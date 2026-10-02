<?php
/**
 * Runs when the plugin is deleted from the Plugins page.
 *
 * Product meta (sizes, measurements, material requirements, zip lengths) is shop data and is kept,
 * so reinstalling the plugin does not lose any product configuration.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'aimp_settings' );

// Customers' favorites (guests' favorites live in their own browser cookie).
delete_metadata( 'user', 0, '_aimp_favorites', '', true );
delete_site_transient( 'aimp_github_release' );
