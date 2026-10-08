<?php
/**
 * Cart: adds a configured set as linked cart lines with locked quantities.
 *
 * Every line of a set carries cart item data under the "aimp" key:
 *   group => unique set ID, role => pattern|fabric|button|zip, qty => locked quantity, label => "Pattern – Size".
 * Removing any line of a set removes the whole set; undo restores the whole set.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Cart {

	const KEY = 'aimp';

	/** Suppresses the cascade while we remove/restore lines ourselves. */
	private static $cascading = false;

	public static function init() {
		// Locked quantities: classic cart.
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'quantity_html' ), 20, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_quantity_update' ), 10, 4 );

		// Locked quantities: block cart (Store API).
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( __CLASS__, 'store_api_editable' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( __CLASS__, 'store_api_limit' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( __CLASS__, 'store_api_limit' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_multiple_of', array( __CLASS__, 'store_api_multiple_of' ), 10, 3 );

		// Linked lines.
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'remove_group' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( __CLASS__, 'restore_group' ), 10, 2 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'remove_orphans' ), 20 );

		// Safety net before checkout.
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart_items' ), 5 );

		// Sewing project kit discount.
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_kit_discount' ), 20 );

		// Display & order meta.
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( __CLASS__, 'order_meta_label' ), 10, 2 );
	}

	/* ------------------------------------------------------------------
	 * Adding a set
	 * ------------------------------------------------------------------ */

	/**
	 * Validate and add a complete set. Nothing is added when any check fails.
	 *
	 * @param int   $variation_id Size variation ID.
	 * @param int   $fabric_id    Fabric product ID.
	 * @param array $notions      Chosen haberdashery: notion type (buttons, zips, ribbons, bias) => product ID (0 = none).
	 * @return true|WP_Error WP_Error carries one message per problem.
	 */
	public static function add_group( $variation_id, $fabric_id, $notions ) {
		$errors = new WP_Error();
		$req    = AIMP_Catalog::get_requirements( $variation_id );
		if ( ! $req ) {
			$errors->add( 'aimp_pattern', __( 'This pattern size is not available.', 'atelier-irisee-master-plugin' ) );
			return $errors;
		}

		$label   = AIMP_Catalog::kit_label( $req );
		$lines   = array();
		$pending = array();

		// Pattern (size).
		$lines[] = array(
			'product' => $req['variation'],
			'qty'     => 1,
			'role'    => 'pattern',
		);

		// Fabric: required when the size needs fabric. Quantities always come from the size, never the browser.
		if ( $req['fabric_units'] > 0 ) {
			$fabric = $fabric_id ? AIMP_Catalog::get_allowed_fabric( $fabric_id, $req ) : null;
			if ( ! $fabric ) {
				$errors->add( 'aimp_fabric', __( 'Please choose one of the fabrics allowed for this pattern.', 'atelier-irisee-master-plugin' ) );
			} else {
				$lines[] = array(
					'product' => $fabric,
					'qty'     => $req['fabric_units'],
					'role'    => 'fabric',
				);
			}
		}

		// Haberdashery (buttons, zips, ribbons, bias tape): optional, but if chosen it must be allowed.
		$not_allowed = array(
			'buttons' => __( 'These buttons cannot be used with this pattern size.', 'atelier-irisee-master-plugin' ),
			'zips'    => __( 'This zip cannot be used with this pattern size.', 'atelier-irisee-master-plugin' ),
			'ribbons' => __( 'This ribbon cannot be used with this pattern size.', 'atelier-irisee-master-plugin' ),
			'bias'    => __( 'This bias tape cannot be used with this pattern size.', 'atelier-irisee-master-plugin' ),
		);
		foreach ( AIMP_Catalog::notion_types() as $type => $info ) {
			$role = $info['role'];
			$id   = isset( $notions[ $type ] ) ? absint( $notions[ $type ] ) : 0;
			if ( ! $id ) {
				continue;
			}
			$product = AIMP_Catalog::get_allowed_notion( $id, $type, $req );
			if ( ! $product ) {
				$errors->add( 'aimp_' . $role, $not_allowed[ $type ] );
				continue;
			}
			$lines[] = array(
				'product' => $product,
				'qty'     => AIMP_Catalog::notion_count( $type, $req ),
				'role'    => $role,
			);
		}

		// Stock for every line, counting what is already in the cart.
		foreach ( $lines as $line ) {
			$message = self::stock_error( $line['product'], $line['qty'], $pending );
			if ( $message ) {
				$errors->add( 'aimp_stock_' . $line['role'], $message );
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$group = wp_generate_uuid4();
		$added = array();
		foreach ( $lines as $line ) {
			$product = $line['product'];
			$data    = array(
				self::KEY => array(
					'group' => $group,
					'role'  => $line['role'],
					'qty'   => $line['qty'],
					'label' => $label,
				),
			);
			try {
				if ( $product->is_type( 'variation' ) ) {
					$key = WC()->cart->add_to_cart( $product->get_parent_id(), $line['qty'], $product->get_id(), $product->get_variation_attributes(), $data );
				} else {
					$key = WC()->cart->add_to_cart( $product->get_id(), $line['qty'], 0, array(), $data );
				}
			} catch ( Exception $e ) {
				$key = false;
				$errors->add( 'aimp_add', $e->getMessage() );
			}

			if ( ! $key ) {
				self::rollback( $added );
				if ( ! $errors->has_errors() ) {
					$errors->add(
						'aimp_add',
						sprintf(
							/* translators: %s: product name */
							__( '"%s" could not be added to the cart.', 'atelier-irisee-master-plugin' ),
							wp_strip_all_tags( $product->get_name() )
						)
					);
				}
				return $errors;
			}
			$added[] = $key;
		}

		return true;
	}

	/**
	 * Stock problem for a product and quantity, or '' when fine.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $qty     Quantity to add.
	 * @param array      $pending Quantities already claimed by earlier lines of this set (by stock ID).
	 * @return string
	 */
	private static function stock_error( $product, $qty, &$pending ) {
		$name = wp_strip_all_tags( $product->get_name() );
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			/* translators: %s: product name */
			return sprintf( __( '"%s" is out of stock.', 'atelier-irisee-master-plugin' ), $name );
		}

		$stock_id             = $product->get_stock_managed_by_id();
		$in_cart              = WC()->cart->get_cart_item_quantities();
		$already              = ( isset( $in_cart[ $stock_id ] ) ? $in_cart[ $stock_id ] : 0 ) + ( isset( $pending[ $stock_id ] ) ? $pending[ $stock_id ] : 0 );
		$pending[ $stock_id ] = ( isset( $pending[ $stock_id ] ) ? $pending[ $stock_id ] : 0 ) + $qty;

		if ( ! $product->has_enough_stock( $already + $qty ) ) {
			return sprintf(
				/* translators: 1: product name, 2: stock quantity */
				__( 'Not enough stock for "%1$s" (%2$d available, including what is already in your cart).', 'atelier-irisee-master-plugin' ),
				$name,
				max( 0, (int) $product->get_stock_quantity() - $already )
			);
		}
		return '';
	}

	/**
	 * Remove lines added during a failed add_group(), without leaving an undo entry.
	 *
	 * @param string[] $keys Cart item keys.
	 */
	private static function rollback( $keys ) {
		self::$cascading = true;
		foreach ( $keys as $key ) {
			WC()->cart->remove_cart_item( $key );
			unset( WC()->cart->removed_cart_contents[ $key ] );
		}
		self::$cascading = false;
		WC()->cart->calculate_totals();
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	private static function meta( $cart_item ) {
		return ( isset( $cart_item[ self::KEY ] ) && is_array( $cart_item[ self::KEY ] ) ) ? $cart_item[ self::KEY ] : null;
	}

	private static function locked_qty( $cart_item ) {
		$meta = self::meta( $cart_item );
		return $meta ? max( 1, absint( $meta['qty'] ) ) : null;
	}

	/**
	 * Cart lines of one set: key => item.
	 *
	 * @param array  $contents Cart contents (or removed contents).
	 * @param string $group    Set ID.
	 * @return array
	 */
	private static function group_items( $contents, $group ) {
		return array_filter(
			$contents,
			function ( $item ) use ( $group ) {
				$meta = self::meta( $item );
				return $meta && $meta['group'] === $group;
			}
		);
	}

	/* ------------------------------------------------------------------
	 * Locked quantities
	 * ------------------------------------------------------------------ */

	public static function quantity_html( $html, $cart_item_key, $cart_item = null ) {
		if ( null === $cart_item ) {
			$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		}
		$qty = self::locked_qty( $cart_item );
		if ( null === $qty ) {
			return $html;
		}
		return sprintf(
			'<span class="aimp-locked-qty">%1$d</span><input type="hidden" name="cart[%2$s][qty]" value="%1$d">',
			$qty,
			esc_attr( $cart_item_key )
		);
	}

	public static function validate_quantity_update( $passed, $cart_item_key, $values, $quantity ) {
		$qty = self::locked_qty( $values );
		if ( null !== $qty && (int) $quantity !== $qty ) {
			wc_add_notice( __( 'The quantities of a configured pattern set are fixed by the chosen size.', 'atelier-irisee-master-plugin' ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function store_api_editable( $value, $product, $cart_item ) {
		return ( is_array( $cart_item ) && self::meta( $cart_item ) ) ? false : $value;
	}

	public static function store_api_limit( $value, $product, $cart_item ) {
		$qty = is_array( $cart_item ) ? self::locked_qty( $cart_item ) : null;
		return null === $qty ? $value : $qty;
	}

	public static function store_api_multiple_of( $value, $product, $cart_item ) {
		return ( is_array( $cart_item ) && self::meta( $cart_item ) ) ? 1 : $value;
	}

	/* ------------------------------------------------------------------
	 * Linked lines
	 * ------------------------------------------------------------------ */

	/**
	 * Removing any line of a set removes the rest of the set.
	 *
	 * @param string  $removed_key Removed cart item key.
	 * @param WC_Cart $cart        Cart.
	 */
	public static function remove_group( $removed_key, $cart ) {
		if ( self::$cascading || empty( $cart->removed_cart_contents[ $removed_key ] ) ) {
			return;
		}
		$meta = self::meta( $cart->removed_cart_contents[ $removed_key ] );
		if ( ! $meta ) {
			return;
		}
		self::$cascading = true;
		foreach ( array_keys( self::group_items( $cart->cart_contents, $meta['group'] ) ) as $key ) {
			$cart->remove_cart_item( $key );
		}
		self::$cascading = false;
	}

	/**
	 * Undoing the removal of one line restores the whole set.
	 *
	 * @param string  $restored_key Restored cart item key.
	 * @param WC_Cart $cart         Cart.
	 */
	public static function restore_group( $restored_key, $cart ) {
		if ( self::$cascading || empty( $cart->cart_contents[ $restored_key ] ) ) {
			return;
		}
		$meta = self::meta( $cart->cart_contents[ $restored_key ] );
		if ( ! $meta ) {
			return;
		}
		self::$cascading = true;
		foreach ( array_keys( self::group_items( $cart->removed_cart_contents, $meta['group'] ) ) as $key ) {
			$cart->restore_cart_item( $key );
		}
		self::$cascading = false;
	}

	/**
	 * Drop material lines whose pattern line is gone (e.g. after a session edge case).
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function remove_orphans( $cart ) {
		$patterns = array();
		foreach ( $cart->cart_contents as $item ) {
			$meta = self::meta( $item );
			if ( $meta && 'pattern' === $meta['role'] ) {
				$patterns[ $meta['group'] ] = true;
			}
		}
		$changed = false;
		foreach ( $cart->cart_contents as $key => $item ) {
			$meta = self::meta( $item );
			if ( $meta && ! isset( $patterns[ $meta['group'] ] ) ) {
				unset( $cart->cart_contents[ $key ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			$cart->set_session();
		}
	}

	/**
	 * Before checkout: restore any tampered quantity. WooCommerce's own stock check runs after this.
	 */
	public static function check_cart_items() {
		$cart    = WC()->cart;
		$changed = false;
		self::remove_orphans( $cart );
		foreach ( $cart->get_cart() as $key => $item ) {
			$qty = self::locked_qty( $item );
			if ( null !== $qty && (int) $item['quantity'] !== $qty ) {
				$cart->set_quantity( $key, $qty, false );
				$changed = true;
			}
		}
		if ( $changed ) {
			$cart->calculate_totals();
			wc_add_notice( __( 'A quantity in your pattern set was reset to the amount required by the chosen size.', 'atelier-irisee-master-plugin' ), 'notice' );
		}
	}

	/* ------------------------------------------------------------------
	 * Sewing project kit discount
	 * ------------------------------------------------------------------ */

	/**
	 * Discount on every item of a set made in the configurator, in percent (setting; 0 = none).
	 *
	 * @return int
	 */
	public static function kit_discount() {
		return min( 90, AIMP_Settings::get( 'kit_discount' ) );
	}

	/**
	 * Whether a cart item belongs to a set made in the configurator.
	 *
	 * @param array $cart_item Cart item.
	 * @return bool
	 */
	public static function is_kit_item( $cart_item ) {
		return null !== self::meta( $cart_item );
	}

	/**
	 * Lower the price of set items. Always from a fresh copy of the product, so recalculating the cart
	 * never applies the discount twice.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function apply_kit_discount( $cart ) {
		$percent = self::kit_discount();
		if ( ! $percent || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( ! self::is_kit_item( $item ) || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			$fresh = wc_get_product( $item['data']->get_id() );
			if ( $fresh ) {
				$item['data']->set_price( round( (float) $fresh->get_price() * ( 100 - $percent ) / 100, 4 ) );
			}
		}
	}

	/**
	 * Price of one unit of a set item before the kit discount (for showing it crossed out).
	 *
	 * @param WC_Product $product Product in the cart.
	 * @return float Display price (with or without VAT, like the shop).
	 */
	public static function undiscounted_price( $product ) {
		$fresh = wc_get_product( $product->get_id() );
		return $fresh ? (float) wc_get_price_to_display( $fresh ) : 0.0;
	}

	/* ------------------------------------------------------------------
	 * Display and order meta
	 * ------------------------------------------------------------------ */

	public static function item_data( $item_data, $cart_item ) {
		$meta = self::meta( $cart_item );
		if ( ! $meta ) {
			return $item_data;
		}

		if ( 'pattern' === $meta['role'] ) {
			$parts = array();
			foreach ( self::group_items( WC()->cart->get_cart(), $meta['group'] ) as $item ) {
				$item_meta = self::meta( $item );
				if ( 'pattern' !== $item_meta['role'] ) {
					$parts[] = sprintf( '%s × %d', wp_strip_all_tags( $item['data']->get_name() ), $item['quantity'] );
				}
			}
			$item_data[] = array(
				'key'   => __( 'Configured set', 'atelier-irisee-master-plugin' ),
				'value' => $parts ? implode( ', ', $parts ) : __( 'Pattern only', 'atelier-irisee-master-plugin' ),
			);
		} else {
			$item_data[] = array(
				'key'   => __( 'For', 'atelier-irisee-master-plugin' ),
				'value' => $meta['label'],
			);
			if ( in_array( $meta['role'], array( 'fabric', 'ribbon', 'bias' ), true ) ) {
				$item_data[] = array(
					'key'   => __( 'Length', 'atelier-irisee-master-plugin' ),
					'value' => AIMP_Catalog::fabric_text( (int) $meta['qty'] ),
				);
			}
		}

		$item_data[] = array(
			'key'   => __( 'Note', 'atelier-irisee-master-plugin' ),
			'value' => __( 'Removing any item of this set removes the whole set.', 'atelier-irisee-master-plugin' ),
		);
		return $item_data;
	}

	/**
	 * Copy the set information onto the order line item (HPOS-safe: order item meta, not post meta).
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item.
	 * @param WC_Order              $order         Order.
	 */
	public static function order_item_meta( $item, $cart_item_key, $values, $order ) {
		$meta = self::meta( $values );
		if ( ! $meta ) {
			return;
		}
		$item->add_meta_data( '_aimp_group', $meta['group'], true );
		$item->add_meta_data( '_aimp_role', $meta['role'], true );
		$item->add_meta_data(
			'aimp_configuration',
			sprintf( '%s (#%s)', $meta['label'], strtoupper( substr( str_replace( '-', '', $meta['group'] ), 0, 6 ) ) ),
			true
		);
		if ( in_array( $meta['role'], array( 'fabric', 'ribbon', 'bias' ), true ) ) {
			$item->add_meta_data( 'aimp_length', AIMP_Catalog::fabric_text( (int) $meta['qty'] ), true );
		}
		if ( self::kit_discount() ) {
			$item->add_meta_data( __( 'Sewing project kit discount', 'atelier-irisee-master-plugin' ), self::kit_discount() . '%', true );
		}
	}

	public static function order_meta_label( $display_key, $meta ) {
		if ( 'aimp_configuration' === $meta->key ) {
			return __( 'Configuration', 'atelier-irisee-master-plugin' );
		}
		if ( 'aimp_length' === $meta->key ) {
			return __( 'Length', 'atelier-irisee-master-plugin' );
		}
		return $display_key;
	}
}
