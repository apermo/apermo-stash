<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest;

use Apermo\LinkStash\Rest\TagsController;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

/**
 * Tests the GET /tags endpoint controller.
 */
class TagsControllerTest extends TestCase {

	/**
	 * Builds a minimal term-shaped object.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $slug    Term slug.
	 * @param string $name    Term name.
	 * @param int    $count   Bookmark count.
	 *
	 * @return object
	 */
	private static function term( int $term_id, string $slug, string $name, int $count ): WP_Term {
		$term          = new WP_Term();
		$term->term_id = $term_id;
		$term->slug    = $slug;
		$term->name    = $name;
		$term->count   = $count;

		return $term;
	}

	/**
	 * Sets up Brain Monkey and the WP_Query stub queue.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		WP_Query::$results = [];

		Functions\when( '__' )->returnArg();
		Functions\when( 'rest_ensure_response' )->alias( static fn ( $data ) => new WP_REST_Response( $data ) );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( 'current_user_can' )->justReturn( false );
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		WP_Query::$results = [];
	}

	/**
	 * Verifies register_routes registers the /tags route.
	 *
	 * @return void
	 */
	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->withArgs(
				static fn ( string $rest_namespace, string $route ): bool => $rest_namespace === 'linkstash/v1' && $route === '/tags',
			);

		( new TagsController() )->register_routes( 'linkstash/v1' );
	}

	/**
	 * Verifies list_items returns an empty array when the user has no visible bookmarks.
	 *
	 * @return void
	 */
	public function test_list_items_empty_when_no_visible_bookmarks(): void {
		WP_Query::$results[] = [ 'posts' => [] ];

		$response = ( new TagsController() )->list_items( new WP_REST_Request() );

		self::assertSame( [], $response->data );
	}

	/**
	 * Verifies list_items returns formatted tag entries from get_terms.
	 *
	 * @return void
	 */
	public function test_list_items_returns_terms_with_counts(): void {
		WP_Query::$results[] = [ 'posts' => [ 1, 2, 3 ] ];

		Functions\when( 'get_terms' )->justReturn(
			[
				self::term( 10, 'reading', 'Reading', 2 ),
				self::term( 11, 'archive', 'Archive', 1 ),
			],
		);

		$response = ( new TagsController() )->list_items( new WP_REST_Request() );

		self::assertCount( 2, $response->data );
		self::assertSame( 10, $response->data[0]['id'] );
		self::assertSame( 'reading', $response->data[0]['slug'] );
		self::assertSame( 2, $response->data[0]['count'] );
	}

	/**
	 * Verifies list_items handles a non-array get_terms result defensively.
	 *
	 * @return void
	 */
	public function test_list_items_handles_get_terms_error(): void {
		WP_Query::$results[] = [ 'posts' => [ 1 ] ];
		Functions\when( 'get_terms' )->justReturn( false );

		$response = ( new TagsController() )->list_items( new WP_REST_Request() );

		self::assertSame( [], $response->data );
	}
}
