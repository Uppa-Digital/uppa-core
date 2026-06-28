<?php
/**
 * Runs on plugin activation.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Activator
 */
class UPPA_Activator {

	/**
	 * Perform activation tasks (flush rewrite rules, create default options, etc.).
	 */
	public static function activate(): void {
		// TODO: create default options, custom DB tables, flush rewrite rules.
		flush_rewrite_rules();
	}
}
