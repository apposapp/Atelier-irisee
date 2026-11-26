<?php
/**
 * A timed discount on the whole shop (e.g. Black Friday): Atelier Irisee → Sale.
 *
 * - A percentage off every product (gift cards excepted) between a start and an end moment, from a minimum order
 *   amount. Sewing project kits first get their kit discount, then this one on top.
 * - Worked out in the cart (the minimum is about the whole order): the line prices are lowered, so free shipping,
 *   invoices, VAT and emails all see the real prices. Discount codes can't be used while it applies.
 * - Announced while it runs (each can be switched off): the first text of the gold bar, a popup once per visitor,
 *   a banner under the home page hero with a countdown, and a label on products in the shop and on product pages.
 * - Cached pages are refreshed at the start and at the end.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Sale {

	const OPTION = 'aimp_sale';
	const PAGE   = 'aimp-sale';
	const SWITCH = 'aimp_sale_switch';
	const TAG    = 'atelier_irisee_sale_banner';

	/** Cookie of a shop manager's preview. */
	const PREVIEW = 'aimp_sale_preview';

	/** Texts the owner can set per language (empty: the default text). */
	const TEXTS = array( 'bar', 'popup_title', 'popup_text', 'banner_title', 'banner_text', 'button' );

	/** A notice about removed codes only once per request. */
	private static $told = false;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 66 );
		add_action( 'admin_post_aimp_sale_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_aimp_sale_preview', array( __CLASS__, 'toggle_preview' ) );
		add_action( self::SWITCH, array( __CLASS__, 'purge' ) );

		// The discount: after the kit discount (AIMP_Cart, priority 20).
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply' ), 30 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 20, 3 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'order_meta' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'coupon_valid' ), 20, 2 );
		add_action( 'woocommerce_cart_totals_before_order_total', array( __CLASS__, 'total_row' ) );
		add_action( 'woocommerce_review_order_before_order_total', array( __CLASS__, 'total_row' ) );

		// The announcements.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'popup' ), 30 );
		add_shortcode( self::TAG, array( __CLASS__, 'banner' ) );
		add_filter( 'aimp_after_hero', array( __CLASS__, 'after_hero' ) );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'on'      => 0,
				'title'   => 'Black Friday',
				'percent' => 0,
				'minimum' => 0,
				'from'    => '',
				'to'      => '',
				'bar'     => 1,
				'popup'   => 1,
				'banner'  => 1,
				'hero'    => 1,
				'labels'  => 1,
				'texts'   => array(),
			)
		);
	}

	/**
	 * A moment from the settings ("2026-11-27T00:00", the site's time zone) as a timestamp, 0 when not set.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function moment( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return 0;
		}
		$date = DateTime::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $date ? $date->getTimestamp() : 0;
	}

	/** @return int */
	public static function start() {
		return self::moment( self::settings()['from'] );
	}

	/** @return int */
	public static function end() {
		$end = self::moment( self::settings()['to'] );
		// Preview without an end yet: three days from now.
		return ! $end && self::preview() ? time() + 3 * DAY_IN_SECONDS : $end;
	}

	/** @return int */
	public static function percent() {
		$percent = min( 90, max( 0, (int) self::settings()['percent'] ) );
		return ! $percent && self::preview() ? 20 : $percent;
	}

	/**
	 * Preview: a shop manager sees the sale on the site as if it ran now (cookie from the admin page), nobody else.
	 *
	 * @return bool
	 */
	public static function preview() {
		static $preview = null;
		if ( empty( $_COOKIE[ self::PREVIEW ] ) ) {
			return false;
		}
		// Who is logged in is only known from here on.
		if ( ! did_action( 'set_current_user' ) ) {
			return false;
		}
		if ( null === $preview ) {
			$preview = current_user_can( 'manage_woocommerce' );
			if ( $preview && ! is_admin() ) {
				// Never in the page cache.
				do_action( 'litespeed_control_set_nocache', 'aimp sale preview' );
			}
		}
		return $preview;
	}

	/** Turns the preview on (an hour) or off, then shows the home page or the admin page. */
	public static function toggle_preview() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_sale_preview' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$on     = ! empty( $_GET['on'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked above.
		$secure = is_ssl();
		setcookie( self::PREVIEW, $on ? '1' : '', $on ? time() + HOUR_IN_SECONDS : time() - YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, $secure, true );
		wp_safe_redirect( $on ? home_url( '/' ) : admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/** @return float */
	public static function minimum() {
		return max( 0.0, (float) self::settings()['minimum'] );
	}

	/** @return string */
	public static function title() {
		$title = trim( (string) self::settings()['title'] );
		return '' !== $title ? $title : 'Black Friday';
	}

	/**
	 * Whether the sale runs now.
	 *
	 * @return bool
	 */
	public static function active() {
		if ( self::preview() ) {
			return true;
		}
		$s     = self::settings();
		$start = self::start();
		$end   = self::end();
		$now   = time();
		return ! empty( $s['on'] ) && self::percent() > 0 && $start && $end && $start <= $now && $now < $end;
	}

	/**
	 * Whether an announcement is switched on (and the sale runs).
	 *
	 * @param string $key bar, popup, banner or labels.
	 * @return bool
	 */
	public static function shows( $key ) {
		$s = self::settings();
		return self::active() && ! empty( $s[ $key ] );
	}

	/**
	 * The default texts, in the current language.
	 *
	 * @return array
	 */
	private static function default_texts() {
		$min = self::minimum() > 0;
		return array(
			/* translators: placeholders are replaced: {title}, {percent}, {minimum} */
			'bar'          => $min ? __( '{title}: −{percent}% on everything from {minimum}', 'atelier-irisee-master-plugin' ) : __( '{title}: −{percent}% on everything', 'atelier-irisee-master-plugin' ),
			'popup_title'  => __( '{title}: −{percent}% on everything', 'atelier-irisee-master-plugin' ),
			'popup_text'   => $min ? __( 'Until {end}. The discount is taken off in your cart for orders from {minimum}. Gift cards excepted.', 'atelier-irisee-master-plugin' ) : __( 'Until {end}. The discount is taken off in your cart. Gift cards excepted.', 'atelier-irisee-master-plugin' ),
			'banner_title' => __( '{title}: −{percent}% on everything', 'atelier-irisee-master-plugin' ),
			'banner_text'  => $min ? __( 'On orders from {minimum}, taken off in your cart. Gift cards excepted.', 'atelier-irisee-master-plugin' ) : __( 'Taken off in your cart. Gift cards excepted.', 'atelier-irisee-master-plugin' ),
			'button'       => __( 'Shop now', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * A text in the current language, with the placeholders filled in.
	 *
	 * @param string $key Text.
	 * @return string
	 */
	public static function text( $key ) {
		$s     = self::settings();
		$lang  = AIMP_I18n::current();
		$own   = isset( $s['texts'][ $key . '_' . $lang ] ) ? trim( (string) $s['texts'][ $key . '_' . $lang ] ) : '';
		$texts = self::default_texts();
		$text  = '' !== $own ? $own : ( isset( $texts[ $key ] ) ? $texts[ $key ] : '' );
		return self::fill( $text );
	}

	/**
	 * @param string $text Text with {title}, {percent}, {minimum}, {end}.
	 * @return string
	 */
	private static function fill( $text ) {
		$end = self::end();
		return strtr(
			$text,
			array(
				'{title}'   => self::title(),
				'{percent}' => (string) self::percent(),
				'{minimum}' => html_entity_decode( wp_strip_all_tags( wc_price( self::minimum(), array( 'decimals' => floor( self::minimum() ) === self::minimum() ? 0 : 2 ) ) ), ENT_QUOTES, 'UTF-8' ),
				'{end}'     => $end ? self::end_text( $end ) : '',
			)
		);
	}

	/**
	 * "29 november 2026, 23:59".
	 *
	 * @param int $timestamp Moment.
	 * @return string
	 */
	private static function end_text( $timestamp ) {
		return AIMP_I18n::date( $timestamp ) . ', ' . wp_date( 'H:i', $timestamp );
	}

	/** Cached pages show the announcements only while the sale runs: refreshed at the start and the end. */
	public static function purge() {
		if ( class_exists( 'AIMP_Design' ) && method_exists( 'AIMP_Design', 'purge' ) ) {
			AIMP_Design::purge();
		} else {
			do_action( 'litespeed_purge_all' );
		}
	}

	/* ------------------------------------------------------------------
	 * Admin page
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( AIMP_Settings::PAGE, __( 'Sale', 'atelier-irisee-master-plugin' ), __( 'Sale (Black Friday)', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render_admin' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aimp_sale_save' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field below.
		$in   = isset( $_POST['sale'] ) && is_array( $_POST['sale'] ) ? wp_unslash( $_POST['sale'] ) : array();
		$time = function ( $value ) {
			$value = sanitize_text_field( (string) $value );
			return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ? $value : '';
		};
		$clean = array(
			'on'      => empty( $in['on'] ) ? 0 : 1,
			'title'   => isset( $in['title'] ) ? sanitize_text_field( $in['title'] ) : '',
			'percent' => isset( $in['percent'] ) ? min( 90, absint( $in['percent'] ) ) : 0,
			'minimum' => isset( $in['minimum'] ) ? max( 0, (float) wc_format_decimal( sanitize_text_field( $in['minimum'] ) ) ) : 0,
			'from'    => isset( $in['from'] ) ? $time( $in['from'] ) : '',
			'to'      => isset( $in['to'] ) ? $time( $in['to'] ) : '',
			'texts'   => array(),
		);
		foreach ( array( 'bar', 'popup', 'banner', 'hero', 'labels' ) as $key ) {
			$clean[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
		}
		foreach ( self::TEXTS as $key ) {
			foreach ( array_keys( AIMP_I18n::languages() ) as $lang ) {
				$name = $key . '_' . $lang;
				$text = isset( $in['texts'][ $name ] ) ? trim( sanitize_textarea_field( $in['texts'][ $name ] ) ) : '';
				if ( '' !== $text ) {
					$clean['texts'][ $name ] = $text;
				}
			}
		}
		update_option( self::OPTION, $clean, false );

		// Refresh the cached pages now, at the start and at the end.
		wp_clear_scheduled_hook( self::SWITCH );
		foreach ( array( self::start(), self::end() ) as $moment ) {
			if ( $moment > time() ) {
				wp_schedule_single_event( $moment + 5, self::SWITCH, array( $moment ) );
			}
		}
		self::purge();

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&saved=1' ) );
		exit;
	}

	/**
	 * @return string The sale's state for the admin page.
	 */
	private static function status() {
		$s     = self::settings();
		$start = self::start();
		$end   = self::end();
		if ( empty( $s['on'] ) ) {
			return __( 'Off', 'atelier-irisee-master-plugin' );
		}
		if ( ! self::percent() || ! $start || ! $end ) {
			return __( 'On, but the percentage, start or end is missing.', 'atelier-irisee-master-plugin' );
		}
		if ( $end <= $start ) {
			return __( 'On, but the end is before the start.', 'atelier-irisee-master-plugin' );
		}
		if ( time() < $start ) {
			/* translators: %s: date and time */
			return sprintf( __( 'Starts on %s.', 'atelier-irisee-master-plugin' ), self::end_text( $start ) );
		}
		if ( time() >= $end ) {
			/* translators: %s: date and time */
			return sprintf( __( 'Ended on %s.', 'atelier-irisee-master-plugin' ), self::end_text( $end ) );
		}
		/* translators: %s: date and time */
		return sprintf( __( 'Running now, until %s.', 'atelier-irisee-master-plugin' ), self::end_text( $end ) );
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s      = self::settings();
		$langs  = AIMP_I18n::languages();
		$labels = array(
			'bar'          => __( 'Gold bar text', 'atelier-irisee-master-plugin' ),
			'popup_title'  => __( 'Popup title', 'atelier-irisee-master-plugin' ),
			'popup_text'   => __( 'Popup text', 'atelier-irisee-master-plugin' ),
			'banner_title' => __( 'Banner title', 'atelier-irisee-master-plugin' ),
			'banner_text'  => __( 'Banner text', 'atelier-irisee-master-plugin' ),
			'button'       => __( 'Button text', 'atelier-irisee-master-plugin' ),
		);
		$check  = function ( $key, $label, $help = '' ) use ( $s ) {
			printf(
				'<label style="display:block;margin:0 0 6px"><input type="checkbox" name="sale[%1$s]" value="1"%2$s> %3$s</label>%4$s',
				esc_attr( $key ),
				checked( ! empty( $s[ $key ] ), true, false ),
				esc_html( $label ),
				'' !== $help ? '<p class="description" style="margin:-2px 0 8px 24px">' . esc_html( $help ) . '</p>' : ''
			);
		};
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sale (Black Friday)', 'atelier-irisee-master-plugin' ); ?></h1>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only a message. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'atelier-irisee-master-plugin' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'A discount on the whole shop for a set time. Gift cards are excepted; sewing project kits first get their kit discount, then this one. It is taken off in the cart once the order reaches the minimum amount; discount codes can\'t be used with it.', 'atelier-irisee-master-plugin' ); ?></p>
			<p><strong><?php esc_html_e( 'Status:', 'atelier-irisee-master-plugin' ); ?></strong> <?php echo esc_html( self::status() ); ?></p>
			<p>
				<?php if ( self::preview() ) : ?>
					<strong><?php esc_html_e( 'Preview is on: you see the sale on the site as if it ran now; visitors don\'t.', 'atelier-irisee-master-plugin' ); ?></strong>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_sale_preview' ), 'aimp_sale_preview' ) ); ?>"><?php esc_html_e( 'Stop preview', 'atelier-irisee-master-plugin' ); ?></a>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_sale_preview&on=1' ), 'aimp_sale_preview' ) ); ?>"><?php esc_html_e( 'Preview on the site', 'atelier-irisee-master-plugin' ); ?></a>
					<span class="description"><?php esc_html_e( 'Only you see it, for an hour (saved settings; without a percentage: 20%).', 'atelier-irisee-master-plugin' ); ?></span>
				<?php endif; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aimp_sale_save">
				<?php wp_nonce_field( 'aimp_sale_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Sale', 'atelier-irisee-master-plugin' ); ?></th>
						<td><?php $check( 'on', __( 'On (only between the start and the end)', 'atelier-irisee-master-plugin' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="aimp-sale-title"><?php esc_html_e( 'Name', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td><input type="text" id="aimp-sale-title" name="sale[title]" value="<?php echo esc_attr( $s['title'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="aimp-sale-percent"><?php esc_html_e( 'Discount', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td><input type="number" id="aimp-sale-percent" name="sale[percent]" min="0" max="90" step="1" value="<?php echo esc_attr( (string) (int) $s['percent'] ); ?>" class="small-text"> %</td>
					</tr>
					<tr>
						<th scope="row"><label for="aimp-sale-minimum"><?php esc_html_e( 'Minimum order amount', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td>
							<input type="number" id="aimp-sale-minimum" name="sale[minimum]" min="0" step="0.01" value="<?php echo esc_attr( (string) (float) $s['minimum'] ); ?>" class="small-text"> €
							<p class="description"><?php esc_html_e( 'The products in the cart (after the kit discount, without gift cards and shipping). 0: no minimum.', 'atelier-irisee-master-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aimp-sale-from"><?php esc_html_e( 'Start', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td><input type="datetime-local" id="aimp-sale-from" name="sale[from]" value="<?php echo esc_attr( $s['from'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="aimp-sale-to"><?php esc_html_e( 'End', 'atelier-irisee-master-plugin' ); ?></label></th>
						<td>
							<input type="datetime-local" id="aimp-sale-to" name="sale[to]" value="<?php echo esc_attr( $s['to'] ); ?>">
							<?php /* translators: %s: time zone */ ?>
							<p class="description"><?php echo esc_html( sprintf( __( 'Time zone: %s.', 'atelier-irisee-master-plugin' ), wp_timezone_string() ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Announce it', 'atelier-irisee-master-plugin' ); ?></th>
						<td>
							<?php
							$check( 'bar', __( 'Gold bar on top', 'atelier-irisee-master-plugin' ), __( 'The sale as the first text of the gold bar.', 'atelier-irisee-master-plugin' ) );
							$check( 'popup', __( 'Popup', 'atelier-irisee-master-plugin' ), __( 'Once per visitor, not on the cart or checkout.', 'atelier-irisee-master-plugin' ) );
							$check( 'banner', __( 'Home page banner', 'atelier-irisee-master-plugin' ), __( 'With a countdown to the end.', 'atelier-irisee-master-plugin' ) );
							/* translators: %s: shortcode */
							$check( 'hero', __( 'Banner right under the home page hero', 'atelier-irisee-master-plugin' ), sprintf( __( 'Off: place it yourself with the shortcode %s.', 'atelier-irisee-master-plugin' ), '[' . self::TAG . ']' ) );
							$check( 'labels', __( 'Label on products', 'atelier-irisee-master-plugin' ), __( 'In the shop and on product pages, and a line in the cart with what is still needed for the minimum.', 'atelier-irisee-master-plugin' ) );
							?>
						</td>
					</tr>
				</table>
				<h2><?php esc_html_e( 'Texts', 'atelier-irisee-master-plugin' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Empty: the text shown in grey. You can use {title}, {percent}, {minimum} and {end}.', 'atelier-irisee-master-plugin' ); ?></p>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th style="width:150px"></th>
						<?php foreach ( $langs as $lang ) : ?>
							<th><?php echo esc_html( $lang['name'] ); ?></th>
						<?php endforeach; ?>
					</tr></thead>
					<tbody>
						<?php foreach ( $labels as $key => $label ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<?php foreach ( array_keys( $langs ) as $code ) : ?>
									<?php
									$default = AIMP_I18n::with_language(
										$code,
										function () use ( $key ) {
											$texts = self::default_texts();
											return $texts[ $key ];
										}
									);
									$value   = isset( $s['texts'][ $key . '_' . $code ] ) ? $s['texts'][ $key . '_' . $code ] : '';
									?>
									<td><textarea name="sale[texts][<?php echo esc_attr( $key . '_' . $code ); ?>]" rows="2" style="width:100%" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $value ); ?></textarea></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * The discount in the cart
	 * ------------------------------------------------------------------ */

	/**
	 * Whether a cart item gets the discount (everything but gift cards).
	 *
	 * @param array $item Cart item.
	 * @return bool
	 */
	public static function eligible( $item ) {
		if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product || ! empty( $item['aimp_gc'] ) ) {
			return false;
		}
		if ( class_exists( 'AIMP_Giftcards_Product' ) ) {
			$product = $item['data'];
			$parent  = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
			if ( $parent && AIMP_Giftcards_Product::is_giftcard( $parent ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Price of one unit before this discount: the product's price, with the kit discount for kit lines.
	 *
	 * @param array $item Cart item.
	 * @return float|null
	 */
	private static function base_price( $item ) {
		$fresh = wc_get_product( $item['data']->get_id() );
		if ( ! $fresh ) {
			return null;
		}
		$price = (float) $fresh->get_price();
		if ( class_exists( 'AIMP_Cart' ) && AIMP_Cart::is_kit_item( $item ) ) {
			$kit = AIMP_Cart::item_discount( $item );
			if ( $kit > 0 ) {
				$price = round( $price * ( 100 - $kit ) / 100, 4 );
			}
		}
		return $price;
	}

	/**
	 * The products in the cart that count for the minimum (before this discount).
	 *
	 * @param WC_Cart|null $cart Cart.
	 * @return float
	 */
	public static function cart_base_total( $cart = null ) {
		$cart = $cart ? $cart : ( function_exists( 'WC' ) ? WC()->cart : null );
		if ( ! $cart ) {
			return 0.0;
		}
		$total = 0.0;
		foreach ( $cart->get_cart() as $item ) {
			if ( self::eligible( $item ) ) {
				$price  = self::base_price( $item );
				$total += null === $price ? 0.0 : $price * (float) $item['quantity'];
			}
		}
		return $total;
	}

	/**
	 * Whether the discount applies to this cart now.
	 *
	 * @param WC_Cart|null $cart Cart.
	 * @return bool
	 */
	public static function cart_applies( $cart = null ) {
		if ( ! self::active() ) {
			return false;
		}
		$total = self::cart_base_total( $cart );
		return $total > 0 && $total >= self::minimum();
	}

	/**
	 * The line prices: lowered when the discount applies, the price before it otherwise (the cart can drop below
	 * the minimum within one request). Always from a fresh product, so it is never taken off twice.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function apply( $cart ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! self::active() ) {
			return;
		}
		$applies = self::cart_applies( $cart );
		$percent = self::percent();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! self::eligible( $item ) ) {
				continue;
			}
			$price = self::base_price( $item );
			if ( null !== $price ) {
				$item['data']->set_price( $applies ? round( $price * ( 100 - $percent ) / 100, 4 ) : $price );
			}
		}
		// No discount codes on top.
		if ( $applies && $cart->get_applied_coupons() ) {
			foreach ( $cart->get_applied_coupons() as $code ) {
				$cart->remove_coupon( $code );
			}
			if ( ! self::$told && function_exists( 'wc_add_notice' ) ) {
				self::$told = true;
				wc_add_notice( self::codes_message(), 'notice' );
			}
		}
	}

	/** @return string */
	private static function codes_message() {
		/* translators: %s: sale name, e.g. Black Friday */
		return sprintf( __( 'Discount codes can\'t be used together with the %s discount. Your personal codes stay in your account for a next order.', 'atelier-irisee-master-plugin' ), self::title() );
	}

	/**
	 * A discount code is refused while the sale discount applies to the cart.
	 *
	 * @param bool      $valid  Valid so far.
	 * @param WC_Coupon $coupon Coupon.
	 * @return bool
	 * @throws Exception With the reason (WooCommerce shows it).
	 */
	public static function coupon_valid( $valid, $coupon ) {
		if ( $valid && function_exists( 'WC' ) && WC()->cart && ! is_admin() && self::cart_applies( WC()->cart ) ) {
			throw new Exception( esc_html( self::codes_message() ), 100 );
		}
		return $valid;
	}

	/**
	 * The discount on the order lines, for the order screen and later reference.
	 *
	 * @param WC_Order_Item_Product $item   Order line.
	 * @param string                $key    Cart key.
	 * @param array                 $values Cart item.
	 */
	public static function order_item_meta( $item, $key, $values ) {
		if ( self::eligible( $values ) && self::cart_applies() ) {
			$item->add_meta_data( '_aimp_sale', self::percent(), true );
		}
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public static function order_meta( $order ) {
		if ( self::cart_applies() ) {
			$order->update_meta_data( '_aimp_sale', self::title() . ' −' . self::percent() . '%' );
		}
	}

	/**
	 * Cart and checkout totals: what is still needed for the discount, or what it saves; and a customer who still
	 * has a welcome code is told it stays valid.
	 */
	public static function total_row() {
		if ( ! self::active() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$base = self::cart_base_total();
		if ( $base <= 0 ) {
			return;
		}
		$percent = self::percent();
		$label   = self::title() . ' −' . $percent . '%';
		$missing = self::minimum() - $base;
		if ( $missing > 0 ) {
			$done = (int) max( 0, min( 100, round( 100 * $base / self::minimum() ) ) );
			/* translators: 1: discount, e.g. -20%, 2: order amount, 3: amount still to go */
			$text = sprintf( __( '%1$s from %2$s: %3$s to go.', 'atelier-irisee-master-plugin' ), '−' . $percent . '%', wc_price( self::minimum() ), wc_price( $missing ) );
			$cell = sprintf(
				'<span class="aimp-free-shipping-hint aimp-sale-hint">%1$s<span class="aimp-free-shipping-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%2$d" aria-label="%3$s"><span style="width:%2$d%%"></span></span></span>',
				wp_kses_post( $text ),
				$done,
				esc_attr( wp_strip_all_tags( $text ) )
			);
		} else {
			/* translators: %s: amount saved */
			$cell = '<span class="aimp-sale-saved">' . wp_kses_post( sprintf( __( 'Applied: you save %s', 'atelier-irisee-master-plugin' ), wc_price( $base * $percent / 100 ) ) ) . '</span>';
			$code = self::welcome_code();
			if ( $code ) {
				$cell .= '<span class="aimp-sale-welcome">' . esc_html( $code ) . '</span>';
			}
		}
		printf( '<tr class="aimp-sale-row"><th>%1$s</th><td data-title="%2$s">%3$s</td></tr>', esc_html( $label ), esc_attr( $label ), $cell ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
	}

	/**
	 * "Your welcome code X stays valid until …: use it for a next order." for a customer with an unused one.
	 *
	 * @return string
	 */
	private static function welcome_code() {
		if ( ! is_user_logged_in() || ! class_exists( 'AIMP_Coupons' ) || ! method_exists( 'AIMP_Coupons', 'welcome_coupon' ) ) {
			return '';
		}
		$coupon = AIMP_Coupons::welcome_coupon( wp_get_current_user() );
		if ( ! $coupon ) {
			return '';
		}
		$expires = $coupon->get_date_expires();
		if ( $expires ) {
			/* translators: 1: code, 2: date */
			return sprintf( __( 'Your welcome code %1$s stays valid until %2$s: use it for a next order.', 'atelier-irisee-master-plugin' ), strtoupper( $coupon->get_code() ), AIMP_I18n::date( $expires->getTimestamp() ) );
		}
		/* translators: %s: code */
		return sprintf( __( 'Your welcome code %s stays valid: use it for a next order.', 'atelier-irisee-master-plugin' ), strtoupper( $coupon->get_code() ) );
	}

	/**
	 * For the cart's own lines: the discount of a line (0: none).
	 *
	 * @param array $item Cart item.
	 * @return int
	 */
	public static function line_percent( $item ) {
		return self::eligible( $item ) && self::cart_applies() ? self::percent() : 0;
	}

	/* ------------------------------------------------------------------
	 * Announcements
	 * ------------------------------------------------------------------ */

	public static function assets() {
		if ( self::active() ) {
			wp_enqueue_style( 'aimp-sale', AIMP_PLUGIN_URL . 'assets/css/sale.css', array(), AIMP_VERSION );
		}
	}

	/**
	 * The gold bar's first text ('' when not shown).
	 *
	 * @return string
	 */
	public static function bar_text() {
		return self::shows( 'bar' ) ? self::text( 'bar' ) : '';
	}

	/**
	 * Where the buttons lead: the shop.
	 *
	 * @return string
	 */
	private static function shop_url() {
		return class_exists( 'AIMP_Search' ) ? AIMP_Search::results_url() : wc_get_page_permalink( 'shop' );
	}

	/** @return string */
	public static function bar_url() {
		return self::shop_url();
	}

	/**
	 * The label on a product ('' for gift cards, or when not shown).
	 *
	 * @param WC_Product $product Product.
	 * @param bool       $short   "Black Friday −20%" (shop cards) instead of the sentence (product page).
	 * @return string
	 */
	public static function label( $product, $short = false ) {
		if ( ! self::shows( 'labels' ) || ! $product instanceof WC_Product ) {
			return '';
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( class_exists( 'AIMP_Giftcards_Product' ) && $parent && AIMP_Giftcards_Product::is_giftcard( $parent ) ) {
			return '';
		}
		if ( $short ) {
			return self::title() . ' −' . self::percent() . '%';
		}
		return self::fill( self::minimum() > 0 ? __( '{title}: −{percent}% in your cart on orders from {minimum}', 'atelier-irisee-master-plugin' ) : __( '{title}: −{percent}% in your cart', 'atelier-irisee-master-plugin' ) );
	}

	/**
	 * The label under the price on a product page.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function label_html( $product ) {
		$label = self::label( $product );
		return '' !== $label ? '<p class="aimp-sale-label">' . esc_html( $label ) . '</p>' : '';
	}

	/**
	 * The countdown: shown without JavaScript as "Until …", counted down by the script.
	 *
	 * @return string
	 */
	private static function countdown_html() {
		$end = self::end();
		/* translators: %s: date and time */
		return '<p class="aimp-sale-count" data-aimp-sale-end="' . esc_attr( (string) $end ) . '" data-days="' . esc_attr__( 'd', 'atelier-irisee-master-plugin' ) . '" data-hours="' . esc_attr__( 'h', 'atelier-irisee-master-plugin' ) . '" data-minutes="' . esc_attr__( 'min', 'atelier-irisee-master-plugin' ) . '" data-left="' . esc_attr__( 'Still', 'atelier-irisee-master-plugin' ) . '">' . esc_html( sprintf( __( 'Until %s', 'atelier-irisee-master-plugin' ), self::end_text( $end ) ) ) . '</p>';
	}

	/** @return string The countdown script (once per page). */
	private static function countdown_script() {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		return '<script>(function(){function run(){document.querySelectorAll("[data-aimp-sale-end]").forEach(function(el){var end=parseInt(el.getAttribute("data-aimp-sale-end"),10)*1000,box=el.closest("[data-aimp-sale]");function tick(){var s=Math.floor((end-Date.now())/1000);if(s<=0){if(box){box.hidden=true;}return;}var d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60);el.textContent=el.getAttribute("data-left")+" "+(d?d+" "+el.getAttribute("data-days")+" ":"")+h+" "+el.getAttribute("data-hours")+" "+m+" "+el.getAttribute("data-minutes");setTimeout(tick,30000);}tick();});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}})();</script>';
	}

	/**
	 * The home page banner (shortcode; empty when the sale is not running).
	 *
	 * @return string
	 */
	public static function banner() {
		if ( ! self::shows( 'banner' ) ) {
			return '';
		}
		wp_enqueue_style( 'aimp-sale', AIMP_PLUGIN_URL . 'assets/css/sale.css', array(), AIMP_VERSION );
		return '<section class="aimp-sale-banner" data-aimp-sale><div class="aimp-sale-banner-inner">' .
			'<p class="aimp-sale-banner-percent" aria-hidden="true">−' . (int) self::percent() . '%</p>' .
			'<div class="aimp-sale-banner-body"><h2 class="aimp-sale-banner-title">' . esc_html( self::text( 'banner_title' ) ) . '</h2>' .
			'<p class="aimp-sale-banner-text">' . esc_html( self::text( 'banner_text' ) ) . '</p>' . self::countdown_html() . '</div>' .
			'<a class="aimp-sale-button" href="' . esc_url( self::shop_url() ) . '">' . esc_html( self::text( 'button' ) ) . '</a>' .
			'</div></section>' . self::countdown_script();
	}

	/**
	 * The banner right under the home page hero.
	 *
	 * @param string $html After the hero.
	 * @return string
	 */
	public static function after_hero( $html ) {
		$s = self::settings();
		return ! empty( $s['hero'] ) && is_front_page() ? $html . self::banner() : $html;
	}

	/** The popup: once per visitor per sale, not on the cart or checkout, never over another popup. */
	public static function popup() {
		if ( ! self::shows( 'popup' ) || is_admin() || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
			return;
		}
		$key = 'aimp_sale_seen_' . self::start();
		?>
		<dialog class="aimp-sale-popup" data-aimp-sale data-aimp-sale-popup aria-labelledby="aimp-sale-popup-title">
			<button type="button" class="aimp-sale-popup-close" data-aimp-sale-close aria-label="<?php esc_attr_e( 'Close', 'atelier-irisee-master-plugin' ); ?>">×</button>
			<p class="aimp-sale-popup-percent" aria-hidden="true">−<?php echo (int) self::percent(); ?>%</p>
			<h2 id="aimp-sale-popup-title" class="aimp-sale-popup-title"><?php echo esc_html( self::text( 'popup_title' ) ); ?></h2>
			<p class="aimp-sale-popup-text"><?php echo esc_html( self::text( 'popup_text' ) ); ?></p>
			<?php echo self::countdown_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in countdown_html(). ?>
			<a class="aimp-sale-button" href="<?php echo esc_url( self::shop_url() ); ?>" data-aimp-sale-close><?php echo esc_html( self::text( 'button' ) ); ?></a>
		</dialog>
		<?php echo self::countdown_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed script. ?>
		<script>
		( function () {
			var dialog = document.querySelector( '[data-aimp-sale-popup]' );
			var key = <?php echo wp_json_encode( $key ); ?>;
			var end = <?php echo (int) self::end(); ?> * 1000;
			if ( ! dialog || Date.now() >= end ) {
				return;
			}
			try {
				if ( window.localStorage.getItem( key ) ) {
					return;
				}
			} catch ( e ) {
				return;
			}
			var close = function () {
				if ( dialog.close ) {
					dialog.close();
				} else {
					dialog.removeAttribute( 'open' );
				}
			};
			dialog.addEventListener( 'click', function ( e ) {
				if ( e.target === dialog || e.target.closest( '[data-aimp-sale-close]' ) ) {
					close();
				}
			} );
			var tries = 0;
			var open = function () {
				// Never over another popup (newsletter, login, welcome code): wait until it is closed.
				if ( document.querySelector( '.aimp-nl-popup, dialog[open]:not([data-aimp-sale-popup]), [data-aimp-modal]:not([hidden])' ) ) {
					if ( ++tries < 20 ) {
						setTimeout( open, 3000 );
					}
					return;
				}
				try {
					window.localStorage.setItem( key, '1' );
				} catch ( e ) {}
				if ( dialog.showModal ) {
					dialog.showModal();
				} else {
					dialog.setAttribute( 'open', '' );
				}
			};
			setTimeout( open, 2000 );
		} )();
		</script>
		<?php
	}
}
