<?php
/**
 * Favorites for the whole shop.
 *
 * - Heart buttons on shop/category grids (classic loop and product blocks), the single product page
 *   and the configurator details panels.
 * - Logged-in customers: stored in user meta. Guests: stored in a cookie, merged into the account on login.
 * - The [atelier_irisee_favorites] shortcode shows the favorites page.
 *
 * Heart states and the favorites page are filled in by assets/js/favorites.js, so they also work on cached pages.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Favorites {

	const META      = '_aimp_favorites';
	const COOKIE    = 'aimp_favorites';
	const NONCE     = 'aimp_favorites';
	const SHORTCODE = 'atelier_irisee_favorites';
	const MAX       = 200;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wc_ajax_aimp_favorites', array( __CLASS__, 'ajax_ids' ) );
		add_action( 'wc_ajax_aimp_favorite_toggle', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'wc_ajax_aimp_favorites_list', array( __CLASS__, 'ajax_list' ) );
		add_action( 'wp_login', array( __CLASS__, 'merge_on_login' ), 10, 2 );

		// Heart buttons.
		add_action( 'woocommerce_before_shop_loop_item', array( __CLASS__, 'loop_button' ), 5 );
		add_filter( 'render_block_woocommerce/product-image', array( __CLASS__, 'block_button' ), 10, 3 );

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_page' ) );
	}

	/* ------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------ */

	/**
	 * Unique positive IDs, newest first, at most MAX.
	 *
	 * @param mixed $ids IDs.
	 * @return int[]
	 */
	private static function clean( $ids ) {
		$ids = is_array( $ids ) ? $ids : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		return array_slice( $ids, 0, self::MAX );
	}

	private static function cookie_ids() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		return self::clean( explode( ',', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) ) );
	}

	/**
	 * Favorite product IDs of the current visitor.
	 *
	 * @return int[]
	 */
	public static function get_ids() {
		if ( is_user_logged_in() ) {
			return self::clean( get_user_meta( get_current_user_id(), self::META, true ) );
		}
		return self::cookie_ids();
	}

	private static function save( $ids ) {
		$ids = self::clean( $ids );
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::META, $ids );
			return;
		}
		$value = implode( ',', $ids );
		wc_setcookie( self::COOKIE, $value, time() + YEAR_IN_SECONDS, is_ssl() );
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * Keep a guest's favorites when they log in.
	 *
	 * @param string  $user_login Login name.
	 * @param WP_User $user       User.
	 */
	public static function merge_on_login( $user_login, $user ) {
		$guest = self::cookie_ids();
		if ( ! $guest ) {
			return;
		}
		$saved = self::clean( get_user_meta( $user->ID, self::META, true ) );
		update_user_meta( $user->ID, self::META, self::clean( array_merge( $guest, $saved ) ) );
		wc_setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, is_ssl() );
		unset( $_COOKIE[ self::COOKIE ] );
	}

	/* ------------------------------------------------------------------
	 * Heart buttons
	 * ------------------------------------------------------------------ */

	public static function icon() {
		return '<svg class="aimp-fav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
	}

	/**
	 * Heart button. Its state and labels are set by favorites.js.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $modifier   Extra CSS class (e.g. aimp-fav--overlay).
	 * @return string
	 */
	public static function button_html( $product_id, $modifier = '' ) {
		$label = __( 'Add to favorites', 'atelier-irisee-master-plugin' );
		return sprintf(
			'<button type="button" class="aimp-fav %1$s" data-aimp-fav="%2$d" aria-pressed="false" aria-label="%3$s" title="%3$s">%4$s</button>',
			esc_attr( $modifier ),
			absint( $product_id ),
			esc_attr( $label ),
			self::icon()
		);
	}

	public static function loop_button() {
		global $product;
		if ( $product instanceof WC_Product ) {
			echo self::button_html( $product->get_id(), 'aimp-fav--overlay' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button_html().
		}
	}

	/**
	 * Heart on the product image of block-based product grids (Product Collection etc.).
	 *
	 * @param string        $content  Block HTML.
	 * @param array         $block    Parsed block.
	 * @param WP_Block|null $instance Block instance (provides the postId context).
	 * @return string
	 */
	public static function block_button( $content, $block, $instance = null ) {
		$product_id = ( $instance instanceof WP_Block && isset( $instance->context['postId'] ) ) ? absint( $instance->context['postId'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) || '' === trim( $content ) ) {
			return $content;
		}
		return '<div class="aimp-fav-wrap">' . $content . self::button_html( $product_id, 'aimp-fav--overlay' ) . '</div>';
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Texts for favorites.js in the active language.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'add'         => __( 'Add to favorites', 'atelier-irisee-master-plugin' ),
			'remove'      => __( 'Remove from favorites', 'atelier-irisee-master-plugin' ),
			'inFavorites' => __( 'In your favorites', 'atelier-irisee-master-plugin' ),
			'added'       => __( 'Added to favorites.', 'atelier-irisee-master-plugin' ),
			'removed'     => __( 'Removed from favorites.', 'atelier-irisee-master-plugin' ),
			'view'        => __( 'View favorites', 'atelier-irisee-master-plugin' ),
			'empty'       => __( 'You have no favorites yet.', 'atelier-irisee-master-plugin' ),
			'browse'      => __( 'Browse the shop', 'atelier-irisee-master-plugin' ),
			'loading'     => __( 'Loading…', 'atelier-irisee-master-plugin' ),
			'error'       => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function enqueue() {
		wp_enqueue_style( 'aimp-favorites', AIMP_PLUGIN_URL . 'assets/css/favorites.css', array(), AIMP_VERSION );
		// The favorites page: its card styles in <head> (no flash of unstyled cards).
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			self::enqueue_card_styles();
		}
		wp_enqueue_script( 'aimp-favorites', AIMP_PLUGIN_URL . 'assets/js/favorites.js', array(), AIMP_VERSION, true );

		$i18n = array();
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			$i18n[ $code ] = AIMP_I18n::with_language( $code, array( __CLASS__, 'strings' ) );
		}
		$page = AIMP_Settings::get( 'favorites_page' );

		wp_localize_script(
			'aimp-favorites',
			'aimpFavoritesConfig',
			array(
				'endpoint'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'nonce'           => is_user_logged_in() ? wp_create_nonce( self::NONCE ) : '',
				'favoritesUrl'    => $page ? get_permalink( $page ) : '',
				'shopUrl'         => wc_get_page_permalink( 'shop' ),
				'icon'            => self::icon(),
				'defaultLanguage' => AIMP_I18n::default_language(),
				'i18n'            => $i18n,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Endpoints
	 * ------------------------------------------------------------------ */

	public static function ajax_ids() {
		wp_send_json_success( array( 'ids' => self::get_ids() ) );
	}

	public static function ajax_toggle() {
		// Logged-in customers: protect their account data. Guests only change their own cookie,
		// and a nonce check would break on cached pages.
		if ( is_user_logged_in() && ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'Your session has expired. Please reload the page and try again.', 'atelier-irisee-master-plugin' ) ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above for logged-in users.
		$product = wc_get_product( isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0 );
		if ( $product && $product->get_parent_id() ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		if ( ! $product || 'publish' !== $product->get_status() ) {
			wp_send_json_error( array( 'errors' => array( __( 'This item is not available.', 'atelier-irisee-master-plugin' ) ) ), 404 );
		}

		$id  = $product->get_id();
		$ids = self::get_ids();
		if ( in_array( $id, $ids, true ) ) {
			$ids      = array_values( array_diff( $ids, array( $id ) ) );
			$favorite = false;
		} else {
			array_unshift( $ids, $id );
			$favorite = true;
		}
		self::save( $ids );

		wp_send_json_success(
			array(
				'ids'      => self::get_ids(),
				'favorite' => $favorite,
			)
		);
	}

	public static function ajax_list() {
		$html = '';
		foreach ( self::get_ids() as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
				continue;
			}
			$html .= self::card_html( $product );
		}
		wp_send_json_success(
			array(
				'html' => $html ? '<ul class="aimp-grid aimp-grid--compact aimp-favorites-grid">' . $html . '</ul>' : '',
			)
		);
	}

	/**
	 * One product on the favorites page.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function card_html( $product ) {
		// The card of the category pages, as a link to the product page (the amount, length or size is
		// chosen there), with a "View product" button at the bottom.
		$card   = AIMP_Catalog::card( $product );
		$suffix = AIMP_Catalog::sold_per_10cm( $product ) ? ' <small>' . esc_html__( 'per 10 cm', 'atelier-irisee-master-plugin' ) . '</small>' : '';
		$badge  = $product->is_in_stock() ? '' : '<span class="aimp-badge">' . esc_html__( 'Out of stock', 'atelier-irisee-master-plugin' ) . '</span>';

		return sprintf(
			'<li class="aimp-fav-item aimp-fav-wrap" data-aimp-fav-card="%1$d">' .
			'<a class="aimp-card%2$s" href="%3$s">' .
			'<span class="aimp-card-image"><img src="%4$s" alt="%5$s" loading="lazy"></span>' .
			'<span class="aimp-card-name">%6$s</span>' .
			'<span class="aimp-card-price">%7$s%8$s</span>%9$s' .
			'<span class="aimp-button aimp-fav-view">%10$s</span>' .
			'</a>%11$s</li>',
			$product->get_id(),
			$product->is_in_stock() ? '' : ' is-unavailable',
			esc_url( $card['permalink'] ),
			esc_url( $card['image'] ),
			esc_attr( '' !== $card['image_alt'] ? $card['image_alt'] : $card['name'] ),
			esc_html( $card['name'] ),
			wp_kses_post( $card['price_html'] ),
			$suffix, // Escaped above.
			$badge, // Escaped above.
			esc_html__( 'View product', 'atelier-irisee-master-plugin' ),
			self::button_html( $product->get_id(), 'aimp-fav--overlay' )
		);
	}

	/* ------------------------------------------------------------------
	 * Favorites page
	 * ------------------------------------------------------------------ */

	/**
	 * The cards use the configurator's card styles.
	 */
	private static function enqueue_card_styles() {
		if ( ! wp_style_is( 'aimp-configurator', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
		}
		wp_enqueue_style( 'aimp-configurator' );
		wp_enqueue_style( 'aimp-favorites-page', AIMP_PLUGIN_URL . 'assets/css/favorites-page.css', array( 'aimp-configurator', 'aimp-favorites' ), AIMP_VERSION );
	}

	public static function render_page() {
		self::enqueue_card_styles();
		$kits = class_exists( 'AIMP_Saved_Kits' ) ? AIMP_Saved_Kits::section_html() : '';
		return '<div class="aimp-configurator aimp-favorites">' . $kits .
			( $kits ? '<h2 class="aimp-saved-kits-title">' . esc_html__( 'My favorites', 'atelier-irisee-master-plugin' ) . '</h2>' : '' ) .
			'<div class="aimp-favorites-list" data-aimp-favorites-page><noscript>' .
			esc_html__( 'Please enable JavaScript to see your favorites.', 'atelier-irisee-master-plugin' ) .
			'</noscript></div></div>';
	}
}
