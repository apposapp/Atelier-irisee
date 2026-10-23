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
		foreach ( array( 'patterns', 'sizes', 'fabrics', 'linings', 'notions', 'add_to_cart' ) as $action ) {
			add_action( 'wc_ajax_aimp_' . $action, array( __CLASS__, $action ) );
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only public catalog endpoints.

	private static function int_param( $name ) {
		return isset( $_POST[ $name ] ) ? absint( wp_unslash( $_POST[ $name ] ) ) : 0;
	}

	/**
	 * A comma list of IDs ("12,15"), for filters where several can be chosen.
	 *
	 * @param string $name Field.
	 * @return int[]
	 */
	private static function int_list_param( $name ) {
		$value = isset( $_POST[ $name ] ) ? substr( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ), 0, 200 ) : '';
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $value ) ) ) ) ), 0, 20 );
	}

	private static function not_found() {
		wp_send_json_error( array( 'errors' => array( __( 'This item is not available.', 'atelier-irisee-master-plugin' ) ) ), 404 );
	}

	/**
	 * A stored answer for the same question in the same language, made with the current catalog (AIMP_Shop's cache
	 * version changes with every product, stock or settings change). Kept for 12 hours.
	 *
	 * @param string   $what  Endpoint.
	 * @param array    $args  Its arguments.
	 * @param callable $build Makes the answer (null = not found, not stored).
	 * @return mixed
	 */
	private static function cached( $what, $args, $build ) {
		$key  = 'aimp_cfg_' . md5( $what . wp_json_encode( $args ) . AIMP_I18n::current() . AIMP_Shop::cache_version() );
		$data = get_transient( $key );
		if ( false === $data ) {
			$data = call_user_func( $build );
			if ( null !== $data ) {
				set_transient( $key, $data, 12 * HOUR_IN_SECONDS );
			}
		}
		return $data;
	}

	public static function patterns() {
		$category = self::int_list_param( 'category' );
		$page     = max( 1, self::int_param( 'page' ) );
		wp_send_json_success(
			self::cached(
				'patterns',
				array( $category, $page ),
				function () use ( $category, $page ) {
					return AIMP_Catalog::get_patterns( $category, $page );
				}
			)
		);
	}

	public static function sizes() {
		$id   = self::int_param( 'pattern' );
		$data = self::cached(
			'sizes',
			array( $id ),
			function () use ( $id ) {
				return AIMP_Catalog::get_sizes( $id );
			}
		);
		if ( null === $data ) {
			self::not_found();
		}
		wp_send_json_success( $data );
	}

	public static function fabrics( $lining = false ) {
		$data = AIMP_Catalog::get_fabrics(
			self::int_param( 'variation' ),
			array(
				'page'     => max( 1, self::int_param( 'page' ) ),
				'category' => self::int_list_param( 'category' ),
				'search'   => isset( $_POST['search'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['search'] ) ), 0, 100 ) : '',
				'in_stock' => 1 === self::int_param( 'in_stock' ),
				'sort'     => isset( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : 'recommended',
			),
			true === $lining
		);
		if ( null === $data ) {
			self::not_found();
		}
		wp_send_json_success( $data );
	}

	// The lining step: every fabric that can also be used as lining.
	public static function linings() {
		self::fabrics( true );
	}

	public static function notions() {
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		if ( ! array_key_exists( $type, AIMP_Catalog::notion_types() ) ) {
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

		// One request parameter per haberdashery role: button, zip, ribbon, bias.
		$notions = array();
		foreach ( AIMP_Catalog::notion_types() as $type => $info ) {
			$notions[ $type ] = self::int_param( $info['role'] );
		}

		$result = AIMP_Cart::add_group(
			self::int_param( 'variation' ),
			self::int_param( 'fabric' ),
			$notions,
			self::int_param( 'lining' )
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
