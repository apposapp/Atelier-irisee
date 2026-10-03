<?php
/**
 * [atelier_irisee_account]: one account page with tabs for personal data, orders,
 * refund / remove account, and gift cards. Also the admin side of refund requests.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Account {

	const SHORTCODE = 'atelier_irisee_account';
	const REQUEST   = '_aimp_refund_request';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );

		// Admin: refund requests on orders.
		add_action( 'add_meta_boxes', array( __CLASS__, 'order_box' ), 10, 2 );
		add_action( 'admin_post_aimp_refund_decide', array( __CLASS__, 'decide' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'order_columns' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'order_columns' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'order_column_legacy' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'order_column_hpos' ), 10, 2 );
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	public static function tabs() {
		return array(
			'personal'  => __( 'Personal data', 'atelier-irisee-master-plugin' ),
			'orders'    => __( 'My orders', 'atelier-irisee-master-plugin' ),
			'refund'    => __( 'Refund or remove account', 'atelier-irisee-master-plugin' ),
			'giftcards' => __( 'My gift cards', 'atelier-irisee-master-plugin' ),
		);
	}

	private static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab selection only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'personal';
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'personal';
	}

	public static function page_url( $tab, $args = array() ) {
		$base = get_permalink();
		$base = $base ? $base : home_url( '/' );
		return add_query_arg( array_merge( array( 'tab' => $tab ), $args ), $base );
	}

	private static function refund_days() {
		return max( 1, (int) AIMP_Settings::get( 'refund_days' ) );
	}

	private static function can_delete( $user_id ) {
		return (bool) AIMP_Settings::get( 'account_delete' ) && ! user_can( $user_id, 'edit_posts' ) && ! user_can( $user_id, 'manage_woocommerce' );
	}

	/**
	 * Gift cards of an order item that have been (partly) used.
	 *
	 * @param WC_Order_Item_Product $item Item.
	 * @return bool
	 */
	private static function item_has_used_giftcard( $item ) {
		foreach ( (array) $item->get_meta( '_aimp_gc_ids' ) as $id ) {
			$card = AIMP_Giftcards::get( $id );
			if ( $card && abs( $card['balance'] - $card['amount'] ) > 0.001 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Orders of a customer that can still get a refund request.
	 *
	 * @param int $user_id Customer.
	 * @return WC_Order[]
	 */
	private static function eligible_orders( $user_id ) {
		$orders = wc_get_orders(
			array(
				'customer_id'  => $user_id,
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'date_created' => '>' . ( time() - self::refund_days() * DAY_IN_SECONDS ),
				'limit'        => 50,
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);
		return array_values(
			array_filter(
				$orders,
				function ( $order ) {
					$request = $order->get_meta( AIMP_Account::REQUEST );
					$open    = is_array( $request ) && in_array( $request['status'], array( 'pending', 'approved' ), true );
					return ! $open && ( (float) $order->get_total() - (float) $order->get_total_refunded() ) > 0;
				}
			)
		);
	}

	private static function message( $code ) {
		$messages = array(
			'refund_sent'   => array( 'success', __( 'Your refund request has been sent. We will let you know by email as soon as we have looked at it.', 'atelier-irisee-master-plugin' ) ),
			'refund_error'  => array( 'error', __( 'Please choose an order, at least one item and tell us why you want a refund.', 'atelier-irisee-master-plugin' ) ),
			'delete_pw'     => array( 'error', __( 'The password (or email address) you entered is not correct.', 'atelier-irisee-master-plugin' ) ),
			'delete_ok'     => array( 'error', __( 'Please tick the box to confirm that you want to remove your account.', 'atelier-irisee-master-plugin' ) ),
			'delete_denied' => array( 'error', __( 'This account cannot be removed here. Please contact us.', 'atelier-irisee-master-plugin' ) ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : null;
	}

	/* ------------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------------ */

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="aimp-account aimp-account--guest"><p class="aimp-account-intro">' . esc_html__( 'Log in to see your account.', 'atelier-irisee-master-plugin' ) . '</p>' . AIMP_Login::container( array( 'context' => 'inline' ) ) . '</div>';
		}
		AIMP_Giftcards_Redeem::enqueue_assets();
		wp_enqueue_style( 'aimp-account', AIMP_PLUGIN_URL . 'assets/css/account.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-account', AIMP_PLUGIN_URL . 'assets/js/account.js', array(), AIMP_VERSION, true );

		$tab  = self::current_tab();
		$user = wp_get_current_user();
		ob_start();
		?>
		<div class="aimp-account">
			<p class="aimp-account-hello">
				<?php
				/* translators: %s: customer name */
				printf( esc_html__( 'Hello %s', 'atelier-irisee-master-plugin' ), '<strong>' . esc_html( $user->display_name ) . '</strong>' );
				?>
				· <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log out', 'atelier-irisee-master-plugin' ); ?></a>
			</p>
			<nav class="aimp-account-tabs" aria-label="<?php esc_attr_e( 'My account', 'atelier-irisee-master-plugin' ); ?>">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::page_url( $key ) ); ?>" class="aimp-account-tab<?php echo $key === $tab ? ' is-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="aimp-account-panel">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
				$message = isset( $_GET['aimp_msg'] ) ? self::message( sanitize_key( wp_unslash( $_GET['aimp_msg'] ) ) ) : null;
				if ( $message ) {
					echo '<div class="aimp-account-notice aimp-account-notice--' . esc_attr( $message[0] ) . '" role="status">' . esc_html( $message[1] ) . '</div>';
				}
				call_user_func( array( __CLASS__, 'tab_' . $tab ), $user );
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------- Personal data ---------- */

	public static function tab_personal( $user ) {
		echo '<h2 class="aimp-account-title">' . esc_html__( 'Personal data', 'atelier-irisee-master-plugin' ) . '</h2>';
		echo AIMP_Login::shortcode_profile(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.

		echo '<h3 class="aimp-account-subtitle">' . esc_html__( 'Addresses', 'atelier-irisee-master-plugin' ) . '</h3><div class="aimp-account-addresses">';
		$types = array(
			'billing'  => __( 'Billing address', 'atelier-irisee-master-plugin' ),
			'shipping' => __( 'Shipping address', 'atelier-irisee-master-plugin' ),
		);
		foreach ( $types as $type => $label ) {
			$address = wc_get_account_formatted_address( $type );
			printf(
				'<div class="aimp-account-address"><h4>%1$s</h4><address>%2$s</address><a class="aimp-account-button" href="%3$s">%4$s</a></div>',
				esc_html( $label ),
				$address ? wp_kses_post( $address ) : esc_html__( 'You have not added this address yet.', 'atelier-irisee-master-plugin' ),
				esc_url( wc_get_endpoint_url( 'edit-address', $type, wc_get_page_permalink( 'myaccount' ) ) ),
				esc_html( $address ? __( 'Edit', 'atelier-irisee-master-plugin' ) : __( 'Add', 'atelier-irisee-master-plugin' ) )
			);
		}
		echo '</div>';
	}

	/* ---------- Orders ---------- */

	public static function tab_orders() {
		$view_url = function ( $url, $order ) {
			return AIMP_Account::page_url( 'orders', array( 'order' => $order->get_id() ) );
		};
		$endpoint = function ( $url, $endpoint, $value ) {
			return 'orders' === $endpoint ? AIMP_Account::page_url( 'orders', array( 'orders_page' => max( 1, (int) $value ) ) ) : $url;
		};
		add_filter( 'woocommerce_get_view_order_url', $view_url, 10, 2 );
		add_filter( 'woocommerce_get_endpoint_url', $endpoint, 10, 3 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only; WooCommerce checks the order owner.
		$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		if ( $order_id ) {
			echo '<p><a class="aimp-account-back" href="' . esc_url( self::page_url( 'orders' ) ) . '">← ' . esc_html__( 'All orders', 'atelier-irisee-master-plugin' ) . '</a></p>';
			woocommerce_account_view_order( $order_id );
		} else {
			echo '<h2 class="aimp-account-title">' . esc_html__( 'My orders', 'atelier-irisee-master-plugin' ) . '</h2>';
			woocommerce_account_orders( isset( $_GET['orders_page'] ) ? max( 1, absint( $_GET['orders_page'] ) ) : 1 );
		}
		// phpcs:enable

		remove_filter( 'woocommerce_get_view_order_url', $view_url, 10 );
		remove_filter( 'woocommerce_get_endpoint_url', $endpoint, 10 );
	}

	/* ---------- Refund / remove account ---------- */

	public static function tab_refund( $user ) {
		$orders = self::eligible_orders( $user->ID );
		echo '<h2 class="aimp-account-title">' . esc_html__( 'Request a refund', 'atelier-irisee-master-plugin' ) . '</h2>';
		/* translators: %d: number of days */
		echo '<p class="aimp-account-text">' . esc_html( sprintf( __( 'You can ask for a refund up to %d days after your order. Choose the order and the items, and tell us why. We will reply by email.', 'atelier-irisee-master-plugin' ), self::refund_days() ) ) . '</p>';

		if ( ! $orders ) {
			echo '<p class="aimp-account-empty">' . esc_html__( 'There are no orders that can be refunded at the moment.', 'atelier-irisee-master-plugin' ) . '</p>';
		} else {
			?>
			<form method="post" class="aimp-account-form" data-aimp-refund-form>
				<?php wp_nonce_field( 'aimp_account_refund', 'aimp_account_nonce' ); ?>
				<input type="hidden" name="aimp_account_action" value="refund">
				<p class="aimp-account-field">
					<label for="aimp-refund-order"><?php esc_html_e( 'Order', 'atelier-irisee-master-plugin' ); ?></label>
					<select id="aimp-refund-order" name="aimp_refund[order]" required>
						<option value=""><?php esc_html_e( '— Choose an order —', 'atelier-irisee-master-plugin' ); ?></option>
						<?php foreach ( $orders as $order ) : ?>
							<option value="<?php echo esc_attr( $order->get_id() ); ?>">
								<?php
								/* translators: 1: order number, 2: date, 3: total */
								echo esc_html( sprintf( __( 'Order #%1$s of %2$s (%3$s)', 'atelier-irisee-master-plugin' ), $order->get_order_number(), wc_format_datetime( $order->get_date_created() ), wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ) );
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php foreach ( $orders as $order ) : ?>
					<fieldset class="aimp-refund-items" data-order="<?php echo esc_attr( $order->get_id() ); ?>" hidden>
						<legend><?php esc_html_e( 'Which items?', 'atelier-irisee-master-plugin' ); ?></legend>
						<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
							<?php
							$max  = (int) $item->get_quantity() - abs( (int) $order->get_qty_refunded_for_item( $item_id ) );
							$used = self::item_has_used_giftcard( $item );
							if ( $max < 1 ) {
								continue;
							}
							?>
							<div class="aimp-refund-item<?php echo $used ? ' is-disabled' : ''; ?>">
								<label>
									<input type="checkbox" name="aimp_refund[items][<?php echo esc_attr( $item_id ); ?>]" value="1" <?php disabled( $used ); ?>>
									<span><?php echo esc_html( $item->get_name() ); ?></span>
								</label>
								<?php if ( $used ) : ?>
									<small><?php esc_html_e( 'This gift card has already been used.', 'atelier-irisee-master-plugin' ); ?></small>
								<?php elseif ( $max > 1 ) : ?>
									<label class="aimp-refund-qty"><?php esc_html_e( 'Quantity', 'atelier-irisee-master-plugin' ); ?> <input type="number" name="aimp_refund[qty][<?php echo esc_attr( $item_id ); ?>]" value="<?php echo esc_attr( $max ); ?>" min="1" max="<?php echo esc_attr( $max ); ?>"></label>
								<?php else : ?>
									<input type="hidden" name="aimp_refund[qty][<?php echo esc_attr( $item_id ); ?>]" value="1">
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
				<p class="aimp-account-field">
					<label for="aimp-refund-reason"><?php esc_html_e( 'Why would you like a refund?', 'atelier-irisee-master-plugin' ); ?></label>
					<textarea id="aimp-refund-reason" name="aimp_refund[reason]" rows="4" maxlength="1000" required></textarea>
				</p>
				<button type="submit" class="aimp-account-button aimp-account-button--primary"><?php esc_html_e( 'Send refund request', 'atelier-irisee-master-plugin' ); ?></button>
			</form>
			<?php
		}

		// Earlier requests.
		$requested = wc_get_orders(
			array(
				'customer_id'  => $user->ID,
				'limit'        => 20,
				'meta_key'     => self::REQUEST, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);
		if ( $requested ) {
			echo '<h3 class="aimp-account-subtitle">' . esc_html__( 'Your refund requests', 'atelier-irisee-master-plugin' ) . '</h3><ul class="aimp-account-requests">';
			foreach ( $requested as $order ) {
				$request = $order->get_meta( self::REQUEST );
				if ( ! is_array( $request ) ) {
					continue;
				}
				printf(
					'<li><span>%1$s</span> <span class="aimp-request-status aimp-request-status--%2$s">%3$s</span> <span>%4$s</span></li>',
					/* translators: %s: order number */
					esc_html( sprintf( __( 'Order #%s', 'atelier-irisee-master-plugin' ), $order->get_order_number() ) ),
					esc_attr( $request['status'] ),
					esc_html( self::request_status_label( $request['status'] ) ),
					wp_kses_post( wc_price( $request['amount'], array( 'currency' => $order->get_currency() ) ) )
				);
			}
			echo '</ul>';
		}

		// Remove account.
		echo '<h2 class="aimp-account-title aimp-account-title--danger">' . esc_html__( 'Remove my account', 'atelier-irisee-master-plugin' ) . '</h2>';
		if ( ! self::can_delete( $user->ID ) ) {
			echo '<p class="aimp-account-text">' . esc_html__( 'Please contact us if you would like your account to be removed.', 'atelier-irisee-master-plugin' ) . '</p>';
			return;
		}
		$social_only = (bool) get_user_meta( $user->ID, AIMP_Login_Social::SOCIAL_ONLY, true );
		?>
		<p class="aimp-account-text"><?php esc_html_e( 'Your account, saved addresses and favorites are removed for good. Orders stay in our records because the law requires it, and gift cards keep their balance: you can still use their codes.', 'atelier-irisee-master-plugin' ); ?></p>
		<form method="post" class="aimp-account-form aimp-account-form--danger">
			<?php wp_nonce_field( 'aimp_account_delete', 'aimp_account_nonce' ); ?>
			<input type="hidden" name="aimp_account_action" value="delete">
			<p class="aimp-account-field">
				<label for="aimp-delete-confirm"><?php echo $social_only ? esc_html__( 'Type your email address to confirm', 'atelier-irisee-master-plugin' ) : esc_html__( 'Your password', 'atelier-irisee-master-plugin' ); ?></label>
				<input type="<?php echo $social_only ? 'email' : 'password'; ?>" id="aimp-delete-confirm" name="aimp_delete_confirm" required autocomplete="<?php echo $social_only ? 'email' : 'current-password'; ?>">
			</p>
			<p class="aimp-account-field">
				<label class="aimp-account-check"><input type="checkbox" name="aimp_delete_sure" value="1" required> <?php esc_html_e( 'I understand that my account is removed for good.', 'atelier-irisee-master-plugin' ); ?></label>
			</p>
			<button type="submit" class="aimp-account-button aimp-account-button--danger"><?php esc_html_e( 'Remove my account', 'atelier-irisee-master-plugin' ); ?></button>
		</form>
		<?php
	}

	/* ---------- Gift cards ---------- */

	public static function tab_giftcards( $user ) {
		echo '<h2 class="aimp-account-title">' . esc_html__( 'My gift cards', 'atelier-irisee-master-plugin' ) . '</h2>';
		$ids = get_posts(
			array(
				'post_type'      => AIMP_Giftcards::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'   => '_aimp_gc_purchaser',
						'value' => $user->ID,
					),
					array(
						'key'   => '_aimp_gc_recipient_email',
						'value' => $user->user_email,
					),
				),
			)
		);
		$cards = array();
		foreach ( $ids as $id ) {
			$card = AIMP_Giftcards::get( $id );
			$mine = $card && (int) $card['purchaser'] === $user->ID;
			// A gift that is still waiting for its send date stays a surprise for the recipient.
			if ( $card && ( $mine || $card['sent'] || ! $card['send_date'] ) ) {
				$cards[] = $card;
			}
		}

		if ( ! $cards ) {
			echo '<p class="aimp-account-empty">' . esc_html__( 'You have no gift cards yet.', 'atelier-irisee-master-plugin' ) . '</p>';
		} else {
			echo '<ul class="aimp-gc-list">';
			foreach ( $cards as $card ) {
				$usable  = '' === AIMP_Giftcards::unusable_reason( $card );
				$for_who = ( 'email_other' === $card['delivery'] && (int) $card['purchaser'] === $user->ID && $card['recipient_email'] !== $user->user_email )
					/* translators: %s: recipient */
					? sprintf( __( 'Gift for %s', 'atelier-irisee-master-plugin' ), $card['recipient_name'] ? $card['recipient_name'] : $card['recipient_email'] )
					: '';
				?>
				<li class="aimp-gc-item<?php echo $usable ? '' : ' is-inactive'; ?>">
					<img class="aimp-gc-item-image" src="<?php echo esc_url( AIMP_Giftcards::design_image( $card['design'], 'woocommerce_thumbnail' ) ); ?>" alt="">
					<div class="aimp-gc-item-info">
						<p class="aimp-gc-item-value"><?php echo wp_kses_post( wc_price( $card['balance'] ) ); ?> <small><?php /* translators: %s: original value */ echo esc_html( sprintf( __( 'of %s', 'atelier-irisee-master-plugin' ), wp_strip_all_tags( wc_price( $card['amount'] ) ) ) ); ?></small></p>
						<p class="aimp-gc-item-code"><code><?php echo esc_html( $card['code'] ); ?></code> <button type="button" class="aimp-gc-link" data-aimp-gc-copy="<?php echo esc_attr( $card['code'] ); ?>"><?php esc_html_e( 'Copy code', 'atelier-irisee-master-plugin' ); ?></button></p>
						<p class="aimp-gc-item-meta">
							<span class="aimp-gc-badge"><?php echo esc_html( AIMP_Giftcards::status_label( $card ) ); ?></span>
							<?php if ( $card['expires'] ) : ?>
								<?php /* translators: %s: date */ ?>
								<span><?php echo esc_html( sprintf( __( 'Valid until %s', 'atelier-irisee-master-plugin' ), date_i18n( get_option( 'date_format' ), strtotime( $card['expires'] ) ) ) ); ?></span>
							<?php endif; ?>
							<?php if ( $for_who ) : ?>
								<span><?php echo esc_html( $for_who ); ?></span>
							<?php endif; ?>
						</p>
					</div>
					<?php if ( $usable ) : ?>
						<button type="button" class="aimp-account-button" data-aimp-gc-use="<?php echo esc_attr( $card['code'] ); ?>"><?php esc_html_e( 'Use in my cart', 'atelier-irisee-master-plugin' ); ?></button>
					<?php endif; ?>
				</li>
				<?php
			}
			echo '</ul>';
		}
		?>
		<h3 class="aimp-account-subtitle"><?php esc_html_e( 'Check a gift card', 'atelier-irisee-master-plugin' ); ?></h3>
		<div class="aimp-gc-check" data-aimp-gc-check>
			<label class="screen-reader-text" for="aimp-gc-check-code"><?php esc_html_e( 'Gift card code', 'atelier-irisee-master-plugin' ); ?></label>
			<input type="text" id="aimp-gc-check-code" placeholder="<?php esc_attr_e( 'Gift card code', 'atelier-irisee-master-plugin' ); ?>" autocomplete="off" spellcheck="false">
			<button type="button" class="aimp-account-button" data-aimp-gc-check-button><?php esc_html_e( 'Check balance', 'atelier-irisee-master-plugin' ); ?></button>
			<p class="aimp-gc-redeem-msg" role="status" hidden></p>
		</div>
		<?php
		$product_id = (int) AIMP_Giftcards::opt( 'product_id' );
		if ( $product_id && get_post_status( $product_id ) === 'publish' ) {
			echo '<p class="aimp-account-text"><a class="aimp-account-button" href="' . esc_url( get_permalink( $product_id ) ) . '">' . esc_html__( 'Buy a gift card', 'atelier-irisee-master-plugin' ) . '</a></p>';
		}
	}

	/* ------------------------------------------------------------------
	 * Form handling
	 * ------------------------------------------------------------------ */

	public static function handle_post() {
		if ( empty( $_POST['aimp_account_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['aimp_account_action'] ) );
		$nonce  = isset( $_POST['aimp_account_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_account_nonce'] ) ) : '';
		if ( ! in_array( $action, array( 'refund', 'delete' ), true ) || ! wp_verify_nonce( $nonce, 'aimp_account_' . $action ) ) {
			return;
		}
		if ( 'refund' === $action ) {
			self::handle_refund();
		} else {
			self::handle_delete();
		}
	}

	private static function back( $tab, $message ) {
		wp_safe_redirect( self::page_url( $tab, array( 'aimp_msg' => $message ) ) );
		exit;
	}

	private static function handle_refund() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_post().
		$data    = isset( $_POST['aimp_refund'] ) && is_array( $_POST['aimp_refund'] ) ? wp_unslash( $_POST['aimp_refund'] ) : array();
		// phpcs:enable
		$user_id = get_current_user_id();
		$order   = wc_get_order( isset( $data['order'] ) ? absint( $data['order'] ) : 0 );
		$reason  = isset( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : '';
		$eligible = array_map(
			function ( $o ) {
				return $o->get_id();
			},
			self::eligible_orders( $user_id )
		);

		if ( ! $order || (int) $order->get_customer_id() !== $user_id || ! in_array( $order->get_id(), $eligible, true ) || '' === trim( $reason ) ) {
			self::back( 'refund', 'refund_error' );
		}

		$items  = array();
		$amount = 0;
		foreach ( isset( $data['items'] ) ? (array) $data['items'] : array() as $item_id => $on ) {
			$item = $order->get_item( absint( $item_id ) );
			if ( ! $item || ! $item instanceof WC_Order_Item_Product || self::item_has_used_giftcard( $item ) ) {
				continue;
			}
			$max = (int) $item->get_quantity() - abs( (int) $order->get_qty_refunded_for_item( $item->get_id() ) );
			$qty = isset( $data['qty'][ $item_id ] ) ? min( $max, max( 1, absint( $data['qty'][ $item_id ] ) ) ) : $max;
			if ( $qty < 1 ) {
				continue;
			}
			$items[ $item->get_id() ] = $qty;
			$amount                  += ( (float) $item->get_total() + (float) $item->get_total_tax() ) / max( 1, $item->get_quantity() ) * $qty;
		}
		if ( ! $items ) {
			self::back( 'refund', 'refund_error' );
		}

		$order->update_meta_data(
			self::REQUEST,
			array(
				'status' => 'pending',
				'items'  => $items,
				'amount' => round( $amount, wc_get_price_decimals() ),
				'reason' => function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, 1000 ) : substr( $reason, 0, 1000 ),
				'date'   => current_time( 'mysql' ),
				'lang'   => AIMP_I18n::current(),
			)
		);
		$order->add_order_note( __( 'The customer asked for a refund:', 'atelier-irisee-master-plugin' ) . "\n" . $reason, 0, false );
		$order->save();

		self::refund_email( $order, 'requested_customer' );
		self::refund_email( $order, 'requested_admin' );
		self::back( 'refund', 'refund_sent' );
	}

	private static function handle_delete() {
		$user = wp_get_current_user();
		if ( ! self::can_delete( $user->ID ) ) {
			self::back( 'refund', 'delete_denied' );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_post().
		if ( empty( $_POST['aimp_delete_sure'] ) ) {
			self::back( 'refund', 'delete_ok' );
		}
		$confirm = isset( $_POST['aimp_delete_confirm'] ) ? (string) wp_unslash( $_POST['aimp_delete_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password, checked below.
		// phpcs:enable
		$ok = get_user_meta( $user->ID, AIMP_Login_Social::SOCIAL_ONLY, true )
			? strtolower( trim( $confirm ) ) === strtolower( $user->user_email )
			: wp_check_password( $confirm, $user->user_pass, $user->ID );
		if ( ! $ok ) {
			self::back( 'refund', 'delete_pw' );
		}

		$lang  = get_user_meta( $user->ID, 'aimp_lang', true );
		$email = $user->user_email;
		$name  = $user->display_name;
		AIMP_Giftcards::mail(
			$email,
			$lang,
			function () use ( $name ) {
				$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
				return array(
					/* translators: %s: shop name */
					sprintf( __( 'Your account at %s has been removed', 'atelier-irisee-master-plugin' ), $site ),
					/* translators: %s: customer name */
					'<p>' . esc_html( sprintf( __( 'Hello %s,', 'atelier-irisee-master-plugin' ), $name ) ) . '</p><p>' . esc_html__( 'As you asked, your account has been removed. Thank you for having been our customer. You are always welcome back.', 'atelier-irisee-master-plugin' ) . '</p>',
				);
			}
		);
		AIMP_Giftcards::mail(
			get_option( 'admin_email' ),
			AIMP_I18n::default_language(),
			function () use ( $name, $email ) {
				return array(
					/* translators: %s: customer name */
					sprintf( __( 'A customer removed their account: %s', 'atelier-irisee-master-plugin' ), $name ),
					/* translators: 1: customer name, 2: email */
					'<p>' . esc_html( sprintf( __( '%1$s (%2$s) removed their account through the account page. Their orders are kept as guest orders.', 'atelier-irisee-master-plugin' ), $name, $email ) ) . '</p>',
				);
			}
		);

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$user_id = $user->ID;
		wp_logout();
		wp_delete_user( $user_id );
		wp_safe_redirect( add_query_arg( 'aimp_el_msg', 'account_removed', home_url( '/' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Refund requests: admin
	 * ------------------------------------------------------------------ */

	public static function request_status_label( $status ) {
		$labels = array(
			'pending'  => __( 'Waiting for an answer', 'atelier-irisee-master-plugin' ),
			'approved' => __( 'Approved', 'atelier-irisee-master-plugin' ),
			'rejected' => __( 'Rejected', 'atelier-irisee-master-plugin' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Add the box only on orders that have a request (HPOS and classic order screens).
	 *
	 * @param string                 $screen_or_type Screen ID or post type.
	 * @param WP_Post|WC_Order|mixed $object         Post or order.
	 */
	public static function order_box( $screen_or_type, $object = null ) {
		$order = $object instanceof WC_Order ? $object : ( $object instanceof WP_Post && 'shop_order' === $object->post_type ? wc_get_order( $object->ID ) : null );
		if ( ! $order || ! is_array( $order->get_meta( self::REQUEST ) ) ) {
			return;
		}
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'aimp-refund-request', __( 'Refund request', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_order_box' ), $screen, 'side', 'high' );
	}

	public static function render_order_box( $object ) {
		$order   = $object instanceof WC_Order ? $object : wc_get_order( $object->ID );
		$request = $order ? $order->get_meta( self::REQUEST ) : null;
		if ( ! is_array( $request ) ) {
			return;
		}
		echo '<p><strong>' . esc_html( self::request_status_label( $request['status'] ) ) . '</strong><br>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $request['date'] ) ) . '</p>';
		echo '<ul>';
		foreach ( $request['items'] as $item_id => $qty ) {
			$item = $order->get_item( $item_id );
			if ( $item ) {
				echo '<li>' . esc_html( $item->get_name() ) . ' × ' . (int) $qty . '</li>';
			}
		}
		echo '</ul><p>' . esc_html__( 'Amount:', 'atelier-irisee-master-plugin' ) . ' <strong>' . wp_kses_post( wc_price( $request['amount'], array( 'currency' => $order->get_currency() ) ) ) . '</strong></p>';
		echo '<p><em>' . nl2br( esc_html( $request['reason'] ) ) . '</em></p>';
		if ( ! empty( $request['answer'] ) ) {
			echo '<p>' . esc_html__( 'Your answer:', 'atelier-irisee-master-plugin' ) . ' ' . esc_html( $request['answer'] ) . '</p>';
		}
		if ( 'pending' !== $request['status'] ) {
			return;
		}
		$base = admin_url( 'admin-post.php?action=aimp_refund_decide&order=' . $order->get_id() );
		$url  = wp_nonce_url( $base, 'aimp_refund_' . $order->get_id() );
		?>
		<p><label for="aimp-refund-answer"><?php esc_html_e( 'Message to the customer (optional)', 'atelier-irisee-master-plugin' ); ?></label><br>
			<textarea id="aimp-refund-answer" rows="3" style="width:100%"></textarea></p>
		<p>
			<a class="button button-primary" data-aimp-decide href="<?php echo esc_url( add_query_arg( 'do', 'approve', $url ) ); ?>"><?php esc_html_e( 'Approve and refund', 'atelier-irisee-master-plugin' ); ?></a>
			<a class="button" data-aimp-decide href="<?php echo esc_url( add_query_arg( 'do', 'reject', $url ) ); ?>"><?php esc_html_e( 'Reject', 'atelier-irisee-master-plugin' ); ?></a>
		</p>
		<p class="description"><?php esc_html_e( 'Approve refunds the amount through the payment method when it supports refunds; otherwise the refund is recorded and you pay it back yourself.', 'atelier-irisee-master-plugin' ); ?></p>
		<script>
		document.querySelectorAll('[data-aimp-decide]').forEach(function (link) {
			link.addEventListener('click', function () {
				var answer = document.getElementById('aimp-refund-answer').value;
				if (answer) {
					link.href += '&answer=' + encodeURIComponent(answer);
				}
			});
		});
		</script>
		<?php
	}

	public static function decide() {
		$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		if ( ! current_user_can( 'edit_shop_orders' ) || ! check_admin_referer( 'aimp_refund_' . $order_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
		$order   = wc_get_order( $order_id );
		$request = $order ? $order->get_meta( self::REQUEST ) : null;
		$back    = $order ? $order->get_edit_order_url() : admin_url();
		if ( ! is_array( $request ) || 'pending' !== $request['status'] ) {
			wp_safe_redirect( $back );
			exit;
		}
		$do     = isset( $_GET['do'] ) && 'approve' === $_GET['do'] ? 'approve' : 'reject';
		$answer = isset( $_GET['answer'] ) ? sanitize_textarea_field( wp_unslash( $_GET['answer'] ) ) : '';

		if ( 'approve' === $do ) {
			$result = self::make_refund( $order, $request );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg( array( 'aimp_refund' => 'error', 'aimp_refund_msg' => rawurlencode( $result->get_error_message() ) ), $back ) );
				exit;
			}
		}

		$request['status']  = 'approve' === $do ? 'approved' : 'rejected';
		$request['answer']  = $answer;
		$request['decided'] = current_time( 'mysql' );
		$order->update_meta_data( self::REQUEST, $request );
		$order->add_order_note( 'approve' === $do ? __( 'Refund request approved.', 'atelier-irisee-master-plugin' ) : __( 'Refund request rejected.', 'atelier-irisee-master-plugin' ) );
		$order->save();
		self::refund_email( $order, 'approve' === $do ? 'approved' : 'rejected' );

		wp_safe_redirect( add_query_arg( 'aimp_refund', $request['status'], $back ) );
		exit;
	}

	/**
	 * Create the WooCommerce refund. The part paid with a gift card goes back onto that gift card.
	 *
	 * @param WC_Order $order   Order.
	 * @param array    $request Request.
	 * @return WC_Order_Refund|true|WP_Error
	 */
	private static function make_refund( $order, $request ) {
		$line_items = array();
		foreach ( $request['items'] as $item_id => $qty ) {
			$item = $order->get_item( $item_id );
			if ( ! $item ) {
				continue;
			}
			$ratio = $qty / max( 1, $item->get_quantity() );
			$taxes = $item->get_taxes();
			$tax   = array();
			foreach ( isset( $taxes['total'] ) ? (array) $taxes['total'] : array() as $rate_id => $value ) {
				$tax[ $rate_id ] = wc_format_decimal( (float) $value * $ratio, wc_get_price_decimals() );
			}
			$line_items[ $item_id ] = array(
				'qty'          => $qty,
				'refund_total' => wc_format_decimal( (float) $item->get_total() * $ratio, wc_get_price_decimals() ),
				'refund_tax'   => $tax,
			);
		}

		$amount       = (float) $request['amount'];
		$remaining    = (float) $order->get_remaining_refund_amount();
		$gateway_part = round( min( $amount, $remaining ), wc_get_price_decimals() );
		$card_part    = round( $amount - $gateway_part, wc_get_price_decimals() );

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$gateway  = isset( $gateways[ $order->get_payment_method() ] ) ? $gateways[ $order->get_payment_method() ] : null;
		$refund   = true;
		if ( $gateway_part > 0 ) {
			$refund = wc_create_refund(
				array(
					'amount'         => $gateway_part,
					'reason'         => __( 'Refund request approved', 'atelier-irisee-master-plugin' ),
					'order_id'       => $order->get_id(),
					'line_items'     => $line_items,
					'refund_payment' => $gateway && $gateway->supports( 'refunds' ),
					'restock_items'  => true,
				)
			);
			if ( is_wp_error( $refund ) ) {
				return $refund;
			}
			if ( ! $gateway || ! $gateway->supports( 'refunds' ) ) {
				$order->add_order_note( __( 'This payment method cannot refund automatically. Please pay the refund back to the customer yourself.', 'atelier-irisee-master-plugin' ) );
			}
		}

		// Part of the order was paid with a gift card: give that part back to the card.
		$deducted = (array) $order->get_meta( '_aimp_gc_deducted' );
		if ( $card_part > 0 && $deducted ) {
			$code = key( $deducted );
			$id   = AIMP_Giftcards::find_by_code( $code );
			if ( $id ) {
				/* translators: %s: order number */
				AIMP_Giftcards::adjust( $id, $card_part, sprintf( __( 'Refund for order #%s', 'atelier-irisee-master-plugin' ), $order->get_order_number() ), $order->get_id() );
			}
		}

		// Refunded gift cards (never used) are switched off.
		foreach ( array_keys( $request['items'] ) as $item_id ) {
			$item = $order->get_item( $item_id );
			foreach ( $item ? (array) $item->get_meta( '_aimp_gc_ids' ) : array() as $gc_id ) {
				/* translators: %s: order number */
				AIMP_Giftcards::set_status( $gc_id, 'disabled', sprintf( __( 'Disabled: refunded with order #%s.', 'atelier-irisee-master-plugin' ), $order->get_order_number() ) );
			}
		}
		return $refund;
	}

	public static function admin_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$state = isset( $_GET['aimp_refund'] ) ? sanitize_key( wp_unslash( $_GET['aimp_refund'] ) ) : '';
		if ( 'approved' === $state ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The refund request was approved and the customer was emailed.', 'atelier-irisee-master-plugin' ) . '</p></div>';
		} elseif ( 'rejected' === $state ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The refund request was rejected and the customer was emailed.', 'atelier-irisee-master-plugin' ) . '</p></div>';
		} elseif ( 'error' === $state ) {
			$msg = isset( $_GET['aimp_refund_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['aimp_refund_msg'] ) ) ) : '';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The refund could not be made:', 'atelier-irisee-master-plugin' ) . ' ' . esc_html( $msg ) . '</p></div>';
		}
		// phpcs:enable
	}

	public static function order_columns( $columns ) {
		$columns['aimp_refund'] = __( 'Refund', 'atelier-irisee-master-plugin' );
		return $columns;
	}

	private static function order_column_value( $order ) {
		$request = $order ? $order->get_meta( self::REQUEST ) : null;
		if ( is_array( $request ) ) {
			echo '<mark class="order-status ' . ( 'pending' === $request['status'] ? 'status-on-hold' : 'status-processing' ) . '"><span>' . esc_html( 'pending' === $request['status'] ? __( 'Refund requested', 'atelier-irisee-master-plugin' ) : self::request_status_label( $request['status'] ) ) . '</span></mark>';
		}
	}

	public static function order_column_legacy( $column, $post_id ) {
		if ( 'aimp_refund' === $column ) {
			self::order_column_value( wc_get_order( $post_id ) );
		}
	}

	public static function order_column_hpos( $column, $order ) {
		if ( 'aimp_refund' === $column ) {
			self::order_column_value( $order );
		}
	}

	/* ------------------------------------------------------------------
	 * Emails
	 * ------------------------------------------------------------------ */

	private static function refund_email( $order, $type ) {
		$request = $order->get_meta( self::REQUEST );
		if ( ! is_array( $request ) ) {
			return;
		}
		$to_admin = 'requested_admin' === $type;
		$to       = $to_admin ? get_option( 'admin_email' ) : $order->get_billing_email();
		$lang     = $to_admin ? AIMP_I18n::default_language() : $request['lang'];
		AIMP_Giftcards::mail(
			$to,
			$lang,
			function () use ( $order, $request, $type ) {
				$number = $order->get_order_number();
				$amount = wp_strip_all_tags( wc_price( $request['amount'], array( 'currency' => $order->get_currency() ) ) );
				$name   = $order->get_billing_first_name();
				switch ( $type ) {
					case 'requested_admin':
						/* translators: %s: order number */
						$subject = sprintf( __( 'Refund request for order #%s', 'atelier-irisee-master-plugin' ), $number );
						/* translators: 1: customer name, 2: amount */
						$body = '<p>' . esc_html( sprintf( __( '%1$s asks for a refund of %2$s.', 'atelier-irisee-master-plugin' ), $order->get_formatted_billing_full_name(), $amount ) ) . '</p><p><em>' . nl2br( esc_html( $request['reason'] ) ) . '</em></p><p><a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Open the order to approve or reject the request', 'atelier-irisee-master-plugin' ) . '</a></p>';
						break;
					case 'approved':
						/* translators: %s: order number */
						$subject = sprintf( __( 'Your refund for order #%s has been approved', 'atelier-irisee-master-plugin' ), $number );
						/* translators: 1: customer name, 2: amount */
						$body = '<p>' . esc_html( sprintf( __( 'Hello %1$s, good news: we approved your refund of %2$s. Depending on your payment method, it can take a few days before you see it.', 'atelier-irisee-master-plugin' ), $name, $amount ) ) . '</p>';
						break;
					case 'rejected':
						/* translators: %s: order number */
						$subject = sprintf( __( 'About your refund request for order #%s', 'atelier-irisee-master-plugin' ), $number );
						/* translators: %s: customer name */
						$body = '<p>' . esc_html( sprintf( __( 'Hello %s, unfortunately we cannot approve your refund request.', 'atelier-irisee-master-plugin' ), $name ) ) . '</p>';
						break;
					default:
						/* translators: %s: order number */
						$subject = sprintf( __( 'We received your refund request for order #%s', 'atelier-irisee-master-plugin' ), $number );
						/* translators: 1: customer name, 2: amount */
						$body = '<p>' . esc_html( sprintf( __( 'Hello %1$s, we received your request for a refund of %2$s. We will look at it as soon as possible and let you know by email.', 'atelier-irisee-master-plugin' ), $name, $amount ) ) . '</p>';
				}
				if ( ! empty( $request['answer'] ) && in_array( $type, array( 'approved', 'rejected' ), true ) ) {
					$body .= '<p><em>' . nl2br( esc_html( $request['answer'] ) ) . '</em></p>';
				}
				return array( $subject, $body );
			}
		);
	}
}
