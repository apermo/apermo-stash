<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\QuickAdd;
use Apermo\Stash\PostType\BookmarkPostType;
use Apermo\Stash\Url\MetadataFetcher;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests the QuickAdd class — the shared form HTML used by the dashboard
 * widget plus the admin-post.php submission handler.
 *
 * Branches that call `wp_safe_redirect` followed by `exit()` are not
 * exercised here.
 */
class QuickAddTest extends TestCase {

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub for esc_*_e. 
			},
		);
		Functions\when( 'esc_html_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub for esc_*_e. 
			},
		);
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'admin_url' )->alias( static fn ( string $path ): string => 'https://wp.example.tld/wp-admin/' . $path );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce-value' );
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
	 * Verifies register hooks the submission handler.
	 *
	 * @return void
	 */
	public function test_register_hooks_actions(): void {
		$quick = $this->quick_add();
		$quick->register();

		self::assertNotFalse( has_action( 'admin_post_linkstash_quick_add', [ $quick, 'handle_submission' ] ) );
		self::assertFalse( has_action( 'all_admin_notices' ) );
	}

	/**
	 * Verifies render_form_html outputs the shared markup the dashboard widget consumes.
	 *
	 * @return void
	 */
	public function test_render_form_html_outputs_form(): void {
		\ob_start();
		QuickAdd::render_form_html( 'linkstash-dashboard-widget' );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'linkstash-dashboard-widget', $output );
		self::assertStringContainsString( 'name="url"', $output );
		self::assertStringContainsString( 'name="tags"', $output );
		self::assertStringContainsString( 'name="public"', $output );
	}

	/**
	 * Verifies parse_tags drops empty entries and dedupes.
	 *
	 * @return void
	 */
	public function test_parse_tags_normalizes_input(): void {
		$method = ( new ReflectionClass( QuickAdd::class ) )->getMethod( 'parse_tags' );

		self::assertSame(
			[ 'a', 'b', 'c' ],
			$method->invoke( null, ' a , b ,, c , a ' ),
		);
	}

	/**
	 * Verifies parse_tags returns an empty list for empty input.
	 *
	 * @return void
	 */
	public function test_parse_tags_empty_input(): void {
		$method = ( new ReflectionClass( QuickAdd::class ) )->getMethod( 'parse_tags' );

		self::assertSame( [], $method->invoke( null, '' ) );
	}

	/**
	 * Verifies list_url builds an admin URL with the bookmark CPT plus a notice query arg.
	 *
	 * @return void
	 */
	public function test_list_url_includes_notice(): void {
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ) {
				return $url . '?' . \http_build_query( $args );
			},
		);

		$method = ( new ReflectionClass( QuickAdd::class ) )->getMethod( 'list_url' );
		$url    = (string) $method->invoke( null, 'saved' );

		self::assertStringContainsString( 'post_type=' . BookmarkPostType::POST_TYPE, $url );
		self::assertStringContainsString( 'linkstash_notice=saved', $url );
	}

	/**
	 * Builds a QuickAdd wired to a mock metadata fetcher.
	 *
	 * @return QuickAdd
	 */
	private function quick_add(): QuickAdd {
		return new QuickAdd( Mockery::mock( MetadataFetcher::class ) );
	}
}
