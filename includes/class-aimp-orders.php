<?php
/**
 * Orders: sewing project kits as one product after the order too, like the cart. On the thank-you page,
 * under My orders and in the order emails, the items of a kit are shown in one block (thumbnails, amounts,
 * the set total) instead of loose lines.
 *
 * WooCommerce's own tables are used: the kit items are hidden from the item loop
 * (woocommerce_order_item_visible) and their block is added at the end of the same table.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Orders {

	/** True while WooCommerce builds a plain-text email. */
	private static $plain = false;

	public static function init() {
		add_filter( 'woocommerce_order_item_visible', array( __CLASS__, 'hide_kit_items' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table_items', array( __CLASS__, 'details_rows' ) );
		add_filter( 'woocommerce_email_order_items_args', array( __CLASS__, 'email_args' ) );
		add_filter( 'woocommerce_email_order_items_table', array( __CLASS__, 'email_rows' ), 10, 2 );
	}

	/**
	 * @param bool                  $visible Visible.
	 * @param WC_Order_Item_Product $item    Item.
	 * @return bool
	 */
	public static function hide_kit_items( $visible, $item ) {
		return ( $item instanceof WC_Order_Item_Product && '' !== (string) $item->get_meta( '_aimp_group' ) ) ? false : $visible;
	}

	/**
	 * The kits of an order: [ label, total, items: [ item, product, name, amount, total ] ], in the
	 * order pattern, fabric, buttons, zip, ribbon, bias tape.
	 *
	 * @param WC_Order $order Order.
	 * @return array[]
	 */
	public static function kits( $order ) {
		$incl        = 'incl' === get_option( 'woocommerce_tax_display_cart' );
		$order_roles = array_flip( AIMP_Cart_Page::ROLE_ORDER );
		$kits        = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$group = (string) $item->get_meta( '_aimp_group' );
			if ( '' === $group ) {
				continue;
			}
			if ( ! isset( $kits[ $group ] ) ) {
				$label          = (string) $item->get_meta( 'aimp_configuration' );
				$kits[ $group ] = array(
					// "Pattern – M (#AB12CD)" → "Pattern – M".
					'label' => trim( preg_replace( '/\s*\(#[A-Z0-9]+\)$/', '', $label ) ),
					'total' => 0,
					'items' => array(),
				);
			}
			$role   = (string) $item->get_meta( '_aimp_role' );
			$length = (string) $item->get_meta( 'aimp_length' );
			$line   = (float) $order->get_line_subtotal( $item, $incl );
			$kits[ $group ]['total'] += $line;
			$kits[ $group ]['items'][] = array(
				'item'    => $item,
				'role'    => $role,
				'product' => $item->get_product(),
				'name'    => wp_strip_all_tags( $item->get_name() ),
				'amount'  => '' !== $length ? $length : ( 'pattern' === $role ? '' : '× ' . $item->get_quantity() ),
				'total'   => $line,
			);
		}
		foreach ( $kits as &$kit ) {
			usort(
				$kit['items'],
				function ( $a, $b ) use ( $order_roles ) {
					$pa = isset( $order_roles[ $a['role'] ] ) ? $order_roles[ $a['role'] ] : 99;
					$pb = isset( $order_roles[ $b['role'] ] ) ? $order_roles[ $b['role'] ] : 99;
					return $pa - $pb;
				}
			);
		}
		unset( $kit );
		return array_values( $kits );
	}

	private static function thumb( $product, $size = 48 ) {
		$url = ( $product && $product->get_image_id() ) ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_gallery_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' );
		return '<img src="' . esc_url( $url ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" style="width:' . (int) $size . 'px;height:' . (int) $size . 'px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px">';
	}

	/**
	 * Thank-you page and My orders → order: one row per kit in WooCommerce's order details table.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function details_rows( $order ) {
		foreach ( self::kits( $order ) as $kit ) {
			echo '<tr class="woocommerce-table__line-item order_item aimp-order-kit"><td class="woocommerce-table__product-name product-name">';
			/* translators: %s: pattern name and size */
			echo '<strong class="aimp-order-kit-title">' . esc_html( sprintf( __( 'Sewing project kit: %s', 'atelier-irisee-master-plugin' ), $kit['label'] ) ) . '</strong>';
			echo '<ul class="aimp-order-kit-items">';
			foreach ( $kit['items'] as $line ) {
				printf(
					'<li>%1$s<span class="aimp-order-kit-name">%2$s</span>%3$s<span class="aimp-order-kit-price">%4$s</span></li>',
					self::thumb( $line['product'], 40 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in thumb().
					esc_html( $line['name'] ),
					'' !== $line['amount'] ? ' <small class="aimp-order-kit-amount">' . esc_html( $line['amount'] ) . '</small>' : '',
					wp_kses_post( wc_price( $line['total'], array( 'currency' => $order->get_currency() ) ) )
				);
			}
			echo '</ul></td><td class="woocommerce-table__product-total product-total">' . wp_kses_post( wc_price( $kit['total'], array( 'currency' => $order->get_currency() ) ) ) . '</td></tr>';
		}
	}

	/**
	 * @param array $args Arguments of wc_get_email_order_items().
	 * @return array
	 */
	public static function email_args( $args ) {
		self::$plain = ! empty( $args['plain_text'] );
		return $args;
	}

	/**
	 * Order emails: the kits after the other products.
	 *
	 * @param string   $html  Item rows.
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function email_rows( $html, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $html;
		}
		$kits = self::kits( $order );
		if ( ! $kits ) {
			return $html;
		}
		$money = function ( $amount ) use ( $order ) {
			return wc_price( $amount, array( 'currency' => $order->get_currency() ) );
		};
		$out = '';
		foreach ( $kits as $kit ) {
			/* translators: %s: pattern name and size */
			$title = sprintf( __( 'Sewing project kit: %s', 'atelier-irisee-master-plugin' ), $kit['label'] );
			if ( self::$plain ) {
				$out .= "\n" . $title . ' = ' . wp_strip_all_tags( $money( $kit['total'] ) ) . "\n";
				foreach ( $kit['items'] as $line ) {
					$out .= '  - ' . $line['name'] . ( '' !== $line['amount'] ? ' (' . $line['amount'] . ')' : '' ) . ' ' . wp_strip_all_tags( $money( $line['total'] ) ) . "\n";
				}
				continue;
			}
			$items = '';
			foreach ( $kit['items'] as $line ) {
				$items .= '<tr><td style="padding:4px 0;border:0;vertical-align:middle">' . self::thumb( $line['product'], 40 ) . esc_html( $line['name'] ) .
					( '' !== $line['amount'] ? ' <small style="color:#8a6a33">' . esc_html( $line['amount'] ) . '</small>' : '' ) .
					'</td><td style="padding:4px 0 4px 12px;border:0;text-align:right;white-space:nowrap;vertical-align:middle">' . wp_kses_post( $money( $line['total'] ) ) . '</td></tr>';
			}
			$out .= '<tr class="order_item aimp-order-kit">' .
				'<td class="td" style="text-align:left;vertical-align:top;word-wrap:break-word"><strong>' . esc_html( $title ) . '</strong>' .
				'<table cellspacing="0" cellpadding="0" style="width:100%;margin-top:6px;border:0">' . $items . '</table></td>' .
				'<td class="td" style="text-align:left;vertical-align:top">1</td>' .
				'<td class="td" style="text-align:left;vertical-align:top">' . wp_kses_post( $money( $kit['total'] ) ) . '</td></tr>';
		}
		return $html . $out;
	}
}
