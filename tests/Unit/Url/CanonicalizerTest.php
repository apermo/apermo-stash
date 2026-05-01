<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Url;

use Apermo\LinkStash\Url\Canonicalizer;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests URL canonicalization.
 */
class CanonicalizerTest extends TestCase {

	/**
	 * Provides input and expected canonical URL pairs.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function urlProvider(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys
		return [
			'lowercase scheme + host'    => [ 'HTTPS://Example.tld/Path', 'https://example.tld/Path' ],
			'strip default https port'   => [ 'https://example.tld:443/path', 'https://example.tld/path' ],
			'strip default http port'    => [ 'http://example.tld:80/path', 'http://example.tld/path' ],
			'keep non-default port'      => [ 'http://example.tld:8080/path', 'http://example.tld:8080/path' ],
			'strip trailing root slash'  => [ 'https://example.tld/', 'https://example.tld' ],
			'keep deeper trailing slash' => [ 'https://example.tld/foo/', 'https://example.tld/foo/' ],
			'drop fragment'              => [ 'https://example.tld/p#section', 'https://example.tld/p' ],
			'strip utm params'           => [
				'https://example.tld/p?utm_source=x&utm_medium=y&id=1',
				'https://example.tld/p?id=1',
			],
			'strip fbclid + gclid'       => [
				'https://example.tld/p?fbclid=abc&gclid=xyz&id=1',
				'https://example.tld/p?id=1',
			],
			'keep other query params'    => [
				'https://example.tld/search?q=hello&page=2',
				'https://example.tld/search?page=2&q=hello',
			],
			'empty after stripping'      => [
				'https://example.tld/p?utm_source=x',
				'https://example.tld/p',
			],
		];
	}

	/**
	 * Sets up Brain Monkey and stubs wp_parse_url.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url ) => \parse_url( $url ) );
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
	 * Verifies canonicalize produces the expected output for representative inputs.
	 *
	 * @param string $input    Original URL.
	 * @param string $expected Canonical form.
	 *
	 * @return void
	 */
	#[DataProvider( 'urlProvider' )]
	public function test_canonicalize( string $input, string $expected ): void {
		self::assertSame( $expected, Canonicalizer::canonicalize( $input ) );
	}

	/**
	 * Verifies canonicalize returns an empty string for invalid input.
	 *
	 * @return void
	 */
	public function test_canonicalize_invalid_url_returns_empty(): void {
		self::assertSame( '', Canonicalizer::canonicalize( 'not a url' ) );
	}
}
