<?php
/**
 * WP-CLI eval-file script: produce a non-PII diagnostic report for the
 * St. Thekla WordPress recovery.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

global $wpdb;

function stx_table_exists( $table_name ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
}

function stx_table_columns( $table_name ) {
    global $wpdb;
    if ( ! stx_table_exists( $table_name ) ) {
        return array();
    }
    return $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
}

function stx_nested_value( $array, $path, $default = null ) {
    $value = $array;
    foreach ( $path as $key ) {
        if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
            return $default;
        }
        $value = $value[ $key ];
    }
    return $value;
}

function stx_mask_email( $email ) {
    $email = (string) $email;
    if ( false === strpos( $email, '@' ) ) {
        return '';
    }
    list( , $domain ) = explode( '@', $email, 2 );
    return '***@' . strtolower( $domain );
}

function stx_redact_text( $text ) {
    $text = (string) $text;
    $text = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text );
    $text = preg_replace( '#https?://\S+#i', '[url]', $text );
    $text = preg_replace( '/\b[A-Za-z0-9_\-]{32,}\b/', '[token]', $text );
    $text = str_replace( ABSPATH, '[WP_ROOT]/', $text );
    $home = getenv( 'HOME' );
    if ( $home ) {
        $text = str_replace( rtrim( $home, '/' ) . '/', '[HOME]/', $text );
    }
    $text = preg_replace( '/^\[[^\]]+\]\s*/', '', $text );
    return trim( $text );
}

function stx_log_report( $path ) {
    $report = array(
        'path'       => $path,
        'exists'     => is_file( $path ),
        'size_bytes' => is_file( $path ) ? (int) filesize( $path ) : 0,
        'modified'   => is_file( $path ) ? gmdate( 'c', filemtime( $path ) ) : null,
        'signatures' => array(),
    );

    if ( ! is_file( $path ) || ! is_readable( $path ) ) {
        return $report;
    }

    $max_bytes = 2 * 1024 * 1024;
    $size      = filesize( $path );
    $handle    = fopen( $path, 'rb' );
    if ( ! $handle ) {
        return $report;
    }

    if ( $size > $max_bytes ) {
        fseek( $handle, -$max_bytes, SEEK_END );
        fgets( $handle );
    }

    $counts = array();
    while ( false !== ( $line = fgets( $handle ) ) ) {
        if ( ! preg_match( '/Fatal error|Uncaught|Parse error|Deprecated|Warning|Notice/i', $line ) ) {
            continue;
        }
        $signature = stx_redact_text( $line );
        $signature = preg_replace( '/\s+/', ' ', $signature );
        $signature = substr( $signature, 0, 400 );
        if ( '' === $signature ) {
            continue;
        }
        if ( ! isset( $counts[ $signature ] ) ) {
            $counts[ $signature ] = 0;
        }
        $counts[ $signature ]++;
    }
    fclose( $handle );

    arsort( $counts );
    $counts = array_slice( $counts, 0, 40, true );
    foreach ( $counts as $signature => $count ) {
        $report['signatures'][] = array(
            'count'     => (int) $count,
            'signature' => $signature,
        );
    }

    return $report;
}

function stx_plugin_info_for_directory( $directory ) {
    $result = array(
        'directory'  => basename( $directory ),
        'is_symlink' => is_link( $directory ),
        'size_bytes' => 0,
        'main_file'  => null,
        'version'    => null,
        'sha256'     => null,
    );

    if ( ! is_dir( $directory ) ) {
        return $result;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
    );
    foreach ( $iterator as $file ) {
        if ( $file->isFile() ) {
            $result['size_bytes'] += $file->getSize();
        }
    }

    $candidates = glob( rtrim( $directory, '/' ) . '/*community-directory.php' );
    if ( empty( $candidates ) ) {
        $candidates = glob( rtrim( $directory, '/' ) . '/*/community-directory.php' );
    }
    if ( ! empty( $candidates ) ) {
        $main_file = $candidates[0];
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data                = get_plugin_data( $main_file, false, false );
        $result['main_file'] = str_replace( WP_PLUGIN_DIR . '/', '', $main_file );
        $result['version']   = isset( $data['Version'] ) ? $data['Version'] : null;
        $result['sha256']    = hash_file( 'sha256', $main_file );
    }

    return $result;
}

