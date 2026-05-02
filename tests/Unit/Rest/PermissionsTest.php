<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest;

use Apermo\LinkStash\Rest\Permissions;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Error;
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
}
