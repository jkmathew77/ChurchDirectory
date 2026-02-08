<?php
/**
 * Base class for all REST API controllers.
 * Provides common utilities: response envelope, permission checks, input sanitization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class CD_API_Base {

    /**
     * Register routes — implemented by each controller.
     */
    abstract public function register_routes();

    /**
     * Return a success response in the standard envelope.
     */
    protected function success( $data = array(), $meta = array(), $status = 200 ) {
        $response = array( 'success' => true, 'data' => $data );
        if ( ! empty( $meta ) ) {
            $response['meta'] = $meta;
        }
        return new WP_REST_Response( $response, $status );
    }

    /**
     * Return an error response in the standard envelope.
     */
    protected function error( $code, $message, $status = 400 ) {
        return new WP_REST_Response( array(
            'success' => false,
            'error'   => array(
                'code'    => $code,
                'message' => $message,
            ),
        ), $status );
    }

    /**
     * Permission callback: public (anyone, but with nonce for CSRF).
     */
    public function permission_public() {
        return true;
    }

    /**
     * Permission callback: must be a logged-in member.
     */
    public function permission_member() {
        return is_user_logged_in() && current_user_can( 'cd_member' );
    }

    /**
     * Permission callback: must be a church officer.
     */
    public function permission_officer() {
        return is_user_logged_in() && ( current_user_can( 'cd_officer' ) || current_user_can( 'cd_secretary' ) || current_user_can( 'cd_admin' ) );
    }

    /**
     * Permission callback: must be secretary or admin.
     */
    public function permission_secretary() {
        return is_user_logged_in() && ( current_user_can( 'cd_secretary' ) || current_user_can( 'cd_admin' ) );
    }

    /**
     * Permission callback: must be WP admin.
     */
    public function permission_admin() {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    /**
     * Get the current member record for the logged-in user.
     */
    protected function get_current_member() {
        global $wpdb;
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return null;
        }
        $table = CD_Database::table( 'members' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE wp_user_id = %d AND status IN ('active', 'self_deactivated')",
            $user_id
        ) );
    }

    /**
     * Simple rate limiter using transients.
     *
     * @param string $key     Unique key for this rate limit (e.g., 'login_' . $ip).
     * @param int    $limit   Max attempts allowed.
     * @param int    $window  Time window in seconds.
     * @return bool True if rate limit exceeded.
     */
    protected function is_rate_limited( $key, $limit, $window ) {
        $transient_key = 'cd_rl_' . md5( $key );
        $attempts = (int) get_transient( $transient_key );

        if ( $attempts >= $limit ) {
            return true;
        }

        set_transient( $transient_key, $attempts + 1, $window );
        return false;
    }
}
