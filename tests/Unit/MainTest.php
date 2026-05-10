<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit;

use Apermo\Stash\Main;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Error;

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
			->with( 'apermo_stash_starter_tags_seeded', false )
			->andReturn( false );
		Functions\when( 'term_exists' )->justReturn( null );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\expect( 'wp_insert_term' )->times( 4 )->andReturn( [ 'term_id' => 1 ] );
		Functions\expect( 'update_option' )
			->once()
			->with( 'apermo_stash_starter_tags_seeded', true, false );

		Main::activate();
	}

	/**
	 * Verifies activate does not write the seeded marker if any wp_insert_term fails,
	 * so the next activation can retry.
	 *
	 * @return void
	 */
	public function test_activate_skips_marker_when_insert_fails(): void {
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
			->with( 'apermo_stash_starter_tags_seeded', false )
			->andReturn( false );
		Functions\when( 'term_exists' )->justReturn( null );
		Functions\when( 'is_wp_error' )->alias( static fn ( $value ): bool => $value instanceof WP_Error );

		$call = 0;
		Functions\when( 'wp_insert_term' )->alias(
			static function () use ( &$call ) {
				$call++;
				return $call === 2 ? new WP_Error( 'db_fail', 'transient' ) : [ 'term_id' => $call ];
			},
		);
		Functions\expect( 'update_option' )->never();

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
			->with( 'apermo_stash_starter_tags_seeded', false )
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
	 * Verifies boot wires the link post type.
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
