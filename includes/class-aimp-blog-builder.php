<?php
/**
 * The blog layout on a post's edit screen: rows of at most three blocks (a title, a text, a picture or a video)
 * instead of the editor. The rows travel in the post's content field as <!-- aimp-blog:… --> (so WordPress's own
 * preview and autosave carry them too); on saving they go into _aimp_blog_rows and the content becomes a plain copy
 * for search, feeds and SEO plugins (AIMP_Blog::content_copy()).
 *
 * Posts made with the Divi builder keep the normal editor.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Blog_Builder {

	/** The rows of the post being saved (between wp_insert_post_data and save_post). */
	private static $pending = null;

	/** Its own text and spacing. */
	private static $pending_look = array();

	public static function init() {
		// The classic edit screen for posts: the rows replace the block editor.
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'classic' ), 100, 2 );
		add_action( 'load-post.php', array( __CLASS__, 'screen' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'screen' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'content' ), 20, 2 );
		add_action( 'save_post_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * @param bool   $use  Block editor.
	 * @param string $type Post type.
	 * @return bool
	 */
	public static function classic( $use, $type ) {
		return 'post' === $type ? false : $use;
	}

	/**
	 * A post made with the Divi builder (keeps its editor).
	 *
	 * @param WP_Post|null $post Post.
	 * @return bool
	 */
	private static function is_divi( $post ) {
		return $post && ( false !== strpos( (string) $post->post_content, '[et_pb_' ) || 'on' === get_post_meta( $post->ID, '_et_pb_use_builder', true ) );
	}

	/**
	 * The edit screen of a post: the rows instead of the editor.
	 */
	public static function screen() {
		$type = 'post';
		$post = null;
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post = get_post( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$type = $post ? $post->post_type : '';
		} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$type = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( 'post' !== $type || self::is_divi( $post ) ) {
			return;
		}
		remove_post_type_support( 'post', 'editor' );
		add_action( 'add_meta_boxes_post', array( __CLASS__, 'add_box' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'after_title' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// The excerpt is the text on the overview card: shown, not hidden under "Screen options".
		add_filter(
			'default_hidden_meta_boxes',
			function ( $hidden ) {
				return array_values( array_diff( (array) $hidden, array( 'postexcerpt' ) ) );
			}
		);
	}

	public static function add_box() {
		// Right under the title, where the editor was (above boxes of other plugins, e.g. SEO).
		add_meta_box( 'aimp-blog-layout', __( 'Blog layout', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'box' ), 'post', 'aimp_top', 'high' );
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function after_title( $post ) {
		if ( 'post' === $post->post_type ) {
			echo '<div id="aimp-blog-top" class="aimp-bb-top">';
			do_meta_boxes( get_current_screen(), 'aimp_top', $post );
			echo '</div>';
		}
	}

	/**
	 * The rows of a post to edit: saved ones, or the text of a post written before as one text block.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private static function rows( $post ) {
		$rows = json_decode( (string) get_post_meta( $post->ID, AIMP_Blog::ROWS, true ), true );
		if ( is_array( $rows ) && $rows ) {
			return array_map( array( 'AIMP_Blog', 'upgrade_row' ), $rows );
		}
		$content = trim( (string) $post->post_content );
		if ( '' !== $content && 0 !== strpos( $content, '<!-- aimp-blog' ) ) {
			return AIMP_Blog::clean_rows(
				array(
					array(
						'blocks' => array(
							array(
								't'    => 'text',
								'html' => $content,
							),
						),
					),
				)
			);
		}
		return array();
	}

	/**
	 * The box: the builder (admin-blog.js) and the content field that carries the rows.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function box( $post ) {
		$rows = self::rows( $post );
		echo '<div id="aimp-blog-builder" class="aimp-bb"></div>';
		echo '<textarea name="content" id="content" class="aimp-bb-content" hidden>' . esc_textarea( AIMP_Blog::encode( $rows, AIMP_Blog::look( $post->ID ) ) ) . '</textarea>';
		echo '<p class="description aimp-bb-help">' . esc_html__( 'The picture of the post (featured image) is shown on the overview card and when the post is shared; the excerpt is the text on the card. A row has up to three columns side by side, each with blocks under each other; a row with one column fills the whole width, in the middle.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	/**
	 * The settings of "Text and spacing of this post" for the builder (AIMP_Blog::LOOK_FIELDS, with the labels of the
	 * Design studio): [ key, kind, label, maximum or choices ], or [ '', 'head', title ].
	 *
	 * @return array
	 */
	private static function look_fields() {
		$standard = __( 'Standard', 'atelier-irisee-master-plugin' );
		$labels   = array(
			'head1'     => __( 'Spacing', 'atelier-irisee-master-plugin' ),
			'row'       => __( 'Space between rows', 'atelier-irisee-master-plugin' ),
			'row_s'     => __( 'Space between rows: "small"', 'atelier-irisee-master-plugin' ),
			'row_l'     => __( 'Space between rows: "large"', 'atelier-irisee-master-plugin' ),
			'colgap'    => __( 'Space between blocks side by side', 'atelier-irisee-master-plugin' ),
			'stack'     => __( 'Space between blocks under each other', 'atelier-irisee-master-plugin' ),
			'stack_s'   => __( 'Space between blocks under each other: "small"', 'atelier-irisee-master-plugin' ),
			'stack_l'   => __( 'Space between blocks under each other: "large"', 'atelier-irisee-master-plugin' ),
			'head2'     => __( 'Titles', 'atelier-irisee-master-plugin' ),
			'pt_size'   => __( 'Title of the post at the top (0 = standard)', 'atelier-irisee-master-plugin' ),
			'pt_color'  => __( 'Colour of the title at the top', 'atelier-irisee-master-plugin' ),
			'h2_size'   => __( 'Large titles (0 = standard)', 'atelier-irisee-master-plugin' ),
			'h2_color'  => __( 'Colour of the large titles', 'atelier-irisee-master-plugin' ),
			'h2_noline' => __( 'No gold double line under large titles', 'atelier-irisee-master-plugin' ),
			'h3_size'   => __( 'Small titles (0 = standard)', 'atelier-irisee-master-plugin' ),
			'h3_color'  => __( 'Colour of the small titles', 'atelier-irisee-master-plugin' ),
			't_weight'  => __( 'Thickness of the titles', 'atelier-irisee-master-plugin' ),
			'head3'     => __( 'Text', 'atelier-irisee-master-plugin' ),
			'tx_size'   => __( 'Text size (0 = standard)', 'atelier-irisee-master-plugin' ),
			'tx_lh'     => __( 'Line height', 'atelier-irisee-master-plugin' ),
			'tx_color'  => __( 'Colour of the text', 'atelier-irisee-master-plugin' ),
			'tx_width'  => __( 'Width of a text alone in its row (0 = standard, 740)', 'atelier-irisee-master-plugin' ),
			'cap_size'  => __( 'Captions under pictures and videos (0 = standard)', 'atelier-irisee-master-plugin' ),
			'cap_color' => __( 'Colour of the captions', 'atelier-irisee-master-plugin' ),
		);
		$choices  = array(
			't_weight' => array(
				array( '', $standard ),
				array( '400', __( 'Normal', 'atelier-irisee-master-plugin' ) ),
				array( '600', __( 'Half bold', 'atelier-irisee-master-plugin' ) ),
				array( '700', __( 'Bold', 'atelier-irisee-master-plugin' ) ),
			),
			'tx_lh'    => array(
				array( '', $standard ),
				array( '1.4', __( 'Tight (1.4)', 'atelier-irisee-master-plugin' ) ),
				array( '1.6', '1.6' ),
				array( '1.8', '1.8' ),
				array( '2', '2' ),
				array( '2.2', __( 'Airy (2.2)', 'atelier-irisee-master-plugin' ) ),
			),
		);
		$list     = array();
		foreach ( $labels as $key => $label ) {
			if ( 0 === strpos( $key, 'head' ) ) {
				$list[] = array( '', 'head', $label, 0 );
				continue;
			}
			$field  = AIMP_Blog::LOOK_FIELDS[ $key ];
			$list[] = array( $key, $field[0], $label, 'select' === $field[0] ? $choices[ $key ] : $field[1] );
		}
		return $list;
	}

	/**
	 * The pictures and videos already in the rows, for the builder's previews: id => [ url, thumb, kind ].
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function media( $rows ) {
		$list = array();
		foreach ( $rows as $row ) {
			foreach ( AIMP_Blog::row_blocks( $row ) as $block ) {
				foreach ( array( 'id', 'poster' ) as $key ) {
					$id = isset( $block[ $key ] ) ? (int) $block[ $key ] : 0;
					if ( $id && ! isset( $list[ $id ] ) ) {
						$image       = wp_attachment_is_image( $id );
						$list[ $id ] = array(
							'url'   => (string) wp_get_attachment_url( $id ),
							'thumb' => $image ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '',
							'kind'  => $image ? 'image' : 'video',
							'name'  => wp_basename( (string) get_attached_file( $id ) ),
						);
					}
				}
			}
		}
		return $list;
	}

	/**
	 * @param string $hook Admin page.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$post = get_post();
		self::enqueue( $post ? self::rows( $post ) : array(), array( 'look' => self::look_fields() ) );
	}

	/**
	 * The builder's script and styles, also for the newsletter (WooCommerce → Newsletter).
	 *
	 * @param array $rows  The rows being edited (for the previews of their pictures).
	 * @param array $extra More settings for the script: mode ('newsletter'), root (element id), field (selector), look.
	 */
	public static function enqueue( $rows, $extra = array() ) {
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'aimp-admin-blog', AIMP_PLUGIN_URL . 'assets/css/admin-blog.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-admin-blog', AIMP_PLUGIN_URL . 'assets/js/admin-blog.js', array( 'jquery', 'editor' ), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-blog',
			'aimpBlogBuilder',
			array_merge(
				array(
				'media' => self::media( $rows ),
				'i18n'  => array(
					'lijn'        => __( 'Line with a star', 'atelier-irisee-master-plugin' ),
					'addLijn'     => __( '+ Line with a star', 'atelier-irisee-master-plugin' ),
					'lijnWidth'   => __( 'Width', 'atelier-irisee-master-plugin' ),
					'lijnFull'    => __( 'The full width', 'atelier-irisee-master-plugin' ),
					'lijnNarrow'  => __( 'Narrower, in the middle', 'atelier-irisee-master-plugin' ),
					'section'     => __( 'A new section starts here', 'atelier-irisee-master-plugin' ),
					'addSection'  => __( '+ New section', 'atelier-irisee-master-plugin' ),
					'sectionName' => __( 'Section name', 'atelier-irisee-master-plugin' ),
					'sectionNameHint' => __( 'Empty: the first title of the section', 'atelier-irisee-master-plugin' ),
					'sectionSum'  => __( 'Short text for the summary email (optional)', 'atelier-irisee-master-plugin' ),
					'sectionSumHint' => __( 'Empty: the start of the section\'s text', 'atelier-irisee-master-plugin' ),
					'emptyNl'     => __( 'No rows yet. Start with a title or a text; with "+ New section" you mark the parts the summary email shows separately.', 'atelier-irisee-master-plugin' ),
					'row'         => __( 'Row', 'atelier-irisee-master-plugin' ),
					'addRow'      => __( '+ Row', 'atelier-irisee-master-plugin' ),
					'addTitleRow' => __( '+ Title', 'atelier-irisee-master-plugin' ),
					'addTextRow'  => __( '+ Text', 'atelier-irisee-master-plugin' ),
					'addPicText'  => __( '+ Picture and text', 'atelier-irisee-master-plugin' ),
					'addBlock'    => __( '+ Block', 'atelier-irisee-master-plugin' ),
					'lookTitle'   => __( 'Text and spacing of this post', 'atelier-irisee-master-plugin' ),
					'lookHelp'    => __( 'Empty = as for all posts (Design studio → Blog post). Spaces: 0 = no space. On phones the sizes and spaces become a little smaller by themselves.', 'atelier-irisee-master-plugin' ),
					'lookReset'   => __( 'Back to standard', 'atelier-irisee-master-plugin' ),
					'standard'    => __( 'Standard', 'atelier-irisee-master-plugin' ),
					'addColumn'   => __( '+ Column', 'atelier-irisee-master-plugin' ),
					'addUnder'    => __( '+ Block underneath', 'atelier-irisee-master-plugin' ),
					'column'      => __( 'Column', 'atelier-irisee-master-plugin' ),
					'removeCol'   => __( 'Remove this column', 'atelier-irisee-master-plugin' ),
					'stack'       => __( 'Space between blocks under each other', 'atelier-irisee-master-plugin' ),
					'gap'         => __( 'Space above this block', 'atelier-irisee-master-plugin' ),
					'spaceNone'   => __( 'None', 'atelier-irisee-master-plugin' ),
					'empty'       => __( 'No rows yet. Start with a title, a text, or a picture with text next to it.', 'atelier-irisee-master-plugin' ),
					'up'          => __( 'Move up', 'atelier-irisee-master-plugin' ),
					'down'        => __( 'Move down', 'atelier-irisee-master-plugin' ),
					'left'        => __( 'Move left', 'atelier-irisee-master-plugin' ),
					'right'       => __( 'Move right', 'atelier-irisee-master-plugin' ),
					'copy'        => __( 'Duplicate', 'atelier-irisee-master-plugin' ),
					'remove'      => __( 'Remove', 'atelier-irisee-master-plugin' ),
					'removeRow'   => __( 'Remove this row?', 'atelier-irisee-master-plugin' ),
					'yes'         => __( 'Yes, remove', 'atelier-irisee-master-plugin' ),
					'no'          => __( 'Keep', 'atelier-irisee-master-plugin' ),
					'title'       => __( 'Title', 'atelier-irisee-master-plugin' ),
					'text'        => __( 'Text', 'atelier-irisee-master-plugin' ),
					'image'       => __( 'Picture', 'atelier-irisee-master-plugin' ),
					'video'       => __( 'Video', 'atelier-irisee-master-plugin' ),
					'widths'      => __( 'Widths', 'atelier-irisee-master-plugin' ),
					'half'        => __( 'Half and half', 'atelier-irisee-master-plugin' ),
					'narrowWide'  => __( 'Narrow, wide', 'atelier-irisee-master-plugin' ),
					'wideNarrow'  => __( 'Wide, narrow', 'atelier-irisee-master-plugin' ),
					'vertical'    => __( 'Height', 'atelier-irisee-master-plugin' ),
					'vMiddle'     => __( 'In the middle', 'atelier-irisee-master-plugin' ),
					'vTop'        => __( 'At the top', 'atelier-irisee-master-plugin' ),
					'vBottom'     => __( 'At the bottom', 'atelier-irisee-master-plugin' ),
					'space'       => __( 'Space above', 'atelier-irisee-master-plugin' ),
					'spaceNormal' => __( 'Normal', 'atelier-irisee-master-plugin' ),
					'spaceSmall'  => __( 'Little', 'atelier-irisee-master-plugin' ),
					'spaceLarge'  => __( 'Much', 'atelier-irisee-master-plugin' ),
					'background'  => __( 'Soft gold background', 'atelier-irisee-master-plugin' ),
					'titleText'   => __( 'The title', 'atelier-irisee-master-plugin' ),
					'size'        => __( 'Size', 'atelier-irisee-master-plugin' ),
					'large'       => __( 'Large', 'atelier-irisee-master-plugin' ),
					'small'       => __( 'Small', 'atelier-irisee-master-plugin' ),
					'align'       => __( 'Alignment', 'atelier-irisee-master-plugin' ),
					'alignAuto'   => __( 'Automatic (in the middle when alone)', 'atelier-irisee-master-plugin' ),
					'alignLeft'   => __( 'Left', 'atelier-irisee-master-plugin' ),
					'alignCenter' => __( 'In the middle', 'atelier-irisee-master-plugin' ),
					'alignRight'  => __( 'Right', 'atelier-irisee-master-plugin' ),
					'choose'      => __( 'Choose a picture', 'atelier-irisee-master-plugin' ),
					'change'      => __( 'Change', 'atelier-irisee-master-plugin' ),
					'use'         => __( 'Use', 'atelier-irisee-master-plugin' ),
					'shape'       => __( 'Shape', 'atelier-irisee-master-plugin' ),
					'plain'       => __( 'As it is', 'atelier-irisee-master-plugin' ),
					'arch'        => __( 'Arch', 'atelier-irisee-master-plugin' ),
					'round'       => __( 'Round', 'atelier-irisee-master-plugin' ),
					'square'      => __( 'Rounded corners', 'atelier-irisee-master-plugin' ),
					'frame'       => __( 'Gold double frame', 'atelier-irisee-master-plugin' ),
					'focus'       => __( 'Click the picture to choose what stays in view.', 'atelier-irisee-master-plugin' ),
					'caption'     => __( 'Caption (optional)', 'atelier-irisee-master-plugin' ),
					'link'        => __( 'Link (optional)', 'atelier-irisee-master-plugin' ),
					'zoom'        => __( 'Larger on a click', 'atelier-irisee-master-plugin' ),
					'chooseVideo' => __( 'Choose a video', 'atelier-irisee-master-plugin' ),
					'videoUrl'    => __( 'Or a YouTube or Vimeo link', 'atelier-irisee-master-plugin' ),
					'poster'      => __( 'Picture before it plays (optional)', 'atelier-irisee-master-plugin' ),
					'auto'        => __( 'Plays on its own, silently and in a loop (no controls)', 'atelier-irisee-master-plugin' ),
					'rounded'     => __( 'Rounded corners', 'atelier-irisee-master-plugin' ),
					'videoShape'  => __( 'Corners', 'atelier-irisee-master-plugin' ),
					'square2'     => __( 'Square', 'atelier-irisee-master-plugin' ),
				),
				),
				$extra
			)
		);
	}

	/**
	 * The rows in the content field: kept in the post (meta), the content becomes the plain copy. In a preview copy
	 * (autosave) the rows stay in the content, for the preview.
	 *
	 * @param array $data    Post data (slashed).
	 * @param array $postarr Submitted data.
	 * @return array
	 */
	public static function content( $data, $postarr ) {
		$payload = AIMP_Blog::payload( wp_unslash( (string) $data['post_content'] ) );
		if ( null === $payload ) {
			return $data;
		}
		$rows = AIMP_Blog::clean_rows( $payload['rows'] );
		$look = AIMP_Blog::clean_look( $payload['look'] );
		if ( 'revision' === $data['post_type'] ) {
			$data['post_content'] = wp_slash( AIMP_Blog::encode( $rows, $look ) );
			return $data;
		}
		if ( 'post' !== $data['post_type'] ) {
			return $data;
		}
		self::$pending        = $rows;
		self::$pending_look   = $look;
		$data['post_content'] = wp_slash( AIMP_Blog::content_copy( $rows ) );
		return $data;
	}

	/**
	 * @param int     $post_id Post.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( null === self::$pending || wp_is_post_revision( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, AIMP_Blog::ROWS, wp_slash( (string) wp_json_encode( self::$pending ) ) );
		if ( self::$pending_look ) {
			update_post_meta( $post_id, AIMP_Blog::LOOK, wp_slash( (string) wp_json_encode( self::$pending_look ) ) );
		} else {
			delete_post_meta( $post_id, AIMP_Blog::LOOK );
		}
		self::$pending      = null;
		self::$pending_look = array();
	}
}
