<?php
/**
 * Menu scanner.
 *
 * @package AdminForge
 */

namespace AdminForge\Modules;

use AdminForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu_Scanner {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Return menu inventory from settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_inventory() {
		$settings = $this->settings->get_settings();
		return $settings['menu_inventory'];
	}

	/**
	 * Store inventory in settings.
	 *
	 * @param array<string, mixed> $inventory Inventory.
	 */
	public function store_inventory( array $inventory ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings                      = $this->settings->get_settings();
		$settings['menu_inventory']    = $this->settings->sanitize_menu_inventory( $inventory );
		$this->settings->save_settings( $settings );
	}

	/**
	 * Refresh inventory if needed.
	 */
	public function maybe_refresh_inventory() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->get_settings();
		if ( ! empty( $settings['advanced']['cache_inventory'] ) && ! empty( $settings['menu_inventory']['top_level'] ) ) {
			return;
		}

		$inventory = $this->scan();
		$this->store_inventory( $inventory );
	}

	/**
	 * Scan all current menus.
	 *
	 * @return array<string, mixed>
	 */
	public function scan() {
		global $menu, $submenu;

		$inventory = array(
			'collected_at' => current_time( 'mysql' ),
			'top_level'    => array(),
			'submenus'     => array(),
		);

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( empty( $item[0] ) || empty( $item[2] ) ) {
					continue;
				}

				$slug = (string) $item[2];
				$inventory['top_level'][] = $this->normalize_item( $item, '', $slug );
			}
		}

		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent_slug => $items ) {
				foreach ( $items as $item ) {
					if ( empty( $item[0] ) || empty( $item[2] ) ) {
						continue;
					}

					$inventory['submenus'][ $parent_slug ][] = $this->normalize_item( $item, $parent_slug, (string) $item[2] );
				}
			}
		}

		return $inventory;
	}

	/**
	 * Normalize one menu entry.
	 *
	 * @param array<int, mixed> $item Raw menu array.
	 * @param string            $parent_slug Parent slug.
	 * @param string            $slug Slug.
	 * @return array<string, mixed>
	 */
	protected function normalize_item( array $item, $parent_slug, $slug ) {
		$title      = wp_strip_all_tags( (string) $item[0] );
		$capability = isset( $item[1] ) ? (string) $item[1] : '';
		$icon       = isset( $item[6] ) ? (string) $item[6] : '';
		$source     = $this->guess_source( $slug, $parent_slug );
		$hook       = $this->get_hook_suffix( $slug, $parent_slug );

		return array(
			'title'      => $title,
			'slug'       => $slug,
			'parent'     => $parent_slug,
			'capability' => $capability,
			'hook_suffix' => $hook,
			'screen_id'  => $hook,
			'icon'       => $icon,
			'source'     => $source,
		);
	}

	/**
	 * Guess source.
	 *
	 * @param string $slug Slug.
	 * @param string $parent Parent.
	 * @return string
	 */
	protected function guess_source( $slug, $parent ) {
		$haystack = strtolower( $slug . ' ' . $parent );

		if ( false !== strpos( $haystack, 'admin.php?page=' ) || false !== strpos( $haystack, 'toplevel' ) ) {
			return 'plugin';
		}

		if ( false !== strpos( $haystack, 'edit.php?post_type=' ) || false !== strpos( $haystack, 'post-new.php' ) ) {
			return 'core';
		}

		if ( false !== strpos( $haystack, '.php' ) ) {
			return 'core';
		}

		return 'custom';
	}

	/**
	 * Get hook suffix for a menu item.
	 *
	 * @param string $slug Slug.
	 * @param string $parent Parent slug.
	 * @return string
	 */
	protected function get_hook_suffix( $slug, $parent ) {
		if ( function_exists( 'get_plugin_page_hookname' ) ) {
			return (string) get_plugin_page_hookname( $slug, $parent );
		}

		return sanitize_key( $slug );
	}
}
