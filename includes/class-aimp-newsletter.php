<?php
/**
 * Newsletters: written and sent from Atelier Irisee → Newsletter.
 *
 * A newsletter (private post type aimp_newsletter) has a subject, content (the WordPress editor, with
 * pictures), an optional button and an optional attachment. Preview, a test to yourself, then "Send to
 * all confirmed subscribers": the emails go out in batches through WooCommerce's Action Scheduler, each
 * with a personal unsubscribe link, as the WooCommerce email "Newsletter" (the Atelier Irisee look).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Newsletter {

	const POST_TYPE = 'aimp_newsletter';
	const BATCH     = 25;
	const HOOK      = 'aimp_newsletter_batch';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email' ) );
		add_action( 'aimp_newsletter_admin_top', array( __CLASS__, 'render_composer' ) );
		add_action( 'admin_post_aimp_newsletter_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_aimp_newsletter_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( self::HOOK, array( __CLASS__, 'send_batch' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'template_redirect', array( __CLASS__, 'web_version' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'          => false,
				'show_ui'         => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * @param WC_Email[] $emails Emails.
	 * @return WC_Email[]
	 */
	public static function register_email( $emails ) {
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-email-newsletter.php';
		if ( class_exists( 'AIMP_Email_Newsletter' ) ) {
			$emails['AIMP_Email_Newsletter'] = new AIMP_Email_Newsletter();
		}
		return $emails;
	}

	public static function enqueue( $hook_suffix ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which admin page this is.
		if ( ! isset( $_GET['page'] ) || AIMP_Footer::ADMIN_PAGE !== $_GET['page'] ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'aimp-admin-newsletter', AIMP_PLUGIN_URL . 'assets/js/admin-newsletter.js', array( 'jquery' ), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-newsletter',
			'aimpNewsletter',
			array(
				'choose'      => __( 'Choose a file to attach', 'atelier-irisee-master-plugin' ),
				'use'         => __( 'Attach this file', 'atelier-irisee-master-plugin' ),
				/* translators: %d: number of subscribers */
				'sendConfirm' => __( 'Send this newsletter to %d confirmed subscribers now?', 'atelier-irisee-master-plugin' ),
			)
		);
		wp_enqueue_style( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/css/admin.css', array(), AIMP_VERSION );

		// The rows and blocks of the blog, for "Build it here".
		if ( class_exists( 'AIMP_Blog_Builder' ) ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- choosing what to show.
			$id = isset( $_GET['aimp_nl_copy'] ) ? absint( $_GET['aimp_nl_copy'] ) : ( isset( $_GET['aimp_nl'] ) ? absint( $_GET['aimp_nl'] ) : 0 );
			// phpcs:enable
			$nl = self::get( $id );
			AIMP_Blog_Builder::enqueue(
				$nl['rows'],
				array(
					'mode'  => 'newsletter',
					'root'  => 'aimp-nl-builder',
					'field' => '#aimp_nl_rows',
				)
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------------ */

	/**
	 * @param int $id Newsletter.
	 * @return array subject, content, button_text, button_url, attachment_id, status, total, sent, sent_at
	 */
	public static function get( $id ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return array(
				'id'            => 0,
				'subject'       => '',
				'content'       => '',
				'button_text'   => '',
				'button_url'    => '',
				'attachment_id' => 0,
				'source'        => 'builder',
				'rows'          => array(),
				'layout'        => 'full',
				'lang'          => class_exists( 'AIMP_I18n' ) ? AIMP_I18n::default_language() : 'nl',
				'page'          => 0,
				'preheader'     => '',
				'status'        => 'draft',
				'total'         => 0,
				'sent'          => 0,
				'sent_at'       => '',
			);
		}
		$meta = function ( $key ) use ( $post ) {
			return get_post_meta( $post->ID, $key, true );
		};
		return array(
			'id'            => (int) $post->ID,
			'subject'       => $post->post_title,
			'content'       => $post->post_content,
			'button_text'   => (string) $meta( '_aimp_button_text' ),
			'button_url'    => (string) $meta( '_aimp_button_url' ),
			'attachment_id' => (int) $meta( '_aimp_attachment' ),
			'source'        => self::source( $meta( '_aimp_source' ) ),
			'rows'          => self::rows_from( $meta( '_aimp_rows' ) ),
			'layout'        => 'summary' === $meta( '_aimp_layout' ) ? 'summary' : 'full',
			// '' (older newsletters): each subscriber's own language.
			'lang'          => (string) $meta( '_aimp_nl_lang' ),
			'page'          => (int) $meta( '_aimp_page' ),
			'preheader'     => (string) $meta( '_aimp_preheader' ),
			'status'        => $meta( '_aimp_status' ) ? (string) $meta( '_aimp_status' ) : 'draft',
			'total'         => (int) $meta( '_aimp_total' ),
			'sent'          => (int) $meta( '_aimp_sent' ),
			'sent_at'       => (string) $meta( '_aimp_sent_at' ),
		);
	}

	/**
	 * @param mixed $value builder (rows and blocks), page or editor (simple text, the older newsletters).
	 * @return string
	 */
	private static function source( $value ) {
		return in_array( $value, array( 'builder', 'page' ), true ) ? $value : 'editor';
	}

	/**
	 * Saved rows (JSON) as clean rows.
	 *
	 * @param mixed $json JSON.
	 * @return array
	 */
	private static function rows_from( $json ) {
		$rows = is_string( $json ) && '' !== $json ? json_decode( $json, true ) : array();
		return class_exists( 'AIMP_Blog' ) && is_array( $rows ) ? AIMP_Blog::clean_rows( $rows ) : array();
	}

	/**
	 * The language the newsletter is written in (the composer's choice).
	 *
	 * @return string
	 */
	private static function posted_lang() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the callers.
		$lang = isset( $_POST['aimp_nl_lang'] ) ? sanitize_key( wp_unslash( $_POST['aimp_nl_lang'] ) ) : '';
		return class_exists( 'AIMP_I18n' ) && AIMP_I18n::is_valid( $lang ) ? $lang : '';
	}

	/**
	 * The language of a newsletter's email: the language it is written in, so its buttons, links and the band at the
	 * end match its text. Older newsletters without one: the subscriber's own language.
	 *
	 * @param array $nl            Newsletter.
	 * @param int   $subscriber_id Subscriber (0 = none).
	 * @return string
	 */
	private static function language( $nl, $subscriber_id = 0 ) {
		$lang = isset( $nl['lang'] ) ? (string) $nl['lang'] : '';
		if ( ! AIMP_I18n::is_valid( $lang ) && $subscriber_id ) {
			$lang = (string) get_post_meta( (int) $subscriber_id, '_aimp_lang', true );
		}
		return AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::default_language();
	}

	/**
	 * The newsletter's email as HTML, in its language (preview and web version).
	 *
	 * @param WC_Email $email Email.
	 * @param array    $nl    Newsletter.
	 * @return string
	 */
	private static function email_html( $email, $nl ) {
		return AIMP_I18n::with_language(
			self::language( $nl ),
			function () use ( $email ) {
				return AIMP_Emails::tidy_header( $email->style_inline( $email->get_content_html() ) );
			}
		);
	}

	/**
	 * The rows the builder posted (<!-- aimp-blog:base64 -->), made safe.
	 *
	 * @return array
	 */
	private static function posted_rows() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked by the callers; decoded and cleaned below.
		$raw     = isset( $_POST['aimp_nl_rows'] ) ? (string) wp_unslash( $_POST['aimp_nl_rows'] ) : '';
		$payload = class_exists( 'AIMP_Blog' ) ? AIMP_Blog::payload( $raw ) : null;
		return null !== $payload ? AIMP_Blog::clean_rows( $payload['rows'] ) : array();
	}

	/**
	 * The parts the email template needs, for one subscriber (or for a test/preview without one).
	 *
	 * @param array $nl            Newsletter.
	 * @param int   $subscriber_id Subscriber (0 = test).
	 * @return array
	 */
	private static function email_parts( $nl, $subscriber_id = 0 ) {
		$parts = array(
			'subject'     => $nl['subject'],
			'preheader'   => isset( $nl['preheader'] ) ? $nl['preheader'] : '',
			'content'     => $nl['content'],
			'image'       => '',
			'button_text' => $nl['button_text'],
			'button_url'  => $nl['button_url'],
			'attachment'  => $nl['attachment_id'] ? (string) get_attached_file( $nl['attachment_id'] ) : '',
			'unsubscribe' => $subscriber_id ? AIMP_Footer::unsubscribe_url( $subscriber_id ) : home_url( '/' ),
			// A test or preview (no subscriber): no unsubscribe headers.
			'test'        => ! $subscriber_id,
			'view_url'    => self::view_url( $nl ),
			'source'      => isset( $nl['source'] ) ? $nl['source'] : 'editor',
			'rows'        => isset( $nl['rows'] ) ? $nl['rows'] : array(),
			'layout'      => isset( $nl['layout'] ) ? $nl['layout'] : 'full',
		);
		// A page on the website (for example designed with Divi): intro, its picture and "Read the newsletter".
		$page = ( isset( $nl['source'], $nl['page'] ) && 'page' === $nl['source'] && $nl['page'] ) ? get_post( $nl['page'] ) : null;
		if ( $page && 'publish' === $page->post_status ) {
			$parts['image'] = (string) get_the_post_thumbnail_url( $page, 'large' );
			if ( '' === $parts['button_text'] || '' === $parts['button_url'] ) {
				$parts['button_text'] = __( 'Read the newsletter', 'atelier-irisee-master-plugin' );
				$parts['button_url']  = get_permalink( $page );
			}
		}
		return $parts;
	}

	/**
	 * "View in browser": the page itself, or the web version of a written newsletter.
	 *
	 * @param array $nl Newsletter.
	 * @return string
	 */
	public static function view_url( $nl ) {
		if ( isset( $nl['source'], $nl['page'] ) && 'page' === $nl['source'] && $nl['page'] ) {
			return (string) get_permalink( $nl['page'] );
		}
		if ( empty( $nl['id'] ) ) {
			return '';
		}
		return add_query_arg(
			array(
				'aimp_nl_view' => (int) $nl['id'],
				'key'          => substr( wp_hash( 'aimp_nl_view_' . (int) $nl['id'] ), 0, 16 ),
			),
			home_url( '/' )
		);
	}

	/**
	 * The web version of a written newsletter (/?aimp_nl_view=ID&key=…).
	 */
	public static function web_version() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the key is the proof.
		if ( empty( $_GET['aimp_nl_view'] ) ) {
			return;
		}
		$id  = absint( $_GET['aimp_nl_view'] );
		$key = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable
		$nl = self::get( $id );
		// A draft only for the shop (to try the "Read more" links of a preview).
		$draft = 'draft' === $nl['status'] && ! current_user_can( 'manage_woocommerce' );
		if ( ! $nl['id'] || $draft || ! hash_equals( substr( wp_hash( 'aimp_nl_view_' . $id ), 0, 16 ), $key ) ) {
			return;
		}
		$email = self::mailer_email();
		if ( ! $email ) {
			return;
		}
		$email->newsletter = self::email_parts( $nl );
		$email->newsletter['view_url'] = '';
		// On the website the whole newsletter, with its sections' anchors.
		$email->newsletter['layout'] = 'full';
		nocache_headers();
		// A copy of an email: not for search engines.
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		echo self::email_html( $email, $nl ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- email HTML built from escaped parts.
		exit;
	}

	private static function mailer_email() {
		$emails = WC()->mailer()->get_emails();
		return isset( $emails['AIMP_Email_Newsletter'] ) ? $emails['AIMP_Email_Newsletter'] : null;
	}

	/* ------------------------------------------------------------------
	 * Saving, test, sending
	 * ------------------------------------------------------------------ */

	/**
	 * Save (and test or send) the newsletter from the composer.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_newsletter_save' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$id = self::save_from_post();
		$do = isset( $_POST['aimp_nl_do'] ) ? sanitize_key( wp_unslash( $_POST['aimp_nl_do'] ) ) : 'save';
		$msg = 'nl_saved';

		if ( 'test' === $do ) {
			$email = self::mailer_email();
			$user  = wp_get_current_user();
			$saved = self::get( $id );
			$ok    = $email && AIMP_I18n::with_language(
				self::language( $saved ),
				function () use ( $email, $user, $saved ) {
					return $email->trigger( $user->user_email, self::email_parts( $saved ) );
				}
			);
			$msg   = $ok ? 'nl_test_sent' : 'nl_test_failed';
		} elseif ( 'send' === $do ) {
			$msg = self::start_sending( $id ) ? 'nl_sending' : 'nl_nobody';
		}
		wp_safe_redirect( add_query_arg( array( 'aimp_msg' => $msg, 'aimp_nl' => $id ), admin_url( 'admin.php?page=' . AIMP_Footer::ADMIN_PAGE ) ) );
		exit;
	}

	/**
	 * @return int Newsletter ID.
	 */
	private static function save_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in handle_save().
		$id      = isset( $_POST['aimp_nl_id'] ) ? absint( $_POST['aimp_nl_id'] ) : 0;
		$current = self::get( $id );
		$subject = isset( $_POST['aimp_nl_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_nl_subject'] ) ) : '';
		$content = isset( $_POST['aimp_nl_content'] ) ? wp_kses_post( wp_unslash( $_POST['aimp_nl_content'] ) ) : '';
		$fields  = array(
			'_aimp_button_text' => isset( $_POST['aimp_nl_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_nl_button_text'] ) ) : '',
			'_aimp_button_url'  => isset( $_POST['aimp_nl_button_url'] ) ? esc_url_raw( wp_unslash( $_POST['aimp_nl_button_url'] ) ) : '',
			'_aimp_attachment'  => isset( $_POST['aimp_nl_attachment'] ) ? absint( $_POST['aimp_nl_attachment'] ) : 0,
			'_aimp_source'      => self::source( isset( $_POST['aimp_nl_source'] ) ? sanitize_key( wp_unslash( $_POST['aimp_nl_source'] ) ) : '' ),
			'_aimp_page'        => isset( $_POST['aimp_nl_page'] ) ? absint( $_POST['aimp_nl_page'] ) : 0,
			'_aimp_preheader'   => isset( $_POST['aimp_nl_preheader'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_nl_preheader'] ) ) : '',
			'_aimp_rows'        => wp_slash( (string) wp_json_encode( self::posted_rows() ) ),
			'_aimp_layout'      => ( isset( $_POST['aimp_nl_layout'] ) && 'summary' === $_POST['aimp_nl_layout'] ) ? 'summary' : 'full',
			'_aimp_nl_lang'     => self::posted_lang(),
		);
		// phpcs:enable
		// A newsletter that was sent stays as it was; saving it again makes a new copy.
		if ( $current['id'] && 'draft' !== $current['status'] ) {
			$id = 0;
		}
		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'private',
			'post_title'   => '' !== $subject ? $subject : __( '(no subject)', 'atelier-irisee-master-plugin' ),
			'post_content' => $content,
		);
		if ( $id ) {
			$postarr['ID'] = $id;
			wp_update_post( $postarr );
		} else {
			$id = (int) wp_insert_post( $postarr );
			update_post_meta( $id, '_aimp_status', 'draft' );
		}
		foreach ( $fields as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * Queue the newsletter for every confirmed subscriber and start the first batch.
	 *
	 * @param int $id Newsletter.
	 * @return bool Queued.
	 */
	private static function start_sending( $id ) {
		$queue = AIMP_Footer::confirmed_subscriber_ids();
		if ( ! $queue ) {
			return false;
		}
		update_post_meta( $id, '_aimp_queue', $queue );
		update_post_meta( $id, '_aimp_total', count( $queue ) );
		update_post_meta( $id, '_aimp_sent', 0 );
		update_post_meta( $id, '_aimp_status', 'sending' );
		self::schedule( $id, 0 );
		return true;
	}

	private static function schedule( $id, $delay ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay, self::HOOK, array( 'newsletter' => (int) $id ), 'atelier-irisee' );
		} else {
			wp_schedule_single_event( time() + $delay, self::HOOK, array( (int) $id ) );
		}
	}

	/**
	 * One batch: up to BATCH emails, then the next batch a minute later.
	 *
	 * @param int $id Newsletter.
	 */
	public static function send_batch( $id ) {
		$id    = absint( $id );
		$nl    = self::get( $id );
		$queue = (array) get_post_meta( $id, '_aimp_queue', true );
		if ( ! $nl['id'] || 'sending' !== $nl['status'] ) {
			return;
		}
		$email = self::mailer_email();
		$sent  = $nl['sent'];
		$batch = array_splice( $queue, 0, self::BATCH );
		foreach ( $batch as $subscriber_id ) {
			$address = (string) get_post_field( 'post_title', (int) $subscriber_id, 'raw' );
			if ( ! $email || ! is_email( $address ) || 'confirmed' !== AIMP_Footer::status( (int) $subscriber_id ) ) {
				continue; // Unsubscribed in the meantime.
			}
			$lang = self::language( $nl, (int) $subscriber_id );
			$ok   = AIMP_I18n::with_language(
				$lang,
				function () use ( $email, $address, $nl, $subscriber_id ) {
					return $email->trigger( $address, self::email_parts( $nl, (int) $subscriber_id ) );
				}
			);
			if ( $ok ) {
				++$sent;
			}
		}
		update_post_meta( $id, '_aimp_queue', array_values( $queue ) );
		update_post_meta( $id, '_aimp_sent', $sent );
		if ( $queue ) {
			self::schedule( $id, MINUTE_IN_SECONDS );
		} else {
			update_post_meta( $id, '_aimp_status', 'sent' );
			update_post_meta( $id, '_aimp_sent_at', current_time( 'mysql' ) );
		}
	}

	/**
	 * Preview: the email as it will be sent, from the composer's current text (opens in a new tab).
	 */
	public static function handle_preview() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_newsletter_save' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$nl = array(
			'subject'       => isset( $_POST['aimp_nl_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_nl_subject'] ) ) : '',
			'content'       => isset( $_POST['aimp_nl_content'] ) ? wp_kses_post( wp_unslash( $_POST['aimp_nl_content'] ) ) : '',
			'button_text'   => isset( $_POST['aimp_nl_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_nl_button_text'] ) ) : '',
			'button_url'    => isset( $_POST['aimp_nl_button_url'] ) ? esc_url_raw( wp_unslash( $_POST['aimp_nl_button_url'] ) ) : '',
			'attachment_id' => 0,
			'source'        => self::source( isset( $_POST['aimp_nl_source'] ) ? sanitize_key( wp_unslash( $_POST['aimp_nl_source'] ) ) : '' ),
			'page'          => isset( $_POST['aimp_nl_page'] ) ? absint( $_POST['aimp_nl_page'] ) : 0,
			'preheader'     => isset( $_POST['aimp_nl_preheader'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_nl_preheader'] ) ) : '',
			'rows'          => self::posted_rows(),
			'layout'        => ( isset( $_POST['aimp_nl_layout'] ) && 'summary' === $_POST['aimp_nl_layout'] ) ? 'summary' : 'full',
			'lang'          => self::posted_lang(),
			// The saved newsletter (for the "Read more" links to its web version).
			'id'            => isset( $_POST['aimp_nl_id'] ) ? absint( $_POST['aimp_nl_id'] ) : 0,
		);
		// phpcs:enable
		$email = self::mailer_email();
		if ( ! $email ) {
			wp_die( esc_html__( 'The newsletter email is not available.', 'atelier-irisee-master-plugin' ) );
		}
		$email->newsletter = self::email_parts( $nl );
		echo self::email_html( $email, $nl ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- email HTML built from escaped parts.
		exit;
	}

	/* ------------------------------------------------------------------
	 * Composer (top of Atelier Irisee → Newsletter)
	 * ------------------------------------------------------------------ */

	private static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message after a redirect.
		$code     = isset( $_GET['aimp_msg'] ) ? sanitize_key( wp_unslash( $_GET['aimp_msg'] ) ) : '';
		$messages = array(
			'nl_saved'       => array( 'success', __( 'Newsletter saved.', 'atelier-irisee-master-plugin' ) ),
			/* translators: %s: email address of the person logged in */
			'nl_test_sent'   => array( 'success', sprintf( __( 'The test email has been sent to %s (the email address of your WordPress account).', 'atelier-irisee-master-plugin' ), wp_get_current_user()->user_email ) ),
			'nl_test_failed' => array( 'error', __( 'The test email could not be sent. Check that "Newsletter" is on under WooCommerce → Settings → Emails.', 'atelier-irisee-master-plugin' ) ),
			'nl_sending'     => array( 'success', __( 'The newsletter is being sent in the background, a few emails per minute. You can follow it in the list below.', 'atelier-irisee-master-plugin' ) ),
			'nl_nobody'      => array( 'error', __( 'There are no confirmed subscribers yet.', 'atelier-irisee-master-plugin' ) ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
		}
	}

	public static function render_composer() {
		self::notice();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- choosing what to show.
		$edit = isset( $_GET['aimp_nl'] ) ? absint( $_GET['aimp_nl'] ) : 0;
		$copy = isset( $_GET['aimp_nl_copy'] ) ? absint( $_GET['aimp_nl_copy'] ) : 0;
		// phpcs:enable
		$nl = self::get( $copy ? $copy : $edit );
		if ( $copy || 'draft' !== $nl['status'] ) {
			// A sent newsletter opens as a new copy.
			$nl['id']     = 0;
			$nl['status'] = 'draft';
		}
		$count      = count( AIMP_Footer::confirmed_subscriber_ids() );
		$attachment = $nl['attachment_id'] ? get_post( $nl['attachment_id'] ) : null;
		?>
		<div class="aimp-nl-composer">
			<h2><?php esc_html_e( 'Write a newsletter', 'atelier-irisee-master-plugin' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=aimp_newsletter_save' ) ); ?>" data-aimp-nl-form data-count="<?php echo (int) $count; ?>">
				<?php wp_nonce_field( 'aimp_newsletter_save' ); ?>
				<input type="hidden" name="aimp_nl_id" value="<?php echo (int) $nl['id']; ?>">
				<p>
					<label for="aimp_nl_subject"><strong><?php esc_html_e( 'Subject', 'atelier-irisee-master-plugin' ); ?></strong></label><br>
					<input type="text" id="aimp_nl_subject" name="aimp_nl_subject" class="large-text" value="<?php echo esc_attr( $nl['subject'] ); ?>" required>
				</p>
				<p>
					<label for="aimp_nl_preheader"><strong><?php esc_html_e( 'Preview text (optional)', 'atelier-irisee-master-plugin' ); ?></strong></label><br>
					<input type="text" id="aimp_nl_preheader" name="aimp_nl_preheader" class="large-text" value="<?php echo esc_attr( $nl['preheader'] ); ?>" placeholder="<?php esc_attr_e( 'The short line inboxes show after the subject', 'atelier-irisee-master-plugin' ); ?>">
				</p>
				<p>
					<label for="aimp_nl_lang"><strong><?php esc_html_e( 'Language of this newsletter', 'atelier-irisee-master-plugin' ); ?></strong></label><br>
					<select id="aimp_nl_lang" name="aimp_nl_lang">
						<?php if ( '' === $nl['lang'] ) : ?>
							<option value="" selected><?php esc_html_e( 'Each subscriber\'s own language', 'atelier-irisee-master-plugin' ); ?></option>
						<?php endif; ?>
						<?php foreach ( AIMP_I18n::languages() as $code => $language ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $nl['lang'], $code ); ?>><?php echo esc_html( $language['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="description"><?php esc_html_e( 'The buttons, links and "Let\'s connect" at the end follow the language you write in.', 'atelier-irisee-master-plugin' ); ?></span>
				</p>
				<fieldset class="aimp-nl-source">
					<legend><strong><?php esc_html_e( 'Content', 'atelier-irisee-master-plugin' ); ?></strong></legend>
					<label><input type="radio" name="aimp_nl_source" value="builder" <?php checked( 'builder' === $nl['source'] ); ?>> <?php esc_html_e( 'Build it here with rows and blocks (as a blog post)', 'atelier-irisee-master-plugin' ); ?></label>
					<label><input type="radio" name="aimp_nl_source" value="editor" <?php checked( 'editor' === $nl['source'] ); ?>> <?php esc_html_e( 'Simple text', 'atelier-irisee-master-plugin' ); ?></label>
					<label><input type="radio" name="aimp_nl_source" value="page" <?php checked( 'page' === $nl['source'] ); ?>> <?php esc_html_e( 'A page on the website (for example designed with Divi)', 'atelier-irisee-master-plugin' ); ?></label>
					<span class="aimp-nl-page">
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'aimp_nl_page',
								'id'                => 'aimp_nl_page',
								'selected'          => (int) $nl['page'],
								'show_option_none'  => __( '— Choose a page —', 'atelier-irisee-master-plugin' ),
								'option_none_value' => 0,
							)
						);
						?>
					</span>
					<p class="description"><?php esc_html_e( 'With a page, the email shows the text below as an introduction, the page\'s featured image and a "Read the newsletter" button. Email programs can\'t show a Divi design, so the full newsletter is read on the website.', 'atelier-irisee-master-plugin' ); ?></p>
				</fieldset>
				<div class="aimp-nl-builder" data-aimp-nl-for="builder"<?php echo 'builder' === $nl['source'] ? '' : ' hidden'; ?>>
					<fieldset class="aimp-nl-layout">
						<legend><strong><?php esc_html_e( 'The email shows', 'atelier-irisee-master-plugin' ); ?></strong></legend>
						<label><input type="radio" name="aimp_nl_layout" value="full" <?php checked( 'summary' !== $nl['layout'] ); ?>> <?php esc_html_e( 'The whole newsletter', 'atelier-irisee-master-plugin' ); ?></label>
						<label><input type="radio" name="aimp_nl_layout" value="summary" <?php checked( 'summary' === $nl['layout'] ); ?>> <?php esc_html_e( 'A summary per section (title, picture, short text and a "Read more" button to the newsletter on the website)', 'atelier-irisee-master-plugin' ); ?></label>
						<p class="description"><?php esc_html_e( 'Mark the start of each section with "+ New section" or the tick "A new section starts here" on a row. What comes before the first section is shown in full in both.', 'atelier-irisee-master-plugin' ); ?></p>
					</fieldset>
					<textarea name="aimp_nl_rows" id="aimp_nl_rows" hidden><?php echo esc_textarea( class_exists( 'AIMP_Blog' ) ? AIMP_Blog::encode( $nl['rows'], array() ) : '' ); ?></textarea>
					<div id="aimp-nl-builder" class="aimp-bb"></div>
				</div>
				<div class="aimp-nl-editor" data-aimp-nl-for="editor page"<?php echo 'builder' === $nl['source'] ? ' hidden' : ''; ?>>
				<?php
				wp_editor(
					$nl['content'],
					'aimp_nl_content',
					array(
						'textarea_name' => 'aimp_nl_content',
						'textarea_rows' => 14,
						'media_buttons' => true,
					)
				);
				?>
				</div>
				<div class="aimp-nl-extra">
					<p>
						<label for="aimp_nl_button_text"><strong><?php esc_html_e( 'Button (optional)', 'atelier-irisee-master-plugin' ); ?></strong></label><br>
						<input type="text" id="aimp_nl_button_text" name="aimp_nl_button_text" value="<?php echo esc_attr( $nl['button_text'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Discover the new fabrics', 'atelier-irisee-master-plugin' ); ?>">
						<input type="url" name="aimp_nl_button_url" value="<?php echo esc_attr( $nl['button_url'] ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( 'Button link', 'atelier-irisee-master-plugin' ); ?>">
					</p>
					<p>
						<strong><?php esc_html_e( 'Attachment (optional)', 'atelier-irisee-master-plugin' ); ?></strong><br>
						<input type="hidden" name="aimp_nl_attachment" value="<?php echo (int) $nl['attachment_id']; ?>" data-aimp-nl-attachment>
						<span class="aimp-nl-file" data-aimp-nl-file><?php echo $attachment ? esc_html( basename( (string) get_attached_file( $attachment->ID ) ) ) : esc_html__( 'No file', 'atelier-irisee-master-plugin' ); ?></span>
						<button type="button" class="button" data-aimp-nl-choose><?php esc_html_e( 'Choose a file', 'atelier-irisee-master-plugin' ); ?></button>
						<button type="button" class="button-link" data-aimp-nl-remove-file<?php echo $attachment ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'atelier-irisee-master-plugin' ); ?></button>
					</p>
				</div>
				<p class="aimp-nl-actions">
					<button type="submit" class="button" name="aimp_nl_do" value="save"><?php esc_html_e( 'Save draft', 'atelier-irisee-master-plugin' ); ?></button>
					<button type="submit" class="button" formaction="<?php echo esc_url( admin_url( 'admin-post.php?action=aimp_newsletter_preview' ) ); ?>" formtarget="_blank" name="aimp_nl_do" value="preview"><?php esc_html_e( 'Preview', 'atelier-irisee-master-plugin' ); ?></button>
					<button type="submit" class="button" name="aimp_nl_do" value="test"><?php
					/* translators: %s: email address of the person logged in */
					echo esc_html( sprintf( __( 'Send a test to me (%s)', 'atelier-irisee-master-plugin' ), wp_get_current_user()->user_email ) );
					?></button>
					<button type="submit" class="button button-primary" name="aimp_nl_do" value="send" data-aimp-nl-send<?php echo $count ? '' : ' disabled'; ?>>
						<?php
						/* translators: %d: number of confirmed subscribers */
						echo esc_html( sprintf( __( 'Send to all confirmed subscribers (%d)', 'atelier-irisee-master-plugin' ), $count ) );
						?>
					</button>
				</p>
			</form>
			<?php self::render_list(); ?>
		</div>
		<hr>
		<?php
	}

	private static function render_list() {
		$items = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 30,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		if ( ! $items ) {
			return;
		}
		$base = admin_url( 'admin.php?page=' . AIMP_Footer::ADMIN_PAGE );
		echo '<h3>' . esc_html__( 'Newsletters', 'atelier-irisee-master-plugin' ) . '</h3><table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html__( 'Subject', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Status', 'atelier-irisee-master-plugin' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $items as $id ) {
			$nl = self::get( $id );
			if ( 'sent' === $nl['status'] ) {
				/* translators: 1: number sent, 2: date */
				$status = sprintf( __( 'Sent to %1$d on %2$s', 'atelier-irisee-master-plugin' ), $nl['sent'], mysql2date( get_option( 'date_format' ), $nl['sent_at'] ) );
			} elseif ( 'sending' === $nl['status'] ) {
				/* translators: 1: number sent, 2: total */
				$status = sprintf( __( 'Sending: %1$d of %2$d', 'atelier-irisee-master-plugin' ), $nl['sent'], $nl['total'] );
			} else {
				$status = __( 'Draft', 'atelier-irisee-master-plugin' );
			}
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td><a href="%3$s">%4$s</a></td></tr>',
				esc_html( $nl['subject'] ),
				esc_html( $status ),
				esc_url( add_query_arg( 'draft' === $nl['status'] ? 'aimp_nl' : 'aimp_nl_copy', $id, $base ) ),
				esc_html( 'draft' === $nl['status'] ? __( 'Open', 'atelier-irisee-master-plugin' ) : __( 'Use as a copy', 'atelier-irisee-master-plugin' ) )
			);
		}
		echo '</tbody></table>';
	}
}
