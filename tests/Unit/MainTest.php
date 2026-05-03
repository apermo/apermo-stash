<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit;

use Apermo\LinkStash\Main;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

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
	 * Verifies activate registers the CPT and flushes rewrites.
	 *
	 * @return void
	 */
	public function test_activate_seeds_starter_tags_on_first_run(): void {
		Functions\stubs(
			[
				'__' => null,
				'_x' => null,
			],
		);
		Functions\expect( 'register_post_type' )->once();
		Functions\expect( 'register_taxonomy' )->once();
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'get_option' )
			->once()
			->with( 'linkstash_starter_tags_seeded', false )
			->andReturn( false );
		Functions\when( 'term_exists' )->justReturn( null );
		Functions\expect( 'wp_insert_term' )->times( 4 );
		Functions\expect( 'update_option' )
			->once()
			->with( 'linkstash_starter_tags_seeded', true, false );

		Main::activate();
	}

	/**
	 * Verifies activate skips seeding entirely when the marker option is set.
	 *
	 * @return void
	 */
	public function test_activate_skips_seeding_when_already_seeded(): void {
		Functions\stubs(
			[
				'__' => null,
				'_x' => null,
			],
		);
		Functions\expect( 'register_post_type' )->once();
		Functions\expect( 'register_taxonomy' )->once();
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'get_option' )
			->once()
			->with( 'linkstash_starter_tags_seeded', false )
			->andReturn( true );
		Functions\expect( 'wp_insert_term' )->never();
		Functions\expect( 'update_option' )->never();

		Main::activate();
	}

	/**
	 * Verifies deactivate flushes rewrites.
	 *
	 * @return void
	 */
	public function test_deactivate(): void {
		Functions\expect( 'flush_rewrite_rules' )->once();

		Main::deactivate();
	}

	/**
	 * Verifies boot wires the bookmark post type.
	 *
	 * @return void
	 */
	public function test_boot(): void {
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );

		Main::boot();
	}
}
