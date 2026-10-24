<?php
/**
 * [atelier_irisee_hero]: the full-screen opening of the home page. A picture (chosen under Atelier Irisee →
 * Home page) under a gold filter, a cream arch with the logo as a seal, a tagline and buttons to the customizer
 * and the shop, and a gold arrow that slides the page down to the next section (the kit steps bar).
 *
 * Attributes (all optional): title, text, image (attachment ID).
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Hero {

	const TAG = 'atelier_irisee_hero';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ), 20 );
	}

	public static function register_assets() {
		wp_register_style( 'aimp-hero', AIMP_PLUGIN_URL . 'assets/css/hero.css', array(), AIMP_VERSION );
		wp_register_script( 'aimp-hero', AIMP_PLUGIN_URL . 'assets/js/hero.js', array(), AIMP_VERSION, true );
	}

	/**
	 * Where the subject of the picture is: key => [ label, CSS background position ].
	 *
	 * @return array
	 */
	public static function focus_options() {
		return array(
			'right'  => array( __( 'Right', 'atelier-irisee-master-plugin' ), '75% 40%' ),
			'center' => array( __( 'Centre', 'atelier-irisee-master-plugin' ), '50% 40%' ),
			'left'   => array( __( 'Left', 'atelier-irisee-master-plugin' ), '25% 40%' ),
			'top'    => array( __( 'Top', 'atelier-irisee-master-plugin' ), '50% 15%' ),
			'bottom' => array( __( 'Bottom', 'atelier-irisee-master-plugin' ), '50% 85%' ),
		);
	}

	/**
	 * A text from the Home page settings in the visitor's language, or the translated default.
	 *
	 * @param string $key     Setting prefix (hero_title, hero_text).
	 * @param string $default Default text (already translated).
	 * @return string
	 */
	private static function text( $key, $default ) {
		$text = trim( AIMP_Settings::get_text( $key . '_' . AIMP_I18n::current() ) );
		return '' !== $text ? $text : $default;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => '',
				'text'  => '',
				'image' => '',
			),
			$atts,
			self::TAG
		);

		if ( ! wp_style_is( 'aimp-hero', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'aimp-hero' );
		wp_enqueue_script( 'aimp-hero' );

		// Design studio (Hero): other pictures, a video, slides, buttons and where the arrow goes.
		$design   = function ( $key, $default = null ) {
			return class_exists( 'AIMP_Design' ) ? AIMP_Design::opt( 'hero', $key, $default ) : $default;
		};
		$image_id = absint( $atts['image'] ) ? absint( $atts['image'] ) : ( $design( 'h_img' ) ? (int) $design( 'h_img' ) : AIMP_Settings::get( 'hero_image' ) );
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		$phone_id = $design( 'h_pimg' ) ? (int) $design( 'h_pimg' ) : AIMP_Settings::get( 'hero_image_phone' );
		$phone    = $phone_id ? wp_get_attachment_image_url( $phone_id, 'large' ) : '';
		$logo_id  = AIMP_Settings::get( 'hero_logo' );
		$logo     = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : AIMP_Header::logo_url();
		$focus    = self::focus_options();
		$focus    = $focus[ AIMP_Settings::get_text( 'hero_focus', 'right' ) ][1] ?? $focus['right'][1];
		$tint     = min( 90, AIMP_Settings::get( 'hero_tint' ) ) / 100;

		$title = '' !== $atts['title'] ? $atts['title'] : self::text( 'hero_title', __( 'Sew something truly yours', 'atelier-irisee-master-plugin' ) );
		$text  = '' !== $atts['text'] ? $atts['text'] : self::text( 'hero_text', __( 'Pattern, fabric and haberdashery that match, in one kit.', 'atelier-irisee-master-plugin' ) );

		$brand = trim( html_entity_decode( wp_strip_all_tags( get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' ) );
		$brand = '' !== $brand ? $brand : 'Atelier Irisée';

		$kit_page  = AIMP_Settings::get( 'configurator_page' );
		$shop_page = AIMP_Settings::get( 'page_all' );
		$kit_url   = $kit_page && 'publish' === get_post_status( $kit_page ) ? get_permalink( $kit_page ) : '';
		$shop_url  = $shop_page && 'publish' === get_post_status( $shop_page ) ? get_permalink( $shop_page ) : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '' );

		$style = '--aimp-hero-focus:' . $focus . ';--aimp-hero-tint:' . $tint . ';';
		if ( $image ) {
			$style .= '--aimp-hero-img:url("' . esc_url_raw( $image ) . '");';
		}
		if ( $phone ) {
			$style .= '--aimp-hero-img-phone:url("' . esc_url_raw( $phone ) . '");';
		}

		$video_id = (int) $design( 'h_video', 0 );
		$video    = $video_id ? wp_get_attachment_url( $video_id ) : '';
		$slides   = array();
		foreach ( (array) $design( 'h_slides', array() ) as $slide_id ) {
			$slide_url = wp_get_attachment_image_url( (int) $slide_id, 'full' );
			if ( $slide_url ) {
				$slides[] = $slide_url;
			}
		}
		// The first slide is the hero picture itself.
		if ( $slides && $image ) {
			array_unshift( $slides, $image );
		}
		$interval = (int) $design( 'h_interval', 0 );
		// Where the arrow goes (Design studio → Hero arrow).
		$arrow  = class_exists( 'AIMP_Design' ) ? (string) AIMP_Design::opt( 'hero-arrow', 'ha_target', '' ) : '';
		$anchor = class_exists( 'AIMP_Design' ) ? (string) AIMP_Design::opt( 'hero-arrow', 'ha_anchor', '' ) : '';
		// Design studio (Hero arrow): how long the slide down takes (ms).
		$glide  = class_exists( 'AIMP_Design' ) ? (int) AIMP_Design::opt( 'hero-arrow', 'ha_speed', 0 ) : 0;

		ob_start();
		?>
		<section class="aimp-hero<?php echo ( $video || count( $slides ) > 1 ) ? ' has-media' : ''; ?>" data-aimp-hero style="<?php echo esc_attr( $style ); ?>"<?php echo $arrow ? ' data-arrow="' . esc_attr( $arrow ) . '"' : ''; ?><?php echo '' !== $anchor ? ' data-anchor="' . esc_attr( $anchor ) . '"' : ''; ?><?php echo $glide ? ' data-slide="' . esc_attr( (string) $glide ) . '"' : ''; ?><?php echo $interval ? ' data-interval="' . esc_attr( (string) $interval ) . '"' : ''; ?>>
			<?php if ( $video || count( $slides ) > 1 ) : ?>
				<div class="aimp-hero-media" aria-hidden="true">
					<?php if ( $video ) : ?>
						<video class="aimp-hero-video" src="<?php echo esc_url( $video ); ?>" autoplay muted loop playsinline<?php echo $image ? ' poster="' . esc_url( $image ) . '"' : ''; ?>></video>
					<?php else : ?>
						<?php foreach ( $slides as $index => $slide ) : ?>
							<div class="aimp-hero-slide<?php echo 0 === $index ? ' is-current' : ''; ?>" style="background-image:url('<?php echo esc_url( $slide ); ?>')"></div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="aimp-hero-inner">
				<div class="aimp-hero-arch">
					<?php if ( $logo ) : ?>
						<img class="aimp-hero-seal" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<?php endif; ?>
					<span class="aimp-hero-brand"><?php echo esc_html( $brand ); ?></span>
					<h1 class="aimp-hero-title"><?php echo esc_html( $title ); ?></h1>
					<?php if ( '' !== $text ) : ?>
						<p class="aimp-hero-text"><?php echo esc_html( $text ); ?></p>
					<?php endif; ?>
					<?php
					// Design studio (Hero → Buttons): each standard button can go elsewhere or be left out, and a third
					// one can be added (its text in the visitor's language: "NL | FR | EN" or one text).
					$kit_url  = $design( 'hb1_off' ) ? '' : ( $design( 'hb1_url' ) ? $design( 'hb1_url' ) : $kit_url );
					$shop_url = $design( 'hb2_off' ) ? '' : ( $design( 'hb2_url' ) ? $design( 'hb2_url' ) : $shop_url );
					$third    = $design( 'hb3_text' ) && $design( 'hb3_url' ) ? AIMP_Settings::localized( (string) $design( 'hb3_text' ) ) : '';
					?>
					<div class="aimp-hero-buttons">
						<?php if ( $kit_url ) : ?>
							<a class="aimp-hero-button is-primary" href="<?php echo esc_url( $kit_url ); ?>"><?php esc_html_e( 'Make your sewing kit', 'atelier-irisee-master-plugin' ); ?></a>
						<?php endif; ?>
						<?php if ( $shop_url ) : ?>
							<a class="aimp-hero-button is-outline" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Discover the shop', 'atelier-irisee-master-plugin' ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $third ) : ?>
							<a class="aimp-hero-button is-outline" href="<?php echo esc_url( $design( 'hb3_url' ) ); ?>"><?php echo esc_html( $third ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<button class="aimp-hero-arrow" type="button" aria-label="<?php esc_attr_e( 'Scroll down', 'atelier-irisee-master-plugin' ); ?>">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 4v15M5 12l7 7 7-7"/></svg>
			</button>
		</section>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ settings: the "Home page" tab */

	public static function register_settings() {
		add_settings_section(
			'aimp_home',
			__( 'Home page', 'atelier-irisee-master-plugin' ),
			array( __CLASS__, 'settings_intro' ),
			AIMP_Settings::PAGE
		);
		$pictures = array(
			'hero_image'       => __( 'Hero picture', 'atelier-irisee-master-plugin' ),
			'hero_image_phone' => __( 'Phone picture', 'atelier-irisee-master-plugin' ),
			'hero_logo'        => __( 'Logo', 'atelier-irisee-master-plugin' ),
		);
		foreach ( $pictures as $key => $label ) {
			add_settings_field(
				'aimp_' . $key,
				$label,
				array( __CLASS__, 'render_picture_field' ),
				AIMP_Settings::PAGE,
				'aimp_home',
				array( 'key' => $key )
			);
		}
		add_settings_field( 'aimp_hero_focus', __( 'Where is the subject?', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_focus_field' ), AIMP_Settings::PAGE, 'aimp_home', array( 'label_for' => 'aimp_hero_focus' ) );
		add_settings_field( 'aimp_hero_tint', __( 'Gold filter strength', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_tint_field' ), AIMP_Settings::PAGE, 'aimp_home', array( 'label_for' => 'aimp_hero_tint' ) );
		add_settings_field( 'aimp_hero_texts', __( 'Texts', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_text_fields' ), AIMP_Settings::PAGE, 'aimp_home' );
	}

	public static function settings_intro() {
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: 1: hero shortcode, 2: kit steps shortcode */
				__( 'The opening of the home page: place %1$s at the top of the home page (in a full-width section), with %2$s right under it. The arrow slides down to the steps.', 'atelier-irisee-master-plugin' ),
				'<code>[atelier_irisee_hero]</code>',
				'<code>[atelier_irisee_kit_steps]</code>'
			)
		) . '</p>';
	}

	public static function render_picture_field( $args ) {
		AIMP_Settings::render_media_field(
			array(
				'key'  => $args['key'],
				'type' => 'image',
			)
		);
		$notes = array(
			'hero_image'       => __( 'A large landscape picture (at least 1920 px wide). Keep the subject away from the left side: the text sits there.', 'atelier-irisee-master-plugin' ),
			'hero_image_phone' => __( 'Optional: a portrait picture for phones. Without it the hero picture is used.', 'atelier-irisee-master-plugin' ),
			'hero_logo'        => __( 'Optional: shown as a round seal on top of the arch. Without it the logo of the header is used.', 'atelier-irisee-master-plugin' ),
		);
		echo '<p class="description">' . esc_html( $notes[ $args['key'] ] ) . '</p>';
	}

	public static function render_focus_field() {
		$current = AIMP_Settings::get_text( 'hero_focus', 'right' );
		echo '<select id="aimp_hero_focus" name="' . esc_attr( AIMP_Settings::OPTION ) . '[hero_focus]">';
		foreach ( self::focus_options() as $key => $option ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $option[0] ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'The part of the picture that always stays in view, on every screen size.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	public static function render_tint_field() {
		$tint = min( 90, AIMP_Settings::get( 'hero_tint' ) );
		printf(
			'<input type="range" id="aimp_hero_tint" name="%1$s[hero_tint]" min="0" max="90" step="5" value="%2$d" oninput="this.nextElementSibling.textContent=this.value+\'%%\'" style="vertical-align:middle;width:240px"> <strong>%2$d%%</strong>',
			esc_attr( AIMP_Settings::OPTION ),
			(int) $tint
		);
		echo '<p class="description">' . esc_html__( 'How much gold lies over the picture: 0% shows the picture as it is.', 'atelier-irisee-master-plugin' ) . '</p>';
	}

	public static function render_text_fields() {
		echo '<p class="description" style="margin-bottom:8px">' . esc_html__( 'Leave empty for the standard text ("Sew something truly yours"), translated automatically.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th></th><th>' . esc_html__( 'Tagline', 'atelier-irisee-master-plugin' ) . '</th><th>' . esc_html__( 'Text under the tagline', 'atelier-irisee-master-plugin' ) . '</th></tr></thead><tbody>';
		foreach ( AIMP_I18n::languages() as $code => $language ) {
			echo '<tr><th scope="row">' . esc_html( $language['name'] ) . '</th>';
			foreach ( array( 'hero_title', 'hero_text' ) as $key ) {
				printf(
					'<td><input type="text" class="widefat" name="%1$s[%2$s]" value="%3$s"></td>',
					esc_attr( AIMP_Settings::OPTION ),
					esc_attr( $key . '_' . $code ),
					esc_attr( AIMP_Settings::get_text( $key . '_' . $code ) )
				);
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The Home page settings from the submitted form (called from AIMP_Settings::sanitize()).
	 *
	 * @param array $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean = array();
		foreach ( array( 'hero_image', 'hero_image_phone', 'hero_logo' ) as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}
		$clean['hero_tint']  = isset( $input['hero_tint'] ) ? min( 90, absint( $input['hero_tint'] ) ) : 65;
		$focus               = isset( $input['hero_focus'] ) ? sanitize_key( $input['hero_focus'] ) : 'right';
		$clean['hero_focus'] = isset( self::focus_options()[ $focus ] ) ? $focus : 'right';
		foreach ( array_keys( AIMP_I18n::languages() ) as $code ) {
			foreach ( array( 'hero_title', 'hero_text' ) as $key ) {
				$clean[ $key . '_' . $code ] = isset( $input[ $key . '_' . $code ] ) ? sanitize_text_field( wp_unslash( $input[ $key . '_' . $code ] ) ) : '';
			}
		}
		return $clean;
	}
}
