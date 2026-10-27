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
 *                              WooCommerce's own form), fabric (texts, or null), button (specs, ideal_for, set_size, or null), gallery, attributes,
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
$aimp_notion      = ! empty( $data['notion'] ) ? $data['notion'] : null;
$aimp_set_size    = $aimp_notion ? (int) $aimp_notion['set_size'] : 1;
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
$aimp_own_specs   = $aimp_fabric || $aimp_notion; // Fabrics and haberdashery show their specifications instead.
$aimp_attributes  = $aimp_own_specs ? array() : $data['attributes'];
$aimp_weight      = ! $aimp_own_specs && $the_product->has_weight() ? wc_format_weight( $the_product->get_weight() ) : '';
$aimp_dimensions  = ! $aimp_own_specs && $the_product->has_dimensions() ? wc_format_dimensions( $the_product->get_dimensions( false ) ) : '';
$aimp_show_detail = ! $aimp_own_specs && ! $aimp_pattern && ! $aimp_giftcard && ( $the_product->has_attributes() || $aimp_weight || $aimp_dimensions );
$aimp_show_review = wc_reviews_enabled() && comments_open( $aimp_id ) && is_singular( 'product' ) && (int) get_queried_object_id() === $aimp_id;
$aimp_skills      = AIMP_Catalog::skill_levels();

