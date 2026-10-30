<?php
/**
 * The "page not found" page (404): a short explanation, a product search field and links to the main shop pages,
 * instead of the theme's "No results found" text. Override in your theme: yourtheme/atelier-irisee/404.php
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Not_Found {

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
	}

	/**
	 * @param string $template Template file.
	 * @return string
	 */
	public static function template( $template ) {
		if ( ! is_404() || wp_is_block_theme() ) {
			return $template;
		}
		$own = locate_template( 'atelier-irisee/404.php' );
		return $own ? $own : AIMP_PLUGIN_DIR . 'templates/404.php';
	}

	public static function assets() {
		if ( ! is_404() ) {
			return;
		}
		// The buttons use the configurator's look.
		if ( ! wp_style_is( 'aimp-configurator', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
		}
		wp_enqueue_style( 'aimp-not-found', AIMP_PLUGIN_URL . 'assets/css/not-found.css', array( 'aimp-configurator' ), AIMP_VERSION );
	}

	/**
	 * The links under the search field: [ label, url ] of the pages that exist.
	 *
	 * @return array[]
	 */
	public static function links() {
		$links = array();
		$kits  = AIMP_Settings::get( 'configurator_page' );
		if ( $kits && 'publish' === get_post_status( $kits ) ) {
			$links[] = array( __( 'Sewing kits', 'atelier-irisee-master-plugin' ), get_permalink( $kits ) );
		}
		foreach (
			array(
				'fabrics'      => __( 'Fabrics', 'atelier-irisee-master-plugin' ),
				'patterns'     => __( 'Patterns', 'atelier-irisee-master-plugin' ),
				'haberdashery' => __( 'Haberdashery', 'atelier-irisee-master-plugin' ),
			) as $type => $label
		) {
			$page = AIMP_Shop::page_for_type( $type );
			if ( $page ) {
				$links[] = array( $label, get_permalink( $page ) );
			}
		}
		$contact = get_page_by_path( 'contact' );
		if ( $contact && 'publish' === $contact->post_status ) {
			$links[] = array( __( 'Contact', 'atelier-irisee-master-plugin' ), get_permalink( $contact ) );
		}
		return $links;
	}
}
