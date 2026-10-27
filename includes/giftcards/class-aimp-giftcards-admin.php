<?php
/**
 * Gift cards in the WordPress admin: list, edit screen (balance, status, ledger), manual creation,
 * settings (designs and options), printing, resending and "mark as sent".
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Giftcards_Admin {

	const SETTINGS_PAGE = 'aimp-giftcard-settings';
	const NEW_PAGE      = 'aimp-giftcard-new';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 62 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		$type = AIMP_Giftcards::POST_TYPE;
		add_filter( 'manage_' . $type . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . $type . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters' ) );
		add_action( 'add_meta_boxes_' . $type, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . $type, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . $type, array( __CLASS__, 'bulk_actions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		foreach ( array( 'save_settings', 'create', 'resend', 'mark_sent' ) as $action ) {
			add_action( 'admin_post_aimp_gc_' . $action, array( __CLASS__, $action ) );
		}
		add_action( 'admin_post_aimp_gc_print', array( __CLASS__, 'print_card' ) );
	}

	private static function check( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( $nonce_action ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) );
		}
	}

	private static function back( $url, $notice ) {
		wp_safe_redirect( add_query_arg( 'aimp_gc_notice', $notice, $url ) );
		exit;
	}

	public static function print_url( $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=aimp_gc_print&id=' . (int) $id ), 'aimp_gc_print_' . (int) $id );
	}

	/* ------------------------------------------------------------------
	 * Menu & assets
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Add gift card', 'atelier-irisee-master-plugin' ), __( 'Add gift card', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', self::NEW_PAGE, array( __CLASS__, 'render_new' ) );
		add_submenu_page( 'woocommerce', __( 'Gift card settings', 'atelier-irisee-master-plugin' ), __( 'Gift card settings', 'atelier-irisee-master-plugin' ), 'manage_woocommerce', self::SETTINGS_PAGE, array( __CLASS__, 'render_settings' ) );
	}

	public static function enqueue( $hook_suffix ) {
		$screen = get_current_screen();
		$ours   = false !== strpos( (string) $hook_suffix, self::SETTINGS_PAGE ) || false !== strpos( (string) $hook_suffix, self::NEW_PAGE ) || ( $screen && AIMP_Giftcards::POST_TYPE === $screen->post_type );
		if ( ! $ours ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'aimp-admin', AIMP_PLUGIN_URL . 'assets/css/admin.css', array(), AIMP_VERSION );
		wp_enqueue_script( 'aimp-admin-giftcards', AIMP_PLUGIN_URL . 'assets/js/admin-giftcards.js', array( 'jquery', 'jquery-ui-sortable' ), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-giftcards',
			'aimpAdminGc',
			array(
				'choose'  => __( 'Choose image', 'atelier-irisee-master-plugin' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aimp_gc_admin_preview' ),
				'working' => __( 'Drawing…', 'atelier-irisee-master-plugin' ),
				'error'   => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	public static function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$notice = isset( $_GET['aimp_gc_notice'] ) ? sanitize_key( wp_unslash( $_GET['aimp_gc_notice'] ) ) : '';
		$texts  = array(
			'saved'   => __( 'Settings saved.', 'atelier-irisee-master-plugin' ),
			'created' => __( 'Gift card created.', 'atelier-irisee-master-plugin' ),
			'resent'  => __( 'The gift card email was sent again.', 'atelier-irisee-master-plugin' ),
			'sent'    => __( 'The gift card is marked as sent by post.', 'atelier-irisee-master-plugin' ),
			'invalid' => __( 'Please fill in a valid amount and email address.', 'atelier-irisee-master-plugin' ),
		);
		if ( isset( $texts[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', 'invalid' === $notice ? 'error' : 'success', esc_html( $texts[ $notice ] ) );
		}
	}

	/* ------------------------------------------------------------------
	 * List
	 * ------------------------------------------------------------------ */

	public static function columns() {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => __( 'Code', 'atelier-irisee-master-plugin' ),
			'aimp_val'  => __( 'Balance / value', 'atelier-irisee-master-plugin' ),
			'aimp_to'   => __( 'For', 'atelier-irisee-master-plugin' ),
			'aimp_del'  => __( 'Delivery', 'atelier-irisee-master-plugin' ),
			'aimp_stat' => __( 'Status', 'atelier-irisee-master-plugin' ),
			'aimp_exp'  => __( 'Valid until', 'atelier-irisee-master-plugin' ),
			'aimp_ord'  => __( 'Order', 'atelier-irisee-master-plugin' ),
			'date'      => __( 'Created', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function column( $column, $post_id ) {
		$card = AIMP_Giftcards::get( $post_id );
		if ( ! $card ) {
			return;
		}
		switch ( $column ) {
			case 'aimp_val':
				echo wp_kses_post( wc_price( $card['balance'] ) . ' / ' . wc_price( $card['amount'] ) );
				break;
			case 'aimp_to':
				$name  = $card['recipient_name'] ? $card['recipient_name'] : '';
				$email = 'email_other' === $card['delivery'] ? $card['recipient_email'] : $card['purchaser_email'];
				echo esc_html( trim( $name . ' ' . ( $email ? '<' . $email . '>' : '' ) ) );
				break;
			case 'aimp_del':
				echo esc_html( AIMP_Giftcards::delivery_label( $card['delivery'] ) );
				if ( $card['send_date'] && ! $card['sent'] && 'post' !== $card['delivery'] ) {
					/* translators: %s: date */
					echo '<br><small>' . esc_html( sprintf( __( 'Sends on %s', 'atelier-irisee-master-plugin' ), AIMP_I18n::date( strtotime( $card['send_date'] ) ) ) ) . '</small>';
				}
				break;
			case 'aimp_stat':
				echo '<span class="aimp-gc-status">' . esc_html( AIMP_Giftcards::status_label( $card ) ) . '</span>';
				break;
			case 'aimp_exp':
				echo $card['expires'] ? esc_html( AIMP_I18n::date( strtotime( $card['expires'] ) ) ) : '—';
				break;
			case 'aimp_ord':
				$order = $card['order_id'] ? wc_get_order( $card['order_id'] ) : null;
				echo $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '—';
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( AIMP_Giftcards::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		$card = AIMP_Giftcards::get( $post->ID );
		unset( $actions['inline hide-if-no-js'] );
		$actions['aimp_print'] = '<a href="' . esc_url( self::print_url( $post->ID ) ) . '" target="_blank">' . esc_html__( 'Print', 'atelier-irisee-master-plugin' ) . '</a>';
		if ( 'post' !== $card['delivery'] ) {
			$actions['aimp_resend'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_gc_resend&id=' . $post->ID ), 'aimp_gc_resend_' . $post->ID ) ) . '">' . esc_html__( 'Resend email', 'atelier-irisee-master-plugin' ) . '</a>';
		} elseif ( ! $card['shipped'] ) {
			$actions['aimp_sent'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_gc_mark_sent&id=' . $post->ID ), 'aimp_gc_mark_sent_' . $post->ID ) ) . '">' . esc_html__( 'Mark as sent', 'atelier-irisee-master-plugin' ) . '</a>';
		}
		return $actions;
	}

	public static function bulk_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	private static function status_filters() {
		return array(
			'active'   => __( 'Active', 'atelier-irisee-master-plugin' ),
			'used'     => __( 'Used up', 'atelier-irisee-master-plugin' ),
			'expired'  => __( 'Expired', 'atelier-irisee-master-plugin' ),
			'disabled' => __( 'Disabled', 'atelier-irisee-master-plugin' ),
			'to_send'  => __( 'To send by post', 'atelier-irisee-master-plugin' ),
		);
	}

	public static function filters( $post_type ) {
		if ( AIMP_Giftcards::POST_TYPE !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$current = isset( $_GET['aimp_gc_status'] ) ? sanitize_key( wp_unslash( $_GET['aimp_gc_status'] ) ) : '';
		echo '<select name="aimp_gc_status"><option value="">' . esc_html__( 'All statuses', 'atelier-irisee-master-plugin' ) . '</option>';
		foreach ( self::status_filters() as $key => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public static function apply_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || AIMP_Giftcards::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$status = isset( $_GET['aimp_gc_status'] ) ? sanitize_key( wp_unslash( $_GET['aimp_gc_status'] ) ) : '';
		$today  = current_time( 'Y-m-d' );
		$map    = array(
			'active'   => array(
				'relation' => 'AND',
				array( 'key' => '_aimp_gc_status', 'value' => 'active' ),
				array( 'key' => '_aimp_gc_balance', 'value' => 0, 'compare' => '>', 'type' => 'DECIMAL(10,2)' ),
			),
			'used'     => array( array( 'key' => '_aimp_gc_balance', 'value' => 0, 'compare' => '<=', 'type' => 'DECIMAL(10,2)' ) ),
			'expired'  => array(
				'relation' => 'AND',
				array( 'key' => '_aimp_gc_expires', 'value' => '', 'compare' => '!=' ),
				array( 'key' => '_aimp_gc_expires', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ),
			),
			'disabled' => array( array( 'key' => '_aimp_gc_status', 'value' => 'disabled' ) ),
			'to_send'  => array(
				'relation' => 'AND',
				array( 'key' => '_aimp_gc_delivery', 'value' => 'post' ),
				array( 'key' => '_aimp_gc_shipped', 'value' => '0' ),
			),
		);
		if ( isset( $map[ $status ] ) ) {
			$query->set( 'meta_query', $map[ $status ] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
	}

	/* ------------------------------------------------------------------
	 * Edit screen
	 * ------------------------------------------------------------------ */

	public static function meta_boxes() {
		add_meta_box( 'aimp_gc_details', __( 'Gift card', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_details' ), AIMP_Giftcards::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'aimp_gc_ledger', __( 'History', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_ledger' ), AIMP_Giftcards::POST_TYPE, 'normal', 'default' );
	}

	public static function render_details( $post ) {
		$card = AIMP_Giftcards::get( $post->ID );
		if ( ! $card || ! $card['code'] ) {
			echo '<p>' . esc_html__( 'Use "Add gift card" in the WooCommerce menu to create a gift card.', 'atelier-irisee-master-plugin' ) . '</p>';
			return;
		}
		wp_nonce_field( 'aimp_gc_edit', 'aimp_gc_edit_nonce' );
		$order = $card['order_id'] ? wc_get_order( $card['order_id'] ) : null;
		?>
		<p class="aimp-gc-code-big"><?php echo esc_html( $card['code'] ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( self::print_url( $post->ID ) ); ?>" target="_blank"><?php esc_html_e( 'Print', 'atelier-irisee-master-plugin' ); ?></a>
			<?php if ( 'post' !== $card['delivery'] ) : ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aimp_gc_resend&id=' . $post->ID ), 'aimp_gc_resend_' . $post->ID ) ); ?>"><?php esc_html_e( 'Resend email', 'atelier-irisee-master-plugin' ); ?></a>
			<?php endif; ?>
		</p>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Balance / value', 'atelier-irisee-master-plugin' ); ?></th><td><strong><?php echo wp_kses_post( wc_price( $card['balance'] ) ); ?></strong> / <?php echo wp_kses_post( wc_price( $card['amount'] ) ); ?></td></tr>
			<tr>
				<th><label for="aimp_gc_adjust"><?php esc_html_e( 'Change balance', 'atelier-irisee-master-plugin' ); ?></label></th>
				<td>
					<input type="number" step="0.01" id="aimp_gc_adjust" name="aimp_gc_adjust" class="small-text" placeholder="-10 / 10">
					<input type="text" name="aimp_gc_adjust_reason" class="regular-text" placeholder="<?php esc_attr_e( 'Reason (shown in the history)', 'atelier-irisee-master-plugin' ); ?>">
					<p class="description"><?php esc_html_e( 'A negative number lowers the balance, a positive number raises it.', 'atelier-irisee-master-plugin' ); ?></p>
				</td>
			</tr>
			<tr><th><?php esc_html_e( 'Status', 'atelier-irisee-master-plugin' ); ?></th><td>
				<select name="aimp_gc_status">
					<option value="active" <?php selected( $card['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'atelier-irisee-master-plugin' ); ?></option>
					<option value="disabled" <?php selected( $card['status'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'atelier-irisee-master-plugin' ); ?></option>
				</select>
				<span class="description"><?php echo esc_html( AIMP_Giftcards::status_label( $card ) ); ?></span>
			</td></tr>
			<tr><th><label for="aimp_gc_expires"><?php esc_html_e( 'Valid until', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="date" id="aimp_gc_expires" name="aimp_gc_expires" value="<?php echo esc_attr( $card['expires'] ); ?>"> <span class="description"><?php esc_html_e( 'Empty = never expires.', 'atelier-irisee-master-plugin' ); ?></span></td></tr>
			<tr><th><?php esc_html_e( 'Design', 'atelier-irisee-master-plugin' ); ?></th><td>
				<select name="aimp_gc_design">
					<?php foreach ( AIMP_Giftcards::designs( true ) as $id => $design ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $card['design'], $id ); ?>><?php echo esc_html( $design['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Delivery', 'atelier-irisee-master-plugin' ); ?></th><td>
				<?php echo esc_html( AIMP_Giftcards::delivery_label( $card['delivery'] ) ); ?>
				<?php if ( 'post' === $card['delivery'] ) : ?>
					&nbsp; <label><input type="checkbox" name="aimp_gc_shipped" value="1" <?php checked( $card['shipped'] ); ?>> <?php esc_html_e( 'Sent by post', 'atelier-irisee-master-plugin' ); ?></label>
				<?php elseif ( $card['sent'] ) : ?>
					<?php /* translators: %s: date and time */ ?>
					&nbsp; <span class="description"><?php echo esc_html( sprintf( __( 'Emailed on %s', 'atelier-irisee-master-plugin' ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $card['sent'] ) ) ); ?></span>
				<?php endif; ?>
			</td></tr>
			<tr><th><label for="aimp_gc_recipient_name"><?php esc_html_e( 'Recipient name', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="text" id="aimp_gc_recipient_name" name="aimp_gc_recipient_name" class="regular-text" value="<?php echo esc_attr( $card['recipient_name'] ); ?>"></td></tr>
			<tr><th><label for="aimp_gc_recipient_email"><?php esc_html_e( 'Recipient email', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="email" id="aimp_gc_recipient_email" name="aimp_gc_recipient_email" class="regular-text" value="<?php echo esc_attr( $card['recipient_email'] ); ?>"></td></tr>
			<tr><th><label for="aimp_gc_greeting"><?php esc_html_e( 'Greeting', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="text" id="aimp_gc_greeting" name="aimp_gc_greeting" class="regular-text" maxlength="80" value="<?php echo esc_attr( AIMP_Giftcards_Render::greeting( $card ) ); ?>"></td></tr>
			<tr><th><label for="aimp_gc_message"><?php esc_html_e( 'Message', 'atelier-irisee-master-plugin' ); ?></label></th><td><textarea id="aimp_gc_message" name="aimp_gc_message" class="large-text" rows="3" maxlength="300"><?php echo esc_textarea( $card['message'] ); ?></textarea></td></tr>
			<tr><th><?php esc_html_e( 'Buyer', 'atelier-irisee-master-plugin' ); ?></th><td><?php echo esc_html( $card['purchaser_email'] ? $card['purchaser_email'] : '—' ); ?><?php echo $order ? ' · <a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Order', 'atelier-irisee-master-plugin' ) . ' #' . esc_html( $order->get_order_number() ) . '</a>' : ''; ?></td></tr>
		</table>
		<?php
	}

	public static function render_ledger( $post ) {
		$card = AIMP_Giftcards::get( $post->ID );
		if ( ! $card || ! $card['ledger'] ) {
			echo '<p>—</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Change', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Balance', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'What happened', 'atelier-irisee-master-plugin' ) . '</th></tr></thead><tbody>';
		foreach ( array_reverse( $card['ledger'] ) as $row ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['date'] ) ),
				$row['change'] ? wp_kses_post( ( $row['change'] > 0 ? '+' : '−' ) . wc_price( abs( $row['change'] ) ) ) : '—',
				wp_kses_post( wc_price( $row['balance'] ) ),
				esc_html( $row['note'] )
			);
		}
		echo '</tbody></table>';
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['aimp_gc_edit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aimp_gc_edit_nonce'] ) ), 'aimp_gc_edit' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$card = AIMP_Giftcards::get( $post_id );
		if ( ! $card ) {
			return;
		}

		$status = isset( $_POST['aimp_gc_status'] ) && 'disabled' === $_POST['aimp_gc_status'] ? 'disabled' : 'active';
		if ( $status !== $card['status'] ) {
			AIMP_Giftcards::set_status( $post_id, $status, 'disabled' === $status ? __( 'Disabled by the shop', 'atelier-irisee-master-plugin' ) : __( 'Activated by the shop', 'atelier-irisee-master-plugin' ) );
		}

		$expires = isset( $_POST['aimp_gc_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_gc_expires'] ) ) : '';
		update_post_meta( $post_id, '_aimp_gc_expires', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ? $expires : '' );

		$designs = AIMP_Giftcards::designs( true );
		$design  = isset( $_POST['aimp_gc_design'] ) ? sanitize_key( wp_unslash( $_POST['aimp_gc_design'] ) ) : '';
		if ( isset( $designs[ $design ] ) ) {
			update_post_meta( $post_id, '_aimp_gc_design', $design );
		}

		update_post_meta( $post_id, '_aimp_gc_recipient_name', isset( $_POST['aimp_gc_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_gc_recipient_name'] ) ) : '' );
		update_post_meta( $post_id, '_aimp_gc_recipient_email', isset( $_POST['aimp_gc_recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['aimp_gc_recipient_email'] ) ) : '' );
		update_post_meta( $post_id, '_aimp_gc_message', isset( $_POST['aimp_gc_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['aimp_gc_message'] ) ) : '' );
		// The greeting: only stored when it differs from the theme's (so the theme's stays in use otherwise).
		$greeting = isset( $_POST['aimp_gc_greeting'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_gc_greeting'] ) ) : null;
		if ( null !== $greeting && $greeting !== AIMP_Giftcards_Render::greeting( AIMP_Giftcards::get( $post_id ) ) ) {
			update_post_meta( $post_id, '_aimp_gc_greeting', $greeting );
		}

		if ( 'post' === $card['delivery'] ) {
			$shipped = ! empty( $_POST['aimp_gc_shipped'] );
			if ( $shipped !== $card['shipped'] ) {
				update_post_meta( $post_id, '_aimp_gc_shipped', $shipped ? 1 : 0 );
			}
		}

		$adjust = isset( $_POST['aimp_gc_adjust'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['aimp_gc_adjust'] ) ) ) : 0;
		if ( 0.0 !== $adjust ) {
			$reason = isset( $_POST['aimp_gc_adjust_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['aimp_gc_adjust_reason'] ) ) : '';
			AIMP_Giftcards::adjust( $post_id, $adjust, $reason ? $reason : __( 'Balance changed by the shop', 'atelier-irisee-master-plugin' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	public static function resend() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		self::check( 'aimp_gc_resend_' . $id );
		AIMP_Giftcards::deliver( $id, true );
		self::back( admin_url( 'edit.php?post_type=' . AIMP_Giftcards::POST_TYPE ), 'resent' );
	}

	public static function mark_sent() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		self::check( 'aimp_gc_mark_sent_' . $id );
		update_post_meta( $id, '_aimp_gc_shipped', 1 );
		self::back( admin_url( 'edit.php?post_type=' . AIMP_Giftcards::POST_TYPE ), 'sent' );
	}

	public static function print_card() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		self::check( 'aimp_gc_print_' . $id );
		$card = AIMP_Giftcards::get( $id );
		if ( ! $card ) {
			wp_die( esc_html__( 'This gift card does not exist.', 'atelier-irisee-master-plugin' ) );
		}
		AIMP_I18n::with_language(
			$card['lang'],
			function () use ( $card ) {
				$drawn = (bool) AIMP_Giftcards_Render::files( $card );
				?>
				<!doctype html>
				<html <?php language_attributes(); ?>>
				<head>
					<meta charset="<?php bloginfo( 'charset' ); ?>">
					<title><?php echo esc_html( $card['code'] ); ?></title>
					<style>
						body { margin: 0; padding: 32px; font-family: Georgia, serif; background: #fff; }
						.aimp-print-bar { max-width: 560px; margin: 0 auto 20px; text-align: right; }
						.aimp-print-bar button { padding: 10px 22px; border: 3px double #b38f4f; border-radius: 100px; background: #fff; color: #b38f4f; font: inherit; font-weight: bold; cursor: pointer; }
						.aimp-print-side { max-width: 740px; margin: 0 auto 24px; }
						.aimp-print-side img { display: block; width: 100%; height: auto; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
						@media print { .aimp-print-bar { display: none; } body { padding: 0; } @page { margin: 12mm; } }
						<?php if ( $drawn ) : ?>
						@media print { .aimp-print-side { max-width: none; margin: 0; page-break-after: always; break-after: page; } .aimp-print-side img { width: <?php echo esc_html( AIMP_Giftcards_Render::mm()[0] ); ?>mm; height: <?php echo esc_html( AIMP_Giftcards_Render::mm()[1] ); ?>mm; box-shadow: none; } @page { size: <?php echo esc_html( AIMP_Giftcards_Render::mm()[0] ); ?>mm <?php echo esc_html( AIMP_Giftcards_Render::mm()[1] ); ?>mm; margin: 0; } }
						<?php endif; ?>
					</style>
				</head>
				<body>
					<div class="aimp-print-bar"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'atelier-irisee-master-plugin' ); ?></button></div>
					<?php if ( $drawn ) : ?>
						<?php // Both sides, one per A6 page: print on both sides of the paper (flip on the long edge). ?>
						<div class="aimp-print-side"><img src="<?php echo esc_url( AIMP_Giftcards_Render::url( $card, 'front', true ) ); ?>" alt=""></div>
						<div class="aimp-print-side"><img src="<?php echo esc_url( AIMP_Giftcards_Render::url( $card, 'back', true ) ); ?>" alt=""></div>
					<?php else : ?>
						<?php echo AIMP_Giftcards::card_html( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template. ?>
					<?php endif; ?>
				</body>
				</html>
				<?php
			}
		);
		exit;
	}

	/* ------------------------------------------------------------------
	 * Add gift card (manual)
	 * ------------------------------------------------------------------ */

	public static function render_new() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add gift card', 'atelier-irisee-master-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Create a gift card by hand, for example as a present or to make up for a problem.', 'atelier-irisee-master-plugin' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aimp_gc_create">
				<?php wp_nonce_field( 'aimp_gc_create' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="aimp_new_amount"><?php esc_html_e( 'Value', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="number" step="0.01" min="0.01" id="aimp_new_amount" name="amount" class="small-text" required> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></td></tr>
					<tr><th><label for="aimp_new_design"><?php esc_html_e( 'Design', 'atelier-irisee-master-plugin' ); ?></label></th><td>
						<select id="aimp_new_design" name="design">
							<?php foreach ( AIMP_Giftcards::designs() as $id => $design ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $design['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th><label for="aimp_new_name"><?php esc_html_e( 'Recipient name', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="text" id="aimp_new_name" name="recipient_name" class="regular-text"></td></tr>
					<tr><th><label for="aimp_new_email"><?php esc_html_e( 'Recipient email', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="email" id="aimp_new_email" name="recipient_email" class="regular-text"></td></tr>
					<tr><th><label for="aimp_new_message"><?php esc_html_e( 'Message', 'atelier-irisee-master-plugin' ); ?></label></th><td><textarea id="aimp_new_message" name="message" class="large-text" rows="3" maxlength="300"></textarea></td></tr>
					<tr><th><?php esc_html_e( 'Language of the email', 'atelier-irisee-master-plugin' ); ?></th><td>
						<select name="lang">
							<?php foreach ( AIMP_I18n::languages() as $code => $language ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( AIMP_I18n::default_language(), $code ); ?>><?php echo esc_html( $language['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th><?php esc_html_e( 'Email', 'atelier-irisee-master-plugin' ); ?></th><td><label><input type="checkbox" name="send" value="1" checked> <?php esc_html_e( 'Email the gift card to the recipient now', 'atelier-irisee-master-plugin' ); ?></label></td></tr>
				</table>
				<?php submit_button( __( 'Create gift card', 'atelier-irisee-master-plugin' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function create() {
		self::check( 'aimp_gc_create' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in check().
		$amount = isset( $_POST['amount'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
		$email  = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) ) : '';
		$send   = ! empty( $_POST['send'] );
		if ( $amount <= 0 || ( $send && ! is_email( $email ) ) ) {
			self::back( admin_url( 'admin.php?page=' . self::NEW_PAGE ), 'invalid' );
		}
		$id = AIMP_Giftcards::create(
			array(
				'amount'          => $amount,
				'design'          => isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '',
				'delivery'        => 'email_other',
				'recipient_name'  => isset( $_POST['recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) ) : '',
				'recipient_email' => $email,
				'message'         => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
				'sender_name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'lang'            => isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '',
				'note'            => __( 'Created by the shop', 'atelier-irisee-master-plugin' ),
			)
		);
		// phpcs:enable
		if ( $id && $send ) {
			AIMP_Giftcards::deliver( $id, true );
		}
		self::back( admin_url( 'post.php?post=' . $id . '&action=edit' ), 'created' );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$designs = (array) AIMP_Giftcards::opt( 'designs' );
		$product = wc_get_product( (int) AIMP_Giftcards::opt( 'product_id' ) );
		$o       = function ( $key ) {
			return AIMP_Giftcards::opt( $key );
		};
		?>
		<div class="wrap aimp-gc-settings">
			<h1><?php esc_html_e( 'Gift card settings', 'atelier-irisee-master-plugin' ); ?></h1>

			<h2><?php esc_html_e( 'Gift card product', 'atelier-irisee-master-plugin' ); ?></h2>
			<?php if ( $product ) : ?>
				<p>
					<?php
					/* translators: %s: product name */
					printf( esc_html__( 'Your gift card product is %s.', 'atelier-irisee-master-plugin' ), '<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . esc_html( $product->get_name() ) . '</a>' );
					?>
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>" target="_blank"><?php esc_html_e( 'View in the shop', 'atelier-irisee-master-plugin' ); ?></a>
				</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="aimp_gc_create_product">
					<?php wp_nonce_field( 'aimp_gc_create_product' ); ?>
					<p><?php esc_html_e( 'Add your designs below first, then create the product.', 'atelier-irisee-master-plugin' ); ?></p>
					<?php submit_button( __( 'Create gift card product', 'atelier-irisee-master-plugin' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aimp_gc_save_settings">
				<?php wp_nonce_field( 'aimp_gc_save_settings' ); ?>

				<h2><?php esc_html_e( 'Designs', 'atelier-irisee-master-plugin' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Customers choose one of the active designs. Each design is a theme: a front (its picture with your logo), a back with the card details and a greeting, and its own email texts. Customers receive both sides as pictures and as a PDF.', 'atelier-irisee-master-plugin' ); ?></p>
				<?php if ( ! AIMP_Giftcards_Render::available() ) : ?>
					<div class="notice notice-warning inline"><p><?php echo esc_html( AIMP_Giftcards_Render::unavailable_text() ); ?></p></div>
				<?php endif; ?>
				<?php $aimp_logo = (int) $o( 'logo_id' ) ? wp_get_attachment_image_url( (int) $o( 'logo_id' ), 'thumbnail' ) : ''; ?>
				<p class="aimp-gc-logo-row">
					<strong><?php esc_html_e( 'Logo on the cards', 'atelier-irisee-master-plugin' ); ?></strong>
					<span class="aimp-gc-design-preview aimp-gc-logo-preview"><?php echo $aimp_logo ? '<img src="' . esc_url( $aimp_logo ) . '" alt="">' : ''; ?></span>
					<input type="hidden" class="aimp-gc-logo-id" name="logo_id" value="<?php echo esc_attr( (int) $o( 'logo_id' ) ); ?>">
					<button type="button" class="button aimp-gc-logo-choose"><?php esc_html_e( 'Choose logo', 'atelier-irisee-master-plugin' ); ?></button>
					<button type="button" class="button-link aimp-gc-logo-remove"><?php esc_html_e( 'Use the Divi logo', 'atelier-irisee-master-plugin' ); ?></button>
					<span class="description"><?php esc_html_e( 'A PNG with a transparent background works best. Empty = the Divi logo.', 'atelier-irisee-master-plugin' ); ?></span>
				</p>
				<div class="aimp-gc-designs-admin">
					<ul class="aimp-gc-design-list">
						<?php foreach ( array_values( $designs ) as $i => $design ) : ?>
							<?php self::design_row( $i, $design ); ?>
						<?php endforeach; ?>
					</ul>
					<template class="aimp-gc-design-template"><?php self::design_row( '__i__', array() ); ?></template>
					<p><button type="button" class="button aimp-gc-design-add"><?php esc_html_e( 'Add design', 'atelier-irisee-master-plugin' ); ?></button></p>
				</div>

				<h2><?php esc_html_e( 'Amounts and options', 'atelier-irisee-master-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="aimp_gc_amounts"><?php esc_html_e( 'Amounts to choose from', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="text" id="aimp_gc_amounts" name="amounts" value="<?php echo esc_attr( $o( 'amounts' ) ); ?>" class="regular-text"><p class="description"><?php esc_html_e( 'Comma separated, for example 25,50,75,100.', 'atelier-irisee-master-plugin' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Own amount', 'atelier-irisee-master-plugin' ); ?></th><td>
						<label><input type="checkbox" name="custom_amount" value="1" <?php checked( 1, (int) $o( 'custom_amount' ) ); ?>> <?php esc_html_e( 'Customers may enter their own amount', 'atelier-irisee-master-plugin' ); ?></label><br>
						<label><?php esc_html_e( 'Minimum', 'atelier-irisee-master-plugin' ); ?> <input type="number" step="5" min="10" name="min_amount" value="<?php echo esc_attr( $o( 'min_amount' ) ); ?>" class="small-text"></label>
						<label><?php esc_html_e( 'Maximum', 'atelier-irisee-master-plugin' ); ?> <input type="number" step="0.01" min="1" name="max_amount" value="<?php echo esc_attr( $o( 'max_amount' ) ); ?>" class="small-text"></label>
					</td></tr>
					<tr><th><label for="aimp_gc_expiry"><?php esc_html_e( 'Valid for (months)', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="number" min="0" max="120" id="aimp_gc_expiry" name="expiry_months" value="<?php echo esc_attr( $o( 'expiry_months' ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( '0 = never expires.', 'atelier-irisee-master-plugin' ); ?></span></td></tr>
					<tr><th><?php esc_html_e( 'Delivery options', 'atelier-irisee-master-plugin' ); ?></th><td>
						<label><input type="checkbox" name="allow_recipient" value="1" <?php checked( 1, (int) $o( 'allow_recipient' ) ); ?>> <?php esc_html_e( 'Customers can have it emailed to someone else', 'atelier-irisee-master-plugin' ); ?></label><br>
						<label><input type="checkbox" name="allow_send_date" value="1" <?php checked( 1, (int) $o( 'allow_send_date' ) ); ?>> <?php esc_html_e( 'Customers can choose the date the email is sent', 'atelier-irisee-master-plugin' ); ?></label><br>
						<label><input type="checkbox" name="allow_post" value="1" <?php checked( 1, (int) $o( 'allow_post' ) ); ?>> <?php esc_html_e( 'Customers can order a printed gift card by post', 'atelier-irisee-master-plugin' ); ?></label>
					</td></tr>
					<tr><th><label for="aimp_gc_fee"><?php esc_html_e( 'Printing fee for a card by post', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="number" step="0.01" min="0" id="aimp_gc_fee" name="post_fee" value="<?php echo esc_attr( $o( 'post_fee' ) ); ?>" class="small-text"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?> <span class="description"><?php esc_html_e( 'Comes on top of the card value; the normal shipping costs also apply.', 'atelier-irisee-master-plugin' ); ?></span></td></tr>
					<tr><th><label for="aimp_gc_prefix"><?php esc_html_e( 'Code starts with', 'atelier-irisee-master-plugin' ); ?></label></th><td><input type="text" id="aimp_gc_prefix" name="code_prefix" value="<?php echo esc_attr( $o( 'code_prefix' ) ); ?>" class="small-text" maxlength="8"> <span class="description"><?php esc_html_e( 'Codes look like IRIS-7KQ2-M9XD-4HBT.', 'atelier-irisee-master-plugin' ); ?></span></td></tr>
				</table>
				<?php submit_button( __( 'Save settings', 'atelier-irisee-master-plugin' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function design_row( $index, $design ) {
		$design = wp_parse_args( $design, AIMP_Giftcards_Render::theme_defaults() );
		$base   = 'designs[' . $index . ']';
		$image  = $design['image_id'] ? wp_get_attachment_image_url( (int) $design['image_id'], 'thumbnail' ) : '';
		$back   = $design['back_image_id'] ? wp_get_attachment_image_url( (int) $design['back_image_id'], 'thumbnail' ) : '';
		$name   = function ( $key ) use ( $base ) {
			return esc_attr( $base . '[' . $key . ']' );
		};
		$colors = class_exists( 'AIMP_Design' ) ? AIMP_Design::divi_colors() : array();
		$color  = function ( $key, $label ) use ( $design, $name, $colors ) {
			?>
			<span class="aimp-gc-color">
				<label><?php echo esc_html( $label ); ?> <input type="color" name="<?php echo $name( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $name. ?>" value="<?php echo esc_attr( $design[ $key ] ); ?>"></label>
				<?php foreach ( $colors as $color ) : ?>
					<button type="button" class="aimp-gc-swatch" data-color="<?php echo esc_attr( $color[1] ); ?>" title="<?php echo esc_attr( $color[0] . ' · ' . $color[1] ); ?>" style="background:<?php echo esc_attr( $color[1] ); ?>"><span class="screen-reader-text"><?php echo esc_html( $color[0] ); ?></span></button>
				<?php endforeach; ?>
			</span>
			<?php
		};
		?>
		<li class="aimp-gc-design-row">
			<span class="aimp-field-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'atelier-irisee-master-plugin' ); ?>"></span>
			<span class="aimp-gc-design-preview"><?php echo $image ? '<img src="' . esc_url( $image ) . '" alt="">' : ''; ?></span>
			<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $design['id'] ); ?>">
			<input type="hidden" class="aimp-gc-design-image" name="<?php echo esc_attr( $base ); ?>[image_id]" value="<?php echo esc_attr( $design['image_id'] ); ?>">
			<button type="button" class="button aimp-gc-design-choose"><?php esc_html_e( 'Choose image', 'atelier-irisee-master-plugin' ); ?></button>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( $design['name'] ); ?>" placeholder="<?php esc_attr_e( 'Name, e.g. Golden ribbon', 'atelier-irisee-master-plugin' ); ?>" class="regular-text">
			<label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[active]" value="1" <?php checked( 1, (int) $design['active'] ); ?>> <?php esc_html_e( 'Active', 'atelier-irisee-master-plugin' ); ?></label>
			<button type="button" class="button-link button-link-delete aimp-gc-design-remove"><?php esc_html_e( 'Delete', 'atelier-irisee-master-plugin' ); ?></button>
			<details class="aimp-gc-theme">
				<summary><?php esc_html_e( 'Card design: front, back, greeting and email', 'atelier-irisee-master-plugin' ); ?></summary>
				<div class="aimp-gc-theme-grid">
					<fieldset>
						<legend><?php esc_html_e( 'Back (picture side)', 'atelier-irisee-master-plugin' ); ?></legend>
						<p class="description"><?php esc_html_e( 'The picture above fills the back (A6 landscape, 148 × 105 mm), without anything on it. Use a picture of at least 1748 × 1240 pixels.', 'atelier-irisee-master-plugin' ); ?></p>
						<p>
							<label><?php esc_html_e( 'Focus left / right', 'atelier-irisee-master-plugin' ); ?> <input type="number" min="0" max="100" class="small-text" name="<?php echo $name( 'fx' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $design['fx'] ); ?>"> %</label>
							<label><?php esc_html_e( 'top / bottom', 'atelier-irisee-master-plugin' ); ?> <input type="number" min="0" max="100" class="small-text" name="<?php echo $name( 'fy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $design['fy'] ); ?>"> %</label>
						</p>
					</fieldset>
					<fieldset>
						<legend><?php esc_html_e( 'Front (greeting, amount and code)', 'atelier-irisee-master-plugin' ); ?></legend>
						<p>
							<label><input type="checkbox" name="<?php echo $name( 'text_logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="1" <?php checked( 1, (int) $design['text_logo'] ); ?>> <?php esc_html_e( 'Logo at the top', 'atelier-irisee-master-plugin' ); ?></label>
							<label><?php esc_html_e( 'Size', 'atelier-irisee-master-plugin' ); ?> <input type="number" min="5" max="30" class="small-text" name="<?php echo $name( 'text_logo_size' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $design['text_logo_size'] ); ?>"> %</label>
						</p>
						<p>
							<span class="aimp-gc-design-preview aimp-gc-back-preview"><?php echo $back ? '<img src="' . esc_url( $back ) . '" alt="">' : ''; ?></span>
							<input type="hidden" class="aimp-gc-back-image" name="<?php echo $name( 'back_image_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $design['back_image_id'] ); ?>">
							<button type="button" class="button aimp-gc-back-choose"><?php esc_html_e( 'Background picture', 'atelier-irisee-master-plugin' ); ?></button>
							<button type="button" class="button-link aimp-gc-back-remove"><?php esc_html_e( 'No picture', 'atelier-irisee-master-plugin' ); ?></button>
						</p>
						<p class="description"><?php esc_html_e( 'A calm, light picture, so the text stays readable. Without a picture the background colour is used.', 'atelier-irisee-master-plugin' ); ?></p>
						<p><?php $color( 'back_color', __( 'Background', 'atelier-irisee-master-plugin' ) ); ?></p>
						<p><?php $color( 'text_color', __( 'Text', 'atelier-irisee-master-plugin' ) ); ?></p>
						<p><?php $color( 'accent_color', __( 'Titles, amount and frame', 'atelier-irisee-master-plugin' ) ); ?></p>
						<p><label><input type="checkbox" name="<?php echo $name( 'frame' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="1" <?php checked( 1, (int) $design['frame'] ); ?>> <?php esc_html_e( 'Double line around the back', 'atelier-irisee-master-plugin' ); ?></label></p>
					</fieldset>
					<fieldset class="aimp-gc-theme-texts">
						<legend><?php esc_html_e( 'Texts', 'atelier-irisee-master-plugin' ); ?></legend>
						<p class="description"><?php esc_html_e( 'Write each text as "Dutch | French | English", or one text for all. In the email texts, {sender}, {recipient} and {shop} are filled in. Empty = the standard text.', 'atelier-irisee-master-plugin' ); ?></p>
						<p><label><?php esc_html_e( 'Greeting on the back (customers may change it)', 'atelier-irisee-master-plugin' ); ?><br><input type="text" class="large-text" name="<?php echo $name( 'greeting' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $design['greeting'] ); ?>" placeholder="<?php esc_attr_e( 'Gelukkige verjaardag! | Joyeux anniversaire ! | Happy birthday!', 'atelier-irisee-master-plugin' ); ?>"></label></p>
						<p><label><?php esc_html_e( 'Email subject, card for the buyer', 'atelier-irisee-master-plugin' ); ?><br><input type="text" class="large-text" name="<?php echo $name( 'subject_self' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $design['subject_self'] ); ?>"></label></p>
						<p><label><?php esc_html_e( 'Email subject, card sent to someone else', 'atelier-irisee-master-plugin' ); ?><br><input type="text" class="large-text" name="<?php echo $name( 'subject_other' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $design['subject_other'] ); ?>" placeholder="<?php esc_attr_e( '{sender} wishes you a happy birthday!', 'atelier-irisee-master-plugin' ); ?>"></label></p>
						<p><label><?php esc_html_e( 'Email intro (above the card)', 'atelier-irisee-master-plugin' ); ?><br><textarea class="large-text" rows="3" name="<?php echo $name( 'intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_textarea( $design['intro'] ); ?></textarea></label></p>
					</fieldset>
				</div>
				<p>
					<button type="button" class="button aimp-gc-theme-preview"><?php esc_html_e( 'Preview front and back', 'atelier-irisee-master-plugin' ); ?></button>
					<span class="aimp-gc-theme-msg" role="status"></span>
				</p>
				<div class="aimp-gc-theme-sides" hidden><img alt="" class="aimp-gc-theme-front"><img alt="" class="aimp-gc-theme-back"></div>
			</details>
		</li>
		<?php
	}

	/**
	 * One design row of the settings form, cleaned (also for the preview of unsaved settings).
	 *
	 * @param array $row Posted row (unslashed).
	 * @return array
	 */
	public static function clean_design( $row ) {
		$row   = is_array( $row ) ? $row : array();
		$text  = function ( $key, $long = false ) use ( $row ) {
			$value = isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : '';
			return $long ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		};
		$num   = function ( $key, $min, $max, $default ) use ( $row ) {
			return isset( $row[ $key ] ) && is_numeric( $row[ $key ] ) ? max( $min, min( $max, (int) $row[ $key ] ) ) : $default;
		};
		$hex   = function ( $key, $default ) use ( $row ) {
			$value = isset( $row[ $key ] ) ? sanitize_hex_color( (string) $row[ $key ] ) : '';
			return $value ? strtolower( $value ) : $default;
		};
		$d     = AIMP_Giftcards_Render::theme_defaults();
		return array(
			'id'            => isset( $row['id'] ) && '' !== $row['id'] ? sanitize_key( $row['id'] ) : '',
			'name'          => $text( 'name' ),
			'image_id'      => isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0,
			'active'        => empty( $row['active'] ) ? 0 : 1,
			'fx'            => $num( 'fx', 0, 100, 50 ),
			'fy'            => $num( 'fy', 0, 100, 50 ),
			'text_logo'      => empty( $row['text_logo'] ) ? 0 : 1,
			'text_logo_size' => $num( 'text_logo_size', 5, 30, 13 ),
			'back_image_id' => isset( $row['back_image_id'] ) ? absint( $row['back_image_id'] ) : 0,
			'back_color'    => $hex( 'back_color', $d['back_color'] ),
			'text_color'    => $hex( 'text_color', $d['text_color'] ),
			'accent_color'  => $hex( 'accent_color', $d['accent_color'] ),
			'frame'         => empty( $row['frame'] ) ? 0 : 1,
			'greeting'      => $text( 'greeting' ),
			'subject_self'  => $text( 'subject_self' ),
			'subject_other' => $text( 'subject_other' ),
			'intro'         => $text( 'intro', true ),
		);
	}

	public static function save_settings() {
		self::check( 'aimp_gc_save_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in check().
		$designs = array();
		foreach ( isset( $_POST['designs'] ) && is_array( $_POST['designs'] ) ? wp_unslash( $_POST['designs'] ) : array() as $row ) {
			$design = self::clean_design( $row );
			if ( '' === $design['name'] && ! $design['image_id'] ) {
				continue;
			}
			if ( '' === $design['id'] ) {
				$design['id'] = 'd' . strtolower( wp_generate_password( 8, false, false ) );
			}
			if ( '' === $design['name'] ) {
				$design['name'] = __( 'Design', 'atelier-irisee-master-plugin' );
			}
			$designs[] = $design;
		}
		$num = function ( $key, $default, $min = 0 ) {
			$value = isset( $_POST[ $key ] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : $default;
			return max( $min, $value );
		};
		$amounts = array();
		foreach ( explode( ',', isset( $_POST['amounts'] ) ? sanitize_text_field( wp_unslash( $_POST['amounts'] ) ) : '' ) as $amount ) {
			$amount = (float) trim( $amount );
			if ( $amount > 0 ) {
				$amounts[] = $amount;
			}
		}
		$min = $num( 'min_amount', 10, AIMP_Giftcards::MIN_AMOUNT );
		$max = max( $min, $num( 'max_amount', 500, 1 ) );

		$options = array_merge(
			(array) get_option( AIMP_Giftcards::OPTION, array() ),
			array(
				'designs'         => $designs,
				'amounts'         => implode( ',', array_unique( $amounts ) ),
				'custom_amount'   => empty( $_POST['custom_amount'] ) ? 0 : 1,
				'min_amount'      => $min,
				'max_amount'      => $max,
				'expiry_months'   => (int) min( 120, $num( 'expiry_months', 12 ) ),
				'allow_recipient' => empty( $_POST['allow_recipient'] ) ? 0 : 1,
				'allow_send_date' => empty( $_POST['allow_send_date'] ) ? 0 : 1,
				'allow_post'      => empty( $_POST['allow_post'] ) ? 0 : 1,
				'post_fee'        => $num( 'post_fee', 0 ),
				'logo_id'         => isset( $_POST['logo_id'] ) ? absint( $_POST['logo_id'] ) : 0,
				'code_prefix'     => substr( strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', isset( $_POST['code_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['code_prefix'] ) ) : '' ) ), 0, 8 ),
			)
		);
		// phpcs:enable
		update_option( AIMP_Giftcards::OPTION, $options );
		self::back( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE ), 'saved' );
	}
}
