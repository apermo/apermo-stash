<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\TagAutocomplete;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests the tag autocomplete enqueue.
 */
class TagAutocompleteTest extends TestCase {

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
		$widget = new TagAutocomplete();
		$widget->register();

		self::assertNotFalse( has_action( 'admin_enqueue_scripts', [ $widget, 'maybe_enqueue' ] ) );
	}

	/**
	 * Verifies the dashboard hook (`index.php`) enqueues the script.
	 *
	 * @return void
	 */
	public function test_enqueues_on_dashboard(): void {
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'jquery-ui-autocomplete' );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn();
		Functions\when( 'wp_add_inline_script' )->justReturn( true );
		Functions\when( 'wp_add_inline_style' )->justReturn( true );

		( new TagAutocomplete() )->maybe_enqueue( 'index.php' );
	}

	/**
	 * Verifies it skips an unrelated screen.
	 *
	 * @return void
	 */
	public function test_skips_other_screens(): void {
		Functions\expect( 'wp_enqueue_script' )->never();

		( new TagAutocomplete() )->maybe_enqueue( 'plugins.php' );
	}

	/**
	 * Verifies it skips the bookmark list screen — the quick-add form
	 * is no longer rendered there, so there are no inputs to bind to.
	 *
	 * @return void
	 */
	public function test_skips_bookmark_list_screen(): void {
		Functions\expect( 'wp_enqueue_script' )->never();

		( new TagAutocomplete() )->maybe_enqueue( 'edit.php' );
	}
}
