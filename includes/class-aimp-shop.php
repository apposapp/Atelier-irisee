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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_action( 'wc_ajax_aimp_shop', array( __CLASS__, 'ajax' ) );

		foreach ( array( 'save_post_product', 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_delete_product', 'woocommerce_trash_product' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_cache' ) );
		}
		foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_term_cache' ), 10, 3 );
		}

		// Category and shop links lead to the shop pages chosen in the settings.
		add_filter( 'term_link', array( __CLASS__, 'term_link' ), 20, 3 );
		add_filter( 'woocommerce_get_shop_page_permalink', array( __CLASS__, 'shop_link' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_archives' ) );
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
					: add_query_arg( 'aimp_cat', $term_id, get_permalink( $page ) );
			}
		}
		$page = self::page_for_type( 'all' );
		return $page ? add_query_arg( 'aimp_cat', $term_id, get_permalink( $page ) ) : '';
	}

	/**
	 * @param string  $url      Term link.
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 * @return string
	 */
	public static function term_link( $url, $term, $taxonomy ) {
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
		} elseif ( is_shop() ) {
			$page = self::page_for_type( 'all' );
			$url  = ( $page && $page !== (int) wc_get_page_id( 'shop' ) ) ? get_permalink( $page ) : '';
		}
		if ( $url ) {
			wp_safe_redirect( $url, 302 );
			exit;
		}
	}

	/* ------------------------------------------------------------------
	 * Cache
	 * ------------------------------------------------------------------ */

	public static function cache_version() {
		return (int) get_option( self::CACHE_OPTION, 1 );
	}

	public static function flush_cache() {
		update_option( self::CACHE_OPTION, self::cache_version() + 1, false );
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
			)
		);
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'type' => 'all' ), $atts, self::TAG );
		$type = in_array( $atts['type'], self::TYPES, true ) ? $atts['type'] : 'all';

		// The All products page is an overview of the shop sections, unless a category link or
		// filter brought the visitor here: then it lists the products.
		if ( 'all' === $type && ! self::has_filters_in_url() ) {
			$overview = self::overview_html();
			if ( '' !== $overview ) {
				return $overview;
			}
		}

		self::enqueue();
		return '<div class="aimp-configurator aimp-shop" data-aimp-shop data-type="' . esc_attr( $type ) . '"><noscript>' .
			esc_html__( 'Please enable JavaScript to browse the products.', 'atelier-irisee-master-plugin' ) .
			'</noscript></div>';
	}

	private static function has_filters_in_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which filters are in the address.
		foreach ( array_keys( $_GET ) as $key ) {
			if ( 0 === strpos( (string) $key, 'aimp_' ) && 'aimp_lang' !== $key ) {
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
			'haberdashery' => array( __( 'Haberdashery', 'atelier-irisee-master-plugin' ), self::page_for_type( 'haberdashery' ), 'overview_img_haberdashery', array( 'button_cat', 'zip_cat', 'ribbon_cat', 'bias_cat' ) ),
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
	 * @return string Empty when no section has a page yet.
	 */
	public static function overview_html() {
		$cards = '';
		$hero  = '';
		foreach ( self::overview_sections() as $key => $section ) {
			list( $label, $link_id, $setting, $cat_keys ) = $section;
			if ( ! $link_id ) {
				continue;
			}
			if ( 'configurator' === $key ) {
				// Sewing project kits: the hero, a large tile on the left (bento).
				$hero = sprintf(
					'<li class="aimp-overview-card aimp-overview-hero"><a class="aimp-card" href="%1$s"><span class="aimp-card-image"><img src="%2$s" alt="" loading="lazy"></span><span class="aimp-overview-hero-text"><span class="aimp-card-name">%3$s</span><span class="aimp-overview-hero-line">%4$s</span><span class="aimp-button aimp-overview-hero-button">%5$s</span></span></a></li>',
					esc_url( get_permalink( $link_id ) ),
					esc_url( self::overview_image( $setting, $cat_keys, $link_id ) ),
					esc_html( $label ),
					esc_html__( 'Pattern, fabric and haberdashery together', 'atelier-irisee-master-plugin' ),
					esc_html__( 'Compose your kit', 'atelier-irisee-master-plugin' )
				);
				continue;
			}
			$cards .= sprintf(
				'<li class="aimp-overview-card"><a class="aimp-card" href="%1$s"><span class="aimp-card-image"><img src="%2$s" alt="" loading="lazy"></span><span class="aimp-card-name">%3$s</span></a></li>',
				esc_url( get_permalink( $link_id ) ),
				esc_url( self::overview_image( $setting, $cat_keys, $link_id ) ),
				esc_html( $label )
			);
		}
		$cards = $hero . $cards;
		if ( '' === $cards ) {
			return '';
		}
		if ( ! wp_style_is( 'aimp-shop', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-shop' );
		return '<div class="aimp-configurator aimp-shop aimp-overview"><div class="aimp-topbar">' . AIMP_Product_Page::languages_html() . '</div>' .
			'<ul class="aimp-grid aimp-overview-grid' . ( $hero ? ' has-hero' : '' ) . '">' . $cards . '</ul></div>';
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
				$keys = array( 'button_cat', 'zip_cat', 'ribbon_cat', 'bias_cat' );
				break;
			default:
				return array();
		}
		$roots = array();
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
			'price'      => self::price_range( $ids ),
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
					$list[] = array(
						'id'    => (int) $term->term_id,
						'name'  => $term->name,
						'depth' => 0,
					);
					self::walk_terms( $by_parent, $root, 1, $list );
				}
			} else {
				self::walk_terms( $by_parent, $root, 0, $list );
			}
		}
		return $list;
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
			$list[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'depth' => $depth,
			);
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
							'name' => $term->name,
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
		$item['price_suffix']      = $per_10cm ? __( 'per 10 cm', 'atelier-irisee-master-plugin' ) : '';
		$item['badge']             = $product->is_in_stock() ? '' : __( 'Out of stock', 'atelier-irisee-master-plugin' );
		$item['short_description'] = wp_kses_post( wpautop( $product->get_short_description() ) );
		$item['gallery']           = AIMP_Catalog::images( $product );
		$item['in_stock']          = $product->is_in_stock();
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
			'sort'     => in_array( $sort, self::SORTS, true ) ? $sort : 'recommended',
			'page'     => max( 1, absint( $text( 'page', 10 ) ) ),
			'attrs'    => array(),
			'skills'   => array_values( array_intersect( array_map( 'sanitize_key', explode( ',', $text( 'skill', 100 ) ) ), array_keys( AIMP_Catalog::skill_levels() ) ) ),
		);

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
		);
	}
}
