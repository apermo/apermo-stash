<?php

declare(strict_types=1);

namespace Apermo\Stash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\Stash\PostType\BookmarkMeta;
use Apermo\Stash\PostType\BookmarkPostType;
use Apermo\Stash\PostType\TagTaxonomy;

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

		echo wp_kses_post( \implode( ', ', $links ) );
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
			esc_html( $is_public ? __( 'Public', 'apermo-stash' ) : __( 'Private', 'apermo-stash' ) ),
		);
	}

	/**
	 * Renders the favorite column — a star when set, em-dash otherwise.
	 *
	 * @param int $post_id Bookmark post ID.
	 *
	 * @return void
	 */
	private static function render_favorite( int $post_id ): void {
		$favorite = (bool) get_post_meta( $post_id, BookmarkMeta::META_FAVORITE, true );

		echo $favorite
			? '<span aria-label="' . esc_attr__( 'Favorite', 'apermo-stash' ) . '">&#9733;</span>'
			: '—';
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
			'cb'               => $columns['cb'] ?? '<input type="checkbox" />',
			'title'            => esc_html__( 'Title', 'apermo-stash' ),
			'url'              => esc_html__( 'URL', 'apermo-stash' ),
			'apermo_stash_tag' => esc_html__( 'Tags', 'apermo-stash' ),
			'visibility'       => esc_html__( 'Visibility', 'apermo-stash' ),
			'favorite'         => esc_html__( 'Favorite', 'apermo-stash' ),
			'date'             => $columns['date'] ?? esc_html__( 'Date', 'apermo-stash' ),
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
			'apermo_stash_tag' => self::render_tags( $post_id ),
			'visibility'    => self::render_visibility( $post_id ),
			'favorite'      => self::render_favorite( $post_id ),
			default         => null,
		};
	}
}
