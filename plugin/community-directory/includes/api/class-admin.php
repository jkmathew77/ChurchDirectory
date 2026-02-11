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
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // POST /admin/registrations/{id}/resend-verification
        register_rest_route( CD_API_NAMESPACE, '/admin/registrations/(?P<id>\d+)/resend-verification', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'resend_verification' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // GET /admin/applications — verified applications for review
        register_rest_route( CD_API_NAMESPACE, '/admin/applications', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_applications' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // PUT /admin/applications/{id} — approve/reject/hold/info
        register_rest_route( CD_API_NAMESPACE, '/admin/applications/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_application' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // POST /admin/applications/{id}/resend-invite
        register_rest_route( CD_API_NAMESPACE, '/admin/applications/(?P<id>\d+)/resend-invite', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'resend_invite' ),
            'permission_callback' => array( $this, 'permission_officer' ),
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

        // GET /admin/google/callback — Handle Google OAuth callback for admin sync
        register_rest_route( CD_API_NAMESPACE, '/admin/google/callback', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'google_auth_callback' ),
            'permission_callback' => array( $this, 'permission_public' ), // Public because it's a callback, we verify state/nonce inside
        ) );

        // POST /admin/import/preview — Parse CSV and match against members
        register_rest_route( CD_API_NAMESPACE, '/admin/import/preview', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'csv_import_preview' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /admin/import/confirm — Import selected CSV rows
        register_rest_route( CD_API_NAMESPACE, '/admin/import/confirm', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'csv_import_confirm' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /admin/members — List all members
        register_rest_route( CD_API_NAMESPACE, '/admin/members', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_members' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // PUT /admin/members/{id} — Update member details
        register_rest_route( CD_API_NAMESPACE, '/admin/members/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_member' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // DELETE /admin/members/{id} — Delete/Archive member
        register_rest_route( CD_API_NAMESPACE, '/admin/members/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_member' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // POST /admin/members/{id}/resend-invite — Resend invite to member
        register_rest_route( CD_API_NAMESPACE, '/admin/members/(?P<id>\d+)/resend-invite', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'resend_member_invite' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // GET /admin/members/export — Export members as CSV
        register_rest_route( CD_API_NAMESPACE, '/admin/members/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'export_members_csv' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /admin/members/duplicates — Find potential duplicate members
        register_rest_route( CD_API_NAMESPACE, '/admin/members/duplicates', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'find_duplicate_members' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // POST /admin/members/merge — Merge two member profiles
        register_rest_route( CD_API_NAMESPACE, '/admin/members/merge', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'merge_members' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );

        // GET /admin/stats — Dashboard statistics
        register_rest_route( CD_API_NAMESPACE, '/admin/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_dashboard_stats' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // ── Household Requests ──

        // GET /admin/household-requests — list pending household requests
        register_rest_route( CD_API_NAMESPACE, '/admin/household-requests', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_household_requests' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // PUT /admin/household-requests/{id} — approve/deny household request
        register_rest_route( CD_API_NAMESPACE, '/admin/household-requests/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_household_request' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // ── Deletion Requests ──

        // GET /admin/deletion-requests — list pending deletion requests
        register_rest_route( CD_API_NAMESPACE, '/admin/deletion-requests', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_deletion_requests' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // PUT /admin/deletion-requests/{id} — approve/deny deletion request
        register_rest_route( CD_API_NAMESPACE, '/admin/deletion-requests/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_deletion_request' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // ── WhatsApp Groups CRUD ──

        // GET /admin/whatsapp-groups — list all groups
        register_rest_route( CD_API_NAMESPACE, '/admin/whatsapp-groups', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_whatsapp_groups' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // POST /admin/whatsapp-groups — create group
        register_rest_route( CD_API_NAMESPACE, '/admin/whatsapp-groups', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_whatsapp_group' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // PUT /admin/whatsapp-groups/{id} — update group
        register_rest_route( CD_API_NAMESPACE, '/admin/whatsapp-groups/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_whatsapp_group' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // DELETE /admin/whatsapp-groups/{id} — delete group
        register_rest_route( CD_API_NAMESPACE, '/admin/whatsapp-groups/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_whatsapp_group' ),
            'permission_callback' => array( $this, 'permission_officer' ),
        ) );

        // POST /admin/database/reset — NUCLEAR OPTION (Clean Start)
        register_rest_route( CD_API_NAMESPACE, '/admin/database/reset', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'reset_database' ),
            'permission_callback' => array( $this, 'permission_admin' ),
        ) );
    }

    /* ──────────────────────────────────────
     * MEMBERS
     * ──────────────────────────────────── */

    /**
     * Update member profile and status.
     */
    /**
     * Update member profile and status.
     */
    public function update_member( WP_REST_Request $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );
        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        // Check exists
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$members_table} WHERE id = %d", $id ) );
        if ( ! $member ) {
            return $this->error( 'not_found', __( 'Member not found.', 'community-directory' ), 404 );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            return $this->error( 'no_data', __( 'No data provided.', 'community-directory' ) );
        }

        $wpdb->query( 'START TRANSACTION' );

        try {
            // Updated member status if provided
            if ( isset( $params['status'] ) ) {
                $status = sanitize_text_field( $params['status'] );
                // Basic validation
                $allowed = array( 'active', 'inactive', 'archived', 'deceased' );
                if ( in_array( $status, $allowed, true ) ) {
                    $old_status = $member->status;
                    $wpdb->update(
                        $members_table,
                        array( 'status' => $status ),
                        array( 'id' => $id ),
                        array( '%s' ),
                        array( '%d' )
                    );

                    // Handle household impact and force logout when deactivating
                    if ( in_array( $status, array( 'inactive', 'archived', 'deceased' ), true ) && 'active' === $old_status ) {
                        $this->handle_member_household_removal( $id );
                        $this->force_logout_member( $id );
                    }
                }
            }
            
            // Member Since
            if ( isset( $params['member_since'] ) ) {
                $member_since = sanitize_text_field( $params['member_since'] );
                // Basic date validation or null
                $wpdb->update(
                    $members_table,
                    array( 'member_since' => $member_since ?: null ),
                    array( 'id' => $id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }

            // Update profile fields
            $profile_update = array();
            $profile_format = array();

            // Plain-text string fields (not encrypted)
            $plain_fields = array(
                'first_name', 'last_name', 'bio', 'occupation', 'employer',
                'baptism_date', 'wedding_anniversary', 'name_day',
                'address_line_1', 'address_line_2', 'city', 'state', 'zip_code', 'country',
                'preferred_contact_method', 'preferred_language',
                'avatar_url',
                'school_name', 'major_studies', 'minor_studies', 'graduation_date',
            );

            foreach ( $plain_fields as $key ) {
                if ( isset( $params[ $key ] ) ) {
                    $profile_update[ $key ] = sanitize_text_field( $params[ $key ] );
                    $profile_format[] = '%s';
                }
            }

            // Encrypted PII fields
            $encrypted_fields = array( 'date_of_birth', 'address_home', 'address_mailing', 'emergency_contact_name', 'emergency_contact_phone' );
            foreach ( $encrypted_fields as $key ) {
                if ( isset( $params[ $key ] ) ) {
                    $val = sanitize_text_field( $params[ $key ] );
                    $profile_update[ $key ] = ! empty( $val ) ? CD_Encryption::encrypt( $val ) : '';
                    $profile_format[] = '%s';
                }
            }
            
            // Handle emails/phones separately (JSON)
            // We expect the frontend to send the full array if possible, or we wrap single values
            if ( isset( $params['emails'] ) && is_array( $params['emails'] ) ) {
                 // Sanitize recursive? For now, we trust the structure but sanitize values?
                 // Simple save for now
                 $profile_update['emails'] = json_encode( $params['emails'] );
                 $profile_format[] = '%s';
            } elseif ( isset( $params['email'] ) ) {
                $email = sanitize_email( $params['email'] );
                $emails = array( array( 'type' => 'home', 'value' => $email, 'primary' => true ) );
                $profile_update['emails'] = json_encode( $emails );
                $profile_format[] = '%s';
            }
            
            if ( isset( $params['phones'] ) && is_array( $params['phones'] ) ) {
                 // Simple save
                 $profile_update['phones'] = json_encode( $params['phones'] );
                 $profile_format[] = '%s';
            } elseif ( isset( $params['phone'] ) ) {
                $phone = sanitize_text_field( $params['phone'] );
                $phones = array( array( 'type' => 'mobile', 'value' => $phone, 'primary' => true ) );
                $profile_update['phones'] = json_encode( $phones );
                $profile_format[] = '%s';
            }
            
            // Social Links (expecting array)
            if ( isset( $params['social_links'] ) ) {
                $profile_update['social_links'] = json_encode( $params['social_links'] );
                $profile_format[] = '%s';
            }
            
            // Ministry Tags (expecting array)
            if ( isset( $params['ministry_tags'] ) ) {
                $profile_update['ministry_tags'] = json_encode( $params['ministry_tags'] );
                $profile_format[] = '%s';
            }
            
            // Avatar: only update if explicitly provided by the admin
            if ( isset( $params['avatar_url'] ) ) {
                 $profile_update['avatar_url'] = sanitize_url( $params['avatar_url'] );
                 $profile_format[] = '%s';
            }

            // Child/student fields
            if ( isset( $params['school_type'] ) ) {
                $allowed_school = array( 'high_school', 'college', 'university', 'other', '' );
                $val = sanitize_text_field( $params['school_type'] );
                $profile_update['school_type'] = in_array( $val, $allowed_school, true ) ? $val : '';
                $profile_format[] = '%s';
            }
            if ( isset( $params['sunday_school_teacher_id'] ) ) {
                $profile_update['sunday_school_teacher_id'] = absint( $params['sunday_school_teacher_id'] ) ?: null;
                $profile_format[] = '%d';
            }
            if ( isset( $params['emergency_contact_member_id'] ) ) {
                $profile_update['emergency_contact_member_id'] = absint( $params['emergency_contact_member_id'] ) ?: null;
                $profile_format[] = '%d';
            }

            if ( ! empty( $profile_update ) ) {
                $wpdb->update(
                    $profiles_table,
                    $profile_update,
                    array( 'member_id' => $id ),
                    $profile_format,
                    array( '%d' )
                );
            }

            $wpdb->query( 'COMMIT' );

            // Google Contacts sync — update
            if ( class_exists( 'CD_Google_Contacts' ) ) {
                CD_Google_Contacts::sync_member( $id, 'update' );
            }

            return $this->success( array( 'message' => __( 'Member updated.', 'community-directory' ) ) );

        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return $this->error( 'server_error', $e->getMessage() );
        }
    }

    /**
     * Delete a member.
     */
    public function delete_member( WP_REST_Request $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );
        $members_table    = CD_Database::table( 'members' );
        $profiles_table   = CD_Database::table( 'directory_profiles' );
        $hm_table         = CD_Database::table( 'household_members' );
        $invites_table    = CD_Database::table( 'invites' );
        $resets_table     = CD_Database::table( 'password_resets' );
        $dr_table         = CD_Database::table( 'deletion_requests' );

        // Check exists
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$members_table} WHERE id = %d", $id ) );
        if ( ! $member ) {
            return $this->error( 'not_found', __( 'Member not found.', 'community-directory' ), 404 );
        }

        // Google Contacts sync — delete (must run before DB records are removed)
        if ( class_exists( 'CD_Google_Contacts' ) && CD_Google_Contacts::is_enabled() ) {
            $google_cid = $member->google_contact_id ?? '';
            if ( ! empty( $google_cid ) ) {
                $del_result = CD_Google_Contacts::delete_contact( $google_cid );
                if ( is_wp_error( $del_result ) ) {
                    CD_Logger::warn( 'Google Sync: delete failed for member ' . $id . ': ' . $del_result->get_error_message() );
                    CD_Google_Contacts::queue_retry( $id, array( 'google_contact_id' => $google_cid ), 'delete' );
                }
            }
        }

        // Handle household impact (auto-promote spouse if head is being removed)
        $this->handle_member_household_removal( $id );

        $wpdb->query( 'START TRANSACTION' );

        try {
            // Delete directory profile
            $wpdb->delete( $profiles_table, array( 'member_id' => $id ), array( '%d' ) );

            // Delete household membership records
            $wpdb->delete( $hm_table, array( 'member_id' => $id ), array( '%d' ) );

            // Delete invites for this member
            $wpdb->delete( $invites_table, array( 'member_id' => $id ), array( '%d' ) );

            // Delete deletion requests for this member
            $wpdb->delete( $dr_table, array( 'member_id' => $id ), array( '%d' ) );

            // Revoke cd_member capability and clean up WP user sessions
            if ( ! empty( $member->wp_user_id ) ) {
                $wp_user_id = (int) $member->wp_user_id;

                // Delete password reset tokens
                $wpdb->delete( $resets_table, array( 'user_id' => $wp_user_id ), array( '%d' ) );

                // Revoke cd_member capability
                CD_Capabilities::revoke_cap( $wp_user_id, 'cd_member' );

                // Destroy all sessions (force logout)
                $sessions = WP_Session_Tokens::get_instance( $wp_user_id );
                $sessions->destroy_all();
            }

            // Delete member record last
            $wpdb->delete( $members_table, array( 'id' => $id ), array( '%d' ) );

            $wpdb->query( 'COMMIT' );

            CD_Audit_Logger::log( CD_Audit_Logger::DELETION_PROCESSED, get_current_user_id(), $id, array(
                'action'     => 'hard_delete',
                'wp_user_id' => $member->wp_user_id ?? null,
            ) );

            return $this->success( array( 'message' => __( 'Member and all associated records have been permanently deleted.', 'community-directory' ) ) );

        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return $this->error( 'server_error', $e->getMessage() );
        }
    }

    /**
     * List members with pagination and filtering.
     */
    public function list_members( WP_REST_Request $request ) {
        global $wpdb;

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        
        $status = sanitize_text_field( $request->get_param( 'status' ) ?: 'active' );
        $search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
        $page   = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
        $offset = ( $page - 1 ) * $per;

        // Base Where
        $where = '1=1';
        $args  = array();

        // Status Filter
        if ( $status && 'all' !== $status ) {
            $where .= ' AND m.status = %s';
            $args[] = $status;
        }

        // Search Filter
        if ( $search ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (p.first_name LIKE %s OR p.last_name LIKE %s OR p.emails LIKE %s)';
            $args[] = $search_like;
            $args[] = $search_like;
            $args[] = $search_like;
        }

        // Count totals
        $total_query = "SELECT COUNT(*) FROM {$members_table} m LEFT JOIN {$profiles_table} p ON m.id = p.member_id WHERE {$where}";
        if ( ! empty( $args ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $total_query, $args ) );
        } else {
            $total = (int) $wpdb->get_var( $total_query );
        }

        // Fetch Rows
        // We select ALL profile fields (p.*) to support full editing
        $query = "SELECT m.id, m.status, m.created_at, m.member_since, p.*
                  FROM {$members_table} m
                  LEFT JOIN {$profiles_table} p ON m.id = p.member_id
                  WHERE {$where}
                  ORDER BY p.last_name ASC, p.first_name ASC
                  LIMIT %d OFFSET %d";
        
        $args[] = $per;
        $args[] = $offset;

        $rows = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        // Process rows for display
        foreach ( $rows as $row ) {
            // Decode JSON fields (null-coalesce to avoid PHP 8.1 deprecation)
            $row->emails = json_decode( $row->emails ?? '', true );
            $row->phones = json_decode( $row->phones ?? '', true );
            $row->social_links = json_decode( $row->social_links ?? '', true );
            $row->ministry_tags = json_decode( $row->ministry_tags ?? '', true );
            $row->privacy_settings = json_decode( $row->privacy_settings ?? '', true );

            // Decrypt sensitive PII fields
            $encrypted_fields = array( 'date_of_birth', 'address_home', 'address_mailing', 'emergency_contact_name', 'emergency_contact_phone' );
            foreach ( $encrypted_fields as $ef ) {
                if ( ! empty( $row->$ef ) ) {
                    $decrypted = CD_Encryption::decrypt( $row->$ef );
                    if ( $decrypted !== false ) {
                        $row->$ef = $decrypted;
                    }
                }
            }

            // Get primary email/phone
            $row->primary_email = '';
            if ( ! empty( $row->emails ) && is_array( $row->emails ) ) {
                $row->primary_email = $row->emails[0]['value'] ?? '';
            }

            $row->primary_phone = '';
            if ( ! empty( $row->phones ) && is_array( $row->phones ) ) {
                 $row->primary_phone = $row->phones[0]['value'] ?? '';
            }
        }

        // Attach household info for each member
        $hm_table = CD_Database::table( 'household_members' );
        $households_table = CD_Database::table( 'households' );
        $role_labels = CD_API_Households::role_options();

        foreach ( $rows as $row ) {
            $hh = $wpdb->get_row( $wpdb->prepare(
                "SELECT hm.role, h.id AS household_id, h.name AS household_name
                 FROM {$hm_table} hm
                 JOIN {$households_table} h ON hm.household_id = h.id
                 WHERE hm.member_id = %d AND hm.left_at IS NULL
                 LIMIT 1",
                $row->id
            ) );
            if ( $hh ) {
                $row->household_id   = (int) $hh->household_id;
                $row->household_name = $hh->household_name;
                $row->household_role = $hh->role;
                $row->household_role_label = isset( $role_labels[ $hh->role ] ) ? $role_labels[ $hh->role ] : $hh->role;
            } else {
                $row->household_id   = null;
                $row->household_name = null;
                $row->household_role = null;
                $row->household_role_label = null;
            }
        }

        // Get counts for tabs
        $counts_raw = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$members_table} GROUP BY status" );
        $counts = array( 'all' => 0 );
        foreach ( $counts_raw as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }

        return $this->success( array(
            'members' => $rows,
            'counts'  => $counts,
        ), array(
            'page'     => $page,
            'per_page' => $per,
            'total'    => $total,
            'pages'    => ceil( $total / $per ),
        ) );
    }

    /* ──────────────────────────────────────
     * REGISTRATIONS (All applications)
     * ──────────────────────────────────── */

    /**
     * List all applications including unverified AND pending member invites.
     */
    public function list_registrations( WP_REST_Request $request ) {
        global $wpdb;

        $apps_table    = CD_Database::table( 'applications' );
        $members_table = CD_Database::table( 'members' );
        $invites_table = CD_Database::table( 'invites' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $status = sanitize_text_field( $request->get_param( 'status' ) ?: '' );
        $page   = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
        $offset = ( $page - 1 ) * $per;

        // We build a UNION query to combine Applications and Pending Invites (Members)
        
        // 1. Applications Query Parts
        // Select matching columns
        $sql_apps = "
            SELECT 
                id, 
                'application' as type, 
                first_name, 
                last_name, 
                email, 
                status, 
                submitted_at, 
                verified_at 
            FROM {$apps_table} 
            WHERE 1=1
        ";

        // 2. Member Invites Query Parts
        // Members who have no user_id (not registered) but have an invite
        // We look for the MOST RECENT invite for each member to determine status check? 
        // Or just list members who are 'active' but 'user_id' is NULL?
        // Let's list Members with user_id=NULL AND status='active'.
        // We fake the 'status' column to 'pending_verification' (or 'invited') for UI.
        $sql_members = "
            SELECT 
                m.id, 
                'member_invite' as type, 
                p.first_name, 
                p.last_name, 
                (SELECT email FROM {$invites_table} WHERE member_id = m.id ORDER BY created_at DESC LIMIT 1) as email,
                'pending_verification' as status, 
                m.created_at as submitted_at, 
                NULL as verified_at 
            FROM {$members_table} m
            JOIN {$profiles_table} p ON m.id = p.member_id
            WHERE m.wp_user_id IS NULL AND m.status = 'active'
        ";

        // Wrappers for filtering
        $sql_union = "
            SELECT SQL_CALC_FOUND_ROWS * FROM (
                ($sql_apps)
                UNION ALL
                ($sql_members)
            ) as combined_table
            WHERE 1=1
        ";

        $args = array();

        // Apply Status Filter
        if ( $status && 'all' !== $status ) {
            $sql_union .= " AND status = %s";
            $args[] = $status;
        }

        // Apply Pagination
        $sql_union .= " ORDER BY submitted_at DESC LIMIT %d OFFSET %d";
        $args[] = $per;
        $args[] = $offset;

        // Execute Main Query
        $rows = $wpdb->get_results( $wpdb->prepare( $sql_union, $args ) );
        
        // Get Total Rows (for pagination)
        $total = (int) $wpdb->get_var( "SELECT FOUND_ROWS()" );

        // Calculate Counts (Expensive? We can do separate count queries)
        // Count Applications
        $count_apps = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$apps_table} GROUP BY status" );
        // Count Pending Invites (Members)
        $count_invites = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$members_table} WHERE wp_user_id IS NULL AND status = 'active'" );

        $counts = array( 'all' => 0 );
        
        // Fill defaults
        $statuses = array('pending_verification', 'new', 'approved', 'not_approved', 'archived');
        foreach ($statuses as $s) $counts[$s] = 0;

        foreach ( $count_apps as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }

        // Add invites to 'pending_verification' count?
        $counts['pending_verification'] += $count_invites;
        $counts['all'] += $count_invites;

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
     * Resend invite to a member (imported/added manually).
     */
    public function resend_member_invite( WP_REST_Request $request ) {
        global $wpdb;

        $member_id = (int) $request->get_param( 'id' );
        $members_table = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        
        // Check member exists and is pending
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$members_table} WHERE id = %d", $member_id ) );
        if ( ! $member ) {
            return $this->error( 'not_found', __( 'Member not found.', 'community-directory' ), 404 );
        }
        if ( ! empty( $member->wp_user_id ) ) {
            return $this->error( 'already_registered', __( 'This member already has a registered account.', 'community-directory' ), 400 );
        }

        // Get email/name from profile or invites?
        // We need an email address.
        // Try profile emails first, then look for previous invites?
        $profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$profiles_table} WHERE member_id = %d", $member_id ) );
        $email = '';
        
        if ( $profile && ! empty( $profile->emails ) ) {
            $emails = json_decode( $profile->emails, true );
            if ( ! empty( $emails ) && is_array( $emails ) ) {
                $email = $emails[0]['value']; // Use primary/first
            }
        }

        // Fallback: Check last invite
        if ( empty( $email ) ) {
            $invites_table = CD_Database::table( 'invites' );
            $last_invite = $wpdb->get_row( $wpdb->prepare( "SELECT email FROM {$invites_table} WHERE member_id = %d ORDER BY created_at DESC LIMIT 1", $member_id ) );
            if ( $last_invite ) $email = $last_invite->email;
        }

        if ( empty( $email ) ) {
            return $this->error( 'no_email', __( 'No email address found for this member.', 'community-directory' ), 400 );
        }

        // Rate limit
        if ( $this->is_rate_limited( 'resend_member_invite_' . $member_id, 3, DAY_IN_SECONDS ) ) {
            return $this->error( 'rate_limited', __( 'Invite resend limit reached. Try again tomorrow.', 'community-directory' ), 429 );
        }
        
        // Invalidate old invite tokens
        $invites_table = CD_Database::table( 'invites' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$invites_table} SET used_at = %s WHERE member_id = %d AND used_at IS NULL",
            current_time( 'mysql' ),
            $member_id
        ) );

        // Generate new token
        $token       = bin2hex( random_bytes( 32 ) );
        $token_hash  = hash( 'sha256', $token );
        $expiry_days = (int) get_option( 'cd_invite_expiry', 14 );

        $wpdb->insert( $invites_table, array(
            'member_id'   => $member_id,
            'email'       => $email,
            'token_hash'  => $token_hash,
            'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) ),
            'created_at'  => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s' ) );

        // Send invite
        $first_name = $profile ? $profile->first_name : 'Member';
        if ( class_exists( 'CD_Email_Templates' ) ) {
            CD_Email_Templates::send_invite( $email, $first_name, $token );
        }

        CD_Audit_Logger::log( CD_Audit_Logger::INVITE_RESENT, get_current_user_id(), $member_id );

        return $this->success( array(
            'message' => __( 'Invite email sent to ', 'community-directory' ) . $email,
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

            try {
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
                
                // Send Invite
                if ( $email ) {
                    $token      = bin2hex( random_bytes( 32 ) );
                    $token_hash = hash( 'sha256', $token );
                    $expiry_days = (int) get_option( 'cd_invite_expiry', 14 );

                    $invites_table = CD_Database::table( 'invites' );
                    $wpdb->insert( $invites_table, array(
                        'application_id' => null,
                        'member_id'      => $member_id,
                        'email'          => $email,
                        'token_hash'     => $token_hash,
                        'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) ),
                        'created_at'     => current_time( 'mysql' ),
                    ), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
                    
                    // Send email
                    if ( class_exists( 'CD_Email_Templates' ) ) {
                        CD_Email_Templates::send_invite( $email, $first_name, $token );
                    }
                }
            } catch ( Exception $e ) {
                 CD_Logger::error( 'Admin: Exception during Google sync: ' . $e->getMessage() );
            } catch ( Error $e ) {
                 CD_Logger::error( 'Admin: Fatal Error during Google sync: ' . $e->getMessage() );
            }
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
    /**
     * Handle Google OAuth callback for admin sync.
     */
    public function google_auth_callback( WP_REST_Request $request ) {
        CD_Logger::info( 'Google Contacts: Callback API hit.' );

        // REST API doesn't authenticate via cookies without X-WP-Nonce header.
        // Since this is a browser redirect from Google, manually authenticate
        // from the WordPress logged_in cookie so nonce verification works.
        $user_id = wp_validate_auth_cookie( '', 'logged_in' );
        if ( $user_id ) {
            wp_set_current_user( $user_id );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_redirect( admin_url( 'admin.php?page=cd-settings&google_error=' . urlencode( 'Unauthorized — please log in as an admin.' ) ) );
            exit;
        }

        $state = sanitize_text_field( $request->get_param( 'state' ) ?? '' );

        // redirect back to settings page with error if state is invalid
        if ( ! wp_verify_nonce( $state, 'cd_google_oauth' ) ) {
            CD_Logger::warn( 'Google Contacts: Invalid nonce/state: ' . $state . ' for user ' . get_current_user_id() );
            wp_redirect( admin_url( 'admin.php?page=cd-settings&google_error=' . urlencode( 'Invalid state parameter' ) ) );
            exit;
        }

        if ( $request->get_param( 'error' ) ) {
            $error_msg = sanitize_text_field( $request->get_param( 'error' ) );
            CD_Logger::error( 'Google Contacts: Google returned error: ' . $error_msg );
            wp_redirect( admin_url( 'admin.php?page=cd-settings&google_error=' . urlencode( $error_msg ) ) );
            exit;
        }

        $code = sanitize_text_field( $request->get_param( 'code' ) ?? '' );
        if ( empty( $code ) ) {
            CD_Logger::warn( 'Google Contacts: No code received.' );
            wp_redirect( admin_url( 'admin.php?page=cd-settings&google_error=' . urlencode( 'No code received' ) ) );
            exit;
        }

        CD_Logger::info( 'Google Contacts: Code received. Attempting exchange.' );

        // We need to defer to the Google Contacts class to handle the exchange
        // since it manages the options and encryption.
        if ( class_exists( 'CD_Google_Contacts' ) ) {
            $result = CD_Google_Contacts::exchange_code( $code );
            
            if ( is_wp_error( $result ) ) {
                wp_redirect( admin_url( 'admin.php?page=cd-settings&google_error=' . urlencode( $result->get_error_message() ) ) );
            } else {
                wp_redirect( admin_url( 'admin.php?page=cd-settings&google_connected=1' ) );
            }
            exit;
        }
        
        return $this->error( 'server_error', __( 'Google Contacts class not found.', 'community-directory' ) );
    }

    /**
     * Preview CSV Import.
     */
    public function csv_import_preview( WP_REST_Request $request ) {
        global $wpdb;

        CD_Logger::info( 'Admin: csv_import_preview called.' );

        try {
            $files = $request->get_file_params();
            if ( empty( $files['file'] ) ) {
                CD_Logger::warn( 'Admin: No file in request.' );
                return $this->error( 'no_file', __( 'No file uploaded.', 'community-directory' ) );
            }

            $file = $files['file'];
            CD_Logger::debug( 'Admin: File uploaded: ' . print_r( $file, true ) );

            // Basic MIME check (not foolproof, but helpful)
            $allowed_mimes = array( 'text/csv', 'application/vnd.ms-excel', 'text/plain', 'application/csv' );
            $is_csv = false;
            
            if ( in_array( $file['type'], $allowed_mimes, true ) ) {
                $is_csv = true;
            } else {
                 $len = strlen( '.csv' );
                 $filename = strtolower( $file['name'] );
                 if ( strlen( $filename ) >= $len && 0 === substr_compare( $filename, '.csv', -$len, $len ) ) {
                     $is_csv = true;
                 }
            }

            if ( ! $is_csv ) {
                 CD_Logger::warn( 'Admin: Invalid CSV MIME/Extended check failed.' );
                 return $this->error( 'invalid_file', __( 'Please upload a valid CSV file.', 'community-directory' ) );
            }

            // Parse CSV
            if ( empty( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
                 CD_Logger::warn( 'Admin: CSV file not readable or tmp_name empty: ' . ($file['tmp_name'] ?? 'NULL') );
                 return $this->error( 'file_error', __( 'Cannot read uploaded file.', 'community-directory' ) );
            }

            $lines = file( $file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            if ( false === $lines ) {
                 CD_Logger::warn( 'Admin: Failed to read CSV lines.' );
                 return $this->error( 'file_error', __( 'Failed to read file contents.', 'community-directory' ) );
            }

            $rows = array_map( 'str_getcsv', $lines );
            if ( empty( $rows ) ) {
                CD_Logger::warn( 'Admin: CSV parsed but empty rows.' );
                return $this->error( 'empty_file', __( 'The file appears to be empty.', 'community-directory' ) );
            }
            
            // Check if first row is valid
            if ( empty( $rows[0] ) ) {
                 CD_Logger::debug( 'Admin: First row is empty/invalid.' );
                 return $this->error( 'empty_file', __( 'Invalid CSV format.', 'community-directory' ) );
            }

            $header = array_shift( $rows );
            // Remove BOM if present
            $header[0] = preg_replace( '/\xEF\xBB\xBF/', '', $header[0] );
            $header = array_map( 'trim', $header );

            $csv_data = array();
            foreach ( $rows as $row ) {
                if ( count( $row ) === count( $header ) ) {
                    $item = array_combine( $header, $row );
                    // Filter out empty rows
                    if ( array_filter( $item ) ) {
                        $csv_data[] = $item;
                    }
                }
            }
        } catch ( Exception $e ) {
            CD_Logger::error( 'Admin: Exception in csv_import_preview: ' . $e->getMessage() );
             return $this->error( 'server_error', __( 'An internal error occurred during import.', 'community-directory' ) );
        } catch ( Error $e ) {
             CD_Logger::error( 'Admin: Fatal Error in csv_import_preview: ' . $e->getMessage() );
             return $this->error( 'server_error', __( 'A fatal error occurred during import.', 'community-directory' ) );
        }

        // Fetch existing members for matching
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $members_table  = CD_Database::table( 'members' );
        $existing = $wpdb->get_results(
            "SELECT m.id, p.first_name, p.last_name, p.emails
             FROM {$members_table} m
             JOIN {$profiles_table} p ON m.id = p.member_id
             WHERE m.status = 'active'"
        );

        $existing_normalized = array();
        foreach ( $existing as $member ) {
            $email = '';
            if ( ! empty( $member->emails ) ) {
                $emails_arr = json_decode( $member->emails, true );
                if ( ! empty( $emails_arr[0]['value'] ) ) {
                    $email = strtolower( trim( $emails_arr[0]['value'] ) );
                }
            }
            $existing_normalized[] = array(
                'id'         => $member->id,
                'first_name' => strtolower( trim( $member->first_name ) ),
                'last_name'  => strtolower( trim( $member->last_name ) ),
                'email'      => $email,
            );
        }

        // Process and match
        $contacts = array();
        foreach ( $csv_data as $row ) {
            $first = trim( $row['First Name'] ?? '' );
            $last  = trim( $row['Last Name'] ?? '' );
            $email = trim( $row['Email'] ?? '' );
            $phone = trim( $row['Phone'] ?? '' );

            // Construct contact object
            $contact = array(
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $email,
                'phone'      => $phone,
                'address'    => trim( $row['Address Line 1'] ?? '' ),
                'city'       => trim( $row['City'] ?? '' ),
                'state'      => trim( $row['State'] ?? '' ),
                'zip'        => trim( $row['Zip'] ?? '' ),
                'dob'        => trim( $row['Date of Birth (YYYY-MM-DD)'] ?? '' ),
                'match_type' => 'none',
                'member_id'  => null,
            );

            // Match logic
            $email_lower = strtolower( $email );
            $first_lower = strtolower( $first );
            $last_lower  = strtolower( $last );

            foreach ( $existing_normalized as $ex ) {
                // 1. Exact Email Match
                if ( $email_lower && $ex['email'] === $email_lower ) {
                    $contact['match_type'] = 'exact';
                    $contact['member_id']  = $ex['id'];
                    break;
                }
                // 2. Exact Name Match
                if ( $first_lower && $last_lower && $ex['first_name'] === $first_lower && $ex['last_name'] === $last_lower ) {
                    $contact['match_type'] = 'strong';
                    $contact['member_id']  = $ex['id'];
                    // Don't break yet, keep looking for email match which is stronger
                }
            }
            
            // Re-label for UI consistency
            $contact['name'] = trim( $first . ' ' . $last );

            $contacts[] = $contact;
        }

        return $this->success( array(
            'contacts'    => $contacts,
            'totalPeople' => count( $contacts ),
        ) );
    }

    /**
     * Confirm CSV Import.
     */
    public function csv_import_confirm( WP_REST_Request $request ) {
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
            
            // Wrap in try-catch to prevent critical errors from crashing the whole request
            try {
                // Skip matches
                if ( ! empty( $contact['member_id'] ) ) {
                    continue;
                }

                if ( empty( $first_name ) && empty( $last_name ) ) {
                    continue;
                }

                // Create member
                $uuid = wp_generate_uuid4();
                $wpdb->insert( $members_table, array(
                    'uuid'         => $uuid,
                    'status'       => 'active',
                    'member_since' => current_time( 'Y-m-d' ),
                    'created_at'   => current_time( 'mysql' ),
                ), array( '%s', '%s', '%s', '%s' ) );
                $member_id = $wpdb->insert_id;

                if ( ! $member_id ) {
                    CD_Logger::error( 'Admin: Failed to insert member.' );
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

                // Extra CSV fields
                if ( ! empty( $contact['dob'] ) ) {
                    $profile_data['date_of_birth'] = sanitize_text_field( $contact['dob'] );
                }
                
                // Address encryption
                $addr_parts = array();
                if ( ! empty( $contact['address'] ) ) $addr_parts[] = $contact['address'];
                if ( ! empty( $contact['city'] ) )    $addr_parts[] = $contact['city'];
                if ( ! empty( $contact['state'] ) )   $addr_parts[] = $contact['state'];
                if ( ! empty( $contact['zip'] ) )     $addr_parts[] = $contact['zip'];
                
                if ( ! empty( $addr_parts ) && class_exists( 'CD_Encryption' ) ) {
                     $profile_data['address_home'] = CD_Encryption::encrypt( implode( ', ', $addr_parts ) );
                }

                $wpdb->insert( $profiles_table, $profile_data );

                // Google Contacts sync — create imported member
                if ( class_exists( 'CD_Google_Contacts' ) ) {
                    CD_Google_Contacts::sync_member( $member_id, 'create' );
                }

                $imported++;

                // Send Invite
                if ( $email ) {
                    $token      = bin2hex( random_bytes( 32 ) );
                    $token_hash = hash( 'sha256', $token );
                    $expiry_days = (int) get_option( 'cd_invite_expiry', 14 );

                    $invites_table = CD_Database::table( 'invites' );
                    $wpdb->insert( $invites_table, array(
                        'application_id' => null, // Imported members have no application
                        'member_id'      => $member_id,
                        'email'          => $email,
                        'token_hash'     => $token_hash,
                        'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) ),
                        'created_at'     => current_time( 'mysql' ),
                    ), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
                    
                    // Send email if class exists
                    if ( class_exists( 'CD_Email_Templates' ) ) {
                        CD_Email_Templates::send_invite( $email, $first_name, $token );
                    } else {
                        CD_Logger::warn( 'Admin: CD_Email_Templates class not found during import.' );
                    }
                }
            } catch ( Exception $e ) {
                CD_Logger::error( 'Admin: Exception during import row: ' . $e->getMessage() );
            } catch ( Error $e ) {
                CD_Logger::error( 'Admin: Fatal Error during import row: ' . $e->getMessage() );
            }
        }

        CD_Audit_Logger::log( CD_Audit_Logger::BULK_OPERATION, get_current_user_id(), null, array(
            'action'   => 'csv_import',
            'imported' => $imported,
        ) );

        return $this->success( array(
            'message'  => sprintf( __( '%d member(s) imported successfully.', 'community-directory' ), $imported ),
            'imported' => $imported,
        ) );
    }

    /* ──────────────────────────────────────
     * HOUSEHOLD REQUESTS
     * ──────────────────────────────────── */

    /**
     * List household requests (merge requests).
     */
    public function list_household_requests( WP_REST_Request $request ) {
        global $wpdb;

        $hr_table         = CD_Database::table( 'household_requests' );
        $profiles_table   = CD_Database::table( 'directory_profiles' );
        $households_table = CD_Database::table( 'households' );
        $hm_table         = CD_Database::table( 'household_members' );

        $status = sanitize_text_field( $request->get_param( 'status' ) ?: 'pending' );

        $where = '1=1';
        $args  = array();
        if ( 'all' !== $status ) {
            $where .= ' AND hr.status = %s';
            $args[] = $status;
        }

        $query = "SELECT hr.*,
                    p.first_name AS requester_first, p.last_name AS requester_last,
                    hs.name AS source_name,
                    ht.name AS target_name,
                    (SELECT COUNT(*) FROM {$hm_table} WHERE household_id = hr.household_id AND left_at IS NULL) AS source_count,
                    (SELECT COUNT(*) FROM {$hm_table} WHERE household_id = hr.target_household_id AND left_at IS NULL) AS target_count
                  FROM {$hr_table} hr
                  LEFT JOIN {$profiles_table} p ON hr.requesting_member_id = p.member_id
                  LEFT JOIN {$households_table} hs ON hr.household_id = hs.id
                  LEFT JOIN {$households_table} ht ON hr.target_household_id = ht.id
                  WHERE {$where}
                  ORDER BY hr.created_at DESC";

        $rows = ! empty( $args )
            ? $wpdb->get_results( $wpdb->prepare( $query, $args ) )
            : $wpdb->get_results( $query );

        // Status counts
        $counts_raw = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$hr_table} GROUP BY status" );
        $counts = array( 'all' => 0 );
        foreach ( $counts_raw as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }

        return $this->success( array(
            'requests' => $rows,
            'counts'   => $counts,
        ) );
    }

    /**
     * Approve or deny a household request.
     */
    public function update_household_request( WP_REST_Request $request ) {
        global $wpdb;

        $id     = (int) $request->get_param( 'id' );
        $action = sanitize_text_field( $request->get_param( 'action' ) ?: '' );
        $notes  = sanitize_textarea_field( $request->get_param( 'notes' ) ?: '' );

        $hr_table = CD_Database::table( 'household_requests' );

        $hr = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hr_table} WHERE id = %d",
            $id
        ) );
        if ( ! $hr ) {
            return $this->error( 'not_found', __( 'Request not found.', 'community-directory' ), 404 );
        }
        if ( 'pending' !== $hr->status ) {
            return $this->error( 'already_processed', __( 'This request has already been processed.', 'community-directory' ) );
        }

        if ( 'deny' === $action ) {
            $wpdb->update( $hr_table, array(
                'status'      => 'rejected',
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time( 'mysql' ),
                'notes'       => $notes,
            ), array( 'id' => $id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );

            CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MERGE_DENIED, get_current_user_id(), $hr->household_id, array(
                'request_id'          => $id,
                'target_household_id' => $hr->target_household_id,
            ) );

            return $this->success( array( 'message' => __( 'Request denied.', 'community-directory' ) ) );
        }

        if ( 'approve' === $action && 'merge' === $hr->type ) {
            return $this->process_merge( $hr, $notes );
        }

        return $this->error( 'invalid_action', __( 'Invalid action.', 'community-directory' ) );
    }

    /**
     * Process a household merge: move all members from source to target.
     */
    private function process_merge( $hr, $notes ) {
        global $wpdb;

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );
        $hr_table          = CD_Database::table( 'household_requests' );

        $source_id = (int) $hr->household_id;
        $target_id = (int) $hr->target_household_id;

        // Get all active members from source
        $source_members = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE household_id = %d AND left_at IS NULL",
            $source_id
        ) );

        // Check target household has a head and spouse status
        $target_has_head = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$hm_table} WHERE household_id = %d AND role = 'head' AND left_at IS NULL",
            $target_id
        ) );
        $target_has_spouse = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$hm_table} WHERE household_id = %d AND role = 'spouse' AND left_at IS NULL",
            $target_id
        ) );

        $wpdb->query( 'START TRANSACTION' );

        $moved = 0;
        foreach ( $source_members as $sm ) {
            // Set left_at on source
            $wpdb->update(
                $hm_table,
                array( 'left_at' => current_time( 'mysql' ) ),
                array( 'id' => $sm->id ),
                array( '%s' ),
                array( '%d' )
            );

            // Determine role for target: demote if conflict
            $new_role = $sm->role;
            if ( 'head' === $new_role && $target_has_head ) {
                $new_role = 'other';
            }
            if ( 'spouse' === $new_role && $target_has_spouse ) {
                $new_role = 'other';
            }

            // Check for existing row in target (member may have been there before)
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$hm_table} WHERE household_id = %d AND member_id = %d",
                $target_id, $sm->member_id
            ) );

            if ( $existing ) {
                $wpdb->update( $hm_table, array(
                    'left_at'   => null,
                    'role'      => $new_role,
                    'joined_at' => current_time( 'mysql' ),
                ), array( 'id' => $existing->id ), array( '%s', '%s', '%s' ), array( '%d' ) );
            } else {
                $wpdb->insert( $hm_table, array(
                    'household_id' => $target_id,
                    'member_id'    => $sm->member_id,
                    'role'         => $new_role,
                    'joined_at'    => current_time( 'mysql' ),
                ), array( '%d', '%d', '%s', '%s' ) );
            }

            $moved++;
        }

        // Deactivate source household
        $wpdb->update( $households_table, array( 'status' => 'inactive' ), array( 'id' => $source_id ), array( '%s' ), array( '%d' ) );

        // Update request
        $wpdb->update( $hr_table, array(
            'status'      => 'approved',
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => current_time( 'mysql' ),
            'notes'       => $notes,
        ), array( 'id' => $hr->id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );

        $wpdb->query( 'COMMIT' );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MERGE_APPROVED, get_current_user_id(), $source_id, array(
            'request_id'          => $hr->id,
            'target_household_id' => $target_id,
            'members_moved'       => $moved,
        ) );

        CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_MERGED, get_current_user_id(), $target_id, array(
            'source_household_id' => $source_id,
            'members_moved'       => $moved,
        ) );

        return $this->success( array(
            'message' => sprintf( __( 'Merge approved. %d member(s) moved to the target household.', 'community-directory' ), $moved ),
        ) );
    }

    /* ──────────────────────────────────────
     * DELETION REQUESTS
     * ──────────────────────────────────── */

    /**
     * List deletion requests.
     */
    public function list_deletion_requests( WP_REST_Request $request ) {
        global $wpdb;

        $dr_table       = CD_Database::table( 'deletion_requests' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $members_table  = CD_Database::table( 'members' );
        $hm_table       = CD_Database::table( 'household_members' );
        $hh_table       = CD_Database::table( 'households' );

        $status = sanitize_text_field( $request->get_param( 'status' ) ?: 'pending' );

        $where = '1=1';
        $args  = array();
        if ( 'all' !== $status ) {
            $where .= ' AND dr.status = %s';
            $args[] = $status;
        }

        $query = "SELECT dr.*, p.first_name, p.last_name, p.emails
                  FROM {$dr_table} dr
                  LEFT JOIN {$profiles_table} p ON dr.member_id = p.member_id
                  WHERE {$where}
                  ORDER BY dr.requested_at DESC";

        $rows = ! empty( $args )
            ? $wpdb->get_results( $wpdb->prepare( $query, $args ) )
            : $wpdb->get_results( $query );

        // Add household info and email for each request
        foreach ( $rows as $row ) {
            $emails = json_decode( $row->emails ?? '', true ) ?: array();
            $row->primary_email = ! empty( $emails[0]['value'] ) ? $emails[0]['value'] : '';
            unset( $row->emails );

            $hh = $wpdb->get_row( $wpdb->prepare(
                "SELECT hm.role, h.name
                 FROM {$hm_table} hm
                 JOIN {$hh_table} h ON hm.household_id = h.id
                 WHERE hm.member_id = %d AND hm.left_at IS NULL
                 LIMIT 1",
                $row->member_id
            ) );
            $row->household_name = $hh ? $hh->name : null;
            $row->household_role = $hh ? $hh->role : null;
        }

        // Counts
        $counts_raw = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$dr_table} GROUP BY status" );
        $counts = array( 'all' => 0 );
        foreach ( $counts_raw as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }

        return $this->success( array(
            'requests' => $rows,
            'counts'   => $counts,
        ) );
    }

    /**
     * Process a deletion request: approve (deactivate member) or deny.
     */
    public function update_deletion_request( WP_REST_Request $request ) {
        global $wpdb;

        $id     = (int) $request->get_param( 'id' );
        $action = sanitize_text_field( $request->get_param( 'action' ) ?: '' );

        $dr_table      = CD_Database::table( 'deletion_requests' );
        $members_table = CD_Database::table( 'members' );

        $dr = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$dr_table} WHERE id = %d",
            $id
        ) );
        if ( ! $dr ) {
            return $this->error( 'not_found', __( 'Request not found.', 'community-directory' ), 404 );
        }
        if ( 'pending' !== $dr->status ) {
            return $this->error( 'already_processed', __( 'This request has already been processed.', 'community-directory' ) );
        }

        if ( 'deny' === $action ) {
            $wpdb->update( $dr_table, array(
                'status'          => 'denied',
                'acknowledged_by' => get_current_user_id(),
                'acknowledged_at' => current_time( 'mysql' ),
            ), array( 'id' => $id ), array( '%s', '%d', '%s' ), array( '%d' ) );

            return $this->success( array( 'message' => __( 'Deletion request denied.', 'community-directory' ) ) );
        }

        if ( 'approve' === $action ) {
            // Handle household impact first
            $this->handle_member_household_removal( $dr->member_id );

            // Deactivate member
            $wpdb->update( $members_table, array(
                'status' => 'inactive',
            ), array( 'id' => $dr->member_id ), array( '%s' ), array( '%d' ) );

            // Google Contacts sync — delete on deletion approval
            if ( class_exists( 'CD_Google_Contacts' ) ) {
                CD_Google_Contacts::sync_member( $dr->member_id, 'delete' );
            }

            // Force logout — destroy sessions and revoke capability
            $this->force_logout_member( $dr->member_id );

            // Update request
            $wpdb->update( $dr_table, array(
                'status'          => 'approved',
                'acknowledged_by' => get_current_user_id(),
                'acknowledged_at' => current_time( 'mysql' ),
            ), array( 'id' => $id ), array( '%s', '%d', '%s' ), array( '%d' ) );

            CD_Audit_Logger::log( CD_Audit_Logger::DELETION_PROCESSED, get_current_user_id(), $dr->member_id );

            return $this->success( array( 'message' => __( 'Member deactivated and deletion request processed.', 'community-directory' ) ) );
        }

        return $this->error( 'invalid_action', __( 'Invalid action.', 'community-directory' ) );
    }

    /* ──────────────────────────────────────
     * HELPER: HOUSEHOLD REMOVAL ON DEACTIVATION
     * ──────────────────────────────────── */

    /**
     * Handle household impact when a member is deactivated/deleted.
     * - If head and spouse exists: auto-promote spouse
     * - Set left_at on household membership
     * - If household now empty: set inactive
     */
    private function handle_member_household_removal( $member_id ) {
        global $wpdb;

        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );

        $membership = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
            $member_id
        ) );

        if ( ! $membership ) {
            return; // Not in a household
        }

        $household_id = (int) $membership->household_id;

        // If member is head, try to auto-promote spouse
        if ( 'head' === $membership->role ) {
            $spouse = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$hm_table} WHERE household_id = %d AND role = 'spouse' AND left_at IS NULL",
                $household_id
            ) );

            if ( $spouse ) {
                $wpdb->update(
                    $hm_table,
                    array( 'role' => 'head' ),
                    array( 'id' => $spouse->id ),
                    array( '%s' ),
                    array( '%d' )
                );

                CD_Audit_Logger::log( CD_Audit_Logger::HOUSEHOLD_ROLE_CHANGED, get_current_user_id(), $household_id, array(
                    'member_id' => $spouse->member_id,
                    'old_role'  => 'spouse',
                    'new_role'  => 'head',
                    'reason'    => 'auto_promote_on_head_deactivation',
                ) );
            }
        }

        // Set left_at on the member
        $wpdb->update(
            $hm_table,
            array( 'left_at' => current_time( 'mysql' ) ),
            array( 'member_id' => $member_id, 'left_at' => null ),
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
    }

    /**
     * Force-logout a member by destroying all their WordPress sessions
     * and revoking the cd_member capability.
     *
     * @param int $member_id The member table ID (not WP user ID).
     */
    private function force_logout_member( $member_id ) {
        global $wpdb;

        $members_table = CD_Database::table( 'members' );
        $wp_user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT wp_user_id FROM {$members_table} WHERE id = %d",
            $member_id
        ) );

        if ( ! $wp_user_id ) {
            return;
        }

        // Destroy all sessions for this user (forces logout everywhere)
        $sessions = WP_Session_Tokens::get_instance( $wp_user_id );
        $sessions->destroy_all();

        // Revoke directory capability so they can't access even if re-authenticated
        CD_Capabilities::revoke_cap( $wp_user_id, 'cd_member' );
    }

    /* ──────────────────────────────────────
     * WHATSAPP GROUPS CRUD
     * ──────────────────────────────────── */

    /**
     * List all WhatsApp groups (admin view — includes inactive).
     */
    public function list_whatsapp_groups( WP_REST_Request $request ) {
        global $wpdb;
        $table = CD_Database::table( 'whatsapp_groups' );

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY display_order ASC, name ASC"
        );

        $groups = array();
        foreach ( $rows as $row ) {
            $groups[] = array(
                'id'             => (int) $row->id,
                'name'           => $row->name,
                'description'    => $row->description ?: '',
                'invite_url'     => $row->invite_url,
                'icon'           => $row->icon ?: '',
                'visibility'     => $row->visibility,
                'visibility_tag' => $row->visibility_tag ?: '',
                'display_order'  => (int) $row->display_order,
                'is_active'      => (bool) $row->is_active,
                'created_at'     => $row->created_at,
            );
        }

        return $this->success( array( 'groups' => $groups ) );
    }

    /**
     * Create a WhatsApp group.
     */
    public function create_whatsapp_group( WP_REST_Request $request ) {
        global $wpdb;
        $table  = CD_Database::table( 'whatsapp_groups' );
        $params = $request->get_json_params();

        $name = sanitize_text_field( $params['name'] ?? '' );
        $url  = esc_url_raw( $params['invite_url'] ?? '' );

        if ( ! $name || ! $url ) {
            return $this->error( 'missing_fields', __( 'Name and invite URL are required.', 'community-directory' ) );
        }

        $wpdb->insert( $table, array(
            'name'           => $name,
            'description'    => sanitize_textarea_field( $params['description'] ?? '' ),
            'invite_url'     => $url,
            'icon'           => sanitize_text_field( $params['icon'] ?? '' ),
            'visibility'     => in_array( $params['visibility'] ?? 'all', array( 'all', 'tag' ), true ) ? $params['visibility'] : 'all',
            'visibility_tag' => sanitize_text_field( $params['visibility_tag'] ?? '' ),
            'display_order'  => (int) ( $params['display_order'] ?? 0 ),
            'is_active'      => 1,
            'created_by'     => get_current_user_id(),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' ) );

        if ( ! $wpdb->insert_id ) {
            return $this->error( 'db_error', __( 'Could not create group.', 'community-directory' ), 500 );
        }

        return $this->success( array(
            'id'      => $wpdb->insert_id,
            'message' => __( 'WhatsApp group created.', 'community-directory' ),
        ) );
    }

    /**
     * Update a WhatsApp group.
     */
    public function update_whatsapp_group( WP_REST_Request $request ) {
        global $wpdb;
        $table = CD_Database::table( 'whatsapp_groups' );
        $id    = (int) $request->get_param( 'id' );

        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) );
        if ( ! $existing ) {
            return $this->error( 'not_found', __( 'Group not found.', 'community-directory' ), 404 );
        }

        $params = $request->get_json_params();
        $data   = array();
        $format = array();

        if ( isset( $params['name'] ) ) {
            $data['name'] = sanitize_text_field( $params['name'] );
            $format[] = '%s';
        }
        if ( isset( $params['description'] ) ) {
            $data['description'] = sanitize_textarea_field( $params['description'] );
            $format[] = '%s';
        }
        if ( isset( $params['invite_url'] ) ) {
            $data['invite_url'] = esc_url_raw( $params['invite_url'] );
            $format[] = '%s';
        }
        if ( isset( $params['icon'] ) ) {
            $data['icon'] = sanitize_text_field( $params['icon'] );
            $format[] = '%s';
        }
        if ( isset( $params['visibility'] ) ) {
            $data['visibility'] = in_array( $params['visibility'], array( 'all', 'tag' ), true ) ? $params['visibility'] : 'all';
            $format[] = '%s';
        }
        if ( isset( $params['visibility_tag'] ) ) {
            $data['visibility_tag'] = sanitize_text_field( $params['visibility_tag'] );
            $format[] = '%s';
        }
        if ( isset( $params['display_order'] ) ) {
            $data['display_order'] = (int) $params['display_order'];
            $format[] = '%d';
        }
        if ( isset( $params['is_active'] ) ) {
            $data['is_active'] = $params['is_active'] ? 1 : 0;
            $format[] = '%d';
        }

        if ( empty( $data ) ) {
            return $this->success( array( 'message' => __( 'No changes.', 'community-directory' ) ) );
        }

        $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );

        return $this->success( array( 'message' => __( 'WhatsApp group updated.', 'community-directory' ) ) );
    }

    /**
     * Delete a WhatsApp group.
     */
    public function delete_whatsapp_group( WP_REST_Request $request ) {
        global $wpdb;
        $table = CD_Database::table( 'whatsapp_groups' );
        $id    = (int) $request->get_param( 'id' );

        $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        if ( ! $deleted ) {
            return $this->error( 'not_found', __( 'Group not found.', 'community-directory' ), 404 );
        }

        return $this->success( array( 'message' => __( 'WhatsApp group deleted.', 'community-directory' ) ) );
    }


    /**
     * NUCLEAR OPTION: Wipe functionality and re-initialize.
     */
    public function reset_database( WP_REST_Request $request ) {
        // Double check permission
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->error( 'forbidden', 'You are not authorized to perform this action.', 403 );
        }

        // Decode JSON body to check for confirmation
        $params = $request->get_json_params();
        if ( empty( $params['confirm'] ) || 'I UNDERSTAND' !== $params['confirm'] ) {
             return $this->error( 'confirmation_required', 'You must provide { "confirm": "I UNDERSTAND" } to execute this destructive action.', 400 );
        }

        try {
            // 1. Nuke everything
            CD_Database::nuke_everything();

            // 2. Re-activate (re-create tables)
            require_once CD_PLUGIN_DIR . 'includes/class-activator.php';
            CD_Activator::activate();

            CD_Audit_Logger::log( CD_Audit_Logger::SETTINGS_UPDATED, get_current_user_id(), null, array( 'action' => 'database_reset' ) );

            return $this->success( array(
                'message' => __( 'Database successfully reset. All tables dropped and re-created.', 'community-directory' )
            ) );

        } catch ( Exception $e ) {
            return $this->error( 'reset_failed', $e->getMessage(), 500 );
        }
    }

    /* ──────────────────────────────────────────────
     * DATA EXPORT — CSV
     * ────────────────────────────────────────────── */

    /**
     * Export members as CSV download.
     * GET /admin/members/export?status=active&search=
     */
    public function export_members_csv( WP_REST_Request $request ) {
        global $wpdb;

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $hm_table       = CD_Database::table( 'household_members' );
        $hh_table       = CD_Database::table( 'households' );

        $status = sanitize_text_field( $request->get_param( 'status' ) ?: 'active' );
        $search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );

        $where = '1=1';
        $args  = array();

        if ( $status && 'all' !== $status ) {
            $where .= ' AND m.status = %s';
            $args[] = $status;
        }
        if ( $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= ' AND (p.first_name LIKE %s OR p.last_name LIKE %s OR p.emails LIKE %s)';
            $args[]  = $like;
            $args[]  = $like;
            $args[]  = $like;
        }

        $query = "SELECT m.id, m.status, m.member_since,
                         p.first_name, p.last_name, p.emails, p.phones,
                         p.address_home, p.city, p.state, p.zip_code,
                         p.date_of_birth, p.baptism_date, p.occupation, p.employer,
                         h.name AS household_name, hm.role AS household_role
                  FROM {$members_table} m
                  LEFT JOIN {$profiles_table} p ON m.id = p.member_id
                  LEFT JOIN {$hm_table} hm ON m.id = hm.member_id AND hm.left_at IS NULL
                  LEFT JOIN {$hh_table} h ON hm.household_id = h.id
                  WHERE {$where}
                  ORDER BY p.last_name ASC, p.first_name ASC";

        if ( ! empty( $args ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare( $query, $args ) );
        } else {
            $rows = $wpdb->get_results( $query );
        }

        // Audit log
        CD_Audit_Logger::log( CD_Audit_Logger::SETTINGS_UPDATED, get_current_user_id(), null, array(
            'action' => 'csv_export',
            'status' => $status,
            'count'  => count( $rows ),
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
        ) );

        // Build CSV in memory
        $output = fopen( 'php://temp', 'r+' );
        fputcsv( $output, array(
            'First Name', 'Last Name', 'Email', 'Phone',
            'Address', 'City', 'State', 'Zip',
            'Date of Birth', 'Baptism Date', 'Occupation', 'Employer',
            'Member Since', 'Status', 'Household', 'Household Role',
        ) );

        foreach ( $rows as $row ) {
            $emails = json_decode( $row->emails ?? '', true ) ?: array();
            $phones = json_decode( $row->phones ?? '', true ) ?: array();
            $email  = ! empty( $emails[0]['value'] ) ? $emails[0]['value'] : '';
            $phone  = ! empty( $phones[0]['value'] ) ? $phones[0]['value'] : '';

            $address = '';
            if ( ! empty( $row->address_home ) ) {
                $dec = CD_Encryption::decrypt( $row->address_home );
                if ( $dec !== false ) {
                    $address = $dec;
                }
            }
            $dob = '';
            if ( ! empty( $row->date_of_birth ) ) {
                $dec = CD_Encryption::decrypt( $row->date_of_birth );
                if ( $dec !== false ) {
                    $dob = $dec;
                }
            }

            fputcsv( $output, array(
                $row->first_name ?? '',
                $row->last_name ?? '',
                $email,
                $phone,
                $address,
                $row->city ?? '',
                $row->state ?? '',
                $row->zip_code ?? '',
                $dob,
                $row->baptism_date ?? '',
                $row->occupation ?? '',
                $row->employer ?? '',
                $row->member_since ?? '',
                $row->status ?? '',
                $row->household_name ?? '',
                $row->household_role ?? '',
            ) );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        $filename = 'community-directory-export-' . gmdate( 'Y-m-d' ) . '.csv';

        return new WP_REST_Response( $csv, 200, array(
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ) );
    }

    /* ──────────────────────────────────────────────
     * PROFILE MERGE — Duplicate Finder + Merge
     * ────────────────────────────────────────────── */

    /**
     * Find potential duplicate members by name or email.
     * GET /admin/members/duplicates
     */
    public function find_duplicate_members( WP_REST_Request $request ) {
        global $wpdb;

        $profiles_table = CD_Database::table( 'directory_profiles' );
        $members_table  = CD_Database::table( 'members' );

        // Find members sharing the same first+last name
        $name_dupes = $wpdb->get_results(
            "SELECT p.member_id, p.first_name, p.last_name, p.emails, m.status, m.created_at
             FROM {$profiles_table} p
             JOIN {$members_table} m ON p.member_id = m.id
             WHERE m.status IN ('active','inactive')
             AND (p.first_name, p.last_name) IN (
                 SELECT p2.first_name, p2.last_name
                 FROM {$profiles_table} p2
                 JOIN {$members_table} m2 ON p2.member_id = m2.id
                 WHERE m2.status IN ('active','inactive')
                 GROUP BY p2.first_name, p2.last_name
                 HAVING COUNT(*) > 1
             )
             ORDER BY p.last_name, p.first_name, m.created_at"
        );

        // Group into clusters
        $clusters = array();
        foreach ( $name_dupes as $row ) {
            $key = strtolower( trim( $row->first_name ) . '|' . trim( $row->last_name ) );
            $emails = json_decode( $row->emails ?? '', true ) ?: array();
            $email  = ! empty( $emails[0]['value'] ) ? $emails[0]['value'] : '';

            if ( ! isset( $clusters[ $key ] ) ) {
                $clusters[ $key ] = array(
                    'reason'  => 'Same name: ' . $row->first_name . ' ' . $row->last_name,
                    'members' => array(),
                );
            }
            $clusters[ $key ]['members'][] = array(
                'id'         => (int) $row->member_id,
                'first_name' => $row->first_name,
                'last_name'  => $row->last_name,
                'email'      => $email,
                'status'     => $row->status,
                'created_at' => $row->created_at,
            );
        }

        // Check if any cluster has email matches (upgrade confidence)
        foreach ( $clusters as $key => &$cluster ) {
            $emails_seen = array();
            $has_email_match = false;
            foreach ( $cluster['members'] as $m ) {
                if ( ! empty( $m['email'] ) ) {
                    $e = strtolower( $m['email'] );
                    if ( isset( $emails_seen[ $e ] ) ) {
                        $has_email_match = true;
                    }
                    $emails_seen[ $e ] = true;
                }
            }
            $cluster['confidence'] = $has_email_match ? 'high' : 'medium';
            if ( $has_email_match ) {
                $cluster['reason'] = 'Same email + name';
            }
        }
        unset( $cluster );

        return $this->success( array(
            'duplicates' => array_values( $clusters ),
            'total'      => count( $clusters ),
        ) );
    }

    /**
     * Merge two member profiles.
     * POST /admin/members/merge { primary_id, secondary_id }
     */
    public function merge_members( WP_REST_Request $request ) {
        global $wpdb;

        $primary_id   = (int) $request->get_param( 'primary_id' );
        $secondary_id = (int) $request->get_param( 'secondary_id' );

        if ( ! $primary_id || ! $secondary_id || $primary_id === $secondary_id ) {
            return $this->error( 'invalid_params', __( 'Two different member IDs are required.', 'community-directory' ), 400 );
        }

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $hm_table       = CD_Database::table( 'household_members' );
        $invites_table  = CD_Database::table( 'invites' );

        $primary   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$members_table} WHERE id = %d", $primary_id ) );
        $secondary = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$members_table} WHERE id = %d", $secondary_id ) );

        if ( ! $primary || ! $secondary ) {
            return $this->error( 'not_found', __( 'One or both members not found.', 'community-directory' ), 404 );
        }

        $p_profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$profiles_table} WHERE member_id = %d", $primary_id ) );
        $s_profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$profiles_table} WHERE member_id = %d", $secondary_id ) );

        $wpdb->query( 'START TRANSACTION' );

        try {
            // Fill empty fields in primary with secondary's data
            if ( $p_profile && $s_profile ) {
                $fill_fields = array(
                    'bio', 'occupation', 'employer', 'address_home', 'address_mailing',
                    'city', 'state', 'zip_code', 'date_of_birth', 'baptism_date',
                    'wedding_anniversary', 'name_day', 'avatar_url',
                );
                $updates = array();
                $formats = array();
                foreach ( $fill_fields as $field ) {
                    if ( empty( $p_profile->$field ) && ! empty( $s_profile->$field ) ) {
                        $updates[ $field ] = $s_profile->$field;
                        $formats[] = '%s';
                    }
                }
                // Merge JSON array fields
                foreach ( array( 'emails', 'phones', 'social_links', 'ministry_tags' ) as $jf ) {
                    $p_arr = json_decode( $p_profile->$jf ?? '', true ) ?: array();
                    $s_arr = json_decode( $s_profile->$jf ?? '', true ) ?: array();
                    if ( ! empty( $s_arr ) ) {
                        $merged = array_merge( $p_arr, $s_arr );
                        // Deduplicate by value for emails/phones
                        if ( in_array( $jf, array( 'emails', 'phones' ), true ) ) {
                            $seen = array();
                            $unique = array();
                            foreach ( $merged as $item ) {
                                $val = strtolower( $item['value'] ?? '' );
                                if ( $val && ! isset( $seen[ $val ] ) ) {
                                    $seen[ $val ] = true;
                                    $unique[] = $item;
                                }
                            }
                            $merged = $unique;
                        }
                        $updates[ $jf ] = wp_json_encode( $merged );
                        $formats[] = '%s';
                    }
                }
                if ( ! empty( $updates ) ) {
                    $wpdb->update( $profiles_table, $updates, array( 'member_id' => $primary_id ), $formats, array( '%d' ) );
                }
            }

            // Transfer WP user ID if primary has none
            if ( empty( $primary->wp_user_id ) && ! empty( $secondary->wp_user_id ) ) {
                $wpdb->update( $members_table, array( 'wp_user_id' => $secondary->wp_user_id ), array( 'id' => $primary_id ), array( '%d' ), array( '%d' ) );
            }

            // Transfer household memberships
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$hm_table} SET member_id = %d WHERE member_id = %d AND left_at IS NULL",
                $primary_id, $secondary_id
            ) );

            // Delete secondary's Google contact
            if ( class_exists( 'CD_Google_Contacts' ) && CD_Google_Contacts::is_enabled() ) {
                $sec_gid = $secondary->google_contact_id ?? '';
                if ( ! empty( $sec_gid ) ) {
                    CD_Google_Contacts::delete_contact( $sec_gid );
                }
                // If primary lacks a google_contact_id, take secondary's
                if ( empty( $primary->google_contact_id ) && ! empty( $sec_gid ) ) {
                    $wpdb->update( $members_table, array( 'google_contact_id' => $sec_gid ), array( 'id' => $primary_id ), array( '%s' ), array( '%d' ) );
                }
            }

            // Delete secondary records
            $wpdb->delete( $profiles_table, array( 'member_id' => $secondary_id ), array( '%d' ) );
            $wpdb->delete( $hm_table, array( 'member_id' => $secondary_id ), array( '%d' ) );
            $wpdb->delete( $invites_table, array( 'member_id' => $secondary_id ), array( '%d' ) );
            $wpdb->delete( $members_table, array( 'id' => $secondary_id ), array( '%d' ) );

            $wpdb->query( 'COMMIT' );

            CD_Audit_Logger::log( CD_Audit_Logger::BULK_OPERATION, get_current_user_id(), $primary_id, array(
                'action'       => 'member_merge',
                'primary_id'   => $primary_id,
                'secondary_id' => $secondary_id,
            ) );

            // Sync merged profile to Google
            if ( class_exists( 'CD_Google_Contacts' ) ) {
                CD_Google_Contacts::sync_member( $primary_id, 'update' );
            }

            return $this->success( array(
                'message'    => __( 'Members merged successfully.', 'community-directory' ),
                'primary_id' => $primary_id,
            ) );

        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return $this->error( 'merge_failed', $e->getMessage(), 500 );
        }
    }

    /* ──────────────────────────────────────────────
     * DASHBOARD STATS
     * ────────────────────────────────────────────── */

    /**
     * Get dashboard statistics.
     * GET /admin/stats
     */
    public function get_dashboard_stats( WP_REST_Request $request ) {
        global $wpdb;

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $hh_table       = CD_Database::table( 'households' );
        $hm_table       = CD_Database::table( 'household_members' );
        $apps_table     = CD_Database::table( 'applications' );
        $audit_table    = CD_Database::table( 'audit_log' );
        $del_table      = CD_Database::table( 'deletion_requests' );

        // Each query wrapped with null-safe defaults
        $status_counts = array();
        $status_rows = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$members_table} GROUP BY status" );
        if ( is_array( $status_rows ) ) {
            foreach ( $status_rows as $sr ) {
                $status_counts[ $sr->status ] = (int) $sr->cnt;
            }
        }

        $pending_applications  = (int) ( $wpdb->get_var( "SELECT COUNT(*) FROM {$apps_table} WHERE status = 'new'" ) ?? 0 );
        $awaiting_verification = (int) ( $wpdb->get_var( "SELECT COUNT(*) FROM {$apps_table} WHERE status = 'pending_verification'" ) ?? 0 );

        $pending_deletions = 0;
        $del_result = $wpdb->get_var( "SELECT COUNT(*) FROM {$del_table} WHERE status = 'pending'" );
        if ( null !== $del_result && false !== $del_result ) {
            $pending_deletions = (int) $del_result;
        }

        $incomplete_profiles = (int) ( $wpdb->get_var( "SELECT COUNT(*) FROM {$profiles_table} WHERE profile_completion < 80" ) ?? 0 );

        $members_by_month = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
             FROM {$members_table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY month ORDER BY month"
        );
        if ( ! is_array( $members_by_month ) ) {
            $members_by_month = array();
        }

        $hh_total = (int) ( $wpdb->get_var( "SELECT COUNT(*) FROM {$hh_table} WHERE status = 'active'" ) ?? 0 );
        $hh_avg   = 0;
        $avg_result = $wpdb->get_var(
            "SELECT AVG(cnt) FROM (
                SELECT COUNT(*) AS cnt FROM {$hm_table}
                WHERE left_at IS NULL GROUP BY household_id
            ) sub"
        );
        if ( null !== $avg_result ) {
            $hh_avg = (float) $avg_result;
        }

        $synced = 0;
        $synced_result = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$members_table} WHERE google_contact_id IS NOT NULL AND google_contact_id != ''"
        );
        if ( null !== $synced_result && false !== $synced_result ) {
            $synced = (int) $synced_result;
        }
        $retry_queue = get_option( 'cd_google_retry_queue', array() );

        $recent = array();
        $recent_rows = $wpdb->get_results(
            "SELECT event_type AS action, actor_user_id, target_id, details, created_at
             FROM {$audit_table} ORDER BY created_at DESC LIMIT 20"
        );
        if ( is_array( $recent_rows ) ) {
            foreach ( $recent_rows as &$r ) {
                $decoded = json_decode( $r->details ?? '', true );
                $r->details = is_array( $decoded ) ? ( $decoded['summary'] ?? $decoded['note'] ?? '' ) : '';
            }
            unset( $r );
            $recent = $recent_rows;
        }

        $apps_month = (int) ( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$apps_table} WHERE submitted_at >= %s",
            gmdate( 'Y-m-01 00:00:00' )
        ) ) ?? 0 );

        return $this->success( array(
            'status_counts'    => array_merge( $status_counts, array(
                'awaiting_verification' => $awaiting_verification,
                'deletion_requests'     => $pending_deletions,
                'incomplete_profiles'   => $incomplete_profiles,
            ) ),
            'pending_applications' => $pending_applications,
            'members_by_month'     => $members_by_month,
            'household_stats'      => array(
                'total'    => $hh_total,
                'avg_size' => round( $hh_avg, 1 ),
            ),
            'google_sync' => array(
                'synced'         => $synced,
                'pending_retries' => is_array( $retry_queue ) ? count( $retry_queue ) : 0,
            ),
            'recent_activity'    => $recent,
            'applications_month' => $apps_month,
        ) );
    }
}
