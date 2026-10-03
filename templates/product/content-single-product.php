<?php
/**
 * Classic themes: WooCommerce's product page content, in the Atelier Irisee design.
 * The theme already prints the breadcrumb (woocommerce_before_main_content), so it is left out here.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( $product instanceof WC_Product ) {
	AIMP_Product_Page::render( $product, array( 'breadcrumb' => false ) );
}
