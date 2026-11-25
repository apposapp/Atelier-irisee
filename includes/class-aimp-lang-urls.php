<?php
/**
 * Language addresses: every language has its own address. The shop's own language (Atelier Irisee → Settings →
 * Plugin language, standard Dutch) keeps the plain address (/stoffen/), the others get theirs (/fr/stoffen/,
 * /en/stoffen/). Search engines can then list each language, with hreflang links between them; before, French and
 * English shared the Dutch address (a cookie) and were never found.
 *
 * - The language part is taken off the address before WordPress reads it, so every page, product, category and
 *   shop request works as before; every link the site makes (home_url) gets it back on that page.
 * - Old links with ?aimp_lang=fr go to the language's address for good (301), and set the language cookie.
 * - A visitor whose cookie says another language, on a page in the shop's own language, goes to their language
 *   (a small script, so it also works on cached pages; search engines have no cookie and stay).
 * - Switched off with Atelier Irisee → Settings → Language addresses, or without pretty permalinks.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Lang_Urls {

	/** @var string The language in the address ('' = none: the shop's own language). */
	private static $lang = '';

	/** @var string|null The address as requested, with its language part. */
	private static $original = null;

	/** @var bool|null */
	private static $enabled = null;

	/**
	 * Whether languages have their own addresses.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( null === self::$enabled ) {
			$options       = get_option( 'aimp_settings', array() );
			$on            = ! is_array( $options ) || ! isset( $options['lang_urls'] ) || ! empty( $options['lang_urls'] );
			self::$enabled = $on && '' !== (string) get_option( 'permalink_structure' );
		}
		return self::$enabled;
	}

	/**
	 * Runs when the plugin loads, before WordPress reads the address.
	 */
	public static function boot() {
		if ( ! self::enabled() ) {
			return;
		}
		self::strip();
		add_filter( 'home_url', array( __CLASS__, 'home_url' ), 20, 3 );
		add_action( 'parse_request', array( __CLASS__, 'restore' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_param' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_filter( 'language_attributes', array( __CLASS__, 'html_lang' ), 20 );
		// WordPress's own "right address" check compares with the address as requested (with its language part).
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ), 20 );
		// A sitemap per language (/sitemap-lang-fr.xml), named in robots.txt.
		// After everything is registered (WooCommerce's products and categories), before WordPress reads the address.
		add_action( 'wp_loaded', array( __CLASS__, 'sitemap' ), 1 );
		// After All in One SEO, which writes robots.txt anew at 10000.
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10001 );
		foreach ( array( 'save_post', 'deleted_post', 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_sitemaps' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Sitemaps per language
	 * ------------------------------------------------------------------ */

	/** Kept for a while (made again after a change). */
	const SITEMAP_CACHE = 'aimp_lang_sitemap_';

	/**
	 * The address of a language's sitemap.
	 *
	 * @param string $lang Language.
	 * @return string
	 */
	public static function sitemap_url( $lang ) {
		return untrailingslashit( (string) get_option( 'home' ) ) . '/sitemap-lang-' . $lang . '.xml';
	}

	public static function flush_sitemaps() {
		foreach ( self::prefixed() as $code ) {
			delete_transient( self::SITEMAP_CACHE . $code );
		}
	}

	/**
	 * @param string $output robots.txt.
	 * @return string
	 */
	public static function robots_txt( $output ) {
		foreach ( self::prefixed() as $code ) {
			$line = 'Sitemap: ' . self::sitemap_url( $code );
			if ( false === strpos( $output, $line ) ) {
				$output = rtrim( $output ) . "\n" . $line . "\n";
			}
		}
		return $output;
	}

	/**
	 * /sitemap-lang-fr.xml: every page, post, product, product category and brand in that language, each with its
	 * addresses in the other languages.
	 */
	public static function sitemap() {
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared.
		if ( ! preg_match( '#^' . preg_quote( self::home_path(), '#' ) . '/sitemap-lang-([a-z]{2})\.xml$#', $path, $m ) || ! in_array( $m[1], self::prefixed(), true ) ) {
			return;
		}
		$lang = $m[1];
		$xml  = get_transient( self::SITEMAP_CACHE . $lang );
		if ( ! is_string( $xml ) || '' === $xml ) {
			$xml = self::sitemap_xml( $lang );
			set_transient( self::SITEMAP_CACHE . $lang, $xml, 12 * HOUR_IN_SECONDS );
		}
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_url() below.
		exit;
	}

	/**
	 * The addresses of the shop in its own language: [ address, last change ].
	 *
	 * @return array
	 */
	private static function sitemap_entries() {
		$entries = array();
		$skip    = array();
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$skip[] = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $page ) : 0;
		}
		$options = get_option( 'aimp_settings', array() );
		$skip[]  = is_array( $options ) && ! empty( $options['favorites_page'] ) ? (int) $options['favorites_page'] : 0;
		$hidden  = array();
		if ( taxonomy_exists( 'product_visibility' ) ) {
			foreach ( array( 'exclude-from-catalog', 'exclude-from-search' ) as $slug ) {
				$term = get_term_by( 'slug', $slug, 'product_visibility' );
				if ( $term ) {
					$hidden[] = (int) $term->term_taxonomy_id;
				}
			}
		}
		$posts = get_posts(
			array(
				'post_type'        => array( 'page', 'post', 'product' ),
				'post_status'      => 'publish',
				'posts_per_page'   => 2000,
				'has_password'     => false,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		foreach ( $posts as $post ) {
			if ( in_array( (int) $post->ID, $skip, true ) ) {
				continue;
			}
			// A product hidden from the shop and search (Catalog visibility → Hidden).
			if ( 'product' === $post->post_type && $hidden && 2 === count( array_intersect( $hidden, wp_get_object_terms( $post->ID, 'product_visibility', array( 'fields' => 'tt_ids' ) ) ) ) ) {
				continue;
			}
			$entries[] = array( get_permalink( $post ), get_post_modified_time( 'c', true, $post ) );
		}
		$uncategorized = (int) get_option( 'default_product_cat' );
		foreach ( array( 'product_cat', 'product_brand', 'category' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( 'product_cat' === $taxonomy && (int) $term->term_id === $uncategorized ) {
					continue;
				}
				$link = get_term_link( $term );
				if ( is_string( $link ) && '' !== $link ) {
					$entries[] = array( $link, '' );
				}
			}
		}
		// Once per address (a category can be its shop page itself).
		$unique = array();
		foreach ( $entries as $entry ) {
			$unique[ $entry[0] ] = $entry;
		}
		return array_values( $unique );
	}

	/**
	 * @param string $lang Language.
	 * @return string
	 */
	private static function sitemap_xml( $lang ) {
		$codes = array_keys( AIMP_I18n::languages() );
		$xml   = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
			'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
		foreach ( self::sitemap_entries() as $entry ) {
			list( $url, $modified ) = $entry;
			$xml .= "\t<url>\n\t\t<loc>" . esc_url( self::with_lang( $url, $lang ) ) . "</loc>\n";
			if ( '' !== $modified ) {
				$xml .= "\t\t<lastmod>" . esc_html( $modified ) . "</lastmod>\n";
			}
			foreach ( $codes as $code ) {
				$xml .= "\t\t" . '<xhtml:link rel="alternate" hreflang="' . esc_attr( $code ) . '" href="' . esc_url( self::with_lang( $url, $code ) ) . '"/>' . "\n";
			}
			$xml .= "\t\t" . '<xhtml:link rel="alternate" hreflang="x-default" href="' . esc_url( self::with_lang( $url, AIMP_I18n::default_language() ) ) . '"/>' . "\n";
			$xml .= "\t</url>\n";
		}
		return $xml . '</urlset>' . "\n";
	}

	/**
	 * The languages with an address part (all but the shop's own).
	 *
	 * @return string[]
	 */
	public static function prefixed() {
		return array_values( array_diff( array_keys( AIMP_I18n::languages() ), array( AIMP_I18n::default_language() ) ) );
	}

	/**
	 * The path of the site's home ('' for a site at the root of its domain).
	 *
	 * @return string
	 */
	private static function home_path() {
		return untrailingslashit( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ) );
	}

	/**
	 * The language part of this request's address ('' when there is none).
	 *
	 * @return string
	 */
	public static function prefix_lang() {
		return self::$lang;
	}

	/**
	 * The language of the page: its address part, else the shop's own language.
	 *
	 * @return string
	 */
	public static function page_lang() {
		return '' !== self::$lang ? self::$lang : AIMP_I18n::default_language();
	}

	/**
	 * Whether this request shows a page (not a background request of the shop, the dashboard, the REST API or cron):
	 * its language then always follows its address.
	 *
	 * @return bool
	 */
	public static function is_page_view() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides the request kind.
		return ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! isset( $_GET['wc-ajax'] ) && ! isset( $_GET['rest_route'] ) && false === strpos( $uri, '/wp-json/' );
	}

	/**
	 * Takes the language part off the address, so WordPress finds the page as usual.
	 */
	private static function strip() {
		if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri   = (string) $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- WordPress reads it the same way.
		$home  = self::home_path();
		$codes = array_map(
			function ( $code ) {
				return preg_quote( $code, '#' );
			},
			self::prefixed()
		);
		if ( ! $codes || ! preg_match( '#^' . preg_quote( $home, '#' ) . '/(' . implode( '|', $codes ) . ')(?=/|\?|\#|$)#', $uri, $m ) ) {
			return;
		}
		self::$lang     = $m[1];
		self::$original = $uri;
		$rest           = (string) substr( $uri, strlen( $m[0] ) );
		if ( '' === $rest || '/' !== $rest[0] ) {
			$rest = '/' . $rest;
		}
		$_SERVER['REQUEST_URI'] = $home . $rest;
	}

	/**
	 * Once WordPress has read the address: the address as requested again (for the "right address" check, paging
	 * links and the like).
	 */
	public static function restore() {
		if ( null !== self::$original ) {
			$_SERVER['REQUEST_URI'] = self::$original;
		}
	}

	/**
	 * @param string|false $redirect The address WordPress would send to.
	 * @return string|false
	 */
	public static function redirect_canonical( $redirect ) {
		if ( ! $redirect || '' === self::$lang ) {
			return $redirect;
		}
		// Only to an address in this same language (never back to the plain one).
		return self::lang_of( $redirect ) === self::$lang ? $redirect : false;
	}

	/**
	 * Every link the site makes on a page in another language gets that language's part.
	 *
	 * @param string $url    Address.
	 * @param string $path   Path asked for.
	 * @param string $scheme Scheme.
	 * @return string
	 */
	public static function home_url( $url, $path = '', $scheme = null ) {
		if ( '' === self::$lang || ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $url;
		}
		return self::with_lang( $url, self::$lang );
	}

	/**
	 * Splits an address of this site in [ start up to the home path, the rest ]; null for another site.
	 *
	 * @param string $url Address.
	 * @return array|null
	 */
	private static function split( $url ) {
		$home = (string) preg_replace( '#^https?:#i', '', untrailingslashit( (string) get_option( 'home' ) ) );
		$pos  = stripos( $url, $home );
		if ( false !== $pos && $pos <= 6 ) {
			return array( substr( $url, 0, $pos + strlen( $home ) ), (string) substr( $url, $pos + strlen( $home ) ) );
		}
		// A relative address (/stoffen/).
		$path = self::home_path();
		if ( '' !== $url && '/' === $url[0] && ( strlen( $url ) < 2 || '/' !== $url[1] ) && ( '' === $path || 0 === strpos( $url, $path . '/' ) || $url === $path ) ) {
			return array( $path, (string) substr( $url, strlen( $path ) ) );
		}
		return null;
	}

	/**
	 * The language of an address of this site ('' for the shop's own language or another site).
	 *
	 * @param string $url Address.
	 * @return string
	 */
	public static function lang_of( $url ) {
		$parts = self::split( (string) $url );
		if ( ! $parts ) {
			return '';
		}
		foreach ( self::prefixed() as $code ) {
			if ( preg_match( '#^/' . preg_quote( $code, '#' ) . '(?=/|\?|\#|$)#', $parts[1] ) ) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * An address of this site in a language: its language part taken off, and the new one put in (none for the shop's
	 * own language). Files, the dashboard, feeds and sitemaps keep their address.
	 *
	 * @param string $url  Address.
	 * @param string $lang Language.
	 * @return string
	 */
	public static function with_lang( $url, $lang ) {
		$parts = self::split( (string) $url );
		if ( ! $parts ) {
			return $url;
		}
		list( $start, $rest ) = $parts;
		$old = self::lang_of( $url );
		if ( '' !== $old ) {
			$rest = (string) substr( $rest, strlen( $old ) + 1 );
		}
		if ( preg_match( '#^/?(wp-|xmlrpc\.php|feed(/|$)|comments/feed|[^?\#]*\.(xml|xsl|txt|php)(\?|\#|$))#i', $rest ) ) {
			return $start . $rest;
		}
		if ( $lang === AIMP_I18n::default_language() || ! in_array( $lang, self::prefixed(), true ) ) {
			return $start . ( '' === $rest ? '/' : $rest );
		}
		if ( '' === $rest || '/' !== $rest[0] ) {
			$rest = '/' . $rest;
		}
		return $start . '/' . $lang . $rest;
	}

	/**
	 * This page's full address as requested (with its language part).
	 *
	 * @return string
	 */
	public static function current_url() {
		$uri  = null !== self::$original ? self::$original : ( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- escaped where printed.
		$home = (string) get_option( 'home' );
		$host = (string) preg_replace( '#^(https?://[^/]+).*$#i', '$1', $home );
		return set_url_scheme( $host . $uri );
	}

	/**
	 * The address of this page in another language (the language flags): the language's own address; the shop's own
	 * language with ?aimp_lang, so the choice is remembered (the server then drops it again).
	 *
	 * @param string $lang Language.
	 * @return string
	 */
	public static function switch_url( $lang ) {
		$url = self::with_lang( remove_query_arg( 'aimp_lang', self::current_url() ), $lang );
		return $lang === AIMP_I18n::default_language() ? add_query_arg( 'aimp_lang', $lang, $url ) : $url;
	}

	/**
	 * Old links with ?aimp_lang=…: to the language's own address, for good; the cookie remembers the choice.
	 */
	public static function redirect_param() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only selects a display language.
		if ( ! isset( $_GET['aimp_lang'] ) || isset( $_GET['wc-ajax'] ) || ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() ) ) {
			return;
		}
		$lang = sanitize_key( wp_unslash( $_GET['aimp_lang'] ) );
		// phpcs:enable
		if ( ! AIMP_I18n::is_valid( $lang ) ) {
			return;
		}
		if ( ! headers_sent() ) {
			setcookie( AIMP_I18n::COOKIE, $lang, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		}
		wp_safe_redirect( self::with_lang( remove_query_arg( 'aimp_lang', self::current_url() ), $lang ), 301 );
		exit;
	}

	/**
	 * The addresses of this page in every language: language => address (null on pages that have none, as search
	 * results and "not found"). Only the page number is kept of the parameters.
	 *
	 * @return array
	 */
	public static function alternates() {
		if ( is_404() || is_search() || ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() ) ) {
			return array();
		}
		$url   = self::current_url();
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$base  = (string) strtok( $url, '?' );
		parse_str( $query, $args );
		$keep = array_intersect_key( $args, array_flip( array( 'aimp_page', 'paged' ) ) );
		$list = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			$list[ $code ] = add_query_arg( $keep, self::with_lang( $base, $code ) );
		}
		return $list;
	}

	/**
	 * hreflang links (search engines), and the page's language with its addresses for the scripts (the language
	 * buttons of the shop and the customizer go to the page in that language).
	 */
	public static function head() {
		if ( is_admin() ) {
			return;
		}
		$list = self::alternates();
		$noindex = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
		if ( $list && ! $noindex ) {
			foreach ( $list as $code => $href ) {
				echo '<link rel="alternate" hreflang="' . esc_attr( $code ) . '" href="' . esc_url( $href ) . '">' . "\n";
			}
			echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $list[ AIMP_I18n::default_language() ] ) . '">' . "\n";
		}
		$paths = array();
		foreach ( $list as $code => $href ) {
			$paths[ $code ] = (string) strtok( wp_make_link_relative( $href ), '?' );
		}
		printf(
			'<script>window.aimpLang=%s;</script>' . "\n",
			wp_json_encode(
				array(
					'page'    => self::page_lang(),
					'default' => AIMP_I18n::default_language(),
					'urls'    => (object) $paths,
				)
			)
		);
	}

	/**
	 * <html lang="…"> in the page's language.
	 *
	 * @param string $output Attributes.
	 * @return string
	 */
	public static function html_lang( $output ) {
		$lang = self::page_lang();
		return preg_match( '/\blang="[^"]*"/', $output ) ? preg_replace( '/\blang="[^"]*"/', 'lang="' . esc_attr( $lang ) . '"', $output ) : trim( $output . ' lang="' . esc_attr( $lang ) . '"' );
	}
}
