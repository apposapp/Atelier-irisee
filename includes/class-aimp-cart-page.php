<?php
/**
 * Cart page: [atelier_irisee_cart].
 *
 * Configurator sets are shown as one product (thumbnails, names and prices of the items, the set total;
 * the amounts stay locked). Other products work as usual. Updating, coupons and removing go through
 * WooCommerce's own cart handling; the totals (with the gift card field, shipping and checkout button)
 * are WooCommerce's cart totals.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Cart_Page {

	const TAG = 'atelier_irisee_cart';

	/** Order of the items inside a set. */
	const ROLE_ORDER = array( 'pattern', 'fabric', 'button', 'zip', 'ribbon', 'bias' );

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		// Before WooCommerce updates the cart (wp_loaded, 20): lengths in cm become units of 10 cm.
		add_action( 'wp_loaded', array( __CLASS__, 'lengths_to_quantities' ), 19 );
	}

	public static function register_assets() {
		wp_register_style( 'aimp-cart', AIMP_PLUGIN_URL . 'assets/css/cart.css', array( 'aimp-configurator', 'aimp-product' ), AIMP_VERSION );
		wp_register_script( 'aimp-cart', AIMP_PLUGIN_URL . 'assets/js/cart.js', array(), AIMP_VERSION, true );
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'aimp-cart' );
		}
	}

	/**
	 * Fabric, ribbon and bias tape are changed in cm on the cart page; WooCommerce counts units of 10 cm.
	 */
	public static function lengths_to_quantities() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the cart nonce when it updates the cart.
		if ( empty( $_POST['aimp_cart_cm'] ) || ! is_array( $_POST['aimp_cart_cm'] ) ) {
			return;
		}
		foreach ( wp_unslash( $_POST['aimp_cart_cm'] ) as $key => $cm ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast below.
			$key = sanitize_key( $key );
			$cm  = (float) $cm;
			// 0 cm removes the product, as a quantity of 0 does in WooCommerce.
			$_POST['cart'][ $key ]['qty'] = $cm > 0 ? (int) ceil( $cm / AIMP_Catalog::FABRIC_UNIT_CM ) : 0;
		}
		$_REQUEST['cart'] = $_POST['cart'];
		// phpcs:enable
	}

	/* ------------------------------------------------------------------
	 * Cart contents
	 * ------------------------------------------------------------------ */

	/**
	 * Price of a cart line as WooCommerce shows it (with or without VAT, per the shop setting).
	 *
	 * @param array $item Cart item.
	 * @return float
	 */
	private static function line_total( $item ) {
		$total = (float) $item['line_subtotal'];
		if ( WC()->cart->display_prices_including_tax() ) {
			$total += (float) $item['line_subtotal_tax'];
		}
		return $total;
	}

	/**
	 * "120 cm", "6 pieces" or "" for a cart line.
	 *
	 * @param array $item Cart item.
	 * @return string
	 */
	private static function amount_text( $item ) {
		$qty = (int) $item['quantity'];
		if ( AIMP_Catalog::sold_per_10cm( $item['data'] ) ) {
			return sprintf(
				/* translators: %s: length in cm */
				__( '%s cm', 'atelier-irisee-master-plugin' ),
				AIMP_I18n::number( $qty * AIMP_Catalog::FABRIC_UNIT_CM )
			);
		}
		/* translators: %d: number of pieces */
		return sprintf( _n( '%d piece', '%d pieces', $qty, 'atelier-irisee-master-plugin' ), $qty );
	}

	/**
	 * The cart split into configurator sets and single products.
	 *
	 * @return array [ sets: [ [ label, total, regular (before the kit discount), remove_url, items: [ … ] ] ], singles: [ … ] ]
	 */
	public static function contents() {
		$sets    = array();
		$singles = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof WC_Product || ! $product->exists() || $item['quantity'] <= 0 ) {
				continue;
			}
			$meta = ( isset( $item[ AIMP_Cart::KEY ] ) && is_array( $item[ AIMP_Cart::KEY ] ) ) ? $item[ AIMP_Cart::KEY ] : null;
			$line = array(
				'key'       => $key,
				'item'      => $item,
				'product'   => $product,
				'name'      => wp_strip_all_tags( $product->get_name() ),
				'image'     => $product->get_image( 'woocommerce_thumbnail' ),
				'permalink' => $product->is_visible() ? $product->get_permalink( $item ) : '',
				'price'     => WC()->cart->get_product_price( $product ),
				'subtotal'  => WC()->cart->get_product_subtotal( $product, $item['quantity'] ),
				'amount'    => self::amount_text( $item ),
				'role'      => $meta ? (string) $meta['role'] : '',
			);
			if ( $meta ) {
				$group = (string) $meta['group'];
				if ( ! isset( $sets[ $group ] ) ) {
					$sets[ $group ] = array(
						'label'      => (string) $meta['label'],
						'total'      => 0,
						'regular'    => 0,
						'remove_url' => wc_get_cart_remove_url( $key ),
						'items'      => array(),
					);
				}
				$sets[ $group ]['total']  += self::line_total( $item );
				// Before the sewing project kit discount, for showing it crossed out.
				$line['regular']             = AIMP_Cart::undiscounted_price( $product ) * (int) $item['quantity'];
				$sets[ $group ]['regular'] += $line['regular'];
				$sets[ $group ]['items'][] = $line;
				if ( 'pattern' === $line['role'] ) {
					$sets[ $group ]['remove_url'] = wc_get_cart_remove_url( $key );
				}
			} else {
				$singles[] = $line;
			}
		}

		$order = array_flip( self::ROLE_ORDER );
		foreach ( $sets as &$set ) {
			usort(
				$set['items'],
				function ( $a, $b ) use ( $order ) {
					$pa = isset( $order[ $a['role'] ] ) ? $order[ $a['role'] ] : 99;
					$pb = isset( $order[ $b['role'] ] ) ? $order[ $b['role'] ] : 99;
					return $pa - $pb;
				}
			);
		}
		unset( $set );

		return array(
			'sets'    => array_values( $sets ),
			'singles' => $singles,
		);
	}

	/**
	 * The amount field of a single product: ‹ amount ›, in cm (steps of 10) for products sold per 10 cm.
	 *
	 * @param array $line Line from contents().
	 * @return string
	 */
	public static function stepper_html( $line ) {
		$product = $line['product'];
		$qty     = (int) $line['item']['quantity'];
		if ( $product->is_sold_individually() ) {
			return '<span class="aimp-cart-qty-fixed">1</span><input type="hidden" name="cart[' . esc_attr( $line['key'] ) . '][qty]" value="1">';
		}
		$per_10cm = AIMP_Catalog::sold_per_10cm( $product );
		$factor   = $per_10cm ? AIMP_Catalog::FABRIC_UNIT_CM : 1;
		$max      = (int) $product->get_max_purchase_quantity();
		$name     = $per_10cm ? 'aimp_cart_cm[' . $line['key'] . ']' : 'cart[' . $line['key'] . '][qty]';
		return sprintf(
			'<div class="aimp-stepper" data-aimp-cart-stepper><button type="button" class="aimp-stepper-btn" data-dir="-1" aria-label="%1$s">‹</button><input type="number" class="aimp-stepper-input" inputmode="numeric" name="%2$s" value="%3$d" min="0" step="%4$d"%5$s aria-label="%6$s">%7$s<button type="button" class="aimp-stepper-btn" data-dir="1" aria-label="%8$s">›</button></div>',
			esc_attr__( 'Less', 'atelier-irisee-master-plugin' ),
			esc_attr( $name ),
			$qty * $factor,
			$factor,
			$max > 0 ? ' max="' . (int) ( $max * $factor ) . '"' : '',
			esc_attr( $per_10cm ? __( 'Length in cm', 'atelier-irisee-master-plugin' ) : __( 'Quantity', 'atelier-irisee-master-plugin' ) ),
			$per_10cm ? '<span class="aimp-stepper-unit">' . esc_html__( 'cm', 'atelier-irisee-master-plugin' ) . '</span>' : '',
			esc_attr__( 'More', 'atelier-irisee-master-plugin' )
		);
	}

	/* ------------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------------ */

	public static function shortcode() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		if ( ! wp_style_is( 'aimp-cart', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-cart' );
		wp_enqueue_script( 'aimp-cart' );

		// What WooCommerce's own cart page does before showing the cart.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce's shipping calculator checks its nonce.
		if ( ! empty( $_POST['calc_shipping'] ) && class_exists( 'WC_Shortcode_Cart' ) ) {
			WC_Shortcode_Cart::calculate_shipping();
		}
		do_action( 'woocommerce_check_cart_items' );
		WC()->cart->calculate_totals();

		$data = array(
			'contents'   => self::contents(),
			'empty'      => WC()->cart->is_empty(),
			'shop_url'   => wc_get_page_permalink( 'shop' ),
			'cart_url'   => wc_get_cart_url(),
			'crosssells' => array_slice( array_filter( array_map( 'wc_get_product', WC()->cart->get_cross_sells() ) ), 0, 4 ),
		);

		ob_start();
		$template = locate_template( 'atelier-irisee/cart.php' );
		include $template ? $template : AIMP_PLUGIN_DIR . 'templates/cart.php';
		return ob_get_clean();
	}
}
