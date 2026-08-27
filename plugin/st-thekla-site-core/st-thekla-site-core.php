<?php
/**
 * Plugin Name: St. Thekla Site Core
 * Plugin URI:  https://www.sttheklachurch.org/
 * Description: Stable, church-owned public-site features for service schedules, leadership, announcements, contact details, and app integrations.
 * Version:     0.3.0
 * Author:      St. Thekla Church
 * Author URI:  https://www.sttheklachurch.org/
 * Text Domain: st-thekla-site-core
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STC_VERSION', '0.3.0' );
define( 'STC_DATA_VERSION', '003' );
define( 'STC_PLUGIN_FILE', __FILE__ );
define( 'STC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once STC_PLUGIN_DIR . 'includes/class-stc-plugin.php';
require_once STC_PLUGIN_DIR . 'includes/class-stc-weekly-schedule.php';
require_once STC_PLUGIN_DIR . 'includes/class-stc-migrations.php';
require_once STC_PLUGIN_DIR . 'includes/class-stc-visit-us.php';

function stc_activate_plugin() {
    STC_Plugin::activate();
    STC_Weekly_Schedule::activate();
    STC_Visit_Us::activate();
    STC_Migrations::maybe_run();
}
register_activation_hook( __FILE__, 'stc_activate_plugin' );
register_deactivation_hook( __FILE__, array( 'STC_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', static function () {
    STC_Migrations::maybe_run();
    add_action( 'admin_notices', array( 'STC_Migrations', 'render_admin_notice' ) );
    STC_Plugin::instance()->init();
    STC_Weekly_Schedule::instance()->init();
    STC_Visit_Us::instance()->init();
} );
