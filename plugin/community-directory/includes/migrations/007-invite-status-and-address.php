<?php
/**
 * Migration 007 — Fix invites.status column and ensure address columns exist.
 *
 * - Adds a status column to cd_invites with default 'pending', plus index.
 * - Backfills status based on used_at / expires_at timestamps.
 * - Adds missing address fields to cd_directory_profiles if they were skipped
 *   when migration 003 failed on some hosts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cd_migration_007() {
    global $wpdb;

    $invites_table   = CD_Database::table( 'invites' );
    $profiles_table  = CD_Database::table( 'directory_profiles' );

    // ── 1) Invites: add status column if missing ──────────────────────────────
    $col = $wpdb->get_var( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
        $wpdb->dbname, $invites_table
    ) );

    if ( ! $col ) {
        $wpdb->query( "ALTER TABLE {$invites_table} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER used_at" );
        $wpdb->query( "ALTER TABLE {$invites_table} ADD INDEX idx_status (status)" );
    }

    // Backfill statuses
    $wpdb->query( "UPDATE {$invites_table} SET status = 'used' WHERE used_at IS NOT NULL" );
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$invites_table} SET status = 'expired' WHERE used_at IS NULL AND expires_at < %s",
        current_time( 'mysql' )
    ) );
    $wpdb->query( "UPDATE {$invites_table} SET status = 'pending' WHERE status IS NULL OR status = ''" );

    // ── 2) Profiles: ensure address fields exist (guard against dbDelta quirks) ──
    $address_columns = array(
        'address_line_1' => "ALTER TABLE {$profiles_table} ADD COLUMN address_line_1 VARCHAR(255) DEFAULT NULL AFTER address_mailing",
        'address_line_2' => "ALTER TABLE {$profiles_table} ADD COLUMN address_line_2 VARCHAR(255) DEFAULT NULL AFTER address_line_1",
        'city'           => "ALTER TABLE {$profiles_table} ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER address_line_2",
        'state'          => "ALTER TABLE {$profiles_table} ADD COLUMN state VARCHAR(100) DEFAULT NULL AFTER city",
        'zip_code'       => "ALTER TABLE {$profiles_table} ADD COLUMN zip_code VARCHAR(20) DEFAULT NULL AFTER state",
        'country'        => "ALTER TABLE {$profiles_table} ADD COLUMN country VARCHAR(100) DEFAULT 'USA' AFTER zip_code",
    );

    foreach ( $address_columns as $name => $sql ) {
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            $wpdb->dbname, $profiles_table, $name
        ) );
        if ( ! $exists ) {
            $wpdb->query( $sql );
        }
    }

    return true;
}
