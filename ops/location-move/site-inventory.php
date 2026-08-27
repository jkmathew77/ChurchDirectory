<?php
/**
 * Read-only WP-CLI inventory for the St. Thekla move to Sacred Heart Chapel.
 *
 * Produces a sanitized JSON report. It does not modify WordPress content,
 * settings, media, users, plugins, or database records.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

if ( ! function_exists( 'get_option' ) ) {
    fwrite( STDERR, "WordPress is not loaded.\n" );
    exit( 1 );
}

global $wpdb;

function st_move_redact( $value ) {
    if ( is_array( $value ) ) {
        $clean = array();
        foreach ( $value as $key => $child ) {
            if ( preg_match( '/(?:pass|password|secret|token|api[_-]?key|license|nonce|recipient|smtp|auth)/i', (string) $key ) ) {
                $clean[ $key ] = '[redacted]';
            } else {
                $clean[ $key ] = st_move_redact( $child );
            }
        }
        return $clean;
    }

    if ( is_object( $value ) ) {
        return st_move_redact( get_object_vars( $value ) );
    }

    if ( ! is_string( $value ) ) {
        return $value;
    }

    $value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $value );
    $value = preg_replace( '/\b[A-Za-z0-9_\-]{40,}\b/', '[token]', $value );
    return $value;
}

function st_move_excerpt( $value, $needle, $radius = 140 ) {
    $value = maybe_unserialize( $value );
    if ( is_array( $value ) || is_object( $value ) ) {
        $value = wp_json_encode( st_move_redact( $value ), JSON_UNESCAPED_SLASHES );
    }
    $value = st_move_redact( (string) $value );
    $position = stripos( $value, $needle );
    if ( false === $position ) {
        return '';
    }
    $start = max( 0, $position - $radius );
    $length = strlen( $needle ) + ( 2 * $radius );
    $excerpt = substr( $value, $start, $length );
    $excerpt = preg_replace( '/\s+/', ' ', $excerpt );
    return trim( ( $start > 0 ? '…' : '' ) . $excerpt . ( $start + $length < strlen( $value ) ? '…' : '' ) );
}

function st_move_extract_shortcodes( $content ) {
    $items = array();
    $pattern = get_shortcode_regex();
    if ( preg_match_all( '/' . $pattern . '/s', (string) $content, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $items[] = array(
                'tag'        => isset( $match[2] ) ? sanitize_key( $match[2] ) : '',
                'attributes' => isset( $match[3] ) ? trim( st_move_redact( $match[3] ) ) : '',
            );
        }
    }
    return $items;
}

function st_move_extract_urls( $content, $tag ) {
    $results = array();
    $attribute = 'img' === $tag ? 'src' : 'src';
    if ( preg_match_all( '/<' . preg_quote( $tag, '/' ) . '\b[^>]*\b' . $attribute . '=["\']([^"\']+)["\'][^>]*>/i', (string) $content, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $element = $match[0];
            $entry = array( 'src' => esc_url_raw( html_entity_decode( $match[1] ) ) );
            if ( 'img' === $tag && preg_match( '/\balt=["\']([^"\']*)["\']/i', $element, $alt ) ) {
                $entry['alt'] = sanitize_text_field( html_entity_decode( $alt[1] ) );
            }
            $results[] = $entry;
        }
    }
    return $results;
}

function st_move_post_summary( $post ) {
    if ( ! $post instanceof WP_Post ) {
        return null;
    }
    $rendered = apply_filters( 'the_content', $post->post_content );
    return array(
        'id'               => (int) $post->ID,
        'type'             => $post->post_type,
        'status'           => $post->post_status,
        'title'            => $post->post_title,
        'slug'             => $post->post_name,
        'permalink'        => get_permalink( $post ),
        'modified_gmt'     => $post->post_modified_gmt,
        'shortcodes'       => st_move_extract_shortcodes( $post->post_content ),
        'raw_images'       => st_move_extract_urls( $post->post_content, 'img' ),
        'rendered_images'  => st_move_extract_urls( $rendered, 'img' ),
        'rendered_iframes' => st_move_extract_urls( $rendered, 'iframe' ),
        'text_excerpt'     => substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) ), 0, 1600 ),
    );
}

$old_markers = array(
    '2 Old Ox Road',
    'Old Ox Road',
    'Nyack, NY 10960',
    'Nyack, New York 10960',
    'St Thomas Lutheran Church',
    'St. Thomas Lutheran Church',
    '107 Strawtown Road',
    'West Nyack',
);

$new_markers = array(
    'Sacred Heart Chapel',
    '175 Route 340',
    'Sparkill, NY 10976',
    'Sparkill',
);

$schedule_markers = array(
    'Morning Prayers',
    'Morning Prayer',
    'Holy Liturgy',
    'Holy Qurbana',
    'Lilyo',
    'Lilyu',
    'Dismissal',
    'Refreshments',
    'Tree of Life',
);

$all_markers = array_values( array_unique( array_merge( $old_markers, $new_markers, $schedule_markers ) ) );

$front_page_id = (int) get_option( 'page_on_front', 0 );
$posts_page_id = (int) get_option( 'page_for_posts', 0 );
$front_page = $front_page_id ? get_post( $front_page_id ) : null;
$contact_page = get_page_by_path( 'contact-us' );

$matches = array(
    'posts'     => array(),
    'postmeta'  => array(),
    'options'   => array(),
    'terms'     => array(),
);

$post_rows = $wpdb->get_results(
    "SELECT ID, post_type, post_status, post_title, post_content, post_excerpt
     FROM {$wpdb->posts}
     WHERE post_status NOT IN ('auto-draft', 'inherit', 'trash')
     ORDER BY ID ASC"
);
foreach ( $post_rows as $row ) {
    $haystack = implode( "\n", array( $row->post_title, $row->post_content, $row->post_excerpt ) );
    foreach ( $all_markers as $marker ) {
        if ( false !== stripos( $haystack, $marker ) ) {
            $matches['posts'][] = array(
                'marker'    => $marker,
                'post_id'   => (int) $row->ID,
                'post_type' => $row->post_type,
                'status'    => $row->post_status,
                'title'     => $row->post_title,
                'permalink' => get_permalink( (int) $row->ID ),
                'excerpt'   => st_move_excerpt( $haystack, $marker ),
            );
        }
    }
}

$postmeta_rows = $wpdb->get_results(
    "SELECT meta_id, post_id, meta_key, meta_value
     FROM {$wpdb->postmeta}
     WHERE meta_value <> ''
     ORDER BY meta_id ASC"
);
foreach ( $postmeta_rows as $row ) {
    foreach ( $all_markers as $marker ) {
        if ( false !== stripos( (string) $row->meta_value, $marker ) ) {
            $post = get_post( (int) $row->post_id );
            $matches['postmeta'][] = array(
                'marker'    => $marker,
                'post_id'   => (int) $row->post_id,
                'post_type' => $post ? $post->post_type : null,
                'title'     => $post ? $post->post_title : null,
                'meta_key'  => $row->meta_key,
                'excerpt'   => st_move_excerpt( $row->meta_value, $marker ),
            );
        }
    }
}

$option_rows = $wpdb->get_results(
    "SELECT option_id, option_name, option_value, autoload
     FROM {$wpdb->options}
     WHERE option_value <> ''
     ORDER BY option_id ASC"
);
foreach ( $option_rows as $row ) {
    foreach ( $all_markers as $marker ) {
        if ( false !== stripos( (string) $row->option_value, $marker ) ) {
            $matches['options'][] = array(
                'marker'      => $marker,
                'option_name' => $row->option_name,
                'autoload'    => $row->autoload,
                'excerpt'     => st_move_excerpt( $row->option_value, $marker ),
            );
        }
    }
}

$term_rows = $wpdb->get_results(
    "SELECT tt.term_taxonomy_id, tt.taxonomy, tt.description, t.name, t.slug
     FROM {$wpdb->term_taxonomy} tt
     INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
     WHERE tt.description <> ''"
);
foreach ( $term_rows as $row ) {
    foreach ( $all_markers as $marker ) {
        if ( false !== stripos( (string) $row->description, $marker ) ) {
            $matches['terms'][] = array(
                'marker'   => $marker,
                'taxonomy' => $row->taxonomy,
                'name'     => $row->name,
                'slug'     => $row->slug,
                'excerpt'  => st_move_excerpt( $row->description, $marker ),
            );
        }
    }
}

$menus = array();
foreach ( wp_get_nav_menus() as $menu ) {
    $items = array();
    foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: array() as $item ) {
        $items[] = array(
            'id'        => (int) $item->ID,
            'title'     => $item->title,
            'url'       => $item->url,
            'type'      => $item->type,
            'object'    => $item->object,
            'object_id' => (int) $item->object_id,
            'status'    => $item->post_status,
        );
    }
    $menus[] = array(
        'id'    => (int) $menu->term_id,
        'name'  => $menu->name,
        'slug'  => $menu->slug,
        'items' => $items,
    );
}

$attachments = array();
$attachment_posts = get_posts(
    array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => 40,
        'orderby'        => 'date',
        'order'          => 'DESC',
    )
);
foreach ( $attachment_posts as $attachment ) {
    $meta = wp_get_attachment_metadata( $attachment->ID );
    $attachments[] = array(
        'id'          => (int) $attachment->ID,
        'title'       => $attachment->post_title,
        'date_gmt'    => $attachment->post_date_gmt,
        'url'         => wp_get_attachment_url( $attachment->ID ),
        'file'        => get_attached_file( $attachment->ID ) ? basename( get_attached_file( $attachment->ID ) ) : '',
        'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
        'width'       => is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : null,
        'height'      => is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : null,
        'parent_id'   => (int) $attachment->post_parent,
    );
}

$post_type_counts = array();
foreach ( get_post_types( array(), 'objects' ) as $post_type => $object ) {
    $counts = wp_count_posts( $post_type );
    $post_type_counts[ $post_type ] = array(
        'label'     => $object->labels->name,
        'public'    => (bool) $object->public,
        'show_ui'   => (bool) $object->show_ui,
        'published' => isset( $counts->publish ) ? (int) $counts->publish : 0,
        'draft'     => isset( $counts->draft ) ? (int) $counts->draft : 0,
    );
}

$active_plugins = array();
if ( ! function_exists( 'get_plugins' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
foreach ( get_plugins() as $plugin_file => $data ) {
    $active_plugins[] = array(
        'file'    => $plugin_file,
        'name'    => $data['Name'],
        'version' => $data['Version'],
        'active'  => is_plugin_active( $plugin_file ),
    );
}

$stc_routes = array();
foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
    if ( 0 === strpos( $route, '/st-thekla/v1/' ) ) {
        $stc_routes[] = $route;
    }
}
sort( $stc_routes );

$site_core_main = WP_PLUGIN_DIR . '/st-thekla-site-core/st-thekla-site-core.php';
$directory_main = WP_PLUGIN_DIR . '/community-directory/community-directory.php';

$report = array(
    'generated_at_utc' => gmdate( 'c' ),
    'site' => array(
        'home'             => home_url( '/' ),
        'siteurl'          => site_url( '/' ),
        'name'             => get_bloginfo( 'name' ),
        'description'      => get_bloginfo( 'description' ),
        'timezone'         => wp_timezone_string(),
        'show_on_front'    => get_option( 'show_on_front' ),
        'front_page_id'    => $front_page_id,
        'posts_page_id'    => $posts_page_id,
        'permalink'        => get_option( 'permalink_structure' ),
        'theme'            => array(
            'stylesheet' => get_stylesheet(),
            'template'   => get_template(),
            'name'       => wp_get_theme()->get( 'Name' ),
            'version'    => wp_get_theme()->get( 'Version' ),
        ),
    ),
    'front_page' => st_move_post_summary( $front_page ),
    'contact_page' => st_move_post_summary( $contact_page ),
    'site_core' => array(
        'settings'            => st_move_redact( get_option( 'stc_settings', array() ) ),
        'weekly_schedule'     => st_move_redact( get_option( 'stc_weekly_schedule', array() ) ),
        'service_posts'       => get_posts( array( 'post_type' => 'stc_service', 'post_status' => array( 'publish', 'draft', 'future' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ),
        'announcement_count'  => (int) ( wp_count_posts( 'stc_announcement' )->publish ?? 0 ),
        'registered_routes'   => $stc_routes,
        'main_file_sha256'    => is_file( $site_core_main ) ? hash_file( 'sha256', $site_core_main ) : null,
    ),
    'community_directory' => array(
        'version'          => defined( 'CD_VERSION' ) ? CD_VERSION : null,
        'db_version'       => get_option( 'cd_db_version', null ),
        'main_file_sha256' => is_file( $directory_main ) ? hash_file( 'sha256', $directory_main ) : null,
        'base_slug'        => get_option( 'cd_base_slug', 'community' ),
        'pwa_enabled'      => get_option( 'cd_pwa_enabled', '0' ),
    ),
    'matches'          => $matches,
    'menus'            => $menus,
    'recent_images'    => $attachments,
    'post_type_counts' => $post_type_counts,
    'plugins'          => $active_plugins,
    'selected_options' => array(
        'admin_email_redacted'      => get_option( 'admin_email' ) ? '[configured]' : '[missing]',
        'users_can_register'        => (string) get_option( 'users_can_register', '' ),
        'default_comment_status'    => get_option( 'default_comment_status', '' ),
        'default_ping_status'       => get_option( 'default_ping_status', '' ),
        'wpforms_forms_count'       => (int) ( wp_count_posts( 'wpforms' )->publish ?? 0 ),
        'widget_text'               => st_move_redact( get_option( 'widget_text', array() ) ),
        'widget_custom_html'        => st_move_redact( get_option( 'widget_custom_html', array() ) ),
        'widget_block'              => st_move_redact( get_option( 'widget_block', array() ) ),
        'sidebars_widgets'          => st_move_redact( get_option( 'sidebars_widgets', array() ) ),
        'theme_mods'                => st_move_redact( get_option( 'theme_mods_' . get_stylesheet(), array() ) ),
    ),
);

// Replace service post objects with safe summaries.
$service_summaries = array();
foreach ( $report['site_core']['service_posts'] as $service ) {
    $service_summaries[] = array(
        'id'        => (int) $service->ID,
        'title'     => $service->post_title,
        'status'    => $service->post_status,
        'start'     => get_post_meta( $service->ID, '_stc_start', true ),
        'end'       => get_post_meta( $service->ID, '_stc_end', true ),
        'type'      => get_post_meta( $service->ID, '_stc_service_type', true ),
        'location'  => get_post_meta( $service->ID, '_stc_location', true ),
        'cancelled' => (bool) get_post_meta( $service->ID, '_stc_cancelled', true ),
    );
}
$report['site_core']['service_posts'] = $service_summaries;

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
