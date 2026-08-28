<?php
/**
 * Backup-gated content and option migration for the completed St. Thekla move.
 * Run with WP-CLI and ST_MOVE_MODE=snapshot|apply|verify|rollback.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run through WP-CLI.\n" );
    exit( 1 );
}

global $wpdb;

$mode          = getenv( 'ST_MOVE_MODE' ) ?: '';
$snapshot_file = getenv( 'ST_MOVE_SNAPSHOT_FILE' ) ?: '';
$exterior_id   = absint( getenv( 'ST_MOVE_EXTERIOR_ID' ) ?: 0 );
$parking_id    = absint( getenv( 'ST_MOVE_PARKING_ID' ) ?: 0 );

function stm_fail( $message ) {
    fwrite( STDERR, 'ERROR: ' . $message . "\n" );
    exit( 2 );
}

function stm_json( $value ) {
    echo wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
}

function stm_find_page( $path, $fallback_id = 0 ) {
    $page = get_page_by_path( $path, OBJECT, 'page' );
    if ( $page instanceof WP_Post ) {
        return $page;
    }
    if ( $fallback_id ) {
        $fallback = get_post( $fallback_id );
        if ( $fallback instanceof WP_Post && 'page' === $fallback->post_type ) {
            return $fallback;
        }
    }
    return null;
}

function stm_post_state( $post ) {
    if ( ! $post instanceof WP_Post ) {
        return null;
    }
    return array(
        'ID'             => (int) $post->ID,
        'post_author'    => (int) $post->post_author,
        'post_date'      => $post->post_date,
        'post_date_gmt'  => $post->post_date_gmt,
        'post_content'   => $post->post_content,
        'post_title'     => $post->post_title,
        'post_excerpt'   => $post->post_excerpt,
        'post_status'    => $post->post_status,
        'comment_status' => $post->comment_status,
        'ping_status'    => $post->ping_status,
        'post_password'  => $post->post_password,
        'post_name'      => $post->post_name,
        'post_parent'    => (int) $post->post_parent,
        'menu_order'     => (int) $post->menu_order,
        'post_type'      => $post->post_type,
    );
}

function stm_restore_post( $state ) {
    if ( ! is_array( $state ) || empty( $state['ID'] ) ) {
        return;
    }
    $result = wp_update_post( wp_slash( $state ), true );
    if ( is_wp_error( $result ) ) {
        stm_fail( 'Unable to restore post ' . (int) $state['ID'] . ': ' . $result->get_error_message() );
    }
}

function stm_capture_meta( $post_id ) {
    return get_post_meta( $post_id );
}

function stm_restore_meta( $post_id, $meta ) {
    $current = get_post_meta( $post_id );
    foreach ( array_keys( $current ) as $key ) {
        delete_post_meta( $post_id, $key );
    }
    if ( ! is_array( $meta ) ) {
        return;
    }
    foreach ( $meta as $key => $values ) {
        foreach ( (array) $values as $value ) {
            add_post_meta( $post_id, $key, maybe_unserialize( $value ) );
        }
    }
}

function stm_option_state( $key ) {
    $value = get_option( $key, null );
    return array(
        'exists' => null !== $value,
        'value'  => $value,
    );
}

function stm_restore_option( $key, $state ) {
    if ( ! empty( $state['exists'] ) ) {
        update_option( $key, $state['value'], false );
    } else {
        delete_option( $key );
    }
}

function stm_find_announcement() {
    $posts = get_posts(
        array(
            'post_type'      => 'stc_announcement',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
            'posts_per_page' => 10,
            's'              => 'St. Thekla Has a New Home',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        )
    );
    foreach ( $posts as $post ) {
        if ( 'St. Thekla Has a New Home' === $post->post_title ) {
            return $post;
        }
    }
    return null;
}

function stm_find_menu() {
    $locations = get_nav_menu_locations();
    foreach ( array( 'primary', 'menu-1', 'top' ) as $location ) {
        if ( ! empty( $locations[ $location ] ) ) {
            $menu = wp_get_nav_menu_object( (int) $locations[ $location ] );
            if ( $menu ) {
                return $menu;
            }
        }
    }
    $menu = wp_get_nav_menu_object( 'Main' );
    if ( $menu ) {
        return $menu;
    }
    $menus = wp_get_nav_menus();
    return ! empty( $menus ) ? $menus[0] : null;
}

function stm_schedule_expected() {
    return array(
        array( 'time' => '8:00 AM',  'description' => 'Lilyo' ),
        array( 'time' => '8:30 AM',  'description' => 'Morning Prayer' ),
        array( 'time' => '9:00 AM',  'description' => 'Holy Qurbana' ),
        array( 'time' => '10:10 AM', 'description' => 'Dismissal' ),
        array( 'time' => '10:30 AM', 'description' => 'Refreshments / Fellowship' ),
        array( 'time' => '10:45 AM', 'description' => 'Tree of Life' ),
        array( 'time' => '11:30 AM', 'description' => 'End of Tree of Life' ),
    );
}

function stm_has_old_location( $value ) {
    $value = strtolower( (string) $value );
    foreach ( array( '107 strawtown road', 'west nyack', '2 old ox road', 'st. thomas lutheran church', 'st thomas lutheran church', 'nyack, ny 10960', 'nyack, new york 10960' ) as $needle ) {
        if ( false !== strpos( $value, $needle ) ) {
            return true;
        }
    }
    return false;
}

function stm_save_snapshot( $path, $snapshot ) {
    if ( ! $path ) {
        stm_fail( 'Snapshot path is missing.' );
    }
    if ( false === file_put_contents( $path, serialize( $snapshot ) ) ) {
        stm_fail( 'Unable to write private snapshot.' );
    }
    chmod( $path, 0600 );
}

function stm_load_snapshot( $path ) {
    if ( ! $path || ! is_file( $path ) ) {
        stm_fail( 'Private snapshot was not found.' );
    }
    $snapshot = unserialize( file_get_contents( $path ), array( 'allowed_classes' => false ) );
    if ( ! is_array( $snapshot ) ) {
        stm_fail( 'Private snapshot is invalid.' );
    }
    return $snapshot;
}

if ( 'snapshot' === $mode ) {
    $home_id = (int) get_option( 'page_on_front', 0 );
    $home = $home_id ? get_post( $home_id ) : null;
    if ( ! $home instanceof WP_Post ) {
        $home = stm_find_page( 'home', 5 );
    }
    $contact = stm_find_page( 'contact-us', 7 );
    if ( ! $home || ! $contact ) {
        stm_fail( 'Required Home or Contact Us page was not found.' );
    }

    $visit = stm_find_page( 'visit-us' );
    $announcement = stm_find_announcement();
    $menu = stm_find_menu();

    $snapshot = array(
        'created_at_utc' => gmdate( 'c' ),
        'options' => array(
            'stc_settings'        => stm_option_state( 'stc_settings' ),
            'stc_weekly_schedule' => stm_option_state( 'stc_weekly_schedule' ),
            'stc_visit_settings'  => stm_option_state( 'stc_visit_settings' ),
            'stc_data_version'    => stm_option_state( 'stc_data_version' ),
        ),
        'home'          => stm_post_state( $home ),
        'contact'       => stm_post_state( $contact ),
        'visit'         => stm_post_state( $visit ),
        'announcement'  => stm_post_state( $announcement ),
        'announcement_meta' => $announcement ? stm_capture_meta( $announcement->ID ) : array(),
        'menu_id'       => $menu ? (int) $menu->term_id : 0,
        'created'       => array(
            'visit_page_id'   => 0,
            'announcement_id' => 0,
            'menu_item_id'    => 0,
        ),
    );
    stm_save_snapshot( $snapshot_file, $snapshot );
    stm_json(
        array(
            'home_id'               => (int) $home->ID,
            'contact_id'            => (int) $contact->ID,
            'visit_page_exists'     => (bool) $visit,
            'announcement_exists'   => (bool) $announcement,
            'menu_id'               => $snapshot['menu_id'],
            'snapshot_contains_pii' => false,
        )
    );
    exit( 0 );
}

if ( 'apply' === $mode ) {
    $snapshot = stm_load_snapshot( $snapshot_file );
    if ( ! $exterior_id || ! wp_attachment_is_image( $exterior_id ) || ! $parking_id || ! wp_attachment_is_image( $parking_id ) ) {
        stm_fail( 'Approved exterior and parking images were not imported correctly.' );
    }
    if ( '003' !== (string) get_option( 'stc_data_version', '' ) ) {
        stm_fail( 'Site Core data migration 003 did not complete.' );
    }

    $settings = get_option( 'stc_settings', array() );
    $settings = is_array( $settings ) ? $settings : array();
    if ( empty( $settings['phone'] ) && preg_match( '/Telephone\s*([^<\r\n]+)/i', $snapshot['contact']['post_content'], $match ) ) {
        $settings['phone'] = sanitize_text_field( trim( $match[1] ) );
        update_option( 'stc_settings', $settings, false );
    }

    $visit_page = stm_find_page( 'visit-us' );
    if ( ! $visit_page ) {
        $visit_id = wp_insert_post(
            array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'post_title'     => 'Visit Us',
                'post_name'      => 'visit-us',
                'post_content'   => "<!-- wp:shortcode -->\n[st_visit_us]\n<!-- /wp:shortcode -->",
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ),
            true
        );
        if ( is_wp_error( $visit_id ) ) {
            stm_fail( 'Unable to create Visit Us page: ' . $visit_id->get_error_message() );
        }
        $snapshot['created']['visit_page_id'] = (int) $visit_id;
        stm_save_snapshot( $snapshot_file, $snapshot );
    } else {
        $visit_id = (int) $visit_page->ID;
        $updated = wp_update_post(
            wp_slash(
                array(
                    'ID'             => $visit_id,
                    'post_title'     => 'Visit Us',
                    'post_name'      => 'visit-us',
                    'post_status'    => 'publish',
                    'post_content'   => "<!-- wp:shortcode -->\n[st_visit_us]\n<!-- /wp:shortcode -->",
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                )
            ),
            true
        );
        if ( is_wp_error( $updated ) ) {
            stm_fail( 'Unable to update Visit Us page: ' . $updated->get_error_message() );
        }
    }
    $visit_url = get_permalink( $visit_id );

    $visit_settings = get_option( 'stc_visit_settings', array() );
    $visit_settings = is_array( $visit_settings ) ? $visit_settings : array();
    $visit_settings = array_merge(
        $visit_settings,
        array(
            'move_effective_date'       => '2026-08-23',
            'move_announcement_enabled' => '1',
            'visit_page_url'            => $visit_url,
            'visit_image_id'            => $exterior_id,
            'parking_map_image_id'      => $parking_id,
            'parking_notes'             => 'Please use only the designated parking areas shown on the map. The chapel entrance and exit are marked in blue. Please do not park in areas marked in red. Additional parking, St. Martin Hall and restrooms are also identified on the map.',
        )
    );
    update_option( 'stc_visit_settings', $visit_settings, false );

    $home_tail = '';
    if ( preg_match( '/<!-- wp:image \{[^\n]*"id":2641.*?<!-- \/wp:image -->/s', $snapshot['home']['post_content'], $tail_match ) ) {
        $home_tail = "\n\n" . $tail_match[0];
    }
    $home_content = <<<'HTML'
<!-- wp:paragraph -->
<p>We welcome all who seek to know and experience Christ. The beauty of Orthodox worship must be experienced to be understood, therefore we invite you to visit. Whether you wish to learn more about Orthodoxy, or you are already an Orthodox Christian seeking a parish, or you are just visiting, you will find something to meet your needs in our Church. Our parishioners are all ages, families and individuals, and we come from a variety of ethnic and non-ethnic backgrounds.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Whether you live in the area, or are seeking to experience the practice of the Church for the first time, we welcome you to join us and participate.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Following Holy Qurbana, we gather for refreshments, fellowship and Tree of Life. Visitors are warmly invited to join us.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Thank you for visiting our website. Please review our new Sparkill location, Sunday schedule, directions and parking information below.</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:shortcode -->
[st_visit_us layout="compact"]
<!-- /wp:shortcode -->
HTML;
    $home_content .= $home_tail;
    $home_update = wp_update_post(
        wp_slash(
            array(
                'ID'             => (int) $snapshot['home']['ID'],
                'post_content'   => $home_content,
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            )
        ),
        true
    );
    if ( is_wp_error( $home_update ) ) {
        stm_fail( 'Unable to update the homepage: ' . $home_update->get_error_message() );
    }

    if ( ! preg_match( '/\[wpforms\b[^\]]*\]/i', $snapshot['contact']['post_content'], $form_match ) ) {
        stm_fail( 'Existing WPForms shortcode was not found on Contact Us.' );
    }
    $contact_content = '<!-- wp:paragraph -->' . "\n" . '<p>We look forward to hearing from you. Please use the form below and we will respond as soon as possible.</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n"
        . '<!-- wp:shortcode -->' . "\n" . '[st_church_location]' . "\n" . '<!-- /wp:shortcode -->' . "\n\n"
        . '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Sunday Schedule</h2>' . "\n" . '<!-- /wp:heading -->' . "\n\n"
        . '<!-- wp:shortcode -->' . "\n" . '[st_weekly_schedule]' . "\n" . '<!-- /wp:shortcode -->' . "\n\n"
        . '<!-- wp:paragraph -->' . "\n" . '<p><a href="' . esc_url( $visit_url ) . '">View directions, parking and arrival information.</a></p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n"
        . '<!-- wp:shortcode -->' . "\n" . $form_match[0] . "\n" . '<!-- /wp:shortcode -->';
    $contact_update = wp_update_post(
        wp_slash(
            array(
                'ID'             => (int) $snapshot['contact']['ID'],
                'post_content'   => $contact_content,
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            )
        ),
        true
    );
    if ( is_wp_error( $contact_update ) ) {
        stm_fail( 'Unable to update Contact Us: ' . $contact_update->get_error_message() );
    }

    $announcement = stm_find_announcement();
    $announcement_content = '<p>St. Thekla Malankara Orthodox Church is now worshiping at Sacred Heart Chapel, 175 Route 340, Sparkill, NY 10976. Join us Sundays for Lilyo at 8:00 AM, Morning Prayer at 8:30 AM, Holy Qurbana at 9:00 AM, refreshments, fellowship and Tree of Life.</p><p><a href="' . esc_url( $visit_url ) . '">Plan your visit and view the parking map.</a></p>';
    if ( ! $announcement ) {
        $announcement_id = wp_insert_post(
            array(
                'post_type'      => 'stc_announcement',
                'post_status'    => 'publish',
                'post_title'     => 'St. Thekla Has a New Home',
                'post_content'   => $announcement_content,
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ),
            true
        );
        if ( is_wp_error( $announcement_id ) ) {
            stm_fail( 'Unable to create move announcement: ' . $announcement_id->get_error_message() );
        }
        $snapshot['created']['announcement_id'] = (int) $announcement_id;
        stm_save_snapshot( $snapshot_file, $snapshot );
    } else {
        $announcement_id = (int) $announcement->ID;
        $announcement_update = wp_update_post(
            wp_slash(
                array(
                    'ID'             => $announcement_id,
                    'post_status'    => 'publish',
                    'post_title'     => 'St. Thekla Has a New Home',
                    'post_content'   => $announcement_content,
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                )
            ),
            true
        );
        if ( is_wp_error( $announcement_update ) ) {
            stm_fail( 'Unable to update move announcement: ' . $announcement_update->get_error_message() );
        }
    }
    update_post_meta( $announcement_id, '_stc_announcement_priority', 'important' );
    update_post_meta( $announcement_id, '_stc_announcement_start', '2026-08-23' );
    delete_post_meta( $announcement_id, '_stc_announcement_end' );
    set_post_thumbnail( $announcement_id, $exterior_id );

    $menu = $snapshot['menu_id'] ? wp_get_nav_menu_object( (int) $snapshot['menu_id'] ) : stm_find_menu();
    if ( ! $menu ) {
        stm_fail( 'Primary navigation menu was not found.' );
    }
    $existing_item = 0;
    foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
        if ( ( 'page' === $item->object && (int) $item->object_id === (int) $visit_id ) || untrailingslashit( $item->url ) === untrailingslashit( $visit_url ) ) {
            $existing_item = (int) $item->ID;
            break;
        }
    }
    if ( ! $existing_item ) {
        $menu_item_id = wp_update_nav_menu_item(
            $menu->term_id,
            0,
            array(
                'menu-item-title'     => 'Visit Us',
                'menu-item-object-id' => (int) $visit_id,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            )
        );
        if ( is_wp_error( $menu_item_id ) ) {
            stm_fail( 'Unable to add Visit Us to the primary navigation: ' . $menu_item_id->get_error_message() );
        }
        $snapshot['created']['menu_item_id'] = (int) $menu_item_id;
        stm_save_snapshot( $snapshot_file, $snapshot );
    }

    stm_save_snapshot( $snapshot_file, $snapshot );
    clean_post_cache( (int) $snapshot['home']['ID'] );
    clean_post_cache( (int) $snapshot['contact']['ID'] );
    clean_post_cache( (int) $visit_id );
    stm_json(
        array(
            'home_id'          => (int) $snapshot['home']['ID'],
            'contact_id'       => (int) $snapshot['contact']['ID'],
            'visit_page_id'    => (int) $visit_id,
            'announcement_id'  => (int) $announcement_id,
            'menu_id'          => (int) $menu->term_id,
            'menu_item_id'     => $existing_item ?: (int) $snapshot['created']['menu_item_id'],
            'exterior_image_id'=> $exterior_id,
            'parking_image_id' => $parking_id,
        )
    );
    exit( 0 );
}

if ( 'verify' === $mode ) {
    $snapshot = stm_load_snapshot( $snapshot_file );
    $settings = get_option( 'stc_settings', array() );
    $visit_settings = get_option( 'stc_visit_settings', array() );
    $schedule = get_option( 'stc_weekly_schedule', array() );
    $home = get_post( (int) $snapshot['home']['ID'] );
    $contact = get_post( (int) $snapshot['contact']['ID'] );
    $visit = stm_find_page( 'visit-us' );
    $announcement = stm_find_announcement();
    $menu = stm_find_menu();

    $checks = array(
        'plugin_version_0_3_0'       => defined( 'STC_VERSION' ) && '0.3.0' === STC_VERSION,
        'data_version_003'           => '003' === (string) get_option( 'stc_data_version', '' ),
        'address_chapel'             => 'Sacred Heart Chapel' === (string) ( $settings['address_line_1'] ?? '' ),
        'address_street'             => '175 Route 340' === (string) ( $settings['address_line_2'] ?? '' ),
        'address_city'               => 'Sparkill' === (string) ( $settings['city'] ?? '' ),
        'address_state_zip'          => 'NY' === (string) ( $settings['state'] ?? '' ) && '10976' === (string) ( $settings['zip'] ?? '' ),
        'schedule_exact'             => stm_schedule_expected() === $schedule,
        'move_date'                  => '2026-08-23' === (string) ( $visit_settings['move_effective_date'] ?? '' ),
        'move_enabled'               => '1' === (string) ( $visit_settings['move_announcement_enabled'] ?? '' ),
        'exterior_image'             => $exterior_id && (int) ( $visit_settings['visit_image_id'] ?? 0 ) === $exterior_id && wp_attachment_is_image( $exterior_id ),
        'parking_image'              => $parking_id && (int) ( $visit_settings['parking_map_image_id'] ?? 0 ) === $parking_id && wp_attachment_is_image( $parking_id ),
        'home_compact_component'     => $home && false !== strpos( $home->post_content, '[st_visit_us layout="compact"]' ),
        'home_no_old_location'       => $home && ! stm_has_old_location( $home->post_content ),
        'home_no_ninja_shortcode'    => $home && false === stripos( $home->post_content, '[ninja_tables' ),
        'contact_location_shortcode' => $contact && false !== strpos( $contact->post_content, '[st_church_location]' ),
        'contact_schedule_shortcode' => $contact && false !== strpos( $contact->post_content, '[st_weekly_schedule]' ),
        'contact_wpforms_preserved'  => $contact && (bool) preg_match( '/\[wpforms\b/i', $contact->post_content ),
        'contact_no_old_location'    => $contact && ! stm_has_old_location( $contact->post_content ),
        'visit_page_published'       => $visit && 'publish' === $visit->post_status && false !== strpos( $visit->post_content, '[st_visit_us]' ),
        'announcement_published'     => $announcement && 'publish' === $announcement->post_status,
        'announcement_priority'      => $announcement && 'important' === get_post_meta( $announcement->ID, '_stc_announcement_priority', true ),
        'announcement_image'         => $announcement && $exterior_id === (int) get_post_thumbnail_id( $announcement->ID ),
        'menu_item_present'          => false,
    );
    if ( $menu && $visit ) {
        foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
            if ( 'page' === $item->object && (int) $item->object_id === (int) $visit->ID ) {
                $checks['menu_item_present'] = true;
                break;
            }
        }
    }

    $old_page_matches = array();
    $published_pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
    foreach ( $published_pages as $page ) {
        if ( stm_has_old_location( $page->post_content ) ) {
            $old_page_matches[] = array( 'id' => (int) $page->ID, 'title' => $page->post_title );
        }
    }
    $checks['no_old_location_in_published_pages'] = empty( $old_page_matches );

    $failed = array_keys( array_filter( $checks, static function ( $passed ) { return ! $passed; } ) );
    stm_json( array( 'checks' => $checks, 'failed' => $failed, 'old_page_matches' => $old_page_matches ) );
    exit( empty( $failed ) ? 0 : 2 );
}

if ( 'rollback' === $mode ) {
    $snapshot = stm_load_snapshot( $snapshot_file );
    foreach ( $snapshot['options'] as $key => $state ) {
        stm_restore_option( $key, $state );
    }
    stm_restore_post( $snapshot['home'] );
    stm_restore_post( $snapshot['contact'] );

    if ( ! empty( $snapshot['created']['menu_item_id'] ) ) {
        wp_delete_post( (int) $snapshot['created']['menu_item_id'], true );
    }
    if ( ! empty( $snapshot['created']['visit_page_id'] ) ) {
        wp_delete_post( (int) $snapshot['created']['visit_page_id'], true );
    } elseif ( ! empty( $snapshot['visit'] ) ) {
        stm_restore_post( $snapshot['visit'] );
    }
    if ( ! empty( $snapshot['created']['announcement_id'] ) ) {
        wp_delete_post( (int) $snapshot['created']['announcement_id'], true );
    } elseif ( ! empty( $snapshot['announcement'] ) ) {
        stm_restore_post( $snapshot['announcement'] );
        stm_restore_meta( (int) $snapshot['announcement']['ID'], $snapshot['announcement_meta'] );
    }
    wp_cache_flush();
    stm_json( array( 'rollback' => 'completed', 'restored_home_id' => (int) $snapshot['home']['ID'], 'restored_contact_id' => (int) $snapshot['contact']['ID'] ) );
    exit( 0 );
}

stm_fail( 'Unsupported ST_MOVE_MODE.' );
