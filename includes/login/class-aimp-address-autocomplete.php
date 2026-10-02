<?php
/**
 * Address autocomplete with Google Places API (New): suggestions while typing a street address in the
 * registration/profile forms (fields saved as billing/shipping address) and at checkout.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Address_Autocomplete {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 20 );
	}

	public static function enabled() {
		return AIMP_Login::opt( 'address_enabled' ) && '' !== trim( (string) AIMP_Login::opt( 'address_api_key' ) );
	}

	/**
	 * Whether any registration/profile field is saved as a street address.
	 *
	 * @return bool
	 */
	private static function has_address_field() {
		foreach ( AIMP_Login_Fields::custom() as $field ) {
			if ( in_array( $field['map'], array( 'billing_address_1', 'shipping_address_1' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Settings for address.js.
	 *
	 * @return array|null
	 */
	public static function script_config() {
		if ( ! self::enabled() ) {
			return null;
		}
		$countries = array_filter( array_map( 'trim', explode( ',', strtolower( (string) AIMP_Login::opt( 'address_countries' ) ) ) ) );
		return array(
			'key'       => trim( (string) AIMP_Login::opt( 'address_api_key' ) ),
			'countries' => array_values( array_slice( $countries, 0, 15 ) ),
			'checkout'  => (bool) AIMP_Login::opt( 'address_checkout' ),
			'noResults' => __( 'No addresses found.', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function enqueue() {
		if ( ! self::enabled() || wp_script_is( 'aimp-address', 'enqueued' ) ) {
			return;
		}
		// The suggestion list is styled in login.css.
		if ( ! wp_style_is( 'aimp-login', 'registered' ) ) {
			wp_register_style( 'aimp-login', AIMP_PLUGIN_URL . 'assets/css/login.css', array(), AIMP_VERSION );
		}
		wp_enqueue_style( 'aimp-login' );
		wp_enqueue_script( 'aimp-address', AIMP_PLUGIN_URL . 'assets/js/address.js', array(), AIMP_VERSION, true );
		wp_localize_script( 'aimp-address', 'aimpAddress', self::script_config() );
	}

	public static function maybe_enqueue() {
		if ( ! self::enabled() ) {
			return;
		}
		$checkout = AIMP_Login::opt( 'address_checkout' ) && function_exists( 'is_checkout' ) && is_checkout();
		$account  = function_exists( 'is_account_page' ) && is_account_page();
		// The registration popup is on every page for logged-out visitors.
		$popup = ! is_user_logged_in() && AIMP_Login::enabled() && self::has_address_field();
		if ( $checkout || $account || $popup ) {
			self::enqueue();
		}
	}
}
