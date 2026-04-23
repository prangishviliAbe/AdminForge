<?php
/**
 * Access restriction manager.
 *
 * @package AdminForge
 */

namespace AdminForge\Modules;

use AdminForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Access_Restriction_Manager {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Scanner.
	 *
	 * @var Menu_Scanner
	 */
	protected $scanner;

	/**
	 * Rules engine.
	 *
	 * @var Rules_Engine
	 */
	protected $rules;

	/**
	 * Restricted hook suffixes.
	 *
	 * @var array<int, string>
	 */
	protected $restricted_hooks = array();

	public function __construct( Settings $settings, Menu_Scanner $scanner, Rules_Engine $rules ) {
		$this->settings = $settings;
		$this->scanner  = $scanner;
		$this->rules    = $rules;
	}

	/**
	 * Register dynamic guards for hidden pages.
	 */
	public function register_dynamic_guards() {
		if ( ! $this->rules->should_apply() ) {
			return;
		}

		$settings = $this->settings->get_settings();
		$mode     = $settings['visibility']['menu_mode'];

		$top_selected    = array_map( 'sanitize_text_field', (array) $settings['visibility']['menu_items'] );
		$submenu_selected = array_map( 'sanitize_text_field', (array) $settings['visibility']['submenu_items'] );

		foreach ( (array) $settings['menu_inventory']['top_level'] as $item ) {
			$this->maybe_add_guard( $item, $top_selected, 'show_only' === $mode );
		}

		foreach ( (array) $settings['menu_inventory']['submenus'] as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				$item['parent'] = $parent;
				$this->maybe_add_guard( $item, $submenu_selected, 'show_only' === $mode );
			}
		}
	}

	/**
	 * Remove hidden menu and submenu items from the admin UI.
	 */
	public function apply_menu_visibility() {
		if ( ! $this->rules->should_apply() ) {
			return;
		}

		$settings = $this->settings->get_settings();
		$mode     = $settings['visibility']['menu_mode'];

		$top_selected = array_map( 'sanitize_text_field', (array) $settings['visibility']['menu_items'] );
		$sub_selected = array_map( 'sanitize_text_field', (array) $settings['visibility']['submenu_items'] );

		global $menu, $submenu;

		$runtime_top = array();
		$runtime_sub = array();

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( empty( $item[2] ) ) {
					continue;
				}

				$runtime_top[] = array(
					'slug' => (string) $item[2],
				);
			}
		}

		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				foreach ( (array) $items as $item ) {
					if ( empty( $item[2] ) ) {
						continue;
					}

					$runtime_sub[ (string) $parent ][] = array(
						'slug' => (string) $item[2],
					);
				}
			}
		}

		$this->remove_menu_items( $runtime_top, $top_selected, 'show_only' === $mode );
		$this->remove_submenu_items( $runtime_sub, $sub_selected, 'show_only' === $mode );
	}

	/**
	 * Remove top-level menu items.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param array<int, string> $selected Selected slugs.
	 * @param bool $show_only Whether selected items are the only visible ones.
	 */
	protected function remove_menu_items( array $items, array $selected, $show_only ) {
		foreach ( $items as $item ) {
			$slug = (string) ( $item['slug'] ?? '' );

			if ( '' === $slug || false !== strpos( strtolower( $slug ), 'adminforge' ) ) {
				continue;
			}

			$selected_match = $this->settings->menu_item_is_selected( $slug, $selected );
			$should_remove  = $show_only ? ! $selected_match : $selected_match;

			if ( $should_remove ) {
				remove_menu_page( $slug );
			}
		}
	}

	/**
	 * Remove submenu items.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $groups Groups.
	 * @param array<int, string> $selected Selected keys.
	 * @param bool $show_only Whether selected items are the only visible ones.
	 */
	protected function remove_submenu_items( array $groups, array $selected, $show_only ) {
		foreach ( $groups as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				$slug = (string) ( $item['slug'] ?? '' );

				if ( $this->is_canonical_post_type_screen( (string) $parent, $slug ) ) {
					continue;
				}

				if ( '' === $slug || false !== strpos( strtolower( $slug ), 'adminforge' ) ) {
					continue;
				}

				$selected_match = $this->is_linked_submenu( (string) $parent, $slug )
					? $this->settings->menu_item_is_selected( (string) $parent, $selected )
					: $this->settings->submenu_item_is_selected( (string) $parent, $slug, $selected );
				$should_remove  = $show_only ? ! $selected_match : $selected_match;

				if ( $should_remove ) {
					remove_submenu_page( (string) $parent, $slug );
				}
			}
		}
	}

	/**
	 * Add a guard for one item.
	 *
	 * @param array<string, mixed> $item Item.
	 * @param array<int, string>   $selected Selected items.
	 * @param bool                 $show_only Show-only mode.
	 */
	protected function maybe_add_guard( array $item, array $selected, $show_only ) {
		$slug = (string) $item['slug'];
		if ( false !== strpos( strtolower( $slug ), 'adminforge' ) ) {
			return;
		}

		if ( ! empty( $item['parent'] ) && $this->is_canonical_post_type_screen( (string) $item['parent'], $slug ) ) {
			return;
		}

		$selected_match = ! empty( $item['parent'] )
			? ( $this->is_linked_submenu( (string) $item['parent'], $slug )
				? $this->settings->menu_item_is_selected( (string) $item['parent'], $selected )
				: $this->settings->submenu_item_is_selected( (string) $item['parent'], $slug, $selected ) )
			: $this->settings->menu_item_is_selected( $slug, $selected );
		$hide = $show_only ? ! $selected_match : $selected_match;

		if ( ! $hide ) {
			return;
		}

		$hook_suffix = (string) ( $item['hook_suffix'] ?? '' );
		if ( '' === $hook_suffix || in_array( $hook_suffix, $this->restricted_hooks, true ) ) {
			return;
		}

		$this->restricted_hooks[] = $hook_suffix;
		add_action( 'load-' . $hook_suffix, array( $this, 'block_current_page' ) );
	}

	/**
	 * Block access during admin_init for requests without a load hook.
	 */
	public function maybe_block_direct_access() {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! $this->rules->should_apply() ) {
			return;
		}

		$settings = $this->settings->get_settings();

		if ( empty( $settings['visibility']['restrict_direct_access'] ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'adminforge' === $page ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $this->is_restricted_request( $page, $screen ) ) {
			$this->deny_access();
		}
	}

	/**
	 * Guard pages once screen information is available.
	 *
	 * @param object $screen Screen object.
	 */
	public function maybe_register_screen_guards( $screen ) {
		if ( ! $screen || ! $this->rules->should_apply() ) {
			return;
		}

		$settings = $this->settings->get_settings();

		if ( empty( $settings['visibility']['restrict_direct_access'] ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'adminforge' === $page ) {
			return;
		}

		if ( $this->is_restricted_request( $page, $screen ) ) {
			$this->deny_access();
		}
	}

	/**
	 * Guard current page on load hook.
	 */
	public function block_current_page() {
		$this->deny_access();
	}

	/**
	 * Determine whether the current request is restricted.
	 *
	 * @param string $page Page slug.
	 * @param object|null $screen Screen object.
	 * @return bool
	 */
	protected function is_restricted_request( $page, $screen ) {
		$settings = $this->settings->get_settings();
		$mode     = $settings['visibility']['menu_mode'];
		$top_selected = array_map( 'sanitize_text_field', (array) $settings['visibility']['menu_items'] );
		$sub_selected = array_map( 'sanitize_text_field', (array) $settings['visibility']['submenu_items'] );
		$screen_id    = $screen ? (string) $screen->id : '';
		$screen_base  = $screen ? (string) $screen->base : '';

		foreach ( (array) $settings['menu_inventory']['top_level'] as $item ) {
			if ( false !== strpos( strtolower( (string) $item['slug'] ), 'adminforge' ) ) {
				continue;
			}

			if ( $this->matches_item( $item, $page, $screen_id, $screen_base ) ) {
				$key = (string) $item['slug'];
				$selected_match = $this->settings->menu_item_is_selected( $key, $top_selected );
				return 'show_only' === $mode ? ! $selected_match : $selected_match;
			}
		}

		foreach ( (array) $settings['menu_inventory']['submenus'] as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				if ( false !== strpos( strtolower( (string) $item['slug'] ), 'adminforge' ) ) {
					continue;
				}

				if ( $this->is_canonical_post_type_screen( (string) $parent, (string) $item['slug'] ) ) {
					continue;
				}

				if ( $this->matches_item( array_merge( $item, array( 'parent' => $parent ) ), $page, $screen_id, $screen_base ) ) {
					$selected_match = $this->is_linked_submenu( (string) $parent, (string) $item['slug'] )
						? $this->settings->menu_item_is_selected( (string) $parent, $top_selected )
						: $this->settings->submenu_item_is_selected( (string) $parent, (string) $item['slug'], $sub_selected );
					return 'show_only' === $mode ? ! $selected_match : $selected_match;
				}
			}
		}

		return false;
	}

	/**
	 * Match request to inventory item.
	 *
	 * @param array<string, mixed> $item Item.
	 * @param string               $page Page slug.
	 * @param string               $screen_id Screen ID.
	 * @param string               $screen_base Screen base.
	 * @return bool
	 */
	protected function matches_item( array $item, $page, $screen_id, $screen_base ) {
		$slug = (string) $item['slug'];
		$hook = (string) ( $item['hook_suffix'] ?? '' );
		$base = sanitize_key( preg_replace( '/\.php.*$/', '', $slug ) );

		if ( '' !== $page && ( $slug === $page || false !== strpos( $slug, $page ) || false !== strpos( $page, $slug ) ) ) {
			return true;
		}

		if ( '' !== $screen_id && ( $hook === $screen_id || false !== strpos( $screen_id, $base ) || false !== strpos( $screen_id, sanitize_key( $slug ) ) ) ) {
			return true;
		}

		if ( '' !== $screen_base && ( $screen_base === $base || false !== strpos( $screen_base, $base ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether a submenu is the canonical screen for its parent post type menu.
	 *
	 * @param string $parent Parent slug.
	 * @param string $slug Submenu slug.
	 * @return bool
	 */
	protected function is_linked_submenu( $parent, $slug ) {
		return (string) $parent === (string) $slug;
	}

	/**
	 * Determine whether a slug is the canonical first screen for a post type menu.
	 *
	 * @param string $parent Parent slug.
	 * @param string $slug Submenu slug.
	 * @return bool
	 */
	protected function is_canonical_post_type_screen( $parent, $slug ) {
		$parent = (string) $parent;
		$slug   = (string) $slug;

		return $parent === $slug && false !== strpos( $slug, 'edit.php?post_type=' );
	}

	/**
	 * Deny access safely.
	 */
	protected function deny_access() {
		$settings = $this->settings->get_settings();
		$action   = isset( $settings['visibility']['access_action'] ) ? $settings['visibility']['access_action'] : 'redirect';
		$target   = isset( $settings['visibility']['redirect_target'] ) ? $settings['visibility']['redirect_target'] : 'index.php';
		$message  = isset( $settings['visibility']['deny_message'] ) ? $settings['visibility']['deny_message'] : __( 'Access denied.', 'adminforge' );

		if ( 'deny' === $action ) {
			wp_die( esc_html( $message ), esc_html__( 'Access denied', 'adminforge' ), array( 'response' => 403 ) );
		}

		wp_safe_redirect( admin_url( $target ) );
		exit;
	}
}
