<?php
/**
 * Updates from GitHub releases.
 *
 * The plugin header sets "Update URI: https://github.com/apposapp/Atelier-irisee", so WordPress never
 * asks wordpress.org about this plugin and instead calls the "update_plugins_github.com" filter (WP 5.8+).
 * We answer with the latest GitHub release; WordPress compares versions and shows "Update available".
 *
 * Releases are created by .github/workflows/release.yml whenever the Version in the header is bumped.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Updater {

	const CACHE_KEY = 'aimp_github_release';
	const ASSET     = 'atelier-irisee-master-plugin.zip';

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_force_check' ) );
	}

	private static function slug() {
		return dirname( AIMP_PLUGIN_BASENAME );
	}

	/**
	 * Latest release info, cached.
	 *
	 * @return array|null [ version, package, url, notes, published ]
	 */
	private static function get_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . AIMP_GITHUB_REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Atelier-Irisee-Master-Plugin/' . AIMP_VERSION,
				),
			)
		);

		$release = null;
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $body ) && ! empty( $body['tag_name'] ) && ! empty( $body['assets'] ) ) {
				foreach ( $body['assets'] as $asset ) {
					if ( isset( $asset['name'], $asset['browser_download_url'] ) && self::ASSET === $asset['name'] ) {
						$release = array(
							'version'   => ltrim( $body['tag_name'], 'vV' ),
							'package'   => $asset['browser_download_url'],
							'url'       => isset( $body['html_url'] ) ? $body['html_url'] : 'https://github.com/' . AIMP_GITHUB_REPO,
							'notes'     => isset( $body['body'] ) ? (string) $body['body'] : '',
							'published' => isset( $body['published_at'] ) ? $body['published_at'] : '',
						);
						break;
					}
				}
			}
		}

		// On failure, retry after 30 minutes instead of hitting GitHub on every page load.
		set_site_transient( self::CACHE_KEY, $release ? $release : 'none', $release ? 6 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
		return $release;
	}

	/**
	 * @param array|false $update      Update data from earlier filters.
	 * @param array       $plugin_data Plugin header data.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check_update( $update, $plugin_data, $plugin_file ) {
		if ( AIMP_PLUGIN_BASENAME !== $plugin_file ) {
			return $update;
		}
		$release = self::get_release();
		if ( ! $release ) {
			return $update;
		}
		return array(
			'slug'         => self::slug(),
			'plugin'       => $plugin_file,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
			'icons'        => array(),
			'banners'      => array(),
			'banners_rtl'  => array(),
		);
	}

	/**
	 * Fills the "View details" popup on the Plugins page.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::slug() !== $args->slug ) {
			return $result;
		}
		$release = self::get_release();
		$version = $release ? $release['version'] : AIMP_VERSION;

		return (object) array(
			'name'          => 'Atelier Irisee Master Plugin',
			'slug'          => self::slug(),
			'version'       => $version,
			'author'        => 'Atelier Irisee',
			'homepage'      => 'https://github.com/' . AIMP_GITHUB_REPO,
			'download_link' => $release ? $release['package'] : '',
			'requires'      => '6.3',
			'requires_php'  => '7.4',
			'last_updated'  => $release ? $release['published'] : '',
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Pattern configurator for WooCommerce: pick a pattern and size, a matching fabric, buttons and zips, and add the whole set to the cart.', 'atelier-irisee-master-plugin' ) . '</p>',
				'changelog'   => $release && '' !== trim( $release['notes'] )
					? '<h4>' . esc_html( $version ) . '</h4>' . self::notes_to_html( $release['notes'] )
					: '<p>' . esc_html__( 'See the GitHub releases page for details.', 'atelier-irisee-master-plugin' ) . '</p>',
			),
		);
	}

	/**
	 * Very small Markdown subset (headings, bullet lists, links) for release notes.
	 *
	 * @param string $markdown Release notes.
	 * @return string
	 */
	private static function notes_to_html( $markdown ) {
		$html    = '';
		$in_list = false;
		foreach ( preg_split( '/\r\n|\r|\n/', $markdown ) as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^[-*]\s+(.*)$/', $line, $m ) ) {
				if ( ! $in_list ) {
					$html   .= '<ul>';
					$in_list = true;
				}
				$html .= '<li>' . make_clickable( esc_html( $m[1] ) ) . '</li>';
				continue;
			}
			if ( $in_list ) {
				$html   .= '</ul>';
				$in_list = false;
			}
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^#{1,6}\s+(.*)$/', $line, $m ) ) {
				$html .= '<h4>' . esc_html( $m[1] ) . '</h4>';
			} else {
				$html .= '<p>' . make_clickable( esc_html( $line ) ) . '</p>';
			}
		}
		if ( $in_list ) {
			$html .= '</ul>';
		}
		return wp_kses_post( $html );
	}

	public static function clear_cache_after_upgrade( $upgrader, $options ) {
		if ( isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	/**
	 * "Check again" on Dashboard > Updates bypasses our cache too.
	 */
	public static function maybe_force_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag, same as core.
		if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}
}
