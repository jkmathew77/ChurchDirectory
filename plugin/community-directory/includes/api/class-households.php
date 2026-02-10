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
            "SELECT hm.*, h.name AS household_name, h.primary_address, h.status AS household_status
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

        return $this->success( array(
            'household' => array(
                'id'              => (int) $hm->household_id,
                'name'            => $hm->household_name,
                'address'         => $address,
                'status'          => $hm->household_status,
                'my_role'         => $hm->role,
                'my_role_label'   => self::role_label( $hm->role ),
                'can_manage'      => in_array( $hm->role, array( 'head', 'spouse' ), true ),
                'members'         => $hm_members,
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

            // Create profile
            $wpdb->insert( $profiles_table, array(
                'member_id'  => $new_member_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'emails'     => wp_json_encode( array( array( 'type' => 'primary', 'value' => $email ) ) ),
                'created_at' => current_time( 'mysql' ),
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

        // Create profile with basic info
        $wpdb->insert( $profiles_table, array(
            'member_id'  => $new_member_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'emails'     => wp_json_encode( array() ),
            'created_at' => current_time( 'mysql' ),
        ) );

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
}
