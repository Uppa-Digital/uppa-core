<?php
/**
 * SEO utility helpers.
 *
 * Provides static helpers for structured breadcrumb data, page meta titles,
 * and JSON-LD schema output. All methods return data or escaped markup strings —
 * none produce direct output, so callers control where and when things render.
 *
 * Schema methods are designed to be hooked to wp_head:
 *
 *   add_action( 'wp_head', [ 'UPPA_SEO_Utils', 'schema_organization' ], 1 );
 *
 * Breadcrumb data is rendering-agnostic; pass the array from get_breadcrumbs()
 * to your template, then optionally to schema_breadcrumb() for JSON-LD output.
 *
 * @package uppa-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UPPA_SEO_Utils
 */
class UPPA_SEO_Utils {

	// -------------------------------------------------------------------------
	// Breadcrumbs
	// -------------------------------------------------------------------------

	/**
	 * Build a structured breadcrumb trail for the current request.
	 *
	 * Returns an ordered array of items, each containing a human-readable label
	 * and an absolute URL. The last item represents the current page and always
	 * has an empty string URL (conventional for "current" crumbs).
	 *
	 * Handled contexts:
	 *   - Singular posts / pages / CPTs  (home → [taxonomy term →] post)
	 *   - Post type archives             (home → archive)
	 *   - Date archives                  (home → year [→ month])
	 *   - Author archives                (home → Author Name)
	 *   - Search results                 (home → Search: "query")
	 *   - 404 pages                      (home → Page Not Found)
	 *
	 * This method never outputs HTML. Pass the returned array to your template
	 * or to schema_breadcrumb() for JSON-LD output.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional configuration.
	 *
	 *     @type string $home_label Label for the first (home) crumb. Default 'Home'.
	 *     @type bool   $taxonomy   Whether to include taxonomy terms for singular
	 *                              posts. Default true.
	 * } $args
	 * @return array<int, array{label: string, url: string}> Ordered breadcrumb items.
	 *         The last item always has url === '' (current page convention).
	 */
	public static function get_breadcrumbs( array $args = [] ): array {
		$defaults = [
			'home_label' => __( 'Home', 'uppa-core' ),
			'taxonomy'   => true,
		];
		$args = wp_parse_args( $args, $defaults );

		$crumbs = [];

		// Every trail starts with Home.
		$crumbs[] = [
			'label' => (string) $args['home_label'],
			'url'   => esc_url( home_url( '/' ) ),
		];

		if ( is_404() ) {
			$crumbs[] = [
				'label' => __( 'Page Not Found', 'uppa-core' ),
				'url'   => '',
			];
			return $crumbs;
		}

		if ( is_search() ) {
			$crumbs[] = [
				/* translators: %s: search query string */
				'label' => sprintf( __( 'Search: "%s"', 'uppa-core' ), get_search_query() ),
				'url'   => '',
			];
			return $crumbs;
		}

		if ( is_archive() ) {
			if ( is_post_type_archive() ) {
				$post_type = get_post_type_object( (string) get_post_type() );
				$crumbs[]  = [
					'label' => $post_type ? $post_type->labels->name : '',
					'url'   => '',
				];
				return $crumbs;
			}

			if ( is_author() ) {
				$crumbs[] = [
					'label' => (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() ),
					'url'   => '',
				];
				return $crumbs;
			}

			if ( is_day() ) {
				$crumbs[] = [
					'label' => get_the_date( 'Y' ),
					'url'   => esc_url( (string) get_year_link( (int) get_query_var( 'year' ) ) ),
				];
				$crumbs[] = [
					'label' => get_the_date( 'F' ),
					'url'   => esc_url( (string) get_month_link(
						(int) get_query_var( 'year' ),
						(int) get_query_var( 'monthnum' )
					) ),
				];
				$crumbs[] = [
					'label' => get_the_date( 'j' ),
					'url'   => '',
				];
				return $crumbs;
			}

			if ( is_month() ) {
				$crumbs[] = [
					'label' => get_the_date( 'Y' ),
					'url'   => esc_url( (string) get_year_link( (int) get_query_var( 'year' ) ) ),
				];
				$crumbs[] = [
					'label' => get_the_date( 'F' ),
					'url'   => '',
				];
				return $crumbs;
			}

			if ( is_year() ) {
				$crumbs[] = [
					'label' => get_the_date( 'Y' ),
					'url'   => '',
				];
				return $crumbs;
			}

			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				if ( $term instanceof WP_Term && $term->parent ) {
					$parent = get_term( $term->parent, $term->taxonomy );
					if ( $parent instanceof WP_Term ) {
						$crumbs[] = [
							'label' => $parent->name,
							'url'   => esc_url( (string) get_term_link( $parent ) ),
						];
					}
				}
				$crumbs[] = [
					'label' => $term instanceof WP_Term ? $term->name : '',
					'url'   => '',
				];
				return $crumbs;
			}
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( ! $post ) {
				return $crumbs;
			}

			// For hierarchical post types (pages) walk the ancestor chain.
			if ( is_page() && $post->post_parent ) {
				$ancestors = array_reverse( get_post_ancestors( $post ) );
				foreach ( $ancestors as $ancestor_id ) {
					$crumbs[] = [
						'label' => (string) get_the_title( $ancestor_id ),
						'url'   => esc_url( (string) get_permalink( $ancestor_id ) ),
					];
				}
			}

			// For non-page post types optionally prepend the primary taxonomy term.
			if ( ! is_page() && $args['taxonomy'] ) {
				$post_type  = $post->post_type;
				$taxonomies = get_object_taxonomies( $post_type, 'objects' );

				foreach ( $taxonomies as $tax ) {
					// Use only hierarchical taxonomies (category-style) as breadcrumb context.
					if ( ! $tax->hierarchical ) {
						continue;
					}
					$terms = get_the_terms( $post, $tax->name );
					if ( ! is_array( $terms ) || empty( $terms ) ) {
						continue;
					}
					// Take the first term (lowest ID = oldest, most likely primary).
					$term     = reset( $terms );
					$crumbs[] = [
						'label' => $term->name,
						'url'   => esc_url( (string) get_term_link( $term ) ),
					];
					break; // One taxonomy context is enough.
				}
			}

			// Current singular item — no URL (it is the current page).
			$crumbs[] = [
				'label' => (string) get_the_title( $post ),
				'url'   => '',
			];
		}

