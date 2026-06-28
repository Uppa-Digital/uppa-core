<?php
/**
 * Fired when the plugin is uninstalled (deleted via the WP admin).
 *
 * @package uppa-core
 */

// Only run when WordPress itself triggers uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// TODO: remove plugin options, custom tables, and any stored data here.
