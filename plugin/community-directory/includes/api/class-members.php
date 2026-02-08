<?php
/**
 * REST API controller for member profiles and directory.
 * Stub — full implementation in Phase 3.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Members extends CD_API_Base {

    public function register_routes() {
        // POST /directory/search — search directory
        register_rest_route( CD_API_NAMESPACE, '/directory/search', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'search_directory' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // GET /members/{uuid} — get member profile
        register_rest_route( CD_API_NAMESPACE, '/members/(?P<uuid>[a-f0-9-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_member' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // PUT /members/{uuid} — update member profile
        register_rest_route( CD_API_NAMESPACE, '/members/(?P<uuid>[a-f0-9-]+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_member' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );
    }

    public function search_directory( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Directory search coming in Phase 3.', 'community-directory' ), 501 );
    }

    public function get_member( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Member profiles coming in Phase 3.', 'community-directory' ), 501 );
    }

    public function update_member( WP_REST_Request $request ) {
        return $this->error( 'not_implemented', __( 'Profile editing coming in Phase 3.', 'community-directory' ), 501 );
    }
}
