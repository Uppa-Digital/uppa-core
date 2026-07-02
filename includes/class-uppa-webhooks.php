<?php
/**
 * REST API webhook endpoints for Paystack and Flutterwave.
 *
 * Registers two endpoints under the uppa-core/v1 namespace:
 *
 *   POST /wp-json/uppa-core/v1/webhooks/paystack
 *   POST /wp-json/uppa-core/v1/webhooks/flutterwave
 *
 * Both endpoints verify the gateway's signature header before processing the
 * payload, then fire a WordPress action hook so themes and plugins can react
 * without modifying this file.
 *
 * Configure each gateway's webhook URL in their dashboard:
 *   Paystack:     Settings → API Keys & Webhooks → Webhook URL
 *   Flutterwave:  Settings → Webhooks
 *
 * Listen for confirmed payments in your theme or feature plugin:
 *
 *   add_action( 'uppa_paystack_webhook', function( array $event ) {
 *       if ( 'charge.success' === $event['event'] ) {
 *           $reference = $event['data']['reference'];
 *           // mark order as paid…
 *       }
 *   } );
 *
 *   add_action( 'uppa_flutterwave_webhook', function( array $event ) {
 *       if ( 'charge.completed' === $event['event'] && 'successful' === $event['data']['status'] ) {
 *           $tx_ref = $event['data']['tx_ref'];
 *           // mark order as paid…
 *       }
 *   } );
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Webhooks
 */
class UPPA_Webhooks {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'uppa-core/v1';

	/**
	 * Register REST routes.
	 *
	 * Called on the rest_api_init action.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/webhooks/paystack',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_paystack' ],
				'permission_callback' => '__return_true', // Auth via signature header.
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/webhooks/flutterwave',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_flutterwave' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	// -------------------------------------------------------------------------
	// Paystack webhook
	// -------------------------------------------------------------------------

	/**
	 * Handle a Paystack webhook POST.
	 *
	 * Paystack signs each request with an HMAC-SHA512 hash of the raw JSON body
	 * using the secret key. The hash is sent in the X-Paystack-Signature header.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_paystack( WP_REST_Request $request ): WP_REST_Response {
		$secret = UPPA_Paystack::get_instance()->get_secret_key_for_webhook();

		if ( '' === $secret ) {
			return new WP_REST_Response( [ 'error' => 'Gateway not configured.' ], 503 );
		}

		$raw_body  = $request->get_body();
		$signature = $request->get_header( 'x-paystack-signature' );

		if ( ! hash_equals( hash_hmac( 'sha512', $raw_body, $secret ), (string) $signature ) ) {
			return new WP_REST_Response( [ 'error' => 'Invalid signature.' ], 401 );
		}

		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) ) {
			return new WP_REST_Response( [ 'error' => 'Invalid JSON payload.' ], 400 );
		}

		/**
		 * Fires after a verified Paystack webhook event is received.
		 *
		 * @param array<string, mixed> $event Decoded Paystack event payload.
		 *                                    Key 'event' holds the event type string
		 *                                    (e.g. 'charge.success', 'transfer.success').
		 *                                    Key 'data' holds the event data object.
		 */
		do_action( 'uppa_paystack_webhook', $event );

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// Flutterwave webhook
	// -------------------------------------------------------------------------

	/**
	 * Handle a Flutterwave webhook POST.
	 *
	 * Flutterwave sends a verif-hash header that must match the secret hash
	 * configured in the Flutterwave dashboard (Settings → Webhooks → Secret Hash).
	 * This is different from the API secret key — it is a separate value you
	 * choose and store in the plugin settings.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_flutterwave( WP_REST_Request $request ): WP_REST_Response {
		$secret = UPPA_Flutterwave::get_instance()->get_webhook_hash_for_webhook();

		if ( '' === $secret ) {
			return new WP_REST_Response( [ 'error' => 'Gateway not configured.' ], 503 );
		}

		$received_hash = $request->get_header( 'verif-hash' );

		if ( ! hash_equals( $secret, (string) $received_hash ) ) {
			return new WP_REST_Response( [ 'error' => 'Invalid signature.' ], 401 );
		}

		$raw_body = $request->get_body();
		$event    = json_decode( $raw_body, true );
		if ( ! is_array( $event ) ) {
			return new WP_REST_Response( [ 'error' => 'Invalid JSON payload.' ], 400 );
		}

		/**
		 * Fires after a verified Flutterwave webhook event is received.
		 *
		 * @param array<string, mixed> $event Decoded Flutterwave event payload.
		 *                                    Key 'event' holds the event type string
		 *                                    (e.g. 'charge.completed').
		 *                                    Key 'data' holds the event data object.
		 */
		do_action( 'uppa_flutterwave_webhook', $event );

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}
}
