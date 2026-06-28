<?php
/**
 * Fired when the plugin is activated.
 *
 * Performs environment checks and sets up initial plugin state.
 * If the server does not meet minimum requirements the plugin is immediately
 * deactivated and the administrator is shown a clear error message.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Activator
 */
class UPPA_Activator {

	/**
	 * Minimum WordPress version required by this plugin.
	 *
	 * @var string
	 */
	private const MIN_WP = '6.4';

	/**
	 * Minimum PHP version required by this plugin.
	 *
	 * @var string
	 */
	private const MIN_PHP = '8.1';

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Run all activation tasks.
	 *
	 * Called by register_activation_hook() in the main plugin file.
	 * Execution order:
	 *   1. check_requirements() — bail early with wp_die() if unmet.
	 *   2. set_version_option() — persist the installed version to the DB.
	 *   3. flush_rewrite_rules() — ensure CPT permalinks are registered.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_requirements();
		self::set_version_option();
		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify WordPress and PHP meet the minimum version requirements.
	 *
	 * If either check fails the plugin is deactivated programmatically and
	 * wp_die() is called with an admin-friendly message so the administrator
	 * understands what needs to be updated. Execution does not continue.
	 *
	 * @return void
	 */
	private static function check_requirements(): void {
		$errors = [];

		if ( version_compare( get_bloginfo( 'version' ), self::MIN_WP, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required WP version, 2: installed WP version */
				__( 'UPPA Core requires WordPress %1$s or higher. You are running WordPress %2$s.', 'uppa-core' ),
				self::MIN_WP,
				get_bloginfo( 'version' )
			);
		}

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: installed PHP version */
				__( 'UPPA Core requires PHP %1$s or higher. Your server is running PHP %2$s.', 'uppa-core' ),
				self::MIN_PHP,
				PHP_VERSION
			);
		}

		if ( empty( $errors ) ) {
			return;
		}

		// Deactivate the plugin before surfacing the error so it does not appear
		// as "active" in the plugins list.
		deactivate_plugins( plugin_basename( UPPA_CORE_DIR . '../uppa-core.php' ) );

		wp_die(
			'<p>' . implode( '</p><p>', array_map( 'esc_html', $errors ) ) . '</p>',
			esc_html__( 'UPPA Core — Activation Error', 'uppa-core' ),
			[ 'back_link' => true ]
		);
	}

	/**
	 * Persist the current plugin version to the WordPress options table.
	 *
	 * Stores the version under the "uppa_core_version" key so that future
	 * upgrade routines can compare the stored value against UPPA_CORE_VERSION
	 * and run any necessary migrations.
	 *
	 * @return void
	 */
	private static function set_version_option(): void {
		update_option( 'uppa_core_version', UPPA_CORE_VERSION, false );
	}
}
