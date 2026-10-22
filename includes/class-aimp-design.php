<?php
/**
 * Design studio (Atelier Irisee → Design studio): the site in a live preview; click a part of it (or pick it from the
 * list) and move it, make it bigger or smaller, hide it, change its colours, corners, spacing, picture (with focal
 * point) and animations — for computers and phones apart — and write your own wording for every text of the plugin.
 * Changes are a draft (only visible in the studio) until "Publish"; every publish is kept in the history. A draft can
 * also be saved as a named look, and a look can switch itself on between two dates (e.g. Christmas).
 *
 * The parts that can be changed are registered in elements() with a CSS selector; the result is one small style block
 * in the page head. Moving uses the CSS "translate" property and resizing "zoom", so the layout around a part does not
 * break. Texts go through the plugin's own translation layer (AIMP_I18n::translate()).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Design {

	/** [ live, draft, live_texts, draft_texts, live_time, draft_time ]. */
	const OPTION = 'aimp_design';

	/** Earlier published looks: [ [ time, user, styles, texts ], … ], newest first. */
	const HISTORY = 'aimp_design_history';

	const HISTORY_MAX = 20;

	/** Saved looks: id => [ name, styles, texts, from, to, time ]. */
	const LOOKS = 'aimp_design_looks';

	const PAGE = 'aimp-design';

	const NONCE = 'aimp_design';

	/** The query argument of the preview inside the studio. */
	const PREVIEW = 'aimp_design_preview';

	/** Phones: up to this width (the same break as the rest of the plugin's phone layout). */
	const PHONE = 700;

	/** The texts in use this request (draft in the preview, else the published or planned look). */
	private static $texts = null;

	public static function init() {
		// After the main Atelier Irisee item (AIMP_Settings::add_menu(), priority 60).
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 61 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		foreach ( array( 'save', 'publish', 'discard', 'restore', 'import', 'texts', 'look_save', 'look_load', 'look_delete', 'look_plan' ) as $action ) {
			add_action( 'wp_ajax_aimp_design_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
		add_action( 'wp_head', array( __CLASS__, 'print_style' ), 100 );
		add_action( 'wp_footer', array( __CLASS__, 'print_speed' ), 100 );
		add_action( 'template_redirect', array( __CLASS__, 'preview_headers' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'preview_assets' ), 30 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ), 50 );
		// A planned look starts or ends: cached pages get the new look.
		add_action( 'aimp_design_switch', array( __CLASS__, 'purge' ) );
	}

	/* ------------------------------------------------------------------ what can be changed */

	/**
	 * The parts of the site that can be changed: id => [ group, label, selector ].
	 *
	 * @return array
	 */
	public static function elements() {
		$header  = __( 'Header and menu', 'atelier-irisee-master-plugin' );
		$hero    = __( 'Home page hero', 'atelier-irisee-master-plugin' );
		$steps   = __( 'Kit steps bar', 'atelier-irisee-master-plugin' );
		$shop    = __( 'Shop pages', 'atelier-irisee-master-plugin' );
		$product = __( 'Product page', 'atelier-irisee-master-plugin' );
		$config  = __( 'Customizer', 'atelier-irisee-master-plugin' );
		$cart    = __( 'Cart', 'atelier-irisee-master-plugin' );
		$account = __( 'My account', 'atelier-irisee-master-plugin' );
		$footer  = __( 'Footer', 'atelier-irisee-master-plugin' );
		$list    = array(
			'header'          => array( $header, __( 'Header (whole)', 'atelier-irisee-master-plugin' ), '.aimp-header-inner' ),
			'header-logo'     => array( $header, __( 'Logo', 'atelier-irisee-master-plugin' ), '.aimp-header-brand' ),
			'header-icons'    => array( $header, __( 'Header icons', 'atelier-irisee-master-plugin' ), '.aimp-header-icons' ),
			'header-menu-bar' => array( $header, __( '"Menu" bar', 'atelier-irisee-master-plugin' ), '.aimp-header-bar' ),
			'info-bar'        => array( $header, __( 'Gold info bar', 'atelier-irisee-master-plugin' ), '.aimp-info-bar' ),
			'side-menu'       => array( $header, __( 'Side menu', 'atelier-irisee-master-plugin' ), '.aimp-side-menu' ),
			'tabbar'          => array( $header, __( 'Phone tab bar', 'atelier-irisee-master-plugin' ), '.aimp-tabbar' ),
			'hero'            => array( $hero, __( 'Hero (whole)', 'atelier-irisee-master-plugin' ), '.aimp-hero' ),
			'hero-logo'       => array( $hero, __( 'Hero logo', 'atelier-irisee-master-plugin' ), '.aimp-hero-brand' ),
			'hero-title'      => array( $hero, __( 'Hero title', 'atelier-irisee-master-plugin' ), '.aimp-hero-title' ),
			'hero-text'       => array( $hero, __( 'Hero text', 'atelier-irisee-master-plugin' ), '.aimp-hero-text' ),
			'hero-buttons'    => array( $hero, __( 'Hero buttons', 'atelier-irisee-master-plugin' ), '.aimp-hero-buttons' ),
			'hero-arrow'      => array( $hero, __( 'Hero arrow', 'atelier-irisee-master-plugin' ), '.aimp-hero-arrow' ),
			'hero-image'      => array( $hero, __( 'Hero picture', 'atelier-irisee-master-plugin' ), '.aimp-hero-img, .aimp-hero-img-phone' ),
			'kit-steps'       => array( $steps, __( 'Kit steps bar (whole)', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps' ),
			'kit-steps-title' => array( $steps, __( 'Kit steps title', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps-title' ),
			'kit-steps-list'  => array( $steps, __( 'Kit steps cards', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps-list' ),
			'kit-steps-button' => array( $steps, __( 'Kit steps button', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps-button' ),
			'overview'        => array( $shop, __( 'Overview cards (whole)', 'atelier-irisee-master-plugin' ), '.aimp-overview-grid' ),
			'overview-card'   => array( $shop, __( 'Overview cards (each)', 'atelier-irisee-master-plugin' ), '.aimp-overview-card' ),
			'overview-hero'   => array( $shop, __( 'Large overview card text', 'atelier-irisee-master-plugin' ), '.aimp-overview-hero-text' ),
			'card-name'       => array( $shop, __( 'Product names on cards', 'atelier-irisee-master-plugin' ), '.aimp-card-name' ),
			'card-price'      => array( $shop, __( 'Prices on cards', 'atelier-irisee-master-plugin' ), '.aimp-card-price' ),
			'mosaic'          => array( $shop, __( 'Mosaic', 'atelier-irisee-master-plugin' ), '.aimp-mosaic-grid' ),
			'product-title'   => array( $product, __( 'Product title', 'atelier-irisee-master-plugin' ), '.aimp-product-title' ),
			'product-brand'   => array( $product, __( 'Brand', 'atelier-irisee-master-plugin' ), '.aimp-product-brand' ),
			'product-subtitle' => array( $product, __( 'Product subtitle', 'atelier-irisee-master-plugin' ), '.aimp-product-subtitle' ),
			'product-gallery' => array( $product, __( 'Product pictures', 'atelier-irisee-master-plugin' ), '.aimp-gallery' ),
			'product-buy'     => array( $product, __( 'Buy bar', 'atelier-irisee-master-plugin' ), '.aimp-buy-bar' ),
			'product-price'   => array( $product, __( 'Product price', 'atelier-irisee-master-plugin' ), '.aimp-buy-price' ),
			'product-desc'    => array( $product, __( 'Description', 'atelier-irisee-master-plugin' ), '.aimp-description' ),
			'product-specs'   => array( $product, __( 'Specifications', 'atelier-irisee-master-plugin' ), '.aimp-info-list' ),
			'product-recommend' => array( $product, __( 'Recommended products', 'atelier-irisee-master-plugin' ), '.aimp-recommended' ),
			'config-steps'    => array( $config, __( 'Step indicator', 'atelier-irisee-master-plugin' ), '.aimp-configurator .aimp-steps' ),
			'config-title'    => array( $config, __( 'Details titles', 'atelier-irisee-master-plugin' ), '.aimp-details-title' ),
			'cart-items'      => array( $cart, __( 'Cart items', 'atelier-irisee-master-plugin' ), '.aimp-cart-items' ),
			'cart-side'       => array( $cart, __( 'Cart totals', 'atelier-irisee-master-plugin' ), '.aimp-cart-side' ),
			'cart-coupon'     => array( $cart, __( 'Discount code field', 'atelier-irisee-master-plugin' ), '.aimp-cart-coupon' ),
			'cart-empty'      => array( $cart, __( 'Empty cart', 'atelier-irisee-master-plugin' ), '.aimp-cart-empty' ),
			'account-tabs'    => array( $account, __( 'Account tabs', 'atelier-irisee-master-plugin' ), '.aimp-account-tabs' ),
			'account-title'   => array( $account, __( 'Account titles', 'atelier-irisee-master-plugin' ), '.aimp-account-title' ),
			'account-hello'   => array( $account, __( 'Welcome text', 'atelier-irisee-master-plugin' ), '.aimp-account-hello' ),
			'footer'          => array( $footer, __( 'Footer (whole)', 'atelier-irisee-master-plugin' ), '.aimp-footer' ),
			'footer-columns'  => array( $footer, __( 'Footer columns', 'atelier-irisee-master-plugin' ), '.aimp-footer-columns' ),
			'footer-title'    => array( $footer, __( 'Footer titles', 'atelier-irisee-master-plugin' ), '.aimp-footer-title' ),
			'newsletter'      => array( $footer, __( 'Newsletter', 'atelier-irisee-master-plugin' ), '.aimp-newsletter' ),
			'footer-socials'  => array( $footer, __( 'Social media icons', 'atelier-irisee-master-plugin' ), '.aimp-footer-socials' ),
			'footer-payments' => array( $footer, __( 'Payment logos', 'atelier-irisee-master-plugin' ), '.aimp-footer-payments' ),
			'footer-bottom'   => array( $footer, __( 'Bottom line', 'atelier-irisee-master-plugin' ), '.aimp-footer-bottom' ),
		);
		$elements = array();
		foreach ( $list as $id => $row ) {
			$elements[ $id ] = array(
				'group'    => $row[0],
				'label'    => $row[1],
				'selector' => $row[2],
			);
		}
		return apply_filters( 'aimp_design_elements', $elements );
	}

	/**
	 * The settings of one part: key => [ min, max ] for numbers, 'bool', 'color' or 'image'. A "p" in front = on phones.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'x'      => array( -200, 200 ),
			'y'      => array( -200, 200 ),
			'zoom'   => array( 40, 250 ),
			'hide'   => 'bool',
			'mt'     => array( -150, 300 ),
			'mb'     => array( -150, 300 ),
			'px'     => array( -200, 200 ),
			'py'     => array( -200, 200 ),
			'pzoom'  => array( 40, 250 ),
			'phide'  => 'bool',
			'pmt'    => array( -150, 300 ),
			'pmb'    => array( -150, 300 ),
			'color'  => 'color',
			'bg'     => 'color',
			'border' => 'color',
			'radius' => array( 0, 200 ),
			// Picture: another picture from the media library, and the point that stays in view when it is cropped.
			'img'    => 'image',
			'fx'     => array( 0, 100 ),
			'fy'     => array( 0, 100 ),
			// Animations: off, or slower / faster (percent of the normal speed).
			'noanim' => 'bool',
			'speed'  => array( 25, 400 ),
		);
	}

	/**
	 * The value of a setting that changes nothing (it is not kept).
	 *
	 * @param string $key Setting.
	 * @return int
	 */
	private static function neutral( $key ) {
		if ( in_array( $key, array( 'zoom', 'pzoom', 'speed' ), true ) ) {
			return 100;
		}
		return in_array( $key, array( 'fx', 'fy' ), true ) ? 50 : 0;
	}

	/**
	 * Only known parts and settings, within their limits.
	 *
	 * @param mixed $styles Posted or imported styles.
	 * @return array
	 */
	public static function clean( $styles ) {
		$clean    = array();
		$elements = self::elements();
		$fields   = self::fields();
		foreach ( is_array( $styles ) ? $styles : array() as $id => $values ) {
			if ( ! isset( $elements[ $id ] ) || ! is_array( $values ) ) {
				continue;
			}
			$row = array();
			foreach ( $fields as $key => $rule ) {
				if ( ! isset( $values[ $key ] ) || '' === $values[ $key ] || null === $values[ $key ] ) {
					continue;
				}
				$value = $values[ $key ];
				if ( 'bool' === $rule ) {
					if ( ! empty( $value ) && 'false' !== $value ) {
						$row[ $key ] = 1;
					}
				} elseif ( 'color' === $rule ) {
					$color = sanitize_hex_color( (string) $value );
					if ( $color ) {
						$row[ $key ] = $color;
					}
				} elseif ( 'image' === $rule ) {
					if ( absint( $value ) && wp_attachment_is_image( absint( $value ) ) ) {
						$row[ $key ] = absint( $value );
					}
				} elseif ( is_numeric( $value ) ) {
					$number = (int) round( (float) $value );
					if ( $number !== self::neutral( $key ) ) {
						$row[ $key ] = max( $rule[0], min( $rule[1], $number ) );
					}
				}
			}
			if ( $row ) {
				$clean[ $id ] = $row;
			}
		}
		return $clean;
	}

	/**
	 * The placeholders of a text (%s, %1$d, …), sorted: an own text must keep the same ones.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	private static function placeholders( $text ) {
		preg_match_all( '/%(?:\d+\$)?[sdfu]/', (string) $text, $found );
		$list = $found[0];
		sort( $list );
		return $list;
	}

	/**
	 * Own texts: English original => [ nl, fr, en ]. Only texts the plugin has, with the same placeholders (a text
	 * without its %s would break the sentence it is used in).
	 *
	 * @param mixed $texts Posted or imported texts.
	 * @return array
	 */
	public static function clean_texts( $texts ) {
		$known = self::dictionary( 'nl' );
		$clean = array();
		foreach ( is_array( $texts ) ? $texts : array() as $original => $langs ) {
			$original = (string) $original;
			if ( ! isset( $known[ $original ] ) || ! is_array( $langs ) ) {
				continue;
			}
			$row = array();
			foreach ( array( 'nl', 'fr', 'en' ) as $lang ) {
				if ( ! isset( $langs[ $lang ] ) || '' === trim( (string) $langs[ $lang ] ) ) {
					continue;
				}
				$value = false !== strpos( $original, '<' ) ? wp_kses_post( (string) $langs[ $lang ] ) : sanitize_textarea_field( (string) $langs[ $lang ] );
				$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 2000 ) : substr( $value, 0, 2000 );
				if ( self::placeholders( $value ) === self::placeholders( $original ) ) {
					$row[ $lang ] = $value;
				}
			}
			if ( $row ) {
				$clean[ $original ] = $row;
			}
		}
		return $clean;
	}

	/**
	 * A language file of the plugin (English original => translation).
	 *
	 * @param string $lang nl or fr.
	 * @return array
	 */
	private static function dictionary( $lang ) {
		static $files = array();
		if ( ! isset( $files[ $lang ] ) ) {
			$file           = AIMP_PLUGIN_DIR . 'includes/languages/' . $lang . '.php';
			$files[ $lang ] = file_exists( $file ) ? (array) include $file : array();
		}
		return $files[ $lang ];
	}

	/* ------------------------------------------------------------------ stored looks */

	private static function data() {
		$data = get_option( self::OPTION, array() );
		$data = is_array( $data ) ? $data : array();
		return wp_parse_args(
			$data,
			array(
				'live'        => array(),
				'draft'       => null,
				'live_texts'  => array(),
				'draft_texts' => null,
				'live_time'   => 0,
				'draft_time'  => 0,
			)
		);
	}

	/**
	 * The published look (styles).
	 *
	 * @return array
	 */
	public static function live() {
		$data = self::data();
		return is_array( $data['live'] ) ? $data['live'] : array();
	}

	/**
	 * The published texts.
	 *
	 * @return array
	 */
	public static function live_texts() {
		$data = self::data();
		return is_array( $data['live_texts'] ) ? $data['live_texts'] : array();
	}

	/**
	 * The draft (the published look when there is no draft).
	 *
	 * @return array
	 */
	public static function draft() {
		$data = self::data();
		return is_array( $data['draft'] ) ? $data['draft'] : self::live();
	}

	/**
	 * The draft texts (the published texts when there is no draft).
	 *
	 * @return array
	 */
	public static function draft_texts() {
		$data = self::data();
		return is_array( $data['draft_texts'] ) ? $data['draft_texts'] : self::live_texts();
	}

	private static function store( $changes ) {
		update_option( self::OPTION, array_merge( self::data(), $changes ), true );
	}

	private static function history() {
		$history = get_option( self::HISTORY, array() );
		return is_array( $history ) ? $history : array();
	}

	private static function looks() {
		$looks = get_option( self::LOOKS, array() );
		return is_array( $looks ) ? $looks : array();
	}

	/**
	 * The planned look that is on now (its dates include today), or null. The latest start wins.
	 *
	 * @return array|null
	 */
	public static function planned() {
		$now  = time();
		$best = null;
		foreach ( self::looks() as $look ) {
			if ( ! empty( $look['from'] ) && ! empty( $look['to'] ) && $look['from'] <= $now && $now < $look['to'] && ( ! $best || $look['from'] > $best['from'] ) ) {
				$best = $look;
			}
		}
		return $best;
	}

	/**
	 * What visitors see: [ styles, texts ] of the planned look that is on now, else of the published look.
	 *
	 * @return array
	 */
	public static function visible() {
		$planned = self::planned();
		if ( $planned ) {
			return array( (array) $planned['styles'], (array) $planned['texts'] );
		}
		return array( self::live(), self::live_texts() );
	}

	/**
	 * An own text for the page being made, or null (AIMP_I18n::translate() asks this first).
	 *
	 * @param string $original English original.
	 * @param string $lang     Language of the page.
	 * @return string|null
	 */
	public static function text( $original, $lang ) {
		if ( null === self::$texts ) {
			// Before WordPress knows who is looking, nothing is kept: the next call decides.
			if ( ! did_action( 'set_current_user' ) ) {
				$texts = self::visible();
				return isset( $texts[1][ $original ][ $lang ] ) ? $texts[1][ $original ][ $lang ] : null;
			}
			$visible     = self::visible();
			self::$texts = self::is_preview() ? self::draft_texts() : $visible[1];
		}
		return isset( self::$texts[ $original ][ $lang ] ) ? self::$texts[ $original ][ $lang ] : null;
	}

	public static function purge() {
		do_action( 'litespeed_purge_all' );
	}

	/* ------------------------------------------------------------------ the style block */

	/**
	 * "html body" in front of every part of a selector list, so these rules win over the plugin's own; $extra goes after
	 * each part (e.g. " img").
	 *
	 * @param string $selector Selector list.
	 * @param string $extra    Added to each part.
	 * @return string
	 */
	private static function prefixed( $selector, $extra = '' ) {
		return implode(
			',',
			array_map(
				function ( $part ) use ( $extra ) {
					return 'html body ' . trim( $part ) . $extra;
				},
				explode( ',', $selector )
			)
		);
	}

	/**
	 * The CSS of a look.
	 *
	 * @param array $styles Styles (clean()).
	 * @return string
	 */
	public static function css( $styles ) {
		$elements = self::elements();
		$all      = '';
		$computer = '';
		$phone    = '';
		foreach ( $styles as $id => $row ) {
			if ( ! isset( $elements[ $id ] ) ) {
				continue;
			}
			$base     = $elements[ $id ]['selector'];
			$selector = self::prefixed( $base );
			$common   = '';
			foreach ( array( 'color' => 'color', 'bg' => 'background-color', 'border' => 'border-color' ) as $key => $property ) {
				if ( isset( $row[ $key ] ) ) {
					$common .= $property . ':' . $row[ $key ] . '!important;';
				}
			}
			if ( isset( $row['radius'] ) ) {
				$common .= 'border-radius:' . (int) $row['radius'] . 'px!important;';
			}
			if ( '' !== $common ) {
				$all .= $selector . '{' . $common . '}';
			}
			// The picture: the part itself when it is a picture, else the pictures in it.
			$images = self::prefixed( $base, ':is(img)' ) . ',' . self::prefixed( $base, ' img' );
			$image  = '';
			if ( ! empty( $row['img'] ) ) {
				$url = wp_get_attachment_image_url( (int) $row['img'], 'large' );
				if ( $url ) {
					$image .= 'content:url("' . esc_url_raw( $url ) . '")!important;object-fit:cover;';
				}
			}
			if ( isset( $row['fx'] ) || isset( $row['fy'] ) ) {
				$image .= 'object-position:' . ( isset( $row['fx'] ) ? (int) $row['fx'] : 50 ) . '% ' . ( isset( $row['fy'] ) ? (int) $row['fy'] : 50 ) . '%!important;';
			}
			if ( '' !== $image ) {
				$all .= $images . '{' . $image . '}';
			}
			if ( ! empty( $row['noanim'] ) ) {
				$all .= $selector . ',' . self::prefixed( $base, ' *' ) . ',' . self::prefixed( $base, '::before' ) . ',' . self::prefixed( $base, '::after' ) . ',' . self::prefixed( $base, ' *::before' ) . ',' . self::prefixed( $base, ' *::after' ) . '{animation:none!important;transition:none!important}';
			}
			$device = function ( $prefix ) use ( $row ) {
				$css = '';
				$x   = isset( $row[ $prefix . 'x' ] ) ? (int) $row[ $prefix . 'x' ] : 0;
				$y   = isset( $row[ $prefix . 'y' ] ) ? (int) $row[ $prefix . 'y' ] : 0;
				if ( $x || $y ) {
					$css .= 'translate:' . $x . 'px ' . $y . 'px!important;';
				}
				if ( isset( $row[ $prefix . 'zoom' ] ) ) {
					$css .= 'zoom:' . ( (int) $row[ $prefix . 'zoom' ] / 100 ) . '!important;';
				}
				if ( isset( $row[ $prefix . 'mt' ] ) ) {
					$css .= 'margin-top:' . (int) $row[ $prefix . 'mt' ] . 'px!important;';
				}
				if ( isset( $row[ $prefix . 'mb' ] ) ) {
					$css .= 'margin-bottom:' . (int) $row[ $prefix . 'mb' ] . 'px!important;';
				}
				if ( ! empty( $row[ $prefix . 'hide' ] ) ) {
					$css .= 'display:none!important;';
				}
				return $css;
			};
			$desktop = $device( '' );
			$mobile  = $device( 'p' );
			if ( '' !== $desktop ) {
				$computer .= $selector . '{' . $desktop . '}';
			}
			if ( '' !== $mobile ) {
				$phone .= $selector . '{' . $mobile . '}';
			}
		}
		$css = $all;
		if ( '' !== $computer ) {
			$css .= '@media (min-width:' . ( self::PHONE + 1 ) . 'px){' . $computer . '}';
		}
		if ( '' !== $phone ) {
			$css .= '@media (max-width:' . self::PHONE . 'px){' . $phone . '}';
		}
		return $css;
	}

	/**
	 * Animation speeds of a look: selector => percent.
	 *
	 * @param array $styles Styles.
	 * @return array
	 */
	private static function speeds( $styles ) {
		$elements = self::elements();
		$speeds   = array();
		foreach ( $styles as $id => $row ) {
			if ( isset( $elements[ $id ], $row['speed'] ) && empty( $row['noanim'] ) ) {
				$speeds[ $elements[ $id ]['selector'] ] = (int) $row['speed'];
			}
		}
		return $speeds;
	}

	/** The styles of this request: the draft in the studio's preview. */
	private static function current_styles() {
		if ( self::is_preview() ) {
			return self::draft();
		}
		$visible = self::visible();
		return $visible[0];
	}

	public static function print_style() {
		$preview = self::is_preview();
		$css     = self::css( self::current_styles() );
		// In the studio's preview the block is always there: the studio replaces its content while you edit.
		if ( '' !== $css || $preview ) {
			echo '<style id="aimp-design-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from clean values and fixed selectors.
		}
	}

	/**
	 * Slower or faster animations: every CSS animation and transition that starts inside a part gets its speed
	 * (the browser's own playback rate; the plugin's styles stay as they are).
	 */
	public static function print_speed() {
		$speeds  = self::speeds( self::current_styles() );
		$preview = self::is_preview();
		if ( ! $speeds && ! $preview ) {
			return;
		}
		?>
		<script id="aimp-design-speed">
		(function () {
			window.aimpDesignSpeed = <?php echo wp_json_encode( (object) $speeds ); ?>;
			function rate(el) {
				var map = window.aimpDesignSpeed || {};
				for (var sel in map) {
					try {
						if (el.closest(sel)) {
							return map[sel] / 100;
						}
					} catch (e) {}
				}
				return 1;
			}
			function apply(e) {
				var el = e && e.target && e.target.nodeType === 1 ? e.target : null;
				var list = el && el.getAnimations ? el.getAnimations() : (document.getAnimations ? document.getAnimations() : []);
				list.forEach(function (a) {
					var target = a.effect && a.effect.target;
					if (target && target.nodeType === 1) {
						a.playbackRate = rate(target);
					}
				});
			}
			window.aimpDesignSpeedApply = apply;
			['animationstart', 'transitionstart', 'transitionrun'].forEach(function (type) {
				document.addEventListener(type, apply, true);
			});
			apply();
		})();
		</script>
		<?php
	}

	/* ------------------------------------------------------------------ the preview inside the studio */

	public static function is_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only switches the view for shop managers.
		return isset( $_GET[ self::PREVIEW ] ) && ! is_admin() && current_user_can( 'manage_woocommerce' );
	}

	public static function preview_headers() {
		if ( ! self::is_preview() ) {
			return;
		}
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'Atelier Irisee design preview' );
	}

	public static function hide_admin_bar( $show ) {
		return self::is_preview() ? false : $show;
	}

	public static function preview_assets() {
		if ( ! self::is_preview() ) {
			return;
		}
		wp_enqueue_script( 'aimp-design-preview', AIMP_PLUGIN_URL . 'assets/js/design-preview.js', array(), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-design-preview',
			'aimpDesignPreview',
			array(
				'origin'   => admin_url(),
				'param'    => self::PREVIEW,
				'phone'    => self::PHONE,
				'elements' => wp_list_pluck( self::elements(), 'selector' ),
			)
		);
	}

	/* ------------------------------------------------------------------ the studio page */

	public static function admin_menu() {
		// The first item keeps the settings; the studio comes right after it.
		add_submenu_page( AIMP_Settings::PAGE, __( 'Settings', 'atelier-irisee-master-plugin' ), __( 'Settings', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', AIMP_Settings::PAGE );
		add_submenu_page( AIMP_Settings::PAGE, __( 'Design studio', 'atelier-irisee-master-plugin' ), __( 'Design studio', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render_page' ) );
	}

	/**
	 * Pages to look at in the studio: [ label, address ].
	 *
	 * @return array
	 */
	private static function pages() {
		$pages = array( array( __( 'Home page', 'atelier-irisee-master-plugin' ), home_url( '/' ) ) );
		$types = array(
			'all'          => __( 'All products', 'atelier-irisee-master-plugin' ),
			'fabrics'      => __( 'Fabrics', 'atelier-irisee-master-plugin' ),
			'patterns'     => __( 'Patterns', 'atelier-irisee-master-plugin' ),
			'haberdashery' => __( 'Haberdashery', 'atelier-irisee-master-plugin' ),
		);
		foreach ( $types as $type => $label ) {
			$page = AIMP_Shop::page_for_type( $type );
			if ( $page ) {
				$pages[] = array( $label, get_permalink( $page ) );
			}
		}
		$configurator = AIMP_Settings::get( 'configurator_page' );
		if ( $configurator && 'publish' === get_post_status( $configurator ) ) {
			$pages[] = array( __( 'Customizer', 'atelier-irisee-master-plugin' ), get_permalink( $configurator ) );
		}
		// One product of each kind.
		foreach ( array( 'fabric_cat' => __( 'A fabric', 'atelier-irisee-master-plugin' ), 'pattern_cat' => __( 'A pattern', 'atelier-irisee-master-plugin' ), 'button_cat' => __( 'A button', 'atelier-irisee-master-plugin' ) ) as $setting => $label ) {
			$cat = AIMP_Settings::get( $setting );
			if ( ! $cat ) {
				continue;
			}
			$ids = wc_get_products(
				array(
					'status'   => 'publish',
					'limit'    => 1,
					'return'   => 'ids',
					'category' => array( (string) get_term_field( 'slug', $cat, 'product_cat' ) ),
				)
			);
			if ( $ids ) {
				$pages[] = array( $label, get_permalink( $ids[0] ) );
			}
		}
		$pages[] = array( __( 'Cart', 'atelier-irisee-master-plugin' ), wc_get_cart_url() );
		$pages[] = array( __( 'My account', 'atelier-irisee-master-plugin' ), wc_get_page_permalink( 'myaccount' ) );
		return $pages;
	}

	public static function admin_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::PAGE ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'aimp-admin-design', AIMP_PLUGIN_URL . 'assets/css/admin-design.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-admin-design', AIMP_PLUGIN_URL . 'assets/js/admin-design.js', array(), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-design',
			'aimpDesign',
			array_merge(
				self::state(),
				array(
					'ajax'     => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( self::NONCE ),
					'param'    => self::PREVIEW,
					'phone'    => self::PHONE,
					'pages'    => self::pages(),
					'elements' => self::elements(),
					'i18n'     => array(
						'saving'      => __( 'Saving…', 'atelier-irisee-master-plugin' ),
						'saved'       => __( 'Draft saved', 'atelier-irisee-master-plugin' ),
						'unpublished' => __( 'Not published yet', 'atelier-irisee-master-plugin' ),
						'published'   => __( 'Published', 'atelier-irisee-master-plugin' ),
						'failed'      => __( 'Could not save. Check your connection and try again.', 'atelier-irisee-master-plugin' ),
						'confirm'     => __( 'Click again to confirm.', 'atelier-irisee-master-plugin' ),
						'restored'    => __( 'The earlier look is in the draft. Check it, then publish.', 'atelier-irisee-master-plugin' ),
						'imported'    => __( 'Imported into the draft. Check it, then publish.', 'atelier-irisee-master-plugin' ),
						'badFile'     => __( 'This file is not a design export.', 'atelier-irisee-master-plugin' ),
						'changed'     => __( 'changed', 'atelier-irisee-master-plugin' ),
						/* translators: %d: number of changed parts */
						'parts'       => __( '%d parts changed', 'atelier-irisee-master-plugin' ),
						'noHistory'   => __( 'Nothing published yet.', 'atelier-irisee-master-plugin' ),
						'restore'     => __( 'Put in draft', 'atelier-irisee-master-plugin' ),
						'pick'        => __( 'Click a part of the page, or choose it from the list.', 'atelier-irisee-master-plugin' ),
						'choose'      => __( 'Choose a picture', 'atelier-irisee-master-plugin' ),
						'use'         => __( 'Use this picture', 'atelier-irisee-master-plugin' ),
						'loading'     => __( 'Loading…', 'atelier-irisee-master-plugin' ),
						/* translators: 1: texts shown, 2: texts found */
						'textsShown'  => __( '%1$d of %2$d texts shown. Search to find others.', 'atelier-irisee-master-plugin' ),
						'textsOwn'    => __( 'Only my own texts', 'atelier-irisee-master-plugin' ),
						'keepCodes'   => __( 'Keep these codes in your text:', 'atelier-irisee-master-plugin' ),
						'noLooks'     => __( 'No saved looks yet.', 'atelier-irisee-master-plugin' ),
						'load'        => __( 'Load into draft', 'atelier-irisee-master-plugin' ),
						'delete'      => __( 'Delete', 'atelier-irisee-master-plugin' ),
						'planned'     => __( 'Planned', 'atelier-irisee-master-plugin' ),
						'onNow'       => __( 'On now', 'atelier-irisee-master-plugin' ),
						'plan'        => __( 'Plan', 'atelier-irisee-master-plugin' ),
						'from'        => __( 'From', 'atelier-irisee-master-plugin' ),
						'to'          => __( 'To', 'atelier-irisee-master-plugin' ),
						'noPlan'      => __( 'Not planned', 'atelier-irisee-master-plugin' ),
						'lookSaved'   => __( 'The look is saved.', 'atelier-irisee-master-plugin' ),
						'lookLoaded'  => __( 'The look is in the draft. Check it, then publish.', 'atelier-irisee-master-plugin' ),
						'nameNeeded'  => __( 'Give the look a name.', 'atelier-irisee-master-plugin' ),
						'datesNeeded' => __( 'Choose a start and an end date (the end after the start).', 'atelier-irisee-master-plugin' ),
					),
				)
			)
		);
	}

	/**
	 * A range slider with a number box.
	 *
	 * @param string $key   Setting.
	 * @param string $label Label.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @param string $unit  Unit shown after it.
	 */
	private static function number( $key, $label, $min, $max, $unit ) {
		printf(
			'<label class="aimp-ds-num"><span>%1$s</span><input type="range" data-key="%2$s" min="%3$d" max="%4$d" step="1"><input type="number" data-key="%2$s" min="%3$d" max="%4$d" step="1"><em>%5$s</em></label>',
			esc_html( $label ),
			esc_attr( $key ),
			(int) $min,
			(int) $max,
			esc_html( $unit )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$computer = __( 'Computer', 'atelier-irisee-master-plugin' );
		$phone    = __( 'Phone', 'atelier-irisee-master-plugin' );
		?>
		<div class="wrap aimp-ds" data-aimp-design>
			<div class="aimp-ds-bar">
				<h1><?php esc_html_e( 'Design studio', 'atelier-irisee-master-plugin' ); ?></h1>
				<select class="aimp-ds-page" aria-label="<?php esc_attr_e( 'Page', 'atelier-irisee-master-plugin' ); ?>"></select>
				<div class="aimp-ds-devices" role="group" aria-label="<?php esc_attr_e( 'Screen', 'atelier-irisee-master-plugin' ); ?>">
					<button type="button" class="button" data-width="1280" aria-pressed="true"><?php echo esc_html( $computer ); ?></button>
					<button type="button" class="button" data-width="820" aria-pressed="false"><?php esc_html_e( 'Tablet', 'atelier-irisee-master-plugin' ); ?></button>
					<button type="button" class="button" data-width="390" aria-pressed="false"><?php echo esc_html( $phone ); ?></button>
				</div>
				<label class="aimp-ds-pickmode"><input type="checkbox" checked> <?php esc_html_e( 'Click to select', 'atelier-irisee-master-plugin' ); ?></label>
				<span class="aimp-ds-status" role="status"></span>
				<button type="button" class="button aimp-ds-discard"><?php esc_html_e( 'Discard draft', 'atelier-irisee-master-plugin' ); ?></button>
				<button type="button" class="button button-primary aimp-ds-publish"><?php esc_html_e( 'Publish', 'atelier-irisee-master-plugin' ); ?></button>
			</div>
			<div class="aimp-ds-main">
				<aside class="aimp-ds-panel">
					<div class="aimp-ds-tabs" role="tablist">
						<button type="button" role="tab" data-tab="parts" aria-selected="true"><?php esc_html_e( 'Parts', 'atelier-irisee-master-plugin' ); ?></button>
						<button type="button" role="tab" data-tab="texts" aria-selected="false"><?php esc_html_e( 'Texts', 'atelier-irisee-master-plugin' ); ?></button>
						<button type="button" role="tab" data-tab="looks" aria-selected="false"><?php esc_html_e( 'Looks', 'atelier-irisee-master-plugin' ); ?></button>
					</div>

					<section class="aimp-ds-pane" data-pane="parts">
						<input type="search" class="aimp-ds-search" placeholder="<?php esc_attr_e( 'Search a part…', 'atelier-irisee-master-plugin' ); ?>">
						<div class="aimp-ds-list"></div>

						<div class="aimp-ds-edit" hidden>
							<div class="aimp-ds-edit-head">
								<strong class="aimp-ds-edit-name"></strong>
								<button type="button" class="button-link aimp-ds-reset-one"><?php esc_html_e( 'Reset this part', 'atelier-irisee-master-plugin' ); ?></button>
							</div>
							<?php foreach ( array( '' => $computer, 'p' => $phone ) as $prefix => $device ) : ?>
								<fieldset class="aimp-ds-device">
									<legend><?php echo esc_html( $device ); ?></legend>
									<?php
									self::number( $prefix . 'x', __( 'Left / right', 'atelier-irisee-master-plugin' ), -200, 200, 'px' );
									self::number( $prefix . 'y', __( 'Up / down', 'atelier-irisee-master-plugin' ), -200, 200, 'px' );
									self::number( $prefix . 'zoom', __( 'Size', 'atelier-irisee-master-plugin' ), 40, 250, '%' );
									self::number( $prefix . 'mt', __( 'Space above', 'atelier-irisee-master-plugin' ), -150, 300, 'px' );
									self::number( $prefix . 'mb', __( 'Space below', 'atelier-irisee-master-plugin' ), -150, 300, 'px' );
									?>
									<label class="aimp-ds-check"><input type="checkbox" data-key="<?php echo esc_attr( $prefix . 'hide' ); ?>"> <?php esc_html_e( 'Hide', 'atelier-irisee-master-plugin' ); ?></label>
								</fieldset>
							<?php endforeach; ?>
							<fieldset class="aimp-ds-device">
								<legend><?php esc_html_e( 'Colours and corners (all screens)', 'atelier-irisee-master-plugin' ); ?></legend>
								<?php foreach ( array( 'color' => __( 'Text colour', 'atelier-irisee-master-plugin' ), 'bg' => __( 'Background', 'atelier-irisee-master-plugin' ), 'border' => __( 'Border colour', 'atelier-irisee-master-plugin' ) ) as $key => $label ) : ?>
									<label class="aimp-ds-color">
										<span><?php echo esc_html( $label ); ?></span>
										<input type="color" data-key="<?php echo esc_attr( $key ); ?>">
										<button type="button" class="button-link" data-clear="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Standard', 'atelier-irisee-master-plugin' ); ?></button>
									</label>
								<?php endforeach; ?>
								<?php self::number( 'radius', __( 'Rounded corners', 'atelier-irisee-master-plugin' ), 0, 200, 'px' ); ?>
							</fieldset>
							<fieldset class="aimp-ds-device">
								<legend><?php esc_html_e( 'Picture (all screens)', 'atelier-irisee-master-plugin' ); ?></legend>
								<div class="aimp-ds-image">
									<img class="aimp-ds-image-preview" alt="" hidden>
									<button type="button" class="button aimp-ds-image-choose"><?php esc_html_e( 'Other picture', 'atelier-irisee-master-plugin' ); ?></button>
									<button type="button" class="button-link" data-clear="img"><?php esc_html_e( 'Standard', 'atelier-irisee-master-plugin' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'Replaces the pictures in this part. The focal point is the spot that stays in view when a picture is cut off.', 'atelier-irisee-master-plugin' ); ?></p>
								<?php
								self::number( 'fx', __( 'Focal point left → right', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
								self::number( 'fy', __( 'Focal point top → bottom', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
								?>
							</fieldset>
							<fieldset class="aimp-ds-device">
								<legend><?php esc_html_e( 'Animations (all screens)', 'atelier-irisee-master-plugin' ); ?></legend>
								<label class="aimp-ds-check"><input type="checkbox" data-key="noanim"> <?php esc_html_e( 'No animations in this part', 'atelier-irisee-master-plugin' ); ?></label>
								<?php self::number( 'speed', __( 'Speed', 'atelier-irisee-master-plugin' ), 25, 400, '%' ); ?>
							</fieldset>
						</div>
					</section>

					<section class="aimp-ds-pane" data-pane="texts" hidden>
						<p class="description"><?php esc_html_e( 'Every text of the shop, with your own wording per language. Empty = the standard text. The preview shows your texts after a moment.', 'atelier-irisee-master-plugin' ); ?></p>
						<input type="search" class="aimp-ds-text-search" placeholder="<?php esc_attr_e( 'Search a text (in any language)…', 'atelier-irisee-master-plugin' ); ?>">
						<label class="aimp-ds-check"><input type="checkbox" class="aimp-ds-text-own"> <?php esc_html_e( 'Only my own texts', 'atelier-irisee-master-plugin' ); ?></label>
						<p class="aimp-ds-text-count"></p>
						<div class="aimp-ds-texts"></div>
					</section>

					<section class="aimp-ds-pane" data-pane="looks" hidden>
						<p class="description"><?php esc_html_e( 'Save the draft (parts and texts) as a look, e.g. "Christmas" or "Sale". Load it into the draft later, or plan it: between two dates visitors see that look, and afterwards the published look again.', 'atelier-irisee-master-plugin' ); ?></p>
						<p class="aimp-ds-look-new">
							<input type="text" class="regular-text aimp-ds-look-name" maxlength="60" placeholder="<?php esc_attr_e( 'Name of the look', 'atelier-irisee-master-plugin' ); ?>">
							<button type="button" class="button aimp-ds-look-save"><?php esc_html_e( 'Save the draft as a look', 'atelier-irisee-master-plugin' ); ?></button>
						</p>
						<ul class="aimp-ds-looks"></ul>
					</section>

					<details class="aimp-ds-more">
						<summary><?php esc_html_e( 'History, backup and reset', 'atelier-irisee-master-plugin' ); ?></summary>
						<h3><?php esc_html_e( 'Earlier published looks', 'atelier-irisee-master-plugin' ); ?></h3>
						<ul class="aimp-ds-history"></ul>
						<h3><?php esc_html_e( 'Backup', 'atelier-irisee-master-plugin' ); ?></h3>
						<p>
							<button type="button" class="button aimp-ds-export"><?php esc_html_e( 'Download the draft', 'atelier-irisee-master-plugin' ); ?></button>
							<label class="button aimp-ds-import"><?php esc_html_e( 'Load a file into the draft', 'atelier-irisee-master-plugin' ); ?> <input type="file" accept="application/json,.json" hidden></label>
						</p>
						<h3><?php esc_html_e( 'Reset', 'atelier-irisee-master-plugin' ); ?></h3>
						<p><button type="button" class="button aimp-ds-reset-all"><?php esc_html_e( 'Reset every part in the draft', 'atelier-irisee-master-plugin' ); ?></button></p>
					</details>
				</aside>
				<div class="aimp-ds-stage">
					<div class="aimp-ds-frame-wrap"><iframe class="aimp-ds-frame" title="<?php esc_attr_e( 'Preview', 'atelier-irisee-master-plugin' ); ?>"></iframe></div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ ajax */

	private static function check() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) ), 403 );
		}
	}

	/**
	 * A posted JSON field.
	 *
	 * @param string $field Field.
	 * @return mixed
	 */
	private static function posted_json( $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked in check(); cleaned by the caller.
		return isset( $_POST[ $field ] ) ? json_decode( wp_unslash( $_POST[ $field ] ), true ) : null;
	}

	/**
	 * Everything the studio shows (also sent after every change).
	 *
	 * @return array
	 */
	private static function state() {
		$draft  = self::draft();
		$images = array();
		foreach ( array( $draft, self::live() ) as $styles ) {
			foreach ( $styles as $row ) {
				if ( ! empty( $row['img'] ) ) {
					$images[ (int) $row['img'] ] = (string) wp_get_attachment_image_url( (int) $row['img'], 'large' );
				}
			}
		}
		$looks = array();
		$now   = time();
		foreach ( self::looks() as $id => $look ) {
			$looks[] = array(
				'id'    => $id,
				'name'  => $look['name'],
				'count' => count( (array) $look['styles'] ) + count( (array) $look['texts'] ),
				'from'  => ! empty( $look['from'] ) ? wp_date( 'Y-m-d', (int) $look['from'] ) : '',
				'to'    => ! empty( $look['to'] ) ? wp_date( 'Y-m-d', (int) $look['to'] - 1 ) : '',
				'on'    => ! empty( $look['from'] ) && $look['from'] <= $now && $now < $look['to'],
			);
		}
		return array(
			'draft'      => (object) $draft,
			'live'       => (object) self::live(),
			'draftTexts' => (object) self::draft_texts(),
			'liveTexts'  => (object) self::live_texts(),
			'images'     => (object) $images,
			'history'    => self::history_list(),
			'looks'      => $looks,
		);
	}

	/**
	 * The history for the studio: [ index, when, who, count ].
	 *
	 * @return array
	 */
	private static function history_list() {
		$list = array();
		foreach ( self::history() as $index => $item ) {
			$user   = get_userdata( (int) $item['user'] );
			$list[] = array(
				'index' => $index,
				'when'  => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $item['time'] ),
				'who'   => $user ? $user->display_name : '',
				'count' => count( (array) $item['styles'] ) + count( isset( $item['texts'] ) ? (array) $item['texts'] : array() ),
			);
		}
		return $list;
	}

	/** The draft, saved while editing (the parts, the texts, or both). */
	public static function ajax_save() {
		self::check();
		$changes = array( 'draft_time' => time() );
		$styles  = self::posted_json( 'styles' );
		$texts   = self::posted_json( 'texts' );
		if ( null !== $styles ) {
			$changes['draft'] = self::clean( $styles );
		}
		if ( null !== $texts ) {
			$changes['draft_texts'] = self::clean_texts( $texts );
		}
		self::store( $changes );
		wp_send_json_success( self::state() );
	}

	/** The draft becomes the live look; the look it replaces goes into the history. */
	public static function ajax_publish() {
		self::check();
		$styles  = self::posted_json( 'styles' );
		$texts   = self::posted_json( 'texts' );
		$history = self::history();
		array_unshift(
			$history,
			array(
				'time'   => time(),
				'user'   => get_current_user_id(),
				'styles' => self::live(),
				'texts'  => self::live_texts(),
			)
		);
		update_option( self::HISTORY, array_slice( $history, 0, self::HISTORY_MAX ), false );
		self::store(
			array(
				'live'        => null !== $styles ? self::clean( $styles ) : self::draft(),
				'live_texts'  => null !== $texts ? self::clean_texts( $texts ) : self::draft_texts(),
				'draft'       => null,
				'draft_texts' => null,
				'live_time'   => time(),
				'draft_time'  => 0,
			)
		);
		// Cached pages carry the old style block and texts.
		self::purge();
		wp_send_json_success( self::state() );
	}

	/** The draft goes back to the live look. */
	public static function ajax_discard() {
		self::check();
		self::store(
			array(
				'draft'       => null,
				'draft_texts' => null,
				'draft_time'  => 0,
			)
		);
		wp_send_json_success( self::state() );
	}

	/** An earlier look goes into the draft. */
	public static function ajax_restore() {
		self::check();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
		$index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$history = self::history();
		if ( ! isset( $history[ $index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This look no longer exists.', 'atelier-irisee-master-plugin' ) ) );
		}
		self::store(
			array(
				'draft'       => self::clean( $history[ $index ]['styles'] ),
				'draft_texts' => self::clean_texts( isset( $history[ $index ]['texts'] ) ? $history[ $index ]['texts'] : array() ),
				'draft_time'  => time(),
			)
		);
		wp_send_json_success( self::state() );
	}

	/** A downloaded file goes into the draft. */
	public static function ajax_import() {
		self::check();
		$texts = self::posted_json( 'texts' );
		self::store(
			array(
				'draft'       => self::clean( self::posted_json( 'styles' ) ),
				'draft_texts' => self::clean_texts( null !== $texts ? $texts : array() ),
				'draft_time'  => time(),
			)
		);
		wp_send_json_success( self::state() );
	}

	/** All texts of the plugin: [ original, nl, fr ] (the studio's Texts tab loads them when it is opened). */
	public static function ajax_texts() {
		self::check();
		$nl   = self::dictionary( 'nl' );
		$fr   = self::dictionary( 'fr' );
		$list = array();
		foreach ( $nl as $original => $dutch ) {
			$list[] = array( $original, $dutch, isset( $fr[ $original ] ) ? $fr[ $original ] : '' );
		}
		wp_send_json_success( $list );
	}

	/** The draft is saved as a named look. */
	public static function ajax_look_save() {
		self::check();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Give the look a name.', 'atelier-irisee-master-plugin' ) ) );
		}
		$looks = self::looks();
		$looks[ 'l' . time() . wp_rand( 10, 99 ) ] = array(
			'name'   => function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 60 ) : substr( $name, 0, 60 ),
			'styles' => self::draft(),
			'texts'  => self::draft_texts(),
			'from'   => 0,
			'to'     => 0,
			'time'   => time(),
		);
		update_option( self::LOOKS, array_slice( $looks, -30, null, true ), false );
		wp_send_json_success( self::state() );
	}

	private static function posted_look() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
		$id    = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$looks = self::looks();
		if ( ! isset( $looks[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This look no longer exists.', 'atelier-irisee-master-plugin' ) ) );
		}
		return array( $id, $looks );
	}

	/** A saved look goes into the draft. */
	public static function ajax_look_load() {
		self::check();
		list( $id, $looks ) = self::posted_look();
		self::store(
			array(
				'draft'       => self::clean( $looks[ $id ]['styles'] ),
				'draft_texts' => self::clean_texts( $looks[ $id ]['texts'] ),
				'draft_time'  => time(),
			)
		);
		wp_send_json_success( self::state() );
	}

	public static function ajax_look_delete() {
		self::check();
		list( $id, $looks ) = self::posted_look();
		$was_on = ! empty( $looks[ $id ]['from'] ) && $looks[ $id ]['from'] <= time() && time() < $looks[ $id ]['to'];
		unset( $looks[ $id ] );
		update_option( self::LOOKS, $looks, false );
		if ( $was_on ) {
			self::purge();
		}
		wp_send_json_success( self::state() );
	}

	/**
	 * Plan a look between two dates (from the start of the first day to the end of the last day, in the shop's time
	 * zone), or take it off the plan (empty dates).
	 */
	public static function ajax_look_plan() {
		self::check();
		list( $id, $looks ) = self::posted_look();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in check().
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		// phpcs:enable
		$zone  = wp_timezone();
		$start = 0;
		$end   = 0;
		if ( '' !== $from || '' !== $to ) {
			$start_date = DateTime::createFromFormat( '!Y-m-d', $from, $zone );
			$end_date   = DateTime::createFromFormat( '!Y-m-d', $to, $zone );
			if ( ! $start_date || ! $end_date || $end_date < $start_date ) {
				wp_send_json_error( array( 'message' => __( 'Choose a start and an end date (the end after the start).', 'atelier-irisee-master-plugin' ) ) );
			}
			$start = $start_date->getTimestamp();
			$end   = $end_date->modify( '+1 day' )->getTimestamp();
			// Cached pages get the planned look when it starts and the published look when it ends.
			foreach ( array( $start, $end ) as $moment ) {
				if ( $moment > time() ) {
					wp_schedule_single_event( $moment + 5, 'aimp_design_switch' );
				}
			}
		}
		$looks[ $id ]['from'] = $start;
		$looks[ $id ]['to']   = $end;
		update_option( self::LOOKS, $looks, false );
		self::purge();
		wp_send_json_success( self::state() );
	}
}
