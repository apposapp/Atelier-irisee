<?php
/**
 * Search engines: what the shop's own pages tell Google, next to All in One SEO (which keeps doing the rest).
 *
 * - Category and brand pages (/stoffen/boucle/, /alle-producten/merk/cousette/, AIMP_Shop): their own title,
 *   description, address (canonical), heading and breadcrumb; filtered shop pages are not listed (their links are).
 * - Structured data: the shop as an organisation (logo, social media, contact, address), the FAQ block as FAQPage,
 *   and on products the shipping costs (PostNL, per country) and the return policy (fabric cut to length cannot be
 *   returned).
 * - The page itself: one main heading (h1) on every page, no second one in a blog post, and Divi's own header and
 *   footer (replaced by the plugin's, hidden) no longer in the page.
 * - Not listed: the favourites page.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_SEO {

	/** @var array FAQ questions and answers shown on this page: [ question, answer ]. */
	private static $faq = array();

	/** @var array|null|false The title, description and address of this category or brand page (false: not worked out yet). */
	private static $term_meta = false;

	public static function init() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 999 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ), 10 );

		// All in One SEO.
		add_filter( 'aioseo_title', array( __CLASS__, 'title' ), 20 );
		add_filter( 'aioseo_description', array( __CLASS__, 'description' ), 20 );
		add_filter( 'aioseo_canonical_url', array( __CLASS__, 'canonical' ), 20 );
		add_filter( 'aioseo_facebook_tags', array( __CLASS__, 'social_tags' ), 20 );
		add_filter( 'aioseo_twitter_tags', array( __CLASS__, 'social_tags' ), 20 );
		add_filter( 'aioseo_schema_output', array( __CLASS__, 'schema' ), 20 );
		add_filter( 'aioseo_robots_meta', array( __CLASS__, 'aioseo_robots' ), 20 );
		// WooCommerce's "Uncategorized" category is not a page worth listing.
		add_filter( 'aioseo_sitemap_exclude_terms', array( __CLASS__, 'sitemap_exclude_terms' ), 20, 2 );
		// Without an SEO plugin: WordPress's own title and canonical.
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ), 20 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical' ), 20 );

		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'product_schema' ), 20, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'faq_schema' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 9998 );
		// Pictures without an alt text (image search, screen readers): the page's or the picture's own title.
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'image_alt' ), 20, 2 );
	}

	/**
	 * @param array   $attr       Picture attributes.
	 * @param WP_Post $attachment Picture.
	 * @return array
	 */
	public static function image_alt( $attr, $attachment ) {
		if ( isset( $attr['alt'] ) && '' !== trim( (string) $attr['alt'] ) ) {
			return $attr;
		}
		$parent = $attachment instanceof WP_Post && $attachment->post_parent ? get_post( $attachment->post_parent ) : null;
		$title  = $parent && in_array( $parent->post_type, array( 'product', 'post', 'page' ), true ) ? $parent->post_title : '';
		if ( '' === trim( (string) $title ) && $attachment instanceof WP_Post ) {
			// The picture's own title, unless it is only a file name (IMG_1234, 18115890466888906-l).
			$own   = (string) $attachment->post_title;
			$title = preg_match( '/^[\w-]*\d{3,}[\w-]*$/', $own ) ? '' : $own;
		}
		if ( '' !== trim( (string) $title ) ) {
			$attr['alt'] = wp_strip_all_tags( $title );
		}
		return $attr;
	}

	/* ------------------------------------------------------------------
	 * Category and brand pages
	 * ------------------------------------------------------------------ */

	/**
	 * Title, description and address of this category or brand page; null on other pages.
	 *
	 * @return array|null [ title (without the site name), description, url, term, kind ]
	 */
	public static function term_meta() {
		if ( false !== self::$term_meta ) {
			return self::$term_meta;
		}
		self::$term_meta = null;
		$own             = class_exists( 'AIMP_Shop' ) ? AIMP_Shop::request_term() : null;
		if ( ! $own || ! is_page( $own[2] ) ) {
			return null;
		}
		list( $term, $kind, $page_id ) = $own;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only the page number.
		$num   = isset( $_GET['aimp_page'] ) ? max( 1, absint( $_GET['aimp_page'] ) ) : 1;
		$name  = self::in_language( $term->name );
		$page  = self::in_language( wp_strip_all_tags( get_the_title( $page_id ) ) );
		$site  = get_bloginfo( 'name' );
		$count = (int) get_term_meta( $term->term_id, 'product_count_' . $term->taxonomy, true );
		$count = $count > 0 ? $count : (int) $term->count;

		/* translators: 1: category, 2: shop page (Fabrics) */
		$title = 'brand' === $kind ? sprintf( __( '%s: all products', 'atelier-irisee-master-plugin' ), $name ) : sprintf( __( '%1$s – %2$s', 'atelier-irisee-master-plugin' ), $name, $page );
		if ( $num > 1 ) {
			/* translators: %d: page number */
			$title .= ' – ' . sprintf( __( 'Page %d', 'atelier-irisee-master-plugin' ), $num );
		}
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( self::in_language( (string) $term->description ) ) ) );
		if ( '' === $text ) {
			if ( 'brand' === $kind ) {
				/* translators: 1: brand, 2: shop name, 3: number of products */
				$text = sprintf( _n( '%1$s at %2$s: %3$d product of %1$s, to order online.', '%1$s at %2$s: all %3$d products of %1$s, to order online.', max( 1, $count ), 'atelier-irisee-master-plugin' ), $name, $site, $count );
			} else {
				/* translators: 1: category, 2: shop name, 3: number of products, 4: shop page (Fabrics) */
				$text = sprintf( _n( '%1$s at %2$s: %3$d product in %4$s, to order online.', '%1$s at %2$s: discover %3$d products in %4$s, to order online.', max( 1, $count ), 'atelier-irisee-master-plugin' ), $name, $site, $count, $page );
			}
		}
		self::$term_meta = array( $title, self::shorten( $text, 158 ), AIMP_Shop::term_url( $term, $num ), $term, $kind );
		return self::$term_meta;
	}

	/**
	 * A Dutch text (a category's name) in the page's language.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function in_language( $text ) {
		return class_exists( 'AIMP_Content_I18n' ) ? AIMP_Content_I18n::translate( $text ) : $text;
	}

	/**
	 * A text cut at a word, at most $max characters.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function shorten( $text, $max ) {
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, $max - 1 );
		$pos = mb_strrpos( $cut, ' ' );
		return rtrim( false !== $pos ? mb_substr( $cut, 0, $pos ) : $cut, ' ,;:.–-' ) . '…';
	}

	/**
	 * @param string $title Title from All in One SEO.
	 * @return string
	 */
	public static function title( $title ) {
		$meta = self::term_meta();
		return $meta ? $meta[0] . ' – ' . get_bloginfo( 'name' ) : $title;
	}

	/**
	 * @param array $parts WordPress's title parts.
	 * @return array
	 */
	public static function title_parts( $parts ) {
		$meta = self::term_meta();
		if ( $meta ) {
			$parts['title'] = $meta[0];
		}
		return $parts;
	}

	/**
	 * @param string $description Description from All in One SEO.
	 * @return string
	 */
	public static function description( $description ) {
		$meta = self::term_meta();
		return $meta ? $meta[1] : self::description_in_language( $description );
	}

	/**
	 * A page's description on a French or English page: its translation (includes/languages/content-*.php), else made
	 * from the translated paragraphs of the product's or page's own text (All in One SEO makes the Dutch one from
	 * that same text); Dutch only when none of it is translated.
	 *
	 * @param string $description Dutch description.
	 * @return string
	 */
	private static function description_in_language( $description ) {
		if ( ! class_exists( 'AIMP_I18n' ) || AIMP_I18n::current() === AIMP_I18n::default_language() || '' === trim( (string) $description ) ) {
			return $description;
		}
		$own = self::in_language( $description );
		if ( $own !== $description || ! is_singular() ) {
			return $own;
		}
		static $made = array();
		$id = (int) get_queried_object_id();
		if ( isset( $made[ $id ] ) ) {
			return '' !== $made[ $id ] ? $made[ $id ] : $description;
		}
		$post   = get_post( $id );
		$source = $post ? (string) $post->post_excerpt : '';
		if ( $post && 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $id );
			$source  = $product ? (string) $product->get_short_description() : '';
			if ( '' === trim( wp_strip_all_tags( $source ) ) && $product ) {
				$source = (string) $product->get_description();
			}
		}
		if ( $post && '' === trim( wp_strip_all_tags( $source ) ) ) {
			// Divi's layout codes out, their content kept.
			$source = (string) preg_replace( '/\[\/?[a-z_]+[^\]]*\]/i', "\n\n", (string) $post->post_content );
		}
		$texts = array();
		foreach ( preg_split( '#</p>|<br\s*/?>|</h[1-6]>|</li>|\n\s*\n#i', wpautop( $source ) ) as $part ) {
			$plain = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $part ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' === $plain || ! preg_match( '/\pL{2}/u', $plain ) ) {
				continue;
			}
			$found = self::in_language( $plain );
			if ( $found !== $plain ) {
				$texts[] = $found;
			}
			if ( mb_strlen( implode( ' ', $texts ) ) > 160 ) {
				break;
			}
		}
		$made[ $id ] = $texts ? self::shorten( implode( ' ', $texts ), 158 ) : '';
		return '' !== $made[ $id ] ? $made[ $id ] : $description;
	}

	/**
	 * @param string $url Canonical address.
	 * @return string
	 */
	public static function canonical( $url ) {
		$meta = self::term_meta();
		return $meta && '' !== $meta[2] ? $meta[2] : $url;
	}

	/**
	 * Open Graph and Twitter: the category's own title, text and address.
	 *
	 * @param array $tags Tags.
	 * @return array
	 */
	public static function social_tags( $tags ) {
		$meta = self::term_meta();
		if ( ! is_array( $tags ) ) {
			return $tags;
		}
		$tags = self::share_image( $tags );
		// Other pages in French or English: the share texts in that language too.
		if ( ! $meta ) {
			foreach ( array( 'og:description', 'twitter:description' ) as $key ) {
				if ( ! empty( $tags[ $key ] ) && is_string( $tags[ $key ] ) ) {
					$tags[ $key ] = self::description_in_language( $tags[ $key ] );
				}
			}
			foreach ( array( 'og:title', 'twitter:title' ) as $key ) {
				if ( ! empty( $tags[ $key ] ) && is_string( $tags[ $key ] ) ) {
					$tags[ $key ] = self::in_language( $tags[ $key ] );
				}
			}
			return $tags;
		}
		foreach ( array( 'og:title', 'twitter:title' ) as $key ) {
			if ( isset( $tags[ $key ] ) ) {
				$tags[ $key ] = $meta[0];
			}
		}
		foreach ( array( 'og:description', 'twitter:description' ) as $key ) {
			if ( isset( $tags[ $key ] ) ) {
				$tags[ $key ] = $meta[1];
			}
		}
		if ( isset( $tags['og:url'] ) ) {
			$tags['og:url'] = $meta[2];
		}
		return $tags;
	}

	/**
	 * A share picture on every page (Facebook, WhatsApp, X): the page's own picture when All in One SEO found none,
	 * else the category's picture, else the home page's hero picture, else the logo.
	 *
	 * @param array $tags Tags.
	 * @return array
	 */
	private static function share_image( $tags ) {
		$has_og      = ! empty( $tags['og:image'] );
		$has_twitter = ! empty( $tags['twitter:image'] );
		if ( ( $has_og || ! array_key_exists( 'og:title', $tags ) ) && ( $has_twitter || ! array_key_exists( 'twitter:title', $tags ) ) ) {
			return $tags;
		}
		$id  = 0;
		$own = class_exists( 'AIMP_Shop' ) ? AIMP_Shop::request_term() : null;
		if ( $own ) {
			$id = (int) get_term_meta( $own[0]->term_id, 'thumbnail_id', true );
		} elseif ( is_singular() ) {
			$id = (int) get_post_thumbnail_id();
		} elseif ( is_tax() || is_category() ) {
			$id = (int) get_term_meta( get_queried_object_id(), 'thumbnail_id', true );
		}
		if ( ! $id ) {
			$id = class_exists( 'AIMP_Design' ) ? (int) AIMP_Design::opt( 'hero', 'h_img', 0 ) : 0;
			$id = $id ? $id : (int) AIMP_Settings::get( 'hero_image' );
		}
		$image = $id ? wp_get_attachment_image_src( $id, 'large' ) : false;
		$url   = $image ? (string) $image[0] : '';
		if ( '' === $url && class_exists( 'AIMP_Header' ) ) {
			$url = (string) AIMP_Header::logo_url();
		}
		if ( '' === $url ) {
			return $tags;
		}
		if ( ! $has_og && array_key_exists( 'og:title', $tags ) ) {
			$tags['og:image']            = $url;
			$tags['og:image:secure_url'] = $url;
			if ( $image ) {
				$tags['og:image:width']  = (int) $image[1];
				$tags['og:image:height'] = (int) $image[2];
			}
		}
		if ( ! $has_twitter && array_key_exists( 'twitter:title', $tags ) ) {
			$tags['twitter:image'] = $url;
			if ( isset( $tags['twitter:card'] ) && 'summary' === $tags['twitter:card'] && $image ) {
				$tags['twitter:card'] = 'summary_large_image';
			}
		}
		return $tags;
	}

	/**
	 * A category page is not sent to the shop page itself by WordPress's address check.
	 *
	 * @param string|false $redirect Address.
	 * @return string|false
	 */
	public static function redirect_canonical( $redirect ) {
		return class_exists( 'AIMP_Shop' ) && AIMP_Shop::request_term() ? false : $redirect;
	}

	/**
	 * Not listed: filtered shop pages (more than a category, brand or page number), the favourites page.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		$noindex = false;
		$favorites = class_exists( 'AIMP_Settings' ) ? (int) AIMP_Settings::get( 'favorites_page' ) : 0;
		if ( $favorites && is_page( $favorites ) ) {
			$noindex = true;
		}
		// The "Uncategorized" category page.
		$meta = self::term_meta();
		if ( $meta && (int) $meta[3]->term_id === (int) get_option( 'default_product_cat' ) ) {
			$noindex = true;
		}
		if ( is_page() && class_exists( 'AIMP_Shop' ) && self::is_shop_page() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only the names of the parameters.
			foreach ( array_keys( $_GET ) as $key ) {
				if ( 0 === strpos( (string) $key, 'aimp_' ) && ! in_array( $key, array( 'aimp_page', 'aimp_lang', 'aimp_all' ), true ) ) {
					$noindex = true;
				}
			}
		}
		if ( $noindex ) {
			unset( $robots['index'], $robots['max-image-preview'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Whether this page is not to be listed (robots()).
	 *
	 * @return bool
	 */
	private static function noindex() {
		$robots = self::robots( array() );
		return ! empty( $robots['noindex'] );
	}

	/**
	 * All in One SEO's robots tag (it writes its own, not WordPress's): noindex, follow where robots() says so.
	 *
	 * @param array $attributes Directives (name => value, '' when not set).
	 * @return array
	 */
	public static function aioseo_robots( $attributes ) {
		if ( ! is_array( $attributes ) || ! self::noindex() ) {
			return $attributes;
		}
		$attributes['noindex'] = 'noindex';
		if ( isset( $attributes['index'] ) ) {
			unset( $attributes['index'] );
		}
		if ( isset( $attributes['nofollow'] ) ) {
			$attributes['nofollow'] = '';
		}
		return $attributes;
	}

	/**
	 * @param int[]  $ids  Terms left out of the sitemap.
	 * @param string $type Taxonomy.
	 * @return int[]
	 */
	public static function sitemap_exclude_terms( $ids, $type = '' ) {
		$uncategorized = (int) get_option( 'default_product_cat' );
		// $type is the sitemap (general, rss), not a taxonomy: term ids are unique over all taxonomies.
		if ( ! $uncategorized ) {
			return $ids;
		}
		// Given back in the form it came (a list, or a comma separated text).
		if ( is_array( $ids ) ) {
			$ids[] = $uncategorized;
			return $ids;
		}
		return trim( (string) $ids . ',' . $uncategorized, ',' );
	}

	/**
	 * Whether this page is one of the shop pages (Patterns, Fabrics, Haberdashery, All products).
	 *
	 * @return bool
	 */
	private static function is_shop_page() {
		$id = (int) get_queried_object_id();
		foreach ( array( 'patterns', 'fabrics', 'haberdashery', 'all' ) as $type ) {
			if ( $id && AIMP_Shop::page_for_type( $type ) === $id ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * Structured data
	 * ------------------------------------------------------------------ */

	/**
	 * All in One SEO's structured data: the organisation's details, and a category page's own name and breadcrumb.
	 *
	 * @param array $graphs Graphs.
	 * @return array
	 */
	public static function schema( $graphs ) {
		if ( ! is_array( $graphs ) ) {
			return $graphs;
		}
		$meta = self::term_meta();
		foreach ( $graphs as $i => $graph ) {
			if ( ! is_array( $graph ) || empty( $graph['@type'] ) ) {
				continue;
			}
			$types = (array) $graph['@type'];
			if ( in_array( 'Organization', $types, true ) ) {
				$graphs[ $i ] = self::organization( $graph );
			}
			if ( $meta && array_intersect( array( 'WebPage', 'CollectionPage' ), $types ) ) {
				$graphs[ $i ]['name']        = $meta[0];
				$graphs[ $i ]['description'] = $meta[1];
				$graphs[ $i ]['url']         = $meta[2];
			}
			if ( $meta && in_array( 'BreadcrumbList', $types, true ) && isset( $graph['itemListElement'] ) && is_array( $graph['itemListElement'] ) ) {
				$items   = array_values( $graph['itemListElement'] );
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => count( $items ) + 1,
					'name'     => self::in_language( $meta[3]->name ),
					'item'     => $meta[2],
				);
				$graphs[ $i ]['itemListElement'] = $items;
			}
		}
		return $graphs;
	}

	/**
	 * The organisation: logo, social media, contact and address (Footer settings and WooCommerce's shop address).
	 *
	 * @param array $org Organisation.
	 * @return array
	 */
	private static function organization( $org ) {
		$logo = class_exists( 'AIMP_Header' ) ? AIMP_Header::logo_url() : '';
		if ( empty( $org['logo'] ) && '' !== $logo ) {
			$org['logo']  = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
			$org['image'] = $logo;
		}
		if ( class_exists( 'AIMP_Footer' ) ) {
			$same = array_values( array_filter( array_map( 'esc_url_raw', AIMP_Footer::socials() ) ) );
			if ( $same ) {
				$org['sameAs'] = array_values( array_unique( array_merge( isset( $org['sameAs'] ) ? (array) $org['sameAs'] : array(), $same ) ) );
			}
			$email = sanitize_email( (string) AIMP_Footer::get( 'email' ) );
			$phone = trim( (string) AIMP_Footer::get( 'phone' ) );
			if ( '' !== $email && empty( $org['email'] ) ) {
				$org['email'] = $email;
			}
			if ( '' !== $phone && empty( $org['telephone'] ) ) {
				$org['telephone'] = $phone;
			}
		}
		$street = trim( (string) get_option( 'woocommerce_store_address' ) . ' ' . (string) get_option( 'woocommerce_store_address_2' ) );
		$city   = (string) get_option( 'woocommerce_store_city' );
		if ( '' !== $street && '' !== $city && empty( $org['address'] ) ) {
			$country        = explode( ':', (string) get_option( 'woocommerce_default_country' ) );
			$org['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'addressLocality' => $city,
				'postalCode'      => (string) get_option( 'woocommerce_store_postcode' ),
				'addressCountry'  => $country[0],
			);
		}
		return $org;
	}

	/**
	 * A product's offer: the shipping costs per country (PostNL's rates, free from the set amount) and the return
	 * policy (fabric cut to length cannot be returned, as the Shipping & returns page says).
	 *
	 * @param array      $markup  Product data.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function product_schema( $markup, $product ) {
		if ( empty( $markup['offers'] ) || ! is_array( $markup['offers'] ) || ! $product instanceof WC_Product ) {
			return $markup;
		}
		$price    = (float) wc_get_price_to_display( $product );
		$shipping = self::shipping_details( $price );
		$returns  = self::return_policy( $product );
		$single   = isset( $markup['offers']['@type'] );
		$offers   = $single ? array( $markup['offers'] ) : $markup['offers'];
		foreach ( $offers as $i => $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			if ( $shipping && empty( $offer['shippingDetails'] ) ) {
				$offers[ $i ]['shippingDetails'] = $shipping;
			}
			if ( $returns && empty( $offer['hasMerchantReturnPolicy'] ) ) {
				$offers[ $i ]['hasMerchantReturnPolicy'] = $returns;
			}
		}
		$markup['offers'] = $single ? $offers[0] : $offers;
		return $markup;
	}

	/**
	 * Shipping to Belgium and the Netherlands for one product at this price.
	 *
	 * @param float $price Price.
	 * @return array
	 */
	private static function shipping_details( $price ) {
		if ( ! class_exists( 'AIMP_PostNL' ) || ! method_exists( 'AIMP_PostNL', 'amounts_for' ) ) {
			return array();
		}
		$days    = class_exists( 'AIMP_Settings' ) ? max( 1, (int) AIMP_Settings::get( 'delivery_days' ) ) : 3;
		$details = array();
		foreach ( array( 'BE', 'NL' ) as $country ) {
			$amounts = AIMP_PostNL::amounts_for( $country );
			if ( '' === $amounts['cost'] || ! is_numeric( $amounts['cost'] ) ) {
				continue;
			}
			$cost      = (float) $amounts['cost'];
			$free      = '' !== $amounts['free'] && is_numeric( $amounts['free'] ) && $price >= (float) $amounts['free'];
			$details[] = array(
				'@type'               => 'OfferShippingDetails',
				'shippingRate'        => array(
					'@type'    => 'MonetaryAmount',
					'value'    => $free ? 0 : round( $cost, 2 ),
					'currency' => get_woocommerce_currency(),
				),
				'shippingDestination' => array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => $country,
				),
				'deliveryTime'        => array(
					'@type'        => 'ShippingDeliveryTime',
					'handlingTime' => array(
						'@type'    => 'QuantitativeValue',
						'minValue' => 0,
						'maxValue' => 1,
						'unitCode' => 'DAY',
					),
					'transitTime'  => array(
						'@type'    => 'QuantitativeValue',
						'minValue' => 1,
						'maxValue' => max( 1, $days ),
						'unitCode' => 'DAY',
					),
				),
			);
		}
		return $details;
	}

	/**
	 * The return policy (Settings → days to return); fabric sold per 10 cm is cut to length and cannot be returned.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private static function return_policy( $product ) {
		$days = class_exists( 'AIMP_Settings' ) ? (int) AIMP_Settings::get( 'return_days' ) : 14;
		$cut  = class_exists( 'AIMP_Catalog' ) && method_exists( 'AIMP_Catalog', 'sold_per_10cm' ) && AIMP_Catalog::sold_per_10cm( $product );
		$base = array(
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => array( 'BE', 'NL' ),
		);
		if ( $cut || $days <= 0 ) {
			return $base + array( 'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted' );
		}
		return $base + array(
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
			'merchantReturnDays'   => $days,
			'returnMethod'         => 'https://schema.org/ReturnByMail',
		);
	}

	/**
	 * The FAQ block's questions (AIMP_Blocks::faq_html()), for FAQPage data at the end of the page.
	 *
	 * @param array[] $pairs [ question, answer ].
	 */
	public static function add_faq( $pairs ) {
		foreach ( (array) $pairs as $pair ) {
			if ( isset( $pair[0], $pair[1] ) && '' !== trim( (string) $pair[0] ) && '' !== trim( (string) $pair[1] ) ) {
				self::$faq[ md5( (string) $pair[0] ) ] = array( (string) $pair[0], (string) $pair[1] );
			}
		}
	}

	public static function faq_schema() {
		if ( ! self::$faq || is_admin() ) {
			return;
		}
		$items = array();
		foreach ( self::$faq as $pair ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => self::in_language( wp_strip_all_tags( $pair[0] ) ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => self::in_language( wp_strip_all_tags( $pair[1] ) ),
				),
			);
		}
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $items,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
		) . '</script>' . "\n";
	}

	/* ------------------------------------------------------------------
	 * The page itself: one main heading, no hidden Divi header and footer
	 * ------------------------------------------------------------------ */

	public static function start_buffer() {
		if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether the page is HTML.
		if ( isset( $_GET['wc-ajax'] ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'fix_html' ) );
	}

	/**
	 * @param string $html Page.
	 * @return string
	 */
	public static function fix_html( $html ) {
		if ( '' === $html || false === stripos( $html, '<body' ) ) {
			return $html;
		}
		// Divi's header and footer, replaced by the plugin's (body class aimp-replace-theme-*): empty, so their
		// menu, logo and "Designed by Elegant Themes" are no longer in the page (the empty element stays for Divi's scripts).
		if ( preg_match( '/<body[^>]*\baimp-replace-theme-header\b/', $html ) ) {
			$html = preg_replace( '#(<header\b[^>]*\bid=["\']main-header["\'][^>]*>).*?(</header>)#s', '$1$2', $html, 1 );
		}
		if ( preg_match( '/<body[^>]*\baimp-replace-theme-footer\b/', $html ) ) {
			$html = preg_replace( '#(<footer\b[^>]*\bid=["\']main-footer["\'][^>]*>).*?(</footer>)#s', '$1$2', $html, 1 );
		}
		// A category or brand page: its name in the page's main heading (the shop page's own heading, e.g. a Divi
		// heading "All products", is the shop page's).
		$meta = self::term_meta();
		if ( $meta ) {
			$name = esc_html( self::in_language( $meta[3]->name ) );
			$html = preg_replace_callback(
				'#(<h1\b[^>]*>)(.*?)(</h1>)#s',
				function ( $m ) use ( $name ) {
					return trim( wp_strip_all_tags( $m[2] ) ) === html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ? $m[0] : $m[1] . $name . $m[3];
				},
				$html,
				1
			);
		}
		// A blog post: the plugin's own title is the main heading; Divi's (hidden) title becomes a paragraph.
		if ( false !== strpos( $html, 'aimp-blog-post-title' ) ) {
			$html = preg_replace( '#<h1(\b[^>]*\bclass=["\'][^"\']*\bentry-title\b[^>]*)>(.*?)</h1>#s', '<p$1>$2</p>', $html );
		}
		// A page without a main heading (blog, a blog category, favourites): its title as one, for search engines and
		// screen readers.
		$body = stristr( $html, '<body' );
		if ( false !== $body && ! preg_match( '/<h1\b/i', $body ) ) {
			$title = self::page_heading();
			if ( '' !== $title ) {
				$h1   = '<h1 class="screen-reader-text">' . esc_html( self::in_language( $title ) ) . '</h1>';
				$done = 0;
				$html = preg_replace( '#(<div\b[^>]*\bid=["\']et-main-area["\'][^>]*>)#', '$1' . $h1, $html, 1, $done );
				if ( ! $done ) {
					$html = preg_replace( '#(<main\b[^>]*>)#', '$1' . $h1, $html, 1, $done );
				}
			}
		}
		return $html;
	}

	/**
	 * The title of this page for its main heading.
	 *
	 * @return string
	 */
	private static function page_heading() {
		if ( is_front_page() ) {
			return get_bloginfo( 'name' );
		}
		if ( is_home() ) {
			$id = (int) get_option( 'page_for_posts' );
			return $id ? wp_strip_all_tags( get_the_title( $id ) ) : get_bloginfo( 'name' );
		}
		if ( is_singular() ) {
			return wp_strip_all_tags( get_the_title( get_queried_object_id() ) );
		}
		if ( is_archive() ) {
			return wp_strip_all_tags( single_term_title( '', false ) ? single_term_title( '', false ) : get_the_archive_title() );
		}
		return '';
	}
}
