<?php
/**
 * Statistics: visits, pages, sources and searches (counted anonymously by assets/js/stats.js, also on cached
 * pages), the customizer's steps, favorites and saved kits, and sales, customers and sign-ups from the shop's
 * own data. Shown under Atelier Irisee → Statistics.
 *
 * Counting is anonymous: no cookie, no IP address is kept. A visitor is recognised for one day only, by a hash of
 * the IP address and browser with a random salt of that day; those hashes are deleted after two days. Counters are
 * kept per day ({prefix}aimp_stats: day, kind, item, hits) for 25 months.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Stats {

	const PAGE       = 'aimp-stats';
	const DB_VERSION = '1';
	const CRON       = 'aimp_stats_cleanup';
	const BOTS       = '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|preview|headless|lighthouse|pingdom|gtmetrix|uptime|monitor|curl|wget|python|java\/|go-http|okhttp|axios|scrapy|semrush|ahrefs|bingpreview|petal|yandex|baidu/i';

	/** The steps of the customizer that are counted, in order. */
	const STEPS = array( 'cz_start', 'cz_pattern', 'cz_size', 'cz_fabric', 'cz_cart' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'install' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 5 );
		add_action( 'wc_ajax_aimp_hit', array( __CLASS__, 'hit' ) );
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 30 );
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aimp_stats';
	}

	private static function seen_table() {
		global $wpdb;
		return $wpdb->prefix . 'aimp_stats_seen';
	}

	/**
	 * The tables (once per version) and the daily clean-up.
	 */
	public static function install() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
		if ( self::DB_VERSION === get_option( 'aimp_stats_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
				day date NOT NULL,
				kind varchar(20) NOT NULL,
				item varchar(190) NOT NULL DEFAULT '',
				hits int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,kind,item),
				KEY kind_day (kind,day)
			) $charset;"
		);
		dbDelta(
			'CREATE TABLE ' . self::seen_table() . " (
				day date NOT NULL,
				hash char(16) NOT NULL,
				PRIMARY KEY  (day,hash)
			) $charset;"
		);
		update_option( 'aimp_stats_db', self::DB_VERSION, true );
		if ( ! get_option( 'aimp_stats_since' ) ) {
			update_option( 'aimp_stats_since', current_time( 'Y-m-d' ), false );
		}
	}

	/** Counters older than 25 months and the visitor hashes of two days ago go. */
	public static function cleanup() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE day < %s', gmdate( 'Y-m-d', strtotime( '-25 months', current_time( 'timestamp' ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::seen_table() . ' WHERE day < %s', gmdate( 'Y-m-d', current_time( 'timestamp' ) - DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable
	}

	/** The shop's own people are not counted. */
	private static function is_staff() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_posts' );
	}

	public static function enqueue() {
		if ( is_admin() || self::is_staff() || ( class_exists( 'AIMP_Design' ) && AIMP_Design::is_preview() ) ) {
			return;
		}
		wp_enqueue_script( 'aimp-stats', AIMP_PLUGIN_URL . 'assets/js/stats.js', array(), AIMP_VERSION, true );
		wp_localize_script( 'aimp-stats', 'aimpStatsConfig', array( 'endpoint' => WC_AJAX::get_endpoint( 'aimp_hit' ) ) );
	}

	/**
	 * Add one to a counter of today.
	 *
	 * @param string $kind Kind (view, product, search, cz_pattern, fav, …).
	 * @param string $item Item ('' for a total).
	 */
	public static function count( $kind, $item = '' ) {
		if ( self::DB_VERSION !== get_option( 'aimp_stats_db' ) ) {
			return;
		}
		global $wpdb;
		$item = function_exists( 'mb_substr' ) ? mb_substr( (string) $item, 0, 190 ) : substr( (string) $item, 0, 190 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table() . ' (day, kind, item, hits) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', current_time( 'Y-m-d' ), $kind, $item ) );
	}

	/**
	 * Whether this visitor is new today (and remember them for today only).
	 *
	 * @return bool
	 */
	private static function new_visitor() {
		$today = current_time( 'Y-m-d' );
		$salt  = get_option( 'aimp_stats_salt' );
		if ( ! is_array( $salt ) || ! isset( $salt['day'], $salt['salt'] ) || $salt['day'] !== $today ) {
			$salt = array(
				'day'  => $today,
				'salt' => wp_generate_password( 32, true, true ),
			);
			update_option( 'aimp_stats_salt', $salt, false );
		}
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$hash = substr( hash( 'sha256', $salt['salt'] . '|' . $ip . '|' . $ua ), 0, 16 );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return 1 === (int) $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::seen_table() . ' (day, hash) VALUES (%s, %s)', $today, $hash ) );
	}

	/**
	 * Where a visit came from: Google, Instagram, … , another site's address, or "direct".
	 *
	 * @param string $host Referrer host ('' = typed in or a bookmark).
	 * @return string
	 */
	public static function source( $host ) {
		$host = strtolower( preg_replace( '/^www\./', '', $host ) );
		$known = array(
			'google'    => 'Google',
			'bing'      => 'Bing',
			'duckduckgo' => 'DuckDuckGo',
			'ecosia'    => 'Ecosia',
			'instagram' => 'Instagram',
			'facebook'  => 'Facebook',
			'fb.'       => 'Facebook',
			'pinterest' => 'Pinterest',
			'tiktok'    => 'TikTok',
			'youtube'   => 'YouTube',
			'chatgpt'   => 'ChatGPT',
		);
		if ( '' === $host ) {
			return 'direct';
		}
		foreach ( $known as $needle => $name ) {
			if ( false !== strpos( $host, $needle ) ) {
				return $name;
			}
		}
		return substr( $host, 0, 80 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- anonymous counters from public pages.

	private static function param( $name, $length = 190 ) {
		return isset( $_POST[ $name ] ) ? substr( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ), 0, $length ) : '';
	}

	/**
	 * ?wc-ajax=aimp_hit: a page view (k=view) or an event of the customizer, the search or the favorites.
	 */
	public static function hit() {
		nocache_headers();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( '' === $ua || preg_match( self::BOTS, $ua ) || self::is_staff() ) {
			status_header( 204 );
			exit;
		}
		$kind = sanitize_key( self::param( 'k', 20 ) );
		$item = self::param( 'i' );

		if ( 'view' === $kind && '' !== $item && '/' === $item[0] ) {
			self::count( 'pv' );
			self::count( 'view', $item );
			$product = absint( self::param( 'p', 12 ) );
			if ( $product && 'product' === get_post_type( $product ) ) {
				self::count( 'product', (string) $product );
			}
			$referrer = sanitize_text_field( self::param( 'r', 120 ) );
			if ( self::new_visitor() ) {
				self::count( 'uv' );
				self::count( 'ref', self::source( 'self' === $referrer ? '' : $referrer ) );
				self::count( 'lang', AIMP_I18n::current() );
				$device = self::param( 'd', 10 );
				self::count( 'device', in_array( $device, array( 'phone', 'tablet', 'computer' ), true ) ? $device : 'computer' );
			}
			// WordPress's own search page (?s=).
			$term = self::param( 's', 80 );
			if ( '' !== $term ) {
				self::search( $term, '1' === self::param( 'n', 1 ) ? 0 : -1 );
			}
		} elseif ( 'search' === $kind && '' !== $item ) {
			self::search( $item, (int) self::param( 'x', 6 ) );
		} elseif ( 'cz' === $kind && in_array( 'cz_' . $item, self::STEPS, true ) ) {
			self::count( 'cz_' . $item, 'cz_start' === 'cz_' . $item ? '' : self::param( 'x', 120 ) );
		}
		status_header( 204 );
		exit;
	}

	// phpcs:enable

	/**
	 * A search: its words (lowercase), and the ones that found nothing apart.
	 *
	 * @param string $term  Words.
	 * @param int    $found Results (-1 = not known).
	 */
	public static function search( $term, $found = -1 ) {
		$term = trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( $term ) : strtolower( $term ) );
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term ) ) < 2 ) {
			return;
		}
		self::count( 'search', $term );
		if ( 0 === $found ) {
			self::count( 'search_none', $term );
		}
	}

	/* ------------------------------------------------------------------
	 * Reading the counters
	 * ------------------------------------------------------------------ */

	/**
	 * Items of a kind in a period, most first: item => hits.
	 *
	 * @param string $kind  Kind.
	 * @param string $from  Y-m-d.
	 * @param string $to    Y-m-d.
	 * @param int    $limit Number of items.
	 * @return array
	 */
	private static function items( $kind, $from, $to, $limit = 15 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT item, SUM(hits) AS n FROM ' . self::table() . ' WHERE kind = %s AND day BETWEEN %s AND %s GROUP BY item ORDER BY n DESC LIMIT %d', $kind, $from, $to, $limit ), ARRAY_A );
		$list = array();
		foreach ( (array) $rows as $row ) {
			$list[ $row['item'] ] = (int) $row['n'];
		}
		return $list;
	}

	private static function total( $kind, $from, $to ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(hits) FROM ' . self::table() . ' WHERE kind = %s AND day BETWEEN %s AND %s', $kind, $from, $to ) );
	}

	/**
	 * Page views and visitors per day (per week for a long period): [ [ label, views, visitors ] ].
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array
	 */
	private static function series( $from, $to ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT day, kind, SUM(hits) AS n FROM ' . self::table() . " WHERE kind IN ('pv','uv') AND day BETWEEN %s AND %s GROUP BY day, kind", $from, $to ), ARRAY_A );
		$by    = array();
		foreach ( (array) $rows as $row ) {
			$by[ $row['day'] ][ $row['kind'] ] = (int) $row['n'];
		}
		$days   = (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
		$weekly = $days > 100;
		$series = array();
		for ( $t = strtotime( $from ); $t <= strtotime( $to ); $t += DAY_IN_SECONDS ) {
			$day = gmdate( 'Y-m-d', $t );
			$key = $weekly ? gmdate( 'o-W', $t ) : $day;
			if ( ! isset( $series[ $key ] ) ) {
				$series[ $key ] = array( wp_date( $weekly ? 'j M' : ( $days > 10 ? 'j M' : 'D j' ), $t ), 0, 0 );
			}
			$series[ $key ][1] += isset( $by[ $day ]['pv'] ) ? $by[ $day ]['pv'] : 0;
			$series[ $key ][2] += isset( $by[ $day ]['uv'] ) ? $by[ $day ]['uv'] : 0;
		}
		return array_values( $series );
	}

	/* ------------------------------------------------------------------
	 * The shop's own data
	 * ------------------------------------------------------------------ */

	/**
	 * Paid orders of a period: totals, best sellers, kits, codes and gift cards (kept for an hour).
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array
	 */
	private static function sales( $from, $to ) {
		$key  = 'aimp_stats_sales_' . md5( $from . $to );
		$data = get_transient( $key );
		if ( is_array( $data ) ) {
			return $data;
		}
		$data   = array(
			'orders'     => 0,
			'revenue'    => 0.0,
			'products'   => array(),
			'categories' => array(),
			'kits'       => 0,
			'kit_revenue' => 0.0,
			'single'     => 0,
			'codes'      => array(),
			'welcome'    => 0,
			'giftcards'  => 0,
		);
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'type'         => 'shop_order',
				'status'       => wc_get_is_paid_statuses(),
				'date_created' => ( new DateTime( $from . ' 00:00:00', wp_timezone() ) )->getTimestamp() . '...' . ( new DateTime( $to . ' 23:59:59', wp_timezone() ) )->getTimestamp(),
			)
		);
		$roots = array();
		foreach ( $orders as $order ) {
			++$data['orders'];
			$data['revenue'] += (float) $order->get_total();
			$kits             = array();
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$id    = $item->get_product_id();
				$total = (float) $item->get_total() + (float) $item->get_total_tax();
				$group = (string) $item->get_meta( '_aimp_group' );
				if ( '' !== $group ) {
					$kits[ $group ]       = true;
					$data['kit_revenue'] += $total;
				} else {
					++$data['single'];
				}
				if ( ! isset( $data['products'][ $id ] ) ) {
					$data['products'][ $id ] = array( 'qty' => 0, 'total' => 0.0 );
				}
				$data['products'][ $id ]['qty']   += (int) $item->get_quantity();
				$data['products'][ $id ]['total'] += $total;
				if ( ! isset( $roots[ $id ] ) ) {
					$roots[ $id ] = self::root_category( $id );
				}
				$cat = $roots[ $id ];
				$data['categories'][ $cat ] = ( isset( $data['categories'][ $cat ] ) ? $data['categories'][ $cat ] : 0 ) + $total;
				$product = $item->get_product();
				if ( $product && class_exists( 'AIMP_Giftcards_Product' ) && AIMP_Giftcards_Product::is_giftcard( $product ) ) {
					$data['giftcards'] += (int) $item->get_quantity();
				}
			}
			$data['kits'] += count( $kits );
			foreach ( $order->get_coupon_codes() as $code ) {
				$code                  = strtoupper( $code );
				$data['codes'][ $code ] = ( isset( $data['codes'][ $code ] ) ? $data['codes'][ $code ] : 0 ) + 1;
				if ( 0 === strpos( $code, 'WELCOME-' ) ) {
					++$data['welcome'];
				}
			}
		}
		uasort(
			$data['products'],
			function ( $a, $b ) {
				return $b['qty'] <=> $a['qty'];
			}
		);
		arsort( $data['categories'] );
		arsort( $data['codes'] );
		set_transient( $key, $data, HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * The top category of a product (Fabrics, Patterns, …).
	 *
	 * @param int $id Product.
	 * @return string
	 */
	private static function root_category( $id ) {
		$terms = get_the_terms( $id, 'product_cat' );
		if ( ! is_array( $terms ) || ! $terms ) {
			return __( 'Other', 'atelier-irisee-master-plugin' );
		}
		$term = $terms[0];
		while ( $term->parent ) {
			$parent = get_term( $term->parent, 'product_cat' );
			if ( ! $parent || is_wp_error( $parent ) ) {
				break;
			}
			$term = $parent;
		}
		return wp_specialchars_decode( $term->name, ENT_QUOTES );
	}

	/**
	 * Every customer's favorites (stored with logged-in customers): product ID => customers.
	 *
	 * @return array
	 */
	private static function favorites_all() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", AIMP_Favorites::META ) );
		$count  = array();
		foreach ( $values as $value ) {
			foreach ( array_unique( array_map( 'absint', (array) maybe_unserialize( $value ) ) ) as $id ) {
				if ( $id ) {
					$count[ $id ] = ( isset( $count[ $id ] ) ? $count[ $id ] : 0 ) + 1;
				}
			}
		}
		arsort( $count );
		return $count;
	}

	/**
	 * Every saved sewing kit: [ patterns => [ pattern ID => kits ], fabrics => [ fabric ID => kits ], total ].
	 *
	 * @return array
	 */
	private static function saved_kits() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", AIMP_Saved_Kits::META ) );
		$out    = array(
			'patterns' => array(),
			'fabrics'  => array(),
			'total'    => 0,
		);
		foreach ( $values as $value ) {
			foreach ( (array) maybe_unserialize( $value ) as $kit ) {
				if ( ! is_array( $kit ) || empty( $kit['variation'] ) ) {
					continue;
				}
				++$out['total'];
				$pattern                      = (int) wp_get_post_parent_id( (int) $kit['variation'] );
				$pattern                      = $pattern ? $pattern : (int) $kit['variation'];
				$out['patterns'][ $pattern ]  = ( isset( $out['patterns'][ $pattern ] ) ? $out['patterns'][ $pattern ] : 0 ) + 1;
				if ( ! empty( $kit['fabric'] ) ) {
					$fabric                     = (int) $kit['fabric'];
					$out['fabrics'][ $fabric ] = ( isset( $out['fabrics'][ $fabric ] ) ? $out['fabrics'][ $fabric ] : 0 ) + 1;
				}
			}
		}
		arsort( $out['patterns'] );
		arsort( $out['fabrics'] );
		return $out;
	}

	/**
	 * New accounts, newsletter subscribers and back-in-stock requests.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array
	 */
	private static function people( $from, $to ) {
		$range    = array(
			array(
				'after'     => $from . ' 00:00:00',
				'before'    => $to . ' 23:59:59',
				'inclusive' => true,
			),
		);
		$accounts = new WP_User_Query(
			array(
				'role__in'    => array( 'customer' ),
				'date_query'  => $range,
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);
		$all      = new WP_User_Query(
			array(
				'role__in'    => array( 'customer' ),
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);
		$subs     = new WP_Query(
			array(
				'post_type'      => AIMP_Footer::SUBSCRIBERS,
				'post_status'    => 'any',
				'date_query'     => $range,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		$alerts   = array();
		foreach ( get_posts(
			array(
				'post_type'      => AIMP_Stock_Alerts::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		) as $alert ) {
			$product = (int) get_post_meta( $alert, '_aimp_product', true );
			if ( $product ) {
				$alerts[ $product ] = ( isset( $alerts[ $product ] ) ? $alerts[ $product ] : 0 ) + 1;
			}
		}
		arsort( $alerts );
		return array(
			'accounts'     => (int) $accounts->get_total(),
			'customers'    => (int) $all->get_total(),
			'subscribers'  => count( AIMP_Footer::confirmed_subscriber_ids() ),
			'new_subs'     => (int) $subs->found_posts,
			'alerts'       => $alerts,
			'alerts_total' => array_sum( $alerts ),
		);
	}

	/* ------------------------------------------------------------------
	 * The page
	 * ------------------------------------------------------------------ */

	public static function add_page() {
		$hook = add_submenu_page( AIMP_Settings::PAGE, __( 'Statistics', 'atelier-irisee-master-plugin' ), __( 'Statistics', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render' ) );
		add_action(
			'admin_print_styles-' . $hook,
			function () {
				wp_enqueue_style( 'aimp-admin-stats', AIMP_PLUGIN_URL . 'assets/css/admin-stats.css', array(), AIMP_VERSION );
			}
		);
	}

	/**
	 * A product's or pattern's name, or "#ID" when it is gone.
	 *
	 * @param int $id Product.
	 * @return string
	 */
	private static function product_name( $id ) {
		$product = wc_get_product( (int) $id );
		return $product ? wp_strip_all_tags( $product->get_name() ) : '#' . (int) $id;
	}

	/**
	 * The name of a counted page address ("/stoffen/?aimp_cat=35" → "Stoffen · Denim").
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function page_name( $path ) {
		if ( '/' === $path ) {
			return __( 'Home', 'atelier-irisee-master-plugin' );
		}
		$parts = explode( '?', $path, 2 );
		$id    = url_to_postid( home_url( $parts[0] ) );
		$name  = $id ? wp_strip_all_tags( get_the_title( $id ) ) : $parts[0];
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
			if ( ! empty( $query['aimp_cat'] ) ) {
				$term  = get_term( absint( $query['aimp_cat'] ), 'product_cat' );
				$name .= $term && ! is_wp_error( $term ) ? ' · ' . wp_specialchars_decode( $term->name, ENT_QUOTES ) : '';
			} elseif ( ! empty( $query['aimp_brand'] ) ) {
				$name .= ' · ' . sanitize_text_field( $query['aimp_brand'] );
			}
		}
		return '' !== $name ? $name : $path;
	}

	/**
	 * A key number with the change against the period before.
	 *
	 * @param string $label Label.
	 * @param string $value Shown value.
	 * @param float  $now   This period.
	 * @param float  $then  The period before (null: no comparison).
	 * @return string
	 */
	private static function tile( $label, $value, $now = 0, $then = null ) {
		$delta = '';
		if ( null !== $then && $then > 0 ) {
			$change = ( $now - $then ) / $then * 100;
			$delta  = sprintf( '<span class="aimp-stats-delta %s">%s%s%%</span>', $change >= 0 ? 'is-up' : 'is-down', $change >= 0 ? '+' : '−', esc_html( AIMP_I18n::number( abs( $change ), 0 ) ) );
		}
		return '<div class="aimp-stats-tile"><span class="aimp-stats-label">' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong>' . $delta . '</div>';
	}

	/**
	 * A ranked list with bars: rows of [ name, number shown, number for the bar ].
	 *
	 * @param string $title Title.
	 * @param array  $rows  Rows.
	 * @param string $empty Text when empty.
	 * @return string
	 */
	private static function ranking( $title, $rows, $empty = '' ) {
		$html = '<section class="aimp-stats-box"><h3>' . esc_html( $title ) . '</h3>';
		if ( ! $rows ) {
			return $html . '<p class="aimp-stats-empty">' . esc_html( '' !== $empty ? $empty : __( 'Nothing yet in this period.', 'atelier-irisee-master-plugin' ) ) . '</p></section>';
		}
		$max   = max( 1, max( array_column( $rows, 2 ) ) );
		$html .= '<ol class="aimp-stats-list">';
		foreach ( $rows as $row ) {
			$html .= sprintf(
				'<li><span class="aimp-stats-name">%1$s</span><span class="aimp-stats-num">%2$s</span><span class="aimp-stats-bar" style="width:%3$.1f%%"></span></li>',
				esc_html( $row[0] ),
				esc_html( $row[1] ),
				100 * $row[2] / $max
			);
		}
		return $html . '</ol></section>';
	}

	/**
	 * Counters as ranking rows, with a function for their names.
	 *
	 * @param array    $counts Item => number.
	 * @param callable $name   Name of an item.
	 * @param int      $limit  Rows.
	 * @return array
	 */
	private static function rows( $counts, $name, $limit = 15 ) {
		$rows = array();
		foreach ( array_slice( $counts, 0, $limit, true ) as $item => $n ) {
			$rows[] = array( call_user_func( $name, $item ), AIMP_I18n::number( $n ), $n );
		}
		return $rows;
	}

	/**
	 * Page views and visitors as an SVG line chart.
	 *
	 * @param array $series [ [ label, views, visitors ] ].
	 * @return string
	 */
	private static function chart( $series ) {
		$w     = 900;
		$h     = 220;
		$pad   = array( 34, 12, 28, 40 ); // top, right, bottom, left.
		$count = count( $series );
		$max   = max( 1, max( array_column( $series, 1 ) ) );
		$step  = $max > 10 ? pow( 10, floor( log10( $max ) ) ) : 1;
		$top   = ceil( $max / $step ) * $step;
		$x     = function ( $i ) use ( $w, $pad, $count ) {
			return $pad[3] + ( $count > 1 ? $i * ( $w - $pad[1] - $pad[3] ) / ( $count - 1 ) : 0 );
		};
		$y     = function ( $v ) use ( $h, $pad, $top ) {
			return $h - $pad[2] - $v / $top * ( $h - $pad[0] - $pad[2] );
		};
		$svg   = '<svg class="aimp-stats-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr__( 'Page views and visitors per day', 'atelier-irisee-master-plugin' ) . '">';
		for ( $g = 0; $g <= 4; $g++ ) {
			$v    = $top * $g / 4;
			$svg .= sprintf( '<line x1="%1$d" x2="%2$d" y1="%3$.1f" y2="%3$.1f" class="grid"/><text x="%4$d" y="%5$.1f" class="axis" text-anchor="end">%6$s</text>', $pad[3], $w - $pad[1], $y( $v ), $pad[3] - 6, $y( $v ) + 4, esc_html( AIMP_I18n::number( $v ) ) );
		}
		foreach ( array( 1 => 'views', 2 => 'visitors' ) as $col => $class ) {
			$points = array();
			foreach ( $series as $i => $row ) {
				$points[] = sprintf( '%.1f,%.1f', $x( $i ), $y( $row[ $col ] ) );
			}
			if ( 1 === $col ) {
				$svg .= '<polygon class="area" points="' . esc_attr( sprintf( '%.1f,%.1f ', $x( 0 ), $y( 0 ) ) . implode( ' ', $points ) . sprintf( ' %.1f,%.1f', $x( $count - 1 ), $y( 0 ) ) ) . '"/>';
			}
			$svg .= '<polyline class="' . $class . '" points="' . esc_attr( implode( ' ', $points ) ) . '"/>';
		}
		$every = max( 1, (int) ceil( $count / 10 ) );
		foreach ( $series as $i => $row ) {
			if ( 0 === $i % $every || $i === $count - 1 ) {
				$svg .= sprintf( '<text x="%.1f" y="%d" class="axis" text-anchor="middle">%s</text>', $x( $i ), $h - 8, esc_html( $row[0] ) );
			}
		}
		$svg .= sprintf(
			'<g class="legend"><rect x="%1$d" y="6" width="12" height="3" class="views"/><text x="%2$d" y="12">%3$s</text><rect x="%4$d" y="6" width="12" height="3" class="visitors"/><text x="%5$d" y="12">%6$s</text></g>',
			$pad[3],
			$pad[3] + 18,
			esc_html__( 'Page views', 'atelier-irisee-master-plugin' ),
			$pad[3] + 120,
			$pad[3] + 138,
			esc_html__( 'Visitors', 'atelier-irisee-master-plugin' )
		);
		return $svg . '</svg>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$periods = array(
			7   => __( 'Last 7 days', 'atelier-irisee-master-plugin' ),
			30  => __( 'Last 30 days', 'atelier-irisee-master-plugin' ),
			90  => __( 'Last 3 months', 'atelier-irisee-master-plugin' ),
			365 => __( 'Last 12 months', 'atelier-irisee-master-plugin' ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the period to show.
		$days  = isset( $_GET['period'] ) && isset( $periods[ absint( $_GET['period'] ) ] ) ? absint( $_GET['period'] ) : 30;
		$now   = current_time( 'timestamp' );
		$to    = gmdate( 'Y-m-d', $now );
		$from  = gmdate( 'Y-m-d', $now - ( $days - 1 ) * DAY_IN_SECONDS );
		$pto   = gmdate( 'Y-m-d', $now - $days * DAY_IN_SECONDS );
		$pfrom = gmdate( 'Y-m-d', $now - ( 2 * $days - 1 ) * DAY_IN_SECONDS );
		$num   = function ( $n, $decimals = 0 ) {
			return AIMP_I18n::number( $n, $decimals );
		};
		$money = function ( $n ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $n ) ), ENT_QUOTES, 'UTF-8' );
		};
		$product = function ( $id ) {
			return self::product_name( $id );
		};

		$pv  = self::total( 'pv', $from, $to );
		$uv  = self::total( 'uv', $from, $to );
		$ppv = self::total( 'pv', $pfrom, $pto );
		$puv = self::total( 'uv', $pfrom, $pto );

		echo '<div class="wrap aimp-stats"><h1>' . esc_html__( 'Statistics', 'atelier-irisee-master-plugin' ) . '</h1>';
		echo '<nav class="aimp-stats-periods">';
		foreach ( $periods as $n => $label ) {
			printf( '<a href="%1$s" class="%2$s">%3$s</a>', esc_url( add_query_arg( array( 'page' => self::PAGE, 'period' => $n ), admin_url( 'admin.php' ) ) ), $n === $days ? 'is-current' : '', esc_html( $label ) );
		}
		echo '</nav>';
		$since = get_option( 'aimp_stats_since' );
		if ( $since ) {
			/* translators: %s: date */
			echo '<p class="aimp-stats-note">' . esc_html( sprintf( __( 'Visits are counted since %s, anonymously: no cookie, no IP address kept, the shop\'s own logged-in staff and search engine robots not counted. The percentages compare with the period before.', 'atelier-irisee-master-plugin' ), AIMP_I18n::date( strtotime( $since ) ) ) ) . '</p>';
		}

		// Visitors.
		echo '<h2>' . esc_html__( 'Visitors', 'atelier-irisee-master-plugin' ) . '</h2><div class="aimp-stats-tiles">';
		echo self::tile( __( 'Visitors', 'atelier-irisee-master-plugin' ), $num( $uv ), $uv, $puv ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in tile().
		echo self::tile( __( 'Page views', 'atelier-irisee-master-plugin' ), $num( $pv ), $pv, $ppv ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Pages per visitor', 'atelier-irisee-master-plugin' ), $num( $uv ? $pv / $uv : 0, 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Visitors per day', 'atelier-irisee-master-plugin' ), $num( $uv / $days, 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div><div class="aimp-stats-box aimp-stats-wide">' . self::chart( self::series( $from, $to ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.

		$sources = array(
			'direct' => __( 'Typed in or bookmark', 'atelier-irisee-master-plugin' ),
		);
		$langs   = array( 'nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English' );
		$devices = array(
			'phone'    => __( 'Phone', 'atelier-irisee-master-plugin' ),
			'tablet'   => __( 'Tablet', 'atelier-irisee-master-plugin' ),
			'computer' => __( 'Computer', 'atelier-irisee-master-plugin' ),
		);
		echo '<div class="aimp-stats-grid">';
		echo self::ranking( __( 'Most visited pages', 'atelier-irisee-master-plugin' ), self::rows( self::items( 'view', $from, $to ), array( __CLASS__, 'page_name' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ranking().
		echo self::ranking( __( 'Most viewed products', 'atelier-irisee-master-plugin' ), self::rows( self::items( 'product', $from, $to ), $product ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking(
			__( 'Where visitors come from', 'atelier-irisee-master-plugin' ),
			self::rows(
				self::items( 'ref', $from, $to ),
				function ( $item ) use ( $sources ) {
					return isset( $sources[ $item ] ) ? $sources[ $item ] : $item;
				}
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking(
			__( 'Language and screen', 'atelier-irisee-master-plugin' ),
			array_merge(
				self::rows(
					self::items( 'lang', $from, $to ),
					function ( $item ) use ( $langs ) {
						return isset( $langs[ $item ] ) ? $langs[ $item ] : $item;
					}
				),
				self::rows(
					self::items( 'device', $from, $to ),
					function ( $item ) use ( $devices ) {
						return isset( $devices[ $item ] ) ? $devices[ $item ] : $item;
					}
				)
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$same = function ( $item ) {
			return $item;
		};
		echo self::ranking( __( 'What people search for', 'atelier-irisee-master-plugin' ), self::rows( self::items( 'search', $from, $to ), $same ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking( __( 'Searches that found nothing', 'atelier-irisee-master-plugin' ), self::rows( self::items( 'search_none', $from, $to ), $same ), __( 'Every search found something.', 'atelier-irisee-master-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		// Sales.
		$sales = self::sales( $from, $to );
		$prev  = self::sales( $pfrom, $pto );
		echo '<h2>' . esc_html__( 'Sales', 'atelier-irisee-master-plugin' ) . '</h2><div class="aimp-stats-tiles">';
		echo self::tile( __( 'Orders', 'atelier-irisee-master-plugin' ), $num( $sales['orders'] ), $sales['orders'], $prev['orders'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Revenue', 'atelier-irisee-master-plugin' ), $money( $sales['revenue'] ), $sales['revenue'], $prev['revenue'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Average order', 'atelier-irisee-master-plugin' ), $money( $sales['orders'] ? $sales['revenue'] / $sales['orders'] : 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Sewing kits sold', 'atelier-irisee-master-plugin' ), $num( $sales['kits'] ), $sales['kits'], $prev['kits'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Orders per 100 visitors', 'atelier-irisee-master-plugin' ), $uv ? $num( 100 * $sales['orders'] / $uv, 1 ) : '–' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div><div class="aimp-stats-grid">';
		$best = array();
		foreach ( array_slice( $sales['products'], 0, 15, true ) as $id => $row ) {
			$best[] = array( self::product_name( $id ), $num( $row['qty'] ) . ' × · ' . $money( $row['total'] ), $row['qty'] );
		}
		echo self::ranking( __( 'Best sellers', 'atelier-irisee-master-plugin' ), $best ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$cats = array();
		foreach ( $sales['categories'] as $name => $total ) {
			$cats[] = array( $name, $money( $total ), $total );
		}
		$cats[] = array( __( 'Of which in sewing kits', 'atelier-irisee-master-plugin' ), $money( $sales['kit_revenue'] ), $sales['kit_revenue'] );
		echo self::ranking( __( 'Revenue per category', 'atelier-irisee-master-plugin' ), $sales['orders'] ? $cats : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$codes   = self::rows( $sales['codes'], $same, 10 );
		$codes[] = array( __( 'Welcome codes used', 'atelier-irisee-master-plugin' ), $num( $sales['welcome'] ), $sales['welcome'] );
		$codes[] = array( __( 'Gift cards sold', 'atelier-irisee-master-plugin' ), $num( $sales['giftcards'] ), $sales['giftcards'] );
		echo self::ranking( __( 'Discount codes and gift cards', 'atelier-irisee-master-plugin' ), $sales['orders'] ? $codes : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		// Customizer.
		$steps = array(
			'cz_start'   => __( 'Started a kit', 'atelier-irisee-master-plugin' ),
			'cz_pattern' => __( 'Chose a pattern', 'atelier-irisee-master-plugin' ),
			'cz_size'    => __( 'Chose a size', 'atelier-irisee-master-plugin' ),
			'cz_fabric'  => __( 'Chose a fabric', 'atelier-irisee-master-plugin' ),
			'cz_cart'    => __( 'Added the kit to the cart', 'atelier-irisee-master-plugin' ),
		);
		$funnel = array();
		$first  = 0;
		foreach ( $steps as $kind => $label ) {
			$n     = self::total( $kind, $from, $to );
			$first = $first ? $first : $n;
			/* translators: 1: number, 2: percentage of the kits started */
			$funnel[] = array( $label, $first ? sprintf( __( '%1$s (%2$s%%)', 'atelier-irisee-master-plugin' ), $num( $n ), $num( 100 * $n / $first ) ) : $num( $n ), $n );
		}
		echo '<h2>' . esc_html__( 'Customizer', 'atelier-irisee-master-plugin' ) . '</h2><div class="aimp-stats-grid">';
		echo self::ranking( __( 'Steps of the customizer', 'atelier-irisee-master-plugin' ), $first ? $funnel : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking( __( 'Patterns chosen most', 'atelier-irisee-master-plugin' ), self::rows( self::items( 'cz_pattern', $from, $to ), $product ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking(
			__( 'Sizes chosen most', 'atelier-irisee-master-plugin' ),
			self::rows(
				self::items( 'cz_size', $from, $to ),
				function ( $item ) {
					$parts = explode( '|', $item, 2 );
					return self::product_name( (int) $parts[0] ) . ( isset( $parts[1] ) ? ' · ' . $parts[1] : '' );
				}
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking( __( 'Fabrics chosen most', 'atelier-irisee-master-plugin' ), self::rows( self::items( 'cz_fabric', $from, $to ), $product ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		// Favorites and saved kits.
		$kits = self::saved_kits();
		echo '<h2>' . esc_html__( 'Favorites and saved kits', 'atelier-irisee-master-plugin' ) . '</h2><div class="aimp-stats-grid">';
		echo self::ranking( __( 'Added to favorites in this period', 'atelier-irisee-master-plugin' ), self::rows( self::items( 'fav', $from, $to ), $product ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking( __( 'In the most favorites lists (customers with an account)', 'atelier-irisee-master-plugin' ), self::rows( self::favorites_all(), $product ), __( 'No customer has favorites yet.', 'atelier-irisee-master-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		/* translators: %s: number of kits */
		echo self::ranking( sprintf( __( 'Saved kits: patterns (%s kits in all)', 'atelier-irisee-master-plugin' ), $num( $kits['total'] ) ), self::rows( $kits['patterns'], $product ), __( 'No saved kits yet.', 'atelier-irisee-master-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::ranking( __( 'Saved kits: fabrics', 'atelier-irisee-master-plugin' ), self::rows( $kits['fabrics'], $product ), __( 'No saved kits yet.', 'atelier-irisee-master-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		// Customers and sign-ups.
		$people = self::people( $from, $to );
		$pp     = self::people( $pfrom, $pto );
		echo '<h2>' . esc_html__( 'Customers and sign-ups', 'atelier-irisee-master-plugin' ) . '</h2><div class="aimp-stats-tiles">';
		echo self::tile( __( 'New accounts', 'atelier-irisee-master-plugin' ), $num( $people['accounts'] ), $people['accounts'], $pp['accounts'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Customer accounts in all', 'atelier-irisee-master-plugin' ), $num( $people['customers'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'New newsletter sign-ups', 'atelier-irisee-master-plugin' ), $num( $people['new_subs'] ), $people['new_subs'], $pp['new_subs'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::tile( __( 'Confirmed subscribers', 'atelier-irisee-master-plugin' ), $num( $people['subscribers'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div><div class="aimp-stats-grid">';
		/* translators: %s: number of requests */
		echo self::ranking( sprintf( __( '"Let me know when it is back" requests (%s waiting)', 'atelier-irisee-master-plugin' ), $num( $people['alerts_total'] ) ), self::rows( $people['alerts'], $product ), __( 'Nobody is waiting for a product.', 'atelier-irisee-master-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></div>';
	}
}
