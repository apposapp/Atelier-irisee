<?php
/**
 * Plugin languages (Dutch and English), independent of the WordPress site language.
 *
 * All strings in the plugin keep using __() / esc_html__() with the plugin text domain.
 * The "gettext_atelier-irisee-master-plugin" filter swaps in the Dutch text from
 * includes/languages/nl.php when the active language is Dutch.
 *
 * Active language:
 *   - WordPress admin: the "Plugin language" setting (WooCommerce > Atelier Irisee).
 *   - Front end: the language the customer picked in the configurator (aimp_lang request
 *     parameter or cookie), otherwise the same setting.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_I18n {

	const DOMAIN  = 'atelier-irisee-master-plugin';
	const COOKIE  = 'aimp_lang';
	const DEFAULT_LANG = 'nl';

	/** @var string|null Detected language for this request. */
	private static $lang = null;

	/** @var string|null Temporary language set by with_language(). */
	private static $override = null;

	/** @var array|null Dutch dictionary. */
	private static $nl = null;

	public static function init() {
		add_filter( 'gettext_' . self::DOMAIN, array( __CLASS__, 'translate' ), 10, 2 );
	}

	/**
	 * Supported languages: code => [ name, locale, flag ].
	 *
	 * @return array
	 */
	public static function languages() {
		return array(
			'nl' => array(
				'name'   => 'Nederlands',
				'locale' => 'nl-BE',
				'flag'   => AIMP_PLUGIN_URL . 'assets/images/flags/be.svg',
			),
			'en' => array(
				'name'   => 'English',
				'locale' => 'en',
				'flag'   => AIMP_PLUGIN_URL . 'assets/images/flags/gb.svg',
			),
		);
	}

	public static function is_valid( $code ) {
		return is_string( $code ) && array_key_exists( $code, self::languages() );
	}

	/**
	 * The "Plugin language" setting.
	 *
	 * @return string
	 */
	public static function default_language() {
		$options = get_option( 'aimp_settings', array() );
		$lang    = is_array( $options ) && isset( $options['language'] ) ? $options['language'] : self::DEFAULT_LANG;
		return self::is_valid( $lang ) ? $lang : self::DEFAULT_LANG;
	}

	/**
	 * Active language code for this request.
	 *
	 * @return string
	 */
	public static function current() {
		if ( null !== self::$override ) {
			return self::$override;
		}
		if ( null === self::$lang ) {
			self::$lang = self::detect();
		}
		return self::$lang;
	}

	private static function detect() {
		if ( is_admin() ) {
			return self::default_language();
		}
		// phpcs:ignore WordPress.Security.NonceVerification -- only selects a display language.
		$requested = isset( $_REQUEST['aimp_lang'] ) ? sanitize_key( wp_unslash( $_REQUEST['aimp_lang'] ) ) : '';
		if ( self::is_valid( $requested ) ) {
			return $requested;
		}
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( self::is_valid( $cookie ) ) {
			return $cookie;
		}
		return self::default_language();
	}

	/**
	 * Run a callback with a specific language active.
	 *
	 * @param string   $lang     Language code.
	 * @param callable $callback Callback.
	 * @return mixed Callback result.
	 */
	public static function with_language( $lang, $callback ) {
		$previous       = self::$override;
		self::$override = $lang;
		try {
			return call_user_func( $callback );
		} finally {
			self::$override = $previous;
		}
	}

	/**
	 * gettext filter for the plugin text domain.
	 *
	 * @param string $translation Translation from WordPress (if any).
	 * @param string $text        Original English text.
	 * @return string
	 */
	public static function translate( $translation, $text ) {
		if ( 'nl' !== self::current() ) {
			return $text;
		}
		if ( null === self::$nl ) {
			self::$nl = require AIMP_PLUGIN_DIR . 'includes/languages/nl.php';
		}
		return isset( self::$nl[ $text ] ) ? self::$nl[ $text ] : $translation;
	}

	/**
	 * Number with the decimal separator of the active language (1.20 / 1,20).
	 *
	 * @param float $number   Number.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	public static function number( $number, $decimals = 0 ) {
		return 'nl' === self::current()
			? number_format( (float) $number, $decimals, ',', '.' )
			: number_format( (float) $number, $decimals, '.', ',' );
	}
}
