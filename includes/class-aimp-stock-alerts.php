<?php
/**
 * Back-in-stock alerts: "Email me when it's back" on sold-out product pages. When the product is in stock
 * again, everyone who asked gets an email in their language, and their alert is removed.
 *
 * Alerts are private posts (title = email, meta _aimp_product and _aimp_lang). A size of a pattern counts
 * as the pattern.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Stock_Alerts {

	const POST_TYPE  = 'aimp_stock_alert';
	const ADMIN_PAGE = 'aimp-stock-alerts';

	/** Products already handled in this request. */
	private static $sent = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'wc_ajax_aimp_stock_alert', array( __CLASS__, 'ajax' ) );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'stock_changed' ), 20, 2 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'stock_changed' ), 20, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 71 );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'          => false,
				'show_ui'         => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * The product an alert is kept for: a size of a pattern counts as the pattern.
	 *
	 * @param int $product_id Product or variation.
	 * @return int
	 */
	private static function alert_product( $product_id ) {
		$parent = wp_get_post_parent_id( $product_id );
		return 'product_variation' === get_post_type( $product_id ) && $parent ? (int) $parent : (int) $product_id;
	}

	/**
	 * The form under "Out of stock".
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function form_html( $product ) {
		$email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
		return sprintf(
			'<form class="aimp-stock-alert" data-aimp-stock-alert data-endpoint="%1$s" data-error="%2$s" novalidate>' .
			'<p class="aimp-stock-alert-title">%3$s</p>' .
			'<div class="aimp-stock-alert-row">' .
			'<label class="screen-reader-text" for="aimp-stock-alert-email">%4$s</label>' .
			'<input type="email" id="aimp-stock-alert-email" name="email" required autocomplete="email" value="%5$s" placeholder="%6$s">' .
			'<input type="text" name="aimp_hp" value="" tabindex="-1" autocomplete="off" class="aimp-newsletter-hp" aria-hidden="true">' .
			'<input type="hidden" name="product_id" value="%7$d">' .
			'<button type="submit" class="aimp-button">%8$s</button>' .
			'</div><p class="aimp-stock-alert-message" aria-live="polite" hidden></p></form>',
			esc_url( WC_AJAX::get_endpoint( 'aimp_stock_alert' ) ),
			esc_attr__( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
			esc_html__( 'Email me when it\'s back', 'atelier-irisee-master-plugin' ),
			esc_html__( 'Email address', 'atelier-irisee-master-plugin' ),
			esc_attr( $email ),
			esc_attr__( 'Your e-mail address', 'atelier-irisee-master-plugin' ),
			$product->get_id(),
			esc_html__( 'Notify me', 'atelier-irisee-master-plugin' )
		);
	}

	/**
	 * ?wc-ajax=aimp_stock_alert. No nonce (cached product pages); a hidden field and a limit stop bots.
	 */
	public static function ajax() {
		$ok = __( 'We\'ll email you as soon as it\'s back in stock.', 'atelier-irisee-master-plugin' );
		if ( AIMP_Login_Security::honeypot_triggered() ) {
			wp_send_json_success( array( 'message' => $ok ) );
		}
		if ( AIMP_Login_Security::rate_limited( 'stock_alert', AIMP_Login_Security::ip(), 10 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many attempts. Please try again later.', 'atelier-irisee-master-plugin' ) ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see above.
		$email   = isset( $_POST['email'] ) ? strtolower( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : '';
		$product = wc_get_product( isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0 );
		// phpcs:enable
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ) ) );
		}
		if ( ! $product || 'publish' !== $product->get_status() ) {
			wp_send_json_error( array( 'message' => __( 'This item is not available.', 'atelier-irisee-master-plugin' ) ) );
		}
		$product_id = self::alert_product( $product->get_id() );
		$exists     = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'title'          => $email,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_aimp_product', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small list.
				'meta_value'     => $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small list.
			)
		);
		if ( ! $exists ) {
			$id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => $email,
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_aimp_product', $product_id );
				update_post_meta( $id, '_aimp_lang', AIMP_I18n::current() );
			}
		}
		wp_send_json_success( array( 'message' => $ok ) );
	}

	/**
	 * In stock again: email everyone waiting for it.
	 *
	 * @param int    $product_id Product or variation.
	 * @param string $status     New stock status.
	 */
	public static function stock_changed( $product_id, $status ) {
		if ( 'instock' !== $status ) {
			return;
		}
		$product_id = self::alert_product( $product_id );
		if ( isset( self::$sent[ $product_id ] ) ) {
			return;
		}
		self::$sent[ $product_id ] = true;
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return;
		}
		$alerts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_key'       => '_aimp_product', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small list.
				'meta_value'     => $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small list.
			)
		);
		foreach ( $alerts as $alert ) {
			self::send( $alert, $product );
			wp_delete_post( $alert->ID, true );
		}
	}

	/**
	 * @param WP_Post    $alert   Alert.
	 * @param WC_Product $product Product.
	 */
	private static function send( $alert, $product ) {
		$name  = wp_strip_all_tags( $product->get_name() );
		$url   = $product->get_permalink();
		$image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) : '';
		AIMP_Giftcards::mail(
			$alert->post_title,
			(string) get_post_meta( $alert->ID, '_aimp_lang', true ),
			function () use ( $name, $url, $image ) {
				return array(
					/* translators: %s: product name */
					sprintf( __( '%s is back in stock', 'atelier-irisee-master-plugin' ), $name ),
					'<p>' . esc_html__( 'Hello,', 'atelier-irisee-master-plugin' ) . '</p>' .
					/* translators: %s: product name */
					'<p>' . esc_html( sprintf( __( 'Good news: %s is back in stock. Be quick, the stock is limited.', 'atelier-irisee-master-plugin' ), $name ) ) . '</p>' .
					( $image ? '<p><a href="' . esc_url( $url ) . '"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" width="220" style="border-radius:16px;max-width:100%;height:auto"></a></p>' : '' ) .
					'<p><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 26px;border:4px double #b38f4f;border-radius:100px;color:#613907;font-weight:bold;text-decoration:none">' . esc_html__( 'View product', 'atelier-irisee-master-plugin' ) . '</a></p>',
				);
			}
		);
	}

	/* ------------------------------------------------------------------
	 * Admin: WooCommerce → Stock alerts
	 * ------------------------------------------------------------------ */

	public static function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Stock alerts', 'atelier-irisee-master-plugin' ),
			__( 'Stock alerts', 'atelier-irisee-master-plugin' ),
			'manage_woocommerce',
			self::ADMIN_PAGE,
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$counts = array();
		foreach ( get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $id ) {
			$product_id            = (int) get_post_meta( $id, '_aimp_product', true );
			$counts[ $product_id ] = isset( $counts[ $product_id ] ) ? $counts[ $product_id ] + 1 : 1;
		}
		arsort( $counts );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stock alerts', 'atelier-irisee-master-plugin' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Customers who asked for an email when a sold-out product is back. The emails go out by themselves as soon as the product is in stock again.', 'atelier-irisee-master-plugin' ); ?></p>
			<table class="widefat striped" style="max-width:780px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'atelier-irisee-master-plugin' ); ?></th>
						<th><?php esc_html_e( 'Waiting customers', 'atelier-irisee-master-plugin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $counts ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'No stock alerts at the moment.', 'atelier-irisee-master-plugin' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $counts as $product_id => $count ) : ?>
						<tr>
							<td>
								<?php $link = get_edit_post_link( $product_id ); ?>
								<?php if ( $link ) : ?>
									<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( get_the_title( $product_id ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo (int) $count; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
