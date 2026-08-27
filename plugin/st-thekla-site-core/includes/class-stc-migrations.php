<?php
/**
 * Versioned, conservative data migrations for St. Thekla Site Core.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class STC_Migrations {

    private const VERSION_OPTION = 'stc_data_version';
    private const SETTINGS_OPTION = 'stc_settings';
    private const SCHEDULE_OPTION = 'stc_weekly_schedule';
    private const VISIT_OPTION = 'stc_visit_settings';
    private const ERROR_TRANSIENT = 'stc_migration_error';

    public static function maybe_run() {
        $installed = (string) get_option( self::VERSION_OPTION, '002' );
        $target    = defined( 'STC_DATA_VERSION' ) ? (string) STC_DATA_VERSION : '003';

        if ( version_compare( $installed, $target, '>=' ) ) {
            return true;
        }

        $result = self::migrate_to_003();
        if ( is_wp_error( $result ) ) {
            set_transient( self::ERROR_TRANSIENT, $result->get_error_message(), DAY_IN_SECONDS );
            return false;
        }

        update_option( self::VERSION_OPTION, $target, false );
        delete_transient( self::ERROR_TRANSIENT );
        return true;
    }

    public static function render_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $message = get_transient( self::ERROR_TRANSIENT );
        if ( ! $message ) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>'
            . esc_html__( 'St. Thekla Site Core migration paused:', 'st-thekla-site-core' )
            . '</strong> ' . esc_html( $message ) . '</p></div>';
    }

    private static function migrate_to_003() {
        $settings = get_option( self::SETTINGS_OPTION, array() );
        $settings = is_array( $settings ) ? $settings : array();
        $schedule = get_option( self::SCHEDULE_OPTION, array() );
        $schedule = is_array( $schedule ) ? $schedule : array();

        if ( ! self::location_is_known_old_or_new( $settings ) ) {
            return new WP_Error(
                'stc_unexpected_location',
                __( 'The saved location does not match the approved Nyack baseline or the new Sparkill location. No location or schedule data was changed.', 'st-thekla-site-core' )
            );
        }

        if ( ! self::schedule_is_known_old_or_new( $schedule ) ) {
            return new WP_Error(
                'stc_unexpected_schedule',
                __( 'The saved weekly schedule differs from the approved baseline. No location or schedule data was changed.', 'st-thekla-site-core' )
            );
        }

        $settings = array_merge(
            $settings,
            array(
                'church_name'    => 'St. Thekla Malankara Orthodox Church',
                'address_line_1' => 'Sacred Heart Chapel',
                'address_line_2' => '175 Route 340',
                'city'           => 'Sparkill',
                'state'          => 'NY',
                'zip'            => '10976',
                'map_url'        => 'https://www.google.com/maps/search/?api=1&query=175+Route+340+Sparkill+NY+10976',
            )
        );
        foreach ( array( 'phone', 'email', 'donation_url', 'livestream_url' ) as $optional_key ) {
            if ( ! array_key_exists( $optional_key, $settings ) ) {
                $settings[ $optional_key ] = '';
            }
        }

        $visit = get_option( self::VISIT_OPTION, array() );
        $visit = is_array( $visit ) ? $visit : array();
        $visit = array_merge(
            array(
                'visit_image_id'            => 0,
                'parking_map_image_id'      => 0,
                'parking_notes'             => self::default_parking_notes(),
                'move_announcement_enabled' => '1',
                'move_effective_date'        => '2026-08-23',
                'visit_page_url'             => home_url( '/visit-us/' ),
            ),
            $visit
        );

        update_option( self::SETTINGS_OPTION, $settings, false );
        update_option( self::SCHEDULE_OPTION, STC_Weekly_Schedule::default_rows(), false );
        update_option( self::VISIT_OPTION, $visit, false );

        return true;
    }

    private static function location_is_known_old_or_new( $settings ) {
        $address = array(
            self::normalize( isset( $settings['address_line_1'] ) ? $settings['address_line_1'] : '' ),
            self::normalize( isset( $settings['address_line_2'] ) ? $settings['address_line_2'] : '' ),
            self::normalize( isset( $settings['city'] ) ? $settings['city'] : '' ),
            self::normalize( isset( $settings['state'] ) ? $settings['state'] : '' ),
            self::normalize( isset( $settings['zip'] ) ? $settings['zip'] : '' ),
        );

        $old = array( 'st thomas lutheran church', '2 old ox road', 'nyack', 'ny', '10960' );
        $new = array( 'sacred heart chapel', '175 route 340', 'sparkill', 'ny', '10976' );
        $empty = array( '', '', '', '', '' );

        return $address === $old || $address === $new || $address === $empty;
    }

    private static function schedule_is_known_old_or_new( $schedule ) {
        if ( empty( $schedule ) ) {
            return true;
        }

        return self::normalize_schedule( $schedule ) === self::normalize_schedule( self::old_schedule() )
            || self::normalize_schedule( $schedule ) === self::normalize_schedule( STC_Weekly_Schedule::default_rows() );
    }

    private static function old_schedule() {
        return array(
            array( 'time' => '8:20 AM', 'description' => 'Morning Prayers' ),
            array( 'time' => '9:00 AM', 'description' => 'Holy Liturgy' ),
            array( 'time' => '10:10 AM', 'description' => 'Dismissal' ),
            array( 'time' => '10:30 AM', 'description' => 'Refreshments' ),
            array( 'time' => '10:45 AM', 'description' => 'Tree of Life' ),
            array( 'time' => '11:30 AM', 'description' => 'End of Tree of Life' ),
        );
    }

    private static function normalize_schedule( $rows ) {
        $normalized = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $normalized[] = array(
                'time'        => self::normalize( isset( $row['time'] ) ? $row['time'] : '' ),
                'description' => self::normalize( isset( $row['description'] ) ? $row['description'] : '' ),
            );
        }
        return $normalized;
    }

    private static function normalize( $value ) {
        $value = strtolower( trim( (string) $value ) );
        $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
        return trim( (string) $value );
    }

    private static function default_parking_notes() {
        return 'Please use only the designated parking areas shown on the map. The chapel entrance and exit are marked in blue. Please do not park in areas marked in red. Additional parking, St. Martin Hall and restrooms are also identified on the map.';
    }
}
