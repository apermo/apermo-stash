<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\ListFilter;
use Apermo\Stash\PostType\BookmarkMeta;
use Apermo\Stash\PostType\BookmarkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Query;

/**
 * Tests the ?favorite=1 admin list-table filter.
 */
class ListFilterTest extends TestCase {

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$_GET = [];
		Functions\when( 'is_admin' )->justReturn( true );
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		$_GET = [];
	}

	/**
	 * Verifies register hooks the filter on pre_get_posts.
	 *
	 * @return void
	 */
	public function test_register_hooks_pre_get_posts(): void {
		$filter = new ListFilter();
		$filter->register();

		self::assertNotFalse( has_action( 'pre_get_posts', [ $filter, 'apply_favorite_filter' ] ) );
	}

	/**
	 * Verifies the meta_query is added when ?favorite=1 is present on the bookmark CPT main query.
	 *
	 * @return void
	 */
	public function test_apply_favorite_filter_sets_meta_query(): void {
		$_GET = [ 'favorite' => '1' ];

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );
		$query->shouldReceive( 'get' )->with( 'post_type' )->andReturn( BookmarkPostType::POST_TYPE );
		$query->shouldReceive( 'get' )->with( 'meta_query' )->andReturn( '' );

		$captured = null;
		$query->shouldReceive( 'set' )->with(
			'meta_query',
			Mockery::on(
				static function ( array $value ) use ( &$captured ): bool {
					$captured = $value;
					return true;
				},
			),
		);

		( new ListFilter() )->apply_favorite_filter( $query );

		self::assertIsArray( $captured );
		self::assertSame( BookmarkMeta::META_FAVORITE, $captured['linkstash_favorite']['key'] );
		self::assertSame( '1', $captured['linkstash_favorite']['value'] );
	}

	/**
	 * Verifies the filter is a no-op for non-bookmark queries.
	 *
	 * @return void
	 */
	public function test_apply_favorite_filter_skips_other_post_types(): void {
		$_GET = [ 'favorite' => '1' ];

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );
		$query->shouldReceive( 'get' )->with( 'post_type' )->andReturn( 'post' );
		$query->shouldNotReceive( 'set' );

		( new ListFilter() )->apply_favorite_filter( $query );
	}

	/**
	 * Verifies the filter is a no-op when the URL parameter is absent.
	 *
	 * @return void
	 */
	public function test_apply_favorite_filter_skips_when_param_absent(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );
		$query->shouldReceive( 'get' )->with( 'post_type' )->andReturn( BookmarkPostType::POST_TYPE );
		$query->shouldNotReceive( 'set' );

		( new ListFilter() )->apply_favorite_filter( $query );
	}

	/**
	 * Verifies the filter is a no-op for sub-queries (not main).
	 *
	 * @return void
	 */
	public function test_apply_favorite_filter_skips_non_main_query(): void {
		$_GET = [ 'favorite' => '1' ];

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( false );
		$query->shouldNotReceive( 'set' );

		( new ListFilter() )->apply_favorite_filter( $query );
	}
}
