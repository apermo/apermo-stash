<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Url;

use Apermo\LinkStash\Url\MetadataFetcher;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Tests the URL metadata fetcher.
 */
class MetadataFetcherTest extends TestCase {

	/**
	 * Sets up Brain Monkey and stubs WP HTTP helpers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias(
			static fn ( $thing ): bool => $thing instanceof WP_Error,
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn ( array $response ): int => (int) ( $response['response']['code'] ?? 0 ),
		);
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn ( array $response ): string => (string) ( $response['body'] ?? '' ),
		);
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
	 * Verifies a successful fetch parses title and description.
	 *
	 * @return void
	 */
	public function test_fetch_parses_title_and_description(): void {
		$html = '<html><head>'
			. '<title>Hello &amp; World</title>'
			. '<meta name="description" content="A great article">'
			. '</head><body>x</body></html>';

		Functions\expect( 'wp_safe_remote_get' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => $html,
				],
			);

		$result = ( new MetadataFetcher() )->fetch( 'https://example.tld' );

		self::assertSame( 'Hello & World', $result['title'] );
		self::assertSame( 'A great article', $result['description'] );
	}

	/**
	 * Verifies og:description is used as a fallback.
	 *
	 * @return void
	 */
	public function test_fetch_falls_back_to_og_description(): void {
		$html = '<html><head>'
			. '<title>T</title>'
			. '<meta property="og:description" content="OG desc">'
			. '</head></html>';

		Functions\expect( 'wp_safe_remote_get' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => $html,
				],
			);

		$result = ( new MetadataFetcher() )->fetch( 'https://example.tld' );

		self::assertSame( 'OG desc', $result['description'] );
	}

	/**
	 * Verifies a WP_Error response yields empty values.
	 *
	 * @return void
	 */
	public function test_fetch_returns_empty_on_wp_error(): void {
		Functions\expect( 'wp_safe_remote_get' )
			->once()
			->andReturn( new WP_Error( 'http_request_failed', 'timeout' ) );

		$result = ( new MetadataFetcher() )->fetch( 'https://example.tld' );

		self::assertNull( $result['title'] );
		self::assertNull( $result['description'] );
	}

	/**
	 * Verifies a non-200 response yields empty values.
	 *
	 * @return void
	 */
	public function test_fetch_returns_empty_on_non_200(): void {
		Functions\expect( 'wp_safe_remote_get' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 500 ],
					'body'     => '',
				],
			);

		$result = ( new MetadataFetcher() )->fetch( 'https://example.tld' );

		self::assertNull( $result['title'] );
		self::assertNull( $result['description'] );
	}
}
