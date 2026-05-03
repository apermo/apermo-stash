<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Admin;

use Apermo\LinkStash\Admin\ListColumns;
use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Tests the bookmark list table column hooks and renderers.
 */
class ListColumnsTest extends TestCase {

	/**
	 * Sets up Brain Monkey and a permissive set of WP stubs for the renderers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Verifies register hooks the column filter and renderer for the CPT.
	 *
	 * @return void
	 */
	public function test_register_hooks_column_filters(): void {
		$columns = new ListColumns();
		$columns->register();

		$post_type = BookmarkPostType::POST_TYPE;
		self::assertNotFalse( has_filter( "manage_{$post_type}_posts_columns", [ $columns, 'filter_columns' ] ) );
		self::assertNotFalse( has_action( "manage_{$post_type}_posts_custom_column", [ $columns, 'render_column' ] ) );
	}

	/**
	 * Verifies filter_columns adds the LinkStash columns.
	 *
	 * @return void
	 */
	public function test_filter_columns_returns_linkstash_columns(): void {
		$result = ( new ListColumns() )->filter_columns(
			[
				'cb'   => '<input type="checkbox" />',
				'date' => 'Date',
			],
		);

		self::assertArrayHasKey( 'cb', $result );
		self::assertArrayHasKey( 'title', $result );
		self::assertArrayHasKey( 'url', $result );
		self::assertArrayHasKey( 'taxonomy-linkstash_tag', $result );
		self::assertArrayHasKey( 'visibility', $result );
		self::assertArrayHasKey( 'flags', $result );
		self::assertArrayHasKey( 'date', $result );
	}

	/**
	 * Verifies the URL column renders an anchor for a saved URL.
	 *
	 * @return void
	 */
	public function test_render_url_column(): void {
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id, string $key ): string => $key === BookmarkMeta::META_URL ? 'https://example.tld' : '',
		);

		$output = $this->capture_render( 'url', 7 );

		self::assertStringContainsString( '<a href="https://example.tld"', $output );
		self::assertStringContainsString( 'https://example.tld</a>', $output );
	}

	/**
	 * Verifies the URL column renders nothing for a missing URL.
	 *
	 * @return void
	 */
	public function test_render_url_column_empty(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$output = $this->capture_render( 'url', 7 );

		self::assertSame( '', $output );
	}

	/**
	 * Verifies render_column does not handle the taxonomy-linkstash_tag
	 * column itself — that column is rendered by core's
	 * `show_admin_column` mechanism, which emits clickable filter links.
	 *
	 * @return void
	 */
	public function test_render_tags_column_is_core_owned(): void {
		$output = $this->capture_render( 'taxonomy-linkstash_tag', 7 );

		self::assertSame( '', $output );
	}

	/**
	 * Verifies the visibility column renders a public badge.
	 *
	 * @return void
	 */
	public function test_render_visibility_public(): void {
		$post              = new WP_Post();
		$post->post_status = 'publish';
		Functions\when( 'get_post' )->justReturn( $post );

		$output = $this->capture_render( 'visibility', 7 );

		self::assertStringContainsString( 'linkstash-badge--public', $output );
		self::assertStringContainsString( 'Public', $output );
	}

	/**
	 * Verifies the visibility column renders a private badge.
	 *
	 * @return void
	 */
	public function test_render_visibility_private(): void {
		$post              = new WP_Post();
		$post->post_status = 'private';
		Functions\when( 'get_post' )->justReturn( $post );

		$output = $this->capture_render( 'visibility', 7 );

		self::assertStringContainsString( 'linkstash-badge--private', $output );
		self::assertStringContainsString( 'Private', $output );
	}

	/**
	 * Verifies the flags column renders Unread / Archived chips when set.
	 *
	 * @return void
	 */
	public function test_render_flags_column_with_both_flags(): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key ): bool {
				return \in_array( $key, [ BookmarkMeta::META_UNREAD, BookmarkMeta::META_ARCHIVED ], true );
			},
		);

		$output = $this->capture_render( 'flags', 7 );

		self::assertSame( 'Unread, Archived', $output );
	}

	/**
	 * Verifies the flags column renders an em-dash when no flags are set.
	 *
	 * @return void
	 */
	public function test_render_flags_column_empty(): void {
		Functions\when( 'get_post_meta' )->justReturn( false );

		$output = $this->capture_render( 'flags', 7 );

		self::assertSame( '—', $output );
	}

	/**
	 * Verifies render_column ignores unknown column keys.
	 *
	 * @return void
	 */
	public function test_render_column_ignores_unknown_columns(): void {
		$output = $this->capture_render( 'unrelated', 7 );

		self::assertSame( '', $output );
	}

	/**
	 * Captures the output of render_column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 *
	 * @return string
	 */
	private function capture_render( string $column, int $post_id ): string {
		\ob_start();
		( new ListColumns() )->render_column( $column, $post_id );

		return (string) \ob_get_clean();
	}

	/**
	 * Suppresses the "unused" warning for the imported TagTaxonomy class.
	 *
	 * @return void
	 */
	public function test_taxonomy_constant_is_imported(): void {
		self::assertSame( 'linkstash_tag', TagTaxonomy::TAXONOMY );
	}
}
