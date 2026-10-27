<?php
/**
 * Gift cards as pictures: a front (the theme's picture with the logo) and a back (the theme's greeting and the card
 * details), drawn with PHP GD at A6 landscape (148 × 105 mm at 300 dpi), plus a 2-page PDF of both sides.
 *
 * The same pictures are used in the email (shown and attached), for downloading, on the account page, on the print
 * page and as the preview on the product page. Without GD (or without its FreeType fonts) the plugin keeps using the
 * HTML card of templates/giftcards/card.php.
 *
 * Files of real cards live in uploads/aimp-giftcards/<secret folder>/ (closed to visitors, served only through a
 * download link with a key); the fronts of the themes, which hold no personal data, in uploads/aimp-giftcards-themes/.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Giftcards_Render {

	/** The long side of a card at 300 dpi (148 mm); the other side follows the shape of the standard design's picture. */
	const SIDE = 1748;

	/** Width and height of the cards in px (see size()). */
	private static $size = null;

	/** Raise when the drawing changes, so cached pictures are drawn again. */
	const DRAW_VERSION = 5;


	/**
	 * The shape of every card: that of the picture of the standard design (the first one, which the gift card page
	 * chooses first), so that picture is never cut; A6 landscape (148 × 105 mm) without one.
	 *
	 * @return float Width / height.
	 */
	public static function ratio() {
		$designs = AIMP_Giftcards::designs();
		$first   = $designs ? reset( $designs ) : null;
		$meta    = $first && ! empty( $first['image_id'] ) ? wp_get_attachment_metadata( (int) $first['image_id'] ) : array();
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			return max( 0.5, min( 2.5, $meta['width'] / $meta['height'] ) );
		}
		return 148 / 105;
	}

	private static function size() {
		if ( null === self::$size ) {
			$ratio      = self::ratio();
			self::$size = $ratio >= 1 ? array( self::SIDE, (int) round( self::SIDE / $ratio ) ) : array( (int) round( self::SIDE * $ratio ), self::SIDE );
		}
		return self::$size;
	}

	public static function w() {
		return self::size()[0];
	}

	public static function h() {
		return self::size()[1];
	}

	/**
	 * The card in millimetres (the long side 148 mm), for the PDF and the print page.
	 *
	 * @return float[] [ width, height ].
	 */
	public static function mm() {
		return array( round( 148 * self::w() / self::SIDE, 1 ), round( 148 * self::h() / self::SIDE, 1 ) );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'serve' ), 5 );
		add_action( 'wp_ajax_aimp_gc_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_nopriv_aimp_gc_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_aimp_gc_admin_preview', array( __CLASS__, 'ajax_admin_preview' ) );
	}

	/**
	 * Can this server draw the cards (GD with FreeType fonts and JPEG)?
	 *
	 * @return bool
	 */
	public static function available() {
		static $ok = null;
		if ( null !== $ok ) {
			return $ok;
		}
		$ok = self::check_gd();
		return $ok;
	}

	private static function check_gd() {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagettftext' ) || ! function_exists( 'imagejpeg' ) ) {
			return false;
		}
		$info = function_exists( 'gd_info' ) ? gd_info() : array();
		return ! empty( $info['FreeType Support'] ) && ( ! empty( $info['JPEG Support'] ) || ! empty( $info['JPG Support'] ) );
	}

	/* ------------------------------------------------------------------
	 * Themes
	 * ------------------------------------------------------------------ */

	/**
	 * The settings of a theme (a design), with the standard for everything not set.
	 *
	 * @param string $design_id Design.
	 * @return array
	 */
	public static function theme( $design_id ) {
		$designs = AIMP_Giftcards::designs( true );
		$design  = isset( $designs[ $design_id ] ) ? $designs[ $design_id ] : array();
		return wp_parse_args( $design, self::theme_defaults() );
	}

	public static function theme_defaults() {
		// The gradient of the website's sections: Divi's primary colour in the middle, the secondary at the edges.
		$divi = class_exists( 'AIMP_Design' ) ? AIMP_Design::divi_colors() : array();
		return array(
			'id'            => '',
			'name'          => '',
			'image_id'      => 0,
			'active'        => 1,
			'fx'            => 50,
			'fy'            => 50,
			// The logo at the top of the text side (the front); its width in % of the card. New keys in 3.50.1: the old
			// "logo" and "logo_size" were the logo on the picture side and are no longer used.
			'text_logo'      => 1,
			'text_logo_size' => 13,
			'back_image_id' => 0,
			'back_color'    => '#fffaf7',
			// Background of the text side: the website's radial gradient (standard) or one colour.
			'back_style'    => 'gradient',
			'grad_inner'    => isset( $divi['gcid-primary-color'] ) ? $divi['gcid-primary-color'][1] : '#fbefef',
			'grad_outer'    => isset( $divi['gcid-secondary-color'] ) ? $divi['gcid-secondary-color'][1] : '#d9bcea',
			'text_color'    => '#613907',
			'accent_color'  => '#b38f4f',
			// The double gold border at the edge of the text side and of the picture side.
			'frame'         => 1,
			'picture_frame' => 1,
			'greeting'      => '',
			'subject_self'  => '',
			'subject_other' => '',
			'intro'         => '',
		);
	}

	/**
	 * A theme text ("NL | FR | EN") in a language.
	 *
	 * @param string $text Text.
	 * @param string $lang Language ('' = the current one).
	 * @return string
	 */
	public static function in_language( $text, $lang = '' ) {
		$lang  = AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::current();
		$parts = array_map( 'trim', explode( '|', (string) $text ) );
		$index = array_search( $lang, array_keys( AIMP_I18n::languages() ), true );
		return ( count( $parts ) > 1 && false !== $index && isset( $parts[ $index ] ) && '' !== $parts[ $index ] ) ? $parts[ $index ] : $parts[0];
	}

	/**
	 * The greeting on a card: the customer's own (also when they emptied it), else the theme's.
	 *
	 * @param array $card Card (AIMP_Giftcards::get()).
	 * @return string
	 */
	public static function greeting( $card ) {
		if ( isset( $card['greeting'] ) && null !== $card['greeting'] ) {
			return (string) $card['greeting'];
		}
		$theme = self::theme( $card['design'] );
		return self::in_language( $theme['greeting'], $card['lang'] );
	}

	/* ------------------------------------------------------------------
	 * Files
	 * ------------------------------------------------------------------ */

	/**
	 * Folder for the files of real cards (closed to visitors), created on first use.
	 *
	 * @return string Path with a trailing slash, or '' when uploads cannot be written.
	 */
	private static function card_dir() {
		$secret = get_option( 'aimp_gc_files' );
		if ( ! is_string( $secret ) || ! preg_match( '/^[a-z0-9]{20}$/', $secret ) ) {
			$secret = strtolower( wp_generate_password( 20, false, false ) );
			update_option( 'aimp_gc_files', $secret, false );
		}
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] ) . 'aimp-giftcards/';
		$dir     = $base . $secret . '/';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		foreach ( array( $base, $dir ) as $folder ) {
			if ( ! file_exists( $folder . 'index.html' ) ) {
				file_put_contents( $folder . 'index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			if ( ! file_exists( $folder . '.htaccess' ) ) {
				file_put_contents( $folder . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return $dir;
	}

	/**
	 * Folder and address for the fronts of the themes (no personal data, open to visitors).
	 *
	 * @return array [ path, url ] or [ '', '' ].
	 */
	private static function theme_dir() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'aimp-giftcards-themes/';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return array( '', '' );
		}
		return array( $dir, trailingslashit( $uploads['baseurl'] ) . 'aimp-giftcards-themes/' );
	}

	/**
	 * What the front of a theme depends on (a change draws it again).
	 *
	 * @param array $theme Theme.
	 * @return string
	 */
	private static function front_hash( $theme ) {
		$keys = array( 'image_id', 'fx', 'fy', 'back_color', 'picture_frame', 'accent_color' );
		return substr( md5( wp_json_encode( array( array_intersect_key( $theme, array_flip( $keys ) ), self::size(), self::DRAW_VERSION ) ) ), 0, 10 );
	}

	/**
	 * The front of a theme as a public picture.
	 *
	 * @param string $design_id Design.
	 * @param bool   $small     700 px wide (for the choice on the product page, the cart and the account page).
	 * @return string URL, or '' when it cannot be drawn.
	 */
	public static function theme_picture_url( $design_id, $small = false ) {
		$designs = AIMP_Giftcards::designs( true );
		if ( ! self::available() || '' === (string) $design_id || ! isset( $designs[ $design_id ] ) ) {
			return '';
		}
		list( $dir, $url ) = self::theme_dir();
		if ( '' === $dir ) {
			return '';
		}
		$theme = self::theme( $design_id );
		$name  = sanitize_key( $design_id ) . '-' . self::front_hash( $theme ) . ( $small ? '-s' : '' ) . '.jpg';
		if ( ! file_exists( $dir . $name ) ) {
			foreach ( (array) glob( $dir . sanitize_key( $design_id ) . '-*' ) as $old ) {
				wp_delete_file( $old );
			}
			$im = self::draw_picture( $theme );
			if ( ! $im ) {
				return '';
			}
			self::save_jpeg( $im, $dir . sanitize_key( $design_id ) . '-' . self::front_hash( $theme ) . '.jpg' );
			self::save_jpeg( self::scaled( $im, 700 ), $dir . sanitize_key( $design_id ) . '-' . self::front_hash( $theme ) . '-s.jpg' );
			imagedestroy( $im );
		}
		return $url . $name;
	}

	/**
	 * The front, back and PDF of a card, drawn when missing or when the card or its theme changed.
	 *
	 * @param array $card Card.
	 * @return array front, back, pdf => path; empty when they cannot be drawn.
	 */
	public static function files( $card ) {
		$dir = self::available() ? self::card_dir() : '';
		if ( ! $card || '' === $dir ) {
			return array();
		}
		$theme = self::theme( $card['design'] );
		$data  = self::back_data( $card );
		$hash  = substr( md5( wp_json_encode( array( $theme, $data, self::logo_id(), self::size(), self::DRAW_VERSION ) ) ), 0, 12 );
		$base  = $dir . (int) $card['id'] . '-' . $hash;
		$files = array(
			'front' => $base . '-front.jpg',
			'back'  => $base . '-back.jpg',
			'pdf'   => $base . '.pdf',
		);
		if ( file_exists( $files['front'] ) && file_exists( $files['back'] ) && file_exists( $files['pdf'] ) ) {
			return $files;
		}
		foreach ( (array) glob( $dir . (int) $card['id'] . '-*' ) as $old ) {
			wp_delete_file( $old );
		}
		$front = self::draw_details( $theme, $data, false );
		$back  = self::draw_picture( $theme );
		if ( ! $front || ! $back ) {
			return array();
		}
		self::save_jpeg( $front, $files['front'] );
		self::save_jpeg( $back, $files['back'] );
		imagedestroy( $front );
		imagedestroy( $back );
		file_put_contents( $files['pdf'], self::pdf( array( $files['front'], $files['back'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $files;
	}

	/**
	 * Everything the back of a card shows, as plain text in the card's language.
	 *
	 * @param array $card Card.
	 * @return array
	 */
	public static function back_data( $card ) {
		return AIMP_I18n::with_language(
			AIMP_I18n::is_valid( $card['lang'] ) ? $card['lang'] : AIMP_I18n::default_language(),
			function () use ( $card ) {
				$amount = html_entity_decode( wp_strip_all_tags( wc_price( $card['amount'], array( 'currency' => $card['currency'] ) ) ), ENT_QUOTES, 'UTF-8' );
				return array(
					'greeting'  => self::greeting( $card ),
					/* translators: %s: recipient name */
					'for'       => '' !== $card['recipient_name'] ? sprintf( __( 'For %s', 'atelier-irisee-master-plugin' ), $card['recipient_name'] ) : '',
					'label'     => __( 'Gift card', 'atelier-irisee-master-plugin' ),
					'amount'    => str_replace( "\xC2\xA0", ' ', $amount ),
					'message'   => (string) $card['message'],
					/* translators: %s: sender name */
					'from'      => '' !== $card['sender_name'] && 'email_self' !== $card['delivery'] ? sprintf( __( 'From %s', 'atelier-irisee-master-plugin' ), $card['sender_name'] ) : '',
					'code_label' => __( 'Your code', 'atelier-irisee-master-plugin' ),
					'code'      => (string) $card['code'],
					/* translators: %s: date */
					'valid'     => $card['expires'] ? sprintf( __( 'Valid until %s', 'atelier-irisee-master-plugin' ), AIMP_I18n::date( strtotime( $card['expires'] ) ) ) : '',
					'how'       => __( 'Enter the code in the gift card field in your cart or at checkout. Anything you do not spend stays on the card.', 'atelier-irisee-master-plugin' ),
					'site'      => preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( home_url() ) ),
				);
			}
		);
	}

	/* ------------------------------------------------------------------
	 * Download links
	 * ------------------------------------------------------------------ */

	private static function key( $card ) {
		return substr( hash_hmac( 'sha256', (int) $card['id'] . '|' . $card['code'], wp_salt( 'auth' ) ), 0, 24 );
	}

	/**
	 * A link to the front, back or PDF of a card (works without logging in; the key cannot be guessed).
	 *
	 * @param array  $card   Card.
	 * @param string $kind   front, back or pdf.
	 * @param bool   $inline Show it (in an email) instead of downloading it.
	 * @return string
	 */
	public static function url( $card, $kind, $inline = false ) {
		$args = array(
			'aimp_gc_file' => $kind,
			'id'           => (int) $card['id'],
			'key'          => self::key( $card ),
		);
		if ( $inline ) {
			$args['view'] = 1;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Sends a card's picture or PDF for a download link.
	 */
	public static function serve() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a link from an email; checked with its key.
		if ( ! isset( $_GET['aimp_gc_file'], $_GET['id'], $_GET['key'] ) ) {
			return;
		}
		$kind = sanitize_key( wp_unslash( $_GET['aimp_gc_file'] ) );
		$card = AIMP_Giftcards::get( absint( $_GET['id'] ) );
		$key  = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		$view = ! empty( $_GET['view'] );
		// phpcs:enable
		// Never kept by the page cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'gift card file' );
		if ( ! $card || ! in_array( $kind, array( 'front', 'back', 'pdf' ), true ) || ! hash_equals( self::key( $card ), $key ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$files = self::files( $card );
		if ( empty( $files[ $kind ] ) || ! is_readable( $files[ $kind ] ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$name = AIMP_I18n::with_language(
			$card['lang'],
			function () use ( $kind, $card ) {
				$side = 'front' === $kind ? __( 'front', 'atelier-irisee-master-plugin' ) : ( 'back' === $kind ? __( 'back', 'atelier-irisee-master-plugin' ) : '' );
				return sanitize_file_name( __( 'gift-card', 'atelier-irisee-master-plugin' ) . '-' . $card['code'] . ( '' !== $side ? '-' . $side : '' ) ) . ( 'pdf' === $kind ? '.pdf' : '.jpg' );
			}
		);
		header( 'Content-Type: ' . ( 'pdf' === $kind ? 'application/pdf' : 'image/jpeg' ) );
		header( 'Content-Disposition: ' . ( $view ? 'inline' : 'attachment' ) . '; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $files[ $kind ] ) );
		header( 'Cache-Control: private, max-age=86400' );
		header( 'X-Robots-Tag: noindex' );
		readfile( $files[ $kind ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/* ------------------------------------------------------------------
	 * Previews
	 * ------------------------------------------------------------------ */

	/**
	 * The back as the customer fills in the form (product page), marked as an example and without a real code.
	 */
	public static function ajax_preview() {
		// No nonce: product pages are cached for days, and this only draws an example (no code, nothing saved).
		if ( ! self::available() ) {
			wp_send_json_error();
		}
		// At most 40 previews per visitor in 10 minutes.
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$limit = 'aimp_gc_pv_' . md5( $ip );
		$count = (int) get_transient( $limit );
		if ( $count >= 40 ) {
			wp_send_json_error();
		}
		set_transient( $limit, $count + 1, 10 * MINUTE_IN_SECONDS );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see above.
		$get     = function ( $key, $length ) {
			$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
		};
		$designs = AIMP_Giftcards::designs();
		$design  = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';
		$amount  = isset( $_POST['amount'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
		$self    = isset( $_POST['delivery'] ) && 'email_self' === $_POST['delivery'];
		// phpcs:enable
		if ( ! isset( $designs[ $design ] ) ) {
			wp_send_json_error();
		}
		$card  = self::example_card(
			$design,
			array(
				'amount'         => $amount > 0 ? min( $amount, 10000 ) : 50,
				'recipient_name' => $get( 'recipient_name', 80 ),
				'sender_name'    => $get( 'sender_name', 80 ),
				'message'        => $get( 'message', 300 ),
				'greeting'       => $get( 'greeting', 80 ),
				'delivery'       => $self ? 'email_self' : 'email_other',
			)
		);
		$theme = self::theme( $design );
		$front = self::draw_details( $theme, self::back_data( $card ), true );
		wp_send_json_success(
			array(
				'front' => self::data_uri( $front ? self::scaled( $front, 1000 ) : null ),
				'back'  => self::theme_picture_url( $design ),
			)
		);
	}

	/**
	 * Both sides of a theme as set in the (unsaved) settings form, with example details.
	 */
	public static function ajax_admin_preview() {
		check_ajax_referer( 'aimp_gc_admin_preview', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		if ( ! self::available() ) {
			wp_send_json_error( array( 'message' => self::unavailable_text() ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		$row   = isset( $_POST['design'] ) && is_array( $_POST['design'] ) ? AIMP_Giftcards_Admin::clean_design( wp_unslash( $_POST['design'] ) ) : array();
		$theme = wp_parse_args( $row, self::theme_defaults() );
		$card  = self::example_card( '', array( 'greeting' => self::in_language( $theme['greeting'] ) ) );
		$front = self::draw_details( $theme, self::back_data( $card ), true );
		$back  = self::draw_picture( $theme );
		wp_send_json_success(
			array(
				'front' => self::data_uri( $front ? self::scaled( $front, 900 ) : null ),
				'back'  => self::data_uri( $back ? self::scaled( $back, 900 ) : null ),
			)
		);
	}

	public static function unavailable_text() {
		return __( 'This server cannot draw gift card pictures (PHP GD with FreeType is missing). Ask your host to enable it; until then the gift card is sent as before.', 'atelier-irisee-master-plugin' );
	}

	/**
	 * A card with example details for previews.
	 *
	 * @param string $design Design.
	 * @param array  $values Details to use.
	 * @return array
	 */
	private static function example_card( $design, $values ) {
		$months = (int) AIMP_Giftcards::opt( 'expiry_months' );
		return array_merge(
			array(
				'id'             => 0,
				'code'           => AIMP_Giftcards::opt( 'code_prefix' ) . '-XXXX-XXXX-XXXX',
				'amount'         => 50,
				'currency'       => get_woocommerce_currency(),
				'design'         => $design,
				'delivery'       => 'email_other',
				'sender_name'    => __( 'Sofie', 'atelier-irisee-master-plugin' ),
				'recipient_name' => __( 'Emma', 'atelier-irisee-master-plugin' ),
				'message'        => __( 'Have fun sewing something beautiful!', 'atelier-irisee-master-plugin' ),
				'expires'        => $months > 0 ? gmdate( 'Y-m-d', strtotime( '+' . $months . ' months' ) ) : '',
				'lang'           => AIMP_I18n::current(),
				'greeting'       => null,
			),
			$values
		);
	}

	/* ------------------------------------------------------------------
	 * Drawing
	 * ------------------------------------------------------------------ */

	private static function font( $name ) {
		$files = array(
			'display'      => 'Cinzel-Regular.ttf',
			'display-bold' => 'Cinzel-Bold.ttf',
			'text'         => 'CormorantGaramond-Medium.ttf',
			'text-bold'    => 'CormorantGaramond-SemiBold.ttf',
			'text-italic'  => 'CormorantGaramond-SemiBoldItalic.ttf',
		);
		return AIMP_PLUGIN_DIR . 'assets/fonts/' . $files[ $name ];
	}

	/**
	 * The logo for the cards: the gift card setting, else the Divi logo (when it is a picture GD can read).
	 *
	 * @return int|string Attachment id, a file path, or 0.
	 */
	private static function logo_id() {
		$id = (int) AIMP_Giftcards::opt( 'logo_id' );
		if ( $id ) {
			return $id;
		}
		$url = class_exists( 'AIMP_Header' ) ? (string) AIMP_Header::logo_url() : '';
		if ( '' === $url || ! preg_match( '/\.(png|jpe?g|webp)(\?|$)/i', $url ) ) {
			return 0;
		}
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			return $id;
		}
		// A theme or upload file without an attachment.
		$uploads = wp_upload_dir();
		$path    = 0 === strpos( $url, $uploads['baseurl'] ) ? $uploads['basedir'] . substr( strtok( $url, '?' ), strlen( $uploads['baseurl'] ) ) : '';
		return '' !== $path && is_readable( $path ) ? $path : 0;
	}

	/**
	 * A picture from the media library (or a file) as a GD image, at least $min px wide when possible.
	 *
	 * @param int|string $source Attachment id or path.
	 * @param int        $min    Wanted width.
	 * @return resource|GdImage|null
	 */
	private static function load( $source, $min = self::SIDE ) {
		$path = '';
		if ( is_numeric( $source ) && (int) $source > 0 ) {
			$original = get_attached_file( (int) $source );
			$path     = $original;
			// A smaller copy WordPress made, when it is big enough (less memory than a camera original).
			$meta = wp_get_attachment_metadata( (int) $source );
			if ( $original && ! empty( $meta['sizes'] ) ) {
				$best = 0;
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) && $size['width'] >= $min && ( ! $best || $size['width'] < $best ) ) {
						$best = $size['width'];
						$path = trailingslashit( dirname( $original ) ) . $size['file'];
					}
				}
			}
		} elseif ( is_string( $source ) ) {
			$path = $source;
		}
		if ( ! $path || ! is_readable( $path ) ) {
			return null;
		}
		$im = @imagecreatefromstring( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a broken picture gives null.
		if ( ! $im ) {
			return null;
		}
		if ( ! imageistruecolor( $im ) ) {
			imagepalettetotruecolor( $im );
		}
		imagealphablending( $im, true );
		imagesavealpha( $im, true );
		return $im;
	}

	private static function canvas( $w = 0, $h = 0, $transparent = false ) {
		$w = $w ? $w : self::w();
		$h = $h ? $h : self::h();
		$im = imagecreatetruecolor( $w, $h );
		if ( $transparent ) {
			imagealphablending( $im, false );
			imagefill( $im, 0, 0, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );
			imagesavealpha( $im, true );
			imagealphablending( $im, true );
		}
		return $im;
	}

	/**
	 * @param resource|GdImage $im    Image.
	 * @param string           $hex   Colour.
	 * @param int              $alpha 0 (solid) – 127 (invisible).
	 * @return int
	 */
	private static function color( $im, $hex, $alpha = 0 ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			$hex = '613907';
		}
		return imagecolorallocatealpha( $im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), $alpha );
	}

	/**
	 * The radial gradient of the website's sections: the middle colour up to 75 % of the way to the corners, then
	 * fading to the edge colour (CSS: radial-gradient(circle, middle 0%, middle 75%, rgba(235,215,237,.2) 100%) over the
	 * edge colour). Drawn as thin rings from the outside in.
	 *
	 * @param resource|GdImage $im    Card.
	 * @param string           $inner Middle colour.
	 * @param string           $outer Edge colour.
	 */
	private static function radial( $im, $inner, $outer ) {
		$rgb = function ( $hex ) {
			$hex = ltrim( (string) $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			return preg_match( '/^[0-9a-f]{6}$/i', $hex ) ? array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ) : array( 251, 239, 239 );
		};
		$in  = $rgb( $inner );
		$out = $rgb( $outer );
		// The colour at the very edge: a light, mostly see-through lilac over the edge colour, as on the website.
		$end = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$end[ $i ] = 0.2 * array( 235, 215, 237 )[ $i ] + 0.8 * $out[ $i ];
		}
		$w      = self::w();
		$h      = self::h();
		$radius = sqrt( $w * $w + $h * $h ) / 2;
		imagefill( $im, 0, 0, imagecolorallocate( $im, (int) $end[0], (int) $end[1], (int) $end[2] ) );
		$steps = 240;
		for ( $s = $steps; $s >= 0; $s-- ) {
			$t = $s / $steps; // 1 = the corners, 0 = 75 % of the way.
			$r = $radius * ( 0.75 + 0.25 * $t );
			$c = imagecolorallocate( $im, (int) round( $in[0] + ( $end[0] - $in[0] ) * $t ), (int) round( $in[1] + ( $end[1] - $in[1] ) * $t ), (int) round( $in[2] + ( $end[2] - $in[2] ) * $t ) );
			imagefilledellipse( $im, (int) round( $w / 2 ), (int) round( $h / 2 ), (int) round( 2 * $r ), (int) round( 2 * $r ), $c );
		}
	}

	/**
	 * Fills the card with a picture, cut to fit (like background-size: cover) around a focus point.
	 */
	private static function cover( $im, $src, $fx = 50, $fy = 50 ) {
		$sw    = imagesx( $src );
		$sh    = imagesy( $src );
		$scale = max( self::w() / $sw, self::h() / $sh );
		$cw    = (int) round( self::w() / $scale );
		$ch    = (int) round( self::h() / $scale );
		$sx    = (int) round( max( 0, min( $sw - $cw, $sw * $fx / 100 - $cw / 2 ) ) );
		$sy    = (int) round( max( 0, min( $sh - $ch, $sh * $fy / 100 - $ch / 2 ) ) );
		imagecopyresampled( $im, $src, 0, 0, $sx, $sy, self::w(), self::h(), $cw, $ch );
	}

	/**
	 * A smooth shape: drawn twice as big on a transparent layer and scaled down onto the card.
	 *
	 * @param resource|GdImage $im   Card.
	 * @param callable         $draw function( $layer, $scale ) draws on the big layer.
	 */
	private static function smooth( $im, $draw ) {
		$s     = 2;
		$layer = self::canvas( self::w() * $s, self::h() * $s, true );
		imagealphablending( $layer, false );
		call_user_func( $draw, $layer, $s );
		$small = self::canvas( self::w(), self::h(), true );
		imagealphablending( $small, false );
		imagecopyresampled( $small, $layer, 0, 0, 0, 0, self::w(), self::h(), self::w() * $s, self::h() * $s );
		imagedestroy( $layer );
		imagealphablending( $im, true );
		imagecopy( $im, $small, 0, 0, 0, 0, self::w(), self::h() );
		imagedestroy( $small );
	}

	private static function rounded( $im, $x1, $y1, $x2, $y2, $r, $color ) {
		imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y2, $color );
		imagefilledrectangle( $im, $x1, $y1 + $r, $x2, $y2 - $r, $color );
		foreach ( array( array( $x1 + $r, $y1 + $r ), array( $x2 - $r, $y1 + $r ), array( $x1 + $r, $y2 - $r ), array( $x2 - $r, $y2 - $r ) ) as $c ) {
			imagefilledellipse( $im, $c[0], $c[1], $r * 2, $r * 2, $color );
		}
	}

	/**
	 * A rounded line around the card (cut out of a filled shape, so the background stays visible).
	 */
	private static function ring( $layer, $inset, $width, $radius, $color, $s ) {
		$clear = imagecolorallocatealpha( $layer, 0, 0, 0, 127 );
		$i     = $inset * $s;
		$w     = $width * $s;
		self::rounded( $layer, $i, $i, self::w() * $s - $i, self::h() * $s - $i, $radius * $s, $color );
		self::rounded( $layer, $i + $w, $i + $w, self::w() * $s - $i - $w, self::h() * $s - $i - $w, max( 1, ( $radius - $width ) * $s ), $clear );
	}

	/**
	 * The picture side (the back of the card): only the theme's picture.
	 *
	 * @param array $theme Theme.
	 * @return resource|GdImage|null
	 */
	public static function draw_picture( $theme ) {
		// A card at 300 dpi takes about 90 MB while drawing.
		wp_raise_memory_limit( 'image' );
		$im = self::canvas();
		imagefill( $im, 0, 0, self::color( $im, $theme['back_color'] ) );
		$picture = $theme['image_id'] ? self::load( (int) $theme['image_id'] ) : null;
		if ( $picture ) {
			self::cover( $im, $picture, (float) $theme['fx'], (float) $theme['fy'] );
			imagedestroy( $picture );
		}
		if ( $theme['picture_frame'] ) {
			self::border( $im, $theme['accent_color'] );
		}
		return $im;
	}

	/**
	 * The double gold border of the website's buttons and cards (CSS "5px double"), right at the edge of the card:
	 * two lines with a gap between them and rounded corners.
	 *
	 * @param resource|GdImage $im    Card.
	 * @param string           $color Colour.
	 */
	private static function border( $im, $color ) {
		self::smooth(
			$im,
			function ( $layer, $s ) use ( $color ) {
				self::ring( $layer, 0, 9, 40, self::color( $layer, $color ), $s );
				self::ring( $layer, 17, 9, 23, self::color( $layer, $color ), $s );
			}
		);
	}

	/**
	 * Text size: width, and the height above and below the line, the same for every line of that font and size.
	 */
	private static function measure( $text, $font, $size ) {
		$box  = imagettfbbox( $size, 0, $font, '' !== $text ? $text : ' ' );
		$line = imagettfbbox( $size, 0, $font, 'ÁÉHgjpqy' );
		return array(
			'w'    => abs( $box[2] - $box[0] ),
			'x0'   => $box[0],
			'up'   => -$line[7],
			'down' => $line[1],
		);
	}

	/**
	 * Text split into lines no wider than $max.
	 *
	 * @return string[]
	 */
	private static function wrap( $text, $font, $size, $max ) {
		$lines = array();
		foreach ( preg_split( '/\R/u', trim( (string) $text ) ) as $paragraph ) {
			$line = '';
			foreach ( preg_split( '/\s+/u', trim( $paragraph ) ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( '' !== $line && self::measure( $try, $font, $size )['w'] > $max ) {
					$lines[] = $line;
					$line    = $word;
				} else {
					$line = $try;
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}

	/**
	 * A line with extra space between the letters (small capitals labels), centred on $cx.
	 *
	 * @param resource|GdImage $im       Image (null = only measure).
	 * @param string           $text     Text.
	 * @param string           $font     Font file.
	 * @param float            $size     Size.
	 * @param int              $color    Colour.
	 * @param float            $cx       Centre.
	 * @param int              $baseline Baseline.
	 * @param float            $tracking Extra space after each letter, in px.
	 * @return float Width.
	 */
	private static function spaced( $im, $text, $font, $size, $color, $cx, $baseline, $tracking ) {
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$pair  = self::measure( 'HH', $font, $size )['w'];
		$adv   = array();
		foreach ( $chars as $char ) {
			// The advance of a letter (spaces included): "H<letter>H" minus "HH".
			$adv[] = self::measure( 'H' . $char . 'H', $font, $size )['w'] - $pair;
		}
		$width = array_sum( $adv ) + $tracking * max( 0, count( $chars ) - 1 );
		if ( $im ) {
			$x = $cx - $width / 2;
			foreach ( $chars as $i => $char ) {
				if ( ' ' !== $char ) {
					imagettftext( $im, $size, 0, (int) round( $x ), $baseline, $color, $font, $char );
				}
				$x += $adv[ $i ] + $tracking;
			}
		}
		return $width;
	}

	/**
	 * The back: the theme's background and the card details, laid out from top to bottom and made smaller as a whole
	 * when they do not fit.
	 *
	 * @param array $theme   Theme.
	 * @param array $data    back_data().
	 * @param bool  $example Mark it as an example.
	 * @return resource|GdImage|null
	 */
	public static function draw_details( $theme, $data, $example ) {
		wp_raise_memory_limit( 'image' );
		$im = self::canvas();
		if ( 'color' === $theme['back_style'] ) {
			imagefill( $im, 0, 0, self::color( $im, $theme['back_color'] ) );
		} else {
			self::radial( $im, $theme['grad_inner'], $theme['grad_outer'] );
		}
		$picture = $theme['back_image_id'] ? self::load( (int) $theme['back_image_id'] ) : null;
		if ( $picture ) {
			self::cover( $im, $picture );
			imagedestroy( $picture );
		}
		$text   = $theme['text_color'];
		$accent = $theme['accent_color'];
		if ( $theme['frame'] ) {
			self::border( $im, $accent );
		}

		// The blocks from top to bottom: [ kind, text, font, size, colour, space after ].
		$blocks = array();
		// The logo at the top (for a logo: its width in px, and its height / width in the text slot).
		$logo = $theme['text_logo'] ? self::load( self::logo_id(), 400 ) : null;
		if ( $logo ) {
			$blocks[] = array( 'logo', imagesy( $logo ) / imagesx( $logo ), 'display', self::w() * max( 5, min( 30, (float) $theme['text_logo_size'] ) ) / 100, '', 22 );
		}
		if ( '' !== trim( $data['greeting'] ) ) {
			$blocks[] = array( 'lines', $data['greeting'], 'display-bold', 54, $accent, 26 );
		}
		if ( '' !== $data['for'] ) {
			$blocks[] = array( 'lines', $data['for'], 'text-bold', 34, $text, 22 );
		}
		$blocks[] = array( 'label', $data['label'], 'display', 20, $accent, 6 );
		$blocks[] = array( 'lines', $data['amount'], 'display-bold', 66, $accent, 26 );
		if ( '' !== trim( $data['message'] ) ) {
			$blocks[] = array( 'lines', $data['message'], 'text-italic', 32, $text, 20 );
		}
		if ( '' !== $data['from'] ) {
			$blocks[] = array( 'lines', $data['from'], 'text-bold', 30, $text, 30 );
		}
		$blocks[] = array( 'code', $data['code'], 'display-bold', 30, $text, 22 );
		if ( '' !== $data['valid'] ) {
			$blocks[] = array( 'lines', $data['valid'], 'text-bold', 24, $text, 8 );
		}
		$blocks[] = array( 'lines', $data['how'], 'text', 21, $text, 0 );

		$max_w = self::w() - 2 * 190;
		$max_h = self::h() - 2 * 130;
		$scale = 1.0;
		for ( $try = 0; $try < 12; $try++ ) {
			$laid  = self::lay_out( $blocks, $scale, $max_w );
			$total = array_sum( wp_list_pluck( $laid, 'h' ) );
			if ( $total <= $max_h ) {
				break;
			}
			$scale *= 0.92;
		}

		$y = (int) round( ( self::h() - $total ) / 2 );
		foreach ( $laid as $block ) {
			$font  = self::font( $block['font'] );
			$color = self::color( $im, $block['color'] );
			if ( 'logo' === $block['kind'] ) {
				$w = (int) round( $block['size'] );
				imagealphablending( $im, true );
				imagecopyresampled( $im, $logo, (int) round( ( self::w() - $w ) / 2 ), $y, 0, 0, $w, $block['line'], imagesx( $logo ), imagesy( $logo ) );
			} elseif ( 'code' === $block['kind'] ) {
				$m   = self::measure( $block['lines'][0], $font, $block['size'] );
				$pad = (int) round( 22 * $scale );
				$x1  = (int) round( ( self::w() - $m['w'] ) / 2 ) - $pad * 2;
				$x2  = (int) round( ( self::w() + $m['w'] ) / 2 ) + $pad * 2;
				self::dashed_box( $im, $x1, $y, $x2, $y + $block['line'] + 2 * $pad, $color, max( 2, (int) round( 3 * $scale ) ) );
				imagettftext( $im, $block['size'], 0, (int) round( ( self::w() - $m['w'] ) / 2 ) - $m['x0'], $y + $pad + $m['up'], $color, $font, $block['lines'][0] );
			} elseif ( 'label' === $block['kind'] ) {
				$m = self::measure( 'H', $font, $block['size'] );
				self::spaced( $im, $block['lines'][0], $font, $block['size'], $color, self::w() / 2, $y + $m['up'], $block['size'] * 0.45 );
			} else {
				$ly = $y;
				foreach ( $block['lines'] as $line ) {
					$m = self::measure( $line, $font, $block['size'] );
					imagettftext( $im, $block['size'], 0, (int) round( ( self::w() - $m['w'] ) / 2 ) - $m['x0'], $ly + $m['up'], $color, $font, $line );
					$ly += $block['line'];
				}
			}
			$y += $block['h'];
		}

		// The address of the shop, small at the bottom.
		$font = self::font( 'display' );
		self::spaced( $im, strtoupper( $data['site'] ), $font, 15, self::color( $im, $accent ), self::w() / 2, self::h() - 96, 7 );

		if ( $example ) {
			$mark = implode( ' ', preg_split( '//u', ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( __( 'Example', 'atelier-irisee-master-plugin' ) ) : strtoupper( __( 'Example', 'atelier-irisee-master-plugin' ) ) ), -1, PREG_SPLIT_NO_EMPTY ) );
			$font = self::font( 'display-bold' );
			$box  = imagettfbbox( 130, 18, $font, $mark );
			$w    = max( $box[2], $box[4] ) - min( $box[0], $box[6] );
			$h    = max( $box[1], $box[3] ) - min( $box[5], $box[7] );
			imagettftext( $im, 130, 18, (int) round( ( self::w() - $w ) / 2 ), (int) round( ( self::h() + $h ) / 2 ), self::color( $im, $accent, 108 ), $font, $mark );
		}
		if ( $logo ) {
			imagedestroy( $logo );
		}
		return $im;
	}

	/**
	 * The blocks of the back at a scale: their lines, line height and height (with the space after).
	 */
	private static function lay_out( $blocks, $scale, $max_w ) {
		$laid = array();
		foreach ( $blocks as $block ) {
			list( $kind, $text, $font_name, $size, $color, $after ) = $block;
			if ( 'logo' === $kind ) {
				$width  = $size * $scale;
				$laid[] = array(
					'kind'  => 'logo',
					'lines' => array(),
					'font'  => $font_name,
					'size'  => $width,
					'color' => '#000000',
					'line'  => (int) round( $width * $text ),
					'h'     => (int) round( $width * $text ) + (int) round( $after * $scale ),
				);
				continue;
			}
			$size = max( 9, $size * $scale );
			$font = self::font( $font_name );
			if ( 'label' === $kind ) {
				$lines = array( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text ) : strtoupper( $text ) );
			} elseif ( 'code' === $kind ) {
				$lines = array( $text );
			} else {
				$lines = self::wrap( $text, $font, $size, 'text-italic' === $font_name ? min( $max_w, 1180 * $scale ) : $max_w );
				// Long messages: at most 6 lines.
				if ( count( $lines ) > 6 ) {
					$lines    = array_slice( $lines, 0, 6 );
					$lines[5] = rtrim( $lines[5], " .,;:!?" ) . '…';
				}
			}
			$m      = self::measure( 'H', $font, $size );
			$line   = (int) round( ( $m['up'] + $m['down'] ) * ( 'text-italic' === $font_name || 'text' === $font_name ? 1.12 : 1.05 ) );
			$height = 'code' === $kind ? $line + 2 * (int) round( 22 * $scale ) : $line * count( $lines );
			$laid[] = array(
				'kind'  => $kind,
				'lines' => $lines,
				'font'  => $font_name,
				'size'  => $size,
				'color' => $color,
				'line'  => $line,
				'h'     => $height + (int) round( $after * $scale ),
			);
		}
		return $laid;
	}

	private static function dashed_box( $im, $x1, $y1, $x2, $y2, $color, $t ) {
		$dash = 18;
		$gap  = 10;
		for ( $x = $x1; $x < $x2; $x += $dash + $gap ) {
			$end = min( $x + $dash, $x2 );
			imagefilledrectangle( $im, $x, $y1, $end, $y1 + $t - 1, $color );
			imagefilledrectangle( $im, $x, $y2 - $t + 1, $end, $y2, $color );
		}
		for ( $y = $y1; $y < $y2; $y += $dash + $gap ) {
			$end = min( $y + $dash, $y2 );
			imagefilledrectangle( $im, $x1, $y, $x1 + $t - 1, $end, $color );
			imagefilledrectangle( $im, $x2 - $t + 1, $y, $x2, $end, $color );
		}
	}

	private static function scaled( $im, $width ) {
		$height = (int) round( imagesy( $im ) * $width / imagesx( $im ) );
		$small  = imagecreatetruecolor( $width, $height );
		imagecopyresampled( $small, $im, 0, 0, 0, 0, $width, $height, imagesx( $im ), imagesy( $im ) );
		return $small;
	}

	private static function save_jpeg( $im, $path ) {
		imageinterlace( $im, true );
		imagejpeg( $im, $path, 90 );
	}

	private static function data_uri( $im ) {
		if ( ! $im ) {
			return '';
		}
		ob_start();
		imagejpeg( $im, null, 85 );
		imagedestroy( $im );
		return 'data:image/jpeg;base64,' . base64_encode( (string) ob_get_clean() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a picture for an <img>.
	}

	/* ------------------------------------------------------------------
	 * PDF
	 * ------------------------------------------------------------------ */

	/**
	 * A PDF with one JPEG per A6 landscape page, filling the page.
	 *
	 * @param string[] $jpegs Paths.
	 * @return string
	 */
	public static function pdf( $jpegs ) {
		// The card's size in points (1 mm = 72 / 25.4 pt).
		list( $w_mm, $h_mm ) = self::mm();
		$w       = round( $w_mm * 72 / 25.4, 2 );
		$h       = round( $h_mm * 72 / 25.4, 2 );
		$objects = array();
		$kids    = array();
		$n       = count( $jpegs );
		foreach ( array_values( $jpegs ) as $i => $path ) {
			$data  = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$size  = getimagesize( $path );
			$page  = 3 + $i * 3;
			$kids[] = $page . ' 0 R';
			$draw  = sprintf( 'q %.2F 0 0 %.2F 0 0 cm /Im%d Do Q', $w, $h, $i );
			$space = ( isset( $size['channels'] ) && 1 === (int) $size['channels'] ) ? '/DeviceGray' : '/DeviceRGB';
			$objects[ $page ]     = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im%d %d 0 R >> >> /Contents %d 0 R >>', $w, $h, $i, $page + 2, $page + 1 );
			$objects[ $page + 1 ] = '<< /Length ' . strlen( $draw ) . " >>\nstream\n" . $draw . "\nendstream";
			$objects[ $page + 2 ] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $size[0] . ' /Height ' . (int) $size[1] . ' /ColorSpace ' . $space . ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $data ) . " >>\nstream\n" . $data . "\nendstream";
		}
		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $n . ' >>';
		ksort( $objects );

		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $number => $body ) {
			$offsets[ $number ] = strlen( $out );
			$out               .= $number . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $out );
		$out .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$out .= sprintf( "%010d 00000 n \n", $offset );
		}
		$out .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
		return $out;
	}
}
