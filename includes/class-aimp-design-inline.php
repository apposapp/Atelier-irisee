<?php
/**
 * Design studio: texts edited on the page itself ("Edit texts on the page").
 *
 * - The studio's own texts (home blocks, hero, About page, footer…): the preview gets a list of what each text shows,
 *   so a click on it can be edited in place; the change is a draft setting like in the side panel.
 * - Texts typed in Divi (text, heading, button and other modules): each text gets a key (its page and a short hash of
 *   its Dutch wording). Edits are draft settings "divi:{page}:{hash}_{language}". On Publish, the Dutch goes into the
 *   page itself; French and English are kept in an option and shown to French and English visitors.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Design_Inline {

	/** French and English versions of Divi texts: hash of the Dutch => [ fr => …, en => … ]. */
	const OPTION = 'aimp_divi_texts';

	/** Draft keys of Divi texts: divi:{page}:{hash}_{language}. */
	const KEY = '/^divi:(\d+):([a-f0-9]{12})_([a-z]{2})$/';

	/** In the preview: the Divi texts of this page, for the studio. */
	private static $seen = array();

	public static function init() {
		add_filter( 'render_block_data', array( __CLASS__, 'divi_block' ), 10, 1 );
		add_action( 'wp_footer', array( __CLASS__, 'print_texts' ), 1001 );
	}

	/**
	 * A short key for a Divi text: the first 12 characters of the md5 of its Dutch wording.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function hash( $value ) {
		return substr( md5( (string) $value ), 0, 12 );
	}

	/**
	 * Words a visitor reads (not a link, a number or a shortcode).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_text( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$plain = trim( wp_strip_all_tags( $value ) );
		if ( '' === $plain || false !== strpos( $value, '[' ) ) {
			return false;
		}
		return ! preg_match( '#^(https?://|/|\#|mailto:|tel:)\S*$#i', $plain ) && ! is_numeric( $plain );
	}

	/**
	 * Calls $fn( &$value ) for every text of a Divi module's settings ("innerContent" → "desktop" → "value", or its
	 * "text" for buttons).
	 *
	 * @param array    $node Settings.
	 * @param callable $fn   Callback.
	 */
	private static function walk( &$node, $fn ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => &$child ) {
			if ( 'innerContent' === $key && is_array( $child ) && isset( $child['desktop']['value'] ) ) {
				if ( is_string( $child['desktop']['value'] ) ) {
					$fn( $child['desktop']['value'] );
				} elseif ( is_array( $child['desktop']['value'] ) && isset( $child['desktop']['value']['text'] ) && is_string( $child['desktop']['value']['text'] ) ) {
					$fn( $child['desktop']['value']['text'] );
				}
				continue;
			}
			if ( is_array( $child ) ) {
				self::walk( $child, $fn );
			}
		}
		unset( $child );
	}

	/**
	 * Before a Divi module is drawn: its draft wording (studio preview) or its French / English version.
	 *
	 * @param array $block Parsed block.
	 * @return array
	 */
	public static function divi_block( $block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( 0 !== strpos( $name, 'divi/' ) || 'divi/code' === $name || empty( $block['attrs'] ) || is_admin() ) {
			return $block;
		}
		$lang     = class_exists( 'AIMP_I18n' ) ? AIMP_I18n::current() : 'nl';
		$preview  = class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview();
		$post     = (int) get_queried_object_id();
		$versions = 'nl' === $lang && ! $preview ? array() : (array) get_option( self::OPTION, array() );
		self::walk(
			$block['attrs'],
			function ( &$value ) use ( $lang, $preview, $post, $versions ) {
				if ( ! self::is_text( $value ) ) {
					return;
				}
				$hash  = self::hash( $value );
				$saved = isset( $versions[ $hash ] ) && is_array( $versions[ $hash ] ) ? $versions[ $hash ] : array();
				$shown = $value;
				if ( 'nl' !== $lang && ! empty( $saved[ $lang ] ) ) {
					$shown = $saved[ $lang ];
				}
				if ( $preview && $post ) {
					$draft = AIMP_Design::setting_draft( 'divi', $post . ':' . $hash . '_' . $lang );
					if ( null !== $draft && '' !== trim( $draft ) ) {
						$shown = $draft;
					}
					self::$seen[ $hash ] = array(
						'post'  => $post,
						'hash'  => $hash,
						'nl'    => $value,
						'fr'    => isset( $saved['fr'] ) ? $saved['fr'] : '',
						'en'    => isset( $saved['en'] ) ? $saved['en'] : '',
						'shown' => $shown,
						'rich'  => (bool) preg_match( '/<(p|br|strong|em|a|ul|ol|li|span|h\d)\b/i', $value ),
					);
				}
				$value = $shown;
			}
		);
		return $block;
	}

	/**
	 * In the studio's preview: every text that can be edited in place (Divi texts and the studio's own texts).
	 */
	public static function print_texts() {
		if ( ! class_exists( 'AIMP_Design' ) || ! AIMP_Design::is_preview() ) {
			return;
		}
		$data = array(
			'lang'     => AIMP_I18n::current(),
			'divi'     => array_values( self::$seen ),
			'settings' => AIMP_Design::inline_settings(),
		);
		echo '<script>window.aimpInlineTexts=' . wp_json_encode( $data ) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON (wp_json_encode escapes "</").
	}

	/**
	 * A draft value of a Divi text: kept when the key is valid (allowed HTML like the editor's).
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return string|null Clean value, or null for another key.
	 */
	public static function clean_draft( $key, $value ) {
		if ( ! preg_match( self::KEY, (string) $key, $m ) || ! is_scalar( $value ) || ! array_key_exists( $m[3], AIMP_I18n::languages() ) ) {
			return null;
		}
		$value = wp_kses_post( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 20000 ) : substr( $value, 0, 20000 );
	}


	/**
	 * Publish: Dutch into the pages, French and English into the option (under the hash of the new Dutch wording).
	 * A text that is not in its page itself is looked for in Divi's Theme Builder (header, body, footer) and Library.
	 *
	 * @param array $draft key => value (only divi: keys).
	 * @return array Draft entries (key => value) that could not be placed; they stay in the draft.
	 */
	public static function publish( $draft ) {
		$by_page = array();
		foreach ( $draft as $key => $value ) {
			if ( preg_match( self::KEY, $key, $m ) ) {
				$by_page[ (int) $m[1] ][ $m[2] ][ $m[3] ] = $value;
			}
		}
		$versions = (array) get_option( self::OPTION, array() );
		$failed   = array();
		// Contents to save: post ID => content (the page itself and any Divi layout a text was found in).
		$contents = array();
		foreach ( $by_page as $post_id => $texts ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			foreach ( $texts as $hash => $langs ) {
				$new_hash = $hash;
				if ( isset( $langs['nl'] ) && '' !== trim( $langs['nl'] ) ) {
					$placed = false;
					foreach ( array_merge( array( $post_id ), self::layout_ids() ) as $id ) {
						if ( ! isset( $contents[ $id ] ) ) {
							$source = $id === $post_id ? $post : get_post( $id );
							if ( ! $source ) {
								continue;
							}
							$contents[ $id ] = array( $source->post_content, false );
						}
						$result = self::replace_in_content( $contents[ $id ][0], $hash, $langs['nl'] );
						if ( null === $result ) {
							continue;
						}
						if ( is_string( $result ) ) {
							$contents[ $id ] = array( $result, true );
							$placed          = true;
						}
						break;
					}
					if ( ! $placed ) {
						foreach ( $langs as $lang => $value ) {
							$failed[ 'divi:' . $post_id . ':' . $hash . '_' . $lang ] = $value;
						}
						continue;
					}
					$new_hash = self::hash( $langs['nl'] );
				}
				$keep = isset( $versions[ $hash ] ) ? (array) $versions[ $hash ] : array();
				foreach ( $langs as $lang => $value ) {
					if ( 'nl' !== $lang ) {
						$keep[ $lang ] = $value;
					}
				}
				unset( $versions[ $hash ] );
				$keep = array_filter( $keep, 'strlen' );
				if ( $keep ) {
					$versions[ $new_hash ] = $keep;
				}
			}
		}
		foreach ( $contents as $id => $content ) {
			if ( $content[1] ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $id,
							'post_content' => $content[0],
						)
					)
				);
			}
		}
		update_option( self::OPTION, $versions, false );
		return $failed;
	}

	/**
	 * Divi's Theme Builder layouts (header, body, footer) and Library items: texts shown on a page can live there.
	 *
	 * @return int[]
	 */
	private static function layout_ids() {
		static $ids = null;
		if ( null === $ids ) {
			$ids = array_map(
				'absint',
				get_posts(
					array(
						'post_type'      => array( 'et_header_layout', 'et_body_layout', 'et_footer_layout', 'et_pb_layout' ),
						'post_status'    => array( 'publish', 'draft', 'private' ),
						'posts_per_page' => 200,
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				)
			);
		}
		return $ids;
	}

	/**
	 * The content with one Divi text (found by its hash) replaced; null when the text is not in it, false when it
	 * cannot be placed safely (it is there more than once).
	 *
	 * @param string $content Page content.
	 * @param string $hash    Hash of the old Dutch wording.
	 * @param string $value   New Dutch wording.
	 * @return string|false|null
	 */
	private static function replace_in_content( $content, $hash, $value ) {
		$result = null;
		$visit  = function ( $blocks ) use ( &$visit, &$result, $content, $hash, $value ) {
			foreach ( $blocks as $block ) {
				if ( null !== $result ) {
					return;
				}
				if ( ! empty( $block['blockName'] ) && 0 === strpos( $block['blockName'], 'divi/' ) && ! empty( $block['attrs'] ) ) {
					$attrs = $block['attrs'];
					$old   = null;
					self::walk(
						$attrs,
						function ( &$text ) use ( $hash, $value, &$old ) {
							if ( null === $old && is_string( $text ) && self::hash( $text ) === $hash ) {
								$old  = $text;
								$text = $value;
							}
						}
					);
					if ( null !== $old ) {
						$result = self::swap( $content, $block['attrs'], $attrs, $old, $value );
						return;
					}
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$visit( $block['innerBlocks'] );
				}
			}
		};
		$visit( parse_blocks( $content ) );
		return $result;
	}

	/**
	 * Swaps the old wording for the new one in the saved content: the module's whole settings when they are saved
	 * exactly as WordPress writes them, otherwise the wording itself (as Divi may have written it) when it is there
	 * exactly once.
	 *
	 * @param string $content   Content.
	 * @param array  $old_attrs The module's settings.
	 * @param array  $new_attrs The settings with the new wording.
	 * @param string $old       Old wording.
	 * @param string $new       New wording.
	 * @return string|false
	 */
	private static function swap( $content, $old_attrs, $new_attrs, $old, $new ) {
		$from = serialize_block_attributes( $old_attrs );
		if ( 1 === substr_count( $content, $from ) ) {
			return str_replace( $from, serialize_block_attributes( $new_attrs ), $content );
		}
		$styles = array(
			function ( $text ) {
				return substr( serialize_block_attributes( array( $text ) ), 1, -1 );
			},
			function ( $text ) {
				return wp_json_encode( $text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			},
			function ( $text ) {
				return wp_json_encode( $text );
			},
		);
		foreach ( $styles as $style ) {
			$from = $style( $old );
			if ( is_string( $from ) && 1 === substr_count( $content, $from ) ) {
				return str_replace( $from, $style( $new ), $content );
			}
		}
		return false;
	}
}
