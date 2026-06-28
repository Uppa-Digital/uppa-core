<?php
/**
 * Admin-area functionality for UPPA Core.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Admin
 */
class UPPA_Admin {

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
	 * Register admin stylesheets.
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style(
			'uppa-core-admin',
			UPPA_CORE_URI . 'admin/css/uppa-admin.css',
			[],
			$this->version
		);
	}

	/**
	 * Register admin scripts.
	 */
	public function enqueue_scripts(): void {
		// TODO: enqueue admin JS when needed.
	}

	/**
	 * Add top-level admin menu item and settings sub-page.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'UPPA Core', 'uppa-core' ),
			__( 'UPPA Core', 'uppa-core' ),
			'manage_options',
			'uppa-core',
			[ $this, 'render_settings_page' ],
			'dashicons-admin-plugins',
			80
		);
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page(): void {
		require_once UPPA_CORE_DIR . 'admin/partials/uppa-admin-display.php';
	}
}
