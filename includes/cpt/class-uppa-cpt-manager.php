<?php
/**
 * Dynamic Custom Post Type and Taxonomy registration manager.
 *
 * Client code calls the static factory methods (register / register_taxonomy)
 * at any point before or during the init action. Each call schedules a
 * WordPress add_action( 'init', ..., 0 ) internally, so callers never need
 * to worry about hook timing themselves.
 *
 * Usage — post type:
 *
 *   UPPA_CPT_Manager::register([
 *       'post_type'   => 'project',
 *       'singular'    => 'Project',
 *       'plural'      => 'Projects',
 *       'icon'        => 'dashicons-portfolio',
 *       'supports'    => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
 *       'public'      => true,
 *       'has_archive' => true,
 *       'rewrite'     => [ 'slug' => 'projects' ],
 *   ]);
 *
 * Usage — taxonomy:
 *
 *   UPPA_CPT_Manager::register_taxonomy([
 *       'taxonomy'  => 'project_category',
 *       'singular'  => 'Project Category',
 *       'plural'    => 'Project Categories',
 *       'post_type' => [ 'project' ],
 *       'rewrite'   => [ 'slug' => 'project-category' ],
 *   ]);
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_CPT_Manager
 */
class UPPA_CPT_Manager {

	// -------------------------------------------------------------------------
	// State
	// -------------------------------------------------------------------------

