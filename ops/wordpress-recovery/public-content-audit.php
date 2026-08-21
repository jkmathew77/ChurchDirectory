<?php
/**
 * WP-CLI eval-file script: capture public, migration-relevant shortcode and
 * Ninja Tables content while removing secret-like attributes and email values.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

global $wpdb;

function stpc_sensitive_key( $key ) {
    return (bool) preg_match( '/(?:pass|password|secret|token|api[_-]?key|license|nonce|recipient|to_email|admin_email)/i', (string) $key );
}

function stpc_sanitize_value( $value, $key = '' ) {
    if ( stpc_sensitive_key( $key ) ) {
        return '[redacted]';
    }

    if ( is_array( $value ) ) {
        $clean = array();
        foreach ( $value as $child_key => $child_value ) {
            $clean[ $child_key ] = stpc_sanitize_value( $child_value, (string) $child_key );
        }
        return $clean;
    }

    if ( is_object( $value ) ) {
        return stpc_sanitize_value( get_object_vars( $value ), $key );
    }

    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            return stpc_sanitize_value( $decoded, $key );
        }
        $value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $value );
        $value = preg_replace( '/\b[A-Za-z0-9_\-]{40,}\b/', '[token]', $value );
        if ( strlen( $value ) > 5000 ) {
            $value = substr( $value, 0, 5000 ) . '…[truncated]';
        }
    }

    return $value;
}

function stpc_extract_shortcodes( $content ) {
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
        $safe_attributes = array();
        foreach ( $attributes as $key => $value ) {
            $safe_attributes[ $key ] = stpc_sanitize_value( $value, (string) $key );
        }
        $enclosed = isset( $match[5] ) ? wp_strip_all_tags( $match[5] ) : '';
        $enclosed = preg_replace( '/\s+/', ' ', $enclosed );
        $enclosed = stpc_sanitize_value( substr( trim( $enclosed ), 0, 1000 ), 'content' );

        $shortcodes[] = array(
            'tag'        => isset( $match[2] ) ? $match[2] : '',
            'attributes' => $safe_attributes,
            'content'    => $enclosed,
        );
    }

    return $shortcodes;
}

$public_posts = array();
foreach ( array( 5, 7, 196, 2932 ) as $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        $public_posts[] = array( 'id' => $post_id, 'exists' => false );
        continue;
    }

    $public_posts[] = array(
        'id'                => (int) $post->ID,
        'exists'            => true,
        'post_type'         => $post->post_type,
        'status'            => $post->post_status,
        'title'             => $post->post_title,
        'permalink'         => get_permalink( $post->ID ),
        'shortcodes'        => stpc_extract_shortcodes( $post->post_content ),
        'sanitized_content' => stpc_sanitize_value( $post->post_content, 'content' ),
        'text_excerpt'      => stpc_sanitize_value(
            substr( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ), 0, 1500 ),
            'content'
        ),
    );
}

$ninja = array(
    'post' => null,
    'meta' => array(),
    'storage_table' => null,
    'rows' => array(),
);

$ninja_post = get_post( 142 );
if ( $ninja_post ) {
    $ninja['post'] = array(
        'id'        => 142,
        'type'      => $ninja_post->post_type,
        'status'    => $ninja_post->post_status,
        'title'     => $ninja_post->post_title,
        'permalink' => get_permalink( 142 ),
    );

    foreach ( get_post_meta( 142 ) as $meta_key => $values ) {
        if ( stpc_sensitive_key( $meta_key ) ) {
            continue;
        }
        $ninja['meta'][ $meta_key ] = stpc_sanitize_value( maybe_unserialize( count( $values ) === 1 ? $values[0] : $values ), $meta_key );
    }
}

$pattern = $wpdb->esc_like( $wpdb->prefix ) . '%ninja%';
$table_names = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
foreach ( $table_names as $table_name ) {
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
    if ( ! in_array( 'table_id', $columns, true ) ) {
        continue;
    }

    $ninja['storage_table'] = $table_name;
    $rows = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE table_id = %d ORDER BY 1 ASC LIMIT 500", 142 ),
        ARRAY_A
    );
    foreach ( $rows as $row ) {
        $ninja['rows'][] = stpc_sanitize_value( $row );
    }
    break;
}

$report = array(
    'generated_at_utc' => gmdate( 'c' ),
    'public_posts'     => $public_posts,
    'ninja_table_142'  => $ninja,
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
