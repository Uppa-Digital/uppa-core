<?php
/**
 * Flutterwave payment integration.
 *
 * A lightweight wrapper around the Flutterwave v3 REST API for custom payment
 * forms. This class is NOT a WooCommerce gateway — it is designed for direct API
 * calls from custom checkout flows, donation forms, and event registration pages
 * built by UPPA Digital.
 *
 * Unlike Paystack (which works in kobo), Flutterwave accepts amounts in the
 * major currency unit — pass naira directly (e.g. 5000 for ₦5,000).
 *
 * Typical usage:
 *
 *   $flw = new UPPA_Flutterwave();
 *
 *   $result = $flw->initialize_payment([
 *       'amount'       => 5000,
 *       'currency'     => 'NGN',
 *       'redirect_url' => home_url( '/payment/callback/' ),
 *       'customer'     => [
 *           'email'       => 'customer@example.com',
 *           'name'        => 'Ada Obi',
 *           'phonenumber' => '08012345678',
 *       ],
 *   ]);
 *
 *   if ( is_wp_error( $result ) ) {
 *       // handle error
 *   } else {
 *       wp_redirect( $result['payment_link'] );
 *   }
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Flutterwave
 */
class UPPA_Flutterwave {

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
	 * Return the single shared instance, creating it on first call.
	 *
	 * Keys are loaded once from the database and reused across all callers in
	 * the same request, avoiding repeated get_option() calls.
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
	// Constants
	// -------------------------------------------------------------------------

	/**
	 * Flutterwave REST API v3 base URL.
	 *
	 * @var string
	 */
	private string $base_url = 'https://api.flutterwave.com/v3';

	// -------------------------------------------------------------------------
	// Properties
	// -------------------------------------------------------------------------

