<?php
/**
 * Lost password form.
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/lostpw.php
 *
 * @var string $prefix Unique ID prefix.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="aimp-el-title"><?php esc_html_e( 'Forgot your password?', 'atelier-irisee-master-plugin' ); ?></h2>
<p class="aimp-el-help"><?php esc_html_e( 'Enter your email address or username. We will send you a link to choose a new password.', 'atelier-irisee-master-plugin' ); ?></p>
<form class="aimp-el-form" data-aimp-form="lostpw" novalidate>
	<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php
	foreach ( AIMP_Login_Fields::builtin( 'lostpw' ) as $aimp_field ) {
		echo AIMP_Login_Fields::render( $aimp_field, '', $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}
	?>
	<?php echo AIMP_Login::form_bottom( 'lostpw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Send link', 'atelier-irisee-master-plugin' ); ?></button>
</form>
<p class="aimp-el-switch"><button type="button" class="aimp-el-link" data-aimp-goto="login"><?php esc_html_e( 'Back to log in', 'atelier-irisee-master-plugin' ); ?></button></p>
