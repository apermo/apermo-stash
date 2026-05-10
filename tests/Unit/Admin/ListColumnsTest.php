<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\ListColumns;
use Apermo\Stash\PostType\LinkMeta;
use Apermo\Stash\PostType\LinkPostType;
use Apermo\Stash\PostType\TagTaxonomy;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Term;

/**
 * Tests the link list table column hooks and renderers.
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
		Functions\when( 'wp_kses_post' )->returnArg();
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

		$post_type = LinkPostType::POST_TYPE;
		self::assertNotFalse( has_filter( "manage_{$post_type}_posts_columns", [ $columns, 'filter_columns' ] ) );
		self::assertNotFalse( has_action( "manage_{$post_type}_posts_custom_column", [ $columns, 'render_column' ] ) );
	}

	/**
	 * Verifies filter_columns adds the Apermo Stash columns.
	 *
	 * @return void
	 */
	public function test_filter_columns_returns_apermo_stash_columns(): void {
		$result = ( new ListColumns() )->filter_columns(
			[
				'cb'   => '<input type="checkbox" />',
				'date' => 'Date',
			],
		);

		self::assertArrayHasKey( 'cb', $result );
		self::assertArrayHasKey( 'title', $result );
		self::assertArrayHasKey( 'url', $result );
		self::assertArrayHasKey( 'apermo_stash_tag', $result );
		self::assertArrayHasKey( 'visibility', $result );
		self::assertArrayHasKey( 'favorite', $result );
		self::assertArrayHasKey( 'date', $result );
	}

	/**
	 * Verifies the URL column renders an anchor for a saved URL.
	 *
	 * @return void
	 */
	public function test_render_url_column(): void {
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id, string $key ): string => $key === LinkMeta::META_URL ? 'https://example.tld' : '',
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
	 * Verifies the tags column emits an anchor per tag pointing at the
	 * filter-by-tag URL.
	 *
	 * @return void
	 */
	public function test_render_tags_column_renders_filter_links(): void {
		$reading       = new WP_Term();
		$reading->name = 'reading';
		$reading->slug = 'reading';
		$archive       = new WP_Term();
		$archive->name = 'archive';
		$archive->slug = 'archive';
		Functions\when( 'get_the_terms' )->justReturn( [ $reading, $archive ] );
		Functions\when( 'add_query_arg' )->alias(
			static fn ( array $args, string $url ): string => $url . '?' . \http_build_query( $args ),
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $path ): string => '/wp-admin/' . $path );

		$output = $this->capture_render( 'apermo_stash_tag', 7 );

		self::assertStringContainsString( 'post_type=apermo_stash_link', $output );
		self::assertStringContainsString( 'apermo_stash_tag=reading', $output );
		self::assertStringContainsString( 'apermo_stash_tag=archive', $output );
		self::assertStringContainsString( '>reading</a>', $output );
		self::assertStringContainsString( '>archive</a>', $output );
		self::assertStringContainsString( ', ', $output );
	}

	/**
	 * Verifies the tags column renders an em-dash when no terms are attached.
	 *
	 * @return void
	 */
	public function test_render_tags_column_empty(): void {
		Functions\when( 'get_the_terms' )->justReturn( [] );

		$output = $this->capture_render( 'apermo_stash_tag', 7 );

		self::assertSame( '—', $output );
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

		self::assertStringContainsString( 'apermo-stash-badge--public', $output );
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

		self::assertStringContainsString( 'apermo-stash-badge--private', $output );
		self::assertStringContainsString( 'Private', $output );
	}

	/**
	 * Verifies the favorite column renders a star when the flag is set.
	 *
	 * @return void
	 */
	public function test_render_favorite_column_renders_star(): void {
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id, string $key ): bool => $key === LinkMeta::META_FAVORITE,
		);

		$output = $this->capture_render( 'favorite', 7 );

		self::assertStringContainsString( '&#9733;', $output );
		self::assertStringContainsString( 'aria-label="Favorite"', $output );
	}

	/**
	 * Verifies the favorite column renders an em-dash when not set.
	 *
	 * @return void
	 */
	public function test_render_favorite_column_empty(): void {
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'get_post_meta' )->justReturn( false );

		$output = $this->capture_render( 'favorite', 7 );

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
		self::assertSame( 'apermo_stash_tag', TagTaxonomy::TAXONOMY );
	}
}
