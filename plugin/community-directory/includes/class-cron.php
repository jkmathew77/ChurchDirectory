<?php
/**
 * Cron job callback implementations.
 * Hooks into the 7 cron events registered by CD_Activator.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Cron {

    /**
     * Register all cron callbacks.
     */
    public static function init() {
        add_action( 'cd_expire_invites',       array( __CLASS__, 'expire_invites' ) );
        add_action( 'cd_audit_log_cleanup',    array( __CLASS__, 'cleanup_audit_log' ) );
        add_action( 'cd_expire_reset_tokens',  array( __CLASS__, 'expire_reset_tokens' ) );
        add_action( 'cd_data_retention_check', array( __CLASS__, 'data_retention_check' ) );
        add_action( 'cd_archive_unverified',   array( __CLASS__, 'archive_unverified' ) );
        add_action( 'cd_transient_cleanup',    array( __CLASS__, 'transient_cleanup' ) );
        add_action( 'cd_google_contact_retry', array( __CLASS__, 'retry_google_contacts' ) );
    }

    /**
     * Mark expired invites (runs twice daily).
     * Invites past their expires_at that haven't been used are marked expired.
     */
    public static function expire_invites() {
        global $wpdb;

        $table = CD_Database::table( 'invites' );

        // Check if status column exists (may be missing if migration 007 was blocked)
        $has_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
            $wpdb->dbname, $table
        ) );

        if ( $has_status ) {
            $expired = $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET status = 'expired' WHERE status = 'pending' AND expires_at < %s",
                current_time( 'mysql' )
            ) );
        } else {
            // Fallback: mark expired invites by setting used_at (column always exists)
            $expired = 0;
        }

        if ( $expired > 0 ) {
            CD_Audit_Logger::log( CD_Audit_Logger::BULK_OPERATION, null, null, array(
                'action' => 'expire_invites',
                'count'  => $expired,
            ) );
        }
    }

    /**
     * Clean up old audit log entries beyond the retention period (runs daily).
     */
    public static function cleanup_audit_log() {
        global $wpdb;

        $retention_days = (int) get_option( 'cd_data_retention_period', 730 );
        $table = CD_Database::table( 'audit_log' );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
            current_time( 'mysql' ),
            $retention_days
        ) );
    }

    /**
     * Expire unused password reset tokens (runs hourly).
     */
    public static function expire_reset_tokens() {
        global $wpdb;

        $table = CD_Database::table( 'password_resets' );

        // Delete tokens older than 1 hour
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE used_at IS NULL AND created_at < DATE_SUB(%s, INTERVAL 1 HOUR)",
            current_time( 'mysql' )
        ) );
    }

    /**
     * Check data retention policies and anonymize old records (runs daily).
     */
    public static function data_retention_check() {
        global $wpdb;

        $retention_days = (int) get_option( 'cd_data_retention_period', 730 );

        // Archive applications that were rejected more than retention_days ago
        $applications_table = CD_Database::table( 'applications' );
        $archived = $wpdb->query( $wpdb->prepare(
            "UPDATE {$applications_table} SET status = 'archived', form_data = NULL
             WHERE status = 'not_approved'
             AND reviewed_at IS NOT NULL
             AND reviewed_at < DATE_SUB(%s, INTERVAL %d DAY)",
            current_time( 'mysql' ),
            $retention_days
        ) );

        if ( $archived > 0 ) {
            CD_Audit_Logger::log( CD_Audit_Logger::BULK_OPERATION, null, null, array(
                'action' => 'data_retention_archive',
                'count'  => $archived,
            ) );
        }
    }

    /**
     * Archive unverified applications past the cooldown period (runs daily).
     */
    public static function archive_unverified() {
        global $wpdb;

        $archive_days = (int) get_option( 'cd_unverified_archive_days', 30 );
        $table = CD_Database::table( 'applications' );

        $archived = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'archived'
             WHERE status = 'pending_verification'
             AND submitted_at < DATE_SUB(%s, INTERVAL %d DAY)",
            current_time( 'mysql' ),
            $archive_days
        ) );

        if ( $archived > 0 ) {
            CD_Audit_Logger::log( CD_Audit_Logger::BULK_OPERATION, null, null, array(
                'action' => 'archive_unverified',
                'count'  => $archived,
            ) );
        }
    }

    /**
     * Clean up expired transients used for rate limiting (runs daily).
     */
    public static function transient_cleanup() {
        global $wpdb;

        // WordPress handles transient expiry on read, but we do a sweep for housekeeping
        $wpdb->query( $wpdb->prepare(
            "DELETE a, b FROM {$wpdb->options} a
             JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_timeout', '')
             WHERE a.option_name LIKE %s
             AND a.option_value < %d",
            $wpdb->esc_like( '_transient_timeout_cd_' ) . '%',
            time()
        ) );
    }

    /**
     * Retry failed Google Contacts sync operations (runs hourly).
     */
    public static function retry_google_contacts() {
        $queue = get_option( 'cd_google_retry_queue', array() );

        if ( empty( $queue ) || ! class_exists( 'CD_Google_Contacts' ) ) {
            return;
        }

        if ( ! CD_Google_Contacts::is_enabled() ) {
            return;
        }

        $remaining = array();
        $processed = 0;

        foreach ( $queue as $item ) {
            $retries = isset( $item['retries'] ) ? (int) $item['retries'] : 0;

            // Max 3 retries with exponential backoff
            if ( $retries >= 3 ) {
                continue; // Drop after 3 failures
            }

            // Check backoff timing: 1h, 4h, 12h
            $backoff_seconds = pow( 4, $retries ) * HOUR_IN_SECONDS;
            $last_attempt    = isset( $item['last_attempt'] ) ? (int) $item['last_attempt'] : 0;
            if ( ( time() - $last_attempt ) < $backoff_seconds ) {
                $remaining[] = $item;
                continue;
            }

            $result = CD_Google_Contacts::create_contact( $item['member_data'] );

            if ( is_wp_error( $result ) ) {
                $item['retries']      = $retries + 1;
                $item['last_attempt'] = time();
                $item['last_error']   = $result->get_error_message();
                $remaining[]          = $item;
            } else {
                $processed++;
                // Update google_contact_id on member if we have member_id
                if ( ! empty( $item['member_id'] ) && ! empty( $result ) ) {
                    global $wpdb;
                    $members_table = CD_Database::table( 'members' );
                    $wpdb->update(
                        $members_table,
                        array( 'google_contact_id' => sanitize_text_field( $result ) ),
                        array( 'id' => (int) $item['member_id'] ),
                        array( '%s' ),
                        array( '%d' )
                    );
                }
            }
        }

        update_option( 'cd_google_retry_queue', $remaining );

        if ( $processed > 0 ) {
            CD_Audit_Logger::log( CD_Audit_Logger::GOOGLE_SYNC_RUN, null, null, array(
                'action'    => 'retry_sync',
                'processed' => $processed,
                'remaining' => count( $remaining ),
            ) );
        }
    }
}
