<?php
/**
 * Saved sewing project kits: logged-in customers save a kit composed in the configurator and finish it
 * later. The kits are listed on the favorites page; "Continue" opens the configurator with ?aimp_kit=ID.
 *
 * Stored in user meta _aimp_saved_kits: [ id, variation, fabric, notions: [ type => product ID ], date ].
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Saved_Kits {

	const META = '_aimp_saved_kits';
	const MAX  = 20;

	public static function init() {
		foreach ( array( 'save_kit', 'load_kit', 'delete_kit' ) as $action ) {
			add_action( 'wc_ajax_aimp_' . $action, array( __CLASS__, $action ) );
		}
	}

	/**
	 * @param int $user_id User.
	 * @return array[]
	 */
	public static function kits( $user_id ) {
		$kits = get_user_meta( $user_id, self::META, true );
		return is_array( $kits ) ? array_values( array_filter( $kits, 'is_array' ) ) : array();
	}

	private static function find( $user_id, $id ) {
		foreach ( self::kits( $user_id ) as $kit ) {
			if ( isset( $kit['id'] ) && $kit['id'] === $id ) {
				return $kit;
			}
		}
		return null;
	}

	private static function fail( $message, $status = 400 ) {
		wp_send_json_error( array( 'errors' => array( $message ) ), $status );
	}

	private static function require_login() {
		if ( ! is_user_logged_in() ) {
			self::fail( __( 'Log in to save your kit.', 'atelier-irisee-master-plugin' ), 403 );
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- save and delete check the configurator nonce; load only reads the visitor's own data.

	private static function int_param( $name ) {
		return isset( $_POST[ $name ] ) ? absint( wp_unslash( $_POST[ $name ] ) ) : 0;
	}

	public static function save_kit() {
		self::require_login();
		if ( ! check_ajax_referer( AIMP_Ajax::NONCE, 'nonce', false ) ) {
			self::fail( __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ), 403 );
		}
		$req = AIMP_Catalog::get_requirements( self::int_param( 'variation' ) );
		if ( ! $req ) {
			self::fail( __( 'This item is not available.', 'atelier-irisee-master-plugin' ), 404 );
		}
		$notions = array();
		foreach ( AIMP_Catalog::notion_types() as $type => $info ) {
			$notions[ $type ] = self::int_param( $info['role'] );
		}
		$kit = array(
			'id'        => strtolower( wp_generate_password( 10, false, false ) ),
			'variation' => $req['variation']->get_id(),
			'fabric'    => self::int_param( 'fabric' ),
			'notions'   => $notions,
			'date'      => time(),
		);
		$user_id = get_current_user_id();
		$kits    = self::kits( $user_id );
		array_unshift( $kits, $kit );
		update_user_meta( $user_id, self::META, array_slice( $kits, 0, self::MAX ) );
		wp_send_json_success(
			array(
				'message' => __( 'Kit saved. Find it on your favorites page.', 'atelier-irisee-master-plugin' ),
				'url'     => self::favorites_url(),
			)
		);
	}

	public static function delete_kit() {
		self::require_login();
		if ( ! check_ajax_referer( AIMP_Favorites::NONCE, 'nonce', false ) ) {
			self::fail( __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ), 403 );
		}
		$id      = isset( $_POST['kit'] ) ? sanitize_key( wp_unslash( $_POST['kit'] ) ) : '';
		$user_id = get_current_user_id();
		$kits    = array_values(
			array_filter(
				self::kits( $user_id ),
				function ( $kit ) use ( $id ) {
					return ! isset( $kit['id'] ) || $kit['id'] !== $id;
				}
			)
		);
		update_user_meta( $user_id, self::META, $kits );
		wp_send_json_success( array( 'left' => count( $kits ) ) );
	}

	/**
	 * Everything the configurator needs to open a saved kit: the pattern and its sizes, the chosen size,
	 * and the fabric and haberdashery as the configurator's own cards (current prices and stock).
	 */
	public static function load_kit() {
		self::require_login();
		$kit = self::find( get_current_user_id(), isset( $_POST['kit'] ) ? sanitize_key( wp_unslash( $_POST['kit'] ) ) : '' );
		$req = $kit ? AIMP_Catalog::get_requirements( $kit['variation'] ) : null;
		if ( ! $req ) {
			self::fail( __( 'This kit is no longer available.', 'atelier-irisee-master-plugin' ), 404 );
		}
		$sizes   = AIMP_Catalog::get_sizes( $req['pattern']->get_id() );
		$missing = false;

		$fabric = null;
		if ( $req['fabric_units'] > 0 ) {
			$product = $kit['fabric'] ? wc_get_product( $kit['fabric'] ) : null;
			$card    = $product && 'publish' === $product->get_status() ? AIMP_Catalog::material_card( $product, $req['fabric_units'] ) : null;
			if ( $card && $card['available'] ) {
				$fabric = $card;
			} else {
				$missing = true;
			}
		}

		$notions = array();
		foreach ( array_keys( AIMP_Catalog::notion_types() ) as $type ) {
			$count = AIMP_Catalog::notion_count( $type, $req );
			if ( $count <= 0 ) {
				continue;
			}
			$id      = isset( $kit['notions'][ $type ] ) ? absint( $kit['notions'][ $type ] ) : 0;
			$product = $id ? wc_get_product( $id ) : null;
			$card    = $product && 'publish' === $product->get_status() ? AIMP_Catalog::material_card( $product, $count ) : null;
			if ( $card && $card['available'] ) {
				$notions[ $type ] = $card;
			} elseif ( $id ) {
				$missing = true;
			}
		}

		wp_send_json_success(
			array(
				'sizes'   => $sizes,
				'size'    => $req['variation']->get_id(),
				'fabric'  => $fabric,
				'notions' => (object) $notions,
				'message' => $missing ? __( 'Some items of this kit are no longer available. Please choose them again.', 'atelier-irisee-master-plugin' ) : '',
			)
		);
	}

	// phpcs:enable

	private static function favorites_url() {
		$page = AIMP_Settings::get( 'favorites_page' );
		return ( $page && 'publish' === get_post_status( $page ) ) ? get_permalink( $page ) : '';
	}

	/**
	 * The current price of a saved kit, with the sewing project kit discount.
	 *
	 * @param array $kit Kit.
	 * @param array $req Requirements of its size.
	 * @return float
	 */
	private static function price( $kit, $req ) {
		$total = (float) wc_get_price_to_display( $req['variation'] );
		if ( $req['fabric_units'] > 0 && $kit['fabric'] ) {
			$fabric = wc_get_product( $kit['fabric'] );
			$total += $fabric ? (float) wc_get_price_to_display( $fabric ) * $req['fabric_units'] : 0;
		}
		foreach ( array_keys( AIMP_Catalog::notion_types() ) as $type ) {
			$count   = AIMP_Catalog::notion_count( $type, $req );
			$product = ( $count > 0 && ! empty( $kit['notions'][ $type ] ) ) ? wc_get_product( $kit['notions'][ $type ] ) : null;
			$total  += $product ? (float) wc_get_price_to_display( $product ) * $count : 0;
		}
		return $total * ( 100 - AIMP_Cart::kit_discount() ) / 100;
	}

	/**
	 * "My saved sewing kits" on the favorites page (logged-in customers with saved kits).
	 *
	 * @return string
	 */
	public static function section_html() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$configurator = AIMP_Settings::get( 'configurator_page' );
		$base         = ( $configurator && 'publish' === get_post_status( $configurator ) ) ? get_permalink( $configurator ) : '';
		$cards        = '';
		foreach ( self::kits( get_current_user_id() ) as $kit ) {
			$req = AIMP_Catalog::get_requirements( $kit['variation'] );
			if ( ! $req || ! $base ) {
				continue;
			}
			$image  = $req['pattern']->get_image_id() ? wp_get_attachment_image_url( $req['pattern']->get_image_id(), 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
			$fabric = ( $req['fabric_units'] > 0 && $kit['fabric'] ) ? wc_get_product( $kit['fabric'] ) : null;
			$label  = wp_strip_all_tags( $req['pattern']->get_name() ) . ' – ' . $req['size'];
			$cards .= sprintf(
				'<li class="aimp-saved-kit" data-aimp-saved-kit="%1$s">' .
				'<a class="aimp-card" href="%2$s">' .
				'<span class="aimp-card-image"><img src="%3$s" alt="" loading="lazy"></span>' .
				'<span class="aimp-card-name">%4$s</span>' .
				'%5$s<span class="aimp-card-price">%6$s</span>' .
				'<span class="aimp-button aimp-fav-view">%7$s</span></a>' .
				'<button type="button" class="aimp-saved-kit-remove" data-aimp-kit-remove="%1$s" aria-label="%8$s" title="%8$s">×</button></li>',
				esc_attr( $kit['id'] ),
				esc_url( add_query_arg( 'aimp_kit', $kit['id'], $base ) ),
				esc_url( $image ),
				/* translators: %s: pattern name and size */
				esc_html( sprintf( __( 'Sewing project kit: %s', 'atelier-irisee-master-plugin' ), $label ) ),
				$fabric ? '<span class="aimp-saved-kit-fabric">' . esc_html( wp_strip_all_tags( $fabric->get_name() ) ) . '</span>' : '',
				wp_kses_post( wc_price( self::price( $kit, $req ) ) ),
				esc_html__( 'Continue', 'atelier-irisee-master-plugin' ),
				esc_attr__( 'Remove this kit', 'atelier-irisee-master-plugin' )
			);
		}
		if ( '' === $cards ) {
			return '';
		}
		return '<section class="aimp-saved-kits" data-aimp-saved-kits data-endpoint="' . esc_url( WC_AJAX::get_endpoint( 'aimp_delete_kit' ) ) . '">' .
			'<h2 class="aimp-saved-kits-title">' . esc_html__( 'My saved sewing kits', 'atelier-irisee-master-plugin' ) . '</h2>' .
			'<ul class="aimp-grid aimp-grid--compact aimp-favorites-grid">' . $cards . '</ul></section>';
	}
}
