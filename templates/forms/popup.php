<?php
/**
 * Popup / slider wrapper (printed in the footer for logged-out visitors).
 *
 * Override in your theme: yourtheme/atelier-irisee/forms/popup.php
 *
 * @var string $layout    popup | slider.
 * @var string $animation fade | slide | zoom.
 * @var string $image     Side image URL ('' = none).
 * @var string $container The forms (escaped HTML).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aimp-el-modal aimp-el-modal--<?php echo esc_attr( $layout ); ?> aimp-el-anim--<?php echo esc_attr( $animation ); ?><?php echo $image ? ' aimp-el-modal--image' : ''; ?>" data-aimp-modal hidden>
	<div class="aimp-el-overlay" data-aimp-close></div>
	<div class="aimp-el-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Log in or register', 'atelier-irisee-master-plugin' ); ?>" tabindex="-1">
		<button type="button" class="aimp-el-close" data-aimp-close aria-label="<?php esc_attr_e( 'Close', 'atelier-irisee-master-plugin' ); ?>">&times;</button>
		<?php if ( $image ) : ?>
			<div class="aimp-el-image" style="background-image:url('<?php echo esc_url( $image ); ?>')"></div>
		<?php endif; ?>
		<div class="aimp-el-dialog-body">
			<?php echo $container; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in container.php. ?>
		</div>
	</div>
</div>