$site_settings = array(
    'users_can_register'      => (string) get_option( 'users_can_register', '0' ),
    'default_comment_status'  => (string) get_option( 'default_comment_status', '' ),
    'default_ping_status'     => (string) get_option( 'default_ping_status', '' ),
    'default_role'            => (string) get_option( 'default_role', '' ),
    'wp_debug'                => defined( 'WP_DEBUG' ) ? (bool) WP_DEBUG : false,
    'wp_debug_log'            => defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false,
    'wp_debug_display'        => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : false,
    'environment_type'        => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : null,
);

$user_ids          = get_users( array( 'fields' => 'ID', 'orderby' => 'ID', 'order' => 'ASC' ) );
$role_counts       = array();
$capability_counts = array(
    'cd_member'    => 0,
    'cd_officer'   => 0,
    'cd_secretary' => 0,
    'cd_admin'     => 0,
    'manage_options' => 0,
);
$registration_months = array();
$no_role_count       = 0;

$members_table = $wpdb->prefix . 'cd_members';
$member_links  = array();
$directory_member_rows = 0;
$directory_orphans     = 0;
if ( stx_table_exists( $members_table ) ) {
    $member_rows = $wpdb->get_results( "SELECT id, wp_user_id, status FROM `{$members_table}`", ARRAY_A );
    $directory_member_rows = count( $member_rows );
    foreach ( $member_rows as $member_row ) {
        $wp_user_id = (int) $member_row['wp_user_id'];
        if ( $wp_user_id > 0 ) {
            $member_links[ $wp_user_id ] = array(
                'member_id' => (int) $member_row['id'],
                'status'    => (string) $member_row['status'],
            );
        } else {
            $directory_orphans++;
        }
    }
}

$linked_users                  = 0;
$subscribers_without_directory = 0;
$cd_cap_without_directory      = 0;
$directory_without_cd_cap      = 0;

foreach ( $user_ids as $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        continue;
    }

    $roles = (array) $user->roles;
    if ( empty( $roles ) ) {
        $no_role_count++;
    }
    foreach ( $roles as $role ) {
        if ( ! isset( $role_counts[ $role ] ) ) {
            $role_counts[ $role ] = 0;
        }
        $role_counts[ $role ]++;
    }

    foreach ( array_keys( $capability_counts ) as $capability ) {
        if ( user_can( $user, $capability ) ) {
            $capability_counts[ $capability ]++;
        }
    }

    $month = substr( (string) $user->user_registered, 0, 7 );
    if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
        if ( ! isset( $registration_months[ $month ] ) ) {
            $registration_months[ $month ] = 0;
        }
        $registration_months[ $month ]++;
    }

    $has_directory = isset( $member_links[ (int) $user_id ] );
    $has_cd_member = user_can( $user, 'cd_member' );
    if ( $has_directory ) {
        $linked_users++;
    }
    if ( in_array( 'subscriber', $roles, true ) && ! $has_directory ) {
        $subscribers_without_directory++;
    }
    if ( $has_cd_member && ! $has_directory ) {
        $cd_cap_without_directory++;
    }
    if ( $has_directory && ! $has_cd_member && ! user_can( $user, 'manage_options' ) ) {
        $directory_without_cd_cap++;
    }
}
ksort( $role_counts );
ksort( $registration_months );

$post_counts = array();
$post_rows = $wpdb->get_results(
    "SELECT post_type, post_status, COUNT(*) AS total
     FROM {$wpdb->posts}
     GROUP BY post_type, post_status
     ORDER BY post_type, post_status",
    ARRAY_A
);
foreach ( $post_rows as $row ) {
    $post_type = (string) $row['post_type'];
    if ( ! isset( $post_counts[ $post_type ] ) ) {
        $post_counts[ $post_type ] = array();
    }
    $post_counts[ $post_type ][ (string) $row['post_status'] ] = (int) $row['total'];
}

