<?php
/**
 * Login form.
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/login.php
 *
 * @var string $prefix   Unique ID prefix.
 * @var bool   $register Whether registration is allowed.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="aimp-el-title"><?php esc_html_e( 'Log in', 'atelier-irisee-master-plugin' ); ?></h2>
<form class="aimp-el-form" data-aimp-form="login" novalidate>
	<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php
	foreach ( AIMP_Login_Fields::builtin( 'login' ) as $aimp_field ) {
		echo AIMP_Login_Fields::render( $aimp_field, '', $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}
	?>
	<div class="aimp-el-row">
		<?php if ( AIMP_Login::opt( 'remember_me' ) ) : ?>
			<label class="aimp-el-check"><input type="checkbox" name="aimp[remember]" value="1"> <?php esc_html_e( 'Remember me', 'atelier-irisee-master-plugin' ); ?></label>
		<?php endif; ?>
		<button type="button" class="aimp-el-link" data-aimp-goto="lostpw"><?php esc_html_e( 'Forgot your password?', 'atelier-irisee-master-plugin' ); ?></button>
	</div>
	<?php echo AIMP_Login::form_bottom( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Log in', 'atelier-irisee-master-plugin' ); ?></button>
</form>
<?php echo AIMP_Login_Social::buttons_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in buttons_html(). ?>
<?php if ( $register && 'links' === AIMP_Login::opt( 'navigation' ) ) : ?>
	<p class="aimp-el-switch"><?php esc_html_e( 'No account yet?', 'atelier-irisee-master-plugin' ); ?> <button type="button" class="aimp-el-link" data-aimp-goto="register"><?php esc_html_e( 'Register', 'atelier-irisee-master-plugin' ); ?></button></p>
<?php endif; ?>
