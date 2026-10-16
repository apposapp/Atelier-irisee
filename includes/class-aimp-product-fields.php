<?php
/**
 * Admin product fields: size requirements on pattern variations, length on zips.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Product_Fields {

	/** Price of a pattern, the same for every size (written to the sizes on save). */
	const META_PRICE      = '_aimp_pattern_price';
	const META_SALE_PRICE = '_aimp_pattern_sale_price';

	/** True while sync_pattern() saves sizes and the pattern (prevents a save loop). */
	private static $syncing = false;

	public static function init() {
		// One price and one stock for all sizes of a pattern.
		add_action( 'woocommerce_update_product', array( __CLASS__, 'sync_pattern' ), 20 );
		add_action( 'woocommerce_ajax_save_product_variations', array( __CLASS__, 'sync_pattern' ), 20 );

		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'save_variation_fields' ), 10, 2 );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_pattern_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_pattern_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_fabric_priority' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook_suffix ) {
		global $typenow;
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || 'product' !== $typenow ) {
			return;
		}
		wp_enqueue_style( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/css/admin.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), AIMP_VERSION, true );
	}

	/**
	 * A pack with designs: the sizes are ordered per design (all sizes of the first design, then the next),
	 * each design in the order of the sizes. The Variations tab and the configurator follow this order.
	 *
	 * @param WC_Product $product Pattern.
	 */
	private static function sort_by_design( $product ) {
		$design_key = AIMP_Catalog::design_attribute( $product );
		$designs    = array_keys( AIMP_Catalog::designs( $product ) );
		if ( '' === $design_key || ! $designs ) {
			return;
		}
		$size_key   = '';
		$size_order = array();
		foreach ( $product->get_attributes() as $key => $attribute ) {
			if ( $key === $design_key || ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_variation() ) {
				continue;
			}
			$size_key   = (string) $key;
			$size_order = $attribute->is_taxonomy()
				? wc_get_product_terms( $product->get_id(), $key, array( 'fields' => 'slugs' ) )
				: array_map( 'sanitize_title', $attribute->get_options() );
			break;
		}
		$rows = array();
		foreach ( $product->get_children() as $index => $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}
			$values = $variation->get_variation_attributes();
			$design = array_search( sanitize_title( isset( $values[ 'attribute_' . $design_key ] ) ? $values[ 'attribute_' . $design_key ] : '' ), $designs, true );
			$size   = '' !== $size_key ? array_search( sanitize_title( isset( $values[ 'attribute_' . $size_key ] ) ? $values[ 'attribute_' . $size_key ] : '' ), $size_order, true ) : false;
			$rows[] = array( false === $design ? 99 : $design, false === $size ? 999 : $size, $index, $variation );
		}
		usort(
			$rows,
			function ( $a, $b ) {
				return array( $a[0], $a[1], $a[2] ) <=> array( $b[0], $b[1], $b[2] );
			}
		);
		foreach ( $rows as $position => $row ) {
			if ( (int) $row[3]->get_menu_order() !== $position ) {
				$row[3]->set_menu_order( $position );
				$row[3]->save();
			}
		}
	}

	/**
	 * Body measurements of a size: meta => label.
	 *
	 * @return array
	 */
	private static function measurement_fields() {
		return array(
			AIMP_Catalog::META_BUST       => __( 'Bust', 'atelier-irisee-master-plugin' ),
			AIMP_Catalog::META_WAIST      => __( 'Waist', 'atelier-irisee-master-plugin' ),
			AIMP_Catalog::META_HIP        => __( 'Hip', 'atelier-irisee-master-plugin' ),
			AIMP_Catalog::META_INSIDE_LEG => __( 'Inside leg', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * Only pattern products get the fields (or every variable product if no Patterns category is set yet).
	 *
	 * @param int $product_id Parent product ID.
	 * @return bool
	 */
	private static function is_pattern( $product_id ) {
		$root = AIMP_Settings::get( 'pattern_cat' );
		return ! $root || AIMP_Catalog::in_categories( $product_id, AIMP_Catalog::category_tree( $root ) );
	}

	/**
	 * Pattern price for the form: the saved one, or else the price of the first size that has one.
	 *
	 * @param WC_Product $product Pattern.
	 * @return array [ regular, sale ]
	 */
	private static function pattern_prices( $product ) {
		$regular = (string) $product->get_meta( self::META_PRICE );
		$sale    = (string) $product->get_meta( self::META_SALE_PRICE );
		if ( '' === $regular ) {
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( $variation && '' !== (string) $variation->get_regular_price( 'edit' ) ) {
					$regular = (string) $variation->get_regular_price( 'edit' );
					$sale    = (string) $variation->get_sale_price( 'edit' );
					break;
				}
			}
		}
		return array( $regular, $sale );
	}

	/**
	 * A pattern is one product: every size gets the pattern price, and no size keeps its own stock, so
	 * all sizes (and "All sizes" sales) use the stock on the pattern's Inventory tab. WooCommerce calls
	 * this "stock managed by the parent product".
	 *
	 * @param int $product_id Pattern ID.
	 */
	public static function sync_pattern( $product_id ) {
		if ( self::$syncing ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) || ! self::is_pattern( $product->get_id() ) ) {
			return;
		}
		$regular = (string) $product->get_meta( self::META_PRICE );
		$sale    = (string) $product->get_meta( self::META_SALE_PRICE );

		self::$syncing = true;
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}
			$changed = false;
			if ( '' !== $regular && ( (string) $variation->get_regular_price( 'edit' ) !== $regular || (string) $variation->get_sale_price( 'edit' ) !== $sale ) ) {
				$variation->set_regular_price( $regular );
				$variation->set_sale_price( $sale );
				$changed = true;
			}
			if ( true === $variation->get_manage_stock( 'edit' ) ) {
				$variation->set_manage_stock( false );
				$changed = true;
			}
			if ( $changed ) {
				$variation->save();
			}
		}
		self::sort_by_design( $product );
		WC_Product_Variable::sync( $product->get_id() );
		$product    = wc_get_product( $product->get_id() );
		$data_store = $product ? $product->get_data_store() : null;
		if ( $data_store && method_exists( $data_store, 'sync_managed_variation_stock_status' ) ) {
			$data_store->sync_managed_variation_stock_status( $product ); // The sizes follow the pattern's stock status.
		}
		self::$syncing = false;
	}

	/**
	 * @param int     $loop           Variation index.
	 * @param array   $variation_data Unused.
	 * @param WP_Post $variation_post Variation post.
	 */
	public static function render_variation_fields( $loop, $variation_data, $variation_post ) {
		if ( ! self::is_pattern( $variation_post->post_parent ) ) {
			return;
		}
		$variation = wc_get_product( $variation_post->ID );
		if ( ! $variation ) {
			return;
		}
		// The materials grouped as in AIMP_Catalog::material_fields(): Fabric, Zips, Buttons, Elastic, …
		$groups = array();
		foreach ( AIMP_Catalog::material_fields() as $key => $field ) {
			$groups[ $field['group'] ][ $key ] = $field;
		}
		$value = function ( $key, $field ) use ( $variation ) {
			$saved = (string) $variation->get_meta( $field['meta'] );
			// Fabric in cm; patterns saved before 3.5 had units of 10 cm.
			if ( 'fabric' === $key && '' === $saved && (int) $variation->get_meta( AIMP_Catalog::META_FABRIC_UNITS ) > 0 ) {
				$saved = (string) ( (int) $variation->get_meta( AIMP_Catalog::META_FABRIC_UNITS ) * AIMP_Catalog::FABRIC_UNIT_CM );
			}
			return '0' === $saved ? '' : $saved;
		};
		// Haberdashery the pattern does not use (Atelier Irisee tab → Haberdashery) can't be filled in; admin.js
		// follows the switches.
		$pattern = wc_get_product( $variation_post->post_parent );
		$design  = self::size_design( $pattern, $variation_post->ID );
		$off     = array();
		$ruled   = array();
		foreach ( self::shown_rules( $pattern, $design ) as $rule => $state ) {
			$material           = AIMP_Notions::rules()[ $rule ][2];
			$ruled[ $material ] = true;
			if ( ! $state['on'] ) {
				$off[ $material ] = true;
			}
		}
		$input = function ( $name, $label, $unit, $current ) use ( $loop, $off ) {
			$id = 'aimp_' . $name . '_' . $loop;
			printf(
				'<label class="aimp-material-field" for="%1$s" data-material="%7$s"%8$s><span>%2$s</span><span class="aimp-material-input"><input type="number" min="0" step="%3$s" id="%1$s" name="%4$s" value="%5$s" placeholder="0"%9$s><em>%6$s</em></span></label>',
				esc_attr( $id ),
				esc_html( $label ),
				'cm' === $unit ? '0.5' : '1',
				esc_attr( 'aimp_material[' . $name . '][' . $loop . ']' ),
				esc_attr( $current ),
				esc_html( 'cm' === $unit ? __( 'cm', 'atelier-irisee-master-plugin' ) : __( 'pcs', 'atelier-irisee-master-plugin' ) ),
				esc_attr( $name ),
				isset( $off[ $name ] ) ? ' hidden' : '',
				isset( $off[ $name ] ) ? ' disabled' : ''
			);
		};
		$note = function ( $fields ) use ( $pattern, $ruled, $design ) {
			$materials = array_keys( array_intersect_key( $fields, $ruled ) );
			if ( ! $materials ) {
				return;
			}
			// What may be filled in: the kinds switched on (Atelier Irisee tab → Haberdashery); admin.js keeps it up to date.
			printf(
				'<p class="aimp-notion-allowed" data-aimp-allowed="%1$s">%2$s</p>',
				esc_attr( implode( ',', $materials ) ),
				esc_html( self::allowed_text( $pattern, $materials, $design ) )
			);
		};
		?>
		<div class="aimp-variation-fields" data-design="<?php echo esc_attr( $design ); ?>">
			<h4><?php esc_html_e( 'Needed materials & sizes', 'atelier-irisee-master-plugin' ); ?></h4>
			<div class="aimp-material-groups">
				<?php foreach ( $groups as $group => $fields ) : ?>
					<?php if ( count( $fields ) > 1 ) : ?>
						<fieldset class="aimp-material-group has-types">
							<legend><?php echo esc_html( $group ); ?></legend>
							<?php
							foreach ( $fields as $key => $field ) {
								$input( $key, $field['label'], $field['unit'], $value( $key, $field ) );
							}
							$note( $fields );
							?>
						</fieldset>
					<?php else : ?>
						<div class="aimp-material-group">
							<?php
							foreach ( $fields as $key => $field ) {
								$input( $key, $field['label'], $field['unit'], $value( $key, $field ) );
							}
							$note( $fields );
							?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
				<input type="hidden" class="aimp-notion-on" name="<?php echo esc_attr( 'aimp_notion_on[' . $loop . ']' ); ?>" value="<?php echo esc_attr( implode( ',', array_keys( array_diff_key( $ruled, $off ) ) ) ); ?>">
				<fieldset class="aimp-material-group aimp-measurement-group has-types">
					<legend><?php esc_html_e( 'Body measurements', 'atelier-irisee-master-plugin' ); ?></legend>
					<?php
					foreach ( self::measurement_fields() as $meta => $label ) {
						$input( ltrim( $meta, '_' ), $label, 'cm', (string) $variation->get_meta( $meta ) );
					}
					?>
				</fieldset>
			</div>
			<p class="aimp-variation-note"><?php esc_html_e( 'Price, stock, height and fitting fabrics are the same for all sizes: set them on the Atelier Irisee tab and the Inventory tab. Empty = not needed.', 'atelier-irisee-master-plugin' ); ?></p>
			<input type="hidden" name="<?php echo esc_attr( 'aimp_fields_present[' . $loop . ']' ); ?>" value="1">
		</div>
		<?php
	}

	/**
	 * Runs inside WooCommerce's variation save, which already verified its nonce.
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @param int                  $i         Variation index.
	 */
	public static function save_variation_fields( $variation, $i ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( ! current_user_can( 'edit_products' ) || empty( $_POST['aimp_fields_present'][ $i ] ) ) {
			return;
		}
		$posted = isset( $_POST['aimp_material'] ) && is_array( $_POST['aimp_material'] ) ? wp_unslash( $_POST['aimp_material'] ) : array();
		// phpcs:enable
		$get = function ( $name ) use ( $posted, $i ) {
			return isset( $posted[ $name ][ $i ] ) ? trim( wc_clean( $posted[ $name ][ $i ] ) ) : '';
		};
		$fields = array();
		foreach ( AIMP_Catalog::material_fields() as $key => $field ) {
			$fields[ $key ] = array( $field['meta'], $field['unit'] );
		}
		foreach ( self::measurement_fields() as $meta => $label ) {
			$fields[ ltrim( $meta, '_' ) ] = array( $meta, 'cm' );
		}
		// Haberdashery switched off on the Atelier Irisee tab (as on the screen, also before the pattern is saved).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		$on_list = isset( $_POST['aimp_notion_on'][ $i ] ) ? array_filter( explode( ',', sanitize_text_field( wp_unslash( $_POST['aimp_notion_on'][ $i ] ) ) ) ) : null;
		$off     = array();
		$parent = wc_get_product( $variation->get_parent_id() );
		foreach ( self::shown_rules( $parent, self::size_design( $parent, $variation->get_id() ) ) as $rule => $state ) {
			$material = AIMP_Notions::rules()[ $rule ][2];
			if ( null === $on_list ? ! $state['on'] : ! in_array( $material, $on_list, true ) ) {
				$off[ $material ] = true;
			}
		}
		foreach ( $fields as $name => $field ) {
			$value = isset( $off[ $name ] ) ? '' : $get( $name );
			if ( '' === $value || (float) $value <= 0 ) {
				$variation->delete_meta_data( $field[0] );
				continue;
			}
			$variation->update_meta_data( $field[0], 'cm' === $field[1] ? wc_format_decimal( (float) $value, 1, true ) : (string) absint( $value ) );
		}
		// Fabric is kept in cm from now on.
		$variation->delete_meta_data( AIMP_Catalog::META_FABRIC_UNITS );
	}

	/**
	 * "Atelier Irisee" tab on variable (pattern) products.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public static function add_pattern_tab( $tabs ) {
		$tabs['aimp_pattern'] = array(
			'label'    => __( 'Atelier Irisee', 'atelier-irisee-master-plugin' ),
			'target'   => 'aimp_pattern_data',
			'class'    => array( 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * The pattern's Atelier Irisee tab: availability, price, details (skill level, sizes, project time, height)
	 * and the fitting fabrics with their order. A pattern saved before 3.2 shows the values of its sizes,
	 * so saving once moves them to the pattern.
	 */
	public static function render_pattern_panel() {
		global $product_object;
		$is_product = $product_object instanceof WC_Product;
		$pattern_id = $is_product ? (int) $product_object->get_id() : 0;
		$priority   = $is_product ? AIMP_Catalog::get_fabric_priority( $product_object ) : array();
		$checked    = $pattern_id ? AIMP_Catalog::pattern_fabric_cats( $pattern_id ) : array();
		$height     = $pattern_id ? AIMP_Catalog::pattern_height( $pattern_id ) : '';
		$terms      = AIMP_Catalog::get_fabric_categories();
		// A pack with designs (a "Design" attribute next to the sizes): fabrics, height, time and pictures per design.
		$designs    = $is_product ? AIMP_Catalog::designs( $product_object ) : array();
		$design_set = $pattern_id ? AIMP_Catalog::design_data( $pattern_id ) : array();
		?>
		<div id="aimp_pattern_data" class="panel woocommerce_options_panel hidden">
			<?php if ( $is_product && $product_object->get_id() && $product_object->is_type( 'variable' ) ) : ?>
				<?php $issue = AIMP_Product_Page::pattern_issue( $product_object ); ?>
				<div class="options_group aimp-availability">
					<p class="<?php echo $issue ? 'aimp-availability--no' : 'aimp-availability--yes'; ?>">
						<?php
						if ( $issue ) {
							/* translators: %s: reason, e.g. "there is no pattern price" */
							echo esc_html( sprintf( __( 'On the website: shown as out of stock, because %s', 'atelier-irisee-master-plugin' ), $issue ) );
						} elseif ( $product_object->get_manage_stock() ) {
							/* translators: %d: stock quantity */
							echo esc_html( sprintf( __( 'On the website: available. Stock: %d, shared by all sizes.', 'atelier-irisee-master-plugin' ), (int) $product_object->get_stock_quantity() ) );
						} else {
							esc_html_e( 'On the website: available.', 'atelier-irisee-master-plugin' );
						}
						?>
						<?php if ( ! $product_object->get_manage_stock() ) : ?>
							<br><span class="description"><?php esc_html_e( 'Tip: tick "Manage stock?" on the Inventory tab and enter the number of patterns you have. Every sale (with or without a size) then lowers it by one.', 'atelier-irisee-master-plugin' ); ?></span>
						<?php endif; ?>
						<br><span class="description"><?php esc_html_e( 'Still seeing old information on the website after saving? Clear the cache of your caching plugin once.', 'atelier-irisee-master-plugin' ); ?></span>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( $is_product && self::is_pattern( $product_object->get_id() ) ) : ?>
				<?php list( $regular, $sale ) = self::pattern_prices( $product_object ); ?>
				<div class="options_group">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'          => self::META_PRICE,
							/* translators: %s: currency symbol */
							'label'       => sprintf( __( 'Pattern price (%s)', 'atelier-irisee-master-plugin' ), get_woocommerce_currency_symbol() ),
							'data_type'   => 'price',
							'value'       => wc_format_localized_price( $regular ),
							'description' => __( 'One price for the pattern, whatever size is chosen in the configurator, and when it is bought on its own (all sizes). It is copied to every size when you save.', 'atelier-irisee-master-plugin' ),
							'desc_tip'    => true,
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'          => self::META_SALE_PRICE,
							/* translators: %s: currency symbol */
							'label'       => sprintf( __( 'Sale price (%s)', 'atelier-irisee-master-plugin' ), get_woocommerce_currency_symbol() ),
							'data_type'   => 'price',
							'value'       => wc_format_localized_price( $sale ),
							'description' => __( 'Optional. Leave empty when the pattern is not on sale.', 'atelier-irisee-master-plugin' ),
							'desc_tip'    => true,
						)
					);
					?>
				</div>
			<?php endif; ?>
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id'          => AIMP_Catalog::META_SKILL,
						'label'       => __( 'Skill level', 'atelier-irisee-master-plugin' ),
						'options'     => array( '' => __( '— Not set —', 'atelier-irisee-master-plugin' ) ) + AIMP_Catalog::skill_levels(),
						'value'       => $is_product ? (string) $product_object->get_meta( AIMP_Catalog::META_SKILL ) : '',
						'description' => __( 'Shown on the pattern page and used as a filter on the patterns page.', 'atelier-irisee-master-plugin' ),
						'desc_tip'    => true,
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'          => AIMP_Catalog::META_SIZES_TEXT,
						'label'       => __( 'Included sizes', 'atelier-irisee-master-plugin' ),
						'placeholder' => __( 'e.g. XS – XXL (32 – 50)', 'atelier-irisee-master-plugin' ),
						'value'       => $is_product ? (string) $product_object->get_meta( AIMP_Catalog::META_SIZES_TEXT ) : '',
						'description' => __( 'Shown next to the skill level on the pattern page, e.g. 34 → 52.', 'atelier-irisee-master-plugin' ),
						'desc_tip'    => true,
					)
				);
				if ( $designs ) {
					echo '<p class="description aimp-designs-note">' . esc_html__( 'This pattern has designs: the project time, the height and the fitting fabrics are set per design below.', 'atelier-irisee-master-plugin' ) . '</p>';
				} else {
				woocommerce_wp_text_input(
					array(
						'id'          => AIMP_Catalog::META_PROJECT_TIME,
						'label'       => __( 'Average project time', 'atelier-irisee-master-plugin' ),
						'placeholder' => __( 'e.g. 5h', 'atelier-irisee-master-plugin' ),
						'value'       => $is_product ? (string) $product_object->get_meta( AIMP_Catalog::META_PROJECT_TIME ) : '',
						'description' => __( 'How long sewing it takes on average. Shown next to the skill level and the sizes on the pattern page.', 'atelier-irisee-master-plugin' ),
						'desc_tip'    => true,
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'                => AIMP_Catalog::META_HEIGHT,
						'label'             => __( 'Height (cm)', 'atelier-irisee-master-plugin' ),
						'type'              => 'number',
						'value'             => $height,
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '0.1',
						),
						'description'       => __( 'The body length the pattern is drafted for, the same for all sizes. Shown with the size measurements in the configurator.', 'atelier-irisee-master-plugin' ),
						'desc_tip'          => true,
					)
				);
				}
				?>
			</div>
			<?php if ( $designs ) : ?>
				<?php self::render_designs( $product_object, $designs, $design_set, $terms ); ?>
			<?php endif; ?>
			<?php if ( $is_product ) : ?>
				<?php self::render_rules( $product_object ); ?>
			<?php endif; ?>
			<div class="options_group aimp-priority">
				<h4><?php esc_html_e( 'Fitting fabrics', 'atelier-irisee-master-plugin' ); ?></h4>
				<?php if ( $designs ) : ?>
					<p class="description"><?php esc_html_e( 'This pattern has designs: the fitting fabrics and their positions are set per design (Pattern details → Designs).', 'atelier-irisee-master-plugin' ); ?></p>
				<?php else : ?>
				<p class="description"><?php esc_html_e( 'Tick the fabric categories that suit this pattern; they are the same for all sizes. Customers choose their fabric from these categories. Give them a position to choose which they see first: 1 is shown first, then 2, and so on. Categories without a position come after them.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php endif; ?>
				<?php if ( $designs ) : ?>
					<?php // Per design (Designs block). ?>
				<?php elseif ( ! $terms ) : ?>
					<p class="description"><?php esc_html_e( 'No fabric subcategories found. Set the Fabrics category under WooCommerce > Atelier Irisee and give it subcategories.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php else : ?>
					<table class="widefat aimp-priority-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Fabric category', 'atelier-irisee-master-plugin' ); ?></th>
									<?php if ( ! $designs ) : ?>
									<th><?php esc_html_e( 'Allowed', 'atelier-irisee-master-plugin' ); ?></th>
									<?php endif; ?>
								<th><?php esc_html_e( 'Position', 'atelier-irisee-master-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $terms as $term ) : ?>
								<?php
								$term_id = (int) $term->term_id;
									$allowed = $designs ? true : in_array( $term_id, $checked, true );
								?>
								<tr data-term="<?php echo esc_attr( $term_id ); ?>"<?php echo $allowed ? '' : ' class="is-off"'; ?>>
									<td><label for="<?php echo esc_attr( 'aimp_fitting_' . $term_id ); ?>"><?php echo esc_html( $term->name ); ?></label></td>
										<?php if ( ! $designs ) : ?>
										<td>
											<input type="checkbox" class="aimp-fitting-checkbox" id="<?php echo esc_attr( 'aimp_fitting_' . $term_id ); ?>"
												name="aimp_fitting_fabrics[]" value="<?php echo esc_attr( $term_id ); ?>"<?php checked( $allowed ); ?>>
										</td>
										<?php endif; ?>
									<td>
										<input type="number" min="1" step="1" class="small-text" id="<?php echo esc_attr( 'aimp_priority_' . $term_id ); ?>"
											name="aimp_fabric_priority[<?php echo esc_attr( $term_id ); ?>]"
											aria-label="<?php echo esc_attr( sprintf( /* translators: %s: fabric category */ __( 'Position of %s', 'atelier-irisee-master-plugin' ), $term->name ) ); ?>"
											value="<?php echo isset( $priority[ $term_id ] ) ? (int) $priority[ $term_id ] : ''; ?>"<?php disabled( ! $allowed ); ?>>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<input type="hidden" name="aimp_priority_present" value="1">
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * "Designs": one card per design with its pictures (ticked from the product pictures), fitting fabrics,
	 * height and project time. assets/js/admin.js keeps the picture grids in step with the product gallery.
	 *
	 * @param WC_Product $product Pattern.
	 * @param array      $designs Slug => name.
	 * @param array      $saved   AIMP_Catalog::design_data().
	 * @param WP_Term[]  $terms   Fabric categories.
	 */
	private static function render_designs( $product, $designs, $saved, $terms ) {
		$pictures = array_values( array_unique( array_filter( array_map( 'absint', array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) ) );
		?>
		<div class="options_group aimp-designs" data-aimp-designs>
			<h4><?php esc_html_e( 'Designs', 'atelier-irisee-master-plugin' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Each design is its own choice in the configurator, with its own sizes (the rows of the Variations tab). Choose the main picture of each design and tick its other pictures, then fill its fitting fabrics (with their positions), height and project time. Add the pictures to the product gallery first.', 'atelier-irisee-master-plugin' ); ?></p>
			<?php foreach ( $designs as $slug => $name ) : ?>
				<?php
				$row  = isset( $saved[ $slug ] ) ? $saved[ $slug ] : array( 'image_ids' => array(), 'fabric_cats' => array(), 'height' => '', 'project_time' => '', 'fabric_priority' => array() );
				$base = 'aimp_designs[' . $slug . ']';
				?>
				<fieldset class="aimp-design" data-design="<?php echo esc_attr( $slug ); ?>">
					<legend><?php echo esc_html( $name ); ?></legend>
					<?php $main = $row['image_ids'] ? (int) $row['image_ids'][0] : 0; ?>
					<div class="aimp-design-picture-boxes">
						<div class="aimp-design-main-box">
							<p class="aimp-design-label"><?php echo esc_html( sprintf( /* translators: %s: design name */ __( 'Main picture: %s', 'atelier-irisee-master-plugin' ), $name ) ); ?></p>
							<p class="description"><?php esc_html_e( 'The picture of the design\'s card in the configurator, shown first.', 'atelier-irisee-master-plugin' ); ?></p>
							<ul class="aimp-design-pictures aimp-design-main" data-type="radio" data-name="<?php echo esc_attr( $base . '[main_image]' ); ?>">
								<?php foreach ( $pictures as $picture ) : ?>
									<?php $url = wp_get_attachment_image_url( $picture, 'thumbnail' ); ?>
									<?php if ( $url ) : ?>
										<li data-id="<?php echo esc_attr( $picture ); ?>">
											<label>
												<input type="radio" name="<?php echo esc_attr( $base . '[main_image]' ); ?>" value="<?php echo esc_attr( $picture ); ?>"<?php checked( $main, $picture ); ?>>
												<img src="<?php echo esc_url( $url ); ?>" alt="">
											</label>
										</li>
									<?php endif; ?>
								<?php endforeach; ?>
							</ul>
						</div>
						<div class="aimp-design-other-box">
							<p class="aimp-design-label"><?php echo esc_html( sprintf( /* translators: %s: design name */ __( 'Other pictures of this design: %s', 'atelier-irisee-master-plugin' ), $name ) ); ?></p>
							<p class="description"><?php esc_html_e( 'Tick every other picture of this design; they are shown after the main picture.', 'atelier-irisee-master-plugin' ); ?></p>
							<ul class="aimp-design-pictures aimp-design-others" data-type="checkbox" data-name="<?php echo esc_attr( $base . '[image_ids][]' ); ?>">
								<?php foreach ( $pictures as $picture ) : ?>
									<?php $url = wp_get_attachment_image_url( $picture, 'thumbnail' ); ?>
									<?php if ( $url ) : ?>
										<li data-id="<?php echo esc_attr( $picture ); ?>"<?php echo $picture === $main ? ' class="is-main"' : ''; ?>>
											<label>
												<input type="checkbox" name="<?php echo esc_attr( $base . '[image_ids][]' ); ?>" value="<?php echo esc_attr( $picture ); ?>"<?php checked( $picture !== $main && in_array( $picture, $row['image_ids'], true ) ); ?><?php disabled( $picture === $main ); ?>>
												<img src="<?php echo esc_url( $url ); ?>" alt="">
											</label>
										</li>
									<?php endif; ?>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
					<p class="description aimp-design-empty"<?php echo $pictures ? ' hidden' : ''; ?>><?php esc_html_e( 'Add the pictures to the product gallery first.', 'atelier-irisee-master-plugin' ); ?></p>
					<p class="aimp-design-label"><?php echo esc_html( sprintf( /* translators: %s: design name */ __( 'Fitting fabrics: %s', 'atelier-irisee-master-plugin' ), $name ) ); ?></p>
					<p class="description"><?php esc_html_e( 'Tick the fabric categories that suit this design. Give them a position to choose which customers see first: 1 is shown first, then 2, and so on.', 'atelier-irisee-master-plugin' ); ?></p>
					<table class="widefat aimp-priority-table aimp-design-fabrics">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Fabric category', 'atelier-irisee-master-plugin' ); ?></th>
								<th><?php esc_html_e( 'Allowed', 'atelier-irisee-master-plugin' ); ?></th>
								<th><?php esc_html_e( 'Position', 'atelier-irisee-master-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $terms as $term ) : ?>
								<?php
								$term_id = (int) $term->term_id;
								$allowed = in_array( $term_id, $row['fabric_cats'], true );
								$field   = 'aimp_design_' . $slug . '_' . $term_id;
								?>
								<tr<?php echo $allowed ? '' : ' class="is-off"'; ?>>
									<td><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $term->name ); ?></label></td>
									<td><input type="checkbox" class="aimp-fitting-checkbox" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $base . '[fabric_cats][]' ); ?>" value="<?php echo esc_attr( $term_id ); ?>"<?php checked( $allowed ); ?>></td>
									<td>
										<input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( $base . '[fabric_priority][' . $term_id . ']' ); ?>"
											aria-label="<?php echo esc_attr( sprintf( /* translators: %s: fabric category */ __( 'Position of %s', 'atelier-irisee-master-plugin' ), $term->name ) ); ?>"
											value="<?php echo isset( $row['fabric_priority'][ $term_id ] ) ? (int) $row['fabric_priority'][ $term_id ] : ''; ?>"<?php disabled( ! $allowed ); ?>>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="aimp-design-fields">
						<label><?php esc_html_e( 'Height (cm)', 'atelier-irisee-master-plugin' ); ?> <input type="number" min="0" step="0.1" class="short" name="<?php echo esc_attr( $base . '[height]' ); ?>" value="<?php echo esc_attr( $row['height'] ); ?>"></label>
						<label><?php esc_html_e( 'Average project time', 'atelier-irisee-master-plugin' ); ?> <input type="text" class="short" placeholder="<?php esc_attr_e( 'e.g. 5h', 'atelier-irisee-master-plugin' ); ?>" name="<?php echo esc_attr( $base . '[project_time]' ); ?>" value="<?php echo esc_attr( $row['project_time'] ); ?>"></label>
					</p>
				</fieldset>
			<?php endforeach; ?>
			<input type="hidden" name="aimp_designs_present" value="1">
		</div>
		<?php
	}

	/**
	 * Which haberdashery the pattern (or one design) uses, as shown on the tab: rule => [ on, min, max, label, type,
	 * amount, saved ]. A rule that was never saved (patterns from before 3.6, or kinds new in 3.7) shows as on when a
	 * size has an amount for it.
	 *
	 * @param WC_Product|null $product Pattern.
	 * @param string          $design  Design slug ('' = the whole pattern).
	 * @return array
	 */
	private static function shown_rules( $product, $design = '' ) {
		$id     = $product ? $product->get_id() : 0;
		$rules  = AIMP_Notions::pattern_rules( $id, $design );
		$fields = AIMP_Catalog::material_fields();
		$sizes  = $product ? $product->get_children() : array();
		if ( '' !== $design ) {
			$sizes = array_filter(
				$sizes,
				function ( $child_id ) use ( $design ) {
					return AIMP_Catalog::variation_design( $child_id ) === $design;
				}
			);
		}
		foreach ( AIMP_Notions::rules() as $rule => $info ) {
			if ( $rules[ $rule ]['saved'] || AIMP_Notions::is_extra( $rule ) ) {
				continue;
			}
			$used = false;
			foreach ( $sizes as $child_id ) {
				if ( (float) get_post_meta( $child_id, $fields[ $info[2] ]['meta'], true ) > 0 ) {
					$used = true;
					break;
				}
			}
			$rules[ $rule ]['on'] = $used;
		}
		return $rules;
	}

	/**
	 * The design of a size, or '' when the pattern has no designs.
	 *
	 * @param WC_Product|null $pattern      Pattern.
	 * @param int             $variation_id Size.
	 * @return string
	 */
	private static function size_design( $pattern, $variation_id ) {
		return $pattern && AIMP_Catalog::designs( $pattern ) ? AIMP_Catalog::variation_design( $variation_id ) : '';
	}

	/**
	 * "Haberdashery": per kind (buttons, zips, elastic, bias tape, ribbons & cords, interfacing) and type a switch,
	 * the diameter or width that fits, and the number or length for every size. Buttons can have up to two extra
	 * groups (another diameter or type, e.g. "Cuffs"). A pattern with designs gets one block per design.
	 *
	 * @param WC_Product $product Pattern.
	 */
	private static function render_rules( $product ) {
		$designs = AIMP_Catalog::designs( $product );
		if ( ! $designs ) {
			echo '<div class="options_group aimp-notion-rules">';
			self::render_rule_block( $product, '', '' );
			echo '<input type="hidden" name="aimp_notion_rules_present" value="1"></div>';
			return;
		}
		echo '<div class="options_group aimp-notion-rules aimp-notion-rules--designs">';
		foreach ( $designs as $slug => $name ) {
			self::render_rule_block( $product, $slug, $name );
		}
		echo '<input type="hidden" name="aimp_design_rules_present" value="1"></div>';
	}

	/**
	 * One haberdashery block: for the pattern, or for one design.
	 *
	 * @param WC_Product $product Pattern.
	 * @param string     $design  Design slug, or ''.
	 * @param string     $name    Design name.
	 */
	private static function render_rule_block( $product, $design, $name ) {
		$rules  = self::shown_rules( $product, $design );
		$fields = AIMP_Catalog::material_fields();
		$prefix = '' === $design ? 'aimp_notion_rules' : 'aimp_design_rules[' . $design . ']';
		$attrs  = function ( $unit ) {
			$cm = 'cm' === $unit;
			return sprintf(
				' data-range="%1$s" data-from="%2$s" data-upto="%3$s" data-unit="%4$s"',
				/* translators: 1: smallest size, 2: largest size */
				esc_attr( $cm ? __( '%1$s–%2$s cm', 'atelier-irisee-master-plugin' ) : __( '%1$s–%2$s mm', 'atelier-irisee-master-plugin' ) ),
				/* translators: %s: size */
				esc_attr( $cm ? __( 'from %s cm', 'atelier-irisee-master-plugin' ) : __( 'from %s mm', 'atelier-irisee-master-plugin' ) ),
				/* translators: %s: size */
				esc_attr( $cm ? __( 'up to %s cm', 'atelier-irisee-master-plugin' ) : __( 'up to %s mm', 'atelier-irisee-master-plugin' ) ),
				esc_attr( $unit )
			);
		};
		$button_types = AIMP_Notions::kind( 'button' )['types'];
		?>
		<div class="aimp-notion-rule-block" data-aimp-notion-rules data-design="<?php echo esc_attr( $design ); ?>"
			data-allowed="<?php /* translators: %s: what may be filled in, e.g. "Buttons 14–18 mm" */ echo esc_attr( __( 'Allowed: %s', 'atelier-irisee-master-plugin' ) ); ?>"
			data-none="<?php esc_attr_e( 'Switch it on on the Atelier Irisee tab (Haberdashery) to fill this in.', 'atelier-irisee-master-plugin' ); ?>">
			<h4>
				<?php
				/* translators: %s: design name */
				echo esc_html( '' === $design ? __( 'Haberdashery', 'atelier-irisee-master-plugin' ) : sprintf( __( 'Haberdashery: %s', 'atelier-irisee-master-plugin' ), $name ) );
				?>
			</h4>
			<p class="description"><?php esc_html_e( 'Switch on what this pattern needs, with the diameter or width that fits (empty = any size), and the number or length for all sizes. A size can still have its own number or length on the Variations tab. Zips: only zips of exactly that length are offered. Ribbons and cords: all of them are offered.', 'atelier-irisee-master-plugin' ); ?></p>
			<?php foreach ( AIMP_Notions::kinds() as $kind => $kind_info ) : ?>
				<fieldset class="aimp-notion-rule-group" data-kind="<?php echo esc_attr( $kind ); ?>">
					<legend><?php echo esc_html( $kind_info['name'] ); ?></legend>
					<?php foreach ( AIMP_Notions::rules() as $rule_key => $info ) : ?>
						<?php
						if ( $info[0] !== $kind ) {
							continue;
						}
						$rule   = $rules[ $rule_key ];
						$base   = $prefix . '[' . $rule_key . ']';
						$field  = $fields[ $info[2] ];
						$label  = $field['label'];
						$unit   = AIMP_Notions::rule_unit( $rule_key );
						$range  = '' !== $kind_info['range'] ? AIMP_Notions::fields()[ $kind_info['range'] ][1] : '';
						$is_cm  = 'cm' === $field['unit'];
						$extra  = AIMP_Notions::is_extra( $rule_key );
						$shown  = $label . ( '' !== $rule['label'] ? ' – ' . $rule['label'] : '' );
						?>
						<div class="aimp-notion-rule<?php echo $rule['on'] ? ' is-on' : ''; ?>" data-material="<?php echo esc_attr( $info[2] ); ?>" data-label="<?php echo esc_attr( $shown ); ?>"<?php echo '' !== $unit ? $attrs( $unit ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $attrs. ?>>
							<label class="aimp-notion-rule-switch">
								<input type="checkbox" class="aimp-notion-rule-toggle" name="<?php echo esc_attr( $base . '[on]' ); ?>" value="1"<?php checked( $rule['on'] ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
							<?php if ( 'button' === $kind ) : ?>
								<label class="aimp-notion-rule-name">
									<?php esc_html_e( 'Name', 'atelier-irisee-master-plugin' ); ?>
									<input type="text" class="short" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $rule['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Cuffs', 'atelier-irisee-master-plugin' ); ?>">
								</label>
							<?php endif; ?>
							<?php if ( $extra ) : ?>
								<label class="aimp-notion-rule-type">
									<?php esc_html_e( 'Type', 'atelier-irisee-master-plugin' ); ?>
									<select name="<?php echo esc_attr( $base . '[type]' ); ?>">
										<?php foreach ( $button_types as $type_key => $type_name ) : ?>
											<option value="<?php echo esc_attr( $type_key ); ?>"<?php selected( '' !== $rule['type'] ? $rule['type'] : 'button', $type_key ); ?>><?php echo esc_html( $type_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endif; ?>
							<?php if ( '' !== $unit ) : ?>
								<span class="aimp-notion-rule-range">
									<label>
										<?php echo esc_html( sprintf( /* translators: %s: "Diameter" or "Width" */ __( '%s from', 'atelier-irisee-master-plugin' ), $range ) ); ?>
										<input type="number" class="small-text" min="0" step="<?php echo 'cm' === $unit ? '1' : '0.5'; ?>" name="<?php echo esc_attr( $base . '[min]' ); ?>" value="<?php echo esc_attr( (string) $rule['min'] ); ?>">
									</label>
									<label>
										<?php esc_html_e( 'to', 'atelier-irisee-master-plugin' ); ?>
										<input type="number" class="small-text" min="0" step="<?php echo 'cm' === $unit ? '1' : '0.5'; ?>" name="<?php echo esc_attr( $base . '[max]' ); ?>" value="<?php echo esc_attr( (string) $rule['max'] ); ?>">
										<?php echo esc_html( $unit ); ?>
									</label>
								</span>
							<?php endif; ?>
							<label class="aimp-notion-rule-amount">
								<?php echo esc_html( 'zip' === $kind ? __( 'Length (all sizes)', 'atelier-irisee-master-plugin' ) : ( $is_cm ? __( 'Length (all sizes)', 'atelier-irisee-master-plugin' ) : __( 'Number (all sizes)', 'atelier-irisee-master-plugin' ) ) ); ?>
								<input type="number" class="small-text" min="0" step="<?php echo $is_cm ? '0.5' : '1'; ?>" name="<?php echo esc_attr( $base . '[amount]' ); ?>" value="<?php echo esc_attr( (string) $rule['amount'] ); ?>">
								<?php echo esc_html( $is_cm ? __( 'cm', 'atelier-irisee-master-plugin' ) : __( 'pcs', 'atelier-irisee-master-plugin' ) ); ?>
							</label>
						</div>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Cleans the posted rows of one haberdashery block.
	 *
	 * @param array $input Posted rows: rule => [ on, min, max, label, type, amount ].
	 * @return array
	 */
	private static function clean_rules( $input ) {
		$number = function ( $value ) {
			$value = wc_clean( (string) $value );
			return '' === $value || (float) $value <= 0 ? '' : wc_format_decimal( (float) $value, 1, true );
		};
		$fields       = AIMP_Catalog::material_fields();
		$button_types = AIMP_Notions::kind( 'button' )['types'];
		$clean        = array();
		foreach ( AIMP_Notions::rules() as $rule => $info ) {
			$row            = isset( $input[ $rule ] ) && is_array( $input[ $rule ] ) ? $input[ $rule ] : array();
			$type           = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : '';
			$amount         = isset( $row['amount'] ) ? $number( $row['amount'] ) : '';
			$clean[ $rule ] = array(
				'on'     => ! empty( $row['on'] ),
				'min'    => isset( $row['min'] ) ? $number( $row['min'] ) : '',
				'max'    => isset( $row['max'] ) ? $number( $row['max'] ) : '',
				'label'  => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
				'type'   => AIMP_Notions::is_extra( $rule ) && isset( $button_types[ $type ] ) ? $type : '',
				// Pieces are whole numbers; lengths in cm.
				'amount' => '' !== $amount && 'cm' !== $fields[ $info[2] ]['unit'] ? (string) max( 1, (int) round( (float) $amount ) ) : $amount,
			);
		}
		return $clean;
	}

	/**
	 * A kind switched off loses its amounts on the sizes (of one design, or all).
	 *
	 * @param WC_Product $product Pattern.
	 * @param array      $clean   Cleaned rules.
	 * @param string     $design  Design slug, or ''.
	 */
	private static function clear_off_amounts( $product, $clean, $design ) {
		$fields = AIMP_Catalog::material_fields();
		foreach ( AIMP_Notions::rules() as $rule => $info ) {
			if ( $clean[ $rule ]['on'] ) {
				continue;
			}
			foreach ( $product->get_children() as $child_id ) {
				if ( '' === $design || AIMP_Catalog::variation_design( $child_id ) === $design ) {
					delete_post_meta( $child_id, $fields[ $info[2] ]['meta'] );
				}
			}
		}
	}

	/**
	 * Saves the "Haberdashery" block of a pattern without designs (the designs save theirs with the Designs block).
	 *
	 * @param WC_Product $product Pattern.
	 */
	private static function save_rules( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( empty( $_POST['aimp_notion_rules_present'] ) || ! $product->is_type( 'variable' ) ) {
			return;
		}
		$input = isset( $_POST['aimp_notion_rules'] ) && is_array( $_POST['aimp_notion_rules'] ) ? wp_unslash( $_POST['aimp_notion_rules'] ) : array();
		// phpcs:enable
		$clean = self::clean_rules( $input );
		self::clear_off_amounts( $product, $clean, '' );
		$product->update_meta_data( AIMP_Notions::META_RULES, $clean );
		$product->delete_meta_data( AIMP_Notions::META_BUTTON_RULES );
	}

	/**
	 * The haberdashery rules of one design from the form, or null when the form had no design blocks.
	 *
	 * @param WC_Product $product Pattern.
	 * @param string     $slug    Design slug.
	 * @return array|null
	 */
	private static function posted_design_rules( $product, $slug ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( empty( $_POST['aimp_design_rules_present'] ) ) {
			return null;
		}
		$input = isset( $_POST['aimp_design_rules'][ $slug ] ) && is_array( $_POST['aimp_design_rules'][ $slug ] ) ? wp_unslash( $_POST['aimp_design_rules'][ $slug ] ) : array();
		// phpcs:enable
		$clean = self::clean_rules( $input );
		self::clear_off_amounts( $product, $clean, $slug );
		return $clean;
	}

	/**
	 * "Allowed: Buttons 14–18 mm · Press studs" under a group of a size, or how to switch one on.
	 *
	 * @param WC_Product|null $product   Pattern.
	 * @param string[]        $materials Material keys of the group.
	 * @param string          $design    The size's design, or ''.
	 * @return string
	 */
	private static function allowed_text( $product, $materials, $design = '' ) {
		$parts  = array();
		$fields = AIMP_Catalog::material_fields();
		foreach ( self::shown_rules( $product, $design ) as $rule => $state ) {
			$material = AIMP_Notions::rules()[ $rule ][2];
			if ( $state['on'] && in_array( $material, $materials, true ) ) {
				$range   = AIMP_Notions::range_text( $state, AIMP_Notions::rule_unit( $rule ) );
				$parts[] = $fields[ $material ]['label'] . ( '' !== $state['label'] ? ' – ' . $state['label'] : '' ) . ( '' !== $range ? ' ' . $range : '' );
			}
		}
		/* translators: %s: what may be filled in, e.g. "Buttons 14–18 mm" */
		return $parts ? sprintf( __( 'Allowed: %s', 'atelier-irisee-master-plugin' ), implode( ' · ', $parts ) ) : __( 'Switch it on on the Atelier Irisee tab (Haberdashery) to fill this in.', 'atelier-irisee-master-plugin' );
	}

	/**
	 * Saves the "Designs" block (per design: pictures in the order they were ticked, fabrics, height, time).
	 *
	 * @param WC_Product $product Pattern.
	 */
	private static function save_designs( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( empty( $_POST['aimp_designs_present'] ) ) {
			return;
		}
		$input = isset( $_POST['aimp_designs'] ) && is_array( $_POST['aimp_designs'] ) ? wp_unslash( $_POST['aimp_designs'] ) : array();
		// phpcs:enable
		$clean    = array();
		$previous = AIMP_Catalog::design_data( $product->get_id() );
		foreach ( AIMP_Catalog::designs( $product ) as $slug => $name ) {
			$rules          = self::posted_design_rules( $product, $slug );
			$row            = isset( $input[ $slug ] ) && is_array( $input[ $slug ] ) ? $input[ $slug ] : array();
			$height         = isset( $row['height'] ) ? wc_clean( $row['height'] ) : '';
			$main           = isset( $row['main_image'] ) ? absint( $row['main_image'] ) : 0;
			$others         = array_map( 'absint', isset( $row['image_ids'] ) ? (array) $row['image_ids'] : array() );
			$fabric_cats    = array_values( array_unique( array_filter( array_map( 'absint', isset( $row['fabric_cats'] ) ? (array) $row['fabric_cats'] : array() ) ) ) );
			$positions      = array();
			foreach ( isset( $row['fabric_priority'] ) ? (array) $row['fabric_priority'] : array() as $term_id => $position ) {
				if ( in_array( absint( $term_id ), $fabric_cats, true ) && absint( $position ) ) {
					$positions[ absint( $term_id ) ] = absint( $position );
				}
			}
			$clean[ $slug ] = array(
				'image_ids'       => array_values( array_unique( array_filter( array_merge( array( $main ), $others ) ) ) ),
				'fabric_cats'     => $fabric_cats,
				'fabric_priority' => $positions,
				'height'       => '' === $height ? '' : wc_format_decimal( max( 0, (float) $height ), 2, true ),
				'project_time' => isset( $row['project_time'] ) ? sanitize_text_field( $row['project_time'] ) : '',
				'notion_rules' => null !== $rules ? $rules : ( isset( $previous[ $slug ]['notion_rules'] ) ? $previous[ $slug ]['notion_rules'] : array() ),
			);
		}
		$product->update_meta_data( AIMP_Catalog::META_DESIGNS, $clean );
	}

	/**
	 * Runs inside WooCommerce's product save, which already verified its nonce.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_fabric_priority( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( isset( $_POST[ AIMP_Catalog::META_SKILL ] ) && current_user_can( 'edit_products' ) ) {
			$skill = sanitize_key( wp_unslash( $_POST[ AIMP_Catalog::META_SKILL ] ) );
			if ( array_key_exists( $skill, AIMP_Catalog::skill_levels() ) ) {
				$product->update_meta_data( AIMP_Catalog::META_SKILL, $skill );
			} else {
				$product->delete_meta_data( AIMP_Catalog::META_SKILL );
			}
		}
		if ( isset( $_POST[ self::META_PRICE ] ) && current_user_can( 'edit_products' ) && $product->is_type( 'variable' ) ) {
			$regular = wc_format_decimal( wc_clean( wp_unslash( $_POST[ self::META_PRICE ] ) ) );
			$sale    = isset( $_POST[ self::META_SALE_PRICE ] ) ? wc_format_decimal( wc_clean( wp_unslash( $_POST[ self::META_SALE_PRICE ] ) ) ) : '';
			if ( '' === $regular || ( '' !== $sale && (float) $sale >= (float) $regular ) ) {
				$sale = ''; // A sale price needs a (higher) normal price.
			}
			$product->update_meta_data( self::META_PRICE, $regular );
			$product->update_meta_data( self::META_SALE_PRICE, $sale );
		}
		if ( isset( $_POST[ AIMP_Catalog::META_SIZES_TEXT ] ) && current_user_can( 'edit_products' ) ) {
			$sizes = sanitize_text_field( wp_unslash( $_POST[ AIMP_Catalog::META_SIZES_TEXT ] ) );
			if ( '' === $sizes ) {
				$product->delete_meta_data( AIMP_Catalog::META_SIZES_TEXT );
			} else {
				$product->update_meta_data( AIMP_Catalog::META_SIZES_TEXT, $sizes );
			}
		}
		if ( current_user_can( 'edit_products' ) ) {
			self::save_designs( $product );
			self::save_rules( $product );
		}
		if ( isset( $_POST[ AIMP_Catalog::META_PROJECT_TIME ] ) && current_user_can( 'edit_products' ) ) {
			$time = sanitize_text_field( wp_unslash( $_POST[ AIMP_Catalog::META_PROJECT_TIME ] ) );
			if ( '' === $time ) {
				$product->delete_meta_data( AIMP_Catalog::META_PROJECT_TIME );
			} else {
				$product->update_meta_data( AIMP_Catalog::META_PROJECT_TIME, $time );
			}
		}
		if ( isset( $_POST[ AIMP_Catalog::META_HEIGHT ] ) && current_user_can( 'edit_products' ) ) {
			$height = wc_clean( wp_unslash( $_POST[ AIMP_Catalog::META_HEIGHT ] ) );
			// Empty is saved too, so the old heights of the sizes no longer count.
			$product->update_meta_data( AIMP_Catalog::META_HEIGHT, '' === $height ? '' : wc_format_decimal( max( 0, (float) $height ), 2, true ) );
		}
		if ( empty( $_POST['aimp_priority_present'] ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		// Fitting fabrics: the same for all sizes (an empty list is saved too).
		$fitting = isset( $_POST['aimp_fitting_fabrics'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['aimp_fitting_fabrics'] ) ) : array();
		$product->update_meta_data( AIMP_Catalog::META_FABRIC_CATS, array_values( array_filter( $fitting ) ) );
		$input = isset( $_POST['aimp_fabric_priority'] ) ? (array) wp_unslash( $_POST['aimp_fabric_priority'] ) : array();
		// phpcs:enable
		$priority = array();
		foreach ( $input as $term_id => $position ) {
			$term_id  = absint( $term_id );
			$position = absint( $position );
			if ( $term_id && $position ) {
				$priority[ $term_id ] = $position;
			}
		}
		if ( $priority ) {
			$product->update_meta_data( AIMP_Catalog::META_FABRIC_PRIORITY, $priority );
		} else {
			$product->delete_meta_data( AIMP_Catalog::META_FABRIC_PRIORITY );
		}
	}
}
