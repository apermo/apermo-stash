<?php

declare(strict_types=1);

namespace Plugin_Name\Tests\Integration;

use Plugin_Name\Main;
use WP_UnitTestCase;

/**
 * Verifies the WordPress integration test environment is functional.
 */
class ExampleIntegrationTest extends WP_UnitTestCase {

	/**
	 * Confirms WordPress is loaded and available.
	 *
	 * @return void
	 */
	public function test_wordpress_is_loaded(): void {
		$this->assertTrue( \function_exists( 'do_action' ) );
	}

	/**
	 * Confirms the plugin or theme is active.
	 *
	 * @return void
	 */
	public function test_project_is_active(): void {
		$plugin_file = \dirname( __DIR__, 2 ) . '/plugin.php';

		if ( \file_exists( $plugin_file ) ) {
			$this->assertNotEmpty(
				Main::VERSION,
				'Plugin version should be set.',
			);
		} else {
			$this->assertNotFalse(
				wp_get_theme()->get( 'Name' ),
				'Active theme should have a name.',
			);
		}
	}
}
