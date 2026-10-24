<?php
/**
 * [atelier_irisee_page_header]: a full-width header at the top of a page, with a picture or a video (later, e.g.
 * the picture animated). Per page, in the Design studio (Page header):
 *
 * - "Picture + title": the picture fills the band under a light gold tint, with the title (and a line) over it;
 * - "Title in the picture": the picture with the title already in it, shown whole; the title is still on the page
 *   for search engines and screen readers.
 *
 * Without a picture: a calm gold band with the page title. The look (height, tint, place of the title) is shared by
 * all pages.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Page_Header {

	const TAG = 'atelier_irisee_page_header';

	/** Printed on this page already (automatically or by the shortcode). */
	private static $printed = false;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		// The list of pages with a header is kept until a page or post is saved.
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		// Divi: at the top of every page, above the content (Design studio → Page header → Where).
		add_action( 'et_before_main_content', array( __CLASS__, 'auto' ) );
	}

	public static function flush() {
		delete_transient( 'aimp_page_headers' );
	}

	public static function register_assets() {
		wp_register_style( 'aimp-page-header', AIMP_PLUGIN_URL . 'assets/css/page-header.css', array(), AIMP_VERSION );
		if ( ! wp_script_is( 'aimp-home-blocks', 'registered' ) ) {
			AIMP_Home_Blocks::register_assets();
		}
		if ( AIMP_Blocks::page_has( array( self::TAG ) ) ) {
			wp_enqueue_style( 'aimp-page-header' );
		}
	}

	/**
	 * The pages with a page header (their settings are per page): id => title.
	 *
	 * @return array
	 */
	public static function pages() {
		static $pages = null;
		if ( null !== $pages ) {
			return $pages;
		}
		$cached = function_exists( 'get_transient' ) ? get_transient( 'aimp_page_headers' ) : false;
		if ( is_array( $cached ) ) {
			$pages = $cached;
			return $pages;
		}
		$pages = array();
		if ( ! function_exists( 'get_posts' ) ) {
			return $pages;
		}
		// Every page (the header comes at the top of each one), not the home page.
		$front = (int) get_option( 'page_on_front' );
		foreach ( get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $id ) {
			if ( (int) $id !== $front ) {
				$pages[ (int) $id ] = get_the_title( $id );
			}
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'aimp_page_headers', $pages, DAY_IN_SECONDS );
		}
		return $pages;
	}

	/**
	 * A setting of the page header: per page (ph_{page}_{key}) or shared (ph_{key}).
	 *
	 * @param string   $key     Setting.
	 * @param mixed    $default When it is not set.
	 * @param int|null $page    The page (null: shared).
	 * @return mixed
	 */
	private static function design( $key, $default = null, $page = null ) {
		return AIMP_Blocks::design( 'page-header', null === $page ? 'ph_' . $key : 'ph_' . $page . '_' . $key, $default );
	}

	/**
	 * What is shown: the settings of this page (its id), of all product pages ("product") or of the other pages
	 * ("other": shop categories, blog, search, …), its title, and whether the title is the page's main heading (not
	 * on product pages and pages that have one of their own).
	 *
	 * @return array [ key, title, heading ]
	 */
	private static function context() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return array( 'product', get_the_title( get_queried_object_id() ), false );
		}
		if ( is_page() ) {
			$id      = (int) get_queried_object_id();
			$content = (string) get_post_field( 'post_content', $id );
			$own_h1  = false !== strpos( $content, '[atelier_irisee_about' ) || false !== strpos( $content, '[atelier_irisee_contact' );
			return array( $id, get_the_title( $id ), ! $own_h1 );
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return array( 'other', get_the_title( wc_get_page_id( 'shop' ) ), true );
		}
		if ( is_search() ) {
			/* translators: %s: what was searched */
			return array( 'other', sprintf( __( 'Search results for "%s"', 'atelier-irisee-master-plugin' ), get_search_query() ), true );
		}
		if ( is_404() ) {
			return array( 'other', __( 'Page not found', 'atelier-irisee-master-plugin' ), true );
		}
		if ( is_singular() ) {
			return array( 'other', get_the_title( get_queried_object_id() ), false );
		}
		if ( is_archive() ) {
			return array( 'other', wp_strip_all_tags( get_the_archive_title() ), true );
		}
		return array( 'other', get_bloginfo( 'name' ), false );
	}

	/**
	 * Whether this page gets the header automatically (Design studio → Page header → Where).
	 *
	 * @return bool
	 */
	private static function applies() {
		if ( is_admin() || is_feed() || is_front_page() || 'manual' === self::design( 'where', '' ) ) {
			return false;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return ! self::design( 'noproducts' );
		}
		if ( is_page() ) {
			$id      = (int) get_queried_object_id();
			$content = (string) get_post_field( 'post_content', $id );
			// The home page hero is a header of its own.
			if ( false !== strpos( $content, '[atelier_irisee_hero' ) || self::design( 'off', false, $id ) ) {
				return false;
			}
			$commerce = ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_account_page' ) && is_account_page() );
			return $commerce ? ! self::design( 'nocommerce' ) : true;
		}
		return ! self::design( 'noother' );
	}

	/**
	 * Divi (et_before_main_content): the header at the top of every page, above the content and full width.
	 */
	public static function auto() {
		if ( self::$printed || ! self::applies() ) {
			return;
		}
		echo self::render( self::context(), '', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * @param array|string $atts Attributes: title (another title), sub (a line under it).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => '',
				'sub'   => '',
			),
			$atts,
			self::TAG
		);
		// Already at the top of the page (automatic): not a second time.
		if ( self::$printed ) {
			return '';
		}
		return self::render( self::context(), $atts['title'], $atts['sub'] );
	}

	/**
	 * The header.
	 *
	 * @param array  $context   context().
	 * @param string $title_att Title from the shortcode.
	 * @param string $sub_att   Line from the shortcode.
	 * @return string
	 */
	private static function render( $context, $title_att, $sub_att ) {
		self::$printed = true;
		if ( ! wp_style_is( 'aimp-page-header', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-page-header' );
		// The full width (scrollbar) and the video (home-blocks.js).
		wp_enqueue_script( 'aimp-home-blocks' );

		list( $page, $page_title, $heading ) = $context;
		$atts    = array(
			'title' => $title_att,
			'sub'   => $sub_att,
		);
		$mode    = (string) self::design( 'mode', '', $page );
		$picture = (int) self::design( 'img', 0, $page );
		$phone   = (int) self::design( 'pimg', 0, $page );
		$video   = (int) self::design( 'video', 0, $page );
		$pvideo  = (int) self::design( 'pvideo', 0, $page );
		$fx      = (int) self::design( 'fx', -1, $page );
		$fy      = (int) self::design( 'fy', -1, $page );
		$title   = (string) self::design( 'title', '', $page );
		$title   = '' !== $title ? AIMP_Settings::localized( $title ) : ( '' !== $atts['title'] ? $atts['title'] : $page_title );
		$sub     = (string) self::design( 'sub', '', $page );
		$sub     = '' !== $sub ? AIMP_Settings::localized( $sub ) : $atts['sub'];
		// "Title in the picture" needs a picture (or a video); without one the title is shown as text.
		$baked   = 'baked' === $mode && ( $picture || $video );
		// Design studio: no header where no picture or video is chosen (in the studio's preview a slim band, to click).
		$empty = ! $picture && ! $video && self::design( 'hideempty' );
		if ( $empty && ! ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() ) ) {
			return '';
		}

		$image_url  = $picture ? wp_get_attachment_image_url( $picture, 'full' ) : '';
		$phone_url  = $phone ? wp_get_attachment_image_url( $phone, 'large' ) : '';
		$video_url  = $video ? wp_get_attachment_url( $video ) : '';
		$pvideo_url = $pvideo ? wp_get_attachment_url( $pvideo ) : '';
		$focus      = $fx >= 0 || $fy >= 0 ? 'object-position:' . ( $fx >= 0 ? $fx : 50 ) . '% ' . ( $fy >= 0 ? $fy : 50 ) . '%;' : '';

		// The shared look (Design studio → Page header).
		$height   = (string) self::design( 'height', '' );
		$pheight  = (string) self::design( 'pheight', '' );
		$align    = (string) self::design( 'align', '' );
		$style    = '';
		$tint     = (string) self::design( 'tint', '' );
		$strength = (int) self::design( 'strength', -1 );
		$color    = (string) self::design( 'color', '' );
		$size     = (int) self::design( 'size', 0 );
		$style   .= '' !== $tint ? '--ph-tint:' . $tint . ';' : '';
		$style   .= $strength >= 0 ? '--ph-strength:' . ( $strength / 100 ) . ';' : '';
		$style   .= '' !== $color ? '--ph-color:' . $color . ';' : '';
		$style   .= $size > 0 ? '--ph-size:' . $size . 'px;' : '';
		$classes  = array( 'aimp-page-header', $baked ? 'is-baked' : 'is-text' );
		$classes[] = ( $picture || $video ) ? 'has-media' : 'has-no-media';
		if ( $empty ) {
			$classes[] = 'is-empty-preview';
		}
		$classes[] = 'is-' . ( in_array( $height, array( 'small', 'large', 'full' ), true ) ? $height : 'medium' );
		$classes[] = 'is-phone-' . ( in_array( $pheight, array( 'small', 'medium', 'large', 'full' ), true ) ? $pheight : 'same' );
		$classes[] = 'is-' . ( in_array( $align, array( 'left', 'bottomleft' ), true ) ? $align : 'center' );

		$html = '<header class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';
		if ( $picture || $video ) {
			$html .= '<div class="aimp-page-header-media">';
			if ( $image_url ) {
				$html .= '<picture>' . ( $phone_url ? '<source media="(max-width: 700px)" srcset="' . esc_url( $phone_url ) . '">' : '' ) .
					'<img class="aimp-page-header-image" src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $baked ? $title : '' ) . '"' . ( '' !== $focus ? ' style="' . esc_attr( $focus ) . '"' : '' ) . ' fetchpriority="high"></picture>';
			}
			if ( $video_url ) {
				// The video gets its source from home-blocks.js: not with "reduced motion" or the data saver, and the
				// phone video on phones. Until it plays, the picture shows (poster).
				$html .= '<video class="aimp-page-header-video" muted loop playsinline preload="none" data-aimp-ph-video data-src="' . esc_url( $video_url ) . '"' .
					( $pvideo_url ? ' data-psrc="' . esc_url( $pvideo_url ) . '"' : '' ) . ( $image_url ? ' poster="' . esc_url( $image_url ) . '"' : '' ) .
					( '' !== $focus ? ' style="' . esc_attr( $focus ) . '"' : '' ) . ' aria-hidden="true"></video>';
			}
			$html .= '</div>';
		}
		if ( $baked ) {
			// The title in the picture: still on the page for search engines and screen readers (not where the page has a heading of its own).
			$html .= $heading ? '<h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>' : '';
		} else {
			$html .= '<div class="aimp-page-header-text">';
			if ( self::design( 'crumbs' ) ) {
				$html .= '<nav class="aimp-page-header-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'atelier-irisee-master-plugin' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'atelier-irisee-master-plugin' ) . '</a> <span aria-hidden="true">›</span> <span aria-current="page">' . esc_html( $title ) . '</span></nav>';
			}
			$tag   = $heading ? 'h1' : 'p';
			$html .= '<' . $tag . ' class="aimp-page-header-title"><span>' . esc_html( $title ) . '</span></' . $tag . '>';
			if ( '' !== $sub ) {
				$html .= '<p class="aimp-page-header-sub">' . esc_html( $sub ) . '</p>';
			}
			$html .= '</div>';
		}
		return $html . '</header>';
	}
}
