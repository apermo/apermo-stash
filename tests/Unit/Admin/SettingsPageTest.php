<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\SettingsPage;
use Apermo\Stash\Auth\TokenStore;
use Apermo\Stash\Main;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Tools → Apermo Stash settings page.
 *
 * Branches that call `wp_safe_redirect` followed by `exit()` are not
 * exercised here — `exit()` in PHP cannot be intercepted in-process — but
 * the registration, menu registration, and render flow account for the
 * majority of the class.
 */
class SettingsPageTest extends TestCase {

	/**
	 * Sets up Brain Monkey with output-friendly stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_html_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub for esc_*_e. 
			},
		);
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_attr_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub for esc_*_e. 
			},
		);
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_js' )->returnArg();
		Functions\when( 'admin_url' )->alias( static fn ( string $path ): string => 'https://wp.example.tld/wp-admin/' . $path );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce-value' );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'wp_date' )->justReturn( '2026-05-01 00:00' );
		Functions\when( 'get_option' )->alias(
			static fn ( string $key ) => $key === 'date_format' ? 'Y-m-d' : 'H:i',
		);
		Functions\when( 'human_time_diff' )->justReturn( '5 minutes' );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'add_options_page' )->justReturn( '' );
		Functions\when( 'plugin_basename' )->returnArg();
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
	 * Verifies register hooks the menu and admin-post handlers.
	 *
	 * @return void
	 */
	public function test_register_hooks_admin_actions(): void {
		$page = $this->page();
		$page->register();

		self::assertNotFalse( has_action( 'admin_menu', [ $page, 'register_menu' ] ) );
		self::assertNotFalse( has_action( 'admin_post_apermo_stash_token_create', [ $page, 'handle_create' ] ) );
		self::assertNotFalse( has_action( 'admin_post_apermo_stash_token_revoke', [ $page, 'handle_revoke' ] ) );
		self::assertNotFalse( has_filter( 'plugin_action_links_' . Main::file() ) );
	}

	/**
	 * Verifies plugin_action_links prepends a Settings link pointing at the page.
	 *
	 * @return void
	 */
	public function test_plugin_action_links_prepends_settings(): void {
		$existing = [ 'deactivate' => '<a href="x">Deactivate</a>' ];

		$links = $this->page()->plugin_action_links( $existing );

		self::assertCount( 2, $links );
		self::assertStringContainsString( 'options-general.php?page=apermo-stash', $links[0] );
		self::assertStringContainsString( 'Settings', $links[0] );
		self::assertSame( $existing['deactivate'], $links['deactivate'] );
	}

	/**
	 * Verifies render outputs the expected page skeleton when there are no tokens.
	 *
	 * @return void
	 */
	public function test_render_with_no_tokens(): void {
		$store = Mockery::mock( TokenStore::class );
		$store->shouldReceive( 'list' )->with( 7 )->andReturn( [] );

		\ob_start();
		( new SettingsPage( $store ) )->render();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'Apermo Stash API Tokens', $output );
		self::assertStringContainsString( 'Generate a new token', $output );
		self::assertStringContainsString( 'No tokens yet', $output );
	}

	/**
	 * Verifies render displays the new-token notice when a transient exists.
	 *
	 * @return void
	 */
	public function test_render_shows_new_token_notice(): void {
		Functions\when( 'get_transient' )->justReturn( 'plain-token-abc' );
		Functions\when( 'delete_transient' )->justReturn( true );

		$store = Mockery::mock( TokenStore::class );
		$store->shouldReceive( 'list' )->andReturn( [] );

		\ob_start();
		( new SettingsPage( $store ) )->render();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'plain-token-abc', $output );
		self::assertStringContainsString( 'Copy it now', $output );
	}

	/**
	 * Verifies render lists existing tokens with their metadata.
	 *
	 * @return void
	 */
	public function test_render_lists_existing_tokens(): void {
		$store = Mockery::mock( TokenStore::class );
		$store->shouldReceive( 'list' )->andReturn(
			[
				[
					'id'        => 'tok-1',
					'name'      => 'My laptop',
					'created'   => 1700000000,
					'last_used' => null,
				],
				[
					'id'        => 'tok-2',
					'name'      => 'Office',
					'created'   => 1700000000,
					'last_used' => \time() - 60,
				],
			],
		);

		\ob_start();
		( new SettingsPage( $store ) )->render();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'My laptop', $output );
		self::assertStringContainsString( 'Office', $output );
		self::assertStringContainsString( 'never', $output );
		// Recent last_used hits the human_time_diff branch ("5 minutes ago").
		self::assertStringContainsString( 'ago', $output );
	}

	/**
	 * Builds a SettingsPage wired to a mock TokenStore.
	 *
	 * @return SettingsPage
	 */
	private function page(): SettingsPage {
		return new SettingsPage( Mockery::mock( TokenStore::class ) );
	}
}