		return $crumbs;
	}

	// -------------------------------------------------------------------------
	// Page meta title
	// -------------------------------------------------------------------------

	/**
	 * Return a formatted page title string for use in <title> or Open Graph tags.
	 *
	 * Resolution order:
	 *   1. Rank Math  — rank_math_get_term_meta() / rank_math()->head->get_title()
	 *                   detected via function_exists( 'rank_math' ).
	 *   2. Yoast SEO  — WPSEO_Meta::get_value() detected via
	 *                   function_exists( 'wpseo_init' ) / class_exists( 'WPSEO_Meta' ).
	 *   3. Fallback   — "Post Title | Site Name" assembled from core functions.
	 *
	 * When $post_id is 0 or null the method uses the queried object of the
	 * current request, so it works correctly on archive and taxonomy pages as
	 * well as singular posts.
	 *
	 * @param int|null $post_id Post or term ID. Null or 0 uses the current request
	 *                          queried object. Default null.
	 * @return string Formatted title string. Never empty — always falls back to
	 *                the site name at minimum.
	 */
	public static function get_page_meta_title( ?int $post_id = null ): string {
		$site_name = get_bloginfo( 'name' );

		// --- Rank Math ---
		if ( function_exists( 'rank_math' ) && ! empty( $post_id ) ) {
			$rm_title = get_post_meta( $post_id, 'rank_math_title', true );
			if ( ! empty( $rm_title ) ) {
				return (string) $rm_title;
			}
		}

		// --- Yoast SEO ---
		if ( class_exists( 'WPSEO_Meta' ) && ! empty( $post_id ) ) {
			$yoast_title = WPSEO_Meta::get_value( 'title', $post_id );
			if ( ! empty( $yoast_title ) ) {
				return (string) $yoast_title;
			}
		}

		// --- Core fallback ---
		if ( ! empty( $post_id ) ) {
			$post_title = get_the_title( $post_id );
			if ( $post_title ) {
				return $post_title . ' | ' . $site_name;
			}
		}

		// Handle archives, search, 404, and front-page without a post ID.
		if ( is_front_page() || is_home() ) {
			return $site_name;
		}

		if ( is_search() ) {
			return sprintf(
				/* translators: %s: search query */
				__( 'Search: "%s"', 'uppa-core' ),
				get_search_query()
			) . ' | ' . $site_name;
		}

		if ( is_404() ) {
			return __( 'Page Not Found', 'uppa-core' ) . ' | ' . $site_name;
		}

		if ( is_archive() ) {
			$archive_title = get_the_archive_title();
			if ( $archive_title ) {
				return $archive_title . ' | ' . $site_name;
			}
		}

		return $site_name;
	}

	// -------------------------------------------------------------------------
	// JSON-LD schema
	// -------------------------------------------------------------------------

	/**
	 * Return a JSON-LD <script> block containing Organization schema markup.
	 *
	 * Uses the site name, URL, and logo attachment (Custom Logo via the
	 * Customizer) sourced from WordPress core settings — no plugin required.
	 *
	 * Designed to be registered on wp_head at priority 1 so it appears before
	 * other <head> content:
	 *
	 *   add_action( 'wp_head', [ 'UPPA_SEO_Utils', 'schema_organization' ], 1 );
	 *
	 * Note: as of WordPress 6.3, core emits its own basic schema on some themes.
	 * Check for conflicts before registering this hook on a block theme.
	 *
	 * @return string Complete <script type="application/ld+json">…</script> block,
	 *                or an empty string if wp_json_encode() fails.
	 */
	public static function schema_organization(): string {
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => get_bloginfo( 'name' ),
			'url'      => esc_url( home_url( '/' ) ),
		];

		// Attach logo if a Custom Logo is set via Appearance → Customize.
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_src = wp_get_attachment_image_src( (int) $logo_id, 'full' );
			if ( $logo_src ) {
				$schema['logo'] = [
					'@type'  => 'ImageObject',
					'url'    => esc_url( $logo_src[0] ),
					'width'  => (int) $logo_src[1],
					'height' => (int) $logo_src[2],
				];
			}
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return '';
		}

		return '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	/**
	 * Return a JSON-LD <script> block containing BreadcrumbList schema markup.
	 *
	 * Accepts the structured array produced by get_breadcrumbs() and converts it
	 * to a schema.org BreadcrumbList. The final crumb (current page, url === '')
	 * is included without an 'item' property, which is the correct schema
	 * representation for the active page.
	 *
	 * Typical usage:
	 *
	 *   $crumbs = UPPA_SEO_Utils::get_breadcrumbs();
	 *   echo UPPA_SEO_Utils::schema_breadcrumb( $crumbs ); // in wp_head or inline
	 *
	 * @param array<int, array{label: string, url: string}> $breadcrumbs Ordered
	 *        breadcrumb items as returned by get_breadcrumbs().
	 * @return string Complete <script type="application/ld+json">…</script> block,
	 *                or an empty string if $breadcrumbs is empty or encoding fails.
	 */
	public static function schema_breadcrumb( array $breadcrumbs ): string {
		if ( empty( $breadcrumbs ) ) {
			return '';
		}

		$list_elements = [];

		foreach ( $breadcrumbs as $index => $crumb ) {
			$element = [
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb['label'],
			];

			// Only include 'item' (URL) when this is not the current page crumb.
			if ( ! empty( $crumb['url'] ) ) {
				$element['item'] = esc_url( $crumb['url'] );
			}

			$list_elements[] = $element;
		}

		$schema = [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list_elements,
		];

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return '';
		}

		return '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}
}
