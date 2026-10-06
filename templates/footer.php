<?php
/**
 * Atelier Irisee footer.
 *
 * Copy this file to yourtheme/atelier-irisee/footer.php to change the markup.
 *
 * @var array $data company, address, phone, email, socials, account_menu, account_links, service_menu,
 *                  legal_menu, privacy_url, newsletter_text, payment_logos, copyright.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="aimp-footer">
	<div class="aimp-footer-columns">

		<div class="aimp-footer-col">
			<?php if ( '' !== $data['company'] ) : ?>
				<h2 class="aimp-footer-title"><?php echo esc_html( $data['company'] ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $data['address'] ) : ?>
				<p><?php echo nl2br( esc_html( $data['address'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $data['phone'] || '' !== $data['email'] ) : ?>
				<p>
					<?php if ( '' !== $data['phone'] ) : ?>
						<?php esc_html_e( 'Tel:', 'atelier-irisee-master-plugin' ); ?> <a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $data['phone'] ) ); ?>"><?php echo esc_html( $data['phone'] ); ?></a><br>
					<?php endif; ?>
					<?php if ( '' !== $data['email'] ) : ?>
						<?php esc_html_e( 'E-mail:', 'atelier-irisee-master-plugin' ); ?> <a href="<?php echo esc_url( 'mailto:' . $data['email'] ); ?>"><?php echo esc_html( $data['email'] ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( $data['socials'] ) : ?>
				<ul class="aimp-footer-socials">
					<?php foreach ( $data['socials'] as $aimp_network => $aimp_url ) : ?>
						<li><a href="<?php echo esc_url( $aimp_url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $aimp_network ) ); ?>"><?php echo AIMP_Footer::social_icon( $aimp_network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="aimp-footer-col">
			<h2 class="aimp-footer-title"><?php esc_html_e( 'My account', 'atelier-irisee-master-plugin' ); ?></h2>
			<?php if ( '' !== $data['account_menu'] ) : ?>
				<?php echo $data['account_menu']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu() output. ?>
			<?php else : ?>
				<ul class="aimp-footer-menu">
					<?php foreach ( $data['account_links'] as $aimp_link ) : ?>
						<li><a href="<?php echo esc_url( $aimp_link[0] ); ?>"><?php echo esc_html( $aimp_link[1] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="aimp-footer-col">
			<h2 class="aimp-footer-title"><?php esc_html_e( 'Customer service', 'atelier-irisee-master-plugin' ); ?></h2>
			<?php echo $data['service_menu']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu() output. ?>
		</div>

		<div class="aimp-footer-col">
			<h2 class="aimp-footer-title"><?php esc_html_e( 'Newsletter', 'atelier-irisee-master-plugin' ); ?></h2>
			<p><?php echo '' !== $data['newsletter_text'] ? nl2br( esc_html( $data['newsletter_text'] ) ) : esc_html__( 'Subscribe to our newsletter to stay up to date.', 'atelier-irisee-master-plugin' ); ?></p>
			<form class="aimp-newsletter" data-aimp-newsletter novalidate>
				<label class="screen-reader-text" for="aimp-newsletter-email"><?php esc_html_e( 'Email address', 'atelier-irisee-master-plugin' ); ?></label>
				<input type="email" id="aimp-newsletter-email" name="email" required autocomplete="email" placeholder="<?php esc_attr_e( 'Your e-mail address', 'atelier-irisee-master-plugin' ); ?>">
				<input type="text" name="aimp_hp" value="" tabindex="-1" autocomplete="off" class="aimp-newsletter-hp" aria-hidden="true">
				<button type="submit" class="aimp-footer-button"><?php esc_html_e( 'Subscribe', 'atelier-irisee-master-plugin' ); ?></button>
				<p class="aimp-newsletter-message" aria-live="polite" hidden></p>
			</form>
			<?php // Shown instead of the form once the visitor is subscribed (footer.js asks, so it also works on cached pages). ?>
			<div class="aimp-newsletter-state" data-aimp-newsletter-state aria-live="polite" hidden>
				<p class="aimp-newsletter-state-text"></p>
				<div class="aimp-newsletter-state-actions">
					<button type="button" class="aimp-footer-button aimp-footer-button--ghost" data-aimp-nl-resend hidden><?php esc_html_e( 'Send the email again', 'atelier-irisee-master-plugin' ); ?></button>
					<button type="button" class="aimp-footer-button" data-aimp-nl-unsubscribe><?php esc_html_e( 'Unsubscribe', 'atelier-irisee-master-plugin' ); ?></button>
				</div>
			</div>
			<?php if ( $data['privacy_url'] ) : ?>
				<p class="aimp-newsletter-consent">
					<?php
					printf(
						/* translators: %s: link to the privacy policy */
						esc_html__( 'You can unsubscribe at any time. Read our %s.', 'atelier-irisee-master-plugin' ),
						'<a href="' . esc_url( $data['privacy_url'] ) . '">' . esc_html__( 'privacy policy', 'atelier-irisee-master-plugin' ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $data['payment_logos'] ) : ?>
		<div class="aimp-footer-payments">
			<?php foreach ( $data['payment_logos'] as $aimp_logo ) : ?>
				<?php echo wp_get_attachment_image( $aimp_logo, 'thumbnail', false, array( 'class' => 'aimp-footer-payment' ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="aimp-footer-bottom">
		<?php echo $data['legal_menu']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu() output. ?>
		<p class="aimp-footer-copyright"><?php echo esc_html( $data['copyright'] ); ?></p>
	</div>
</footer>
