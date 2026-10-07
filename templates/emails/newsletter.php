<?php
/**
 * Newsletter email (HTML).
 *
 * Copy to yourtheme/woocommerce/emails/newsletter.php to change it.
 *
 * @var string   $email_heading Heading (the subject).
 * @var array    $newsletter    subject, content (HTML), button_text, button_url, unsubscribe.
 * @var WC_Email $email         Email.
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

echo wp_kses_post( wpautop( isset( $newsletter['content'] ) ? $newsletter['content'] : '' ) );

if ( ! empty( $newsletter['button_text'] ) && ! empty( $newsletter['button_url'] ) ) {
	printf(
		'<p style="margin:28px 0;text-align:center"><a href="%1$s" style="display:inline-block;padding:12px 28px;border:4px double #b38f4f;border-radius:100px;color:#613907;font-weight:bold;text-decoration:none">%2$s</a></p>',
		esc_url( $newsletter['button_url'] ),
		esc_html( $newsletter['button_text'] )
	);
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
