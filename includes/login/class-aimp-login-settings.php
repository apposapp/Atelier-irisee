<?php
/**
 * Settings page "WooCommerce > Login & registration".
 *
 * One schema describes every setting (tab, type, default). It drives the defaults, the rendering,
 * the sanitizing and the import/export, so they can never disagree.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login_Settings {

	const PAGE  = 'aimp-login';
	const GROUP = 'aimp_login_group';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 61 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_aimp_login_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_aimp_login_import', array( __CLASS__, 'import' ) );
		add_action( 'admin_post_aimp_login_clear_lockouts', array( __CLASS__, 'clear_lockouts' ) );
	}

	/**
	 * Social login providers: key => label.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'google'    => 'Google',
			'facebook'  => 'Facebook',
			'apple'     => 'Apple',
			'linkedin'  => 'LinkedIn',
			'microsoft' => 'Microsoft',
			'line'      => 'LINE',
			'x'         => 'X',
		);
	}

	/**
	 * Email templates of the verification add-on: type => label.
	 *
	 * @return array
	 */
	public static function email_types() {
		return array(
			'verify_code'   => __( 'Verification code', 'atelier-irisee-master-plugin' ),
			'verify_link'   => __( 'Verification link', 'atelier-irisee-master-plugin' ),
			'pending'       => __( 'Awaiting approval (to the customer)', 'atelier-irisee-master-plugin' ),
			'approved'      => __( 'Account approved', 'atelier-irisee-master-plugin' ),
			'rejected'      => __( 'Account rejected', 'atelier-irisee-master-plugin' ),
			'admin_pending' => __( 'New account awaiting approval (to the admin)', 'atelier-irisee-master-plugin' ),
			'email_change'  => __( 'Confirm new email address', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * All settings, grouped per tab: tab => [ label, fields => [ key => definition ] ].
	 *
	 * @return array
	 */
	public static function schema() {
		$redirect_options = array(
			'same'      => __( 'The same page', 'atelier-irisee-master-plugin' ),
			'myaccount' => __( 'My Account', 'atelier-irisee-master-plugin' ),
			'custom'    => __( 'A custom URL', 'atelier-irisee-master-plugin' ),
			'default'   => __( 'WordPress default', 'atelier-irisee-master-plugin' ),
		);

		$social = array(
			'social_button_style' => array(
				'type'    => 'select',
				'label'   => __( 'Button style', 'atelier-irisee-master-plugin' ),
				'default' => 'full',
				'options' => array(
					'full' => __( 'Icon and text', 'atelier-irisee-master-plugin' ),
					'icon' => __( 'Icon only', 'atelier-irisee-master-plugin' ),
				),
			),
			'social_order'        => array(
				'type'    => 'text',
				'label'   => __( 'Button order', 'atelier-irisee-master-plugin' ),
				'default' => 'google,facebook,apple,linkedin,microsoft,line,x',
				'desc'    => __( 'Comma separated: google, facebook, apple, linkedin, microsoft, line, x.', 'atelier-irisee-master-plugin' ),
			),
		);
		foreach ( self::providers() as $key => $label ) {
			$social[ 'social_' . $key . '_heading' ] = array(
				'type'     => 'heading',
				'label'    => $label,
				'provider' => $key,
			);
			$social[ 'social_' . $key . '_enabled' ]   = array(
				'type'    => 'checkbox',
				/* translators: %s: provider name */
				'label'   => sprintf( __( 'Enable %s login', 'atelier-irisee-master-plugin' ), $label ),
				'default' => 0,
			);
			$social[ 'social_' . $key . '_client_id' ] = array(
				'type'    => 'text',
				'label'   => 'apple' === $key ? __( 'Services ID', 'atelier-irisee-master-plugin' ) : __( 'Client ID', 'atelier-irisee-master-plugin' ),
				'default' => '',
			);
			if ( 'apple' === $key ) {
				$social['social_apple_team_id']     = array(
					'type'    => 'text',
					'label'   => __( 'Team ID', 'atelier-irisee-master-plugin' ),
					'default' => '',
				);
				$social['social_apple_key_id']      = array(
					'type'    => 'text',
					'label'   => __( 'Key ID', 'atelier-irisee-master-plugin' ),
					'default' => '',
				);
				$social['social_apple_private_key'] = array(
					'type'    => 'secret_textarea',
					'label'   => __( 'Private key (.p8 file contents)', 'atelier-irisee-master-plugin' ),
					'default' => '',
				);
			} else {
				$social[ 'social_' . $key . '_client_secret' ] = array(
					'type'    => 'secret',
					'label'   => __( 'Client secret', 'atelier-irisee-master-plugin' ),
					'default' => '',
				);
			}
		}

		$emails = array(
			'emails_intro' => array(
				'type'  => 'info',
				'label' => __( 'Emails', 'atelier-irisee-master-plugin' ),
				'desc'  => __( 'Leave a subject or text empty to use the built-in text in the customer\'s language. Placeholders: {display_name}, {email}, {site_name}, {code}, {link}, {login_url}, {admin_url}.', 'atelier-irisee-master-plugin' ),
			),
		);
		foreach ( self::email_types() as $type => $label ) {
			$emails[ 'email_' . $type . '_subject' ] = array(
				'type'    => 'text',
				/* translators: %s: email name */
				'label'   => sprintf( __( '%s: subject', 'atelier-irisee-master-plugin' ), $label ),
				'default' => '',
			);
			$emails[ 'email_' . $type . '_body' ]    = array(
				'type'    => 'textarea',
				/* translators: %s: email name */
				'label'   => sprintf( __( '%s: text', 'atelier-irisee-master-plugin' ), $label ),
				'default' => '',
			);
		}

		return array(
			'general'      => array(
				'label'  => __( 'General', 'atelier-irisee-master-plugin' ),
				'fields' => array(
					'enabled'               => array(
						'type'    => 'checkbox',
						'label'   => __( 'Enable the login & registration popup', 'atelier-irisee-master-plugin' ),
						'default' => 1,
					),
					'layout'                => array(
						'type'    => 'select',
						'label'   => __( 'Popup layout', 'atelier-irisee-master-plugin' ),
						'default' => 'popup',
						'options' => array(
							'popup'  => __( 'Popup in the middle of the screen', 'atelier-irisee-master-plugin' ),
							'slider' => __( 'Slider from the side', 'atelier-irisee-master-plugin' ),
						),
					),
					'navigation'            => array(
						'type'    => 'select',
						'label'   => __( 'Switching between forms', 'atelier-irisee-master-plugin' ),
						'default' => 'tabs',
						'options' => array(
							'tabs'  => __( 'Tabs at the top', 'atelier-irisee-master-plugin' ),
							'links' => __( 'Links under the form', 'atelier-irisee-master-plugin' ),
						),
					),
					'animation'             => array(
						'type'    => 'select',
						'label'   => __( 'Animation', 'atelier-irisee-master-plugin' ),
						'default' => 'fade',
						'options' => array(
							'fade'  => __( 'Fade', 'atelier-irisee-master-plugin' ),
							'slide' => __( 'Slide', 'atelier-irisee-master-plugin' ),
							'zoom'  => __( 'Zoom', 'atelier-irisee-master-plugin' ),
						),
					),
					'logo'                  => array(
						'type'    => 'media',
						'label'   => __( 'Logo above the forms', 'atelier-irisee-master-plugin' ),
						'default' => '',
					),
					'sidebar_image'         => array(
						'type'    => 'media',
						'label'   => __( 'Side image (slider and wide popup)', 'atelier-irisee-master-plugin' ),
						'default' => '',
					),
					'single_field'          => array(
						'type'    => 'checkbox',
						'label'   => __( 'Email first: ask only the email address, then show the login or registration form', 'atelier-irisee-master-plugin' ),
						'default' => 0,
					),
					'username_mode'         => array(
						'type'    => 'select',
						'label'   => __( 'Username', 'atelier-irisee-master-plugin' ),
						'default' => 'email',
						'options' => array(
							'email' => __( 'Create it automatically from the email address', 'atelier-irisee-master-plugin' ),
							'field' => __( 'Let the customer choose a username', 'atelier-irisee-master-plugin' ),
						),
					),
					'show_names'            => array(
						'type'    => 'checkbox',
						'label'   => __( 'Ask first and last name at registration', 'atelier-irisee-master-plugin' ),
						'default' => 1,
					),
					'remember_me'           => array(
						'type'    => 'checkbox',
						'label'   => __( 'Show "Remember me"', 'atelier-irisee-master-plugin' ),
						'default' => 1,
					),
					'terms_page'            => array(
						'type'    => 'page',
						'label'   => __( 'Terms & Conditions page', 'atelier-irisee-master-plugin' ),
						'default' => 0,
						'desc'    => __( 'When set, customers must accept the terms to register.', 'atelier-irisee-master-plugin' ),
					),
					'auto_open'             => array(
						'type'    => 'checkbox',
						'label'   => __( 'Open the popup automatically for logged-out visitors (once per visit)', 'atelier-irisee-master-plugin' ),
						'default' => 0,
					),
					'auto_open_form'        => array(
						'type'    => 'select',
						'label'   => __( 'Form that opens automatically', 'atelier-irisee-master-plugin' ),
						'default' => 'login',
						'options' => array(
							'login'    => __( 'Login', 'atelier-irisee-master-plugin' ),
							'register' => __( 'Register', 'atelier-irisee-master-plugin' ),
						),
					),
					'auto_open_delay'       => array(
						'type'    => 'number',
						'label'   => __( 'Delay before opening (seconds)', 'atelier-irisee-master-plugin' ),
						'default' => 3,
						'min'     => 0,
						'max'     => 120,
					),
					'auto_open_pages'       => array(
						'type'    => 'pages_multi',
						'label'   => __( 'Open automatically on these pages', 'atelier-irisee-master-plugin' ),
						'default' => array(),
						'desc'    => __( 'Select none to open it on every page.', 'atelier-irisee-master-plugin' ),
					),
					'myaccount_replace'     => array(
						'type'    => 'checkbox',
						'label'   => __( 'Use these forms on the My Account login page', 'atelier-irisee-master-plugin' ),
						'default' => 1,
					),
					'checkout_popup'        => array(
						'type'    => 'checkbox',
						'label'   => __( 'Open the popup from the checkout "Log in" link', 'atelier-irisee-master-plugin' ),
						'default' => 1,
					),
					'profile_replace'       => array(
						'type'    => 'checkbox',
						'label'   => __( 'Replace My Account > Account details with the profile form', 'atelier-irisee-master-plugin' ),
						'default' => 0,
					),
				),
			),
			'redirects'    => array(
				'label'  => __( 'Redirects', 'atelier-irisee-master-plugin' ),
				'fields' => array(
					'login_redirect'        => array(
						'type'    => 'select',
						'label'   => __( 'After login', 'atelier-irisee-master-plugin' ),
						'default' => 'same',
						'options' => $redirect_options,
					),
					'login_redirect_url'    => array(
						'type'    => 'url',
						'label'   => __( 'Custom URL after login', 'atelier-irisee-master-plugin' ),
						'default' => '',
					),
					'register_redirect'     => array(
						'type'    => 'select',
						'label'   => __( 'After registration', 'atelier-irisee-master-plugin' ),
						'default' => 'same',
						'options' => $redirect_options,
					),
					'register_redirect_url' => array(
						'type'    => 'url',
						'label'   => __( 'Custom URL after registration', 'atelier-irisee-master-plugin' ),
						'default' => '',
					),
					'logout_redirect'       => array(
						'type'    => 'select',
						'label'   => __( 'After logout', 'atelier-irisee-master-plugin' ),
						'default' => 'default',
						'options' => $redirect_options,
					),
					'logout_redirect_url'   => array(
						'type'    => 'url',
						'label'   => __( 'Custom URL after logout', 'atelier-irisee-master-plugin' ),
						'default' => '',
					),
				),
			),
			'style'        => array(
				'label'  => __( 'Style', 'atelier-irisee-master-plugin' ),
				'fields' => array(
					'color_primary'     => array(
						'type'    => 'color',
						'label'   => __( 'Main color (buttons, borders)', 'atelier-irisee-master-plugin' ),
						'default' => '#b38f4f',
					),
					'color_button_text' => array(
						'type'    => 'color',
						'label'   => __( 'Text on filled buttons', 'atelier-irisee-master-plugin' ),
						'default' => '#ffffff',
					),
					'color_text'        => array(
						'type'    => 'color',
						'label'   => __( 'Text color', 'atelier-irisee-master-plugin' ),
						'default' => '#b38f4f',
					),
					'color_border'      => array(
						'type'    => 'color',
						'label'   => __( 'Input border color', 'atelier-irisee-master-plugin' ),
						'default' => '#b38f4f',
					),
					'color_bg'          => array(
						'type'    => 'color',
						'label'   => __( 'Popup background', 'atelier-irisee-master-plugin' ),
						'default' => '#ffffff',
					),
					'overlay_opacity'   => array(
						'type'    => 'number',
						'label'   => __( 'Dark background behind the popup (%)', 'atelier-irisee-master-plugin' ),
						'default' => 60,
						'min'     => 0,
						'max'     => 100,
					),
					'font_size'         => array(
						'type'    => 'number',
						'label'   => __( 'Font size (px)', 'atelier-irisee-master-plugin' ),
						'default' => 16,
						'min'     => 12,
						'max'     => 24,
					),
					'popup_width'       => array(
						'type'    => 'number',
						'label'   => __( 'Popup width (px)', 'atelier-irisee-master-plugin' ),
						'default' => 480,
						'min'     => 320,
						'max'     => 1100,
					),
					'button_style'      => array(
						'type'    => 'select',
						'label'   => __( 'Buttons', 'atelier-irisee-master-plugin' ),
						'default' => 'round',
						'options' => array(
							'round'  => __( 'Round with double border', 'atelier-irisee-master-plugin' ),
							'square' => __( 'Square, filled', 'atelier-irisee-master-plugin' ),
						),
					),
					'labels'            => array(
						'type'    => 'select',
						'label'   => __( 'Field labels', 'atelier-irisee-master-plugin' ),
						'default' => 'show',
						'options' => array(
							'show'        => __( 'Labels above the fields', 'atelier-irisee-master-plugin' ),
							'placeholder' => __( 'Only placeholders inside the fields', 'atelier-irisee-master-plugin' ),
						),
					),
				),
			),
			'fields'       => array(
				'label'  => __( 'Registration fields', 'atelier-irisee-master-plugin' ),
				'fields' => array(
					'fields' => array(
						'type'    => 'fields_builder',
						'label'   => __( 'Extra fields', 'atelier-irisee-master-plugin' ),
						'default' => array(),
					),
				),
			),
			'security'     => array(
				'label'  => __( 'Security', 'atelier-irisee-master-plugin' ),
				'fields' => array(
					'captcha'             => array(
						'type'    => 'select',
						'label'   => __( 'Captcha', 'atelier-irisee-master-plugin' ),
						'default' => 'none',
						'options' => array(
							'none'         => __( 'None', 'atelier-irisee-master-plugin' ),
							'recaptcha_v2' => 'Google reCAPTCHA v2 (checkbox)',
							'recaptcha_v3' => 'Google reCAPTCHA v3 (invisible)',
							'turnstile'    => 'Cloudflare Turnstile',
							'friendly'     => 'Friendly Captcha',
						),
					),
					'captcha_site_key'    => array(
						'type'    => 'text',
						'label'   => __( 'Site key', 'atelier-irisee-master-plugin' ),
						'default' => '',
					),
					'captcha_secret_key'  => array(
						'type'    => 'secret',
						'label'   => __( 'Secret key', 'atelier-irisee-master-plugin' ),
						'default' => '',
					),
					'recaptcha_threshold' => array(
						'type'    => 'number',
						'label'   => __( 'reCAPTCHA v3 minimum score (0.0–1.0)', 'atelier-irisee-master-plugin' ),
						'default' => 0.5,
						'min'     => 0,
						'max'     => 1,
						'step'    => 0.1,
					),
					'captcha_forms'       => array(
						'type'    => 'checkboxes',
						'label'   => __( 'Protect these forms', 'atelier-irisee-master-plugin' ),
						'default' => array( 'login', 'register', 'lostpw', 'check_email' ),
						'options' => array(
							'login'       => __( 'Login', 'atelier-irisee-master-plugin' ),
							'register'    => __( 'Register', 'atelier-irisee-master-plugin' ),
							'lostpw'      => __( 'Lost password', 'atelier-irisee-master-plugin' ),
							'check_email' => __( 'Email first step', 'atelier-irisee-master-plugin' ),
						),
					),
					'strength_min'        => array(
						'type'    => 'select',
						'label'   => __( 'Minimum password strength', 'atelier-irisee-master-plugin' ),
						'default' => '3',
						'options' => array(
							'0' => __( 'No requirement', 'atelier-irisee-master-plugin' ),
							'1' => __( 'Very weak (at least 6 characters)', 'atelier-irisee-master-plugin' ),
							'2' => __( 'Weak (at least 8 characters)', 'atelier-irisee-master-plugin' ),
							'3' => __( 'Medium (8+ characters with letters and numbers)', 'atelier-irisee-master-plugin' ),
							'4' => __( 'Strong (12+ characters with upper and lower case, numbers and symbols)', 'atelier-irisee-master-plugin' ),
						),
					),
					'limit_enabled'       => array(
						'type'    => 'checkbox',
						'label'   => __( 'Limit failed login attempts', 'atelier-irisee-master-plugin' ),
						'default' => 1,
					),
					'limit_attempts'      => array(
						'type'    => 'number',
						'label'   => __( 'Failed attempts allowed', 'atelier-irisee-master-plugin' ),
						'default' => 5,
						'min'     => 1,
						'max'     => 50,
					),
					'limit_window'        => array(
						'type'    => 'number',
						'label'   => __( 'Within (minutes)', 'atelier-irisee-master-plugin' ),
						'default' => 15,
						'min'     => 1,
						'max'     => 1440,
					),
					'limit_lockout'       => array(
						'type'    => 'number',
						'label'   => __( 'Lock out for (minutes)', 'atelier-irisee-master-plugin' ),
						'default' => 30,
						'min'     => 1,
						'max'     => 10080,
					),
					'rate_limit'          => array(
						'type'    => 'number',
						'label'   => __( 'Registrations and password reset requests per hour (per visitor)', 'atelier-irisee-master-plugin' ),
						'default' => 5,
						'min'     => 1,
						'max'     => 100,
					),
				),
			),
			'verification' => array(
				'label'  => __( 'Verification & approval', 'atelier-irisee-master-plugin' ),
				'fields' => array_merge(
					array(
						'verify_mode'    => array(
							'type'    => 'select',
							'label'   => __( 'Email verification', 'atelier-irisee-master-plugin' ),
							'default' => 'none',
							'options' => array(
								'none' => __( 'No verification', 'atelier-irisee-master-plugin' ),
								'code' => __( 'Verification code (6 digits)', 'atelier-irisee-master-plugin' ),
								'link' => __( 'One-click link', 'atelier-irisee-master-plugin' ),
							),
						),
						'admin_approval' => array(
							'type'    => 'checkbox',
							'label'   => __( 'New accounts need approval by an administrator', 'atelier-irisee-master-plugin' ),
							'default' => 0,
						),
					),
					$emails
				),
			),
			'social'       => array(
				'label'  => __( 'Social login', 'atelier-irisee-master-plugin' ),
				'fields' => $social,
			),
			'address'      => array(
				'label'  => __( 'Address autocomplete', 'atelier-irisee-master-plugin' ),
				'fields' => array(
					'address_enabled'   => array(
						'type'    => 'checkbox',
						'label'   => __( 'Suggest addresses while typing (Google Places)', 'atelier-irisee-master-plugin' ),
						'default' => 0,
					),
					'address_api_key'   => array(
						'type'    => 'text',
						'label'   => __( 'Google API key', 'atelier-irisee-master-plugin' ),
						'default' => '',
						'desc'    => __( 'Enable "Places API (New)" for this key and restrict it to your website address.', 'atelier-irisee-master-plugin' ),
					),
					'address_countries' => array(
						'type'    => 'text',
						'label'   => __( 'Countries (2-letter codes, comma separated)', 'atelier-irisee-master-plugin' ),
						'default' => 'BE,NL,FR,LU',
					),
					'address_checkout'  => array(
						'type'    => 'checkbox',
						'label'   => __( 'Also on the checkout billing and shipping address', 'atelier-irisee-master-plugin' ),
						'default' => 1,
					),
				),
			),
			'tools'        => array(
				'label'  => __( 'Import / export', 'atelier-irisee-master-plugin' ),
				'fields' => array(),
			),
		);
	}

	/**
	 * Default value of every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		static $defaults = null;
		if ( null === $defaults ) {
			$defaults = array();
			foreach ( self::schema() as $tab ) {
				foreach ( $tab['fields'] as $key => $field ) {
					if ( isset( $field['default'] ) ) {
						$defaults[ $key ] = $field['default'];
					}
				}
			}
		}
		return $defaults;
	}

	/* ------------------------------------------------------------------
	 * Admin page
	 * ------------------------------------------------------------------ */

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Login & registration', 'atelier-irisee-master-plugin' ),
			__( 'Login & registration', 'atelier-irisee-master-plugin' ),
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			AIMP_Login::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public static function enqueue( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::PAGE ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/css/admin.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-admin-login', AIMP_PLUGIN_URL . 'assets/js/admin-login.js', array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-login',
			'aimpAdminLogin',
			array(
				'choose' => __( 'Choose image', 'atelier-irisee-master-plugin' ),
				'remove' => __( 'Remove', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	private static function current_tab() {
		$tabs = self::schema();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab selection only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tabs    = self::schema();
		$current = self::current_tab();
		$url     = admin_url( 'admin.php?page=' . self::PAGE );
		?>
		<div class="wrap aimp-login-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>
			<?php self::render_notice(); ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $url ) ); ?>" class="nav-tab<?php echo $key === $current ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'tools' === $current ) : ?>
				<?php self::render_tools(); ?>
			<?php else : ?>
				<?php self::render_tab_intro( $current ); ?>
				<form action="options.php" method="post">
					<?php settings_fields( self::GROUP ); ?>
					<input type="hidden" name="<?php echo esc_attr( AIMP_Login::OPTION ); ?>[_tab]" value="<?php echo esc_attr( $current ); ?>">
					<table class="form-table" role="presentation">
						<?php
						foreach ( $tabs[ $current ]['fields'] as $key => $field ) {
							self::render_row( $key, $field );
						}
						?>
					</table>
					<?php submit_button( __( 'Save settings', 'atelier-irisee-master-plugin' ) ); ?>
				</form>
				<?php
				if ( 'security' === $current ) {
					self::render_lockouts();
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$notice = isset( $_GET['aimp_notice'] ) ? sanitize_key( wp_unslash( $_GET['aimp_notice'] ) ) : '';
		$texts  = array(
			'imported'      => __( 'Settings imported.', 'atelier-irisee-master-plugin' ),
			'import_failed' => __( 'The file could not be imported. Choose a settings file exported by this plugin.', 'atelier-irisee-master-plugin' ),
			'cleared'       => __( 'All lockouts were cleared.', 'atelier-irisee-master-plugin' ),
		);
		if ( isset( $texts[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				'import_failed' === $notice ? 'error' : 'success',
				esc_html( $texts[ $notice ] )
			);
		}
	}

	private static function render_tab_intro( $tab ) {
		$intro = array(
			'general' => sprintf(
				/* translators: 1-3: shortcodes, 4: CSS classes */
				__( 'Shortcodes: %1$s (button or link that opens the popup), %2$s (form inside a page), %3$s (profile form). Any link or button with the class %4$s opens the matching form.', 'atelier-irisee-master-plugin' ),
				'<code>[aimp_login_popup]</code>',
				'<code>[aimp_login_form]</code>',
				'<code>[aimp_profile]</code>',
				'<code>aimp-login-tgr</code>, <code>aimp-reg-tgr</code>, <code>aimp-lostpw-tgr</code>'
			),
			'social'  => __( 'Create an app at each provider and copy its keys here. Register the callback URL shown under each provider exactly as written. Social login needs HTTPS.', 'atelier-irisee-master-plugin' ),
			'fields'  => __( 'Add extra fields to the registration and profile forms. Drag rows to change the order. A field saved as a WooCommerce billing or shipping field also fills in the checkout.', 'atelier-irisee-master-plugin' ),
		);
		if ( isset( $intro[ $tab ] ) ) {
			echo '<p class="aimp-tab-intro">' . wp_kses_post( $intro[ $tab ] ) . '</p>';
		}
	}

	/**
	 * One settings row.
	 *
	 * @param string $key   Setting key.
	 * @param array  $field Definition.
	 */
	private static function render_row( $key, $field ) {
		$name  = AIMP_Login::OPTION . '[' . $key . ']';
		$id    = 'aimp_login_' . $key;
		$value = AIMP_Login::opt( $key );

		if ( 'heading' === $field['type'] ) {
			echo '<tr class="aimp-heading-row"><th colspan="2"><h2>' . esc_html( $field['label'] ) . '</h2>';
			if ( ! empty( $field['provider'] ) ) {
				echo '<p class="description">' . esc_html__( 'Callback / redirect URL:', 'atelier-irisee-master-plugin' ) . ' <code>' . esc_html( AIMP_Login_Social::callback_url( $field['provider'] ) ) . '</code></p>';
			}
			echo '</th></tr>';
			return;
		}
		if ( 'info' === $field['type'] ) {
			echo '<tr><th>' . esc_html( $field['label'] ) . '</th><td><p class="description">' . esc_html( $field['desc'] ) . '</p></td></tr>';
			return;
		}

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';

		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s> %4$s</label>',
					esc_attr( $name ),
					esc_attr( $id ),
					checked( 1, (int) $value, false ),
					esc_html__( 'Yes', 'atelier-irisee-master-plugin' )
				);
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $field['options'] as $option => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'number':
				printf(
					'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( isset( $field['min'] ) ? $field['min'] : '' ),
					esc_attr( isset( $field['max'] ) ? $field['max'] : '' ),
					esc_attr( isset( $field['step'] ) ? $field['step'] : 1 )
				);
				break;

			case 'color':
				printf( '<input type="text" class="aimp-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="%4$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $field['default'] ) );
				break;

			case 'textarea':
			case 'secret_textarea':
				printf( '<textarea class="large-text" rows="%4$d" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ), 'secret_textarea' === $field['type'] ? 6 : 4 );
				break;

			case 'secret':
				printf( '<input type="password" class="regular-text" autocomplete="new-password" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;

			case 'url':
				printf( '<input type="url" class="regular-text" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;

			case 'page':
				wp_dropdown_pages(
					array(
						'name'              => $name, // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by wp_dropdown_pages.
						'id'                => $id,
						'selected'          => (int) $value,
						'show_option_none'  => __( '— Select —', 'atelier-irisee-master-plugin' ),
						'option_none_value' => 0,
					)
				);
				break;

			case 'pages_multi':
				$selected = array_map( 'absint', (array) $value );
				echo '<select multiple size="6" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" style="min-width:300px">';
				foreach ( get_pages() as $page ) {
					printf( '<option value="%d" %s>%s</option>', (int) $page->ID, selected( in_array( (int) $page->ID, $selected, true ), true, false ), esc_html( $page->post_title ) );
				}
				echo '</select>';
				break;

			case 'checkboxes':
				$selected = (array) $value;
				echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
				foreach ( $field['options'] as $option => $label ) {
					printf(
						'<label style="margin-right:1.5em"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
						esc_attr( $name ),
						esc_attr( $option ),
						checked( in_array( $option, $selected, true ), true, false ),
						esc_html( $label )
					);
				}
				break;

			case 'media':
				printf(
					'<div class="aimp-media"><input type="url" class="regular-text" id="%1$s" name="%2$s" value="%3$s"> <button type="button" class="button aimp-media-choose">%4$s</button> <button type="button" class="button-link aimp-media-remove">%5$s</button><div class="aimp-media-preview">%6$s</div></div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_html__( 'Choose image', 'atelier-irisee-master-plugin' ),
					esc_html__( 'Remove', 'atelier-irisee-master-plugin' ),
					$value ? '<img src="' . esc_url( $value ) . '" alt="">' : ''
				);
				break;

			case 'fields_builder':
				AIMP_Login_Fields::render_builder( $name, (array) $value );
				break;

			default:
				printf( '<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}

		if ( ! empty( $field['desc'] ) && 'info' !== $field['type'] ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/* ------------------------------------------------------------------
	 * Sanitizing
	 * ------------------------------------------------------------------ */

	/**
	 * Sanitize settings.
	 *
	 * - From the settings form (has "_tab"): only that tab is sanitized and merged into the stored
	 *   settings, so the other tabs stay untouched.
	 * - Anything else (import, or WordPress sanitizing a second time when the option is first created):
	 *   treated as a complete set; every key is sanitized and missing keys get their default.
	 *   Running this twice gives the same result.
	 *
	 * @param array $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$schema = self::schema();
		$tab    = isset( $input['_tab'] ) ? (string) $input['_tab'] : '';

		if ( isset( $schema[ $tab ] ) ) {
			$stored = get_option( AIMP_Login::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();
			$tabs   = array( $tab );
			$full   = false;
		} else {
			$stored = array();
			$tabs   = array_keys( $schema );
			$full   = true;
		}

		foreach ( $tabs as $tab_key ) {
			foreach ( $schema[ $tab_key ]['fields'] as $key => $field ) {
				if ( in_array( $field['type'], array( 'heading', 'info' ), true ) ) {
					continue;
				}
				if ( array_key_exists( $key, $input ) ) {
					$raw = $input[ $key ];
				} else {
					// Form: a missing key means "empty" (e.g. no checkboxes ticked). Full set: use the default.
					$raw = $full ? ( isset( $field['default'] ) ? $field['default'] : null ) : null;
				}
				$stored[ $key ] = self::sanitize_value( $raw, $field );
			}
		}

		AIMP_Login::flush_cache();
		return $stored;
	}

	private static function sanitize_value( $raw, $field ) {
		$default = isset( $field['default'] ) ? $field['default'] : '';
		switch ( $field['type'] ) {
			case 'checkbox':
				return empty( $raw ) ? 0 : 1;
			case 'select':
				return ( null !== $raw && isset( $field['options'][ (string) $raw ] ) ) ? (string) $raw : $default;
			case 'number':
				if ( null === $raw || '' === $raw ) {
					return $default;
				}
				$number = (float) $raw;
				if ( isset( $field['min'] ) ) {
					$number = max( (float) $field['min'], $number );
				}
				if ( isset( $field['max'] ) ) {
					$number = min( (float) $field['max'], $number );
				}
				return ( isset( $field['step'] ) && $field['step'] < 1 ) ? $number : (int) $number;
			case 'color':
				$color = sanitize_hex_color( (string) $raw );
				return $color ? $color : $default;
			case 'textarea':
				return null === $raw ? '' : sanitize_textarea_field( (string) $raw );
			case 'secret_textarea':
				// Keys contain line breaks and +/= characters: keep them, strip tags.
				return null === $raw ? '' : trim( wp_strip_all_tags( (string) $raw ) );
			case 'url':
			case 'media':
				return null === $raw ? '' : esc_url_raw( (string) $raw );
			case 'page':
				return absint( $raw );
			case 'pages_multi':
				return array_values( array_filter( array_map( 'absint', (array) $raw ) ) );
			case 'checkboxes':
				return array_values( array_intersect( array_map( 'sanitize_key', (array) $raw ), array_keys( $field['options'] ) ) );
			case 'fields_builder':
				return AIMP_Login_Fields::sanitize_definitions( (array) $raw );
			default:
				return null === $raw ? '' : sanitize_text_field( (string) $raw );
		}
	}

	/* ------------------------------------------------------------------
	 * Import / export & lockouts
	 * ------------------------------------------------------------------ */

	private static function render_tools() {
		?>
		<h2><?php esc_html_e( 'Export', 'atelier-irisee-master-plugin' ); ?></h2>
		<p><?php esc_html_e( 'Download all login & registration settings as a file, for example to copy them to another site.', 'atelier-irisee-master-plugin' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aimp_login_export">
			<?php wp_nonce_field( 'aimp_login_export' ); ?>
			<?php submit_button( __( 'Download settings', 'atelier-irisee-master-plugin' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Import', 'atelier-irisee-master-plugin' ); ?></h2>
		<p><?php esc_html_e( 'Upload a settings file. All current login & registration settings are replaced.', 'atelier-irisee-master-plugin' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aimp_login_import">
			<?php wp_nonce_field( 'aimp_login_import' ); ?>
			<input type="file" name="aimp_settings_file" accept=".json,application/json" required>
			<?php submit_button( __( 'Import settings', 'atelier-irisee-master-plugin' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	public static function export() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_login_export' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$data = array(
			'plugin'   => 'atelier-irisee-master-plugin',
			'version'  => AIMP_VERSION,
			'settings' => get_option( AIMP_Login::OPTION, array() ),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=atelier-irisee-login-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function import() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_login_import' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$back = admin_url( 'admin.php?page=' . self::PAGE . '&tab=tools' );
		// The temporary path is set by PHP itself and checked with is_uploaded_file(); unslashing would break Windows paths.
		$file = isset( $_FILES['aimp_settings_file']['tmp_name'] ) ? (string) $_FILES['aimp_settings_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$json = ( $file && is_uploaded_file( $file ) ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local uploaded file.

		if ( ! is_array( $json ) || empty( $json['settings'] ) || ! is_array( $json['settings'] ) || 'atelier-irisee-master-plugin' !== ( $json['plugin'] ?? '' ) ) {
			wp_safe_redirect( add_query_arg( 'aimp_notice', 'import_failed', $back ) );
			exit;
		}

		// The whole file is sanitized as a complete set (see sanitize()); keys missing from the file
		// fall back to their defaults. update_option() runs the same sanitizing again, which is harmless.
		$settings = $json['settings'];
		unset( $settings['_tab'] );
		update_option( AIMP_Login::OPTION, self::sanitize( $settings ) );

		wp_safe_redirect( add_query_arg( 'aimp_notice', 'imported', $back ) );
		exit;
	}

	private static function render_lockouts() {
		$lockouts = AIMP_Login_Security::lockouts();
		echo '<h2>' . esc_html__( 'Current lockouts', 'atelier-irisee-master-plugin' ) . '</h2>';
		if ( ! $lockouts ) {
			echo '<p>' . esc_html__( 'Nobody is locked out at the moment.', 'atelier-irisee-master-plugin' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>' . esc_html__( 'Locked', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Until', 'atelier-irisee-master-plugin' ) . '</th></tr></thead><tbody>';
		foreach ( $lockouts as $lockout ) {
			printf(
				'<tr><td>%s</td><td>%s</td></tr>',
				esc_html( $lockout['label'] ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lockout['until'] ) )
			);
		}
		echo '</tbody></table>';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aimp_login_clear_lockouts">
			<?php wp_nonce_field( 'aimp_login_clear_lockouts' ); ?>
			<?php submit_button( __( 'Clear all lockouts', 'atelier-irisee-master-plugin' ), 'secondary' ); ?>
		</form>
		<?php
	}

	public static function clear_lockouts() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_login_clear_lockouts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		AIMP_Login_Security::clear_lockouts();
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&tab=security&aimp_notice=cleared' ) );
		exit;
	}
}
