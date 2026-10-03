<?php
/**
 * One gift card, used in the email, on the account page and on the print page.
 * Inline styles, so it also looks right in email programs.
 *
 * Override in your theme: yourtheme/atelier-irisee/giftcards/card.php
 *
 * @var array $aimp_card      Gift card data (see AIMP_Giftcards::get()).
 * @var bool  $aimp_with_shop Show the "Visit the shop" button.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aimp_gold  = '#b38f4f';
$aimp_brown = '#613907';
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;margin:0 auto;border:5px double <?php echo esc_attr( $aimp_gold ); ?>;border-radius:24px;border-collapse:separate;font-family:Georgia,'Times New Roman',serif;">
	<tr>
		<td style="padding:0;">
			<img src="<?php echo esc_url( AIMP_Giftcards::design_image( $aimp_card['design'], 'large' ) ); ?>" alt="" width="550" style="display:block;width:100%;max-width:550px;height:auto;border-radius:19px 19px 0 0;">
		</td>
	</tr>
	<tr>
		<td style="padding:24px 28px 28px;text-align:center;color:<?php echo esc_attr( $aimp_brown ); ?>;">
			<?php if ( $aimp_card['recipient_name'] ) : ?>
				<p style="margin:0 0 6px;font-size:17px;font-weight:600;">
					<?php
					/* translators: %s: recipient name */
					printf( esc_html__( 'For %s', 'atelier-irisee-master-plugin' ), esc_html( $aimp_card['recipient_name'] ) );
					?>
				</p>
			<?php endif; ?>
			<p style="margin:0 0 4px;font-size:14px;letter-spacing:3px;text-transform:uppercase;color:<?php echo esc_attr( $aimp_gold ); ?>;"><?php esc_html_e( 'Gift card', 'atelier-irisee-master-plugin' ); ?></p>
			<p style="margin:0 0 16px;font-size:38px;line-height:1.2;color:<?php echo esc_attr( $aimp_gold ); ?>;"><?php echo wp_kses_post( wc_price( $aimp_card['amount'], array( 'currency' => $aimp_card['currency'] ) ) ); ?></p>
			<?php if ( $aimp_card['message'] ) : ?>
				<p style="margin:0 0 18px;font-size:17px;font-style:italic;font-weight:600;line-height:1.5;"><?php echo nl2br( esc_html( $aimp_card['message'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( $aimp_card['sender_name'] && 'email_self' !== $aimp_card['delivery'] ) : ?>
				<p style="margin:0 0 18px;font-size:16px;font-weight:600;">
					<?php
					/* translators: %s: sender name */
					printf( esc_html__( 'From %s', 'atelier-irisee-master-plugin' ), esc_html( $aimp_card['sender_name'] ) );
					?>
				</p>
			<?php endif; ?>
			<p style="margin:0 0 6px;font-size:14px;font-weight:600;"><?php esc_html_e( 'Your code', 'atelier-irisee-master-plugin' ); ?></p>
			<p style="display:inline-block;margin:0 0 14px;padding:10px 18px;border:2px dashed <?php echo esc_attr( $aimp_gold ); ?>;border-radius:12px;font-family:'Courier New',monospace;font-size:22px;font-weight:bold;letter-spacing:2px;color:<?php echo esc_attr( $aimp_brown ); ?>;"><?php echo esc_html( $aimp_card['code'] ); ?></p>
			<?php if ( $aimp_card['expires'] ) : ?>
				<p style="margin:0 0 8px;font-size:14px;font-weight:600;">
					<?php
					/* translators: %s: date */
					printf( esc_html__( 'Valid until %s', 'atelier-irisee-master-plugin' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $aimp_card['expires'] ) ) ) );
					?>
				</p>
			<?php endif; ?>
			<p style="margin:0;font-size:14px;font-weight:600;"><?php esc_html_e( 'Enter the code in the gift card field in your cart or at checkout. Anything you do not spend stays on the card.', 'atelier-irisee-master-plugin' ); ?></p>
			<?php if ( $aimp_with_shop ) : ?>
				<p style="margin:20px 0 0;">
					<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" style="display:inline-block;padding:12px 26px;border:5px double <?php echo esc_attr( $aimp_gold ); ?>;border-radius:100px;color:<?php echo esc_attr( $aimp_gold ); ?>;font-weight:bold;text-decoration:none;"><?php esc_html_e( 'Visit the shop', 'atelier-irisee-master-plugin' ); ?></a>
				</p>
			<?php endif; ?>
		</td>
	</tr>
</table>
