<?php
/**
 * REST API controller for admin operations.
 * Handles application review, approval workflow, officer management,
 * registrations, and member search.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Admin extends CD_API_Base {

    public function register_routes() {
        // GET /admin/registrations — all applications including unverified
        register_rest_route( CD_API_NAMESPACE, '/admin/registrations', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_registrations' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /admin/registrations/{id}/resend-verification
        register_rest_route( CD_API_NAMESPACE, '/admin/registrations/(?P<id>\d+)/resend-verification', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'resend_verification' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /admin/applications — verified applications for review
        register_rest_route( CD_API_NAMESPACE, '/admin/applications', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_applications' ),
            'permission_callback' => array( $this, 'permission_secretary' ),
        ) );

        // PUT /admin/applications/{id} — approve/reject/hold/info
        register_rest_route( CD_API_NAMESPACE, '/admin/applications/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_application' ),
            'permission_callback' => array( $this, 'permission_secretary' ),
        ) );

        // POST /admin/applications/{id}/resend-invite
        register_rest_route( CD_API_NAMESPACE, '/admin/applications/(?P<id>\d+)/resend-invite', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'resend_invite' ),
            'permission_callback' => array( $this, 'permission_secretary' ),
        ) );

        // GET /admin/officers — list active officers
        register_rest_route( CD_API_NAMESPACE, '/admin/officers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_officers' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /admin/officers — add officer
        register_rest_route( CD_API_NAMESPACE, '/admin/officers', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_officer' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // DELETE /admin/officers/{id} — remove officer
        register_rest_route( CD_API_NAMESPACE, '/admin/officers/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'remove_officer' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /admin/officers/rotate — annual rotation
        register_rest_route( CD_API_NAMESPACE, '/admin/officers/rotate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'rotate_officers' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /admin/members/search — search members for officer autocomplete
        register_rest_route( CD_API_NAMESPACE, '/admin/members/search', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'search_members' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /admin/google-sync/preview — fetch Google contacts + match against members
        register_rest_route( CD_API_NAMESPACE, '/admin/google-sync/preview', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'google_sync_preview' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /admin/google-sync/confirm — import selected contacts as members
        register_rest_route( CD_API_NAMESPACE, '/admin/google-sync/confirm', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'google_sync_confirm' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );
    }

    /* ──────────────────────────────────────
     * REGISTRATIONS (All applications)
     * ──────────────────────────────────── */

    /**
     * List all applications including unverified.
     */
    public function list_registrations( WP_REST_Request $request ) {
        global $wpdb;

        $table  = CD_Database::table( 'applications' );
        $status = sanitize_text_field( $request->get_param( 'status' ) ?: '' );
        $page   = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
        $offset = ( $page - 1 ) * $per;

        // Status filter
        $where = '1=1';
        $args  = array();
        if ( $status && 'all' !== $status ) {
            $where .= ' AND status = %s';
            $args[] = $status;
        }

        // Count by status for tabs
        $counts_raw = $wpdb->get_results(
            "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status"
        );
        $counts = array( 'all' => 0 );
        foreach ( $counts_raw as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }

        // Fetch rows
        $query = "SELECT * FROM {$table} WHERE {$where} ORDER BY submitted_at DESC LIMIT %d OFFSET %d";
        $args[] = $per;
        $args[] = $offset;
        $rows = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        $total_query = "SELECT COUNT(*) FROM {$table} WHERE " . ( $status && 'all' !== $status ? $wpdb->prepare( 'status = %s', $status ) : '1=1' );
        $total = (int) $wpdb->get_var( $total_query );

        return $this->success( array(
            'registrations' => $rows,
            'counts'        => $counts,
        ), array(
            'page'     => $page,
            'per_page' => $per,
            'total'    => $total,
            'pages'    => ceil( $total / $per ),
        ) );
    }

    /**
     * Resend verification email for a pending_verification application.
     */
    public function resend_verification( WP_REST_Request $request ) {
        global $wpdb;

        $app_id = (int) $request->get_param( 'id' );
        $table  = CD_Database::table( 'applications' );

        $app = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $app_id
        ) );

        if ( ! $app ) {
            return $this->error( 'not_found', __( 'Application not found.', 'community-directory' ), 404 );
        }

        if ( 'pending_verification' !== $app->status ) {
            return $this->error( 'invalid_status', __( 'Application is not pending verification.', 'community-directory' ) );
        }

        // Rate limit: 5 per email per 24 hours
        if ( $this->is_rate_limited( 'resend_verify_' . $app->email, 5, DAY_IN_SECONDS ) ) {
            return $this->error( 'rate_limited', __( 'Verification email resend limit reached. Try again tomorrow.', 'community-directory' ), 429 );
        }

        // Generate new token
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );

        $wpdb->update( $table, array(
            'verification_token_hash' => $token_hash,
            'verification_sent_at'    => current_time( 'mysql' ),
        ), array( 'id' => $app_id ), array( '%s', '%s' ), array( '%d' ) );

        // Send verification email
        $base_slug  = get_option( 'cd_base_slug', 'community' );
        $verify_url = home_url( $base_slug . '/verify/' . $token . '/' );
        $expiry     = (int) get_option( 'cd_verification_expiry', 48 );

        $subject = __( 'Verify your email — St. Thekla Community Directory', 'community-directory' );
        $message = sprintf(
            __( "Hello %s,\n\nPlease verify your email address to complete your application to the St. Thekla Community Directory.\n\nClick here to verify: %s\n\nThis link expires in %d hours.\n\nSt. Thekla Church", 'community-directory' ),
            esc_html( $app->first_name ),
            esc_url( $verify_url ),
            $expiry
        );
        wp_mail( $app->email, $subject, $message );

        CD_Audit_Logger::log( CD_Audit_Logger::VERIFICATION_RESENT, get_current_user_id(), $app_id );

        return $this->success( array(
            'message' => __( 'Verification email resent.', 'community-directory' ),
        ) );
    }

    /* ──────────────────────────────────────
     * APPLICATIONS (Verified, for review)
     * ──────────────────────────────────── */

    /**
     * List verified applications for officer review.
     */
    public function list_applications( WP_REST_Request $request ) {
        global $wpdb;

        $table  = CD_Database::table( 'applications' );
        $status = sanitize_text_field( $request->get_param( 'status' ) ?: '' );
        $page   = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
        $offset = ( $page - 1 ) * $per;

        // Only show verified applications (not pending_verification)
        $valid_statuses = array( 'new', 'under_review', 'on_hold', 'approved', 'not_approved' );

        $where = "status IN ('" . implode( "','", $valid_statuses ) . "')";
        $args  = array();

        if ( $status && in_array( $status, $valid_statuses, true ) ) {
            $where = 'status = %s';
            $args[] = $status;
        }

        // Count by status for tabs
        $counts_raw = $wpdb->get_results(
            "SELECT status, COUNT(*) as cnt FROM {$table}
             WHERE status IN ('" . implode( "','", $valid_statuses ) . "')
             GROUP BY status"
        );
        $counts = array( 'all' => 0 );
        foreach ( $valid_statuses as $s ) {
            $counts[ $s ] = 0;
        }
        foreach ( $counts_raw as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }

        // Build and execute query
        $query = "SELECT a.*, u.display_name as reviewer_name
                  FROM {$table} a
                  LEFT JOIN {$wpdb->users} u ON a.reviewed_by = u.ID
                  WHERE {$where}
                  ORDER BY a.submitted_at DESC
                  LIMIT %d OFFSET %d";
        $args[] = $per;
        $args[] = $offset;
        $rows = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        // Decode form_data JSON
        foreach ( $rows as &$row ) {
            if ( ! empty( $row->form_data ) ) {
                $row->form_data = json_decode( $row->form_data );
            }
        }

        $total_where = $status && in_array( $status, $valid_statuses, true )
            ? $wpdb->prepare( 'status = %s', $status )
            : "status IN ('" . implode( "','", $valid_statuses ) . "')";
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$total_where}" );

        return $this->success( array(
            'applications' => $rows,
            'counts'       => $counts,
        ), array(
            'page'     => $page,
            'per_page' => $per,
            'total'    => $total,
            'pages'    => ceil( $total / $per ),
        ) );
    }

    /**
     * Update application status (approve/reject/hold/request-info).
     */
    public function update_application( WP_REST_Request $request ) {
        global $wpdb;

        $app_id = (int) $request->get_param( 'id' );
        $action = sanitize_text_field( $request->get_param( 'action' ) );

        $table = CD_Database::table( 'applications' );
        $app   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $app_id ) );

        if ( ! $app ) {
            return $this->error( 'not_found', __( 'Application not found.', 'community-directory' ), 404 );
        }

        switch ( $action ) {
            case 'approve':
                return $this->approve_application( $app, $request );
            case 'reject':
                return $this->reject_application( $app, $request );
            case 'request_info':
                return $this->request_info( $app, $request );
            case 'hold':
                return $this->hold_application( $app, $request );
            default:
                return $this->error( 'invalid_action', __( 'Invalid action.', 'community-directory' ) );
        }
    }

    /**
     * Approve an application: create member + profile + invite.
     */
    private function approve_application( $app, WP_REST_Request $request ) {
        global $wpdb;

        $allowed = array( 'new', 'under_review', 'on_hold' );
        if ( ! in_array( $app->status, $allowed, true ) ) {
            return $this->error( 'invalid_status', __( 'This application cannot be approved in its current state.', 'community-directory' ) );
        }

        $user_id = get_current_user_id();
        $notes   = sanitize_textarea_field( $request->get_param( 'notes' ) ?: '' );
        $form_data = ! empty( $app->form_data ) ? json_decode( $app->form_data, true ) : array();

        // 1. Update application status
        $applications_table = CD_Database::table( 'applications' );
        $wpdb->update( $applications_table, array(
            'status'      => 'approved',
            'reviewed_by' => $user_id,
            'reviewed_at' => current_time( 'mysql' ),
            'notes'       => $notes,
        ), array( 'id' => $app->id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );

        // 2. Create member record
        $members_table = CD_Database::table( 'members' );
        $uuid = wp_generate_uuid4();
        $wpdb->insert( $members_table, array(
            'uuid'           => $uuid,
            'application_id' => $app->id,
            'status'         => 'active',
            'member_since'   => current_time( 'Y-m-d' ),
            'created_at'     => current_time( 'mysql' ),
        ), array( '%s', '%d', '%s', '%s', '%s' ) );
        $member_id = $wpdb->insert_id;

        // 3. Create directory profile
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $phones = array();
        if ( ! empty( $form_data['phone'] ) || ! empty( $app->form_data ) ) {
            // Phone is stored as a top-level param on applications
            // We need to reconstruct it; use form data if available
        }

        $profile_data = array(
            'member_id'  => $member_id,
            'first_name' => $app->first_name,
            'last_name'  => $app->last_name,
            'emails'     => wp_json_encode( array( array( 'type' => 'primary', 'value' => $app->email ) ) ),
            'created_at' => current_time( 'mysql' ),
        );

        // Populate from form_data
        if ( ! empty( $form_data['address_line_1'] ) ) {
            $addr_parts = array_filter( array(
                $form_data['address_line_1'] ?? '',
                $form_data['city'] ?? '',
                $form_data['state'] ?? '',
                $form_data['zip'] ?? '',
            ) );
            $profile_data['address_home'] = CD_Encryption::encrypt( implode( ', ', $addr_parts ) );
        }

        if ( ! empty( $form_data['date_of_birth'] ) ) {
            $profile_data['date_of_birth'] = sanitize_text_field( $form_data['date_of_birth'] );
        }
        if ( ! empty( $form_data['date_of_baptism'] ) ) {
            $profile_data['baptism_date'] = sanitize_text_field( $form_data['date_of_baptism'] );
        }
        if ( ! empty( $form_data['profession'] ) ) {
            $profile_data['occupation'] = sanitize_text_field( $form_data['profession'] );
        }
        if ( ! empty( $form_data['ministry_interests'] ) ) {
            $profile_data['ministry_tags'] = wp_json_encode( $form_data['ministry_interests'] );
        }

        $wpdb->insert( $profiles_table, $profile_data );

        // 4. Generate invite token
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );
        $expiry_days = (int) get_option( 'cd_invite_expiry', 14 );

        $invites_table = CD_Database::table( 'invites' );
        $wpdb->insert( $invites_table, array(
            'application_id' => $app->id,
            'email'          => $app->email,
            'token_hash'     => $token_hash,
            'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) ),
            'created_at'     => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s' ) );

        // 5. Send invite email
        CD_Email_Templates::send_invite( $app->email, $app->first_name, $token );

        // 6. Audit log
        CD_Audit_Logger::log( CD_Audit_Logger::APPLICATION_APPROVED, $user_id, $app->id, array(
            'member_uuid' => $uuid,
        ) );
        CD_Audit_Logger::log( CD_Audit_Logger::INVITE_SENT, $user_id, $app->id, array(
            'email' => $app->email,
        ) );

        // 7. Notify officers
        $reviewer = get_userdata( $user_id );
        CD_Email_Templates::notify_officers_of_approval( array(
            'first_name' => $app->first_name,
            'last_name'  => $app->last_name,
            'email'      => $app->email,
        ), $reviewer ? $reviewer->display_name : 'Admin' );

        // 8. Google Contacts sync (if enabled)
        if ( class_exists( 'CD_Google_Contacts' ) && CD_Google_Contacts::is_enabled() ) {
            $contact_data = array(
                'first_name' => $app->first_name,
                'last_name'  => $app->last_name,
                'email'      => $app->email,
                'form_data'  => $form_data,
            );
            $result = CD_Google_Contacts::create_contact( $contact_data );

            if ( is_wp_error( $result ) ) {
                // Queue for retry
                $queue = get_option( 'cd_google_retry_queue', array() );
                $queue[] = array(
                    'member_id'    => $member_id,
                    'member_data'  => $contact_data,
                    'retries'      => 0,
                    'last_attempt' => time(),
                );
                update_option( 'cd_google_retry_queue', $queue );
            } elseif ( ! empty( $result ) ) {
                $wpdb->update( $members_table,
                    array( 'google_contact_id' => sanitize_text_field( $result ) ),
                    array( 'id' => $member_id ),
                    array( '%s' ), array( '%d' )
                );
            }
        }

        return $this->success( array(
            'message'     => __( 'Application approved. Invite email sent.', 'community-directory' ),
            'member_uuid' => $uuid,
        ) );
    }

    /**
     * Reject an application.
     */
    private function reject_application( $app, WP_REST_Request $request ) {
        global $wpdb;

        $reason = sanitize_text_field( $request->get_param( 'rejection_reason' ) ?: 'other' );
        $notes  = sanitize_textarea_field( $request->get_param( 'notes' ) ?: '' );
        $send_email = (bool) $request->get_param( 'send_email' );

        $table = CD_Database::table( 'applications' );
        $wpdb->update( $table, array(
            'status'           => 'not_approved',
            'reviewed_by'      => get_current_user_id(),
            'reviewed_at'      => current_time( 'mysql' ),
            'notes'            => $notes,
            'rejection_reason' => $reason,
        ), array( 'id' => $app->id ), array( '%s', '%d', '%s', '%s', '%s' ), array( '%d' ) );

        if ( $send_email ) {
            CD_Email_Templates::send_rejection( $app->email, $app->first_name, $reason, $notes );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::APPLICATION_REJECTED, get_current_user_id(), $app->id, array(
            'reason'     => $reason,
            'email_sent' => $send_email,
        ) );

        return $this->success( array(
            'message' => __( 'Application marked as not approved.', 'community-directory' ),
        ) );
    }

    /**
     * Request more information from applicant.
     */
    private function request_info( $app, WP_REST_Request $request ) {
        global $wpdb;

        $message_text = sanitize_textarea_field( $request->get_param( 'message' ) ?: '' );
        $notes = sanitize_textarea_field( $request->get_param( 'notes' ) ?: '' );

        if ( empty( $message_text ) ) {
            return $this->error( 'missing_message', __( 'Please provide a message for the applicant.', 'community-directory' ) );
        }

        $table = CD_Database::table( 'applications' );
        $wpdb->update( $table, array(
            'status'      => 'under_review',
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => current_time( 'mysql' ),
            'notes'       => $notes,
        ), array( 'id' => $app->id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );

        CD_Email_Templates::send_info_request( $app->email, $app->first_name, $message_text );

        CD_Audit_Logger::log( CD_Audit_Logger::APPLICATION_SUBMITTED, get_current_user_id(), $app->id, array(
            'action' => 'request_info',
        ) );

        return $this->success( array(
            'message' => __( 'Information request sent to applicant.', 'community-directory' ),
        ) );
    }

    /**
     * Place application on hold.
     */
    private function hold_application( $app, WP_REST_Request $request ) {
        global $wpdb;

        $notes = sanitize_textarea_field( $request->get_param( 'notes' ) ?: '' );

        $table = CD_Database::table( 'applications' );
        $wpdb->update( $table, array(
            'status'      => 'on_hold',
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => current_time( 'mysql' ),
            'notes'       => $notes,
        ), array( 'id' => $app->id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );

        CD_Audit_Logger::log( CD_Audit_Logger::BULK_OPERATION, get_current_user_id(), $app->id, array(
            'action' => 'hold_application',
        ) );

        return $this->success( array(
            'message' => __( 'Application placed on hold.', 'community-directory' ),
        ) );
    }

    /**
     * Resend invite email for an approved application.
     */
    public function resend_invite( WP_REST_Request $request ) {
        global $wpdb;

        $app_id = (int) $request->get_param( 'id' );
        $table  = CD_Database::table( 'applications' );
        $app    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $app_id ) );

        if ( ! $app || 'approved' !== $app->status ) {
            return $this->error( 'invalid', __( 'Application not found or not approved.', 'community-directory' ), 404 );
        }

        // Rate limit: 3 per application per 24 hours
        if ( $this->is_rate_limited( 'resend_invite_' . $app_id, 3, DAY_IN_SECONDS ) ) {
            return $this->error( 'rate_limited', __( 'Invite resend limit reached. Try again tomorrow.', 'community-directory' ), 429 );
        }

        // Invalidate old invite tokens
        $invites_table = CD_Database::table( 'invites' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$invites_table} SET used_at = %s WHERE application_id = %d AND used_at IS NULL",
            current_time( 'mysql' ),
            $app_id
        ) );

        // Generate new token
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );
        $expiry_days = (int) get_option( 'cd_invite_expiry', 14 );

        $wpdb->insert( $invites_table, array(
            'application_id' => $app_id,
            'email'          => $app->email,
            'token_hash'     => $token_hash,
            'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) ),
            'created_at'     => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s' ) );

        // Send invite
        CD_Email_Templates::send_invite( $app->email, $app->first_name, $token );

        CD_Audit_Logger::log( CD_Audit_Logger::INVITE_RESENT, get_current_user_id(), $app_id );

        return $this->success( array(
            'message' => __( 'Invite email resent.', 'community-directory' ),
        ) );
    }

    /* ──────────────────────────────────────
     * OFFICERS
     * ──────────────────────────────────── */

    /**
     * List active officers.
     */
    public function list_officers( WP_REST_Request $request ) {
        global $wpdb;

        $officers_table = CD_Database::table( 'officers' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $officers = $wpdb->get_results(
            "SELECT o.*, p.first_name, p.last_name
             FROM {$officers_table} o
             LEFT JOIN {$profiles_table} p ON o.member_id = p.member_id
             WHERE o.is_active = 1
             ORDER BY o.added_at ASC"
        );

        return $this->success( array( 'officers' => $officers ) );
    }

    /**
     * Add an officer.
     */
    public function add_officer( WP_REST_Request $request ) {
        global $wpdb;

        $member_id = (int) $request->get_param( 'member_id' );
        $title     = sanitize_text_field( $request->get_param( 'title' ) ?: '' );
        $term_label = sanitize_text_field( $request->get_param( 'term_label' ) ?: '' );

        if ( ! $member_id ) {
            return $this->error( 'missing_member', __( 'Please select a member.', 'community-directory' ) );
        }

        // Verify member exists and is active
        $members_table = CD_Database::table( 'members' );
        $member = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, p.first_name, p.last_name FROM {$members_table} m
             LEFT JOIN " . CD_Database::table( 'directory_profiles' ) . " p ON m.id = p.member_id
             WHERE m.id = %d AND m.status = 'active'",
            $member_id
        ) );

        if ( ! $member ) {
            return $this->error( 'invalid_member', __( 'Member not found or not active.', 'community-directory' ), 404 );
        }

        // Check not already an active officer
        $officers_table = CD_Database::table( 'officers' );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$officers_table} WHERE member_id = %d AND is_active = 1",
            $member_id
        ) );

        if ( $existing ) {
            return $this->error( 'already_officer', __( 'This member is already an active officer.', 'community-directory' ) );
        }

        // Get member email from application or profiles
        $app_table = CD_Database::table( 'applications' );
        $email = $wpdb->get_var( $wpdb->prepare(
            "SELECT a.email FROM {$app_table} a WHERE a.id = %d",
            $member->application_id ?: 0
        ) );
        if ( ! $email && $member->wp_user_id ) {
            $user = get_userdata( $member->wp_user_id );
            $email = $user ? $user->user_email : '';
        }

        // Insert officer
        $wpdb->insert( $officers_table, array(
            'member_id'  => $member_id,
            'email'      => $email ?: '',
            'title'      => $title,
            'term_label' => $term_label,
            'added_by'   => get_current_user_id(),
            'added_at'   => current_time( 'mysql' ),
            'is_active'  => 1,
        ), array( '%d', '%s', '%s', '%s', '%d', '%s', '%d' ) );

        // Grant officer capability
        if ( $member->wp_user_id ) {
            CD_Capabilities::grant_cap( $member->wp_user_id, 'cd_officer' );
        }

        // Send notification
        $first_name = $member->first_name ?: '';
        if ( $email ) {
            CD_Email_Templates::send_officer_added( $email, $first_name, $title );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::OFFICER_ADDED, get_current_user_id(), $member_id, array(
            'title' => $title,
        ) );

        return $this->success( array(
            'message' => sprintf( __( '%s has been added as an officer.', 'community-directory' ), $first_name ),
        ), array(), 201 );
    }

    /**
     * Remove an officer.
     */
    public function remove_officer( WP_REST_Request $request ) {
        global $wpdb;

        $officer_id = (int) $request->get_param( 'id' );
        $officers_table = CD_Database::table( 'officers' );

        $officer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$officers_table} WHERE id = %d AND is_active = 1",
            $officer_id
        ) );

        if ( ! $officer ) {
            return $this->error( 'not_found', __( 'Officer not found.', 'community-directory' ), 404 );
        }

        $wpdb->update( $officers_table, array(
            'is_active'  => 0,
            'removed_at' => current_time( 'mysql' ),
        ), array( 'id' => $officer_id ), array( '%d', '%s' ), array( '%d' ) );

        // Revoke capability
        $members_table = CD_Database::table( 'members' );
        $member = $wpdb->get_row( $wpdb->prepare(
            "SELECT wp_user_id FROM {$members_table} WHERE id = %d",
            $officer->member_id
        ) );
        if ( $member && $member->wp_user_id ) {
            CD_Capabilities::revoke_cap( $member->wp_user_id, 'cd_officer' );
        }

        // Send notification
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $profile = $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name FROM {$profiles_table} WHERE member_id = %d",
            $officer->member_id
        ) );
        if ( $officer->email ) {
            CD_Email_Templates::send_officer_removed( $officer->email, $profile ? $profile->first_name : '' );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::OFFICER_REMOVED, get_current_user_id(), $officer->member_id );

        return $this->success( array(
            'message' => __( 'Officer removed.', 'community-directory' ),
        ) );
    }

    /**
     * Annual officer rotation — deactivate all current officers.
     */
    public function rotate_officers( WP_REST_Request $request ) {
        global $wpdb;

        $officers_table = CD_Database::table( 'officers' );
        $members_table  = CD_Database::table( 'members' );

        // Get all active officers for notification
        $active_officers = $wpdb->get_results(
            "SELECT o.*, p.first_name FROM {$officers_table} o
             LEFT JOIN " . CD_Database::table( 'directory_profiles' ) . " p ON o.member_id = p.member_id
             WHERE o.is_active = 1"
        );

        // Deactivate all
        $count = $wpdb->query(
            "UPDATE {$officers_table} SET is_active = 0, removed_at = '" . current_time( 'mysql' ) . "' WHERE is_active = 1"
        );

        // Revoke capabilities
        foreach ( $active_officers as $officer ) {
            $member = $wpdb->get_row( $wpdb->prepare(
                "SELECT wp_user_id FROM {$members_table} WHERE id = %d",
                $officer->member_id
            ) );
            if ( $member && $member->wp_user_id ) {
                CD_Capabilities::revoke_cap( $member->wp_user_id, 'cd_officer' );
            }
            if ( $officer->email ) {
                CD_Email_Templates::send_officer_removed( $officer->email, $officer->first_name ?: '' );
            }
        }

        CD_Audit_Logger::log( CD_Audit_Logger::OFFICER_ROTATION, get_current_user_id(), null, array(
            'officers_removed' => $count,
        ) );

        return $this->success( array(
            'message' => sprintf( __( 'Officer rotation complete. %d officers deactivated.', 'community-directory' ), $count ),
        ) );
    }

    /* ──────────────────────────────────────
     * MEMBER SEARCH (for autocomplete)
     * ──────────────────────────────────── */

    /**
     * Search active members by name or email.
     */
    public function search_members( WP_REST_Request $request ) {
        global $wpdb;

        $q = sanitize_text_field( $request->get_param( 'q' ) ?: '' );
        if ( strlen( $q ) < 2 ) {
            return $this->success( array( 'members' => array() ) );
        }

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $search = '%' . $wpdb->esc_like( $q ) . '%';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT m.id, m.uuid, p.first_name, p.last_name
             FROM {$members_table} m
             JOIN {$profiles_table} p ON m.id = p.member_id
             WHERE m.status = 'active'
             AND (p.first_name LIKE %s OR p.last_name LIKE %s OR CONCAT(p.first_name, ' ', p.last_name) LIKE %s)
             ORDER BY p.last_name, p.first_name
             LIMIT 20",
            $search, $search, $search
        ) );

        return $this->success( array( 'members' => $results ) );
    }

    /* ──────────────────────────────────────
     * GOOGLE CONTACTS SYNC
     * ──────────────────────────────────── */

    /**
     * Preview Google Contacts import: fetch contacts and match against existing members.
     */
    public function google_sync_preview( WP_REST_Request $request ) {
        global $wpdb;

        if ( ! class_exists( 'CD_Google_Contacts' ) ) {
            return $this->error( 'not_available', __( 'Google Contacts integration is not available.', 'community-directory' ) );
        }

        $result = CD_Google_Contacts::import_contacts( 500 );
        if ( is_wp_error( $result ) ) {
            return $this->error( 'google_error', $result->get_error_message() );
        }

        // Get existing members for matching
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $members_table  = CD_Database::table( 'members' );
        $existing = $wpdb->get_results(
            "SELECT m.id, p.first_name, p.last_name, p.emails
             FROM {$members_table} m
             JOIN {$profiles_table} p ON m.id = p.member_id
             WHERE m.status = 'active'"
        );

        // Normalize existing members for matching
        $existing_normalized = array();
        foreach ( $existing as $member ) {
            $email = '';
            if ( ! empty( $member->emails ) ) {
                $emails = json_decode( $member->emails, true );
                if ( ! empty( $emails[0]['value'] ) ) {
                    $email = $emails[0]['value'];
                }
            }
            $existing_normalized[] = array(
                'id'         => $member->id,
                'first_name' => $member->first_name,
                'last_name'  => $member->last_name,
                'email'      => $email,
            );
        }

        // Match each contact
        $contacts = array();
        foreach ( $result['contacts'] as $contact ) {
            $match = CD_Google_Contacts::match_contact( $contact, $existing_normalized );
            $contact['match_type'] = $match['match_type'];
            $contact['member_id']  = $match['member_id'];
            $contacts[] = $contact;
        }

        return $this->success( array(
            'contacts'    => $contacts,
            'totalPeople' => $result['totalPeople'] ?? count( $contacts ),
        ) );
    }

    /**
     * Confirm Google Contacts import: create members from selected contacts.
     */
    public function google_sync_confirm( WP_REST_Request $request ) {
        global $wpdb;

        $contacts = $request->get_param( 'contacts' );
        if ( empty( $contacts ) || ! is_array( $contacts ) ) {
            return $this->error( 'no_contacts', __( 'No contacts selected for import.', 'community-directory' ) );
        }

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $imported = 0;

        foreach ( $contacts as $contact ) {
            $first_name = sanitize_text_field( $contact['first_name'] ?? '' );
            $last_name  = sanitize_text_field( $contact['last_name'] ?? '' );
            $email      = sanitize_email( $contact['email'] ?? '' );
            $phone      = sanitize_text_field( $contact['phone'] ?? '' );

            if ( empty( $first_name ) && empty( $last_name ) ) {
                continue;
            }

            // Skip if already matched to an existing member
            if ( ! empty( $contact['member_id'] ) ) {
                continue;
            }

            // Create member
            $uuid = wp_generate_uuid4();
            $wpdb->insert( $members_table, array(
                'uuid'         => $uuid,
                'status'       => 'active',
                'member_since' => current_time( 'Y-m-d' ),
                'created_at'   => current_time( 'mysql' ),
                'google_contact_id' => sanitize_text_field( $contact['resourceName'] ?? '' ),
            ), array( '%s', '%s', '%s', '%s', '%s' ) );
            $member_id = $wpdb->insert_id;

            if ( ! $member_id ) {
                continue;
            }

            // Create profile
            $profile_data = array(
                'member_id'  => $member_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'created_at' => current_time( 'mysql' ),
            );
            if ( $email ) {
                $profile_data['emails'] = wp_json_encode( array( array( 'type' => 'primary', 'value' => $email ) ) );
            }
            if ( $phone ) {
                $profile_data['phones'] = wp_json_encode( array( array( 'type' => 'mobile', 'value' => $phone ) ) );
            }

            $wpdb->insert( $profiles_table, $profile_data );
            $imported++;
        }

        CD_Audit_Logger::log( CD_Audit_Logger::BULK_OPERATION, get_current_user_id(), null, array(
            'action'   => 'google_import',
            'imported' => $imported,
        ) );

        return $this->success( array(
            'message'  => sprintf( __( '%d contact(s) imported successfully.', 'community-directory' ), $imported ),
            'imported' => $imported,
        ) );
    }
}
