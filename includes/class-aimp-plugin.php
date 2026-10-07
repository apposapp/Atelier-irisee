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
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-fabric-fields.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-cart.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-ajax.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-shortcode.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-favorites.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-shop.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-product-page.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-editor.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-site.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-header.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-footer.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-cart-page.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-coupons.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-checkout.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-shipping.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-search.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-stock-alerts.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-saved-kits.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-orders.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-emails.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-tracking.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-admin-menu.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-newsletter.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-product-workspace.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-trust.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-mosaic.php';
		require_once AIMP_PLUGIN_DIR . 'includes/login/class-aimp-login.php';
		require_once AIMP_PLUGIN_DIR . 'includes/giftcards/class-aimp-giftcards.php';
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-account.php';

		AIMP_Settings::init();
		AIMP_Product_Fields::init();
		AIMP_Fabric_Fields::init();
		AIMP_Cart::init();
		AIMP_Ajax::init();
		AIMP_Shortcode::init();
		AIMP_Favorites::init();
		AIMP_Shop::init();
		AIMP_Product_Page::init();
		AIMP_Editor::init();
		AIMP_Site::init();
		AIMP_Header::init();
		AIMP_Footer::init();
		AIMP_Cart_Page::init();
		AIMP_Coupons::init();
		AIMP_Checkout::init();
		AIMP_Shipping::init();
		AIMP_Search::init();
		AIMP_Stock_Alerts::init();
		AIMP_Saved_Kits::init();
		AIMP_Orders::init();
		AIMP_Emails::init();
		AIMP_Tracking::init();
		AIMP_Admin_Menu::init();
		AIMP_Newsletter::init();
		AIMP_Product_Workspace::init();
		AIMP_Trust::init();
		AIMP_Mosaic::init();
		AIMP_Login::init();
		AIMP_Giftcards::init();
		AIMP_Account::init();
	}

	public static function missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Atelier Irisee Master Plugin requires WooCommerce to be installed and active.', 'atelier-irisee-master-plugin' ) . '</p></div>';
	}
}
