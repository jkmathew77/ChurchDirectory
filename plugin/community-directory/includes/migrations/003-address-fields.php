<?php
/**
 * Migration 003 — Add separate address fields to directory_profiles.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cd_migration_003() {
    global $wpdb;

    $profiles_table = CD_Database::table( 'directory_profiles' );
    $charset_collate = $wpdb->get_charset_collate();

    // We use dbDelta to add columns
    // Note: We are keeping 'address_home' for now as a fallback or full string, 
    // but the new fields will take precedence in the UI.
    
    $sql = "CREATE TABLE {$profiles_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        member_id BIGINT UNSIGNED NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        emails LONGTEXT DEFAULT NULL,
        phones LONGTEXT DEFAULT NULL,
        address_home TEXT DEFAULT NULL,
        address_mailing TEXT DEFAULT NULL,
        
        address_line_1 VARCHAR(255) DEFAULT NULL,
        address_line_2 VARCHAR(255) DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        state VARCHAR(100) DEFAULT NULL,
        zip_code VARCHAR(20) DEFAULT NULL,
        country VARCHAR(100) DEFAULT 'USA',
        
        bio VARCHAR(500) DEFAULT NULL,
        avatar_url VARCHAR(500) DEFAULT NULL,
        avatar_source VARCHAR(20) DEFAULT 'initials',
        date_of_birth TEXT DEFAULT NULL,
        name_day VARCHAR(100) DEFAULT NULL,
        baptism_date DATE DEFAULT NULL,
        wedding_anniversary DATE DEFAULT NULL,
        occupation VARCHAR(200) DEFAULT NULL,
        employer VARCHAR(200) DEFAULT NULL,
        preferred_contact_method VARCHAR(20) DEFAULT 'email',
        preferred_language VARCHAR(20) DEFAULT 'en',
        emergency_contact_name VARCHAR(255) DEFAULT NULL,
        emergency_contact_phone VARCHAR(255) DEFAULT NULL,
        social_links LONGTEXT DEFAULT NULL,
        ministry_tags LONGTEXT DEFAULT NULL,
        privacy_settings LONGTEXT DEFAULT NULL,
        profile_completion TINYINT UNSIGNED DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_member_id (member_id),
        KEY idx_last_name (last_name),
        KEY idx_first_name (first_name)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    return true;
}
