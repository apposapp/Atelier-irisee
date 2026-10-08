<?php
/**
 * Product page in the Atelier Irisee design.
 *
 * Copy this file to yourtheme/atelier-irisee/product/single.php to change the markup.
 *
 * Available variables:
 *
 * @var WC_Product $the_product The product (also the global $product).
 * @var array      $data        From AIMP_Product_Page::data(): per_10cm, pattern, skill, sizes_text, recommended, fabrics, buy (buy bar data, or null for
 *                              WooCommerce's own form), fabric (texts, or null), gallery, attributes,
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
$aimp_giftcard    = $aimp_buy && ! empty( $aimp_buy['giftcard'] );
$aimp_form_id     = 'aimp-buy-' . $aimp_id;
$aimp_description = $the_product->get_description();
$aimp_has_desc    = '' !== trim( wp_strip_all_tags( $aimp_description ) );
$aimp_has_text    = function ( $html ) {
	return '' !== trim( wp_strip_all_tags( (string) $html ) );
};
$aimp_insp_cards  = $aimp_fabric ? $aimp_fabric['inspiration_cards'] : array();
$aimp_show_short  = $the_product->get_short_description() && ! $aimp_insp_cards;
$aimp_attributes  = $aimp_fabric ? array() : $data['attributes']; // Fabrics show their specifications instead.
$aimp_weight      = ! $aimp_fabric && $the_product->has_weight() ? wc_format_weight( $the_product->get_weight() ) : '';
$aimp_dimensions  = ! $aimp_fabric && $the_product->has_dimensions() ? wc_format_dimensions( $the_product->get_dimensions( false ) ) : '';
$aimp_show_detail = ! $aimp_fabric && ! $aimp_pattern && ! $aimp_giftcard && ( $the_product->has_attributes() || $aimp_weight || $aimp_dimensions );
$aimp_show_review = wc_reviews_enabled() && comments_open( $aimp_id ) && is_singular( 'product' ) && (int) get_queried_object_id() === $aimp_id;
$aimp_skills      = AIMP_Catalog::skill_levels();

/**
 * A dropdown with a gold title and a gold arrow on the right; closed when the page loads.
 *
 * @param string $aimp_title Title.
 * @param string $aimp_body  Content (HTML).
 * @param string $aimp_class Extra class.
 */
$aimp_dropdown = function ( $aimp_title, $aimp_body, $aimp_class ) {
	echo '<details class="aimp-dropdown ' . esc_attr( $aimp_class ) . '"><summary><h4 class="aimp-product-subtitle">' . esc_html( $aimp_title ) . '</h4>';
	echo '<span class="aimp-dropdown-arrow" aria-hidden="true"></span></summary><div class="aimp-dropdown-body">' . $aimp_body . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the callers.
};

/**
 * Tiles with a gold icon, the subject and the text (specifications and washing instructions).
 *
 * @param array $aimp_lines Subject => [ label, value ].
 * @return string
 */
