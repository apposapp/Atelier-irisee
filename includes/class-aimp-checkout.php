<?php
/**
 * Step-by-step checkout: [atelier_irisee_checkout].
 *
 * Cart → Details → Delivery → Payment → Confirmation, with a step bar like the configurator. It prints
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
			'delivery'     => __( 'Delivery', 'atelier-irisee-master-plugin' ),
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

		ob_start();
		?>
		<div class="aimp-configurator aimp-checkout<?php echo $confirmation ? ' is-confirmation' : ''; ?>" data-aimp-checkout data-step="<?php echo esc_attr( $current ); ?>">
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
			<p class="aimp-checkout-message" role="alert" hidden></p>
			<?php WC_Shortcode_Checkout::output( array() ); // Prints WooCommerce's checkout (or the order confirmation). ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
