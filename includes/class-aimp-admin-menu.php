<?php
/**
 * Admin menu (WooCommerce → Atelier Irisee → Admin menu): the order of the dashboard's left menu, and
 * which items are hidden for chosen roles or users.
 *
 * Hiding only takes the item out of the menu; its pages stay reachable by their address. WooCommerce
 * (where these settings live) is never hidden for people who can manage the shop.
 *
 * Option aimp_admin_menu: [ order: [ slug, … ], hidden: [ slug => [ roles: [], users: [] ] ] ].
 *
 * @package AtelierIriseeMasterPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMP_Admin_Menu {

	const OPTION = 'aimp_admin_menu';

	/** The menu before anything is hidden, for the editor. */
	private static $full = array();

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'remember_menu' ), 9998 );
		add_action( 'admin_menu', array( __CLASS__, 'hide_items' ), 9999 );
		add_filter( 'custom_menu_order', array( __CLASS__, 'has_order' ) );
		add_filter( 'menu_order', array( __CLASS__, 'menu_order' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * @return array [ order, hidden ]
	 */
	public static function options() {
		$options = wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'order'  => array(),
				'hidden' => array(),
			)
		);
		$options['order']  = array_values( array_filter( (array) $options['order'], 'is_string' ) );
		$options['hidden'] = (array) $options['hidden'];
		return $options;
	}

	/* ------------------------------------------------------------------
	 * Applying it
	 * ------------------------------------------------------------------ */

	public static function remember_menu() {
		global $menu;
		self::$full = is_array( $menu ) ? $menu : array();
	}

	/**
	 * Is this item hidden for the current user?
	 *
	 * @param string $slug Menu slug.
	 * @return bool
	 */
	private static function hidden_for_current_user( $slug ) {
		$rules = self::options()['hidden'];
		if ( empty( $rules[ $slug ] ) || ! is_array( $rules[ $slug ] ) ) {
			return false;
		}
		// Never lock shop managers out of WooCommerce (and these settings).
		if ( 'woocommerce' === $slug && current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		$user  = wp_get_current_user();
		$roles = isset( $rules[ $slug ]['roles'] ) ? (array) $rules[ $slug ]['roles'] : array();
		$users = isset( $rules[ $slug ]['users'] ) ? array_map( 'intval', (array) $rules[ $slug ]['users'] ) : array();
		return (bool) array_intersect( (array) $user->roles, $roles ) || in_array( (int) $user->ID, $users, true );
	}

	public static function hide_items() {
		foreach ( array_keys( self::options()['hidden'] ) as $slug ) {
			if ( self::hidden_for_current_user( (string) $slug ) ) {
				remove_menu_page( (string) $slug );
			}
		}
	}

	public static function has_order( $custom ) {
		return self::options()['order'] ? true : $custom;
	}

	/**
	 * The saved order first; items that are not in it (new plugins) follow in their own order.
	 *
	 * @param string[] $order Menu slugs.
	 * @return string[]
	 */
	public static function menu_order( $order ) {
		$saved = self::options()['order'];
		if ( ! $saved || ! is_array( $order ) ) {
			return $order;
		}
		$sorted = array_values( array_intersect( $saved, $order ) );
		return array_values( array_unique( array_merge( $sorted, array_diff( $order, $sorted ) ) ) );
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
		wp_enqueue_script( 'aimp-admin-menu', AIMP_PLUGIN_URL . 'assets/js/admin-menu.js', array( 'jquery', 'jquery-ui-sortable' ), AIMP_VERSION, true );
		wp_localize_script(
			'aimp-admin-menu',
			'aimpAdminMenu',
			array(
				'resetConfirm' => __( 'Put the menu back in its normal order and show every item to everyone?', 'atelier-irisee-master-plugin' ),
			)
		);
	}

	/**
	 * Menu items for the editor, in the order they will have: [ slug, title, icon, separator ].
	 *
	 * @return array[]
	 */
	private static function items() {
		$items = array();
		foreach ( self::$full as $entry ) {
			if ( empty( $entry[2] ) ) {
				continue;
			}
			$separator = isset( $entry[4] ) && false !== strpos( (string) $entry[4], 'wp-menu-separator' );
			// "Comments <span class=…>3</span>" → "Comments".
			$title = trim( wp_strip_all_tags( preg_replace( '#<span[^>]*>.*?</span>#s', '', (string) $entry[0] ) ) );
			$items[ (string) $entry[2] ] = array(
				'slug'      => (string) $entry[2],
				'title'     => $title,
				'icon'      => isset( $entry[6] ) ? (string) $entry[6] : '',
				'separator' => $separator,
			);
		}
		$order = self::menu_order( array_keys( $items ) );
		$out   = array();
		foreach ( $order as $slug ) {
			if ( isset( $items[ $slug ] ) ) {
				$out[] = $items[ $slug ];
			}
		}
		return $out;
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

	public static function render_editor() {
		$options = self::options();
		$roles   = wp_roles()->get_names();
		echo '<p>' . esc_html__( 'Drag the items into the order you like. Click the eye to hide an item for chosen roles or people. Hiding only takes the item out of the menu; the pages themselves stay reachable. WooCommerce stays visible for shop managers, so nobody locks themselves out of these settings.', 'atelier-irisee-master-plugin' ) . '</p>';
		echo '<div class="aimp-am" data-aimp-am>';
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[reset]" value="" data-aimp-am-reset-field>';
		echo '<ol class="aimp-am-list" data-aimp-am-list>';
		foreach ( self::items() as $i => $item ) {
			$name  = self::OPTION . '[items][' . $i . ']';
			$rule  = isset( $options['hidden'][ $item['slug'] ] ) ? (array) $options['hidden'][ $item['slug'] ] : array();
			$hide  = ! empty( $rule );
			$keep  = 'woocommerce' === $item['slug'];
			printf( '<li class="aimp-am-item%1$s%2$s" data-aimp-am-item>', $item['separator'] ? ' is-separator' : '', $hide ? ' is-hidden' : '' );
			printf( '<input type="hidden" name="%1$s[slug]" value="%2$s">', esc_attr( $name ), esc_attr( $item['slug'] ) );
			echo '<div class="aimp-am-row">';
			echo '<span class="aimp-am-handle dashicons dashicons-move" aria-hidden="true" title="' . esc_attr__( 'Drag to move', 'atelier-irisee-master-plugin' ) . '"></span>';
			if ( $item['separator'] ) {
				echo '<span class="aimp-am-title aimp-am-separator-label">' . esc_html__( 'Divider', 'atelier-irisee-master-plugin' ) . '</span>';
			} else {
				echo self::icon_html( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_html().
				echo '<span class="aimp-am-title">' . esc_html( '' !== $item['title'] ? $item['title'] : $item['slug'] ) . '</span>';
			}
			echo '<span class="aimp-am-buttons">';
			printf( '<button type="button" class="button-link aimp-am-move" data-aimp-am-move="-1" aria-label="%s"><span class="dashicons dashicons-arrow-up-alt2"></span></button>', esc_attr__( 'Move up', 'atelier-irisee-master-plugin' ) );
			printf( '<button type="button" class="button-link aimp-am-move" data-aimp-am-move="1" aria-label="%s"><span class="dashicons dashicons-arrow-down-alt2"></span></button>', esc_attr__( 'Move down', 'atelier-irisee-master-plugin' ) );
			if ( ! $item['separator'] ) {
				printf(
					'<label class="aimp-am-eye" title="%3$s"><input type="checkbox" name="%1$s[hide]" value="1" data-aimp-am-toggle %2$s><span class="dashicons %4$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span></label>',
					esc_attr( $name ),
					checked( $hide, true, false ),
					esc_attr__( 'Hide for chosen roles or people', 'atelier-irisee-master-plugin' ),
					$hide ? 'dashicons-hidden' : 'dashicons-visibility'
				);
			}
			echo '</span></div>';

			if ( ! $item['separator'] ) {
				echo '<div class="aimp-am-rules"' . ( $hide ? '' : ' hidden' ) . '>';
				if ( $keep ) {
					echo '<p class="description">' . esc_html__( 'Always visible for shop managers and administrators.', 'atelier-irisee-master-plugin' ) . '</p>';
				}
				echo '<p class="aimp-am-label">' . esc_html__( 'Hidden for these roles', 'atelier-irisee-master-plugin' ) . '</p><div class="aimp-am-roles">';
				foreach ( $roles as $role => $label ) {
					printf(
						'<label><input type="checkbox" name="%1$s[roles][]" value="%2$s" %3$s> %4$s</label>',
						esc_attr( $name ),
						esc_attr( $role ),
						checked( in_array( $role, isset( $rule['roles'] ) ? (array) $rule['roles'] : array(), true ), true, false ),
						esc_html( translate_user_role( $label ) )
					);
				}
				echo '</div><p class="aimp-am-label">' . esc_html__( 'Hidden for these people', 'atelier-irisee-master-plugin' ) . '</p>';
				printf(
					'<select class="wc-customer-search" multiple="multiple" name="%1$s[users][]" data-placeholder="%2$s" data-allow_clear="true" style="width:100%%;max-width:520px">',
					esc_attr( $name ),
					esc_attr__( 'Search for a person…', 'atelier-irisee-master-plugin' )
				);
				foreach ( isset( $rule['users'] ) ? (array) $rule['users'] : array() as $user_id ) {
					$user = get_userdata( (int) $user_id );
					if ( $user ) {
						printf( '<option value="%1$d" selected>%2$s</option>', (int) $user->ID, esc_html( $user->display_name . ' (' . $user->user_email . ')' ) );
					}
				}
				echo '</select></div>';
			}
			echo '</li>';
		}
		echo '</ol>';
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
			return array(
				'order'  => array(),
				'hidden' => array(),
			);
		}
		if ( ! isset( $input['items'] ) ) {
			return self::options(); // The tab was not on the page: keep what is saved.
		}
		$roles = array_keys( wp_roles()->get_names() );
		$clean = array(
			'order'  => array(),
			'hidden' => array(),
		);
		foreach ( (array) $input['items'] as $item ) {
			$slug = isset( $item['slug'] ) ? sanitize_text_field( wp_unslash( $item['slug'] ) ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$clean['order'][] = $slug;
			if ( empty( $item['hide'] ) ) {
				continue;
			}
			$item_roles = array_values( array_intersect( array_map( 'sanitize_key', (array) ( isset( $item['roles'] ) ? $item['roles'] : array() ) ), $roles ) );
			$item_users = array_values( array_filter( array_map( 'absint', (array) ( isset( $item['users'] ) ? $item['users'] : array() ) ) ) );
			if ( $item_roles || $item_users ) {
				$clean['hidden'][ $slug ] = array(
					'roles' => $item_roles,
					'users' => $item_users,
				);
			}
		}
		return $clean;
	}
}
