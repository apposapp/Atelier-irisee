<?php
/**
 * Page not found (404).
 *
 * Override in your theme: yourtheme/atelier-irisee/404.php
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="main-content">
	<div class="aimp-configurator aimp-not-found">
		<p class="aimp-not-found-code" aria-hidden="true">404</p>
		<h1 class="aimp-not-found-title"><?php esc_html_e( 'This page does not exist (anymore)', 'atelier-irisee-master-plugin' ); ?></h1>
		<p class="aimp-not-found-text"><?php esc_html_e( 'The link may be old or contain a typo. Search for what you need, or continue below.', 'atelier-irisee-master-plugin' ); ?></p>
		<form class="aimp-not-found-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="aimp-not-found-q"><?php esc_html_e( 'Search products', 'atelier-irisee-master-plugin' ); ?></label>
			<input type="search" id="aimp-not-found-q" name="s" placeholder="<?php esc_attr_e( 'Search products…', 'atelier-irisee-master-plugin' ); ?>">
			<input type="hidden" name="post_type" value="product">
			<button type="submit" class="aimp-button"><?php esc_html_e( 'Search', 'atelier-irisee-master-plugin' ); ?></button>
		</form>
		<?php $aimp_links = AIMP_Not_Found::links(); ?>
		<?php if ( $aimp_links ) : ?>
			<ul class="aimp-not-found-links">
				<?php foreach ( $aimp_links as $aimp_link ) : ?>
					<li><a class="aimp-button aimp-button--ghost" href="<?php echo esc_url( $aimp_link[1] ); ?>"><?php echo esc_html( $aimp_link[0] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
