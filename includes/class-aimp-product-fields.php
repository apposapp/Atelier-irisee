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

	public static function init() {
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
			AIMP_Catalog::META_BUST         => array( __( 'Bust (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
			AIMP_Catalog::META_WAIST        => array( __( 'Waist (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
			AIMP_Catalog::META_HEIGHT       => array( __( 'Height (cm)', 'atelier-irisee-master-plugin' ), '0.1' ),
		);
	}

	private static function is_integer_field( $key ) {
		return in_array( $key, array( AIMP_Catalog::META_FABRIC_UNITS, AIMP_Catalog::META_BUTTON_COUNT, AIMP_Catalog::META_ZIP_COUNT ), true );
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
			<fieldset class="form-field aimp-fabric-cats">
				<legend><?php esc_html_e( 'Fabric categories allowed', 'atelier-irisee-master-plugin' ); ?></legend>
				<?php
				$terms    = AIMP_Catalog::get_fabric_categories();
				$selected = (array) $variation->get_meta( AIMP_Catalog::META_FABRIC_CATS );
				$selected = array_map( 'absint', $selected );
				if ( ! $terms ) {
					echo '<p class="description">' . esc_html__( 'No fabric subcategories found. Set the Fabrics category under WooCommerce > Atelier Irisee and give it subcategories.', 'atelier-irisee-master-plugin' ) . '</p>';
				}
				foreach ( $terms as $term ) {
					printf(
						'<label><input type="checkbox" name="%s[]" value="%d" %s> %s</label>',
						esc_attr( 'aimp' . AIMP_Catalog::META_FABRIC_CATS . '[' . $loop . ']' ),
						(int) $term->term_id,
						checked( in_array( (int) $term->term_id, $selected, true ), true, false ),
						esc_html( $term->name )
					);
				}
				?>
				<input type="hidden" name="<?php echo esc_attr( 'aimp_fields_present[' . $loop . ']' ); ?>" value="1">
			</fieldset>
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

		$name = 'aimp' . AIMP_Catalog::META_FABRIC_CATS;
		$cats = isset( $_POST[ $name ][ $i ] ) ? array_map( 'absint', (array) wp_unslash( $_POST[ $name ][ $i ] ) ) : array();
		// phpcs:enable
		$variation->update_meta_data( AIMP_Catalog::META_FABRIC_CATS, array_values( array_filter( $cats ) ) );
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

	public static function render_pattern_panel() {
		global $product_object;
		$priority = $product_object instanceof WC_Product ? AIMP_Catalog::get_fabric_priority( $product_object ) : array();
		$terms    = AIMP_Catalog::get_fabric_categories();
		?>
		<div id="aimp_pattern_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group aimp-priority">
				<h4><?php esc_html_e( 'Fabric categories shown first', 'atelier-irisee-master-plugin' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Choose which fabric categories customers see first when they pick a fabric for this pattern. Give them a position: 1 is shown first, then 2, and so on. Categories without a position come after them. Only the categories allowed for the chosen size are shown.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php if ( ! $terms ) : ?>
					<p class="description"><?php esc_html_e( 'No fabric subcategories found. Set the Fabrics category under WooCommerce > Atelier Irisee and give it subcategories.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php else : ?>
					<table class="widefat striped aimp-priority-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Fabric category', 'atelier-irisee-master-plugin' ); ?></th>
								<th><?php esc_html_e( 'Position', 'atelier-irisee-master-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $terms as $term ) : ?>
								<?php $id = 'aimp_priority_' . (int) $term->term_id; ?>
								<tr>
									<td><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $term->name ); ?></label></td>
									<td>
										<input type="number" min="1" step="1" class="small-text" id="<?php echo esc_attr( $id ); ?>"
											name="aimp_fabric_priority[<?php echo (int) $term->term_id; ?>]"
											value="<?php echo isset( $priority[ $term->term_id ] ) ? (int) $priority[ $term->term_id ] : ''; ?>">
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
	 * Runs inside WooCommerce's product save, which already verified its nonce.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_fabric_priority( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
		if ( empty( $_POST['aimp_priority_present'] ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}
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
