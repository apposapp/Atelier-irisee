<?php
/**
 * [atelier_irisee_configurator] shortcode and its assets.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Shortcode {

	const TAG = 'atelier_irisee_configurator';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_script( 'aimp-ui', AIMP_PLUGIN_URL . 'assets/js/ui.js', array(), AIMP_VERSION, true );
		wp_register_style( 'aimp-configurator', AIMP_PLUGIN_URL . 'assets/css/configurator.css', array(), AIMP_VERSION );
		wp_register_script( 'aimp-configurator', AIMP_PLUGIN_URL . 'assets/js/configurator.js', array( 'aimp-ui', 'aimp-favorites' ), AIMP_VERSION, true );

		// Load the stylesheet in <head> when we can already tell the page uses the shortcode.
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'aimp-configurator' );
			wp_enqueue_style( 'aimp-shop' ); // Registered a little later by AIMP_Shop; printed in the head.
		}
	}

	private static function script_data() {
		return array(
			'endpoint'   => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'nonce'      => wp_create_nonce( AIMP_Ajax::NONCE ),
			'cartUrl'    => wc_get_cart_url(),
			'categories' => AIMP_Catalog::get_pattern_categories(),
			'currency'   => array(
				'symbol'    => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'position'  => get_option( 'woocommerce_currency_pos', 'left' ),
				'decimals'  => wc_get_price_decimals(),
				'decimal'   => wc_get_price_decimal_separator(),
				'thousand'  => wc_get_price_thousand_separator(),
			),
			'measureImage'    => AIMP_PLUGIN_URL . 'assets/images/lichaamsmaten.png',
			'kitDiscount'     => AIMP_Cart::kit_discount(),
			'loggedIn'        => is_user_logged_in(),
			'defaultLanguage' => AIMP_I18n::default_language(),
			'languages'       => self::languages_data(),
			'i18n'            => self::all_strings(),
		);
	}

	/**
	 * Supported languages for the flag switcher.
	 *
	 * @return array[]
	 */
	public static function languages_data() {
		$list = array();
		foreach ( AIMP_I18n::languages() as $code => $language ) {
			$list[] = array(
				'code'    => $code,
				'name'    => $language['name'],
				'locale'  => $language['locale'],
				'flag'    => $language['flag'],
				'decimal' => $language['decimal'],
			);
		}
		return $list;
	}

	/**
	 * Configurator strings in every supported language, so the customer can switch without reloading.
	 *
	 * @return array language code => strings
	 */
	private static function all_strings() {
		$all = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			$all[ $code ] = AIMP_I18n::with_language( $code, array( __CLASS__, 'strings' ) );
		}
		return $all;
	}

	/**
	 * Configurator strings in the active language.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'language'        => __( 'Language', 'atelier-irisee-master-plugin' ),
			'stepPattern'     => __( 'Pattern & size', 'atelier-irisee-master-plugin' ),
			'stepFabric'      => __( 'Fabric', 'atelier-irisee-master-plugin' ),
			'stepNotions'     => __( 'Choose your haberdashery', 'atelier-irisee-master-plugin' ),
			'stepSummary'     => __( 'Summary', 'atelier-irisee-master-plugin' ),
			'all'             => __( 'All', 'atelier-irisee-master-plugin' ),
			'loading'         => __( 'Loading…', 'atelier-irisee-master-plugin' ),
			'error'           => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
			'noPatterns'      => __( 'No patterns found.', 'atelier-irisee-master-plugin' ),
			'noFabrics'       => __( 'No fabrics are available for this pattern size at the moment.', 'atelier-irisee-master-plugin' ),
			'noNotions'       => __( 'Nothing available at the moment. You can continue without.', 'atelier-irisee-master-plugin' ),
			'previous'        => __( 'Previous', 'atelier-irisee-master-plugin' ),
			'next'            => __( 'Next', 'atelier-irisee-master-plugin' ),
			'back'            => __( 'Back', 'atelier-irisee-master-plugin' ),
			'chooseSize'      => __( 'Choose your size', 'atelier-irisee-master-plugin' ),
			'sizeHelp'        => __( 'Compare your own measurements with the measurements below to pick the right size for this pattern.', 'atelier-irisee-master-plugin' ),
			'sizeChart'       => __( 'Size chart for this pattern', 'atelier-irisee-master-plugin' ),
			'size'            => __( 'Size', 'atelier-irisee-master-plugin' ),
			'bust'            => __( 'Bust', 'atelier-irisee-master-plugin' ),
			'waist'           => __( 'Waist', 'atelier-irisee-master-plugin' ),
			'height'          => __( 'Height', 'atelier-irisee-master-plugin' ),
			'cm'              => __( 'cm', 'atelier-irisee-master-plugin' ),
			'needs'           => __( 'This size needs', 'atelier-irisee-master-plugin' ),
			'fabricNeeded'    => __( 'Fabric', 'atelier-irisee-master-plugin' ),
			'buttons'         => __( 'Buttons', 'atelier-irisee-master-plugin' ),
			'zips'            => __( 'Zips', 'atelier-irisee-master-plugin' ),
			'ribbon'          => __( 'Ribbon', 'atelier-irisee-master-plugin' ),
			'biasTape'        => __( 'Bias tape', 'atelier-irisee-master-plugin' ),
			'zipOf'           => __( '%1$d × zip of %2$s cm', 'atelier-irisee-master-plugin' ),
			'none'            => __( 'None', 'atelier-irisee-master-plugin' ),
			'unavailable'     => __( 'Not available in this size', 'atelier-irisee-master-plugin' ),
			'confirmPattern'  => __( 'Confirm pattern & size', 'atelier-irisee-master-plugin' ),
			'chooseFabric'    => __( 'Choose your fabric', 'atelier-irisee-master-plugin' ),
			'notEnoughStock'  => __( 'Not enough stock', 'atelier-irisee-master-plugin' ),
			'pricePerUnit'    => __( 'Price per 10 cm', 'atelier-irisee-master-plugin' ),
			'pricePerPiece'   => __( 'Price per piece', 'atelier-irisee-master-plugin' ),
			'youNeed'         => __( 'You need', 'atelier-irisee-master-plugin' ),
			'totalForSize'    => __( 'Total for this size', 'atelier-irisee-master-plugin' ),
			'confirmFabric'   => __( 'Confirm fabric', 'atelier-irisee-master-plugin' ),
			'chooseButtons'   => __( 'Choose your buttons (optional)', 'atelier-irisee-master-plugin' ),
			'chooseZip'       => __( 'Choose your zip (optional)', 'atelier-irisee-master-plugin' ),
			'chooseRibbon'    => __( 'Choose your ribbon (optional)', 'atelier-irisee-master-plugin' ),
			'chooseBias'      => __( 'Choose your bias tape (optional)', 'atelier-irisee-master-plugin' ),
			'lengthWillAdd'   => __( '%s will be added.', 'atelier-irisee-master-plugin' ),
			'buttonsWillAdd'  => __( '%d buttons of the chosen design will be added.', 'atelier-irisee-master-plugin' ),
			'zipsWillAdd'     => __( '%1$d zip(s) of %2$s cm will be added.', 'atelier-irisee-master-plugin' ),
			'deselectHint'    => __( 'Click a selected item again to remove it.', 'atelier-irisee-master-plugin' ),
			'continue'        => __( 'Continue', 'atelier-irisee-master-plugin' ),
			'summaryTitle'    => __( 'Your pattern set', 'atelier-irisee-master-plugin' ),
			'product'         => __( 'Product', 'atelier-irisee-master-plugin' ),
			'quantity'        => __( 'Quantity', 'atelier-irisee-master-plugin' ),
			'price'           => __( 'Price', 'atelier-irisee-master-plugin' ),
			'total'           => __( 'Total', 'atelier-irisee-master-plugin' ),
			'lockedNote'      => __( 'Quantities are fixed by your size. The items stay linked in your cart: removing one removes the whole set.', 'atelier-irisee-master-plugin' ),
			'addToCart'       => __( 'Add to cart', 'atelier-irisee-master-plugin' ),
			/* translators: %d: discount percentage */
			'kitDiscount'     => __( 'Sewing project kit discount: −%d%%', 'atelier-irisee-master-plugin' ),
			'adding'          => __( 'Adding…', 'atelier-irisee-master-plugin' ),
			'viewCart'        => __( 'View cart', 'atelier-irisee-master-plugin' ),
			'configureAnother' => __( 'Configure another pattern', 'atelier-irisee-master-plugin' ),
			'saveKit'          => __( 'Save this kit', 'atelier-irisee-master-plugin' ),
			'saving'           => __( 'Saving…', 'atelier-irisee-master-plugin' ),
			'loginToSave'      => __( 'Log in to save your kit.', 'atelier-irisee-master-plugin' ),
			'logIn'            => __( 'Log in', 'atelier-irisee-master-plugin' ),
			'viewFavorites'    => __( 'View favorites', 'atelier-irisee-master-plugin' ),
			'selected'        => __( 'Selected', 'atelier-irisee-master-plugin' ),
			'selectPatternHint' => __( 'Select a pattern to see its pictures, sizes and measurements.', 'atelier-irisee-master-plugin' ),
			'selectFabricHint'  => __( 'Select a fabric to see its pictures and details.', 'atelier-irisee-master-plugin' ),
			'selectItemHint'    => __( 'Select an item to see its pictures and details.', 'atelier-irisee-master-plugin' ),
			'fabricCategory'    => __( 'Fabric category', 'atelier-irisee-master-plugin' ),
			'filters'           => __( 'Filters', 'atelier-irisee-master-plugin' ),
			'availability'      => __( 'Availability', 'atelier-irisee-master-plugin' ),
			'searchFabrics'     => __( 'Search fabrics…', 'atelier-irisee-master-plugin' ),
			'sortBy'            => __( 'Sort by', 'atelier-irisee-master-plugin' ),
			'sortRecommended'   => __( 'Recommended', 'atelier-irisee-master-plugin' ),
			'sortNameAsc'       => __( 'Name (A–Z)', 'atelier-irisee-master-plugin' ),
			'sortNameDesc'      => __( 'Name (Z–A)', 'atelier-irisee-master-plugin' ),
			'sortPriceAsc'      => __( 'Price (low to high)', 'atelier-irisee-master-plugin' ),
			'sortPriceDesc'     => __( 'Price (high to low)', 'atelier-irisee-master-plugin' ),
			'sortNewest'        => __( 'Newest', 'atelier-irisee-master-plugin' ),
			'inStockOnly'       => __( 'Only show fabrics in stock', 'atelier-irisee-master-plugin' ),
			'showing'           => __( 'Showing %1$d–%2$d of %3$d', 'atelier-irisee-master-plugin' ),
			'noFabricsMatch'    => __( 'No fabrics match your filters.', 'atelier-irisee-master-plugin' ),
			'showPicture'       => __( 'Show picture %d', 'atelier-irisee-master-plugin' ),
			'pagination'        => __( 'Pagination', 'atelier-irisee-master-plugin' ),
			'per10cm'           => __( 'per 10 cm', 'atelier-irisee-master-plugin' ),
			'hip'               => __( 'Hip', 'atelier-irisee-master-plugin' ),
			'insideLeg'         => __( 'Inside leg', 'atelier-irisee-master-plugin' ),
			'howToMeasure'      => __( 'How to measure your body measurements', 'atelier-irisee-master-plugin' ),
			'close'             => __( 'Close', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function render() {
		if ( ! wp_script_is( 'aimp-configurator', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-configurator' );
		wp_enqueue_script( 'aimp-configurator' );
		// The fabric step uses the shop pages' filter panel.
		if ( ! wp_style_is( 'aimp-shop', 'registered' ) ) {
			AIMP_Shop::register_assets();
		}
		wp_enqueue_style( 'aimp-shop' );
		wp_localize_script( 'aimp-configurator', 'aimpConfig', self::script_data() );

		ob_start();
		?>
		<div class="aimp-configurator" data-aimp-configurator>
			<noscript><?php esc_html_e( 'Please enable JavaScript to use the pattern configurator.', 'atelier-irisee-master-plugin' ); ?></noscript>
		</div>
		<?php
		return ob_get_clean();
	}
}
