<?php
/**
 * Plugin Name:          Atelier Irisee Master Plugin
 * Plugin URI:           https://github.com/apposapp/Atelier-irisee
 * Description:          Pattern configurator for WooCommerce: pick a pattern and size, a matching fabric, buttons and zips, and add the whole set to the cart.
 * Version:              1.4.0
 * Requires at least:    6.3
 * Requires PHP:         7.4
 * Author:               Atelier Irisee
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          atelier-irisee-master-plugin
 * Domain Path:          /languages
 * Update URI:           https://github.com/apposapp/Atelier-irisee
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIMP_VERSION', '1.4.0' );
define( 'AIMP_PLUGIN_FILE', __FILE__ );
define( 'AIMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIMP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AIMP_GITHUB_REPO', 'apposapp/Atelier-irisee' );

// Declare compatibility with HPOS (custom order tables) and the cart/checkout blocks.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-i18n.php';
require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-updater.php';
require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-plugin.php';

// Dutch/English translations, loaded first so every string (including the plugin description) is translated.
AIMP_I18n::init();

// The updater runs even when WooCommerce is inactive, so fixes can always be delivered.
AIMP_Updater::init();

add_action( 'plugins_loaded', array( 'AIMP_Plugin', 'init' ) );
