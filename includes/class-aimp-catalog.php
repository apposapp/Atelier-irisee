<?php
/**
 * Catalog rules: which patterns, sizes, fabrics, buttons and zips are eligible.
 *
 * Both the configurator endpoints and the cart validation use these methods,
 * so the rules shown to the customer and the rules enforced on add-to-cart
 * cannot drift apart.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Catalog {

	const META_FABRIC_UNITS = '_aimp_fabric_units';
	const META_FABRIC_CATS  = '_aimp_fabric_cats';
	const META_BUTTON_COUNT = '_aimp_button_count';
	const META_ZIP_COUNT    = '_aimp_zip_count';
	const META_ZIP_LENGTH   = '_aimp_zip_length';
	const META_BUST         = '_aimp_bust';
	const META_WAIST        = '_aimp_waist';
	const META_HEIGHT       = '_aimp_height';

	/** Length of one fabric unit in cm. */
	const FABRIC_UNIT_CM = 10;

	/* ------------------------------------------------------------------
	 * Category helpers
	 * ------------------------------------------------------------------ */

	/**
	 * A category and all of its descendants.
	 *
	 * @param int $term_id Category ID.
	 * @return int[]
	 */
	public static function category_tree( $term_id ) {
		$term_id = absint( $term_id );
		if ( ! $term_id ) {
			return array();
		}
		$children = get_term_children( $term_id, 'product_cat' );
		$children = is_wp_error( $children ) ? array() : array_map( 'absint', $children );
		return array_merge( array( $term_id ), $children );
	}

	/**
	 * Whether a product (or the parent of a variation) is in any of the given categories.
	 *
	 * @param int|WC_Product $product  Product or ID.
	 * @param int[]          $term_ids Category IDs (already expanded to include children).
	 * @return bool
	 */
	public static function in_categories( $product, $term_ids ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		if ( ! $product || empty( $term_ids ) ) {
			return false;
		}
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		return (bool) array_intersect( wc_get_product_cat_ids( $id ), array_map( 'absint', $term_ids ) );
	}

	/**
	 * Direct subcategories of the Patterns category, used as filter tabs.
	 *
	 * @return array[] List of [ id, name ].
	 */
	public static function get_pattern_categories() {
		$root = AIMP_Settings::get( 'pattern_cat' );
		if ( ! $root ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $root,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			},
			$terms
		);
	}

	/**
	 * All subcategories of the Fabrics category (for the admin checkboxes).
	 *
	 * @return WP_Term[]
	 */
	public static function get_fabric_categories() {
		$root = AIMP_Settings::get( 'fabric_cat' );
		if ( ! $root ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'child_of'   => $root,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/* ------------------------------------------------------------------
	 * Querying
	 * ------------------------------------------------------------------ */

	/**
	 * Paginated product IDs in the given categories.
	 *
	 * @param int[]  $term_ids   Category IDs (children are included automatically).
	 * @param string $type       Product type slug (variable, simple).
	 * @param int    $page       1-based page.
	 * @param array  $meta_query Optional extra meta query.
	 * @return array [ ids => int[], page => int, pages => int ]
	 */
	private static function query_products( $term_ids, $type, $page, $meta_query = array() ) {
		$empty = array(
			'ids'   => array(),
			'page'  => 1,
			'pages' => 0,
		);
		if ( empty( $term_ids ) ) {
			return $empty;
		}

		$tax_query = array(
			'relation' => 'AND',
			array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => array_map( 'absint', $term_ids ),
				'include_children' => true,
			),
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => array( $type ),
			),
		);

		$visibility = wc_get_product_visibility_term_ids();
		$hidden     = array( $visibility['exclude-from-catalog'] ?? 0 );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$hidden[] = $visibility['outofstock'] ?? 0;
		}
		$hidden = array_filter( $hidden );
		if ( $hidden ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => $hidden,
				'operator' => 'NOT IN',
			);
		}

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, AIMP_Settings::get( 'per_page' ) ),
			'paged'               => max( 1, absint( $page ) ),
			'orderby'             => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'tax_query'           => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$query = new WP_Query( $args );
		return array(
			'ids'   => array_map( 'absint', $query->posts ),
			'page'  => max( 1, absint( $page ) ),
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Basic card data for a product grid.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private static function card( $product ) {
		$image_id = $product->get_image_id();
		return array(
			'id'          => $product->get_id(),
			'name'        => wp_strip_all_tags( $product->get_name() ),
			'image'       => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			'image_large' => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) : wc_placeholder_img_src( 'woocommerce_single' ),
			'image_alt'   => $image_id ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '',
			'price'       => (float) wc_get_price_to_display( $product ),
			'price_html'  => $product->get_price_html(),
			'permalink'   => $product->get_permalink(),
		);
	}

	/**
	 * Patterns for the grid.
	 *
	 * @param int $cat_id Filter category (0 = all patterns).
	 * @param int $page   Page.
	 * @return array
	 */
	public static function get_patterns( $cat_id, $page ) {
		$root = AIMP_Settings::get( 'pattern_cat' );
		$tree = self::category_tree( $root );
		$cat  = absint( $cat_id );
		$cats = ( $cat && in_array( $cat, $tree, true ) ) ? array( $cat ) : ( $root ? array( $root ) : array() );

		$result = self::query_products( $cats, 'variable', $page );
		$items  = array();
		foreach ( $result['ids'] as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$items[] = self::card( $product );
			}
		}
		return array(
			'items' => $items,
			'page'  => $result['page'],
			'pages' => $result['pages'],
		);
	}

	/**
	 * A pattern product, if it is a variable product in the Patterns category.
	 *
	 * @param int $pattern_id Product ID.
	 * @return WC_Product_Variable|null
	 */
	public static function get_pattern( $pattern_id ) {
		$product = wc_get_product( absint( $pattern_id ) );
		if ( ! $product || ! $product->is_type( 'variable' ) || 'publish' !== $product->get_status() ) {
			return null;
		}
		if ( ! self::in_categories( $product, self::category_tree( AIMP_Settings::get( 'pattern_cat' ) ) ) ) {
			return null;
		}
		return $product;
	}

	/**
	 * Requirements stored on a size variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array|null Null when the variation is not a valid pattern size.
	 */
	public static function get_requirements( $variation_id ) {
		$variation = wc_get_product( absint( $variation_id ) );
		if ( ! $variation || ! $variation->is_type( 'variation' ) || 'publish' !== $variation->get_status() ) {
			return null;
		}
		$pattern = self::get_pattern( $variation->get_parent_id() );
		if ( ! $pattern ) {
			return null;
		}

		$fabric_cats = $variation->get_meta( self::META_FABRIC_CATS );
		$fabric_cats = is_array( $fabric_cats ) ? array_map( 'absint', $fabric_cats ) : array();
		// Only subcategories that still belong to the Fabrics tree count.
		$fabric_cats = array_values( array_intersect( $fabric_cats, self::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) ) );

		return array(
			'variation'    => $variation,
			'pattern'      => $pattern,
			'size'         => self::size_label( $variation ),
			'fabric_units' => absint( $variation->get_meta( self::META_FABRIC_UNITS ) ),
			'fabric_cats'  => $fabric_cats,
			'button_count' => absint( $variation->get_meta( self::META_BUTTON_COUNT ) ),
			'zip_count'    => absint( $variation->get_meta( self::META_ZIP_COUNT ) ),
			'zip_length'   => (string) $variation->get_meta( self::META_ZIP_LENGTH ),
			'bust'         => (string) $variation->get_meta( self::META_BUST ),
			'waist'        => (string) $variation->get_meta( self::META_WAIST ),
			'height'       => (string) $variation->get_meta( self::META_HEIGHT ),
		);
	}

	/**
	 * Human size label from the variation attributes, e.g. "M".
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return string
	 */
	public static function size_label( $variation ) {
		$label = wc_get_formatted_variation( $variation, true, false, false );
		return '' !== $label ? wp_strip_all_tags( $label ) : '#' . $variation->get_id();
	}

	/**
	 * "12 × 10 cm (1.20 m)".
	 *
	 * @param int $units Fabric units.
	 * @return string
	 */
	public static function fabric_text( $units ) {
		return sprintf(
			/* translators: 1: number of units, 2: unit length in cm, 3: total length in metres */
			__( '%1$d × %2$d cm (%3$s m)', 'atelier-irisee-master-plugin' ),
			$units,
			self::FABRIC_UNIT_CM,
			AIMP_I18n::number( $units * self::FABRIC_UNIT_CM / 100, 2 )
		);
	}

	/**
	 * Pattern details and its sizes with measurements and requirements.
	 *
	 * @param int $pattern_id Pattern product ID.
	 * @return array|null
	 */
	public static function get_sizes( $pattern_id ) {
		$pattern = self::get_pattern( $pattern_id );
		if ( ! $pattern ) {
			return null;
		}

		$sizes = array();
		foreach ( $pattern->get_children() as $child_id ) {
			$req = self::get_requirements( $child_id );
			if ( ! $req ) {
				continue;
			}
			$variation = $req['variation'];
			$sizes[]   = array(
				'id'           => $variation->get_id(),
				'label'        => $req['size'],
				'price'        => (float) wc_get_price_to_display( $variation ),
				'price_html'   => $variation->get_price_html(),
				'available'    => $variation->is_purchasable() && $variation->is_in_stock(),
				'bust'         => $req['bust'],
				'waist'        => $req['waist'],
				'height'       => $req['height'],
				'fabric_units' => $req['fabric_units'],
				'fabric_text'  => self::fabric_text( $req['fabric_units'] ),
				'button_count' => $req['button_count'],
				'zip_count'    => $req['zip_count'],
				'zip_length'   => $req['zip_length'],
			);
		}

		$card                      = self::card( $pattern );
		$card['short_description'] = wp_kses_post( wpautop( $pattern->get_short_description() ) );

		return array(
			'pattern' => $card,
			'sizes'   => $sizes,
		);
	}

	/**
	 * Whether a product is a published simple product in the given categories.
	 *
	 * @param int   $product_id Product ID.
	 * @param int[] $term_ids   Allowed categories.
	 * @return WC_Product|null
	 */
	private static function get_material( $product_id, $term_ids ) {
		$product = wc_get_product( absint( $product_id ) );
		if ( ! $product || ! $product->is_type( 'simple' ) || 'publish' !== $product->get_status() ) {
			return null;
		}
		return self::in_categories( $product, $term_ids ) ? $product : null;
	}

	/**
	 * The fabric product if it is allowed for these requirements.
	 *
	 * @param int   $fabric_id Product ID.
	 * @param array $req       Requirements from get_requirements().
	 * @return WC_Product|null
	 */
	public static function get_allowed_fabric( $fabric_id, $req ) {
		$tree = array();
		foreach ( $req['fabric_cats'] as $cat ) {
			$tree = array_merge( $tree, self::category_tree( $cat ) );
		}
		return self::get_material( $fabric_id, $tree );
	}

	/**
	 * The button or zip product if it is allowed for these requirements.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type       'buttons' or 'zips'.
	 * @param array  $req        Requirements.
	 * @return WC_Product|null
	 */
	public static function get_allowed_notion( $product_id, $type, $req ) {
		if ( 0 === self::notion_count( $type, $req ) ) {
			return null;
		}
		$product = self::get_material( $product_id, self::category_tree( self::notion_category( $type ) ) );
		if ( $product && 'zips' === $type && ! self::zip_length_matches( $product, $req['zip_length'] ) ) {
			return null;
		}
		return $product;
	}

	/**
	 * Required quantity for buttons or zips.
	 *
	 * @param string $type 'buttons' or 'zips'.
	 * @param array  $req  Requirements.
	 * @return int
	 */
	public static function notion_count( $type, $req ) {
		return 'zips' === $type ? $req['zip_count'] : $req['button_count'];
	}

	private static function notion_category( $type ) {
		return 'zips' === $type ? AIMP_Settings::get( 'zip_cat' ) : AIMP_Settings::get( 'button_cat' );
	}

	private static function zip_length_matches( $product, $required ) {
		$length = $product->get_meta( self::META_ZIP_LENGTH );
		if ( '' === (string) $length || '' === (string) $required ) {
			return '' === (string) $required;
		}
		return abs( (float) $length - (float) $required ) < 0.01;
	}

	/**
	 * Visible product attributes as label/value pairs.
	 *
	 * @param WC_Product $product Product.
	 * @return array[]
	 */
	private static function attributes( $product ) {
		$rows = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute->get_visible() ) {
				continue;
			}
			if ( $attribute->is_taxonomy() ) {
				$values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
			} else {
				$values = $attribute->get_options();
			}
			$rows[] = array(
				'label' => wc_attribute_label( $attribute->get_name(), $product ),
				'value' => wp_strip_all_tags( implode( ', ', $values ) ),
			);
		}
		return $rows;
	}

	/**
	 * Card plus availability and detail info for a material that is needed $qty times.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $qty     Required quantity.
	 * @param bool       $fabric  Whether stock is shown in fabric units.
	 * @return array
	 */
	private static function material_card( $product, $qty, $fabric ) {
		$card                = self::card( $product );
		$card['qty']         = $qty;
		$card['available']   = $product->is_purchasable() && $product->is_in_stock() && $product->has_enough_stock( $qty );
		$card['total_html']  = wc_price( $card['price'] * $qty );
		$card['description'] = wp_kses_post( wpautop( $product->get_short_description() ) );
		$card['attributes']  = self::attributes( $product );
		$card['stock_text']  = '';

		if ( $product->managing_stock() ) {
			$stock              = (int) $product->get_stock_quantity();
			$card['stock_text'] = $fabric
				? sprintf(
					/* translators: 1: units in stock, 2: metres in stock */
					__( '%1$d × 10 cm in stock (%2$s m)', 'atelier-irisee-master-plugin' ),
					$stock,
					AIMP_I18n::number( $stock * self::FABRIC_UNIT_CM / 100, 2 )
				)
				: sprintf(
					/* translators: %d: pieces in stock */
					__( '%d in stock', 'atelier-irisee-master-plugin' ),
					$stock
				);
		} elseif ( $product->is_in_stock() ) {
			$card['stock_text'] = __( 'In stock', 'atelier-irisee-master-plugin' );
		}
		return $card;
	}

	/**
	 * Fabrics allowed for a size.
	 *
	 * @param int $variation_id Size variation.
	 * @param int $page         Page.
	 * @return array|null
	 */
	public static function get_fabrics( $variation_id, $page ) {
		$req = self::get_requirements( $variation_id );
		if ( ! $req ) {
			return null;
		}
		$result = self::query_products( $req['fabric_cats'], 'simple', $page );
		$items  = array();
		foreach ( $result['ids'] as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$items[] = self::material_card( $product, $req['fabric_units'], true );
			}
		}
		return array(
			'items' => $items,
			'page'  => $result['page'],
			'pages' => $result['pages'],
		);
	}

	/**
	 * Buttons or zips allowed for a size.
	 *
	 * @param int    $variation_id Size variation.
	 * @param string $type         'buttons' or 'zips'.
	 * @param int    $page         Page.
	 * @return array|null
	 */
	public static function get_notions( $variation_id, $type, $page ) {
		$req = self::get_requirements( $variation_id );
		if ( ! $req ) {
			return null;
		}
		$count = self::notion_count( $type, $req );
		$empty = array(
			'items' => array(),
			'page'  => 1,
			'pages' => 0,
		);
		if ( 0 === $count ) {
			return $empty;
		}

		$meta_query = array();
		if ( 'zips' === $type ) {
			if ( '' === $req['zip_length'] ) {
				return $empty;
			}
			$meta_query[] = array(
				'key'     => self::META_ZIP_LENGTH,
				'value'   => wc_format_decimal( $req['zip_length'], 2 ),
				'compare' => '=',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		$result = self::query_products( array( self::notion_category( $type ) ), 'simple', $page, $meta_query );
		$items  = array();
		foreach ( $result['ids'] as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$items[] = self::material_card( $product, $count, false );
			}
		}
		return array(
			'items' => $items,
			'page'  => $result['page'],
			'pages' => $result['pages'],
		);
	}
}
