<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\UrlAutoScheme;
use Apermo\Stash\PostType\LinkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Screen;

/**
 * Tests the auto-prepend-https url-input enhancer.
 */
class UrlAutoSchemeTest extends TestCase {

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
	 * Verifies register hooks admin_enqueue_scripts.
	 *
	 * @return void
	 */
	public function test_register_hooks_enqueue(): void {
		$enhancer = new UrlAutoScheme();
		$enhancer->register();

		self::assertNotFalse( has_action( 'admin_enqueue_scripts', [ $enhancer, 'maybe_enqueue' ] ) );
	}

	/**
	 * Verifies the dashboard hook enqueues the inline script.
	 *
	 * @return void
	 */
	public function test_enqueues_on_dashboard(): void {
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'apermo-stash-url-auto-scheme' );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );

		( new UrlAutoScheme() )->maybe_enqueue( 'index.php' );
	}

	/**
	 * Verifies the bookmark edit screen enqueues the script.
	 *
	 * @return void
	 */
	public function test_enqueues_on_bookmark_edit_screen(): void {
		$screen            = new WP_Screen();
		$screen->base      = 'post';
		$screen->post_type = LinkPostType::POST_TYPE;
		Functions\when( 'get_current_screen' )->justReturn( $screen );

		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\expect( 'wp_enqueue_script' )->once();
		Functions\when( 'wp_add_inline_script' )->justReturn( true );

		( new UrlAutoScheme() )->maybe_enqueue( 'post.php' );
	}

	/**
	 * Verifies unrelated screens are skipped.
	 *
	 * @return void
	 */
	public function test_skips_unrelated_screens(): void {
		Functions\expect( 'wp_enqueue_script' )->never();

		( new UrlAutoScheme() )->maybe_enqueue( 'plugins.php' );
	}

	/**
	 * Verifies the bookmark list screen is skipped — the quick-add
	 * form is no longer rendered there, so no URL inputs to bind.
	 *
	 * @return void
	 */
	public function test_skips_bookmark_list_screen(): void {
		Functions\expect( 'wp_enqueue_script' )->never();

		( new UrlAutoScheme() )->maybe_enqueue( 'edit.php' );
	}

	/**
	 * Verifies post.php for an unrelated CPT is skipped.
	 *
	 * @return void
	 */
	public function test_skips_post_screen_for_other_cpts(): void {
		$screen            = new WP_Screen();
		$screen->base      = 'post';
		$screen->post_type = 'page';
		Functions\when( 'get_current_screen' )->justReturn( $screen );

		Functions\expect( 'wp_enqueue_script' )->never();

		( new UrlAutoScheme() )->maybe_enqueue( 'post.php' );
	}
}
