<?php
/**
 * Settings page: WooCommerce > Atelier Irisee.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Settings {

	const OPTION = 'aimp_settings';
	const PAGE   = 'aimp-settings';

	/**
	 * Category settings: key => label.
	 *
	 * @return array
	 */
	private static function category_fields() {
		return array(
			'pattern_cat' => __( 'Patterns category', 'atelier-irisee-master-plugin' ),
			'fabric_cat'  => __( 'Fabrics category', 'atelier-irisee-master-plugin' ),
			'button_cat'  => __( 'Buttons category', 'atelier-irisee-master-plugin' ),
			'zip_cat'     => __( 'Zips category', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . AIMP_PLUGIN_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Get one setting value.
	 *
	 * @param string $key Setting key.
	 * @return int
	 */
	public static function get( $key ) {
		$defaults = array(
			'pattern_cat' => 0,
			'fabric_cat'  => 0,
			'button_cat'  => 0,
			'zip_cat'     => 0,
			'per_page'    => 9,
		);
		$options = wp_parse_args( (array) get_option( self::OPTION, array() ), $defaults );
		return isset( $options[ $key ] ) ? absint( $options[ $key ] ) : 0;
	}

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Atelier Irisee', 'atelier-irisee-master-plugin' ),
			__( 'Atelier Irisee', 'atelier-irisee-master-plugin' ),
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'atelier-irisee-master-plugin' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			'aimp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'aimp_categories',
			__( 'Product categories', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		foreach ( self::category_fields() as $key => $label ) {
			add_settings_field(
				'aimp_' . $key,
				$label,
				array( __CLASS__, 'render_category_field' ),
				self::PAGE,
				'aimp_categories',
				array(
					'key'       => $key,
					'label_for' => 'aimp_' . $key,
				)
			);
		}

		add_settings_field(
			'aimp_per_page',
			__( 'Items per page', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_per_page_field' ),
			self::PAGE,
			'aimp_categories',
			array( 'label_for' => 'aimp_per_page' )
		);
	}

	public static function sanitize( $input ) {
		$input     = (array) $input;
		$sanitized = array();
		foreach ( array_keys( self::category_fields() ) as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}
		$per_page              = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 9;
		$sanitized['per_page'] = ( $per_page < 1 || $per_page > 60 ) ? 9 : $per_page;
		return $sanitized;
	}

	public static function section_intro() {
		echo '<p>' . esc_html__( 'Choose which product categories hold your patterns and materials. The subcategories of the Fabrics category are the fabric categories you can allow per pattern size. The subcategories of the Patterns category become the filter tabs in the configurator.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'Place the configurator on any page with the shortcode %s.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_configurator]</code>'
			)
		) . '</p>';
	}

	public static function render_category_field( $args ) {
		$key = $args['key'];
		wp_dropdown_categories(
			array(
				'taxonomy'          => 'product_cat',
				'hide_empty'        => false,
				'hierarchical'      => true,
				'name'              => self::OPTION . '[' . $key . ']',
				'id'                => 'aimp_' . $key,
				'selected'          => self::get( $key ),
				'show_option_none'  => __( '— Select —', 'atelier-irisee-master-plugin' ),
				'option_none_value' => 0,
			)
		);
	}

	public static function render_per_page_field() {
		printf(
			'<input type="number" min="1" max="60" id="aimp_per_page" name="%s[per_page]" value="%d" class="small-text"> <p class="description">%s</p>',
			esc_attr( self::OPTION ),
			(int) self::get( 'per_page' ),
			esc_html__( 'Products per page in the configurator grids. 9 fills a 3x3 grid.', 'atelier-irisee-master-plugin' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'aimp_settings_group' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
