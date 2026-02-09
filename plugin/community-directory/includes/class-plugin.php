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

        // Register cron schedules and callbacks
        add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
        CD_Cron::init();

        // Google Contacts OAuth callback
        CD_Google_Contacts::register_ajax();

        // Flush rewrite rules once after plugin update adds new routes
        $this->maybe_flush_rewrites();
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

        $invites_api = new CD_API_Invites();
        $invites_api->register_routes();
    }

    /**
     * Load public-facing functionality (front-end pages).
     */
    private function load_public() {
        add_action( 'init', array( $this, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_community_redirects' ) );
        add_filter( 'template_include', array( $this, 'load_community_template' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_filter( 'body_class', array( $this, 'add_body_classes' ) );
        add_filter( 'document_title_parts', array( $this, 'filter_page_title' ) );

        // Inject Community link into site navigation
        add_filter( 'wp_nav_menu_items', array( $this, 'inject_community_menu_item' ), 10, 2 );
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
        add_rewrite_rule(
            '^' . $base_slug . '/invite/([A-Za-z0-9+/=]+)/?$',
            'index.php?cd_page=invite&cd_invite_email=$matches[1]',
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
        $vars[] = 'cd_invite_email';
        return $vars;
    }

    /**
     * Handle auth redirects only — template loading is done via template_include.
     */
    public function handle_community_redirects() {
        $page = get_query_var( 'cd_page' );
        if ( empty( $page ) ) {
            return;
        }

        $base_slug = get_option( 'cd_base_slug', 'community' );

        // Logged-in members skip landing/login and go to directory
        if ( in_array( $page, array( 'landing', 'login' ), true ) ) {
            if ( is_user_logged_in() && current_user_can( 'cd_member' ) ) {
                wp_safe_redirect( home_url( $base_slug . '/directory/' ) );
                exit;
            }
        }

        // Protected pages require cd_member capability
        if ( in_array( $page, array( 'directory', 'member', 'profile' ), true ) ) {
            if ( ! is_user_logged_in() || ! current_user_can( 'cd_member' ) ) {
                wp_safe_redirect( home_url( $base_slug . '/login/' ) );
                exit;
            }
        }

        // Tell WordPress this is a valid page, not a 404
        global $wp_query;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        status_header( 200 );
    }

    /**
     * Load the correct plugin template for community pages.
     * Uses template_include so pages render inside the active theme.
     */
    public function load_community_template( $template ) {
        $page = get_query_var( 'cd_page' );
        if ( empty( $page ) ) {
            return $template;
        }

        // Send security headers
        $this->send_security_headers();

        $templates = array(
            'landing'     => 'landing.php',
            'login'       => 'login.php',
            'application' => 'application.php',
            'directory'   => 'directory.php',
            'member'      => 'member-profile.php',
            'profile'     => 'edit-profile.php',
            'verify'      => 'verify.php',
            'invite'      => 'invite.php',
        );

        if ( isset( $templates[ $page ] ) ) {
            $plugin_template = CD_PLUGIN_DIR . 'public/views/' . $templates[ $page ];
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Add body classes for community pages (used by theme's body_class()).
     */
    public function add_body_classes( $classes ) {
        $page = get_query_var( 'cd_page' );
        if ( ! empty( $page ) ) {
            $classes[] = 'cd-page';
            $classes[] = 'cd-page-' . sanitize_html_class( $page );
        }
        return $classes;
    }

    /**
     * Filter the document title for community pages.
     */
    public function filter_page_title( $title_parts ) {
        $page = get_query_var( 'cd_page' );
        if ( empty( $page ) ) {
            return $title_parts;
        }

        $titles = array(
            'landing'     => __( 'Community Directory', 'community-directory' ),
            'login'       => __( 'Login', 'community-directory' ),
            'application' => __( 'Become a Member', 'community-directory' ),
            'directory'   => __( 'Directory', 'community-directory' ),
            'member'      => __( 'Member Profile', 'community-directory' ),
            'profile'     => __( 'Edit Profile', 'community-directory' ),
            'verify'      => __( 'Email Verification', 'community-directory' ),
            'invite'      => __( 'Accept Invitation', 'community-directory' ),
        );

        if ( isset( $titles[ $page ] ) ) {
            $title_parts['title'] = $titles[ $page ];
        }

        return $title_parts;
    }

    /**
     * Send HTTP security headers on community pages.
     */
    private function send_security_headers() {
        if ( headers_sent() ) {
            return;
        }

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

        // Pass page-specific data via inline script
        $token = get_query_var( 'cd_token', '' );
        if ( ! empty( $token ) ) {
            wp_add_inline_script( 'community-directory', 'window.cdVerifyToken = ' . wp_json_encode( $token ) . ';', 'before' );
        }

        $member_uuid = get_query_var( 'cd_member_uuid', '' );
        if ( ! empty( $member_uuid ) ) {
            wp_add_inline_script( 'community-directory', 'window.cdMemberUuid = ' . wp_json_encode( $member_uuid ) . ';', 'before' );
        }

        $invite_email = get_query_var( 'cd_invite_email', '' );
        if ( ! empty( $invite_email ) ) {
            wp_add_inline_script( 'community-directory', 'window.cdInviteEmail = ' . wp_json_encode( $invite_email ) . ';', 'before' );
        }

        // Plugin CSS
        wp_enqueue_style(
            'community-directory',
            CD_PLUGIN_URL . 'public/css/community-directory.css',
            array(),
            CD_VERSION
        );
    }

    /**
     * Inject Community menu item into site navigation.
     */
    public function inject_community_menu_item( $items, $args ) {
        $menu_visible = get_option( 'cd_menu_visible', '1' );
        if ( '1' !== $menu_visible ) {
            return $items;
        }

        // Only inject into primary/main menu locations
        $primary_locations = array(
            'primary', 'main', 'main-menu', 'primary-menu',
            'header-menu', 'menu-1', 'primary_navigation',
            'header', 'top', 'top-menu',
        );
        if ( ! empty( $args->theme_location ) && ! in_array( $args->theme_location, $primary_locations, true ) ) {
            return $items;
        }

        $base_slug  = get_option( 'cd_base_slug', 'community' );
        $menu_label = get_option( 'cd_menu_label', 'Community' );
        $url        = home_url( $base_slug . '/' );

        $active_class = '';
        $page = get_query_var( 'cd_page' );
        if ( ! empty( $page ) ) {
            $active_class = ' current-menu-item current_page_item';
        }

        $items .= '<li class="menu-item menu-item-community-directory' . $active_class . '">'
                . '<a href="' . esc_url( $url ) . '">' . esc_html( $menu_label ) . '</a>'
                . '</li>';

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

    /**
     * Flush rewrite rules once when new routes are added in a plugin update.
     * Checks a stored version and flushes if it's behind.
     */
    private function maybe_flush_rewrites() {
        $current_rewrite_version = 2; // Bump this when adding new rewrite rules
        $stored = (int) get_option( 'cd_rewrite_version', 1 );

        if ( $stored < $current_rewrite_version ) {
            add_action( 'admin_init', function () use ( $current_rewrite_version ) {
                flush_rewrite_rules();
                update_option( 'cd_rewrite_version', $current_rewrite_version );
            } );
        }
    }
}
