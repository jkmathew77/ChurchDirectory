<?php
/**
 * Migration 005 — Add columns to household_requests for merge workflow.
 *
 * 1. Add target_household_id column for merge destination.
 * 2. Add notes column for admin review notes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cd_migration_005() {
    global $wpdb;

    $hr_table = CD_Database::table( 'household_requests' );

    // 1. Add target_household_id column if it doesn't exist
    $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$wpdb->dbname}' AND TABLE_NAME = '{$hr_table}' AND COLUMN_NAME = 'target_household_id'" );
    if ( empty( $row ) ) {
        $wpdb->query( "ALTER TABLE {$hr_table} ADD COLUMN target_household_id BIGINT UNSIGNED DEFAULT NULL AFTER household_id" );
        $wpdb->query( "ALTER TABLE {$hr_table} ADD INDEX idx_target_household (target_household_id)" );
    }

    // 2. Add notes column if it doesn't exist
    $row2 = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$wpdb->dbname}' AND TABLE_NAME = '{$hr_table}' AND COLUMN_NAME = 'notes'" );
    if ( empty( $row2 ) ) {
        $wpdb->query( "ALTER TABLE {$hr_table} ADD COLUMN notes TEXT DEFAULT NULL AFTER status" );
    }

    return true;
}
