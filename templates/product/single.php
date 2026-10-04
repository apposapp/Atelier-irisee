<?php
/**
 * Product page in the Atelier Irisee design.
 *
 * Copy this file to yourtheme/atelier-irisee/product/single.php to change the markup.
 *
 * Available variables:
 *
 * @var WC_Product $the_product The product (also the global $product).
 * @var array      $data        From AIMP_Product_Page::data(): per_10cm, unit_price, gallery, category, stock_text,
 *                              attributes, fabric (texts, or null for other products), configurator_url,
 *                              fitting, related.
 * @var array      $args        [ breadcrumb: bool ].
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

$aimp_gallery     = $data['gallery'];
$aimp_first       = $aimp_gallery ? $aimp_gallery[0] : null;
$aimp_id          = $the_product->get_id();
$aimp_fabric      = $data['fabric'];
$aimp_description = $the_product->get_description();
$aimp_has_desc    = '' !== trim( wp_strip_all_tags( $aimp_description ) );
$aimp_show_short  = $the_product->get_short_description() && ! ( $aimp_fabric && '' !== trim( wp_strip_all_tags( $aimp_fabric['inspiration'] ) ) );
$aimp_attributes  = $aimp_fabric ? array() : $data['attributes']; // Fabrics show their specifications instead.
$aimp_show_detail = ! $aimp_fabric && ( $the_product->has_attributes() || apply_filters( 'wc_product_enable_dimensions_display', $the_product->has_weight() || $the_product->has_dimensions() ) );
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
?>
<div class="aimp-configurator aimp-product alignwide<?php echo $aimp_fabric ? ' aimp-product--fabric' : ''; ?>" data-aimp-product="<?php echo esc_attr( $aimp_id ); ?>">

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

			<?php if ( '' !== $the_product->get_price_html() ) : ?>
				<div class="aimp-product-price">
					<p class="aimp-details-price price" data-aimp-price><?php echo wp_kses_post( $the_product->get_price_html() ); ?></p>
					<?php if ( $data['per_10cm'] ) : ?>
						<p class="aimp-price-unit" data-aimp-unit-line><?php esc_html_e( 'per 10 cm', 'atelier-irisee-master-plugin' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $aimp_fabric && '' !== trim( wp_strip_all_tags( $aimp_fabric['inspiration'] ) ) ) : ?>
				<div class="aimp-description aimp-product-inspiration"><?php echo wp_kses_post( wpautop( $aimp_fabric['inspiration'] ) ); ?></div>
			<?php endif; ?>

			<?php if ( $aimp_fabric && '' !== trim( wp_strip_all_tags( $aimp_fabric['order_info'] ) ) ) : ?>
				<div class="aimp-description aimp-product-order-info"><?php echo wp_kses_post( wpautop( $aimp_fabric['order_info'] ) ); ?></div>
			<?php endif; ?>

			<?php if ( $aimp_show_short ) : ?>
				<div class="aimp-description"><?php echo wp_kses_post( wc_format_content( $the_product->get_short_description() ) ); ?></div>
			<?php endif; ?>

			<div class="aimp-product-buy"<?php echo $data['per_10cm'] ? ' data-aimp-per-10cm data-unit-price="' . esc_attr( wc_format_decimal( $data['unit_price'], wc_get_price_decimals() ) ) . '"' : ''; ?>>
				<?php woocommerce_template_single_add_to_cart(); ?>
			</div>

			<?php if ( $aimp_attributes || $data['stock_text'] || $the_product->get_sku() ) : ?>
				<dl class="aimp-info-list">
					<?php foreach ( $aimp_attributes as $aimp_attribute ) : ?>
						<div><dt><?php echo esc_html( $aimp_attribute['label'] ); ?></dt><dd><?php echo esc_html( $aimp_attribute['value'] ); ?></dd></div>
					<?php endforeach; ?>
					<?php if ( $data['stock_text'] ) : ?>
						<div><dt><?php esc_html_e( 'Stock', 'atelier-irisee-master-plugin' ); ?></dt><dd class="<?php echo $the_product->is_in_stock() ? '' : 'is-out'; ?>"><?php echo esc_html( $data['stock_text'] ); ?></dd></div>
					<?php endif; ?>
					<?php if ( $the_product->get_sku() ) : ?>
						<div><dt><?php esc_html_e( 'Item number', 'atelier-irisee-master-plugin' ); ?></dt><dd><?php echo esc_html( $the_product->get_sku() ); ?></dd></div>
					<?php endif; ?>
				</dl>
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
	<?php elseif ( ! $aimp_fabric && $aimp_has_desc ) : ?>
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
				foreach ( $data['fitting'] as $aimp_pattern ) {
					echo AIMP_Product_Page::card_html( $aimp_pattern ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
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
