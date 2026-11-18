<?php
/**
 * Blocks for the home page, each a shortcode and a part of the Design studio:
 *
 * - [atelier_irisee_categories]: shop by category, the sewing project kits as the large tile;
 * - [atelier_irisee_reviews]: product reviews and your own testimonials in a slider;
 * - [atelier_irisee_maker]: meet the maker (picture, story, button);
 * - [atelier_irisee_new]: the newest products in a row;
 * - [atelier_irisee_newsletter]: the newsletter sign-up, with the welcome discount for new accounts;
 * - [atelier_irisee_faq]: the first questions and answers, with a button to the contact page;
 * - [atelier_irisee_brands]: the brands, linking to their products;
 * - [atelier_irisee_giftcard_promo]: the gift cards promoted (banner, amounts or designs; layout="…");
 * - [atelier_irisee_instagram]: the newest posts of the shop's Instagram (AIMP_Instagram; count="…").
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Home_Blocks {

	/** Shortcode => method. */
	const TAGS = array(
		'atelier_irisee_categories' => 'categories',
		'atelier_irisee_reviews'    => 'reviews',
		'atelier_irisee_maker'      => 'maker',
		'atelier_irisee_new'        => 'new_in',
		'atelier_irisee_newsletter' => 'newsletter',
		'atelier_irisee_faq'        => 'faq',
		'atelier_irisee_brands'     => 'brands',
		'atelier_irisee_giftcard_promo' => 'giftcard_promo',
		'atelier_irisee_instagram'      => 'instagram',
	);

	public static function init() {
		foreach ( self::TAGS as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
	}

	public static function register_assets() {
		wp_register_style( 'aimp-home-blocks', AIMP_PLUGIN_URL . 'assets/css/home-blocks.css', array(), AIMP_VERSION );
		wp_register_script( 'aimp-home-blocks', AIMP_PLUGIN_URL . 'assets/js/home-blocks.js', array(), AIMP_VERSION, true );
		if ( AIMP_Blocks::page_has( array_keys( self::TAGS ) ) ) {
			wp_enqueue_style( 'aimp-home-blocks' );
		}
	}

	/**
	 * The block's styles and script.
	 */
	private static function assets() {
		if ( ! wp_style_is( 'aimp-home-blocks', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-home-blocks' );
		// Every block: the script measures the scrollbar for the full width (and runs the sliders).
		wp_enqueue_script( 'aimp-home-blocks' );
	}

	/**
	 * A block title with the gold lines on both sides.
	 *
	 * @param string $text  Title.
	 * @param string $class Extra class.
	 * @return string
	 */
	private static function title( $text, $class = '' ) {
		return '' === $text ? '' : '<h2 class="aimp-block-title' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '"><span>' . esc_html( $text ) . '</span></h2>';
	}

	/**
	 * A page address chosen in the studio, or the given page's address.
	 *
	 * @param string $url  Studio address.
	 * @param int    $page Page.
	 * @return string
	 */
	private static function url_or_page( $url, $page ) {
		if ( '' !== (string) $url ) {
			return (string) $url;
		}
		return $page && 'publish' === get_post_status( $page ) ? get_permalink( $page ) : '';
	}

	/**
	 * Previous / next buttons of a slider.
	 *
	 * @return string
	 */
	private static function arrows() {
		return '<div class="aimp-slider-arrows">' .
			'<button type="button" class="aimp-slider-arrow" data-dir="-1" aria-label="' . esc_attr__( 'Previous', 'atelier-irisee-master-plugin' ) . '">' . AIMP_Blocks::icon( 'arrow-left' ) . '</button>' .
			'<button type="button" class="aimp-slider-arrow" data-dir="1" aria-label="' . esc_attr__( 'Next', 'atelier-irisee-master-plugin' ) . '">' . AIMP_Blocks::icon( 'arrow-right' ) . '</button></div>';
	}

	/* ------------------------------------------------------------------ shop by category */

	/**
	 * The overview tiles of the All products page (Design studio → Overview cards: the large tile, the order, the
	 * columns), with a title. Without the gift card tile: gift cards have their own menu item and promo block.
	 *
	 * @return string
	 */
	public static function categories() {
		$grid = AIMP_Shop::overview_html( false, array( 'giftcards' ) );
		if ( '' === $grid ) {
			return '';
		}
		self::assets();
		return '<section class="aimp-home-block aimp-home-cats">' . ( AIMP_Blocks::design( 'home-cats', 'hc_notitle' ) ? '' : self::title( AIMP_Blocks::text( 'hc_title' ) ) ) . $grid . '</section>';
	}

	/* ------------------------------------------------------------------ reviews and testimonials */

	/**
	 * Product reviews with at least the chosen number of stars: [ rating, text, name, product, date ].
	 *
	 * @param int $min   Stars.
	 * @param int $limit Number.
	 * @return array
	 */
	private static function product_reviews( $min, $limit ) {
		$list = array();
		foreach ( get_comments(
			array(
				'post_type' => 'product',
				'status'    => 'approve',
				'type'      => 'review',
				'number'    => 60,
			)
		) as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			$text   = trim( wp_strip_all_tags( $comment->comment_content ) );
			if ( $rating < $min || '' === $text ) {
				continue;
			}
			$list[] = array(
				'rating'  => $rating,
				'text'    => $text,
				'name'    => $comment->comment_author,
				'product' => (int) $comment->comment_post_ID,
				'date'    => $comment->comment_date,
			);
			if ( count( $list ) >= $limit ) {
				break;
			}
		}
		return $list;
	}

	/**
	 * The average of all approved product reviews: [ average, count ].
	 *
	 * @return array
	 */
	private static function review_average() {
		global $wpdb;
		$cached = wp_cache_get( 'aimp_review_average', 'aimp' );
		if ( false !== $cached ) {
			return $cached;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row    = $wpdb->get_row(
			"SELECT AVG(m.meta_value) AS average, COUNT(*) AS total FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = 'rating'
			INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID AND p.post_type = 'product'
			WHERE c.comment_approved = '1' AND c.comment_type = 'review' AND m.meta_value > 0"
		);
		$result = array( $row ? round( (float) $row->average, 1 ) : 0, $row ? (int) $row->total : 0 );
		wp_cache_set( 'aimp_review_average', $result, 'aimp', HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Five stars, filled up to the rating.
	 *
	 * @param float $rating 0–5.
	 * @return string
	 */
	private static function stars( $rating ) {
		$rating = max( 0, min( 5, (float) $rating ) );
		$row    = str_repeat( AIMP_Blocks::icon( 'star' ), 5 );
		// Outlined stars, and filled ones over them up to the rating.
		return '<span class="aimp-stars" role="img" aria-label="' .
			/* translators: %s: rating, e.g. 4.5 */
			esc_attr( sprintf( __( '%s out of 5 stars', 'atelier-irisee-master-plugin' ), AIMP_I18n::number( $rating, floor( $rating ) === $rating ? 0 : 1 ) ) ) . '">' .
			'<span class="aimp-stars-row">' . $row . '</span>' .
			'<span class="aimp-stars-row aimp-stars-fill" style="width:' . esc_attr( (string) round( $rating / 5 * 100, 1 ) ) . '%">' . $row . '</span></span>';
	}

	/**
	 * @return string '' when there is nothing to show.
	 */
	public static function reviews() {
		$part  = 'home-reviews';
		$min   = (int) AIMP_Blocks::design( $part, 'rv_min', 4 );
		$count = (int) AIMP_Blocks::design( $part, 'rv_count', 0 );
		$count = $count > 0 ? $count : 12;
		$items = array();
		foreach ( AIMP_Blocks::pairs( 'rv_quotes' ) as $quote ) {
			$items[] = array(
				'rating'  => 5,
				'text'    => $quote[0],
				'name'    => $quote[1],
				'product' => 0,
				'date'    => '',
				'own'     => true,
			);
		}
		if ( ! AIMP_Blocks::design( $part, 'rv_noproducts' ) ) {
			$items = array_merge( $items, self::product_reviews( max( 1, min( 5, $min ) ), $count ) );
		}
		$items = array_slice( $items, 0, $count );
		if ( ! $items ) {
			return '';
		}
		self::assets();
		list( $average, $total ) = self::review_average();

		$html = '<section class="aimp-home-block aimp-home-reviews" data-aimp-slider>' . self::title( AIMP_Blocks::text( 'rv_title' ) );
		// The average only when it is good news (as good as the reviews shown).
		if ( $total && $average >= $min && ! AIMP_Blocks::design( $part, 'rv_noaverage' ) ) {
			$html .= '<p class="aimp-reviews-average">' . self::stars( $average ) . ' <strong>' . esc_html( AIMP_I18n::number( $average, 1 ) ) . '/5</strong> · ' .
				/* translators: %d: number of reviews */
				esc_html( sprintf( _n( '%d review', '%d reviews', $total, 'atelier-irisee-master-plugin' ), $total ) ) . '</p>';
		}
		$html .= '<div class="aimp-slider-track" tabindex="0" role="region" aria-label="' . esc_attr( AIMP_Blocks::text( 'rv_title' ) ) . '"><ul class="aimp-reviews-list">';
		foreach ( $items as $item ) {
			$product = $item['product'] ? wc_get_product( $item['product'] ) : null;
			$html   .= '<li class="aimp-review' . ( empty( $item['own'] ) ? '' : ' aimp-review--own' ) . '"><figure>' . self::stars( $item['rating'] ) .
				'<blockquote class="aimp-review-text"><p>' . esc_html( wp_trim_words( $item['text'], 60 ) ) . '</p></blockquote>' .
				'<figcaption class="aimp-review-by"><strong>' . esc_html( $item['name'] ) . '</strong>';
			if ( $product && ! AIMP_Blocks::design( $part, 'rv_noproduct' ) ) {
				$html .= '<a class="aimp-review-product" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a>';
			}
			$html .= '</figcaption></figure></li>';
		}
		$html .= '</ul></div>' . ( count( $items ) > 1 ? self::arrows() : '' ) . '</section>';
		return $html;
	}

	/* ------------------------------------------------------------------ meet the maker */

	/**
	 * @return string
	 */
	public static function maker() {
		self::assets();
		$part    = 'home-maker';
		$picture = (int) AIMP_Blocks::design( $part, 'mk_img', 0 );
		$inset   = (int) AIMP_Blocks::design( $part, 'mk_img2', 0 );
		$fx      = (int) AIMP_Blocks::design( $part, 'mk_fx', -1 );
		$fy      = (int) AIMP_Blocks::design( $part, 'mk_fy', -1 );
		$url     = (string) AIMP_Blocks::design( $part, 'mk_url', '' );
		$button  = AIMP_Blocks::text( 'mk_button' );
		$style   = $fx >= 0 || $fy >= 0 ? ' style="object-position:' . ( $fx >= 0 ? $fx : 50 ) . '% ' . ( $fy >= 0 ? $fy : 50 ) . '%"' : '';

		$html = '<section class="aimp-home-block aimp-home-maker' . ( AIMP_Blocks::design( $part, 'mk_right' ) ? ' is-picture-right' : '' ) . ( $picture ? '' : ' has-no-picture' ) . '">';
		if ( $picture ) {
			$image = wp_get_attachment_image( $picture, 'large', false, array( 'class' => 'aimp-maker-image', 'loading' => 'lazy', 'alt' => '' ) );
			if ( '' !== $style ) {
				$image = preg_replace( '/^<img /', '<img' . $style . ' ', $image );
			}
			// The picture in a frame with the gold border, so moving or zooming it (Design studio) stays inside.
			$html .= '<div class="aimp-maker-media"><span class="aimp-maker-frame">' . $image . '</span>';
			$url2  = $inset ? wp_get_attachment_image_url( $inset, 'medium' ) : '';
			if ( $url2 ) {
				// The round picture (e.g. the mascot): a picture in its own round frame, with its own focal point. A real
				// <img>, so the Design studio's "Picture" control (replace, move, zoom) works on it too.
				$ix    = (int) AIMP_Blocks::design( $part, 'mk_fx2', -1 );
				$iy    = (int) AIMP_Blocks::design( $part, 'mk_fy2', -1 );
				$html .= '<span class="aimp-maker-inset"><img class="aimp-maker-inset-image" src="' . esc_url( $url2 ) . '" alt="" loading="lazy" style="object-position:' . ( $ix >= 0 ? $ix : 50 ) . '% ' . ( $iy >= 0 ? $iy : 50 ) . '%"></span>';
			}
			$html .= '</div>';
		}
		$html .= '<div class="aimp-maker-text"><p class="aimp-maker-kicker">' . esc_html( AIMP_Blocks::text( 'mk_kicker' ) ) . '</p>' .
			'<h2 class="aimp-block-title aimp-maker-title"><span>' . esc_html( AIMP_Blocks::text( 'mk_title' ) ) . '</span></h2>' .
			'<div class="aimp-maker-story">' . wpautop( esc_html( AIMP_Blocks::text( 'mk_text' ) ) ) . '</div>';
		if ( '' !== $url && '' !== $button ) {
			$html .= '<a class="aimp-block-button" href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a>';
		}
		return $html . '</div></section>';
	}

	/* ------------------------------------------------------------------ new in */

	/**
	 * @return string '' without products.
	 */
	public static function new_in() {
		$part  = 'home-new';
		$count = (int) AIMP_Blocks::design( $part, 'nw_count', 0 );
		$count = $count > 0 ? $count : 6;
		$type  = (string) AIMP_Blocks::design( $part, 'nw_type', '' );
		$type  = in_array( $type, array( 'fabrics', 'patterns', 'haberdashery' ), true ) ? $type : 'all';
		$roots = 'all' === $type ? array() : AIMP_Shop::roots( $type );
		if ( 'all' !== $type && ! $roots ) {
			return '';
		}
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => $count,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'tax_query'           => AIMP_Shop::scope_tax_query( $roots ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);
		if ( AIMP_Blocks::design( $part, 'nw_instock' ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_stock_status',
					'value'   => array( 'instock', 'onbackorder' ),
					'compare' => 'IN',
				),
			);
		}
		$cards = '';
		foreach ( ( new WP_Query( $args ) )->posts as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$cards .= AIMP_Product_Page::card_html( $product );
			}
		}
		if ( '' === $cards ) {
			return '';
		}
		if ( ! wp_style_is( 'aimp-configurator', 'registered' ) ) {
			AIMP_Shortcode::register_assets();
		}
		wp_enqueue_style( 'aimp-configurator' );
		self::assets();
		$url = self::url_or_page( (string) AIMP_Blocks::design( $part, 'nw_url', '' ), AIMP_Shop::page_for_type( $type ) );
		if ( '' !== $url && '' === (string) AIMP_Blocks::design( $part, 'nw_url', '' ) ) {
			$url = add_query_arg( 'aimp_sort', 'newest', $url );
		}
		return '<section class="aimp-home-block aimp-home-new aimp-configurator" data-aimp-slider>' .
			'<div class="aimp-home-head">' . self::title( AIMP_Blocks::text( 'nw_title' ) ) .
			( '' !== $url ? '<a class="aimp-home-more" href="' . esc_url( $url ) . '">' . esc_html( AIMP_Blocks::text( 'nw_button' ) ) . AIMP_Blocks::icon( 'arrow-right' ) . '</a>' : '' ) . '</div>' .
			'<div class="aimp-slider-track" tabindex="0" role="region" aria-label="' . esc_attr( AIMP_Blocks::text( 'nw_title' ) ) . '"><ul class="aimp-new-list">' . $cards . '</ul></div>' . self::arrows() . '</section>';
	}

	/* ------------------------------------------------------------------ newsletter */

	/**
	 * The footer's sign-up form (footer.js sends it and shows the visitor's state), with an invitation and the
	 * welcome discount for new accounts when that is on (Settings → Discounts).
	 *
	 * @return string
	 */
	public static function newsletter() {
		self::assets();
		if ( class_exists( 'AIMP_Footer' ) && ! wp_script_is( 'aimp-footer', 'enqueued' ) ) {
			AIMP_Footer::enqueue();
		}
		$part    = 'home-newsletter';
		$percent = (int) AIMP_Settings::get( 'welcome_discount' );
		$account = (int) get_option( 'woocommerce_myaccount_page_id' );
		$account = ! is_user_logged_in() && $percent && ! AIMP_Blocks::design( $part, 'hn_nooffer' ) ? self::url_or_page( '', $account ) : '';
		$picture = (int) AIMP_Blocks::design( $part, 'hn_img', 0 );
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy = $privacy && 'publish' === get_post_status( $privacy ) ? get_permalink( $privacy ) : '';
		$id      = wp_unique_id( 'aimp-home-nl-' );

		// Hidden for subscribers unless chosen otherwise (never in the studio's preview: it has to stay editable).
		$hide = ! AIMP_Blocks::design( $part, 'hn_showsubs' ) && ! ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() );
		$html = '<section class="aimp-home-block aimp-home-newsletter' . ( $picture ? ' has-picture' : '' ) . ( $hide ? ' hides-subscribed' : '' ) . '"><div class="aimp-home-nl-box">';
		if ( $picture ) {
			// Behind the texts, filling the gold frame.
			$html .= wp_get_attachment_image( $picture, 'large', false, array( 'class' => 'aimp-home-nl-image', 'loading' => 'lazy', 'alt' => '' ) );
		}
		$html .= '<div class="aimp-home-nl-main">' . self::title( AIMP_Blocks::text( 'hn_title' ) ) .
			'<p class="aimp-newsletter-intro">' . nl2br( esc_html( AIMP_Blocks::text( 'hn_text' ) ) ) . '</p>' .
			'<form class="aimp-home-nl-form" data-aimp-newsletter novalidate>' .
			'<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Email address', 'atelier-irisee-master-plugin' ) . '</label>' .
			'<input type="email" id="' . esc_attr( $id ) . '" name="email" required autocomplete="email" placeholder="' . esc_attr__( 'Your e-mail address', 'atelier-irisee-master-plugin' ) . '">' .
			'<input type="text" name="aimp_hp" value="" tabindex="-1" autocomplete="off" class="aimp-newsletter-hp" aria-hidden="true">' .
			'<button type="submit" class="aimp-block-button">' . esc_html__( 'Subscribe', 'atelier-irisee-master-plugin' ) . '</button>' .
			'<p class="aimp-newsletter-message" aria-live="polite" hidden></p></form>' .
			'<div class="aimp-newsletter-state" data-aimp-newsletter-state aria-live="polite" hidden><p class="aimp-newsletter-state-text"></p>' .
			'<div class="aimp-newsletter-state-actions">' .
			'<button type="button" class="aimp-block-button aimp-block-button--ghost" data-aimp-nl-resend hidden>' . esc_html__( 'Send the email again', 'atelier-irisee-master-plugin' ) . '</button>' .
			'<button type="button" class="aimp-block-button aimp-block-button--ghost" data-aimp-nl-unsubscribe>' . esc_html__( 'Unsubscribe', 'atelier-irisee-master-plugin' ) . '</button></div></div>';
		if ( '' !== $privacy ) {
			$html .= '<p class="aimp-newsletter-consent">' . sprintf(
				/* translators: %s: link to the privacy policy */
				esc_html__( 'You can unsubscribe at any time. Read our %s.', 'atelier-irisee-master-plugin' ),
				'<a href="' . esc_url( $privacy ) . '">' . esc_html__( 'privacy policy', 'atelier-irisee-master-plugin' ) . '</a>'
			) . '</p>';
		}
		$html .= '</div>';
		if ( '' !== $account ) {
			$html .= '<div class="aimp-home-nl-offer"><p class="aimp-home-nl-percent">' . esc_html( str_replace( '{percent}', (string) $percent, AIMP_Blocks::text( 'hn_offer' ) ) ) . '</p>' .
				'<p class="aimp-home-nl-offer-text">' . esc_html( AIMP_Blocks::text( 'hn_offer_text' ) ) . '</p>' .
				'<a class="aimp-block-button aimp-block-button--light" href="' . esc_url( $account ) . '">' . esc_html( AIMP_Blocks::text( 'hn_offer_button' ) ) . '</a></div>';
		}
		return $html . '</div></section>';
	}

	/* ------------------------------------------------------------------ questions and answers */

	/**
	 * @return string '' without questions.
	 */
	public static function faq() {
		$part  = 'home-faq';
		$count = (int) AIMP_Blocks::design( $part, 'fq_count', 0 );
		$pairs = array_slice( AIMP_Blocks::pairs( 'faq' ), 0, $count > 0 ? $count : 4 );
		if ( ! $pairs ) {
			return '';
		}
		self::assets();
		$url = (string) AIMP_Blocks::design( $part, 'fq_url', '' );
		if ( '' === $url ) {
			// The page with the contact page shortcode.
			$found = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					's'              => '[' . AIMP_Contact::TAG,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			$url   = $found ? get_permalink( $found[0] ) : '';
		}
		return '<section class="aimp-home-block aimp-home-faq" id="veelgestelde-vragen">' . self::title( AIMP_Blocks::text( 'fq_title' ) ) . AIMP_Blocks::faq_html( $pairs ) .
			( '' !== $url ? '<p class="aimp-home-faq-more"><a class="aimp-block-button" href="' . esc_url( $url ) . '">' . esc_html( AIMP_Blocks::text( 'fq_button' ) ) . '</a></p>' : '' ) . '</section>';
	}

	/* ------------------------------------------------------------------ gift card promo */

	/**
	 * The gift cards promoted, in one of three layouts (Design studio → Gift card promo, or layout="…"):
	 * banner (picture of a card next to a title, line and button), amounts (quick choices that open the gift card
	 * with that amount chosen) or designs (the card designs side by side, each opening the gift card in that design).
	 *
	 * @param array|string $atts layout.
	 * @return string '' without a published gift card product.
	 */
	public static function giftcard_promo( $atts = array() ) {
		if ( ! class_exists( 'AIMP_Giftcards' ) ) {
			return '';
		}
		$product_id = (int) AIMP_Giftcards::opt( 'product_id' );
		if ( ! $product_id || 'publish' !== get_post_status( $product_id ) ) {
			return '';
		}
		$part   = 'home-giftcards';
		$atts   = shortcode_atts( array( 'layout' => '' ), $atts, 'atelier_irisee_giftcard_promo' );
		$layout = sanitize_key( '' !== $atts['layout'] ? $atts['layout'] : (string) AIMP_Blocks::design( $part, 'gp_layout', '' ) );
		$layout = in_array( $layout, array( 'amounts', 'designs' ), true ) ? $layout : 'banner';
		$url    = get_permalink( $product_id );
		$designs = AIMP_Giftcards::designs();
		self::assets();

		// The picture: chosen in the studio, else the first design.
		$picture = (int) AIMP_Blocks::design( $part, 'gp_img', 0 );
		$src     = $picture ? (string) wp_get_attachment_image_url( $picture, 'large' ) : '';
		if ( '' === $src && $designs ) {
			$src = AIMP_Giftcards::design_image( (string) key( $designs ), 'large' );
		}
		$kicker = AIMP_Blocks::text( 'gp_kicker' );
		$title  = AIMP_Blocks::text( 'gp_title' );
		$text   = AIMP_Blocks::text( 'gp_text' );
		$button = '<a class="aimp-block-button" href="' . esc_url( $url ) . '">' . esc_html( AIMP_Blocks::text( 'gp_button' ) ) . '</a>';

		if ( 'designs' === $layout && $designs ) {
			$cards = '';
			foreach ( $designs as $id => $design ) {
				$cards .= '<li><a class="aimp-gp-design" href="' . esc_url( add_query_arg( 'aimp_gc_design', $id, $url ) ) . '"><img src="' . esc_url( AIMP_Giftcards::design_image( $id, 'woocommerce_thumbnail' ) ) . '" alt="" loading="lazy"><span>' . esc_html( $design['name'] ) . '</span></a></li>';
			}
			return '<section class="aimp-home-block aimp-home-giftcards is-designs" data-aimp-slider>' . self::title( $title ) .
				( '' !== $text ? '<p class="aimp-gp-text">' . esc_html( $text ) . '</p>' : '' ) .
				'<div class="aimp-slider-track" tabindex="0" role="region" aria-label="' . esc_attr( $title ) . '"><ul class="aimp-gp-designs">' . $cards . '</ul></div>' . self::arrows() .
				'<p class="aimp-gp-more">' . $button . '</p></section>';
		}

		$media = '' !== $src ? '<div class="aimp-gp-media"><img class="aimp-gp-card" src="' . esc_url( $src ) . '" alt="" loading="lazy"></div>' : '';
		$body  = ( '' !== $kicker ? '<p class="aimp-gp-kicker">' . esc_html( $kicker ) . '</p>' : '' ) .
			( '' !== $title ? '<h2 class="aimp-gp-title">' . esc_html( $title ) . '</h2>' : '' ) .
			( '' !== $text ? '<p class="aimp-gp-text">' . esc_html( $text ) . '</p>' : '' );
		if ( 'amounts' === $layout ) {
			$chips = '';
			foreach ( AIMP_Giftcards::preset_amounts() as $amount ) {
				$chips .= '<li><a class="aimp-gp-amount" href="' . esc_url( add_query_arg( 'aimp_gc_amount', $amount, $url ) ) . '">' . wp_kses_post( wc_price( $amount, array( 'decimals' => floor( $amount ) == $amount ? 0 : wc_get_price_decimals() ) ) ) . '</a></li>'; // phpcs:ignore Universal.Operators.StrictComparisons -- whole euros.
			}
			$body .= '<ul class="aimp-gp-amounts">' . $chips . '</ul><p class="aimp-gp-other"><a href="' . esc_url( $url ) . '">' . esc_html( AIMP_Blocks::text( 'gp_other' ) ) . AIMP_Blocks::icon( 'arrow-right' ) . '</a></p>';
		} else {
			$body .= '<p class="aimp-gp-actions">' . $button . '</p>';
		}
		return '<section class="aimp-home-block aimp-home-giftcards is-' . esc_attr( $layout ) . ( '' === $media ? ' has-no-picture' : '' ) . '">' . $media . '<div class="aimp-gp-body">' . $body . '</div></section>';
	}

	/* ------------------------------------------------------------------ Instagram */

	/**
	 * The newest Instagram posts (AIMP_Instagram: a Smash Balloon feed or our own token) in the site's style: a grid
	 * of pictures linking to their posts, a title and a "Follow" button. Set in the Design studio → Instagram; a
	 * page can differ with attributes: count, columns, columns_phone, shape (square|portrait|original), gap, radius,
	 * hover (zoom|caption|none), title="no", button="no", style (classic|polaroid|overlay|luxe|grid), and for the cards
	 * profile, date, actions and caption="no", and string="yes" (polaroids on a gold thread).
	 *
	 * @param array|string $atts Attributes.
	 * @return string '' without posts (nothing connected yet, or Instagram failed).
	 */
	public static function instagram( $atts = array() ) {
		$part = 'home-instagram';
		$atts = shortcode_atts(
			array(
				'count'         => '',
				'columns'       => '',
				'columns_phone' => '',
				'shape'         => '',
				'gap'           => '',
				'radius'        => '',
				'hover'         => '',
				'title'         => '',
				'button'        => '',
				'style'         => '',
				'profile'       => '',
				'date'          => '',
				'actions'       => '',
				'caption'       => '',
				'string'        => '',
			),
			$atts,
			'atelier_irisee_instagram'
		);
		// An attribute, else the studio's setting, else the standard.
		$num = function ( $att, $key, $min, $max, $standard ) use ( $atts, $part ) {
			$value = '' !== $atts[ $att ] ? (int) $atts[ $att ] : (int) AIMP_Blocks::design( $part, $key, -99 );
			return ( -99 === $value || $value < $min || ( 0 === $value && $min > 0 ) ) ? $standard : min( $max, $value );
		};
		$pick = function ( $att, $key, $allowed ) use ( $atts, $part ) {
			$value = '' !== $atts[ $att ] ? sanitize_key( $atts[ $att ] ) : (string) AIMP_Blocks::design( $part, $key, '' );
			return in_array( $value, $allowed, true ) ? $value : '';
		};
		$count  = $num( 'count', 'ig_count', 1, AIMP_Instagram::MAX, 6 );
		$items  = array_slice( AIMP_Instagram::items(), 0, $count );
		if ( ! $items ) {
			return '';
		}
		self::assets();
		// '' = the classic Instagram card; 'grid' = only the pictures.
		$style  = $pick( 'style', 'ig_style', array( 'polaroid', 'overlay', 'luxe', 'grid', 'classic' ) );
		$style  = 'classic' === $style ? '' : $style;
		if ( 'grid' !== $style ) {
			return self::instagram_cards( $atts, $style, $num, $pick, $items );
		}
		$cols   = $num( 'columns', 'ig_cols', 1, 8, min( 6, count( $items ) ) );
		$phone  = $num( 'columns_phone', 'ig_cols_phone', 1, 4, 3 );
		$gap    = $num( 'gap', 'ig_gap', 0, 40, -1 );
		$radius = $num( 'radius', 'ig_radius', 0, 30, -1 );
		$shape  = $pick( 'shape', 'ig_shape', array( 'portrait', 'original' ) );
		$hover  = $pick( 'hover', 'ig_hover', array( 'caption', 'none' ) );
		$narrow = 'narrow' === (string) AIMP_Blocks::design( $part, 'ig_width', '' );
		$icons  = ! AIMP_Blocks::design( $part, 'ig_noicon' );
		$title  = 'no' !== $atts['title'] && ! AIMP_Blocks::design( $part, 'ig_notitle' );
		$button = 'no' !== $atts['button'] && ! AIMP_Blocks::design( $part, 'ig_nobutton' );

		$user  = AIMP_Instagram::username();
		$cards = '';
		foreach ( $items as $item ) {
			$text    = trim( preg_replace( '/\s+/u', ' ', (string) $item['caption'] ) );
			$short   = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 120 ) : substr( $text, 0, 120 );
			$type    = isset( $item['type'] ) ? (string) $item['type'] : '';
			$mark    = '';
			if ( $icons && 'VIDEO' === $type ) {
				$mark = '<span class="aimp-ig-video" aria-hidden="true"></span>';
			} elseif ( $icons && 'CAROUSEL_ALBUM' === $type ) {
				$mark = '<span class="aimp-ig-album" aria-hidden="true"></span>';
			}
			$size    = ! empty( $item['width'] ) && ! empty( $item['height'] ) ? ' width="' . (int) $item['width'] . '" height="' . (int) $item['height'] . '"' : '';
			$cards  .= '<li><a class="aimp-ig-post" href="' . esc_url( $item['permalink'] ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $item['image'] ) . '" alt="' . esc_attr( $short ) . '"' . $size . ' loading="lazy">' . $mark .
				( 'caption' === $hover && '' !== $short ? '<span class="aimp-ig-caption">' . esc_html( $short . ( strlen( $text ) > strlen( $short ) ? '…' : '' ) ) . '</span>' : '' ) . '</a></li>';
		}
		$follow = $button && '' !== $user ? '<p class="aimp-ig-follow"><a class="aimp-block-button" href="' . esc_url( 'https://www.instagram.com/' . rawurlencode( $user ) . '/' ) . '" target="_blank" rel="noopener">' . esc_html( str_replace( '{account}', '@' . $user, AIMP_Blocks::text( 'ig_button' ) ) ) . '</a></p>' : '';

		$classes = array( 'aimp-home-block', 'aimp-home-instagram' );
		$classes[] = '' !== $shape ? 'is-' . $shape : 'is-square';
		$classes[] = '' !== $hover ? 'hover-' . $hover : 'hover-zoom';
		if ( $narrow ) {
			$classes[] = 'is-narrow';
		}
		$style = '--aimp-ig-cols:' . (int) $cols . ';--aimp-ig-cols-phone:' . (int) $phone . ';' .
			( $gap >= 0 ? '--aimp-ig-gap:' . (int) $gap . 'px;' : '' ) . ( $radius >= 0 ? '--aimp-ig-radius:' . (int) $radius . 'px;' : '' );
		return '<section class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '">' . ( $title ? self::title( AIMP_Blocks::text( 'ig_title' ) ) : '' ) .
			'<ul class="aimp-ig-grid">' . $cards . '</ul>' . $follow . '</section>';
	}

	/**
	 * The posts as Instagram post cards in the site's colours: profile picture and name, the picture, the icons
	 * (like, comment and save open the post on Instagram, which allows those only there; share uses the phone's
	 * share sheet or copies the link), the counts when Instagram gives them, and the start of the text.
	 *
	 * @param array    $atts  Shortcode attributes.
	 * @param string   $style '' (classic), polaroid, overlay or luxe.
	 * @param callable $num   Number setting.
	 * @param callable $pick  Choice setting.
	 * @param array    $items Posts.
	 * @return string
	 */
	private static function instagram_cards( $atts, $style, $num, $pick, $items ) {
		$part    = 'home-instagram';
		$show    = function ( $att, $key ) use ( $atts, $part ) {
			return 'no' !== $atts[ $att ] && ! AIMP_Blocks::design( $part, $key );
		};
		$cols    = $num( 'columns', 'ig_cols', 1, 8, min( 3, count( $items ) ) );
		$phone   = $num( 'columns_phone', 'ig_cols_phone', 1, 4, -1 ); // -1: a row to swipe.
		$gap     = $num( 'gap', 'ig_gap', 0, 40, -1 );
		$radius  = $num( 'radius', 'ig_radius', 0, 30, -1 );
		$shape   = $pick( 'shape', 'ig_shape', array( 'portrait', 'original' ) );
		$hover   = $pick( 'hover', 'ig_hover', array( 'caption', 'none' ) );
		$marks   = ! AIMP_Blocks::design( $part, 'ig_noicon' );
		$profile = $show( 'profile', 'ig_noprofile' ) && 'polaroid' !== $style;
		$dates   = $show( 'date', 'ig_nodate' );
		$actions = $show( 'actions', 'ig_noactions' );
		$caption = $show( 'caption', 'ig_nocaption' );
		// Polaroids on a gold thread, each with a clip (Design studio, or string="yes" | "no").
		$string  = 'polaroid' === $style && ( 'yes' === $atts['string'] || ( 'no' !== $atts['string'] && AIMP_Blocks::design( $part, 'ig_string' ) ) );
		$hang    = $string ? '<svg class="aimp-ig-string" viewBox="0 0 100 20" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path class="aimp-ig-string-guide" d="M0 3C25 5 40 12 50 14C60 12 75 5 100 3"/><path class="aimp-ig-string-sewn" d="M0 3C25 5 40 12 50 14C60 12 75 5 100 3"/></svg>' .
			'<svg class="aimp-ig-peg" viewBox="0 0 14 34" aria-hidden="true" focusable="false"><rect class="aimp-ig-peg-wood" x="1.5" y="1" width="11" height="32" rx="3"/><path class="aimp-ig-peg-split" d="M7 16v17"/><path class="aimp-ig-peg-shine" d="M4 5v7"/><circle class="aimp-ig-peg-spring" cx="7" cy="11" r="3"/></svg>' : '';
		$title   = $show( 'title', 'ig_notitle' );
		$button  = $show( 'button', 'ig_nobutton' );

		$user    = AIMP_Instagram::username();
		$account = '' !== $user ? 'https://www.instagram.com/' . rawurlencode( $user ) . '/' : '';
		$avatar  = AIMP_Instagram::avatar();
		$picture = '' !== $avatar
			? '<img class="aimp-ig-avatar" src="' . esc_url( $avatar ) . '" alt="" width="36" height="36" loading="lazy">'
			: '<span class="aimp-ig-avatar is-letter" aria-hidden="true">' . esc_html( strtoupper( substr( '' !== $user ? $user : 'A', 0, 1 ) ) ) . '</span>';
		$outside = ' target="_blank" rel="noopener"';
		$svg     = function ( $path ) {
			return '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
		};
		$icons = array(
			'like'    => $svg( 'M12 20.5s-7.5-4.6-9.2-9.4C1.6 7.6 3.8 4.5 7.1 4.5c2 0 3.6 1.1 4.9 2.9 1.3-1.8 2.9-2.9 4.9-2.9 3.3 0 5.5 3.1 4.3 6.6-1.7 4.8-9.2 9.4-9.2 9.4z' ),
			'comment' => $svg( 'M20.5 11.6a8.4 8.4 0 0 1-12.3 7.5L3.5 20.5l1.4-4.6a8.4 8.4 0 1 1 15.6-4.3z' ),
			'share'   => $svg( 'M21.5 2.5 10.6 13.4M21.5 2.5l-6.9 19-4-8.1-8.1-4z' ),
			'save'    => $svg( 'M18.5 21.5 12 15.4l-6.5 6.1V3.5h13z' ),
		);
		$year  = wp_date( 'Y' );
		$cards = '';
		foreach ( $items as $item ) {
			$link  = esc_url( $item['permalink'] );
			$text  = trim( preg_replace( '/\s+/u', ' ', (string) $item['caption'] ) );
			$limit = 'polaroid' === $style ? 60 : 110;
			$short = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
			$more  = strlen( $short ) < strlen( $text );
			$type  = isset( $item['type'] ) ? (string) $item['type'] : '';
			$mark  = '';
			if ( $marks && 'VIDEO' === $type ) {
				$mark = '<span class="aimp-ig-video" aria-hidden="true"></span>';
			} elseif ( $marks && 'CAROUSEL_ALBUM' === $type ) {
				$mark = '<span class="aimp-ig-album" aria-hidden="true"></span>';
			}
			$time = ! empty( $item['time'] ) ? (int) $item['time'] : 0;
			$date = $dates && $time ? '<time class="aimp-ig-date" datetime="' . esc_attr( gmdate( 'c', $time ) ) . '">' . esc_html( wp_date( wp_date( 'Y', $time ) === $year ? 'j M' : 'j M Y', $time ) ) . '</time>' : '';
			$size = ! empty( $item['width'] ) && ! empty( $item['height'] ) ? ' width="' . (int) $item['width'] . '" height="' . (int) $item['height'] . '"' : '';

			$html = '';
			if ( $profile && '' !== $user ) {
				$html .= '<header class="aimp-ig-head"><a class="aimp-ig-avatar-link" href="' . esc_url( $account ) . '"' . $outside . ' tabindex="-1" aria-hidden="true">' . $picture . '</a>' .
					'<span class="aimp-ig-who"><a class="aimp-ig-user" href="' . esc_url( $account ) . '"' . $outside . '>' . esc_html( $user ) . '</a>' . $date . '</span></header>';
			}
			$html .= '<a class="aimp-ig-post" href="' . $link . '"' . $outside . '><img src="' . esc_url( $item['image'] ) . '" alt="' . esc_attr( $short ) . '"' . $size . ' loading="lazy">' . $mark . '</a>';
			if ( $actions || ( $date && ! $profile ) ) {
				$html .= '<div class="aimp-ig-actions">';
				if ( $actions ) {
					$html .= '<a class="aimp-ig-action is-like" href="' . $link . '"' . $outside . ' title="' . esc_attr__( 'Like on Instagram', 'atelier-irisee-master-plugin' ) . '" aria-label="' . esc_attr__( 'Like on Instagram', 'atelier-irisee-master-plugin' ) . '">' . $icons['like'] . '</a>' .
						'<a class="aimp-ig-action is-comment" href="' . $link . '"' . $outside . ' title="' . esc_attr__( 'Comment on Instagram', 'atelier-irisee-master-plugin' ) . '" aria-label="' . esc_attr__( 'Comment on Instagram', 'atelier-irisee-master-plugin' ) . '">' . $icons['comment'] . '</a>' .
						'<button type="button" class="aimp-ig-action is-share" data-aimp-ig-share="' . esc_attr( $item['permalink'] ) . '" data-text="' . esc_attr( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 80 ) : substr( $text, 0, 80 ) ) . '" data-copied="' . esc_attr__( 'Link copied', 'atelier-irisee-master-plugin' ) . '" title="' . esc_attr__( 'Share', 'atelier-irisee-master-plugin' ) . '" aria-label="' . esc_attr__( 'Share', 'atelier-irisee-master-plugin' ) . '">' . $icons['share'] . '</button>';
				}
				// Without the header the date goes here (the polaroid: on the right).
				$html .= $date && ! $profile ? $date : '';
				if ( $actions ) {
					$html .= '<a class="aimp-ig-action is-save" href="' . $link . '"' . $outside . ' title="' . esc_attr__( 'Save on Instagram', 'atelier-irisee-master-plugin' ) . '" aria-label="' . esc_attr__( 'Save on Instagram', 'atelier-irisee-master-plugin' ) . '">' . $icons['save'] . '</a>';
				}
				$html .= '</div>';
			}
			$counts = array();
			if ( isset( $item['likes'] ) && $item['likes'] > 0 ) {
				$counts[] = sprintf( _n( '%s like', '%s likes', (int) $item['likes'], 'atelier-irisee-master-plugin' ), number_format_i18n( (int) $item['likes'] ) );
			}
			if ( isset( $item['comments'] ) && $item['comments'] > 0 ) {
				$counts[] = sprintf( _n( '%s comment', '%s comments', (int) $item['comments'], 'atelier-irisee-master-plugin' ), number_format_i18n( (int) $item['comments'] ) );
			}
			if ( $actions && $counts ) {
				$html .= '<p class="aimp-ig-counts">' . esc_html( implode( ' · ', $counts ) ) . '</p>';
			}
			if ( $caption && '' !== $short ) {
				$html .= '<p class="aimp-ig-text">' . ( $profile && '' !== $user && 'polaroid' !== $style ? '<a class="aimp-ig-user" href="' . esc_url( $account ) . '"' . $outside . ' tabindex="-1">' . esc_html( $user ) . '</a> ' : '' ) .
					esc_html( $short ) . ( $more ? '… <a class="aimp-ig-more" href="' . $link . '"' . $outside . '>' . esc_html__( 'more', 'atelier-irisee-master-plugin' ) . '</a>' : '' ) . '</p>';
			}
			$cards .= '<li>' . $hang . '<article class="aimp-ig-card">' . $html . '</article></li>';
		}
		$follow = $button && '' !== $user ? '<p class="aimp-ig-follow"><a class="aimp-block-button" href="' . esc_url( $account ) . '"' . $outside . '>' . esc_html( str_replace( '{account}', '@' . $user, AIMP_Blocks::text( 'ig_button' ) ) ) . '</a></p>' : '';

		$classes   = array( 'aimp-home-block', 'aimp-home-instagram', 'has-cards', 'style-' . ( '' !== $style ? $style : 'classic' ) );
		$classes[] = '' !== $shape ? 'is-' . $shape : 'is-square';
		$classes[] = 'none' === $hover ? 'hover-none' : 'hover-zoom';
		if ( 'narrow' === (string) AIMP_Blocks::design( $part, 'ig_width', '' ) ) {
			$classes[] = 'is-narrow';
		}
		if ( $phone < 1 ) {
			$classes[] = 'is-swipe';
		}
		if ( $string ) {
			$classes[] = 'has-string';
		}
		$css = '--aimp-ig-cols:' . (int) $cols . ';' . ( $phone > 0 ? '--aimp-ig-cols-phone:' . (int) $phone . ';' : '' ) .
			( $gap >= 0 ? '--aimp-ig-gap:' . (int) $gap . 'px;' : '' ) . ( $radius >= 0 ? '--aimp-ig-radius:' . (int) $radius . 'px;' : '' );
		return '<section class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $css ) . '">' . ( $title ? self::title( AIMP_Blocks::text( 'ig_title' ) ) : '' ) .
			'<ul class="aimp-ig-grid aimp-ig-cards">' . $cards . '</ul>' . $follow . '</section>';
	}

	/* ------------------------------------------------------------------ brands */

	/**
	 * @return string '' without brands.
	 */
	public static function brands() {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return '';
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_brand',
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		self::assets();
		$part  = 'home-brands';
		$names = (bool) AIMP_Blocks::design( $part, 'br_names' );
		$html  = '<section class="aimp-home-block aimp-home-brands' . ( AIMP_Blocks::design( $part, 'br_gray' ) ? ' is-gray' : '' ) . '">' . self::title( AIMP_Blocks::text( 'br_title' ) ) . '<ul class="aimp-brands-list">';
		foreach ( $terms as $term ) {
			$link = AIMP_Shop::brand_url( $term );
			$link = '' !== $link ? $link : get_term_link( $term );
			// A picture chosen in the studio, else the brand's own picture (Products → Brands).
			$logo = $names ? 0 : (int) AIMP_Blocks::design( $part, 'br_img_' . $term->term_id, 0 );
			$logo = $names || $logo ? $logo : (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			$html .= '<li class="aimp-brand"><a href="' . esc_url( is_wp_error( $link ) ? '' : $link ) . '">' .
				( $logo ? wp_get_attachment_image( $logo, 'medium', false, array( 'class' => 'aimp-brand-logo', 'loading' => 'lazy', 'alt' => $term->name ) ) : '<span class="aimp-brand-name">' . esc_html( $term->name ) . '</span>' ) .
				'</a></li>';
		}
		return $html . '</ul></section>';
	}
}
