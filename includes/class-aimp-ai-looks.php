<?php
/**
 * AI pictures ("looks"): a pattern design sewn from a fabric, worn by the reference person, made by an image AI
 * (Google Gemini, OpenAI or Leonardo AI) from three kinds of pictures: the reference person, the design's picture and
 * the fabric's picture.
 *
 * Settings → Atelier Irisee → AI pictures: the service and its API key (kept on the server, never sent to the
 * browser), the reference photos and the prompt; "Generate one combination" to try it out, "Generate missing
 * pictures" for every design × fitting fabric, and a review grid. Only approved pictures are used: in the
 * customizer they come first in the fabric's pictures for that design.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_AI_Looks {

	/** Settings: service, keys, models, people, prompt, monthly_max. */
	const OPTION = 'aimp_ai';

	/** The pictures: "pattern|design|fabric" => [ att, status, job, time, error ]. */
	const LOOKS = 'aimp_ai_looks';

	/** Pictures made per month: [ month, n ]. */
	const COUNT = 'aimp_ai_count';

	/** Attachment meta: the combination a picture was made for. */
	const META = '_aimp_ai_look';

	/** Attachment meta: Leonardo's id of an uploaded reference picture, [ id, time ]. */
	const META_LEONARDO = '_aimp_leonardo_init';

	const NONCE = 'aimp_ai';

	const MAX_PEOPLE = 4;

	const MAX_FACES = 3;

	/** Pictures of a garment per design (ticked + other). */
	const MAX_PATTERN_PICS = 4;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		foreach ( array( 'fabrics', 'generate', 'poll', 'missing', 'review', 'list' ) as $action ) {
			add_action( 'wp_ajax_aimp_ai_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
	}

	/* ------------------------------------------------------------------ settings */

	/**
	 * The services: key => [ label, default model ].
	 *
	 * @return array
	 */
	public static function services() {
		return array(
			'leonardo' => array( 'Leonardo AI', 'gemini-2.5-flash-image' ),
			'gemini'   => array( 'Google Gemini', 'gemini-2.5-flash-image' ),
			'openai'   => array( 'OpenAI', 'gpt-image-1' ),
		);
	}

	/** The standard prompt of 3.16 (saved with the settings): read as today's standard prompt. */
	const OLD_PROMPT = 'Create a realistic fashion photo of the person from the reference photos wearing the garment from the sewing pattern picture, sewn from the fabric. Keep the person\'s face, hair and body the same. Keep the garment\'s cut, length, neckline and details as in the pattern picture. Use the fabric\'s colour, texture and print at a realistic scale. Full body, natural pose, soft daylight studio light, plain light background, portrait format. No text, no logos.';

	/** The standard prompt of 3.17: read as today's standard prompt. */
	const OLD_PROMPT_2 = 'Create a realistic fashion photo of the reference person wearing the garment. Keep the person\'s face, hair and body the same. Full body, natural pose, soft daylight studio light, plain light background, portrait format. No text, no logos.';

	/**
	 * What the picture should look like (editable). Which picture is what, that the fabric must be used and that only
	 * the clothes of the model photo change, is always added by prompt().
	 */
	public static function default_prompt() {
		return 'Photorealistic, natural fit and drape of the fabric, realistic folds and shadows. No text, no logos.';
	}

	/**
	 * A setting.
	 *
	 * @param string $key service, model_{service}, model_step2_{service}, people, faces, prompt, monthly_max, two_steps,
	 *                    pattern_pics, pattern_extra, design_model.
	 * @return mixed
	 */
	public static function opt( $key ) {
		$options  = (array) get_option( self::OPTION, array() );
		$services = self::services();
		$defaults = array(
			'service'       => 'leonardo',
			'people'        => array(),
			'faces'         => array(),
			'prompt'        => self::default_prompt(),
			'monthly_max'   => 100,
			'two_steps'     => 1,
			// Per design: the pattern's pictures for the AI, other pictures of the garment, the model photo to dress.
			'pattern_pics'  => array(),
			'pattern_extra' => array(),
			'design_model'  => array(),
		);
		foreach ( $services as $service => $info ) {
			$defaults[ 'model_' . $service ] = $info[1];
		}
		$value = isset( $options[ $key ] ) ? $options[ $key ] : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
		if ( 0 === strpos( $key, 'model_' ) && '' === trim( (string) $value ) ) {
			$value = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		}
		if ( 'prompt' === $key && in_array( $value, array( self::OLD_PROMPT, self::OLD_PROMPT_2 ), true ) ) {
			$value = self::default_prompt();
		}
		return $value;
	}

	/**
	 * Generations per picture: 2 with "Two steps".
	 *
	 * @return int
	 */
	public static function steps() {
		return self::opt( 'two_steps' ) ? 2 : 1;
	}

	/**
	 * The pictures of a pattern design the AI can get: the design's pictures, then the pattern's main and gallery
	 * pictures.
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $slug       Design or ''.
	 * @return int[]
	 */
	public static function pattern_images( $pattern_id, $slug ) {
		$pattern = wc_get_product( absint( $pattern_id ) );
		if ( ! $pattern ) {
			return array();
		}
		$ids = array();
		if ( '' !== (string) $slug ) {
			$data = AIMP_Catalog::design_data( $pattern->get_id() );
			if ( ! empty( $data[ $slug ]['image_ids'] ) ) {
				$ids = $data[ $slug ]['image_ids'];
			}
		}
		$ids = array_merge( $ids, array( (int) $pattern->get_image_id() ), $pattern->get_gallery_image_ids() );
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * The API key of a service ('' when none is saved).
	 *
	 * @param string $service Service.
	 * @return string
	 */
	private static function api_key( $service ) {
		$options = (array) get_option( self::OPTION, array() );
		return isset( $options['keys'][ $service ] ) ? (string) $options['keys'][ $service ] : '';
	}

	public static function register_settings() {
		register_setting(
			'aimp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
		add_settings_section( 'aimp_ai_looks', __( 'AI pictures', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_section' ), AIMP_Settings::PAGE );
	}

	/**
	 * @param array $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		// Already clean (WordPress can run this twice when the option is first added).
		if ( isset( $input['keys'] ) && is_array( $input['keys'] ) && ! isset( $input['key_new'] ) ) {
			return $input;
		}
		$old      = (array) get_option( self::OPTION, array() );
		$services = self::services();
		$clean    = array(
			'service'      => isset( $input['service'], $services[ $input['service'] ] ) ? $input['service'] : 'leonardo',
			'keys'         => isset( $old['keys'] ) && is_array( $old['keys'] ) ? $old['keys'] : array(),
			'prompt'       => isset( $input['prompt'] ) ? sanitize_textarea_field( wp_unslash( $input['prompt'] ) ) : self::default_prompt(),
			'monthly_max'  => isset( $input['monthly_max'] ) ? min( 5000, absint( $input['monthly_max'] ) ) : 100,
			'people'       => array(),
			'two_steps'    => empty( $input['two_steps'] ) ? 0 : 1,
			'pattern_pics' => array(),
		);
		// Per design: the pictures of the pattern the AI gets (only pictures of that pattern), other pictures of the
		// garment from the media library, and the model photo to dress.
		$clean['pattern_extra'] = array();
		$clean['design_model']  = array();
		foreach ( array( 'pattern_pics', 'pattern_extra', 'design_model' ) as $field ) {
			foreach ( isset( $input[ $field ] ) && is_array( $input[ $field ] ) ? $input[ $field ] : array() as $design => $ids ) {
				$parts = explode( '|', sanitize_text_field( (string) $design ) );
				$id    = absint( $parts[0] );
				if ( ! $id ) {
					continue;
				}
				$design = $id . '|' . ( isset( $parts[1] ) ? sanitize_title( $parts[1] ) : '' );
				$ids    = array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : explode( ',', (string) $ids ) ) ) ) );
				if ( 'pattern_pics' === $field ) {
					$ids = array_values( array_intersect( $ids, self::pattern_images( $id, isset( $parts[1] ) ? sanitize_title( $parts[1] ) : '' ) ) );
				}
				$ids = array_slice( $ids, 0, 'design_model' === $field ? 1 : self::MAX_PATTERN_PICS );
				if ( $ids ) {
					$clean[ $field ][ $design ] = 'design_model' === $field ? $ids[0] : $ids;
				}
			}
		}
		if ( '' === trim( $clean['prompt'] ) ) {
			$clean['prompt'] = self::default_prompt();
		}
		foreach ( array_keys( $services ) as $service ) {
			$clean[ 'model_' . $service ] = isset( $input[ 'model_' . $service ] ) ? sanitize_text_field( wp_unslash( $input[ 'model_' . $service ] ) ) : '';
			$clean[ 'model_step2_' . $service ] = isset( $input[ 'model_step2_' . $service ] ) ? sanitize_text_field( wp_unslash( $input[ 'model_step2_' . $service ] ) ) : '';
			// An empty key field keeps the saved key; "Remove" deletes it.
			$new = isset( $input['key_new'][ $service ] ) ? trim( sanitize_text_field( wp_unslash( $input['key_new'][ $service ] ) ) ) : '';
			if ( '' !== $new ) {
				$clean['keys'][ $service ] = $new;
			} elseif ( ! empty( $input['key_remove'][ $service ] ) ) {
				unset( $clean['keys'][ $service ] );
			}
		}
		$people = isset( $input['people'] ) ? array_filter( array_map( 'absint', explode( ',', (string) $input['people'] ) ) ) : array();
		$clean['people'] = array_slice( array_values( array_unique( $people ) ), 0, self::MAX_PEOPLE );
		$faces           = isset( $input['faces'] ) ? array_filter( array_map( 'absint', explode( ',', (string) $input['faces'] ) ) ) : array();
		$clean['faces']  = array_slice( array_values( array_unique( $faces ) ), 0, self::MAX_FACES );
		return $clean;
	}

	/**
	 * A picker for several photos from the media library (admin-settings.js, type "images").
	 *
	 * @param string $input Field name, e.g. aimp_ai[faces].
	 * @param int[]  $ids   Chosen photos.
	 */
	private static function render_photos( $input, $ids ) {
		?>
		<div class="aimp-media-field" data-type="images">
			<input type="hidden" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( implode( ',', array_map( 'absint', $ids ) ) ); ?>">
			<span class="aimp-media-list">
				<?php foreach ( $ids as $id ) : ?>
					<img src="<?php echo esc_url( (string) wp_get_attachment_image_url( $id, 'thumbnail' ) ); ?>" alt="" style="height:36px;width:auto;margin-right:6px;vertical-align:middle;">
				<?php endforeach; ?>
			</span>
			<button type="button" class="button aimp-media-choose"><?php esc_html_e( 'Choose pictures', 'atelier-irisee-master-plugin' ); ?></button>
			<button type="button" class="button-link aimp-media-remove"<?php echo $ids ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'atelier-irisee-master-plugin' ); ?></button>
		</div>
		<?php
	}

	public static function render_section() {
		$name     = self::OPTION;
		$service  = self::opt( 'service' );
		$people   = (array) self::opt( 'people' );
		$count    = self::month_count();
		?>
		<p><?php esc_html_e( 'An image AI makes a picture of a pattern design sewn from a fabric, worn by your reference person. Pictures are only made when you ask for it here, and only the pictures you approve are shown: in the customizer, first among the fabric\'s pictures for that design.', 'atelier-irisee-master-plugin' ); ?></p>
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row"><label for="aimp_ai_service"><?php esc_html_e( 'Service', 'atelier-irisee-master-plugin' ); ?></label></th>
				<td>
					<select id="aimp_ai_service" name="<?php echo esc_attr( $name ); ?>[service]">
						<?php foreach ( self::services() as $key => $info ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $service, $key ); ?>><?php echo esc_html( $info[0] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Leonardo AI: the API uses API credits of an API plan (Leonardo → API Access), not the tokens of the app. Each service charges per picture.', 'atelier-irisee-master-plugin' ); ?></p>
				</td>
			</tr>
			<?php foreach ( self::services() as $key => $info ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $info[0] ); ?></th>
					<td>
						<p>
							<label><?php esc_html_e( 'API key', 'atelier-irisee-master-plugin' ); ?><br>
							<input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[key_new][<?php echo esc_attr( $key ); ?>]" value="" placeholder="<?php echo esc_attr( '' !== self::api_key( $key ) ? __( 'Saved – type a new key to replace it', 'atelier-irisee-master-plugin' ) : __( 'Not set', 'atelier-irisee-master-plugin' ) ); ?>"></label>
							<?php if ( '' !== self::api_key( $key ) ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[key_remove][<?php echo esc_attr( $key ); ?>]" value="1"> <?php esc_html_e( 'Remove', 'atelier-irisee-master-plugin' ); ?></label>
							<?php endif; ?>
						</p>
						<p>
							<label><?php esc_html_e( 'Model', 'atelier-irisee-master-plugin' ); ?><br>
							<input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[model_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( self::opt( 'model_' . $key ) ); ?>" placeholder="<?php echo esc_attr( $info[1] ); ?>"></label>
						</p>
						<p>
							<label><?php esc_html_e( 'Model for step 2 (the person)', 'atelier-irisee-master-plugin' ); ?><br>
							<input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[model_step2_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) self::opt( 'model_step2_' . $key ) ); ?>" placeholder="<?php esc_attr_e( 'The same model', 'atelier-irisee-master-plugin' ); ?>"></label>
						</p>
						<?php if ( 'gemini' === $key ) : ?>
							<p class="description"><?php esc_html_e( 'For the most faithful person: gemini-3-pro-image-preview (Nano Banana Pro) as the model for step 2; it costs more per picture.', 'atelier-irisee-master-plugin' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'General model photos to dress (full body)', 'atelier-irisee-master-plugin' ); ?></th>
				<td>
					<?php self::render_photos( $name . '[people]', $people ); ?>
					<p class="description">
						<?php
						/* translators: %d: maximum number of photos */
						echo esc_html( sprintf( __( 'Up to %d photos. The AI dresses her in one of these photos (her face, hair, body and pose stay exactly as they are; only the clothes change), so: full body, front-facing, plain background, sharp (at least 1024 px), in simple fitted clothes such as a plain top and leggings. Several poses give variety. Only use photos of someone who agreed to this.', 'atelier-irisee-master-plugin' ), self::MAX_PEOPLE ) );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Face close-ups (for likeness)', 'atelier-irisee-master-plugin' ); ?></th>
				<td>
					<?php self::render_photos( $name . '[faces]', (array) self::opt( 'faces' ) ); ?>
					<p class="description">
						<?php
						/* translators: %d: maximum number of photos */
						echo esc_html( sprintf( __( 'Up to %d sharp close-ups of her face (from the front and slightly from the side), so the AI keeps her face exactly the same.', 'atelier-irisee-master-plugin' ), self::MAX_FACES ) );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aimp_ai_prompt"><?php esc_html_e( 'Prompt', 'atelier-irisee-master-plugin' ); ?></label></th>
				<td>
					<textarea id="aimp_ai_prompt" class="large-text" rows="6" name="<?php echo esc_attr( $name ); ?>[prompt]"><?php echo esc_textarea( self::opt( 'prompt' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'The AI is told which picture is the person, the pattern and the fabric; this text says what the picture should look like. Leave it empty for the standard text.', 'atelier-irisee-master-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Two steps', 'atelier-irisee-master-plugin' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[two_steps]" value="1"<?php checked( 1, (int) self::opt( 'two_steps' ) ); ?>> <?php esc_html_e( 'First the garment in the fabric, then on the person (the fabric comes out much better; each picture costs 2 generations)', 'atelier-irisee-master-plugin' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Per pattern design', 'atelier-irisee-master-plugin' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'Per design: the model photo she is dressed in, and the pictures of the pattern the AI gets to see what the garment is (front, back, technical drawing, a photo in a plain fabric). The AI copies the fabric it sees, so avoid photos in a busy print. Without its own model photo, one of the general model photos is used.', 'atelier-irisee-master-plugin' ); ?></p>
					<?php
					$models = (array) self::opt( 'design_model' );
					$extra  = (array) self::opt( 'pattern_extra' );
					?>
					<?php foreach ( self::designs() as $design_key => $design ) : ?>
						<?php
						$parts = explode( '|', $design_key );
						$pics  = self::pattern_images( (int) $parts[0], $parts[1] );
						$model = isset( $models[ $design_key ] ) ? (int) $models[ $design_key ] : 0;
						$more  = isset( $extra[ $design_key ] ) ? array_map( 'absint', (array) $extra[ $design_key ] ) : array();
						?>
						<details class="aimp-ai-design-box">
							<summary>
								<strong><?php echo esc_html( $design['name'] ); ?></strong>
								<span class="aimp-ai-design-state">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: number of pattern pictures, 2: "own model photo" or "general model photos" */
											__( '%1$d pattern pictures · %2$s', 'atelier-irisee-master-plugin' ),
											count( $design['images'] ),
											$model ? __( 'own model photo', 'atelier-irisee-master-plugin' ) : __( 'general model photos', 'atelier-irisee-master-plugin' )
										)
									);
									?>
								</span>
							</summary>
							<p><strong><?php esc_html_e( 'Model photo to dress', 'atelier-irisee-master-plugin' ); ?></strong></p>
							<div class="aimp-media-field" data-type="image">
								<input type="hidden" name="<?php echo esc_attr( $name . '[design_model][' . $design_key . ']' ); ?>" value="<?php echo esc_attr( (string) $model ); ?>">
								<?php $url = $model ? wp_get_attachment_image_url( $model, 'thumbnail' ) : ''; ?>
								<img class="aimp-media-preview" src="<?php echo esc_url( (string) $url ); ?>" alt="" width="80" height="80" style="object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px;<?php echo $url ? '' : 'display:none;'; ?>">
								<button type="button" class="button aimp-media-choose"><?php esc_html_e( 'Choose a picture', 'atelier-irisee-master-plugin' ); ?></button>
								<button type="button" class="button-link aimp-media-remove"<?php echo $model ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'atelier-irisee-master-plugin' ); ?></button>
							</div>
							<p><strong><?php esc_html_e( 'Pictures of the pattern', 'atelier-irisee-master-plugin' ); ?></strong></p>
							<div class="aimp-ai-pics-row">
								<?php foreach ( $pics as $pic ) : ?>
									<label class="aimp-ai-pic">
										<input type="checkbox" name="<?php echo esc_attr( $name . '[pattern_pics][' . $design_key . '][]' ); ?>" value="<?php echo esc_attr( (string) $pic ); ?>"<?php checked( in_array( $pic, $design['images'], true ) ); ?>>
										<img src="<?php echo esc_url( (string) wp_get_attachment_image_url( $pic, 'thumbnail' ) ); ?>" alt="" loading="lazy">
									</label>
								<?php endforeach; ?>
							</div>
							<p><?php esc_html_e( 'Other pictures of this garment (from the media library):', 'atelier-irisee-master-plugin' ); ?></p>
							<?php self::render_photos( $name . '[pattern_extra][' . $design_key . ']', $more ); ?>
							<p class="description">
								<?php
								/* translators: 1: maximum ticked pictures, 2: maximum other pictures */
								echo esc_html( sprintf( __( 'Up to %1$d ticked and up to %2$d other pictures.', 'atelier-irisee-master-plugin' ), self::MAX_PATTERN_PICS, self::MAX_PATTERN_PICS ) );
								?>
							</p>
						</details>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aimp_ai_max"><?php esc_html_e( 'Maximum per month', 'atelier-irisee-master-plugin' ); ?></label></th>
				<td>
					<input id="aimp_ai_max" type="number" min="0" max="5000" class="small-text" name="<?php echo esc_attr( $name ); ?>[monthly_max]" value="<?php echo esc_attr( (string) self::opt( 'monthly_max' ) ); ?>">
					<p class="description">
						<?php
						/* translators: %d: pictures made this month */
						echo esc_html( sprintf( __( 'Made this month: %d. No more pictures are made once the maximum is reached.', 'atelier-irisee-master-plugin' ), $count ) );
						?>
					</p>
				</td>
			</tr>
		</tbody></table>

		<div class="aimp-ai" data-aimp-ai>
			<p class="description"><?php esc_html_e( 'Save the settings above before generating.', 'atelier-irisee-master-plugin' ); ?></p>
			<h3><?php esc_html_e( 'Generate one combination', 'atelier-irisee-master-plugin' ); ?></h3>
			<p>
				<select class="aimp-ai-design">
					<option value=""><?php esc_html_e( '— Pattern design —', 'atelier-irisee-master-plugin' ); ?></option>
					<?php foreach ( self::designs() as $key => $design ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $design['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="aimp-ai-fabric" disabled>
					<option value=""><?php esc_html_e( '— Fabric —', 'atelier-irisee-master-plugin' ); ?></option>
				</select>
				<button type="button" class="button button-primary aimp-ai-one" disabled><?php esc_html_e( 'Generate', 'atelier-irisee-master-plugin' ); ?></button>
			</p>
			<h3><?php esc_html_e( 'Generate missing pictures', 'atelier-irisee-master-plugin' ); ?></h3>
			<p><?php esc_html_e( 'Every published pattern design with every fabric that fits it, except the combinations that already have a picture (or that you rejected). Keep this page open while it runs.', 'atelier-irisee-master-plugin' ); ?></p>
			<p>
				<button type="button" class="button aimp-ai-count"><?php esc_html_e( 'Count missing pictures', 'atelier-irisee-master-plugin' ); ?></button>
				<button type="button" class="button button-primary aimp-ai-start" hidden><?php esc_html_e( 'Generate missing pictures', 'atelier-irisee-master-plugin' ); ?></button>
				<button type="button" class="button aimp-ai-stop" hidden><?php esc_html_e( 'Stop', 'atelier-irisee-master-plugin' ); ?></button>
			</p>
			<p class="aimp-ai-message" role="status" hidden></p>
			<h3><?php esc_html_e( 'Pictures', 'atelier-irisee-master-plugin' ); ?></h3>
			<div class="aimp-ai-list"><p><?php esc_html_e( 'Loading…', 'atelier-irisee-master-plugin' ); ?></p></div>
		</div>
		<?php
	}

	public static function enqueue( $hook_suffix ) {
		if ( ! AIMP_Settings::is_settings_screen( $hook_suffix ) ) {
			return;
		}
		wp_enqueue_script( 'aimp-admin-ai', AIMP_PLUGIN_URL . 'assets/js/admin-ai.js', array(), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-ai',
			'aimpAi',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => array(
					'fabric'    => __( '— Fabric —', 'atelier-irisee-master-plugin' ),
					'busy'      => __( 'Generating… this takes up to a minute.', 'atelier-irisee-master-plugin' ),
					'done'      => __( 'Done. The picture is waiting for your approval below.', 'atelier-irisee-master-plugin' ),
					/* translators: %d: number of pictures */
					'start'     => __( 'Generate %d pictures', 'atelier-irisee-master-plugin' ),
					/* translators: 1: number of pictures, 2: number of generations */
					'confirm'   => __( 'Sure? This makes %1$d pictures: %2$d generations, each one is charged by the service. Click again to start.', 'atelier-irisee-master-plugin' ),
					'none'      => __( 'Every combination has a picture.', 'atelier-irisee-master-plugin' ),
					/* translators: 1: pictures done, 2: all pictures */
					'progress'  => __( 'Generating: %1$d / %2$d…', 'atelier-irisee-master-plugin' ),
					'stopped'   => __( 'Stopped.', 'atelier-irisee-master-plugin' ),
					'finished'  => __( 'Finished. Check the new pictures below.', 'atelier-irisee-master-plugin' ),
					'failed'    => __( 'Something went wrong:', 'atelier-irisee-master-plugin' ),
					'pending'   => __( 'Waiting for approval', 'atelier-irisee-master-plugin' ),
					'approved'  => __( 'Approved', 'atelier-irisee-master-plugin' ),
					'rejected'  => __( 'Rejected', 'atelier-irisee-master-plugin' ),
					'error'     => __( 'Failed', 'atelier-irisee-master-plugin' ),
					'running'   => __( 'Being made', 'atelier-irisee-master-plugin' ),
					'approve'   => __( 'Approve', 'atelier-irisee-master-plugin' ),
					'reject'    => __( 'Reject', 'atelier-irisee-master-plugin' ),
					'again'     => __( 'Generate again', 'atelier-irisee-master-plugin' ),
					'remove'    => __( 'Remove', 'atelier-irisee-master-plugin' ),
					'empty'     => __( 'No pictures yet.', 'atelier-irisee-master-plugin' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ combinations */

	/**
	 * The pattern designs as the customizer shows them: "pattern|design" => [ name, image, fabric_cats ]. A pattern
	 * without designs has one, with design ''. The fabric categories are the ones the customizer allows for the
	 * design's sizes; a design without any is left out (the customizer shows no fabrics for it).
	 *
	 * @return array
	 */
	public static function designs() {
		static $list = null;
		if ( null !== $list ) {
			return $list;
		}
		$list = array();
		$root = AIMP_Settings::get( 'pattern_cat' );
		if ( ! $root ) {
			return $list;
		}
		$args                   = AIMP_Catalog::base_query_args( array( $root ), 'variable' );
		$args['posts_per_page'] = -1;
		$args['no_found_rows']  = true;
		foreach ( ( new WP_Query( $args ) )->posts as $pattern_id ) {
			$pattern = wc_get_product( $pattern_id );
			if ( ! $pattern || ! $pattern->is_visible() ) {
				continue;
			}
			$name    = wp_strip_all_tags( $pattern->get_name() );
			$designs = AIMP_Catalog::designs( $pattern );
			// The allowed fabric categories of every size the customizer can open, per design.
			$cats = array();
			foreach ( $pattern->get_children() as $size_id ) {
				$req = AIMP_Catalog::get_requirements( $size_id );
				if ( ! $req || ! $req['fabric_cats'] ) {
					continue;
				}
				$slug          = $designs ? AIMP_Catalog::variation_design( $size_id ) : '';
				$cats[ $slug ] = array_values( array_unique( array_merge( isset( $cats[ $slug ] ) ? $cats[ $slug ] : array(), $req['fabric_cats'] ) ) );
			}
			if ( ! $designs ) {
				if ( isset( $cats[''] ) ) {
					$list[ $pattern_id . '|' ] = array(
						'name'        => $name,
						'image'       => (int) $pattern->get_image_id(),
						'fabric_cats' => $cats[''],
					);
				}
				continue;
			}
			$data = AIMP_Catalog::design_data( $pattern_id );
			foreach ( $designs as $slug => $label ) {
				if ( ! isset( $cats[ $slug ] ) ) {
					continue;
				}
				$row = isset( $data[ $slug ] ) ? $data[ $slug ] : array();
				$list[ $pattern_id . '|' . $slug ] = array(
					'name'        => $name . ' – ' . $label,
					'image'       => ! empty( $row['image_ids'] ) ? (int) $row['image_ids'][0] : (int) $pattern->get_image_id(),
					'fabric_cats' => $cats[ $slug ],
				);
			}
		}
		// The pictures the AI gets (Per pattern design): the ticked pictures of the pattern (else its first picture),
		// then the other pictures of the garment. 3.17 kept one picture per design.
		$ticked = (array) self::opt( 'pattern_pics' );
		$extra  = (array) self::opt( 'pattern_extra' );
		foreach ( $list as $design => $item ) {
			$images = isset( $ticked[ $design ] ) ? array_map( 'absint', (array) $ticked[ $design ] ) : array();
			if ( ! $images ) {
				$images = array( $item['image'] );
			}
			$more = isset( $extra[ $design ] ) ? array_map( 'absint', (array) $extra[ $design ] ) : array();
			$list[ $design ]['images'] = array_values( array_unique( array_filter( array_merge( $images, $more ) ) ) );
		}
		return $list;
	}

	/**
	 * The fabrics of a design as the customizer lists them (published and visible, in the allowed categories).
	 *
	 * @param string $design "pattern|design".
	 * @return int[]
	 */
	public static function fabrics( $design ) {
		$designs = self::designs();
		if ( ! isset( $designs[ $design ] ) ) {
			return array();
		}
		$args                   = AIMP_Catalog::base_query_args( $designs[ $design ]['fabric_cats'], 'simple' );
		$args['posts_per_page'] = -1;
		$args['no_found_rows']  = true;
		$ids                    = array();
		foreach ( ( new WP_Query( $args ) )->posts as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/**
	 * @param string $key "pattern|design|fabric".
	 * @return array [ design key, fabric ID ] or empty when it is not a valid combination.
	 */
	private static function parse( $key ) {
		$parts = explode( '|', (string) $key );
		if ( 3 !== count( $parts ) || ! absint( $parts[0] ) || ! absint( $parts[2] ) ) {
			return array();
		}
		$design = absint( $parts[0] ) . '|' . sanitize_title( $parts[1] );
		$fabric = absint( $parts[2] );
		return isset( self::designs()[ $design ] ) ? array( $design, $fabric ) : array();
	}

	private static function looks() {
		$looks = get_option( self::LOOKS, array() );
		return is_array( $looks ) ? $looks : array();
	}

	private static function set_look( $key, $look ) {
		$looks = self::looks();
		if ( null === $look ) {
			unset( $looks[ $key ] );
		} else {
			$looks[ $key ] = $look;
		}
		update_option( self::LOOKS, $looks, false );
	}

	/**
	 * Approved pictures of a design: fabric ID => attachment ID (used by the customizer).
	 *
	 * @param int    $pattern_id Pattern.
	 * @param string $design     Design slug or ''.
	 * @return array
	 */
	public static function approved_for( $pattern_id, $design ) {
		$prefix = absint( $pattern_id ) . '|' . (string) $design . '|';
		$found  = array();
		foreach ( self::looks() as $key => $look ) {
			if ( 0 === strpos( (string) $key, $prefix ) && 'approved' === $look['status'] && ! empty( $look['att'] ) ) {
				$found[ absint( substr( $key, strlen( $prefix ) ) ) ] = (int) $look['att'];
			}
		}
		return $found;
	}

	/* ------------------------------------------------------------------ the monthly count */

	public static function month_count() {
		$count = get_option( self::COUNT, array() );
		return ( is_array( $count ) && isset( $count['month'] ) && gmdate( 'Y-m' ) === $count['month'] ) ? (int) $count['n'] : 0;
	}

	private static function add_count() {
		update_option(
			self::COUNT,
			array(
				'month' => gmdate( 'Y-m' ),
				'n'     => self::month_count() + 1,
			),
			false
		);
	}

	/* ------------------------------------------------------------------ making a picture */

	/**
	 * A picture as the AI gets it: a mid-size copy (not the large original).
	 *
	 * @param int    $id   Attachment.
	 * @param string $role person, garment, fabric or made (the garment from step 1).
	 * @return array|null [ bytes, mime, extension, attachment, role, upload cache key ].
	 */
	private static function picture( $id, $role ) {
		$file = $id ? get_attached_file( $id ) : '';
		if ( ! $file ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $id );
		foreach ( array( 'large', 'medium_large', '1536x1536' ) as $size ) {
			if ( ! empty( $meta['sizes'][ $size ]['file'] ) && file_exists( dirname( $file ) . '/' . $meta['sizes'][ $size ]['file'] ) ) {
				$file = dirname( $file ) . '/' . $meta['sizes'][ $size ]['file'];
				break;
			}
		}
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$type = wp_check_filetype( $file );
		if ( ! in_array( $type['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local upload.
		$bytes = file_get_contents( $file );
		return false === $bytes ? null : array( $bytes, $type['type'], 'jpeg' === $type['ext'] ? 'jpg' : $type['ext'], $id, $role, '' );
	}

	/**
	 * The fabric as a swatch: a square from the middle of its main picture, so the AI sees the cloth itself (not a
	 * folded bolt or the background). The whole picture when cutting fails.
	 *
	 * @param WC_Product $fabric Fabric.
	 * @return array|null Like picture().
	 */
	private static function swatch( $fabric ) {
		$id   = (int) $fabric->get_image_id();
		$file = $id ? get_attached_file( $id ) : '';
		if ( $file && file_exists( $file ) ) {
			$editor = wp_get_image_editor( $file );
			if ( ! is_wp_error( $editor ) ) {
				$size = $editor->get_size();
				$side = (int) floor( min( $size['width'], $size['height'] ) * 0.6 );
				if ( $side >= 100 ) {
					$out = min( 768, $side );
					$editor->crop( (int) floor( ( $size['width'] - $side ) / 2 ), (int) floor( ( $size['height'] - $side ) / 2 ), $side, $side, $out, $out );
					$editor->set_quality( 90 );
					$target = trailingslashit( get_temp_dir() ) . 'aimp-swatch-' . wp_generate_password( 8, false ) . '.jpg';
					$saved  = $editor->save( $target, 'image/jpeg' );
					if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && file_exists( $saved['path'] ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- our own temporary file.
						$bytes = file_get_contents( $saved['path'] );
						wp_delete_file( $saved['path'] );
						if ( $bytes ) {
							return array( $bytes, 'image/jpeg', 'jpg', $id, 'fabric', '_swatch' );
						}
					}
				}
			}
		}
		return self::picture( $id, 'fabric' );
	}

	/**
	 * What a picture is, as told to the AI.
	 *
	 * @param string $role Role.
	 * @return string
	 */
	private static function role_text( $role ) {
		$roles = array(
			'base'    => 'the photo to edit: keep this person, her face, hair, skin, body, pose, hands, background and light exactly as they are',
			'face'    => 'a close-up of the same person, only to keep her face exactly the same',
			'garment' => 'a picture of the garment (sewing pattern): use only its shape, cut, length, neckline, sleeves and details, NOT its colour, print or fabric',
			'fabric'  => 'the fabric swatch: the garment must be made from exactly this fabric (same colour, print and texture)',
			'made'    => 'the finished garment: copy it exactly, including its fabric, colour and print',
		);
		return isset( $roles[ $role ] ) ? $roles[ $role ] : $role;
	}

	/**
	 * The text for the AI: which picture is what, what to do, then (except for step 1) the prompt from the settings.
	 *
	 * @param array $images Pictures (picture()).
	 * @param int   $step   0 = one step, 1 = the garment in the fabric, 2 = that garment on the person.
	 * @return string
	 */
	private static function prompt( $images, $step ) {
		$lines   = array();
		$garment = array();
		foreach ( $images as $i => $image ) {
			$lines[] = 'Image ' . ( $i + 1 ) . ': ' . self::role_text( $image[4] ) . '.';
			if ( in_array( $image[4], array( 'garment', 'made' ), true ) ) {
				$garment[] = $i + 1;
			}
		}
		$text = implode( "\n", $lines ) . "\n\n";
		if ( 1 === $step ) {
			return $text . 'Make a product photo of the garment, sewn entirely from the fabric of the fabric swatch. Keep the garment\'s exact shape, cut, length, neckline, sleeves and details. Replace its colour, print and texture completely with the fabric: the same colour, print and texture, with the print at a realistic scale for clothing. Ignore the colour and fabric shown in the garment picture. Show the whole garment on an invisible mannequin, front view, plain white background, soft even light. No person, no text.';
		}
		// The person is not drawn again: her own photo is edited, only the clothes change.
		$from = count( $garment ) > 1 ? 'images ' . reset( $garment ) . ' to ' . end( $garment ) : 'image ' . reset( $garment );
		$rule = 'Edit image 1. Replace only her clothes with exactly the garment from ' . $from;
		$rule .= 2 === $step
			? ' (the same shape, details, fabric, colour and print)'
			: ', made entirely from the fabric of the fabric swatch (its colour, print and texture replace the colour and fabric shown in the garment picture, which only shows the shape)';
		$rule .= ', fitted naturally to her body and pose. Change nothing else: the same face, hair, expression, skin, body, hands, pose, background and light.';
		return $text . $rule . "\n\n" . (string) self::opt( 'prompt' );
	}

	/**
	 * The model photo a combination is made with: always the same one for a combination, different poses across
	 * combinations.
	 *
	 * @param string $key Combination.
	 * @return int Attachment, or 0.
	 */
	private static function base_photo( $key ) {
		// The design's own model photo (Per pattern design), else one of the general model photos.
		$parts  = explode( '|', $key );
		$models = (array) self::opt( 'design_model' );
		$design = $parts[0] . '|' . ( isset( $parts[1] ) ? $parts[1] : '' );
		if ( ! empty( $models[ $design ] ) && wp_attachment_is_image( (int) $models[ $design ] ) ) {
			return (int) $models[ $design ];
		}
		$people = array_values( array_filter( array_map( 'absint', (array) self::opt( 'people' ) ) ) );
		return $people ? $people[ abs( crc32( $key ) ) % count( $people ) ] : 0;
	}

	/**
	 * Starts a picture or a step of it: [ 'image' => bytes, 'mime' => … ] when the service answers at once,
	 * [ 'job' => id ] when it works in the background (Leonardo), or a WP_Error.
	 *
	 * @param string $key  Combination.
	 * @param int    $step 0 = one step, 1 = the garment in the fabric, 2 = that garment ($made) on the person.
	 * @param int    $made Step 2: the attachment of step 1.
	 * @return array|WP_Error
	 */
	private static function start( $key, $step, $made = 0 ) {
		$parsed = self::parse( $key );
		if ( ! $parsed ) {
			return new WP_Error( 'aimp_ai', __( 'This combination no longer exists.', 'atelier-irisee-master-plugin' ) );
		}
		list( $design, $fabric_id ) = $parsed;
		$service = (string) self::opt( 'service' );
		$api_key = self::api_key( $service );
		if ( '' === $api_key ) {
			return new WP_Error( 'aimp_ai', __( 'There is no API key for this service yet (save it first).', 'atelier-irisee-master-plugin' ) );
		}
		// Room for the whole picture (both steps) before the first one; step 2 was counted in already.
		$need = 1 === $step ? 2 : 1;
		if ( 2 !== $step && self::month_count() + $need > (int) self::opt( 'monthly_max' ) ) {
			return new WP_Error( 'aimp_ai', __( 'The maximum number of pictures for this month is reached (see Maximum per month).', 'atelier-irisee-master-plugin' ) );
		}
		$images = array();
		if ( 1 !== $step ) {
			// Her own photo to dress first, then the face close-ups.
			$base = self::picture( self::base_photo( $key ), 'base' );
			if ( ! $base ) {
				return new WP_Error( 'aimp_ai', __( 'Choose at least one model photo to dress (and save).', 'atelier-irisee-master-plugin' ) );
			}
			$images[] = $base;
			foreach ( (array) self::opt( 'faces' ) as $id ) {
				$face = self::picture( absint( $id ), 'face' );
				if ( $face ) {
					$images[] = $face;
				}
			}
		}
		if ( 2 === $step ) {
			$garment = self::picture( $made, 'made' );
			if ( ! $garment ) {
				return new WP_Error( 'aimp_ai', __( 'The picture of the first step is missing. Generate again.', 'atelier-irisee-master-plugin' ) );
			}
			$images[] = $garment;
		} else {
			$designs = self::designs();
			$fabric  = wc_get_product( $fabric_id );
			$cloth   = $fabric ? self::swatch( $fabric ) : null;
			$pattern = array();
			foreach ( $designs[ $design ]['images'] as $id ) {
				$picture = self::picture( $id, 'garment' );
				if ( $picture ) {
					$pattern[] = $picture;
				}
			}
			if ( ! $pattern || ! $cloth ) {
				return new WP_Error( 'aimp_ai', __( 'The pattern or the fabric has no picture.', 'atelier-irisee-master-plugin' ) );
			}
			$images   = array_merge( $images, $pattern );
			$images[] = $cloth;
		}
		$prompt = self::prompt( $images, $step );
		$model  = (string) self::opt( 'model_' . $service );
		// Step 2 (the person) can use a better model than step 1.
		$model2 = trim( (string) self::opt( 'model_step2_' . $service ) );
		if ( 1 !== $step && '' !== $model2 ) {
			$model = $model2;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		switch ( $service ) {
			case 'gemini':
				$result = self::gemini( $api_key, $model, $prompt, $images );
				break;
			case 'openai':
				$result = self::openai( $api_key, $model, $prompt, $images );
				break;
			default:
				$result = self::leonardo( $api_key, $model, $prompt, $images );
		}
		if ( ! is_wp_error( $result ) ) {
			self::add_count();
		}
		return $result;
	}

	/**
	 * The error message in a service's answer.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param array|null     $json     Decoded body.
	 * @return WP_Error
	 */
	private static function http_error( $response, $json ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aimp_ai', $response->get_error_message() );
		}
		$message = '';
		if ( is_array( $json ) ) {
			if ( isset( $json['error']['message'] ) ) {
				$message = $json['error']['message'];
			} elseif ( isset( $json['error'] ) && is_string( $json['error'] ) ) {
				$message = $json['error'];
			} elseif ( isset( $json['message'] ) ) {
				$message = $json['message'];
			}
		}
		if ( '' === $message ) {
			$message = 'HTTP ' . wp_remote_retrieve_response_code( $response ) . ' ' . substr( wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ) ), 0, 200 );
		}
		return new WP_Error( 'aimp_ai', (string) $message );
	}

	/**
	 * A multipart/form-data body.
	 *
	 * @param array  $fields   name => value.
	 * @param array  $files    List of [ name, file name, mime, bytes ].
	 * @param string $boundary Set to the boundary used.
	 * @return string
	 */
	private static function multipart( $fields, $files, &$boundary ) {
		$boundary = 'aimp' . wp_generate_password( 24, false );
		$body     = '';
		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n" . $value . "\r\n";
		}
		foreach ( $files as $file ) {
			$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $file[0] . '"; filename="' . $file[1] . "\"\r\nContent-Type: " . $file[2] . "\r\n\r\n" . $file[3] . "\r\n";
		}
		return $body . '--' . $boundary . "--\r\n";
	}

	/**
	 * Google Gemini (generateContent with the pictures inline; the answer has the picture).
	 */
	private static function gemini( $api_key, $model, $prompt, $images ) {
		// Each picture comes after a line that says what it is.
		$parts = array( array( 'text' => $prompt ) );
		foreach ( $images as $i => $image ) {
			$parts[] = array( 'text' => 'Image ' . ( $i + 1 ) . ' – ' . self::role_text( $image[4] ) . ':' );
			$parts[] = array(
				'inline_data' => array(
					'mime_type' => $image[1],
					'data'      => base64_encode( $image[0] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				),
			);
		}
		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
			array(
				'timeout' => 150,
				'headers' => array(
					'x-goog-api-key' => $api_key,
					'Content-Type'   => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contents'         => array( array( 'parts' => $parts ) ),
						'generationConfig' => array(
							'responseModalities' => array( 'TEXT', 'IMAGE' ),
							'imageConfig'        => array( 'aspectRatio' => '3:4' ),
						),
					)
				),
			)
		);
		$json = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return self::http_error( $response, $json );
		}
		$text = '';
		foreach ( isset( $json['candidates'][0]['content']['parts'] ) ? (array) $json['candidates'][0]['content']['parts'] : array() as $part ) {
			$inline = isset( $part['inlineData'] ) ? $part['inlineData'] : ( isset( $part['inline_data'] ) ? $part['inline_data'] : null );
			if ( $inline && ! empty( $inline['data'] ) ) {
				return array(
					'image' => base64_decode( $inline['data'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					'mime'  => isset( $inline['mimeType'] ) ? $inline['mimeType'] : ( isset( $inline['mime_type'] ) ? $inline['mime_type'] : 'image/png' ),
				);
			}
			if ( isset( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}
		$reason = isset( $json['candidates'][0]['finishReason'] ) ? $json['candidates'][0]['finishReason'] : ( isset( $json['promptFeedback']['blockReason'] ) ? $json['promptFeedback']['blockReason'] : '' );
		return new WP_Error( 'aimp_ai', trim( 'No picture in the answer. ' . $reason . ' ' . substr( $text, 0, 300 ) ) );
	}

	/**
	 * OpenAI (images/edits with all pictures; the answer has the picture).
	 */
	private static function openai( $api_key, $model, $prompt, $images ) {
		$files = array();
		foreach ( $images as $i => $image ) {
			$files[] = array( 'image[]', 'image-' . ( $i + 1 ) . '.' . $image[2], $image[1], $image[0] );
		}
		$boundary = '';
		$body     = self::multipart(
			array(
				'model'   => $model,
				'prompt'  => $prompt,
				'size'    => '1024x1536',
				'quality' => 'medium',
				'n'       => '1',
			),
			$files,
			$boundary
		);
		$response = wp_remote_post(
			'https://api.openai.com/v1/images/edits',
			array(
				'timeout' => 170,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);
		$json = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return self::http_error( $response, $json );
		}
		if ( empty( $json['data'][0]['b64_json'] ) ) {
			return new WP_Error( 'aimp_ai', 'No picture in the answer.' );
		}
		return array(
			'image' => base64_decode( $json['data'][0]['b64_json'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			'mime'  => 'image/png',
		);
	}

	/**
	 * Leonardo AI: the pictures are uploaded once (their ids are remembered), then a generation is started with them
	 * as image references; it is fetched later (poll()).
	 */
	private static function leonardo( $api_key, $model, $prompt, $images ) {
		$headers = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
		);
		$refs = array();
		// Leonardo takes up to 6 reference pictures: leave out face close-ups first, then extra garment pictures (one
		// garment picture always stays).
		foreach ( array( 'face' => 0, 'garment' => 1 ) as $role => $keep ) {
			for ( $i = count( $images ) - 1; $i >= 0 && count( $images ) > 6; $i-- ) {
				$same = 0;
				foreach ( $images as $image ) {
					$same += $role === $image[4] ? 1 : 0;
				}
				if ( $role === $images[ $i ][4] && $same > $keep ) {
					array_splice( $images, $i, 1 );
				}
			}
		}
		foreach ( array_slice( $images, 0, 6 ) as $image ) {
			$id = self::leonardo_upload( $headers, $image );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$refs[] = array(
				'image'    => array(
					'id'   => $id,
					'type' => 'UPLOADED',
				),
				// The garment picture only for the shape; the person and the fabric count most.
				'strength' => 'garment' === $image[4] ? 'LOW' : 'HIGH',
			);
		}
		$response = wp_remote_post(
			'https://cloud.leonardo.ai/api/rest/v2/generations',
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'parameters' => array(
							'width'          => 864,
							'height'         => 1152,
							'prompt'         => $prompt,
							'quantity'       => 1,
							'guidances'      => array( 'image_reference' => $refs ),
							'prompt_enhance' => 'OFF',
						),
						'public'     => false,
					)
				),
			)
		);
		$json = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return self::http_error( $response, $json );
		}
		$job = '';
		if ( isset( $json['generate']['generationId'] ) ) {
			$job = $json['generate']['generationId'];
		} elseif ( isset( $json['sdGenerationJob']['generationId'] ) ) {
			$job = $json['sdGenerationJob']['generationId'];
		} elseif ( is_array( $json ) && isset( $json[0]['errors'] ) ) {
			return new WP_Error( 'aimp_ai', wp_json_encode( $json[0]['errors'] ) );
		}
		if ( '' === $job ) {
			return new WP_Error( 'aimp_ai', 'Leonardo: no generation id. ' . substr( (string) wp_remote_retrieve_body( $response ), 0, 300 ) );
		}
		return array( 'job' => $job );
	}

	/**
	 * Uploads a picture to Leonardo (or reuses the id of an earlier upload of the same picture, for a week).
	 *
	 * @return string|WP_Error Leonardo's image id.
	 */
	private static function leonardo_upload( $headers, $image ) {
		// The swatch is uploaded apart from the whole fabric picture.
		$meta  = self::META_LEONARDO . $image[5];
		$saved = get_post_meta( $image[3], $meta, true );
		if ( is_array( $saved ) && ! empty( $saved['id'] ) && $saved['time'] > time() - WEEK_IN_SECONDS ) {
			return $saved['id'];
		}
		$response = wp_remote_post(
			'https://cloud.leonardo.ai/api/rest/v1/init-image',
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => wp_json_encode( array( 'extension' => $image[2] ) ),
			)
		);
		$json = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $json['uploadInitImage']['id'] ) || empty( $json['uploadInitImage']['url'] ) ) {
			return self::http_error( $response, $json );
		}
		$upload = $json['uploadInitImage'];
		$fields = is_string( $upload['fields'] ) ? json_decode( $upload['fields'], true ) : (array) $upload['fields'];
		$boundary = '';
		$body     = self::multipart( (array) $fields, array( array( 'file', 'image.' . $image[2], $image[1], $image[0] ) ), $boundary );
		$sent     = wp_remote_post(
			$upload['url'],
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
				'body'    => $body,
			)
		);
		$code = is_wp_error( $sent ) ? 0 : wp_remote_retrieve_response_code( $sent );
		if ( $code < 200 || $code >= 300 ) {
			return self::http_error( $sent, null );
		}
		update_post_meta(
			$image[3],
			$meta,
			array(
				'id'   => $upload['id'],
				'time' => time(),
			)
		);
		return $upload['id'];
	}

	/**
	 * A Leonardo generation: null while it runs, the picture when it is ready, or a WP_Error.
	 *
	 * @param string $job Generation id.
	 * @return array|null|WP_Error
	 */
	private static function leonardo_result( $job ) {
		$response = wp_remote_get(
			'https://cloud.leonardo.ai/api/rest/v1/generations/' . rawurlencode( $job ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . self::api_key( 'leonardo' ),
					'Accept'        => 'application/json',
				),
			)
		);
		$json = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $json['generations_by_pk'] ) ) {
			return self::http_error( $response, $json );
		}
		$generation = $json['generations_by_pk'];
		if ( 'FAILED' === $generation['status'] ) {
			return new WP_Error( 'aimp_ai', 'Leonardo: the generation failed.' );
		}
		if ( 'COMPLETE' !== $generation['status'] || empty( $generation['generated_images'][0]['url'] ) ) {
			return null;
		}
		$file = wp_remote_get( $generation['generated_images'][0]['url'], array( 'timeout' => 60 ) );
		if ( is_wp_error( $file ) || 200 !== wp_remote_retrieve_response_code( $file ) ) {
			return self::http_error( $file, null );
		}
		$mime = (string) wp_remote_retrieve_header( $file, 'content-type' );
		return array(
			'image' => wp_remote_retrieve_body( $file ),
			'mime'  => 0 === strpos( $mime, 'image/' ) ? $mime : 'image/jpeg',
		);
	}

	/**
	 * Puts a made picture in the media library (AIMP_Images makes its WebP copy).
	 *
	 * @param string $key    Combination.
	 * @param array  $image  [ image, mime ].
	 * @param string $prefix File name start.
	 * @param string $title  Title start.
	 * @return int|WP_Error Attachment.
	 */
	private static function store( $key, $image, $prefix, $title ) {
		$extensions = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		);
		$mime = strtolower( trim( explode( ';', $image['mime'] )[0] ) );
		$ext  = isset( $extensions[ $mime ] ) ? $extensions[ $mime ] : 'png';
		list( $design, $fabric_id ) = self::parse( $key );
		$designs = self::designs();
		$name    = $designs[ $design ]['name'] . ' – ' . wp_strip_all_tags( get_the_title( $fabric_id ) );
		$upload  = wp_upload_bits( sanitize_file_name( $prefix . str_replace( '|', '-', $key ) . '-' . time() . '.' . $ext ), null, $image['image'] );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'aimp_ai', $upload['error'] );
		}
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => $title . ' – ' . $name,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'aimp_ai', __( 'The picture could not be saved.', 'atelier-irisee-master-plugin' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $name );
		return (int) $id;
	}

	/**
	 * Saves the finished picture and marks it as waiting for approval (the picture of step 1 is removed).
	 *
	 * @param string $key   Combination.
	 * @param array  $image [ image, mime ].
	 * @return array|WP_Error The look.
	 */
	private static function save( $key, $image ) {
		$id = self::store( $key, $image, 'ai-look-', 'AI' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::META, $key );
		self::drop_step1( $key );
		$look = array(
			'att'    => $id,
			'status' => 'pending',
			'time'   => time(),
		);
		self::set_look( $key, $look );
		return $look;
	}

	/**
	 * Removes the in-between picture of step 1 of a combination.
	 *
	 * @param string $key Combination.
	 */
	private static function drop_step1( $key ) {
		$looks = self::looks();
		if ( ! empty( $looks[ $key ]['step1_att'] ) ) {
			wp_delete_attachment( (int) $looks[ $key ]['step1_att'], true );
		}
	}

	/**
	 * Removes the picture of a combination (from the media library too), and a picture of step 1.
	 *
	 * @param string $key Combination.
	 */
	private static function drop_picture( $key ) {
		$looks = self::looks();
		if ( ! empty( $looks[ $key ]['att'] ) ) {
			wp_delete_attachment( (int) $looks[ $key ]['att'], true );
		}
		self::drop_step1( $key );
	}

	/**
	 * Marks a combination as failed and answers the browser.
	 *
	 * @param string $key     Combination.
	 * @param string $message Error.
	 */
	private static function fail( $key, $message ) {
		self::drop_step1( $key );
		self::set_look(
			$key,
			array(
				'att'    => 0,
				'status' => 'failed',
				'time'   => time(),
				'error'  => $message,
			)
		);
		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * What happens with the answer of a step, and the answer to the browser: "running" while a background job runs
	 * or step 2 is still to come (the browser then asks poll), "done" when the picture is saved.
	 *
	 * @param string         $key    Combination.
	 * @param array|WP_Error $result Answer of start() or leonardo_result().
	 * @param int            $step   0 = one step, 1 or 2.
	 */
	private static function advance( $key, $result, $step ) {
		if ( is_wp_error( $result ) ) {
			self::fail( $key, $result->get_error_message() );
		}
		$looks = self::looks();
		$step1 = ! empty( $looks[ $key ]['step1_att'] ) ? (int) $looks[ $key ]['step1_att'] : 0;
		if ( isset( $result['job'] ) ) {
			self::set_look(
				$key,
				array(
					'att'       => 0,
					'status'    => 'running',
					'job'       => $result['job'],
					'step'      => $step,
					'step1_att' => $step1,
					'time'      => time(),
				)
			);
			wp_send_json_success( array( 'state' => 'running' ) );
		}
		if ( 1 === $step ) {
			// The garment in the fabric: kept until step 2 has used it.
			$id = self::store( $key, $result, 'ai-step1-', 'AI step 1' );
			if ( is_wp_error( $id ) ) {
				self::fail( $key, $id->get_error_message() );
			}
			update_post_meta( $id, self::META . '_step1', $key );
			self::set_look(
				$key,
				array(
					'att'       => 0,
					'status'    => 'running',
					'step'      => 2,
					'step1_att' => $id,
					'time'      => time(),
				)
			);
			wp_send_json_success( array( 'state' => 'running' ) );
		}
		$look = self::save( $key, $result );
		if ( is_wp_error( $look ) ) {
			self::fail( $key, $look->get_error_message() );
		}
		wp_send_json_success( array( 'state' => 'done' ) );
	}

	/* ------------------------------------------------------------------ ajax */

	private static function check() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'atelier-irisee-master-plugin' ) ), 403 );
		}
	}

	private static function posted_key() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
		return isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	}

	/** The fabrics of a design, for the test select. */
	public static function ajax_fabrics() {
		self::check();
		$design = self::posted_key();
		$looks  = self::looks();
		$list   = array();
		foreach ( self::fabrics( $design ) as $id ) {
			$key    = $design . '|' . $id;
			$list[] = array(
				'key'    => $key,
				'name'   => wp_strip_all_tags( get_the_title( $id ) ),
				'status' => isset( $looks[ $key ] ) ? $looks[ $key ]['status'] : '',
			);
		}
		wp_send_json_success( $list );
	}

	/**
	 * Makes the picture of one combination. With "Two steps" (and with Leonardo, which works in the background) this
	 * answers "running" and the browser asks poll until it is done.
	 */
	public static function ajax_generate() {
		self::check();
		$key    = self::posted_key();
		$parsed = self::parse( $key );
		// Only combinations the customizer shows: the fabric must be allowed for the design.
		if ( ! $parsed || ! in_array( $parsed[1], self::fabrics( $parsed[0] ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This combination no longer exists.', 'atelier-irisee-master-plugin' ) ) );
		}
		// A new picture replaces the old one.
		self::drop_picture( $key );
		self::set_look( $key, null );
		$step = 2 === self::steps() ? 1 : 0;
		self::advance( $key, self::start( $key, $step ), $step );
	}

	/** A picture being made: a background job to fetch, or step 2 to make. */
	public static function ajax_poll() {
		self::check();
		$key   = self::posted_key();
		$looks = self::looks();
		if ( ! isset( $looks[ $key ] ) || 'running' !== $looks[ $key ]['status'] ) {
			wp_send_json_success( array( 'state' => isset( $looks[ $key ] ) ? $looks[ $key ]['status'] : '' ) );
		}
		$look = $looks[ $key ];
		$step = isset( $look['step'] ) ? (int) $look['step'] : 0;
		if ( ! empty( $look['job'] ) ) {
			$result = self::leonardo_result( $look['job'] );
			if ( null === $result ) {
				// Ten minutes without an answer: give up.
				if ( $look['time'] >= time() - 600 ) {
					wp_send_json_success( array( 'state' => 'running' ) );
				}
				$result = new WP_Error( 'aimp_ai', __( 'The service did not answer in time.', 'atelier-irisee-master-plugin' ) );
			}
			self::advance( $key, $result, $step );
		}
		if ( 2 === $step && ! empty( $look['step1_att'] ) ) {
			self::advance( $key, self::start( $key, 2, (int) $look['step1_att'] ), 2 );
		}
		self::fail( $key, __( 'The picture of the first step is missing. Generate again.', 'atelier-irisee-master-plugin' ) );
	}

	/** The combinations without a picture (failed ones are tried again; rejected ones are not). */
	public static function ajax_missing() {
		self::check();
		$looks   = self::looks();
		$missing = array();
		foreach ( array_keys( self::designs() ) as $design ) {
			foreach ( self::fabrics( $design ) as $fabric_id ) {
				$key = $design . '|' . $fabric_id;
				if ( ! isset( $looks[ $key ] ) || 'failed' === $looks[ $key ]['status'] ) {
					$missing[] = $key;
				}
			}
		}
		$steps = self::steps();
		wp_send_json_success(
			array(
				'keys'  => $missing,
				'steps' => $steps,
				// Pictures that still fit in this month's maximum.
				'left'  => (int) floor( max( 0, (int) self::opt( 'monthly_max' ) - self::month_count() ) / $steps ),
			)
		);
	}

	/** Approve, reject or remove a picture. */
	public static function ajax_review() {
		self::check();
		$key = self::posted_key();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
		$do    = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$looks = self::looks();
		if ( ! isset( $looks[ $key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This picture no longer exists.', 'atelier-irisee-master-plugin' ) ) );
		}
		$look = $looks[ $key ];
		if ( 'approve' === $do && ! empty( $look['att'] ) ) {
			$look['status'] = 'approved';
			self::set_look( $key, $look );
		} elseif ( 'reject' === $do ) {
			// Rejected: the picture is deleted; "Generate missing pictures" skips this combination.
			self::drop_picture( $key );
			self::set_look(
				$key,
				array(
					'att'    => 0,
					'status' => 'rejected',
					'time'   => time(),
				)
			);
		} elseif ( 'remove' === $do ) {
			self::drop_picture( $key );
			self::set_look( $key, null );
		}
		// The customizer remembers fabric lists: start again.
		AIMP_Shop::flush_cache();
		wp_send_json_success();
	}

	/** All pictures for the review grid, newest first. */
	public static function ajax_list() {
		self::check();
		$designs = self::designs();
		$items   = array();
		foreach ( self::looks() as $key => $look ) {
			$parts  = explode( '|', $key );
			$design = $parts[0] . '|' . ( isset( $parts[1] ) ? $parts[1] : '' );
			$att    = ! empty( $look['att'] ) ? (int) $look['att'] : 0;
			$items[] = array(
				'key'    => $key,
				'status' => $look['status'],
				'design' => isset( $designs[ $design ] ) ? $designs[ $design ]['name'] : '#' . $parts[0],
				'fabric' => isset( $parts[2] ) ? wp_strip_all_tags( get_the_title( absint( $parts[2] ) ) ) : '',
				'thumb'  => $att ? (string) wp_get_attachment_image_url( $att, 'medium' ) : '',
				'large'  => $att ? (string) wp_get_attachment_image_url( $att, 'full' ) : '',
				// The fabric beside the picture, to compare.
				'swatch' => isset( $parts[2] ) ? (string) get_the_post_thumbnail_url( absint( $parts[2] ), 'thumbnail' ) : '',
				'error'  => isset( $look['error'] ) ? $look['error'] : '',
				'time'   => isset( $look['time'] ) ? (int) $look['time'] : 0,
			);
		}
		usort(
			$items,
			function ( $a, $b ) {
				return $b['time'] - $a['time'];
			}
		);
		wp_send_json_success(
			array(
				'items' => $items,
				'count' => self::month_count(),
			)
		);
	}
}
