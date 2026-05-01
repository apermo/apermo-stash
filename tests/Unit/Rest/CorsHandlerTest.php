<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest;

use Apermo\LinkStash\Rest\CorsHandler;
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
	 * Verifies handle_preflight returns early when the request is not OPTIONS.
	 *
	 * @return void
	 */
	public function test_preflight_skipped_for_non_options_request(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/wp-json/linkstash/v1/bookmarks';
		$_SERVER['HTTP_ORIGIN']    = 'chrome-extension://abc';

		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Filters\expectApplied( 'linkstash_allowed_origins' )->andReturn( [ 'chrome-extension://*' ] );

		$handler = new CorsHandler();

		self::assertFalse( $handler->handle_preflight( false, null, null, null ) );
	}

	/**
	 * Verifies an unknown origin is rejected during preflight.
	 *
	 * @return void
	 */
	public function test_preflight_rejects_unknown_origin(): void {
		$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
		$_SERVER['REQUEST_URI']    = '/wp-json/linkstash/v1/bookmarks';
		$_SERVER['HTTP_ORIGIN']    = 'https://attacker.tld';

		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Filters\expectApplied( 'linkstash_allowed_origins' )->andReturn( [ 'chrome-extension://*' ] );

		$handler = new CorsHandler();

		self::assertFalse( $handler->handle_preflight( false, null, null, null ) );
	}
}
