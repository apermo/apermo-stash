<?php

declare(strict_types=1);

namespace Plugin_Name\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Plugin_Name\Main;

/**
 * Tests for the Main class.
 */
class MainTest extends TestCase {

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
	 * Verifies init registers activation and deactivation hooks.
	 *
	 * @return void
	 */
	public function test_init_registers_hooks(): void {
		$file = '/tmp/plugin.php';

		Functions\expect( 'register_activation_hook' )
			->once()
			->with( $file, [ Main::class, 'activate' ] );

		Functions\expect( 'register_deactivation_hook' )
			->once()
			->with( $file, [ Main::class, 'deactivate' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'plugins_loaded', [ Main::class, 'boot' ] );

		Main::init( $file );
	}

	/**
	 * Verifies init stores the plugin file path.
	 *
	 * @return void
	 */
	public function test_init_stores_file_path(): void {
		$file = '/tmp/plugin.php';

		Functions\stubs(
			[
				'register_activation_hook',
				'register_deactivation_hook',
				'add_action',
			],
		);

		Main::init( $file );

		$this->assertSame( $file, Main::file() );
	}

	/**
	 * Verifies activate can be called without error.
	 *
	 * @return void
	 */
	public function test_activate(): void {
		Main::activate();
		$this->assertTrue( true );
	}

	/**
	 * Verifies deactivate can be called without error.
	 *
	 * @return void
	 */
	public function test_deactivate(): void {
		Main::deactivate();
		$this->assertTrue( true );
	}

	/**
	 * Verifies boot can be called without error.
	 *
	 * @return void
	 */
	public function test_boot(): void {
		Main::boot();
		$this->assertTrue( true );
	}
}
