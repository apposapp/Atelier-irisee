<?php
/**
 * Profile form ([aimp_profile], or My Account > Account details when that option is on).
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/profile.php
 *
 * @var WP_User $user   Current user.
 * @var string  $prefix Unique ID prefix.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aimp_social_only = (bool) get_user_meta( $user->ID, AIMP_Login_Social::SOCIAL_ONLY, true );
$aimp_values      = array(
	'first_name'   => $user->first_name,
	'last_name'    => $user->last_name,
	'display_name' => $user->display_name,
	'email'        => $user->user_email,
);
$aimp_pending     = get_user_meta( $user->ID, AIMP_Login_Verification::PENDING_EMAIL, true );
?>
<div class="aimp-el aimp-el--profile aimp-el--buttons-<?php echo esc_attr( AIMP_Login::opt( 'button_style' ) ); ?>" data-aimp-el>
	<form class="aimp-el-form" data-aimp-form="profile" enctype="multipart/form-data" novalidate>
		<?php echo AIMP_Login::form_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="aimp-el-fields">
			<?php
			foreach ( AIMP_Login_Fields::builtin( 'profile' ) as $aimp_field ) {
				echo AIMP_Login_Fields::render( $aimp_field, $aimp_values[ $aimp_field['key'] ], $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
			}
			?>
			<?php if ( is_array( $aimp_pending ) && ! empty( $aimp_pending['email'] ) && $aimp_pending['expires'] > time() ) : ?>
				<p class="aimp-el-help">
					<?php
					/* translators: %s: new email address */
					printf( esc_html__( 'Waiting for confirmation of your new email address %s.', 'atelier-irisee-master-plugin' ), '<strong>' . esc_html( $aimp_pending['email'] ) . '</strong>' );
					?>
				</p>
			<?php endif; ?>
			<?php
			foreach ( AIMP_Login_Fields::custom( 'profile' ) as $aimp_field ) {
				echo AIMP_Login_Fields::render( $aimp_field, AIMP_Login_Fields::value( $user->ID, $aimp_field ), $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
			}
			?>
		</div>

		<fieldset class="aimp-el-fieldset">
			<legend><?php esc_html_e( 'Change password', 'atelier-irisee-master-plugin' ); ?></legend>
			<p class="aimp-el-help">
				<?php
				echo $aimp_social_only
					? esc_html__( 'You signed up with a social account. Choose a password here if you also want to log in with your email address.', 'atelier-irisee-master-plugin' )
					: esc_html__( 'Leave these fields empty to keep your current password.', 'atelier-irisee-master-plugin' );
				?>
			</p>
			<?php
			$aimp_password_fields = array();
			if ( ! $aimp_social_only ) {
				$aimp_password_fields[] = array(
					'key'          => 'current_password',
					'type'         => 'password',
					'label'        => __( 'Current password', 'atelier-irisee-master-plugin' ),
					'autocomplete' => 'current-password',
				);
			}
			$aimp_password_fields[] = array(
				'key'          => 'new_password',
				'type'         => 'password',
				'label'        => __( 'New password', 'atelier-irisee-master-plugin' ),
				'autocomplete' => 'new-password',
				'strength'     => 1,
			);
			$aimp_password_fields[] = array(
				'key'          => 'new_password2',
				'type'         => 'password',
				'label'        => __( 'Confirm new password', 'atelier-irisee-master-plugin' ),
				'autocomplete' => 'new-password',
				'confirm'      => 'new_password',
			);
			foreach ( $aimp_password_fields as $aimp_field ) {
				echo AIMP_Login_Fields::render( $aimp_field, '', $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
			}
			?>
		</fieldset>

		<button type="submit" class="aimp-el-button aimp-el-submit"><?php esc_html_e( 'Save changes', 'atelier-irisee-master-plugin' ); ?></button>
	</form>
	<?php echo AIMP_Login_Social::connections_html( $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in connections_html(). ?>
</div>
