<?php
/**
 * Atelier Irisee header.
 *
 * Copy this file to yourtheme/atelier-irisee/header.php to change the markup.
 *
 * @var array $data favorites_url, cart_url, account_url, cart_badge (HTML), menu (HTML, empty without a menu).
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="aimp-header" data-aimp-header>
	<div class="aimp-header-inner">
		<div class="aimp-header-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="aimp-header-sitename" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>

		<nav class="aimp-header-icons" aria-label="<?php esc_attr_e( 'Shortcuts', 'atelier-irisee-master-plugin' ); ?>">
			<?php if ( $data['favorites_url'] ) : ?>
				<a class="aimp-header-link" href="<?php echo esc_url( $data['favorites_url'] ); ?>">
					<span class="aimp-header-icon-wrap"><?php echo AIMP_Header::icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span class="aimp-header-count" data-aimp-fav-count hidden></span></span>
					<span class="aimp-header-label"><?php esc_html_e( 'Favourites', 'atelier-irisee-master-plugin' ); ?></span>
				</a>
			<?php endif; ?>
			<a class="aimp-header-link" href="<?php echo esc_url( $data['cart_url'] ); ?>">
				<span class="aimp-header-icon-wrap"><?php echo AIMP_Header::icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo $data['cart_badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from an integer. ?></span>
				<span class="aimp-header-label"><?php esc_html_e( 'Cart', 'atelier-irisee-master-plugin' ); ?></span>
			</a>
			<a class="aimp-header-link" href="<?php echo esc_url( $data['account_url'] ); ?>">
				<span class="aimp-header-icon-wrap"><?php echo AIMP_Header::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="aimp-header-label"><?php esc_html_e( 'Account', 'atelier-irisee-master-plugin' ); ?></span>
			</a>
		</nav>
	</div>

	<div class="aimp-header-bar">
		<div class="aimp-header-inner">
			<button type="button" class="aimp-header-menu-toggle" data-aimp-menu-open aria-expanded="false" aria-controls="aimp-side-menu">
				<?php echo AIMP_Header::icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<span><?php esc_html_e( 'Menu', 'atelier-irisee-master-plugin' ); ?></span>
			</button>
		</div>
	</div>

	<div class="aimp-side-menu-backdrop" data-aimp-menu-close hidden></div>
	<nav class="aimp-side-menu" id="aimp-side-menu" aria-label="<?php esc_attr_e( 'Menu', 'atelier-irisee-master-plugin' ); ?>" aria-hidden="true">
		<div class="aimp-side-menu-head">
			<span class="aimp-side-menu-title"><?php esc_html_e( 'Menu', 'atelier-irisee-master-plugin' ); ?></span>
			<button type="button" class="aimp-side-menu-close" data-aimp-menu-close aria-label="<?php esc_attr_e( 'Close menu', 'atelier-irisee-master-plugin' ); ?>">
				<?php echo AIMP_Header::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</button>
		</div>
		<?php if ( $data['menu'] ) : ?>
			<?php echo $data['menu']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu() output. ?>
		<?php elseif ( current_user_can( 'edit_theme_options' ) ) : ?>
			<p class="aimp-side-menu-empty"><?php esc_html_e( 'Add a menu under Appearance → Menus and assign it to "Atelier Irisee side menu".', 'atelier-irisee-master-plugin' ); ?></p>
		<?php endif; ?>
	</nav>
</header>