	/**
	 * Flutterwave secret key used for server-side API authorisation.
	 *
	 * Loaded from the 'uppa_flw_secret_key' option, falling back to the
	 * FLW_SECRET_KEY constant when the option is empty.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Flutterwave public key used for client-side SDK initialisation.
	 *
	 * Loaded from the 'uppa_flw_public_key' option, falling back to the
	 * FLW_PUBLIC_KEY constant when the option is empty.
	 *
	 * @var string
	 */
	private string $public_key;

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	/**
	 * Instantiate the Flutterwave integration and load API keys.
	 *
	 * Key resolution order (first non-empty value wins):
	 *   1. WordPress option  'uppa_core_settings' (keys: flw_secret_key / flw_public_key).
	 *   2. PHP constant       FLW_SECRET_KEY / FLW_PUBLIC_KEY.
	 *   3. Empty string       (methods return WP_Error when keys are absent).
	 *
	 * Keys are stored inside the unified 'uppa_core_settings' array option managed
	 * by the UPPA Core Settings page. The constant fallback supports
	 * environment-variable-driven deployments (e.g. Bedrock / 12-factor).
	 */
	public function __construct() {
		$settings = (array) get_option( 'uppa_core_settings', [] );

		$this->secret_key = $settings['flw_secret_key'] ?? '';
		if ( '' === $this->secret_key && defined( 'FLW_SECRET_KEY' ) ) {
			$this->secret_key = (string) FLW_SECRET_KEY;
		}

		$this->public_key = $settings['flw_public_key'] ?? '';
		if ( '' === $this->public_key && defined( 'FLW_PUBLIC_KEY' ) ) {
			$this->public_key = (string) FLW_PUBLIC_KEY;
		}
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Initialise a Flutterwave payment and return the hosted payment link.
	 *
	 * POSTs to the /payments endpoint. On success the customer should be
	 * redirected to the returned 'payment_link'. After payment Flutterwave
	 * redirects the customer to 'redirect_url' with a 'transaction_id' query
	 * parameter that you pass to verify_transaction().
	 *
	 * Required keys in $args:
	 *   amount        float|int           Amount in the major currency unit
	 *                                     (naira for NGN, cedis for GHS, etc.).
	 *                                     Must be > 0. Do NOT convert to kobo.
	 *   redirect_url  string              URL Flutterwave redirects to after payment.
	 *   customer      array {
	 *       email        string  Customer email address (required).
	 *       name         string  Customer display name (required).
	 *       phonenumber  string  Customer phone number (optional, recommended).
	 *   }
	 *
	 * Optional keys in $args:
	 *   tx_ref        string              Unique transaction reference. Auto-generated
	 *                                     via uniqid("uppa_flw_") when absent.
	 *   currency      string              ISO 4217 currency code. Default 'NGN'.
	 *   payment_options string            Comma-separated payment methods, e.g.
	 *                                     'card,banktransfer,ussd'.
	 *   customizations array {
	 *       title       string  Modal header title.
	 *       description string  Short payment description.
	 *       logo        string  URL to your brand logo.
	 *   }
	 *   meta          array<string, mixed> Arbitrary metadata stored with the
	 *                                      transaction (must be flat key-value pairs).
	 *
	 * @param array<string, mixed> $args Payment arguments. See above for keys.
	 * @return array{status: bool, payment_link: string, tx_ref: string}|WP_Error
	 *         On success: status=true, the hosted payment_link URL, and the tx_ref.
	 *         On failure: WP_Error with a descriptive code and message.
	 */
	public function initialize_payment( array $args ): array|WP_Error {
		// --- Validate required fields ---
		$customer = $args['customer'] ?? [];

		if ( empty( $customer['email'] ) || ! is_email( $customer['email'] ) ) {
			return new WP_Error(
				'flw_invalid_email',
				__( 'A valid customer email address is required to initialise a Flutterwave payment.', 'uppa-core' )
			);
		}

		if ( empty( $customer['name'] ) ) {
			return new WP_Error(
				'flw_invalid_customer',
				__( 'A customer name is required to initialise a Flutterwave payment.', 'uppa-core' )
			);
		}

		if ( empty( $args['amount'] ) || (float) $args['amount'] <= 0 ) {
			return new WP_Error(
				'flw_invalid_amount',
				__( 'A positive amount is required to initialise a Flutterwave payment.', 'uppa-core' )
			);
		}

		if ( empty( $args['redirect_url'] ) ) {
			return new WP_Error(
				'flw_missing_redirect',
				__( 'A redirect_url is required so Flutterwave can return the customer after payment.', 'uppa-core' )
			);
		}

		// --- Build payload ---
		$tx_ref = isset( $args['tx_ref'] ) && '' !== $args['tx_ref']
			? sanitize_text_field( $args['tx_ref'] )
			: uniqid( 'uppa_flw_', true );

		$payload = [
			'tx_ref'       => $tx_ref,
			'amount'       => (float) $args['amount'],
			'currency'     => isset( $args['currency'] ) ? strtoupper( sanitize_text_field( $args['currency'] ) ) : 'NGN',
			'redirect_url' => esc_url_raw( $args['redirect_url'] ),
			'customer'     => [
				'email'       => sanitize_email( $customer['email'] ),
				'name'        => sanitize_text_field( $customer['name'] ),
				'phonenumber' => sanitize_text_field( $customer['phonenumber'] ?? '' ),
			],
		];

		if ( ! empty( $args['payment_options'] ) ) {
			$payload['payment_options'] = sanitize_text_field( $args['payment_options'] );
		}

		if ( ! empty( $args['customizations'] ) && is_array( $args['customizations'] ) ) {
			$custom = $args['customizations'];
			$payload['customizations'] = [
				'title'       => sanitize_text_field( $custom['title']       ?? '' ),
				'description' => sanitize_text_field( $custom['description'] ?? '' ),
				'logo'        => esc_url_raw( $custom['logo'] ?? '' ),
			];
		}

		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			$payload['meta'] = $args['meta'];
		}

		// --- Call the API ---
		$response = $this->api_request( 'POST', '/payments', $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['data']['link'] ) ) {
			return new WP_Error(
				'flw_init_error',
				__( 'Flutterwave did not return a payment link. Please try again.', 'uppa-core' ),
				$response
			);
		}

