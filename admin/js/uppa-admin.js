/**
 * UPPA Core — admin script.
 *
 * Handles UX enhancements on UPPA Core admin pages:
 *   - Password show/hide toggle on secret key fields.
 *
 * @package uppa-core
 */

( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Password show/hide toggle
	// -------------------------------------------------------------------------

	/**
	 * Add a show/hide toggle button beside every password input on the page.
	 */
	function initPasswordToggles() {
		document.querySelectorAll( 'input[type="password"]' ).forEach( function ( input ) {
			const btn = document.createElement( 'button' );
			btn.type        = 'button';
			btn.className   = 'uppa-toggle-password button button-secondary';
			btn.textContent = 'Show';
			btn.setAttribute( 'aria-label', 'Show password' );
			btn.style.marginLeft = '6px';

			btn.addEventListener( 'click', function () {
				const isHidden = input.type === 'password';
				input.type      = isHidden ? 'text' : 'password';
				btn.textContent = isHidden ? 'Hide' : 'Show';
				btn.setAttribute( 'aria-label', isHidden ? 'Hide password' : 'Show password' );
			} );

			input.insertAdjacentElement( 'afterend', btn );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initPasswordToggles );
	} else {
		initPasswordToggles();
	}

} )();
