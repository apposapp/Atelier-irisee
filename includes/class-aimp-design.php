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

	/** Up to this width a screen counts as a tablet (its own spacing; the rest as on computers). */
	const TABLET = 1024;

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
		// The studio uses the whole screen: no notices of WordPress or other plugins above it.
		add_action( 'in_admin_header', array( __CLASS__, 'quiet_screen' ), 1000 );
		// The studio's screen: room for the panel and the preview (admin-design.css hides the WordPress menu).
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		foreach ( array( 'css', 'save', 'publish', 'discard', 'restore', 'import', 'texts', 'translate', 'look_save', 'look_load', 'look_delete', 'look_plan' ) as $action ) {
			add_action( 'wp_ajax_aimp_design_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
		add_action( 'wp_head', array( __CLASS__, 'print_style' ), 100 );
		// The preview: which shop texts the page shows (a click on one opens it in the studio).
		add_action( 'template_redirect', array( __CLASS__, 'start_seen' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'print_seen' ), 1000 );
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
		$contact = __( 'Contact page', 'atelier-irisee-master-plugin' );
		$home    = __( 'Home page blocks', 'atelier-irisee-master-plugin' );
		$about   = __( 'About page', 'atelier-irisee-master-plugin' );
		$pagehead = __( 'Page header', 'atelier-irisee-master-plugin' );
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
			'page-header'     => array( $pagehead, __( 'Page header (whole)', 'atelier-irisee-master-plugin' ), '.aimp-page-header' ),
			'page-header-title' => array( $pagehead, __( 'Page header title', 'atelier-irisee-master-plugin' ), '.aimp-page-header-text' ),
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
			'giftcard-form'   => array( $product, __( 'Gift card choices', 'atelier-irisee-master-plugin' ), '.aimp-gc-form' ),
			'product-recommend' => array( $product, __( 'Recommended products', 'atelier-irisee-master-plugin' ), '.aimp-recommended' ),
			'customizer'      => array( $config, __( 'Customizer (whole)', 'atelier-irisee-master-plugin' ), '.aimp-configurator[data-aimp-configurator]' ),
			'config-steps'    => array( $config, __( 'Step indicator', 'atelier-irisee-master-plugin' ), '.aimp-configurator .aimp-steps' ),
			'config-title'    => array( $config, __( 'Details titles', 'atelier-irisee-master-plugin' ), '.aimp-details-title' ),
			'cart-page'       => array( $cart, __( 'Cart page (whole)', 'atelier-irisee-master-plugin' ), '.aimp-configurator.aimp-cart' ),
			'checkout-page'   => array( $cart, __( 'Checkout (whole)', 'atelier-irisee-master-plugin' ), '.aimp-configurator.aimp-checkout' ),
			'favorites-page'  => array( $cart, __( 'Favourites page (whole)', 'atelier-irisee-master-plugin' ), '.aimp-configurator.aimp-favorites' ),
			'cart-items'      => array( $cart, __( 'Cart items', 'atelier-irisee-master-plugin' ), '.aimp-cart-items' ),
			'cart-side'       => array( $cart, __( 'Cart totals', 'atelier-irisee-master-plugin' ), '.aimp-cart-side' ),
			'cart-coupon'     => array( $cart, __( 'Discount code field', 'atelier-irisee-master-plugin' ), '.aimp-cart-coupon' ),
			'cart-empty'      => array( $cart, __( 'Empty cart', 'atelier-irisee-master-plugin' ), '.aimp-cart-empty' ),
			'account-page'    => array( $account, __( 'My account (whole)', 'atelier-irisee-master-plugin' ), '.aimp-account:not(.aimp-account--guest)' ),
			'login-forms'     => array( $account, __( 'Log in and register', 'atelier-irisee-master-plugin' ), '.aimp-el' ),
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
			// The contact page and the home page blocks (AIMP_Contact, AIMP_Home_Blocks).
			'contact'         => array( $contact, __( 'Contact page (whole)', 'atelier-irisee-master-plugin' ), '.aimp-contact' ),
			'contact-head'    => array( $contact, __( 'Contact title and intro', 'atelier-irisee-master-plugin' ), '.aimp-contact-head' ),
			'contact-cards'   => array( $contact, __( 'Contact details', 'atelier-irisee-master-plugin' ), '.aimp-contact-cards' ),
			'contact-form'    => array( $contact, __( 'Contact form', 'atelier-irisee-master-plugin' ), '.aimp-contact-form-wrap' ),
			'faq'             => array( $contact, __( 'Questions and answers', 'atelier-irisee-master-plugin' ), '.aimp-faq' ),
			'block-title'     => array( $home, __( 'Block titles', 'atelier-irisee-master-plugin' ), '.aimp-block-title' ),
			'home-cats'       => array( $home, __( 'Shop by category', 'atelier-irisee-master-plugin' ), '.aimp-home-cats' ),
			'home-reviews'    => array( $home, __( 'Reviews', 'atelier-irisee-master-plugin' ), '.aimp-home-reviews' ),
			'home-maker'      => array( $home, __( 'Meet the maker', 'atelier-irisee-master-plugin' ), '.aimp-home-maker' ),
			'home-new'        => array( $home, __( 'New in', 'atelier-irisee-master-plugin' ), '.aimp-home-new' ),
			'home-newsletter' => array( $home, __( 'Newsletter block', 'atelier-irisee-master-plugin' ), '.aimp-home-newsletter' ),
			'home-faq'        => array( $home, __( 'Questions block', 'atelier-irisee-master-plugin' ), '.aimp-home-faq' ),
			'home-brands'     => array( $home, __( 'Brands', 'atelier-irisee-master-plugin' ), '.aimp-home-brands' ),
			'about'           => array( $about, __( 'About page (whole)', 'atelier-irisee-master-plugin' ), '.aimp-about' ),
			'about-head'      => array( $about, __( 'About title and intro', 'atelier-irisee-master-plugin' ), '.aimp-about-head' ),
			'about-chapter'   => array( $about, __( 'Story chapters', 'atelier-irisee-master-plugin' ), '.aimp-about-chapter' ),
			'about-values'    => array( $about, __( 'What we stand for', 'atelier-irisee-master-plugin' ), '.aimp-about-values' ),
			'about-closing'   => array( $about, __( 'Closing and signature', 'atelier-irisee-master-plugin' ), '.aimp-about-closing' ),
		);
		// Which settings a part has: every part can be moved, sized, spaced, hidden, coloured and calmed down; boxes
		// also get rounded corners, and parts with pictures a picture and focal point.
		$text    = array( 'move', 'size', 'space', 'hide', 'colors', 'typo', 'anim' );
		$box     = array_merge( $text, array( 'radius' ) );
		$picture = array_merge( $box, array( 'image' ) );
		$texts   = array( 'header-menu-bar', 'hero-title', 'hero-text', 'kit-steps-title', 'overview-hero', 'card-name', 'card-price', 'product-title', 'product-brand', 'product-subtitle', 'product-price', 'config-title', 'account-title', 'account-hello', 'footer-title', 'footer-bottom', 'hero-arrow', 'block-title', 'contact-head', 'about-head', 'page-header-title' );
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
			'footer'          => array( 'footer_opts' ),
			'newsletter'      => array( 'nl_opts' ),
			'contact'         => array( 'ct_opts' ),
			'faq'             => array( 'faq_opts' ),
			'home-cats'       => array( 'hc_opts' ),
			'home-reviews'    => array( 'rv_opts' ),
			'home-maker'      => array( 'mk_opts' ),
			'home-new'        => array( 'nw_opts' ),
			'home-newsletter' => array( 'hn_opts' ),
			'home-faq'        => array( 'fq_opts' ),
			'home-brands'     => array( 'br_opts' ),
			'about'           => array( 'ab_opts' ),
			'about-chapter'   => array( 'ab_chapters' ),
			'about-closing'   => array( 'ab_closing' ),
		) as $id => $more ) {
			if ( isset( $elements[ $id ] ) ) {
				$elements[ $id ]['controls'] = array_merge( $elements[ $id ]['controls'], $more );
			}
		}
		// The hero: its own pictures, video, slides, filter, place of the text and buttons.
		if ( isset( $elements['hero'] ) ) {
			$elements['hero']['controls'] = array( 'hero_media', 'hero_tint', 'hero_layout', 'hero_buttons', 'space', 'hide', 'anim' );
		}
		// The page header: the picture or video per page, the shared look, spacing.
		if ( isset( $elements['page-header'] ) ) {
			$elements['page-header']['controls'] = array( 'ph_page', 'ph_look', 'space', 'hide', 'anim' );
		}
		// The product page as a whole (phase 6): the order of its blocks, the pictures, the buy bar, the information.
		if ( isset( $elements['product-page'] ) ) {
			$elements['product-page']['controls'] = array( 'pp_blocks', 'pp_gallery', 'pp_buy', 'pp_info' );
		}
		// The customizer as a whole (phase 7): steps, cards, the haberdashery, the bar on phones, the summary.
		if ( isset( $elements['customizer'] ) ) {
			$elements['customizer']['controls'] = array( 'cz_steps', 'cz_cards', 'cz_flow', 'cz_bar', 'cz_summary' );
		}
		// Cart, checkout, account, login, favourites (phase 8).
		if ( isset( $elements['giftcard-form'] ) ) {
			$elements['giftcard-form']['controls'] = array( 'gc_opts', 'space', 'colors', 'radius' );
		}
		foreach ( array(
			'cart-page'      => 'cart_opts',
			'checkout-page'  => 'co_opts',
			'account-page'   => 'acc_opts',
			'login-forms'    => 'log_opts',
			'favorites-page' => 'fav_opts',
		) as $id => $group ) {
			if ( isset( $elements[ $id ] ) ) {
				$elements[ $id ]['controls'] = array( $group, 'space', 'colors', 'radius' );
			}
		}
		// Picture position (move and zoom): every part with pictures, also those with pictures of their own.
		foreach ( $elements as $id => $row ) {
			if ( in_array( 'image', $row['controls'], true ) || in_array( $id, array( 'hero', 'home-maker', 'about-chapter', 'about-closing', 'home-newsletter', 'home-brands', 'page-header' ), true ) ) {
				$elements[ $id ]['controls'][] = 'imgpos';
			}
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
			'site-notices' => array( __( 'Messages (added to cart, errors)', 'atelier-irisee-master-plugin' ), array( 'notice_opts' ) ),
			'site-emails'  => array( __( 'Emails', 'atelier-irisee-master-plugin' ), array( 'email_opts' ) ),
			'site-tech'    => array( __( 'SEO, speed and dashboard', 'atelier-irisee-master-plugin' ), array( 'seo_opts', 'speed_opts', 'admin_opts' ) ),
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
			'space'      => self::space_fields(),
			'hide'       => array(
				'hide'  => 'bool',
				'phide' => 'bool',
			),
			'colors'     => array(
				'color'  => 'color',
				'bg'     => 'color',
				'border' => 'color',
			),
			// Text style: size, weight, alignment, capitals (the part and the text in it).
			'typo'       => array(
				'fsize'   => array( 0, 120, 0 ),
				'fweight' => array( 'select', array( '', '300', '400', '600', '700' ) ),
				'talign'  => array( 'select', array( '', 'left', 'center', 'right' ) ),
				'tcase'   => array( 'select', array( '', 'upper', 'normal' ) ),
			),
			'radius'     => array( 'radius' => array( 0, 200, 0 ) ),
			'image'      => array(
				'img' => 'image',
				'fx'  => array( 0, 100, 50 ),
				'fy'  => array( 0, 100, 50 ),
			),
			// Picture position: moved (% of the picture) and zoomed, on computers and on phones ("p").
			'imgpos'     => array(
				'ix'     => array( -100, 100, 0 ),
				'iy'     => array( -100, 100, 0 ),
				'izoom'  => array( 50, 300, 100 ),
				'pix'    => array( -100, 100, 0 ),
				'piy'    => array( -100, 100, 0 ),
				'pizoom' => array( 50, 300, 100 ),
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
				'ha_speed'  => array( 300, 3000, 1200 ),
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
			// Footer, newsletter, gift cards, messages, emails (phase 9).
			'footer_opts' => self::footer_fields(),
			'nl_opts'     => array(
				'nl_popup'       => array( 0, 120, 0 ),
				'nl_popup_title' => 'text',
				'nl_wide'        => 'bool',
			),
			'gc_opts'     => array(
				'gc_cols'     => array( 0, 6, 0 ),
				'gc_chip'     => 'color',
				'gc_chip_fg'  => 'color',
			),
			'notice_opts' => array(
				'nt_time'   => array( 0, 20, 0 ),
				'nt_pos'    => array( 'select', array( '', 'top', 'bottom' ) ),
				'nt_bg'     => 'color',
				'nt_fg'     => 'color',
				'nt_err_bg' => 'color',
			),
			'email_opts'  => array(
				'em_gold'    => 'color',
				'em_brown'   => 'color',
				'em_bg'      => 'color',
				'em_logo'    => 'image',
				'em_noframe' => 'bool',
				'em_footer'  => 'text',
			),
			// SEO, speed and dashboard (phase 10, AIMP_Design_Tech).
			'seo_opts'    => array(
				'seo_noindex' => 'bool',
				'seo_og'      => 'bool',
				'seo_brand'   => 'bool',
			),
			'speed_opts'  => array(
				'sp_prefetch' => 'bool',
				'sp_noemoji'  => 'bool',
				'sp_noembed'  => 'bool',
			),
			'admin_opts'  => array(
				'ad_nobar' => 'bool',
			),
			// Cart, checkout, account, login, favourites (phase 8).
			'cart_opts'   => array(
				'cart_nocoupon'   => 'bool',
				'cart_noupdate'   => 'bool',
				'cart_notrust'    => 'bool',
				'cart_nopayments' => 'bool',
				'cart_nocontinue' => 'bool',
				'cart_nocross'    => 'bool',
				'cart_nopopular'  => 'bool',
				'cart_left'       => 'bool',
				'cart_nosticky'   => 'bool',
				'cart_thumb'      => array( 0, 160, 0 ),
			),
			'co_opts'     => array(
				'co_nosteps'    => 'bool',
				'co_left'       => 'bool',
				'co_closed'     => 'bool',
				'co_nocodes'    => 'bool',
				'co_nonotes'    => 'bool',
				'co_noaccount'  => 'bool',
				'co_thanks'     => 'text',
			),
			'acc_opts'    => self::account_fields(),
			'log_opts'    => array(
				'log_noimage' => 'bool',
				'log_nologo'  => 'bool',
				'log_width'   => array( 0, 1000, 0 ),
				'log_overlay' => 'color',
			),
			'fav_opts'    => array(
				'fav_cols'    => array( 0, 8, 0 ),
				'fav_pcols'   => array( 0, 3, 0 ),
				'fav_heart'   => 'color',
				'fav_notoast' => 'bool',
			),
			// The customizer (phase 7).
			'cz_steps'    => array(
				'cz_progress'      => array( 'select', array( '', 'bar', 'none' ) ),
				'cz_nonum'         => 'bool',
				'cz_intro_pattern' => 'text',
				'cz_intro_fabric'  => 'text',
				'cz_intro_lining'  => 'text',
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
			// The contact page and the home page blocks.
			'ct_opts'     => array(
				'ct_formleft'   => 'bool',
				'ct_nowhatsapp' => 'bool',
				'ct_noroute'    => 'bool',
				'ct_nosocials'  => 'bool',
				'ct_noorder'    => 'bool',
				'ct_noconsent'  => 'bool',
				'ct_nocopy'     => 'bool',
				'ct_nofaq'      => 'bool',
			),
			'faq_opts'    => array(
				'faq_open'   => 'bool',
				'faq_single' => 'bool',
			),
			'hc_opts'     => array( 'hc_notitle' => 'bool' ),
			'rv_opts'     => array(
				'rv_min'        => array( 1, 5, 4 ),
				'rv_count'      => array( 0, 24, 0 ),
				'rv_noproducts' => 'bool',
				'rv_noproduct'  => 'bool',
				'rv_noaverage'  => 'bool',
			),
			'mk_opts'     => array(
				'mk_img'   => 'image',
				'mk_img2'  => 'image',
				'mk_fx'    => array( 0, 100, -1 ),
				'mk_fy'    => array( 0, 100, -1 ),
				'mk_fx2'   => array( 0, 100, -1 ),
				'mk_fy2'   => array( 0, 100, -1 ),
				'mk_right' => 'bool',
				'mk_url'   => 'url',
			),
			'nw_opts'     => array(
				'nw_count'   => array( 0, 16, 0 ),
				'nw_type'    => array( 'select', array( '', 'fabrics', 'patterns', 'haberdashery' ) ),
				'nw_instock' => 'bool',
				'nw_url'     => 'url',
			),
			'hn_opts'     => array(
				'hn_img'     => 'image',
				'hn_nooffer' => 'bool',
				'hn_showsubs' => 'bool',
			),
			'fq_opts'     => array(
				'fq_count' => array( 0, 12, 0 ),
				'fq_url'   => 'url',
			),
			'br_opts'     => array_merge(
				array(
					'br_names' => 'bool',
					'br_gray'  => 'bool',
				),
				self::brand_fields()
			),
			'ab_opts'     => array(
				'ab_right'    => 'bool',
				'ab_novalues' => 'bool',
				'ab_noshop'   => 'bool',
				'ab_nokit'    => 'bool',
			),
			'ab_chapters' => self::about_chapter_fields(),
			// The page header: per page (ph_{page}_…) and the look of all of them.
			'ph_page'     => self::page_header_fields(),
			'ph_look'     => array(
				'ph_where'      => array( 'select', array( '', 'manual' ) ),
				'ph_noproducts' => 'bool',
				'ph_nocommerce' => 'bool',
				'ph_noother'    => 'bool',
				'ph_hideempty'  => 'bool',
				'ph_height'   => array( 'select', array( '', 'small', 'large', 'full' ) ),
				'ph_pheight'  => array( 'select', array( '', 'small', 'medium', 'large', 'full' ) ),
				'ph_align'    => array( 'select', array( '', 'left', 'bottomleft' ) ),
				'ph_tint'     => 'color',
				'ph_strength' => array( 0, 90, -1 ),
				'ph_color'    => 'color',
				'ph_size'     => array( 0, 120, 0 ),
				'ph_crumbs'   => 'bool',
			),
			'ab_closing'  => array( 'ab_sign_img' => 'image' ),
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
	 * Spacing like Divi: margin (mt, mr, mb, ml) and padding (padt, padr, padb, padl) for computers (no prefix),
	 * tablets ("t") and phones ("p"). "Standard" is outside the range, so 0 is a value of its own.
	 *
	 * @return array
	 */
	public static function space_fields() {
		$fields = array();
		foreach ( array( '', 't', 'p' ) as $prefix ) {
			foreach ( array( 'mt', 'mr', 'mb', 'ml' ) as $side ) {
				$fields[ $prefix . $side ] = array( -150, 300, -999 );
			}
			foreach ( array( 'padt', 'padr', 'padb', 'padl' ) as $side ) {
				$fields[ $prefix . $side ] = array( 0, 300, -1 );
			}
		}
		return $fields;
	}

	/**
	 * The footer columns: key => label (class aimp-footer-col--{key}).
	 *
	 * @return array
	 */
	public static function footer_columns() {
		return array(
			'contact'    => __( 'Contact details', 'atelier-irisee-master-plugin' ),
			'account'    => __( 'My account', 'atelier-irisee-master-plugin' ),
			'service'    => __( 'Customer service', 'atelier-irisee-master-plugin' ),
			'newsletter' => __( 'Newsletter', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * The footer: columns per row, centred, a "back to top" button; per column hidden and its place.
	 *
	 * @return array
	 */
	private static function footer_fields() {
		$fields = array(
			'ft_cols'  => array( 0, 4, 0 ),
			'ft_align' => array( 'select', array( '', 'center' ) ),
			'ft_top'   => 'bool',
		);
		foreach ( array_keys( self::footer_columns() ) as $col ) {
			$fields[ 'ft_hide_' . $col ]  = 'bool';
			$fields[ 'ft_order_' . $col ] = array( 0, 4, 0 );
		}
		return $fields;
	}

	/**
	 * My account: the tab that opens first, tabs hidden, the tabs above the page, no "Hello".
	 *
	 * @return array
	 */
	private static function account_fields() {
		$tabs   = class_exists( 'AIMP_Account' ) ? array_keys( AIMP_Account::tabs() ) : array( 'personal', 'orders', 'refund', 'giftcards' );
		$fields = array(
			'acc_first'   => array( 'select', array_merge( array( '' ), array_values( array_diff( $tabs, array( 'personal' ) ) ) ) ),
			'acc_top'     => 'bool',
			'acc_nohello' => 'bool',
		);
		foreach ( $tabs as $tab ) {
			$fields[ 'acc_hide_' . $tab ] = 'bool';
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
		foreach ( array( 'cz_intro_pattern', 'cz_intro_fabric', 'cz_intro_lining', 'cz_intro_notions', 'cz_intro_summary', 'cz_note' ) as $key ) {
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
			'lining'        => __( 'Lining', 'atelier-irisee-master-plugin' ),
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
	 * The page header per page that has one: the kind (picture + title, or the title in the picture), pictures and
	 * videos for computers and phones, the focal point, another title and a line under it.
	 *
	 * @return array
	 */
	private static function page_header_fields() {
		$fields = array();
		// Per page, and once for all product pages and once for the other pages (shop categories, blog, search, …).
		foreach ( array_merge( array_keys( class_exists( 'AIMP_Page_Header' ) ? AIMP_Page_Header::pages() : array() ), array( 'product', 'other' ) ) as $id ) {
			$p                     = 'ph_' . $id . '_';
			if ( is_int( $id ) ) {
				$fields[ $p . 'off' ] = 'bool';
			}
			$fields[ $p . 'mode' ]   = array( 'select', array( '', 'baked' ) );
			$fields[ $p . 'img' ]    = 'image';
			$fields[ $p . 'pimg' ]   = 'image';
			$fields[ $p . 'video' ]  = 'video';
			$fields[ $p . 'pvideo' ] = 'video';
			$fields[ $p . 'fx' ]     = array( 0, 100, -1 );
			$fields[ $p . 'fy' ]     = array( 0, 100, -1 );
			if ( 'product' !== $id ) {
				$fields[ $p . 'title' ] = 'text';
			}
			$fields[ $p . 'sub' ]    = 'text';
		}
		return $fields;
	}

	/**
	 * The brands with products: term id => name (a picture per brand in the Brands block).
	 *
	 * @return array
	 */
	public static function brands() {
		static $brands = null;
		if ( null !== $brands ) {
			return $brands;
		}
		$brands = array();
		if ( taxonomy_exists( 'product_brand' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_brand',
					'hide_empty' => true,
					'orderby'    => 'name',
				)
			);
			foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
				$brands[ (int) $term->term_id ] = $term->name;
			}
		}
		return $brands;
	}

	/**
	 * A picture per brand (br_img_{term id}).
	 *
	 * @return array
	 */
	private static function brand_fields() {
		$fields = array();
		foreach ( array_keys( self::brands() ) as $id ) {
			$fields[ 'br_img_' . $id ] = 'image';
		}
		return $fields;
	}

	/**
	 * The story chapters of the About page, per chapter: a picture with its focal point and shape, the address of its
	 * button, and hidden.
	 *
	 * @return array
	 */
	private static function about_chapter_fields() {
		$fields = array( 'ab_line' => 'bool' );
		for ( $n = 1; $n <= 4; $n++ ) {
			$fields[ 'ab_img_' . $n ]   = 'image';
			$fields[ 'ab_fx_' . $n ]    = array( 0, 100, -1 );
			$fields[ 'ab_fy_' . $n ]    = array( 0, 100, -1 );
			$fields[ 'ab_shape_' . $n ] = array( 'select', array( '', 'arch', 'round', 'square' ) );
			$fields[ 'ab_url_' . $n ]   = 'url';
			$fields[ 'ab_hide_' . $n ]  = 'bool';
		}
		return $fields;
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
		return array( 'side_opts', 'info_opts', 'tabbar_opts', 'lang_opts', 'hero_buttons', 'arrow_opts', 'favicon', 'shop_tools', 'shop_cats', 'overview_opts', 'cz_steps', 'cz_flow', 'cz_summary', 'co_opts', 'acc_opts', 'nl_opts', 'notice_opts', 'email_opts', 'seo_opts', 'speed_opts', 'admin_opts', 'ct_opts', 'faq_opts', 'hc_opts', 'rv_opts', 'mk_opts', 'nw_opts', 'hn_opts', 'fq_opts', 'br_opts', 'ab_opts', 'ab_chapters', 'ab_closing', 'ph_page', 'ph_look' );
	}

	/**
	 * Settings made on the server when the page is built: the preview loads again after a change. The groups of
	 * reload_groups() whole, and from other groups only the settings that are not just CSS (a focal point or a colour
	 * shows at once).
	 *
	 * @return string[]
	 */
	public static function reload_keys() {
		$groups = self::groups();
		$keys   = array( 'nosticky', 'noshrink', 'h_img', 'h_pimg', 'h_video', 'h_slides', 'h_interval', 'ks_hide_2', 'ks_hide_3', 'ks_step5', 'ks_speed', 'ks_noloops', 'ks_url', 'ks_nosparkle', 'ks_nopopup', 'mos_source', 'brand', 'b_new', 'b_sale', 'b_low', 'pp_sticky', 'pp_share', 'pp_layout', 'pp_brand_link', 'pp_count', 'pp_related', 'cz_nosparkle', 'ft_top', 'cz_norder', 'cz_nonotions' );
		for ( $n = 1; $n <= 5; $n++ ) {
			$keys[] = 'ks_icon_' . $n;
			$keys[] = 'ks_img_' . $n;
		}
		foreach ( self::reload_groups() as $group ) {
			$keys = array_merge( $keys, array_keys( isset( $groups[ $group ] ) ? $groups[ $group ] : array() ) );
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * All settings of a part: key => rule.
	 *
	 * @param string $id Part.
	 * @return array
	 */
	/**
	 * An own part: any element of a page, clicked in the preview ("el_" and 8 characters; its selector and name are
	 * stored with its settings).
	 *
	 * @param string $id Part.
	 * @return bool
	 */
	public static function is_own( $id ) {
		return (bool) preg_match( '/^el_[a-f0-9]{8}$/', (string) $id );
	}

	/** The settings of an own part. */
	public static function own_controls() {
		return array( 'typo', 'colors', 'image', 'imgpos', 'size', 'space', 'radius', 'move', 'hide', 'anim' );
	}

	/**
	 * A selector made by the studio's preview: classes, ids, tags, :nth-of-type() and > only.
	 *
	 * @param mixed $selector Selector.
	 * @return string '' when it is not one.
	 */
	public static function clean_selector( $selector ) {
		$selector = trim( preg_replace( '/\s+/', ' ', (string) $selector ) );
		if ( '' === $selector || strlen( $selector ) > 400 || ! preg_match( '/^[A-Za-z0-9_\-.#:()\s>+\x7e*]+$/', $selector ) ) {
			return '';
		}
		return $selector;
	}

	/**
	 * A part of the registry, or an own part made from its stored row; null when unknown.
	 *
	 * @param string $id       Part.
	 * @param array  $row      Its settings.
	 * @param array  $elements The registry.
	 * @return array|null
	 */
	private static function element_of( $id, $row, $elements ) {
		if ( isset( $elements[ $id ] ) ) {
			return $elements[ $id ];
		}
		if ( self::is_own( $id ) && is_array( $row ) && ! empty( $row['sel'] ) ) {
			return array(
				'group'    => '',
				'label'    => isset( $row['name'] ) ? (string) $row['name'] : '',
				'selector' => (string) $row['sel'],
				'controls' => self::own_controls(),
			);
		}
		return null;
	}

	public static function fields( $id ) {
		$elements = self::elements();
		$groups   = self::groups();
		$fields   = array();
		foreach ( isset( $elements[ $id ] ) ? $elements[ $id ]['controls'] : ( self::is_own( $id ) ? self::own_controls() : array() ) as $group ) {
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
			if ( ( ! isset( $elements[ $id ] ) && ! self::is_own( $id ) ) || ! is_array( $values ) ) {
				continue;
			}
			// An own part keeps where it is (its selector) and its name.
			$own_selector = self::is_own( $id ) ? self::clean_selector( isset( $values['sel'] ) ? $values['sel'] : '' ) : '';
			if ( self::is_own( $id ) && '' === $own_selector ) {
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
					// "Standard" (it can lie outside the range, e.g. -999 for a margin) is no setting.
					if ( (int) round( (float) $value ) === $rule[2] ) {
						continue;
					}
					$number = max( $rule[0], min( $rule[1], (int) round( (float) $value ) ) );
					if ( $number !== $rule[2] ) {
						$row[ $key ] = $number;
					}
				}
			}
			if ( $row ) {
				if ( '' !== $own_selector ) {
					$name        = isset( $values['name'] ) ? sanitize_text_field( (string) $values['name'] ) : '';
					$row['sel']  = $own_selector;
					$row['name'] = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 80 ) : substr( $name, 0, 80 );
				}
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
				// Texts of the Atelier Irisee settings changed in the studio, not yet published: "option:key" => text.
				'draft_settings' => array(),
			)
		);
	}

	/* ------------------------------------------------------------------ texts of the settings */

	/**
	 * Texts of the Atelier Irisee settings that the studio shows with their part: id => [ label, type, parts, key ].
	 * Types: lang (a setting per language, key_{code}), langlong (the same, longer texts), trio (one "NL | FR | EN"
	 * setting), lines (several such lines), pairs (lines of two such texts, "… || …": questions and answers), plain
	 * (one text), long (a longer text). A key is "option:key" (aimp_settings, aimp_footer or aimp_blocks). Pairs have
	 * the names of their two texts as a fifth item: [ [ label, long ], [ label, long ] ].
	 *
	 * @return array
	 */
	public static function setting_texts() {
		$steps  = array( 'kit-steps', 'kit-step', 'kit-steps-list' );
		$footer = array( 'footer', 'footer-columns', 'footer-title' );
		$list   = array(
			'info_bar'        => array( __( 'Texts in the gold bar', 'atelier-irisee-master-plugin' ), 'lines', array( 'info-bar' ), 'aimp_settings:info_bar' ),
			'delivery_time'   => array( __( 'Delivery time (shown under "Add to cart" and in the cart)', 'atelier-irisee-master-plugin' ), 'trio', array( 'product-buy', 'product-page', 'cart-side', 'cart-page' ), 'aimp_settings:delivery_time' ),
			'hero_title'      => array( __( 'Tagline', 'atelier-irisee-master-plugin' ), 'lang', array( 'hero', 'hero-title' ), 'aimp_settings:hero_title' ),
			'hero_text'       => array( __( 'Text under the tagline', 'atelier-irisee-master-plugin' ), 'lang', array( 'hero', 'hero-text' ), 'aimp_settings:hero_text' ),
			'ks_title'        => array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'kit-steps', 'kit-steps-title' ), 'aimp_settings:ks_title' ),
		);
		for ( $n = 1; $n <= 5; $n++ ) {
			/* translators: %d: step number */
			$list[ 'ks_s' . $n . '_title' ] = array( sprintf( __( 'Step %d: title', 'atelier-irisee-master-plugin' ), $n ), 'lang', $steps, 'aimp_settings:ks_s' . $n . '_title' );
			/* translators: %d: step number */
			$list[ 'ks_s' . $n . '_text' ] = array( sprintf( __( 'Step %d: text', 'atelier-irisee-master-plugin' ), $n ), 'lang', $steps, 'aimp_settings:ks_s' . $n . '_text' );
		}
		$list['ks_button']       = array( __( 'Button', 'atelier-irisee-master-plugin' ), 'lang', array( 'kit-steps', 'kit-steps-button' ), 'aimp_settings:ks_button' );
		$list['company']         = array( __( 'Company name', 'atelier-irisee-master-plugin' ), 'plain', $footer, 'aimp_footer:company' );
		$list['address']         = array( __( 'Address', 'atelier-irisee-master-plugin' ), 'long', $footer, 'aimp_footer:address' );
		$list['phone']           = array( __( 'Phone', 'atelier-irisee-master-plugin' ), 'plain', $footer, 'aimp_footer:phone' );
		$list['email']           = array( __( 'Email', 'atelier-irisee-master-plugin' ), 'plain', $footer, 'aimp_footer:email' );
		$list['newsletter_text'] = array( __( 'Newsletter text', 'atelier-irisee-master-plugin' ), 'long', array( 'newsletter', 'footer', 'footer-columns' ), 'aimp_footer:newsletter_text' );
		$list['copyright']       = array( __( 'Copyright line', 'atelier-irisee-master-plugin' ), 'plain', array( 'footer', 'footer-bottom' ), 'aimp_footer:copyright' );

		// The contact page (its details are the footer's: company, address, phone, email).
		$contact = array( 'contact', 'contact-head', 'contact-cards', 'contact-form' );
		$faq     = array( 'faq', 'contact', 'home-faq' );
		$b       = 'aimp_blocks:';
		foreach ( array( 'address', 'phone', 'email' ) as $key ) {
			$list[ $key ][2] = array_merge( $list[ $key ][2], array( 'contact-cards' ) );
		}
		$list['ct_title']      = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', $contact, $b . 'ct_title' );
		$list['ct_intro']      = array( __( 'Introduction', 'atelier-irisee-master-plugin' ), 'langlong', $contact, $b . 'ct_intro' );
		$list['ct_hours']      = array( __( 'Answer time or opening hours', 'atelier-irisee-master-plugin' ), 'langlong', $contact, $b . 'ct_hours' );
		$list['ct_form_title'] = array( __( 'Title above the form', 'atelier-irisee-master-plugin' ), 'lang', $contact, $b . 'ct_form_title' );
		$list['ct_subjects']   = array( __( 'Subjects to choose from', 'atelier-irisee-master-plugin' ), 'lines', $contact, $b . 'ct_subjects' );
		$list['ct_success']    = array( __( 'Message after sending', 'atelier-irisee-master-plugin' ), 'langlong', $contact, $b . 'ct_success' );
		$list['ct_faq_title']  = array( __( 'Title above the questions', 'atelier-irisee-master-plugin' ), 'lang', array( 'contact', 'faq' ), $b . 'ct_faq_title' );
		$list['faq']           = array( __( 'Questions and answers', 'atelier-irisee-master-plugin' ), 'pairs', $faq, $b . 'faq', array( array( __( 'Question', 'atelier-irisee-master-plugin' ), false ), array( __( 'Answer', 'atelier-irisee-master-plugin' ), true ) ) );
		// The home page blocks.
		$list['hc_title']        = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-cats' ), $b . 'hc_title' );
		$list['rv_title']        = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-reviews' ), $b . 'rv_title' );
		$list['rv_quotes']       = array( __( 'Your own testimonials (shown before the reviews)', 'atelier-irisee-master-plugin' ), 'pairs', array( 'home-reviews' ), $b . 'rv_quotes', array( array( __( 'Quote', 'atelier-irisee-master-plugin' ), true ), array( __( 'Name', 'atelier-irisee-master-plugin' ), false ) ) );
		$list['mk_kicker']       = array( __( 'Small line above the title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-maker' ), $b . 'mk_kicker' );
		$list['mk_title']        = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-maker' ), $b . 'mk_title' );
		$list['mk_text']         = array( __( 'Story', 'atelier-irisee-master-plugin' ), 'langlong', array( 'home-maker' ), $b . 'mk_text' );
		$list['mk_button']       = array( __( 'Button', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-maker' ), $b . 'mk_button' );
		$list['nw_title']        = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-new' ), $b . 'nw_title' );
		$list['nw_button']       = array( __( 'Link to all', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-new' ), $b . 'nw_button' );
		$list['hn_title']        = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-newsletter' ), $b . 'hn_title' );
		$list['hn_text']         = array( __( 'Text', 'atelier-irisee-master-plugin' ), 'langlong', array( 'home-newsletter' ), $b . 'hn_text' );
		$list['hn_offer']        = array( __( 'Welcome discount ({percent} = the percentage)', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-newsletter' ), $b . 'hn_offer' );
		$list['hn_offer_text']   = array( __( 'Text under the welcome discount', 'atelier-irisee-master-plugin' ), 'langlong', array( 'home-newsletter' ), $b . 'hn_offer_text' );
		$list['hn_offer_button'] = array( __( 'Button to create an account', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-newsletter' ), $b . 'hn_offer_button' );
		$list['fq_title']        = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-faq' ), $b . 'fq_title' );
		$list['fq_button']       = array( __( 'Button to the contact page', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-faq' ), $b . 'fq_button' );
		$list['br_title']        = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', array( 'home-brands' ), $b . 'br_title' );
		// The About page.
		$head     = array( 'about', 'about-head' );
		$chapters = array( 'about', 'about-chapter' );
		$closing  = array( 'about', 'about-closing' );
		$list['ab_kicker'] = array( __( 'Small line above the title', 'atelier-irisee-master-plugin' ), 'lang', $head, $b . 'ab_kicker' );
		$list['ab_title']  = array( __( 'Title', 'atelier-irisee-master-plugin' ), 'lang', $head, $b . 'ab_title' );
		$list['ab_intro']  = array( __( 'Introduction', 'atelier-irisee-master-plugin' ), 'langlong', $head, $b . 'ab_intro' );
		for ( $n = 1; $n <= 4; $n++ ) {
			/* translators: %d: chapter number */
			$list[ 'ab_c' . $n . '_title' ] = array( sprintf( __( 'Chapter %d: title', 'atelier-irisee-master-plugin' ), $n ), 'lang', $chapters, $b . 'ab_c' . $n . '_title' );
			/* translators: %d: chapter number */
			$list[ 'ab_c' . $n . '_text' ] = array( sprintf( __( 'Chapter %d: text (an empty line starts a new paragraph)', 'atelier-irisee-master-plugin' ), $n ), 'langlong', $chapters, $b . 'ab_c' . $n . '_text' );
			/* translators: %d: chapter number */
			$list[ 'ab_c' . $n . '_button' ] = array( sprintf( __( 'Chapter %d: button (shown with an address in its settings)', 'atelier-irisee-master-plugin' ), $n ), 'lang', $chapters, $b . 'ab_c' . $n . '_button' );
		}
		$list['ab_values']      = array( __( 'What we stand for', 'atelier-irisee-master-plugin' ), 'pairs', array( 'about', 'about-values' ), $b . 'ab_values', array( array( __( 'Title', 'atelier-irisee-master-plugin' ), false ), array( __( 'Text', 'atelier-irisee-master-plugin' ), true ) ) );
		$list['ab_quote']       = array( __( 'Quote', 'atelier-irisee-master-plugin' ), 'langlong', $closing, $b . 'ab_quote' );
		$list['ab_sign']        = array( __( 'Signature', 'atelier-irisee-master-plugin' ), 'langlong', $closing, $b . 'ab_sign' );
		$list['ab_shop_button'] = array( __( 'Button to the shop', 'atelier-irisee-master-plugin' ), 'lang', $closing, $b . 'ab_shop_button' );
		$list['ab_kit_button']  = array( __( 'Button to the customizer', 'atelier-irisee-master-plugin' ), 'lang', $closing, $b . 'ab_kit_button' );
		return $list;
	}

	/**
	 * Every setting key of setting_texts() ("option:key"; per language for "lang").
	 *
	 * @return array key => type
	 */
	private static function setting_keys() {
		$keys = array();
		foreach ( self::setting_texts() as $field ) {
			if ( in_array( $field[1], array( 'lang', 'langlong' ), true ) ) {
				foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
					$keys[ $field[3] . '_' . $code ] = $field[1];
				}
			} else {
				$keys[ $field[3] ] = $field[1];
			}
		}
		return $keys;
	}

	/**
	 * The texts of setting_texts() for the studio: the fields, with the standard texts as placeholders.
	 *
	 * @return array
	 */
	private static function setting_fields() {
		$languages = array_keys( AIMP_I18n::languages() );
		$standard  = array();
		foreach ( $languages as $code ) {
			$standard[ $code ] = AIMP_I18n::with_language(
				$code,
				function () {
					return array_merge(
						class_exists( 'AIMP_Kit_Steps' ) ? AIMP_Kit_Steps::default_texts() : array(),
						class_exists( 'AIMP_Blocks' ) ? AIMP_Blocks::default_texts() : array(),
						array(
							'hero_title' => __( 'Sew something truly yours', 'atelier-irisee-master-plugin' ),
							'hero_text'  => __( 'Pattern, fabric and haberdashery that match, in one kit.', 'atelier-irisee-master-plugin' ),
						)
					);
				}
			);
		}
		$fields = array();
		foreach ( self::setting_texts() as $id => $field ) {
			$base  = substr( $field[3], strpos( $field[3], ':' ) + 1 );
			$hints = array();
			foreach ( $languages as $code ) {
				$hints[ $code ] = isset( $standard[ $code ][ $base ] ) ? $standard[ $code ][ $base ] : '';
			}
			$fields[] = array(
				'id'    => $id,
				'label' => $field[0],
				'type'  => $field[1],
				'parts' => $field[2],
				'key'   => $field[3],
				'hints' => $hints,
				'sub'   => isset( $field[4] ) ? $field[4] : null,
			);
		}
		return $fields;
	}

	/**
	 * The current (published) value of a setting key ("option:key").
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function setting_value( $key ) {
		list( $option, $name ) = explode( ':', $key, 2 );
		$values = (array) get_option( $option, array() );
		if ( 'aimp_settings' === $option && 'info_bar' === $name && ! isset( $values['info_bar'] ) && class_exists( 'AIMP_Header' ) ) {
			return AIMP_Header::default_info_bar();
		}
		if ( 'aimp_settings' === $option && 'delivery_time' === $name && ! isset( $values['delivery_time'] ) ) {
			return AIMP_Settings::default_delivery_time();
		}
		// The standard subjects and questions, until they are changed.
		if ( 'aimp_blocks' === $option && ! isset( $values[ $name ] ) && class_exists( 'AIMP_Blocks' ) && '' !== AIMP_Blocks::stored_default( $name ) ) {
			return AIMP_Blocks::stored_default( $name );
		}
		return isset( $values[ $name ] ) && is_scalar( $values[ $name ] ) ? (string) $values[ $name ] : '';
	}

	/**
	 * Only known setting keys, sanitised like the settings pages do.
	 *
	 * @param mixed $posted Posted texts.
	 * @return array
	 */
	private static function clean_settings( $posted ) {
		$keys  = self::setting_keys();
		$clean = array();
		foreach ( is_array( $posted ) ? $posted : array() as $key => $value ) {
			if ( ! isset( $keys[ $key ] ) || ! is_scalar( $value ) ) {
				continue;
			}
			$value = in_array( $keys[ $key ], array( 'lines', 'long', 'langlong', 'pairs' ), true ) ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
			// Questions and answers can be long.
			$max   = 'pairs' === $keys[ $key ] ? 20000 : 2000;
			$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
			if ( $value !== self::setting_value( $key ) ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * In the studio's preview: the draft wording of a setting, or null (AIMP_Settings::get_text(), AIMP_Footer::get()).
	 *
	 * @param string $option aimp_settings or aimp_footer.
	 * @param string $key    Setting.
	 * @return string|null
	 */
	public static function setting_draft( $option, $key ) {
		if ( ! did_action( 'set_current_user' ) || ! self::is_preview() ) {
			return null;
		}
		static $draft = null;
		if ( null === $draft ) {
			$data  = self::data();
			$draft = is_array( $data['draft_settings'] ) ? $data['draft_settings'] : array();
		}
		return isset( $draft[ $option . ':' . $key ] ) ? (string) $draft[ $option . ':' . $key ] : null;
	}

	/**
	 * Publishing: the draft wording goes into the settings themselves (as if saved on the settings page; the values
	 * are clean already, so the settings pages' form handling is left out).
	 *
	 * @param array $draft "option:key" => text.
	 */
	private static function publish_settings( $draft ) {
		$by_option = array();
		foreach ( $draft as $key => $value ) {
			list( $option, $name )           = explode( ':', $key, 2 );
			$by_option[ $option ][ $name ] = $value;
		}
		$callbacks = array(
			'aimp_settings' => array( 'AIMP_Settings', 'sanitize' ),
			'aimp_footer'   => array( 'AIMP_Footer', 'sanitize' ),
		);
		foreach ( $by_option as $option => $values ) {
			// aimp_blocks (contact page and home page blocks) has no settings page: only the studio writes it.
			if ( 'aimp_blocks' === $option ) {
				update_option( $option, array_merge( (array) get_option( $option, array() ), $values ), false );
				continue;
			}
			if ( ! isset( $callbacks[ $option ] ) ) {
				continue;
			}
			$removed = remove_filter( 'sanitize_option_' . $option, $callbacks[ $option ] );
			update_option( $option, array_merge( (array) get_option( $option, array() ), $values ) );
			if ( $removed ) {
				add_filter( 'sanitize_option_' . $option, $callbacks[ $option ] );
			}
		}
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

	/** In the preview: original => text as shown, for every shop text on the page (null: not noting). */
	private static $seen = null;

	/**
	 * A shop text as the page shows it (AIMP_I18n::translate()); noted in the studio's preview.
	 *
	 * @param string $original English original.
	 * @param string $shown    The text on the page.
	 * @return string The text on the page.
	 */
	public static function seen( $original, $shown ) {
		if ( null !== self::$seen && '' !== trim( (string) $shown ) && count( self::$seen ) < 3000 ) {
			self::$seen[ $original ] = (string) $shown;
		}
		return $shown;
	}

	public static function start_seen() {
		if ( self::is_preview() ) {
			self::$seen = array();
		}
	}

	public static function print_seen() {
		if ( null === self::$seen ) {
			return;
		}
		$rows = array();
		foreach ( self::$seen as $original => $shown ) {
			$rows[] = array( (string) $original, $shown );
		}
		echo '<script>window.aimpDesignTexts=' . wp_json_encode( $rows ) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON (wp_json_encode escapes "</").
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
					$part = trim( $part );
					return ( 0 === strpos( $part, 'body' ) ? 'html ' : 'html body ' ) . $part . $extra;
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
		$tablet   = '';
		$phone    = '';
		foreach ( $styles as $id => $row ) {
			$element = self::element_of( $id, $row, $elements );
			// "Whole site" parts have no selector: site_css() makes their CSS.
			if ( ! $element || '' === $element['selector'] ) {
				continue;
			}
			$base     = $element['selector'];
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
			// Text style: the part and the text in it.
			$typo = '';
			if ( ! empty( $row['fsize'] ) ) {
				$typo .= 'font-size:' . (int) $row['fsize'] . 'px!important;';
			}
			if ( ! empty( $row['fweight'] ) ) {
				$typo .= 'font-weight:' . (int) $row['fweight'] . '!important;';
			}
			if ( ! empty( $row['tcase'] ) ) {
				$typo .= 'text-transform:' . ( 'upper' === $row['tcase'] ? 'uppercase' : 'none' ) . '!important;';
			}
			if ( '' !== $typo ) {
				$all .= $selector . ',' . self::prefixed( $base, ' *' ) . '{' . $typo . '}';
			}
			if ( ! empty( $row['talign'] ) && in_array( $row['talign'], array( 'left', 'center', 'right' ), true ) ) {
				$all .= $selector . '{text-align:' . $row['talign'] . '!important}';
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
				$sides = array(
					'mt'   => 'margin-top',
					'mr'   => 'margin-right',
					'mb'   => 'margin-bottom',
					'ml'   => 'margin-left',
					'padt' => 'padding-top',
					'padr' => 'padding-right',
					'padb' => 'padding-bottom',
					'padl' => 'padding-left',
				);
				foreach ( $sides as $key => $property ) {
					if ( isset( $row[ $prefix . $key ] ) ) {
						$css .= $property . ':' . (int) $row[ $prefix . $key ] . 'px!important;';
					}
				}
				if ( ! empty( $row[ $prefix . 'hide' ] ) ) {
					$css .= 'display:none!important;';
				}
				return $css;
			};
			// Picture position: the pictures move inside their frame (which clips them) and zoom around the focal point.
			$picture = function ( $prefix ) use ( $row ) {
				$x    = isset( $row[ $prefix . 'ix' ] ) ? (int) $row[ $prefix . 'ix' ] : 0;
				$y    = isset( $row[ $prefix . 'iy' ] ) ? (int) $row[ $prefix . 'iy' ] : 0;
				$zoom = isset( $row[ $prefix . 'izoom' ] ) ? (int) $row[ $prefix . 'izoom' ] : 100;
				if ( ! $x && ! $y && 100 === $zoom ) {
					return '';
				}
				$origin = ( isset( $row['fx'] ) ? (int) $row['fx'] : 50 ) . '% ' . ( isset( $row['fy'] ) ? (int) $row['fy'] : 50 ) . '%';
				return 'transform:translate(' . $x . '%,' . $y . '%) scale(' . ( $zoom / 100 ) . ')!important;transform-origin:' . $origin . '!important;';
			};
			// The frame: the part itself or the element in it that holds the picture.
			$frames = self::prefixed( $base, ':has(> img)' ) . ',' . self::prefixed( $base, ' :has(> img)' );
			foreach ( array( '' => 'computer', 'p' => 'phone' ) as $prefix => $screen ) {
				$moved = $picture( $prefix );
				if ( '' === $moved ) {
					continue;
				}
				$rule = $images . '{' . $moved . '}' . $frames . '{overflow:hidden!important}';
				if ( 'phone' === $screen ) {
					$phone .= $rule;
				} else {
					$computer .= $rule;
				}
			}
			$desktop = $device( '' );
			$mid     = $device( 't' );
			$mobile  = $device( 'p' );
			if ( '' !== $desktop ) {
				$computer .= $selector . '{' . $desktop . '}';
			}
			if ( '' !== $mid ) {
				$tablet .= $selector . '{' . $mid . '}';
			}
			if ( '' !== $mobile ) {
				$phone .= $selector . '{' . $mobile . '}';
			}
		}
		$css = self::site_css( $styles ) . self::opts_css( $styles ) . $all;
		if ( '' !== $computer ) {
			$css .= '@media (min-width:' . ( self::PHONE + 1 ) . 'px){' . $computer . '}';
		}
		// Tablets: their own spacing where it is set, after the computer values.
		if ( '' !== $tablet ) {
			$css .= '@media (min-width:' . ( self::PHONE + 1 ) . 'px) and (max-width:' . self::TABLET . 'px){' . $tablet . '}';
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

		// Footer: columns per row, centred, columns hidden or moved.
		$ft = 'html body .aimp-footer';
		if ( $get( 'footer', 'ft_cols' ) ) {
			$computer .= $ft . ' .aimp-footer-columns{grid-template-columns:repeat(' . (int) $get( 'footer', 'ft_cols' ) . ',minmax(0,1fr))!important}';
		}
		if ( 'center' === $get( 'footer', 'ft_align' ) ) {
			$css .= $ft . ' :is(.aimp-footer-columns,.aimp-footer-bottom){text-align:center}' . $ft . ' :is(.aimp-footer-socials,.aimp-footer-menu,.aimp-footer-payments,.aimp-footer-bottom .menu){justify-content:center}' . $ft . ' .aimp-newsletter{align-items:center}';
		}
		foreach ( array_keys( self::footer_columns() ) as $col ) {
			if ( $get( 'footer', 'ft_hide_' . $col ) ) {
				$css .= $ft . ' .aimp-footer-col--' . $col . '{display:none!important}';
			}
			if ( $get( 'footer', 'ft_order_' . $col ) ) {
				$css .= $ft . ' .aimp-footer-col--' . $col . '{order:' . (int) $get( 'footer', 'ft_order_' . $col ) . '}';
			}
		}
		if ( $get( 'newsletter', 'nl_wide' ) ) {
			$css .= 'html body .aimp-newsletter{flex-direction:row!important;flex-wrap:wrap;align-items:stretch!important}html body .aimp-newsletter input[type="email"]{flex:1 1 12rem}';
		}
		// Gift card choices: designs per row, the chosen amount's colours.
		if ( $get( 'giftcard-form', 'gc_cols' ) ) {
			$css .= 'html body .aimp-gc-designs{grid-template-columns:repeat(' . (int) $get( 'giftcard-form', 'gc_cols' ) . ',minmax(0,1fr))!important}';
		}
		$chip = '';
		if ( $get( 'giftcard-form', 'gc_chip' ) ) {
			$chip .= 'background:' . $get( 'giftcard-form', 'gc_chip' ) . '!important;border-color:' . $get( 'giftcard-form', 'gc_chip' ) . '!important;';
		}
		if ( $get( 'giftcard-form', 'gc_chip_fg' ) ) {
			$chip .= 'color:' . $get( 'giftcard-form', 'gc_chip_fg' ) . '!important;';
		}
		if ( '' !== $chip ) {
			$css .= 'html body .aimp-gc-chip:has(input:checked),html body .aimp-gc-chip:has(input:checked) :is(.amount,bdi){' . $chip . '}';
		}
		// Messages: colours and a fixed place at the top or bottom of the screen.
		$nb = 'html body .aimp-notice-box';
		$ok = '';
		if ( $get( 'site-notices', 'nt_bg' ) ) {
			$ok .= 'background:' . $get( 'site-notices', 'nt_bg' ) . '!important;border-color:' . $get( 'site-notices', 'nt_bg' ) . '!important;';
		}
		if ( $get( 'site-notices', 'nt_fg' ) ) {
			$ok .= 'color:' . $get( 'site-notices', 'nt_fg' ) . '!important;';
		}
		if ( '' !== $ok ) {
			$css .= $nb . ':not(.woocommerce-error):not(.is-error){' . $ok . '}' . $nb . ':not(.woocommerce-error):not(.is-error) a{color:inherit!important}';
		}
		if ( $get( 'site-notices', 'nt_err_bg' ) ) {
			$css .= $nb . ':is(.woocommerce-error,.is-error){background:' . $get( 'site-notices', 'nt_err_bg' ) . '!important;border-color:' . $get( 'site-notices', 'nt_err_bg' ) . '!important}';
		}
		if ( $get( 'site-notices', 'nt_pos' ) ) {
			$css .= 'html body .woocommerce-notices-wrapper:has(.aimp-notice-box:not(.aimp-notice-hidden)){position:fixed!important;left:50%;z-index:100000;display:flex;flex-direction:column;gap:.5rem;width:min(560px,calc(100% - 32px));margin:0!important;transform:translateX(-50%);' .
				( 'top' === $get( 'site-notices', 'nt_pos' ) ? 'top:calc(var(--aimp-sticky-top,0px) + 12px)' : 'bottom:calc(var(--aimp-tabbar-h,0px) + 16px)' ) . '}';
		}

		// Cart: parts left out, the totals on the left or not sticky, the size of the pictures.
		$cart = 'html body .aimp-configurator.aimp-cart';
		foreach ( array(
			'cart_nocoupon'   => ' .aimp-cart-coupon',
			'cart_noupdate'   => ' .aimp-cart-update',
			'cart_notrust'    => ' .aimp-cart-trust .aimp-delivery-line',
			'cart_nopayments' => ' .aimp-cart-payments',
			'cart_nocontinue' => ' .aimp-cart-continue',
			'cart_nocross'    => ' .aimp-cart-cross',
			'cart_nopopular'  => ' .aimp-cart-suggestions',
		) as $key => $selector ) {
			if ( $get( 'cart-page', $key ) ) {
				$css .= $cart . $selector . '{display:none!important}';
			}
		}
		if ( $get( 'cart-page', 'cart_left' ) ) {
			$computer .= $cart . ' .aimp-cart-layout{grid-template-columns:minmax(0,2fr) minmax(0,3fr)!important}' . $cart . ' .aimp-cart-side{order:-1}';
		}
		if ( $get( 'cart-page', 'cart_nosticky' ) ) {
			$css .= $cart . ' .aimp-cart-side{position:static!important}';
		}
		if ( $get( 'cart-page', 'cart_thumb' ) ) {
			$css .= $cart . ' .aimp-cart-thumb{width:' . (int) $get( 'cart-page', 'cart_thumb' ) . 'px!important;flex:0 0 ' . (int) $get( 'cart-page', 'cart_thumb' ) . 'px!important}' . $cart . ' .aimp-cart-thumb img{width:100%!important;height:auto!important}';
		}
		// Checkout: the steps, "Your order" on the left, the discount code box, "Create an account" after ordering.
		$co = 'html body .aimp-configurator.aimp-checkout';
		if ( $get( 'checkout-page', 'co_nosteps' ) ) {
			$css .= $co . ' .aimp-checkout-steps{display:none!important}';
		}
		if ( $get( 'checkout-page', 'co_left' ) ) {
			$computer .= $co . ' .aimp-checkout-layout{grid-template-columns:minmax(280px,380px) minmax(0,1fr)!important;grid-template-areas:"summary main"!important}';
		}
		if ( $get( 'checkout-page', 'co_nocodes' ) ) {
			$css .= $co . ' .aimp-checkout-codes{display:none!important}';
		}
		if ( $get( 'checkout-page', 'co_noaccount' ) ) {
			$css .= 'html body .aimp-thankyou-account{display:none!important}';
		}
		// My account: tabs hidden, the tabs above the page, no "Hello".
		$acc = 'html body .aimp-account:not(.aimp-account--guest)';
		foreach ( array_keys( self::account_fields() ) as $key ) {
			if ( 0 === strpos( $key, 'acc_hide_' ) && $get( 'account-page', $key ) ) {
				$css .= $acc . ' .aimp-account-tabs li:has(> a[href*="tab=' . substr( $key, 9 ) . '"]){display:none!important}';
			}
		}
		if ( $get( 'account-page', 'acc_top' ) ) {
			$computer .= $acc . ' .aimp-account-layout{grid-template-columns:minmax(0,1fr)!important}' . $acc . ' .aimp-account-tabs{position:static!important}' . $acc . ' .aimp-account-tabs ul{display:flex!important;flex-wrap:wrap;gap:.5rem}';
		}
		if ( $get( 'account-page', 'acc_nohello' ) ) {
			$css .= $acc . ' .aimp-account-hello{display:none!important}';
		}
		// Log in and register: the picture, the logo, the width, the dark layer behind the pop-up.
		if ( $get( 'login-forms', 'log_noimage' ) ) {
			$css .= 'html body .aimp-el .aimp-el-image{display:none!important}';
		}
		if ( $get( 'login-forms', 'log_nologo' ) ) {
			$css .= 'html body .aimp-el .aimp-el-logo{display:none!important}';
		}
		if ( $get( 'login-forms', 'log_width' ) ) {
			$css .= 'html body .aimp-el{--aimp-el-width:' . (int) $get( 'login-forms', 'log_width' ) . 'px!important}';
		}
		if ( $get( 'login-forms', 'log_overlay' ) ) {
			$css .= 'html body .aimp-el{--aimp-el-overlay:color-mix(in srgb,' . $get( 'login-forms', 'log_overlay' ) . ' 70%,transparent)!important}';
		}
		// Favourites: cards per row, the colour of the hearts (everywhere), no message after a click.
		$fav = 'html body .aimp-configurator.aimp-favorites .aimp-favorites-grid';
		if ( $get( 'favorites-page', 'fav_cols' ) ) {
			$computer .= $fav . '{grid-template-columns:repeat(' . (int) $get( 'favorites-page', 'fav_cols' ) . ',minmax(0,1fr))!important}';
		}
		if ( $get( 'favorites-page', 'fav_pcols' ) ) {
			$phone .= $fav . '{grid-template-columns:repeat(' . (int) $get( 'favorites-page', 'fav_pcols' ) . ',minmax(0,1fr))!important}';
		}
		if ( $get( 'favorites-page', 'fav_heart' ) ) {
			$css .= 'html body .aimp-fav{color:' . $get( 'favorites-page', 'fav_heart' ) . '!important;border-color:' . $get( 'favorites-page', 'fav_heart' ) . '!important}';
		}
		if ( $get( 'favorites-page', 'fav_notoast' ) ) {
			$css .= 'html body .aimp-fav-toast{display:none!important}';
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
			$element = self::element_of( $id, $row, $elements );
			if ( $element && '' !== $element['selector'] && isset( $row['speed'] ) && empty( $row['noanim'] ) ) {
				$speeds[ $element['selector'] ] = (int) $row['speed'];
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
				// Parts of the page only ("Whole site" has nothing to click), with their names for the label under the mouse.
				'elements' => array_filter( wp_list_pluck( self::elements(), 'selector' ) ),
				'names'    => wp_list_pluck( self::elements(), 'label' ),
				// Names for other elements.
				'words'    => array(
					'section'   => __( 'Section', 'atelier-irisee-master-plugin' ),
					'row'       => __( 'Row', 'atelier-irisee-master-plugin' ),
					'column'    => __( 'Column', 'atelier-irisee-master-plugin' ),
					'title'     => __( 'Title', 'atelier-irisee-master-plugin' ),
					'paragraph' => __( 'Paragraph', 'atelier-irisee-master-plugin' ),
					'link'      => __( 'Link', 'atelier-irisee-master-plugin' ),
					'button'    => __( 'Button', 'atelier-irisee-master-plugin' ),
					'picture'   => __( 'Picture', 'atelier-irisee-master-plugin' ),
					'item'      => __( 'List item', 'atelier-irisee-master-plugin' ),
					'list'      => __( 'List', 'atelier-irisee-master-plugin' ),
					'block'     => __( 'Block', 'atelier-irisee-master-plugin' ),
				),
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
		$pages[] = array( __( 'Checkout', 'atelier-irisee-master-plugin' ), wc_get_checkout_url() );
		$pages[] = array( __( 'My account', 'atelier-irisee-master-plugin' ), AIMP_Header::account_url() );
		$favorites = AIMP_Settings::get( 'favorites_page' );
		if ( $favorites && 'publish' === get_post_status( $favorites ) ) {
			$pages[] = array( __( 'Favourites', 'atelier-irisee-master-plugin' ), get_permalink( $favorites ) );
		}
		// Every other published page (e.g. a start page), A to Z, and the latest blog posts.
		$known = array_map( 'untrailingslashit', wp_list_pluck( $pages, 1 ) );
		$more  = array(
			__( 'Pages', 'atelier-irisee-master-plugin' )      => get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			),
			__( 'Blog posts', 'atelier-irisee-master-plugin' ) => get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 20,
				)
			),
		);
		foreach ( $more as $group => $posts ) {
			foreach ( $posts as $post ) {
				$url = get_permalink( $post );
				if ( $url && ! in_array( untrailingslashit( $url ), $known, true ) ) {
					$known[] = untrailingslashit( $url );
					$title   = wp_strip_all_tags( get_the_title( $post ) );
					$pages[] = array( '' !== $title ? $title : '#' . $post->ID, $url, $group );
				}
			}
		}
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
					'tablet'   => self::TABLET,
					'pages'    => self::pages(),
					'settingTexts' => self::setting_fields(),
					'editPost' => admin_url( 'post.php?post=%d&action=edit' ),
					'elements' => self::elements(),
					'groups'   => self::groups(),
					'reload'   => self::reload_groups(),
					'reloadKeys' => self::reload_keys(),
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
						'thisPage'    => __( 'The page you came from', 'atelier-irisee-master-plugin' ),
						'onPage'      => __( 'On this page', 'atelier-irisee-master-plugin' ),
						'allParts'    => __( 'All parts', 'atelier-irisee-master-plugin' ),
						'noneHere'    => __( 'This page has no parts of its own to change; see the whole site below.', 'atelier-irisee-master-plugin' ),
						'wholeSite'   => __( 'Whole site (colours, fonts, buttons, …)', 'atelier-irisee-master-plugin' ),
						'otherPages'  => __( 'Parts of other pages', 'atelier-irisee-master-plugin' ),
						'tabContent'  => __( 'Content', 'atelier-irisee-master-plugin' ),
						'tabDesign'   => __( 'Design', 'atelier-irisee-master-plugin' ),
						'tabAdvanced' => __( 'Advanced', 'atelier-irisee-master-plugin' ),
						'textTitle'   => __( 'Text', 'atelier-irisee-master-plugin' ),
						'textOnly'    => __( 'Text', 'atelier-irisee-master-plugin' ),
						'textHelp'    => __( 'Empty = the standard text. The preview shows your text after a moment; visitors see it after Publish.', 'atelier-irisee-master-plugin' ),
						'textMiss'    => __( 'These words come from your own content (a product, a category, a page or a setting), not from the shop texts. Change them where they are made.', 'atelier-irisee-master-plugin' ),
						'textsOnPage' => __( 'Only the texts on the page in the preview', 'atelier-irisee-master-plugin' ),
						'editPost'    => __( 'Edit this in WordPress', 'atelier-irisee-master-plugin' ),
						'diviBuilder' => __( 'Open this page in the Divi builder', 'atelier-irisee-master-plugin' ),
						'ownParts'    => __( 'Your own picks', 'atelier-irisee-master-plugin' ),
						'ownScope'    => __( 'An element you clicked: these settings count for this element on this page (header and footer: on every page).', 'atelier-irisee-master-plugin' ),
						'pathTitle'   => __( 'Change instead:', 'atelier-irisee-master-plugin' ),
						// Translating, back to standard, finding settings, layers.
						'translate'        => __( 'Translate to the empty languages', 'atelier-irisee-master-plugin' ),
						'translating'      => __( 'Translating…', 'atelier-irisee-master-plugin' ),
						'translateFull'    => __( 'Every language is filled in already. Empty one to translate it again.', 'atelier-irisee-master-plugin' ),
						'translateNothing' => __( 'Type a text in one language first.', 'atelier-irisee-master-plugin' ),
						'resetOne'         => __( 'Back to standard', 'atelier-irisee-master-plugin' ),
						'findSetting'      => __( 'Find a setting…', 'atelier-irisee-master-plugin' ),
						'noSetting'        => __( 'No setting of this part matches.', 'atelier-irisee-master-plugin' ),
						'onlyChanged'      => __( 'Only what I changed', 'atelier-irisee-master-plugin' ),
						'backPart'         => __( 'Back to the previous part', 'atelier-irisee-master-plugin' ),
						'layers'           => __( 'Layers of this page', 'atelier-irisee-master-plugin' ),
						'undo'        => __( 'Undo', 'atelier-irisee-master-plugin' ),
						'redo'        => __( 'Redo', 'atelier-irisee-master-plugin' ),
						'shopPages'   => __( 'Shop', 'atelier-irisee-master-plugin' ),
						'thisPageNow' => __( 'This page', 'atelier-irisee-master-plugin' ),
						'settingsTitle' => __( 'Texts from the settings', 'atelier-irisee-master-plugin' ),
						'settingsHelp'  => __( 'These texts are the same as under Atelier Irisee → Settings. The preview shows your change after a moment; Publish saves them there.', 'atelier-irisee-master-plugin' ),
						'addItem'       => __( 'Add a text', 'atelier-irisee-master-plugin' ),
						'removeItem'    => __( 'Remove', 'atelier-irisee-master-plugin' ),
						'wpMenu'        => __( 'WordPress menu', 'atelier-irisee-master-plugin' ),
						'focal'         => __( 'Focal point', 'atelier-irisee-master-plugin' ),
						'focalHint'     => __( 'Click or drag on the picture to choose the spot that stays in view.', 'atelier-irisee-master-plugin' ),
						'notMoved'      => __( 'Not moved', 'atelier-irisee-master-plugin' ),
						'sizeWord'      => __( 'Size', 'atelier-irisee-master-plugin' ),
						'spaceAbove'    => __( 'Space above', 'atelier-irisee-master-plugin' ),
						'spaceBelow'    => __( 'Space below', 'atelier-irisee-master-plugin' ),
						'deviceTablet'  => __( 'Showing the settings for tablets: spacing has its own values here (empty = as on computers); the other settings are those for computers.', 'atelier-irisee-master-plugin' ),
						'marginWord'    => __( 'Margin', 'atelier-irisee-master-plugin' ),
						'paddingWord'   => __( 'Padding', 'atelier-irisee-master-plugin' ),
						'sideTop'       => __( 'top', 'atelier-irisee-master-plugin' ),
						'sideRight'     => __( 'right', 'atelier-irisee-master-plugin' ),
						'sideBottom'    => __( 'bottom', 'atelier-irisee-master-plugin' ),
						'sideLeft'      => __( 'left', 'atelier-irisee-master-plugin' ),
						'standardWord'  => __( 'standard', 'atelier-irisee-master-plugin' ),
						'deviceComputer' => __( 'Showing the settings for computers and tablets. Click "Phone" at the top for the phone settings.', 'atelier-irisee-master-plugin' ),
						'devicePhone' => __( 'Showing the settings for phones. Click "Computer" at the top for the computer settings.', 'atelier-irisee-master-plugin' ),
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
	 * Spacing like Divi: an outer box (margin) and an inner box (padding), each with a field per side, "the same on
	 * all sides" and "Standard".
	 *
	 * @param string $prefix '' (computers), t (tablets) or p (phones).
	 * @param string $device desktop, tablet or phone (the studio shows the screen chosen at the top).
	 */
	private static function box_model( $prefix, $device ) {
		$field = function ( $key, $side, $min ) {
			return '<input type="number" class="aimp-ds-bm-' . esc_attr( $side ) . '" data-key="' . esc_attr( $key ) . '" min="' . (int) $min . '" max="300" step="1" aria-label="' . esc_attr( $side ) . '">';
		};
		$margin  = array();
		$padding = array();
		foreach ( array( 't' => 'top', 'r' => 'right', 'b' => 'bottom', 'l' => 'left' ) as $short => $side ) {
			$margin[ $side ]  = $field( $prefix . 'm' . $short, $side, -150 );
			$padding[ $side ] = $field( $prefix . 'pad' . $short, $side, 0 );
		}
		$keys = function ( $base ) use ( $prefix ) {
			return implode( ',', array_map( function ( $s ) use ( $prefix, $base ) {
				return $prefix . $base . $s;
			}, array( 't', 'r', 'b', 'l' ) ) );
		};
		echo '<div class="aimp-ds-boxmodel" data-device="' . esc_attr( $device ) . '">';
		echo '<div class="aimp-ds-bm-margin" data-bm="margin"><span class="aimp-ds-bm-label">' . esc_html__( 'Margin (outside)', 'atelier-irisee-master-plugin' ) . '</span>';
		echo $margin['top'] . $margin['left'] . $margin['right'] . $margin['bottom']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $field.
		echo '<div class="aimp-ds-bm-padding" data-bm="padding"><span class="aimp-ds-bm-label">' . esc_html__( 'Padding (inside)', 'atelier-irisee-master-plugin' ) . '</span>';
		echo $padding['top'] . $padding['left'] . $padding['right'] . $padding['bottom']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $field.
		echo '<div class="aimp-ds-bm-core"></div></div></div>';
		echo '<div class="aimp-ds-bm-tools">';
		echo '<label class="aimp-ds-check"><input type="checkbox" class="aimp-ds-bm-link" data-link="margin"> ' . esc_html__( 'Margin: the same on all sides', 'atelier-irisee-master-plugin' ) . '</label>';
		echo '<label class="aimp-ds-check"><input type="checkbox" class="aimp-ds-bm-link" data-link="padding"> ' . esc_html__( 'Padding: the same on all sides', 'atelier-irisee-master-plugin' ) . '</label>';
		echo '<button type="button" class="button-link" data-clear="' . esc_attr( $keys( 'm' ) . ',' . $keys( 'pad' ) ) . '">' . esc_html__( 'Standard', 'atelier-irisee-master-plugin' ) . '</button>';
		echo '</div></div>';
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
		// "Hide on computers / phones": only shown with the settings of that screen.
		$device = preg_match( '/^(ic_|pp_)?phide(_|$)/', $key ) ? 'phone' : ( preg_match( '/^(ic_|pp_)?hide(_|$)/', $key ) ? 'computer' : '' );
		echo '<label class="aimp-ds-check"' . ( '' !== $device ? ' data-device="' . esc_attr( $device ) . '"' : '' ) . '><input type="checkbox" data-key="' . esc_attr( $key ) . '"> ' . esc_html( $label ) . '</label>';
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
		// A "Computer" or "Phone" heading: the studio shows the settings of the screen chosen at the top.
		$sub      = function ( $title ) use ( $computer, $phone ) {
			$device = $title === $computer ? 'computer' : ( $title === $phone ? 'phone' : '' );
			echo '<p class="aimp-ds-sub"' . ( '' !== $device ? ' data-device-sub="' . esc_attr( $device ) . '"' : '' ) . '>' . esc_html( $title ) . '</p>';
		};

		$group( 'move', __( 'Position', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Tip: drag the part in the preview, or use the arrow keys (Shift: 10 px at a time). The preview shows where it was and how far it moved.', 'atelier-irisee-master-plugin' ) . '</p>';
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

		$group( 'space', __( 'Spacing', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Margin is the space outside the part, padding the space inside it (in px). Empty = standard. Tip: drag the coloured bands of the chosen part in the preview.', 'atelier-irisee-master-plugin' ) . '</p>';
		foreach ( array(
			''  => 'desktop',
			't' => 'tablet',
			'p' => 'phone',
		) as $prefix => $device ) {
			self::box_model( $prefix, $device );
		}
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

		$group( 'typo', __( 'Text style', 'atelier-irisee-master-plugin' ) );
		self::number( 'fsize', __( 'Text size (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 120, 'px' );
		self::choice(
			'fweight',
			__( 'Thickness', 'atelier-irisee-master-plugin' ),
			array(
				''    => $standard,
				'300' => __( 'Thin', 'atelier-irisee-master-plugin' ),
				'400' => __( 'Normal', 'atelier-irisee-master-plugin' ),
				'600' => __( 'Half bold', 'atelier-irisee-master-plugin' ),
				'700' => __( 'Bold', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice(
			'talign',
			__( 'Alignment', 'atelier-irisee-master-plugin' ),
			array(
				''       => $standard,
				'left'   => __( 'Left', 'atelier-irisee-master-plugin' ),
				'center' => __( 'Centre', 'atelier-irisee-master-plugin' ),
				'right'  => __( 'Right', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice(
			'tcase',
			__( 'Capitals', 'atelier-irisee-master-plugin' ),
			array(
				''       => $standard,
				'upper'  => __( 'ALL CAPITALS', 'atelier-irisee-master-plugin' ),
				'normal' => __( 'Normal letters', 'atelier-irisee-master-plugin' ),
			)
		);
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

		$group( 'imgpos', __( 'Picture position', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Moves the pictures of this part inside their frame and zooms in or out. Zoom in first to move without empty edges.', 'atelier-irisee-master-plugin' ) . '</p>';
		$sub( $computer );
		self::number( 'ix', __( 'Left / right', 'atelier-irisee-master-plugin' ), -100, 100, '%' );
		self::number( 'iy', __( 'Up / down', 'atelier-irisee-master-plugin' ), -100, 100, '%' );
		self::number( 'izoom', __( 'Zoom', 'atelier-irisee-master-plugin' ), 50, 300, '%' );
		$sub( $phone );
		self::number( 'pix', __( 'Left / right', 'atelier-irisee-master-plugin' ), -100, 100, '%' );
		self::number( 'piy', __( 'Up / down', 'atelier-irisee-master-plugin' ), -100, 100, '%' );
		self::number( 'pizoom', __( 'Zoom', 'atelier-irisee-master-plugin' ), 50, 300, '%' );
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
		self::number( 'ha_speed', __( 'Time to slide down', 'atelier-irisee-master-plugin' ), 300, 3000, 'ms' );
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
		$order_list = function ( $key, $label ) use ( $blocks, $standard, $phone ) {
			echo '<div class="aimp-ds-order-field" data-device="' . ( $label === $phone ? 'phone' : 'computer' ) . '"><p class="aimp-ds-sub">' . esc_html( $label ) . '</p><ol class="aimp-ds-order" data-order="' . esc_attr( $key ) . '">';
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
		$field( 'cz_intro_lining', __( 'Lining', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
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

		// Footer, newsletter, gift cards, messages, emails (phase 9).
		$group( 'footer_opts', __( 'Footer layout', 'atelier-irisee-master-plugin' ) );
		self::number( 'ft_cols', __( 'Columns per row on computers (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 4, '' );
		self::choice(
			'ft_align',
			__( 'Text', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Left (standard)', 'atelier-irisee-master-plugin' ),
				'center' => __( 'Centre', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'ft_top', __( 'A "Back to top" button', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Place 1 = first. 0 = the standard place.', 'atelier-irisee-master-plugin' ) . '</p>';
		foreach ( self::footer_columns() as $col => $name ) {
			echo '<div class="aimp-ds-icon-row"><strong>' . esc_html( $name ) . '</strong>';
			self::check_box( 'ft_hide_' . $col, __( 'Hide', 'atelier-irisee-master-plugin' ) );
			self::number( 'ft_order_' . $col, __( 'Place', 'atelier-irisee-master-plugin' ), 0, 4, '' );
			echo '</div>';
		}
		echo '</fieldset>';

		$group( 'nl_opts', __( 'Newsletter', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'nl_wide', __( 'The button next to the field', 'atelier-irisee-master-plugin' ) );
		self::number( 'nl_popup', __( 'A pop-up with the sign-up after (0 = no pop-up)', 'atelier-irisee-master-plugin' ), 0, 120, __( 'seconds', 'atelier-irisee-master-plugin' ) );
		$field( 'nl_popup_title', __( 'Title of the pop-up', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		echo '<p class="description">' . esc_html__( 'The pop-up shows once per visitor, never to subscribers. The texts come from the footer settings and the Texts tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'gc_opts', __( 'Gift card choices', 'atelier-irisee-master-plugin' ) );
		self::number( 'gc_cols', __( 'Designs per row (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 6, '' );
		self::color( 'gc_chip', __( 'Chosen amount: background', 'atelier-irisee-master-plugin' ) );
		self::color( 'gc_chip_fg', __( 'Chosen amount: text', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'notice_opts', __( 'Messages', 'atelier-irisee-master-plugin' ) );
		self::number( 'nt_time', __( 'Messages disappear after (0 = standard, 4)', 'atelier-irisee-master-plugin' ), 0, 20, __( 'seconds', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'nt_pos',
			__( 'Place', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'In the page (standard)', 'atelier-irisee-master-plugin' ),
				'top'    => __( 'Floating at the top of the screen', 'atelier-irisee-master-plugin' ),
				'bottom' => __( 'Floating at the bottom of the screen', 'atelier-irisee-master-plugin' ),
			)
		);
		self::color( 'nt_bg', __( 'Background', 'atelier-irisee-master-plugin' ) );
		self::color( 'nt_fg', __( 'Text colour', 'atelier-irisee-master-plugin' ) );
		self::color( 'nt_err_bg', __( 'Errors: background', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Errors stay until they are closed.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'email_opts', __( 'Emails', 'atelier-irisee-master-plugin' ) );
		self::color( 'em_gold', __( 'Gold (titles, lines, links)', 'atelier-irisee-master-plugin' ) );
		self::color( 'em_brown', __( 'Text colour', 'atelier-irisee-master-plugin' ) );
		self::color( 'em_bg', __( 'Background around the email', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'em_logo', __( 'Logo at the top', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'em_noframe', __( 'No gold frame around the email', 'atelier-irisee-master-plugin' ) );
		$field( 'em_footer', __( 'An extra line at the bottom', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		echo '<p class="description">' . esc_html__( 'For the WooCommerce emails (orders, accounts), when "Atelier Irisee style for WooCommerce emails" is on in the settings. Emails cannot be shown in the preview; they use what is published.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		// SEO, speed and dashboard (phase 10).
		$group( 'seo_opts', __( 'Search engines', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'seo_noindex', __( 'Filtered shop pages and search results stay out of search engines', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'seo_og', __( 'A picture and text when a page is shared (Open Graph; not with an SEO plugin)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'seo_brand', __( 'The brand in the product data for Google', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'speed_opts', __( 'Speed', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'sp_prefetch', __( 'Load the next page while the mouse is on a link', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'sp_noemoji', __( 'No emoji script (the browser shows emoji itself)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'sp_noembed', __( 'No embed script (for showing other sites\' posts in a page)', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'These take effect for visitors after publishing (LiteSpeed is emptied then).', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'admin_opts', __( 'Dashboard', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ad_nobar', __( 'No "Design studio" in the admin bar on the site', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( '"Design studio" in the admin bar opens the studio with the page you are on.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		// Cart, checkout, account, login, favourites (phase 8).
		$group( 'cart_opts', __( 'Cart page', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_nocoupon', __( 'No discount code field', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_noupdate', __( 'No "Update cart" button', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_notrust', __( 'No delivery line under the totals', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_nopayments', __( 'No payment logos', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_nocontinue', __( 'No "Continue shopping"', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_nocross', __( 'No "You may also like"', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_nopopular', __( 'No "Popular right now" in an empty cart', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_left', __( 'The totals on the left (computers)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'cart_nosticky', __( 'The totals scroll with the page', 'atelier-irisee-master-plugin' ) );
		self::number( 'cart_thumb', __( 'Size of the pictures (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 160, 'px' );
		echo '</fieldset>';

		$group( 'co_opts', __( 'Checkout', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'co_nosteps', __( 'No steps above the checkout', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'co_left', __( '"Your order" on the left (computers)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'co_closed', __( '"Your order" starts folded', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'co_nocodes', __( 'No discount code or gift card box', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'co_nonotes', __( 'No order notes field', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'co_noaccount', __( 'No "Create an account" after ordering', 'atelier-irisee-master-plugin' ) );
		$field( 'co_thanks', __( 'An extra line on the thank-you page', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
		echo '</fieldset>';

		$group( 'acc_opts', __( 'My account', 'atelier-irisee-master-plugin' ) );
		$acc_tabs = class_exists( 'AIMP_Account' ) ? AIMP_Account::tabs() : array();
		$first    = array( '' => isset( $acc_tabs['personal'] ) ? $acc_tabs['personal'] . ' (' . $standard . ')' : $standard );
		foreach ( $acc_tabs as $tab => $name ) {
			if ( 'personal' !== $tab ) {
				$first[ $tab ] = $name;
			}
		}
		self::choice( 'acc_first', __( 'Opens with', 'atelier-irisee-master-plugin' ), $first );
		self::check_box( 'acc_top', __( 'The tabs above the page (computers)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'acc_nohello', __( 'No "Hello …" at the top', 'atelier-irisee-master-plugin' ) );
		$sub( __( 'Hide a tab', 'atelier-irisee-master-plugin' ) );
		foreach ( $acc_tabs as $tab => $name ) {
			self::check_box( 'acc_hide_' . $tab, $name );
		}
		echo '</fieldset>';

		$group( 'log_opts', __( 'Log in and register', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'log_noimage', __( 'No picture next to the form', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'log_nologo', __( 'No logo above the form', 'atelier-irisee-master-plugin' ) );
		self::number( 'log_width', __( 'Width of the form (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 1000, 'px' );
		self::color( 'log_overlay', __( 'Colour behind the pop-up', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The picture, logo and texts themselves are set under WooCommerce → Login & registration.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'fav_opts', __( 'Favourites', 'atelier-irisee-master-plugin' ) );
		$sub( __( 'Cards per row (0 = standard)', 'atelier-irisee-master-plugin' ) );
		self::number( 'fav_cols', $computer, 0, 8, '' );
		self::number( 'fav_pcols', $phone, 0, 3, '' );
		self::color( 'fav_heart', __( 'Colour of the hearts (everywhere)', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'fav_notoast', __( 'No message after adding a favourite', 'atelier-irisee-master-plugin' ) );
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

		// The contact page and the home page blocks: their texts are in the Texts block of the part.
		$texts_note = '<p class="description">' . esc_html__( 'The texts of this block are in its Texts block above.', 'atelier-irisee-master-plugin' ) . '</p>';
		$group( 'ct_opts', __( 'Contact page', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The email address, phone number, address and social media come from the footer settings.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::check_box( 'ct_formleft', __( 'The form on the left, the contact details on the right', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ct_nowhatsapp', __( 'No WhatsApp link', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ct_noroute', __( 'No "Plan your route" link', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ct_nosocials', __( 'No social media', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ct_noorder', __( 'No order number field', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ct_noconsent', __( 'No privacy tick box', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ct_nocopy', __( 'Do not send the customer a copy', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ct_nofaq', __( 'No questions and answers under the form', 'atelier-irisee-master-plugin' ) );
		echo '</fieldset>';

		$group( 'faq_opts', __( 'Questions and answers', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'faq_open', __( 'The first question open', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'faq_single', __( 'Only one answer open at a time', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The same questions are on the contact page and in the questions block of the home page.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'hc_opts', __( 'Shop by category', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'hc_notitle', __( 'No title above the tiles', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The tiles are the overview cards of the All products page: click them for the large tile, the order and the columns.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'rv_opts', __( 'Reviews', 'atelier-irisee-master-plugin' ) );
		self::number( 'rv_min', __( 'Product reviews from (stars)', 'atelier-irisee-master-plugin' ), 1, 5, '★' );
		self::number( 'rv_count', __( 'At most (0 = standard, 12)', 'atelier-irisee-master-plugin' ), 0, 24, '' );
		self::check_box( 'rv_noproducts', __( 'Only your own testimonials, no product reviews', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'rv_noproduct', __( 'Without the product name', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'rv_noaverage', __( 'Without the average score', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The block stays hidden while there is nothing to show. The average is only shown when it reaches the stars chosen above.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'mk_opts', __( 'Meet the maker', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'mk_img', __( 'Picture', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'mk_img2', __( 'Small round picture (e.g. the mascot)', 'atelier-irisee-master-plugin' ) );
		self::number( 'mk_fx', __( 'Focal point: left / right', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		self::number( 'mk_fy', __( 'Focal point: top / bottom', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		echo '<button type="button" class="button-link" data-clear="mk_fx,mk_fy">' . esc_html( $standard ) . '</button>';
		self::number( 'mk_fx2', __( 'Round picture: focal point left / right', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		self::number( 'mk_fy2', __( 'Round picture: focal point top / bottom', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
		self::check_box( 'mk_right', __( 'The picture on the right', 'atelier-irisee-master-plugin' ) );
		$field( 'mk_url', __( 'The button goes to (no address = no button)', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
		echo $texts_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '</fieldset>';

		$group( 'nw_opts', __( 'New in', 'atelier-irisee-master-plugin' ) );
		self::number( 'nw_count', __( 'Products (0 = standard, 8)', 'atelier-irisee-master-plugin' ), 0, 16, '' );
		self::choice(
			'nw_type',
			__( 'Which products', 'atelier-irisee-master-plugin' ),
			array(
				''             => __( 'All (standard)', 'atelier-irisee-master-plugin' ),
				'fabrics'      => __( 'Fabrics', 'atelier-irisee-master-plugin' ),
				'patterns'     => __( 'Patterns', 'atelier-irisee-master-plugin' ),
				'haberdashery' => __( 'Haberdashery', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'nw_instock', __( 'Only products in stock', 'atelier-irisee-master-plugin' ) );
		$field( 'nw_url', __( '"View all" goes to (standard: the shop page, newest first)', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
		echo '</fieldset>';

		$group( 'hn_opts', __( 'Newsletter block', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'hn_img', __( 'Background picture', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'hn_nooffer', __( 'Do not show the welcome discount', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'hn_showsubs', __( 'Also show the block to visitors who are subscribed already', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The welcome discount is the one of Settings → Discounts: new customers get a personal code after creating an account. It is not shown to logged-in visitors or when the discount is 0.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'fq_opts', __( 'Questions block', 'atelier-irisee-master-plugin' ) );
		self::number( 'fq_count', __( 'Questions shown (0 = standard, 4)', 'atelier-irisee-master-plugin' ), 0, 12, '' );
		$field( 'fq_url', __( 'The button goes to (standard: the page with the contact form)', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
		echo '</fieldset>';

		$group( 'br_opts', __( 'Brands', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'br_names', __( 'Names instead of logos', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'br_gray', __( 'Logos in grey, in colour when pointed at', 'atelier-irisee-master-plugin' ) );
		foreach ( self::brands() as $brand_id => $brand_name ) {
			self::picture_field( 'br_img_' . $brand_id, $brand_name );
		}
		echo '<p class="description">' . esc_html__( 'Only brands with products are shown. A picture chosen here goes before the brand\'s own picture (Products → Brands); without either, the name is shown.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'ab_opts', __( 'About page', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ab_right', __( 'The first chapter with its picture on the right', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ab_novalues', __( 'No "What we stand for" tiles', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ab_noshop', __( 'No button to the shop', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ab_nokit', __( 'No button to the customizer', 'atelier-irisee-master-plugin' ) );
		echo $texts_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '</fieldset>';

		$group( 'ab_chapters', __( 'Pictures of the chapters', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'A chapter without a title and text is not shown; one without a picture shows its text in the middle.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::check_box( 'ab_line', __( 'A dotted gold line behind the pictures', 'atelier-irisee-master-plugin' ) );
		for ( $n = 1; $n <= 4; $n++ ) {
			/* translators: %d: chapter number */
			echo '<p class="aimp-ds-sub">' . esc_html( sprintf( __( 'Chapter %d', 'atelier-irisee-master-plugin' ), $n ) ) . '</p>';
			self::picture_field( 'ab_img_' . $n, __( 'Picture', 'atelier-irisee-master-plugin' ) );
			self::number( 'ab_fx_' . $n, __( 'Focal point: left / right', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
			self::number( 'ab_fy_' . $n, __( 'Focal point: top / bottom', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
			echo '<button type="button" class="button-link" data-clear="' . esc_attr( 'ab_fx_' . $n . ',ab_fy_' . $n ) . '">' . esc_html( $standard ) . '</button>';
			self::choice(
				'ab_shape_' . $n,
				__( 'Shape', 'atelier-irisee-master-plugin' ),
				array(
					''       => 3 === $n ? __( 'Round (standard)', 'atelier-irisee-master-plugin' ) : __( 'Arch (standard)', 'atelier-irisee-master-plugin' ),
					'arch'   => __( 'Arch', 'atelier-irisee-master-plugin' ),
					'round'  => __( 'Round', 'atelier-irisee-master-plugin' ),
					'square' => __( 'Rounded corners', 'atelier-irisee-master-plugin' ),
				)
			);
			$field( 'ab_url_' . $n, __( 'The button goes to (no address = no button)', 'atelier-irisee-master-plugin' ), 'url', 'https://' );
			self::check_box( 'ab_hide_' . $n, __( 'Hide this chapter', 'atelier-irisee-master-plugin' ) );
		}
		echo '</fieldset>';

		$group( 'ph_page', __( 'Picture or video of this page', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description aimp-ds-ph-none">' . esc_html__( 'This page has no page header of its own (the home page, or a page with the hero).', 'atelier-irisee-master-plugin' ) . '</p>';
		$video_field = function ( $key, $label ) use ( $standard ) {
			printf(
				'<div class="aimp-ds-image" data-image="%1$s"><span class="aimp-ds-file-name"></span><button type="button" class="button aimp-ds-image-choose" data-image-key="%1$s" data-media="video">%2$s</button><button type="button" class="button-link" data-clear="%1$s">%3$s</button></div>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_html( $standard )
			);
		};
		$header_pages = class_exists( 'AIMP_Page_Header' ) ? AIMP_Page_Header::pages() : array();
		$header_pages['product'] = __( 'All product pages (the title is the product\'s name)', 'atelier-irisee-master-plugin' );
		$header_pages['other']   = __( 'Other pages: shop categories, blog, search results', 'atelier-irisee-master-plugin' );
		foreach ( $header_pages as $page_id => $page_title ) {
			$p = 'ph_' . $page_id . '_';
			echo '<div class="aimp-ds-ph-page" data-ph-page="' . esc_attr( (string) $page_id ) . '">';
			echo '<p class="aimp-ds-sub">' . esc_html( $page_title ) . '</p>';
			if ( is_int( $page_id ) ) {
				self::check_box( $p . 'off', __( 'No page header on this page', 'atelier-irisee-master-plugin' ) );
			}
			self::choice(
				$p . 'mode',
				__( 'Kind', 'atelier-irisee-master-plugin' ),
				array(
					''      => __( 'Picture + title over it (standard)', 'atelier-irisee-master-plugin' ),
					'baked' => __( 'Picture with the title already in it', 'atelier-irisee-master-plugin' ),
				)
			);
			self::picture_field( $p . 'img', __( 'Picture (wide, at least 1920 px)', 'atelier-irisee-master-plugin' ) );
			self::picture_field( $p . 'pimg', __( 'Other picture for phones (optional)', 'atelier-irisee-master-plugin' ) );
			$video_field( $p . 'video', __( 'Video instead of the picture (MP4, without sound; the picture shows until it plays)', 'atelier-irisee-master-plugin' ) );
			$video_field( $p . 'pvideo', __( 'Other video for phones (optional)', 'atelier-irisee-master-plugin' ) );
			self::number( $p . 'fx', __( 'Focal point: left / right', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
			self::number( $p . 'fy', __( 'Focal point: top / bottom', 'atelier-irisee-master-plugin' ), 0, 100, '%' );
			if ( 'product' !== $page_id ) {
				$field( $p . 'title', __( 'Title (empty = the page title)', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
			}
			$field( $p . 'sub', __( 'Line under the title', 'atelier-irisee-master-plugin' ), 'text', $lang_hint );
			echo '</div>';
		}
		echo '</fieldset>';

		$group( 'ph_look', __( 'Where and look of all page headers', 'atelier-irisee-master-plugin' ) );
		self::choice(
			'ph_where',
			__( 'Where', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'Automatically at the top of every page (standard)', 'atelier-irisee-master-plugin' ),
				'manual' => __( 'Only where I place the shortcode [atelier_irisee_page_header]', 'atelier-irisee-master-plugin' ),
			)
		);
		self::check_box( 'ph_noproducts', __( 'Not on product pages', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ph_nocommerce', __( 'Not on the cart, checkout and my account', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ph_noother', __( 'Not on shop categories, blog and search results', 'atelier-irisee-master-plugin' ) );
		self::check_box( 'ph_hideempty', __( 'Hide the header where no picture or video is chosen', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'Never on the home page and pages with the hero. A single page can be left out in its own block above.', 'atelier-irisee-master-plugin' ) . '</p>';
		self::choice(
			'ph_height',
			__( 'Height', 'atelier-irisee-master-plugin' ),
			array(
				''      => __( 'Medium (standard)', 'atelier-irisee-master-plugin' ),
				'small' => __( 'Small', 'atelier-irisee-master-plugin' ),
				'large' => __( 'Large', 'atelier-irisee-master-plugin' ),
				'full'  => __( 'Whole screen', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice(
			'ph_pheight',
			__( 'Height on phones', 'atelier-irisee-master-plugin' ),
			array(
				''       => __( 'As on computers (standard)', 'atelier-irisee-master-plugin' ),
				'small'  => __( 'Small', 'atelier-irisee-master-plugin' ),
				'medium' => __( 'Medium', 'atelier-irisee-master-plugin' ),
				'large'  => __( 'Large', 'atelier-irisee-master-plugin' ),
				'full'   => __( 'Whole screen', 'atelier-irisee-master-plugin' ),
			)
		);
		self::choice(
			'ph_align',
			__( 'Place of the title', 'atelier-irisee-master-plugin' ),
			array(
				''           => __( 'In the middle (standard)', 'atelier-irisee-master-plugin' ),
				'left'       => __( 'On the left', 'atelier-irisee-master-plugin' ),
				'bottomleft' => __( 'At the bottom left', 'atelier-irisee-master-plugin' ),
			)
		);
		self::color( 'ph_tint', __( 'Tint over the picture', 'atelier-irisee-master-plugin' ) );
		self::number( 'ph_strength', __( 'Strength of the tint', 'atelier-irisee-master-plugin' ), 0, 90, '%' );
		self::color( 'ph_color', __( 'Colour of the title', 'atelier-irisee-master-plugin' ) );
		self::number( 'ph_size', __( 'Size of the title (0 = standard)', 'atelier-irisee-master-plugin' ), 0, 120, 'px' );
		self::check_box( 'ph_crumbs', __( 'A breadcrumb above the title (Home › page)', 'atelier-irisee-master-plugin' ) );
		echo '<p class="description">' . esc_html__( 'The title in the picture is still on the page for search engines and screen readers. Without a picture the header is a calm gold band with the title.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '</fieldset>';

		$group( 'ab_closing', __( 'Closing', 'atelier-irisee-master-plugin' ) );
		self::picture_field( 'ab_sign_img', __( 'Small round picture next to the signature (e.g. Klaus)', 'atelier-irisee-master-plugin' ) );
		echo $texts_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '</fieldset>';
	}

	/**
	 * @param string $classes Classes of the dashboard's body.
	 * @return string
	 */
	public static function body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only the screen's look.
		return isset( $_GET['page'] ) && self::PAGE === $_GET['page'] ? $classes . ' aimp-ds-screen' : $classes;
	}

	public static function quiet_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'aimp-design' ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'network_admin_notices' );
			remove_all_actions( 'user_admin_notices' );
		}
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
							<p class="aimp-ds-device-note"></p>
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
			// Texts of the Atelier Irisee settings: the draft wording and what is published now.
			'draftSettings' => (object) self::data()['draft_settings'],
			'liveSettings'  => (object) array_combine(
				array_keys( self::setting_keys() ),
				array_map( array( __CLASS__, 'setting_value' ), array_keys( self::setting_keys() ) )
			),
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
		$settings = self::posted_json( 'settings' );
		if ( null !== $settings ) {
			$changes['draft_settings'] = self::clean_settings( $settings );
		}
		self::store( $changes );
		wp_send_json_success( self::state() );
	}

	/**
	 * While you change a setting: the "Whole site" and option CSS of the styles in the studio, at once, without
	 * saving (the preview shows a focal point or a colour right away; the save follows on its own).
	 */
	public static function ajax_css() {
		self::check();
		$styles = self::clean( self::posted_json( 'styles' ) );
		wp_send_json_success( array( 'css' => self::site_css( $styles ) . self::opts_css( $styles ) ) );
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
		// Texts of the settings: into the settings themselves.
		$settings = self::posted_json( 'settings' );
		$data     = self::data();
		self::publish_settings( null !== $settings ? self::clean_settings( $settings ) : (array) $data['draft_settings'] );
		self::store(
			array(
				'live'        => null !== $styles ? self::clean( $styles ) : self::draft(),
				'live_texts'  => null !== $texts ? self::clean_texts( $texts ) : self::draft_texts(),
				'draft'       => null,
				'draft_texts' => null,
				'live_time'   => time(),
				'draft_time'  => 0,
				'draft_settings' => array(),
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
				'draft'          => null,
				'draft_texts'    => null,
				'draft_time'     => 0,
				'draft_settings' => array(),
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

	/**
	 * A text translated into the empty languages (with the AI pictures key: Gemini or OpenAI).
	 */
	public static function ajax_translate() {
		self::check();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in check().
		$text = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		$from = isset( $_POST['from'] ) ? sanitize_key( $_POST['from'] ) : 'nl';
		$to   = isset( $_POST['to'] ) ? array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_POST['to'] ) ) ) ) : array();
		// phpcs:enable
		if ( '' === trim( $text ) || ! $to || ! class_exists( 'AIMP_AI_Looks' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to translate.', 'atelier-irisee-master-plugin' ) ) );
		}
		$result = AIMP_AI_Looks::translate( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 4000 ) : substr( $text, 0, 4000 ), $from, $to );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
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
