<?php
/**
 * Google Merchant Center product feed: /google-feed.xml (RSS 2.0 with Google's g: fields), fetched by Merchant
 * Center every day, so prices, stock and pictures there always match the shop.
 *
 * - Every published product that can be bought; a product with variations (a pattern's sizes) as one item per
 *   variation, grouped (item_group_id), with its size.
 * - Not in it: gift cards, products hidden from the shop, products without a price.
 * - Fabric and other products sold per 10 cm: the price of 10 cm, with unit pricing (per metre) for Google.
 * - Identifiers: the GTIN when the product has one, otherwise "identifier_exists: no" (fabrics and patterns have none).
 * - Shipping and returns are set in Merchant Center itself.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Feed {

	const PATH  = 'google-feed.xml';
	const CACHE = 'aimp_google_feed';

	public static function init() {
		// After WooCommerce has registered its products, before WordPress reads the address.
		add_action( 'wp_loaded', array( __CLASS__, 'serve' ), 1 );
		foreach ( array( 'save_post_product', 'woocommerce_update_product', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'deleted_post', 'trashed_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	public static function flush() {
		delete_transient( self::CACHE );
	}

	/**
	 * The feed's address.
	 *
	 * @return string
	 */
	public static function url() {
		return untrailingslashit( (string) get_option( 'home' ) ) . '/' . self::PATH;
	}

	public static function serve() {
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared.
		$home = untrailingslashit( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ) );
		if ( $home . '/' . self::PATH !== $path || ! function_exists( 'wc_get_products' ) ) {
			return;
		}
		$xml = get_transient( self::CACHE );
		if ( ! is_string( $xml ) || '' === $xml ) {
			// The shop's own language (product texts are written in it).
			$xml = AIMP_I18n::with_language( AIMP_I18n::default_language(), array( __CLASS__, 'xml' ) );
			set_transient( self::CACHE, $xml, 6 * HOUR_IN_SECONDS );
		}
		status_header( 200 );
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		do_action( 'litespeed_control_set_nocache', 'aimp google feed' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped in tag().
		exit;
	}

	/**
	 * One XML element (text escaped), or '' for an empty value.
	 *
	 * @param string $name  Element.
	 * @param mixed  $value Value.
	 * @return string
	 */
	private static function tag( $name, $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		return "\t\t<" . $name . '>' . htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</' . $name . ">\n";
	}

	/**
	 * Plain text of an HTML description.
	 *
	 * @param string $html HTML.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function plain( $html, $max = 4900 ) {
		$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $html ) ), ENT_QUOTES, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
	}

	/**
	 * A price for Google: "12.50 EUR".
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function money( $amount ) {
		return number_format( (float) $amount, 2, '.', '' ) . ' ' . get_woocommerce_currency();
	}

	/**
	 * The whole feed.
	 *
	 * @return string
	 */
	public static function xml() {
		$items = '';
		$page  = 1;
		do {
			$products = wc_get_products(
				array(
					'status'     => 'publish',
					'visibility' => 'visible',
					'limit'      => 100,
					'page'       => $page,
					'orderby'    => 'ID',
					'order'      => 'ASC',
				)
			);
			foreach ( $products as $product ) {
				$items .= self::product_items( $product );
			}
			$page++;
		} while ( count( $products ) === 100 );

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
			'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n" .
			"\t<title>" . htmlspecialchars( get_bloginfo( 'name' ), ENT_XML1, 'UTF-8' ) . "</title>\n" .
			"\t<link>" . htmlspecialchars( home_url( '/' ), ENT_XML1, 'UTF-8' ) . "</link>\n" .
			"\t<description>" . htmlspecialchars( get_bloginfo( 'description' ), ENT_XML1, 'UTF-8' ) . "</description>\n" .
			$items . "</channel>\n</rss>\n";
	}

	/**
	 * The items of one product (one, or one per variation).
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function product_items( $product ) {
		if ( class_exists( 'AIMP_Giftcards_Product' ) && AIMP_Giftcards_Product::is_giftcard( $product ) ) {
			return '';
		}
		if ( $product->is_type( 'variable' ) ) {
			$out = '';
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( $variation && $variation->exists() && 'publish' === $variation->get_status() ) {
					$out .= self::item( $variation, $product );
				}
			}
			return $out;
		}
		return $product->is_type( array( 'simple' ) ) ? self::item( $product, null ) : '';
	}

	/**
	 * One item.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  The variable product of a variation.
	 * @return string
	 */
	private static function item( $product, $parent ) {
		$main  = $parent ? $parent : $product;
		$price = (float) wc_get_price_including_tax( $product, array( 'price' => $product->get_regular_price() ) );
		if ( $price <= 0 ) {
			$price = (float) wc_get_price_including_tax( $product );
		}
		if ( $price <= 0 ) {
			return '';
		}
		$per_10cm = class_exists( 'AIMP_Catalog' ) && AIMP_Catalog::sold_per_10cm( $main );
		$title    = wp_strip_all_tags( $main->get_name() );
		$size     = '';
		if ( $parent ) {
			$attrs = array();
			foreach ( $product->get_variation_attributes() as $key => $value ) {
				if ( '' === (string) $value ) {
					continue;
				}
				$taxonomy = str_replace( 'attribute_', '', $key );
				$term     = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', $value, $taxonomy ) : null;
				$attrs[]  = $term ? $term->name : $value;
			}
			$size  = implode( ' / ', $attrs );
			$title = $title . ( '' !== $size ? ' – ' . $size : '' );
		}
		if ( $per_10cm ) {
			/* translators: %s: product name */
			$title = sprintf( __( '%s (per 10 cm)', 'atelier-irisee-master-plugin' ), $title );
		}
		$description = self::plain( $main->get_description() );
		if ( '' === $description ) {
			$description = self::plain( $main->get_short_description() );
		}
		if ( '' === $description ) {
			$description = $title;
		}

		// Pictures: the variation's own first, then the product's.
		$ids = array_values( array_unique( array_filter( array_merge( array( $product->get_image_id(), $main->get_image_id() ), $main->get_gallery_image_ids() ) ) ) );
		$urls = array();
		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( (int) $id, 'full' );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		if ( ! $urls ) {
			return '';
		}

		if ( $product->is_on_backorder() ) {
			$availability = 'backorder';
		} else {
			$availability = $product->is_in_stock() ? 'in_stock' : 'out_of_stock';
		}

		// Categories as the shop names them ("Stoffen > Bouclé").
		$type  = '';
		$terms = get_the_terms( $main->get_id(), 'product_cat' );
		if ( is_array( $terms ) && $terms ) {
			$deepest = $terms[0];
			foreach ( $terms as $term ) {
				if ( count( get_ancestors( $term->term_id, 'product_cat' ) ) > count( get_ancestors( $deepest->term_id, 'product_cat' ) ) ) {
					$deepest = $term;
				}
			}
			$path = array_reverse( array_map( 'intval', get_ancestors( $deepest->term_id, 'product_cat' ) ) );
			$names = array();
			foreach ( $path as $ancestor ) {
				$names[] = get_term( $ancestor, 'product_cat' )->name;
			}
			$names[] = $deepest->name;
			$type    = implode( ' > ', $names );
		}

		$brand = class_exists( 'AIMP_Product_Page' ) ? (string) AIMP_Product_Page::brand( $main ) : '';
		$gtin  = method_exists( $product, 'get_global_unique_id' ) ? (string) $product->get_global_unique_id() : '';
		if ( '' === $gtin && $parent && method_exists( $parent, 'get_global_unique_id' ) ) {
			$gtin = (string) $parent->get_global_unique_id();
		}

		$out  = "\t<item>\n";
		$out .= self::tag( 'g:id', $product->get_id() );
		if ( $parent ) {
			$out .= self::tag( 'g:item_group_id', $parent->get_id() );
		}
		$out .= self::tag( 'g:title', mb_substr( $title, 0, 150 ) );
		$out .= self::tag( 'g:description', $description );
		$out .= self::tag( 'g:link', $product->get_permalink() );
		$out .= self::tag( 'g:image_link', $urls[0] );
		foreach ( array_slice( $urls, 1, 10 ) as $extra ) {
			$out .= self::tag( 'g:additional_image_link', $extra );
		}
		$out .= self::tag( 'g:availability', $availability );
		$out .= self::tag( 'g:price', self::money( $price ) );
		if ( $product->is_on_sale() && '' !== (string) $product->get_sale_price() ) {
			$out .= self::tag( 'g:sale_price', self::money( wc_get_price_including_tax( $product, array( 'price' => $product->get_sale_price() ) ) ) );
		}
		if ( $per_10cm ) {
			$out .= self::tag( 'g:unit_pricing_measure', '10cm' );
			$out .= self::tag( 'g:unit_pricing_base_measure', '100cm' );
		}
		$out .= self::tag( 'g:condition', 'new' );
		$out .= self::tag( 'g:brand', $brand );
		if ( '' !== $gtin ) {
			$out .= self::tag( 'g:gtin', $gtin );
		} else {
			$out .= self::tag( 'g:identifier_exists', 'no' );
		}
		$out .= self::tag( 'g:product_type', $type );
		if ( '' !== $size ) {
			$out .= self::tag( 'g:size', $size );
		}
		return $out . "\t</item>\n";
	}
}
