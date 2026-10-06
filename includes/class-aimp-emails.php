<?php
/**
 * WooCommerce emails in the Atelier Irisee style (setting "Emails" under Site look): gold and brown
 * colours, the site logo at the top, the company details at the bottom and gold double-line buttons.
 * The plugin's own emails use WooCommerce's mail wrapper, so they get the same look.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Emails {

	const GOLD  = '#b38f4f';
	const BROWN = '#613907';

	public static function init() {
		if ( ! AIMP_Settings::get( 'email_style' ) ) {
			return;
		}
		$colours = array(
			'woocommerce_email_base_color'            => self::GOLD,
			'woocommerce_email_background_color'      => '#faf6ef',
			'woocommerce_email_body_background_color' => '#ffffff',
			'woocommerce_email_text_color'            => self::BROWN,
		);
		foreach ( $colours as $option => $colour ) {
			add_filter(
				'pre_option_' . $option,
				function () use ( $colour ) {
					return $colour;
				}
			);
		}
		add_filter( 'option_woocommerce_email_header_image', array( __CLASS__, 'header_image' ) );
		add_filter( 'default_option_woocommerce_email_header_image', array( __CLASS__, 'header_image' ) );
		add_filter( 'woocommerce_email_footer_text', array( __CLASS__, 'footer_text' ), 20 );
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'styles' ), 20 );
	}

	/**
	 * The site logo when no header image is set under WooCommerce → Settings → Emails.
	 *
	 * @param mixed $value Header image URL.
	 * @return string
	 */
	public static function header_image( $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		$divi = function_exists( 'et_get_option' ) ? (string) et_get_option( 'divi_logo' ) : '';
		if ( '' !== $divi ) {
			return $divi;
		}
		$logo = get_theme_mod( 'custom_logo' );
		return $logo ? (string) wp_get_attachment_image_url( $logo, 'medium' ) : '';
	}

	/**
	 * Company details from the footer settings, then WooCommerce's own footer text.
	 *
	 * @param string $text Footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( ! class_exists( 'AIMP_Footer' ) ) {
			return $text;
		}
		$parts = array_filter(
			array(
				(string) AIMP_Footer::get( 'company' ),
				str_replace( array( "\r\n", "\n" ), ', ', (string) AIMP_Footer::get( 'address' ) ),
				(string) AIMP_Footer::get( 'phone' ),
				(string) AIMP_Footer::get( 'email' ),
			)
		);
		if ( ! $parts ) {
			return $text;
		}
		return esc_html( implode( ' · ', $parts ) ) . ( '' !== trim( (string) $text ) ? '<br>' . $text : '' );
	}

	/**
	 * @param string $css WooCommerce's email CSS.
	 * @return string
	 */
	public static function styles( $css ) {
		$gold  = self::GOLD;
		$brown = self::BROWN;
		return $css . "
			#template_header { background-color: #ffffff !important; border-bottom: 5px double {$gold}; }
			#template_header h1, #template_header h1 a { color: {$gold} !important; text-shadow: none; font-family: 'Trajan Pro', 'Cinzel', Georgia, 'Times New Roman', serif; letter-spacing: 0.04em; }
			#template_container { border: 5px double {$gold} !important; border-radius: 24px !important; box-shadow: none !important; overflow: hidden; }
			h2, h3 { color: {$gold} !important; font-family: 'Trajan Pro', 'Cinzel', Georgia, 'Times New Roman', serif; }
			body, #body_content_inner, td, th { color: {$brown}; }
			a { color: {$gold}; }
			.td, .address { border-color: rgba(179, 143, 79, 0.35) !important; color: {$brown}; }
			#template_footer #credit, #template_footer #credit p { color: {$brown}; }
			.button, a.button { display: inline-block; padding: 12px 26px; border: 4px double {$gold}; border-radius: 100px; color: {$brown} !important; background: transparent !important; font-weight: bold; text-decoration: none; }
		";
	}
}