// Design studio (Product page): the settings that change the markup.
$aimp_pp     = function ( $aimp_key, $aimp_fallback = null ) {
	return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( 'product-page', $aimp_key, $aimp_fallback ) : $aimp_fallback;
};
$aimp_layout = (string) $aimp_pp( 'pp_layout', '' );
$aimp_sticky = (string) $aimp_pp( 'pp_sticky', '' );
$aimp_count  = (int) $aimp_pp( 'pp_count', 0 );
if ( $aimp_count ) {
	$data['fitting'] = array_slice( $data['fitting'], 0, $aimp_count );
	$data['fabrics'] = array_slice( $data['fabrics'], 0, $aimp_count );
}

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
		$aimp_html .= '<li class="aimp-washing-tile aimp-washing-tile--' . esc_attr( $aimp_subject ) . '">' . AIMP_Product_Page::fabric_icon( $aimp_subject ) .
			'<span><strong>' . esc_html( $aimp_line[0] ) . '</strong>' . nl2br( esc_html( $aimp_line[1] ) ) . '</span></li>';
	}
	return $aimp_html . '</ul>';
};
$aimp_washing_html = $aimp_fabric ? $aimp_tiles( $aimp_fabric['washing'] ) : '';
$aimp_specs_html   = $aimp_fabric ? $aimp_tiles( $aimp_fabric['specs'] ) : ( $aimp_notion ? $aimp_tiles( $aimp_notion['specs'] ) : '' );
?>
<div class="aimp-configurator aimp-product alignwide<?php echo $aimp_fabric ? ' aimp-product--fabric' : ''; ?><?php echo $aimp_notion ? ' aimp-product--notion aimp-product--' . esc_attr( $aimp_notion['kind'] ) : ''; ?><?php echo $aimp_pattern ? ' aimp-product--pattern' : ''; ?><?php echo $aimp_giftcard ? ' aimp-product--giftcard' : ''; ?>" data-aimp-product="<?php echo esc_attr( $aimp_id ); ?>"<?php echo $aimp_layout ? ' data-pp-layout="' . esc_attr( $aimp_layout ) . '"' : ''; ?><?php echo $aimp_sticky ? ' data-pp-sticky="' . esc_attr( $aimp_sticky ) . '"' : ''; ?>>

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
			<?php $aimp_brand = AIMP_Product_Page::brand( $the_product ); ?>
			<?php if ( '' !== $aimp_brand ) : ?>
				<?php $aimp_brand_url = $aimp_pp( 'pp_brand_link' ) ? AIMP_Product_Page::brand_url( $the_product ) : ''; ?>
				<p class="aimp-product-brand">
					<?php if ( '' !== $aimp_brand_url ) : ?>
						<a href="<?php echo esc_url( $aimp_brand_url ); ?>"><?php echo esc_html( $aimp_brand ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $aimp_brand ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

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
				<div class="aimp-buy-bar" data-aimp-buy data-unit-price="<?php echo esc_attr( wc_format_decimal( $aimp_buy['unit_price'], wc_get_price_decimals() ) ); ?>" data-per-10cm="<?php echo $aimp_buy['per_10cm'] ? '1' : '0'; ?>" data-set-size="<?php echo esc_attr( isset( $aimp_buy['set_size'] ) ? (int) $aimp_buy['set_size'] : 1 ); ?>"<?php echo ! empty( $aimp_buy['unit_text'] ) ? ' data-unit-text="' . esc_attr( $aimp_buy['unit_text'] ) . '"' : ''; ?><?php echo $aimp_giftcard ? ' data-giftcard' : ''; ?>>
					<div class="aimp-buy-price">
						<?php if ( '' !== $the_product->get_price_html() ) : ?>
							<p class="aimp-details-price price" data-aimp-price><?php echo wp_kses_post( $the_product->get_price_html() ); ?></p>
						<?php endif; ?>
						<?php if ( $aimp_buy['per_10cm'] ) : ?>
							<p class="aimp-price-unit" data-aimp-unit-line><?php esc_html_e( 'per 10 cm', 'atelier-irisee-master-plugin' ); ?></p>
						<?php elseif ( ! empty( $aimp_buy['unit_text'] ) ) : ?>
							<?php // Haberdashery: always "€0,60 per piece", "per set of 6" or "per roll of 3 m" (product.js adds the price). ?>
							<p class="aimp-price-unit" data-aimp-unit-line><?php echo esc_html( AIMP_Catalog::price_suffix( $the_product ) ); ?></p>
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
								<form class="aimp-buy-form" id="<?php echo esc_attr( $aimp_form_id ); ?>" data-rendered="<?php echo esc_attr( (string) time() ); ?>" data-nonces="<?php echo esc_url( WC_AJAX::get_endpoint( 'aimp_nonces' ) ); ?>" action="<?php echo esc_url( $the_product->get_permalink() ); ?>" method="post">
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
								: ( $aimp_set_size > 1
									/* translators: %d: number of sets */
									? sprintf( _n( 'Only %d set left', 'Only %d sets left', $aimp_buy['low'], 'atelier-irisee-master-plugin' ), $aimp_buy['low'] )
									: ( 'roll' === AIMP_Notions::sold( $the_product )
										/* translators: %d: number of rolls */
										? sprintf( _n( 'Only %d roll left', 'Only %d rolls left', $aimp_buy['low'], 'atelier-irisee-master-plugin' ), $aimp_buy['low'] )
										/* translators: %d: number of items */
										: sprintf( _n( 'Only %d left', 'Only %d left', $aimp_buy['low'], 'atelier-irisee-master-plugin' ), $aimp_buy['low'] ) ) )
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

			<?php if ( $aimp_pp( 'pp_share' ) ) : ?>
				<?php echo AIMP_Product_Page::share_html( $the_product, $aimp_first ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in share_html(). ?>
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

			<?php $aimp_order_info = $aimp_fabric ? $aimp_fabric['order_info'] : ( $aimp_notion ? $aimp_notion['order_info'] : '' ); ?>
			<?php if ( $aimp_has_text( $aimp_order_info ) ) : ?>
				<div class="aimp-description aimp-product-order-info"><?php echo wp_kses_post( wpautop( $aimp_order_info ) ); ?></div>
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
						<a class="aimp-recommended" href="<?php echo esc_url( $aimp_rec['url'] ); ?>">
							<?php echo wp_kses_post( $aimp_rec['image'] ); ?>
							<span>
								<small><?php echo esc_html( $aimp_pattern ? __( 'Recommended fabric', 'atelier-irisee-master-plugin' ) : __( 'Recommended pattern', 'atelier-irisee-master-plugin' ) ); ?></small>
								<span class="aimp-recommended-name"><?php echo esc_html( $aimp_rec['name'] ); ?></span>
							</span>
						</a>
					<?php endif; ?>
					<?php if ( $data['configurator_url'] ) : ?>
						<div class="aimp-recommend-kit">
							<p><?php esc_html_e( 'Create your sewing kit', 'atelier-irisee-master-plugin' ); ?></p>
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
		<section class="aimp-inspiration aimp-pp-inspiration" data-pp-title="<?php esc_attr_e( 'Inspiration', 'atelier-irisee-master-plugin' ); ?>">
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

	<?php if ( $aimp_notion && $aimp_notion['ideal_for'] ) : ?>
		<?php // Buttons: up to four kinds of clothes, each with its icon, in the place of the fabric inspiration cards. ?>
		<section class="aimp-inspiration aimp-ideal-for aimp-pp-ideal" data-pp-title="<?php esc_attr_e( 'Ideal for', 'atelier-irisee-master-plugin' ); ?>">
		<h3><?php esc_html_e( 'Ideal for', 'atelier-irisee-master-plugin' ); ?></h3>
		<ul class="aimp-inspiration-grid aimp-ideal-grid">
			<?php foreach ( $aimp_notion['ideal_for'] as $aimp_ideal ) : ?>
				<li class="aimp-inspiration-card aimp-ideal-card">
					<?php echo AIMP_Product_Page::clothing_icon( $aimp_ideal[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
					<h4 class="aimp-product-subtitle"><?php echo esc_html( $aimp_ideal[1] ); ?></h4>
				</li>
			<?php endforeach; ?>
		</ul>
		</section>
	<?php endif; ?>

	<?php if ( $aimp_own_specs && ( $aimp_has_desc || $aimp_washing_html || $aimp_specs_html ) ) : ?>
		<?php // Fabrics and buttons: description on the left; washing instructions and specifications (dropdowns) on the right. ?>
		<section class="aimp-product-section aimp-product-row aimp-pp-description<?php echo $aimp_has_desc && ( $aimp_washing_html || $aimp_specs_html ) ? '' : ' is-single'; ?>" data-pp-title="<?php echo esc_attr( $aimp_has_desc ? __( 'Description', 'atelier-irisee-master-plugin' ) : __( 'Specifications', 'atelier-irisee-master-plugin' ) ); ?>">
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
	<?php elseif ( ! $aimp_own_specs && ! $aimp_pattern && $aimp_has_desc ) : ?>
		<section class="aimp-product-section aimp-product-desc aimp-pp-description" data-pp-title="<?php esc_attr_e( 'Description', 'atelier-irisee-master-plugin' ); ?>">
			<h3><?php esc_html_e( 'Description', 'atelier-irisee-master-plugin' ); ?></h3>
			<div class="aimp-description aimp-product-description"><?php echo wc_format_content( $aimp_description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product content, filtered like the WooCommerce description tab. ?></div>
		</section>
	<?php endif; ?>

	<?php if ( $aimp_show_detail ) : ?>
		<section class="aimp-product-section aimp-pp-details" data-pp-title="<?php esc_attr_e( 'Details', 'atelier-irisee-master-plugin' ); ?>">
			<h3><?php esc_html_e( 'Details', 'atelier-irisee-master-plugin' ); ?></h3>
			<?php wc_display_product_attributes( $the_product ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['fitting'] ) : ?>
		<section class="aimp-product-section aimp-product-suggested aimp-pp-fitting">
			<h3><?php echo esc_html( $aimp_notion ? __( 'Fits these patterns', 'atelier-irisee-master-plugin' ) : __( 'Suggested patterns', 'atelier-irisee-master-plugin' ) ); ?></h3>
			<ul class="aimp-grid aimp-product-cards">
				<?php
				foreach ( $data['fitting'] as $aimp_fit ) {
					echo AIMP_Product_Page::card_html( $aimp_fit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card_html().
				}
				?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $data['size_table'] ) ) : ?>
		<?php // Patterns: the accessories (left) and the sizes with measurements and fabric (right), per design. ?>
		<section class="aimp-product-section aimp-pattern-needs aimp-pp-sizes" data-pp-title="<?php esc_attr_e( 'Size chart', 'atelier-irisee-master-plugin' ); ?>">
			<?php foreach ( $data['size_table'] as $aimp_group ) : ?>
				<?php if ( '' !== $aimp_group['name'] ) : ?>
					<h3 class="aimp-pattern-needs-design"><?php echo esc_html( $aimp_group['name'] ); ?></h3>
				<?php endif; ?>
				<div class="aimp-pattern-needs-row">
					<div class="aimp-pattern-accessories">
						<h4 class="aimp-product-subtitle"><?php esc_html_e( 'Accessories', 'atelier-irisee-master-plugin' ); ?></h4>
						<?php if ( $aimp_group['accessories'] ) : ?>
							<ul>
								<?php foreach ( $aimp_group['accessories'] as $aimp_item ) : ?>
									<li><span><?php echo esc_html( $aimp_item[0] ); ?></span><strong><?php echo esc_html( $aimp_item[1] ); ?></strong></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p><?php esc_html_e( 'No accessories needed.', 'atelier-irisee-master-plugin' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="aimp-pattern-sizes">
						<div class="aimp-pattern-sizes-head">
							<h4 class="aimp-product-subtitle"><?php esc_html_e( 'Size chart', 'atelier-irisee-master-plugin' ); ?></h4>
							<button type="button" class="aimp-button aimp-measure-button" data-aimp-measure><?php esc_html_e( 'How to measure your body measurements', 'atelier-irisee-master-plugin' ); ?></button>
						</div>
						<div class="aimp-table-scroll">
							<table class="aimp-size-chart">
								<thead>
									<tr>
										<?php foreach ( $aimp_group['columns'] as $aimp_column ) : ?>
											<th scope="col"><?php echo esc_html( $aimp_column ); ?></th>
										<?php endforeach; ?>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $aimp_group['rows'] as $aimp_row ) : ?>
										<tr>
											<?php foreach ( $aimp_row as $aimp_index => $aimp_cell ) : ?>
												<?php if ( 0 === $aimp_index ) : ?>
													<th scope="row"><?php echo esc_html( $aimp_cell ); ?></th>
												<?php else : ?>
													<?php // Phones show the chart as cards: each value with its column name. ?>
													<td data-label="<?php echo esc_attr( isset( $aimp_group['columns'][ $aimp_index ] ) ? $aimp_group['columns'][ $aimp_index ] : '' ); ?>"><?php echo esc_html( $aimp_cell ); ?></td>
												<?php endif; ?>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['fabrics'] ) : ?>
		<section class="aimp-product-section aimp-pp-fabrics" data-pp-title="<?php esc_attr_e( 'Recommended fabrics', 'atelier-irisee-master-plugin' ); ?>">
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
		<section class="aimp-product-section aimp-product-reviews aimp-pp-reviews" id="aimp-reviews" data-pp-title="<?php esc_attr_e( 'Reviews', 'atelier-irisee-master-plugin' ); ?>">
			<?php comments_template(); ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['related'] ) : ?>
		<section class="aimp-product-section aimp-pp-related" data-pp-title="<?php esc_attr_e( 'You may also like', 'atelier-irisee-master-plugin' ); ?>">
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
