<?php
/**
 * Login & registration module: bootstrap, popup, shortcodes, menu items, redirects, templates and assets.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login {

	const OPTION = 'aimp_login';
	const NONCE  = 'aimp_el';

	/** @var array|null Cached settings. */
	private static $options = null;

	/** @var int Counter for unique field IDs when several forms are on one page. */
	private static $instance = 0;

	/** @var bool Whether the front-end assets are needed on this page. */
	private static $needs_assets = false;

	public static function init() {
		$dir = AIMP_PLUGIN_DIR . 'includes/login/';
		require_once $dir . 'class-aimp-login-settings.php';
		require_once $dir . 'class-aimp-login-fields.php';
		require_once $dir . 'class-aimp-login-security.php';
		require_once $dir . 'class-aimp-login-verification.php';
		require_once $dir . 'class-aimp-login-ajax.php';
		require_once $dir . 'class-aimp-login-social.php';
		require_once $dir . 'class-aimp-login-woocommerce.php';
		require_once $dir . 'class-aimp-address-autocomplete.php';

		AIMP_Login_Settings::init();
		AIMP_Login_Fields::init();
		AIMP_Login_Security::init();
		AIMP_Login_Verification::init();
		AIMP_Login_Ajax::init();
		AIMP_Login_Social::init();
		AIMP_Login_WooCommerce::init();
		AIMP_Address_Autocomplete::init();

		add_shortcode( 'aimp_login_popup', array( __CLASS__, 'shortcode_popup' ) );
		add_shortcode( 'aimp_login_form', array( __CLASS__, 'shortcode_form' ) );
		add_shortcode( 'aimp_profile', array( __CLASS__, 'shortcode_profile' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_popup' ), 5 );
		add_action( 'admin_head-nav-menus.php', array( __CLASS__, 'add_menu_meta_box' ) );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'menu_items' ), 20 );
		add_filter( 'logout_redirect', array( __CLASS__, 'logout_redirect' ), 20, 3 );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * One setting (stored value, or its default).
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function opt( $key ) {
		if ( null === self::$options ) {
			$stored        = get_option( self::OPTION, array() );
			self::$options = array_merge( AIMP_Login_Settings::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return isset( self::$options[ $key ] ) ? self::$options[ $key ] : null;
	}

	public static function flush_cache() {
		self::$options = null;
		// The Apple client secret is signed with the stored key: rebuild it after a settings change.
		delete_transient( 'aimp_el_apple_secret' );
	}

	public static function enabled() {
		return (bool) self::opt( 'enabled' );
	}

	/**
	 * Registration is allowed when WooCommerce allows it on My Account or WordPress allows it.
	 *
	 * @return bool
	 */
	public static function registration_enabled() {
		return 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) || (bool) get_option( 'users_can_register' );
	}

	/* ------------------------------------------------------------------
	 * Templates
	 * ------------------------------------------------------------------ */

	/**
	 * Render a form template. Themes can override it in yourtheme/atelier-irisee/forms/{name}.php.
	 *
	 * @param string $name Template name without .php.
	 * @param array  $vars Variables for the template.
	 * @return string
	 */
	public static function template( $name, $vars = array() ) {
		$name     = sanitize_file_name( $name );
		$template = locate_template( 'atelier-irisee/forms/' . $name . '.php' );
		if ( ! $template ) {
			$template = AIMP_PLUGIN_DIR . 'templates/forms/' . $name . '.php';
		}
		if ( ! file_exists( $template ) ) {
			return '';
		}
		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables.
		extract( $vars, EXTR_SKIP );
		include $template;
		return ob_get_clean();
	}

	/**
	 * Start of every form: the message area.
	 *
	 * @return string
	 */
	public static function form_top() {
		return '<div class="aimp-el-notice" role="alert" hidden></div>';
	}

	/**
	 * End of every form: the invisible honeypot field and, when enabled for this form, the captcha.
	 *
	 * @param string $form Form key (login, register, lostpw, check_email…).
	 * @return string
	 */
	public static function form_bottom( $form ) {
		$html = '<div class="aimp-el-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'atelier-irisee-master-plugin' ) . ' <input type="text" name="aimp_hp" value="" tabindex="-1" autocomplete="off"></label></div>';
		if ( AIMP_Login_Security::form_has_captcha( $form ) ) {
			$html .= '<div class="aimp-el-captcha" data-aimp-captcha></div>';
		}
		return $html;
	}

	/**
	 * The complete set of forms (login, register, lost/reset password, verification) in one container.
	 *
	 * @param array $args [ active, context, login_redirect, register_redirect ].
	 * @return string
	 */
	public static function container( $args = array() ) {
		self::$needs_assets = true;
		self::enqueue_assets();
		self::$instance++;
		$args = wp_parse_args(
			$args,
			array(
				'active'            => 'login',
				'context'           => 'inline',
				'login_redirect'    => '',
				'register_redirect' => '',
			)
		);
		if ( 'register' === $args['active'] && ! self::registration_enabled() ) {
			$args['active'] = 'login';
		}
		if ( self::opt( 'single_field' ) && in_array( $args['active'], array( 'login', 'register' ), true ) ) {
			$args['active'] = 'email';
		}
		$args['prefix']   = 'aimp-el-' . self::$instance;
		$args['register'] = self::registration_enabled();

		do_action( 'aimp_el_before_form', $args );
		$html = self::template( 'container', $args );
		do_action( 'aimp_el_after_form', $args );
		return $html;
	}

	/* ------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------ */

	/**
	 * [aimp_login_popup type="login|register|lostpw" display="button|link" text="" change_to="logout|myaccount|none" redirect_to="same|url"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_popup( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'        => 'login',
				'display'     => 'link',
				'text'        => '',
				'change_to'   => 'logout',
				'redirect_to' => '',
			),
			$atts,
			'aimp_login_popup'
		);
		$button = 'button' === $atts['display'] ? ' aimp-el-button' : '';

		if ( is_user_logged_in() ) {
			if ( 'logout' === $atts['change_to'] ) {
				return '<a class="aimp-el-logout' . esc_attr( $button ) . '" href="' . esc_url( wp_logout_url( self::current_url() ) ) . '">' . esc_html__( 'Log out', 'atelier-irisee-master-plugin' ) . '</a>';
			}
			if ( 'myaccount' === $atts['change_to'] ) {
				return '<a class="aimp-el-myaccount' . esc_attr( $button ) . '" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'My account', 'atelier-irisee-master-plugin' ) . '</a>';
			}
			return '';
		}

		self::$needs_assets = true;
		self::enqueue_assets();
		$types = array(
			'login'    => array( 'aimp-login-tgr', __( 'Log in', 'atelier-irisee-master-plugin' ) ),
			'register' => array( 'aimp-reg-tgr', __( 'Register', 'atelier-irisee-master-plugin' ) ),
			'lostpw'   => array( 'aimp-lostpw-tgr', __( 'Forgot your password?', 'atelier-irisee-master-plugin' ) ),
		);
		$type     = isset( $types[ $atts['type'] ] ) ? $types[ $atts['type'] ] : $types['login'];
		$text     = '' !== $atts['text'] ? $atts['text'] : $type[1];
		$redirect = 'same' === $atts['redirect_to'] ? self::current_url() : ( $atts['redirect_to'] ? $atts['redirect_to'] : '' );

		return sprintf(
			'<a href="#" class="%1$s%2$s"%3$s>%4$s</a>',
			esc_attr( $type[0] ),
			esc_attr( $button ),
			$redirect ? ' data-aimp-redirect="' . esc_url( $redirect ) . '"' : '',
			esc_html( $text )
		);
	}

	/**
	 * [aimp_login_form active="login|register" login_redirect="" register_redirect=""]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'active'            => 'login',
				'login_redirect'    => '',
				'register_redirect' => '',
			),
			$atts,
			'aimp_login_form'
		);
		if ( is_user_logged_in() ) {
			return self::logged_in_notice();
		}
		return self::container(
			array(
				'active'            => in_array( $atts['active'], array( 'login', 'register', 'lostpw' ), true ) ? $atts['active'] : 'login',
				'context'           => 'inline',
				'login_redirect'    => esc_url_raw( $atts['login_redirect'] ),
				'register_redirect' => esc_url_raw( $atts['register_redirect'] ),
			)
		);
	}

	public static function shortcode_profile() {
		if ( ! is_user_logged_in() ) {
			return '<p class="aimp-el-info">' . esc_html__( 'Please log in to edit your profile.', 'atelier-irisee-master-plugin' ) . '</p>' . self::container( array( 'context' => 'inline' ) );
		}
		self::$needs_assets = true;
		self::enqueue_assets();
		return self::template(
			'profile',
			array(
				'user'   => wp_get_current_user(),
				'prefix' => 'aimp-el-profile',
			)
		);
	}

	public static function logged_in_notice() {
		$user = wp_get_current_user();
		return '<p class="aimp-el-info">' . sprintf(
			/* translators: 1: user name, 2: logout link */
			esc_html__( 'You are logged in as %1$s. %2$s', 'atelier-irisee-master-plugin' ),
			'<strong>' . esc_html( $user->display_name ) . '</strong>',
			'<a href="' . esc_url( wp_logout_url( self::current_url() ) ) . '">' . esc_html__( 'Log out', 'atelier-irisee-master-plugin' ) . '</a>'
		) . '</p>';
	}

	/* ------------------------------------------------------------------
	 * Popup
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the popup is printed: for logged-out visitors, or when a link from an email needs it.
	 *
	 * @return bool
	 */
	private static function popup_needed() {
		if ( ! self::enabled() || is_admin() ) {
			return false;
		}
		return ! is_user_logged_in();
	}

	public static function render_popup() {
		if ( ! self::popup_needed() ) {
			return;
		}
		self::$needs_assets = true;
		self::enqueue_assets();
		echo self::template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
			'popup',
			array(
				'layout'    => self::opt( 'layout' ),
				'animation' => self::opt( 'animation' ),
				'image'     => self::opt( 'sidebar_image' ),
				'container' => self::container( array( 'context' => 'popup' ) ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public static function register_assets() {
		wp_register_style( 'aimp-login', AIMP_PLUGIN_URL . 'assets/css/login.css', array(), AIMP_VERSION );
		wp_register_script( 'aimp-login', AIMP_PLUGIN_URL . 'assets/js/login.js', array( 'password-strength-meter' ), AIMP_VERSION, true );

		// Logged-out visitors get the popup on every page: load the stylesheet in <head> right away.
		if ( self::popup_needed() || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Enqueue CSS + JS with their settings. Safe to call several times.
	 */
	public static function enqueue_assets() {
		if ( wp_script_is( 'aimp-login', 'enqueued' ) ) {
			return;
		}
		if ( ! wp_script_is( 'aimp-login', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-login' );
		wp_add_inline_style( 'aimp-login', self::inline_css() );
		wp_enqueue_script( 'aimp-login' );
		wp_localize_script( 'aimp-login', 'aimpLogin', self::script_data() );
	}

	/**
	 * Style settings as CSS variables.
	 *
	 * @return string
	 */
	private static function inline_css() {
		$vars = array(
			'--aimp-el-primary'     => self::opt( 'color_primary' ),
			'--aimp-el-button-text' => self::opt( 'color_button_text' ),
			'--aimp-el-text'        => self::opt( 'color_text' ),
			'--aimp-el-border'      => self::opt( 'color_border' ),
			'--aimp-el-bg'          => self::opt( 'color_bg' ),
			'--aimp-el-overlay'     => 'rgba(0,0,0,' . ( (int) self::opt( 'overlay_opacity' ) / 100 ) . ')',
			'--aimp-el-font-size'   => (int) self::opt( 'font_size' ) . 'px',
			'--aimp-el-width'       => (int) self::opt( 'popup_width' ) . 'px',
		);
		$css = ':root{';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . esc_attr( $value ) . ';';
		}
		return $css . '}';
	}

	/**
	 * Texts for login.js in the active language.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'loading'        => __( 'Please wait…', 'atelier-irisee-master-plugin' ),
			'error'          => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
			'show'           => __( 'Show password', 'atelier-irisee-master-plugin' ),
			'hide'           => __( 'Hide password', 'atelier-irisee-master-plugin' ),
			'strength'       => array(
				'0' => __( 'Very weak', 'atelier-irisee-master-plugin' ),
				'1' => __( 'Very weak', 'atelier-irisee-master-plugin' ),
				'2' => __( 'Weak', 'atelier-irisee-master-plugin' ),
				'3' => __( 'Medium', 'atelier-irisee-master-plugin' ),
				'4' => __( 'Strong', 'atelier-irisee-master-plugin' ),
			),
			'mismatch'       => __( 'The passwords do not match.', 'atelier-irisee-master-plugin' ),
			'required'       => __( 'This field is required.', 'atelier-irisee-master-plugin' ),
			'captcha'        => __( 'Please complete the captcha.', 'atelier-irisee-master-plugin' ),
			'noResults'      => __( 'No addresses found.', 'atelier-irisee-master-plugin' ),
			'messages'       => array(
				'verified'      => __( 'Your email address is verified. You can log in now.', 'atelier-irisee-master-plugin' ),
				'verified_wait' => __( 'Your email address is verified. An administrator will review your account; you will get an email once it is approved.', 'atelier-irisee-master-plugin' ),
				'invalid'       => __( 'This link is invalid or has expired.', 'atelier-irisee-master-plugin' ),
				'email_changed' => __( 'Your email address has been changed.', 'atelier-irisee-master-plugin' ),
				'social_error'  => __( 'Logging in with this account did not work. Please try again or use your email address.', 'atelier-irisee-master-plugin' ),
				'social_exists' => __( 'An account with this email address already exists. Log in with your password, then connect this account under My Account.', 'atelier-irisee-master-plugin' ),
				'check_email'   => __( 'Almost done! We sent you an email with a link to confirm your email address.', 'atelier-irisee-master-plugin' ),
				'account_unverified' => __( 'Please verify your email address first. Check your inbox (and spam folder).', 'atelier-irisee-master-plugin' ),
				'account_pending'    => __( 'Your account is waiting for approval. You will get an email as soon as it is approved.', 'atelier-irisee-master-plugin' ),
				'account_rejected'   => __( 'Your account has not been approved.', 'atelier-irisee-master-plugin' ),
				'account_removed'    => __( 'Your account has been removed. Thank you for having been our customer.', 'atelier-irisee-master-plugin' ),
			),
		);
	}

	private static function script_data() {
		$i18n = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			$i18n[ $code ] = AIMP_I18n::with_language( $code, array( __CLASS__, 'strings' ) );
		}
		$auto_pages = array_map( 'absint', (array) self::opt( 'auto_open_pages' ) );

		return array(
			'endpoint'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'defaultLanguage' => AIMP_I18n::default_language(),
			'i18n'            => $i18n,
			'singleField'     => (bool) self::opt( 'single_field' ),
			'strengthMin'     => (int) self::opt( 'strength_min' ),
			'autoOpen'        => self::opt( 'auto_open' ) && ! is_user_logged_in() && ( ! $auto_pages || is_page( $auto_pages ) ),
			'autoOpenForm'    => self::opt( 'auto_open_form' ),
			'autoOpenDelay'   => (int) self::opt( 'auto_open_delay' ),
			'checkoutPopup'   => (bool) self::opt( 'checkout_popup' ),
			'captcha'         => AIMP_Login_Security::script_config(),
		);
	}

	/* ------------------------------------------------------------------
	 * Redirects
	 * ------------------------------------------------------------------ */

	public static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $uri );
	}

	/**
	 * Where to go after login or registration. '' means: reload the current page.
	 *
	 * @param string $type     'login' or 'register'.
	 * @param string $override Redirect from the shortcode, trigger or ?aimp_redirect= (wins over the setting).
	 * @param string $current  The page the form was used on.
	 * @param int    $user_id  User ID.
	 * @return string
	 */
	public static function redirect_url( $type, $override, $current, $user_id ) {
		$fallback = '';
		if ( $override ) {
			$url = $override;
		} else {
			switch ( self::opt( $type . '_redirect' ) ) {
				case 'myaccount':
					$url = wc_get_page_permalink( 'myaccount' );
					break;
				case 'custom':
					$url = self::opt( $type . '_redirect_url' );
					break;
				case 'default':
					$url = user_can( $user_id, 'edit_posts' ) ? admin_url() : wc_get_page_permalink( 'myaccount' );
					break;
				default:
					$url = $current;
			}
		}
		// Never redirect away from the site, and never back into a password-reset or verification link.
		$url = $url ? wp_validate_redirect( $url, $fallback ) : $fallback;
		if ( $url ) {
			// remove_query_arg() would use the current request URL for an empty string, so only call it with a URL.
			$url = remove_query_arg( array( 'aimp_el', 'aimp_el_msg', 'key', 'login', 'aimp_token' ), $url );
		}
		return (string) apply_filters( 'aimp_el_redirect', $url, $type, $user_id );
	}

	public static function logout_redirect( $redirect_to, $requested, $user ) {
		switch ( self::opt( 'logout_redirect' ) ) {
			case 'same':
				$same = $requested ? $requested : wp_get_referer();
				return $same ? wp_validate_redirect( $same, home_url( '/' ) ) : home_url( '/' );
			case 'myaccount':
				return wc_get_page_permalink( 'myaccount' );
			case 'custom':
				$url = self::opt( 'logout_redirect_url' );
				return $url ? wp_validate_redirect( $url, home_url( '/' ) ) : $redirect_to;
		}
		return $redirect_to;
	}

	/* ------------------------------------------------------------------
	 * Menu items
	 * ------------------------------------------------------------------ */

	/**
	 * Menu item URLs: placeholder => [ label ].
	 *
	 * @return array
	 */
	private static function menu_links() {
		return array(
			'#aimp-login'           => __( 'Log in', 'atelier-irisee-master-plugin' ),
			'#aimp-register'        => __( 'Register', 'atelier-irisee-master-plugin' ),
			'#aimp-login-logout'    => __( 'Log in / Log out', 'atelier-irisee-master-plugin' ),
			'#aimp-login-myaccount' => __( 'Log in / My account', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function add_menu_meta_box() {
		add_meta_box( 'aimp-el-menu', __( 'Atelier Irisee login', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_menu_meta_box' ), 'nav-menus', 'side', 'default' );
	}

	public static function render_menu_meta_box() {
		$i = -1;
		?>
		<div id="aimp-el-menu-items" class="posttypediv">
			<div class="tabs-panel tabs-panel-active">
				<ul class="categorychecklist form-no-clear">
					<?php foreach ( self::menu_links() as $url => $label ) : ?>
						<li>
							<label class="menu-item-title"><input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo (int) $i; ?>][menu-item-object-id]" value="<?php echo (int) $i; ?>"> <?php echo esc_html( $label ); ?></label>
							<input type="hidden" class="menu-item-type" name="menu-item[<?php echo (int) $i; ?>][menu-item-type]" value="custom">
							<input type="hidden" class="menu-item-title" name="menu-item[<?php echo (int) $i; ?>][menu-item-title]" value="<?php echo esc_attr( $label ); ?>">
							<input type="hidden" class="menu-item-url" name="menu-item[<?php echo (int) $i; ?>][menu-item-url]" value="<?php echo esc_attr( $url ); ?>">
						</li>
						<?php $i--; ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<p class="button-controls">
				<span class="add-to-menu">
					<input type="submit" class="button submit-add-to-menu right" value="<?php esc_attr_e( 'Add to menu', 'atelier-irisee-master-plugin' ); ?>" name="add-aimp-el-menu-item" id="submit-aimp-el-menu-items">
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Turn the placeholder menu items into popup triggers, or logout / My account links when logged in.
	 *
	 * @param WP_Post[] $items Menu items.
	 * @return WP_Post[]
	 */
	public static function menu_items( $items ) {
		$logged_in = is_user_logged_in();
		foreach ( $items as $index => $item ) {
			$url = isset( $item->url ) ? (string) $item->url : '';
			if ( 0 !== strpos( $url, '#aimp-' ) ) {
				continue;
			}
			if ( $logged_in ) {
				if ( '#aimp-login-logout' === $url ) {
					$item->title = __( 'Log out', 'atelier-irisee-master-plugin' );
					$item->url   = wp_logout_url( self::current_url() );
				} elseif ( '#aimp-login-myaccount' === $url ) {
					$item->title = __( 'My account', 'atelier-irisee-master-plugin' );
					$item->url   = wc_get_page_permalink( 'myaccount' );
				} else {
					unset( $items[ $index ] );
				}
				continue;
			}
			if ( ! self::enabled() ) {
				// Without the popup, send visitors to the normal My Account login page.
				$item->url = wc_get_page_permalink( 'myaccount' );
				continue;
			}
			$item->classes   = (array) $item->classes;
			$item->classes[] = '#aimp-register' === $url ? 'aimp-reg-tgr' : 'aimp-login-tgr';
			$item->url       = '#';
		}
		return array_values( $items );
	}
}
