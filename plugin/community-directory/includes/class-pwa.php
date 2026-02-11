<?php
/**
 * PWA (Progressive Web App) manager.
 *
 * Handles manifest generation, service worker serving, icon management,
 * and PWA meta tag injection.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_PWA {

    /** Icon sizes to generate (px). */
    const ICON_SIZES = array( 512, 192, 180, 152, 120, 32 );

    /** Upload subdirectory for PWA icons. */
    const ICON_DIR = 'community-directory/pwa';

    /**
     * Initialize PWA hooks.
     */
    public static function init() {
        // Always register the AJAX handler (needed even when PWA is disabled to allow setup)
        self::register_ajax();

        // Extend session to 30 days for remembered logins (PWA-friendly)
        add_filter( 'auth_cookie_expiration', array( __CLASS__, 'extend_session_expiration' ), 10, 3 );

        if ( '1' !== get_option( 'cd_pwa_enabled', '0' ) ) {
            return;
        }

        add_action( 'wp_head', array( __CLASS__, 'inject_meta_tags' ), 5 );
        add_action( 'wp_footer', array( __CLASS__, 'inject_sw_registration' ), 99 );
    }

    /**
     * Extend auth cookie expiration to 30 days for remembered sessions.
     */
    public static function extend_session_expiration( $expiration, $user_id, $remember ) {
        if ( $remember ) {
            return 30 * DAY_IN_SECONDS;
        }
        return $expiration;
    }

    /**
     * Check if the current request is a community page.
     */
    private static function is_community_page() {
        return ! empty( get_query_var( 'cd_page' ) );
    }

    /**
     * Inject PWA meta tags into <head> on community pages.
     */
    public static function inject_meta_tags() {
        if ( ! self::is_community_page() ) {
            return;
        }

        $theme_color = get_option( 'cd_pwa_theme_color', '#8B0000' );
        $app_title   = get_option( 'cd_pwa_short_name', 'St. Thekla' );
        $base_slug   = get_option( 'cd_base_slug', 'community' );
        $icons       = self::get_icon_urls();

        // Web app manifest
        echo '<link rel="manifest" href="' . esc_url( home_url( $base_slug . '/manifest.json' ) ) . '">' . "\n";

        // Theme color
        echo '<meta name="theme-color" content="' . esc_attr( $theme_color ) . '">' . "\n";

        // Apple touch icons
        $apple_sizes = array( 180, 152, 120 );
        foreach ( $apple_sizes as $size ) {
            if ( ! empty( $icons[ $size ] ) ) {
                echo '<link rel="apple-touch-icon" sizes="' . $size . 'x' . $size . '" href="' . esc_url( $icons[ $size ] ) . '">' . "\n";
            }
        }

        // iOS PWA meta tags
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $app_title ) . '">' . "\n";
    }

    /**
     * Inject service worker registration script in footer.
     */
    public static function inject_sw_registration() {
        if ( ! self::is_community_page() ) {
            return;
        }

        $base_slug = get_option( 'cd_base_slug', 'community' );
        $sw_url    = home_url( $base_slug . '/cd-sw.js' );
        $scope     = home_url( $base_slug . '/' );
        ?>
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register(<?php echo wp_json_encode( $sw_url ); ?>, {
                    scope: <?php echo wp_json_encode( $scope ); ?>
                }).then(function(reg) {
                    // Check for updates
                    reg.addEventListener('updatefound', function() {
                        var newWorker = reg.installing;
                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New version available
                                window.dispatchEvent(new CustomEvent('cd-sw-update', { detail: { registration: reg } }));
                            }
                        });
                    });
                });

                // When controller changes (after skipWaiting), reload
                navigator.serviceWorker.addEventListener('controllerchange', function() {
                    if (window._cdSwUpdating) {
                        window.location.reload();
                    }
                });
            });
        }
        </script>
        <?php
    }

    /**
     * Serve the web app manifest as JSON.
     */
    public static function serve_manifest() {
        $app_name    = get_option( 'cd_pwa_app_name', 'St. Thekla Directory' );
        $short_name  = get_option( 'cd_pwa_short_name', 'St. Thekla' );
        $theme_color = get_option( 'cd_pwa_theme_color', '#8B0000' );
        $bg_color    = get_option( 'cd_pwa_background_color', '#FFFFFF' );
        $base_slug   = get_option( 'cd_base_slug', 'community' );
        $icons       = self::get_icon_urls();

        $manifest = array(
            'name'             => $app_name,
            'short_name'       => $short_name,
            'description'      => $app_name,
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'theme_color'      => $theme_color,
            'background_color' => $bg_color,
            'start_url'        => '/' . $base_slug . '/',
            'scope'            => '/' . $base_slug . '/',
            'icons'            => array(),
        );

        // Build icons array
        foreach ( self::ICON_SIZES as $size ) {
            if ( ! empty( $icons[ $size ] ) ) {
                $icon_entry = array(
                    'src'   => $icons[ $size ],
                    'sizes' => $size . 'x' . $size,
                    'type'  => 'image/png',
                );
                // 192 and 512 are maskable + any
                if ( in_array( $size, array( 192, 512 ), true ) ) {
                    $icon_entry['purpose'] = 'any maskable';
                }
                $manifest['icons'][] = $icon_entry;
            }
        }

        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'Cache-Control: public, max-age=86400' );
        echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    /**
     * Serve the service worker JavaScript.
     * Dynamically injects version and asset URLs.
     */
    public static function serve_service_worker() {
        $base_slug   = get_option( 'cd_base_slug', 'community' );
        $version     = CD_VERSION;
        $cache_name  = 'cd-shell-v' . $version;

        // Build list of assets to precache
        $assets = array(
            CD_PLUGIN_URL . 'public/css/community-directory.css?ver=' . $version,
            CD_PLUGIN_URL . 'public/js/community-directory.js?ver=' . $version,
            CD_PLUGIN_URL . 'public/js/alpine.min.js?ver=3.14.3',
            '/' . $base_slug . '/offline/',
        );

        // Add icon URLs
        $icons = self::get_icon_urls();
        foreach ( $icons as $url ) {
            if ( ! empty( $url ) ) {
                $assets[] = $url;
            }
        }

        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' . $base_slug . '/' );
        header( 'Cache-Control: no-cache' );

        // Output service worker JS
        ?>
// Community Directory Service Worker v<?php echo esc_js( $version ); ?>

const SW_VERSION = <?php echo wp_json_encode( $version ); ?>;
const CACHE_NAME = <?php echo wp_json_encode( $cache_name ); ?>;
const BASE_SCOPE = <?php echo wp_json_encode( '/' . $base_slug . '/' ); ?>;
const OFFLINE_URL = <?php echo wp_json_encode( '/' . $base_slug . '/offline/' ); ?>;

const PRECACHE_ASSETS = <?php echo wp_json_encode( $assets, JSON_UNESCAPED_SLASHES ); ?>;

// Install: precache app shell
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(PRECACHE_ASSETS);
        })
    );
});

