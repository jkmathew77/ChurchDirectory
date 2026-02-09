<?php
/**
 * Community Directory — Uninstall Handler.
 *
 * Fired when the plugin is deleted from WP Admin → Plugins.
 * Removes all custom database tables, capabilities, options, and cron jobs.
 *
 * NOTE: This is intentionally destructive. Deactivation (class-deactivator.php)
 * only removes cron jobs. Uninstall removes ALL data permanently.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// ─── 1. Drop all custom tables ───
$prefix = $wpdb->prefix . 'cd_';
$tables = array(
    'schema_versions',
    'audit_log',
    'google_sync_log',
    'push_subscriptions',
    'whatsapp_groups',
    'household_requests',
    'deletion_requests',
    'household_members',
    'households',
    'invites',
    'officers',
    'directory_profiles',
    'members',
    'applications',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

// ─── 2. Remove all custom capabilities from all roles ───
$caps = array( 'cd_member', 'cd_officer', 'cd_secretary', 'cd_admin' );

global $wp_roles;
if ( ! isset( $wp_roles ) ) {
    $wp_roles = new WP_Roles();
}

foreach ( $wp_roles->roles as $role_name => $role_info ) {
    $role = get_role( $role_name );
    if ( $role ) {
        foreach ( $caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }
}

// Also remove from individual users who may have been granted caps directly
$users_with_caps = get_users( array(
    'meta_query' => array(
        'relation' => 'OR',
        array( 'key' => $wpdb->prefix . 'capabilities', 'value' => 'cd_member', 'compare' => 'LIKE' ),
        array( 'key' => $wpdb->prefix . 'capabilities', 'value' => 'cd_officer', 'compare' => 'LIKE' ),
        array( 'key' => $wpdb->prefix . 'capabilities', 'value' => 'cd_secretary', 'compare' => 'LIKE' ),
        array( 'key' => $wpdb->prefix . 'capabilities', 'value' => 'cd_admin', 'compare' => 'LIKE' ),
    ),
) );

foreach ( $users_with_caps as $user ) {
    foreach ( $caps as $cap ) {
        $user->remove_cap( $cap );
    }
}

// ─── 3. Delete all plugin options ───
$options = array(
    'cd_db_version',
    'cd_base_slug',
    'cd_menu_label',
    'cd_menu_visible',
    'cd_verification_expiry',
    'cd_invite_expiry',
    'cd_login_rate_limit',
    'cd_google_client_id',
    'cd_google_client_secret_enc',
    'cd_google_redirect_uri',
    'cd_vapid_public_key',
    'cd_vapid_private_key_enc',
    'cd_photo_max_size',
    'cd_enable_push_notifications',
    'cd_enable_whatsapp_links',
    'cd_deletion_grace_days',
    'cd_undo_grace_seconds',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// ─── 4. Clear all transients created by the plugin ───
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cd_%' OR option_name LIKE '_transient_timeout_cd_%'"
); // phpcs:ignore WordPress.DB.PreparedSQL

// ─── 5. Clear scheduled cron events ───
// Must match the hook names in class-activator.php
$cron_hooks = array(
    'cd_expire_invites',
    'cd_audit_log_cleanup',
    'cd_expire_reset_tokens',
    'cd_data_retention_check',
    'cd_archive_unverified',
    'cd_transient_cleanup',
    'cd_google_contact_retry',
);

foreach ( $cron_hooks as $hook ) {
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
}

// ─── 6. Flush rewrite rules ───
flush_rewrite_rules();
