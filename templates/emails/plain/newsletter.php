<?php
/**
 * Newsletter email (plain text).
 *
 * @var string   $email_heading Heading (the subject).
 * @var array    $newsletter    subject, content (HTML), button_text, button_url, unsubscribe.
 * @var WC_Email $email         Email.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
echo esc_html( wp_strip_all_tags( isset( $newsletter['content'] ) ? $newsletter['content'] : '' ) ) . "\n\n";
if ( ! empty( $newsletter['button_text'] ) && ! empty( $newsletter['button_url'] ) ) {
	echo esc_html( $newsletter['button_text'] ) . ': ' . esc_url_raw( $newsletter['button_url'] ) . "\n\n";
}
if ( ! empty( $newsletter['unsubscribe'] ) ) {
	echo esc_html__( 'Unsubscribe', 'atelier-irisee-master-plugin' ) . ': ' . esc_url_raw( $newsletter['unsubscribe'] ) . "\n\n";
}
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
