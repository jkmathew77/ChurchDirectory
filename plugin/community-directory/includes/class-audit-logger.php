<?php
/**
 * Audit logger — records all sensitive actions to the audit_log table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Audit_Logger {

    // Event type constants
    const APPLICATION_SUBMITTED     = 'application_submitted';
    const APPLICATION_VERIFIED      = 'application_verified';
    const APPLICATION_APPROVED      = 'application_approved';
    const APPLICATION_REJECTED      = 'application_rejected';
    const VERIFICATION_SENT         = 'verification_sent';
    const VERIFICATION_RESENT       = 'verification_resent';
    const INVITE_SENT               = 'invite_sent';
    const INVITE_RESENT             = 'invite_resent';
    const MEMBER_ACTIVATED          = 'member_activated';
    const MEMBER_DEACTIVATED        = 'member_deactivated';
    const MEMBER_SELF_DEACTIVATED   = 'member_self_deactivated';
    const MEMBER_REACTIVATED        = 'member_reactivated';
    const DELETION_REQUESTED        = 'deletion_requested';
    const DELETION_PROCESSED        = 'deletion_processed';
    const PROFILE_UPDATED           = 'profile_updated';
    const PASSWORD_RESET_REQUESTED  = 'password_reset_requested';
    const PASSWORD_RESET_COMPLETED  = 'password_reset_completed';
    const LOGIN_SUCCESS             = 'login_success';
    const LOGIN_FAILED              = 'login_failed';
    const GOOGLE_LINKED             = 'google_linked';
    const GOOGLE_UNLINKED           = 'google_unlinked';
    const HOUSEHOLD_CREATED         = 'household_created';
    const HOUSEHOLD_MEMBER_ADDED    = 'household_member_added';
    const HOUSEHOLD_MEMBER_REMOVED  = 'household_member_removed';
    const HOUSEHOLD_ROLE_CHANGED    = 'household_role_changed';
    const HOUSEHOLD_MERGED          = 'household_merged';
    const HOUSEHOLD_SPINOFF         = 'household_spinoff';
    const HOUSEHOLD_LEAVE           = 'household_leave';
    const HOUSEHOLD_TRANSFER_HEAD   = 'household_transfer_head';
    const HOUSEHOLD_MERGE_REQUESTED = 'household_merge_requested';
    const HOUSEHOLD_MERGE_APPROVED  = 'household_merge_approved';
    const HOUSEHOLD_MERGE_DENIED    = 'household_merge_denied';
    const OFFICER_ADDED             = 'officer_added';
    const OFFICER_REMOVED           = 'officer_removed';
    const OFFICER_ROTATION          = 'officer_rotation';
    const MENU_VISIBILITY_CHANGED   = 'menu_visibility_changed';
    const DATA_EXPORTED             = 'data_exported';
    const DATA_IMPORTED             = 'data_imported';
    const PROFILES_MERGED           = 'profiles_merged';
    const GOOGLE_SYNC_RUN           = 'google_sync_run';
    const BULK_OPERATION            = 'bulk_operation';
    const SETTINGS_CHANGED          = 'settings_changed';
    const BOT_DETECTED              = 'bot_detected';

    /**
     * Log an event.
     *
     * @param string   $event_type One of the constants above.
     * @param int|null $actor_id   WP user ID of the person performing the action. Null for system actions.
     * @param int|null $target_id  The target entity ID (member, application, etc.).
     * @param array    $details    Additional context as key-value pairs.
     */
    public static function log( $event_type, $actor_id = null, $target_id = null, $details = array() ) {
        global $wpdb;

        $table = CD_Database::table( 'audit_log' );

        $wpdb->insert( $table, array(
            'event_type' => sanitize_text_field( $event_type ),
            'actor_id'   => $actor_id ? absint( $actor_id ) : null,
            'target_id'  => $target_id ? absint( $target_id ) : null,
            'details'    => ! empty( $details ) ? wp_json_encode( $details ) : null,
            'ip_address' => self::get_client_ip(),
            'created_at' => current_time( 'mysql' ),
        ), array( '%s', '%d', '%d', '%s', '%s', '%s' ) );
    }

    /**
     * Get the client IP address, respecting proxy headers safely.
     */
    private static function get_client_ip() {
        // Direct connection IP is most reliable
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';

        // Validate it's a proper IP
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return '0.0.0.0';
    }
}
