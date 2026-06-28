<?php
/**
 * Collects and dispatches all WordPress action and filter hooks.
 *
 * Registration (add_action / add_filter) is intentionally separated from
 * execution (run) so that the full hook list can be inspected or mocked in
 * unit tests before WordPress is ever involved.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_Loader
 *
 * Maintains two internal queues — one for actions, one for filters — and
 * flushes both to WordPress only when run() is called.
 */
class UPPA_Loader {

	/**
	 * Queued action registrations.
	 *
	 * Each entry is an associative array with keys:
	 *   hook          string        WordPress hook name.
	 *   component     object|null   Object that owns the callback; null for bare callables.
	 *   callback      string|callable  Method name (when component is set) or any callable.
	 *   priority      int           Hook priority (default 10).
	 *   accepted_args int           Number of arguments the callback accepts (default 1).
	 *
	 * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	private array $actions = [];

	/**
	 * Queued filter registrations.
	 *
	 * Same shape as {@see $actions}.
	 *
	 * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	private array $filters = [];

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Queue an action hook for later registration with WordPress.
	 *
	 * Does NOT call the native add_action() — that happens in run().
	 *
	 * @param string          $hook          WordPress action hook name.
	 * @param object|null     $component     Object that owns the callback. Pass null
	 *                                       when $callback is already a standalone callable.
	 * @param string|callable $callback      Public method name on $component, or any
	 *                                       callable when $component is null.
	 * @param int             $priority      Hook execution priority. Default 10.
	 * @param int             $accepted_args Number of arguments the callback accepts. Default 1.
	 * @return void
	 */
	public function add_action(
		string $hook,
		?object $component,
		string|callable $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = $this->build( $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Queue a filter hook for later registration with WordPress.
	 *
	 * Does NOT call the native add_filter() — that happens in run().
	 *
	 * @param string          $hook          WordPress filter hook name.
	 * @param object|null     $component     Object that owns the callback. Pass null
	 *                                       when $callback is already a standalone callable.
	 * @param string|callable $callback      Public method name on $component, or any
	 *                                       callable when $component is null.
	 * @param int             $priority      Hook execution priority. Default 10.
	 * @param int             $accepted_args Number of arguments the callback accepts. Default 1.
	 * @return void
	 */
	public function add_filter(
		string $hook,
		?object $component,
		string|callable $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters[] = $this->build( $hook, $component, $callback, $priority, $accepted_args );
	}

	// -------------------------------------------------------------------------
	// Execution
	// -------------------------------------------------------------------------

	/**
	 * Register every queued action and filter with WordPress.
	 *
	 * Call this once — typically from UPPA_Core::run() on the plugins_loaded
	 * action. Registering the same hook twice (e.g. by calling run() again)
	 * is harmless but redundant; WordPress deduplicates by callback identity.
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $entry ) {
			add_filter(
				$entry['hook'],
				$this->resolve_callback( $entry ),
				$entry['priority'],
				$entry['accepted_args']
			);
		}

		foreach ( $this->actions as $entry ) {
			add_action(
				$entry['hook'],
				$this->resolve_callback( $entry ),
				$entry['priority'],
				$entry['accepted_args']
			);
		}
	}

	// -------------------------------------------------------------------------
	// Inspection (useful in tests)
	// -------------------------------------------------------------------------

	/**
	 * Return the raw list of queued actions (not yet registered with WordPress).
	 *
	 * @return array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	public function get_actions(): array {
		return $this->actions;
	}

	/**
	 * Return the raw list of queued filters (not yet registered with WordPress).
	 *
	 * @return array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	public function get_filters(): array {
		return $this->filters;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a normalised hook entry array.
	 *
	 * @param string          $hook          WordPress hook name.
	 * @param object|null     $component     Owning object or null.
	 * @param string|callable $callback      Method name or callable.
	 * @param int             $priority      Hook priority.
	 * @param int             $accepted_args Number of accepted arguments.
	 * @return array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}
	 */
	private function build(
		string $hook,
		?object $component,
		string|callable $callback,
		int $priority,
		int $accepted_args
	): array {
		return compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Resolve a hook entry to the callable WordPress expects.
	 *
	 * When a component object is present the callback is a method name string,
	 * so we return [ $object, 'method_name' ]. Otherwise the callback is
	 * already a fully-qualified callable (closure, function name, etc.).
	 *
	 * @param array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int} $entry
	 * @return callable
	 */
	private function resolve_callback( array $entry ): callable {
		if ( null !== $entry['component'] ) {
			return [ $entry['component'], $entry['callback'] ];
		}

		return $entry['callback'];
	}
}
