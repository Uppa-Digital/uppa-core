<?php
/**
 * Registers and dispatches all action and filter hooks.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Loader
 */
class UPPA_Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	private array $actions = [];

	/**
	 * Registered filters.
	 *
	 * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	private array $filters = [];

	/**
	 * Add an action hook.
	 *
	 * @param string          $hook          The WordPress action hook name.
	 * @param object|null     $component     Object that owns the callback (null for closures).
	 * @param string|callable $callback      Method name or callable.
	 * @param int             $priority      Hook priority.
	 * @param int             $accepted_args Number of arguments accepted.
	 */
	public function add_action(
		string $hook,
		?object $component,
		string|callable $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string          $hook          The WordPress filter hook name.
	 * @param object|null     $component     Object that owns the callback (null for closures).
	 * @param string|callable $callback      Method name or callable.
	 * @param int             $priority      Hook priority.
	 * @param int             $accepted_args Number of arguments accepted.
	 */
	public function add_filter(
		string $hook,
		?object $component,
		string|callable $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register all collected actions and filters with WordPress.
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				$hook['component'] ? [ $hook['component'], $hook['callback'] ] : $hook['callback'],
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				$hook['component'] ? [ $hook['component'], $hook['callback'] ] : $hook['callback'],
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
