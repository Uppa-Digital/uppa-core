<?php
/**
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * Removes all options written by UPPA Core. No custom database tables exist
 * at v1.0, so no DROP TABLE statements are needed.
 *
 * @package uppa-core
 */

// WordPress sets this constant before loading uninstall.php.
// Direct file access must be blocked — exit immediately if it is absent.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove the version option written on activation.
delete_option( 'uppa_core_version' );

// Remove any remaining options whose names begin with "uppa_".
// We query the options table directly because there is no WordPress function
// for prefix-based bulk deletion.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$uppa_option_names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'uppa_' ) . '%'
	)
);

foreach ( $uppa_option_names as $uppa_option_name ) {
	delete_option( $uppa_option_name );
}
