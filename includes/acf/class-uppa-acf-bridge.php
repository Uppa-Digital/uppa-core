<?php
/**
 * Bridge between UPPA Core and Advanced Custom Fields (Free or Pro).
 *
 * All public methods degrade gracefully when ACF is absent:
 *   - read methods fall back to native WordPress meta functions so templates
 *     continue to work without modification.
 *   - write/registration methods return silently so no errors surface.
 *
 * Typical usage from a theme or feature plugin:
 *
 *   $acf = UPPA_ACF_Bridge::get_instance();
 *
 *   // Register a field group (no-op when ACF is absent):
 *   $acf->register_field_group( [...] );
 *
 *   // Read a field value (falls back to get_post_meta when ACF is absent):
 *   $value = $acf->get_field( 'hero_heading', get_the_ID() );
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_ACF_Bridge
 */
class UPPA_ACF_Bridge {

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/** Prevent cloning of the singleton. */
	private function __clone() {}

	/**
	 * Return the single instance of UPPA_ACF_Bridge.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Detection
	// -------------------------------------------------------------------------

	/**
	 * Check whether ACF (free or Pro) is currently active and functional.
	 *
	 * The presence of acf_add_local_field_group() is the canonical signal that
	 * ACF has fully bootstrapped. This check is intentionally narrow so it does
	 * not give a false positive when ACF's main file is present but not yet loaded
	 * (e.g. during very early hooks).
	 *
	 * @return bool True when ACF is active and usable, false otherwise.
	 */
	public static function is_acf_active(): bool {
		return function_exists( 'acf_add_local_field_group' );
	}

	// -------------------------------------------------------------------------
	// Field group registration
	// -------------------------------------------------------------------------

	/**
	 * Register a field group from a PHP array config.
	 *
	 * When ACF is absent this method returns silently, so callers never need to
	 * guard against a missing ACF themselves. Use this instead of calling
	 * acf_add_local_field_group() directly to keep ACF coupling inside this class.
	 *
	 * The $config array must follow the ACF field group array format. Refer to
	 * the ACF documentation for the full schema:
	 * https://www.advancedcustomfields.com/resources/register-fields-via-php/
	 *
	 * @param array<string, mixed> $config ACF field group configuration array.
	 * @return void
	 */
	public function register_field_group( array $config ): void {
		if ( ! self::is_acf_active() ) {
			return;
		}

		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( $config );
	}

	// -------------------------------------------------------------------------
	// Field value retrieval
	// -------------------------------------------------------------------------

	/**
	 * Retrieve a custom field value, falling back to post meta when ACF is absent.
	 *
	 * When ACF is active this delegates to ACF's get_field(), which honours
	 * field formatting, relationships, and other ACF-specific behaviour.
	 *
	 * When ACF is absent this falls back to get_post_meta() so templates that
	 * call $acf->get_field() continue to return raw meta values rather than null,
	 * keeping pages renderable without ACF installed.
	 *
	 * @param string   $key     ACF field name (not the field key starting with 'field_').
	 * @param int|null $post_id Post ID to read from. Null uses the current post in
	 *                          The Loop; pass an explicit ID outside of The Loop.
	 * @return mixed Field value (ACF-formatted when ACF is active), raw meta string
	 *               when falling back, or an empty string when neither source has data.
	 */
	public function get_field( string $key, ?int $post_id = null ): mixed {
		if ( self::is_acf_active() && function_exists( 'get_field' ) ) {
			return get_field( $key, $post_id );
		}

		// Fallback: read the raw meta value so templates don't silently break.
		$resolved_id = $post_id ?? get_the_ID();
		if ( ! $resolved_id ) {
			return '';
		}

		return get_post_meta( $resolved_id, $key, true );
	}

	/**
	 * Retrieve a field object (metadata about the field, not its value).
	 *
	 * When ACF is active this delegates to ACF's get_field_object(), which
	 * returns the full field definition array including type, label, and settings.
	 *
	 * When ACF is absent this falls back to a minimal array constructed from
	 * get_post_meta() so that callers inspecting the return value receive an array
	 * (not null) and can safely access ['value'] without a type check.
	 *
	 * @param string   $key     ACF field name (not the field key starting with 'field_').
	 * @param int|null $post_id Post ID to read from. Null uses the current post in
	 *                          The Loop; pass an explicit ID outside of The Loop.
	 * @return array<string, mixed>|false Full ACF field object array when ACF is active
	 *                                    and the field exists; a minimal fallback array
	 *                                    when ACF is absent; false if the field key is
	 *                                    not found by ACF.
	 */
	public function get_field_object( string $key, ?int $post_id = null ): mixed {
		if ( self::is_acf_active() && function_exists( 'get_field_object' ) ) {
			return get_field_object( $key, $post_id );
		}

		// Fallback: return a minimal array so callers can read ['value'] safely.
		$resolved_id = $post_id ?? get_the_ID();
		$value       = $resolved_id ? get_post_meta( $resolved_id, $key, true ) : '';

		return [
			'key'   => $key,
			'name'  => $key,
			'value' => $value,
		];
	}

	// -------------------------------------------------------------------------
	// JSON sync
	// -------------------------------------------------------------------------

	/**
	 * Add a directory to ACF's local JSON load paths.
	 *
	 * ACF's local JSON feature saves field group definitions as .json files.
	 * This method registers an additional directory so that a child theme or
	 * feature plugin can store its own field group JSON files and have ACF
	 * detect and sync them automatically.
	 *
	 * Hooks onto acf/settings/load_json at priority 10.
	 * Does nothing when ACF is absent.
	 *
	 * @param string $path Absolute filesystem path to the directory containing
	 *                     ACF JSON files. Trailing slash is optional.
	 * @return void
	 */
	public function sync_json_path( string $path ): void {
		if ( ! self::is_acf_active() ) {
			return;
		}

		$path = rtrim( $path, '/\\' );

		add_filter(
			'acf/settings/load_json',
			static function ( array $paths ) use ( $path ): array {
				$paths[] = $path;
				return $paths;
			}
		);
	}
}
