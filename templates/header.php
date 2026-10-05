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
			<?php echo AIMP_Header::logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in logo_html(). ?>
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
			<?php
			$aimp_current = AIMP_I18n::current();
			$aimp_langs   = AIMP_Shortcode::languages_data();
			$aimp_active  = null;
			foreach ( $aimp_langs as $aimp_lang ) {
				if ( $aimp_lang['code'] === $aimp_current ) {
					$aimp_active = $aimp_lang;
				}
			}
			?>
			<?php if ( $aimp_active && count( $aimp_langs ) > 1 ) : ?>
				<div class="aimp-header-lang" data-aimp-lang-switch>
					<button type="button" class="aimp-lang-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="aimp-lang-list" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name */ __( 'Language: %s', 'atelier-irisee-master-plugin' ), $aimp_active['name'] ) ); ?>">
						<img src="<?php echo esc_url( $aimp_active['flag'] ); ?>" alt="" width="26" height="18">
						<span class="aimp-lang-caret" aria-hidden="true"></span>
					</button>
					<ul class="aimp-lang-list" id="aimp-lang-list" hidden>
						<?php foreach ( $aimp_langs as $aimp_lang ) : ?>
							<?php if ( $aimp_lang['code'] === $aimp_current ) { continue; } ?>
							<li>
								<a href="<?php echo esc_url( add_query_arg( 'aimp_lang', $aimp_lang['code'] ) ); ?>" data-lang="<?php echo esc_attr( $aimp_lang['code'] ); ?>" lang="<?php echo esc_attr( $aimp_lang['locale'] ); ?>">
									<img src="<?php echo esc_url( $aimp_lang['flag'] ); ?>" alt="" width="24" height="16">
									<span><?php echo esc_html( $aimp_lang['name'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
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
