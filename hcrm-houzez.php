<?php
/**
 * Plugin Name: HCRM Houzez
 * Plugin URI: https://equalpixels.io/houzez-real-estate-crm/
 * Description: Integrates Houzez theme with Laravel CRM for bidirectional property listing sync.
 * Version: 1.0.0
 * Author: Muhammad Shahnawaz
 * Author URI: https://equalpixels.io
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: hcrm-houzez
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version.
 */
define('HCRM_VERSION', '1.0.0');

/**
 * Plugin base path.
 */
define('HCRM_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin base URL.
 */
define('HCRM_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename.
 */
define('HCRM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Database table prefix for plugin tables.
 */
define('HCRM_TABLE_PREFIX', 'hcrm_');

/**
 * The code that runs during plugin activation.
 */
function hcrm_activate() {
    require_once HCRM_PLUGIN_PATH . 'includes/class-hcrm-activator.php';
    HCRM_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function hcrm_deactivate() {
    require_once HCRM_PLUGIN_PATH . 'includes/class-hcrm-deactivator.php';
    HCRM_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'hcrm_activate');
register_deactivation_hook(__FILE__, 'hcrm_deactivate');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require HCRM_PLUGIN_PATH . 'includes/class-hcrm-houzez.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function hcrm_run() {
    $plugin = HCRM_Houzez::get_instance();
    $plugin->run();
}
hcrm_run();
