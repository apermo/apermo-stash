<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\PostType;

use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests the bookmark tag taxonomy registration.
 */
class TagTaxonomyTest extends TestCase {

	/**
	 * Matches the expected register_taxonomy arguments.
	 *
	 * @param array<string, mixed> $args Args passed to register_taxonomy.
	 *
	 * @return bool
	 */
	public static function matchExpectedArgs( array $args ): bool {
		return $args['hierarchical'] === false
			&& $args['public'] === false
			&& $args['show_ui'] === true
			&& $args['show_in_rest'] === true
			&& $args['rest_base'] === 'tags'
			&& $args['rewrite'] === false;
	}

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
	 * Verifies register hooks taxonomy registration on init.
	 *
	 * @return void
	 */
	public function test_register_hooks_init(): void {
		$taxonomy = new TagTaxonomy();
		$taxonomy->register();

		self::assertNotFalse( has_action( 'init', [ $taxonomy, 'register_taxonomy' ] ) );
	}

	/**
	 * Verifies register_taxonomy registers the taxonomy with expected args.
	 *
	 * @return void
	 */
	public function test_register_taxonomy_uses_expected_args(): void {
		Functions\stubs(
			[
				'__' => null,
				'_x' => null,
			],
		);

		Functions\expect( 'register_taxonomy' )
			->once()
			->with(
				TagTaxonomy::TAXONOMY,
				BookmarkPostType::POST_TYPE,
				Mockery::on( [ self::class, 'matchExpectedArgs' ] ),
			);

		( new TagTaxonomy() )->register_taxonomy();
	}

	/**
	 * Confirms the taxonomy slug is the documented value.
	 *
	 * @return void
	 */
	public function test_taxonomy_constant(): void {
		self::assertSame( 'linkstash_tag', TagTaxonomy::TAXONOMY );
	}
}
