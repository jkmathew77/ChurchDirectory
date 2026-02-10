<?php
/**
 * REST API controller for member profiles and directory.
 * Handles public (member-only) directory search and profile viewing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_API_Members extends CD_API_Base {

    public function register_routes() {
        // GET /directory — search directory
        register_rest_route( CD_API_NAMESPACE, '/directory', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'search_directory' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // GET /members/{uuid} — get member profile
        register_rest_route( CD_API_NAMESPACE, '/members/(?P<uuid>[a-f0-9-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_member' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // PUT /members/me — update OWN profile
        register_rest_route( CD_API_NAMESPACE, '/members/me', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'update_profile' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // POST /members/avatar — upload avatar
        register_rest_route( CD_API_NAMESPACE, '/members/avatar', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'upload_avatar' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // DELETE /members/avatar — remove avatar
        register_rest_route( CD_API_NAMESPACE, '/members/avatar', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_avatar' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );
    }

    /**
     * Search/List Directory Members.
     * Returns: UUID, Name, Avatar, Primary Email, Primary Phone, City, State, Ministry Tags.
     */
    public function search_directory( WP_REST_Request $request ) {
        global $wpdb;

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );
        
        $search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
        $page   = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per    = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ?: 24 ) ); // 24 for grid layout (3x8 or 4x6)
        $offset = ( $page - 1 ) * $per;

        // Base Where: Active Members Only
        $where = "m.status = 'active'";
        $args  = array();

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
        // We select specific fields for the directory card
        $query = "SELECT m.uuid, p.first_name, p.last_name, p.avatar_url, 
                         p.emails, p.phones, p.city, p.state, p.ministry_tags
                  FROM {$members_table} m
                  LEFT JOIN {$profiles_table} p ON m.id = p.member_id
                  WHERE {$where}
                  ORDER BY p.last_name ASC, p.first_name ASC
                  LIMIT %d OFFSET %d";
        
        $args[] = $per;
        $args[] = $offset;

        $rows = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        // Process rows for display
        $results = array();
        foreach ( $rows as $row ) {
            // Decode JSON
            $emails = json_decode( $row->emails, true );
            $phones = json_decode( $row->phones, true );
            $ministry_tags = json_decode( $row->ministry_tags, true );

            // Primary Contact
            $primary_email = '';
            if ( ! empty( $emails ) && is_array( $emails ) ) {
                $primary_email = $emails[0]['value'] ?? '';
            }

            $primary_phone = '';
            if ( ! empty( $phones ) && is_array( $phones ) ) {
                $primary_phone = $phones[0]['value'] ?? '';
            }

            $results[] = array(
                'uuid'         => $row->uuid,
                'first_name'   => $row->first_name,
                'last_name'    => $row->last_name,
                'avatar_url'   => $row->avatar_url,
                'email'        => $primary_email,
                'phone'        => $primary_phone, // TODO: Apply privacy filter here later?
                'city'         => $row->city,
                'state'        => $row->state,
                'ministry_tags' => $ministry_tags ?: array(),
            );
        }

        return $this->success( array(
            'members' => $results,
        ), array(
            'page'     => $page,
            'per_page' => $per,
            'total'    => $total,
            'pages'    => ceil( $total / $per ),
        ) );
    }

    /**
     * Get single member profile by UUID.
     */
    public function get_member( WP_REST_Request $request ) {
        global $wpdb;
        $uuid = sanitize_text_field( $request->get_param( 'uuid' ) );

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.uuid, p.*
             FROM {$members_table} m
             LEFT JOIN {$profiles_table} p ON m.id = p.member_id
             WHERE m.uuid = %s AND m.status = 'active'",
            $uuid
        ) );

        if ( ! $row ) {
            return $this->error( 'not_found', __( 'Member not found.', 'community-directory' ), 404 );
        }

        // Decode all JSON fields
        $row->emails        = json_decode( $row->emails, true );
        $row->phones        = json_decode( $row->phones, true );
        $row->social_links  = json_decode( $row->social_links, true );
        $row->ministry_tags = json_decode( $row->ministry_tags, true );
        $row->privacy_settings = json_decode( $row->privacy_settings, true );
        
        // Decrypt Encrypted Fields
        if ( ! empty( $row->address_home ) ) {
            $row->address_home = CD_Encryption::decrypt( $row->address_home );
        }
        if ( ! empty( $row->address_mailing ) ) {
             $row->address_mailing = CD_Encryption::decrypt( $row->address_mailing );
        }
        if ( ! empty( $row->date_of_birth ) ) {
             $row->date_of_birth = CD_Encryption::decrypt( $row->date_of_birth );
        }
        if ( ! empty( $row->emergency_contact_name ) ) {
             $row->emergency_contact_name = CD_Encryption::decrypt( $row->emergency_contact_name );
        }
        if ( ! empty( $row->emergency_contact_phone ) ) {
             $row->emergency_contact_phone = CD_Encryption::decrypt( $row->emergency_contact_phone );
        }
        
        // Remove sensitive system fields (id, member_id, etc from p.*)
        unset( $row->id );
        unset( $row->member_id );
        
        // TODO: Filter based on privacy settings
        // For now, return full profile (Internal Directory)
        
        return $this->success( array( 'member' => $row ) );
    }

    /**
     * Update own profile.
     */
    public function update_profile( WP_REST_Request $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $member_id = CD_Members::get_member_id_by_user_id( $user_id );

        if ( ! $member_id ) {
            return $this->error( 'not_found', __( 'Member record not found.', 'community-directory' ), 404 );
        }

        $profiles_table = CD_Database::table( 'directory_profiles' );
        $params = $request->get_json_params();

        // White-listed fields to update
        $data = array();
        $format = array();

        // Text fields
        if ( isset( $params['first_name'] ) ) {
            $data['first_name'] = sanitize_text_field( $params['first_name'] );
            $format[] = '%s';
        }
        if ( isset( $params['last_name'] ) ) {
            $data['last_name'] = sanitize_text_field( $params['last_name'] );
            $format[] = '%s';
        }
        if ( isset( $params['bio'] ) ) {
            $data['bio'] = sanitize_textarea_field( $params['bio'] );
            $format[] = '%s';
        }
        if ( isset( $params['occupation'] ) ) {
            $data['occupation'] = sanitize_text_field( $params['occupation'] );
            $format[] = '%s';
        }
        if ( isset( $params['employer'] ) ) {
            $data['employer'] = sanitize_text_field( $params['employer'] );
            $format[] = '%s';
        }
        if ( isset( $params['address_home'] ) ) {
            $data['address_home'] = CD_Encryption::encrypt( sanitize_textarea_field( $params['address_home'] ) );
            $format[] = '%s';
        }
        if ( isset( $params['address_mailing'] ) ) {
            $data['address_mailing'] = CD_Encryption::encrypt( sanitize_textarea_field( $params['address_mailing'] ) );
            $format[] = '%s';
        }
        if ( isset( $params['city'] ) ) {
             $data['city'] = sanitize_text_field( $params['city'] );
             $format[] = '%s';
        }
        if ( isset( $params['state'] ) ) {
             $data['state'] = sanitize_text_field( $params['state'] );
             $format[] = '%s';
        }
        if ( isset( $params['zip'] ) ) {
             $data['zip'] = sanitize_text_field( $params['zip'] );
             $format[] = '%s';
        }
        if ( isset( $params['date_of_birth'] ) ) {
            $data['date_of_birth'] = CD_Encryption::encrypt( sanitize_text_field( $params['date_of_birth'] ) );
            $format[] = '%s';
        }
        if ( isset( $params['baptism_date'] ) ) {
            $data['baptism_date'] = sanitize_text_field( $params['baptism_date'] );
            $format[] = '%s';
        }
        if ( isset( $params['wedding_anniversary'] ) ) {
            $data['wedding_anniversary'] = sanitize_text_field( $params['wedding_anniversary'] );
            $format[] = '%s';
        }
        if ( isset( $params['name_day'] ) ) {
            $data['name_day'] = sanitize_text_field( $params['name_day'] );
            $format[] = '%s';
        }
        if ( isset( $params['emergency_contact_name'] ) ) {
            $data['emergency_contact_name'] = CD_Encryption::encrypt( sanitize_text_field( $params['emergency_contact_name'] ) );
            $format[] = '%s';
        }
        if ( isset( $params['emergency_contact_phone'] ) ) {
            $data['emergency_contact_phone'] = CD_Encryption::encrypt( sanitize_text_field( $params['emergency_contact_phone'] ) );
            $format[] = '%s';
        }
        if ( isset( $params['preferred_contact_method'] ) ) {
            $data['preferred_contact_method'] = sanitize_text_field( $params['preferred_contact_method'] );
            $format[] = '%s';
        }
         if ( isset( $params['preferred_language'] ) ) {
            $data['preferred_language'] = sanitize_text_field( $params['preferred_language'] );
            $format[] = '%s';
        }

        // JSON fields - explicit sanitization
        if ( isset( $params['emails'] ) && is_array( $params['emails'] ) ) {
            $clean_emails = array();
            foreach ( $params['emails'] as $email ) {
                if ( ! empty( $email['value'] ) && is_email( $email['value'] ) ) {
                    $clean_emails[] = array(
                        'type'  => sanitize_text_field( $email['type'] ?? 'personal' ),
                        'value' => sanitize_email( $email['value'] ),
                    );
                }
            }
            $data['emails'] = wp_json_encode( $clean_emails );
            $format[] = '%s';
        }

        if ( isset( $params['phones'] ) && is_array( $params['phones'] ) ) {
            $clean_phones = array();
            foreach ( $params['phones'] as $phone ) {
                if ( ! empty( $phone['value'] ) ) {
                    $clean_phones[] = array(
                        'type'  => sanitize_text_field( $phone['type'] ?? 'mobile' ),
                        'value' => sanitize_text_field( $phone['value'] ),
                    );
                }
            }
            $data['phones'] = wp_json_encode( $clean_phones );
            $format[] = '%s';
        }
        
        if ( isset( $params['ministry_tags'] ) && is_array( $params['ministry_tags'] ) ) {
             $clean_tags = array_map( 'sanitize_text_field', $params['ministry_tags'] );
             $data['ministry_tags'] = wp_json_encode( $clean_tags );
             $format[] = '%s';
        }

        if ( isset( $params['social_links'] ) && is_array( $params['social_links'] ) ) {
             $clean_social = array();
             foreach ( $params['social_links'] as $link ) {
                 if ( ! empty( $link['url'] ) ) {
                     $clean_social[] = array(
                         'platform' => sanitize_text_field( $link['platform'] ?? 'website' ),
                         'url'      => esc_url_raw( $link['url'] ),
                     );
                 }
             }
             $data['social_links'] = wp_json_encode( $clean_social );
             $format[] = '%s';
        }

        if ( empty( $data ) ) {
            return $this->success( array( 'message' => __( 'No changes to save.', 'community-directory' ) ) );
        }

        $updated = $wpdb->update(
            $profiles_table,
            $data,
            array( 'member_id' => $member_id ),
            $format,
            array( '%d' )
        );

        if ( $updated === false ) {
            error_log( 'CD Update Profile Error: ' . $wpdb->last_error );
            return $this->error( 'db_error', __( 'Could not save profile.', 'community-directory' ), 500 );
        }

        return $this->success( array( 'message' => __( 'Profile updated successfully.', 'community-directory' ) ) );
    }

    /**
     * Upload Avatar.
     * POST /members/avatar
     */
    public function upload_avatar( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $member_id = CD_Members::get_member_id_by_user_id( $user_id );

        if ( ! $member_id ) {
            return $this->error( 'not_found', __( 'Member record not found.', 'community-directory' ), 404 );
        }

        $files = $request->get_file_params();
        if ( empty( $files['file'] ) ) {
            return $this->error( 'no_file', __( 'No file uploaded.', 'community-directory' ), 400 );
        }

        $file = $files['file'];
        
        // Basic validation
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
        if ( ! in_array( $file['type'], $allowed_types ) ) {
             return $this->error( 'invalid_type', __( 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP.', 'community-directory' ), 400 );
        }

        if ( $file['size'] > 5 * 1024 * 1024 ) { // 5MB
             return $this->error( 'file_too_large', __( 'File missing or too large (Max 5MB).', 'community-directory' ), 400 );
        }

        // Use WordPress media handler
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $attachment_id = media_handle_sideload( $file, 0 );

        if ( is_wp_error( $attachment_id ) ) {
            error_log( 'CD Upload Error: ' . $attachment_id->get_error_message() );
            return $this->error( 'upload_failed', $attachment_id->get_error_message(), 500 );
        }

        $url = wp_get_attachment_url( $attachment_id );

        // Update profile
        global $wpdb;
        $profiles_table = CD_Database::table( 'directory_profiles' );
        
        );

        return $this->success( array( 
            'url' => $url,
            'message' => __( 'Avatar updated.', 'community-directory' )
        ) );
    }

    /**
     * Delete Avatar.
     * DELETE /members/avatar
     */
    public function delete_avatar( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $member_id = CD_Members::get_member_id_by_user_id( $user_id );

        if ( ! $member_id ) {
            return $this->error( 'not_found', __( 'Member record not found.', 'community-directory' ), 404 );
        }

        global $wpdb;
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $updated = $wpdb->update(
            $profiles_table,
            array( 'avatar_url' => '', 'avatar_source' => 'gravatar' ),
            array( 'member_id' => $member_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return $this->error( 'db_error', __( 'Could not remove avatar.', 'community-directory' ), 500 );
        }

        return $this->success( array( 'message' => __( 'Avatar removed.', 'community-directory' ) ) );
    }
}
