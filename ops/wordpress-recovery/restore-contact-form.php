<?php
/**
 * WP-CLI eval-file script: create/update the St. Thekla contact form and
 * replace the inactive Jetpack form shortcode on the Contact Us page.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

$page_id     = (int) ( getenv( 'ST_CONTACT_PAGE_ID' ) ?: 7 );
$backup_file = (string) getenv( 'ST_CONTACT_BACKUP_FILE' );

if ( ! function_exists( 'wpforms' ) || ! wpforms() || ! wpforms()->form ) {
    fwrite( STDERR, "WPForms is not loaded.\n" );
    exit( 1 );
}

if ( ! current_user_can( 'manage_options' ) ) {
    fwrite( STDERR, "An administrator context is required.\n" );
    exit( 1 );
}

$page = get_post( $page_id );
if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
    fwrite( STDERR, "Contact page not found.\n" );
    exit( 1 );
}

if ( '' === $backup_file ) {
    fwrite( STDERR, "A private backup file path is required.\n" );
    exit( 1 );
}

if ( false === file_put_contents( $backup_file, $page->post_content ) ) {
    fwrite( STDERR, "Unable to preserve the original Contact page content.\n" );
    exit( 1 );
}
@chmod( $backup_file, 0600 );

$form_id               = 0;
$form_created          = false;
$original_form_content = null;
$page_changed          = false;

try {
    $existing_forms = get_posts(
        array(
            'post_type'      => 'wpforms',
            'post_status'    => array( 'publish', 'draft', 'trash' ),
            'posts_per_page' => 10,
            's'              => 'St. Thekla Contact Us',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        )
    );

    foreach ( $existing_forms as $candidate ) {
        if ( 'St. Thekla Contact Us' === $candidate->post_title ) {
            $form_id               = (int) $candidate->ID;
            $original_form_content = $candidate->post_content;
            if ( 'publish' !== $candidate->post_status ) {
                wp_update_post(
                    array(
                        'ID'          => $form_id,
                        'post_status' => 'publish',
                    )
                );
            }
            break;
        }
    }

    if ( ! $form_id ) {
        $created = wpforms()->form->add(
            'St. Thekla Contact Us',
            array(),
            array( 'builder' => false )
        );

        if ( is_wp_error( $created ) || ! $created ) {
            $message = is_wp_error( $created ) ? $created->get_error_message() : 'Unknown WPForms creation error.';
            throw new RuntimeException( $message );
        }

        $form_id      = (int) $created;
        $form_created = true;
    }

    $mail_settings = get_option( 'wp_mail_smtp', array() );
    $sender_email  = '';
    if (
        is_array( $mail_settings ) &&
        isset( $mail_settings['mail']['from_email'] ) &&
        is_email( $mail_settings['mail']['from_email'] )
    ) {
        $sender_email = (string) $mail_settings['mail']['from_email'];
    }
    if ( ! is_email( $sender_email ) ) {
        $sender_email = (string) get_option( 'admin_email', '' );
    }
    if ( ! is_email( $sender_email ) ) {
        throw new RuntimeException( 'No valid sender email is configured.' );
    }

    $form_data = array(
        'id'       => $form_id,
        'field_id' => 4,
        'fields'   => array(
            '1' => array(
                'id'       => '1',
                'type'     => 'name',
                'format'   => 'first-last',
                'label'    => 'Name',
                'required' => '1',
                'size'     => 'medium',
            ),
            '2' => array(
                'id'       => '2',
                'type'     => 'email',
                'label'    => 'Email',
                'required' => '1',
                'size'     => 'medium',
            ),
            '3' => array(
                'id'          => '3',
                'type'        => 'textarea',
                'label'       => 'Comment or Message',
                'required'    => '1',
                'size'        => 'medium',
                'placeholder' => '',
                'css'         => '',
            ),
        ),
        'settings' => array(
            'form_title'            => 'St. Thekla Contact Us',
            'form_desc'             => 'Send a message to St. Thekla Church.',
            'submit_text'           => 'Send Message',
            'submit_text_processing'=> 'Sending...',
            'antispam_v3'           => '1',
            'store_spam_entries'    => '0',
            'notification_enable'   => '1',
            'notifications'         => array(
                '1' => array(
                    'notification_name' => 'Church Website Inquiry',
                    'email'             => '{admin_email}',
                    'subject'           => 'New Website Inquiry: {field_id="1"}',
                    'sender_name'       => get_bloginfo( 'name' ),
                    'sender_address'    => $sender_email,
                    'replyto'           => '{field_id="2"}',
                    'message'           => '{all_fields}',
                ),
            ),
            'confirmations' => array(
                '1' => array(
                    'type'           => 'message',
                    'message'        => 'Thank you for contacting St. Thekla Church. We will respond as soon as possible.',
                    'message_scroll' => '1',
                ),
            ),
            'ajax_submit' => '1',
        ),
        'meta' => array(
            'template' => 'simple-contact-form-template',
        ),
    );

    $updated = wpforms()->form->update( $form_id, $form_data, array( 'cap' => false ) );
    if ( ! $updated ) {
        throw new RuntimeException( 'WPForms could not save the contact form.' );
    }

    $shortcode   = sprintf( '[wpforms id="%d" title="false" description="false"]', $form_id );
    $new_content = preg_replace(
        '/\[contact-form[^\]]*\].*?\[\/contact-form\]/is',
        $shortcode,
        $page->post_content,
        1,
        $replacement_count
    );

    if ( 0 === $replacement_count ) {
        if ( false === strpos( $page->post_content, '[wpforms' ) ) {
            throw new RuntimeException( 'The legacy Jetpack contact-form block was not found.' );
        }
        $new_content = preg_replace(
            '/\[wpforms\s+[^\]]*\]/i',
            $shortcode,
            $page->post_content,
            1,
            $replacement_count
        );
        if ( 0 === $replacement_count ) {
            throw new RuntimeException( 'The existing WPForms shortcode could not be updated.' );
        }
    }

    $page_update = wp_update_post(
        array(
            'ID'           => $page_id,
            'post_content' => $new_content,
        ),
        true
    );
    if ( is_wp_error( $page_update ) ) {
        throw new RuntimeException( $page_update->get_error_message() );
    }
    $page_changed = true;

    clean_post_cache( $page_id );
    $saved_content = get_post_field( 'post_content', $page_id );
    if ( false !== strpos( $saved_content, '[contact-form' ) ) {
        throw new RuntimeException( 'The legacy contact form remained in the saved page.' );
    }
    if ( false === strpos( $saved_content, '[wpforms' ) ) {
        throw new RuntimeException( 'The WPForms shortcode was not saved.' );
    }

    $rendered = do_shortcode( $shortcode );
    if (
        false === strpos( $rendered, 'wpforms-form-' . $form_id ) ||
        false === stripos( $rendered, '<form' ) ||
        false === strpos( $rendered, 'Comment or Message' )
    ) {
        throw new RuntimeException( 'The new contact form did not render correctly.' );
    }

    $report = array(
        'form_id'                  => $form_id,
        'form_created'             => $form_created,
        'page_id'                  => $page_id,
        'legacy_shortcode_removed' => true,
        'wpforms_shortcode_saved'  => true,
        'internal_render_verified' => true,
        'notification_enabled'     => true,
        'recipient_uses_admin_tag' => true,
        'sender_email_configured'  => true,
        'completed_at_utc'         => gmdate( 'c' ),
    );

    echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
} catch ( Throwable $error ) {
    if ( $page_changed ) {
        wp_update_post(
            array(
                'ID'           => $page_id,
                'post_content' => $page->post_content,
            )
        );
    }

    if ( $form_created && $form_id ) {
        wp_delete_post( $form_id, true );
    } elseif ( $form_id && null !== $original_form_content ) {
        wp_update_post(
            array(
                'ID'           => $form_id,
                'post_content' => $original_form_content,
            )
        );
    }

    fwrite( STDERR, 'Contact form restoration failed: ' . $error->getMessage() . "\n" );
    exit( 1 );
}
