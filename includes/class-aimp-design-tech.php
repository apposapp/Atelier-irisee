<?php
/**
 * Design studio (Whole site → SEO, speed and dashboard): search engines, faster pages and the studio in the
 * admin bar. Every setting is off unless switched on in the studio, except the admin bar link.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

class AIMP_Design_Tech {

	public static function init() {
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'open_graph' ), 5 );
		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'brand_schema' ), 20, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'speed' ), 20 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
	}

	/**
	 * A setting of this part (the draft in the studio's preview, else what visitors see).
	 *
	 * @param string $key Setting.
	 * @return mixed
	 */
	private static function opt( $key ) {
		return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( 'site-tech', $key, '' ) : '';
	}

	/**
	 * Filtered shop pages and search results: not in search engines (their links are still followed).
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( ! self::opt( 'seo_noindex' ) ) {
			return $robots;
		}
		$filtered = false;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only the names of the parameters are read.
		foreach ( array_keys( $_GET ) as $param ) {
			// The shop filters and the customizer links (aimp_cat, aimp_q, aimp_pattern, …); page numbers stay indexed.
			if ( 0 === strpos( (string) $param, 'aimp_' ) && 'aimp_page' !== $param ) {
				$filtered = true;
			}
		}
		if ( $filtered || is_search() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Open Graph tags (the picture and text when a page is shared), only when no SEO plugin already adds them.
	 */
	public static function open_graph() {
		if ( ! self::opt( 'seo_og' ) || ! is_singular() || defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return;
		}
		$id      = get_queried_object_id();
		$excerpt = has_excerpt( $id ) ? get_the_excerpt( $id ) : get_post_field( 'post_content', $id );
		$tags    = array(
			'og:type'        => is_singular( 'product' ) ? 'product' : 'article',
			'og:title'       => wp_strip_all_tags( get_the_title( $id ) ),
			'og:description' => wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $excerpt ) ), 30, '…' ),
			'og:url'         => get_permalink( $id ),
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:image'       => (string) get_the_post_thumbnail_url( $id, 'large' ),
			'twitter:card'   => 'summary_large_image',
		);
		$product = is_singular( 'product' ) && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( $product && '' !== (string) $product->get_price() ) {
			$tags['product:price:amount']   = wc_format_decimal( $product->get_price(), wc_get_price_decimals() );
			$tags['product:price:currency'] = get_woocommerce_currency();
		}
		foreach ( $tags as $property => $content ) {
			if ( '' !== trim( (string) $content ) ) {
				printf( '<meta %1$s="%2$s" content="%3$s">' . "\n", 0 === strpos( $property, 'twitter:' ) ? 'name' : 'property', esc_attr( $property ), esc_attr( $content ) );
			}
		}
	}

	/**
	 * The brand in the product's structured data (Google), when WooCommerce does not add it itself.
	 *
	 * @param array      $markup  Structured data.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function brand_schema( $markup, $product ) {
		if ( ! self::opt( 'seo_brand' ) || ! empty( $markup['brand'] ) || ! class_exists( 'AIMP_Product_Page' ) ) {
			return $markup;
		}
		$brand = AIMP_Product_Page::brand( $product );
		if ( '' !== $brand ) {
			$markup['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}
		return $markup;
	}

	/**
	 * Faster pages: no emoji script, no embed script, and the next page loaded while the mouse is on a link.
	 */
	public static function speed() {
		if ( self::opt( 'sp_noemoji' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}
		if ( self::opt( 'sp_noembed' ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_action(
				'wp_enqueue_scripts',
				function () {
					wp_dequeue_script( 'wp-embed' );
				},
				100
			);
		}
		if ( self::opt( 'sp_prefetch' ) && ! AIMP_Design::is_preview() ) {
			if ( function_exists( 'wp_get_speculation_rules_configuration' ) ) {
				// WordPress 6.8+: its own speculative loading, a little earlier (on hover instead of on click).
				add_filter(
					'wp_speculation_rules_configuration',
					function () {
						return array(
							'mode'      => 'prefetch',
							'eagerness' => 'moderate',
						);
					}
				);
			} else {
				add_action( 'wp_footer', array( __CLASS__, 'speculation_rules' ), 99 );
			}
		}
	}

	/**
	 * Older WordPress: the speculation rules themselves (browsers without support ignore them). Never the cart,
	 * checkout, account, log-in or links that change something.
	 */
	public static function speculation_rules() {
		$exclude = array( '/wp-admin/*', '/wp-login.php*', '/*\\?(.+)' );
		foreach ( array( wc_get_cart_url(), wc_get_checkout_url(), wc_get_page_permalink( 'myaccount' ) ) as $url ) {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( '' !== $path && '/' !== $path ) {
				$exclude[] = trailingslashit( $path ) . '*';
			}
		}
		$rules = array(
			'prefetch' => array(
				array(
					'source'    => 'document',
					'where'     => array(
						'and' => array(
							array( 'href_matches' => '/*' ),
							array( 'not' => array( 'href_matches' => $exclude ) ),
						),
					),
					'eagerness' => 'moderate',
				),
			),
		);
		echo '<script type="speculationrules">' . wp_json_encode( $rules ) . '</script>' . "\n";
	}

	/**
	 * "Design studio" in the admin bar on the site: opens the studio with this page in the preview.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function admin_bar( $bar ) {
		if ( is_admin() || ! current_user_can( 'manage_woocommerce' ) || self::opt( 'ad_nobar' ) || AIMP_Design::is_preview() || empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$current = esc_url_raw( ( is_ssl() ? 'https://' : 'http://' ) . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw().
		$bar->add_node(
			array(
				'id'    => 'aimp-design',
				'title' => __( 'Design studio', 'atelier-irisee-master-plugin' ),
				'href'  => add_query_arg(
					array(
						'page'      => 'aimp-design',
						'aimp_page' => rawurlencode( $current ),
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}
}
