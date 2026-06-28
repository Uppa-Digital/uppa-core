<?php
/**
 * Admin-area controller for UPPA Core.
 *
 * Registers the admin menu, sub-pages, Settings API fields, and stylesheet.
 * The stylesheet is scoped to the plugin's own pages via hook_suffix check.
 * All settings are stored under the single option key "uppa_core_settings".
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Admin
 */
class UPPA_Admin {

	/**
	 * Plugin version — passed to wp_enqueue_style() for cache-busting.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Hook suffixes returned by add_menu_page() / add_submenu_page().
	 * Used to scope stylesheet enqueue to the plugin's own screens only.
	 *
	 * @var array<string, string>  Keys: 'dashboard', 'settings'.
	 */
	private array $page_hooks = [];

	/**
	 * Option name under which all plugin settings are stored.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'uppa_core_settings';

	/**
	 * Settings section ID used with the WordPress Settings API.
	 *
	 * @var string
	 */
	private const SECTION_PAYSTACK = 'uppa_core_paystack';

	/**
	 * Settings section ID for Flutterwave keys.
	 *
	 * @var string
	 */
	private const SECTION_FLUTTERWAVE = 'uppa_core_flutterwave';

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param string $version Current plugin version string.
	 */
	public function __construct( string $version ) {
		$this->version = $version;
	}

	// -------------------------------------------------------------------------
	// Asset enqueue
	// -------------------------------------------------------------------------

