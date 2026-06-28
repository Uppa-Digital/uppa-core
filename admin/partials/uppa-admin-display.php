<?php
/**
 * Dashboard page partial.
 *
 * Rendered by UPPA_Admin::render_dashboard_page(). All variables are
 * resolved here rather than in the controller to keep the partial self-contained.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

// --- Theme info ---
$theme             = wp_get_theme();
$theme_name        = $theme->get( 'Name' );
$theme_version     = $theme->get( 'Version' );
$parent            = $theme->parent();
$is_uppa_base      = ( 'UPPA Base' === $theme_name )
	|| ( $parent instanceof WP_Theme && 'UPPA Base' === $parent->get( 'Name' ) );

// --- CPTs ---
$registered_cpts   = UPPA_CPT_Manager::get_registered();

// --- ACF ---
$acf_active        = UPPA_ACF_Bridge::is_acf_active();
?>
<div class="wrap uppa-admin-wrap">

	<h1 class="uppa-page-title">
		<span class="dashicons dashicons-layout"></span>
		<?php esc_html_e( 'UPPA Core', 'uppa-core' ); ?>
		<span class="uppa-version-badge">v<?php echo esc_html( UPPA_CORE_VERSION ); ?></span>
	</h1>

	<div class="uppa-dashboard-grid">

		<!-- ── Theme Status ─────────────────────────────────────── -->
		<div class="uppa-card">
			<h2 class="uppa-card__title"><?php esc_html_e( 'Active Theme', 'uppa-core' ); ?></h2>
			<table class="uppa-info-table">
				<tr>
					<th><?php esc_html_e( 'Name', 'uppa-core' ); ?></th>
					<td><?php echo esc_html( $theme_name ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Version', 'uppa-core' ); ?></th>
					<td><?php echo esc_html( $theme_version ); ?></td>
				</tr>
				<?php if ( $parent instanceof WP_Theme ) : ?>
				<tr>
					<th><?php esc_html_e( 'Parent Theme', 'uppa-core' ); ?></th>
					<td><?php echo esc_html( $parent->get( 'Name' ) ); ?></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'UPPA Base', 'uppa-core' ); ?></th>
					<td>
						<?php if ( $is_uppa_base ) : ?>
							<span class="uppa-badge uppa-badge--ok">&#10003; <?php esc_html_e( 'Active', 'uppa-core' ); ?></span>
						<?php else : ?>
							<span class="uppa-badge uppa-badge--warn">&#9888; <?php esc_html_e( 'Not detected', 'uppa-core' ); ?></span>
							<p class="description"><?php esc_html_e( 'Some features require the UPPA Base parent theme.', 'uppa-core' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- ── ACF Status ───────────────────────────────────────── -->
		<div class="uppa-card">
			<h2 class="uppa-card__title"><?php esc_html_e( 'Advanced Custom Fields', 'uppa-core' ); ?></h2>
			<p>
				<?php if ( $acf_active ) : ?>
					<span class="uppa-badge uppa-badge--ok">&#10003; <?php esc_html_e( 'ACF / ACF Pro is active', 'uppa-core' ); ?></span>
				<?php else : ?>
					<span class="uppa-badge uppa-badge--neutral">&#8722; <?php esc_html_e( 'ACF not detected — fallbacks are active', 'uppa-core' ); ?></span>
				<?php endif; ?>
			</p>
		</div>

		<!-- ── Registered CPTs ──────────────────────────────────── -->
		<div class="uppa-card uppa-card--wide">
			<h2 class="uppa-card__title"><?php esc_html_e( 'Registered Custom Post Types', 'uppa-core' ); ?></h2>

			<?php if ( empty( $registered_cpts ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'No CPTs have been registered via UPPA_CPT_Manager yet.', 'uppa-core' ); ?>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post Type', 'uppa-core' ); ?></th>
							<th><?php esc_html_e( 'Label', 'uppa-core' ); ?></th>
							<th><?php esc_html_e( 'Public', 'uppa-core' ); ?></th>
							<th><?php esc_html_e( 'Archive', 'uppa-core' ); ?></th>
							<th><?php esc_html_e( 'REST', 'uppa-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $registered_cpts as $slug => $args ) : ?>
						<tr>
							<td><code><?php echo esc_html( $slug ); ?></code></td>
							<td><?php echo esc_html( $args['labels']['name'] ?? $slug ); ?></td>
							<td><?php echo ( $args['public'] ?? false ) ? '&#10003;' : '&#8722;'; ?></td>
							<td><?php echo ( $args['has_archive'] ?? false ) ? '&#10003;' : '&#8722;'; ?></td>
							<td><?php echo ( $args['show_in_rest'] ?? false ) ? '&#10003;' : '&#8722;'; ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- ── Plugin Info ──────────────────────────────────────── -->
		<div class="uppa-card">
			<h2 class="uppa-card__title"><?php esc_html_e( 'Plugin Info', 'uppa-core' ); ?></h2>
			<table class="uppa-info-table">
				<tr>
					<th><?php esc_html_e( 'Version', 'uppa-core' ); ?></th>
					<td><?php echo esc_html( UPPA_CORE_VERSION ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'PHP', 'uppa-core' ); ?></th>
					<td><?php echo esc_html( PHP_VERSION ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WordPress', 'uppa-core' ); ?></th>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
			</table>
		</div>

	</div><!-- .uppa-dashboard-grid -->
</div><!-- .uppa-admin-wrap -->
