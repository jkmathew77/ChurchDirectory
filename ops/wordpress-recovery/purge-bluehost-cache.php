<?php
/**
 * WP-CLI eval-file helper: purge every available Bluehost/Newfold/Endurance
 * page cache without requiring a particular hosting plugin implementation.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

$results = array(
    'wordpress_object_cache' => false,
    'newfold_cache'          => 'unavailable',
    'endurance_page_cache'   => 'unavailable',
    'jetpack_boost_hook'     => false,
);

$results['wordpress_object_cache'] = (bool) wp_cache_flush();

try {
    if ( class_exists( 'NewfoldLabs\\WP\\ModuleLoader' ) ) {
        $container = NewfoldLabs\WP\ModuleLoader\container();
        if ( $container && method_exists( $container, 'get' ) ) {
            $purger = $container->get( 'cachePurger' );
            if ( is_object( $purger ) && method_exists( $purger, 'purge_all' ) ) {
                $purger->purge_all();
                $results['newfold_cache'] = 'purged';
            }
        }
    }
} catch ( Throwable $error ) {
    $results['newfold_cache'] = 'error:' . sanitize_key( get_class( $error ) );
}

try {
    if ( class_exists( 'Endurance_Page_Cache' ) ) {
        $endurance = Endurance_Page_Cache::get_instance();
        if ( $endurance && method_exists( $endurance, 'purge_all' ) ) {
            if ( property_exists( $endurance, 'force_purge' ) ) {
                $endurance->force_purge = true;
            }
            $endurance->purge_all();
            $results['endurance_page_cache'] = 'purged';
        }
    }
} catch ( Throwable $error ) {
    $results['endurance_page_cache'] = 'error:' . sanitize_key( get_class( $error ) );
}

// Harmless when Jetpack Boost is not installed; useful if it is added later.
do_action( 'jetpack_boost_clear_page_cache_all' );
$results['jetpack_boost_hook'] = true;

$results['completed_at_utc'] = gmdate( 'c' );
echo wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
