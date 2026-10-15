<?php
/**
 * Buttons: the "Button details" box on products in the Buttons category (type, colour, diameter, material,
 * surface finish, mounting method, sold per piece or per set, "Ideal for"), the set size in the cart and on
 * orders, and the Button type and Diameter columns in the "All buttons" list.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Button_Fields {

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_box' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );

		// A set is one item in the cart; "Set of 6" says how many buttons are in it.
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item' ), 10, 3 );

		// Products → All buttons: type and diameter columns.
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort' ) );
	}

	public static function add_box() {
		add_meta_box( 'aimp-button-details', __( 'Button details', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render' ), 'product', 'normal', 'high' );
	}

	/**
	 * Suggestions for the free text fields (they can type anything else).
	 *
	 * @return array field => [ suggestion ]
	 */
	private static function suggestions() {
		return array(
			'material' => array(
				__( 'Wood', 'atelier-irisee-master-plugin' ),
				__( 'Mother-of-pearl', 'atelier-irisee-master-plugin' ),
				__( 'Metal', 'atelier-irisee-master-plugin' ),
				__( 'Plastic', 'atelier-irisee-master-plugin' ),
				__( 'Coconut', 'atelier-irisee-master-plugin' ),
				__( 'Horn', 'atelier-irisee-master-plugin' ),
				__( 'Corozo', 'atelier-irisee-master-plugin' ),
				__( 'Leather', 'atelier-irisee-master-plugin' ),
			),
			'finish'   => array(
				__( 'Glossy', 'atelier-irisee-master-plugin' ),
				__( 'Matt', 'atelier-irisee-master-plugin' ),
				__( 'Satin', 'atelier-irisee-master-plugin' ),
				__( 'Brushed', 'atelier-irisee-master-plugin' ),
				__( 'Polished', 'atelier-irisee-master-plugin' ),
				__( 'Natural', 'atelier-irisee-master-plugin' ),
			),
			'mounting' => array(
				__( '2 holes', 'atelier-irisee-master-plugin' ),
				__( '4 holes', 'atelier-irisee-master-plugin' ),
				__( 'Shank', 'atelier-irisee-master-plugin' ),
				__( 'Press (with a tool)', 'atelier-irisee-master-plugin' ),
				__( 'Rivet', 'atelier-irisee-master-plugin' ),
				__( 'Sew-on', 'atelier-irisee-master-plugin' ),
			),
		);
	}

	/**
	 * Free text fields: field => [ meta, label, placeholder ].
	 *
	 * @return array
	 */
	private static function text_fields() {
		return array(
			'colour'   => array( AIMP_Catalog::META_BTN_COLOUR, __( 'Colour', 'atelier-irisee-master-plugin' ), __( 'e.g. ochre yellow', 'atelier-irisee-master-plugin' ) ),
			'material' => array( AIMP_Catalog::META_BTN_MATERIAL, __( 'Material', 'atelier-irisee-master-plugin' ), __( 'e.g. wood', 'atelier-irisee-master-plugin' ) ),
			'finish'   => array( AIMP_Catalog::META_BTN_FINISH, __( 'Surface finish', 'atelier-irisee-master-plugin' ), __( 'e.g. matt', 'atelier-irisee-master-plugin' ) ),
			'mounting' => array( AIMP_Catalog::META_BTN_MOUNTING, __( 'Mounting method', 'atelier-irisee-master-plugin' ), __( 'e.g. 4 holes', 'atelier-irisee-master-plugin' ) ),
		);
	}

	/**
	 * @param WP_Post $post Product post.
	 */
	public static function render( $post ) {
		$product = wc_get_product( $post->ID );
		$tree    = AIMP_Catalog::category_tree( AIMP_Settings::get( 'button_cat' ) );
		$get     = function ( $key ) use ( $product ) {
			return $product ? (string) $product->get_meta( $key ) : '';
		};
		$type    = $product ? AIMP_Catalog::button_type( $product ) : 'button';
		$set     = $product ? AIMP_Catalog::button_set_size( $product ) : 1;
		$ideal   = $product ? AIMP_Catalog::ideal_for( $product ) : array();
		$hints   = self::suggestions();
		$fields  = self::text_fields();
		?>
		<div class="aimp-button-details" data-button-terms="<?php echo esc_attr( wp_json_encode( array_values( $tree ) ) ); ?>">
			<input type="hidden" name="aimp_button_details" value="1">
			<p class="description"><?php esc_html_e( 'Only used for buttons (the Buttons category). The type and the diameter decide in which patterns the button is offered in the configurator.', 'atelier-irisee-master-plugin' ); ?></p>

			<fieldset class="aimp-button-specs">
				<legend><?php esc_html_e( 'Specifications', 'atelier-irisee-master-plugin' ); ?></legend>
				<p>
					<label for="aimp_btn_type"><?php esc_html_e( 'Type', 'atelier-irisee-master-plugin' ); ?></label>
					<select id="aimp_btn_type" name="aimp_button[type]">
						<?php foreach ( AIMP_Catalog::button_types() as $key => $info ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $type, $key ); ?>><?php echo esc_html( $info[0] ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php foreach ( array( 'colour' ) as $field ) : ?>
					<p>
						<label for="<?php echo esc_attr( 'aimp_btn_' . $field ); ?>"><?php echo esc_html( $fields[ $field ][1] ); ?></label>
						<input type="text" id="<?php echo esc_attr( 'aimp_btn_' . $field ); ?>" name="<?php echo esc_attr( 'aimp_button[' . $field . ']' ); ?>" value="<?php echo esc_attr( $get( $fields[ $field ][0] ) ); ?>" placeholder="<?php echo esc_attr( $fields[ $field ][2] ); ?>">
					</p>
				<?php endforeach; ?>
				<p>
					<label for="aimp_btn_diameter"><?php esc_html_e( 'Diameter (mm)', 'atelier-irisee-master-plugin' ); ?></label>
					<input type="number" id="aimp_btn_diameter" name="aimp_button[diameter]" min="0" step="0.5" value="<?php echo esc_attr( $get( AIMP_Catalog::META_BTN_DIAMETER ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. 15', 'atelier-irisee-master-plugin' ); ?>">
				</p>
				<?php foreach ( array( 'material', 'finish', 'mounting' ) as $field ) : ?>
					<p>
						<label for="<?php echo esc_attr( 'aimp_btn_' . $field ); ?>"><?php echo esc_html( $fields[ $field ][1] ); ?></label>
						<input type="text" id="<?php echo esc_attr( 'aimp_btn_' . $field ); ?>" name="<?php echo esc_attr( 'aimp_button[' . $field . ']' ); ?>" list="<?php echo esc_attr( 'aimp_btn_' . $field . '_list' ); ?>" value="<?php echo esc_attr( $get( $fields[ $field ][0] ) ); ?>" placeholder="<?php echo esc_attr( $fields[ $field ][2] ); ?>">
						<datalist id="<?php echo esc_attr( 'aimp_btn_' . $field . '_list' ); ?>">
							<?php foreach ( $hints[ $field ] as $hint ) : ?>
								<option value="<?php echo esc_attr( $hint ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</p>
				<?php endforeach; ?>
			</fieldset>

			<fieldset class="aimp-button-sold">
				<legend><?php esc_html_e( 'Sold per', 'atelier-irisee-master-plugin' ); ?></legend>
				<p class="aimp-button-sold-choice">
					<label><input type="radio" name="aimp_button[sold]" value="piece"<?php checked( $set <= 1 ); ?>> <?php esc_html_e( 'Per piece', 'atelier-irisee-master-plugin' ); ?></label>
					<label>
						<input type="radio" name="aimp_button[sold]" value="set"<?php checked( $set > 1 ); ?>>
						<?php esc_html_e( 'Per set of', 'atelier-irisee-master-plugin' ); ?>
						<input type="number" class="small-text" name="aimp_button[set_size]" min="2" step="1" value="<?php echo $set > 1 ? esc_attr( $set ) : ''; ?>" aria-label="<?php esc_attr_e( 'Buttons in a set', 'atelier-irisee-master-plugin' ); ?>">
						<?php esc_html_e( 'buttons', 'atelier-irisee-master-plugin' ); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Per set: the price and the stock are per set, and one sale lowers the stock by one set. The set size is shown to the customer ("per set of 6"); the configurator adds as many whole sets as a pattern size needs.', 'atelier-irisee-master-plugin' ); ?></p>
			</fieldset>

			<fieldset class="aimp-button-ideal">
				<legend><?php esc_html_e( 'Ideal for', 'atelier-irisee-master-plugin' ); ?></legend>
				<p class="description"><?php esc_html_e( 'Up to four kinds of clothes, each shown with its own icon on the button page under "Ideal for".', 'atelier-irisee-master-plugin' ); ?></p>
				<div class="aimp-button-ideal-list">
					<?php for ( $n = 0; $n < AIMP_Catalog::IDEAL_FOR_MAX; $n++ ) : ?>
						<select name="aimp_button[ideal_for][]" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: choice number */ __( 'Ideal for, choice %d', 'atelier-irisee-master-plugin' ), $n + 1 ) ); ?>">
							<option value=""><?php esc_html_e( '— None —', 'atelier-irisee-master-plugin' ); ?></option>
							<?php foreach ( AIMP_Catalog::ideal_for_options() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"<?php selected( isset( $ideal[ $n ] ) ? $ideal[ $n ] : '', $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endfor; ?>
				</div>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Runs inside WooCommerce's product save, which already verified its nonce.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( empty( $_POST['aimp_button_details'] ) || ! isset( $_POST['aimp_button'] ) || ! is_array( $_POST['aimp_button'] ) ) {
			return;
		}
		$input = wp_unslash( $_POST['aimp_button'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		// phpcs:enable
		// Only buttons keep these fields (the box is hidden for other products, but still sent).
		$root = AIMP_Settings::get( 'button_cat' );
		$cats = isset( $_POST['tax_input']['product_cat'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['tax_input']['product_cat'] ) ) : wc_get_product_cat_ids( $product->get_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $root || ! array_intersect( $cats, AIMP_Catalog::category_tree( $root ) ) ) {
			return;
		}

		$set = function ( $key, $value ) use ( $product ) {
			if ( '' === $value || array() === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		};

		$type = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
		$set( AIMP_Catalog::META_BTN_TYPE, array_key_exists( $type, AIMP_Catalog::button_types() ) ? $type : 'button' );
		foreach ( self::text_fields() as $field => $info ) {
			$set( $info[0], isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '' );
		}
		$diameter = isset( $input['diameter'] ) ? wc_clean( $input['diameter'] ) : '';
		$set( AIMP_Catalog::META_BTN_DIAMETER, '' === $diameter || (float) $diameter <= 0 ? '' : wc_format_decimal( (float) $diameter, 1, true ) );

		$size = isset( $input['set_size'] ) ? absint( $input['set_size'] ) : 0;
		$set( AIMP_Catalog::META_BTN_SET_SIZE, isset( $input['sold'] ) && 'set' === $input['sold'] && $size > 1 ? (string) $size : '' );

		$ideal = isset( $input['ideal_for'] ) ? array_map( 'sanitize_key', (array) $input['ideal_for'] ) : array();
		$ideal = array_slice( array_values( array_unique( array_intersect( $ideal, array_keys( AIMP_Catalog::ideal_for_options() ) ) ) ), 0, AIMP_Catalog::IDEAL_FOR_MAX );
		$set( AIMP_Catalog::META_BTN_IDEAL_FOR, $ideal );
	}

	/**
	 * Cart and checkout: "Sold per: Set of 6" under a button sold per set.
	 *
	 * @param array $item_data Lines.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function item_data( $item_data, $cart_item ) {
		$set = isset( $cart_item['data'] ) ? AIMP_Catalog::button_set_size( $cart_item['data'] ) : 1;
		if ( $set > 1 ) {
			$item_data[] = array(
				'key'   => __( 'Sold per', 'atelier-irisee-master-plugin' ),
				'value' => AIMP_Catalog::set_text( $set ),
			);
		}
		return $item_data;
	}

	/**
	 * Order line (also in the e-mails): "Sold per: Set of 6".
	 *
	 * @param WC_Order_Item_Product $item   Order item.
	 * @param string                $key    Cart item key.
	 * @param array                 $values Cart item.
	 */
	public static function order_item( $item, $key, $values ) {
		$set = isset( $values['data'] ) ? AIMP_Catalog::button_set_size( $values['data'] ) : 1;
		if ( $set > 1 ) {
			$item->add_meta_data( __( 'Sold per', 'atelier-irisee-master-plugin' ), AIMP_Catalog::set_text( $set ), true );
		}
	}

	/**
	 * Whether the product list shows the Buttons category ("Products → All buttons").
	 *
	 * @return bool
	 */
	private static function is_button_list() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which list is open.
		$slug = isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( $_GET['product_cat'] ) ) : '';
		$term = $slug ? get_term( AIMP_Settings::get( 'button_cat' ), 'product_cat' ) : null;
		return $term && ! is_wp_error( $term ) && $term->slug === $slug;
	}

	/**
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		if ( ! self::is_button_list() ) {
			return $columns;
		}
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['aimp_btn_type']     = __( 'Button type', 'atelier-irisee-master-plugin' );
				$new['aimp_btn_diameter'] = __( 'Diameter', 'atelier-irisee-master-plugin' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Product.
	 */
	public static function column( $column, $post_id ) {
		if ( 'aimp_btn_type' !== $column && 'aimp_btn_diameter' !== $column ) {
			return;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product || ! AIMP_Catalog::is_button( $product ) ) {
			echo '–';
			return;
		}
		if ( 'aimp_btn_type' === $column ) {
			$types = AIMP_Catalog::button_types();
			echo esc_html( $types[ AIMP_Catalog::button_type( $product ) ][0] );
			$set = AIMP_Catalog::button_set_size( $product );
			if ( $set > 1 ) {
				echo '<br><small>' . esc_html( AIMP_Catalog::set_text( $set ) ) . '</small>';
			}
			return;
		}
		$text = AIMP_Catalog::button_diameter_text( $product );
		echo esc_html( '' !== $text ? $text : '–' );
	}

	/**
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['aimp_btn_type']     = 'aimp_btn_type';
		$columns['aimp_btn_diameter'] = 'aimp_btn_diameter';
		return $columns;
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function sort( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' ) ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( 'aimp_btn_type' === $orderby ) {
			$query->set( 'meta_key', AIMP_Catalog::META_BTN_TYPE ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'aimp_btn_diameter' === $orderby ) {
			$query->set( 'meta_key', AIMP_Catalog::META_BTN_DIAMETER ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
}
