<?php
/**
 * Settings storage and sanitization.
 *
 * @package AdminForge
 */

namespace AdminForge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	const OPTION_NAME = 'adminforge_settings';

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults() {
		return array(
			'general'         => array(
				'enabled'                => 1,
				'mode'                   => 'global',
				'target_roles'           => array(),
				'target_users'           => array(),
				'role_over_global'       => 1,
				'user_over_role'         => 1,
				'bypass_for_admins'      => 1,
			),
			'visibility'      => array(
				'menu_mode'               => 'hide_selected',
				'menu_items'              => array(),
				'submenu_items'           => array(),
				'dashboard_widgets'       => array(),
				'restrict_direct_access'  => 1,
				'access_action'           => 'redirect',
				'redirect_target'         => 'index.php',
				'deny_message'            => __( 'You do not have permission to access this admin page.', 'adminforge' ),
			),
			'ui'              => array(
				'sidebar_bg'        => '#111827',
				'sidebar_text'      => '#e5e7eb',
				'sidebar_accent'    => '#4f46e5',
				'content_bg'        => '#f8fafc',
				'content_text'      => '#0f172a',
				'font_family'       => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
				'menu_font_size'    => '14px',
				'menu_icon_size'    => '18px',
				'custom_css'        => '',
				'custom_js'         => '',
				'hide_wp_logo'      => 0,
				'hide_screen_options' => 0,
			),
			'branding'        => array(
				'custom_logo'      => '',
				'login_logo'       => '',
				'hide_branding'    => 0,
				'footer_text'      => '',
			),
			'advanced'        => array(
				'cache_inventory'  => 1,
				'enable_ajax'      => 1,
				'debug_mode'       => 0,
			),
			'menu_inventory'   => array(
				'collected_at' => '',
				'top_level'    => array(),
				'submenus'     => array(),
			),
			'dashboard_inventory' => array(
				'collected_at' => '',
				'widgets'      => array(),
			),
		);
	}

	/**
	 * Get all settings with defaults merged in.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$stored = get_option( self::OPTION_NAME, array() );
		return $this->merge_defaults( $stored );
	}

	/**
	 * Save settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public function save_settings( array $settings ) {
		return (bool) update_option( self::OPTION_NAME, $settings, false );
	}

	/**
	 * Merge defaults recursively.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public function merge_defaults( array $settings ) {
		return array_replace_recursive( $this->get_defaults(), $settings );
	}

	/**
	 * Sanitize full settings payload.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param array<string, mixed> $existing Existing settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input, array $existing = array() ) {
		$defaults = $this->get_defaults();
		$input    = wp_unslash( $input );

		$clean = $defaults;

		$clean['general']['enabled']           = empty( $input['general']['enabled'] ) ? 0 : 1;
		$clean['general']['mode']              = isset( $input['general']['mode'] ) ? $this->sanitize_allowed( $input['general']['mode'], array( 'global', 'roles', 'users', 'roles_users' ), 'global' ) : 'global';
		$clean['general']['target_roles']      = $this->sanitize_key_array( $input['general']['target_roles'] ?? array() );
		$clean['general']['target_users']      = $this->sanitize_absint_array( $input['general']['target_users'] ?? array() );
		$clean['general']['role_over_global']   = empty( $input['general']['role_over_global'] ) ? 0 : 1;
		$clean['general']['user_over_role']     = empty( $input['general']['user_over_role'] ) ? 0 : 1;
		$clean['general']['bypass_for_admins']  = empty( $input['general']['bypass_for_admins'] ) ? 0 : 1;

		$clean['visibility']['menu_mode']              = isset( $input['visibility']['menu_mode'] ) ? $this->sanitize_allowed( $input['visibility']['menu_mode'], array( 'hide_selected', 'show_only' ), 'hide_selected' ) : 'hide_selected';
		$clean['visibility']['menu_items']             = $this->sanitize_key_array( $input['visibility']['menu_items'] ?? array() );
		$clean['visibility']['submenu_items']          = $this->sanitize_key_array( $input['visibility']['submenu_items'] ?? array() );
		$clean['visibility']['dashboard_widgets']      = $this->sanitize_key_array( $input['visibility']['dashboard_widgets'] ?? array() );
		$clean['visibility']['restrict_direct_access'] = empty( $input['visibility']['restrict_direct_access'] ) ? 0 : 1;
		$clean['visibility']['access_action']          = isset( $input['visibility']['access_action'] ) ? $this->sanitize_allowed( $input['visibility']['access_action'], array( 'redirect', 'deny' ), 'redirect' ) : 'redirect';
		$clean['visibility']['redirect_target']        = isset( $input['visibility']['redirect_target'] ) ? sanitize_text_field( $input['visibility']['redirect_target'] ) : 'index.php';
		$clean['visibility']['deny_message']           = isset( $input['visibility']['deny_message'] ) ? sanitize_textarea_field( $input['visibility']['deny_message'] ) : $defaults['visibility']['deny_message'];

		$clean['ui']['sidebar_bg']          = isset( $input['ui']['sidebar_bg'] ) ? sanitize_hex_color( $input['ui']['sidebar_bg'] ) : $defaults['ui']['sidebar_bg'];
		$clean['ui']['sidebar_text']        = isset( $input['ui']['sidebar_text'] ) ? sanitize_hex_color( $input['ui']['sidebar_text'] ) : $defaults['ui']['sidebar_text'];
		$clean['ui']['sidebar_accent']      = isset( $input['ui']['sidebar_accent'] ) ? sanitize_hex_color( $input['ui']['sidebar_accent'] ) : $defaults['ui']['sidebar_accent'];
		$clean['ui']['content_bg']          = isset( $input['ui']['content_bg'] ) ? sanitize_hex_color( $input['ui']['content_bg'] ) : $defaults['ui']['content_bg'];
		$clean['ui']['content_text']        = isset( $input['ui']['content_text'] ) ? sanitize_hex_color( $input['ui']['content_text'] ) : $defaults['ui']['content_text'];
		$clean['ui']['font_family']         = isset( $input['ui']['font_family'] ) ? sanitize_text_field( $input['ui']['font_family'] ) : $defaults['ui']['font_family'];
		$clean['ui']['menu_font_size']      = isset( $input['ui']['menu_font_size'] ) ? sanitize_text_field( $input['ui']['menu_font_size'] ) : $defaults['ui']['menu_font_size'];
		$clean['ui']['menu_icon_size']      = isset( $input['ui']['menu_icon_size'] ) ? sanitize_text_field( $input['ui']['menu_icon_size'] ) : $defaults['ui']['menu_icon_size'];
		$clean['ui']['custom_css']          = isset( $input['ui']['custom_css'] ) ? sanitize_textarea_field( $input['ui']['custom_css'] ) : '';
		$clean['ui']['custom_js']           = isset( $input['ui']['custom_js'] ) ? sanitize_textarea_field( $input['ui']['custom_js'] ) : '';
		$clean['ui']['hide_wp_logo']        = empty( $input['ui']['hide_wp_logo'] ) ? 0 : 1;
		$clean['ui']['hide_screen_options']  = empty( $input['ui']['hide_screen_options'] ) ? 0 : 1;

		$clean['branding']['custom_logo']    = isset( $input['branding']['custom_logo'] ) ? esc_url_raw( $input['branding']['custom_logo'] ) : '';
		$clean['branding']['login_logo']     = isset( $input['branding']['login_logo'] ) ? esc_url_raw( $input['branding']['login_logo'] ) : '';
		$clean['branding']['hide_branding']  = empty( $input['branding']['hide_branding'] ) ? 0 : 1;
		$clean['branding']['footer_text']    = isset( $input['branding']['footer_text'] ) ? sanitize_textarea_field( $input['branding']['footer_text'] ) : '';

		$clean['advanced']['cache_inventory'] = empty( $input['advanced']['cache_inventory'] ) ? 0 : 1;
		$clean['advanced']['enable_ajax']     = empty( $input['advanced']['enable_ajax'] ) ? 0 : 1;
		$clean['advanced']['debug_mode']      = empty( $input['advanced']['debug_mode'] ) ? 0 : 1;

		$clean['menu_inventory']        = isset( $existing['menu_inventory'] ) ? $this->sanitize_menu_inventory( $existing['menu_inventory'] ) : $defaults['menu_inventory'];
		$clean['dashboard_inventory']   = isset( $existing['dashboard_inventory'] ) ? $this->sanitize_dashboard_inventory( $existing['dashboard_inventory'] ) : $defaults['dashboard_inventory'];

		return $clean;
	}

	/**
	 * Sanitize menu inventory.
	 *
	 * @param array<string, mixed> $inventory Inventory.
	 * @return array<string, mixed>
	 */
	public function sanitize_menu_inventory( array $inventory ) {
		$clean = array(
			'collected_at' => isset( $inventory['collected_at'] ) ? sanitize_text_field( $inventory['collected_at'] ) : '',
			'top_level'    => array(),
			'submenus'     => array(),
		);

		foreach ( (array) ( $inventory['top_level'] ?? array() ) as $item ) {
			$clean['top_level'][] = $this->sanitize_menu_item( $item );
		}

		foreach ( (array) ( $inventory['submenus'] ?? array() ) as $parent_slug => $items ) {
			$parent_slug = sanitize_text_field( $parent_slug );
			$clean['submenus'][ $parent_slug ] = array();
			foreach ( (array) $items as $item ) {
				$clean['submenus'][ $parent_slug ][] = $this->sanitize_menu_item( $item );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize dashboard inventory.
	 *
	 * @param array<string, mixed> $inventory Inventory.
	 * @return array<string, mixed>
	 */
	public function sanitize_dashboard_inventory( array $inventory ) {
		$clean = array(
			'collected_at' => isset( $inventory['collected_at'] ) ? sanitize_text_field( $inventory['collected_at'] ) : '',
			'widgets'      => array(),
		);

		foreach ( (array) ( $inventory['widgets'] ?? array() ) as $widget ) {
			$clean['widgets'][] = array(
				'id'       => sanitize_key( $widget['id'] ?? '' ),
				'title'    => sanitize_text_field( $widget['title'] ?? '' ),
				'context'  => sanitize_key( $widget['context'] ?? '' ),
				'priority' => sanitize_key( $widget['priority'] ?? '' ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize one menu item.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return array<string, mixed>
	 */
	protected function sanitize_menu_item( array $item ) {
		return array(
			'title'      => sanitize_text_field( $item['title'] ?? '' ),
			'slug'       => sanitize_text_field( $item['slug'] ?? '' ),
			'parent'     => sanitize_text_field( $item['parent'] ?? '' ),
			'capability'  => sanitize_text_field( $item['capability'] ?? '' ),
			'hook_suffix' => sanitize_text_field( $item['hook_suffix'] ?? '' ),
			'screen_id'   => sanitize_text_field( $item['screen_id'] ?? '' ),
			'icon'       => sanitize_text_field( $item['icon'] ?? '' ),
			'source'     => sanitize_text_field( $item['source'] ?? 'custom' ),
		);
	}

	/**
	 * Sanitize allowed value.
	 *
	 * @param string $value Value.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string $default Default.
	 * @return string
	 */
	protected function sanitize_allowed( $value, array $allowed, $default ) {
		$value = sanitize_key( $value );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Sanitize array of keys.
	 *
	 * @param mixed $values Values.
	 * @return array<int, string>
	 */
	protected function sanitize_key_array( $values ) {
		$values = is_array( $values ) ? $values : explode( ',', (string) $values );
		$values = array_map( 'sanitize_key', array_filter( array_map( 'trim', $values ) ) );
		return array_values( array_unique( $values ) );
	}

	/**
	 * Sanitize array of absint.
	 *
	 * @param mixed $values Values.
	 * @return array<int, int>
	 */
	protected function sanitize_absint_array( $values ) {
		$values = is_array( $values ) ? $values : explode( ',', (string) $values );
		$values = array_map( 'absint', array_filter( array_map( 'trim', $values ) ) );
		return array_values( array_unique( array_filter( $values ) ) );
	}

	/**
	 * Get role labels.
	 *
	 * @return array<string, string>
	 */
	public function get_role_labels() {
		$roles = wp_roles();
		return $roles ? $roles->get_names() : array();
	}

	/**
	 * Search users by term.
	 *
	 * @param string $term Search term.
	 * @param int    $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function search_users( $term = '', $limit = 20 ) {
		$args = array(
			'number'  => $limit,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => 'all',
		);

		if ( '' !== $term ) {
			$args['search']         = '*' . $term . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$users = get_users( $args );
		$data  = array();

		foreach ( $users as $user ) {
			$data[] = array(
				'id'    => (int) $user->ID,
				'label' => sprintf( '%s (%s)', $user->display_name, $user->user_login ),
			);
		}

		return $data;
	}
}
