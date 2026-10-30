<?php
/**
 * Product pages in the Atelier Irisee design: [atelier_irisee_product id="123"].
 *
 * When "Use the Atelier Irisee design on product pages" is on, WooCommerce's own product pages use it too:
 * classic themes through the content-single-product template part, block themes through the
 * single-product block template (theme header + this shortcode + theme footer).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Product_Page {

	const TAG = 'atelier_irisee_product';

	/** How many related products and fitting patterns are shown. */
	const RELATED = 4;
	const FITTING = 8;

	private static $localized = false;

	/** True while a product page is being printed (the gift card form then leaves the amount to the price bar). */
	private static $rendering = false;

	public static function is_rendering() {
		return self::$rendering;
	}

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_filter( 'wc_get_template_part', array( __CLASS__, 'template_part' ), 20, 3 );
		add_filter( 'get_block_templates', array( __CLASS__, 'block_templates' ), 20, 3 );
		add_filter( 'get_block_template', array( __CLASS__, 'block_template' ), 20, 3 );

		// Buy bar: length in cm, and patterns bought as one item with all sizes.
		add_action( 'wp_loaded', array( __CLASS__, 'length_to_quantity' ), 19 );
		add_action( 'wp_loaded', array( __CLASS__, 'add_pattern_to_cart' ), 20 );
		add_action( 'wc_ajax_aimp_shop_buy', array( __CLASS__, 'ajax_add_to_cart' ) ); // Its own address: aimp_add_to_cart is the configurator's (with a nonce).
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'all_sizes_item_data' ), 5, 2 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'all_sizes_item_name' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'all_sizes_order_item' ), 10, 3 );

		// Customers don't see stock levels; only "Out of stock" stays.
		add_filter( 'woocommerce_get_stock_html', array( __CLASS__, 'stock_html' ), 20, 2 );

		// A saved product (or size) is shown right away, also when a caching plugin keeps copies of the pages.
		add_action( 'woocommerce_update_product', array( __CLASS__, 'purge_caches' ), 99 );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'purge_caches' ), 99 );
		add_action( 'shutdown', array( __CLASS__, 'run_purge' ) );
	}

	/* ------------------------------------------------------------------
	 * Caches
	 * ------------------------------------------------------------------ */

	/** Products saved during this request (refreshed once, at the end). */
	private static $to_purge = array();

	/**
	 * @param int $product_id Product or variation ID.
	 */
	public static function purge_caches( $product_id ) {
		$parent = wp_get_post_parent_id( $product_id );
		$id     = 'product_variation' === get_post_type( $product_id ) && $parent ? $parent : (int) $product_id;
		if ( $id ) {
			self::$to_purge[ $id ] = $id;
		}
	}

	/**
	 * Clears WooCommerce's product caches and asks the common page caching plugins to refresh the product page
	 * and the shop pages.
	 */
	public static function run_purge() {
		if ( ! self::$to_purge ) {
			return;
		}
		$urls = array();
		foreach ( self::$to_purge as $id ) {
			wc_delete_product_transients( $id );
			clean_post_cache( $id );
			$urls[] = get_permalink( $id );
		}
		AIMP_Shop::flush_cache();
		foreach ( array( wc_get_page_id( 'shop' ), AIMP_Settings::get( 'configurator_page' ) ) as $page_id ) {
			if ( $page_id > 0 ) {
				$urls[] = get_permalink( $page_id );
			}
		}
		$urls = array_filter( array_unique( $urls ) );

		foreach ( self::$to_purge as $id ) {
			do_action( 'litespeed_purge_post', $id );                // LiteSpeed Cache.
			if ( function_exists( 'rocket_clean_post' ) ) {          // WP Rocket.
				rocket_clean_post( $id );
			}
			if ( function_exists( 'w3tc_flush_post' ) ) {            // W3 Total Cache.
				w3tc_flush_post( $id );
			}
			if ( function_exists( 'wp_cache_post_change' ) ) {       // WP Super Cache.
				wp_cache_post_change( $id );
			}
			if ( function_exists( 'sg_cachepress_purge_cache' ) ) {  // SiteGround Optimizer.
				sg_cachepress_purge_cache( get_permalink( $id ) );
			}
			do_action( 'breeze_clear_all_cache' );                   // Breeze (no per-page purge).
			do_action( 'wphb_clear_page_cache', $id );               // Hummingbird.
		}
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'singleDeleteCache' ) ) { // WP Fastest Cache.
			foreach ( self::$to_purge as $id ) {
				$GLOBALS['wp_fastest_cache']->singleDeleteCache( false, $id );
			}
		}
		if ( class_exists( '\CF\WordPress\Hooks' ) ) {               // Cloudflare (APO).
			try {
				$cloudflare = new \CF\WordPress\Hooks();
				if ( method_exists( $cloudflare, 'purgeCacheByRelevantURLs' ) ) {
					foreach ( self::$to_purge as $id ) {
						$cloudflare->purgeCacheByRelevantURLs( $id );
					}
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- a failing cache plugin must not break saving.
			}
		}
		/**
		 * Lets other caching setups refresh the pages of saved products.
		 *
		 * @param int[]    $ids  Product IDs.
		 * @param string[] $urls Product and shop page addresses.
		 */
		do_action( 'aimp_purge_product_pages', array_values( self::$to_purge ), $urls );
		self::$to_purge = array();
	}

	public static function enabled() {
		return (bool) AIMP_Settings::get( 'product_design' );
	}

	/* ------------------------------------------------------------------
	 * Buy bar
	 * ------------------------------------------------------------------ */

	/**
	 * @param string     $html    Stock HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function stock_html( $html, $product ) {
		return ( $product instanceof WC_Product && ! $product->is_in_stock() ) ? $html : '';
	}

	/**
	 * The length field in cm is sent as aimp_length_cm; WooCommerce counts units of 10 cm.
	 * Runs just before WooCommerce handles add-to-cart, so it also works without JavaScript.
	 */
	public static function length_to_quantity() {
		// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce's add-to-cart form has no nonce either; this only converts the amount.
		if ( ! isset( $_REQUEST['aimp_length_cm'] ) ) {
			return;
		}
		$units = max( 1, (int) ceil( (float) wc_clean( wp_unslash( $_REQUEST['aimp_length_cm'] ) ) / AIMP_Catalog::FABRIC_UNIT_CM ) );
		// phpcs:enable
		$_REQUEST['quantity'] = $units;
		$_POST['quantity']    = $units;
	}

	/**
	 * The size a pattern is added with when it is bought as one item with all sizes: the first size that can be bought.
	 *
	 * @param WC_Product $pattern Pattern product.
	 * @return WC_Product_Variation|null
	 */
	/**
	 * Pattern page "Accessories" and size table: one group per design (or one group), each
	 * [ name, accessories: [ [ icon, name, facts: [ [ icon, label, value ] ] ] ], columns: [ label ], rows: [ [ size, cells… ] ] ].
	 * Only filled-in materials and measurements are listed.
	 *
	 * @param WC_Product $pattern Pattern.
	 * @return array
	 */
	public static function size_table( $pattern ) {
		$names    = AIMP_Catalog::designs( $pattern );
		$fields   = AIMP_Catalog::material_fields();
		$measures = array(
			'bust'       => __( 'Bust', 'atelier-irisee-master-plugin' ),
			'waist'      => __( 'Waist', 'atelier-irisee-master-plugin' ),
			'hip'        => __( 'Hip', 'atelier-irisee-master-plugin' ),
			'inside_leg' => __( 'Inside leg', 'atelier-irisee-master-plugin' ),
			'height'     => __( 'Height', 'atelier-irisee-master-plugin' ),
		);
		$sizes = array();
		foreach ( $pattern->get_children() as $child_id ) {
			$req = AIMP_Catalog::get_requirements( $child_id );
			if ( $req ) {
				$sizes[ $names ? AIMP_Catalog::variation_design( $req['variation'] ) : '' ][] = $req;
			}
		}
		$groups = array();
		foreach ( $sizes as $slug => $reqs ) {
			// The diameter or width that fits ("15–20 mm"), from this design's rules: material => [ fact key, text ].
			$ranges = array();
			foreach ( AIMP_Notions::rules() as $rule => $info ) {
				$text = AIMP_Notions::range_text( $reqs[0]['notion_rules'][ $rule ], AIMP_Notions::rule_unit( $rule ) );
				$kind = AIMP_Notions::kind( $info[0] );
				if ( '' !== $text && $kind ) {
					$ranges[ $info[2] ] = array( 'diameter' === $kind['range'] ? 'diameter' : 'width', $text );
				}
			}
			$fact_labels = array(
				'amount'   => __( 'Amount', 'atelier-irisee-master-plugin' ),
				'length'   => __( 'Length', 'atelier-irisee-master-plugin' ),
				'diameter' => __( 'Diameter', 'atelier-irisee-master-plugin' ),
				'width'    => __( 'Width', 'atelier-irisee-master-plugin' ),
			);
			// Accessories: every material any size needs, with an icon and its facts (amount or length, then diameter or width).
			$accessories = array();
			foreach ( $fields as $key => $field ) {
				// Fabric and lining have their own columns in the table.
				if ( 'fabric' === $key || 'lining' === $key ) {
					continue;
				}
				$amounts = array_filter(
					array_map(
						function ( $req ) use ( $key ) {
							return (float) $req['materials'][ $key ];
						},
						$reqs
					)
				);
				if ( ! $amounts ) {
					continue;
				}
				$min   = min( $amounts );
				$max   = max( $amounts );
				$text  = $min === $max ? AIMP_Catalog::material_text( $key, $min ) : ( 'cm' === $field['unit']
					/* translators: 1: smallest length, 2: largest length (in cm) */
					? sprintf( __( '%1$s–%2$s cm', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $min, floor( $min ) == $min ? 0 : 1 ), AIMP_I18n::number( $max, floor( $max ) == $max ? 0 : 1 ) )
					: sprintf( '%d–%d', $min, $max ) );
				$first = 'cm' === $field['unit'] ? 'length' : 'amount';
				$facts = array( array( 'icon' => self::fabric_icon( 'cm' === $field['unit'] ? 'length' : 'set' ), 'label' => $fact_labels[ $first ], 'value' => $text ) );
				if ( isset( $ranges[ $key ] ) ) {
					$facts[] = array( 'icon' => self::fabric_icon( $ranges[ $key ][0] ), 'label' => $fact_labels[ $ranges[ $key ][0] ], 'value' => $ranges[ $key ][1] );
				}
				$accessories[] = array(
					'icon'  => self::fabric_icon( self::material_icon( $key ) ),
					'name'  => AIMP_Catalog::material_label( $key, $reqs[0] ),
					'facts' => $facts,
				);
			}
			// Table: the measurements filled in for at least one size, then the fabric.
			$used = array();
			foreach ( $measures as $key => $label ) {
				foreach ( $reqs as $req ) {
					if ( '' !== (string) $req[ $key ] && (float) $req[ $key ] > 0 ) {
						$used[ $key ] = $label;
						break;
					}
				}
			}
			$lining = (bool) array_filter(
				$reqs,
				function ( $req ) {
					return $req['lining_cm'] > 0;
				}
			);
			$rows   = array();
			foreach ( $reqs as $req ) {
				$row = array( $req['size'] );
				foreach ( array_keys( $used ) as $key ) {
					$row[] = '' !== (string) $req[ $key ] && (float) $req[ $key ] > 0 ? AIMP_Catalog::measure_text( $req[ $key ], isset( $req[ $key . '_to' ] ) ? $req[ $key . '_to' ] : '' ) : '–';
				}
				/* translators: %s: length in metres */
				$row[] = $req['fabric_cm'] > 0 ? sprintf( __( '%s m', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $req['fabric_cm'] / 100, 2 ) ) : '–';
				if ( $lining ) {
					/* translators: %s: length in metres */
					$row[] = $req['lining_cm'] > 0 ? sprintf( __( '%s m', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $req['lining_cm'] / 100, 2 ) ) : '–';
				}
				$rows[] = $row;
			}
			$groups[] = array(
				'name'        => isset( $names[ $slug ] ) ? $names[ $slug ] : '',
				'accessories' => $accessories,
				'columns'     => array_merge( array( __( 'Size', 'atelier-irisee-master-plugin' ) ), array_values( $used ), array( __( 'Fabric', 'atelier-irisee-master-plugin' ) ), $lining ? array( __( 'Lining', 'atelier-irisee-master-plugin' ) ) : array() ),
				'rows'        => $rows,
			);
		}
		// Designs in their own order.
		if ( $names ) {
			$order = array_flip( array_values( $names ) );
			usort(
				$groups,
				function ( $a, $b ) use ( $order ) {
					return ( isset( $order[ $a['name'] ] ) ? $order[ $a['name'] ] : 99 ) <=> ( isset( $order[ $b['name'] ] ) ? $order[ $b['name'] ] : 99 );
				}
			);
		}
		return $groups;
	}

	/**
	 * The designs of a pack: [ name, project_time, url ] per design (url: the configurator with that design).
	 *
	 * @param WC_Product $pattern      Pattern.
	 * @param string     $configurator Configurator page URL ('' = none).
	 * @return array
	 */
	public static function design_list( $pattern, $configurator ) {
		$names = AIMP_Catalog::designs( $pattern );
		$first = AIMP_Catalog::design_first_sizes( $pattern );
		$data  = AIMP_Catalog::design_data( $pattern->get_id() );
		$list  = array();
		foreach ( $first as $slug => $variation_id ) {
			$list[] = array(
				'name'         => isset( $names[ $slug ] ) ? $names[ $slug ] : $slug,
				'project_time' => '' !== $data[ $slug ]['project_time'] ? $data[ $slug ]['project_time'] : (string) $pattern->get_meta( AIMP_Catalog::META_PROJECT_TIME ),
				'url'          => '' !== $configurator ? add_query_arg( 'aimp_pattern', $variation_id, $configurator ) : '',
			);
		}
		return $list;
	}

	public static function pattern_variation( $pattern ) {
		foreach ( $pattern->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( $variation && $variation->is_type( 'variation' ) && 'publish' === $variation->get_status() && $variation->is_purchasable() && $variation->is_in_stock() ) {
				return $variation;
			}
		}
		return null;
	}

	/**
	 * Why a pattern shows "Out of stock" on its page, for the admin check. Empty when it can be bought.
	 *
	 * @param WC_Product $pattern Pattern product.
	 * @return string
	 */
	public static function pattern_issue( $pattern ) {
		if ( 'publish' !== $pattern->get_status() ) {
			return __( 'the pattern is not published.', 'atelier-irisee-master-plugin' );
		}
		if ( ! AIMP_Catalog::get_pattern( $pattern->get_id() ) ) {
			return __( 'the pattern is not in the Patterns category set under WooCommerce > Atelier Irisee.', 'atelier-irisee-master-plugin' );
		}
		if ( self::pattern_variation( $pattern ) ) {
			return '';
		}
		$enabled = 0;
		$priced  = 0;
		foreach ( $pattern->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || 'publish' !== $variation->get_status() ) {
				continue;
			}
			++$enabled;
			if ( '' !== (string) $variation->get_price() ) {
				++$priced;
			}
		}
		if ( ! $enabled ) {
			return __( 'the pattern has no enabled sizes (Variations tab).', 'atelier-irisee-master-plugin' );
		}
		if ( ! $priced ) {
			return __( 'there is no pattern price (Pattern price field below).', 'atelier-irisee-master-plugin' );
		}
		if ( ! $pattern->is_in_stock() || $pattern->get_manage_stock() ) {
			return __( 'the pattern is out of stock (Inventory tab).', 'atelier-irisee-master-plugin' );
		}
		return __( 'all sizes are marked out of stock. Save the pattern once to give them the stock of the Inventory tab.', 'atelier-irisee-master-plugin' );
	}

	/**
	 * ?wc-ajax=aimp_shop_buy: the buy bar in the shop's details panel. A simple product (amount in units;
	 * 10 cm for fabric) or a pattern with all sizes. No nonce, like WooCommerce's own add-to-cart endpoint
	 * (cached shop pages).
	 */
	public static function ajax_add_to_cart() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see above.
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$qty        = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;
		// phpcs:enable
		$product = wc_get_product( $product_id );
		$added   = false;
		if ( ! $product || ! function_exists( 'WC' ) || ! WC()->cart || 'publish' !== $product->get_status() ) {
			wc_add_notice( __( 'This item is not available.', 'atelier-irisee-master-plugin' ), 'error' );
		} elseif ( AIMP_Catalog::get_pattern( $product_id ) ) {
			$variation = self::pattern_variation( $product );
			if ( $variation ) {
				$added = (bool) WC()->cart->add_to_cart( $product_id, $qty, $variation->get_id(), $variation->get_variation_attributes(), array( 'aimp_all_sizes' => 1 ) );
			} else {
				wc_add_notice( __( 'This item is not available.', 'atelier-irisee-master-plugin' ), 'error' );
			}
		} elseif ( $product->is_type( 'simple' ) && ! ( class_exists( 'AIMP_Giftcards_Product' ) && AIMP_Giftcards_Product::is_giftcard( $product ) ) ) {
			$added = (bool) WC()->cart->add_to_cart( $product_id, $qty );
		} else {
			wc_add_notice( __( 'This item is not available.', 'atelier-irisee-master-plugin' ), 'error' );
		}

		$errors = array_map(
			function ( $notice ) {
				return wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : $notice );
			},
			wc_get_notices( 'error' )
		);
		wc_clear_notices(); // The answer is shown in the panel, not later as a page notice.
		if ( ! $added ) {
			wp_send_json_error( array( 'errors' => $errors ? $errors : array( __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ) ) ) );
		}
		wp_send_json_success(
			array(
				'message'  => __( 'Added to your cart.', 'atelier-irisee-master-plugin' ),
				'cart_url' => wc_get_cart_url(),
			)
		);
	}

	public static function add_pattern_to_cart() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
		if ( empty( $_POST['aimp_add_pattern'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$nonce = isset( $_POST['aimp_pattern_nonce'] ) ? sanitize_key( wp_unslash( $_POST['aimp_pattern_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aimp_add_pattern' ) ) {
			wc_add_notice( __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ), 'error' );
			return;
		}
		$pattern = AIMP_Catalog::get_pattern( absint( wp_unslash( $_POST['aimp_add_pattern'] ) ) );
		$qty     = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;
		// phpcs:enable
		$variation = $pattern ? self::pattern_variation( $pattern ) : null;
		if ( ! $variation ) {
			wc_add_notice( __( 'This item is not available.', 'atelier-irisee-master-plugin' ), 'error' );
			return;
		}

		$added = WC()->cart->add_to_cart( $pattern->get_id(), $qty, $variation->get_id(), $variation->get_variation_attributes(), array( 'aimp_all_sizes' => 1 ) );
		if ( ! $added ) {
			return; // WooCommerce has added the reason as a notice.
		}
		wc_add_to_cart_message( array( $pattern->get_id() => $qty ), true );
		$url = 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ? wc_get_cart_url() : $pattern->get_permalink();
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Cart and checkout: no size line for patterns bought with all sizes.
	 *
	 * @param array $item_data Lines shown under the product.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function all_sizes_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['aimp_all_sizes'] ) ) {
			return $item_data;
		}
		$size_labels = array();
		foreach ( array_keys( (array) ( isset( $cart_item['variation'] ) ? $cart_item['variation'] : array() ) ) as $attribute ) {
			$size_labels[] = wc_attribute_label( str_replace( 'attribute_', '', $attribute ), $cart_item['data'] );
		}
		$item_data   = array_values(
			array_filter(
				(array) $item_data,
				function ( $line ) use ( $size_labels ) {
					return ! isset( $line['key'] ) || ! in_array( $line['key'], $size_labels, true );
				}
			)
		);
		$item_data[] = array(
			'key'   => __( 'Sizes', 'atelier-irisee-master-plugin' ),
			'value' => __( 'All sizes', 'atelier-irisee-master-plugin' ),
		);
		return $item_data;
	}

	/**
	 * "Pattern - M" → "Pattern" for patterns bought with all sizes.
	 *
	 * @param string $name      Name HTML.
	 * @param array  $cart_item Cart item.
	 * @return string
	 */
	public static function all_sizes_item_name( $name, $cart_item ) {
		if ( empty( $cart_item['aimp_all_sizes'] ) || empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $name;
		}
		$parent = wc_get_product( $cart_item['data']->get_parent_id() );
		return $parent ? str_replace( esc_html( $cart_item['data']->get_name() ), esc_html( $parent->get_name() ), $name ) : $name;
	}

	/**
	 * Order line: pattern name, "Sizes: All sizes" instead of the size.
	 *
	 * @param WC_Order_Item_Product $item   Order item.
	 * @param string                $key    Cart item key.
	 * @param array                 $values Cart item.
	 */
	public static function all_sizes_order_item( $item, $key, $values ) {
		if ( empty( $values['aimp_all_sizes'] ) ) {
			return;
		}
		foreach ( array_keys( (array) ( isset( $values['variation'] ) ? $values['variation'] : array() ) ) as $attribute ) {
			$item->delete_meta_data( str_replace( 'attribute_', '', $attribute ) );
		}
		$parent = wc_get_product( $item->get_product_id() );
		if ( $parent ) {
			$item->set_name( $parent->get_name() );
		}
		$item->add_meta_data( __( 'Sizes', 'atelier-irisee-master-plugin' ), __( 'All sizes', 'atelier-irisee-master-plugin' ), true );
	}

	/**
	 * Buy bar data: what is bought, in which steps, and for which price.
	 *
	 * @param WC_Product $product Product.
	 * @param bool       $per_10cm Sold per 10 cm.
	 * @return array|null Null when the product uses WooCommerce's own form (other product types).
	 */
	public static function buy_data( $product, $per_10cm ) {
		$is_giftcard = class_exists( 'AIMP_Giftcards_Product' ) && AIMP_Giftcards_Product::is_giftcard( $product );
		if ( $is_giftcard ) {
			// The ‹ › arrows choose the card's value in steps of €5; the price is that value (+ printing fee).
			list( $min, $max ) = AIMP_Giftcards::amount_range();
			$value             = $min;
			foreach ( AIMP_Giftcards::preset_amounts() as $preset ) {
				if ( $preset >= $min && $preset <= $max && abs( fmod( $preset, AIMP_Giftcards::AMOUNT_STEP ) ) < 0.001 ) {
					$value = (float) $preset;
					break;
				}
			}
			// A link from the gift card promo (?aimp_gc_amount=50) starts at that amount.
			$link = isset( $_GET['aimp_gc_amount'] ) ? (float) wp_unslash( $_GET['aimp_gc_amount'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only preselects an amount.
			if ( $link >= $min && $link <= $max && abs( fmod( $link, AIMP_Giftcards::AMOUNT_STEP ) ) < 0.001 ) {
				$value = $link;
			}
			return array(
				'available'  => $product->is_purchasable(),
				'pattern'    => false,
				'giftcard'   => true,
				'per_10cm'   => false,
				'unit_price' => $value,
				'min'        => $min,
				'max'        => $max,
				'step'       => AIMP_Giftcards::AMOUNT_STEP,
				'value'      => $value,
			);
		}
		if ( $product->is_type( 'simple' ) ) {
			$buyable = $product;
		} elseif ( AIMP_Catalog::get_pattern( $product->get_id() ) ) {
			$buyable = self::pattern_variation( $product );
		} else {
			return null;
		}

		$available = $buyable && $buyable->is_purchasable() && $buyable->is_in_stock();
		$min       = $available ? max( 1, (int) $buyable->get_min_purchase_quantity() ) : 1;
		$max       = $available ? (int) $buyable->get_max_purchase_quantity() : 0; // -1 = no maximum.
		$factor    = $per_10cm ? AIMP_Catalog::FABRIC_UNIT_CM : 1;
		// Only when the stock is low (setting; 0 = never), the one place a stock level is shown.
		$threshold = $available ? AIMP_Trust::low_stock_threshold( $buyable ) : 0;
		$left      = $available && $threshold > 0 && $buyable->managing_stock() ? (int) $buyable->get_stock_quantity() : 0;

		return array(
			'available'  => $available,
			'pattern'    => ! $product->is_type( 'simple' ),
			'giftcard'   => false,
			'per_10cm'   => $per_10cm,
			'unit_price' => $buyable ? (float) wc_get_price_to_display( $buyable ) : 0,
			'min'        => $min * $factor,
			'max'        => $max > 0 ? $max * $factor : 0,
			'step'       => $factor,
			'value'      => $min * $factor,
			'low'        => $left > 0 && $left <= $threshold ? $left * $factor : 0,
			// Haberdashery: the amount is in pieces, sets or rolls (1 = 1 in stock); the unit is shown next to the price.
			'unit_text'  => '' !== AIMP_Notions::kind_of( $product ) && ! $per_10cm ? AIMP_Notions::price_suffix( $product ) : '',
			'set_size'   => AIMP_Notions::set_size( $product ),
		);
	}

	/* ------------------------------------------------------------------
	 * Taking over the product pages
	 * ------------------------------------------------------------------ */

	/**
	 * Classic themes (and WooCommerce's classic template inside block themes).
	 *
	 * @param string $template Template file.
	 * @param string $slug     Template slug.
	 * @param string $name     Template name.
	 * @return string
	 */
	public static function template_part( $template, $slug, $name ) {
		if ( 'content' === $slug && 'single-product' === $name && self::enabled() ) {
			return AIMP_PLUGIN_DIR . 'templates/product/content-single-product.php';
		}
		return $template;
	}

	/**
	 * Whether block templates may be changed in this request: only on the front end, never in the
	 * Site Editor (so your own template stays untouched there).
	 *
	 * @return bool
	 */
	private static function can_swap_block_template() {
		return self::enabled() && ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Theme header part, our shortcode, theme footer part.
	 *
	 * @param WP_Block_Template $template Template to change.
	 * @return WP_Block_Template
	 */
	private static function swap( $template ) {
		if ( ! $template instanceof WP_Block_Template || 'single-product' !== $template->slug ) {
			return $template;
		}
		if ( false !== strpos( (string) $template->content, '[' . self::TAG ) ) {
			return $template; // Already placed by hand.
		}

		$header = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
		$footer = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		$found_header = false;
		foreach ( parse_blocks( (string) $template->content ) as $block ) {
			if ( 'core/template-part' !== $block['blockName'] ) {
				continue;
			}
			$attrs = $block['attrs'];
			$area  = ( isset( $attrs['tagName'] ) ? $attrs['tagName'] : '' ) . ' ' . ( isset( $attrs['slug'] ) ? $attrs['slug'] : '' ) . ' ' . ( isset( $attrs['area'] ) ? $attrs['area'] : '' );
			if ( false !== strpos( $area, 'header' ) && ! $found_header ) {
				$header       = serialize_block( $block );
				$found_header = true;
			} elseif ( false !== strpos( $area, 'footer' ) ) {
				$footer = serialize_block( $block );
			}
		}

		$template          = clone $template;
		$template->content = $header . "\n" .
			'<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">' .
			'<!-- wp:shortcode -->[' . self::TAG . ']<!-- /wp:shortcode -->' .
			'</main><!-- /wp:group -->' . "\n" . $footer;
		return $template;
	}

	/**
	 * @param WP_Block_Template[] $templates     Templates.
	 * @param array               $query         Query.
	 * @param string              $template_type wp_template or wp_template_part.
	 * @return WP_Block_Template[]
	 */
	public static function block_templates( $templates, $query, $template_type ) {
		if ( 'wp_template' !== $template_type || ! self::can_swap_block_template() || ! is_array( $templates ) ) {
			return $templates;
		}
		return array_map( array( __CLASS__, 'swap' ), $templates );
	}

	/**
	 * @param WP_Block_Template|null $template      Template.
	 * @param string                 $id            Template ID.
	 * @param string                 $template_type wp_template or wp_template_part.
	 * @return WP_Block_Template|null
	 */
	public static function block_template( $template, $id, $template_type ) {
		if ( 'wp_template' !== $template_type || ! self::can_swap_block_template() ) {
			return $template;
		}
		return self::swap( $template );
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public static function register_assets() {
		wp_register_style( 'aimp-product', AIMP_PLUGIN_URL . 'assets/css/product.css', array( 'aimp-configurator' ), AIMP_VERSION );
		wp_register_script( 'aimp-product', AIMP_PLUGIN_URL . 'assets/js/product.js', array( 'aimp-ui', 'aimp-favorites' ), AIMP_VERSION, true );

		$post = get_post();
		if ( ( self::enabled() && function_exists( 'is_product' ) && is_product() ) || ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) ) {
			wp_enqueue_style( 'aimp-product' );
		}
	}

	/**
	 * Texts for product.js in the active language (the page itself is rendered in that language).
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'close'       => __( 'Close', 'atelier-irisee-master-plugin' ),
			'previous'    => __( 'Previous', 'atelier-irisee-master-plugin' ),
			'next'        => __( 'Next', 'atelier-irisee-master-plugin' ),
			'showPicture' => __( 'Show picture %d', 'atelier-irisee-master-plugin' ),
			'per10cm'     => __( 'per 10 cm', 'atelier-irisee-master-plugin' ),
			/* translators: %s: price of one piece */
			'perPiece'    => __( '%s per piece', 'atelier-irisee-master-plugin' ),
			'linkCopied'  => __( 'Link copied', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * How many "You may also like" products are shown (Design studio, else 4).
	 *
	 * @return int
	 */
	private static function related_count() {
		$count = class_exists( 'AIMP_Design' ) ? (int) AIMP_Design::opt( 'product-page', 'pp_related', 0 ) : 0;
		return $count > 0 ? $count : self::RELATED;
	}

	/**
	 * Share buttons under the buy bar (Design studio): WhatsApp, Facebook, Pinterest, email and copy the link.
	 *
	 * @param WC_Product $product Product.
	 * @param array|null $picture The first picture of the gallery.
	 * @return string
	 */
	public static function share_html( $product, $picture ) {
		$url   = $product->get_permalink();
		$name  = wp_strip_all_tags( $product->get_name() );
		$links = array(
			'whatsapp'  => array( 'WhatsApp', 'https://wa.me/?text=' . rawurlencode( $name . ' ' . $url ) ),
			'facebook'  => array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ),
			'pinterest' => array( 'Pinterest', 'https://pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . ( $picture ? '&media=' . rawurlencode( $picture['large'] ) : '' ) . '&description=' . rawurlencode( $name ) ),
			'email'     => array( __( 'Email', 'atelier-irisee-master-plugin' ), 'mailto:?subject=' . rawurlencode( $name ) . '&body=' . rawurlencode( $url ) ),
		);
		$html = '';
		foreach ( $links as $key => $link ) {
			$html .= '<a class="aimp-share-link aimp-share-link--' . esc_attr( $key ) . '" href="' . esc_url( $link[1], array( 'https', 'mailto' ) ) . '"' . ( 'email' === $key ? '' : ' target="_blank" rel="noopener noreferrer"' ) . '>' . esc_html( $link[0] ) . '</a>';
		}
		$html .= '<button type="button" class="aimp-share-link aimp-share-link--copy" data-aimp-copy="' . esc_url( $url ) . '">' . esc_html__( 'Copy link', 'atelier-irisee-master-plugin' ) . '</button>';
		return '<div class="aimp-share"><span class="aimp-share-label">' . esc_html__( 'Share', 'atelier-irisee-master-plugin' ) . '</span>' . $html . '</div>';
	}

	private static function enqueue() {
		if ( ! wp_script_is( 'aimp-ui', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
		}
		if ( ! wp_script_is( 'aimp-product', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-product' );
		wp_enqueue_script( 'aimp-product' );
		if ( ! self::$localized ) {
			self::$localized = true;
			wp_localize_script(
				'aimp-product',
				'aimpProduct',
				array(
					'currency' => array(
						'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
						'position' => get_option( 'woocommerce_currency_pos', 'left' ),
						'decimals' => wc_get_price_decimals(),
						'decimal'  => wc_get_price_decimal_separator(),
						'thousand' => wc_get_price_thousand_separator(),
					),
					'i18n'     => self::strings(),
					// "How to measure" picture of the size table.
					'measureImage' => AIMP_PLUGIN_URL . 'assets/images/lichaamsmaten.png',
					'howToMeasure' => __( 'How to measure your body measurements', 'atelier-irisee-master-plugin' ),
				)
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts       = shortcode_atts( array( 'id' => 0 ), $atts, self::TAG );
		$product_id = absint( $atts['id'] );
		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			} elseif ( is_singular( 'product' ) ) {
				$product_id = get_queried_object_id();
			}
		}
		$the_product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $the_product || $the_product->get_parent_id() ) {
			return '';
		}
		if ( 'publish' !== $the_product->get_status() && ! current_user_can( 'edit_post', $the_product->get_id() ) ) {
			return '';
		}

		ob_start();
		self::render( $the_product );
		return ob_get_clean();
	}

	/**
	 * Print a product page. Sets the global $product and $post while rendering, as WooCommerce templates expect.
	 *
	 * @param WC_Product $the_product Product.
	 * @param array      $args        [ breadcrumb: bool ].
	 */
	public static function render( $the_product, $args = array() ) {
		global $product, $post;

		$args = wp_parse_args( $args, array( 'breadcrumb' => true ) );
		self::enqueue();

		$previous_product = $product;
		$previous_post    = $post;
		$product          = $the_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce templates read the global product.
		$post             = get_post( $the_product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		setup_postdata( $post );

		if ( post_password_required( $post ) ) {
			echo get_the_password_form( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core form.
		} else {
			$data     = self::data( $the_product );
			$template = locate_template( 'atelier-irisee/product/single.php' );
			self::$rendering = true;
			include $template ? $template : AIMP_PLUGIN_DIR . 'templates/product/single.php';
			self::$rendering = false;

			if ( isset( WC()->structured_data ) && is_object( WC()->structured_data ) ) {
				WC()->structured_data->generate_product_data( $the_product );
			}
		}

		$product = $previous_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $post ) {
			setup_postdata( $post );
		} else {
			wp_reset_postdata();
		}
	}

	/**
	 * Everything the template needs, prepared once.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function data( $product ) {
		$per_10cm = AIMP_Catalog::sold_per_10cm( $product );
		$pattern  = AIMP_Catalog::get_pattern( $product->get_id() );
		$fabric   = AIMP_Catalog::in_categories( $product, AIMP_Catalog::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) );
		$notion   = $fabric ? '' : AIMP_Notions::kind_of( $product );

		// Patterns show no size pictures (sizes are chosen in the configurator); other variable products do.
		$extra = array();
		if ( $product->is_type( 'variable' ) && ! $pattern ) {
			foreach ( $product->get_children() as $child_id ) {
				$extra[] = (int) get_post_thumbnail_id( $child_id );
			}
		}
		$gallery = AIMP_Catalog::images( $product, $extra );

		$configurator = AIMP_Settings::get( 'configurator_page' );

		return array(
			'per_10cm'         => $per_10cm,
			'pattern'          => (bool) $pattern,
			'skill'            => $pattern ? (string) $product->get_meta( AIMP_Catalog::META_SKILL ) : '',
			'sizes_text'       => $pattern ? (string) $product->get_meta( AIMP_Catalog::META_SIZES_TEXT ) : '',
			'project_time'     => $pattern ? (string) $product->get_meta( AIMP_Catalog::META_PROJECT_TIME ) : '',
			'recommended'      => self::recommended_product( $product ),
			'fabrics'          => $pattern ? self::recommended_fabrics( $pattern ) : array(),
			'buy'              => self::buy_data( $product, $per_10cm ),
			'fabric'           => $fabric ? AIMP_Catalog::fabric_texts( $product ) : null,
			// Buttons: specifications (tiles) and "Ideal for" (clothes with an icon).
			// Haberdashery (buttons, zips, …): specifications (tiles), "Ideal for", how it is sold, order information.
			'notion'           => '' !== $notion ? self::notion_data( $product ) : null,
			'gallery'          => $gallery,
			'attributes'       => AIMP_Catalog::attributes( $product, (bool) $pattern ),
			// The configurator; from a pattern it opens with that pattern already chosen.
			'configurator_url' => ( $configurator && 'publish' === get_post_status( $configurator ) )
				? ( $pattern ? add_query_arg( 'aimp_pattern', $product->get_id(), get_permalink( $configurator ) ) : self::recommended_configurator_url( $product, get_permalink( $configurator ) ) )
				: '',
			// Accessories and the size table (per design for a pack with designs).
			'size_table'       => $pattern ? self::size_table( $product ) : array(),
			// A pack with designs: per design its name, project time and configurator link.
			'designs'          => $pattern ? self::design_list( $product, ( $configurator && 'publish' === get_post_status( $configurator ) ) ? get_permalink( $configurator ) : '' ) : array(),
			'fitting'          => $fabric ? self::fitting_patterns( $product ) : ( '' !== $notion ? self::fitting_notion_patterns( $product ) : array() ),
			'related'          => array_filter( array_map( 'wc_get_product', wc_get_related_products( $product->get_id(), self::related_count() ) ) ),
		);
	}

	/**
	 * A haberdashery product's page data: [ kind, specs: subject => [ label, value ], ideal_for: [ [ key, label ] ],
	 * set_size, order_info ].
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function notion_data( $product ) {
		$options = AIMP_Notions::ideal_for_options();
		$ideal   = array();
		foreach ( AIMP_Notions::ideal_for( $product ) as $key ) {
			$ideal[] = array( $key, $options[ $key ] );
		}
		return array(
			'kind'       => AIMP_Notions::kind_of( $product ),
			'specs'      => AIMP_Notions::specs( $product ),
			'ideal_for'  => $ideal,
			'set_size'   => AIMP_Notions::set_size( $product ),
			'order_info' => (string) $product->get_meta( AIMP_Catalog::META_ORDER_INFO ),
		);
	}

	/**
	 * Patterns that use this product: a rule of its kind and type is switched on, its size is within the
	 * pattern's range, and a size needs it (zips: a size needs exactly this length).
	 *
	 * @param WC_Product $product Haberdashery product.
	 * @return WC_Product[]
	 */
	public static function fitting_notion_patterns( $product ) {
		$key = 'aimp_shop_habfits_' . $product->get_id() . '_' . AIMP_Shop::cache_version();
		$ids = get_transient( $key );

		if ( ! is_array( $ids ) ) {
			$ids    = array();
			$root   = AIMP_Settings::get( 'pattern_cat' );
			$kind   = AIMP_Notions::kind_of( $product );
			$fields = AIMP_Catalog::material_fields();
			$length = AIMP_Notions::number( $product, 'length' );
			if ( $root && '' !== $kind ) {
				$args                   = AIMP_Catalog::base_query_args( array( $root ), 'variable' );
				$args['posts_per_page'] = -1;
				$args['no_found_rows']  = true;
				foreach ( ( new WP_Query( $args ) )->posts as $pattern_id ) {
					$pattern = wc_get_product( $pattern_id );
					$fits    = false;
					foreach ( AIMP_Notions::rules() as $rule => $info ) {
						if ( $info[0] !== $kind ) {
							continue;
						}
						foreach ( $pattern ? $pattern->get_children() : array() as $child_id ) {
							// Each size with the rules of its design.
							$rules = AIMP_Notions::pattern_rules( (int) $pattern_id, AIMP_Catalog::designs( $pattern ) ? AIMP_Catalog::variation_design( $child_id ) : '' );
							if ( ! AIMP_Notions::fits( $product, $rule, $rules[ $rule ] ) ) {
								continue;
							}
							$amount = (float) get_post_meta( $child_id, $fields[ $info[2] ]['meta'], true );
							if ( $amount <= 0 && '' !== $rules[ $rule ]['amount'] ) {
								$amount = (float) $rules[ $rule ]['amount'];
							}
							if ( $amount > 0 && ( 'zip' !== $kind || abs( $amount - $length ) < 0.01 ) ) {
								$fits = true;
								break 2;
							}
						}
					}
					if ( $fits ) {
						$ids[] = (int) $pattern_id;
					}
					if ( count( $ids ) >= self::FITTING ) {
						break;
					}
				}
			}
			set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		}

		return array_values(
			array_filter(
				array_map( 'wc_get_product', $ids ),
				function ( $item ) {
					return $item && $item->is_visible();
				}
			)
		);
	}

	/**
	 * Gold line icon for an "Ideal for" kind of clothes.
	 *
	 * @param string $key A key of AIMP_Notions::ideal_for_options().
	 * @param int|null $variant 0 = the standard icon, 1–5 an alternative; null = the one chosen in the settings.
	 * @return string SVG.
	 */
	public static function clothing_icon( $key, $variant = null ) {
		// The icon chosen under Atelier Irisee → Ideal for icons (0 = the standard one below).
		$variant     = null === $variant ? AIMP_Clothing_Icons::chosen( $key ) : (int) $variant;
		$alternative = AIMP_Clothing_Icons::variant( $key, $variant );
		$paths = array(
			// A shirt with a collar and a button placket.
			'shirt'    => '<path d="M9 3.5 5 5.5 2.5 10l3 1.5 1-2V20.5h11V9.5l1 2 3-1.5L19 5.5l-4-2"/><path d="M9 3.5 12 7l3-3.5"/><path d="M12 7v13.5"/><path d="M12 10.5h.01M12 14h.01M12 17.5h.01"/>',
			// A blouse with a round neck and puffed sleeves.
			'blouse'   => '<path d="M9 4c.5 1.6 1.6 2.5 3 2.5S14.5 5.6 15 4l4 2 2 4.5-3 1-1-1.5V20H7v-10l-1 1.5-3-1L5 6z"/><path d="M7 14c1.6.8 3.3 1.2 5 1.2s3.4-.4 5-1.2"/>',
			// A dress with a fitted top and a flared skirt.
			'dress'    => '<path d="M9 3l1 4-2 3 1 1-4 9.5h14L15 11l1-1-2-3 1-4"/><path d="M9 11h6"/><path d="M10 3h4"/>',
			// A flared skirt with a waistband.
			'skirt'    => '<path d="M7.5 5h9"/><path d="M7.5 5v2.5h9V5"/><path d="M7.5 7.5 4 20h16L16.5 7.5"/><path d="M10 7.5 9 20M14 7.5l1 12.5"/>',
			// Trousers.
			'trousers' => '<path d="M6.5 3.5h11l1.5 17h-5L12 9l-2 11.5H5z"/><path d="M6.5 6h11"/>',
			// Jeans with pockets and rivets.
			'jeans'    => '<path d="M6.5 3.5h11l1.5 17h-5L12 9l-2 11.5H5z"/><path d="M6.5 6h11"/><path d="M8 6c0 1.6.8 2.5 2 2.8M16 6c0 1.6-.8 2.5-2 2.8"/><circle cx="12" cy="4.8" r=".4"/>',
			// A jacket, open, with lapels.
			'jacket'   => '<path d="M8.5 3.5 4.5 5.5 3 12l2 .5V20.5h5.5L12 9l1.5 11.5H19V12.5l2-.5-1.5-6.5-4-2"/><path d="M8.5 3.5 12 9l3.5-5.5"/><path d="M6.5 15h2M15.5 15h2"/>',
			// A long coat with two rows of buttons.
			'coat'     => '<path d="M8.5 3 4.5 5 3 13l2 .3V21h14v-7.7l2-.3-1.5-8-4-2"/><path d="M8.5 3 12 7.5 15.5 3"/><path d="M12 7.5V21"/><path d="M10 11h.01M10 14.5h.01M10 18h.01M14 11h.01M14 14.5h.01M14 18h.01"/>',
			// A cardigan with a V-neck and buttons.
			'cardigan' => '<path d="M9 3.5 5 5.5 3.5 19l2.5.5 1-8V20.5h10v-9l1 8 2.5-.5L19 5.5l-4-2"/><path d="M9 3.5l3 7 3-7"/><path d="M12 10.5v10"/><path d="M12 13.5h.01M12 16.5h.01M7 19h10"/>',
			// A knitted jumper with ribbing.
			'knitwear' => '<path d="M9 3.5c.7 1.2 1.7 1.8 3 1.8s2.3-.6 3-1.8l4 2L20.5 19l-2.5.5-1-8V20.5H7v-9l-1 8-2.5-.5L5 5.5z"/><path d="M7 18.5h10M8.5 18.5v2M10.5 18.5v2M12.5 18.5v2M14.5 18.5v2"/>',
			// A small romper for children.
			'kids'     => '<path d="M9 4 6 5.5 4.5 9l2.5 1 1-1.5v5.5L7 20h4l1-4 1 4h4l-1-5.5V8.5l1 1.5 2.5-1L18 5.5 15 4c-.6 1.4-1.6 2-3 2S9.6 5.4 9 4z"/><path d="M10.5 10.5h.01M13.5 10.5h.01"/>',
			// A pyjama top with a moon.
			'pyjamas'  => '<path d="M9 4 5 5.5 3 11l2.5 1 1-2V20h11V10l1 2 2.5-1L19 5.5 15 4l-3 3z"/><path d="M14.5 13.5a2.5 2.5 0 1 1-2.6-2.9 2 2 0 0 0 2.6 2.9z"/>',
			// Dungarees for work.
			'workwear' => '<path d="M8 3v5.5M16 3v5.5"/><path d="M6.5 8.5h11l1.5 12h-5L12 13l-2 7.5H5z"/><path d="M9.5 8.5v3h5v-3"/>',
			// A bag with a handle.
			'bag'      => '<path d="M4.5 8.5h15l-1.2 11a1 1 0 0 1-1 .9H6.7a1 1 0 0 1-1-.9z"/><path d="M8.5 8.5V7a3.5 3.5 0 0 1 7 0v1.5"/><path d="M10 13h4"/>',
			// A cushion.
			'home'     => '<path d="M5 5c2.3.8 4.7 1.2 7 1.2S16.7 5.8 19 5c-.8 2.3-1.2 4.7-1.2 7s.4 4.7 1.2 7c-2.3-.8-4.7-1.2-7-1.2S7.3 18.2 5 19c.8-2.3 1.2-4.7 1.2-7S5.8 7.3 5 5z"/><circle cx="12" cy="12" r="1"/>',
		);
		$path  = '' !== $alternative ? $alternative : ( isset( $paths[ $key ] ) ? $paths[ $key ] : $paths['shirt'] );
		return '<svg class="aimp-ideal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/**
	 * Patterns with at least one size that allows this fabric.
	 *
	 * @param WC_Product $fabric Fabric product.
	 * @return WC_Product[]
	 */
	public static function fitting_patterns( $fabric ) {
		$key = 'aimp_shop_fits_' . $fabric->get_id() . '_' . AIMP_Shop::cache_version();
		$ids = get_transient( $key );

		if ( ! is_array( $ids ) ) {
			$ids = array();

			// The fabric's categories and their parents: a size that allows a parent allows its subcategories too.
			$fabric_terms = array();
			foreach ( wc_get_product_cat_ids( $fabric->get_id() ) as $term_id ) {
				$fabric_terms[] = (int) $term_id;
				$fabric_terms   = array_merge( $fabric_terms, array_map( 'absint', get_ancestors( $term_id, 'product_cat' ) ) );
			}

			$root = AIMP_Settings::get( 'pattern_cat' );
			if ( $fabric_terms && $root ) {
				$visibility = AIMP_Catalog::visibility_tax_query();
				$tax_query  = array(
					'relation' => 'AND',
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => array( $root ),
						'include_children' => true,
					),
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => array( 'variable' ),
					),
				);
				if ( $visibility ) {
					$tax_query[] = $visibility;
				}
				$patterns = new WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'fields'         => 'ids',
						'posts_per_page' => -1,
						'no_found_rows'  => true,
						'orderby'        => array(
							'menu_order' => 'ASC',
							'title'      => 'ASC',
						),
						'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					)
				);
				foreach ( $patterns->posts as $pattern_id ) {
					if ( array_intersect( AIMP_Catalog::pattern_fabric_cats( (int) $pattern_id ), $fabric_terms ) ) {
						$ids[] = (int) $pattern_id;
					}
					if ( count( $ids ) >= self::FITTING ) {
						break;
					}
				}
			}
			set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		}

		return array_values(
			array_filter(
				array_map( 'wc_get_product', $ids ),
				function ( $product ) {
					return $product && $product->is_visible();
				}
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Template helpers
	 * ------------------------------------------------------------------ */

	/**
	 * A product card that links to the product page (same look as the configurator cards).
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function card_html( $product ) {
		$card   = AIMP_Catalog::card( $product );
		$suffix = ( '' !== AIMP_Catalog::price_suffix( $product ) ? ' <small>' . esc_html( AIMP_Catalog::price_suffix( $product ) ) . '</small>' : '' );
		return sprintf(
			'<li class="aimp-product-card aimp-fav-wrap"><a class="aimp-card" href="%1$s"><span class="aimp-card-image"><img src="%2$s" alt="%3$s" loading="lazy"></span><span class="aimp-card-name">%4$s</span><span class="aimp-card-price">%5$s%6$s</span></a>%7$s</li>',
			esc_url( $card['permalink'] ),
			esc_url( $card['image'] ),
			esc_attr( '' !== $card['image_alt'] ? $card['image_alt'] : $card['name'] ),
			// Haberdashery: the diameter, length or width under the name.
			esc_html( $card['name'] ) . ( '' !== AIMP_Notions::main_text( $product ) ? '<small class="aimp-card-sub">' . esc_html( AIMP_Notions::main_text( $product ) ) . '</small>' : '' ),
			wp_kses_post( $card['price_html'] ),
			$suffix, // Escaped above.
			AIMP_Favorites::button_html( $product->get_id(), 'aimp-fav--overlay' )
		);
	}

	/**
	 * The brand of a product (WooCommerce Brands), e.g. "YKK"; several are joined with a comma. '' without one.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function brand( $product ) {
		if ( ! $product instanceof WC_Product || ! taxonomy_exists( 'product_brand' ) ) {
			return '';
		}
		$id    = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$terms = get_the_terms( $id, 'product_brand' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * The page of the product's (first) brand, or ''.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function brand_url( $product ) {
		if ( ! $product instanceof WC_Product || ! taxonomy_exists( 'product_brand' ) ) {
			return '';
		}
		$terms = get_the_terms( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), 'product_brand' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$link = get_term_link( $terms[0] );
		return is_wp_error( $link ) ? '' : $link;
	}

	/**
	 * The icon name of a configurator step (AIMP_Catalog::notion_types()): zips, buttons, bias, elastic, interfacing, … .
	 *
	 * @param string $type Step key.
	 * @param array  $info Its notion_types() entry.
	 * @return string
	 */
	public static function notion_icon( $type, $info ) {
		if ( 'shoulder_pads' === $type ) {
			return 'shoulder_pad';
		}
		if ( 'zip' === $info['kind'] ) {
			return 'zips';
		}
		if ( 'count' === $info['kind'] ) {
			return 'buttons';
		}
		if ( in_array( $type, array( 'bias', 'elastic', 'interfacing' ), true ) ) {
			return $type;
		}
		return 'interfacing' === $info['notion_kind'] ? 'interfacing' : 'ribbons';
	}

	/**
	 * The icon name of a material (a key of AIMP_Catalog::material_fields()).
	 *
	 * @param string $key Material.
	 * @return string
	 */
	public static function material_icon( $key ) {
		foreach ( AIMP_Catalog::notion_types() as $type => $info ) {
			if ( $info['material'] === $key ) {
				return self::notion_icon( $type, $info );
			}
		}
		// Not sold in the kit (embroidery, bag strap, cord lock): a length or a number.
		$fields = AIMP_Catalog::material_fields();
		return isset( $fields[ $key ] ) && 'cm' === $fields[ $key ]['unit'] ? 'length' : 'set';
	}

	/**
	 * Gold line icon for a fabric subject: specifications (a round chart for the composition, a thread spool
	 * for the type, a palette, a width arrow, a weight) and washing (a wash tub, a tumble dryer, an iron, a light bulb for tips).
	 *
	 * @param string $subject A subject key of AIMP_Catalog::fabric_text_fields().
	 * @return string SVG.
	 */
	public static function fabric_icon( $subject ) {
		$paths = array(
			'composition' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 3.5V12l6 6"/><path d="M12 12H3.5"/>',
			'type'        => '<path d="M6.5 4h11M6.5 20h11"/><path d="M8 4v16M16 4v16"/><path d="M8 7.5l8 2.5M8 11.5l8 2.5M8 15.5l8 2"/>',
			'colour'      => '<path d="M12 3.5a8.5 8.5 0 1 0 0 17c.9 0 1.5-.7 1.5-1.5 0-.4-.2-.8-.4-1.1-.3-.3-.4-.6-.4-1 0-.8.7-1.5 1.5-1.5h1.8a4.5 4.5 0 0 0 4.5-4.5c0-4-3.8-7.4-8.5-7.4z"/><circle cx="7.5" cy="11" r=".9"/><circle cx="10" cy="7.3" r=".9"/><circle cx="14.5" cy="7.3" r=".9"/><circle cx="17" cy="11" r=".9"/>',
			'width'       => '<path d="M3 12h18"/><path d="M7 8l-4 4 4 4M17 8l4 4-4 4"/><path d="M3 5v14M21 5v14"/>',
			'weight'      => '<path d="M6.5 9.5h11l2 10.5h-15z"/><circle cx="12" cy="6.2" r="2.4"/>',
			'washing'     => '<path d="M3 7l2.2 12.2a1 1 0 0 0 1 .8h11.6a1 1 0 0 0 1-.8L21 7"/><path d="M3.6 10.5c1.4 1 2.8 1 4.2 0s2.8-1 4.2 0 2.8 1 4.2 0 2.8-1 4.2 0"/>',
			'drying'      => '<rect x="3.5" y="3.5" width="17" height="17" rx="2"/><circle cx="12" cy="12" r="5"/>',
			'ironing'     => '<path d="M3 17.5h18l-1.6-6.4a3 3 0 0 0-2.9-2.3H8.5"/><path d="M3 17.5c.6-4.3 3.7-6.9 8-6.9h8.6"/><path d="M9 14.5h.01M12 14.5h.01M15 14.5h.01"/>',
			'tips'        => '<path d="M9.5 18h5M10.5 21h3"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.7.6 1.1 1.3 1.1 2.2h5c0-.9.4-1.6 1.1-2.2A6 6 0 0 0 12 3z"/>',
			// A seal with a tick.
			'certification' => '<circle cx="12" cy="9" r="5.5"/><path d="M9.6 9.1l1.6 1.6 3.3-3.3"/><path d="M8.6 13.4L7 21l5-2.6 5 2.6-1.6-7.6"/>',
			// Configurator: what a size needs.
			// A roll of fabric seen from the side, with its loose end hanging down.
			'fabric'      => '<ellipse cx="6" cy="8" rx="2.5" ry="4.5"/><ellipse cx="6" cy="8" rx="0.9" ry="1.6"/><path d="M6 3.5h12a2.5 4.5 0 0 1 0 9H6"/><path d="M11 12.5v8h9.5l-1.6-8.2"/>',
			// Pattern page: skill level, sizes, project time.
			'sewing_machine' => '<rect x="3" y="17" width="18" height="3" rx="1"/><path d="M6 17V7.5A2.5 2.5 0 0 1 8.5 5H18a2 2 0 0 1 2 2v3.5h-8.5V17"/><path d="M17 10.5v3.5M15.5 14h3"/><circle cx="16.5" cy="7.6" r="0.9"/>',
			'tape_measure' => '<circle cx="9" cy="12" r="6"/><circle cx="9" cy="12" r="2"/><path d="M9 18h12v-4h-6.5"/><path d="M13 18v-1.6M16 18v-1.6M19 18v-1.6"/>',
			'clock'        => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.2 2"/><path d="M12 4.2v.01M19.8 12h.01M12 19.8v.01M4.2 12h.01"/>',
			// Two pattern pieces, one over the other: designs in one pack.
			'designs'      => '<path d="M4 8.5 8 5h3l1 2 1-2h3l4 3.5-2 2.5-2-1.5V19H8V9.5L6 11z"/><path d="M8 14h8"/>',
			'buttons'     => '<circle cx="12" cy="12" r="8.5"/><circle cx="10" cy="10" r="1"/><circle cx="14" cy="10" r="1"/><circle cx="10" cy="14" r="1"/><circle cx="14" cy="14" r="1"/>',
			'zips'        => '<path d="M12 3v12"/><path d="M9.5 5H12M12 7.5h2.5M9.5 10H12M12 12.5h2.5"/><rect x="9" y="15" width="6" height="6" rx="1.5"/>',
			'ribbons'     => '<path d="M12 10c-2-3.5-6.5-4-7-1.5S8 13 12 10zm0 0c2-3.5 6.5-4 7-1.5S16 13 12 10z"/><path d="M11 11l-3 9M13 11l3 9"/>',
			'bias'        => '<path d="M3 16c3-6 6-6 9 0s6 6 9 0"/><path d="M3 11c3-6 6-6 9 0s6 6 9 0"/>',
			// Elastic: a band with stretch marks; interfacing: a sheet with an iron-on dot pattern.
			'elastic'     => '<path d="M2.5 9.5h19v5h-19z"/><path d="M6 9.5l1.5 5M10 9.5l1.5 5M14 9.5l1.5 5M18 9.5l1.5 5"/>',
			// A shoulder pad: a rounded half-moon with its stitching.
			'shoulder_pad' => '<path d="M3.5 16.5C5 9.5 8.5 6.5 12 6.5s7 3 8.5 10z"/><path d="M6.5 14.5c1.3-3.8 3.3-5.5 5.5-5.5s4.2 1.7 5.5 5.5" stroke-dasharray="1.6 1.6"/>',
			'interfacing' => '<path d="M5 3.5h10l4 4v13H5z"/><path d="M15 3.5v4h4"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01M8.5 14.5h.01M12 14.5h.01M15.5 14.5h.01M8.5 18h.01M12 18h.01"/>',
			// Button page: type (a button with four holes), diameter (a button between two measuring lines),
			// material, surface finish (a sparkle), mounting method (a needle and thread), sold per (a stack).
			'button_type'     => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="6"/><circle cx="10.3" cy="10.3" r=".9"/><circle cx="13.7" cy="10.3" r=".9"/><circle cx="10.3" cy="13.7" r=".9"/><circle cx="13.7" cy="13.7" r=".9"/>',
			'diameter'        => '<circle cx="12" cy="12" r="6"/><path d="M3 3.5v17M21 3.5v17"/><path d="M3 12h3M18 12h3"/>',
			'button_material' => '<path d="M12 3.5 20 8v8l-8 4.5L4 16V8z"/><path d="M4 8l8 4.5L20 8M12 12.5v8"/>',
			'finish'          => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M18.5 16l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>',
			'mounting'        => '<path d="M19.5 4.5 7 17"/><path d="M17.5 3.5l3 3"/><ellipse cx="18.6" cy="5.4" rx=".5" ry="1.2" transform="rotate(45 18.6 5.4)"/><path d="M7 17c-2 1-3.5.5-4 2s1.5 2 3 1 1.5-2.5 4-2.5"/>',
			'set'             => '<circle cx="8" cy="9" r="4"/><circle cx="16" cy="9" r="4"/><circle cx="12" cy="16" r="4"/>',
			// Length: a ruler (the width uses the fabric width arrows).
			'length'          => '<rect x="2.5" y="8.5" width="19" height="7" rx="1"/><path d="M6 8.5v3M9.5 8.5v2M13 8.5v3M16.5 8.5v2M20 8.5v3"/>',
			// Lining: a jacket opened at the front, its lining showing.
			'lining'          => '<path d="M8.5 3.5 4 6l-1 14h6.5L12 9l2.5 11H21l-1-14-4.5-2.5"/><path d="M8.5 3.5 12 9l3.5-5.5"/><path d="M12 9l-1.6 7.5M12 9l1.6 7.5" stroke-dasharray="1.5 1.5"/>',
		);
		$path  = isset( $paths[ $subject ] ) ? $paths[ $subject ] : $paths['tips'];
		return '<svg class="aimp-washing-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/**
	 * The product chosen in "Atelier Irisee recommendation": a fabric for a pattern, a pattern for
	 * everything else. Only while it is still a visible product of that kind.
	 *
	 * @param WC_Product $product Product.
	 * @return WC_Product|null
	 */
	public static function recommended_product( $product ) {
		$id = absint( $product->get_meta( AIMP_Catalog::META_RECOMMENDED ) );
		if ( ! $id ) {
			return null;
		}
		if ( AIMP_Catalog::get_pattern( $product->get_id() ) ) {
			$fabric = wc_get_product( $id );
			$ok     = $fabric && 'publish' === $fabric->get_status() && AIMP_Catalog::in_categories( $fabric, AIMP_Catalog::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) );
			return ( $ok && $fabric->is_visible() ) ? self::recommended_box( $fabric ) : null;
		}
		// A pattern, or one design of a pack (saved as the design's first size).
		$object  = wc_get_product( $id );
		$design  = $object && $object->is_type( 'variation' ) ? AIMP_Catalog::variation_design( $object ) : '';
		$pattern = AIMP_Catalog::get_pattern( $object && $object->is_type( 'variation' ) ? $object->get_parent_id() : $id );
		if ( ! $pattern || ! $pattern->is_visible() ) {
			return null;
		}
		$first = '' !== $design ? AIMP_Catalog::design_first_sizes( $pattern ) : array();
		if ( isset( $first[ $design ] ) ) {
			$card = AIMP_Catalog::design_card( $pattern, $design, $first[ $design ] );
			return array(
				'url'        => $pattern->get_permalink(),
				'image'      => sprintf( '<img src="%1$s" alt="%2$s" class="aimp-recommended-image" loading="lazy">', esc_url( $card['image'] ), esc_attr( '' !== $card['image_alt'] ? $card['image_alt'] : $card['name'] ) ),
				'name'       => $card['name'],
				'pattern_id' => (int) $first[ $design ],
			);
		}
		return self::recommended_box( $pattern );
	}

	/**
	 * The recommendation box of a product: [ url, image (HTML), name, pattern_id ].
	 *
	 * @param WC_Product $product Fabric or pattern.
	 * @return array
	 */
	private static function recommended_box( $product ) {
		return array(
			'url'        => $product->get_permalink(),
			'image'      => $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'aimp-recommended-image' ) ),
			'name'       => wp_strip_all_tags( $product->get_name() ),
			'pattern_id' => AIMP_Catalog::get_pattern( $product->get_id() ) ? $product->get_id() : 0,
		);
	}

	/**
	 * The configurator from a fabric or haberdashery product: with its recommended pattern (or design) already
	 * chosen, otherwise empty.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $url     Configurator page.
	 * @return string
	 */
	private static function recommended_configurator_url( $product, $url ) {
		$rec = self::recommended_product( $product );
		return $rec && ! empty( $rec['pattern_id'] ) ? add_query_arg( 'aimp_pattern', $rec['pattern_id'], $url ) : $url;
	}

	/**
	 * Fabrics that can be used for a pattern: from the fabric categories its sizes allow, the pattern's
	 * preferred categories first (same order as the configurator's "Recommended" sort).
	 *
	 * @param WC_Product $pattern Pattern product.
	 * @return WC_Product[]
	 */
	public static function recommended_fabrics( $pattern ) {
		$key = 'aimp_shop_recfab_' . $pattern->get_id() . '_' . AIMP_Shop::cache_version();
		$ids = get_transient( $key );

		if ( ! is_array( $ids ) ) {
			$ids  = array();
			$cats = array();
			foreach ( $pattern->get_children() as $variation_id ) {
				$req = AIMP_Catalog::get_requirements( $variation_id );
				if ( $req ) {
					$cats = array_merge( $cats, $req['fabric_cats'] );
				}
			}
			$cats = array_values( array_unique( $cats ) );
			if ( $cats ) {
				$args                   = AIMP_Catalog::base_query_args( $cats, 'simple' );
				$args['posts_per_page'] = 60;
				$args['no_found_rows']  = true;
				$query                  = new WP_Query( $args );
				$ids                    = AIMP_Catalog::sort_by_category_priority( array_map( 'absint', $query->posts ), AIMP_Catalog::get_fabric_priority( $pattern ) );
				$ids                    = array_slice( $ids, 0, self::FITTING );
			}
			set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		}

		return array_values(
			array_filter(
				array_map( 'wc_get_product', $ids ),
				function ( $product ) {
					return $product && $product->is_visible();
				}
			)
		);
	}

	/**
	 * Language flags as links; product.js also stores the choice so the next pages follow.
	 *
	 * @return string
	 */
	public static function languages_html() {
		$current = AIMP_I18n::current();
		$html    = '<div class="aimp-languages" role="group" aria-label="' . esc_attr__( 'Language', 'atelier-irisee-master-plugin' ) . '">';
		foreach ( AIMP_Shortcode::languages_data() as $language ) {
			$active = $language['code'] === $current;
			$html  .= sprintf(
				'<a class="aimp-language%1$s" href="%2$s" data-lang="%3$s" lang="%4$s"%5$s><img src="%6$s" alt="" width="24" height="16"><span>%7$s</span></a>',
				$active ? ' is-active' : '',
				esc_url( add_query_arg( 'aimp_lang', $language['code'] ) ),
				esc_attr( $language['code'] ),
				esc_attr( $language['locale'] ),
				$active ? ' aria-current="true"' : '',
				esc_url( $language['flag'] ),
				esc_html( $language['name'] )
			);
		}
		return $html . '</div>';
	}
}
