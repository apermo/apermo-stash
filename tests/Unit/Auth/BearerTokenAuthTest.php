<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Auth;

use Apermo\LinkStash\Auth\BearerTokenAuth;
use Apermo\LinkStash\Auth\TokenStore;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests the bearer-token authentication filter.
 */
class BearerTokenAuthTest extends TestCase {

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();

		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		unset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
	}

	/**
	 * Verifies a matching bearer token resolves to the bound user id.
	 *
	 * @return void
	 */
	public function test_matching_token_resolves_to_user(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123';

		$store = Mockery::mock( TokenStore::class );
		$store->shouldReceive( 'find_by_plain' )
			->once()
			->with( 'abc123' )
			->andReturn(
				[
					'user_id' => 42,
					'id'      => 'tok-1',
				],
			);
		$store->shouldReceive( 'touch_last_used' )
			->once()
			->with( 42, 'tok-1' );

		$auth = new BearerTokenAuth( $store );
		self::assertSame( 42, $auth->authenticate( null ) );
	}

	/**
	 * Verifies a missing Authorization header returns the original user id.
	 *
	 * @return void
	 */
	public function test_no_header_falls_through(): void {
		$store = Mockery::mock( TokenStore::class );
		$store->shouldNotReceive( 'find_by_plain' );

		$auth = new BearerTokenAuth( $store );
		self::assertSame( 0, $auth->authenticate( 0 ) );
	}

	/**
	 * Verifies a non-Bearer scheme is ignored.
	 *
	 * @return void
	 */
	public function test_non_bearer_scheme_ignored(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

		$store = Mockery::mock( TokenStore::class );
		$store->shouldNotReceive( 'find_by_plain' );

		$auth = new BearerTokenAuth( $store );
		self::assertSame( 0, $auth->authenticate( 0 ) );
	}

	/**
	 * Verifies a no-match token leaves the input user id alone.
	 *
	 * @return void
	 */
	public function test_unknown_token_falls_through(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer nope';

		$store = Mockery::mock( TokenStore::class );
		$store->shouldReceive( 'find_by_plain' )
			->once()
			->with( 'nope' )
			->andReturn( null );

		$auth = new BearerTokenAuth( $store );
		self::assertSame( 5, $auth->authenticate( 5 ) );
	}

	/**
	 * Verifies the redirect-style header is honoured when HTTP_AUTHORIZATION is missing.
	 *
	 * @return void
	 */
	public function test_redirect_header_is_honoured(): void {
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer fromredirect';

		$store = Mockery::mock( TokenStore::class );
		$store->shouldReceive( 'find_by_plain' )
			->once()
			->with( 'fromredirect' )
			->andReturn(
				[
					'user_id' => 11,
					'id'      => 'tok-2',
				],
			);
		$store->shouldReceive( 'touch_last_used' )->once();

		$auth = new BearerTokenAuth( $store );
		self::assertSame( 11, $auth->authenticate( null ) );
	}

	/**
	 * Verifies register hooks the determine_current_user filter.
	 *
	 * @return void
	 */
	public function test_register_hooks_filter(): void {
		$auth = new BearerTokenAuth( Mockery::mock( TokenStore::class ) );
		$auth->register();

		self::assertNotFalse( has_filter( 'determine_current_user', [ $auth, 'authenticate' ] ) );
	}
}
