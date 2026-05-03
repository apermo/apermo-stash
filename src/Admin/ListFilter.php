<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use WP_Query;

/**
 * Wires URL-parameter filters on the bookmark list table.
 *
 * `?favorite=1` narrows the list to favorited bookmarks; the standard
 * `?linkstash_tag=<slug>` taxonomy filter is handled by core.
 */
class ListFilter {

	/**
	 * Hooks `pre_get_posts` to add the favorite meta_query when the
	 * caller passes `?favorite=1` on the bookmark list screen.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre_get_posts', [ $this, 'apply_favorite_filter' ] );
	}

	/**
	 * Adds a `_linkstash_favorite = 1` meta_query when the URL says so.
	 *
	 * @param WP_Query $query Current query.
	 *
	 * @return void
	 */
	public function apply_favorite_filter( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'post_type' ) !== BookmarkPostType::POST_TYPE ) {
			return;
		}

		// Nonce verification: list-table filters are read-only URL params,
		// not actions; WP core's own list tables use `$_GET` here too.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['favorite'] ) || $_GET['favorite'] !== '1' ) {
			return;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$existing = $query->get( 'meta_query' );
		$existing = \is_array( $existing ) ? $existing : [];

		$existing['linkstash_favorite'] = [
			'key'   => BookmarkMeta::META_FAVORITE,
			'value' => '1',
		];
		$query->set( 'meta_query', $existing );
	}
}
