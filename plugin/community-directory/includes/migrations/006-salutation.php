<?php
/**
 * Migration 006 — Add salutation column to directory_profiles.
 *
 * Stores optional prefix/title (Mr, Mrs, Fr., Dr, etc.).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cd_migration_006() {
    global $wpdb;

    $profiles_table = CD_Database::table( 'directory_profiles' );

    // Add salutation column if it doesn't exist
    $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$wpdb->dbname}' AND TABLE_NAME = '{$profiles_table}' AND COLUMN_NAME = 'salutation'" );
    if ( empty( $row ) ) {
        $wpdb->query( "ALTER TABLE {$profiles_table} ADD COLUMN salutation VARCHAR(20) DEFAULT NULL AFTER member_id" );
    }

    return true;
}
