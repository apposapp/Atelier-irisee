<?php
/**
 * "Atelier Irisee shipping": the shipping method behind the costs in WooCommerce → Atelier Irisee → Shipping.
 *
 * The plugin adds it to the "Locations not covered by your other zones" zone by itself, so WooCommerce
 * knows the shop ships. The cost comes from AIMP_Shipping (no settings of its own here).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AIMP_Shipping_Method' ) && class_exists( 'WC_Shipping_Method' ) ) {

	class AIMP_Shipping_Method extends WC_Shipping_Method {

		/**
		 * @param int $instance_id Instance ID.
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = AIMP_Shipping::RATE_ID;
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'Atelier Irisee shipping', 'atelier-irisee-master-plugin' );
			$this->method_description = __( 'The shipping costs set under WooCommerce → Atelier Irisee → Shipping.', 'atelier-irisee-master-plugin' );
			$this->title              = $this->method_title;
			$this->supports           = array( 'shipping-zones' );
			$this->enabled            = 'yes';
		}

		/**
		 * @param array $package Package.
		 */
		public function calculate_shipping( $package = array() ) {
			if ( ! AIMP_Shipping::enabled() ) {
				return;
			}
			$rate = AIMP_Shipping::build_rate( $package );
			$this->add_rate(
				array(
					'id'       => $rate->get_id(),
					'label'    => $rate->get_label(),
					'cost'     => $rate->get_cost(),
					'taxes'    => $rate->get_taxes(),
					'calc_tax' => 'per_order',
					'package'  => $package,
				)
			);
		}
	}
}
