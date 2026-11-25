<?php
/**
 * The plugin's own scripts of a page as one file: fewer requests for the browser (on the home page 13 files become
 * one). Only for the scripts printed at the end of the page; their settings (wp_localize_script, inline code before
 * them) stay in the page, the file holds only the scripts themselves and is kept in uploads/aimp-cache/ until a
 * script changes.
 *
 * Left as separate files when anything is unusual: a script of another plugin that needs one of ours, inline code
 * after one of ours, a file that cannot be read or written. Switched off with Design studio → Site → Speed.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Bundle {

	const HANDLE = 'aimp-bundle';

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		// Before WordPress prints the scripts at the end of the page (_wp_footer_scripts, priority 10).
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'combine' ), 1 );
	}

	/**
	 * @return bool
	 */
	private static function enabled() {
		if ( class_exists( 'AIMP_Design' ) && ( AIMP_Design::opt( 'site-tech', 'sp_nobundle' ) || AIMP_Design::is_preview() ) ) {
			return false;
		}
		return (bool) apply_filters( 'aimp_combine_scripts', ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) );
	}

	public static function combine() {
		global $wp_scripts;
		if ( ! self::enabled() || ! $wp_scripts instanceof WP_Scripts || ! $wp_scripts->queue ) {
			return;
		}
		// What is still to be printed, in order (the scripts in <head> are done already).
		$deps         = clone $wp_scripts;
		$deps->to_do  = array();
		$deps->all_deps( $wp_scripts->queue );
		$todo         = $deps->to_do;
		$ours         = array();
		$external     = array();
		$base_url     = AIMP_PLUGIN_URL;
		foreach ( $todo as $handle ) {
			$item = isset( $wp_scripts->registered[ $handle ] ) ? $wp_scripts->registered[ $handle ] : null;
			if ( ! $item || ! is_string( $item->src ) || '' === $item->src ) {
				continue;
			}
			$src = 0 === strpos( $item->src, '//' ) ? set_url_scheme( $item->src ) : $item->src;
			if ( 0 === strpos( $handle, 'aimp-' ) && 0 === strpos( $src, $base_url ) ) {
				$ours[ $handle ] = $item;
			} else {
				$external[ $handle ] = $item;
			}
		}
		if ( count( $ours ) < 2 ) {
			return;
		}
		// Another plugin's script that needs one of ours: leave everything as it is.
		foreach ( $external as $item ) {
			if ( array_intersect( (array) $item->deps, array_keys( $ours ) ) ) {
				return;
			}
		}
		$sources = array();
		$need    = array();
		$data    = '';
		$before  = array();
		$key     = '';
		foreach ( $ours as $handle => $item ) {
			$path = AIMP_PLUGIN_DIR . ltrim( (string) substr( strtok( $item->src, '?' ), strlen( $base_url ) ), '/' );
			if ( ! is_readable( $path ) || '.js' !== substr( $path, -3 ) ) {
				return;
			}
			$sources[ $handle ] = $path;
			$key               .= $handle . ':' . $item->ver . ':' . filemtime( $path ) . ';';
			$need               = array_merge( $need, array_diff( (array) $item->deps, array_keys( $ours ) ) );
			if ( ! empty( $item->extra['data'] ) ) {
				$data .= $item->extra['data'] . "\n";
			}
			if ( ! empty( $item->extra['before'] ) ) {
				$before = array_merge( $before, array_filter( (array) $item->extra['before'] ) );
			}
			// Inline code "after" one of ours already runs before it today (the plugin's files are deferred, the
			// inline code is not), so it stays inline before the combined file. It must not need the file's code.
			if ( ! empty( $item->extra['after'] ) ) {
				$before = array_merge( $before, array_filter( (array) $item->extra['after'] ) );
			}
		}
		$url = self::file( md5( $key ), $sources );
		if ( '' === $url ) {
			return;
		}
		wp_register_script( self::HANDLE, $url, array_values( array_unique( $need ) ), null, true );
		wp_script_add_data( self::HANDLE, 'strategy', 'defer' );
		if ( '' !== $data ) {
			$wp_scripts->add_data( self::HANDLE, 'data', $data );
		}
		foreach ( $before as $code ) {
			wp_add_inline_script( self::HANDLE, $code, 'before' );
		}
		// The separate files count as printed; the combined one takes their place.
		foreach ( array_keys( $ours ) as $handle ) {
			wp_dequeue_script( $handle );
			$wp_scripts->done[] = $handle;
		}
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * The combined file (written once for this set of scripts).
	 *
	 * @param string $hash    Set of scripts and their versions.
	 * @param array  $sources handle => path.
	 * @return string Address, '' when it cannot be written.
	 */
	private static function file( $hash, $sources ) {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$dir  = trailingslashit( $uploads['basedir'] ) . 'aimp-cache';
		$name = 'scripts-' . substr( $hash, 0, 16 ) . '.js';
		$file = $dir . '/' . $name;
		if ( ! file_exists( $file ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return '';
			}
			$js = '';
			foreach ( $sources as $handle => $path ) {
				$code = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a plugin file.
				if ( false === $code ) {
					return '';
				}
				$js .= '/* ' . $handle . " */\n" . rtrim( $code ) . "\n;\n";
			}
			// Written next to it first, then put in place, so a visitor never gets half a file.
			$tmp = $file . '.' . wp_generate_password( 6, false ) . '.tmp';
			if ( false === file_put_contents( $tmp, $js ) || ! rename( $tmp, $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- a cache file.
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				return '';
			}
			self::clean( $dir, $name );
		} elseif ( filemtime( $file ) < time() - DAY_IN_SECONDS ) {
			// Still in use: marked, so the clean-up keeps it (cached pages point to it for days).
			touch( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a cache file.
		}
		return trailingslashit( $uploads['baseurl'] ) . 'aimp-cache/' . $name;
	}

	/**
	 * Combined files nothing has used for a month are removed (cached pages are kept for a week at most).
	 *
	 * @param string $dir  Folder.
	 * @param string $keep The current file.
	 */
	private static function clean( $dir, $keep ) {
		foreach ( (array) glob( $dir . '/scripts-*.js' ) as $old ) {
			if ( basename( $old ) !== $keep && filemtime( $old ) < time() - MONTH_IN_SECONDS ) {
				wp_delete_file( $old );
			}
		}
	}
}
