<?php
/**
 * Verification code form.
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/verify.php
 *
 * @var string $prefix Unique ID prefix.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="aimp-el-title"><?php esc_html_e( 'Verify your email address', 'atelier-irisee-master-plugin' ); ?></h2>
<p class="aimp-el-help aimp-el-verify-text"><?php esc_html_e( 'We sent a 6-digit code to your email address. Enter it below to activate your account.', 'atelier-irisee-master-plugin' ); ?></p>
<form class="aimp-el-form" data-aimp-form="verify_code" novalidate>
	<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="email" value="">
	<p class="aimp-el-field aimp-el-field--code" data-field="code">
		<label class="aimp-el-label" for="<?php echo esc_attr( $prefix ); ?>-code"><?php esc_html_e( 'Verification code', 'atelier-irisee-master-plugin' ); ?></label>
		<input type="text" id="<?php echo esc_attr( $prefix ); ?>-code" name="aimp[code]" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required aria-required="true">
		<span class="aimp-el-field-error" role="alert"></span>
	</p>
	<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Verify', 'atelier-irisee-master-plugin' ); ?></button>
</form>
<p class="aimp-el-switch"><?php esc_html_e( 'No email received?', 'atelier-irisee-master-plugin' ); ?> <button type="button" class="aimp-el-link" data-aimp-resend><?php esc_html_e( 'Send a new code', 'atelier-irisee-master-plugin' ); ?></button></p>
