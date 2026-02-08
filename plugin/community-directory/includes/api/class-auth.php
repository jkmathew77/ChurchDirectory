<?php
/**
 * REST API controller for authentication (Google OAuth, email lookup).
 * Stub — full implementation in Phase 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Auth extends CD_API_Base {

    public function register_routes() {
        // POST /auth/google — Google OAuth callback
        register_rest_route( CD_API_NAMESPACE, '/auth/google', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'google_callback' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );

        // GET /auth/email-lookup — "Can't remember your email?" lookup
        register_rest_route( CD_API_NAMESPACE, '/auth/email-lookup', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'email_lookup' ),
            'permission_callback' => array( $this, 'permission_public' ),
        ) );
    }

    public function google_callback( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Google OAuth coming in Phase 1.', 'community-directory' ), 501 );
    }

    public function email_lookup( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Email lookup coming in Phase 1.', 'community-directory' ), 501 );
    }
}
