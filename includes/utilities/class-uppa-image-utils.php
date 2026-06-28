<?php
/**
 * Image utility helpers — background-removal prep, responsive srcset, etc.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Uppa_Image_Utils
 */
class Uppa_Image_Utils {

	/**
	 * Return a fully-qualified srcset string for a given attachment ID.
	 *
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $size          Registered image size name.
	 * @return string HTML-ready srcset attribute value, or empty string on failure.
	 */
	public static function get_srcset( int $attachment_id, string $size = 'full' ): string {
		// TODO: implement srcset generation.
		return '';
	}

	/**
	 * Prepare an attachment for background-removal processing.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array<string, mixed> Prepared payload for the removal API.
	 */
	public static function prepare_for_bg_removal( int $attachment_id ): array {
		// TODO: implement background-removal payload builder.
		return [];
	}
}
