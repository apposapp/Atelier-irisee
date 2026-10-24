<?php
/**
 * [atelier_irisee_contact]: the contact page. Contact cards (email with a copy button, phone with WhatsApp, the
 * address with a route link, the answer time, social media), a contact form sent in the background (subject, order
 * number, privacy consent; a copy to the customer) and the questions and answers.
 *
 * The contact details come from the footer settings; the texts and choices from the Design studio (Contact page).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Contact {

	const TAG = 'atelier_irisee_contact';

	/** At least this long between opening the page and sending (people type; bots do not). */
	const MIN_MS = 3000;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
		add_action( 'wc_ajax_aimp_contact', array( __CLASS__, 'send' ) );
	}

	public static function register_assets() {
		// On the shared block styles (titles, buttons, questions and answers).
		if ( ! wp_style_is( 'aimp-home-blocks', 'registered' ) ) {
			AIMP_Home_Blocks::register_assets();
		}
		wp_register_style( 'aimp-contact', AIMP_PLUGIN_URL . 'assets/css/contact.css', array( 'aimp-home-blocks' ), AIMP_VERSION );
		wp_register_script( 'aimp-contact', AIMP_PLUGIN_URL . 'assets/js/contact.js', array(), AIMP_VERSION, true );
		if ( AIMP_Blocks::page_has( array( self::TAG ) ) ) {
			wp_enqueue_style( 'aimp-contact' );
		}
	}

	/**
	 * A Design studio setting of the contact page.
	 *
	 * @param string $key     Setting.
	 * @param mixed  $default When it is not set.
	 * @return mixed
	 */
	private static function design( $key, $default = null ) {
		return AIMP_Blocks::design( 'contact', $key, $default );
	}

	/**
	 * The phone number for a WhatsApp link: digits with the country code ("+32 (0)475 24 52 10" → 32475245210).
	 *
	 * @param string $phone Phone number.
	 * @return string '' when it is not an international number.
	 */
	private static function whatsapp_number( $phone ) {
		$phone = str_replace( '(0)', '', $phone );
		if ( 0 === strpos( trim( $phone ), '00' ) ) {
			$phone = '+' . substr( trim( $phone ), 2 );
		}
		if ( 0 !== strpos( trim( $phone ), '+' ) ) {
			return '';
		}
		return preg_replace( '/\D/', '', $phone );
	}

	/**
	 * The customer's recent orders, for the order choice in the form: [ number => "number – date" ].
	 *
	 * @return array
	 */
	private static function customer_orders() {
		if ( ! is_user_logged_in() || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$list = array();
		foreach ( wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		) as $order ) {
			$date = $order->get_date_created();
			$list[ $order->get_order_number() ] = '#' . $order->get_order_number() . ( $date ? ' – ' . wc_format_datetime( $date ) : '' );
		}
		return $list;
	}

	/**
	 * @param array|string $atts Attributes (none).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		if ( ! wp_style_is( 'aimp-contact', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-contact' );
		wp_enqueue_script( 'aimp-contact' );
		wp_localize_script(
			'aimp-contact',
			'aimpContact',
			array(
				'endpoint' => WC_AJAX::get_endpoint( 'aimp_contact' ),
				'lang'     => AIMP_I18n::current(),
				'sending'  => __( 'Sending…', 'atelier-irisee-master-plugin' ),
				'error'    => __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ),
				'required' => __( 'Please fill in this field.', 'atelier-irisee-master-plugin' ),
				'email'    => __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ),
				'consent'  => __( 'Please agree to the privacy policy.', 'atelier-irisee-master-plugin' ),
				'short'    => __( 'Your message is a little short. Tell us a bit more.', 'atelier-irisee-master-plugin' ),
				'copied'   => __( 'Copied', 'atelier-irisee-master-plugin' ),
			)
		);

		$email    = sanitize_email( (string) AIMP_Footer::get( 'email' ) );
		$phone    = trim( (string) AIMP_Footer::get( 'phone' ) );
		$address  = trim( (string) AIMP_Footer::get( 'address' ) );
		$whatsapp = self::design( 'ct_nowhatsapp' ) ? '' : self::whatsapp_number( $phone );
		$socials  = self::design( 'ct_nosocials' ) ? array() : array_filter(
			array(
				'facebook'  => (string) AIMP_Footer::get( 'facebook' ),
				'instagram' => (string) AIMP_Footer::get( 'instagram' ),
				'tiktok'    => (string) AIMP_Footer::get( 'tiktok' ),
				'pinterest' => (string) AIMP_Footer::get( 'pinterest' ),
			)
		);
		$privacy  = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy  = $privacy && 'publish' === get_post_status( $privacy ) ? get_permalink( $privacy ) : '';
		$orders   = self::customer_orders();
		$user     = wp_get_current_user();
		$name     = $user && $user->ID ? trim( $user->first_name . ' ' . $user->last_name ) : '';
		$name     = '' !== $name ? $name : ( $user && $user->ID ? $user->display_name : '' );
		$subjects = AIMP_Blocks::lines( 'ct_subjects' );
		$faq      = self::design( 'ct_nofaq' ) ? array() : AIMP_Blocks::pairs( 'faq' );
		$hours    = AIMP_Blocks::text( 'ct_hours' );
		$id       = wp_unique_id( 'aimp-contact-' );

		ob_start();
		?>
		<section class="aimp-contact<?php echo self::design( 'ct_formleft' ) ? ' is-form-left' : ''; ?>" data-aimp-contact>
			<header class="aimp-contact-head">
				<h1 class="aimp-block-title aimp-contact-title"><?php echo esc_html( AIMP_Blocks::text( 'ct_title' ) ); ?></h1>
				<p class="aimp-contact-intro"><?php echo nl2br( esc_html( AIMP_Blocks::text( 'ct_intro' ) ) ); ?></p>
			</header>

			<div class="aimp-contact-body">
				<ul class="aimp-contact-cards">
					<?php if ( '' !== $email ) : ?>
						<li class="aimp-contact-card aimp-contact-card--email">
							<?php echo AIMP_Blocks::icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
							<div class="aimp-contact-card-text">
								<span class="aimp-contact-label"><?php esc_html_e( 'Email', 'atelier-irisee-master-plugin' ); ?></span>
								<a class="aimp-contact-value" href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
							</div>
							<button type="button" class="aimp-contact-copy" data-copy="<?php echo esc_attr( $email ); ?>" aria-label="<?php esc_attr_e( 'Copy the email address', 'atelier-irisee-master-plugin' ); ?>">
								<?php echo AIMP_Blocks::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><span class="aimp-contact-copied" aria-live="polite"></span>
							</button>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $phone ) : ?>
						<li class="aimp-contact-card aimp-contact-card--phone">
							<?php echo AIMP_Blocks::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
							<div class="aimp-contact-card-text">
								<span class="aimp-contact-label"><?php esc_html_e( 'Phone', 'atelier-irisee-master-plugin' ); ?></span>
								<a class="aimp-contact-value" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', str_replace( '(0)', '', $phone ) ) ); ?>"><?php echo esc_html( $phone ); ?></a>
								<?php if ( '' !== $whatsapp ) : ?>
									<a class="aimp-contact-link" href="<?php echo esc_url( 'https://wa.me/' . $whatsapp ); ?>" target="_blank" rel="noopener"><?php echo AIMP_Blocks::icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Send a WhatsApp message', 'atelier-irisee-master-plugin' ); ?></a>
								<?php endif; ?>
							</div>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $address ) : ?>
						<li class="aimp-contact-card aimp-contact-card--address">
							<?php echo AIMP_Blocks::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
							<div class="aimp-contact-card-text">
								<span class="aimp-contact-label"><?php esc_html_e( 'Address', 'atelier-irisee-master-plugin' ); ?></span>
								<span class="aimp-contact-value"><?php echo nl2br( esc_html( $address ) ); ?></span>
								<?php if ( ! self::design( 'ct_noroute' ) ) : ?>
									<?php // A link, not an embedded map: no cookies of Google before the visitor chooses. ?>
									<a class="aimp-contact-link" href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( preg_replace( '/\s+/', ' ', $address ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Plan your route', 'atelier-irisee-master-plugin' ); ?></a>
								<?php endif; ?>
							</div>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $hours ) : ?>
						<li class="aimp-contact-card aimp-contact-card--hours">
							<?php echo AIMP_Blocks::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
							<div class="aimp-contact-card-text">
								<span class="aimp-contact-label"><?php esc_html_e( 'Answer time', 'atelier-irisee-master-plugin' ); ?></span>
								<span class="aimp-contact-value"><?php echo nl2br( esc_html( $hours ) ); ?></span>
							</div>
						</li>
					<?php endif; ?>
					<?php if ( $socials ) : ?>
						<li class="aimp-contact-card aimp-contact-card--socials">
							<span class="aimp-contact-label"><?php esc_html_e( 'Follow us', 'atelier-irisee-master-plugin' ); ?></span>
							<span class="aimp-contact-socials">
								<?php foreach ( $socials as $network => $url ) : ?>
									<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $network ) ); ?>"><?php echo AIMP_Footer::social_icon( $network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
								<?php endforeach; ?>
							</span>
						</li>
					<?php endif; ?>
				</ul>

				<div class="aimp-contact-form-wrap">
					<form class="aimp-contact-form" data-aimp-contact-form novalidate>
						<h2 class="aimp-contact-form-title"><?php echo esc_html( AIMP_Blocks::text( 'ct_form_title' ) ); ?></h2>
						<div class="aimp-contact-row">
							<p class="aimp-contact-field">
								<label for="<?php echo esc_attr( $id ); ?>-name"><?php esc_html_e( 'Name', 'atelier-irisee-master-plugin' ); ?> <abbr title="<?php esc_attr_e( 'required', 'atelier-irisee-master-plugin' ); ?>">*</abbr></label>
								<input type="text" id="<?php echo esc_attr( $id ); ?>-name" name="name" required maxlength="100" autocomplete="name" value="<?php echo esc_attr( $name ); ?>">
							</p>
							<p class="aimp-contact-field">
								<label for="<?php echo esc_attr( $id ); ?>-email"><?php esc_html_e( 'Email address', 'atelier-irisee-master-plugin' ); ?> <abbr title="<?php esc_attr_e( 'required', 'atelier-irisee-master-plugin' ); ?>">*</abbr></label>
								<input type="email" id="<?php echo esc_attr( $id ); ?>-email" name="email" required maxlength="100" autocomplete="email" value="<?php echo esc_attr( $user && $user->ID ? $user->user_email : '' ); ?>">
							</p>
						</div>
						<div class="aimp-contact-row">
							<?php if ( $subjects ) : ?>
								<p class="aimp-contact-field">
									<label for="<?php echo esc_attr( $id ); ?>-subject"><?php esc_html_e( 'Subject', 'atelier-irisee-master-plugin' ); ?></label>
									<select id="<?php echo esc_attr( $id ); ?>-subject" name="subject">
										<?php foreach ( $subjects as $index => $subject ) : ?>
											<option value="<?php echo (int) $index; ?>"><?php echo esc_html( $subject ); ?></option>
										<?php endforeach; ?>
									</select>
								</p>
							<?php endif; ?>
							<?php if ( ! self::design( 'ct_noorder' ) ) : ?>
								<p class="aimp-contact-field">
									<label for="<?php echo esc_attr( $id ); ?>-order"><?php esc_html_e( 'Order number', 'atelier-irisee-master-plugin' ); ?> <small><?php esc_html_e( '(optional)', 'atelier-irisee-master-plugin' ); ?></small></label>
									<?php if ( $orders ) : ?>
										<select id="<?php echo esc_attr( $id ); ?>-order" name="order">
											<option value=""><?php esc_html_e( 'Not about an order', 'atelier-irisee-master-plugin' ); ?></option>
											<?php foreach ( $orders as $number => $label ) : ?>
												<option value="<?php echo esc_attr( $number ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<input type="text" id="<?php echo esc_attr( $id ); ?>-order" name="order" maxlength="40" inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 1234', 'atelier-irisee-master-plugin' ); ?>">
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
						<p class="aimp-contact-field">
							<label for="<?php echo esc_attr( $id ); ?>-message"><?php esc_html_e( 'Your message', 'atelier-irisee-master-plugin' ); ?> <abbr title="<?php esc_attr_e( 'required', 'atelier-irisee-master-plugin' ); ?>">*</abbr></label>
							<textarea id="<?php echo esc_attr( $id ); ?>-message" name="message" required rows="6" maxlength="5000"></textarea>
						</p>
						<?php if ( ! self::design( 'ct_noconsent' ) ) : ?>
							<p class="aimp-contact-field aimp-contact-consent">
								<label>
									<input type="checkbox" name="consent" value="1" required>
									<span>
										<?php
										if ( '' !== $privacy ) {
											printf(
												/* translators: %s: link to the privacy policy */
												esc_html__( 'I agree that my details are used to answer my question (%s).', 'atelier-irisee-master-plugin' ),
												'<a href="' . esc_url( $privacy ) . '" target="_blank">' . esc_html__( 'privacy policy', 'atelier-irisee-master-plugin' ) . '</a>'
											);
										} else {
											esc_html_e( 'I agree that my details are used to answer my question.', 'atelier-irisee-master-plugin' );
										}
										?>
									</span>
								</label>
							</p>
						<?php endif; ?>
						<input type="text" name="aimp_hp" value="" tabindex="-1" autocomplete="off" class="aimp-contact-hp" aria-hidden="true">
						<div class="aimp-contact-actions">
							<button type="submit" class="aimp-contact-button"><?php esc_html_e( 'Send message', 'atelier-irisee-master-plugin' ); ?></button>
							<p class="aimp-contact-message" aria-live="polite" hidden></p>
						</div>
					</form>
					<div class="aimp-contact-done" data-aimp-contact-done tabindex="-1" hidden>
						<?php echo AIMP_Blocks::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
						<p><?php echo nl2br( esc_html( AIMP_Blocks::text( 'ct_success' ) ) ); ?></p>
						<button type="button" class="aimp-contact-button aimp-contact-button--ghost" data-aimp-contact-again><?php esc_html_e( 'Send another message', 'atelier-irisee-master-plugin' ); ?></button>
					</div>
				</div>
			</div>

			<?php if ( $faq ) : ?>
				<div class="aimp-contact-faq">
					<h2 class="aimp-block-title aimp-contact-faq-title"><?php echo esc_html( AIMP_Blocks::text( 'ct_faq_title' ) ); ?></h2>
					<?php echo AIMP_Blocks::faq_html( $faq ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in faq_html(). ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ sending */

	/**
	 * An answer in the visitor's language.
	 *
	 * @param string $message Message.
	 * @param string $field   The field it is about ('' for the form).
	 */
	private static function fail( $message, $field = '' ) {
		wp_send_json_error(
			array(
				'message' => $message,
				'field'   => $field,
			)
		);
	}

	/**
	 * ?wc-ajax=aimp_contact. No nonce on purpose: the page may be cached, where a nonce would expire. Bots are
	 * stopped by the hidden field, the time it takes to write and a limit per visitor.
	 */
	public static function send() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see above.
		$get = function ( $name, $max ) {
			return isset( $_POST[ $name ] ) ? substr( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ), 0, $max ) : '';
		};
		$lang = sanitize_key( $get( 'lang', 5 ) );
		if ( AIMP_I18n::is_valid( $lang ) ) {
			AIMP_I18n::with_language( $lang, array( __CLASS__, 'handle' ) );
		}
		self::handle();
		// phpcs:enable
	}

	/**
	 * Check and send the message (in the visitor's language).
	 */
	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see send().
		if ( AIMP_Login_Security::honeypot_triggered() ) {
			wp_send_json_success();
		}
		$elapsed = isset( $_POST['elapsed'] ) ? absint( $_POST['elapsed'] ) : 0;
		if ( $elapsed < self::MIN_MS ) {
			self::fail( __( 'Something went wrong. Please try again.', 'atelier-irisee-master-plugin' ) );
		}

		$name    = isset( $_POST['name'] ) ? trim( substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 100 ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$order   = isset( $_POST['order'] ) ? trim( substr( sanitize_text_field( wp_unslash( $_POST['order'] ) ), 0, 40 ) ) : '';
		$message = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
		$message = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 5000 ) : substr( $message, 0, 5000 );
		$index   = isset( $_POST['subject'] ) ? absint( $_POST['subject'] ) : 0;
		$consent = ! empty( $_POST['consent'] );
		// phpcs:enable

		if ( '' === $name ) {
			self::fail( __( 'Please fill in this field.', 'atelier-irisee-master-plugin' ), 'name' );
		}
		if ( ! is_email( $email ) ) {
			self::fail( __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' ), 'email' );
		}
		if ( strlen( $message ) < 10 ) {
			self::fail( __( 'Your message is a little short. Tell us a bit more.', 'atelier-irisee-master-plugin' ), 'message' );
		}
		if ( ! $consent && ! self::design( 'ct_noconsent' ) ) {
			self::fail( __( 'Please agree to the privacy policy.', 'atelier-irisee-master-plugin' ), 'consent' );
		}
		if ( AIMP_Login_Security::rate_limited( 'contact', AIMP_Login_Security::ip(), 5 ) ) {
			self::fail( __( 'Too many attempts. Please try again later.', 'atelier-irisee-master-plugin' ) );
		}

		$subjects = AIMP_Blocks::lines( 'ct_subjects' );
		$subject  = isset( $subjects[ $index ] ) ? $subjects[ $index ] : __( 'Contact form', 'atelier-irisee-master-plugin' );
		$to       = sanitize_email( (string) AIMP_Footer::get( 'email' ) );
		$to       = is_email( $to ) ? $to : get_option( 'admin_email' );
		$site     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		// To the shop: the answer goes straight to the customer (Reply-To).
		$rows = array(
			__( 'Name', 'atelier-irisee-master-plugin' )          => $name,
			__( 'Email address', 'atelier-irisee-master-plugin' ) => $email,
			__( 'Subject', 'atelier-irisee-master-plugin' )       => $subject,
		);
		if ( '' !== $order ) {
			$rows[ __( 'Order number', 'atelier-irisee-master-plugin' ) ] = $order;
		}
		$rows[ __( 'Language', 'atelier-irisee-master-plugin' ) ] = strtoupper( AIMP_I18n::current() );
		$table = '<table cellpadding="6" style="border-collapse:collapse;margin-bottom:16px">';
		foreach ( $rows as $label => $value ) {
			$table .= '<tr><th align="left" style="padding-right:16px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		$table .= '</table>';
		$body   = $table . '<div>' . nl2br( esc_html( $message ) ) . '</div>';
		$sent   = self::mail(
			$to,
			sprintf( '[%1$s] %2$s – %3$s', $site, $subject, $name ),
			__( 'New message from the contact form', 'atelier-irisee-master-plugin' ),
			$body,
			array( 'Reply-To: ' . str_replace( array( "\r", "\n", '"' ), '', $name ) . ' <' . $email . '>' )
		);
		if ( ! $sent ) {
			/* translators: %s: email address of the shop */
			self::fail( sprintf( __( 'Your message could not be sent. Please email us at %s.', 'atelier-irisee-master-plugin' ), $to ) );
		}

		// A copy for the customer (Design studio → Contact page: can be switched off).
		if ( ! self::design( 'ct_nocopy' ) ) {
			self::mail(
				$email,
				/* translators: %s: shop name */
				sprintf( __( 'We received your message – %s', 'atelier-irisee-master-plugin' ), $site ),
				__( 'Thank you for your message', 'atelier-irisee-master-plugin' ),
				'<p>' . esc_html__( 'We received your message and will get back to you as soon as possible. This is what you sent us:', 'atelier-irisee-master-plugin' ) . '</p>' . $body,
				array( 'Reply-To: ' . $to )
			);
		}
		wp_send_json_success();
	}

	/**
	 * An email in the shop's email style (WooCommerce's template, with the Atelier Irisee look when that is on).
	 *
	 * @param string   $to      Address.
	 * @param string   $subject Subject.
	 * @param string   $heading Heading in the email.
	 * @param string   $body    HTML.
	 * @param string[] $headers Extra headers.
	 * @return bool
	 */
	private static function mail( $to, $subject, $heading, $body, $headers ) {
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$mailer = WC()->mailer();
			return (bool) $mailer->send( $to, $subject, $mailer->wrap_message( $heading, $body ), array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers ) );
		}
		return wp_mail( $to, $subject, $body, array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers ) );
	}
}
