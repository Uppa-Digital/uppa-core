/**
 * UPPA Core — public script.
 *
 * The `uppaCore` object is injected by wp_localize_script() before this file
 * executes. Available properties:
 *
 *   uppaCore.ajaxUrl              — WordPress admin-ajax.php URL
 *   uppaCore.nonce                — Request nonce (action: uppa_core_nonce)
 *   uppaCore.paystackPublicKey    — Paystack public key for the inline SDK
 *   uppaCore.flutterwavePublicKey — Flutterwave public key for the inline SDK
 *   uppaCore.currency             — Default currency code ('NGN')
 *   uppaCore.siteUrl              — Site home URL
 *
 * @package uppa-core
 */

( function ( data ) {
	'use strict';

	if ( ! data ) {
		return;
	}

	/**
	 * Send a POST request to the UPPA Core AJAX endpoint.
	 *
	 * @param {string} action  WordPress AJAX action name.
	 * @param {Object} payload Fields to include in the POST body.
	 * @returns {Promise<Object>} Resolved with the parsed JSON response body.
	 */
	function request( action, payload ) {
		const body = new URLSearchParams( {
			action,
			nonce: data.nonce,
			...payload,
		} );

		return fetch( data.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		} ).then( function ( res ) {
			return res.json();
		} );
	}

	/**
	 * Initialise a payment via the chosen gateway.
	 *
	 * @param {string} gateway  'paystack' or 'flutterwave'.
	 * @param {Object} args     Gateway-specific fields (email, amount, etc.).
	 * @returns {Promise<Object>}
	 */
	function initPayment( gateway, args ) {
		return request( 'uppa_init_payment', { gateway, ...args } );
	}

	/**
	 * Verify a completed payment via the chosen gateway.
	 *
	 * @param {string} gateway        'paystack' or 'flutterwave'.
	 * @param {Object} identifiers    { reference } for Paystack or
	 *                                { transaction_id } for Flutterwave.
	 * @returns {Promise<Object>}
	 */
	function verifyPayment( gateway, identifiers ) {
		return request( 'uppa_verify_payment', { gateway, ...identifiers } );
	}

	// Expose a minimal public API on the uppaCore namespace so theme templates
	// and child-plugin scripts can call these helpers without duplicating the
	// AJAX plumbing.
	window.uppaCore       = window.uppaCore || {};
	window.uppaCore.init   = initPayment;
	window.uppaCore.verify = verifyPayment;

} )( typeof uppaCore !== 'undefined' ? uppaCore : null );
