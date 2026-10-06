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
		);
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
			'recommended'      => self::recommended_product( $product ),
			'fabrics'          => $pattern ? self::recommended_fabrics( $pattern ) : array(),
			'buy'              => self::buy_data( $product, $per_10cm ),
			'fabric'           => $fabric ? AIMP_Catalog::fabric_texts( $product ) : null,
			'gallery'          => $gallery,
			'attributes'       => AIMP_Catalog::attributes( $product, (bool) $pattern ),
			// The configurator; from a pattern it opens with that pattern already chosen.
			'configurator_url' => ( $configurator && 'publish' === get_post_status( $configurator ) )
				? ( $pattern ? add_query_arg( 'aimp_pattern', $product->get_id(), get_permalink( $configurator ) ) : get_permalink( $configurator ) )
				: '',
			'fitting'          => $fabric ? self::fitting_patterns( $product ) : array(),
			'related'          => array_filter( array_map( 'wc_get_product', wc_get_related_products( $product->get_id(), self::RELATED ) ) ),
		);
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
					foreach ( get_children(
						array(
							'post_parent' => $pattern_id,
							'post_type'   => 'product_variation',
							'post_status' => 'publish',
							'fields'      => 'ids',
						)
					) as $variation_id ) {
						$allowed = get_post_meta( $variation_id, AIMP_Catalog::META_FABRIC_CATS, true );
						if ( is_array( $allowed ) && array_intersect( array_map( 'absint', $allowed ), $fabric_terms ) ) {
							$ids[] = (int) $pattern_id;
							break;
						}
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
		$suffix = AIMP_Catalog::sold_per_10cm( $product ) ? ' <small>' . esc_html__( 'per 10 cm', 'atelier-irisee-master-plugin' ) . '</small>' : '';
		return sprintf(
			'<li class="aimp-product-card aimp-fav-wrap"><a class="aimp-card" href="%1$s"><span class="aimp-card-image"><img src="%2$s" alt="%3$s" loading="lazy"></span><span class="aimp-card-name">%4$s</span><span class="aimp-card-price">%5$s%6$s</span></a>%7$s</li>',
			esc_url( $card['permalink'] ),
			esc_url( $card['image'] ),
			esc_attr( '' !== $card['image_alt'] ? $card['image_alt'] : $card['name'] ),
			esc_html( $card['name'] ),
			wp_kses_post( $card['price_html'] ),
			$suffix, // Escaped above.
			AIMP_Favorites::button_html( $product->get_id(), 'aimp-fav--overlay' )
		);
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
			return ( $ok && $fabric->is_visible() ) ? $fabric : null;
		}
		$pattern = AIMP_Catalog::get_pattern( $id );
		return ( $pattern && $pattern->is_visible() ) ? $pattern : null;
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
