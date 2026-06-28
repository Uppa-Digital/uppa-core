<?php
/**
 * Asset enqueue helpers shared across UPPA client builds.
 *
 * Provides static helpers for Google Fonts, local @font-face declarations,
 * <link rel="preload"> hints, and theme-aware asset URL resolution. All methods
 * are safe to call from functions.php, a child theme, or a feature plugin.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Asset_Utils
 */
class UPPA_Asset_Utils {

	// -------------------------------------------------------------------------
	// Internal state
	// -------------------------------------------------------------------------

	/**
	 * Tracks whether the fonts.gstatic.com preconnect hint has been added to
	 * wp_head so it is emitted at most once per request.
	 *
	 * @var bool
	 */
	private static bool $gstatic_preconnect_added = false;

	/**
	 * Tracks preload URLs already queued so duplicates are silently skipped.
	 *
	 * @var array<string, true>
	 */
	private static array $preloaded_urls = [];

	// -------------------------------------------------------------------------
	// Google Fonts
	// -------------------------------------------------------------------------

	/**
	 * Enqueue a Google Font stylesheet using the fonts.googleapis.com/css2 API.
	 *
	 * Builds a fonts.googleapis.com/css2 URL that includes every requested weight
	 * in both normal and italic axis variants (ital,wght notation), then registers
	 * and enqueues the stylesheet via wp_enqueue_style().
	 *
	 * Also adds a <link rel="preconnect"> to fonts.gstatic.com the first time
	 * this method is called on the current request, which reduces the time Google
	 * Fonts' CDN needs to serve the actual font files.
	 *
	 * Must be called from a hook that runs before wp_head (e.g. wp_enqueue_scripts).
	 *
	 * @param string        $family  Font family name as it appears on Google Fonts,
	 *                               e.g. 'Inter', 'Playfair Display'. Spaces are
	 *                               allowed; they are encoded in the URL automatically.
	 * @param array<string> $weights Numeric weight strings to request.
	 *                               Default ['400', '600', '700'].
	 * @return void
	 */
	public static function enqueue_google_font(
		string $family,
		array $weights = [ '400', '600', '700' ]
	): void {
		// Derive a safe handle: lowercase, spaces → hyphens, no special chars.
		$slug   = 'uppa-font-' . sanitize_title( $family );
		$family = trim( $family );

		// Build ital,wght axis tuples for every requested weight so both normal
		// and italic variants are available in a single request.
		$tuples = [];
		foreach ( $weights as $weight ) {
			$tuples[] = '0,' . (int) $weight; // normal
			$tuples[] = '1,' . (int) $weight; // italic
		}
		sort( $tuples ); // Google requires the tuples to be sorted.

		$url = add_query_arg(
			[
				'family'  => rawurlencode( $family ) . ':ital,wght@' . implode( ';', $tuples ),
				'display' => 'swap',
			],
			'https://fonts.googleapis.com/css2'
		);

		wp_enqueue_style( $slug, esc_url_raw( $url ), [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs are versioned by the API; a WP version string would break cache.

		// Add the preconnect hint once per request.
		if ( ! self::$gstatic_preconnect_added ) {
			self::$gstatic_preconnect_added = true;
			add_action(
				'wp_head',
				static function (): void {
					echo '<link rel="preconnect" href="' . esc_url( 'https://fonts.gstatic.com' ) . '" crossorigin>' . "\n";
				},
				1 // Priority 1 — before most other wp_head output.
			);
		}
	}

	// -------------------------------------------------------------------------
	// Local fonts
	// -------------------------------------------------------------------------

	/**
	 * Enqueue a locally hosted font file via an inline @font-face declaration.
	 *
	 * The font file is resolved from the child theme directory (falling back to
	 * the parent theme via get_asset_url()). An inline <style> containing the
	 * @font-face rule is attached to the registered empty stylesheet handle so
	 * WordPress manages load order correctly.
	 *
	 * @param string $handle      Unique stylesheet handle, e.g. 'uppa-inter-variable'.
	 *                            Follows the same naming conventions as wp_enqueue_style().
	 * @param string $src         Relative path to the font file within the theme directory,
	 *                            e.g. 'assets/fonts/inter.woff2'.
	 * @param string $format      Font format string used in the @font-face src descriptor.
	 *                            Common values: 'woff2', 'woff', 'truetype'. Default 'woff2'.
	 * @param string $family      CSS font-family name to use in the @font-face rule.
	 *                            Defaults to the $handle value when empty.
	 * @param string $weight      CSS font-weight descriptor. Default 'normal'.
	 * @param string $style       CSS font-style descriptor. Default 'normal'.
	 * @param string $display     CSS font-display descriptor. Default 'swap'.
	 * @return void
	 */
	public static function enqueue_local_font(
		string $handle,
		string $src,
		string $format = 'woff2',
		string $family = '',
		string $weight = 'normal',
		string $style = 'normal',
		string $display = 'swap'
	): void {
		$font_url   = self::get_asset_url( $src );
		$font_family = $family ?: $handle;

		// Register an empty stylesheet as an anchor for the inline style.
		wp_register_style( $handle, false, [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( $handle );

		$face = sprintf(
			'@font-face { font-family: "%s"; src: url("%s") format("%s"); font-weight: %s; font-style: %s; font-display: %s; }',
			esc_attr( $font_family ),
			esc_url_raw( $font_url ),
			esc_attr( $format ),
			esc_attr( $weight ),
			esc_attr( $style ),
			esc_attr( $display )
		);

		wp_add_inline_style( $handle, $face );
	}

	// -------------------------------------------------------------------------
	// Resource hints
	// -------------------------------------------------------------------------

	/**
	 * Add a <link rel="preload"> resource hint to wp_head.
	 *
	 * Preloading tells the browser to fetch a critical asset early in the page
	 * load cycle, before the parser reaches the element that would normally
	 * trigger the download. Most useful for LCP images, hero fonts, and above-
	 * the-fold stylesheets.
	 *
	 * This method is idempotent — calling it more than once with the same URL
	 * only emits one <link> tag.
	 *
	 * Must be called from a hook that runs before wp_head (e.g. wp_enqueue_scripts,
	 * template_redirect, or get_header).
	 *
	 * @param string $url  Absolute URL of the asset to preload. Sanitised with
	 *                     esc_url() before output.
	 * @param string $as   Value of the `as` attribute. Common values: 'image',
	 *                     'font', 'style', 'script', 'fetch'.
	 * @param string $type Optional MIME type for the `type` attribute, e.g.
	 *                     'image/webp', 'font/woff2'. Omitted when empty.
	 * @return void
	 */
	public static function add_preload( string $url, string $as, string $type = '' ): void {
		// Deduplicate — skip if this URL was already queued.
		if ( isset( self::$preloaded_urls[ $url ] ) ) {
			return;
		}
		self::$preloaded_urls[ $url ] = true;

		add_action(
			'wp_head',
			static function () use ( $url, $as, $type ): void {
				// Fonts need crossorigin even for same-origin files (browser uses anonymous CORS for @font-face).
				$crossorigin = ( 'font' === $as ) ? ' crossorigin' : '';
				$type_attr   = ( '' !== $type ) ? ' type="' . esc_attr( $type ) . '"' : '';

				printf(
					'<link rel="preload" href="%s" as="%s"%s%s>' . "\n",
					esc_url( $url ),
					esc_attr( $as ),
					$type_attr,   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr applied above.
					$crossorigin  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string ' crossorigin' or ''.
				);
			},
			2 // Slightly after preconnect (priority 1) but before main wp_head content.
		);
	}

	// -------------------------------------------------------------------------
	// Theme-aware asset URL resolution
	// -------------------------------------------------------------------------

	/**
	 * Resolve a theme asset URL, checking the child theme before the parent.
	 *
	 * Mirrors the logic of get_template_part() for URL-based assets: if a child
	 * theme is active and the file exists within it, the child-theme URL is
	 * returned. Otherwise the parent theme URL is returned unconditionally
	 * (without a filesystem existence check, to match WordPress's own convention
	 * for parent-theme asset fallbacks).
	 *
	 * When $child_first is false the parent theme URL is always returned,
	 * which is useful when a feature plugin ships an asset that must not be
	 * overridden by child themes.
	 *
	 * @param string $path        Relative path to the asset within the theme directory,
	 *                            e.g. 'assets/images/logo.svg' or 'js/main.js'.
	 *                            Leading slash is optional.
	 * @param bool   $child_first Whether to look in the child theme first.
	 *                            Default true.
	 * @return string Fully qualified, esc_url()-sanitised asset URL. When the
	 *                child-theme file does not exist the parent theme URL is
	 *                returned even if that file is also absent, following the
	 *                same convention WordPress uses for get_template_part().
	 */
	public static function get_asset_url( string $path, bool $child_first = true ): string {
		$path = ltrim( $path, '/\\' );

		if ( $child_first && get_stylesheet_directory() !== get_template_directory() ) {
			$child_file = get_stylesheet_directory() . '/' . $path;
			if ( file_exists( $child_file ) ) {
				return esc_url( get_stylesheet_directory_uri() . '/' . $path );
			}
		}

		return esc_url( get_template_directory_uri() . '/' . $path );
	}
}
