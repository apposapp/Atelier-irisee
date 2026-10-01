<?php
/**
 * Bootstrap: loads the plugin classes once WooCommerce is available.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Plugin {

	public static function init() {
		// Translations come from AIMP_I18n (includes/languages/), not from .mo files.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_woocommerce_notice' ) );
			return;
		}

		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-settings.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-catalog.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-product-fields.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-cart.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-ajax.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-shortcode.php';

		AIMP_Settings::init();
		AIMP_Product_Fields::init();
		AIMP_Cart::init();
		AIMP_Ajax::init();
		AIMP_Shortcode::init();
	}

	public static function missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Atelier Irisee Master Plugin requires WooCommerce to be installed and active.', 'atelier-irisee-master-plugin' ) . '</p></div>';
	}
}
