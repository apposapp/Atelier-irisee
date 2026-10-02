<?php
/**
 * "Enter your email address" step after a social login that did not share an email address (e.g. X).
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/social-email.php
 *
 * @var string $prefix Unique ID prefix.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="aimp-el-title"><?php esc_html_e( 'One more step', 'atelier-irisee-master-plugin' ); ?></h2>
<p class="aimp-el-help"><?php esc_html_e( 'Enter your email address to finish creating your account.', 'atelier-irisee-master-plugin' ); ?></p>
<form class="aimp-el-form" data-aimp-form="social_email" novalidate>
	<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="token" value="">
	<?php
	echo AIMP_Login_Fields::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		array(
			'key'          => 'email',
			'type'         => 'email',
			'label'        => __( 'Email address', 'atelier-irisee-master-plugin' ),
			'required'     => 1,
			'autocomplete' => 'email',
		),
		'',
		$prefix
	);
	?>
	<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Continue', 'atelier-irisee-master-plugin' ); ?></button>
</form>
