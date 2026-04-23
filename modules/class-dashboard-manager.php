<?php
/**
 * Dashboard widget control.
 *
 * @package AdminForge
 */

namespace AdminForge\Modules;

use AdminForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard_Manager {
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

	public function __construct( Settings $settings, Menu_Scanner $scanner, Rules_Engine $rules ) {
		$this->settings = $settings;
		$this->scanner  = $scanner;
		$this->rules    = $rules;
	}

	/**
	 * Refresh dashboard widget inventory.
	 */
	public function maybe_refresh_dashboard_inventory() {
		$settings = $this->settings->get_settings();
		if ( ! empty( $settings['advanced']['cache_inventory'] ) && ! empty( $settings['dashboard_inventory']['widgets'] ) ) {
			return;
		}

		$inventory = $this->scan_widgets();
		$this->store_inventory( $inventory );
	}

	/**
	 * Register dashboard widget cleanup.
	 */
	public function register_dashboard_hooks() {
		$this->maybe_refresh_dashboard_inventory();

		if ( ! $this->rules->should_apply() ) {
			return;
		}

		$settings = $this->settings->get_settings();
		$hidden   = array_map( 'sanitize_key', (array) $settings['visibility']['dashboard_widgets'] );

		foreach ( $hidden as $widget_id ) {
			remove_meta_box( $widget_id, 'dashboard', 'normal' );
			remove_meta_box( $widget_id, 'dashboard', 'side' );
			remove_meta_box( $widget_id, 'dashboard', 'column3' );
			remove_meta_box( $widget_id, 'dashboard', 'column4' );
		}
	}

	/**
	 * Scan dashboard widgets.
	 *
	 * @return array<string, mixed>
	 */
	public function scan_widgets() {
		global $wp_meta_boxes;

		$inventory = array(
			'collected_at' => current_time( 'mysql' ),
			'widgets'      => array(),
		);

		if ( empty( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
			return $inventory;
		}

		foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
			foreach ( (array) $priorities as $priority => $widgets ) {
				foreach ( (array) $widgets as $widget_id => $widget ) {
					$inventory['widgets'][] = array(
						'id'       => sanitize_key( $widget_id ),
						'title'    => isset( $widget['title'] ) ? wp_strip_all_tags( $widget['title'] ) : $widget_id,
						'context'  => sanitize_key( $context ),
						'priority' => sanitize_key( $priority ),
					);
				}
			}
		}

		return $inventory;
	}

	/**
	 * Store dashboard inventory.
	 *
	 * @param array<string, mixed> $inventory Inventory.
	 */
	public function store_inventory( array $inventory ) {
		$settings                        = $this->settings->get_settings();
		$settings['dashboard_inventory'] = $this->settings->sanitize_dashboard_inventory( $inventory );
		$this->settings->save_settings( $settings );
	}
}
