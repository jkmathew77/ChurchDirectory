<?php
/**
 * REST API controller for invite acceptance.
 * Handles invite validation and account creation for approved applicants.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Invites extends CD_API_Base {

    public function register_routes() {
        // GET /invites/validate — check token validity
        register_rest_route( CD_API_NAMESPACE, '/invites/validate', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'validate_invite' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // POST /invites/accept — create account from invite
        register_rest_route( CD_API_NAMESPACE, '/invites/accept', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'accept_invite' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );
    }

    /**
     * Validate an invite token and return applicant info for pre-fill.
     */
    public function validate_invite( WP_REST_Request $request ) {
        global $wpdb;

        $token = sanitize_text_field( $request->get_param( 'token' ) ?: '' );
        $email = sanitize_email( $request->get_param( 'email' ) ?: '' );

        if ( empty( $token ) || empty( $email ) ) {
            return $this->error( 'missing_params', __( 'Invalid invitation link.', 'community-directory' ) );
        }

        $invite = $this->find_valid_invite( $token, $email );
        if ( is_wp_error( $invite ) ) {
            return $this->error( $invite->get_error_code(), $invite->get_error_message() );
        }

        // Get the applicant name
        $applications_table = CD_Database::table( 'applications' );
        $app = $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name, last_name FROM {$applications_table} WHERE id = %d",
            $invite->application_id
        ) );

        $name = $app ? trim( $app->first_name . ' ' . $app->last_name ) : '';

        return $this->success( array(
            'valid' => true,
            'name'  => $name,
            'email' => $invite->email,
        ) );
    }

    /**
     * Accept an invite: create WP user, link to member, grant capability, auto-login.
     */
    public function accept_invite( WP_REST_Request $request ) {
        global $wpdb;

        $token    = sanitize_text_field( $request->get_param( 'token' ) ?: '' );
        $email    = sanitize_email( $request->get_param( 'email' ) ?: '' );
        $password = $request->get_param( 'password' ) ?: '';

        if ( empty( $token ) || empty( $email ) || empty( $password ) ) {
            return $this->error( 'missing_params', __( 'All fields are required.', 'community-directory' ) );
        }

        if ( strlen( $password ) < 8 ) {
            return $this->error( 'weak_password', __( 'Password must be at least 8 characters.', 'community-directory' ) );
        }

        // Find and validate the invite
        $invite = $this->find_valid_invite( $token, $email );
        if ( is_wp_error( $invite ) ) {
            return $this->error( $invite->get_error_code(), $invite->get_error_message() );
        }

        // Race condition guard: atomically mark invite as used
        $invites_table = CD_Database::table( 'invites' );
        $marked = $wpdb->query( $wpdb->prepare(
            "UPDATE {$invites_table} SET used_at = %s WHERE id = %d AND used_at IS NULL",
            current_time( 'mysql' ),
            $invite->id
        ) );

        if ( 0 === $marked ) {
            return $this->error( 'invite_used', __( 'This invitation has already been used.', 'community-directory' ) );
        }

        // Get application data for display name
        $applications_table = CD_Database::table( 'applications' );
        $app = $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name, last_name FROM {$applications_table} WHERE id = %d",
            $invite->application_id
        ) );

        $display_name = $app ? trim( $app->first_name . ' ' . $app->last_name ) : $email;

        // Check if WP user already exists
        $existing_user = get_user_by( 'email', $email );
        if ( $existing_user ) {
            $wp_user_id = $existing_user->ID;
            // Update password
            wp_set_password( $password, $wp_user_id );
        } else {
            // Create WP user
            $wp_user_id = wp_create_user( $email, $password, $email );
            if ( is_wp_error( $wp_user_id ) ) {
                // Undo the used_at mark
                $wpdb->update( $invites_table,
                    array( 'used_at' => null ),
                    array( 'id' => $invite->id )
                );
                return $this->error( 'user_creation_failed', $wp_user_id->get_error_message(), 500 );
            }

            // Set display name
            wp_update_user( array(
                'ID'           => $wp_user_id,
                'display_name' => $display_name,
                'first_name'   => $app ? $app->first_name : '',
                'last_name'    => $app ? $app->last_name : '',
            ) );
        }

        // Grant cd_member capability
        CD_Capabilities::grant_cap( $wp_user_id, 'cd_member' );

        // Link member record to WP user
        $members_table = CD_Database::table( 'members' );
        $wpdb->update( $members_table, array(
            'wp_user_id'   => $wp_user_id,
            'activated_at' => current_time( 'mysql' ),
        ), array( 'application_id' => $invite->application_id ), array( '%d', '%s' ), array( '%d' ) );

        // Auto-login
        wp_set_current_user( $wp_user_id );
        wp_set_auth_cookie( $wp_user_id, true );

        // Audit log
        $member = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$members_table} WHERE application_id = %d",
            $invite->application_id
        ) );
        CD_Audit_Logger::log( CD_Audit_Logger::MEMBER_ACTIVATED, $wp_user_id, $member ? $member->id : null, array(
            'email' => $email,
        ) );

        // Redirect to profile edit so new member can complete their profile
        $base_slug = get_option( 'cd_base_slug', 'community' );
        $redirect  = home_url( $base_slug . '/profile/edit/' );

        return $this->success( array(
            'message'  => __( 'Account created successfully! Let\'s complete your profile...', 'community-directory' ),
            'redirect' => $redirect,
        ), array(), 201 );
    }

    /**
     * Find a valid (unused, unexpired) invite by token and email.
     *
     * @return object|WP_Error The invite row or error.
     */
    private function find_valid_invite( $token, $email ) {
        global $wpdb;

        $token_hash = hash( 'sha256', $token );
        $invites_table = CD_Database::table( 'invites' );

        $invite = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$invites_table} WHERE token_hash = %s AND used_at IS NULL",
            $token_hash
        ) );

        if ( ! $invite ) {
            return new WP_Error( 'invalid_token', __( 'This invitation link is invalid or has already been used.', 'community-directory' ) );
        }

        // Verify email matches
        if ( strtolower( $invite->email ) !== strtolower( $email ) ) {
            return new WP_Error( 'email_mismatch', __( 'This invitation link does not match your email address.', 'community-directory' ) );
        }

        // Check expiry
        if ( strtotime( $invite->expires_at ) < time() ) {
            return new WP_Error( 'invite_expired', __( 'This invitation has expired. Please contact the church office for a new invitation.', 'community-directory' ) );
        }

        return $invite;
    }
}
