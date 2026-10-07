<?php
/**
 * Atelier Irisee footer: company details with social icons, "My account" and "Customer service" menus,
 * a newsletter sign-up, payment logos, legal links and the copyright line.
 *
 * With "Use the Atelier Irisee footer" on, it replaces the theme footer (Divi's "Designed by Elegant
 * Themes | Powered by WordPress" bar included). Newsletter addresses are stored on the site, with a list
 * and a CSV download under WooCommerce → Newsletter.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Footer {

	const OPTION      = 'aimp_footer';
	const TAG         = 'atelier_irisee_footer';
	const SUBSCRIBERS = 'aimp_subscriber';
	const ADMIN_PAGE  = 'aimp-newsletter';

	/** Menu locations: key => label. */
	public static function menus() {
		return array(
			'aimp_footer_account' => __( 'Footer: My account', 'atelier-irisee-master-plugin' ),
			'aimp_footer_service' => __( 'Footer: Customer service', 'atelier-irisee-master-plugin' ),
			'aimp_footer_legal'   => __( 'Footer: Legal links', 'atelier-irisee-master-plugin' ),
		);
	}

	private static $printed = false;

	public static function init() {
		add_action( 'after_setup_theme', array( __CLASS__, 'register_menus' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wc_ajax_aimp_subscribe', array( __CLASS__, 'subscribe' ) );
		add_action( 'wc_ajax_aimp_newsletter_status', array( __CLASS__, 'ajax_status' ) );
		add_action( 'wc_ajax_aimp_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'wc_ajax_aimp_newsletter_resend', array( __CLASS__, 'resend' ) );
		add_action( 'template_redirect', array( __CLASS__, 'confirm' ) );
		add_action( 'template_redirect', array( __CLASS__, 'unsubscribe_link' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email' ) );
		add_action( 'admin_post_aimp_newsletter_action', array( __CLASS__, 'admin_subscriber_action' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 70 );
		add_action( 'admin_post_aimp_newsletter_csv', array( __CLASS__, 'download_csv' ) );
		add_action( 'admin_post_aimp_newsletter_delete', array( __CLASS__, 'delete_subscriber' ) );

		if ( ! self::get( 'enabled' ) || is_admin() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_filter( 'render_block_core/template-part', array( __CLASS__, 'replace_block_footer' ), 20, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'classic_footer' ), 1 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function register_menus() {
		register_nav_menus( self::menus() );
	}

	public static function register_post_type() {
		register_post_type(
			self::SUBSCRIBERS,
			array(
				'label'           => __( 'Newsletter', 'atelier-irisee-master-plugin' ),
				'public'          => false,
				'show_ui'         => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Settings (WooCommerce → Atelier Irisee → Footer)
	 * ------------------------------------------------------------------ */

	private static function defaults() {
		return array(
			'enabled'         => 0,
			'company'         => get_bloginfo( 'name' ),
			'address'         => '',
			'phone'           => '',
			'email'           => '',
			'facebook'        => '',
			'instagram'       => '',
			'tiktok'          => '',
			'pinterest'       => '',
			'newsletter_text' => '',
			'payment_logos'   => '',
			'copyright'       => '',
		);
	}

	/**
	 * @param string $key Setting.
	 * @return mixed
	 */
	public static function get( $key ) {
		$options = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $options[ $key ] ) ? $options[ $key ] : '';
	}

	public static function register_settings() {
		register_setting(
			'aimp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
		add_settings_section( 'aimp_footer', __( 'Footer', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'section_intro' ), AIMP_Settings::PAGE );
		foreach ( self::fields() as $key => $field ) {
			add_settings_field(
				'aimp_footer_' . $key,
				$field[0],
				array( __CLASS__, 'render_field' ),
				AIMP_Settings::PAGE,
				'aimp_footer',
				array(
					'key'       => $key,
					'type'      => $field[1],
					'label_for' => 'aimp_footer_' . $key,
				)
			);
		}
	}

	/**
	 * Footer settings: key => [ label, type ].
	 *
	 * @return array
	 */
	private static function fields() {
		return array(
			'enabled'         => array( __( 'Footer', 'atelier-irisee-master-plugin' ), 'checkbox' ),
			'company'         => array( __( 'Company name', 'atelier-irisee-master-plugin' ), 'text' ),
			'address'         => array( __( 'Address', 'atelier-irisee-master-plugin' ), 'textarea' ),
			'phone'           => array( __( 'Phone', 'atelier-irisee-master-plugin' ), 'text' ),
			'email'           => array( __( 'Email', 'atelier-irisee-master-plugin' ), 'email' ),
			'facebook'        => array( 'Facebook', 'url' ),
			'instagram'       => array( 'Instagram', 'url' ),
			'tiktok'          => array( 'TikTok', 'url' ),
			'pinterest'       => array( 'Pinterest', 'url' ),
			'newsletter_text' => array( __( 'Newsletter text', 'atelier-irisee-master-plugin' ), 'textarea' ),
			'payment_logos'   => array( __( 'Payment logos', 'atelier-irisee-master-plugin' ), 'images' ),
			'copyright'       => array( __( 'Copyright line', 'atelier-irisee-master-plugin' ), 'text' ),
		);
	}

	public static function section_intro() {
		echo '<p>' . esc_html__( 'The footer at the bottom of every page. The links come from the menus "Footer: My account", "Footer: Customer service" and "Footer: Legal links" (Appearance → Menus). Fields left empty are not shown.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	/**
	 * @param array $args [ key, type ].
	 */
	public static function render_field( $args ) {
		$key   = $args['key'];
		$name  = self::OPTION . '[' . $key . ']';
		$id    = 'aimp_footer_' . $key;
		$value = self::get( $key );
		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label><p class="description">%5$s</p>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html__( 'Use the Atelier Irisee footer', 'atelier-irisee-master-plugin' ),
					esc_html__( 'Replaces the theme footer on every page, including Divi\'s "Designed by Elegant Themes | Powered by WordPress" bar.', 'atelier-irisee-master-plugin' )
				);
				break;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
				break;
			case 'images':
				$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
				echo '<div class="aimp-media-field" data-type="images"><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '"><span class="aimp-media-list">';
				foreach ( $ids as $image_id ) {
					$url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
					if ( $url ) {
						echo '<img src="' . esc_url( $url ) . '" alt="" style="height:36px;width:auto;margin-right:6px;vertical-align:middle;">';
					}
				}
				echo '</span> <button type="button" class="button aimp-media-choose">' . esc_html__( 'Choose pictures', 'atelier-irisee-master-plugin' ) . '</button> ';
				echo '<button type="button" class="button-link aimp-media-remove"' . ( $ids ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove', 'atelier-irisee-master-plugin' ) . '</button></div>';
				echo '<p class="description">' . esc_html__( 'The logos of your payment methods (Bancontact, Visa, Mastercard, …), shown in a row in the footer.', 'atelier-irisee-master-plugin' ) . '</p>';
				break;
			default:
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text"%5$s>',
					esc_attr( 'url' === $args['type'] ? 'url' : ( 'email' === $args['type'] ? 'email' : 'text' ) ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					'copyright' === $key ? ' placeholder="' . esc_attr__( '© {year} Atelier Irisée', 'atelier-irisee-master-plugin' ) . '"' : ''
				);
				if ( 'copyright' === $key ) {
					echo '<p class="description">' . esc_html__( '{year} becomes the current year.', 'atelier-irisee-master-plugin' ) . '</p>';
				}
		}
	}

	/**
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = (array) $input;
		$clean = array();
		foreach ( self::fields() as $key => $field ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : '';
			switch ( $field[1] ) {
				case 'checkbox':
					$clean[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( $value );
					break;
				case 'email':
					$clean[ $key ] = sanitize_email( $value );
					break;
				case 'url':
					$clean[ $key ] = esc_url_raw( trim( (string) $value ) );
					break;
				case 'images':
					$clean[ $key ] = implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( $value );
			}
		}
		return $clean;
	}

	/* ------------------------------------------------------------------
	 * Replacing the theme footer
	 * ------------------------------------------------------------------ */

	public static function enqueue() {
		wp_enqueue_style( 'aimp-footer', AIMP_PLUGIN_URL . 'assets/css/footer.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-footer', AIMP_PLUGIN_URL . 'assets/js/footer.js', array(), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-footer',
			'aimpFooter',
			array(
				'endpoint'    => WC_AJAX::get_endpoint( 'aimp_subscribe' ),
				'status'      => WC_AJAX::get_endpoint( 'aimp_newsletter_status' ),
				'unsubscribe' => WC_AJAX::get_endpoint( 'aimp_unsubscribe' ),
				'resend'      => WC_AJAX::get_endpoint( 'aimp_newsletter_resend' ),
				'error'       => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
				'messages'    => array(
					'confirmed' => __( 'Thank you! Your subscription is confirmed.', 'atelier-irisee-master-plugin' ),
					'invalid'   => __( 'This link is invalid or has expired.', 'atelier-irisee-master-plugin' ),
					'unsubscribed' => __( 'You are unsubscribed from our newsletter.', 'atelier-irisee-master-plugin' ),
				),
			)
		);
	}

	/**
	 * Block themes: the footer template part becomes our footer.
	 *
	 * @param string $content Rendered template part.
	 * @param array  $block   Block.
	 * @return string
	 */
	public static function replace_block_footer( $content, $block ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $content;
		}
		$attrs = isset( $block['attrs'] ) ? (array) $block['attrs'] : array();
		$area  = implode( ' ', array_map( 'strval', array_intersect_key( $attrs, array_flip( array( 'slug', 'tagName', 'area' ) ) ) ) );
		if ( false === strpos( $area, 'footer' ) ) {
			return $content;
		}
		return self::$printed ? '' : self::render();
	}

	/**
	 * Classic themes (Divi and others): print the footer at the bottom of the page.
	 */
	public static function classic_footer() {
		if ( wp_is_block_theme() || self::$printed ) {
			return;
		}
		echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( ! wp_is_block_theme() ) {
			$classes[] = 'aimp-replace-theme-footer';
		}
		return $classes;
	}

	public static function shortcode() {
		return self::$printed ? '' : self::render();
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Gold social media icons (simple line drawings).
	 *
	 * @param string $network facebook, instagram, tiktok or pinterest.
	 * @return string SVG.
	 */
	public static function social_icon( $network ) {
		$paths = array(
			'facebook'  => '<path d="M14 8.5h2.5V5H14a3.5 3.5 0 0 0-3.5 3.5V11H8v3.5h2.5V21H14v-6.5h2.5L17 11h-3V9a.5.5 0 0 1 .5-.5z"/>',
			'instagram' => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.8"/><path d="M16.6 7.4h.01"/>',
			'tiktok'    => '<path d="M14.5 4v10.2a3.3 3.3 0 1 1-3.3-3.3"/><path d="M14.5 4c.4 2.3 2 3.9 4.5 4.1"/>',
			'pinterest' => '<path d="M11.5 8.5c-3 0-4.6 2-4.6 4.3 0 1.2.6 2.6 1.6 3"/><path d="M12 8.4c2.7 0 4.6 1.8 4.6 4.1 0 2.6-1.5 4.5-3.5 4.5-1 0-1.8-.7-1.5-1.7l.9-3.3"/><path d="M11.7 12.5 9.8 20"/>',
		);
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( isset( $paths[ $network ] ) ? $paths[ $network ] : '' ) . '</svg>';
	}

	/**
	 * A footer menu, or '' when no menu is assigned.
	 *
	 * @param string $location Menu location.
	 * @return string
	 */
	private static function menu_html( $location ) {
		if ( ! has_nav_menu( $location ) ) {
			return '';
		}
		return (string) wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'menu_class'     => 'aimp-footer-menu',
				'depth'          => 1,
				'echo'           => false,
				'fallback_cb'    => false,
			)
		);
	}

	/**
	 * Links for the "My account" column when no menu is assigned.
	 *
	 * @return array[] [ url, label ]
	 */
	private static function default_account_links() {
		$account = class_exists( 'AIMP_Header' ) ? AIMP_Header::account_url() : wc_get_page_permalink( 'myaccount' );
		$links   = array(
			array( $account, is_user_logged_in() ? __( 'My account', 'atelier-irisee-master-plugin' ) : __( 'Log in', 'atelier-irisee-master-plugin' ) ),
			array( add_query_arg( 'tab', 'orders', $account ), __( 'My orders', 'atelier-irisee-master-plugin' ) ),
		);
		$favorites = AIMP_Settings::get( 'favorites_page' );
		if ( $favorites && 'publish' === get_post_status( $favorites ) ) {
			$links[] = array( get_permalink( $favorites ), __( 'Favourites', 'atelier-irisee-master-plugin' ) );
		}
		return $links;
	}

	/**
	 * The footer HTML.
	 *
	 * @return string
	 */
	public static function render() {
		self::$printed = true;
		if ( ! wp_style_is( 'aimp-footer', 'enqueued' ) ) {
			self::enqueue();
		}

		$copyright = (string) self::get( 'copyright' );
		if ( '' === $copyright ) {
			$copyright = __( '© {year} Atelier Irisée', 'atelier-irisee-master-plugin' );
		}

		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		$data    = array(
			'company'         => (string) self::get( 'company' ),
			'address'         => (string) self::get( 'address' ),
			'phone'           => (string) self::get( 'phone' ),
			'email'           => (string) self::get( 'email' ),
			'socials'         => array_filter(
				array(
					'facebook'  => (string) self::get( 'facebook' ),
					'instagram' => (string) self::get( 'instagram' ),
					'tiktok'    => (string) self::get( 'tiktok' ),
					'pinterest' => (string) self::get( 'pinterest' ),
				)
			),
			'account_menu'    => self::menu_html( 'aimp_footer_account' ),
			'account_links'   => self::default_account_links(),
			'service_menu'    => self::menu_html( 'aimp_footer_service' ),
			'legal_menu'      => self::menu_html( 'aimp_footer_legal' ),
			'privacy_url'     => ( $privacy && 'publish' === get_post_status( $privacy ) ) ? get_permalink( $privacy ) : '',
			'newsletter_text' => (string) self::get( 'newsletter_text' ),
			'payment_logos'   => array_filter( array_map( 'absint', explode( ',', (string) self::get( 'payment_logos' ) ) ) ),
			'copyright'       => str_replace( '{year}', gmdate( 'Y' ), $copyright ),
		);

		ob_start();
		$template = locate_template( 'atelier-irisee/footer.php' );
		include $template ? $template : AIMP_PLUGIN_DIR . 'templates/footer.php';
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * Newsletter
	 * ------------------------------------------------------------------ */

	/*
	 * Subscribers are private posts (title = email) with:
	 * - _aimp_status: "pending" until the link in the confirmation email is clicked, then "confirmed"
	 *   (subscribers from before 2.7 have no status and count as confirmed);
	 * - _aimp_token: random; also kept in the visitor's aimp_nl cookie, so the footer can show their state
	 *   on cached pages and they can unsubscribe;
	 * - _aimp_lang.
	 */

	const COOKIE = 'aimp_nl';

	/**
	 * The subscriber with this address, if any.
	 *
	 * @param string $email Email address.
	 * @return int Post ID or 0.
	 */
	private static function find( $email ) {
		$found = get_posts(
			array(
				'post_type'      => self::SUBSCRIBERS,
				'post_status'    => 'private',
				'title'          => $email,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * @param string $token Token.
	 * @return int Post ID or 0.
	 */
	private static function find_by_token( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'      => self::SUBSCRIBERS,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_aimp_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small list.
				'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small list.
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * @param int $id Subscriber.
	 * @return string pending|confirmed
	 */
	public static function status( $id ) {
		return 'pending' === get_post_meta( $id, '_aimp_status', true ) ? 'pending' : 'confirmed';
	}

	private static function token( $id ) {
		$token = (string) get_post_meta( $id, '_aimp_token', true );
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			$token = wp_generate_password( 32, false, false );
			update_post_meta( $id, '_aimp_token', $token );
		}
		return $token;
	}

	private static function set_cookie( $token ) {
		wc_setcookie( self::COOKIE, $token, $token ? time() + YEAR_IN_SECONDS : time() - HOUR_IN_SECONDS, is_ssl(), false );
	}

	/**
	 * The visitor's own subscription: by the token in their cookie, or by their account's email.
	 *
	 * @return int Post ID or 0.
	 */
	private static function current_subscriber() {
		$id = self::find_by_token( isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '' );
		if ( ! $id && is_user_logged_in() ) {
			$id = self::find( strtolower( wp_get_current_user()->user_email ) );
		}
		return $id;
	}

	/**
	 * The email with the confirmation link, in the subscriber's language.
	 *
	 * @param int $id Subscriber.
	 */
	private static function send_confirmation( $id ) {
		// The address straight from the post: get_the_title() puts "Private: " before it.
		$email = (string) get_post_field( 'post_title', $id, 'raw' );
		$url   = add_query_arg( 'aimp_nl_confirm', self::token( $id ), home_url( '/' ) );
		$lang  = (string) get_post_meta( $id, '_aimp_lang', true );
		$lang  = AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::default_language();
		return (bool) AIMP_I18n::with_language(
			$lang,
			function () use ( $email, $url ) {
				$emails = WC()->mailer()->get_emails();
				return isset( $emails['AIMP_Email_Newsletter_Confirm'] ) ? $emails['AIMP_Email_Newsletter_Confirm']->trigger( $email, $url ) : false;
			}
		);
	}

	/**
	 * The confirmation email as a WooCommerce email (WooCommerce → Settings → Emails).
	 *
	 * @param WC_Email[] $emails Emails.
	 * @return WC_Email[]
	 */
	public static function register_email( $emails ) {
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-email-newsletter-confirm.php';
		if ( class_exists( 'AIMP_Email_Newsletter_Confirm' ) ) {
			$emails['AIMP_Email_Newsletter_Confirm'] = new AIMP_Email_Newsletter_Confirm();
		}
		return $emails;
	}

	/**
	 * Admin: send the confirmation email again, or confirm a subscriber yourself.
	 */
	public static function admin_subscriber_action() {
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$action = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_newsletter_' . $action . '_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$message = '';
		if ( self::SUBSCRIBERS === get_post_type( $id ) ) {
			if ( 'confirm' === $action ) {
				update_post_meta( $id, '_aimp_status', 'confirmed' );
				$message = 'confirmed';
			} elseif ( 'resend' === $action ) {
				$message = self::send_confirmation( $id ) ? 'sent' : 'not_sent';
			}
		}
		wp_safe_redirect( add_query_arg( 'aimp_msg', $message, admin_url( 'admin.php?page=' . self::ADMIN_PAGE ) ) );
		exit;
	}

	/**
	 * Texts of the footer states.
	 *
	 * @param string $state confirmed|pending|none.
	 * @return string
	 */
	private static function state_message( $state ) {
		if ( 'confirmed' === $state ) {
			return __( 'Thank you! You are subscribed to our newsletter.', 'atelier-irisee-master-plugin' );
		}
		if ( 'pending' === $state ) {
			return __( 'Almost done! Check your inbox to confirm your subscription.', 'atelier-irisee-master-plugin' );
		}
		return __( 'You are unsubscribed from our newsletter.', 'atelier-irisee-master-plugin' );
	}

	private static function too_many() {
		wp_send_json_error( array( 'message' => __( 'Too many attempts. Please try again later.', 'atelier-irisee-master-plugin' ) ) );
	}

	/**
	 * ?wc-ajax=aimp_subscribe. No nonce on purpose: the form is also on cached pages, where a nonce
	 * would expire. Bots are stopped by the hidden field and a limit per visitor.
	 */
	public static function subscribe() {
		if ( AIMP_Login_Security::honeypot_triggered() ) {
			wp_send_json_success(
				array(
					'state'   => 'pending',
					'message' => self::state_message( 'pending' ),
				)
			);
		}
		if ( AIMP_Login_Security::rate_limited( 'newsletter', AIMP_Login_Security::ip(), 5 ) ) {
			self::too_many();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ) ) );
		}
		$email = strtolower( $email );
		$id    = self::find( $email );
		if ( ! $id ) {
			$id = wp_insert_post(
				array(
					'post_type'   => self::SUBSCRIBERS,
					'post_status' => 'private',
					'post_title'  => $email,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ) ) );
			}
			update_post_meta( $id, '_aimp_lang', AIMP_I18n::current() );
			update_post_meta( $id, '_aimp_status', 'pending' );
		}
		$state = self::status( $id );
		if ( 'pending' === $state ) {
			self::send_confirmation( $id );
		}
		self::set_cookie( self::token( $id ) );
		wp_send_json_success(
			array(
				'state'   => $state,
				'message' => self::state_message( $state ),
			)
		);
	}

	/**
	 * ?wc-ajax=aimp_newsletter_status: none, pending or confirmed for this visitor.
	 */
	public static function ajax_status() {
		$id    = self::current_subscriber();
		$state = $id ? self::status( $id ) : 'none';
		if ( $id && empty( $_COOKIE[ self::COOKIE ] ) ) {
			self::set_cookie( self::token( $id ) );
		}
		wp_send_json_success(
			array(
				'state'   => $state,
				'message' => 'none' === $state ? '' : self::state_message( $state ),
			)
		);
	}

	/**
	 * ?wc-ajax=aimp_unsubscribe (POST): removes the visitor's own subscription (their cookie token or
	 * their account's email), nobody else's.
	 */
	public static function unsubscribe() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'post' !== $method || AIMP_Login_Security::rate_limited( 'newsletter', AIMP_Login_Security::ip(), 5 ) ) {
			self::too_many();
		}
		$id = self::current_subscriber();
		if ( $id ) {
			wp_delete_post( $id, true );
		}
		self::set_cookie( '' );
		wp_send_json_success(
			array(
				'state'   => 'none',
				'message' => self::state_message( 'none' ),
			)
		);
	}

	/**
	 * ?wc-ajax=aimp_newsletter_resend: the confirmation email again.
	 */
	public static function resend() {
		if ( AIMP_Login_Security::rate_limited( 'newsletter', AIMP_Login_Security::ip(), 5 ) ) {
			self::too_many();
		}
		$id = self::current_subscriber();
		if ( $id && 'pending' === self::status( $id ) ) {
			self::send_confirmation( $id );
		}
		wp_send_json_success(
			array(
				'state'   => $id ? self::status( $id ) : 'none',
				'message' => __( 'We sent the email again.', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	/**
	 * The link in the confirmation email: /?aimp_nl_confirm=TOKEN.
	 */
	/**
	 * Confirmed subscribers (for sending a newsletter).
	 *
	 * @return int[] Post IDs.
	 */
	public static function confirmed_subscriber_ids() {
		$ids = array();
		foreach ( self::all_subscribers() as $subscriber ) {
			if ( 'confirmed' === self::status( $subscriber->ID ) ) {
				$ids[] = (int) $subscriber->ID;
			}
		}
		return $ids;
	}

	/**
	 * The personal one-click unsubscribe link of a subscriber (in newsletters).
	 *
	 * @param int $id Subscriber.
	 * @return string
	 */
	public static function unsubscribe_url( $id ) {
		return add_query_arg( 'aimp_nl_unsub', self::token( $id ), home_url( '/' ) );
	}

	/**
	 * The unsubscribe link of a newsletter: /?aimp_nl_unsub=TOKEN removes that subscriber.
	 */
	public static function unsubscribe_link() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the proof.
		if ( empty( $_GET['aimp_nl_unsub'] ) ) {
			return;
		}
		$id = self::find_by_token( sanitize_text_field( wp_unslash( $_GET['aimp_nl_unsub'] ) ) );
		// phpcs:enable
		if ( $id ) {
			wp_delete_post( $id, true );
			self::set_cookie( '' );
		}
		// One-click unsubscribe from the inbox (List-Unsubscribe-Post): a POST that only needs "OK".
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			status_header( 200 );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'aimp_nl_msg', $id ? 'unsubscribed' : 'invalid', home_url( '/' ) ) );
		exit;
	}

	public static function confirm() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the proof.
		if ( empty( $_GET['aimp_nl_confirm'] ) ) {
			return;
		}
		$id = self::find_by_token( sanitize_text_field( wp_unslash( $_GET['aimp_nl_confirm'] ) ) );
		// phpcs:enable
		if ( $id ) {
			update_post_meta( $id, '_aimp_status', 'confirmed' );
			self::set_cookie( self::token( $id ) );
		}
		wp_safe_redirect( add_query_arg( 'aimp_nl_msg', $id ? 'confirmed' : 'invalid', home_url( '/' ) ) );
		exit;
	}

	public static function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Newsletter', 'atelier-irisee-master-plugin' ),
			__( 'Newsletter', 'atelier-irisee-master-plugin' ),
			'manage_woocommerce',
			self::ADMIN_PAGE,
			array( __CLASS__, 'render_admin' )
		);
	}

	private static function all_subscribers() {
		return get_posts(
			array(
				'post_type'      => self::SUBSCRIBERS,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$subscribers = self::all_subscribers();
		$languages   = AIMP_I18n::languages();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Newsletter', 'atelier-irisee-master-plugin' ); ?></h1>
			<?php do_action( 'aimp_newsletter_admin_top' ); // The newsletter composer (AIMP_Newsletter). ?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message after a redirect.
			$notice   = isset( $_GET['aimp_msg'] ) ? sanitize_key( wp_unslash( $_GET['aimp_msg'] ) ) : '';
			$messages = array(
				'confirmed' => array( 'success', __( 'The subscriber is confirmed.', 'atelier-irisee-master-plugin' ) ),
				'sent'      => array( 'success', __( 'The confirmation email has been sent again.', 'atelier-irisee-master-plugin' ) ),
				'not_sent'  => array( 'error', __( 'The confirmation email could not be sent. Check that "Newsletter confirmation" is on under WooCommerce → Settings → Emails.', 'atelier-irisee-master-plugin' ) ),
			);
			if ( isset( $messages[ $notice ] ) ) {
				printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
			}
			$mailer = function_exists( 'WC' ) ? WC()->mailer()->get_emails() : array();
			if ( isset( $mailer['AIMP_Email_Newsletter_Confirm'] ) && ! $mailer['AIMP_Email_Newsletter_Confirm']->is_enabled() ) {
				printf(
					'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
					esc_html__( 'The newsletter confirmation email is switched off, so new subscribers cannot confirm.', 'atelier-irisee-master-plugin' ),
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=aimp_email_newsletter_confirm' ) ),
					esc_html__( 'Switch it on', 'atelier-irisee-master-plugin' )
				);
			}
			?>
			<p>
				<?php
				/* translators: %d: number of subscribers */
				echo esc_html( sprintf( _n( '%d subscriber', '%d subscribers', count( $subscribers ), 'atelier-irisee-master-plugin' ), count( $subscribers ) ) );
				?>
				&nbsp; <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_newsletter_csv' ), 'aimp_newsletter_csv' ) ); ?>"><?php esc_html_e( 'Download CSV', 'atelier-irisee-master-plugin' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'Import the CSV in your mail program (Mailchimp, MailerLite, …) to send a newsletter.', 'atelier-irisee-master-plugin' ); ?> <?php esc_html_e( 'New subscribers confirm their address through an email first; only confirmed subscribers are in the CSV.', 'atelier-irisee-master-plugin' ); ?></p>
			<table class="widefat striped" style="max-width:780px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'atelier-irisee-master-plugin' ); ?></th>
						<th><?php esc_html_e( 'Language', 'atelier-irisee-master-plugin' ); ?></th>
						<th><?php esc_html_e( 'Subscribed on', 'atelier-irisee-master-plugin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'atelier-irisee-master-plugin' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $subscribers ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No subscribers yet.', 'atelier-irisee-master-plugin' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $subscribers as $subscriber ) : ?>
						<?php $lang = (string) get_post_meta( $subscriber->ID, '_aimp_lang', true ); ?>
						<tr>
							<td><?php echo esc_html( $subscriber->post_title ); ?></td>
							<td><?php echo esc_html( isset( $languages[ $lang ] ) ? $languages[ $lang ]['name'] : '' ); ?></td>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $subscriber->post_date_gmt . ' UTC' ) ) ); ?></td>
							<td><?php echo 'pending' === self::status( $subscriber->ID ) ? esc_html__( 'Waiting for confirmation', 'atelier-irisee-master-plugin' ) : esc_html__( 'Confirmed', 'atelier-irisee-master-plugin' ); ?></td>
							<td>
								<?php if ( 'pending' === self::status( $subscriber->ID ) ) : ?>
									<?php foreach ( array( 'resend' => __( 'Send the email again', 'atelier-irisee-master-plugin' ), 'confirm' => __( 'Confirm', 'atelier-irisee-master-plugin' ) ) as $aimp_do => $aimp_label ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_newsletter_action&do=' . $aimp_do . '&id=' . $subscriber->ID ), 'aimp_newsletter_' . $aimp_do . '_' . $subscriber->ID ) ); ?>"><?php echo esc_html( $aimp_label ); ?></a> ·
									<?php endforeach; ?>
								<?php endif; ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_newsletter_delete&id=' . $subscriber->ID ), 'aimp_newsletter_delete_' . $subscriber->ID ) ); ?>" class="aimp-confirm-delete"><?php esc_html_e( 'Delete', 'atelier-irisee-master-plugin' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function download_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_newsletter_csv' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=newsletter-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'language', 'subscribed' ) );
		foreach ( self::all_subscribers() as $subscriber ) {
			if ( 'pending' === self::status( $subscriber->ID ) ) {
				continue; // Only confirmed subscribers.
			}
			fputcsv( $out, array( $subscriber->post_title, (string) get_post_meta( $subscriber->ID, '_aimp_lang', true ), $subscriber->post_date ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output.
		exit;
	}

	public static function delete_subscriber() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_newsletter_delete_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		if ( self::SUBSCRIBERS === get_post_type( $id ) ) {
			wp_delete_post( $id, true );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::ADMIN_PAGE ) );
		exit;
	}
}
