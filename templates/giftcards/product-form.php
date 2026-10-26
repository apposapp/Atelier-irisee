<?php
/**
 * Gift card choices on the product page (inside the add-to-cart form).
 *
 * Override in your theme: yourtheme/atelier-irisee/giftcards/product-form.php
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aimp_designs   = AIMP_Giftcards::designs();
$aimp_amounts   = AIMP_Giftcards::preset_amounts();
$aimp_custom    = (bool) AIMP_Giftcards::opt( 'custom_amount' );
$aimp_min       = (float) AIMP_Giftcards::opt( 'min_amount' );
$aimp_max       = (float) AIMP_Giftcards::opt( 'max_amount' );
$aimp_fee       = (float) AIMP_Giftcards::opt( 'post_fee' );
$aimp_recipient = (bool) AIMP_Giftcards::opt( 'allow_recipient' );
$aimp_post      = (bool) AIMP_Giftcards::opt( 'allow_post' );
$aimp_date      = (bool) AIMP_Giftcards::opt( 'allow_send_date' );
// On the Atelier Irisee product page the amount is chosen with the ‹ › arrows in the price bar.
$aimp_stepper   = class_exists( 'AIMP_Product_Page' ) && AIMP_Product_Page::is_rendering();
// The cart refused the gift card (e.g. a wrong email address): what was filled in comes back.
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- only shown back in the form.
$aimp_sent = ( isset( $_POST['aimp_gc'] ) && is_array( $_POST['aimp_gc'] ) && function_exists( 'wc_notice_count' ) && wc_notice_count( 'error' ) ) ? wc_clean( wp_unslash( $_POST['aimp_gc'] ) ) : array();
$aimp_v    = function ( $key ) use ( $aimp_sent ) {
	return isset( $aimp_sent[ $key ] ) && is_string( $aimp_sent[ $key ] ) ? $aimp_sent[ $key ] : '';
};
?>
<div class="aimp-gc-form" data-aimp-gc-form data-fee="<?php echo esc_attr( $aimp_fee ); ?>">

	<?php if ( $aimp_designs ) : ?>
		<fieldset class="aimp-gc-group">
			<legend><?php esc_html_e( 'Choose a design', 'atelier-irisee-master-plugin' ); ?></legend>
			<div class="aimp-gc-designs">
				<?php $aimp_first = true; ?>
				<?php foreach ( $aimp_designs as $aimp_id => $aimp_design ) : ?>
					<label class="aimp-gc-design">
						<input type="radio" name="aimp_gc[design]" value="<?php echo esc_attr( $aimp_id ); ?>" data-large="<?php echo esc_url( AIMP_Giftcards::design_image( $aimp_id, 'woocommerce_single' ) ); ?>" data-greeting="<?php echo esc_attr( AIMP_Giftcards_Render::in_language( AIMP_Giftcards_Render::theme( $aimp_id )['greeting'] ) ); ?>" <?php checked( '' !== $aimp_v( 'design' ) ? (string) $aimp_id === $aimp_v( 'design' ) : $aimp_first ); ?> required>
						<img src="<?php echo esc_url( AIMP_Giftcards::design_image( $aimp_id, 'woocommerce_thumbnail' ) ); ?>" alt="" loading="lazy">
						<span><?php echo esc_html( $aimp_design['name'] ); ?></span>
					</label>
					<?php $aimp_first = false; ?>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endif; ?>

	<?php if ( $aimp_stepper ) : ?>
		<input type="hidden" name="aimp_gc[amount]" value="custom">
		<input type="hidden" name="aimp_gc[stepper]" value="1">
	<?php else : ?>
	<fieldset class="aimp-gc-group">
		<legend><?php esc_html_e( 'Choose an amount', 'atelier-irisee-master-plugin' ); ?></legend>
		<div class="aimp-gc-chips">
			<?php foreach ( $aimp_amounts as $aimp_index => $aimp_amount ) : ?>
				<label class="aimp-gc-chip">
					<input type="radio" name="aimp_gc[amount]" value="<?php echo esc_attr( $aimp_amount ); ?>" data-amount="<?php echo esc_attr( $aimp_amount ); ?>" <?php checked( '' !== $aimp_v( 'amount' ) ? (string) $aimp_amount === $aimp_v( 'amount' ) : 0 === $aimp_index ); ?> required>
					<span><?php echo wp_kses_post( wc_price( $aimp_amount ) ); ?></span>
				</label>
			<?php endforeach; ?>
			<?php if ( $aimp_custom ) : ?>
				<label class="aimp-gc-chip">
					<input type="radio" name="aimp_gc[amount]" value="custom" <?php checked( empty( $aimp_amounts ) || 'custom' === $aimp_v( 'amount' ) ); ?> required>
					<span><?php esc_html_e( 'Other amount', 'atelier-irisee-master-plugin' ); ?></span>
				</label>
			<?php endif; ?>
		</div>
		<?php if ( $aimp_custom ) : ?>
			<p class="aimp-gc-field" data-aimp-gc-show="amount:custom">
				<label for="aimp-gc-custom">
					<?php
					/* translators: 1: minimum amount, 2: maximum amount */
					printf( esc_html__( 'Your amount (%1$s – %2$s)', 'atelier-irisee-master-plugin' ), wp_kses_post( wc_price( $aimp_min ) ), wp_kses_post( wc_price( $aimp_max ) ) );
					?>
				</label>
				<input type="number" id="aimp-gc-custom" name="aimp_gc[custom_amount]" min="<?php echo esc_attr( $aimp_min ); ?>" max="<?php echo esc_attr( $aimp_max ); ?>" step="0.01" inputmode="decimal" value="<?php echo esc_attr( $aimp_v( 'custom_amount' ) ); ?>">
			</p>
		<?php endif; ?>
	</fieldset>
	<?php endif; ?>

	<fieldset class="aimp-gc-group">
		<legend><?php esc_html_e( 'How should the gift card be delivered?', 'atelier-irisee-master-plugin' ); ?></legend>
		<div class="aimp-gc-options">
			<label class="aimp-gc-option">
				<input type="radio" name="aimp_gc[delivery]" value="email_self" <?php checked( ! in_array( $aimp_v( 'delivery' ), array( 'email_other', 'post' ), true ) ); ?>>
				<span><strong><?php esc_html_e( 'By email to me', 'atelier-irisee-master-plugin' ); ?></strong> <?php esc_html_e( 'You receive the gift card and can pass it on yourself.', 'atelier-irisee-master-plugin' ); ?></span>
			</label>
			<?php if ( $aimp_recipient ) : ?>
				<label class="aimp-gc-option">
					<input type="radio" name="aimp_gc[delivery]" value="email_other" <?php checked( 'email_other', $aimp_v( 'delivery' ) ); ?>>
					<span><strong><?php esc_html_e( 'By email to someone else', 'atelier-irisee-master-plugin' ); ?></strong> <?php esc_html_e( 'We email the gift card straight to the lucky one, on the day you choose.', 'atelier-irisee-master-plugin' ); ?></span>
				</label>
			<?php endif; ?>
			<?php if ( $aimp_post ) : ?>
				<label class="aimp-gc-option">
					<input type="radio" name="aimp_gc[delivery]" value="post" <?php checked( 'post', $aimp_v( 'delivery' ) ); ?>>
					<span><strong><?php esc_html_e( 'Printed, by post', 'atelier-irisee-master-plugin' ); ?></strong>
						<?php
						if ( $aimp_fee > 0 ) {
							/* translators: %s: printing fee */
							printf( esc_html__( 'A printed gift card sent to your shipping address. Printing fee %s, plus the normal shipping costs.', 'atelier-irisee-master-plugin' ), wp_kses_post( wc_price( $aimp_fee ) ) );
						} else {
							esc_html_e( 'A printed gift card sent to your shipping address, plus the normal shipping costs.', 'atelier-irisee-master-plugin' );
						}
						?>
					</span>
				</label>
			<?php endif; ?>
		</div>

		<?php if ( $aimp_recipient || $aimp_post ) : ?>
			<p class="aimp-gc-field" data-aimp-gc-show="delivery:email_other,post">
				<label for="aimp-gc-recipient-name"><?php esc_html_e( 'Name of the lucky one', 'atelier-irisee-master-plugin' ); ?></label>
				<input type="text" id="aimp-gc-recipient-name" name="aimp_gc[recipient_name]" maxlength="80" autocomplete="off" value="<?php echo esc_attr( $aimp_v( 'recipient_name' ) ); ?>">
			</p>
		<?php endif; ?>
		<?php if ( $aimp_recipient ) : ?>
			<p class="aimp-gc-field" data-aimp-gc-show="delivery:email_other">
				<label for="aimp-gc-recipient-email"><?php esc_html_e( 'Their email address', 'atelier-irisee-master-plugin' ); ?></label>
				<input type="email" id="aimp-gc-recipient-email" name="aimp_gc[recipient_email]" autocomplete="off" value="<?php echo esc_attr( $aimp_v( 'recipient_email' ) ); ?>">
			</p>
			<p class="aimp-gc-field" data-aimp-gc-show="delivery:email_other,post">
				<label for="aimp-gc-sender"><?php esc_html_e( 'Your name (shown on the gift card)', 'atelier-irisee-master-plugin' ); ?></label>
				<input type="text" id="aimp-gc-sender" name="aimp_gc[sender_name]" maxlength="80" autocomplete="name" value="<?php echo esc_attr( $aimp_v( 'sender_name' ) ); ?>">
			</p>
		<?php endif; ?>
		<p class="aimp-gc-field">
			<label for="aimp-gc-greeting"><?php esc_html_e( 'Greeting on the card (you may change it)', 'atelier-irisee-master-plugin' ); ?></label>
			<input type="text" id="aimp-gc-greeting" name="aimp_gc[greeting]" maxlength="80" autocomplete="off" value="<?php echo esc_attr( $aimp_v( 'greeting' ) ); ?>"<?php echo '' !== $aimp_v( 'greeting' ) ? ' data-aimp-gc-own="1"' : ''; ?>>
		</p>
		<p class="aimp-gc-field">
			<label for="aimp-gc-message"><?php esc_html_e( 'Personal message (optional, max. 300 characters)', 'atelier-irisee-master-plugin' ); ?></label>
			<textarea id="aimp-gc-message" name="aimp_gc[message]" rows="3" maxlength="300"><?php echo esc_textarea( $aimp_v( 'message' ) ); ?></textarea>
		</p>
		<?php if ( $aimp_date ) : ?>
			<p class="aimp-gc-field" data-aimp-gc-show="delivery:email_self,email_other">
				<label for="aimp-gc-date"><?php esc_html_e( 'Send on (optional, leave empty to send right away)', 'atelier-irisee-master-plugin' ); ?></label>
				<input type="date" id="aimp-gc-date" name="aimp_gc[send_date]" min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 year' ) ) ); ?>" value="<?php echo esc_attr( $aimp_v( 'send_date' ) ); ?>">
			</p>
		<?php endif; ?>
	</fieldset>

	<?php if ( AIMP_Giftcards_Render::available() && $aimp_designs ) : ?>
		<div class="aimp-gc-preview" data-aimp-gc-preview>
			<button type="button" class="aimp-gc-preview-button" data-aimp-gc-preview-button><?php esc_html_e( 'Preview your card', 'atelier-irisee-master-plugin' ); ?></button>
			<div class="aimp-gc-preview-sides" hidden>
				<figure><img alt="" data-aimp-gc-preview-front><figcaption><?php esc_html_e( 'Front', 'atelier-irisee-master-plugin' ); ?></figcaption></figure>
				<figure><img alt="" data-aimp-gc-preview-back><figcaption><?php esc_html_e( 'Back', 'atelier-irisee-master-plugin' ); ?></figcaption></figure>
			</div>
			<p class="aimp-gc-preview-msg" role="status" hidden></p>
		</div>
	<?php endif; ?>

	<p class="aimp-gc-total" aria-live="polite"><?php esc_html_e( 'Total', 'atelier-irisee-master-plugin' ); ?>: <strong data-aimp-gc-total></strong></p>
</div>
