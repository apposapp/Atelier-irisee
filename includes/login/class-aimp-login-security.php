<?php
/**
 * Security: captchas, password strength, login-attempt limits, rate limits and honeypot.
 *
 * Login limits use the standard "authenticate" filter and "wp_login_failed" action, so they also
 * protect wp-login.php and the WooCommerce login form, and they stay compatible with Wordfence.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login_Security {

	const LOCKOUT_INDEX = 'aimp_el_lockouts';

	public static function init() {
		add_filter( 'authenticate', array( __CLASS__, 'check_lockout' ), 30, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failure' ) );
		add_action( 'wp_login', array( __CLASS__, 'clear_failures' ) );
	}

	/* ------------------------------------------------------------------
	 * Visitor identity
	 * ------------------------------------------------------------------ */

	/**
	 * The visitor's IP. Only REMOTE_ADDR is trusted: forwarded headers can be faked by anyone.
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/* ------------------------------------------------------------------
	 * Captcha
	 * ------------------------------------------------------------------ */

	private static function captcha_active( $form = '' ) {
		$type = AIMP_Login::opt( 'captcha' );
		if ( 'none' === $type || ! AIMP_Login::opt( 'captcha_site_key' ) || ! AIMP_Login::opt( 'captcha_secret_key' ) ) {
			return false;
		}
		return '' === $form || in_array( $form, (array) AIMP_Login::opt( 'captcha_forms' ), true );
	}

	/**
	 * Whether a form shows a captcha (used by the templates).
	 *
	 * @param string $form Form key.
	 * @return bool
	 */
	public static function form_has_captcha( $form ) {
		return self::captcha_active( $form );
	}

	/**
	 * Captcha settings for login.js. The scripts are loaded only when a protected form is shown.
	 *
	 * @return array|null
	 */
	public static function script_config() {
		if ( ! self::captcha_active() ) {
			return null;
		}
		$type    = AIMP_Login::opt( 'captcha' );
		$scripts = array(
			'recaptcha_v2' => 'https://www.google.com/recaptcha/api.js?render=explicit',
			'recaptcha_v3' => 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( AIMP_Login::opt( 'captcha_site_key' ) ),
			'turnstile'    => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
			'friendly'     => 'https://cdn.jsdelivr.net/npm/friendly-challenge@0.9/widget.min.js',
		);
		return array(
			'type'    => $type,
			'siteKey' => AIMP_Login::opt( 'captcha_site_key' ),
			'script'  => $scripts[ $type ],
			'forms'   => array_values( (array) AIMP_Login::opt( 'captcha_forms' ) ),
		);
	}

	/**
	 * Verify the captcha answer sent with a form.
	 *
	 * @param string $form Form key.
	 * @return string Error message, or '' when fine (or not required).
	 */
	public static function verify_captcha( $form ) {
		if ( ! self::captcha_active( $form ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
		$token = isset( $_POST['aimp_captcha'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_captcha'] ) ) : '';
		$fail  = __( 'Please complete the captcha.', 'atelier-irisee-master-plugin' );
		if ( '' === $token ) {
			return $fail;
		}

		$type   = AIMP_Login::opt( 'captcha' );
		$secret = AIMP_Login::opt( 'captcha_secret_key' );
		switch ( $type ) {
			case 'turnstile':
				$url  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
				$body = array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => self::ip(),
				);
				break;
			case 'friendly':
				$url  = 'https://api.friendlycaptcha.com/api/v1/siteverify';
				$body = array(
					'solution' => $token,
					'secret'   => $secret,
					'sitekey'  => AIMP_Login::opt( 'captcha_site_key' ),
				);
				break;
			default:
				$url  = 'https://www.google.com/recaptcha/api/siteverify';
				$body = array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => self::ip(),
				);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			// Friendly Captcha asks sites to accept the form when its server cannot be reached.
			return 'friendly' === $type ? '' : $fail;
		}
		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $result['success'] ) ) {
			return $fail;
		}
		if ( 'recaptcha_v3' === $type && ( ! isset( $result['score'] ) || (float) $result['score'] < (float) AIMP_Login::opt( 'recaptcha_threshold' ) ) ) {
			return $fail;
		}
		return '';
	}

	/* ------------------------------------------------------------------
	 * Honeypot & rate limits
	 * ------------------------------------------------------------------ */

	/**
	 * Bots fill in every field, including the invisible one.
	 *
	 * @return bool
	 */
	public static function honeypot_triggered() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
		return ! empty( $_POST['aimp_hp'] );
	}

	/**
	 * Count a request and tell whether the limit is exceeded.
	 *
	 * @param string $action Action name.
	 * @param string $key    Who (IP, email…).
	 * @param int    $max    Allowed requests per window.
	 * @param int    $window Window in seconds.
	 * @return bool True when over the limit.
	 */
	public static function rate_limited( $action, $key, $max, $window = HOUR_IN_SECONDS ) {
		$transient = 'aimp_el_rl_' . md5( $action . '|' . strtolower( $key ) );
		$data      = get_transient( $transient );
		$data      = is_array( $data ) ? $data : array(
			'count' => 0,
			'start' => time(),
		);
		if ( $data['count'] >= $max ) {
			return true;
		}
		$data['count']++;
		set_transient( $transient, $data, max( 1, $window - ( time() - $data['start'] ) ) );
		return false;
	}

	/* ------------------------------------------------------------------
	 * Password strength
	 * ------------------------------------------------------------------ */

	/**
	 * Server-side strength check matching the levels in the settings (the meter in the browser uses zxcvbn).
	 *
	 * @param string $password Password.
	 * @return string Error message or ''.
	 */
	public static function password_problem( $password ) {
		$level  = (int) AIMP_Login::opt( 'strength_min' );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $password ) : strlen( $password );
		$letter = preg_match( '/\p{L}/u', $password );
		$digit  = preg_match( '/\d/', $password );
		$upper  = preg_match( '/\p{Lu}/u', $password );
		$lower  = preg_match( '/\p{Ll}/u', $password );
		$symbol = preg_match( '/[^\p{L}\d]/u', $password );

		$rules = array(
			1 => array( $length >= 6, __( 'Use at least 6 characters.', 'atelier-irisee-master-plugin' ) ),
			2 => array( $length >= 8, __( 'Use at least 8 characters.', 'atelier-irisee-master-plugin' ) ),
			3 => array( $length >= 8 && $letter && $digit, __( 'Use at least 8 characters with letters and numbers.', 'atelier-irisee-master-plugin' ) ),
			4 => array( $length >= 12 && $upper && $lower && $digit && $symbol, __( 'Use at least 12 characters with upper and lower case letters, numbers and a symbol.', 'atelier-irisee-master-plugin' ) ),
		);
		if ( $level > 0 && isset( $rules[ $level ] ) && ! $rules[ $level ][0] ) {
			return $rules[ $level ][1];
		}
		return '';
	}

	/* ------------------------------------------------------------------
	 * Login-attempt limits
	 * ------------------------------------------------------------------ */

	private static function lock_key( $type, $value ) {
		return 'aimp_el_lock_' . md5( $type . '|' . strtolower( $value ) );
	}

	private static function fail_key( $type, $value ) {
		return 'aimp_el_fail_' . md5( $type . '|' . strtolower( $value ) );
	}

	/**
	 * Block logins while the IP or username is locked out.
	 *
	 * @param WP_User|WP_Error|null $user     Result so far.
	 * @param string                $username Username.
	 * @return WP_User|WP_Error|null
	 */
	public static function check_lockout( $user, $username ) {
		if ( ! AIMP_Login::opt( 'limit_enabled' ) || '' === (string) $username ) {
			return $user;
		}
		foreach ( array( array( 'ip', self::ip() ), array( 'user', $username ) ) as $check ) {
			$until = (int) get_transient( self::lock_key( $check[0], $check[1] ) );
			if ( $until > time() ) {
				$minutes = max( 1, (int) ceil( ( $until - time() ) / MINUTE_IN_SECONDS ) );
				return new WP_Error(
					'aimp_locked',
					sprintf(
						/* translators: %d: minutes */
						_n( 'Too many failed login attempts. Please try again in %d minute.', 'Too many failed login attempts. Please try again in %d minutes.', $minutes, 'atelier-irisee-master-plugin' ),
						$minutes
					)
				);
			}
		}
		return $user;
	}

	/**
	 * Count a failed login per IP and per username; lock out when the limit is reached.
	 *
	 * @param string $username Username or email that was tried.
	 */
	public static function record_failure( $username ) {
		if ( ! AIMP_Login::opt( 'limit_enabled' ) ) {
			return;
		}
		$window  = max( 1, (int) AIMP_Login::opt( 'limit_window' ) ) * MINUTE_IN_SECONDS;
		$lockout = max( 1, (int) AIMP_Login::opt( 'limit_lockout' ) ) * MINUTE_IN_SECONDS;
		$max     = max( 1, (int) AIMP_Login::opt( 'limit_attempts' ) );

		foreach ( array( array( 'ip', self::ip() ), array( 'user', (string) $username ) ) as $check ) {
			if ( '' === $check[1] ) {
				continue;
			}
			$key  = self::fail_key( $check[0], $check[1] );
			$data = get_transient( $key );
			$data = is_array( $data ) ? $data : array(
				'count' => 0,
				'start' => time(),
			);
			$data['count']++;
			if ( $data['count'] >= $max ) {
				$until = time() + $lockout;
				set_transient( self::lock_key( $check[0], $check[1] ), $until, $lockout );
				delete_transient( $key );
				self::index_lockout( $check[0], $check[1], $until );
			} else {
				set_transient( $key, $data, max( 1, $window - ( time() - $data['start'] ) ) );
			}
		}
	}

	/**
	 * A successful login resets the failures for that username.
	 *
	 * @param string $username Username.
	 */
	public static function clear_failures( $username ) {
		delete_transient( self::fail_key( 'user', $username ) );
	}

	private static function index_lockout( $type, $value, $until ) {
		$index = get_option( self::LOCKOUT_INDEX, array() );
		$index = is_array( $index ) ? $index : array();
		foreach ( $index as $key => $item ) {
			if ( $item['until'] < time() ) {
				unset( $index[ $key ] );
			}
		}
		$index[ self::lock_key( $type, $value ) ] = array(
			'type'  => $type,
			'value' => $value,
			'until' => $until,
		);
		update_option( self::LOCKOUT_INDEX, array_slice( $index, -200, null, true ), false );
	}

	/**
	 * Current lockouts for the settings page.
	 *
	 * @return array[]
	 */
	public static function lockouts() {
		$list  = array();
		$index = get_option( self::LOCKOUT_INDEX, array() );
		foreach ( (array) $index as $key => $item ) {
			if ( $item['until'] > time() && get_transient( $key ) ) {
				$list[] = array(
					/* translators: %s: IP address or username */
					'label' => sprintf( 'ip' === $item['type'] ? __( 'IP address %s', 'atelier-irisee-master-plugin' ) : __( 'Username %s', 'atelier-irisee-master-plugin' ), $item['value'] ),
					'until' => (int) $item['until'],
				);
			}
		}
		return $list;
	}

	public static function clear_lockouts() {
		foreach ( array_keys( (array) get_option( self::LOCKOUT_INDEX, array() ) ) as $key ) {
			delete_transient( $key );
		}
		delete_option( self::LOCKOUT_INDEX );
	}
}
