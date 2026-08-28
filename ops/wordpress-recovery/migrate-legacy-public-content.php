<?php
/**
 * WP-CLI eval-file migration for the final public shortcode dependencies.
 *
 * Environment:
 *   ST_MIGRATION_MODE=apply|rollback
 *   ST_MIGRATION_BACKUP_DIR=/private/path
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

$mode       = getenv( 'ST_MIGRATION_MODE' ) ?: 'apply';
$backup_dir = getenv( 'ST_MIGRATION_BACKUP_DIR' ) ?: '';

if ( ! in_array( $mode, array( 'apply', 'rollback' ), true ) ) {
    fwrite( STDERR, "Unsupported migration mode.\n" );
    exit( 1 );
}
if ( '' === $backup_dir || ! is_dir( $backup_dir ) || ! is_writable( $backup_dir ) ) {
    fwrite( STDERR, "A private writable ST_MIGRATION_BACKUP_DIR is required.\n" );
    exit( 1 );
}

$donation_id = 196;
$event_id    = 2932;
$donation_backup = trailingslashit( $backup_dir ) . 'post-196-before.txt';
$event_backup    = trailingslashit( $backup_dir ) . 'post-2932-before.txt';
$metadata_file   = trailingslashit( $backup_dir ) . 'content-backup-metadata.json';

if ( 'rollback' === $mode ) {
    if ( ! is_file( $donation_backup ) || ! is_file( $event_backup ) ) {
        fwrite( STDERR, "Rollback content files are missing.\n" );
        exit( 1 );
    }

    $restore = array(
        $donation_id => file_get_contents( $donation_backup ),
        $event_id    => file_get_contents( $event_backup ),
    );

    foreach ( $restore as $post_id => $content ) {
        $result = wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => $content,
            ),
            true
        );
        if ( is_wp_error( $result ) ) {
            fwrite( STDERR, sprintf( "Rollback failed for post %d: %s\n", $post_id, $result->get_error_message() ) );
            exit( 1 );
        }
    }

    echo wp_json_encode(
        array(
            'mode'              => 'rollback',
            'restored_post_ids' => array( $donation_id, $event_id ),
            'success'           => true,
        ),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
    exit( 0 );
}

$donation = get_post( $donation_id );
$event    = get_post( $event_id );
if ( ! $donation || 'page' !== $donation->post_type || 'publish' !== $donation->post_status ) {
    fwrite( STDERR, "The expected published donation page was not found.\n" );
    exit( 1 );
}
if ( ! $event || 'tribe_events' !== $event->post_type || 'publish' !== $event->post_status ) {
    fwrite( STDERR, "The expected published Palm Sunday event was not found.\n" );
    exit( 1 );
}

$donation_content = (string) $donation->post_content;
$event_content    = (string) $event->post_content;

$donation_already_migrated = false !== strpos( $donation_content, 'st-thekla-native-donation-options' )
    && false === strpos( $donation_content, '[su_' );
$event_already_migrated = false !== strpos( $event_content, 'Palm Sunday 2024 service booklet (PDF)' )
    && false === strpos( $event_content, '[pdf-embedder' );

if ( $donation_already_migrated && $event_already_migrated ) {
    echo wp_json_encode(
        array(
            'mode'                   => 'apply',
            'already_migrated'       => true,
            'donation_post_id'       => $donation_id,
            'event_post_id'          => $event_id,
            'remaining_su_shortcode' => false,
            'remaining_pdf_shortcode'=> false,
            'success'                => true,
        ),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
    exit( 0 );
}

if ( false === strpos( $donation_content, '[su_lightbox' )
    || false === strpos( $donation_content, 'KQXSC8WTH7TXJ' ) ) {
    fwrite( STDERR, "Donation page preflight failed because its legacy content changed unexpectedly.\n" );
    exit( 1 );
}
if ( false === strpos( $event_content, '[pdf-embedder' )
    || false === strpos( $event_content, 'Palm-Sunday-2024-DRAFT-1.pdf' ) ) {
    fwrite( STDERR, "Palm Sunday event preflight failed because its legacy content changed unexpectedly.\n" );
    exit( 1 );
}

if ( false === file_put_contents( $donation_backup, $donation_content, LOCK_EX )
    || false === file_put_contents( $event_backup, $event_content, LOCK_EX ) ) {
    fwrite( STDERR, "Unable to save private rollback content.\n" );
    exit( 1 );
}

$metadata = array(
    'created_at_utc' => gmdate( 'c' ),
    'posts'          => array(
        array(
            'id'            => $donation_id,
            'post_type'     => $donation->post_type,
            'post_status'   => $donation->post_status,
            'title'         => $donation->post_title,
            'content_sha256'=> hash( 'sha256', $donation_content ),
        ),
        array(
            'id'            => $event_id,
            'post_type'     => $event->post_type,
            'post_status'   => $event->post_status,
            'title'         => $event->post_title,
            'content_sha256'=> hash( 'sha256', $event_content ),
        ),
    ),
);
file_put_contents( $metadata_file, wp_json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL, LOCK_EX );

$church_email = 'sainttheklachurch@gmail.com';
$paypal_url   = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=KQXSC8WTH7TXJ&source=url';
$pdf_url      = 'https://www.sttheklachurch.org/wp-content/uploads/2024/03/Palm-Sunday-2024-DRAFT-1.pdf';

$new_donation_content = <<<'HTML'
<!-- wp:group {"className":"st-thekla-native-donation-options","layout":{"type":"constrained"}} -->
<div class="wp-block-group st-thekla-native-donation-options">
<!-- wp:paragraph -->
<p>Thank you for supporting St. Thekla Malankara Orthodox Church. Some payment services deduct processing fees, so please choose the donation method that works best for you.</p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Donate with Chase QuickPay</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>From your Chase account, send the donation to <strong><a href="mailto:sainttheklachurch@gmail.com">sainttheklachurch@gmail.com</a></strong>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Please include your name and the purpose of the donation in the payment memo when possible.</p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Donate securely with PayPal</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Use the secure PayPal donation page below. PayPal will open in a new browser tab.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=KQXSC8WTH7TXJ&amp;source=url" target="_blank" rel="noreferrer noopener">Donate with PayPal</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph -->
<p>For a donation receipt or help with a payment, contact <a href="mailto:sainttheklachurch@gmail.com">sainttheklachurch@gmail.com</a>.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
HTML;

$new_event_content = <<<'HTML'
<!-- wp:paragraph -->
<p><a href="https://www.sttheklachurch.org/wp-content/uploads/2024/03/Palm-Sunday-2024-DRAFT-1.pdf" target="_blank" rel="noreferrer noopener">View or download the Palm Sunday 2024 service booklet (PDF)</a>.</p>
<!-- /wp:paragraph -->
HTML;

$updates = array(
    $donation_id => $new_donation_content,
    $event_id    => $new_event_content,
);
foreach ( $updates as $post_id => $content ) {
    $result = wp_update_post(
        array(
            'ID'           => $post_id,
            'post_content' => $content,
        ),
        true
    );
    if ( is_wp_error( $result ) ) {
        fwrite( STDERR, sprintf( "Migration failed for post %d: %s\n", $post_id, $result->get_error_message() ) );
        exit( 1 );
    }
}

$updated_donation = (string) get_post_field( 'post_content', $donation_id );
$updated_event    = (string) get_post_field( 'post_content', $event_id );

$checks = array(
    'donation_marker_present'       => false !== strpos( $updated_donation, 'st-thekla-native-donation-options' ),
    'donation_email_present'        => false !== strpos( $updated_donation, $church_email ),
    'donation_paypal_button_present'=> false !== strpos( $updated_donation, 'KQXSC8WTH7TXJ' ),
    'donation_su_shortcode_absent'  => false === strpos( $updated_donation, '[su_' ),
    'event_pdf_link_present'        => false !== strpos( $updated_event, $pdf_url ),
    'event_pdf_label_present'       => false !== strpos( $updated_event, 'Palm Sunday 2024 service booklet (PDF)' ),
    'event_pdf_shortcode_absent'    => false === strpos( $updated_event, '[pdf-embedder' ),
);
$failed = array_keys( array_filter( $checks, static function ( $passed ) {
    return ! $passed;
} ) );

$report = array(
    'mode'                    => 'apply',
    'already_migrated'        => false,
    'donation_post_id'        => $donation_id,
    'event_post_id'           => $event_id,
    'donation_content_sha256' => hash( 'sha256', $updated_donation ),
    'event_content_sha256'    => hash( 'sha256', $updated_event ),
    'checks'                  => $checks,
    'failed'                  => $failed,
    'success'                 => empty( $failed ),
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
exit( empty( $failed ) ? 0 : 2 );
