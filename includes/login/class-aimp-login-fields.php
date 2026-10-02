<?php
/**
 * Registration & profile fields: built-in fields, the custom field builder, rendering,
 * validation, saving (incl. uploads) and display in My Account and the admin user profile.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Login_Fields {

	const AVATAR_META   = 'aimp_avatar';
	const AVATAR_MAX_MB = 5;
	const FILE_MAX_MB   = 10;

	public static function init() {
		// Admin user profile.
		add_action( 'show_user_profile', array( __CLASS__, 'admin_profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'admin_profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'admin_profile_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'admin_profile_save' ) );
		add_action( 'user_edit_form_tag', array( __CLASS__, 'multipart_form_tag' ) );

		// WooCommerce My Account > Account details.
		add_action( 'woocommerce_edit_account_form_tag', array( __CLASS__, 'multipart_form_tag' ) );
		add_action( 'woocommerce_edit_account_form', array( __CLASS__, 'account_fields' ) );
		add_action( 'woocommerce_save_account_details_errors', array( __CLASS__, 'account_validate' ), 10, 2 );
		add_action( 'woocommerce_save_account_details', array( __CLASS__, 'account_save' ) );

		// Profile picture as the site-wide avatar.
		add_filter( 'pre_get_avatar_data', array( __CLASS__, 'avatar_data' ), 10, 2 );
	}

	/* ------------------------------------------------------------------
	 * Definitions
	 * ------------------------------------------------------------------ */

	/**
	 * Field types for the builder: type => label.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'text'     => __( 'Text', 'atelier-irisee-master-plugin' ),
			'textarea' => __( 'Text area', 'atelier-irisee-master-plugin' ),
			'email'    => __( 'Email', 'atelier-irisee-master-plugin' ),
			'number'   => __( 'Number', 'atelier-irisee-master-plugin' ),
			'date'     => __( 'Date', 'atelier-irisee-master-plugin' ),
			'phone'    => __( 'Phone', 'atelier-irisee-master-plugin' ),
			'select'   => __( 'Dropdown', 'atelier-irisee-master-plugin' ),
			'radio'    => __( 'Radio buttons', 'atelier-irisee-master-plugin' ),
			'checkbox' => __( 'Checkbox', 'atelier-irisee-master-plugin' ),
			'file'     => __( 'File upload', 'atelier-irisee-master-plugin' ),
			'avatar'   => __( 'Profile picture', 'atelier-irisee-master-plugin' ),
			'role'     => __( 'User role', 'atelier-irisee-master-plugin' ),
		);
	}

	/**
	 * WooCommerce customer fields a custom field can be saved to.
	 *
	 * @return array
	 */
	public static function wc_map_options() {
		$fields = array(
			'first_name' => __( 'First name', 'atelier-irisee-master-plugin' ),
			'last_name'  => __( 'Last name', 'atelier-irisee-master-plugin' ),
			'company'    => __( 'Company', 'atelier-irisee-master-plugin' ),
			'address_1'  => __( 'Street and house number', 'atelier-irisee-master-plugin' ),
			'address_2'  => __( 'Address line 2', 'atelier-irisee-master-plugin' ),
			'postcode'   => __( 'Postcode', 'atelier-irisee-master-plugin' ),
			'city'       => __( 'City', 'atelier-irisee-master-plugin' ),
			'state'      => __( 'State / province', 'atelier-irisee-master-plugin' ),
			'country'    => __( 'Country', 'atelier-irisee-master-plugin' ),
			'phone'      => __( 'Phone', 'atelier-irisee-master-plugin' ),
		);
		$options = array( '' => __( 'Do not save to WooCommerce', 'atelier-irisee-master-plugin' ) );
		foreach ( array( 'billing' => __( 'Billing', 'atelier-irisee-master-plugin' ), 'shipping' => __( 'Shipping', 'atelier-irisee-master-plugin' ) ) as $group => $group_label ) {
			foreach ( $fields as $key => $label ) {
				if ( 'shipping' === $group && 'phone' === $key ) {
					continue;
				}
				$options[ $group . '_' . $key ] = $group_label . ': ' . $label;
			}
		}
		return $options;
	}

	/**
	 * Roles a customer may choose in a "User role" field: never roles that can manage or edit the site.
	 *
	 * @return array role => name
	 */
	public static function safe_roles() {
		$roles = array();
		foreach ( wp_roles()->roles as $role => $info ) {
			$caps = isset( $info['capabilities'] ) ? $info['capabilities'] : array();
			if ( ! empty( $caps['manage_options'] ) || ! empty( $caps['edit_posts'] ) || ! empty( $caps['manage_woocommerce'] ) || ! empty( $caps['edit_users'] ) ) {
				continue;
			}
			$roles[ $role ] = translate_user_role( $info['name'] );
		}
		return $roles;
	}

	/**
	 * Custom fields from the settings, optionally only those for one context.
	 *
	 * @param string $context '' (all), 'register', 'profile' or 'admin'.
	 * @return array[]
	 */
	public static function custom( $context = '' ) {
		$fields = array();
		foreach ( (array) AIMP_Login::opt( 'fields' ) as $field ) {
			if ( empty( $field['key'] ) ) {
				continue;
			}
			if ( $context && ! in_array( $context, (array) $field['contexts'], true ) ) {
				continue;
			}
			$fields[] = $field;
		}
		return apply_filters( 'aimp_el_fields', $fields, $context );
	}

	/**
	 * Sanitize the builder rows into field definitions.
	 *
	 * @param array $rows Submitted rows.
	 * @return array[]
	 */
	public static function sanitize_definitions( $rows ) {
		$types   = self::types();
		$maps    = self::wc_map_options();
		$roles   = self::safe_roles();
		$clean   = array();
		$used    = array();
		$reserve = array( 'email', 'username', 'password', 'password2', 'first_name', 'last_name', 'display_name', 'terms', 'remember' );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$key   = isset( $row['key'] ) && '' !== $row['key'] ? sanitize_key( $row['key'] ) : sanitize_key( str_replace( ' ', '_', remove_accents( $label ) ) );
			if ( '' === $key || '' === $label || in_array( $key, $reserve, true ) || isset( $used[ $key ] ) ) {
				continue;
			}
			$used[ $key ] = true;
			$type         = isset( $row['type'], $types[ $row['type'] ] ) ? $row['type'] : 'text';

			$options = array();
			if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $row['options'] ) ) {
				$lines = is_array( $row['options'] ) ? $row['options'] : preg_split( '/\r\n|\r|\n/', (string) $row['options'] );
				foreach ( $lines as $line_key => $line ) {
					if ( is_array( $row['options'] ) ) {
						$options[ sanitize_text_field( $line_key ) ] = sanitize_text_field( $line );
						continue;
					}
					$line = trim( $line );
					if ( '' === $line ) {
						continue;
					}
					$parts                                  = array_map( 'trim', explode( '|', $line, 2 ) );
					$options[ sanitize_text_field( $parts[0] ) ] = sanitize_text_field( isset( $parts[1] ) ? $parts[1] : $parts[0] );
				}
			}

			$contexts = isset( $row['contexts'] ) ? array_values( array_intersect( (array) $row['contexts'], array( 'register', 'profile', 'admin' ) ) ) : array();

			$clean[] = array(
				'key'          => $key,
				'label'        => $label,
				'type'         => $type,
				'required'     => ! empty( $row['required'] ) ? 1 : 0,
				'placeholder'  => isset( $row['placeholder'] ) ? sanitize_text_field( $row['placeholder'] ) : '',
				'options'      => $options,
				'width'        => ( isset( $row['width'] ) && 'half' === $row['width'] ) ? 'half' : 'full',
				'autocomplete' => isset( $row['autocomplete'] ) ? sanitize_text_field( $row['autocomplete'] ) : '',
				'contexts'     => $contexts,
				'map'          => ( isset( $row['map'] ) && isset( $maps[ $row['map'] ] ) ) ? $row['map'] : '',
				'extensions'   => isset( $row['extensions'] ) ? implode( ',', array_filter( array_map( 'sanitize_key', explode( ',', (string) $row['extensions'] ) ) ) ) : '',
				'roles'        => isset( $row['roles'] ) ? array_values( array_intersect( (array) $row['roles'], array_keys( $roles ) ) ) : array(),
			);
		}
		return $clean;
	}

	/* ------------------------------------------------------------------
	 * Builder (admin)
	 * ------------------------------------------------------------------ */

	/**
	 * The repeatable field rows on the "Registration fields" tab.
	 *
	 * @param string $name   Input name base.
	 * @param array  $fields Current definitions.
	 */
	public static function render_builder( $name, $fields ) {
		echo '<div class="aimp-fields-builder" data-name="' . esc_attr( $name ) . '">';
		echo '<ul class="aimp-fields-list">';
		foreach ( array_values( $fields ) as $index => $field ) {
			self::render_builder_row( $name, $index, $field );
		}
		echo '</ul>';
		echo '<template class="aimp-field-template">';
		self::render_builder_row( $name, '__i__', array() );
		echo '</template>';
		echo '<p><button type="button" class="button aimp-field-add">' . esc_html__( 'Add field', 'atelier-irisee-master-plugin' ) . '</button></p>';
		echo '</div>';
	}

	private static function render_builder_row( $name, $index, $field ) {
		$field = wp_parse_args(
			$field,
			array(
				'key'          => '',
				'label'        => '',
				'type'         => 'text',
				'required'     => 0,
				'placeholder'  => '',
				'options'      => array(),
				'width'        => 'full',
				'autocomplete' => '',
				'contexts'     => array( 'register', 'profile', 'admin' ),
				'map'          => '',
				'extensions'   => 'pdf,jpg,jpeg,png',
				'roles'        => array(),
			)
		);
		$base    = $name . '[' . $index . ']';
		$options = '';
		foreach ( (array) $field['options'] as $value => $label ) {
			$options .= ( (string) $value === (string) $label ? $label : $value . '|' . $label ) . "\n";
		}
		?>
		<li class="aimp-field-row">
			<div class="aimp-field-row-head">
				<span class="aimp-field-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'atelier-irisee-master-plugin' ); ?>"></span>
				<strong class="aimp-field-title"><?php echo esc_html( $field['label'] ? $field['label'] : __( 'New field', 'atelier-irisee-master-plugin' ) ); ?></strong>
				<button type="button" class="button-link aimp-field-toggle"><?php esc_html_e( 'Edit', 'atelier-irisee-master-plugin' ); ?></button>
				<button type="button" class="button-link button-link-delete aimp-field-remove"><?php esc_html_e( 'Delete', 'atelier-irisee-master-plugin' ); ?></button>
			</div>
			<div class="aimp-field-row-body"<?php echo $field['label'] ? ' hidden' : ''; ?>>
				<p>
					<label><?php esc_html_e( 'Label', 'atelier-irisee-master-plugin' ); ?><br><input type="text" class="regular-text aimp-field-label" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $field['label'] ); ?>"></label>
					<label><?php esc_html_e( 'Key (letters, numbers, _)', 'atelier-irisee-master-plugin' ); ?><br><input type="text" name="<?php echo esc_attr( $base ); ?>[key]" value="<?php echo esc_attr( $field['key'] ); ?>"></label>
					<label><?php esc_html_e( 'Type', 'atelier-irisee-master-plugin' ); ?><br>
						<select class="aimp-field-type" name="<?php echo esc_attr( $base ); ?>[type]">
							<?php foreach ( self::types() as $type => $label ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p>
					<label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[required]" value="1" <?php checked( 1, (int) $field['required'] ); ?>> <?php esc_html_e( 'Required', 'atelier-irisee-master-plugin' ); ?></label>
					&nbsp; <label><?php esc_html_e( 'Width', 'atelier-irisee-master-plugin' ); ?>
						<select name="<?php echo esc_attr( $base ); ?>[width]">
							<option value="full" <?php selected( $field['width'], 'full' ); ?>><?php esc_html_e( 'Full width', 'atelier-irisee-master-plugin' ); ?></option>
							<option value="half" <?php selected( $field['width'], 'half' ); ?>><?php esc_html_e( 'Half width', 'atelier-irisee-master-plugin' ); ?></option>
						</select>
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Placeholder', 'atelier-irisee-master-plugin' ); ?><br><input type="text" class="regular-text" name="<?php echo esc_attr( $base ); ?>[placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>"></label>
					<label><?php esc_html_e( 'Browser autofill (autocomplete)', 'atelier-irisee-master-plugin' ); ?><br><input type="text" name="<?php echo esc_attr( $base ); ?>[autocomplete]" value="<?php echo esc_attr( $field['autocomplete'] ); ?>" placeholder="tel, postal-code, …"></label>
				</p>
				<p class="aimp-field-only aimp-field-only--select aimp-field-only--radio">
					<label><?php esc_html_e( 'Options (one per line, optionally value|Label)', 'atelier-irisee-master-plugin' ); ?><br><textarea rows="4" class="large-text" name="<?php echo esc_attr( $base ); ?>[options]"><?php echo esc_textarea( trim( $options ) ); ?></textarea></label>
				</p>
				<p class="aimp-field-only aimp-field-only--file">
					<label><?php esc_html_e( 'Allowed file extensions (comma separated)', 'atelier-irisee-master-plugin' ); ?><br><input type="text" name="<?php echo esc_attr( $base ); ?>[extensions]" value="<?php echo esc_attr( $field['extensions'] ); ?>"></label>
				</p>
				<p class="aimp-field-only aimp-field-only--role">
					<?php esc_html_e( 'Roles the customer can choose:', 'atelier-irisee-master-plugin' ); ?><br>
					<?php foreach ( self::safe_roles() as $role => $role_name ) : ?>
						<label style="margin-right:1em"><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $field['roles'], true ) ); ?>> <?php echo esc_html( $role_name ); ?></label>
					<?php endforeach; ?>
				</p>
				<p>
					<?php esc_html_e( 'Show in:', 'atelier-irisee-master-plugin' ); ?>
					<?php
					$contexts = array(
						'register' => __( 'Registration form', 'atelier-irisee-master-plugin' ),
						'profile'  => __( 'Profile & My Account', 'atelier-irisee-master-plugin' ),
						'admin'    => __( 'Admin user profile', 'atelier-irisee-master-plugin' ),
					);
					foreach ( $contexts as $context => $label ) :
						?>
						<label style="margin-right:1em"><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[contexts][]" value="<?php echo esc_attr( $context ); ?>" <?php checked( in_array( $context, (array) $field['contexts'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</p>
				<p class="aimp-field-not aimp-field-not--file aimp-field-not--avatar aimp-field-not--role">
					<label><?php esc_html_e( 'Also save as WooCommerce field', 'atelier-irisee-master-plugin' ); ?><br>
						<select name="<?php echo esc_attr( $base ); ?>[map]">
							<?php foreach ( self::wc_map_options() as $map => $label ) : ?>
								<option value="<?php echo esc_attr( $map ); ?>" <?php selected( $field['map'], $map ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
			</div>
		</li>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Built-in fields
	 * ------------------------------------------------------------------ */

	/**
	 * Built-in fields of a form.
	 *
	 * @param string $form 'login', 'register', 'lostpw', 'resetpw' or 'profile'.
	 * @return array[]
	 */
	public static function builtin( $form ) {
		$f = array();
		switch ( $form ) {
			case 'login':
				$f[] = array( 'key' => 'username', 'type' => 'text', 'label' => __( 'Email address or username', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'username' );
				$f[] = array( 'key' => 'password', 'type' => 'password', 'label' => __( 'Password', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'current-password' );
				break;
			case 'register':
				if ( AIMP_Login::opt( 'show_names' ) ) {
					$f[] = array( 'key' => 'first_name', 'type' => 'text', 'label' => __( 'First name', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'given-name', 'width' => 'half' );
					$f[] = array( 'key' => 'last_name', 'type' => 'text', 'label' => __( 'Last name', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'family-name', 'width' => 'half' );
				}
				$f[] = array( 'key' => 'email', 'type' => 'email', 'label' => __( 'Email address', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'email' );
				if ( 'field' === AIMP_Login::opt( 'username_mode' ) ) {
					$f[] = array( 'key' => 'username', 'type' => 'text', 'label' => __( 'Username', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'username' );
				}
				$f[] = array( 'key' => 'password', 'type' => 'password', 'label' => __( 'Password', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'new-password', 'strength' => 1 );
				$f[] = array( 'key' => 'password2', 'type' => 'password', 'label' => __( 'Confirm password', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'new-password', 'confirm' => 'password' );
				break;
			case 'lostpw':
				$f[] = array( 'key' => 'username', 'type' => 'text', 'label' => __( 'Email address or username', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'username' );
				break;
			case 'resetpw':
				$f[] = array( 'key' => 'password', 'type' => 'password', 'label' => __( 'New password', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'new-password', 'strength' => 1 );
				$f[] = array( 'key' => 'password2', 'type' => 'password', 'label' => __( 'Confirm new password', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'new-password', 'confirm' => 'password' );
				break;
			case 'profile':
				$f[] = array( 'key' => 'first_name', 'type' => 'text', 'label' => __( 'First name', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'given-name', 'width' => 'half' );
				$f[] = array( 'key' => 'last_name', 'type' => 'text', 'label' => __( 'Last name', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'family-name', 'width' => 'half' );
				$f[] = array( 'key' => 'display_name', 'type' => 'text', 'label' => __( 'Display name', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'nickname' );
				$f[] = array( 'key' => 'email', 'type' => 'email', 'label' => __( 'Email address', 'atelier-irisee-master-plugin' ), 'required' => 1, 'autocomplete' => 'email' );
				break;
		}
		return $f;
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * HTML of one field.
	 *
	 * @param array  $field  Definition.
	 * @param mixed  $value  Current value (for uploads: attachment ID).
	 * @param string $prefix Unique ID prefix for this form instance.
	 * @return string
	 */
	public static function render( $field, $value, $prefix ) {
		$field = wp_parse_args(
			$field,
			array(
				'type'         => 'text',
				'required'     => 0,
				'placeholder'  => '',
				'width'        => 'full',
				'autocomplete' => '',
				'options'      => array(),
				'map'          => '',
			)
		);
		$key         = $field['key'];
		$id          = $prefix . '-' . $key;
		$name        = 'aimp[' . $key . ']';
		$label_only  = 'placeholder' === AIMP_Login::opt( 'labels' ) && ! in_array( $field['type'], array( 'checkbox', 'radio', 'file', 'avatar' ), true );
		$placeholder = $field['placeholder'] ? $field['placeholder'] : ( $label_only ? $field['label'] : '' );
		$required    = $field['required'] ? ' required aria-required="true"' : '';
		$star        = $field['required'] ? ' <span class="aimp-el-required" aria-hidden="true">*</span>' : '';
		$label       = '<label class="aimp-el-label' . ( $label_only ? ' screen-reader-text' : '' ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . $star . '</label>';
		$common      = sprintf(
			' id="%1$s" name="%2$s"%3$s%4$s%5$s',
			esc_attr( $id ),
			esc_attr( $name ),
			$required,
			$field['autocomplete'] ? ' autocomplete="' . esc_attr( $field['autocomplete'] ) . '"' : '',
			$field['map'] ? ' data-aimp-map="' . esc_attr( $field['map'] ) . '"' : ''
		);
		$value       = is_scalar( $value ) ? (string) $value : '';
		$classes     = 'aimp-el-field aimp-el-field--' . $field['type'] . ( 'half' === $field['width'] ? ' aimp-el-field--half' : '' );
		$input       = '';

		switch ( $field['type'] ) {
			case 'textarea':
				$input = $label . '<textarea rows="3"' . $common . ' placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( $value ) . '</textarea>';
				break;

			case 'password':
				$input = $label . '<span class="aimp-el-password">' .
					'<input type="password"' . $common . ' placeholder="' . esc_attr( $placeholder ) . '"' .
					( ! empty( $field['strength'] ) ? ' data-aimp-strength' : '' ) .
					( ! empty( $field['confirm'] ) ? ' data-aimp-confirm="' . esc_attr( $prefix . '-' . $field['confirm'] ) . '"' : '' ) . '>' .
					'<button type="button" class="aimp-el-toggle-pw" aria-label="' . esc_attr__( 'Show password', 'atelier-irisee-master-plugin' ) . '" aria-pressed="false" aria-controls="' . esc_attr( $id ) . '">' .
					'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5C6.5 5 2.7 9.2 1.5 12c1.2 2.8 5 7 10.5 7s9.3-4.2 10.5-7C21.3 9.2 17.5 5 12 5zm0 11.5A4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg>' .
					'</button></span>' .
					( ! empty( $field['strength'] ) ? '<span class="aimp-el-strength" aria-live="polite"><span class="aimp-el-strength-bar"></span><span class="aimp-el-strength-text"></span></span>' : '' );
				break;

			case 'select':
			case 'role':
				$options = 'role' === $field['type'] ? self::role_options( $field ) : (array) $field['options'];
				$input   = $label . '<select' . $common . '><option value="">' . esc_html( $placeholder ? $placeholder : __( '— Choose —', 'atelier-irisee-master-plugin' ) ) . '</option>';
				foreach ( $options as $option => $option_label ) {
					$input .= '<option value="' . esc_attr( $option ) . '"' . selected( $value, (string) $option, false ) . '>' . esc_html( $option_label ) . '</option>';
				}
				$input .= '</select>';
				break;

			case 'radio':
				$input = '<fieldset class="aimp-el-radios"><legend class="aimp-el-label">' . esc_html( $field['label'] ) . $star . '</legend>';
				$i     = 0;
				foreach ( (array) $field['options'] as $option => $option_label ) {
					$input .= sprintf(
						'<label><input type="radio" name="%1$s" value="%2$s"%3$s%4$s> %5$s</label>',
						esc_attr( $name ),
						esc_attr( $option ),
						checked( $value, (string) $option, false ),
						0 === $i++ ? $required : '',
						esc_html( $option_label )
					);
				}
				$input .= '</fieldset>';
				break;

			case 'checkbox':
				$input = '<label class="aimp-el-check" for="' . esc_attr( $id ) . '"><input type="checkbox" value="1"' . $common . checked( '1', $value, false ) . '> ' . esc_html( $field['label'] ) . $star . '</label>';
				break;

			case 'file':
			case 'avatar':
				$current = '';
				if ( $value && wp_get_attachment_url( (int) $value ) ) {
					$current = 'avatar' === $field['type']
						? '<span class="aimp-el-avatar-preview">' . wp_get_attachment_image( (int) $value, array( 96, 96 ) ) . '</span>'
						: '<a class="aimp-el-file-current" href="' . esc_url( wp_get_attachment_url( (int) $value ) ) . '" target="_blank" rel="noopener">' . esc_html( basename( (string) get_attached_file( (int) $value ) ) ) . '</a>';
				}
				$accept = 'avatar' === $field['type'] ? 'image/jpeg,image/png,image/webp' : self::accept_attr( $field );
				$input  = $label . $current . '<input type="file" accept="' . esc_attr( $accept ) . '"' . sprintf( ' id="%1$s" name="aimp_file_%2$s"', esc_attr( $id ), esc_attr( $key ) ) . ( $field['required'] && ! $current ? ' required' : '' ) . ( 'avatar' === $field['type'] ? ' data-aimp-avatar' : '' ) . '>';
				break;

			default:
				$types = array(
					'email'  => 'email',
					'number' => 'number',
					'date'   => 'date',
					'phone'  => 'tel',
				);
				$type  = isset( $types[ $field['type'] ] ) ? $types[ $field['type'] ] : 'text';
				$input = $label . '<input type="' . esc_attr( $type ) . '"' . $common . ' value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( 'number' === $type ? ' step="any"' : '' ) . '>';
		}

		return '<p class="' . esc_attr( $classes ) . '" data-field="' . esc_attr( $key ) . '">' . $input . '<span class="aimp-el-field-error" role="alert"></span></p>';
	}

	private static function role_options( $field ) {
		$roles   = self::safe_roles();
		$allowed = array();
		foreach ( (array) $field['roles'] as $role ) {
			if ( isset( $roles[ $role ] ) ) {
				$allowed[ $role ] = $roles[ $role ];
			}
		}
		return $allowed;
	}

	private static function allowed_extensions( $field ) {
		if ( 'avatar' === $field['type'] ) {
			return array( 'jpg', 'jpeg', 'png', 'webp' );
		}
		$list = array_filter( array_map( 'trim', explode( ',', (string) $field['extensions'] ) ) );
		// Never allow executable or script uploads, whatever was configured.
		return array_values( array_diff( $list, array( 'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'js', 'html', 'htm', 'svg', 'exe', 'sh', 'bat' ) ) );
	}

	private static function accept_attr( $field ) {
		return implode(
			',',
			array_map(
				function ( $ext ) {
					return '.' . $ext;
				},
				self::allowed_extensions( $field )
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Values
	 * ------------------------------------------------------------------ */

	private static function meta_key( $field ) {
		if ( 'avatar' === $field['type'] ) {
			return self::AVATAR_META;
		}
		return ! empty( $field['map'] ) ? $field['map'] : 'aimp_field_' . $field['key'];
	}

	public static function value( $user_id, $field ) {
		if ( 'role' === $field['type'] ) {
			$user  = get_userdata( $user_id );
			$roles = $user ? array_intersect( (array) $user->roles, (array) $field['roles'] ) : array();
			return $roles ? reset( $roles ) : '';
		}
		return get_user_meta( $user_id, self::meta_key( $field ), true );
	}

	/**
	 * Validate and sanitize custom fields of a context from the request.
	 *
	 * @param string   $context  'register', 'profile' or 'admin'.
	 * @param int      $user_id  Existing user (0 at registration), to know whether a required upload already exists.
	 * @param WP_Error $errors   Errors are added here, keyed by field.
	 * @return array key => sanitized value (uploads are validated here and handled in save()).
	 */
	public static function collect( $context, $user_id, $errors ) {
		// phpcs:disable WordPress.Security.NonceVerification -- every caller verified a nonce.
		$input  = isset( $_POST['aimp'] ) && is_array( $_POST['aimp'] ) ? wp_unslash( $_POST['aimp'] ) : array();
		$values = array();
		foreach ( self::custom( $context ) as $field ) {
			$key      = $field['key'];
			$raw      = isset( $input[ $key ] ) ? $input[ $key ] : '';
			$raw      = is_array( $raw ) ? '' : trim( (string) $raw );
			$required = $field['required'] && 'admin' !== $context;

			if ( in_array( $field['type'], array( 'file', 'avatar' ), true ) ) {
				$file = isset( $_FILES[ 'aimp_file_' . $key ] ) ? $_FILES[ 'aimp_file_' . $key ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked below.
				if ( $file && UPLOAD_ERR_NO_FILE !== (int) $file['error'] ) {
					$problem = self::check_upload( $file, $field );
					if ( $problem ) {
						$errors->add( $key, $problem );
					} else {
						$values[ $key ] = array( 'upload' => 'aimp_file_' . $key );
					}
				} elseif ( $required && ! ( $user_id && self::value( $user_id, $field ) ) ) {
					$errors->add( $key, __( 'Please choose a file.', 'atelier-irisee-master-plugin' ) );
				}
				continue;
			}

			if ( '' === $raw ) {
				if ( $required ) {
					/* translators: %s: field label */
					$errors->add( $key, sprintf( __( '%s is required.', 'atelier-irisee-master-plugin' ), $field['label'] ) );
				}
				$values[ $key ] = '';
				continue;
			}

			$problem = '';
			switch ( $field['type'] ) {
				case 'email':
					$raw     = sanitize_email( $raw );
					$problem = is_email( $raw ) ? '' : __( 'Please enter a valid email address.', 'atelier-irisee-master-plugin' );
					break;
				case 'number':
					$problem = is_numeric( $raw ) ? '' : __( 'Please enter a number.', 'atelier-irisee-master-plugin' );
					break;
				case 'date':
					$date    = DateTime::createFromFormat( 'Y-m-d', $raw );
					$problem = ( $date && $date->format( 'Y-m-d' ) === $raw ) ? '' : __( 'Please enter a valid date.', 'atelier-irisee-master-plugin' );
					break;
				case 'phone':
					$problem = preg_match( '/^[0-9 +().\/-]{6,20}$/', $raw ) ? '' : __( 'Please enter a valid phone number.', 'atelier-irisee-master-plugin' );
					break;
				case 'select':
				case 'radio':
					$problem = array_key_exists( $raw, (array) $field['options'] ) ? '' : __( 'Please choose one of the options.', 'atelier-irisee-master-plugin' );
					break;
				case 'role':
					$problem = array_key_exists( $raw, self::role_options( $field ) ) ? '' : __( 'Please choose one of the options.', 'atelier-irisee-master-plugin' );
					break;
				case 'checkbox':
					$raw = '1';
					break;
				case 'textarea':
					$raw = sanitize_textarea_field( $raw );
					break;
				default:
					$raw = sanitize_text_field( $raw );
			}
			if ( $problem ) {
				$errors->add( $key, $problem );
				continue;
			}
			$values[ $key ] = 'textarea' === $field['type'] ? $raw : sanitize_text_field( $raw );
		}
		// phpcs:enable
		return $values;
	}

	/**
	 * Problem with an uploaded file, or '' when it is acceptable.
	 *
	 * @param array $file  $_FILES entry.
	 * @param array $field Definition.
	 * @return string
	 */
	private static function check_upload( $file, $field ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return __( 'The file could not be uploaded.', 'atelier-irisee-master-plugin' );
		}
		$max = ( 'avatar' === $field['type'] ? self::AVATAR_MAX_MB : self::FILE_MAX_MB ) * MB_IN_BYTES;
		if ( (int) $file['size'] > $max ) {
			/* translators: %d: size in MB */
			return sprintf( __( 'The file is too large (maximum %d MB).', 'atelier-irisee-master-plugin' ), $max / MB_IN_BYTES );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $check['ext'] ) || ! in_array( strtolower( $check['ext'] ), self::allowed_extensions( $field ), true ) ) {
			return __( 'This file type is not allowed.', 'atelier-irisee-master-plugin' );
		}
		return '';
	}

	/**
	 * Save collected values (and uploads) for a user.
	 *
	 * @param int    $user_id User.
	 * @param array  $values  From collect().
	 * @param string $context Context.
	 */
	public static function save( $user_id, $values, $context ) {
		foreach ( self::custom( $context ) as $field ) {
			$key = $field['key'];
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}
			$value = $values[ $key ];

			if ( is_array( $value ) && isset( $value['upload'] ) ) {
				$attachment = self::handle_upload( $value['upload'], $user_id );
				if ( $attachment ) {
					$old = (int) get_user_meta( $user_id, self::meta_key( $field ), true );
					update_user_meta( $user_id, self::meta_key( $field ), $attachment );
					if ( $old && $old !== $attachment && (int) get_post_field( 'post_author', $old ) === (int) $user_id ) {
						wp_delete_attachment( $old, true );
					}
				}
				continue;
			}

			if ( 'role' === $field['type'] ) {
				if ( 'admin' !== $context && $value && array_key_exists( $value, self::role_options( $field ) ) ) {
					$user = get_userdata( $user_id );
					if ( $user && ! user_can( $user, 'edit_posts' ) ) {
						$user->set_role( $value );
					}
				}
				continue;
			}

			update_user_meta( $user_id, self::meta_key( $field ), $value );
			// Mapped names also update the account name.
			if ( in_array( $field['map'], array( 'billing_first_name', 'billing_last_name' ), true ) && $value && ! get_user_meta( $user_id, str_replace( 'billing_', '', $field['map'] ), true ) ) {
				update_user_meta( $user_id, str_replace( 'billing_', '', $field['map'] ), $value );
			}
		}
	}

	private static function handle_upload( $file_key, $user_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$id = media_handle_upload( $file_key, 0, array( 'post_author' => $user_id ), array( 'test_form' => false ) );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/* ------------------------------------------------------------------
	 * Admin user profile
	 * ------------------------------------------------------------------ */

	public static function multipart_form_tag() {
		echo ' enctype="multipart/form-data"';
	}

	public static function admin_profile_fields( $user ) {
		$fields = self::custom( 'admin' );
		if ( ! $fields ) {
			return;
		}
		wp_nonce_field( 'aimp_admin_fields', 'aimp_admin_fields_nonce' );
		echo '<h2>' . esc_html__( 'Atelier Irisee fields', 'atelier-irisee-master-plugin' ) . '</h2><table class="form-table aimp-admin-fields" role="presentation">';
		foreach ( $fields as $field ) {
			echo '<tr><th>' . esc_html( $field['label'] ) . '</th><td>';
			echo self::render( array_merge( $field, array( 'required' => 0 ) ), self::value( $user->ID, $field ), 'aimp-admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
			echo '</td></tr>';
		}
		echo '</table>';
	}

	public static function admin_profile_save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['aimp_admin_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aimp_admin_fields_nonce'] ) ), 'aimp_admin_fields' ) ) {
			return;
		}
		$errors = new WP_Error();
		$values = self::collect( 'admin', $user_id, $errors );
		foreach ( $errors->get_error_codes() as $code ) {
			unset( $values[ $code ] );
		}
		self::save( $user_id, $values, 'admin' );
	}

	/* ------------------------------------------------------------------
	 * WooCommerce My Account > Account details
	 * ------------------------------------------------------------------ */

	public static function account_fields() {
		$user_id = get_current_user_id();
		foreach ( self::custom( 'profile' ) as $field ) {
			echo self::render( $field, self::value( $user_id, $field ), 'aimp-account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		}
	}

	/**
	 * @param WP_Error $errors WooCommerce errors.
	 * @param WP_User  $user   User being saved.
	 */
	public static function account_validate( $errors, $user ) {
		$own = new WP_Error();
		self::collect( 'profile', $user->ID, $own );
		foreach ( $own->get_error_messages() as $message ) {
			$errors->add( 'aimp_field', $message );
		}
	}

	public static function account_save( $user_id ) {
		$errors = new WP_Error();
		$values = self::collect( 'profile', $user_id, $errors );
		if ( ! $errors->has_errors() ) {
			self::save( $user_id, $values, 'profile' );
		}
	}

	/* ------------------------------------------------------------------
	 * Avatar
	 * ------------------------------------------------------------------ */

	public static function avatar_data( $args, $id_or_email ) {
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user    = get_user_by( 'email', $id_or_email );
			$user_id = $user ? $user->ID : 0;
		}
		$attachment = $user_id ? (int) get_user_meta( $user_id, self::AVATAR_META, true ) : 0;
		if ( $attachment ) {
			$size = isset( $args['size'] ) ? (int) $args['size'] : 96;
			$url  = wp_get_attachment_image_url( $attachment, array( $size, $size ) );
			if ( $url ) {
				$args['url']          = $url;
				$args['found_avatar'] = true;
			}
		}
		return $args;
	}
}
