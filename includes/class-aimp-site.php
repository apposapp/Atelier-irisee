<?php
/**
 * Site-wide look: the Trajan Pro font (uploaded in the settings, never shipped with the plugin),
 * 15px text, the "empty line" spacing and the WooCommerce notices (gold border, close button,
 * success messages close by themselves).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Site {

	const FONT_FAMILY = 'Trajan Pro';

	public static function init() {
		add_filter( 'upload_mimes', array( __CLASS__, 'font_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'font_filetype' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 5 );
		// Our front-end scripts don't block the page from showing.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'defer_scripts' ), 999 );
		// Divi's viewport blocks pinch zoom on phones; allow it again.
		add_action( 'wp_head', array( __CLASS__, 'viewport' ), 0 );
	}

	public static function viewport() {
		if ( ! function_exists( 'et_add_viewport_meta' ) || ! has_action( 'wp_head', 'et_add_viewport_meta' ) ) {
			return;
		}
		remove_action( 'wp_head', 'et_add_viewport_meta' );
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
	}

	/* ------------------------------------------------------------------
	 * Font uploads (only for shop managers)
	 * ------------------------------------------------------------------ */

	private static function font_types() {
		return array(
			'ttf' => 'font/ttf',
			'otf' => 'font/otf',
		);
	}

	/**
	 * @param array $mimes Allowed extensions => mime types.
	 * @return array
	 */
	public static function font_mimes( $mimes ) {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$mimes = array_merge( $mimes, self::font_types() );
		}
		return $mimes;
	}

	/**
	 * Fonts are often detected as another mime type (application/font-sfnt, …); accept ttf and otf by extension.
	 *
	 * @param array  $data     [ ext, type, proper_filename ].
	 * @param string $file     Full path.
	 * @param string $filename File name.
	 * @param array  $mimes    Allowed mime types.
	 * @return array
	 */
	public static function font_filetype( $data, $file, $filename, $mimes ) {
		if ( ! empty( $data['ext'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return $data;
		}
		$ext   = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$types = self::font_types();
		if ( isset( $types[ $ext ] ) ) {
			$data = array(
				'ext'             => $ext,
				'type'            => $types[ $ext ],
				'proper_filename' => false,
			);
		}
		return $data;
	}

	/* ------------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------------ */

	/**
	 * Notice texts for notices.js.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'close' => __( 'Close', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * Load our own front-end scripts with "defer" (WordPress 6.3+), so they never hold up the page.
	 * Scripts that listen to WooCommerce's jQuery checkout events stay as they are.
	 */
	public static function defer_scripts() {
		$handles = array( 'aimp-notices', 'aimp-favorites', 'aimp-header', 'aimp-search', 'aimp-footer', 'aimp-ui', 'aimp-configurator', 'aimp-shop', 'aimp-product', 'aimp-cart', 'aimp-account', 'aimp-giftcards' );
		foreach ( $handles as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_script_add_data( $handle, 'strategy', 'defer' );
			}
		}
	}

	public static function enqueue() {
		wp_enqueue_style( 'aimp-site', AIMP_PLUGIN_URL . 'assets/css/site.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-notices', AIMP_PLUGIN_URL . 'assets/js/notices.js', array(), AIMP_VERSION, true );
		wp_localize_script( 'aimp-notices', 'aimpNotices', self::strings() );

		$css = self::font_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'aimp-site', $css );
		}
	}

	/**
	 * @font-face rules and the site-wide font, when the setting is on and a font file is uploaded.
	 *
	 * @return string
	 */
	public static function font_css() {
		if ( ! AIMP_Settings::get( 'site_font' ) ) {
			return '';
		}
		$faces = '';
		foreach ( array(
			'font_regular' => 400,
			'font_bold'    => 700,
		) as $setting => $weight ) {
			$url = AIMP_Settings::get( $setting ) ? wp_get_attachment_url( AIMP_Settings::get( $setting ) ) : '';
			if ( ! $url ) {
				continue;
			}
			$format = 'otf' === strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ? 'opentype' : 'truetype';
			$faces .= sprintf(
				'@font-face{font-family:"%1$s";src:url("%2$s") format("%3$s");font-weight:%4$d;font-style:normal;font-display:swap;}',
				self::FONT_FAMILY,
				esc_url_raw( $url ),
				$format,
				$weight
			);
		}
		if ( '' === $faces ) {
			return '';
		}
		$family = '"' . self::FONT_FAMILY . '", "Trajan", Georgia, "Times New Roman", serif';
		return $faces .
			'body,button,input,select,textarea,h1,h2,h3,h4,h5,h6,.wp-element-button,.wp-block-button__link,.aimp-configurator,.aimp-header{font-family:' . $family . ';}' .
			'body{font-size:15px;}';
	}
}
