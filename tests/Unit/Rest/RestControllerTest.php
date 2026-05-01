<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest;

use Apermo\LinkStash\Rest\BookmarksController;
use Apermo\LinkStash\Rest\CheckController;
use Apermo\LinkStash\Rest\RestController;
use Apermo\LinkStash\Rest\TagsController;
use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests the REST controller registrar.
 */
class RestControllerTest extends TestCase {

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
	 * Verifies register hooks rest_api_init.
	 *
	 * @return void
	 */
	public function test_register_hooks_rest_api_init(): void {
		$bookmarks = Mockery::mock( BookmarksController::class );
		$tags      = Mockery::mock( TagsController::class );
		$check     = Mockery::mock( CheckController::class );

		$rest = new RestController( $bookmarks, $tags, $check );
		$rest->register();

		self::assertNotFalse( has_action( 'rest_api_init', [ $rest, 'register_routes' ] ) );
	}

	/**
	 * Verifies register_routes delegates to each child controller with the namespace.
	 *
	 * @return void
	 */
	public function test_register_routes_delegates_to_children(): void {
		$bookmarks = Mockery::mock( BookmarksController::class );
		$tags      = Mockery::mock( TagsController::class );
		$check     = Mockery::mock( CheckController::class );

		$bookmarks->shouldReceive( 'register_routes' )->once()->with( RestController::NAMESPACE );
		$tags->shouldReceive( 'register_routes' )->once()->with( RestController::NAMESPACE );
		$check->shouldReceive( 'register_routes' )->once()->with( RestController::NAMESPACE );

		( new RestController( $bookmarks, $tags, $check ) )->register_routes();
	}

	/**
	 * Confirms the namespace constant is the documented value.
	 *
	 * @return void
	 */
	public function test_namespace_constant(): void {
		self::assertSame( 'linkstash/v1', RestController::NAMESPACE );
	}
}
