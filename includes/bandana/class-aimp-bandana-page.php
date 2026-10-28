<?php
/**
 * [atelier_irisee_bandana]: the dog bandana guide page. Everyone sees the introduction, the styles, what you need and
 * the size chart; customers who bought the bandana pattern (or open the link in their order email) see the whole
 * guide and download the guide and the template as PDF, in Dutch, English or French.
 *
 * Also: the "Dog bandana" settings tab, the guide link in orders, and the customizer's "matching dog bandana" question
 * (the pattern plus extra fabric of the kit's own fabric, with a thread and a band to choose).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Bandana_Page {

	const TAG = 'atelier_irisee_bandana';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'download' ), 5 );

		add_filter( 'aimp_settings_own_tabs', array( __CLASS__, 'settings_tab' ) );
		add_action( 'admin_post_aimp_bandana_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );

		// The guide link for the buyers: order page, thank-you page and emails.
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'order_link' ), 5 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_link' ), 5, 4 );

		// The customizer's question at the end.
		add_action( 'wc_ajax_aimp_bandana', array( __CLASS__, 'ajax_offer' ) );
	}

	public static function register_assets() {
		if ( ! wp_style_is( 'aimp-home-blocks', 'registered' ) ) {
			AIMP_Home_Blocks::register_assets();
		}
		wp_register_style( 'aimp-bandana', AIMP_PLUGIN_URL . 'assets/css/bandana.css', array( 'aimp-home-blocks' ), AIMP_VERSION );
		if ( AIMP_Blocks::page_has( array( self::TAG ) ) ) {
			wp_enqueue_style( 'aimp-bandana' );
		}
	}

	/* ------------------------------------------------------------------
	 * The page
	 * ------------------------------------------------------------------ */

	/**
	 * @param array|string $atts Attributes (none).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		if ( ! wp_style_is( 'aimp-bandana', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-bandana' );
		wp_enqueue_script( 'aimp-home-blocks' );

		$full  = AIMP_Bandana::can_view();
		$parts = AIMP_Bandana::guide();
		// A buyer's page is never kept by the page cache (others would see the whole guide).
		if ( $full ) {
			self::no_cache();
		}
		$intro = $parts[0][3];

		$html  = '<section class="aimp-home-block aimp-bandana">';
		$html .= '<header class="aimp-bandana-head"><p class="aimp-maker-kicker">' . esc_html__( 'Sewing pattern and guide', 'atelier-irisee-master-plugin' ) . '</p>' .
			'<h1 class="aimp-block-title aimp-bandana-title"><span>' . esc_html__( 'Dog bandana', 'atelier-irisee-master-plugin' ) . '</span></h1>' .
			'<p class="aimp-bandana-intro">' . esc_html( $intro[0][1] ) . '</p>' . self::block_html( $intro[1] ) . '</header>';

		if ( $full ) {
			$html .= self::downloads_html();
		}

		$locked = array();
		$nav    = '';
		foreach ( array_slice( $parts, 1 ) as $part ) {
			if ( ! $part[2] && ! $full ) {
				$locked[] = $part[1];
				continue;
			}
			$nav  .= '<li><a href="#aimp-bandana-' . esc_attr( $part[0] ) . '">' . esc_html( $part[1] ) . '</a></li>';
			$html .= '<section class="aimp-bandana-part aimp-bandana-part--' . esc_attr( $part[0] ) . '" id="aimp-bandana-' . esc_attr( $part[0] ) . '">' .
				'<h2 class="aimp-block-title"><span>' . esc_html( $part[1] ) . '</span></h2>';
			foreach ( $part[3] as $block ) {
				$html .= self::block_html( $block );
			}
			$html .= '</section>';
		}
		if ( $locked ) {
			$html .= self::locked_html( $locked );
		}
		$html .= '</section>';

		// The table of contents after the head.
		return preg_replace( '#</header>#', '</header><nav class="aimp-bandana-nav" aria-label="' . esc_attr__( 'Contents', 'atelier-irisee-master-plugin' ) . '"><ul>' . $nav . '</ul></nav>', $html, 1 );
	}

	/**
	 * A block of the guide as HTML (see AIMP_Bandana::guide()).
	 *
	 * @param array $b Block.
	 * @return string
	 */
	private static function block_html( $b ) {
		switch ( $b[0] ) {
			case 'p':
				return '<p>' . esc_html( $b[1] ) . '</p>';
			case 'list':
				return '<ul class="aimp-bandana-list"><li>' . implode( '</li><li>', array_map( 'esc_html', $b[1] ) ) . '</li></ul>';
			case 'steps':
				$html = '<ol class="aimp-bandana-steps">';
				foreach ( $b[1] as $step ) {
					$html .= '<li class="aimp-bandana-step"><div class="aimp-bandana-step-text">' .
						( '' !== $step[0] ? '<h3>' . esc_html( $step[0] ) . '</h3>' : '' ) . '<p>' . esc_html( $step[1] ) . '</p></div>' .
						( '' !== $step[2] ? '<figure class="aimp-bandana-fig">' . AIMP_Bandana_Draw::svg( AIMP_Bandana::figure( $step[2] ) ) . '</figure>' : '' ) . '</li>';
				}
				return $html . '</ol>';
			case 'figure':
				return '<figure class="aimp-bandana-fig aimp-bandana-fig--wide">' . AIMP_Bandana_Draw::svg( AIMP_Bandana::figure( $b[1] ) ) .
					( '' !== $b[2] ? '<figcaption>' . esc_html( $b[2] ) . '</figcaption>' : '' ) . '</figure>';
			case 'table':
				$html = '<div class="aimp-bandana-table-wrap"><table class="aimp-bandana-table"><thead><tr>';
				foreach ( $b[1] as $cell ) {
					$html .= '<th scope="col">' . esc_html( $cell ) . '</th>';
				}
				$html .= '</tr></thead><tbody>';
				foreach ( $b[2] as $row ) {
					$html .= '<tr>';
					foreach ( $row as $i => $cell ) {
						$html .= 0 === $i ? '<th scope="row">' . esc_html( $cell ) . '</th>' : '<td data-label="' . esc_attr( $b[1][ $i ] ) . '">' . esc_html( $cell ) . '</td>';
					}
					$html .= '</tr>';
				}
				return $html . '</tbody></table></div>' . ( ! empty( $b[3] ) ? '<p class="aimp-bandana-note">' . esc_html( $b[3] ) . '</p>' : '' );
			case 'facts':
				$html = '<ul class="aimp-bandana-facts">';
				foreach ( $b[1] as $fact ) {
					$html .= '<li><span>' . esc_html( $fact[0] ) . '</span><strong>' . esc_html( $fact[1] ) . '</strong></li>';
				}
				return $html . '</ul>';
			case 'tip':
				return '<p class="aimp-bandana-tip"><strong>' . esc_html__( 'Tip', 'atelier-irisee-master-plugin' ) . '</strong> ' . esc_html( $b[1] ) . '</p>';
			case 'terms':
				$html = '<dl class="aimp-bandana-terms">';
				foreach ( $b[1] as $term ) {
					$html .= '<div><dt>' . esc_html( $term[0] ) . '</dt><dd>' . esc_html( $term[1] ) . '</dd></div>';
				}
				return $html . '</dl>';
		}
		return '';
	}

	/**
	 * Buyers: download the guide and the template (style, size, language).
	 *
	 * @return string
	 */
	private static function downloads_html() {
		$order  = AIMP_Bandana::order_from_link();
		$hidden = '';
		if ( $order ) {
			$hidden = '<input type="hidden" name="aimp_order" value="' . esc_attr( $order->get_id() ) . '"><input type="hidden" name="aimp_key" value="' . esc_attr( $order->get_order_key() ) . '">';
		}
		$select = function ( $name, $label, $options, $chosen ) {
			$html = '<label class="aimp-bandana-field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
			foreach ( $options as $value => $text ) {
				$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $chosen, $value, false ) . '>' . esc_html( $text ) . '</option>';
			}
			return $html . '</select></label>';
		};
		$sizes = array();
		foreach ( AIMP_Bandana::sizes() as $key => $s ) {
			/* translators: 1: size, 2: from, 3: to (cm) */
			$sizes[ $key ] = sprintf( __( '%1$s (neck %2$s–%3$s cm)', 'atelier-irisee-master-plugin' ), $s[0], $s[1], $s[2] );
		}
		$sizes['all'] = __( 'All sizes', 'atelier-irisee-master-plugin' );
		$langs        = array();
		foreach ( AIMP_I18n::languages() as $code => $info ) {
			$langs[ $code ] = $info['name'];
		}
		$action = self::page_url();
		return '<section class="aimp-bandana-downloads" id="aimp-bandana-downloads">' .
			'<h2 class="aimp-bandana-downloads-title">' . esc_html__( 'Your pattern', 'atelier-irisee-master-plugin' ) . '</h2>' .
			'<p>' . esc_html__( 'Download the guide and the template to print on A4 paper. Choose your style and size; "All sizes" puts every size on one template.', 'atelier-irisee-master-plugin' ) . '</p>' .
			'<form class="aimp-bandana-form" method="get" action="' . esc_url( $action ) . '">' . $hidden .
			'<div class="aimp-bandana-fields">' .
			$select( 'style', __( 'Style', 'atelier-irisee-master-plugin' ), AIMP_Bandana::styles(), 'tie' ) .
			$select( 'size', __( 'Size', 'atelier-irisee-master-plugin' ), $sizes, 'm' ) .
			$select( 'pdf_lang', __( 'Language', 'atelier-irisee-master-plugin' ), $langs, AIMP_I18n::current() ) .
			'</div><div class="aimp-bandana-buttons">' .
			'<button type="submit" class="aimp-block-button aimp-block-button--gold" name="aimp_bandana_pdf" value="template">' . esc_html__( 'Download template (PDF)', 'atelier-irisee-master-plugin' ) . '</button>' .
			'<button type="submit" class="aimp-block-button" name="aimp_bandana_pdf" value="guide">' . esc_html__( 'Download guide (PDF)', 'atelier-irisee-master-plugin' ) . '</button>' .
			'</div></form></section>';
	}

	/**
	 * The page's own address (for the download form).
	 */
	private static function page_url() {
		$url = AIMP_Bandana::page_url();
		return '' !== $url ? $url : (string) get_permalink();
	}

	/**
	 * Visitors who did not buy the pattern: what else is inside, and the buy button.
	 *
	 * @param string[] $titles Parts they cannot see yet.
	 * @return string
	 */
	private static function locked_html( $titles ) {
		$product = AIMP_Bandana::product();
		$html    = '<section class="aimp-bandana-locked"><h2 class="aimp-block-title"><span>' . esc_html__( 'The whole pattern', 'atelier-irisee-master-plugin' ) . '</span></h2>' .
			'<p>' . esc_html__( 'The step-by-step guide and the printable template (real size, on A4 sheets) come with the dog bandana pattern, in Dutch, English and French.', 'atelier-irisee-master-plugin' ) . '</p>' .
			'<ul class="aimp-bandana-list aimp-bandana-list--locked"><li>' . implode( '</li><li>', array_map( 'esc_html', $titles ) ) . '</li></ul>';
		if ( $product ) {
			$html .= '<p class="aimp-bandana-price">' . wp_kses_post( $product->get_price_html() ) . '</p>' .
				'<p class="aimp-bandana-buttons"><a class="aimp-block-button aimp-block-button--gold" href="' . esc_url( add_query_arg( 'add-to-cart', $product->get_id(), wc_get_cart_url() ) ) . '" rel="nofollow">' . esc_html__( 'Buy the pattern', 'atelier-irisee-master-plugin' ) . '</a></p>';
		}
		$html .= '<p class="aimp-bandana-note">' . esc_html__( 'Already bought it? Log in, or open the link in your order email.', 'atelier-irisee-master-plugin' ) . '</p>';
		return $html . '</section>';
	}

	/* ------------------------------------------------------------------
	 * PDF downloads
	 * ------------------------------------------------------------------ */

	private static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'aimp bandana guide' );
	}

	public static function download() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a download; who may have it is checked below.
		if ( ! isset( $_GET['aimp_bandana_pdf'] ) ) {
			return;
		}
		$what  = sanitize_key( wp_unslash( $_GET['aimp_bandana_pdf'] ) );
		$style = isset( $_GET['style'] ) ? sanitize_key( wp_unslash( $_GET['style'] ) ) : 'tie';
		$size  = isset( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : 'm';
		$lang  = isset( $_GET['pdf_lang'] ) ? sanitize_key( wp_unslash( $_GET['pdf_lang'] ) ) : '';
		// phpcs:enable
		if ( ! in_array( $what, array( 'guide', 'template' ), true ) ) {
			return;
		}
		self::no_cache();
		if ( ! AIMP_Bandana::can_view() ) {
			wp_die( esc_html__( 'This pattern comes with the dog bandana pattern. Log in, or open the link in your order email.', 'atelier-irisee-master-plugin' ), '', array( 'response' => 403 ) );
		}
		$styles = AIMP_Bandana::styles();
		$style  = isset( $styles[ $style ] ) ? $style : 'tie';
		$size   = 'all' === $size || isset( AIMP_Bandana::sizes()[ $size ] ) ? $size : 'm';
		$lang   = AIMP_I18n::is_valid( $lang ) ? $lang : AIMP_I18n::current();

		$file = AIMP_I18n::with_language(
			$lang,
			function () use ( $what, $style, $size ) {
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				if ( 'guide' === $what ) {
					return array( AIMP_Bandana_Docs::guide(), sanitize_title( __( 'dog-bandana-guide', 'atelier-irisee-master-plugin' ) ) );
				}
				return array( AIMP_Bandana_Docs::template( $style, $size ), sanitize_title( __( 'dog-bandana-template', 'atelier-irisee-master-plugin' ) . '-' . $style . '-' . $size ) );
			}
		);

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $file[1] . '.pdf"' );
		header( 'Content-Length: ' . strlen( $file[0] ) );
		header( 'X-Robots-Tag: noindex' );
		echo $file[0]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a PDF file.
		exit;
	}

	/* ------------------------------------------------------------------
	 * The link for the buyers
	 * ------------------------------------------------------------------ */

	/**
	 * @param WC_Order $order Order.
	 */
	public static function order_link( $order ) {
		$url = $order && AIMP_Bandana::order_has_pattern( $order ) ? AIMP_Bandana::order_link( $order ) : '';
		if ( '' === $url ) {
			return;
		}
		echo '<section class="aimp-bandana-order"><h2>' . esc_html__( 'Your dog bandana pattern', 'atelier-irisee-master-plugin' ) . '</h2><p>' .
			esc_html__( 'The step-by-step guide and the template to print are on the pattern\'s page.', 'atelier-irisee-master-plugin' ) .
			'</p><p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Open the guide', 'atelier-irisee-master-plugin' ) . '</a></p></section>';
	}

	/**
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin To the shop.
	 * @param bool     $plain_text    Plain text email.
	 * @param WC_Email $email         Email.
	 */
	public static function email_link( $order, $sent_to_admin, $plain_text, $email = null ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! AIMP_Bandana::order_has_pattern( $order ) ) {
			return;
		}
		$url = AIMP_Bandana::order_link( $order );
		if ( '' === $url ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Your dog bandana pattern', 'atelier-irisee-master-plugin' ) . "\n" . esc_html__( 'The step-by-step guide and the template to print are on the pattern\'s page.', 'atelier-irisee-master-plugin' ) . "\n" . esc_url_raw( $url ) . "\n\n";
			return;
		}
		echo '<h2>' . esc_html__( 'Your dog bandana pattern', 'atelier-irisee-master-plugin' ) . '</h2><p>' .
			esc_html__( 'The step-by-step guide and the template to print are on the pattern\'s page.', 'atelier-irisee-master-plugin' ) .
			'</p><p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the guide', 'atelier-irisee-master-plugin' ) . '</a></p>';
	}

	/* ------------------------------------------------------------------
	 * Settings (Atelier Irisee → Dog bandana)
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $tabs Tab ID => [ title, callback, after ].
	 * @return array
	 */
	public static function settings_tab( $tabs ) {
		$tabs['aimp_bandana'] = array(
			'title'    => __( 'Dog bandana', 'atelier-irisee-master-plugin' ),
			'callback' => array( __CLASS__, 'render_settings' ),
			'after'    => 'aimp_giftcards',
		);
		return $tabs;
	}

	public static function admin_assets( $hook_suffix ) {
		if ( AIMP_Settings::is_settings_screen( $hook_suffix ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o       = function ( $key ) {
			return AIMP_Bandana::opt( $key );
		};
		$product = $o( 'product' ) ? wc_get_product( $o( 'product' ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a message after saving.
		if ( isset( $_GET['aimp_bandana_saved'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Settings saved.', 'atelier-irisee-master-plugin' ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aimp_bandana_save">
			<?php wp_nonce_field( 'aimp_bandana_save' ); ?>
			<p><?php esc_html_e( 'The dog bandana guide: a page with the shortcode [atelier_irisee_bandana]. Visitors see the introduction and the size chart; customers who bought the bandana pattern see the whole guide and download the guide and the template as PDF.', 'atelier-irisee-master-plugin' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="aimp_bandana_product"><?php esc_html_e( 'Bandana pattern product', 'atelier-irisee-master-plugin' ); ?></label></th>
					<td>
						<select id="aimp_bandana_product" name="product" class="wc-product-search" style="width:min(100%,28rem)" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'atelier-irisee-master-plugin' ); ?>" data-action="woocommerce_json_search_products" data-allow_clear="true">
							<?php if ( $product ) : ?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option>
							<?php endif; ?>
						</select>
						<p class="description"><?php esc_html_e( 'A simple (virtual) product with the price of the pattern. Buying it opens the whole guide.', 'atelier-irisee-master-plugin' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aimp_bandana_page"><?php esc_html_e( 'Guide page', 'atelier-irisee-master-plugin' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'page',
								'id'                => 'aimp_bandana_page',
								'selected'          => (int) $o( 'page' ),
								'show_option_none'  => esc_html__( '— Choose a page —', 'atelier-irisee-master-plugin' ),
								'option_none_value' => 0,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'The page with [atelier_irisee_bandana]: the link in the order emails goes there.', 'atelier-irisee-master-plugin' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'In the customizer', 'atelier-irisee-master-plugin' ); ?></th>
					<td>
						<p><label><input type="checkbox" name="addon" value="1" <?php checked( $o( 'addon' ) ); ?>> <?php esc_html_e( 'At the end, offer a matching dog bandana: the pattern plus extra fabric of the chosen fabric', 'atelier-irisee-master-plugin' ); ?></label></p>
						<p><label><?php esc_html_e( 'Extra fabric', 'atelier-irisee-master-plugin' ); ?> <input type="number" name="fabric_cm" min="10" max="300" step="10" value="<?php echo esc_attr( $o( 'fabric_cm' ) ); ?>" class="small-text"> cm</label></p>
						<p class="description"><?php esc_html_e( 'Price: the pattern\'s price plus this length times the fabric\'s price per 10 cm. 50 cm is enough for sizes XS and S, and for the over-the-collar and snap styles in every size; the size chart in the guide gives the length per size.', 'atelier-irisee-master-plugin' ); ?></p>
						<p><label><input type="checkbox" name="thread" value="1" <?php checked( $o( 'thread' ) ); ?>> <?php esc_html_e( 'Let the customer choose a sewing thread', 'atelier-irisee-master-plugin' ); ?></label>
							<label><input type="number" name="thread_qty" min="1" max="10" value="<?php echo esc_attr( $o( 'thread_qty' ) ); ?>" class="small-text"> <?php esc_html_e( 'spool(s)', 'atelier-irisee-master-plugin' ); ?></label></p>
						<p><label><input type="checkbox" name="band" value="1" <?php checked( $o( 'band' ) ); ?>> <?php esc_html_e( 'Let the customer choose a ribbon or bias tape', 'atelier-irisee-master-plugin' ); ?></label>
							<label><input type="number" name="band_cm" min="10" max="500" step="10" value="<?php echo esc_attr( $o( 'band_cm' ) ); ?>" class="small-text"> cm</label></p>
						<p class="description"><?php esc_html_e( 'Threads come from the sewing thread category (Product categories); ribbons from the ribbons & cords and bias tape categories.', 'atelier-irisee-master-plugin' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'aimp_bandana_save' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$int     = function ( $key, $min, $max ) {
			return isset( $_POST[ $key ] ) ? max( $min, min( $max, absint( $_POST[ $key ] ) ) ) : AIMP_Bandana::defaults()[ $key ];
		};
		$product = isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;
		$page    = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 0;
		update_option(
			AIMP_Bandana::OPTION,
			array(
				'product'    => $product && wc_get_product( $product ) ? $product : 0,
				'page'       => $page && 'page' === get_post_type( $page ) ? $page : 0,
				'addon'      => empty( $_POST['addon'] ) ? 0 : 1,
				'fabric_cm'  => $int( 'fabric_cm', 10, 300 ),
				'thread'     => empty( $_POST['thread'] ) ? 0 : 1,
				'thread_qty' => $int( 'thread_qty', 1, 10 ),
				'band'       => empty( $_POST['band'] ) ? 0 : 1,
				'band_cm'    => $int( 'band_cm', 10, 500 ),
			),
			false
		);
		// phpcs:enable
		AIMP_Shop::flush_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=' . AIMP_Settings::PAGE . '&aimp_bandana_saved=1#tab-aimp_bandana' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * The customizer: "Also make a matching dog bandana?"
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the customizer asks the question at all.
	 *
	 * @return bool
	 */
	public static function offered() {
		return (bool) AIMP_Bandana::opt( 'addon' ) && null !== AIMP_Bandana::product();
	}

	/**
	 * Units of fabric for the bandana.
	 *
	 * @return int
	 */
	public static function fabric_units() {
		return (int) ceil( AIMP_Bandana::opt( 'fabric_cm' ) / AIMP_Catalog::FABRIC_UNIT_CM );
	}

	/**
	 * The haberdashery of a kind (thread; ribbon and bias tape) that can be bought in the amount the bandana needs.
	 *
	 * @param string[] $kinds Kinds.
	 * @param float    $need  Pieces or cm.
	 * @return array[] Cards.
	 */
	private static function choices( $kinds, $need ) {
		$cats = array();
		foreach ( $kinds as $kind ) {
			$info = AIMP_Notions::kind( $kind );
			$root = $info ? (int) AIMP_Settings::get( $info['setting'] ) : 0;
			$cats = array_merge( $cats, AIMP_Catalog::category_tree( $root ) );
		}
		if ( ! $cats ) {
			return array();
		}
		$ids   = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 60,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $cats,
					),
				),
			)
		);
		$cards = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! in_array( AIMP_Notions::kind_of( $product ), $kinds, true ) ) {
				continue;
			}
			$card = AIMP_Catalog::material_card( $product, $need );
			if ( $card['available'] ) {
				// Only what the small cards show.
				$cards[] = array_intersect_key( $card, array_flip( array( 'id', 'name', 'image', 'image_alt', 'price', 'qty', 'qty_text', 'subtitle', 'total_html' ) ) );
			}
		}
		return $cards;
	}

	/**
	 * The offer for a kit: the pattern, the extra fabric, and the threads and bands to choose from.
	 *
	 * @param int $variation_id Pattern size.
	 * @param int $fabric_id    The kit's fabric.
	 * @return array
	 */
	public static function offer( $variation_id, $fabric_id ) {
		$req     = AIMP_Catalog::get_requirements( $variation_id );
		$product = AIMP_Bandana::product();
		$fabric  = $req && $req['fabric_units'] > 0 && $fabric_id ? AIMP_Catalog::get_allowed_fabric( $fabric_id, $req ) : null;
		$units   = self::fabric_units();
		if ( ! self::offered() || ! $fabric || ! $fabric->is_purchasable() || ! $fabric->has_enough_stock( $req['fabric_units'] + $units ) ) {
			return array( 'available' => false );
		}
		$pattern = AIMP_Catalog::card( $product );
		return array(
			'available'   => true,
			'pattern'     => array(
				'id'    => $pattern['id'],
				'name'  => $pattern['name'],
				'image' => $pattern['image'],
				'price' => $pattern['price'],
			),
			'fabric_units' => $units,
			'fabric_text'  => AIMP_Catalog::fabric_text( $units ),
			'fabric_price' => AIMP_Catalog::card( $fabric )['price'],
			'threads'      => AIMP_Bandana::opt( 'thread' ) ? self::choices( array( 'thread' ), AIMP_Bandana::opt( 'thread_qty' ) ) : array(),
			'bands'        => AIMP_Bandana::opt( 'band' ) ? self::choices( array( 'ribbon', 'bias' ), AIMP_Bandana::opt( 'band_cm' ) ) : array(),
			'band_text'    => AIMP_Notions::length_text( AIMP_Bandana::opt( 'band_cm' ) ),
			'guide_url'    => AIMP_Bandana::page_url(),
		);
	}

	public static function ajax_offer() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only public catalog data.
		$variation = isset( $_POST['variation'] ) ? absint( $_POST['variation'] ) : 0;
		$fabric    = isset( $_POST['fabric'] ) ? absint( $_POST['fabric'] ) : 0;
		// phpcs:enable
		wp_send_json_success( self::offer( $variation, $fabric ) );
	}

	/**
	 * The cart lines of the bandana in a kit (AIMP_Cart::add_group()): the pattern, the extra fabric, and the chosen
	 * thread and band. Problems go into $errors.
	 *
	 * @param array      $req    Requirements of the kit.
	 * @param WC_Product $fabric The kit's fabric (or null).
	 * @param array      $extras [ bandana => 0|1, thread => ID, band => ID ].
	 * @param WP_Error   $errors Errors.
	 * @return array[] Lines.
	 */
	public static function cart_lines( $req, $fabric, $extras, $errors ) {
		if ( empty( $extras['bandana'] ) ) {
			return array();
		}
		$product = AIMP_Bandana::product();
		if ( ! self::offered() || ! $product || ! $fabric ) {
			$errors->add( 'aimp_bandana', __( 'The dog bandana cannot be added to this kit.', 'atelier-irisee-master-plugin' ) );
			return array();
		}
		$lines = array(
			array(
				'product' => $product,
				'qty'     => 1,
				'role'    => 'bandana',
				'use'     => 'bandana',
			),
			array(
				'product' => $fabric,
				'qty'     => self::fabric_units(),
				'role'    => 'bandana_fabric',
				'use'     => 'bandana',
			),
		);
		$picks = array(
			'thread' => array( array( 'thread' ), AIMP_Bandana::opt( 'thread' ), AIMP_Bandana::opt( 'thread_qty' ) ),
			'band'   => array( array( 'ribbon', 'bias' ), AIMP_Bandana::opt( 'band' ), AIMP_Bandana::opt( 'band_cm' ) ),
		);
		foreach ( $picks as $key => $pick ) {
			$id = isset( $extras[ $key ] ) ? absint( $extras[ $key ] ) : 0;
			if ( ! $id ) {
				continue;
			}
			$item = wc_get_product( $id );
			if ( ! $pick[1] || ! $item || 'publish' !== $item->get_status() || ! in_array( AIMP_Notions::kind_of( $item ), $pick[0], true ) ) {
				$errors->add( 'aimp_bandana_' . $key, __( 'This item cannot be used for the dog bandana.', 'atelier-irisee-master-plugin' ) );
				continue;
			}
			$lines[] = array(
				'product' => $item,
				'qty'     => AIMP_Catalog::pack_qty( $item, $pick[2] ),
				'role'    => 'bandana_' . $key,
				'use'     => 'bandana',
			);
		}
		return $lines;
	}
}
