<?php
/**
 * Track & trace link on orders: pasted in the order screen before the order is set to Completed, then
 * added to the "Completed order" email and shown with the order under My orders.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Tracking {

	const META  = '_aimp_tracking_url';
	const NONCE = 'aimp_tracking';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		// Before WooCommerce saves the status (priority 40), which sends the Completed email.
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save' ), 5 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'email_block' ), 5, 4 );
		add_action( 'woocommerce_order_details_before_order_table', array( __CLASS__, 'order_block' ) );
	}

	/**
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function url( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}
		$url = (string) $order->get_meta( self::META );
		// No link pasted: PostNL's track & trace once a PostNL label was made.
		if ( '' === $url && class_exists( 'AIMP_PostNL' ) ) {
			$url = AIMP_PostNL::tracking_url( $order );
		}
		return $url;
	}

	/**
	 * Only the pasted link (for the box on the order screen).
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private static function pasted_url( $order ) {
		return $order instanceof WC_Order ? (string) $order->get_meta( self::META ) : '';
	}

	/* ------------------------------------------------------------------
	 * Order screen
	 * ------------------------------------------------------------------ */

	public static function add_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'aimp-tracking', __( 'Track & trace', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_box' ), $screen, 'side', 'high' );
	}

	/**
	 * @param WP_Post|WC_Order $post_or_order Order (HPOS) or its post.
	 */
	public static function render_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		wp_nonce_field( self::NONCE, 'aimp_tracking_nonce' );
		printf(
			'<p><label for="aimp_tracking_url" class="screen-reader-text">%1$s</label><input type="url" id="aimp_tracking_url" name="aimp_tracking_url" value="%2$s" placeholder="%3$s" style="width:100%%"></p><p class="description">%4$s</p>',
			esc_html__( 'Track & trace link', 'atelier-irisee-master-plugin' ),
			esc_attr( self::pasted_url( $order ) ),
			esc_attr__( 'Paste the track & trace link', 'atelier-irisee-master-plugin' ),
			esc_html__( 'Added to the Completed order email when you set the order to Completed, and shown with the order in the customer account. Left empty: the PostNL track & trace is used once you made a PostNL label.', 'atelier-irisee-master-plugin' )
		);
		if ( self::url( $order ) ) {
			printf( '<p><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>', esc_url( self::url( $order ) ), esc_html__( 'Open the link', 'atelier-irisee-master-plugin' ) );
		}
	}

	/**
	 * @param int $order_id Order.
	 */
	public static function save( $order_id ) {
		if ( ! isset( $_POST['aimp_tracking_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aimp_tracking_nonce'] ) ), self::NONCE ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$url = isset( $_POST['aimp_tracking_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['aimp_tracking_url'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw.
		if ( '' === $url ) {
			$order->delete_meta_data( self::META );
		} else {
			$order->update_meta_data( self::META, $url );
		}
		$order->save_meta_data();
	}

	/* ------------------------------------------------------------------
	 * Email and account
	 * ------------------------------------------------------------------ */

	/**
	 * "Your order is on its way" with the link, in the Completed order email.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin To the shop.
	 * @param bool     $plain_text    Plain text email.
	 * @param WC_Email $email         Email.
	 */
	public static function email_block( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( $sent_to_admin || ! $email || 'customer_completed_order' !== $email->id ) {
			return;
		}
		$url = self::url( $order );
		if ( '' === $url ) {
			return;
		}
		if ( $plain_text ) {
			echo esc_html__( 'Your order is on its way! Follow your parcel:', 'atelier-irisee-master-plugin' ) . "\n" . esc_url_raw( $url ) . "\n\n";
			return;
		}
		printf(
			'<div style="margin:0 0 24px;padding:16px 20px 4px;border:1px solid #b38f4f;border-radius:20px;text-align:center"><p style="margin:0">%1$s</p>%2$s</div>',
			esc_html__( 'Your order is on its way! Follow your parcel:', 'atelier-irisee-master-plugin' ),
			AIMP_Emails::button( __( 'Track my parcel', 'atelier-irisee-master-plugin' ), $url ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button().
		);
	}

	/**
	 * My orders → order (and the thank-you page, once there is a link).
	 *
	 * @param WC_Order $order Order.
	 */
	public static function order_block( $order ) {
		$url = self::url( $order );
		if ( '' === $url ) {
			return;
		}
		printf(
			'<p class="aimp-tracking"><span>%1$s</span> <a class="aimp-button aimp-account-button" href="%2$s" target="_blank" rel="noopener">%3$s</a></p>',
			esc_html__( 'Your order is on its way! Follow your parcel:', 'atelier-irisee-master-plugin' ),
			esc_url( $url ),
			esc_html__( 'Track my parcel', 'atelier-irisee-master-plugin' )
		);
	}
}
