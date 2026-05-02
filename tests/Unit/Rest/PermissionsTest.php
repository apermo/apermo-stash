<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest;

use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\Rest\Permissions;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Post;
use WP_REST_Request;

/**
 * Tests the static permission callbacks shared by the REST controllers.
 */
class PermissionsTest extends TestCase {

	/**
	 * Sets up Brain Monkey with WP_Error stub.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
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
	 * Verifies allow_anyone is unconditional.
	 *
	 * @return void
	 */
	public function test_allow_anyone(): void {
		self::assertTrue( Permissions::allow_anyone() );
	}

	/**
	 * Verifies require_edit_posts returns an error for users without the cap.
	 *
	 * @return void
	 */
	public function test_require_edit_posts_denies_when_cap_missing(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = Permissions::require_edit_posts();

		self::assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Verifies require_edit_posts allows users with edit_posts.
	 *
	 * @return void
	 */
	public function test_require_edit_posts_allows_when_cap_present(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		self::assertTrue( Permissions::require_edit_posts() );
	}

	/**
	 * Verifies can_edit_bookmark delegates to current_user_can( 'edit_post', id ).
	 *
	 * @return void
	 */
	public function test_can_edit_bookmark_passes_id_to_cap_check(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 42 )
			->andReturn( true );

		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )->with( 'id' )->andReturn( 42 );

		self::assertTrue( Permissions::can_edit_bookmark( $request ) );
	}

	/**
	 * Verifies can_delete_bookmark delegates to current_user_can( 'delete_post', id ).
	 *
	 * @return void
	 */
	public function test_can_delete_bookmark_passes_id_to_cap_check(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'delete_post', 42 )
			->andReturn( false );

		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )->with( 'id' )->andReturn( 42 );

		$result = Permissions::can_delete_bookmark( $request );
		self::assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Verifies can_read_bookmark returns 404 for an unknown post.
	 *
	 * @return void
	 */
	public function test_can_read_bookmark_404_when_post_missing(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 999 ];

		$result = Permissions::can_read_bookmark( $request );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_not_found', $result->code );
	}

	/**
	 * Verifies can_read_bookmark allows anyone when the bookmark is public.
	 *
	 * @return void
	 */
	public function test_can_read_bookmark_allows_publish(): void {
		$post              = new WP_Post();
		$post->post_type   = BookmarkPostType::POST_TYPE;
		$post->post_status = 'publish';
		Functions\when( 'get_post' )->justReturn( $post );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 7 ];

		self::assertTrue( Permissions::can_read_bookmark( $request ) );
	}

	/**
	 * Verifies can_read_bookmark allows the owner on a private bookmark.
	 *
	 * @return void
	 */
	public function test_can_read_bookmark_allows_owner_on_private(): void {
		$post              = new WP_Post();
		$post->post_type   = BookmarkPostType::POST_TYPE;
		$post->post_status = 'private';
		$post->post_author = 11;
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_current_user_id' )->justReturn( 11 );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 7 ];

		self::assertTrue( Permissions::can_read_bookmark( $request ) );
	}

	/**
	 * Verifies can_read_bookmark allows users with edit_others_posts.
	 *
	 * @return void
	 */
	public function test_can_read_bookmark_allows_admin(): void {
		$post              = new WP_Post();
		$post->post_type   = BookmarkPostType::POST_TYPE;
		$post->post_status = 'private';
		$post->post_author = 11;
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'current_user_can' )->justReturn( true );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 7 ];

		self::assertTrue( Permissions::can_read_bookmark( $request ) );
	}

	/**
	 * Verifies can_read_bookmark denies a non-owner without admin caps.
	 *
	 * @return void
	 */
	public function test_can_read_bookmark_denies_non_owner(): void {
		$post              = new WP_Post();
		$post->post_type   = BookmarkPostType::POST_TYPE;
		$post->post_status = 'private';
		$post->post_author = 11;
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_current_user_id' )->justReturn( 22 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 7 ];

		$result = Permissions::can_read_bookmark( $request );
		self::assertInstanceOf( WP_Error::class, $result );
	}
}
