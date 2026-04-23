<?php
/**
 * Assignment manager.
 *
 * @package AdminForge
 */

namespace AdminForge\Modules;

use AdminForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assignment_Manager {
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
	 * Determine if current user should receive transformations.
	 *
	 * @param int|null $user_id User ID.
	 * @return bool
	 */
	public function is_targeted_user( $user_id = null ) {
		$settings = $this->settings->get_settings();

		if ( empty( $settings['general']['enabled'] ) ) {
			return false;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		if ( ! empty( $settings['general']['bypass_for_admins'] ) && current_user_can( 'manage_options' ) ) {
			$mode = $settings['general']['mode'];
			if ( 'global' !== $mode ) {
				return false;
			}
		}

		switch ( $settings['general']['mode'] ) {
			case 'global':
				return true;
			case 'users':
				return in_array( $user_id, array_map( 'absint', (array) $settings['general']['target_users'] ), true );
			case 'roles':
				return $this->user_has_selected_role( $user_id, (array) $settings['general']['target_roles'] );
			case 'roles_users':
				return $this->user_in_targets( $user_id );
		}

		return false;
	}

	/**
	 * Resolve priority scope.
	 *
	 * @param int|null $user_id User ID.
	 * @return string
	 */
	public function resolve_scope( $user_id = null ) {
		$settings = $this->settings->get_settings();

		if ( empty( $settings['general']['enabled'] ) ) {
			return 'none';
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return 'none';
		}

		if ( in_array( $user_id, array_map( 'absint', (array) $settings['general']['target_users'] ), true ) ) {
			return 'user';
		}

		if ( $this->user_has_selected_role( $user_id, (array) $settings['general']['target_roles'] ) ) {
			return 'role';
		}

		return 'global';
	}

	/**
	 * Determine if user has one of the selected roles.
	 *
	 * @param int   $user_id User ID.
	 * @param array $roles Roles.
	 * @return bool
	 */
	protected function user_has_selected_role( $user_id, array $roles ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles = array_filter( array_map( 'sanitize_key', $roles ) );
		return (bool) array_intersect( (array) $user->roles, $roles );
	}

	/**
	 * Determine if user matches any rule target.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function user_in_targets( $user_id ) {
		$settings = $this->settings->get_settings();
		return in_array( $user_id, array_map( 'absint', (array) $settings['general']['target_users'] ), true )
			|| $this->user_has_selected_role( $user_id, (array) $settings['general']['target_roles'] );
	}
}

