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
			'ribbon_cat'  => __( 'Ribbons category', 'atelier-irisee-master-plugin' ),
			'bias_cat'    => __( 'Bias tape category', 'atelier-irisee-master-plugin' ),
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
			'pattern_cat'     => 0,
			'fabric_cat'      => 0,
			'button_cat'      => 0,
			'zip_cat'         => 0,
			'ribbon_cat'      => 0,
			'bias_cat'        => 0,
			'per_page'        => 9,
			'fabric_per_page' => 16,
			'favorites_page'  => 0,
			'refund_days'     => 14,
			'account_delete'  => 1,
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
			'aimp_general',
			__( 'General', 'atelier-irisee-master-plugin' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'aimp_language',
			__( 'Plugin language', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_language_field' ),
			self::PAGE,
			'aimp_general',
			array( 'label_for' => 'aimp_language' )
		);

		add_settings_field(
			'aimp_favorites_page',
			__( 'Favorites page', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_favorites_page_field' ),
			self::PAGE,
			'aimp_general',
			array( 'label_for' => 'aimp_favorites_page' )
		);

		add_settings_field(
			'aimp_refund_days',
			__( 'Refund requests', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_account_fields' ),
			self::PAGE,
			'aimp_general',
			array( 'label_for' => 'aimp_refund_days' )
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

		foreach ( self::per_page_fields() as $key => $field ) {
			add_settings_field(
				'aimp_' . $key,
				$field['label'],
				array( __CLASS__, 'render_per_page_field' ),
				self::PAGE,
				'aimp_categories',
				array(
					'key'       => $key,
					'label_for' => 'aimp_' . $key,
				)
			);
		}
	}

	/**
	 * Page size settings: key => [ label, default, description ].
	 *
	 * @return array
	 */
	private static function per_page_fields() {
		return array(
			'per_page'        => array(
				'label'       => __( 'Items per page', 'atelier-irisee-master-plugin' ),
				'default'     => 9,
				'description' => __( 'Patterns and haberdashery per page. 9 fills a 3x3 grid.', 'atelier-irisee-master-plugin' ),
			),
			'fabric_per_page' => array(
				'label'       => __( 'Fabrics per page', 'atelier-irisee-master-plugin' ),
				'default'     => 16,
				'description' => __( 'Number of fabrics per page in the fabric step. 16 fills a 4x4 grid.', 'atelier-irisee-master-plugin' ),
			),
		);
	}

	public static function sanitize( $input ) {
		$input     = (array) $input;
		$sanitized = array();
		foreach ( array_keys( self::category_fields() ) as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}
		foreach ( self::per_page_fields() as $key => $field ) {
			$value             = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $field['default'];
			$sanitized[ $key ] = ( $value < 1 || $value > 60 ) ? $field['default'] : $value;
		}
		$sanitized['favorites_page'] = isset( $input['favorites_page'] ) ? absint( $input['favorites_page'] ) : 0;
		$sanitized['refund_days']    = isset( $input['refund_days'] ) ? min( 365, max( 1, absint( $input['refund_days'] ) ) ) : 14;
		$sanitized['account_delete'] = empty( $input['account_delete'] ) ? 0 : 1;

		$language             = isset( $input['language'] ) ? sanitize_key( $input['language'] ) : AIMP_I18n::DEFAULT_LANG;
		$sanitized['language'] = AIMP_I18n::is_valid( $language ) ? $language : AIMP_I18n::DEFAULT_LANG;
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

	public static function render_account_fields() {
		printf(
			'<label>%1$s <input type="number" min="1" max="365" id="aimp_refund_days" name="%2$s[refund_days]" value="%3$d" class="small-text"> %4$s</label><br><label><input type="checkbox" name="%2$s[account_delete]" value="1" %5$s> %6$s</label>',
			esc_html__( 'Customers can ask for a refund up to', 'atelier-irisee-master-plugin' ),
			esc_attr( self::OPTION ),
			(int) self::get( 'refund_days' ),
			esc_html__( 'days after their order', 'atelier-irisee-master-plugin' ),
			checked( 1, self::get( 'account_delete' ), false ),
			esc_html__( 'Customers can remove their own account on the account page', 'atelier-irisee-master-plugin' )
		);
		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'Place the account page on any page with the shortcode %s.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_account]</code>'
			)
		) . '</p>';
	}

	public static function render_favorites_page_field() {
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[favorites_page]',
				'id'                => 'aimp_favorites_page',
				'selected'          => self::get( 'favorites_page' ),
				'show_option_none'  => __( '— Select —', 'atelier-irisee-master-plugin' ),
				'option_none_value' => 0,
			)
		);
		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'The page with the shortcode %s. Customers get a link to it after adding a favorite.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_favorites]</code>'
			)
		) . '</p>';
	}

	public static function render_language_field() {
		$current = AIMP_I18n::default_language();
		echo '<select id="aimp_language" name="' . esc_attr( self::OPTION ) . '[language]">';
		foreach ( AIMP_I18n::languages() as $code => $language ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $code ), selected( $current, $code, false ), esc_html( $language['name'] ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Used for these settings, the product fields and as the starting language of the configurator. Customers can switch language at any time with the flags in the configurator.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	public static function render_per_page_field( $args ) {
		$key    = $args['key'];
		$fields = self::per_page_fields();
		printf(
			'<input type="number" min="1" max="60" id="%s" name="%s[%s]" value="%d" class="small-text"> <p class="description">%s</p>',
			esc_attr( 'aimp_' . $key ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			(int) self::get( $key ),
			esc_html( $fields[ $key ]['description'] )
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
				submit_button( __( 'Save settings', 'atelier-irisee-master-plugin' ) );
				?>
			</form>
		</div>
		<?php
	}
}
