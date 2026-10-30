<?php
/**
 * Haberdashery products: buttons, zips, interfacing, elastic, ribbons & cords and bias tape.
 *
 * One registry describes each kind: its category, its types, its specifications, how it is sold (per piece or
 * set, or per 10 cm or roll) and how patterns link to it (a switch per type, and a diameter or width range).
 * The product fields, the product page, the shop filters, the pattern rules and the configurator all read it.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Notions {

	const META_TYPE      = '_aimp_hab_type';
	const META_COLOUR    = '_aimp_hab_colour';
	const META_DIAMETER  = '_aimp_hab_diameter';
	const META_WIDTH     = '_aimp_hab_width';
	const META_LENGTH    = '_aimp_hab_length';
	const META_MATERIAL  = '_aimp_hab_material';
	const META_FINISH    = '_aimp_hab_finish';
	const META_MOUNTING  = '_aimp_hab_mounting';
	const META_SOLD      = '_aimp_hab_sold';
	const META_SET_SIZE  = '_aimp_hab_set_size';
	const META_IDEAL_FOR = '_aimp_hab_ideal_for';

	/** On a pattern: rule key => [ on, min, max, label, type, amount ] (see rules(); designs keep their own in the design
	 * data). 3.6 kept the button rules in META_BUTTON_RULES. */
	const META_RULES        = '_aimp_notion_rules';
	const META_BUTTON_RULES = '_aimp_button_rules';

	/** Number of "Ideal for" choices. */
	const IDEAL_FOR_MAX = 4;

	/** Own haberdashery types: at most this many, in these kinds (the kinds that have types). */
	const OWN_MAX   = 8;
	const OWN_KINDS = array( 'button', 'zip', 'ribbon', 'interfacing' );

	/** Version of the stored data (see migrate()). */
	const DB_VERSION = 2;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'migrate' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ), 20 );
	}

	/* ------------------------------------------------------------------
	 * Registry
	 * ------------------------------------------------------------------ */

	/**
	 * The kinds of haberdashery products: kind => [ setting, name, single, all, add, types, default_type, specs,
	 * measure (piece|length), main (main specification), range (field patterns limit, or ''), range_unit ].
	 *
	 * @return array
	 */
	public static function kinds() {
		static $kinds = array();
		$lang = AIMP_I18n::current();
		if ( isset( $kinds[ $lang ] ) ) {
			return $kinds[ $lang ];
		}
		$kinds[ $lang ] = array(
			'button'      => array(
				'setting'      => 'button_cat',
				// The category's name (not "Buttons": that text is also used for the number of buttons a size needs).
				'name'         => __( 'Buttons & fasteners', 'atelier-irisee-master-plugin' ),
				'single'       => __( 'Button', 'atelier-irisee-master-plugin' ),
				'all'          => __( 'All buttons & fasteners', 'atelier-irisee-master-plugin' ),
				'add'          => __( 'Add a button', 'atelier-irisee-master-plugin' ),
				'types'        => array(
					'button'       => __( 'Button', 'atelier-irisee-master-plugin' ),
					'jeans_button' => __( 'Jeans button', 'atelier-irisee-master-plugin' ),
					'press_stud'   => __( 'Press stud', 'atelier-irisee-master-plugin' ),
				),
				'default_type' => 'button',
				'specs'        => array( 'type', 'colour', 'diameter', 'material', 'finish', 'mounting' ),
				'measure'      => 'piece',
				'main'         => 'diameter',
				'range'        => 'diameter',
				'range_unit'   => 'mm',
			),
			'zip'         => array(
				'setting'      => 'zip_cat',
				'name'         => __( 'Zips', 'atelier-irisee-master-plugin' ),
				'single'       => __( 'Zip', 'atelier-irisee-master-plugin' ),
				'all'          => __( 'All zips', 'atelier-irisee-master-plugin' ),
				'add'          => __( 'Add a zip', 'atelier-irisee-master-plugin' ),
				'types'        => array(
					'zip_divisible'        => __( 'Divisible zipper', 'atelier-irisee-master-plugin' ),
					'zip_invisible'        => __( 'Invisible zipper', 'atelier-irisee-master-plugin' ),
					'zip_non_divisible'    => __( 'Non divisible zipper', 'atelier-irisee-master-plugin' ),
					'zip_double_divisible' => __( 'Double divisible zipper', 'atelier-irisee-master-plugin' ),
				),
				// A zip saved without a type fits every zip type (as before 3.7).
				'default_type' => '',
				'specs'        => array( 'type', 'length', 'colour' ),
				'measure'      => 'piece',
				'main'         => 'length',
				'range'        => '',
				'range_unit'   => '',
			),
			'interfacing' => array(
				'setting'      => 'interfacing_cat',
				'name'         => __( 'Interfacing', 'atelier-irisee-master-plugin' ),
				'single'       => __( 'Interfacing', 'atelier-irisee-master-plugin' ),
				'all'          => __( 'All interfacing', 'atelier-irisee-master-plugin' ),
				'add'          => __( 'Add interfacing', 'atelier-irisee-master-plugin' ),
				'types'        => array(
					'interfacing'  => __( 'Interfacing fabric', 'atelier-irisee-master-plugin' ),
					'shoulder_pad' => __( 'Shoulder pads', 'atelier-irisee-master-plugin' ),
				),
				// Interfacing saved before 3.20 had no type: it is interfacing fabric.
				'default_type' => 'interfacing',
				'specs'        => array( 'type', 'width' ),
				'measure'      => 'length',
				// Types sold another way than the kind: shoulder pads per pair (piece).
				'type_measure' => array( 'shoulder_pad' => 'piece' ),
				'main'         => 'width',
				'range'        => 'width',
				'range_unit'   => 'cm',
				// Interfacing fabric is only sold per 10 cm, never per roll.
				'rolls'        => false,
			),
			'elastic'     => array(
				'setting'      => 'elastic_cat',
				'name'         => __( 'Elastic', 'atelier-irisee-master-plugin' ),
				'single'       => __( 'Elastic', 'atelier-irisee-master-plugin' ),
				'all'          => __( 'All elastic', 'atelier-irisee-master-plugin' ),
				'add'          => __( 'Add elastic', 'atelier-irisee-master-plugin' ),
				'types'        => array(),
				'default_type' => '',
				'specs'        => array( 'colour', 'length', 'width' ),
				'measure'      => 'length',
				'main'         => 'width',
				'range'        => 'width',
				'range_unit'   => 'mm',
			),
			'ribbon'      => array(
				'setting'      => 'ribbon_cat',
				'name'         => __( 'Ribbons & cords', 'atelier-irisee-master-plugin' ),
				'single'       => __( 'Ribbon or cord', 'atelier-irisee-master-plugin' ),
				'all'          => __( 'All ribbons & cords', 'atelier-irisee-master-plugin' ),
				'add'          => __( 'Add a ribbon or cord', 'atelier-irisee-master-plugin' ),
				'types'        => array(
					'ribbon' => __( 'Ribbon', 'atelier-irisee-master-plugin' ),
					'cord'   => __( 'Cord', 'atelier-irisee-master-plugin' ),
				),
				'default_type' => 'ribbon',
				'specs'        => array( 'type', 'length', 'width', 'colour' ),
				'measure'      => 'length',
				'main'         => 'width',
				// No width filter: every ribbon or cord fits.
				'range'        => '',
				'range_unit'   => '',
			),
			'bias'        => array(
				'setting'      => 'bias_cat',
				'name'         => __( 'Bias tape', 'atelier-irisee-master-plugin' ),
				'single'       => __( 'Bias tape', 'atelier-irisee-master-plugin' ),
				'all'          => __( 'All bias tape', 'atelier-irisee-master-plugin' ),
				'add'          => __( 'Add bias tape', 'atelier-irisee-master-plugin' ),
				'types'        => array(),
				'default_type' => '',
				'specs'        => array( 'colour', 'width', 'length' ),
				'measure'      => 'length',
				'main'         => 'width',
				'range'        => 'width',
				'range_unit'   => 'mm',
			),
			// Sewing thread: per spool (the dog bandana in the customizer offers it).
			'thread'      => array(
				'setting'      => 'thread_cat',
				'name'         => __( 'Sewing thread', 'atelier-irisee-master-plugin' ),
				'single'       => __( 'Sewing thread', 'atelier-irisee-master-plugin' ),
				'all'          => __( 'All sewing thread', 'atelier-irisee-master-plugin' ),
				'add'          => __( 'Add sewing thread', 'atelier-irisee-master-plugin' ),
				'types'        => array(),
				'default_type' => '',
				'specs'        => array( 'colour', 'material' ),
				'measure'      => 'piece',
				'main'         => 'colour',
				'range'        => '',
				'range_unit'   => '',
			),
		);
		// Own types (Settings → Haberdashery options): a type of their kind, sold like it unless chosen otherwise.
		foreach ( self::own_types() as $key => $own ) {
			$kinds[ $lang ][ $own['kind'] ]['types'][ $key ] = AIMP_Settings::localized( $own['name'] );
			if ( '' !== $own['measure'] && $own['measure'] !== $kinds[ $lang ][ $own['kind'] ]['measure'] ) {
				$kinds[ $lang ][ $own['kind'] ]['type_measure'][ $key ] = $own['measure'];
			}
		}
		return $kinds[ $lang ];
	}

	/**
	 * Own haberdashery types: key => [ kind, name (NL | FR | EN), measure (piece|length|'' = as the kind) ]. The keys
	 * ("own_…") never change, so patterns, saved kits and cart lines keep them when a name changes.
	 *
	 * @return array
	 */
	public static function own_types() {
		$options = (array) get_option( AIMP_Settings::OPTION, array() );
		$rows    = isset( $options['own_notion_types'] ) && is_array( $options['own_notion_types'] ) ? $options['own_notion_types'] : array();
		$own     = array();
		foreach ( $rows as $key => $row ) {
			if ( preg_match( '/^own_[a-z0-9_]+$/', (string) $key ) && is_array( $row ) && isset( $row['kind'], $row['name'] ) && in_array( $row['kind'], self::OWN_KINDS, true ) && '' !== trim( (string) $row['name'] ) ) {
				$own[ $key ] = array(
					'kind'    => $row['kind'],
					'name'    => (string) $row['name'],
					// Zips are always one zip of the length the size needs.
					'measure' => 'zip' !== $row['kind'] && isset( $row['measure'] ) && in_array( $row['measure'], array( 'piece', 'length' ), true ) ? $row['measure'] : '',
				);
			}
		}
		return $own;
	}

	/**
	 * A kind, or null.
	 *
	 * @param string $kind Kind.
	 * @return array|null
	 */
	public static function kind( $kind ) {
		$kinds = self::kinds();
		return isset( $kinds[ $kind ] ) ? $kinds[ $kind ] : null;
	}

	/**
	 * Specification fields: field => [ meta, label ]. The unit of a width depends on the kind (unit()).
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'type'     => array( self::META_TYPE, __( 'Type', 'atelier-irisee-master-plugin' ) ),
			'colour'   => array( self::META_COLOUR, __( 'Colour', 'atelier-irisee-master-plugin' ) ),
			'diameter' => array( self::META_DIAMETER, __( 'Diameter', 'atelier-irisee-master-plugin' ) ),
			'width'    => array( self::META_WIDTH, __( 'Width', 'atelier-irisee-master-plugin' ) ),
			'length'   => array( self::META_LENGTH, __( 'Length', 'atelier-irisee-master-plugin' ) ),
			'material' => array( self::META_MATERIAL, __( 'Material', 'atelier-irisee-master-plugin' ) ),
			'finish'   => array( self::META_FINISH, __( 'Surface finish', 'atelier-irisee-master-plugin' ) ),
			'mounting' => array( self::META_MOUNTING, __( 'Mounting method', 'atelier-irisee-master-plugin' ) ),
		);
	}

	/**
	 * The unit of a measured field for a kind: diameter mm, length cm, width mm (interfacing cm); '' for text.
	 *
	 * @param string $kind  Kind.
	 * @param string $field Field.
	 * @return string
	 */
	public static function unit( $kind, $field ) {
		switch ( $field ) {
			case 'diameter':
				return 'mm';
			case 'length':
				return 'cm';
			case 'width':
				return 'interfacing' === $kind ? 'cm' : 'mm';
		}
		return '';
	}

	/**
	 * The pattern rules, in the order of the pattern's Haberdashery block: rule => [ kind, type, material ]
	 * (material: a key of AIMP_Catalog::material_fields(), the amount a size needs).
	 *
	 * @return array
	 */
	public static function rules() {
		$rules = array(
			'button'               => array( 'button', 'button', 'buttons' ),
			'jeans_button'         => array( 'button', 'jeans_button', 'jeans_buttons' ),
			'press_stud'           => array( 'button', 'press_stud', 'snaps' ),
			// Extra button groups: their button type, label and diameter are set per pattern (or design).
			'button_2'             => array( 'button', 'button', 'buttons_2' ),
			'button_3'             => array( 'button', 'button', 'buttons_3' ),
			'zip_divisible'        => array( 'zip', 'zip_divisible', 'zip_divisible' ),
			'zip_invisible'        => array( 'zip', 'zip_invisible', 'zip_invisible' ),
			'zip_non_divisible'    => array( 'zip', 'zip_non_divisible', 'zip_non_divisible' ),
			'zip_double_divisible' => array( 'zip', 'zip_double_divisible', 'zip_double_divisible' ),
			'elastic'              => array( 'elastic', '', 'elastic' ),
			'bias'                 => array( 'bias', '', 'bias' ),
			'ribbon'               => array( 'ribbon', 'ribbon', 'ribbon' ),
			'cord'                 => array( 'ribbon', 'cord', 'cord' ),
			'interfacing'          => array( 'interfacing', 'interfacing', 'interfacing' ),
			'shoulder_pad'         => array( 'interfacing', 'shoulder_pad', 'shoulder_pads' ),
		);
		// Own types: their key is the rule, the type and the material.
		foreach ( self::own_types() as $key => $own ) {
			$rules[ $key ] = array( $own['kind'], $key, $key );
		}
		return $rules;
	}

	/**
	 * The rule of a material key ("buttons" → "button"), or ''.
	 *
	 * @param string $material Material key.
	 * @return string
	 */
	public static function rule_for_material( $material ) {
		foreach ( self::rules() as $rule => $info ) {
			if ( $info[2] === $material ) {
				return $rule;
			}
		}
		return '';
	}

	/**
	 * Clothes a product can be ideal for: key => label (icons: AIMP_Product_Page::clothing_icon()).
	 *
	 * @return array
	 */
	public static function ideal_for_options() {
		return array(
			'shirt'    => __( 'Shirt', 'atelier-irisee-master-plugin' ),
			'blouse'   => __( 'Blouse', 'atelier-irisee-master-plugin' ),
			'dress'    => __( 'Dress', 'atelier-irisee-master-plugin' ),
			'skirt'    => __( 'Skirt', 'atelier-irisee-master-plugin' ),
			'trousers' => __( 'Trousers', 'atelier-irisee-master-plugin' ),
			'jeans'    => __( 'Jeans', 'atelier-irisee-master-plugin' ),
			'jacket'   => __( 'Jacket', 'atelier-irisee-master-plugin' ),
			'coat'     => __( 'Coat', 'atelier-irisee-master-plugin' ),
			'cardigan' => __( 'Cardigan', 'atelier-irisee-master-plugin' ),
			'knitwear' => __( 'Knitwear', 'atelier-irisee-master-plugin' ),
			'kids'     => __( 'Children\'s clothing', 'atelier-irisee-master-plugin' ),
			'pyjamas'  => __( 'Pyjamas', 'atelier-irisee-master-plugin' ),
			'workwear' => __( 'Workwear', 'atelier-irisee-master-plugin' ),
			'bag'      => __( 'Bag', 'atelier-irisee-master-plugin' ),
			'home'     => __( 'Home textiles', 'atelier-irisee-master-plugin' ),
		);
	}

	/* ------------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------------ */

	/**
	 * The kind of a product (a simple product in a kind's category tree), or ''.
	 *
	 * @param WC_Product|int|null $product Product.
	 * @return string
	 */
	public static function kind_of( $product ) {
		$product = $product instanceof WC_Product ? $product : ( $product ? wc_get_product( $product ) : null );
		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return '';
		}
		$cats = wc_get_product_cat_ids( $product->get_id() );
		foreach ( self::kinds() as $kind => $info ) {
			$root = (int) AIMP_Settings::get( $info['setting'] );
			if ( $root && array_intersect( $cats, AIMP_Catalog::category_tree( $root ) ) ) {
				return $kind;
			}
		}
		return '';
	}

	/**
	 * The type of a product; without a saved type the kind's default ('' for zips: fits every zip type).
	 *
	 * @param WC_Product $product Product.
	 * @param string     $kind    Its kind (optional).
	 * @return string
	 */
	public static function type_of( $product, $kind = '' ) {
		$kind = '' !== $kind ? $kind : self::kind_of( $product );
		$info = self::kind( $kind );
		if ( ! $info ) {
			return '';
		}
		// Interfacing: the subcategory (Tussenvoering, Schoudervulling, Keperband, …) decides, before the saved type.
		if ( 'interfacing' === $kind ) {
			$cats = wc_get_product_cat_ids( $product->get_id() );
			foreach ( self::type_categories( $kind ) as $type => $cat ) {
				if ( array_intersect( $cats, AIMP_Catalog::category_tree( $cat ) ) ) {
					return $type;
				}
			}
		}
		$type = (string) $product->get_meta( self::META_TYPE );
		return isset( $info['types'][ $type ] ) ? $type : $info['default_type'];
	}

	/**
	 * The subcategories of a kind's category that belong to one of its types, matched by name in any language
	 * ("Schoudervulling" ↔ "Schoudervullingen", "Keperband" ↔ own type "Keperband"): type => category ID. Only
	 * interfacing (Verstevigingen) works this way.
	 *
	 * @param string $kind Kind.
	 * @return array
	 */
	public static function type_categories( $kind ) {
		static $cache = array();
		if ( isset( $cache[ $kind ] ) ) {
			return $cache[ $kind ];
		}
		$cache[ $kind ] = array();
		$info           = self::kind( $kind );
		$root           = $info && 'interfacing' === $kind ? (int) AIMP_Settings::get( $info['setting'] ) : 0;
		if ( ! $root ) {
			return $cache[ $kind ];
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $root,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return $cache[ $kind ];
		}
		$plain = function ( $text ) {
			return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( wp_specialchars_decode( (string) $text, ENT_QUOTES ) ) ) );
		};
		$same  = function ( $a, $b ) {
			return '' !== $a && '' !== $b && ( $a === $b || ( min( strlen( $a ), strlen( $b ) ) >= 5 && ( 0 === strpos( $a, $b ) || 0 === strpos( $b, $a ) ) ) );
		};
		// Every type's name in every language.
		$names = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $lang ) {
			AIMP_I18n::with_language(
				$lang,
				function () use ( $kind, &$names, $plain ) {
					foreach ( self::kinds()[ $kind ]['types'] as $type => $name ) {
						$names[ $type ][] = $plain( $name );
					}
				}
			);
		}
		foreach ( $names as $type => $list ) {
			foreach ( $terms as $term ) {
				foreach ( array_unique( $list ) as $name ) {
					if ( $same( $name, $plain( $term->name ) ) || $same( $name, $plain( $term->slug ) ) ) {
						$cache[ $kind ][ $type ] = (int) $term->term_id;
						continue 3;
					}
				}
			}
		}
		return $cache[ $kind ];
	}

	/**
	 * A specification as a number (diameter, width, length), 0 when empty.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $field   Field.
	 * @return float
	 */
	public static function number( $product, $field ) {
		$fields = self::fields();
		return isset( $fields[ $field ] ) ? max( 0, (float) $product->get_meta( $fields[ $field ][0] ) ) : 0;
	}

	/**
	 * How the product is sold: piece, set, 10cm or roll.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function sold( $product ) {
		$info = $product instanceof WC_Product ? self::kind( self::kind_of( $product ) ) : null;
		if ( ! $info ) {
			return '';
		}
		$sold = (string) $product->get_meta( self::META_SOLD );
		if ( 'piece' === self::measure( self::kind_of( $product ), self::type_of( $product ) ) ) {
			$size = absint( $product->get_meta( self::META_SET_SIZE ) );
			return ( 'set' === $sold || '' === $sold ) && $size > 1 ? 'set' : 'piece';
		}
		return 'roll' === $sold && self::has_rolls( self::kind_of( $product ) ) && self::number( $product, 'length' ) > 0 ? 'roll' : '10cm';
	}

	/**
	 * Whether products of a kind sold by length can be sold per roll (not interfacing).
	 *
	 * @param string $kind Kind.
	 * @return bool
	 */
	public static function has_rolls( $kind ) {
		$info = self::kind( $kind );
		return $info && ( ! isset( $info['rolls'] ) || $info['rolls'] );
	}

	/**
	 * How a kind (and type) is counted: piece or length. Shoulder pads (an interfacing type) are sold per pair.
	 *
	 * @param string $kind Kind.
	 * @param string $type Type, or ''.
	 * @return string
	 */
	public static function measure( $kind, $type = '' ) {
		$info = self::kind( $kind );
		if ( ! $info ) {
			return '';
		}
		return ( '' !== $type && isset( $info['type_measure'][ $type ] ) ) ? $info['type_measure'][ $type ] : $info['measure'];
	}

	/**
	 * Whether a product is counted in pairs (shoulder pads).
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function per_pair( $product ) {
		return 'interfacing' === self::kind_of( $product ) && 'shoulder_pad' === self::type_of( $product );
	}

	/**
	 * Products in a set: the set size, else 1.
	 *
	 * @param WC_Product $product Product.
	 * @return int
	 */
	public static function set_size( $product ) {
		return 'set' === self::sold( $product ) ? max( 2, absint( $product->get_meta( self::META_SET_SIZE ) ) ) : 1;
	}

	/**
	 * The length of one roll in cm (0 when not sold per roll).
	 *
	 * @param WC_Product $product Product.
	 * @return float
	 */
	public static function roll_length( $product ) {
		return 'roll' === self::sold( $product ) ? self::number( $product, 'length' ) : 0;
	}

	/**
	 * Whether a haberdashery product is sold per 10 cm.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function sold_per_10cm( $product ) {
		return '10cm' === self::sold( $product );
	}

	/**
	 * The cart quantity for a need: pieces (sets: whole sets) or cm (10 cm units, or whole rolls).
	 * Products that are not haberdashery (fabric) keep the need as it is.
	 *
	 * @param WC_Product $product Product.
	 * @param float      $need    Pieces, or cm for products sold by length.
	 * @return int
	 */
	public static function pack_qty( $product, $need ) {
		$need = max( 0, (float) $need );
		switch ( self::sold( $product ) ) {
			case 'set':
				return (int) ceil( $need / self::set_size( $product ) );
			case 'roll':
				return (int) ceil( $need / self::roll_length( $product ) - 0.0001 );
			case '10cm':
				return (int) ceil( $need / AIMP_Catalog::FABRIC_UNIT_CM - 0.0001 );
		}
		return (int) ceil( $need );
	}

	/**
	 * "3 m", "1,5 m" or "80 cm".
	 *
	 * @param float $cm Centimetres.
	 * @return string
	 */
	public static function length_text( $cm ) {
		$cm = (float) $cm;
		if ( $cm >= 100 ) {
			$m = $cm / 100;
			/* translators: %s: length in metres */
			return sprintf( __( '%s m', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $m, floor( $m ) == $m ? 0 : ( floor( $m * 10 ) == $m * 10 ? 1 : 2 ) ) );
		}
		/* translators: %s: length in cm */
		return sprintf( __( '%s cm', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $cm, floor( $cm ) == $cm ? 0 : 1 ) );
	}

	/**
	 * "15 mm", "90 cm": a number with its unit.
	 *
	 * @param float  $value Value.
	 * @param string $unit  mm or cm.
	 * @return string
	 */
	public static function measure_text( $value, $unit ) {
		$value = (float) $value;
		$num   = AIMP_I18n::number( $value, floor( $value ) == $value ? 0 : 1 );
		/* translators: %s: number of millimetres or centimetres */
		return 'cm' === $unit ? sprintf( __( '%s cm', 'atelier-irisee-master-plugin' ), $num ) : sprintf( __( '%s mm', 'atelier-irisee-master-plugin' ), $num );
	}

	/**
	 * A specification of a product as text ("15 mm", "Divisible zipper", "ochre"), '' when empty.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $field   Field.
	 * @param string     $kind    Its kind.
	 * @return string
	 */
	public static function field_text( $product, $field, $kind ) {
		$info = self::kind( $kind );
		if ( 'type' === $field ) {
			$type = self::type_of( $product, $kind );
			return isset( $info['types'][ $type ] ) ? $info['types'][ $type ] : '';
		}
		if ( in_array( $field, array( 'diameter', 'width', 'length' ), true ) ) {
			$value = self::number( $product, $field );
			if ( $value <= 0 ) {
				return '';
			}
			return 'length' === $field ? self::length_text( $value ) : self::measure_text( $value, self::unit( $kind, $field ) );
		}
		$fields = self::fields();
		$value  = trim( (string) $product->get_meta( $fields[ $field ][0] ) );
		return in_array( $field, array( 'material', 'finish' ), true ) ? self::option_label( $field, $value ) : $value;
	}

	/* ------------------------------------------------------------------
	 * Button materials and surface finishes: lists from the settings (Atelier Irisee → Haberdashery options)
	 * ------------------------------------------------------------------ */

	/**
	 * The standard list of a field, one option per line as "NL | FR | EN".
	 *
	 * @param string $field material or finish.
	 * @return string
	 */
	public static function default_options( $field ) {
		$lists = array(
			'material' => array( 'Hout | Bois | Wood', 'Parelmoer | Nacre | Mother-of-pearl', 'Metaal | Métal | Metal', 'Kunststof | Plastique | Plastic', 'Kokosnoot | Noix de coco | Coconut', 'Hoorn | Corne | Horn', 'Corozo | Corozo | Corozo', 'Leder | Cuir | Leather' ),
			'finish'   => array( 'Glanzend | Brillant | Glossy', 'Mat | Mat | Matt', 'Satijn | Satiné | Satin', 'Geborsteld | Brossé | Brushed', 'Gepolijst | Poli | Polished', 'Natuurlijk | Naturel | Natural' ),
		);
		return isset( $lists[ $field ] ) ? implode( "\n", $lists[ $field ] ) : '';
	}

	/**
	 * The options of a field: [ [ NL, FR, EN ], … ]. The first part (NL) is what a product stores.
	 *
	 * @param string $field material or finish.
	 * @return array
	 */
	public static function options( $field ) {
		$key  = 'material' === $field ? 'button_materials' : 'button_finishes';
		$text = AIMP_Settings::get_text( $key, self::default_options( $field ) );
		$list = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( '' !== $parts[0] ) {
				$list[] = $parts;
			}
		}
		return $list;
	}

	/**
	 * A stored material or finish in the visitor's language (as stored when it is not in the list).
	 *
	 * @param string $field material or finish.
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function option_label( $field, $value ) {
		if ( '' === $value ) {
			return '';
		}
		foreach ( self::options( $field ) as $parts ) {
			if ( 0 === strcasecmp( $parts[0], $value ) ) {
				return AIMP_Settings::localized( implode( ' | ', $parts ) );
			}
		}
		return $value;
	}

	public static function register_settings() {
		add_settings_section(
			'aimp_notion_options',
			__( 'Haberdashery options', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_options' ),
			AIMP_Settings::PAGE
		);
	}

	public static function render_options() {
		echo '<p>' . esc_html__( 'The choices for buttons in the product editor (Haberdashery details). One option per line, written as Dutch | French | English, for example "Hout | Bois | Wood". The Dutch name is what a product keeps: changing it later does not change products that already have it.', 'atelier-irisee-master-plugin' ) . '</p>';
		$fields = array(
			'button_materials' => array( 'material', __( 'Button materials', 'atelier-irisee-master-plugin' ) ),
			'button_finishes'  => array( 'finish', __( 'Surface finishes', 'atelier-irisee-master-plugin' ) ),
		);
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $key => $field ) {
			printf(
				'<tr><th scope="row"><label for="aimp_%1$s">%2$s</label></th><td><textarea id="aimp_%1$s" name="%3$s[%1$s]" rows="9" class="large-text code">%4$s</textarea></td></tr>',
				esc_attr( $key ),
				esc_html( $field[1] ),
				esc_attr( AIMP_Settings::OPTION ),
				esc_textarea( AIMP_Settings::get_text( $key, self::default_options( $field[0] ) ) )
			);
		}
		echo '</tbody></table>';

		// Own haberdashery types.
		$kinds = self::kinds();
		$rows  = self::own_types();
		$rows  = array_merge( $rows, array_fill( 0, max( 0, min( 2, self::OWN_MAX - count( $rows ) ) ), null ) );
		echo '<h3>' . esc_html__( 'Own haberdashery types', 'atelier-irisee-master-plugin' ) . '</h3>';
		echo '<p>' . esc_html__( 'A new type within buttons, zips, ribbons & cords or interfacing, e.g. "Klittenband | Velcro | Hook-and-loop tape" in Ribbons & cords. It can then be chosen in the product editor, patterns can ask for it in their Haberdashery block (with an amount per size), and the customizer gets a step for it. Leave the name empty to remove a type.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<input type="hidden" name="' . esc_attr( AIMP_Settings::OPTION ) . '[own_notion_types_present]" value="1">';
		echo '<table class="widefat striped aimp-own-types" style="max-width:960px"><thead><tr><th>' . esc_html__( 'Name (Dutch | French | English)', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Kind', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Sold', 'atelier-irisee-master-plugin' ) . '</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $rows as $key => $row ) {
			$name = AIMP_Settings::OPTION . '[own_notion_types][' . $i . ']';
			echo '<tr><td><input type="hidden" name="' . esc_attr( $name . '[key]' ) . '" value="' . esc_attr( $row ? $key : '' ) . '">';
			echo '<input type="text" class="regular-text" name="' . esc_attr( $name . '[name]' ) . '" value="' . esc_attr( $row ? $row['name'] : '' ) . '" aria-label="' . esc_attr__( 'Name', 'atelier-irisee-master-plugin' ) . '"></td><td><select name="' . esc_attr( $name . '[kind]' ) . '" aria-label="' . esc_attr__( 'Kind', 'atelier-irisee-master-plugin' ) . '">';
			foreach ( self::OWN_KINDS as $kind ) {
				echo '<option value="' . esc_attr( $kind ) . '"' . selected( $row ? $row['kind'] : 'ribbon', $kind, false ) . '>' . esc_html( $kinds[ $kind ]['name'] ) . '</option>';
			}
			echo '</select></td><td><select name="' . esc_attr( $name . '[measure]' ) . '" aria-label="' . esc_attr__( 'Sold', 'atelier-irisee-master-plugin' ) . '">';
			foreach ( array(
				''       => __( 'Like the kind', 'atelier-irisee-master-plugin' ),
				'piece'  => __( 'Per piece', 'atelier-irisee-master-plugin' ),
				'length' => __( 'Per length', 'atelier-irisee-master-plugin' ),
			) as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $row ? $row['measure'] : '', $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></td></tr>';
			++$i;
		}
		echo '</tbody></table>';
		if ( count( self::own_types() ) < self::OWN_MAX ) {
			echo '<p class="description">' . esc_html__( 'Save to get more empty rows.', 'atelier-irisee-master-plugin' ) . '</p>';
		}
	}

	/**
	 * The lists from the settings form (called from AIMP_Settings::sanitize()).
	 *
	 * @param array $input Submitted values.
	 * @return array
	 */
	public static function sanitize_options( $input ) {
		$clean = array();
		foreach ( array( 'button_materials' => 'material', 'button_finishes' => 'finish' ) as $key => $field ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}
			$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $input[ $key ] ) ) ) ), 'strlen' );
			$clean[ $key ] = implode( "\n", array_slice( array_values( $lines ), 0, 60 ) );
		}
		// Own haberdashery types: kept as they are when the form did not send them.
		if ( empty( $input['own_notion_types_present'] ) ) {
			$clean['own_notion_types'] = self::own_types();
			return $clean;
		}
		$own = array();
		foreach ( isset( $input['own_notion_types'] ) && is_array( $input['own_notion_types'] ) ? $input['own_notion_types'] : array() as $row ) {
			if ( ! is_array( $row ) || count( $own ) >= self::OWN_MAX ) {
				continue;
			}
			$name = isset( $row['name'] ) ? trim( sanitize_text_field( wp_unslash( $row['name'] ) ) ) : '';
			$kind = isset( $row['kind'] ) ? sanitize_key( $row['kind'] ) : '';
			if ( '' === $name || ! in_array( $kind, self::OWN_KINDS, true ) ) {
				continue;
			}
			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( ! preg_match( '/^own_[a-z0-9_]+$/', $key ) || isset( $own[ $key ] ) ) {
				// A new type: its key from the Dutch name, once.
				$parts = explode( '|', $name );
				$slug  = substr( str_replace( '-', '_', sanitize_title( $parts[0] ) ), 0, 30 );
				$base  = 'own_' . ( '' !== trim( $slug, '_' ) && preg_match( '/^[a-z0-9_]+$/', $slug ) ? $slug : 'type' );
				$key   = $base;
				for ( $n = 2; isset( $own[ $key ] ); $n++ ) {
					$key = $base . '_' . $n;
				}
			}
			$measure     = isset( $row['measure'] ) ? sanitize_key( $row['measure'] ) : '';
			$own[ $key ] = array(
				'kind'    => $kind,
				'name'    => function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 120 ) : substr( $name, 0, 120 ),
				'measure' => in_array( $measure, array( 'piece', 'length' ), true ) ? $measure : '',
			);
		}
		$clean['own_notion_types'] = $own;
		return $clean;
	}

	/**
	 * The main specification as text (buttons: diameter, zips: length, the others: width), for card subtitles.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function main_text( $product ) {
		$kind = self::kind_of( $product );
		$info = self::kind( $kind );
		return $info ? self::field_text( $product, $info['main'], $kind ) : '';
	}

	/**
	 * Next to a price: "per piece", "per set of 6", "per 10 cm", "per roll of 3 m"; '' for other products.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function price_suffix( $product ) {
		switch ( self::sold( $product ) ) {
			case 'set':
				/* translators: %d: number of pieces in a set */
				return sprintf( __( 'per set of %d', 'atelier-irisee-master-plugin' ), self::set_size( $product ) );
			case 'roll':
				/* translators: %s: length of a roll, e.g. "3 m" */
				return sprintf( __( 'per roll of %s', 'atelier-irisee-master-plugin' ), self::length_text( self::roll_length( $product ) ) );
			case '10cm':
				return __( 'per 10 cm', 'atelier-irisee-master-plugin' );
			case 'piece':
				return self::per_pair( $product ) ? __( 'per pair', 'atelier-irisee-master-plugin' ) : __( 'per piece', 'atelier-irisee-master-plugin' );
		}
		return '';
	}

	/**
	 * "Set of 6", "Roll of 3 m", "Per 10 cm" or "Piece" (product page tile, cart line).
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function sold_text( $product ) {
		switch ( self::sold( $product ) ) {
			case 'set':
				/* translators: %d: number of pieces in a set */
				return sprintf( __( 'Set of %d', 'atelier-irisee-master-plugin' ), self::set_size( $product ) );
			case 'roll':
				/* translators: %s: length of a roll, e.g. "3 m" */
				return sprintf( __( 'Roll of %s', 'atelier-irisee-master-plugin' ), self::length_text( self::roll_length( $product ) ) );
			case '10cm':
				return __( 'Per 10 cm', 'atelier-irisee-master-plugin' );
		}
		return self::per_pair( $product ) ? __( 'Pair', 'atelier-irisee-master-plugin' ) : __( 'Piece', 'atelier-irisee-master-plugin' );
	}

	/**
	 * "2 × set of 6", "1 × roll of 3 m" or "1,50 m is added (15 × 10 cm)" for a cart quantity; '' when sold per piece.
	 * Per 10 cm the need is rounded up, so 145 cm needed becomes 150 cm.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $qty     Quantity.
	 * @return string
	 */
	public static function qty_text( $product, $qty ) {
		switch ( self::sold( $product ) ) {
			case 'set':
				/* translators: 1: number of sets, 2: number of pieces in a set */
				return sprintf( __( '%1$d × set of %2$d', 'atelier-irisee-master-plugin' ), (int) $qty, self::set_size( $product ) );
			case 'roll':
				/* translators: 1: number of rolls, 2: length of a roll */
				return sprintf( __( '%1$d × roll of %2$s', 'atelier-irisee-master-plugin' ), (int) $qty, self::length_text( self::roll_length( $product ) ) );
			case '10cm':
				/* translators: 1: length, e.g. "1,50 m", 2: number of 10 cm pieces */
				return sprintf( __( '%1$s is added (%2$d × 10 cm)', 'atelier-irisee-master-plugin' ), self::length_text( (int) $qty * AIMP_Catalog::FABRIC_UNIT_CM ), (int) $qty );
		}
		return '';
	}

	/**
	 * The notice in the configurator for products sold per set or roll: whole sets/rolls are added, the amount
	 * can't be changed, and there may be more than needed. '' for other products.
	 *
	 * @param WC_Product $product Product.
	 * @param float      $need    Pieces, or cm.
	 * @param int        $qty     Sets or rolls added.
	 * @return string
	 */
	public static function note( $product, $need, $qty ) {
		$sold = self::sold( $product );
		if ( 'set' === $sold ) {
			$size  = self::set_size( $product );
			$total = (int) $qty * $size;
			/* translators: %d: number of pieces in a set */
			$note = sprintf( __( 'These are only sold per set of %d, and the amount can\'t be changed.', 'atelier-irisee-master-plugin' ), $size ) . ' ';
			if ( $total > $need ) {
				/* translators: 1: number of sets, 2: pieces in a set, 3: pieces in total, 4: pieces more than needed */
				return $note . sprintf( __( 'Added: %1$d × set of %2$d = %3$d pieces, %4$d more than needed.', 'atelier-irisee-master-plugin' ), $qty, $size, $total, $total - (int) $need );
			}
			/* translators: 1: number of sets, 2: pieces in a set, 3: pieces in total */
			return $note . sprintf( __( 'Added: %1$d × set of %2$d = %3$d pieces.', 'atelier-irisee-master-plugin' ), $qty, $size, $total );
		}
		if ( 'roll' === $sold ) {
			$length = self::roll_length( $product );
			$total  = (int) $qty * $length;
			/* translators: %s: length of a roll */
			$note = sprintf( __( 'This is only sold per roll of %s, and the amount can\'t be changed.', 'atelier-irisee-master-plugin' ), self::length_text( $length ) ) . ' ';
			if ( $total - $need > 0.01 ) {
				/* translators: 1: number of rolls, 2: length of a roll, 3: total length, 4: length more than needed */
				return $note . sprintf( __( 'Added: %1$d × roll of %2$s = %3$s, %4$s more than needed.', 'atelier-irisee-master-plugin' ), $qty, self::length_text( $length ), self::length_text( $total ), self::length_text( $total - $need ) );
			}
			/* translators: 1: number of rolls, 2: length of a roll, 3: total length */
			return $note . sprintf( __( 'Added: %1$d × roll of %2$s = %3$s.', 'atelier-irisee-master-plugin' ), $qty, self::length_text( $length ), self::length_text( $total ) );
		}
		return '';
	}

	/**
	 * The specification tiles of a product page: subject => [ label, value ] (icon key AIMP_Product_Page::fabric_icon()).
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function specs( $product ) {
		$kind   = self::kind_of( $product );
		$info   = self::kind( $kind );
		$fields = self::fields();
		$specs  = array();
		if ( ! $info ) {
			return $specs;
		}
		foreach ( $info['specs'] as $field ) {
			if ( 'length' === $field && 'roll' === self::sold( $product ) ) {
				continue; // A roll's length is in "Sold per".
			}
			$value = self::field_text( $product, $field, $kind );
			if ( '' !== $value ) {
				// Icon keys: the type tile shows a button or a zip, the material a cube.
				$icon           = 'type' === $field ? ( 'zip' === $kind ? 'zips' : ( 'ribbon' === $kind ? 'ribbons' : 'button_type' ) ) : ( 'material' === $field ? 'button_material' : $field );
				$specs[ $icon ] = array( $fields[ $field ][1], $value );
			}
		}
		$specs['set'] = array( __( 'Sold per', 'atelier-irisee-master-plugin' ), self::sold_text( $product ) );
		return $specs;
	}

	/**
	 * The "Ideal for" choices: keys of ideal_for_options(), at most IDEAL_FOR_MAX.
	 *
	 * @param WC_Product $product Product.
	 * @return string[]
	 */
	public static function ideal_for( $product ) {
		$saved = $product->get_meta( self::META_IDEAL_FOR );
		$keys  = array_values( array_unique( array_intersect( is_array( $saved ) ? $saved : array(), array_keys( self::ideal_for_options() ) ) ) );
		return array_slice( $keys, 0, self::IDEAL_FOR_MAX );
	}

	/* ------------------------------------------------------------------
	 * Pattern rules
	 * ------------------------------------------------------------------ */

	/**
	 * The rules of a pattern (or of one of its designs): rule => [ on, min, max, label, type, amount, saved ].
	 * A rule that was never saved (patterns from before 3.6, or kinds added in 3.7) counts as on with any width, so
	 * existing amounts keep working; "saved" tells the editor to show it as on only when a size has an amount for
	 * it. The extra button groups are off until switched on. "label" names a button group ("Cuffs"), "type" is the
	 * button type of an extra group, and "amount" is the number or length (cm) for every size that has none of its own.
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $design     Design slug: its own rules when it has them, else the pattern's.
	 * @return array
	 */
	public static function pattern_rules( $pattern_id, $design = '' ) {
		$saved = self::saved_rules( $pattern_id, $design );
		$rules = array();
		foreach ( array_keys( self::rules() ) as $key ) {
			$row           = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : null;
			$rules[ $key ] = array(
				'on'     => null === $row ? ! self::is_extra( $key ) : ! empty( $row['on'] ),
				'min'    => $row && isset( $row['min'] ) && '' !== (string) $row['min'] ? (float) $row['min'] : '',
				'max'    => $row && isset( $row['max'] ) && '' !== (string) $row['max'] ? (float) $row['max'] : '',
				'label'  => $row && isset( $row['label'] ) ? (string) $row['label'] : '',
				'type'   => $row && isset( $row['type'] ) ? (string) $row['type'] : '',
				'amount' => $row && isset( $row['amount'] ) && '' !== (string) $row['amount'] && (float) $row['amount'] > 0 ? (float) $row['amount'] : '',
				// Finish choice: '' (always needed), or option 'a' / 'b' of which the customer chooses one.
				'finish' => $row && isset( $row['finish'] ) && in_array( $row['finish'], array( 'a', 'b' ), true ) ? $row['finish'] : '',
				'saved'  => null !== $row,
			);
		}
		return $rules;
	}

	/**
	 * The finish choice of a pattern (or design): the customer takes option a or b (e.g. buttons or a cord).
	 * [ 'a' => [ name, rules[] ], 'b' => … ], or [] when the pattern has no choice (both options need a rule).
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $design     Design slug.
	 * @return array
	 */
	public static function finish_options( $pattern_id, $design = '' ) {
		$names   = self::finish_names( $pattern_id, $design );
		$fields  = AIMP_Catalog::material_fields();
		$all     = self::rules();
		$options = array( 'a' => array(), 'b' => array() );
		foreach ( self::pattern_rules( $pattern_id, $design ) as $key => $rule ) {
			if ( $rule['on'] && '' !== $rule['finish'] ) {
				$options[ $rule['finish'] ][ $key ] = $fields[ $all[ $key ][2] ]['label'] . ( '' !== $rule['label'] ? ' – ' . $rule['label'] : '' );
			}
		}
		if ( ! $options['a'] || ! $options['b'] ) {
			return array();
		}
		$result = array();
		foreach ( $options as $option => $rules ) {
			$name              = isset( $names[ $option ] ) ? trim( (string) $names[ $option ] ) : '';
			$result[ $option ] = array(
				// Without its own name: what it needs ("Cord", "Buttons + Ribbon").
				'name'  => '' !== $name ? $name : implode( ' + ', $rules ),
				'rules' => array_keys( $rules ),
			);
		}
		return $result;
	}

	/**
	 * The own names of the finish options, as saved: [ a => name, b => name ] ('' = named after its items).
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $design     Design slug.
	 * @return array
	 */
	public static function finish_names( $pattern_id, $design = '' ) {
		$saved = self::saved_rules( $pattern_id, $design );
		$names = isset( $saved['finish'] ) && is_array( $saved['finish'] ) ? $saved['finish'] : array();
		return array(
			'a' => isset( $names['a'] ) ? (string) $names['a'] : '',
			'b' => isset( $names['b'] ) ? (string) $names['b'] : '',
		);
	}

	/**
	 * The lining switch of a pattern (or design), stored with its haberdashery rules (key "lining"): [ on, amount,
	 * saved ]. Never saved (patterns from before 3.52): on, so the lining lengths of the sizes keep counting.
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $design     Design slug.
	 * @return array
	 */
	public static function lining_rule( $pattern_id, $design = '' ) {
		$saved = self::saved_rules( $pattern_id, $design );
		$row   = isset( $saved['lining'] ) && is_array( $saved['lining'] ) ? $saved['lining'] : null;
		return array(
			'on'     => null === $row || ! empty( $row['on'] ),
			'amount' => $row && isset( $row['amount'] ) && (float) $row['amount'] > 0 ? (float) $row['amount'] : '',
			'saved'  => null !== $row,
		);
	}

	/**
	 * "Extra fabric for narrow fabrics" of a pattern (or design), stored with its haberdashery rules (key
	 * "fabric_extra"): rows of [ wmin, wmax (fabric width in cm), from, to (size slugs, '' = no limit), cm ].
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $design     Design slug.
	 * @return array
	 */
	public static function fabric_extra_rows( $pattern_id, $design = '' ) {
		$saved = self::saved_rules( $pattern_id, $design );
		$rows  = array();
		foreach ( isset( $saved['fabric_extra'] ) && is_array( $saved['fabric_extra'] ) ? $saved['fabric_extra'] : array() as $row ) {
			if ( is_array( $row ) && ! empty( $row['cm'] ) ) {
				$rows[] = array(
					'wmin' => isset( $row['wmin'] ) ? (int) $row['wmin'] : 0,
					'wmax' => isset( $row['wmax'] ) ? (int) $row['wmax'] : 999,
					'from' => isset( $row['from'] ) ? (string) $row['from'] : '',
					'to'   => isset( $row['to'] ) ? (string) $row['to'] : '',
					'cm'   => (float) $row['cm'],
				);
			}
		}
		return $rows;
	}

	/**
	 * The stored rules of a pattern, or of one of its designs when it has its own.
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $design     Design slug.
	 * @return array
	 */
	private static function saved_rules( $pattern_id, $design = '' ) {
		$saved = array();
		if ( $pattern_id && '' !== $design ) {
			$designs = AIMP_Catalog::design_data( $pattern_id );
			if ( ! empty( $designs[ $design ]['notion_rules'] ) ) {
				$saved = $designs[ $design ]['notion_rules'];
			}
		}
		if ( ! $saved && $pattern_id ) {
			$saved = get_post_meta( $pattern_id, self::META_RULES, true );
			$saved = is_array( $saved ) ? $saved : array();
		}
		if ( ! $saved && $pattern_id ) {
			// 3.6: only the buttons, keyed by button type (the same keys as the rules).
			$old   = get_post_meta( $pattern_id, self::META_BUTTON_RULES, true );
			$saved = is_array( $old ) ? $old : array();
		}
		return $saved;
	}

	/**
	 * An extra button group (button_2, button_3): its button type and label are set per pattern.
	 *
	 * @param string $key Rule key.
	 * @return bool
	 */
	public static function is_extra( $key ) {
		return in_array( $key, array( 'button_2', 'button_3' ), true );
	}

	/**
	 * The product type a rule asks for: the extra button groups use the type set on the pattern.
	 *
	 * @param string $key  Rule key.
	 * @param array  $rule [ on, min, max, label, type, … ].
	 * @return string
	 */
	public static function rule_type( $key, $rule ) {
		$rules = self::rules();
		if ( ! isset( $rules[ $key ] ) ) {
			return '';
		}
		if ( self::is_extra( $key ) && ! empty( $rule['type'] ) ) {
			$kind = self::kind( $rules[ $key ][0] );
			if ( isset( $kind['types'][ $rule['type'] ] ) ) {
				return $rule['type'];
			}
		}
		return $rules[ $key ][1];
	}

	/**
	 * The unit of a rule's range ('mm', 'cm', or '' when the rule has no range).
	 *
	 * @param string $rule Rule key.
	 * @return string
	 */
	public static function rule_unit( $rule ) {
		$rules = self::rules();
		$info  = isset( $rules[ $rule ] ) ? self::kind( $rules[ $rule ][0] ) : null;
		// A type counted otherwise than its kind (shoulder pads) has no size range.
		if ( $info && 'piece' === self::measure( $rules[ $rule ][0], $rules[ $rule ][1] ) && 'piece' !== $info['measure'] ) {
			return '';
		}
		// Of the interfacing types only interfacing fabric (Tussenvoering) is limited by the pattern; twill tape and the
		// other own types offer all of theirs.
		if ( 'interfacing' === $rules[ $rule ][0] && 'interfacing' !== $rules[ $rule ][1] ) {
			return '';
		}
		return $info && '' !== $info['range'] ? $info['range_unit'] : '';
	}

	/**
	 * "15–20 mm", "15 mm" (only the first box), "up to 20 mm" or '' (any size).
	 *
	 * @param array  $rule [ on, min, max ].
	 * @param string $unit mm or cm.
	 * @return string
	 */
	public static function range_text( $rule, $unit = 'mm' ) {
		$min = isset( $rule['min'] ) && '' !== $rule['min'] ? (float) $rule['min'] : 0;
		$max = isset( $rule['max'] ) && '' !== $rule['max'] ? (float) $rule['max'] : 0;
		$num = function ( $n ) {
			return AIMP_I18n::number( $n, floor( $n ) == $n ? 0 : 1 );
		};
		$cm  = 'cm' === $unit;
		if ( $min > 0 && $max > 0 ) {
			if ( $min == $max ) {
				return self::measure_text( $min, $unit );
			}
			/* translators: 1: smallest size, 2: largest size */
			return sprintf( $cm ? __( '%1$s–%2$s cm', 'atelier-irisee-master-plugin' ) : __( '%1$s–%2$s mm', 'atelier-irisee-master-plugin' ), $num( min( $min, $max ) ), $num( max( $min, $max ) ) );
		}
		// Only the first box filled in: that size, without "from".
		if ( $min > 0 ) {
			return self::measure_text( $min, $unit );
		}
		if ( $max > 0 ) {
			/* translators: %s: size */
			return sprintf( $cm ? __( 'up to %s cm', 'atelier-irisee-master-plugin' ) : __( 'up to %s mm', 'atelier-irisee-master-plugin' ), $num( $max ) );
		}
		return '';
	}

	/**
	 * Whether a product fits a pattern rule: the rule's kind, its type, and a size within the range.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     Rule key.
	 * @param array      $rule    [ on, min, max ].
	 * @return bool
	 */
	public static function fits( $product, $key, $rule ) {
		$rules = self::rules();
		if ( empty( $rule['on'] ) || ! isset( $rules[ $key ] ) ) {
			return false;
		}
		$kind = $rules[ $key ][0];
		$type = self::rule_type( $key, $rule );
		if ( self::kind_of( $product ) !== $kind ) {
			return false;
		}
		$own = self::type_of( $product, $kind );
		if ( '' !== $type && '' !== $own && $own !== $type ) {
			return false;
		}
		$info = self::kind( $kind );
		$min  = '' !== $rule['min'] ? (float) $rule['min'] : 0;
		$max  = '' !== $rule['max'] ? (float) $rule['max'] : 0;
		if ( '' === $info['range'] || '' === self::rule_unit( $key ) || ( ! $min && ! $max ) ) {
			return true;
		}
		$value = self::number( $product, $info['range'] );
		if ( $value <= 0 ) {
			return false;
		}
		$low  = $min && $max ? min( $min, $max ) : $min;
		$high = $min && $max ? max( $min, $max ) : $max;
		return ( ! $low || $value >= $low - 0.001 ) && ( ! $high || $value <= $high + 0.001 );
	}

	/**
	 * Meta query for the products of a rule: its type (products saved without a type count as the kind's default
	 * type; zips without a type fit every zip type) and its range.
	 *
	 * @param string $key  Rule key.
	 * @param array  $rule [ on, min, max ].
	 * @return array
	 */
	public static function meta_query( $key, $rule ) {
		$rules = self::rules();
		$query = array( 'relation' => 'AND' );
		if ( ! isset( $rules[ $key ] ) ) {
			return $query;
		}
		$kind = $rules[ $key ][0];
		$type = self::rule_type( $key, $rule );
		$info = self::kind( $kind );
		if ( '' !== $type ) {
			if ( $info['default_type'] === $type || '' === $info['default_type'] ) {
				$query[] = array(
					'relation' => 'OR',
					array(
						'key'   => self::META_TYPE,
						'value' => $type,
					),
					array(
						'key'     => self::META_TYPE,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => self::META_TYPE,
						'value' => '',
					),
				);
			} else {
				$query[] = array(
					'key'   => self::META_TYPE,
					'value' => $type,
				);
			}
		}
		$min = '' !== $rule['min'] ? (float) $rule['min'] : 0;
		$max = '' !== $rule['max'] ? (float) $rule['max'] : 0;
		if ( '' !== $info['range'] && '' !== self::rule_unit( $key ) && ( $min || $max ) ) {
			$fields = self::fields();
			$clause = array(
				'key'  => $fields[ $info['range'] ][0],
				'type' => 'DECIMAL(10,2)',
			);
			if ( $min && $max ) {
				$clause['value']   = array( min( $min, $max ), max( $min, $max ) );
				$clause['compare'] = 'BETWEEN';
			} else {
				$clause['value']   = $min ? $min : $max;
				$clause['compare'] = $min ? '>=' : '<=';
			}
			$query[] = $clause;
		}
		return $query;
	}

	/* ------------------------------------------------------------------
	 * Stored data from before 3.7
	 * ------------------------------------------------------------------ */

	/**
	 * Once: the button fields (3.6) and the zip length move to the shared haberdashery fields, and zips in one
	 * of the four zip type categories (3.5) get that zip type. The old values stay in place, unused.
	 */
	public static function migrate() {
		if ( (int) get_option( 'aimp_db_version', 0 ) >= self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		$moves = array(
			'_aimp_btn_type'      => self::META_TYPE,
			'_aimp_btn_colour'    => self::META_COLOUR,
			'_aimp_btn_diameter'  => self::META_DIAMETER,
			'_aimp_btn_material'  => self::META_MATERIAL,
			'_aimp_btn_finish'    => self::META_FINISH,
			'_aimp_btn_mounting'  => self::META_MOUNTING,
			'_aimp_btn_set_size'  => self::META_SET_SIZE,
			'_aimp_btn_ideal_for' => self::META_IDEAL_FOR,
			'_aimp_zip_length'    => self::META_LENGTH,
		);
		$touched = array();
		foreach ( $moves as $old => $new ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time update.
			foreach ( (array) $rows as $row ) {
				if ( ! metadata_exists( 'post', $row->post_id, $new ) ) {
					add_post_meta( (int) $row->post_id, $new, maybe_unserialize( $row->meta_value ), true );
					$touched[ (int) $row->post_id ] = true;
				}
				if ( '_aimp_btn_set_size' === $old && absint( $row->meta_value ) > 1 && ! metadata_exists( 'post', $row->post_id, self::META_SOLD ) ) {
					add_post_meta( (int) $row->post_id, self::META_SOLD, 'set', true );
				}
			}
		}
		// Zips in the old zip type categories (the settings are no longer shown, but still stored).
		$settings = get_option( 'aimp_settings', array() );
		foreach ( array( 'zip_divisible', 'zip_invisible', 'zip_non_divisible', 'zip_double_divisible' ) as $type ) {
			$cat = is_array( $settings ) && ! empty( $settings[ $type . '_cat' ] ) ? absint( $settings[ $type . '_cat' ] ) : 0;
			if ( ! $cat ) {
				continue;
			}
			$ids = get_objects_in_term( AIMP_Catalog::category_tree( $cat ), 'product_cat' );
			foreach ( is_wp_error( $ids ) ? array() : $ids as $id ) {
				if ( ! metadata_exists( 'post', $id, self::META_TYPE ) ) {
					add_post_meta( (int) $id, self::META_TYPE, $type, true );
					$touched[ (int) $id ] = true;
				}
			}
		}
		foreach ( array_keys( $touched ) as $id ) {
			wc_delete_product_transients( $id );
		}
		update_option( 'aimp_db_version', self::DB_VERSION );
		if ( class_exists( 'AIMP_Shop' ) ) {
			AIMP_Shop::flush_cache();
		}
	}
}
