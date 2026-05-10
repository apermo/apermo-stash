<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\DashboardWidget;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests the dashboard "Quick Bookmark" widget.
 */
class DashboardWidgetTest extends TestCase {

	/**
	 * Sets up Brain Monkey with output-friendly stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_attr_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub.
			},
		);
		Functions\when( 'esc_html_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub.
			},
		);
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
	 * Verifies register hooks wp_dashboard_setup.
	 *
	 * @return void
	 */
	public function test_register_hooks_dashboard_setup(): void {
		$widget = new DashboardWidget();
		$widget->register();

		self::assertNotFalse( has_action( 'wp_dashboard_setup', [ $widget, 'register_widget' ] ) );
	}

	/**
	 * Verifies the widget is added when the user can edit posts.
	 *
	 * @return void
	 */
	public function test_register_widget_calls_wp_add_dashboard_widget(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_add_dashboard_widget' )->once();

		( new DashboardWidget() )->register_widget();
	}

	/**
	 * Verifies the widget is suppressed when the user lacks edit_posts.
	 *
	 * @return void
	 */
	public function test_register_widget_suppressed_without_cap(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_add_dashboard_widget' )->never();

		( new DashboardWidget() )->register_widget();
	}

	/**
	 * Verifies render outputs the form via the shared QuickAdd helper.
	 *
	 * @return void
	 */
	public function test_render_outputs_form(): void {
		\ob_start();
		( new DashboardWidget() )->render();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'apermo-stash-dashboard-widget', $output );
		self::assertStringContainsString( 'name="url"', $output );
		self::assertStringContainsString( 'name="tags"', $output );
		self::assertStringContainsString( 'name="public"', $output );
	}
}
