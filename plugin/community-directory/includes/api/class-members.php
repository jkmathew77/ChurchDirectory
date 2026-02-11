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

        // POST /members/me/deletion-request — request account deletion
        register_rest_route( CD_API_NAMESPACE, '/members/me/deletion-request', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'request_deletion' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );

        // GET /whatsapp-groups — list active WhatsApp groups for members
        register_rest_route( CD_API_NAMESPACE, '/whatsapp-groups', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_whatsapp_groups' ),
            'permission_callback' => array( $this, 'permission_member' ),
        ) );
    }

    /**
     * Search/List Directory Members.
     * Returns: UUID, Name, Avatar, Primary Email, Primary Phone, City, State, Ministry Tags.
     */
    public function search_directory( WP_REST_Request $request ) {
        // Rate limit: 30 searches per minute per user (PRD Section 10.3)
        $current_uid = get_current_user_id();
        if ( $this->is_rate_limited( 'dir_search_' . $current_uid, 30, 60 ) ) {
            return $this->error( 'rate_limited', __( 'Too many searches. Please wait a moment and try again.', 'community-directory' ), 429 );
        }

        // Bot detection (PRD Section 10.2.4)
        if ( $this->detect_bot( 'directory_search' ) ) {
            return $this->error( 'blocked', __( 'Request blocked. Please try again later.', 'community-directory' ), 403 );
        }

        global $wpdb;

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
        $page   = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per    = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ?: 24 ) );
        $offset = ( $page - 1 ) * $per;

        $where = "m.status = 'active'";
        $args  = array();

        if ( $search ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (p.first_name LIKE %s OR p.last_name LIKE %s OR p.emails LIKE %s OR p.phones LIKE %s)';
            $args[] = $search_like;
            $args[] = $search_like;
            $args[] = $search_like;
            $args[] = $search_like;
        }

        // Advanced filters
        $filter_city = sanitize_text_field( $request->get_param( 'city' ) ?: '' );
        if ( $filter_city ) {
            $where .= ' AND p.city LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $filter_city ) . '%';
        }
        $filter_state = sanitize_text_field( $request->get_param( 'state' ) ?: '' );
        if ( $filter_state ) {
            $where .= ' AND p.state LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $filter_state ) . '%';
        }
        $filter_occupation = sanitize_text_field( $request->get_param( 'occupation' ) ?: '' );
        if ( $filter_occupation ) {
            $where .= ' AND p.occupation LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $filter_occupation ) . '%';
        }
        $filter_employer = sanitize_text_field( $request->get_param( 'employer' ) ?: '' );
        if ( $filter_employer ) {
            $where .= ' AND p.employer LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $filter_employer ) . '%';
        }

        $total_query = "SELECT COUNT(*) FROM {$members_table} m LEFT JOIN {$profiles_table} p ON m.id = p.member_id WHERE {$where}";
        if ( ! empty( $args ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $total_query, $args ) );
        } else {
            $total = (int) $wpdb->get_var( $total_query );
        }

        $query = "SELECT m.id AS member_id, m.uuid, p.salutation, p.first_name, p.last_name, p.avatar_url,
                         p.emails, p.phones, p.city, p.state, p.occupation, p.employer, p.ministry_tags, p.privacy_settings
                  FROM {$members_table} m
                  LEFT JOIN {$profiles_table} p ON m.id = p.member_id
                  WHERE {$where}
                  ORDER BY p.last_name ASC, p.first_name ASC
                  LIMIT %d OFFSET %d";

        $args[] = $per;
        $args[] = $offset;

        $rows = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        $results = array();
        foreach ( $rows as $row ) {
            $emails = json_decode( $row->emails ?? '', true );
            $phones = json_decode( $row->phones ?? '', true );
            $ministry_tags = json_decode( $row->ministry_tags ?? '', true );
            $privacy = json_decode( $row->privacy_settings ?? '', true );
            if ( ! is_array( $privacy ) ) {
                $privacy = array();
            }

            // Apply privacy: default to visible if not set
            $primary_email = '';
            if ( ( $privacy['email'] ?? 'visible' ) === 'visible' && ! empty( $emails ) && is_array( $emails ) ) {
                // Base64-encode for anti-scraping (PRD Section 10.2.4)
                $raw_email = $emails[0]['value'] ?? '';
                $primary_email = $raw_email ? base64_encode( $raw_email ) : '';
            }

            $primary_phone = '';
            if ( ( $privacy['phone'] ?? 'visible' ) === 'visible' && ! empty( $phones ) && is_array( $phones ) ) {
                $primary_phone = $phones[0]['value'] ?? '';
            }

            $show_address = ( $privacy['address'] ?? 'visible' ) === 'visible';

            $results[] = array(
                'member_id'     => (int) $row->member_id,
                'uuid'          => $row->uuid,
                'salutation'    => $row->salutation ?: '',
                'first_name'    => $row->first_name,
                'last_name'     => $row->last_name,
                'avatar_url'    => $row->avatar_url,
                'email'         => $primary_email,
                'phone'         => $primary_phone,
                'city'          => $show_address ? $row->city : '',
                'state'         => $show_address ? $row->state : '',
                'occupation'    => $row->occupation ?: '',
                'employer'      => $row->employer ?: '',
                'ministry_tags' => $ministry_tags ?: array(),
            );
        }

        return $this->success( array(
            'members'          => $results,
            'email_obfuscated' => true,
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
        // Rate limit: 60 profile views per minute per user (PRD Section 10.3)
        $current_uid = get_current_user_id();
        if ( $this->is_rate_limited( 'profile_view_' . $current_uid, 60, 60 ) ) {
            return $this->error( 'rate_limited', __( 'Too many profile views. Please wait a moment and try again.', 'community-directory' ), 429 );
        }

        // Bot detection (PRD Section 10.2.4)
        if ( $this->detect_bot( 'profile_view' ) ) {
            return $this->error( 'blocked', __( 'Request blocked. Please try again later.', 'community-directory' ), 403 );
        }

        global $wpdb;
        $uuid = sanitize_text_field( $request->get_param( 'uuid' ) );

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.uuid, m.member_since, p.*
             FROM {$members_table} m
             LEFT JOIN {$profiles_table} p ON m.id = p.member_id
             WHERE m.uuid = %s AND m.status = 'active'",
            $uuid
        ) );

        if ( ! $row ) {
            return $this->error( 'not_found', __( 'Member not found.', 'community-directory' ), 404 );
        }

        // Decode all JSON fields (null-coalesce to avoid PHP 8.1 deprecation)
        $row->emails           = json_decode( $row->emails ?? '', true ) ?: array();
        $row->phones           = json_decode( $row->phones ?? '', true ) ?: array();
        $row->social_links     = json_decode( $row->social_links ?? '', true ) ?: array();
        $row->ministry_tags    = json_decode( $row->ministry_tags ?? '', true ) ?: array();
        $row->privacy_settings = json_decode( $row->privacy_settings ?? '', true ) ?: array();

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

        // Determine if this is the user's own profile
        $current_user_id = get_current_user_id();
        $current_member_id = CD_Members::get_member_id_by_user_id( $current_user_id );
        $is_own_profile = ( $current_member_id && $current_member_id == $row->member_id );
        $is_admin = current_user_can( 'manage_options' );

        // Remove internal DB fields and remap column names
        $member_id_for_check = $row->member_id;
        unset( $row->id );
        unset( $row->member_id );

        // Remap zip_code → zip for frontend consistency
        $row->zip = $row->zip_code ?? '';
        unset( $row->zip_code );

        // Apply privacy filtering for other members' profiles
        if ( ! $is_own_profile && ! $is_admin ) {
            $privacy = $row->privacy_settings;

            if ( ( $privacy['email'] ?? 'visible' ) === 'hidden' ) {
                $row->emails = array();
            }
            if ( ( $privacy['phone'] ?? 'visible' ) === 'hidden' ) {
                $row->phones = array();
            }
            if ( ( $privacy['address'] ?? 'visible' ) === 'hidden' ) {
                $row->address_home = '';
                $row->address_mailing = '';
                $row->city = '';
                $row->state = '';
                $row->zip = '';
            }
            if ( ( $privacy['social'] ?? 'hidden' ) === 'hidden' ) {
                $row->social_links = array();
            }
            if ( ( $privacy['date_of_birth'] ?? 'hidden' ) === 'hidden' ) {
                $row->date_of_birth = '';
            }
            if ( ( $privacy['wedding_anniversary'] ?? 'hidden' ) === 'hidden' ) {
                $row->wedding_anniversary = '';
            }
            // Emergency contact is always admin-only
            $row->emergency_contact_name = '';
            $row->emergency_contact_phone = '';
        }

        // Base64-encode email values for anti-scraping (PRD Section 10.2.4)
        if ( is_array( $row->emails ) ) {
            foreach ( $row->emails as &$email_entry ) {
                if ( ! empty( $email_entry['value'] ) ) {
                    $email_entry['value'] = base64_encode( $email_entry['value'] );
                }
            }
            unset( $email_entry );
        }

        // Fetch household data for this member
        $household = null;
        $hm_table         = CD_Database::table( 'household_members' );
        $households_table  = CD_Database::table( 'households' );
        $hm_profiles_table = CD_Database::table( 'directory_profiles' );
        $hm_members_table  = CD_Database::table( 'members' );

        $hm = $wpdb->get_row( $wpdb->prepare(
            "SELECT hm.household_id, hm.role, h.name AS household_name, h.primary_address, h.photo_url AS household_photo_url
             FROM {$hm_table} hm
             JOIN {$households_table} h ON hm.household_id = h.id
             WHERE hm.member_id = %d AND hm.left_at IS NULL AND h.status = 'active'",
            $member_id_for_check
        ) );

        if ( $hm ) {
            $hh_members = $wpdb->get_results( $wpdb->prepare(
                "SELECT hm2.member_id, hm2.role,
                        p.first_name, p.last_name, p.avatar_url,
                        m2.uuid
                 FROM {$hm_table} hm2
                 JOIN {$hm_members_table} m2 ON hm2.member_id = m2.id
                 LEFT JOIN {$hm_profiles_table} p ON hm2.member_id = p.member_id
                 WHERE hm2.household_id = %d AND hm2.left_at IS NULL
                 ORDER BY FIELD(hm2.role, 'head', 'spouse', 'child', 'other'), p.first_name ASC",
                $hm->household_id
            ) );

            $members_list = array();
            foreach ( $hh_members as $hh_m ) {
                $members_list[] = array(
                    'uuid'       => $hh_m->uuid,
                    'first_name' => $hh_m->first_name,
                    'last_name'  => $hh_m->last_name,
                    'avatar_url' => $hh_m->avatar_url,
                    'role'       => $hh_m->role,
                    'role_label' => CD_API_Households::role_label( $hh_m->role ),
                    'is_self'    => ( (int) $hh_m->member_id === (int) $member_id_for_check ),
                );
            }

            $household = array(
                'id'        => (int) $hm->household_id,
                'name'      => $hm->household_name,
                'address'   => CD_API_Households::decrypt_address( $hm->primary_address ),
                'photo_url' => $hm->household_photo_url ?? '',
                'my_role'   => $hm->role,
                'members'   => $members_list,
            );
        }

        // Resolve sunday_school_teacher name if set
        if ( ! empty( $row->sunday_school_teacher_id ) ) {
            $teacher_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT CONCAT(first_name, ' ', last_name) FROM {$profiles_table} WHERE member_id = %d",
                (int) $row->sunday_school_teacher_id
            ) );
            $row->sunday_school_teacher_name = $teacher_name ?: '';
        }

        // If emergency contact is linked to a member, re-sync their phone (keeps it fresh)
        if ( ! empty( $row->emergency_contact_member_id ) && $is_own_profile ) {
            $ec_data = $wpdb->get_row( $wpdb->prepare(
                "SELECT CONCAT(first_name, ' ', last_name) AS ec_name, phones FROM {$profiles_table} WHERE member_id = %d",
                (int) $row->emergency_contact_member_id
            ) );
            if ( $ec_data ) {
                $row->emergency_contact_name = $ec_data->ec_name;
                $ec_phones = json_decode( $ec_data->phones ?? '', true );
                if ( ! empty( $ec_phones[0]['value'] ) ) {
                    $row->emergency_contact_phone = $ec_phones[0]['value'];
                }
            }
        }

        return $this->success( array(
            'member'           => $row,
            'is_own_profile'   => $is_own_profile,
            'household'        => $household,
            'household_role'   => $hm ? $hm->role : null,
            'email_obfuscated' => true,
        ) );
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
        if ( isset( $params['salutation'] ) ) {
            $allowed_salutations = array( '', 'Mr', 'Mrs', 'Ms', 'Dr', 'Fr.', 'Dn.', 'Sr.', 'Rev.', 'Prof.' );
            $sal = sanitize_text_field( $params['salutation'] );
            $data['salutation'] = in_array( $sal, $allowed_salutations, true ) ? $sal : '';
            $format[] = '%s';
        }
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
        // Address: accept either address_home directly or address_line_1 + address_line_2
        if ( isset( $params['address_line_1'] ) || isset( $params['address_line_2'] ) ) {
            $line1 = sanitize_text_field( $params['address_line_1'] ?? '' );
            $line2 = sanitize_text_field( $params['address_line_2'] ?? '' );
            $combined = trim( $line1 . ( $line2 ? "\n" . $line2 : '' ) );
            $data['address_home'] = CD_Encryption::encrypt( $combined );
            $format[] = '%s';
        } elseif ( isset( $params['address_home'] ) ) {
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
             $data['zip_code'] = sanitize_text_field( $params['zip'] );
             $format[] = '%s';
        }
        if ( isset( $params['date_of_birth'] ) ) {
            $val = sanitize_text_field( $params['date_of_birth'] );
            if ( $val !== '' ) {
                $data['date_of_birth'] = CD_Encryption::encrypt( $val );
                $format[] = '%s';
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$profiles_table} SET date_of_birth = NULL WHERE member_id = %d",
                    $member_id
                ) );
            }
        }
        if ( isset( $params['baptism_date'] ) ) {
            $val = sanitize_text_field( $params['baptism_date'] );
            if ( $val !== '' ) {
                $data['baptism_date'] = $val;
                $format[] = '%s';
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$profiles_table} SET baptism_date = NULL WHERE member_id = %d",
                    $member_id
                ) );
            }
        }
        if ( isset( $params['wedding_anniversary'] ) ) {
            $val = sanitize_text_field( $params['wedding_anniversary'] );
            if ( $val !== '' ) {
                $data['wedding_anniversary'] = $val;
                $format[] = '%s';
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$profiles_table} SET wedding_anniversary = NULL WHERE member_id = %d",
                    $member_id
                ) );
            }
        }
        if ( isset( $params['name_day'] ) ) {
            $data['name_day'] = sanitize_text_field( $params['name_day'] );
            $format[] = '%s';
        }
        if ( isset( $params['emergency_contact_name'] ) ) {
            $val = sanitize_text_field( $params['emergency_contact_name'] );
            $data['emergency_contact_name'] = $val !== '' ? CD_Encryption::encrypt( $val ) : '';
            $format[] = '%s';
        }
        if ( isset( $params['emergency_contact_phone'] ) ) {
            $val = sanitize_text_field( $params['emergency_contact_phone'] );
            $data['emergency_contact_phone'] = $val !== '' ? CD_Encryption::encrypt( $val ) : '';
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

        // Child/student-specific fields
        if ( isset( $params['graduation_date'] ) ) {
            $data['graduation_date'] = sanitize_text_field( $params['graduation_date'] ) ?: null;
            $format[] = '%s';
        }
        if ( isset( $params['school_name'] ) ) {
            $data['school_name'] = sanitize_text_field( $params['school_name'] );
            $format[] = '%s';
        }
        if ( isset( $params['school_type'] ) ) {
            $allowed = array( 'high_school', 'college', 'university', 'other', '' );
            $val = sanitize_text_field( $params['school_type'] );
            $data['school_type'] = in_array( $val, $allowed, true ) ? $val : '';
            $format[] = '%s';
        }
        if ( isset( $params['major_studies'] ) ) {
            $data['major_studies'] = sanitize_text_field( $params['major_studies'] );
            $format[] = '%s';
        }
        if ( isset( $params['minor_studies'] ) ) {
            $data['minor_studies'] = sanitize_text_field( $params['minor_studies'] );
            $format[] = '%s';
        }
        if ( isset( $params['sunday_school_teacher_id'] ) ) {
            $data['sunday_school_teacher_id'] = absint( $params['sunday_school_teacher_id'] ) ?: null;
            $format[] = '%d';
        }
        if ( isset( $params['emergency_contact_member_id'] ) ) {
            $ec_id = absint( $params['emergency_contact_member_id'] );
            $data['emergency_contact_member_id'] = $ec_id ?: null;
            $format[] = '%d';

            // If linked to a directory member, auto-sync their phone as emergency contact phone
            if ( $ec_id ) {
                $ec_phones = $wpdb->get_var( $wpdb->prepare(
                    "SELECT phones FROM {$profiles_table} WHERE member_id = %d",
                    $ec_id
                ) );
                $ec_phone_arr = json_decode( $ec_phones ?? '', true );
                if ( ! empty( $ec_phone_arr[0]['value'] ) ) {
                    $data['emergency_contact_phone'] = CD_Encryption::encrypt( sanitize_text_field( $ec_phone_arr[0]['value'] ) );
                    // Check if format already has emergency_contact_phone, if not add it
                    if ( ! isset( $params['emergency_contact_phone'] ) ) {
                        $format[] = '%s';
                    }
                }
            }
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

        // Privacy settings
        if ( isset( $params['privacy_settings'] ) && is_array( $params['privacy_settings'] ) ) {
            $allowed_keys = array( 'email', 'phone', 'address', 'social', 'date_of_birth', 'wedding_anniversary', 'occupation' );
            $clean_privacy = array();
            foreach ( $params['privacy_settings'] as $key => $value ) {
                if ( in_array( $key, $allowed_keys, true ) ) {
                    $clean_privacy[ $key ] = ( $value === 'visible' ) ? 'visible' : 'hidden';
                }
            }
            $data['privacy_settings'] = wp_json_encode( $clean_privacy );
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
            CD_Logger::error( 'Update Profile: ' . $wpdb->last_error );
            return $this->error( 'db_error', __( 'Could not save profile.', 'community-directory' ), 500 );
        }

        // Update has_different_address flag on household_members if provided
        if ( isset( $params['has_different_address'] ) ) {
            $hm_table = CD_Database::table( 'household_members' );
            $wpdb->update(
                $hm_table,
                array( 'has_different_address' => $params['has_different_address'] ? 1 : 0 ),
                array( 'member_id' => $member_id, 'left_at' => null ),
                array( '%d' ),
                array( '%d', '%s' )
            );
        }

        // Google Contacts sync — update
        if ( class_exists( 'CD_Google_Contacts' ) ) {
            CD_Google_Contacts::sync_member( $member_id, 'update' );
        }

        // Auto-sync wedding anniversary to spouse
        if ( isset( $data['wedding_anniversary'] ) ) {
            $hm_table = CD_Database::table( 'household_members' );
            $my_hh = $wpdb->get_row( $wpdb->prepare(
                "SELECT household_id, role FROM {$hm_table} WHERE member_id = %d AND left_at IS NULL",
                $member_id
            ) );
            if ( $my_hh && in_array( $my_hh->role, array( 'head', 'spouse' ), true ) ) {
                $spouse = $wpdb->get_var( $wpdb->prepare(
                    "SELECT member_id FROM {$hm_table}
                     WHERE household_id = %d AND member_id != %d AND role IN ('head','spouse') AND left_at IS NULL",
                    $my_hh->household_id, $member_id
                ) );
                if ( $spouse ) {
                    $wpdb->update(
                        $profiles_table,
                        array( 'wedding_anniversary' => $data['wedding_anniversary'] ),
                        array( 'member_id' => $spouse ),
                        array( '%s' ),
                        array( '%d' )
                    );
                }
            }
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
            CD_Logger::error( 'Avatar Upload: ' . $attachment_id->get_error_message() );
            return $this->error( 'upload_failed', $attachment_id->get_error_message(), 500 );
        }

        // Strip EXIF metadata (PRD Section 10.2.9) — re-save via image editor
        // GD/Imagick discard EXIF when re-rendering pixel data
        $att_file_path = get_attached_file( $attachment_id );
        if ( $att_file_path && file_exists( $att_file_path ) ) {
            $editor = wp_get_image_editor( $att_file_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->save( $att_file_path );

                // Verify EXIF stripped for JPEG/TIFF (if exif extension available)
                if ( function_exists( 'exif_read_data' ) && in_array( $file['type'], array( 'image/jpeg', 'image/tiff' ), true ) ) {
                    $exif = @exif_read_data( $att_file_path );
                    if ( $exif && ! empty( $exif['GPSLatitude'] ) ) {
                        CD_Logger::warn( 'EXIF GPS data persisted after re-save for attachment ' . $attachment_id );
                    }
                }
            }
        }

        $url = wp_get_attachment_url( $attachment_id );

        // Update profile
        global $wpdb;
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $wpdb->update(
            $profiles_table,
            array( 'avatar_url' => $url, 'avatar_source' => 'upload' ),
            array( 'member_id' => $member_id ),
            array( '%s', '%s' ),
            array( '%d' )
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

    /**
     * Request account deletion. Creates a pending request for admin review.
     */
    public function request_deletion( WP_REST_Request $request ) {
        global $wpdb;

        $member = $this->get_current_member();
        if ( ! $member ) {
            return $this->error( 'no_member', __( 'No member record found.', 'community-directory' ), 404 );
        }

        $dr_table = CD_Database::table( 'deletion_requests' );

        // Check for existing pending request
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$dr_table} WHERE member_id = %d AND status = 'pending'",
            $member->id
        ) );
        if ( $existing ) {
            return $this->error( 'duplicate_request', __( 'You already have a pending deletion request.', 'community-directory' ) );
        }

        $reason = sanitize_textarea_field( $request->get_param( 'reason' ) ?: '' );

        $wpdb->insert( $dr_table, array(
            'member_id'    => $member->id,
            'reason'       => $reason,
            'requested_at' => current_time( 'mysql' ),
            'status'       => 'pending',
        ), array( '%d', '%s', '%s', '%s' ) );

        CD_Audit_Logger::log( CD_Audit_Logger::DELETION_REQUESTED, get_current_user_id(), $member->id, array(
            'reason' => $reason,
        ) );

        return $this->success( array(
            'message' => __( 'Your account deletion request has been submitted for review.', 'community-directory' ),
        ) );
    }

    /**
     * List active WhatsApp groups visible to the current member.
     * GET /whatsapp-groups
     */
    public function list_whatsapp_groups( WP_REST_Request $request ) {
        global $wpdb;

        $table = CD_Database::table( 'whatsapp_groups' );

        $rows = $wpdb->get_results(
            "SELECT id, name, description, invite_url, icon, visibility, visibility_tag
             FROM {$table}
             WHERE is_active = 1
             ORDER BY display_order ASC, name ASC"
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
            );
        }

        return $this->success( array( 'groups' => $groups ) );
    }
}
