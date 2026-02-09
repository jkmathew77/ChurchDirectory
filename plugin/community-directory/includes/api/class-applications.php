<?php
/**
 * REST API controller for membership applications.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Applications extends CD_API_Base {

    public function register_routes() {
        // POST /applications — submit a new application (public)
        register_rest_route( CD_API_NAMESPACE, '/applications', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'submit_application' ),
            'permission_callback' => array( $this, 'permission_public' ),
            'args'                => $this->get_submit_args(),
        ) );

        // GET /applications/verify/{token} — verify email
        register_rest_route( CD_API_NAMESPACE, '/applications/verify/(?P<token>[a-f0-9]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'verify_email' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );
    }

    /**
     * Define validation args for application submission.
     */
    private function get_submit_args() {
        return array(
            'first_name' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $value ) {
                    return strlen( trim( $value ) ) >= 2;
                },
            ),
            'last_name' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $value ) {
                    return strlen( trim( $value ) ) >= 2;
                },
            ),
            'email' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function( $value ) {
                    return is_email( $value );
                },
            ),
            'phone' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'form_data' => array(
                'required' => false,
                'type'     => 'object',
            ),
        );
    }

    /**
     * Handle application submission.
     */
    public function submit_application( WP_REST_Request $request ) {
        global $wpdb;

        $email = $request->get_param( 'email' );
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';

        // Rate limit: 5 submissions per IP per hour
        if ( $this->is_rate_limited( 'app_submit_' . $ip, 5, HOUR_IN_SECONDS ) ) {
            return $this->error( 'rate_limited', __( 'Too many submissions. Please try again later.', 'community-directory' ), 429 );
        }

        $applications_table = CD_Database::table( 'applications' );
        $members_table = CD_Database::table( 'members' );

        // Check re-application cooldown (30 days default)
        $cooldown_days = (int) get_option( 'cd_reapplication_cooldown', 30 );
        $existing_rejected = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$applications_table}
             WHERE email = %s AND status = 'not_approved'
             AND reviewed_at > DATE_SUB(%s, INTERVAL %d DAY)
             LIMIT 1",
            $email,
            current_time( 'mysql' ),
            $cooldown_days
        ) );

        if ( $existing_rejected ) {
            return $this->error(
                'cooldown_active',
                sprintf(
                    __( 'A previous application was recently reviewed. Please wait %d days before reapplying, or contact the church office.', 'community-directory' ),
                    $cooldown_days
                )
            );
        }

        // Check if email already belongs to an active member
        $existing_member = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$members_table} WHERE id IN (
                SELECT m.id FROM {$members_table} m
                JOIN {$applications_table} a ON m.application_id = a.id
                WHERE a.email = %s AND m.status = 'active'
            )",
            $email
        ) );

        if ( $existing_member ) {
            // Don't reveal details — generic message
            return $this->error(
                'email_exists',
                __( 'An account with this email address already exists in the directory. If you already have an account, please log in instead. If you need help, contact the church office.', 'community-directory' )
            );
        }

        // Check for existing pending_verification — replace it
        $existing_pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$applications_table} WHERE email = %s AND status = 'pending_verification'",
            $email
        ) );

        if ( $existing_pending ) {
            $wpdb->delete( $applications_table, array( 'id' => $existing_pending ), array( '%d' ) );
        }

        // Generate verification token
        $token = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );

        // Prepare form_data JSON
        $form_data = $request->get_param( 'form_data' );

        // Insert application
        $inserted = $wpdb->insert( $applications_table, array(
            'email'                   => $email,
            'first_name'              => $request->get_param( 'first_name' ),
            'last_name'               => $request->get_param( 'last_name' ),
            'form_data'               => $form_data ? wp_json_encode( $form_data ) : null,
            'status'                  => 'pending_verification',
            'verification_token_hash' => $token_hash,
            'verification_sent_at'    => current_time( 'mysql' ),
            'submitted_at'            => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );

        if ( false === $inserted ) {
            return $this->error( 'db_error', __( 'Could not save your application. Please try again.', 'community-directory' ), 500 );
        }

        $application_id = $wpdb->insert_id;

        // Send verification email
        $this->send_verification_email( $email, $request->get_param( 'first_name' ), $token );

        // Audit log
        CD_Audit_Logger::log( CD_Audit_Logger::APPLICATION_SUBMITTED, null, $application_id, array(
            'email' => $email,
        ) );

        return $this->success( array(
            'message' => __( 'Application submitted! Please check your email to verify your address.', 'community-directory' ),
        ), array(), 201 );
    }

    /**
     * Handle email verification.
     */
    public function verify_email( WP_REST_Request $request ) {
        global $wpdb;

        $token = $request->get_param( 'token' );
        $token_hash = hash( 'sha256', $token );

        $applications_table = CD_Database::table( 'applications' );

        $application = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$applications_table} WHERE verification_token_hash = %s AND status = 'pending_verification'",
            $token_hash
        ) );

        if ( ! $application ) {
            return $this->error( 'invalid_token', __( 'This verification link is invalid or has already been used.', 'community-directory' ), 404 );
        }

        // Check expiry
        $expiry_hours = (int) get_option( 'cd_verification_expiry', 48 );
        $sent_at = strtotime( $application->verification_sent_at );
        if ( ( time() - $sent_at ) > ( $expiry_hours * HOUR_IN_SECONDS ) ) {
            return $this->error( 'token_expired', __( 'This verification link has expired. Please submit a new application.', 'community-directory' ), 410 );
        }

        // Mark as verified
        $wpdb->update(
            $applications_table,
            array(
                'status'      => 'new',
                'verified_at' => current_time( 'mysql' ),
                'verification_token_hash' => null, // consume the token
            ),
            array( 'id' => $application->id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        // Audit log
        CD_Audit_Logger::log( CD_Audit_Logger::APPLICATION_VERIFIED, null, $application->id );

        // Send officer notification now that email is verified
        $this->notify_officers( $application );

        return $this->success( array(
            'message' => __( 'Your email has been verified! Your application is now under review.', 'community-directory' ),
        ) );
    }

    /**
     * Send verification email to applicant.
     */
    private function send_verification_email( $email, $first_name, $token ) {
        $base_slug = get_option( 'cd_base_slug', 'community' );
        $verify_url = home_url( $base_slug . '/verify/' . $token . '/' );

        $subject = __( 'Verify your email — St. Thekla Community Directory', 'community-directory' );

        $message = sprintf(
            __( "Hello %s,\n\nWelcome! Please verify your email address to complete your application to the St. Thekla Community Directory.\n\nClick here to verify: %s\n\nThis link expires in %d hours.\n\nIf you did not submit this application, you can safely ignore this email.\n\nSt. Thekla Church", 'community-directory' ),
            esc_html( $first_name ),
            esc_url( $verify_url ),
            (int) get_option( 'cd_verification_expiry', 48 )
        );

        wp_mail( $email, $subject, $message );

        CD_Audit_Logger::log( CD_Audit_Logger::VERIFICATION_SENT, null, null, array( 'email' => $email ) );
    }

    /**
     * Notify officers of a new verified application.
     */
    private function notify_officers( $application ) {
        global $wpdb;

        $officers_table = CD_Database::table( 'officers' );
        $officer_emails = $wpdb->get_col(
            "SELECT email FROM {$officers_table} WHERE is_active = 1"
        );

        if ( empty( $officer_emails ) ) {
            return;
        }

        $subject = sprintf(
            __( 'New Directory Application: %s %s', 'community-directory' ),
            $application->first_name,
            $application->last_name
        );

        $admin_url = admin_url( 'admin.php?page=cd-applications' );
        $message = sprintf(
            __( "A new membership application has been submitted and verified.\n\nName: %s %s\nEmail: %s\nSubmitted: %s\n\nReview it here: %s\n\nSt. Thekla Community Directory", 'community-directory' ),
            $application->first_name,
            $application->last_name,
            $application->email,
            $application->submitted_at,
            $admin_url
        );

        foreach ( $officer_emails as $email ) {
            wp_mail( $email, $subject, $message );
        }
    }
}
