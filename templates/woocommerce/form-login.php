<?php
/**
 * My Account login/register page, replaced by the plugin's forms
 * (WooCommerce > Login & registration > "Use these forms on the My Account login page").
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_customer_login_form' );

echo AIMP_Login::container( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates.
	array(
		'active'         => 'login',
		'context'        => 'myaccount',
		'login_redirect' => wc_get_page_permalink( 'myaccount' ),
	)
);

do_action( 'woocommerce_after_customer_login_form' );
