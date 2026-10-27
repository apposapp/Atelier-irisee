<?php
/**
 * Instagram feed: the newest posts of the shop's own Instagram account, through the Instagram API with Instagram
 * Login (a Business or Creator account and a long-lived access token, set under Atelier Irisee → Instagram).
 *
 * Once an hour the posts are fetched and their pictures saved in uploads/aimp-instagram/ (Instagram's own picture
 * addresses stop working after a while). The token is renewed every 30 days, so it never runs out (they last 60 days).
 * The shortcode [atelier_irisee_instagram] (AIMP_Home_Blocks::instagram()) shows them.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Instagram {

	const OPTION = 'aimp_instagram';
	const FEED   = 'aimp_instagram_feed';
	const PAGE   = 'aimp-instagram';
	const HOOK   = 'aimp_instagram_sync';
	const API    = 'https://graph.instagram.com/';

	/** The most posts that are kept. */
	const MAX = 12;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_aimp_instagram_save', array( __CLASS__, 'save' ) );
		add_action( self::HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	private static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'token'      => '',
				'token_time' => 0,
				'username'   => '',
				'error'      => '',
			)
		);
	}

	private static function update( $changes ) {
		update_option( self::OPTION, array_merge( self::settings(), $changes ), false );
	}

	/**
	 * Hourly fetching while a token is set.
	 */
	public static function schedule() {
		$has = '' !== self::settings()['token'];
		$next = wp_next_scheduled( self::HOOK );
		if ( $has && ! $next ) {
			wp_schedule_event( time() + 60, 'hourly', self::HOOK );
		} elseif ( ! $has && $next ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	/**
	 * The saved posts: [ id, permalink, caption, image (URL), type ], newest first.
	 *
	 * @return array
	 */
	public static function items() {
		$feed = get_option( self::FEED, array() );
		return is_array( $feed ) && ! empty( $feed['items'] ) ? (array) $feed['items'] : array();
	}

	public static function username() {
		return (string) self::settings()['username'];
	}

	/**
	 * A GET request to the Instagram API.
	 *
	 * @param string $path  Path.
	 * @param array  $query Query.
	 * @return array|WP_Error Decoded answer.
	 */
	private static function api( $path, $query ) {
		$response = wp_remote_get( add_query_arg( $query, self::API . $path ), array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : __( 'Instagram did not answer as expected.', 'atelier-irisee-master-plugin' );
			return new WP_Error( 'aimp_instagram', $message );
		}
		return $body;
	}

	/**
	 * Fetches the newest posts and saves their pictures (hourly, and right after saving the token).
	 */
	public static function sync() {
		$settings = self::settings();
		$token    = $settings['token'];
		if ( '' === $token ) {
			return;
		}
		// The token lasts 60 days: renewed after 30 (Instagram allows it once it is a day old).
		if ( $settings['token_time'] && time() - (int) $settings['token_time'] > 30 * DAY_IN_SECONDS ) {
			$fresh = self::api(
				'refresh_access_token',
				array(
					'grant_type'   => 'ig_refresh_token',
					'access_token' => $token,
				)
			);
			if ( ! is_wp_error( $fresh ) && ! empty( $fresh['access_token'] ) ) {
				$token = (string) $fresh['access_token'];
				self::update(
					array(
						'token'      => $token,
						'token_time' => time(),
					)
				);
			}
		}

		$me = self::api(
			'me',
			array(
				'fields'       => 'user_id,username',
				'access_token' => $token,
			)
		);
		if ( is_wp_error( $me ) ) {
			self::update( array( 'error' => $me->get_error_message() ) );
			return;
		}
		$media = self::api(
			'me/media',
			array(
				'fields'       => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
				'limit'        => self::MAX,
				'access_token' => $token,
			)
		);
		if ( is_wp_error( $media ) ) {
			self::update( array( 'error' => $media->get_error_message() ) );
			return;
		}

		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'aimp-instagram/';
		$url     = trailingslashit( $uploads['baseurl'] ) . 'aimp-instagram/';
		wp_mkdir_p( $dir );
		$items = array();
		$keep  = array();
		foreach ( isset( $media['data'] ) ? (array) $media['data'] : array() as $post ) {
			$id     = isset( $post['id'] ) ? preg_replace( '/[^0-9_]/', '', (string) $post['id'] ) : '';
			$type   = isset( $post['media_type'] ) ? (string) $post['media_type'] : '';
			// Videos: their cover picture.
			$source = 'VIDEO' === $type ? ( isset( $post['thumbnail_url'] ) ? (string) $post['thumbnail_url'] : '' ) : ( isset( $post['media_url'] ) ? (string) $post['media_url'] : '' );
			if ( '' === $id || '' === $source || empty( $post['permalink'] ) ) {
				continue;
			}
			$file = $id . '.jpg';
			if ( ! file_exists( $dir . $file ) && ! self::save_picture( $source, $dir . $file ) ) {
				continue;
			}
			$keep[]  = $file;
			$items[] = array(
				'id'        => $id,
				'permalink' => esc_url_raw( (string) $post['permalink'] ),
				'caption'   => isset( $post['caption'] ) ? wp_strip_all_tags( (string) $post['caption'] ) : '',
				'image'     => $url . $file,
				'type'      => $type,
			);
		}
		// Pictures of posts that are no longer among the newest.
		foreach ( (array) glob( $dir . '*.jpg' ) as $old ) {
			if ( ! in_array( basename( $old ), $keep, true ) ) {
				wp_delete_file( $old );
			}
		}
		update_option(
			self::FEED,
			array(
				'time'  => time(),
				'items' => $items,
			),
			false
		);
		self::update(
			array(
				'username' => isset( $me['username'] ) ? sanitize_text_field( (string) $me['username'] ) : '',
				'error'    => '',
			)
		);
	}

	/**
	 * A picture from Instagram, square and 640 px, as a JPEG.
	 *
	 * @param string $source Address.
	 * @param string $path   File.
	 * @return bool
	 */
	private static function save_picture( $source, $path ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = download_url( $source, 20 );
		if ( is_wp_error( $tmp ) ) {
			return false;
		}
		$editor = wp_get_image_editor( $tmp );
		$ok     = false;
		if ( ! is_wp_error( $editor ) ) {
			$editor->resize( 640, 640, true );
			$editor->set_quality( 82 );
			$ok = ! is_wp_error( $editor->save( $path, 'image/jpeg' ) );
		}
		wp_delete_file( $tmp );
		return $ok;
	}

	/* ------------------------------------------------------------------
	 * Settings page
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( AIMP_Settings::PAGE, __( 'Instagram', 'atelier-irisee-master-plugin' ), __( 'Instagram', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_instagram_save' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$token = isset( $_POST['token'] ) ? preg_replace( '/[^A-Za-z0-9_\-\.|]/', '', (string) wp_unslash( $_POST['token'] ) ) : '';
		$clear = ! empty( $_POST['clear'] );
		// phpcs:enable
		if ( $clear ) {
			delete_option( self::OPTION );
			delete_option( self::FEED );
			wp_clear_scheduled_hook( self::HOOK );
		} elseif ( '' !== $token ) {
			self::update(
				array(
					'token'      => $token,
					'token_time' => time(),
					'error'      => '',
				)
			);
		}
		if ( ! $clear ) {
			self::sync();
			self::schedule();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = self::settings();
		$feed     = get_option( self::FEED, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Instagram', 'atelier-irisee-master-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Shows your newest Instagram posts with [atelier_irisee_instagram] (its look and texts: Design studio → Instagram).', 'atelier-irisee-master-plugin' ); ?></p>

			<?php if ( '' !== $settings['token'] ) : ?>
				<?php if ( '' !== $settings['error'] ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( __( 'Instagram gave an error:', 'atelier-irisee-master-plugin' ) . ' ' . $settings['error'] ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-success inline"><p>
						<?php
						printf(
							/* translators: 1: Instagram account, 2: number of posts, 3: date and time */
							esc_html__( 'Connected to @%1$s: %2$d posts, last fetched %3$s. New posts appear within an hour.', 'atelier-irisee-master-plugin' ),
							esc_html( $settings['username'] ),
							count( self::items() ),
							! empty( $feed['time'] ) ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $feed['time'] ) ) : '—'
						);
						?>
					</p></div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aimp_instagram_save">
				<?php wp_nonce_field( 'aimp_instagram_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="aimp-ig-token"><?php esc_html_e( 'Access token', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td>
							<input type="password" id="aimp-ig-token" name="token" class="large-text" autocomplete="off" placeholder="<?php echo '' !== $settings['token'] ? esc_attr__( 'Saved. Paste a new token to replace it.', 'atelier-irisee-master-plugin' ) : ''; ?>">
							<p class="description"><?php esc_html_e( 'The plugin renews the token by itself every 30 days.', 'atelier-irisee-master-plugin' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<?php submit_button( __( 'Save and fetch now', 'atelier-irisee-master-plugin' ), 'primary', 'submit', false ); ?>
					<?php if ( '' !== $settings['token'] ) : ?>
						<button type="submit" name="clear" value="1" class="button-link button-link-delete"><?php esc_html_e( 'Disconnect', 'atelier-irisee-master-plugin' ); ?></button>
					<?php endif; ?>
				</p>
			</form>

			<h2><?php esc_html_e( 'How to get a token (once)', 'atelier-irisee-master-plugin' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'In the Instagram app: Settings → Account type and tools → switch to a professional account (Business or Creator). This is free.', 'atelier-irisee-master-plugin' ); ?></li>
				<li><?php esc_html_e( 'Go to developers.facebook.com, log in and choose My Apps → Create app → "Other" → type "Business".', 'atelier-irisee-master-plugin' ); ?></li>
				<li><?php esc_html_e( 'In the app: add the product "Instagram" and choose "API setup with Instagram login".', 'atelier-irisee-master-plugin' ); ?></li>
				<li><?php esc_html_e( 'Under "Generate access tokens": add your Instagram account, log in to Instagram and allow access.', 'atelier-irisee-master-plugin' ); ?></li>
				<li><?php esc_html_e( 'Click "Generate token", copy it and paste it above. The app can stay in development mode: it only reads your own posts.', 'atelier-irisee-master-plugin' ); ?></li>
			</ol>
		</div>
		<?php
	}
}
