<?php
/**
 * Newsletter email (HTML).
 *
 * Copy to yourtheme/woocommerce/emails/newsletter.php to change it.
 *
 * @var string   $email_heading Heading (the subject).
 * @var array    $newsletter    subject, content (HTML), image, button_text, button_url, unsubscribe, view_url.
 * @var WC_Email $email         Email.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

if ( ! empty( $newsletter['view_url'] ) ) {
	printf(
		'<p style="margin:0 0 16px;font-size:12px;text-align:center"><a href="%1$s">%2$s</a></p>',
		esc_url( $newsletter['view_url'] ),
		esc_html__( 'View in browser', 'atelier-irisee-master-plugin' )
	);
}

if ( ! empty( $newsletter['image'] ) ) {
	printf(
		'<p style="margin:0 0 20px;text-align:center"><img src="%1$s" alt="%2$s" width="520" style="display:block;width:100%%;max-width:520px;height:auto;margin:0 auto;border-radius:16px"></p>',
		esc_url( $newsletter['image'] ),
		esc_attr( isset( $newsletter['subject'] ) ? $newsletter['subject'] : '' )
	);
}

if ( isset( $newsletter['source'] ) && 'builder' === $newsletter['source'] && class_exists( 'AIMP_Newsletter_Render' ) ) {
	// Rows and blocks: the whole newsletter, or a summary per section with "Read more" to the web version.
	echo AIMP_Newsletter_Render::html( isset( $newsletter['rows'] ) ? $newsletter['rows'] : array(), isset( $newsletter['layout'] ) ? $newsletter['layout'] : 'full', isset( $newsletter['view_url'] ) ? (string) $newsletter['view_url'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
} else {
	echo wp_kses_post( wpautop( isset( $newsletter['content'] ) ? $newsletter['content'] : '' ) );
}

if ( ! empty( $newsletter['button_text'] ) && ! empty( $newsletter['button_url'] ) ) {
	echo AIMP_Emails::button( $newsletter['button_text'], $newsletter['button_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button().
}

if ( ! empty( $newsletter['unsubscribe'] ) ) {
	printf(
		'<p style="margin:32px 0 0;font-size:12px;text-align:center;opacity:.8">%1$s <a href="%2$s">%3$s</a></p>',
		esc_html__( 'You get this email because you subscribed to our newsletter.', 'atelier-irisee-master-plugin' ),
		esc_url( $newsletter['unsubscribe'] ),
		esc_html__( 'Unsubscribe', 'atelier-irisee-master-plugin' )
	);
}

do_action( 'woocommerce_email_footer', $email );
