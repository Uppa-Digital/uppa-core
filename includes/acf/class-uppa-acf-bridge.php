<?php
/**
 * Bridge between UPPA Core and Advanced Custom Fields.
 *
 * Gracefully degrades when ACF is not active.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Uppa_ACF_Bridge
 */
class Uppa_ACF_Bridge {

	/**
	 * Whether ACF (or ACF Pro) is currently available.
	 */
	public static function is_available(): bool {
		return function_exists( 'acf_add_local_field_group' );
	}

	/**
	 * Initialise ACF integrations.
	 *
	 * Called from Uppa_Core on `acf/init` when ACF is present.
	 */
	public function init(): void {
		if ( ! self::is_available() ) {
			return;
		}
		// TODO: register field groups, options pages, etc.
	}
}
