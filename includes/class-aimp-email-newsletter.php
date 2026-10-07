<?php
/**
 * "Newsletter": the WooCommerce email used to send a newsletter written under WooCommerce → Newsletter.
 * WooCommerce's header, footer and styles (the Atelier Irisee look), the content, an optional button and
 * attachment, and a personal unsubscribe link.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AIMP_Email_Newsletter' ) && class_exists( 'WC_Email' ) ) {

	class AIMP_Email_Newsletter extends WC_Email {

		/** @var array subject, content, button_text, button_url, attachment (path), unsubscribe (URL). */
		public $newsletter = array();

		public function __construct() {
			$this->id             = 'aimp_newsletter';
			$this->customer_email = true;
			$this->title          = __( 'Newsletter', 'atelier-irisee-master-plugin' );
			$this->description    = __( 'Used for the newsletters you write and send under WooCommerce → Newsletter. The subject and text come from the newsletter itself.', 'atelier-irisee-master-plugin' );
			$this->template_base  = AIMP_PLUGIN_DIR . 'templates/';
			$this->template_html  = 'emails/newsletter.php';
			$this->template_plain = 'emails/plain/newsletter.php';
			$this->placeholders   = array();
			parent::__construct();
		}

		public function get_default_subject() {
			return __( 'Newsletter', 'atelier-irisee-master-plugin' );
		}

		public function get_default_heading() {
			return '';
		}

		public function get_subject() {
			return isset( $this->newsletter['subject'] ) ? wp_strip_all_tags( $this->newsletter['subject'] ) : parent::get_subject();
		}

		public function get_heading() {
			return $this->get_subject();
		}

		public function get_attachments() {
			$file = isset( $this->newsletter['attachment'] ) ? (string) $this->newsletter['attachment'] : '';
			return ( '' !== $file && file_exists( $file ) ) ? array( $file ) : array();
		}

		public function get_headers() {
			$headers = parent::get_headers();
			if ( ! empty( $this->newsletter['unsubscribe'] ) ) {
				$headers .= 'List-Unsubscribe: <' . esc_url_raw( $this->newsletter['unsubscribe'] ) . ">\r\n";
			}
			return $headers;
		}

		/**
		 * @param string $to         Address.
		 * @param array  $newsletter Newsletter parts (see $newsletter).
		 * @return bool Sent.
		 */
		public function trigger( $to, $newsletter = array() ) {
			$this->setup_locale();
			$this->recipient  = $to;
			$this->newsletter = (array) $newsletter;
			$sent             = false;
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
			$this->restore_locale();
			return (bool) $sent;
		}

		private function args( $plain ) {
			return array(
				'email_heading' => $this->get_heading(),
				'newsletter'    => $this->newsletter,
				'sent_to_admin' => false,
				'plain_text'    => $plain,
				'email'         => $this,
			);
		}

		public function get_content_html() {
			return wc_get_template_html( $this->template_html, $this->args( false ), '', $this->template_base );
		}

		public function get_content_plain() {
			return wc_get_template_html( $this->template_plain, $this->args( true ), '', $this->template_base );
		}
	}
}
