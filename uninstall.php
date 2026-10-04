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

// Login & registration module: settings, lockouts, account status, verification, social links,
// profile pictures and extra registration fields (user meta starting with "aimp_").
// Fields that were saved as WooCommerce billing/shipping fields belong to WooCommerce and stay.
delete_option( 'aimp_login' );
delete_option( 'aimp_el_lockouts' );
delete_option( 'aimp_el_rewrite_version' );

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-off cleanup on uninstall.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'aimp_' ) . '%' ) );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_aimp_el_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_aimp_el_' ) . '%'
	)
);
// Gift cards: settings and the cards themselves (codes in sent emails stop working).
delete_option( 'aimp_giftcards' );
$aimp_gift_cards = get_posts(
	array(
		'post_type'      => 'aimp_gift_card',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $aimp_gift_cards as $aimp_gift_card ) {
	wp_delete_post( $aimp_gift_card, true );
}
wp_unschedule_hook( 'aimp_gc_send' );

// Footer, mosaic and the newsletter subscribers (their addresses are removed with the plugin).
delete_option( 'aimp_footer' );
delete_option( 'aimp_mosaic' );
$aimp_subscribers = get_posts(
	array(
		'post_type'      => 'aimp_subscriber',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $aimp_subscribers as $aimp_subscriber ) {
	wp_delete_post( $aimp_subscriber, true );
}

// Shop pages and product pages: cached filter options and "fits these patterns" lists.
delete_option( 'aimp_shop_cache_version' );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_aimp_shop_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_aimp_shop_' ) . '%'
	)
);
// phpcs:enable
