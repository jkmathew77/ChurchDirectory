<?php
/**
 * Plugin-specific logger.
 *
 * Writes to a dedicated log file so plugin messages
 * are not buried in WordPress's debug.log noise.
 *
 * Log file: wp-content/cd-debug.log
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Logger {

    /** @var string|null Cached log file path. */
    private static $log_file = null;

    /** Max log file size before rotation (5 MB). */
    const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Get the log file path.
     */
    private static function get_log_file() {
        if ( null === self::$log_file ) {
            self::$log_file = WP_CONTENT_DIR . '/cd-debug.log';
        }
        return self::$log_file;
    }

    /**
     * Write a log entry.
     *
     * @param string $level   Log level: DEBUG, INFO, WARN, ERROR.
     * @param string $message The message to log.
     */
    private static function write( $level, $message ) {
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
}
