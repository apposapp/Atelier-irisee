<?php
/**
 * "Empty line" button for the classic text editor (Visual and Text tab): adds an empty line that
 * WordPress keeps, so texts can have extra space without editing HTML.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Editor {

	/** What the button inserts: an empty paragraph WordPress does not strip (it has a class). */
	const SPACE_HTML = '<p class="aimp-space">&nbsp;</p>';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		// The full editor and the small (teeny) editors each have their own filter.
		add_filter( 'tiny_mce_before_init', array( __CLASS__, 'tinymce' ), 20, 2 );
		add_filter( 'teeny_mce_before_init', array( __CLASS__, 'tinymce' ), 20, 2 );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'quicktags' ), 100 );
	}

	private static function label() {
		return __( 'Empty line', 'atelier-irisee-master-plugin' );
	}

	/**
	 * Visual tab, for the full editor and the small (teeny) editors. WordPress only reads
	 * mce_external_plugins for the full editor, so the plugin is added to the settings directly.
	 *
	 * @param array  $init      TinyMCE settings.
	 * @param string $editor_id Editor ID.
	 * @return array
	 */
	public static function tinymce( $init, $editor_id ) {
		$external = array();
		if ( ! empty( $init['external_plugins'] ) ) {
			$decoded  = json_decode( (string) $init['external_plugins'], true );
			$external = is_array( $decoded ) ? $decoded : array();
		}
		$external['aimpspace']    = AIMP_PLUGIN_URL . 'assets/js/editor-space.js?ver=' . rawurlencode( AIMP_VERSION );
		$init['external_plugins'] = wp_json_encode( $external );
		$init['aimpspace_label']  = self::label();
		$toolbar                  = isset( $init['toolbar1'] ) ? (string) $init['toolbar1'] : '';
		if ( false === strpos( $toolbar, 'aimpspace' ) ) {
			$init['toolbar1'] = '' === $toolbar ? 'aimpspace' : $toolbar . ',aimpspace';
		}
		return $init;
	}

	/**
	 * Text tab.
	 */
	public static function quicktags() {
		if ( ! wp_script_is( 'quicktags' ) ) {
			return;
		}
		printf(
			'<script>if ( window.QTags ) { QTags.addButton( "aimp_space", %1$s, %2$s, "", "", %1$s, 200 ); }</script>',
			wp_json_encode( self::label() ),
			wp_json_encode( "\n" . self::SPACE_HTML . "\n" )
		);
	}
}
