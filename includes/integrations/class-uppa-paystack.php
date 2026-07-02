<?php
/**
 * Paystack payment integration.
 *
 * A lightweight wrapper around the Paystack REST API for custom payment forms.
 * This class is NOT a WooCommerce gateway — it is designed for direct API calls
 * from custom checkout flows, donation forms, and event registration pages built
 * by UPPA Digital.
 *
 * All monetary amounts are in the smallest currency unit (kobo for NGN,
 * pesewas for GHS, etc.). Multiply naira × 100 before passing to any method.
 *
 * Typical usage:
 *
 *   $paystack = new UPPA_Paystack();
 *
 *   $result = $paystack->initialize_transaction([
 *       'email'  => 'customer@example.com',
 *       'amount' => 5000 * 100, // ₦5,000 in kobo
 *   ]);
 *
 *   if ( is_wp_error( $result ) ) {
 *       // handle error
 *   } else {
 *       wp_redirect( $result['authorization_url'] );
 *   }
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Paystack
 */
class UPPA_Paystack {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Paystack REST API base URL.
	 *
	 * @var string
	 */
	private string $base_url = 'https://api.paystack.co';

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

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
	// Properties
	// -------------------------------------------------------------------------

	/**
	 * Paystack secret key used for server-side API authorisation.
	 *
	 * Loaded from the 'uppa_paystack_secret_key' option, falling back to the
	 * PAYSTACK_SECRET_KEY constant when the option is empty.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Paystack public key used for client-side SDK initialisation.
	 *
	 * Loaded from the 'uppa_paystack_public_key' option, falling back to the
	 * PAYSTACK_PUBLIC_KEY constant when the option is empty.
	 *
	 * @var string
	 */
	private string $public_key;

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	/**
	 * Instantiate the Paystack integration and load API keys.
	 *
	 * Key resolution order (first non-empty value wins):
	 *   1. WordPress option  'uppa_core_settings' (keys: paystack_secret_key / paystack_public_key).
	 *   2. PHP constant       PAYSTACK_SECRET_KEY / PAYSTACK_PUBLIC_KEY.
	 *   3. Empty string       (methods return WP_Error when keys are absent).
	 *
	 * Keys are stored inside the unified 'uppa_core_settings' array option managed
	 * by the UPPA Core Settings page. The constant fallback supports
	 * environment-variable-driven deployments (e.g. Bedrock / 12-factor).
	 */
	public function __construct() {
		$settings = (array) get_option( 'uppa_core_settings', [] );

		$this->secret_key = $settings['paystack_secret_key'] ?? '';
		if ( '' === $this->secret_key && defined( 'PAYSTACK_SECRET_KEY' ) ) {
			$this->secret_key = (string) PAYSTACK_SECRET_KEY;
		}

		$this->public_key = $settings['paystack_public_key'] ?? '';
		if ( '' === $this->public_key && defined( 'PAYSTACK_PUBLIC_KEY' ) ) {
			$this->public_key = (string) PAYSTACK_PUBLIC_KEY;
		}
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Initialise a Paystack transaction and return the authorisation URL.
	 *
	 * POSTs to the /transaction/initialize endpoint. On success the customer
	 * should be redirected to the returned 'authorization_url'. After payment
	 * Paystack redirects the customer back to 'callback_url' (if supplied) or
	 * to the URL configured in the Paystack dashboard.
	 *
	 * Required keys in $args:
	 *   email   string  Customer's email address.
	 *   amount  int     Amount in kobo (naira × 100). Must be > 0.
	 *
	 * Optional keys in $args:
	 *   reference    string          Unique transaction reference. Auto-generated
	 *                                via uniqid("uppa_") when absent.
	 *   callback_url string          URL Paystack redirects to after payment.
	 *   currency     string          ISO 4217 currency code. Default 'NGN'.
	 *   channels     array<string>   Payment channels: 'card', 'bank', 'ussd',
	 *                                'qr', 'mobile_money', 'bank_transfer'.
	 *   metadata     array<string, mixed>  Arbitrary key-value pairs stored with
	 *                                      the transaction (max 5 keys).
	 *
	 * @param array<string, mixed> $args Transaction arguments. See above for keys.
	 * @return array{status: bool, authorization_url: string, reference: string}|WP_Error
	 *         On success: status=true, authorization_url, and the transaction reference.
	 *         On failure: WP_Error with code 'paystack_init_error'.
	 *
	 * @throws InvalidArgumentException If 'email' or 'amount' are missing (dev-time guard,
	 *         caught internally and returned as WP_Error in production).
	 */
	public function initialize_transaction( array $args ): array|WP_Error {
		// --- Validate required fields ---
		if ( empty( $args['email'] ) || ! is_email( $args['email'] ) ) {
			return new WP_Error(
				'paystack_invalid_email',
				__( 'A valid email address is required to initialise a Paystack transaction.', 'uppa-core' )
			);
		}

		if ( empty( $args['amount'] ) || (int) $args['amount'] <= 0 ) {
			return new WP_Error(
				'paystack_invalid_amount',
				__( 'A positive amount in kobo is required to initialise a Paystack transaction.', 'uppa-core' )
			);
		}

		// --- Build payload ---
		$payload = [
			'email'    => sanitize_email( $args['email'] ),
			'amount'   => (int) $args['amount'],
			'reference'=> isset( $args['reference'] ) && '' !== $args['reference']
				? sanitize_text_field( $args['reference'] )
				: uniqid( 'uppa_', true ),
			'currency' => isset( $args['currency'] ) ? strtoupper( sanitize_text_field( $args['currency'] ) ) : 'NGN',
		];

		if ( ! empty( $args['callback_url'] ) ) {
			$payload['callback_url'] = esc_url_raw( $args['callback_url'] );
		}

		if ( ! empty( $args['channels'] ) && is_array( $args['channels'] ) ) {
			$payload['channels'] = array_map( 'sanitize_key', $args['channels'] );
		}

		if ( ! empty( $args['metadata'] ) && is_array( $args['metadata'] ) ) {
			$payload['metadata'] = $args['metadata'];
		}

		// --- Call the API ---
		$response = $this->api_request( 'POST', '/transaction/initialize', $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['data']['authorization_url'] ) ) {
			return new WP_Error(
				'paystack_init_error',
				__( 'Paystack did not return an authorisation URL. Please try again.', 'uppa-core' ),
				$response
			);
		}

		return [
			'status'            => true,
			'authorization_url' => esc_url_raw( $response['data']['authorization_url'] ),
			'reference'         => (string) ( $response['data']['reference'] ?? $payload['reference'] ),
		];
	}

