<?php
/**
 * Haberdashery products (buttons, zips, interfacing, elastic, ribbons & cords, bias tape): the "Haberdashery
 * details" box with the order information, the specifications of the kind, how it is sold and "Ideal for";
 * the set or roll in the cart and on orders; and the Type and size columns in the product lists per kind.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Notion_Fields {

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_box' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );

		// A set or roll is one item in the cart; "Set of 6" / "Roll of 3 m" says what is in it.
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item' ), 10, 3 );

		// Products → All buttons, All zips, …: type and size columns.
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort' ) );
	}

	public static function add_box() {
		add_meta_box( 'aimp-notion-details', __( 'Haberdashery details', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render' ), 'product', 'normal', 'high' );
	}

	/**
	 * Suggestions for the free text fields (anything else can be typed).
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
	 * Label of a specification field in the form, with its unit: "Diameter (mm)", "Width (cm)", "Length (cm)".
	 *
	 * @param string $kind  Kind.
	 * @param string $field Field.
	 * @return string
	 */
	private static function field_label( $kind, $field ) {
		$fields = AIMP_Notions::fields();
		$unit   = AIMP_Notions::unit( $kind, $field );
		/* translators: 1: field, e.g. "Width", 2: unit, e.g. "mm" */
		return '' !== $unit ? sprintf( __( '%1$s (%2$s)', 'atelier-irisee-master-plugin' ), $fields[ $field ][1], $unit ) : $fields[ $field ][1];
	}

	/**
	 * Placeholders.
	 *
	 * @param string $kind  Kind.
	 * @param string $field Field.
	 * @return string
	 */
	private static function example( $kind, $field ) {
		switch ( $field ) {
			case 'colour':
				return __( 'e.g. ochre yellow', 'atelier-irisee-master-plugin' );
			case 'material':
				return __( 'e.g. wood', 'atelier-irisee-master-plugin' );
			case 'finish':
				return __( 'e.g. matt', 'atelier-irisee-master-plugin' );
			case 'mounting':
				return __( 'e.g. 4 holes', 'atelier-irisee-master-plugin' );
			case 'diameter':
				return __( 'e.g. 15', 'atelier-irisee-master-plugin' );
			case 'width':
				return 'interfacing' === $kind ? __( 'e.g. 90', 'atelier-irisee-master-plugin' ) : __( 'e.g. 15', 'atelier-irisee-master-plugin' );
			case 'length':
				return __( 'e.g. 30', 'atelier-irisee-master-plugin' );
		}
		return '';
	}

	/**
	 * The category trees of the kinds: kind => [ term IDs ] (admin.js shows the matching part of the box).
	 *
	 * @return array
	 */
	private static function kind_terms() {
		$terms = array();
		foreach ( AIMP_Notions::kinds() as $kind => $info ) {
			$root = (int) AIMP_Settings::get( $info['setting'] );
			if ( $root ) {
				$terms[ $kind ] = array_values( AIMP_Catalog::category_tree( $root ) );
			}
		}
		return $terms;
	}

	/**
	 * @param WP_Post $post Product post.
	 */
	public static function render( $post ) {
		$product = wc_get_product( $post->ID );
		$current = $product ? AIMP_Notions::kind_of( $product ) : '';
		$get     = function ( $key ) use ( $product ) {
			return $product ? (string) $product->get_meta( $key ) : '';
		};
		$fields  = AIMP_Notions::fields();
		$hints   = self::suggestions();
		$ideal   = $product ? AIMP_Notions::ideal_for( $product ) : array();
		?>
		<div class="aimp-notion-details" data-kind-terms="<?php echo esc_attr( wp_json_encode( (object) self::kind_terms() ) ); ?>">
			<input type="hidden" name="aimp_hab_present" value="1">
			<p class="description"><?php esc_html_e( 'Only used for haberdashery (the Buttons, Zips, Interfacing, Elastic, Ribbons & cords and Bias tape categories). The type and the size decide in which patterns the product is offered in the configurator.', 'atelier-irisee-master-plugin' ); ?></p>

			<div class="aimp-notion-order-info">
				<h4><label for="aimp_hab_order_info"><?php esc_html_e( 'Order information', 'atelier-irisee-master-plugin' ); ?></label></h4>
				<p class="description"><?php esc_html_e( 'Shown under the price bar, next to the picture.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php
				wp_editor(
					$get( AIMP_Catalog::META_ORDER_INFO ),
					'aimp_hab_order_info',
					array(
						'textarea_name' => 'aimp_hab[order_info]',
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny'         => true,
					)
				);
				?>
			</div>

			<?php foreach ( AIMP_Notions::kinds() as $kind => $info ) : ?>
				<?php
				$is_current = $kind === $current;
				$base       = 'aimp_hab[' . $kind . ']';
				$id         = 'aimp_hab_' . $kind . '_';
				$sold       = $is_current ? AIMP_Notions::sold( $product ) : ( 'piece' === $info['measure'] ? 'piece' : '10cm' );
				$specs      = array_values( array_diff( $info['specs'], 'length' === $info['measure'] ? array( 'length' ) : array() ) );
				?>
				<div class="aimp-notion-kind" data-kind="<?php echo esc_attr( $kind ); ?>"<?php echo $is_current ? '' : ' hidden'; ?>>
					<fieldset class="aimp-notion-specs">
						<legend><?php echo esc_html( sprintf( /* translators: %s: kind, e.g. "Zips" */ __( 'Specifications: %s', 'atelier-irisee-master-plugin' ), $info['name'] ) ); ?></legend>
						<?php foreach ( $specs as $field ) : ?>
							<p>
								<label for="<?php echo esc_attr( $id . $field ); ?>"><?php echo esc_html( self::field_label( $kind, $field ) ); ?></label>
								<?php if ( 'type' === $field ) : ?>
									<?php $type = $is_current ? AIMP_Notions::type_of( $product, $kind ) : $info['default_type']; ?>
									<select id="<?php echo esc_attr( $id . $field ); ?>" name="<?php echo esc_attr( $base . '[type]' ); ?>">
										<?php if ( '' === $info['default_type'] ) : ?>
											<option value=""><?php esc_html_e( '— Choose —', 'atelier-irisee-master-plugin' ); ?></option>
										<?php endif; ?>
										<?php foreach ( $info['types'] as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ( in_array( $field, array( 'diameter', 'width', 'length' ), true ) ) : ?>
									<input type="number" id="<?php echo esc_attr( $id . $field ); ?>" name="<?php echo esc_attr( $base . '[' . $field . ']' ); ?>" min="0" step="<?php echo 'mm' === AIMP_Notions::unit( $kind, $field ) ? '0.5' : '0.1'; ?>" value="<?php echo esc_attr( $is_current ? $get( $fields[ $field ][0] ) : '' ); ?>" placeholder="<?php echo esc_attr( self::example( $kind, $field ) ); ?>">
								<?php else : ?>
									<input type="text" id="<?php echo esc_attr( $id . $field ); ?>" name="<?php echo esc_attr( $base . '[' . $field . ']' ); ?>"<?php echo isset( $hints[ $field ] ) ? ' list="' . esc_attr( 'aimp_hab_' . $field . '_list' ) . '"' : ''; ?> value="<?php echo esc_attr( $is_current ? $get( $fields[ $field ][0] ) : '' ); ?>" placeholder="<?php echo esc_attr( self::example( $kind, $field ) ); ?>">
								<?php endif; ?>
							</p>
						<?php endforeach; ?>
					</fieldset>

					<fieldset class="aimp-notion-sold">
						<legend><?php esc_html_e( 'Sold per', 'atelier-irisee-master-plugin' ); ?></legend>
						<p class="aimp-notion-sold-choice">
							<?php if ( 'piece' === $info['measure'] ) : ?>
								<?php $size = $is_current ? AIMP_Notions::set_size( $product ) : 1; ?>
								<label><input type="radio" name="<?php echo esc_attr( $base . '[sold]' ); ?>" value="piece"<?php checked( 'piece', $sold ); ?>> <?php esc_html_e( 'Per piece', 'atelier-irisee-master-plugin' ); ?></label>
								<label>
									<input type="radio" name="<?php echo esc_attr( $base . '[sold]' ); ?>" value="set"<?php checked( 'set', $sold ); ?>>
									<?php esc_html_e( 'Per set of', 'atelier-irisee-master-plugin' ); ?>
									<input type="number" class="small-text" data-sold="set" name="<?php echo esc_attr( $base . '[set_size]' ); ?>" min="2" step="1" value="<?php echo $size > 1 ? esc_attr( $size ) : ''; ?>" aria-label="<?php esc_attr_e( 'Pieces in a set', 'atelier-irisee-master-plugin' ); ?>">
									<?php esc_html_e( 'pieces', 'atelier-irisee-master-plugin' ); ?>
								</label>
							<?php else : ?>
								<?php $roll = $is_current ? AIMP_Notions::number( $product, 'length' ) : 0; ?>
								<label><input type="radio" name="<?php echo esc_attr( $base . '[sold]' ); ?>" value="10cm"<?php checked( '10cm', $sold ); ?>> <?php esc_html_e( 'Per 10 cm', 'atelier-irisee-master-plugin' ); ?></label>
								<label>
									<input type="radio" name="<?php echo esc_attr( $base . '[sold]' ); ?>" value="roll"<?php checked( 'roll', $sold ); ?>>
									<?php esc_html_e( 'Per piece/roll of', 'atelier-irisee-master-plugin' ); ?>
									<input type="number" class="small-text" data-sold="roll" name="<?php echo esc_attr( $base . '[length]' ); ?>" min="1" step="1" value="<?php echo $roll > 0 ? esc_attr( wc_format_decimal( $roll, 1, true ) ) : ''; ?>" aria-label="<?php esc_attr_e( 'Length of a roll in cm', 'atelier-irisee-master-plugin' ); ?>">
									<?php esc_html_e( 'cm', 'atelier-irisee-master-plugin' ); ?>
								</label>
							<?php endif; ?>
						</p>
						<p class="description">
							<?php
							if ( 'piece' === $info['measure'] ) {
								esc_html_e( 'Per set: the price and the stock are per set, and one sale lowers the stock by one set. The customer sees "per set of 6"; the configurator adds as many whole sets as a pattern size needs.', 'atelier-irisee-master-plugin' );
							} else {
								esc_html_e( 'Per 10 cm: price and stock per 10 cm, like fabric (a 5 m roll = stock 50). Per piece/roll: price and stock per roll of the length you fill in; the configurator adds as many whole rolls as a pattern size needs.', 'atelier-irisee-master-plugin' );
							}
							?>
						</p>
					</fieldset>
				</div>
			<?php endforeach; ?>

			<?php foreach ( $hints as $field => $list ) : ?>
				<datalist id="<?php echo esc_attr( 'aimp_hab_' . $field . '_list' ); ?>">
					<?php foreach ( $list as $hint ) : ?>
						<option value="<?php echo esc_attr( $hint ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
			<?php endforeach; ?>

			<fieldset class="aimp-notion-ideal">
				<legend><?php esc_html_e( 'Ideal for', 'atelier-irisee-master-plugin' ); ?></legend>
				<p class="description"><?php esc_html_e( 'Up to four kinds of clothes, each shown with its own icon on the product page under "Ideal for".', 'atelier-irisee-master-plugin' ); ?></p>
				<div class="aimp-notion-ideal-list">
					<?php for ( $n = 0; $n < AIMP_Notions::IDEAL_FOR_MAX; $n++ ) : ?>
						<select name="aimp_hab[ideal_for][]" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: choice number */ __( 'Ideal for, choice %d', 'atelier-irisee-master-plugin' ), $n + 1 ) ); ?>">
							<option value=""><?php esc_html_e( '— None —', 'atelier-irisee-master-plugin' ); ?></option>
							<?php foreach ( AIMP_Notions::ideal_for_options() as $key => $label ) : ?>
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
		if ( empty( $_POST['aimp_hab_present'] ) || ! isset( $_POST['aimp_hab'] ) || ! is_array( $_POST['aimp_hab'] ) ) {
			return;
		}
		$input = wp_unslash( $_POST['aimp_hab'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$cats  = isset( $_POST['tax_input']['product_cat'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['tax_input']['product_cat'] ) ) : wc_get_product_cat_ids( $product->get_id() );
		// phpcs:enable
		// The kind from the ticked categories (the box is hidden for other products, but still sent).
		$kind = '';
		foreach ( AIMP_Notions::kinds() as $key => $info ) {
			$root = (int) AIMP_Settings::get( $info['setting'] );
			if ( $root && array_intersect( $cats, AIMP_Catalog::category_tree( $root ) ) ) {
				$kind = $key;
				break;
			}
		}
		if ( '' === $kind ) {
			return;
		}
		$info   = AIMP_Notions::kind( $kind );
		$row    = isset( $input[ $kind ] ) && is_array( $input[ $kind ] ) ? $input[ $kind ] : array();
		$fields = AIMP_Notions::fields();
		$set    = function ( $key, $value ) use ( $product ) {
			if ( '' === $value || array() === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		};
		$number = function ( $value ) {
			$value = wc_clean( (string) $value );
			return '' === $value || (float) $value <= 0 ? '' : wc_format_decimal( (float) $value, 1, true );
		};

		$info_text = isset( $input['order_info'] ) ? wp_kses_post( $input['order_info'] ) : '';
		$set( AIMP_Catalog::META_ORDER_INFO, '' === trim( wp_strip_all_tags( $info_text ) ) ? '' : $info_text );

		foreach ( $fields as $field => $field_info ) {
			if ( ! in_array( $field, $info['specs'], true ) && ! ( 'length' === $field && 'length' === $info['measure'] ) ) {
				$product->delete_meta_data( $field_info[0] ); // Not a field of this kind.
				continue;
			}
			$value = isset( $row[ $field ] ) ? $row[ $field ] : '';
			if ( 'type' === $field ) {
				$value = sanitize_key( $value );
				$set( $field_info[0], isset( $info['types'][ $value ] ) ? $value : $info['default_type'] );
			} elseif ( in_array( $field, array( 'diameter', 'width', 'length' ), true ) ) {
				$set( $field_info[0], $number( $value ) );
			} else {
				$set( $field_info[0], sanitize_text_field( $value ) );
			}
		}

		$sold = isset( $row['sold'] ) ? sanitize_key( $row['sold'] ) : '';
		if ( 'piece' === $info['measure'] ) {
			$size = isset( $row['set_size'] ) ? absint( $row['set_size'] ) : 0;
			$sold = 'set' === $sold && $size > 1 ? 'set' : 'piece';
			$set( AIMP_Notions::META_SET_SIZE, 'set' === $sold ? (string) $size : '' );
		} else {
			$sold = 'roll' === $sold && '' !== $number( isset( $row['length'] ) ? $row['length'] : '' ) ? 'roll' : '10cm';
			$product->delete_meta_data( AIMP_Notions::META_SET_SIZE );
		}
		$product->update_meta_data( AIMP_Notions::META_SOLD, $sold );

		$ideal = isset( $input['ideal_for'] ) ? array_map( 'sanitize_key', (array) $input['ideal_for'] ) : array();
		$ideal = array_slice( array_values( array_unique( array_intersect( $ideal, array_keys( AIMP_Notions::ideal_for_options() ) ) ) ), 0, AIMP_Notions::IDEAL_FOR_MAX );
		$set( AIMP_Notions::META_IDEAL_FOR, $ideal );
	}

	/**
	 * Cart and checkout: "Sold per: Set of 6" or "Roll of 3 m".
	 *
	 * @param array $item_data Lines.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function item_data( $item_data, $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( $product instanceof WC_Product && in_array( AIMP_Notions::sold( $product ), array( 'set', 'roll' ), true ) ) {
			$item_data[] = array(
				'key'   => __( 'Sold per', 'atelier-irisee-master-plugin' ),
				'value' => AIMP_Notions::sold_text( $product ),
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
		$product = isset( $values['data'] ) ? $values['data'] : null;
		if ( $product instanceof WC_Product && in_array( AIMP_Notions::sold( $product ), array( 'set', 'roll' ), true ) ) {
			$item->add_meta_data( __( 'Sold per', 'atelier-irisee-master-plugin' ), AIMP_Notions::sold_text( $product ), true );
		}
	}

	/**
	 * The kind whose category the product list shows (Products → All buttons, All zips, …), or ''.
	 *
	 * @return string
	 */
	private static function list_kind() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which list is open.
		$slug = isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( $_GET['product_cat'] ) ) : '';
		if ( '' === $slug ) {
			return '';
		}
		foreach ( AIMP_Notions::kinds() as $kind => $info ) {
			$term = get_term( AIMP_Settings::get( $info['setting'] ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) && $term->slug === $slug ) {
				return $kind;
			}
		}
		return '';
	}

	/**
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$kind = self::list_kind();
		if ( '' === $kind ) {
			return $columns;
		}
		$info   = AIMP_Notions::kind( $kind );
		$fields = AIMP_Notions::fields();
		$new    = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				if ( $info['types'] ) {
					$new['aimp_hab_type'] = __( 'Type', 'atelier-irisee-master-plugin' );
				}
				$new['aimp_hab_size'] = $fields[ $info['main'] ][1];
				$new['aimp_hab_sold'] = __( 'Sold per', 'atelier-irisee-master-plugin' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Product.
	 */
	public static function column( $column, $post_id ) {
		if ( ! in_array( $column, array( 'aimp_hab_type', 'aimp_hab_size', 'aimp_hab_sold' ), true ) ) {
			return;
		}
		$product = wc_get_product( $post_id );
		$kind    = $product ? AIMP_Notions::kind_of( $product ) : '';
		if ( '' === $kind ) {
			echo '–';
			return;
		}
		if ( 'aimp_hab_type' === $column ) {
			$text = AIMP_Notions::field_text( $product, 'type', $kind );
		} elseif ( 'aimp_hab_size' === $column ) {
			$text = AIMP_Notions::main_text( $product );
		} else {
			$text = AIMP_Notions::sold_text( $product );
		}
		echo esc_html( '' !== $text ? $text : '–' );
	}

	/**
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['aimp_hab_type'] = 'aimp_hab_type';
		$columns['aimp_hab_size'] = 'aimp_hab_size';
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
		if ( 'aimp_hab_type' === $orderby ) {
			$query->set( 'meta_key', AIMP_Notions::META_TYPE ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'aimp_hab_size' === $orderby ) {
			$kind   = self::list_kind();
			$info   = '' !== $kind ? AIMP_Notions::kind( $kind ) : null;
			$fields = AIMP_Notions::fields();
			$query->set( 'meta_key', $fields[ $info ? $info['main'] : 'diameter' ][0] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
}
