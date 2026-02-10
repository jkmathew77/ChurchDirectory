<?php
/**
 * Core Members class.
 * Handles member retrieval and ID lookups.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Members {

    /**
     * Get Member ID by WP User ID.
     *
     * @param int $user_id WP User ID.
     * @return int|false Member ID or false if not found.
     */
    public static function get_member_id_by_user_id( $user_id ) {
        global $wpdb;
        $table = CD_Database::table( 'members' );

        $member_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE wp_user_id = %d AND status = 'active'",
            $user_id
        ) );

        return $member_id ? (int) $member_id : false;
    }

    /**
     * Get Member by Member ID (with Profile).
     *
     * @param int $member_id Member ID.
     * @return object|false Member object or false.
     */
    public static function get_member( $member_id ) {
        global $wpdb;

        $members_table  = CD_Database::table( 'members' );
        $profiles_table = CD_Database::table( 'directory_profiles' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.uuid, p.*
             FROM {$members_table} m
             LEFT JOIN {$profiles_table} p ON m.id = p.member_id
             WHERE m.id = %d AND m.status = 'active'",
            $member_id
        ) );

        if ( ! $row ) {
            return false;
        }

        // Decode all JSON fields
        $row->emails        = json_decode( $row->emails, true ) ?: array();
        $row->phones        = json_decode( $row->phones, true ) ?: array();
        $row->social_links  = json_decode( $row->social_links, true ) ?: array();
        $row->ministry_tags = json_decode( $row->ministry_tags, true ) ?: array();
        $row->privacy_settings = json_decode( $row->privacy_settings, true ) ?: array();

        return $row;
    }

    /**
     * Get Member by UUID.
     *
     * @param string $uuid Member UUID.
     * @return object|false
     */
    public static function get_member_by_uuid( $uuid ) {
        global $wpdb;
        $table = CD_Database::table( 'members' );
        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE uuid = %s", $uuid ) );
        
        if ( $id ) {
            return self::get_member( (int) $id );
        }
        return false;
    }

}
