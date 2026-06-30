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

	// -------------------------------------------------------------------------
	// AJAX helpers
	// -------------------------------------------------------------------------

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

	// -------------------------------------------------------------------------
	// [uppa_pay] shortcode form handler
	// -------------------------------------------------------------------------

	/**
	 * Set the loading state on a payment form.
	 *
	 * @param {HTMLFormElement} form    The form element.
	 * @param {boolean}         loading True to show spinner, false to restore.
	 */
	function setLoading( form, loading ) {
		const btn     = form.querySelector( '.uppa-pay-form__submit' );
		const spinner = form.querySelector( '.uppa-pay-form__spinner' );
		if ( btn ) {
			btn.disabled = loading;
		}
		if ( spinner ) {
			if ( loading ) {
				spinner.removeAttribute( 'hidden' );
			} else {
				spinner.setAttribute( 'hidden', '' );
			}
		}
	}

	/**
	 * Show an error message inside a payment form.
	 *
	 * @param {HTMLFormElement} form    The form element.
	 * @param {string}          message Error text to display.
	 */
	function showError( form, message ) {
		const el = form.querySelector( '.uppa-pay-form__error' );
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.removeAttribute( 'hidden' );
	}

	/**
	 * Clear any visible error inside a payment form.
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function clearError( form ) {
		const el = form.querySelector( '.uppa-pay-form__error' );
		if ( el ) {
			el.setAttribute( 'hidden', '' );
			el.textContent = '';
		}
	}

	/**
	 * Handle submission of an [uppa_pay] form.
	 *
	 * Reads gateway, currency, and amount from the form, calls the AJAX
	 * initialisation endpoint, then redirects to the gateway's hosted
	 * payment page. For Paystack the response contains authorization_url;
	 * for Flutterwave it contains payment_link.
	 *
	 * @param {SubmitEvent} e
	 */
	function handlePayFormSubmit( e ) {
		e.preventDefault();

		const form        = /** @type {HTMLFormElement} */ ( e.currentTarget );
		const gateway     = form.dataset.gateway     || 'paystack';
		const currency    = form.dataset.currency    || data.currency || 'NGN';
		const redirectUrl = form.dataset.redirectUrl || data.siteUrl  || '/';

		const email  = ( form.querySelector( '.uppa-pay-form__email'  ) || {} ).value || '';
		const amount = parseInt( ( form.querySelector( '.uppa-pay-form__amount' ) || {} ).value || '0', 10 );

		clearError( form );

		if ( ! email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			showError( form, 'Please enter a valid email address.' );
			return;
		}

		if ( ! amount || amount < 1 ) {
			showError( form, 'Please enter a valid amount.' );
			return;
		}

		setLoading( form, true );

		const args = { email, currency };

		if ( 'paystack' === gateway ) {
			// Paystack expects amount in kobo (smallest unit). The shortcode
			// amount attribute and the user-entered value are always in the
			// major unit (naira), so multiply here.
			args.amount = amount * 100;
		} else {
			// Flutterwave accepts the major currency unit directly.
			args.amount       = amount;
			args.redirect_url = redirectUrl;
			args.customer     = { email, name: '', phonenumber: '' };
		}

		initPayment( gateway, args )
			.then( function ( response ) {
				if ( ! response.success ) {
					throw new Error(
						( response.data && response.data.message )
							? response.data.message
							: 'Payment initialisation failed. Please try again.'
					);
				}

				const dest = 'paystack' === gateway
					? response.data.authorization_url
					: response.data.payment_link;

				if ( ! dest ) {
					throw new Error( 'No payment URL returned by the gateway.' );
				}

				window.location.href = dest;
			} )
			.catch( function ( err ) {
				setLoading( form, false );
				showError( form, err.message || 'An unexpected error occurred.' );
			} );
	}

	/**
	 * Attach submit handlers to every [uppa_pay] form present in the DOM.
	 */
	function initPayForms() {
		document.querySelectorAll( '.uppa-pay-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', handlePayFormSubmit );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initPayForms );
	} else {
		initPayForms();
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	// Expose helpers on the uppaCore namespace so theme templates and
	// child-plugin scripts can call them without duplicating the AJAX plumbing.
	window.uppaCore        = window.uppaCore || {};
	window.uppaCore.init   = initPayment;
	window.uppaCore.verify = verifyPayment;

} )( typeof uppaCore !== 'undefined' ? uppaCore : null );
