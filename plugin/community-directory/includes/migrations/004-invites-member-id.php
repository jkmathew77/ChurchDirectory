<?php
/**
 * Migration 004 — Update invites table.
 * 
 * 1. Add member_id column (nullable) to link invites directly to member records.
 * 2. Make application_id nullable (to support invites for imported members who have no application).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cd_migration_004() {
    global $wpdb;

    $invites_table = CD_Database::table( 'invites' );

    // 1. Add member_id column if it doesn't exist
    $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$wpdb->dbname}' AND TABLE_NAME = '{$invites_table}' AND COLUMN_NAME = 'member_id'" );
    if ( empty( $row ) ) {
        $wpdb->query( "ALTER TABLE {$invites_table} ADD COLUMN member_id BIGINT UNSIGNED DEFAULT NULL AFTER application_id" );
        $wpdb->query( "ALTER TABLE {$invites_table} ADD INDEX idx_member_id (member_id)" );
    }

    // 2. Make application_id nullable
    // We check if it's currently NOT NULL (simplified check: just run the modify)
    $wpdb->query( "ALTER TABLE {$invites_table} MODIFY COLUMN application_id BIGINT UNSIGNED DEFAULT NULL" );

    return true;
}
