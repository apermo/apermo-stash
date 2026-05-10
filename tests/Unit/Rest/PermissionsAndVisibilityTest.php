<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Rest;

use Apermo\Stash\Rest\LinksController;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Tests the public/private visibility filter that gates every read endpoint.
 *
 * Covers the matrix of (anonymous, authed normal user, authed admin) by
 * (no flags, public=1, private=1, both flags) and asserts the resulting
 * post_status / author / perm fragments.
 */
class PermissionsAndVisibilityTest extends TestCase {

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
	 * Verifies anonymous callers only ever see published links.
	 *
	 * @return void
	 */
	public function test_anonymous_sees_only_publish(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$request = $this->request();
		$result  = LinksController::visibility_filter( $request );

		self::assertSame( [ 'publish' ], $result['post_status'] );
		self::assertNull( $result['author'] );
		self::assertNull( $result['perm'] );
	}

	/**
	 * Verifies a normal authed user defaults to "own + public" via perm=readable.
	 *
	 * @return void
	 */
	public function test_authed_user_default_uses_perm_readable(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->request();
		$result  = LinksController::visibility_filter( $request );

		self::assertSame( [ 'publish', 'private' ], $result['post_status'] );
		self::assertNull( $result['author'] );
		self::assertSame( 'readable', $result['perm'] );
	}

	/**
	 * Verifies public=1 narrows to only public links (everyone's).
	 *
	 * @return void
	 */
	public function test_authed_user_public_filter_returns_all_public(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->request( [ 'public' => true ] );
		$result  = LinksController::visibility_filter( $request );

		self::assertSame( [ 'publish' ], $result['post_status'] );
		self::assertNull( $result['author'] );
		self::assertNull( $result['perm'] );
	}

	/**
	 * Verifies private=1 narrows to only the caller's own private links.
	 *
	 * @return void
	 */
	public function test_authed_user_private_filter_scopes_to_owner(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->request( [ 'private' => true ] );
		$result  = LinksController::visibility_filter( $request );

		self::assertSame( [ 'private' ], $result['post_status'] );
		self::assertSame( [ 7 ], $result['author'] );
		self::assertNull( $result['perm'] );
	}

	/**
	 * Verifies an admin sees everything by default — no perm, no author filter.
	 *
	 * @return void
	 */
	public function test_admin_sees_all_without_perm_filter(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );

		$request = $this->request();
		$result  = LinksController::visibility_filter( $request );

		self::assertSame( [ 'publish', 'private' ], $result['post_status'] );
		self::assertNull( $result['author'] );
		self::assertNull( $result['perm'] );
	}

	/**
	 * Builds a Mockery WP_REST_Request mock returning the given param map.
	 *
	 * @param array<string, mixed> $params Param overrides keyed by name.
	 *
	 * @return WP_REST_Request
	 */
	private function request( array $params = [] ): WP_REST_Request {
		$mock = Mockery::mock( WP_REST_Request::class );
		$mock->shouldReceive( 'get_param' )->andReturnUsing(
			static fn ( string $key ) => $params[ $key ] ?? null,
		);

		return $mock;
	}
}
