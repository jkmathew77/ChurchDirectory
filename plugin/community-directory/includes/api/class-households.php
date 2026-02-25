<?php
/**
 * REST API controller for household operations.
 * Handles CRUD for households and household membership management.
 *
 * Roles stored in DB: head, spouse, adult_child, child, other
 * Display label for 'head' is 'Primary Membership Holder' (or 'Primary').
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Households extends CD_API_Base {

    public function register_routes() {
        // ── Admin endpoints ──

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

        // GET /households/{id} — get household with members (admin)
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_household' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // PUT /households/{id} — update household (admin)
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_household' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /households/{id}/members — add member to household (admin)
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)/members', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_member' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // PUT /households/{id}/members/{member_id} — update member role (admin)
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)/members/(?P<member_id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_member_role' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // DELETE /households/{id}/members/{member_id} — remove member from household (admin)
        register_rest_route( CD_API_NAMESPACE, '/households/(?P<id>\d+)/members/(?P<member_id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'remove_member' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // ── Member-facing endpoints ──

        // GET /members/me/household — get current user's household
        register_rest_route( CD_API_NAMESPACE, '/members/me/household', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_household' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/me/household — create a household (member becomes primary)
        register_rest_route( CD_API_NAMESPACE, '/members/me/household', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_my_household' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // PUT /members/me/household — update household name/address (primary/spouse only)
        register_rest_route( CD_API_NAMESPACE, '/members/me/household', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_my_household' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/me/household/members — add a member to household (primary/spouse only)
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/members', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_household_member' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // DELETE /members/me/household/members/{member_id} — remove member (primary only)
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/members/(?P<member_id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'remove_household_member' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // PUT /members/me/household/members/{member_id} — edit managed member (head/spouse only)
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/members/(?P<member_id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_household_member' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // GET /members/me/household/members/{member_id} — get managed member details (head/spouse only)
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/members/(?P<member_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_household_member' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/me/household/members/{member_id}/avatar — upload avatar for managed member
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/members/(?P<member_id>\d+)/avatar', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'upload_managed_member_avatar' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // ── Lifecycle endpoints ──

        // POST /members/me/household/leave — leave household
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/leave', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'leave_household' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/me/household/transfer-head — transfer primary role
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/transfer-head', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'transfer_head' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/me/household/spin-off — spin off into new household
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/spin-off', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'spin_off' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/me/household/photo — upload household family photo
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/photo', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'upload_household_photo' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // DELETE /members/me/household/photo — remove household family photo
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/photo', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_household_photo' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // PATCH /members/me/household/photo-position — update focal point + zoom for a photo
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/photo-position', array(
            'methods'             => 'PATCH',
            'callback'            => array( $this, 'update_photo_position' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/me/household/merge-request — request merge with another household
        register_rest_route( CD_API_NAMESPACE, '/members/me/household/merge-request', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'request_merge' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // GET /households/search — lightweight search for merge target picker
        register_rest_route( CD_API_NAMESPACE, '/households/search', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'search_households' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );
    }

    /**
     * Valid household member roles.
     */
    private static $valid_roles = array( 'head', 'spouse', 'child', 'other' );

    /**
     * Map DB role values to display labels.
     */
    public static function role_label( $role ) {
        $labels = array(
            'head'        => __( 'Primary', 'community-directory' ),
            'spouse'      => __( 'Spouse', 'community-directory' ),
            'child'       => __( 'Child', 'community-directory' ),
            'other'       => __( 'Other', 'community-directory' ),
            'adult_child' => __( 'Child', 'community-directory' ), // legacy compat
        );
        return isset( $labels[ $role ] ) ? $labels[ $role ] : ucfirst( $role );
    }

    /**
     * All role options for dropdowns (value => label).
     */
    public static function role_options() {
        return array(
            'head'   => __( 'Primary', 'community-directory' ),
            'spouse' => __( 'Spouse', 'community-directory' ),
            'child'  => __( 'Child', 'community-directory' ),
            'other'  => __( 'Other', 'community-directory' ),
        );
    }

    /* ──────────────────────────────────────
     * ADMIN: HOUSEHOLD CRUD
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

        // Fetch households with member count and primary name
        $query = "SELECT h.*,
                    (SELECT COUNT(*) FROM {$hm_table} hm WHERE hm.household_id = h.id AND hm.left_at IS NULL) AS member_count,
                    (SELECT CONCAT(p.first_name, ' ', p.last_name)
                     FROM {$hm_table} hm2
                     JOIN {$profiles_table} p ON hm2.member_id = p.member_id
                     WHERE hm2.household_id = h.id AND hm2.role = 'head' AND hm2.left_at IS NULL
                     LIMIT 1) AS primary_name
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
     * Create a new household (admin).
     */
    public function create_household( WP_REST_Request $request ) {
        global $wpdb;

        $name    = sanitize_text_field( $request->get_param( 'name' ) );
        $address = sanitize_textarea_field( $request->get_param( 'primary_address' ) ?: '' );

        if ( empty( $name ) ) {
            return $this->error( 'missing_name', __( 'Household name is required.', 'community-directory' ) );
        }

        $households_table = CD_Database::table( 'households' );
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
     * Get a single household with its members (admin).
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

        // Decrypt address into structured format
        $household->address = self::decrypt_address( $household->primary_address );
        unset( $household->primary_address );

        // Fetch active members with profile info
        $members = $wpdb->get_results( $wpdb->prepare(
            "SELECT hm.id AS membership_id, hm.member_id, hm.role, hm.address_override, hm.joined_at,
                    p.first_name, p.last_name, p.avatar_url, p.emails, p.phones,
                    m.uuid, m.status AS member_status, m.wp_user_id
             FROM {$hm_table} hm
             JOIN {$members_table} m ON hm.member_id = m.id
             LEFT JOIN {$profiles_table} p ON hm.member_id = p.member_id
             WHERE hm.household_id = %d AND hm.left_at IS NULL
             ORDER BY FIELD(hm.role, 'head', 'spouse', 'child', 'other'), p.first_name ASC",
            $id
        ) );

        // Decode JSON fields and add role labels
        foreach ( $members as $member ) {
            $member->emails = json_decode( $member->emails, true ) ?: array();
            $member->phones = json_decode( $member->phones, true ) ?: array();
            if ( ! empty( $member->address_override ) ) {
                $member->address_override = CD_Encryption::decrypt( $member->address_override );
            }
            $member->primary_email = ! empty( $member->emails[0]['value'] ) ? $member->emails[0]['value'] : '';
            $member->role_label = self::role_label( $member->role );
            $member->has_login = ! empty( $member->wp_user_id );
        }

        $household->members = $members;

        return $this->success( array( 'household' => $household ) );
    }

    /**
     * Update household name, address, or status (admin).
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

        // Cascade: deactivate all household members when household is deactivated
        if ( isset( $update['status'] ) && 'inactive' === $update['status'] && 'active' === $household->status ) {
            $this->deactivate_household_members( $id );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_CREATED, get_current_user_id(), $id, array(
            'action' => 'updated',
            'fields' => array_keys( $update ),
        ) );

        return $this->success( array( 'message' => __( 'Household updated.', 'community-directory' ) ) );
    }

    /* ──────────────────────────────────────
     * ADMIN: HOUSEHOLD MEMBERSHIP
     * ──────────────────────────────────── */

    /**
     * Add a member to a household (admin).
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

        $household = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$households_table} WHERE id = %d", $household_id
        ) );
        if ( ! $household ) {
            return $this->error( 'not_found', __( 'Household not found.', 'community-directory' ), 404 );
        }

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
                return $this->error( 'head_exists', __( 'This household already has a primary membership holder. Change the existing one\'s role first.', 'community-directory' ) );
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

        $role_display = self::role_label( $role );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MEMBER_ADDED, get_current_user_id(), $household_id, array(
            'member_id'   => $member_id,
            'member_name' => $name,
            'role'        => $role,
        ) );

        return $this->success( array(
            'message' => sprintf( __( '%s added to household as %s.', 'community-directory' ), $name, $role_display ),
        ), array(), 201 );
    }

    /**
     * Update a household member's role (admin).
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
                return $this->error( 'head_exists', __( 'This household already has a primary membership holder. Change their role first.', 'community-directory' ) );
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
     * Remove a member from a household — soft: sets left_at (admin).
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

        $wpdb->update(
            $hm_table,
            array( 'left_at' => current_time( 'mysql' ) ),
            array( 'household_id' => $household_id, 'member_id' => $member_id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%d', '%s' )
        );

        $was_head = ( 'head' === $membership->role );

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
            $message .= ' ' . __( 'Warning: This household no longer has a primary membership holder. Please assign a new one.', 'community-directory' );
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

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT hm.*, h.name AS household_name, h.primary_address, h.status AS household_status, h.photo_url AS household_photo_url, h.photos AS household_photos
             FROM {$hm_table} hm
             JOIN {$households_table} h ON hm.household_id = h.id
             WHERE hm.member_id = %d AND hm.left_at IS NULL",
            $member->id
        ) );

        if ( ! $hm ) {
            return $this->success( array( 'household' => null ) );
        }

        // Decrypt address into structured format
        $address = self::decrypt_address( $hm->primary_address );

        // Fetch all household members
        $hm_members = $wpdb->get_results( $wpdb->prepare(
            "SELECT hm2.member_id, hm2.role,
                    p.first_name, p.last_name, p.avatar_url, p.emails,
                    m.uuid, m.wp_user_id
             FROM {$hm_table} hm2
             JOIN {$members_table} m ON hm2.member_id = m.id
             LEFT JOIN {$profiles_table} p ON hm2.member_id = p.member_id
             WHERE hm2.household_id = %d AND hm2.left_at IS NULL
             ORDER BY FIELD(hm2.role, 'head', 'spouse', 'child', 'other'), p.first_name ASC",
            $hm->household_id
        ) );

        // Add display info
        foreach ( $hm_members as $m ) {
            $m->role_label = self::role_label( $m->role );
            $m->has_login = ! empty( $m->wp_user_id );
            $emails = json_decode( $m->emails, true ) ?: array();
            $m->primary_email = ! empty( $emails[0]['value'] ) ? $emails[0]['value'] : '';
            unset( $m->emails ); // Don't leak full email list
            unset( $m->wp_user_id );
        }

        // Parse photos JSON array with backward compat (old format: array of URL strings)
        $photos_raw = json_decode( $hm->household_photos ?? '', true );
        if ( ! is_array( $photos_raw ) ) {
            $photos_raw = array();
            // Backwards compat: use single photo_url if photos array empty
            if ( ! empty( $hm->household_photo_url ) ) {
                $photos_raw = array( $hm->household_photo_url );
            }
        }
        $photos = array_map( array( $this, 'parse_photo_item' ), $photos_raw );

        return $this->success( array(
            'household' => array(
                'id'                    => (int) $hm->household_id,
                'name'                  => $hm->household_name,
                'address'               => $address,
                'status'                => $hm->household_status,
                'photo_url'             => $hm->household_photo_url ?? '',
                'photos'                => $photos,
                'my_role'               => $hm->role,
                'my_role_label'         => self::role_label( $hm->role ),
                'can_manage'            => in_array( $hm->role, array( 'head', 'spouse' ), true ),
                'has_different_address'  => (bool) ( $hm->has_different_address ?? 0 ),
                'members'               => $hm_members,
            ),
        ) );
    }

    /**
     * Build a sanitized address array from request params.
     */
    private function sanitize_address( $request ) {
        $addr = $request->get_param( 'address' );
        if ( ! is_array( $addr ) ) {
            // Backwards compat: accept plain string in primary_address
            $legacy = sanitize_textarea_field( $request->get_param( 'primary_address' ) ?: '' );
            if ( ! empty( $legacy ) ) {
                return array( 'line_1' => $legacy );
            }
            return array();
        }
        return array_filter( array(
            'line_1' => sanitize_text_field( $addr['line_1'] ?? '' ),
            'line_2' => sanitize_text_field( $addr['line_2'] ?? '' ),
            'city'   => sanitize_text_field( $addr['city'] ?? '' ),
            'state'  => sanitize_text_field( $addr['state'] ?? '' ),
            'zip'    => sanitize_text_field( $addr['zip'] ?? '' ),
        ) );
    }

    /**
     * Encrypt an address array into the primary_address column.
     */
    private function encrypt_address( $addr ) {
        if ( empty( $addr ) ) {
            return '';
        }
        return CD_Encryption::encrypt( wp_json_encode( $addr ) );
    }

    /**
     * Decrypt primary_address column into a structured array.
     */
    public static function decrypt_address( $encrypted ) {
        if ( empty( $encrypted ) ) {
            return array( 'line_1' => '', 'line_2' => '', 'city' => '', 'state' => '', 'zip' => '' );
        }
        $decrypted = CD_Encryption::decrypt( $encrypted );
        if ( empty( $decrypted ) ) {
            return array( 'line_1' => '', 'line_2' => '', 'city' => '', 'state' => '', 'zip' => '' );
        }
        // Try JSON first (new format)
        $parsed = json_decode( $decrypted, true );
        if ( is_array( $parsed ) ) {
            return array_merge( array( 'line_1' => '', 'line_2' => '', 'city' => '', 'state' => '', 'zip' => '' ), $parsed );
        }
        // Legacy: plain string — put it in line_1
        return array( 'line_1' => $decrypted, 'line_2' => '', 'city' => '', 'state' => '', 'zip' => '' );
    }

    /**
     * Create a new household — current member becomes the primary membership holder.
     */
    public function create_my_household( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        // Check member is not already in a household
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT household_id FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( $existing ) {
            return $this->error( 'already_in_household', __( 'You are already part of a household.', 'community-directory' ) );
        }

        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $addr = $this->sanitize_address( $request );

        if ( empty( $name ) ) {
            return $this->error( 'missing_name', __( 'Household name is required.', 'community-directory' ) );
        }

        $encrypted_address = $this->encrypt_address( $addr );

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

        // Add current member as head (primary)
        $wpdb->insert( $hm_table, array(
            'household_id' => $household_id,
            'member_id'    => $member->id,
            'role'         => 'head',
            'joined_at'    => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s' ) );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_CREATED, get_current_user_id(), $household_id, array(
            'name'      => $name,
            'member_id' => $member->id,
        ) );

        return $this->success( array(
            'message'      => __( 'Household created! You are the primary membership holder.', 'community-directory' ),
            'household_id' => $household_id,
        ), array(), 201 );
    }

    /**
     * Update current member's household (name/address). Primary or spouse only.
     */
    public function update_my_household( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can edit household details.', 'community-directory' ), 403 );
        }

        $params = $request->get_json_params();
        $update = array();
        $format = array();

        if ( isset( $params['name'] ) ) {
            $update['name'] = sanitize_text_field( $params['name'] );
            $format[] = '%s';
        }
        if ( isset( $params['address'] ) || isset( $params['primary_address'] ) ) {
            $addr = $this->sanitize_address( $request );
            $update['primary_address'] = $this->encrypt_address( $addr );
            $format[] = '%s';
        }

        if ( empty( $update ) ) {
            return $this->error( 'no_data', __( 'No data provided.', 'community-directory' ) );
        }

        $wpdb->update( $households_table, $update, array( 'id' => $hm->household_id ), $format, array( '%d' ) );

        return $this->success( array( 'message' => __( 'Household updated.', 'community-directory' ) ) );
    }

    /**
     * Add a household member. Two flows:
     * 1. With email → creates member + sends invite (person gets own login)
     * 2. Without email → creates "managed" member (primary fills in their info)
     *
     * Only head/spouse can add members.
     */
    public function add_household_member( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        // Verify caller is in a household and is head/spouse
        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can add household members.', 'community-directory' ), 403 );
        }

        $household_id = (int) $hm->household_id;

        $first_name = sanitize_text_field( $request->get_param( 'first_name' ) );
        $last_name  = sanitize_text_field( $request->get_param( 'last_name' ) );
        $email      = sanitize_email( $request->get_param( 'email' ) ?: '' );
        $role       = sanitize_text_field( $request->get_param( 'role' ) ?: 'other' );

        if ( empty( $first_name ) || empty( $last_name ) ) {
            return $this->error( 'missing_name', __( 'First name and last name are required.', 'community-directory' ) );
        }

        if ( ! in_array( $role, self::$valid_roles, true ) ) {
            $role = 'other';
        }

        // Cannot add another head
        if ( 'head' === $role ) {
            return $this->error( 'cannot_set_head', __( 'Cannot add another primary membership holder.', 'community-directory' ) );
        }

        // Enforce one spouse
        if ( 'spouse' === $role ) {
            $existing_spouse = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$hm_table} WHERE household_id = %d AND role = 'spouse' AND left_at IS NULL",
                $household_id
            ) );
            if ( $existing_spouse ) {
                return $this->error( 'spouse_exists', __( 'This household already has a spouse.', 'community-directory' ) );
            }
        }

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $invites_table  = CD_Database::table( 'invites' );

        // If email provided, check for existing member
        if ( ! empty( $email ) ) {
            // Check if a member with this email already exists
            $existing_member_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT p.member_id FROM {$profiles_table} p WHERE p.emails LIKE %s",
                '%' . $wpdb->esc_like( $email ) . '%'
            ) );

            if ( $existing_member_id ) {
                // Check if already in a household
                $in_hh = $wpdb->get_var( $wpdb->prepare(
                    "SELECT household_id FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
                    $existing_member_id
                ) );
                if ( $in_hh ) {
                    return $this->error( 'member_in_household', __( 'This person is already part of a household.', 'community-directory' ) );
                }

                // Add existing member to household
                $wpdb->insert( $hm_table, array(
                    'household_id' => $household_id,
                    'member_id'    => (int) $existing_member_id,
                    'role'         => $role,
                    'joined_at'    => current_time( 'mysql' ),
                ), array( '%d', '%d', '%s', '%s' ) );

                CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MEMBER_ADDED, get_current_user_id(), $household_id, array(
                    'member_id'   => $existing_member_id,
                    'member_name' => $first_name . ' ' . $last_name,
                    'role'        => $role,
                    'via'         => 'existing_member',
                ) );

                return $this->success( array(
                    'message' => sprintf( __( '%s has been added to your household.', 'community-directory' ), $first_name ),
                    'type'    => 'existing',
                ), array(), 201 );
            }

            // New person with email → create member record + send invite
            $uuid = wp_generate_uuid4();
            $wpdb->insert( $members_table, array(
                'uuid'        => $uuid,
                'status'      => 'active',
                'member_since' => current_time( 'Y-m-d' ),
                'created_at'  => current_time( 'mysql' ),
            ), array( '%s', '%s', '%s', '%s' ) );
            $new_member_id = $wpdb->insert_id;

            // Set default directory preferences based on role
            $default_view = 'adults_only';
            if ( 'child' === $role || 'other' === $role ) {
                $default_view = 'children_only';
            }
            $default_prefs = wp_json_encode( array(
                'default_view'    => $default_view,
                'search_sections' => array( 'all', 'households' ),
            ) );

            // Create profile
            $wpdb->insert( $profiles_table, array(
                'member_id'             => $new_member_id,
                'first_name'            => $first_name,
                'last_name'             => $last_name,
                'emails'                => wp_json_encode( array( array( 'type' => 'primary', 'value' => $email ) ) ),
                'directory_preferences' => $default_prefs,
                'created_at'            => current_time( 'mysql' ),
            ) );

            // Add to household
            $wpdb->insert( $hm_table, array(
                'household_id' => $household_id,
                'member_id'    => $new_member_id,
                'role'         => $role,
                'joined_at'    => current_time( 'mysql' ),
            ), array( '%d', '%d', '%s', '%s' ) );

            // Generate and send invite
            $token      = bin2hex( random_bytes( 32 ) );
            $token_hash = hash( 'sha256', $token );
            $expiry_days = (int) get_option( 'cd_invite_expiry', 14 );

            $wpdb->insert( $invites_table, array(
                'member_id'  => $new_member_id,
                'email'      => $email,
                'token_hash' => $token_hash,
                'expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) ),
                'created_at' => current_time( 'mysql' ),
            ), array( '%d', '%s', '%s', '%s', '%s' ) );

            // Google Contacts sync — create new household member (invite)
            if ( class_exists( 'CD_Google_Contacts' ) ) {
                CD_Google_Contacts::sync_member( $new_member_id, 'create' );
            }

            // Send invite email
            CD_Email_Templates::send_household_invite( $email, $first_name, $token, $member );

            CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MEMBER_ADDED, get_current_user_id(), $household_id, array(
                'member_id'   => $new_member_id,
                'member_name' => $first_name . ' ' . $last_name,
                'role'        => $role,
                'via'         => 'invite',
                'email'       => $email,
            ) );

            return $this->success( array(
                'message' => sprintf( __( 'An invitation has been sent to %s at %s.', 'community-directory' ), $first_name, $email ),
                'type'    => 'invited',
            ), array(), 201 );
        }

        // No email → "managed" member (primary fills in their info)
        $uuid = wp_generate_uuid4();
        $wpdb->insert( $members_table, array(
            'uuid'        => $uuid,
            'status'      => 'active',
            'member_since' => current_time( 'Y-m-d' ),
            'created_at'  => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s' ) );
        $new_member_id = $wpdb->insert_id;

        // Set default directory preferences based on role
        $managed_default_view = 'adults_only';
        if ( 'child' === $role || 'other' === $role ) {
            $managed_default_view = 'children_only';
        }
        $managed_prefs = wp_json_encode( array(
            'default_view'    => $managed_default_view,
            'search_sections' => array( 'all', 'households' ),
        ) );

        // Create profile with basic info
        $wpdb->insert( $profiles_table, array(
            'member_id'             => $new_member_id,
            'first_name'            => $first_name,
            'last_name'             => $last_name,
            'emails'                => wp_json_encode( array() ),
            'directory_preferences' => $managed_prefs,
            'created_at'            => current_time( 'mysql' ),
        ) );

        // Google Contacts sync — create new managed household member
        if ( class_exists( 'CD_Google_Contacts' ) ) {
            CD_Google_Contacts::sync_member( $new_member_id, 'create' );
        }

        // Add to household
        $wpdb->insert( $hm_table, array(
            'household_id' => $household_id,
            'member_id'    => $new_member_id,
            'role'         => $role,
            'joined_at'    => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s' ) );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MEMBER_ADDED, get_current_user_id(), $household_id, array(
            'member_id'   => $new_member_id,
            'member_name' => $first_name . ' ' . $last_name,
            'role'        => $role,
            'via'         => 'managed',
        ) );

        return $this->success( array(
            'message' => sprintf( __( '%s has been added to your household.', 'community-directory' ), $first_name ),
            'type'    => 'managed',
        ), array(), 201 );
    }

    /**
     * Remove a member from the current user's household. Primary only.
     */
    public function remove_household_member( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can remove household members.', 'community-directory' ), 403 );
        }

        $target_member_id = (int) $request->get_param( 'member_id' );
        $household_id     = (int) $hm->household_id;

        // Cannot remove yourself (use "leave household" for that in future)
        if ( $target_member_id === (int) $member->id ) {
            return $this->error( 'cannot_remove_self', __( 'You cannot remove yourself from the household.', 'community-directory' ) );
        }

        $target_membership = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND left_at IS NULL",
            $household_id, $target_member_id
        ) );
        if ( ! $target_membership ) {
            return $this->error( 'not_found', __( 'This person is not in your household.', 'community-directory' ), 404 );
        }

        // Spouse cannot remove the head
        if ( 'head' === $target_membership->role && 'spouse' === $hm->role ) {
            return $this->error( 'cannot_remove_head', __( 'Only the primary membership holder can be removed by an admin.', 'community-directory' ), 403 );
        }

        $wpdb->update(
            $hm_table,
            array( 'left_at' => current_time( 'mysql' ) ),
            array( 'household_id' => $household_id, 'member_id' => $target_member_id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%d', '%s' )
        );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MEMBER_REMOVED, get_current_user_id(), $household_id, array(
            'member_id' => $target_member_id,
            'role'      => $target_membership->role,
            'removed_by' => $member->id,
        ) );

        return $this->success( array( 'message' => __( 'Member removed from household.', 'community-directory' ) ) );
    }

    /**
     * Get a managed household member's full profile (for editing).
     * Only head/spouse can view members who don't have their own login.
     */
    public function get_household_member( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table      = CD_Database::table( 'household_members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $members_table  = CD_Database::table( 'members' );

        // Verify caller is head/spouse in a household
        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm || ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can edit members.', 'community-directory' ), 403 );
        }

        $target_member_id = (int) $request->get_param( 'member_id' );

        // Verify target is in the same household
        $target_hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND left_at IS NULL",
            $hm->household_id, $target_member_id
        ) );
        if ( ! $target_hm ) {
            return $this->error( 'not_found', __( 'This person is not in your household.', 'community-directory' ), 404 );
        }

        // Only allow editing managed members (no WP user = no login)
        $target_member = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$members_table} WHERE id = %d",
            $target_member_id
        ) );
        if ( ! empty( $target_member->wp_user_id ) ) {
            return $this->error( 'has_login', __( 'This member has their own login and manages their own profile.', 'community-directory' ), 403 );
        }

        // Get profile data
        $profile = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$profiles_table} WHERE member_id = %d",
            $target_member_id
        ) );
        if ( ! $profile ) {
            return $this->error( 'no_profile', __( 'Profile not found.', 'community-directory' ), 404 );
        }

        $emails = json_decode( $profile->emails ?? '', true ) ?: array();
        $phones = json_decode( $profile->phones ?? '', true ) ?: array();

        return $this->success( array(
            'member' => array(
                'member_id'    => $target_member_id,
                'first_name'   => $profile->first_name ?? '',
                'last_name'    => $profile->last_name ?? '',
                'salutation'   => $profile->salutation ?? '',
                'primary_email' => ! empty( $emails[0]['value'] ) ? $emails[0]['value'] : '',
                'phone'        => ! empty( $phones[0]['value'] ) ? $phones[0]['value'] : '',
                'date_of_birth' => $profile->date_of_birth ?? '',
                'occupation'   => $profile->occupation ?? '',
                'employer'     => $profile->employer ?? '',
                'avatar_url'   => $profile->avatar_url ?? '',
                'role'         => $target_hm->role,
            ),
        ) );
    }

    /**
     * Upload avatar for a managed household member.
     * Only head/spouse can upload for members without their own login.
     * POST /members/me/household/members/{member_id}/avatar
     */
    public function upload_managed_member_avatar( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table       = CD_Database::table( 'household_members' );
        $profiles_table  = CD_Database::table( 'directory_profiles' );
        $members_table   = CD_Database::table( 'members' );

        // Verify caller is head/spouse
        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm || ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can upload photos.', 'community-directory' ), 403 );
        }

        $target_member_id = (int) $request->get_param( 'member_id' );

        // Verify target is in same household
        $target_hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND left_at IS NULL",
            $hm->household_id, $target_member_id
        ) );
        if ( ! $target_hm ) {
            return $this->error( 'not_found', __( 'This person is not in your household.', 'community-directory' ), 404 );
        }

        // Only managed members (no WP user)
        $target_member = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$members_table} WHERE id = %d",
            $target_member_id
        ) );
        if ( ! empty( $target_member->wp_user_id ) ) {
            return $this->error( 'has_login', __( 'This member manages their own profile.', 'community-directory' ), 403 );
        }

        $files = $request->get_file_params();
        if ( empty( $files['file'] ) ) {
            return $this->error( 'no_file', __( 'No file uploaded.', 'community-directory' ), 400 );
        }

        $file = $files['file'];
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            return $this->error( 'invalid_type', __( 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP.', 'community-directory' ), 400 );
        }
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            return $this->error( 'file_too_large', __( 'File too large (Max 5MB).', 'community-directory' ), 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_sideload( $file, 0 );
        if ( is_wp_error( $attachment_id ) ) {
            return $this->error( 'upload_failed', $attachment_id->get_error_message(), 500 );
        }

        // Strip EXIF metadata (PRD Section 10.2.9)
        $att_file_path = get_attached_file( $attachment_id );
        if ( $att_file_path && file_exists( $att_file_path ) ) {
            $editor = wp_get_image_editor( $att_file_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->save( $att_file_path );
            }
        }

        $url = wp_get_attachment_url( $attachment_id );

        $wpdb->update(
            $profiles_table,
            array( 'avatar_url' => $url, 'avatar_source' => 'upload' ),
            array( 'member_id' => $target_member_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return $this->success( array(
            'url'     => $url,
            'message' => __( 'Photo updated.', 'community-directory' ),
        ) );
    }

    /**
     * Update a managed household member's profile.
     * Only head/spouse can edit members who don't have their own login.
     */
    public function update_household_member( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table      = CD_Database::table( 'household_members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $members_table  = CD_Database::table( 'members' );

        // Verify caller is head/spouse
        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm || ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can edit members.', 'community-directory' ), 403 );
        }

        $target_member_id = (int) $request->get_param( 'member_id' );

        // Verify target is in the same household
        $target_hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND left_at IS NULL",
            $hm->household_id, $target_member_id
        ) );
        if ( ! $target_hm ) {
            return $this->error( 'not_found', __( 'This person is not in your household.', 'community-directory' ), 404 );
        }

        // Only allow editing managed members (no WP user = no login)
        $target_member = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$members_table} WHERE id = %d",
            $target_member_id
        ) );
        if ( ! empty( $target_member->wp_user_id ) ) {
            return $this->error( 'has_login', __( 'This member has their own login and manages their own profile.', 'community-directory' ), 403 );
        }

        // Sanitize inputs
        $first_name = sanitize_text_field( $request->get_param( 'first_name' ) ?? '' );
        $last_name  = sanitize_text_field( $request->get_param( 'last_name' ) ?? '' );

        if ( empty( $first_name ) || empty( $last_name ) ) {
            return $this->error( 'missing_name', __( 'First and last name are required.', 'community-directory' ), 400 );
        }

        $update_data = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'updated_at' => current_time( 'mysql' ),
        );
        $update_format = array( '%s', '%s', '%s' );

        // Optional fields
        $salutation = $request->get_param( 'salutation' );
        if ( null !== $salutation ) {
            $update_data['salutation'] = sanitize_text_field( $salutation );
            $update_format[] = '%s';
        }

        $email = sanitize_email( $request->get_param( 'email' ) ?? '' );
        if ( ! empty( $email ) ) {
            $update_data['emails'] = wp_json_encode( array( array( 'type' => 'primary', 'value' => $email ) ) );
            $update_format[] = '%s';
        } elseif ( '' === $request->get_param( 'email' ) ) {
            $update_data['emails'] = wp_json_encode( array() );
            $update_format[] = '%s';
        }

        $phone = sanitize_text_field( $request->get_param( 'phone' ) ?? '' );
        if ( ! empty( $phone ) ) {
            $update_data['phones'] = wp_json_encode( array( array( 'type' => 'mobile', 'value' => $phone ) ) );
            $update_format[] = '%s';
        } elseif ( '' === $request->get_param( 'phone' ) ) {
            $update_data['phones'] = wp_json_encode( array() );
            $update_format[] = '%s';
        }

        $dob = sanitize_text_field( $request->get_param( 'date_of_birth' ) ?? '' );
        if ( ! empty( $dob ) ) {
            $update_data['date_of_birth'] = $dob;
            $update_format[] = '%s';
        } elseif ( '' === $request->get_param( 'date_of_birth' ) ) {
            $update_data['date_of_birth'] = null;
            $update_format[] = '%s';
        }

        $occupation = $request->get_param( 'occupation' );
        if ( null !== $occupation ) {
            $update_data['occupation'] = sanitize_text_field( $occupation );
            $update_format[] = '%s';
        }

        $employer = $request->get_param( 'employer' );
        if ( null !== $employer ) {
            $update_data['employer'] = sanitize_text_field( $employer );
            $update_format[] = '%s';
        }

        $wpdb->update(
            $profiles_table,
            $update_data,
            array( 'member_id' => $target_member_id ),
            $update_format,
            array( '%d' )
        );

        CD_Audit_Logger::log( CD_Audit_Logger::PROFILE_UPDATED, get_current_user_id(), $target_member_id, array(
            'updated_by' => $member->id,
            'managed_member' => true,
        ) );

        return $this->success( array( 'message' => __( 'Member details updated.', 'community-directory' ) ) );
    }

    /* ──────────────────────────────────────
     * LIFECYCLE WORKFLOWS
     * ──────────────────────────────────── */

    /**
     * Leave the current member's household.
     * Head must transfer first; children cannot leave on their own.
     */
    public function leave_household( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }

        if ( 'head' === $hm->role ) {
            return $this->error( 'head_cannot_leave', __( 'You must transfer the primary role before leaving the household.', 'community-directory' ) );
        }

        if ( 'child' === $hm->role ) {
            return $this->error( 'child_cannot_leave', __( 'Child members cannot leave on their own. Ask the primary membership holder to remove you.', 'community-directory' ), 403 );
        }

        $household_id = (int) $hm->household_id;

        // Set left_at
        $wpdb->update(
            $hm_table,
            array( 'left_at' => current_time( 'mysql' ) ),
            array( 'member_id' => $member->id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%s' )
        );

        // Check if household is now empty
        $remaining = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$hm_table} WHERE household_id = %d AND left_at IS NULL",
            $household_id
        ) );
        if ( 0 === $remaining ) {
            $wpdb->update( $households_table, array( 'status' => 'inactive' ), array( 'id' => $household_id ), array( '%s' ), array( '%d' ) );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_LEAVE, get_current_user_id(), $household_id, array(
            'member_id' => $member->id,
            'role'      => $hm->role,
        ) );

        return $this->success( array( 'message' => __( 'You have left the household.', 'community-directory' ) ) );
    }

    /**
     * Transfer the primary (head) role to another household member.
     * Only the current head can do this. Target must not be a child.
     */
    public function transfer_head( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table       = CD_Database::table( 'household_members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( 'head' !== $hm->role ) {
            return $this->error( 'not_head', __( 'Only the primary membership holder can transfer this role.', 'community-directory' ), 403 );
        }

        $target_member_id = (int) $request->get_param( 'target_member_id' );
        if ( ! $target_member_id ) {
            return $this->error( 'missing_target', __( 'Please select a member to transfer to.', 'community-directory' ) );
        }

        $household_id = (int) $hm->household_id;

        // Verify target is in same household and not a child
        $target = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND left_at IS NULL",
            $household_id, $target_member_id
        ) );
        if ( ! $target ) {
            return $this->error( 'target_not_in_household', __( 'That member is not in your household.', 'community-directory' ), 404 );
        }
        if ( 'child' === $target->role ) {
            return $this->error( 'target_is_child', __( 'Cannot transfer the primary role to a child member.', 'community-directory' ) );
        }

        // Swap roles in a transaction
        $wpdb->query( 'START TRANSACTION' );

        $wpdb->update(
            $hm_table,
            array( 'role' => 'other' ),
            array( 'household_id' => $household_id, 'member_id' => $member->id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%d', '%s' )
        );

        $wpdb->update(
            $hm_table,
            array( 'role' => 'head' ),
            array( 'household_id' => $household_id, 'member_id' => $target_member_id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%d', '%s' )
        );

        $wpdb->query( 'COMMIT' );

        // Get target's name for the response
        $target_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT CONCAT(first_name, ' ', last_name) FROM {$profiles_table} WHERE member_id = %d",
            $target_member_id
        ) );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_TRANSFER_HEAD, get_current_user_id(), $household_id, array(
            'old_head' => $member->id,
            'new_head' => $target_member_id,
        ) );

        return $this->success( array(
            'message' => sprintf( __( 'Primary role transferred to %s.', 'community-directory' ), $target_name ?: __( 'the selected member', 'community-directory' ) ),
        ) );
    }

    /**
     * Spin off into a new household. Non-head, non-child members only.
     * Optionally bring children along.
     */
    public function spin_off( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( 'head' === $hm->role ) {
            return $this->error( 'head_cannot_spinoff', __( 'The primary membership holder must transfer the role first before spinning off.', 'community-directory' ) );
        }
        if ( 'child' === $hm->role ) {
            return $this->error( 'child_cannot_spinoff', __( 'Child members cannot create their own household.', 'community-directory' ), 403 );
        }

        $household_name = sanitize_text_field( $request->get_param( 'household_name' ) );
        if ( empty( $household_name ) ) {
            return $this->error( 'missing_name', __( 'Please enter a name for your new household.', 'community-directory' ) );
        }

        $addr = $this->sanitize_address( $request );
        $encrypted_address = $this->encrypt_address( $addr );
        $bring_children = $request->get_param( 'bring_children' );
        if ( ! is_array( $bring_children ) ) {
            $bring_children = array();
        }
        $bring_children = array_map( 'absint', $bring_children );

        $old_household_id = (int) $hm->household_id;

        $wpdb->query( 'START TRANSACTION' );

        // 1. Set left_at on caller's old membership
        $wpdb->update(
            $hm_table,
            array( 'left_at' => current_time( 'mysql' ) ),
            array( 'member_id' => $member->id, 'left_at' => null ),
            array( '%s' ),
            array( '%d', '%s' )
        );

        // 2. Move children if requested
        $children_moved = array();
        foreach ( $bring_children as $child_id ) {
            $child_hm = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$hm_table} WHERE household_id = %d AND member_id = %d AND role = 'child' AND left_at IS NULL",
                $old_household_id, $child_id
            ) );
            if ( $child_hm ) {
                $wpdb->update(
                    $hm_table,
                    array( 'left_at' => current_time( 'mysql' ) ),
                    array( 'id' => $child_hm->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                $children_moved[] = $child_id;
            }
        }

        // 3. Create new household
        $wpdb->insert( $households_table, array(
            'name'            => $household_name,
            'primary_address' => $encrypted_address,
            'status'          => 'active',
            'created_by'      => get_current_user_id(),
            'created_at'      => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%d', '%s' ) );

        $new_household_id = $wpdb->insert_id;
        if ( ! $new_household_id ) {
            $wpdb->query( 'ROLLBACK' );
            return $this->error( 'create_failed', __( 'Failed to create new household.', 'community-directory' ), 500 );
        }

        // 4. Insert caller as head of new household
        $wpdb->insert( $hm_table, array(
            'household_id' => $new_household_id,
            'member_id'    => $member->id,
            'role'         => 'head',
            'joined_at'    => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s' ) );

        // 5. Insert children into new household
        foreach ( $children_moved as $child_id ) {
            $wpdb->insert( $hm_table, array(
                'household_id' => $new_household_id,
                'member_id'    => $child_id,
                'role'         => 'child',
                'joined_at'    => current_time( 'mysql' ),
            ), array( '%d', '%d', '%s', '%s' ) );
        }

        // 6. Check if old household is now empty
        $remaining = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$hm_table} WHERE household_id = %d AND left_at IS NULL",
            $old_household_id
        ) );
        if ( 0 === $remaining ) {
            $wpdb->update( $households_table, array( 'status' => 'inactive' ), array( 'id' => $old_household_id ), array( '%s' ), array( '%d' ) );
        }

        $wpdb->query( 'COMMIT' );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_SPINOFF, get_current_user_id(), $new_household_id, array(
            'old_household_id' => $old_household_id,
            'member_id'        => $member->id,
            'children_moved'   => $children_moved,
        ) );

        return $this->success( array(
            'message'      => sprintf( __( 'Your new household "%s" has been created.', 'community-directory' ), $household_name ),
            'household_id' => $new_household_id,
        ), array(), 201 );
    }

    /**
     * Request to merge current household into another (head only).
     * Creates a pending request for admin approval.
     */
    public function request_merge( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );
        $hr_table          = CD_Database::table( 'household_requests' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( 'head' !== $hm->role ) {
            return $this->error( 'not_head', __( 'Only the primary membership holder can request a merge.', 'community-directory' ), 403 );
        }

        $target_household_id = (int) $request->get_param( 'target_household_id' );
        if ( ! $target_household_id ) {
            return $this->error( 'missing_target', __( 'Please select a target household.', 'community-directory' ) );
        }

        $source_household_id = (int) $hm->household_id;

        if ( $target_household_id === $source_household_id ) {
            return $this->error( 'same_household', __( 'Cannot merge a household with itself.', 'community-directory' ) );
        }

        // Verify target exists and is active
        $target_hh = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$households_table} WHERE id = %d AND status = 'active'",
            $target_household_id
        ) );
        if ( ! $target_hh ) {
            return $this->error( 'target_not_found', __( 'Target household not found or not active.', 'community-directory' ), 404 );
        }

        // Check for duplicate pending request
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$hr_table} WHERE type = 'merge' AND household_id = %d AND target_household_id = %d AND status = 'pending'",
            $source_household_id, $target_household_id
        ) );
        if ( $existing ) {
            return $this->error( 'duplicate_request', __( 'A merge request for these households is already pending.', 'community-directory' ) );
        }

        $wpdb->insert( $hr_table, array(
            'type'                 => 'merge',
            'requesting_member_id' => $member->id,
            'household_id'         => $source_household_id,
            'target_household_id'  => $target_household_id,
            'status'               => 'pending',
            'created_at'           => current_time( 'mysql' ),
        ), array( '%s', '%d', '%d', '%d', '%s', '%s' ) );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MERGE_REQUESTED, get_current_user_id(), $source_household_id, array(
            'target_household_id' => $target_household_id,
            'requesting_member'   => $member->id,
        ) );

        return $this->success( array(
            'message' => __( 'Merge request submitted. An admin will review and process it.', 'community-directory' ),
        ) );
    }

    /**
     * Search households by name (for merge target picker).
     * Returns only id and name, no PII.
     */
    public function search_households( WP_REST_Request $request ) {
        global $wpdb;

        $q = sanitize_text_field( $request->get_param( 'q' ) ?: '' );
        if ( strlen( $q ) < 2 ) {
            return $this->success( array( 'households' => array() ) );
        }

        $households_table = CD_Database::table( 'households' );
        $like = '%' . $wpdb->esc_like( $q ) . '%';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name FROM {$households_table} WHERE status = 'active' AND name LIKE %s ORDER BY name ASC LIMIT 10",
            $like
        ) );

        return $this->success( array( 'households' => $rows ?: array() ) );
    }

    /**
     * Upload household family photo. Head/spouse only.
     */
    public function upload_household_photo( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can upload a family photo.', 'community-directory' ), 403 );
        }

        $files = $request->get_file_params();
        if ( empty( $files['file'] ) ) {
            return $this->error( 'no_file', __( 'No file uploaded.', 'community-directory' ), 400 );
        }

        $file = $files['file'];
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            return $this->error( 'invalid_type', __( 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP.', 'community-directory' ), 400 );
        }
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            return $this->error( 'file_too_large', __( 'File too large (Max 5MB).', 'community-directory' ), 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Strip EXIF (security: GPS location protection)
        add_filter( 'wp_handle_upload', function( $info ) {
            if ( function_exists( 'wp_read_image_metadata' ) && in_array( $info['type'], array( 'image/jpeg', 'image/png' ), true ) ) {
                $editor = wp_get_image_editor( $info['file'] );
                if ( ! is_wp_error( $editor ) ) {
                    $editor->save( $info['file'] );
                }
            }
            return $info;
        } );

        // Check current photo count (max 10)
        $current_photos_json = $wpdb->get_var( $wpdb->prepare(
            "SELECT photos FROM {$households_table} WHERE id = %d",
            $hm->household_id
        ) );
        $photos_raw = json_decode( $current_photos_json ?? '', true );
        if ( ! is_array( $photos_raw ) ) {
            $photos_raw = array();
            // Migrate legacy single photo_url
            $legacy_url = $wpdb->get_var( $wpdb->prepare(
                "SELECT photo_url FROM {$households_table} WHERE id = %d",
                $hm->household_id
            ) );
            if ( ! empty( $legacy_url ) ) {
                $photos_raw[] = $legacy_url;
            }
        }
        $photos = array_map( array( $this, 'parse_photo_item' ), $photos_raw );

        if ( count( $photos ) >= 10 ) {
            return $this->error( 'limit_reached', __( 'Maximum 10 photos allowed. Please delete one before uploading a new one.', 'community-directory' ), 400 );
        }

        CD_Logger::info( 'Household photo upload: starting for household ' . $hm->household_id . ', file: ' . $file['name'] . ', size: ' . $file['size'] );

        $upload = wp_handle_upload( $file, array( 'test_form' => false ) );
        if ( isset( $upload['error'] ) ) {
            CD_Logger::error( 'Household photo upload: wp_handle_upload failed — ' . $upload['error'] );
            return $this->error( 'upload_failed', $upload['error'], 500 );
        }

        CD_Logger::info( 'Household photo upload: file uploaded to ' . $upload['url'] );

        $photos[] = array( 'url' => $upload['url'], 'fx' => 50, 'fy' => 50, 'zoom' => 1.0 );

        $primary_url = is_array( $photos[0] ) ? $photos[0]['url'] : $photos[0];
        $result = $wpdb->update(
            $households_table,
            array(
                'photos'    => wp_json_encode( $photos ),
                'photo_url' => $primary_url,
            ),
            array( 'id' => $hm->household_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $result ) {
            CD_Logger::error( 'Household photo upload: DB update failed — ' . $wpdb->last_error );
            return $this->error( 'db_error', __( 'Failed to save photo to database.', 'community-directory' ), 500 );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_CREATED, get_current_user_id(), $hm->household_id, array(
            'action' => 'photo_uploaded',
            'count'  => count( $photos ),
        ) );

        return $this->success( array(
            'message' => sprintf( __( 'Family photo uploaded (%d of 10).', 'community-directory' ), count( $photos ) ),
            'url'     => $upload['url'],
            'photos'  => $photos, // full objects with fx/fy/zoom
        ) );
    }

    /**
     * Delete household family photo. Head/spouse only.
     */
    public function delete_household_photo( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can manage the family photo.', 'community-directory' ), 403 );
        }

        $photo_url = sanitize_text_field( $request->get_param( 'photo_url' ) ?: '' );

        // Load current photos array
        $photos_json = $wpdb->get_var( $wpdb->prepare(
            "SELECT photos FROM {$households_table} WHERE id = %d",
            $hm->household_id
        ) );
        $photos_raw = json_decode( $photos_json ?? '', true );
        if ( ! is_array( $photos_raw ) ) {
            $photos_raw = array();
        }
        $photos = array_map( array( $this, 'parse_photo_item' ), $photos_raw );

        if ( ! empty( $photo_url ) ) {
            // Delete specific photo (works for both legacy string and new object format)
            $photos = array_values( array_filter( $photos, function( $item ) use ( $photo_url ) {
                return $item['url'] !== $photo_url;
            } ) );
            $del_id = attachment_url_to_postid( $photo_url );
            if ( $del_id ) {
                wp_delete_attachment( $del_id, true );
            }
        } else {
            // No URL specified — delete all photos
            foreach ( $photos as $item ) {
                $del_id = attachment_url_to_postid( $item['url'] );
                if ( $del_id ) {
                    wp_delete_attachment( $del_id, true );
                }
            }
            $photos = array();
        }

        $primary_url = ! empty( $photos ) ? ( is_array( $photos[0] ) ? $photos[0]['url'] : $photos[0] ) : null;
        $wpdb->update(
            $households_table,
            array(
                'photos'    => ! empty( $photos ) ? wp_json_encode( $photos ) : null,
                'photo_url' => $primary_url,
            ),
            array( 'id' => $hm->household_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return $this->success( array(
            'message' => __( 'Photo removed.', 'community-directory' ),
            'photos'  => $photos,
        ) );
    }

    /**
     * Update focal point and zoom for a household photo. Head/spouse only.
     * PATCH /members/me/household/photo-position
     * Body: { url: string, fx: number, fy: number, zoom: number }
     */
    public function update_photo_position( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member->id
        ) );
        if ( ! $hm ) {
            return $this->error( 'not_in_household', __( 'You are not part of a household.', 'community-directory' ) );
        }
        if ( ! in_array( $hm->role, array( 'head', 'spouse' ), true ) ) {
            return $this->error( 'not_authorized', __( 'Only the primary membership holder or spouse can update photo position.', 'community-directory' ), 403 );
        }

        $params   = $request->get_json_params();
        $photo_url = sanitize_url( $params['url'] ?? '' );
        $fx        = max( 0, min( 100, (float) ( $params['fx'] ?? 50 ) ) );
        $fy        = max( 0, min( 100, (float) ( $params['fy'] ?? 50 ) ) );
        $zoom      = max( 1.0, min( 3.0, (float) ( $params['zoom'] ?? 1.0 ) ) );

        if ( empty( $photo_url ) ) {
            return $this->error( 'missing_url', __( 'Photo URL is required.', 'community-directory' ) );
        }

        // Load current photos
        $photos_json = $wpdb->get_var( $wpdb->prepare(
            "SELECT photos FROM {$households_table} WHERE id = %d",
            $hm->household_id
        ) );
        $photos_raw = json_decode( $photos_json ?? '', true );
        if ( ! is_array( $photos_raw ) ) {
            $photos_raw = array();
        }
        $photos = array_map( array( $this, 'parse_photo_item' ), $photos_raw );

        // Find and update matching photo
        $found = false;
        foreach ( $photos as &$item ) {
            if ( $item['url'] === $photo_url ) {
                $item['fx']   = $fx;
                $item['fy']   = $fy;
                $item['zoom'] = $zoom;
                $found = true;
                break;
            }
        }
        unset( $item );

        if ( ! $found ) {
            return $this->error( 'not_found', __( 'Photo not found in this household.', 'community-directory' ), 404 );
        }

        $primary_url = is_array( $photos[0] ) ? $photos[0]['url'] : $photos[0];
        $wpdb->update(
            $households_table,
            array(
                'photos'    => wp_json_encode( $photos ),
                'photo_url' => $primary_url,
            ),
            array( 'id' => $hm->household_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return $this->success( array(
            'message' => __( 'Photo position saved.', 'community-directory' ),
            'photos'  => $photos,
        ) );
    }

    /**
     * Normalize a photo list item into a consistent object with url/fx/fy/zoom.
     * Handles both legacy string format and new object format.
     *
     * @param string|array $item  A photo entry from the stored JSON array.
     * @return array              Normalized { url, fx, fy, zoom }.
     */
    private function parse_photo_item( $item ) {
        if ( is_string( $item ) ) {
            return array( 'url' => $item, 'fx' => 50, 'fy' => 50, 'zoom' => 1.0 );
        }
        return array(
            'url'  => sanitize_url( $item['url'] ?? '' ),
            'fx'   => max( 0, min( 100, (float) ( $item['fx'] ?? 50 ) ) ),
            'fy'   => max( 0, min( 100, (float) ( $item['fy'] ?? 50 ) ) ),
            'zoom' => max( 1.0, min( 3.0, (float) ( $item['zoom'] ?? 1.0 ) ) ),
        );
    }

    /**
     * Deactivate all active members of a household when the household is deactivated.
     * Sets left_at, deactivates member records, destroys WP sessions, and revokes capabilities.
     *
     * @param int $household_id The household being deactivated.
     */
    private function deactivate_household_members( $household_id ) {
        global $wpdb;

        $hm_table      = CD_Database::table( 'household_members' );
        $members_table = CD_Database::table( 'members' );

        // Get all active members in this household
        $active_members = $wpdb->get_results( $wpdb->prepare(
            "SELECT hm.member_id, m.wp_user_id, m.status AS member_status
             FROM {$hm_table} hm
             JOIN {$members_table} m ON m.id = hm.member_id
             WHERE hm.household_id = %d AND hm.left_at IS NULL",
            $household_id
        ) );

        if ( empty( $active_members ) ) {
            return;
        }

        $now = current_time( 'mysql' );

        foreach ( $active_members as $am ) {
            // Mark as left from household
            $wpdb->update(
                $hm_table,
                array( 'left_at' => $now ),
                array( 'household_id' => $household_id, 'member_id' => $am->member_id, 'left_at' => null ),
                array( '%s' ),
                array( '%d', '%d', '%s' )
            );

            // Deactivate the member record if currently active
            if ( 'active' === $am->member_status ) {
                $wpdb->update(
                    $members_table,
                    array( 'status' => 'inactive' ),
                    array( 'id' => $am->member_id ),
                    array( '%s' ),
                    array( '%d' )
                );

                // Google Contacts sync — delete deactivated household member
                if ( class_exists( 'CD_Google_Contacts' ) ) {
                    CD_Google_Contacts::sync_member( $am->member_id, 'delete' );
                }

                // Force logout: destroy sessions and revoke capability
                if ( $am->wp_user_id ) {
                    $sessions = WP_Session_Tokens::get_instance( $am->wp_user_id );
                    $sessions->destroy_all();
                    CD_Capabilities::revoke_cap( $am->wp_user_id, 'cd_member' );
                }

                CD_Audit_Logger::log( CD_Audit_Logger::MEMBER_DEACTIVATED, get_current_user_id(), $am->member_id, array(
                    'reason'       => 'household_deactivated',
                    'household_id' => $household_id,
                ) );
            }
        }
    }
}
