<?php
/**
 * Plugin deactivation — clean up cron jobs. Does NOT remove data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Deactivator {

    public static function deactivate() {
        // Unschedule all plugin cron jobs
        $hooks = array(
            'cd_expire_invites',
            'cd_audit_log_cleanup',
            'cd_expire_reset_tokens',
            'cd_data_retention_check',
            'cd_archive_unverified',
            'cd_transient_cleanup',
            'cd_google_contact_retry',
            'cd_google_sync_auto',
        );

        foreach ( $hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
