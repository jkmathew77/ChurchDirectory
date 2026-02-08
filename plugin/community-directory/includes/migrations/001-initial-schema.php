<?php
/**
 * Migration 001 — Initial schema: all 14 database tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cd_migration_001() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $json_type = CD_Database::json_type();

    // Build all table names up front
    $applications       = CD_Database::table( 'applications' );
    $members            = CD_Database::table( 'members' );
    $directory_profiles = CD_Database::table( 'directory_profiles' );
    $households         = CD_Database::table( 'households' );
    $household_members  = CD_Database::table( 'household_members' );
    $invites            = CD_Database::table( 'invites' );
    $audit_log          = CD_Database::table( 'audit_log' );
    $google_sync_log    = CD_Database::table( 'google_sync_log' );
    $officers           = CD_Database::table( 'officers' );
    $push_subscriptions = CD_Database::table( 'push_subscriptions' );
    $whatsapp_groups    = CD_Database::table( 'whatsapp_groups' );
    $household_requests = CD_Database::table( 'household_requests' );
    $deletion_requests  = CD_Database::table( 'deletion_requests' );
    $schema_versions    = CD_Database::table( 'schema_versions' );

    $sql = array();

    // 1. Applications
    $sql[] = "CREATE TABLE {$applications} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        form_data {$json_type} DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending_verification',
        verification_token_hash VARCHAR(64) DEFAULT NULL,
        verification_sent_at DATETIME DEFAULT NULL,
        verified_at DATETIME DEFAULT NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by BIGINT UNSIGNED DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        rejection_reason VARCHAR(100) DEFAULT NULL,
        previous_application_id BIGINT UNSIGNED DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_email (email),
        KEY idx_status (status),
        KEY idx_submitted_at (submitted_at),
        KEY idx_verification_token (verification_token_hash)
    ) {$charset_collate};";

    // 2. Members
    $sql[] = "CREATE TABLE {$members} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        uuid CHAR(36) NOT NULL,
        wp_user_id BIGINT UNSIGNED DEFAULT NULL,
        application_id BIGINT UNSIGNED DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        activated_at DATETIME DEFAULT NULL,
        deactivated_at DATETIME DEFAULT NULL,
        deactivation_reason VARCHAR(100) DEFAULT NULL,
        google_id VARCHAR(255) DEFAULT NULL,
        google_contact_id VARCHAR(255) DEFAULT NULL,
        passkey_credential_id VARCHAR(512) DEFAULT NULL,
        member_since DATE DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_uuid (uuid),
        UNIQUE KEY idx_wp_user_id (wp_user_id),
        KEY idx_email_lookup (application_id),
        KEY idx_status (status),
        KEY idx_google_id (google_id)
    ) {$charset_collate};";

    // 3. Directory Profiles
    $sql[] = "CREATE TABLE {$directory_profiles} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        member_id BIGINT UNSIGNED NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        emails {$json_type} DEFAULT NULL,
        phones {$json_type} DEFAULT NULL,
        address_home TEXT DEFAULT NULL,
        address_mailing TEXT DEFAULT NULL,
        bio VARCHAR(500) DEFAULT NULL,
        avatar_url VARCHAR(500) DEFAULT NULL,
        avatar_source VARCHAR(20) DEFAULT 'initials',
        date_of_birth DATE DEFAULT NULL,
        name_day VARCHAR(100) DEFAULT NULL,
        baptism_date DATE DEFAULT NULL,
        wedding_anniversary DATE DEFAULT NULL,
        occupation VARCHAR(200) DEFAULT NULL,
        employer VARCHAR(200) DEFAULT NULL,
        preferred_contact_method VARCHAR(20) DEFAULT 'email',
        preferred_language VARCHAR(20) DEFAULT 'en',
        emergency_contact_name VARCHAR(200) DEFAULT NULL,
        emergency_contact_phone VARCHAR(30) DEFAULT NULL,
        social_links {$json_type} DEFAULT NULL,
        ministry_tags {$json_type} DEFAULT NULL,
        privacy_settings {$json_type} DEFAULT NULL,
        profile_completion TINYINT UNSIGNED DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_member_id (member_id),
        KEY idx_last_name (last_name),
        KEY idx_first_name (first_name)
    ) {$charset_collate};";

    // 4. Households
    $sql[] = "CREATE TABLE {$households} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(200) NOT NULL,
        primary_address TEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_by BIGINT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_status (status)
    ) {$charset_collate};";

    // 5. Household Members (join table)
    $sql[] = "CREATE TABLE {$household_members} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        household_id BIGINT UNSIGNED NOT NULL,
        member_id BIGINT UNSIGNED NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'other',
        address_override TEXT DEFAULT NULL,
        joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        left_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_household_member (household_id, member_id),
        KEY idx_member_id (member_id),
        KEY idx_role (role)
    ) {$charset_collate};";

    // 6. Invites
    $sql[] = "CREATE TABLE {$invites} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        application_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(255) NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_token_hash (token_hash),
        KEY idx_email (email),
        KEY idx_expires_at (expires_at)
    ) {$charset_collate};";

    // 7. Audit Log
    $sql[] = "CREATE TABLE {$audit_log} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(50) NOT NULL,
        actor_id BIGINT UNSIGNED DEFAULT NULL,
        target_id BIGINT UNSIGNED DEFAULT NULL,
        details {$json_type} DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_event_type (event_type),
        KEY idx_created_at (created_at),
        KEY idx_actor_id (actor_id),
        KEY idx_target_id (target_id)
    ) {$charset_collate};";

    // 8. Google Sync Log
    $sql[] = "CREATE TABLE {$google_sync_log} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sync_type VARCHAR(20) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        contacts_found INT UNSIGNED DEFAULT 0,
        contacts_imported INT UNSIGNED DEFAULT 0,
        contacts_skipped INT UNSIGNED DEFAULT 0,
        contacts_errored INT UNSIGNED DEFAULT 0,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,
        details {$json_type} DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_sync_type (sync_type),
        KEY idx_started_at (started_at)
    ) {$charset_collate};";

    // 9. Officers
    $sql[] = "CREATE TABLE {$officers} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        member_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(255) NOT NULL,
        title VARCHAR(100) DEFAULT NULL,
        term_label VARCHAR(100) DEFAULT NULL,
        added_by BIGINT UNSIGNED DEFAULT NULL,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        removed_at DATETIME DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        KEY idx_member_id (member_id),
        KEY idx_is_active (is_active)
    ) {$charset_collate};";

    // 10. Push Subscriptions
    $sql[] = "CREATE TABLE {$push_subscriptions} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        member_id BIGINT UNSIGNED NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh_key VARCHAR(255) NOT NULL,
        auth_key VARCHAR(255) NOT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_member_id (member_id)
    ) {$charset_collate};";

    // 11. WhatsApp Groups
    $sql[] = "CREATE TABLE {$whatsapp_groups} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(200) NOT NULL,
        description TEXT DEFAULT NULL,
        invite_url VARCHAR(500) NOT NULL,
        icon VARCHAR(50) DEFAULT NULL,
        visibility VARCHAR(20) NOT NULL DEFAULT 'all',
        visibility_tag VARCHAR(100) DEFAULT NULL,
        display_order INT UNSIGNED DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by BIGINT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_is_active (is_active),
        KEY idx_display_order (display_order)
    ) {$charset_collate};";

    // 12. Household Requests
    $sql[] = "CREATE TABLE {$household_requests} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(30) NOT NULL,
        requesting_member_id BIGINT UNSIGNED NOT NULL,
        target_member_id BIGINT UNSIGNED DEFAULT NULL,
        household_id BIGINT UNSIGNED DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reviewed_by BIGINT UNSIGNED DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_status (status),
        KEY idx_requesting_member (requesting_member_id)
    ) {$charset_collate};";

    // 13. Deletion Requests
    $sql[] = "CREATE TABLE {$deletion_requests} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        member_id BIGINT UNSIGNED NOT NULL,
        reason TEXT DEFAULT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        acknowledged_by BIGINT UNSIGNED DEFAULT NULL,
        acknowledged_at DATETIME DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        PRIMARY KEY  (id),
        KEY idx_member_id (member_id),
        KEY idx_status (status)
    ) {$charset_collate};";

    // 14. Schema Versions
    $sql[] = "CREATE TABLE {$schema_versions} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        version VARCHAR(10) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        description VARCHAR(200) DEFAULT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    // Execute all table creation via dbDelta
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    foreach ( $sql as $table_sql ) {
        dbDelta( $table_sql );
    }

    return true;
}
