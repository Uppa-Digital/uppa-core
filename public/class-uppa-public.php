<?php
/**
 * Public-facing controller for UPPA Core.
 *
 * Enqueues front-end assets, localises JavaScript variables, and registers
 * the AJAX handlers used by custom payment forms. All AJAX endpoints verify
 * a nonce, sanitise every input, and return JSON before calling wp_die().
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Public
 */
class UPPA_Public {

	/**
	 * Plugin version string — used for asset cache-busting.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Script handle for the main public JavaScript file.
	 *
	 * Stored as a constant so the AJAX handler registration and
	 * wp_localize_script() call always reference the same string.
	 *
	 * @var string
	 */
	private const SCRIPT_HANDLE = 'uppa-core-public';

	/**
	 * Nonce action used for all front-end AJAX requests.
	 *
	 * Must match the action string verified in every AJAX handler.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'uppa_core_nonce';

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
	 * Enqueue the public stylesheet.
	 *
	 * Hooked to wp_enqueue_scripts. WordPress only fires that hook on the
	 * front-end, so the is_admin() guard is a secondary safety net.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'uppa-core-public',
			UPPA_CORE_URI . 'public/css/uppa-public.css',
			[],
			$this->version
		);
	}

	/**
	 * Enqueue the public script and pass runtime variables to it.
	 *
	 * Hooked to wp_enqueue_scripts. The script is loaded in the footer
	 * (fifth argument = true) to avoid render-blocking.
	 *
	 * wp_localize_script() makes the uppaCore object available globally in
	 * the browser before uppa-public.js executes, so payment form handlers
	 * can read ajaxUrl, nonce, and gateway public keys without hard-coding them.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			UPPA_CORE_URI . 'public/js/uppa-public.js',
			[],
			$this->version,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'uppaCore',
			[
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( self::NONCE_ACTION ),
				'paystackPublicKey'    => UPPA_Paystack::get_instance()->get_public_key(),
				'flutterwavePublicKey' => UPPA_Flutterwave::get_instance()->get_public_key(),
				'currency'             => self::get_default_currency(),
				'siteUrl'              => home_url(),
				// Signals that the current page is a payment callback URL so
				// the JS auto-fires verification on DOMContentLoaded.
				'isCallback'           => self::detect_payment_callback(),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Settings helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the configured default currency code, falling back to 'NGN'.
	 *
	 * Reads the uppa_core_settings option so the value comes from the admin
	 * Settings page rather than being hard-coded.
	 *
	 * @return string Uppercase ISO 4217 currency code.
	 */
	private static function get_default_currency(): string {
		$settings = (array) get_option( 'uppa_core_settings', [] );
		$currency = strtoupper( sanitize_text_field( $settings['default_currency'] ?? '' ) );
		return $currency ?: 'NGN';
	}

	// -------------------------------------------------------------------------
	// Payment callback detection
	// -------------------------------------------------------------------------

