<?php
/**
 * Product edit screen for fabrics and patterns: one "Atelier Irisee" workspace with only the fields these
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

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_box' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		// Patterns: the long description sits under "other fields"; WordPress's sticky editor toolbar would misbehave there.
		add_filter( 'wp_editor_expand', array( __CLASS__, 'editor_expand' ), 10, 2 );
		// Products → All fabrics / Add a fabric / All patterns / Add a pattern.
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_filter( 'submenu_file', array( __CLASS__, 'current_submenu' ) );
		add_action( 'save_post_product', array( __CLASS__, 'prepare_new' ), 10, 3 );
	}

	public static function editor_expand( $expand, $post_type ) {
		return ( 'product' === $post_type && 'pattern' === self::current_type() ) ? false : $expand;
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
			foreach ( array( 'fabric_cat', 'pattern_cat' ) as $key ) {
				$term = get_term( AIMP_Settings::get( $key ), 'product_cat' );
				if ( $term && ! is_wp_error( $term ) && $term->slug === $slug ) {
					return 'edit.php?post_type=product&product_cat=' . rawurlencode( $term->slug );
				}
			}
		}
		if ( 'add' === $screen->action && ! empty( $_GET['aimp_new'] ) ) {
			$kind = sanitize_key( wp_unslash( $_GET['aimp_new'] ) );
			if ( in_array( $kind, array( 'fabric', 'pattern' ), true ) ) {
				return 'post-new.php?post_type=product&aimp_new=' . $kind;
			}
		}
		// phpcs:enable
		return $file;
	}

	/**
	 * The new draft from "Add a fabric" or "Add a pattern": its category (Stoffen / Patronen) is ticked, and a
	 * pattern becomes a variable product, so the workspace opens straight away.
	 *
	 * @param int     $post_id Product.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Existing post.
	 */
	public static function prepare_new( $post_id, $post, $update ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only on the "new product" screen, for its own draft.
		$kind = isset( $_GET['aimp_new'] ) ? sanitize_key( wp_unslash( $_GET['aimp_new'] ) ) : '';
		if ( $update || 'auto-draft' !== $post->post_status || ! in_array( $kind, array( 'fabric', 'pattern' ), true ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$cat = AIMP_Settings::get( 'fabric' === $kind ? 'fabric_cat' : 'pattern_cat' );
		if ( $cat ) {
			wp_set_object_terms( $post_id, array( (int) $cat ), 'product_cat' );
		}
		wp_set_object_terms( $post_id, 'pattern' === $kind ? 'variable' : 'simple', 'product_type' );
	}

	/**
	 * Fabric, pattern or '' (the normal screen), from the saved categories.
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
		return '';
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
		echo '<div class="aimp-ws" data-aimp-ws data-type="' . esc_attr( $type ) . '" data-skill="' . esc_attr( AIMP_Catalog::META_SKILL ) . '" data-price="' . esc_attr( AIMP_Product_Fields::META_PRICE ) . '" data-long-label="' . esc_attr__( 'Long description', 'atelier-irisee-master-plugin' ) . '">';
		echo '<div data-ws-slot="check"></div>';
		if ( 'fabric' === $type ) {
			self::card( 1, __( 'Price and stock', 'atelier-irisee-master-plugin' ), 'price', __( 'The price is per 10 cm.', 'atelier-irisee-master-plugin' ) );
			self::card( 2, __( 'Inspiration', 'atelier-irisee-master-plugin' ), 'inspiration' );
			self::card( 3, __( 'Specifications', 'atelier-irisee-master-plugin' ), 'specs' );
			self::card( 4, __( 'Washing instructions', 'atelier-irisee-master-plugin' ), 'washing' );
			self::card( 5, __( 'Recommendation', 'atelier-irisee-master-plugin' ), 'recommendation' );
		} else {
			self::card( 1, __( 'Description', 'atelier-irisee-master-plugin' ), 'description', __( 'Shown on the pattern page under "Description".', 'atelier-irisee-master-plugin' ) );
			self::card( 2, __( 'Price and stock', 'atelier-irisee-master-plugin' ), 'price', __( 'One price and one stock for all sizes.', 'atelier-irisee-master-plugin' ) );
			self::card( 3, __( 'Pattern details', 'atelier-irisee-master-plugin' ), 'details', __( 'The same for all sizes.', 'atelier-irisee-master-plugin' ) );
			self::card( 4, __( 'Fitting fabrics', 'atelier-irisee-master-plugin' ), 'priority' );
			self::card( 5, __( 'Sizes and material needs', 'atelier-irisee-master-plugin' ), 'sizes', __( 'Attributes: the sizes, and for a pack with two designs a Design attribute. Variations: per size (and design) the fabric, buttons, zip, ribbon and bias tape it needs, and the measurements.', 'atelier-irisee-master-plugin' ) );
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