	/**
	 * Verify a completed Paystack transaction by its reference string.
	 *
	 * GETs /transaction/verify/{reference}. Call this from the callback URL
	 * handler (or a webhook listener) after Paystack redirects the customer back
	 * to your site.  Always verify server-side before fulfilling an order — never
	 * trust client-side payment confirmation.
	 *
	 * @param string $reference The transaction reference returned by
	 *                          initialize_transaction() or received in the callback.
	 * @return array{status: bool, amount: int, paid_at: string, data: array<string, mixed>}|WP_Error
	 *         On success: status=true, amount (in kobo), paid_at (ISO 8601 string),
	 *         and the full Paystack data object for storage or audit logging.
	 *         On failure: WP_Error with code 'paystack_verify_error'.
	 */
	public function verify_transaction( string $reference ): array|WP_Error {
		$reference = sanitize_text_field( $reference );

		if ( '' === $reference ) {
			return new WP_Error(
				'paystack_invalid_reference',
				__( 'A non-empty transaction reference is required for verification.', 'uppa-core' )
			);
		}

		$response = $this->api_request( 'GET', '/transaction/verify/' . rawurlencode( $reference ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response['data'] ?? [];

		// Paystack uses 'success' as the status string for successful transactions.
		if ( ( $response['status'] ?? false ) !== true || ( $data['status'] ?? '' ) !== 'success' ) {
			return new WP_Error(
				'paystack_verify_error',
				$response['message'] ?? __( 'Transaction could not be verified.', 'uppa-core' ),
				$response
			);
		}

		return [
			'status'  => true,
			'amount'  => (int) ( $data['amount'] ?? 0 ),
			'paid_at' => (string) ( $data['paid_at'] ?? '' ),
			'data'    => $data,
		];
	}

	/**
	 * Return the sanitised Paystack public key for use in JavaScript.
	 *
	 * Safe to pass to wp_localize_script() or embed in a data attribute.
	 * The public key is not secret — it identifies your Paystack account to
	 * the client-side SDK and is safe to expose in page source.
	 *
	 * @return string Sanitised public key string, or empty string when no key
	 *                has been configured.
	 */
	public function get_public_key(): string {
		return sanitize_text_field( $this->public_key );
	}

	/**
	 * Return true when a secret key has been configured.
	 *
	 * Does not expose the key itself — used only for dashboard status display.
	 *
	 * @return bool
	 */
	public function has_secret_key(): bool {
		return '' !== $this->secret_key;
	}

	/**
	 * Return the secret key for use in webhook signature verification only.
	 *
	 * This value must never be sent to the browser or logged. It is exposed
	 * here solely so UPPA_Webhooks can perform HMAC-SHA512 verification of
	 * the X-Paystack-Signature header in the server-side webhook endpoint.
	 *
	 * @return string The raw secret key, or empty string when not configured.
	 */
	public function get_secret_key_for_webhook(): string {
		return $this->secret_key;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Perform an authenticated HTTP request to the Paystack API.
	 *
	 * Handles both GET and POST methods. The secret key is sent as a Bearer
	 * token in the Authorization header as required by the Paystack API.
	 * Responses are JSON-decoded and the decoded body is returned.
	 *
	 * Error handling:
	 *   - Missing secret key               → WP_Error 'paystack_missing_key'
	 *   - wp_remote_*() transport failure  → WP_Error (passed through)
	 *   - HTTP status code outside 2xx     → WP_Error 'paystack_http_error'
	 *   - Non-JSON or empty response body  → WP_Error 'paystack_invalid_response'
	 *   - Paystack API-level failure       → WP_Error 'paystack_api_error'
	 *
	 * @param string               $method   HTTP method: 'GET' or 'POST'.
	 * @param string               $endpoint API path including leading slash,
	 *                                       e.g. '/transaction/initialize'.
	 * @param array<string, mixed> $body     Request body for POST requests.
	 *                                       Ignored for GET.
	 * @return array<string, mixed>|WP_Error Decoded JSON response body on success,
	 *                                       or a descriptive WP_Error on any failure.
	 */
	private function api_request( string $method, string $endpoint, array $body = [] ): array|WP_Error {
		if ( '' === $this->secret_key ) {
			return new WP_Error(
				'paystack_missing_key',
				__( 'Paystack secret key is not configured. Add it via UPPA Core settings or the PAYSTACK_SECRET_KEY constant.', 'uppa-core' )
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
				'paystack_request_failed',
				sprintf(
					/* translators: %s: underlying transport error message */
					__( 'Paystack API request failed: %s', 'uppa-core' ),
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
				'paystack_http_error',
				$decoded['message'] ?? sprintf(
					/* translators: %d: HTTP status code */
					__( 'Paystack returned HTTP %d.', 'uppa-core' ),
					(int) $http_code
				),
				[ 'http_code' => $http_code, 'body' => $decoded ?? $raw_body ]
			);
		}

		$decoded = json_decode( $raw_body, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'paystack_invalid_response',
				__( 'Paystack returned an unexpected non-JSON response.', 'uppa-core' ),
				[ 'raw' => $raw_body ]
			);
		}

		// Paystack wraps every response in a top-level 'status' boolean.
		if ( ( $decoded['status'] ?? false ) !== true ) {
			return new WP_Error(
				'paystack_api_error',
				$decoded['message'] ?? __( 'Paystack returned an unknown API error.', 'uppa-core' ),
				$decoded
			);
		}

		return $decoded;
	}
}
