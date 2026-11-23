<?php
/**
 * Share buttons: a round button with a paper plane. On phones and tablets it opens the device's own share sheet; on
 * computers a small menu with Facebook, Pinterest, WhatsApp, email and "Copy link" (share.js). Used on product pages
 * and product cards (next to the heart), and on blog posts and cards.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Share {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 15 );
	}

	/**
	 * On every page: product cards can be anywhere (small files).
	 */
	public static function assets() {
		wp_enqueue_style( 'aimp-share', AIMP_PLUGIN_URL . 'assets/css/share.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-share', AIMP_PLUGIN_URL . 'assets/js/share.js', array(), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-share',
			'aimpShare',
			array(
				'copied' => __( 'Link copied', 'atelier-irisee-master-plugin' ),
				'copy'   => __( 'Copy link', 'atelier-irisee-master-plugin' ),
				'email'  => __( 'E-mail', 'atelier-irisee-master-plugin' ),
				'share'  => __( 'Share', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	public static function icon() {
		return '<svg class="aimp-share-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21 3 10.2 13.8M21 3l-6.8 18-4-7.2L3 9.8z"/></svg>';
	}

	/**
	 * A share button.
	 *
	 * @param string $url   Address to share.
	 * @param string $title Its title.
	 * @param string $image A picture (Pinterest).
	 * @param string $class Extra classes.
	 * @return string
	 */
	public static function button( $url, $title, $image = '', $class = '' ) {
		$label = __( 'Share', 'atelier-irisee-master-plugin' );
		return sprintf(
			'<button type="button" class="aimp-share-btn%1$s" data-aimp-share="%2$s" data-title="%3$s" data-image="%4$s" aria-haspopup="true" aria-expanded="false" aria-label="%5$s" title="%5$s">%6$s</button>',
			'' !== $class ? ' ' . esc_attr( $class ) : '',
			esc_url( $url ),
			esc_attr( wp_strip_all_tags( $title ) ),
			esc_url( $image ),
			esc_attr( $label ),
			self::icon()
		);
	}

	/**
	 * The share button of a product (on its picture, under the heart), unless switched off in the Design studio.
	 *
	 * @param WC_Product|int $product Product.
	 * @param string         $where   card (product cards) or page (the product page).
	 * @return string
	 */
	public static function product_button( $product, $where = 'card' ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		if ( ! $product || ! class_exists( 'AIMP_Design' ) ) {
			return '';
		}
		if ( AIMP_Design::opt( 'product-page', 'page' === $where ? 'pp_noshare' : 'pp_nocardshare' ) ) {
			return '';
		}
		$image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'large' ) : '';
		return self::button( $product->get_permalink(), $product->get_name(), (string) $image, 'aimp-share-btn--overlay' );
	}
}
