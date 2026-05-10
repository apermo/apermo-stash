<?php

declare(strict_types=1);

namespace Apermo\Stash\Rest;

\defined( 'ABSPATH' ) || exit();

use Apermo\Stash\PostType\LinkPostType;
use Apermo\Stash\PostType\TagTaxonomy;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves tag listings over REST.
 */
class TagsController {

	private const CACHE_GROUP   = 'apermo-stash';
	private const CACHE_VERSION = 2;

	/**
	 * Runs the per-tag aggregate query and returns the raw rows.
	 *
	 * Results are cached in the object cache with a key derived from:
	 * - the taxonomy's `last_changed` timestamp (busts on term and term-
	 *   relationship mutations);
	 * - the posts cache's `last_changed` timestamp (busts on post status
	 *   / author / type mutations, which the visibility WHERE clause
	 *   reads);
	 * - the visibility spec; and
	 * - the current user id, since `perm === 'readable'` joins
	 *   `get_current_user_id()` into the SQL — without the user id in
	 *   the key, two authenticated callers with different IDs would
	 *   collide on the same cache entry and read each other's
	 *   per-author counts.
	 *
	 * @param array{post_status: list<string>, author: list<int>|null, perm: ?string} $visibility Visibility constraints.
	 *
	 * @return list<array{id: int|string, slug: string, name: string, count: int|string}>
	 */
	private static function fetch_term_counts( array $visibility ): array {
		global $wpdb;

		$key_payload = wp_json_encode(
			[
				'taxonomy'   => wp_cache_get_last_changed( TagTaxonomy::TAXONOMY ),
				'posts'      => wp_cache_get_last_changed( 'posts' ),
				'visibility' => $visibility,
				'user'       => get_current_user_id(),
			],
		);
		$cache_key = 'tag_counts_v' . self::CACHE_VERSION . ':' . \md5( (string) $key_payload );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( \is_array( $cached ) ) {
			return $cached;
		}

		[ $where, $args ] = self::build_where_clause( $visibility );

		// Single aggregate replacing the previous "fetch every visible
		// link id, then ask get_terms for counts" fan-out. Joins to
		// indexed columns (post_type, post_status, taxonomy) keep this
		// fast as the bookmark library grows.
		$sql = "SELECT t.term_id AS id, t.name, t.slug, COUNT(DISTINCT p.ID) AS count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				WHERE {$where}
				GROUP BY t.term_id, t.name, t.slug
				ORDER BY t.name ASC";

		/*
		 * $sql is composed from `$wpdb->`-prefixed table names plus the
		 * `$where` fragment built in build_where_clause(), which contains
		 * only literal placeholders (`%s`/`%d`) and constant SQL — every
		 * user-controlled value flows through `$args` into wpdb::prepare.
		 * The remaining sniff (DirectQuery) is intentional: this single
		 * aggregate replaces an N+1 fan-out and is cached above. The
		 * PluginCheck.Security.DirectDB.UnescapedDBParameter sniff fires
		 * on the same false-positive (it doesn't follow the prepare()
		 * call) and is suppressed alongside the WordPress.DB ones.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), \ARRAY_A );
		$rows = \is_array( $rows ) ? \array_values( $rows ) : [];

		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP );

		return $rows;
	}

	/**
	 * Builds the SQL WHERE fragment + placeholder args for a visibility spec.
	 *
	 * Returned as `[$where, $args]` where `$where` is intended to be
	 * concatenated into a `wpdb::prepare()` template and `$args` is the
	 * matching ordered parameter list.
	 *
	 * @param array{post_status: list<string>, author: list<int>|null, perm: ?string} $visibility Visibility constraints.
	 *
	 * @return array{0: string, 1: list<string|int>}
	 */
	public static function build_where_clause( array $visibility ): array {
		$args  = [
			LinkPostType::POST_TYPE,
			TagTaxonomy::TAXONOMY,
		];
		$where = 'p.post_type = %s AND tt.taxonomy = %s';

		$statuses     = $visibility['post_status'];
		$placeholders = \implode( ', ', \array_fill( 0, \count( $statuses ), '%s' ) );
		$where        .= " AND p.post_status IN ({$placeholders})";
		$args         = \array_merge( $args, $statuses );

		if ( $visibility['author'] !== null ) {
			$authors             = \array_map( '\intval', $visibility['author'] );
			$author_placeholders = \implode( ', ', \array_fill( 0, \count( $authors ), '%d' ) );
			$where               .= " AND p.post_author IN ({$author_placeholders})";
			$args                = \array_merge( $args, $authors );
		}

		if ( $visibility['perm'] === 'readable' ) {
			$where .= " AND (p.post_status = 'publish' OR p.post_author = %d)";
			$args[] = get_current_user_id();
		}

		return [ $where, $args ];
	}

	/**
	 * Registers the tags route.
	 *
	 * @param string $rest_namespace REST namespace.
	 *
	 * @return void
	 */
	public function register_routes( string $rest_namespace ): void {
		register_rest_route(
			$rest_namespace,
			'/tags',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_items' ],
					'permission_callback' => [ Permissions::class, 'require_read_links' ],
				],
			],
		);
	}

	/**
	 * Lists tags with link counts that respect the requester's visibility.
	 *
	 * Counts are computed in a single aggregate SQL statement that joins
	 * the terms/term_taxonomy/term_relationships tables to the posts
	 * table, applying the same visibility constraints used by the
	 * links list endpoint.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$visibility = LinksController::visibility_filter( $request );
		$rows       = self::fetch_term_counts( $visibility );

		$items = [];
		foreach ( $rows as $row ) {
			$items[] = [
				'id'    => (int) $row['id'],
				'slug'  => $row['slug'],
				'name'  => $row['name'],
				'count' => (int) $row['count'],
			];
		}

		return rest_ensure_response( $items );
	}
}
