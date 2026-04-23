<?php
/**
 * Rules engine.
 *
 * @package AdminForge
 */

namespace AdminForge\Modules;

use AdminForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rules_Engine {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Assignment manager.
	 *
	 * @var Assignment_Manager
	 */
	protected $assignments;

	public function __construct( Settings $settings, Assignment_Manager $assignments ) {
		$this->settings    = $settings;
		$this->assignments = $assignments;
	}

	/**
	 * Determine whether AdminForge should apply.
	 *
	 * @param int|null $user_id User ID.
	 * @return bool
	 */
	public function should_apply( $user_id = null ) {
		return $this->assignments->is_targeted_user( $user_id );
	}

	/**
	 * Get current scope.
	 *
	 * @param int|null $user_id User ID.
	 * @return string
	 */
	public function get_scope( $user_id = null ) {
		return $this->assignments->resolve_scope( $user_id );
	}

	/**
	 * Get visibility settings.
	 *
	 * @return array<string, mixed>
	 */
	public function visibility() {
		$settings = $this->settings->get_settings();
		return $settings['visibility'];
	}

	/**
	 * Get UI settings.
	 *
	 * @return array<string, mixed>
	 */
	public function ui() {
		$settings = $this->settings->get_settings();
		return $settings['ui'];
	}

	/**
	 * Get branding settings.
	 *
	 * @return array<string, mixed>
	 */
	public function branding() {
		$settings = $this->settings->get_settings();
		return $settings['branding'];
	}
}

