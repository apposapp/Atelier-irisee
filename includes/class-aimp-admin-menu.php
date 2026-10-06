<?php
/**
 * Admin menu (WooCommerce → Atelier Irisee → Admin menu): the dashboard's left menu set up per role or
 * per person: which items and submenu items are shown, and in which order.
 *
 * One set-up ("profile") per role or person, plus "Everyone" for anyone without their own. A person's own
 * profile wins, then the first of their roles that has one, then Everyone.
 *
 * Option aimp_admin_menu: [ profiles: [ "everyone" | "role:{role}" | "user:{id}" => [ order: [ slug ],
 * sub_order: [ parent => [ slug ] ], hidden: [ slug ], sub_hidden: [ parent => [ slug ] ] ] ] ].
 *
 * Hiding only takes items out of the menu; the pages stay reachable by their address. WooCommerce and
 * WooCommerce → Atelier Irisee (these settings) are never hidden for people who can manage the shop.
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Admin_Menu {

	const OPTION = 'aimp_admin_menu';

	/** The menu and submenus before anything is hidden, for the editor. */
	private static $full     = array();
	private static $full_sub = array();

	/** The profile of the current user (cached per request). */
	private static $current = null;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'remember_menu' ), 9998 );
		add_action( 'admin_menu', array( __CLASS__, 'hide_items' ), 9999 );
		add_filter( 'custom_menu_order', array( __CLASS__, 'has_order' ) );
		add_filter( 'menu_order', array( __CLASS__, 'menu_order' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/* ------------------------------------------------------------------
	 * Profiles
	 * ------------------------------------------------------------------ */

	private static function empty_profile() {
		return array(
			'order'      => array(),
			'sub_order'  => array(),
			'hidden'     => array(),
			'sub_hidden' => array(),
		);
	}

	/**
	 * All profiles. Settings saved by 2.9.0 (one order, and per item roles/users) are converted.
	 *
	 * @return array[]
	 */
	public static function profiles() {
		$raw = (array) get_option( self::OPTION, array() );
		if ( isset( $raw['profiles'] ) && is_array( $raw['profiles'] ) ) {
			return $raw['profiles'];
		}
		$profiles = array();
		if ( ! empty( $raw['order'] ) ) {
			$profiles['everyone']          = self::empty_profile();
			$profiles['everyone']['order'] = array_values( (array) $raw['order'] );
		}
		foreach ( (array) ( isset( $raw['hidden'] ) ? $raw['hidden'] : array() ) as $slug => $rule ) {
			$keys = array();
			foreach ( (array) ( isset( $rule['roles'] ) ? $rule['roles'] : array() ) as $role ) {
				$keys[] = 'role:' . $role;
			}
			foreach ( (array) ( isset( $rule['users'] ) ? $rule['users'] : array() ) as $user ) {
				$keys[] = 'user:' . (int) $user;
			}
			foreach ( $keys as $key ) {
				if ( ! isset( $profiles[ $key ] ) ) {
					$profiles[ $key ] = self::empty_profile();
				}
				$profiles[ $key ]['hidden'][] = (string) $slug;
			}
		}
		return $profiles;
	}

	/**
	 * The profile that applies to the current user.
	 *
	 * @return array
	 */
	private static function current_profile() {
		if ( null !== self::$current ) {
			return self::$current;
		}
		$profiles = self::profiles();
		$user     = wp_get_current_user();
		$keys     = array( 'user:' . (int) $user->ID );
		foreach ( (array) $user->roles as $role ) {
			$keys[] = 'role:' . $role;
		}
		$keys[]        = 'everyone';
		self::$current = self::empty_profile();
		foreach ( $keys as $key ) {
			if ( isset( $profiles[ $key ] ) && is_array( $profiles[ $key ] ) ) {
				self::$current = wp_parse_args( $profiles[ $key ], self::empty_profile() );
				break;
			}
		}
		return self::$current;
	}

	/**
	 * Items that can never be hidden for people who can manage the shop: main slugs and "parent>sub".
	 *
	 * @return string[]
	 */
	private static function locked() {
		return array( 'woocommerce', 'woocommerce>' . AIMP_Settings::PAGE );
	}

	private static function is_locked( $key ) {
		return in_array( $key, self::locked(), true ) && current_user_can( 'manage_woocommerce' );
	}

	/* ------------------------------------------------------------------
	 * Applying it
	 * ------------------------------------------------------------------ */

	public static function remember_menu() {
		global $menu, $submenu;
		self::$full     = is_array( $menu ) ? $menu : array();
		self::$full_sub = is_array( $submenu ) ? $submenu : array();
	}

	public static function hide_items() {
		$profile = self::current_profile();
		foreach ( (array) $profile['hidden'] as $slug ) {
			if ( ! self::is_locked( (string) $slug ) ) {
				remove_menu_page( (string) $slug );
			}
		}
		foreach ( (array) $profile['sub_hidden'] as $parent => $subs ) {
			foreach ( (array) $subs as $sub ) {
				if ( ! self::is_locked( $parent . '>' . $sub ) ) {
					remove_submenu_page( (string) $parent, (string) $sub );
				}
			}
		}
	}

	public static function has_order( $custom ) {
		$profile = self::current_profile();
		return ( $profile['order'] || $profile['sub_order'] ) ? true : $custom;
	}

	/**
	 * Saved order first; items that are not in it (new plugins) follow in their own order.
	 *
	 * @param string[] $saved   Saved slugs.
	 * @param string[] $current Current slugs.
	 * @return string[]
	 */
	private static function sort_slugs( $saved, $current ) {
		$sorted = array_values( array_intersect( (array) $saved, $current ) );
		return array_values( array_unique( array_merge( $sorted, array_diff( $current, $sorted ) ) ) );
	}

	/**
	 * The main order, and (after WooCommerce has sorted its own submenu) the submenu orders.
	 *
	 * @param string[] $order Menu slugs.
	 * @return string[]
	 */
	public static function menu_order( $order ) {
		global $submenu;
		$profile = self::current_profile();
		foreach ( (array) $profile['sub_order'] as $parent => $saved ) {
			if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
				continue;
			}
			$by_slug = array();
			foreach ( $submenu[ $parent ] as $entry ) {
				$by_slug[ (string) $entry[2] ] = $entry;
			}
			$sorted = array();
			foreach ( self::sort_slugs( $saved, array_keys( $by_slug ) ) as $slug ) {
				$sorted[] = $by_slug[ $slug ];
			}
			$submenu[ $parent ] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering the submenu is the point.
		}
		if ( ! $profile['order'] || ! is_array( $order ) ) {
			return $order;
		}
		return self::sort_slugs( $profile['order'], $order );
	}

	/* ------------------------------------------------------------------
	 * Settings tab
	 * ------------------------------------------------------------------ */

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
		add_settings_section( 'aimp_admin_menu', __( 'Admin menu', 'atelier-irisee-master-plugin' ), array( __CLASS__, 'render_editor' ), AIMP_Settings::PAGE );
	}

	public static function enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . AIMP_Settings::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'aimp-admin-menu', AIMP_PLUGIN_URL . 'assets/js/admin-menu.js', array( 'jquery', 'jquery-ui-sortable', 'wc-enhanced-select' ), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-menu',
			'aimpAdminMenu',
			array(
				'resetConfirm' => __( 'Put the menu back in its normal order and show every item to everyone?', 'atelier-irisee-master-plugin' ),
				'own'          => __( 'Has its own set-up', 'atelier-irisee-master-plugin' ),
				'usesDefault'  => __( 'Uses the Everyone set-up', 'atelier-irisee-master-plugin' ),
				/* translators: %d: number of submenu items */
				'submenu'      => __( 'Submenu (%d)', 'atelier-irisee-master-plugin' ),
				/* translators: %d: number of hidden submenu items */
				'hiddenCount'  => __( '%d hidden', 'atelier-irisee-master-plugin' ),
				'divider'      => __( 'Divider', 'atelier-irisee-master-plugin' ),
				'drag'         => __( 'Drag to move', 'atelier-irisee-master-plugin' ),
				'up'           => __( 'Move up', 'atelier-irisee-master-plugin' ),
				'down'         => __( 'Move down', 'atelier-irisee-master-plugin' ),
				'toggle'       => __( 'Show or hide', 'atelier-irisee-master-plugin' ),
				'locked'       => __( 'Always visible for shop managers and administrators.', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	private static function clean_title( $title ) {
		// "Comments <span class=…>3</span>" → "Comments".
		return trim( wp_strip_all_tags( preg_replace( '#<span[^>]*>.*?</span>#s', '', (string) $title ) ) );
	}

	private static function icon_html( $icon ) {
		if ( 0 === strpos( $icon, 'dashicons-' ) ) {
			return '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
		}
		if ( 0 === strpos( $icon, 'data:image' ) || 0 === strpos( $icon, 'http' ) ) {
			return '<span class="aimp-am-img" style="background-image:url(\'' . esc_attr( $icon ) . '\')"></span>';
		}
		return '<span class="dashicons dashicons-admin-generic"></span>';
	}

	/**
	 * The menu as the editor shows it, in WordPress's own order: [ slug, title, icon, separator, subs ].
	 *
	 * @return array[]
	 */
	private static function structure() {
		$items = array();
		foreach ( self::$full as $entry ) {
			if ( empty( $entry[2] ) ) {
				continue;
			}
			$slug = (string) $entry[2];
			$subs = array();
			foreach ( isset( self::$full_sub[ $slug ] ) ? (array) self::$full_sub[ $slug ] : array() as $sub ) {
				if ( ! empty( $sub[2] ) ) {
					$subs[] = array(
						'slug'  => (string) $sub[2],
						'title' => self::clean_title( $sub[0] ),
					);
				}
			}
			$items[] = array(
				'slug'      => $slug,
				'title'     => self::clean_title( $entry[0] ),
				'icon'      => self::icon_html( isset( $entry[6] ) ? (string) $entry[6] : '' ),
				'separator' => isset( $entry[4] ) && false !== strpos( (string) $entry[4], 'wp-menu-separator' ),
				'subs'      => $subs,
			);
		}
		return $items;
	}

	public static function render_editor() {
		$profiles = self::profiles();
		$people   = array();
		foreach ( array_keys( $profiles ) as $key ) {
			if ( 0 === strpos( $key, 'user:' ) ) {
				$user = get_userdata( (int) substr( $key, 5 ) );
				if ( $user ) {
					$people[ $key ] = $user->display_name . ' (' . $user->user_email . ')';
				}
			}
		}
		$data = array(
			'structure' => self::structure(),
			'profiles'  => (object) $profiles,
			'locked'    => self::locked(),
		);

		echo '<p>' . esc_html__( 'Choose a role or a person at the top, then set up the menu for them: drag the items into order and click the eye to hide an item. Items with a submenu fold open. People without their own set-up use the one of their role, or else Everyone. Hiding only takes items out of the menu; the pages stay reachable.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<div class="aimp-am" data-aimp-am>';
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[reset]" value="" data-aimp-am-reset-field>';
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[json]" value="" data-aimp-am-json>';
		echo '<script type="application/json" data-aimp-am-data>' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';

		echo '<div class="aimp-am-bar">';
		echo '<label for="aimp-am-target" class="aimp-am-bar-label">' . esc_html__( 'Menu for:', 'atelier-irisee-master-plugin' ) . '</label>';
		echo '<select id="aimp-am-target" data-aimp-am-target>';
		echo '<option value="everyone">' . esc_html__( 'Everyone (default)', 'atelier-irisee-master-plugin' ) . '</option>';
		echo '<optgroup label="' . esc_attr__( 'Roles', 'atelier-irisee-master-plugin' ) . '">';
		foreach ( wp_roles()->get_names() as $role => $label ) {
			echo '<option value="' . esc_attr( 'role:' . $role ) . '">' . esc_html( translate_user_role( $label ) ) . '</option>';
		}
		echo '</optgroup><optgroup label="' . esc_attr__( 'People', 'atelier-irisee-master-plugin' ) . '" data-aimp-am-people>';
		foreach ( $people as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</optgroup></select>';
		printf(
			'<select class="wc-customer-search aimp-am-person" data-aimp-am-person data-placeholder="%s" data-allow_clear="true" style="width:260px"></select>',
			esc_attr__( 'Add a person…', 'atelier-irisee-master-plugin' )
		);
		echo '<span class="aimp-am-status" data-aimp-am-status></span>';
		echo '<span class="aimp-am-bar-buttons">';
		echo '<button type="button" class="button" data-aimp-am-copy>' . esc_html__( 'Copy from Everyone', 'atelier-irisee-master-plugin' ) . '</button> ';
		echo '<button type="button" class="button-link aimp-am-remove" data-aimp-am-remove>' . esc_html__( 'Remove this set-up', 'atelier-irisee-master-plugin' ) . '</button>';
		echo '</span></div>';

		echo '<ol class="aimp-am-list" data-aimp-am-list></ol>';
		echo '<p><button type="button" class="button" data-aimp-am-reset>' . esc_html__( 'Reset to default', 'atelier-irisee-master-plugin' ) . '</button></p>';
		echo '</div>';
	}

	/**
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = (array) $input;
		if ( ! empty( $input['reset'] ) ) {
			return array( 'profiles' => array() );
		}
		// The first save of a new option runs this twice; the second time it gets the cleaned value.
		if ( isset( $input['profiles'] ) && is_array( $input['profiles'] ) && ! isset( $input['json'] ) ) {
			return array( 'profiles' => $input['profiles'] );
		}
		if ( ! isset( $input['json'] ) || '' === $input['json'] ) {
			return array( 'profiles' => self::profiles() ); // The tab was not used: keep what is saved.
		}
		$decoded = json_decode( wp_unslash( $input['json'] ), true );
		if ( ! is_array( $decoded ) ) {
			return array( 'profiles' => self::profiles() );
		}
		$roles    = array_keys( wp_roles()->get_names() );
		$slug     = function ( $value ) {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		};
		$list     = function ( $values ) use ( $slug ) {
			return array_values( array_unique( array_filter( array_map( $slug, is_array( $values ) ? $values : array() ) ) ) );
		};
		$profiles = array();
		foreach ( $decoded as $key => $profile ) {
			$key   = (string) $key;
			$valid = 'everyone' === $key
				|| ( 0 === strpos( $key, 'role:' ) && in_array( substr( $key, 5 ), $roles, true ) )
				|| ( 0 === strpos( $key, 'user:' ) && get_userdata( (int) substr( $key, 5 ) ) );
			if ( ! $valid || ! is_array( $profile ) ) {
				continue;
			}
			$clean = array(
				'order'      => $list( isset( $profile['order'] ) ? $profile['order'] : array() ),
				'sub_order'  => array(),
				'hidden'     => $list( isset( $profile['hidden'] ) ? $profile['hidden'] : array() ),
				'sub_hidden' => array(),
			);
			foreach ( array( 'sub_order', 'sub_hidden' ) as $part ) {
				foreach ( (array) ( isset( $profile[ $part ] ) ? $profile[ $part ] : array() ) as $parent => $subs ) {
					$parent = $slug( $parent );
					$subs   = $list( $subs );
					if ( '' !== $parent && $subs ) {
						$clean[ $part ][ $parent ] = $subs;
					}
				}
			}
			$profiles[ $key ] = $clean;
		}
		return array( 'profiles' => $profiles );
	}
}
