<?php
/**
 * Core functionality for St. Thekla Site Core.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class STC_Plugin {

    private const OPTION_KEY = 'stc_settings';
    private const NONCE_ACTION = 'stc_save_meta';
    private const NONCE_NAME = 'stc_meta_nonce';

    /** @var STC_Plugin|null */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        $plugin = self::instance();
        $plugin->register_post_types();

        if ( false === get_option( self::OPTION_KEY ) ) {
            add_option(
                self::OPTION_KEY,
                array(
                    'church_name'    => 'St. Thekla Malankara Orthodox Church',
                    'address_line_1' => 'Sacred Heart Chapel',
                    'address_line_2' => '175 Route 340',
                    'city'           => 'Sparkill',
                    'state'          => 'NY',
                    'zip'            => '10976',
                    'phone'          => '',
                    'email'          => '',
                    'map_url'        => 'https://www.google.com/maps/search/?api=1&query=Sacred+Heart+Chapel+175+Route+340+Sparkill+NY+10976',
                    'donation_url'   => '',
                    'livestream_url' => '',
                )
            );
        }

        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ), 20 );
        add_action( 'init', array( $this, 'register_legacy_shortcodes' ), 100 );

        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_stc_service', array( $this, 'save_service_meta' ) );
        add_action( 'save_post_stc_leader', array( $this, 'save_leader_meta' ) );
        add_action( 'save_post_stc_announcement', array( $this, 'save_announcement_meta' ) );

        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        add_filter( 'manage_stc_service_posts_columns', array( $this, 'service_columns' ) );
        add_action( 'manage_stc_service_posts_custom_column', array( $this, 'render_service_column' ), 10, 2 );
        add_filter( 'manage_edit-stc_service_sortable_columns', array( $this, 'sortable_service_columns' ) );
        add_action( 'pre_get_posts', array( $this, 'service_admin_ordering' ) );
    }

    public function register_post_types() {
        register_post_type(
            'stc_service',
            array(
                'labels' => array(
                    'name'          => __( 'Services', 'st-thekla-site-core' ),
                    'singular_name' => __( 'Service', 'st-thekla-site-core' ),
                    'add_new_item'  => __( 'Add Service', 'st-thekla-site-core' ),
                    'edit_item'     => __( 'Edit Service', 'st-thekla-site-core' ),
                    'menu_name'     => __( 'Service Schedule', 'st-thekla-site-core' ),
                ),
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => true,
                'menu_icon'           => 'dashicons-calendar-alt',
                'supports'            => array( 'title', 'editor', 'thumbnail' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'has_archive'         => false,
                'rewrite'             => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
            )
        );

        register_post_type(
            'stc_leader',
            array(
                'labels' => array(
                    'name'          => __( 'Leadership', 'st-thekla-site-core' ),
                    'singular_name' => __( 'Leader', 'st-thekla-site-core' ),
                    'add_new_item'  => __( 'Add Leader', 'st-thekla-site-core' ),
                    'edit_item'     => __( 'Edit Leader', 'st-thekla-site-core' ),
                    'menu_name'     => __( 'Leadership', 'st-thekla-site-core' ),
                ),
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => true,
                'menu_icon'           => 'dashicons-groups',
                'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'has_archive'         => false,
                'rewrite'             => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
            )
        );

        register_post_type(
            'stc_announcement',
            array(
                'labels' => array(
                    'name'          => __( 'Announcements', 'st-thekla-site-core' ),
                    'singular_name' => __( 'Announcement', 'st-thekla-site-core' ),
                    'add_new_item'  => __( 'Add Announcement', 'st-thekla-site-core' ),
                    'edit_item'     => __( 'Edit Announcement', 'st-thekla-site-core' ),
                    'menu_name'     => __( 'Announcements', 'st-thekla-site-core' ),
                ),
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => true,
                'menu_icon'           => 'dashicons-megaphone',
                'supports'            => array( 'title', 'editor', 'thumbnail' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'has_archive'         => false,
                'rewrite'             => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
            )
        );
    }

    public function register_shortcodes() {
        add_shortcode( 'st_liturgy_schedule', array( $this, 'shortcode_schedule' ) );
        add_shortcode( 'st_leadership', array( $this, 'shortcode_leadership' ) );
        add_shortcode( 'st_church_location', array( $this, 'shortcode_location' ) );
        add_shortcode( 'st_announcements', array( $this, 'shortcode_announcements' ) );
    }

    public function register_legacy_shortcodes() {
        if ( ! shortcode_exists( 'ninja_tables' ) ) {
            add_shortcode( 'ninja_tables', array( $this, 'legacy_ninja_tables' ) );
        }
    }

    public function legacy_ninja_tables( $atts ) {
        $atts = shortcode_atts( array( 'id' => '' ), $atts, 'ninja_tables' );

        if ( '142' !== (string) $atts['id'] ) {
            if ( current_user_can( 'edit_pages' ) ) {
                return '<p class="stc-admin-warning">' . esc_html__( 'This legacy Ninja Tables shortcode has not been migrated.', 'st-thekla-site-core' ) . '</p>';
            }
            return '';
        }

        return $this->shortcode_schedule( array() );
    }

    public function add_meta_boxes() {
        add_meta_box( 'stc_service_details', __( 'Service Details', 'st-thekla-site-core' ), array( $this, 'render_service_meta_box' ), 'stc_service', 'normal', 'high' );
        add_meta_box( 'stc_leader_details', __( 'Leadership Details', 'st-thekla-site-core' ), array( $this, 'render_leader_meta_box' ), 'stc_leader', 'normal', 'high' );
        add_meta_box( 'stc_announcement_details', __( 'Announcement Timing', 'st-thekla-site-core' ), array( $this, 'render_announcement_meta_box' ), 'stc_announcement', 'side', 'default' );
    }

    public function render_service_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        $start     = get_post_meta( $post->ID, '_stc_start', true );
        $end       = get_post_meta( $post->ID, '_stc_end', true );
        $type      = get_post_meta( $post->ID, '_stc_service_type', true );
        $location  = get_post_meta( $post->ID, '_stc_location', true );
        $celebrant = get_post_meta( $post->ID, '_stc_celebrant', true );
        $cancelled = (bool) get_post_meta( $post->ID, '_stc_cancelled', true );
        ?>
        <table class="form-table" role="presentation">
            <tr><th><label for="stc_start"><?php esc_html_e( 'Start', 'st-thekla-site-core' ); ?></label></th><td><input type="datetime-local" class="regular-text" id="stc_start" name="stc_start" value="<?php echo esc_attr( $start ); ?>" required></td></tr>
            <tr><th><label for="stc_end"><?php esc_html_e( 'End', 'st-thekla-site-core' ); ?></label></th><td><input type="datetime-local" class="regular-text" id="stc_end" name="stc_end" value="<?php echo esc_attr( $end ); ?>"></td></tr>
            <tr><th><label for="stc_service_type"><?php esc_html_e( 'Service type', 'st-thekla-site-core' ); ?></label></th><td><input type="text" class="regular-text" id="stc_service_type" name="stc_service_type" value="<?php echo esc_attr( $type ); ?>" placeholder="Holy Qurbana"></td></tr>
            <tr><th><label for="stc_location"><?php esc_html_e( 'Location', 'st-thekla-site-core' ); ?></label></th><td><input type="text" class="regular-text" id="stc_location" name="stc_location" value="<?php echo esc_attr( $location ); ?>"></td></tr>
            <tr><th><label for="stc_celebrant"><?php esc_html_e( 'Celebrant / leader', 'st-thekla-site-core' ); ?></label></th><td><input type="text" class="regular-text" id="stc_celebrant" name="stc_celebrant" value="<?php echo esc_attr( $celebrant ); ?>"></td></tr>
            <tr><th><?php esc_html_e( 'Status', 'st-thekla-site-core' ); ?></th><td><label><input type="checkbox" name="stc_cancelled" value="1" <?php checked( $cancelled ); ?>> <?php esc_html_e( 'Cancelled', 'st-thekla-site-core' ); ?></label></td></tr>
        </table>
        <?php
    }

    public function render_leader_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        $title = get_post_meta( $post->ID, '_stc_leader_title', true );
        $term  = get_post_meta( $post->ID, '_stc_leader_term', true );
        ?>
        <table class="form-table" role="presentation">
            <tr><th><label for="stc_leader_title"><?php esc_html_e( 'Role / title', 'st-thekla-site-core' ); ?></label></th><td><input type="text" class="regular-text" id="stc_leader_title" name="stc_leader_title" value="<?php echo esc_attr( $title ); ?>" placeholder="Vicar"></td></tr>
            <tr><th><label for="stc_leader_term"><?php esc_html_e( 'Term or notes', 'st-thekla-site-core' ); ?></label></th><td><input type="text" class="regular-text" id="stc_leader_term" name="stc_leader_term" value="<?php echo esc_attr( $term ); ?>" placeholder="2026–2027"></td></tr>
        </table>
        <p><?php esc_html_e( 'Use the Page Order field to control display order and the Featured Image for the portrait.', 'st-thekla-site-core' ); ?></p>
        <?php
    }

    public function render_announcement_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        $start    = get_post_meta( $post->ID, '_stc_announcement_start', true );
        $end      = get_post_meta( $post->ID, '_stc_announcement_end', true );
        $priority = get_post_meta( $post->ID, '_stc_announcement_priority', true );
        ?>
        <p><label for="stc_announcement_start"><strong><?php esc_html_e( 'Start date', 'st-thekla-site-core' ); ?></strong></label><br><input type="date" id="stc_announcement_start" name="stc_announcement_start" value="<?php echo esc_attr( $start ); ?>"></p>
        <p><label for="stc_announcement_end"><strong><?php esc_html_e( 'End date', 'st-thekla-site-core' ); ?></strong></label><br><input type="date" id="stc_announcement_end" name="stc_announcement_end" value="<?php echo esc_attr( $end ); ?>"></p>
        <p><label for="stc_announcement_priority"><strong><?php esc_html_e( 'Priority', 'st-thekla-site-core' ); ?></strong></label><br>
            <select id="stc_announcement_priority" name="stc_announcement_priority">
                <option value="normal" <?php selected( $priority, 'normal' ); ?>><?php esc_html_e( 'Normal', 'st-thekla-site-core' ); ?></option>
                <option value="important" <?php selected( $priority, 'important' ); ?>><?php esc_html_e( 'Important', 'st-thekla-site-core' ); ?></option>
                <option value="urgent" <?php selected( $priority, 'urgent' ); ?>><?php esc_html_e( 'Urgent', 'st-thekla-site-core' ); ?></option>
            </select>
        </p>
        <?php
    }

    public function save_service_meta( $post_id ) {
        if ( ! $this->can_save_meta( $post_id ) ) {
            return;
        }
        $this->save_text_meta( $post_id, '_stc_start', 'stc_start' );
        $this->save_text_meta( $post_id, '_stc_end', 'stc_end' );
        $this->save_text_meta( $post_id, '_stc_service_type', 'stc_service_type' );
        $this->save_text_meta( $post_id, '_stc_location', 'stc_location' );
        $this->save_text_meta( $post_id, '_stc_celebrant', 'stc_celebrant' );
        update_post_meta( $post_id, '_stc_cancelled', isset( $_POST['stc_cancelled'] ) ? '1' : '0' );
    }

    public function save_leader_meta( $post_id ) {
        if ( ! $this->can_save_meta( $post_id ) ) {
            return;
        }
        $this->save_text_meta( $post_id, '_stc_leader_title', 'stc_leader_title' );
        $this->save_text_meta( $post_id, '_stc_leader_term', 'stc_leader_term' );
    }

    public function save_announcement_meta( $post_id ) {
        if ( ! $this->can_save_meta( $post_id ) ) {
            return;
        }
        $this->save_text_meta( $post_id, '_stc_announcement_start', 'stc_announcement_start' );
        $this->save_text_meta( $post_id, '_stc_announcement_end', 'stc_announcement_end' );
        $allowed  = array( 'normal', 'important', 'urgent' );
        $priority = isset( $_POST['stc_announcement_priority'] ) ? sanitize_key( wp_unslash( $_POST['stc_announcement_priority'] ) ) : 'normal';
        update_post_meta( $post_id, '_stc_announcement_priority', in_array( $priority, $allowed, true ) ? $priority : 'normal' );
    }

    private function can_save_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return false;
        }
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
            return false;
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return false;
        }
        return current_user_can( 'edit_post', $post_id );
    }

    private function save_text_meta( $post_id, $meta_key, $field_name ) {
        if ( ! isset( $_POST[ $field_name ] ) ) {
            delete_post_meta( $post_id, $meta_key );
            return;
        }
        $value = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
        if ( '' === $value ) {
            delete_post_meta( $post_id, $meta_key );
        } else {
            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    public function register_settings_page() {
        add_options_page( __( 'St. Thekla Site Core', 'st-thekla-site-core' ), __( 'St. Thekla Site Core', 'st-thekla-site-core' ), 'manage_options', 'st-thekla-site-core', array( $this, 'render_settings_page' ) );
    }

    public function register_settings() {
        register_setting(
            'stc_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => array(),
            )
        );
    }

    public function sanitize_settings( $input ) {
        $input = is_array( $input ) ? $input : array();
        return array(
            'church_name'    => sanitize_text_field( $input['church_name'] ?? '' ),
            'address_line_1' => sanitize_text_field( $input['address_line_1'] ?? '' ),
            'address_line_2' => sanitize_text_field( $input['address_line_2'] ?? '' ),
            'city'           => sanitize_text_field( $input['city'] ?? '' ),
            'state'          => sanitize_text_field( $input['state'] ?? '' ),
            'zip'            => sanitize_text_field( $input['zip'] ?? '' ),
            'phone'          => sanitize_text_field( $input['phone'] ?? '' ),
            'email'          => sanitize_email( $input['email'] ?? '' ),
            'map_url'        => esc_url_raw( $input['map_url'] ?? '' ),
            'donation_url'   => esc_url_raw( $input['donation_url'] ?? '' ),
            'livestream_url' => esc_url_raw( $input['livestream_url'] ?? '' ),
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'St. Thekla Site Core', 'st-thekla-site-core' ); ?></h1>
            <p><?php esc_html_e( 'Maintain public church contact information in one place. Shortcodes and the public app API use these values.', 'st-thekla-site-core' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'stc_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <?php
                    $this->settings_text_row( 'church_name', __( 'Church name', 'st-thekla-site-core' ), $settings );
                    $this->settings_text_row( 'address_line_1', __( 'Address line 1', 'st-thekla-site-core' ), $settings );
                    $this->settings_text_row( 'address_line_2', __( 'Address line 2', 'st-thekla-site-core' ), $settings );
                    $this->settings_text_row( 'city', __( 'City', 'st-thekla-site-core' ), $settings );
                    $this->settings_text_row( 'state', __( 'State', 'st-thekla-site-core' ), $settings );
                    $this->settings_text_row( 'zip', __( 'ZIP code', 'st-thekla-site-core' ), $settings );
                    $this->settings_text_row( 'phone', __( 'Telephone', 'st-thekla-site-core' ), $settings, 'tel' );
                    $this->settings_text_row( 'email', __( 'Public email', 'st-thekla-site-core' ), $settings, 'email' );
                    $this->settings_text_row( 'map_url', __( 'Map URL', 'st-thekla-site-core' ), $settings, 'url' );
                    $this->settings_text_row( 'donation_url', __( 'Donation URL', 'st-thekla-site-core' ), $settings, 'url' );
                    $this->settings_text_row( 'livestream_url', __( 'Livestream URL', 'st-thekla-site-core' ), $settings, 'url' );
                    ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e( 'Available shortcodes', 'st-thekla-site-core' ); ?></h2>
            <ul><li><code>[st_liturgy_schedule]</code></li><li><code>[st_leadership]</code></li><li><code>[st_church_location]</code></li><li><code>[st_announcements]</code></li></ul>
            <p><strong><?php esc_html_e( 'Public app API:', 'st-thekla-site-core' ); ?></strong> <code><?php echo esc_html( rest_url( 'st-thekla/v1/public' ) ); ?></code></p>
        </div>
        <?php
    }

    private function settings_text_row( $key, $label, $settings, $type = 'text' ) {
        $name  = self::OPTION_KEY . '[' . $key . ']';
        $value = $settings[ $key ] ?? '';
        ?>
        <tr><th scope="row"><label for="<?php echo esc_attr( 'stc_' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( 'stc_' . $key ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"></td></tr>
        <?php
    }

    private function get_settings() {
        $settings = get_option( self::OPTION_KEY, array() );
        return is_array( $settings ) ? $settings : array();
    }

    public function render_admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( get_option( 'users_can_register' ) ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'St. Thekla Site Core:', 'st-thekla-site-core' ) . '</strong> ' . esc_html__( 'Public WordPress registration is enabled. Disable “Anyone can register” under Settings → General unless there is a documented reason to keep it.', 'st-thekla-site-core' ) . '</p></div>';
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && wp_get_environment_type() === 'production' ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'St. Thekla Site Core:', 'st-thekla-site-core' ) . '</strong> ' . esc_html__( 'WP_DEBUG is enabled on the production site. Keep it only during active troubleshooting and disable it after recovery.', 'st-thekla-site-core' ) . '</p></div>';
        }
    }

    public function shortcode_schedule( $atts ) {
        $atts = shortcode_atts( array( 'limit' => '12', 'show_past' => 'no' ), $atts, 'st_liturgy_schedule' );
        $limit = max( 1, min( 100, absint( $atts['limit'] ) ) );
        $meta_query = array();
        if ( 'yes' !== strtolower( (string) $atts['show_past'] ) ) {
            $meta_query[] = array( 'key' => '_stc_start', 'value' => current_time( 'Y-m-d\TH:i' ), 'compare' => '>=', 'type' => 'CHAR' );
        }
        $query = new WP_Query(
            array(
                'post_type'      => 'stc_service',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'meta_key'       => '_stc_start',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => $meta_query,
                'no_found_rows'  => true,
            )
        );
        $this->enqueue_public_style();
        ob_start();
        echo '<div class="stc-schedule" data-stc-component="schedule">';
        if ( ! $query->have_posts() ) {
            echo '<p>' . esc_html__( 'No upcoming services have been posted.', 'st-thekla-site-core' ) . '</p>';
        } else {
            echo '<div class="stc-schedule-list">';
            while ( $query->have_posts() ) {
                $query->the_post();
                $this->render_service_card( get_the_ID() );
            }
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    private function render_service_card( $post_id ) {
        $start     = get_post_meta( $post_id, '_stc_start', true );
        $type      = get_post_meta( $post_id, '_stc_service_type', true );
        $location  = get_post_meta( $post_id, '_stc_location', true );
        $celebrant = get_post_meta( $post_id, '_stc_celebrant', true );
        $cancelled = (bool) get_post_meta( $post_id, '_stc_cancelled', true );
        $timestamp = $this->local_datetime_to_timestamp( $start );
        ?>
        <article class="stc-service-card<?php echo $cancelled ? ' is-cancelled' : ''; ?>">
            <div class="stc-service-date"><span class="stc-service-month"><?php echo esc_html( $timestamp ? wp_date( 'M', $timestamp ) : '' ); ?></span><span class="stc-service-day"><?php echo esc_html( $timestamp ? wp_date( 'j', $timestamp ) : '' ); ?></span></div>
            <div class="stc-service-content">
                <h3 class="stc-service-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
                <?php if ( $cancelled ) : ?><p class="stc-status stc-status-cancelled"><?php esc_html_e( 'Cancelled', 'st-thekla-site-core' ); ?></p><?php endif; ?>
                <p class="stc-service-time"><?php echo esc_html( $timestamp ? wp_date( 'l, F j, Y \a\t g:i a', $timestamp ) : $start ); ?></p>
                <?php if ( $type ) : ?><p><strong><?php esc_html_e( 'Service:', 'st-thekla-site-core' ); ?></strong> <?php echo esc_html( $type ); ?></p><?php endif; ?>
                <?php if ( $location ) : ?><p><strong><?php esc_html_e( 'Location:', 'st-thekla-site-core' ); ?></strong> <?php echo esc_html( $location ); ?></p><?php endif; ?>
                <?php if ( $celebrant ) : ?><p><strong><?php esc_html_e( 'Celebrant:', 'st-thekla-site-core' ); ?></strong> <?php echo esc_html( $celebrant ); ?></p><?php endif; ?>
                <?php if ( get_post_field( 'post_content', $post_id ) ) : ?><div class="stc-service-notes"><?php echo wp_kses_post( wpautop( get_post_field( 'post_content', $post_id ) ) ); ?></div><?php endif; ?>
            </div>
        </article>
        <?php
    }

    public function shortcode_leadership( $atts ) {
        $atts = shortcode_atts( array( 'limit' => '-1' ), $atts, 'st_leadership' );
        $limit = (int) $atts['limit'];
        if ( 0 === $limit || $limit < -1 ) {
            $limit = -1;
        }
        $query = new WP_Query( array( 'post_type' => 'stc_leader', 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'no_found_rows' => true ) );
        $this->enqueue_public_style();
        ob_start();
        echo '<div class="stc-leadership-grid" data-stc-component="leadership">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $role = get_post_meta( $post_id, '_stc_leader_title', true );
            $term = get_post_meta( $post_id, '_stc_leader_term', true );
            echo '<article class="stc-leader-card">';
            if ( has_post_thumbnail( $post_id ) ) {
                echo get_the_post_thumbnail( $post_id, 'medium', array( 'class' => 'stc-leader-photo', 'loading' => 'lazy' ) );
            }
            echo '<h3>' . esc_html( get_the_title( $post_id ) ) . '</h3>';
            if ( $role ) {
                echo '<p class="stc-leader-role">' . esc_html( $role ) . '</p>';
            }
            if ( $term ) {
                echo '<p class="stc-leader-term">' . esc_html( $term ) . '</p>';
            }
            $bio = get_post_field( 'post_content', $post_id );
            if ( $bio ) {
                echo '<div class="stc-leader-bio">' . wp_kses_post( wpautop( $bio ) ) . '</div>';
            }
            echo '</article>';
        }
        echo '</div>';
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    public function shortcode_announcements( $atts ) {
        $atts = shortcode_atts( array( 'limit' => '5' ), $atts, 'st_announcements' );
        $limit = max( 1, min( 50, absint( $atts['limit'] ) ) );
        $today = current_time( 'Y-m-d' );
        $query = new WP_Query(
            array(
                'post_type'      => 'stc_announcement',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => array( 'date' => 'DESC' ),
                'meta_query'     => array(
                    'relation' => 'AND',
                    array( 'relation' => 'OR', array( 'key' => '_stc_announcement_start', 'compare' => 'NOT EXISTS' ), array( 'key' => '_stc_announcement_start', 'value' => '', 'compare' => '=' ), array( 'key' => '_stc_announcement_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ) ),
                    array( 'relation' => 'OR', array( 'key' => '_stc_announcement_end', 'compare' => 'NOT EXISTS' ), array( 'key' => '_stc_announcement_end', 'value' => '', 'compare' => '=' ), array( 'key' => '_stc_announcement_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ) ),
                ),
                'no_found_rows' => true,
            )
        );
        $this->enqueue_public_style();
        ob_start();
        echo '<div class="stc-announcements" data-stc-component="announcements">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $priority = get_post_meta( get_the_ID(), '_stc_announcement_priority', true ) ?: 'normal';
            echo '<article class="stc-announcement stc-priority-' . esc_attr( $priority ) . '"><h3>' . esc_html( get_the_title() ) . '</h3><div>' . wp_kses_post( apply_filters( 'the_content', get_the_content() ) ) . '</div></article>';
        }
        echo '</div>';
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    public function shortcode_location( $atts ) {
        $atts = shortcode_atts( array( 'show_map_link' => 'yes' ), $atts, 'st_church_location' );
        $settings = $this->get_settings();
        $address = $this->formatted_address( $settings );
        $this->enqueue_public_style();
        ob_start();
        echo '<address class="stc-location" data-stc-component="location">';
        if ( ! empty( $settings['church_name'] ) ) {
            echo '<strong>' . esc_html( $settings['church_name'] ) . '</strong><br>';
        }
        if ( $address ) {
            echo nl2br( esc_html( $address ) ) . '<br>';
        }
        if ( ! empty( $settings['phone'] ) ) {
            $phone_href = preg_replace( '/[^0-9+]/', '', $settings['phone'] );
            echo '<a href="tel:' . esc_attr( $phone_href ) . '">' . esc_html( $settings['phone'] ) . '</a><br>';
        }
        if ( ! empty( $settings['email'] ) ) {
            echo '<a href="mailto:' . esc_attr( antispambot( $settings['email'] ) ) . '">' . esc_html( antispambot( $settings['email'] ) ) . '</a><br>';
        }
        if ( 'yes' === strtolower( (string) $atts['show_map_link'] ) && ! empty( $settings['map_url'] ) ) {
            echo '<a class="stc-button" href="' . esc_url( $settings['map_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open map', 'st-thekla-site-core' ) . '</a>';
        }
        echo '</address>';
        return (string) ob_get_clean();
    }

    public function register_rest_routes() {
        register_rest_route( 'st-thekla/v1', '/public', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_public_payload' ), 'permission_callback' => '__return_true' ) );
    }

    public function rest_public_payload( WP_REST_Request $request ) {
        $limit = absint( $request->get_param( 'limit' ) );
        $limit = $limit ? min( 50, $limit ) : 20;
        return rest_ensure_response( array( 'generated_at' => current_time( 'c' ), 'contact' => $this->public_contact_payload(), 'services' => $this->public_services_payload( $limit ), 'announcements' => $this->public_announcements_payload( $limit ) ) );
    }

    private function public_contact_payload() {
        $settings = $this->get_settings();
        return array(
            'church_name'    => $settings['church_name'] ?? '',
            'address'        => $this->formatted_address( $settings ),
            'phone'          => $settings['phone'] ?? '',
            'email'          => $settings['email'] ?? '',
            'map_url'        => $settings['map_url'] ?? '',
            'donation_url'   => $settings['donation_url'] ?? '',
            'livestream_url' => $settings['livestream_url'] ?? '',
        );
    }

    private function public_services_payload( $limit ) {
        $posts = get_posts(
            array(
                'post_type'      => 'stc_service',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'meta_key'       => '_stc_start',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array( array( 'key' => '_stc_start', 'value' => current_time( 'Y-m-d\TH:i' ), 'compare' => '>=', 'type' => 'CHAR' ) ),
            )
        );
        $payload = array();
        foreach ( $posts as $post ) {
            $payload[] = array(
                'id'           => (int) $post->ID,
                'title'        => get_the_title( $post ),
                'start'        => get_post_meta( $post->ID, '_stc_start', true ),
                'end'          => get_post_meta( $post->ID, '_stc_end', true ),
                'service_type' => get_post_meta( $post->ID, '_stc_service_type', true ),
                'location'     => get_post_meta( $post->ID, '_stc_location', true ),
                'celebrant'    => get_post_meta( $post->ID, '_stc_celebrant', true ),
                'cancelled'    => (bool) get_post_meta( $post->ID, '_stc_cancelled', true ),
                'notes'        => wp_strip_all_tags( $post->post_content ),
            );
        }
        return $payload;
    }

    private function public_announcements_payload( $limit ) {
        $today = current_time( 'Y-m-d' );
        $posts = get_posts(
            array(
                'post_type'      => 'stc_announcement',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array( 'relation' => 'OR', array( 'key' => '_stc_announcement_start', 'compare' => 'NOT EXISTS' ), array( 'key' => '_stc_announcement_start', 'value' => '', 'compare' => '=' ), array( 'key' => '_stc_announcement_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ) ),
                    array( 'relation' => 'OR', array( 'key' => '_stc_announcement_end', 'compare' => 'NOT EXISTS' ), array( 'key' => '_stc_announcement_end', 'value' => '', 'compare' => '=' ), array( 'key' => '_stc_announcement_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ) ),
                ),
            )
        );
        $payload = array();
        foreach ( $posts as $post ) {
            $payload[] = array(
                'id'       => (int) $post->ID,
                'title'    => get_the_title( $post ),
                'content'  => wp_strip_all_tags( $post->post_content ),
                'priority' => get_post_meta( $post->ID, '_stc_announcement_priority', true ) ?: 'normal',
                'starts'   => get_post_meta( $post->ID, '_stc_announcement_start', true ),
                'ends'     => get_post_meta( $post->ID, '_stc_announcement_end', true ),
            );
        }
        return $payload;
    }

    private function local_datetime_to_timestamp( $value ) {
        if ( ! $value ) {
            return false;
        }
        try {
            $datetime = new DateTimeImmutable( $value, wp_timezone() );
            return $datetime->getTimestamp();
        } catch ( Exception $exception ) {
            return false;
        }
    }

    private function formatted_address( $settings ) {
        $lines = array_filter(
            array(
                $settings['address_line_1'] ?? '',
                $settings['address_line_2'] ?? '',
                trim( implode( ' ', array_filter( array( trim( ( $settings['city'] ?? '' ) . ( ! empty( $settings['city'] ) && ! empty( $settings['state'] ) ? ',' : '' ) ), $settings['state'] ?? '', $settings['zip'] ?? '' ) ) ) ),
            )
        );
        return implode( "\n", $lines );
    }

    private function enqueue_public_style() {
        if ( ! wp_style_is( 'st-thekla-site-core', 'registered' ) ) {
            wp_register_style( 'st-thekla-site-core', STC_PLUGIN_URL . 'assets/css/public.css', array(), STC_VERSION );
        }
        wp_enqueue_style( 'st-thekla-site-core' );
    }

    public function service_columns( $columns ) {
        $columns['stc_start'] = __( 'Start', 'st-thekla-site-core' );
        $columns['stc_status'] = __( 'Status', 'st-thekla-site-core' );
        return $columns;
    }

    public function render_service_column( $column, $post_id ) {
        if ( 'stc_start' === $column ) {
            $value = get_post_meta( $post_id, '_stc_start', true );
            $timestamp = $this->local_datetime_to_timestamp( $value );
            echo esc_html( $timestamp ? wp_date( 'M j, Y g:i a', $timestamp ) : '—' );
        }
        if ( 'stc_status' === $column ) {
            echo get_post_meta( $post_id, '_stc_cancelled', true ) ? esc_html__( 'Cancelled', 'st-thekla-site-core' ) : esc_html__( 'Scheduled', 'st-thekla-site-core' );
        }
    }

    public function sortable_service_columns( $columns ) {
        $columns['stc_start'] = 'stc_start';
        return $columns;
    }

    public function service_admin_ordering( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'stc_service' !== $query->get( 'post_type' ) ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        if ( ! $orderby || 'stc_start' === $orderby ) {
            $query->set( 'meta_key', '_stc_start' );
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'order', 'ASC' );
        }
    }
}
