<?php
/**
 * The core plugin class — singleton orchestrator.
 *
 * Instantiates every sub-system, wires all action and filter hooks through
 * UPPA_Loader, and exposes a single run() entry point called from the main
 * plugin file.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Core
 */
class UPPA_Core {

	// -------------------------------------------------------------------------
	// Properties
	// -------------------------------------------------------------------------

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Hook registration manager.
	 *
	 * Collects all add_action / add_filter calls and defers them to WordPress
	 * until run() is invoked.
	 *
	 * @var UPPA_Loader
	 */
	private UPPA_Loader $loader;

	/**
	 * Admin-area controller.
	 *
	 * Kept as a property so that define_admin_hooks() can pass it to the
	 * loader by reference and callers can retrieve it via get_admin().
	 *
	 * @var UPPA_Admin
	 */
	private UPPA_Admin $admin;

	/**
	 * Public-facing controller.
	 *
	 * @var UPPA_Public
	 */
	private UPPA_Public $public;

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Return the single instance of UPPA_Core, creating it on first call.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Bootstrap the plugin.
	 *
	 * Execution order matters:
	 *   1. load_dependencies()   — require_once every class file.
	 *   2. Instantiate Loader, Admin, Public as properties.
	 *   3. set_locale()          — queue the text-domain action.
	 *   4. define_admin_hooks()  — queue all admin-side hooks.
	 *   5. define_public_hooks() — queue all public-side hooks.
	 *
	 * Hooks are not registered with WordPress until run() is called.
	 *
	 * @return void
	 */
	private function __construct() {
		$this->load_dependencies();

		$this->loader = new UPPA_Loader();
		$this->admin  = new UPPA_Admin( UPPA_CORE_VERSION );
		$this->public = new UPPA_Public( UPPA_CORE_VERSION );

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/** Prevent cloning of the singleton. */
	private function __clone() {}

	// -------------------------------------------------------------------------
	// Dependency loading
	// -------------------------------------------------------------------------

	/**
	 * Require every class file the plugin depends on.
	 *
	 * All paths use the UPPA_CORE_DIR constant defined in the main plugin file.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		// Infrastructure.
		require_once UPPA_CORE_DIR . 'includes/class-uppa-loader.php';
		require_once UPPA_CORE_DIR . 'includes/class-uppa-activator.php';
		require_once UPPA_CORE_DIR . 'includes/class-uppa-deactivator.php';

		// Feature modules.
		require_once UPPA_CORE_DIR . 'includes/cpt/class-uppa-cpt-manager.php';
		require_once UPPA_CORE_DIR . 'includes/acf/class-uppa-acf-bridge.php';

		// Utilities.
		require_once UPPA_CORE_DIR . 'includes/utilities/class-uppa-image-utils.php';
		require_once UPPA_CORE_DIR . 'includes/utilities/class-uppa-seo-utils.php';
		require_once UPPA_CORE_DIR . 'includes/utilities/class-uppa-asset-utils.php';

		// Payment integrations.
		require_once UPPA_CORE_DIR . 'includes/integrations/class-uppa-paystack.php';
		require_once UPPA_CORE_DIR . 'includes/integrations/class-uppa-flutterwave.php';

		// Surface controllers.
		require_once UPPA_CORE_DIR . 'admin/class-uppa-admin.php';
		require_once UPPA_CORE_DIR . 'public/class-uppa-public.php';
	}

	// -------------------------------------------------------------------------
	// Localisation
	// -------------------------------------------------------------------------

	/**
	 * Queue the text-domain load on the init action.
	 *
	 * Uses a closure so no additional method is exposed on the public API.
	 *
	 * @return void
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

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	/**
	 * Queue all admin-side actions and filters through the loader.
	 *
	 * Every hook here targets the wp-admin context. They are not registered
	 * with WordPress until run() calls $this->loader->run().
	 *
	 * @return void
	 */
	private function define_admin_hooks(): void {
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $this->admin, 'register_menu' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );
	}

	/**
	 * Queue all public-facing actions and filters through the loader.
	 *
	 * Every hook here targets front-end page requests. They are not registered
	 * with WordPress until run() calls $this->loader->run().
	 *
	 * @return void
	 */
	private function define_public_hooks(): void {
		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );

		// Payment AJAX — both logged-in (wp_ajax_) and guest (wp_ajax_nopriv_) variants.
		$this->loader->add_action( 'wp_ajax_uppa_init_payment',        $this->public, 'ajax_init_payment' );
		$this->loader->add_action( 'wp_ajax_nopriv_uppa_init_payment',  $this->public, 'ajax_init_payment' );
		$this->loader->add_action( 'wp_ajax_uppa_verify_payment',       $this->public, 'ajax_verify_payment' );
		$this->loader->add_action( 'wp_ajax_nopriv_uppa_verify_payment', $this->public, 'ajax_verify_payment' );
	}

	// -------------------------------------------------------------------------
	// Execution
	// -------------------------------------------------------------------------

	/**
	 * Flush all queued hooks to WordPress.
	 *
	 * Called once from the main plugin file after get_instance() returns.
	 * Delegates entirely to UPPA_Loader::run().
	 *
	 * @return void
	 */
	public function run(): void {
		$this->loader->run();
	}

	// -------------------------------------------------------------------------
	// Accessors
	// -------------------------------------------------------------------------

	/**
	 * Return the loader instance (useful for inspection in tests).
	 *
	 * @return UPPA_Loader
	 */
	public function get_loader(): UPPA_Loader {
		return $this->loader;
	}

	/**
	 * Return the admin controller instance.
	 *
	 * @return UPPA_Admin
	 */
	public function get_admin(): UPPA_Admin {
		return $this->admin;
	}

	/**
	 * Return the public controller instance.
	 *
	 * @return UPPA_Public
	 */
	public function get_public(): UPPA_Public {
		return $this->public;
	}
}
