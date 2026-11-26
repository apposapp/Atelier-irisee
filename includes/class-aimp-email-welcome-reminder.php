<?php
/**
 * "Your welcome code expires in a week": a WooCommerce email (WooCommerce → Settings → Emails) to a customer whose
 * welcome code is still unused a week before it expires (AIMP_Coupons::send_reminders), in their language (that of
 * their last order, else the shop's).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AIMP_Email_Welcome_Reminder' ) && class_exists( 'WC_Email' ) ) {

	class AIMP_Email_Welcome_Reminder extends WC_Email {

		/** @var WC_Coupon|null */
		private $coupon = null;

		/** @var WP_User|null */
		private $user = null;

		public function __construct() {
			$this->id             = 'aimp_welcome_reminder';
			$this->customer_email = true;
			$this->title          = __( 'Welcome code expires soon', 'atelier-irisee-master-plugin' );
			$this->description    = __( 'Sent to a customer a week before their welcome code expires, when they have not used it yet.', 'atelier-irisee-master-plugin' );
			$this->placeholders   = array(
				'{code}' => '',
			);
			parent::__construct();
		}

		public function get_default_subject() {
			return __( 'Your welcome code expires in a week', 'atelier-irisee-master-plugin' );
		}

		public function get_default_heading() {
			return __( 'Your welcome discount is still waiting', 'atelier-irisee-master-plugin' );
		}

		public function get_default_additional_content() {
			return '';
		}

		/**
		 * The customer's language: that of their last order, else the shop's.
		 *
		 * @param WP_User $user Customer.
		 * @return string
		 */
		private static function language( $user ) {
			$orders = function_exists( 'wc_get_orders' ) ? wc_get_orders(
				array(
					'customer_id' => $user->ID,
					'limit'       => 1,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			) : array();
			$lang   = $orders ? (string) $orders[0]->get_meta( '_aimp_lang' ) : '';
			return AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::default_language();
		}

		/**
		 * @param WP_User   $user   Customer.
		 * @param WC_Coupon $coupon Their welcome code.
		 * @return bool Sent.
		 */
		public function trigger( $user, $coupon ) {
			if ( ! $user instanceof WP_User || ! $coupon instanceof WC_Coupon ) {
				return false;
			}
			return (bool) AIMP_I18n::with_language(
				self::language( $user ),
				function () use ( $user, $coupon ) {
					$this->setup_locale();
					$this->user                   = $user;
					$this->coupon                 = $coupon;
					$this->recipient              = $user->user_email;
					$this->placeholders['{code}'] = strtoupper( $coupon->get_code() );
					$sent                         = false;
					if ( $this->is_enabled() && $this->get_recipient() ) {
						$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
					}
					$this->restore_locale();
					return $sent;
				}
			);
		}

		/**
		 * @return array [ first name, code, value, date, shop address ]
		 */
		private function parts() {
			$user    = $this->user;
			$coupon  = $this->coupon;
			$name    = $user ? (string) get_user_meta( $user->ID, 'billing_first_name', true ) : '';
			$name    = '' !== $name ? $name : ( $user ? (string) $user->first_name : '' );
			$name    = '' !== $name ? $name : ( $user ? (string) $user->display_name : '' );
			$expires = $coupon ? $coupon->get_date_expires() : null;
			return array(
				$name,
				$coupon ? strtoupper( $coupon->get_code() ) : '',
				$coupon ? AIMP_Coupons::value_text( $coupon ) : '',
				$expires ? AIMP_I18n::date( $expires->getTimestamp() ) : '',
				class_exists( 'AIMP_Search' ) ? AIMP_Search::results_url() : wc_get_page_permalink( 'shop' ),
			);
		}

		public function get_content_html() {
			list( $name, $code, $value, $date, $shop ) = $this->parts();
			ob_start();
			do_action( 'woocommerce_email_header', $this->get_heading(), $this );
			echo '<p>' . esc_html( sprintf( /* translators: %s: first name */ __( 'Hi %s,', 'atelier-irisee-master-plugin' ), $name ) ) . '</p>';
			/* translators: 1: discount, e.g. 10%, 2: date */
			echo '<p>' . esc_html( sprintf( __( 'Your welcome code for %1$s off is still waiting for you, but only until %2$s.', 'atelier-irisee-master-plugin' ), $value, $date ) ) . '</p>';
			echo '<p style="font-size:20px;font-weight:bold;letter-spacing:2px;text-align:center;padding:12px;border:1px dashed #b38f4f;">' . esc_html( $code ) . '</p>';
			echo '<p>' . esc_html__( 'Log in and it is added to your cart by itself, or type it in at checkout.', 'atelier-irisee-master-plugin' ) . '</p>';
			echo '<p style="text-align:center;"><a href="' . esc_url( $shop ) . '" style="display:inline-block;padding:12px 24px;background:#b38f4f;color:#ffffff;text-decoration:none;border-radius:4px;">' . esc_html__( 'Shop now', 'atelier-irisee-master-plugin' ) . '</a></p>';
			if ( $this->get_additional_content() ) {
				echo wp_kses_post( wpautop( wptexturize( $this->get_additional_content() ) ) );
			}
			do_action( 'woocommerce_email_footer', $this );
			return ob_get_clean();
		}

		public function get_content_plain() {
			list( $name, $code, $value, $date, $shop ) = $this->parts();
			return implode(
				"\n",
				array(
					sprintf( /* translators: %s: first name */ __( 'Hi %s,', 'atelier-irisee-master-plugin' ), $name ),
					'',
					/* translators: 1: discount, e.g. 10%, 2: date */
					sprintf( __( 'Your welcome code for %1$s off is still waiting for you, but only until %2$s.', 'atelier-irisee-master-plugin' ), $value, $date ),
					'',
					$code,
					'',
					__( 'Log in and it is added to your cart by itself, or type it in at checkout.', 'atelier-irisee-master-plugin' ),
					$shop,
				)
			) . "\n";
		}
	}
}
