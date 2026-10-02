<?php
/**
 * "Email first" step (when the single-field layout is on): ask the email address, then show
 * the login form (existing account) or the registration form (new customer).
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/email.php
 *
 * @var string $prefix Unique ID prefix.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="aimp-el-title"><?php esc_html_e( 'Log in or create an account', 'atelier-irisee-master-plugin' ); ?></h2>
<p class="aimp-el-help"><?php esc_html_e( 'Enter your email address to continue.', 'atelier-irisee-master-plugin' ); ?></p>
<form class="aimp-el-form" data-aimp-form="check_email" novalidate>
	<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
	<?php echo AIMP_Login::form_bottom( 'check_email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Continue', 'atelier-irisee-master-plugin' ); ?></button>
</form>
<?php echo AIMP_Login_Social::buttons_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in buttons_html(). ?>
