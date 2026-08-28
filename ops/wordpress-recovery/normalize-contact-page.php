<?php
/**
 * WP-CLI eval-file script: normalize the public Contact Us page around the
 * already-created WPForms shortcode and the church's current Nyack address.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

$page_id = (int) ( getenv( 'ST_CONTACT_PAGE_ID' ) ?: 7 );
$page    = get_post( $page_id );

if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
    fwrite( STDERR, "Contact page not found.\n" );
    exit( 1 );
}

if ( ! current_user_can( 'manage_options' ) ) {
    fwrite( STDERR, "An administrator context is required.\n" );
    exit( 1 );
}

if ( ! preg_match( '/\[wpforms\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i', $page->post_content, $match ) ) {
    fwrite( STDERR, "The Contact page does not contain a WPForms shortcode.\n" );
    exit( 1 );
}

$form_id   = (int) $match[1];
$shortcode = sprintf( '[wpforms id="%d" title="false" description="false"]', $form_id );

$new_content = <<<HTML
<p>Hi There,</p>
<p>We are looking forward to hearing from you. Please use the form below and we will get back to you as soon as possible.</p>
<p><strong>ST. THEKLA MALANKARA ORTHODOX CHURCH</strong><br>
St. Thomas Lutheran Church<br>
2 Old Ox Road<br>
Nyack, NY 10960</p>
<p><strong>Telephone:</strong> (914) 349-4GOD</p>
{$shortcode}
HTML;

$result = wp_update_post(
    array(
        'ID'           => $page_id,
        'post_content' => $new_content,
    ),
    true
);

if ( is_wp_error( $result ) ) {
    fwrite( STDERR, 'Unable to update Contact page: ' . $result->get_error_message() . "\n" );
    exit( 1 );
}

clean_post_cache( $page_id );
$saved = (string) get_post_field( 'post_content', $page_id );

$checks = array(
    'form_id'                     => $form_id,
    'wpforms_shortcode_present'   => false !== strpos( $saved, '[wpforms' ),
    'current_address_present'     => false !== strpos( $saved, '2 Old Ox Road' ),
    'current_city_present'        => false !== strpos( $saved, 'Nyack, NY 10960' ),
    'legacy_address_absent'       => false === stripos( $saved, '107 Strawtown Road' ),
    'legacy_city_absent'          => false === stripos( $saved, 'West Nyack' ),
    'completed_at_utc'            => gmdate( 'c' ),
);

if (
    ! $checks['wpforms_shortcode_present'] ||
    ! $checks['current_address_present'] ||
    ! $checks['current_city_present'] ||
    ! $checks['legacy_address_absent'] ||
    ! $checks['legacy_city_absent']
) {
    fwrite( STDERR, "Contact page verification failed after update.\n" );
    exit( 1 );
}

echo wp_json_encode( $checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
