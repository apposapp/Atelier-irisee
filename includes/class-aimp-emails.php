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
		// Email best practices (Litmus), for every email: a preview line, a clear sender and a reply address.
		add_action( 'woocommerce_email_header', array( __CLASS__, 'preheader' ), 5, 2 );
		add_filter( 'woocommerce_email_from_name', array( __CLASS__, 'from_name' ), 20 );
		add_filter( 'woocommerce_email_headers', array( __CLASS__, 'reply_to' ), 20, 4 );

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
	 * A button that looks right in every mail program, Outlook included: a table cell with the
	 * background and border, and the link inside.
	 *
	 * @param string $text Button text.
	 * @param string $url  Link.
	 * @return string
	 */
	public static function button( $text, $url ) {
		return sprintf(
			'<table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center" style="margin:24px auto;border-collapse:separate"><tr><td align="center" bgcolor="#ffffff" style="border:3px double %1$s;border-radius:100px;mso-padding-alt:12px 28px"><a href="%2$s" target="_blank" style="display:inline-block;padding:12px 28px;min-height:20px;color:%3$s;font-weight:bold;font-size:16px;line-height:20px;text-decoration:none;border-radius:100px">%4$s</a></td></tr></table>',
			self::GOLD,
			esc_url( $url ),
			self::BROWN,
			esc_html( $text )
		);
	}

	/**
	 * The preview line inboxes show after the subject, per kind of email.
	 *
	 * @param WC_Email|null $email Email.
	 * @return string
	 */
	private static function preheader_text( $email ) {
		if ( ! $email instanceof WC_Email ) {
			return '';
		}
		if ( 'aimp_newsletter' === $email->id && ! empty( $email->newsletter['preheader'] ) ) {
			return (string) $email->newsletter['preheader'];
		}
		$texts = array(
			'customer_on_hold_order'     => __( 'We have received your order.', 'atelier-irisee-master-plugin' ),
			'customer_processing_order'  => __( 'Thank you! We are preparing your order with care.', 'atelier-irisee-master-plugin' ),
			'customer_completed_order'   => __( 'Your order is on its way.', 'atelier-irisee-master-plugin' ),
			'customer_refunded_order'    => __( 'Your refund has been processed.', 'atelier-irisee-master-plugin' ),
			'customer_invoice'           => __( 'The details of your order.', 'atelier-irisee-master-plugin' ),
			'customer_note'              => __( 'A message about your order.', 'atelier-irisee-master-plugin' ),
			'customer_new_account'       => __( 'Welcome to Atelier Irisée.', 'atelier-irisee-master-plugin' ),
			'customer_reset_password'    => __( 'Choose a new password.', 'atelier-irisee-master-plugin' ),
			'aimp_newsletter_confirm'    => __( 'Confirm your subscription with one click.', 'atelier-irisee-master-plugin' ),
		);
		return isset( $texts[ $email->id ] ) ? $texts[ $email->id ] : '';
	}

	/**
	 * Hidden preview text, printed first so inboxes show it after the subject.
	 *
	 * @param string        $heading Heading.
	 * @param WC_Email|null $email   Email.
	 */
	public static function preheader( $heading, $email = null ) {
		$text = self::preheader_text( $email );
		if ( '' === $text ) {
			return;
		}
		// The spacers keep the inbox from filling the preview with the email's first words.
		echo '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">' . esc_html( $text ) . str_repeat( '&#847;&zwnj;&nbsp;', 40 ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed entities.
	}

	/**
	 * A recognisable sender name when WooCommerce still has an empty or generic one.
	 *
	 * @param string $name Sender name.
	 * @return string
	 */
	public static function from_name( $name ) {
		$name = trim( wp_specialchars_decode( (string) $name, ENT_QUOTES ) );
		return ( '' === $name || 'WordPress' === $name ) ? 'Atelier Irisée' : $name;
	}

	/**
	 * Customer emails: replies go to the shop (the email in the footer settings).
	 *
	 * @param string        $headers Headers.
	 * @param string        $id      Email ID.
	 * @param mixed         $object  Object.
	 * @param WC_Email|null $email   Email.
	 * @return string
	 */
	public static function reply_to( $headers, $id = '', $object = null, $email = null ) {
		$shop = class_exists( 'AIMP_Footer' ) ? sanitize_email( (string) AIMP_Footer::get( 'email' ) ) : '';
		if ( '' === $shop || ! is_email( $shop ) || false !== stripos( (string) $headers, 'reply-to:' ) ) {
			return $headers;
		}
		if ( $email instanceof WC_Email && ! $email->is_customer_email() ) {
			return $headers;
		}
		return $headers . 'Reply-To: ' . self::from_name( get_option( 'woocommerce_email_from_name' ) ) . ' <' . $shop . ">\r\n";
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
			#template_container { max-width: 600px !important; }
			#template_header_image img { background: #ffffff; padding: 8px 14px; border-radius: 12px; }
			@media screen and (max-width: 600px) {
				#template_container, #template_body, #template_header, #template_footer { width: 100% !important; }
				#body_content_inner, #body_content_inner p, #body_content_inner td { font-size: 16px !important; line-height: 1.5 !important; }
				#body_content table td { padding: 12px !important; }
				#template_header h1 { font-size: 24px !important; line-height: 1.25 !important; }
				.button, a.button, td a[target] { min-height: 44px !important; }
			}
			@media (prefers-color-scheme: dark) {
				#wrapper { background-color: #2b2118 !important; }
				#template_container, #template_header, #body_content, #template_body { background-color: #fbf7f0 !important; }
				#body_content_inner, #body_content_inner p, #body_content_inner td, #body_content_inner th { color: {$brown} !important; }
			}
		";
	}
}
