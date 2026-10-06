<?php
/**
 * "Newsletter confirmation": a WooCommerce email (WooCommerce → Settings → Emails) with the link a new
 * subscriber clicks to confirm their subscription.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AIMP_Email_Newsletter_Confirm' ) && class_exists( 'WC_Email' ) ) {

	class AIMP_Email_Newsletter_Confirm extends WC_Email {

		/** @var string Confirmation link. */
		public $confirm_url = '';

		public function __construct() {
			$this->id             = 'aimp_newsletter_confirm';
			$this->customer_email = true;
			$this->title          = __( 'Newsletter confirmation', 'atelier-irisee-master-plugin' );
			$this->description    = __( 'Sent to new newsletter subscribers with a link to confirm their subscription. Only confirmed subscribers are in the newsletter list.', 'atelier-irisee-master-plugin' );
			$this->template_base  = AIMP_PLUGIN_DIR . 'templates/';
			$this->template_html  = 'emails/newsletter-confirm.php';
			$this->template_plain = 'emails/plain/newsletter-confirm.php';
			$this->placeholders   = array();
			parent::__construct();
		}

		public function get_default_subject() {
			return __( 'Confirm your subscription to the {site_title} newsletter', 'atelier-irisee-master-plugin' );
		}

		public function get_default_heading() {
			return __( 'Confirm your subscription', 'atelier-irisee-master-plugin' );
		}

		public function get_default_additional_content() {
			return __( 'Did you not sign up? Then simply ignore this email.', 'atelier-irisee-master-plugin' );
		}

		/**
		 * @param string $email       Address.
		 * @param string $confirm_url Confirmation link.
		 * @return bool Sent.
		 */
		public function trigger( $email, $confirm_url = '' ) {
			$this->setup_locale();
			$this->recipient   = $email;
			$this->confirm_url = $confirm_url;
			$sent              = false;
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
			$this->restore_locale();
			return (bool) $sent;
		}

		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'email_heading'      => $this->get_heading(),
					'confirm_url'        => $this->confirm_url,
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'email_heading'      => $this->get_heading(),
					'confirm_url'        => $this->confirm_url,
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}
	}
}
