<?php
/**
 * AJAX endpoints of the login & registration forms (?wc-ajax=aimp_el_*).
 *
 * Responses: success { redirect?, message?, next?, ... } or error { errors: { field|_form: message } }.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login_Ajax {

	public static function init() {
		$actions = array( 'nonce', 'login', 'register', 'lostpw', 'resetpw', 'check_email', 'verify_code', 'resend', 'profile' );
		foreach ( $actions as $action ) {
			add_action( 'wc_ajax_aimp_el_' . $action, array( __CLASS__, $action ) );
		}
		add_filter( 'retrieve_password_message', array( __CLASS__, 'reset_email' ), 20, 4 );
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Send errors and stop.
	 *
	 * @param array|WP_Error|string $errors Errors (field => message), a WP_Error or one form message.
	 * @param array                 $extra  Extra response data.
	 */
	private static function fail( $errors, $extra = array() ) {
		if ( is_wp_error( $errors ) ) {
			$list = array();
			foreach ( $errors->get_error_codes() as $code ) {
				$list[ $code ] = $errors->get_error_message( $code );
			}
			$errors = $list;
		} elseif ( ! is_array( $errors ) ) {
			$errors = array( '_form' => (string) $errors );
		}
		wp_send_json_error( array_merge( array( 'errors' => $errors ), $extra ) );
	}

	private static function check_nonce() {
		if ( ! check_ajax_referer( AIMP_Login::NONCE, 'nonce', false ) ) {
			self::fail( __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ) );
		}
	}

	/**
	 * A form value from aimp[...] (text, sanitized).
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function field( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every endpoint checks the nonce first.
		$value = isset( $_POST['aimp'][ $key ] ) ? wp_unslash( $_POST['aimp'][ $key ] ) : '';
		return is_array( $value ) ? '' : sanitize_text_field( $value );
	}

	/**
	 * A password from aimp[...]: never sanitized (that would change it), only unslashed.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function password( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords must stay exactly as typed.
		$value = isset( $_POST['aimp'][ $key ] ) ? wp_unslash( $_POST['aimp'][ $key ] ) : '';
		return is_string( $value ) ? $value : '';
	}

	private static function request( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every endpoint checks the nonce first.
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	private static function request_url( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every endpoint checks the nonce first.
		return isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	private static function guard( $form, $limit_key = '' ) {
		if ( AIMP_Login_Security::honeypot_triggered() ) {
			self::fail( __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ) );
		}
		if ( $limit_key && AIMP_Login_Security::rate_limited( $form, $limit_key, (int) AIMP_Login::opt( 'rate_limit' ) ) ) {
			self::fail( __( 'Too many attempts. Please try again later.', 'atelier-irisee-master-plugin' ) );
		}
		$captcha = AIMP_Login_Security::verify_captcha( $form );
		if ( $captcha ) {
			self::fail( array( '_form' => $captcha ), array( 'resetCaptcha' => true ) );
		}
	}

	/**
	 * Log a user in and answer with the redirect.
	 *
	 * @param int    $user_id User.
	 * @param string $type    'login' or 'register' (which redirect setting to use).
	 * @param bool   $remember Remember me.
	 */
	public static function login_and_respond( $user_id, $type, $remember = true ) {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, $remember, is_ssl() );
		do_action( 'aimp_el_login_success', get_userdata( $user_id ) );
		wp_send_json_success(
			array(
				'redirect' => AIMP_Login::redirect_url( $type, self::request_url( 'redirect' ), self::request_url( 'current' ), $user_id ),
			)
		);
	}

	/**
	 * Create a customer account (through WooCommerce when available, so its hooks and emails run).
	 *
	 * @param string $email    Email.
	 * @param string $username Username ('' = generate from the email).
	 * @param string $password Password.
	 * @param string $first    First name.
	 * @param string $last     Last name.
	 * @return int|WP_Error
	 */
	public static function create_user( $email, $username, $password, $first, $last ) {
		$names = array(
			'first_name' => $first,
			'last_name'  => $last,
		);
		if ( function_exists( 'wc_create_new_customer' ) ) {
			if ( '' === $username ) {
				$username = wc_create_new_customer_username( $email, $names );
			}
			$user_id = wc_create_new_customer( $email, $username, $password, $names );
		} else {
			if ( '' === $username ) {
				$username = sanitize_user( current( explode( '@', $email ) ), true );
				$base     = $username ? $username : 'user';
				$i        = 1;
				while ( username_exists( $username ) || '' === $username ) {
					$username = $base . $i++;
				}
			}
			$user_id = wp_insert_user(
				array_merge(
					$names,
					array(
						'user_login' => $username,
						'user_email' => $email,
						'user_pass'  => $password,
						'role'       => get_option( 'default_role', 'subscriber' ),
					)
				)
			);
		}
		if ( ! is_wp_error( $user_id ) ) {
			update_user_meta( $user_id, AIMP_Login_Verification::LANG, AIMP_I18n::current() );
			if ( $first || $last ) {
				wp_update_user(
					array(
						'ID'           => $user_id,
						'display_name' => trim( $first . ' ' . $last ),
					)
				);
			}
		}
		return $user_id;
	}

	/* ------------------------------------------------------------------
	 * Endpoints
	 * ------------------------------------------------------------------ */

	/**
	 * Fresh nonce, so forms keep working on cached pages.
	 */
	public static function nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( AIMP_Login::NONCE ) ) );
	}

	public static function login() {
		self::check_nonce();
		self::guard( 'login' );

		$login    = self::field( 'username' );
		$password = self::password( 'password' );
		$errors   = array();
		if ( '' === $login ) {
			$errors['username'] = __( 'Please enter your email address or username.', 'atelier-irisee-master-plugin' );
		}
		if ( '' === $password ) {
			$errors['password'] = __( 'Please enter your password.', 'atelier-irisee-master-plugin' );
		}
		if ( $errors ) {
			self::fail( $errors );
		}

		$remember = '1' === self::field( 'remember' );
		$user     = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$code = $user->get_error_code();
			if ( 'aimp_unverified' === $code ) {
				$found = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
				self::fail(
					array( '_form' => $user->get_error_message() ),
					array(
						'unverified' => true,
						'email'      => $found ? $found->user_email : '',
						'codeMode'   => 'code' === AIMP_Login::opt( 'verify_mode' ),
					)
				);
			}
			if ( in_array( $code, array( 'aimp_pending', 'aimp_rejected', 'aimp_locked' ), true ) ) {
				self::fail( $user->get_error_message() );
			}
			// One generic message: never reveal whether the account exists.
			self::fail( __( 'The email address, username or password is incorrect.', 'atelier-irisee-master-plugin' ), array( 'resetCaptcha' => true ) );
		}

		self::login_and_respond( $user->ID, 'login', $remember );
	}

	public static function register() {
		self::check_nonce();
		if ( ! AIMP_Login::registration_enabled() ) {
			self::fail( __( 'Registration is currently not possible.', 'atelier-irisee-master-plugin' ) );
		}
		self::guard( 'register', AIMP_Login_Security::ip() );

		$errors = new WP_Error();
		$email  = sanitize_email( self::field( 'email' ) );
		if ( ! is_email( $email ) ) {
			$errors->add( 'email', __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ) );
		} elseif ( email_exists( $email ) ) {
			$errors->add( 'email', __( 'An account with this email address already exists. Please log in.', 'atelier-irisee-master-plugin' ) );
		}

		$username = '';
		if ( 'field' === AIMP_Login::opt( 'username_mode' ) ) {
			$username = sanitize_user( self::field( 'username' ), true );
			if ( '' === $username || ! validate_username( $username ) ) {
				$errors->add( 'username', __( 'Please enter a valid username (letters, numbers, - _ . @).', 'atelier-irisee-master-plugin' ) );
			} elseif ( username_exists( $username ) ) {
				$errors->add( 'username', __( 'This username is already taken.', 'atelier-irisee-master-plugin' ) );
			}
		}

		$first = '';
		$last  = '';
		if ( AIMP_Login::opt( 'show_names' ) ) {
			$first = self::field( 'first_name' );
			$last  = self::field( 'last_name' );
			if ( '' === $first ) {
				$errors->add( 'first_name', __( 'Please enter your first name.', 'atelier-irisee-master-plugin' ) );
			}
			if ( '' === $last ) {
				$errors->add( 'last_name', __( 'Please enter your last name.', 'atelier-irisee-master-plugin' ) );
			}
		}

		$password = self::password( 'password' );
		if ( '' === $password ) {
			$errors->add( 'password', __( 'Please choose a password.', 'atelier-irisee-master-plugin' ) );
		} else {
			$weak = AIMP_Login_Security::password_problem( $password );
			if ( $weak ) {
				$errors->add( 'password', $weak );
			} elseif ( self::password( 'password2' ) !== $password ) {
				$errors->add( 'password2', __( 'The passwords do not match.', 'atelier-irisee-master-plugin' ) );
			}
		}

		if ( AIMP_Login::opt( 'terms_page' ) && '1' !== self::field( 'terms' ) ) {
			$errors->add( 'terms', __( 'Please accept the terms and conditions.', 'atelier-irisee-master-plugin' ) );
		}

		$values = AIMP_Login_Fields::collect( 'register', 0, $errors );
		if ( $errors->has_errors() ) {
			self::fail( $errors );
		}

		$user_id = self::create_user( $email, $username, $password, $first, $last );
		if ( is_wp_error( $user_id ) ) {
			self::fail( wp_strip_all_tags( $user_id->get_error_message() ) );
		}

		AIMP_Login_Fields::save( $user_id, $values, 'register' );
		if ( AIMP_Login::opt( 'terms_page' ) ) {
			update_user_meta( $user_id, 'aimp_terms_accepted', gmdate( 'Y-m-d H:i:s' ) );
		}
		do_action( 'aimp_el_register_user', $user_id );

		$result = AIMP_Login_Verification::start( $user_id, 'form' );
		if ( 'login' === $result['result'] ) {
			self::login_and_respond( $user_id, 'register' );
		}
		wp_send_json_success(
			array(
				'next'    => 'verify' === $result['result'] ? 'verify' : 'message',
				'email'   => $email,
				'message' => $result['message'],
			)
		);
	}

	public static function lostpw() {
		self::check_nonce();
		$login = self::field( 'username' );
		self::guard( 'lostpw', AIMP_Login_Security::ip() );
		if ( '' === $login ) {
			self::fail( array( 'username' => __( 'Please enter your email address or username.', 'atelier-irisee-master-plugin' ) ) );
		}
		if ( ! AIMP_Login_Security::rate_limited( 'lostpw_account', $login, 3 ) ) {
			$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
			if ( $user ) {
				retrieve_password( $user->user_login );
			}
		}
		// Same answer whether or not the account exists.
		wp_send_json_success(
			array(
				'message' => __( 'If an account exists for this email address or username, we sent you a link to choose a new password.', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	/**
	 * Password reset emails link to the popup instead of wp-login.php.
	 *
	 * @param string  $message    Default message.
	 * @param string  $key        Reset key.
	 * @param string  $user_login Login.
	 * @param WP_User $user       User.
	 * @return string
	 */
	public static function reset_email( $message, $key, $user_login, $user ) {
		if ( ! AIMP_Login::enabled() ) {
			return $message;
		}
		$link = add_query_arg(
			array(
				'aimp_el' => 'resetpw',
				'key'     => $key,
				'login'   => rawurlencode( $user_login ),
			),
			home_url( '/' )
		);
		$lang = $user instanceof WP_User ? get_user_meta( $user->ID, AIMP_Login_Verification::LANG, true ) : '';
		$lang = AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::current();
		return AIMP_I18n::with_language(
			$lang,
			function () use ( $user_login, $link ) {
				return sprintf(
					/* translators: 1: site name, 2: username, 3: reset link */
					__( "Someone asked to reset the password of your account at %1\$s (%2\$s).\n\nIf this was a mistake, simply ignore this email and nothing will happen.\n\nTo choose a new password, open this link:\n%3\$s", 'atelier-irisee-master-plugin' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					$user_login,
					$link
				);
			}
		);
	}

	public static function resetpw() {
		self::check_nonce();
		$key   = self::request( 'key' );
		$login = self::request( 'login' );
		$user  = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			self::fail( __( 'This link is invalid or has expired. Please request a new one.', 'atelier-irisee-master-plugin' ) );
		}

		$password = self::password( 'password' );
		$problem  = '' === $password ? __( 'Please choose a password.', 'atelier-irisee-master-plugin' ) : AIMP_Login_Security::password_problem( $password );
		if ( $problem ) {
			self::fail( array( 'password' => $problem ) );
		}
		if ( self::password( 'password2' ) !== $password ) {
			self::fail( array( 'password2' => __( 'The passwords do not match.', 'atelier-irisee-master-plugin' ) ) );
		}

		reset_password( $user, $password );
		// Log out every other device that may still be using the old password.
		WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		delete_user_meta( $user->ID, AIMP_Login_Social::SOCIAL_ONLY );

		$blocked = user_can( $user, 'manage_options' ) ? '' : AIMP_Login_Verification::blocked_message( $user->ID );
		if ( $blocked ) {
			wp_send_json_success(
				array(
					'next'    => 'message',
					'message' => __( 'Your password has been changed.', 'atelier-irisee-master-plugin' ) . ' ' . $blocked,
				)
			);
		}
		self::login_and_respond( $user->ID, 'login' );
	}

	public static function check_email() {
		self::check_nonce();
		self::guard( 'check_email' );
		if ( AIMP_Login_Security::rate_limited( 'check_email', AIMP_Login_Security::ip(), 30 ) ) {
			self::fail( __( 'Too many attempts. Please try again later.', 'atelier-irisee-master-plugin' ) );
		}
		$email = sanitize_email( self::field( 'email' ) );
		if ( ! is_email( $email ) ) {
			self::fail( array( 'email' => __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ) ) );
		}
		wp_send_json_success(
			array(
				'exists'   => (bool) email_exists( $email ),
				'register' => AIMP_Login::registration_enabled(),
				'email'    => $email,
			)
		);
	}

	public static function verify_code() {
		self::check_nonce();
		$email = sanitize_email( self::request( 'email' ) );
		if ( AIMP_Login_Security::rate_limited( 'verify_code', $email . '|' . AIMP_Login_Security::ip(), 10 ) ) {
			self::fail( __( 'Too many attempts. Please try again later.', 'atelier-irisee-master-plugin' ) );
		}
		$user = $email ? get_user_by( 'email', $email ) : false;
		if ( ! $user ) {
			self::fail( array( 'code' => __( 'This code is not valid. Request a new code.', 'atelier-irisee-master-plugin' ) ) );
		}
		$check = AIMP_Login_Verification::verify_code( $user, self::field( 'code' ) );
		if ( is_wp_error( $check ) ) {
			self::fail( array( 'code' => $check->get_error_message() ) );
		}
		$result = AIMP_Login_Verification::after_verified( $user->ID );
		if ( 'login' === $result['result'] ) {
			self::login_and_respond( $user->ID, 'register' );
		}
		wp_send_json_success(
			array(
				'next'    => 'message',
				'message' => $result['message'],
			)
		);
	}

	public static function resend() {
		self::check_nonce();
		$email = sanitize_email( self::request( 'email' ) );
		if ( $email
			&& ! AIMP_Login_Security::rate_limited( 'resend', $email, 3 )
			&& ! AIMP_Login_Security::rate_limited( 'resend_ip', AIMP_Login_Security::ip(), 10 ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				AIMP_Login_Verification::resend( $user->ID );
			}
		}
		wp_send_json_success(
			array(
				'message' => __( 'If your account still needs to be verified, we sent you a new email.', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	public static function profile() {
		self::check_nonce();
		if ( ! is_user_logged_in() ) {
			self::fail( __( 'Please log in to edit your profile.', 'atelier-irisee-master-plugin' ) );
		}
		$user   = wp_get_current_user();
		$errors = new WP_Error();

		$first   = self::field( 'first_name' );
		$last    = self::field( 'last_name' );
		$display = self::field( 'display_name' );
		foreach ( array(
			'first_name'   => array( $first, __( 'Please enter your first name.', 'atelier-irisee-master-plugin' ) ),
			'last_name'    => array( $last, __( 'Please enter your last name.', 'atelier-irisee-master-plugin' ) ),
			'display_name' => array( $display, __( 'Please enter a display name.', 'atelier-irisee-master-plugin' ) ),
		) as $key => $check ) {
			if ( '' === $check[0] ) {
				$errors->add( $key, $check[1] );
			}
		}

		$email        = sanitize_email( self::field( 'email' ) );
		$email_change = false;
		if ( ! is_email( $email ) ) {
			$errors->add( 'email', __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ) );
		} elseif ( strtolower( $email ) !== strtolower( $user->user_email ) ) {
			$owner = email_exists( $email );
			if ( $owner && (int) $owner !== $user->ID ) {
				$errors->add( 'email', __( 'This email address is already used by another account.', 'atelier-irisee-master-plugin' ) );
			} else {
				$email_change = true;
			}
		}

		$new_password = self::password( 'new_password' );
		$social_only  = (bool) get_user_meta( $user->ID, AIMP_Login_Social::SOCIAL_ONLY, true );
		if ( '' !== $new_password ) {
			if ( ! $social_only && ! wp_check_password( self::password( 'current_password' ), $user->user_pass, $user->ID ) ) {
				$errors->add( 'current_password', __( 'Your current password is not correct.', 'atelier-irisee-master-plugin' ) );
			}
			$weak = AIMP_Login_Security::password_problem( $new_password );
			if ( $weak ) {
				$errors->add( 'new_password', $weak );
			} elseif ( self::password( 'new_password2' ) !== $new_password ) {
				$errors->add( 'new_password2', __( 'The passwords do not match.', 'atelier-irisee-master-plugin' ) );
			}
		}

		$values = AIMP_Login_Fields::collect( 'profile', $user->ID, $errors );
		if ( $errors->has_errors() ) {
			self::fail( $errors );
		}

		$update = array(
			'ID'           => $user->ID,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => $display,
		);
		if ( '' !== $new_password ) {
			$update['user_pass'] = $new_password;
			delete_user_meta( $user->ID, AIMP_Login_Social::SOCIAL_ONLY );
		}
		wp_update_user( $update );
		AIMP_Login_Fields::save( $user->ID, $values, 'profile' );

		$message = __( 'Your profile has been saved.', 'atelier-irisee-master-plugin' );
		if ( $email_change ) {
			AIMP_Login_Verification::request_email_change( $user->ID, $email );
			$message .= ' ' . __( 'We sent a link to your new email address. Your email address changes once you open that link.', 'atelier-irisee-master-plugin' );
		}
		wp_send_json_success(
			array(
				'message' => $message,
				'reload'  => true,
			)
		);
	}
}
