<?php
/**
 * SEO utility helpers — breadcrumb data, meta tag helpers, etc.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_SEO_Utils
 */
class UPPA_SEO_Utils {

	/**
	 * Generate a breadcrumb data array for the current request.
	 *
	 * @return array<int, array{label: string, url: string}> Ordered breadcrumb items.
	 */
	public static function get_breadcrumb_data(): array {
		// TODO: implement breadcrumb builder (home → archive → singular).
		return [];
	}

	/**
	 * Output an Open Graph / Twitter Card meta block for the current post.
	 *
	 * @param int $post_id Post ID (defaults to current post).
	 */
	public static function render_meta_tags( int $post_id = 0 ): void {
		// TODO: implement meta tag output.
	}
}
