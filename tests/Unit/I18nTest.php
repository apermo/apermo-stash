<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit;

use Apermo\Stash\I18n;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Traduttore Registry project registration.
 *
 * The library autoloads its `Required\Traduttore_Registry\add_project()`
 * function eagerly (composer `files` autoload), so the function is always
 * defined in the test process and cannot be redefined by Brain Monkey. These
 * tests therefore drive the real `add_project()` and assert on the WordPress
 * hooks it wires.
 */
final class I18nTest extends TestCase {

	use MockeryPHPUnitIntegration;

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
	 * Confirms register() hooks the project registration on init.
	 *
	 * @return void
	 */
	public function test_register_hooks_init(): void {
		$i18n = new I18n();

		Actions\expectAdded( 'init' )
			->once()
			->with( [ $i18n, 'add_project' ] );

		$i18n->register();
	}

	/**
	 * Confirms the registry library is bundled so the runtime guard resolves.
	 *
	 * @return void
	 */
	public function test_registry_library_is_available(): void {
		$this->assertTrue(
			\function_exists( 'Required\Traduttore_Registry\add_project' ),
			'The Traduttore Registry library must ship with the plugin.',
		);
	}

	/**
	 * Confirms add_project() wires the registry's translation filters for the
	 * apermo-stash slug.
	 *
	 * add_project() mutates the library's static project registry, so this runs
	 * in a separate process to keep that state out of sibling tests.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_adds_project_when_library_present(): void {
		$filters = [];
		// has_action/add_action stubs satisfy the registry's own internal cron
		// wiring; only the add_filter calls are the subject of this test.
		Functions\when( 'has_action' )->justReturn( false );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$filters ): bool {
				$filters[] = $hook;

				return true;
			},
		);

		( new I18n() )->add_project();

		$this->assertContains( 'translations_api', $filters );
		$this->assertContains( 'site_transient_update_plugins', $filters );
	}
}
