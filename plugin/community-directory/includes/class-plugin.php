<?php
/**
 * Core plugin class — singleton that bootstraps all components.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Initialize all plugin components.
     */
    public function init() {
        // Check and run database migrations if needed
        $this->check_db_version();

        // Load components
        $this->load_capabilities();
        $this->load_admin();
        $this->load_api();
        $this->load_public();

        // Register cron schedules
        add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
    }

    /**
     * Check if database needs migration.
     */
    private function check_db_version() {
        $installed_version = get_option( 'cd_db_version', '000' );
        if ( version_compare( $installed_version, CD_DB_VERSION, '<' ) ) {
            $db = new CD_Database();
            $db->run_migrations( $installed_version );
        }
    }

    /**
     * Register custom capabilities.
     */
    private function load_capabilities() {
        // Capabilities are added on activation, but we verify on init
        CD_Capabilities::init();
    }

    /**
     * Load admin-facing functionality.
     */
    private function load_admin() {
        if ( is_admin() ) {
            $admin_menu = new CD_Admin_Menu();
            $admin_menu->init();
        }
    }

    /**
     * Load REST API endpoints.
     */
    private function load_api() {
        add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );
    }

    /**
     * Register all REST API routes.
     */
    public function register_api_routes() {
        $applications_api = new CD_API_Applications();
        $applications_api->register_routes();

        $auth_api = new CD_API_Auth();
        $auth_api->register_routes();

        $members_api = new CD_API_Members();
        $members_api->register_routes();

        $admin_api = new CD_API_Admin();
        $admin_api->register_routes();
    }

    /**
     * Load public-facing functionality (front-end pages).
     */
    private function load_public() {
        add_action( 'init', array( $this, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_community_pages' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

        // Menu visibility filter
        add_filter( 'wp_nav_menu_items', array( $this, 'filter_community_menu_item' ), 10, 2 );
    }

    /**
     * Register rewrite rules for /community/ pages.
     */
    public function register_rewrite_rules() {
        $base_slug = get_option( 'cd_base_slug', 'community' );

        add_rewrite_rule(
            '^' . $base_slug . '/?$',
            'index.php?cd_page=landing',
            'top'
        );
        add_rewrite_rule(
            '^' . $base_slug . '/login/?$',
            'index.php?cd_page=login',
            'top'
        );
        add_rewrite_rule(
            '^' . $base_slug . '/apply/?$',
            'index.php?cd_page=application',
            'top'
        );
        add_rewrite_rule(
            '^' . $base_slug . '/directory/?$',
            'index.php?cd_page=directory',
            'top'
        );
        add_rewrite_rule(
            '^' . $base_slug . '/member/([a-f0-9-]+)/?$',
            'index.php?cd_page=member&cd_member_uuid=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^' . $base_slug . '/profile/?$',
            'index.php?cd_page=profile',
            'top'
        );
        add_rewrite_rule(
            '^' . $base_slug . '/verify/([a-f0-9]+)/?$',
            'index.php?cd_page=verify&cd_token=$matches[1]',
            'top'
        );
    }

    /**
     * Register custom query variables.
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'cd_page';
        $vars[] = 'cd_member_uuid';
        $vars[] = 'cd_token';
        return $vars;
    }

    /**
     * Handle front-end page routing.
     */
    public function handle_community_pages() {
        $page = get_query_var( 'cd_page' );
        if ( empty( $page ) ) {
            return;
        }

        // Security headers for all community pages
        $this->send_security_headers();

        $template_dir = CD_PLUGIN_DIR . 'public/views/';

        switch ( $page ) {
            case 'landing':
                if ( is_user_logged_in() && current_user_can( 'cd_member' ) ) {
                    wp_safe_redirect( home_url( get_option( 'cd_base_slug', 'community' ) . '/directory/' ) );
                    exit;
                }
                include $template_dir . 'landing.php';
                exit;

            case 'login':
                if ( is_user_logged_in() && current_user_can( 'cd_member' ) ) {
                    wp_safe_redirect( home_url( get_option( 'cd_base_slug', 'community' ) . '/directory/' ) );
                    exit;
                }
                include $template_dir . 'login.php';
                exit;

            case 'application':
                include $template_dir . 'application.php';
                exit;

            case 'directory':
                if ( ! is_user_logged_in() || ! current_user_can( 'cd_member' ) ) {
                    wp_safe_redirect( home_url( get_option( 'cd_base_slug', 'community' ) . '/login/' ) );
                    exit;
                }
                include $template_dir . 'directory.php';
                exit;

            case 'member':
                if ( ! is_user_logged_in() || ! current_user_can( 'cd_member' ) ) {
                    wp_safe_redirect( home_url( get_option( 'cd_base_slug', 'community' ) . '/login/' ) );
                    exit;
                }
                include $template_dir . 'member-profile.php';
                exit;

            case 'profile':
                if ( ! is_user_logged_in() || ! current_user_can( 'cd_member' ) ) {
                    wp_safe_redirect( home_url( get_option( 'cd_base_slug', 'community' ) . '/login/' ) );
                    exit;
                }
                include $template_dir . 'edit-profile.php';
                exit;

            case 'verify':
                include $template_dir . 'verify.php';
                exit;
        }
    }

    /**
     * Send HTTP security headers on community pages.
     */
    private function send_security_headers() {
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: DENY' );
        header( 'Referrer-Policy: no-referrer' );
        header( 'Permissions-Policy: geolocation=(), camera=(), microphone=()' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
        header( 'Pragma: no-cache' );

        if ( is_ssl() ) {
            header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
        }
    }

    /**
     * Enqueue public CSS and JS on community pages only.
     */
    public function enqueue_public_assets() {
        $page = get_query_var( 'cd_page' );
        if ( empty( $page ) ) {
            return;
        }

        // Alpine.js (vendored)
        wp_enqueue_script(
            'alpinejs',
            CD_PLUGIN_URL . 'public/js/alpine.min.js',
            array(),
            '3.14.3',
            array( 'strategy' => 'defer' )
        );

        // Plugin JS
        wp_enqueue_script(
            'community-directory',
            CD_PLUGIN_URL . 'public/js/community-directory.js',
            array( 'alpinejs' ),
            CD_VERSION,
            true
        );

        // Localize script with API config
        wp_localize_script( 'community-directory', 'cdConfig', array(
            'apiUrl'   => esc_url_raw( rest_url( CD_API_NAMESPACE ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'baseUrl'  => esc_url( home_url( get_option( 'cd_base_slug', 'community' ) ) ),
            'isLoggedIn' => is_user_logged_in(),
        ) );

        // Plugin CSS
        wp_enqueue_style(
            'community-directory',
            CD_PLUGIN_URL . 'public/css/community-directory.css',
            array(),
            CD_VERSION
        );
    }

    /**
     * Filter community menu item visibility based on admin toggle.
     */
    public function filter_community_menu_item( $items, $args ) {
        $menu_visible = get_option( 'cd_menu_visible', '1' );
        if ( '0' === $menu_visible ) {
            // Remove community menu item by filtering it out
            $base_slug = get_option( 'cd_base_slug', 'community' );
            $pattern = '/<li[^>]*>.*?<a[^>]*href=["\'][^"\']*\/' . preg_quote( $base_slug, '/' ) . '\/?["\'][^>]*>.*?<\/a>.*?<\/li>/is';
            $items = preg_replace( $pattern, '', $items );
        }
        return $items;
    }

    /**
     * Register custom cron schedules.
     */
    public function register_cron_schedules( $schedules ) {
        $schedules['twice_daily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __( 'Twice Daily', 'community-directory' ),
        );
        return $schedules;
    }
}
