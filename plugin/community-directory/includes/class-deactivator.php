<?php
/**
 * Plugin deactivation — clean up cron jobs. Does NOT remove data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Deactivator {

    public static function deactivate() {
        // Unschedule every occurrence of each plugin cron hook. This is more
        // reliable than removing only the next timestamp after repeated
        // activation/deactivation cycles.
        $hooks = array(
            'cd_expire_invites',
            'cd_audit_log_cleanup',
            'cd_expire_reset_tokens',
            'cd_data_retention_check',
            'cd_archive_unverified',
            'cd_transient_cleanup',
            'cd_google_contact_retry',
        );

        foreach ( $hooks as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
