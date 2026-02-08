<?php
/**
 * REST API controller for admin operations.
 * Stub — full implementation across Phases 2-5.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Admin extends CD_API_Base {

    public function register_routes() {
        // GET /admin/applications — list applications for review
        register_rest_route( CD_API_NAMESPACE, '/admin/applications', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_applications' ),
            'permission_callback' => array( $this, 'permission_secretary' ),
        ) );

        // PUT /admin/applications/{id} — approve/reject
        register_rest_route( CD_API_NAMESPACE, '/admin/applications/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_application' ),
            'permission_callback' => array( $this, 'permission_secretary' ),
        ) );

        // GET /admin/registrations — all applications including unverified
        register_rest_route( CD_API_NAMESPACE, '/admin/registrations', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_registrations' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );
    }

    public function list_applications( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Coming in Phase 2.', 'community-directory' ), 501 );
    }

    public function update_application( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Coming in Phase 2.', 'community-directory' ), 501 );
    }

    public function list_registrations( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Coming in Phase 1.', 'community-directory' ), 501 );
    }
}
