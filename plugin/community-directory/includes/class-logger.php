<?php
/**
 * Plugin-specific logger.
 *
 * Writes to a dedicated log file so plugin messages
 * are not buried in WordPress's debug.log noise.
 *
 * Log file: wp-content/cd-debug.log
 *
 * Controlled by the 'cd_debug_logging' option (default: on).
 * Toggle via WP Admin → Community → Debug Log.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Logger {

    /** @var string|null Cached log file path. */
    private static $log_file = null;

    /** @var bool|null Cached enabled state. */
    private static $enabled = null;

    /** Max log file size before rotation (5 MB). */
    const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Get the log file path.
     */
    public static function get_log_file() {
        if ( null === self::$log_file ) {
            self::$log_file = WP_CONTENT_DIR . '/cd-debug.log';
        }
        return self::$log_file;
    }

    /**
     * Check if logging is enabled.
     */
    private static function is_enabled() {
        if ( null === self::$enabled ) {
            self::$enabled = ( '1' === get_option( 'cd_debug_logging', '1' ) );
        }
        return self::$enabled;
    }

    /**
     * Reset the cached enabled state (after option update).
     */
    public static function reset_cache() {
        self::$enabled = null;
    }

    /**
     * Write a log entry.
     *
     * @param string $level   Log level: DEBUG, INFO, WARN, ERROR.
     * @param string $message The message to log.
     */
    private static function write( $level, $message ) {
        if ( ! self::is_enabled() ) {
            return;
        }

        $file = self::get_log_file();

        // Auto-rotate if file exceeds max size.
        if ( file_exists( $file ) && filesize( $file ) > self::MAX_SIZE ) {
            $backup = $file . '.1';
            if ( file_exists( $backup ) ) {
                @unlink( $backup );
            }
            @rename( $file, $backup );
        }

        $timestamp = gmdate( 'Y-m-d H:i:s' );
        $entry     = sprintf( "[%s] [%s] %s\n", $timestamp, $level, $message );

        @file_put_contents( $file, $entry, FILE_APPEND | LOCK_EX );
    }

    /**
     * Debug-level message (verbose, for development).
     */
    public static function debug( $message ) {
        self::write( 'DEBUG', $message );
    }

    /**
     * Informational message (normal operations).
     */
    public static function info( $message ) {
        self::write( 'INFO', $message );
    }

    /**
     * Warning (non-critical issue).
     */
    public static function warn( $message ) {
        self::write( 'WARN', $message );
    }

    /**
     * Error (something failed).
     */
    public static function error( $message ) {
        self::write( 'ERROR', $message );
    }

    /**
     * Clear the log file.
     */
    public static function clear() {
        $file = self::get_log_file();
        if ( file_exists( $file ) ) {
            @file_put_contents( $file, '' );
        }
        $backup = $file . '.1';
        if ( file_exists( $backup ) ) {
            @unlink( $backup );
        }
    }

    /**
     * Get log file size in bytes (0 if missing).
     */
    public static function get_file_size() {
        $file = self::get_log_file();
        return file_exists( $file ) ? filesize( $file ) : 0;
    }

    /**
     * Read the last N lines of the log file.
     *
     * @param int $lines Number of lines to return.
     * @return string
     */
    public static function tail( $lines = 200 ) {
        $file = self::get_log_file();
        if ( ! file_exists( $file ) ) {
            return '';
        }

        $content = @file_get_contents( $file );
        if ( false === $content || '' === $content ) {
            return '';
        }

        $all_lines = explode( "\n", rtrim( $content, "\n" ) );
        $total     = count( $all_lines );

        if ( $total <= $lines ) {
            return implode( "\n", $all_lines );
        }

        return implode( "\n", array_slice( $all_lines, -$lines ) );
    }
}
