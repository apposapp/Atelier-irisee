<?php
/**
 * Shipping with PostNL for WooCommerce (the official "woo-postnl" plugin), managed from the Atelier Irisee
 * settings:
 *
 * - "Shipping and delivery" tab: the PostNL shipping cost and "free from" of every shipping zone (one zone
 *   per country), with "Add country".
 * - "PostNL" tab: PostNL's own settings form (API keys, sender, checkout, labels …) and "Fill in with PostNL",
 *   saved by PostNL's own code. PostNL's settings pages in WooCommerce open this tab.
 * - Once, after the update from 3.2: the old Atelier Irisee shipping costs become PostNL zones.
 * - Shop: "Free shipping from …" in the delivery line, and "… to go" with a bar under the PostNL rate.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_PostNL {

	/** PostNL's shipping method ID. */
	const METHOD = 'postnl';

	/** The shipping costs of plugin version 3.2 and older (read once for the move to PostNL). */
	const OLD_OPTION = 'aimp_shipping';

	const MIGRATED = 'aimp_postnl_migrated';

	/** Saved settings run only once per request (WordPress can call a sanitize callback twice). */
	private static $saved = array();

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_postnl_pages' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		// No shipping cost (and no calculator) until the customer's address is known.
		add_filter( 'pre_option_woocommerce_shipping_cost_requires_address', array( __CLASS__, 'requires_address' ) );
		// "Free shipping from €75: €12.50 to go." under the PostNL rate (cart and checkout totals).
		add_action( 'woocommerce_after_shipping_rate', array( __CLASS__, 'free_hint' ) );
		// My orders → order: the PostNL pickup point (the thank-you page shows it at the top instead).
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'pickup_block' ) );
	}

	/**
	 * PostNL for WooCommerce is installed and active.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( '\PostNLWooCommerce\Shipping_Method\PostNL' ) && defined( 'POSTNL_SETTINGS_ID' );
	}

	/**
	 * PostNL's shipping method object (its global settings), or null.
	 *
	 * @return WC_Shipping_Method|null
	 */
	private static function method() {
		if ( ! self::active() || ! function_exists( 'WC' ) ) {
			return null;
		}
		$methods = WC()->shipping()->get_shipping_methods();
		return isset( $methods[ self::METHOD ] ) ? $methods[ self::METHOD ] : null;
	}

	/**
	 * @param mixed $value Option value (false = not short-circuited).
	 * @return string
	 */
	public static function requires_address( $value ) {
		return 'yes';
	}

	/* ------------------------------------------------------------------
	 * Zones and amounts
	 * ------------------------------------------------------------------ */

	/**
	 * Settings of a PostNL instance in a zone.
	 *
	 * @param int $instance_id Instance.
	 * @return array
	 */
	private static function instance_settings( $instance_id ) {
		$settings = get_option( 'woocommerce_' . self::METHOD . '_' . (int) $instance_id . '_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * @param int   $instance_id Instance.
	 * @param array $changes     Settings to change.
	 */
	private static function update_instance( $instance_id, $changes ) {
		update_option( 'woocommerce_' . self::METHOD . '_' . (int) $instance_id . '_settings', array_merge( self::instance_settings( $instance_id ), $changes ), 'yes' );
	}

	/**
	 * What the customer pays per unit of shipping cost: 1.21 with 21% VAT when the shop's prices include VAT
	 * (amounts here are shown and typed as the customer pays them, like the product prices), otherwise 1.
	 *
	 * @param string $country Country for the VAT rate ('' = the shop's country).
	 * @return float
	 */
	private static function vat_factor( $country = '' ) {
		if ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) {
			return 1.0;
		}
		$rates = WC_Tax::find_shipping_rates(
			array(
				'country'   => '' !== $country ? $country : WC()->countries->get_base_country(),
				'state'     => '',
				'postcode'  => '',
				'city'      => '',
				'tax_class' => '',
			)
		);
		$percent = 0.0;
		foreach ( (array) $rates as $rate ) {
			$percent += isset( $rate['rate'] ) ? (float) $rate['rate'] : 0.0;
		}
		return 1 + $percent / 100;
	}

	/**
	 * The single country of a zone, or '' (several regions, or the "rest of the world" zone).
	 *
	 * @param WC_Shipping_Zone $zone Zone.
	 * @return string
	 */
	private static function zone_country( $zone ) {
		$locations = $zone->get_zone_locations();
		return ( 1 === count( $locations ) && 'country' === $locations[0]->type ) ? (string) $locations[0]->code : '';
	}

	/**
	 * Every zone with a PostNL method: [ zone, instance_id, country, cost (as the customer pays), free ].
	 *
	 * @return array
	 */
	private static function rates() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}
		$zones = array();
		foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $data ) {
			$zones[] = new WC_Shipping_Zone( $data['id'] );
		}
		$zones[] = new WC_Shipping_Zone( 0 );
		$rows    = array();
		foreach ( $zones as $zone ) {
			foreach ( $zone->get_shipping_methods() as $instance_id => $method ) {
				if ( self::METHOD !== $method->id ) {
					continue;
				}
				$settings = self::instance_settings( $instance_id );
				$country  = self::zone_country( $zone );
				$cost     = isset( $settings['cost'] ) ? (string) $settings['cost'] : '';
				$rows[]   = array(
					'zone'        => $zone,
					'instance_id' => (int) $instance_id,
					'country'     => $country,
					'cost'        => ( '' === $cost || ! is_numeric( $cost ) ) ? $cost : wc_format_decimal( (float) $cost * self::vat_factor( $country ), 2 ),
					'free'        => isset( $settings['minimum_for_free_shipping'] ) ? (string) $settings['minimum_for_free_shipping'] : '',
				);
			}
		}
		return $rows;
	}

	/**
	 * The zone for a country: the one that has only this country, or a new one.
	 *
	 * @param string $country Country code ('' = the "rest of the world" zone).
	 * @return WC_Shipping_Zone
	 */
	private static function zone_for( $country ) {
		if ( '' === $country ) {
			return new WC_Shipping_Zone( 0 );
		}
		foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $data ) {
			$zone = new WC_Shipping_Zone( $data['id'] );
			if ( self::zone_country( $zone ) === $country ) {
				return $zone;
			}
		}
		$countries = WC()->countries->get_countries();
		$zone      = new WC_Shipping_Zone();
		$zone->set_zone_name( isset( $countries[ $country ] ) ? html_entity_decode( $countries[ $country ], ENT_QUOTES, 'UTF-8' ) : $country );
		$zone->add_location( $country, 'country' );
		$zone->save();
		return $zone;
	}

	/**
	 * A PostNL method in the zone of a country with this cost (as the customer pays it) and "free from".
	 *
	 * @param string $country Country code ('' = rest of the world).
	 * @param string $cost    Cost ('' = 0).
	 * @param string $free    Free from ('' = never).
	 */
	private static function set_country_rate( $country, $cost, $free ) {
		$zone        = self::zone_for( $country );
		$instance_id = 0;
		foreach ( $zone->get_shipping_methods() as $id => $method ) {
			if ( self::METHOD === $method->id ) {
				$instance_id = (int) $id;
				break;
			}
		}
		if ( ! $instance_id ) {
			$instance_id = (int) $zone->add_shipping_method( self::METHOD );
		}
		if ( ! $instance_id ) {
			return;
		}
		$changes = array(
			'cost'                      => '' === $cost ? '0' : wc_format_decimal( (float) $cost / self::vat_factor( $country ), 4 ),
			'minimum_for_free_shipping' => $free,
		);
		if ( ! self::instance_settings( $instance_id ) ) {
			$changes += array(
				'title'      => 'PostNL',
				'tax_status' => 'taxable',
			);
		}
		self::update_instance( $instance_id, $changes );
	}

	/**
	 * "Free from" and cost of the PostNL rate for a country (from its zone).
	 *
	 * @param string $country Country code.
	 * @return array [ cost, free ] as strings ('' = not set).
	 */
	public static function amounts_for( $country ) {
		if ( ! self::active() || ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array( 'cost' => '', 'free' => '' );
		}
		$zone = WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'  => $country,
					'state'    => '',
					'postcode' => '',
				),
			)
		);
		foreach ( $zone->get_shipping_methods( true ) as $instance_id => $method ) {
			if ( self::METHOD === $method->id ) {
				$settings = self::instance_settings( $instance_id );
				$cost     = isset( $settings['cost'] ) ? (string) $settings['cost'] : '';
				return array(
					'cost' => ( '' === $cost || ! is_numeric( $cost ) ) ? $cost : wc_format_decimal( (float) $cost * self::vat_factor( $country ), 2 ),
					'free' => isset( $settings['minimum_for_free_shipping'] ) ? (string) $settings['minimum_for_free_shipping'] : '',
				);
			}
		}
		return array( 'cost' => '', 'free' => '' );
	}

	/* ------------------------------------------------------------------
	 * Moving the old Atelier Irisee shipping costs to PostNL (once)
	 * ------------------------------------------------------------------ */

	public static function maybe_migrate() {
		if ( get_option( self::MIGRATED ) || ! self::active() || ! class_exists( 'WC_Shipping_Zones' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// The old "Atelier Irisee shipping" method is no longer there: remove it from every zone.
		$zones = array( new WC_Shipping_Zone( 0 ) );
		foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $data ) {
			$zones[] = new WC_Shipping_Zone( $data['id'] );
		}
		foreach ( $zones as $zone ) {
			foreach ( $zone->get_shipping_methods() as $instance_id => $method ) {
				if ( 'aimp_shipping' === $method->id ) {
					$zone->delete_shipping_method( $instance_id );
				}
			}
		}

		$old = get_option( self::OLD_OPTION, array() );
		if ( is_array( $old ) && ! empty( $old['enabled'] ) ) {
			$amount = function ( $key ) use ( $old ) {
				return isset( $old[ $key ] ) ? (string) $old[ $key ] : '';
			};
			$base = WC()->countries->get_base_country();
			self::set_country_rate( $base, $amount( 'home_cost' ), $amount( 'home_free' ) );
			foreach ( isset( $old['countries'] ) ? (array) $old['countries'] : array() as $row ) {
				if ( ! empty( $row['country'] ) && $row['country'] !== $base && '' !== (string) $row['cost'] ) {
					self::set_country_rate( (string) $row['country'], (string) $row['cost'], (string) $row['free'] );
				}
			}
			$other = '' !== $amount( 'other_cost' );
			self::set_country_rate( '', $other ? $amount( 'other_cost' ) : $amount( 'home_cost' ), $other ? $amount( 'other_free' ) : $amount( 'home_free' ) );
		}
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		update_option( self::MIGRATED, AIMP_VERSION, false );
	}

	/* ------------------------------------------------------------------
	 * Settings: "Shipping and delivery" and "PostNL" tabs
	 * ------------------------------------------------------------------ */

	public static function register_settings() {
		register_setting(
			'aimp_settings_group',
			'aimp_postnl_rates',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'save_rates' ),
				'default'           => array(),
			)
		);
		register_setting(
			'aimp_settings_group',
			'aimp_postnl_proxy',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'save_postnl' ),
				'default'           => '',
			)
		);
		add_settings_section( 'aimp_shipping', __( 'Shipping and delivery', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'shipping_intro' ), AIMP_Settings::PAGE );
		if ( self::active() ) {
			add_settings_field( 'aimp_postnl_rates', __( 'Shipping costs (PostNL)', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_rates' ), AIMP_Settings::PAGE, 'aimp_shipping' );
			add_settings_section( 'aimp_postnl', __( 'PostNL', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_postnl' ), AIMP_Settings::PAGE );
		}
	}

	public static function shipping_intro() {
		if ( ! self::active() ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to add plugins */
					__( 'Shipping runs through the PostNL for WooCommerce plugin. Install and activate it under %s, then its settings and the shipping costs appear here.', 'atelier-irisee-master-plugin' ),
					'<a href="' . esc_url( admin_url( 'plugin-install.php?s=postnl+for+woocommerce&tab=search&type=term' ) ) . '">' . esc_html__( 'Plugins → Add New', 'atelier-irisee-master-plugin' ) . '</a>'
				)
			) . '</p></div>';
			return;
		}
		if ( 'disabled' === get_option( 'woocommerce_ship_to_countries' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to WooCommerce's general settings */
					__( 'Shipping is switched off in WooCommerce, so no shipping costs are shown. Choose a shipping location under %s ("Shipping location(s)").', 'atelier-irisee-master-plugin' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">' . esc_html__( 'WooCommerce → Settings → General', 'atelier-irisee-master-plugin' ) . '</a>'
				)
			) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Orders are shipped with PostNL. Every country has its own shipping zone with the PostNL method: its cost and the order amount from which shipping is free. The amounts are what the customer pays (VAT included when your prices include VAT). The PostNL settings themselves (API key, sender, pickup points, labels …) are on the PostNL tab.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	/**
	 * Cost and "free from" inputs.
	 *
	 * @param string $name Field name prefix.
	 * @param string $cost Cost.
	 * @param string $free Free from.
	 * @return string
	 */
	private static function amount_inputs( $name, $cost, $free ) {
		$currency = esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) );
		return sprintf(
			'<label class="aimp-ship-amount">%1$s <input type="number" min="0" step="0.01" class="small-text" name="%2$s[cost]" value="%3$s"> %4$s</label> ' .
			'<label class="aimp-ship-amount">%5$s <input type="number" min="0" step="0.01" class="small-text" name="%2$s[free]" value="%6$s"> %4$s</label>',
			esc_html__( 'Shipping cost', 'atelier-irisee-master-plugin' ),
			esc_attr( $name ),
			esc_attr( $cost ),
			$currency,
			esc_html__( 'Free from', 'atelier-irisee-master-plugin' ),
			esc_attr( $free )
		);
	}

	public static function render_rates() {
		echo '<table class="widefat striped aimp-postnl-rates"><thead><tr>';
		printf( '<th>%1$s</th><th>%2$s</th><th>%3$s</th>', esc_html__( 'Zone', 'atelier-irisee-master-plugin' ), esc_html__( 'Amounts', 'atelier-irisee-master-plugin' ), esc_html__( 'Remove', 'atelier-irisee-master-plugin' ) );
		echo '</tr></thead><tbody>';
		$rows = self::rates();
		if ( ! $rows ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No shipping zone has PostNL yet. Add your own country below.', 'atelier-irisee-master-plugin' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$zone  = $row['zone'];
			$name  = 'aimp_postnl_rates[existing][' . $row['instance_id'] . ']';
			$title = 0 === (int) $zone->get_id() ? __( 'All other countries', 'atelier-irisee-master-plugin' ) : $zone->get_zone_name();
			printf(
				'<tr><td><strong>%1$s</strong><br><span class="description">%2$s</span></td><td>%3$s</td><td><label><input type="checkbox" name="%4$s[remove]" value="1"> <span class="screen-reader-text">%5$s</span></label></td></tr>',
				esc_html( $title ),
				esc_html( 0 === (int) $zone->get_id() ? __( 'Countries without a zone of their own', 'atelier-irisee-master-plugin' ) : wp_strip_all_tags( $zone->get_formatted_location() ) ),
				self::amount_inputs( $name, $row['cost'], $row['free'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in amount_inputs().
				esc_attr( $name ),
				esc_html__( 'Remove', 'atelier-irisee-master-plugin' )
			);
		}
		echo '</tbody></table>';

		// New countries (the same add / remove rows as before, see assets/js/admin-settings.js).
		echo '<div class="aimp-ship-rows" data-aimp-ship-rows></div>';
		echo '<template data-aimp-ship-template>' . self::country_row() . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in country_row().
		echo '<p><button type="button" class="button" data-aimp-ship-add>' . esc_html__( 'Add country', 'atelier-irisee-master-plugin' ) . '</button> ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ) . '">' . esc_html__( 'All shipping zones in WooCommerce', 'atelier-irisee-master-plugin' ) . '</a></p>';
		echo '<input type="hidden" name="aimp_postnl_rates[present]" value="1">';
		echo '<p class="description">' . esc_html__( 'Empty "free from" = never free. Removing a country (✓ and save) sends its orders to "All other countries".', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	/**
	 * A new country row.
	 *
	 * @return string
	 */
	private static function country_row() {
		$options = '<option value="">' . esc_html__( '— Select —', 'atelier-irisee-master-plugin' ) . '</option>';
		foreach ( WC()->countries->get_shipping_countries() as $code => $label ) {
			$options .= sprintf( '<option value="%1$s">%2$s</option>', esc_attr( $code ), esc_html( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ) );
		}
		return sprintf(
			'<div class="aimp-ship-row"><select name="aimp_postnl_rates[new][__i__][country]">%1$s</select> %2$s <button type="button" class="button-link aimp-ship-remove" aria-label="%3$s">×</button></div>',
			$options, // Escaped above.
			self::amount_inputs( 'aimp_postnl_rates[new][__i__]', '', '' ),
			esc_attr__( 'Remove', 'atelier-irisee-master-plugin' )
		);
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
	 * Saves the zones table into the PostNL methods of the zones. Nothing is kept in an option of our own.
	 *
	 * @param mixed $input Posted table.
	 * @return array
	 */
	public static function save_rates( $input ) {
		if ( ! is_array( $input ) || empty( $input['present'] ) || isset( self::$saved['rates'] ) || ! self::active() || ! current_user_can( 'manage_woocommerce' ) ) {
			return array();
		}
		self::$saved['rates'] = true;

		$by_instance = array();
		foreach ( self::rates() as $row ) {
			$by_instance[ $row['instance_id'] ] = $row;
		}
		foreach ( isset( $input['existing'] ) ? (array) $input['existing'] : array() as $instance_id => $values ) {
			$instance_id = (int) $instance_id;
			if ( ! isset( $by_instance[ $instance_id ] ) ) {
				continue;
			}
			$zone = $by_instance[ $instance_id ]['zone'];
			if ( ! empty( $values['remove'] ) ) {
				$zone->delete_shipping_method( $instance_id );
				// A country zone without any method would mean "no shipping to this country".
				if ( (int) $zone->get_id() && ! $zone->get_shipping_methods() ) {
					$zone->delete();
				}
				continue;
			}
			$cost = self::clean_amount( isset( $values['cost'] ) ? $values['cost'] : '' );
			self::update_instance(
				$instance_id,
				array(
					'cost'                      => '' === $cost ? '0' : wc_format_decimal( (float) $cost / self::vat_factor( $by_instance[ $instance_id ]['country'] ), 4 ),
					'minimum_for_free_shipping' => self::clean_amount( isset( $values['free'] ) ? $values['free'] : '' ),
				)
			);
		}
		$valid = WC()->countries->get_countries();
		foreach ( isset( $input['new'] ) ? (array) $input['new'] : array() as $row ) {
			$code = isset( $row['country'] ) ? strtoupper( sanitize_key( $row['country'] ) ) : '';
			if ( '' === $code || ! isset( $valid[ $code ] ) ) {
				continue;
			}
			self::set_country_rate( $code, self::clean_amount( isset( $row['cost'] ) ? $row['cost'] : '' ), self::clean_amount( isset( $row['free'] ) ? $row['free'] : '' ) );
		}
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		return array();
	}

	/**
	 * "Fill in with PostNL": PostNL's settings fields for that section.
	 *
	 * @return array
	 */
	private static function fill_in_fields() {
		$fields = apply_filters( 'woocommerce_get_settings_shipping', array(), 'fill-in-with-postnl' );
		return is_array( $fields ) ? $fields : array();
	}

	private static function load_admin_settings() {
		if ( ! class_exists( 'WC_Admin_Settings' ) && function_exists( 'WC' ) ) {
			include_once WC()->plugin_path() . '/includes/admin/class-wc-admin-settings.php';
		}
		return class_exists( 'WC_Admin_Settings' );
	}

	/**
	 * The PostNL tab: PostNL's own settings form, and "Fill in with PostNL".
	 */
	public static function render_postnl() {
		$method = self::method();
		if ( ! $method ) {
			return;
		}
		echo '<p>' . esc_html__( 'These are the settings of the PostNL for WooCommerce plugin, saved by PostNL itself. Shipping costs per country are on the Shipping and delivery tab.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<div class="aimp-postnl-form">';
		echo '<table class="form-table">';
		$method->generate_settings_html( $method->get_form_fields(), true );
		echo '</table>';
		$fields = self::fill_in_fields();
		if ( $fields && self::load_admin_settings() ) {
			WC_Admin_Settings::output_fields( $fields );
		}
		echo '<input type="hidden" name="aimp_postnl_present" value="1">';
		echo '</div>';
	}

	/**
	 * Saves the PostNL tab with PostNL's own code (validation, API key check, merchant codes).
	 *
	 * @param mixed $value Unused.
	 * @return string
	 */
	public static function save_postnl( $value ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php checked the settings nonce.
		if ( empty( $_POST['aimp_postnl_present'] ) || isset( self::$saved['postnl'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return '';
		}
		self::$saved['postnl'] = true;
		$method                = self::method();
		if ( ! $method ) {
			return '';
		}
		self::load_admin_settings();
		$method->process_admin_options();
		$fields = self::fill_in_fields();
		if ( $fields && class_exists( 'WC_Admin_Settings' ) ) {
			WC_Admin_Settings::save_fields( $fields );
		}
		if ( class_exists( '\PostNLWooCommerce\Shipping_Method\Settings' ) ) {
			\PostNLWooCommerce\Shipping_Method\Settings::get_instance()->init_settings();
		}
		return '';
	}

	/**
	 * PostNL's settings pages in WooCommerce open the PostNL tab here.
	 */
	public static function redirect_postnl_pages() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which page is opened.
		if ( ! self::active() || ! is_admin() || wp_doing_ajax() || 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable
		if ( 'wc-settings' === $page && 'shipping' === $tab && in_array( $section, array( self::METHOD, 'fill-in-with-postnl' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . AIMP_Settings::PAGE . '#tab-aimp_postnl' ) );
			exit;
		}
	}

	/**
	 * PostNL's form needs WooCommerce's admin styles and scripts, and PostNL's own, on our settings page.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . AIMP_Settings::PAGE !== $hook_suffix || ! self::active() ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'jquery-tiptip' );
		if ( defined( 'POSTNL_WC_PLUGIN_DIR_URL' ) && defined( 'POSTNL_WC_VERSION' ) ) {
			wp_enqueue_style( 'postnl-admin-settings', POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/admin-settings.css', array(), POSTNL_WC_VERSION );
			wp_enqueue_script( 'postnl-admin-settings', POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-settings.js', array( 'jquery' ), POSTNL_WC_VERSION, true );
			if ( class_exists( '\PostNLWooCommerce\Admin\Api_Key_Check' ) ) {
				wp_localize_script(
					'postnl-admin-settings',
					'postnlApiKeyCheck',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'action'  => \PostNLWooCommerce\Admin\Api_Key_Check::AJAX_ACTION,
						'nonce'   => wp_create_nonce( \PostNLWooCommerce\Admin\Api_Key_Check::NONCE_ACTION ),
					)
				);
			}
			if ( function_exists( 'wp_enqueue_code_editor' ) ) {
				wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}
			wp_enqueue_script( 'postnl-admin-fill-in-with-postnl-settings', POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-fill-in-with-postnl-settings.js', array( 'jquery' ), POSTNL_WC_VERSION, true );
		}
		// Tooltips (?) of WooCommerce's settings fields.
		wp_add_inline_script( 'jquery-tiptip', 'jQuery(function($){$(".aimp-postnl-form .woocommerce-help-tip").tipTip({attribute:"data-tip",fadeIn:50,fadeOut:50,delay:200});});' );
	}

	/* ------------------------------------------------------------------
	 * Shop
	 * ------------------------------------------------------------------ */

	/**
	 * "Free shipping from €75: €12.50 to go." with a bar, under a PostNL rate in the cart and checkout totals.
	 *
	 * @param WC_Shipping_Rate $rate Shipping option.
	 */
	public static function free_hint( $rate ) {
		if ( ! $rate instanceof WC_Shipping_Rate || self::METHOD !== $rate->get_method_id() || ! WC()->cart || ! WC()->cart->needs_shipping() ) {
			return;
		}
		$settings = self::instance_settings( $rate->get_instance_id() );
		$free     = isset( $settings['minimum_for_free_shipping'] ) ? (string) $settings['minimum_for_free_shipping'] : '';
		if ( '' === $free || (float) $free <= 0 || (float) $rate->get_cost() <= 0 ) {
			return;
		}
		// PostNL makes shipping free when the subtotal (as shown in the cart) is above the amount.
		$missing = (float) $free - (float) WC()->cart->get_displayed_subtotal();
		if ( $missing <= 0 ) {
			return;
		}
		$percent = (int) max( 0, min( 100, round( 100 * ( 1 - $missing / (float) $free ) ) ) );
		$text    = sprintf(
			/* translators: 1: order amount for free shipping, 2: amount still to go */
			__( 'Free shipping from %1$s: %2$s to go.', 'atelier-irisee-master-plugin' ),
			wc_price( (float) $free ),
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
	 * Orders: pickup point and track & trace from PostNL
	 * ------------------------------------------------------------------ */

	/**
	 * PostNL's saved data of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private static function order_data( $order ) {
		$data = $order instanceof WC_Order ? $order->get_meta( '_postnl_order_metadata' ) : array();
		return is_array( $data ) ? $data : array();
	}

	/**
	 * The PostNL pickup point chosen at checkout, as address lines, or [].
	 *
	 * @param WC_Order $order Order.
	 * @return string[]
	 */
	public static function pickup_point( $order ) {
		$data  = self::order_data( $order );
		$front = isset( $data['frontend'] ) && is_array( $data['frontend'] ) ? $data['frontend'] : array();
		$get   = function ( $key ) use ( $front ) {
			return isset( $front[ 'dropoff_points_' . $key ] ) ? trim( (string) $front[ 'dropoff_points_' . $key ] ) : '';
		};
		if ( '' === $get( 'address_company' ) && '' === $get( 'address_address_1' ) ) {
			return array();
		}
		return array_values(
			array_filter(
				array(
					$get( 'address_company' ),
					trim( $get( 'address_address_1' ) . ' ' . $get( 'address_address_2' ) ),
					trim( $get( 'address_postcode' ) . ' ' . $get( 'address_city' ) ),
				)
			)
		);
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public static function pickup_block( $order ) {
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return;
		}
		$lines = self::pickup_point( $order );
		if ( ! $lines ) {
			return;
		}
		printf(
			'<section class="aimp-pickup-point"><h3>%1$s</h3><address>%2$s</address></section>',
			esc_html__( 'Pickup point', 'atelier-irisee-master-plugin' ),
			implode( '<br>', array_map( 'esc_html', $lines ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per line.
		);
	}

	/**
	 * PostNL's track & trace link once a label was made, or ''.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function tracking_url( $order ) {
		$data    = self::order_data( $order );
		$barcode = isset( $data['labels']['label']['barcode'] ) ? (string) $data['labels']['label']['barcode'] : '';
		if ( '' === $barcode || ! is_callable( array( '\PostNLWooCommerce\Utils', 'generate_tracking_url' ) ) ) {
			return '';
		}
		return (string) \PostNLWooCommerce\Utils::generate_tracking_url( $barcode, $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country(), $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode() );
	}
}
