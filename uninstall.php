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
delete_site_transient( 'aimp_github_release' );
