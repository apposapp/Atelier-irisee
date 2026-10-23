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
			'header-lang'     => array( $header, __( 'Language switch', 'atelier-irisee-master-plugin' ), '.aimp-header-lang' ),
			'header-search'   => array( $header, __( 'Search', 'atelier-irisee-master-plugin' ), '.aimp-header-search' ),
			'hero'            => array( $hero, __( 'Hero (whole)', 'atelier-irisee-master-plugin' ), '.aimp-hero' ),
			'hero-logo'       => array( $hero, __( 'Hero logo', 'atelier-irisee-master-plugin' ), '.aimp-hero-brand' ),
			'hero-title'      => array( $hero, __( 'Hero title', 'atelier-irisee-master-plugin' ), '.aimp-hero-title' ),
			'hero-text'       => array( $hero, __( 'Hero text', 'atelier-irisee-master-plugin' ), '.aimp-hero-text' ),
			'hero-buttons'    => array( $hero, __( 'Hero buttons', 'atelier-irisee-master-plugin' ), '.aimp-hero-buttons' ),
			'hero-arrow'      => array( $hero, __( 'Hero arrow', 'atelier-irisee-master-plugin' ), '.aimp-hero-arrow' ),
			'hero-seal'       => array( $hero, __( 'Hero seal (round logo)', 'atelier-irisee-master-plugin' ), '.aimp-hero-seal' ),
			'kit-steps'       => array( $steps, __( 'Kit steps bar (whole)', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps' ),
			'kit-steps-title' => array( $steps, __( 'Kit steps title', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps-title' ),
			'kit-steps-list'  => array( $steps, __( 'Kit steps cards', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps-list' ),
			'kit-step'        => array( $steps, __( 'Kit steps card (each)', 'atelier-irisee-master-plugin' ), '.aimp-kit-step' ),
			'kit-steps-button' => array( $steps, __( 'Kit steps button', 'atelier-irisee-master-plugin' ), '.aimp-kit-steps-button' ),
			'shop'            => array( $shop, __( 'Shop (product list)', 'atelier-irisee-master-plugin' ), '.aimp-shop:not(.aimp-overview)' ),
			'overview'        => array( $shop, __( 'Overview cards (whole)', 'atelier-irisee-master-plugin' ), '.aimp-overview-grid' ),
			'overview-card'   => array( $shop, __( 'Overview cards (each)', 'atelier-irisee-master-plugin' ), '.aimp-overview-card' ),
			'overview-hero'   => array( $shop, __( 'Large overview card text', 'atelier-irisee-master-plugin' ), '.aimp-overview-hero-text' ),
			'card-name'       => array( $shop, __( 'Product names on cards', 'atelier-irisee-master-plugin' ), '.aimp-card-name' ),
			'card-price'      => array( $shop, __( 'Prices on cards', 'atelier-irisee-master-plugin' ), '.aimp-card-price' ),
			'mosaic'          => array( $shop, __( 'Mosaic', 'atelier-irisee-master-plugin' ), '.aimp-mosaic-grid' ),
			'product-page'    => array( $product, __( 'Product page (whole)', 'atelier-irisee-master-plugin' ), '.aimp-configurator.aimp-product' ),
			'product-title'   => array( $product, __( 'Product title', 'atelier-irisee-master-plugin' ), '.aimp-product-title' ),
			'product-brand'   => array( $product, __( 'Brand', 'atelier-irisee-master-plugin' ), '.aimp-product-brand' ),
			'product-subtitle' => array( $product, __( 'Product subtitle', 'atelier-irisee-master-plugin' ), '.aimp-product-subtitle' ),
			'product-gallery' => array( $product, __( 'Product pictures', 'atelier-irisee-master-plugin' ), '.aimp-gallery' ),
			'product-buy'     => array( $product, __( 'Buy bar', 'atelier-irisee-master-plugin' ), '.aimp-buy-bar' ),
			'product-price'   => array( $product, __( 'Product price', 'atelier-irisee-master-plugin' ), '.aimp-buy-price' ),
			'product-desc'    => array( $product, __( 'Description', 'atelier-irisee-master-plugin' ), '.aimp-description' ),
			'product-specs'   => array( $product, __( 'Specifications', 'atelier-irisee-master-plugin' ), '.aimp-info-list' ),
			'product-recommend' => array( $product, __( 'Recommended products', 'atelier-irisee-master-plugin' ), '.aimp-recommended' ),
			'customizer'      => array( $config, __( 'Customizer (whole)', 'atelier-irisee-master-plugin' ), '.aimp-configurator[data-aimp-configurator]' ),
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
		// Which settings a part has: every part can be moved, sized, spaced, hidden, coloured and calmed down; boxes
		// also get rounded corners, and parts with pictures a picture and focal point.
		$text    = array( 'move', 'size', 'space', 'hide', 'colors', 'anim' );
		$box     = array_merge( $text, array( 'radius' ) );
		$picture = array_merge( $box, array( 'image' ) );
		$texts   = array( 'header-menu-bar', 'hero-title', 'hero-text', 'kit-steps-title', 'overview-hero', 'card-name', 'card-price', 'product-title', 'product-brand', 'product-subtitle', 'product-price', 'config-title', 'account-title', 'account-hello', 'footer-title', 'footer-bottom', 'hero-arrow' );
		$images  = array( 'header-logo', 'hero-seal', 'overview', 'overview-card', 'mosaic', 'product-gallery', 'product-recommend', 'kit-steps-list', 'footer-payments', 'footer-socials', 'cart-items' );
		$elements = array();
		foreach ( $list as $id => $row ) {
			$elements[ $id ] = array(
				'group'    => $row[0],
				'label'    => $row[1],
				'selector' => $row[2],
				'controls' => in_array( $id, $images, true ) ? $picture : ( in_array( $id, $texts, true ) ? $text : $box ),
			);
		}
		// Parts with settings of their own (phase 3: header, menu, tab bar, search, hero).
		foreach ( array(
			'header'          => array( 'header_opts' ),
			'header-icons'    => array( 'icons_opts' ),
			'header-menu-bar' => array( 'menubar_opts' ),
			'side-menu'       => array( 'side_opts' ),
			'info-bar'        => array( 'info_opts' ),
			'tabbar'          => array( 'tabbar_opts' ),
			'header-lang'     => array( 'lang_opts' ),
			'header-search'   => array( 'search_opts' ),
			'hero-arrow'      => array( 'arrow_opts' ),
			'kit-steps'       => array( 'ks_opts' ),
			'shop'            => array( 'shop_grid', 'shop_cards', 'shop_tools', 'shop_badges', 'shop_cats' ),
			'overview'        => array( 'overview_opts' ),
			'mosaic'          => array( 'mosaic_opts' ),
		) as $id => $more ) {
			if ( isset( $elements[ $id ] ) ) {
				$elements[ $id ]['controls'] = array_merge( $elements[ $id ]['controls'], $more );
			}
		}
		// The hero: its own pictures, video, slides, filter, place of the text and buttons.
		if ( isset( $elements['hero'] ) ) {
			$elements['hero']['controls'] = array( 'hero_media', 'hero_tint', 'hero_layout', 'hero_buttons', 'space', 'hide', 'anim' );
		}
		// The product page as a whole (phase 6): the order of its blocks, the pictures, the buy bar, the information.
		if ( isset( $elements['product-page'] ) ) {
			$elements['product-page']['controls'] = array( 'pp_blocks', 'pp_gallery', 'pp_buy', 'pp_info', 'cz_steps', 'cz_flow', 'cz_bar', 'cz_summary' );
		}
		// The customizer as a whole (phase 7): steps, cards, the haberdashery, the bar on phones, the summary.
		if ( isset( $elements['customizer'] ) ) {
			$elements['customizer']['controls'] = array( 'cz_steps', 'cz_cards', 'cz_flow', 'cz_bar', 'cz_summary' );
		}
		// The overall look of the site: no part of the page to click, chosen from the list.
		$site = __( 'Whole site', 'atelier-irisee-master-plugin' );
		foreach ( array(
			'site-colors'  => array( __( 'Brand colours', 'atelier-irisee-master-plugin' ), array( 'palette' ) ),
			'site-fonts'   => array( __( 'Fonts and text sizes', 'atelier-irisee-master-plugin' ), array( 'fonts', 'textsize', 'headings', 'caps' ) ),
			'site-buttons' => array( __( 'Buttons', 'atelier-irisee-master-plugin' ), array( 'buttons', 'hover' ) ),
			'site-cards'   => array( __( 'Cards and shadows', 'atelier-irisee-master-plugin' ), array( 'cards', 'shadows' ) ),
			'site-page'    => array( __( 'Page background, width and lines', 'atelier-irisee-master-plugin' ), array( 'background', 'width', 'lines' ) ),
			'site-effects' => array( __( 'Effects, icons and browser icon', 'atelier-irisee-master-plugin' ), array( 'effects', 'icons', 'loading', 'focus', 'favicon' ) ),
		) as $id => $row ) {
			$elements[ $id ] = array(
				'group'    => $site,
				'label'    => $row[0],
				'selector' => '',
				'controls' => $row[1],
			);
		}
		return apply_filters( 'aimp_design_elements', $elements );
	}

	/**
	 * The settings per group: key => rule. A rule is [ min, max, standard ] for numbers, 'bool', 'color', 'image',
	 * 'font' (a Google Fonts family name) or [ 'select', choices ] (the first choice is the standard). A "p" in front
	 * of a key = on phones.
	 *
	 * @return array
	 */
	public static function groups() {
		return array(
			'move'       => array(
				'x'  => array( -200, 200, 0 ),
				'y'  => array( -200, 200, 0 ),
				'px' => array( -200, 200, 0 ),
				'py' => array( -200, 200, 0 ),
			),
			'size'       => array(
				'zoom'  => array( 40, 250, 100 ),
				'pzoom' => array( 40, 250, 100 ),
			),
			'space'      => array(
				'mt'  => array( -150, 300, 0 ),
				'mb'  => array( -150, 300, 0 ),
				'pmt' => array( -150, 300, 0 ),
				'pmb' => array( -150, 300, 0 ),
			),
			'hide'       => array(
				'hide'  => 'bool',
				'phide' => 'bool',
			),
			'colors'     => array(
				'color'  => 'color',
				'bg'     => 'color',
				'border' => 'color',
			),
			'radius'     => array( 'radius' => array( 0, 200, 0 ) ),
			'image'      => array(
				'img' => 'image',
				'fx'  => array( 0, 100, 50 ),
				'fy'  => array( 0, 100, 50 ),
			),
			'anim'       => array(
				'noanim' => 'bool',
				'speed'  => array( 25, 400, 100 ),
			),
			// The whole site.
			'palette'    => array(
				'gold'      => 'color',
				'gold_text' => 'color',
				'brown'     => 'color',
				'success'   => 'color',
				'error'     => 'color',
			),
			'fonts'      => array(
				'head_font'   => array( 'select', array( '', 'trajan', 'serif', 'sans', 'google' ) ),
				'head_google' => 'font',
				'body_font'   => array( 'select', array( '', 'trajan', 'serif', 'sans', 'google' ) ),
				'body_google' => 'font',
			),
			'textsize'   => array(
				'fs'  => array( 0, 24, 0 ),
				'pfs' => array( 0, 24, 0 ),
			),
			'headings'   => array(
				'h1'  => array( 0, 80, 0 ),
				'h2'  => array( 0, 64, 0 ),
				'h3'  => array( 0, 48, 0 ),
				'ph1' => array( 0, 64, 0 ),
				'ph2' => array( 0, 48, 0 ),
				'ph3' => array( 0, 40, 0 ),
			),
			'caps'       => array(
				'nocaps'  => 'bool',
				'spacing' => array( 0, 30, -1 ),
			),
			'buttons'    => array(
				'b_radius' => array( 0, 100, -1 ),
				'b_style'  => array( 'select', array( '', 'single', 'none' ) ),
				'b_width'  => array( 0, 10, -1 ),
				'b_fill'   => 'bool',
				'b_pad'    => array( 50, 200, 100 ),
			),
			'hover'      => array( 'hover' => array( 'select', array( '', 'lift', 'underline', 'none' ) ) ),
			'cards'      => array(
				'c_radius' => array( 0, 60, -1 ),
				'c_style'  => array( 'select', array( '', 'single', 'none' ) ),
				'c_width'  => array( 0, 10, -1 ),
			),
			'shadows'    => array( 'shadow' => array( 'select', array( '', 'soft', 'strong' ) ) ),
			'background' => array(
				'page_bg'    => 'color',
				'page_bg2'   => 'color',
				'page_img'   => 'image',
				'page_fixed' => 'bool',
			),
			'width'      => array(
				'max_width' => array( 0, 2000, 0 ),
				'gutter'    => array( 0, 48, 0 ),
			),
			'lines'      => array( 'line' => array( 'select', array( '', 'dashed', 'dotted' ) ) ),
			'effects'    => array( 'nosparkle' => 'bool' ),
			'icons'      => array( 'icon_line' => array( 'select', array( '', 'thin', 'bold' ) ) ),
			'loading'    => array( 'loader' => array( 'select', array( '', 'ring', 'dots' ) ) ),
			'focus'      => array(
				'focus_color' => 'color',
				'focus_width' => array( 0, 6, -1 ),
			),
			'favicon'    => array( 'favicon' => 'image' ),
			// Header, menu, tab bar, search (phase 3).
			'header_opts' => array(
				'nosticky' => 'bool',
				'noshrink' => 'bool',
				'nolabels' => 'bool',
				'badge_bg' => 'color',
				'badge_fg' => 'color',
			),
			'icons_opts'  => self::icon_fields(),
			'menubar_opts' => array( 'mb_align' => array( 'select', array( '', 'center', 'right' ) ) ),
			'side_opts'   => array(
				'sm_width'    => array( 0, 640, 0 ),
				'sm_bg'       => 'color',
				'sm_text'     => 'color',
				'sm_size'     => array( 0, 28, 0 ),
				'sm_open'     => array( 'select', array( '', 'none', 'all' ) ),
				'sm_nohab'    => 'bool',
				'sm_fabrics'  => 'bool',
				'sm_patterns' => 'bool',
				'sm_contact'  => 'longtext',
				'sm_socials'  => 'bool',
				'sm_img'      => 'image',
			),
			'info_opts'   => array(
				'ib_speed' => array( 0, 20, 0 ),
				'ib_where' => array( 'select', array( '', 'home', 'nocheckout' ) ),
				'ib_links' => 'longtext',
			),
			'tabbar_opts' => array(
				'tb_nohome'   => 'bool',
				'tb_noshop'   => 'bool',
				'tb_nokit'    => 'bool',
				'tb_search'   => 'bool',
				'tb_fav'      => 'bool',
				'tb_cart'     => 'bool',
				'tb_account'  => 'bool',
				'tb_nolabels' => 'bool',
			),
			'lang_opts'   => array( 'lang_style' => array( 'select', array( '', 'code', 'name' ) ) ),
			'search_opts' => array(
				's_count'   => array( 0, 30, 0 ),
				's_nofab'   => 'bool',
				's_nopat'   => 'bool',
				's_nohab'   => 'bool',
				's_noother' => 'bool',
			),
			// The hero (phase 3).
			'hero_media'  => array(
				'h_img'      => 'image',
				'h_pimg'     => 'image',
				'h_fx'       => array( 0, 100, -1 ),
				'h_fy'       => array( 0, 100, -1 ),
				'h_video'    => 'video',
				'h_slides'   => 'images',
				'h_interval' => array( 0, 20, 0 ),
			),
			'hero_tint'   => array(
				'h_tint'     => 'color',
				'h_strength' => array( 0, 90, -1 ),
			),
			'hero_layout' => array(
				'h_height' => array( 'select', array( '', 'large', 'medium' ) ),
				'h_x'      => array( 'select', array( '', 'center', 'right' ) ),
				'h_y'      => array( 'select', array( '', 'top', 'bottom' ) ),
				'ph_x'     => array( 'select', array( '', 'left', 'right' ) ),
				'ph_y'     => array( 'select', array( '', 'top', 'bottom' ) ),
			),
			'hero_buttons' => array(
				'hb1_off'  => 'bool',
				'hb1_url'  => 'url',
				'hb2_off'  => 'bool',
				'hb2_url'  => 'url',
				'hb3_text' => 'text',
				'hb3_url'  => 'url',
			),
			'arrow_opts'  => array(
				'ha_target' => array( 'select', array( '', 'next', 'anchor' ) ),
				'ha_anchor' => 'text',
			),
			// The kit steps bar and the mosaic (phase 4).
			'ks_opts'     => self::kit_step_fields(),
			// Shop pages (phase 5).
			'shop_grid'   => array(
				'cols'  => array( 0, 6, 0 ),
				'tcols' => array( 0, 6, 0 ),
				'pcols' => array( 0, 3, 0 ),
			),
			'shop_cards'  => array(
				'noprice' => 'bool',
				'nounit'  => 'bool',
				'nosub'   => 'bool',
				'brand'   => 'bool',
				'nofav'   => 'bool',
				'ratio'   => array( 'select', array( '', 'square', 'portrait', 'landscape' ) ),
				'zoom_in' => 'bool',
			),
			'shop_tools'  => array(
				'sort_default'   => array( 'select', array( '', 'newest', 'price_asc', 'price_desc', 'name_asc' ) ),
				'no_sort_name'   => 'bool',
				'no_sort_price'  => 'bool',
				'no_sort_newest' => 'bool',
				'f_nosearch'     => 'bool',
				'f_nocat'        => 'bool',
				'f_noprice'      => 'bool',
				'f_noattrs'      => 'bool',
				'f_nostock'      => 'bool',
				'f_closed'       => 'bool',
				'f_hidden'       => 'bool',
				'paging'         => array( 'select', array( '', 'more', 'infinite' ) ),
				'noquick'        => 'bool',
			),
			'shop_badges' => array(
				'b_new'    => array( 0, 90, 0 ),
				'b_sale'   => 'bool',
				'b_low'    => 'bool',
				'badge_bg' => 'color',
				'badge_fg' => 'color',
			),
			'shop_cats'   => array(
				'cat_banner' => 'bool',
				'cat_text'   => 'bool',
			),
			'overview_opts' => self::overview_fields(),
			'mosaic_opts' => array(
				'mos_source'    => array( 'select', array( '', 'newest', 'sale', 'random' ) ),
				'mos_layout'    => array( 'select', array( '', 'grid', 'row' ) ),
				'mos_gap'       => array( 0, 40, -1 ),
				'mos_nocaption' => 'bool',
				'mos_noprice'   => 'bool',
				'mos_hover'     => array( 'select', array( '', 'none', 'lift' ) ),
			),
			// The product page (phase 6).
			'pp_blocks'   => self::product_block_fields(),
			'pp_gallery'  => array(
				'g_thumbs' => array( 'select', array( '', 'left', 'none' ) ),
				'g_ratio'  => array( 'select', array( '', 'square', 'portrait', 'landscape' ) ),
				'g_split'  => array( 'select', array( '', 'wide', 'narrow' ) ),
				'g_right'  => 'bool',
				'g_nozoom' => 'bool',
			),
			'pp_buy'      => array(
				'pp_sticky'  => array( 'select', array( '', 'all', 'none' ) ),
				'pp_notrust' => 'bool',
				'pp_nolow'   => 'bool',
				'pp_noalert' => 'bool',
				'pp_share'   => 'bool',
			),
			'pp_info'     => self::product_info_fields(),
			// The customizer (phase 7).
			'cz_steps'    => array(
				'cz_progress'      => array( 'select', array( '', 'bar', 'none' ) ),
				'cz_nonum'         => 'bool',
				'cz_intro_pattern' => 'text',
				'cz_intro_fabric'  => 'text',
				'cz_intro_notions' => 'text',
				'cz_intro_summary' => 'text',
				'cz_nohowto'       => 'bool',
			),
			'cz_cards'    => array(
				'cz_cols'    => array( 0, 6, 0 ),
				'cz_pcols'   => array( 0, 3, 0 ),
				'cz_noprice' => 'bool',
				'cz_nosub'   => 'bool',
				'cz_nofav'   => 'bool',
				'cz_ratio'   => array( 'select', array( '', 'square', 'portrait', 'landscape' ) ),
			),
			'cz_flow'     => array(
				'cz_norder'      => array( 'order', array_keys( self::notion_labels() ) ),
				'cz_nonotions'   => 'bool',
				'cz_nosizechart' => 'bool',
				'cz_nomeasure'   => 'bool',
				'cz_sheet'       => array( 'select', array( '', 'full' ) ),
			),
			'cz_bar'      => array(
				'cz_bar_bg'     => 'color',
				'cz_bar_fg'     => 'color',
				'cz_nosparkle'  => 'bool',
			),
			'cz_summary'  => array(
				'cz_nosave'   => 'bool',
				'cz_discount' => array( 'select', array( '', 'total', 'badge' ) ),
				'cz_note'     => 'text',
			),
		);
	}

	/**
	 * The header icons: [ key => label ] (their CSS class is aimp-header-link--{key}, the language switch .aimp-header-lang).
	 *
	 * @return array
	 */
	public static function header_icons() {
		return array(
			'search'  => __( 'Search', 'atelier-irisee-master-plugin' ),
			'shop'    => __( 'Shop', 'atelier-irisee-master-plugin' ),
			'fav'     => __( 'Favourites', 'atelier-irisee-master-plugin' ),
			'cart'    => __( 'Cart', 'atelier-irisee-master-plugin' ),
			'account' => __( 'Account', 'atelier-irisee-master-plugin' ),
			'lang'    => __( 'Language', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * Per header icon: hidden on computers / phones, and its place (0 = standard).
	 *
	 * @return array
	 */
	private static function icon_fields() {
		$fields = array();
		foreach ( array( 'search', 'shop', 'fav', 'cart', 'account', 'lang' ) as $icon ) {
			$fields[ 'ic_hide_' . $icon ]  = 'bool';
			$fields[ 'ic_phide_' . $icon ] = 'bool';
			$fields[ 'ic_order_' . $icon ] = array( 0, 6, 0 );
		}
		return $fields;
	}

	/**
	 * The overview cards (All products page): which section is the large card, columns, and per section its place
	 * and a line under its name.
	 *
	 * @return array
	 */
	private static function overview_fields() {
		$fields = array(
			'ov_hero'  => array( 'select', array( '', 'none', 'fabrics', 'patterns', 'haberdashery', 'giftcards' ) ),
			'ov_cols'  => array( 0, 6, 0 ),
			'ov_tcols' => array( 0, 4, 0 ),
			'ov_pcols' => array( 0, 3, 0 ),
		);
		foreach ( array( 'configurator', 'fabrics', 'patterns', 'haberdashery', 'giftcards' ) as $section ) {
			$fields[ 'ov_order_' . $section ] = array( 0, 6, 0 );
			$fields[ 'ov_sub_' . $section ]   = 'text';
		}
		return $fields;
	}

	/**
	 * The haberdashery steps of the customizer: type => label (in their standard order).
	 *
	 * @return array
	 */
	public static function notion_labels() {
		static $labels = null;
		if ( null !== $labels ) {
			return $labels;
		}
		$labels = array();
		if ( class_exists( 'AIMP_Catalog' ) && method_exists( 'AIMP_Catalog', 'notion_types' ) ) {
			$fields = AIMP_Catalog::material_fields();
			foreach ( AIMP_Catalog::notion_types() as $type => $info ) {
				$labels[ $type ] = isset( $fields[ $info['material'] ]['label'] ) ? $fields[ $info['material'] ]['label'] : $type;
			}
		}
		return $labels;
	}

	/**
	 * The customizer's settings for configurator.js (texts as "NL | FR | EN": the customizer switches language itself).
	 *
	 * @return array
	 */
	public static function customizer() {
		$out = array();
		foreach ( array( 'cz_progress', 'cz_nonum', 'cz_nohowto', 'cz_nosparkle', 'cz_nosave', 'cz_discount' ) as $key ) {
			$value = self::opt( 'customizer', $key, '' );
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}
		foreach ( array( 'cz_intro_pattern', 'cz_intro_fabric', 'cz_intro_notions', 'cz_intro_summary', 'cz_note' ) as $key ) {
			$value = (string) self::opt( 'customizer', $key, '' );
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * The haberdashery steps in the order chosen in the Design studio (none when the step is switched off).
	 *
	 * @param array $steps [ [ type, … ] ] in the standard order.
	 * @return array
	 */
	public static function notion_order( $steps ) {
		if ( self::opt( 'customizer', 'cz_nonotions' ) ) {
			return array();
		}
		$order = self::opt( 'customizer', 'cz_norder', '' );
		if ( ! $order ) {
			return $steps;
		}
		$place = array_flip( explode( ',', $order ) );
		usort(
			$steps,
			function ( $a, $b ) use ( $place ) {
				$pa = isset( $place[ $a['type'] ] ) ? $place[ $a['type'] ] : 999;
				$pb = isset( $place[ $b['type'] ] ) ? $place[ $b['type'] ] : 999;
				return $pa - $pb;
			}
		);
		return $steps;
	}

	/**
	 * The blocks under the pictures of a product page: key => label (their class is aimp-pp-{key}).
	 *
	 * @return array
	 */
	public static function product_blocks() {
		return array(
			'description' => __( 'Description', 'atelier-irisee-master-plugin' ),
			'recommend'   => __( 'Recommended product and kit buttons (phones)', 'atelier-irisee-master-plugin' ),
			'inspiration' => __( 'Inspiration', 'atelier-irisee-master-plugin' ),
			'ideal'       => __( 'Ideal for', 'atelier-irisee-master-plugin' ),
			'details'     => __( 'Details', 'atelier-irisee-master-plugin' ),
			'fitting'     => __( 'Fits these patterns / Suggested patterns', 'atelier-irisee-master-plugin' ),
			'sizes'       => __( 'Size chart', 'atelier-irisee-master-plugin' ),
			'fabrics'     => __( 'Recommended fabrics', 'atelier-irisee-master-plugin' ),
			'reviews'     => __( 'Reviews', 'atelier-irisee-master-plugin' ),
			'related'     => __( 'You may also like', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * The specification and washing subjects of fabrics and haberdashery: key => label (tile class aimp-washing-tile--{key}).
	 *
	 * @return array
	 */
	public static function product_spec_subjects() {
		return array(
			'composition'   => __( 'Fabric composition', 'atelier-irisee-master-plugin' ),
			'type'          => __( 'Type', 'atelier-irisee-master-plugin' ),
			'colour'        => __( 'Colour', 'atelier-irisee-master-plugin' ),
			'width'         => __( 'Width', 'atelier-irisee-master-plugin' ),
			'length'        => __( 'Length', 'atelier-irisee-master-plugin' ),
			'weight'        => __( 'Fabric weight', 'atelier-irisee-master-plugin' ),
			'diameter'      => __( 'Diameter', 'atelier-irisee-master-plugin' ),
			'material'      => __( 'Material', 'atelier-irisee-master-plugin' ),
			'finish'        => __( 'Finish', 'atelier-irisee-master-plugin' ),
			'mounting'      => __( 'Mounting', 'atelier-irisee-master-plugin' ),
			'certification' => __( 'Certification', 'atelier-irisee-master-plugin' ),
			'washing'       => __( 'Washing', 'atelier-irisee-master-plugin' ),
			'drying'        => __( 'Drying', 'atelier-irisee-master-plugin' ),
			'ironing'       => __( 'Ironing', 'atelier-irisee-master-plugin' ),
			'tips'          => __( 'Tips', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * The blocks of a product page: their order on computers and phones, hidden per screen, and shown as they are,
	 * as dropdowns or as tabs.
	 *
	 * @return array
	 */
	private static function product_block_fields() {
		$keys   = array_keys( self::product_blocks() );
		$fields = array(
			'pp_order'  => array( 'order', $keys ),
			'pp_porder' => array( 'order', $keys ),
		);
		foreach ( $keys as $key ) {
			$fields[ 'pp_hide_' . $key ]  = 'bool';
			$fields[ 'pp_phide_' . $key ] = 'bool';
		}
		return $fields;
	}

	/**
	 * The information on a product page: the blocks as dropdowns or tabs, the brand as a link, the "Ideal for"
	 * icons, the specifications shown, the product cards, the size chart and the review stars.
	 *
	 * @return array
	 */
	private static function product_info_fields() {
		$fields = array(
			'pp_layout'     => array( 'select', array( '', 'accordion', 'tabs' ) ),
			'pp_brand_link' => 'bool',
			'pp_ideal_size' => array( 0, 120, 0 ),
		);
		foreach ( array_keys( self::product_spec_subjects() ) as $subject ) {
			$fields[ 'pp_nospec_' . $subject ] = 'bool';
		}
		return array_merge(
			$fields,
			array(
				'pp_count'     => array( 0, 8, 0 ),
				'pp_related'   => array( 0, 12, 0 ),
				'pp_cards'     => array( 0, 8, 0 ),
				'pp_nomeasure' => 'bool',
				'pp_stars'     => 'color',
			)
		);
	}

	/**
	 * The kit steps bar's own settings: steps 2 and 3 can be left out and a fifth one added; per step a drawing or a
	 * picture; the thread, the needle's speed, loops, the button and the customizer's first-visit pop-up.
	 *
	 * @return array
	 */
	private static function kit_step_fields() {
		$drawings = array( 'select', array( '', 'pattern', 'fabric', 'notions', 'kit', 'sew' ) );
		$fields   = array(
			'ks_hide_2'   => 'bool',
			'ks_hide_3'   => 'bool',
			'ks_step5'    => 'bool',
		);
		for ( $n = 1; $n <= 5; $n++ ) {
			$fields[ 'ks_icon_' . $n ] = $drawings;
			$fields[ 'ks_img_' . $n ]  = 'image';
		}
		return array_merge(
			$fields,
			array(
				'ks_thread'    => 'color',
				'ks_width'     => array( 1, 8, -1 ),
				'ks_speed'     => array( 25, 400, 100 ),
				'ks_noloops'   => 'bool',
				'ks_url'       => 'url',
				'ks_nosparkle' => 'bool',
				'ks_nopopup'   => 'bool',
			)
		);
	}

	/**
	 * A setting of a part as the page being made uses it: the draft in the studio's preview, else what visitors see.
	 *
	 * @param string $part    Part.
	 * @param string $key     Setting.
	 * @param mixed  $default When it is not set.
	 * @return mixed
	 */
	public static function opt( $part, $key, $default = null ) {
		static $styles = null;
		if ( null === $styles ) {
			$styles = self::current_styles();
		}
		return isset( $styles[ $part ][ $key ] ) ? $styles[ $part ][ $key ] : $default;
	}

	/**
	 * Groups made on the server when the page is built: the preview loads again after a change.
	 *
	 * @return string[]
	 */
	public static function reload_groups() {
		return array( 'header_opts', 'side_opts', 'info_opts', 'tabbar_opts', 'lang_opts', 'hero_media', 'hero_buttons', 'arrow_opts', 'favicon', 'ks_opts', 'mosaic_opts', 'shop_cards', 'shop_tools', 'shop_badges', 'shop_cats', 'overview_opts', 'pp_buy', 'pp_info' );
	}

	/**
	 * All settings of a part: key => rule.
	 *
	 * @param string $id Part.
	 * @return array
	 */
	public static function fields( $id ) {
		$elements = self::elements();
		$groups   = self::groups();
		$fields   = array();
		foreach ( isset( $elements[ $id ] ) ? $elements[ $id ]['controls'] : array() as $group ) {
			if ( isset( $groups[ $group ] ) ) {
				$fields = array_merge( $fields, $groups[ $group ] );
			}
		}
		return $fields;
	}

	/**
	 * Only known parts and the settings they have, within their limits.
	 *
	 * @param mixed $styles Posted or imported styles.
	 * @return array
	 */
	public static function clean( $styles ) {
		$clean    = array();
		$elements = self::elements();
		foreach ( is_array( $styles ) ? $styles : array() as $id => $values ) {
			if ( ! isset( $elements[ $id ] ) || ! is_array( $values ) ) {
				continue;
			}
			$row = array();
			foreach ( self::fields( $id ) as $key => $rule ) {
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
				} elseif ( 'url' === $rule ) {
					$url = esc_url_raw( trim( (string) $value ) );
					if ( '' !== $url ) {
						$row[ $key ] = $url;
					}
				} elseif ( 'text' === $rule ) {
					$text = sanitize_text_field( (string) $value );
					if ( '' !== $text ) {
						$row[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 120 ) : substr( $text, 0, 120 );
					}
				} elseif ( 'longtext' === $rule ) {
					$text = trim( sanitize_textarea_field( (string) $value ) );
					if ( '' !== $text ) {
						$row[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 1500 ) : substr( $text, 0, 1500 );
					}
				} elseif ( 'video' === $rule ) {
					if ( absint( $value ) && 0 === strpos( (string) get_post_mime_type( absint( $value ) ), 'video/' ) ) {
						$row[ $key ] = absint( $value );
					}
				} elseif ( 'images' === $rule ) {
					$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $value ) ? $value : explode( ',', (string) $value ) ), 'wp_attachment_is_image' ) ) );
					if ( $ids ) {
						$row[ $key ] = array_slice( $ids, 0, 6 );
					}
				} elseif ( 'font' === $rule ) {
					// A Google Fonts family: letters, digits and spaces only.
					$font = trim( preg_replace( '/[^A-Za-z0-9 ]/', '', (string) $value ) );
					if ( '' !== $font ) {
						$row[ $key ] = substr( $font, 0, 60 );
					}
				} elseif ( is_array( $rule ) && 'order' === $rule[0] ) {
					// An order of blocks: the known ones, each once; the ones left out keep their standard place after them.
					$list = array_values( array_unique( array_intersect( array_map( 'trim', explode( ',', (string) $value ) ), $rule[1] ) ) );
					$list = array_merge( $list, array_values( array_diff( $rule[1], $list ) ) );
					if ( $list !== $rule[1] ) {
						$row[ $key ] = implode( ',', $list );
					}
				} elseif ( is_array( $rule ) && 'select' === $rule[0] ) {
					if ( '' !== $value && in_array( (string) $value, $rule[1], true ) ) {
						$row[ $key ] = (string) $value;
					}
				} elseif ( is_array( $rule ) && is_numeric( $value ) ) {
					$number = max( $rule[0], min( $rule[1], (int) round( (float) $value ) ) );
					if ( $number !== $rule[2] ) {
						$row[ $key ] = $number;
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
			// "Whole site" parts have no selector: site_css() makes their CSS.
			if ( ! isset( $elements[ $id ] ) || '' === $elements[ $id ]['selector'] ) {
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
		$css = self::site_css( $styles ) . self::opts_css( $styles ) . $all;
		if ( '' !== $computer ) {
			$css .= '@media (min-width:' . ( self::PHONE + 1 ) . 'px){' . $computer . '}';
		}
		if ( '' !== $phone ) {
			$css .= '@media (max-width:' . self::PHONE . 'px){' . $phone . '}';
		}
		return $css;
	}

	/**
	 * The settings of the "Whole site" parts of a look, as one row.
	 *
	 * @param array $styles Styles.
	 * @return array
	 */
	private static function site_row( $styles ) {
		$elements = self::elements();
		$row      = array();
		foreach ( $styles as $id => $values ) {
			if ( isset( $elements[ $id ] ) && '' === $elements[ $id ]['selector'] ) {
				$row = array_merge( $row, (array) $values );
			}
		}
		return $row;
	}

	/**
	 * The CSS of the "Whole site" parts (fonts from Google Fonts come first, as @import).
	 *
	 * @param array $styles Styles.
	 * @return string
	 */
	public static function site_css( $styles ) {
		$s       = self::site_row( $styles );
		$imports = '';
		$css     = '';
		$root    = '';
		foreach ( array(
			'gold'      => '--aimp-site-gold',
			'gold_text' => '--aimp-site-gold-text',
			'brown'     => '--aimp-site-brown',
			'success'   => '--aimp-site-success',
			'error'     => '--aimp-site-error',
		) as $key => $var ) {
			if ( isset( $s[ $key ] ) ) {
				$root .= $var . ':' . $s[ $key ] . ';';
			}
		}
		if ( isset( $s['line'] ) ) {
			$root .= '--aimp-site-line:' . $s['line'] . ';';
		}
		if ( '' !== $root ) {
			$css .= ':root{' . $root . '}';
		}

		// Fonts.
		$families = array(
			'trajan' => '"' . ( class_exists( 'AIMP_Site' ) ? AIMP_Site::FONT_FAMILY : 'Trajan Pro' ) . '","Trajan",Georgia,serif',
			'serif'  => 'Georgia,"Times New Roman",serif',
			'sans'   => '-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif',
		);
		$family = function ( $which, $fallback ) use ( $s, $families, &$imports ) {
			$choice = isset( $s[ $which . '_font' ] ) ? $s[ $which . '_font' ] : '';
			if ( 'google' === $choice && ! empty( $s[ $which . '_google' ] ) ) {
				$name     = $s[ $which . '_google' ];
				$imports .= '@import url("https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $name ) . ':wght@400;600;700&display=swap");';
				return '"' . $name . '",' . $fallback;
			}
			return isset( $families[ $choice ] ) ? $families[ $choice ] : '';
		};
		$titles = 'html body :is(h1,h2,h3,h4,h5,h6,.aimp-product-title,.aimp-hero-title,.aimp-kit-steps-title,.aimp-card-name,.aimp-details-title,.aimp-account-title,.aimp-footer-title)';
		$head   = $family( 'head', 'Georgia,serif' );
		$body   = $family( 'body', 'sans-serif' );
		if ( '' !== $body ) {
			$css .= 'html body,html body :is(button,input,select,textarea,.aimp-configurator,.aimp-header,.aimp-footer,.aimp-kit-steps,.aimp-hero){font-family:' . $body . '!important}';
		}
		if ( '' !== $head ) {
			$css .= $titles . '{font-family:' . $head . '!important}';
		}

		// Text and title sizes, computer and phone.
		$sizes    = function ( $prefix ) use ( $s ) {
			$out = '';
			if ( ! empty( $s[ $prefix . 'fs' ] ) ) {
				$out .= 'html body{font-size:' . (int) $s[ $prefix . 'fs' ] . 'px}html body :is(.aimp-configurator,.aimp-header,.aimp-footer){--aimp-body-size:' . (int) $s[ $prefix . 'fs' ] . 'px}';
			}
			foreach ( array( 'h1', 'h2', 'h3' ) as $tag ) {
				if ( ! empty( $s[ $prefix . $tag ] ) ) {
					$out .= 'html body ' . $tag . '{font-size:' . (int) $s[ $prefix . $tag ] . 'px!important}';
				}
			}
			return $out;
		};
		$computer = $sizes( '' );
		$phone    = $sizes( 'p' );
		if ( '' !== $computer ) {
			$css .= '@media (min-width:' . ( self::PHONE + 1 ) . 'px){' . $computer . '}';
		}
		if ( '' !== $phone ) {
			$css .= '@media (max-width:' . self::PHONE . 'px){' . $phone . '}';
		}
		if ( ! empty( $s['nocaps'] ) ) {
			$css .= 'html body :is(.aimp-configurator,.aimp-header,.aimp-footer,.aimp-kit-steps,.aimp-hero),html body :is(.aimp-configurator,.aimp-header,.aimp-footer,.aimp-kit-steps,.aimp-hero) *{text-transform:none!important}';
		}
		if ( isset( $s['spacing'] ) ) {
			$css .= $titles . '{letter-spacing:' . ( (int) $s['spacing'] / 100 ) . 'em!important}';
		}

		// Buttons.
		$buttons = 'html body :is(.aimp-configurator,.aimp-header,.aimp-footer,.aimp-kit-steps,.aimp-hero,.aimp-account) :is(.aimp-button,.aimp-account-button,.aimp-footer-button,.aimp-kit-steps-button,.aimp-hero-button)';
		$button  = '';
		if ( isset( $s['b_radius'] ) ) {
			$button .= 'border-radius:' . (int) $s['b_radius'] . 'px!important;';
		}
		if ( isset( $s['b_style'] ) ) {
			$button .= 'border-style:' . ( 'single' === $s['b_style'] ? 'solid' : 'none' ) . '!important;';
		}
		if ( isset( $s['b_width'] ) ) {
			$button .= 'border-width:' . (int) $s['b_width'] . 'px!important;';
		}
		if ( ! empty( $s['b_fill'] ) ) {
			$button .= 'background-color:var(--aimp-site-gold,#b38f4f)!important;color:#fff!important;';
		}
		if ( isset( $s['b_pad'] ) ) {
			$k       = (int) $s['b_pad'] / 100;
			$button .= 'padding:' . round( 0.7 * $k, 2 ) . 'rem ' . round( 1.6 * $k, 2 ) . 'rem!important;';
		}
		if ( '' !== $button ) {
			$css .= $buttons . '{' . $button . '}';
		}
		if ( isset( $s['hover'] ) ) {
			$hover = array(
				'lift'      => 'transform:translateY(-2px);box-shadow:0 6px 14px color-mix(in srgb,var(--aimp-site-brown,#613907) 18%,transparent)!important;',
				'underline' => 'text-decoration:underline!important;text-underline-offset:3px;',
				'none'      => 'background-color:transparent!important;',
			);
			$css .= $buttons . ':is(:hover,:focus-visible){' . $hover[ $s['hover'] ] . '}';
		}

		// Cards and shadows.
		$cards = 'html body :is(.aimp-configurator,.aimp-account) .aimp-card';
		$card  = '';
		if ( isset( $s['c_radius'] ) ) {
			$card .= 'border-radius:' . (int) $s['c_radius'] . 'px!important;';
		}
		if ( isset( $s['c_style'] ) ) {
			$card .= 'border-style:' . ( 'single' === $s['c_style'] ? 'solid' : 'none' ) . '!important;';
		}
		if ( isset( $s['c_width'] ) ) {
			$card .= 'border-width:' . (int) $s['c_width'] . 'px!important;';
		}
		if ( isset( $s['shadow'] ) ) {
			$card .= 'soft' === $s['shadow']
				? 'box-shadow:0 4px 14px color-mix(in srgb,var(--aimp-site-brown,#613907) 12%,transparent)!important;'
				: 'box-shadow:0 10px 28px color-mix(in srgb,var(--aimp-site-brown,#613907) 24%,transparent)!important;';
		}
		if ( '' !== $card ) {
			$css .= $cards . '{' . $card . '}';
		}

		// Page background and width.
		$background = '';
		if ( isset( $s['page_bg'] ) && isset( $s['page_bg2'] ) ) {
			$background .= 'background:linear-gradient(180deg,' . $s['page_bg'] . ',' . $s['page_bg2'] . ')!important;';
		} elseif ( isset( $s['page_bg'] ) ) {
			$background .= 'background:' . $s['page_bg'] . '!important;';
		}
		if ( ! empty( $s['page_img'] ) ) {
			$url = wp_get_attachment_image_url( (int) $s['page_img'], 'full' );
			if ( $url ) {
				$background .= 'background-image:url("' . esc_url_raw( $url ) . '")!important;background-size:cover!important;background-position:center top!important;';
			}
		}
		if ( ! empty( $s['page_fixed'] ) ) {
			$background .= 'background-attachment:fixed!important;';
		}
		if ( '' !== $background ) {
			$css .= 'html body{' . $background . '}';
		}
		if ( ! empty( $s['max_width'] ) ) {
			$css .= 'html body .aimp-configurator{max-width:' . (int) $s['max_width'] . 'px;margin-left:auto!important;margin-right:auto!important}';
		}
		if ( ! empty( $s['gutter'] ) ) {
			$css .= '@media (max-width:' . self::PHONE . 'px){html body .aimp-configurator{padding-left:' . (int) $s['gutter'] . 'px!important;padding-right:' . (int) $s['gutter'] . 'px!important}}';
		}

		// Effects, icons, loading, focus.
		if ( ! empty( $s['nosparkle'] ) ) {
			$css .= 'html body .is-sparked,html body .is-sparked::before,html body .is-sparked::after{animation:none!important}html body .ks-spark{display:none!important}';
		}
		if ( isset( $s['icon_line'] ) ) {
			$css .= 'html body :is(.aimp-header,.aimp-configurator,.aimp-footer,.aimp-kit-steps,.aimp-tabbar) svg :is(path,circle,rect,line,polyline,polygon,ellipse){stroke-width:' . ( 'thin' === $s['icon_line'] ? '1.1' : '2.1' ) . '!important}';
		}
		if ( isset( $s['loader'] ) ) {
			$css .= 'ring' === $s['loader']
				? 'html body .aimp-loading::before{content:"";display:block;width:28px;height:28px;margin:0 auto .6rem;border:3px solid color-mix(in srgb,var(--aimp-site-gold,#b38f4f) 25%,transparent);border-top-color:var(--aimp-site-gold,#b38f4f);border-radius:50%;animation:aimp-spin .9s linear infinite}@keyframes aimp-spin{to{transform:rotate(360deg)}}'
				: 'html body .aimp-loading::after{content:"";display:inline-block;width:1.2em;text-align:left;animation:aimp-dots 1.2s steps(4,end) infinite}@keyframes aimp-dots{0%{content:""}25%{content:"."}50%{content:".."}75%{content:"..."}}';
		}
		if ( isset( $s['focus_color'] ) || isset( $s['focus_width'] ) ) {
			$css .= 'html body :focus-visible{outline:' . ( isset( $s['focus_width'] ) ? (int) $s['focus_width'] : 2 ) . 'px solid ' . ( isset( $s['focus_color'] ) ? $s['focus_color'] : 'var(--aimp-site-gold,#b38f4f)' ) . '!important;outline-offset:2px}';
		}
		return $imports . $css;
	}

	/**
	 * The CSS of the header, menu and hero settings (phase 3) that are styling only.
	 *
	 * @param array $styles Styles.
	 * @return string
	 */
	public static function opts_css( $styles ) {
		$get      = function ( $part, $key ) use ( $styles ) {
			return isset( $styles[ $part ][ $key ] ) ? $styles[ $part ][ $key ] : null;
		};
		$min      = '@media (min-width:' . ( self::PHONE + 1 ) . 'px){';
		$max      = '@media (max-width:' . self::PHONE . 'px){';
		$css      = '';
		$computer = '';
		$phone    = '';

		// Header: the numbers on the icons, the words under them.
		$badge = '';
		if ( $get( 'header', 'badge_bg' ) ) {
			$badge .= 'background:' . $get( 'header', 'badge_bg' ) . '!important;';
		}
		if ( $get( 'header', 'badge_fg' ) ) {
			$badge .= 'color:' . $get( 'header', 'badge_fg' ) . '!important;';
		}
		if ( '' !== $badge ) {
			$css .= 'html body .aimp-header .aimp-header-count{' . $badge . '}';
		}
		if ( $get( 'header', 'nolabels' ) ) {
			$css .= 'html body .aimp-header .aimp-header-label{display:none!important}';
		}

		// Header icons: hidden per screen, and their order.
		$icons = array(
			'search'  => '.aimp-header-search-toggle',
			'shop'    => '.aimp-header-link--shop',
			'fav'     => '.aimp-header-link--fav',
			'cart'    => '.aimp-header-link--cart',
			'account' => '.aimp-header-link--account',
			'lang'    => '.aimp-header-lang',
		);
		foreach ( $icons as $icon => $selector ) {
			$sel = 'html body .aimp-header-icons > ' . $selector;
			if ( $get( 'header-icons', 'ic_hide_' . $icon ) ) {
				$computer .= $sel . '{display:none!important}';
			}
			if ( $get( 'header-icons', 'ic_phide_' . $icon ) ) {
				$phone .= $sel . '{display:none!important}';
			}
			if ( $get( 'header-icons', 'ic_order_' . $icon ) ) {
				$css .= $sel . '{order:' . (int) $get( 'header-icons', 'ic_order_' . $icon ) . '}';
			}
		}

		// The "Menu" bar.
		if ( $get( 'header-menu-bar', 'mb_align' ) ) {
			$css .= 'html body .aimp-header-bar .aimp-header-inner{display:flex!important;justify-content:' . ( 'center' === $get( 'header-menu-bar', 'mb_align' ) ? 'center' : 'flex-end' ) . '!important}';
		}

		// Side menu.
		if ( $get( 'side-menu', 'sm_width' ) ) {
			$css .= 'html body .aimp-side-menu{width:' . (int) $get( 'side-menu', 'sm_width' ) . 'px!important;max-width:100vw!important}';
		}
		if ( $get( 'side-menu', 'sm_bg' ) ) {
			$css .= 'html body .aimp-side-menu{background:' . $get( 'side-menu', 'sm_bg' ) . '!important}';
		}
		if ( $get( 'side-menu', 'sm_text' ) ) {
			$css .= 'html body .aimp-side-menu,html body .aimp-header .aimp-side-menu :is(a,.aimp-side-menu-title,.aimp-side-menu-close,.aimp-side-menu-extra){color:' . $get( 'side-menu', 'sm_text' ) . '!important}html body .aimp-side-menu-sub-toggle::before{border-color:' . $get( 'side-menu', 'sm_text' ) . '!important}';
		}
		if ( $get( 'side-menu', 'sm_size' ) ) {
			$css .= 'html body .aimp-header .aimp-side-menu-list a{font-size:' . (int) $get( 'side-menu', 'sm_size' ) . 'px!important}';
		}

		// Hero: the filter's colour and strength, the focal point of the picture, its height and the place of the text.
		$tint = $get( 'hero', 'h_tint' );
		if ( $tint ) {
			$layer = function ( $angle ) use ( $tint ) {
				return 'background:linear-gradient(' . $angle . ',' . $tint . ',color-mix(in srgb,' . $tint . ' 88%,transparent) 55%,color-mix(in srgb,' . $tint . ' 76%,transparent))!important;';
			};
			$computer .= 'html body .aimp-hero::before{' . $layer( '90deg' ) . '}';
			$phone    .= 'html body .aimp-hero::before{' . $layer( '180deg' ) . '}';
		}
		if ( null !== $get( 'hero', 'h_strength' ) ) {
			$css .= 'html body .aimp-hero{--aimp-hero-tint:' . ( (int) $get( 'hero', 'h_strength' ) / 100 ) . '!important}';
		}
		if ( null !== $get( 'hero', 'h_fx' ) || null !== $get( 'hero', 'h_fy' ) ) {
			$x    = null !== $get( 'hero', 'h_fx' ) ? (int) $get( 'hero', 'h_fx' ) : 75;
			$y    = null !== $get( 'hero', 'h_fy' ) ? (int) $get( 'hero', 'h_fy' ) : 40;
			$css .= 'html body .aimp-hero,html body .aimp-hero-media > *{background-position:' . $x . '% ' . $y . '%!important;object-position:' . $x . '% ' . $y . '%!important}';
		}
		$heights = array(
			'large'  => '75vh',
			'medium' => '55vh',
		);
		if ( isset( $heights[ $get( 'hero', 'h_height' ) ] ) ) {
			$css .= 'html body .aimp-hero{min-height:' . $heights[ $get( 'hero', 'h_height' ) ] . '!important}';
		}
		$place = function ( $x, $y ) {
			$out = '';
			$map = array(
				'left'   => 'flex-start',
				'center' => 'center',
				'right'  => 'flex-end',
				'top'    => 'flex-start',
				'bottom' => 'flex-end',
			);
			if ( $x && isset( $map[ $x ] ) ) {
				$out .= 'html body .aimp-hero-inner{display:flex!important;justify-content:' . $map[ $x ] . '!important}';
			}
			if ( $y && isset( $map[ $y ] ) ) {
				$out .= 'html body .aimp-hero{align-items:' . $map[ $y ] . '!important}';
			}
			return $out;
		};
		$computer .= $place( $get( 'hero', 'h_x' ), $get( 'hero', 'h_y' ) );
		$phone    .= $place( $get( 'hero', 'ph_x' ), $get( 'hero', 'ph_y' ) );

		// Shop pages: columns (computer, tablet, phone), the cards, the badges, the overview cards.
		$grid    = 'html body .aimp-shop:not(.aimp-overview) .aimp-grid';
		$columns = function ( $n ) use ( $grid ) {
			return $grid . '{grid-template-columns:repeat(' . (int) $n . ',minmax(0,1fr))!important}';
		};
		if ( $get( 'shop', 'cols' ) ) {
			$css .= '@media (min-width:1025px){' . $columns( $get( 'shop', 'cols' ) ) . '}';
		}
		if ( $get( 'shop', 'tcols' ) ) {
			$css .= '@media (min-width:' . ( self::PHONE + 1 ) . 'px) and (max-width:1024px){' . $columns( $get( 'shop', 'tcols' ) ) . '}';
		}
		if ( $get( 'shop', 'pcols' ) ) {
			$phone .= $columns( $get( 'shop', 'pcols' ) );
		}
		$card = 'html body .aimp-shop:not(.aimp-overview) .aimp-card';
		if ( $get( 'shop', 'noprice' ) ) {
			$css .= $card . ' .aimp-card-price{display:none!important}';
		} elseif ( $get( 'shop', 'nounit' ) ) {
			$css .= $card . ' .aimp-card-price small{display:none!important}';
		}
		if ( $get( 'shop', 'nosub' ) ) {
			$css .= $card . ' .aimp-card-sub{display:none!important}';
		}
		if ( $get( 'shop', 'nofav' ) ) {
			$css .= 'html body .aimp-shop:not(.aimp-overview) .aimp-grid .aimp-fav{display:none!important}';
		}
		$ratios = array(
			'square'    => '1 / 1',
			'portrait'  => '3 / 4',
			'landscape' => '4 / 3',
		);
		if ( isset( $ratios[ $get( 'shop', 'ratio' ) ] ) ) {
			$css .= $card . ' .aimp-card-image{aspect-ratio:' . $ratios[ $get( 'shop', 'ratio' ) ] . '!important}' . $card . ' .aimp-card-image img{width:100%;height:100%;object-fit:cover}';
		}
		if ( $get( 'shop', 'zoom_in' ) ) {
			$css .= $card . ' .aimp-card-image{overflow:hidden}' . $card . ' .aimp-card-image img{transition:transform .35s ease}' . $card . ':is(:hover,:focus-visible) .aimp-card-image img{transform:scale(1.06)}';
		}
		$badge = '';
		if ( $get( 'shop', 'badge_bg' ) ) {
			$badge .= 'background:' . $get( 'shop', 'badge_bg' ) . '!important;border-color:' . $get( 'shop', 'badge_bg' ) . '!important;';
		}
		if ( $get( 'shop', 'badge_fg' ) ) {
			$badge .= 'color:' . $get( 'shop', 'badge_fg' ) . '!important;';
		}
		if ( '' !== $badge ) {
			$css .= $card . ' .aimp-badge:not(.aimp-badge--selected){' . $badge . '}';
		}
		$ov = 'html body .aimp-configurator .aimp-overview-grid';
		if ( $get( 'overview', 'ov_cols' ) ) {
			$css .= '@media (min-width:1025px){' . $ov . '{grid-template-columns:repeat(' . (int) $get( 'overview', 'ov_cols' ) . ',minmax(0,1fr))!important}}';
		}
		if ( $get( 'overview', 'ov_tcols' ) ) {
			$css .= '@media (min-width:' . ( self::PHONE + 1 ) . 'px) and (max-width:1024px){' . $ov . '{grid-template-columns:repeat(' . (int) $get( 'overview', 'ov_tcols' ) . ',minmax(0,1fr))!important}}';
		}
		if ( $get( 'overview', 'ov_pcols' ) ) {
			$phone .= $ov . '{grid-template-columns:repeat(' . (int) $get( 'overview', 'ov_pcols' ) . ',minmax(0,1fr))!important}';
		}
		foreach ( array( 'configurator', 'fabrics', 'patterns', 'haberdashery', 'giftcards' ) as $section ) {
			if ( $get( 'overview', 'ov_order_' . $section ) ) {
				$css .= $ov . ' > .aimp-overview-card--' . $section . '{order:' . (int) $get( 'overview', 'ov_order_' . $section ) . '}';
			}
		}

		// Kit steps bar: the thread.
		if ( $get( 'kit-steps', 'ks_thread' ) ) {
			$css .= 'html body .aimp-kit-steps .ks-seam-sewn{stroke:' . $get( 'kit-steps', 'ks_thread' ) . '!important}html body .aimp-kit-steps .ks-seam-guide{stroke:color-mix(in srgb,' . $get( 'kit-steps', 'ks_thread' ) . ' 35%,transparent)!important}';
		}
		if ( null !== $get( 'kit-steps', 'ks_width' ) ) {
			$css .= 'html body .aimp-kit-steps :is(.ks-seam-sewn,.ks-seam-guide){stroke-width:' . (int) $get( 'kit-steps', 'ks_width' ) . '!important}';
		}

		// Mosaic: layout, gaps, captions, mouse-over.
		$layout = $get( 'mosaic', 'mos_layout' );
		if ( 'grid' === $layout ) {
			$css      .= 'html body .aimp-configurator .aimp-mosaic-grid{grid-template-columns:repeat(5,minmax(0,1fr))!important;grid-template-rows:none!important;grid-auto-rows:clamp(140px,18vw,240px)!important}html body .aimp-configurator .aimp-mosaic-tile{grid-column:auto!important;grid-row:auto!important}';
			$phone    .= 'html body .aimp-configurator .aimp-mosaic-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}';
		} elseif ( 'row' === $layout ) {
			$css .= 'html body .aimp-configurator .aimp-mosaic-grid{display:grid!important;grid-template-columns:none!important;grid-template-rows:none!important;grid-auto-flow:column!important;grid-auto-columns:clamp(160px,22vw,260px)!important;grid-auto-rows:clamp(200px,28vw,320px)!important;overflow-x:auto!important;scroll-snap-type:x mandatory}html body .aimp-configurator .aimp-mosaic-tile{grid-column:auto!important;grid-row:auto!important;scroll-snap-align:start}';
		}
		if ( null !== $get( 'mosaic', 'mos_gap' ) ) {
			$css .= 'html body .aimp-configurator .aimp-mosaic-grid{gap:' . (int) $get( 'mosaic', 'mos_gap' ) . 'px!important}';
		}
		if ( $get( 'mosaic', 'mos_nocaption' ) ) {
			$css .= 'html body .aimp-mosaic-caption{display:none!important}';
		} elseif ( $get( 'mosaic', 'mos_noprice' ) ) {
			$css .= 'html body .aimp-configurator .aimp-mosaic-price{display:none!important}';
		}
		if ( 'none' === $get( 'mosaic', 'mos_hover' ) ) {
			$css .= 'html body .aimp-configurator .aimp-mosaic-tile a:is(:hover,:focus-visible) img{transform:none!important}';
		} elseif ( 'lift' === $get( 'mosaic', 'mos_hover' ) ) {
			$css .= 'html body .aimp-configurator .aimp-mosaic-tile a:is(:hover,:focus-visible) img{transform:none!important}html body .aimp-configurator .aimp-mosaic-tile{transition:transform .25s ease,box-shadow .25s ease}html body .aimp-configurator .aimp-mosaic-tile:hover{transform:translateY(-4px);box-shadow:0 10px 24px color-mix(in srgb,var(--aimp-site-brown,#613907) 22%,transparent)}';
		}

		// Product page: the order of the blocks (computers, phones) and blocks hidden per screen.
		$pp    = 'html body .aimp-configurator.aimp-product';
		$order = function ( $list ) use ( $pp ) {
			$out = $pp . '>*{order:9!important}' . $pp . '>:is(.aimp-product-topbar,.woocommerce-notices-wrapper){order:0!important}' . $pp . '>.aimp-product-main{order:1!important}';
			foreach ( explode( ',', $list ) as $i => $block ) {
				$out .= $pp . '>.aimp-pp-' . sanitize_html_class( $block ) . '{order:' . ( 10 + $i ) . '!important}';
			}
			return $out;
		};
		if ( $get( 'product-page', 'pp_order' ) ) {
			$computer .= $pp . '{display:flex!important;flex-direction:column!important}' . $order( $get( 'product-page', 'pp_order' ) );
		}
		if ( $get( 'product-page', 'pp_porder' ) ) {
			$phone .= $order( $get( 'product-page', 'pp_porder' ) );
		}
		foreach ( array_keys( self::product_blocks() ) as $block ) {
			if ( $get( 'product-page', 'pp_hide_' . $block ) ) {
				$computer .= $pp . '>.aimp-pp-' . $block . '{display:none!important}';
			}
			if ( $get( 'product-page', 'pp_phide_' . $block ) ) {
				$phone .= $pp . '>.aimp-pp-' . $block . '{display:none!important}';
			}
		}
		// The pictures: thumbnails on the left or none (dots), the shape, no zoom, the picture's width and side.
		$g = $pp . ' .aimp-gallery';
		if ( 'left' === $get( 'product-page', 'g_thumbs' ) ) {
			$computer .= $g . '{display:grid!important;grid-template-columns:64px minmax(0,1fr);gap:.6rem;align-items:start}' . $g . ' .aimp-gallery-main{grid-column:2;grid-row:1}' . $g . ' .aimp-gallery-thumbs{grid-column:1;grid-row:1;grid-template-columns:minmax(0,1fr)!important;margin-top:0!important}';
		} elseif ( 'none' === $get( 'product-page', 'g_thumbs' ) ) {
			$computer .= $g . ' .aimp-gallery-thumbs{display:none!important}' . $g . ' .aimp-gallery-dots{display:flex!important;justify-content:center;gap:.5rem;margin-top:.6rem}' . $g . ' .aimp-gallery-dot{width:.6rem;height:.6rem;min-height:0;padding:0;border:1px solid var(--aimp-gold);border-radius:50%;background:transparent;cursor:pointer}' . $g . ' .aimp-gallery-dot.is-active{background:var(--aimp-gold)}';
		}
		if ( isset( $ratios[ $get( 'product-page', 'g_ratio' ) ] ) ) {
			$css .= $g . ' .aimp-gallery-main img{width:100%;aspect-ratio:' . $ratios[ $get( 'product-page', 'g_ratio' ) ] . '!important;object-fit:cover}';
		}
		if ( $get( 'product-page', 'g_nozoom' ) ) {
			$css .= $g . ' .aimp-gallery-main.is-zooming img{transform:none!important}';
		}
		$split = $get( 'product-page', 'g_split' );
		if ( $split || $get( 'product-page', 'g_right' ) ) {
			$cols = 'wide' === $split ? array( 3, 2 ) : ( 'narrow' === $split ? array( 2, 3 ) : array( 1, 1 ) );
			if ( $get( 'product-page', 'g_right' ) ) {
				$cols = array_reverse( $cols );
			}
			$css .= '@media (min-width:1025px){' . $pp . ' .aimp-product-main{grid-template-columns:minmax(0,' . $cols[0] . 'fr) minmax(0,' . $cols[1] . 'fr)!important}' .
				( $get( 'product-page', 'g_right' ) ? $pp . ' .aimp-product-main>.aimp-product-media{order:2}' : '' ) . '}';
		}
		// The buy bar: the delivery line, "Only … left", "Email me when it's back".
		foreach ( array(
			'pp_notrust' => '.aimp-delivery-line',
			'pp_nolow'   => '.aimp-low-stock',
			'pp_noalert' => '.aimp-stock-alert',
		) as $key => $selector ) {
			if ( $get( 'product-page', $key ) ) {
				$css .= $pp . ' ' . $selector . '{display:none!important}';
			}
		}
		// The information: "Ideal for" icons, specifications left out, cards per row, the size chart, review stars.
		if ( $get( 'product-page', 'pp_ideal_size' ) ) {
			$css .= $pp . ' .aimp-ideal-card svg{width:' . (int) $get( 'product-page', 'pp_ideal_size' ) . 'px!important;height:' . (int) $get( 'product-page', 'pp_ideal_size' ) . 'px!important}';
		}
		foreach ( array_keys( self::product_spec_subjects() ) as $subject ) {
			if ( $get( 'product-page', 'pp_nospec_' . $subject ) ) {
				$css .= $pp . ' .aimp-washing-tile--' . $subject . '{display:none!important}';
			}
		}
		if ( $get( 'product-page', 'pp_cards' ) ) {
			$css .= '@media (min-width:1025px){' . $pp . ' .aimp-product-cards{grid-template-columns:repeat(' . (int) $get( 'product-page', 'pp_cards' ) . ',minmax(0,1fr))!important}}';
		}
		if ( $get( 'product-page', 'pp_nomeasure' ) ) {
			$css .= $pp . ' .aimp-measure-button{display:none!important}';
		}
		if ( $get( 'product-page', 'pp_stars' ) ) {
			$css .= $pp . ' .star-rating,' . $pp . ' .star-rating span::before,' . $pp . ' p.stars a{color:' . $get( 'product-page', 'pp_stars' ) . '!important}';
		}

		// Customizer: cards per row, what a card shows, the size chart, the sheet, the bar on phones.
		$cz = 'html body :is(.aimp-configurator[data-aimp-configurator],.aimp-config-sheet)';
		if ( $get( 'customizer', 'cz_cols' ) ) {
			$computer .= $cz . ' .aimp-grid{grid-template-columns:repeat(' . (int) $get( 'customizer', 'cz_cols' ) . ',minmax(0,1fr))!important}';
		}
		if ( $get( 'customizer', 'cz_pcols' ) ) {
			$phone .= $cz . ' .aimp-grid{grid-template-columns:repeat(' . (int) $get( 'customizer', 'cz_pcols' ) . ',minmax(0,1fr))!important}';
		}
		foreach ( array(
			'cz_noprice'     => ' .aimp-grid .aimp-card-price',
			'cz_nosub'       => ' .aimp-grid .aimp-card-sub',
			'cz_nofav'       => ' .aimp-grid .aimp-fav',
			'cz_nosizechart' => ' :is([data-role="size-chart"],[data-role="size-chart-in"])',
			'cz_nomeasure'   => ' [data-action="measure-guide"]',
		) as $key => $selector ) {
			if ( $get( 'customizer', $key ) ) {
				$css .= $cz . $selector . '{display:none!important}';
			}
		}
		if ( isset( $ratios[ $get( 'customizer', 'cz_ratio' ) ] ) ) {
			$css .= $cz . ' .aimp-grid .aimp-card-image{aspect-ratio:' . $ratios[ $get( 'customizer', 'cz_ratio' ) ] . '!important}' . $cz . ' .aimp-grid .aimp-card-image img{width:100%;height:100%;object-fit:cover}';
		}
		if ( 'full' === $get( 'customizer', 'cz_sheet' ) ) {
			$css .= 'html body .aimp-config-sheet .aimp-sheet-panel{top:0;max-height:none!important;border-radius:0!important}';
		}
		$bar = '';
		if ( $get( 'customizer', 'cz_bar_bg' ) ) {
			$bar .= 'background:' . $get( 'customizer', 'cz_bar_bg' ) . '!important;';
		}
		if ( $get( 'customizer', 'cz_bar_fg' ) ) {
			$bar .= 'color:' . $get( 'customizer', 'cz_bar_fg' ) . '!important;';
		}
		if ( '' !== $bar ) {
			$css .= 'html body .aimp-config-bar{' . $bar . '}';
		}

		if ( '' !== $computer ) {
			$css .= $min . $computer . '}';
		}
		if ( '' !== $phone ) {
			$css .= $max . $phone . '}';
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
				// Parts of the page only ("Whole site" has nothing to click).
				'elements' => array_filter( wp_list_pluck( self::elements(), 'selector' ) ),
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
					'groups'   => self::groups(),
					'reload'   => self::reload_groups(),
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

	/**
	 * A colour with a "Standard" link.
	 *
	 * @param string $key   Setting.
	 * @param string $label Label.
	 */
	private static function color( $key, $label ) {
		printf(
			'<label class="aimp-ds-color"><span>%1$s</span><input type="color" data-key="%2$s"><button type="button" class="button-link" data-clear="%2$s">%3$s</button></label>',
			esc_html( $label ),
			esc_attr( $key ),
			esc_html__( 'Standard', 'atelier-irisee-master-plugin' )
		);
	}

	/**
	 * A choice list; the first choice is the standard.
	 *
	 * @param string $key     Setting.
	 * @param string $label   Label.
	 * @param array  $choices value => label.
	 */
	private static function choice( $key, $label, $choices ) {
		echo '<label class="aimp-ds-select"><span>' . esc_html( $label ) . '</span><select data-key="' . esc_attr( $key ) . '">';
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $text ) . '</option>';
		}
		echo '</select></label>';
	}

	/**
	 * A yes/no setting.
	 *
	 * @param string $key   Setting.
	 * @param string $label Label.
	 */
	private static function check_box( $key, $label ) {
		echo '<label class="aimp-ds-check"><input type="checkbox" data-key="' . esc_attr( $key ) . '"> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * A picture from the media library.
	 *
	 * @param string $key   Setting.
	 * @param string $label Button label.
	 */
	private static function picture_field( $key, $label ) {
		printf(
			'<div class="aimp-ds-image" data-image="%1$s"><img class="aimp-ds-image-preview" alt="" hidden><button type="button" class="button aimp-ds-image-choose" data-image-key="%1$s">%2$s</button><button type="button" class="button-link" data-clear="%1$s">%3$s</button></div>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_html__( 'Standard', 'atelier-irisee-master-plugin' )
		);
	}

	/**
	 * The settings, one block per group (the studio shows the blocks of the chosen part only).
	 */
	private static function render_groups() {
		$computer = __( 'Computer', 'atelier-irisee-master-plugin' );
		$phone    = __( 'Phone', 'atelier-irisee-master-plugin' );
		$standard = __( 'Standard', 'atelier-irisee-master-plugin' );
		$group    = function ( $key, $title ) {
			echo '<fieldset class="aimp-ds-group" data-group="' . esc_attr( $key ) . '" hidden><legend>' . esc_html( $title ) . '</legend>';
		};
		$sub      = function ( $title ) {
			echo '<p class="aimp-ds-sub">' . esc_html( $title ) . '</p>';
		};

		$group( 'move', __( 'Position', 'atelier-irisee-master-plugin' ) );
		$sub( $computer );
		self::number( 'x', __( 'Left / right', 'atelier-irisee-master-plugin' ), -200, 200, 'px' );
		self::number( 'y', __( 'Up / down', 'atelier-irisee-master-plugin' ), -200, 200, 'px' );
		$sub( $phone );
		self::number( 'px', __( 'Left / right', 'atelier-irisee-master-plugin' ), -200, 200, 'px' );
		self::number( 'py', __( 'Up / down', 'atelier-irisee-master-plugin' ), -200, 200, 'px' );
		echo '</fieldset>';

		$group( 'size', __( 'Size', 'atelier-irisee-master-plugin' ) );
		self::number( 'zoom', $computer, 40, 250, '%' );
		self::number( 'pzoom', $phone, 40, 250, '%' );
		echo '</fieldset>';

		$group( 'space', __( 'Space above and below', 'atelier-irisee-master-plugin' ) );
		$sub( $computer );
		self::number( 'mt', __( 'Space above', 'atelier-irisee-master-plugin' ), -150, 300, 'px' );
		self::number( 'mb', __( 'Space below', 'atelier-irisee-master-plugin' ), -150, 300, 'px' );
		$sub( $phone );
		self::number( 'pmt', __( 'Space above', 'atelier-irisee-master-plugin' ), -150, 300, 'px' );
		self::number( 'pmb', __( 'Space below', 'atelier-irisee-master-plugin' ), -150, 300, 'px' );
		echo '</fieldset>';

		$group( 'hide', __( 'Show or hide', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'hide', __( 'Hide on computers', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'phide', __( 'Hide on phones', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'colors', __( 'Colours', 'atelier-irisee-master-plugin' ) );
		self::color( 'color', __( 'Text colour', 'atelier-irisee-master-plugin' ) );
		self::color( 'bg', __( 'Background', 'atelier-irisee-master-plugin' ) );
		self::color( 'border', __( 'Border colour', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'radius', __( 'Rounded corners', 'atelier-irisee-master-plugin' ) );
		self::number( 'radius', __( 'Rounded corners', 'atelier-irisee-master-plugin' ), 0, 200, 'px' );
		echo '</fieldset>';

		$group( 'image', __( 'Picture', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'img', __( 'Other picture', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Replaces the pictures in this part. The focal point is the spot that stays in view when a picture is cut off.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::number( 'fx', __( 'Focal point left → right', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		self::number( 'fy', __( 'Focal point top → bottom', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		echo '</fieldset>';

		$group( 'anim', __( 'Animations', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'noanim', __( 'No animations in this part', 'atelier-irisee-master-plugin' ) );
		self::number( 'speed', __( 'Speed', 'atelier-irisee-master-plugin' ), 25, 400, '%' );
		echo '</fieldset>';

		// The whole site.
		$group( 'palette', __( 'Brand colours', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Used everywhere in the shop: buttons, lines, prices, titles, icons.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::color( 'gold', __( 'Gold', 'atelier-irisee-master-plugin' ) );
		self::color( 'gold_text', __( 'Gold text (darker, readable)', 'atelier-irisee-master-plugin' ) );
		self::color( 'brown', __( 'Brown (body text)', 'atelier-irisee-master-plugin' ) );
		self::color( 'success', __( 'Success messages', 'atelier-irisee-master-plugin' ) );
		self::color( 'error', __( 'Error messages', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$fonts = array(
			''       => $standard,
			'trajan' => __( 'Trajan Pro (the uploaded font files)', 'atelier-irisee-master-plugin' ),
			'serif'  => __( 'Classic serif (Georgia)', 'atelier-irisee-master-plugin' ),
			'sans'   => __( 'Clean sans-serif (system font)', 'atelier-irisee-master-plugin' ),
			'google' => __( 'A Google Font (name below)', 'atelier-irisee-master-plugin' ),
		);
		$group( 'fonts', __( 'Fonts', 'atelier-irisee-master-plugin' ) );
		self::choice( 'head_font', __( 'Titles', 'atelier-irisee-master-plugin' ), $fonts );
		echo '<label class="aimp-ds-text-field"><span>' . esc_html__( 'Google Font for titles', 'atelier-irisee-master-plugin' ) . '</span><input type="text" data-key="head_google" placeholder="Cinzel"></label>';
		self::choice( 'body_font', __( 'Text', 'atelier-irisee-master-plugin' ), $fonts );
		echo '<label class="aimp-ds-text-field"><span>' . esc_html__( 'Google Font for text', 'atelier-irisee-master-plugin' ) . '</span><input type="text" data-key="body_google" placeholder="Lato"></label>';
		echo '<p class="description">' . esc_html__( 'Google Fonts: type the name exactly as on fonts.google.com, e.g. "Cormorant Garamond".', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'textsize', __( 'Text size (0 = standard)', 'atelier-irisee-master-plugin' ) );
		self::number( 'fs', $computer, 0, 24, 'px' );
		self::number( 'pfs', $phone, 0, 24, 'px' );
		echo '</fieldset>';

		$group( 'headings', __( 'Title sizes (0 = standard)', 'atelier-irisee-master-plugin' ) );
		$sub( $computer );
		self::number( 'h1', __( 'Main title', 'atelier-irisee-master-plugin' ), 0, 80, 'px' );
		self::number( 'h2', __( 'Subtitle', 'atelier-irisee-master-plugin' ), 0, 64, 'px' );
		self::number( 'h3', __( 'Small title', 'atelier-irisee-master-plugin' ), 0, 48, 'px' );
		$sub( $phone );
		self::number( 'ph1', __( 'Main title', 'atelier-irisee-master-plugin' ), 0, 64, 'px' );
		self::number( 'ph2', __( 'Subtitle', 'atelier-irisee-master-plugin' ), 0, 48, 'px' );
		self::number( 'ph3', __( 'Small title', 'atelier-irisee-master-plugin' ), 0, 40, 'px' );
		echo '</fieldset>';

		$group( 'caps', __( 'Capitals and letter spacing', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nocaps', __( 'No capitals (normal letters instead of UPPERCASE)', 'atelier-irisee-master-plugin' ) );
		self::number( 'spacing', __( 'Letter spacing of titles', 'atelier-irisee-master-plugin' ), 0, 30, '‰' );
		echo '<button type="button" class="button-link" data-clear="spacing">' . esc_html( $standard ) . '</button>';
		echo '</fieldset>';

		$group( 'buttons', __( 'Buttons', 'atelier-irisee-master-plugin' ) );
		self::number( 'b_radius', __( 'Rounded corners', 'atelier-irisee-master-plugin' ), 0, 100, 'px' );
		self::choice(
			'b_style',
			__( 'Border', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Double gold line (standard)', 'atelier-irisee-master-plugin' ),
				'single' => __( 'Single line', 'atelier-irisee-master-plugin' ),
				'none'   => __( 'No border', 'atelier-irisee-master-plugin' ),
			)
		);
		self::number( 'b_width', __( 'Border width', 'atelier-irisee-master-plugin' ), 0, 10, 'px' );
		self::check_box( 'b_fill', __( 'Filled with gold (white text)', 'atelier-irisee-master-plugin' ) );
		self::number( 'b_pad', __( 'Inner space', 'atelier-irisee-master-plugin' ), 50, 200, '%' );
		echo '<p><button type="button" class="button-link" data-clear="b_radius,b_width">' . esc_html( $standard ) . '</button></p>';
		echo '</fieldset>';

		$group( 'hover', __( 'When the mouse is on a button', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'hover',
			__( 'Effect', 'atelier-irisee-master-plugin' ),
			array(
				''          => __( 'Light gold (standard)', 'atelier-irisee-master-plugin' ),
				'lift'      => __( 'Lifts up a little', 'atelier-irisee-master-plugin' ),
				'underline' => __( 'Underline', 'atelier-irisee-master-plugin' ),
				'none'      => __( 'Nothing', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'cards', __( 'Product and choice cards', 'atelier-irisee-master-plugin' ) );
		self::number( 'c_radius', __( 'Rounded corners', 'atelier-irisee-master-plugin' ), 0, 60, 'px' );
		self::choice(
			'c_style',
			__( 'Border', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Double gold line (standard)', 'atelier-irisee-master-plugin' ),
				'single' => __( 'Single line', 'atelier-irisee-master-plugin' ),
				'none'   => __( 'No border', 'atelier-irisee-master-plugin' ),
			)
		);
		self::number( 'c_width', __( 'Border width', 'atelier-irisee-master-plugin' ), 0, 10, 'px' );
		echo '<p><button type="button" class="button-link" data-clear="c_radius,c_width">' . esc_html( $standard ) . '</button></p>';
		echo '</fieldset>';

		$group( 'shadows', __( 'Shadows', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'shadow',
			__( 'Shadow under cards', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'None (standard)', 'atelier-irisee-master-plugin' ),
				'soft'   => __( 'Soft', 'atelier-irisee-master-plugin' ),
				'strong' => __( 'Stronger', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'background', __( 'Page background', 'atelier-irisee-master-plugin' ) );
		self::color( 'page_bg', __( 'Colour', 'atelier-irisee-master-plugin' ) );
		self::color( 'page_bg2', __( 'Second colour (gradient to the bottom)', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'page_img', __( 'Background picture', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'page_fixed', __( 'The picture stays in place while scrolling', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'width', __( 'Width (0 = standard)', 'atelier-irisee-master-plugin' ) );
		self::number( 'max_width', __( 'Widest shop content', 'atelier-irisee-master-plugin' ), 0, 2000, 'px' );
		self::number( 'gutter', __( 'Side margin on phones', 'atelier-irisee-master-plugin' ), 0, 48, 'px' );
		echo '</fieldset>';

		$group( 'lines', __( 'Gold lines', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'line',
			__( 'Style', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Solid (standard)', 'atelier-irisee-master-plugin' ),
				'dashed' => __( 'Stitched (dashes)', 'atelier-irisee-master-plugin' ),
				'dotted' => __( 'Dotted', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'effects', __( 'Sparkles', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nosparkle', __( 'No sparkles and twinkling stars', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'icons', __( 'Icons', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'icon_line',
			__( 'Line thickness', 'atelier-irisee-master-plugin' ),
			array(
				''     => $standard,
				'thin' => __( 'Thin', 'atelier-irisee-master-plugin' ),
				'bold' => __( 'Bold', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'loading', __( 'While something loads', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'loader',
			__( 'Show', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'The word "Loading…" (standard)', 'atelier-irisee-master-plugin' ),
				'ring' => __( 'A turning gold ring', 'atelier-irisee-master-plugin' ),
				'dots' => __( 'Moving dots', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'focus', __( 'Keyboard focus (accessibility)', 'atelier-irisee-master-plugin' ) );
		self::color( 'focus_color', __( 'Colour of the outline', 'atelier-irisee-master-plugin' ) );
		self::number( 'focus_width', __( 'Thickness', 'atelier-irisee-master-plugin' ), 0, 6, 'px' );
		echo '<button type="button" class="button-link" data-clear="focus_width">' . esc_html( $standard ) . '</button>';
		echo '</fieldset>';

		$group( 'favicon', __( 'Browser tab icon', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'favicon', __( 'Choose a square picture', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The small icon in the browser tab and on phone home screens. It changes when you publish.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		// Header, menu, tab bar, search (phase 3).
		$field = function ( $key, $label, $type = 'text', $placeholder = '' ) {
			if ( 'longtext' === $type ) {
				echo '<label class="aimp-ds-text-field aimp-ds-long"><span>' . esc_html( $label ) . '</span><textarea data-key="' . esc_attr( $key ) . '" rows="3" placeholder="' . esc_attr( $placeholder ) . '"></textarea></label>';
				return;
			}
			echo '<label class="aimp-ds-text-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" data-key="' . esc_attr( $key ) . '" placeholder="' . esc_attr( $placeholder ) . '"></label>';
		};

		$group( 'header_opts', __( 'Header behaviour', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nosticky', __( 'Scrolls away with the page (does not stay on top)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'noshrink', __( 'Stays the same size while scrolling', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nolabels', __( 'No words under the icons', 'atelier-irisee-master-plugin' ) );
		self::color( 'badge_bg', __( 'Number on icons: background', 'atelier-irisee-master-plugin' ) );
		self::color( 'badge_fg', __( 'Number on icons: text', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'icons_opts', __( 'Which icons, in which order', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Place 1 = first. 0 = the standard place.', 'atelier-irisee-master-plugin' ) . '</p>';
		foreach ( self::header_icons() as $icon => $label ) {
			echo '<div class="aimp-ds-icon-row"><strong>' . esc_html( $label ) . '</strong>';
			self::check_box( 'ic_hide_' . $icon, __( 'Hide on computers', 'atelier-irisee-master-plugin' ) );
			self::check_box( 'ic_phide_' . $icon, __( 'Hide on phones', 'atelier-irisee-master-plugin' ) );
			self::number( 'ic_order_' . $icon, __( 'Place', 'atelier-irisee-master-plugin' ), 0, 6, '' );
			echo '</div>';
		}
		echo '</fieldset>';

		$group( 'menubar_opts', __( '"Menu" bar', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'mb_align',
			__( 'Position', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Left (standard)', 'atelier-irisee-master-plugin' ),
				'center' => __( 'Centre', 'atelier-irisee-master-plugin' ),
				'right'  => __( 'Right', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'side_opts', __( 'Side menu', 'atelier-irisee-master-plugin' ) );
		self::number( 'sm_width', __( 'Width (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 640, 'px' );
		self::color( 'sm_bg', __( 'Background', 'atelier-irisee-master-plugin' ) );
		self::color( 'sm_text', __( 'Text colour', 'atelier-irisee-master-plugin' ) );
		self::number( 'sm_size', __( 'Text size (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 28, 'px' );
		self::choice(
			'sm_open',
			__( 'Dropdowns when the menu opens', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( '"All products" open (standard)', 'atelier-irisee-master-plugin' ),
				'none' => __( 'All closed', 'atelier-irisee-master-plugin' ),
				'all'  => __( 'All open', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '<p class="aimp-ds-sub">' . esc_html__( 'Automatic sub-items', 'atelier-irisee-master-plugin' ) . '</p>';
		self::check_box( 'sm_nohab', __( 'No automatic sub-items under Haberdashery', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'sm_fabrics', __( 'Fabric categories under Fabrics', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'sm_patterns', __( 'Pattern categories under Patterns', 'atelier-irisee-master-plugin' ) );
		echo '<p class="aimp-ds-sub">' . esc_html__( 'Under the menu', 'atelier-irisee-master-plugin' ) . '</p>';
		self::picture_field( 'sm_img', __( 'A picture', 'atelier-irisee-master-plugin' ) );
		$field( 'sm_contact', __( 'Contact details', 'atelier-irisee-master-plugin' ), 'longtext' );
		self::check_box( 'sm_socials', __( 'Social media icons (from the footer settings)', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'info_opts', __( 'Gold info bar', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The texts themselves are under Settings → Site look.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::number( 'ib_speed', __( 'Seconds per text on phones (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 20, 's' );
		self::choice(
			'ib_where',
			__( 'Show on', 'atelier-irisee-master-plugin' ),
			array(
				''           => __( 'Every page (standard)', 'atelier-irisee-master-plugin' ),
				'home'       => __( 'The home page only', 'atelier-irisee-master-plugin' ),
				'nocheckout' => __( 'Every page except cart and checkout', 'atelier-irisee-master-plugin' ),
			)
		);
		$field( 'ib_links', __( 'Links (one address per line, in the order of the texts; an empty line = no link)', 'atelier-irisee-master-plugin' ), 'longtext', 'https://' );
		echo '</fieldset>';

		$group( 'tabbar_opts', __( 'Tabs', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_nohome', __( 'No Home tab', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_noshop', __( 'No Shop tab', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_nokit', __( 'No Customizer tab', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_search', __( 'A Search tab', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_fav', __( 'A Favourites tab', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_cart', __( 'A Cart tab', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_account', __( 'An Account tab', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'tb_nolabels', __( 'Icons only, no words', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'lang_opts', __( 'Language switch', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'lang_style',
			__( 'Show', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'The flag (standard)', 'atelier-irisee-master-plugin' ),
				'code' => __( 'Flag and code (NL)', 'atelier-irisee-master-plugin' ),
				'name' => __( 'Flag and name (Nederlands)', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'search_opts', __( 'Search results', 'atelier-irisee-master-plugin' ) );
		self::number( 's_count', __( 'Number of results (0 = standard 8)', 'atelier-irisee-master-plugin' ), 0, 30, '' );
		self::check_box( 's_nofab', __( 'Leave out fabrics', 'atelier-irisee-master-plugin' ) );
		self::check_box( 's_nopat', __( 'Leave out patterns', 'atelier-irisee-master-plugin' ) );
		self::check_box( 's_nohab', __( 'Leave out haberdashery', 'atelier-irisee-master-plugin' ) );
		self::check_box( 's_noother', __( 'Leave out other products (gift cards, …)', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		// The hero (phase 3).
		$group( 'hero_media', __( 'Pictures, video and slides', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'h_img', __( 'Picture for computers', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'h_pimg', __( 'Picture for phones', 'atelier-irisee-master-plugin' ) );
		self::number( 'h_fx', __( 'Focal point left → right', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		self::number( 'h_fy', __( 'Focal point top → bottom', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		echo '<p><button type="button" class="button-link" data-clear="h_fx,h_fy">' . esc_html( $standard ) . '</button></p>';
		printf(
			'<div class="aimp-ds-image" data-image="h_video"><span class="aimp-ds-file-name"></span><button type="button" class="button aimp-ds-image-choose" data-image-key="h_video" data-media="video">%1$s</button><button type="button" class="button-link" data-clear="h_video">%2$s</button></div>',
			esc_html__( 'A video instead of the picture (MP4, short, without sound)', 'atelier-irisee-master-plugin' ),
			esc_html( $standard )
		);
		printf(
			'<div class="aimp-ds-image" data-image="h_slides" data-multiple="1"><span class="aimp-ds-image-list"></span><button type="button" class="button aimp-ds-image-choose" data-image-key="h_slides" data-media="images">%1$s</button><button type="button" class="button-link" data-clear="h_slides">%2$s</button></div>',
			esc_html__( 'Slides: more pictures that take turns (up to 6)', 'atelier-irisee-master-plugin' ),
			esc_html( $standard )
		);
		self::number( 'h_interval', __( 'Seconds per slide (0 = standard 6)', 'atelier-irisee-master-plugin' ), 0, 20, 's' );
		echo '</fieldset>';

		$group( 'hero_tint', __( 'Filter over the picture', 'atelier-irisee-master-plugin' ) );
		self::color( 'h_tint', __( 'Colour (standard gold)', 'atelier-irisee-master-plugin' ) );
		self::number( 'h_strength', __( 'Strength', 'atelier-irisee-master-plugin' ), 0, 90, '%' );
		echo '<button type="button" class="button-link" data-clear="h_strength">' . esc_html( $standard ) . '</button>';
		echo '</fieldset>';

		$places = array(
			''       => $standard,
			'center' => __( 'Centre', 'atelier-irisee-master-plugin' ),
			'right'  => __( 'Right', 'atelier-irisee-master-plugin' ),
		);
		$rows   = array(
			''       => $standard,
			'top'    => __( 'Top', 'atelier-irisee-master-plugin' ),
			'bottom' => __( 'Bottom', 'atelier-irisee-master-plugin' ),
		);
		$group( 'hero_layout', __( 'Height and place of the text', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'h_height',
			__( 'Height', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Full screen (standard)', 'atelier-irisee-master-plugin' ),
				'large'  => __( 'Three quarters of the screen', 'atelier-irisee-master-plugin' ),
				'medium' => __( 'Half the screen', 'atelier-irisee-master-plugin' ),
			)
		);
		$sub( $computer );
		self::choice( 'h_x', __( 'Left / right', 'atelier-irisee-master-plugin' ), $places );
		self::choice( 'h_y', __( 'Up / down', 'atelier-irisee-master-plugin' ), $rows );
		$sub( $phone );
		self::choice(
			'ph_x',
			__( 'Left / right', 'atelier-irisee-master-plugin' ),
			array(
				''      => $standard,
				'left'  => __( 'Left', 'atelier-irisee-master-plugin' ),
				'right' => __( 'Right', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice( 'ph_y', __( 'Up / down', 'atelier-irisee-master-plugin' ), $rows );
		echo '</fieldset>';

		$group( 'hero_buttons', __( 'Buttons', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Their texts can be changed in the Texts tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::check_box( 'hb1_off', __( 'No first button ("Make your sewing kit")', 'atelier-irisee-master-plugin' ) );
		$field( 'hb1_url', __( 'First button goes to', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
		self::check_box( 'hb2_off', __( 'No second button ("Discover the shop")', 'atelier-irisee-master-plugin' ) );
		$field( 'hb2_url', __( 'Second button goes to', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
		$field( 'hb3_text', __( 'Third button: text (NL | FR | EN)', 'atelier-irisee-master-plugin' ), 'text', 'Over ons | À propos | About us' );
		$field( 'hb3_url', __( 'Third button goes to', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
		echo '</fieldset>';

		$group( 'arrow_opts', __( 'Where the arrow goes', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'ha_target',
			__( 'To', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'The kit steps bar, else the next part (standard)', 'atelier-irisee-master-plugin' ),
				'next'   => __( 'Always the next part of the page', 'atelier-irisee-master-plugin' ),
				'anchor' => __( 'A part with a #name (below)', 'atelier-irisee-master-plugin' ),
			)
		);
		$field( 'ha_anchor', __( '#name of that part', 'atelier-irisee-master-plugin' ), 'text', '#shop' );
		echo '</fieldset>';

		// The kit steps bar and the mosaic (phase 4).
		$drawings = array(
			''        => $standard,
			'pattern' => __( 'Pattern with scissors', 'atelier-irisee-master-plugin' ),
			'fabric'  => __( 'Fabric roll with tape measure', 'atelier-irisee-master-plugin' ),
			'notions' => __( 'Buttons and zip', 'atelier-irisee-master-plugin' ),
			'kit'     => __( 'Gift box', 'atelier-irisee-master-plugin' ),
			'sew'     => __( 'Spool and needle', 'atelier-irisee-master-plugin' ),
		);
		$group( 'ks_opts', __( 'Steps, drawings, thread and button', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The texts of the steps are under Settings → Kit steps bar; the fifth step\'s texts in the Texts tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::check_box( 'ks_hide_2', __( 'Leave out step 2 (fabric)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ks_hide_3', __( 'Leave out step 3 (haberdashery)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ks_step5', __( 'Add a fifth step ("Start sewing")', 'atelier-irisee-master-plugin' ) );
		for ( $n = 1; $n <= 5; $n++ ) {
			/* translators: %d: step number */
			echo '<p class="aimp-ds-sub">' . esc_html( sprintf( __( 'Step %d', 'atelier-irisee-master-plugin' ), $n ) ) . '</p>';
			self::choice( 'ks_icon_' . $n, __( 'Drawing', 'atelier-irisee-master-plugin' ), $drawings );
			self::picture_field( 'ks_img_' . $n, __( 'Or your own picture', 'atelier-irisee-master-plugin' ) );
		}
		echo '<p class="aimp-ds-sub">' . esc_html__( 'Needle and thread', 'atelier-irisee-master-plugin' ) . '</p>';
		self::color( 'ks_thread', __( 'Thread colour', 'atelier-irisee-master-plugin' ) );
		self::number( 'ks_width', __( 'Thread thickness', 'atelier-irisee-master-plugin' ), 1, 8, '' );
		echo '<button type="button" class="button-link" data-clear="ks_width">' . esc_html( $standard ) . '</button>';
		self::number( 'ks_speed', __( 'Needle speed', 'atelier-irisee-master-plugin' ), 25, 400, '%' );
		self::check_box( 'ks_noloops', __( 'A smooth thread without loops', 'atelier-irisee-master-plugin' ) );
		echo '<p class="aimp-ds-sub">' . esc_html__( 'Button', 'atelier-irisee-master-plugin' ) . '</p>';
		$field( 'ks_url', __( 'The button goes to (standard: the customizer)', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
		self::check_box( 'ks_nosparkle', __( 'No sparkle on the button', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ks_nopopup', __( 'Do not open the steps as a pop-up on the first visit of the customizer', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'mosaic_opts', __( 'Products, layout and captions', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'mos_source',
			__( 'Products', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'As in Settings → Mosaic (standard)', 'atelier-irisee-master-plugin' ),
				'newest' => __( 'The newest', 'atelier-irisee-master-plugin' ),
				'sale'   => __( 'On sale', 'atelier-irisee-master-plugin' ),
				'random' => __( 'Random (changes every hour)', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice(
			'mos_layout',
			__( 'Layout', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'Centre stage (standard)', 'atelier-irisee-master-plugin' ),
				'grid' => __( 'Even grid', 'atelier-irisee-master-plugin' ),
				'row'  => __( 'One row to scroll sideways', 'atelier-irisee-master-plugin' ),
			)
		);
		self::number( 'mos_gap', __( 'Space between pictures', 'atelier-irisee-master-plugin' ), 0, 40, 'px' );
		echo '<button type="button" class="button-link" data-clear="mos_gap">' . esc_html( $standard ) . '</button>';
		self::check_box( 'mos_nocaption', __( 'No names and prices', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'mos_noprice', __( 'Names without prices', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'mos_hover',
			__( 'When the mouse is on a picture', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'The picture zooms in (standard)', 'atelier-irisee-master-plugin' ),
				'none' => __( 'Nothing', 'atelier-irisee-master-plugin' ),
				'lift' => __( 'Lifts up a little', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		// Shop pages (phase 5).
		$group( 'shop_grid', __( 'Products per row (0 = standard)', 'atelier-irisee-master-plugin' ) );
		self::number( 'cols', $computer, 0, 6, '' );
		self::number( 'tcols', __( 'Tablet', 'atelier-irisee-master-plugin' ), 0, 6, '' );
		self::number( 'pcols', $phone, 0, 3, '' );
		echo '</fieldset>';

		$group( 'shop_cards', __( 'What a product card shows', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'noprice', __( 'No price', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nounit', __( 'No unit after the price ("per 10 cm")', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nosub', __( 'No line under the name (e.g. the diameter)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'brand', __( 'The brand under the name', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nofav', __( 'No favourite heart', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'ratio',
			__( 'Picture shape', 'atelier-irisee-master-plugin' ),
			array(
				''          => $standard,
				'square'    => __( 'Square', 'atelier-irisee-master-plugin' ),
				'portrait'  => __( 'Upright (3:4)', 'atelier-irisee-master-plugin' ),
				'landscape' => __( 'Wide (4:3)', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'zoom_in', __( 'The picture zooms in under the mouse', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'shop_tools', __( 'Sorting, filters and pages', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'sort_default',
			__( 'Sorted at first by', 'atelier-irisee-master-plugin' ),
			array(
				''           => __( 'Recommended (standard)', 'atelier-irisee-master-plugin' ),
				'newest'     => __( 'Newest', 'atelier-irisee-master-plugin' ),
				'price_asc'  => __( 'Price, low to high', 'atelier-irisee-master-plugin' ),
				'price_desc' => __( 'Price, high to low', 'atelier-irisee-master-plugin' ),
				'name_asc'   => __( 'Name, A to Z', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'no_sort_name', __( 'No sorting by name', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'no_sort_price', __( 'No sorting by price', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'no_sort_newest', __( 'No sorting by newest', 'atelier-irisee-master-plugin' ) );
		echo '<p class="aimp-ds-sub">' . esc_html__( 'Filters', 'atelier-irisee-master-plugin' ) . '</p>';
		self::check_box( 'f_nosearch', __( 'No search field', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'f_nocat', __( 'No category filter', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'f_noprice', __( 'No price filter', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'f_noattrs', __( 'No colour/material filters', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'f_nostock', __( 'No "in stock only" filter', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'f_closed', __( 'Every filter starts folded', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'f_hidden', __( 'The filter column starts closed on computers', 'atelier-irisee-master-plugin' ) );
		echo '<p class="aimp-ds-sub">' . esc_html__( 'More products', 'atelier-irisee-master-plugin' ) . '</p>';
		self::choice(
			'paging',
			__( 'Show more products with', 'atelier-irisee-master-plugin' ),
			array(
				''         => __( 'Page numbers (standard)', 'atelier-irisee-master-plugin' ),
				'more'     => __( 'A "Show more" button', 'atelier-irisee-master-plugin' ),
				'infinite' => __( 'Loading by itself while scrolling', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'noquick', __( 'A click on a product opens its page (no quick view)', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'shop_badges', __( 'Badges on product cards', 'atelier-irisee-master-plugin' ) );
		self::number( 'b_new', __( '"New" for products younger than (0 = no badge)', 'atelier-irisee-master-plugin' ), 0, 90, __( 'days', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'b_sale', __( '"Sale" on products with a sale price', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'b_low', __( '"Almost sold out" when the stock is low', 'atelier-irisee-master-plugin' ) );
		self::color( 'badge_bg', __( 'Badge colour', 'atelier-irisee-master-plugin' ) );
		self::color( 'badge_fg', __( 'Badge text colour', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The badge texts can be changed in the Texts tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'shop_cats', __( 'Category pages', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cat_banner', __( 'A banner with the category\'s picture, name and description above the products', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cat_text', __( 'Only the description above the products (no picture)', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Shown when one category is chosen. The picture and the description are those of the category itself (Products → Categories).', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$sections = array(
			'configurator' => __( 'Sewing project kits', 'atelier-irisee-master-plugin' ),
			'fabrics'      => __( 'Fabrics', 'atelier-irisee-master-plugin' ),
			'patterns'     => __( 'Patterns', 'atelier-irisee-master-plugin' ),
			'haberdashery' => __( 'Haberdashery', 'atelier-irisee-master-plugin' ),
			'giftcards'    => __( 'Gift cards', 'atelier-irisee-master-plugin' ),
		);
		$group( 'overview_opts', __( 'Overview cards (All products page)', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'ov_hero',
			__( 'Large card', 'atelier-irisee-master-plugin' ),
			array(
				''             => __( 'Sewing project kits (standard)', 'atelier-irisee-master-plugin' ),
				'none'         => __( 'No large card', 'atelier-irisee-master-plugin' ),
				'fabrics'      => $sections['fabrics'],
				'patterns'     => $sections['patterns'],
				'haberdashery' => $sections['haberdashery'],
				'giftcards'    => $sections['giftcards'],
			)
		);
		$sub( __( 'Cards per row (0 = standard)', 'atelier-irisee-master-plugin' ) );
		self::number( 'ov_cols', $computer, 0, 6, '' );
		self::number( 'ov_tcols', __( 'Tablet', 'atelier-irisee-master-plugin' ), 0, 4, '' );
		self::number( 'ov_pcols', $phone, 0, 3, '' );
		echo '<p class="description">' . esc_html__( 'Per card: its place (1 = first, 0 = standard) and a line under its name (NL | FR | EN). The names themselves are in the Texts tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		foreach ( $sections as $section => $label ) {
			echo '<div class="aimp-ds-icon-row"><strong>' . esc_html( $label ) . '</strong>';
			self::number( 'ov_order_' . $section, __( 'Place', 'atelier-irisee-master-plugin' ), 0, 6, '' );
			$field( 'ov_sub_' . $section, __( 'Line under the name', 'atelier-irisee-master-plugin' ) );
			echo '</div>';
		}
		echo '</fieldset>';

		// Product page (phase 6).
		$blocks     = self::product_blocks();
		$order_list = function ( $key, $label ) use ( $blocks, $standard ) {
			echo '<div class="aimp-ds-order-field"><p class="aimp-ds-sub">' . esc_html( $label ) . '</p><ol class="aimp-ds-order" data-order="' . esc_attr( $key ) . '">';
			$n = 0;
			foreach ( $blocks as $block => $name ) {
				echo '<li draggable="true" data-block="' . esc_attr( $block ) . '" data-std="' . esc_attr( $n++ ) . '"><span class="aimp-ds-order-grip" aria-hidden="true">⋮⋮</span><span class="aimp-ds-order-name">' . esc_html( $name ) . '</span>';
				echo '<button type="button" class="button-link" data-move="-1" aria-label="' . esc_attr__( 'Up', 'atelier-irisee-master-plugin' ) . '">↑</button><button type="button" class="button-link" data-move="1" aria-label="' . esc_attr__( 'Down', 'atelier-irisee-master-plugin' ) . '">↓</button></li>';
			}
			echo '</ol><button type="button" class="button-link" data-clear="' . esc_attr( $key ) . '">' . esc_html( $standard ) . '</button></div>';
		};
		$group( 'pp_blocks', __( 'Blocks under the pictures', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Drag the blocks (or use the arrows) to put them in order. A block only shows on products that have it.', 'atelier-irisee-master-plugin' ) . '</p>';
		$order_list( 'pp_order', $computer );
		$order_list( 'pp_porder', $phone );
		$sub( __( 'Hide a block', 'atelier-irisee-master-plugin' ) );
		foreach ( $blocks as $block => $name ) {
			echo '<div class="aimp-ds-icon-row"><strong>' . esc_html( $name ) . '</strong>';
			self::check_box( 'pp_hide_' . $block, __( 'Hide on computers', 'atelier-irisee-master-plugin' ) );
			self::check_box( 'pp_phide_' . $block, __( 'Hide on phones', 'atelier-irisee-master-plugin' ) );
			echo '</div>';
		}
		echo '</fieldset>';

		$group( 'pp_gallery', __( 'Product pictures', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'g_thumbs',
			__( 'Small pictures (computers)', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'Under the picture (standard)', 'atelier-irisee-master-plugin' ),
				'left' => __( 'Left of the picture', 'atelier-irisee-master-plugin' ),
				'none' => __( 'None, dots under the picture', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice(
			'g_ratio',
			__( 'Picture shape', 'atelier-irisee-master-plugin' ),
			array(
				''          => $standard,
				'square'    => __( 'Square', 'atelier-irisee-master-plugin' ),
				'portrait'  => __( 'Upright (3:4)', 'atelier-irisee-master-plugin' ),
				'landscape' => __( 'Wide (4:3)', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice(
			'g_split',
			__( 'Width of the picture (large screens)', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Half (standard)', 'atelier-irisee-master-plugin' ),
				'wide'   => __( 'Wider than the information', 'atelier-irisee-master-plugin' ),
				'narrow' => __( 'Narrower than the information', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'g_right', __( 'The picture on the right (large screens)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'g_nozoom', __( 'No zoom under the mouse', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'pp_buy', __( 'Buy bar', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'pp_sticky',
			__( 'Slim buy bar at the bottom once the buy bar has scrolled away', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'On phones (standard)', 'atelier-irisee-master-plugin' ),
				'all'  => __( 'On every screen', 'atelier-irisee-master-plugin' ),
				'none' => __( 'Never', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'pp_notrust', __( 'No delivery line under the buy bar', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'pp_nolow', __( 'No "Only … left"', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'pp_noalert', __( 'No "Email me when it\'s back" on sold-out products', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'pp_share', __( 'Share buttons (WhatsApp, Facebook, Pinterest, email, copy link)', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The texts (delivery line, sold out, back in stock) can be changed in the Texts tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		// Customizer (phase 7).
		$lang_hint = __( 'NL | FR | EN', 'atelier-irisee-master-plugin' );
		$group( 'cz_steps', __( 'Steps', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'cz_progress',
			__( 'Step indicator', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'Numbered steps (standard)', 'atelier-irisee-master-plugin' ),
				'bar'  => __( 'Numbered steps with a gold progress bar', 'atelier-irisee-master-plugin' ),
				'none' => __( 'None', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'cz_nonum', __( 'No numbers in front of the step names', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cz_nohowto', __( 'No "How it works" button', 'atelier-irisee-master-plugin' ) );
		$sub( __( 'A line at the top of each step', 'atelier-irisee-master-plugin' ) );
		$field( 'cz_intro_pattern', __( 'Pattern', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		$field( 'cz_intro_fabric', __( 'Fabric', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		$field( 'cz_intro_notions', __( 'Haberdashery', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		$field( 'cz_intro_summary', __( 'Summary', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		echo '<p class="description">' . esc_html__( 'The step names themselves can be changed in the Texts tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'cz_cards', __( 'Product cards in the customizer', 'atelier-irisee-master-plugin' ) );
		$sub( __( 'Cards per row (0 = standard)', 'atelier-irisee-master-plugin' ) );
		self::number( 'cz_cols', $computer, 0, 6, '' );
		self::number( 'cz_pcols', $phone, 0, 3, '' );
		self::check_box( 'cz_noprice', __( 'No price', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cz_nosub', __( 'No line under the name (e.g. the diameter)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cz_nofav', __( 'No favourite heart', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'cz_ratio',
			__( 'Picture shape', 'atelier-irisee-master-plugin' ),
			array(
				''          => $standard,
				'square'    => __( 'Square', 'atelier-irisee-master-plugin' ),
				'portrait'  => __( 'Upright (3:4)', 'atelier-irisee-master-plugin' ),
				'landscape' => __( 'Wide (4:3)', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$notions    = self::notion_labels();
		$group( 'cz_flow', __( 'Haberdashery, size chart and details', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cz_nonotions', __( 'No haberdashery step (only pattern and fabric)', 'atelier-irisee-master-plugin' ) );
		echo '<div class="aimp-ds-order-field"><p class="aimp-ds-sub">' . esc_html__( 'Order of the haberdashery', 'atelier-irisee-master-plugin' ) . '</p><ol class="aimp-ds-order" data-order="cz_norder">';
		$n = 0;
		foreach ( $notions as $type => $name ) {
			echo '<li draggable="true" data-block="' . esc_attr( $type ) . '" data-std="' . esc_attr( $n++ ) . '"><span class="aimp-ds-order-grip" aria-hidden="true">⋮⋮</span><span class="aimp-ds-order-name">' . esc_html( $name ) . '</span>';
			echo '<button type="button" class="button-link" data-move="-1" aria-label="' . esc_attr__( 'Up', 'atelier-irisee-master-plugin' ) . '">↑</button><button type="button" class="button-link" data-move="1" aria-label="' . esc_attr__( 'Down', 'atelier-irisee-master-plugin' ) . '">↓</button></li>';
		}
		echo '</ol><button type="button" class="button-link" data-clear="cz_norder">' . esc_html( $standard ) . '</button></div>';
		echo '<p class="description">' . esc_html__( 'Every haberdashery choice stays optional; a pattern only shows the ones it needs.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::check_box( 'cz_nosizechart', __( 'No size chart under the sizes', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cz_nomeasure', __( 'No "How to measure" button', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'cz_sheet',
			__( 'Details on phones and tablets', 'atelier-irisee-master-plugin' ),
			array(
				''     => __( 'A sheet from the bottom (standard)', 'atelier-irisee-master-plugin' ),
				'full' => __( 'The whole screen', 'atelier-irisee-master-plugin' ),
			)
		);
		echo '</fieldset>';

		$group( 'cz_bar', __( 'Gold bar at the bottom (phones)', 'atelier-irisee-master-plugin' ) );
		self::color( 'cz_bar_bg', __( 'Background', 'atelier-irisee-master-plugin' ) );
		self::color( 'cz_bar_fg', __( 'Text colour', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cz_nosparkle', __( 'The next button does not sparkle', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'cz_summary', __( 'Summary', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'cz_discount',
			__( 'The kit discount', 'atelier-irisee-master-plugin' ),
			array(
				''      => __( 'Crossed-out prices on every line (standard)', 'atelier-irisee-master-plugin' ),
				'total' => __( 'Only at the total', 'atelier-irisee-master-plugin' ),
				'badge' => __( 'Only at the total, with a gold badge above the table', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'cz_nosave', __( 'No "Save this kit" button', 'atelier-irisee-master-plugin' ) );
		$field( 'cz_note', __( 'A line under the table', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		echo '</fieldset>';

		$group( 'pp_info', __( 'Information', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'pp_layout',
			__( 'The blocks under the pictures', 'atelier-irisee-master-plugin' ),
			array(
				''          => __( 'One under the other (standard)', 'atelier-irisee-master-plugin' ),
				'accordion' => __( 'As dropdowns (the first one open)', 'atelier-irisee-master-plugin' ),
				'tabs'      => __( 'As tabs', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'pp_brand_link', __( 'The brand links to all products of that brand', 'atelier-irisee-master-plugin' ) );
		self::number( 'pp_ideal_size', __( '"Ideal for" icons (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 120, 'px' );
		$sub( __( 'Specifications and washing: leave out', 'atelier-irisee-master-plugin' ) );
		foreach ( self::product_spec_subjects() as $subject => $name ) {
			self::check_box( 'pp_nospec_' . $subject, $name );
		}
		$sub( __( 'Product cards (patterns, fabrics, you may also like)', 'atelier-irisee-master-plugin' ) );
		self::number( 'pp_count', __( 'Fitting patterns and recommended fabrics shown (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 8, '' );
		self::number( 'pp_related', __( '"You may also like" products (0 = standard, 4)', 'atelier-irisee-master-plugin' ), 0, 12, '' );
		self::number( 'pp_cards', __( 'Cards per row on large screens (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 8, '' );
		$sub( __( 'Size chart and reviews', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'pp_nomeasure', __( 'No "How to measure" button', 'atelier-irisee-master-plugin' ) );
		self::color( 'pp_stars', __( 'Colour of the review stars', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';
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
							<button type="button" class="button-link aimp-ds-back">‹ <?php esc_html_e( 'All parts', 'atelier-irisee-master-plugin' ); ?></button>
							<div class="aimp-ds-edit-head">
								<strong class="aimp-ds-edit-name"></strong>
								<button type="button" class="button-link aimp-ds-reset-one"><?php esc_html_e( 'Reset this part', 'atelier-irisee-master-plugin' ); ?></button>
							</div>
							<?php self::render_groups(); ?>
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
		// Thumbnails of every chosen picture, and the file names of videos.
		$images = array();
		$files  = array();
		$groups = self::groups();
		$rules  = array();
		foreach ( $groups as $fields ) {
			$rules = array_merge( $rules, $fields );
		}
		foreach ( array( $draft, self::live() ) as $styles ) {
			foreach ( $styles as $row ) {
				foreach ( (array) $row as $key => $value ) {
					$rule = isset( $rules[ $key ] ) ? $rules[ $key ] : '';
					if ( 'image' === $rule || 'images' === $rule ) {
						foreach ( (array) $value as $id ) {
							$images[ (int) $id ] = (string) wp_get_attachment_image_url( (int) $id, 'large' );
						}
					} elseif ( 'video' === $rule ) {
						$files[ (int) $value ] = wp_basename( (string) get_attached_file( (int) $value ) );
					}
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
			// The "Whole site" CSS of the draft (made on the server; the parts' CSS is made in the studio itself).
			'siteCss'    => self::site_css( $draft ) . self::opts_css( $draft ),
			'draftTexts' => (object) self::draft_texts(),
			'liveTexts'  => (object) self::live_texts(),
			'images'     => (object) $images,
			'files'      => (object) $files,
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
		// The browser icon of the whole site (WordPress's own site icon).
		$site = self::site_row( self::live() );
		if ( ! empty( $site['favicon'] ) && (int) get_option( 'site_icon' ) !== (int) $site['favicon'] ) {
			update_option( 'site_icon', (int) $site['favicon'] );
		}
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
