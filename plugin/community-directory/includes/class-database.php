<?php
/**
 * Database helper — migration runner and table utilities.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Database {

    /**
     * Get the full table name with WP prefix.
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . CD_TABLE_PREFIX . $name;
    }

    /**
     * Detect if MySQL supports native JSON columns.
     */
    public static function supports_json() {
        global $wpdb;
        $version = $wpdb->db_version();

        // MySQL 5.7.8+ supports JSON. MariaDB 10.2.7+ supports JSON.
        if ( strpos( $version, 'MariaDB' ) !== false ) {
            return version_compare( $version, '10.2.7', '>=' );
        }
        return version_compare( $version, '5.7.8', '>=' );
    }

    /**
     * Get the appropriate column type for JSON data.
     */
    public static function json_type() {
        return self::supports_json() ? 'JSON' : 'LONGTEXT';
    }

    /**
     * Run all pending migrations.
     */
    public function run_migrations( $from_version ) {
        $migrations_dir = CD_PLUGIN_DIR . 'includes/migrations/';
        $migration_files = glob( $migrations_dir . '*.php' );

        if ( empty( $migration_files ) ) {
            return;
        }

        sort( $migration_files );

        foreach ( $migration_files as $file ) {
            $filename = basename( $file, '.php' );
            // Extract version number from filename (e.g., "001" from "001-initial-schema")
            $migration_version = substr( $filename, 0, 3 );

            if ( version_compare( $migration_version, $from_version, '>' ) ) {
                require_once $file;

                // Migration functions are named: cd_migration_001, cd_migration_002, etc.
                $function_name = 'cd_migration_' . $migration_version;
                if ( function_exists( $function_name ) ) {
                    $result = call_user_func( $function_name );

                    if ( false === $result ) {
                        // Migration failed — halt and notify admin
                        set_transient( 'cd_migration_error', sprintf(
                            'Migration %s failed. Please check the error log.',
                            $migration_version
                        ), HOUR_IN_SECONDS );
                        return;
                    }

                    // Record successful migration
                    $this->record_migration( $migration_version, $filename );
                }

                update_option( 'cd_db_version', $migration_version );
            }
        }
    }

    /**
     * Record a completed migration in the schema_versions table.
     */
    private function record_migration( $version, $description ) {
        global $wpdb;
        $table = self::table( 'schema_versions' );

        // Table might not exist yet for the very first migration
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
            $wpdb->insert( $table, array(
                'version'     => $version,
                'description' => $description,
                'applied_at'  => current_time( 'mysql' ),
            ) );
        }
    }
}
