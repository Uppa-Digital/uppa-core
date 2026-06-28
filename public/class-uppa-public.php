<?php
/**
 * Public-facing functionality for UPPA Core.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Uppa_Public
 */
class Uppa_Public {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Constructor.
	 *
	 * @param string $version Current plugin version.
	 */
	public function __construct( string $version ) {
		$this->version = $version;
	}

	/**
	 * Register public stylesheets.
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style(
			'uppa-core-public',
			UPPA_CORE_URL . 'public/css/uppa-public.css',
			[],
			$this->version
		);
	}

	/**
	 * Register public scripts.
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_script(
			'uppa-core-public',
			UPPA_CORE_URL . 'public/js/uppa-public.js',
			[],
			$this->version,
			true
		);
	}
}