$comment_counts = array();
$comment_rows = $wpdb->get_results(
    "SELECT comment_type, comment_approved, COUNT(*) AS total
     FROM {$wpdb->comments}
     GROUP BY comment_type, comment_approved
     ORDER BY comment_type, comment_approved",
    ARRAY_A
);
foreach ( $comment_rows as $row ) {
    $type = '' === (string) $row['comment_type'] ? 'comment' : (string) $row['comment_type'];
    if ( ! isset( $comment_counts[ $type ] ) ) {
        $comment_counts[ $type ] = array();
    }
    $comment_counts[ $type ][ (string) $row['comment_approved'] ] = (int) $row['total'];
}

$directory_installs = array();
foreach ( glob( WP_PLUGIN_DIR . '/community-directory*' ) as $directory ) {
    if ( is_dir( $directory ) ) {
        $directory_installs[] = stx_plugin_info_for_directory( $directory );
    }
}

$ninja_tables = array();
$ninja_pattern = $wpdb->esc_like( $wpdb->prefix ) . '%ninja%';
$ninja_table_names = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ninja_pattern ) );
foreach ( $ninja_table_names as $table_name ) {
    $ninja_tables[] = array(
        'name'    => $table_name,
        'rows'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" ),
        'columns' => stx_table_columns( $table_name ),
    );
}

$ninja_post = get_post( 142 );
$ninja_142  = array(
    'post_exists' => (bool) $ninja_post,
    'post_type'   => $ninja_post ? $ninja_post->post_type : null,
    'post_status' => $ninja_post ? $ninja_post->post_status : null,
    'post_title'  => $ninja_post ? $ninja_post->post_title : null,
    'row_count'   => null,
    'meta_keys'   => array(),
);
if ( $ninja_post ) {
    $ninja_142['meta_keys'] = array_keys( get_post_meta( 142 ) );
    sort( $ninja_142['meta_keys'] );
}
foreach ( $ninja_table_names as $table_name ) {
    $columns = stx_table_columns( $table_name );
    if ( in_array( 'table_id', $columns, true ) ) {
        $ninja_142['row_count'] = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE table_id = %d", 142 )
        );
        $ninja_142['storage_table'] = $table_name;
        break;
    }
}

$wp_mail_smtp = get_option( 'wp_mail_smtp', array() );
if ( ! is_array( $wp_mail_smtp ) ) {
    $wp_mail_smtp = array();
}
$mailer     = stx_nested_value( $wp_mail_smtp, array( 'mail', 'mailer' ), '' );
$from_email = stx_nested_value( $wp_mail_smtp, array( 'mail', 'from_email' ), '' );
$smtp_host  = stx_nested_value( $wp_mail_smtp, array( 'smtp', 'host' ), '' );
$smtp_user  = stx_nested_value( $wp_mail_smtp, array( 'smtp', 'user' ), '' );
$smtp_pass  = stx_nested_value( $wp_mail_smtp, array( 'smtp', 'pass' ), '' );
$mail_report = array(
    'option_exists'        => ! empty( $wp_mail_smtp ),
    'mailer'               => is_scalar( $mailer ) ? (string) $mailer : '',
    'from_email'           => stx_mask_email( $from_email ),
    'from_email_configured'=> ! empty( $from_email ),
    'smtp_host'            => is_scalar( $smtp_host ) ? (string) $smtp_host : '',
    'smtp_port'            => (string) stx_nested_value( $wp_mail_smtp, array( 'smtp', 'port' ), '' ),
    'smtp_encryption'      => (string) stx_nested_value( $wp_mail_smtp, array( 'smtp', 'encryption' ), '' ),
    'smtp_auth'            => (bool) stx_nested_value( $wp_mail_smtp, array( 'smtp', 'auth' ), false ),
    'smtp_user_configured' => ! empty( $smtp_user ),
    'smtp_pass_configured' => ! empty( $smtp_pass ),
);

$wpforms_counts = array();
$wpforms_rows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT post_status, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_status",
        'wpforms'
    ),
    ARRAY_A
);
foreach ( $wpforms_rows as $row ) {
    $wpforms_counts[ (string) $row['post_status'] ] = (int) $row['total'];
}

