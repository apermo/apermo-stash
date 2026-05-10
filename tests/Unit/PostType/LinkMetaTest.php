<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\PostType;

use Apermo\Stash\PostType\LinkMeta;
use Apermo\Stash\PostType\LinkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests link post-meta registration.
 */
class LinkMetaTest extends TestCase {

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
	 * Verifies register hooks meta registration on init.
	 *
	 * @return void
	 */
	public function test_register_hooks_init(): void {
		$meta = new LinkMeta();
		$meta->register();

		self::assertNotFalse( has_action( 'init', [ $meta, 'register_post_meta' ] ) );
	}

	/**
	 * Verifies register_post_meta is called for every documented meta key.
	 *
	 * @return void
	 */
	public function test_register_post_meta_registers_all_keys(): void {
		Functions\expect( 'register_post_meta' )
			->times( 4 )
			->with( LinkPostType::POST_TYPE, Mockery::type( 'string' ), Mockery::type( 'array' ) );

		( new LinkMeta() )->register_post_meta();
	}

	/**
	 * Confirms the documented meta-key constants.
	 *
	 * @return void
	 */
	public function test_meta_key_constants(): void {
		self::assertSame( '_apermo_stash_url', LinkMeta::META_URL );
		self::assertSame( '_apermo_stash_url_canonical', LinkMeta::META_URL_CANONICAL );
		self::assertSame( '_apermo_stash_favorite', LinkMeta::META_FAVORITE );
	}

	/**
	 * Confirms bool_to_meta yields the canonical "1"/"0" storage shape.
	 *
	 * @return void
	 */
	public function test_bool_to_meta(): void {
		self::assertSame( '1', LinkMeta::bool_to_meta( true ) );
		self::assertSame( '0', LinkMeta::bool_to_meta( false ) );
	}

	/**
	 * Confirms sanitize_bool_meta routes raw values through rest_sanitize_boolean.
	 *
	 * @return void
	 */
	public function test_sanitize_bool_meta(): void {
		Functions\when( 'rest_sanitize_boolean' )->alias(
			static fn ( $value ): bool => \in_array( $value, [ true, 1, '1', 'true', 'on', 'yes' ], true ),
		);

		self::assertSame( '1', LinkMeta::sanitize_bool_meta( true ) );
		self::assertSame( '1', LinkMeta::sanitize_bool_meta( '1' ) );
		self::assertSame( '0', LinkMeta::sanitize_bool_meta( false ) );
		self::assertSame( '0', LinkMeta::sanitize_bool_meta( '' ) );
		// Non-scalar input is treated as false.
		self::assertSame( '0', LinkMeta::sanitize_bool_meta( [ 'unexpected' ] ) );
	}
}