	/**
	 * Enqueue the admin stylesheet — only on the plugin's own pages.
	 *
	 * The $hook_suffix parameter passed by admin_enqueue_scripts matches the
	 * return value of add_menu_page() / add_submenu_page(), so we store those
	 * values in $page_hooks during register_menu() and compare here.
	 *
	 * @param string $hook_suffix The hook suffix of the currently loaded admin page.
	 * @return void
	 */
	public function enqueue_styles( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'uppa-core-admin',
			UPPA_CORE_URI . 'admin/css/uppa-admin.css',
			[],
			$this->version
		);
	}

	/**
	 * Enqueue admin scripts — only on the plugin's own pages.
	 *
	 * @param string $hook_suffix The hook suffix of the currently loaded admin page.
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}
		// TODO: enqueue admin JS when needed.
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	/**
	 * Register the top-level "UPPA Core" menu and its two sub-pages.
	 *
	 * Stores the hook suffixes returned by add_menu_page() and add_submenu_page()
	 * in $this->page_hooks so enqueue_styles() can gate on them.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Top-level menu — callback renders the Dashboard page.
		$dashboard_hook = add_menu_page(
			__( 'UPPA Core', 'uppa-core' ),
			__( 'UPPA Core', 'uppa-core' ),
			'manage_options',
			'uppa-core',
			[ $this, 'render_dashboard_page' ],
			'dashicons-layout',
			80
		);

		// "Dashboard" sub-page — mirrors the top-level entry so the label is explicit.
		$this->page_hooks['dashboard'] = add_submenu_page(
			'uppa-core',
			__( 'UPPA Core Dashboard', 'uppa-core' ),
			__( 'Dashboard', 'uppa-core' ),
			'manage_options',
			'uppa-core',           // Same slug — shares the top-level page.
			[ $this, 'render_dashboard_page' ]
		);

		// "Settings" sub-page.
		$this->page_hooks['settings'] = add_submenu_page(
			'uppa-core',
			__( 'UPPA Core Settings', 'uppa-core' ),
			__( 'Settings', 'uppa-core' ),
			'manage_options',
			'uppa-core-settings',
			[ $this, 'render_settings_page' ]
		);

		// The top-level hook suffix comes back as a string — store it too.
		if ( is_string( $dashboard_hook ) ) {
			$this->page_hooks['top'] = $dashboard_hook;
		}
	}

	// -------------------------------------------------------------------------
	// Settings API registration
	// -------------------------------------------------------------------------

	/**
	 * Register the plugin settings, sections, and fields with the Settings API.
	 *
	 * Hook this method onto admin_init. All four gateway keys are stored as a
	 * single serialised array under self::OPTION_NAME so only one DB row is used.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'uppa_core_settings_group',
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [],
			]
		);

		// --- Paystack section ---
		add_settings_section(
			self::SECTION_PAYSTACK,
			__( 'Paystack', 'uppa-core' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Enter your Paystack API keys. Find them in your Paystack dashboard under Settings → API Keys &amp; Webhooks.', 'uppa-core' ) . '</p>';
			},
			'uppa-core-settings'
		);

		add_settings_field(
			'paystack_public_key',
			__( 'Public Key', 'uppa-core' ),
			[ $this, 'render_text_field' ],
			'uppa-core-settings',
			self::SECTION_PAYSTACK,
			[
				'field'       => 'paystack_public_key',
				'placeholder' => 'pk_live_…',
				'desc'        => __( 'Starts with pk_live_ (production) or pk_test_ (test mode).', 'uppa-core' ),
			]
		);

		add_settings_field(
			'paystack_secret_key',
			__( 'Secret Key', 'uppa-core' ),
			[ $this, 'render_password_field' ],
			'uppa-core-settings',
			self::SECTION_PAYSTACK,
			[
				'field'       => 'paystack_secret_key',
				'placeholder' => 'sk_live_…',
				'desc'        => __( 'Keep this key private — never expose it in client-side code.', 'uppa-core' ),
			]
		);

		// --- Flutterwave section ---
		add_settings_section(
			self::SECTION_FLUTTERWAVE,
			__( 'Flutterwave', 'uppa-core' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Enter your Flutterwave API keys. Find them in your Flutterwave dashboard under Settings → API.', 'uppa-core' ) . '</p>';
			},
			'uppa-core-settings'
		);

		add_settings_field(
			'flw_public_key',
			__( 'Public Key', 'uppa-core' ),
			[ $this, 'render_text_field' ],
			'uppa-core-settings',
			self::SECTION_FLUTTERWAVE,
			[
				'field'       => 'flw_public_key',
				'placeholder' => 'FLWPUBK_TEST-…',
				'desc'        => __( 'Starts with FLWPUBK_ (production) or FLWPUBK_TEST- (test mode).', 'uppa-core' ),
			]
		);

		add_settings_field(
			'flw_secret_key',
			__( 'Secret Key', 'uppa-core' ),
			[ $this, 'render_password_field' ],
			'uppa-core-settings',
			self::SECTION_FLUTTERWAVE,
			[
				'field'       => 'flw_secret_key',
				'placeholder' => 'FLWSECK_TEST-…',
				'desc'        => __( 'Keep this key private — never expose it in client-side code.', 'uppa-core' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Settings sanitization
	// -------------------------------------------------------------------------

	/**
	 * Sanitise the settings array before it is written to the database.
	 *
	 * Called automatically by the Settings API as the sanitize_callback for
	 * self::OPTION_NAME. Each gateway key is stripped of whitespace and
	 * sanitised as a plain text field.
	 *
	 * @param mixed $raw Raw POST input from the settings form. Expected to be
	 *                   an array; treated as empty array if not.
	 * @return array<string, string> Sanitised settings array.
	 */
	public function sanitize_settings( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean = [];
		$keys  = [
			'paystack_public_key',
			'paystack_secret_key',
			'flw_public_key',
			'flw_secret_key',
		];

		foreach ( $keys as $key ) {
			$clean[ $key ] = isset( $raw[ $key ] )
				? sanitize_text_field( trim( (string) $raw[ $key ] ) )
				: '';
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Field renderers (called by Settings API)
	// -------------------------------------------------------------------------

	/**
	 * Render a plain text input for a settings field.
	 *
	 * @param array{field: string, placeholder?: string, desc?: string} $args Field metadata
	 *        passed from add_settings_field().
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$options = (array) get_option( self::OPTION_NAME, [] );
		$field   = $args['field'];
		$value   = $options[ $field ] ?? '';
		$name    = self::OPTION_NAME . '[' . $field . ']';
		$desc    = $args['desc'] ?? '';
		$placeholder = $args['placeholder'] ?? '';
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $field ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text"
			autocomplete="off"
		>
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a masked password input for a settings field.
	 *
	 * The field is intentionally not auto-filled (autocomplete="new-password") and
	 * displays a placeholder indicating a saved key exists without revealing it.
	 * The raw value is never re-populated into the markup to prevent leaking it
	 * in the page source.
	 *
	 * @param array{field: string, placeholder?: string, desc?: string} $args Field metadata
	 *        passed from add_settings_field().
	 * @return void
	 */
	public function render_password_field( array $args ): void {
		$options = (array) get_option( self::OPTION_NAME, [] );
		$field   = $args['field'];
		$saved   = ! empty( $options[ $field ] );
		$name    = self::OPTION_NAME . '[' . $field . ']';
		$desc    = $args['desc'] ?? '';
		$placeholder = $saved
			? __( '(saved — leave blank to keep current key)', 'uppa-core' )
			: ( $args['placeholder'] ?? '' );
		?>
		<input
			type="password"
			id="<?php echo esc_attr( $field ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value=""
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text"
			autocomplete="new-password"
		>
		<?php if ( $saved ) : ?>
			<span class="uppa-key-saved">&#10003; <?php esc_html_e( 'Key saved', 'uppa-core' ); ?></span>
		<?php endif; ?>
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/**
	 * Render the Dashboard sub-page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'uppa-core' ) );
		}
		require_once UPPA_CORE_DIR . 'admin/partials/uppa-admin-display.php';
	}

	/**
	 * Render the Settings sub-page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'uppa-core' ) );
		}
		require_once UPPA_CORE_DIR . 'admin/partials/uppa-settings-display.php';
	}
}
