<?php

declare(strict_types=1);

namespace Apermo\Stash\Rest;

\defined( 'ABSPATH' ) || exit();

use Apermo\Stash\PostType\LinkPostType;
use WP_Error;
use WP_REST_Request;

/**
 * Holds permission callbacks shared by the Apermo Stash REST controllers.
 */
class Permissions {

	/**
	 * Allows write requests when the resolved user can edit links.
	 *
	 * @return bool|WP_Error
	 */
	public static function require_edit_posts(): bool|WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'apermo_stash_forbidden',
				__( 'You are not allowed to create or modify links.', 'apermo-stash' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}

	/**
	 * Allows read-only requests against the bookmark library when the
	 * resolved user can edit links.
	 *
	 * Same capability check as `require_edit_posts` — link reads via
	 * `/check` and friends are editor-only by design — but the error
	 * message is phrased for a read context so callers see the right
	 * thing on a 403.
	 *
	 * @return bool|WP_Error
	 */
	public static function require_read_links(): bool|WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'apermo_stash_forbidden',
				__( 'You are not allowed to read links.', 'apermo-stash' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}

	/**
	 * Allows reads of a single link when the link is public, the
	 * caller owns it, or the caller can edit other users' posts.
	 *
	 * Returns 404 (not 403) when the caller is not authorised to read,
	 * so the response is indistinguishable from "post does not exist" —
	 * preventing ID-enumeration that would otherwise reveal the
	 * existence of private links.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_read_link( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( $post !== null && $post->post_type === LinkPostType::POST_TYPE ) {
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
		}

		return new WP_Error(
			'apermo_stash_not_found',
			__( 'Link not found.', 'apermo-stash' ),
			[ 'status' => 404 ],
		);
	}

	/**
	 * Allows updates when the user can edit the targeted link.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_edit_link( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'apermo_stash_forbidden',
				__( 'You are not allowed to edit this link.', 'apermo-stash' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}

	/**
	 * Allows deletes when the user can delete the targeted link.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_delete_link( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new WP_Error(
				'apermo_stash_forbidden',
				__( 'You are not allowed to delete this link.', 'apermo-stash' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}
}
