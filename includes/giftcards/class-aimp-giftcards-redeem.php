<?php
/**
 * Paying with a gift card.
 *
 * A gift card works like a payment: it lowers the amount to pay *after* VAT and shipping, so the VAT on
 * the products stays correct. In the cart it reduces the calculated total; on the order it is stored as
 * a VAT-free negative fee line per card, so the order still adds up. The balance is taken off when the
 * order is placed and given back when the order is cancelled, fails or is fully refunded.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Giftcards_Redeem {

	const SESSION = 'aimp_gc_applied';
	const NONCE   = 'aimp_gc';

	/** @var array code => amount used in the latest cart calculation. */
	private static $used = array();

	/** @var bool Whether the cart total has been calculated in this request. */
	private static $calculated = false;

	public static function init() {
		add_filter( 'woocommerce_calculated_total', array( __CLASS__, 'apply_to_total' ), 99, 2 );

		// Code field and lines in the totals.
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'print_box' ) );
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'print_box' ) );
		add_action( 'woocommerce_cart_totals_before_order_total', array( __CLASS__, 'total_rows' ) );
		add_action( 'woocommerce_review_order_before_order_total', array( __CLASS__, 'total_rows' ) );
		add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'block_box' ) );
		add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'block_box' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );

		foreach ( array( 'apply', 'remove', 'check' ) as $action ) {
			add_action( 'wc_ajax_aimp_gc_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}

		// Orders.
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'add_order_lines' ), 20 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'deduct' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'store_api_order' ), 20 );
		foreach ( array( 'cancelled', 'failed', 'refunded' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'restore' ) );
		}
		// A failed order that is paid later (e.g. "Pay for order") takes the gift card amounts again.
		foreach ( array( 'on-hold', 'processing', 'completed' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'deduct_again' ) );
		}
	}

	public static function deduct_again( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_meta( '_aimp_gc_used' ) && ! $order->get_meta( '_aimp_gc_deducted' ) ) {
			self::deduct( $order );
		}
	}

	/* ------------------------------------------------------------------
	 * Session
	 * ------------------------------------------------------------------ */

	public static function codes() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}
		return array_values( array_filter( (array) WC()->session->get( self::SESSION, array() ) ) );
	}

	private static function set_codes( $codes ) {
		if ( WC()->session ) {
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			WC()->session->set( self::SESSION, array_values( array_unique( $codes ) ) );
		}
	}

	/**
	 * Amounts used per code in the current cart (calculates the cart when needed).
	 *
	 * @return array
	 */
	public static function used() {
		if ( ! self::$calculated && self::codes() && WC()->cart ) {
			WC()->cart->calculate_totals();
		}
		return self::$used;
	}

	/* ------------------------------------------------------------------
	 * Cart total
	 * ------------------------------------------------------------------ */

	public static function apply_to_total( $total, $cart ) {
		self::$calculated = true;
		self::$used       = array();
		$codes            = self::codes();
		if ( ! $codes || AIMP_Giftcards_Product::cart_has_giftcard() ) {
			return $total;
		}
		$remaining = (float) $total;
		foreach ( $codes as $code ) {
			$card = AIMP_Giftcards::get( AIMP_Giftcards::find_by_code( $code ) );
			if ( AIMP_Giftcards::unusable_reason( $card ) || $remaining <= 0 ) {
				continue;
			}
			$use = round( min( $card['balance'], $remaining ), wc_get_price_decimals() );
			if ( $use > 0 ) {
				self::$used[ $card['code'] ] = $use;
				$remaining                  -= $use;
			}
		}
		return max( 0, round( $remaining, wc_get_price_decimals() ) );
	}

	/* ------------------------------------------------------------------
	 * Field & totals display
	 * ------------------------------------------------------------------ */

	public static function enqueue_assets() {
		if ( wp_script_is( 'aimp-giftcards', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'aimp-giftcards', AIMP_PLUGIN_URL . 'assets/css/giftcards.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-giftcards', AIMP_PLUGIN_URL . 'assets/js/giftcards.js', array(), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-giftcards',
			'aimpGiftcards',
			array(
				'endpoint' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
				'cartUrl'  => wc_get_cart_url(),
				'lang'     => AIMP_I18n::current(),
				'currency' => array(
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'position' => get_option( 'woocommerce_currency_pos', 'left' ),
					'decimals' => wc_get_price_decimals(),
					'decimal'  => wc_get_price_decimal_separator(),
					'thousand' => wc_get_price_thousand_separator(),
				),
				'i18n'     => array(
					'error'   => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
					'copied'  => __( 'Copied', 'atelier-irisee-master-plugin' ),
					'copy'    => __( 'Copy code', 'atelier-irisee-master-plugin' ),
					'enter'   => __( 'Please enter a gift card code.', 'atelier-irisee-master-plugin' ),
					'working' => __( 'Please wait…', 'atelier-irisee-master-plugin' ),
				),
			)
		);
	}

	public static function maybe_enqueue() {
		if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * The gift card code box.
	 *
	 * @return string
	 */
	public static function box_html() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return '';
		}
		self::enqueue_assets();
		$used = self::used();
		$html = '<div class="aimp-gc-redeem" data-aimp-gc-redeem><p class="aimp-gc-redeem-title">' . esc_html__( 'Do you have a gift card?', 'atelier-irisee-master-plugin' ) . '</p>';

		if ( AIMP_Giftcards_Product::cart_has_giftcard() ) {
			return $html . '<p class="aimp-gc-redeem-note">' . esc_html__( 'Gift cards cannot be used to buy other gift cards.', 'atelier-irisee-master-plugin' ) . '</p></div>';
		}

		if ( $used ) {
			$html .= '<ul class="aimp-gc-applied">';
			foreach ( $used as $code => $amount ) {
				$html .= sprintf(
					'<li><span>%1$s</span> <strong>−%2$s</strong> <button type="button" class="aimp-gc-link" data-aimp-gc-remove="%3$s">%4$s</button></li>',
					/* translators: %s: masked gift card code */
					esc_html( sprintf( __( 'Gift card %s', 'atelier-irisee-master-plugin' ), AIMP_Giftcards::mask( $code ) ) ),
					wp_strip_all_tags( wc_price( $amount ) ),
					esc_attr( $code ),
					esc_html__( 'Remove', 'atelier-irisee-master-plugin' )
				);
			}
			$html .= '</ul>';
		}

		$html .= '<div class="aimp-gc-redeem-row">' .
			'<label class="screen-reader-text" for="aimp-gc-code">' . esc_html__( 'Gift card code', 'atelier-irisee-master-plugin' ) . '</label>' .
			'<input type="text" id="aimp-gc-code" class="input-text" placeholder="' . esc_attr__( 'Gift card code', 'atelier-irisee-master-plugin' ) . '" autocomplete="off" spellcheck="false">' .
			'<button type="button" class="button aimp-gc-button" data-aimp-gc-apply>' . esc_html__( 'Use gift card', 'atelier-irisee-master-plugin' ) . '</button>' .
			'</div><p class="aimp-gc-redeem-msg" role="status" hidden></p></div>';
		return $html;
	}

	public static function print_box() {
		echo self::box_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in box_html().
	}

	public static function block_box( $content ) {
		return self::box_html() . $content;
	}

	public static function total_rows() {
		foreach ( self::used() as $code => $amount ) {
			/* translators: %s: masked gift card code */
			$label = sprintf( __( 'Gift card %s', 'atelier-irisee-master-plugin' ), AIMP_Giftcards::mask( $code ) );
			printf(
				'<tr class="aimp-gc-total-row"><th>%1$s</th><td data-title="%2$s">−%3$s</td></tr>',
				esc_html( $label ),
				esc_attr( $label ),
				wp_kses_post( wc_price( $amount ) )
			);
		}
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	private static function respond_error( $message ) {
		wp_send_json_error( array( 'message' => $message ) );
	}

	private static function check_request( $limit_action ) {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			self::respond_error( __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ) );
		}
		if ( AIMP_Login_Security::rate_limited( $limit_action, AIMP_Login_Security::ip(), 20 ) ) {
			self::respond_error( __( 'Too many attempts. Please try again later.', 'atelier-irisee-master-plugin' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		return AIMP_Giftcards::normalize_code( isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '' );
	}

	public static function ajax_apply() {
		$code = self::check_request( 'gc_apply' );
		$card = AIMP_Giftcards::get( AIMP_Giftcards::find_by_code( $code ) );
		$why  = AIMP_Giftcards::unusable_reason( $card );
		if ( $why ) {
			self::respond_error( $why );
		}
		if ( AIMP_Giftcards_Product::cart_has_giftcard() ) {
			self::respond_error( __( 'Gift cards cannot be used to buy other gift cards.', 'atelier-irisee-master-plugin' ) );
		}
		$codes = self::codes();
		if ( ! in_array( $card['code'], $codes, true ) ) {
			$codes[] = $card['code'];
			self::set_codes( $codes );
		}
		wp_send_json_success(
			array(
				/* translators: %s: balance */
				'message' => sprintf( __( 'Gift card added. Available balance: %s.', 'atelier-irisee-master-plugin' ), wp_strip_all_tags( wc_price( $card['balance'] ) ) ),
				'cartUrl' => wc_get_cart_url(),
			)
		);
	}

	public static function ajax_remove() {
		$code = self::check_request( 'gc_remove' );
		self::set_codes( array_diff( self::codes(), array( $code ) ) );
		wp_send_json_success( array( 'message' => __( 'Gift card removed.', 'atelier-irisee-master-plugin' ) ) );
	}

	public static function ajax_check() {
		$code = self::check_request( 'gc_check' );
		$card = AIMP_Giftcards::get( AIMP_Giftcards::find_by_code( $code ) );
		if ( ! $card ) {
			self::respond_error( AIMP_Giftcards::unusable_reason( null ) );
		}
		$text = sprintf(
			/* translators: 1: masked code, 2: balance, 3: original value, 4: status */
			__( 'Gift card %1$s: %2$s left of %3$s (%4$s).', 'atelier-irisee-master-plugin' ),
			AIMP_Giftcards::mask( $card['code'] ),
			wp_strip_all_tags( wc_price( $card['balance'] ) ),
			wp_strip_all_tags( wc_price( $card['amount'] ) ),
			AIMP_Giftcards::status_label( $card )
		);
		if ( $card['expires'] ) {
			/* translators: %s: date */
			$text .= ' ' . sprintf( __( 'Valid until %s.', 'atelier-irisee-master-plugin' ), date_i18n( get_option( 'date_format' ), strtotime( $card['expires'] ) ) );
		}
		wp_send_json_success( array( 'message' => $text ) );
	}

	/* ------------------------------------------------------------------
	 * Orders
	 * ------------------------------------------------------------------ */

	/**
	 * Problem with the applied cards (balance changed since they were added), or ''.
	 *
	 * @param int $order_id Order whose own earlier deduction may be counted back.
	 * @return string
	 */
	private static function balance_problem( $order_id = 0 ) {
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		$deducted = $order ? (array) $order->get_meta( '_aimp_gc_deducted' ) : array();
		foreach ( self::used() as $code => $amount ) {
			$card = AIMP_Giftcards::get( AIMP_Giftcards::find_by_code( $code ) );
			$why  = AIMP_Giftcards::unusable_reason( $card );
			$own  = isset( $deducted[ $code ] ) ? (float) $deducted[ $code ] : 0;
			if ( ( $why && ! $own ) || ( $card && $card['balance'] + $own + 0.001 < $amount ) ) {
				/* translators: %s: masked code */
				return sprintf( __( 'The balance of gift card %s has changed. Please remove it and add it again.', 'atelier-irisee-master-plugin' ), AIMP_Giftcards::mask( $code ) );
			}
		}
		return '';
	}

	public static function validate_checkout( $data, $errors ) {
		$problem = self::balance_problem();
		if ( $problem ) {
			$errors->add( 'aimp_gc', $problem );
		}
	}

	/**
	 * Add one VAT-free negative fee line per gift card, so the order adds up.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function add_order_lines( $order ) {
		foreach ( $order->get_fees() as $item_id => $fee ) {
			if ( $fee->get_meta( '_aimp_gc_code' ) ) {
				$order->remove_item( $item_id );
			}
		}
		$used = self::used();
		foreach ( $used as $code => $amount ) {
			$fee = new WC_Order_Item_Fee();
			/* translators: %s: masked gift card code */
			$fee->set_name( sprintf( __( 'Gift card %s', 'atelier-irisee-master-plugin' ), AIMP_Giftcards::mask( $code ) ) );
			$fee->set_amount( -$amount );
			$fee->set_total( -$amount );
			$fee->set_tax_status( 'none' );
			$fee->set_tax_class( '' );
			$fee->set_taxes( array( 'total' => array() ) );
			$fee->add_meta_data( '_aimp_gc_code', $code, true );
			$order->add_item( $fee );
		}
		$order->update_meta_data( '_aimp_gc_used', $used );
	}

	/**
	 * Take the used amounts off the cards (first giving back any earlier deduction for this order).
	 *
	 * @param WC_Order $order Order.
	 */
	public static function deduct( $order ) {
		self::restore_amounts( $order, false );
		$used     = (array) $order->get_meta( '_aimp_gc_used' );
		$deducted = array();
		foreach ( $used as $code => $amount ) {
			$id = AIMP_Giftcards::find_by_code( $code );
			if ( $id ) {
				/* translators: %s: order number */
				$deducted[ $code ] = -1 * AIMP_Giftcards::adjust( $id, -1 * (float) $amount, sprintf( __( 'Used for order #%s', 'atelier-irisee-master-plugin' ), $order->get_order_number() ), $order->get_id() );
			}
		}
		$order->update_meta_data( '_aimp_gc_deducted', $deducted );
		$order->save();
		if ( $deducted ) {
			self::set_codes( array() );
		}
	}

	/**
	 * Checkout block: add the lines, recalculate the order total from its lines and deduct.
	 *
	 * @param WC_Order $order Order.
	 * @throws Exception When a card's balance changed.
	 */
	public static function store_api_order( $order ) {
		$problem = self::balance_problem( $order->get_id() );
		if ( $problem ) {
			if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'aimp_gc_balance', $problem, 400 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON response.
			}
			throw new Exception( $problem ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON response.
		}
		self::add_order_lines( $order );
		// The block checkout calculates the order total from its lines; include the new gift card lines.
		$order->calculate_totals( false );
		self::deduct( $order );
	}

	/**
	 * Give the amounts back to the cards.
	 *
	 * @param WC_Order $order Order.
	 * @param bool     $note  Add an order note.
	 */
	private static function restore_amounts( $order, $note ) {
		$deducted = (array) $order->get_meta( '_aimp_gc_deducted' );
		if ( ! $deducted ) {
			return;
		}
		foreach ( $deducted as $code => $amount ) {
			$id = AIMP_Giftcards::find_by_code( $code );
			if ( $id && $amount > 0 ) {
				/* translators: %s: order number */
				AIMP_Giftcards::adjust( $id, (float) $amount, sprintf( __( 'Returned from order #%s', 'atelier-irisee-master-plugin' ), $order->get_order_number() ), $order->get_id() );
			}
		}
		$order->delete_meta_data( '_aimp_gc_deducted' );
		if ( $note ) {
			$order->add_order_note( __( 'The gift card amounts of this order were returned to the gift cards.', 'atelier-irisee-master-plugin' ) );
		}
		$order->save();
	}

	public static function restore( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::restore_amounts( $order, true );
		}
	}
}
