<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Auth;

use Apermo\LinkStash\Auth\TokenStore;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests the bearer-token store.
 */
class TokenStoreTest extends TestCase {

	/**
	 * Sets up Brain Monkey and stubs WP user-meta plus helpers used by the store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$store   = [];
		$options = [];

		Functions\when( 'get_user_meta' )->alias(
			static function ( int $user_id, string $key ) use ( &$store ) {
				return $store[ $user_id ][ $key ] ?? '';
			},
		);
		Functions\when( 'update_user_meta' )->alias(
			static function ( int $user_id, string $key, $value ) use ( &$store ): bool {
				$store[ $user_id ][ $key ] = $value;
				return true;
			},
		);
		Functions\when( 'delete_user_meta' )->alias(
			static function ( int $user_id, string $key ) use ( &$store ): bool {
				unset( $store[ $user_id ][ $key ] );
				return true;
			},
		);
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- mirrors WP signature.
			static function ( string $key, $default_value = false ) use ( &$options ) {
				return $options[ $key ] ?? $default_value;
			},
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, $value ) use ( &$options ): bool {
				$options[ $key ] = $value;
				return true;
			},
		);
		Functions\when( 'delete_option' )->alias(
			static function ( string $key ) use ( &$options ): bool {
				unset( $options[ $key ] );
				return true;
			},
		);
		Functions\when( 'wp_generate_password' )->alias(
			static fn ( int $length = 12 ): string => \str_repeat( 'A', $length ),
		);
		Functions\when( 'wp_generate_uuid4' )->alias(
			static fn (): string => 'uuid-' . \uniqid( '', true ),
		);
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
	 * Verifies create returns a plain token and stores a hashed entry.
	 *
	 * @return void
	 */
	public function test_create_returns_plain_token_and_stores_entry(): void {
		$store = new TokenStore( static fn (): int => 1700000000 );
		$plain = $store->create( 7, 'My laptop' );

		self::assertNotSame( '', $plain );

		$entries = $store->list( 7 );
		self::assertCount( 1, $entries );
		self::assertSame( 'My laptop', $entries[0]['name'] );
		self::assertArrayNotHasKey( 'hash', $entries[0] );
		self::assertSame( 1700000000, $entries[0]['created'] );
		self::assertNull( $entries[0]['last_used'] );
	}

	/**
	 * Verifies revoke removes the matching entry.
	 *
	 * @return void
	 */
	public function test_revoke_removes_entry(): void {
		$store = new TokenStore( static fn (): int => 1700000000 );
		$store->create( 7, 'a' );
		$store->create( 7, 'b' );

		$entries = $store->list( 7 );
		self::assertCount( 2, $entries );

		self::assertTrue( $store->revoke( 7, $entries[0]['id'] ) );
		self::assertCount( 1, $store->list( 7 ) );
		self::assertSame( 'b', $store->list( 7 )[0]['name'] );
	}

	/**
	 * Verifies find_by_plain locates the owning user via the index alone.
	 *
	 * @return void
	 */
	public function test_find_by_plain_uses_index_without_scanning(): void {
		Functions\expect( 'get_users' )->never();

		$store = new TokenStore( static fn (): int => 1700000000 );
		$plain = $store->create( 9, 'x' );

		$found = $store->find_by_plain( $plain );
		self::assertNotNull( $found );
		self::assertSame( 9, $found['user_id'] );
	}

	/**
	 * Verifies find_by_plain returns null when no entry matches.
	 *
	 * @return void
	 */
	public function test_find_by_plain_returns_null_on_miss(): void {
		Functions\when( 'get_users' )->justReturn( [ 7 ] );

		$store = new TokenStore( static fn (): int => 1700000000 );
		$store->create( 7, 'x' );

		self::assertNull( $store->find_by_plain( 'wrong-token' ) );
	}

	/**
	 * Verifies the user-meta scan fallback recovers an unindexed token and
	 * back-fills the index for next time.
	 *
	 * @return void
	 */
	public function test_find_by_plain_falls_back_to_scan_for_unindexed_tokens(): void {
		Functions\when( 'get_users' )->justReturn( [ 4, 8 ] );

		$store = new TokenStore( static fn (): int => 1700000000 );
		$plain = $store->create( 8, 'pre-index' );

		// Wipe the index option to simulate a token minted before indexing
		// existed; the lookup should still succeed via scan.
		Functions\when( 'get_option' )->justReturn( [] );

		$found = $store->find_by_plain( $plain );
		self::assertNotNull( $found );
		self::assertSame( 8, $found['user_id'] );
	}

	/**
	 * Verifies touch_last_used updates the timestamp.
	 *
	 * @return void
	 */
	public function test_touch_last_used_updates_timestamp(): void {
		$store = new TokenStore( static fn (): int => 1700000000 );
		$store->create( 7, 'x' );
		$entries = $store->list( 7 );
		$id      = $entries[0]['id'];

		$store->touch_last_used( 7, $id );
		$entries = $store->list( 7 );
		self::assertSame( 1700000000, $entries[0]['last_used'] );
	}
}
