<?php
/**
 * Hook loader for AdminForge.
 *
 * @package AdminForge
 */

namespace AdminForge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loader {
	/**
	 * Registered actions.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $filters = array();

	/**
	 * Add an action.
	 *
	 * @param string   $hook Hook name.
	 * @param object   $component Object instance.
	 * @param string   $callback Callback method.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Accepted args.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a filter.
	 *
	 * @param string   $hook Hook name.
	 * @param object   $component Object instance.
	 * @param string   $callback Callback method.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Accepted args.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register all hooks.
	 */
	public function run() {
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}

