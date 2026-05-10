<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Url;

use Apermo\Stash\Url\DisplayUrl;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the human-readable URL helper used as a title fallback.
 */
class DisplayUrlTest extends TestCase {

	/**
	 * Provides input/output pairs for simplify().
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function urlProvider(): array {
		return [
			'strip scheme + www, drop trailing slash' => [
				'https://www.example.tld/blog/article?utm=1#x',
				'example.tld/blog/article',
			],
			'root with trailing slash'                => [ 'https://example.tld/', 'example.tld' ],
			'root without trailing slash'             => [ 'https://example.tld', 'example.tld' ],
			'http scheme + path with query stripped'  => [
				'http://Example.tld/x/y?a=1',
				'example.tld/x/y',
			],
			'plain hostname keeps deep path'          => [
				'https://blog.example.tld/2026/01/foo/',
				'blog.example.tld/2026/01/foo',
			],
			'unparseable returns input unchanged'     => [ 'not a url', 'not a url' ],
			'empty string'                            => [ '', '' ],
		];
	}

	/**
	 * Sets up Brain Monkey and stubs wp_parse_url with PHP's parse_url.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// Stubbing wp_parse_url with PHP's parse_url is the whole point of
		// this alias — the suggested wp_parse_url alternative is what the
		// stub itself is replacing.
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
	 * Verifies simplify produces the expected display string for each input.
	 *
	 * @param string $input    URL input.
	 * @param string $expected Expected display value.
	 *
	 * @return void
	 */
	#[DataProvider( 'urlProvider' )]
	public function test_simplify( string $input, string $expected ): void {
		self::assertSame( $expected, DisplayUrl::simplify( $input ) );
	}
}
