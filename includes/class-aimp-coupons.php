<?php
/**
 * Discount codes. They are WooCommerce coupons (Marketing → Coupons); the plugin adds the shop's rules:
 * codes can't be combined, and they don't apply to products that already have a sale price
 * (sewing project kits excepted) or to gift cards.
 *
 * New customers get a personal welcome code after their first login: shown once in a popup and kept
 * in their account until it has been used.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Coupons {

	const META_PENDING = '_aimp_welcome_pending';
	const META_COUPON  = '_aimp_welcome_coupon';
	const META_POPUP   = '_aimp_welcome_popup';

	/** Characters for welcome codes (no 0/O and 1/I). */
	const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	public static function init() {
		// Shop rules for every coupon.
		add_filter( 'woocommerce_coupon_get_individual_use', '__return_true' );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'valid_for_product' ), 20, 4 );

		// Welcome code.
		add_action( 'user_register', array( __CLASS__, 'mark_new_customer' ) );
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 20, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_create_for_current_user' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'popup' ), 20 );

		// "Use in my cart" from the account page.
		add_action( 'wp_loaded', array( __CLASS__, 'apply_from_link' ), 25 );
	}

	/* ------------------------------------------------------------------
	 * Rules
	 * ------------------------------------------------------------------ */

	/**
	 * No discount on products with a sale price (except the items of a sewing project kit) or on gift cards.
	 *
	 * @param bool       $valid   Valid so far.
	 * @param WC_Product $product Product.
	 * @param WC_Coupon  $coupon  Coupon.
	 * @param array      $values  Cart item.
	 * @return bool
	 */
	public static function valid_for_product( $valid, $product, $coupon, $values ) {
		if ( ! $valid || ! $product instanceof WC_Product ) {
			return $valid;
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( class_exists( 'AIMP_Giftcards_Product' ) && $parent && AIMP_Giftcards_Product::is_giftcard( $parent ) ) {
			return false;
		}
		$is_kit = is_array( $values ) && AIMP_Cart::is_kit_item( $values );
		if ( ! $is_kit ) {
			$fresh = wc_get_product( $product->get_id() );
			if ( $fresh && $fresh->is_on_sale() ) {
				return false;
			}
		}
		return $valid;
	}

	/* ------------------------------------------------------------------
	 * Welcome code
	 * ------------------------------------------------------------------ */

	/**
	 * @param int $user_id New user.
	 */
	public static function mark_new_customer( $user_id ) {
		if ( AIMP_Settings::get( 'welcome_discount' ) ) {
			update_user_meta( $user_id, self::META_PENDING, 1 );
		}
	}

	/**
	 * @param string  $login User login.
	 * @param WP_User $user  User.
	 */
	public static function on_login( $login, $user ) {
		self::maybe_create( $user );
	}

	/**
	 * Also covers being logged in right after registering (and social login).
	 */
	public static function maybe_create_for_current_user() {
		if ( is_user_logged_in() ) {
			self::maybe_create( wp_get_current_user() );
		}
	}

	/**
	 * Create the welcome code once, for a new customer who has not had one.
	 *
	 * @param WP_User $user User.
	 */
	private static function maybe_create( $user ) {
		$percent = AIMP_Settings::get( 'welcome_discount' );
		if ( ! $user instanceof WP_User || ! $user->ID || ! $percent || ! get_user_meta( $user->ID, self::META_PENDING, true ) ) {
			return;
		}
		delete_user_meta( $user->ID, self::META_PENDING );
		if ( get_user_meta( $user->ID, self::META_COUPON, true ) || ! is_email( $user->user_email ) ) {
			return;
		}

		do {
			$code = 'WELCOME-';
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= substr( self::ALPHABET, wp_rand( 0, strlen( self::ALPHABET ) - 1 ), 1 );
			}
		} while ( wc_get_coupon_id_by_code( $code ) );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( min( 90, $percent ) );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_email_restrictions( array( strtolower( $user->user_email ) ) );
		$days = AIMP_Settings::get( 'welcome_days' );
		if ( $days ) {
			$coupon->set_date_expires( time() + $days * DAY_IN_SECONDS );
		}
		/* translators: %s: email address */
		$coupon->set_description( sprintf( __( 'Welcome code for %s', 'atelier-irisee-master-plugin' ), $user->user_email ) );
		$id = $coupon->save();

		if ( $id ) {
			update_user_meta( $user->ID, self::META_COUPON, $id );
			update_user_meta( $user->ID, self::META_POPUP, 1 );
		}
	}

	/**
	 * A coupon the customer can still use: not used up, not expired.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return bool
	 */
	private static function usable( $coupon ) {
		if ( ! $coupon->get_id() || 'publish' !== get_post_status( $coupon->get_id() ) ) {
			return false;
		}
		if ( $coupon->get_usage_limit() && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
			return false;
		}
		$expires = $coupon->get_date_expires();
		return ! ( $expires && $expires->getTimestamp() < time() );
	}

	/**
	 * Personal coupons of a customer that can still be used: their welcome code and any coupon limited
	 * to their email address.
	 *
	 * @param WP_User $user User.
	 * @return WC_Coupon[]
	 */
	public static function personal_coupons( $user ) {
		$ids = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'customer_email',
						'value'   => '"' . strtolower( $user->user_email ) . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);
		$welcome = (int) get_user_meta( $user->ID, self::META_COUPON, true );
		if ( $welcome ) {
			$ids[] = $welcome;
		}
		$coupons = array();
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			$coupon = new WC_Coupon( $id );
			if ( self::usable( $coupon ) && ( ! $coupon->get_usage_limit_per_user() || ! in_array( (string) $user->ID, array_map( 'strval', (array) $coupon->get_used_by() ), true ) ) ) {
				$coupons[] = $coupon;
			}
		}
		return $coupons;
	}

	/**
	 * "10%" or "€ 5,00".
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return string
	 */
	private static function value_text( $coupon ) {
		return 'percent' === $coupon->get_discount_type()
			? wc_format_localized_decimal( $coupon->get_amount() ) . '%'
			: wp_strip_all_tags( wc_price( $coupon->get_amount() ) );
	}

	/**
	 * The list of personal codes on the account page.
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	public static function account_codes_html( $user ) {
		$coupons = self::personal_coupons( $user );
		$html    = '<h3 class="aimp-account-subtitle">' . esc_html__( 'My discount codes', 'atelier-irisee-master-plugin' ) . '</h3>';
		if ( ! $coupons ) {
			return $html . '<p class="aimp-account-empty">' . esc_html__( 'You have no discount codes at the moment.', 'atelier-irisee-master-plugin' ) . '</p>';
		}
		$html .= '<ul class="aimp-gc-list aimp-coupon-list">';
		foreach ( $coupons as $coupon ) {
			$expires = $coupon->get_date_expires();
			$html   .= '<li class="aimp-gc-item aimp-coupon-item"><div class="aimp-gc-item-info">';
			/* translators: %s: discount, e.g. 10% */
			$html .= '<p class="aimp-gc-item-value">' . esc_html( sprintf( __( '%s discount', 'atelier-irisee-master-plugin' ), self::value_text( $coupon ) ) ) . '</p>';
			$html .= '<p class="aimp-gc-item-code"><code>' . esc_html( strtoupper( $coupon->get_code() ) ) . '</code> <button type="button" class="aimp-gc-link" data-aimp-gc-copy="' . esc_attr( strtoupper( $coupon->get_code() ) ) . '">' . esc_html__( 'Copy code', 'atelier-irisee-master-plugin' ) . '</button></p>';
			if ( $expires ) {
				/* translators: %s: date */
				$html .= '<p class="aimp-gc-item-meta"><span>' . esc_html( sprintf( __( 'Valid until %s', 'atelier-irisee-master-plugin' ), date_i18n( get_option( 'date_format' ), $expires->getTimestamp() ) ) ) . '</span></p>';
			}
			$html .= '</div><a class="aimp-account-button" href="' . esc_url( add_query_arg( 'aimp_coupon', rawurlencode( $coupon->get_code() ), wc_get_cart_url() ) ) . '">' . esc_html__( 'Use in my cart', 'atelier-irisee-master-plugin' ) . '</a></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * ?aimp_coupon=CODE (from the account page) applies the code to the cart.
	 */
	public static function apply_from_link() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only adds a discount code to the visitor's own cart; WooCommerce validates it.
		$code = isset( $_GET['aimp_coupon'] ) ? wc_format_coupon_code( sanitize_text_field( wp_unslash( $_GET['aimp_coupon'] ) ) ) : '';
		if ( '' === $code || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! WC()->cart->has_discount( $code ) ) {
			WC()->cart->apply_coupon( $code );
		}
		wp_safe_redirect( remove_query_arg( 'aimp_coupon' ) );
		exit;
	}

	/**
	 * The welcome popup, once, on the first page after the code was made.
	 */
	public static function popup() {
		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! get_user_meta( $user_id, self::META_POPUP, true ) ) {
			return;
		}
		delete_user_meta( $user_id, self::META_POPUP );
		$coupon = new WC_Coupon( (int) get_user_meta( $user_id, self::META_COUPON, true ) );
		if ( ! self::usable( $coupon ) ) {
			return;
		}
		$code    = strtoupper( $coupon->get_code() );
		$expires = $coupon->get_date_expires();
		$shop    = wc_get_page_permalink( 'shop' );
		?>
		<dialog class="aimp-welcome" data-aimp-welcome aria-labelledby="aimp-welcome-title">
			<button type="button" class="aimp-welcome-close" data-aimp-welcome-close aria-label="<?php esc_attr_e( 'Close', 'atelier-irisee-master-plugin' ); ?>">×</button>
			<h2 id="aimp-welcome-title"><?php esc_html_e( 'Welcome!', 'atelier-irisee-master-plugin' ); ?></h2>
			<p>
				<?php
				/* translators: %s: discount, e.g. 10% */
				echo esc_html( sprintf( __( 'Thank you for creating an account. Here is %s off your first order.', 'atelier-irisee-master-plugin' ), self::value_text( $coupon ) ) );
				?>
			</p>
			<p class="aimp-welcome-code"><code><?php echo esc_html( $code ); ?></code> <button type="button" class="aimp-welcome-copy" data-copy="<?php echo esc_attr( $code ); ?>" data-done="<?php esc_attr_e( 'Copied', 'atelier-irisee-master-plugin' ); ?>"><?php esc_html_e( 'Copy code', 'atelier-irisee-master-plugin' ); ?></button></p>
			<?php if ( $expires ) : ?>
				<p class="aimp-welcome-small">
					<?php
					/* translators: %s: date */
					echo esc_html( sprintf( __( 'Valid until %s. You can also find it in your account, under "Gift cards and discount codes".', 'atelier-irisee-master-plugin' ), date_i18n( get_option( 'date_format' ), $expires->getTimestamp() ) ) );
					?>
				</p>
			<?php else : ?>
				<p class="aimp-welcome-small"><?php esc_html_e( 'You can also find it in your account, under "Gift cards and discount codes".', 'atelier-irisee-master-plugin' ); ?></p>
			<?php endif; ?>
			<a class="aimp-welcome-button" href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'Shop now', 'atelier-irisee-master-plugin' ); ?></a>
		</dialog>
		<script>
			( function () {
				var dialog = document.querySelector( '[data-aimp-welcome]' );
				if ( ! dialog ) {
					return;
				}
				var close = function () {
					if ( typeof dialog.close === 'function' ) {
						dialog.close();
					} else {
						dialog.removeAttribute( 'open' );
					}
				};
				dialog.querySelector( '[data-aimp-welcome-close]' ).addEventListener( 'click', close );
				dialog.addEventListener( 'click', function ( e ) {
					if ( e.target === dialog ) {
						close();
					}
				} );
				var copy = dialog.querySelector( '.aimp-welcome-copy' );
				copy.addEventListener( 'click', function () {
					if ( navigator.clipboard ) {
						navigator.clipboard.writeText( copy.getAttribute( 'data-copy' ) );
					}
					copy.textContent = copy.getAttribute( 'data-done' );
				} );
				if ( typeof dialog.showModal === 'function' ) {
					dialog.showModal();
				} else {
					dialog.setAttribute( 'open', '' );
				}
			} )();
		</script>
		<?php
	}
}
