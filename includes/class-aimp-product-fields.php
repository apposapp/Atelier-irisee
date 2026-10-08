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
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_zip_length_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_zip_length_field' ) );
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
	 * Number fields on a variation: key => [ label, step ].
	 *
	 * @return array
	 */
	private static function number_fields() {
		return array(
			AIMP_Catalog::META_FABRIC_UNITS => array( __( 'Fabric needed (units of 10 cm)', 'atelier-irisee-master-plugin' ), '1' ),
			AIMP_Catalog::META_BUTTON_COUNT => array( __( 'Button count', 'atelier-irisee-master-plugin' ), '1' ),
			AIMP_Catalog::META_ZIP_COUNT    => array( __( 'Zip count', 'atelier-irisee-master-plugin' ), '1' ),
			AIMP_Catalog::META_ZIP_LENGTH   => array( __( 'Zip length (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
			AIMP_Catalog::META_RIBBON_LENGTH => array( __( 'Ribbon length (cm)', 'atelier-irisee-master-plugin' ), '1' ),
			AIMP_Catalog::META_BIAS_LENGTH  => array( __( 'Bias tape length (cm)', 'atelier-irisee-master-plugin' ), '1' ),
			AIMP_Catalog::META_BUST         => array( __( 'Bust (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
			AIMP_Catalog::META_WAIST        => array( __( 'Waist (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
			AIMP_Catalog::META_HIP          => array( __( 'Hip (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
			AIMP_Catalog::META_INSIDE_LEG   => array( __( 'Inside leg (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
		);
	}

	private static function is_integer_field( $key ) {
		return in_array(
			$key,
			array(
				AIMP_Catalog::META_FABRIC_UNITS,
				AIMP_Catalog::META_BUTTON_COUNT,
				AIMP_Catalog::META_ZIP_COUNT,
				AIMP_Catalog::META_RIBBON_LENGTH,
				AIMP_Catalog::META_BIAS_LENGTH,
			),
			true
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
		?>
		<div class="aimp-variation-fields">
			<p class="aimp-variation-note"><?php esc_html_e( 'The price and stock of a pattern are the same for all sizes: set the price on the Atelier Irisee tab and the stock on the Inventory tab. They are copied to the sizes when you save.', 'atelier-irisee-master-plugin' ); ?></p>
			<h4><?php esc_html_e( 'Atelier Irisee – material requirements & size measurements', 'atelier-irisee-master-plugin' ); ?></h4>
			<div class="aimp-variation-grid">
				<?php foreach ( self::number_fields() as $key => $field ) : ?>
					<?php $id = 'aimp' . $key . '_' . $loop; ?>
					<p class="form-field">
						<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field[0] ); ?></label>
						<input type="number" min="0" step="<?php echo esc_attr( $field[1] ); ?>"
							id="<?php echo esc_attr( $id ); ?>"
							name="<?php echo esc_attr( 'aimp' . $key . '[' . $loop . ']' ); ?>"
							value="<?php echo esc_attr( $variation->get_meta( $key ) ); ?>">
					</p>
				<?php endforeach; ?>
			</div>
			<p class="aimp-variation-note"><?php esc_html_e( 'The height and the fitting fabrics are the same for all sizes: set them on the Atelier Irisee tab.', 'atelier-irisee-master-plugin' ); ?></p>
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

		foreach ( array_keys( self::number_fields() ) as $key ) {
			$name  = 'aimp' . $key;
			$value = isset( $_POST[ $name ][ $i ] ) ? wc_clean( wp_unslash( $_POST[ $name ][ $i ] ) ) : '';
			if ( '' === $value ) {
				$variation->delete_meta_data( $key );
				continue;
			}
			$value = self::is_integer_field( $key ) ? absint( $value ) : wc_format_decimal( max( 0, (float) $value ), 2, true );
			$variation->update_meta_data( $key, $value );
		}

		// phpcs:enable
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
			<div class="options_group aimp-priority">
				<h4><?php esc_html_e( 'Fitting fabrics', 'atelier-irisee-master-plugin' ); ?></h4>
				<?php if ( $designs ) : ?>
					<p class="description"><?php esc_html_e( 'The fitting fabrics are ticked per design (Pattern details). Here you choose which fabric categories customers see first: 1 is shown first, then 2, and so on.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php else : ?>
				<p class="description"><?php esc_html_e( 'Tick the fabric categories that suit this pattern; they are the same for all sizes. Customers choose their fabric from these categories. Give them a position to choose which they see first: 1 is shown first, then 2, and so on. Categories without a position come after them.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php endif; ?>
				<?php if ( ! $terms ) : ?>
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
			<p class="description"><?php esc_html_e( 'Each design is its own choice in the configurator, with its own sizes (the rows of the Variations tab). Tick the pictures of each design (the first ticked picture is its card picture), and fill its fitting fabrics, height and project time. Add the pictures to the product gallery first.', 'atelier-irisee-master-plugin' ); ?></p>
			<?php foreach ( $designs as $slug => $name ) : ?>
				<?php
				$row  = isset( $saved[ $slug ] ) ? $saved[ $slug ] : array( 'image_ids' => array(), 'fabric_cats' => array(), 'height' => '', 'project_time' => '' );
				$base = 'aimp_designs[' . $slug . ']';
				?>
				<fieldset class="aimp-design" data-design="<?php echo esc_attr( $slug ); ?>">
					<legend><?php echo esc_html( $name ); ?></legend>
					<p class="aimp-design-label"><?php esc_html_e( 'Pictures of this design', 'atelier-irisee-master-plugin' ); ?></p>
					<ul class="aimp-design-pictures" data-name="<?php echo esc_attr( $base . '[image_ids][]' ); ?>" data-card-label="<?php esc_attr_e( 'Card picture', 'atelier-irisee-master-plugin' ); ?>">
						<?php foreach ( $pictures as $picture ) : ?>
							<?php $url = wp_get_attachment_image_url( $picture, 'thumbnail' ); ?>
							<?php if ( $url ) : ?>
								<li data-id="<?php echo esc_attr( $picture ); ?>">
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $base . '[image_ids][]' ); ?>" value="<?php echo esc_attr( $picture ); ?>"<?php checked( in_array( $picture, $row['image_ids'], true ) ); ?>>
										<img src="<?php echo esc_url( $url ); ?>" alt="">
									</label>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
					<p class="description aimp-design-empty"<?php echo $pictures ? ' hidden' : ''; ?>><?php esc_html_e( 'Add the pictures to the product gallery first.', 'atelier-irisee-master-plugin' ); ?></p>
					<p class="aimp-design-label"><?php esc_html_e( 'Fitting fabrics', 'atelier-irisee-master-plugin' ); ?></p>
					<div class="aimp-design-fabrics">
						<?php foreach ( $terms as $term ) : ?>
							<label><input type="checkbox" name="<?php echo esc_attr( $base . '[fabric_cats][]' ); ?>" value="<?php echo esc_attr( $term->term_id ); ?>"<?php checked( in_array( (int) $term->term_id, $row['fabric_cats'], true ) ); ?>> <?php echo esc_html( $term->name ); ?></label>
						<?php endforeach; ?>
					</div>
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
		$clean = array();
		foreach ( AIMP_Catalog::designs( $product ) as $slug => $name ) {
			$row            = isset( $input[ $slug ] ) && is_array( $input[ $slug ] ) ? $input[ $slug ] : array();
			$height         = isset( $row['height'] ) ? wc_clean( $row['height'] ) : '';
			$clean[ $slug ] = array(
				'image_ids'    => array_values( array_unique( array_filter( array_map( 'absint', isset( $row['image_ids'] ) ? (array) $row['image_ids'] : array() ) ) ) ),
				'fabric_cats'  => array_values( array_unique( array_filter( array_map( 'absint', isset( $row['fabric_cats'] ) ? (array) $row['fabric_cats'] : array() ) ) ) ),
				'height'       => '' === $height ? '' : wc_format_decimal( max( 0, (float) $height ), 2, true ),
				'project_time' => isset( $row['project_time'] ) ? sanitize_text_field( $row['project_time'] ) : '',
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

	public static function render_zip_length_field() {
		echo '<div class="options_group show_if_simple">';
		woocommerce_wp_text_input(
			array(
				'id'                => AIMP_Catalog::META_ZIP_LENGTH,
				'label'             => __( 'Zip length (cm)', 'atelier-irisee-master-plugin' ),
				'description'       => __( 'Only used for products in the Zips category. The configurator only offers zips whose length equals the length a pattern size needs.', 'atelier-irisee-master-plugin' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.1',
				),
			)
		);
		echo '</div>';
	}

	/**
	 * Runs inside WooCommerce's product save, which already verified its nonce.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_zip_length_field( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( ! isset( $_POST[ AIMP_Catalog::META_ZIP_LENGTH ] ) ) {
			return;
		}
		$value = wc_clean( wp_unslash( $_POST[ AIMP_Catalog::META_ZIP_LENGTH ] ) );
		// phpcs:enable
		if ( '' === $value ) {
			$product->delete_meta_data( AIMP_Catalog::META_ZIP_LENGTH );
		} else {
			$product->update_meta_data( AIMP_Catalog::META_ZIP_LENGTH, wc_format_decimal( max( 0, (float) $value ), 2, true ) );
		}
	}
}
