<?php
/**
 * French and English for the texts that are only written in Dutch: product names and descriptions, categories,
 * colours and other product details, page texts typed in Divi, and WordPress, WooCommerce and Divi texts that the
 * Dutch site language shows in Dutch.
 *
 * On a French or English page every text between the tags (and the alt, title, placeholder and aria-label of a tag)
 * is looked up as Dutch in includes/languages/content-{fr,en}.php and replaced by its translation. The answers of
 * the shop's own requests (?wc-ajax=…, the shop, customizer and cart) get the same treatment, so what JavaScript
 * draws matches. A text without a translation stays as it is; translations made in the Design studio are used first
 * (they are no longer Dutch when this runs).
 *
 * Keys are the Dutch text with its spaces collapsed. A key with {n} stands for any number ("5 van de 5 sterren").
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Content_I18n {

	/** @var array|null Dutch => translation for the current language. */
	private static $map = null;

	/** @var array Regular expressions of the keys with {n}: [ pattern, translation with {n} ]. */
	private static $patterns = array();

	/** @var array|null Every text without a translation (only for listing what is left to translate; null: not noting). */
	public static $missed = null;

	public static function init() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether to translate the answer.
		if ( isset( $_GET['wc-ajax'] ) ) {
			add_action( 'init', array( __CLASS__, 'start' ), 1 );
		} else {
			add_action( 'template_redirect', array( __CLASS__, 'start' ), 9999 );
		}
	}

	/**
	 * Translates this answer when it is French or English (not for feeds, the REST API or the Design studio's preview,
	 * where the shop manager writes their own translations).
	 */
	public static function start() {
		$lang = AIMP_I18n::current();
		if ( 'nl' === $lang || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() ) {
			return;
		}
		if ( ! self::load( $lang ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'filter' ) );
	}

	/**
	 * @param string $lang Language.
	 * @return bool Whether there is anything to translate.
	 */
	private static function load( $lang ) {
		if ( null === self::$map ) {
			$file      = AIMP_PLUGIN_DIR . 'includes/languages/content-' . $lang . '.php';
			$list      = file_exists( $file ) ? (array) require $file : array();
			self::$map = array();
			foreach ( $list as $dutch => $text ) {
				$dutch = self::key( $dutch );
				if ( false !== strpos( $dutch, '{n}' ) || false !== strpos( $dutch, '{s}' ) ) {
					// The order of {n} and {s} in the Dutch, so each goes to its own place in the translation.
					preg_match_all( '/\{([ns])\}/', $dutch, $order );
					$regex            = str_replace( array( '\{n\}', '\{s\}' ), array( '(\d+(?:[.,]\d+)?)', '(.+?)' ), preg_quote( $dutch, '/' ) );
					self::$patterns[] = array( '/^' . $regex . '$/u', $text, $order[1] );
				} else {
					self::$map[ $dutch ] = $text;
				}
			}
		}
		return self::$map || self::$patterns;
	}

	/**
	 * The whole answer: JSON (the shop's requests) or HTML.
	 *
	 * @param string $out Output.
	 * @return string
	 */
	public static function filter( $out ) {
		if ( '' === $out ) {
			return $out;
		}
		$first = ltrim( $out );
		if ( '' !== $first && ( '{' === $first[0] || '[' === $first[0] ) ) {
			// As objects, so an empty {} stays an object.
			$data = json_decode( $out );
			if ( null === $data ) {
				return $out;
			}
			$json = wp_json_encode( self::walk( $data ) );
			return false === $json ? $out : $json;
		}
		return false !== stripos( $out, '<' ) ? self::html( $out ) : $out;
	}

	/** Fields of the shop's answers that are data, not text: a filter's key is sent back as it is ("bruin, beige"). */
	const DATA_FIELDS = array( 'key', 'slug', 'taxonomy', 'type', 'role', 'kind', 'id', 'value', 'code', 'lang', 'design', 'finish', 'rule', 'material', 'unit', 'setting', 'notion_kind', 'sort', 'status', 'nonce', 'hash', 'url', 'permalink', 'image', 'thumb', 'large', 'src', 'href', 'endpoint', 'fragments_hash' );

	/**
	 * Every string in decoded JSON (HTML strings as HTML), except the data fields.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function walk( $value ) {
		if ( is_string( $value ) ) {
			// HTML, or a text with entities ("&#8211;"), is read as HTML.
			return '' === $value ? $value : ( preg_match( '/<|&#?\w+;/', $value ) ? self::html( $value ) : self::text( $value, false ) );
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) && in_array( $key, self::DATA_FIELDS, true ) ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$value[ $key ] = self::walk( $item );
				} elseif ( '' !== (string) $key ) {
					$value->{$key} = self::walk( $item );
				}
			}
		}
		return $value;
	}

	/**
	 * HTML: the texts between the tags and a few attributes; never inside scripts, styles or comments.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html( $html ) {
		// First whole paragraphs, headings, list items and cells with only inline tags inside ("Deze <strong>stof</strong>
		// is …"): translated as one text, which reads better than piece by piece.
		$html = preg_replace_callback(
			'/(<(p|li|h[1-6]|td|th|dt|dd|figcaption|label|button)\b[^>]*>)((?:(?!<\/?(?:p|li|h[1-6]|td|th|dt|dd|div|ul|ol|table|section|script|style|figcaption|label|button)\b)[\s\S])*?)(<\/\2>)/i',
			function ( $m ) {
				// Only with formatting inside (bold, italic, …): links, line breaks and pictures must stay, and so must the
				// parts of the shop and its forms (a price, the * of a required field, a step number); those go piece by piece.
				if ( false === strpos( $m[3], '<' ) || preg_match( '/<(?!\/?(?:strong|b|em|i|u|span|mark|small|sup|sub)\b)/i', $m[3] )
					|| preg_match( '/class=["\'][^"\']*\b(required|optional|screen-reader|amount|price|aimp-|woocommerce)/i', $m[3] ) ) {
					return $m[0];
				}
				$found = self::find( self::key( html_entity_decode( wp_strip_all_tags( $m[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
				return null === $found ? $m[0] : $m[1] . esc_html( $found ) . $m[4];
			},
			$html
		);
		$parts = preg_split( '/(<script\b.*?<\/script>|<style\b.*?<\/style>|<!--.*?-->|<[^>]*>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $html;
		}
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( preg_match( '/^<(script|style|!--)/i', $part ) ) {
					continue;
				}
				$parts[ $i ] = preg_replace_callback(
					'/(\s(?:alt|title|placeholder|aria-label|data-title)=)(["\'])(.*?)\2/is',
					function ( $m ) {
						return $m[1] . $m[2] . self::text( $m[3], true ) . $m[2];
					},
					$part
				);
				// The label of a button written as <input type="submit" value="Verzenden">.
				if ( preg_match( '/^<input\b[^>]*\stype=(["\']?)(?:submit|button|reset)\1/i', $part ) ) {
					$parts[ $i ] = preg_replace_callback(
						'/(\svalue=)(["\'])(.*?)\2/is',
						function ( $m ) {
							return $m[1] . $m[2] . self::text( $m[3], true ) . $m[2];
						},
						$parts[ $i ]
					);
				}
				// The shop's first products, sent along with the page as JSON (AIMP_Shop).
				if ( false !== strpos( $part, 'data-initial=' ) ) {
					$parts[ $i ] = preg_replace_callback(
						'/(\sdata-initial=)(["\'])(.*?)\2/s',
						function ( $m ) {
							$data = json_decode( html_entity_decode( $m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
							$json = null === $data ? false : wp_json_encode( self::walk( $data ) );
							return false === $json ? $m[0] : $m[1] . $m[2] . esc_attr( $json ) . $m[2];
						},
						$parts[ $i ]
					);
				}
				continue;
			}
			$parts[ $i ] = self::text( $part, true );
		}
		return implode( '', $parts );
	}

	/**
	 * One text, kept with its spaces around it.
	 *
	 * @param string $raw     Text.
	 * @param bool   $encoded Whether it is HTML (entities in, escaped out).
	 * @return string
	 */
	public static function text( $raw, $encoded ) {
		// Not a text: no letters, or an address (the pictures and links in the shop's answers).
		if ( ! preg_match( '/[A-Za-z]{2}/', $raw ) || preg_match( '#^\s*(https?:)?//#i', $raw ) ) {
			return $raw;
		}
		$plain = $encoded ? html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $raw;
		$found = self::find( self::key( $plain ) );
		if ( null === $found ) {
			return $raw;
		}
		// The spaces around it as they were ("Voornaam&nbsp;" keeps its non-breaking space before the *).
		preg_match( '/^[\s\x{00A0}]*/u', $plain, $lead );
		preg_match( '/[\s\x{00A0}]*$/u', $plain, $tail );
		return $lead[0] . ( $encoded ? esc_html( $found ) : $found ) . $tail[0];
	}

	/**
	 * The translation of a key, or null.
	 *
	 * @param string $key Key (key()).
	 * @return string|null
	 */
	private static function find( $key ) {
		if ( '' === $key ) {
			return null;
		}
		if ( isset( self::$map[ $key ] ) ) {
			return self::$map[ $key ];
		}
		foreach ( self::$patterns as $pattern ) {
			if ( ! preg_match( $pattern[0], $key, $m ) ) {
				continue;
			}
			// {n}: the number as it is; {s}: a text that is translated itself ("Wees de eerste om "{s}" te beoordelen").
			$text = $pattern[1];
			foreach ( $pattern[2] as $i => $kind ) {
				$value = $m[ $i + 1 ];
				if ( 's' === $kind ) {
					$inner = isset( self::$map[ $value ] ) ? self::$map[ $value ] : null;
					if ( null === $inner && null !== self::$missed ) {
						self::$missed[ $value ] = true;
					}
					$value = null !== $inner ? $inner : $value;
				}
				$pos = strpos( $text, '{' . $kind . '}' );
				if ( false !== $pos ) {
					$text = substr_replace( $text, $value, $pos, 3 );
				}
			}
			return $text;
		}
		if ( null !== self::$missed ) {
			self::$missed[ $key ] = true;
		}
		return null;
	}

	/**
	 * A text as a key: spaces collapsed, curly quotes, a dash between words and the ellipsis written plainly (the same
	 * text comes as "Cha' Coud - Lison" in one place and "Cha’ Coud – Lison" in another). A range keeps its dash
	 * ("18–22 cm").
	 *
	 * @param string $text Text (no entities).
	 * @return string
	 */
	public static function key( $text ) {
		$text = str_replace( array( "\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{201E}", "\u{2026}" ), array( "'", "'", '"', '"', '"', '...' ), $text );
		$text = trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
		return preg_replace( '/(^| )[\x{2013}\x{2014}]( |$)/u', '$1-$2', $text );
	}
}
