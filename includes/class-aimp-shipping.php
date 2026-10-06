<?php
/**
 * Shipping costs from the settings (WooCommerce → Atelier Irisee → Shipping).
 *
 * One shipping option replaces WooCommerce's shipping options (local pickup is kept): a cost for the
 * shop's own country, a cost per listed country, and a cost for all other countries, each free from an
 * order amount. Amounts are what the customer pays (VAT included when the shop's prices include VAT).
 * No WooCommerce shipping zones are needed.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Shipping {

	const OPTION  = 'aimp_shipping';
	const RATE_ID = 'aimp_shipping';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'package_rates' ), 50, 2 );
		// No shipping row (and no shipping calculator) until the customer's address is known.
		add_filter( 'pre_option_woocommerce_shipping_cost_requires_address', array( __CLASS__, 'requires_address' ) );
		add_filter( 'pre_option_woocommerce_enable_shipping_calc', array( __CLASS__, 'no_calculator' ) );
		// A real shipping method, so WooCommerce knows the shop ships (it hides shipping without any method in a zone).
		add_filter( 'woocommerce_shipping_methods', array( __CLASS__, 'register_method' ) );
		add_action( 'woocommerce_shipping_init', array( __CLASS__, 'load_method' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync_zone' ), 20 );
		// "Free shipping from …: … to go." right under the shipping cost (cart and checkout totals).
		add_action( 'woocommerce_after_shipping_rate', array( __CLASS__, 'free_hint' ) );
	}

	/* ------------------------------------------------------------------
	 * Settings values
	 * ------------------------------------------------------------------ */

	/**
	 * @return array [ enabled, home_cost, home_free, other_cost, other_free, countries: [ [ country, cost, free ] ] ]
	 */
	public static function options() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'enabled'    => 0,
				'home_cost'  => '',
				'home_free'  => '',
				'other_cost' => '',
				'other_free' => '',
				'countries'  => array(),
			)
		);
	}

	public static function enabled() {
		return (bool) self::options()['enabled'];
	}

	/**
	 * Cost and "free from" for a delivery country: its own row, the shop's country, or all other countries.
	 *
	 * @param string $country Country code.
	 * @return array [ cost, free ] ('' = not set).
	 */
	public static function amounts_for( $country ) {
		$options = self::options();
		foreach ( (array) $options['countries'] as $row ) {
			if ( isset( $row['country'] ) && $row['country'] === $country && '' !== (string) $row['cost'] ) {
				return array(
					'cost' => (string) $row['cost'],
					'free' => (string) $row['free'],
				);
			}
		}
		$home = array(
			'cost' => (string) $options['home_cost'],
			'free' => (string) $options['home_free'],
		);
		if ( '' === $country || WC()->countries->get_base_country() === $country || '' === (string) $options['other_cost'] ) {
			return $home;
		}
		return array(
			'cost' => (string) $options['other_cost'],
			'free' => (string) $options['other_free'],
		);
	}

	/**
	 * What counts for "free from": the products after discounts, VAT included.
	 *
	 * @param array|null $package Shipping package (null = the whole cart).
	 * @return float
	 */
	private static function order_amount( $package = null ) {
		if ( $package && ! empty( $package['contents'] ) ) {
			$amount = 0;
			foreach ( $package['contents'] as $item ) {
				$amount += (float) $item['line_total'] + (float) $item['line_tax'];
			}
			return $amount;
		}
		return WC()->cart ? (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_cart_contents_tax() : 0;
	}

	/* ------------------------------------------------------------------
	 * Shipping options
	 * ------------------------------------------------------------------ */

	/**
	 * @param WC_Shipping_Rate[] $rates   Options found by WooCommerce.
	 * @param array              $package Package.
	 * @return WC_Shipping_Rate[]
	 */
	public static function package_rates( $rates, $package ) {
		if ( ! self::enabled() ) {
			return $rates;
		}
		// Local pickup set up in WooCommerce stays available.
		$kept = array();
		foreach ( (array) $rates as $id => $rate ) {
			if ( $rate instanceof WC_Shipping_Rate && in_array( $rate->get_method_id(), array( 'local_pickup', 'pickup_location' ), true ) ) {
				$kept[ $id ] = $rate;
			}
		}

		$rate = self::build_rate( $package );
		return array( $rate->get_id() => $rate ) + $kept;
	}

	/**
	 * The shipping option for a package: the cost for its country, or free from the amount.
	 *
	 * @param array $package Package.
	 * @return WC_Shipping_Rate
	 */
	public static function build_rate( $package ) {
		$country = isset( $package['destination']['country'] ) ? (string) $package['destination']['country'] : '';
		$amounts = self::amounts_for( $country );
		$cost    = '' === $amounts['cost'] ? 0.0 : (float) $amounts['cost'];
		$free    = $cost <= 0 || ( '' !== $amounts['free'] && self::order_amount( $package ) >= (float) $amounts['free'] );

		$net   = $free ? 0.0 : $cost;
		$taxes = array();
		if ( ! $free && wc_tax_enabled() ) {
			$tax_rates = WC_Tax::get_shipping_tax_rates();
			if ( wc_prices_include_tax() ) {
				// The customer pays exactly the amount from the settings.
				$taxes = WC_Tax::calc_inclusive_tax( $cost, $tax_rates );
				$net   = $cost - array_sum( $taxes );
			} else {
				$taxes = WC_Tax::calc_shipping_tax( $cost, $tax_rates );
			}
		}

		return new WC_Shipping_Rate(
			self::RATE_ID,
			$free ? __( 'Free shipping', 'atelier-irisee-master-plugin' ) : __( 'Shipping', 'atelier-irisee-master-plugin' ),
			$net,
			$taxes,
			self::RATE_ID
		);
	}

	/**
	 * @param mixed $value Option value (false = not short-circuited).
	 * @return mixed
	 */
	public static function requires_address( $value ) {
		return self::enabled() ? 'yes' : $value;
	}

	/**
	 * @param mixed $value Option value.
	 * @return mixed
	 */
	public static function no_calculator( $value ) {
		return self::enabled() ? 'no' : $value;
	}

	/* ------------------------------------------------------------------
	 * Shipping method in the "Locations not covered by your other zones" zone
	 * ------------------------------------------------------------------ */

	public static function load_method() {
		require_once AIMP_PLUGIN_DIR . 'includes/class-aimp-shipping-method.php';
	}

	/**
	 * @param array $methods Method ID => class.
	 * @return array
	 */
	public static function register_method( $methods ) {
		self::load_method();
		$methods[ self::RATE_ID ] = 'AIMP_Shipping_Method';
		return $methods;
	}

	/**
	 * On: one instance of the method in the "rest of the world" zone. Off: removed again.
	 */
	public static function sync_zone() {
		if ( ! class_exists( 'WC_Shipping_Zone' ) ) {
			return;
		}
		$zone  = new WC_Shipping_Zone( 0 );
		$found = array();
		foreach ( $zone->get_shipping_methods() as $instance_id => $method ) {
			if ( self::RATE_ID === $method->id ) {
				$found[] = (int) $instance_id;
			}
		}
		if ( self::enabled() && ! $found ) {
			$zone->add_shipping_method( self::RATE_ID );
		} elseif ( ! self::enabled() ) {
			foreach ( $found as $instance_id ) {
				$zone->delete_shipping_method( $instance_id );
			}
		}
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		update_option( 'aimp_shipping_zone_synced', AIMP_VERSION . ':' . ( self::enabled() ? 1 : 0 ), false );
	}

	/**
	 * Once after an update or a settings change (the settings are saved before this runs on the next page).
	 */
	public static function maybe_sync_zone() {
		if ( get_option( 'aimp_shipping_zone_synced' ) !== AIMP_VERSION . ':' . ( self::enabled() ? 1 : 0 ) ) {
			self::sync_zone();
		}
	}

	/**
	 * "Free shipping from €75: €12.50 to go." under our shipping option in the cart and checkout totals.
	 *
	 * @param WC_Shipping_Rate $method Shipping option.
	 */
	public static function free_hint( $method ) {
		if ( ! $method instanceof WC_Shipping_Rate || self::RATE_ID !== $method->get_method_id() ) {
			return;
		}
		if ( ! self::enabled() || ! WC()->cart || ! WC()->cart->needs_shipping() || ! WC()->cart->show_shipping() ) {
			return;
		}
		$country = WC()->customer ? (string) WC()->customer->get_shipping_country() : '';
		$amounts = self::amounts_for( '' !== $country ? $country : WC()->countries->get_base_country() );
		if ( '' === $amounts['free'] || '' === $amounts['cost'] || (float) $amounts['cost'] <= 0 ) {
			return;
		}
		$missing = (float) $amounts['free'] - self::order_amount();
		if ( $missing <= 0 ) {
			return;
		}
		$percent = (int) max( 0, min( 100, round( 100 * ( 1 - $missing / (float) $amounts['free'] ) ) ) );
		$text    = sprintf(
			/* translators: 1: order amount for free shipping, 2: amount still to go */
			__( 'Free shipping from %1$s: %2$s to go.', 'atelier-irisee-master-plugin' ),
			wc_price( (float) $amounts['free'] ),
			wc_price( $missing )
		);
		printf(
			'<span class="aimp-free-shipping-hint">%1$s<span class="aimp-free-shipping-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%2$d" aria-label="%3$s"><span style="width:%2$d%%"></span></span></span>',
			wp_kses_post( $text ),
			$percent,
			esc_attr( wp_strip_all_tags( $text ) )
		);
	}

	/* ------------------------------------------------------------------
	 * Settings (WooCommerce → Atelier Irisee → Shipping)
	 * ------------------------------------------------------------------ */

	public static function register_settings() {
		register_setting(
			'aimp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
		add_settings_section( 'aimp_shipping', __( 'Shipping', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'section_intro' ), AIMP_Settings::PAGE );
		add_settings_field( 'aimp_shipping_enabled', __( 'Shipping costs', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_enabled' ), AIMP_Settings::PAGE, 'aimp_shipping' );
		add_settings_field(
			'aimp_shipping_home',
			/* translators: %s: country name */
			sprintf( __( 'Your country (%s)', 'atelier-irisee-master-plugin' ), self::country_name( WC()->countries->get_base_country() ) ),
			array( __CLASS__, 'render_amounts' ),
			AIMP_Settings::PAGE,
			'aimp_shipping',
			array( 'prefix' => 'home' )
		);
		add_settings_field( 'aimp_shipping_countries', __( 'Other countries', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_countries' ), AIMP_Settings::PAGE, 'aimp_shipping' );
		add_settings_field( 'aimp_shipping_other', __( 'All other countries', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_amounts' ), AIMP_Settings::PAGE, 'aimp_shipping', array( 'prefix' => 'other' ) );
	}

	private static function country_name( $code ) {
		$countries = WC()->countries->get_countries();
		return isset( $countries[ $code ] ) ? html_entity_decode( $countries[ $code ], ENT_QUOTES, 'UTF-8' ) : $code;
	}

	public static function section_intro() {
		if ( 'disabled' === get_option( 'woocommerce_ship_to_countries' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to WooCommerce's general settings */
					__( 'Shipping is switched off in WooCommerce, so no shipping costs are shown. Choose a shipping location under %s ("Shipping location(s)").', 'atelier-irisee-master-plugin' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">' . esc_html__( 'WooCommerce → Settings → General', 'atelier-irisee-master-plugin' ) . '</a>'
				)
			) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'One shipping cost for every order, depending on the delivery country, and free from an order amount. The amounts are what the customer pays (VAT included when your prices include VAT). "Free from" counts the products in the cart after discounts; leave it empty for never free. Local pickup set up in WooCommerce stays available; other WooCommerce shipping methods are not used while this is on.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	public static function render_enabled() {
		printf(
			'<label><input type="checkbox" name="%1$s[enabled]" value="1" %2$s> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( self::enabled(), true, false ),
			esc_html__( 'Use the Atelier Irisee shipping costs', 'atelier-irisee-master-plugin' )
		);
	}

	/**
	 * A cost and "free from" pair, as plain number fields.
	 *
	 * @param string $name_cost Field name of the cost.
	 * @param string $name_free Field name of "free from".
	 * @param string $cost      Cost.
	 * @param string $free      Free from.
	 * @return string
	 */
	private static function amount_inputs( $name_cost, $name_free, $cost, $free ) {
		$currency = get_woocommerce_currency_symbol();
		return sprintf(
			'<label class="aimp-ship-amount">%1$s <input type="number" min="0" step="0.01" class="small-text" name="%2$s" value="%3$s"> %4$s</label> ' .
			'<label class="aimp-ship-amount">%5$s <input type="number" min="0" step="0.01" class="small-text" name="%6$s" value="%7$s"> %4$s</label>',
			esc_html__( 'Shipping cost', 'atelier-irisee-master-plugin' ),
			esc_attr( $name_cost ),
			esc_attr( $cost ),
			esc_html( html_entity_decode( $currency, ENT_QUOTES, 'UTF-8' ) ),
			esc_html__( 'Free from', 'atelier-irisee-master-plugin' ),
			esc_attr( $name_free ),
			esc_attr( $free )
		);
	}

	/**
	 * @param array $args [ prefix: home|other ].
	 */
	public static function render_amounts( $args ) {
		$options = self::options();
		$prefix  = $args['prefix'];
		echo self::amount_inputs( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in amount_inputs().
			self::OPTION . '[' . $prefix . '_cost]',
			self::OPTION . '[' . $prefix . '_free]',
			(string) $options[ $prefix . '_cost' ],
			(string) $options[ $prefix . '_free' ]
		);
		if ( 'other' === $prefix ) {
			echo '<p class="description">' . esc_html__( 'Countries not in the list above. Leave the cost empty to use the cost of your country.', 'atelier-irisee-master-plugin' ) . '</p>';
		}
	}

	/**
	 * One row of the countries table.
	 *
	 * @param string $index Row index (or the placeholder __i__ for new rows).
	 * @param array  $row   [ country, cost, free ].
	 * @return string
	 */
	private static function country_row( $index, $row ) {
		$name    = self::OPTION . '[countries][' . $index . ']';
		$options = '<option value="">' . esc_html__( '— Select —', 'atelier-irisee-master-plugin' ) . '</option>';
		foreach ( WC()->countries->get_shipping_countries() as $code => $label ) {
			if ( WC()->countries->get_base_country() === $code ) {
				continue;
			}
			$options .= sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $code ), selected( $row['country'], $code, false ), esc_html( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ) );
		}
		return sprintf(
			'<div class="aimp-ship-row"><select name="%1$s[country]">%2$s</select> %3$s <button type="button" class="button-link aimp-ship-remove" aria-label="%4$s">×</button></div>',
			esc_attr( $name ),
			$options, // Escaped above.
			self::amount_inputs( $name . '[cost]', $name . '[free]', (string) $row['cost'], (string) $row['free'] ),
			esc_attr__( 'Remove', 'atelier-irisee-master-plugin' )
		);
	}

	public static function render_countries() {
		$empty = array(
			'country' => '',
			'cost'    => '',
			'free'    => '',
		);
		echo '<div class="aimp-ship-rows" data-aimp-ship-rows>';
		foreach ( array_values( (array) self::options()['countries'] ) as $i => $row ) {
			echo self::country_row( (string) $i, wp_parse_args( (array) $row, $empty ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in country_row().
		}
		echo '</div>';
		echo '<template data-aimp-ship-template>' . self::country_row( '__i__', $empty ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in country_row().
		echo '<p><button type="button" class="button" data-aimp-ship-add>' . esc_html__( 'Add country', 'atelier-irisee-master-plugin' ) . '</button></p>';
	}

	/**
	 * @param mixed $amount Raw amount.
	 * @return string '' or a decimal.
	 */
	private static function clean_amount( $amount ) {
		$amount = is_scalar( $amount ) ? trim( (string) $amount ) : '';
		return '' === $amount ? '' : wc_format_decimal( max( 0, (float) str_replace( ',', '.', $amount ) ), 2 );
	}

	/**
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = (array) $input;
		$clean = array( 'enabled' => empty( $input['enabled'] ) ? 0 : 1 );
		foreach ( array( 'home_cost', 'home_free', 'other_cost', 'other_free' ) as $key ) {
			$clean[ $key ] = self::clean_amount( isset( $input[ $key ] ) ? $input[ $key ] : '' );
		}
		$valid              = WC()->countries->get_countries();
		$clean['countries'] = array();
		$seen               = array();
		foreach ( (array) ( isset( $input['countries'] ) ? $input['countries'] : array() ) as $row ) {
			$code = isset( $row['country'] ) ? strtoupper( sanitize_key( $row['country'] ) ) : '';
			if ( '' === $code || ! isset( $valid[ $code ] ) || isset( $seen[ $code ] ) ) {
				continue;
			}
			$seen[ $code ]        = true;
			$clean['countries'][] = array(
				'country' => $code,
				'cost'    => self::clean_amount( isset( $row['cost'] ) ? $row['cost'] : '' ),
				'free'    => self::clean_amount( isset( $row['free'] ) ? $row['free'] : '' ),
			);
		}
		// Shipping options stored in customers' sessions are calculated again.
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
		return $clean;
	}
}
