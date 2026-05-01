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
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$visibility = BookmarksController::visibility_filter( $request );

		$terms = get_terms(
			[
				'taxonomy'   => TagTaxonomy::TAXONOMY,
				'hide_empty' => false,
			],
		);

		if ( ! \is_array( $terms ) ) {
			return rest_ensure_response( [] );
		}

		$items = [];
		foreach ( $terms as $term ) {
			$count = $this->count_for_term( $term->term_id, $visibility );
			if ( $count === 0 ) {
				continue;
			}

			$items[] = [
				'id'    => $term->term_id,
				'slug'  => $term->slug,
				'name'  => $term->name,
				'count' => $count,
			];
		}

		\usort(
			$items,
			static fn ( array $left, array $right ): int => \strcasecmp( $left['name'], $right['name'] ),
		);

		return rest_ensure_response( $items );
	}

	/**
	 * Counts the bookmarks tagged with the given term that satisfy the visibility filter.
	 *
	 * @param int                                                            $term_id    Tag term ID.
	 * @param array{post_status: array<int, string>, author: list<int>|null} $visibility Visibility constraints.
	 *
	 * @return int
	 */
	private function count_for_term( int $term_id, array $visibility ): int {
		$args = [
			'post_type'      => BookmarkPostType::POST_TYPE,
			'post_status'    => $visibility['post_status'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'tax_query'      => [
				[
					'taxonomy' => TagTaxonomy::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
			'no_found_rows'  => false,
		];

		if ( $visibility['author'] !== null ) {
			$args['author__in'] = $visibility['author'];
		}

		$query = new WP_Query( $args );

		return $query->found_posts;
	}
}
