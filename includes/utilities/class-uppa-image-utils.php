<?php
/**
 * Image utility helpers.
 *
 * Provides static helpers for responsive images, inline background removal,
 * srcset generation, WebP MIME support, and SVG placeholders. No external
 * library dependencies — every method relies solely on WordPress core functions
 * and native PHP.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Image_Utils
 */
class UPPA_Image_Utils {

	// -------------------------------------------------------------------------
	// Responsive image output
	// -------------------------------------------------------------------------

	/**
	 * Return a fully-formed <img> tag for an attachment with lazy-loading defaults.
	 *
	 * Wraps wp_get_attachment_image() and injects loading="lazy" and
	 * decoding="async" into $attr unless the caller explicitly overrides them.
	 * These two attributes together defer off-screen image decoding to a
	 * background thread, improving Largest Contentful Paint on image-heavy pages.
	 *
	 * @param int                  $attachment_id WordPress media library attachment ID.
	 * @param string               $size          Registered image size name or a
	 *                                            two-element [width, height] array.
	 *                                            Default 'large'.
	 * @param array<string, mixed> $attr          Additional HTML attributes to pass to
	 *                                            wp_get_attachment_image(). Values set
	 *                                            here override the lazy-loading defaults.
	 * @return string Complete <img> HTML string, or an empty string if the
	 *                attachment does not exist.
	 */
	public static function get_responsive_image(
		int $attachment_id,
		string $size = 'large',
		array $attr = []
	): string {
		$attr = array_merge(
			[
				'loading'  => 'lazy',
				'decoding' => 'async',
			],
			$attr
		);

		return wp_get_attachment_image( $attachment_id, $size, false, $attr );
	}

	// -------------------------------------------------------------------------
	// CSS blend-mode background removal
	// -------------------------------------------------------------------------

	/**
	 * Return an <img> tag that visually removes white backgrounds via CSS.
	 *
	 * Applies mix-blend-mode: multiply as an inline style. In multiply blending
	 * white pixels (RGB 255,255,255) become fully transparent against any
	 * non-white background, providing a lightweight, zero-server-cost alternative
	 * to raster background removal for product images on white backgrounds.
	 *
	 * Limitations: the technique only works against non-white page backgrounds
	 * and does not alter the underlying image file.
	 *
	 * @param string               $image_url    Absolute or relative URL of the image.
	 * @param string               $alt          Alt text for the <img> element.
	 *                                           Default empty string (decorative).
	 * @param array<string, mixed> $extra_attr   Optional additional HTML attributes
	 *                                           (e.g. class, width, height). A 'style'
	 *                                           key here is merged with the blend-mode
	 *                                           rule, not replaced.
	 * @return string <img> HTML string with inline mix-blend-mode: multiply.
	 */
	public static function remove_bg_inline(
		string $image_url,
		string $alt = '',
		array $extra_attr = []
	): string {
		$blend_style = 'mix-blend-mode:multiply;';

		// Merge any caller-supplied style rather than overwriting it.
		if ( ! empty( $extra_attr['style'] ) ) {
			$blend_style .= $extra_attr['style'];
		}
		$extra_attr['style'] = $blend_style;

		$attr_string = '';
		foreach ( $extra_attr as $name => $value ) {
			$attr_string .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return sprintf(
			'<img src="%s" alt="%s" loading="lazy" decoding="async"%s>',
			esc_url( $image_url ),
			esc_attr( $alt ),
			$attr_string
		);
	}

	// -------------------------------------------------------------------------
	// srcset generation
	// -------------------------------------------------------------------------

	/**
	 * Build a srcset attribute value from an attachment and a list of size names.
	 *
	 * Iterates over the supplied registered size names, resolves each to a URL
	 * and width via wp_get_attachment_image_src(), and assembles the pairs into
	 * a standards-compliant srcset string (e.g. "image-300.jpg 300w, …").
	 *
	 * Sizes for which no image file exists (e.g. not yet generated) are silently
	 * skipped so the output is always a valid, usable srcset.
	 *
	 * @param int           $attachment_id WordPress media library attachment ID.
	 * @param array<string> $sizes         Registered image size names to include,
	 *                                     e.g. ['thumbnail', 'medium', 'large', 'full'].
	 *                                     Order is preserved in the output string.
	 * @return string srcset attribute value (without the "srcset=" wrapper), or an
	 *                empty string if no matching image files are found.
	 */
	public static function get_image_srcset( int $attachment_id, array $sizes ): string {
		$entries = [];

		foreach ( $sizes as $size ) {
			$src = wp_get_attachment_image_src( $attachment_id, $size );

			// wp_get_attachment_image_src() returns false when the size is unavailable.
			if ( false === $src || empty( $src[0] ) || empty( $src[1] ) ) {
				continue;
			}

			[ $url, $width ] = $src;

			$entries[] = esc_url( $url ) . ' ' . (int) $width . 'w';
		}

		return implode( ', ', $entries );
	}

	// -------------------------------------------------------------------------
	// WebP MIME type support
	// -------------------------------------------------------------------------

	/**
	 * Add WebP to the list of allowed upload MIME types.
	 *
	 * Designed to be registered as a filter callback on the upload_mimes hook:
	 *
	 *   add_filter( 'upload_mimes', [ 'UPPA_Image_Utils', 'add_webp_support' ] );
	 *
	 * WordPress 5.8+ natively allows WebP uploads; this helper keeps older
	 * installations in parity and serves as a single authoritative registration
	 * point that can be removed without hunting through theme files.
	 *
	 * @param array<string, string> $mimes Associative array of extension => MIME type
	 *                                     provided by WordPress (e.g. ['jpg' => 'image/jpeg']).
	 * @return array<string, string> The same array with 'webp' => 'image/webp' appended
	 *                               if it is not already present.
	 */
	public static function add_webp_support( array $mimes ): array {
		if ( ! isset( $mimes['webp'] ) ) {
			$mimes['webp'] = 'image/webp';
		}
		return $mimes;
	}

	// -------------------------------------------------------------------------
	// SVG placeholder
	// -------------------------------------------------------------------------

	/**
	 * Return a data-URI-encoded inline SVG suitable for use as a placeholder src.
	 *
	 * The SVG is a filled rectangle in the given dimensions and fill color,
	 * base64-encoded and wrapped in a data: URI so it can be assigned directly
	 * to an <img> src attribute. This keeps the DOM layout stable before the
	 * real image loads, preventing cumulative layout shift (CLS).
	 *
	 * Typical usage with JavaScript lazy loading:
	 *
	 *   <img src="<?= esc_attr( UPPA_Image_Utils::get_placeholder_svg( 1200, 630 ) ); ?>"
	 *        data-src="<?= esc_url( $real_url ); ?>"
	 *        class="lazyload" alt="">
	 *
	 * @param int    $width  Placeholder width in pixels. Default 800.
	 * @param int    $height Placeholder height in pixels. Default 600.
	 * @param string $color  CSS fill color for the rectangle. Accepts any valid CSS
	 *                       color value (hex, rgb, named). Default '#E2E8F0' (cool grey).
	 * @return string data: URI string (data:image/svg+xml;base64,…) ready for use
	 *                as an <img> src value.
	 */
	public static function get_placeholder_svg(
		int $width = 800,
		int $height = 600,
		string $color = '#E2E8F0'
	): string {
		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
			. '<rect width="%d" height="%d" fill="%s"/>'
			. '</svg>',
			$width,
			$height,
			$width,
			$height,
			$width,
			$height,
			esc_attr( $color )
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
