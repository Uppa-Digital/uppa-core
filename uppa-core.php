<?php
/**
 * UPPA Core
 *
 * @package           uppa-core
 * @author            UPPA Digital
 * @copyright         2024 UPPA Digital
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       UPPA Core
 * Plugin URI:        https://uppadigital.com/plugins/uppa-core
 * Description:       Core functionality plugin for UPPA Digital client builds — CPT management, ACF bridge, payment integrations, and shared utilities.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            UPPA Digital
 * Author URI:        https://uppadigital.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       uppa-core
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin version and path constants.
define( 'UPPA_CORE_VERSION', '1.0.0' );
define( 'UPPA_CORE_FILE', __FILE__ );
define( 'UPPA_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'UPPA_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'UPPA_CORE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activation hook.
 */
function uppa_core_activate(): void {
	require_once UPPA_CORE_PATH . 'includes/class-uppa-activator.php';
	Uppa_Activator::activate();
}
register_activation_hook( __FILE__, 'uppa_core_activate' );

/**
 * Deactivation hook.
 */
function uppa_core_deactivate(): void {
	require_once UPPA_CORE_PATH . 'includes/class-uppa-deactivator.php';
	Uppa_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'uppa_core_deactivate' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function uppa_core_run(): void {
	require_once UPPA_CORE_PATH . 'includes/class-uppa-loader.php';
	require_once UPPA_CORE_PATH . 'includes/class-uppa-core.php';
	$plugin = Uppa_Core::get_instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'uppa_core_run' );
