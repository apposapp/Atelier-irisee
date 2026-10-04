<?php
/**
 * Product page in the Atelier Irisee design.
 *
 * Copy this file to yourtheme/atelier-irisee/product/single.php to change the markup.
 *
 * Available variables:
 *
 * @var WC_Product $the_product The product (also the global $product).
 * @var array      $data        From AIMP_Product_Page::data(): per_10cm, pattern, buy (buy bar data, or null for
 *                              WooCommerce's own form), fabric (texts, or null), gallery, category, attributes,
 *                              configurator_url, fitting, related.
 * @var array      $args        [ breadcrumb: bool ].
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

$aimp_gallery     = $data['gallery'];
$aimp_first       = $aimp_gallery ? $aimp_gallery[0] : null;
$aimp_id          = $the_product->get_id();
$aimp_fabric      = $data['fabric'];
$aimp_pattern     = $data['pattern'];
$aimp_buy         = $data['buy'];
$aimp_description = $the_product->get_description();
$aimp_has_desc    = '' !== trim( wp_strip_all_tags( $aimp_description ) );
$aimp_has_text    = function ( $html ) {
	return '' !== trim( wp_strip_all_tags( (string) $html ) );
};
$aimp_show_short  = $the_product->get_short_description() && ! ( $aimp_fabric && $aimp_has_text( $aimp_fabric['inspiration'] ) );
$aimp_attributes  = $aimp_fabric ? array() : $data['attributes']; // Fabrics show their specifications instead.
$aimp_weight      = ! $aimp_fabric && $the_product->has_weight() ? wc_format_weight( $the_product->get_weight() ) : '';
$aimp_dimensions  = ! $aimp_fabric && $the_product->has_dimensions() ? wc_format_dimensions( $the_product->get_dimensions( false ) ) : '';
$aimp_show_detail = ! $aimp_fabric && ! $aimp_pattern && ( $the_product->has_attributes() || $aimp_weight || $aimp_dimensions );
$aimp_show_review = wc_reviews_enabled() && comments_open( $aimp_id ) && is_singular( 'product' ) && (int) get_queried_object_id() === $aimp_id;

/**
 * A titled bullet list: "Label: value".
 *
 * @param string $aimp_title Title.
 * @param array  $aimp_items Label => value.
 */
$aimp_bullets = function ( $aimp_title, $aimp_items ) {
	if ( ! $aimp_items ) {
		return;
	}
	echo '<div class="aimp-product-bullets"><h4 class="aimp-product-subtitle">' . esc_html( $aimp_title ) . '</h4><ul>';
	foreach ( $aimp_items as $aimp_label => $aimp_value ) {
		echo '<li><strong>' . esc_html( $aimp_label ) . ':</strong> ' . nl2br( esc_html( $aimp_value ) ) . '</li>';
	}
	echo '</ul></div>';
};

/**
 * A text block with a gold title.
 *
 * @param string $aimp_title Title.
 * @param string $aimp_html  Text (HTML).
 * @param string $aimp_class Extra class.
 */
