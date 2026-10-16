<?php
/**
 * [atelier_irisee_kit_steps]: a playful bar for the home page that shows how the customizer works, in four
 * animated steps on a gold thread: choose a pattern, pick a fabric, add haberdashery, your sewing project kit.
 *
 * Attributes (all optional): title, button (button text), url (default: the configurator page).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Kit_Steps {

	const TAG = 'atelier_irisee_kit_steps';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'aimp-kit-steps', AIMP_PLUGIN_URL . 'assets/css/kit-steps.css', array(), AIMP_VERSION );
		wp_register_script( 'aimp-kit-steps', AIMP_PLUGIN_URL . 'assets/js/kit-steps.js', array(), AIMP_VERSION, true );
	}

	/**
	 * The four steps: [ key, title, text ].
	 *
	 * @return array
	 */
	private static function steps() {
		return array(
			array( 'pattern', __( 'Choose a pattern', 'atelier-irisee-master-plugin' ), __( 'Pick your pattern and your size.', 'atelier-irisee-master-plugin' ) ),
			array( 'fabric', __( 'Pick your fabric', 'atelier-irisee-master-plugin' ), __( 'Only fabrics that suit the pattern.', 'atelier-irisee-master-plugin' ) ),
			array( 'notions', __( 'Add haberdashery', 'atelier-irisee-master-plugin' ), __( 'Buttons, zips and more that fit.', 'atelier-irisee-master-plugin' ) ),
			array( 'kit', __( 'Your sewing kit', 'atelier-irisee-master-plugin' ), __( 'Everything together, ready to sew.', 'atelier-irisee-master-plugin' ) ),
		);
	}

	/**
	 * Line drawings of the steps (viewBox 0 0 120 90), with parts that move (kit-steps.css).
	 *
	 * @param string $key Step.
	 * @return string SVG.
	 */
	private static function drawing( $key ) {
		$parts = array(
			// A pattern sheet with a folded corner; a dress outline that draws itself as a cutting line, and scissors.
			'pattern' => '<path class="ks-paper" d="M28 10h48l16 16v58H28z"/><path class="ks-paper" d="M76 10v16h16"/>' .
				'<path class="ks-cut" d="M52 26l-6 6 4 10-10 34h40L70 42l4-10-6-6c-2 4-5 6-8 6s-6-2-8-6z"/>' .
				'<g class="ks-scissors"><circle cx="98" cy="72" r="4"/><circle cx="98" cy="84" r="4"/><path d="M101 74l14-10M101 82l14 10"/></g>',
			// A fabric swatch with pinked edges and a flower print, waving like cloth; a stack of folded fabrics and
			// a tape measure in front of it.
			'fabric'  => '<g class="ks-swatch-wave"><path class="ks-pinked" d="' . self::pinked( 40, 6, 64, 44, 4 ) . '"/>' .
				'<g class="ks-print">' . self::flower( 54, 18 ) . self::flower( 86, 24 ) . self::flower( 68, 38 ) .
				'<circle cx="72" cy="16" r="1"/><circle cx="58" cy="32" r="1"/><circle cx="92" cy="40" r="1"/><circle cx="80" cy="34" r="1"/><circle cx="48" cy="42" r="1"/></g></g>' .
				'<g class="ks-folds"><path d="M8 60h40a4 4 0 0 1 0 8H8z"/><path d="M12 68h40a4 4 0 0 1 0 8H12z"/><path d="M8 76h40a4 4 0 0 1 0 8H8z"/>' .
				'<path class="ks-fold-line" d="M14 64h22M18 72h22M14 80h22"/></g>' .
				'<g class="ks-tape"><path d="M58 84l46-10"/><path d="M58 78l44-9.6"/><path class="ks-tape-ticks" d="M64 82.7l-1.2-5.5M71 81.2l-.8-4M78 79.7l-1.2-5.5M85 78.2l-.8-4M92 76.7l-1.2-5.5"/>' .
				'<circle cx="108" cy="70" r="6"/><circle cx="108" cy="70" r="2"/></g>',
			// Buttons dropping in and a zip that opens and closes.
			'notions' => '<g class="ks-button ks-button--1"><circle cx="26" cy="30" r="11"/><circle cx="23" cy="27" r="1.4"/><circle cx="29" cy="27" r="1.4"/><circle cx="23" cy="33" r="1.4"/><circle cx="29" cy="33" r="1.4"/></g>' .
				'<g class="ks-button ks-button--2"><circle cx="54" cy="22" r="8"/><circle cx="52" cy="22" r="1.2"/><circle cx="56" cy="22" r="1.2"/></g>' .
				'<g class="ks-button ks-button--3"><circle cx="42" cy="58" r="9"/><circle cx="39.5" cy="55.5" r="1.3"/><circle cx="44.5" cy="55.5" r="1.3"/><circle cx="39.5" cy="60.5" r="1.3"/><circle cx="44.5" cy="60.5" r="1.3"/></g>' .
				'<path d="M86 10v70"/><path class="ks-teeth" d="M82 14h4M86 20h4M82 26h4M86 32h4M82 38h4M86 44h4M82 50h4M86 56h4M82 62h4M86 68h4M82 74h4"/>' .
				// The slider with the usual long pull tab, swinging as it moves.
				'<g class="ks-slider"><path d="M80 37h12l-2 9h-8z"/><g class="ks-pull"><path d="M84.2 46h3.6v15.5a1.8 1.8 0 0 1-3.6 0z"/><circle cx="86" cy="58" r="1"/></g></g>',
			// A box whose lid closes, a bow and a sparkle.
			'kit'     => '<path class="ks-box" d="M26 42h68v40H26z"/><path class="ks-box" d="M60 42v40"/>' .
				'<g class="ks-lid"><path d="M22 32h76v10H22z"/></g>' .
				'<g class="ks-bow"><path d="M60 32c-6-10-18-10-16-2s12 4 16 2zm0 0c6-10 18-10 16-2s-12 4-16 2z"/></g>' .
				'<path class="ks-spark" d="M100 14l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/><path class="ks-spark ks-spark--2" d="M16 18l1.3 3.2 3.2 1.3-3.2 1.3L16 27l-1.3-3.2-3.2-1.3 3.2-1.3z"/>',
		);
		return '<svg class="aimp-kit-steps-drawing ks-' . esc_attr( $key ) . '" viewBox="0 0 120 90" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $parts[ $key ] . '</svg>';
	}

	/**
	 * A rectangle with pinked (zigzag) edges, like a fabric sample.
	 *
	 * @param int $x     Left.
	 * @param int $y     Top.
	 * @param int $w     Width.
	 * @param int $h     Height.
	 * @param int $tooth Tooth size.
	 * @return string Path data.
	 */
	private static function pinked( $x, $y, $w, $h, $tooth ) {
		$points = array();
		$nx     = (int) round( $w / $tooth );
		$ny     = (int) round( $h / $tooth );
		for ( $i = 0; $i <= $nx; $i++ ) { // Top, left to right.
			$points[] = array( $x + $i * $w / $nx, $y + ( $i % 2 ? -$tooth / 2 : 0 ) );
		}
		for ( $i = 1; $i <= $ny; $i++ ) { // Right, down.
			$points[] = array( $x + $w + ( $i % 2 ? $tooth / 2 : 0 ), $y + $i * $h / $ny );
		}
		for ( $i = $nx - 1; $i >= 0; $i-- ) { // Bottom, right to left.
			$points[] = array( $x + $i * $w / $nx, $y + $h + ( $i % 2 ? $tooth / 2 : 0 ) );
		}
		for ( $i = $ny - 1; $i > 0; $i-- ) { // Left, up.
			$points[] = array( $x + ( $i % 2 ? -$tooth / 2 : 0 ), $y + $i * $h / $ny );
		}
		$d = '';
		foreach ( $points as $n => $point ) {
			$d .= ( 0 === $n ? 'M' : 'L' ) . round( $point[0], 1 ) . ' ' . round( $point[1], 1 );
		}
		return $d . 'Z';
	}

	/**
	 * A small five-petal flower for the fabric print.
	 *
	 * @param int $cx Centre x.
	 * @param int $cy Centre y.
	 * @return string SVG.
	 */
	private static function flower( $cx, $cy ) {
		$svg = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$a    = deg2rad( $i * 72 - 90 );
			$svg .= '<circle cx="' . round( $cx + cos( $a ) * 2.6, 1 ) . '" cy="' . round( $cy + sin( $a ) * 2.6, 1 ) . '" r="1.6"/>';
		}
		return $svg . '<circle class="ks-print-heart" cx="' . $cx . '" cy="' . $cy . '" r="0.9"/>';
	}

	/**
	 * A classic sewing needle pointing right (to +x), centred on 0,0: a silver tapered shaft with a sharp point
	 * and an elongated eye at the back.
	 *
	 * @return string SVG group.
	 */
	private static function needle() {
		return '<g class="ks-needle">' .
			'<path class="ks-needle-shaft" d="M-34 -2.4 Q-37 0 -34 2.4 L24 1.1 L36 0 L24 -1.1 Z"/>' .
			'<path class="ks-needle-shine" d="M-30 -0.9 L22 -0.5"/>' .
			'<ellipse class="ks-needle-eye" cx="-28" cy="0" rx="4" ry="0.9"/>' .
			'</g>';
	}

	/**
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'  => '',
				'button' => '',
				'url'    => '',
			),
			$atts,
			self::TAG
		);
		$page = AIMP_Settings::get( 'configurator_page' );
		$url  = '' !== $atts['url'] ? $atts['url'] : ( $page && 'publish' === get_post_status( $page ) ? get_permalink( $page ) : '' );

		if ( ! wp_style_is( 'aimp-kit-steps', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-kit-steps' );
		wp_enqueue_script( 'aimp-kit-steps' );

		$title  = '' !== $atts['title'] ? $atts['title'] : __( 'Your own sewing project kit in 4 steps', 'atelier-irisee-master-plugin' );
		$button = '' !== $atts['button'] ? $atts['button'] : __( 'Start making your project sewing kit', 'atelier-irisee-master-plugin' );

		ob_start();
		?>
		<section class="aimp-kit-steps" data-aimp-kit-steps aria-label="<?php echo esc_attr( $title ); ?>">
			<h2 class="aimp-kit-steps-title"><?php echo esc_html( $title ); ?></h2>
			<div class="aimp-kit-steps-row">
				<?php // The seam behind the steps, and a needle above them that sews it from left to right (kit-steps.js). ?>
				<svg class="aimp-kit-steps-thread" viewBox="0 0 1000 120" preserveAspectRatio="none" aria-hidden="true" focusable="false">
					<path class="ks-seam-guide" d="M0 70 C 90 10, 160 10, 250 60 S 410 115, 500 60 S 660 5, 750 60 S 910 115, 1000 50" />
					<path class="ks-seam-sewn" d="M0 70 C 90 10, 160 10, 250 60 S 410 115, 500 60 S 660 5, 750 60 S 910 115, 1000 50" />
				</svg>
				<svg class="aimp-kit-steps-sewing" aria-hidden="true" focusable="false"><?php echo self::needle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></svg>
				<ol class="aimp-kit-steps-list">
					<?php foreach ( self::steps() as $index => $step ) : ?>
						<li class="aimp-kit-step aimp-kit-step--<?php echo esc_attr( $step[0] ); ?>">
							<span class="aimp-kit-step-seal" aria-hidden="true"><?php echo (int) $index + 1; ?></span>
							<?php echo self::drawing( $step[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
							<h3 class="aimp-kit-step-title"><?php echo esc_html( $step[1] ); ?></h3>
							<p class="aimp-kit-step-text"><?php echo esc_html( $step[2] ); ?></p>
						</li>
					<?php endforeach; ?>
				</ol>
				<?php if ( '' !== $url ) : ?>
					<div class="aimp-kit-steps-cta">
						<a class="aimp-kit-steps-button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
