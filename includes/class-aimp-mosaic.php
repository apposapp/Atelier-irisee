<?php
/**
 * Product mosaic: [atelier_irisee_mosaic type="patterns|fabrics|haberdashery|all"].
 *
 * Ten products in a mosaic of two large and eight small tiles: the best sellers (ties and products
 * without sales in random order), or the products chosen in the settings.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Mosaic {

	const TAG    = 'atelier_irisee_mosaic';
	const OPTION = 'aimp_mosaic';
	const COUNT  = 10;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ) );
	}

	/**
	 * @param string $key source, or a page type (product IDs).
	 * @return mixed
	 */
	public static function get( $key ) {
		$options = wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'source'       => 'bestsellers',
				'patterns'     => array(),
				'fabrics'      => array(),
				'haberdashery' => array(),
				'all'          => array(),
			)
		);
		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	/* ------------------------------------------------------------------
	 * Settings (WooCommerce → Atelier Irisee → Mosaic)
	 * ------------------------------------------------------------------ */

	private static function type_labels() {
		return array(
			'patterns'     => __( 'Patterns mosaic', 'atelier-irisee-master-plugin' ),
			'fabrics'      => __( 'Fabrics mosaic', 'atelier-irisee-master-plugin' ),
			'haberdashery' => __( 'Haberdashery mosaic', 'atelier-irisee-master-plugin' ),
			'all'          => __( 'Mixed mosaic', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function register_settings() {
		register_setting(
			'aimp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
		add_settings_section( 'aimp_mosaic', __( 'Mosaic', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'section_intro' ), AIMP_Settings::PAGE );
		add_settings_field( 'aimp_mosaic_source', __( 'Products in the mosaic', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_source' ), AIMP_Settings::PAGE, 'aimp_mosaic' );
		foreach ( self::type_labels() as $type => $label ) {
			add_settings_field( 'aimp_mosaic_' . $type, $label, array( __CLASS__, 'render_products' ), AIMP_Settings::PAGE, 'aimp_mosaic', array( 'type' => $type ) );
		}
	}

	public static function section_intro() {
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'Show ten products in a mosaic with %s (type="patterns", "fabrics", "haberdashery" or "all").', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_mosaic type="fabrics"]</code>'
			)
		) . '</p>';
	}

	public static function render_source() {
		$source = self::get( 'source' );
		foreach ( array(
			'bestsellers' => __( 'Best sellers (products with the same sales, or without sales yet, in random order)', 'atelier-irisee-master-plugin' ),
			'manual'      => __( 'My choice (the products chosen below)', 'atelier-irisee-master-plugin' ),
		) as $value => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="radio" name="%1$s[source]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $value ),
				checked( $source, $value, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * @param array $args [ type ].
	 */
	public static function render_products( $args ) {
		$type = $args['type'];
		printf(
			'<select class="wc-product-search" multiple="multiple" style="width:100%%;max-width:600px" name="%1$s[%2$s][]" data-placeholder="%3$s" data-action="woocommerce_json_search_products">',
			esc_attr( self::OPTION ),
			esc_attr( $type ),
			esc_attr__( 'Search for a product…', 'atelier-irisee-master-plugin' )
		);
		foreach ( (array) self::get( $type ) as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				printf( '<option value="%1$d" selected>%2$s</option>', (int) $product_id, esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) );
			}
		}
		echo '</select><p class="description">' . esc_html__( 'Up to 10 products, in this order. Used with "My choice".', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	/**
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = (array) $input;
		$clean = array(
			'source' => ( isset( $input['source'] ) && 'manual' === $input['source'] ) ? 'manual' : 'bestsellers',
		);
		foreach ( array_keys( self::type_labels() ) as $type ) {
			$ids            = isset( $input[ $type ] ) ? array_map( 'absint', (array) $input[ $type ] ) : array();
			$clean[ $type ] = array_slice( array_values( array_unique( array_filter( $ids ) ) ), 0, self::COUNT );
		}
		return $clean;
	}

	/**
	 * WooCommerce's product search field on the settings page.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function admin_enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . AIMP_Settings::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}

	/* ------------------------------------------------------------------
	 * Choosing the products
	 * ------------------------------------------------------------------ */

	/**
	 * Visible product IDs of a page type.
	 *
	 * @param string $type Page type.
	 * @return int[]
	 */
	private static function scope_ids( $type ) {
		$roots = AIMP_Shop::roots( $type );
		if ( ! $roots && 'all' !== $type ) {
			return array();
		}
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 500,
				'no_found_rows'  => true,
				'tax_query'      => AIMP_Shop::scope_tax_query( $roots ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		return array_map( 'absint', $query->posts );
	}

	/**
	 * The ten products: best sellers first; equal sales (also no sales at all) in random order.
	 * Kept for an hour, so the mosaic doesn't change on every page view.
	 *
	 * @param string $type Page type.
	 * @return int[]
	 */
	private static function bestsellers( $type ) {
		$key = 'aimp_shop_mosaic_' . $type . '_' . AIMP_Shop::cache_version();
		$ids = get_transient( $key );
		if ( is_array( $ids ) ) {
			return $ids;
		}
		$ids = self::scope_ids( $type );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}
		$rows = array();
		foreach ( $ids as $id ) {
			$rows[] = array( (int) get_post_meta( $id, 'total_sales', true ), wp_rand(), $id );
		}
		usort(
			$rows,
			function ( $a, $b ) {
				return $a[0] !== $b[0] ? $b[0] - $a[0] : $a[1] - $b[1];
			}
		);
		$ids = array_slice( array_column( $rows, 2 ), 0, self::COUNT );
		set_transient( $key, $ids, HOUR_IN_SECONDS );
		return $ids;
	}

	/**
	 * @param string $type Page type.
	 * @return WC_Product[]
	 */
	public static function products( $type ) {
		$ids = 'manual' === self::get( 'source' ) ? (array) self::get( $type ) : array();
		if ( ! $ids ) {
			$ids = self::bestsellers( $type );
		}
		return array_slice(
			array_values(
				array_filter(
					array_map( 'wc_get_product', $ids ),
					function ( $product ) {
						return $product && 'publish' === $product->get_status() && $product->is_visible();
					}
				)
			),
			0,
			self::COUNT
		);
	}

	/* ------------------------------------------------------------------
	 * Output
	 * ------------------------------------------------------------------ */

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts     = shortcode_atts( array( 'type' => 'all' ), $atts, self::TAG );
		$type     = in_array( $atts['type'], AIMP_Shop::TYPES, true ) ? $atts['type'] : 'all';
		$products = self::products( $type );
		if ( ! $products ) {
			return '';
		}
		if ( ! wp_style_is( 'aimp-shop', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
			AIMP_Shop::register_assets();
		}
		wp_enqueue_style( 'aimp-shop' );

		$html = '';
		foreach ( $products as $index => $product ) {
			$per_10cm = AIMP_Catalog::sold_per_10cm( $product );
			$image_id = $product->get_image_id();
			$size     = in_array( $index, array( 0, 7 ), true ) ? 'woocommerce_single' : 'woocommerce_thumbnail';
			$image    = $image_id ? wp_get_attachment_image_url( $image_id, $size ) : wc_placeholder_img_src( $size );
			$html    .= sprintf(
				'<li class="aimp-mosaic-tile aimp-mosaic-tile--%1$d aimp-fav-wrap"><a href="%2$s"><img src="%3$s" alt="%4$s" loading="lazy"><span class="aimp-mosaic-caption"><span class="aimp-mosaic-name">%5$s</span><span class="aimp-mosaic-price">%6$s%7$s</span></span></a>%8$s</li>',
				$index + 1,
				esc_url( $product->get_permalink() ),
				esc_url( $image ),
				esc_attr( wp_strip_all_tags( $product->get_name() ) ),
				esc_html( wp_strip_all_tags( $product->get_name() ) ),
				wp_kses_post( $product->get_price_html() ),
				$per_10cm ? ' <small>' . esc_html__( 'per 10 cm', 'atelier-irisee-master-plugin' ) . '</small>' : '',
				AIMP_Favorites::button_html( $product->get_id(), 'aimp-fav--overlay' )
			);
		}
		return '<div class="aimp-configurator aimp-mosaic"><ul class="aimp-mosaic-grid">' . $html . '</ul></div>';
	}
}
