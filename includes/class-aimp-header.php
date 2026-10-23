<?php
/**
 * Atelier Irisee header: logo on the left, favourites, cart and account icons on the right, and a
 * "Menu" bar that opens a gold side panel with the "Atelier Irisee side menu" (Appearance → Menus).
 *
 * With "Use the Atelier Irisee header" on, it replaces the theme header: in block themes the header
 * template part, in classic themes it is printed at the top of the page and the theme header is hidden.
 * [atelier_irisee_header] places it anywhere.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Header {

	const TAG      = 'atelier_irisee_header';
	const LOCATION = 'aimp_side_menu';

	/** The header is printed once per page. */
	private static $printed = false;

	public static function init() {
		add_action( 'after_setup_theme', array( __CLASS__, 'register_menu' ), 20 );
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_fragment' ) );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'haberdashery_items' ), 10, 2 );

		if ( ! AIMP_Settings::get( 'header_enabled' ) || is_admin() ) {
			return;
		}
		add_filter( 'render_block_core/template-part', array( __CLASS__, 'replace_block_header' ), 20, 2 );
		add_action( 'wp_body_open', array( __CLASS__, 'classic_header' ), 5 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Side menu: the item that links to the Haberdashery page gets "All haberdashery" and the kinds (Buttons,
	 * Zips, …) as sub-items by itself, unless sub-items were added to it in Appearance → Menus. In the Design studio
	 * (Side menu) this can be switched off, and the Fabrics and Patterns pages can get their categories the same way.
	 *
	 * @param array    $items Menu items (sorted).
	 * @param stdClass $args  wp_nav_menu() arguments.
	 * @return array
	 */
	public static function haberdashery_items( $items, $args ) {
		if ( ! isset( $args->theme_location ) || self::LOCATION !== $args->theme_location ) {
			return $items;
		}
		$opt   = function ( $key ) {
			return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( 'side-menu', $key ) : null;
		};
		$types = array();
		if ( ! $opt( 'sm_nohab' ) ) {
			$types[] = 'haberdashery';
		}
		if ( $opt( 'sm_fabrics' ) ) {
			$types[] = 'fabrics';
		}
		if ( $opt( 'sm_patterns' ) ) {
			$types[] = 'patterns';
		}
		$n = 0;
		foreach ( $types as $type ) {
			$page = AIMP_Shop::page_for_type( $type );
			if ( ! $page ) {
				continue;
			}
			$items  = array_values( $items );
			$parent = null;
			$at     = 0;
			foreach ( $items as $i => $item ) {
				if ( 'post_type' === $item->type && (int) $item->object_id === $page ) {
					$parent = $item;
					$at     = $i;
					break;
				}
			}
			if ( ! $parent ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( (int) $item->menu_item_parent === (int) $parent->db_id ) {
					continue 2;
				}
			}
			$links = self::auto_links( $type, $page );
			if ( ! $links ) {
				continue;
			}
			$children = array();
			foreach ( $links as $link ) {
				// Negative ids cannot clash with real menu items.
				$id         = -1 * ( 1000 + $n++ );
				$children[] = new WP_Post(
					(object) array(
						'ID'               => $id,
						'db_id'            => $id,
						'menu_item_parent' => (string) $parent->db_id,
						'object_id'        => $id,
						'object'           => 'custom',
						'type'             => 'custom',
						'type_label'       => '',
						'title'            => $link[1],
						'url'              => $link[2],
						'target'           => '',
						'attr_title'       => '',
						'description'      => '',
						'xfn'              => '',
						'classes'          => array( 'menu-item', 'menu-item-type-custom', 'aimp-menu-auto-' . $link[0] ),
						'current'          => false,
						'post_type'        => 'nav_menu_item',
						'post_status'      => 'publish',
						'menu_order'       => 0,
					)
				);
			}
			if ( ! in_array( 'menu-item-has-children', (array) $parent->classes, true ) ) {
				$parent->classes[] = 'menu-item-has-children';
			}
			array_splice( $items, $at + 1, 0, $children );
		}
		return $items;
	}

	/**
	 * The automatic sub-items of a shop page: [ key, title, address ].
	 *
	 * @param string $type haberdashery, fabrics or patterns.
	 * @param int    $page Its page.
	 * @return array
	 */
	private static function auto_links( $type, $page ) {
		$url = get_permalink( $page );
		if ( 'haberdashery' === $type ) {
			$kinds = AIMP_Shop::haberdashery_kinds();
			if ( ! $kinds ) {
				return array();
			}
			$links = array( array( 'all', __( 'All haberdashery', 'atelier-irisee-master-plugin' ), add_query_arg( 'aimp_all', 1, $url ) ) );
			foreach ( $kinds as $kind => $item ) {
				$links[] = array( $kind, $item[0], add_query_arg( 'aimp_cat', $item[1], $url ) );
			}
			return $links;
		}
		// Fabrics and patterns: the categories right under the root that have products.
		$root  = AIMP_Settings::get( 'fabrics' === $type ? 'fabric_cat' : 'pattern_cat' );
		$terms = $root ? get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $root,
				'hide_empty' => true,
			)
		) : array();
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		$links = array( array( 'all', 'fabrics' === $type ? __( 'All fabrics', 'atelier-irisee-master-plugin' ) : __( 'All patterns', 'atelier-irisee-master-plugin' ), $url ) );
		foreach ( $terms as $term ) {
			$links[] = array( $term->slug, $term->name, add_query_arg( 'aimp_cat', $term->term_id, $url ) );
		}
		return $links;
	}

	public static function register_menu() {
		register_nav_menus( array( self::LOCATION => __( 'Atelier Irisee side menu', 'atelier-irisee-master-plugin' ) ) );
	}

	public static function register_assets() {
		wp_register_style( 'aimp-header', AIMP_PLUGIN_URL . 'assets/css/header.css', array(), AIMP_VERSION );
		wp_register_script( 'aimp-header', AIMP_PLUGIN_URL . 'assets/js/header.js', array(), AIMP_VERSION, true );
		wp_register_script( 'aimp-search', AIMP_PLUGIN_URL . 'assets/js/search.js', array(), AIMP_VERSION, true );
		if ( AIMP_Settings::get( 'header_enabled' ) ) {
			wp_enqueue_style( 'aimp-header' );
		}
	}

	/* ------------------------------------------------------------------
	 * Replacing the theme header
	 * ------------------------------------------------------------------ */

	private static function is_rest() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Block themes: the header template part becomes our header.
	 *
	 * @param string $content Rendered template part.
	 * @param array  $block   Block.
	 * @return string
	 */
	public static function replace_block_header( $content, $block ) {
		if ( self::is_rest() ) {
			return $content;
		}
		$attrs = isset( $block['attrs'] ) ? (array) $block['attrs'] : array();
		$area  = implode( ' ', array_map( 'strval', array_intersect_key( $attrs, array_flip( array( 'slug', 'tagName', 'area' ) ) ) ) );
		if ( false === strpos( $area, 'header' ) ) {
			return $content;
		}
		if ( self::$printed ) {
			return '';
		}
		return self::render();
	}

	/**
	 * Classic themes: print the header at the top of the page.
	 */
	public static function classic_header() {
		if ( wp_is_block_theme() || self::$printed ) {
			return;
		}
		echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
	}

	/**
	 * Classic themes: hides the theme's own header (see header.css).
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		// The language flags move into the header (header.css hides the flags on the pages).
		$classes[] = 'aimp-has-header';
		if ( ! wp_is_block_theme() ) {
			$classes[] = 'aimp-replace-theme-header';
		}
		return $classes;
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	public static function shortcode() {
		return self::$printed ? '' : self::render();
	}

	/**
	 * Number of products in the cart (lines, so 30 cm of fabric counts as one).
	 *
	 * @return int
	 */
	public static function cart_count() {
		return ( function_exists( 'WC' ) && WC()->cart ) ? count( WC()->cart->get_cart() ) : 0;
	}

	/**
	 * @param int $count Number.
	 * @return string
	 */
	public static function cart_badge( $count ) {
		return '<span class="aimp-header-count aimp-header-cart-count"' . ( $count ? '' : ' hidden' ) . '>' . (int) $count . '</span>';
	}

	/**
	 * Keeps the cart number up to date after adding to the cart without reloading.
	 *
	 * @param array $fragments Selector => HTML.
	 * @return array
	 */
	public static function cart_fragment( $fragments ) {
		$fragments['span.aimp-header-cart-count'] = self::cart_badge( self::cart_count() );
		return $fragments;
	}

	/**
	 * Account link: the account page from the settings, otherwise WooCommerce's My account.
	 *
	 * @return string
	 */
	public static function account_url() {
		$page = AIMP_Settings::get( 'account_page' );
		if ( $page && 'publish' === get_post_status( $page ) ) {
			return get_permalink( $page );
		}
		return wc_get_page_permalink( 'myaccount' );
	}

	private static function divi_logo() {
		return function_exists( 'et_get_option' ) ? (string) et_get_option( 'divi_logo' ) : '';
	}

	/**
	 * The address of the logo picture (Divi's logo, otherwise the site logo), or '' when there is none.
	 *
	 * @return string
	 */
	public static function logo_url() {
		$divi = self::divi_logo();
		if ( '' !== $divi ) {
			return $divi;
		}
		$id = (int) get_theme_mod( 'custom_logo' );
		return $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
	}

	/**
	 * The logo: the one set in Divi (Theme Options → General → Logo), otherwise the WordPress site logo,
	 * otherwise the site name.
	 *
	 * @return string
	 */
	public static function logo_html() {
		$divi = self::divi_logo();
		if ( '' !== $divi ) {
			return sprintf(
				'<a class="aimp-header-logo" href="%1$s" rel="home"><img src="%2$s" alt="%3$s"></a>',
				esc_url( home_url( '/' ) ),
				esc_url( $divi ),
				esc_attr( get_bloginfo( 'name' ) )
			);
		}
		if ( has_custom_logo() ) {
			return get_custom_logo();
		}
		return '<a class="aimp-header-sitename" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
	}

	/**
	 * The info bar's default text: one item per line, the languages as "NL | FR | EN".
	 *
	 * @return string
	 */
	public static function default_info_bar() {
		return "Gratis levering vanaf €75 | Livraison gratuite dès 75 € | Free delivery from €75\n14 dagen retour | Retour sous 14 jours | 14 days return";
	}

	/**
	 * The items of the gold info bar under the header, in the visitor's language.
	 *
	 * @return string[]
	 */
	public static function info_bar_items() {
		$text  = AIMP_Settings::get_text( 'info_bar', self::default_info_bar() );
		$index = array_search( AIMP_I18n::current(), array_keys( AIMP_I18n::languages() ), true );
		$items = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			// One text for every language, or "NL | FR | EN".
			$item = ( count( $parts ) > 1 && false !== $index && isset( $parts[ $index ] ) && '' !== $parts[ $index ] ) ? $parts[ $index ] : $parts[0];
			if ( '' !== $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Gold line icons.
	 *
	 * @param string $name heart, bag, user, menu or close.
	 * @return string SVG.
	 */
	public static function icon( $name ) {
		$paths = array(
			'heart' => '<path d="M12 20.3s-7.5-4.6-7.5-10.1A4.2 4.2 0 0 1 12 7.6a4.2 4.2 0 0 1 7.5 2.6c0 5.5-7.5 10.1-7.5 10.1z"/>',
			'bag'   => '<path d="M5 8h14l-1.2 12H6.2z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
			'user'  => '<circle cx="12" cy="8" r="3.8"/><path d="M4.5 20.5c1-3.8 4-5.8 7.5-5.8s6.5 2 7.5 5.8"/>',
			'menu'  => '<path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17"/>',
			'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
			'search' => '<circle cx="10.5" cy="10.5" r="6"/><path d="M15 15l5.5 5.5"/>',
			'shop'   => '<path d="M4 9.5h16l-1.2 10.5H5.2z"/><path d="M3.5 9.5l2.2-5h12.6l2.2 5"/><path d="M9.5 13.5a2.5 2.5 0 0 0 5 0"/>',
			'home'   => '<path d="M3.5 11 12 4l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5.5h4V20"/>',
			// The customizer: a needle with thread.
			'kit'    => '<path d="M12 2.8v2"/><path d="M8.3 4.8h7.4c.4 3.3-1 5.6-1.9 6.8 1.7 1 3 2.8 3 5H7.2c0-2.2 1.3-4 3-5-.9-1.2-2.3-3.5-1.9-6.8z"/><path d="M12 16.6v3M12 19.6l-3.5 2M12 19.6l3.5 2M12 19.6v2"/>', // A dress form on a tripod.
		);
		return '<svg class="aimp-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( isset( $paths[ $name ] ) ? $paths[ $name ] : '' ) . '</svg>';
	}

	/**
	 * The address of the page being shown (without query), to mark the current tab.
	 *
	 * @return string
	 */
	private static function current_url() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- only compared with links.
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '/';
		return trailingslashit( home_url( $path ) );
	}

	/**
	 * Whether a tab's link is the page being shown.
	 *
	 * @param string $url     Tab link.
	 * @param string $current Current page.
	 * @return bool
	 */
	public static function is_current( $url, $current ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );
		return '' !== (string) $url && trailingslashit( home_url( $path ? $path : '/' ) ) === $current;
	}

	/**
	 * The header HTML.
	 *
	 * @return string
	 */
	public static function render() {
		self::$printed = true;
		if ( ! wp_style_is( 'aimp-header', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-header' );
		wp_enqueue_script( 'aimp-header' );
		wp_enqueue_script( 'aimp-search' );
		wp_localize_script( 'aimp-search', 'aimpSearch', AIMP_Search::config() );
		// The cart number comes from aimpBoot() (class-aimp-boot.php), not from WooCommerce's cart fragments, which
		// cost a request on every page.

		$favorites = AIMP_Settings::get( 'favorites_page' );
		$data      = array(
			'favorites_url' => ( $favorites && 'publish' === get_post_status( $favorites ) ) ? get_permalink( $favorites ) : '',
			'cart_url'      => wc_get_cart_url(),
			'account_url'   => self::account_url(),
			'search_url'    => AIMP_Search::results_url(),
			'shop_url'      => AIMP_Search::results_url(),
			'info_bar'      => self::info_bar_items(),
			'cart_badge'    => self::cart_badge( self::cart_count() ),
			// Phones: the tab bar at the bottom (Home, Shop, Customizer, Cart, Menu).
			'home_url'      => home_url( '/' ),
			'kit_url'       => ( AIMP_Settings::get( 'configurator_page' ) && 'publish' === get_post_status( AIMP_Settings::get( 'configurator_page' ) ) ) ? get_permalink( AIMP_Settings::get( 'configurator_page' ) ) : '',
			'current_url'   => self::current_url(),
			'menu'          => has_nav_menu( self::LOCATION )
				? wp_nav_menu(
					array(
						'theme_location' => self::LOCATION,
						'container'      => false,
						'menu_class'     => 'aimp-side-menu-list',
						'depth'          => 3,
						'echo'           => false,
						'fallback_cb'    => false,
					)
				)
				: '',
		);

		ob_start();
		$template = locate_template( 'atelier-irisee/header.php' );
		include $template ? $template : AIMP_PLUGIN_DIR . 'templates/header.php';
		return ob_get_clean();
	}
}
