<?php
/**
 * Search in the header: live results while typing (?wc-ajax=aimp_search&q=…), and "See all results" on the
 * All products page (its search filter reads ?aimp_q=).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Search {

	const LIMIT = 8;

	public static function init() {
		add_action( 'wc_ajax_aimp_search', array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Where "See all results" (and the form without JavaScript) goes.
	 *
	 * @return string
	 */
	public static function results_url() {
		$page = AIMP_Shop::page_for_type( 'all' );
		return $page ? get_permalink( $page ) : wc_get_page_permalink( 'shop' );
	}

	/**
	 * Texts and addresses for search.js.
	 *
	 * @return array
	 */
	public static function config() {
		return array(
			'endpoint'   => WC_AJAX::get_endpoint( 'aimp_search' ),
			'allUrl'     => self::results_url(),
			/* translators: %s: what was searched for */
			'none'       => __( 'No products found for "%s".', 'atelier-irisee-master-plugin' ),
			'all'        => __( 'See all results', 'atelier-irisee-master-plugin' ),
			'loading'    => __( 'Loading…', 'atelier-irisee-master-plugin' ),
			/* translators: %s: a product name or word */
			'didYouMean' => __( 'Did you mean %s?', 'atelier-irisee-master-plugin' ),
			'recent'     => __( 'Recent searches', 'atelier-irisee-master-plugin' ),
			'products'   => __( 'Products', 'atelier-irisee-master-plugin' ),
			'categories' => __( 'Categories', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * Visible, published products matching the words (title, content, excerpt) or the SKU.
	 *
	 * @param string $q Search words.
	 * @return int[]
	 */
	private static function find( $q ) {
		$hidden = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => array( 'exclude-from-search' ),
			'operator' => 'NOT IN',
		);
		$ids    = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				's'                => $q,
				'posts_per_page'   => self::LIMIT,
				'fields'           => 'ids',
				'orderby'          => 'relevance',
				'tax_query'        => array( $hidden ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- needed to hide products.
				'suppress_filters' => false,
			)
		);
		if ( count( $ids ) < self::LIMIT ) {
			$sku = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => self::LIMIT,
					'fields'         => 'ids',
					'tax_query'      => array( $hidden ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- SKU search.
						array(
							'key'     => '_sku',
							'value'   => $q,
							'compare' => 'LIKE',
						),
					),
				)
			);
			$ids = array_slice( array_values( array_unique( array_merge( $ids, $sku ) ) ), 0, self::LIMIT );
		}
		return array_map( 'intval', $ids );
	}

	public static function ajax() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public search.
		$q = isset( $_GET['q'] ) ? trim( substr( sanitize_text_field( wp_unslash( $_GET['q'] ) ), 0, 80 ) ) : '';
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $q ) < 2 : strlen( $q ) < 2 ) {
			wp_send_json_success( array( 'items' => array() ) );
		}

		$key = 'aimp_search_' . md5( strtolower( $q ) . '|' . AIMP_I18n::current() . '|' . AIMP_Shop::cache_version() );
		$ids = get_transient( $key );
		if ( ! is_array( $ids ) ) {
			$ids = self::find( $q );
			set_transient( $key, $ids, 10 * MINUTE_IN_SECONDS );
		}

		$items = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			$card    = AIMP_Catalog::card( $product );
			$items[] = array(
				'name'       => $card['name'],
				'image'      => $card['image'],
				'url'        => $card['permalink'],
				'price_html' => $card['price_html'] . ( AIMP_Catalog::sold_per_10cm( $product ) ? ' <small>' . esc_html__( 'per 10 cm', 'atelier-irisee-master-plugin' ) . '</small>' : '' ),
			);
		}
		$categories = self::categories( $q );
		wp_send_json_success(
			array(
				'categories' => $categories,
				'items'      => $items,
				'suggest'    => $items || $categories ? '' : self::suggestion( $q ),
				'all'        => add_query_arg( 'aimp_q', rawurlencode( $q ), self::results_url() ),
			)
		);
	}

	/**
	 * Product categories whose name matches, with their shop page: "Viscose (12)".
	 *
	 * @param string $q Search words.
	 * @return array [ name, count, url ]
	 */
	private static function categories( $q ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'name__like' => $q,
				'hide_empty' => true,
				'number'     => 6,
			)
		);
		$list = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$url = AIMP_Shop::category_url( $term );
			if ( $url && count( $list ) < 3 ) {
				$list[] = array(
					'name'  => $term->name,
					'count' => (int) $term->count,
					'url'   => $url,
				);
			}
		}
		return $list;
	}

	/**
	 * Nothing found: the product name closest to what was typed ("viskose" → "Viscose …"), or ''.
	 * Each word of a name counts too, so one misspelled word still finds its product.
	 *
	 * @param string $q Search words.
	 * @return string
	 */
	private static function suggestion( $q ) {
		$key   = 'aimp_search_names_' . AIMP_Shop::cache_version();
		$names = get_transient( $key );
		if ( ! is_array( $names ) ) {
			$names = array();
			foreach ( get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 1000,
					'fields'         => 'ids',
				)
			) as $id ) {
				$names[] = wp_strip_all_tags( get_post_field( 'post_title', $id ) );
			}
			set_transient( $key, $names, DAY_IN_SECONDS );
		}
		$q     = strtolower( remove_accents( $q ) );
		$best  = '';
		$score = max( 1, (int) floor( strlen( $q ) / 3 ) ) + 1;
		foreach ( $names as $name ) {
			$plain = strtolower( remove_accents( $name ) );
			$words = array_merge( array( $plain ), preg_split( '/[\s\-–]+/', $plain ) );
			foreach ( $words as $word ) {
				if ( '' === $word || strlen( $word ) > 255 ) {
					continue;
				}
				$distance = levenshtein( $q, $word );
				if ( $distance < $score ) {
					$score = $distance;
					$best  = $word === $plain ? $name : self::matching_word( $name, $word );
				}
			}
		}
		return $best;
	}

	/**
	 * The word of a name as it is written (with its accents and capitals).
	 *
	 * @param string $name  Product name.
	 * @param string $plain The word without accents, lower case.
	 * @return string
	 */
	private static function matching_word( $name, $plain ) {
		foreach ( preg_split( '/[\s\-–]+/u', $name ) as $word ) {
			if ( strtolower( remove_accents( $word ) ) === $plain ) {
				return $word;
			}
		}
		return $plain;
	}
}
