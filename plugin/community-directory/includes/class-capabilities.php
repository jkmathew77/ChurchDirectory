<?php
/**
 * Custom roles and capabilities for the Community Directory.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Capabilities {

    /**
     * All custom capabilities defined by the plugin.
     */
    const CAPS = array(
        'cd_member'    => 'Community Directory Member — can view directory and edit own profile',
        'cd_officer'   => 'Church Officer — can review and approve/reject applications',
        'cd_secretary' => 'Secretary/Approver — can manage applications, invites, and view logs',
        'cd_admin'     => 'Directory Admin — full plugin management',
    );

    /**
     * Initialize — hide WordPress backend from directory-only members.
     */
    public static function init() {
        // Hide the WP admin bar for users who only have cd_member (not admins/editors)
        add_filter( 'show_admin_bar', array( __CLASS__, 'maybe_hide_admin_bar' ) );

        // Block wp-admin access for directory-only members
        add_action( 'admin_init', array( __CLASS__, 'redirect_members_from_admin' ) );

        // Redirect wp-login.php to community login for non-admins
        add_action( 'login_init', array( __CLASS__, 'redirect_wp_login' ) );
    }

    /**
     * Hide the WordPress admin bar for users who are only cd_member
     * (not administrators, editors, or other WP roles with dashboard access).
     */
    public static function maybe_hide_admin_bar( $show ) {
        if ( ! is_user_logged_in() ) {
            return $show;
        }

        // If the user can manage options or edit posts, they're a real WP admin/editor — keep the bar
        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
            return $show;
        }

        // Directory-only members: hide the admin bar
        if ( current_user_can( 'cd_member' ) ) {
            return false;
        }

        return $show;
    }

    /**
     * Redirect directory-only members away from wp-admin to the community directory.
     * Allows AJAX requests to pass through (needed for some WP functionality).
     */
    public static function redirect_members_from_admin() {
        if ( wp_doing_ajax() ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        // If user has real WP admin capabilities, let them through
        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
            return;
        }

        // Directory-only members get redirected to the community directory
        if ( current_user_can( 'cd_member' ) ) {
            $base_slug = get_option( 'cd_base_slug', 'community' );
            wp_safe_redirect( home_url( $base_slug . '/directory/' ) );
            exit;
        }
    }

    /**
     * Redirect wp-login.php to the community login page.
     * Only for regular login attempts — preserves admin login, logout actions, and password resets.
     */
    public static function redirect_wp_login() {
        // Allow logout, password reset, registration, and other actions through
        $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : 'login';
        $allowed_actions = array( 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass', 'confirmaction' );

        if ( in_array( $action, $allowed_actions, true ) ) {
            return;
        }

        // If already logged in as admin, let them through to wp-admin
        if ( is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) ) {
            return;
        }

        // Allow direct wp-admin access via a query param (escape hatch for admins)
        if ( isset( $_GET['wp-admin'] ) ) {
            return;
        }

        $base_slug = get_option( 'cd_base_slug', 'community' );
        wp_safe_redirect( home_url( $base_slug . '/login/' ) );
        exit;
    }

    /**
     * Add all custom capabilities to the administrator role on activation.
     */
    public static function add_caps() {
        $admin_role = get_role( 'administrator' );
        if ( ! empty( $admin_role ) && is_object( $admin_role ) ) {
            foreach ( array_keys( self::CAPS ) as $cap ) {
                $admin_role->add_cap( $cap, true );
            }
        }
    }

    /**
     * Remove all custom capabilities from all roles on uninstall.
     */
    public static function remove_caps() {
        global $wp_roles;

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        foreach ( $wp_roles->roles as $role_name => $role_info ) {
            $role = get_role( $role_name );
            if ( $role ) {
                foreach ( array_keys( self::CAPS ) as $cap ) {
                    $role->remove_cap( $cap );
                }
            }
        }
    }

    /**
     * Grant a specific capability to a user.
     */
    public static function grant_cap( $user_id, $cap ) {
        $user = get_user_by( 'id', $user_id );
        if ( $user && array_key_exists( $cap, self::CAPS ) ) {
            $user->add_cap( $cap );
        }
    }

    /**
     * Revoke a specific capability from a user.
     */
    public static function revoke_cap( $user_id, $cap ) {
        $user = get_user_by( 'id', $user_id );
        if ( $user && array_key_exists( $cap, self::CAPS ) ) {
            $user->remove_cap( $cap );
        }
    }
}
