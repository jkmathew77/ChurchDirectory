<?php
/**
 * Recurring Sunday schedule and one-time public-site bootstrap values.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class STC_Weekly_Schedule {

    private const OPTION_KEY = 'stc_weekly_schedule';
    private const SETTINGS_OPTION_KEY = 'stc_settings';
    private const NONCE_ACTION = 'stc_save_weekly_schedule';

    /** @var STC_Weekly_Schedule|null */
    private static $instance = null;

    /** @var callable|null */
    private $previous_ninja_tables_handler = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        if ( false === get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, self::default_rows(), '', false );
        }

        self::bootstrap_public_settings();
    }

    public function init() {
        self::activate();

        add_action( 'init', array( $this, 'register_shortcodes' ), 120 );
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_post_stc_save_weekly_schedule', array( $this, 'save_settings' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
        add_filter( 'rest_request_after_callbacks', array( $this, 'append_to_public_payload' ), 10, 3 );
    }

    public static function default_rows() {
        return array(
            array( 'time' => '8:20 AM', 'description' => 'Morning Prayers' ),
            array( 'time' => '9:00 AM', 'description' => 'Holy Liturgy' ),
            array( 'time' => '10:10 AM', 'description' => 'Dismissal' ),
            array( 'time' => '10:30 AM', 'description' => 'Refreshments' ),
            array( 'time' => '10:45 AM', 'description' => 'Tree of Life' ),
            array( 'time' => '11:30 AM', 'description' => 'End of Tree of Life' ),
        );
    }

    private static function bootstrap_public_settings() {
        $settings = get_option( self::SETTINGS_OPTION_KEY, array() );
        $settings = is_array( $settings ) ? $settings : array();

        $defaults = array(
            'church_name'    => 'St. Thekla Malankara Orthodox Church',
            'address_line_1' => 'St. Thomas Lutheran Church',
            'address_line_2' => '2 Old Ox Road',
            'city'           => 'Nyack',
            'state'          => 'NY',
            'zip'            => '10960',
            'map_url'        => 'https://www.google.com/maps/search/?api=1&query=2+Old+Ox+Road+Nyack+NY+10960',
        );

        $changed = false;
        foreach ( $defaults as $key => $value ) {
            if ( ! isset( $settings[ $key ] ) || '' === trim( (string) $settings[ $key ] ) ) {
                $settings[ $key ] = $value;
                $changed = true;
            }
        }

        foreach ( array( 'phone', 'email', 'donation_url', 'livestream_url' ) as $optional_key ) {
            if ( ! array_key_exists( $optional_key, $settings ) ) {
                $settings[ $optional_key ] = '';
                $changed = true;
            }
        }

        if ( $changed || false === get_option( self::SETTINGS_OPTION_KEY ) ) {
            update_option( self::SETTINGS_OPTION_KEY, $settings, false );
        }
    }

    public function register_shortcodes() {
        global $shortcode_tags;

        add_shortcode( 'st_weekly_schedule', array( $this, 'shortcode_schedule' ) );

        if ( isset( $shortcode_tags['ninja_tables'] ) ) {
            $this->previous_ninja_tables_handler = $shortcode_tags['ninja_tables'];
        }
        add_shortcode( 'ninja_tables', array( $this, 'legacy_ninja_tables' ) );
    }

    public function legacy_ninja_tables( $atts, $content = null, $tag = 'ninja_tables' ) {
        $atts = shortcode_atts( array( 'id' => '' ), $atts, $tag );

        if ( '142' === trim( (string) $atts['id'], " \t\n\r\0\x0B\"'“”" ) ) {
            return $this->shortcode_schedule( array() );
        }

        if ( is_callable( $this->previous_ninja_tables_handler ) ) {
            return (string) call_user_func( $this->previous_ninja_tables_handler, $atts, $content, $tag );
        }

        if ( current_user_can( 'edit_pages' ) ) {
            return '<p class="stc-admin-warning">' . esc_html__( 'This legacy Ninja Tables shortcode has not been migrated.', 'st-thekla-site-core' ) . '</p>';
        }

        return '';
    }

    public function shortcode_schedule() {
        $rows = $this->get_rows();
        $this->enqueue_styles();

        ob_start();
        ?>
        <div class="stc-weekly-schedule" data-stc-component="weekly-schedule">
            <table class="stc-weekly-schedule-table">
                <caption><?php esc_html_e( 'Sunday Schedule', 'st-thekla-site-core' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Time', 'st-thekla-site-core' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Service or Activity', 'st-thekla-site-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $row['time'] ); ?></th>
                            <td><?php echo esc_html( $row['description'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function register_settings_page() {
        add_options_page(
            __( 'St. Thekla Weekly Schedule', 'st-thekla-site-core' ),
            __( 'Weekly Schedule', 'st-thekla-site-core' ),
            'manage_options',
            'st-thekla-weekly-schedule',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rows = $this->get_rows();
        while ( count( $rows ) < 10 ) {
            $rows[] = array( 'time' => '', 'description' => '' );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'St. Thekla Weekly Schedule', 'st-thekla-site-core' ); ?></h1>
            <p><?php esc_html_e( 'Maintain the recurring Sunday schedule shown on the homepage and exposed to the public app API.', 'st-thekla-site-core' ); ?></p>
            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Weekly schedule saved.', 'st-thekla-site-core' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="stc_save_weekly_schedule">
                <?php wp_nonce_field( self::NONCE_ACTION ); ?>
                <table class="widefat striped" style="max-width: 900px">
                    <thead><tr><th><?php esc_html_e( 'Time', 'st-thekla-site-core' ); ?></th><th><?php esc_html_e( 'Service or Activity', 'st-thekla-site-core' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><input type="text" class="regular-text" name="schedule_time[]" value="<?php echo esc_attr( $row['time'] ); ?>" placeholder="9:00 AM"></td>
                                <td><input type="text" class="regular-text" name="schedule_description[]" value="<?php echo esc_attr( $row['description'] ); ?>" placeholder="Holy Liturgy"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( __( 'Save Weekly Schedule', 'st-thekla-site-core' ) ); ?>
            </form>
            <p><strong><?php esc_html_e( 'Shortcode:', 'st-thekla-site-core' ); ?></strong> <code>[st_weekly_schedule]</code></p>
        </div>
        <?php
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to update this schedule.', 'st-thekla-site-core' ) );
        }
        check_admin_referer( self::NONCE_ACTION );

        $times = isset( $_POST['schedule_time'] ) ? (array) wp_unslash( $_POST['schedule_time'] ) : array();
        $descriptions = isset( $_POST['schedule_description'] ) ? (array) wp_unslash( $_POST['schedule_description'] ) : array();
        $rows = array();
        $count = min( 30, max( count( $times ), count( $descriptions ) ) );

        for ( $index = 0; $index < $count; $index++ ) {
            $time = sanitize_text_field( $times[ $index ] ?? '' );
            $description = sanitize_text_field( $descriptions[ $index ] ?? '' );
            if ( '' === $time && '' === $description ) {
                continue;
            }
            $rows[] = array( 'time' => $time, 'description' => $description );
        }

        update_option( self::OPTION_KEY, $rows, false );
        wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'options-general.php?page=st-thekla-weekly-schedule' ) ) );
        exit;
    }

    public function register_rest_route() {
        register_rest_route(
            'st-thekla/v1',
            '/weekly-schedule',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_schedule' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function rest_schedule() {
        return rest_ensure_response(
            array(
                'generated_at' => current_time( 'c' ),
                'timezone'     => wp_timezone_string(),
                'day'          => 'Sunday',
                'items'        => $this->get_rows(),
            )
        );
    }

    public function append_to_public_payload( $response, $handler, $request ) {
        if ( '/st-thekla/v1/public' !== $request->get_route() || is_wp_error( $response ) ) {
            return $response;
        }

        $response = rest_ensure_response( $response );
        $data = $response->get_data();
        if ( is_array( $data ) ) {
            $data['weekly_schedule'] = array(
                'day'   => 'Sunday',
                'items' => $this->get_rows(),
            );
            $response->set_data( $data );
        }
        return $response;
    }

    private function get_rows() {
        $rows = get_option( self::OPTION_KEY, self::default_rows() );
        if ( ! is_array( $rows ) ) {
            return self::default_rows();
        }

        $clean = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $time = sanitize_text_field( $row['time'] ?? '' );
            $description = sanitize_text_field( $row['description'] ?? '' );
            if ( '' === $time && '' === $description ) {
                continue;
            }
            $clean[] = array( 'time' => $time, 'description' => $description );
        }

        return $clean ?: self::default_rows();
    }

    private function enqueue_styles() {
        if ( ! wp_style_is( 'st-thekla-site-core', 'registered' ) ) {
            wp_register_style( 'st-thekla-site-core', STC_PLUGIN_URL . 'assets/css/public.css', array(), STC_VERSION );
        }
        wp_enqueue_style( 'st-thekla-site-core' );
        wp_enqueue_style( 'st-thekla-site-core-weekly', STC_PLUGIN_URL . 'assets/css/weekly-schedule.css', array( 'st-thekla-site-core' ), STC_VERSION );
    }
}
