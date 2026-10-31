<?php
/**
 * Atelier Irisee header.
 *
 * Copy this file to yourtheme/atelier-irisee/header.php to change the markup.
 *
 * @var array $data favorites_url, cart_url, account_url, search_url, cart_badge (HTML), menu (HTML, empty without a menu).
 *
 * @package AtelierIriseeMasterPlugin
 */

defined( 'ABSPATH' ) || exit;

// Settings from the Design studio (Atelier Irisee → Design studio), with the standard when nothing is set.
$aimp_d = function ( $part, $key, $default = null ) {
	return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( $part, $key, $default ) : $default;
};
?>
<header class="aimp-header" data-aimp-header<?php echo $aimp_d( 'header', 'nosticky' ) ? ' data-sticky="0"' : ''; ?><?php echo $aimp_d( 'header', 'noshrink' ) ? ' data-shrink="0"' : ''; ?>>
	<div class="aimp-header-inner">
		<div class="aimp-header-brand">
			<?php echo AIMP_Header::logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in logo_html(). ?>
		</div>

		<?php // Search: centred in this row between logo and icons, fades in (search.js). Without JavaScript the form opens the All products page. ?>
		<div class="aimp-header-search" id="aimp-header-search" data-aimp-search hidden>
			<form class="aimp-search-form" role="search" action="<?php echo esc_url( $data['search_url'] ); ?>" method="get">
				<label class="screen-reader-text" for="aimp-search-input"><?php esc_html_e( 'Search products…', 'atelier-irisee-master-plugin' ); ?></label>
				<span class="aimp-search-icon"><?php echo AIMP_Header::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<input type="search" id="aimp-search-input" class="aimp-search-input" name="aimp_q" placeholder="<?php esc_attr_e( 'Search products…', 'atelier-irisee-master-plugin' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="aimp-search-results" aria-autocomplete="list">
				<button type="button" class="aimp-search-close" data-aimp-search-close aria-label="<?php esc_attr_e( 'Close', 'atelier-irisee-master-plugin' ); ?>"><?php echo AIMP_Header::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			</form>
			<ul class="aimp-search-results" id="aimp-search-results" role="listbox" hidden></ul>
			<p class="aimp-search-status" aria-live="polite"></p>
		</div>

		<nav class="aimp-header-icons" aria-label="<?php esc_attr_e( 'Shortcuts', 'atelier-irisee-master-plugin' ); ?>">
			<?php if ( class_exists( 'AIMP_Footer' ) && AIMP_Footer::social_opt( 'so_header' ) ) : ?>
				<?php echo AIMP_Footer::socials_html( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in socials_html(). ?>
			<?php endif; ?>
			<button type="button" class="aimp-header-link aimp-header-search-toggle" data-aimp-search-toggle aria-expanded="false" aria-controls="aimp-header-search">
				<span class="aimp-header-icon-wrap"><?php echo AIMP_Header::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="aimp-header-label"><?php esc_html_e( 'Search', 'atelier-irisee-master-plugin' ); ?></span>
			</button>
			<?php if ( $data['shop_url'] ) : ?>
				<a class="aimp-header-link aimp-header-link--shop" href="<?php echo esc_url( $data['shop_url'] ); ?>">
					<span class="aimp-header-icon-wrap"><?php echo AIMP_Header::icon( 'shop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<span class="aimp-header-label"><?php esc_html_e( 'Shop', 'atelier-irisee-master-plugin' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $data['favorites_url'] ) : ?>
				<a class="aimp-header-link aimp-header-link--fav" href="<?php echo esc_url( $data['favorites_url'] ); ?>">
					<span class="aimp-header-icon-wrap"><?php echo AIMP_Header::icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span class="aimp-header-count" data-aimp-fav-count hidden></span></span>
					<span class="aimp-header-label"><?php esc_html_e( 'Favourites', 'atelier-irisee-master-plugin' ); ?></span>
				</a>
			<?php endif; ?>
			<a class="aimp-header-link aimp-header-link--cart" href="<?php echo esc_url( $data['cart_url'] ); ?>">
				<span class="aimp-header-icon-wrap"><?php echo AIMP_Header::icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo $data['cart_badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from an integer. ?></span>
				<span class="aimp-header-label"><?php esc_html_e( 'Cart', 'atelier-irisee-master-plugin' ); ?></span>
			</a>
			<a class="aimp-header-link aimp-header-link--account" href="<?php echo esc_url( $data['account_url'] ); ?>">
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
						<?php $aimp_lang_style = $aimp_d( 'header-lang', 'lang_style', '' ); ?>
						<?php if ( 'code' === $aimp_lang_style ) : ?>
							<span class="aimp-lang-name"><?php echo esc_html( strtoupper( $aimp_active['code'] ) ); ?></span>
						<?php elseif ( 'name' === $aimp_lang_style ) : ?>
							<span class="aimp-lang-name"><?php echo esc_html( $aimp_active['name'] ); ?></span>
						<?php endif; ?>
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

	<?php
	// Design studio (Gold info bar): on which pages, a link per text (one address per line, in the order of the texts),
	// and the seconds per text when they take turns.
	$aimp_where = $aimp_d( 'info-bar', 'ib_where', '' );
	$aimp_show  = ! ( ( 'home' === $aimp_where && ! is_front_page() ) || ( 'nocheckout' === $aimp_where && function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) );
	$aimp_links = preg_split( '/\r\n|\r|\n/', (string) $aimp_d( 'info-bar', 'ib_links', '' ) );
	$aimp_speed = (int) $aimp_d( 'info-bar', 'ib_speed', 0 );
	?>
	<?php if ( ! empty( $data['info_bar'] ) && $aimp_show ) : ?>
		<?php // Gold info bar (Site look → Info bar); folds away while scrolling down (header.js). ?>
		<div class="aimp-info-bar" data-aimp-info-bar<?php echo $aimp_speed ? ' data-speed="' . esc_attr( (string) $aimp_speed ) . '"' : ''; ?>>
			<ul class="aimp-info-bar-items">
				<?php foreach ( array_values( $data['info_bar'] ) as $aimp_i => $aimp_info ) : ?>
					<?php $aimp_link = isset( $aimp_links[ $aimp_i ] ) ? esc_url( trim( $aimp_links[ $aimp_i ] ) ) : ''; ?>
					<li><span class="aimp-info-bar-mark" aria-hidden="true">✦</span><?php if ( '' !== $aimp_link ) : ?><a href="<?php echo esc_url( $aimp_link ); ?>"><?php echo esc_html( $aimp_info ); ?></a><?php else : ?><?php echo esc_html( $aimp_info ); ?><?php endif; ?></li>
				<?php endforeach; ?>
				<?php // Guests: the welcome discount, opening the register form. ?>
				<?php $aimp_offer = class_exists( 'AIMP_Coupons' ) ? AIMP_Coupons::offer_short() : ''; ?>
				<?php if ( '' !== $aimp_offer ) : ?>
					<li class="aimp-info-bar-offer"><span class="aimp-info-bar-mark" aria-hidden="true">✦</span><a class="aimp-reg-tgr" href="#aimp-register"><?php echo esc_html( $aimp_offer ); ?></a></li>
				<?php endif; ?>
			</ul>
			<?php if ( class_exists( 'AIMP_Footer' ) && AIMP_Footer::social_opt( 'so_info' ) ) : ?>
				<?php echo AIMP_Footer::socials_html( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in socials_html(). ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php // Phones: an app-like tab bar at the bottom of the screen (header.css). ?>
	<nav class="aimp-tabbar" aria-label="<?php esc_attr_e( 'Quick navigation', 'atelier-irisee-master-plugin' ); ?>">
		<?php
		// Design studio (Phone tab bar): which tabs (Home, Shop and Customizer as standard; the cart is in the top bar).
		$aimp_tabs = array();
		if ( ! $aimp_d( 'tabbar', 'tb_nohome' ) ) {
			$aimp_tabs[] = array( $data['home_url'], 'home', __( 'Home', 'atelier-irisee-master-plugin' ) );
		}
		if ( ! $aimp_d( 'tabbar', 'tb_noshop' ) ) {
			$aimp_tabs[] = array( $data['shop_url'], 'shop', __( 'Shop', 'atelier-irisee-master-plugin' ) );
		}
		if ( ! $aimp_d( 'tabbar', 'tb_nokit' ) ) {
			// A short label: "Naaipakketten" doesn't fit a phone's tab.
			$aimp_tabs[] = array( isset( $data['kit_url'] ) ? $data['kit_url'] : '', 'kit', __( 'Kits', 'atelier-irisee-master-plugin' ) );
		}
		if ( $aimp_d( 'tabbar', 'tb_fav' ) ) {
			$aimp_tabs[] = array( $data['favorites_url'], 'heart', __( 'Favourites', 'atelier-irisee-master-plugin' ) );
		}
		if ( $aimp_d( 'tabbar', 'tb_cart' ) ) {
			$aimp_tabs[] = array( $data['cart_url'], 'bag', __( 'Basket', 'atelier-irisee-master-plugin' ) );
		}
		if ( $aimp_d( 'tabbar', 'tb_account' ) ) {
			$aimp_tabs[] = array( $data['account_url'], 'user', __( 'Account', 'atelier-irisee-master-plugin' ) );
		}
		$aimp_tab_labels = ! $aimp_d( 'tabbar', 'tb_nolabels' );
		foreach ( $aimp_tabs as $aimp_tab ) :
			if ( '' === (string) $aimp_tab[0] ) {
				continue;
			}
			$aimp_now = AIMP_Header::is_current( $aimp_tab[0], isset( $data['current_url'] ) ? $data['current_url'] : '' );
			?>
			<a class="aimp-tabbar-item<?php echo $aimp_now ? ' is-current' : ''; ?>" href="<?php echo esc_url( $aimp_tab[0] ); ?>"<?php echo $aimp_now ? ' aria-current="page"' : ''; ?>>
				<span class="aimp-tabbar-icon"><?php echo AIMP_Header::icon( $aimp_tab[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo 'bag' === $aimp_tab[1] ? $data['cart_badge'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from an integer. ?></span>
				<span class="aimp-tabbar-label"<?php echo $aimp_tab_labels ? '' : ' hidden'; ?>><?php echo esc_html( $aimp_tab[2] ); ?></span>
			</a>
		<?php endforeach; ?>
		<?php if ( $aimp_d( 'tabbar', 'tb_search' ) ) : ?>
			<button type="button" class="aimp-tabbar-item" data-aimp-tab-search>
				<span class="aimp-tabbar-icon"><?php echo AIMP_Header::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="aimp-tabbar-label"<?php echo $aimp_tab_labels ? '' : ' hidden'; ?>><?php esc_html_e( 'Search', 'atelier-irisee-master-plugin' ); ?></span>
			</button>
		<?php endif; ?>
		<button type="button" class="aimp-tabbar-item" data-aimp-menu-open aria-expanded="false" aria-controls="aimp-side-menu">
			<span class="aimp-tabbar-icon"><?php echo AIMP_Header::icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span class="aimp-tabbar-label"<?php echo $aimp_tab_labels ? '' : ' hidden'; ?>><?php esc_html_e( 'Menu', 'atelier-irisee-master-plugin' ); ?></span>
		</button>
	</nav>

	<div class="aimp-side-menu-backdrop" data-aimp-menu-close hidden></div>
	<nav class="aimp-side-menu" id="aimp-side-menu" data-all-url="<?php echo esc_url( $data['shop_url'] ); ?>" data-open="<?php echo esc_attr( (string) $aimp_d( 'side-menu', 'sm_open', '' ) ); ?>" aria-label="<?php esc_attr_e( 'Menu', 'atelier-irisee-master-plugin' ); ?>" aria-hidden="true">
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
		<?php
		// Design studio (Side menu): a picture and contact details under the menu; Settings → Social media: the icons.
		$aimp_side_img     = (int) $aimp_d( 'side-menu', 'sm_img', 0 );
		$aimp_side_contact = (string) $aimp_d( 'side-menu', 'sm_contact', '' );
		$aimp_side_socials = class_exists( 'AIMP_Footer' ) && AIMP_Footer::social_opt( 'so_side' ) ? AIMP_Footer::socials() : array();
		?>
		<?php if ( $aimp_side_img || '' !== $aimp_side_contact || $aimp_side_socials ) : ?>
			<div class="aimp-side-menu-extra">
				<?php if ( $aimp_side_img ) : ?>
					<?php echo wp_get_attachment_image( $aimp_side_img, 'medium', false, array( 'class' => 'aimp-side-menu-picture', 'loading' => 'lazy' ) ); ?>
				<?php endif; ?>
				<?php if ( '' !== $aimp_side_contact ) : ?>
					<p class="aimp-side-menu-contact"><?php echo nl2br( esc_html( $aimp_side_contact ) ); ?></p>
				<?php endif; ?>
				<?php if ( $aimp_side_socials ) : ?>
					<ul class="aimp-side-menu-socials">
						<?php foreach ( $aimp_side_socials as $aimp_network => $aimp_url ) : ?>
							<li><a href="<?php echo esc_url( $aimp_url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $aimp_network ) ); ?>"><?php echo AIMP_Footer::social_icon( $aimp_network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</nav>
</header>
<?php
// Settings → Social media: a strip of icons at the side of the screen; on phones a small tab opens it.
$aimp_float       = class_exists( 'AIMP_Footer' ) ? (string) AIMP_Footer::social_opt( 'so_float' ) : '';
$aimp_float_icons = in_array( $aimp_float, array( 'left', 'right' ), true ) && class_exists( 'AIMP_Footer' ) ? AIMP_Footer::socials_html( 'float' ) : '';
?>
<?php if ( '' !== $aimp_float_icons ) : ?>
	<aside class="aimp-socials-float is-<?php echo esc_attr( $aimp_float ); ?>" aria-label="<?php esc_attr_e( 'Social media', 'atelier-irisee-master-plugin' ); ?>">
		<button type="button" class="aimp-socials-toggle" data-aimp-socials-toggle aria-expanded="false" aria-label="<?php esc_attr_e( 'Social media', 'atelier-irisee-master-plugin' ); ?>"><?php echo AIMP_Footer::social_icon( 'instagram' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
		<?php echo $aimp_float_icons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in socials_html(). ?>
	</aside>
<?php endif; ?>
