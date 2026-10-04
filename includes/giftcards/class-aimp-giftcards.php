<?php
/**
 * Gift cards: storage, settings, codes, balance ledger and emails.
 *
 * Each gift card is a private post of type aimp_gift_card (title = code). Its value lives in post meta,
 * and every change of the balance is written to a ledger, so you can always see what happened.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Giftcards {

	const POST_TYPE = 'aimp_gift_card';
	const OPTION    = 'aimp_giftcards';
	const ALPHABET  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No 0/O and 1/I, which are easy to confuse.

	/** Lowest gift card value, and the steps the amount goes up in on the product page. */
	const MIN_AMOUNT  = 10;
	const AMOUNT_STEP = 5;

	/**
	 * Amount limits for the ‹ › amount chooser: [ min, max ], both multiples of AMOUNT_STEP.
	 *
	 * @return float[]
	 */
	public static function amount_range() {
		$step = self::AMOUNT_STEP;
		$min  = max( self::MIN_AMOUNT, ceil( (float) self::opt( 'min_amount' ) / $step ) * $step );
		$max  = max( $min, floor( (float) self::opt( 'max_amount' ) / $step ) * $step );
		return array( (float) $min, (float) $max );
	}

	public static function init() {
		$dir = AIMP_PLUGIN_DIR . 'includes/giftcards/';
		require_once $dir . 'class-aimp-giftcards-product.php';
		require_once $dir . 'class-aimp-giftcards-redeem.php';
		require_once $dir . 'class-aimp-giftcards-admin.php';

		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'aimp_gc_send', array( __CLASS__, 'send_scheduled' ) );

		AIMP_Giftcards_Product::init();
		AIMP_Giftcards_Redeem::init();
		AIMP_Giftcards_Admin::init();
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Gift cards', 'atelier-irisee-master-plugin' ),
					'singular_name'      => __( 'Gift card', 'atelier-irisee-master-plugin' ),
					'add_new'            => __( 'Add gift card', 'atelier-irisee-master-plugin' ),
					'add_new_item'       => __( 'Add gift card', 'atelier-irisee-master-plugin' ),
					'edit_item'          => __( 'Edit gift card', 'atelier-irisee-master-plugin' ),
					'search_items'       => __( 'Search gift cards', 'atelier-irisee-master-plugin' ),
					'not_found'          => __( 'No gift cards found.', 'atelier-irisee-master-plugin' ),
					'not_found_in_trash' => __( 'No gift cards found in the trash.', 'atelier-irisee-master-plugin' ),
					'all_items'          => __( 'Gift cards', 'atelier-irisee-master-plugin' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'woocommerce',
				'supports'        => false,
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_post'          => 'manage_woocommerce',
					'read_post'          => 'manage_woocommerce',
					'delete_post'        => 'manage_woocommerce',
					'edit_posts'         => 'manage_woocommerce',
					'edit_others_posts'  => 'manage_woocommerce',
					'publish_posts'      => 'manage_woocommerce',
					'read_private_posts' => 'manage_woocommerce',
					'delete_posts'       => 'manage_woocommerce',
					'create_posts'       => 'do_not_allow', // Manual cards are made via "Add gift card".
				),
				'map_meta_cap'    => false,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'designs'         => array(),
			'amounts'         => '25,50,75,100',
			'custom_amount'   => 1,
			'min_amount'      => 10,
			'max_amount'      => 500,
			'expiry_months'   => 12,
			'allow_recipient' => 1,
			'allow_send_date' => 1,
			'allow_post'      => 1,
			'post_fee'        => 2.5,
			'code_prefix'     => 'IRIS',
			'product_id'      => 0,
		);
	}

	public static function opt( $key ) {
		$options = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	/**
	 * Preset amounts as numbers.
	 *
	 * @return float[]
	 */
	public static function preset_amounts() {
		$list = array();
		foreach ( explode( ',', (string) self::opt( 'amounts' ) ) as $amount ) {
			$amount = (float) str_replace( ',', '.', trim( $amount ) );
			if ( $amount > 0 ) {
				$list[] = $amount;
			}
		}
		return array_values( array_unique( $list ) );
	}

	/**
	 * Active designs: id => [ name, image_id ].
	 *
	 * @param bool $all Also inactive designs.
	 * @return array
	 */
	public static function designs( $all = false ) {
		$designs = array();
		foreach ( (array) self::opt( 'designs' ) as $design ) {
			if ( empty( $design['id'] ) || ( ! $all && empty( $design['active'] ) ) ) {
				continue;
			}
			$designs[ $design['id'] ] = $design;
		}
		return $designs;
	}

	public static function design_image( $design_id, $size = 'large' ) {
		$designs = self::designs( true );
		$image   = isset( $designs[ $design_id ]['image_id'] ) ? wp_get_attachment_image_url( (int) $designs[ $design_id ]['image_id'], $size ) : '';
		return $image ? $image : wc_placeholder_img_src( $size );
	}

	public static function design_name( $design_id ) {
		$designs = self::designs( true );
		return isset( $designs[ $design_id ]['name'] ) ? $designs[ $design_id ]['name'] : '';
	}

	/* ------------------------------------------------------------------
	 * Codes
	 * ------------------------------------------------------------------ */

	public static function normalize_code( $code ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) $code ) );
	}

	/**
	 * A new unique code like IRIS-7KQ2-M9XD-4HBT.
	 *
	 * @return string
	 */
	public static function generate_code() {
		$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) self::opt( 'code_prefix' ) ) );
		$max    = strlen( self::ALPHABET ) - 1;
		do {
			$groups = array();
			for ( $g = 0; $g < 3; $g++ ) {
				$group = '';
				for ( $i = 0; $i < 4; $i++ ) {
					$group .= self::ALPHABET[ random_int( 0, $max ) ];
				}
				$groups[] = $group;
			}
			$code = ( $prefix ? $prefix . '-' : '' ) . implode( '-', $groups );
		} while ( self::find_by_code( $code ) );
		return $code;
	}

	/**
	 * Code with only the last group visible, e.g. IRIS-…-4HBT.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function mask( $code ) {
		$parts = explode( '-', $code );
		return count( $parts ) > 2 ? $parts[0] . '-…-' . end( $parts ) : $code;
	}

	public static function find_by_code( $code ) {
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'meta_key'       => '_aimp_gc_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/* ------------------------------------------------------------------
	 * Cards
	 * ------------------------------------------------------------------ */

	/**
	 * Create a gift card.
	 *
	 * @param array $args amount, design, delivery (email_self|email_other|post), purchaser, purchaser_email,
	 *                    sender_name, recipient_name, recipient_email, message, send_date, order_id, item_id, lang, note.
	 * @return int Gift card ID (0 on failure).
	 */
	public static function create( $args ) {
		$args   = wp_parse_args(
			$args,
			array(
				'amount'          => 0,
				'design'          => '',
				'delivery'        => 'email_self',
				'purchaser'       => 0,
				'purchaser_email' => '',
				'sender_name'     => '',
				'recipient_name'  => '',
				'recipient_email' => '',
				'message'         => '',
				'send_date'       => '',
				'order_id'        => 0,
				'item_id'         => 0,
				'lang'            => AIMP_I18n::current(),
				'note'            => '',
			)
		);
		$amount = round( (float) $args['amount'], wc_get_price_decimals() );
		if ( $amount <= 0 ) {
			return 0;
		}
		$code    = self::generate_code();
		$months  = (int) self::opt( 'expiry_months' );
		$expires = $months > 0 ? gmdate( 'Y-m-d', strtotime( '+' . $months . ' months', current_time( 'timestamp' ) ) ) : ''; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local date.

		$id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $code,
				// Emails in the content make the admin search find cards by email address.
				'post_content' => trim( $args['purchaser_email'] . ' ' . $args['recipient_email'] ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$meta = array(
			'_aimp_gc_code'            => $code,
			'_aimp_gc_amount'          => $amount,
			'_aimp_gc_balance'         => $amount,
			'_aimp_gc_currency'        => get_woocommerce_currency(),
			'_aimp_gc_design'          => sanitize_key( $args['design'] ),
			'_aimp_gc_delivery'        => $args['delivery'],
			'_aimp_gc_purchaser'       => (int) $args['purchaser'],
			'_aimp_gc_purchaser_email' => sanitize_email( $args['purchaser_email'] ),
			'_aimp_gc_sender_name'     => sanitize_text_field( $args['sender_name'] ),
			'_aimp_gc_recipient_name'  => sanitize_text_field( $args['recipient_name'] ),
			'_aimp_gc_recipient_email' => sanitize_email( $args['recipient_email'] ),
			'_aimp_gc_message'         => sanitize_textarea_field( $args['message'] ),
			'_aimp_gc_send_date'       => $args['send_date'],
			'_aimp_gc_order'           => (int) $args['order_id'],
			'_aimp_gc_item'            => (int) $args['item_id'],
			'_aimp_gc_status'          => 'active',
			'_aimp_gc_expires'         => $expires,
			'_aimp_gc_lang'            => AIMP_I18n::is_valid( $args['lang'] ) ? $args['lang'] : AIMP_I18n::default_language(),
			'_aimp_gc_shipped'         => 'post' === $args['delivery'] ? 0 : 1,
			'_aimp_gc_ledger'          => array(),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		self::log( $id, $amount, $args['note'] ? $args['note'] : __( 'Gift card created', 'atelier-irisee-master-plugin' ), (int) $args['order_id'] );
		return (int) $id;
	}

	/**
	 * All data of a gift card.
	 *
	 * @param int $id Gift card ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$m = function ( $key ) use ( $id ) {
			return get_post_meta( $id, '_aimp_gc_' . $key, true );
		};
		return array(
			'id'              => (int) $id,
			'code'            => (string) $m( 'code' ),
			'amount'          => (float) $m( 'amount' ),
			'balance'         => (float) $m( 'balance' ),
			'currency'        => (string) $m( 'currency' ),
			'design'          => (string) $m( 'design' ),
			'delivery'        => (string) $m( 'delivery' ),
			'purchaser'       => (int) $m( 'purchaser' ),
			'purchaser_email' => (string) $m( 'purchaser_email' ),
			'sender_name'     => (string) $m( 'sender_name' ),
			'recipient_name'  => (string) $m( 'recipient_name' ),
			'recipient_email' => (string) $m( 'recipient_email' ),
			'message'         => (string) $m( 'message' ),
			'send_date'       => (string) $m( 'send_date' ),
			'order_id'        => (int) $m( 'order' ),
			'status'          => (string) $m( 'status' ),
			'expires'         => (string) $m( 'expires' ),
			'lang'            => (string) $m( 'lang' ),
			'shipped'         => (bool) $m( 'shipped' ),
			'sent'            => (string) $m( 'sent' ),
			'ledger'          => (array) $m( 'ledger' ),
		);
	}

	public static function is_expired( $card ) {
		return $card['expires'] && $card['expires'] < current_time( 'Y-m-d' );
	}

	/**
	 * Why a card cannot be used right now, or '' when it can.
	 *
	 * @param array|null $card Card.
	 * @return string
	 */
	public static function unusable_reason( $card ) {
		if ( ! $card ) {
			return __( 'This gift card code does not exist. Please check it and try again.', 'atelier-irisee-master-plugin' );
		}
		if ( 'active' !== $card['status'] ) {
			return __( 'This gift card is no longer valid.', 'atelier-irisee-master-plugin' );
		}
		if ( self::is_expired( $card ) ) {
			return __( 'This gift card has expired.', 'atelier-irisee-master-plugin' );
		}
		if ( $card['balance'] <= 0 ) {
			return __( 'This gift card has been used up.', 'atelier-irisee-master-plugin' );
		}
		return '';
	}

	public static function status_label( $card ) {
		if ( 'active' !== $card['status'] ) {
			return __( 'Disabled', 'atelier-irisee-master-plugin' );
		}
		if ( self::is_expired( $card ) ) {
			return __( 'Expired', 'atelier-irisee-master-plugin' );
		}
		if ( $card['balance'] <= 0 ) {
			return __( 'Used up', 'atelier-irisee-master-plugin' );
		}
		if ( 'post' === $card['delivery'] && ! $card['shipped'] ) {
			return __( 'To send by post', 'atelier-irisee-master-plugin' );
		}
		return __( 'Active', 'atelier-irisee-master-plugin' );
	}

	public static function delivery_label( $delivery ) {
		$labels = array(
			'email_self'  => __( 'Email to the buyer', 'atelier-irisee-master-plugin' ),
			'email_other' => __( 'Email to someone else', 'atelier-irisee-master-plugin' ),
			'post'        => __( 'By post', 'atelier-irisee-master-plugin' ),
		);
		return isset( $labels[ $delivery ] ) ? $labels[ $delivery ] : $delivery;
	}

	/* ------------------------------------------------------------------
	 * Balance
	 * ------------------------------------------------------------------ */

	private static function log( $id, $change, $note, $order_id = 0 ) {
		$ledger   = (array) get_post_meta( $id, '_aimp_gc_ledger', true );
		$ledger[] = array(
			'date'    => current_time( 'mysql' ),
			'change'  => (float) $change,
			'balance' => (float) get_post_meta( $id, '_aimp_gc_balance', true ),
			'note'    => (string) $note,
			'order'   => (int) $order_id,
			'user'    => get_current_user_id(),
		);
		update_post_meta( $id, '_aimp_gc_ledger', $ledger );
	}

	/**
	 * Change the balance (negative = spend, positive = restore or top up). Never goes below 0.
	 *
	 * @param int    $id       Gift card.
	 * @param float  $change   Amount.
	 * @param string $note     Ledger note.
	 * @param int    $order_id Related order.
	 * @return float The amount actually changed.
	 */
	public static function adjust( $id, $change, $note, $order_id = 0 ) {
		$balance = (float) get_post_meta( $id, '_aimp_gc_balance', true );
		$new     = max( 0, round( $balance + (float) $change, wc_get_price_decimals() ) );
		update_post_meta( $id, '_aimp_gc_balance', $new );
		self::log( $id, $new - $balance, $note, $order_id );
		return $new - $balance;
	}

	public static function set_status( $id, $status, $note ) {
		update_post_meta( $id, '_aimp_gc_status', 'active' === $status ? 'active' : 'disabled' );
		self::log( $id, 0, $note );
	}

	/* ------------------------------------------------------------------
	 * Emails
	 * ------------------------------------------------------------------ */

	/**
	 * Send an HTML email in the WooCommerce design, in a given language.
	 *
	 * @param string   $to      Recipient.
	 * @param string   $lang    Language code.
	 * @param callable $builder Returns [ subject, html ] (runs in that language).
	 */
	public static function mail( $to, $lang, $builder ) {
		$lang = AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::default_language();
		AIMP_I18n::with_language(
			$lang,
			function () use ( $to, $builder ) {
				list( $subject, $html ) = call_user_func( $builder );
				if ( function_exists( 'WC' ) && WC()->mailer() ) {
					$mailer = WC()->mailer();
					$mailer->send( $to, $subject, $mailer->wrap_message( $subject, $html ), array( 'Content-Type: text/html; charset=UTF-8' ) );
				} else {
					wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
				}
			}
		);
	}

	/**
	 * Deliver a card: email now (or schedule it), or tell the shop to post it.
	 *
	 * @param int  $id    Gift card.
	 * @param bool $force Send now even if a send date is set (admin "Resend").
	 */
	public static function deliver( $id, $force = false ) {
		$card = self::get( $id );
		if ( ! $card ) {
			return;
		}
		if ( 'post' === $card['delivery'] ) {
			self::mail_admin_post( $card );
			return;
		}
		if ( ! $force && $card['send_date'] && strtotime( $card['send_date'] . ' 08:00:00' ) > current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local date.
			$when = strtotime( get_gmt_from_date( $card['send_date'] . ' 08:00:00' ) );
			wp_clear_scheduled_hook( 'aimp_gc_send', array( $id ) );
			wp_schedule_single_event( $when, 'aimp_gc_send', array( $id ) );
			return;
		}
		self::send_card( $card );
	}

	public static function send_scheduled( $id ) {
		$card = self::get( $id );
		if ( $card && ! $card['sent'] && 'active' === $card['status'] ) {
			self::send_card( $card );
		}
	}

	private static function send_card( $card ) {
		$to = 'email_other' === $card['delivery'] ? $card['recipient_email'] : $card['purchaser_email'];
		if ( ! is_email( $to ) ) {
			return;
		}
		self::mail(
			$to,
			$card['lang'],
			function () use ( $card ) {
				$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
				$subject = $card['sender_name'] && 'email_other' === $card['delivery']
					/* translators: 1: sender name, 2: shop name */
					? sprintf( __( '%1$s sent you a gift card for %2$s', 'atelier-irisee-master-plugin' ), $card['sender_name'], $site )
					/* translators: %s: shop name */
					: sprintf( __( 'Your gift card for %s', 'atelier-irisee-master-plugin' ), $site );
				return array( $subject, AIMP_Giftcards::card_html( $card, true ) );
			}
		);
		update_post_meta( $card['id'], '_aimp_gc_sent', current_time( 'mysql' ) );
		self::log( $card['id'], 0, __( 'Gift card emailed', 'atelier-irisee-master-plugin' ) . ' (' . $to . ')' );
	}

	private static function mail_admin_post( $card ) {
		self::mail(
			get_option( 'admin_email' ),
			AIMP_I18n::default_language(),
			function () use ( $card ) {
				/* translators: %s: gift card code */
				$subject = sprintf( __( 'Gift card to send by post: %s', 'atelier-irisee-master-plugin' ), $card['code'] );
				$order   = $card['order_id'] ? wc_get_order( $card['order_id'] ) : null;
				$html    = '<p>' . esc_html__( 'A customer bought a gift card that must be printed and sent by post. The shipping address is on the order.', 'atelier-irisee-master-plugin' ) . '</p>' .
					'<p><a href="' . esc_url( AIMP_Giftcards_Admin::print_url( $card['id'] ) ) . '">' . esc_html__( 'Print the gift card', 'atelier-irisee-master-plugin' ) . '</a>' .
					( $order ? ' · <a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Open the order', 'atelier-irisee-master-plugin' ) . '</a>' : '' ) . '</p>';
				return array( $subject, $html );
			}
		);
	}

	/**
	 * The gift card itself (for emails, the account page and printing).
	 *
	 * @param array $card       Card.
	 * @param bool  $with_shop  Show a "Visit the shop" link.
	 * @return string
	 */
	public static function card_html( $card, $with_shop = false ) {
		ob_start();
		$aimp_card      = $card;
		$aimp_with_shop = $with_shop;
		$template       = locate_template( 'atelier-irisee/giftcards/card.php' );
		include $template ? $template : AIMP_PLUGIN_DIR . 'templates/giftcards/card.php';
		return ob_get_clean();
	}
}
