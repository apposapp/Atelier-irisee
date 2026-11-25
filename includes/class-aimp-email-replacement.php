<?php
/**
 * "Replacement shipment": a WooCommerce email (WooCommerce → Settings → Emails) to the customer when a lost package is
 * sent again for free (AIMP_Lost_Orders), in the customer's language, with the products that are sent again.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AIMP_Email_Replacement' ) && class_exists( 'WC_Email' ) ) {

	class AIMP_Email_Replacement extends WC_Email {

		public function __construct() {
			$this->id             = 'aimp_replacement_order';
			$this->customer_email = true;
			$this->title          = __( 'Replacement shipment', 'atelier-irisee-master-plugin' );
			$this->description    = __( 'Sent to the customer when a lost package is sent again for free (order → "Package lost").', 'atelier-irisee-master-plugin' );
			$this->placeholders   = array(
				'{order_number}' => '',
				'{lost_number}'  => '',
			);
			parent::__construct();
		}

		public function get_default_subject() {
			return __( 'We are sending your order again', 'atelier-irisee-master-plugin' );
		}

		public function get_default_heading() {
			return __( 'Your package is on its way again', 'atelier-irisee-master-plugin' );
		}

		public function get_default_additional_content() {
			return '';
		}

		/**
		 * @param int $order_id The replacement order.
		 * @return bool Sent.
		 */
		public function trigger( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return false;
			}
			$lang = (string) $order->get_meta( '_aimp_lang' );
			$lang = AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::default_language();
			return (bool) AIMP_I18n::with_language(
				$lang,
				function () use ( $order ) {
					$this->setup_locale();
					$this->object                         = $order;
					$this->recipient                      = $order->get_billing_email();
					$this->placeholders['{order_number}'] = $order->get_order_number();
					$this->placeholders['{lost_number}']  = (string) $order->get_meta( '_aimp_replaces_number' );
					$sent = false;
					if ( $this->is_enabled() && $this->get_recipient() ) {
						$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
					}
					$this->restore_locale();
					return $sent;
				}
			);
		}

		/**
		 * The message: sorry, it is sent again for free, and what is in the package.
		 *
		 * @return string
		 */
		public function get_content_html() {
			$order = $this->object;
			ob_start();
			do_action( 'woocommerce_email_header', $this->get_heading(), $this );
			echo '<p>' . esc_html( sprintf( /* translators: %s: first name */ __( 'Hi %s,', 'atelier-irisee-master-plugin' ), $order->get_billing_first_name() ) ) . '</p>';
			echo '<p>' . esc_html( sprintf( /* translators: %s: order number */ __( 'Unfortunately the package of your order %s got lost on its way to you. We are very sorry about that.', 'atelier-irisee-master-plugin' ), (string) $order->get_meta( '_aimp_replaces_number' ) ) ) . '</p>';
			echo '<p>' . esc_html__( 'We are sending it again, at no cost for you. As soon as it leaves, you get the track & trace as usual.', 'atelier-irisee-master-plugin' ) . '</p>';
			echo '<h2>' . esc_html__( 'In the new package', 'atelier-irisee-master-plugin' ) . '</h2><ul>';
			foreach ( $order->get_items() as $item ) {
				$length = (string) $item->get_meta( 'aimp_length' );
				echo '<li>' . esc_html( wp_strip_all_tags( $item->get_name() ) . ( '' !== $length ? ' – ' . $length : ' × ' . $item->get_quantity() ) ) . '</li>';
			}
			echo '</ul>';
			$note = trim( (string) $order->get_meta( '_aimp_replacement_note_customer' ) );
			if ( '' !== $note ) {
				echo wp_kses_post( wpautop( $note ) );
			}
			if ( $this->get_additional_content() ) {
				echo wp_kses_post( wpautop( wptexturize( $this->get_additional_content() ) ) );
			}
			do_action( 'woocommerce_email_footer', $this );
			return ob_get_clean();
		}

		public function get_content_plain() {
			$order = $this->object;
			$lines = array(
				sprintf( /* translators: %s: first name */ __( 'Hi %s,', 'atelier-irisee-master-plugin' ), $order->get_billing_first_name() ),
				'',
				sprintf( /* translators: %s: order number */ __( 'Unfortunately the package of your order %s got lost on its way to you. We are very sorry about that.', 'atelier-irisee-master-plugin' ), (string) $order->get_meta( '_aimp_replaces_number' ) ),
				__( 'We are sending it again, at no cost for you. As soon as it leaves, you get the track & trace as usual.', 'atelier-irisee-master-plugin' ),
				'',
				__( 'In the new package', 'atelier-irisee-master-plugin' ) . ':',
			);
			foreach ( $order->get_items() as $item ) {
				$length  = (string) $item->get_meta( 'aimp_length' );
				$lines[] = '- ' . wp_strip_all_tags( $item->get_name() ) . ( '' !== $length ? ' – ' . $length : ' × ' . $item->get_quantity() );
			}
			return implode( "\n", $lines ) . "\n";
		}
	}
}
