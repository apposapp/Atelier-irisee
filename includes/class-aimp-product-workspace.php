<?php
/**
 * Product edit screen for fabrics and patterns: one "Atelier Irisee" workspace with only the fields these
 * products use, as numbered cards. Every other field is still there, neatly grouped under "Show all
 * other fields".
 *
 * Nothing is duplicated: assets/js/admin-workspace.js moves the existing inputs (WooCommerce's price and
 * stock rows, the Fabric texts box, the recommendation box, the pattern tab) into the cards, so saving
 * works exactly as before. The script runs in the footer before the text editors start, because moving a
 * running editor would empty it.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Product_Workspace {

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_box' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Fabric, pattern or '' (the normal screen), from the saved categories.
	 *
	 * @param WC_Product|null $product Product.
	 * @return string
	 */
	public static function type( $product ) {
		if ( ! $product instanceof WC_Product || ! $product->get_id() ) {
			return '';
		}
		if ( $product->is_type( 'variable' ) && AIMP_Catalog::in_categories( $product, AIMP_Catalog::category_tree( AIMP_Settings::get( 'pattern_cat' ) ) ) ) {
			return 'pattern';
		}
		if ( $product->is_type( 'simple' ) && AIMP_Catalog::in_categories( $product, AIMP_Catalog::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) ) ) {
			return 'fabric';
		}
		return '';
	}

	private static function current_type() {
		global $post;
		return $post ? self::type( wc_get_product( $post->ID ) ) : '';
	}

	public static function add_box() {
		if ( '' === self::current_type() ) {
			return;
		}
		add_meta_box( 'aimp-workspace', __( 'Atelier Irisee', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render' ), 'product', 'normal', 'high' );
	}

	public static function enqueue( $hook_suffix ) {
		global $typenow;
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || 'product' !== $typenow || '' === self::current_type() ) {
			return;
		}
		wp_enqueue_style( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/css/admin.css', array(), AIMP_VERSION );
		// In the footer, before the text editors start (they print at priority 50).
		wp_enqueue_script( 'aimp-admin-workspace', AIMP_PLUGIN_URL . 'assets/js/admin-workspace.js', array(), AIMP_VERSION, true );
	}

	/**
	 * A numbered, foldable card with an empty place the script fills.
	 *
	 * @param int    $number Number.
	 * @param string $title  Title.
	 * @param string $slot   Slot name.
	 * @param string $hint   Short explanation.
	 */
	private static function card( $number, $title, $slot, $hint = '' ) {
		printf(
			'<details class="aimp-ws-card" open data-ws-card="%3$s"><summary><span class="aimp-ws-num">%1$d</span><span class="aimp-ws-title">%2$s</span></summary><div class="aimp-ws-body">%4$s<div data-ws-slot="%3$s"></div></div></details>',
			(int) $number,
			esc_html( $title ),
			esc_attr( $slot ),
			'' !== $hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
		);
	}

	public static function render() {
		$type = self::current_type();
		echo '<div class="aimp-ws" data-aimp-ws data-type="' . esc_attr( $type ) . '" data-skill="' . esc_attr( AIMP_Catalog::META_SKILL ) . '" data-price="' . esc_attr( AIMP_Product_Fields::META_PRICE ) . '">';
		echo '<div data-ws-slot="check"></div>';
		if ( 'fabric' === $type ) {
			self::card( 1, __( 'Price and stock', 'atelier-irisee-master-plugin' ), 'price', __( 'The price is per 10 cm.', 'atelier-irisee-master-plugin' ) );
			self::card( 2, __( 'Inspiration', 'atelier-irisee-master-plugin' ), 'inspiration' );
			self::card( 3, __( 'Specifications', 'atelier-irisee-master-plugin' ), 'specs' );
			self::card( 4, __( 'Washing instructions', 'atelier-irisee-master-plugin' ), 'washing' );
			self::card( 5, __( 'Recommendation', 'atelier-irisee-master-plugin' ), 'recommendation' );
		} else {
			self::card( 1, __( 'Price and stock', 'atelier-irisee-master-plugin' ), 'price', __( 'One price and one stock for all sizes.', 'atelier-irisee-master-plugin' ) );
			self::card( 2, __( 'Pattern details', 'atelier-irisee-master-plugin' ), 'details' );
			self::card( 3, __( 'Sizes and material needs', 'atelier-irisee-master-plugin' ), 'sizes', __( 'Attributes: the sizes. Variations: per size the fabric, buttons, zip, ribbon and bias tape it needs, and the measurements.', 'atelier-irisee-master-plugin' ) );
			self::card( 4, __( 'Fabric categories shown first', 'atelier-irisee-master-plugin' ), 'priority' );
			self::card( 5, __( 'Recommendation', 'atelier-irisee-master-plugin' ), 'recommendation' );
		}
		printf(
			'<details class="aimp-ws-other" data-ws-other><summary>%1$s</summary><p class="description">%2$s</p><div data-ws-slot="other"></div></details>',
			esc_html__( 'Show all other fields', 'atelier-irisee-master-plugin' ),
			esc_html__( 'Fields these products normally don\'t use, grouped. Everything here still saves as usual.', 'atelier-irisee-master-plugin' )
		);
		echo '</div>';
	}
}
