<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest;

use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\Rest\BookmarksController;
use Apermo\LinkStash\Url\MetadataFetcher;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Tests the BookmarksController REST handlers.
 */
class BookmarksControllerTest extends TestCase {

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
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\when( 'rest_ensure_response' )->alias(
			static fn ( $data ) => $data instanceof WP_REST_Response ? $data : new WP_REST_Response( $data ),
		);
		Functions\when( 'rest_sanitize_boolean' )->alias( static fn ( $value ): bool => (bool) $value );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url ) => \parse_url( $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'wp_get_object_terms' )->justReturn( [] );
		Functions\when( 'mysql2date' )->returnArg();
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->justReturn( true );
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
	 * Verifies register_routes registers the collection and per-id routes.
	 *
	 * @return void
	 */
	public function test_register_routes_registers_two_route_groups(): void {
		Functions\expect( 'register_rest_route' )->twice();

		$this->controller()->register_routes( 'linkstash/v1' );
	}

	/**
	 * Verifies list_items wraps the query in a paginated response with WP_Total headers.
	 *
	 * @return void
	 */
	public function test_list_items_returns_paginated_response(): void {
		$post              = new WP_Post();
		$post->ID          = 1;
		$post->post_status = 'publish';
		$post->post_title  = 'A bookmark';

		WP_Query::$results[] = [
			'posts'         => [ $post ],
			'found_posts'   => 5,
			'max_num_pages' => 2,
		];

		$response = $this->controller()->list_items( new WP_REST_Request() );

		self::assertCount( 1, $response->data );
		self::assertSame( '5', $response->headers['X-WP-Total'] );
		self::assertSame( '2', $response->headers['X-WP-TotalPages'] );
	}

	/**
	 * Verifies create_item rejects an empty url with a 400.
	 *
	 * @return void
	 */
	public function test_create_item_rejects_empty_url(): void {
		$request         = new WP_REST_Request();
		$request->params = [ 'url' => '' ];

		$result = $this->controller()->create_item( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_missing_url', $result->code );
	}

	/**
	 * Verifies create_item rejects a non-canonicalisable url.
	 *
	 * @return void
	 */
	public function test_create_item_rejects_invalid_url(): void {
		$request         = new WP_REST_Request();
		$request->params = [ 'url' => 'not a url' ];

		$result = $this->controller()->create_item( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_invalid_url', $result->code );
	}

	/**
	 * Verifies get_item returns 404 for a non-bookmark post.
	 *
	 * @return void
	 */
	public function test_get_item_returns_404_for_unknown_post(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 999 ];

		$result = $this->controller()->get_item( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_not_found', $result->code );
	}

	/**
	 * Verifies get_item wraps a found bookmark in a REST response.
	 *
	 * @return void
	 */
	public function test_get_item_returns_response_for_known_bookmark(): void {
		$post            = new WP_Post();
		$post->ID        = 7;
		$post->post_type = BookmarkPostType::POST_TYPE;
		Functions\when( 'get_post' )->justReturn( $post );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 7 ];

		$response = $this->controller()->get_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 7, $response->data['id'] );
	}

	/**
	 * Verifies update_item returns 404 for a non-bookmark post.
	 *
	 * @return void
	 */
	public function test_update_item_returns_404_for_unknown_post(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 999 ];

		$result = $this->controller()->update_item( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_not_found', $result->code );
	}

	/**
	 * Verifies update_item rejects an invalid url update.
	 *
	 * @return void
	 */
	public function test_update_item_rejects_invalid_url_update(): void {
		$post            = new WP_Post();
		$post->ID        = 7;
		$post->post_type = BookmarkPostType::POST_TYPE;
		Functions\when( 'get_post' )->justReturn( $post );

		$request         = new WP_REST_Request();
		$request->params = [
			'id'  => 7,
			'url' => 'not a url',
		];

		$result = $this->controller()->update_item( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_invalid_url', $result->code );
	}

	/**
	 * Verifies delete_item returns 404 for a non-bookmark post.
	 *
	 * @return void
	 */
	public function test_delete_item_returns_404_for_unknown_post(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 999 ];

		$result = $this->controller()->delete_item( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_not_found', $result->code );
	}

	/**
	 * Verifies delete_item returns success when wp_delete_post succeeds.
	 *
	 * @return void
	 */
	public function test_delete_item_returns_success(): void {
		$post            = new WP_Post();
		$post->ID        = 7;
		$post->post_type = BookmarkPostType::POST_TYPE;
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'wp_delete_post' )->justReturn( $post );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 7 ];

		$response = $this->controller()->delete_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertTrue( $response->data['deleted'] );
		self::assertSame( 7, $response->data['id'] );
	}

	/**
	 * Verifies create_item persists a fresh bookmark and returns 201.
	 *
	 * @return void
	 */
	public function test_create_item_persists_new_bookmark(): void {
		// First WP_Query: dedupe lookup returns nothing.
		WP_Query::$results[] = [ 'posts' => [] ];

		Functions\when( 'wp_insert_post' )->justReturn( 42 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_set_object_terms' )->justReturn( true );

		$post              = new WP_Post();
		$post->ID          = 42;
		$post->post_status = 'private';
		Functions\when( 'get_post' )->justReturn( $post );

		$request         = new WP_REST_Request();
		$request->params = [
			'url'  => 'https://example.tld/article',
			'tags' => [ 'reading' ],
		];

		$response = $this->controller()->create_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 201, $response->status );
		self::assertSame( 42, $response->data['id'] );
	}

	/**
	 * Verifies create_item returns the existing bookmark on dedupe match.
	 *
	 * @return void
	 */
	public function test_create_item_dedupes_on_canonical_match(): void {
		$existing            = new WP_Post();
		$existing->ID        = 17;
		WP_Query::$results[] = [ 'posts' => [ $existing ] ];

		Functions\when( 'wp_set_object_terms' )->justReturn( true );

		$post              = new WP_Post();
		$post->ID          = 17;
		$post->post_status = 'private';
		Functions\when( 'get_post' )->justReturn( $post );

		$request         = new WP_REST_Request();
		$request->params = [
			'url'  => 'https://example.tld/article',
			'tags' => [ 'reading' ],
		];

		$response = $this->controller()->create_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 200, $response->status );
		self::assertSame( '1', $response->headers['X-LinkStash-Existing'] );
	}

	/**
	 * Verifies update_item persists field updates.
	 *
	 * @return void
	 */
	public function test_update_item_applies_changes(): void {
		$post            = new WP_Post();
		$post->ID        = 7;
		$post->post_type = BookmarkPostType::POST_TYPE;
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'wp_update_post' )->justReturn( 7 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_set_object_terms' )->justReturn( true );

		$request         = new WP_REST_Request();
		$request->params = [
			'id'          => 7,
			'title'       => 'Updated',
			'description' => 'New notes',
			'tags'        => [ 'a', 'b' ],
			'unread'      => true,
			'archived'    => true,
			'public'      => true,
		];

		$response = $this->controller()->update_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 7, $response->data['id'] );
	}

	/**
	 * Verifies delete_item surfaces a 500 when wp_delete_post fails.
	 *
	 * @return void
	 */
	public function test_delete_item_returns_500_on_failure(): void {
		$post            = new WP_Post();
		$post->ID        = 7;
		$post->post_type = BookmarkPostType::POST_TYPE;
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'wp_delete_post' )->justReturn( false );

		$request         = new WP_REST_Request();
		$request->params = [ 'id' => 7 ];

		$result = $this->controller()->delete_item( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'linkstash_delete_failed', $result->code );
	}

	/**
	 * Builds a controller wired to a Mockery'd metadata fetcher.
	 *
	 * @return BookmarksController
	 */
	private function controller(): BookmarksController {
		$fetcher = Mockery::mock( MetadataFetcher::class );
		$fetcher->shouldReceive( 'fetch' )->andReturn(
			[
				'title'       => null,
				'description' => null,
				'reachable'   => false,
			],
		);

		return new BookmarksController( $fetcher );
	}
}
