<?php
/**
 * WP-CLI eval-file script: report Community Directory runtime/schema health.
 * Outputs JSON and intentionally excludes member PII and secret option values.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

global $wpdb;

$plugin_file = WP_PLUGIN_DIR . '/community-directory/community-directory.php';
$plugin_dir  = dirname( $plugin_file );
$plugin_data = array();

if ( file_exists( $plugin_file ) ) {
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_data = get_plugin_data( $plugin_file, false, false );
}

$table_suffixes = array(
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

$tables = array();
foreach ( $table_suffixes as $suffix ) {
    $table_name = $wpdb->prefix . 'cd_' . $suffix;
    $exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
    $entry      = array(
        'name'   => $table_name,
        'exists' => $exists,
        'rows'   => null,
    );

    if ( $exists ) {
        $entry['rows'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );
    }

    $tables[ $suffix ] = $entry;
}

$critical_columns = array(
    'members' => array( 'id', 'wp_user_id', 'status', 'deactivated_at', 'deactivation_reason', 'google_id' ),
    'directory_profiles' => array( 'member_id', 'first_name', 'last_name', 'address_line_1', 'city', 'state', 'zip_code', 'salutation', 'directory_preferences' ),
    'households' => array( 'id', 'name', 'status', 'photo_url', 'photos' ),
    'household_members' => array( 'household_id', 'member_id', 'role', 'has_different_address' ),
    'invites' => array( 'id', 'email', 'token_hash', 'used_at', 'status' ),
);

$column_report = array();
foreach ( $critical_columns as $suffix => $expected ) {
    $table_name = $wpdb->prefix . 'cd_' . $suffix;
    if ( empty( $tables[ $suffix ]['exists'] ) ) {
        $column_report[ $suffix ] = array(
            'expected' => $expected,
            'present'  => array(),
            'missing'  => $expected,
        );
        continue;
    }

    $present = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
    $column_report[ $suffix ] = array(
        'expected' => $expected,
        'present'  => array_values( array_intersect( $expected, $present ) ),
        'missing'  => array_values( array_diff( $expected, $present ) ),
    );
}

$status_counts = array();
if ( ! empty( $tables['members']['exists'] ) ) {
    $members_table = $wpdb->prefix . 'cd_members';
    $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM `{$members_table}` GROUP BY status ORDER BY status ASC", ARRAY_A );
    foreach ( $rows as $row ) {
        $status_counts[ (string) $row['status'] ] = (int) $row['total'];
    }
}

$schema_history = array();
if ( ! empty( $tables['schema_versions']['exists'] ) ) {
    $schema_table = $wpdb->prefix . 'cd_schema_versions';
    $schema_history = $wpdb->get_results( "SELECT version, description, applied_at FROM `{$schema_table}` ORDER BY id ASC", ARRAY_A );
}

$report = array(
    'generated_at_utc' => gmdate( 'c' ),
    'wordpress' => array(
        'version' => get_bloginfo( 'version' ),
        'site_url' => site_url(),
        'home_url' => home_url(),
        'table_prefix' => $wpdb->prefix,
    ),
    'runtime' => array(
        'php_version' => PHP_VERSION,
        'openssl_loaded' => extension_loaded( 'openssl' ),
        'mysql_version' => $wpdb->db_version(),
    ),
    'plugin_files' => array(
        'file' => $plugin_file,
        'exists' => file_exists( $plugin_file ),
        'directory_is_symlink' => is_link( $plugin_dir ),
        'resolved_directory' => file_exists( $plugin_dir ) ? realpath( $plugin_dir ) : false,
        'version' => $plugin_data['Version'] ?? null,
        'requires_php' => $plugin_data['RequiresPHP'] ?? null,
    ),
    'safe_options' => array(
        'cd_db_version' => get_option( 'cd_db_version', null ),
        'cd_base_slug' => get_option( 'cd_base_slug', null ),
        'cd_menu_visible' => get_option( 'cd_menu_visible', null ),
        'cd_google_oauth_enabled' => get_option( 'cd_google_oauth_enabled', null ),
        'cd_google_sync_enabled' => get_option( 'cd_google_sync_enabled', null ),
        'cd_pwa_enabled' => get_option( 'cd_pwa_enabled', null ),
        'cd_rewrite_version' => get_option( 'cd_rewrite_version', null ),
    ),
    'tables' => $tables,
    'critical_columns' => $column_report,
    'member_status_counts' => $status_counts,
    'schema_history' => $schema_history,
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
