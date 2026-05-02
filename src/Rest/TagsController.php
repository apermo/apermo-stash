<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Rest;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves tag listings over REST.
 */
class TagsController {

	/**
	 * Runs the per-tag aggregate query and returns the raw rows.
	 *
	 * @param array{post_status: list<string>, author: list<int>|null, perm: ?string} $visibility Visibility constraints.
	 *
	 * @return list<array{id: int|string, slug: string, name: string, count: int|string}>
	 */
	private static function fetch_term_counts( array $visibility ): array {
		global $wpdb;

		[ $where, $args ] = self::build_where_clause( $visibility );

		// Single aggregate replacing the previous "fetch every visible
		// bookmark id, then ask get_terms for counts" fan-out. Joins to
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

		// $sql is built from table-name constants + placeholder fragments
		// — every user-controlled value flows through wpdb::prepare. The
		// direct query is the whole point of this rewrite (it replaces
		// the slow N+1 from the previous get_terms loop).
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), \ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return \is_array( $rows ) ? \array_values( $rows ) : [];
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
			BookmarkPostType::POST_TYPE,
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
					'permission_callback' => [ Permissions::class, 'allow_anyone' ],
				],
			],
		);
	}

	/**
	 * Lists tags with bookmark counts that respect the requester's visibility.
	 *
	 * Counts are computed in a single aggregate SQL statement that joins
	 * the terms/term_taxonomy/term_relationships tables to the posts
	 * table, applying the same visibility constraints used by the
	 * bookmarks list endpoint.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$visibility = BookmarksController::visibility_filter( $request );
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
