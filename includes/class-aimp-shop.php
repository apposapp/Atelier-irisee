<?php
/**
 * Shop pages: [atelier_irisee_shop type="all|patterns|fabrics|haberdashery"].
 *
 * A filter sidebar, the product grid and a details panel, in the configurator's split-view design.
 * Products and filters come from the public, read-only ?wc-ajax=aimp_shop endpoint.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Shop {

	const TAG = 'atelier_irisee_shop';

	const TYPES = array( 'all', 'patterns', 'fabrics', 'haberdashery' );

	const SORTS = array( 'recommended', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'newest' );

	/** Option holding a number that changes whenever products or categories change (part of every cache key). */
	const CACHE_OPTION = 'aimp_shop_cache_version';

	/** Prefix of the transients that hold the filter options. */
	const CACHE_PREFIX = 'aimp_shop_';

	private static $localized = false;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		// [atelier_irisee_haberdashery]: the overview of the haberdashery kinds (as on the Haberdashery page), anywhere.
		add_shortcode( 'atelier_irisee_haberdashery', array( __CLASS__, 'haberdashery_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_action( 'wc_ajax_aimp_shop', array( __CLASS__, 'ajax' ) );

		// Product, stock and settings changes start a new cache version (shop filters, the first page of products in
		// the page, and the customizer's pattern data).
		foreach ( array( 'save_post_product', 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_delete_product', 'woocommerce_trash_product', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'update_option_' . AIMP_Settings::OPTION ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_cache' ) );
		}
		foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_term_cache' ), 10, 3 );
		}

		// Category and shop links lead to the shop pages chosen in the settings.
		add_filter( 'term_link', array( __CLASS__, 'term_link' ), 20, 3 );
		add_filter( 'woocommerce_get_shop_page_permalink', array( __CLASS__, 'shop_link' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_archives' ) );

		// Every category and brand gets an address of its own under its shop page (/stoffen/boucle/,
		// /alle-producten/merk/cousette/), with its own title and heading (AIMP_SEO): pages search engines can list.
		add_action( 'init', array( __CLASS__, 'rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'request', array( __CLASS__, 'check_request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_filter_links' ), 5 );
	}

	/* ------------------------------------------------------------------
	 * Category and brand pages: /{shop page}/{category}/ and /{All products}/merk/{brand}/
	 * ------------------------------------------------------------------ */

	/** The part of a brand page's address after the All products page. */
	const BRAND_BASE = 'merk';

	/** Option: the rules last written, so WordPress's rewrite rules are only renewed when a shop page changes. */
	const RULES_OPTION = 'aimp_shop_rules';

	/**
	 * Whether categories and brands get their own address (pretty permalinks and the category routing on).
	 *
	 * @return bool
	 */
	public static function clean_urls() {
		return (bool) AIMP_Settings::get( 'category_redirect' ) && '' !== (string) get_option( 'permalink_structure' );
	}

	/**
	 * The shop pages: page id => type, for patterns, fabrics, haberdashery and all products.
	 *
	 * @return array
	 */
	private static function type_pages() {
		$pages = array();
		foreach ( array( 'patterns', 'fabrics', 'haberdashery', 'all' ) as $type ) {
			$page = self::page_for_type( $type );
			if ( $page && ! isset( $pages[ $page ] ) ) {
				$pages[ $page ] = $type;
			}
		}
		return $pages;
	}

	public static function rewrite_rules() {
		if ( ! self::clean_urls() ) {
			return;
		}
		$rules = array();
		$all   = self::page_for_type( 'all' );
		foreach ( self::type_pages() as $page => $type ) {
			$uri = get_page_uri( $page );
			if ( ! $uri ) {
				continue;
			}
			$base = '^' . str_replace( '#', '\#', preg_quote( $uri ) );
			if ( $page === $all ) {
				$rules[ $base . '/' . self::BRAND_BASE . '/([^/]+)/?$' ] = 'index.php?page_id=' . $page . '&aimp_brand_slug=$matches[1]';
			}
			$rules[ $base . '/([^/]+)/?$' ] = 'index.php?page_id=' . $page . '&aimp_term=$matches[1]';
		}
		foreach ( $rules as $regex => $query ) {
			add_rewrite_rule( $regex, $query, 'top' );
		}
		// New or changed shop pages: WordPress writes its rules again (once).
		$signature = md5( wp_json_encode( $rules ) );
		if ( get_option( self::RULES_OPTION ) !== $signature ) {
			update_option( self::RULES_OPTION, $signature, true );
			add_action(
				'wp_loaded',
				function () {
					flush_rewrite_rules( false );
				}
			);
		}
	}

	/**
	 * @param array $vars Public query variables.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'aimp_term';
		$vars[] = 'aimp_brand_slug';
		return $vars;
	}

	/**
	 * A category or brand address that is not one (a child page with that name, or a mistake): the page at that
	 * address, or "not found".
	 *
	 * @param array $vars Query.
	 * @return array
	 */
	public static function check_request( $vars ) {
		if ( empty( $vars['page_id'] ) || ( empty( $vars['aimp_term'] ) && empty( $vars['aimp_brand_slug'] ) ) ) {
			return $vars;
		}
		if ( self::request_term( $vars ) ) {
			return $vars;
		}
		$uri  = get_page_uri( (int) $vars['page_id'] );
		$slug = ! empty( $vars['aimp_term'] ) ? $vars['aimp_term'] : self::BRAND_BASE . '/' . $vars['aimp_brand_slug'];
		return array( 'pagename' => $uri . '/' . $slug );
	}

	/**
	 * The category or brand of a category or brand address, when it really belongs to that shop page.
	 *
	 * @param array|null $vars Query (null: the current one).
	 * @return array|null [ term, kind (category|brand), page id, type ]
	 */
	public static function request_term( $vars = null ) {
		static $current = false;
		$own = null === $vars;
		if ( $own ) {
			if ( false !== $current ) {
				return $current;
			}
			$vars = array(
				'page_id'         => get_query_var( 'page_id' ),
				'aimp_term'       => get_query_var( 'aimp_term' ),
				'aimp_brand_slug' => get_query_var( 'aimp_brand_slug' ),
			);
		}
		$found = null;
		$page  = isset( $vars['page_id'] ) ? (int) $vars['page_id'] : 0;
		$types = self::type_pages();
		if ( $page && isset( $types[ $page ] ) && self::clean_urls() ) {
			if ( ! empty( $vars['aimp_term'] ) ) {
				$slug = sanitize_title( $vars['aimp_term'] );
				foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'slug' => $slug, 'hide_empty' => false ) ) as $term ) {
					// Only where the category's own address is this one (a fabric category under Fabrics, …).
					if ( $term instanceof WP_Term && trailingslashit( get_permalink( $page ) ) . $term->slug . '/' === self::category_url( $term ) ) {
						$found = array( $term, 'category', $page, $types[ $page ] );
						break;
					}
				}
			} elseif ( ! empty( $vars['aimp_brand_slug'] ) && taxonomy_exists( 'product_brand' ) && self::page_for_type( 'all' ) === $page ) {
				$term = get_term_by( 'slug', sanitize_title( $vars['aimp_brand_slug'] ), 'product_brand' );
				if ( $term instanceof WP_Term ) {
					$found = array( $term, 'brand', $page, $types[ $page ] );
				}
			}
		}
		if ( $own ) {
			$current = $found;
		}
		return $found;
	}

	/**
	 * The address of a category or brand page, with a page number.
	 *
	 * @param WP_Term $term Category or brand.
	 * @param int     $page Page number.
	 * @return string
	 */
	public static function term_url( $term, $page = 1 ) {
		$url = 'product_brand' === $term->taxonomy ? self::brand_url( $term ) : self::category_url( $term );
		return $page > 1 && '' !== $url ? add_query_arg( 'aimp_page', (int) $page, $url ) : $url;
	}

	/**
	 * Old links with one category or brand as a filter (?aimp_cat=34, ?aimp_brand=cousette): to that category's or
	 * brand's own address, for good (301).
	 */
	public static function redirect_filter_links() {
		if ( ! self::clean_urls() || ! is_page() || is_admin() ) {
			return;
		}
		$types = self::type_pages();
		$page  = (int) get_queried_object_id();
		if ( ! isset( $types[ $page ] ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which filter the address has.
		$keys = array_filter(
			array_keys( $_GET ),
			function ( $key ) {
				return 0 === strpos( (string) $key, 'aimp_' ) && ! in_array( $key, array( 'aimp_lang', 'aimp_page', 'aimp_design_preview' ), true );
			}
		);
		if ( 1 !== count( $keys ) ) {
			return;
		}
		$key   = reset( $keys );
		$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		$num   = isset( $_GET['aimp_page'] ) ? absint( $_GET['aimp_page'] ) : 1;
		// phpcs:enable
		$term = null;
		if ( 'aimp_cat' === $key && ctype_digit( $value ) ) {
			$term = get_term( (int) $value, 'product_cat' );
		} elseif ( 'aimp_brand' === $key && taxonomy_exists( 'product_brand' ) ) {
			$term = get_term_by( 'slug', $value, 'product_brand' );
		}
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$url = self::term_url( $term, $num );
		// Only to a category page under this same shop page (other categories stay a filter here).
		if ( '' !== $url && 0 === strpos( $url, trailingslashit( get_permalink( $page ) ) ) && trailingslashit( get_permalink( $page ) ) !== $url ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}

	/* ------------------------------------------------------------------
	 * Category links → shop pages
	 * ------------------------------------------------------------------ */

	private static function routing_enabled() {
		return ! is_admin() && (bool) AIMP_Settings::get( 'category_redirect' );
	}

	/**
	 * The published page chosen for a page type, or 0.
	 *
	 * @param string $type all, patterns, fabrics or haberdashery.
	 * @return int
	 */
	public static function page_for_type( $type ) {
		$id = AIMP_Settings::get( 'page_' . $type );
		return ( $id && 'publish' === get_post_status( $id ) ) ? $id : 0;
	}

	/**
	 * Shop page address for a product category, with that category selected; '' when no page is set.
	 *
	 * @param WP_Term|int $term Product category.
	 * @return string
	 */
	public static function category_url( $term ) {
		$term_id = (int) ( $term instanceof WP_Term ? $term->term_id : $term );
		foreach ( array( 'patterns', 'fabrics', 'haberdashery' ) as $type ) {
			foreach ( self::roots( $type ) as $root ) {
				if ( ! in_array( $term_id, AIMP_Catalog::category_tree( $root ), true ) ) {
					continue;
				}
				$page = self::page_for_type( $type );
				if ( ! $page ) {
					return '';
				}
				// The Patterns or Fabrics category itself is the whole page; on the haberdashery page
				// Buttons, Zips, Ribbons and Bias tape are filters of their own.
				return ( $term_id === $root && 'haberdashery' !== $type )
					? get_permalink( $page )
					: self::under_page( $page, $term_id );
			}
		}
		$page = self::page_for_type( 'all' );
		return $page ? self::under_page( $page, $term_id ) : '';
	}

	/**
	 * A category's address for a menu or overview link: its own address, else the shop page with it as a filter.
	 *
	 * @param int    $term_id Category.
	 * @param string $url     The shop page.
	 * @return string
	 */
	public static function kind_url( $term_id, $url ) {
		$own = self::routing_enabled() ? self::category_url( (int) $term_id ) : '';
		return '' !== $own && $own !== $url ? $own : add_query_arg( 'aimp_cat', (int) $term_id, $url );
	}

	/**
	 * A category's address under a shop page: its own (/stoffen/boucle/), or the page with the category as a filter.
	 *
	 * @param int $page    Shop page.
	 * @param int $term_id Category.
	 * @return string
	 */
	private static function under_page( $page, $term_id ) {
		$term = get_term( $term_id, 'product_cat' );
		if ( self::clean_urls() && $term instanceof WP_Term && '' !== $term->slug ) {
			return trailingslashit( get_permalink( $page ) ) . $term->slug . '/';
		}
		return add_query_arg( 'aimp_cat', $term_id, get_permalink( $page ) );
	}

	/**
	 * @param string  $url      Term link.
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 * @return string
	 */
	public static function term_link( $url, $term, $taxonomy ) {
		if ( 'product_brand' === $taxonomy && self::routing_enabled() && $term instanceof WP_Term ) {
			$page_url = self::brand_url( $term );
			return '' !== $page_url ? $page_url : $url;
		}
		if ( 'product_cat' !== $taxonomy || ! self::routing_enabled() ) {
			return $url;
		}
		$page_url = self::category_url( $term );
		return '' !== $page_url ? $page_url : $url;
	}

	/**
	 * WooCommerce's shop link (breadcrumb "Shop", "Return to shop") → the All products page.
	 *
	 * @param string $url Shop page link.
	 * @return string
	 */
	public static function shop_link( $url ) {
		if ( ! self::routing_enabled() ) {
			return $url;
		}
		$page = self::page_for_type( 'all' );
		return ( $page && $page !== (int) wc_get_page_id( 'shop' ) ) ? get_permalink( $page ) : $url;
	}

	/**
	 * Old bookmarks and menu links to WooCommerce's category and shop pages.
	 */
	public static function redirect_archives() {
		if ( ! self::routing_enabled() || is_search() ) {
			return;
		}
		$url = '';
		if ( is_product_category() ) {
			$url = self::category_url( get_queried_object() );
		} elseif ( taxonomy_exists( 'product_brand' ) && is_tax( 'product_brand' ) && get_queried_object() instanceof WP_Term ) {
			$url = self::brand_url( get_queried_object() );
		} elseif ( is_shop() ) {
			$page = self::page_for_type( 'all' );
			$url  = ( $page && $page !== (int) wc_get_page_id( 'shop' ) ) ? get_permalink( $page ) : '';
		}
		if ( $url ) {
			// For good (301): search engines move the old category address to the new one.
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}

	/* ------------------------------------------------------------------
	 * Cache
	 * ------------------------------------------------------------------ */

	/**
	 * Part of every cache key of the shop: it changes with every shop change and with every plugin update (an update
	 * can add fields to the cached data).
	 *
	 * @return string
	 */
	public static function cache_version() {
		return (int) get_option( self::CACHE_OPTION, 1 ) . '-' . AIMP_VERSION;
	}

	public static function flush_cache() {
		update_option( self::CACHE_OPTION, (int) get_option( self::CACHE_OPTION, 1 ) + 1, false );
	}

	public static function flush_term_cache( $term_id, $tt_id, $taxonomy ) {
		if ( 'product_cat' === $taxonomy || 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
			self::flush_cache();
		}
	}

	/* ------------------------------------------------------------------
	 * Shortcode and assets
	 * ------------------------------------------------------------------ */

	public static function register_assets() {
		wp_register_style( 'aimp-shop', AIMP_PLUGIN_URL . 'assets/css/shop.css', array( 'aimp-configurator' ), AIMP_VERSION );
		wp_register_script( 'aimp-shop', AIMP_PLUGIN_URL . 'assets/js/shop.js', array( 'aimp-ui', 'aimp-favorites' ), AIMP_VERSION, true );

		// Load the stylesheets in <head> when we can already tell the page uses the shortcode.
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'aimp-shop' );
		}
	}

	/**
	 * Shop page texts in the active language.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'language'        => __( 'Language', 'atelier-irisee-master-plugin' ),
			'filters'         => __( 'Filters', 'atelier-irisee-master-plugin' ),
			'closeFilters'    => __( 'Close filters', 'atelier-irisee-master-plugin' ),
			'showResults'     => __( 'Show results', 'atelier-irisee-master-plugin' ),
			'search'          => __( 'Search', 'atelier-irisee-master-plugin' ),
			'searchProducts'  => __( 'Search products…', 'atelier-irisee-master-plugin' ),
			'category'        => __( 'Category', 'atelier-irisee-master-plugin' ),
			'all'             => __( 'All', 'atelier-irisee-master-plugin' ),
			'price'           => __( 'Price', 'atelier-irisee-master-plugin' ),
			'minPrice'        => __( 'Minimum price', 'atelier-irisee-master-plugin' ),
			'maxPrice'        => __( 'Maximum price', 'atelier-irisee-master-plugin' ),
			'availability'    => __( 'Availability', 'atelier-irisee-master-plugin' ),
			'inStockOnly'     => __( 'Only show items in stock', 'atelier-irisee-master-plugin' ),
			'sortBy'          => __( 'Sort by', 'atelier-irisee-master-plugin' ),
			'sortRecommended' => __( 'Recommended', 'atelier-irisee-master-plugin' ),
			'sortNameAsc'     => __( 'Name (A–Z)', 'atelier-irisee-master-plugin' ),
			'sortNameDesc'    => __( 'Name (Z–A)', 'atelier-irisee-master-plugin' ),
			'sortPriceAsc'    => __( 'Price (low to high)', 'atelier-irisee-master-plugin' ),
			'sortPriceDesc'   => __( 'Price (high to low)', 'atelier-irisee-master-plugin' ),
			'sortNewest'      => __( 'Newest', 'atelier-irisee-master-plugin' ),
			'skillLevel'      => __( 'Skill level', 'atelier-irisee-master-plugin' ),
			'clearFilters'    => __( 'Clear all filters', 'atelier-irisee-master-plugin' ),
			'clearAll'        => __( 'Clear all', 'atelier-irisee-master-plugin' ),
			/* translators: %s: filter, e.g. a colour */
			'removeFilter'    => __( 'Remove filter: %s', 'atelier-irisee-master-plugin' ),
			'resultOne'       => __( '%d product', 'atelier-irisee-master-plugin' ),
			'resultMany'      => __( '%d products', 'atelier-irisee-master-plugin' ),
			'loading'         => __( 'Loading…', 'atelier-irisee-master-plugin' ),
			'error'           => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
			'previous'        => __( 'Previous', 'atelier-irisee-master-plugin' ),
			'next'            => __( 'Next', 'atelier-irisee-master-plugin' ),
			'close'           => __( 'Close', 'atelier-irisee-master-plugin' ),
			'pagination'      => __( 'Pagination', 'atelier-irisee-master-plugin' ),
			'showing'         => __( 'Showing %1$d–%2$d of %3$d', 'atelier-irisee-master-plugin' ),
			'showPicture'     => __( 'Show picture %d', 'atelier-irisee-master-plugin' ),
			'selected'        => __( 'Selected', 'atelier-irisee-master-plugin' ),
			'noProducts'      => __( 'No products found.', 'atelier-irisee-master-plugin' ),
			'noMatch'         => __( 'No products match your filters.', 'atelier-irisee-master-plugin' ),
			// Design studio (Shop): badges and the "Show more" button.
			'badgeNew'        => __( 'New', 'atelier-irisee-master-plugin' ),
			'lining'          => __( 'Lining', 'atelier-irisee-master-plugin' ),
			'badgeSale'       => __( 'Sale', 'atelier-irisee-master-plugin' ),
			'badgeLow'        => __( 'Almost sold out', 'atelier-irisee-master-plugin' ),
			'showMore'        => __( 'Show more', 'atelier-irisee-master-plugin' ),
			'selectHint'      => __( 'Select a product to see its pictures and details.', 'atelier-irisee-master-plugin' ),
			'viewProduct'     => __( 'View product', 'atelier-irisee-master-plugin' ),
			'addToCart'       => __( 'Add to cart', 'atelier-irisee-master-plugin' ),
			'adding'          => __( 'Adding…', 'atelier-irisee-master-plugin' ),
			'viewCart'        => __( 'View cart', 'atelier-irisee-master-plugin' ),
			'less'            => __( 'Less', 'atelier-irisee-master-plugin' ),
			'more'            => __( 'More', 'atelier-irisee-master-plugin' ),
			'lengthCm'        => __( 'Length in cm', 'atelier-irisee-master-plugin' ),
			'quantity'        => __( 'Quantity', 'atelier-irisee-master-plugin' ),
			'cm'              => __( 'cm', 'atelier-irisee-master-plugin' ),
		);
	}

	private static function enqueue() {
		if ( ! wp_script_is( 'aimp-ui', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
		}
		if ( ! wp_script_is( 'aimp-shop', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-shop' );
		wp_enqueue_script( 'aimp-shop' );
		if ( self::$localized ) {
			return;
		}
		self::$localized = true;

		$i18n = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			$i18n[ $code ] = AIMP_I18n::with_language( $code, array( __CLASS__, 'strings' ) );
		}
		wp_localize_script(
			'aimp-shop',
			'aimpShop',
			array(
				'endpoint'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'currencySymbol'  => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'currency'        => array(
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'position' => get_option( 'woocommerce_currency_pos', 'left' ),
					'decimals' => wc_get_price_decimals(),
					'decimal'  => wc_get_price_decimal_separator(),
					'thousand' => wc_get_price_thousand_separator(),
				),
				'defaultLanguage' => AIMP_I18n::default_language(),
				'languages'       => AIMP_Shortcode::languages_data(),
				'i18n'            => $i18n,
				'design'          => self::design(),
			)
		);
	}

	/**
	 * The Design studio's shop settings for shop.js (Design studio → Shop).
	 *
	 * @return array
	 */
	public static function design() {
		$keys = array( 'brand', 'sort_default', 'no_sort_name', 'no_sort_price', 'no_sort_newest', 'f_nosearch', 'f_nocat', 'f_noprice', 'f_noattrs', 'f_nostock', 'f_hidden', 'paging', 'noquick', 'b_new', 'b_sale', 'b_low', 'cat_banner', 'cat_text' );
		$out  = array();
		foreach ( $keys as $key ) {
			$value = class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( 'shop', $key ) : null;
			if ( null !== $value ) {
				$out[ $key ] = $value;
			}
		}
		// The share button on product cards (Design studio → Product page).
		if ( class_exists( 'AIMP_Design' ) && AIMP_Design::opt( 'product-page', 'pp_nocardshare' ) ) {
			$out['noshare'] = true;
		}
		return (object) $out;
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts     = shortcode_atts(
			array(
				'type'     => 'all',
				// list="all": always the products, never the overview (e.g. a page with all haberdashery).
				'list'     => '',
				// category="58": the products of that category (and its subcategories).
				'category' => '',
			),
			$atts,
			self::TAG
		);
		$type     = in_array( $atts['type'], self::TYPES, true ) ? $atts['type'] : 'all';
		$category = absint( $atts['category'] );
		$brand    = '';
		// A category or brand page of its own (/stoffen/boucle/): that category or brand.
		$own = self::request_term();
		if ( $own && (int) get_queried_object_id() === (int) $own[2] ) {
			if ( 'brand' === $own[1] ) {
				$brand = $own[0]->slug;
			} else {
				$category = (int) $own[0]->term_id;
			}
		}

		// The All products page is an overview of the shop sections, and the Haberdashery page one of its kinds,
		// unless a category link or filter (or "All haberdashery": aimp_all) brought the visitor here: then it
		// lists the products.
		if ( ! self::has_filters_in_url() && 'all' !== $atts['list'] && ! $category && '' === $brand ) {
			$overview = '';
			if ( 'all' === $type ) {
				$overview = self::overview_html();
			} elseif ( 'haberdashery' === $type ) {
				$overview = self::haberdashery_overview_html();
			}
			if ( '' !== $overview ) {
				return $overview;
			}
		}

		self::enqueue();
		// The first page of products (with the filter options) comes with the page, so the grid shows at once; the
		// script only asks the server again when the visitor filters, sorts or pages.
		$initial = '';
		if ( ! self::has_filters_in_url( true ) && ! $category && '' === $brand ) {
			$data            = self::query( self::default_args( $type ) );
			$data['facets']  = self::facets( $type );
			$data['lang']    = AIMP_I18n::current();
			$initial         = ' data-initial="' . esc_attr( wp_json_encode( $data ) ) . '"';
		}
		$clean = '';
		if ( $own && (int) get_queried_object_id() === (int) $own[2] ) {
			$clean = ' data-clean="1" data-base="' . esc_attr( wp_make_link_relative( get_permalink( $own[2] ) ) ) . '"';
		}
		return '<div class="aimp-configurator aimp-shop" data-aimp-shop data-type="' . esc_attr( $type ) . '"' . ( $category ? ' data-category="' . $category . '"' : '' ) .
			( '' !== $brand ? ' data-brand="' . esc_attr( $brand ) . '"' : '' ) . $clean . $initial . '>' .
			self::static_html( $type, $category, $brand, $own ) .
			'<noscript><p>' . esc_html__( 'Please enable JavaScript to filter the products.', 'atelier-irisee-master-plugin' ) . '</p></noscript></div>';
	}

	/**
	 * The products as plain HTML, replaced by the shop's script: what search engines (and visitors without
	 * JavaScript) see. A page of products with links to them, and links to the next pages.
	 *
	 * @param string     $type     Page type.
	 * @param int        $category Category.
	 * @param string     $brand    Brand slug.
	 * @param array|null $own      request_term() on a category or brand page.
	 * @return string
	 */
	private static function static_html( $type, $category, $brand, $own ) {
		$args = self::default_args( $type );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which products to show.
		if ( ! $category && isset( $_GET['aimp_cat'] ) ) {
			$category = absint( wp_unslash( $_GET['aimp_cat'] ) );
		}
		if ( '' === $brand && isset( $_GET['aimp_brand'] ) ) {
			$brand = sanitize_title( wp_unslash( $_GET['aimp_brand'] ) );
		}
		$page = isset( $_GET['aimp_page'] ) ? max( 1, absint( $_GET['aimp_page'] ) ) : 1;
		// phpcs:enable
		$args['category'] = $category ? array( $category ) : array();
		$args['brand']    = $brand;
		$args['page']     = $page;
		$data             = self::query( $args );

		$html = '<div class="aimp-shop-static">';
		// A category's or brand's own text (Products → Categories / Brands → Description).
		if ( $own && '' !== trim( (string) $own[0]->description ) ) {
			$html .= '<div class="aimp-shop-static-intro">' . wp_kses_post( wpautop( $own[0]->description ) ) . '</div>';
		}
		if ( ! $data['items'] ) {
			return $html . '<p>' . esc_html__( 'No products found.', 'atelier-irisee-master-plugin' ) . '</p></div>';
		}
		$size  = wc_get_image_size( 'woocommerce_thumbnail' );
		$html .= '<ul class="aimp-grid">';
		foreach ( $data['items'] as $i => $item ) {
			$html .= '<li><a class="aimp-card" href="' . esc_url( $item['permalink'] ) . '">' .
				'<span class="aimp-card-image"><img src="' . esc_url( $item['image'] ) . '" alt="' . esc_attr( '' !== $item['image_alt'] ? $item['image_alt'] : $item['name'] ) . '"' .
				( ! empty( $size['width'] ) ? ' width="' . (int) $size['width'] . '" height="' . (int) ( ! empty( $size['height'] ) ? $size['height'] : $size['width'] ) . '"' : '' ) .
				( $i > 3 ? ' loading="lazy"' : '' ) . ' decoding="async"></span>' .
				'<span class="aimp-card-name">' . esc_html( $item['name'] ) . '</span>' .
				'<span class="aimp-card-price">' . wp_kses_post( (string) $item['price_html'] ) . '</span></a></li>';
		}
		$html .= '</ul>';
		if ( $data['pages'] > 1 ) {
			$base = $own ? self::term_url( $own[0] ) : get_permalink();
			if ( ! $own && $category ) {
				$base = add_query_arg( 'aimp_cat', $category, $base );
			}
			if ( ! $own && '' !== $brand ) {
				$base = add_query_arg( 'aimp_brand', $brand, $base );
			}
			$html .= '<nav class="aimp-pagination" aria-label="' . esc_attr__( 'Pages', 'atelier-irisee-master-plugin' ) . '">';
			for ( $p = 1; $p <= $data['pages']; $p++ ) {
				$url   = $p > 1 ? add_query_arg( 'aimp_page', $p, $base ) : $base;
				$html .= $p === (int) $data['page']
					? '<span class="aimp-page is-current" aria-current="page">' . $p . '</span> '
					: '<a class="aimp-page" href="' . esc_url( $url ) . '">' . $p . '</a> ';
			}
			$html .= '</nav>';
		}
		return $html . '</div>';
	}

	/**
	 * The arguments of a shop page without filters (as read_args() gives them for a first visit).
	 *
	 * @param string $type Page type.
	 * @return array
	 */
	private static function default_args( $type ) {
		return array(
			'type'     => $type,
			'category' => array(),
			'search'   => '',
			'min'      => null,
			'max'      => null,
			'in_stock' => false,
			'lining'   => false,
			'brand'    => '',
			'sort'     => ( class_exists( 'AIMP_Design' ) && AIMP_Design::opt( 'shop', 'sort_default' ) ) ? AIMP_Design::opt( 'shop', 'sort_default' ) : 'recommended',
			'page'     => 1,
			'attrs'    => array(),
			'skills'   => array(),
			'buttons'  => array(),
		);
	}

	/**
	 * Whether the address has shop filters (aimp_*, not the language).
	 *
	 * @param bool $ignore_all Leave out aimp_all ("show the list, not the overview"), which filters nothing.
	 * @return bool
	 */
	private static function has_filters_in_url( $ignore_all = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which filters are in the address.
		foreach ( array_keys( $_GET ) as $key ) {
			// Not the language, nor the Design studio's preview marker (aimp_design_preview): those filter nothing.
			if ( 0 === strpos( (string) $key, 'aimp_' ) && 'aimp_lang' !== $key && 'aimp_design_preview' !== $key && ! ( $ignore_all && 'aimp_all' === $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The five sections of the overview: key => [ label, link, picture setting, category settings ].
	 *
	 * @return array
	 */
	public static function overview_sections() {
		$giftcard     = class_exists( 'AIMP_Giftcards' ) ? absint( AIMP_Giftcards::opt( 'product_id' ) ) : 0;
		$configurator = AIMP_Settings::get( 'configurator_page' );
		return array(
			'fabrics'      => array( __( 'Fabrics', 'atelier-irisee-master-plugin' ), self::page_for_type( 'fabrics' ), 'overview_img_fabrics', array( 'fabric_cat' ) ),
			'patterns'     => array( __( 'Patterns', 'atelier-irisee-master-plugin' ), self::page_for_type( 'patterns' ), 'overview_img_patterns', array( 'pattern_cat' ) ),
			'haberdashery' => array( __( 'Haberdashery', 'atelier-irisee-master-plugin' ), self::page_for_type( 'haberdashery' ), 'overview_img_haberdashery', array_column( AIMP_Notions::kinds(), 'setting' ) ),
			'giftcards'    => array( __( 'Gift cards', 'atelier-irisee-master-plugin' ), ( $giftcard && 'publish' === get_post_status( $giftcard ) ) ? $giftcard : 0, 'overview_img_giftcards', array() ),
			'configurator' => array( __( 'Sewing project kits', 'atelier-irisee-master-plugin' ), ( $configurator && 'publish' === get_post_status( $configurator ) ) ? $configurator : 0, 'overview_img_configurator', array() ),
		);
	}

	/**
	 * Picture of an overview card: the one chosen in the settings, otherwise a product picture from that section.
	 *
	 * @param string $setting  Picture setting.
	 * @param array  $cat_keys Category settings of the section.
	 * @param int    $link_id  Page or product the card links to.
	 * @return string
	 */
	private static function overview_image( $setting, $cat_keys, $link_id ) {
		$image_id = AIMP_Settings::get( $setting );
		if ( ! $image_id ) {
			$cats = array();
			foreach ( $cat_keys as $key ) {
				$cats[] = AIMP_Settings::get( $key );
			}
			$cats = array_filter( $cats );
			if ( $cats ) {
				$query    = new WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'orderby'        => array(
							'menu_order' => 'ASC',
							'date'       => 'DESC',
						),
						'tax_query'      => self::scope_tax_query( $cats ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'meta_query'     => array( array( 'key' => '_thumbnail_id' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					)
				);
				$image_id = $query->posts ? (int) get_post_thumbnail_id( $query->posts[0] ) : 0;
			} else {
				$image_id = (int) get_post_thumbnail_id( $link_id );
			}
		}
		$url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) : '';
		return $url ? $url : wc_placeholder_img_src( 'woocommerce_single' );
	}

	/**
	 * The overview: one row of cards that link to the shop sections.
	 *
	 * @param bool  $topbar With the language choice above it (not on the home page block).
	 * @param array $skip   Sections left out (the home page block has no gift card tile).
	 * @return string Empty when no section has a page yet.
	 */
	public static function overview_html( $topbar = true, $skip = array() ) {
		$cards = '';
		$hero  = '';
		// Design studio (Overview cards): which section is the large card, and a line under each name.
		$hero_key = class_exists( 'AIMP_Design' ) ? (string) AIMP_Design::opt( 'overview', 'ov_hero', '' ) : '';
		$hero_key = '' === $hero_key ? 'configurator' : $hero_key;
		foreach ( self::overview_sections() as $key => $section ) {
			list( $label, $link_id, $setting, $cat_keys ) = $section;
			if ( ! $link_id || in_array( $key, $skip, true ) ) {
				continue;
			}
			$line = class_exists( 'AIMP_Design' ) ? (string) AIMP_Design::opt( 'overview', 'ov_sub_' . $key, '' ) : '';
			$line = '' !== $line ? AIMP_Settings::localized( $line ) : '';
			if ( $hero_key === $key ) {
				// Sewing project kits: the hero, a large tile on the left (bento), with only its picture and name.
				$hero = sprintf(
					'<li class="aimp-overview-card aimp-overview-hero aimp-overview-card--%4$s"><a class="aimp-card" href="%1$s"><span class="aimp-card-image"><img src="%2$s" alt="%3$s" loading="lazy"></span><span class="aimp-card-name">%3$s</span></a></li>',
					esc_url( get_permalink( $link_id ) ),
					esc_url( self::overview_image( $setting, $cat_keys, $link_id ) ),
					esc_html( $label ),
					esc_attr( $key )
				);
				continue;
			}
			$cards .= sprintf(
				'<li class="aimp-overview-card aimp-overview-card--%4$s"><a class="aimp-card" href="%1$s"><span class="aimp-card-image"><img src="%2$s" alt="%3$s" loading="lazy"></span><span class="aimp-card-name">%3$s</span>%5$s</a></li>',
				esc_url( get_permalink( $link_id ) ),
				esc_url( self::overview_image( $setting, $cat_keys, $link_id ) ),
				esc_html( $label ),
				esc_attr( $key ),
				'' !== $line ? '<span class="aimp-overview-line">' . esc_html( $line ) . '</span>' : ''
			);
		}
		return self::overview_wrap( $hero, $cards, $topbar );
	}

	/**
	 * The overview grid around the cards, with the shop styles.
	 *
	 * @param string $hero   The large card ('' for none).
	 * @param string $cards  The other cards.
	 * @param bool   $topbar With the language choice.
	 * @return string Empty without cards.
	 */
	private static function overview_wrap( $hero, $cards, $topbar = true, $grid_class = '' ) {
		$cards = $hero . $cards;
		if ( '' === $cards ) {
			return '';
		}
		if ( ! wp_style_is( 'aimp-shop', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-shop' );
		return '<div class="aimp-configurator aimp-shop aimp-overview">' . ( $topbar ? '<div class="aimp-topbar">' . AIMP_Product_Page::languages_html() . '</div>' : '' ) .
			'<ul class="aimp-grid aimp-overview-grid' . ( $hero ? ' has-hero' : '' ) . ( '' !== $grid_class ? ' ' . esc_attr( $grid_class ) : '' ) . '">' . $cards . '</ul></div>';
	}

	/**
	 * The kinds of haberdashery that have a category with products: kind => [ name, category ].
	 *
	 * @return array
	 */
	public static function haberdashery_kinds() {
		// Which categories have products is kept until the shop changes (the menu asks on every page).
		$key    = 'aimp_shop_habkinds_' . self::cache_version();
		$filled = get_transient( $key );
		if ( ! is_array( $filled ) ) {
			$filled = array();
			foreach ( AIMP_Notions::kinds() as $info ) {
				$cat = AIMP_Settings::get( $info['setting'] );
				if ( $cat && ! in_array( $cat, $filled, true ) && self::category_has_products( $cat ) ) {
					$filled[] = $cat;
				}
			}
			// Extra categories (Settings → Shop pages) with products.
			foreach ( AIMP_Settings::hab_extra_cats() as $cat ) {
				if ( ! in_array( $cat, $filled, true ) && self::category_has_products( $cat ) ) {
					$filled[] = $cat;
				}
			}
			set_transient( $key, $filled, DAY_IN_SECONDS );
		}
		$list = array();
		foreach ( AIMP_Notions::kinds() as $kind => $info ) {
			$cat = AIMP_Settings::get( $info['setting'] );
			if ( $cat && ! isset( $list[ $cat ] ) && in_array( $cat, $filled, true ) ) {
				$list[ $cat ] = array( $kind, $info['name'], $info['setting'] );
			}
		}
		$kinds = array();
		foreach ( $list as $cat => $item ) {
			$kinds[ $item[0] ] = array( $item[1], $cat, $item[2] );
		}
		// Extra categories: "cat_{id}" => [ name, category, '' (no setting) ], A to Z.
		$extra = array();
		foreach ( AIMP_Settings::hab_extra_cats() as $cat ) {
			$term = get_term( $cat, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) && ! isset( $list[ $cat ] ) && in_array( $cat, $filled, true ) ) {
				$extra[ 'cat_' . $cat ] = array( $term->name, $cat, '' );
			}
		}
		uasort(
			$extra,
			function ( $a, $b ) {
				return strcasecmp( $a[0], $b[0] );
			}
		);
		return $kinds + $extra;
	}

	/**
	 * Whether a category (or one below it) has a visible product.
	 *
	 * @param int $cat Product category.
	 * @return bool
	 */
	private static function category_has_products( $cat ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => self::scope_tax_query( array( $cat ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		return (bool) $query->posts;
	}

	/**
	 * The Haberdashery page without filters: "All haberdashery" as the large card, then one card per kind
	 * (Buttons, Zips, …) that opens the list with that category chosen.
	 *
	 * @return string Empty when there is no haberdashery page or no kind with products.
	 */
	/**
	 * [atelier_irisee_haberdashery]: the tiles of the haberdashery kinds and the extra categories. Its links lead to the
	 * Haberdashery page (Settings → Shop pages), which lists the products of the kind clicked.
	 *
	 * @return string
	 */
	public static function haberdashery_shortcode() {
		return self::haberdashery_overview_html();
	}

	public static function haberdashery_overview_html() {
		$page  = self::page_for_type( 'haberdashery' );
		$kinds = $page ? self::haberdashery_kinds() : array();
		if ( ! $kinds ) {
			return '';
		}
		$url   = get_permalink( $page );
		$cards = '';
		foreach ( $kinds as $kind => $item ) {
			// An extra category (no setting of its own): its own picture (Products → Categories), else one of its products.
			$picture = '' !== $item[2] ? self::overview_image( 'overview_img_hab_' . $kind, array( $item[2] ), $page ) : self::category_image( $item[1] );
			$cards  .= sprintf(
				'<li class="aimp-overview-card aimp-overview-card--%4$s"><a class="aimp-card" href="%1$s"><span class="aimp-card-image"><img src="%2$s" alt="%3$s" loading="lazy"></span><span class="aimp-card-name">%3$s</span></a></li>',
				esc_url( self::kind_url( $item[1], $url ) ),
				esc_url( $picture ),
				esc_html( $item[0] ),
				esc_attr( $kind )
			);
		}
		// All tiles the same size (no large "All haberdashery" tile; ?aimp_all=1 still lists everything).
		return self::overview_wrap( '', $cards, true, 'aimp-hab-grid' );
	}

	/**
	 * The picture of a product category: its own (Products → Categories), else a picture of one of its products.
	 *
	 * @param int $cat Category.
	 * @return string
	 */
	private static function category_image( $cat ) {
		$image_id = (int) get_term_meta( $cat, 'thumbnail_id', true );
		if ( ! $image_id ) {
			$query    = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'tax_query'      => self::scope_tax_query( array( $cat ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'meta_query'     => array( array( 'key' => '_thumbnail_id' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			);
			$image_id = $query->posts ? (int) get_post_thumbnail_id( $query->posts[0] ) : 0;
		}
		$url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) : '';
		return $url ? $url : wc_placeholder_img_src( 'woocommerce_single' );
	}

	/* ------------------------------------------------------------------
	 * Scope of a page type
	 * ------------------------------------------------------------------ */

	/**
	 * Root categories of a page type. Empty for "all" (the whole shop).
	 *
	 * @param string $type Page type.
	 * @return int[]
	 */
	public static function roots( $type ) {
		switch ( $type ) {
			case 'patterns':
				$keys = array( 'pattern_cat' );
				break;
			case 'fabrics':
				$keys = array( 'fabric_cat' );
				break;
			case 'haberdashery':
				$keys  = array_column( AIMP_Notions::kinds(), 'setting' );
				// The extra categories of the Haberdashery page (Settings → Shop pages) belong to it as well.
				$extra = AIMP_Settings::hab_extra_cats();
				break;
			default:
				return array();
		}
		$roots = isset( $extra ) ? $extra : array();
		foreach ( $keys as $key ) {
			$roots[] = AIMP_Settings::get( $key );
		}
		return array_values( array_unique( array_filter( $roots ) ) );
	}

	/**
	 * Base tax query: visible products in the page type's categories.
	 *
	 * @param int[] $cats Categories (children included); empty = whole shop.
	 * @return array
	 */
	public static function scope_tax_query( $cats ) {
		$tax_query  = array( 'relation' => 'AND' );
		$visibility = AIMP_Catalog::visibility_tax_query();
		if ( $visibility ) {
			$tax_query[] = $visibility;
		}
		if ( $cats ) {
			$tax_query[] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => array_map( 'absint', $cats ),
				'include_children' => true,
			);
		}
		return $tax_query;
	}

	/* ------------------------------------------------------------------
	 * Filter options (cached)
	 * ------------------------------------------------------------------ */

	/**
	 * Categories, attributes and price range for a page type.
	 *
	 * @param string $type Page type.
	 * @return array
	 */
	public static function facets( $type ) {
		$key    = self::CACHE_PREFIX . $type . '_' . AIMP_I18n::current() . '_' . self::cache_version();
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$roots = self::roots( $type );
		$ids   = array();
		if ( $roots || 'all' === $type ) {
			$query = new WP_Query(
				array(
					'post_type'           => 'product',
					'post_status'         => 'publish',
					'fields'              => 'ids',
					'posts_per_page'      => -1,
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'tax_query'           => self::scope_tax_query( $roots ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			$ids   = array_map( 'absint', $query->posts );
		}

		$facets = array(
			'categories' => self::category_facet( $type, $roots ),
			'attributes' => self::attribute_facet( $ids ),
			'skills'     => self::skill_facet( $ids ),
			// Haberdashery: type, colour, diameter, width, length and material (only those the page has).
			'buttons'    => self::notion_facets( $ids ),
			'price'      => self::price_range( $ids ),
			// Whether this page has fabrics that can also be used as lining (the "Voering" filter).
			'lining'     => in_array( $type, array( 'all', 'fabrics' ), true ) && (bool) array_filter(
				$ids,
				function ( $id ) {
					return 'yes' === get_post_meta( $id, AIMP_Catalog::META_LINING, true );
				}
			),
		);
		set_transient( $key, $facets, 12 * HOUR_IN_SECONDS );
		return $facets;
	}

	/**
	 * Category tree as a flat list with depth, in the shop's category order.
	 *
	 * @param string $type  Page type.
	 * @param int[]  $roots Root categories.
	 * @return array[] [ id, name, depth ]
	 */
	private static function category_facet( $type, $roots ) {
		$list = array();
		if ( 'all' === $type ) {
			$terms   = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'exclude'    => array( absint( get_option( 'default_product_cat' ) ) ),
				)
			);
			$by_parent = self::group_by_parent( $terms );
			self::walk_terms( $by_parent, 0, 0, $list );
			return $list;
		}

		foreach ( $roots as $root ) {
			$terms     = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'child_of'   => $root,
				)
			);
			$by_parent = self::group_by_parent( $terms );
			if ( 'haberdashery' === $type ) {
				// The four material categories themselves are the first level.
				$term = get_term( $root, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$list[] = self::term_row( $term, 0 );
					self::walk_terms( $by_parent, $root, 1, $list );
				}
			} else {
				self::walk_terms( $by_parent, $root, 0, $list );
			}
		}
		return $list;
	}

	/**
	 * A category for the filters: id, name, depth, and its picture and description (Design studio → Shop: a banner
	 * above the products of one category).
	 *
	 * @param WP_Term $term  Category.
	 * @param int     $depth Depth.
	 * @return array
	 */
	private static function term_row( $term, $depth ) {
		$image = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		return array(
			'id'    => (int) $term->term_id,
			'name'  => wp_specialchars_decode( $term->name, ENT_QUOTES ),
			'depth' => $depth,
			'image' => $image ? (string) wp_get_attachment_image_url( $image, 'large' ) : '',
			'desc'  => trim( wp_strip_all_tags( term_description( $term ) ) ),
		);
	}

	private static function group_by_parent( $terms ) {
		$by_parent = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$by_parent[ (int) $term->parent ][] = $term;
			}
		}
		return $by_parent;
	}

	private static function walk_terms( $by_parent, $parent, $depth, &$list ) {
		if ( empty( $by_parent[ $parent ] ) || $depth > 5 ) {
			return;
		}
		foreach ( $by_parent[ $parent ] as $term ) {
			$list[] = self::term_row( $term, $depth );
			self::walk_terms( $by_parent, (int) $term->term_id, $depth + 1, $list );
		}
	}

	/**
	 * Global product attributes used by these products, with the values that occur.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array[] [ taxonomy, label, terms: [ slug, name ] ]
	 */
	private static function attribute_facet( $ids ) {
		$facet = array();
		if ( ! $ids ) {
			return $facet;
		}
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'object_ids' => $ids,
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			$facet[] = array(
				'taxonomy' => $taxonomy,
				'label'    => wc_attribute_label( $taxonomy ),
				'terms'    => array_map(
					function ( $term ) {
						return array(
							'slug' => $term->slug,
							'name' => wp_specialchars_decode( $term->name, ENT_QUOTES ),
						);
					},
					array_values( $terms )
				),
			);
		}
		return $facet;
	}

	/**
	 * Skill levels used by these products (patterns), from easy to hard.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array[] [ key, label ]
	 */
	private static function skill_facet( $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$in   = implode( ',', array_map( 'absint', $ids ) );
		$used = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient by facets().
			$wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ( {$in} )", AIMP_Catalog::META_SKILL ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		);
		$facet = array();
		foreach ( AIMP_Catalog::skill_levels() as $key => $label ) {
			if ( in_array( $key, (array) $used, true ) ) {
				$facet[] = array(
					'key'   => $key,
					'label' => $label,
				);
			}
		}
		return $facet;
	}

	/**
	 * Haberdashery filters: key => label. The request uses m_<key>, the address aimp_<key>.
	 *
	 * @return array
	 */
	public static function notion_filters() {
		return array(
			'htype' => __( 'Type', 'atelier-irisee-master-plugin' ),
			'hcol'  => __( 'Colour', 'atelier-irisee-master-plugin' ),
			'hdia'  => __( 'Diameter', 'atelier-irisee-master-plugin' ),
			'hwid'  => __( 'Width', 'atelier-irisee-master-plugin' ),
			'hlen'  => __( 'Length', 'atelier-irisee-master-plugin' ),
			'hmat'  => __( 'Material', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * The filter values of a haberdashery product: key => [ value key, label, sort ].
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private static function notion_values( $product ) {
		$kind = AIMP_Notions::kind_of( $product );
		$info = AIMP_Notions::kind( $kind );
		if ( ! $info ) {
			return array();
		}
		$values = array();
		$type   = AIMP_Notions::type_of( $product, $kind );
		if ( '' !== $type && isset( $info['types'][ $type ] ) ) {
			$values['htype'] = array( $type, $info['types'][ $type ], array_search( $type, array_keys( AIMP_Notions::rules() ), true ) );
		}
		foreach ( array( 'hcol' => 'colour', 'hmat' => 'material' ) as $key => $field ) {
			$text = in_array( $field, $info['specs'], true ) ? AIMP_Notions::field_text( $product, $field, $kind ) : '';
			if ( '' !== $text ) {
				// The filter key is the stored value (the same in every language); the label is in the visitor's language.
				$raw            = strtolower( trim( (string) $product->get_meta( AIMP_Notions::fields()[ $field ][0] ) ) );
				$values[ $key ] = array( $raw, $text, strtolower( $text ) );
			}
		}
		foreach ( array( 'hdia' => 'diameter', 'hwid' => 'width', 'hlen' => 'length' ) as $key => $field ) {
			$number = in_array( $field, $info['specs'], true ) || ( 'length' === $field && 'roll' === AIMP_Notions::sold( $product ) ) ? AIMP_Notions::number( $product, $field ) : 0;
			if ( $number > 0 ) {
				$unit           = AIMP_Notions::unit( $kind, $field );
				$cm             = 'cm' === $unit ? $number : $number / 10;
				$values[ $key ] = array( wc_format_decimal( $number, 1, true ) . $unit, 'length' === $field ? AIMP_Notions::length_text( $number ) : AIMP_Notions::measure_text( $number, $unit ), $cm );
			}
		}
		return $values;
	}

	/**
	 * Published haberdashery products (all kinds' categories): IDs.
	 *
	 * @return int[]
	 */
	private static function notion_ids() {
		$cats = self::roots( 'haberdashery' );
		if ( ! $cats ) {
			return array();
		}
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => self::scope_tax_query( $cats ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		return array_map( 'absint', $query->posts );
	}

	/**
	 * The haberdashery filters with the values the products of the page have.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array[] [ key, label, options: [ key, label ] ]
	 */
	private static function notion_facets( $ids ) {
		$ids = $ids ? array_values( array_intersect( array_map( 'absint', $ids ), self::notion_ids() ) ) : array();
		if ( ! $ids ) {
			return array();
		}
		$found = array();
		// The categories (with their parents) of the products that have each value: the filter only shows the
		// values of the ticked categories.
		$cats = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			$values  = $product ? self::notion_values( $product ) : array();
			if ( ! $values ) {
				continue;
			}
			$own = array();
			foreach ( wc_get_product_cat_ids( $id ) as $cat ) {
				$own = array_merge( $own, array( (int) $cat ), array_map( 'intval', get_ancestors( $cat, 'product_cat', 'taxonomy' ) ) );
			}
			foreach ( $values as $key => $value ) {
				$found[ $key ][ $value[0] ] = $value;
				$cats[ $key ][ $value[0] ] = array_merge( isset( $cats[ $key ][ $value[0] ] ) ? $cats[ $key ][ $value[0] ] : array(), $own );
			}
		}
		$facets = array();
		foreach ( self::notion_filters() as $key => $label ) {
			if ( empty( $found[ $key ] ) ) {
				continue;
			}
			$options = array_values( $found[ $key ] );
			usort(
				$options,
				function ( $a, $b ) {
					return is_string( $a[2] ) ? strnatcasecmp( $a[2], $b[2] ) : ( $a[2] <=> $b[2] );
				}
			);
			$facets[] = array(
				'key'     => $key,
				'label'   => $label,
				'options' => array_map(
					function ( $option ) use ( $cats, $key ) {
						return array(
							'key'   => (string) $option[0],
							'label' => $option[1],
							'cats'  => array_values( array_unique( $cats[ $key ][ $option[0] ] ) ),
						);
					},
					$options
				),
			);
		}
		return $facets;
	}

	/**
	 * The haberdashery products that match the chosen filters (any of the values of a filter, all filters).
	 *
	 * @param array $chosen key => [ value keys ].
	 * @return int[]
	 */
	private static function notion_matches( $chosen ) {
		$ids = array();
		foreach ( self::notion_ids() as $id ) {
			$product = wc_get_product( $id );
			$values  = $product ? self::notion_values( $product ) : array();
			foreach ( $chosen as $key => $list ) {
				if ( ! isset( $values[ $key ] ) || ! in_array( (string) $values[ $key ][0], $list, true ) ) {
					continue 2;
				}
			}
			$ids[] = $id;
		}
		return $ids;
	}

	/**
	 * Lowest and highest price of these products, rounded outwards to whole numbers.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array [ min, max ]
	 */
	private static function price_range( $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array(
				'min' => 0,
				'max' => 0,
			);
		}
		$in  = implode( ',', array_map( 'absint', $ids ) );
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient by facets().
			"SELECT MIN( CAST( meta_value AS DECIMAL(12,2) ) ) AS min_price, MAX( CAST( meta_value AS DECIMAL(12,2) ) ) AS max_price
			FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND meta_value <> '' AND post_id IN ( {$in} )" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		);
		return array(
			'min' => $row ? (int) floor( (float) $row->min_price ) : 0,
			'max' => $row ? (int) ceil( (float) $row->max_price ) : 0,
		);
	}

	/* ------------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------------ */

	/**
	 * Card plus everything the details panel shows.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function item( $product ) {
		$per_10cm                  = AIMP_Catalog::sold_per_10cm( $product );
		$item                      = AIMP_Catalog::card( $product );
		$item['price_suffix']      = AIMP_Catalog::price_suffix( $product );
		$item['subtitle']          = AIMP_Notions::main_text( $product );
		$item['badge']             = $product->is_in_stock() ? '' : __( 'Out of stock', 'atelier-irisee-master-plugin' );
		$item['short_description'] = wp_kses_post( wpautop( $product->get_short_description() ) );
		$item['gallery']           = AIMP_Catalog::images( $product );
		$item['in_stock']          = $product->is_in_stock();
		$item['brand']             = AIMP_Product_Page::brand( $product );
		$item['created']           = $product->get_date_created() ? $product->get_date_created()->getTimestamp() : 0;
		$item['on_sale']           = $product->is_on_sale();
		$stock                     = $product->managing_stock() ? (float) $product->get_stock_quantity() : null;
		$item['low']               = null !== $stock && $stock > 0 && $stock <= max( 1, (int) AIMP_Settings::get( 'low_stock' ) );
		// The buy bar in the details panel: products that can be bought straight away (not gift cards).
		$is_giftcard = class_exists( 'AIMP_Giftcards_Product' ) && AIMP_Giftcards_Product::is_giftcard( $product );
		$buy         = $is_giftcard ? null : AIMP_Product_Page::buy_data( $product, $per_10cm );
		$item['buy']   = ( $buy && $buy['available'] )
			? array(
				'per_10cm'   => (bool) $buy['per_10cm'],
				'unit_price' => (float) $buy['unit_price'],
				'min'        => (int) $buy['min'],
				'max'        => (int) $buy['max'],
				'step'       => (int) $buy['step'],
			)
			: null;
		return $item;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only public catalog endpoint.

	/**
	 * Filters from the request.
	 *
	 * @return array
	 */
	private static function read_args() {
		$text = function ( $name, $max = 100 ) {
			return isset( $_POST[ $name ] ) ? substr( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ), 0, $max ) : '';
		};
		$price = function ( $name ) use ( $text ) {
			$value = $text( $name, 20 );
			return '' === $value ? null : max( 0, (float) wc_format_decimal( $value ) );
		};

		$type = sanitize_key( $text( 'type', 20 ) );
		$sort = sanitize_key( $text( 'sort', 20 ) );
		$args = array(
			'type'     => in_array( $type, self::TYPES, true ) ? $type : 'all',
			// One or more categories: "12" or "12,15".
			'category' => array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $text( 'category', 200 ) ) ) ) ) ), 0, 20 ),
			'search'   => $text( 'search' ),
			'min'      => $price( 'min' ),
			'max'      => $price( 'max' ),
			'in_stock' => '1' === $text( 'in_stock', 1 ),
			// Fabrics that can also be used as lining ("Voering").
			'lining'   => '1' === $text( 'lining', 1 ),
			// One brand (WooCommerce Brands), by its slug: the brand tiles of the home page link here.
			'brand'    => sanitize_title( $text( 'brand', 100 ) ),
			'sort'     => in_array( $sort, self::SORTS, true ) ? $sort : 'recommended',
			'page'     => max( 1, absint( $text( 'page', 10 ) ) ),
			'attrs'    => array(),
			'skills'   => array_values( array_intersect( array_map( 'sanitize_key', explode( ',', $text( 'skill', 100 ) ) ), array_keys( AIMP_Catalog::skill_levels() ) ) ),
			'buttons'  => array(),
		);

		// Haberdashery filters arrive as m_hdia=15mm,18mm (several values: any of them). Links from 3.6 (m_b*) still work.
		$legacy = array(
			'htype' => 'btype',
			'hcol'  => 'bcol',
			'hdia'  => 'bdia',
			'hmat'  => 'bmat',
		);
		foreach ( array_keys( self::notion_filters() ) as $key ) {
			$raw = $text( 'm_' . $key, 500 );
			if ( '' === $raw && isset( $legacy[ $key ] ) ) {
				$raw = $text( 'm_' . $legacy[ $key ], 500 );
				if ( 'hdia' === $key && '' !== $raw ) {
					$raw = implode(
						',',
						array_map(
							function ( $value ) {
								return wc_format_decimal( $value, 1, true ) . 'mm';
							},
							explode( ',', $raw )
						)
					);
				}
			}
			$list = array_slice( array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) ), 0, 30 );
			if ( in_array( $key, array( 'hcol', 'hmat' ), true ) ) {
				$list = array_map( 'strtolower', $list );
			}
			if ( $list ) {
				$args['buttons'][ $key ] = $list;
			}
		}

		// Attribute filters arrive as attr_pa_colour=red,blue.
		foreach ( array_keys( $_POST ) as $key ) {
			if ( 0 !== strpos( (string) $key, 'attr_pa_' ) || count( $args['attrs'] ) >= 10 ) {
				continue;
			}
			$taxonomy = wc_sanitize_taxonomy_name( substr( $key, 5 ) );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$slugs = array_slice( array_filter( array_map( 'sanitize_title', explode( ',', $text( $key, 1000 ) ) ) ), 0, 30 );
			if ( $slugs ) {
				$args['attrs'][ $taxonomy ] = $slugs;
			}
		}
		return $args;
	}

	public static function ajax() {
		$args = self::read_args();
		$data = self::query( $args );
		if ( ! empty( $_POST['facets'] ) ) {
			$data['facets'] = self::facets( $args['type'] );
		}
		wp_send_json_success( $data );
	}

	// phpcs:enable

	/**
	 * One page of products for the given filters.
	 *
	 * @param array $args From read_args().
	 * @return array [ items, page, pages, total, per_page ]
	 */
	public static function query( $args ) {
		$per_page = max( 1, AIMP_Settings::get( 'shop_per_page' ) );
		$roots    = self::roots( $args['type'] );
		$empty    = array(
			'items'    => array(),
			'page'     => 1,
			'pages'    => 0,
			'total'    => 0,
			'per_page' => $per_page,
		);
		if ( ! $roots && 'all' !== $args['type'] ) {
			return $empty;
		}

		// Category filter: one or more categories (any of them), only within this page's categories.
		$cats   = $roots;
		$chosen = array();
		foreach ( (array) $args['category'] as $term_id ) {
			$term = get_term( absint( $term_id ), 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$allowed = ! $roots;
			foreach ( $roots as $root ) {
				if ( in_array( (int) $term->term_id, AIMP_Catalog::category_tree( $root ), true ) ) {
					$allowed = true;
					break;
				}
			}
			if ( $allowed ) {
				$chosen[] = (int) $term->term_id;
			}
		}
		if ( $chosen ) {
			$cats = $chosen;
		}

		$tax_query = self::scope_tax_query( $cats );
		$brand     = ! empty( $args['brand'] ) && taxonomy_exists( 'product_brand' ) ? get_term_by( 'slug', $args['brand'], 'product_brand' ) : null;
		if ( ! empty( $args['brand'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_brand',
				'field'    => 'term_id',
				'terms'    => $brand ? array( (int) $brand->term_id ) : array( 0 ),
			);
		}
		foreach ( $args['attrs'] as $taxonomy => $slugs ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		$meta_query = array( 'relation' => 'AND' );
		if ( $args['skills'] ) {
			$meta_query[] = array(
				'key'     => AIMP_Catalog::META_SKILL,
				'value'   => $args['skills'],
				'compare' => 'IN',
			);
		}
		// Haberdashery filters: only the matching haberdashery products.
		$notion_in = $args['buttons'] ? self::notion_matches( $args['buttons'] ) : null;
		if ( ! empty( $args['lining'] ) ) {
			$meta_query[] = array(
				'key'   => AIMP_Catalog::META_LINING,
				'value' => 'yes',
			);
		}
		if ( $args['in_stock'] ) {
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => array( 'instock', 'onbackorder' ),
				'compare' => 'IN',
			);
		}
		$price_sort = in_array( $args['sort'], array( 'price_asc', 'price_desc' ), true );
		if ( null !== $args['min'] || null !== $args['max'] || $price_sort ) {
			$clause = array(
				'key'  => '_price',
				'type' => 'DECIMAL(12,2)',
			);
			if ( null !== $args['min'] && null !== $args['max'] ) {
				$clause['value']   = array( min( $args['min'], $args['max'] ), max( $args['min'], $args['max'] ) );
				$clause['compare'] = 'BETWEEN';
			} elseif ( null !== $args['min'] ) {
				$clause['value']   = $args['min'];
				$clause['compare'] = '>=';
			} elseif ( null !== $args['max'] ) {
				$clause['value']   = $args['max'];
				$clause['compare'] = '<=';
			} else {
				$clause['compare'] = 'EXISTS';
			}
			$meta_query['price_clause'] = $clause;
		}

		$query_args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'posts_per_page'      => $per_page,
			'paged'               => $args['page'],
			'tax_query'           => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);
		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( null !== $notion_in ) {
			$query_args['post__in'] = $notion_in ? $notion_in : array( 0 );
		}
		if ( '' !== $args['search'] ) {
			$query_args['s'] = $args['search'];
		}

		switch ( $args['sort'] ) {
			case 'name_asc':
			case 'name_desc':
				$query_args['orderby'] = array( 'title' => 'name_asc' === $args['sort'] ? 'ASC' : 'DESC' );
				break;
			case 'price_asc':
			case 'price_desc':
				$query_args['orderby'] = array(
					'price_clause' => 'price_asc' === $args['sort'] ? 'ASC' : 'DESC',
					'title'        => 'ASC',
				);
				break;
			case 'newest':
				$query_args['orderby'] = array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				);
				break;
			default:
				$query_args['orderby'] = array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				);
		}

		$query = new WP_Query( $query_args );
		$pages = (int) $query->max_num_pages;

		// A page number from an old link can be past the end: show the last page instead.
		if ( ! $query->posts && $args['page'] > 1 && $pages > 0 ) {
			$args['page'] = $pages;
			return self::query( $args );
		}

		$items = array();
		foreach ( $query->posts as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$items[] = self::item( $product );
			}
		}
		return array(
			'items'    => $items,
			'page'     => $args['page'],
			'pages'    => $pages,
			'total'    => (int) $query->found_posts,
			'per_page' => $per_page,
			// The name of the chosen brand, for its filter chip.
			'brand'    => $brand ? $brand->name : '',
		);
	}

	/**
	 * The All products page with only this brand's products (the brand tiles and the product page's brand link).
	 *
	 * @param WP_Term $term Brand.
	 * @return string '' without an All products page.
	 */
	public static function brand_url( $term ) {
		$page = self::page_for_type( 'all' );
		if ( ! $page ) {
			return '';
		}
		// Its own address (/alle-producten/merk/cousette/), or the page with the brand as a filter.
		return self::clean_urls()
			? trailingslashit( get_permalink( $page ) ) . self::BRAND_BASE . '/' . $term->slug . '/'
			: add_query_arg( 'aimp_brand', $term->slug, get_permalink( $page ) );
	}
}
