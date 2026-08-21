<?php
/**
 * Plugin activation — create tables, add capabilities, flush rewrite rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Activator {

    public static function activate() {
        // Run only migrations that have not already been applied. Reactivating
        // the plugin after maintenance or a hosting upgrade must not replay the
        // entire migration chain against an existing production database.
        $installed_version = get_option( 'cd_db_version', '000' );
        $db = new CD_Database();
        $db->run_migrations( $installed_version );

        // Add custom capabilities to administrator
        CD_Capabilities::add_caps();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Flush rewrite rules so /community/ routes work
        // We need to register the rules first
        $plugin = CD_Plugin::get_instance();
        $plugin->register_rewrite_rules();
        flush_rewrite_rules();

        // Set default options
        self::set_default_options();
    }

    /**
     * Schedule all plugin cron jobs.
     */
    private static function schedule_cron_jobs() {
        $jobs = array(
            'cd_expire_invites'       => 'twicedaily',
            'cd_audit_log_cleanup'    => 'daily',
            'cd_expire_reset_tokens'  => 'hourly',
            'cd_data_retention_check' => 'daily',
            'cd_archive_unverified'   => 'daily',
            'cd_transient_cleanup'    => 'daily',
            'cd_google_contact_retry' => 'hourly',
        );

        foreach ( $jobs as $hook => $recurrence ) {
            if ( ! wp_next_scheduled( $hook ) ) {
                wp_schedule_event( time(), $recurrence, $hook );
            }
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $defaults = array(
            'cd_base_slug'               => 'community',
            'cd_menu_label'              => 'Community',
            'cd_menu_visible'            => '1',
            'cd_verification_expiry'     => 48,      // hours
            'cd_invite_expiry'           => 14,      // days
            'cd_login_rate_limit'        => 5,       // attempts per 15 min
            'cd_password_reset_limit'    => 3,       // per hour per email
            'cd_reapplication_cooldown'  => 30,      // days
            'cd_data_retention_period'   => 730,     // days (2 years)
            'cd_unverified_archive_days' => 30,
            'cd_avatar_max_size'         => 10,      // MB
            'cd_google_oauth_enabled'    => '0',
            'cd_google_sync_enabled'     => '0',
            'cd_push_enabled'            => '0',
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }
}
