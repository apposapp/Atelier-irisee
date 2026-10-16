<?php
/**
 * What a page needs to know about the visitor, in one request: the cart number, the favourites (logged in) and the
 * newsletter state. Pages themselves can then be cached for everyone (LiteSpeed Cache).
 *
 * window.aimpBoot() (printed in <head>) only asks the server when there is something to ask: a logged-in visitor,
 * a cart, or a newsletter cookie. Other visitors start without any request.
 *
 * ?wc-ajax=aimp_nonces gives fresh security codes for pages that came from the cache (they can be days old).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Boot {

	public static function init() {
		add_action( 'wc_ajax_aimp_boot', array( __CLASS__, 'ajax' ) );
		add_action( 'wc_ajax_aimp_nonces', array( __CLASS__, 'nonces' ) );
		add_action( 'wp_head', array( __CLASS__, 'loader' ), 1 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'defer' ), 10, 2 );
	}

	/**
	 * The plugin's own scripts never hold up the page: "defer" (none of them has inline code that runs after it).
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function defer( $tag, $handle ) {
		if ( is_admin() || 0 !== strpos( $handle, 'aimp-' ) || 0 === strpos( $handle, 'aimp-admin' ) || preg_match( '/\s(defer|async)[\s=>]/', $tag ) ) {
			return $tag;
		}
		return preg_replace( '/<script\s(?=[^>]*\bsrc=)/', '<script defer ', $tag, 1 );
	}

	/** Never cached: the answer is about this visitor. */
	private static function no_cache() {
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'aimp visitor data' );
	}

	/**
	 * ?wc-ajax=aimp_boot: cart number, favourites and newsletter state of this visitor.
	 */
	public static function ajax() {
		self::no_cache();
		$count = AIMP_Header::cart_count();
		$data  = array(
			'cart' => array(
				'count' => $count,
				'badge' => AIMP_Header::cart_badge( $count ),
			),
		);
		if ( is_user_logged_in() && class_exists( 'AIMP_Favorites' ) ) {
			$data['favorites'] = AIMP_Favorites::get_ids();
		}
		if ( class_exists( 'AIMP_Footer' ) && ( is_user_logged_in() || ! empty( $_COOKIE[ AIMP_Footer::COOKIE ] ) ) ) {
			$data['newsletter'] = AIMP_Footer::status_data();
		}
		wp_send_json_success( $data );
	}

	/**
	 * ?wc-ajax=aimp_nonces: fresh security codes for the customizer and the "add pattern" form.
	 */
	public static function nonces() {
		self::no_cache();
		wp_send_json_success(
			array(
				'configurator' => wp_create_nonce( AIMP_Ajax::NONCE ),
				'add_pattern'  => wp_create_nonce( 'aimp_add_pattern' ),
			)
		);
	}

	/**
	 * window.aimpBoot( force ): a promise with the visitor data (asked once per page; force asks again, e.g. after
	 * adding to the cart). Without a login, cart or newsletter cookie it answers at once, without a request.
	 */
	public static function loader() {
		if ( is_admin() ) {
			return;
		}
		$url    = WC_AJAX::get_endpoint( 'aimp_boot' );
		$logged = is_user_logged_in() ? 'true' : 'false';
		?>
		<script id="aimp-boot">
		(function () {
			var url = <?php echo wp_json_encode( $url ); ?>, loggedIn = <?php echo $logged; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- true or false. ?>, pending = null;
			window.aimpBoot = function (force) {
				if (pending && !force) {
					return pending;
				}
				var needed = force || loggedIn || /(?:^|;\s*)(woocommerce_items_in_cart|aimp_nl)=/.test(document.cookie);
				pending = !needed || !window.fetch ? Promise.resolve({}) : fetch(url, { method: 'POST', credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (json) { return json && json.success ? json.data : {}; })
					.catch(function () { return {}; });
				return pending;
			};
		})();
		</script>
		<?php
	}
}
