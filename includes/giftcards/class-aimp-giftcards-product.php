<?php
/**
 * The gift card product: product-page form (design, amount, delivery), cart pricing,
 * order item data, and creating the codes once the order is paid.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Giftcards_Product {

	const FLAG = '_aimp_is_giftcard';

	/** @var array|null Validated form values of the current add-to-cart request. */
	private static $pending = null;

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_flag' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_flag' ) );
		add_action( 'admin_post_aimp_gc_create_product', array( __CLASS__, 'create_product' ) );

		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_form' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'loop_url' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_text' ), 10, 2 );
		add_filter( 'woocommerce_product_supports', array( __CLASS__, 'supports' ), 10, 3 );

		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'set_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_meta' ) );
		// The chosen design as the picture of the gift card in the cart, mini cart and order.
		add_filter( 'woocommerce_cart_item_thumbnail', array( __CLASS__, 'cart_thumbnail' ), 10, 2 );
		add_filter( 'woocommerce_order_item_thumbnail', array( __CLASS__, 'order_thumbnail' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_item_thumbnail', array( __CLASS__, 'admin_order_thumbnail' ), 10, 3 );

		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'remember_language' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'remember_language' ) );

		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'issue_cards' ), 5 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'issue_cards' ), 5 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'issue_cards' ), 5 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'void_cards' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'void_cards' ) );
	}

	public static function is_giftcard( $product ) {
		return $product instanceof WC_Product && 'yes' === $product->get_meta( self::FLAG );
	}

	public static function cart_has_giftcard() {
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['aimp_gc'] ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * Admin: flag and "create product"
	 * ------------------------------------------------------------------ */

	public static function render_flag() {
		echo '<div class="options_group show_if_simple">';
		woocommerce_wp_checkbox(
			array(
				'id'          => self::FLAG,
				'label'       => __( 'Atelier Irisee gift card', 'atelier-irisee-master-plugin' ),
				'description' => __( 'Customers choose the amount, design and delivery on the product page, and receive a gift card code.', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</div>';
	}

	public static function save_flag( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		$product->update_meta_data( self::FLAG, empty( $_POST[ self::FLAG ] ) ? 'no' : 'yes' );
	}

	public static function create_product() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_gc_create_product' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$designs = AIMP_Giftcards::designs();
		$first   = reset( $designs );

		$product = new WC_Product_Simple();
		$product->set_name( __( 'Custom gift card', 'atelier-irisee-master-plugin' ) );
		$product->set_status( 'publish' );
		$product->set_virtual( true );
		$product->set_tax_status( 'none' );
		$product->set_regular_price( (string) AIMP_Giftcards::opt( 'min_amount' ) );
		$product->set_short_description( __( 'Choose your own amount and one of our designs. Send the gift card by email, to yourself or straight to the lucky one, or receive it beautifully printed by post.', 'atelier-irisee-master-plugin' ) );
		if ( $first && ! empty( $first['image_id'] ) ) {
			$product->set_image_id( (int) $first['image_id'] );
		}
		$product->update_meta_data( self::FLAG, 'yes' );
		$id = $product->save();

		$options               = (array) get_option( AIMP_Giftcards::OPTION, array() );
		$options['product_id'] = $id;
		update_option( AIMP_Giftcards::OPTION, $options );

		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Product page
	 * ------------------------------------------------------------------ */

	public static function enqueue() {
		if ( ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( self::is_giftcard( $product ) ) {
			AIMP_Giftcards_Redeem::enqueue_assets();
		}
	}

	public static function render_form() {
		global $product;
		if ( ! self::is_giftcard( $product ) ) {
			return;
		}
		$template = locate_template( 'atelier-irisee/giftcards/product-form.php' );
		include $template ? $template : AIMP_PLUGIN_DIR . 'templates/giftcards/product-form.php';
	}

	public static function price_html( $html, $product ) {
		if ( ! self::is_giftcard( $product ) ) {
			return $html;
		}
		$amounts = AIMP_Giftcards::preset_amounts();
		$min     = $amounts ? min( $amounts ) : 0;
		$max     = $amounts ? max( $amounts ) : 0;
		if ( AIMP_Giftcards::opt( 'custom_amount' ) ) {
			$min = $amounts ? min( $min, (float) AIMP_Giftcards::opt( 'min_amount' ) ) : (float) AIMP_Giftcards::opt( 'min_amount' );
			$max = max( $max, (float) AIMP_Giftcards::opt( 'max_amount' ) );
		}
		return $min === $max ? wc_price( $min ) : wc_format_price_range( $min, $max );
	}

	public static function loop_url( $url, $product ) {
		return self::is_giftcard( $product ) ? $product->get_permalink() : $url;
	}

	public static function loop_text( $text, $product ) {
		return self::is_giftcard( $product ) && ! is_product() ? __( 'Choose your gift card', 'atelier-irisee-master-plugin' ) : $text;
	}

	public static function supports( $supports, $feature, $product ) {
		return ( 'ajax_add_to_cart' === $feature && self::is_giftcard( $product ) ) ? false : $supports;
	}

	/* ------------------------------------------------------------------
	 * Validation & cart
	 * ------------------------------------------------------------------ */

	/**
	 * Read and check the product-page form.
	 *
	 * @return array [ values, errors ]
	 */
	private static function read_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce's add-to-cart form; values are validated below.
		$raw = isset( $_POST['aimp_gc'] ) && is_array( $_POST['aimp_gc'] ) ? wp_unslash( $_POST['aimp_gc'] ) : array();
		// phpcs:enable
		$get    = function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
		};
		$errors = array();
		$values = array();

		// Design.
		$designs          = AIMP_Giftcards::designs();
		$values['design'] = sanitize_key( $get( 'design' ) );
		if ( $designs && ! isset( $designs[ $values['design'] ] ) ) {
			$errors[] = __( 'Please choose a design.', 'atelier-irisee-master-plugin' );
		}

		// Amount.
		$choice  = $get( 'amount' );
		$presets = AIMP_Giftcards::preset_amounts();
		$amount  = 0;
		$stepper = '1' === $get( 'stepper' ); // The ‹ › amount chooser in the product page's price bar.
		if ( 'custom' === $choice && ( $stepper || AIMP_Giftcards::opt( 'custom_amount' ) ) ) {
			$amount = (float) str_replace( ',', '.', $get( 'custom_amount' ) );
			$min    = max( (float) AIMP_Giftcards::MIN_AMOUNT, (float) AIMP_Giftcards::opt( 'min_amount' ) );
			$max    = (float) AIMP_Giftcards::opt( 'max_amount' );
			if ( $stepper ) {
				list( $min, $max ) = AIMP_Giftcards::amount_range();
			}
			if ( $amount < $min || $amount > $max ) {
				/* translators: 1: minimum amount, 2: maximum amount */
				$errors[] = sprintf( __( 'Please choose an amount between %1$s and %2$s.', 'atelier-irisee-master-plugin' ), wp_strip_all_tags( wc_price( $min ) ), wp_strip_all_tags( wc_price( $max ) ) );
			} elseif ( $stepper && abs( fmod( $amount, AIMP_Giftcards::AMOUNT_STEP ) ) > 0.001 ) {
				/* translators: %s: step, e.g. €5 */
				$errors[] = sprintf( __( 'Please choose an amount in steps of %s.', 'atelier-irisee-master-plugin' ), wp_strip_all_tags( wc_price( AIMP_Giftcards::AMOUNT_STEP ) ) );
			}
		} elseif ( in_array( (float) str_replace( ',', '.', $choice ), $presets, true ) ) {
			$amount = (float) str_replace( ',', '.', $choice );
		} else {
			$errors[] = __( 'Please choose an amount.', 'atelier-irisee-master-plugin' );
		}
		$values['amount'] = round( $amount, wc_get_price_decimals() );

		// Delivery.
		$allowed = array( 'email_self' );
		if ( AIMP_Giftcards::opt( 'allow_recipient' ) ) {
			$allowed[] = 'email_other';
		}
		if ( AIMP_Giftcards::opt( 'allow_post' ) ) {
			$allowed[] = 'post';
		}
		$values['delivery'] = in_array( $get( 'delivery' ), $allowed, true ) ? $get( 'delivery' ) : 'email_self';

		$values['recipient_name']  = sanitize_text_field( $get( 'recipient_name' ) );
		$values['recipient_email'] = '';
		$values['sender_name']     = sanitize_text_field( $get( 'sender_name' ) );
		$message                   = sanitize_textarea_field( $get( 'message' ) );
		$values['message']         = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 300 ) : substr( $message, 0, 300 );
		$values['send_date']       = '';

		if ( 'email_other' === $values['delivery'] ) {
			$values['recipient_email'] = sanitize_email( $get( 'recipient_email' ) );
			if ( '' === $values['recipient_name'] ) {
				$errors[] = __( 'Please enter the name of the person who receives the gift card.', 'atelier-irisee-master-plugin' );
			}
			if ( ! is_email( $values['recipient_email'] ) ) {
				$errors[] = __( 'Please enter a valid email address for the person who receives the gift card.', 'atelier-irisee-master-plugin' );
			}
		}

		if ( 'post' !== $values['delivery'] && AIMP_Giftcards::opt( 'allow_send_date' ) && '' !== $get( 'send_date' ) ) {
			$date  = DateTime::createFromFormat( 'Y-m-d', $get( 'send_date' ) );
			$today = current_time( 'Y-m-d' );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $get( 'send_date' ) || $get( 'send_date' ) < $today || $get( 'send_date' ) > gmdate( 'Y-m-d', strtotime( '+1 year' ) ) ) {
				$errors[] = __( 'Please choose a send date between today and one year from now.', 'atelier-irisee-master-plugin' );
			} elseif ( $get( 'send_date' ) > $today ) {
				$values['send_date'] = $get( 'send_date' );
			}
		}

		return array( $values, $errors );
	}

	public static function validate( $passed, $product_id ) {
		if ( ! self::is_giftcard( wc_get_product( $product_id ) ) ) {
			return $passed;
		}
		list( $values, $errors ) = self::read_form();
		foreach ( $errors as $error ) {
			wc_add_notice( $error, 'error' );
		}
		self::$pending = $errors ? null : $values;
		return $errors ? false : $passed;
	}

	public static function cart_item_data( $data, $product_id ) {
		if ( ! self::is_giftcard( wc_get_product( $product_id ) ) ) {
			return $data;
		}
		if ( null === self::$pending ) {
			list( $values, $errors ) = self::read_form();
			self::$pending           = $errors ? null : $values;
		}
		if ( self::$pending ) {
			$data['aimp_gc'] = array_merge( self::$pending, array( 'key' => wp_generate_uuid4() ) );
		}
		return $data;
	}

	/**
	 * The picture of a chosen design, or '' when there is none.
	 *
	 * @param array  $gc   Stored gift card choices (aimp_gc).
	 * @param string $size Image size.
	 * @return string <img>
	 */
	public static function design_img( $gc, $size = 'woocommerce_thumbnail' ) {
		$design  = is_array( $gc ) && isset( $gc['design'] ) ? (string) $gc['design'] : '';
		$designs = AIMP_Giftcards::designs( true );
		if ( '' === $design || empty( $designs[ $design ]['image_id'] ) ) {
			return '';
		}
		return (string) wp_get_attachment_image( (int) $designs[ $design ]['image_id'], $size, false, array( 'alt' => AIMP_Giftcards::design_name( $design ) ) );
	}

	/**
	 * @param array  $cart_item Cart item.
	 * @param string $size      Image size.
	 * @return string <img> of the chosen design, or ''.
	 */
	public static function cart_item_image( $cart_item, $size = 'woocommerce_thumbnail' ) {
		return empty( $cart_item['aimp_gc'] ) ? '' : self::design_img( $cart_item['aimp_gc'], $size );
	}

	public static function cart_thumbnail( $html, $cart_item ) {
		$image = self::cart_item_image( $cart_item );
		return '' !== $image ? $image : $html;
	}

	public static function order_thumbnail( $html, $item ) {
		$image = ( $item instanceof WC_Order_Item_Product ) ? self::design_img( $item->get_meta( '_aimp_gc' ) ) : '';
		return '' !== $image ? $image : $html;
	}

	public static function admin_order_thumbnail( $html, $item_id, $item ) {
		$image = ( $item instanceof WC_Order_Item_Product ) ? self::design_img( $item->get_meta( '_aimp_gc' ), 'thumbnail' ) : '';
		return '' !== $image ? $image : $html;
	}

	public static function set_prices( $cart ) {
		$fee = (float) AIMP_Giftcards::opt( 'post_fee' );
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['aimp_gc'] ) ) {
				continue;
			}
			$post = 'post' === $item['aimp_gc']['delivery'];
			$item['data']->set_price( (float) $item['aimp_gc']['amount'] + ( $post ? $fee : 0 ) );
			// A card sent by post is a physical item, so the normal shipping rates apply.
			$item['data']->set_virtual( ! $post );
		}
	}

	/**
	 * Readable gift card details: label => value.
	 *
	 * @param array $gc Stored choices.
	 * @return array
	 */
	private static function details( $gc ) {
		$rows = array();
		if ( ! empty( $gc['design'] ) ) {
			$rows[ __( 'Design', 'atelier-irisee-master-plugin' ) ] = AIMP_Giftcards::design_name( $gc['design'] );
		}
		$rows[ __( 'Gift card value', 'atelier-irisee-master-plugin' ) ] = wp_strip_all_tags( wc_price( $gc['amount'] ) );
		$delivery = AIMP_Giftcards::delivery_label( $gc['delivery'] );
		if ( 'post' === $gc['delivery'] && (float) AIMP_Giftcards::opt( 'post_fee' ) > 0 ) {
			/* translators: %s: fee */
			$delivery .= ' (' . sprintf( __( 'incl. %s printing fee', 'atelier-irisee-master-plugin' ), wp_strip_all_tags( wc_price( AIMP_Giftcards::opt( 'post_fee' ) ) ) ) . ')';
		}
		$rows[ __( 'Delivery', 'atelier-irisee-master-plugin' ) ] = $delivery;
		if ( ! empty( $gc['recipient_name'] ) ) {
			$rows[ __( 'For', 'atelier-irisee-master-plugin' ) ] = $gc['recipient_name'] . ( ! empty( $gc['recipient_email'] ) ? ' (' . $gc['recipient_email'] . ')' : '' );
		}
		if ( ! empty( $gc['send_date'] ) ) {
			$rows[ __( 'Send on', 'atelier-irisee-master-plugin' ) ] = date_i18n( get_option( 'date_format' ), strtotime( $gc['send_date'] ) );
		}
		if ( ! empty( $gc['message'] ) ) {
			$rows[ __( 'Message', 'atelier-irisee-master-plugin' ) ] = $gc['message'];
		}
		return $rows;
	}

	public static function item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['aimp_gc'] ) ) {
			return $item_data;
		}
		foreach ( self::details( $cart_item['aimp_gc'] ) as $key => $value ) {
			$item_data[] = array(
				'key'   => $key,
				'value' => $value,
			);
		}
		return $item_data;
	}

	public static function order_item_meta( $item, $cart_item_key, $values ) {
		if ( empty( $values['aimp_gc'] ) ) {
			return;
		}
		$item->add_meta_data( '_aimp_gc', $values['aimp_gc'], true );
		foreach ( self::details( $values['aimp_gc'] ) as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
	}

	public static function hidden_meta( $keys ) {
		$keys[] = '_aimp_gc';
		$keys[] = '_aimp_gc_ids';
		return $keys;
	}

	public static function remember_language( $order ) {
		$order->update_meta_data( '_aimp_lang', AIMP_I18n::current() );
	}

	/* ------------------------------------------------------------------
	 * Orders: create and void cards
	 * ------------------------------------------------------------------ */

	/**
	 * Create the gift cards of a paid order (safe to call more than once).
	 *
	 * @param int $order_id Order.
	 */
	public static function issue_cards( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$lang   = (string) $order->get_meta( '_aimp_lang' );
		$issued = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$gc = $item->get_meta( '_aimp_gc' );
			if ( ! is_array( $gc ) || $item->get_meta( '_aimp_gc_ids' ) ) {
				continue;
			}
			$ids   = array();
			$codes = array();
			for ( $i = 0; $i < max( 1, (int) $item->get_quantity() ); $i++ ) {
				$id = AIMP_Giftcards::create(
					array(
						'amount'          => $gc['amount'],
						'design'          => $gc['design'],
						'delivery'        => $gc['delivery'],
						'purchaser'       => $order->get_customer_id(),
						'purchaser_email' => $order->get_billing_email(),
						'sender_name'     => $gc['sender_name'] ? $gc['sender_name'] : $order->get_billing_first_name(),
						'recipient_name'  => $gc['recipient_name'],
						'recipient_email' => $gc['recipient_email'],
						'message'         => $gc['message'],
						'send_date'       => $gc['send_date'],
						'order_id'        => $order->get_id(),
						'item_id'         => $item_id,
						'lang'            => $lang,
						/* translators: %s: order number */
						'note'            => sprintf( __( 'Bought with order #%s', 'atelier-irisee-master-plugin' ), $order->get_order_number() ),
					)
				);
				if ( $id ) {
					$ids[]   = $id;
					$card    = AIMP_Giftcards::get( $id );
					$codes[] = $card['code'];
				}
			}
			$item->update_meta_data( '_aimp_gc_ids', $ids );
			$item->update_meta_data( __( 'Gift card code', 'atelier-irisee-master-plugin' ), implode( ', ', $codes ) );
			$item->save();
			$issued = array_merge( $issued, $ids );
		}
		if ( $issued ) {
			/* translators: %d: number of gift cards */
			$order->add_order_note( sprintf( _n( '%d gift card created.', '%d gift cards created.', count( $issued ), 'atelier-irisee-master-plugin' ), count( $issued ) ) );
			foreach ( $issued as $id ) {
				AIMP_Giftcards::deliver( $id );
			}
		}
	}

	/**
	 * Disable unused cards of a cancelled or refunded order.
	 *
	 * @param int $order_id Order.
	 */
	public static function void_cards( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			foreach ( (array) $item->get_meta( '_aimp_gc_ids' ) as $id ) {
				$card = AIMP_Giftcards::get( $id );
				if ( ! $card || 'active' !== $card['status'] ) {
					continue;
				}
				if ( abs( $card['balance'] - $card['amount'] ) < 0.001 ) {
					/* translators: %s: order number */
					AIMP_Giftcards::set_status( $id, 'disabled', sprintf( __( 'Disabled: order #%s was cancelled or refunded.', 'atelier-irisee-master-plugin' ), $order->get_order_number() ) );
				} else {
					/* translators: %s: gift card code */
					$order->add_order_note( sprintf( __( 'Gift card %s was already (partly) used and stays active.', 'atelier-irisee-master-plugin' ), $card['code'] ) );
				}
			}
		}
	}
}
