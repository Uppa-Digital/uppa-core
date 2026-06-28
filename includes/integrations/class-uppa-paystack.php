<?php
/**
 * Paystack payment gateway integration.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Uppa_Paystack
 */
class Uppa_Paystack {

	/**
	 * Paystack public key.
	 *
	 * @var string
	 */
	private string $public_key;

	/**
	 * Paystack secret key.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Constructor.
	 *
	 * @param string $public_key Paystack public key.
	 * @param string $secret_key Paystack secret key.
	 */
	public function __construct( string $public_key = '', string $secret_key = '' ) {
		$this->public_key = $public_key;
		$this->secret_key = $secret_key;
	}

	/**
	 * Initialise a transaction and return the authorisation URL.
	 *
	 * @param string $email  Customer email address.
	 * @param int    $amount Amount in the smallest currency unit (e.g. kobo).
	 * @param array<string, mixed> $meta   Additional transaction metadata.
	 * @return array<string, mixed> Paystack API response payload.
	 */
	public function initialize_transaction( string $email, int $amount, array $meta = [] ): array {
		// TODO: implement Paystack transaction initialisation via WP HTTP API.
		return [];
	}

	/**
	 * Verify a completed transaction by reference.
	 *
	 * @param string $reference Paystack transaction reference.
	 * @return array<string, mixed> Paystack API verification payload.
	 */
	public function verify_transaction( string $reference ): array {
		// TODO: implement Paystack transaction verification via WP HTTP API.
		return [];
	}
}
