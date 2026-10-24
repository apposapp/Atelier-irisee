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

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		// The list of pages with a header is kept until a page or post is saved.
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
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
		foreach ( get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				's'              => '[' . self::TAG,
				'posts_per_page' => 60,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $id ) {
			$pages[ (int) $id ] = get_the_title( $id );
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
		if ( ! wp_style_is( 'aimp-page-header', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-page-header' );
		// The full width (scrollbar) and the video (home-blocks.js).
		wp_enqueue_script( 'aimp-home-blocks' );

		$page    = (int) get_queried_object_id();
		$page    = $page ? $page : (int) get_the_ID();
		$mode    = (string) self::design( 'mode', '', $page );
		$picture = (int) self::design( 'img', 0, $page );
		$phone   = (int) self::design( 'pimg', 0, $page );
		$video   = (int) self::design( 'video', 0, $page );
		$pvideo  = (int) self::design( 'pvideo', 0, $page );
		$fx      = (int) self::design( 'fx', -1, $page );
		$fy      = (int) self::design( 'fy', -1, $page );
		$title   = (string) self::design( 'title', '', $page );
		$title   = '' !== $title ? AIMP_Settings::localized( $title ) : ( '' !== $atts['title'] ? $atts['title'] : get_the_title( $page ) );
		$sub     = (string) self::design( 'sub', '', $page );
		$sub     = '' !== $sub ? AIMP_Settings::localized( $sub ) : $atts['sub'];
		// "Title in the picture" needs a picture (or a video); without one the title is shown as text.
		$baked   = 'baked' === $mode && ( $picture || $video );

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
			$html .= '<h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>';
		} else {
			$html .= '<div class="aimp-page-header-text">';
			if ( self::design( 'crumbs' ) ) {
				$html .= '<nav class="aimp-page-header-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'atelier-irisee-master-plugin' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'atelier-irisee-master-plugin' ) . '</a> <span aria-hidden="true">›</span> <span aria-current="page">' . esc_html( $title ) . '</span></nav>';
			}
			$html .= '<h1 class="aimp-page-header-title"><span>' . esc_html( $title ) . '</span></h1>';
			if ( '' !== $sub ) {
				$html .= '<p class="aimp-page-header-sub">' . esc_html( $sub ) . '</p>';
			}
			$html .= '</div>';
		}
		return $html . '</header>';
	}
}
