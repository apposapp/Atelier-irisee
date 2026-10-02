<?php
/**
 * WooCommerce integration: checkout login link, My Account login page and Account details.
 * (The checkout *blocks* login link is handled in login.js, see openFromCheckoutBlocks.)
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login_WooCommerce {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'setup' ), 20 );
	}

	public static function setup() {
		if ( ! AIMP_Login::enabled() ) {
			return;
		}
		if ( AIMP_Login::opt( 'checkout_popup' ) ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
			add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'checkout_notice' ), 10 );
		}
		if ( AIMP_Login::opt( 'myaccount_replace' ) ) {
			add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate_template' ), 10, 2 );
		}
		if ( AIMP_Login::opt( 'profile_replace' ) ) {
			remove_action( 'woocommerce_account_edit-account_endpoint', 'woocommerce_account_edit_account' );
			add_action( 'woocommerce_account_edit-account_endpoint', array( __CLASS__, 'profile_endpoint' ) );
		}
	}

	/**
	 * Classic checkout: "Returning customer? Click here to log in" opens the popup.
	 */
	public static function checkout_notice() {
		if ( is_user_logged_in() || 'no' === get_option( 'woocommerce_enable_checkout_login_reminder' ) ) {
			return;
		}
		AIMP_Login::enqueue_assets();
		wc_print_notice(
			esc_html__( 'Returning customer?', 'atelier-irisee-master-plugin' ) .
			' <a href="#" class="aimp-login-tgr" data-aimp-redirect="' . esc_url( wc_get_checkout_url() ) . '">' . esc_html__( 'Click here to log in', 'atelier-irisee-master-plugin' ) . '</a>',
			'notice'
		);
	}

	/**
	 * Use the plugin's forms on the My Account login page.
	 *
	 * @param string $template      Located template.
	 * @param string $template_name Template name.
	 * @return string
	 */
	public static function locate_template( $template, $template_name ) {
		if ( 'myaccount/form-login.php' === $template_name ) {
			return AIMP_PLUGIN_DIR . 'templates/woocommerce/form-login.php';
		}
		return $template;
	}

	public static function profile_endpoint() {
		echo AIMP_Login::shortcode_profile(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
	}
}
