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
        echo '<div class="wrap"><h1>' . esc_html__( 'Registrations', 'community-directory' ) . '</h1><p>Coming in Phase 1.</p></div>';
    }

    public function render_applications_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Applications', 'community-directory' ) . '</h1><p>Coming in Phase 2.</p></div>';
    }

    public function render_members_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Members', 'community-directory' ) . '</h1><p>Coming in Phase 3.</p></div>';
    }

    public function render_officers_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Officers Group', 'community-directory' ) . '</h1><p>Coming in Phase 2.</p></div>';
    }

    public function render_whatsapp_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'WhatsApp Groups', 'community-directory' ) . '</h1><p>Coming in Phase 3.</p></div>';
    }

    public function render_import_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Import Members', 'community-directory' ) . '</h1><p>Coming in Phase 2.</p></div>';
    }

    public function render_reports_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Reports', 'community-directory' ) . '</h1><p>Coming in Phase 5.</p></div>';
    }

    public function render_settings_page() {
        include CD_PLUGIN_DIR . 'includes/admin/views/settings.php';
    }
}
