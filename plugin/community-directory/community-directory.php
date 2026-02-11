<?php
/**
 * Plugin Name: Community Directory
 * Plugin URI:  https://sttheklachurch.org
 * Description: A secure, members-only church community directory with application workflow, Google OAuth, household management, and PWA support.
 * Version:     0.3.84
 * Author:      St. Thekla Church
 * Author URI:  https://sttheklachurch.org
 * Text Domain: community-directory
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'CD_VERSION', '0.3.84' );
define( 'CD_DB_VERSION', '007' );
define( 'CD_PLUGIN_FILE', __FILE__ );
define( 'CD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CD_TABLE_PREFIX', 'cd_' );
define( 'CD_API_NAMESPACE', 'community-directory/v1' );

/**
 * Autoloader for plugin classes.
 *
 * Maps class names like CD_Some_Class to includes/class-some-class.php
 * and CD_API_Some_Class to includes/api/class-some-class.php
 * and CD_Admin_Some_Class to includes/admin/class-some-class.php
 */
spl_autoload_register( function ( $class ) {
    // Only handle our plugin's classes
    if ( strpos( $class, 'CD_' ) !== 0 ) {
        return;
    }

    // Remove the CD_ prefix
    $relative = substr( $class, 3 );

    // Determine subdirectory based on prefix
    $subdir = '';
    if ( strpos( $relative, 'API_' ) === 0 ) {
        $subdir = 'api/';
        $relative = substr( $relative, 4 );
    } elseif ( strpos( $relative, 'Admin_' ) === 0 ) {
        $subdir = 'admin/';
        $relative = substr( $relative, 6 );
    }

    // Convert class name to file name: Some_Class -> class-some-class.php
    $filename = 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    $filepath = CD_PLUGIN_DIR . 'includes/' . $subdir . $filename;

    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
} );

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, 'cd_activate_plugin' );
function cd_activate_plugin() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';
    CD_Activator::activate();
}

/**
 * Plugin deactivation hook.
 */
function cd_deactivate() {
    require_once CD_PLUGIN_DIR . 'includes/class-deactivator.php';
    CD_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'cd_deactivate' );

/**
 * Add Settings link on the Plugins page.
 */
function cd_plugin_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=cd-settings' ) ) . '">'
        . esc_html__( 'Settings', 'community-directory' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cd_plugin_action_links' );

/**
 * Initialize the plugin.
 */
function cd_init() {
    $plugin = CD_Plugin::get_instance();
    $plugin->init();
}
add_action( 'plugins_loaded', 'cd_init' );
