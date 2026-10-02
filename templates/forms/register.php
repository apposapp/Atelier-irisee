<?php
/**
 * Registration form.
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/register.php
 *
 * @var string $prefix Unique ID prefix.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aimp_terms = (int) AIMP_Login::opt( 'terms_page' );
?>
<h2 class="aimp-el-title"><?php esc_html_e( 'Create an account', 'atelier-irisee-master-plugin' ); ?></h2>
<form class="aimp-el-form" data-aimp-form="register" enctype="multipart/form-data" novalidate>
	<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="aimp-el-fields">
		<?php
		foreach ( array_merge( AIMP_Login_Fields::builtin( 'register' ), AIMP_Login_Fields::custom( 'register' ) ) as $aimp_field ) {
			echo AIMP_Login_Fields::render( $aimp_field, '', $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		}
		?>
	</div>
	<?php if ( $aimp_terms ) : ?>
		<p class="aimp-el-field aimp-el-field--checkbox" data-field="terms">
			<label class="aimp-el-check">
				<input type="checkbox" name="aimp[terms]" value="1" required aria-required="true">
				<?php
				printf(
					/* translators: %s: link to the terms and conditions */
					esc_html__( 'I have read and agree to the %s', 'atelier-irisee-master-plugin' ),
					'<a href="' . esc_url( get_permalink( $aimp_terms ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'terms and conditions', 'atelier-irisee-master-plugin' ) . '</a>'
				);
				?>
				<span class="aimp-el-required" aria-hidden="true">*</span>
			</label>
			<span class="aimp-el-field-error" role="alert"></span>
		</p>
	<?php endif; ?>
	<?php echo AIMP_Login::form_bottom( 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Register', 'atelier-irisee-master-plugin' ); ?></button>
</form>
<?php echo AIMP_Login_Social::buttons_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in buttons_html(). ?>
<?php if ( 'links' === AIMP_Login::opt( 'navigation' ) || AIMP_Login::opt( 'single_field' ) ) : ?>
	<p class="aimp-el-switch"><?php esc_html_e( 'Already have an account?', 'atelier-irisee-master-plugin' ); ?> <button type="button" class="aimp-el-link" data-aimp-goto="login"><?php esc_html_e( 'Log in', 'atelier-irisee-master-plugin' ); ?></button></p>
<?php endif; ?>
