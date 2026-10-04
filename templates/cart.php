<?php
/**
 * Cart page ([atelier_irisee_cart]).
 *
 * Copy this file to yourtheme/atelier-irisee/cart.php to change the markup.
 *
 * @var array $data contents (sets, singles), empty, shop_url, cart_url, crosssells.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="aimp-configurator aimp-cart woocommerce">

	<?php wc_print_notices(); ?>

	<?php if ( $data['empty'] ) : ?>
		<div class="aimp-cart-empty">
			<p><?php esc_html_e( 'Your cart is empty.', 'atelier-irisee-master-plugin' ); ?></p>
			<a class="aimp-button" href="<?php echo esc_url( $data['shop_url'] ); ?>"><?php esc_html_e( 'Browse the shop', 'atelier-irisee-master-plugin' ); ?></a>
		</div>
	<?php else : ?>

		<div class="aimp-cart-layout">
			<form class="aimp-cart-items" action="<?php echo esc_url( $data['cart_url'] ); ?>" method="post">

				<?php foreach ( $data['contents']['sets'] as $aimp_set ) : ?>
					<?php // A configurator set: one product, amounts locked. ?>
					<section class="aimp-cart-set">
						<div class="aimp-cart-set-head">
							<h3 class="aimp-cart-set-title">
								<?php
								/* translators: %s: pattern name and size */
								echo esc_html( sprintf( __( 'Sewing project kit: %s', 'atelier-irisee-master-plugin' ), $aimp_set['label'] ) );
								?>
							</h3>
							<a class="aimp-cart-remove" href="<?php echo esc_url( $aimp_set['remove_url'] ); ?>" aria-label="<?php esc_attr_e( 'Remove this set', 'atelier-irisee-master-plugin' ); ?>"><?php esc_html_e( 'Remove set', 'atelier-irisee-master-plugin' ); ?></a>
						</div>
						<ul class="aimp-cart-set-items">
							<?php foreach ( $aimp_set['items'] as $aimp_line ) : ?>
								<li class="aimp-cart-set-item">
									<span class="aimp-cart-thumb"><?php echo wp_kses_post( $aimp_line['image'] ); ?></span>
									<span class="aimp-cart-set-name">
										<?php if ( $aimp_line['permalink'] ) : ?>
											<a href="<?php echo esc_url( $aimp_line['permalink'] ); ?>"><?php echo esc_html( $aimp_line['name'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $aimp_line['name'] ); ?>
										<?php endif; ?>
										<?php if ( 'pattern' !== $aimp_line['role'] ) : ?>
											<small><?php echo esc_html( $aimp_line['amount'] ); ?></small>
										<?php endif; ?>
									</span>
									<span class="aimp-cart-set-price"><?php echo wp_kses_post( $aimp_line['subtotal'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="aimp-cart-set-total">
							<span><?php esc_html_e( 'Set total', 'atelier-irisee-master-plugin' ); ?></span>
							<strong><?php echo wp_kses_post( wc_price( $aimp_set['total'] ) ); ?></strong>
						</p>
						<p class="aimp-help aimp-cart-locked"><?php esc_html_e( 'The amounts of a set are fixed by the size.', 'atelier-irisee-master-plugin' ); ?></p>
					</section>
				<?php endforeach; ?>

				<?php if ( $data['contents']['singles'] ) : ?>
					<ul class="aimp-cart-singles">
						<?php foreach ( $data['contents']['singles'] as $aimp_line ) : ?>
							<li class="aimp-cart-single">
								<span class="aimp-cart-thumb"><?php echo wp_kses_post( $aimp_line['image'] ); ?></span>
								<div class="aimp-cart-single-info">
									<?php if ( $aimp_line['permalink'] ) : ?>
										<a class="aimp-cart-single-name" href="<?php echo esc_url( $aimp_line['permalink'] ); ?>"><?php echo esc_html( $aimp_line['name'] ); ?></a>
									<?php else : ?>
										<span class="aimp-cart-single-name"><?php echo esc_html( $aimp_line['name'] ); ?></span>
									<?php endif; ?>
									<?php echo wc_get_formatted_cart_item_data( $aimp_line['item'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce HTML. ?>
									<span class="aimp-cart-single-price"><?php echo wp_kses_post( $aimp_line['price'] ); ?><?php echo AIMP_Catalog::sold_per_10cm( $aimp_line['product'] ) ? ' <small>' . esc_html__( 'per 10 cm', 'atelier-irisee-master-plugin' ) . '</small>' : ''; ?></span>
								</div>
								<div class="aimp-cart-single-qty"><?php echo AIMP_Cart_Page::stepper_html( $aimp_line ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in stepper_html(). ?></div>
								<span class="aimp-cart-single-subtotal"><?php echo wp_kses_post( $aimp_line['subtotal'] ); ?></span>
								<a class="aimp-cart-remove aimp-cart-remove--x" href="<?php echo esc_url( wc_get_cart_remove_url( $aimp_line['key'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Remove %s from your cart', 'atelier-irisee-master-plugin' ), $aimp_line['name'] ) ); ?>">×</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="aimp-cart-actions">
					<div class="aimp-cart-coupon">
						<label class="screen-reader-text" for="aimp-coupon-code"><?php esc_html_e( 'Discount code', 'atelier-irisee-master-plugin' ); ?></label>
						<input type="text" id="aimp-coupon-code" name="coupon_code" value="" placeholder="<?php esc_attr_e( 'Discount code', 'atelier-irisee-master-plugin' ); ?>">
						<button type="submit" class="aimp-button" name="apply_coupon" value="1"><?php esc_html_e( 'Apply', 'atelier-irisee-master-plugin' ); ?></button>
					</div>
					<?php if ( $data['contents']['singles'] ) : ?>
						<button type="submit" class="aimp-button aimp-cart-update" name="update_cart" value="1"><?php esc_html_e( 'Update cart', 'atelier-irisee-master-plugin' ); ?></button>
					<?php endif; ?>
					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</div>
			</form>

			<aside class="aimp-cart-side">
				<?php woocommerce_cart_totals(); ?>
			</aside>
		</div>

		<?php if ( $data['crosssells'] ) : ?>
			<section class="aimp-product-section">
				<h3><?php esc_html_e( 'You may also like', 'atelier-irisee-master-plugin' ); ?></h3>
				<ul class="aimp-grid aimp-product-cards">
					<?php
					foreach ( $data['crosssells'] as $aimp_cross ) {
						echo AIMP_Product_Page::card_html( $aimp_cross ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
					}
					?>
				</ul>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
