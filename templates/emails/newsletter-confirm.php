<?php
/**
 * Newsletter confirmation email (HTML).
 *
 * Copy to yourtheme/woocommerce/emails/newsletter-confirm.php to change it.
 *
 * @var string   $email_heading      Heading.
 * @var string   $confirm_url        Confirmation link.
 * @var string   $additional_content Extra text from the email settings.
 * @var WC_Email $email              Email.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Hello,', 'atelier-irisee-master-plugin' ); ?></p>
<p><?php esc_html_e( 'Please confirm that you would like to receive our newsletter.', 'atelier-irisee-master-plugin' ); ?></p>
<p style="margin:24px 0">
	<a href="<?php echo esc_url( $confirm_url ); ?>" style="display:inline-block;padding:12px 26px;border:4px double #b38f4f;border-radius:100px;color:#613907;font-weight:bold;text-decoration:none"><?php esc_html_e( 'Confirm my subscription', 'atelier-irisee-master-plugin' ); ?></a>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
