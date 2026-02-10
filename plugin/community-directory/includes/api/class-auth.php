<?php
/**
 * REST API controller for authentication.
 * Handles email/password login, Google OAuth, password reset, and email lookup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Auth extends CD_API_Base {

    public function register_routes() {
        // POST /auth/login — email/password login
        register_rest_route( CD_API_NAMESPACE, '/auth/login', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'login' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // GET /auth/google — Get Google OAuth URL
        register_rest_route( CD_API_NAMESPACE, '/auth/google', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'google_auth_url' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // GET /auth/google/callback — Handle Google OAuth callback
        register_rest_route( CD_API_NAMESPACE, '/auth/google/callback', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'google_callback' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // POST /auth/password-reset — Request password reset
        register_rest_route( CD_API_NAMESPACE, '/auth/password-reset', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'request_password_reset' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // POST /auth/password-reset/confirm — Confirm password reset with token
        register_rest_route( CD_API_NAMESPACE, '/auth/password-reset/confirm', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'confirm_password_reset' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // POST /auth/email-lookup — "Can't remember your email?" lookup
        register_rest_route( CD_API_NAMESPACE, '/auth/email-lookup', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'email_lookup' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // POST /auth/logout — Logout
        register_rest_route( CD_API_NAMESPACE, '/auth/logout', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'logout' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );
    }

    /**
     * Email/password login.
     */
    public function login( WP_REST_Request $request ) {
        $email    = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $email ) || empty( $password ) ) {
            return $this->error( 'missing_credentials', __( 'Please enter your email and password.', 'community-directory' ) );
        }

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';

        // Rate limit: configurable, default 5 per 15 min per IP
        $limit = (int) get_option( 'cd_login_rate_limit', 5 );
        if ( $this->is_rate_limited( 'login_' . $ip, $limit, 15 * MINUTE_IN_SECONDS ) ) {
            return $this->error( 'rate_limited', __( 'Too many login attempts. Please try again in 15 minutes.', 'community-directory' ), 429 );
        }

        // Also rate limit per email
        if ( $this->is_rate_limited( 'login_email_' . md5( $email ), $limit, 15 * MINUTE_IN_SECONDS ) ) {
            return $this->error( 'rate_limited', __( 'Too many login attempts for this account. Please try again later.', 'community-directory' ), 429 );
        }

        // Authenticate with WordPress
        $user = wp_authenticate( $email, $password );

        if ( is_wp_error( $user ) ) {
            CD_Audit_Logger::log( CD_Audit_Logger::LOGIN_FAILED, null, null, array(
                'email' => $email,
                'ip'    => $ip,
            ) );
            return $this->error( 'invalid_credentials', __( 'Invalid email or password.', 'community-directory' ) );
        }

        // Check if user has cd_member capability (or is admin)
        if ( ! user_can( $user, 'cd_member' ) && ! user_can( $user, 'manage_options' ) ) {
            return $this->error( 'not_member', __( 'Your account is not associated with a directory membership. Please contact the church office.', 'community-directory' ) );
        }

        // Check member status (handle deactivated, suspended, etc.)
        $member_status = $this->check_member_status( $user->ID );
        if ( is_wp_error( $member_status ) ) {
            return $this->error( $member_status->get_error_code(), $member_status->get_error_message() );
        }

        // Set auth cookie and log in
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        CD_Audit_Logger::log( CD_Audit_Logger::LOGIN_SUCCESS, $user->ID, null, array(
            'method' => 'email',
            'ip'     => $ip,
        ) );

        return $this->success( array(
            'message'  => __( 'Login successful.', 'community-directory' ),
            'redirect' => home_url( get_option( 'cd_base_slug', 'community' ) . '/directory/' ),
        ) );
    }

    /**
     * Check member status — block deactivated/suspended members, reactivate self-deactivated.
     */
    private function check_member_status( $user_id ) {
        global $wpdb;

        $members_table = CD_Database::table( 'members' );
        $member = $wpdb->get_row( $wpdb->prepare(
            "SELECT status FROM {$members_table} WHERE wp_user_id = %d",
            $user_id
        ) );

        if ( ! $member ) {
            return user_can( $user_id, 'manage_options' ) ? true : new WP_Error( 'no_member', __( 'No membership record found.', 'community-directory' ) );
        }

        switch ( $member->status ) {
            case 'active':
                return true;
            case 'self_deactivated':
                $wpdb->update(
                    $members_table,
                    array( 'status' => 'active', 'deactivated_at' => null, 'deactivation_reason' => null ),
                    array( 'wp_user_id' => $user_id ),
                    array( '%s', '%s', '%s' ),
                    array( '%d' )
                );
                return true;
            case 'inactive':
                return new WP_Error( 'member_inactive', __( 'Your membership is inactive. Please contact the church office.', 'community-directory' ) );
            case 'suspended':
                return new WP_Error( 'member_suspended', __( 'Your account has been suspended. Please contact the church office.', 'community-directory' ) );
            case 'deletion_requested':
                return new WP_Error( 'deletion_pending', __( 'Your account has a pending deletion request. Please contact the church office to cancel it.', 'community-directory' ) );
            default:
                return true;
        }
    }

    /**
     * Return the Google OAuth URL for member login (or invite acceptance).
     *
     * Optional params: invite_token, invite_email — when present, the callback
     * will handle invite acceptance instead of normal login.
     */
    public function google_auth_url( WP_REST_Request $request ) {
        $client_id = get_option( 'cd_google_client_id', '' );
        if ( empty( $client_id ) ) {
            return $this->error( 'not_configured', __( 'Google sign-in is not configured. Please use email and password.', 'community-directory' ) );
        }

        $redirect_uri = rest_url( CD_API_NAMESPACE . '/auth/google/callback' );
        $nonce = wp_create_nonce( 'cd_google_login' );

        // If invite params are passed, store them in a transient keyed by nonce
        $invite_token = sanitize_text_field( $request->get_param( 'invite_token' ) ?: '' );
        $invite_email = sanitize_email( $request->get_param( 'invite_email' ) ?: '' );
        if ( ! empty( $invite_token ) && ! empty( $invite_email ) ) {
            set_transient( 'cd_google_invite_' . $nonce, array(
                'token' => $invite_token,
                'email' => $invite_email,
            ), 600 ); // 10 minutes
        }

        $params = array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'state'         => $nonce,
        );

        return $this->success( array(
            'auth_url' => 'https://accounts.google.com/o/oauth2/auth?' . http_build_query( $params ),
        ) );
    }

    /**
     * Handle Google OAuth callback — exchange code, find/link member, log in.
     */
    public function google_callback( WP_REST_Request $request ) {
        $base_slug = get_option( 'cd_base_slug', 'community' );
        $login_url = home_url( $base_slug . '/login/' );

        $state = sanitize_text_field( $request->get_param( 'state' ) ?? '' );
        if ( ! wp_verify_nonce( $state, 'cd_google_login' ) ) {
            wp_safe_redirect( $login_url . '?error=invalid_state' );
            exit;
        }

        if ( $request->get_param( 'error' ) ) {
            wp_safe_redirect( $login_url . '?error=' . urlencode( sanitize_text_field( $request->get_param( 'error' ) ) ) );
            exit;
        }

        $code = sanitize_text_field( $request->get_param( 'code' ) ?? '' );
        if ( empty( $code ) ) {
            wp_safe_redirect( $login_url . '?error=no_code' );
            exit;
        }

        // Exchange code for tokens
        $client_id     = get_option( 'cd_google_client_id', '' );
        $encrypted_secret = get_option( 'cd_google_client_secret', '' );
        $client_secret = ! empty( $encrypted_secret ) ? CD_Encryption::decrypt( $encrypted_secret ) : '';
        $redirect_uri  = rest_url( CD_API_NAMESPACE . '/auth/google/callback' );

        $token_response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $token_response ) ) {
            wp_safe_redirect( $login_url . '?error=token_exchange_failed' );
            exit;
        }

        $token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
        if ( ! empty( $token_body['error'] ) ) {
            wp_safe_redirect( $login_url . '?error=' . urlencode( $token_body['error'] ) );
            exit;
        }

        // Decode ID token (JWT) to get user info
        $id_token = $token_body['id_token'] ?? '';
        if ( empty( $id_token ) ) {
            wp_safe_redirect( $login_url . '?error=no_id_token' );
            exit;
        }

        $parts = explode( '.', $id_token );
        if ( count( $parts ) !== 3 ) {
            wp_safe_redirect( $login_url . '?error=invalid_token' );
            exit;
        }

        $payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );
        if ( ! $payload || empty( $payload['email'] ) ) {
            wp_safe_redirect( $login_url . '?error=invalid_payload' );
            exit;
        }

        $google_email = sanitize_email( $payload['email'] );
        $google_id    = sanitize_text_field( $payload['sub'] );

        global $wpdb;
        $members_table = CD_Database::table( 'members' );

        // ── Invite flow: check if this Google login was initiated from the invite page ──
        $invite_context = get_transient( 'cd_google_invite_' . $state );
        if ( $invite_context && ! empty( $invite_context['token'] ) && ! empty( $invite_context['email'] ) ) {
            delete_transient( 'cd_google_invite_' . $state );
            $this->handle_google_invite_accept( $invite_context, $google_email, $google_id, $payload, $base_slug );
            exit;
        }

        // Find member by google_id
        $member = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$members_table} WHERE google_id = %s AND status IN ('active', 'self_deactivated')",
            $google_id
        ) );

        // If not found, try by email via WP user
        if ( ! $member ) {
            $user = get_user_by( 'email', $google_email );
            if ( $user ) {
                $member = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$members_table} WHERE wp_user_id = %d",
                    $user->ID
                ) );
                // Link google_id for future logins
                if ( $member ) {
                    $wpdb->update( $members_table, array( 'google_id' => $google_id ), array( 'id' => $member->id ), array( '%s' ), array( '%d' ) );
                }
            }
        }

        // CAPTURE AVATAR: If member exists, check/update avatar from Google
        if ( $member && isset( $payload['picture'] ) ) {
            $profiles_table = CD_Database::table( 'directory_profiles' );
            // Check if member provides a picture and current avatar is empty or we want to sync
            // For now, let's only update if empty to avoid overwriting custom ones.
            $profile_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$profiles_table} WHERE member_id = %d AND (avatar_url IS NULL OR avatar_url = '')", $member->id ) );
            
            if ( $profile_id ) {
                $wpdb->update( 
                    $profiles_table, 
                    array( 'avatar_url' => esc_url_raw( $payload['picture'] ) ), 
                    array( 'id' => $profile_id ), 
                    array( '%s' ), 
                    array( '%d' ) 
                );
            }
        }

        if ( ! $member || ! $member->wp_user_id ) {
            // No member record found → redirect to application form
            $apply_url = home_url( $base_slug . '/apply/' );
            $apply_params = array(
                'email'      => $google_email,
                'first_name' => $payload['given_name'] ?? '',
                'last_name'  => $payload['family_name'] ?? '',
                'avatar_url' => $payload['picture'] ?? '',
                'google_id'  => $google_id, // Pass invisible token to link later? (Optional security risk, skipping for now)
            );
            wp_safe_redirect( $apply_url . '?' . http_build_query( $apply_params ) );
            exit;
        }

        $status_check = $this->check_member_status( $member->wp_user_id );
        if ( is_wp_error( $status_check ) ) {
            wp_safe_redirect( $login_url . '?error=' . urlencode( $status_check->get_error_message() ) );
            exit;
        }

        wp_set_current_user( $member->wp_user_id );
        wp_set_auth_cookie( $member->wp_user_id, true );

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
        CD_Audit_Logger::log( CD_Audit_Logger::LOGIN_SUCCESS, $member->wp_user_id, null, array(
            'method' => 'google',
            'ip'     => $ip,
        ) );

        wp_safe_redirect( home_url( $base_slug . '/directory/' ) );
        exit;
    }

    /**
     * Request a password reset email.
     * Always returns success to prevent email enumeration.
     */
    public function request_password_reset( WP_REST_Request $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );

        $success_msg = __( 'If an account exists with that email, a reset link has been sent.', 'community-directory' );

        if ( empty( $email ) ) {
            return $this->success( array( 'message' => $success_msg ) );
        }

        // Rate limit
        $limit = (int) get_option( 'cd_password_reset_limit', 3 );
        if ( $this->is_rate_limited( 'pwd_reset_' . md5( $email ), $limit, HOUR_IN_SECONDS ) ) {
            return $this->success( array( 'message' => $success_msg ) );
        }

        $user = get_user_by( 'email', $email );
        if ( $user && user_can( $user, 'cd_member' ) ) {
            $token      = bin2hex( random_bytes( 32 ) );
            $token_hash = hash( 'sha256', $token );

            global $wpdb;
            $table = CD_Database::table( 'password_resets' );
            $wpdb->insert( $table, array(
                'user_id'    => $user->ID,
                'token_hash' => $token_hash,
                'created_at' => current_time( 'mysql' ),
            ), array( '%d', '%s', '%s' ) );

            CD_Email_Templates::send_password_reset( $email, $user->display_name, $token );

            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
            CD_Audit_Logger::log( CD_Audit_Logger::PASSWORD_RESET_REQUESTED, null, $user->ID, array( 'ip' => $ip ) );
        }

        return $this->success( array( 'message' => $success_msg ) );
    }

    /**
     * Confirm password reset with token + new password.
     */
    public function confirm_password_reset( WP_REST_Request $request ) {
        $token    = sanitize_text_field( $request->get_param( 'token' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $token ) || empty( $password ) ) {
            return $this->error( 'missing_params', __( 'Token and password are required.', 'community-directory' ) );
        }

        if ( strlen( $password ) < 8 ) {
            return $this->error( 'weak_password', __( 'Password must be at least 8 characters.', 'community-directory' ) );
        }

        global $wpdb;
        $table      = CD_Database::table( 'password_resets' );
        $token_hash = hash( 'sha256', $token );

        $reset = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE token_hash = %s AND used_at IS NULL AND created_at > DATE_SUB(%s, INTERVAL 1 HOUR)",
            $token_hash,
            current_time( 'mysql' )
        ) );

        if ( ! $reset ) {
            return $this->error( 'invalid_token', __( 'This reset link is invalid or has expired.', 'community-directory' ), 404 );
        }

        // Race condition guard
        $affected = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET used_at = %s WHERE id = %d AND used_at IS NULL",
            current_time( 'mysql' ),
            $reset->id
        ) );

        if ( ! $affected ) {
            return $this->error( 'token_used', __( 'This reset link has already been used.', 'community-directory' ) );
        }

        wp_set_password( $password, $reset->user_id );

        CD_Audit_Logger::log( CD_Audit_Logger::PASSWORD_CHANGED, $reset->user_id );

        return $this->success( array(
            'message' => __( 'Your password has been reset. You can now log in.', 'community-directory' ),
        ) );
    }

    /**
     * Email lookup by name + phone. Sends a masked email hint.
     */
    public function email_lookup( WP_REST_Request $request ) {
        $name  = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
        $phone = sanitize_text_field( $request->get_param( 'phone' ) ?? '' );

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';

        if ( $this->is_rate_limited( 'email_lookup_' . $ip, 3, HOUR_IN_SECONDS ) ) {
            return $this->success( array( 'message' => __( 'If a matching account was found, a hint has been sent.', 'community-directory' ) ) );
        }

        if ( ! empty( $name ) && ! empty( $phone ) ) {
            global $wpdb;
            $profiles_table = CD_Database::table( 'directory_profiles' );
            $members_table  = CD_Database::table( 'members' );

            $name_parts = preg_split( '/\s+/', trim( $name ), 2 );
            $first_name = $name_parts[0] ?? '';
            $last_name  = $name_parts[1] ?? '';

            if ( $first_name && $last_name ) {
                $profile = $wpdb->get_row( $wpdb->prepare(
                    "SELECT p.emails, p.first_name FROM {$profiles_table} p
                     JOIN {$members_table} m ON p.member_id = m.id
                     WHERE m.status = 'active' AND LOWER(p.first_name) = LOWER(%s) AND LOWER(p.last_name) = LOWER(%s)
                     LIMIT 1",
                    $first_name, $last_name
                ) );

                if ( $profile && ! empty( $profile->emails ) ) {
                    $emails = json_decode( $profile->emails, true );
                    if ( ! empty( $emails[0]['value'] ) ) {
                        $member_email = $emails[0]['value'];
                        $parts  = explode( '@', $member_email );
                        $masked = substr( $parts[0], 0, 2 ) . str_repeat( '*', max( 3, strlen( $parts[0] ) - 2 ) ) . '@' . $parts[1];
                        CD_Email_Templates::send_email_hint( $member_email, $profile->first_name, $masked );
                    }
                }
            }
        }

        return $this->success( array( 'message' => __( 'If a matching account was found, a hint has been sent.', 'community-directory' ) ) );
    }

    /**
     * Handle invite acceptance via Google OAuth.
     * Called from google_callback() when invite context transient exists.
     */
    private function handle_google_invite_accept( $invite_context, $google_email, $google_id, $payload, $base_slug ) {
        global $wpdb;

        $invite_token = $invite_context['token'];
        $invite_email = $invite_context['email'];
        $login_url    = home_url( $base_slug . '/login/' );

        // Validate the invite token
        $token_hash    = hash( 'sha256', $invite_token );
        $invites_table = CD_Database::table( 'invites' );

        $invite = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$invites_table} WHERE token_hash = %s AND used_at IS NULL",
            $token_hash
        ) );

        if ( ! $invite ) {
            wp_safe_redirect( $login_url . '?error=' . urlencode( 'This invitation has already been used or is invalid.' ) );
            return;
        }

        if ( strtolower( $invite->email ) !== strtolower( $invite_email ) ) {
            wp_safe_redirect( $login_url . '?error=' . urlencode( 'Invitation email mismatch.' ) );
            return;
        }

        if ( strtotime( $invite->expires_at ) < time() ) {
            wp_safe_redirect( $login_url . '?error=' . urlencode( 'This invitation has expired.' ) );
            return;
        }

        // Atomically mark invite as used
        $marked = $wpdb->query( $wpdb->prepare(
            "UPDATE {$invites_table} SET used_at = %s WHERE id = %d AND used_at IS NULL",
            current_time( 'mysql' ),
            $invite->id
        ) );
        if ( 0 === $marked ) {
            wp_safe_redirect( $login_url . '?error=' . urlencode( 'This invitation has already been used.' ) );
            return;
        }

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        // Find the member to link
        $member_id_to_link = null;
        if ( ! empty( $invite->member_id ) ) {
            $member_id_to_link = (int) $invite->member_id;
        } elseif ( ! empty( $invite->application_id ) ) {
            $app_member = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$members_table} WHERE application_id = %d",
                $invite->application_id
            ) );
            if ( $app_member ) {
                $member_id_to_link = (int) $app_member->id;
            }
        }

        // Create or find WP user by Google email
        $existing_user = get_user_by( 'email', $google_email );
        $wp_user_id    = 0;

        // Get display name from profile or payload
        $display_name = '';
        $first_name   = $payload['given_name'] ?? '';
        $last_name    = $payload['family_name'] ?? '';

        if ( $member_id_to_link ) {
            $profile = $wpdb->get_row( $wpdb->prepare(
                "SELECT first_name, last_name FROM {$profiles_table} WHERE member_id = %d",
                $member_id_to_link
            ) );
            if ( $profile ) {
                $first_name   = $profile->first_name ?: $first_name;
                $last_name    = $profile->last_name ?: $last_name;
            }
        }
        $display_name = trim( $first_name . ' ' . $last_name ) ?: $google_email;

        if ( $existing_user ) {
            $wp_user_id = $existing_user->ID;
        } else {
            $random_password = wp_generate_password( 24, true, true );
            $wp_user_id = wp_create_user( $google_email, $random_password, $google_email );
            if ( is_wp_error( $wp_user_id ) ) {
                // Undo the used_at mark
                $wpdb->update( $invites_table, array( 'used_at' => null ), array( 'id' => $invite->id ) );
                wp_safe_redirect( $login_url . '?error=' . urlencode( 'Account creation failed: ' . $wp_user_id->get_error_message() ) );
                return;
            }

            wp_update_user( array(
                'ID'           => $wp_user_id,
                'display_name' => $display_name,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
            ) );
        }

        // Grant cd_member capability
        CD_Capabilities::grant_cap( $wp_user_id, 'cd_member' );

        // Link member record to WP user and Google ID
        if ( $member_id_to_link ) {
            $wpdb->update( $members_table, array(
                'wp_user_id'   => $wp_user_id,
                'google_id'    => $google_id,
                'activated_at' => current_time( 'mysql' ),
            ), array( 'id' => $member_id_to_link ), array( '%d', '%s', '%s' ), array( '%d' ) );

            // Set avatar from Google if profile has no avatar
            if ( ! empty( $payload['picture'] ) ) {
                $has_avatar = $wpdb->get_var( $wpdb->prepare(
                    "SELECT avatar_url FROM {$profiles_table} WHERE member_id = %d",
                    $member_id_to_link
                ) );
                if ( empty( $has_avatar ) ) {
                    $wpdb->update(
                        $profiles_table,
                        array( 'avatar_url' => esc_url_raw( $payload['picture'] ), 'avatar_source' => 'google' ),
                        array( 'member_id' => $member_id_to_link ),
                        array( '%s', '%s' ),
                        array( '%d' )
                    );
                }
            }
        }

        // Auto-login
        wp_set_current_user( $wp_user_id );
        wp_set_auth_cookie( $wp_user_id, true );

        CD_Audit_Logger::log( CD_Audit_Logger::MEMBER_ACTIVATED, $wp_user_id, $member_id_to_link, array(
            'email'  => $google_email,
            'method' => 'google_invite',
        ) );

        // Redirect to profile edit
        wp_safe_redirect( home_url( $base_slug . '/profile/edit/' ) );
    }

    /**
     * Logout.
     */
    public function logout( WP_REST_Request $request ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            wp_logout();
            CD_Audit_Logger::log( CD_Audit_Logger::LOGOUT, $user_id );
        }
        return $this->success( array( 'message' => __( 'Logged out.', 'community-directory' ) ) );
    }
}
