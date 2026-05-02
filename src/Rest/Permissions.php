<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Rest;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\PostType\BookmarkPostType;
use WP_Error;
use WP_REST_Request;

/**
 * Holds permission callbacks shared by the LinkStash REST controllers.
 */
class Permissions {

	/**
	 * Allows the request through unconditionally; visibility is enforced at
	 * the query level instead of denying access wholesale.
	 *
	 * @return bool
	 */
	public static function allow_anyone(): bool {
		return true;
	}

	/**
	 * Allows write requests when the resolved user can edit bookmarks.
	 *
	 * @return bool|WP_Error
	 */
	public static function require_edit_posts(): bool|WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'linkstash_forbidden',
				__( 'You are not allowed to create or modify bookmarks.', 'linkstash' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}

	/**
	 * Allows reads of a single bookmark when the bookmark is public, the
	 * caller owns it, or the caller can edit other users' posts.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_read_bookmark( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( $post === null || $post->post_type !== BookmarkPostType::POST_TYPE ) {
			return new WP_Error(
				'linkstash_not_found',
				__( 'Bookmark not found.', 'linkstash' ),
				[ 'status' => 404 ],
			);
		}

		if ( $post->post_status === 'publish' ) {
			return true;
		}

		$current_user = get_current_user_id();
		if ( $current_user > 0 && (int) $post->post_author === $current_user ) {
			return true;
		}

		if ( current_user_can( 'edit_others_posts' ) ) {
			return true;
		}

		return new WP_Error(
			'linkstash_forbidden',
			__( 'You are not allowed to view this bookmark.', 'linkstash' ),
			[ 'status' => 403 ],
		);
	}

	/**
	 * Allows updates when the user can edit the targeted bookmark.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_edit_bookmark( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'linkstash_forbidden',
				__( 'You are not allowed to edit this bookmark.', 'linkstash' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}

	/**
	 * Allows deletes when the user can delete the targeted bookmark.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_delete_bookmark( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new WP_Error(
				'linkstash_forbidden',
				__( 'You are not allowed to delete this bookmark.', 'linkstash' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}
}