	/**
	 * Detect whether the current URL is a gateway payment callback.
	 *
	 * Paystack appends ?reference=xxx&trxref=xxx to the callback URL.
	 * Flutterwave appends ?status=successful&tx_ref=xxx&transaction_id=xxx.
	 *
	 * Returns an array with enough data for the JS layer to auto-verify,
	 * or false when the current page is not a recognised callback URL.
	 *
	 * @return array{gateway: string, reference?: string, transaction_id?: string}|false
	 */
	private static function detect_payment_callback(): array|false {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only URL param detection, no state change here.
		if ( ! empty( $_GET['reference'] ) && ! empty( $_GET['trxref'] ) ) {
			return [
				'gateway'   => 'paystack',
				'reference' => sanitize_text_field( wp_unslash( $_GET['reference'] ) ),
			];
		}

		if ( isset( $_GET['status'] ) && ! empty( $_GET['transaction_id'] ) ) {
			return [
				'gateway'        => 'flutterwave',
				'transaction_id' => sanitize_text_field( wp_unslash( $_GET['transaction_id'] ) ),
				'status'         => sanitize_key( wp_unslash( $_GET['status'] ) ),
			];
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return false;
	}

	// -------------------------------------------------------------------------
	// Automatic front-end enhancements
	// -------------------------------------------------------------------------

	/**
	 * Inject lazy-loading attributes on every WordPress attachment image.
	 *
	 * Hooked to wp_get_attachment_image_attributes. Only sets loading and
	 * decoding when the caller has not already supplied them, so explicit
	 * overrides (e.g. loading="eager" on the LCP hero image) are respected.
	 *
	 * @param array<string, string> $attr Existing attribute array from WordPress.
	 * @return array<string, string>
	 */
	public function add_lazy_loading_defaults( array $attr ): array {
		if ( ! isset( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}
		if ( ! isset( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	/**
	 * Output Organization JSON-LD schema on wp_head.
	 *
	 * Uses the site name, URL, and Custom Logo (Customizer) to build a
	 * schema.org/Organization block. Disable with:
	 *   add_filter( 'uppa_core_output_organization_schema', '__return_false' );
	 *
	 * @return void
	 */
	public function output_organization_schema(): void {
		if ( ! apply_filters( 'uppa_core_output_organization_schema', true ) ) {
			return;
		}
		echo UPPA_SEO_Utils::schema_organization(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method returns pre-escaped JSON-LD script tag.
	}

	/**
	 * Output BreadcrumbList JSON-LD schema on wp_head for content pages.
	 *
	 * Fires on singular posts/pages, archives, search, and 404 — skips the
	 * front page (a one-item "Home" trail has no SEO value as a BreadcrumbList).
	 * Disable with:
	 *   add_filter( 'uppa_core_output_breadcrumb_schema', '__return_false' );
	 *
	 * @return void
	 */
	public function output_breadcrumb_schema(): void {
		if ( is_front_page() ) {
			return;
		}
		if ( ! apply_filters( 'uppa_core_output_breadcrumb_schema', true ) ) {
			return;
		}
		$crumbs = UPPA_SEO_Utils::get_breadcrumbs();
		if ( count( $crumbs ) > 1 ) {
			echo UPPA_SEO_Utils::schema_breadcrumb( $crumbs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method returns pre-escaped JSON-LD script tag.
		}
	}

	// -------------------------------------------------------------------------
	// AJAX: payment initialisation
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler — initialise a payment with the chosen gateway.
	 *
	 * Registered for both wp_ajax_ (logged-in) and wp_ajax_nopriv_ (guests)
	 * because payment forms may appear on public pages.
	 *
	 * Expected POST fields:
	 *   nonce    string  WordPress nonce (action: uppa_core_nonce).
	 *   gateway  string  'paystack' or 'flutterwave'.
	 *
	 * Paystack-specific fields (passed through to initialize_transaction()):
	 *   email        string
	 *   amount       int     Amount in kobo (naira × 100).
	 *   reference    string  Optional — auto-generated when absent.
	 *   callback_url string  Optional.
	 *   currency     string  Optional. Default 'NGN'.
	 *   metadata     array   Optional.
	 *
	 * Flutterwave-specific fields (passed through to initialize_payment()):
	 *   amount        float
	 *   redirect_url  string
	 *   customer      array   { email, name, phonenumber }
	 *   tx_ref        string  Optional.
	 *   currency      string  Optional. Default 'NGN'.
	 *   customizations array  Optional.
	 *
	 * @return never  Always calls wp_send_json_success() or wp_send_json_error()
	 *                followed by wp_die().
	 */
	public function ajax_init_payment(): never {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$gateway = sanitize_key( wp_unslash( $_POST['gateway'] ?? '' ) );

		if ( ! in_array( $gateway, [ 'paystack', 'flutterwave' ], true ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid gateway specified. Use "paystack" or "flutterwave".', 'uppa-core' ) ],
				400
			);
		}

		if ( 'paystack' === $gateway ) {
			$result = $this->init_paystack();
		} else {
			$result = $this->init_flutterwave();
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				],
				422
			);
		}

		wp_send_json_success( $result );
	}

	// -------------------------------------------------------------------------
	// AJAX: payment verification
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler — verify a completed payment with the chosen gateway.
	 *
	 * Registered for both wp_ajax_ and wp_ajax_nopriv_. Call this from the
	 * payment callback page after the gateway redirects the customer back.
	 *
	 * Expected POST fields:
	 *   nonce          string  WordPress nonce (action: uppa_core_nonce).
	 *   gateway        string  'paystack' or 'flutterwave'.
	 *   reference      string  Paystack transaction reference (Paystack only).
	 *   transaction_id string  Flutterwave numeric transaction ID (Flutterwave only).
	 *
	 * @return never  Always calls wp_send_json_success() or wp_send_json_error()
	 *                followed by wp_die().
	 */
	public function ajax_verify_payment(): never {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$gateway = sanitize_key( wp_unslash( $_POST['gateway'] ?? '' ) );

		if ( ! in_array( $gateway, [ 'paystack', 'flutterwave' ], true ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid gateway specified. Use "paystack" or "flutterwave".', 'uppa-core' ) ],
				400
			);
		}

		if ( 'paystack' === $gateway ) {
			$reference = sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) );

			if ( '' === $reference ) {
				wp_send_json_error(
					[ 'message' => __( 'A transaction reference is required to verify a Paystack payment.', 'uppa-core' ) ],
					400
				);
			}

			$result = UPPA_Paystack::get_instance()->verify_transaction( $reference );

		} else {
			$transaction_id = sanitize_text_field( wp_unslash( $_POST['transaction_id'] ?? '' ) );

			if ( '' === $transaction_id ) {
				wp_send_json_error(
					[ 'message' => __( 'A transaction ID is required to verify a Flutterwave payment.', 'uppa-core' ) ],
					400
				);
			}

			$result = UPPA_Flutterwave::get_instance()->verify_transaction( $transaction_id );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				],
				422
			);
		}

		wp_send_json_success( $result );
	}

	// -------------------------------------------------------------------------
	// Private gateway helpers
	// -------------------------------------------------------------------------

	/**
	 * Build and dispatch a Paystack initialize_transaction() call from POST data.
	 *
	 * Sanitises each POST field individually before passing the assembled array
	 * to the gateway. Optional fields are only included when present and non-empty
	 * so the gateway's own validation layer handles missing required fields.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function init_paystack(): array|WP_Error {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in calling public method.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash applied inline below.
		$args = [
			'email'  => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'amount' => absint( wp_unslash( $_POST['amount'] ?? 0 ) ),
		];

		if ( ! empty( $_POST['reference'] ) ) {
			$args['reference'] = sanitize_text_field( wp_unslash( $_POST['reference'] ) );
		}

		if ( ! empty( $_POST['callback_url'] ) ) {
			$args['callback_url'] = esc_url_raw( wp_unslash( $_POST['callback_url'] ) );
		}

		if ( ! empty( $_POST['currency'] ) ) {
			$args['currency'] = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) );
		}

		if ( ! empty( $_POST['metadata'] ) && is_array( $_POST['metadata'] ) ) {
			// Shallow sanitise metadata values — callers must not store raw HTML here.
			$args['metadata'] = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['metadata'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		return UPPA_Paystack::get_instance()->initialize_transaction( $args );
	}

	/**
	 * Build and dispatch a Flutterwave initialize_payment() call from POST data.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function init_flutterwave(): array|WP_Error {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in calling public method.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash applied inline below.
		// Customer sub-array — each field sanitised individually.
		$customer_raw = is_array( $_POST['customer'] ?? null ) ? wp_unslash( (array) $_POST['customer'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitised individually in $customer below.
		$customer     = [
			'email'       => sanitize_email( $customer_raw['email'] ?? '' ),
			'name'        => sanitize_text_field( $customer_raw['name'] ?? '' ),
			'phonenumber' => sanitize_text_field( $customer_raw['phonenumber'] ?? '' ),
		];

		$args = [
			'amount'       => (float) sanitize_text_field( wp_unslash( $_POST['amount'] ?? '0' ) ),
			'redirect_url' => esc_url_raw( wp_unslash( $_POST['redirect_url'] ?? '' ) ),
			'customer'     => $customer,
		];

		if ( ! empty( $_POST['tx_ref'] ) ) {
			$args['tx_ref'] = sanitize_text_field( wp_unslash( $_POST['tx_ref'] ) );
		}

		if ( ! empty( $_POST['currency'] ) ) {
			$args['currency'] = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) );
		}

		if ( ! empty( $_POST['payment_options'] ) ) {
			$args['payment_options'] = sanitize_text_field( wp_unslash( $_POST['payment_options'] ) );
		}

		if ( ! empty( $_POST['customizations'] ) && is_array( $_POST['customizations'] ) ) {
			$custom               = wp_unslash( (array) $_POST['customizations'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitised individually below.
			$args['customizations'] = [
				'title'       => sanitize_text_field( $custom['title']       ?? '' ),
				'description' => sanitize_text_field( $custom['description'] ?? '' ),
				'logo'        => esc_url_raw( $custom['logo']               ?? '' ),
			];
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		return UPPA_Flutterwave::get_instance()->initialize_payment( $args );
	}
}
