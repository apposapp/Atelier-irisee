<?php
/**
 * Reset password form (opened from the link in the reset email; login.js fills in key and login).
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/resetpw.php
 *
 * @var string $prefix Unique ID prefix.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="aimp-el-title"><?php esc_html_e( 'Choose a new password', 'atelier-irisee-master-plugin' ); ?></h2>
<form class="aimp-el-form" data-aimp-form="resetpw" novalidate>
	<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="key" value="">
	<input type="hidden" name="login" value="">
	<?php
	foreach ( AIMP_Login_Fields::builtin( 'resetpw' ) as $aimp_field ) {
		echo AIMP_Login_Fields::render( $aimp_field, '', $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}
	?>
	<?php echo AIMP_Login::form_bottom( 'resetpw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Save password', 'atelier-irisee-master-plugin' ); ?></button>
</form>
