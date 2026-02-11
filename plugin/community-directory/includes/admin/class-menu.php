<?php
/**
 * Register WP Admin menu pages for Community Directory.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Admin_Menu {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
        add_action( 'admin_post_cd_download_csv_template', array( $this, 'download_csv_template' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Debug log AJAX handlers
        add_action( 'wp_ajax_cd_toggle_debug_logging', array( $this, 'ajax_toggle_debug_logging' ) );
        add_action( 'wp_ajax_cd_clear_debug_log', array( $this, 'ajax_clear_debug_log' ) );
        add_action( 'wp_ajax_cd_refresh_debug_log', array( $this, 'ajax_refresh_debug_log' ) );
    }

    /**
     * Enqueue admin assets on plugin pages.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load media library on settings page for PWA icon upload
        // Enqueue media library on our settings page for PWA icon upload
        if ( false !== strpos( $hook, 'cd-settings' ) ) {
            wp_enqueue_media();
        }
    }

    /**
     * Register all admin menu items.
     */
    public function register_menus() {
        // Main menu
        add_menu_page(
            __( 'Community Directory', 'community-directory' ),
            __( 'Community', 'community-directory' ),
            'manage_options',
            'community-directory',
            array( $this, 'render_dashboard_page' ),
            'dashicons-groups',
            30
        );

        // Dashboard (same as main)
        add_submenu_page(
            'community-directory',
            __( 'Dashboard', 'community-directory' ),
            __( 'Dashboard', 'community-directory' ),
            'manage_options',
            'community-directory',
            array( $this, 'render_dashboard_page' )
        );

        // Registrations (all applications including unverified)
        add_submenu_page(
            'community-directory',
            __( 'Registrations', 'community-directory' ),
            __( 'Registrations', 'community-directory' ),
            'manage_options',
            'cd-registrations',
            array( $this, 'render_registrations_page' )
        );

        // Applications (verified, for approval)
        add_submenu_page(
            'community-directory',
            __( 'Applications', 'community-directory' ),
            __( 'Applications', 'community-directory' ),
            'cd_secretary',
            'cd-applications',
            array( $this, 'render_applications_page' )
        );

        // Members
        add_submenu_page(
            'community-directory',
            __( 'Members', 'community-directory' ),
            __( 'Members', 'community-directory' ),
            'cd_admin',
            'cd-members',
            array( $this, 'render_members_page' )
        );

        // Households
        add_submenu_page(
            'community-directory',
            __( 'Households', 'community-directory' ),
            __( 'Households', 'community-directory' ),
            'manage_options',
            'cd-households',
            array( $this, 'render_households_page' )
        );

        // Requests (household merges + deletion requests)
        add_submenu_page(
            'community-directory',
            __( 'Requests', 'community-directory' ),
            __( 'Requests', 'community-directory' ),
            'manage_options',
            'cd-requests',
            array( $this, 'render_requests_page' )
        );

        // Officers Group
        add_submenu_page(
            'community-directory',
            __( 'Officers Group', 'community-directory' ),
            __( 'Officers Group', 'community-directory' ),
            'manage_options',
            'cd-officers',
            array( $this, 'render_officers_page' )
        );

        // WhatsApp Groups
        add_submenu_page(
            'community-directory',
            __( 'WhatsApp Groups', 'community-directory' ),
            __( 'WhatsApp Groups', 'community-directory' ),
            'manage_options',
            'cd-whatsapp',
            array( $this, 'render_whatsapp_page' )
        );

        // Import
        add_submenu_page(
            'community-directory',
            __( 'Import Members', 'community-directory' ),
            __( 'Import', 'community-directory' ),
            'manage_options',
            'cd-import',
            array( $this, 'render_import_page' )
        );

        // Reports
        add_submenu_page(
            'community-directory',
            __( 'Reports', 'community-directory' ),
            __( 'Reports', 'community-directory' ),
            'cd_admin',
            'cd-reports',
            array( $this, 'render_reports_page' )
        );

        // Settings
        add_submenu_page(
            'community-directory',
            __( 'Settings', 'community-directory' ),
            __( 'Settings', 'community-directory' ),
            'manage_options',
            'cd-settings',
            array( $this, 'render_settings_page' )
        );

        // Debug Log
        add_submenu_page(
            'community-directory',
            __( 'Debug Log', 'community-directory' ),
            __( 'Debug Log', 'community-directory' ),
            'manage_options',
            'cd-debug-log',
            array( $this, 'render_debug_log_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // General settings
        register_setting( 'cd_general_settings', 'cd_base_slug', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_title',
            'default'           => 'community',
        ) );
        register_setting( 'cd_general_settings', 'cd_menu_label', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Community',
        ) );
        register_setting( 'cd_general_settings', 'cd_menu_visible', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1',
        ) );

        // Google Contacts settings
        register_setting( 'cd_general_settings', 'cd_google_client_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'cd_general_settings', 'cd_google_client_secret_raw', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_google_client_secret' ),
            'default'           => '',
        ) );
        register_setting( 'cd_general_settings', 'cd_google_sync_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '0',
        ) );
        register_setting( 'cd_general_settings', 'cd_google_export_on_approval', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1',
        ) );
        register_setting( 'cd_general_settings', 'cd_google_contact_group', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'St. Thekla Members',
        ) );

        // PWA settings
        register_setting( 'cd_general_settings', 'cd_pwa_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '0',
        ) );
        register_setting( 'cd_general_settings', 'cd_pwa_app_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'St. Thekla Directory',
        ) );
        register_setting( 'cd_general_settings', 'cd_pwa_short_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'St. Thekla',
        ) );
        register_setting( 'cd_general_settings', 'cd_pwa_theme_color', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#8B0000',
        ) );
        register_setting( 'cd_general_settings', 'cd_pwa_background_color', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#FFFFFF',
        ) );
    }

    /**
     * Encrypt the Google client secret before storage.
     * The raw value comes from the form field name "cd_google_client_secret_raw".
     * We encrypt it and store it in "cd_google_client_secret".
     *
     * @param string $value The raw client secret from the form.
     * @return string Empty string — we handle storage ourselves.
     */
    public function sanitize_google_client_secret( $value ) {
        $value = sanitize_text_field( $value );

        // Only update if a new value was entered (blank means keep existing)
        if ( ! empty( $value ) ) {
            update_option( 'cd_google_client_secret', CD_Encryption::encrypt( $value ) );
        }

        // Return empty so the _raw option itself stays empty (secret is in cd_google_client_secret)
        return '';
    }

    /**
     * Show admin notices (migration errors, etc.).
     */
    public function show_admin_notices() {
        $error = get_transient( 'cd_migration_error' );
        if ( $error ) {
            echo '<div class="notice notice-error"><p><strong>Community Directory:</strong> ' . esc_html( $error ) . '</p></div>';
            delete_transient( 'cd_migration_error' );
        }
    }

    // ---- Page Renderers (placeholder implementations for Phase 0) ----

    public function render_dashboard_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
    }

    public function render_registrations_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/registrations.php';
    }

    public function render_applications_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/applications.php';
    }

    public function render_members_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/members.php';
    }

    public function render_households_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/households.php';
    }

    public function render_requests_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/requests.php';
    }

    public function render_officers_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/officers.php';
    }

    public function render_whatsapp_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/whatsapp.php';
    }

    public function render_import_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/google-sync.php';
    }

    public function render_reports_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Reports', 'community-directory' ) . '</h1><p>Coming in Phase 5.</p></div>';
    }

    public function render_settings_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/settings.php';
    }

    public function render_debug_log_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/debug-log.php';
    }

    /**
     * AJAX: Toggle debug logging on/off.
     */
    public function ajax_toggle_debug_logging() {
        check_ajax_referer( 'cd_debug_log_actions', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $current = get_option( 'cd_debug_logging', '1' );
        $new     = ( '1' === $current ) ? '0' : '1';
        update_option( 'cd_debug_logging', $new );
        CD_Logger::reset_cache();

        wp_send_json_success( array(
            'enabled' => ( '1' === $new ),
            'message' => ( '1' === $new )
                ? __( 'Debug logging enabled.', 'community-directory' )
                : __( 'Debug logging disabled.', 'community-directory' ),
        ) );
    }

    /**
     * AJAX: Clear the debug log file.
     */
    public function ajax_clear_debug_log() {
        check_ajax_referer( 'cd_debug_log_actions', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        CD_Logger::clear();
        wp_send_json_success( array( 'message' => __( 'Log cleared.', 'community-directory' ) ) );
    }

    /**
     * AJAX: Refresh log content.
     */
    public function ajax_refresh_debug_log() {
        check_ajax_referer( 'cd_debug_log_actions', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $lines = isset( $_POST['lines'] ) ? absint( $_POST['lines'] ) : 200;
        $lines = min( $lines, 1000 );

        wp_send_json_success( array(
            'content'  => CD_Logger::tail( $lines ),
            'size'     => CD_Logger::get_file_size(),
            'enabled'  => ( '1' === get_option( 'cd_debug_logging', '1' ) ),
        ) );
    }

    /**
     * Download CSV template for member import.
     */
    public function download_csv_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'community-directory' ) );
        }

        $filename = 'member_import_template.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $fp = fopen( 'php://output', 'w' );
        
        // UTF-8 BOM for Excel compatibility
        fputs( $fp, "\xEF\xBB\xBF" );

        $headers = array(
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Address Line 1',
            'City',
            'State',
            'Zip',
            'Date of Birth (YYYY-MM-DD)',
        );
        fputcsv( $fp, $headers );
        
        // Example row
        fputcsv( $fp, array(
            'John',
            'Doe',
            'john.doe@example.com',
            '555-123-4567',
            '123 Main St',
            'New York',
            'NY',
            '10001',
            '1980-01-01',
        ) );
        
        fclose( $fp );
        exit;
    }
}
