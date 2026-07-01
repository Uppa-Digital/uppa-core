/**
 * UPPA Core — admin script.
 *
 * Runs on all admin pages:
 *   - Intercepts the WordPress dismiss click on #uppa-theme-notice and
 *     persists the dismissal server-side via AJAX so the notice never
 *     re-appears for this user.
 *
 * Runs only on UPPA Core admin pages (window.uppaCoreAdminPage === true):
 *   - Password show/hide toggle on secret key fields.
 *
 * @package uppa-core
 */

( function ( cfg ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Notice dismiss (all admin pages)
	// -------------------------------------------------------------------------

	function initNoticeDismiss() {
		const notice = document.getElementById( 'uppa-theme-notice' );
		if ( ! notice ) {
			return;
		}

		const nonce = notice.dataset.uppaDismissNonce;
		if ( ! nonce ) {
			return;
		}

		// WordPress fires 'click' on the .notice-dismiss button and then removes
		// the notice from the DOM. We piggyback on that same event.
		notice.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'notice-dismiss' ) ) {
				return;
			}

			fetch( ( cfg && cfg.ajaxUrl ) || '/wp-admin/admin-ajax.php', {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    new URLSearchParams( {
					action: 'uppa_dismiss_theme_notice',
					nonce:  nonce,
				} ).toString(),
			} );
			// Fire-and-forget — the notice is already gone from the DOM.
		} );
	}

	// -------------------------------------------------------------------------
	// Password show/hide toggle (plugin pages only)
	// -------------------------------------------------------------------------

	function initPasswordToggles() {
		if ( ! window.uppaCoreAdminPage ) {
			return;
		}

		document.querySelectorAll( 'input[type="password"]' ).forEach( function ( input ) {
			const btn = document.createElement( 'button' );
			btn.type        = 'button';
			btn.className   = 'uppa-toggle-password button button-secondary';
			btn.textContent = 'Show';
			btn.setAttribute( 'aria-label', 'Show password' );
			btn.style.marginLeft = '6px';

			btn.addEventListener( 'click', function () {
				const reveal    = input.type === 'password';
				input.type      = reveal ? 'text' : 'password';
				btn.textContent = reveal ? 'Hide' : 'Show';
				btn.setAttribute( 'aria-label', reveal ? 'Hide password' : 'Show password' );
			} );

			input.insertAdjacentElement( 'afterend', btn );
		} );
	}

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	function init() {
		initNoticeDismiss();
		initPasswordToggles();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )( typeof uppaCoreAdmin !== 'undefined' ? uppaCoreAdmin : null );
