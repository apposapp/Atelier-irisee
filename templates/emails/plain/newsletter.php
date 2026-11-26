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
if ( isset( $newsletter['source'] ) && 'builder' === $newsletter['source'] && class_exists( 'AIMP_Newsletter_Render' ) ) {
	echo esc_html( AIMP_Newsletter_Render::text( isset( $newsletter['rows'] ) ? $newsletter['rows'] : array(), isset( $newsletter['layout'] ) ? $newsletter['layout'] : 'full', isset( $newsletter['view_url'] ) ? (string) $newsletter['view_url'] : '' ) ) . "\n\n";
} else {
	echo esc_html( wp_strip_all_tags( isset( $newsletter['content'] ) ? $newsletter['content'] : '' ) ) . "\n\n";
}
if ( ! empty( $newsletter['button_text'] ) && ! empty( $newsletter['button_url'] ) ) {
	echo esc_html( $newsletter['button_text'] ) . ': ' . esc_url_raw( $newsletter['button_url'] ) . "\n\n";
}
if ( ! empty( $newsletter['unsubscribe'] ) ) {
	echo esc_html__( 'Unsubscribe', 'atelier-irisee-master-plugin' ) . ': ' . esc_url_raw( $newsletter['unsubscribe'] ) . "\n\n";
}
echo class_exists( 'AIMP_Email_Connect' ) ? esc_html( AIMP_Email_Connect::text() ) : '';
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
