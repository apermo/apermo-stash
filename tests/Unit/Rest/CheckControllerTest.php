<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest;

use Apermo\LinkStash\Rest\CheckController;
use Apermo\LinkStash\Rest\Permissions;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Tests the GET /check?url= endpoint controller.
 */
class CheckControllerTest extends TestCase {

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
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url ) => \parse_url( $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
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
	 * Verifies register_routes registers the /check route on the namespace.
	 *
	 * @return void
	 */
	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->withArgs(
				static function ( string $rest_namespace, string $route, array $config ): bool {
					if ( $rest_namespace !== 'linkstash/v1' || $route !== '/check' ) {
						return false;
					}
					$permission = $config[0]['permission_callback'] ?? null;

					return $permission === [ Permissions::class, 'require_edit_posts' ];
				},
			);

		( new CheckController() )->register_routes( 'linkstash/v1' );
	}

	/**
	 * Verifies check returns exists=false on an unparseable URL.
	 *
	 * @return void
	 */
	public function test_check_returns_false_for_invalid_url(): void {
		$request = $this->request( 'not a url' );

		$response = ( new CheckController() )->check( $request );

		self::assertSame( [ 'exists' => false ], $response->data );
	}

	/**
	 * Verifies check returns exists=true with the post id when WP_Query finds a match.
	 *
	 * @return void
	 */
	public function test_check_returns_match_id(): void {
		WP_Query::$results[] = [ 'posts' => [ 42 ] ];

		$response = ( new CheckController() )->check( $this->request( 'https://example.tld/x' ) );

		self::assertSame(
			[
				'exists' => true,
				'id'     => 42,
			],
			$response->data,
		);
	}

	/**
	 * Verifies check returns exists=false when WP_Query returns nothing.
	 *
	 * @return void
	 */
	public function test_check_returns_false_on_no_match(): void {
		WP_Query::$results[] = [ 'posts' => [] ];

		$response = ( new CheckController() )->check( $this->request( 'https://example.tld/x' ) );

		self::assertSame( [ 'exists' => false ], $response->data );
	}

	/**
	 * Builds a request stub returning the given URL and no other params.
	 *
	 * @param string $url URL value.
	 *
	 * @return WP_REST_Request
	 */
	private function request( string $url ): WP_REST_Request {
		$request         = new WP_REST_Request();
		$request->params = [ 'url' => $url ];

		return $request;
	}
}
