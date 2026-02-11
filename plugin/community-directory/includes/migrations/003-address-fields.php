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

    // Add the new address fields individually (safer than dbDelta on some hosts)
    $address_columns = array(
        'address_line_1' => "ALTER TABLE {$profiles_table} ADD COLUMN address_line_1 VARCHAR(255) DEFAULT NULL",
        'address_line_2' => "ALTER TABLE {$profiles_table} ADD COLUMN address_line_2 VARCHAR(255) DEFAULT NULL",
        'city'           => "ALTER TABLE {$profiles_table} ADD COLUMN city VARCHAR(100) DEFAULT NULL",
        'state'          => "ALTER TABLE {$profiles_table} ADD COLUMN state VARCHAR(100) DEFAULT NULL",
        'zip_code'       => "ALTER TABLE {$profiles_table} ADD COLUMN zip_code VARCHAR(20) DEFAULT NULL",
        'country'        => "ALTER TABLE {$profiles_table} ADD COLUMN country VARCHAR(100) DEFAULT 'USA'",
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
