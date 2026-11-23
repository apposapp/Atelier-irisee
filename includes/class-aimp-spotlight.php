<?php
/**
 * [atelier_irisee_kit_spotlight id="1"]: a ready-made sewing kit in the spotlight on the home page. The shop picks
 * the pattern, fabric and haberdashery in advance (Atelier Irisee → Kit spotlights); the visitor only picks a size.
 * "Add to cart" adds the kit through AIMP_Cart::add_group(), exactly as the customizer does (same lines, discount,
 * locked quantities, grouping and order details).
 *
 * Around the kit's picture float three elements (pattern, fabric, haberdashery) in one of three styles, and a gold
 * needle sews a running stitch round it, over and under the elements (spotlight.js).
 *
 * Stored in the option aimp_spotlights: id => spotlight (see clean()).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Spotlight {

	const TAG    = 'atelier_irisee_kit_spotlight';
	const OPTION = 'aimp_spotlights';
	const PAGE   = 'aimp-spotlights';
	const STYLES = array( 'medallion', 'drawing', 'tag' );
	const MOVES  = array( 'move', 'still', 'off' );

	/** Where the elements sit on the needle's circle (degrees, 0 = right, clockwise) and which way the thread goes. */
	const SPOTS = array(
		'pattern' => array( -138, 'under' ),
		'fabric'  => array( -18, 'over' ),
		'notions' => array( 108, 'under' ),
	);

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_action( 'wc_ajax_aimp_spotlight_add', array( __CLASS__, 'add_to_cart' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 65 ); // After the Atelier Irisee menu itself (60).
		add_action( 'admin_post_aimp_spotlight_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_aimp_spotlight_delete', array( __CLASS__, 'delete' ) );
		add_action( 'wp_ajax_aimp_spotlight_choices', array( __CLASS__, 'ajax_choices' ) );
	}

	public static function register_assets() {
		if ( ! wp_style_is( 'aimp-kit-steps', 'registered' ) ) {
			AIMP_Kit_Steps::register_assets();
		}
		// The drawings and the needle are the kit steps bar's (their styles and moves are in kit-steps.css).
		wp_register_style( 'aimp-spotlight', AIMP_PLUGIN_URL . 'assets/css/spotlight.css', array( 'aimp-kit-steps' ), AIMP_VERSION );
		wp_register_script( 'aimp-spotlight', AIMP_PLUGIN_URL . 'assets/js/spotlight.js', array(), AIMP_VERSION, true );
		if ( AIMP_Blocks::page_has( array( self::TAG ) ) ) {
			wp_enqueue_style( 'aimp-spotlight' );
		}
	}

	/* ------------------------------------------------------------------ data */

	/**
	 * Every spotlight: id => spotlight.
	 *
	 * @return array[]
	 */
	public static function all() {
		$list = get_option( self::OPTION, array() );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $id => $spot ) {
			if ( is_array( $spot ) && absint( $id ) ) {
				$out[ absint( $id ) ] = self::clean( $spot );
			}
		}
		return $out;
	}

	/**
	 * One spotlight, or null.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ absint( $id ) ] ) ? $all[ absint( $id ) ] : null;
	}

	/**
	 * A spotlight with every key, only valid values.
	 *
	 * @param array $spot Raw.
	 * @return array
	 */
	public static function clean( $spot ) {
		$texts = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			foreach ( array( 'kicker', 'title', 'text', 'button', 'l_pattern', 'l_fabric', 'l_notions' ) as $key ) {
				$value = isset( $spot['texts'][ $key . '_' . $code ] ) ? (string) $spot['texts'][ $key . '_' . $code ] : '';
				$texts[ $key . '_' . $code ] = 'text' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			}
		}
		$notions = array();
		$types   = AIMP_Catalog::notion_types();
		foreach ( isset( $spot['notions'] ) && is_array( $spot['notions'] ) ? $spot['notions'] : array() as $type => $id ) {
			if ( isset( $types[ $type ] ) && absint( $id ) ) {
				$notions[ $type ] = absint( $id );
			}
		}
		$int = function ( $key, $min = 0, $max = PHP_INT_MAX, $default = 0 ) use ( $spot ) {
			if ( ! isset( $spot[ $key ] ) || '' === $spot[ $key ] ) {
				return $default;
			}
			return max( $min, min( $max, (int) $spot[ $key ] ) );
		};
		return array(
			'name'        => isset( $spot['name'] ) ? sanitize_text_field( (string) $spot['name'] ) : '',
			'texts'       => $texts,
			'image'       => $int( 'image' ),
			'fx'          => $int( 'fx', 0, 100, 50 ),
			'fy'          => $int( 'fy', 0, 100, 50 ),
			// A size of the pattern (and its design): the visitor picks among that design's sizes.
			'size'        => $int( 'size' ),
			'fabric'      => $int( 'fabric' ),
			'lining'      => $int( 'lining' ),
			'finish'      => isset( $spot['finish'] ) ? sanitize_key( (string) $spot['finish'] ) : '',
			'notions'     => $notions,
			'style'       => isset( $spot['style'] ) && in_array( $spot['style'], self::STYLES, true ) ? $spot['style'] : 'medallion',
			'img_pattern' => $int( 'img_pattern' ),
			'img_fabric'  => $int( 'img_fabric' ),
			'img_notions' => $int( 'img_notions' ),
			'move'        => isset( $spot['move'] ) && in_array( $spot['move'], self::MOVES, true ) ? $spot['move'] : 'move',
			'needle'      => ! isset( $spot['needle'] ) || ! empty( $spot['needle'] ) ? 1 : 0,
			'speed'       => $int( 'speed', 25, 300, 100 ),
			'right'       => empty( $spot['right'] ) ? 0 : 1,
		);
	}

	/**
	 * A text of a spotlight in the visitor's language, else the standard text.
	 *
	 * @param array  $spot Spotlight.
	 * @param string $key  kicker, title, text, button, l_pattern, l_fabric, l_notions.
	 * @return string
	 */
	public static function text( $spot, $key ) {
		$value = isset( $spot['texts'][ $key . '_' . AIMP_I18n::current() ] ) ? trim( $spot['texts'][ $key . '_' . AIMP_I18n::current() ] ) : '';
		if ( '' !== $value ) {
			return $value;
		}
		$defaults = self::default_texts();
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	/**
	 * The standard texts (current language).
	 *
	 * @return array
	 */
	public static function default_texts() {
		return array(
			'kicker'    => __( 'Kit in the spotlight', 'atelier-irisee-master-plugin' ),
			'title'     => '',
			'text'      => '',
			'button'    => __( 'Add the kit to the cart', 'atelier-irisee-master-plugin' ),
			'l_pattern' => __( 'Pattern', 'atelier-irisee-master-plugin' ),
			'l_fabric'  => __( 'Fabric', 'atelier-irisee-master-plugin' ),
			'l_notions' => __( 'Haberdashery', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * The requirements of a size with the spotlight's finish (a pattern with a finish choice).
	 *
	 * @param array $spot         Spotlight.
	 * @param int   $variation_id Size.
	 * @return array|null
	 */
	private static function requirements( $spot, $variation_id ) {
		$req = AIMP_Catalog::get_requirements( $variation_id, $spot['fabric'] ? $spot['fabric'] : null );
		if ( ! $req ) {
			return null;
		}
		if ( $req['finish_options'] ) {
			$req['finish'] = isset( $req['finish_options'][ $spot['finish'] ] ) ? $spot['finish'] : (string) key( $req['finish_options'] );
		}
		return $req;
	}

	/**
	 * Whether a product can be sold $qty times.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $qty     Quantity.
	 * @return bool
	 */
	private static function can_sell( $product, $qty ) {
		return $product->is_purchasable() && $product->is_in_stock() && $product->has_enough_stock( $qty );
	}

	/**
	 * The lines of the kit in one size: [ product, qty, role ], or a reason why it cannot be made.
	 *
	 * @param array $spot Spotlight.
	 * @param array $req  Requirements (requirements()).
	 * @return array [ lines => [], reason => '' ]
	 */
	private static function kit_lines( $spot, $req ) {
		$lines   = array( array( $req['variation'], 1, 'pattern' ) );
		$missing = __( 'Not available in this size', 'atelier-irisee-master-plugin' );
		if ( ! self::can_sell( $req['variation'], 1 ) ) {
			return array( 'lines' => $lines, 'reason' => __( 'Sold out', 'atelier-irisee-master-plugin' ) );
		}
		if ( $req['fabric_units'] > 0 ) {
			$fabric = $spot['fabric'] ? AIMP_Catalog::get_allowed_fabric( $spot['fabric'], $req ) : null;
			if ( ! $fabric ) {
				return array( 'lines' => $lines, 'reason' => $missing );
			}
			if ( ! AIMP_Catalog::fabric_stock_ok( $fabric, $req['fabric_units'] ) ) {
				return array( 'lines' => $lines, 'reason' => __( 'Not enough fabric in stock for this size', 'atelier-irisee-master-plugin' ) );
			}
			$lines[] = array( $fabric, $req['fabric_units'], 'fabric' );
		}
		if ( $req['lining_units'] > 0 && $spot['lining'] ) {
			$lining = AIMP_Catalog::get_allowed_lining( $spot['lining'], $spot['fabric'] );
			if ( ! $lining || ! self::can_sell( $lining, $req['lining_units'] + ( $lining->get_id() === $spot['fabric'] ? $req['fabric_units'] : 0 ) ) ) {
				return array( 'lines' => $lines, 'reason' => $missing );
			}
			$lines[] = array( $lining, $req['lining_units'], 'lining' );
		}
		foreach ( $spot['notions'] as $type => $id ) {
			$count = AIMP_Catalog::notion_count( $type, $req );
			if ( $count <= 0 ) {
				continue;
			}
			$product = AIMP_Catalog::get_allowed_notion( $id, $type, $req, $spot['fabric'] );
			$qty     = $product ? AIMP_Catalog::pack_qty( $product, $count ) : 0;
			if ( ! $product || ! self::can_sell( $product, $qty ) ) {
				return array( 'lines' => $lines, 'reason' => $missing );
			}
			$lines[] = array( $product, $qty, $type );
		}
		return array( 'lines' => $lines, 'reason' => '' );
	}

	/**
	 * The sizes the visitor can pick: [ id, label, available, reason, price, regular, fabric ].
	 *
	 * @param array $spot Spotlight.
	 * @return array[]|null Null when the pattern is gone.
	 */
	public static function sizes( $spot ) {
		$data = $spot['size'] ? AIMP_Catalog::get_sizes( $spot['size'] ) : null;
		if ( ! $data ) {
			return null;
		}
		$discount = AIMP_Cart::kit_discount();
		$sizes    = array();
		foreach ( $data['sizes'] as $size ) {
			$req = self::requirements( $spot, $size['id'] );
			if ( ! $req ) {
				continue;
			}
			$kit   = self::kit_lines( $spot, $req );
			$total = 0;
			$kitp  = 0;
			foreach ( $kit['lines'] as $line ) {
				$price  = (float) wc_get_price_to_display( $line[0] );
				$total += $price * $line[1];
				// As the cart: the discount per line, each line rounded (AIMP_Cart::apply_kit_discount()).
				$kitp += round( round( $price * ( 100 - $discount ) / 100, 4 ) * $line[1], wc_get_price_decimals() );
			}
			$sizes[] = array(
				'id'        => (int) $size['id'],
				'label'     => (string) $size['label'],
				'available' => '' === $kit['reason'],
				'reason'    => $kit['reason'],
				'amount'    => $kitp,
				'price'     => '' === $kit['reason'] ? wp_strip_all_tags( wc_price( $kitp ) ) : '',
				'regular'   => '' === $kit['reason'] && $discount > 0 ? wp_strip_all_tags( wc_price( $total ) ) : '',
				'fabric'    => $req['fabric_units'] > 0 ? AIMP_Catalog::fabric_need_text( $req ) : '',
			);
		}
		return $sizes;
	}

	/**
	 * The three elements: [ key => [ name, url, image (attachment id or URL), names (all of them) ] ].
	 *
	 * @param array $spot Spotlight.
	 * @return array
	 */
	public static function elements( $spot ) {
		$req = $spot['size'] ? AIMP_Catalog::get_requirements( $spot['size'] ) : null;
		if ( ! $req ) {
			return array();
		}
		$list = array();
		$name = implode( ' – ', array_filter( array( wp_strip_all_tags( $req['pattern']->get_name() ), $req['design'] ) ) );
		$list['pattern'] = array(
			'name'  => $name,
			'url'   => $req['pattern']->get_permalink(),
			'image' => $spot['img_pattern'] ? $spot['img_pattern'] : AIMP_Catalog::kit_image_id( $req ),
			'names' => array( $name ),
		);
		$fabric = $spot['fabric'] ? wc_get_product( $spot['fabric'] ) : null;
		if ( $fabric && 'publish' === $fabric->get_status() ) {
			$list['fabric'] = array(
				'name'  => wp_strip_all_tags( $fabric->get_name() ),
				'url'   => $fabric->get_permalink(),
				'image' => $spot['img_fabric'] ? $spot['img_fabric'] : (int) $fabric->get_image_id(),
				'names' => array( wp_strip_all_tags( $fabric->get_name() ) ),
			);
		}
		$notions = array();
		$lining  = $spot['lining'] ? wc_get_product( $spot['lining'] ) : null;
		if ( $lining && 'publish' === $lining->get_status() && $req['lining_units'] > 0 ) {
			$notions[] = $lining;
		}
		foreach ( $spot['notions'] as $id ) {
			$product = wc_get_product( $id );
			if ( $product && 'publish' === $product->get_status() ) {
				$notions[] = $product;
			}
		}
		if ( $notions ) {
			// The first haberdashery item (not the lining) stands for all of them.
			$first = count( $notions ) > 1 && $lining && $notions[0]->get_id() === $lining->get_id() ? $notions[1] : $notions[0];
			$list['notions'] = array(
				'name'  => wp_strip_all_tags( $first->get_name() ),
				'url'   => $first->get_permalink(),
				'image' => $spot['img_notions'] ? $spot['img_notions'] : (int) $first->get_image_id(),
				'names' => array_map(
					function ( $product ) {
						return wp_strip_all_tags( $product->get_name() );
					},
					$notions
				),
				'links' => array_map(
					function ( $product ) {
						return $product->get_permalink();
					},
					$notions
				),
			);
		}
		return $list;
	}

	/* ------------------------------------------------------------------ the block */

	/**
	 * @param array|string $atts Attributes: id (standard: the only or first spotlight).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, self::TAG );
		$all  = self::all();
		$id   = absint( $atts['id'] );
		if ( ! $id && $all ) {
			$id = (int) key( $all );
		}
		$spot  = isset( $all[ $id ] ) ? $all[ $id ] : null;
		$sizes = $spot ? self::sizes( $spot ) : null;
		if ( ! $spot || ! $sizes ) {
			// Only the shop sees why nothing is shown.
			return current_user_can( 'manage_woocommerce' ) ? '<p class="aimp-spotlight-missing">' . esc_html__( 'Kit spotlight: this kit is not set up or its pattern is no longer available (Atelier Irisee → Kit spotlights).', 'atelier-irisee-master-plugin' ) . '</p>' : '';
		}
		if ( ! wp_style_is( 'aimp-spotlight', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-spotlight' );
		wp_enqueue_script( 'aimp-spotlight' );
		return self::render( $id, $spot, $sizes, self::elements( $spot ) );
	}

	/**
	 * The block's HTML (also used by the test harness).
	 *
	 * @param int   $id       Spotlight id.
	 * @param array $spot     Spotlight.
	 * @param array $sizes    sizes().
	 * @param array $elements elements().
	 * @return string
	 */
	public static function render( $id, $spot, $sizes, $elements ) {
		$title = self::text( $spot, 'title' );
		if ( '' === $title && isset( $elements['pattern'] ) ) {
			$title = $elements['pattern']['name'];
		}
		$photo = '';
		if ( $spot['image'] ) {
			$photo = wp_get_attachment_image( $spot['image'], 'large', false, array( 'class' => 'aimp-spotlight-img', 'alt' => $title, 'loading' => 'lazy', 'style' => 'object-position:' . $spot['fx'] . '% ' . $spot['fy'] . '%' ) );
		} elseif ( isset( $elements['pattern'] ) && $elements['pattern']['image'] ) {
			$photo = wp_get_attachment_image( (int) $elements['pattern']['image'], 'large', false, array( 'class' => 'aimp-spotlight-img', 'alt' => $title, 'loading' => 'lazy' ) );
		}

		$classes = 'aimp-home-block aimp-spotlight style-' . $spot['style'] . ' move-' . $spot['move'] . ( $spot['right'] ? ' is-photo-right' : '' ) . ( $spot['needle'] ? ' has-needle' : '' );
		$attrs   = ' data-aimp-spotlight data-id="' . (int) $id . '" data-endpoint="' . esc_url( WC_AJAX::get_endpoint( 'aimp_spotlight_add' ) ) . '"' .
			' data-speed="' . (int) $spot['speed'] . '" data-needle="' . (int) $spot['needle'] . '"';

		$html  = '<section class="' . esc_attr( $classes ) . '"' . $attrs . ' aria-label="' . esc_attr( $title ) . '">';
		$html .= '<div class="aimp-spotlight-stage">';
		$html .= '<div class="aimp-spotlight-photo">' . $photo . '</div>';
		if ( $spot['needle'] ) {
			$html .= '<svg class="aimp-spotlight-layer is-back" aria-hidden="true" focusable="false"></svg>';
		}
		if ( 'off' !== $spot['move'] && $elements ) {
			$html .= '<ul class="aimp-spotlight-elements">';
			foreach ( self::SPOTS as $key => $place ) {
				if ( isset( $elements[ $key ] ) ) {
					$html .= self::element( $key, $place, $elements[ $key ], $spot );
				}
			}
			$html .= '</ul>';
		}
		if ( $spot['needle'] ) {
			// The needle starts in the front layer; spotlight.js moves it behind the elements it passes under.
			$html .= '<svg class="aimp-spotlight-layer is-front" aria-hidden="true" focusable="false">' . AIMP_Kit_Steps::needle() . '</svg>';
		}
		$html .= '</div>';

		// The text, what is in the kit, the sizes, the price and the button.
		$html .= '<div class="aimp-spotlight-body">';
		$kicker = self::text( $spot, 'kicker' );
		if ( '' !== $kicker ) {
			$html .= '<p class="aimp-spotlight-kicker">' . esc_html( $kicker ) . '</p>';
		}
		$html .= '<h2 class="aimp-spotlight-title">' . esc_html( $title ) . '</h2>';
		$text  = self::text( $spot, 'text' );
		if ( '' !== $text ) {
			$html .= '<div class="aimp-spotlight-text">' . wpautop( esc_html( $text ) ) . '</div>';
		}
		$html .= '<p class="aimp-spotlight-in">' . esc_html__( 'In this kit', 'atelier-irisee-master-plugin' ) . '</p><ul class="aimp-spotlight-list">';
		foreach ( array( 'pattern', 'fabric', 'notions' ) as $key ) {
			if ( ! isset( $elements[ $key ] ) ) {
				continue;
			}
			$links = array();
			foreach ( $elements[ $key ]['names'] as $n => $name ) {
				$url     = isset( $elements[ $key ]['links'][ $n ] ) ? $elements[ $key ]['links'][ $n ] : $elements[ $key ]['url'];
				$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
			}
			$html .= '<li class="aimp-spotlight-item aimp-spotlight-item--' . esc_attr( $key ) . '"><span class="aimp-spotlight-item-label">' . esc_html( self::text( $spot, 'l_' . $key ) ) . '</span> ' . implode( ', ', $links );
			if ( 'fabric' === $key ) {
				$html .= ' <span class="aimp-spotlight-need" data-need hidden></span>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';

		$html .= '<fieldset class="aimp-spotlight-sizes"><legend>' . esc_html__( 'Choose your size', 'atelier-irisee-master-plugin' ) . '</legend><div class="aimp-spotlight-pills">';
		$open  = array_values(
			array_filter(
				$sizes,
				function ( $size ) {
					return $size['available'];
				}
			)
		);
		foreach ( $sizes as $size ) {
			$html .= sprintf(
				'<button type="button" class="aimp-spotlight-size" data-size="%1$d" data-price="%2$s" data-regular="%3$s" data-need="%4$s" aria-pressed="false"%5$s%6$s>%7$s</button>',
				$size['id'],
				esc_attr( $size['price'] ),
				esc_attr( $size['regular'] ),
				esc_attr( $size['fabric'] ),
				$size['available'] ? '' : ' disabled',
				'' !== $size['reason'] ? ' title="' . esc_attr( $size['reason'] ) . '"' : '',
				esc_html( $size['label'] )
			);
		}
		$html .= '</div></fieldset>';

		// Before a size is chosen: "from" the lowest price.
		$from = '';
		if ( $open ) {
			$amounts = wp_list_pluck( $open, 'amount' );
			$lowest  = wp_strip_all_tags( wc_price( min( $amounts ) ) );
			/* translators: %s: price */
			$from = min( $amounts ) < max( $amounts ) ? sprintf( __( 'from %s', 'atelier-irisee-master-plugin' ), $lowest ) : $lowest;
		}
		$html .= '<p class="aimp-spotlight-price" aria-live="polite"><del data-regular hidden></del> <span data-price>' . esc_html( $from ) . '</span>';
		$discount = AIMP_Cart::kit_discount();
		if ( $discount > 0 ) {
			/* translators: %d: discount percentage */
			$html .= ' <span class="aimp-spotlight-save">' . esc_html( sprintf( __( '%d%% kit discount', 'atelier-irisee-master-plugin' ), $discount ) ) . '</span>';
		}
		$html .= '</p>';
		if ( ! $open ) {
			$html .= '<p class="aimp-spotlight-soldout">' . esc_html__( 'This kit is sold out for now.', 'atelier-irisee-master-plugin' ) . '</p>';
		}
		$html .= '<button type="button" class="aimp-spotlight-button" data-add disabled data-label="' . esc_attr( self::text( $spot, 'button' ) ) . '" data-busy="' . esc_attr__( 'Adding…', 'atelier-irisee-master-plugin' ) . '" data-error="' . esc_attr__( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ) . '"data-pick="' . esc_attr__( 'Choose your size first', 'atelier-irisee-master-plugin' ) . '">' . esc_html( $open ? __( 'Choose your size first', 'atelier-irisee-master-plugin' ) : self::text( $spot, 'button' ) ) . '</button>';
		$html .= '<div class="aimp-spotlight-message" role="status" aria-live="polite" hidden></div>';
		$html .= '<p class="aimp-spotlight-note" hidden><a href="' . esc_url( wc_get_cart_url() ) . '">' . esc_html__( 'View cart', 'atelier-irisee-master-plugin' ) . '</a></p>';
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * One floating element.
	 *
	 * @param string $key     pattern, fabric, notions.
	 * @param array  $place   [ angle, under|over ].
	 * @param array  $element From elements().
	 * @param array  $spot    Spotlight.
	 * @return string
	 */
	private static function element( $key, $place, $element, $spot ) {
		// On the needle's circle (the same circle as spotlight.js: 42% of the stage from its centre).
		$angle = deg2rad( $place[0] );
		$x     = round( 50 + 42 * cos( $angle ), 2 );
		$y     = round( 50 + 42 * sin( $angle ), 2 );
		$label = self::text( $spot, 'l_' . $key );
		$image = '';
		if ( 'drawing' !== $spot['style'] && $element['image'] ) {
			$image = is_numeric( $element['image'] ) ? wp_get_attachment_image_url( (int) $element['image'], 'woocommerce_thumbnail' ) : (string) $element['image'];
		}
		$drawing = AIMP_Kit_Steps::drawing( $key );
		$inner   = '';
		if ( 'medallion' === $spot['style'] ) {
			$inner = '<span class="aimp-sp-disc">' . ( $image ? '<img src="' . esc_url( $image ) . '" alt="" loading="lazy">' : $drawing ) . '</span>' .
				'<span class="aimp-sp-ribbon">' . esc_html( $label ) . '</span>';
		} elseif ( 'drawing' === $spot['style'] ) {
			$inner = '<span class="aimp-sp-badge">' . $drawing . '</span><span class="aimp-sp-caption">' . esc_html( $label ) . '</span>';
		} else {
			// A kraft paper label on a string: the drawing (or the photo) and the product's name.
			$inner = '<span class="aimp-sp-string"></span><span class="aimp-sp-tag"><span class="aimp-sp-hole"></span>' .
				( $image ? '<img src="' . esc_url( $image ) . '" alt="" loading="lazy">' : $drawing ) .
				'<span class="aimp-sp-tag-label">' . esc_html( $label ) . '</span><span class="aimp-sp-tag-name">' . esc_html( $element['name'] ) . '</span></span>';
		}
		return '<li class="aimp-sp-el aimp-sp-el--' . esc_attr( $key ) . ' is-' . esc_attr( $place[1] ) . '" data-el="' . esc_attr( $key ) . '" style="--sp-x:' . $x . '%;--sp-y:' . $y . '%">' .
			'<a class="aimp-sp-float" href="' . esc_url( $element['url'] ) . '" title="' . esc_attr( $label . ': ' . $element['name'] ) . '">' . $inner . '</a></li>';
	}

	/* ------------------------------------------------------------------ add to cart */

	/**
	 * Adds the kit in the chosen size. Only the spotlight and the size come from the browser: the fabric, lining,
	 * haberdashery and finish are the spotlight's own. Like WooCommerce's own add-to-cart links there is no nonce (the
	 * home page may be cached for hours); it can only add this ready-made kit.
	 */
	public static function add_to_cart() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see above.
		$id        = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$variation = isset( $_POST['variation'] ) ? absint( wp_unslash( $_POST['variation'] ) ) : 0;
		// phpcs:enable
		$spot = self::get( $id );
		$fail = function ( $message ) {
			wp_send_json_error( array( 'errors' => array( $message ) ) );
		};
		if ( ! $spot ) {
			$fail( __( 'This kit is no longer available.', 'atelier-irisee-master-plugin' ) );
		}
		// Only a size of the spotlight's pattern and design.
		$data = AIMP_Catalog::get_sizes( $spot['size'] );
		$ids  = $data ? array_map( 'intval', wp_list_pluck( $data['sizes'], 'id' ) ) : array();
		if ( ! in_array( $variation, $ids, true ) ) {
			$fail( __( 'Choose your size.', 'atelier-irisee-master-plugin' ) );
		}
		$req = self::requirements( $spot, $variation );
		if ( ! $req ) {
			$fail( __( 'This pattern size is not available.', 'atelier-irisee-master-plugin' ) );
		}
		$result = AIMP_Cart::add_group(
			$variation,
			$spot['fabric'],
			$spot['notions'],
			$req['lining_units'] > 0 ? $spot['lining'] : 0,
			array(),
			isset( $req['finish'] ) ? $req['finish'] : ''
		);
		$wc_errors = array_map(
			function ( $notice ) {
				return wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : $notice );
			},
			wc_get_notices( 'error' )
		);
		wc_clear_notices();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'errors' => array_values( array_unique( array_merge( $result->get_error_messages(), $wc_errors ) ) ) ) );
		}
		wp_send_json_success(
			array(
				'message'    => __( 'The kit has been added to your cart.', 'atelier-irisee-master-plugin' ),
				'cart_url'   => wc_get_cart_url(),
				'cart_count' => WC()->cart->get_cart_contents_count(),
			)
		);
	}

	/* ------------------------------------------------------------------ admin: Atelier Irisee → Kit spotlights */

	public static function menu() {
		$hook = add_submenu_page( AIMP_Settings::PAGE, __( 'Kit spotlights', 'atelier-irisee-master-plugin' ), __( 'Kit spotlights', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render_admin' ) );
		add_action( 'admin_enqueue_scripts', function ( $current ) use ( $hook ) {
			if ( $current !== $hook ) {
				return;
			}
			wp_enqueue_media();
			wp_enqueue_style( 'aimp-admin-spotlight', AIMP_PLUGIN_URL . 'assets/css/admin-spotlight.css', array(), AIMP_VERSION );
			wp_enqueue_script( 'aimp-admin-spotlight', AIMP_PLUGIN_URL . 'assets/js/admin-spotlight.js', array(), AIMP_VERSION, true );
			wp_localize_script(
				'aimp-admin-spotlight',
				'aimpSpotlight',
				array(
					'ajax'   => admin_url( 'admin-ajax.php' ),
					'nonce'  => wp_create_nonce( 'aimp_spotlight_choices' ),
					'i18n'   => array(
						'none'    => __( '— None —', 'atelier-irisee-master-plugin' ),
						'loading' => __( 'Loading…', 'atelier-irisee-master-plugin' ),
						'pick'    => __( 'Choose a picture', 'atelier-irisee-master-plugin' ),
						'copied'  => __( 'Copied', 'atelier-irisee-master-plugin' ),
					),
				)
			);
		} );
	}

	/**
	 * Every pattern (and every design of a pack) for the pattern list: id (a size) => name.
	 *
	 * @return array
	 */
	private static function pattern_options() {
		$options = array();
		$page    = 1;
		do {
			$result = AIMP_Catalog::get_patterns( array(), $page );
			foreach ( $result['items'] as $item ) {
				$id = (int) $item['id'];
				if ( empty( $item['design'] ) ) {
					// A pattern without designs: its first size.
					$product = wc_get_product( $id );
					$id      = 0;
					foreach ( $product ? $product->get_children() : array() as $child ) {
						if ( 'publish' === get_post_status( $child ) ) {
							$id = (int) $child;
							break;
						}
					}
				}
				if ( $id ) {
					$options[ $id ] = $item['name'];
				}
			}
			$page++;
		} while ( $page <= (int) $result['pages'] && $page < 40 );
		return $options;
	}

	/**
	 * What can be chosen for a pattern (one of its sizes) and fabric: fabrics, linings, finishes, haberdashery.
	 *
	 * @param int $size   A size of the pattern.
	 * @param int $fabric The chosen fabric.
	 * @return array|null
	 */
	public static function choices( $size, $fabric ) {
		$data = $size ? AIMP_Catalog::get_sizes( $size ) : null;
		if ( ! $data || ! $data['sizes'] ) {
			return null;
		}
		$reqs = array();
		foreach ( $data['sizes'] as $row ) {
			$req = AIMP_Catalog::get_requirements( $row['id'] );
			if ( $req ) {
				$reqs[] = $req;
			}
		}
		if ( ! $reqs ) {
			return null;
		}
		$first   = $reqs[0];
		$name    = function ( $id ) {
			return html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' );
		};
		$fabrics = array();
		if ( $first['fabric_cats'] ) {
			$args                   = AIMP_Catalog::base_query_args( $first['fabric_cats'], 'simple' );
			$args['posts_per_page'] = 400;
			$args['no_found_rows']  = true;
			foreach ( ( new WP_Query( $args ) )->posts as $id ) {
				$fabrics[] = array( 'id' => (int) $id, 'name' => $name( $id ) );
			}
		}
		// Lining: when some size needs it; the fabric's fitting linings, else every lining fabric.
		$linings     = array();
		$needs_lining = false;
		foreach ( $reqs as $req ) {
			$needs_lining = $needs_lining || $req['lining_units'] > 0;
		}
		if ( $needs_lining ) {
			$ids = AIMP_Catalog::fit_linings( $fabric );
			if ( ! $ids ) {
				$args                   = AIMP_Catalog::base_query_args( array( (int) AIMP_Settings::get( 'fabric_cat' ) ), 'simple', array( array( 'key' => AIMP_Catalog::META_LINING, 'value' => 'yes' ) ) );
				$args['posts_per_page'] = 400;
				$args['no_found_rows']  = true;
				$ids                    = ( new WP_Query( $args ) )->posts;
			}
			foreach ( $ids as $id ) {
				$linings[] = array( 'id' => (int) $id, 'name' => $name( $id ) );
			}
		}
		$finish = array();
		foreach ( $first['finish_options'] as $key => $info ) {
			$finish[] = array( 'id' => (string) $key, 'name' => (string) $info['name'] );
		}
		// Haberdashery: every type some size needs, with what fits that size (and the fabric's own interfacing).
		$notions = array();
		$fields  = AIMP_Catalog::material_fields();
		foreach ( AIMP_Catalog::notion_types() as $type => $info ) {
			$req = null;
			foreach ( $reqs as $candidate ) {
				if ( AIMP_Catalog::notion_count( $type, $candidate ) > 0 ) {
					$req = $candidate;
					break;
				}
			}
			if ( ! $req ) {
				continue;
			}
			$items = array();
			$seen  = array();
			if ( 'interfacing' === $type ) {
				foreach ( AIMP_Catalog::fabric_interfacings( $fabric ) as $slot => $id ) {
					$items[]       = array( 'id' => (int) $id, 'name' => $name( $id ) . ( 'volume' === $slot ? ' (volume)' : '' ) );
					$seen[ $id ] = true;
				}
			}
			$page = 1;
			do {
				$result = AIMP_Catalog::get_notions( $req['variation']->get_id(), $type, $page );
				foreach ( $result ? $result['items'] : array() as $item ) {
					if ( empty( $seen[ $item['id'] ] ) ) {
						$items[]               = array( 'id' => (int) $item['id'], 'name' => html_entity_decode( $item['name'], ENT_QUOTES, 'UTF-8' ) );
						$seen[ $item['id'] ] = true;
					}
				}
				$page++;
			} while ( $result && $page <= (int) $result['pages'] && $page < 20 );
			$material  = isset( $fields[ $info['material'] ] ) ? $fields[ $info['material'] ]['label'] : $type;
			$notions[] = array(
				'type'  => $type,
				'label' => $material,
				'items' => $items,
			);
		}
		return array(
			'fabrics' => $fabrics,
			'linings' => $linings,
			'finish'  => $finish,
			'notions' => $notions,
			'sizes'   => implode( ', ', wp_list_pluck( $data['sizes'], 'label' ) ),
		);
	}

	public static function ajax_choices() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'aimp_spotlight_choices', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$choices = self::choices( isset( $_POST['size'] ) ? absint( $_POST['size'] ) : 0, isset( $_POST['fabric'] ) ? absint( $_POST['fabric'] ) : 0 );
		if ( null === $choices ) {
			wp_send_json_error( null, 404 );
		}
		wp_send_json_success( $choices );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_spotlight_save' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$post = isset( $_POST['spot'] ) && is_array( $_POST['spot'] ) ? wp_unslash( $_POST['spot'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- clean() checks every value.
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		// phpcs:enable
		$list = get_option( self::OPTION, array() );
		$list = is_array( $list ) ? $list : array();
		if ( ! $id ) {
			$id = $list ? max( array_map( 'absint', array_keys( $list ) ) ) + 1 : 1;
		}
		$post['needle']  = empty( $post['needle'] ) ? 0 : 1;
		$list[ $id ]     = self::clean( $post );
		update_option( self::OPTION, $list, false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $id . '&saved=1' ) );
		exit;
	}

	public static function delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_spotlight_delete_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$list = get_option( self::OPTION, array() );
		if ( is_array( $list ) && isset( $list[ $id ] ) ) {
			unset( $list[ $id ] );
			update_option( self::OPTION, $list, false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * A select.
	 *
	 * @param string $name     Field name.
	 * @param array  $options  value => label.
	 * @param mixed  $current  Current value.
	 * @param string $attrs    Extra attributes.
	 * @return string
	 */
	private static function select( $name, $options, $current, $attrs = '' ) {
		$html = '<select name="' . esc_attr( $name ) . '"' . $attrs . '>';
		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * A picture field (media library).
	 *
	 * @param string $name  Field name.
	 * @param int    $value Attachment.
	 * @param string $empty Text when empty.
	 * @return string
	 */
	private static function picture( $name, $value, $empty ) {
		$url = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';
		return '<span class="aimp-sp-pic" data-pic><input type="hidden" name="' . esc_attr( $name ) . '" value="' . (int) $value . '">' .
			'<span class="aimp-sp-pic-preview">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<em>' . esc_html( $empty ) . '</em>' ) . '</span>' .
			'<button type="button" class="button" data-pic-choose>' . esc_html__( 'Choose a picture', 'atelier-irisee-master-plugin' ) . '</button> ' .
			'<button type="button" class="button-link" data-pic-clear data-empty="' . esc_attr( $empty ) . '">' . esc_html__( 'Remove', 'atelier-irisee-master-plugin' ) . '</button></span>';
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects what to show.
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		$all  = self::all();
		echo '<div class="wrap aimp-sp-admin"><h1>' . esc_html__( 'Kit spotlights', 'atelier-irisee-master-plugin' ) . '</h1>';
		if ( '' === $edit ) {
			echo '<p>' . esc_html__( 'A ready-made sewing kit for the home page: you choose the pattern, fabric and haberdashery, the visitor only picks a size. Put the shortcode of a kit on a page (e.g. the home page).', 'atelier-irisee-master-plugin' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=new' ) ) . '">' . esc_html__( 'New kit spotlight', 'atelier-irisee-master-plugin' ) . '</a></p>';
			if ( $all ) {
				echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Shortcode', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Sizes', 'atelier-irisee-master-plugin' ) . '</th><th></th></tr></thead><tbody>';
				foreach ( $all as $id => $spot ) {
					$sizes = self::sizes( $spot );
					$open  = $sizes ? count( array_filter( wp_list_pluck( $sizes, 'available' ) ) ) : 0;
					$code  = '[' . self::TAG . ' id="' . $id . '"]';
					echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $id ) ) . '"><strong>' . esc_html( '' !== $spot['name'] ? $spot['name'] : '#' . $id ) . '</strong></a></td>';
					echo '<td><code>' . esc_html( $code ) . '</code> <button type="button" class="button-link" data-copy="' . esc_attr( $code ) . '">' . esc_html__( 'Copy', 'atelier-irisee-master-plugin' ) . '</button></td>';
					echo '<td>' . ( null === $sizes ? '<span class="aimp-sp-warn">' . esc_html__( 'The pattern is no longer available', 'atelier-irisee-master-plugin' ) . '</span>' : esc_html( sprintf( /* translators: 1: sizes that can be ordered, 2: all sizes */ __( '%1$d of %2$d can be ordered', 'atelier-irisee-master-plugin' ), $open, count( $sizes ) ) ) ) . '</td>';
					echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $id ) ) . '">' . esc_html__( 'Edit', 'atelier-irisee-master-plugin' ) . '</a> | <a class="aimp-sp-delete" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_spotlight_delete&id=' . $id ), 'aimp_spotlight_delete_' . $id ) ) . '">' . esc_html__( 'Delete', 'atelier-irisee-master-plugin' ) . '</a></td></tr>';
				}
				echo '</tbody></table>';
			}
			echo '</div>';
			return;
		}

		$id   = 'new' === $edit ? 0 : absint( $edit );
		$spot = $id && isset( $all[ $id ] ) ? $all[ $id ] : self::clean( array() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a notice only.
		if ( ! empty( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Saved.', 'atelier-irisee-master-plugin' ) . ' <code>[' . esc_html( self::TAG ) . ' id="' . (int) $id . '"]</code></p></div>';
		}
		if ( $id ) {
			$sizes = self::sizes( $spot );
			if ( null === $sizes ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The pattern of this kit is no longer available: choose another one.', 'atelier-irisee-master-plugin' ) . '</p></div>';
			} elseif ( ! array_filter( wp_list_pluck( $sizes, 'available' ) ) ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'No size of this kit can be ordered right now (stock, or an item that does not fit). The block shows "sold out".', 'atelier-irisee-master-plugin' ) . '</p></div>';
			} else {
				$closed = array();
				foreach ( $sizes as $size ) {
					if ( ! $size['available'] ) {
						$closed[] = $size['label'] . ' (' . $size['reason'] . ')';
					}
				}
				if ( $closed ) {
					echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Not orderable now:', 'atelier-irisee-master-plugin' ) . ' ' . esc_html( implode( '; ', $closed ) ) . '</p></div>';
				}
			}
		}
		$choices  = self::choices( $spot['size'], $spot['fabric'] );
		$patterns = array( 0 => __( '— Choose a pattern —', 'atelier-irisee-master-plugin' ) ) + self::pattern_options();
		$none     = __( '— None —', 'atelier-irisee-master-plugin' );
		$f        = function ( $key ) {
			return 'spot[' . $key . ']';
		};
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">← <?php esc_html_e( 'All kit spotlights', 'atelier-irisee-master-plugin' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aimp-sp-form" data-choices="<?php echo esc_attr( wp_json_encode( $choices ) ); ?>" data-current="<?php echo esc_attr( wp_json_encode( array( 'fabric' => $spot['fabric'], 'lining' => $spot['lining'], 'finish' => $spot['finish'], 'notions' => (object) $spot['notions'] ) ) ); ?>">
			<input type="hidden" name="action" value="aimp_spotlight_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'aimp_spotlight_save' ); ?>
			<h2><?php esc_html_e( 'The kit', 'atelier-irisee-master-plugin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="aimp-sp-name"><?php esc_html_e( 'Name (only for you)', 'atelier-irisee-master-plugin' ); ?></label></th>
					<td><input id="aimp-sp-name" class="regular-text" name="<?php echo esc_attr( $f( 'name' ) ); ?>" value="<?php echo esc_attr( $spot['name'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Pattern', 'atelier-irisee-master-plugin' ); ?></th>
					<td><?php echo self::select( $f( 'size' ), $patterns, $spot['size'], ' data-pattern' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in select(). ?>
						<p class="description" data-sizes data-label="<?php echo esc_attr__( 'Sizes:', 'atelier-irisee-master-plugin' ) . ' '; ?>"><?php echo $choices ? esc_html( __( 'Sizes:', 'atelier-irisee-master-plugin' ) . ' ' . $choices['sizes'] ) : ''; ?></p></td></tr>
				<tr data-row="fabric"><th><?php esc_html_e( 'Fabric', 'atelier-irisee-master-plugin' ); ?></th><td><select name="<?php echo esc_attr( $f( 'fabric' ) ); ?>" data-fabric></select>
					<p class="description"><?php esc_html_e( 'Only fabrics that suit the pattern. The amount follows the size the visitor picks.', 'atelier-irisee-master-plugin' ); ?></p></td></tr>
				<tr data-row="lining"><th><?php esc_html_e( 'Lining', 'atelier-irisee-master-plugin' ); ?></th><td><select name="<?php echo esc_attr( $f( 'lining' ) ); ?>" data-lining></select></td></tr>
				<tr data-row="finish"><th><?php esc_html_e( 'Finish', 'atelier-irisee-master-plugin' ); ?></th><td><select name="<?php echo esc_attr( $f( 'finish' ) ); ?>" data-finish></select></td></tr>
				<tr><th><?php esc_html_e( 'Haberdashery', 'atelier-irisee-master-plugin' ); ?></th><td><div class="aimp-sp-notions" data-notions></div>
					<p class="description"><?php esc_html_e( 'One item per kind the pattern needs. Leave one on "none" to leave it out of the kit. An item that does not fit a size (e.g. the length of a zip) makes that size unavailable.', 'atelier-irisee-master-plugin' ); ?></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Picture and elements', 'atelier-irisee-master-plugin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Picture of the kit', 'atelier-irisee-master-plugin' ); ?></th><td><?php echo self::picture( $f( 'image' ), $spot['image'], __( 'Standard: the pattern picture', 'atelier-irisee-master-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in picture(). ?>
					<p><label><?php esc_html_e( 'Focal point: left / right', 'atelier-irisee-master-plugin' ); ?> <input type="number" min="0" max="100" name="<?php echo esc_attr( $f( 'fx' ) ); ?>" value="<?php echo (int) $spot['fx']; ?>" class="small-text"> %</label>
					<label><?php esc_html_e( 'top / bottom', 'atelier-irisee-master-plugin' ); ?> <input type="number" min="0" max="100" name="<?php echo esc_attr( $f( 'fy' ) ); ?>" value="<?php echo (int) $spot['fy']; ?>" class="small-text"> %</label></p>
					<p><label><input type="checkbox" name="<?php echo esc_attr( $f( 'right' ) ); ?>" value="1" <?php checked( $spot['right'] ); ?>> <?php esc_html_e( 'The picture on the right', 'atelier-irisee-master-plugin' ); ?></label></p></td></tr>
				<tr><th><?php esc_html_e( 'Look of the elements', 'atelier-irisee-master-plugin' ); ?></th><td>
					<?php
					echo self::select( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in select().
						$f( 'style' ),
						array(
							'medallion' => __( 'Photo medallions: a round photo with a gold edge and a ribbon', 'atelier-irisee-master-plugin' ),
							'drawing'   => __( 'Gold line drawings that move, on cream badges', 'atelier-irisee-master-plugin' ),
							'tag'       => __( 'Hanging paper tags with the product name', 'atelier-irisee-master-plugin' ),
						),
						$spot['style']
					);
					?>
				</td></tr>
				<tr><th><?php esc_html_e( 'The elements', 'atelier-irisee-master-plugin' ); ?></th><td>
					<?php
					echo self::select( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in select().
						$f( 'move' ),
						array(
							'move'  => __( 'Floating (standard)', 'atelier-irisee-master-plugin' ),
							'still' => __( 'Standing still', 'atelier-irisee-master-plugin' ),
							'off'   => __( 'Not shown', 'atelier-irisee-master-plugin' ),
						),
						$spot['move']
					);
					?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Needle', 'atelier-irisee-master-plugin' ); ?></th><td>
					<label><input type="checkbox" name="<?php echo esc_attr( $f( 'needle' ) ); ?>" value="1" <?php checked( $spot['needle'] ); ?>> <?php esc_html_e( 'A needle sews a circle round the picture, through the elements', 'atelier-irisee-master-plugin' ); ?></label>
					<p><label><?php esc_html_e( 'Speed', 'atelier-irisee-master-plugin' ); ?> <input type="number" min="25" max="300" step="5" name="<?php echo esc_attr( $f( 'speed' ) ); ?>" value="<?php echo (int) $spot['speed']; ?>" class="small-text"> %</label></p>
					<p class="description"><?php esc_html_e( 'Visitors who asked their device for less motion see everything standing still.', 'atelier-irisee-master-plugin' ); ?></p>
				</td></tr>
				<?php
				foreach ( array(
					'img_pattern' => __( 'Picture of the pattern element', 'atelier-irisee-master-plugin' ),
					'img_fabric'  => __( 'Picture of the fabric element', 'atelier-irisee-master-plugin' ),
					'img_notions' => __( 'Picture of the haberdashery element', 'atelier-irisee-master-plugin' ),
				) as $key => $label ) {
					echo '<tr><th>' . esc_html( $label ) . '</th><td>' . self::picture( $f( $key ), $spot[ $key ], __( 'Standard: the product picture', 'atelier-irisee-master-plugin' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in picture().
				}
				?>
			</table>

			<h2><?php esc_html_e( 'Texts', 'atelier-irisee-master-plugin' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Leave a field empty for the standard text, shown in grey. Without a title the pattern name is shown.', 'atelier-irisee-master-plugin' ); ?></p>
			<table class="widefat striped aimp-sp-texts"><thead><tr><th></th>
				<?php foreach ( AIMP_I18n::languages() as $language ) : ?>
					<th><?php echo esc_html( $language['name'] ); ?></th>
				<?php endforeach; ?>
			</tr></thead><tbody>
			<?php
			$labels   = array(
				'kicker'    => __( 'Small line above the title', 'atelier-irisee-master-plugin' ),
				'title'     => __( 'Title', 'atelier-irisee-master-plugin' ),
				'text'      => __( 'Text', 'atelier-irisee-master-plugin' ),
				'button'    => __( 'Button', 'atelier-irisee-master-plugin' ),
				'l_pattern' => __( 'Label of the pattern element', 'atelier-irisee-master-plugin' ),
				'l_fabric'  => __( 'Label of the fabric element', 'atelier-irisee-master-plugin' ),
				'l_notions' => __( 'Label of the haberdashery element', 'atelier-irisee-master-plugin' ),
			);
			$defaults = array();
			foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
				$defaults[ $code ] = AIMP_I18n::with_language( $code, array( __CLASS__, 'default_texts' ) );
			}
			foreach ( $labels as $key => $label ) {
				echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
				foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
					$name  = 'spot[texts][' . $key . '_' . $code . ']';
					$value = $spot['texts'][ $key . '_' . $code ];
					if ( 'text' === $key ) {
						echo '<td><textarea class="widefat" rows="3" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea></td>';
					} else {
						echo '<td><input type="text" class="widefat" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $defaults[ $code ][ $key ] ) . '"></td>';
					}
				}
				echo '</tr>';
			}
			?>
			</tbody></table>
			<?php submit_button( __( 'Save kit spotlight', 'atelier-irisee-master-plugin' ) ); ?>
		</form>
		<script type="text/template" id="aimp-sp-none"><?php echo esc_html( $none ); ?></script>
		</div>
		<?php
	}
}
