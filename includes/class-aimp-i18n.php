<?php
/**
 * Plugin languages (Dutch, French and English), independent of the WordPress site language.
 *
 * All strings in the plugin keep using __() / esc_html__() with the plugin text domain.
 * The "gettext_atelier-irisee-master-plugin" filter swaps in the translated text from
 * includes/languages/{code}.php (nl.php, fr.php) for the active language.
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

	const DOMAIN       = 'atelier-irisee-master-plugin';
	const COOKIE       = 'aimp_lang';
	const DEFAULT_LANG = 'nl';

	/** @var string|null Detected language for this request. */
	private static $lang = null;

	/** @var string|null Temporary language set by with_language(). */
	private static $override = null;

	/** @var array Loaded dictionaries: language code => [ English => translation ]. */
	private static $dictionaries = array();

	public static function init() {
		add_filter( 'gettext_' . self::DOMAIN, array( __CLASS__, 'translate' ), 10, 2 );
		add_filter( 'ngettext_' . self::DOMAIN, array( __CLASS__, 'translate_plural' ), 10, 4 );
	}

	/**
	 * Supported languages, in the order of the flag switcher: code => [ name, locale, flag, decimal, thousand ].
	 * Every language except English has a dictionary in includes/languages/{code}.php.
	 *
	 * @return array
	 */
	public static function languages() {
		return array(
			'nl' => array(
				'name'     => 'Nederlands',
				'locale'   => 'nl-BE',
				'flag'     => AIMP_PLUGIN_URL . 'assets/images/flags/be.svg',
				'decimal'  => ',',
				'thousand' => '.',
			),
			'fr' => array(
				'name'     => 'Français',
				'locale'   => 'fr',
				'flag'     => AIMP_PLUGIN_URL . 'assets/images/flags/fr.svg',
				'decimal'  => ',',
				'thousand' => "\u{00A0}",
			),
			'en' => array(
				'name'     => 'English',
				'locale'   => 'en',
				'flag'     => AIMP_PLUGIN_URL . 'assets/images/flags/gb.svg',
				'decimal'  => '.',
				'thousand' => ',',
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
		$dictionary = self::dictionary( self::current() );
		return isset( $dictionary[ $text ] ) ? $dictionary[ $text ] : $text;
	}

	/**
	 * ngettext filter: the dictionaries hold the singular and the plural English text as separate entries.
	 *
	 * @param string $translation Translation from WordPress (if any).
	 * @param string $single      Singular English text.
	 * @param string $plural      Plural English text.
	 * @param int    $number      Number deciding singular or plural.
	 * @return string
	 */
	public static function translate_plural( $translation, $single, $plural, $number ) {
		$text = 1 === (int) $number ? $single : $plural;
		return self::translate( $translation, $text );
	}

	/**
	 * Dictionary for a language (empty for English, the source language).
	 *
	 * @param string $lang Language code.
	 * @return array
	 */
	private static function dictionary( $lang ) {
		if ( 'en' === $lang ) {
			return array();
		}
		if ( ! isset( self::$dictionaries[ $lang ] ) ) {
			$file                        = AIMP_PLUGIN_DIR . 'includes/languages/' . $lang . '.php';
			self::$dictionaries[ $lang ] = file_exists( $file ) ? (array) require $file : array();
		}
		return self::$dictionaries[ $lang ];
	}

	/**
	 * Number with the separators of the active language (1.20 / 1,20).
	 *
	 * @param float $number   Number.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	public static function number( $number, $decimals = 0 ) {
		$languages = self::languages();
		$language  = $languages[ self::current() ];
		return number_format( (float) $number, $decimals, $language['decimal'], $language['thousand'] );
	}
}
