<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\HelpTabs;
use Apermo\Stash\PostType\LinkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Screen;

/**
 * Tests the contextual help tabs added to the link list screen.
 */
class HelpTabsTest extends TestCase {

	/**
	 * Sets up Brain Monkey and translation/escape stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_kses' )->alias( static fn ( string $value ) => $value );
		Functions\when( 'admin_url' )->alias(
			static fn ( string $path ): string => 'https://wp.example.tld/wp-admin/' . $path,
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
	 * Verifies register hooks the screen-resolution callback.
	 *
	 * @return void
	 */
	public function test_register_hooks_current_screen(): void {
		$entrys = new HelpTabs();
		$entrys->register();

		self::assertNotFalse( has_action( 'current_screen', [ $entrys, 'maybe_add_help_tabs' ] ) );
	}

	/**
	 * Verifies the link list screen receives all three help tabs and a sidebar.
	 *
	 * @return void
	 */
	public function test_adds_tabs_on_link_list_screen(): void {
		$registered = [];
		$sidebar    = '';

		$screen     = Mockery::mock( WP_Screen::class );
		$screen->id = 'edit-' . LinkPostType::POST_TYPE;
		$screen->shouldReceive( 'add_help_tab' )
			->andReturnUsing(
				static function ( array $entry ) use ( &$registered ): void {
					$registered[] = $entry;
				},
			);
		$screen->shouldReceive( 'set_help_sidebar' )
			->andReturnUsing(
				static function ( string $html ) use ( &$sidebar ): void {
					$sidebar = $html;
				},
			);

		( new HelpTabs() )->maybe_add_help_tabs( $screen );

		$ids = \array_column( $registered, 'id' );
		self::assertSame(
			[ 'apermo-stash-overview', 'apermo-stash-add-links', 'linkstash-extension' ],
			$ids,
		);
		self::assertStringContainsString( 'github.com/apermo/linkstash-extension', $registered[2]['content'] );
		self::assertStringContainsString( 'options-general.php?page=apermo-stash', $sidebar );
	}

	/**
	 * Verifies an unrelated screen is left alone.
	 *
	 * @return void
	 */
	public function test_skips_unrelated_screens(): void {
		$screen     = Mockery::mock( WP_Screen::class );
		$screen->id = 'edit-post';
		$screen->shouldNotReceive( 'add_help_tab' );
		$screen->shouldNotReceive( 'set_help_sidebar' );

		( new HelpTabs() )->maybe_add_help_tabs( $screen );

		self::assertTrue( true );
	}
}
