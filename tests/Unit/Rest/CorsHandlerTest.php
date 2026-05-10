<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Rest;

use Apermo\Stash\Rest\CorsHandler;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests the CORS allow-list and preflight handling.
 *
 * Origin matching is exercised directly through the private static helper
 * via reflection — it is the single place where allow-list policy lives,
 * and locking it down with tests means future contributors can't widen it
 * by accident.
 */
class CorsHandlerTest extends TestCase {

	/**
	 * Provides the origin matcher cases.
	 *
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function originMatcherCases(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Each row is a (candidate, origin, expected) triple.
		return [
			'exact match'                          => [ 'https://example.tld', 'https://example.tld', true ],
			'chrome-extension wildcard hits'       => [ 'chrome-extension://*', 'chrome-extension://abc123', true ],
			'chrome-extension wildcard misses'     => [ 'chrome-extension://*', 'https://example.tld', false ],
			'host wildcard at scheme boundary'     => [ 'https://example.tld*', 'https://example.tld', true ],
			'host wildcard at path boundary'       => [ 'https://example.tld*', 'https://example.tld/x', true ],
			'host wildcard at port boundary'       => [ 'https://example.tld*', 'https://example.tld:8080', true ],
			'host wildcard rejects fake suffix'    => [ 'https://example.tld*', 'https://example.tld.attacker.tld', false ],
			'host wildcard rejects fake subdomain' => [ 'https://example.tld*', 'https://example.tldevil.com', false ],
			'no wildcard rejects suffix'           => [ 'https://example.tld', 'https://example.tld/x', false ],
		];
	}

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();

		unset(
			$_SERVER['HTTP_ORIGIN'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI'],
		);
	}

	/**
	 * Verifies origin matching for representative inputs.
	 *
	 * @param string $candidate Allow-list entry.
	 * @param string $origin    Origin header value.
	 * @param bool   $expected  Whether matching should succeed.
	 *
	 * @return void
	 */
	#[DataProvider( 'originMatcherCases' )]
	public function test_origin_matches( string $candidate, string $origin, bool $expected ): void {
		$method = ( new ReflectionClass( CorsHandler::class ) )->getMethod( 'origin_matches' );

		self::assertSame( $expected, $method->invoke( null, $origin, $candidate ) );
	}

	/**
	 * Verifies register hooks send_cors_response on rest_pre_serve_request
	 * at a high priority (so we run after core's CORS handler).
	 *
	 * @return void
	 */
	public function test_register_hooks_rest_pre_serve_request_late(): void {
		$handler = new CorsHandler();
		$handler->register();

		$priority = has_filter( 'rest_pre_serve_request', [ $handler, 'send_cors_response' ] );
		self::assertNotFalse( $priority );
		self::assertGreaterThan( 10, $priority, 'Must run after WP core rest_send_cors_headers (priority 10).' );
	}

	/**
	 * Verifies send_cors_response is a no-op when the request is not for
	 * an Apermo Stash route, returning the unchanged $served value.
	 *
	 * @return void
	 */
	public function test_send_cors_response_skips_other_namespaces(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/wp/v2/posts';
		$_SERVER['HTTP_ORIGIN']    = 'chrome-extension://abc';
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );

		$result = ( new CorsHandler() )->send_cors_response( false, null, null, null );
		self::assertFalse( $result );
	}

	/**
	 * Verifies send_cors_response is a no-op when the origin is not on the allow-list.
	 *
	 * @return void
	 */
	public function test_send_cors_response_skips_unknown_origin(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/apermo-stash/v1/links';
		$_SERVER['HTTP_ORIGIN']    = 'https://attacker.tld';
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Filters\expectApplied( 'apermo_stash_allowed_origins' )->andReturn( [ 'chrome-extension://*' ] );

		$result = ( new CorsHandler() )->send_cors_response( false, null, null, null );
		self::assertFalse( $result );
	}

	/**
	 * Verifies send_cors_response short-circuits an OPTIONS preflight with 204.
	 *
	 * @return void
	 */
	public function test_send_cors_response_serves_options_preflight(): void {
		$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
		$_SERVER['REQUEST_URI']    = '/wp-json/apermo-stash/v1/links';
		$_SERVER['HTTP_ORIGIN']    = 'chrome-extension://abc';
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Filters\expectApplied( 'apermo_stash_allowed_origins' )->andReturn( [ 'chrome-extension://*' ] );
		Functions\when( 'status_header' )->justReturn( null );

		$result = ( new CorsHandler() )->send_cors_response( false, null, null, null );
		self::assertTrue( $result );
	}

	/**
	 * Verifies send_cors_response on a non-OPTIONS Apermo Stash request returns
	 * $served unchanged (we set headers but don't short-circuit the body).
	 *
	 * @return void
	 */
	public function test_send_cors_response_passes_through_post(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/apermo-stash/v1/links';
		$_SERVER['HTTP_ORIGIN']    = 'chrome-extension://abc';
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Filters\expectApplied( 'apermo_stash_allowed_origins' )->andReturn( [ 'chrome-extension://*' ] );

		$result = ( new CorsHandler() )->send_cors_response( false, null, null, null );
		self::assertFalse( $result );
	}
}
