<?php
/**
 * Alt texts of product photos: a photo without one gets the product's name, so screen readers and image search
 * know what it shows. Only empty alt texts are filled; texts written in the media library are never changed.
 *
 * - When a product is saved: its main photo, gallery and variation photos.
 * - Once for the existing products: in the background, in small batches during admin page loads.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Alt_Texts {

	/** Option: the last product id handled by the one-time run, or "done". */
	const PROGRESS = 'aimp_alt_texts_progress';

	const BATCH = 40;

	public static function init() {
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'on_save' ) );
		add_action( 'admin_init', array( __CLASS__, 'backfill' ) );
	}

	/**
	 * @param WC_Product $product Product (also a variation).
	 */
	public static function on_save( $product ) {
		if ( $product instanceof WC_Product ) {
			self::fill( $product );
		}
	}

	/**
	 * The one-time run for products that existed before: one batch per admin page load until all are done.
	 */
	public static function backfill() {
		$progress = get_option( self::PROGRESS, '0' );
		if ( 'done' === $progress || wp_doing_ajax() || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time run, a few batches.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','private','draft','pending') AND ID > %d ORDER BY ID ASC LIMIT %d",
				(int) $progress,
				self::BATCH
			)
		);
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				self::fill( $product );
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $child ) {
						$variation = wc_get_product( $child );
						if ( $variation ) {
							self::fill( $variation );
						}
					}
				}
			}
		}
		update_option( self::PROGRESS, count( $ids ) < self::BATCH ? 'done' : (string) end( $ids ), false );
	}

	/**
	 * Gives the product's photos without an alt text the product's name.
	 *
	 * @param WC_Product $product Product or variation.
	 */
	private static function fill( $product ) {
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
		$name   = trim( wp_strip_all_tags( html_entity_decode( ( $parent ? $parent : $product )->get_name(), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $name ) {
			return;
		}
		$images = array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', (array) $product->get_gallery_image_ids() ) ) );
		foreach ( array_unique( $images ) as $image_id ) {
			if ( '' === trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) && wp_attachment_is_image( $image_id ) ) {
				update_post_meta( $image_id, '_wp_attachment_image_alt', $name );
			}
		}
	}
}
