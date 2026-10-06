<?php
/**
 * Newsletter confirmation email (plain text).
 *
 * @var string   $email_heading      Heading.
 * @var string   $confirm_url        Confirmation link.
 * @var string   $additional_content Extra text from the email settings.
 * @var WC_Email $email              Email.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
echo esc_html__( 'Hello,', 'atelier-irisee-master-plugin' ) . "\n\n";
echo esc_html__( 'Please confirm that you would like to receive our newsletter.', 'atelier-irisee-master-plugin' ) . "\n\n";
echo esc_html__( 'Confirm my subscription', 'atelier-irisee-master-plugin' ) . ': ' . esc_url_raw( $confirm_url ) . "\n\n";
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
