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

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_filter( 'wc_get_template_part', array( __CLASS__, 'template_part' ), 20, 3 );
		add_filter( 'get_block_templates', array( __CLASS__, 'block_templates' ), 20, 3 );
		add_filter( 'get_block_template', array( __CLASS__, 'block_template' ), 20, 3 );
	}

	public static function enabled() {
		return (bool) AIMP_Settings::get( 'product_design' );
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
			/* translators: 1: number of 10 cm units, 2: total length in cm */
			'unitHelp'    => __( '%1$d × 10 cm = %2$d cm', 'atelier-irisee-master-plugin' ),
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
					'measureImage' => AIMP_PLUGIN_URL . 'assets/images/lichaamsmaten.png',
					'i18n'         => self::strings(),
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
			include $template ? $template : AIMP_PLUGIN_DIR . 'templates/product/single.php';

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
		$sizes    = $pattern ? AIMP_Catalog::get_sizes( $product->get_id() ) : null;

		if ( $sizes ) {
			$gallery = $sizes['pattern']['gallery'];
		} else {
			$extra = array();
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$extra[] = (int) get_post_thumbnail_id( $child_id );
				}
			}
			$gallery = AIMP_Catalog::images( $product, $extra );
		}

		$category = null;
		$terms    = get_the_terms( $product->get_id(), 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$category = reset( $terms );
		}

		$configurator = AIMP_Settings::get( 'configurator_page' );

		return array(
			'per_10cm'         => $per_10cm,
			'gallery'          => $gallery,
			'category'         => $category,
			'stock_text'       => $product->is_type( 'simple' ) ? AIMP_Catalog::stock_text( $product, $per_10cm ) : '',
			'attributes'       => AIMP_Catalog::attributes( $product ),
			'sizes'            => $sizes ? $sizes['sizes'] : array(),
			'configurator_url' => ( $pattern && $configurator && 'publish' === get_post_status( $configurator ) )
				? add_query_arg( 'aimp_pattern', $product->get_id(), get_permalink( $configurator ) )
				: '',
			'fitting'          => AIMP_Catalog::in_categories( $product, AIMP_Catalog::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) )
				? self::fitting_patterns( $product )
				: array(),
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
