<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Rest;

use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\Url\Canonicalizer;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves the URL existence check used by browser extensions.
 */
class CheckController {

	/**
	 * Registers the check route.
	 *
	 * @param string $rest_namespace REST namespace.
	 *
	 * @return void
	 */
	public function register_routes( string $rest_namespace ): void {
		register_rest_route(
			$rest_namespace,
			'/check',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'check' ],
					'permission_callback' => [ Permissions::class, 'allow_anyone' ],
					'args'                => [
						'url' => [
							'type'     => 'string',
							'required' => true,
							'format'   => 'uri',
						],
					],
				],
			],
		);
	}

	/**
	 * Returns whether a bookmark for the given URL exists in the visible scope.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function check( WP_REST_Request $request ): WP_REST_Response {
		$url       = (string) $request->get_param( 'url' );
		$canonical = Canonicalizer::canonicalize( $url );

		if ( $canonical === '' ) {
			return rest_ensure_response( [ 'exists' => false ] );
		}

		$visibility = BookmarksController::visibility_filter( $request );

		$args = [
			'post_type'      => BookmarkPostType::POST_TYPE,
			'post_status'    => $visibility['post_status'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// Looking up by canonical URL is the whole purpose of this
			// endpoint; meta_query is the idiomatic shape.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [
				[
					'key'   => BookmarkMeta::META_URL_CANONICAL,
					'value' => $canonical,
				],
			],
		];

		if ( $visibility['author'] !== null ) {
			$args['author__in'] = $visibility['author'];
		}
		if ( $visibility['perm'] !== null ) {
			$args['perm'] = $visibility['perm'];
		}

		$query = new WP_Query( $args );

		if ( \count( $query->posts ) === 0 ) {
			return rest_ensure_response( [ 'exists' => false ] );
		}

		return rest_ensure_response(
			[
				'exists' => true,
				'id'     => (int) $query->posts[0],
			],
		);
	}
}
