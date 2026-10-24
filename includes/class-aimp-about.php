<?php
/**
 * [atelier_irisee_about]: the "About" page. A head, the story in chapters (picture and text side by side, in turns
 * left and right), what the shop stands for in four tiles, and a closing with the quote, the signature and buttons
 * to the shop and the customizer. Full width, like the home page blocks.
 *
 * The texts and pictures are changed in the Design studio (About page).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_About {

	const TAG = 'atelier_irisee_about';

	/** Story chapters that can be filled in. */
	const CHAPTERS = 4;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
	}

	public static function register_assets() {
		// On the shared block styles (full width, titles, buttons).
		if ( ! wp_style_is( 'aimp-home-blocks', 'registered' ) ) {
			AIMP_Home_Blocks::register_assets();
		}
		wp_register_style( 'aimp-about', AIMP_PLUGIN_URL . 'assets/css/about.css', array( 'aimp-home-blocks' ), AIMP_VERSION );
		if ( AIMP_Blocks::page_has( array( self::TAG ) ) ) {
			wp_enqueue_style( 'aimp-about' );
		}
	}

	/**
	 * A Design studio setting of a part of the page.
	 *
	 * @param string $part    about, about-chapter or about-closing.
	 * @param string $key     Setting.
	 * @param mixed  $default When it is not set.
	 * @return mixed
	 */
	private static function design( $part, $key, $default = null ) {
		return AIMP_Blocks::design( $part, $key, $default );
	}

	/**
	 * A text with paragraphs: an empty line starts a new paragraph, a single line break stays a line break.
	 *
	 * @param string $text Text.
	 * @return string HTML.
	 */
	private static function paragraphs( $text ) {
		return wpautop( esc_html( $text ) );
	}

	/**
	 * A picture of the studio with its focal point.
	 *
	 * @param int    $id    Attachment.
	 * @param string $class Class.
	 * @param int    $fx    Focal point left / right (-1 = middle).
	 * @param int    $fy    Focal point top / bottom (-1 = middle).
	 * @return string
	 */
	private static function picture( $id, $class, $fx = -1, $fy = -1 ) {
		$image = wp_get_attachment_image( $id, 'large', false, array( 'class' => $class, 'loading' => 'lazy', 'alt' => '' ) );
		if ( '' !== $image && ( $fx >= 0 || $fy >= 0 ) ) {
			$image = preg_replace( '/^<img /', '<img style="object-position:' . ( $fx >= 0 ? (int) $fx : 50 ) . '% ' . ( $fy >= 0 ? (int) $fy : 50 ) . '%" ', $image );
		}
		return $image;
	}

	/**
	 * The story chapters that have a title or text.
	 *
	 * @return string
	 */
	private static function chapters() {
		$html  = '';
		$shown = 0;
		$right = (bool) self::design( 'about', 'ab_right' );
		for ( $n = 1; $n <= self::CHAPTERS; $n++ ) {
			$title = AIMP_Blocks::text( 'ab_c' . $n . '_title' );
			$text  = AIMP_Blocks::text( 'ab_c' . $n . '_text' );
			if ( ( '' === $title && '' === $text ) || self::design( 'about-chapter', 'ab_hide_' . $n ) ) {
				continue;
			}
			$picture = (int) self::design( 'about-chapter', 'ab_img_' . $n, 0 );
			// The shape: an arch like the product pictures, or round (standard for the third chapter, Klaus).
			$shape  = (string) self::design( 'about-chapter', 'ab_shape_' . $n, '' );
			$shape  = '' !== $shape ? $shape : ( 3 === $n ? 'round' : 'arch' );
			$url    = (string) self::design( 'about-chapter', 'ab_url_' . $n, '' );
			$button = AIMP_Blocks::text( 'ab_c' . $n . '_button' );
			// In turns left and right (Design studio: the first one on the right).
			$flip   = ( 1 === $shown % 2 ) !== $right;
			$html  .= '<article class="aimp-about-chapter aimp-about-chapter--' . $n . ' is-' . esc_attr( $shape ) . ( $flip ? ' is-flipped' : '' ) . ( $picture ? '' : ' has-no-picture' ) . '">';
			if ( $picture ) {
				$html .= '<div class="aimp-about-media">' . self::picture( $picture, 'aimp-about-image', (int) self::design( 'about-chapter', 'ab_fx_' . $n, -1 ), (int) self::design( 'about-chapter', 'ab_fy_' . $n, -1 ) ) . '</div>';
			}
			$html .= '<div class="aimp-about-text">';
			if ( '' !== $title ) {
				$html .= '<h2 class="aimp-about-chapter-title">' . esc_html( $title ) . '</h2>';
			}
			$html .= '<div class="aimp-about-story">' . self::paragraphs( $text ) . '</div>';
			if ( '' !== $url && '' !== $button ) {
				$html .= '<a class="aimp-block-button" href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a>';
			}
			$html .= '</div></article>';
			$shown++;
		}
		return '' !== $html ? '<div class="aimp-about-chapters">' . $html . '</div>' : '';
	}

	/**
	 * What the shop stands for: four tiles with an icon, a title and a line.
	 *
	 * @return string
	 */
	private static function values() {
		$values = self::design( 'about', 'ab_novalues' ) ? array() : AIMP_Blocks::pairs( 'ab_values' );
		if ( ! $values ) {
			return '';
		}
		$icons = array( 'sparkle', 'kit', 'palette', 'heart', 'paw', 'star' );
		$html  = '<ul class="aimp-about-values">';
		foreach ( $values as $i => $value ) {
			$html .= '<li class="aimp-about-value">' . AIMP_Blocks::icon( $icons[ $i % count( $icons ) ] ) .
				'<h3 class="aimp-about-value-title">' . esc_html( $value[0] ) . '</h3>' .
				( '' !== $value[1] ? '<p>' . esc_html( $value[1] ) . '</p>' : '' ) . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * The closing: the quote, the signature (with a small round picture) and the buttons.
	 *
	 * @return string
	 */
	private static function closing() {
		$quote   = AIMP_Blocks::text( 'ab_quote' );
		$sign    = AIMP_Blocks::text( 'ab_sign' );
		$picture = (int) self::design( 'about-closing', 'ab_sign_img', 0 );
		$buttons = '';
		if ( ! self::design( 'about', 'ab_noshop' ) && AIMP_Shop::page_for_type( 'all' ) ) {
			$buttons .= '<a class="aimp-block-button" href="' . esc_url( get_permalink( AIMP_Shop::page_for_type( 'all' ) ) ) . '">' . esc_html( AIMP_Blocks::text( 'ab_shop_button' ) ) . '</a>';
		}
		$kit = (int) AIMP_Settings::get( 'configurator_page' );
		if ( ! self::design( 'about', 'ab_nokit' ) && $kit && 'publish' === get_post_status( $kit ) ) {
			$buttons .= '<a class="aimp-block-button aimp-block-button--gold" href="' . esc_url( get_permalink( $kit ) ) . '">' . esc_html( AIMP_Blocks::text( 'ab_kit_button' ) ) . '</a>';
		}
		if ( '' === $quote && '' === $sign && '' === $buttons ) {
			return '';
		}
		$html = '<div class="aimp-about-closing">';
		if ( '' !== $quote ) {
			$html .= '<blockquote class="aimp-about-quote"><p>' . esc_html( $quote ) . '</p></blockquote>';
		}
		if ( '' !== $sign || $picture ) {
			$html .= '<div class="aimp-about-sign">' . ( $picture ? self::picture( $picture, 'aimp-about-sign-image' ) : '' ) .
				( '' !== $sign ? '<p class="aimp-about-signature">' . nl2br( esc_html( $sign ) ) . '</p>' : '' ) . '</div>';
		}
		if ( '' !== $buttons ) {
			$html .= '<div class="aimp-about-buttons">' . $buttons . '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * @param array|string $atts Attributes (none).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		if ( ! wp_style_is( 'aimp-about', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-about' );
		// The full width needs the width of the scrollbar (home-blocks.js).
		wp_enqueue_script( 'aimp-home-blocks' );

		$intro = AIMP_Blocks::text( 'ab_intro' );
		return '<section class="aimp-home-block aimp-about">' .
			'<header class="aimp-about-head"><p class="aimp-maker-kicker aimp-about-kicker">' . esc_html( AIMP_Blocks::text( 'ab_kicker' ) ) . '</p>' .
			'<h1 class="aimp-block-title aimp-about-title"><span>' . esc_html( AIMP_Blocks::text( 'ab_title' ) ) . '</span></h1>' .
			( '' !== $intro ? '<p class="aimp-about-intro">' . nl2br( esc_html( $intro ) ) . '</p>' : '' ) . '</header>' .
			self::chapters() . self::values() . self::closing() . '</section>';
	}
}
