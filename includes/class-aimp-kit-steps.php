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
			// A fabric roll that unrolls, with two swatches.
			'fabric'  => '<g class="ks-cloth"><path d="M34 22h66v40H34"/><path d="M48 22v40M62 22v40M76 22v40M90 22v40" class="ks-weave"/></g>' .
				'<ellipse class="ks-roll" cx="30" cy="42" rx="9" ry="22"/><ellipse class="ks-roll" cx="30" cy="42" rx="3" ry="8"/>' .
				'<g class="ks-swatch ks-swatch--1"><rect x="62" y="68" width="18" height="14" rx="2"/></g><g class="ks-swatch ks-swatch--2"><rect x="84" y="68" width="18" height="14" rx="2"/></g>',
			// Buttons dropping in and a zip that opens and closes.
			'notions' => '<g class="ks-button ks-button--1"><circle cx="26" cy="30" r="11"/><circle cx="23" cy="27" r="1.4"/><circle cx="29" cy="27" r="1.4"/><circle cx="23" cy="33" r="1.4"/><circle cx="29" cy="33" r="1.4"/></g>' .
				'<g class="ks-button ks-button--2"><circle cx="54" cy="22" r="8"/><circle cx="52" cy="22" r="1.2"/><circle cx="56" cy="22" r="1.2"/></g>' .
				'<g class="ks-button ks-button--3"><circle cx="42" cy="58" r="9"/><circle cx="39.5" cy="55.5" r="1.3"/><circle cx="44.5" cy="55.5" r="1.3"/><circle cx="39.5" cy="60.5" r="1.3"/><circle cx="44.5" cy="60.5" r="1.3"/></g>' .
				'<path d="M86 10v70"/><path class="ks-teeth" d="M82 14h4M86 20h4M82 26h4M86 32h4M82 38h4M86 44h4M82 50h4M86 56h4M82 62h4M86 68h4M82 74h4"/>' .
				'<g class="ks-slider"><rect x="80" y="38" width="12" height="10" rx="2"/><path d="M86 48v8"/></g>',
			// A box whose lid closes, a bow and a sparkle.
			'kit'     => '<path class="ks-box" d="M26 42h68v40H26z"/><path class="ks-box" d="M60 42v40"/>' .
				'<g class="ks-lid"><path d="M22 32h76v10H22z"/></g>' .
				'<g class="ks-bow"><path d="M60 32c-6-10-18-10-16-2s12 4 16 2zm0 0c6-10 18-10 16-2s-12 4-16 2z"/></g>' .
				'<path class="ks-spark" d="M100 14l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/><path class="ks-spark ks-spark--2" d="M16 18l1.3 3.2 3.2 1.3-3.2 1.3L16 27l-1.3-3.2-3.2-1.3 3.2-1.3z"/>',
		);
		return '<svg class="aimp-kit-steps-drawing ks-' . esc_attr( $key ) . '" viewBox="0 0 120 90" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $parts[ $key ] . '</svg>';
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
		$button = '' !== $atts['button'] ? $atts['button'] : __( 'Start your kit', 'atelier-irisee-master-plugin' );

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
						<svg class="aimp-kit-steps-needle" viewBox="-40 -20 80 40" aria-hidden="true" focusable="false"><g transform="rotate(-30)"><?php echo self::needle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></g></svg>
						<a class="aimp-kit-steps-button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