$aimp_text_block = function ( $aimp_title, $aimp_html, $aimp_class ) {
	echo '<div class="aimp-product-text ' . esc_attr( $aimp_class ) . '"><h4 class="aimp-product-subtitle">' . esc_html( $aimp_title ) . '</h4>';
	echo '<div class="aimp-description">' . wp_kses_post( $aimp_html ) . '</div></div>';
};
?>
<div class="aimp-configurator aimp-product alignwide<?php echo $aimp_fabric ? ' aimp-product--fabric' : ''; ?><?php echo $aimp_pattern ? ' aimp-product--pattern' : ''; ?>" data-aimp-product="<?php echo esc_attr( $aimp_id ); ?>">

	<div class="aimp-topbar aimp-product-topbar">
		<?php
		if ( $args['breadcrumb'] && function_exists( 'woocommerce_breadcrumb' ) ) {
			woocommerce_breadcrumb();
		}
		echo AIMP_Product_Page::languages_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in languages_html().
		?>
	</div>

	<?php do_action( 'woocommerce_before_single_product' ); ?>

	<div id="product-<?php echo esc_attr( $aimp_id ); ?>" <?php wc_product_class( 'aimp-product-main', $the_product ); ?>>

		<div class="aimp-product-media">
			<?php if ( $aimp_first ) : ?>
				<div class="aimp-gallery" data-aimp-gallery="<?php echo esc_attr( wp_json_encode( $aimp_gallery ) ); ?>">
					<div class="aimp-gallery-main aimp-fav-wrap">
						<img src="<?php echo esc_url( $aimp_first['large'] ); ?>" alt="<?php echo esc_attr( $aimp_first['alt'] ); ?>" class="is-zoomable">
						<?php echo AIMP_Favorites::button_html( $aimp_id, 'aimp-fav--overlay' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button_html(). ?>
					</div>
					<?php if ( count( $aimp_gallery ) > 1 ) : ?>
						<div class="aimp-gallery-thumbs">
							<?php foreach ( $aimp_gallery as $aimp_index => $aimp_image ) : ?>
								<button type="button" class="aimp-thumb<?php echo 0 === $aimp_index ? ' is-active' : ''; ?>" data-index="<?php echo esc_attr( $aimp_index ); ?>" aria-pressed="<?php echo 0 === $aimp_index ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: picture number */ __( 'Show picture %d', 'atelier-irisee-master-plugin' ), $aimp_index + 1 ) ); ?>">
									<img src="<?php echo esc_url( $aimp_image['thumb'] ); ?>" alt="" loading="lazy">
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="aimp-product-summary">
			<?php if ( $data['category'] ) : ?>
				<p class="aimp-details-eyebrow"><a href="<?php echo esc_url( get_term_link( $data['category'] ) ); ?>"><?php echo esc_html( $data['category']->name ); ?></a></p>
			<?php endif; ?>

			<h1 class="aimp-product-title"><?php echo esc_html( wp_strip_all_tags( $the_product->get_name() ) ); ?></h1>

			<?php if ( wc_review_ratings_enabled() && $the_product->get_rating_count() > 0 ) : ?>
				<div class="aimp-product-rating">
					<?php echo wc_get_rating_html( $the_product->get_average_rating(), $the_product->get_rating_count() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce HTML. ?>
					<?php if ( $aimp_show_review ) : ?>
						<a href="#aimp-reviews">
							<?php
							/* translators: %d: number of reviews */
							echo esc_html( sprintf( _n( '%d review', '%d reviews', $the_product->get_review_count(), 'atelier-irisee-master-plugin' ), $the_product->get_review_count() ) );
							?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $aimp_buy ) : ?>
				<?php // Buy bar: quantity and button on the left, price on the right. ?>
				<div class="aimp-buy-bar" data-aimp-buy data-unit-price="<?php echo esc_attr( wc_format_decimal( $aimp_buy['unit_price'], wc_get_price_decimals() ) ); ?>" data-per-10cm="<?php echo $aimp_buy['per_10cm'] ? '1' : '0'; ?>">
					<div class="aimp-buy-controls">
						<?php if ( $aimp_buy['available'] ) : ?>
							<form class="aimp-buy-form" action="<?php echo esc_url( $the_product->get_permalink() ); ?>" method="post">
								<div class="aimp-stepper">
									<button type="button" class="aimp-stepper-btn" data-dir="-1" aria-label="<?php esc_attr_e( 'Less', 'atelier-irisee-master-plugin' ); ?>">‹</button>
									<input type="number" class="aimp-stepper-input" inputmode="numeric"
										name="<?php echo $aimp_buy['per_10cm'] ? 'aimp_length_cm' : 'quantity'; ?>"
										value="<?php echo esc_attr( $aimp_buy['min'] ); ?>"
										min="<?php echo esc_attr( $aimp_buy['min'] ); ?>"
										step="<?php echo esc_attr( $aimp_buy['step'] ); ?>"
										<?php echo $aimp_buy['max'] ? 'max="' . esc_attr( $aimp_buy['max'] ) . '"' : ''; ?>
										aria-label="<?php echo esc_attr( $aimp_buy['per_10cm'] ? __( 'Length in cm', 'atelier-irisee-master-plugin' ) : __( 'Quantity', 'atelier-irisee-master-plugin' ) ); ?>">
									<?php if ( $aimp_buy['per_10cm'] ) : ?>
										<span class="aimp-stepper-unit"><?php esc_html_e( 'cm', 'atelier-irisee-master-plugin' ); ?></span>
									<?php endif; ?>
									<button type="button" class="aimp-stepper-btn" data-dir="1" aria-label="<?php esc_attr_e( 'More', 'atelier-irisee-master-plugin' ); ?>">›</button>
								</div>
								<?php if ( $aimp_buy['pattern'] ) : ?>
									<input type="hidden" name="aimp_add_pattern" value="<?php echo esc_attr( $aimp_id ); ?>">
									<?php wp_nonce_field( 'aimp_add_pattern', 'aimp_pattern_nonce', false ); ?>
								<?php else : ?>
									<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $aimp_id ); ?>">
								<?php endif; ?>
								<button type="submit" class="aimp-button aimp-add-to-cart"><?php esc_html_e( 'Add to cart', 'atelier-irisee-master-plugin' ); ?></button>
							</form>
						<?php else : ?>
							<button type="button" class="aimp-button aimp-add-to-cart" disabled><?php esc_html_e( 'Add to cart', 'atelier-irisee-master-plugin' ); ?></button>
						<?php endif; ?>
					</div>
					<div class="aimp-buy-price">
						<?php if ( '' !== $the_product->get_price_html() ) : ?>
							<p class="aimp-details-price price" data-aimp-price><?php echo wp_kses_post( $the_product->get_price_html() ); ?></p>
						<?php endif; ?>
						<?php if ( $aimp_buy['per_10cm'] ) : ?>
							<p class="aimp-price-unit" data-aimp-unit-line><?php esc_html_e( 'per 10 cm', 'atelier-irisee-master-plugin' ); ?></p>
						<?php else : ?>
							<p class="aimp-price-unit" data-aimp-unit-line hidden></p>
						<?php endif; ?>
						<?php if ( ! $aimp_buy['available'] ) : ?>
							<p class="aimp-sold-out"><?php esc_html_e( 'Out of stock', 'atelier-irisee-master-plugin' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php elseif ( '' !== $the_product->get_price_html() ) : ?>
				<div class="aimp-product-price">
					<p class="aimp-details-price price"><?php echo wp_kses_post( $the_product->get_price_html() ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			if ( $aimp_fabric && $aimp_has_text( $aimp_fabric['inspiration'] ) ) {
				$aimp_text_block( __( 'Inspiration', 'atelier-irisee-master-plugin' ), wpautop( $aimp_fabric['inspiration'] ), 'aimp-product-inspiration' );
			}
			if ( $aimp_fabric && $aimp_has_text( $aimp_fabric['order_info'] ) ) {
				$aimp_text_block( __( 'Order information', 'atelier-irisee-master-plugin' ), wpautop( $aimp_fabric['order_info'] ), 'aimp-product-order-info' );
			}
			?>

			<?php if ( $aimp_show_short ) : ?>
				<div class="aimp-description aimp-product-short"><?php echo wp_kses_post( wc_format_content( $the_product->get_short_description() ) ); ?></div>
			<?php endif; ?>

			<?php if ( ! $aimp_buy ) : ?>
				<div class="aimp-product-buy"><?php woocommerce_template_single_add_to_cart(); ?></div>
			<?php endif; ?>

			<?php
			// Patterns: description and details here, next to the picture.
			if ( $aimp_pattern && $aimp_has_desc ) {
				$aimp_text_block( __( 'Description', 'atelier-irisee-master-plugin' ), wc_format_content( $aimp_description ), 'aimp-product-description' );
			}
			?>

			<?php if ( $aimp_attributes || $aimp_weight || $aimp_dimensions || $the_product->get_sku() ) : ?>
				<div class="aimp-product-details">
					<?php if ( $aimp_pattern ) : ?>
						<h4 class="aimp-product-subtitle"><?php esc_html_e( 'Details', 'atelier-irisee-master-plugin' ); ?></h4>
					<?php endif; ?>
					<dl class="aimp-info-list">
						<?php foreach ( $aimp_attributes as $aimp_attribute ) : ?>
							<div><dt><?php echo esc_html( $aimp_attribute['label'] ); ?></dt><dd><?php echo esc_html( $aimp_attribute['value'] ); ?></dd></div>
						<?php endforeach; ?>
						<?php if ( $aimp_pattern && $aimp_weight ) : ?>
							<div><dt><?php esc_html_e( 'Weight', 'atelier-irisee-master-plugin' ); ?></dt><dd><?php echo esc_html( $aimp_weight ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $aimp_pattern && $aimp_dimensions ) : ?>
							<div><dt><?php esc_html_e( 'Dimensions', 'atelier-irisee-master-plugin' ); ?></dt><dd><?php echo esc_html( $aimp_dimensions ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $the_product->get_sku() ) : ?>
							<div><dt><?php esc_html_e( 'Item number', 'atelier-irisee-master-plugin' ); ?></dt><dd><?php echo esc_html( $the_product->get_sku() ); ?></dd></div>
						<?php endif; ?>
					</dl>
				</div>
			<?php endif; ?>

			<?php
			if ( $aimp_fabric ) {
				$aimp_bullets( __( 'Specifications', 'atelier-irisee-master-plugin' ), $aimp_fabric['specs'] );
			}
			?>

			<?php if ( $data['configurator_url'] ) : ?>
				<div class="aimp-product-configure">
					<p class="aimp-help"><?php esc_html_e( 'Choose the fabric and haberdashery that fit your size, all in one go.', 'atelier-irisee-master-plugin' ); ?></p>
					<a class="aimp-button aimp-button--block" href="<?php echo esc_url( $data['configurator_url'] ); ?>"><?php esc_html_e( 'Complete it in the configurator', 'atelier-irisee-master-plugin' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $aimp_fabric && ( $aimp_has_desc || $aimp_fabric['washing'] ) ) : ?>
		<?php // Fabrics: description and washing instructions side by side. ?>
		<section class="aimp-product-section aimp-product-row<?php echo $aimp_has_desc && $aimp_fabric['washing'] ? '' : ' is-single'; ?>">
			<?php if ( $aimp_has_desc ) : ?>
				<div>
					<h3><?php esc_html_e( 'Description', 'atelier-irisee-master-plugin' ); ?></h3>
					<div class="aimp-description aimp-product-description"><?php echo wc_format_content( $aimp_description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product content, filtered like the WooCommerce description tab. ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $aimp_fabric['washing'] ) : ?>
				<div><?php $aimp_bullets( __( 'Washing instructions', 'atelier-irisee-master-plugin' ), $aimp_fabric['washing'] ); ?></div>
			<?php endif; ?>
		</section>
	<?php elseif ( ! $aimp_fabric && ! $aimp_pattern && $aimp_has_desc ) : ?>
		<section class="aimp-product-section">
			<h3><?php esc_html_e( 'Description', 'atelier-irisee-master-plugin' ); ?></h3>
			<div class="aimp-description aimp-product-description"><?php echo wc_format_content( $aimp_description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product content, filtered like the WooCommerce description tab. ?></div>
		</section>
	<?php endif; ?>

	<?php if ( $aimp_show_detail ) : ?>
		<section class="aimp-product-section">
			<h3><?php esc_html_e( 'Details', 'atelier-irisee-master-plugin' ); ?></h3>
			<?php wc_display_product_attributes( $the_product ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['fitting'] ) : ?>
		<section class="aimp-product-section">
			<h3><?php esc_html_e( 'Fits these patterns', 'atelier-irisee-master-plugin' ); ?></h3>
			<ul class="aimp-grid aimp-product-cards">
				<?php
				foreach ( $data['fitting'] as $aimp_fit ) {
					echo AIMP_Product_Page::card_html( $aimp_fit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
				}
				?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $aimp_show_review ) : ?>
		<section class="aimp-product-section aimp-product-reviews" id="aimp-reviews">
			<?php comments_template(); ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['related'] ) : ?>
		<section class="aimp-product-section">
			<h3><?php esc_html_e( 'You may also like', 'atelier-irisee-master-plugin' ); ?></h3>
			<ul class="aimp-grid aimp-product-cards">
				<?php
				foreach ( $data['related'] as $aimp_related ) {
					echo AIMP_Product_Page::card_html( $aimp_related ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
				}
				?>
			</ul>
		</section>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_single_product' ); ?>
</div>
