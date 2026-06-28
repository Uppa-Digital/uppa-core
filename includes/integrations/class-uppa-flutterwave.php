<?php
/**
 * Flutterwave payment gateway integration.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Uppa_Flutterwave
 */
class Uppa_Flutterwave {

	/**
	 * Flutterwave public key.
	 *
	 * @var string
	 */
	private string $public_key;

	/**
	 * Flutterwave secret key.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Constructor.
	 *
	 * @param string $public_key Flutterwave public key.
	 * @param string $secret_key Flutterwave secret key.
	 */
	public function __construct( string $public_key = '', string $secret_key = '' ) {
		$this->public_key = $public_key;
		$this->secret_key = $secret_key;
	}

	/**
	 * Initialise a payment and return the hosted-payment URL.
	 *
	 * @param array<string, mixed> $payload Transaction payload (customer, amount, currency, redirect_url, etc.).
	 * @return array<string, mixed> Flutterwave API response payload.
	 */
	public function initialize_payment( array $payload ): array {
		// TODO: implement Flutterwave payment initialisation via WP HTTP API.
		return [];
	}

	/**
	 * Verify a transaction by its transaction ID.
	 *
	 * @param string $transaction_id Flutterwave transaction ID.
	 * @return array<string, mixed> Flutterwave API verification payload.
	 */
	public function verify_transaction( string $transaction_id ): array {
		// TODO: implement Flutterwave transaction verification via WP HTTP API.
		return [];
	}
}
