<?php
/**
 * Fired when the plugin is deactivated.
 *
 * Deactivation is intentionally lightweight — no data is deleted.
 * Data removal is handled exclusively by uninstall.php when the plugin
 * is actually deleted, following WordPress best practice.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Deactivator
 */
class UPPA_Deactivator {

	/**
	 * Run all deactivation tasks.
	 *
	 * Called by register_deactivation_hook() in the main plugin file.
	 * Flushes rewrite rules so any CPT slugs registered by this plugin
	 * are removed from the rewrite table immediately on deactivation.
	 *
	 * No plugin data (options, meta, etc.) is modified here. That responsibility
	 * belongs to uninstall.php, which only runs when the plugin is deleted.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
