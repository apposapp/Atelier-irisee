<?php
/**
 * All forms in one container. login.js switches between the sections.
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/container.php
 *
 * @var string $active            Section shown first.
 * @var string $context           popup | inline | myaccount.
 * @var string $prefix            Unique ID prefix.
 * @var bool   $register          Whether registration is allowed.
 * @var string $login_redirect    Redirect after login (overrides the setting).
 * @var string $register_redirect Redirect after registration (overrides the setting).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aimp_logo     = AIMP_Login::opt( 'logo' );
$aimp_tabs     = 'tabs' === AIMP_Login::opt( 'navigation' ) && $register && ! AIMP_Login::opt( 'single_field' );
$aimp_sections = array( 'email', 'login', 'register', 'lostpw', 'resetpw', 'verify', 'social-email' );
if ( ! AIMP_Login::opt( 'single_field' ) ) {
	$aimp_sections = array_diff( $aimp_sections, array( 'email' ) );
}
if ( ! $register ) {
	$aimp_sections = array_diff( $aimp_sections, array( 'register' ) );
}
?>
<div class="aimp-el aimp-el--<?php echo esc_attr( $context ); ?> aimp-el--buttons-<?php echo esc_attr( AIMP_Login::opt( 'button_style' ) ); ?>"
	data-aimp-el
	data-active="<?php echo esc_attr( $active ); ?>"
	data-login-redirect="<?php echo esc_url( $login_redirect ); ?>"
	data-register-redirect="<?php echo esc_url( $register_redirect ); ?>">

	<?php if ( $aimp_logo ) : ?>
		<div class="aimp-el-logo"><img src="<?php echo esc_url( $aimp_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></div>
	<?php endif; ?>

	<?php if ( $aimp_tabs ) : ?>
		<div class="aimp-el-tabs" role="tablist">
			<button type="button" role="tab" class="aimp-el-tab" data-aimp-goto="login" aria-selected="<?php echo 'login' === $active ? 'true' : 'false'; ?>"><?php esc_html_e( 'Log in', 'atelier-irisee-master-plugin' ); ?></button>
			<button type="button" role="tab" class="aimp-el-tab" data-aimp-goto="register" aria-selected="<?php echo 'register' === $active ? 'true' : 'false'; ?>"><?php esc_html_e( 'Register', 'atelier-irisee-master-plugin' ); ?></button>
		</div>
	<?php endif; ?>

	<div class="aimp-el-section" data-aimp-section="message" hidden>
		<div class="aimp-el-message-text" role="status"></div>
		<p class="aimp-el-switch"><button type="button" class="aimp-el-link" data-aimp-goto="login"><?php esc_html_e( 'Back to log in', 'atelier-irisee-master-plugin' ); ?></button></p>
	</div>

	<?php foreach ( $aimp_sections as $aimp_section ) : ?>
		<div class="aimp-el-section" data-aimp-section="<?php echo esc_attr( str_replace( '-', '_', $aimp_section ) ); ?>"<?php echo str_replace( '-', '_', $aimp_section ) === $active ? '' : ' hidden'; ?>>
			<?php
			echo AIMP_Login::template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates.
				$aimp_section,
				array(
					'prefix'   => $prefix . '-' . $aimp_section,
					'register' => $register,
					'context'  => $context,
				)
			);
			?>
		</div>
	<?php endforeach; ?>
</div>
