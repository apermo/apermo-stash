<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Rest;

use Apermo\Stash\Rest\Permissions;
use Apermo\Stash\Rest\TagsController;
use Apermo\Stash\Tests\Unit\Rest\Fixtures\WpdbMockForTags;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Tests the GET /tags endpoint controller.
 */
class TagsControllerTest extends TestCase {

	/**
	 * Holds the wpdb mock used by the SUT.
	 *
	 * @var WpdbMockForTags
	 */
	private WpdbMockForTags $wpdb;

	/**
	 * Holds the canned rows the next get_results call will return.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $next_rows = [];

	/**
	 * Sets up Brain Monkey and a $wpdb mock that captures prepare()/get_results().
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'rest_ensure_response' )->alias( static fn ( $data ) => new WP_REST_Response( $data ) );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_cache_get_last_changed' )->justReturn( '0' );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( static fn ( $data ) => \json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		$rows       = &$this->next_rows;
		$this->wpdb = new WpdbMockForTags( $rows );

		// Tests legitimately overwrite the wpdb global to inject a stub;
		// the sniff is aimed at production code mutating WP globals.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $GLOBALS['wpdb'] );
	}

	/**
	 * Verifies register_routes registers the /tags route with the
	 * `require_read_links` permission_callback (anonymous callers blocked).
	 *
	 * @return void
	 */
	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->withArgs(
				static function ( string $rest_namespace, string $route, array $args ): bool {
					return $rest_namespace === 'apermo-stash/v1'
						&& $route === '/tags'
						&& $args[0]['permission_callback']
							=== [ Permissions::class, 'require_read_links' ];
				},
			);

		( new TagsController() )->register_routes( 'apermo-stash/v1' );
	}

	/**
	 * Verifies list_items maps the wpdb rows into the response shape.
	 *
	 * @return void
	 */
	public function test_list_items_returns_term_rows(): void {
		$this->next_rows = [
			[
				'id'    => '10',
				'slug'  => 'reading',
				'name'  => 'Reading',
				'count' => '2',
			],
			[
				'id'    => '11',
				'slug'  => 'archive',
				'name'  => 'Archive',
				'count' => '1',
			],
		];

		$response = ( new TagsController() )->list_items( new WP_REST_Request() );

		self::assertCount( 2, $response->data );
		self::assertSame( 10, $response->data[0]['id'] );
		self::assertSame( 'reading', $response->data[0]['slug'] );
		self::assertSame( 'Reading', $response->data[0]['name'] );
		self::assertSame( 2, $response->data[0]['count'] );
	}

	/**
	 * Verifies list_items is empty when wpdb returns no rows.
	 *
	 * @return void
	 */
	public function test_list_items_empty_when_no_rows(): void {
		$this->next_rows = [];

		$response = ( new TagsController() )->list_items( new WP_REST_Request() );

		self::assertSame( [], $response->data );
	}

	/**
	 * Verifies the anonymous-caller WHERE clause: post_status='publish', no author or perm extras.
	 *
	 * @return void
	 */
	public function test_build_where_clause_anonymous(): void {
		[ $where, $args ] = TagsController::build_where_clause(
			[
				'post_status' => [ 'publish' ],
				'author'      => null,
				'perm'        => null,
			],
		);

		self::assertStringContainsString( 'p.post_type = %s', $where );
		self::assertStringContainsString( 'tt.taxonomy = %s', $where );
		self::assertStringContainsString( 'p.post_status IN (%s)', $where );
		self::assertStringNotContainsString( 'post_author', $where );
		self::assertSame( [ 'apermo_stash_link', 'apermo_stash_tag', 'publish' ], $args );
	}

	/**
	 * Verifies the authed-normal-user WHERE clause includes the perm=readable OR clause.
	 *
	 * @return void
	 */
	public function test_build_where_clause_readable_perm_includes_author_or_publish(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );

		[ $where, $args ] = TagsController::build_where_clause(
			[
				'post_status' => [ 'publish', 'private' ],
				'author'      => null,
				'perm'        => 'readable',
			],
		);

		self::assertStringContainsString( "(p.post_status = 'publish' OR p.post_author = %d)", $where );
		self::assertSame(
			[ 'apermo_stash_link', 'apermo_stash_tag', 'publish', 'private', 7 ],
			$args,
		);
	}

	/**
	 * Verifies private=1 narrows by both post_status and explicit author IN list.
	 *
	 * @return void
	 */
	public function test_build_where_clause_explicit_author_filter(): void {
		[ $where, $args ] = TagsController::build_where_clause(
			[
				'post_status' => [ 'private' ],
				'author'      => [ 7 ],
				'perm'        => null,
			],
		);

		self::assertStringContainsString( 'p.post_author IN (%d)', $where );
		self::assertStringNotContainsString( "(p.post_status = 'publish'", $where );
		self::assertSame( [ 'apermo_stash_link', 'apermo_stash_tag', 'private', 7 ], $args );
	}
}
