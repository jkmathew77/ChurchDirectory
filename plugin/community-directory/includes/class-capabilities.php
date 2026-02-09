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
     * Initialize — ensure administrator has all caps.
     */
    public static function init() {
        // Nothing to do on every request — caps are added on activation
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
