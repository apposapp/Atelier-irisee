<?php
/**
 * Configurator endpoints on WooCommerce's lightweight ?wc-ajax= endpoint (cart session loaded).
 *
 * Read endpoints only return public catalog data, so they work on fully cached pages.
 * The add-to-cart endpoint is protected with a nonce.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Ajax {

	const NONCE = 'aimp_configurator';

	public static function init() {
		foreach ( array( 'patterns', 'sizes', 'fabrics', 'notions', 'add_to_cart' ) as $action ) {
			add_action( 'wc_ajax_aimp_' . $action, array( __CLASS__, $action ) );
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only public catalog endpoints.

	private static function int_param( $name ) {
		return isset( $_POST[ $name ] ) ? absint( wp_unslash( $_POST[ $name ] ) ) : 0;
	}

	private static function not_found() {
		wp_send_json_error( array( 'errors' => array( __( 'This item is not available.', 'atelier-irisee-master-plugin' ) ) ), 404 );
	}

	public static function patterns() {
		wp_send_json_success( AIMP_Catalog::get_patterns( self::int_param( 'category' ), max( 1, self::int_param( 'page' ) ) ) );
	}

	public static function sizes() {
		$data = AIMP_Catalog::get_sizes( self::int_param( 'pattern' ) );
		if ( null === $data ) {
			self::not_found();
		}
		wp_send_json_success( $data );
	}

	public static function fabrics() {
		$data = AIMP_Catalog::get_fabrics( self::int_param( 'variation' ), max( 1, self::int_param( 'page' ) ) );
		if ( null === $data ) {
			self::not_found();
		}
		wp_send_json_success( $data );
	}

	public static function notions() {
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		if ( ! in_array( $type, array( 'buttons', 'zips' ), true ) ) {
			self::not_found();
		}
		$data = AIMP_Catalog::get_notions( self::int_param( 'variation' ), $type, max( 1, self::int_param( 'page' ) ) );
		if ( null === $data ) {
			self::not_found();
		}
		wp_send_json_success( $data );
	}

	// phpcs:enable

	public static function add_to_cart() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ) ) ), 403 );
		}

		$result = AIMP_Cart::add_group(
			self::int_param( 'variation' ),
			self::int_param( 'fabric' ),
			self::int_param( 'button' ),
			self::int_param( 'zip' )
		);

		// WooCommerce may have queued its own notices while adding; we report errors in the configurator instead.
		$wc_errors = array_map(
			function ( $notice ) {
				return wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : $notice );
			},
			wc_get_notices( 'error' )
		);
		wc_clear_notices();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'errors' => array_values( array_unique( array_merge( $result->get_error_messages(), $wc_errors ) ) ) ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Your pattern set has been added to the cart.', 'atelier-irisee-master-plugin' ),
				'cart_url'   => wc_get_cart_url(),
				'cart_count' => WC()->cart->get_cart_contents_count(),
			)
		);
	}
}
