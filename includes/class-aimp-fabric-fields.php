<?php
/**
 * "Fabric texts" box on fabric products: inspiration, order information, specifications and washing
 * instructions. Each subject is its own field, so the product page can place each text separately.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Fabric_Fields {

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_box' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_recommendation' ) );
		// Quick edit in the product list: "This fabric can also be used as lining".
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'quick_edit_data' ), 20, 2 );
		add_action( 'woocommerce_product_quick_edit_end', array( __CLASS__, 'render_quick_edit' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'save_quick_edit' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'quick_edit_script' ) );
	}

	/**
	 * The current lining state of each row, read by the quick edit script (like WooCommerce's own inline data).
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public static function quick_edit_data( $column, $post_id ) {
		if ( 'name' !== $column ) {
			return;
		}
		$fabric = AIMP_Catalog::in_categories( $post_id, AIMP_Catalog::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) );
		printf(
			'<div class="hidden" id="aimp_inline_%1$d" data-fabric="%2$d" data-lining="%3$d"></div>',
			(int) $post_id,
			$fabric ? 1 : 0,
			'yes' === get_post_meta( $post_id, AIMP_Catalog::META_LINING, true ) ? 1 : 0
		);
	}

	/**
	 * The checkbox in the quick edit panel; the script shows it for fabrics only.
	 */
	public static function render_quick_edit() {
		?>
		<div class="inline-edit-group aimp-quick-lining" style="display:none">
			<input type="hidden" name="aimp_quick_lining_shown" value="0">
			<label class="alignleft">
				<input type="checkbox" name="aimp_quick_lining" value="1">
				<span class="checkbox-title"><?php esc_html_e( 'This fabric can also be used as lining', 'atelier-irisee-master-plugin' ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Runs inside WooCommerce's quick edit save (WordPress verified the inline-edit nonce). Only when the
	 * checkbox was shown, so other products are never changed.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_quick_edit( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WordPress before this hook.
		if ( empty( $_POST['aimp_quick_lining_shown'] ) ) {
			return;
		}
		$lining = ! empty( $_POST['aimp_quick_lining'] );
		// phpcs:enable
		if ( $lining ) {
			$product->update_meta_data( AIMP_Catalog::META_LINING, 'yes' );
		} else {
			$product->delete_meta_data( AIMP_Catalog::META_LINING );
		}
		$product->save();
	}

	/**
	 * Fills the quick edit checkbox from the row's data when the panel opens.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function quick_edit_script( $hook_suffix ) {
		global $typenow;
		if ( 'edit.php' !== $hook_suffix || 'product' !== $typenow ) {
			return;
		}
		wp_add_inline_script(
			'inline-edit-post',
			"jQuery(function ($) {
				if (!window.inlineEditPost) {
					return;
				}
				var edit = inlineEditPost.edit;
				inlineEditPost.edit = function (id) {
					edit.apply(this, arguments);
					var postId = typeof id === 'object' ? this.getId(id) : id;
					var data = $('#aimp_inline_' + postId);
					var box = $('#edit-' + postId + ' .aimp-quick-lining');
					var fabric = data.data('fabric') === 1;
					box.toggle(fabric);
					box.find('[name=\"aimp_quick_lining_shown\"]').val(fabric ? '1' : '0');
					box.find('[name=\"aimp_quick_lining\"]').prop('checked', data.data('lining') === 1);
				};
			});"
		);
	}

	public static function add_box() {
		add_meta_box( 'aimp-fabric-texts', __( 'Fabric texts', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render' ), 'product', 'normal', 'high' );
		add_meta_box( 'aimp-recommendation', __( 'Atelier Irisee recommendation', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_recommendation' ), 'product', 'side', 'default' );
	}

	/**
	 * Placeholders that show what to fill in.
	 *
	 * @return array subject => example
	 */
	private static function examples() {
		return array(
			'composition' => __( 'e.g. 100% cotton', 'atelier-irisee-master-plugin' ),
			'type'        => __( 'e.g. poplin', 'atelier-irisee-master-plugin' ),
			'colour'      => __( 'e.g. ochre yellow', 'atelier-irisee-master-plugin' ),
			'width'       => __( 'e.g. 145 cm', 'atelier-irisee-master-plugin' ),
			'weight'      => __( 'e.g. 180 g/m²', 'atelier-irisee-master-plugin' ),
			'certification' => __( 'Optional, e.g. OEKO-TEX® Standard 100. Not shown when empty.', 'atelier-irisee-master-plugin' ),
			'washing'     => __( 'e.g. 30 °C, gentle cycle', 'atelier-irisee-master-plugin' ),
			'drying'      => __( 'e.g. do not tumble dry', 'atelier-irisee-master-plugin' ),
			'ironing'     => __( 'e.g. medium heat, inside out', 'atelier-irisee-master-plugin' ),
			'tips'        => __( 'Optional. Not shown when empty.', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * @param WP_Post $post Product post.
	 */
	public static function render( $post ) {
		$product  = wc_get_product( $post->ID );
		$tree     = AIMP_Catalog::category_tree( AIMP_Settings::get( 'fabric_cat' ) );
		$examples = self::examples();
		$get      = function ( $key ) use ( $product ) {
			return $product ? (string) $product->get_meta( $key ) : '';
		};
		$groups   = array(
			'specs'   => __( 'Specifications', 'atelier-irisee-master-plugin' ),
			'washing' => __( 'Washing instructions', 'atelier-irisee-master-plugin' ),
		);
		?>
		<div class="aimp-fabric-texts" data-fabric-terms="<?php echo esc_attr( wp_json_encode( array_values( $tree ) ) ); ?>">
			<input type="hidden" name="aimp_fabric_texts" value="1">
			<p class="description"><?php esc_html_e( 'Only used for fabrics. Each text has its own place on the product page.', 'atelier-irisee-master-plugin' ); ?></p>

			<h4><?php esc_html_e( 'Inspiration', 'atelier-irisee-master-plugin' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Up to three cards with a title and a text, shown side by side under the picture. Empty cards are not shown.', 'atelier-irisee-master-plugin' ); ?></p>
			<div class="aimp-fabric-cards">
				<?php
				$cards = $product ? AIMP_Catalog::inspiration_cards( $product, true ) : array();
				for ( $n = 1; $n <= AIMP_Catalog::INSPIRATION_CARDS; $n++ ) :
					$card = isset( $cards[ $n - 1 ] ) ? $cards[ $n - 1 ] : array(
						'title' => '',
						'text'  => '',
					);
					?>
					<div class="aimp-fabric-card">
						<p>
							<label for="<?php echo esc_attr( 'aimp_insp_' . $n . '_title' ); ?>">
								<?php
								/* translators: %d: card number */
								echo esc_html( sprintf( __( 'Card %d: title', 'atelier-irisee-master-plugin' ), $n ) );
								?>
							</label>
							<input type="text" id="<?php echo esc_attr( 'aimp_insp_' . $n . '_title' ); ?>" name="<?php echo esc_attr( 'aimp_fabric[insp_' . $n . '_title]' ); ?>" value="<?php echo esc_attr( $card['title'] ); ?>">
						</p>
						<?php
						wp_editor(
							$card['text'],
							'aimp_insp_' . $n . '_text',
							array(
								'textarea_name' => 'aimp_fabric[insp_' . $n . '_text]',
								'textarea_rows' => 4,
								'media_buttons' => false,
								'teeny'         => true,
							)
						);
						?>
					</div>
				<?php endfor; ?>
			</div>

			<div class="aimp-fabric-editors">
				<div>
					<h4><label for="aimp_order_info"><?php esc_html_e( 'Order information', 'atelier-irisee-master-plugin' ); ?></label></h4>
					<p class="description"><?php esc_html_e( 'Shown under the price bar, next to the picture.', 'atelier-irisee-master-plugin' ); ?></p>
					<?php
					wp_editor(
						$get( AIMP_Catalog::META_ORDER_INFO ),
						'aimp_order_info',
						array(
							'textarea_name' => 'aimp_fabric[order_info]',
							'textarea_rows' => 5,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
					?>
				</div>
			</div>

			<div class="aimp-fabric-lists">
				<?php foreach ( AIMP_Catalog::fabric_text_fields() as $group => $fields ) : ?>
					<fieldset>
						<legend><?php echo esc_html( $groups[ $group ] ); ?></legend>
						<?php if ( 'specs' === $group ) : ?>
							<?php // In the specifications (the product workspace moves this fieldset into its own card). ?>
							<p class="aimp-fabric-lining">
								<label><input type="checkbox" name="aimp_fabric[lining]" value="1"<?php checked( 'yes', $get( AIMP_Catalog::META_LINING ) ); ?>> <strong><?php esc_html_e( 'This fabric can also be used as lining', 'atelier-irisee-master-plugin' ); ?></strong></label>
								<br><span class="description"><?php esc_html_e( 'It then also shows under Lining in the shop and in the lining step of the customizer: the same product, price and stock.', 'atelier-irisee-master-plugin' ); ?></span>
							</p>
						<?php endif; ?>
						<?php if ( 'specs' === $group ) : ?>
							<?php self::render_fits( $product ); ?>
						<?php endif; ?>
						<?php foreach ( $fields as $subject => $field ) : ?>
							<p>
								<label for="<?php echo esc_attr( 'aimp_' . $group . '_' . $subject ); ?>"><?php echo esc_html( $field[1] ); ?></label>
								<?php if ( 'tips' === $subject ) : ?>
									<textarea id="<?php echo esc_attr( 'aimp_' . $group . '_' . $subject ); ?>" name="<?php echo esc_attr( 'aimp_fabric[' . $subject . ']' ); ?>" rows="2" placeholder="<?php echo esc_attr( $examples[ $subject ] ); ?>"><?php echo esc_textarea( $get( $field[0] ) ); ?></textarea>
								<?php else : ?>
									<input type="text" id="<?php echo esc_attr( 'aimp_' . $group . '_' . $subject ); ?>" name="<?php echo esc_attr( 'aimp_fabric[' . $subject . ']' ); ?>" value="<?php echo esc_attr( $get( $field[0] ) ); ?>" placeholder="<?php echo esc_attr( $examples[ $subject ] ); ?>">
								<?php endif; ?>
							</p>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Lining and interfacing for this fabric: the lining fabrics that fit it (the customizer's lining step shows
	 * only these), and a normal and a volume interfacing (the customizer's interfacing step).
	 *
	 * @param WC_Product|false $product Fabric.
	 */
	private static function render_fits( $product ) {
		$id       = $product ? $product->get_id() : 0;
		$fit      = $product ? (array) $product->get_meta( AIMP_Catalog::META_FIT_LININGS ) : array();
		$fit      = array_map( 'absint', $fit );
		$linings  = self::lining_choices( $id );
		$interfac = self::interfacing_choices();
		?>
		<div class="aimp-fabric-fits">
			<p>
				<label for="aimp_fit_linings"><strong><?php esc_html_e( 'Linings that fit this fabric', 'atelier-irisee-master-plugin' ); ?></strong></label>
				<select id="aimp_fit_linings" class="wc-enhanced-select" multiple="multiple" style="width:100%" name="aimp_fabric[fit_linings][]" data-placeholder="<?php esc_attr_e( 'Every lining fabric', 'atelier-irisee-master-plugin' ); ?>">
					<?php foreach ( $linings as $lining_id => $name ) : ?>
						<option value="<?php echo (int) $lining_id; ?>"<?php selected( in_array( (int) $lining_id, $fit, true ) ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="description"><?php echo esc_html( $linings ? __( 'The lining step of the customizer offers only these with this fabric. None chosen: every lining fabric is offered.', 'atelier-irisee-master-plugin' ) : __( 'Tick "This fabric can also be used as lining" on your lining fabrics first.', 'atelier-irisee-master-plugin' ) ); ?></span>
			</p>
			<?php
			foreach ( array(
				'interfacing_normal' => array( AIMP_Catalog::META_INTERFACING, __( 'Normal interfacing', 'atelier-irisee-master-plugin' ) ),
				'interfacing_volume' => array( AIMP_Catalog::META_INTERFACING_VOL, __( 'Volume interfacing', 'atelier-irisee-master-plugin' ) ),
			) as $key => $slot ) :
				$current = $product ? absint( $product->get_meta( $slot[0] ) ) : 0;
				?>
				<p>
					<label for="<?php echo esc_attr( 'aimp_' . $key ); ?>"><strong><?php echo esc_html( $slot[1] ); ?></strong></label>
					<select id="<?php echo esc_attr( 'aimp_' . $key ); ?>" class="wc-enhanced-select" style="width:100%" name="<?php echo esc_attr( 'aimp_fabric[' . $key . ']' ); ?>">
						<option value="0"><?php esc_html_e( '— None —', 'atelier-irisee-master-plugin' ); ?></option>
						<?php foreach ( $interfac as $choice_id => $name ) : ?>
							<option value="<?php echo (int) $choice_id; ?>"<?php selected( $current, $choice_id ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'In the customizer the customer chooses between these (or their own interfacing) when the pattern needs interfacing. Both empty: the customer picks from all interfacings that fit the pattern.', 'atelier-irisee-master-plugin' ); ?></p>
		</div>
		<?php
	}

	/**
	 * The lining fabrics, except this fabric: ID => name.
	 *
	 * @param int $exclude This fabric.
	 * @return array
	 */
	private static function lining_choices( $exclude ) {
		$choices = array();
		foreach ( self::products_in( AIMP_Settings::get( 'fabric_cat' ) ) as $id => $name ) {
			if ( $id !== (int) $exclude && 'yes' === get_post_meta( $id, AIMP_Catalog::META_LINING, true ) ) {
				$choices[ $id ] = $name;
			}
		}
		return $choices;
	}

	/**
	 * The interfacings (not the shoulder pads): ID => name.
	 *
	 * @return array
	 */
	private static function interfacing_choices() {
		$choices = array();
		foreach ( self::products_in( AIMP_Settings::get( 'interfacing_cat' ) ) as $id => $name ) {
			$item = wc_get_product( $id );
			if ( $item && 'shoulder_pad' !== AIMP_Notions::type_of( $item, 'interfacing' ) ) {
				$choices[ $id ] = $name;
			}
		}
		return $choices;
	}

	/**
	 * Side box on every product: the product shown in the recommendation box on its page.
	 * Patterns recommend a fabric; every other product recommends a pattern.
	 *
	 * @param WP_Post $post Product post.
	 */
	public static function render_recommendation( $post ) {
		$is_pattern = (bool) AIMP_Catalog::get_pattern( $post->ID );
		$current    = absint( get_post_meta( $post->ID, AIMP_Catalog::META_RECOMMENDED, true ) );
		$choices    = $is_pattern ? self::fabric_choices() : self::pattern_choices();
		?>
		<input type="hidden" name="aimp_recommendation_present" value="1">
		<p>
			<label for="aimp_recommended"><?php echo esc_html( $is_pattern ? __( 'Recommended fabric', 'atelier-irisee-master-plugin' ) : __( 'Recommended pattern', 'atelier-irisee-master-plugin' ) ); ?></label>
			<select id="aimp_recommended" name="aimp_recommended" style="width:100%">
				<option value="0"><?php esc_html_e( '— None —', 'atelier-irisee-master-plugin' ); ?></option>
				<?php foreach ( $choices as $choice_id => $choice_name ) : ?>
					<option value="<?php echo (int) $choice_id; ?>" <?php selected( $current, $choice_id ); ?>><?php echo esc_html( $choice_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Shown next to the product picture, with a button to the configurator. Patterns recommend a fabric, other products a pattern (save the product after changing its category).', 'atelier-irisee-master-plugin' ); ?></p>
		<?php
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public static function save_recommendation( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( empty( $_POST['aimp_recommendation_present'] ) ) {
			return;
		}
		$id = isset( $_POST['aimp_recommended'] ) ? absint( $_POST['aimp_recommended'] ) : 0;
		// phpcs:enable
		$valid = $id && array_key_exists( $id, AIMP_Catalog::get_pattern( $product->get_id() ) ? self::fabric_choices() : self::pattern_choices() );
		if ( $valid ) {
			$product->update_meta_data( AIMP_Catalog::META_RECOMMENDED, $id );
		} else {
			$product->delete_meta_data( AIMP_Catalog::META_RECOMMENDED );
		}
	}

	/**
	 * All published fabrics: ID => name.
	 *
	 * @return array
	 */
	private static function fabric_choices() {
		return self::products_in( AIMP_Settings::get( 'fabric_cat' ) );
	}

	/**
	 * All published patterns, and each design of a pack ("Lison – Jurk"): ID => name.
	 *
	 * @return array
	 */
	private static function pattern_choices() {
		$choices = array();
		foreach ( self::products_in( AIMP_Settings::get( 'pattern_cat' ) ) as $id => $name ) {
			$choices[ $id ] = $name;
			// A pack with designs: each design can be recommended too (saved as the design's first size).
			$pattern = wc_get_product( $id );
			$first   = $pattern ? AIMP_Catalog::design_first_sizes( $pattern ) : array();
			$names   = $first ? AIMP_Catalog::designs( $pattern ) : array();
			foreach ( $first as $slug => $variation_id ) {
				$choices[ (int) $variation_id ] = $name . ' – ' . ( isset( $names[ $slug ] ) ? $names[ $slug ] : $slug );
			}
		}
		return $choices;
	}

	/**
	 * Published products in a category tree: ID => name, alphabetical.
	 *
	 * @param int $root Category.
	 * @return array
	 */
	private static function products_in( $root ) {
		if ( ! $root ) {
			return array();
		}
		$query   = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => array( $root ),
						'include_children' => true,
					),
				),
			)
		);
		$choices = array();
		foreach ( $query->posts as $id ) {
			$choices[ (int) $id ] = wp_strip_all_tags( get_the_title( $id ) );
		}
		return $choices;
	}

	/**
	 * Runs inside WooCommerce's product save, which already verified its nonce.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( empty( $_POST['aimp_fabric_texts'] ) || ! isset( $_POST['aimp_fabric'] ) || ! is_array( $_POST['aimp_fabric'] ) ) {
			return;
		}
		$input = wp_unslash( $_POST['aimp_fabric'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		// phpcs:enable

		$set = function ( $key, $value ) use ( $product ) {
			if ( '' === trim( wp_strip_all_tags( $value ) ) ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		};

		for ( $n = 1; $n <= AIMP_Catalog::INSPIRATION_CARDS; $n++ ) {
			list( $title_key, $text_key ) = AIMP_Catalog::inspiration_keys( $n );
			$set( $title_key, isset( $input[ 'insp_' . $n . '_title' ] ) ? sanitize_text_field( $input[ 'insp_' . $n . '_title' ] ) : '' );
			$set( $text_key, isset( $input[ 'insp_' . $n . '_text' ] ) ? wp_kses_post( $input[ 'insp_' . $n . '_text' ] ) : '' );
		}
		// The single inspiration text from before the cards now lives in card 1.
		$product->delete_meta_data( AIMP_Catalog::META_INSPIRATION );
		$set( AIMP_Catalog::META_ORDER_INFO, isset( $input['order_info'] ) ? wp_kses_post( $input['order_info'] ) : '' );
		$set( AIMP_Catalog::META_LINING, empty( $input['lining'] ) ? '' : 'yes' );
		// Lining and interfacing for this fabric: only ids from the lists shown.
		$fit = array_values( array_intersect( array_map( 'absint', isset( $input['fit_linings'] ) ? (array) $input['fit_linings'] : array() ), array_keys( self::lining_choices( $product->get_id() ) ) ) );
		if ( $fit ) {
			$product->update_meta_data( AIMP_Catalog::META_FIT_LININGS, $fit );
		} else {
			$product->delete_meta_data( AIMP_Catalog::META_FIT_LININGS );
		}
		$interfacings = self::interfacing_choices();
		foreach ( array( 'interfacing_normal' => AIMP_Catalog::META_INTERFACING, 'interfacing_volume' => AIMP_Catalog::META_INTERFACING_VOL ) as $key => $meta ) {
			$choice = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
			if ( $choice && isset( $interfacings[ $choice ] ) ) {
				$product->update_meta_data( $meta, $choice );
			} else {
				$product->delete_meta_data( $meta );
			}
		}

		foreach ( AIMP_Catalog::fabric_text_fields() as $fields ) {
			foreach ( $fields as $subject => $field ) {
				$value = isset( $input[ $subject ] ) ? (string) $input[ $subject ] : '';
				$set( $field[0], 'tips' === $subject ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
			}
		}
	}
}
