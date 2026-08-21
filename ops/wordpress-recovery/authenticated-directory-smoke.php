<?php
/**
 * WP-CLI eval-file script for non-destructive authenticated Community Directory
 * testing. It outputs only aggregate/status data and never exposes member PII.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

global $wpdb;

$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_REFERER']   = home_url( '/community/directory/' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 StTheklaRecoverySmoke/1.0';

$namespace = '/community-directory/v1';
$custom_tables = array(
    'applications',
    'members',
    'directory_profiles',
    'households',
    'household_members',
    'invites',
    'audit_log',
    'google_sync_log',
    'officers',
    'push_subscriptions',
    'whatsapp_groups',
    'household_requests',
    'deletion_requests',
    'schema_versions',
);

function st_smoke_table_counts( $wpdb, $suffixes ) {
    $counts = array();
    foreach ( $suffixes as $suffix ) {
        $table = $wpdb->prefix . 'cd_' . $suffix;
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
        $counts[ $suffix ] = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) : null;
    }
    return $counts;
}

function st_smoke_unwrap( $data ) {
    if ( is_array( $data ) && array_key_exists( 'success', $data ) && array_key_exists( 'data', $data ) ) {
        return $data['data'];
    }
    return $data;
}

function st_smoke_find_count( $data ) {
    $data = st_smoke_unwrap( $data );
    if ( ! is_array( $data ) ) {
        return null;
    }

    foreach ( array( 'items', 'results', 'members', 'households', 'groups', 'applications', 'registrations', 'requests' ) as $key ) {
        if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
            return count( $data[ $key ] );
        }
    }

    if ( array_is_list( $data ) ) {
        return count( $data );
    }

    return null;
}

function st_smoke_request( $method, $route, $params = array() ) {
    $request = new WP_REST_Request( $method, $route );
    foreach ( $params as $key => $value ) {
        $request->set_param( $key, $value );
    }

    $response = rest_do_request( $request );
    if ( is_wp_error( $response ) ) {
        return array(
            'route'       => $route,
            'status'      => 500,
            'success'     => false,
            'error_code'  => $response->get_error_code(),
            'item_count'  => null,
        );
    }

    $status = $response->get_status();
    $data   = $response->get_data();
    $success = $status >= 200 && $status < 300;
    if ( is_array( $data ) && array_key_exists( 'success', $data ) ) {
        $success = $success && true === $data['success'];
    }

    $summary = array(
        'route'      => $route,
        'status'     => $status,
        'success'    => $success,
        'item_count' => st_smoke_find_count( $data ),
    );

    if ( ! $success && is_array( $data ) && isset( $data['error']['code'] ) ) {
        $summary['error_code'] = sanitize_key( (string) $data['error']['code'] );
    }

    return $summary;
}

$before_counts = st_smoke_table_counts( $wpdb, $custom_tables );
$server = rest_get_server();
$registered_routes = $server->get_routes();

$required_routes = array(
    $namespace . '/auth/session-check',
    $namespace . '/auth/google',
    $namespace . '/directory',
    $namespace . '/members/me',
    $namespace . '/members/me/household',
    $namespace . '/whatsapp-groups',
    $namespace . '/admin/registrations',
    $namespace . '/admin/applications',
    $namespace . '/admin/members',
    $namespace . '/admin/stats',
);
$missing_routes = array_values( array_filter( $required_routes, function ( $route ) use ( $registered_routes ) {
    return ! isset( $registered_routes[ $route ] );
} ) );

$members_table = $wpdb->prefix . 'cd_members';
$hm_table      = $wpdb->prefix . 'cd_household_members';
$member_candidates = $wpdb->get_results(
    "SELECT m.id, m.uuid, m.wp_user_id, hm.household_id
     FROM `{$members_table}` m
     INNER JOIN `{$hm_table}` hm ON hm.member_id = m.id AND hm.left_at IS NULL
     WHERE m.status = 'active' AND m.wp_user_id IS NOT NULL
     ORDER BY CASE hm.role WHEN 'head' THEN 0 WHEN 'spouse' THEN 1 ELSE 2 END, m.id ASC"
);

$member_candidate = null;
foreach ( $member_candidates as $candidate ) {
    $user = get_user_by( 'id', (int) $candidate->wp_user_id );
    if ( $user && user_can( $user, 'cd_member' ) ) {
        $member_candidate = $candidate;
        break;
    }
}

$member_tests = array();
if ( $member_candidate ) {
    wp_set_current_user( (int) $member_candidate->wp_user_id );
    $member_tests[] = st_smoke_request( 'GET', $namespace . '/auth/session-check' );
    $member_tests[] = st_smoke_request( 'GET', $namespace . '/members/me' );
    $member_tests[] = st_smoke_request( 'GET', $namespace . '/members/' . $member_candidate->uuid );
    $member_tests[] = st_smoke_request( 'GET', $namespace . '/members/me/household' );
    $member_tests[] = st_smoke_request( 'GET', $namespace . '/whatsapp-groups' );
    $member_tests[] = st_smoke_request( 'GET', $namespace . '/directory', array(
        'page'      => 1,
        'per_page'  => 5,
        'view_mode' => 'members',
    ) );
    $member_tests[] = st_smoke_request( 'GET', $namespace . '/directory', array(
        'page'      => 1,
        'per_page'  => 5,
        'view_mode' => 'households',
    ) );

    delete_transient( 'cd_rl_' . md5( 'dir_search_' . (int) $member_candidate->wp_user_id ) );
    delete_transient( 'cd_bot_timing_' . md5( (int) $member_candidate->wp_user_id . '_127.0.0.1' ) );
    delete_transient( 'cd_bot_block_' . md5( (int) $member_candidate->wp_user_id . '_127.0.0.1' ) );
}

$admin_candidate = null;
$administrator_ids = get_users( array(
    'role'   => 'administrator',
    'fields' => 'ids',
    'number' => 20,
) );
foreach ( $administrator_ids as $user_id ) {
    $user = get_user_by( 'id', (int) $user_id );
    if ( $user && user_can( $user, 'manage_options' ) && user_can( $user, 'cd_admin' ) ) {
        $admin_candidate = $user;
        break;
    }
}

$admin_tests = array();
if ( $admin_candidate ) {
    wp_set_current_user( (int) $admin_candidate->ID );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/registrations', array( 'page' => 1, 'per_page' => 5 ) );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/applications', array( 'page' => 1, 'per_page' => 5 ) );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/members', array( 'page' => 1, 'per_page' => 5 ) );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/stats' );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/household-requests' );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/deletion-requests' );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/whatsapp-groups' );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/admin/officers' );
    $admin_tests[] = st_smoke_request( 'GET', $namespace . '/households', array( 'page' => 1, 'per_page' => 5 ) );
}

wp_set_current_user( 0 );
$oauth_test = st_smoke_request( 'GET', $namespace . '/auth/google' );
$oauth_response = rest_do_request( new WP_REST_Request( 'GET', $namespace . '/auth/google' ) );
$oauth_url_valid = false;
$oauth_state = '';
if ( ! is_wp_error( $oauth_response ) ) {
    $oauth_data = $oauth_response->get_data();
    $auth_url = is_array( $oauth_data ) && isset( $oauth_data['data']['auth_url'] ) ? $oauth_data['data']['auth_url'] : '';
    if ( $auth_url ) {
        $parts = wp_parse_url( $auth_url );
        parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
        $oauth_state = isset( $query['state'] ) ? sanitize_text_field( $query['state'] ) : '';
        $oauth_url_valid = isset( $parts['host'] )
            && 'accounts.google.com' === strtolower( $parts['host'] )
            && ! empty( $query['client_id'] )
            && ! empty( $query['redirect_uri'] )
            && ! empty( $oauth_state );
    }
}
if ( $oauth_state ) {
    delete_transient( 'cd_google_redirect_' . $oauth_state );
    delete_transient( 'cd_google_invite_' . $oauth_state );
}

$encrypted_secret = get_option( 'cd_google_client_secret_enc', '' );
$oauth_secret_decrypts = false;
if ( $encrypted_secret && class_exists( 'CD_Encryption' ) ) {
    $oauth_secret_decrypts = '' !== CD_Encryption::decrypt( $encrypted_secret );
}

$after_counts = st_smoke_table_counts( $wpdb, $custom_tables );
$table_counts_unchanged = $before_counts === $after_counts;

$all_tests = array_merge( $member_tests, $admin_tests, array( $oauth_test ) );
$failed_tests = array_values( array_filter( $all_tests, function ( $test ) {
    return empty( $test['success'] );
} ) );

$report = array(
    'generated_at_utc' => gmdate( 'c' ),
    'plugin' => array(
        'active'       => is_plugin_active( 'community-directory/community-directory.php' ),
        'version'      => defined( 'CD_VERSION' ) ? CD_VERSION : null,
        'db_version'   => get_option( 'cd_db_version', null ),
        'missing_required_routes' => $missing_routes,
    ),
    'member_context' => array(
        'candidate_found'       => null !== $member_candidate,
        'candidate_has_household' => null !== $member_candidate && ! empty( $member_candidate->household_id ),
        'tests'                 => $member_tests,
    ),
    'admin_context' => array(
        'candidate_found' => null !== $admin_candidate,
        'tests'           => $admin_tests,
    ),
    'google_oauth' => array(
        'enabled_option'        => (string) get_option( 'cd_google_oauth_enabled', '0' ),
        'client_id_configured'  => '' !== (string) get_option( 'cd_google_client_id', '' ),
        'secret_option_present' => '' !== (string) $encrypted_secret,
        'secret_decrypts'       => $oauth_secret_decrypts,
        'auth_url_valid'        => $oauth_url_valid,
        'route_test'            => $oauth_test,
    ),
    'pwa' => array(
        'enabled_option' => (string) get_option( 'cd_pwa_enabled', '0' ),
        'app_name_configured' => '' !== (string) get_option( 'cd_pwa_app_name', '' ),
        'short_name_configured' => '' !== (string) get_option( 'cd_pwa_short_name', '' ),
    ),
    'data_preservation' => array(
        'custom_table_counts_unchanged' => $table_counts_unchanged,
        'before' => $before_counts,
        'after'  => $after_counts,
    ),
    'failed_route_tests' => $failed_tests,
);

$success = $report['plugin']['active']
    && '0.5.2' === $report['plugin']['version']
    && empty( $missing_routes )
    && null !== $member_candidate
    && null !== $admin_candidate
    && empty( $failed_tests )
    && $oauth_url_valid
    && $oauth_secret_decrypts
    && $table_counts_unchanged;

$report['success'] = $success;
echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
exit( $success ? 0 : 2 );
