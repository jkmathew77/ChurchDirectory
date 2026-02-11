<?php
/**
 * Google Contacts integration via People API.
 * Creates workspace-wide shared contacts using delegated OAuth.
 * Uses wp_remote_post/wp_remote_get — no Composer dependencies.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Google_Contacts {

    const TOKEN_URL     = 'https://oauth2.googleapis.com/token';
    const PEOPLE_API    = 'https://people.googleapis.com/v1';
    const TRANSIENT_KEY = 'cd_google_access_token';

    /**
     * Check if Google Contacts integration is enabled and configured.
     */
    public static function is_enabled() {
        return '1' === get_option( 'cd_google_sync_enabled', '0' )
            && self::get_client_id()
            && self::get_refresh_token();
    }

    /**
     * Get client ID from settings.
     */
    private static function get_client_id() {
        return get_option( 'cd_google_client_id', '' );
    }

    /**
     * Get client secret (decrypted).
     */
    private static function get_client_secret() {
        $encrypted = get_option( 'cd_google_client_secret', '' );
        if ( empty( $encrypted ) ) {
            return '';
        }
        return CD_Encryption::decrypt( $encrypted );
    }

    /**
     * Get stored refresh token (decrypted).
     */
    private static function get_refresh_token() {
        $encrypted = get_option( 'cd_google_refresh_token', '' );
        if ( empty( $encrypted ) ) {
            return '';
        }
        return CD_Encryption::decrypt( $encrypted );
    }

    /**
     * Get a valid access token, refreshing if needed.
     *
     * @return string|WP_Error Access token or error.
     */
    public static function get_access_token() {
        // Check cache first
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached ) {
            return $cached;
        }

        $client_id     = self::get_client_id();
        $client_secret = self::get_client_secret();
        $refresh_token = self::get_refresh_token();

        if ( ! $client_id || ! $client_secret || ! $refresh_token ) {
            return new WP_Error( 'not_configured', __( 'Google API credentials not configured.', 'community-directory' ) );
        }

        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['error'] ) ) {
            return new WP_Error( 'token_error', $body['error_description'] ?? $body['error'] );
        }

        if ( empty( $body['access_token'] ) ) {
            return new WP_Error( 'no_token', __( 'Failed to obtain access token.', 'community-directory' ) );
        }

        // Cache for slightly less than the token lifetime (default 3600s)
        $expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 60 : 3500;
        set_transient( self::TRANSIENT_KEY, $body['access_token'], $expires_in );

        return $body['access_token'];
    }

    /**
     * Build the OAuth authorization URL for admin setup.
     *
     * @return string The Google OAuth consent URL.
     */
    public static function get_auth_url() {
        $client_id    = self::get_client_id();
        $redirect_uri = rest_url( 'community-directory/v1/admin/google/callback' );
        CD_Logger::debug( 'Google Contacts: Generated Redirect URI: ' . $redirect_uri );

        $params = array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/contacts',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce( 'cd_google_oauth' ),
        );

        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query( $params );
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $code The authorization code from Google.
     * @return true|WP_Error
     */
    public static function exchange_code( $code ) {
        $client_id     = self::get_client_id();
        $client_secret = self::get_client_secret();
        $redirect_uri  = rest_url( 'community-directory/v1/admin/google/callback' );

        CD_Logger::debug( 'Google Contacts: Exchanging code...' );
        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            CD_Logger::error( 'Google Contacts: Token Request Failed: ' . $response->get_error_message() );
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        CD_Logger::debug( 'Google Contacts: Token Response: ' . print_r( $body, true ) );

        if ( ! empty( $body['error'] ) ) {
            CD_Logger::error( 'Google Contacts: Google API Error: ' . ($body['error_description'] ?? $body['error']) );
            return new WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
        }

        // Store encrypted refresh token only if we received one
        if ( ! empty( $body['refresh_token'] ) ) {
            CD_Logger::info( 'Google Contacts: Refresh token received. Updating option.' );
            update_option( 'cd_google_refresh_token', CD_Encryption::encrypt( $body['refresh_token'] ) );
        } else {
            CD_Logger::warn( 'Google Contacts: NO refresh token in response.' );
        }
        
        // If we don't have a refresh token now (and didn't have one before), it's an error
        if ( ! self::get_refresh_token() ) {
             CD_Logger::error( 'Google Contacts: Critical - No refresh token available after exchange.' );
             return new WP_Error( 'no_refresh_token', __( 'No refresh token received. Please revoke access in your Google Account and try again.', 'community-directory' ) );
        }

        CD_Logger::info( 'Google Contacts: Token exchange successful.' );

        // Cache the access token
        if ( ! empty( $body['access_token'] ) ) {
            $expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 60 : 3500;
            set_transient( self::TRANSIENT_KEY, $body['access_token'], $expires_in );
        }

        return true;
    }

    /**
     * Build a People API person resource from member data.
     *
     * @param array $member_data first_name, last_name, email, phone, form_data.
     * @return array Person resource for the People API.
     */
    private static function build_person_resource( $member_data ) {
        $person = array(
            'names' => array( array(
                'givenName'  => $member_data['first_name'] ?? '',
                'familyName' => $member_data['last_name'] ?? '',
            ) ),
        );

        $form_data = $member_data['form_data'] ?? array();
        if ( ! empty( $form_data['middle_initial'] ) ) {
            $person['names'][0]['middleName'] = $form_data['middle_initial'];
        }

        if ( ! empty( $member_data['email'] ) ) {
            $person['emailAddresses'] = array( array(
                'value' => $member_data['email'],
                'type'  => 'home',
            ) );
        }

        $phone = $member_data['phone'] ?? ( $form_data['phone'] ?? '' );
        if ( ! empty( $phone ) ) {
            $person['phoneNumbers'] = array( array(
                'value' => $phone,
                'type'  => 'mobile',
            ) );
        }

        if ( ! empty( $form_data['address_line_1'] ) ) {
            $person['addresses'] = array( array(
                'streetAddress' => $form_data['address_line_1'] ?? '',
                'city'          => $form_data['city'] ?? '',
                'region'        => $form_data['state'] ?? '',
                'postalCode'    => $form_data['zip'] ?? '',
                'type'          => 'home',
            ) );
        }

        if ( ! empty( $form_data['date_of_birth'] ) ) {
            $parts = explode( '-', $form_data['date_of_birth'] );
            if ( count( $parts ) === 3 ) {
                $person['birthdays'] = array( array(
                    'date' => array(
                        'year'  => (int) $parts[0],
                        'month' => (int) $parts[1],
                        'day'   => (int) $parts[2],
                    ),
                ) );
            }
        }

        if ( ! empty( $form_data['profession'] ) ) {
            $person['organizations'] = array( array(
                'title' => $form_data['profession'],
            ) );
        }

        $person['biographies'] = array( array(
            'value'       => 'St. Thekla Community Directory Member',
            'contentType' => 'TEXT_PLAIN',
        ) );

        return $person;
    }

    /**
     * Create a contact in Google via People API.
     *
     * @param array $member_data Member data: first_name, last_name, email, form_data.
     * @return string|WP_Error The Google resource name (e.g., "people/c123") or error.
     */
    public static function create_contact( $member_data ) {
        $access_token = self::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $person = self::build_person_resource( $member_data );
        $url    = self::PEOPLE_API . '/people:createContact?personFields=names,emailAddresses,phoneNumbers,addresses,birthdays,organizations,biographies,memberships';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $person ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            $error_msg = $body['error']['message'] ?? __( 'Google API error', 'community-directory' );
            return new WP_Error( 'google_api_error', $error_msg );
        }

        return $body['resourceName'] ?? '';
    }

    /**
     * Get a contact from Google to retrieve the current etag.
     *
     * @param string $resource_name e.g. "people/c123456789"
     * @return array|WP_Error The person resource array or error.
     */
    public static function get_contact( $resource_name ) {
        $access_token = self::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $url = self::PEOPLE_API . '/' . $resource_name
             . '?personFields=names,emailAddresses,phoneNumbers,addresses,birthdays,organizations,biographies,metadata';

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            $error_msg = $body['error']['message'] ?? 'Google API error';
            return new WP_Error( 'google_api_error', $error_msg );
        }

        return $body;
    }

    /**
     * Update an existing Google contact.
     * GETs the contact first for the required etag, then PATCHes.
     *
     * @param string $resource_name e.g. "people/c123456789"
     * @param array  $member_data   Same format as create_contact().
     * @return true|WP_Error
     */
    public static function update_contact( $resource_name, $member_data ) {
        $access_token = self::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $existing = self::get_contact( $resource_name );
        if ( is_wp_error( $existing ) ) {
            return $existing;
        }

        $etag = $existing['etag'] ?? '';
        if ( empty( $etag ) ) {
            return new WP_Error( 'no_etag', 'Could not retrieve contact etag for update.' );
        }

        $person          = self::build_person_resource( $member_data );
        $person['etag']  = $etag;
        $update_fields   = 'names,emailAddresses,phoneNumbers,addresses,birthdays,organizations,biographies';
        $url             = self::PEOPLE_API . '/' . $resource_name
                         . ':updateContact?updatePersonFields=' . $update_fields;

        $response = wp_remote_request( $url, array(
            'method'  => 'PATCH',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $person ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status >= 400 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_Error( 'google_api_error', $body['error']['message'] ?? 'Update failed' );
        }

        return true;
    }

    /**
     * Delete a contact from Google Workspace.
     *
     * @param string $resource_name e.g. "people/c123456789"
     * @return true|WP_Error
     */
    public static function delete_contact( $resource_name ) {
        $access_token = self::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $url = self::PEOPLE_API . '/' . $resource_name . ':deleteContact';

        $response = wp_remote_request( $url, array(
            'method'  => 'DELETE',
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status >= 400 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_Error( 'google_api_error', $body['error']['message'] ?? 'Delete failed' );
        }

        return true;
    }

    /**
     * Build member data array from the database for Google sync.
     *
     * @param int $member_id
     * @return array|false Member data array or false if not found.
     */
    public static function get_member_sync_data( $member_id ) {
        global $wpdb;
        $profiles_table = CD_Database::table( 'directory_profiles' );
        $members_table  = CD_Database::table( 'members' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.google_contact_id, p.first_name, p.last_name, p.emails, p.phones,
                    p.address_home, p.city, p.state, p.zip_code, p.date_of_birth, p.occupation
             FROM {$members_table} m
             LEFT JOIN {$profiles_table} p ON m.id = p.member_id
             WHERE m.id = %d",
            $member_id
        ) );

        if ( ! $row ) {
            return false;
        }

        $emails = json_decode( $row->emails ?? '', true ) ?: array();
        $phones = json_decode( $row->phones ?? '', true ) ?: array();
        $email  = ! empty( $emails[0]['value'] ) ? $emails[0]['value'] : '';
        $phone  = ! empty( $phones[0]['value'] ) ? $phones[0]['value'] : '';

        $dob     = $row->date_of_birth ? CD_Encryption::decrypt( $row->date_of_birth ) : '';
        $address = $row->address_home  ? CD_Encryption::decrypt( $row->address_home )  : '';

        return array(
            'first_name'        => $row->first_name ?? '',
            'last_name'         => $row->last_name ?? '',
            'email'             => $email,
            'phone'             => $phone,
            'google_contact_id' => $row->google_contact_id ?? '',
            'form_data'         => array(
                'address_line_1' => $address,
                'city'           => $row->city ?? '',
                'state'          => $row->state ?? '',
                'zip'            => $row->zip_code ?? '',
                'date_of_birth'  => $dob,
                'profession'     => $row->occupation ?? '',
            ),
        );
    }

    /**
     * Central sync dispatcher — creates, updates, or deletes a Google contact.
     * Handles missing google_contact_id by falling back to create.
     * Failures are queued for cron retry.
     *
     * @param int    $member_id
     * @param string $operation 'create', 'update', or 'delete'.
     */
    public static function sync_member( $member_id, $operation = 'update' ) {
        if ( ! self::is_enabled() ) {
            return;
        }

        $member_data = self::get_member_sync_data( $member_id );
        if ( ! $member_data ) {
            CD_Logger::warn( "Google Sync: member {$member_id} not found for {$operation}" );
            return;
        }

        $resource_name = $member_data['google_contact_id'] ?? '';

        switch ( $operation ) {
            case 'create':
                $result = self::create_contact( $member_data );
                if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
                    global $wpdb;
                    $wpdb->update(
                        CD_Database::table( 'members' ),
                        array( 'google_contact_id' => sanitize_text_field( $result ) ),
                        array( 'id' => $member_id ),
                        array( '%s' ),
                        array( '%d' )
                    );
                }
                break;

            case 'update':
                if ( empty( $resource_name ) ) {
                    // No google_contact_id yet — create instead
                    $result = self::create_contact( $member_data );
                    if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
                        global $wpdb;
                        $wpdb->update(
                            CD_Database::table( 'members' ),
                            array( 'google_contact_id' => sanitize_text_field( $result ) ),
                            array( 'id' => $member_id ),
                            array( '%s' ),
                            array( '%d' )
                        );
                    }
                } else {
                    $result = self::update_contact( $resource_name, $member_data );
                }
                break;

            case 'delete':
                if ( empty( $resource_name ) ) {
                    return; // Nothing to delete in Google
                }
                $result = self::delete_contact( $resource_name );
                break;

            default:
                return;
        }

        if ( is_wp_error( $result ) ) {
            CD_Logger::warn( "Google Sync {$operation} failed for member {$member_id}: " . $result->get_error_message() );
            self::queue_retry( $member_id, $member_data, $operation );
        } else {
            CD_Logger::info( "Google Sync {$operation} succeeded for member {$member_id}" );
        }
    }

    /**
     * Import contacts from Google (for sync dashboard).
     *
     * @param int $page_size Number of contacts per page.
     * @param string $page_token Pagination token.
     * @return array|WP_Error Array with 'contacts' and 'nextPageToken'.
     */
    public static function import_contacts( $page_size = 100, $page_token = '' ) {
        $access_token = self::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $params = array(
            'personFields' => 'names,emailAddresses,phoneNumbers,addresses',
            'pageSize'     => min( 1000, max( 1, $page_size ) ),
            'sortOrder'    => 'LAST_NAME_ASCENDING',
        );
        if ( $page_token ) {
            $params['pageToken'] = $page_token;
        }

        $url = self::PEOPLE_API . '/people/me/connections?' . http_build_query( $params );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['error'] ) ) {
            return new WP_Error( 'google_api_error', $body['error']['message'] ?? 'Unknown error' );
        }

        $contacts = array();
        foreach ( ( $body['connections'] ?? array() ) as $person ) {
            $contact = array(
                'resourceName' => $person['resourceName'] ?? '',
                'name'         => '',
                'email'        => '',
                'phone'        => '',
            );

            if ( ! empty( $person['names'][0] ) ) {
                $n = $person['names'][0];
                $contact['name'] = trim( ( $n['givenName'] ?? '' ) . ' ' . ( $n['familyName'] ?? '' ) );
                $contact['first_name'] = $n['givenName'] ?? '';
                $contact['last_name']  = $n['familyName'] ?? '';
            }

            if ( ! empty( $person['emailAddresses'][0]['value'] ) ) {
                $contact['email'] = $person['emailAddresses'][0]['value'];
            }

            if ( ! empty( $person['phoneNumbers'][0]['value'] ) ) {
                $contact['phone'] = $person['phoneNumbers'][0]['value'];
            }

            $contacts[] = $contact;
        }

        return array(
            'contacts'      => $contacts,
            'nextPageToken' => $body['nextPageToken'] ?? null,
            'totalPeople'   => $body['totalPeople'] ?? count( $contacts ),
        );
    }

    /**
     * Match an imported Google contact against existing members.
     *
     * @param array $contact Normalized contact from import_contacts().
     * @param array $existing Array of existing members with email, first_name, last_name.
     * @return array Match result: 'match_type' (exact|strong|weak|none), 'member_id'.
     */
    public static function match_contact( $contact, $existing ) {
        // Exact match: email
        if ( ! empty( $contact['email'] ) ) {
            foreach ( $existing as $member ) {
                if ( strtolower( $contact['email'] ) === strtolower( $member['email'] ?? '' ) ) {
                    return array( 'match_type' => 'exact', 'member_id' => $member['id'] );
                }
            }
        }

        // Strong match: first name + last name + phone
        if ( ! empty( $contact['first_name'] ) && ! empty( $contact['last_name'] ) ) {
            foreach ( $existing as $member ) {
                $name_match = strtolower( $contact['first_name'] ) === strtolower( $member['first_name'] ?? '' )
                    && strtolower( $contact['last_name'] ) === strtolower( $member['last_name'] ?? '' );
                if ( $name_match && ! empty( $contact['phone'] ) && ! empty( $member['phone'] ) ) {
                    // Normalize phone numbers for comparison
                    $c_phone = preg_replace( '/\D/', '', $contact['phone'] );
                    $m_phone = preg_replace( '/\D/', '', $member['phone'] );
                    if ( $c_phone === $m_phone ) {
                        return array( 'match_type' => 'strong', 'member_id' => $member['id'] );
                    }
                }
            }
        }

        // Weak match: name only
        if ( ! empty( $contact['first_name'] ) && ! empty( $contact['last_name'] ) ) {
            foreach ( $existing as $member ) {
                if ( strtolower( $contact['first_name'] ) === strtolower( $member['first_name'] ?? '' )
                    && strtolower( $contact['last_name'] ) === strtolower( $member['last_name'] ?? '' ) ) {
                    return array( 'match_type' => 'weak', 'member_id' => $member['id'] );
                }
            }
        }

        return array( 'match_type' => 'none', 'member_id' => null );
    }

    /**
     * Queue a failed contact operation for cron retry.
     *
     * @param int    $member_id
     * @param array  $member_data
     * @param string $operation 'create', 'update', or 'delete'.
     */
    public static function queue_retry( $member_id, $member_data, $operation = 'create' ) {
        $queue = get_option( 'cd_google_retry_queue', array() );
        $queue[] = array(
            'member_id'     => $member_id,
            'member_data'   => $member_data,
            'operation'     => $operation,
            'resource_name' => $member_data['google_contact_id'] ?? '',
            'retries'       => 0,
            'last_attempt'  => time(),
        );
        update_option( 'cd_google_retry_queue', $queue );
    }

    /**
     * Get connection status info.
     */
    public static function get_status() {
        $has_credentials = self::get_client_id() && self::get_client_secret();
        $has_token       = (bool) self::get_refresh_token();
        $enabled         = self::is_enabled();

        $status = 'not_configured';
        if ( $has_credentials && $has_token ) {
            // Try to get an access token to verify connection
            $token = self::get_access_token();
            $status = is_wp_error( $token ) ? 'error' : 'connected';
        } elseif ( $has_credentials ) {
            $status = 'needs_auth';
        }

        $retry_queue = get_option( 'cd_google_retry_queue', array() );

        return array(
            'status'           => $status,
            'enabled'          => $enabled,
            'has_credentials'  => $has_credentials,
            'has_token'        => $has_token,
            'pending_retries'  => count( $retry_queue ),
        );
    }

    /**
     * Handle the OAuth callback (called via admin-ajax.php).
     */
    public static function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'community-directory' ) );
        }

        // Verify state nonce
        $state = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';
        if ( ! wp_verify_nonce( $state, 'cd_google_oauth' ) ) {
            wp_die( __( 'Invalid state parameter.', 'community-directory' ) );
        }

        if ( isset( $_GET['error'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=cd-settings&google_error=' . urlencode( sanitize_text_field( $_GET['error'] ) ) ) );
            exit;
        }

        $code = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
        if ( empty( $code ) ) {
            wp_die( __( 'No authorization code received.', 'community-directory' ) );
        }

        $result = self::exchange_code( $code );

        if ( is_wp_error( $result ) ) {
            wp_redirect( admin_url( 'admin.php?page=cd-settings&google_error=' . urlencode( $result->get_error_message() ) ) );
        } else {
            wp_redirect( admin_url( 'admin.php?page=cd-settings&google_connected=1' ) );
        }
        exit;
    }

    /**
     * Register the AJAX callback handler.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_cd_google_callback', array( __CLASS__, 'handle_oauth_callback' ) );
    }
}
