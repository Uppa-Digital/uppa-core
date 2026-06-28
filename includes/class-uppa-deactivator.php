<?php
/**
 * Runs on plugin deactivation.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Deactivator
 */
class UPPA_Deactivator {

	/**
	 * Perform deactivation tasks (flush rewrite rules, clear scheduled events, etc.).
	 */
	public static function deactivate(): void {
		// TODO: clear scheduled cron events, flush rewrite rules.
		flush_rewrite_rules();
	}
}
