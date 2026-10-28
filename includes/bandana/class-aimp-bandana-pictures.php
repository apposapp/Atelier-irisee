<?php
/**
 * The dog bandana's pictures: styled photos for the guide page and the PDF guide, made with the Google Gemini key of
 * Settings → AI pictures (or chosen from the media library), one per slot (the hero, the three styles, materials,
 * tools, every sewing step …). Also the logo for the PDF and the logo's pink and purple.
 *
 * Without a picture, the page and the PDF show the slot's line drawing instead.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Bandana_Pictures {

	const OPTION = 'aimp_bandana_pics';

	/** The logo's colours (sampled from the Atelier Irisée logo). */
	const PINK   = '#e2bfc9';
	const PURPLE = '#79416d';

	public static function init() {
		add_action( 'wp_ajax_aimp_bandana_pic', array( __CLASS__, 'ajax' ) );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * @return array ids (slot => attachment), prompts (slot => own prompt), logo, pink, purple.
	 */
	private static function options() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'ids'     => array(),
				'prompts' => array(),
				'logo'    => 0,
				'pink'    => '',
				'purple'  => '',
			)
		);
	}

	private static function update( $options ) {
		update_option( self::OPTION, $options, false );
	}

	/**
	 * Saves the logo and the colours (from the Dog bandana settings form).
	 *
	 * @param int    $logo   Attachment.
	 * @param string $pink   Hex.
	 * @param string $purple Hex.
	 */
	public static function save_look( $logo, $pink, $purple ) {
		$options           = self::options();
		$options['logo']   = $logo && wp_attachment_is_image( $logo ) ? (int) $logo : 0;
		$options['pink']   = (string) sanitize_hex_color( $pink );
		$options['purple'] = (string) sanitize_hex_color( $purple );
		self::update( $options );
	}

	/**
	 * The logo's pink and purple (the settings, or the logo's own).
	 *
	 * @return array [ pink, purple ]
	 */
	public static function colors() {
		$options = self::options();
		return array(
			'' !== $options['pink'] ? $options['pink'] : self::PINK,
			'' !== $options['purple'] ? $options['purple'] : self::PURPLE,
		);
	}

	/**
	 * The logo chosen for the PDF in the settings (0 = the site's own).
	 *
	 * @return int
	 */
	public static function logo_setting() {
		return (int) self::options()['logo'];
	}

	/* ------------------------------------------------------------------
	 * The slots
	 * ------------------------------------------------------------------ */

	/**
	 * Slot => [ description (admin and alt text), aspect ratio, with the dog, what the picture shows ].
	 *
	 * @return array
	 */
	public static function slots() {
		$fabric = 'the bandana fabric (a soft woven cotton in cream with a small floral print in antique gold, pearl pink and plum)';
		return array(
			'hero'         => array( __( 'The dog wearing its bandana', 'atelier-irisee-master-plugin' ), '16:9', true, 'The mascot dog sits happily on a sunny wooden floor in a light, cosy room, wearing a tie-on triangle dog bandana made of ' . $fabric . ', knotted at the back of its neck. Keep the left third of the picture calm and light.' ),
			'style_tie'    => array( __( 'Tie-on bandana', 'atelier-irisee-master-plugin' ), '4:5', true, 'Portrait of the mascot dog wearing a tie-on triangle bandana made of ' . $fabric . ', with the long ties knotted in a small bow at the side of the neck. Plain light cream background.' ),
			'style_collar' => array( __( 'Over-the-collar bandana', 'atelier-irisee-master-plugin' ), '4:5', true, 'Portrait of the mascot dog wearing an over-the-collar bandana: a triangle bandana made of ' . $fabric . ' with a channel along its top edge, the dog\'s own collar threaded through the channel so the bandana hangs neatly from the collar. Plain light cream background.' ),
			'style_snap'   => array( __( 'Bandana with a snap', 'atelier-irisee-master-plugin' ), '4:5', true, 'Portrait of the mascot dog wearing a triangle bandana made of ' . $fabric . ' whose short ends close at the back of the neck with a small round plastic snap in plum. Plain light cream background.' ),
			'materials'    => array( __( 'The materials', 'atelier-irisee-master-plugin' ), '4:3', false, 'Overhead flat-lay on a pale wooden table: ' . $fabric . ' folded neatly, a plain pearl pink cotton for the reverse side, a spool of matching sewing thread, two small round plastic snaps and a roll of satin ribbon. Neatly arranged with space between the items.' ),
			'tools'        => array( __( 'The tools', 'atelier-irisee-master-plugin' ), '4:3', false, 'Overhead flat-lay on a cream linen cloth: fabric shears with gold handles, pins in a small pincushion, sewing clips, a bar of tailor\'s chalk, a soft tape measure, a wooden point turner, a seam ripper and a small iron at the edge. Neatly arranged, gentle shadows.' ),
			'fabric'       => array( __( 'Prewashed cotton fabrics', 'atelier-irisee-master-plugin' ), '4:3', false, 'A stack of three folded woven cotton fabrics, freshly washed and pressed: ' . $fabric . ' on top, a pearl pink and a plain cream cotton below, on a pale wooden shelf, with a soft tape measure beside them.' ),
			'measure'      => array( __( 'Measuring the dog\'s neck', 'atelier-irisee-master-plugin' ), '4:3', true, 'Close-up: a hand measures the neck of the mascot dog with a soft cream tape measure where its collar sits, with two fingers slipped between the tape and the neck. Friendly and calm.' ),
			'machine'      => array( __( 'The sewing machine, ready to sew', 'atelier-irisee-master-plugin' ), '4:3', false, 'Close-up of a modern white sewing machine: the presser foot, the needle and the metal needle plate with its seam guide lines; the machine is threaded with a spool of matching thread and a scrap of ' . $fabric . ' lies under the foot.' ),
			'pivot'        => array( __( 'Pivoting at a corner', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up from above: the needle of a sewing machine is down in two layers of ' . $fabric . ' at a corner; the presser foot is lifted and two hands turn the fabric around the needle.' ),
			'print'        => array( __( 'The printed template', 'atelier-irisee-master-plugin' ), '4:3', false, 'Printed A4 pattern sheets with a thin pattern line and small half-diamond marks on their edges, laid out in a grid on a table and taped together, a ruler lying across a small printed square. Nothing readable on the paper.' ),
			'cutting'      => array( __( 'Cutting the fabric', 'atelier-irisee-master-plugin' ), '4:3', false, 'On a large table, ' . $fabric . ' folded double; a paper pattern piece shaped like a triangle with long straight ends is pinned on it along the fold, and fabric shears cut along the edge of the paper.' ),
			'pin'          => array( __( 'Pinning the two pieces', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up from above: two triangle bandana pieces of ' . $fabric . ' lie exactly on top of each other, right sides together; gold-headed pins are placed at right angles to the edge every few centimetres all around.' ),
			'sew'          => array( __( 'Sewing around the bandana', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up: two pinned bandana pieces of ' . $fabric . ' being sewn on a sewing machine, the edge of the fabric following the seam guide line on the needle plate, two hands guiding the fabric flat in front of the presser foot.' ),
			'clip'         => array( __( 'Clipping the points', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up from above: small sharp scissors trim the seam allowance straight across the point of a sewn triangle bandana that is still inside out, close to the stitching without cutting it.' ),
			'turn'         => array( __( 'Turning the bandana', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up: hands turning a sewn bandana of ' . $fabric . ' right side out through a small opening in its top edge, a wooden point turner pushing out a point.' ),
			'press'        => array( __( 'Pressing the edges', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up: an iron pressing the edge of a finished triangle bandana of ' . $fabric . ' flat on an ironing board, the small opening in its top edge folded in neatly and closed with a pin.' ),
			'topstitch'    => array( __( 'Topstitching', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up: a finished triangle bandana of ' . $fabric . ' under the presser foot of a sewing machine, a neat line of stitching in matching thread 3 mm from the edge going around a point.' ),
			'snap'         => array( __( 'Fixing the snap', 'atelier-irisee-master-plugin' ), '1:1', false, 'Close-up: hands pressing a small round plastic snap in plum onto the end of a bandana of ' . $fabric . ' with snap pliers; the other snap parts lie beside it.' ),
			'ribbon'       => array( __( 'Ribbon ties', 'atelier-irisee-master-plugin' ), '1:1', false, 'A triangle bandana of ' . $fabric . ' with pearl pink satin ribbon ties sewn into its top corners, lying flat on a cream linen cloth.' ),
			'care'         => array( __( 'The dog resting in its bandana', 'atelier-irisee-master-plugin' ), '4:3', true, 'The mascot dog rests relaxed on a soft cream blanket, wearing its bandana made of ' . $fabric . '. Peaceful and cosy.' ),
		);
	}

	/**
	 * The look all pictures share.
	 *
	 * @return string
	 */
	public static function style_prompt() {
		list( $pink, $purple ) = self::colors();
		$colors                = AIMP_Bandana::palette();
		return 'A styled lifestyle photograph for a premium sewing pattern booklet of the sewing atelier "Atelier Irisée". Bright, soft natural daylight, airy and warm. ' .
			'Colours: cream and ivory, warm antique gold (' . $colors['gold'] . '), warm brown wood (' . $colors['brown'] . '), with soft pearl pink (' . $pink . ') and plum purple (' . $purple . ') accents. ' .
			'Somewhere in the scene, without hiding the subject, a satin ribbon in iridescent pearl pink with a thin gold edge forms a loose loop or circle, like the brand\'s logo. ' .
			'Light linen and pale wood textures, shallow depth of field, sewing-magazine quality, photorealistic. No text, no letters, no numbers, no logos, no watermarks.';
	}

	/**
	 * The prompt of a slot: the owner's own, or the standard one.
	 *
	 * @param string $slot Slot.
	 * @return string
	 */
	public static function prompt( $slot ) {
		$options = self::options();
		$slots   = self::slots();
		if ( ! empty( $options['prompts'][ $slot ] ) ) {
			return (string) $options['prompts'][ $slot ];
		}
		return isset( $slots[ $slot ] ) ? $slots[ $slot ][3] : '';
	}

	/**
	 * The picture of a slot (0 = none).
	 *
	 * @param string $slot Slot.
	 * @return int
	 */
	public static function id( $slot ) {
		$ids = self::options()['ids'];
		$id  = isset( $ids[ $slot ] ) ? (int) $ids[ $slot ] : 0;
		return $id && wp_attachment_is_image( $id ) ? $id : 0;
	}

	/**
	 * The picture of a slot as <img> ('' = none).
	 *
	 * @param string $slot  Slot.
	 * @param string $size  Image size.
	 * @param string $sizes The sizes attribute.
	 * @param bool   $lazy  Load lazily.
	 * @return string
	 */
	public static function img( $slot, $size = 'large', $sizes = '', $lazy = true ) {
		$id = self::id( $slot );
		if ( ! $id ) {
			return '';
		}
		$slots = self::slots();
		$attr  = array(
			'class'   => 'aimp-bandana-photo-img',
			'alt'     => isset( $slots[ $slot ] ) ? $slots[ $slot ][0] : '',
			'loading' => $lazy ? 'lazy' : 'eager',
		);
		if ( '' !== $sizes ) {
			$attr['sizes'] = $sizes;
		}
		return (string) wp_get_attachment_image( $id, $size, false, $attr );
	}

	/* ------------------------------------------------------------------
	 * Files: the logo, references for the AI, JPEGs for the PDF
	 * ------------------------------------------------------------------ */

	/**
	 * A file in the uploads folder from its address ('' when it is not there).
	 *
	 * @param string $url Address.
	 * @return string
	 */
	private static function url_to_file( $url ) {
		if ( '' === $url ) {
			return '';
		}
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			return (string) get_attached_file( $id );
		}
		$uploads = wp_get_upload_dir();
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
		$base    = (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		return '' !== $base && 0 === strpos( $path, $base ) ? $uploads['basedir'] . substr( $path, strlen( $base ) ) : '';
	}

	/**
	 * Whether a picture file can be read here (GD reads PNG and JPEG; WebP only with support; never SVG).
	 *
	 * @param string $file File.
	 * @return bool
	 */
	private static function readable_picture( $file ) {
		if ( '' === $file || ! is_readable( $file ) ) {
			return false;
		}
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif' ), true ) ) {
			return true;
		}
		return 'webp' === $ext && function_exists( 'imagecreatefromwebp' );
	}

	/**
	 * The logo file for the PDF and the AI: the one chosen in the settings, else the same as the site header
	 * (Divi's logo), the hero logo, the email logo and the WordPress site logo, in that order. A file that cannot
	 * be read (SVG …) is skipped.
	 *
	 * @return string
	 */
	public static function logo_file() {
		$files = array();
		if ( self::logo_setting() ) {
			$files[] = (string) get_attached_file( self::logo_setting() );
		}
		if ( function_exists( 'et_get_option' ) ) {
			$files[] = self::url_to_file( (string) et_get_option( 'divi_logo' ) );
		}
		if ( class_exists( 'AIMP_Settings' ) && AIMP_Settings::get( 'hero_logo' ) ) {
			$files[] = (string) get_attached_file( (int) AIMP_Settings::get( 'hero_logo' ) );
		}
		if ( class_exists( 'AIMP_Design' ) && AIMP_Design::opt( 'emails', 'em_logo', 0 ) ) {
			$files[] = (string) get_attached_file( absint( AIMP_Design::opt( 'emails', 'em_logo', 0 ) ) );
		}
		if ( get_theme_mod( 'custom_logo' ) ) {
			$files[] = (string) get_attached_file( (int) get_theme_mod( 'custom_logo' ) );
		}
		foreach ( $files as $file ) {
			if ( self::readable_picture( $file ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * The dog of the home page hero (the brand's mascot), as a file.
	 *
	 * @return string
	 */
	private static function dog_file() {
		$id = class_exists( 'AIMP_Design' ) ? absint( AIMP_Design::opt( 'hero', 'h_img', 0 ) ) : 0;
		if ( ! $id && class_exists( 'AIMP_Settings' ) ) {
			$id = (int) AIMP_Settings::get( 'hero_image' );
		}
		$file = $id ? (string) get_attached_file( $id ) : '';
		return self::readable_picture( $file ) ? $file : '';
	}

	/**
	 * A smaller copy of a picture file (kept in uploads/aimp-bandana), as [ bytes, mime ].
	 *
	 * @param string $file   File.
	 * @param int    $max    Longest side.
	 * @param string $mime   image/jpeg or image/png (keeps transparency).
	 * @param int    $quality JPEG quality.
	 * @return array|null
	 */
	private static function resized( $file, $max, $mime = 'image/jpeg', $quality = 82 ) {
		if ( '' === $file || ! is_readable( $file ) ) {
			return null;
		}
		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'aimp-bandana';
		$ext     = 'image/png' === $mime ? 'png' : 'jpg';
		$cache   = $dir . '/' . md5( $file . '|' . filemtime( $file ) . '|' . $max . '|' . $quality ) . '.' . $ext;
		if ( ! is_readable( $cache ) ) {
			wp_mkdir_p( $dir );
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				return null;
			}
			$size = $editor->get_size();
			if ( max( $size['width'], $size['height'] ) > $max ) {
				$editor->resize( $max, $max, false );
			}
			$editor->set_quality( $quality );
			$saved = $editor->save( $cache, $mime );
			if ( is_wp_error( $saved ) ) {
				return null;
			}
		}
		$bytes = file_get_contents( $cache ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
		return $bytes ? array( $bytes, $mime ) : null;
	}

	/**
	 * A slot's picture as a JPEG for the PDF guide ('' = none).
	 *
	 * @param string $slot Slot.
	 * @param int    $max  Longest side in pixels.
	 * @return string
	 */
	public static function jpeg( $slot, $max = 1400 ) {
		$id = self::id( $slot );
		if ( ! $id ) {
			return '';
		}
		$copy = self::resized( (string) get_attached_file( $id ), $max, 'image/jpeg', 72 );
		return $copy ? $copy[0] : '';
	}

	/* ------------------------------------------------------------------
	 * Making a picture
	 * ------------------------------------------------------------------ */

	/**
	 * Generates the picture of a slot with Google Gemini and links it to the slot (the old one stays in the media
	 * library).
	 *
	 * @param string $slot Slot.
	 * @return int|WP_Error Attachment.
	 */
	public static function generate( $slot ) {
		$slots = self::slots();
		if ( ! isset( $slots[ $slot ] ) ) {
			return new WP_Error( 'aimp_bandana', __( 'Unknown picture.', 'atelier-irisee-master-plugin' ) );
		}
		if ( ! class_exists( 'AIMP_AI_Looks' ) ) {
			return new WP_Error( 'aimp_bandana', __( 'No Google Gemini key: save one under Settings → AI pictures.', 'atelier-irisee-master-plugin' ) );
		}
		$images = array();
		$logo   = self::resized( self::logo_file(), 768, 'image/png' );
		if ( $logo ) {
			$images[] = array( $logo[0], $logo[1], 'the brand logo. Use only its colours and its flowing ribbon-circle motif; never copy its letters or the logo itself' );
		}
		if ( $slots[ $slot ][2] ) {
			$dog = self::resized( self::dog_file(), 1024 );
			if ( $dog ) {
				$images[] = array( $dog[0], $dog[1], 'the brand\'s mascot dog. The dog in the picture must be this same dog: keep its breed, size, coat colour, markings and face' );
			}
		}
		$result = AIMP_AI_Looks::generate_picture( self::prompt( $slot ) . "\n\n" . self::style_prompt(), $images, $slots[ $slot ][1] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$mime = strtolower( trim( explode( ';', $result['mime'] )[0] ) );
		$ext  = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		);
		$ext  = isset( $ext[ $mime ] ) ? $ext[ $mime ] : 'png';
		$up   = wp_upload_bits( sanitize_file_name( 'dog-bandana-' . $slot . '-' . time() . '.' . $ext ), null, $result['image'] );
		if ( ! empty( $up['error'] ) ) {
			return new WP_Error( 'aimp_bandana', $up['error'] );
		}
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => __( 'Dog bandana', 'atelier-irisee-master-plugin' ) . ' – ' . $slots[ $slot ][0],
				'post_status'    => 'inherit',
			),
			$up['file']
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'aimp_bandana', __( 'The picture could not be saved.', 'atelier-irisee-master-plugin' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $slots[ $slot ][0] );
		self::link( $slot, (int) $id );
		return (int) $id;
	}

	/**
	 * Links a picture to a slot (0 = none).
	 */
	private static function link( $slot, $id ) {
		$options = self::options();
		if ( $id ) {
			$options['ids'][ $slot ] = (int) $id;
		} else {
			unset( $options['ids'][ $slot ] );
		}
		self::update( $options );
	}

	/* ------------------------------------------------------------------
	 * Admin: the pictures in the Dog bandana tab
	 * ------------------------------------------------------------------ */

	/**
	 * One slot's card.
	 *
	 * @param string $slot Slot.
	 * @return string
	 */
	public static function card( $slot ) {
		$slots  = self::slots();
		$info   = $slots[ $slot ];
		$id     = self::id( $slot );
		$own    = ! empty( self::options()['prompts'][ $slot ] );
		$thumb  = $id ? wp_get_attachment_image( $id, 'medium', false, array( 'alt' => '' ) ) : '<span class="aimp-bpic-empty">' . esc_html__( 'No picture yet: the page shows the line drawing.', 'atelier-irisee-master-plugin' ) . '</span>';
		$button = function ( $op, $text, $primary = false ) {
			return '<button type="button" class="button' . ( $primary ? ' button-primary' : '' ) . '" data-op="' . esc_attr( $op ) . '">' . esc_html( $text ) . '</button>';
		};
		return '<div class="aimp-bpic" data-slot="' . esc_attr( $slot ) . '" data-missing="' . ( $id ? '0' : '1' ) . '">' .
			'<div class="aimp-bpic-thumb" style="aspect-ratio:' . esc_attr( str_replace( ':', '/', $info[1] ) ) . '">' . $thumb . '</div>' .
			'<strong>' . esc_html( $info[0] ) . '</strong>' .
			'<span class="aimp-bpic-meta">' . esc_html( $info[1] ) . ( $info[2] ? ' · ' . esc_html__( 'with your dog', 'atelier-irisee-master-plugin' ) : '' ) . ( $own ? ' · ' . esc_html__( 'own prompt', 'atelier-irisee-master-plugin' ) : '' ) . '</span>' .
			'<span class="aimp-bpic-status" role="status"></span>' .
			'<div class="aimp-bpic-buttons">' .
			$button( 'generate', $id ? __( 'Generate again', 'atelier-irisee-master-plugin' ) : __( 'Generate', 'atelier-irisee-master-plugin' ), ! $id ) .
			$button( 'choose', __( 'Choose own picture', 'atelier-irisee-master-plugin' ) ) .
			( $id ? $button( 'unlink', __( 'Remove from the guide', 'atelier-irisee-master-plugin' ) ) : '' ) .
			'</div>' .
			'<details class="aimp-bpic-prompt"><summary>' . esc_html__( 'Prompt', 'atelier-irisee-master-plugin' ) . '</summary>' .
			'<textarea rows="5" class="large-text">' . esc_textarea( self::prompt( $slot ) ) . '</textarea>' .
			'<p>' . $button( 'prompt', __( 'Save prompt', 'atelier-irisee-master-plugin' ) ) . ' ' . ( $own ? $button( 'reset', __( 'Back to standard', 'atelier-irisee-master-plugin' ) ) : '' ) . '</p></details>' .
			'</div>';
	}

	/**
	 * The pictures section (outside the settings form: everything goes through AJAX).
	 */
	public static function render_admin() {
		$missing = 0;
		foreach ( array_keys( self::slots() ) as $slot ) {
			$missing += self::id( $slot ) ? 0 : 1;
		}
		?>
		<h2 id="aimp-bandana-pictures"><?php esc_html_e( 'Pictures', 'atelier-irisee-master-plugin' ); ?></h2>
		<p><?php esc_html_e( 'Styled photos for the guide page and the PDF guide, in the colours of the site and the logo. "Generate" makes one with the Google Gemini key of Settings → AI pictures (one request per picture); the pictures with your dog use the hero photo of the home page as the example. You can also choose your own picture. Removing a picture from the guide keeps it in the media library.', 'atelier-irisee-master-plugin' ); ?></p>
		<p>
			<button type="button" class="button button-primary" id="aimp-bpic-all" data-count="<?php echo esc_attr( (string) $missing ); ?>"><?php esc_html_e( 'Generate all missing pictures', 'atelier-irisee-master-plugin' ); ?></button>
			<span id="aimp-bpic-progress" role="status"></span>
		</p>
		<div class="aimp-bpics" data-nonce="<?php echo esc_attr( wp_create_nonce( 'aimp_bandana_pic' ) ); ?>">
			<?php
			foreach ( array_keys( self::slots() ) as $slot ) {
				echo self::card( $slot ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card().
			}
			?>
		</div>
		<style>
			.aimp-bpics{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px;margin:12px 0 24px}
			.aimp-bpic{display:grid;gap:6px;align-content:start;padding:10px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
			.aimp-bpic-thumb{display:grid;place-items:center;overflow:hidden;border-radius:6px;background:#f6f1e8}
			.aimp-bpic-thumb img{width:100%;height:100%;object-fit:cover}
			.aimp-bpic-empty{padding:12px;color:#8a6a33;font-size:12px;text-align:center}
			.aimp-bpic-meta{color:#646970;font-size:12px}
			.aimp-bpic-status{min-height:1em;font-size:12px}
			.aimp-bpic-status.is-error{color:#b32d2e}
			.aimp-bpic-buttons{display:flex;flex-wrap:wrap;gap:6px}
			.aimp-bpic.is-busy{opacity:.6}
		</style>
		<?php
	}

	public static function ajax() {
		check_ajax_referer( 'aimp_bandana_pic' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) ), 403 );
		}
		$slot  = isset( $_POST['slot'] ) ? sanitize_key( wp_unslash( $_POST['slot'] ) ) : '';
		$op    = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$slots = self::slots();
		if ( ! isset( $slots[ $slot ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown picture.', 'atelier-irisee-master-plugin' ) ) );
		}
		switch ( $op ) {
			case 'generate':
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 200 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				$result = self::generate( $slot );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				break;
			case 'choose':
				$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
				if ( ! $id || ! wp_attachment_is_image( $id ) ) {
					wp_send_json_error( array( 'message' => __( 'Choose a picture.', 'atelier-irisee-master-plugin' ) ) );
				}
				self::link( $slot, $id );
				break;
			case 'unlink':
				self::link( $slot, 0 );
				break;
			case 'prompt':
			case 'reset':
				$options = self::options();
				$text    = 'prompt' === $op && isset( $_POST['prompt'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) ) : '';
				if ( '' === $text || $text === $slots[ $slot ][3] ) {
					unset( $options['prompts'][ $slot ] );
				} else {
					$options['prompts'][ $slot ] = $text;
				}
				self::update( $options );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'atelier-irisee-master-plugin' ) ) );
		}
		wp_send_json_success( array( 'html' => self::card( $slot ) ) );
	}
}
