<?php
/**
 * UPPA Core
 *
 * @package           uppa-core
 * @author            Upper Echelon Digital Services
 * @copyright         2024 Upper Echelon Digital Services
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       UPPA Core
 * Plugin URI:        https://wordpress.org/plugins/uppa-core/
 * Description:       Companion functionality plugin for the UPPA Base parent theme. Registers shared utilities, payment gateway integrations, ACF bridge, and CPT management for sites built by Upper Echelon Digital Services.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Tested up to:      7.0
 * Requires PHP:      8.1
 * Author:            Upper Echelon Digital Services
 * Author URI:        https://uppadigital.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       uppa-core
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

defined( 'UPPA_CORE_VERSION' ) || define( 'UPPA_CORE_VERSION', '1.0.0' );
defined( 'UPPA_CORE_DIR' )     || define( 'UPPA_CORE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'UPPA_CORE_URI' )     || define( 'UPPA_CORE_URI', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'uppa_core_active' ) ) {
	/**
	 * Sentinel detected by the UPPA Base theme via function_exists().
	 *
	 * The theme's inc/compat.php defines this as a fallback stub that returns
	 * false. This declaration (returning true) takes precedence when the plugin
	 * is active, because plugins load before themes in WordPress.
	 *
	 * @return bool Always true when UPPA Core is active.
	 */
	function uppa_core_active(): bool {
		return true;
	}
}

require_once UPPA_CORE_DIR . 'includes/class-uppa-activator.php';
require_once UPPA_CORE_DIR . 'includes/class-uppa-deactivator.php';

register_activation_hook( __FILE__, [ 'UPPA_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'UPPA_Deactivator', 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		require_once UPPA_CORE_DIR . 'includes/class-uppa-core.php';
		UPPA_Core::get_instance()->run();
	}
);
