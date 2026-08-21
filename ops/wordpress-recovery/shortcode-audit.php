<?php
/**
 * WP-CLI eval-file script: locate content that depends on inactive plugins.
 * Output is CSV and does not modify content.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

global $wpdb;

$patterns = array(
    'ninja_tables'        => '/\[ninja_tables\b/i',
    'jetpack_form'        => '/\[(?:contact-form|contact-field)\b/i',
    'iframe'              => '/\[iframe\b/i',
    'shortcodes_ultimate' => '/\[su_[a-z0-9_-]+\b/i',
    'tabs'                => '/\[(?:tabby|tabs?|responsive_tabs?|wpsm_accordion)\b/i',
    'team_members'        => '/\[(?:team-members?|tmm)\b/i',
    'events_calendar'     => '/\[(?:tribe_events|tribe_event|event_calendar)\b/i',
    'tournament'          => '/\[(?:tournament|bracket)[a-z0-9_-]*\b/i',
    'pdf_embedder'        => '/\[(?:pdf-embedder|pdf_embedder)\b/i',
    'wpforms'             => '/\[wpforms\b/i',
);

$posts = $wpdb->get_results(
    "SELECT ID, post_type, post_status, post_title, post_content
     FROM {$wpdb->posts}
     WHERE post_content LIKE '%[%' AND post_status NOT IN ('auto-draft', 'inherit')
     ORDER BY ID ASC"
);

$handle = fopen( 'php://output', 'w' );
fputcsv( $handle, array( 'post_id', 'post_type', 'post_status', 'post_title', 'dependency', 'permalink' ) );

foreach ( $posts as $post ) {
    foreach ( $patterns as $dependency => $pattern ) {
        if ( preg_match( $pattern, $post->post_content ) ) {
            fputcsv(
                $handle,
                array(
                    $post->ID,
                    $post->post_type,
                    $post->post_status,
                    $post->post_title,
                    $dependency,
                    get_permalink( $post->ID ),
                )
            );
        }
    }
}

fclose( $handle );
