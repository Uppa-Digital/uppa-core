<?php
/**
 * Shortcode registration for UPPA Core.
 *
 * Provides the [uppa_pay] shortcode that renders an inline payment form
 * wired to either Paystack or Flutterwave via the front-end AJAX layer.
 *
 * Usage:
 *
 *   [uppa_pay gateway="paystack" amount="5000" currency="NGN" label="Pay ₦5,000"]
 *   [uppa_pay gateway="flutterwave" redirect_url="https://site.com/thank-you/"]
 *
 * When amount is omitted or 0 an "Amount" field is shown to the visitor.
 * When the logged-in user's email is available it pre-fills the email field.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Shortcodes
 */
class UPPA_Shortcodes {

	/**
	 * Register all shortcodes provided by UPPA Core.
	 *
	 * Call once from UPPA_Core after dependencies are loaded. WordPress
	 * accepts shortcode registration at any point before the shortcode tag
	 * is encountered in post content, so calling this from plugins_loaded
	 * is safe.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'uppa_pay',    [ __CLASS__, 'render_pay_form'    ] );
		add_shortcode( 'uppa_verify', [ __CLASS__, 'render_verify_area' ] );
	}

	/**
	 * Render the [uppa_pay] payment form.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output for the payment form.
	 */
	public static function render_pay_form( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'gateway'      => 'paystack',
				'amount'       => '0',
				'currency'     => 'NGN',
				'label'        => __( 'Pay Now', 'uppa-core' ),
				'class'        => '',
				'redirect_url' => '',
				'email'        => '',
			],
			$atts,
			'uppa_pay'
		);

		$gateway   = in_array( $atts['gateway'], [ 'paystack', 'flutterwave' ], true )
			? $atts['gateway']
			: 'paystack';
		$amount    = absint( $atts['amount'] );
		$currency  = strtoupper( sanitize_key( $atts['currency'] ) ) ?: 'NGN';
		$label     = sanitize_text_field( $atts['label'] );
		$css_class = $atts['class'] ? implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $atts['class'] ) ) ) : '';
		$redirect  = $atts['redirect_url'] ? esc_url( $atts['redirect_url'] ) : esc_url( home_url( '/' ) );

		// Pre-fill email: attribute > logged-in user > empty.
		$prefill_email = '';
		if ( is_email( $atts['email'] ) ) {
			$prefill_email = $atts['email'];
		} elseif ( is_user_logged_in() ) {
			$prefill_email = wp_get_current_user()->user_email;
		}

		$uid = esc_attr( wp_unique_id( 'uppa-pay-' ) );

		ob_start();
		?>
		<form
			class="uppa-pay-form<?php echo $css_class ? ' ' . esc_attr( $css_class ) : ''; ?>"
			data-gateway="<?php echo esc_attr( $gateway ); ?>"
			data-currency="<?php echo esc_attr( $currency ); ?>"
			data-redirect-url="<?php echo esc_url( $redirect ); ?>"
			novalidate
		>
			<div class="uppa-pay-form__field">
				<label for="<?php echo $uid; ?>-email"><?php esc_html_e( 'Email address', 'uppa-core' ); ?></label>
				<input
					type="email"
					id="<?php echo $uid; ?>-email"
					name="email"
					class="uppa-pay-form__email"
					value="<?php echo esc_attr( $prefill_email ); ?>"
					required
					autocomplete="email"
				>
			</div>

			<?php if ( 0 === $amount ) : ?>
			<div class="uppa-pay-form__field">
				<label for="<?php echo $uid; ?>-amount">
					<?php
					/* translators: %s: currency code e.g. NGN */
					printf( esc_html__( 'Amount (%s)', 'uppa-core' ), esc_html( $currency ) );
					?>
				</label>
				<input
					type="number"
					id="<?php echo $uid; ?>-amount"
					name="amount"
					class="uppa-pay-form__amount"
					min="1"
					step="1"
					required
				>
			</div>
			<?php else : ?>
			<input type="hidden" name="amount" class="uppa-pay-form__amount" value="<?php echo esc_attr( $amount ); ?>">
			<?php endif; ?>

			<div class="uppa-pay-form__actions">
				<button type="submit" class="uppa-pay-form__submit">
					<?php echo esc_html( $label ); ?>
				</button>
				<span class="uppa-pay-form__spinner" hidden aria-hidden="true"></span>
			</div>

			<p class="uppa-pay-form__error" hidden role="alert"></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the [uppa_verify] payment result area.
	 *
	 * Place this shortcode on the page your gateway redirects to after payment.
	 * The JS layer detects the gateway callback URL parameters and fires
	 * verification automatically; this shortcode provides the HTML container
	 * that displays the result without a page reload.
	 *
	 * The `uppa:payment-verified` CustomEvent is dispatched on window after
	 * verification completes so themes can add additional behaviour:
	 *
	 *   window.addEventListener('uppa:payment-verified', function (e) {
	 *       console.log(e.detail); // { success: true, data: { ... } }
	 *   });
	 *
	 * Usage:
	 *
	 *   [uppa_verify
	 *     success_message="Thank you! Your payment was successful."
	 *     failure_message="Payment could not be confirmed. Please contact us."
	 *   ]
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output for the verification result container.
	 */
	public static function render_verify_area( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'success_message' => __( 'Your payment was successful. Thank you!', 'uppa-core' ),
				'failure_message' => __( 'We could not confirm your payment. Please contact us if you were charged.', 'uppa-core' ),
				'pending_message' => __( 'Verifying your payment…', 'uppa-core' ),
			],
			$atts,
			'uppa_verify'
		);

		ob_start();
		?>
		<div
			class="uppa-verify-area"
			data-success="<?php echo esc_attr( $atts['success_message'] ); ?>"
			data-failure="<?php echo esc_attr( $atts['failure_message'] ); ?>"
			role="status"
			aria-live="polite"
		>
			<p class="uppa-verify-area__pending">
				<span class="uppa-pay-form__spinner" aria-hidden="true"></span>
				<?php echo esc_html( $atts['pending_message'] ); ?>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
