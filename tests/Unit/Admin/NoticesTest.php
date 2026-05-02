<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Admin;

use Apermo\LinkStash\Admin\Notices;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Screen;

/**
 * Tests the bookmark-list-screen notice renderer.
 */
class NoticesTest extends TestCase {

	/**
	 * Builds a WP_Screen-shaped stub.
	 *
	 * @param string $base      Screen base.
	 * @param string $post_type Post type slug.
	 *
	 * @return WP_Screen
	 */
	private static function screen( string $base, string $post_type ): WP_Screen {
		$screen            = new WP_Screen();
		$screen->base      = $base;
		$screen->post_type = $post_type;

		return $screen;
	}

	/**
	 * Sets up Brain Monkey and admin-screen stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();

		$_GET = [];
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		$_GET = [];
	}

	/**
	 * Verifies register hooks admin_notices.
	 *
	 * @return void
	 */
	public function test_register_hooks_admin_notices(): void {
		$notices = new Notices();
		$notices->register();

		self::assertNotFalse( has_action( 'admin_notices', [ $notices, 'maybe_render' ] ) );
	}

	/**
	 * Verifies nothing renders outside the bookmark list screen.
	 *
	 * @return void
	 */
	public function test_silent_outside_bookmark_screen(): void {
		Functions\when( 'get_current_screen' )->justReturn( self::screen( 'edit', 'post' ) );
		$_GET['linkstash_notice'] = 'saved';

		\ob_start();
		( new Notices() )->maybe_render();
		$output = (string) \ob_get_clean();

		self::assertSame( '', $output );
	}

	/**
	 * Verifies nothing renders when the notice slug is missing.
	 *
	 * @return void
	 */
	public function test_silent_without_notice_param(): void {
		Functions\when( 'get_current_screen' )->justReturn( self::screen( 'edit', BookmarkPostType::POST_TYPE ) );

		\ob_start();
		( new Notices() )->maybe_render();
		$output = (string) \ob_get_clean();

		self::assertSame( '', $output );
	}

	/**
	 * Verifies the saved-unreachable warning is rendered with the warning level.
	 *
	 * @return void
	 */
	public function test_renders_saved_unreachable_warning(): void {
		Functions\when( 'get_current_screen' )->justReturn( self::screen( 'edit', BookmarkPostType::POST_TYPE ) );
		$_GET['linkstash_notice'] = 'saved-unreachable';

		\ob_start();
		( new Notices() )->maybe_render();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'notice-warning', $output );
		self::assertStringContainsString( 'didn', $output );
	}

	/**
	 * Verifies the regular saved notice renders as a success.
	 *
	 * @return void
	 */
	public function test_renders_saved_success(): void {
		Functions\when( 'get_current_screen' )->justReturn( self::screen( 'edit', BookmarkPostType::POST_TYPE ) );
		$_GET['linkstash_notice'] = 'saved';

		\ob_start();
		( new Notices() )->maybe_render();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'notice-success', $output );
	}

	/**
	 * Verifies an unrecognised slug renders nothing.
	 *
	 * @return void
	 */
	public function test_unknown_slug_renders_nothing(): void {
		Functions\when( 'get_current_screen' )->justReturn( self::screen( 'edit', BookmarkPostType::POST_TYPE ) );
		$_GET['linkstash_notice'] = 'random-junk';

		\ob_start();
		( new Notices() )->maybe_render();
		$output = (string) \ob_get_clean();

		self::assertSame( '', $output );
	}
}