// Activate: clean old caches
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(key) {
                    return key.startsWith('cd-shell-') && key !== CACHE_NAME;
                }).map(function(key) {
                    return caches.delete(key);
                })
            );
        }).then(function() {
            return self.clients.claim();
        })
    );
});

// Fetch strategy
self.addEventListener('fetch', function(event) {
    var request = event.request;
    var url = new URL(request.url);

    // API requests: network only (never cache PII)
    if (url.pathname.indexOf('/wp-json/') !== -1) {
        event.respondWith(
            fetch(request).catch(function() {
                return new Response(JSON.stringify({
                    success: false,
                    error: { code: 'offline', message: 'You are offline.' }
                }), {
                    status: 503,
                    headers: { 'Content-Type': 'application/json' }
                });
            })
        );
        return;
    }

    // Navigation requests: network first, fallback to offline page
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function() {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // Static assets (CSS, JS, images): cache first, fallback to network
    if (request.destination === 'style' || request.destination === 'script' ||
        request.destination === 'image' || request.destination === 'font') {
        event.respondWith(
            caches.match(request).then(function(cached) {
                return cached || fetch(request).then(function(response) {
                    // Only cache same-origin successful responses
                    if (response.ok && url.origin === self.location.origin) {
                        var clone = response.clone();
                        caches.open(CACHE_NAME).then(function(cache) {
                            cache.put(request, clone);
                        });
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Everything else: network first
    event.respondWith(
        fetch(request).catch(function() {
            return caches.match(request);
        })
    );
});

// Message handler for skip-waiting
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
<?php
        exit;
    }

    /**
     * Serve the offline fallback page.
     */
    public static function serve_offline_page() {
        $app_name    = get_option( 'cd_pwa_app_name', 'St. Thekla Directory' );
        $theme_color = get_option( 'cd_pwa_theme_color', '#8B0000' );

        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Cache-Control: no-cache' );

        include CD_PLUGIN_DIR . 'public/views/offline.php';
        exit;
    }

    /**
     * Get icon URLs by size.
     *
     * @return array Keyed by size (int) => URL (string).
     */
    public static function get_icon_urls() {
        $icon_id = (int) get_option( 'cd_pwa_icon_id', 0 );
        $urls    = array();

        if ( ! $icon_id ) {
            return $urls;
        }

        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'] . '/' . self::ICON_DIR;

        foreach ( self::ICON_SIZES as $size ) {
            $file_path = $upload_dir['basedir'] . '/' . self::ICON_DIR . '/icon-' . $size . '.png';
            if ( file_exists( $file_path ) ) {
                $urls[ $size ] = $base_url . '/icon-' . $size . '.png';
            }
        }

        return $urls;
    }

    /**
     * AJAX handler for PWA icon upload.
     * Generates all required icon sizes from the uploaded image.
     */
    public static function handle_icon_upload() {
        check_ajax_referer( 'cd_pwa_icon_upload', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            wp_send_json_error( array( 'message' => 'No attachment selected.' ) );
        }

        // Validate the image
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            wp_send_json_error( array( 'message' => 'File not found.' ) );
        }

        $image_size = getimagesize( $file_path );
        if ( ! $image_size ) {
            wp_send_json_error( array( 'message' => 'Invalid image file.' ) );
        }

        if ( $image_size[0] < 512 || $image_size[1] < 512 ) {
            wp_send_json_error( array( 'message' => 'Image must be at least 512x512 pixels. Uploaded image is ' . $image_size[0] . 'x' . $image_size[1] . '.' ) );
        }

        // Create output directory
        $upload_dir = wp_upload_dir();
        $output_dir = $upload_dir['basedir'] . '/' . self::ICON_DIR;
        if ( ! file_exists( $output_dir ) ) {
            wp_mkdir_p( $output_dir );
        }

        // Generate each size
        $generated = array();
        foreach ( self::ICON_SIZES as $size ) {
            $editor = wp_get_image_editor( $file_path );
            if ( is_wp_error( $editor ) ) {
                continue;
            }

            $editor->resize( $size, $size, true ); // true = crop to exact square
            $output_path = $output_dir . '/icon-' . $size . '.png';
            $saved = $editor->save( $output_path, 'image/png' );

            if ( ! is_wp_error( $saved ) ) {
                $generated[] = $size;
            }
        }

        if ( empty( $generated ) ) {
            wp_send_json_error( array( 'message' => 'Failed to generate icon sizes.' ) );
        }

        // Store the attachment ID
        update_option( 'cd_pwa_icon_id', $attachment_id );

        wp_send_json_success( array(
            'message' => 'Generated ' . count( $generated ) . ' icon sizes.',
            'sizes'   => $generated,
            'urls'    => self::get_icon_urls(),
        ) );
    }

    /**
     * Register AJAX handler for icon upload.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_cd_pwa_upload_icon', array( __CLASS__, 'handle_icon_upload' ) );
    }
}
