<?php
/**
 * Instagram feed: the newest posts of the shop's own Instagram account, shown by [atelier_irisee_instagram]
 * (AIMP_Home_Blocks::instagram()) in the site's own style. The posts come from one of two sources:
 *
 * - Smash Balloon Instagram Feed (the plugin "instagram-feed"): a feed made there. Its connection to Instagram is
 *   Smash Balloon's own, so no Meta app is needed. The posts are read from Smash Balloon's cache of that feed
 *   (SB_Instagram_Cache, SB_Instagram_Parse); showing the feed's shortcode out of sight lets Smash Balloon fetch
 *   new posts first when its cache is old.
 * - Our own token: the Instagram API with Instagram Login (a Business or Creator account and a long-lived access
 *   token). The token is renewed every 30 days, so it never runs out (they last 60 days).
 *
 * Once an hour the posts are read and their pictures saved in uploads/aimp-instagram/ (Instagram's own picture
 * addresses stop working after a while). Set under Atelier Irisee → Instagram.
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
	const MAX = 24;

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
				'source'     => '',
				'sb_feed'    => 0,
				'token'      => '',
				'token_time' => 0,
				'username'   => '',
				'avatar'     => '',
				'error'      => '',
			)
		);
	}

	private static function update( $changes ) {
		update_option( self::OPTION, array_merge( self::settings(), $changes ), false );
	}

	/** Whether Smash Balloon Instagram Feed is active. */
	public static function smash_active() {
		return class_exists( 'SB_Instagram_Cache' ) && class_exists( 'SB_Instagram_Parse' );
	}

	/**
	 * Where the posts come from: 'smash' or 'token' ('' = nothing set up yet). Not chosen: our own token when there
	 * is one, else Smash Balloon when it is active.
	 *
	 * @return string
	 */
	public static function source() {
		$settings = self::settings();
		if ( in_array( $settings['source'], array( 'smash', 'token' ), true ) ) {
			return $settings['source'];
		}
		if ( '' !== $settings['token'] ) {
			return 'token';
		}
		return self::smash_active() ? 'smash' : '';
	}

	/**
	 * Hourly fetching while there is a source.
	 */
	public static function schedule() {
		$source = self::source();
		$has    = 'smash' === $source || ( 'token' === $source && '' !== self::settings()['token'] );
		$next   = wp_next_scheduled( self::HOOK );
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

	/** The saved profile picture ('' = none yet). */
	public static function avatar() {
		return (string) self::settings()['avatar'];
	}

	/**
	 * The feeds made in Smash Balloon: ID => name.
	 *
	 * @return array
	 */
	public static function smash_feeds() {
		global $wpdb;
		$table = $wpdb->prefix . 'sbi_feeds';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! self::smash_active() || $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return array();
		}
		$feeds = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table name only.
		foreach ( (array) $wpdb->get_results( "SELECT id, feed_name FROM {$table} ORDER BY id ASC", ARRAY_A ) as $row ) {
			$feeds[ (int) $row['id'] ] = '' !== (string) $row['feed_name'] ? (string) $row['feed_name'] : '#' . (int) $row['id'];
		}
		return $feeds;
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
	 * The newest posts through our own token: [ username, posts: [ id, permalink, caption, source, type ] ].
	 *
	 * @return array|WP_Error
	 */
	private static function token_posts() {
		$settings = self::settings();
		$token    = $settings['token'];
		if ( '' === $token ) {
			return new WP_Error( 'aimp_instagram', __( 'Paste an access token first.', 'atelier-irisee-master-plugin' ) );
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
				'fields'       => 'user_id,username,profile_picture_url',
				'access_token' => $token,
			)
		);
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		$media = self::api(
			'me/media',
			array(
				'fields'       => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count',
				'limit'        => self::MAX,
				'access_token' => $token,
			)
		);
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		$posts = array();
		foreach ( isset( $media['data'] ) ? (array) $media['data'] : array() as $post ) {
			$type = isset( $post['media_type'] ) ? (string) $post['media_type'] : '';
			// Videos: their cover picture.
			$posts[] = array(
				'id'        => isset( $post['id'] ) ? (string) $post['id'] : '',
				'permalink' => isset( $post['permalink'] ) ? (string) $post['permalink'] : '',
				'caption'   => isset( $post['caption'] ) ? (string) $post['caption'] : '',
				'source'    => 'VIDEO' === $type ? ( isset( $post['thumbnail_url'] ) ? (string) $post['thumbnail_url'] : '' ) : ( isset( $post['media_url'] ) ? (string) $post['media_url'] : '' ),
				'type'      => $type,
				'time'      => isset( $post['timestamp'] ) ? (int) strtotime( (string) $post['timestamp'] ) : 0,
				'likes'     => isset( $post['like_count'] ) ? (int) $post['like_count'] : -1,
				'comments'  => isset( $post['comments_count'] ) ? (int) $post['comments_count'] : -1,
			);
		}
		return array(
			'username' => isset( $me['username'] ) ? (string) $me['username'] : '',
			'avatar'   => isset( $me['profile_picture_url'] ) ? (string) $me['profile_picture_url'] : '',
			'posts'    => $posts,
		);
	}

	/**
	 * The newest posts of a Smash Balloon feed, from its own cache: [ username, posts ].
	 *
	 * @param int $feed_id Smash Balloon feed (0 = the first one).
	 * @return array|WP_Error
	 */
	private static function smash_posts( $feed_id ) {
		if ( ! self::smash_active() ) {
			return new WP_Error( 'aimp_instagram', __( 'Smash Balloon Instagram Feed is not active.', 'atelier-irisee-master-plugin' ) );
		}
		$feeds = self::smash_feeds();
		if ( ! $feeds ) {
			return new WP_Error( 'aimp_instagram', __( 'Make a feed in Smash Balloon first (Instagram Feed → All Feeds).', 'atelier-irisee-master-plugin' ) );
		}
		$feed_id = isset( $feeds[ $feed_id ] ) ? (int) $feed_id : (int) key( $feeds );

		// Shown out of sight first: Smash Balloon then fetches new posts when its cache of the feed is old.
		do_shortcode( '[instagram-feed feed="' . $feed_id . '"]' );

		$cache = new SB_Instagram_Cache( '*' . $feed_id );
		$cache->retrieve_and_set();
		$data = json_decode( (string) $cache->get( 'posts' ), true );
		if ( empty( $data['data'] ) ) {
			$data = json_decode( (string) $cache->get( 'posts_backup' ), true );
		}
		if ( empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new WP_Error( 'aimp_instagram', __( 'Smash Balloon has no posts for this feed yet. Open the feed once in Instagram Feed → All Feeds, then save here again.', 'atelier-irisee-master-plugin' ) );
		}

		$username = '';
		$avatar   = '';
		$header   = json_decode( (string) $cache->get( 'header' ), true );
		if ( is_array( $header ) && method_exists( 'SB_Instagram_Parse', 'get_username' ) ) {
			$username = (string) SB_Instagram_Parse::get_username( $header );
		}
		if ( is_array( $header ) ) {
			// Smash Balloon's own copy when it has one, else Instagram's address (not get_avatar(): with its GDPR
			// setting on, that gives a grey placeholder).
			foreach ( array( 'local_avatar_url', 'local_avatar' ) as $key ) {
				if ( '' === $avatar && ! empty( $header[ $key ] ) && is_string( $header[ $key ] ) ) {
					$avatar = $header[ $key ];
				}
			}
			$avatar = '' !== $avatar ? $avatar : (string) SB_Instagram_Parse::get_avatar_url( $header );
		}
		if ( '' === $username || '' === $avatar ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table name only.
			$row = $wpdb->get_row( "SELECT username, info FROM {$wpdb->prefix}sbi_sources ORDER BY last_updated DESC LIMIT 1", ARRAY_A );
			if ( $row ) {
				$username = '' !== $username ? $username : (string) $row['username'];
				$info     = json_decode( (string) $row['info'], true );
				if ( '' === $avatar && is_array( $info ) ) {
					$avatar = (string) SB_Instagram_Parse::get_avatar_url( $info );
				}
			}
		}

		$posts = array();
		foreach ( $data['data'] as $post ) {
			if ( ! is_array( $post ) || ( isset( $post['is_shared_to_feed'] ) && false === $post['is_shared_to_feed'] ) ) {
				continue; // Trial reels are not on the profile either.
			}
			$posts[] = array(
				'id'        => (string) SB_Instagram_Parse::get_post_id( $post ),
				'permalink' => (string) SB_Instagram_Parse::get_permalink( $post ),
				'caption'   => (string) SB_Instagram_Parse::get_caption( $post, '' ),
				'source'    => (string) SB_Instagram_Parse::get_media_url( $post, 'full' ),
				'type'      => isset( $post['media_type'] ) ? strtoupper( (string) $post['media_type'] ) : 'IMAGE',
				'time'      => (int) SB_Instagram_Parse::get_timestamp( $post ),
				'likes'     => isset( $post['like_count'] ) ? (int) $post['like_count'] : -1,
				'comments'  => isset( $post['comments_count'] ) ? (int) $post['comments_count'] : -1,
			);
		}
		return array(
			'username' => $username,
			'avatar'   => $avatar,
			'posts'    => $posts,
		);
	}

	/**
	 * Reads the newest posts and saves their pictures (hourly, and right after saving the settings).
	 */
	public static function sync() {
		$source = self::source();
		if ( '' === $source ) {
			return;
		}
		$result = 'smash' === $source ? self::smash_posts( (int) self::settings()['sb_feed'] ) : self::token_posts();
		if ( is_wp_error( $result ) ) {
			self::update( array( 'error' => $result->get_error_message() ) );
			return;
		}

		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'aimp-instagram/';
		$url     = trailingslashit( $uploads['baseurl'] ) . 'aimp-instagram/';
		wp_mkdir_p( $dir );
		$items = array();
		$keep  = array();
		foreach ( $result['posts'] as $post ) {
			if ( count( $items ) >= self::MAX ) {
				break;
			}
			$id = preg_replace( '/[^0-9A-Za-z_]/', '', $post['id'] );
			if ( '' === $id || '' === $post['source'] || '' === $post['permalink'] ) {
				continue;
			}
			// In its own shape, 1080 px on its long side (-l: the square 640 px ones of before are fetched again).
			$file = $id . '-l.jpg';
			if ( ! file_exists( $dir . $file ) && ! self::save_picture( $post['source'], $dir . $file ) ) {
				continue;
			}
			$size    = wp_getimagesize( $dir . $file );
			$keep[]  = $file;
			$items[] = array(
				'id'        => $id,
				'permalink' => esc_url_raw( $post['permalink'] ),
				'caption'   => wp_strip_all_tags( $post['caption'] ),
				'image'     => $url . $file,
				'width'     => $size ? (int) $size[0] : 0,
				'height'    => $size ? (int) $size[1] : 0,
				'type'      => $post['type'],
				'time'      => isset( $post['time'] ) ? (int) $post['time'] : 0,
				'likes'     => isset( $post['likes'] ) ? (int) $post['likes'] : -1,
				'comments'  => isset( $post['comments'] ) ? (int) $post['comments'] : -1,
			);
		}
		if ( ! $items ) {
			self::update( array( 'error' => __( 'No pictures could be saved from the posts.', 'atelier-irisee-master-plugin' ) ) );
			return;
		}
		// The profile picture, small, renewed once a day.
		$avatar = '';
		$fresh  = file_exists( $dir . 'avatar.jpg' ) && time() - (int) filemtime( $dir . 'avatar.jpg' ) < DAY_IN_SECONDS;
		if ( ! empty( $result['avatar'] ) && ! $fresh ) {
			self::save_picture( $result['avatar'], $dir . 'avatar.jpg', 150 );
			clearstatcache();
		}
		if ( file_exists( $dir . 'avatar.jpg' ) ) {
			$avatar = $url . 'avatar.jpg?v=' . (int) filemtime( $dir . 'avatar.jpg' );
		}
		$keep[] = 'avatar.jpg';
		// Pictures of posts that are no longer among the newest.
		foreach ( (array) glob( $dir . '*.jpg' ) as $old ) {
			if ( ! in_array( basename( $old ), $keep, true ) ) {
				wp_delete_file( $old );
			}
		}
		update_option(
			self::FEED,
			array(
				'time'   => time(),
				'items'  => $items,
				'source' => $source,
			),
			false
		);
		self::update(
			array(
				'username' => sanitize_text_field( $result['username'] ),
				'avatar'   => esc_url_raw( $avatar ),
				'error'    => '',
			)
		);
	}

	/**
	 * A picture from Instagram as a JPEG of at most 1080 px, in its own shape.
	 *
	 * @param string $source Address.
	 * @param string $path   File.
	 * @param int    $max    Longest side.
	 * @return bool
	 */
	private static function save_picture( $source, $path, $max = 1080 ) {
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
			$editor->resize( $max, $max, false );
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
		$token  = isset( $_POST['token'] ) ? preg_replace( '/[^A-Za-z0-9_\-\.|]/', '', (string) wp_unslash( $_POST['token'] ) ) : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$feed   = isset( $_POST['sb_feed'] ) ? absint( $_POST['sb_feed'] ) : 0;
		$clear  = ! empty( $_POST['clear'] );
		// phpcs:enable
		if ( $clear ) {
			delete_option( self::OPTION );
			delete_option( self::FEED );
			wp_clear_scheduled_hook( self::HOOK );
		} else {
			$changes = array(
				'source'  => in_array( $source, array( 'smash', 'token' ), true ) ? $source : '',
				'sb_feed' => $feed,
				'error'   => '',
			);
			if ( '' !== $token ) {
				$changes['token']      = $token;
				$changes['token_time'] = time();
			}
			self::update( $changes );
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
		$source   = self::source();
		$feed     = get_option( self::FEED, array() );
		$feeds    = self::smash_feeds();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Instagram', 'atelier-irisee-master-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Shows your newest Instagram posts in the style of the site with [atelier_irisee_instagram]. How many, in how many columns, their shape and more: Design studio → Instagram.', 'atelier-irisee-master-plugin' ); ?></p>

			<?php if ( '' !== $settings['error'] ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( __( 'Instagram gave an error:', 'atelier-irisee-master-plugin' ) . ' ' . $settings['error'] ); ?></p></div>
			<?php elseif ( self::items() ) : ?>
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
			<?php if ( 'smash' === $source && ! self::smash_active() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Smash Balloon Instagram Feed is not active: the block keeps showing the posts saved last.', 'atelier-irisee-master-plugin' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aimp_instagram_save">
				<?php wp_nonce_field( 'aimp_instagram_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Posts come from', 'atelier-irisee-master-plugin' ); ?></th>
						<td>
							<label><input type="radio" name="source" value="smash" <?php checked( 'smash', $source ); ?>> <?php esc_html_e( 'A Smash Balloon feed (no Meta app needed)', 'atelier-irisee-master-plugin' ); ?></label><br>
							<label><input type="radio" name="source" value="token" <?php checked( 'token', $source ); ?>> <?php esc_html_e( 'My own access token', 'atelier-irisee-master-plugin' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="aimp-ig-feed"><?php esc_html_e( 'Smash Balloon feed', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td>
							<?php if ( $feeds ) : ?>
								<select id="aimp-ig-feed" name="sb_feed">
									<?php foreach ( $feeds as $id => $name ) : ?>
										<option value="<?php echo esc_attr( $id ); ?>" <?php selected( (int) $settings['sb_feed'], $id ); ?>><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Only its posts are used, not its look. Smash Balloon keeps the connection with Instagram.', 'atelier-irisee-master-plugin' ); ?></p>
							<?php else : ?>
								<p class="description"><?php echo esc_html( self::smash_active() ? __( 'Make a feed in Smash Balloon first (Instagram Feed → All Feeds).', 'atelier-irisee-master-plugin' ) : __( 'Install and activate the free plugin "Smash Balloon Social Photo Feed" (Instagram Feed) and connect your account there.', 'atelier-irisee-master-plugin' ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="aimp-ig-token"><?php esc_html_e( 'Access token', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td>
							<input type="password" id="aimp-ig-token" name="token" class="large-text" autocomplete="off" placeholder="<?php echo '' !== $settings['token'] ? esc_attr__( 'Saved. Paste a new token to replace it.', 'atelier-irisee-master-plugin' ) : ''; ?>">
							<p class="description"><?php esc_html_e( 'Only for "My own access token". The plugin renews the token by itself every 30 days.', 'atelier-irisee-master-plugin' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<?php submit_button( __( 'Save and fetch now', 'atelier-irisee-master-plugin' ), 'primary', 'submit', false ); ?>
					<?php if ( '' !== $settings['token'] || self::items() ) : ?>
						<button type="submit" name="clear" value="1" class="button-link button-link-delete"><?php esc_html_e( 'Disconnect', 'atelier-irisee-master-plugin' ); ?></button>
					<?php endif; ?>
				</p>
			</form>

			<details>
				<summary><?php esc_html_e( 'How to get your own token (only without Smash Balloon)', 'atelier-irisee-master-plugin' ); ?></summary>
				<ol>
					<li><?php esc_html_e( 'In the Instagram app: Settings → Account type and tools → switch to a professional account (Business or Creator). This is free.', 'atelier-irisee-master-plugin' ); ?></li>
					<li><?php esc_html_e( 'Go to developers.facebook.com, log in and choose My Apps → Create app → "Other" → type "Business".', 'atelier-irisee-master-plugin' ); ?></li>
					<li><?php esc_html_e( 'In the app: add the product "Instagram" and choose "API setup with Instagram login".', 'atelier-irisee-master-plugin' ); ?></li>
					<li><?php esc_html_e( 'Under "Generate access tokens": add your Instagram account, log in to Instagram and allow access.', 'atelier-irisee-master-plugin' ); ?></li>
					<li><?php esc_html_e( 'Click "Generate token", copy it and paste it above. The app can stay in development mode: it only reads your own posts.', 'atelier-irisee-master-plugin' ); ?></li>
				</ol>
			</details>
		</div>
		<?php
	}
}
