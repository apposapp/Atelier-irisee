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
	}

	public static function add_box() {
		add_meta_box( 'aimp-fabric-texts', __( 'Fabric texts', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render' ), 'product', 'normal', 'high' );
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

			<div class="aimp-fabric-editors">
				<div>
					<h4><label for="aimp_inspiration"><?php esc_html_e( 'Inspiration', 'atelier-irisee-master-plugin' ); ?></label></h4>
					<p class="description"><?php esc_html_e( 'Shown next to the picture, under the price.', 'atelier-irisee-master-plugin' ); ?></p>
					<?php
					wp_editor(
						$get( AIMP_Catalog::META_INSPIRATION ),
						'aimp_inspiration',
						array(
							'textarea_name' => 'aimp_fabric[inspiration]',
							'textarea_rows' => 5,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
					?>
				</div>
				<div>
					<h4><label for="aimp_order_info"><?php esc_html_e( 'Order information', 'atelier-irisee-master-plugin' ); ?></label></h4>
					<p class="description"><?php esc_html_e( 'Shown under the inspiration text.', 'atelier-irisee-master-plugin' ); ?></p>
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

		$set( AIMP_Catalog::META_INSPIRATION, isset( $input['inspiration'] ) ? wp_kses_post( $input['inspiration'] ) : '' );
		$set( AIMP_Catalog::META_ORDER_INFO, isset( $input['order_info'] ) ? wp_kses_post( $input['order_info'] ) : '' );

		foreach ( AIMP_Catalog::fabric_text_fields() as $fields ) {
			foreach ( $fields as $subject => $field ) {
				$value = isset( $input[ $subject ] ) ? (string) $input[ $subject ] : '';
				$set( $field[0], 'tips' === $subject ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
			}
		}
	}
}
