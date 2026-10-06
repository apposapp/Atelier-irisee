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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
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
				'kit_discount'      => 10,
			'welcome_discount'  => 10,
			'welcome_days'      => 30,
			'shop_per_page'     => 12,
			'configurator_page' => 0,
			'product_design'    => 1,
			'page_all'          => 0,
			'page_patterns'     => 0,
			'page_fabrics'      => 0,
			'page_haberdashery' => 0,
			'category_redirect' => 1,
			'site_font'                 => 1,
			'font_regular'              => 0,
			'font_bold'                 => 0,
			'header_enabled'            => 0,
			'email_style'               => 1,
			'account_page'              => 0,
			'overview_img_fabrics'      => 0,
			'overview_img_patterns'     => 0,
			'overview_img_haberdashery' => 0,
			'overview_img_giftcards'    => 0,
			'overview_img_configurator' => 0,
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

		add_settings_field(
			'aimp_configurator_page',
			__( 'Shop and product pages', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_shop_fields' ),
			self::PAGE,
			'aimp_general',
			array( 'label_for' => 'aimp_configurator_page' )
		);

		add_settings_section(
			'aimp_discounts',
			__( 'Discounts', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'discounts_intro' ),
			self::PAGE
		);
		add_settings_field(
			'aimp_kit_discount',
			__( 'Sewing project kit discount', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_kit_discount_field' ),
			self::PAGE,
			'aimp_discounts',
			array( 'label_for' => 'aimp_kit_discount' )
		);
		add_settings_field(
			'aimp_welcome_discount',
			__( 'Welcome discount for new customers', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_welcome_fields' ),
			self::PAGE,
			'aimp_discounts',
			array( 'label_for' => 'aimp_welcome_discount' )
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

		add_settings_section(
			'aimp_shop_pages',
			__( 'Shop pages', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'shop_pages_intro' ),
			self::PAGE
		);

		foreach ( self::shop_page_fields() as $key => $label ) {
			add_settings_field(
				'aimp_' . $key,
				$label,
				array( __CLASS__, 'render_shop_page_field' ),
				self::PAGE,
				'aimp_shop_pages',
				array(
					'key'       => $key,
					'label_for' => 'aimp_' . $key,
				)
			);
		}

		add_settings_field(
			'aimp_cart_page',
			__( 'Cart page', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_cart_page_field' ),
			self::PAGE,
			'aimp_shop_pages',
			array( 'label_for' => 'aimp_cart_page' )
		);

		add_settings_field(
			'aimp_checkout_page',
			__( 'Checkout page', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_checkout_page_field' ),
			self::PAGE,
			'aimp_shop_pages',
			array( 'label_for' => 'aimp_checkout_page' )
		);

		add_settings_field(
			'aimp_category_redirect',
			__( 'Category links', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_category_redirect_field' ),
			self::PAGE,
			'aimp_shop_pages'
		);

		foreach ( self::overview_image_fields() as $key => $label ) {
			add_settings_field(
				'aimp_' . $key,
				$label,
				array( __CLASS__, 'render_media_field' ),
				self::PAGE,
				'aimp_shop_pages',
				array(
					'key'  => $key,
					'type' => 'image',
				)
			);
		}

		add_settings_section(
			'aimp_site',
			__( 'Site look', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'site_intro' ),
			self::PAGE
		);

		add_settings_field(
			'aimp_site_font',
			__( 'Font', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_font_fields' ),
			self::PAGE,
			'aimp_site'
		);

		add_settings_field(
			'aimp_header_enabled',
			__( 'Header', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_header_fields' ),
			self::PAGE,
			'aimp_site'
		);

		add_settings_field(
			'aimp_email_style',
			__( 'Emails', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'render_email_field' ),
			self::PAGE,
			'aimp_site'
		);
	}

	/**
	 * Pages with [atelier_irisee_shop]: key => label.
	 *
	 * @return array
	 */
	public static function shop_page_fields() {
		return array(
			'page_all'          => __( 'All products page', 'atelier-irisee-master-plugin' ),
			'page_patterns'     => __( 'Patterns page', 'atelier-irisee-master-plugin' ),
			'page_fabrics'      => __( 'Fabrics page', 'atelier-irisee-master-plugin' ),
			'page_haberdashery' => __( 'Haberdashery page', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function shop_pages_intro() {
		echo '<p>' . esc_html__( 'Choose the pages with the shop shortcode. Category links on the site then open these pages with that category already selected, instead of the standard WooCommerce category pages.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	/**
	 * Media library pickers on the settings page.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/css/admin.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-admin-settings', AIMP_PLUGIN_URL . 'assets/js/admin-settings.js', array( 'jquery' ), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-settings',
			'aimpSettings',
			array(
				'chooseImage' => __( 'Choose a picture', 'atelier-irisee-master-plugin' ),
				'chooseFont'  => __( 'Choose a font file', 'atelier-irisee-master-plugin' ),
				'use'         => __( 'Use this file', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	/**
	 * Pictures of the overview cards on the All products page: key => label.
	 *
	 * @return array
	 */
	public static function overview_image_fields() {
		return array(
			'overview_img_fabrics'      => __( 'Overview picture: Fabrics', 'atelier-irisee-master-plugin' ),
			'overview_img_patterns'     => __( 'Overview picture: Patterns', 'atelier-irisee-master-plugin' ),
			'overview_img_haberdashery' => __( 'Overview picture: Haberdashery', 'atelier-irisee-master-plugin' ),
			'overview_img_giftcards'    => __( 'Overview picture: Gift cards', 'atelier-irisee-master-plugin' ),
			'overview_img_configurator' => __( 'Overview picture: Sewing project kits', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * A media library picker: a picture (with preview) or a font file (with its name).
	 *
	 * @param array $args [ key, type: image|font ].
	 */
	public static function render_media_field( $args ) {
		$key   = $args['key'];
		$value = self::get( $key );
		$image = 'image' === $args['type'];
		$name  = $value ? basename( (string) get_attached_file( $value ) ) : '';
		printf(
			'<div class="aimp-media-field" data-type="%1$s"><input type="hidden" name="%2$s[%3$s]" value="%4$d">',
			esc_attr( $args['type'] ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			(int) $value
		);
		if ( $image ) {
			$url = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';
			printf( '<img class="aimp-media-preview" src="%1$s" alt="" width="80" height="80" style="object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px;%2$s">', esc_url( $url ), $url ? '' : 'display:none;' );
		} else {
			printf( '<code class="aimp-media-name" style="margin-right:8px;%2$s">%1$s</code>', esc_html( $name ), $name ? '' : 'display:none;' );
		}
		printf(
			'<button type="button" class="button aimp-media-choose">%1$s</button> <button type="button" class="button-link aimp-media-remove"%2$s>%3$s</button></div>',
			esc_html( $image ? __( 'Choose a picture', 'atelier-irisee-master-plugin' ) : __( 'Choose a font file', 'atelier-irisee-master-plugin' ) ),
			$value ? '' : ' style="display:none"',
			esc_html__( 'Remove', 'atelier-irisee-master-plugin' )
		);
	}

	public static function site_intro() {
		echo '<p>' . esc_html__( 'The font, text size and header of the whole website.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	public static function render_font_fields() {
		printf(
			'<p><label><input type="checkbox" name="%1$s[site_font]" value="1" %2$s> %3$s</label></p>',
			esc_attr( self::OPTION ),
			checked( 1, self::get( 'site_font' ), false ),
			esc_html__( 'Use Trajan Pro and 15px text on the whole site', 'atelier-irisee-master-plugin' )
		);
		echo '<p><strong>' . esc_html__( 'Font file regular (.ttf or .otf)', 'atelier-irisee-master-plugin' ) . '</strong></p>';
		self::render_media_field(
			array(
				'key'  => 'font_regular',
				'type' => 'font',
			)
		);
		echo '<p><strong>' . esc_html__( 'Font file bold (.ttf or .otf)', 'atelier-irisee-master-plugin' ) . '</strong></p>';
		self::render_media_field(
			array(
				'key'  => 'font_bold',
				'type' => 'font',
			)
		);
		echo '<p class="description">' . esc_html__( 'Upload the font files here; they stay on your own website. Check that your font licence allows use on a website.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	public static function render_email_field() {
		printf(
			'<label><input type="checkbox" name="%1$s[email_style]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( self::OPTION ),
			checked( 1, self::get( 'email_style' ), false ),
			esc_html__( 'Atelier Irisee style for WooCommerce emails', 'atelier-irisee-master-plugin' ),
			esc_html__( 'Order emails and the emails of this plugin get your gold and brown colours, your logo at the top and your company details at the bottom.', 'atelier-irisee-master-plugin' )
		);
	}

	public static function render_header_fields() {
		printf(
			'<p><label><input type="checkbox" name="%1$s[header_enabled]" value="1" %2$s> %3$s</label></p>',
			esc_attr( self::OPTION ),
			checked( 1, self::get( 'header_enabled' ), false ),
			esc_html__( 'Use the Atelier Irisee header', 'atelier-irisee-master-plugin' )
		);
		echo '<p class="description">' . esc_html__( 'Replaces the theme header on every page: your logo on the left, favourites, cart and account on the right, and a Menu button that opens the menu "Atelier Irisee side menu" (Appearance → Menus).', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<p><label for="aimp_account_page"><strong>' . esc_html__( 'Account page', 'atelier-irisee-master-plugin' ) . '</strong></label><br>';
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[account_page]',
				'id'                => 'aimp_account_page',
				'selected'          => self::get( 'account_page' ),
				'show_option_none'  => __( '— WooCommerce My account —', 'atelier-irisee-master-plugin' ),
				'option_none_value' => 0,
			)
		);
		echo '</p><p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'The page the account icon opens, for example the page with %s.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_account]</code>'
			)
		) . '</p>';
	}

	public static function render_shop_page_field( $args ) {
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[' . $args['key'] . ']',
				'id'                => 'aimp_' . $args['key'],
				'selected'          => self::get( $args['key'] ),
				'show_option_none'  => __( '— Select —', 'atelier-irisee-master-plugin' ),
				'option_none_value' => 0,
			)
		);
	}

	/**
	 * The cart page is WooCommerce's own setting; choosing it here changes it there too.
	 */
	public static function render_cart_page_field() {
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[cart_page]',
				'id'                => 'aimp_cart_page',
				'selected'          => absint( get_option( 'woocommerce_cart_page_id' ) ),
				'show_option_none'  => __( '— Select —', 'atelier-irisee-master-plugin' ),
				'option_none_value' => 0,
			)
		);
		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'The page with %s. It also becomes WooCommerce\'s cart page, so every cart link leads to it.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_cart]</code>'
			)
		) . '</p>';
	}

	/**
	 * The checkout page is WooCommerce's own setting; choosing it here changes it there too.
	 */
	public static function render_checkout_page_field() {
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[checkout_page]',
				'id'                => 'aimp_checkout_page',
				'selected'          => absint( get_option( 'woocommerce_checkout_page_id' ) ),
				'show_option_none'  => __( '— Select —', 'atelier-irisee-master-plugin' ),
				'option_none_value' => 0,
			)
		);
		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'The page with %s: the checkout in steps. It also becomes WooCommerce\'s checkout page.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_checkout]</code>'
			)
		) . '</p>';
	}

	public static function render_category_redirect_field() {
		printf(
			'<label><input type="checkbox" name="%1$s[category_redirect]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( self::OPTION ),
			checked( 1, self::get( 'category_redirect' ), false ),
			esc_html__( 'Send WooCommerce\'s shop and category pages to these pages', 'atelier-irisee-master-plugin' ),
			esc_html__( 'Patterns, fabrics and haberdashery categories go to their own page; other categories and the shop page go to the All products page. A category whose page is not set keeps the WooCommerce page.', 'atelier-irisee-master-plugin' )
		);
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
			'shop_per_page'   => array(
				'label'       => __( 'Products per page on shop pages', 'atelier-irisee-master-plugin' ),
				'default'     => 12,
				'description' => __( 'Number of products per page on the shop pages. 12 fills a 3x4 grid.', 'atelier-irisee-master-plugin' ),
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
		$sanitized['kit_discount']     = isset( $input['kit_discount'] ) ? min( 90, absint( $input['kit_discount'] ) ) : 10;
		$sanitized['welcome_discount'] = isset( $input['welcome_discount'] ) ? min( 90, absint( $input['welcome_discount'] ) ) : 10;
		$sanitized['welcome_days']     = isset( $input['welcome_days'] ) ? min( 365, absint( $input['welcome_days'] ) ) : 30;
		$sanitized['configurator_page'] = isset( $input['configurator_page'] ) ? absint( $input['configurator_page'] ) : 0;
		$sanitized['product_design']    = empty( $input['product_design'] ) ? 0 : 1;
		foreach ( array_keys( self::shop_page_fields() ) as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}
		$sanitized['category_redirect'] = empty( $input['category_redirect'] ) ? 0 : 1;
		// The cart page is stored as WooCommerce's own setting, not in ours.
		if ( isset( $input['cart_page'] ) && absint( $input['cart_page'] ) && 'page' === get_post_type( absint( $input['cart_page'] ) ) ) {
			update_option( 'woocommerce_cart_page_id', absint( $input['cart_page'] ) );
		}
		if ( isset( $input['checkout_page'] ) && absint( $input['checkout_page'] ) && 'page' === get_post_type( absint( $input['checkout_page'] ) ) ) {
			update_option( 'woocommerce_checkout_page_id', absint( $input['checkout_page'] ) );
		}
		foreach ( array_merge( array_keys( self::overview_image_fields() ), array( 'font_regular', 'font_bold', 'account_page' ) ) as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}
		$sanitized['site_font']      = empty( $input['site_font'] ) ? 0 : 1;
		$sanitized['header_enabled'] = empty( $input['header_enabled'] ) ? 0 : 1;
		$sanitized['email_style']    = empty( $input['email_style'] ) ? 0 : 1;

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

	public static function discounts_intro() {
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: link to WooCommerce's coupons screen */
				__( 'Make your own discount codes under %s. Codes can never be combined, and they do not apply to products that already have a sale price; sewing project kits do get them.', 'atelier-irisee-master-plugin' ),
				'<a href="' . esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ) . '">' . esc_html__( 'Marketing → Coupons', 'atelier-irisee-master-plugin' ) . '</a>'
			)
		) . '</p>';
	}

	public static function render_kit_discount_field() {
		printf(
			'<input type="number" min="0" max="90" step="1" id="aimp_kit_discount" name="%1$s[kit_discount]" value="%2$d" class="small-text"> %%<p class="description">%3$s</p>',
			esc_attr( self::OPTION ),
			(int) self::get( 'kit_discount' ),
			esc_html__( 'Every item of a sewing project kit made in the configurator gets this discount. 0 = no discount.', 'atelier-irisee-master-plugin' )
		);
	}

	public static function render_welcome_fields() {
		printf(
			'<input type="number" min="0" max="90" step="1" id="aimp_welcome_discount" name="%1$s[welcome_discount]" value="%2$d" class="small-text"> %% &nbsp; <label>%3$s <input type="number" min="0" max="365" step="1" name="%1$s[welcome_days]" value="%4$d" class="small-text"> %5$s</label><p class="description">%6$s</p>',
			esc_attr( self::OPTION ),
			(int) self::get( 'welcome_discount' ),
			esc_html__( 'valid for', 'atelier-irisee-master-plugin' ),
			(int) self::get( 'welcome_days' ),
			esc_html__( 'days', 'atelier-irisee-master-plugin' ),
			esc_html__( 'New customers get a personal code after their first login (a popup and in their account). It works once. 0 = off; 0 days = no end date.', 'atelier-irisee-master-plugin' )
		);
	}

	public static function render_account_fields() {
		printf(
			'<label>%1$s <input type="number" min="1" max="365" id="aimp_refund_days" name="%2$s[refund_days]" value="%3$d" class="small-text"> %4$s</label>',
			esc_html__( 'Customers can ask for a refund up to', 'atelier-irisee-master-plugin' ),
			esc_attr( self::OPTION ),
			(int) self::get( 'refund_days' ),
			esc_html__( 'days after their order', 'atelier-irisee-master-plugin' )
		);
		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %s: shortcode */
				__( 'Place the account page on any page with the shortcode %s.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_account]</code>'
			)
		) . '</p>';
	}

	public static function render_shop_fields() {
		echo '<label for="aimp_configurator_page">' . esc_html__( 'Configurator page', 'atelier-irisee-master-plugin' ) . '</label><br>';
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[configurator_page]',
				'id'                => 'aimp_configurator_page',
				'selected'          => self::get( 'configurator_page' ),
				'show_option_none'  => __( '— Select —', 'atelier-irisee-master-plugin' ),
				'option_none_value' => 0,
			)
		);
		echo '<p class="description">' . esc_html__( 'The page with the configurator. Pattern pages get a button that opens it with that pattern already chosen.', 'atelier-irisee-master-plugin' ) . '</p>';
		printf(
			'<p><label><input type="checkbox" name="%1$s[product_design]" value="1" %2$s> %3$s</label></p>',
			esc_attr( self::OPTION ),
			checked( 1, self::get( 'product_design' ), false ),
			esc_html__( 'Use the Atelier Irisee design on product pages', 'atelier-irisee-master-plugin' )
		);
		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: 1: shop shortcode, 2: product shortcode */
				__( 'Shop pages: %1$s with type="all", "patterns", "fabrics" or "haberdashery". One product anywhere: %2$s.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_shop type="fabrics"]</code>',
				'<code>[atelier_irisee_product id="123"]</code>'
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
		<div class="wrap aimp-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php // One form with a tab per section, so saving one tab never clears the settings of another. ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'aimp_settings_group' ); ?>
				<nav class="nav-tab-wrapper aimp-settings-tabs">
					<?php foreach ( self::tab_sections() as $id => $section ) : ?>
						<a href="#tab-<?php echo esc_attr( $id ); ?>" class="nav-tab" data-aimp-tab="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $section['title'] ); ?></a>
					<?php endforeach; ?>
				</nav>
				<?php foreach ( self::tab_sections() as $id => $section ) : ?>
					<div class="aimp-settings-panel" id="tab-<?php echo esc_attr( $id ); ?>" data-aimp-panel="<?php echo esc_attr( $id ); ?>">
						<?php
						if ( ! empty( $section['callback'] ) ) {
							call_user_func( $section['callback'], $section );
						}
						echo '<table class="form-table" role="presentation">';
						do_settings_fields( self::PAGE, $id );
						echo '</table>';
						?>
					</div>
				<?php endforeach; ?>
				<?php submit_button( __( 'Save settings', 'atelier-irisee-master-plugin' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * The settings sections in tab order: section ID => section (title, callback).
	 *
	 * @return array
	 */
	private static function tab_sections() {
		global $wp_settings_sections;
		$registered = isset( $wp_settings_sections[ self::PAGE ] ) ? (array) $wp_settings_sections[ self::PAGE ] : array();
		$order      = array( 'aimp_general', 'aimp_categories', 'aimp_shop_pages', 'aimp_discounts', 'aimp_shipping', 'aimp_site', 'aimp_footer', 'aimp_mosaic', 'aimp_admin_menu' );
		$sections   = array();
		foreach ( $order as $id ) {
			if ( isset( $registered[ $id ] ) ) {
				$sections[ $id ] = $registered[ $id ];
			}
		}
		// Sections added later (by other parts of the plugin) come last.
		return $sections + $registered;
	}
}
