<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;

/**
 * Customises the bookmark CPT list table columns.
 */
class ListColumns {

	/**
	 * Renders the URL column.
	 *
	 * @param int $post_id Bookmark post ID.
	 *
	 * @return void
	 */
	private static function render_url( int $post_id ): void {
		$url = (string) get_post_meta( $post_id, BookmarkMeta::META_URL, true );
		if ( $url === '' ) {
			return;
		}
		\printf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			esc_html( $url ),
		);
	}

	/**
	 * Renders the tags column with clickable per-tag filter links.
	 *
	 * @param int $post_id Bookmark post ID.
	 *
	 * @return void
	 */
	private static function render_tags( int $post_id ): void {
		$terms = get_the_terms( $post_id, TagTaxonomy::TAXONOMY );
		if ( ! \is_array( $terms ) || $terms === [] ) {
			echo '—';
			return;
		}

		$links = [];
		foreach ( $terms as $term ) {
			$url = add_query_arg(
				[
					'post_type'           => BookmarkPostType::POST_TYPE,
					TagTaxonomy::TAXONOMY => $term->slug,
				],
				admin_url( 'edit.php' ),
			);

			$links[] = \sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html( $term->name ),
			);
		}

		// Each anchor was built from esc_url + esc_html; the join is a
		// constant separator. No user data flows in raw.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo \implode( ', ', $links );
	}

	/**
	 * Renders the visibility column.
	 *
	 * @param int $post_id Bookmark post ID.
	 *
	 * @return void
	 */
	private static function render_visibility( int $post_id ): void {
		$post      = get_post( $post_id );
		$is_public = $post !== null && $post->post_status === 'publish';
		\printf(
			'<span class="linkstash-badge linkstash-badge--%1$s">%2$s</span>',
			esc_attr( $is_public ? 'public' : 'private' ),
			esc_html( $is_public ? __( 'Public', 'linkstash' ) : __( 'Private', 'linkstash' ) ),
		);
	}

	/**
	 * Renders the flags (unread / archived) column.
	 *
	 * @param int $post_id Bookmark post ID.
	 *
	 * @return void
	 */
	private static function render_flags( int $post_id ): void {
		$badges = [];
		if ( (bool) get_post_meta( $post_id, BookmarkMeta::META_UNREAD, true ) ) {
			$badges[] = __( 'Unread', 'linkstash' );
		}
		if ( (bool) get_post_meta( $post_id, BookmarkMeta::META_ARCHIVED, true ) ) {
			$badges[] = __( 'Archived', 'linkstash' );
		}
		if ( $badges === [] ) {
			echo '—';
			return;
		}
		echo esc_html( \implode( ', ', $badges ) );
	}

	/**
	 * Hooks the column filters and renderers.
	 *
	 * @return void
	 */
	public function register(): void {
		$post_type = BookmarkPostType::POST_TYPE;

		add_filter( "manage_{$post_type}_posts_columns", [ $this, 'filter_columns' ] );
		add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_column' ], 10, 2 );
	}

	/**
	 * Replaces the default columns with LinkStash-specific ones.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public function filter_columns( array $columns ): array {
		return [
			'cb'            => $columns['cb'] ?? '<input type="checkbox" />',
			'title'         => __( 'Title', 'linkstash' ),
			'url'           => __( 'URL', 'linkstash' ),
			'linkstash_tag' => __( 'Tags', 'linkstash' ),
			'visibility'    => __( 'Visibility', 'linkstash' ),
			'flags'         => __( 'Flags', 'linkstash' ),
			'date'          => $columns['date'] ?? __( 'Date', 'linkstash' ),
		];
	}

	/**
	 * Renders the value for a custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Bookmark post ID.
	 *
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		match ( $column ) {
			'url'           => self::render_url( $post_id ),
			'linkstash_tag' => self::render_tags( $post_id ),
			'visibility'    => self::render_visibility( $post_id ),
			'flags'         => self::render_flags( $post_id ),
			default         => null,
		};
	}
}
