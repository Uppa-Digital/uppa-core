<?php
/**
 * The core plugin class — singleton entry point.
 *
 * Coordinates loader, admin, public, and all sub-modules.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Core
 */
class UPPA_Core {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * The action/filter loader.
	 *
	 * @var UPPA_Loader
	 */
	private UPPA_Loader $loader;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Return (and lazily create) the singleton.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Require all dependency files.
	 */
	private function load_dependencies(): void {
		require_once UPPA_CORE_DIR . 'includes/class-uppa-loader.php';
		require_once UPPA_CORE_DIR . 'includes/class-uppa-activator.php';
		require_once UPPA_CORE_DIR . 'includes/class-uppa-deactivator.php';
		require_once UPPA_CORE_DIR . 'includes/cpt/class-uppa-cpt-manager.php';
		require_once UPPA_CORE_DIR . 'includes/acf/class-uppa-acf-bridge.php';
		require_once UPPA_CORE_DIR . 'includes/utilities/class-uppa-image-utils.php';
		require_once UPPA_CORE_DIR . 'includes/utilities/class-uppa-seo-utils.php';
		require_once UPPA_CORE_DIR . 'includes/utilities/class-uppa-asset-utils.php';
		require_once UPPA_CORE_DIR . 'includes/integrations/class-uppa-paystack.php';
		require_once UPPA_CORE_DIR . 'includes/integrations/class-uppa-flutterwave.php';
		require_once UPPA_CORE_DIR . 'admin/class-uppa-admin.php';
		require_once UPPA_CORE_DIR . 'public/class-uppa-public.php';

		$this->loader = new UPPA_Loader();
	}

	/**
	 * Load the plugin text domain for i18n.
	 */
	private function set_locale(): void {
		$this->loader->add_action(
			'init',
			null,
			function (): void {
				load_plugin_textdomain(
					'uppa-core',
					false,
					UPPA_CORE_DIR . 'languages/'
				);
			}
		);
	}

	/**
	 * Register all admin-side hooks.
	 */
	private function define_admin_hooks(): void {
		$admin = new UPPA_Admin( UPPA_CORE_VERSION );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
	}

	/**
	 * Register all public-side hooks.
	 */
	private function define_public_hooks(): void {
		$public = new UPPA_Public( UPPA_CORE_VERSION );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
	}

	/**
	 * Execute all registered hooks.
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * Return the loader instance.
	 */
	public function get_loader(): UPPA_Loader {
		return $this->loader;
	}
}
