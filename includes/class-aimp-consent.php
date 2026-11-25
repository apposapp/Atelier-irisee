<?php
/**
 * Cookies and Google: a cookie banner (accept / refuse), Google Analytics with Consent Mode v2 and the Search Console
 * verification tag (Atelier Irisee → Settings → General → Google).
 *
 * - Google Analytics starts with every kind of storage "denied"; only after "Accept" (cookie aimp_consent=yes) may it
 *   set cookies. Without consent Google only receives anonymous counts.
 * - The banner is always in the page but hidden; a small script shows it when no choice was made yet (cached pages
 *   are the same for everyone). "Cookie settings" in the footer (or any [data-aimp-cookies] / #cookies link) opens it
 *   again.
 * - The Facebook pixel (Facebook for WooCommerce) only runs after "Accept"; the page cache keeps a copy per choice.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Consent {

	const COOKIE = 'aimp_consent';

	public static function init() {
		add_filter( 'litespeed_vary_cookies', array( __CLASS__, 'vary_cookie' ) );
		add_filter( 'litespeed_vary_curr_cookies', array( __CLASS__, 'vary_cookie' ) );
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'head' ), 0 );
		add_action( 'wp_footer', array( __CLASS__, 'banner' ), 50 );
		// Facebook for WooCommerce: the pixel only with consent.
		add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', array( __CLASS__, 'pixel_enabled' ), 20 );
	}

	/**
	 * @param array $cookies Cookies the page cache varies on.
	 * @return array
	 */
	public static function vary_cookie( $cookies ) {
		$cookies   = (array) $cookies;
		$cookies[] = self::COOKIE;
		return array_values( array_unique( $cookies ) );
	}

	/**
	 * The Google Analytics ID (G-…), or ''.
	 *
	 * @return string
	 */
	public static function ga_id() {
		$id = strtoupper( trim( AIMP_Settings::get_text( 'ga_id' ) ) );
		return preg_match( '/^G-[A-Z0-9]{4,20}$/', $id ) ? $id : '';
	}

	/**
	 * Whether the banner is shown: switched on, and something on the site needs consent.
	 *
	 * @return bool
	 */
	public static function banner_on() {
		if ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() ) {
			return false;
		}
		return (bool) AIMP_Settings::get( 'cookie_banner' ) && ( '' !== self::ga_id() || self::has_pixel() );
	}

	/**
	 * @return bool
	 */
	private static function has_pixel() {
		return class_exists( 'WC_Facebookcommerce' ) || function_exists( 'facebook_for_woocommerce' );
	}

	/**
	 * Whether the visitor accepted cookies.
	 *
	 * @return bool
	 */
	public static function accepted() {
		return isset( $_COOKIE[ self::COOKIE ] ) && 'yes' === $_COOKIE[ self::COOKIE ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared.
	}

	/**
	 * @param bool $enabled Pixel on.
	 * @return bool
	 */
	public static function pixel_enabled( $enabled ) {
		return self::banner_on() ? ( $enabled && self::accepted() ) : $enabled;
	}

	/**
	 * Search Console's tag, and Google Analytics with consent "denied" until the visitor accepts.
	 */
	public static function head() {
		$verify = trim( AIMP_Settings::get_text( 'gsc_verify' ) );
		if ( '' !== $verify ) {
			// The code only, also when the whole tag was pasted.
			if ( preg_match( '/content=["\']([^"\']+)["\']/', $verify, $m ) ) {
				$verify = $m[1];
			}
			echo '<meta name="google-site-verification" content="' . esc_attr( $verify ) . '">' . "\n";
		}
		$id = self::ga_id();
		if ( '' === $id || is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
			// The shop manager's own visits are not counted.
			return;
		}
		$banner = self::banner_on();
		printf(
			'<script data-no-optimize="1" data-no-defer="1">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}' .
			'gtag("consent","default",{ad_storage:"denied",ad_user_data:"denied",ad_personalization:"denied",analytics_storage:"%1$s",wait_for_update:500});' .
			'if(/(?:^|;\s*)%2$s=yes/.test(document.cookie)){gtag("consent","update",{analytics_storage:"granted"});}' .
			'gtag("js",new Date());gtag("config",%3$s);</script>' . "\n" .
			'<script async data-no-optimize="1" src="https://www.googletagmanager.com/gtag/js?id=%4$s"></script>' . "\n",
			$banner ? 'denied' : 'granted',
			esc_js( self::COOKIE ),
			wp_json_encode( $id ),
			rawurlencode( $id )
		);
	}

	/**
	 * The banner (hidden; consent.js shows it when no choice was made).
	 */
	public static function banner() {
		if ( ! self::banner_on() ) {
			return;
		}
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		$url     = ( $privacy && 'publish' === get_post_status( $privacy ) ) ? get_permalink( $privacy ) : '';
		wp_enqueue_style( 'aimp-consent', AIMP_PLUGIN_URL . 'assets/css/consent.css', array(), AIMP_VERSION );
		?>
		<div class="aimp-consent" id="aimp-consent" role="region" aria-label="<?php esc_attr_e( 'Cookies', 'atelier-irisee-master-plugin' ); ?>" hidden>
			<p class="aimp-consent-text">
				<?php esc_html_e( 'We use cookies to see how our shop is used, so we can make it better. Do you accept them?', 'atelier-irisee-master-plugin' ); ?>
				<?php if ( '' !== $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'More about privacy', 'atelier-irisee-master-plugin' ); ?></a>
				<?php endif; ?>
			</p>
			<div class="aimp-consent-buttons">
				<button type="button" class="aimp-consent-refuse" data-aimp-consent="no"><?php esc_html_e( 'Refuse', 'atelier-irisee-master-plugin' ); ?></button>
				<button type="button" class="aimp-consent-accept" data-aimp-consent="yes"><?php esc_html_e( 'Accept', 'atelier-irisee-master-plugin' ); ?></button>
			</div>
		</div>
		<script data-no-optimize="1">
		(function () {
			var box = document.getElementById('aimp-consent');
			var name = <?php echo wp_json_encode( self::COOKIE ); ?>;
			if (!box) {
				return;
			}
			function chosen() {
				var m = new RegExp('(?:^|;\\s*)' + name + '=(yes|no)').exec(document.cookie);
				return m ? m[1] : '';
			}
			function show() {
				box.hidden = false;
				var first = box.querySelector('.aimp-consent-accept');
				if (first && document.activeElement === document.body) {
					first.focus({ preventScroll: true });
				}
			}
			if (!chosen()) {
				show();
			}
			box.addEventListener('click', function (e) {
				var button = e.target.closest('[data-aimp-consent]');
				if (!button) {
					return;
				}
				var yes = button.getAttribute('data-aimp-consent') === 'yes';
				// Half a year.
				document.cookie = name + '=' + (yes ? 'yes' : 'no') + '; path=/; max-age=15552000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
				if (typeof window.gtag === 'function') {
					window.gtag('consent', 'update', { analytics_storage: yes ? 'granted' : 'denied' });
				}
				box.hidden = true;
			});
			// "Cookie settings": the choice again.
			document.addEventListener('click', function (e) {
				var link = e.target.closest('[data-aimp-cookies], a[href$="#cookies"]');
				if (link) {
					e.preventDefault();
					show();
				}
			});
			if (location.hash === '#cookies') {
				show();
			}
		})();
		</script>
		<?php
	}
}
