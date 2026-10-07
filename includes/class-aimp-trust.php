<?php
/**
 * Reassurance where customers decide (Baymard): the delivery line at "Add to cart" and in the cart, the
 * expected delivery date and a contact line after ordering, and a lighter checkout form (no company field
 * unless wanted, address line 2 and order notes behind a link, a clear "no account needed").
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Trust {

	public static function init() {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 20 );
		add_action( 'woocommerce_before_checkout_registration_form', array( __CLASS__, 'guest_note' ) );
		add_action( 'woocommerce_email_order_details', array( __CLASS__, 'email_block' ), 5, 4 );
	}

	/* ------------------------------------------------------------------
	 * Delivery line
	 * ------------------------------------------------------------------ */

	/**
	 * "Delivered in 2–4 working days", "Free shipping from €75", "14 days to return".
	 *
	 * @return string[]
	 */
	public static function delivery_items() {
		$items = array();
		$time  = trim( AIMP_Settings::localized( AIMP_Settings::get_text( 'delivery_time', AIMP_Settings::default_delivery_time() ) ) );
		if ( '' !== $time ) {
			/* translators: %s: delivery time, e.g. "2–4 working days" */
			$items[] = sprintf( __( 'Delivered in %s', 'atelier-irisee-master-plugin' ), $time );
		}
		if ( class_exists( 'AIMP_PostNL' ) ) {
			// "Free from" of PostNL in the zone of the shop's own country.
			$amounts = AIMP_PostNL::amounts_for( WC()->countries->get_base_country() );
			$free    = $amounts['free'];
			if ( '' !== $free && (float) $free > 0 ) {
				/* translators: %s: order amount */
				$items[] = sprintf( __( 'Free shipping from %s', 'atelier-irisee-master-plugin' ), wp_strip_all_tags( wc_price( (float) $free, array( 'decimals' => 0 ) ) ) );
			}
		}
		$days = (int) AIMP_Settings::get( 'return_days' );
		if ( $days > 0 ) {
			/* translators: %d: number of days */
			$items[] = sprintf( _n( '%d day to return', '%d days to return', $days, 'atelier-irisee-master-plugin' ), $days );
		}
		return $items;
	}

	/**
	 * From which stock "Only … left" shows for a product: the number set for its category, or for the
	 * nearest category above it, or else the general number (Shipping and delivery → Delivery and stock).
	 *
	 * @param WC_Product $product Product or size.
	 * @return int 0 = never.
	 */
	public static function low_stock_threshold( $product ) {
		$general  = (int) AIMP_Settings::get( 'low_stock' );
		$per_cat  = AIMP_Settings::low_stock_categories();
		$owner_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		if ( ! $per_cat ) {
			return $general;
		}
		$best = null;
		$step = PHP_INT_MAX;
		foreach ( wc_get_product_term_ids( $owner_id, 'product_cat' ) as $term_id ) {
			// The category itself, then its parents, nearest first.
			foreach ( array_merge( array( (int) $term_id ), array_map( 'intval', get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) ) as $distance => $id ) {
				if ( isset( $per_cat[ $id ] ) ) {
					if ( $distance < $step ) {
						$step = $distance;
						$best = $per_cat[ $id ];
					}
					break;
				}
			}
		}
		return null === $best ? $general : (int) $best;
	}

	/**
	 * @param string $class Extra CSS class.
	 * @return string
	 */
	public static function delivery_html( $class = '' ) {
		$items = self::delivery_items();
		if ( ! $items ) {
			return '';
		}
		$html = '';
		foreach ( $items as $item ) {
			$html .= '<li><span class="aimp-delivery-mark" aria-hidden="true">✦</span>' . esc_html( $item ) . '</li>';
		}
		return '<ul class="aimp-delivery-line ' . esc_attr( $class ) . '">' . $html . '</ul>';
	}

	/**
	 * Under the cart totals: the delivery line, the accepted payment methods and "Continue shopping".
	 *
	 * @return string
	 */
	public static function cart_html() {
		$logos = '';
		if ( class_exists( 'AIMP_Footer' ) ) {
			foreach ( array_filter( array_map( 'absint', explode( ',', (string) AIMP_Footer::get( 'payment_logos' ) ) ) ) as $logo ) {
				$url = wp_get_attachment_image_url( $logo, 'thumbnail' );
				if ( $url ) {
					$alt    = (string) get_post_meta( $logo, '_wp_attachment_image_alt', true );
					$logos .= '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
				}
			}
		}
		return '<div class="aimp-cart-trust">' . self::delivery_html() .
			( $logos ? '<div class="aimp-cart-payments" aria-label="' . esc_attr__( 'Payment methods', 'atelier-irisee-master-plugin' ) . '">' . $logos . '</div>' : '' ) .
			'<a class="aimp-cart-continue" href="' . esc_url( AIMP_Search::results_url() ) . '" data-aimp-continue>← ' . esc_html__( 'Continue shopping', 'atelier-irisee-master-plugin' ) . '</a></div>';
	}

	/* ------------------------------------------------------------------
	 * Expected delivery date
	 * ------------------------------------------------------------------ */

	/**
	 * Belgian public holidays of a year (Y-m-d).
	 *
	 * @param int $year Year.
	 * @return string[]
	 */
	private static function holidays( $year ) {
		// Easter Sunday (anonymous Gregorian algorithm).
		$a     = $year % 19;
		$b     = intdiv( $year, 100 );
		$c     = $year % 100;
		$d     = intdiv( $b, 4 );
		$e     = $b % 4;
		$f     = intdiv( $b + 8, 25 );
		$g     = intdiv( $b - $f + 1, 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intdiv( $c, 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		$easter = new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ) );
		$days   = array(
			"$year-01-01",
			$easter->modify( '+1 day' )->format( 'Y-m-d' ),  // Easter Monday.
			"$year-05-01",
			$easter->modify( '+39 days' )->format( 'Y-m-d' ), // Ascension.
			$easter->modify( '+50 days' )->format( 'Y-m-d' ), // Whit Monday.
			"$year-07-21",
			"$year-08-15",
			"$year-11-01",
			"$year-11-11",
			"$year-12-25",
		);
		return $days;
	}

	/**
	 * The order date plus the delivery days, counting working days only.
	 *
	 * @param WC_Order $order Order.
	 * @return string Formatted date, or ''.
	 */
	public static function expected_date( $order ) {
		$created = $order instanceof WC_Order ? $order->get_date_created() : null;
		return $created ? self::expected_date_from( $created->date( 'Y-m-d' ) ) : '';
	}

	/**
	 * A start date plus the delivery days, counting working days only (Belgian holidays skipped).
	 *
	 * @param string $start Y-m-d ('' = today).
	 * @return string Formatted date, or ''.
	 */
	public static function expected_date_from( $start = '' ) {
		$days = (int) AIMP_Settings::get( 'delivery_days' );
		if ( $days <= 0 ) {
			return '';
		}
		$date = new DateTimeImmutable( '' !== $start ? $start : current_time( 'Y-m-d' ) );
		while ( $days > 0 ) {
			$date = $date->modify( '+1 day' );
			if ( (int) $date->format( 'N' ) >= 6 || in_array( $date->format( 'Y-m-d' ), self::holidays( (int) $date->format( 'Y' ) ), true ) ) {
				continue;
			}
			--$days;
		}
		return date_i18n( get_option( 'date_format' ), $date->getTimestamp() );
	}

	/**
	 * "Questions? Reply to this email or call … / mail …".
	 *
	 * @return string
	 */
	public static function contact_text() {
		$ways = self::contact_ways();
		/* translators: %s: phone number and/or email address */
		return $ways ? sprintf( __( 'Questions? Reply to this email or contact us: %s', 'atelier-irisee-master-plugin' ), $ways ) : __( 'Questions? Simply reply to this email.', 'atelier-irisee-master-plugin' );
	}

	/**
	 * The same on the thank-you page: "Questions? Contact us: …", or '' without contact details.
	 *
	 * @return string
	 */
	public static function contact_page_text() {
		$ways = self::contact_ways();
		/* translators: %s: phone number and/or email address */
		return $ways ? sprintf( __( 'Questions? Contact us: %s', 'atelier-irisee-master-plugin' ), $ways ) : '';
	}

	/**
	 * Phone and email from the footer settings, "0470 … · info@…".
	 *
	 * @return string
	 */
	private static function contact_ways() {
		if ( ! class_exists( 'AIMP_Footer' ) ) {
			return '';
		}
		return implode( ' · ', array_filter( array( trim( (string) AIMP_Footer::get( 'phone' ) ), trim( (string) AIMP_Footer::get( 'email' ) ) ) ) );
	}

	/**
	 * Customer order emails: expected delivery (while the order is being prepared) and the contact line.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin To the shop.
	 * @param bool     $plain_text    Plain text.
	 * @param WC_Email $email         Email.
	 */
	public static function email_block( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( $sent_to_admin || ! $email instanceof WC_Email || ! in_array( $email->id, array( 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' ), true ) ) {
			return;
		}
		$date = 'customer_completed_order' === $email->id ? '' : self::expected_date( $order );
		$lines = array();
		if ( $date ) {
			/* translators: %s: date */
			$lines[] = sprintf( __( 'Expected delivery: %s', 'atelier-irisee-master-plugin' ), $date );
		}
		$lines[] = self::contact_text();
		if ( $plain_text ) {
			echo esc_html( implode( "\n", $lines ) ) . "\n\n";
			return;
		}
		echo '<div style="margin:0 0 20px;padding:12px 16px;border:1px solid #b38f4f;border-radius:16px">';
		foreach ( $lines as $i => $line ) {
			printf( '<p style="margin:%1$s">%2$s</p>', 0 === $i && $date ? '0 0 6px;font-weight:bold' : '0', esc_html( $line ) );
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * Checkout fields
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public static function checkout_fields( $fields ) {
		if ( ! AIMP_Settings::get( 'checkout_company' ) ) {
			unset( $fields['billing']['billing_company'], $fields['shipping']['shipping_company'] );
		}
		// Shown behind a "+ Add …" link by checkout.js.
		foreach ( array( 'billing' => 'billing_address_2', 'shipping' => 'shipping_address_2' ) as $group => $key ) {
			if ( isset( $fields[ $group ][ $key ] ) ) {
				$fields[ $group ][ $key ]['class'][] = 'aimp-behind-link';
			}
		}
		if ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['class'][] = 'aimp-behind-link';
		}
		return $fields;
	}

	public static function guest_note() {
		if ( is_user_logged_in() || 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
			return;
		}
		echo '<p class="aimp-guest-note">' . esc_html__( 'No account needed — you can also order as a guest.', 'atelier-irisee-master-plugin' ) . '</p>';
	}
}
