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

		if ( ! AIMP_Settings::get( 'header_enabled' ) || is_admin() ) {
			return;
		}
		add_filter( 'render_block_core/template-part', array( __CLASS__, 'replace_block_header' ), 20, 2 );
		add_action( 'wp_body_open', array( __CLASS__, 'classic_header' ), 5 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
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
	private static function cart_badge( $count ) {
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

	/**
	 * The logo: the one set in Divi (Theme Options → General → Logo), otherwise the WordPress site logo,
	 * otherwise the site name.
	 *
	 * @return string
	 */
	public static function logo_html() {
		$divi = function_exists( 'et_get_option' ) ? (string) et_get_option( 'divi_logo' ) : '';
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
		);
		return '<svg class="aimp-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( isset( $paths[ $name ] ) ? $paths[ $name ] : '' ) . '</svg>';
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
		if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}

		$favorites = AIMP_Settings::get( 'favorites_page' );
		$data      = array(
			'favorites_url' => ( $favorites && 'publish' === get_post_status( $favorites ) ) ? get_permalink( $favorites ) : '',
			'cart_url'      => wc_get_cart_url(),
			'account_url'   => self::account_url(),
		'search_url'    => AIMP_Search::results_url(),
			'cart_badge'    => self::cart_badge( self::cart_count() ),
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
