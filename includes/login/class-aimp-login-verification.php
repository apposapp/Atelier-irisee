<?php
/**
 * Email verification (code or one-click link), admin approval, account status, email-change
 * confirmation and the emails that go with them.
 *
 * Status (user meta aimp_status): approved | unverified | pending | rejected.
 * Users without a status (everyone registered before this module) count as approved.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login_Verification {

	const STATUS        = 'aimp_status';
	const VERIFY        = 'aimp_verify';
	const PENDING_EMAIL = 'aimp_pending_email';
	const LANG          = 'aimp_lang';
	const CODE_TTL      = 30 * MINUTE_IN_SECONDS;
	const LINK_TTL      = 72 * HOUR_IN_SECONDS;
	const CODE_TRIES    = 5;

	public static function init() {
		add_filter( 'authenticate', array( __CLASS__, 'block_unapproved' ), 40, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_links' ) );

		// Users list.
		add_filter( 'manage_users_columns', array( __CLASS__, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_column_value' ), 10, 3 );
		add_filter( 'user_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-users', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_filter( 'views_users', array( __CLASS__, 'views' ) );
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users' ) );
		add_action( 'admin_post_aimp_el_user', array( __CLASS__, 'handle_row_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	/* ------------------------------------------------------------------
	 * Status
	 * ------------------------------------------------------------------ */

	public static function status( $user_id ) {
		$status = get_user_meta( $user_id, self::STATUS, true );
		return in_array( $status, array( 'unverified', 'pending', 'rejected' ), true ) ? $status : 'approved';
	}

	public static function set_status( $user_id, $status ) {
		update_user_meta( $user_id, self::STATUS, $status );
	}

	public static function status_label( $status ) {
		$labels = array(
			'approved'   => __( 'Approved', 'atelier-irisee-master-plugin' ),
			'unverified' => __( 'Email not verified', 'atelier-irisee-master-plugin' ),
			'pending'    => __( 'Awaiting approval', 'atelier-irisee-master-plugin' ),
			'rejected'   => __( 'Rejected', 'atelier-irisee-master-plugin' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['approved'];
	}

	/**
	 * Message shown when an account may not log in yet, or '' when it may.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function blocked_message( $user_id ) {
		switch ( self::status( $user_id ) ) {
			case 'unverified':
				return __( 'Please verify your email address first. Check your inbox (and spam folder).', 'atelier-irisee-master-plugin' );
			case 'pending':
				return __( 'Your account is waiting for approval. You will get an email as soon as it is approved.', 'atelier-irisee-master-plugin' );
			case 'rejected':
				return __( 'Your account has not been approved.', 'atelier-irisee-master-plugin' );
		}
		return '';
	}

	/**
	 * Block login for accounts that are not approved (also on wp-login.php and the WooCommerce login).
	 *
	 * @param WP_User|WP_Error|null $user Result so far.
	 * @return WP_User|WP_Error|null
	 */
	public static function block_unapproved( $user ) {
		if ( ! $user instanceof WP_User || user_can( $user, 'manage_options' ) ) {
			return $user;
		}
		$message = self::blocked_message( $user->ID );
		if ( $message ) {
			return new WP_Error( 'aimp_' . self::status( $user->ID ), $message );
		}
		return $user;
	}

	/* ------------------------------------------------------------------
	 * Starting verification after registration
	 * ------------------------------------------------------------------ */

	/**
	 * Decide what happens with a new account.
	 *
	 * @param int    $user_id User.
	 * @param string $source  'form', 'social_verified' (provider confirmed the email) or 'social_unverified'.
	 * @return array [ result => login|verify|message, message => text ]
	 */
	public static function start( $user_id, $source = 'form' ) {
		$mode = AIMP_Login::opt( 'verify_mode' );
		if ( 'social_verified' === $source ) {
			$mode = 'none';
		} elseif ( 'social_unverified' === $source && 'none' === $mode ) {
			// Nobody confirmed this email address yet: always ask for confirmation.
			$mode = 'link';
		}

		if ( 'code' === $mode ) {
			self::set_status( $user_id, 'unverified' );
			self::send_code( $user_id );
			return array(
				'result'  => 'verify',
				'message' => __( 'We sent a 6-digit code to your email address. Enter it below to activate your account.', 'atelier-irisee-master-plugin' ),
			);
		}
		if ( 'link' === $mode ) {
			self::set_status( $user_id, 'unverified' );
			self::send_link( $user_id );
			return array(
				'result'  => 'message',
				'message' => __( 'Almost done! We sent you an email with a link to confirm your email address.', 'atelier-irisee-master-plugin' ),
			);
		}
		return self::after_verified( $user_id );
	}

	/**
	 * Email confirmed (or no verification needed): approve, or wait for an administrator.
	 *
	 * @param int $user_id User.
	 * @return array
	 */
	public static function after_verified( $user_id ) {
		delete_user_meta( $user_id, self::VERIFY );
		if ( AIMP_Login::opt( 'admin_approval' ) ) {
			self::set_status( $user_id, 'pending' );
			self::send( 'pending', $user_id );
			self::send( 'admin_pending', $user_id );
			return array(
				'result'  => 'message',
				'message' => __( 'Thank you! An administrator will review your account. You will get an email as soon as it is approved.', 'atelier-irisee-master-plugin' ),
			);
		}
		self::set_status( $user_id, 'approved' );
		return array(
			'result'  => 'login',
			'message' => '',
		);
	}

	private static function hash( $secret ) {
		return hash_hmac( 'sha256', (string) $secret, wp_salt( 'auth' ) );
	}

	public static function send_code( $user_id ) {
		$code = sprintf( '%06d', random_int( 0, 999999 ) );
		update_user_meta(
			$user_id,
			self::VERIFY,
			array(
				'type'    => 'code',
				'hash'    => self::hash( $code ),
				'expires' => time() + self::CODE_TTL,
				'tries'   => 0,
			)
		);
		self::send( 'verify_code', $user_id, array( '{code}' => $code ) );
	}

	public static function send_link( $user_id ) {
		$token = wp_generate_password( 32, false, false );
		update_user_meta(
			$user_id,
			self::VERIFY,
			array(
				'type'    => 'link',
				'hash'    => self::hash( $token ),
				'expires' => time() + self::LINK_TTL,
			)
		);
		$link = add_query_arg(
			array(
				'aimp_el_verify' => $token,
				'u'              => $user_id,
			),
			home_url( '/' )
		);
		self::send( 'verify_link', $user_id, array( '{link}' => $link ) );
	}

	/**
	 * Send the verification again (code or link, whichever the account uses).
	 *
	 * @param int $user_id User.
	 */
	public static function resend( $user_id ) {
		if ( 'unverified' !== self::status( $user_id ) ) {
			return;
		}
		$data = get_user_meta( $user_id, self::VERIFY, true );
		if ( is_array( $data ) && 'link' === $data['type'] ) {
			self::send_link( $user_id );
		} elseif ( 'link' === AIMP_Login::opt( 'verify_mode' ) && ! is_array( $data ) ) {
			self::send_link( $user_id );
		} else {
			self::send_code( $user_id );
		}
	}

	/**
	 * Check a verification code.
	 *
	 * @param WP_User $user User.
	 * @param string  $code Entered code.
	 * @return true|WP_Error
	 */
	public static function verify_code( $user, $code ) {
		$data = get_user_meta( $user->ID, self::VERIFY, true );
		if ( 'unverified' !== self::status( $user->ID ) || ! is_array( $data ) || 'code' !== $data['type'] ) {
			return new WP_Error( 'aimp_code', __( 'This code is not valid. Request a new code.', 'atelier-irisee-master-plugin' ) );
		}
		if ( $data['expires'] < time() || $data['tries'] >= self::CODE_TRIES ) {
			return new WP_Error( 'aimp_code', __( 'This code has expired. Request a new code.', 'atelier-irisee-master-plugin' ) );
		}
		if ( ! hash_equals( $data['hash'], self::hash( preg_replace( '/\D/', '', $code ) ) ) ) {
			$data['tries']++;
			update_user_meta( $user->ID, self::VERIFY, $data );
			return new WP_Error( 'aimp_code', __( 'This code is not correct. Please check it and try again.', 'atelier-irisee-master-plugin' ) );
		}
		return true;
	}

	/* ------------------------------------------------------------------
	 * Links from emails (verification and email change)
	 * ------------------------------------------------------------------ */

	public static function handle_links() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token in the link is the proof.
		if ( isset( $_GET['aimp_el_verify'], $_GET['u'] ) ) {
			self::handle_verify_link( absint( $_GET['u'] ), sanitize_text_field( wp_unslash( $_GET['aimp_el_verify'] ) ) );
		}
		if ( isset( $_GET['aimp_el_email'], $_GET['u'] ) ) {
			self::handle_email_link( absint( $_GET['u'] ), sanitize_text_field( wp_unslash( $_GET['aimp_el_email'] ) ) );
		}
		// phpcs:enable
	}

	private static function message_redirect( $message ) {
		wp_safe_redirect( add_query_arg( 'aimp_el_msg', $message, home_url( '/' ) ) );
		exit;
	}

	private static function handle_verify_link( $user_id, $token ) {
		$data = get_user_meta( $user_id, self::VERIFY, true );
		if ( ! $user_id || 'unverified' !== self::status( $user_id ) || ! is_array( $data ) || 'link' !== $data['type'] || $data['expires'] < time() || ! hash_equals( $data['hash'], self::hash( $token ) ) ) {
			self::message_redirect( 'invalid' );
		}
		$result = self::after_verified( $user_id );
		if ( 'login' !== $result['result'] ) {
			self::message_redirect( 'verified_wait' );
		}
		// Clicking the link proves the email address: log the customer in and show My Account.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		if ( function_exists( 'wc_add_notice' ) && WC()->session ) {
			WC()->session->set_customer_session_cookie( true );
			wc_add_notice( __( 'Your email address is verified. Welcome!', 'atelier-irisee-master-plugin' ) );
		}
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/**
	 * Start an email change: the new address must be confirmed first.
	 *
	 * @param int    $user_id User.
	 * @param string $email   New email address.
	 */
	public static function request_email_change( $user_id, $email ) {
		$token = wp_generate_password( 32, false, false );
		update_user_meta(
			$user_id,
			self::PENDING_EMAIL,
			array(
				'email'   => $email,
				'hash'    => self::hash( $token ),
				'expires' => time() + self::LINK_TTL,
			)
		);
		$link = add_query_arg(
			array(
				'aimp_el_email' => $token,
				'u'             => $user_id,
			),
			home_url( '/' )
		);
		self::send( 'email_change', $user_id, array( '{link}' => $link ), $email );
	}

	private static function handle_email_link( $user_id, $token ) {
		$data = get_user_meta( $user_id, self::PENDING_EMAIL, true );
		if ( ! $user_id || ! is_array( $data ) || $data['expires'] < time() || ! hash_equals( $data['hash'], self::hash( $token ) ) ) {
			self::message_redirect( 'invalid' );
		}
		delete_user_meta( $user_id, self::PENDING_EMAIL );
		$owner = email_exists( $data['email'] );
		if ( $owner && (int) $owner !== $user_id ) {
			self::message_redirect( 'invalid' );
		}
		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $data['email'],
			)
		);
		update_user_meta( $user_id, 'billing_email', $data['email'] );
		self::message_redirect( 'email_changed' );
	}

	/* ------------------------------------------------------------------
	 * Emails
	 * ------------------------------------------------------------------ */

	/**
	 * Built-in subject and text of an email (in the active language).
	 *
	 * @param string $type Email type.
	 * @return array [ subject, body ]
	 */
	public static function default_email( $type ) {
		$emails = array(
			'verify_code'   => array(
				__( 'Your verification code for {site_name}', 'atelier-irisee-master-plugin' ),
				__( "Hello {display_name},\n\nYour verification code is: {code}\n\nThe code is valid for 30 minutes.", 'atelier-irisee-master-plugin' ),
			),
			'verify_link'   => array(
				__( 'Confirm your email address for {site_name}', 'atelier-irisee-master-plugin' ),
				__( "Hello {display_name},\n\nPlease confirm your email address by clicking this link:\n{link}\n\nThe link is valid for 72 hours.", 'atelier-irisee-master-plugin' ),
			),
			'pending'       => array(
				__( 'Your account at {site_name} is awaiting approval', 'atelier-irisee-master-plugin' ),
				__( "Hello {display_name},\n\nThank you for registering. An administrator will review your account. You will receive an email as soon as it is approved.", 'atelier-irisee-master-plugin' ),
			),
			'approved'      => array(
				__( 'Your account at {site_name} has been approved', 'atelier-irisee-master-plugin' ),
				__( "Hello {display_name},\n\nGood news: your account has been approved. You can log in here:\n{login_url}", 'atelier-irisee-master-plugin' ),
			),
			'rejected'      => array(
				__( 'Your account at {site_name}', 'atelier-irisee-master-plugin' ),
				__( "Hello {display_name},\n\nUnfortunately your account could not be approved. If you have any questions, simply reply to this email.", 'atelier-irisee-master-plugin' ),
			),
			'admin_pending' => array(
				__( 'New account awaiting approval: {display_name}', 'atelier-irisee-master-plugin' ),
				__( "A new customer registered and is waiting for approval:\n\n{display_name} ({email})\n\nReview the account here:\n{admin_url}", 'atelier-irisee-master-plugin' ),
			),
			'email_change'  => array(
				__( 'Confirm your new email address for {site_name}', 'atelier-irisee-master-plugin' ),
				__( "Hello {display_name},\n\nPlease confirm your new email address by clicking this link:\n{link}\n\nIf you did not ask for this change, you can ignore this email.", 'atelier-irisee-master-plugin' ),
			),
		);
		return isset( $emails[ $type ] ) ? $emails[ $type ] : array( '', '' );
	}

	/**
	 * Send an email to a user (or, for admin_* types, to the site admin) in their language.
	 *
	 * @param string $type    Email type.
	 * @param int    $user_id User the email is about.
	 * @param array  $vars    Extra placeholders.
	 * @param string $to      Override recipient.
	 */
	public static function send( $type, $user_id, $vars = array(), $to = '' ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$to_admin = 0 === strpos( $type, 'admin_' );
		$lang     = $to_admin ? AIMP_I18n::default_language() : get_user_meta( $user_id, self::LANG, true );
		$lang     = AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::default_language();
		$to       = $to ? $to : ( $to_admin ? get_option( 'admin_email' ) : $user->user_email );

		AIMP_I18n::with_language(
			$lang,
			function () use ( $type, $user, $vars, $to ) {
				list( $subject, $body ) = AIMP_Login_Verification::default_email( $type );
				$custom_subject         = (string) AIMP_Login::opt( 'email_' . $type . '_subject' );
				$custom_body            = (string) AIMP_Login::opt( 'email_' . $type . '_body' );
				$subject                = '' !== trim( $custom_subject ) ? $custom_subject : $subject;
				$body                   = '' !== trim( $custom_body ) ? $custom_body : $body;

				$replace = array_merge(
					array(
						'{display_name}' => $user->display_name,
						'{email}'        => $user->user_email,
						'{site_name}'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
						'{login_url}'    => wc_get_page_permalink( 'myaccount' ),
						'{admin_url}'    => admin_url( 'users.php?aimp_status=pending' ),
						'{code}'         => '',
						'{link}'         => '',
					),
					$vars
				);
				$subject = strtr( $subject, $replace );
				$html    = wpautop( make_clickable( esc_html( strtr( $body, $replace ) ) ) );

				if ( function_exists( 'WC' ) && WC()->mailer() ) {
					$mailer = WC()->mailer();
					$mailer->send( $to, $subject, $mailer->wrap_message( $subject, $html ), array( 'Content-Type: text/html; charset=UTF-8' ) );
				} else {
					wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
				}
			}
		);
	}

	/* ------------------------------------------------------------------
	 * Users list (admin)
	 * ------------------------------------------------------------------ */

	public static function users_column( $columns ) {
		$columns['aimp_status'] = __( 'Account status', 'atelier-irisee-master-plugin' );
		return $columns;
	}

	public static function users_column_value( $value, $column, $user_id ) {
		if ( 'aimp_status' !== $column ) {
			return $value;
		}
		$status = self::status( $user_id );
		return '<span class="aimp-status aimp-status--' . esc_attr( $status ) . '">' . esc_html( self::status_label( $status ) ) . '</span>';
	}

	private static function action_url( $do, $user_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'aimp_el_user',
					'do'     => $do,
					'user'   => $user_id,
				),
				admin_url( 'admin-post.php' )
			),
			'aimp_el_user_' . $do . '_' . $user_id
		);
	}

	public static function row_actions( $actions, $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) || user_can( $user, 'manage_options' ) ) {
			return $actions;
		}
		$status = self::status( $user->ID );
		if ( 'approved' !== $status ) {
			$actions['aimp_approve'] = '<a href="' . esc_url( self::action_url( 'approve', $user->ID ) ) . '">' . esc_html__( 'Approve', 'atelier-irisee-master-plugin' ) . '</a>';
		}
		if ( 'rejected' !== $status ) {
			$actions['aimp_reject'] = '<a href="' . esc_url( self::action_url( 'reject', $user->ID ) ) . '">' . esc_html__( 'Reject', 'atelier-irisee-master-plugin' ) . '</a>';
		}
		if ( 'unverified' === $status ) {
			$actions['aimp_resend'] = '<a href="' . esc_url( self::action_url( 'resend', $user->ID ) ) . '">' . esc_html__( 'Resend verification', 'atelier-irisee-master-plugin' ) . '</a>';
		}
		return $actions;
	}

	private static function apply_action( $do, $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || user_can( $user_id, 'manage_options' ) ) {
			return false;
		}
		switch ( $do ) {
			case 'approve':
				if ( 'approved' !== self::status( $user_id ) ) {
					delete_user_meta( $user_id, self::VERIFY );
					self::set_status( $user_id, 'approved' );
					self::send( 'approved', $user_id );
				}
				return true;
			case 'reject':
				if ( 'rejected' !== self::status( $user_id ) ) {
					self::set_status( $user_id, 'rejected' );
					self::send( 'rejected', $user_id );
				}
				return true;
			case 'resend':
				self::resend( $user_id );
				return true;
		}
		return false;
	}

	public static function handle_row_action() {
		$do      = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		check_admin_referer( 'aimp_el_user_' . $do . '_' . $user_id );
		$done = self::apply_action( $do, $user_id );
		wp_safe_redirect( add_query_arg( 'aimp_done', $done ? $do : 'none', admin_url( 'users.php' ) ) );
		exit;
	}

	public static function bulk_actions( $actions ) {
		$actions['aimp_approve'] = __( 'Approve accounts', 'atelier-irisee-master-plugin' );
		$actions['aimp_reject']  = __( 'Reject accounts', 'atelier-irisee-master-plugin' );
		return $actions;
	}

	public static function handle_bulk( $redirect, $action, $user_ids ) {
		if ( ! in_array( $action, array( 'aimp_approve', 'aimp_reject' ), true ) ) {
			return $redirect;
		}
		$do = 'aimp_approve' === $action ? 'approve' : 'reject';
		foreach ( (array) $user_ids as $user_id ) {
			self::apply_action( $do, (int) $user_id );
		}
		return add_query_arg( 'aimp_done', $do, $redirect );
	}

	public static function views( $views ) {
		$url = admin_url( 'users.php' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$current = isset( $_GET['aimp_status'] ) ? sanitize_key( wp_unslash( $_GET['aimp_status'] ) ) : '';
		foreach ( array( 'pending', 'unverified', 'rejected' ) as $status ) {
			$count = count(
				get_users(
					array(
						'meta_key'   => self::STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => $status, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'fields'     => 'ID',
						'number'     => 999,
					)
				)
			);
			if ( ! $count ) {
				continue;
			}
			$views[ 'aimp_' . $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'aimp_status', $status, $url ) ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( self::status_label( $status ) ),
				$count
			);
		}
		return $views;
	}

	public static function filter_users( $query ) {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$status = isset( $_GET['aimp_status'] ) ? sanitize_key( wp_unslash( $_GET['aimp_status'] ) ) : '';
		if ( $screen && 'users' === $screen->id && in_array( $status, array( 'pending', 'unverified', 'rejected' ), true ) ) {
			$query->set( 'meta_key', self::STATUS );
			$query->set( 'meta_value', $status );
		}
	}

	public static function admin_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$done = isset( $_GET['aimp_done'] ) ? sanitize_key( wp_unslash( $_GET['aimp_done'] ) ) : '';
		$text = array(
			'approve' => __( 'Account(s) approved and the customer was emailed.', 'atelier-irisee-master-plugin' ),
			'reject'  => __( 'Account(s) rejected and the customer was emailed.', 'atelier-irisee-master-plugin' ),
			'resend'  => __( 'The verification email was sent again.', 'atelier-irisee-master-plugin' ),
		);
		if ( isset( $text[ $done ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text[ $done ] ) . '</p></div>';
		}
	}
}
