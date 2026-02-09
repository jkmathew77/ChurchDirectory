<?php
/**
 * Migration 002 — Add password_resets table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cd_migration_002() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table = CD_Database::table( 'password_resets' );

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_token_hash (token_hash),
        KEY idx_user_id (user_id),
        KEY idx_created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    return true;
}
