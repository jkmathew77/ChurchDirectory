<?php
/**
 * REST API controller for household operations.
 * Handles CRUD for households and household membership management.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Households extends CD_API_Base {

    public function register_routes() {
        // GET /households — list all households (admin)
        register_rest_route( CD_API_NAMESPACE, '/households', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_households' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /households — create a household (admin)
        register_rest_route( CD_API_NAMESPACE, '/households', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_household' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /households/{id} — get household with members
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_household' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // PUT /households/{id} — update household
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_household' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /households/{id}/members — add member to household
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)/members', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_member' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // PUT /households/{id}/members/{member_id} — update member role
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)/members/(?P<member_id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_member_role' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // DELETE /households/{id}/members/{member_id} — remove member from household
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)/members/(?P<member_id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'remove_member' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /members/me/household — get current user's household (member-facing)
        register_rest_route( CD_API_NAMESPACE, '/members/me/household', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_household' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );
    }

    /**
     * Valid household member roles.
     */
    private static $valid_roles = array( 'head', 'spouse', 'adult_child', 'child', 'other' );

    /* ──────────────────────────────────────
     * HOUSEHOLD CRUD
     * ──────────────────────────────────── */

    /**
     * List households with pagination and search.
     */
    public function list_households( WP_REST_Request $request ) {
        global $wpdb;

        $households_table = CD_Database::table( 'households' );
        $hm_table         = CD_Database::table( 'household_members' );
        $profiles_table   = CD_Database::table( 'directory_profiles' );

        $status = sanitize_text_field( $request->get_param( 'status' ) ?: 'active' );
        $search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
        $page   = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
        $offset = ( $page - 1 ) * $per;

        $where = '1=1';
        $args  = array();

        if ( $status && 'all' !== $status ) {
            $where .= ' AND h.status = %s';
            $args[] = $status;
        }

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND h.name LIKE %s';
            $args[] = $like;
        }

        // Count
        $count_query = "SELECT COUNT(*) FROM {$households_table} h WHERE {$where}";
        $total = ! empty( $args )
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_query, $args ) )
            : (int) $wpdb->get_var( $count_query );

        // Fetch households with member count and head name
        $query = "SELECT h.*,
                    (SELECT COUNT(*) FROM {$hm_table} hm WHERE hm.household_id = h.id AND hm.left_at IS NULL) AS member_count,
                    (SELECT CONCAT(p.first_name, ' ', p.last_name)
                     FROM {$hm_table} hm2
                     JOIN {$profiles_table} p ON hm2.member_id = p.member_id
                     WHERE hm2.household_id = h.id AND hm2.role = 'head' AND hm2.left_at IS NULL
                     LIMIT 1) AS head_name
                  FROM {$households_table} h
                  WHERE {$where}
                  ORDER BY h.name ASC
                  LIMIT %d OFFSET %d";

        $args[] = $per;
        $args[] = $offset;
        $rows = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        // Status counts for tabs
        $counts_raw = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$households_table} GROUP BY status" );
        $counts = array( 'all' => 0 );
        foreach ( $counts_raw as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }

        return $this->success( array(
            'households' => $rows,
            'counts'     => $counts,
        ), array(
            'page'     => $page,
            'per_page' => $per,
            'total'    => $total,
            'pages'    => (int) ceil( $total / $per ),
        ) );
    }

    /**
     * Create a new household.
     */
    public function create_household( WP_REST_Request $request ) {
        global $wpdb;

        $name    = sanitize_text_field( $request->get_param( 'name' ) );
        $address = sanitize_textarea_field( $request->get_param( 'primary_address' ) ?: '' );

        if ( empty( $name ) ) {
            return $this->error( 'missing_name', __( 'Household name is required.', 'community-directory' ) );
        }

        $households_table = CD_Database::table( 'households' );

        // Encrypt address if provided
        $encrypted_address = ! empty( $address ) ? CD_Encryption::encrypt( $address ) : '';

        $wpdb->insert( $households_table, array(
            'name'            => $name,
            'primary_address' => $encrypted_address,
            'status'          => 'active',
            'created_by'      => get_current_user_id(),
            'created_at'      => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%d', '%s' ) );

        $household_id = $wpdb->insert_id;
        if ( ! $household_id ) {
            return $this->error( 'create_failed', __( 'Failed to create household.', 'community-directory' ), 500 );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_CREATED, get_current_user_id(), $household_id, array(
            'name' => $name,
        ) );

        return $this->success( array(
            'message'      => __( 'Household created.', 'community-directory' ),
            'household_id' => $household_id,
        ), array(), 201 );
    }

    /**
     * Get a single household with its members.
     */
    public function get_household( WP_REST_Request $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );
        $households_table = CD_Database::table( 'households' );
        $hm_table         = CD_Database::table( 'household_members' );
        $profiles_table   = CD_Database::table( 'directory_profiles' );
        $members_table    = CD_Database::table( 'members' );

        $household = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$households_table} WHERE id = %d",
            $id
        ) );

        if ( ! $household ) {
            return $this->error( 'not_found', __( 'Household not found.', 'community-directory' ), 404 );
        }

        // Decrypt address
        if ( ! empty( $household->primary_address ) ) {
            $household->primary_address = CD_Encryption::decrypt( $household->primary_address );
        }

        // Fetch active members with profile info
        $members = $wpdb->get_results( $wpdb->prepare(
            "SELECT hm.id AS membership_id, hm.member_id, hm.role, hm.address_override, hm.joined_at,
                    p.first_name, p.last_name, p.avatar_url, p.emails, p.phones,
                    m.uuid, m.status AS member_status
             FROM {$hm_table} hm
             JOIN {$members_table} m ON hm.member_id = m.id
             LEFT JOIN {$profiles_table} p ON hm.member_id = p.member_id
             WHERE hm.household_id = %d AND hm.left_at IS NULL
             ORDER BY FIELD(hm.role, 'head', 'spouse', 'adult_child', 'child', 'other'), p.first_name ASC",
            $id
        ) );

        // Decode JSON fields and decrypt address overrides
        foreach ( $members as $member ) {
            $member->emails = json_decode( $member->emails, true ) ?: array();
            $member->phones = json_decode( $member->phones, true ) ?: array();
            if ( ! empty( $member->address_override ) ) {
                $member->address_override = CD_Encryption::decrypt( $member->address_override );
            }
            // Compute primary email
            $member->primary_email = ! empty( $member->emails[0]['value'] ) ? $member->emails[0]['value'] : '';
        }

        $household->members = $members;

        return $this->success( array( 'household' => $household ) );
    }

    /**
     * Update household name, address, or status.
     */
    public function update_household( WP_REST_Request $request ) {
        global $wpdb;

        $id     = (int) $request->get_param( 'id' );
        $params = $request->get_json_params();

        $households_table = CD_Database::table( 'households' );

        $household = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$households_table} WHERE id = %d", $id
        ) );
        if ( ! $household ) {
            return $this->error( 'not_found', __( 'Household not found.', 'community-directory' ), 404 );
        }

        $update = array();
        $format = array();

        if ( isset( $params['name'] ) ) {
            $update['name'] = sanitize_text_field( $params['name'] );
            $format[] = '%s';
        }

        if ( isset( $params['primary_address'] ) ) {
            $address = sanitize_textarea_field( $params['primary_address'] );
            $update['primary_address'] = ! empty( $address ) ? CD_Encryption::encrypt( $address ) : '';
            $format[] = '%s';
        }

        if ( isset( $params['status'] ) ) {
            $status = sanitize_text_field( $params['status'] );
            if ( in_array( $status, array( 'active', 'inactive' ), true ) ) {
                $update['status'] = $status;
                $format[] = '%s';
            }
        }

        if ( empty( $update ) ) {
            return $this->error( 'no_data', __( 'No data provided.', 'community-directory' ) );
        }

        $wpdb->update( $households_table, $update, array( 'id' => $id ), $format, array( '%d' ) );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_CREATED, get_current_user_id(), $id, array(
            'action' => 'updated',
            'fields' => array_keys( $update ),
        ) );

        return $this->success( array( 'message' => __( 'Household updated.', 'community-directory' ) ) );
    }

    /* ──────────────────────────────────────
     * HOUSEHOLD MEMBERSHIP
     * ──────────────────────────────────── */

    /**
     * Add a member to a household.
     */
    public function add_member( WP_REST_Request $request ) {
        global $wpdb;

        $household_id = (int) $request->get_param( 'id' );
        $member_id    = (int) $request->get_param( 'member_id' );
        $role         = sanitize_text_field( $request->get_param( 'role' ) ?: 'other' );

        if ( ! $member_id ) {
            return $this->error( 'missing_member', __( 'Please select a member.', 'community-directory' ) );
        }

        if ( ! in_array( $role, self::$valid_roles, true ) ) {
            return $this->error( 'invalid_role', __( 'Invalid household role.', 'community-directory' ) );
        }

        $households_table = CD_Database::table( 'households' );
        $hm_table         = CD_Database::table( 'household_members' );
        $members_table    = CD_Database::table( 'members' );

        // Verify household exists
        $household = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$households_table} WHERE id = %d", $household_id
        ) );
        if ( ! $household ) {
            return $this->error( 'not_found', __( 'Household not found.', 'community-directory' ), 404 );
        }

        // Verify member exists and is active
        $member = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$members_table} WHERE id = %d AND status = 'active'", $member_id
        ) );
        if ( ! $member ) {
            return $this->error( 'invalid_member', __( 'Member not found or not active.', 'community-directory' ), 404 );
        }

        // Check member is not already in an active household
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT household_id FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member_id
        ) );
        if ( $existing ) {
            if ( (int) $existing === $household_id ) {
                return $this->error( 'already_member', __( 'This member is already in this household.', 'community-directory' ) );
            }
            return $this->error( 'in_other_household', __( 'This member is already in another household. Remove them first.', 'community-directory' ) );
        }

        // Enforce one head per household
        if ( 'head' === $role ) {
            $existing_head = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$hm_table} WHERE household_id = %d AND role = 'head' AND left_at IS NULL",
                $household_id
            ) );
            if ( $existing_head ) {
                return $this->error( 'head_exists', __( 'This household already has a head. Change the existing head\'s role first.', 'community-directory' ) );
            }
        }

        // Enforce one spouse per household
        if ( 'spouse' === $role ) {
            $existing_spouse = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$hm_table} WHERE household_id = %d AND role = 'spouse' AND left_at IS NULL",
                $household_id
            ) );
            if ( $existing_spouse ) {
                return $this->error( 'spouse_exists', __( 'This household already has a spouse.', 'community-directory' ) );
            }
        }

        $wpdb->insert( $hm_table, array(
            'household_id' => $household_id,
            'member_id'    => $member_id,
            'role'         => $role,
            'joined_at'    => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s' ) );

        $profiles_table = CD_Database::table( 'directory_profiles' );
        $name = $wpdb->get_var( $wpdb->prepare(
            "SELECT CONCAT(first_name, ' ', last_name) FROM {$profiles_table} WHERE member_id = %d",
            $member_id
        ) );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MEMBER_ADDED, get_current_user_id(), $household_id, array(
            'member_id'   => $member_id,
            'member_name' => $name,
            'role'        => $role,
        ) );

        return $this->success( array(
            'message' => sprintf( __( '%s added to household as %s.', 'community-directory' ), $name, $role ),
        ), array(), 201 );
    }

    /**
     * Update a household member's role.
     */
    public function update_member_role( WP_REST_Request $request ) {
        global $wpdb;

        $household_id = (int) $request->get_param( 'id' );
        $member_id    = (int) $request->get_param( 'member_id' );
        $new_role     = sanitize_text_field( $request->get_param( 'role' ) );

        if ( ! in_array( $new_role, self::$valid_roles, true ) ) {
            return $this->error( 'invalid_role', __( 'Invalid household role.', 'community-directory' ) );
        }

        $hm_table = CD_Database::table( 'household_members' );

        // Verify membership exists
        $membership = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND left_at IS NULL",
            $household_id, $member_id
        ) );
        if ( ! $membership ) {
            return $this->error( 'not_found', __( 'Member is not in this household.', 'community-directory' ), 404 );
        }

        $old_role = $membership->role;

        // Enforce one head
        if ( 'head' === $new_role && 'head' !== $old_role ) {
            $existing_head = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$hm_table} WHERE household_id = %d AND role = 'head' AND left_at IS NULL AND member_id != %d",
                $household_id, $member_id
            ) );
            if ( $existing_head ) {
                return $this->error( 'head_exists', __( 'This household already has a head. Change their role first.', 'community-directory' ) );
            }
        }

        // Enforce one spouse
        if ( 'spouse' === $new_role && 'spouse' !== $old_role ) {
            $existing_spouse = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$hm_table} WHERE household_id = %d AND role = 'spouse' AND left_at IS NULL AND member_id != %d",
                $household_id, $member_id
            ) );
            if ( $existing_spouse ) {
                return $this->error( 'spouse_exists', __( 'This household already has a spouse.', 'community-directory' ) );
            }
        }

        $wpdb->update(
            $hm_table,
            array( 'role' => $new_role ),
            array( 'household_id' => $household_id, 'member_id' => $member_id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%d', '%s' )
        );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_ROLE_CHANGED, get_current_user_id(), $household_id, array(
            'member_id' => $member_id,
            'old_role'  => $old_role,
            'new_role'  => $new_role,
        ) );

        return $this->success( array( 'message' => __( 'Role updated.', 'community-directory' ) ) );
    }

    /**
     * Remove a member from a household (soft: sets left_at).
     */
    public function remove_member( WP_REST_Request $request ) {
        global $wpdb;

        $household_id = (int) $request->get_param( 'id' );
        $member_id    = (int) $request->get_param( 'member_id' );

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $membership = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND left_at IS NULL",
            $household_id, $member_id
        ) );
        if ( ! $membership ) {
            return $this->error( 'not_found', __( 'Member is not in this household.', 'community-directory' ), 404 );
        }

        // Soft-remove: set left_at
        $wpdb->update(
            $hm_table,
            array( 'left_at' => current_time( 'mysql' ) ),
            array( 'household_id' => $household_id, 'member_id' => $member_id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%d', '%s' )
        );

        // If this was the head, warn (but still remove)
        $was_head = ( 'head' === $membership->role );

        // Check if household is now empty → set inactive
        $remaining = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$hm_table} WHERE household_id = %d AND left_at IS NULL",
            $household_id
        ) );
        if ( 0 === $remaining ) {
            $wpdb->update( $households_table, array( 'status' => 'inactive' ), array( 'id' => $household_id ), array( '%s' ), array( '%d' ) );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MEMBER_REMOVED, get_current_user_id(), $household_id, array(
            'member_id' => $member_id,
            'role'      => $membership->role,
        ) );

        $message = __( 'Member removed from household.', 'community-directory' );
        if ( $was_head && $remaining > 0 ) {
            $message .= ' ' . __( 'Warning: This household no longer has a head. Please assign a new head.', 'community-directory' );
        }
        if ( 0 === $remaining ) {
            $message .= ' ' . __( 'Household is now empty and has been set to inactive.', 'community-directory' );
        }

        return $this->success( array( 'message' => $message ) );
    }

    /* ──────────────────────────────────────
     * MEMBER-FACING
     * ──────────────────────────────────── */

    /**
     * Get the current member's household.
     */
    public function get_my_household( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );
        $profiles_table    = CD_Database::table( 'directory_profiles' );
        $members_table     = CD_Database::table( 'members' );

        // Find member's active household
        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT hm.*, h.name AS household_name, h.primary_address, h.status AS household_status
             FROM {$hm_table} hm
             JOIN {$households_table} h ON hm.household_id = h.id
             WHERE hm.member_id = %d AND hm.left_at IS NULL",
            $member->id
        ) );

        if ( ! $hm ) {
            return $this->success( array( 'household' => null ) );
        }

        // Decrypt address
        if ( ! empty( $hm->primary_address ) ) {
            $hm->primary_address = CD_Encryption::decrypt( $hm->primary_address );
        }

        // Fetch all household members
        $hm_members = $wpdb->get_results( $wpdb->prepare(
            "SELECT hm2.member_id, hm2.role,
                    p.first_name, p.last_name, p.avatar_url,
                    m.uuid
             FROM {$hm_table} hm2
             JOIN {$members_table} m ON hm2.member_id = m.id
             LEFT JOIN {$profiles_table} p ON hm2.member_id = p.member_id
             WHERE hm2.household_id = %d AND hm2.left_at IS NULL
             ORDER BY FIELD(hm2.role, 'head', 'spouse', 'adult_child', 'child', 'other'), p.first_name ASC",
            $hm->household_id
        ) );

        return $this->success( array(
            'household' => array(
                'id'              => (int) $hm->household_id,
                'name'            => $hm->household_name,
                'primary_address' => $hm->primary_address,
                'status'          => $hm->household_status,
                'my_role'         => $hm->role,
                'members'         => $hm_members,
            ),
        ) );
    }
}