	/**
	 * Index of every post type queued via register().
	 *
	 * Keyed by post type slug; value is the final merged args array passed to
	 * register_post_type().
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $registered_post_types = [];

	/**
	 * Index of every taxonomy queued via register_taxonomy().
	 *
	 * Keyed by taxonomy slug; value is the final merged args array passed to
	 * register_taxonomy().
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $registered_taxonomies = [];

	// -------------------------------------------------------------------------
	// Post type registration
	// -------------------------------------------------------------------------

	/**
	 * Queue a Custom Post Type for registration on the init action.
	 *
	 * The method builds a complete labels array from $config['singular'] and
	 * $config['plural'], merges the caller's config with sensible defaults, and
	 * hooks register_post_type() at init priority 0 so CPTs are available as
	 * early as possible (before most plugins add their own init callbacks).
	 *
	 * Required keys in $config:
	 *   post_type  string   Post type slug (max 20 chars, lowercase, no spaces).
	 *   singular   string   Singular label, e.g. 'Project'.
	 *   plural     string   Plural label, e.g. 'Projects'.
	 *
	 * Optional keys (all others are passed directly to register_post_type()):
	 *   icon        string          Dashicon class or URL. Default 'dashicons-admin-post'.
	 *   supports    array<string>   Feature list. Default ['title', 'editor', 'thumbnail'].
	 *   public      bool            Default true.
	 *   has_archive bool            Default false.
	 *   rewrite     array|bool      Default ['slug' => $post_type, 'with_front' => false].
	 *   show_in_rest bool           Default true (enables block editor support).
	 *
	 * @param array<string, mixed> $config Post type configuration. See above for keys.
	 * @return void
	 */
	public static function register( array $config ): void {
		$post_type = $config['post_type'] ?? '';

		if ( '' === $post_type ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'UPPA_CPT_Manager::register() requires a non-empty "post_type" key.', 'uppa-core' ),
				UPPA_CORE_VERSION
			);
			return;
		}

		$args = self::build_post_type_args( $config );

		// Store before the hook fires so get_registered() is accurate even if
		// called before init.
		self::$registered_post_types[ $post_type ] = $args;

		add_action(
			'init',
			static function () use ( $post_type, $args ): void {
				register_post_type( $post_type, $args );
			},
			0
		);
	}

	/**
	 * Return every post type registered through this manager.
	 *
	 * The returned array is keyed by post type slug and contains the final
	 * merged args (including the generated labels array) that were — or will
	 * be — passed to register_post_type().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_registered(): array {
		return self::$registered_post_types;
	}

	// -------------------------------------------------------------------------
	// Taxonomy registration
	// -------------------------------------------------------------------------

	/**
	 * Queue a taxonomy for registration on the init action.
	 *
	 * Follows the same deferred pattern as register(): builds labels from
	 * singular/plural, merges sensible defaults, and hooks at init priority 0.
	 *
	 * Required keys in $config:
	 *   taxonomy   string          Taxonomy slug (max 32 chars, lowercase, no spaces).
	 *   singular   string          Singular label, e.g. 'Project Category'.
	 *   plural     string          Plural label, e.g. 'Project Categories'.
	 *   post_type  string|array<string>  Post type(s) to attach the taxonomy to.
	 *
	 * Optional keys:
	 *   hierarchical bool         Default true (category-style).
	 *   rewrite      array|bool   Default ['slug' => $taxonomy, 'with_front' => false].
	 *   show_in_rest bool         Default true.
	 *   public       bool         Default true.
	 *
	 * @param array<string, mixed> $config Taxonomy configuration. See above for keys.
	 * @return void
	 */
	public static function register_taxonomy( array $config ): void {
		$taxonomy  = $config['taxonomy']  ?? '';
		$post_type = $config['post_type'] ?? [];

		if ( '' === $taxonomy ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'UPPA_CPT_Manager::register_taxonomy() requires a non-empty "taxonomy" key.', 'uppa-core' ),
				UPPA_CORE_VERSION
			);
			return;
		}

		if ( empty( $post_type ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'UPPA_CPT_Manager::register_taxonomy() requires a non-empty "post_type" key.', 'uppa-core' ),
				UPPA_CORE_VERSION
			);
			return;
		}

		$args = self::build_taxonomy_args( $config );

		self::$registered_taxonomies[ $taxonomy ] = $args;

		add_action(
			'init',
			static function () use ( $taxonomy, $post_type, $args ): void {
				register_taxonomy( $taxonomy, (array) $post_type, $args );
			},
			0
		);
	}

	/**
	 * Return every taxonomy registered through this manager.
	 *
	 * The returned array is keyed by taxonomy slug and contains the final
	 * merged args (including the generated labels array).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_registered_taxonomies(): array {
		return self::$registered_taxonomies;
	}

	// -------------------------------------------------------------------------
	// Private builders
	// -------------------------------------------------------------------------

	/**
	 * Build the final args array for register_post_type().
	 *
	 * Generates a complete labels array from singular/plural, then deep-merges
	 * the caller's $config (minus the UPPA-specific keys) over the defaults.
	 *
	 * @param array<string, mixed> $config Raw caller config.
	 * @return array<string, mixed> Merged args ready for register_post_type().
	 */
	private static function build_post_type_args( array $config ): array {
		$singular = $config['singular'] ?? $config['post_type'];
		$plural   = $config['plural']   ?? $config['singular'] ?? $config['post_type'];

		$labels = [
			'name'                  => $plural,
			'singular_name'         => $singular,
			/* translators: %s: post type plural label */
			'all_items'             => sprintf( _x( 'All %s', 'post type label', 'uppa-core' ), $plural ),
			/* translators: %s: post type singular label */
			'add_new_item'          => sprintf( _x( 'Add New %s', 'post type label', 'uppa-core' ), $singular ),
			/* translators: %s: post type singular label */
			'edit_item'             => sprintf( _x( 'Edit %s', 'post type label', 'uppa-core' ), $singular ),
			/* translators: %s: post type singular label */
			'new_item'              => sprintf( _x( 'New %s', 'post type label', 'uppa-core' ), $singular ),
			/* translators: %s: post type singular label */
			'view_item'             => sprintf( _x( 'View %s', 'post type label', 'uppa-core' ), $singular ),
			/* translators: %s: post type plural label */
			'view_items'            => sprintf( _x( 'View %s', 'post type label (plural)', 'uppa-core' ), $plural ),
			/* translators: %s: post type plural label */
			'search_items'          => sprintf( _x( 'Search %s', 'post type label', 'uppa-core' ), $plural ),
			/* translators: %s: post type plural label */
			'not_found'             => sprintf( _x( 'No %s found.', 'post type label', 'uppa-core' ), strtolower( $plural ) ),
			/* translators: %s: post type plural label */
			'not_found_in_trash'    => sprintf( _x( 'No %s found in Trash.', 'post type label', 'uppa-core' ), strtolower( $plural ) ),
			/* translators: %s: post type singular label */
			'featured_image'        => sprintf( _x( '%s Image', 'post type label', 'uppa-core' ), $singular ),
			/* translators: %s: post type singular label */
			'set_featured_image'    => sprintf( _x( 'Set %s image', 'post type label', 'uppa-core' ), strtolower( $singular ) ),
			/* translators: %s: post type singular label */
			'remove_featured_image' => sprintf( _x( 'Remove %s image', 'post type label', 'uppa-core' ), strtolower( $singular ) ),
			/* translators: %s: post type singular label */
			'use_featured_image'    => sprintf( _x( 'Use as %s image', 'post type label', 'uppa-core' ), strtolower( $singular ) ),
			/* translators: %s: post type plural label */
			'archives'              => sprintf( _x( '%s Archives', 'post type label', 'uppa-core' ), $singular ),
			/* translators: %s: post type singular label */
			'insert_into_item'      => sprintf( _x( 'Insert into %s', 'post type label', 'uppa-core' ), strtolower( $singular ) ),
			/* translators: %s: post type singular label */
			'uploaded_to_this_item' => sprintf( _x( 'Uploaded to this %s', 'post type label', 'uppa-core' ), strtolower( $singular ) ),
			/* translators: %s: post type plural label */
			'filter_items_list'     => sprintf( _x( 'Filter %s list', 'post type label', 'uppa-core' ), strtolower( $plural ) ),
			/* translators: %s: post type plural label */
			'items_list_navigation' => sprintf( _x( '%s list navigation', 'post type label', 'uppa-core' ), $plural ),
			/* translators: %s: post type plural label */
			'items_list'            => sprintf( _x( '%s list', 'post type label', 'uppa-core' ), $plural ),
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
		];

		$post_type = $config['post_type'];

		$defaults = [
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'has_archive'  => false,
			'rewrite'      => [ 'slug' => $post_type, 'with_front' => false ],
			'supports'     => [ 'title', 'editor', 'thumbnail' ],
			'menu_icon'    => 'dashicons-admin-post',
		];

		// Strip UPPA-specific meta-keys before merging so they never reach WP core.
		$caller_args = array_diff_key(
			$config,
			array_flip( [ 'post_type', 'singular', 'plural', 'icon' ] )
		);

		// Map 'icon' to 'menu_icon' if the caller supplied it.
		if ( isset( $config['icon'] ) ) {
			$caller_args['menu_icon'] = $config['icon'];
		}

		// Caller values win; labels are always generated (not overridable via this API).
		return array_merge( $defaults, $caller_args, [ 'labels' => $labels ] );
	}

	/**
	 * Build the final args array for register_taxonomy().
	 *
	 * Mirrors build_post_type_args() but generates taxonomy-specific labels.
	 *
	 * @param array<string, mixed> $config Raw caller config.
	 * @return array<string, mixed> Merged args ready for register_taxonomy().
	 */
	private static function build_taxonomy_args( array $config ): array {
		$singular = $config['singular'] ?? $config['taxonomy'];
		$plural   = $config['plural']   ?? $config['singular'] ?? $config['taxonomy'];
		$taxonomy = $config['taxonomy'];

		$labels = [
			'name'                       => $plural,
			'singular_name'              => $singular,
			/* translators: %s: taxonomy plural label */
			'all_items'                  => sprintf( _x( 'All %s', 'taxonomy label', 'uppa-core' ), $plural ),
			/* translators: %s: taxonomy singular label */
			'edit_item'                  => sprintf( _x( 'Edit %s', 'taxonomy label', 'uppa-core' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'view_item'                  => sprintf( _x( 'View %s', 'taxonomy label', 'uppa-core' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'update_item'                => sprintf( _x( 'Update %s', 'taxonomy label', 'uppa-core' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'add_new_item'               => sprintf( _x( 'Add New %s', 'taxonomy label', 'uppa-core' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'new_item_name'              => sprintf( _x( 'New %s Name', 'taxonomy label', 'uppa-core' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'parent_item'                => sprintf( _x( 'Parent %s', 'taxonomy label', 'uppa-core' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'parent_item_colon'          => sprintf( _x( 'Parent %s:', 'taxonomy label', 'uppa-core' ), $singular ),
			/* translators: %s: taxonomy plural label */
			'search_items'               => sprintf( _x( 'Search %s', 'taxonomy label', 'uppa-core' ), $plural ),
			/* translators: %s: taxonomy plural label */
			'popular_items'              => sprintf( _x( 'Popular %s', 'taxonomy label', 'uppa-core' ), $plural ),
			/* translators: %s: taxonomy plural label */
			'not_found'                  => sprintf( _x( 'No %s found.', 'taxonomy label', 'uppa-core' ), strtolower( $plural ) ),
			/* translators: %s: taxonomy plural label */
			'items_list'                 => sprintf( _x( '%s list', 'taxonomy label', 'uppa-core' ), $plural ),
			/* translators: %s: taxonomy plural label */
			'items_list_navigation'      => sprintf( _x( '%s list navigation', 'taxonomy label', 'uppa-core' ), $plural ),
			/* translators: %s: taxonomy plural label */
			'choose_from_most_used'      => sprintf( _x( 'Choose from the most used %s', 'taxonomy label', 'uppa-core' ), strtolower( $plural ) ),
			/* translators: %s: taxonomy singular label */
			'separate_items_with_commas' => sprintf( _x( 'Separate %s with commas', 'taxonomy label', 'uppa-core' ), strtolower( $plural ) ),
			/* translators: %s: taxonomy plural label */
			'add_or_remove_items'        => sprintf( _x( 'Add or remove %s', 'taxonomy label', 'uppa-core' ), strtolower( $plural ) ),
			'menu_name'                  => $plural,
			'back_to_items'              => sprintf(
				/* translators: %s: taxonomy plural label */
				_x( '&larr; Go to %s', 'taxonomy label', 'uppa-core' ),
				$plural
			),
		];

		$defaults = [
			'labels'            => $labels,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'rewrite'           => [ 'slug' => $taxonomy, 'with_front' => false ],
			'show_admin_column' => true,
		];

		$caller_args = array_diff_key(
			$config,
			array_flip( [ 'taxonomy', 'singular', 'plural', 'post_type' ] )
		);

		return array_merge( $defaults, $caller_args, [ 'labels' => $labels ] );
	}
}
