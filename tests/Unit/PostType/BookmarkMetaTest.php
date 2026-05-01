<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\PostType;

use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests bookmark post-meta registration.
 */
class BookmarkMetaTest extends TestCase {

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
		$meta = new BookmarkMeta();
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
			->with( BookmarkPostType::POST_TYPE, Mockery::type( 'string' ), Mockery::type( 'array' ) );

		( new BookmarkMeta() )->register_post_meta();
	}

	/**
	 * Confirms the documented meta-key constants.
	 *
	 * @return void
	 */
	public function test_meta_key_constants(): void {
		self::assertSame( '_linkstash_url', BookmarkMeta::META_URL );
		self::assertSame( '_linkstash_url_canonical', BookmarkMeta::META_URL_CANONICAL );
		self::assertSame( '_linkstash_unread', BookmarkMeta::META_UNREAD );
		self::assertSame( '_linkstash_archived', BookmarkMeta::META_ARCHIVED );
	}
}
