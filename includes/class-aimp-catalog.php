<?php
/**
 * Catalog rules: which patterns, sizes, fabrics and haberdashery (buttons, zips, ribbons, bias tape) are eligible.
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

	const META_FABRIC_UNITS    = '_aimp_fabric_units';
	const META_FABRIC_CATS     = '_aimp_fabric_cats';
	const META_BUTTON_COUNT    = '_aimp_button_count';
	const META_ZIP_COUNT       = '_aimp_zip_count';
	const META_ZIP_LENGTH      = '_aimp_zip_length';
	const META_RIBBON_LENGTH   = '_aimp_ribbon_length';
	const META_BIAS_LENGTH     = '_aimp_bias_length';
	const META_BUST            = '_aimp_bust';
	const META_WAIST           = '_aimp_waist';
	const META_HEIGHT          = '_aimp_height';
	const META_HIP             = '_aimp_hip';
	const META_INSIDE_LEG      = '_aimp_inside_leg';
	const META_FABRIC_PRIORITY = '_aimp_fabric_priority';
	const META_INSPIRATION     = '_aimp_inspiration';
	const META_ORDER_INFO      = '_aimp_order_info';
	const META_SKILL           = '_aimp_skill';

	/** Number of inspiration cards on a fabric. */
	const INSPIRATION_CARDS = 3;

	/** Length of one fabric unit in cm. */
	const FABRIC_UNIT_CM = 10;

	/** Sort options for the fabric step. */
	const FABRIC_SORTS = array( 'recommended', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'newest' );

	/**
	 * Haberdashery types offered in step 3: type => [ role (cart line), setting (category), unit ].
	 * "piece" products are counted per piece; "10cm" products are sold per 10 cm, like fabric.
	 *
	 * @return array
	 */
	public static function notion_types() {
		return array(
			'buttons' => array(
				'role'    => 'button',
				'setting' => 'button_cat',
				'unit'    => 'piece',
			),
			'zips'    => array(
				'role'    => 'zip',
				'setting' => 'zip_cat',
				'unit'    => 'piece',
			),
			'ribbons' => array(
				'role'    => 'ribbon',
				'setting' => 'ribbon_cat',
				'unit'    => '10cm',
			),
			'bias'    => array(
				'role'    => 'bias',
				'setting' => 'bias_cat',
				'unit'    => '10cm',
			),
		);
	}

	/**
	 * Number of 10 cm units needed for a length in cm, rounded up (120 cm -> 12, 85 cm -> 9).
	 *
	 * @param int $cm Length in cm.
	 * @return int
	 */
	public static function units_for_cm( $cm ) {
		return (int) ceil( absint( $cm ) / self::FABRIC_UNIT_CM );
	}

	/**
	 * "120 cm (12 × 10 cm)".
	 *
	 * @param int $cm Length in cm.
	 * @return string
	 */
	public static function length_text( $cm ) {
		return sprintf(
			/* translators: 1: length in cm, 2: number of 10 cm units */
			__( '%1$d cm (%2$d × 10 cm)', 'atelier-irisee-master-plugin' ),
			absint( $cm ),
			self::units_for_cm( $cm )
		);
	}

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
	 * All subcategories of the Fabrics category (for the admin fields).
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

	/**
	 * Fabric category positions set on a pattern: term ID => position (1 = shown first).
	 *
	 * @param WC_Product $pattern Pattern product.
	 * @return int[]
	 */
	public static function get_fabric_priority( $pattern ) {
		$priority = $pattern->get_meta( self::META_FABRIC_PRIORITY );
		$clean    = array();
		if ( is_array( $priority ) ) {
			foreach ( $priority as $term_id => $position ) {
				if ( absint( $term_id ) && absint( $position ) ) {
					$clean[ absint( $term_id ) ] = absint( $position );
				}
			}
		}
		asort( $clean );
		return $clean;
	}

	/* ------------------------------------------------------------------
	 * Querying
	 * ------------------------------------------------------------------ */

	/**
	 * Tax query clause that hides products excluded from the catalog (and out-of-stock products
	 * when WooCommerce is set to hide them).
	 *
	 * @return array Empty when nothing has to be hidden.
	 */
	public static function visibility_tax_query() {
		$visibility = wc_get_product_visibility_term_ids();
		$hidden     = array( $visibility['exclude-from-catalog'] ?? 0 );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$hidden[] = $visibility['outofstock'] ?? 0;
		}
		$hidden = array_values( array_filter( $hidden ) );
		if ( ! $hidden ) {
			return array();
		}
		return array(
			'taxonomy' => 'product_visibility',
			'field'    => 'term_taxonomy_id',
			'terms'    => $hidden,
			'operator' => 'NOT IN',
		);
	}

	/**
	 * Whether a product is sold per 10 cm: fabric, ribbon and bias tape.
	 *
	 * @param WC_Product $product Product (or variation).
	 * @return bool
	 */
	public static function sold_per_10cm( $product ) {
		$terms = array();
		foreach ( array( 'fabric_cat', 'ribbon_cat', 'bias_cat' ) as $setting ) {
			$terms = array_merge( $terms, self::category_tree( AIMP_Settings::get( $setting ) ) );
		}
		return self::in_categories( $product, $terms );
	}

	/**
	 * Fabric text fields: group => [ subject => [ meta key, label, optional ] ].
	 * Inspiration and order information are free (rich) texts; specifications and washing are bullet lists.
	 *
	 * @return array
	 */
	public static function fabric_text_fields() {
		return array(
			'specs'   => array(
				'composition' => array( '_aimp_spec_composition', __( 'Fabric composition', 'atelier-irisee-master-plugin' ), false ),
				'type'        => array( '_aimp_spec_type', __( 'Fabric type', 'atelier-irisee-master-plugin' ), false ),
				'colour'      => array( '_aimp_spec_colour', __( 'Fabric colour', 'atelier-irisee-master-plugin' ), false ),
				'width'       => array( '_aimp_spec_width', __( 'Fabric width', 'atelier-irisee-master-plugin' ), false ),
				'weight'      => array( '_aimp_spec_weight', __( 'Fabric weight', 'atelier-irisee-master-plugin' ), false ),
			),
			'washing' => array(
				'washing' => array( '_aimp_wash_washing', __( 'Washing', 'atelier-irisee-master-plugin' ), false ),
				'drying'  => array( '_aimp_wash_drying', __( 'Drying', 'atelier-irisee-master-plugin' ), false ),
				'ironing' => array( '_aimp_wash_ironing', __( 'Ironing', 'atelier-irisee-master-plugin' ), false ),
				'tips'    => array( '_aimp_wash_tips', __( 'Tips', 'atelier-irisee-master-plugin' ), true ),
			),
		);
	}

	/**
	 * The texts of a fabric, ready to show.
	 *
	 * Specifications and washing list every subject ("–" when empty), except optional subjects (tips),
	 * which only appear when filled in. A list is empty when none of its subjects is filled in.
	 *
	 * @param WC_Product $product Fabric product.
	 * @return array [ inspiration_cards: [ [ title, text ] ], order_info, specs: [ subject => [ label, value ] ],
	 *                 washing: [ subject => [ label, value ] ] ]
	 */
	public static function fabric_texts( $product ) {
		$texts = array(
			'inspiration_cards' => self::inspiration_cards( $product ),
			'order_info'        => (string) $product->get_meta( self::META_ORDER_INFO ),
		);
		foreach ( self::fabric_text_fields() as $group => $fields ) {
			$list   = array();
			$filled = false;
			foreach ( $fields as $subject => $field ) {
				$value = trim( (string) $product->get_meta( $field[0] ) );
				if ( '' !== $value ) {
					$filled = true;
				} elseif ( $field[2] ) {
					continue;
				}
				$list[ $subject ] = array( $field[1], '' === $value ? '–' : $value );
			}
			$texts[ $group ] = $filled ? $list : array();
		}
		return $texts;
	}

	/**
	 * Skill levels of a pattern, from easy to hard: key => label.
	 *
	 * @return array
	 */
	public static function skill_levels() {
		return array(
			'beginner' => __( 'Beginner', 'atelier-irisee-master-plugin' ),
			'average'  => __( 'Average', 'atelier-irisee-master-plugin' ),
			'advanced' => __( 'Advanced', 'atelier-irisee-master-plugin' ),
			'expert'   => __( 'Expert', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * Meta keys of inspiration card $n (1-3).
	 *
	 * @param int $n Card number.
	 * @return array [ title key, text key ]
	 */
	public static function inspiration_keys( $n ) {
		return array( '_aimp_insp_' . $n . '_title', '_aimp_insp_' . $n . '_text' );
	}

	/**
	 * The filled-in inspiration cards. A text from before the cards existed (one free inspiration text)
	 * counts as the text of card 1 until that card is saved.
	 *
	 * @param WC_Product $product Fabric product.
	 * @param bool       $all     Also return empty cards (for the admin form).
	 * @return array[] [ title, text ]
	 */
	public static function inspiration_cards( $product, $all = false ) {
		$cards = array();
		for ( $n = 1; $n <= self::INSPIRATION_CARDS; $n++ ) {
			list( $title_key, $text_key ) = self::inspiration_keys( $n );
			$title = (string) $product->get_meta( $title_key );
			$text  = (string) $product->get_meta( $text_key );
			if ( 1 === $n && '' === $title && '' === trim( wp_strip_all_tags( $text ) ) ) {
				$text = (string) $product->get_meta( self::META_INSPIRATION );
			}
			if ( $all || '' !== trim( $title ) || '' !== trim( wp_strip_all_tags( $text ) ) ) {
				$cards[] = array(
					'title' => $title,
					'text'  => $text,
				);
			}
		}
		return $cards;
	}

	/**
	 * WP_Query arguments for published, visible products of a type in some categories.
	 *
	 * @param int[]  $term_ids   Category IDs (children are included automatically).
	 * @param string $type       Product type slug (variable, simple).
	 * @param array  $meta_query Optional extra meta query.
	 * @return array
	 */
	private static function base_query_args( $term_ids, $type, $meta_query = array() ) {
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

		$visibility = self::visibility_tax_query();
		if ( $visibility ) {
			$tax_query[] = $visibility;
		}

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'tax_query'           => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		return $args;
	}

	private static function empty_page() {
		return array(
			'items'    => array(),
			'page'     => 1,
			'pages'    => 0,
			'total'    => 0,
			'per_page' => 0,
		);
	}

	/**
	 * One page of product IDs in the given categories.
	 *
	 * @param int[]  $term_ids   Category IDs.
	 * @param string $type       Product type slug.
	 * @param int    $page       1-based page.
	 * @param int    $per_page   Items per page.
	 * @param array  $meta_query Optional extra meta query.
	 * @return array [ ids, page, pages, total ]
	 */
	private static function query_products( $term_ids, $type, $page, $per_page, $meta_query = array() ) {
		if ( empty( $term_ids ) ) {
			return array(
				'ids'   => array(),
				'page'  => 1,
				'pages' => 0,
				'total' => 0,
			);
		}
		$args                   = self::base_query_args( $term_ids, $type, $meta_query );
		$args['posts_per_page'] = max( 1, $per_page );
		$args['paged']          = max( 1, absint( $page ) );

		$query = new WP_Query( $args );
		return array(
			'ids'   => array_map( 'absint', $query->posts ),
			'page'  => max( 1, absint( $page ) ),
			'pages' => (int) $query->max_num_pages,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Basic card data for a product grid.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function card( $product ) {
		$image_id = $product->get_image_id();
		return array(
			'id'         => $product->get_id(),
			'name'       => wp_strip_all_tags( $product->get_name() ),
			'image'      => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			'image_alt'  => $image_id ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '',
			'price'      => (float) wc_get_price_to_display( $product ),
			'price_html' => $product->get_price_html(),
			'permalink'  => $product->get_permalink(),
		);
	}

	/**
	 * All pictures of a product: main image, gallery images, then any extra images.
	 *
	 * @param WC_Product $product   Product.
	 * @param int[]      $extra_ids Extra attachment IDs (e.g. variation images).
	 * @return array[] List of [ id, thumb, large, alt ].
	 */
	public static function images( $product, $extra_ids = array() ) {
		$ids    = array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids(), $extra_ids );
		$ids    = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$name   = wp_strip_all_tags( $product->get_name() );
		$images = array();
		foreach ( $ids as $id ) {
			$large = wp_get_attachment_image_url( $id, 'woocommerce_single' );
			if ( ! $large ) {
				continue;
			}
			$alt      = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$images[] = array(
				'id'    => $id,
				'thumb' => wp_get_attachment_image_url( $id, 'woocommerce_gallery_thumbnail' ),
				'large' => $large,
				'alt'   => '' !== $alt ? $alt : $name,
			);
		}
		if ( ! $images ) {
			$images[] = array(
				'id'    => 0,
				'thumb' => wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' ),
				'large' => wc_placeholder_img_src( 'woocommerce_single' ),
				'alt'   => $name,
			);
		}
		return $images;
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

		$result = self::query_products( $cats, 'variable', $page, AIMP_Settings::get( 'per_page' ) );
		$items  = array();
		foreach ( $result['ids'] as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$items[] = self::card( $product );
			}
		}
		return array(
			'items'    => $items,
			'page'     => $result['page'],
			'pages'    => $result['pages'],
			'total'    => $result['total'],
			'per_page' => AIMP_Settings::get( 'per_page' ),
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
			'ribbon_length' => absint( $variation->get_meta( self::META_RIBBON_LENGTH ) ),
			'bias_length'  => absint( $variation->get_meta( self::META_BIAS_LENGTH ) ),
			'ribbon_qty'   => self::units_for_cm( $variation->get_meta( self::META_RIBBON_LENGTH ) ),
			'bias_qty'     => self::units_for_cm( $variation->get_meta( self::META_BIAS_LENGTH ) ),
			'bust'         => (string) $variation->get_meta( self::META_BUST ),
			'waist'        => (string) $variation->get_meta( self::META_WAIST ),
			'height'       => (string) $variation->get_meta( self::META_HEIGHT ),
			'hip'          => (string) $variation->get_meta( self::META_HIP ),
			'inside_leg'   => (string) $variation->get_meta( self::META_INSIDE_LEG ),
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
	 * Pattern details (with all its pictures) and its sizes with measurements and requirements.
	 *
	 * @param int $pattern_id Pattern product ID.
	 * @return array|null
	 */
	public static function get_sizes( $pattern_id ) {
		$pattern = self::get_pattern( $pattern_id );
		if ( ! $pattern ) {
			return null;
		}

		$sizes            = array();
		$variation_images = array();
		foreach ( $pattern->get_children() as $child_id ) {
			$req = self::get_requirements( $child_id );
			if ( ! $req ) {
				continue;
			}
			$variation          = $req['variation'];
			$variation_images[] = absint( $variation->get_image_id() );
			$sizes[]            = array(
				'id'           => $variation->get_id(),
				'label'        => $req['size'],
				'image_id'     => absint( $variation->get_image_id() ),
				'price'        => (float) wc_get_price_to_display( $variation ),
				'price_html'   => $variation->get_price_html(),
				'available'    => $variation->is_purchasable() && $variation->is_in_stock(),
				'bust'         => $req['bust'],
				'waist'        => $req['waist'],
				'height'       => $req['height'],
				'hip'          => $req['hip'],
				'inside_leg'   => $req['inside_leg'],
				'fabric_units' => $req['fabric_units'],
				'fabric_text'  => self::fabric_text( $req['fabric_units'] ),
				'button_count' => $req['button_count'],
				'zip_count'    => $req['zip_count'],
				'zip_length'   => $req['zip_length'],
				'ribbon_qty'   => $req['ribbon_qty'],
				'ribbon_text'  => self::length_text( $req['ribbon_length'] ),
				'bias_qty'     => $req['bias_qty'],
				'bias_text'    => self::length_text( $req['bias_length'] ),
			);
		}

		$gallery = self::images( $pattern, $variation_images );

		// Which picture to show when a size is chosen (its own variation image, if it has one).
		$index_of = array();
		foreach ( $gallery as $index => $image ) {
			$index_of[ $image['id'] ] = $index;
		}
		foreach ( $sizes as &$size ) {
			$size['image_index'] = ( $size['image_id'] && isset( $index_of[ $size['image_id'] ] ) ) ? $index_of[ $size['image_id'] ] : -1;
			unset( $size['image_id'] );
		}
		unset( $size );

		$card                      = self::card( $pattern );
		$card['short_description'] = wp_kses_post( wpautop( $pattern->get_short_description() ) );
		$card['gallery']           = $gallery;

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
	 * The haberdashery product if it is allowed for these requirements.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type       A key of notion_types().
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
	 * Locked cart quantity of a haberdashery type for a size (pieces, or 10 cm units).
	 *
	 * @param string $type A key of notion_types().
	 * @param array  $req  Requirements.
	 * @return int
	 */
	public static function notion_count( $type, $req ) {
		switch ( $type ) {
			case 'buttons':
				return $req['button_count'];
			case 'zips':
				return $req['zip_count'];
			case 'ribbons':
				return $req['ribbon_qty'];
			case 'bias':
				return $req['bias_qty'];
		}
		return 0;
	}

	private static function notion_category( $type ) {
		$types = self::notion_types();
		return isset( $types[ $type ] ) ? AIMP_Settings::get( $types[ $type ]['setting'] ) : 0;
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
	 * @param WC_Product $product         Product.
	 * @param bool       $skip_variations Leave out attributes used for variations (the sizes of a pattern).
	 * @return array[]
	 */
	public static function attributes( $product, $skip_variations = false ) {
		$rows = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute->get_visible() || ( $skip_variations && $attribute->get_variation() ) ) {
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
	 * Whether a material can be bought in the required quantity.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $qty     Required quantity.
	 * @return bool
	 */
	private static function is_available( $product, $qty ) {
		return $product->is_purchasable() && $product->is_in_stock() && $product->has_enough_stock( $qty );
	}

	/**
	 * Card plus availability, pictures and detail info for a material that is needed $qty times.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $qty     Required quantity.
	 * @return array
	 */
	private static function material_card( $product, $qty ) {
		$card                = self::card( $product );
		$card['qty']         = $qty;
		$card['available']   = self::is_available( $product, $qty );
		$card['total_html']  = wc_price( $card['price'] * $qty );
		$card['description'] = wp_kses_post( wpautop( $product->get_short_description() ) );
		$card['attributes']  = self::attributes( $product );
		$card['gallery']     = self::images( $product );
		return $card;
	}

	/* ------------------------------------------------------------------
	 * Fabrics: filter, sort, paginate
	 * ------------------------------------------------------------------ */

	/**
	 * The allowed fabric categories of a size, as filter chips: the pattern's prioritised
	 * categories first (by position), then the others alphabetically.
	 *
	 * @param array $req      Requirements.
	 * @param int[] $priority Term ID => position.
	 * @return array[] List of [ id, name ].
	 */
	private static function fabric_filter_categories( $req, $priority ) {
		$list = array();
		foreach ( $req['fabric_cats'] as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$list[] = array(
					'id'       => (int) $term->term_id,
					'name'     => $term->name,
					'position' => isset( $priority[ $term->term_id ] ) ? $priority[ $term->term_id ] : PHP_INT_MAX,
				);
			}
		}
		usort(
			$list,
			function ( $a, $b ) {
				if ( $a['position'] !== $b['position'] ) {
					return $a['position'] < $b['position'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return array_map(
			function ( $item ) {
				unset( $item['position'] );
				return $item;
			},
			$list
		);
	}

	/**
	 * Reorder product IDs so fabrics from the pattern's prioritised categories come first.
	 * Products keep their existing order within the same position (stable sort).
	 *
	 * @param int[] $ids      Product IDs in their base order.
	 * @param int[] $priority Term ID => position.
	 * @return int[]
	 */
	private static function sort_by_category_priority( $ids, $priority ) {
		if ( ! $ids || ! $priority ) {
			return $ids;
		}

		// A product in a subcategory of a prioritised category gets that category's position.
		$term_position = array();
		foreach ( $priority as $term_id => $position ) {
			foreach ( self::category_tree( $term_id ) as $tree_term ) {
				if ( ! isset( $term_position[ $tree_term ] ) || $position < $term_position[ $tree_term ] ) {
					$term_position[ $tree_term ] = $position;
				}
			}
		}

		$product_position = array();
		$terms            = wp_get_object_terms( $ids, 'product_cat', array( 'fields' => 'all_with_object_id' ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( isset( $term_position[ $term->term_id ] ) ) {
					$object_id                      = (int) $term->object_id;
					$current                        = isset( $product_position[ $object_id ] ) ? $product_position[ $object_id ] : PHP_INT_MAX;
					$product_position[ $object_id ] = min( $current, $term_position[ $term->term_id ] );
				}
			}
		}

		$rows = array();
		foreach ( array_values( $ids ) as $index => $id ) {
			$rows[] = array( isset( $product_position[ $id ] ) ? $product_position[ $id ] : PHP_INT_MAX, $index, $id );
		}
		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a[0] !== $b[0] ) {
					return $a[0] < $b[0] ? -1 : 1;
				}
				return $a[1] - $b[1];
			}
		);
		return array_column( $rows, 2 );
	}

	/**
	 * Fabrics allowed for a size, filtered, sorted and paginated.
	 *
	 * @param int   $variation_id Size variation.
	 * @param array $args         [ page, category, search, in_stock, sort ].
	 * @return array|null
	 */
	public static function get_fabrics( $variation_id, $args ) {
		$req = self::get_requirements( $variation_id );
		if ( ! $req ) {
			return null;
		}

		$priority   = self::get_fabric_priority( $req['pattern'] );
		$categories = self::fabric_filter_categories( $req, $priority );
		$result     = self::empty_page();

		$result['categories'] = $categories;
		if ( ! $req['fabric_cats'] ) {
			return $result;
		}

		// Category filter: only one of the allowed categories (or a subcategory of one).
		$allowed_tree = array();
		foreach ( $req['fabric_cats'] as $cat ) {
			$allowed_tree = array_merge( $allowed_tree, self::category_tree( $cat ) );
		}
		$category = absint( $args['category'] );
		$cats     = ( $category && in_array( $category, $allowed_tree, true ) ) ? array( $category ) : $req['fabric_cats'];

		$query_args                   = self::base_query_args( $cats, 'simple' );
		$query_args['posts_per_page'] = -1;
		$query_args['no_found_rows']  = true;
		if ( '' !== $args['search'] ) {
			$query_args['s'] = $args['search'];
		}

		$sort = in_array( $args['sort'], self::FABRIC_SORTS, true ) ? $args['sort'] : 'recommended';
		switch ( $sort ) {
			case 'name_asc':
			case 'name_desc':
				$query_args['orderby'] = array( 'title' => 'name_asc' === $sort ? 'ASC' : 'DESC' );
				break;
			case 'price_asc':
			case 'price_desc':
				$query_args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = array(
					'meta_value_num' => 'price_asc' === $sort ? 'ASC' : 'DESC',
					'title'          => 'ASC',
				);
				break;
			case 'newest':
				$query_args['orderby'] = array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				);
				break;
		}

		$ids = array_map( 'absint', ( new WP_Query( $query_args ) )->posts );
		if ( 'recommended' === $sort ) {
			$ids = self::sort_by_category_priority( $ids, $priority );
		}

		if ( $args['in_stock'] ) {
			$ids = array_values(
				array_filter(
					$ids,
					function ( $id ) use ( $req ) {
						$product = wc_get_product( $id );
						return $product && self::is_available( $product, $req['fabric_units'] );
					}
				)
			);
		}

		$per_page = max( 1, AIMP_Settings::get( 'fabric_per_page' ) );
		$total    = count( $ids );
		$pages    = (int) ceil( $total / $per_page );
		$page     = min( max( 1, absint( $args['page'] ) ), max( 1, $pages ) );

		$items = array();
		foreach ( array_slice( $ids, ( $page - 1 ) * $per_page, $per_page ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$items[] = self::material_card( $product, $req['fabric_units'] );
			}
		}

		return array(
			'items'      => $items,
			'page'       => $page,
			'pages'      => $pages,
			'total'      => $total,
			'per_page'   => $per_page,
			'categories' => $categories,
		);
	}

	/**
	 * Haberdashery (buttons, zips, ribbons, bias tape) allowed for a size.
	 *
	 * @param int    $variation_id Size variation.
	 * @param string $type         A key of notion_types().
	 * @param int    $page         Page.
	 * @return array|null
	 */
	public static function get_notions( $variation_id, $type, $page ) {
		$types = self::notion_types();
		$req   = self::get_requirements( $variation_id );
		if ( ! $req || ! isset( $types[ $type ] ) ) {
			return null;
		}
		$count = self::notion_count( $type, $req );
		if ( 0 === $count ) {
			return self::empty_page();
		}

		$meta_query = array();
		if ( 'zips' === $type ) {
			if ( '' === $req['zip_length'] ) {
				return self::empty_page();
			}
			$meta_query[] = array(
				'key'     => self::META_ZIP_LENGTH,
				'value'   => wc_format_decimal( $req['zip_length'], 2 ),
				'compare' => '=',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		$result = self::query_products( array( self::notion_category( $type ) ), 'simple', $page, AIMP_Settings::get( 'per_page' ), $meta_query );
		$items  = array();
		foreach ( $result['ids'] as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$items[] = self::material_card( $product, $count );
			}
		}
		return array(
			'items'    => $items,
			'page'     => $result['page'],
			'pages'    => $result['pages'],
			'total'    => $result['total'],
			'per_page' => AIMP_Settings::get( 'per_page' ),
		);
	}
}