$event_counts = isset( $post_counts['tribe_events'] ) ? $post_counts['tribe_events'] : array();
$upcoming_events = array();
$event_posts = get_posts(
    array(
        'post_type'      => 'tribe_events',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'orderby'        => 'meta_value',
        'meta_key'       => '_EventStartDate',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => '_EventStartDate',
                'value'   => current_time( 'mysql' ),
                'compare' => '>=',
                'type'    => 'DATETIME',
            ),
        ),
    )
);
foreach ( $event_posts as $event_post ) {
    $upcoming_events[] = array(
        'id'         => (int) $event_post->ID,
        'title'      => $event_post->post_title,
        'start_date' => (string) get_post_meta( $event_post->ID, '_EventStartDate', true ),
        'end_date'   => (string) get_post_meta( $event_post->ID, '_EventEndDate', true ),
    );
}

$plugin_specific_tables = array();
$table_searches = array(
    'tournament_or_bracket' => array( '%tournament%', '%bracket%' ),
    'wpforms'               => array( '%wpforms%' ),
    'tribe'                 => array( '%tribe%' ),
);
foreach ( $table_searches as $group => $patterns ) {
    $plugin_specific_tables[ $group ] = array();
    foreach ( $patterns as $pattern ) {
        $like = $wpdb->esc_like( $wpdb->prefix ) . $pattern;
        $names = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
        foreach ( $names as $name ) {
            $plugin_specific_tables[ $group ][ $name ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$name}`" );
        }
    }
}

$option_key_counts = array();
$option_patterns = array(
    'ninja'      => '%ninja%',
    'tournament' => '%tournament%',
    'bracket'    => '%bracket%',
    'wpforms'    => '%wpforms%',
    'wp_mail_smtp' => '%wp_mail_smtp%',
    'team'       => '%team%',
);
foreach ( $option_patterns as $label => $pattern ) {
    $option_key_counts[ $label ] = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern )
    );
}

$core_extra_files = array();
foreach ( array(
    ABSPATH . 'wp-admin/error_log',
    ABSPATH . 'wp-admin/network/error_log',
    ABSPATH . 'wp-admin/user/error_log',
    ABSPATH . 'wp-cli.yml',
) as $path ) {
    $core_extra_files[] = array(
        'path'       => str_replace( ABSPATH, '[WP_ROOT]/', $path ),
        'exists'     => file_exists( $path ),
        'size_bytes' => file_exists( $path ) ? (int) filesize( $path ) : 0,
        'modified'   => file_exists( $path ) ? gmdate( 'c', filemtime( $path ) ) : null,
    );
}

$report = array(
    'generated_at_utc' => gmdate( 'c' ),
    'runtime' => array(
        'wordpress'      => get_bloginfo( 'version' ),
        'php_cli'        => PHP_VERSION,
        'mysql'          => $wpdb->db_version(),
        'openssl_loaded' => extension_loaded( 'openssl' ),
        'table_prefix'   => $wpdb->prefix,
    ),
    'site_settings' => $site_settings,
    'users' => array(
        'total'                           => count( $user_ids ),
        'role_counts'                     => $role_counts,
        'capability_counts'               => $capability_counts,
        'no_role_count'                   => $no_role_count,
        'registration_month_counts'       => $registration_months,
        'directory_member_rows'           => $directory_member_rows,
        'directory_members_without_wp_id' => $directory_orphans,
        'linked_wp_users'                 => $linked_users,
        'subscribers_without_directory'   => $subscribers_without_directory,
        'cd_cap_without_directory'        => $cd_cap_without_directory,
        'directory_without_cd_cap'        => $directory_without_cd_cap,
    ),
    'content' => array(
        'post_counts'     => $post_counts,
        'comment_counts'  => $comment_counts,
        'event_counts'    => $event_counts,
        'upcoming_events' => $upcoming_events,
        'wpforms_counts'  => $wpforms_counts,
    ),
    'community_directory_installs' => $directory_installs,
    'ninja_tables' => array(
        'database_tables' => $ninja_tables,
        'table_142'       => $ninja_142,
    ),
    'mail' => $mail_report,
    'plugin_specific_tables' => $plugin_specific_tables,
    'option_key_counts' => $option_key_counts,
    'core_extra_files' => $core_extra_files,
    'logs' => array(
        stx_log_report( WP_CONTENT_DIR . '/debug.log' ),
        stx_log_report( ABSPATH . 'wp-admin/error_log' ),
        stx_log_report( ABSPATH . 'wp-admin/network/error_log' ),
        stx_log_report( ABSPATH . 'wp-admin/user/error_log' ),
    ),
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
