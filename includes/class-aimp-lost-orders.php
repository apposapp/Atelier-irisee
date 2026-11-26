<?php
/**
 * A lost package: the order status "Lost", and from it a free replacement order.
 *
 * - "Lost" (wc-aimp-lost): an order whose package did not arrive. It stays a paid order (statistics, invoice), no
 *   email, no change in stock.
 * - The box "Package lost" on the order: the products with how many to send again (all, or part when part got
 *   lost), a note, and "Make free replacement order". The original becomes "Lost".
 * - The replacement order: the same customer, addresses and products (with their kit and length details), all at
 *   0, shipped with PostNL as before (the same pickup point) so PostNL makes a new label with a new barcode. Paid by
 *   "Replacement shipment", status "Processing", stock goes down as usual. Both orders point to each other.
 * - The customer gets the "Replacement shipment" email (in their language) instead of the usual order email.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Lost_Orders {

	const STATUS = 'aimp-lost';

	/** The payment method of a replacement order. */
	const METHOD = 'aimp_replacement';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_status' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'statuses' ) );
		// A lost order was paid: it keeps counting as a sale.
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'paid_statuses' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( __CLASS__, 'paid_statuses' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 31 );
		add_action( 'admin_post_aimp_replacement', array( __CLASS__, 'create' ) );
		// The button is inside the order form, whose own "action" field (edit_order) wins over the address's: the
		// request is picked up here, before admin-post.php looks at "action".
		add_action( 'admin_init', array( __CLASS__, 'maybe_create' ) );
		add_action( 'admin_head', array( __CLASS__, 'list_style' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'email_class' ) );
		// The replacement order: its own email instead of WooCommerce's order emails to the customer.
		foreach ( array( 'customer_processing_order', 'customer_completed_order', 'customer_on_hold_order', 'new_order' ) as $email ) {
			add_filter( 'woocommerce_email_enabled_' . $email, array( __CLASS__, 'no_usual_email' ), 20, 2 );
		}
		// The order's language, for its emails, invoice and replacement (only gift card orders had it).
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'remember_lang' ) );
	}

	/**
	 * @param WC_Order $order Order being made at checkout.
	 */
	public static function remember_lang( $order ) {
		if ( $order instanceof WC_Order && '' === (string) $order->get_meta( '_aimp_lang' ) ) {
			$order->update_meta_data( '_aimp_lang', AIMP_I18n::current() );
		}
	}

	public static function register_status() {
		register_post_status(
			'wc-' . self::STATUS,
			array(
				'label'                     => _x( 'Lost', 'Order status', 'atelier-irisee-master-plugin' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Lost <span class="count">(%s)</span>', 'Lost <span class="count">(%s)</span>', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	/**
	 * @param array $statuses Order statuses.
	 * @return array
	 */
	public static function statuses( $statuses ) {
		$out = array();
		foreach ( $statuses as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'wc-completed' === $key ) {
				$out[ 'wc-' . self::STATUS ] = _x( 'Lost', 'Order status', 'atelier-irisee-master-plugin' );
			}
		}
		if ( ! isset( $out[ 'wc-' . self::STATUS ] ) ) {
			$out[ 'wc-' . self::STATUS ] = _x( 'Lost', 'Order status', 'atelier-irisee-master-plugin' );
		}
		return $out;
	}

	/**
	 * @param string[] $statuses Paid statuses (without wc-).
	 * @return string[]
	 */
	public static function paid_statuses( $statuses ) {
		$statuses   = (array) $statuses;
		$statuses[] = self::STATUS;
		return array_values( array_unique( $statuses ) );
	}

	/**
	 * The status in the order list: a soft red.
	 */
	public static function list_style() {
		echo '<style>.order-status.status-' . esc_attr( self::STATUS ) . '{background:#f6dcdc;color:#8a2a2a}</style>';
	}

	/**
	 * @param array $emails WooCommerce emails.
	 * @return array
	 */
	public static function email_class( $emails ) {
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-email-replacement.php';
		if ( class_exists( 'AIMP_Email_Replacement' ) ) {
			$emails['AIMP_Email_Replacement'] = new AIMP_Email_Replacement();
		}
		return $emails;
	}

	/**
	 * @param bool          $enabled On.
	 * @param WC_Order|null $order   Order.
	 * @return bool
	 */
	public static function no_usual_email( $enabled, $order = null ) {
		return $order instanceof WC_Order && $order->get_meta( '_aimp_replaces' ) ? false : $enabled;
	}

	/* ------------------------------------------------------------------
	 * The box on the order
	 * ------------------------------------------------------------------ */

	public static function meta_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'aimp-lost', __( 'Package lost', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_box' ), $screen, 'normal', 'low' );
	}

	/**
	 * Whether a line is sent again (no gift cards: those are not a parcel, and a copy could make new cards).
	 *
	 * @param WC_Order_Item_Product $item Line.
	 * @return bool
	 */
	private static function resendable( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product || $item->get_meta( '_aimp_gc' ) || $item->get_meta( '_aimp_gc_ids' ) ) {
			return false;
		}
		$product = $item->get_product();
		return ! ( $product && class_exists( 'AIMP_Giftcards_Product' ) && AIMP_Giftcards_Product::is_giftcard( $product ) ) && ! ( $product && $product->is_virtual() );
	}

	/**
	 * @param WP_Post|WC_Order $object Order.
	 */
	public static function render_box( $object ) {
		$order = $object instanceof WC_Order ? $object : wc_get_order( $object->ID );
		if ( ! $order ) {
			return;
		}
		// A replacement: which order it replaces.
		$replaces = (int) $order->get_meta( '_aimp_replaces' );
		if ( $replaces ) {
			$original = wc_get_order( $replaces );
			/* translators: %s: order number */
			echo '<p>' . wp_kses_post( sprintf( __( 'Free replacement for the lost package of order %s.', 'atelier-irisee-master-plugin' ), $original ? '<a href="' . esc_url( $original->get_edit_order_url() ) . '">#' . esc_html( $original->get_order_number() ) . '</a>' : '#' . (int) $replaces ) ) . '</p>';
			echo '<p class="description">' . esc_html__( 'Make the PostNL label as usual (PostNL box); the delivery note is in the Invoice box.', 'atelier-irisee-master-plugin' ) . '</p>';
			return;
		}
		foreach ( (array) $order->get_meta( '_aimp_replaced_by' ) as $done ) {
			$copy = wc_get_order( (int) $done );
			if ( $copy ) {
				/* translators: %s: order number */
				echo '<p><strong>' . wp_kses_post( sprintf( __( 'Sent again with order %s.', 'atelier-irisee-master-plugin' ), '<a href="' . esc_url( $copy->get_edit_order_url() ) . '">#' . esc_html( $copy->get_order_number() ) . '</a>' ) ) . '</strong></p>';
			}
		}
		if ( ! in_array( $order->get_status(), array( 'processing', 'completed', self::STATUS ), true ) ) {
			echo '<p class="description">' . esc_html__( 'For a paid order (Processing, Completed or Lost) whose package did not arrive.', 'atelier-irisee-master-plugin' ) . '</p>';
			return;
		}
		$lines = array();
		foreach ( $order->get_items() as $item ) {
			if ( self::resendable( $item ) ) {
				$lines[] = $item;
			}
		}
		if ( ! $lines ) {
			echo '<p class="description">' . esc_html__( 'This order has nothing to send again.', 'atelier-irisee-master-plugin' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Did the package not arrive? Make a free replacement order: same customer, address and products, everything at 0. Then make its PostNL label as usual. This order becomes "Lost".', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>' . esc_html__( 'Product', 'atelier-irisee-master-plugin' ) . '</th><th style="width:110px">' . esc_html__( 'Send again', 'atelier-irisee-master-plugin' ) . '</th></tr></thead><tbody>';
		foreach ( $lines as $item ) {
			$length = (string) $item->get_meta( 'aimp_length' );
			printf(
				'<tr><td>%1$s</td><td><input type="number" min="0" max="%2$d" step="1" name="aimp_repl_qty[%3$d]" value="%2$d" class="small-text"> / %2$d</td></tr>',
				esc_html( wp_strip_all_tags( $item->get_name() ) . ( '' !== $length ? ' – ' . $length : '' ) ),
				(int) $item->get_quantity(),
				(int) $item->get_id()
			);
		}
		echo '</tbody></table>';
		echo '<p><label>' . esc_html__( 'Note (in the order notes)', 'atelier-irisee-master-plugin' ) . '<br><input type="text" class="large-text" name="aimp_repl_note" placeholder="' . esc_attr__( 'e.g. PostNL: lost in transit, claim filed', 'atelier-irisee-master-plugin' ) . '"></label></p>';
		echo '<p><label><input type="checkbox" name="aimp_repl_mail" value="1" checked> ' . esc_html__( 'Email the customer that the package is sent again (in their language)', 'atelier-irisee-master-plugin' ) . '</label></p>';
		wp_nonce_field( 'aimp_replacement_' . $order->get_id(), 'aimp_replacement_nonce', false );
		// The order screen is one form: this button sends it to its own handler.
		printf(
			'<input type="hidden" name="aimp_repl_order" value="%1$d"><p><button type="submit" class="button button-primary" formaction="%2$s" formmethod="post" onclick="return confirm(%3$s);">%4$s</button></p>',
			(int) $order->get_id(),
			esc_url( admin_url( 'admin-post.php?action=aimp_replacement' ) ),
			esc_attr( wp_json_encode( __( 'Make a free replacement order and mark this order as lost?', 'atelier-irisee-master-plugin' ) ) ),
			esc_html__( 'Make free replacement order', 'atelier-irisee-master-plugin' )
		);
	}

	/* ------------------------------------------------------------------
	 * The replacement order
	 * ------------------------------------------------------------------ */

	public static function maybe_create() {
		global $pagenow;
		// phpcs:disable WordPress.Security.NonceVerification -- create() checks the nonce.
		if ( 'admin-post.php' === $pagenow && isset( $_GET['action'], $_POST['aimp_repl_order'] ) && 'aimp_replacement' === $_GET['action'] ) {
			self::create();
		}
		// phpcs:enable
	}

	public static function create() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked right here.
		$id    = isset( $_POST['aimp_repl_order'] ) ? absint( $_POST['aimp_repl_order'] ) : 0;
		$nonce = isset( $_POST['aimp_replacement_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_replacement_nonce'] ) ) : '';
		if ( ! $id || ! current_user_can( 'edit_shop_orders' ) || ! wp_verify_nonce( $nonce, 'aimp_replacement_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$original = wc_get_order( $id );
		if ( ! $original || $original instanceof WC_Order_Refund ) {
			wp_die( esc_html__( 'Order not found.', 'atelier-irisee-master-plugin' ) );
		}
		$qty  = isset( $_POST['aimp_repl_qty'] ) && is_array( $_POST['aimp_repl_qty'] ) ? array_map( 'absint', wp_unslash( $_POST['aimp_repl_qty'] ) ) : array();
		$note = isset( $_POST['aimp_repl_note'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_repl_note'] ) ) : '';
		$mail = ! empty( $_POST['aimp_repl_mail'] );
		// phpcs:enable
		$new = self::replacement( $original, $qty, $note );
		if ( is_wp_error( $new ) ) {
			wp_die( esc_html( $new->get_error_message() ) );
		}
		if ( $mail && function_exists( 'WC' ) ) {
			$emails = WC()->mailer()->get_emails();
			if ( isset( $emails['AIMP_Email_Replacement'] ) ) {
				$emails['AIMP_Email_Replacement']->trigger( $new->get_id() );
				$new->add_order_note( __( 'The customer was emailed that the package is sent again.', 'atelier-irisee-master-plugin' ) );
			}
		}
		wp_safe_redirect( $new->get_edit_order_url() );
		exit;
	}

	/**
	 * Makes the free replacement order.
	 *
	 * @param WC_Order $original The order whose package got lost.
	 * @param array    $qty      Line id => how many to send again.
	 * @param string   $note     Note.
	 * @return WC_Order|WP_Error
	 */
	public static function replacement( $original, $qty, $note = '' ) {
		$order = wc_create_order(
			array(
				'customer_id' => $original->get_customer_id(),
				'created_via' => 'aimp_replacement',
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$order->set_address( $original->get_address( 'billing' ), 'billing' );
		$order->set_address( $original->get_address( 'shipping' ), 'shipping' );
		$order->set_currency( $original->get_currency() );
		$order->set_prices_include_tax( $original->get_prices_include_tax() );

		// The products, with their details (kit, length, size), at 0.
		$added = 0;
		$skip  = array( '_reduced_stock', '_refunded_item_id', '_aimp_gc', '_aimp_gc_ids' );
		foreach ( $original->get_items() as $item_id => $item ) {
			$count = isset( $qty[ $item_id ] ) ? min( (int) $qty[ $item_id ], (int) $item->get_quantity() ) : 0;
			if ( $count < 1 || ! self::resendable( $item ) ) {
				continue;
			}
			$copy = new WC_Order_Item_Product();
			$copy->set_props(
				array(
					'name'         => $item->get_name(),
					'product_id'   => $item->get_product_id(),
					'variation_id' => $item->get_variation_id(),
					'quantity'     => $count,
					'tax_class'    => $item->get_tax_class(),
					'subtotal'     => 0,
					'total'        => 0,
					'subtotal_tax' => 0,
					'total_tax'    => 0,
					'taxes'        => array(
						'subtotal' => array(),
						'total'    => array(),
					),
				)
			);
			foreach ( $item->get_meta_data() as $meta ) {
				if ( ! in_array( $meta->key, $skip, true ) ) {
					$copy->add_meta_data( $meta->key, $meta->value, false );
				}
			}
			$order->add_item( $copy );
			$added++;
		}
		if ( ! $added ) {
			$order->delete( true );
			return new WP_Error( 'aimp_nothing', __( 'Choose at least one product to send again.', 'atelier-irisee-master-plugin' ) );
		}

		// Shipping as before (PostNL, the same method and delivery choice), at 0.
		foreach ( $original->get_items( 'shipping' ) as $ship ) {
			$copy = new WC_Order_Item_Shipping();
			$copy->set_props(
				array(
					'method_title' => $ship->get_method_title(),
					'method_id'    => $ship->get_method_id(),
					'instance_id'  => $ship->get_instance_id(),
					'total'        => 0,
					'taxes'        => array( 'total' => array() ),
				)
			);
			foreach ( $ship->get_meta_data() as $meta ) {
				$copy->add_meta_data( $meta->key, $meta->value, false );
			}
			$order->add_item( $copy );
		}
		// PostNL's delivery choice (home or the pickup point), without the old label: PostNL makes a new barcode.
		$postnl = $original->get_meta( '_postnl_order_metadata' );
		if ( is_array( $postnl ) ) {
			unset( $postnl['labels'], $postnl['backend'] );
			$order->update_meta_data( '_postnl_order_metadata', $postnl );
		}
		foreach ( array( '_aimp_lang' ) as $key ) {
			if ( '' !== (string) $original->get_meta( $key ) ) {
				$order->update_meta_data( $key, $original->get_meta( $key ) );
			}
		}

		$order->set_payment_method( self::METHOD );
		$order->set_payment_method_title( __( 'Replacement shipment', 'atelier-irisee-master-plugin' ) );
		$order->update_meta_data( '_aimp_replaces', $original->get_id() );
		$order->update_meta_data( '_aimp_replaces_number', $original->get_order_number() );
		$order->calculate_totals( false );
		$order->set_date_paid( time() );
		$order->save();
		/* translators: 1: order number, 2: note */
		$order->update_status( 'processing', sprintf( __( 'Free replacement for the lost package of order #%1$s. %2$s', 'atelier-irisee-master-plugin' ), $original->get_order_number(), $note ) );

		// The original: lost, and where its replacement is.
		$list   = array_filter( array_map( 'absint', (array) $original->get_meta( '_aimp_replaced_by' ) ) );
		$list[] = $order->get_id();
		$original->update_meta_data( '_aimp_replaced_by', array_values( array_unique( $list ) ) );
		$original->save_meta_data();
		/* translators: 1: order number, 2: note */
		$text = sprintf( __( 'Package lost; sent again with free replacement order #%1$s. %2$s', 'atelier-irisee-master-plugin' ), $order->get_order_number(), $note );
		if ( self::STATUS !== $original->get_status() ) {
			$original->update_status( self::STATUS, $text );
		} else {
			$original->add_order_note( $text );
		}
		return $order;
	}
}
