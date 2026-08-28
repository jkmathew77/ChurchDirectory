<?php
/**
 * WP-CLI eval-file audit: return a sanitized inventory of content owned by
 * inactive legacy plugins and exact references to that content. No data is
 * changed and no option values, member PII, or secrets are emitted.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

function st_legacy_sensitive_key( $key ) {
    return (bool) preg_match( '/(?:pass|password|secret|token|api[_-]?key|license|nonce|recipient|to_email|admin_email)/i', (string) $key );
}

function st_legacy_sanitize( $value, $key = '' ) {
    if ( st_legacy_sensitive_key( $key ) ) {
        return '[redacted]';
    }
    if ( is_array( $value ) ) {
        $clean = array();
        foreach ( $value as $child_key => $child_value ) {
            $clean[ $child_key ] = st_legacy_sanitize( $child_value, (string) $child_key );
        }
        return $clean;
    }
    if ( is_object( $value ) ) {
        return st_legacy_sanitize( get_object_vars( $value ), $key );
    }
    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            return st_legacy_sanitize( $decoded, $key );
        }
        $value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $value );
        $value = preg_replace( '/\b[A-Za-z0-9_\-]{40,}\b/', '[token]', $value );
        if ( strlen( $value ) > 5000 ) {
            $value = substr( $value, 0, 5000 ) . '…[truncated]';
        }
    }
    return $value;
}

function st_legacy_extract_shortcodes( $content ) {
    $shortcodes = array();
    $pattern = get_shortcode_regex();
    if ( ! preg_match_all( '/' . $pattern . '/s', (string) $content, $matches, PREG_SET_ORDER ) ) {
        return $shortcodes;
    }
    foreach ( $matches as $match ) {
        $attributes = shortcode_parse_atts( isset( $match[3] ) ? $match[3] : '' );
        if ( ! is_array( $attributes ) ) {
            $attributes = array();
        }
        $shortcodes[] = array(
            'tag'        => isset( $match[2] ) ? sanitize_key( $match[2] ) : '',
            'attributes' => st_legacy_sanitize( $attributes ),
        );
    }
    return $shortcodes;
}

function st_legacy_relevant_shortcode( $tag ) {
    return (bool) preg_match(
        '/^(?:tribe|tribe_events?|event_calendar|tmm|team_members?|wpedon|paypal|donation|tournament|bracket|iframe|optinmonster|optin_monster)/i',
        (string) $tag
    );
}

function st_legacy_post_summary( $post ) {
    $meta = array();
    foreach ( get_post_meta( $post->ID ) as $meta_key => $values ) {
        if ( st_legacy_sensitive_key( $meta_key ) ) {
            continue;
        }

        $include = false;
        if ( 0 === strpos( $post->post_type, 'tribe_' ) ) {
            $include = 0 === strpos( $meta_key, '_Event' ) || '_thumbnail_id' === $meta_key;
        } elseif ( in_array( $post->post_type, array( 'tmm', 'wpplugin_don_button' ), true ) ) {
            $include = true;
        }
        if ( ! $include ) {
            continue;
        }

        $value = maybe_unserialize( 1 === count( $values ) ? $values[0] : $values );
        $meta[ $meta_key ] = st_legacy_sanitize( $value, $meta_key );
    }

    $excerpt = preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );

    return array(
        'id'                => (int) $post->ID,
        'post_type'         => $post->post_type,
        'status'            => $post->post_status,
        'title'             => $post->post_title,
        'slug'              => $post->post_name,
        'published_gmt'     => $post->post_date_gmt,
        'modified_gmt'      => $post->post_modified_gmt,
        'permalink'         => get_permalink( $post->ID ),
        'shortcodes'        => st_legacy_extract_shortcodes( $post->post_content ),
        'sanitized_content' => st_legacy_sanitize( $post->post_content, 'content' ),
        'text_excerpt'      => st_legacy_sanitize( substr( trim( $excerpt ), 0, 2000 ), 'content' ),
        'meta'              => $meta,
    );
}

function st_legacy_content_reference_type( $content, $target ) {
    $content = (string) $content;
    $id      = (int) $target['id'];
    $slug    = (string) $target['slug'];
    $url     = (string) $target['permalink'];

    if ( '' !== $url && false !== stripos( $content, $url ) ) {
        return 'permalink';
    }
    if ( preg_match( '/(?:[?&]|&amp;)p=' . preg_quote( (string) $id, '/' ) . '(?:&|&amp;|["\'\s<]|$)/i', $content ) ) {
        return 'query_post_id';
    }
    if ( '' !== $slug && preg_match( '#/(?:events?/)?' . preg_quote( $slug, '#' ) . '/?(?:[?\#"\'\s<]|$)#i', $content ) ) {
        return 'slug_path';
    }
    return null;
}

global $wpdb;

$plugin_map = array(
    'the-events-calendar' => array( 'tribe_events', 'tribe_organizer', 'tribe_venue' ),
    'team-members'        => array( 'tmm' ),
    'legacy-donation'     => array( 'wpplugin_don_button' ),
);

$posts_by_type = array();
$targets       = array();
foreach ( $plugin_map as $plugin_group => $post_types ) {
    foreach ( $post_types as $post_type ) {
        $posts = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
                'posts_per_page' => -1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            )
        );
        $posts_by_type[ $post_type ] = array();
        foreach ( $posts as $post ) {
            $summary = st_legacy_post_summary( $post );
            $summary['plugin_group'] = $plugin_group;
            $posts_by_type[ $post_type ][] = $summary;
            $targets[ $post->ID ] = $summary;
        }
    }
}

$content_rows = $wpdb->get_results(
    "SELECT ID, post_type, post_status, post_title, post_content
     FROM {$wpdb->posts}
     WHERE post_status NOT IN ('auto-draft', 'inherit', 'trash')
     ORDER BY ID ASC"
);

$exact_references = array();
foreach ( $targets as $target_id => $target ) {
    $content_refs = array();
    foreach ( $content_rows as $candidate ) {
        if ( (int) $candidate->ID === (int) $target_id ) {
            continue;
        }
        $reference_type = st_legacy_content_reference_type( $candidate->post_content, $target );
        if ( null !== $reference_type ) {
            $content_refs[] = array(
                'id'        => (int) $candidate->ID,
                'post_type' => $candidate->post_type,
                'status'    => $candidate->post_status,
                'title'     => $candidate->post_title,
                'permalink' => get_permalink( $candidate->ID ),
                'matched'   => $reference_type,
            );
        }
    }

    $menu_refs = array();
    $menu_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.ID, p.post_title
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'nav_menu_item'
               AND pm.meta_key = '_menu_item_object_id'
               AND pm.meta_value = %s",
            (string) $target_id
        )
    );
    foreach ( $menu_rows as $menu_row ) {
        $menu_refs[] = array(
            'id'    => (int) $menu_row->ID,
            'title' => $menu_row->post_title,
        );
    }

    $option_names = array();
    foreach ( array_filter( array( $target['permalink'], '?p=' . (int) $target_id, $target['slug'] ) ) as $needle ) {
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name NOT LIKE '\\_transient%%'
                   AND option_name NOT LIKE '\\_site_transient%%'
                   AND option_value LIKE %s
                 LIMIT 100",
                '%' . $wpdb->esc_like( $needle ) . '%'
            )
        );
        foreach ( $rows as $option_name ) {
            if ( ! st_legacy_sensitive_key( $option_name ) ) {
                $option_names[ $option_name ] = true;
            }
        }
    }

    $exact_references[] = array(
        'target_id'          => (int) $target_id,
        'target_type'        => $target['post_type'],
        'target_title'       => $target['title'],
        'content_references' => $content_refs,
        'menu_references'    => $menu_refs,
        'option_names'       => array_keys( $option_names ),
    );
}

$legacy_shortcode_references = array();
$legacy_block_references     = array();
foreach ( $content_rows as $candidate ) {
    $shortcodes = array_values(
        array_filter(
            st_legacy_extract_shortcodes( $candidate->post_content ),
            static function ( $shortcode ) {
                return st_legacy_relevant_shortcode( $shortcode['tag'] );
            }
        )
    );
    if ( ! empty( $shortcodes ) ) {
        $legacy_shortcode_references[] = array(
            'id'         => (int) $candidate->ID,
            'post_type'  => $candidate->post_type,
            'status'     => $candidate->post_status,
            'title'      => $candidate->post_title,
            'permalink'  => get_permalink( $candidate->ID ),
            'shortcodes' => $shortcodes,
        );
    }

    if ( preg_match( '/<!--\s+wp:(?:tribe|tmm|team-members|optinmonster|iframe|tournament|bracket)[^>]*-->/i', $candidate->post_content ) ) {
        $legacy_block_references[] = array(
            'id'        => (int) $candidate->ID,
            'post_type' => $candidate->post_type,
            'status'    => $candidate->post_status,
            'title'     => $candidate->post_title,
            'permalink' => get_permalink( $candidate->ID ),
        );
    }
}

$widget_option_names = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options}
     WHERE option_name = 'sidebars_widgets'
        OR option_name LIKE 'widget\\_%'
     ORDER BY option_name ASC"
);
$legacy_widget_options = array();
foreach ( $widget_option_names as $option_name ) {
    if ( st_legacy_sensitive_key( $option_name ) ) {
        continue;
    }
    $value = get_option( $option_name );
    $serialized = maybe_serialize( $value );
    if ( preg_match( '/(?:tribe|events-calendar|tmm|team-members|wpedon|paypal-donation|tournament|bracket|optinmonster|iframe)/i', $serialized ) ) {
        $legacy_widget_options[] = $option_name;
    }
}

$plugin_state = array();
foreach ( array(
    'akismet/akismet.php'                         => 'akismet',
    'google-analytics-for-wordpress/googleanalytics.php' => 'monsterinsights',
    'iframe/iframe.php'                           => 'iframe',
    'jetpack/jetpack.php'                         => 'jetpack',
    'ninja-tables/ninja-tables.php'               => 'ninja-tables',
    'optinmonster/optin-monster-wp-api.php'        => 'optinmonster',
    'capability-manager-enhanced/capsman-enhanced.php' => 'publishpress-capabilities',
    'simple-tournament-brackets/simple-tournament-brackets.php' => 'simple-tournament-brackets',
    'team-members/tmm.php'                        => 'team-members',
    'the-events-calendar/the-events-calendar.php' => 'the-events-calendar',
    'wordpress-seo/wp-seo.php'                    => 'yoast-seo',
) as $plugin_file => $label ) {
    $plugin_state[ $label ] = array(
        'plugin_file' => $plugin_file,
        'installed'   => file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ),
        'active'      => is_plugin_active( $plugin_file ),
    );
}

$feedback_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'feedback' AND post_status <> 'trash'"
);
$feedback_date_range = $wpdb->get_row(
    "SELECT MIN(post_date_gmt) AS first_gmt, MAX(post_date_gmt) AS last_gmt
     FROM {$wpdb->posts}
     WHERE post_type = 'feedback' AND post_status <> 'trash'",
    ARRAY_A
);

$report = array(
    'generated_at_utc'             => gmdate( 'c' ),
    'plugin_state'                 => $plugin_state,
    'posts_by_type'                => $posts_by_type,
    'exact_references'             => $exact_references,
    'legacy_shortcode_references'  => $legacy_shortcode_references,
    'legacy_block_references'      => $legacy_block_references,
    'legacy_widget_option_names'   => array_values( array_unique( $legacy_widget_options ) ),
    'feedback'                     => array(
        'count'     => $feedback_count,
        'first_gmt' => $feedback_date_range['first_gmt'] ?? null,
        'last_gmt'  => $feedback_date_range['last_gmt'] ?? null,
    ),
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
