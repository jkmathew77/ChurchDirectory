<?php
/**
 * Included by public-content-audit.php. Returns a sanitized inventory of public
 * content owned by inactive legacy plugins and references to that content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    return array( 'error' => 'WordPress is not loaded.' );
}

global $wpdb;

$legacy_types = array(
    'tribe_events',
    'tribe_organizer',
    'tribe_venue',
    'tmm',
    'wpplugin_don_button',
);

$posts_by_type = array();
$all_targets   = array();

foreach ( $legacy_types as $post_type ) {
    $posts = get_posts(
        array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        )
    );

    $summaries = array();
    foreach ( $posts as $post ) {
        $meta = array();
        foreach ( get_post_meta( $post->ID ) as $meta_key => $values ) {
            if ( function_exists( 'stpc_sensitive_key' ) && stpc_sensitive_key( $meta_key ) ) {
                continue;
            }

            $include = false;
            if ( 0 === strpos( $post_type, 'tribe_' ) ) {
                $include = 0 === strpos( $meta_key, '_Event' )
                    || in_array( $meta_key, array( '_thumbnail_id' ), true );
            } elseif ( 'tmm' === $post_type || 'wpplugin_don_button' === $post_type ) {
                $include = true;
            }

            if ( ! $include ) {
                continue;
            }

            $value = maybe_unserialize( 1 === count( $values ) ? $values[0] : $values );
            $meta[ $meta_key ] = function_exists( 'stpc_sanitize_value' )
                ? stpc_sanitize_value( $value, $meta_key )
                : $value;
        }

        $content = function_exists( 'stpc_sanitize_value' )
            ? stpc_sanitize_value( $post->post_content, 'content' )
            : $post->post_content;
        $excerpt = preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
        $excerpt = substr( trim( $excerpt ), 0, 2000 );
        if ( function_exists( 'stpc_sanitize_value' ) ) {
            $excerpt = stpc_sanitize_value( $excerpt, 'content' );
        }

        $summary = array(
            'id'                => (int) $post->ID,
            'post_type'         => $post->post_type,
            'status'            => $post->post_status,
            'title'             => $post->post_title,
            'slug'              => $post->post_name,
            'published_gmt'     => $post->post_date_gmt,
            'modified_gmt'      => $post->post_modified_gmt,
            'permalink'         => get_permalink( $post->ID ),
            'shortcodes'        => function_exists( 'stpc_extract_shortcodes' ) ? stpc_extract_shortcodes( $post->post_content ) : array(),
            'sanitized_content' => $content,
            'text_excerpt'      => $excerpt,
            'meta'              => $meta,
        );
        $summaries[] = $summary;
        $all_targets[ $post->ID ] = $summary;
    }

    $posts_by_type[ $post_type ] = $summaries;
}

$references = array();
foreach ( $all_targets as $target_id => $target ) {
    $needles = array_filter(
        array(
            (string) $target_id,
            $target['slug'],
            $target['permalink'],
        ),
        static function ( $value ) {
            return '' !== (string) $value;
        }
    );

    $ref_posts = array();
    $candidate_rows = $wpdb->get_results(
        "SELECT ID, post_type, post_status, post_title, post_content
         FROM {$wpdb->posts}
         WHERE ID <> " . (int) $target_id . "
           AND post_status NOT IN ('auto-draft', 'inherit', 'trash')
         ORDER BY ID ASC"
    );
    foreach ( $candidate_rows as $candidate ) {
        foreach ( $needles as $needle ) {
            if ( false !== stripos( (string) $candidate->post_content, (string) $needle ) ) {
                $ref_posts[ $candidate->ID ] = array(
                    'id'        => (int) $candidate->ID,
                    'post_type' => $candidate->post_type,
                    'status'    => $candidate->post_status,
                    'title'     => $candidate->post_title,
                    'permalink' => get_permalink( $candidate->ID ),
                    'matched'   => is_numeric( $needle ) ? 'post_id' : ( $needle === $target['slug'] ? 'slug' : 'permalink' ),
                );
                break;
            }
        }
    }

    $menu_items = array();
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
        $menu_items[] = array(
            'id'    => (int) $menu_row->ID,
            'title' => $menu_row->post_title,
        );
    }

    $references[] = array(
        'target_id'        => (int) $target_id,
        'target_type'      => $target['post_type'],
        'target_title'     => $target['title'],
        'content_references'=> array_values( $ref_posts ),
        'menu_references'  => $menu_items,
    );
}

$feedback_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'feedback' AND post_status <> 'trash'"
);

return array(
    'posts_by_type' => $posts_by_type,
    'references'    => $references,
    'feedback_count'=> $feedback_count,
);
