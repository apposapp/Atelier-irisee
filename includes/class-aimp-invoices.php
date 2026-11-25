<?php
/**
 * Invoices and credit notes (PDF), and the delivery note of a free replacement order.
 *
 * - An invoice number per year without gaps (2026-0001), given when a paid order becomes "Processing" or "Completed"
 *   (or when the shop manager makes the invoice). A refund gets a credit note from its own series (C2026-0001) that
 *   names the invoice it corrects.
 * - The PDF: the shop's logo and details with its VAT number, the customer, the lines (a sewing project kit as one
 *   block), shipping, discounts and gift cards, the totals with VAT per rate, and how it was paid. In the customer's
 *   language.
 * - Where: a "Invoice" box on the order (download / print, and a credit note per refund), the bulk action "Print
 *   invoices" in the order list, attached to the "Processing" and "Refunded" emails, and in My account.
 * - Settings: Atelier Irisee → Settings → Invoices.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Invoices {

	/** Options: [ year, next ] of the invoice and credit note series. */
	const COUNTER        = 'aimp_invoice_counter';
	const CREDIT_COUNTER = 'aimp_credit_counter';

	/** The shop's VAT number (Atelier Irisee → Settings → Invoices can change it). */
	const VAT_DEFAULT = 'BE0598.716.761';

	/** Page layout (mm). */
	const LEFT  = 18;
	const RIGHT = 192;
	const FOOT  = 272;

	/** Colours. */
	const INK  = '#3b2a14';
	const SOFT = '#7a6a55';
	const GOLD = '#b38f4f';
	const LINE = '#d9c9a8';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ), 30 );
		foreach ( array( 'processing', 'completed' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'on_paid' ), 5 );
		}
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_refund' ), 10, 2 );
		add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'attachments' ), 10, 4 );

		// The order screen and the order list (classic and HPOS).
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 30 );
		foreach ( array( 'bulk_actions-edit-shop_order', 'bulk_actions-woocommerce_page_wc-orders' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'bulk_action' ) );
		}
		foreach ( array( 'handle_bulk_actions-edit-shop_order', 'handle_bulk_actions-woocommerce_page_wc-orders' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
		add_action( 'admin_post_aimp_invoice', array( __CLASS__, 'admin_download' ) );

		// My account.
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'account_actions' ), 20, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'account_links' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'customer_download' ), 5 );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * A setting of the invoices, with its default.
	 *
	 * @param string $key vat, company, address, extra.
	 * @return string
	 */
	public static function setting( $key ) {
		$value = trim( AIMP_Settings::get_text( 'inv_' . $key ) );
		if ( '' !== $value ) {
			return $value;
		}
		switch ( $key ) {
			case 'vat':
				return self::VAT_DEFAULT;
			case 'company':
				return class_exists( 'AIMP_Footer' ) ? (string) AIMP_Footer::get( 'company' ) : get_bloginfo( 'name' );
			case 'address':
				// WooCommerce's shop address (street, postcode and town), else the footer's.
				$street = trim( get_option( 'woocommerce_store_address' ) . ' ' . get_option( 'woocommerce_store_address_2' ) );
				if ( '' !== $street ) {
					return trim( $street . "\n" . get_option( 'woocommerce_store_postcode' ) . ' ' . get_option( 'woocommerce_store_city' ) );
				}
				return class_exists( 'AIMP_Footer' ) ? (string) AIMP_Footer::get( 'address' ) : '';
		}
		return '';
	}

	public static function register_settings() {
		add_settings_section(
			'aimp_invoices',
			__( 'Invoices', 'atelier-irisee-master-plugin' ),
			function () {
				echo '<p>' . esc_html__( 'Invoices and credit notes (PDF) for your orders: on the order screen, in the order list ("Print invoices"), attached to the emails and in My account.', 'atelier-irisee-master-plugin' ) . '</p>';
			},
			AIMP_Settings::PAGE
		);
		add_settings_field( 'aimp_inv_company', __( 'Your details on the invoice', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_details' ), AIMP_Settings::PAGE, 'aimp_invoices', array( 'label_for' => 'aimp_inv_vat' ) );
		add_settings_field( 'aimp_inv_numbers', __( 'Numbers', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_numbers' ), AIMP_Settings::PAGE, 'aimp_invoices', array( 'label_for' => 'aimp_inv_next' ) );
	}

	public static function render_details() {
		$o = esc_attr( AIMP_Settings::OPTION );
		printf(
			'<p><label>%1$s<br><input type="text" id="aimp_inv_vat" class="regular-text" name="%2$s[inv_vat]" value="%3$s"></label></p>' .
			'<p><label>%4$s<br><input type="text" class="regular-text" name="%2$s[inv_company]" value="%5$s"></label></p>' .
			'<p><label>%6$s<br><textarea class="large-text" rows="3" name="%2$s[inv_address]">%7$s</textarea></label></p>' .
			'<p><label>%8$s<br><textarea class="large-text" rows="2" name="%2$s[inv_extra]" placeholder="IBAN BE00 0000 0000 0000 · BIC …">%9$s</textarea></label></p>',
			esc_html__( 'VAT number', 'atelier-irisee-master-plugin' ),
			$o, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_attr( self::setting( 'vat' ) ),
			esc_html__( 'Company name', 'atelier-irisee-master-plugin' ),
			esc_attr( self::setting( 'company' ) ),
			esc_html__( 'Address', 'atelier-irisee-master-plugin' ),
			esc_textarea( self::setting( 'address' ) ),
			esc_html__( 'Extra line at the bottom (bank account, …)', 'atelier-irisee-master-plugin' ),
			esc_textarea( self::setting( 'extra' ) )
		);
	}

	public static function render_numbers() {
		$year = (int) wp_date( 'Y' );
		printf(
			'<p><label>%1$s <input type="number" min="1" id="aimp_inv_next" class="small-text" name="%2$s[inv_next]" value="%3$d"></label> &nbsp; ' .
			'<label>%4$s <input type="number" min="1" class="small-text" name="%2$s[inv_credit_next]" value="%5$d"></label></p><p class="description">%6$s</p>',
			esc_html( sprintf( /* translators: %d: year */ __( 'Next invoice number in %d', 'atelier-irisee-master-plugin' ), $year ) ),
			esc_attr( AIMP_Settings::OPTION ),
			(int) self::peek( self::COUNTER ),
			esc_html__( 'next credit note number', 'atelier-irisee-master-plugin' ),
			(int) self::peek( self::CREDIT_COUNTER ),
			esc_html( sprintf( /* translators: 1: example invoice number, 2: example credit note number */ __( 'Invoices are numbered per year without gaps (%1$s), credit notes too (%2$s). Change the next number only to continue an existing series.', 'atelier-irisee-master-plugin' ), $year . '-0001', 'C' . $year . '-0001' ) )
		);
	}

	/**
	 * The settings of the invoices (AIMP_Settings::sanitize()); a changed next number is written to its series.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = array();
		$vat = isset( $input['inv_vat'] ) ? strtoupper( sanitize_text_field( wp_unslash( $input['inv_vat'] ) ) ) : '';
		$out['inv_vat']     = $vat === self::VAT_DEFAULT ? '' : $vat;
		$out['inv_company'] = isset( $input['inv_company'] ) ? sanitize_text_field( wp_unslash( $input['inv_company'] ) ) : '';
		$out['inv_address'] = isset( $input['inv_address'] ) ? sanitize_textarea_field( wp_unslash( $input['inv_address'] ) ) : '';
		$out['inv_extra']   = isset( $input['inv_extra'] ) ? sanitize_textarea_field( wp_unslash( $input['inv_extra'] ) ) : '';
		foreach ( array( 'inv_next' => self::COUNTER, 'inv_credit_next' => self::CREDIT_COUNTER ) as $key => $option ) {
			if ( isset( $input[ $key ] ) && absint( $input[ $key ] ) > 0 && absint( $input[ $key ] ) !== self::peek( $option ) ) {
				update_option(
					$option,
					array(
						'year' => (int) wp_date( 'Y' ),
						'next' => absint( $input[ $key ] ),
					),
					false
				);
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Numbers
	 * ------------------------------------------------------------------ */

	/**
	 * The next number of a series this year (not taken).
	 *
	 * @param string $option Series.
	 * @return int
	 */
	private static function peek( $option ) {
		wp_cache_delete( $option, 'options' );
		$counter = get_option( $option, array() );
		return is_array( $counter ) && isset( $counter['year'] ) && (int) $counter['year'] === (int) wp_date( 'Y' ) ? max( 1, (int) $counter['next'] ) : 1;
	}

	/**
	 * Takes the next number of a series (under a database lock, so two orders never get the same one).
	 *
	 * @param string $option Series.
	 * @param string $prefix '' or 'C'.
	 * @return string
	 */
	private static function take( $option, $prefix ) {
		global $wpdb;
		$lock = 'aimp_' . $option;
		$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$year = (int) wp_date( 'Y' );
		$next = self::peek( $option );
		update_option(
			$option,
			array(
				'year' => $year,
				'next' => $next + 1,
			),
			false
		);
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $prefix . $year . '-' . str_pad( (string) $next, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Whether an order gets an invoice: a sale (more than 0), not a free replacement.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function invoiceable( $order ) {
		return $order instanceof WC_Order && ! $order instanceof WC_Order_Refund && (float) $order->get_total() > 0 && ! $order->get_meta( '_aimp_replaces' );
	}

	/**
	 * The order's invoice number (given now when it has none yet).
	 *
	 * @param WC_Order $order Order.
	 * @return string '' for an order without invoice.
	 */
	public static function number( $order ) {
		if ( ! self::invoiceable( $order ) ) {
			return '';
		}
		$number = (string) $order->get_meta( '_aimp_invoice_number' );
		if ( '' === $number ) {
			$number = self::take( self::COUNTER, '' );
			$order->update_meta_data( '_aimp_invoice_number', $number );
			$order->update_meta_data( '_aimp_invoice_date', time() );
			$order->save_meta_data();
			/* translators: %s: invoice number */
			$order->add_order_note( sprintf( __( 'Invoice %s made.', 'atelier-irisee-master-plugin' ), $number ) );
		}
		return $number;
	}

	/**
	 * A refund's credit note number (given now when it has none; only when the order has an invoice).
	 *
	 * @param WC_Order_Refund $refund Refund.
	 * @return string
	 */
	public static function credit_number( $refund ) {
		$order = $refund ? wc_get_order( $refund->get_parent_id() ) : null;
		if ( ! $order || '' === (string) $order->get_meta( '_aimp_invoice_number' ) ) {
			return '';
		}
		$number = (string) $refund->get_meta( '_aimp_credit_number' );
		if ( '' === $number ) {
			$number = self::take( self::CREDIT_COUNTER, 'C' );
			$refund->update_meta_data( '_aimp_credit_number', $number );
			$refund->update_meta_data( '_aimp_credit_date', time() );
			$refund->save_meta_data();
			/* translators: 1: credit note number, 2: invoice number */
			$order->add_order_note( sprintf( __( 'Credit note %1$s made (for invoice %2$s).', 'atelier-irisee-master-plugin' ), $number, $order->get_meta( '_aimp_invoice_number' ) ) );
		}
		return $number;
	}

	/**
	 * @param int $order_id Order.
	 */
	public static function on_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && self::invoiceable( $order ) && ( $order->is_paid() || $order->get_date_paid() ) ) {
			self::number( $order );
		}
	}

	/**
	 * @param int $order_id  Order.
	 * @param int $refund_id Refund.
	 */
	public static function on_refund( $order_id, $refund_id ) {
		$refund = wc_get_order( $refund_id );
		if ( $refund instanceof WC_Order_Refund ) {
			self::credit_number( $refund );
		}
	}

	/* ------------------------------------------------------------------
	 * The PDF
	 * ------------------------------------------------------------------ */

	/**
	 * One or more documents in one PDF.
	 *
	 * @param array[] $docs [ kind (invoice|credit|delivery), order, refund or null ].
	 * @return string PDF.
	 */
	public static function pdf( $docs ) {
		$pdf  = new AIMP_Pdf( __( 'Invoice', 'atelier-irisee-master-plugin' ) );
		$logo = self::logo( $pdf );
		foreach ( $docs as $doc ) {
			list( $kind, $order, $refund ) = $doc;
			$lang = self::order_lang( $order );
			AIMP_I18n::with_language(
				$lang,
				function () use ( $pdf, $logo, $kind, $order, $refund ) {
					self::document( $pdf, $logo, $kind, $order, $refund );
				}
			);
		}
		return $pdf->output();
	}

	/**
	 * The customer's language (the order's), else the shop's.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private static function order_lang( $order ) {
		$lang = (string) $order->get_meta( '_aimp_lang' );
		return AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::default_language();
	}

	/**
	 * The logo as a picture of the PDF ('' without one).
	 *
	 * @param AIMP_Pdf $pdf PDF.
	 * @return string Key.
	 */
	private static function logo( $pdf ) {
		$url  = class_exists( 'AIMP_Header' ) ? AIMP_Header::logo_url() : '';
		$id   = '' !== $url ? (int) attachment_url_to_postid( $url ) : 0;
		$file = $id ? (string) get_attached_file( $id ) : '';
		if ( '' === $file && '' !== $url ) {
			$uploads = wp_get_upload_dir();
			$file    = 0 === strpos( $url, $uploads['baseurl'] ) ? $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) ) : '';
		}
		return '' !== $file ? $pdf->add_image( $file, 500 ) : '';
	}

	/**
	 * Money as the shop writes it, as plain text ("€ 12,50").
	 *
	 * @param float    $amount Amount.
	 * @param WC_Order $order  Order (currency).
	 * @return string
	 */
	private static function money( $amount, $order ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * VAT rate per tax rate id of the order: rate id => percent.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private static function rates( $order ) {
		$rates = array();
		foreach ( $order->get_items( 'tax' ) as $tax ) {
			$percent = method_exists( $tax, 'get_rate_percent' ) ? (float) $tax->get_rate_percent() : 0.0;
			if ( ! $percent && class_exists( 'WC_Tax' ) ) {
				$percent = (float) WC_Tax::get_rate_percent_value( $tax->get_rate_id() );
			}
			$rates[ (int) $tax->get_rate_id() ] = $percent;
		}
		return $rates;
	}

	/**
	 * The VAT rate of an item ("21%"), from its taxes.
	 *
	 * @param WC_Order_Item $item  Item.
	 * @param array         $rates rates().
	 * @return float
	 */
	private static function item_rate( $item, $rates ) {
		$taxes = method_exists( $item, 'get_taxes' ) ? $item->get_taxes() : array();
		foreach ( isset( $taxes['total'] ) ? (array) $taxes['total'] : array() as $rate_id => $amount ) {
			if ( '' !== $amount && isset( $rates[ (int) $rate_id ] ) ) {
				return $rates[ (int) $rate_id ];
			}
		}
		return 0.0;
	}

	/**
	 * The base (excl. VAT) and VAT per rate: percent => [ base, vat ].
	 *
	 * @param WC_Abstract_Order $order Order or refund.
	 * @param array             $rates rates() of the order.
	 * @return array
	 */
	private static function vat_split( $order, $rates ) {
		$split = array();
		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item ) {
			$taxes = $item->get_taxes();
			$total = (float) $item->get_total();
			$found = false;
			foreach ( isset( $taxes['total'] ) ? (array) $taxes['total'] : array() as $rate_id => $amount ) {
				if ( '' === $amount ) {
					continue;
				}
				$percent = isset( $rates[ (int) $rate_id ] ) ? $rates[ (int) $rate_id ] : 0.0;
				$key     = (string) $percent;
				if ( ! isset( $split[ $key ] ) ) {
					$split[ $key ] = array( 0.0, 0.0 );
				}
				if ( ! $found ) {
					$split[ $key ][0] += $total;
					$found             = true;
				}
				$split[ $key ][1] += (float) $amount;
			}
			if ( ! $found && 0.0 !== $total ) {
				if ( ! isset( $split['0'] ) ) {
					$split['0'] = array( 0.0, 0.0 );
				}
				$split['0'][0] += $total;
			}
		}
		krsort( $split, SORT_NUMERIC );
		return $split;
	}

	/**
	 * One document (invoice, credit note or delivery note) on new pages.
	 *
	 * @param AIMP_Pdf             $pdf    PDF.
	 * @param string               $logo   Logo key.
	 * @param string               $kind   invoice, credit or delivery.
	 * @param WC_Order             $order  Order.
	 * @param WC_Order_Refund|null $refund Refund (credit note).
	 */
	private static function document( $pdf, $logo, $kind, $order, $refund ) {
		$start = $pdf->page_count();
		$pdf->add_page();
		$rates = self::rates( $order );
		if ( 'credit' === $kind ) {
			$title  = __( 'Credit note', 'atelier-irisee-master-plugin' );
			$number = self::credit_number( $refund );
			$date   = (int) $refund->get_meta( '_aimp_credit_date' );
		} elseif ( 'delivery' === $kind ) {
			$title  = __( 'Delivery note', 'atelier-irisee-master-plugin' );
			$number = '';
			$date   = time();
		} else {
			$title  = __( 'Invoice', 'atelier-irisee-master-plugin' );
			$number = self::number( $order );
			$date   = (int) $order->get_meta( '_aimp_invoice_date' );
		}
		$date = $date ? $date : time();

		// The shop: logo, name, address, VAT number, contact.
		$y = 18;
		if ( '' !== $logo ) {
			$w = 30;
			$h = $w / max( 0.2, $pdf->image_ratio( $logo ) );
			if ( $h > 30 ) {
				$h = 30;
				$w = $h * $pdf->image_ratio( $logo );
			}
			$pdf->image( $logo, self::LEFT, $y, $w, $h );
		}
		$shop = array_filter( array_merge( preg_split( '/\r?\n/', self::setting( 'address' ) ), array( __( 'VAT', 'atelier-irisee-master-plugin' ) . ' ' . self::setting( 'vat' ) ) ) );
		if ( class_exists( 'AIMP_Footer' ) ) {
			$shop[] = trim( (string) AIMP_Footer::get( 'email' ) . ( '' !== (string) AIMP_Footer::get( 'phone' ) ? '  ·  ' . AIMP_Footer::get( 'phone' ) : '' ), ' ·' );
		}
		$pdf->text( self::RIGHT, $y + 4, self::setting( 'company' ), 'sans-bold', 12, self::INK, 'right' );
		$ty = $y + 9.5;
		foreach ( array_filter( array_map( 'trim', $shop ) ) as $line ) {
			$pdf->text( self::RIGHT, $ty, $line, 'sans', 8.5, self::SOFT, 'right' );
			$ty += 4.2;
		}

		// Title and numbers.
		$y = max( 58, $ty + 8 );
		$pdf->text( self::LEFT, $y, $title, 'sans-bold', 20, self::GOLD );
		if ( 'delivery' === $kind && $order->get_meta( '_aimp_replaces' ) ) {
			/* translators: %s: order number */
			$pdf->text( self::LEFT, $y + 6, sprintf( __( 'Replacement shipment for order %s', 'atelier-irisee-master-plugin' ), (string) $order->get_meta( '_aimp_replaces_number' ) ), 'sans', 9, self::SOFT );
		}
		$facts = array();
		if ( '' !== $number ) {
			$facts[] = array( 'credit' === $kind ? __( 'Credit note number', 'atelier-irisee-master-plugin' ) : __( 'Invoice number', 'atelier-irisee-master-plugin' ), $number );
		}
		$facts[] = array( __( 'Date', 'atelier-irisee-master-plugin' ), AIMP_I18n::date( $date ) );
		if ( 'credit' === $kind ) {
			$inv_date = (int) $order->get_meta( '_aimp_invoice_date' );
			$facts[]  = array( __( 'For invoice', 'atelier-irisee-master-plugin' ), (string) $order->get_meta( '_aimp_invoice_number' ) . ( $inv_date ? ' (' . AIMP_I18n::date( $inv_date ) . ')' : '' ) );
		}
		$facts[] = array( __( 'Order', 'atelier-irisee-master-plugin' ), '#' . $order->get_order_number() . ( $order->get_date_created() ? ' (' . AIMP_I18n::date( $order->get_date_created()->getTimestamp() ) . ')' : '' ) );
		$fy      = $y + 14;
		foreach ( $facts as $fact ) {
			$pdf->text( self::LEFT, $fy, $fact[0], 'sans', 9, self::SOFT );
			$pdf->text( self::LEFT + 34, $fy, $fact[1], 'sans-bold', 9, self::INK );
			$fy += 5;
		}

		// The customer (and where it goes, when that is another address).
		$billing  = self::address_lines( $order->get_formatted_billing_address() );
		$vat_id   = '';
		foreach ( array( '_billing_vat', '_billing_vat_number', 'billing_vat', '_vat_number', 'vat_number' ) as $meta ) {
			if ( '' !== (string) $order->get_meta( $meta ) ) {
				$vat_id = (string) $order->get_meta( $meta );
				break;
			}
		}
		if ( '' !== $vat_id ) {
			$billing[] = __( 'VAT', 'atelier-irisee-master-plugin' ) . ' ' . $vat_id;
		}
		$cx = 118;
		$pdf->text( $cx, $y + 14, 'delivery' === $kind ? __( 'Deliver to', 'atelier-irisee-master-plugin' ) : __( 'Customer', 'atelier-irisee-master-plugin' ), 'sans', 9, self::SOFT );
		$cy    = $y + 19;
		$lines = 'delivery' === $kind && $order->has_shipping_address() ? self::address_lines( $order->get_formatted_shipping_address() ) : $billing;
		foreach ( $lines as $i => $line ) {
			$pdf->text( $cx, $cy, $line, 0 === $i ? 'sans-bold' : 'sans', 9.5, self::INK );
			$cy += 4.6;
		}
		if ( 'delivery' !== $kind && $order->has_shipping_address() && $order->get_formatted_shipping_address() !== $order->get_formatted_billing_address() ) {
			$cy += 2;
			$pdf->text( $cx, $cy, __( 'Delivery address', 'atelier-irisee-master-plugin' ), 'sans', 8.5, self::SOFT );
			$cy += 4.4;
			foreach ( self::address_lines( $order->get_formatted_shipping_address() ) as $line ) {
				$pdf->text( $cx, $cy, $line, 'sans', 8.5, self::INK );
				$cy += 4.2;
			}
		}

		// The lines.
		$y = max( $fy, $cy ) + 8;
		$y = self::table( $pdf, $kind, $order, $refund, $rates, $y );

		// The totals.
		if ( 'delivery' !== $kind ) {
			$y = self::totals( $pdf, $kind, $order, $refund, $rates, $y + 2 );
		}

		// The bottom of every page of this document: the shop's extra line and the page number.
		self::footer( $pdf, $start );
	}

	/**
	 * Address HTML (WooCommerce) as lines.
	 *
	 * @param string $html Address.
	 * @return string[]
	 */
	private static function address_lines( $html ) {
		$lines = preg_split( '#<br\s*/?>#i', (string) $html );
		return array_values( array_filter( array_map( 'trim', array_map( 'wp_strip_all_tags', array_map( 'html_entity_decode', $lines ) ) ) ) );
	}

	/**
	 * A new page when the next $need mm do not fit; returns the y to write at.
	 *
	 * @param AIMP_Pdf $pdf  PDF.
	 * @param float    $y    Current y.
	 * @param float    $need Height needed.
	 * @return float
	 */
	private static function room( $pdf, $y, $need ) {
		if ( $y + $need <= self::FOOT - 8 ) {
			return $y;
		}
		$pdf->add_page();
		return 22;
	}

	/**
	 * The table of lines; returns the y below it.
	 *
	 * @return float
	 */
	private static function table( $pdf, $kind, $order, $refund, $rates, $y ) {
		$delivery = 'delivery' === $kind;
		$cols     = $delivery ? array( 'qty' => self::RIGHT ) : array(
			'qty'   => 118,
			'unit'  => 142,
			'vat'   => 160,
			'total' => self::RIGHT,
		);
		$head = function ( $y ) use ( $pdf, $cols, $delivery ) {
			$pdf->fill( '#f6f0e4' );
			$pdf->rect( self::LEFT, $y - 5, self::RIGHT - self::LEFT, 8, 'f' );
			$pdf->text( self::LEFT + 2, $y, __( 'Product', 'atelier-irisee-master-plugin' ), 'sans-bold', 8.5, self::INK );
			$pdf->text( $cols['qty'] - ( $delivery ? 2 : 0 ), $y, __( 'Qty', 'atelier-irisee-master-plugin' ), 'sans-bold', 8.5, self::INK, 'right' );
			if ( ! $delivery ) {
				$pdf->text( $cols['unit'], $y, __( 'Price', 'atelier-irisee-master-plugin' ), 'sans-bold', 8.5, self::INK, 'right' );
				$pdf->text( $cols['vat'], $y, __( 'VAT', 'atelier-irisee-master-plugin' ), 'sans-bold', 8.5, self::INK, 'right' );
				$pdf->text( $cols['total'] - 2, $y, __( 'Total', 'atelier-irisee-master-plugin' ), 'sans-bold', 8.5, self::INK, 'right' );
			}
			return $y + 7;
		};
		$y    = $head( $y );
		$sign = 'credit' === $kind ? -1 : 1;
		$rows = self::rows( $order, $refund, $kind );
		if ( 'credit' === $kind && ! $rows ) {
			// A refund of an amount only: one line.
			$amount = abs( (float) $refund->get_amount() );
			$reason = trim( (string) $refund->get_reason() );
			$rows[] = array(
				'name'  => __( 'Refund', 'atelier-irisee-master-plugin' ) . ( '' !== $reason ? ': ' . $reason : '' ),
				'qty'   => '',
				'unit'  => null,
				'rate'  => null,
				'total' => $amount,
				'sub'   => false,
				'head'  => false,
			);
		}
		$width = ( $delivery ? $cols['qty'] - 16 : $cols['qty'] - 12 ) - self::LEFT - 2;
		foreach ( $rows as $row ) {
			$indent = $row['sub'] ? 5 : 0;
			$lines  = $pdf->wrap( $row['name'], $row['head'] ? 'sans-bold' : 'sans', 9, $width - $indent );
			$height = count( $lines ) * 4.3 + 2.4;
			$before = $y;
			$y      = self::room( $pdf, $y, $height );
			if ( $y < $before ) {
				$y = $head( $y );
			}
			$ly = $y;
			foreach ( $lines as $line ) {
				$pdf->text( self::LEFT + 2 + $indent, $ly, $line, $row['head'] ? 'sans-bold' : 'sans', 9, $row['sub'] ? self::SOFT : self::INK );
				$ly += 4.3;
			}
			if ( '' !== (string) $row['qty'] ) {
				$pdf->text( $cols['qty'] - ( $delivery ? 2 : 0 ), $y, (string) $row['qty'], 'sans', 9, self::INK, 'right' );
			}
			if ( ! $delivery ) {
				if ( null !== $row['unit'] ) {
					$pdf->text( $cols['unit'], $y, self::money( $sign * $row['unit'], $order ), 'sans', 9, self::INK, 'right' );
				}
				if ( null !== $row['rate'] ) {
					$pdf->text( $cols['vat'], $y, rtrim( rtrim( number_format( $row['rate'], 2, ',', '' ), '0' ), ',' ) . '%', 'sans', 9, self::SOFT, 'right' );
				}
				if ( null !== $row['total'] ) {
					$pdf->text( $cols['total'] - 2, $y, self::money( $sign * $row['total'], $order ), $row['head'] ? 'sans-bold' : 'sans', 9, self::INK, 'right' );
				}
			}
			$y += $height;
			$pdf->stroke( self::LINE, 0.2 );
			$pdf->line( self::LEFT, $y - 3.6, self::RIGHT, $y - 3.6 );
		}
		return $y;
	}

	/**
	 * The lines of a document: a kit as a heading with its parts under it, other products as they are.
	 *
	 * @return array[] [ name, qty, unit (incl. VAT) or null, rate or null, total (incl. VAT) or null, sub, head ].
	 */
	private static function rows( $order, $refund, $kind ) {
		$rates  = self::rates( $order );
		$source = 'credit' === $kind ? $refund : $order;
		$items  = array();
		foreach ( $source->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$items[ $item->get_id() ] = $item;
			}
		}
		$rows = array();
		$before_coupon = 'credit' !== $kind;
		$line          = function ( $item, $sub ) use ( $rates, $before_coupon ) {
			$qty    = abs( (float) $item->get_quantity() );
			$total  = $before_coupon ? abs( (float) $item->get_subtotal() + (float) $item->get_subtotal_tax() ) : abs( (float) $item->get_total() + (float) $item->get_total_tax() );
			$length = (string) $item->get_meta( 'aimp_length' );
			$name   = wp_strip_all_tags( $item->get_name() ) . ( '' !== $length ? ' – ' . $length : '' );
			return array(
				'name'  => $name,
				'qty'   => $qty ? wc_stock_amount( $qty ) : '',
				'unit'  => $qty ? $total / $qty : $total,
				'rate'  => self::item_rate( $item, $rates ),
				'total' => $total,
				'sub'   => $sub,
				'head'  => false,
			);
		};
		// Kits (the order's own grouping; a refund's lines point to the order's lines they refund).
		$done = array();
		if ( class_exists( 'AIMP_Orders' ) ) {
			foreach ( AIMP_Orders::kits( $order ) as $kit ) {
				$parts = array();
				foreach ( $kit['items'] as $part ) {
					$id = $part['item']->get_id();
					if ( 'credit' === $kind ) {
						foreach ( $items as $refund_item ) {
							if ( (int) $refund_item->get_meta( '_refunded_item_id' ) === (int) $id ) {
								$parts[] = $refund_item;
								$done[]  = $refund_item->get_id();
							}
						}
					} elseif ( isset( $items[ $id ] ) ) {
						$parts[] = $items[ $id ];
						$done[]  = $id;
					}
				}
				if ( ! $parts ) {
					continue;
				}
				$sum = 0.0;
				foreach ( $parts as $part ) {
					$sum += 'credit' !== $kind ? abs( (float) $part->get_subtotal() + (float) $part->get_subtotal_tax() ) : abs( (float) $part->get_total() + (float) $part->get_total_tax() );
				}
				$rows[] = array(
					/* translators: %s: kit name */
					'name'  => sprintf( __( 'Sewing project kit: %s', 'atelier-irisee-master-plugin' ), $kit['label'] ),
					'qty'   => '',
					'unit'  => null,
					'rate'  => null,
					'total' => 'delivery' === $kind ? null : $sum,
					'sub'   => false,
					'head'  => true,
				);
				foreach ( $parts as $part ) {
					$rows[] = $line( $part, true );
				}
			}
		}
		foreach ( $items as $id => $item ) {
			if ( ! in_array( $id, $done, true ) ) {
				$rows[] = $line( $item, false );
			}
		}
		return $rows;
	}

	/**
	 * Shipping, fees (gift cards), discounts, the totals and VAT per rate, and the payment; returns the y below.
	 *
	 * @return float
	 */
	private static function totals( $pdf, $kind, $order, $refund, $rates, $y ) {
		$doc   = 'credit' === $kind ? $refund : $order;
		$sign  = 'credit' === $kind ? -1 : 1;
		$lines = array();
		foreach ( $doc->get_items( 'shipping' ) as $ship ) {
			$amount = abs( (float) $ship->get_total() + (float) $ship->get_total_tax() );
			if ( 'credit' !== $kind || $amount > 0 ) {
				$lines[] = array( __( 'Shipping', 'atelier-irisee-master-plugin' ) . ': ' . wp_strip_all_tags( $ship->get_name() ), $sign * $amount );
			}
		}
		foreach ( $doc->get_items( 'fee' ) as $fee ) {
			$lines[] = array( wp_strip_all_tags( $fee->get_name() ), ( 'credit' === $kind ? -abs( (float) $fee->get_total() + (float) $fee->get_total_tax() ) : (float) $fee->get_total() + (float) $fee->get_total_tax() ) );
		}
		if ( 'credit' !== $kind && (float) $order->get_discount_total() > 0 ) {
			$lines[] = array( __( 'Discount', 'atelier-irisee-master-plugin' ) . ( $order->get_coupon_codes() ? ' (' . implode( ', ', $order->get_coupon_codes() ) . ')' : '' ), -( (float) $order->get_discount_total() + (float) $order->get_discount_tax() ) );
		}
		$total = 'credit' === $kind ? -abs( (float) $refund->get_amount() ) : (float) $order->get_total();
		$tax   = 'credit' === $kind ? -abs( (float) $refund->get_total_tax() ) : (float) $order->get_total_tax();
		$split = self::vat_split( $doc, $rates );
		// A refund of an amount without VAT lines: its VAT as in the order (same rates, in proportion).
		if ( 'credit' === $kind && 0.0 === $tax && (float) $order->get_total_tax() > 0 && (float) $order->get_total() > 0 ) {
			$share = abs( $total ) / (float) $order->get_total();
			$split = array();
			foreach ( self::vat_split( $order, $rates ) as $rate => $pair ) {
				$split[ $rate ] = array( -$pair[0] * $share, -$pair[1] * $share );
			}
			$tax = 0.0;
			foreach ( $split as $pair ) {
				$tax += $pair[1];
			}
		}
		$x1  = 120;
		$put = function ( $label, $value, $bold = false ) use ( $pdf, &$y, $x1, $order ) {
			$y = self::room( $pdf, $y, 6 );
			$pdf->text( $x1, $y, $label, $bold ? 'sans-bold' : 'sans', $bold ? 10.5 : 9, $bold ? self::INK : self::SOFT );
			$pdf->text( self::RIGHT - 2, $y, self::money( $value, $order ), $bold ? 'sans-bold' : 'sans', $bold ? 10.5 : 9, self::INK, 'right' );
			$y += $bold ? 7 : 5;
		};
		$y += 2;
		foreach ( $lines as $line ) {
			$put( $line[0], $line[1] );
		}
		$put( __( 'Total excl. VAT', 'atelier-irisee-master-plugin' ), $total - $tax );
		foreach ( $split as $rate => $pair ) {
			/* translators: 1: VAT rate, 2: amount the VAT is on */
			$put( sprintf( __( 'VAT %1$s%% on %2$s', 'atelier-irisee-master-plugin' ), rtrim( rtrim( number_format( (float) $rate, 2, ',', '' ), '0' ), ',' ), self::money( $pair[0], $order ) ), $pair[1] );
		}
		$pdf->stroke( self::GOLD, 0.4 );
		$pdf->line( $x1, $y - 2.5, self::RIGHT, $y - 2.5 );
		$y += 2;
		$put( __( 'Total incl. VAT', 'atelier-irisee-master-plugin' ), $total, true );

		// How it was paid, or why it is credited.
		$y    = self::room( $pdf, $y + 4, 12 );
		$note = array();
		if ( 'credit' === $kind ) {
			$reason = trim( (string) $refund->get_reason() );
			if ( '' !== $reason ) {
				$note[] = __( 'Reason', 'atelier-irisee-master-plugin' ) . ': ' . $reason;
			}
			/* translators: %s: payment method */
			$note[] = sprintf( __( 'Refunded via %s.', 'atelier-irisee-master-plugin' ), $order->get_payment_method_title() );
		} elseif ( $order->get_date_paid() ) {
			/* translators: 1: payment method, 2: date */
			$note[] = sprintf( __( 'Paid via %1$s on %2$s.', 'atelier-irisee-master-plugin' ), $order->get_payment_method_title(), AIMP_I18n::date( $order->get_date_paid()->getTimestamp() ) );
		} elseif ( '' !== $order->get_payment_method_title() ) {
			/* translators: %s: payment method */
			$note[] = sprintf( __( 'Payment: %s.', 'atelier-irisee-master-plugin' ), $order->get_payment_method_title() );
		}
		foreach ( $note as $line ) {
			foreach ( $pdf->wrap( $line, 'sans', 9, self::RIGHT - self::LEFT ) as $part ) {
				$pdf->text( self::LEFT, $y, $part, 'sans', 9, self::SOFT );
				$y += 4.5;
			}
		}
		return $y;
	}

	/**
	 * The bottom of the pages of the document just written: the extra line and "page x / y".
	 *
	 * @param AIMP_Pdf $pdf  PDF.
	 * @param int      $from The document's first page (0 = the PDF's first).
	 */
	private static function footer( $pdf, $from ) {
		$count = $pdf->page_count();
		$extra = trim( preg_replace( '/\s*\r?\n\s*/', '  ·  ', self::setting( 'extra' ) ) );
		$base  = self::setting( 'company' ) . '  ·  ' . __( 'VAT', 'atelier-irisee-master-plugin' ) . ' ' . self::setting( 'vat' );
		for ( $i = $from; $i < $count; $i++ ) {
			$pdf->on_page( $i );
			$pdf->stroke( self::LINE, 0.2 );
			$pdf->line( self::LEFT, self::FOOT, self::RIGHT, self::FOOT );
			$pdf->text( self::LEFT, self::FOOT + 5, $base . ( '' !== $extra ? '  ·  ' . $extra : '' ), 'sans', 7.5, self::SOFT );
			if ( $count - $from > 1 ) {
				$pdf->text( self::RIGHT, self::FOOT + 5, ( $i - $from + 1 ) . ' / ' . ( $count - $from ), 'sans', 7.5, self::SOFT, 'right' );
			}
		}
		$pdf->on_page( $count - 1 );
	}

	/* ------------------------------------------------------------------
	 * What an order has: [ kind, order, refund ] per document
	 * ------------------------------------------------------------------ */

	/**
	 * @param WC_Order $order         Order.
	 * @param bool     $with_credits  The credit notes too.
	 * @return array[]
	 */
	public static function docs( $order, $with_credits = true ) {
		if ( $order->get_meta( '_aimp_replaces' ) ) {
			return array( array( 'delivery', $order, null ) );
		}
		if ( ! self::invoiceable( $order ) ) {
			return array();
		}
		$docs = array( array( 'invoice', $order, null ) );
		if ( $with_credits ) {
			foreach ( array_reverse( $order->get_refunds() ) as $refund ) {
				$docs[] = array( 'credit', $order, $refund );
			}
		}
		return $docs;
	}

	/**
	 * Sends a PDF to the browser (inline: it opens to print) and stops.
	 *
	 * @param string $pdf  PDF.
	 * @param string $name File name.
	 */
	private static function send( $pdf, $name ) {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a PDF file.
		exit;
	}

	/**
	 * A file name: factuur-2026-0001.pdf.
	 *
	 * @param array $doc [ kind, order, refund ].
	 * @return string
	 */
	private static function file_name( $doc ) {
		list( $kind, $order, $refund ) = $doc;
		if ( 'credit' === $kind ) {
			return __( 'credit-note', 'atelier-irisee-master-plugin' ) . '-' . self::credit_number( $refund ) . '.pdf';
		}
		if ( 'delivery' === $kind ) {
			return __( 'delivery-note', 'atelier-irisee-master-plugin' ) . '-' . $order->get_order_number() . '.pdf';
		}
		return __( 'invoice', 'atelier-irisee-master-plugin' ) . '-' . self::number( $order ) . '.pdf';
	}

	/* ------------------------------------------------------------------
	 * The order screen and the order list
	 * ------------------------------------------------------------------ */

	public static function meta_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'aimp-invoice', __( 'Invoice', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_box' ), $screen, 'side', 'high' );
	}

	/**
	 * A download address in the dashboard.
	 *
	 * @param int[]  $ids    Orders.
	 * @param string $which  all, invoice, delivery or credit:<refund id>.
	 * @return string
	 */
	public static function admin_url( $ids, $which = 'all' ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=aimp_invoice&orders=' . implode( ',', array_map( 'absint', (array) $ids ) ) . '&which=' . rawurlencode( $which ) ), 'aimp_invoice' );
	}

	/**
	 * @param WP_Post|WC_Order $object Order.
	 */
	public static function render_box( $object ) {
		$order = $object instanceof WC_Order ? $object : wc_get_order( $object->ID );
		if ( ! $order ) {
			return;
		}
		if ( $order->get_meta( '_aimp_replaces' ) ) {
			echo '<p>' . esc_html__( 'A free replacement order has no invoice; print its delivery note for the package.', 'atelier-irisee-master-plugin' ) . '</p>';
			echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( self::admin_url( array( $order->get_id() ), 'delivery' ) ) . '">' . esc_html__( 'Delivery note', 'atelier-irisee-master-plugin' ) . '</a></p>';
			return;
		}
		if ( ! self::invoiceable( $order ) ) {
			echo '<p>' . esc_html__( 'No invoice for an order of 0.', 'atelier-irisee-master-plugin' ) . '</p>';
			return;
		}
		$number = (string) $order->get_meta( '_aimp_invoice_number' );
		if ( '' !== $number ) {
			$date = (int) $order->get_meta( '_aimp_invoice_date' );
			/* translators: 1: invoice number, 2: date */
			echo '<p>' . esc_html( sprintf( __( 'Invoice %1$s of %2$s', 'atelier-irisee-master-plugin' ), $number, $date ? AIMP_I18n::date( $date ) : '' ) ) . '</p>';
			echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( self::admin_url( array( $order->get_id() ), 'invoice' ) ) . '">' . esc_html__( 'Download / print', 'atelier-irisee-master-plugin' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'The invoice gets its number when the order is paid, or now:', 'atelier-irisee-master-plugin' ) . '</p>';
			echo '<p><a class="button" target="_blank" href="' . esc_url( self::admin_url( array( $order->get_id() ), 'invoice' ) ) . '">' . esc_html__( 'Make the invoice', 'atelier-irisee-master-plugin' ) . '</a></p>';
		}
		foreach ( $order->get_refunds() as $refund ) {
			$credit = (string) $refund->get_meta( '_aimp_credit_number' );
			if ( '' === $credit && '' === $number ) {
				continue;
			}
			/* translators: 1: credit note number, 2: amount */
			$label = '' !== $credit ? sprintf( __( 'Credit note %1$s (%2$s)', 'atelier-irisee-master-plugin' ), $credit, self::money( (float) $refund->get_amount(), $order ) ) : __( 'Make the credit note', 'atelier-irisee-master-plugin' );
			echo '<p><a class="button" target="_blank" href="' . esc_url( self::admin_url( array( $order->get_id() ), 'credit:' . $refund->get_id() ) ) . '">' . esc_html( $label ) . '</a></p>';
		}
	}

	/**
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public static function bulk_action( $actions ) {
		$actions['aimp_print_invoices'] = __( 'Print invoices', 'atelier-irisee-master-plugin' );
		return $actions;
	}

	/**
	 * @param string $redirect Where to go.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Orders.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'aimp_print_invoices' !== $action || ! $ids ) {
			return $redirect;
		}
		return self::admin_url( $ids, 'all' );
	}

	/**
	 * The dashboard's download: one order (its invoice, a credit note or the delivery note) or several (everything
	 * of each, one PDF).
	 */
	public static function admin_download() {
		if ( ! current_user_can( 'edit_shop_orders' ) || ! check_admin_referer( 'aimp_invoice' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked above.
		$ids   = isset( $_GET['orders'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['orders'] ) ) ) ) ) : array();
		$which = isset( $_GET['which'] ) ? sanitize_text_field( wp_unslash( $_GET['which'] ) ) : 'all';
		// phpcs:enable
		$docs = array();
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order || $order instanceof WC_Order_Refund ) {
				continue;
			}
			if ( 0 === strpos( $which, 'credit:' ) ) {
				$refund = wc_get_order( absint( substr( $which, 7 ) ) );
				if ( $refund instanceof WC_Order_Refund && (int) $refund->get_parent_id() === (int) $order->get_id() && '' !== self::number( $order ) ) {
					$docs[] = array( 'credit', $order, $refund );
				}
			} elseif ( 'invoice' === $which ) {
				$docs = array_merge( $docs, array_slice( self::docs( $order, false ), 0, 1 ) );
			} elseif ( 'delivery' === $which ) {
				$docs[] = array( 'delivery', $order, null );
			} else {
				$docs = array_merge( $docs, self::docs( $order ) );
			}
		}
		if ( ! $docs ) {
			wp_die( esc_html__( 'There is no invoice for these orders (an order of 0 has none).', 'atelier-irisee-master-plugin' ) );
		}
		$name = 1 === count( $docs ) ? self::file_name( $docs[0] ) : __( 'invoices', 'atelier-irisee-master-plugin' ) . '-' . wp_date( 'Y-m-d' ) . '.pdf';
		self::send( self::pdf( $docs ), $name );
	}

	/* ------------------------------------------------------------------
	 * Emails
	 * ------------------------------------------------------------------ */

	/**
	 * The invoice with the "Processing" email (or "Completed" when there was no "Processing"), the credit note with
	 * the "Refunded" email.
	 *
	 * @param array  $attachments Files.
	 * @param string $email_id    Email.
	 * @param mixed  $object      Order.
	 * @return array
	 */
	public static function attachments( $attachments, $email_id, $object ) {
		$order = $object instanceof WC_Order && ! $object instanceof WC_Order_Refund ? $object : null;
		if ( ! $order || ! in_array( $email_id, array( 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_invoice' ), true ) ) {
			return $attachments;
		}
		$doc = null;
		if ( 'customer_refunded_order' === $email_id ) {
			$refunds = $order->get_refunds();
			$refund  = $refunds ? reset( $refunds ) : null;
			if ( $refund && '' !== self::credit_number( $refund ) ) {
				$doc = array( 'credit', $order, $refund );
			}
		} elseif ( self::invoiceable( $order ) && ( $order->get_date_paid() || 'customer_invoice' === $email_id ) ) {
			if ( 'customer_completed_order' === $email_id && $order->get_meta( '_aimp_invoice_sent' ) ) {
				return $attachments;
			}
			self::number( $order );
			$doc = array( 'invoice', $order, null );
			$order->update_meta_data( '_aimp_invoice_sent', 1 );
			$order->save_meta_data();
		}
		if ( ! $doc ) {
			return $attachments;
		}
		$file = self::temp_file( $doc );
		if ( '' !== $file ) {
			$attachments[] = $file;
		}
		return $attachments;
	}

	/**
	 * The document as a file for an email attachment (removed at the end of the request).
	 *
	 * @param array $doc [ kind, order, refund ].
	 * @return string Path, or ''.
	 */
	private static function temp_file( $doc ) {
		$dir = trailingslashit( get_temp_dir() ) . 'aimp-invoices-' . wp_generate_password( 8, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		$file = $dir . '/' . sanitize_file_name( self::file_name( $doc ) );
		if ( false === file_put_contents( $file, self::pdf( array( $doc ) ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- a temporary file.
			return '';
		}
		register_shutdown_function(
			function () use ( $file, $dir ) {
				if ( file_exists( $file ) ) {
					wp_delete_file( $file );
				}
				@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- an empty temporary folder.
			}
		);
		return $file;
	}

	/* ------------------------------------------------------------------
	 * My account
	 * ------------------------------------------------------------------ */

	/**
	 * The customer's download address of a document.
	 *
	 * @param WC_Order $order  Order.
	 * @param int      $refund Refund (credit note) or 0.
	 * @return string
	 */
	public static function customer_url( $order, $refund = 0 ) {
		return wp_nonce_url( add_query_arg( array( 'aimp_invoice' => $order->get_id(), 'refund' => (int) $refund ), home_url( '/' ) ), 'aimp_invoice_' . $order->get_id() );
	}

	/**
	 * "Invoice" in My account's order list.
	 *
	 * @param array    $actions Actions.
	 * @param WC_Order $order   Order.
	 * @return array
	 */
	public static function account_actions( $actions, $order ) {
		if ( $order instanceof WC_Order && '' !== (string) $order->get_meta( '_aimp_invoice_number' ) ) {
			$actions['aimp-invoice'] = array(
				'url'  => self::customer_url( $order ),
				'name' => __( 'Invoice', 'atelier-irisee-master-plugin' ),
			);
		}
		return $actions;
	}

	/**
	 * The invoice and credit notes under an order in My account.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function account_links( $order ) {
		if ( ! $order instanceof WC_Order || ! is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id() || '' === (string) $order->get_meta( '_aimp_invoice_number' ) ) {
			return;
		}
		echo '<p class="aimp-invoice-links"><a class="aimp-button" href="' . esc_url( self::customer_url( $order ) ) . '" target="_blank" rel="noopener">' . esc_html( sprintf( /* translators: %s: invoice number */ __( 'Invoice %s', 'atelier-irisee-master-plugin' ), $order->get_meta( '_aimp_invoice_number' ) ) ) . '</a>';
		foreach ( $order->get_refunds() as $refund ) {
			$credit = (string) $refund->get_meta( '_aimp_credit_number' );
			if ( '' !== $credit ) {
				echo ' <a class="aimp-button" href="' . esc_url( self::customer_url( $order, $refund->get_id() ) ) . '" target="_blank" rel="noopener">' . esc_html( sprintf( /* translators: %s: credit note number */ __( 'Credit note %s', 'atelier-irisee-master-plugin' ), $credit ) ) . '</a>';
			}
		}
		echo '</p>';
	}

	/**
	 * The customer's download: only their own order, only a document that exists.
	 */
	public static function customer_download() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked below.
		if ( empty( $_GET['aimp_invoice'] ) ) {
			return;
		}
		$id     = absint( $_GET['aimp_invoice'] );
		$refund = isset( $_GET['refund'] ) ? absint( $_GET['refund'] ) : 0;
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable
		$order = wc_get_order( $id );
		$own   = $order && is_user_logged_in() && ( (int) $order->get_customer_id() === get_current_user_id() || current_user_can( 'edit_shop_orders' ) );
		if ( ! $own || ! wp_verify_nonce( $nonce, 'aimp_invoice_' . $id ) || '' === (string) $order->get_meta( '_aimp_invoice_number' ) ) {
			wp_die( esc_html__( 'This invoice is not available.', 'atelier-irisee-master-plugin' ), '', array( 'response' => 403 ) );
		}
		$doc = array( 'invoice', $order, null );
		if ( $refund ) {
			$object = wc_get_order( $refund );
			if ( ! $object instanceof WC_Order_Refund || (int) $object->get_parent_id() !== $id || '' === (string) $object->get_meta( '_aimp_credit_number' ) ) {
				wp_die( esc_html__( 'This invoice is not available.', 'atelier-irisee-master-plugin' ), '', array( 'response' => 403 ) );
			}
			$doc = array( 'credit', $order, $object );
		}
		do_action( 'litespeed_control_set_nocache', 'aimp invoice' );
		self::send( self::pdf( array( $doc ) ), self::file_name( $doc ) );
	}
}
