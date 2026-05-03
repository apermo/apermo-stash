<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\PostType;

use Apermo\LinkStash\PostType\BookmarkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests the bookmark CPT registration.
 */
class BookmarkPostTypeTest extends TestCase {

	/**
	 * Matches the expected register_post_type arguments.
	 *
	 * @param array<string, mixed> $args Args passed to register_post_type.
	 *
	 * @return bool
	 */
	public static function matchExpectedArgs( array $args ): bool {
		return $args['public'] === false
			&& $args['publicly_queryable'] === false
			&& $args['show_ui'] === true
			&& $args['show_in_rest'] === true
			&& $args['rest_base'] === 'bookmarks'
			&& $args['capability_type'] === 'post'
			&& $args['supports'] === [ 'title' ]
			&& $args['has_archive'] === false
			&& $args['hierarchical'] === false
			&& \is_string( $args['menu_icon'] )
			&& \str_ends_with( $args['menu_icon'], '/assets/menu-icon.svg' );
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
	 * Verifies register hooks the CPT registration on init.
	 *
	 * @return void
	 */
	public function test_register_hooks_init(): void {
		$post_type = new BookmarkPostType();
		$post_type->register();

		self::assertNotFalse( has_action( 'init', [ $post_type, 'register_post_type' ] ) );
		self::assertNotFalse( has_action( 'admin_enqueue_scripts', [ $post_type, 'enqueue_menu_icon_styles' ] ) );
	}

	/**
	 * Verifies enqueue_menu_icon_styles attaches the masking CSS to wp-admin.
	 *
	 * @return void
	 */
	public function test_enqueue_menu_icon_styles_attaches_to_wp_admin(): void {
		Functions\when( 'plugins_url' )->alias(
			static fn ( string $path ): string => '/wp-content/plugins/linkstash/' . $path,
		);
		Functions\when( 'esc_url' )->returnArg();

		$captured = null;
		Functions\when( 'wp_add_inline_style' )->alias(
			static function ( string $handle, string $rules ) use ( &$captured ): bool {
				$captured = [ $handle, $rules ];
				return true;
			},
		);

		( new BookmarkPostType() )->enqueue_menu_icon_styles();

		self::assertNotNull( $captured );
		self::assertSame( 'wp-admin', $captured[0] );
		self::assertStringContainsString( '#menu-posts-' . BookmarkPostType::POST_TYPE, $captured[1] );
		self::assertStringContainsString( '/assets/menu-icon.svg', $captured[1] );
	}

	/**
	 * Verifies register_post_type registers the CPT with the expected args.
	 *
	 * @return void
	 */
	public function test_register_post_type_uses_expected_args(): void {
		Functions\stubs(
			[
				'__' => null,
				'_x' => null,
			],
		);
		Functions\when( 'plugins_url' )->alias(
			static fn ( string $path ): string => '/wp-content/plugins/linkstash/' . $path,
		);

		Functions\expect( 'register_post_type' )
			->once()
			->with(
				BookmarkPostType::POST_TYPE,
				Mockery::on( [ self::class, 'matchExpectedArgs' ] ),
			);

		( new BookmarkPostType() )->register_post_type();
	}

	/**
	 * Confirms the post type slug is the documented value.
	 *
	 * @return void
	 */
	public function test_post_type_constant(): void {
		self::assertSame( 'linkstash_bookmark', BookmarkPostType::POST_TYPE );
	}
}
