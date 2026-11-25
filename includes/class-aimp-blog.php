<?php
/**
 * The blog: posts built from rows (AIMP_Blog_Builder) shown in the design of the site, and [atelier_irisee_blog], an
 * overview of all posts as polaroids, newest first, with the filter bar of the shop (categories, search, sorting).
 *
 * A post is made of rows of at most three blocks: a title, a text, a picture (in the shapes of the About page) or a
 * video (from the media library, or a YouTube or Vimeo link that only loads when it is played). A row with one block
 * fills the whole width, in the middle. Above it comes the page header with the post's picture and title, below it
 * the like, share and view counts, the previous and next post and more posts of its category.
 *
 * Likes and views are kept per post (_aimp_likes, _aimp_views). A visitor likes a post once (a cookie in the browser
 * and an anonymous hash on the server); a view counts once a day per visitor, through the anonymous statistics
 * (AIMP_Stats, not for the shop's own people).
 *
 * The look and the colours of the categories: Design studio → Blog.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Blog {

	const TAG    = 'atelier_irisee_blog';
	/** The newest post on the home page (latest()). */
	const LATEST = 'atelier_irisee_latest_post';
	const ROWS   = '_aimp_blog_rows';
	const LOOK   = '_aimp_blog_look';
	const LIKES  = '_aimp_likes';
	const VIEWS  = '_aimp_views';
	const COOKIE = 'aimp_blog_likes';
	const SORTS  = array( 'newest', 'oldest', 'likes', 'views' );

	/** The blog's own categories (made once if they are missing) with their standard colour. */
	const CATEGORIES = array(
		'irisee-focus'    => array( 'Irisée Focus', '#b38f4f' ),
		'irisee-inspired' => array( 'Irisée Inspired', '#c48b84' ),
		'irisee-moments'  => array( 'Irisée Moments', '#7d9a82' ),
	);

	/**
	 * The text and spacing of one post (the builder; empty = the Design studio's): key => [ kind, maximum or choices ].
	 * The same settings as Design studio → Blog post (bl_ + key).
	 */
	const LOOK_FIELDS = array(
		'row'       => array( 'px', 240 ),
		'row_s'     => array( 'px', 240 ),
		'row_l'     => array( 'px', 240 ),
		'colgap'    => array( 'px', 160 ),
		'stack'     => array( 'px', 160 ),
		'stack_s'   => array( 'px', 160 ),
		'stack_l'   => array( 'px', 160 ),
		'pt_size'   => array( 'px', 120 ),
		'pt_color'  => array( 'color', 0 ),
		'h2_size'   => array( 'px', 120 ),
		'h2_color'  => array( 'color', 0 ),
		'h2_noline' => array( 'bool', 0 ),
		'h3_size'   => array( 'px', 80 ),
		'h3_color'  => array( 'color', 0 ),
		't_weight'  => array( 'select', array( '400', '600', '700' ) ),
		'tx_size'   => array( 'px', 40 ),
		'tx_lh'     => array( 'select', array( '1.4', '1.6', '1.8', '2', '2.2' ) ),
		'tx_color'  => array( 'color', 0 ),
		'tx_width'  => array( 'px', 1400 ),
		'cap_size'  => array( 'px', 30 ),
		'cap_color' => array( 'color', 0 ),
	);

	/** The content filter is running (an excerpt inside it must not start it again). */
	private static $busy = false;

	/** Overviews on this page (their ids). */
	private static $count = 0;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_shortcode( self::LATEST, array( __CLASS__, 'latest' ) );
		add_action( 'init', array( __CLASS__, 'categories' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'get_post_metadata', array( __CLASS__, 'divi_layout' ), 10, 3 );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		add_action( 'wc_ajax_aimp_blog', array( __CLASS__, 'ajax_list' ) );
		add_action( 'wc_ajax_aimp_blog_like', array( __CLASS__, 'ajax_like' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'order_clauses' ), 10, 2 );
	}

	/* ------------------------------------------------------------------
	 * Settings, categories, assets
	 * ------------------------------------------------------------------ */

	/**
	 * A Design studio setting: the overview (blog) or the post page (blog-post).
	 *
	 * @param string $key     Setting.
	 * @param mixed  $default When it is not set.
	 * @param string $part    blog or blog-post.
	 * @return mixed
	 */
	private static function design( $key, $default = null, $part = 'blog' ) {
		return AIMP_Blocks::design( $part, $key, $default );
	}

	/** The four categories of the blog, once (they can be renamed or removed afterwards). */
	public static function categories() {
		if ( '1' === get_option( 'aimp_blog_cats' ) || ! taxonomy_exists( 'category' ) ) {
			return;
		}
		foreach ( self::CATEGORIES as $slug => $row ) {
			if ( ! term_exists( $slug, 'category' ) && ! term_exists( $row[0], 'category' ) ) {
				wp_insert_term( $row[0], 'category', array( 'slug' => $slug ) );
			}
		}
		update_option( 'aimp_blog_cats', '1', true );
	}

	/** The page with the overview is looked up again after a page is saved. */
	public static function flush() {
		delete_transient( 'aimp_blog_page' );
	}

	public static function register_assets() {
		if ( ! wp_style_is( 'aimp-home-blocks', 'registered' ) ) {
			AIMP_Home_Blocks::register_assets();
		}
		if ( ! wp_style_is( 'aimp-configurator', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
		}
		if ( ! wp_style_is( 'aimp-shop', 'registered' ) ) {
			wp_register_style( 'aimp-shop', AIMP_PLUGIN_URL . 'assets/css/shop.css', array( 'aimp-configurator' ), AIMP_VERSION );
		}
		wp_register_style( 'aimp-blog', AIMP_PLUGIN_URL . 'assets/css/blog.css', array( 'aimp-home-blocks', 'aimp-shop' ), AIMP_VERSION );
		wp_register_script( 'aimp-blog', AIMP_PLUGIN_URL . 'assets/js/blog.js', array(), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-blog',
			'aimpBlog',
			array(
				'endpoint' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
				'lang'     => class_exists( 'AIMP_I18n' ) ? AIMP_I18n::current() : '',
				'i18n'     => array(
					'copied'   => __( 'Link copied', 'atelier-irisee-master-plugin' ),
					'copy'     => __( 'Copy link', 'atelier-irisee-master-plugin' ),
					'email'    => __( 'E-mail', 'atelier-irisee-master-plugin' ),
					'share'    => __( 'Share', 'atelier-irisee-master-plugin' ),
					'close'    => __( 'Close', 'atelier-irisee-master-plugin' ),
					'filters'  => __( 'Filters', 'atelier-irisee-master-plugin' ),
					'error'    => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
				),
			)
		);
		if ( is_singular( 'post' ) || AIMP_Blocks::page_has( array( self::TAG, self::LATEST ) ) ) {
			self::enqueue();
		}
	}

	private static function enqueue() {
		if ( ! wp_style_is( 'aimp-blog', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-blog' );
		wp_enqueue_script( 'aimp-blog' );
		// The full width of the blocks (the scrollbar) and the share code of the cards.
		wp_enqueue_script( 'aimp-home-blocks' );
	}

	/**
	 * The colour of a category: Design studio → Blog, else the standard one of the blog's categories, else gold.
	 *
	 * @param WP_Term $term Category.
	 * @return string A colour (hex, or a link to a Divi colour).
	 */
	public static function color( $term ) {
		$value = (string) self::design( 'bl_cat_' . $term->term_id, '' );
		if ( '' !== $value ) {
			return $value;
		}
		return isset( self::CATEGORIES[ $term->slug ] ) ? self::CATEGORIES[ $term->slug ][1] : 'var(--aimp-site-gold, #b38f4f)';
	}

	/**
	 * The text colour that reads on a colour: white on dark and middle colours, brown on light ones.
	 *
	 * @param string $color Colour (its last #hex counts: also a link to a Divi colour with its fallback).
	 * @return string
	 */
	public static function ink( $color ) {
		if ( ! preg_match_all( '/#([0-9a-f]{6}|[0-9a-f]{3})\b/i', $color, $found ) ) {
			return '#fff';
		}
		$hex = end( $found[1] );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		list( $r, $g, $b ) = array_map( 'hexdec', str_split( $hex, 2 ) );
		return ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255 > 0.62 ? 'var(--aimp-site-brown, #613907)' : '#fff';
	}

	/**
	 * The main category of a post (not "Uncategorized" when it has another one).
	 *
	 * @param int $id Post.
	 * @return WP_Term|null
	 */
	public static function category( $id ) {
		$default = (int) get_option( 'default_category' );
		$terms   = get_the_category( $id );
		foreach ( $terms as $term ) {
			if ( (int) $term->term_id !== $default ) {
				return $term;
			}
		}
		return $terms ? $terms[0] : null;
	}

	/**
	 * A category's label in its colour.
	 *
	 * @param WP_Term $term Category.
	 * @param string  $href A link (empty: no link).
	 * @return string
	 */
	public static function label( $term, $href = '' ) {
		$color = self::color( $term );
		$style = '--bl-cat:' . $color . ';--bl-cat-ink:' . self::ink( $color );
		$tag   = '' !== $href ? 'a' : 'span';
		return '<' . $tag . ' class="aimp-blog-label" style="' . esc_attr( $style ) . '"' . ( '' !== $href ? ' href="' . esc_url( $href ) . '"' : '' ) . '>' . esc_html( $term->name ) . '</' . $tag . '>';
	}

	/**
	 * The page with the overview: Design studio, else the published page with the shortcode, else WordPress's own
	 * posts page.
	 *
	 * @return string '' when there is none.
	 */
	public static function overview_url() {
		$url = (string) self::design( 'bl_back', '', 'blog-post' );
		if ( '' !== $url ) {
			return $url;
		}
		$id = get_transient( 'aimp_blog_page' );
		if ( false === $id ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID LIMIT 1", '%' . $wpdb->esc_like( '[' . self::TAG ) . '%' ) );
			set_transient( 'aimp_blog_page', $id, DAY_IN_SECONDS );
		}
		if ( $id ) {
			return (string) get_permalink( (int) $id );
		}
		$posts = (int) get_option( 'page_for_posts' );
		return $posts ? (string) get_permalink( $posts ) : '';
	}

	/* ------------------------------------------------------------------
	 * The rows of a post
	 * ------------------------------------------------------------------ */

	/**
	 * The rows of a post. In a preview of a published post: the rows of its preview copy (the builder puts them in
	 * the copy's content).
	 *
	 * @param int $id Post.
	 * @return array
	 */
	public static function rows( $id ) {
		if ( is_preview() && function_exists( 'wp_get_post_autosave' ) ) {
			$autosave = wp_get_post_autosave( $id, get_current_user_id() );
			if ( $autosave ) {
				$rows = self::from_content( $autosave->post_content );
				if ( null !== $rows ) {
					return self::clean_rows( $rows );
				}
			}
		}
		$rows = json_decode( (string) get_post_meta( $id, self::ROWS, true ), true );
		return is_array( $rows ) ? array_map( array( __CLASS__, 'upgrade_row' ), $rows ) : array();
	}

	/**
	 * The rows carried in the builder's content field (<!-- aimp-blog:base64 json -->).
	 *
	 * @param string $content Content.
	 * @return array|null Null when the content is something else.
	 */
	public static function from_content( $content ) {
		$payload = self::payload( $content );
		return null === $payload ? null : $payload['rows'];
	}

	/**
	 * The builder's data in the content field: the rows, and the post's own text and spacing (look). Before 3.75.2 the
	 * field held the rows alone.
	 *
	 * @param string $content Content.
	 * @return array|null [ 'rows' => array, 'look' => array ], null when the content is something else.
	 */
	public static function payload( $content ) {
		if ( ! preg_match( '/^\s*<!-- aimp-blog:([A-Za-z0-9+\/=]*) -->\s*$/', (string) $content, $found ) ) {
			return null;
		}
		$data = json_decode( (string) base64_decode( $found[1] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the builder's own data.
		if ( is_array( $data ) && isset( $data['rows'] ) ) {
			return array(
				'rows' => is_array( $data['rows'] ) ? $data['rows'] : array(),
				'look' => isset( $data['look'] ) && is_array( $data['look'] ) ? $data['look'] : array(),
			);
		}
		return array(
			'rows' => is_array( $data ) ? $data : array(),
			'look' => array(),
		);
	}

	/**
	 * The content field for the builder: its rows and look.
	 *
	 * @param array $rows Rows.
	 * @param array $look Look.
	 * @return string
	 */
	public static function encode( $rows, $look ) {
		$data = $look ? array(
			'rows' => $rows,
			'look' => $look,
		) : $rows;
		return '<!-- aimp-blog:' . base64_encode( (string) wp_json_encode( $data ) ) . ' -->'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the builder's own data.
	}

	/**
	 * A post's own text and spacing, made safe (only what differs from the Design studio).
	 *
	 * @param mixed $look Look.
	 * @return array
	 */
	public static function clean_look( $look ) {
		$clean = array();
		foreach ( self::LOOK_FIELDS as $key => $field ) {
			if ( ! is_array( $look ) || ! isset( $look[ $key ] ) || '' === $look[ $key ] || ! is_scalar( $look[ $key ] ) ) {
				continue;
			}
			$value = (string) $look[ $key ];
			if ( 'px' === $field[0] && is_numeric( $value ) ) {
				$clean[ $key ] = max( 0, min( $field[1], (int) $value ) );
			} elseif ( 'color' === $field[0] && preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
				$clean[ $key ] = strtolower( $value );
			} elseif ( 'bool' === $field[0] && $value && '0' !== $value ) {
				$clean[ $key ] = 1;
			} elseif ( 'select' === $field[0] && in_array( $value, $field[1], true ) ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * A post's own text and spacing. In a preview: those of its preview copy.
	 *
	 * @param int $id Post.
	 * @return array
	 */
	public static function look( $id ) {
		if ( is_preview() && function_exists( 'wp_get_post_autosave' ) ) {
			$autosave = wp_get_post_autosave( $id, get_current_user_id() );
			if ( $autosave ) {
				$payload = self::payload( $autosave->post_content );
				if ( null !== $payload ) {
					return self::clean_look( $payload['look'] );
				}
			}
		}
		$look = json_decode( (string) get_post_meta( $id, self::LOOK, true ), true );
		return is_array( $look ) ? self::clean_look( $look ) : array();
	}

	/**
	 * The CSS of a post's own text and spacing (over the Design studio's).
	 *
	 * @param int $id Post.
	 * @return string A <style> element, or ''.
	 */
	private static function look_css( $id ) {
		$look = self::look( $id );
		if ( ! $look || ! class_exists( 'AIMP_Design' ) ) {
			return '';
		}
		$get = function ( $part, $key ) use ( $look ) {
			$key = (string) substr( $key, 3 );
			return isset( $look[ $key ] ) ? $look[ $key ] : null;
		};
		$css = AIMP_Design::blog_post_css( $get, 'html body .aimp-blog-post[data-aimp-blog-post="' . (int) $id . '"]' );
		return '' !== $css ? '<style id="aimp-blog-look-' . (int) $id . '">' . $css . '</style>' : '';
	}

	/**
	 * A row of before 3.75 (a flat list of blocks side by side) as columns of one block each.
	 *
	 * @param mixed $row Row.
	 * @return array
	 */
	public static function upgrade_row( $row ) {
		if ( ! is_array( $row ) ) {
			return array( 'cols' => array() );
		}
		if ( ! isset( $row['cols'] ) || ! is_array( $row['cols'] ) ) {
			$row['cols'] = array();
			foreach ( isset( $row['blocks'] ) && is_array( $row['blocks'] ) ? $row['blocks'] : array() as $block ) {
				$row['cols'][] = array( $block );
			}
		}
		unset( $row['blocks'] );
		return $row;
	}

	/**
	 * All blocks of a row, column after column.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function row_blocks( $row ) {
		$blocks = array();
		foreach ( self::upgrade_row( $row )['cols'] as $col ) {
			foreach ( is_array( $col ) ? $col : array() as $block ) {
				if ( is_array( $block ) ) {
					$blocks[] = $block;
				}
			}
		}
		return $blocks;
	}

	/**
	 * Rows as the builder sends them, made safe: at most 60 rows of 3 columns of 8 blocks under each other, every
	 * value checked.
	 *
	 * @param mixed $rows Rows.
	 * @return array
	 */
	public static function clean_rows( $rows ) {
		$pick  = function ( $value, $list ) {
			return in_array( $value, $list, true ) ? $value : $list[0];
		};
		$text  = function ( $value, $length = 300 ) {
			$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
		};
		$clean = array();
		if ( ! is_array( $rows ) ) {
			return $clean;
		}
		foreach ( array_slice( $rows, 0, 60 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row  = self::upgrade_row( $row );
			$cols = array();
			foreach ( array_slice( $row['cols'], 0, 3 ) as $col ) {
				$blocks = array();
				foreach ( array_slice( is_array( $col ) ? $col : array(), 0, 8 ) as $block ) {
					if ( ! is_array( $block ) ) {
						continue;
					}
					$get  = function ( $key, $default = '' ) use ( $block ) {
						return isset( $block[ $key ] ) ? $block[ $key ] : $default;
					};
					$type = $pick( $get( 't' ), array( 'text', 'title', 'image', 'video' ) );
					$item = array(
						't'   => $type,
						'gap' => $pick( $get( 'gap' ), array( '', 'none', 'small', 'large' ) ),
					);
					if ( 'title' === $type ) {
						$item['text']  = $text( $get( 'text' ) );
						$item['size']  = $pick( $get( 'size' ), array( 'h2', 'h3' ) );
						$item['align'] = $pick( $get( 'align' ), array( '', 'left', 'center', 'right' ) );
					} elseif ( 'text' === $type ) {
						$item['html']  = wp_kses_post( is_string( $get( 'html' ) ) ? $get( 'html' ) : '' );
						$item['align'] = $pick( $get( 'align' ), array( '', 'left', 'center', 'right' ) );
					} elseif ( 'image' === $type ) {
						$item['id']      = absint( $get( 'id', 0 ) );
						$item['shape']   = $pick( $get( 'shape' ), array( '', 'arch', 'round', 'square' ) );
						$item['frame']   = $get( 'frame' ) ? 1 : 0;
						$item['fx']      = max( -1, min( 100, (int) $get( 'fx', -1 ) ) );
						$item['fy']      = max( -1, min( 100, (int) $get( 'fy', -1 ) ) );
						$item['caption'] = $text( $get( 'caption' ) );
						$item['link']    = esc_url_raw( is_string( $get( 'link' ) ) ? trim( $get( 'link' ) ) : '' );
						$item['zoom']    = $get( 'zoom', 1 ) ? 1 : 0;
					} else {
						$item['id']      = absint( $get( 'id', 0 ) );
						$item['url']     = esc_url_raw( is_string( $get( 'url' ) ) ? trim( $get( 'url' ) ) : '' );
						$item['poster']  = absint( $get( 'poster', 0 ) );
						$item['auto']    = $get( 'auto' ) ? 1 : 0;
						$item['shape']   = $pick( $get( 'shape' ), array( '', 'plain' ) );
						$item['caption'] = $text( $get( 'caption' ) );
					}
					$blocks[] = $item;
				}
				if ( $blocks ) {
					$cols[] = $blocks;
				}
			}
			$get     = function ( $key ) use ( $row ) {
				return isset( $row[ $key ] ) ? $row[ $key ] : '';
			};
			$clean[] = array(
				'w'     => $pick( $get( 'w' ), array( '', '12', '21' ) ),
				'v'     => $pick( $get( 'v' ), array( '', 'top', 'bottom' ) ),
				'space' => $pick( $get( 'space' ), array( '', 'small', 'large' ) ),
				'stack' => $pick( $get( 'stack' ), array( '', 'none', 'small', 'large' ) ),
				'bg'    => $get( 'bg' ) ? 1 : 0,
				'cols'  => $cols,
			);
		}
		return $clean;
	}

	/**
	 * Whether a block has something to show (empty blocks are left out, so the others fill the row).
	 *
	 * @param array $block Block.
	 * @return bool
	 */
	private static function filled( $block ) {
		switch ( isset( $block['t'] ) ? $block['t'] : '' ) {
			case 'title':
				return '' !== trim( (string) $block['text'] );
			case 'text':
				return '' !== trim( wp_strip_all_tags( (string) $block['html'] ) ) || false !== strpos( (string) $block['html'], '<img' );
			case 'image':
				return ! empty( $block['id'] ) && wp_attachment_is_image( (int) $block['id'] );
			case 'video':
				return ! empty( $block['id'] ) || '' !== (string) $block['url'];
		}
		return false;
	}

	/**
	 * The rows as HTML: columns side by side, each with its blocks under each other. A row with one column (left)
	 * fills the width, in the middle.
	 *
	 * @param array $rows Rows.
	 * @return string
	 */
	public static function render_rows( $rows ) {
		$html = '';
		foreach ( $rows as $row ) {
			$row  = self::upgrade_row( $row );
			$cols = array();
			foreach ( $row['cols'] as $col ) {
				$col = array_values( array_filter( is_array( $col ) ? $col : array(), array( __CLASS__, 'filled' ) ) );
				if ( $col ) {
					$cols[] = $col;
				}
			}
			$count = count( $cols );
			if ( ! $count ) {
				continue;
			}
			$row = array_merge(
				array(
					'w'     => '',
					'v'     => '',
					'space' => '',
					'stack' => '',
					'bg'    => 0,
				),
				$row
			);
			$classes = array( 'aimp-blog-row', 'is-cols-' . $count );
			if ( 2 === $count && in_array( $row['w'], array( '12', '21' ), true ) ) {
				$classes[] = 'is-w-' . $row['w'];
			}
			$classes[] = 'is-v-' . ( 1 < $count && in_array( $row['v'], array( 'top', 'bottom' ), true ) ? $row['v'] : 'middle' );
			if ( in_array( $row['space'], array( 'small', 'large' ), true ) ) {
				$classes[] = 'is-space-' . $row['space'];
			}
			if ( in_array( $row['stack'], array( 'none', 'small', 'large' ), true ) ) {
				$classes[] = 'is-stack-' . $row['stack'];
			}
			if ( ! empty( $row['bg'] ) ) {
				$classes[] = 'has-bg';
			}
			$html .= '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			foreach ( $cols as $col ) {
				$html .= '<div class="aimp-blog-col">';
				foreach ( $col as $i => $block ) {
					$gap   = $i && isset( $block['gap'] ) && in_array( $block['gap'], array( 'none', 'small', 'large' ), true ) ? ' is-gap-' . $block['gap'] : '';
					$html .= '<div class="aimp-blog-block is-' . esc_attr( $block['t'] ) . $gap . '">' . self::block( $block, 1 === $count ) . '</div>';
				}
				$html .= '</div>';
			}
			$html .= '</section>';
		}
		return '' !== $html ? '<div class="aimp-blog-rows">' . $html . '</div>' : '';
	}

	/**
	 * One block.
	 *
	 * @param array $block Block.
	 * @param bool  $alone The only block of its row.
	 * @return string
	 */
	private static function block( $block, $alone ) {
		// Standard alignment: in the middle when the block is alone, else to the left.
		$align = isset( $block['align'] ) && '' !== $block['align'] ? $block['align'] : ( $alone ? 'center' : 'left' );
		switch ( $block['t'] ) {
			case 'title':
				$tag = 'h3' === $block['size'] ? 'h3' : 'h2';
				return '<' . $tag . ' class="aimp-blog-title is-' . $tag . ' is-align-' . esc_attr( $align ) . '">' . esc_html( $block['text'] ) . '</' . $tag . '>';
			case 'text':
				return '<div class="aimp-blog-text is-align-' . esc_attr( $align ) . '">' . do_shortcode( wpautop( wp_kses_post( $block['html'] ) ) ) . '</div>';
			case 'image':
				return self::image( $block, $alone );
			case 'video':
				return self::video( $block );
		}
		return '';
	}

	/**
	 * A picture in a shape of the About page (arch, round, rounded corners or as it is), with its focal point.
	 *
	 * @param array $block Block.
	 * @param bool  $alone The only block of its row (a larger picture).
	 * @return string
	 */
	private static function image( $block, $alone ) {
		$id    = (int) $block['id'];
		$shape = '' !== $block['shape'] ? $block['shape'] : 'plain';
		$sizes = $alone ? '(max-width: 1100px) 100vw, 1100px' : '(max-width: 700px) 100vw, 600px';
		$image = wp_get_attachment_image( $id, $alone ? 'full' : 'large', false, array( 'class' => 'aimp-blog-img', 'loading' => 'lazy', 'sizes' => $sizes ) );
		if ( '' === $image ) {
			return '';
		}
		if ( $block['fx'] >= 0 || $block['fy'] >= 0 ) {
			$image = preg_replace( '/^<img /', '<img style="object-position:' . ( $block['fx'] >= 0 ? (int) $block['fx'] : 50 ) . '% ' . ( $block['fy'] >= 0 ? (int) $block['fy'] : 50 ) . '%" ', $image );
		}
		if ( '' !== $block['link'] ) {
			$image = '<a class="aimp-blog-img-link" href="' . esc_url( $block['link'] ) . '">' . $image . '</a>';
		} elseif ( ! empty( $block['zoom'] ) ) {
			$full  = wp_get_attachment_image_url( $id, 'full' );
			$image = '<a class="aimp-blog-img-link" href="' . esc_url( $full ) . '" data-aimp-zoom aria-label="' . esc_attr__( 'Enlarge picture', 'atelier-irisee-master-plugin' ) . '">' . $image . '</a>';
		}
		$html = '<figure class="aimp-blog-image is-' . esc_attr( $shape ) . ( ! empty( $block['frame'] ) ? ' has-frame' : '' ) . '"><span class="aimp-blog-frame">' . $image . '</span>';
		if ( '' !== $block['caption'] ) {
			$html .= '<figcaption>' . esc_html( $block['caption'] ) . '</figcaption>';
		}
		return $html . '</figure>';
	}

	/**
	 * A YouTube or Vimeo link as an address that plays (privacy-friendly) and the video's own picture.
	 *
	 * @param string $url Link.
	 * @return array|null [ embed address, picture ('' = none) ]
	 */
	public static function embed( $url ) {
		if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $found ) ) {
			return array( 'https://www.youtube-nocookie.com/embed/' . $found[1] . '?autoplay=1&rel=0&playsinline=1', 'https://i.ytimg.com/vi/' . $found[1] . '/hqdefault.jpg' );
		}
		if ( preg_match( '~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)~', $url, $found ) ) {
			return array( 'https://player.vimeo.com/video/' . $found[1] . '?autoplay=1&dnt=1', '' );
		}
		return null;
	}

	/**
	 * A video: from the media library (with controls, or playing silently on its own), or a YouTube or Vimeo link
	 * shown as a picture with a play button; the video itself only loads when it is played.
	 *
	 * @param array $block Block.
	 * @return string
	 */
	private static function video( $block ) {
		$poster = $block['poster'] ? (string) wp_get_attachment_image_url( (int) $block['poster'], 'large' ) : '';
		$play   = '<span class="aimp-blog-play-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 5.5v13l11-6.5z"/></svg></span>';
		$inner  = '';
		$file   = $block['id'] ? (string) wp_get_attachment_url( (int) $block['id'] ) : '';
		if ( '' === $file && '' !== $block['url'] && ! self::embed( $block['url'] ) && preg_match( '/\.(mp4|webm|mov|m4v)(\?|$)/i', $block['url'] ) ) {
			$file = $block['url'];
		}
		if ( '' !== $file ) {
			$attrs = ! empty( $block['auto'] ) ? ' autoplay muted loop playsinline' : ' controls playsinline';
			$inner = '<video class="aimp-blog-video-file" src="' . esc_url( $file ) . '"' . $attrs . ' preload="metadata"' . ( '' !== $poster ? ' poster="' . esc_url( $poster ) . '"' : '' ) . '></video>';
		} elseif ( '' !== $block['url'] ) {
			$embed = self::embed( $block['url'] );
			if ( ! $embed ) {
				return '<p class="aimp-blog-video-link"><a href="' . esc_url( $block['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Watch the video', 'atelier-irisee-master-plugin' ) . '</a></p>';
			}
			$picture = '' !== $poster ? $poster : $embed[1];
			$inner   = '<button type="button" class="aimp-blog-play" data-embed="' . esc_url( $embed[0] ) . '" aria-label="' . esc_attr__( 'Play video', 'atelier-irisee-master-plugin' ) . '">' .
				( '' !== $picture ? '<img src="' . esc_url( $picture ) . '" alt="' . esc_attr__( 'Video', 'atelier-irisee-master-plugin' ) . '" loading="lazy">' : '' ) . $play . '</button>';
		}
		if ( '' === $inner ) {
			return '';
		}
		$html = '<figure class="aimp-blog-video is-' . ( 'plain' === $block['shape'] ? 'plain' : 'rounded' ) . '"><div class="aimp-blog-video-frame">' . $inner . '</div>';
		if ( '' !== $block['caption'] ) {
			$html .= '<figcaption>' . esc_html( $block['caption'] ) . '</figcaption>';
		}
		return $html . '</figure>';
	}

	/**
	 * The copy of a post's rows in its content, for WordPress itself: search, feeds, excerpts and SEO plugins.
	 *
	 * @param array $rows Rows.
	 * @return string
	 */
	public static function content_copy( $rows ) {
		return "<!-- aimp-blog-copy -->\n" . self::render_rows( $rows );
	}

	/* ------------------------------------------------------------------
	 * The post page
	 * ------------------------------------------------------------------ */

	/**
	 * Divi: the posts without its sidebar, and without its own title and picture when the page header shows them
	 * (the title is in the page header).
	 *
	 * @param mixed  $value     Null (or another filter's value).
	 * @param int    $object_id Post.
	 * @param string $meta_key  Meta key.
	 * @return mixed
	 */
	public static function divi_layout( $value, $object_id, $meta_key ) {
		if ( '_et_pb_page_layout' !== $meta_key || is_admin() || 'post' !== get_post_type( $object_id ) || self::design( 'bl_sidebar', false, 'blog-post' ) ) {
			return $value;
		}
		return array( 'et_no_sidebar' );
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( is_singular( 'post' ) ) {
			$classes[] = 'aimp-blog-single';
		}
		return $classes;
	}

	/**
	 * A post's page: under the page header its category, date and reading time, the rows (or the text of a post
	 * written before), then like, share and views, the previous and next post and more of its category.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function content( $content ) {
		if ( self::$busy || is_admin() || is_feed() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		self::$busy = true;
		$id         = (int) get_the_ID();
		$rows       = self::rows( $id );
		$body       = $rows ? self::render_rows( $rows ) : '<div class="aimp-blog-classic">' . $content . '</div>';
		self::enqueue();
		$html       = self::look_css( $id ) . '<div class="aimp-blog-post" data-aimp-blog-post="' . $id . '">' . '<h1 class="aimp-blog-post-title">' . esc_html( wp_strip_all_tags( get_the_title( $id ) ) ) . '</h1>' . self::meta( $id ) . $body . self::after( $id ) . '</div>';
		self::$busy = false;
		return $html;
	}

	/**
	 * Minutes to read a post (200 words a minute).
	 *
	 * @param int $id Post.
	 * @return int
	 */
	private static function minutes( $id ) {
		$words = preg_split( '/\s+/u', trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) ), -1, PREG_SPLIT_NO_EMPTY );
		return max( 1, (int) round( count( $words ) / 200 ) );
	}

	/**
	 * Under the page header: the category (to the overview with that category), the date and the reading time.
	 *
	 * @param int $id Post.
	 * @return string
	 */
	private static function meta( $id ) {
		$parts = array();
		$term  = self::category( $id );
		$back  = self::overview_url();
		if ( $term ) {
			$parts[] = self::label( $term, '' !== $back ? add_query_arg( 'aimp_bcat', $term->slug, $back ) : '' );
		}
		$parts[] = '<time datetime="' . esc_attr( get_the_date( 'c', $id ) ) . '">' . esc_html( get_the_date( 'j F Y', $id ) ) . '</time>';
		if ( ! self::design( 'bl_notime', false, 'blog-post' ) ) {
			$minutes = self::minutes( $id );
			/* translators: %s: number of minutes */
			$parts[] = '<span>' . esc_html( sprintf( _n( '%s minute to read', '%s minutes to read', $minutes, 'atelier-irisee-master-plugin' ), number_format_i18n( $minutes ) ) ) . '</span>';
		}
		return '<div class="aimp-blog-meta">' . implode( '<span class="aimp-blog-dot" aria-hidden="true">·</span>', $parts ) . '</div>';
	}

	/**
	 * Under the post: like, share and views; the previous and next post; more of its category; back to all posts.
	 *
	 * @param int $id Post.
	 * @return string
	 */
	private static function after( $id ) {
		$html = '<div class="aimp-blog-post-actions">' . self::actions( $id, array( 'likes' => ! self::design( 'bl_nolikes' ), 'views' => ! self::design( 'bl_noviews' ) ), true ) . '</div>';
		if ( ! self::design( 'bl_nonav', false, 'blog-post' ) ) {
			$prev = get_previous_post();
			$next = get_next_post();
			if ( $prev || $next ) {
				$html .= '<nav class="aimp-blog-nav" aria-label="' . esc_attr__( 'More posts', 'atelier-irisee-master-plugin' ) . '">';
				if ( $prev ) {
					$html .= '<a class="aimp-blog-nav-link is-prev" href="' . esc_url( get_permalink( $prev ) ) . '"><span>← ' . esc_html__( 'Previous post', 'atelier-irisee-master-plugin' ) . '</span><strong>' . esc_html( get_the_title( $prev ) ) . '</strong></a>';
				}
				if ( $next ) {
					$html .= '<a class="aimp-blog-nav-link is-next" href="' . esc_url( get_permalink( $next ) ) . '"><span>' . esc_html__( 'Next post', 'atelier-irisee-master-plugin' ) . ' →</span><strong>' . esc_html( get_the_title( $next ) ) . '</strong></a>';
				}
				$html .= '</nav>';
			}
		}
		$back = self::overview_url();
		if ( ! self::design( 'bl_norelated', false, 'blog-post' ) ) {
			$term  = self::category( $id );
			$args  = array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 3,
				'post__not_in'        => array( $id ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			);
			if ( $term ) {
				$args['cat'] = $term->term_id;
			}
			$posts = get_posts( $args );
			if ( $posts ) {
				$opts = self::options( array() );
				$opts['layout'] = 'random';
				/* translators: %s: category */
				$title = $term ? sprintf( __( 'More from %s', 'atelier-irisee-master-plugin' ), $term->name ) : __( 'More posts', 'atelier-irisee-master-plugin' );
				$html .= '<section class="aimp-home-block aimp-blog-related"><h2 class="aimp-block-title"><span>' . esc_html( $title ) . '</span></h2>' .
					'<ul class="aimp-blog-grid layout-random shape-' . esc_attr( $opts['shape'] ) . '" style="--bl-cols:' . count( $posts ) . ';--bl-cols-t:' . min( 2, count( $posts ) ) . ';max-width:' . ( count( $posts ) * 420 ) . 'px;margin-inline:auto !important">';
				foreach ( $posts as $post ) {
					$html .= self::card( $post, $opts );
				}
				$html .= '</ul>';
				if ( '' !== $back ) {
					$html .= '<p class="aimp-blog-back"><a class="aimp-block-button" href="' . esc_url( $back ) . '">' . esc_html__( 'All blog posts', 'atelier-irisee-master-plugin' ) . '</a></p>';
				}
				return $html . '</section>';
			}
		}
		if ( '' !== $back ) {
			$html .= '<p class="aimp-blog-back aimp-home-block"><a class="aimp-block-button" href="' . esc_url( $back ) . '">' . esc_html__( 'All blog posts', 'atelier-irisee-master-plugin' ) . '</a></p>';
		}
		return $html;
	}

	/* ------------------------------------------------------------------
	 * Cards: like, share, views
	 * ------------------------------------------------------------------ */

	/**
	 * Like (heart and count), share and views of a post.
	 *
	 * @param int   $id    Post.
	 * @param array $show  likes, views: shown.
	 * @param bool  $words The words next to the icons (on the post page).
	 * @return string
	 */
	public static function actions( $id, $show, $words = false ) {
		$likes = max( 0, (int) get_post_meta( $id, self::LIKES, true ) );
		$views = max( 0, (int) get_post_meta( $id, self::VIEWS, true ) );
		$html  = '';
		if ( ! empty( $show['likes'] ) ) {
			/* translators: %s: number of likes */
			$label = sprintf( _n( '%s like', '%s likes', $likes, 'atelier-irisee-master-plugin' ), number_format_i18n( $likes ) );
			$html .= '<button type="button" class="aimp-blog-action aimp-blog-like" data-aimp-blog-like="' . (int) $id . '" aria-pressed="false" title="' . esc_attr__( 'Like', 'atelier-irisee-master-plugin' ) . '">' .
				'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20.3s-7.6-4.6-9.2-9.4C1.7 7.5 3.8 4.2 7.2 4.2c2 0 3.5 1.1 4.8 2.8 1.3-1.7 2.8-2.8 4.8-2.8 3.4 0 5.5 3.3 4.4 6.7-1.6 4.8-9.2 9.4-9.2 9.4z"/></svg>' .
				'<span class="aimp-blog-count" data-count="' . $likes . '" aria-hidden="true">' . esc_html( number_format_i18n( $likes ) ) . '</span><span class="screen-reader-text" data-label>' . esc_html( $label ) . '</span></button>';
		}
		$url   = get_permalink( $id );
		$thumb = get_post_thumbnail_id( $id );
		$html .= '<button type="button" class="aimp-blog-action aimp-blog-share" data-aimp-share="' . esc_url( $url ) . '" data-title="' . esc_attr( wp_strip_all_tags( get_the_title( $id ) ) ) . '"' .
			( $thumb ? ' data-image="' . esc_url( (string) wp_get_attachment_image_url( $thumb, 'large' ) ) . '"' : '' ) . ' aria-haspopup="true" title="' . esc_attr__( 'Share', 'atelier-irisee-master-plugin' ) . '">' .
			'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21 3 10.2 13.8M21 3l-6.8 18-4-7.2L3 9.8z"/></svg>' .
			( $words ? '<span>' . esc_html__( 'Share', 'atelier-irisee-master-plugin' ) . '</span>' : '<span class="screen-reader-text">' . esc_html__( 'Share', 'atelier-irisee-master-plugin' ) . '</span>' ) . '</button>';
		if ( ! empty( $show['views'] ) ) {
			/* translators: %s: number of views */
			$label = sprintf( _n( '%s view', '%s views', $views, 'atelier-irisee-master-plugin' ), number_format_i18n( $views ) );
			$html .= '<span class="aimp-blog-action aimp-blog-views" title="' . esc_attr( $label ) . '">' .
				'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M1.8 12S5.5 5 12 5s10.2 7 10.2 7-3.7 7-10.2 7S1.8 12 1.8 12z"/><circle cx="12" cy="12" r="3.2"/></svg>' .
				'<span class="aimp-blog-count" aria-hidden="true">' . esc_html( number_format_i18n( $views ) ) . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span></span>';
		}
		return '<div class="aimp-blog-actions">' . $html . '</div>';
	}

	/**
	 * A post's own picture: its featured image, else the first picture in its rows.
	 *
	 * @param int $id Post.
	 * @return int
	 */
	public static function picture( $id ) {
		$thumb = (int) get_post_thumbnail_id( $id );
		if ( $thumb ) {
			return $thumb;
		}
		foreach ( self::rows( $id ) as $row ) {
			foreach ( self::row_blocks( $row ) as $block ) {
				if ( isset( $block['t'] ) && 'image' === $block['t'] && ! empty( $block['id'] ) ) {
					return (int) $block['id'];
				}
			}
		}
		return 0;
	}

	/**
	 * A short text for the card: the excerpt, else the start of the post.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function excerpt( $post ) {
		if ( ! has_excerpt( $post ) ) {
			// A post made with the blog layout: its titles and texts, a space between the blocks (not the captions).
			$parts = array();
			foreach ( self::rows( $post->ID ) as $row ) {
				foreach ( self::row_blocks( $row ) as $block ) {
					$type = isset( $block['t'] ) ? $block['t'] : '';
					if ( 'title' === $type && ! empty( $block['text'] ) ) {
						$parts[] = (string) $block['text'];
					} elseif ( 'text' === $type && ! empty( $block['html'] ) ) {
						$parts[] = preg_replace( '~</?(?:p|h[1-6]|div|li|ul|ol|blockquote|br)[^>]*>~i', ' $0 ', (string) $block['html'] );
					}
				}
			}
			if ( $parts ) {
				return wp_trim_words( wp_strip_all_tags( implode( ' ', $parts ) ), 24, '…' );
			}
		}
		$text = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		// A space around every block tag: the blocks of a post (a title, then a text) do not run into each other.
		$text = preg_replace( '~</?(?:p|h[1-6]|div|section|li|ul|ol|figure|figcaption|blockquote|br|hr|img)[^>]*>~i', ' $0 ', $text );
		return wp_trim_words( wp_strip_all_tags( $text ), 24, '…' );
	}

	/**
	 * A small number from the post id that is the same on every visit (the playful places of the cards).
	 *
	 * @param int $id   Post.
	 * @param int $salt Which number.
	 * @return float 0 … 1.
	 */
	private static function chance( $id, $salt ) {
		return ( crc32( 'aimp-blog-' . $id . '-' . $salt ) % 1000 ) / 999;
	}

	/**
	 * A polaroid card.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $opts options().
	 * @return string
	 */
	public static function card( $post, $opts ) {
		$id      = (int) $post->ID;
		$url     = get_permalink( $post );
		$title   = get_the_title( $post );
		$play    = $opts['play'];
		$tilt    = $opts['tilt'] ? ( self::chance( $id, 2 ) * 2 - 1 ) : 0;
		// Random: down by up to 70 px and turned up to 2°; scattered: up to 110 px, a little to the side, up to 4°.
		if ( 'scatter' === $opts['layout'] ) {
			$style = '--bl-drop:' . round( self::chance( $id, 1 ) * 110 * $play ) . 'px;--bl-shift:' . round( ( self::chance( $id, 3 ) * 2 - 1 ) * 14 * $play ) . 'px;--bl-tilt:' . round( $tilt * 4 * $play, 2 ) . 'deg';
		} elseif ( 'wave' === $opts['layout'] ) {
			$style = '--bl-tilt:' . round( $tilt * 1.5 * $play, 2 ) . 'deg';
		} else {
			$style = '--bl-drop:' . round( self::chance( $id, 1 ) * 70 * $play ) . 'px;--bl-tilt:' . round( $tilt * 2 * $play, 2 ) . 'deg';
		}
		// Mosaic (standard): each picture its own height, the same on every visit.
		if ( 'mosaic' === $opts['shape'] ) {
			$ratios = array( '1/1', '4/5', '3/4', '2/3', '4/3', '5/4' );
			$style .= ';--bl-ratio:' . $ratios[ (int) floor( self::chance( $id, 4 ) * 5.999 ) ];
		}
		$picture = self::picture( $id );
		$image   = $picture ? wp_get_attachment_image( $picture, 'medium_large', false, array( 'class' => 'aimp-blog-card-img', 'loading' => 'lazy', 'alt' => '', 'sizes' => '(max-width: 700px) 85vw, 420px' ) ) : '';
		if ( '' === $image ) {
			$first = function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( $title ), 0, 1 ) : substr( wp_strip_all_tags( $title ), 0, 1 );
			$image = '<span class="aimp-blog-card-empty" aria-hidden="true">' . esc_html( $first ) . '</span>';
		}
		$term = $opts['label'] ? self::category( $id ) : null;
		// The pin that holds the card on the page: its style (a mix: one per post), in the colour of the category.
		$pin   = isset( $opts['pin'] ) ? $opts['pin'] : 'none';
		$pin   = 'mix' === $pin ? array( 'pin', 'tape', 'tack', 'pin', 'heart' )[ (int) floor( self::chance( $id, 5 ) * 4.999 ) ] : $pin;
		$card  = '';
		$cardc = 'aimp-blog-card';
		if ( 'none' !== $pin ) {
			$cat    = self::category( $id );
			$color  = '' !== $opts['pin_rgb'] ? $opts['pin_rgb'] : ( $cat ? self::color( $cat ) : '' );
			$cardc .= ' has-pin pin-' . $pin;
			$card   = ' style="' . esc_attr( ( '' !== $color ? '--bl-pin:' . $color . ';' : '' ) . '--bl-pin-turn:' . round( ( self::chance( $id, 6 ) * 2 - 1 ) * 8, 1 ) . 'deg' ) . '"';
		}
		$html = '<li class="aimp-blog-item" style="' . esc_attr( $style ) . '"><article class="' . $cardc . '"' . $card . '>' .
			'<div class="aimp-blog-card-media"><a class="aimp-blog-card-photo" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $image . '</a>' . ( $term ? self::label( $term ) : '' ) . '</div>' .
			'<div class="aimp-blog-card-body"><h3 class="aimp-blog-card-title"><a href="' . esc_url( $url ) . '">' . esc_html( wp_strip_all_tags( $title ) ) . '</a></h3>';
		if ( $opts['date'] ) {
			$html .= '<time class="aimp-blog-card-date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( 'j M Y', $post ) ) . '</time>';
		}
		if ( $opts['excerpt'] ) {
			$text = self::excerpt( $post );
			if ( '' !== $text ) {
				$html .= '<p class="aimp-blog-card-text">' . esc_html( $text ) . '</p>';
			}
		}
		return $html . self::actions( $id, $opts ) . '</div></article></li>';
	}

	/* ------------------------------------------------------------------
	 * The newest post on the home page
	 * ------------------------------------------------------------------ */

	/**
	 * [atelier_irisee_latest_post]: the newest post in one of three looks: a pinned polaroid with a note next to it
	 * (standard), a wide picture with a cream card in a gold double frame (style="luxe"), or a page of a sewing diary
	 * with the picture taped on and a date stamp (style="journal"). Nothing without a published post.
	 *
	 * Attributes (all optional, over Design studio → Newest blog post): style, category (slug), title, button.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function latest( $atts ) {
		$atts  = shortcode_atts(
			array(
				'style'    => '',
				'category' => '',
				'title'    => '',
				'button'   => '',
			),
			$atts,
			self::LATEST
		);
		$opt   = function ( $key, $default = null ) {
			return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( 'home-latest', $key, $default ) : $default;
		};
		$style = in_array( $atts['style'], array( 'polaroid', 'luxe', 'journal' ), true ) ? $atts['style'] : (string) $opt( 'lp_style', '' );
		$style = in_array( $style, array( 'polaroid', 'luxe', 'journal' ), true ) ? $style : 'polaroid';
		$args  = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$cat   = '' !== $atts['category'] ? sanitize_title( $atts['category'] ) : sanitize_title( (string) $opt( 'lp_cat', '' ) );
		if ( '' !== $cat ) {
			$args['category_name'] = $cat;
		}
		$posts = get_posts( $args );
		if ( ! $posts ) {
			return '';
		}
		return self::latest_html( $posts[0], $style, $atts, $opt );
	}

	/**
	 * The block's HTML (also used by the test harness).
	 *
	 * @param WP_Post  $post  Post.
	 * @param string   $style polaroid, luxe or journal.
	 * @param array    $atts  Attributes.
	 * @param callable $opt   Design studio setting.
	 * @return string
	 */
	public static function latest_html( $post, $style, $atts, $opt ) {
		self::enqueue();
		$id      = (int) $post->ID;
		$url     = get_permalink( $post );
		$title   = wp_strip_all_tags( get_the_title( $post ) );
		$kicker  = '' !== $atts['title'] ? $atts['title'] : AIMP_Blocks::text( 'lp_kicker' );
		$button  = '' !== $atts['button'] ? $atts['button'] : AIMP_Blocks::text( 'lp_button' );
		$term    = $opt( 'lp_nolabel' ) ? null : self::category( $id );
		$picture = self::picture( $id );
		$image   = $picture ? wp_get_attachment_image( $picture, 'luxe' === $style ? 'full' : 'large', false, array( 'class' => 'aimp-latest-img', 'loading' => 'lazy', 'alt' => '', 'sizes' => 'luxe' === $style ? '100vw' : '(max-width: 800px) 90vw, 600px' ) ) : '';
		if ( '' === $image ) {
			$first = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
			$image = '<span class="aimp-latest-empty" aria-hidden="true">' . esc_html( $first ) . '</span>';
		}

		// Polaroid: pinned on the page like the cards of the overview, in the colour of the category.
		$frame  = 'aimp-latest-frame';
		$fstyle = '';
		if ( 'polaroid' === $style ) {
			$pin = (string) $opt( 'lp_pin', '' );
			$pin = in_array( $pin, array( 'tack', 'tape', 'clip', 'heart', 'none' ), true ) ? $pin : 'pin';
			if ( 'none' !== $pin ) {
				$cat     = self::category( $id );
				$color   = $cat ? self::color( $cat ) : '';
				$frame  .= ' aimp-blog-card has-pin pin-' . $pin;
				$fstyle  = ' style="' . esc_attr( ( '' !== $color ? '--bl-pin:' . $color . ';' : '' ) . '--bl-pin-turn:' . round( ( self::chance( $id, 6 ) * 2 - 1 ) * 8, 1 ) . 'deg' ) . '"';
			}
		}

		// Luxe: the cream card as a rectangle or an arch. The date stamp (journal and luxe) in its own colour.
		$arch    = 'luxe' === $style && 'arch' === (string) $opt( 'lp_shape', '' );
		$stamp   = in_array( $style, array( 'journal', 'luxe' ), true ) && ! $opt( 'lp_nodate' );
		$ink     = class_exists( 'AIMP_Design' ) ? AIMP_Design::clean_color( (string) $opt( 'lp_stamp_color', '' ) ) : '';
		// Luxe: where the stamp sits on the card's edge (-100 … 100): the first 70 over the curve of the arch (or along
		// the top of the rectangle), the rest down the side.
		$place   = 'luxe' === $style && $stamp ? max( -100, min( 100, (int) $opt( 'lp_stamp_pos', 0 ) ) ) : 0;
		$vars    = '' !== $ink ? '--lp-stamp:' . $ink . ';' : '';
		if ( 0 !== $place ) {
			$along = max( -1, min( 1, $place / 70 ) );
			$vars .= '--lp-sa:' . round( $along * 90, 2 ) . 'deg;--lp-su:' . round( $along, 4 ) . ';--lp-sd:' . round( max( 0, abs( $place ) - 70 ) / 30, 4 ) . ';';
		}
		$classes = 'aimp-home-block aimp-home-latest style-' . $style . ( $opt( 'lp_right' ) ? ' is-photo-right' : '' ) . ( $arch ? ' is-arch' : '' ) . ( 0 !== $place ? ' has-stamp-place' : '' );
		$html    = '<section class="' . esc_attr( $classes ) . '"' . ( '' !== $vars ? ' style="' . esc_attr( $vars ) . '"' : '' ) . ' aria-label="' . esc_attr( '' !== $kicker ? $kicker : $title ) . '"><div class="aimp-latest-inner">';
		$html   .= '<a class="aimp-latest-media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true"><span class="' . esc_attr( $frame ) . '"' . $fstyle . '>' . $image;
		if ( 'polaroid' === $style ) {
			$html .= '<span class="aimp-latest-caption">' . esc_html( $title ) . '</span>';
		}
		$html .= '</span></a><div class="aimp-latest-body">';
		if ( $stamp ) {
			// A round date stamp, as if stamped on the diary page or the cream card.
			$time = '<time class="aimp-latest-stamp" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '" aria-label="' . esc_attr( get_the_date( 'j F Y', $post ) ) . '"><b aria-hidden="true">' . esc_html( get_the_date( 'j', $post ) ) . '</b><span aria-hidden="true">' . esc_html( get_the_date( 'M', $post ) ) . '</span><small aria-hidden="true">' . esc_html( get_the_date( 'Y', $post ) ) . '</small></time>';
			// Placed on the card's edge: a layer as big as the card, the stamp on its line.
			$html .= 0 !== $place ? '<span class="aimp-latest-stampline">' . $time . '</span>' : $time;
		}
		if ( '' !== $kicker ) {
			$html .= '<p class="aimp-latest-kicker">' . esc_html( $kicker ) . '</p>';
		}
		if ( $term ) {
			$html .= 'luxe' === $style ? '<p class="aimp-latest-cat">' . esc_html( $term->name ) . '</p>' : self::label( $term );
		}
		$html .= '<h2 class="aimp-latest-title"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h2>';
		$meta  = array();
		// Luxe: the date is in the stamp.
		if ( ! $opt( 'lp_nodate' ) && 'luxe' !== $style ) {
			$meta[] = '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( 'j F Y', $post ) ) . '</time>';
		}
		$minutes = self::minutes( $id );
		/* translators: %d: minutes */
		$meta[] = esc_html( sprintf( _n( '%d minute read', '%d minutes read', $minutes, 'atelier-irisee-master-plugin' ), $minutes ) );
		$html  .= '<p class="aimp-latest-meta">' . implode( ' <span aria-hidden="true">·</span> ', $meta ) . '</p>';
		if ( ! $opt( 'lp_noexcerpt' ) ) {
			$text = self::excerpt( $post );
			if ( '' !== $text ) {
				$html .= '<p class="aimp-latest-text">' . esc_html( $text ) . '</p>';
			}
		}
		$html .= '<div class="aimp-latest-actions"><a class="aimp-block-button aimp-latest-button" href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a>';
		$all   = self::overview_url();
		if ( '' !== $all && ! $opt( 'lp_noall' ) ) {
			$html .= '<a class="aimp-latest-all" href="' . esc_url( $all ) . '">' . esc_html( AIMP_Blocks::text( 'lp_all' ) ) . '</a>';
		}
		return $html . '</div></div></div></section>';
	}

	/* ------------------------------------------------------------------
	 * The overview
	 * ------------------------------------------------------------------ */

	/**
	 * The overview's settings: the shortcode's attributes over the Design studio's.
	 *
	 * @param array $atts Attributes.
	 * @return array
	 */
	private static function options( $atts ) {
		$att  = function ( $key ) use ( $atts ) {
			return isset( $atts[ $key ] ) && is_scalar( $atts[ $key ] ) ? trim( (string) $atts[ $key ] ) : '';
		};
		$in   = function ( $value, $list ) {
			return in_array( $value, $list, true ) ? $value : '';
		};
		$layout = $in( $att( 'layout' ), array( 'random', 'wave', 'scatter' ) );
		$layout = '' !== $layout ? $layout : $in( (string) self::design( 'bl_layout', '' ), array( 'wave', 'scatter' ) );
		$sort   = $in( $att( 'sort' ), self::SORTS );
		$sort   = '' !== $sort ? $sort : $in( (string) self::design( 'bl_sort', '' ), self::SORTS );
		$count  = (int) $att( 'count' );
		$cols   = (int) $att( 'columns' );
		$play   = (int) self::design( 'bl_play', -1 );
		$gap    = (int) self::design( 'bl_gap', -99 );
		$lock   = array_values( array_filter( array_map( 'sanitize_title', explode( ',', $att( 'category' ) ) ) ) );
		return array(
			'layout'  => '' !== $layout ? $layout : 'random',
			'sort'    => '' !== $sort ? $sort : 'newest',
			'per'     => $count > 0 ? min( 48, $count ) : ( (int) self::design( 'bl_count', 0 ) ?: 12 ),
			'cols'    => $cols > 0 ? min( 6, $cols ) : ( (int) self::design( 'bl_cols', 0 ) ?: 3 ),
			'cols_t'  => (int) self::design( 'bl_cols_tablet', 0 ) ?: 2,
			'phone'   => $in( (string) self::design( 'bl_cols_phone', '' ), array( '2', 'swipe' ) ),
			'play'    => $play >= 0 ? $play / 100 : 1,
			'tilt'    => (bool) self::design( 'bl_tilt' ),
			'gap'     => $gap >= 0 ? $gap : -1,
			'shape'   => $in( (string) self::design( 'bl_shape', '' ), array( 'square', 'portrait', 'original' ) ) ?: 'mosaic',
			'pin'     => $in( (string) self::design( 'bl_pin', '' ), array( 'tack', 'tape', 'clip', 'heart', 'mix', 'none' ) ) ?: 'pin',
			'pin_rgb' => (string) self::design( 'bl_pin_color', '' ),
			'pages'   => 'numbers' === self::design( 'bl_pages', '' ) ? 'numbers' : 'more',
			'filters' => 'no' !== $att( 'filters' ) && ! self::design( 'bl_nofilters' ),
			'lock'    => $lock,
			'excerpt' => ! self::design( 'bl_noexcerpt' ),
			'date'    => ! self::design( 'bl_nodate' ),
			'label'   => ! self::design( 'bl_nolabel' ),
			'likes'   => ! self::design( 'bl_nolikes' ),
			'views'   => ! self::design( 'bl_noviews' ),
		);
	}

	/**
	 * What the visitor chose: categories, search, sort and page (from the address, or the request).
	 *
	 * @param array $source $_GET or $_POST.
	 * @param array $opts   options().
	 * @return array
	 */
	private static function state( $source, $opts ) {
		$value = function ( $key ) use ( $source ) {
			return isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $source[ $key ] ) ) : '';
		};
		$cats = array_values( array_unique( array_filter( array_map( 'sanitize_title', explode( ',', $value( 'aimp_bcat' ) ) ) ) ) );
		if ( $opts['lock'] ) {
			$cats = array_values( array_intersect( $cats, $opts['lock'] ) );
		}
		$sort = $value( 'aimp_bsort' );
		return array(
			'cats' => array_slice( $cats, 0, 20 ),
			'q'    => function_exists( 'mb_substr' ) ? mb_substr( $value( 'aimp_bq' ), 0, 80 ) : substr( $value( 'aimp_bq' ), 0, 80 ),
			'sort' => in_array( $sort, self::SORTS, true ) ? $sort : $opts['sort'],
			'page' => max( 1, min( 500, absint( $value( 'aimp_bpage' ) ) ) ),
		);
	}

	/**
	 * The posts.
	 *
	 * @param array $state state().
	 * @param array $opts  options().
	 * @param bool  $upto  Everything up to the page ("Load more" after a reload), not that page alone.
	 * @return WP_Query
	 */
	private static function query( $state, $opts, $upto = false ) {
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $upto ? $opts['per'] * $state['page'] : $opts['per'],
			'paged'               => $upto ? 1 : $state['page'],
			'ignore_sticky_posts' => true,
		);
		$cats = $state['cats'] ? $state['cats'] : $opts['lock'];
		if ( $cats ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => $cats,
				),
			);
		}
		if ( '' !== $state['q'] ) {
			$args['s'] = $state['q'];
		}
		if ( 'likes' === $state['sort'] || 'views' === $state['sort'] ) {
			// Ordered in order_clauses(): posts without a count yet count as 0.
			$args['aimp_blog_order'] = 'likes' === $state['sort'] ? self::LIKES : self::VIEWS;
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'oldest' === $state['sort'] ? 'ASC' : 'DESC';
		}
		return new WP_Query( $args );
	}

	/**
	 * Most liked / most viewed: the count joined on its own key (a meta query with "or not exists" does not order
	 * reliably), the newest first among equal counts.
	 *
	 * @param array    $clauses Query clauses.
	 * @param WP_Query $query   Query.
	 * @return array
	 */
	public static function order_clauses( $clauses, $query ) {
		$key = $query->get( 'aimp_blog_order' );
		if ( ! in_array( $key, array( self::LIKES, self::VIEWS ), true ) ) {
			return $clauses;
		}
		global $wpdb;
		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS aimp_bo ON ( aimp_bo.post_id = {$wpdb->posts}.ID AND aimp_bo.meta_key = %s )", $key );
		$clauses['orderby'] = "CAST( COALESCE( aimp_bo.meta_value, '0' ) AS UNSIGNED ) DESC, {$wpdb->posts}.post_date DESC";
		return $clauses;
	}

	/**
	 * The categories in the filter, with their colour and number of posts.
	 *
	 * @param array $opts options().
	 * @return array [ [ slug, name, color, ink, count ] ]
	 */
	private static function facets( $opts ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		$list  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $opts['lock'] && ! in_array( $term->slug, $opts['lock'], true ) ) {
				continue;
			}
			$color  = self::color( $term );
			$list[] = array(
				'slug'  => $term->slug,
				'name'  => $term->name,
				'color' => $color,
				'ink'   => self::ink( $color ),
				'count' => (int) $term->count,
			);
		}
		return $list;
	}

	/**
	 * The address arguments of a state (for links and the address bar).
	 *
	 * @param array $state state().
	 * @param array $opts  options().
	 * @return array
	 */
	private static function args( $state, $opts ) {
		return array_filter(
			array(
				'aimp_bcat'  => implode( ',', $state['cats'] ),
				'aimp_bq'    => $state['q'],
				'aimp_bsort' => $state['sort'] !== $opts['sort'] ? $state['sort'] : '',
				'aimp_bpage' => $state['page'] > 1 ? $state['page'] : '',
			),
			'strlen'
		);
	}

	/**
	 * Above the cards: the number of posts and the chosen filters as chips (× removes one).
	 *
	 * @param array $state  state().
	 * @param array $facets facets().
	 * @param int   $total  Number of posts found.
	 * @return string
	 */
	private static function summary( $state, $facets, $total ) {
		$chips = array();
		$chip  = function ( $label, $kind, $value ) use ( &$chips ) {
			/* translators: %s: a filter */
			$chips[] = '<button type="button" class="aimp-shop-chip" data-action="remove-filter" data-kind="' . esc_attr( $kind ) . '" data-value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( sprintf( __( 'Remove filter: %s', 'atelier-irisee-master-plugin' ), $label ) ) . '"><span>' . esc_html( $label ) . '</span><span aria-hidden="true">×</span></button>';
		};
		foreach ( $state['cats'] as $slug ) {
			$name = $slug;
			foreach ( $facets as $facet ) {
				if ( $facet['slug'] === $slug ) {
					$name = $facet['name'];
				}
			}
			$chip( $name, 'category', $slug );
		}
		if ( '' !== $state['q'] ) {
			$chip( '“' . $state['q'] . '”', 'search', '' );
		}
		/* translators: %s: number of posts */
		$html = '<p class="aimp-shop-results">' . esc_html( sprintf( _n( '%s post', '%s posts', $total, 'atelier-irisee-master-plugin' ), number_format_i18n( $total ) ) ) . '</p>';
		if ( $chips ) {
			$html .= '<div class="aimp-shop-chips">' . implode( '', $chips ) . ( count( $chips ) > 1 ? '<button type="button" class="aimp-shop-chip-clear" data-action="clear-filters">' . esc_html__( 'Clear all', 'atelier-irisee-master-plugin' ) . '</button>' : '' ) . '</div>';
		}
		return $html;
	}

	/**
	 * Under the cards: "Load more posts" or the page numbers.
	 *
	 * @param array $state state().
	 * @param array $opts  options().
	 * @param int   $pages Number of pages.
	 * @return string
	 */
	private static function more( $state, $opts, $pages ) {
		if ( $pages <= 1 ) {
			return '';
		}
		if ( 'more' === $opts['pages'] ) {
			return $state['page'] < $pages ? '<button type="button" class="aimp-block-button" data-action="more">' . esc_html__( 'Load more posts', 'atelier-irisee-master-plugin' ) . '</button>' : '';
		}
		$html = '<nav class="aimp-blog-pages" aria-label="' . esc_attr__( 'Pages', 'atelier-irisee-master-plugin' ) . '">';
		for ( $page = 1; $page <= $pages; $page++ ) {
			if ( $page === $state['page'] ) {
				$html .= '<span aria-current="page">' . $page . '</span>';
				continue;
			}
			$args  = self::args( array_merge( $state, array( 'page' => $page ) ), $opts );
			$html .= '<a href="' . esc_url( '?' . http_build_query( $args ) ) . '" data-page="' . $page . '">' . $page . '</a>';
		}
		return $html . '</nav>';
	}

	/**
	 * The cards of a result.
	 *
	 * @param WP_Query $query Posts.
	 * @param array    $opts  options().
	 * @return string
	 */
	private static function cards( $query, $opts ) {
		$html = '';
		foreach ( $query->posts as $post ) {
			$html .= self::card( $post, $opts );
		}
		return $html;
	}

	/**
	 * [atelier_irisee_blog]: all posts as polaroids, newest first, with the filter bar.
	 *
	 * Attributes: count (posts per load), category (only these categories, e.g. "irisee-focus"), columns (per row on
	 * computers), layout (random, wave or scatter), sort (newest, oldest, likes, views), filters="no" (no filter bar).
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'count'    => '',
				'category' => '',
				'columns'  => '',
				'layout'   => '',
				'sort'     => '',
				'filters'  => '',
			),
			$atts,
			self::TAG
		);
		self::enqueue();
		$opts   = self::options( $atts );
		$state  = self::state( $_GET, $opts ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filters in the address.
		$query  = self::query( $state, $opts, 'more' === $opts['pages'] );
		$total  = (int) $query->found_posts;
		$pages  = (int) ceil( $total / max( 1, $opts['per'] ) );
		$facets = self::facets( $opts );
		$id     = 'aimp-blog-' . ( ++self::$count );

		$classes = array( 'aimp-home-block', 'aimp-configurator', 'aimp-blog', 'is-filters-closed', 'layout-' . $opts['layout'] );
		$classes[] = 'phone-' . ( '' !== $opts['phone'] ? $opts['phone'] : '1' );
		$style = '--bl-cols:' . $opts['cols'] . ';--bl-cols-t:' . min( $opts['cols'], $opts['cols_t'] ) . ';--bl-play:' . $opts['play'] . ( $opts['gap'] >= 0 ? ';--bl-gap:' . $opts['gap'] . 'px' : '' );
		$config = array(
			'atts'  => $atts,
			'page'  => $state['page'],
			'pages' => $pages,
			'sort'  => $opts['sort'],
			'mode'  => $opts['pages'],
		);

		$html = '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '" data-aimp-blog data-config="' . esc_attr( wp_json_encode( $config ) ) . '">';
		if ( 'wave' === $opts['layout'] ) {
			$html .= self::wave_css( $id, $opts );
		}
		$grid = '<div class="aimp-shop-summary" data-role="summary" aria-live="polite">' . self::summary( $state, $facets, $total ) . '</div>' .
			'<ul class="aimp-blog-grid layout-' . esc_attr( $opts['layout'] ) . ' shape-' . esc_attr( $opts['shape'] ) . '" data-role="grid">' . self::cards( $query, $opts ) . '</ul>' .
			'<p class="aimp-blog-none" data-role="none"' . ( $total ? ' hidden' : '' ) . '>' . esc_html__( 'No posts found.', 'atelier-irisee-master-plugin' ) . '</p>' .
			'<div class="aimp-blog-more" data-role="more">' . self::more( $state, $opts, $pages ) . '</div>';

		if ( ! $opts['filters'] ) {
			return $html . '<div class="aimp-blog-content">' . $grid . '</div></div>';
		}
		$active = count( $state['cats'] ) + ( '' !== $state['q'] ? 1 : 0 );
		$sorts  = array(
			'newest' => __( 'Newest first', 'atelier-irisee-master-plugin' ),
			'oldest' => __( 'Oldest first', 'atelier-irisee-master-plugin' ),
			'likes'  => __( 'Most liked', 'atelier-irisee-master-plugin' ),
			'views'  => __( 'Most viewed', 'atelier-irisee-master-plugin' ),
		);
		$sort_by = __( 'Sort by', 'atelier-irisee-master-plugin' );
		$html   .= '<div class="aimp-topbar aimp-shop-topbar"><div class="aimp-shop-tools">' .
			'<button type="button" class="aimp-button aimp-shop-toggle" data-action="toggle-filters" aria-controls="' . esc_attr( $id ) . '-filters" aria-expanded="false">' .
			'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg><span>' . esc_html__( 'Filters', 'atelier-irisee-master-plugin' ) . '</span>' .
			'<span class="aimp-shop-count" data-role="count"' . ( $active ? '' : ' hidden' ) . '>' . $active . '</span></button>' .
			'<div class="aimp-shop-sort"><label class="screen-reader-text" for="' . esc_attr( $id ) . '-sort">' . esc_html( $sort_by ) . '</label><select id="' . esc_attr( $id ) . '-sort" class="aimp-shop-select" data-role="sort">';
		foreach ( $sorts as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $state['sort'], $value, false ) . '>' . esc_html( $sort_by . ': ' . $label ) . '</option>';
		}
		$html .= '</select></div></div></div>';

		$html .= '<div class="aimp-shop-layout"><aside class="aimp-shop-sidebar" id="' . esc_attr( $id ) . '-filters" aria-label="' . esc_attr__( 'Filters', 'atelier-irisee-master-plugin' ) . '">' .
			'<div class="aimp-shop-sidebar-head"><h3>' . esc_html__( 'Filters', 'atelier-irisee-master-plugin' ) . '</h3><button type="button" class="aimp-shop-close" data-action="close-filters" aria-label="' . esc_attr__( 'Close filters', 'atelier-irisee-master-plugin' ) . '" title="' . esc_attr__( 'Close filters', 'atelier-irisee-master-plugin' ) . '">×</button></div>' .
			'<div data-role="filters"><div class="aimp-filter-group aimp-filter-group--search"><label class="screen-reader-text" for="' . esc_attr( $id ) . '-search">' . esc_html__( 'Search', 'atelier-irisee-master-plugin' ) . '</label>' .
			'<input type="search" id="' . esc_attr( $id ) . '-search" class="aimp-search" data-role="search" value="' . esc_attr( $state['q'] ) . '" placeholder="' . esc_attr__( 'Search the blog', 'atelier-irisee-master-plugin' ) . '"></div>';
		if ( count( $facets ) > 1 ) {
			$html .= '<details class="aimp-filter-group" open><summary>' . esc_html__( 'Categories', 'atelier-irisee-master-plugin' ) . '</summary><ul class="aimp-filter-options">';
			foreach ( $facets as $facet ) {
				$html .= '<li><label class="aimp-filter-option"><input type="checkbox" value="' . esc_attr( $facet['slug'] ) . '" data-role="category"' . checked( in_array( $facet['slug'], $state['cats'], true ), true, false ) . '> ' .
					'<span class="aimp-blog-swatch" style="' . esc_attr( '--bl-cat:' . $facet['color'] ) . '" aria-hidden="true"></span><span>' . esc_html( $facet['name'] ) . '</span> <span class="aimp-shop-count">' . (int) $facet['count'] . '</span></label></li>';
			}
			$html .= '</ul></details>';
		}
		$html .= '</div><div class="aimp-shop-sidebar-foot"><button type="button" class="aimp-button aimp-button--block" data-action="close-filters">' . esc_html__( 'Show results', 'atelier-irisee-master-plugin' ) . '</button></div></aside>' .
			'<div class="aimp-shop-backdrop" data-action="close-filters" hidden></div>' .
			'<div class="aimp-blog-content">' . $grid . '</div></div>';
		return $html . '</div>';
	}

	/**
	 * Wave: a fixed rhythm per column (top, down, half way, …), for the number of columns of each screen.
	 *
	 * @param string $id   The overview's id.
	 * @param array  $opts options().
	 * @return string
	 */
	private static function wave_css( $id, $opts ) {
		$rhythm = array( 0, 1, 0.5, 0.9, 0.25, 0.7 );
		$rules  = function ( $cols ) use ( $id, $rhythm, $opts ) {
			$css = '';
			for ( $n = 1; $n <= $cols; $n++ ) {
				$css .= '#' . $id . ' .aimp-blog-item:nth-child(' . $cols . 'n+' . $n . '){--bl-drop:' . round( $rhythm[ ( $n - 1 ) % 6 ] * 64 * $opts['play'] ) . 'px}';
			}
			return $css;
		};
		$tablet = min( $opts['cols'], $opts['cols_t'] );
		$phone  = '2' === $opts['phone'] ? 2 : 1;
		// The same widths as blog.css (the room next to the filter column, not the screen).
		return '<style>' . $rules( $opts['cols'] ) . '@container aimpblog (max-width:860px){' . $rules( $tablet ) . '}@container aimpblog (max-width:540px){' . ( 'swipe' === $opts['phone'] ? $rules( 2 ) : $rules( $phone ) ) . '}</style>';
	}

	/* ------------------------------------------------------------------
	 * Requests
	 * ------------------------------------------------------------------ */

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public, read-only lists and anonymous likes (pages can be cached).

	/**
	 * ?wc-ajax=aimp_blog: the cards for other filters, a sort order or the next page.
	 */
	public static function ajax_list() {
		$atts = array();
		if ( isset( $_POST['atts'] ) ) {
			$decoded = json_decode( wp_unslash( (string) $_POST['atts'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked in options().
			foreach ( array( 'count', 'category', 'columns', 'layout', 'sort', 'filters' ) as $key ) {
				if ( is_array( $decoded ) && isset( $decoded[ $key ] ) && is_scalar( $decoded[ $key ] ) ) {
					$atts[ $key ] = sanitize_text_field( (string) $decoded[ $key ] );
				}
			}
		}
		$opts   = self::options( $atts );
		$state  = self::state( $_POST, $opts );
		$query  = self::query( $state, $opts );
		$total  = (int) $query->found_posts;
		$pages  = (int) ceil( $total / max( 1, $opts['per'] ) );
		wp_send_json_success(
			array(
				'html'    => self::cards( $query, $opts ),
				'summary' => self::summary( $state, self::facets( $opts ), $total ),
				'more'    => self::more( $state, $opts, $pages ),
				'total'   => $total,
				'page'    => $state['page'],
				'pages'   => $pages,
				'args'    => self::args( $state, $opts ),
			)
		);
	}

	/**
	 * The posts this browser liked (a cookie: ids with commas).
	 *
	 * @return int[]
	 */
	private static function liked() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		$ids = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) ) );
		return array_slice( array_values( array_unique( array_filter( $ids ) ) ), -300 );
	}

	/**
	 * ?wc-ajax=aimp_blog_like: like a post, or take the like back. Once per visitor: the browser keeps its likes in
	 * a cookie, the server an anonymous hash of the connection and browser for 30 days (also when the cookie is gone).
	 */
	public static function ajax_like() {
		nocache_headers();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( ! $id || 'post' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || '' === $ua || preg_match( AIMP_Stats::BOTS, $ua ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ) ) ), 400 );
		}
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'aimp_bl_' . substr( hash( 'sha256', wp_salt( 'nonce' ) . '|' . $ip . '|' . $ua . '|' . $id ), 0, 24 );
		$liked = self::liked();
		$likes = max( 0, (int) get_post_meta( $id, self::LIKES, true ) );
		$was   = in_array( $id, $liked, true );
		if ( $was ) {
			$likes = max( 0, $likes - 1 );
			$liked = array_values( array_diff( $liked, array( $id ) ) );
			delete_transient( $key );
			update_post_meta( $id, self::LIKES, $likes );
		} else {
			if ( ! get_transient( $key ) ) {
				++$likes;
				set_transient( $key, 1, 30 * DAY_IN_SECONDS );
				update_post_meta( $id, self::LIKES, $likes );
				AIMP_Stats::count( 'blog_like', (string) $id );
			}
			$liked[] = $id;
		}
		wc_setcookie( self::COOKIE, implode( ',', $liked ), time() + YEAR_IN_SECONDS, is_ssl(), false );
		wp_send_json_success(
			array(
				'liked' => ! $was,
				'likes' => $likes,
				/* translators: %s: number of likes */
				'label' => sprintf( _n( '%s like', '%s likes', $likes, 'atelier-irisee-master-plugin' ), number_format_i18n( $likes ) ),
				'shown' => number_format_i18n( $likes ),
			)
		);
	}

	// phpcs:enable

	/**
	 * A view of a post (AIMP_Stats::hit(), once a day per visitor).
	 *
	 * @param int $id Post.
	 */
	public static function viewed( $id ) {
		if ( 'post' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return;
		}
		update_post_meta( $id, self::VIEWS, max( 0, (int) get_post_meta( $id, self::VIEWS, true ) ) + 1 );
	}
}
