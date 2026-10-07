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
<?php echo AIMP_Emails::button( __( 'Confirm my subscription', 'atelier-irisee-master-plugin' ), $confirm_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button(). ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
