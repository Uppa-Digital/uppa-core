<?php
/**
 * Asset enqueue helpers shared across UPPA client builds.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Uppa_Asset_Utils
 */
class Uppa_Asset_Utils {

	/**
	 * Enqueue a versioned stylesheet from the plugin's public/css directory.
	 *
	 * @param string $handle   Script handle.
	 * @param string $filename CSS filename (without path).
	 * @param array<string> $deps    Handle dependencies.
	 */
	public static function enqueue_style( string $handle, string $filename, array $deps = [] ): void {
		// TODO: implement versioned style enqueue.
	}

	/**
	 * Enqueue a versioned script from the plugin's public/js directory.
	 *
	 * @param string $handle   Script handle.
	 * @param string $filename JS filename (without path).
	 * @param array<string> $deps    Handle dependencies.
	 * @param bool   $in_footer Whether to load in footer.
	 */
	public static function enqueue_script(
		string $handle,
		string $filename,
		array $deps = [],
		bool $in_footer = true
	): void {
		// TODO: implement versioned script enqueue.
	}
}
