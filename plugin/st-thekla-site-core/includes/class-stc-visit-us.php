<?php
/**
 * Visitor-facing location, move announcement and parking information.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class STC_Visit_Us {

    private const OPTION_KEY = 'stc_visit_settings';

    /** @var STC_Visit_Us|null */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        if ( false === get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, self::defaults(), '', false );
        }
    }

    public function init() {
        self::activate();
        add_action( 'init', array( $this, 'register_shortcodes' ), 30 );
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_media' ) );
        add_filter( 'rest_request_after_callbacks', array( $this, 'append_to_public_payload' ), 20, 3 );
    }

    public static function defaults() {
        return array(
            'visit_image_id'            => 0,
            'parking_map_image_id'      => 0,
            'parking_notes'             => 'Please use only the designated parking areas shown on the map. The chapel entrance and exit are marked in blue. Please do not park in areas marked in red. Additional parking, St. Martin Hall and restrooms are also identified on the map.',
            'move_announcement_enabled' => '1',
            'move_effective_date'        => '2026-08-23',
            'visit_page_url'             => home_url( '/visit-us/' ),
        );
    }

    public function register_shortcodes() {
        add_shortcode( 'st_visit_us', array( $this, 'shortcode_visit_us' ) );
    }

    public function register_settings_page() {
        add_options_page(
            __( 'St. Thekla Visit Us', 'st-thekla-site-core' ),
            __( 'Visit Us', 'st-thekla-site-core' ),
            'manage_options',
            'st-thekla-visit-us',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting(
            'stc_visit_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => self::defaults(),
            )
        );
    }

    public function sanitize_settings( $input ) {
        $input = is_array( $input ) ? $input : array();
        $date  = sanitize_text_field( isset( $input['move_effective_date'] ) ? $input['move_effective_date'] : '' );
        if ( $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $date = '';
        }

        return array(
            'visit_image_id'            => absint( isset( $input['visit_image_id'] ) ? $input['visit_image_id'] : 0 ),
            'parking_map_image_id'      => absint( isset( $input['parking_map_image_id'] ) ? $input['parking_map_image_id'] : 0 ),
            'parking_notes'             => sanitize_textarea_field( isset( $input['parking_notes'] ) ? $input['parking_notes'] : '' ),
            'move_announcement_enabled' => ! empty( $input['move_announcement_enabled'] ) ? '1' : '0',
            'move_effective_date'        => $date,
            'visit_page_url'             => esc_url_raw( isset( $input['visit_page_url'] ) ? $input['visit_page_url'] : '' ),
        );
    }

    public function enqueue_admin_media( $hook ) {
        if ( false === strpos( (string) $hook, 'st-thekla-visit-us' ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script( 'jquery' );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'St. Thekla Visit Us', 'st-thekla-site-core' ); ?></h1>
            <p><?php esc_html_e( 'Maintain the public move announcement, visitor image and parking map.', 'st-thekla-site-core' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'stc_visit_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Move announcement', 'st-thekla-site-core' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[move_announcement_enabled]" value="1" <?php checked( $settings['move_announcement_enabled'], '1' ); ?>> <?php esc_html_e( 'Show “St. Thekla Has a New Home” in the Visit Us component', 'st-thekla-site-core' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stc_move_effective_date"><?php esc_html_e( 'Move effective date', 'st-thekla-site-core' ); ?></label></th>
                        <td><input type="date" id="stc_move_effective_date" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[move_effective_date]" value="<?php echo esc_attr( $settings['move_effective_date'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stc_visit_page_url"><?php esc_html_e( 'Visit Us page URL', 'st-thekla-site-core' ); ?></label></th>
                        <td><input class="regular-text" type="url" id="stc_visit_page_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[visit_page_url]" value="<?php echo esc_attr( $settings['visit_page_url'] ); ?>"></td>
                    </tr>
                    <?php $this->media_row( 'visit_image_id', __( 'New-home image', 'st-thekla-site-core' ), $settings ); ?>
                    <?php $this->media_row( 'parking_map_image_id', __( 'Parking map', 'st-thekla-site-core' ), $settings ); ?>
                    <tr>
                        <th scope="row"><label for="stc_parking_notes"><?php esc_html_e( 'Parking notes', 'st-thekla-site-core' ); ?></label></th>
                        <td><textarea class="large-text" rows="5" id="stc_parking_notes" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[parking_notes]"><?php echo esc_textarea( $settings['parking_notes'] ); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e( 'Shortcodes', 'st-thekla-site-core' ); ?></h2>
            <p><code>[st_visit_us]</code> &mdash; <?php esc_html_e( 'full visitor and parking layout', 'st-thekla-site-core' ); ?></p>
            <p><code>[st_visit_us layout="compact"]</code> &mdash; <?php esc_html_e( 'homepage announcement layout', 'st-thekla-site-core' ); ?></p>
        </div>
        <script>
        (function($) {
            $('.stc-select-media').on('click', function(event) {
                event.preventDefault();
                var button = $(this);
                var target = $('#' + button.data('target'));
                var preview = $('#' + button.data('preview'));
                var frame = wp.media({ title: button.data('title'), button: { text: button.data('button') }, multiple: false });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    target.val(attachment.id);
                    preview.attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url).show();
                });
                frame.open();
            });
            $('.stc-clear-media').on('click', function(event) {
                event.preventDefault();
                $('#' + $(this).data('target')).val('0');
                $('#' + $(this).data('preview')).attr('src', '').hide();
            });
        })(jQuery);
        </script>
        <?php
    }

    private function media_row( $key, $label, $settings ) {
        $attachment_id = absint( isset( $settings[ $key ] ) ? $settings[ $key ] : 0 );
        $preview_url   = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
        $input_id      = 'stc_' . $key;
        $preview_id    = $input_id . '_preview';
        ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td>
                <input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>">
                <img id="<?php echo esc_attr( $preview_id ); ?>" src="<?php echo esc_url( $preview_url ); ?>" alt="" style="display:<?php echo $preview_url ? 'block' : 'none'; ?>;max-width:320px;height:auto;margin:0 0 10px;">
                <button type="button" class="button stc-select-media" data-target="<?php echo esc_attr( $input_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>" data-title="<?php echo esc_attr( $label ); ?>" data-button="<?php esc_attr_e( 'Use this image', 'st-thekla-site-core' ); ?>"><?php esc_html_e( 'Select image', 'st-thekla-site-core' ); ?></button>
                <button type="button" class="button-link-delete stc-clear-media" data-target="<?php echo esc_attr( $input_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>"><?php esc_html_e( 'Clear', 'st-thekla-site-core' ); ?></button>
            </td>
        </tr>
        <?php
    }

    public function shortcode_visit_us( $atts ) {
        $atts = shortcode_atts( array( 'layout' => 'full' ), $atts, 'st_visit_us' );
        $layout = 'compact' === strtolower( (string) $atts['layout'] ) ? 'compact' : 'full';
        $visit = $this->get_settings();
        $contact = get_option( 'stc_settings', array() );
        $contact = is_array( $contact ) ? $contact : array();
        $map_url = isset( $contact['map_url'] ) ? $contact['map_url'] : '';
        $visit_url = ! empty( $visit['visit_page_url'] ) ? $visit['visit_page_url'] : home_url( '/visit-us/' );

        $this->enqueue_styles();
        ob_start();
        ?>
        <section class="stc-visit-us stc-visit-us-<?php echo esc_attr( $layout ); ?>" data-stc-component="visit-us">
            <div class="stc-visit-primary">
                <?php if ( ! empty( $visit['visit_image_id'] ) ) : ?>
                    <div class="stc-visit-image-wrap">
                        <?php echo wp_get_attachment_image( absint( $visit['visit_image_id'] ), 'large', false, array( 'class' => 'stc-visit-image', 'loading' => 'eager' ) ); ?>
                    </div>
                <?php endif; ?>
                <div class="stc-visit-copy">
                    <?php if ( '1' === (string) $visit['move_announcement_enabled'] ) : ?>
                        <p class="stc-eyebrow"><?php esc_html_e( 'Now worshiping in Sparkill', 'st-thekla-site-core' ); ?></p>
                        <h2><?php esc_html_e( 'St. Thekla Has a New Home', 'st-thekla-site-core' ); ?></h2>
                        <p><?php esc_html_e( 'St. Thekla Malankara Orthodox Church is now worshiping at Sacred Heart Chapel. We warmly invite you and your family to join us each Sunday for prayer, Holy Qurbana, fellowship and Tree of Life.', 'st-thekla-site-core' ); ?></p>
                    <?php else : ?>
                        <h2><?php esc_html_e( 'Visit St. Thekla', 'st-thekla-site-core' ); ?></h2>
                    <?php endif; ?>
                    <?php echo do_shortcode( '[st_church_location show_map_link="no"]' ); ?>
                    <div class="stc-visit-actions">
                        <?php if ( $map_url ) : ?><a class="stc-button stc-button-primary" href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Directions', 'st-thekla-site-core' ); ?></a><?php endif; ?>
                        <?php if ( 'compact' === $layout ) : ?><a class="stc-button" href="<?php echo esc_url( $visit_url . '#parking-arrival' ); ?>"><?php esc_html_e( 'Parking & Arrival Map', 'st-thekla-site-core' ); ?></a><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="stc-visit-schedule">
                <h3><?php esc_html_e( 'Sunday Schedule', 'st-thekla-site-core' ); ?></h3>
                <?php echo do_shortcode( '[st_weekly_schedule]' ); ?>
            </div>
            <?php if ( 'full' === $layout ) : ?>
                <div id="parking-arrival" class="stc-parking-arrival">
                    <h2><?php esc_html_e( 'Parking and Arrival', 'st-thekla-site-core' ); ?></h2>
                    <?php if ( ! empty( $visit['parking_notes'] ) ) : ?><p><?php echo esc_html( $visit['parking_notes'] ); ?></p><?php endif; ?>
                    <?php if ( ! empty( $visit['parking_map_image_id'] ) ) : ?>
                        <a class="stc-parking-map-link" href="<?php echo esc_url( wp_get_attachment_image_url( absint( $visit['parking_map_image_id'] ), 'full' ) ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo wp_get_attachment_image( absint( $visit['parking_map_image_id'] ), 'large', false, array( 'class' => 'stc-parking-map', 'loading' => 'lazy' ) ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function append_to_public_payload( $response, $handler, $request ) {
        if ( '/st-thekla/v1/public' !== $request->get_route() || is_wp_error( $response ) ) {
            return $response;
        }
        $response = rest_ensure_response( $response );
        $data = $response->get_data();
        if ( ! is_array( $data ) ) {
            return $response;
        }
        $visit = $this->get_settings();
        $data['visit'] = array(
            'move_effective_date'        => $visit['move_effective_date'],
            'move_announcement_enabled' => '1' === (string) $visit['move_announcement_enabled'],
            'visit_page_url'             => $visit['visit_page_url'],
            'visit_image'                => $this->attachment_payload( $visit['visit_image_id'] ),
            'parking_map_image'          => $this->attachment_payload( $visit['parking_map_image_id'] ),
            'parking_notes'              => $visit['parking_notes'],
        );
        $response->set_data( $data );
        return $response;
    }

    private function attachment_payload( $attachment_id ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id ) {
            return null;
        }
        return array(
            'id'  => $attachment_id,
            'url' => wp_get_attachment_image_url( $attachment_id, 'full' ),
            'alt' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
        );
    }

    private function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );
        $saved = is_array( $saved ) ? $saved : array();
        return array_merge( self::defaults(), $saved );
    }

    private function enqueue_styles() {
        if ( ! wp_style_is( 'st-thekla-site-core', 'registered' ) ) {
            wp_register_style( 'st-thekla-site-core', STC_PLUGIN_URL . 'assets/css/public.css', array(), STC_VERSION );
        }
        wp_enqueue_style( 'st-thekla-site-core' );
        if ( ! wp_style_is( 'st-thekla-site-core-weekly', 'registered' ) ) {
            wp_register_style( 'st-thekla-site-core-weekly', STC_PLUGIN_URL . 'assets/css/weekly-schedule.css', array( 'st-thekla-site-core' ), STC_VERSION );
        }
        wp_enqueue_style( 'st-thekla-site-core-weekly' );
    }
}
