<?php
/**
 * "Let's connect": a band at the end of every email to customers, with the social media (gold round icons), the
 * website as a button, the email address and address (never the phone number), and when filled in a hashtag line,
 * a review link and a newsletter sign-up link.
 *
 * WooCommerce's emails (and the plugin's own WC_Email emails) get it through woocommerce_email_footer; the plugin's
 * own wrapped emails (contact copy, gift cards, stock alerts) add html() themselves. Settings: Design studio → Emails.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Email_Connect {

	const NETWORKS = array(
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'tiktok'    => 'TikTok',
		'pinterest' => 'Pinterest',
	);

	public static function init() {
		// Before WooCommerce closes the email's body (priority 10), so the band sits in the white body.
		add_action( 'woocommerce_email_footer', array( __CLASS__, 'footer' ), 9 );
	}

	private static function design( $key ) {
		return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( 'site-emails', $key, '' ) : '';
	}

	public static function enabled() {
		return ! self::design( 'em_noconnect' );
	}

	/**
	 * @param WC_Email|null $email Email.
	 */
	public static function footer( $email = null ) {
		if ( ! self::enabled() ) {
			return;
		}
		$signup = true;
		if ( $email instanceof WC_Email ) {
			// Only for customers: not the shop's own emails (new order, cancelled, …).
			if ( ! $email->is_customer_email() ) {
				return;
			}
			if ( in_array( $email->id, array( 'aimp_newsletter', 'aimp_newsletter_confirm' ), true ) ) {
				$signup = false;
			} elseif ( class_exists( 'AIMP_Footer' ) && AIMP_Footer::is_subscribed( (string) $email->get_recipient() ) ) {
				$signup = false;
			}
		}
		$lang = self::mail_language( $email );
		$html = ( '' !== $lang && class_exists( 'AIMP_I18n' ) ) ? AIMP_I18n::with_language(
			$lang,
			function () use ( $signup ) {
				return self::html( $signup );
			}
		) : self::html( $signup );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
	}

	/**
	 * The language of the email itself, so the band speaks it too. The plugin's own emails (newsletter, gift card,
	 * replacement, reminder…) already run in their language: ''. WooCommerce's own emails are written in the
	 * language WordPress uses for them.
	 *
	 * @param WC_Email|null $email Email.
	 * @return string Language code, or '' to keep the current one.
	 */
	private static function mail_language( $email ) {
		if ( ! class_exists( 'AIMP_I18n' ) || ( $email instanceof WC_Email && 0 === strpos( (string) $email->id, 'aimp_' ) ) ) {
			return '';
		}
		$code = strtolower( substr( (string) get_locale(), 0, 2 ) );
		return AIMP_I18n::is_valid( $code ) ? $code : '';
	}

	/**
	 * The band.
	 *
	 * @param bool   $signup Show the newsletter sign-up line.
	 * @param string $to     Recipient (no sign-up line for a subscriber).
	 * @return string '' when switched off.
	 */
	public static function html( $signup = true, $to = '' ) {
		if ( ! self::enabled() ) {
			return '';
		}
		if ( $signup && '' !== $to && class_exists( 'AIMP_Footer' ) && AIMP_Footer::is_subscribed( $to ) ) {
			$signup = false;
		}
		$gold  = class_exists( 'AIMP_Emails' ) ? AIMP_Emails::gold() : '#b38f4f';
		$brown = class_exists( 'AIMP_Emails' ) ? AIMP_Emails::brown() : '#613907';
		$bg    = class_exists( 'AIMP_Emails' ) ? AIMP_Emails::background() : '#faf6ef';
		$img   = AIMP_PLUGIN_URL . 'assets/img/email/';
		$font  = "'Cinzel','Trajan Pro',Georgia,'Times New Roman',serif";
		$p     = 'margin:0;font-size:14px;line-height:1.6;color:' . $brown . ';text-align:center';

		$html  = '<table role="presentation" class="aimp-connect" width="100%" border="0" cellpadding="0" cellspacing="0" style="margin:40px 0 0;border-collapse:separate"><tr>';
		$html .= '<td align="center" bgcolor="' . esc_attr( $bg ) . '" style="padding:26px 22px 24px;background-color:' . esc_attr( $bg ) . ';border-radius:20px;text-align:center">';
		$html .= '<img src="' . esc_url( $img . 'lijn.png' ) . '" width="280" alt="" style="display:block;width:100%;max-width:280px;height:auto;margin:0 auto 10px;border:0">';
		$html .= '<p style="margin:0 0 6px;font-family:' . $font . ';font-size:21px;line-height:1.3;letter-spacing:0.16em;font-weight:bold;color:' . esc_attr( $gold ) . ';text-align:center">LET\'S CONNECT</p>';
		$html .= '<p style="' . $p . ';margin-bottom:16px">' . esc_html__( 'Follow the atelier for new patterns, fabrics and inspiration.', 'atelier-irisee-master-plugin' ) . '</p>';

		// The social media that are filled in (Settings → Footer).
		$socials = class_exists( 'AIMP_Footer' ) ? AIMP_Footer::socials() : array();
		$icons   = '';
		foreach ( self::NETWORKS as $network => $name ) {
			if ( empty( $socials[ $network ] ) ) {
				continue;
			}
			$icons .= '<td style="padding:0 6px"><a href="' . esc_url( $socials[ $network ] ) . '" target="_blank" style="text-decoration:none"><img src="' . esc_url( $img . $network . '.png' ) . '" width="38" height="38" alt="' . esc_attr( $name ) . '" style="display:block;width:38px;height:38px;border:0"></a></td>';
		}
		if ( '' !== $icons ) {
			$html .= '<table role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" style="margin:0 auto"><tr>' . $icons . '</tr></table>';
		}

		$hashtag = trim( (string) self::design( 'em_hashtag' ) );
		if ( '' !== $hashtag ) {
			$hashtag = '#' . ltrim( $hashtag, '#' );
			/* translators: %s: hashtag */
			$line  = sprintf( esc_html__( 'Show us what you made with %s', 'atelier-irisee-master-plugin' ), '<strong style="color:' . esc_attr( $gold ) . '">' . esc_html( $hashtag ) . '</strong>' );
			$html .= '<p style="' . $p . ';margin-top:14px">' . $line . '</p>';
		}

		// The website as a button.
		$home  = home_url( '/' );
		$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$html .= class_exists( 'AIMP_Emails' ) ? AIMP_Emails::button( $host, $home ) : '<p style="' . $p . '"><a href="' . esc_url( $home ) . '">' . esc_html( $host ) . '</a></p>';

		// Contact: the email address and the address, never the phone number.
		$mail    = class_exists( 'AIMP_Footer' ) ? (string) AIMP_Footer::get( 'email' ) : '';
		$address = class_exists( 'AIMP_Footer' ) ? trim( (string) AIMP_Footer::get( 'address' ) ) : '';
		$contact = array();
		if ( is_email( $mail ) ) {
			$contact[] = '<a href="mailto:' . esc_attr( $mail ) . '" style="color:' . esc_attr( $gold ) . ';text-decoration:none">' . esc_html( $mail ) . '</a>';
		}
		if ( '' !== $address ) {
			$contact[] = esc_html( str_replace( array( "\r\n", "\n" ), ', ', $address ) );
		}
		if ( $contact ) {
			$html .= '<p style="' . $p . ';font-size:13px">' . implode( '<br>', $contact ) . '</p>';
		}

		$extra  = array();
		$review = (string) self::design( 'em_review' );
		if ( '' !== $review ) {
			$extra[] = esc_html__( 'Happy with your order?', 'atelier-irisee-master-plugin' ) . ' <a href="' . esc_url( $review ) . '" target="_blank" style="color:' . esc_attr( $gold ) . '">' . esc_html__( 'Leave a review', 'atelier-irisee-master-plugin' ) . '</a>';
		}
		if ( $signup ) {
			$extra[] = esc_html__( 'Not on the newsletter yet?', 'atelier-irisee-master-plugin' ) . ' <a href="' . esc_url( home_url( '/#aimp-newsletter-email' ) ) . '" target="_blank" style="color:' . esc_attr( $gold ) . '">' . esc_html__( 'Subscribe', 'atelier-irisee-master-plugin' ) . '</a>';
		}
		if ( $extra ) {
			$html .= '<p style="' . $p . ';font-size:13px;margin-top:12px">' . implode( '<br>', $extra ) . '</p>';
		}
		return $html . '</td></tr></table>';
	}

	/**
	 * The band as plain text.
	 *
	 * @return string
	 */
	public static function text() {
		if ( ! self::enabled() ) {
			return '';
		}
		$lines   = array( "LET'S CONNECT", __( 'Follow the atelier for new patterns, fabrics and inspiration.', 'atelier-irisee-master-plugin' ) );
		$socials = class_exists( 'AIMP_Footer' ) ? AIMP_Footer::socials() : array();
		foreach ( self::NETWORKS as $network => $name ) {
			if ( ! empty( $socials[ $network ] ) ) {
				$lines[] = $name . ': ' . $socials[ $network ];
			}
		}
		$lines[] = home_url( '/' );
		$mail    = class_exists( 'AIMP_Footer' ) ? (string) AIMP_Footer::get( 'email' ) : '';
		if ( is_email( $mail ) ) {
			$lines[] = $mail;
		}
		return "\n----\n" . implode( "\n", $lines ) . "\n\n";
	}
}
