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
	const META_SIZES_TEXT      = '_aimp_sizes_text';
	const META_PROJECT_TIME    = '_aimp_project_time';
	const META_DESIGNS         = '_aimp_designs';
	const META_FABRIC_CM       = '_aimp_fabric_cm';
	const META_RECOMMENDED     = '_aimp_recommended_pattern';

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
		$types = array();
		// Zips: one zip of the filled-in length; a type without its own category uses the Zips category.
		foreach ( array( 'zip_divisible', 'zip_invisible', 'zip_non_divisible', 'zip_double_divisible' ) as $key ) {
			$types[ $key ] = array(
				'role'     => $key,
				'setting'  => $key . '_cat',
				'fallback' => 'zip_cat',
				'unit'     => 'piece',
				'kind'     => 'zip',
				'material' => $key,
			);
		}
		// Buttons: the filled-in amount; a type without a category is shown as information only.
		$types['buttons']       = array(
			'role'     => 'button',
			'setting'  => 'button_cat',
			'fallback' => '',
			'unit'     => 'piece',
			'kind'     => 'count',
			'material' => 'buttons',
		);
		$types['snaps']         = array(
			'role'     => 'snap',
			'setting'  => 'snap_cat',
			'fallback' => '',
			'unit'     => 'piece',
			'kind'     => 'count',
			'material' => 'snaps',
		);
		$types['jeans_buttons'] = array(
			'role'     => 'jeans_button',
			'setting'  => 'jeans_button_cat',
			'fallback' => '',
			'unit'     => 'piece',
			'kind'     => 'count',
			'material' => 'jeans_buttons',
		);
		$types['bias']          = array(
			'role'     => 'bias',
			'setting'  => 'bias_cat',
			'fallback' => '',
			'unit'     => '10cm',
			'kind'     => 'length',
			'material' => 'bias',
		);
		$types['ribbons']       = array(
			'role'     => 'ribbon',
			'setting'  => 'ribbon_cat',
			'fallback' => '',
			'unit'     => '10cm',
			'kind'     => 'length',
			'material' => 'ribbon',
		);
		return $types;
	}

	/**
	 * The materials a pattern size can need, in the order of the editor and the pattern page:
	 * key => [ meta, label, unit (cm|piece), group label ].
	 *
	 * @return array
	 */
	public static function material_fields() {
		$zips    = __( 'Zips', 'atelier-irisee-master-plugin' );
		$buttons = __( 'Buttons', 'atelier-irisee-master-plugin' );
		$list    = array(
			'fabric'               => array( self::META_FABRIC_CM, __( 'Fabric', 'atelier-irisee-master-plugin' ), 'cm', __( 'Fabric', 'atelier-irisee-master-plugin' ) ),
			'zip_divisible'        => array( '_aimp_zip_divisible', __( 'Divisible zipper', 'atelier-irisee-master-plugin' ), 'cm', $zips ),
			'zip_invisible'        => array( '_aimp_zip_invisible', __( 'Invisible zipper', 'atelier-irisee-master-plugin' ), 'cm', $zips ),
			'zip_non_divisible'    => array( '_aimp_zip_non_divisible', __( 'Non divisible zipper', 'atelier-irisee-master-plugin' ), 'cm', $zips ),
			'zip_double_divisible' => array( '_aimp_zip_double_divisible', __( 'Double divisible zipper', 'atelier-irisee-master-plugin' ), 'cm', $zips ),
			'buttons'              => array( self::META_BUTTON_COUNT, __( 'Buttons', 'atelier-irisee-master-plugin' ), 'piece', $buttons ),
			'snaps'                => array( '_aimp_snap_count', __( 'Snap fasteners', 'atelier-irisee-master-plugin' ), 'piece', $buttons ),
			'jeans_buttons'        => array( '_aimp_jeans_button_count', __( 'Jeans buttons', 'atelier-irisee-master-plugin' ), 'piece', $buttons ),
			'elastic'              => array( '_aimp_elastic_cm', __( 'Elastic', 'atelier-irisee-master-plugin' ), 'cm', __( 'Elastic', 'atelier-irisee-master-plugin' ) ),
			'bias'                 => array( self::META_BIAS_LENGTH, __( 'Bias tape', 'atelier-irisee-master-plugin' ), 'cm', __( 'Bias tape', 'atelier-irisee-master-plugin' ) ),
			'ribbon'               => array( self::META_RIBBON_LENGTH, __( 'Ribbon', 'atelier-irisee-master-plugin' ), 'cm', __( 'Ribbon', 'atelier-irisee-master-plugin' ) ),
			'cord'                 => array( '_aimp_cord_cm', __( 'Cord', 'atelier-irisee-master-plugin' ), 'cm', __( 'Cord', 'atelier-irisee-master-plugin' ) ),
			'embroidery'           => array( '_aimp_embroidery_count', __( 'Embroidery', 'atelier-irisee-master-plugin' ), 'piece', __( 'Embroidery', 'atelier-irisee-master-plugin' ) ),
			'bag_strap'            => array( '_aimp_bag_strap_cm', __( 'Bag strap', 'atelier-irisee-master-plugin' ), 'cm', __( 'Bag strap', 'atelier-irisee-master-plugin' ) ),
			'interfacing'          => array( '_aimp_interfacing_cm', __( 'Fusible interfacing', 'atelier-irisee-master-plugin' ), 'cm', __( 'Fusible interfacing', 'atelier-irisee-master-plugin' ) ),
			'cord_lock'            => array( '_aimp_cord_lock_count', __( 'Cord lock', 'atelier-irisee-master-plugin' ), 'piece', __( 'Cord lock', 'atelier-irisee-master-plugin' ) ),
		);
		$fields = array();
		foreach ( $list as $key => $row ) {
			$fields[ $key ] = array(
				'meta'  => $row[0],
				'label' => $row[1],
				'unit'  => $row[2],
				'group' => $row[3],
			);
		}
		return $fields;
	}

	/**
	 * "40 cm" or "2".
	 *
	 * @param string $key    A key of material_fields().
	 * @param float  $amount Amount.
	 * @return string
	 */
	public static function material_text( $key, $amount ) {
		$fields = self::material_fields();
		$unit   = isset( $fields[ $key ] ) ? $fields[ $key ]['unit'] : 'piece';
		/* translators: %s: length in cm */
		return 'cm' === $unit ? sprintf( __( '%s cm', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $amount, floor( $amount ) == $amount ? 0 : 1 ) ) : (string) (int) $amount;
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

	/**
	 * The fitting fabric categories of a pattern, the same for all sizes (Atelier Irisee tab). Patterns saved
	 * before 3.2 kept them per size: then the size's own list counts, or with $size 0 all sizes together.
	 *
	 * @param int $pattern_id Pattern.
	 * @param int $size_id    A size (variation) for the fallback, or 0.
	 * @return int[] Term IDs.
	 */
	public static function pattern_fabric_cats( $pattern_id, $size_id = 0 ) {
		// Patterns with two designs: the fabrics of the size's design, or of all designs together.
		$designs = self::design_data( $pattern_id );
		if ( $designs ) {
			$slug = $size_id ? self::variation_design( $size_id ) : '';
			$cats = array();
			foreach ( $designs as $key => $design ) {
				if ( '' === $slug || $slug === $key ) {
					$cats = array_merge( $cats, $design['fabric_cats'] );
				}
			}
			if ( $cats || '' !== $slug ) {
				return array_values( array_unique( array_filter( array_map( 'absint', $cats ) ) ) );
			}
		}
		if ( metadata_exists( 'post', $pattern_id, self::META_FABRIC_CATS ) ) {
			$cats = get_post_meta( $pattern_id, self::META_FABRIC_CATS, true );
			return is_array( $cats ) ? array_values( array_filter( array_map( 'absint', $cats ) ) ) : array();
		}
		$sizes = $size_id ? array( $size_id ) : get_children(
			array(
				'post_parent' => $pattern_id,
				'post_type'   => 'product_variation',
				'fields'      => 'ids',
			)
		);
		$cats = array();
		foreach ( $sizes as $id ) {
			$own = get_post_meta( $id, self::META_FABRIC_CATS, true );
			if ( is_array( $own ) ) {
				$cats = array_merge( $cats, array_map( 'absint', $own ) );
			}
		}
		return array_values( array_unique( array_filter( $cats ) ) );
	}

	/**
	 * The body length (height) of a pattern, the same for all sizes; older patterns kept it per size.
	 *
	 * @param int $pattern_id Pattern.
	 * @param int $size_id    A size for the fallback, or 0 (then the first size that has one).
	 * @return string
	 */
	public static function pattern_height( $pattern_id, $size_id = 0 ) {
		$designs = $size_id ? self::design_data( $pattern_id ) : array();
		$slug    = $designs ? self::variation_design( $size_id ) : '';
		if ( '' !== $slug && isset( $designs[ $slug ] ) && '' !== $designs[ $slug ]['height'] ) {
			return $designs[ $slug ]['height'];
		}
		$height = (string) get_post_meta( $pattern_id, self::META_HEIGHT, true );
		if ( '' !== $height ) {
			return $height;
		}
		$sizes = $size_id ? array( $size_id ) : get_children(
			array(
				'post_parent' => $pattern_id,
				'post_type'   => 'product_variation',
				'fields'      => 'ids',
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			)
		);
		foreach ( $sizes as $id ) {
			$own = (string) get_post_meta( $id, self::META_HEIGHT, true );
			if ( '' !== $own ) {
				return $own;
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------
	 * Patterns with two (or more) designs in one pack
	 * ------------------------------------------------------------------ */

	/**
	 * The variation attribute that holds the designs (its name or label contains "design", "ontwerp",
	 * "model" or "modèle"), or ''. The key as in WC_Product::get_attributes(), e.g. "pa_design" or "design".
	 *
	 * @param WC_Product|int $pattern Pattern.
	 * @return string
	 */
	public static function design_attribute( $pattern ) {
		static $cache = array();
		$id = $pattern instanceof WC_Product ? $pattern->get_id() : absint( $pattern );
		if ( isset( $cache[ $id ] ) ) {
			return $cache[ $id ];
		}
		$pattern = $pattern instanceof WC_Product ? $pattern : wc_get_product( $pattern );
		$found   = '';
		if ( $pattern && $pattern->is_type( 'variable' ) ) {
			foreach ( $pattern->get_attributes() as $key => $attribute ) {
				if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_variation() ) {
					continue;
				}
				$label = wc_attribute_label( $attribute->get_name(), $pattern );
				if ( preg_match( '/design|ontwerp|mod[eè]le?/iu', $attribute->get_name() . ' ' . $label ) ) {
					$found = (string) $key;
					break;
				}
			}
		}
		$cache[ $id ] = (string) apply_filters( 'aimp_pattern_design_attribute', $found, $pattern );
		return $cache[ $id ];
	}

	/**
	 * The designs of a pattern: slug => name, in the attribute's order. Empty without a design attribute.
	 *
	 * @param WC_Product|int $pattern Pattern.
	 * @return array
	 */
	public static function designs( $pattern ) {
		$pattern = $pattern instanceof WC_Product ? $pattern : wc_get_product( $pattern );
		$key     = $pattern ? self::design_attribute( $pattern ) : '';
		$all     = $pattern ? $pattern->get_attributes() : array();
		if ( '' === $key || empty( $all[ $key ] ) ) {
			return array();
		}
		$attribute = $all[ $key ];
		$designs   = array();
		if ( $attribute->is_taxonomy() ) {
			foreach ( (array) $attribute->get_terms() as $term ) {
				$designs[ $term->slug ] = $term->name;
			}
		} else {
			foreach ( $attribute->get_options() as $option ) {
				$designs[ sanitize_title( $option ) ] = (string) $option;
			}
		}
		return $designs;
	}

	/**
	 * The design (slug) of a size, or ''.
	 *
	 * @param WC_Product_Variation|int $variation Size.
	 * @return string
	 */
	public static function variation_design( $variation ) {
		$variation = $variation instanceof WC_Product ? $variation : wc_get_product( $variation );
		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return '';
		}
		$key = self::design_attribute( $variation->get_parent_id() );
		if ( '' === $key ) {
			return '';
		}
		$attributes = $variation->get_variation_attributes();
		$value      = isset( $attributes[ 'attribute_' . $key ] ) ? (string) $attributes[ 'attribute_' . $key ] : '';
		return sanitize_title( $value );
	}

	/**
	 * Per-design settings of a pattern (Atelier Irisee tab): slug => [ image_ids, fabric_cats, height,
	 * project_time ], only for designs the pattern still has.
	 *
	 * @param int $pattern_id Pattern.
	 * @return array
	 */
	public static function design_data( $pattern_id ) {
		$designs = self::designs( $pattern_id );
		if ( ! $designs ) {
			return array();
		}
		$saved = get_post_meta( $pattern_id, self::META_DESIGNS, true );
		$saved = is_array( $saved ) ? $saved : array();
		$data  = array();
		foreach ( array_keys( $designs ) as $slug ) {
			$row           = isset( $saved[ $slug ] ) && is_array( $saved[ $slug ] ) ? $saved[ $slug ] : array();
			$data[ $slug ] = array(
				'image_ids'    => isset( $row['image_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $row['image_ids'] ) ) ) : array(),
				'fabric_cats'  => isset( $row['fabric_cats'] ) ? array_values( array_filter( array_map( 'absint', (array) $row['fabric_cats'] ) ) ) : array(),
				'height'       => isset( $row['height'] ) ? (string) $row['height'] : '',
				'project_time' => isset( $row['project_time'] ) ? (string) $row['project_time'] : '',
			);
		}
		return $data;
	}

	/**
	 * The first published size of each design: slug => variation ID.
	 *
	 * @param WC_Product $pattern Pattern.
	 * @return int[]
	 */
	public static function design_first_sizes( $pattern ) {
		$designs = self::designs( $pattern );
		$first   = array();
		if ( ! $designs ) {
			return $first;
		}
		foreach ( $pattern->get_children() as $child_id ) {
			if ( 'publish' !== get_post_status( $child_id ) ) {
				continue;
			}
			$slug = self::variation_design( $child_id );
			if ( isset( $designs[ $slug ] ) && ! isset( $first[ $slug ] ) ) {
				$first[ $slug ] = (int) $child_id;
			}
		}
		// In the order of the designs.
		return array_intersect_key( array_replace( array_fill_keys( array_keys( $designs ), 0 ), $first ), $first );
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
				// Optional: only shown when filled in.
				'certification' => array( '_aimp_spec_certification', __( 'Certification', 'atelier-irisee-master-plugin' ), true ),
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
	public static function base_query_args( $term_ids, $type, $meta_query = array() ) {
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
	 * Configurator card of one design of a pack: "Lison – Jurk", with the design's card picture.
	 *
	 * @param WC_Product $pattern      Pattern.
	 * @param string     $slug         Design.
	 * @param int        $variation_id The design's first size (the card's id).
	 * @return array
	 */
	public static function design_card( $pattern, $slug, $variation_id ) {
		$card    = self::card( $pattern );
		$designs = self::designs( $pattern );
		$data    = self::design_data( $pattern->get_id() );
		$image   = ! empty( $data[ $slug ]['image_ids'] ) ? (int) $data[ $slug ]['image_ids'][0] : 0;

		$card['id']         = (int) $variation_id;
		$card['product_id'] = $pattern->get_id();
		$card['design']     = $slug;
		$card['name']       = wp_strip_all_tags( $pattern->get_name() ) . ( isset( $designs[ $slug ] ) ? ' – ' . $designs[ $slug ] : '' );
		if ( $image && wp_get_attachment_image_url( $image, 'woocommerce_thumbnail' ) ) {
			$card['image']     = wp_get_attachment_image_url( $image, 'woocommerce_thumbnail' );
			$card['image_alt'] = (string) get_post_meta( $image, '_wp_attachment_image_alt', true );
		}
		return $card;
	}

	/**
	 * All pictures of a product: main image, gallery images, then any extra images.
	 *
	 * @param WC_Product $product   Product.
	 * @param int[]      $extra_ids Extra attachment IDs (e.g. variation images).
	 * @param int[]      $only_ids  Instead of the product's pictures: only these (a design's pictures).
	 * @return array[] List of [ id, thumb, large, alt ].
	 */
	public static function images( $product, $extra_ids = array(), $only_ids = array() ) {
		$own    = $only_ids ? (array) $only_ids : array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() );
		$ids    = array_merge( $own, $extra_ids );
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
	 * @param int[]|int $cat_id Filter categories (empty = all patterns).
	 * @param int $page   Page.
	 * @return array
	 */
	public static function get_patterns( $cat_id, $page ) {
		$root = AIMP_Settings::get( 'pattern_cat' );
		$tree = self::category_tree( $root );
		// One or more pattern categories (any of them); none = all patterns.
		$cats = array_values( array_intersect( array_map( 'absint', (array) $cat_id ), $tree ) );
		if ( ! $cats ) {
			$cats = $root ? array( $root ) : array();
		}

		$result = self::query_products( $cats, 'variable', $page, AIMP_Settings::get( 'per_page' ) );
		$items  = array();
		foreach ( $result['ids'] as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			// A pack with designs: one card per design (its id is the design's first size).
			$first = self::design_first_sizes( $product );
			if ( ! $first ) {
				$items[] = self::card( $product );
				continue;
			}
			foreach ( $first as $slug => $variation_id ) {
				$items[] = self::design_card( $product, $slug, $variation_id );
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

		// The same for all sizes: set on the pattern (older patterns: on the size).
		$fabric_cats = self::pattern_fabric_cats( $variation->get_parent_id(), $variation->get_id() );
		// Only subcategories that still belong to the Fabrics tree count.
		$fabric_cats = array_values( array_intersect( $fabric_cats, self::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) ) );

		// Every material of the size (amounts; 0 = not needed). Fabric in cm, with the old 10 cm units as fallback.
		$materials = array();
		foreach ( self::material_fields() as $key => $field ) {
			$materials[ $key ] = max( 0, (float) $variation->get_meta( $field['meta'] ) );
		}
		if ( $materials['fabric'] <= 0 ) {
			$materials['fabric'] = absint( $variation->get_meta( self::META_FABRIC_UNITS ) ) * self::FABRIC_UNIT_CM;
		}
		$fabric_units = $materials['fabric'] > 0 ? (int) ceil( $materials['fabric'] / self::FABRIC_UNIT_CM ) : 0;

		return array(
			'variation'    => $variation,
			'pattern'      => $pattern,
			'size'         => self::size_label( $variation ),
			'design'       => self::design_label( $variation ),
			'materials'    => $materials,
			'fabric_units' => $fabric_units,
			'fabric_cm'    => (float) $materials['fabric'],
			'fabric_cats'  => $fabric_cats,
			'button_count' => absint( $variation->get_meta( self::META_BUTTON_COUNT ) ),
			'ribbon_length' => absint( $variation->get_meta( self::META_RIBBON_LENGTH ) ),
			'bias_length'  => absint( $variation->get_meta( self::META_BIAS_LENGTH ) ),
			'ribbon_qty'   => self::units_for_cm( $variation->get_meta( self::META_RIBBON_LENGTH ) ),
			'bias_qty'     => self::units_for_cm( $variation->get_meta( self::META_BIAS_LENGTH ) ),
			'bust'         => (string) $variation->get_meta( self::META_BUST ),
			'waist'        => (string) $variation->get_meta( self::META_WAIST ),
			'height'       => self::pattern_height( $variation->get_parent_id(), $variation->get_id() ),
			'hip'          => (string) $variation->get_meta( self::META_HIP ),
			'inside_leg'   => (string) $variation->get_meta( self::META_INSIDE_LEG ),
		);
	}

	/**
	 * The design name of a size ("Jurk"), or '' for a pattern without designs.
	 *
	 * @param WC_Product_Variation $variation Size.
	 * @return string
	 */
	public static function design_label( $variation ) {
		$slug    = self::variation_design( $variation );
		$designs = '' !== $slug ? self::designs( $variation->get_parent_id() ) : array();
		return isset( $designs[ $slug ] ) ? (string) $designs[ $slug ] : '';
	}

	/**
	 * "Lison – Jurk – M" (or "Lison – M"): the pattern, its design and the size, for carts and kits.
	 *
	 * @param array $req From get_requirements().
	 * @return string
	 */
	public static function kit_label( $req ) {
		return implode( ' – ', array_filter( array( wp_strip_all_tags( $req['pattern']->get_name() ), $req['design'], $req['size'] ) ) );
	}

	/**
	 * Picture for a kit: the card picture of its design, or the pattern picture.
	 *
	 * @param array $req From get_requirements().
	 * @return int Attachment ID (0 = none).
	 */
	public static function kit_image_id( $req ) {
		$slug    = self::variation_design( $req['variation'] );
		$designs = '' !== $slug ? self::design_data( $req['pattern']->get_id() ) : array();
		if ( ! empty( $designs[ $slug ]['image_ids'] ) ) {
			return (int) $designs[ $slug ]['image_ids'][0];
		}
		return (int) $req['pattern']->get_image_id();
	}

	/**
	 * Human size label from the variation attributes, e.g. "M".
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return string
	 */
	public static function size_label( $variation ) {
		// A pattern with designs: the size only; the design is named apart (see design_label()).
		$skip = self::design_attribute( $variation->get_parent_id() );
		if ( '' !== $skip ) {
			$parts = array();
			foreach ( $variation->get_variation_attributes() as $name => $value ) {
				$key = substr( (string) $name, 10 ); // Without "attribute_".
				if ( $key === $skip || '' === (string) $value ) {
					continue;
				}
				$term    = taxonomy_exists( $key ) ? get_term_by( 'slug', $value, $key ) : null;
				$parts[] = $term ? $term->name : (string) $value;
			}
			if ( $parts ) {
				return wp_strip_all_tags( implode( ', ', $parts ) );
			}
		}
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
	 * A pack with designs shows one design at a time: pass a size (variation) of that design, e.g. the id of
	 * its configurator card. Only that design's sizes and pictures are returned.
	 *
	 * @param int $id Pattern product ID, or a size of it.
	 * @return array|null
	 */
	public static function get_sizes( $id ) {
		$object     = wc_get_product( absint( $id ) );
		$is_size    = $object && $object->is_type( 'variation' );
		$pattern    = self::get_pattern( $is_size ? $object->get_parent_id() : absint( $id ) );
		if ( ! $pattern ) {
			return null;
		}
		$first  = self::design_first_sizes( $pattern );
		$design = '';
		if ( $first ) {
			$design = $is_size ? self::variation_design( $object ) : '';
			if ( ! isset( $first[ $design ] ) ) {
				$design = (string) key( $first );
			}
		}

		$sizes            = array();
		$variation_images = array();
		foreach ( $pattern->get_children() as $child_id ) {
			if ( '' !== $design && self::variation_design( $child_id ) !== $design ) {
				continue;
			}
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
				'fabric_text'  => self::fabric_need_text( $req ),
				'notions'      => (object) self::size_notions( $req ),
				'extras'       => self::size_extras( $req ),
				'ribbon_qty'   => $req['ribbon_qty'],
				'ribbon_text'  => self::length_text( $req['ribbon_length'] ),
				'bias_qty'     => $req['bias_qty'],
				'bias_text'    => self::length_text( $req['bias_length'] ),
			);
		}

		$data    = '' !== $design ? self::design_data( $pattern->get_id() ) : array();
		$gallery = self::images( $pattern, $variation_images, ! empty( $data[ $design ]['image_ids'] ) ? $data[ $design ]['image_ids'] : array() );

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

		$card                      = '' !== $design ? self::design_card( $pattern, $design, $first[ $design ] ) : self::card( $pattern );
		$card['product_id']        = $pattern->get_id();
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
		if ( $product && '' !== self::zip_length( $type, $req ) && ! self::zip_length_matches( $product, self::zip_length( $type, $req ) ) ) {
			return null;
		}
		return $product;
	}

	/**
	 * The length a zip type needs ("22"), or '' for other types.
	 *
	 * @param string $type A key of notion_types().
	 * @param array  $req  Requirements.
	 * @return string
	 */
	public static function zip_length( $type, $req ) {
		$types = self::notion_types();
		if ( ! isset( $types[ $type ] ) || 'zip' !== $types[ $type ]['kind'] ) {
			return '';
		}
		$length = isset( $req['materials'][ $types[ $type ]['material'] ] ) ? (float) $req['materials'][ $types[ $type ]['material'] ] : 0;
		return $length > 0 ? wc_format_decimal( $length, 2, true ) : '';
	}

	/**
	 * What a size needs of a haberdashery type, whether or not it can be bought in the kit.
	 *
	 * @param string $type A key of notion_types().
	 * @param array  $req  Requirements.
	 * @return int Pieces, or 10 cm units.
	 */
	public static function notion_need( $type, $req ) {
		$types = self::notion_types();
		if ( ! isset( $types[ $type ] ) ) {
			return 0;
		}
		$amount = isset( $req['materials'][ $types[ $type ]['material'] ] ) ? (float) $req['materials'][ $types[ $type ]['material'] ] : 0;
		switch ( $types[ $type ]['kind'] ) {
			case 'zip':
				return $amount > 0 ? 1 : 0;
			case 'length':
				return self::units_for_cm( $amount );
		}
		return (int) $amount;
	}

	/**
	 * The kit haberdashery of a size for the configurator: type => [ qty, need, willAdd ].
	 *
	 * @param array $req Requirements.
	 * @return array
	 */
	public static function size_notions( $req ) {
		$types   = self::notion_types();
		$fields  = self::material_fields();
		$notions = array();
		foreach ( $types as $type => $info ) {
			$qty = self::notion_count( $type, $req );
			if ( $qty <= 0 ) {
				continue;
			}
			$amount = (float) $req['materials'][ $info['material'] ];
			if ( 'zip' === $info['kind'] ) {
				/* translators: 1: number of zips, 2: length in cm */
				$need = sprintf( __( '%1$d × zip of %2$s cm', 'atelier-irisee-master-plugin' ), 1, AIMP_I18n::number( $amount, floor( $amount ) == $amount ? 0 : 1 ) );
				/* translators: 1: number of zips, 2: length in cm */
				$will = sprintf( __( '%1$d zip(s) of %2$s cm will be added.', 'atelier-irisee-master-plugin' ), 1, AIMP_I18n::number( $amount, floor( $amount ) == $amount ? 0 : 1 ) );
			} elseif ( 'length' === $info['kind'] ) {
				$need = self::length_text( (int) $amount );
				/* translators: %s: length */
				$will = sprintf( __( '%s will be added.', 'atelier-irisee-master-plugin' ), $need );
			} else {
				$need = (string) $qty;
				/* translators: 1: amount, 2: material, e.g. "Snap fasteners" */
				$will = sprintf( __( '%1$d × %2$s will be added.', 'atelier-irisee-master-plugin' ), $qty, $fields[ $info['material'] ]['label'] );
			}
			$notions[ $type ] = array(
				'qty'     => $qty,
				'need'    => $need,
				'willAdd' => $will,
			);
		}
		return $notions;
	}

	/**
	 * Materials a size needs that are not in the kit (elastic, cord, … and button types without a category):
	 * [ [ label, text ] ], only the filled-in ones.
	 *
	 * @param array $req Requirements.
	 * @return array
	 */
	public static function size_extras( $req ) {
		$sold = array();
		foreach ( self::notion_types() as $type => $info ) {
			if ( self::notion_count( $type, $req ) > 0 ) {
				$sold[ $info['material'] ] = true;
			}
		}
		$extras = array();
		foreach ( self::material_fields() as $key => $field ) {
			if ( 'fabric' === $key || isset( $sold[ $key ] ) || empty( $req['materials'][ $key ] ) ) {
				continue;
			}
			$extras[] = array(
				'label' => $field['label'],
				'text'  => self::material_text( $key, (float) $req['materials'][ $key ] ),
			);
		}
		return $extras;
	}

	/**
	 * "145 cm (15 × 10 cm)": what a size needs of fabric.
	 *
	 * @param array $req Requirements.
	 * @return string
	 */
	public static function fabric_need_text( $req ) {
		$cm = (float) $req['fabric_cm'];
		if ( $cm <= 0 || ( $cm == $req['fabric_units'] * self::FABRIC_UNIT_CM ) ) {
			return self::fabric_text( $req['fabric_units'] );
		}
		/* translators: 1: length in cm, 2: units of 10 cm */
		return sprintf( __( '%1$s cm (%2$d × 10 cm)', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $cm, floor( $cm ) == $cm ? 0 : 1 ), $req['fabric_units'] );
	}

	/**
	 * Locked cart quantity of a haberdashery type for a size (pieces, or 10 cm units).
	 *
	 * @param string $type A key of notion_types().
	 * @param array  $req  Requirements.
	 * @return int
	 */
	public static function notion_count( $type, $req ) {
		// Only what can be bought: the type needs a product category.
		return self::notion_category( $type ) ? self::notion_need( $type, $req ) : 0;
	}

	/**
	 * The product category of a haberdashery type (its own, else its fallback), or 0.
	 *
	 * @param string $type A key of notion_types().
	 * @return int
	 */
	public static function notion_category( $type ) {
		$types = self::notion_types();
		if ( ! isset( $types[ $type ] ) ) {
			return 0;
		}
		$cat = (int) AIMP_Settings::get( $types[ $type ]['setting'] );
		if ( ! $cat && '' !== $types[ $type ]['fallback'] ) {
			$cat = (int) AIMP_Settings::get( $types[ $type ]['fallback'] );
		}
		return $cat;
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
	public static function material_card( $product, $qty ) {
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
	public static function sort_by_category_priority( $ids, $priority ) {
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

		// Category filter: one or more of the allowed categories (or their subcategories).
		$allowed_tree = array();
		foreach ( $req['fabric_cats'] as $cat ) {
			$allowed_tree = array_merge( $allowed_tree, self::category_tree( $cat ) );
		}
		$chosen = array_values( array_intersect( array_map( 'absint', (array) $args['category'] ), $allowed_tree ) );
		$cats   = $chosen ? $chosen : $req['fabric_cats'];

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
		$zip_length = self::zip_length( $type, $req );
		if ( 'zip' === $types[ $type ]['kind'] ) {
			if ( '' === $zip_length ) {
				return self::empty_page();
			}
			$meta_query[] = array(
				'key'     => self::META_ZIP_LENGTH,
				'value'   => wc_format_decimal( $zip_length, 2 ),
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
