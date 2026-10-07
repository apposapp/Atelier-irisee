<?php
/**
 * Step-by-step checkout: [atelier_irisee_checkout].
 *
 * Cart → Details → Overview → Payment → Confirmation, with a step bar like the configurator. It prints
 * WooCommerce's own checkout form (so payment plugins such as Mollie work unchanged); checkout.js shows
 * one step at a time by switching visibility, without moving anything, so WooCommerce's own updates of
 * totals and shipping keep working. Without JavaScript all steps simply show one under the other.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Checkout {

	const TAG = 'atelier_irisee_checkout';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		// Payment step: discount code and gift card together, above the payment methods (the gift card box
		// prints at priority 10 of the same hook).
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'codes_open' ), 5 );
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'codes_close' ), 15 );
		// Logged-in customers: empty fields are filled from their account.
		add_filter( 'woocommerce_checkout_get_value', array( __CLASS__, 'prefill' ), 20, 2 );
		// Confirmation step: a thank-you block on top, next steps (and an account offer) below the order.
		add_action( 'woocommerce_before_thankyou', array( __CLASS__, 'thankyou_top' ), 5 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou_bottom' ), 30 );
	}

	/**
	 * @param int $order_id Order.
	 * @return WC_Order|null The order, when it is shown on our checkout page and was not a failed payment.
	 */
	private static function thankyou_order( $order_id ) {
		$order = self::$rendering ? wc_get_order( $order_id ) : null;
		return ( $order && ! $order->has_status( 'failed' ) ) ? $order : null;
	}

	/**
	 * Title, the order at a glance (number, date, total, payment method) and the delivery address.
	 *
	 * @param int $order_id Order.
	 */
	public static function thankyou_top( $order_id ) {
		$order = self::thankyou_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$name    = $order->get_billing_first_name();
		$address = $order->has_shipping_address() ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address();
		$facts   = array(
			__( 'Order number', 'atelier-irisee-master-plugin' )   => $order->get_order_number(),
			__( 'Date', 'atelier-irisee-master-plugin' )           => wc_format_datetime( $order->get_date_created() ),
			__( 'Total', 'atelier-irisee-master-plugin' )          => wp_strip_all_tags( $order->get_formatted_order_total() ),
			__( 'Payment method', 'atelier-irisee-master-plugin' ) => wp_strip_all_tags( $order->get_payment_method_title() ),
		);
		if ( $order->needs_shipping_address() && ! $order->has_status( array( 'failed', 'cancelled' ) ) ) {
			$facts[ __( 'Expected delivery', 'atelier-irisee-master-plugin' ) ] = AIMP_Trust::expected_date( $order );
		}
		?>
		<div class="aimp-thankyou">
			<h2 class="aimp-thankyou-title">
				<?php
				echo esc_html(
					'' !== $name
						/* translators: %s: customer's first name */
						? sprintf( __( 'Thank you for your order, %s!', 'atelier-irisee-master-plugin' ), $name )
						: __( 'Thank you for your order!', 'atelier-irisee-master-plugin' )
				);
				?>
			</h2>
			<p class="aimp-thankyou-intro"><?php esc_html_e( 'We have received your order. You will get a confirmation by email.', 'atelier-irisee-master-plugin' ); ?></p>
			<ul class="aimp-thankyou-facts">
				<?php foreach ( $facts as $label => $value ) : ?>
					<?php if ( '' !== (string) $value ) : ?>
						<li><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $value ); ?></strong></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<?php if ( $address ) : ?>
				<div class="aimp-thankyou-address">
					<h3><?php esc_html_e( 'Delivery address', 'atelier-irisee-master-plugin' ); ?></h3>
					<address><?php echo wp_kses_post( $address ); ?></address>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * What happens next, a link to My orders, and for guests an offer to create an account.
	 *
	 * @param int $order_id Order.
	 */
	public static function thankyou_bottom( $order_id ) {
		$order = self::thankyou_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$account = AIMP_Settings::get( 'account_page' );
		$account = ( $account && 'publish' === get_post_status( $account ) ) ? get_permalink( $account ) : wc_get_page_permalink( 'myaccount' );
		?>
		<div class="aimp-thankyou-next">
			<h3><?php esc_html_e( 'What happens next?', 'atelier-irisee-master-plugin' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'You receive an order confirmation by email.', 'atelier-irisee-master-plugin' ); ?></li>
				<li><?php esc_html_e( 'We prepare your order with care.', 'atelier-irisee-master-plugin' ); ?></li>
				<li><?php esc_html_e( 'You get an email as soon as it is on its way.', 'atelier-irisee-master-plugin' ); ?></li>
			</ol>
			<?php $aimp_contact = AIMP_Trust::contact_page_text(); ?>
			<?php if ( $aimp_contact ) : ?>
				<p class="aimp-thankyou-contact"><?php echo esc_html( $aimp_contact ); ?></p>
			<?php endif; ?>
			<?php if ( is_user_logged_in() ) : ?>
				<a class="aimp-button" href="<?php echo esc_url( add_query_arg( 'tab', 'orders', $account ) ); ?>"><?php esc_html_e( 'My orders', 'atelier-irisee-master-plugin' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( ! is_user_logged_in() && $account ) : ?>
			<div class="aimp-thankyou-account">
				<h3><?php esc_html_e( 'Create an account', 'atelier-irisee-master-plugin' ); ?></h3>
				<p><?php esc_html_e( 'Follow your order and order faster next time: your details are filled in for you.', 'atelier-irisee-master-plugin' ); ?></p>
				<a class="aimp-button" href="<?php echo esc_url( add_query_arg( array( 'aimp_el' => 'register', 'aimp_email' => rawurlencode( $order->get_billing_email() ) ), $account ) ); ?>"><?php esc_html_e( 'Create an account', 'atelier-irisee-master-plugin' ); ?></a>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Fills an empty checkout field for a logged-in customer from their saved account: the field itself
	 * (when the session is older than the saved address), then, for billing fields, the saved shipping
	 * address, the account email and name. Fields WooCommerce already has a value for are left alone.
	 *
	 * @param mixed  $value Value from earlier filters (null = none).
	 * @param string $input Field name, e.g. billing_address_1.
	 * @return mixed
	 */
	public static function prefill( $value, $input ) {
		if ( null !== $value || ! is_user_logged_in() || ! function_exists( 'WC' ) || ( 0 !== strpos( (string) $input, 'billing_' ) && 0 !== strpos( (string) $input, 'shipping_' ) ) ) {
			return $value;
		}
		$get = function ( $customer, $key ) {
			$getter = 'get_' . $key;
			return ( $customer && is_callable( array( $customer, $getter ) ) ) ? (string) $customer->$getter() : '';
		};
		if ( '' !== $get( WC()->customer, $input ) ) {
			return $value; // WooCommerce fills it in itself.
		}

		static $account = null;
		if ( null === $account ) {
			$account = new WC_Customer( get_current_user_id() );
		}
		$found = $get( $account, $input );
		if ( '' === $found && 0 === strpos( $input, 'billing_' ) ) {
			$shipping_key = 'shipping_' . substr( $input, 8 );
			$found        = $get( WC()->customer, $shipping_key );
			$found        = '' !== $found ? $found : $get( $account, $shipping_key );
			if ( '' === $found ) {
				$user = wp_get_current_user();
				if ( 'billing_email' === $input ) {
					$found = (string) $user->user_email;
				} elseif ( 'billing_first_name' === $input ) {
					$found = (string) $user->first_name;
				} elseif ( 'billing_last_name' === $input ) {
					$found = (string) $user->last_name;
				}
			}
		}
		return '' !== $found ? $found : $value;
	}

	/** True while [atelier_irisee_checkout] prints WooCommerce's checkout. */
	private static $rendering = false;

	public static function codes_open() {
		if ( ! self::$rendering ) {
			return;
		}
		echo '<div class="aimp-checkout-codes"><h3>' . esc_html__( 'Discount code or gift card', 'atelier-irisee-master-plugin' ) . '</h3>';
		if ( wc_coupons_enabled() ) {
			echo '<div class="aimp-checkout-coupon" data-aimp-checkout-coupon>' .
				'<label class="screen-reader-text" for="aimp-checkout-coupon-code">' . esc_html__( 'Discount code', 'atelier-irisee-master-plugin' ) . '</label>' .
				'<input type="text" id="aimp-checkout-coupon-code" class="input-text" placeholder="' . esc_attr__( 'Discount code', 'atelier-irisee-master-plugin' ) . '" autocomplete="off">' .
				'<button type="button" class="button" data-aimp-apply-coupon>' . esc_html__( 'Apply', 'atelier-irisee-master-plugin' ) . '</button>' .
				'</div><div class="aimp-checkout-coupon-msg" aria-live="polite"></div>';
		}
	}

	public static function codes_close() {
		if ( self::$rendering ) {
			echo '</div>';
		}
	}

	public static function register_assets() {
		wp_register_style( 'aimp-checkout', AIMP_PLUGIN_URL . 'assets/css/checkout.css', array( 'aimp-configurator' ), AIMP_VERSION );
		wp_register_script( 'aimp-checkout', AIMP_PLUGIN_URL . 'assets/js/checkout.js', array( 'jquery' ), AIMP_VERSION, true );
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'aimp-checkout' );
		}
	}

	/**
	 * The steps: key => label.
	 *
	 * @return array
	 */
	public static function steps() {
		return array(
			'cart'         => __( 'Cart', 'atelier-irisee-master-plugin' ),
			'details'      => __( 'Details', 'atelier-irisee-master-plugin' ),
			'delivery'     => __( 'Overview', 'atelier-irisee-master-plugin' ),
			'payment'      => __( 'Payment', 'atelier-irisee-master-plugin' ),
			'confirmation' => __( 'Confirmation', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * Texts for checkout.js.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'next'          => __( 'Next', 'atelier-irisee-master-plugin' ),
			'back'          => __( 'Back', 'atelier-irisee-master-plugin' ),
			'toPay'         => __( 'To payment', 'atelier-irisee-master-plugin' ),
			'required'      => __( 'Please fill in the required fields.', 'atelier-irisee-master-plugin' ),
			'address'       => __( 'Delivery address', 'atelier-irisee-master-plugin' ),
			'change'        => __( 'Change', 'atelier-irisee-master-plugin' ),
			'addAddress2'   => __( '+ Add apartment, suite…', 'atelier-irisee-master-plugin' ),
			'addNote'       => __( '+ Add a note to your order', 'atelier-irisee-master-plugin' ),
			'fieldRequired' => __( 'This field is required.', 'atelier-irisee-master-plugin' ),
			'fieldEmail'    => __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ),
			'fieldPhone'    => __( 'Please enter a valid phone number.', 'atelier-irisee-master-plugin' ),
			'fieldInvalid'  => __( 'Please check this field.', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * @return string
	 */
	public static function shortcode() {
		if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Shortcode_Checkout' ) ) {
			return '';
		}
		if ( ! wp_style_is( 'aimp-checkout', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-checkout' );
		wp_enqueue_script( 'aimp-checkout' );
		wp_localize_script( 'aimp-checkout', 'aimpCheckout', self::strings() );

		$confirmation = is_wc_endpoint_url( 'order-received' );
		$current      = $confirmation ? 'confirmation' : 'details';
		// "Your order" next to the steps: sewing project kits as one product, like the cart.
		$summary = ! $confirmation && ! is_wc_endpoint_url( 'order-pay' ) && WC()->cart && ! WC()->cart->is_empty();
		if ( $summary ) {
			WC()->cart->calculate_totals(); // Up-to-date line prices (WooCommerce's checkout does the same).
		}
		$classes = 'aimp-configurator aimp-checkout' . ( $confirmation ? ' is-confirmation' : '' ) . ( $summary ? ' has-summary' : '' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-aimp-checkout data-step="<?php echo esc_attr( $current ); ?>">
			<ol class="aimp-steps aimp-checkout-steps">
				<?php
				$index = 0;
				foreach ( self::steps() as $key => $label ) :
					++$index;
					$state = '';
					if ( 'cart' === $key || ( $confirmation && 'confirmation' !== $key ) ) {
						$state = 'is-done';
					} elseif ( $key === $current ) {
						$state = 'is-current';
					}
					?>
					<li class="<?php echo esc_attr( $state ); ?>" data-step-key="<?php echo esc_attr( $key ); ?>"<?php echo $key === $current ? ' aria-current="step"' : ''; ?>>
						<?php if ( 'cart' === $key && ! $confirmation ) : ?>
							<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><span class="aimp-step-num"><?php echo (int) $index; ?></span> <?php echo esc_html( $label ); ?></a>
						<?php else : ?>
							<span class="aimp-checkout-step-label"><span class="aimp-step-num"><?php echo (int) $index; ?></span> <?php echo esc_html( $label ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( $summary ) : ?>
				<div class="aimp-checkout-layout">
					<details class="aimp-checkout-summary" open data-aimp-checkout-summary>
						<summary>
							<span class="aimp-checkout-summary-title"><?php esc_html_e( 'Your order', 'atelier-irisee-master-plugin' ); ?></span>
							<span class="aimp-checkout-summary-count">(<?php echo (int) AIMP_Cart_Page::summary_count(); ?>)</span>
							<span class="aimp-checkout-summary-total" data-aimp-summary-total>· <?php echo wp_kses_post( WC()->cart ? WC()->cart->get_total() : '' ); ?></span>
						</summary>
						<?php echo AIMP_Cart_Page::summary_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in summary_html(). ?>
					</details>
					<div class="aimp-checkout-main">
						<p class="aimp-checkout-message" role="alert" hidden></p>
						<?php
						self::$rendering = true;
						WC_Shortcode_Checkout::output( array() ); // Prints WooCommerce's checkout.
						self::$rendering = false;
						?>
					</div>
				</div>
			<?php else : ?>
				<p class="aimp-checkout-message" role="alert" hidden></p>
				<?php
				self::$rendering = true;
				WC_Shortcode_Checkout::output( array() ); // Prints WooCommerce's checkout (or the order confirmation).
				self::$rendering = false;
				?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
