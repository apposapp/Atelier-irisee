<?php
/**
 * Product edit screen for fabrics, patterns and haberdashery: one "Atelier Irisee" workspace with only the fields these
 * products use, as numbered cards. Every other field is still there, neatly grouped under "Show all
 * other fields".
 *
 * Nothing is duplicated: assets/js/admin-workspace.js moves the existing inputs (WooCommerce's price and
 * stock rows, the Fabric texts box, the recommendation box, the pattern tab) into the cards, so saving
 * works exactly as before. The script runs in the footer before the text editors start, because moving a
 * running editor would empty it.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Product_Workspace {

	/**
	 * Boxes the product screen needs: per-user Screen Options could hide, fold or move them away for one user only.
	 */
	const ALWAYS_SHOWN = array( 'aimp-workspace', 'woocommerce-product-data', 'postexcerpt', 'postdivrich' );

	/**
	 * Product kinds with their own menu items and workspace: kind => category setting. Fabric, pattern and every
	 * haberdashery kind (AIMP_Notions::kinds()).
	 *
	 * @return array
	 */
	public static function kinds() {
		return array(
			'fabric'  => 'fabric_cat',
			'pattern' => 'pattern_cat',
		) + array_map(
			function ( $info ) {
				return $info['setting'];
			},
			AIMP_Notions::kinds()
		);
	}

	/**
	 * Whether a workspace type is a haberdashery kind.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	private static function is_notion( $type ) {
		return null !== AIMP_Notions::kind( $type );
	}

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_box' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		// Patterns: the long description sits under "other fields"; WordPress's sticky editor toolbar would misbehave there.
		add_filter( 'wp_editor_expand', array( __CLASS__, 'editor_expand' ), 10, 2 );
		// Products → All fabrics / Add a fabric / All patterns / Add a pattern / All buttons / Add a button / ….
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_filter( 'submenu_file', array( __CLASS__, 'current_submenu' ) );
		add_action( 'save_post_product', array( __CLASS__, 'prepare_new' ), 10, 3 );
		// Everyone sees the Product data box and the workspace, whatever their own Screen Options say.
		add_filter( 'hidden_meta_boxes', array( __CLASS__, 'never_hidden' ), 10, 2 );
		add_filter( 'get_user_option_closedpostboxes_product', array( __CLASS__, 'never_closed' ) );
		add_filter( 'get_user_option_meta-box-order_product', array( __CLASS__, 'main_column' ) );
		add_action( 'admin_head', array( __CLASS__, 'screen_options_css' ) );
	}

	/**
	 * @param string[]  $hidden Hidden box ids.
	 * @param WP_Screen $screen Screen.
	 * @return string[]
	 */
	public static function never_hidden( $hidden, $screen ) {
		if ( ! $screen || 'product' !== $screen->id || ! is_array( $hidden ) ) {
			return $hidden;
		}
		return array_values( array_diff( $hidden, self::ALWAYS_SHOWN ) );
	}

	/**
	 * @param mixed $closed Folded box ids.
	 * @return mixed
	 */
	public static function never_closed( $closed ) {
		return is_array( $closed ) ? array_values( array_diff( $closed, self::ALWAYS_SHOWN ) ) : $closed;
	}

	/**
	 * A box dragged to the side or to the bottom goes back to the top of the main column.
	 *
	 * @param mixed $order Column => comma-separated box ids.
	 * @return mixed
	 */
	public static function main_column( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}
		$main  = array( 'aimp-workspace', 'woocommerce-product-data' );
		$moved = array();
		foreach ( $order as $column => $ids ) {
			$list = array_filter( explode( ',', (string) $ids ) );
			if ( 'normal' !== $column && array_intersect( $list, $main ) ) {
				$moved            = array_merge( $moved, array_intersect( $list, $main ) );
				$order[ $column ] = implode( ',', array_diff( $list, $main ) );
			}
		}
		if ( $moved ) {
			$normal          = array_filter( explode( ',', isset( $order['normal'] ) ? (string) $order['normal'] : '' ) );
			$order['normal'] = implode( ',', array_merge( array_values( array_intersect( $main, $moved ) ), array_values( array_diff( $normal, $moved ) ) ) );
		}
		return $order;
	}

	/**
	 * Screen Options on the product screen: no checkbox for the boxes that always show.
	 */
	public static function screen_options_css() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		$labels = array_map(
			function ( $id ) {
				return '.metabox-prefs label[for="' . $id . '-hide"]';
			},
			self::ALWAYS_SHOWN
		);
		echo '<style>' . implode( ',', $labels ) . '{display:none}</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed ids.
	}

	public static function editor_expand( $expand, $post_type ) {
		$type = self::current_type();
		return ( 'product' === $post_type && ( 'pattern' === $type || self::is_notion( $type ) ) ) ? false : $expand;
	}

	/**
	 * Products menu: "All fabrics", "Add a fabric", "All patterns", "Add a pattern" (for the categories that
	 * are set under WooCommerce → Atelier Irisee). The lists are the product list filtered on that category.
	 */
	public static function menu() {
		$parent = 'edit.php?post_type=product';
		$kinds  = array(
			'fabric'  => array( 'fabric_cat', __( 'All fabrics', 'atelier-irisee-master-plugin' ), __( 'Add a fabric', 'atelier-irisee-master-plugin' ) ),
			'pattern' => array( 'pattern_cat', __( 'All patterns', 'atelier-irisee-master-plugin' ), __( 'Add a pattern', 'atelier-irisee-master-plugin' ) ),
		);
		foreach ( AIMP_Notions::kinds() as $kind => $info ) {
			$kinds[ $kind ] = array( $info['setting'], $info['all'], $info['add'] );
		}
		foreach ( $kinds as $kind => $labels ) {
			$term = get_term( AIMP_Settings::get( $labels[0] ), 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			add_submenu_page( $parent, $labels[1], $labels[1], 'edit_products', $parent . '&product_cat=' . rawurlencode( $term->slug ) );
			add_submenu_page( $parent, $labels[2], $labels[2], 'edit_products', 'post-new.php?post_type=product&aimp_new=' . $kind );
		}
	}

	/**
	 * Highlights "All fabrics" / "All patterns" / "Add a …" in the Products menu while that screen is open
	 * (WordPress would highlight "All products" or "Add New").
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public static function current_submenu( $file ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which screen is open.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return $file;
		}
		if ( 'edit-product' === $screen->id && ! empty( $_GET['product_cat'] ) ) {
			$slug = sanitize_title( wp_unslash( $_GET['product_cat'] ) );
			foreach ( self::kinds() as $key ) {
				$term = get_term( AIMP_Settings::get( $key ), 'product_cat' );
				if ( $term && ! is_wp_error( $term ) && $term->slug === $slug ) {
					return 'edit.php?post_type=product&product_cat=' . rawurlencode( $term->slug );
				}
			}
		}
		if ( 'add' === $screen->action && ! empty( $_GET['aimp_new'] ) ) {
			$kind = sanitize_key( wp_unslash( $_GET['aimp_new'] ) );
			if ( isset( self::kinds()[ $kind ] ) ) {
				return 'post-new.php?post_type=product&aimp_new=' . $kind;
			}
		}
		// phpcs:enable
		return $file;
	}

	/**
	 * The new draft from "Add a fabric", "Add a pattern" or "Add a button": its category (Stoffen / Patronen / Knopen) is ticked, and a
	 * pattern becomes a variable product, so the workspace opens straight away.
	 *
	 * @param int     $post_id Product.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Existing post.
	 */
	public static function prepare_new( $post_id, $post, $update ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only on the "new product" screen, for its own draft.
		$kind = isset( $_GET['aimp_new'] ) ? sanitize_key( wp_unslash( $_GET['aimp_new'] ) ) : '';
		if ( $update || 'auto-draft' !== $post->post_status || ! isset( self::kinds()[ $kind ] ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$cat = AIMP_Settings::get( self::kinds()[ $kind ] );
		if ( $cat ) {
			wp_set_object_terms( $post_id, array( (int) $cat ), 'product_cat' );
		}
		wp_set_object_terms( $post_id, 'pattern' === $kind ? 'variable' : 'simple', 'product_type' );
	}

	/**
	 * Fabric, pattern, a haberdashery kind (button, zip, …) or '' (the normal screen), from the saved categories.
	 *
	 * @param WC_Product|null $product Product.
	 * @return string
	 */
	public static function type( $product ) {
		if ( ! $product instanceof WC_Product || ! $product->get_id() ) {
			return '';
		}
		if ( $product->is_type( 'variable' ) && AIMP_Catalog::in_categories( $product, AIMP_Catalog::category_tree( AIMP_Settings::get( 'pattern_cat' ) ) ) ) {
			return 'pattern';
		}
		if ( $product->is_type( 'simple' ) && AIMP_Catalog::in_categories( $product, AIMP_Catalog::category_tree( AIMP_Settings::get( 'fabric_cat' ) ) ) ) {
			return 'fabric';
		}
		return AIMP_Notions::kind_of( $product );
	}

	private static function current_type() {
		global $post;
		return $post ? self::type( wc_get_product( $post->ID ) ) : '';
	}

	public static function add_box() {
		if ( '' === self::current_type() ) {
			return;
		}
		add_meta_box( 'aimp-workspace', __( 'Atelier Irisee', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render' ), 'product', 'normal', 'high' );
	}

	public static function enqueue( $hook_suffix ) {
		global $typenow;
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || 'product' !== $typenow || '' === self::current_type() ) {
			return;
		}
		wp_enqueue_style( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/css/admin.css', array(), AIMP_VERSION );
		// In the footer, before the text editors start (they print at priority 50).
		wp_enqueue_script( 'aimp-admin-workspace', AIMP_PLUGIN_URL . 'assets/js/admin-workspace.js', array(), AIMP_VERSION, true );
	}

	/**
	 * A numbered, foldable card with an empty place the script fills.
	 *
	 * @param int    $number Number.
	 * @param string $title  Title.
	 * @param string $slot   Slot name.
	 * @param string $hint   Short explanation.
	 */
	private static function card( $number, $title, $slot, $hint = '' ) {
		printf(
			'<details class="aimp-ws-card" open data-ws-card="%3$s"><summary><span class="aimp-ws-num">%1$d</span><span class="aimp-ws-title">%2$s</span></summary><div class="aimp-ws-body">%4$s<div data-ws-slot="%3$s"></div></div></details>',
			(int) $number,
			esc_html( $title ),
			esc_attr( $slot ),
			'' !== $hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
		);
	}

	public static function render() {
		$type = self::current_type();
		echo '<div class="aimp-ws" data-aimp-ws data-type="' . esc_attr( $type ) . '"' . ( self::is_notion( $type ) ? ' data-notion' : '' ) . ' data-skill="' . esc_attr( AIMP_Catalog::META_SKILL ) . '" data-price="' . esc_attr( AIMP_Product_Fields::META_PRICE ) . '" data-long-label="' . esc_attr__( 'Long description', 'atelier-irisee-master-plugin' ) . '">';
		echo '<div data-ws-slot="check"></div>';
		if ( 'fabric' === $type ) {
			self::card( 1, __( 'Price and stock', 'atelier-irisee-master-plugin' ), 'price', __( 'The price is per 10 cm.', 'atelier-irisee-master-plugin' ) );
			self::card( 2, __( 'Inspiration', 'atelier-irisee-master-plugin' ), 'inspiration' );
			self::card( 3, __( 'Specifications', 'atelier-irisee-master-plugin' ), 'specs' );
			self::card( 4, __( 'Washing instructions', 'atelier-irisee-master-plugin' ), 'washing' );
			self::card( 5, __( 'Recommendation', 'atelier-irisee-master-plugin' ), 'recommendation' );
		} elseif ( self::is_notion( $type ) ) {
			self::card( 1, __( 'Price and stock', 'atelier-irisee-master-plugin' ), 'price', __( 'The price and the stock follow "Sold per": per piece, per set, per 10 cm or per roll.', 'atelier-irisee-master-plugin' ) );
			self::card( 2, __( 'Order information', 'atelier-irisee-master-plugin' ), 'order' );
			self::card( 3, __( 'Description', 'atelier-irisee-master-plugin' ), 'description', __( 'Shown on the product page under "Description". The pictures are set on the right: Product image and Product gallery.', 'atelier-irisee-master-plugin' ) );
			self::card( 4, __( 'Specifications', 'atelier-irisee-master-plugin' ), 'specs', __( 'The type and the size decide in which patterns the product is offered.', 'atelier-irisee-master-plugin' ) );
			self::card( 5, __( 'Ideal for', 'atelier-irisee-master-plugin' ), 'ideal' );
			self::card( 6, __( 'Recommendation', 'atelier-irisee-master-plugin' ), 'recommendation' );
		} else {
			self::card( 1, __( 'Description', 'atelier-irisee-master-plugin' ), 'description', __( 'Shown on the pattern page under "Description".', 'atelier-irisee-master-plugin' ) );
			self::card( 2, __( 'Price and stock', 'atelier-irisee-master-plugin' ), 'price', __( 'One price and one stock for all sizes.', 'atelier-irisee-master-plugin' ) );
			self::card( 3, __( 'Pattern details', 'atelier-irisee-master-plugin' ), 'details', __( 'The same for all sizes.', 'atelier-irisee-master-plugin' ) );
			self::card( 4, __( 'Fitting fabrics', 'atelier-irisee-master-plugin' ), 'priority' );
			self::card( 5, __( 'Sizes and material needs', 'atelier-irisee-master-plugin' ), 'sizes', __( 'Attributes: the sizes, and for a pack with two designs a Design attribute. Variations: per size (and design) the fabric and haberdashery it needs (only what is switched on under Pattern details → Haberdashery), and the measurements.', 'atelier-irisee-master-plugin' ) );
			self::card( 6, __( 'Recommendation', 'atelier-irisee-master-plugin' ), 'recommendation' );
		}
		printf(
			'<details class="aimp-ws-other" data-ws-other><summary>%1$s</summary><p class="description">%2$s</p><div data-ws-slot="other"></div></details>',
			esc_html__( 'Show all other fields', 'atelier-irisee-master-plugin' ),
			esc_html__( 'Fields these products normally don\'t use, grouped. Everything here still saves as usual.', 'atelier-irisee-master-plugin' )
		);
		echo '</div>';
	}
}
