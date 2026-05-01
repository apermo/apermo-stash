<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Rest;

use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves tag listings over REST.
 */
class TagsController {

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
	 * Counts are computed in two queries total: one to fetch the IDs of all
	 * bookmarks the caller may see, and one `get_terms` call scoped to those
	 * IDs (which returns per-term counts in a single aggregated query).
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$visibility = BookmarksController::visibility_filter( $request );

		$visible_ids = $this->visible_bookmark_ids( $visibility );
		if ( $visible_ids === [] ) {
			return rest_ensure_response( [] );
		}

		$terms = get_terms(
			[
				'taxonomy'   => TagTaxonomy::TAXONOMY,
				'hide_empty' => true,
				'object_ids' => $visible_ids,
				'orderby'    => 'name',
				'order'      => 'ASC',
			],
		);

		if ( ! \is_array( $terms ) ) {
			return rest_ensure_response( [] );
		}

		$items = [];
		foreach ( $terms as $term ) {
			$items[] = [
				'id'    => $term->term_id,
				'slug'  => $term->slug,
				'name'  => $term->name,
				'count' => $term->count,
			];
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Returns the IDs of every bookmark the caller is allowed to see.
	 *
	 * @param array{post_status: list<string>, author: list<int>|null, perm: ?string} $visibility Visibility constraints.
	 *
	 * @return list<int>
	 */
	private function visible_bookmark_ids( array $visibility ): array {
		$args = [
			'post_type'      => BookmarkPostType::POST_TYPE,
			'post_status'    => $visibility['post_status'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		if ( $visibility['author'] !== null ) {
			$args['author__in'] = $visibility['author'];
		}
		if ( $visibility['perm'] !== null ) {
			$args['perm'] = $visibility['perm'];
		}

		$query = new WP_Query( $args );

		return \array_values( \array_map( 'intval', $query->posts ) );
	}
}