$aimp_tiles = function ( $aimp_lines ) {
	if ( ! $aimp_lines ) {
		return '';
	}
	$aimp_html = '<ul class="aimp-washing-grid">';
	foreach ( $aimp_lines as $aimp_subject => $aimp_line ) {
		$aimp_html .= '<li class="aimp-washing-tile">' . AIMP_Product_Page::fabric_icon( $aimp_subject ) .
			'<span><strong>' . esc_html( $aimp_line[0] ) . '</strong>' . nl2br( esc_html( $aimp_line[1] ) ) . '</span></li>';
	}
	return $aimp_html . '</ul>';
};
$aimp_washing_html = $aimp_fabric ? $aimp_tiles( $aimp_fabric['washing'] ) : '';
$aimp_specs_html   = $aimp_fabric ? $aimp_tiles( $aimp_fabric['specs'] ) : '';
?>
<div class="aimp-configurator aimp-product alignwide<?php echo $aimp_fabric ? ' aimp-product--fabric' : ''; ?><?php echo $aimp_pattern ? ' aimp-product--pattern' : ''; ?><?php echo $aimp_giftcard ? ' aimp-product--giftcard' : ''; ?>" data-aimp-product="<?php echo esc_attr( $aimp_id ); ?>">

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
				<?php // Buy bar: price on the left, quantity and button on the right. ?>
				<div class="aimp-buy-bar" data-aimp-buy data-unit-price="<?php echo esc_attr( wc_format_decimal( $aimp_buy['unit_price'], wc_get_price_decimals() ) ); ?>" data-per-10cm="<?php echo $aimp_buy['per_10cm'] ? '1' : '0'; ?>"<?php echo $aimp_giftcard ? ' data-giftcard' : ''; ?>>
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
					<div class="aimp-buy-controls">
						<?php if ( $aimp_buy['available'] ) : ?>
							<?php if ( ! $aimp_giftcard ) : ?>
								<form class="aimp-buy-form" id="<?php echo esc_attr( $aimp_form_id ); ?>" action="<?php echo esc_url( $the_product->get_permalink() ); ?>" method="post">
							<?php endif; ?>
							<div class="aimp-stepper">
								<?php if ( $aimp_giftcard ) : ?>
									<span class="aimp-stepper-unit aimp-stepper-unit--before"><?php echo esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?></span>
								<?php endif; ?>
								<button type="button" class="aimp-stepper-btn" data-dir="-1" aria-label="<?php esc_attr_e( 'Less', 'atelier-irisee-master-plugin' ); ?>">‹</button>
								<input type="number" class="aimp-stepper-input" inputmode="numeric" form="<?php echo esc_attr( $aimp_form_id ); ?>"
									name="<?php echo esc_attr( $aimp_giftcard ? 'aimp_gc[custom_amount]' : ( $aimp_buy['per_10cm'] ? 'aimp_length_cm' : 'quantity' ) ); ?>"
									value="<?php echo esc_attr( $aimp_buy['value'] ); ?>"
									min="<?php echo esc_attr( $aimp_buy['min'] ); ?>"
									step="<?php echo esc_attr( $aimp_buy['step'] ); ?>"
									<?php echo $aimp_buy['max'] ? 'max="' . esc_attr( $aimp_buy['max'] ) . '"' : ''; ?>
									aria-label="<?php echo esc_attr( $aimp_giftcard ? __( 'Gift card value', 'atelier-irisee-master-plugin' ) : ( $aimp_buy['per_10cm'] ? __( 'Length in cm', 'atelier-irisee-master-plugin' ) : __( 'Quantity', 'atelier-irisee-master-plugin' ) ) ); ?>">
								<?php if ( $aimp_buy['per_10cm'] ) : ?>
									<span class="aimp-stepper-unit"><?php esc_html_e( 'cm', 'atelier-irisee-master-plugin' ); ?></span>
								<?php endif; ?>
								<button type="button" class="aimp-stepper-btn" data-dir="1" aria-label="<?php esc_attr_e( 'More', 'atelier-irisee-master-plugin' ); ?>">›</button>
							</div>
							<?php if ( $aimp_buy['pattern'] ) : ?>
								<input type="hidden" name="aimp_add_pattern" value="<?php echo esc_attr( $aimp_id ); ?>">
								<?php wp_nonce_field( 'aimp_add_pattern', 'aimp_pattern_nonce', false ); ?>
							<?php elseif ( ! $aimp_giftcard ) : ?>
								<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $aimp_id ); ?>">
							<?php endif; ?>
							<button type="submit" class="aimp-button aimp-add-to-cart" form="<?php echo esc_attr( $aimp_form_id ); ?>"><?php esc_html_e( 'Add to cart', 'atelier-irisee-master-plugin' ); ?></button>
							<?php if ( ! $aimp_giftcard ) : ?>
								</form>
							<?php endif; ?>
						<?php else : ?>
							<button type="button" class="aimp-button aimp-add-to-cart" disabled><?php esc_html_e( 'Add to cart', 'atelier-irisee-master-plugin' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( ! $aimp_buy['available'] && ! $aimp_giftcard ) : ?>
					<?php echo AIMP_Stock_Alerts::form_html( $the_product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in form_html(). ?>
				<?php endif; ?>
				<?php if ( ! empty( $aimp_buy['low'] ) ) : ?>
					<p class="aimp-low-stock">
						<?php
						echo esc_html(
							$aimp_buy['per_10cm']
								/* translators: %d: centimetres */
								? sprintf( __( 'Only %d cm left', 'atelier-irisee-master-plugin' ), $aimp_buy['low'] )
								/* translators: %d: number of items */
								: sprintf( _n( 'Only %d left', 'Only %d left', $aimp_buy['low'], 'atelier-irisee-master-plugin' ), $aimp_buy['low'] )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $aimp_buy['available'] && ! $aimp_giftcard ) : ?>
					<?php echo AIMP_Trust::delivery_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in delivery_html(). ?>
				<?php endif; ?>
			<?php elseif ( '' !== $the_product->get_price_html() ) : ?>
				<div class="aimp-product-price">
					<p class="aimp-details-price price"><?php echo wp_kses_post( $the_product->get_price_html() ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			// Patterns: skill level, sizes and project time as tiles with an icon (only those filled in).
			$aimp_pattern_tiles = array();
			if ( $aimp_pattern && isset( $aimp_skills[ $data['skill'] ] ) ) {
				$aimp_level = array_search( $data['skill'], array_keys( $aimp_skills ), true ) + 1;
				$aimp_stars = '';
				for ( $aimp_star = 1; $aimp_star <= count( $aimp_skills ); $aimp_star++ ) {
					$aimp_stars .= $aimp_star <= $aimp_level ? '★' : '☆';
				}
				$aimp_pattern_tiles['sewing_machine'] = array(
					__( 'Skill level', 'atelier-irisee-master-plugin' ),
					'<span class="aimp-pattern-stars" aria-hidden="true">' . $aimp_stars . '</span><span class="aimp-pattern-level">' . esc_html( $aimp_skills[ $data['skill'] ] ) . '</span>',
				);
			}
			if ( $aimp_pattern && '' !== trim( $data['sizes_text'] ) ) {
				$aimp_pattern_tiles['tape_measure'] = array( __( 'Sizes', 'atelier-irisee-master-plugin' ), esc_html( $data['sizes_text'] ) );
			}
			$aimp_designs = ! empty( $data['designs'] ) ? $data['designs'] : array();
			if ( $aimp_designs ) {
				// A pack with designs: their names, and the project time per design when it differs.
				$aimp_pattern_tiles['designs'] = array( __( 'Designs', 'atelier-irisee-master-plugin' ), esc_html( implode( ' · ', wp_list_pluck( $aimp_designs, 'name' ) ) ) );
				$aimp_times                    = array_filter( wp_list_pluck( $aimp_designs, 'project_time' ), 'strlen' );
				if ( $aimp_times ) {
					if ( 1 === count( array_unique( $aimp_times ) ) && count( $aimp_times ) === count( $aimp_designs ) ) {
						$aimp_time_text = esc_html( reset( $aimp_times ) );
					} else {
						$aimp_time_parts = array();
						foreach ( $aimp_designs as $aimp_design ) {
							if ( '' !== $aimp_design['project_time'] ) {
								$aimp_time_parts[] = esc_html( $aimp_design['name'] . ' ' . $aimp_design['project_time'] );
							}
						}
						$aimp_time_text = implode( '<br>', $aimp_time_parts );
					}
					$aimp_pattern_tiles['clock'] = array( __( 'Project time', 'atelier-irisee-master-plugin' ), $aimp_time_text );
				}
			} elseif ( $aimp_pattern && '' !== trim( $data['project_time'] ) ) {
				$aimp_pattern_tiles['clock'] = array( __( 'Project time', 'atelier-irisee-master-plugin' ), esc_html( $data['project_time'] ) );
			}
			?>
			<?php if ( $aimp_pattern_tiles ) : ?>
				<ul class="aimp-pattern-tiles">
					<?php foreach ( $aimp_pattern_tiles as $aimp_icon => $aimp_tile ) : ?>
						<li class="aimp-pattern-tile">
							<?php echo AIMP_Product_Page::fabric_icon( $aimp_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
							<span class="aimp-pattern-tile-label"><?php echo esc_html( $aimp_tile[0] ); ?></span>
							<span class="aimp-pattern-tile-value"><?php echo $aimp_tile[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $aimp_fabric && $aimp_has_text( $aimp_fabric['order_info'] ) ) : ?>
				<div class="aimp-description aimp-product-order-info"><?php echo wp_kses_post( wpautop( $aimp_fabric['order_info'] ) ); ?></div>
			<?php endif; ?>

			<?php if ( $aimp_show_short ) : ?>
				<h4 class="aimp-product-subtitle aimp-product-short-title"><?php esc_html_e( 'Description', 'atelier-irisee-master-plugin' ); ?></h4>
				<div class="aimp-description aimp-product-short"><?php echo wp_kses_post( wc_format_content( $the_product->get_short_description() ) ); ?></div>
			<?php endif; ?>

			<?php if ( $aimp_giftcard && $aimp_buy['available'] ) : ?>
				<?php // Gift card choices; the quantity and button in the buy bar belong to this form. ?>
				<form class="cart aimp-gc-buy" id="<?php echo esc_attr( $aimp_form_id ); ?>" action="<?php echo esc_url( $the_product->get_permalink() ); ?>" method="post" enctype="multipart/form-data">
					<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
					<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $aimp_id ); ?>">
					<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
				</form>
			<?php elseif ( ! $aimp_buy ) : ?>
				<div class="aimp-product-buy"><?php woocommerce_template_single_add_to_cart(); ?></div>
			<?php endif; ?>

			<?php
			// Patterns: description and details here, next to the picture.
			if ( $aimp_pattern && $aimp_has_desc ) {
				// Without a second "Description" title when the short description already has one.
				echo '<div class="aimp-product-text">' . ( $aimp_show_short ? '' : '<h4 class="aimp-product-subtitle">' . esc_html__( 'Description', 'atelier-irisee-master-plugin' ) . '</h4>' );
				echo '<div class="aimp-description">' . wp_kses_post( wc_format_content( $aimp_description ) ) . '</div></div>';
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

			<?php if ( ! $aimp_giftcard && ( $data['configurator_url'] || $data['recommended'] ) ) : ?>
				<?php // Recommendation box: at the bottom of this column, level with the bottom of the picture. ?>
				<div class="aimp-recommend-box">
					<?php if ( $data['recommended'] ) : ?>
						<?php $aimp_rec = $data['recommended']; ?>
						<a class="aimp-recommended" href="<?php echo esc_url( $aimp_rec->get_permalink() ); ?>">
							<?php echo wp_kses_post( $aimp_rec->get_image( 'woocommerce_thumbnail', array( 'class' => 'aimp-recommended-image' ) ) ); ?>
							<span>
								<small><?php echo esc_html( $aimp_pattern ? __( 'Recommended fabric', 'atelier-irisee-master-plugin' ) : __( 'Recommended pattern', 'atelier-irisee-master-plugin' ) ); ?></small>
								<span class="aimp-recommended-name"><?php echo esc_html( wp_strip_all_tags( $aimp_rec->get_name() ) ); ?></span>
							</span>
						</a>
					<?php endif; ?>
					<?php if ( $data['configurator_url'] ) : ?>
						<div class="aimp-recommend-kit">
							<p><?php esc_html_e( 'Start making my own sewing project kit', 'atelier-irisee-master-plugin' ); ?></p>
							<?php if ( count( $aimp_designs ) > 1 ) : ?>
								<?php // One button per design of the pack. ?>
								<?php foreach ( $aimp_designs as $aimp_design ) : ?>
									<?php if ( '' !== $aimp_design['url'] ) : ?>
										<a class="aimp-button" href="<?php echo esc_url( $aimp_design['url'] ); ?>">
											<?php
											/* translators: %s: design name, e.g. "Dress" */
											echo esc_html( sprintf( __( 'Make the %s', 'atelier-irisee-master-plugin' ), $aimp_design['name'] ) );
											?>
										</a>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php else : ?>
								<a class="aimp-button" href="<?php echo esc_url( $data['configurator_url'] ); ?>"><?php esc_html_e( 'To the configurator', 'atelier-irisee-master-plugin' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $aimp_insp_cards ) : ?>
		<?php // Fabrics: up to three inspiration cards across the full width. ?>
		<section class="aimp-inspiration">
		<h3><?php esc_html_e( 'Inspiration', 'atelier-irisee-master-plugin' ); ?></h3>
		<ul class="aimp-inspiration-grid">
			<?php foreach ( $aimp_insp_cards as $aimp_card ) : ?>
				<li class="aimp-inspiration-card">
					<?php if ( '' !== trim( $aimp_card['title'] ) ) : ?>
						<h4 class="aimp-product-subtitle"><?php echo esc_html( $aimp_card['title'] ); ?></h4>
					<?php endif; ?>
					<?php if ( $aimp_has_text( $aimp_card['text'] ) ) : ?>
						<div class="aimp-description"><?php echo wp_kses_post( wpautop( $aimp_card['text'] ) ); ?></div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		</section>
	<?php endif; ?>

	<?php if ( $aimp_fabric && ( $aimp_has_desc || $aimp_washing_html || $aimp_specs_html ) ) : ?>
		<?php // Fabrics: description on the left; washing instructions and specifications (dropdowns) on the right. ?>
		<section class="aimp-product-section aimp-product-row<?php echo $aimp_has_desc && ( $aimp_washing_html || $aimp_specs_html ) ? '' : ' is-single'; ?>">
			<?php if ( $aimp_has_desc ) : ?>
				<div>
					<h3><?php esc_html_e( 'Description', 'atelier-irisee-master-plugin' ); ?></h3>
					<div class="aimp-description aimp-product-description"><?php echo wc_format_content( $aimp_description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product content, filtered like the WooCommerce description tab. ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $aimp_washing_html || $aimp_specs_html ) : ?>
				<div class="aimp-product-dropdowns">
					<?php
					if ( $aimp_specs_html ) {
						$aimp_dropdown( __( 'Specifications', 'atelier-irisee-master-plugin' ), $aimp_specs_html, 'aimp-dropdown--specs' );
					}
					if ( $aimp_washing_html ) {
						$aimp_dropdown( __( 'Washing instructions', 'atelier-irisee-master-plugin' ), $aimp_washing_html, 'aimp-dropdown--washing' );
					}
					?>
				</div>
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
			<h3><?php esc_html_e( 'Suggested patterns', 'atelier-irisee-master-plugin' ); ?></h3>
			<ul class="aimp-grid aimp-product-cards">
				<?php
				foreach ( $data['fitting'] as $aimp_fit ) {
					echo AIMP_Product_Page::card_html( $aimp_fit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
				}
				?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $data['fabrics'] ) : ?>
		<section class="aimp-product-section">
			<h3><?php esc_html_e( 'Recommended fabrics', 'atelier-irisee-master-plugin' ); ?></h3>
			<ul class="aimp-grid aimp-product-cards">
				<?php
				foreach ( $data['fabrics'] as $aimp_fabric_card ) {
					echo AIMP_Product_Page::card_html( $aimp_fabric_card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
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
