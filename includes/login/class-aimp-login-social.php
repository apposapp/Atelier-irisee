<?php
/**
 * Social login: Google, Facebook, Apple, LinkedIn, Microsoft, LINE and X (OAuth 2 / OpenID Connect).
 *
 * Flow: ?aimp_social_start={provider} -> provider -> callback /aimp-social/{provider}/
 * (or ?aimp_social_cb={provider} without pretty permalinks).
 * Security: a random "state" bound to a cookie, PKCE where supported, fixed redirect URIs, and existing
 * accounts are only matched by email when the provider confirms that the email address is verified.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login_Social {

	const META_PREFIX  = 'aimp_social_';
	const SOCIAL_ONLY  = 'aimp_social_only';
	const STATE_COOKIE = 'aimp_el_social';
	const STATE_TTL    = 15 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 1 );
		add_action( 'wc_ajax_aimp_el_social_email', array( __CLASS__, 'ajax_social_email' ) );
		add_action( 'wc_ajax_aimp_el_social_unlink', array( __CLASS__, 'ajax_unlink' ) );
		add_action( 'woocommerce_edit_account_form_end', array( __CLASS__, 'account_connections' ) );
	}

	/* ------------------------------------------------------------------
	 * URLs
	 * ------------------------------------------------------------------ */

	public static function rewrite() {
		add_rewrite_rule( '^aimp-social/([a-z]+)/?$', 'index.php?aimp_social_cb=$matches[1]', 'top' );
		// Plugin updates do not run the activation hook: refresh the rewrite rules once per version.
		if ( get_option( 'aimp_el_rewrite_version' ) !== AIMP_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'aimp_el_rewrite_version', AIMP_VERSION );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'aimp_social_cb';
		return $vars;
	}

	/**
	 * The redirect / callback URL to register at the provider.
	 *
	 * @param string $provider Provider key.
	 * @return string
	 */
	public static function callback_url( $provider ) {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/aimp-social/' . $provider . '/' );
		}
		return add_query_arg( 'aimp_social_cb', $provider, home_url( '/' ) );
	}

	public static function start_url( $provider, $redirect = '', $link = false ) {
		$args = array( 'aimp_social_start' => $provider );
		if ( $redirect ) {
			$args['redirect'] = rawurlencode( $redirect );
		}
		if ( $link ) {
			$args['link']     = 1;
			$args['_wpnonce'] = wp_create_nonce( 'aimp_el_link_' . $provider );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/* ------------------------------------------------------------------
	 * Providers
	 * ------------------------------------------------------------------ */

	/**
	 * Enabled providers with their credentials filled in, in the configured order.
	 *
	 * @return string[]
	 */
	public static function enabled() {
		$order   = array_filter( array_map( 'trim', explode( ',', strtolower( (string) AIMP_Login::opt( 'social_order' ) ) ) ) );
		$all     = array_keys( AIMP_Login_Settings::providers() );
		$order   = array_unique( array_merge( array_intersect( $order, $all ), $all ) );
		$enabled = array();
		foreach ( $order as $provider ) {
			if ( ! AIMP_Login::opt( 'social_' . $provider . '_enabled' ) || ! AIMP_Login::opt( 'social_' . $provider . '_client_id' ) ) {
				continue;
			}
			if ( 'apple' === $provider ) {
				if ( ! AIMP_Login::opt( 'social_apple_team_id' ) || ! AIMP_Login::opt( 'social_apple_key_id' ) || ! AIMP_Login::opt( 'social_apple_private_key' ) ) {
					continue;
				}
			} elseif ( ! AIMP_Login::opt( 'social_' . $provider . '_client_secret' ) ) {
				continue;
			}
			$enabled[] = $provider;
		}
		return $enabled;
	}

	private static function client_id( $provider ) {
		return (string) AIMP_Login::opt( 'social_' . $provider . '_client_id' );
	}

	private static function client_secret( $provider ) {
		return 'apple' === $provider ? self::apple_client_secret() : (string) AIMP_Login::opt( 'social_' . $provider . '_client_secret' );
	}

	private static function config( $provider ) {
		$configs = array(
			'google'    => array(
				'auth'  => 'https://accounts.google.com/o/oauth2/v2/auth',
				'token' => 'https://oauth2.googleapis.com/token',
				'scope' => 'openid email profile',
				'pkce'  => true,
			),
			'facebook'  => array(
				'auth'  => 'https://www.facebook.com/v19.0/dialog/oauth',
				'token' => 'https://graph.facebook.com/v19.0/oauth/access_token',
				'scope' => 'email,public_profile',
				'pkce'  => false,
			),
			'apple'     => array(
				'auth'  => 'https://appleid.apple.com/auth/authorize',
				'token' => 'https://appleid.apple.com/auth/token',
				'scope' => 'name email',
				'pkce'  => false,
			),
			'linkedin'  => array(
				'auth'  => 'https://www.linkedin.com/oauth/v2/authorization',
				'token' => 'https://www.linkedin.com/oauth/v2/accessToken',
				'scope' => 'openid profile email',
				'pkce'  => false,
			),
			'microsoft' => array(
				'auth'  => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
				'scope' => 'openid email profile',
				'pkce'  => true,
			),
			'line'      => array(
				'auth'  => 'https://access.line.me/oauth2/v2.1/authorize',
				'token' => 'https://api.line.me/oauth2/v2.1/token',
				'scope' => 'openid profile email',
				'pkce'  => true,
			),
			'x'         => array(
				'auth'  => 'https://x.com/i/oauth2/authorize',
				'token' => 'https://api.x.com/2/oauth2/token',
				'scope' => 'users.read tweet.read',
				'pkce'  => true,
			),
		);
		return isset( $configs[ $provider ] ) ? $configs[ $provider ] : null;
	}

	/* ------------------------------------------------------------------
	 * Buttons
	 * ------------------------------------------------------------------ */

	private static function icon( $provider ) {
		$icons = array(
			'google'    => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/><path fill="#FBBC05" d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.98-3.09z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/></svg>',
			'facebook'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#1877F2" d="M24 12.07C24 5.41 18.63 0 12 0S0 5.41 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>',
			'apple'     => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16.37 12.78c-.03-2.65 2.16-3.92 2.26-3.98-1.23-1.8-3.15-2.05-3.83-2.08-1.63-.17-3.18.96-4.01.96-.83 0-2.1-.94-3.46-.91-1.78.03-3.42 1.03-4.34 2.62-1.85 3.2-.47 7.95 1.33 10.55.88 1.27 1.93 2.7 3.3 2.65 1.33-.05 1.83-.86 3.43-.86 1.6 0 2.05.86 3.45.83 1.43-.03 2.33-1.29 3.2-2.57 1.01-1.47 1.42-2.9 1.45-2.97-.03-.01-2.77-1.06-2.8-4.2zM13.73 4.99c.73-.88 1.22-2.11 1.09-3.33-1.05.04-2.32.7-3.07 1.58-.68.78-1.27 2.03-1.11 3.23 1.17.09 2.36-.6 3.09-1.48z"/></svg>',
			'linkedin'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#0A66C2" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>',
			'microsoft' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="1" y="1" width="10.5" height="10.5" fill="#F25022"/><rect x="12.5" y="1" width="10.5" height="10.5" fill="#7FBA00"/><rect x="1" y="12.5" width="10.5" height="10.5" fill="#00A4EF"/><rect x="12.5" y="12.5" width="10.5" height="10.5" fill="#FFB900"/></svg>',
			'line'      => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect width="24" height="24" rx="5" fill="#06C755"/><path fill="#fff" d="M12 5C7.6 5 4 7.9 4 11.4c0 3.2 2.8 5.8 6.6 6.3.3.1.6.2.7.4.1.2.1.5 0 .7l-.1.7c0 .2-.2.8.7.4s4.9-2.9 6.7-4.9c1.2-1.3 1.8-2.6 1.8-4C20.4 7.9 16.4 5 12 5z"/></svg>',
			'x'         => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93zm-1.29 19.5h2.04L6.49 3.24H4.3z"/></svg>',
		);
		return isset( $icons[ $provider ] ) ? $icons[ $provider ] : '';
	}

	/**
	 * Social buttons for the login and registration forms.
	 *
	 * @return string
	 */
	public static function buttons_html() {
		$providers = self::enabled();
		if ( ! $providers ) {
			return '';
		}
		$names     = AIMP_Login_Settings::providers();
		$icon_only = 'icon' === AIMP_Login::opt( 'social_button_style' );
		$html      = '<div class="aimp-el-social-wrap"><p class="aimp-el-divider"><span>' . esc_html__( 'or continue with', 'atelier-irisee-master-plugin' ) . '</span></p><div class="aimp-el-social' . ( $icon_only ? ' aimp-el-social--icons' : '' ) . '">';
		foreach ( $providers as $provider ) {
			/* translators: %s: provider name */
			$label = sprintf( __( 'Continue with %s', 'atelier-irisee-master-plugin' ), $names[ $provider ] );
			$html .= sprintf(
				'<a class="aimp-el-social-btn aimp-el-social-btn--%1$s" href="%2$s" data-aimp-social="%1$s" aria-label="%3$s" title="%3$s">%4$s%5$s</a>',
				esc_attr( $provider ),
				esc_url( self::start_url( $provider ) ),
				esc_attr( $label ),
				self::icon( $provider ),
				$icon_only ? '' : '<span>' . esc_html( $label ) . '</span>'
			);
		}
		return $html . '</div></div>';
	}

	/**
	 * Connected accounts (link / unlink) for the profile form and My Account.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function connections_html( $user_id ) {
		$providers = self::enabled();
		if ( ! $providers ) {
			return '';
		}
		$names = AIMP_Login_Settings::providers();
		$html  = '<fieldset class="aimp-el-connections"><legend>' . esc_html__( 'Connected accounts', 'atelier-irisee-master-plugin' ) . '</legend><ul>';
		foreach ( $providers as $provider ) {
			$linked = (bool) get_user_meta( $user_id, self::META_PREFIX . $provider, true );
			$html  .= '<li><span class="aimp-el-connection-name">' . self::icon( $provider ) . ' ' . esc_html( $names[ $provider ] ) . '</span> ';
			if ( $linked ) {
				$html .= '<span class="aimp-el-connected">' . esc_html__( 'Connected', 'atelier-irisee-master-plugin' ) . '</span> <button type="button" class="aimp-el-link-button" data-aimp-unlink="' . esc_attr( $provider ) . '">' . esc_html__( 'Disconnect', 'atelier-irisee-master-plugin' ) . '</button>';
			} else {
				$html .= '<a class="aimp-el-link-button" href="' . esc_url( self::start_url( $provider, wc_get_account_endpoint_url( 'edit-account' ), true ) ) . '">' . esc_html__( 'Connect', 'atelier-irisee-master-plugin' ) . '</a>';
			}
			$html .= '</li>';
		}
		return $html . '</ul></fieldset>';
	}

	public static function account_connections() {
		if ( AIMP_Login::opt( 'profile_replace' ) ) {
			return; // The profile form already shows them.
		}
		AIMP_Login::enqueue_assets();
		echo self::connections_html( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in connections_html().
	}

	/* ------------------------------------------------------------------
	 * Routing
	 * ------------------------------------------------------------------ */

	public static function route() {
		// phpcs:disable WordPress.Security.NonceVerification -- OAuth requests carry their own state.
		if ( isset( $_GET['aimp_social_start'] ) ) {
			self::start( sanitize_key( wp_unslash( $_GET['aimp_social_start'] ) ) );
		}
		$provider = get_query_var( 'aimp_social_cb' );
		if ( ! $provider && isset( $_GET['aimp_social_cb'] ) ) {
			$provider = wp_unslash( $_GET['aimp_social_cb'] );
		}
		// phpcs:enable
		if ( $provider ) {
			self::callback( sanitize_key( $provider ) );
		}
	}

	private static function fail_redirect( $message = 'social_error' ) {
		wp_safe_redirect( add_query_arg( 'aimp_el_msg', $message, home_url( '/' ) ) );
		exit;
	}

	private static function b64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT/PKCE encoding.
	}

	private static function set_state_cookie( $value, $expires ) {
		$secure = is_ssl();
		setcookie(
			self::STATE_COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => true,
				// Apple posts the answer back from its own site: the cookie must travel along with that POST.
				'samesite' => $secure ? 'None' : 'Lax',
			)
		);
	}

	private static function start( $provider ) {
		$config = self::config( $provider );
		if ( ! $config || ! in_array( $provider, self::enabled(), true ) ) {
			self::fail_redirect();
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- linking is checked with its own nonce below.
		$link_user = 0;
		if ( ! empty( $_GET['link'] ) ) {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'aimp_el_link_' . $provider ) ) {
				self::fail_redirect();
			}
			$link_user = get_current_user_id();
		}
		$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['redirect'] ) ) ) : '';
		// phpcs:enable

		$state    = wp_generate_password( 32, false, false );
		$verifier = wp_generate_password( 64, false, false );
		set_transient(
			'aimp_el_soc_' . $state,
			array(
				'provider'  => $provider,
				'verifier'  => $verifier,
				'redirect'  => $redirect ? wp_validate_redirect( $redirect, '' ) : '',
				'link_user' => $link_user,
				'lang'      => AIMP_I18n::current(),
			),
			self::STATE_TTL
		);
		self::set_state_cookie( $state, time() + self::STATE_TTL );

		$args = array(
			'client_id'     => self::client_id( $provider ),
			'redirect_uri'  => self::callback_url( $provider ),
			'response_type' => 'code',
			'scope'         => $config['scope'],
			'state'         => $state,
		);
		if ( $config['pkce'] ) {
			$args['code_challenge']        = self::b64url( hash( 'sha256', $verifier, true ) );
			$args['code_challenge_method'] = 'S256';
		}
		if ( 'apple' === $provider ) {
			$args['response_mode'] = 'form_post';
		}
		if ( 'google' === $provider ) {
			$args['prompt'] = 'select_account';
		}
		if ( 'line' === $provider ) {
			$args['nonce'] = wp_generate_password( 16, false, false );
		}

		// rawurlencode keeps spaces in "scope" as %20, which every provider accepts.
		wp_redirect( $config['auth'] . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external provider.
		exit;
	}

	private static function callback( $provider ) {
		// phpcs:disable WordPress.Security.NonceVerification -- validated with the state value and cookie.
		$source = ( 'apple' === $provider && 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) ? $_POST : $_GET; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$state  = isset( $source['state'] ) ? sanitize_text_field( wp_unslash( $source['state'] ) ) : '';
		$code   = isset( $source['code'] ) ? sanitize_text_field( wp_unslash( $source['code'] ) ) : '';
		$cookie = isset( $_COOKIE[ self::STATE_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::STATE_COOKIE ] ) ) : '';
		$apple  = isset( $source['user'] ) ? json_decode( wp_unslash( $source['user'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- decoded and sanitized below.
		// phpcs:enable

		$data = $state ? get_transient( 'aimp_el_soc_' . $state ) : false;
		delete_transient( 'aimp_el_soc_' . $state );
		self::set_state_cookie( '', time() - HOUR_IN_SECONDS );

		if ( ! $code || ! $data || ! hash_equals( $state, $cookie ) || $data['provider'] !== $provider ) {
			self::fail_redirect();
		}

		$token = self::exchange( $provider, $code, $data['verifier'] );
		$user  = $token ? self::profile( $provider, $token, $apple ) : null;
		if ( ! $user || empty( $user['id'] ) ) {
			self::fail_redirect();
		}

		AIMP_I18n::with_language(
			$data['lang'],
			function () use ( $provider, $user, $data ) {
				if ( $data['link_user'] ) {
					AIMP_Login_Social::link_account( $data['link_user'], $provider, $user['id'] );
				}
				AIMP_Login_Social::login_or_register( $provider, $user, $data['redirect'] );
			}
		);
	}

	/* ------------------------------------------------------------------
	 * Tokens & profiles
	 * ------------------------------------------------------------------ */

	private static function exchange( $provider, $code, $verifier ) {
		$config = self::config( $provider );
		$body   = array(
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => self::callback_url( $provider ),
			'client_id'    => self::client_id( $provider ),
		);
		$headers = array( 'Accept' => 'application/json' );

		if ( 'x' === $provider ) {
			// X wants the app credentials as HTTP Basic auth.
			$headers['Authorization'] = 'Basic ' . base64_encode( self::client_id( $provider ) . ':' . self::client_secret( $provider ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
		} else {
			$body['client_secret'] = self::client_secret( $provider );
		}
		if ( $config['pkce'] ) {
			$body['code_verifier'] = $verifier;
		}
		if ( 'microsoft' === $provider ) {
			$body['scope'] = $config['scope'];
		}

		$response = wp_remote_post(
			$config['token'],
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$token = json_decode( wp_remote_retrieve_body( $response ), true );
		return ( is_array( $token ) && ( ! empty( $token['access_token'] ) || ! empty( $token['id_token'] ) ) ) ? $token : null;
	}

	private static function get_json( $url, $access_token = '' ) {
		$args = array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		);
		if ( $access_token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $access_token;
		}
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $json ) ? $json : null;
	}

	/**
	 * Payload of a JWT that came straight from the provider's token endpoint over HTTPS.
	 *
	 * @param string $jwt Token.
	 * @return array|null
	 */
	private static function jwt_payload( $jwt ) {
		$parts = explode( '.', (string) $jwt );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		$json = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JWT decoding.
		return is_array( $json ) ? $json : null;
	}

	private static function truthy( $value ) {
		return true === $value || 'true' === $value || 1 === $value || '1' === $value;
	}

	/**
	 * Normalized profile: [ id, email, verified, first, last ].
	 *
	 * @param string     $provider Provider.
	 * @param array      $token    Token response.
	 * @param array|null $apple    Apple's one-time "user" field (name).
	 * @return array|null
	 */
	private static function profile( $provider, $token, $apple ) {
		$access = isset( $token['access_token'] ) ? $token['access_token'] : '';
		switch ( $provider ) {
			case 'google':
			case 'linkedin':
				$info = self::get_json( 'google' === $provider ? 'https://openidconnect.googleapis.com/v1/userinfo' : 'https://api.linkedin.com/v2/userinfo', $access );
				return $info ? array(
					'id'       => isset( $info['sub'] ) ? $info['sub'] : '',
					'email'    => isset( $info['email'] ) ? $info['email'] : '',
					'verified' => isset( $info['email_verified'] ) && self::truthy( $info['email_verified'] ),
					'first'    => isset( $info['given_name'] ) ? $info['given_name'] : '',
					'last'     => isset( $info['family_name'] ) ? $info['family_name'] : '',
				) : null;

			case 'facebook':
				$info = self::get_json( 'https://graph.facebook.com/me?fields=id,first_name,last_name,email&access_token=' . rawurlencode( $access ) );
				// Facebook only returns an email address that the person has confirmed.
				return $info ? array(
					'id'       => isset( $info['id'] ) ? $info['id'] : '',
					'email'    => isset( $info['email'] ) ? $info['email'] : '',
					'verified' => ! empty( $info['email'] ),
					'first'    => isset( $info['first_name'] ) ? $info['first_name'] : '',
					'last'     => isset( $info['last_name'] ) ? $info['last_name'] : '',
				) : null;

			case 'apple':
				$info = self::jwt_payload( isset( $token['id_token'] ) ? $token['id_token'] : '' );
				if ( ! $info || ( isset( $info['aud'] ) && $info['aud'] !== self::client_id( 'apple' ) ) ) {
					return null;
				}
				return array(
					'id'       => isset( $info['sub'] ) ? $info['sub'] : '',
					'email'    => isset( $info['email'] ) ? $info['email'] : '',
					'verified' => isset( $info['email_verified'] ) && self::truthy( $info['email_verified'] ),
					'first'    => isset( $apple['name']['firstName'] ) ? $apple['name']['firstName'] : '',
					'last'     => isset( $apple['name']['lastName'] ) ? $apple['name']['lastName'] : '',
				);

			case 'microsoft':
				$info = self::get_json( 'https://graph.microsoft.com/oidc/userinfo', $access );
				return $info ? array(
					'id'       => isset( $info['sub'] ) ? $info['sub'] : '',
					'email'    => isset( $info['email'] ) ? $info['email'] : '',
					'verified' => false, // Microsoft does not say whether the address was verified.
					'first'    => isset( $info['given_name'] ) ? $info['given_name'] : '',
					'last'     => isset( $info['family_name'] ) ? $info['family_name'] : '',
				) : null;

			case 'line':
				$response = wp_remote_post(
					'https://api.line.me/oauth2/v2.1/verify',
					array(
						'timeout' => 15,
						'body'    => array(
							'id_token'  => isset( $token['id_token'] ) ? $token['id_token'] : '',
							'client_id' => self::client_id( 'line' ),
						),
					)
				);
				$info = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! is_array( $info ) || empty( $info['sub'] ) ) {
					return null;
				}
				$name = isset( $info['name'] ) ? explode( ' ', $info['name'], 2 ) : array( '' );
				return array(
					'id'       => $info['sub'],
					'email'    => isset( $info['email'] ) ? $info['email'] : '',
					'verified' => false,
					'first'    => $name[0],
					'last'     => isset( $name[1] ) ? $name[1] : '',
				);

			case 'x':
				$info = self::get_json( 'https://api.x.com/2/users/me', $access );
				if ( ! $info || empty( $info['data']['id'] ) ) {
					return null;
				}
				$name = isset( $info['data']['name'] ) ? explode( ' ', $info['data']['name'], 2 ) : array( '' );
				return array(
					'id'       => $info['data']['id'],
					'email'    => '', // X does not share email addresses.
					'verified' => false,
					'first'    => $name[0],
					'last'     => isset( $name[1] ) ? $name[1] : '',
				);
		}
		return null;
	}

	/* ------------------------------------------------------------------
	 * Apple client secret (ES256 JWT)
	 * ------------------------------------------------------------------ */

	private static function apple_client_secret() {
		$cached = get_transient( 'aimp_el_apple_secret' );
		if ( $cached ) {
			return $cached;
		}
		$key = openssl_pkey_get_private( (string) AIMP_Login::opt( 'social_apple_private_key' ) );
		if ( ! $key ) {
			return '';
		}
		$now     = time();
		$header  = self::b64url( wp_json_encode( array( 'alg' => 'ES256', 'kid' => AIMP_Login::opt( 'social_apple_key_id' ) ) ) );
		$payload = self::b64url(
			wp_json_encode(
				array(
					'iss' => AIMP_Login::opt( 'social_apple_team_id' ),
					'iat' => $now,
					'exp' => $now + DAY_IN_SECONDS,
					'aud' => 'https://appleid.apple.com',
					'sub' => self::client_id( 'apple' ),
				)
			)
		);
		$signature = '';
		if ( ! openssl_sign( $header . '.' . $payload, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			return '';
		}
		$jwt = $header . '.' . $payload . '.' . self::b64url( self::der_to_raw( $signature ) );
		set_transient( 'aimp_el_apple_secret', $jwt, DAY_IN_SECONDS - HOUR_IN_SECONDS );
		return $jwt;
	}

	/**
	 * OpenSSL gives an ECDSA signature in DER format; JWT needs the raw 64-byte R‖S form.
	 *
	 * @param string $der DER signature.
	 * @return string
	 */
	private static function der_to_raw( $der ) {
		$pos = 2;
		if ( ord( $der[1] ) & 0x80 ) {
			$pos = 2 + ( ord( $der[1] ) & 0x7f );
		}
		$parts = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$pos++; // 0x02 (INTEGER).
			$len     = ord( $der[ $pos ] );
			$pos++;
			$parts[] = str_pad( ltrim( substr( $der, $pos, $len ), "\x00" ), 32, "\x00", STR_PAD_LEFT );
			$pos    += $len;
		}
		return $parts[0] . $parts[1];
	}

	/* ------------------------------------------------------------------
	 * Accounts
	 * ------------------------------------------------------------------ */

	private static function find_linked( $provider, $id ) {
		$users = get_users(
			array(
				'meta_key'   => self::META_PREFIX . $provider, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => (string) $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);
		return $users ? (int) $users[0] : 0;
	}

	/**
	 * Connect a provider account to a logged-in user (from My Account).
	 *
	 * @param int    $user_id  User.
	 * @param string $provider Provider.
	 * @param string $id       Provider account ID.
	 */
	public static function link_account( $user_id, $provider, $id ) {
		$back  = wc_get_account_endpoint_url( 'edit-account' );
		$other = self::find_linked( $provider, $id );
		if ( get_current_user_id() !== (int) $user_id || ( $other && $other !== (int) $user_id ) ) {
			wc_add_notice( __( 'This account is already connected to another customer.', 'atelier-irisee-master-plugin' ), 'error' );
		} else {
			update_user_meta( $user_id, self::META_PREFIX . $provider, (string) $id );
			wc_add_notice( __( 'Your account has been connected.', 'atelier-irisee-master-plugin' ) );
		}
		wp_safe_redirect( $back );
		exit;
	}

	private static function finish_login( $user_id, $type, $redirect ) {
		$blocked = user_can( $user_id, 'manage_options' ) ? '' : AIMP_Login_Verification::status( $user_id );
		if ( 'approved' !== $blocked && '' !== $blocked ) {
			self::fail_redirect( 'account_' . $blocked );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'wp_login', get_userdata( $user_id )->user_login, get_userdata( $user_id ) ); // Lets WooCommerce merge the cart.
		do_action( 'aimp_el_login_success', get_userdata( $user_id ) );
		$url = AIMP_Login::redirect_url( $type, '', $redirect, $user_id );
		wp_safe_redirect( $url ? $url : home_url( '/' ) );
		exit;
	}

	/**
	 * Log in a returning social account, link it by verified email, or create a new account.
	 *
	 * @param string $provider Provider.
	 * @param array  $profile  Normalized profile.
	 * @param string $redirect Page to return to.
	 */
	public static function login_or_register( $provider, $profile, $redirect ) {
		$linked = self::find_linked( $provider, $profile['id'] );
		if ( $linked ) {
			self::finish_login( $linked, 'login', $redirect );
		}

		$email = sanitize_email( $profile['email'] );
		if ( $email && is_email( $email ) ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing ) {
				if ( ! $profile['verified'] ) {
					// Never take over an account through an unconfirmed email address.
					self::fail_redirect( 'social_exists' );
				}
				update_user_meta( $existing->ID, self::META_PREFIX . $provider, (string) $profile['id'] );
				self::finish_login( $existing->ID, 'login', $redirect );
			}
			if ( ! AIMP_Login::registration_enabled() ) {
				self::fail_redirect();
			}
			$user_id = self::create_social_user( $provider, $profile, $email );
			$result  = AIMP_Login_Verification::start( $user_id, $profile['verified'] ? 'social_verified' : 'social_unverified' );
			self::respond_after_create( $user_id, $result, $email, $redirect );
		}

		// No email address (X, or the person did not share it): ask for one.
		if ( ! AIMP_Login::registration_enabled() ) {
			self::fail_redirect();
		}
		$token = wp_generate_password( 32, false, false );
		set_transient(
			'aimp_el_soc_profile_' . $token,
			array(
				'provider' => $provider,
				'profile'  => $profile,
				'redirect' => $redirect,
			),
			self::STATE_TTL
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'aimp_el'    => 'social_email',
					'aimp_token' => $token,
				),
				home_url( '/' )
			)
		);
		exit;
	}

	private static function create_social_user( $provider, $profile, $email ) {
		$user_id = AIMP_Login_Ajax::create_user(
			$email,
			'',
			wp_generate_password( 24, true, true ),
			sanitize_text_field( $profile['first'] ),
			sanitize_text_field( $profile['last'] )
		);
		if ( is_wp_error( $user_id ) ) {
			self::fail_redirect();
		}
		update_user_meta( $user_id, self::META_PREFIX . $provider, (string) $profile['id'] );
		// The customer does not know the random password: they can set one later via "forgot password".
		update_user_meta( $user_id, self::SOCIAL_ONLY, 1 );
		do_action( 'aimp_el_register_user', $user_id );
		return $user_id;
	}

	private static function respond_after_create( $user_id, $result, $email, $redirect ) {
		if ( 'login' === $result['result'] ) {
			self::finish_login( $user_id, 'register', $redirect );
		}
		if ( 'verify' === $result['result'] ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'aimp_el'    => 'verify',
						'aimp_email' => rawurlencode( $email ),
					),
					home_url( '/' )
				)
			);
			exit;
		}
		self::fail_redirect( AIMP_Login::opt( 'admin_approval' ) && 'pending' === AIMP_Login_Verification::status( $user_id ) ? 'verified_wait' : 'check_email' );
	}

	/**
	 * The "enter your email" step after X (or any provider without an email address).
	 */
	public static function ajax_social_email() {
		if ( ! check_ajax_referer( AIMP_Login::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'errors' => array( '_form' => __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ) ) ) );
		}
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$email = isset( $_POST['aimp']['email'] ) ? sanitize_email( wp_unslash( $_POST['aimp']['email'] ) ) : '';
		$data  = $token ? get_transient( 'aimp_el_soc_profile_' . $token ) : false;

		if ( ! $data ) {
			wp_send_json_error( array( 'errors' => array( '_form' => __( 'This step has expired. Please log in with your social account again.', 'atelier-irisee-master-plugin' ) ) ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'errors' => array( 'email' => __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ) ) ) );
		}
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'errors' => array( 'email' => __( 'An account with this email address already exists. Log in with your password, then connect this account under My Account.', 'atelier-irisee-master-plugin' ) ) ) );
		}
		delete_transient( 'aimp_el_soc_profile_' . $token );

		$user_id = self::create_social_user( $data['provider'], $data['profile'], $email );
		// The address was typed in by hand, so it must be confirmed.
		$result = AIMP_Login_Verification::start( $user_id, 'social_unverified' );
		if ( 'login' === $result['result'] ) {
			AIMP_Login_Ajax::login_and_respond( $user_id, 'register' );
		}
		wp_send_json_success(
			array(
				'next'    => 'verify' === $result['result'] ? 'verify' : 'message',
				'email'   => $email,
				'message' => $result['message'],
			)
		);
	}

	public static function ajax_unlink() {
		if ( ! is_user_logged_in() || ! check_ajax_referer( AIMP_Login::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'errors' => array( '_form' => __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ) ) ) );
		}
		$user_id  = get_current_user_id();
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		if ( ! array_key_exists( $provider, AIMP_Login_Settings::providers() ) ) {
			wp_send_json_error( array( 'errors' => array( '_form' => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ) ) ) );
		}
		if ( get_user_meta( $user_id, self::SOCIAL_ONLY, true ) ) {
			$linked = 0;
			foreach ( array_keys( AIMP_Login_Settings::providers() ) as $key ) {
				$linked += get_user_meta( $user_id, self::META_PREFIX . $key, true ) ? 1 : 0;
			}
			if ( $linked <= 1 ) {
				wp_send_json_error( array( 'errors' => array( '_form' => __( 'Choose a password first, otherwise you can no longer log in.', 'atelier-irisee-master-plugin' ) ) ) );
			}
		}
		delete_user_meta( $user_id, self::META_PREFIX . $provider );
		wp_send_json_success( array( 'reload' => true ) );
	}
}
