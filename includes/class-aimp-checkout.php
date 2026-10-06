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
			'next'     => __( 'Next', 'atelier-irisee-master-plugin' ),
			'back'     => __( 'Back', 'atelier-irisee-master-plugin' ),
			'toPay'    => __( 'To payment', 'atelier-irisee-master-plugin' ),
			'required' => __( 'Please fill in the required fields.', 'atelier-irisee-master-plugin' ),
			'address'  => __( 'Delivery address', 'atelier-irisee-master-plugin' ),
			'change'   => __( 'Change', 'atelier-irisee-master-plugin' ),
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
				<?php WC_Shortcode_Checkout::output( array() ); // Prints WooCommerce's checkout (or the order confirmation). ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