		return [
			'status'       => true,
			'payment_link' => esc_url_raw( $response['data']['link'] ),
			'tx_ref'       => $tx_ref,
		];
	}

	/**
	 * Verify a completed Flutterwave transaction by its numeric transaction ID.
	 *
	 * GETs /transactions/{id}/verify. Call this from the redirect_url handler
	 * after Flutterwave returns the customer to your site. The transaction_id is
	 * provided as a query parameter in the redirect URL.
	 *
	 * Always verify server-side before fulfilling an order — never trust the
	 * client-side Flutterwave SDK's success callback alone.
	 *
	 * Important: the transaction_id used here is Flutterwave's own numeric ID
	 * (from the redirect's ?transaction_id= parameter), not your tx_ref string.
	 * Cross-check the returned tx_ref against your stored value to guard against
	 * transaction ID substitution attacks.
	 *
	 * @param string $transaction_id Flutterwave numeric transaction ID received
	 *                               in the redirect URL query parameter.
	 * @return array{status: bool, amount: float, currency: string, data: array<string, mixed>}|WP_Error
	 *         On success: status=true, the verified amount (in major currency unit),
	 *         the currency code, and the full Flutterwave data object for audit logging.
	 *         On failure: WP_Error with a descriptive code and message.
	 */
	public function verify_transaction( string $transaction_id ): array|WP_Error {
		$transaction_id = sanitize_text_field( $transaction_id );

		if ( '' === $transaction_id ) {
			return new WP_Error(
				'flw_invalid_transaction_id',
				__( 'A non-empty transaction ID is required for Flutterwave verification.', 'uppa-core' )
			);
		}

		$response = $this->api_request( 'GET', '/transactions/' . rawurlencode( $transaction_id ) . '/verify' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response['data'] ?? [];

		// Flutterwave uses 'successful' as the transaction status string.
		if ( ( $response['status'] ?? '' ) !== 'success' || ( $data['status'] ?? '' ) !== 'successful' ) {
			return new WP_Error(
				'flw_verify_error',
				$response['message'] ?? __( 'Flutterwave transaction could not be verified.', 'uppa-core' ),
				$response
			);
		}

		return [
			'status'   => true,
			'amount'   => (float) ( $data['amount'] ?? 0.0 ),
			'currency' => (string) ( $data['currency'] ?? '' ),
			'data'     => $data,
		];
	}

	/**
	 * Return the sanitised Flutterwave public key for use in JavaScript.
	 *
	 * Safe to pass to wp_localize_script() or embed in a data attribute for
	 * use with the Flutterwave inline JS SDK. The public key identifies your
	 * Flutterwave account to the client-side SDK and is safe to expose in page
	 * source.
	 *
	 * @return string Sanitised public key string, or empty string when no key
	 *                has been configured.
	 */
	public function get_public_key(): string {
		return sanitize_text_field( $this->public_key );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Perform an authenticated HTTP request to the Flutterwave v3 API.
	 *
	 * Handles both GET and POST methods. The secret key is sent as a Bearer
	 * token in the Authorization header as required by the Flutterwave API.
	 * Responses are JSON-decoded and the decoded body is returned.
	 *
	 * Error handling:
	 *   - Missing secret key               → WP_Error 'flw_missing_key'
	 *   - wp_remote_*() transport failure  → WP_Error 'flw_request_failed'
	 *   - HTTP status code outside 2xx     → WP_Error 'flw_http_error'
	 *   - Non-JSON or empty response body  → WP_Error 'flw_invalid_response'
	 *   - Flutterwave API-level failure    → WP_Error 'flw_api_error'
	 *
	 * @param string               $method   HTTP method: 'GET' or 'POST'.
	 * @param string               $endpoint API path including leading slash,
	 *                                       e.g. '/payments' or '/transactions/123/verify'.
	 * @param array<string, mixed> $body     Request body for POST requests.
	 *                                       Ignored for GET.
	 * @return array<string, mixed>|WP_Error Decoded JSON response body on success,
	 *                                       or a descriptive WP_Error on any failure.
	 */
	private function api_request( string $method, string $endpoint, array $body = [] ): array|WP_Error {
		if ( '' === $this->secret_key ) {
			return new WP_Error(
				'flw_missing_key',
				__( 'Flutterwave secret key is not configured. Add it via UPPA Core settings or the FLW_SECRET_KEY constant.', 'uppa-core' )
			);
		}

		$url = $this->base_url . $endpoint;

		$request_args = [
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->secret_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Cache-Control' => 'no-cache',
			],
		];

		if ( 'POST' === strtoupper( $method ) ) {
			$request_args['body'] = wp_json_encode( $body );
			$raw = wp_remote_post( $url, $request_args );
		} else {
			$raw = wp_remote_get( $url, $request_args ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		}

		// Transport-level failure (DNS, timeout, SSL, etc.).
		if ( is_wp_error( $raw ) ) {
			return new WP_Error(
				'flw_request_failed',
				sprintf(
					/* translators: %s: underlying transport error message */
					__( 'Flutterwave API request failed: %s', 'uppa-core' ),
					$raw->get_error_message()
				),
				[ 'endpoint' => $endpoint ]
			);
		}

		$http_code = wp_remote_retrieve_response_code( $raw );
		$raw_body  = wp_remote_retrieve_body( $raw );

		// Non-2xx HTTP status.
		if ( $http_code < 200 || $http_code >= 300 ) {
			$decoded = json_decode( $raw_body, true );
			return new WP_Error(
				'flw_http_error',
				$decoded['message'] ?? sprintf(
					/* translators: %d: HTTP status code */
					__( 'Flutterwave returned HTTP %d.', 'uppa-core' ),
					(int) $http_code
				),
				[ 'http_code' => $http_code, 'body' => $decoded ?? $raw_body ]
			);
		}

		$decoded = json_decode( $raw_body, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'flw_invalid_response',
				__( 'Flutterwave returned an unexpected non-JSON response.', 'uppa-core' ),
				[ 'raw' => $raw_body ]
			);
		}

		// Flutterwave wraps every response in a top-level 'status' string ('success' or 'error').
		if ( ( $decoded['status'] ?? '' ) !== 'success' ) {
			return new WP_Error(
				'flw_api_error',
				$decoded['message'] ?? __( 'Flutterwave returned an unknown API error.', 'uppa-core' ),
				$decoded
			);
		}

		return $decoded;
	}
}
