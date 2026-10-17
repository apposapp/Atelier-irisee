<?php
/**
 * Images: a WebP copy next to every JPEG and PNG of the media library (all the sizes WordPress made), and the site
 * uses those copies. The originals stay as they are.
 *
 * Settings → Atelier Irisee → Images has one button that converts all images (in short batches, with a progress
 * bar); new uploads are converted right away. The site's HTML points to the .webp files, because the web server
 * (nginx) serves files as they are and the page cache gives every visitor the same page.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Images {

	/** Attachment meta: [ v, files: [ file name => webp name ], before, after ]. */
	const META = '_aimp_webp';

	/** Format version: a higher number makes "Optimize all images" do every image again. */
	const VERSION = 1;

	const QUALITY = 82;

	const NONCE = 'aimp_images';

	/** Seconds of work per batch request. */
	const BUDGET = 15;

	/** True while a WooCommerce email is built (some mail apps don't show WebP). */
	private static $in_email = false;

	/** Upload URL => WebP URL or '' (per request). */
	private static $seen = array();

	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_upload' ), 20, 2 );
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_aimp_images_batch', array( __CLASS__, 'ajax_batch' ) );

		// The site uses the WebP copies.
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'filter_src' ), 20 );
		add_filter( 'wp_calculate_image_srcset', array( __CLASS__, 'filter_srcset' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'buffer' ), 1 );
		add_action( 'woocommerce_email_header', array( __CLASS__, 'email_start' ) );
		add_action( 'woocommerce_email_footer', array( __CLASS__, 'email_end' ), 99 );
	}

	/* ------------------------------------------------------------------ converting */

	/**
	 * The image files of an attachment (original and sizes) that can get a WebP copy: path => name.
	 *
	 * @param int $id Attachment.
	 * @return array
	 */
	private static function files( $id ) {
		$file = get_attached_file( $id );
		if ( ! $file || ! preg_match( '/\.(jpe?g|png)$/i', $file ) ) {
			return array();
		}
		$dir   = trailingslashit( dirname( $file ) );
		$files = array( $file => wp_basename( $file ) );
		$meta  = wp_get_attachment_metadata( $id );
		// WordPress keeps the uploaded file before scaling it down ("-scaled"): that one too.
		if ( ! empty( $meta['original_image'] ) ) {
			$files[ $dir . $meta['original_image'] ] = $meta['original_image'];
		}
		foreach ( ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) ? $meta['sizes'] : array() as $size ) {
			if ( ! empty( $size['file'] ) && preg_match( '/\.(jpe?g|png)$/i', $size['file'] ) ) {
				$files[ $dir . $size['file'] ] = $size['file'];
			}
		}
		return $files;
	}

	/**
	 * Makes the WebP copies of one attachment. A copy that is not smaller than its original is not kept.
	 *
	 * @param int $id Attachment.
	 * @return bool Whether it could be converted (or had nothing to convert).
	 */
	public static function convert( $id ) {
		$files = self::files( $id );
		$done  = array();
		$ok    = true;
		$before = 0;
		$after  = 0;
		foreach ( $files as $path => $name ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$size   = (int) filesize( $path );
			$target = $path . '.webp';
			$editor = wp_get_image_editor( $path );
			if ( is_wp_error( $editor ) ) {
				$ok = false;
				continue;
			}
			$editor->set_quality( self::QUALITY );
			$saved = $editor->save( $target, 'image/webp' );
			if ( is_wp_error( $saved ) || ! file_exists( $target ) ) {
				$ok = false;
				continue;
			}
			$webp = (int) filesize( $target );
			if ( $webp <= 0 || $webp >= $size ) {
				wp_delete_file( $target );
				$before += $size;
				$after  += $size;
				continue;
			}
			$done[ $name ] = $name . '.webp';
			$before       += $size;
			$after        += $webp;
		}
		update_post_meta(
			$id,
			self::META,
			array(
				'v'      => self::VERSION,
				'files'  => $done,
				'before' => $before,
				'after'  => $after,
				'failed' => ! $ok,
			)
		);
		return $ok;
	}

	/**
	 * New uploads: converted as soon as WordPress has made the sizes.
	 *
	 * @param array $metadata Attachment metadata.
	 * @param int   $id       Attachment.
	 * @return array
	 */
	public static function on_upload( $metadata, $id ) {
		if ( wp_attachment_is_image( $id ) ) {
			// The metadata is not saved yet: use it directly.
			add_filter(
				'wp_get_attachment_metadata',
				$use = function ( $data, $post_id ) use ( $metadata, $id ) {
					return (int) $post_id === (int) $id ? $metadata : $data;
				},
				10,
				2
			);
			self::convert( $id );
			remove_filter( 'wp_get_attachment_metadata', $use, 10 );
		}
		return $metadata;
	}

	/**
	 * A deleted image takes its WebP copies along.
	 *
	 * @param int $id Attachment.
	 */
	public static function on_delete( $id ) {
		foreach ( array_keys( self::files( $id ) ) as $path ) {
			if ( file_exists( $path . '.webp' ) ) {
				wp_delete_file( $path . '.webp' );
			}
		}
	}

	/* ------------------------------------------------------------------ the button */

	/**
	 * All images of the media library (JPEG and PNG).
	 *
	 * @return int[]
	 */
	private static function all_ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => array( 'image/jpeg', 'image/png' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			)
		);
	}

	/**
	 * How far the optimizing is: [ total, optimized, before, after ].
	 *
	 * @return array
	 */
	public static function stats() {
		$ids       = self::all_ids();
		$optimized = 0;
		$before    = 0;
		$after     = 0;
		foreach ( $ids as $id ) {
			$meta = get_post_meta( $id, self::META, true );
			if ( is_array( $meta ) && (int) $meta['v'] >= self::VERSION ) {
				++$optimized;
				$before += (int) $meta['before'];
				$after  += (int) $meta['after'];
			}
		}
		return array(
			'total'     => count( $ids ),
			'optimized' => $optimized,
			'before'    => $before,
			'after'     => $after,
		);
	}

	/**
	 * admin-ajax: converts the next images for at most BUDGET seconds. "restart" first forgets what was done.
	 */
	public static function ajax_batch() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) ), 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- not allowed on every host.
		}
		wp_raise_memory_limit( 'image' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		if ( ! empty( $_POST['restart'] ) ) {
			foreach ( self::all_ids() as $id ) {
				delete_post_meta( $id, self::META );
			}
		}
		$start  = microtime( true );
		$failed = array();
		foreach ( self::all_ids() as $id ) {
			$meta = get_post_meta( $id, self::META, true );
			if ( is_array( $meta ) && (int) $meta['v'] >= self::VERSION ) {
				continue;
			}
			if ( ! self::convert( $id ) ) {
				$failed[] = $id;
			}
			if ( microtime( true ) - $start > self::BUDGET ) {
				break;
			}
		}
		$stats         = self::stats();
		$stats['done'] = $stats['optimized'] >= $stats['total'];
		$stats['failed'] = $failed;
		if ( $stats['done'] ) {
			// Cached pages still point to the old files: let them be made again.
			do_action( 'litespeed_purge_all' );
		}
		wp_send_json_success( $stats );
	}

	/**
	 * "48.3 MB".
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	private static function mb( $bytes ) {
		return size_format( max( 0, (int) $bytes ), 1 );
	}

	public static function register_settings() {
		add_settings_section(
			'aimp_images',
			__( 'Images', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_section' ),
			AIMP_Settings::PAGE
		);
	}

	public static function render_section() {
		$stats = self::stats();
		$saved = $stats['before'] > 0 ? round( 100 - $stats['after'] / $stats['before'] * 100 ) : 0;
		?>
		<div class="aimp-images" data-aimp-images
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>"
			data-busy="<?php /* translators: 1: images done, 2: all images */ esc_attr_e( 'Optimizing: %1$s / %2$s…', 'atelier-irisee-master-plugin' ); ?>"
			data-status="<?php /* translators: 1: optimized, 2: all images, 3: size before, 4: size after, 5: percent saved */ esc_attr_e( '%1$s of %2$s images optimized · %3$s → %4$s (−%5$s%%)', 'atelier-irisee-master-plugin' ); ?>"
			data-ready="<?php esc_attr_e( 'All images are optimized.', 'atelier-irisee-master-plugin' ); ?>"
			data-error="<?php esc_attr_e( 'Some images could not be optimized (IDs: %s). They are skipped; the others are done.', 'atelier-irisee-master-plugin' ); ?>"
			data-failed="<?php esc_attr_e( 'The connection was interrupted. Press the button again to continue.', 'atelier-irisee-master-plugin' ); ?>">
			<p><?php esc_html_e( 'Makes a light WebP copy of every picture (in all sizes) and shows those copies on the site. Your original pictures stay as they are. New uploads are optimized automatically.', 'atelier-irisee-master-plugin' ); ?></p>
			<p class="aimp-images-status">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: optimized, 2: all images, 3: size before, 4: size after, 5: percent saved */
						__( '%1$s of %2$s images optimized · %3$s → %4$s (−%5$s%%)', 'atelier-irisee-master-plugin' ),
						$stats['optimized'],
						$stats['total'],
						self::mb( $stats['before'] ),
						self::mb( $stats['after'] ),
						$saved
					)
				);
				?>
			</p>
			<div class="aimp-images-bar" hidden><span style="width:0"></span></div>
			<p>
				<button type="button" class="button button-primary aimp-images-start"><?php esc_html_e( 'Optimize all images', 'atelier-irisee-master-plugin' ); ?></button>
				<button type="button" class="button-link aimp-images-restart"><?php esc_html_e( 'Optimize again from the start', 'atelier-irisee-master-plugin' ); ?></button>
			</p>
			<p class="aimp-images-message" hidden></p>
		</div>
		<?php
	}

	/**
	 * @param string $hook_suffix Admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . AIMP_Settings::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script( 'aimp-admin-images', AIMP_PLUGIN_URL . 'assets/js/admin-images.js', array(), AIMP_VERSION, true );
	}

	/* ------------------------------------------------------------------ the site uses the copies */

	private static function active() {
		return ! is_admin() && ! self::$in_email && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && apply_filters( 'aimp_images_use_webp', true );
	}

	public static function email_start() {
		self::$in_email = true;
	}

	public static function email_end() {
		self::$in_email = false;
	}

	/**
	 * The WebP address of an upload address, or '' when there is no copy.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	private static function webp_url( $url ) {
		if ( isset( self::$seen[ $url ] ) ) {
			return self::$seen[ $url ];
		}
		$webp    = '';
		$uploads = wp_get_upload_dir();
		$path    = strtok( $url, '?' );
		if ( preg_match( '/\.(jpe?g|png)$/i', $path ) ) {
			foreach ( array( $uploads['baseurl'], set_url_scheme( $uploads['baseurl'], 'http' ), set_url_scheme( $uploads['baseurl'], 'https' ) ) as $base ) {
				if ( 0 === strpos( $path, $base . '/' ) ) {
					$file = $uploads['basedir'] . substr( $path, strlen( $base ) );
					if ( file_exists( $file . '.webp' ) ) {
						$webp = $path . '.webp';
					}
					break;
				}
			}
		}
		self::$seen[ $url ] = $webp;
		return $webp;
	}

	/**
	 * @param array|false $image [ url, width, height, resized ].
	 * @return array|false
	 */
	public static function filter_src( $image ) {
		if ( is_array( $image ) && ! empty( $image[0] ) && self::active() ) {
			$webp = self::webp_url( $image[0] );
			if ( '' !== $webp ) {
				$image[0] = $webp;
			}
		}
		return $image;
	}

	/**
	 * @param array $sources Width => [ url, descriptor, value ].
	 * @return array
	 */
	public static function filter_srcset( $sources ) {
		if ( is_array( $sources ) && self::active() ) {
			foreach ( $sources as $width => $source ) {
				$webp = ! empty( $source['url'] ) ? self::webp_url( $source['url'] ) : '';
				if ( '' !== $webp ) {
					$sources[ $width ]['url'] = $webp;
				}
			}
		}
		return $sources;
	}

	/**
	 * Front-end pages: every upload address in the HTML (Divi modules, backgrounds, …) to its WebP copy.
	 */
	public static function buffer() {
		if ( ! self::active() || is_feed() || is_embed() || wp_doing_ajax() || isset( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks the request type.
			return;
		}
		ob_start( array( __CLASS__, 'rewrite_html' ) );
	}

	/**
	 * @param string $html Page.
	 * @return string
	 */
	public static function rewrite_html( $html ) {
		if ( '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}
		$uploads = wp_get_upload_dir();
		$host    = preg_quote( preg_replace( '#^https?:#', '', $uploads['baseurl'] ), '#' );
		return preg_replace_callback(
			'#(?:https?:)?' . $host . '/[^"\'\s(),&]+?\.(?:jpe?g|png)(?=["\'\s),?&])#i',
			function ( $match ) use ( $uploads ) {
				$url  = $match[0];
				$full = 0 === strpos( $url, '//' ) ? set_url_scheme( $url ) : $url;
				$webp = self::webp_url( $full );
				return '' === $webp ? $url : $url . '.webp';
			},
			$html
		);
	}
}
