<?php
/**
 * Settings page partial.
 *
 * Rendered by UPPA_Admin::render_settings_page(). Uses the WordPress Settings
 * API exclusively — do not add custom $_POST handling here.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap uppa-admin-wrap">

	<h1 class="uppa-page-title">
		<span class="dashicons dashicons-layout"></span>
		<?php esc_html_e( 'UPPA Core Settings', 'uppa-core' ); ?>
	</h1>

	<?php settings_errors( 'uppa_core_settings' ); ?>

	<form method="post" action="options.php" novalidate>
		<?php
		// Outputs nonce, action, and option_page hidden fields.
		// Nonce verification is handled by the Settings API itself.
		settings_fields( 'uppa_core_settings_group' );

		// Renders all registered sections and fields for this page slug.
		do_settings_sections( 'uppa-core-settings' );

		submit_button( __( 'Save Settings', 'uppa-core' ) );
		?>
	</form>

</div><!-- .uppa-admin-wrap -->
