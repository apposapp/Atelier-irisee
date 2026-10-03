<?php
/**
 * Product page in the Atelier Irisee design.
 *
 * Copy this file to yourtheme/atelier-irisee/product/single.php to change the markup.
 *
 * Available variables:
 *
 * @var WC_Product $the_product The product (also the global $product).
 * @var array      $data        From AIMP_Product_Page::data(): per_10cm, gallery, category, stock_text,
 *                              attributes, sizes, configurator_url, fitting, related.
 * @var array      $args        [ breadcrumb: bool ].
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

$aimp_gallery = $data['gallery'];
$aimp_first   = $aimp_gallery ? $aimp_gallery[0] : null;
$aimp_id      = $the_product->get_id();

// Body measurements that are filled in for at least one size.
$aimp_measures = array();
foreach (
	array(
		'bust'       => __( 'Bust', 'atelier-irisee-master-plugin' ),
		'waist'      => __( 'Waist', 'atelier-irisee-master-plugin' ),
		'hip'        => __( 'Hip', 'atelier-irisee-master-plugin' ),
		'inside_leg' => __( 'Inside leg', 'atelier-irisee-master-plugin' ),
		'height'     => __( 'Height', 'atelier-irisee-master-plugin' ),
	) as $aimp_key => $aimp_label
) {
	foreach ( $data['sizes'] as $aimp_size ) {
		if ( '' !== (string) $aimp_size[ $aimp_key ] ) {
			$aimp_measures[ $aimp_key ] = $aimp_label;
			break;
		}
	}
}

$aimp_show_details = $the_product->has_attributes() || apply_filters( 'wc_product_enable_dimensions_display', $the_product->has_weight() || $the_product->has_dimensions() );
$aimp_show_reviews = wc_reviews_enabled() && comments_open( $aimp_id ) && is_singular( 'product' ) && (int) get_queried_object_id() === $aimp_id;
$aimp_description  = $the_product->get_description();
?>
<div class="aimp-configurator aimp-product alignwide" data-aimp-product="<?php echo esc_attr( $aimp_id ); ?>">

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
					<?php if ( $aimp_show_reviews ) : ?>
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
				<p class="aimp-details-price price">
					<?php echo wp_kses_post( $the_product->get_price_html() ); ?>
					<?php if ( $data['per_10cm'] ) : ?>
						<small><?php esc_html_e( 'per 10 cm', 'atelier-irisee-master-plugin' ); ?></small>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $the_product->get_short_description() ) : ?>
				<div class="aimp-description"><?php echo wp_kses_post( wc_format_content( $the_product->get_short_description() ) ); ?></div>
			<?php endif; ?>

			<?php if ( $data['attributes'] || $data['stock_text'] || $the_product->get_sku() ) : ?>
				<dl class="aimp-info-list">
					<?php foreach ( $data['attributes'] as $aimp_attribute ) : ?>
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

			<div class="aimp-product-buy"<?php echo $data['per_10cm'] ? ' data-aimp-per-10cm' : ''; ?>>
				<?php woocommerce_template_single_add_to_cart(); ?>
				<?php if ( $data['per_10cm'] && $the_product->is_purchasable() && $the_product->is_in_stock() ) : ?>
					<p class="aimp-help aimp-unit-help" data-aimp-unit-help aria-live="polite"></p>
				<?php endif; ?>
			</div>

			<?php if ( $data['configurator_url'] ) : ?>
				<div class="aimp-product-configure">
					<p class="aimp-help"><?php esc_html_e( 'Choose the fabric and haberdashery that fit your size, all in one go.', 'atelier-irisee-master-plugin' ); ?></p>
					<a class="aimp-button aimp-button--block aimp-button--solid" href="<?php echo esc_url( $data['configurator_url'] ); ?>"><?php esc_html_e( 'Complete it in the configurator', 'atelier-irisee-master-plugin' ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( $data['sizes'] && $aimp_measures ) : ?>
				<section class="aimp-product-sizes">
					<div class="aimp-product-sizes-head">
						<h2><?php esc_html_e( 'Size chart for this pattern', 'atelier-irisee-master-plugin' ); ?></h2>
						<button type="button" class="aimp-button aimp-measure-button" data-aimp-measure><?php esc_html_e( 'How to measure your body measurements', 'atelier-irisee-master-plugin' ); ?></button>
					</div>
					<div class="aimp-table-scroll">
						<table class="aimp-size-chart">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Size', 'atelier-irisee-master-plugin' ); ?></th>
									<?php foreach ( $aimp_measures as $aimp_label ) : ?>
										<th scope="col"><?php echo esc_html( $aimp_label ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $data['sizes'] as $aimp_size ) : ?>
									<tr>
										<th scope="row"><?php echo esc_html( $aimp_size['label'] ); ?></th>
										<?php foreach ( array_keys( $aimp_measures ) as $aimp_key ) : ?>
											<?php $aimp_value = (string) $aimp_size[ $aimp_key ]; ?>
											<td><?php echo '' === $aimp_value ? '–' : esc_html( AIMP_I18n::number( (float) $aimp_value, false !== strpos( $aimp_value, '.' ) ? 1 : 0 ) . ' ' . __( 'cm', 'atelier-irisee-master-plugin' ) ); ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( '' !== trim( $aimp_description ) ) : ?>
		<section class="aimp-product-section">
			<h2><?php esc_html_e( 'Description', 'atelier-irisee-master-plugin' ); ?></h2>
			<div class="aimp-description aimp-product-description"><?php echo wc_format_content( $aimp_description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product content, filtered like the WooCommerce description tab. ?></div>
		</section>
	<?php endif; ?>

	<?php if ( $aimp_show_details ) : ?>
		<section class="aimp-product-section">
			<h2><?php esc_html_e( 'Details', 'atelier-irisee-master-plugin' ); ?></h2>
			<?php wc_display_product_attributes( $the_product ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['fitting'] ) : ?>
		<section class="aimp-product-section">
			<h2><?php esc_html_e( 'Fits these patterns', 'atelier-irisee-master-plugin' ); ?></h2>
			<ul class="aimp-grid aimp-product-cards">
				<?php
				foreach ( $data['fitting'] as $aimp_pattern ) {
					echo AIMP_Product_Page::card_html( $aimp_pattern ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
				}
				?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $aimp_show_reviews ) : ?>
		<section class="aimp-product-section aimp-product-reviews" id="aimp-reviews">
			<?php comments_template(); ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['related'] ) : ?>
		<section class="aimp-product-section">
			<h2><?php esc_html_e( 'You may also like', 'atelier-irisee-master-plugin' ); ?></h2>
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
