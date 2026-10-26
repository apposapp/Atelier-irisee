<?php
/**
 * The gift card email with the drawn card: the theme's intro, the front and the back, the code and the downloads.
 * The front, the back and a PDF of both are also attached to the email.
 * Inline styles, so it also looks right in email programs.
 *
 * Override in your theme: yourtheme/atelier-irisee/giftcards/email.php
 *
 * @var array  $aimp_card  Gift card data (see AIMP_Giftcards::get()).
 * @var string $aimp_intro The theme's intro text ('' = the standard one).
 * @var array  $aimp_links front, back (to show), front_download, back_download, pdf.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aimp_gold  = '#b38f4f';
$aimp_brown = '#613907';
$aimp_link  = 'color:' . $aimp_gold . ';font-weight:bold;text-decoration:underline;';
?>
<div style="max-width:600px;margin:0 auto;font-family:Georgia,'Times New Roman',serif;color:<?php echo esc_attr( $aimp_brown ); ?>;text-align:center;">
	<p style="margin:0 0 22px;font-size:17px;line-height:1.6;">
		<?php
		if ( '' !== $aimp_intro ) {
			echo nl2br( esc_html( $aimp_intro ) );
		} elseif ( $aimp_card['sender_name'] && 'email_other' === $aimp_card['delivery'] ) {
			/* translators: %s: sender name */
			printf( esc_html__( '%s gives you a gift card. Here it is, front and back.', 'atelier-irisee-master-plugin' ), esc_html( $aimp_card['sender_name'] ) );
		} else {
			esc_html_e( 'Here is your gift card, front and back.', 'atelier-irisee-master-plugin' );
		}
		?>
	</p>
	<p style="margin:0 0 14px;">
		<img src="<?php echo esc_url( $aimp_links['front'] ); ?>" alt="<?php esc_attr_e( 'Gift card, front', 'atelier-irisee-master-plugin' ); ?>" width="580" style="display:block;width:100%;max-width:580px;height:auto;margin:0 auto;border-radius:14px;">
	</p>
	<p style="margin:0 0 24px;">
		<img src="<?php echo esc_url( $aimp_links['back'] ); ?>" alt="<?php esc_attr_e( 'Gift card, back', 'atelier-irisee-master-plugin' ); ?>" width="580" style="display:block;width:100%;max-width:580px;height:auto;margin:0 auto;border-radius:14px;">
	</p>
	<p style="margin:0 0 6px;font-size:14px;font-weight:600;"><?php esc_html_e( 'Your code', 'atelier-irisee-master-plugin' ); ?></p>
	<p style="display:inline-block;margin:0 0 18px;padding:10px 18px;border:2px dashed <?php echo esc_attr( $aimp_gold ); ?>;border-radius:12px;font-family:'Courier New',monospace;font-size:22px;font-weight:bold;letter-spacing:2px;"><?php echo esc_html( $aimp_card['code'] ); ?></p>
	<p style="margin:0 0 8px;font-size:15px;font-weight:600;"><?php esc_html_e( 'Save or print your gift card:', 'atelier-irisee-master-plugin' ); ?></p>
	<p style="margin:0 0 22px;font-size:15px;">
		<a href="<?php echo esc_url( $aimp_links['front_download'] ); ?>" style="<?php echo esc_attr( $aimp_link ); ?>"><?php esc_html_e( 'Front', 'atelier-irisee-master-plugin' ); ?></a>
		&nbsp;·&nbsp;
		<a href="<?php echo esc_url( $aimp_links['back_download'] ); ?>" style="<?php echo esc_attr( $aimp_link ); ?>"><?php esc_html_e( 'Back', 'atelier-irisee-master-plugin' ); ?></a>
		&nbsp;·&nbsp;
		<a href="<?php echo esc_url( $aimp_links['pdf'] ); ?>" style="<?php echo esc_attr( $aimp_link ); ?>"><?php esc_html_e( 'PDF to print (both sides)', 'atelier-irisee-master-plugin' ); ?></a>
	</p>
	<p style="margin:0 0 6px;font-size:14px;"><?php esc_html_e( 'They are also attached to this email.', 'atelier-irisee-master-plugin' ); ?></p>
	<p style="margin:20px 0 0;">
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" style="display:inline-block;padding:12px 26px;border:5px double <?php echo esc_attr( $aimp_gold ); ?>;border-radius:100px;color:<?php echo esc_attr( $aimp_gold ); ?>;font-weight:bold;text-decoration:none;"><?php esc_html_e( 'Visit the shop', 'atelier-irisee-master-plugin' ); ?></a>
	</p>
</div>
