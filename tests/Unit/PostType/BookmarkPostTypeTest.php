<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\PostType;

use Apermo\Stash\PostType\BookmarkPostType;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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
			&& $args['menu_icon'] === 'none';
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
		self::assertNotFalse( has_action( 'admin_print_footer_scripts', [ $post_type, 'inline_menu_icon' ] ) );
	}

	/**
	 * Verifies enqueue_menu_icon_styles attaches state-based color CSS to wp-admin.
	 *
	 * @return void
	 */
	public function test_enqueue_menu_icon_styles_attaches_to_wp_admin(): void {
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
		self::assertStringContainsString( '#a7aaad', $captured[1] );
		self::assertStringContainsString( '--wp-admin-theme-color', $captured[1] );
		self::assertStringContainsString( ' svg{', $captured[1] );
	}

	/**
	 * Verifies inline_menu_icon outputs a script that injects the SVG markup
	 * into the menu-image div for our CPT.
	 *
	 * @return void
	 */
	public function test_inline_menu_icon_outputs_injection_script(): void {
		Functions\when( 'wp_json_encode' )->alias(
			static fn ( $data ) => \json_encode( $data ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		);

		// Reset the static cache via a new class instance is not enough; the
		// cache is class-level. Use reflection so the test is hermetic.
		$reflection = new ReflectionClass( BookmarkPostType::class );
		$cache_prop = $reflection->getProperty( 'svg_cache' );
		$cache_prop->setValue( null, '<svg viewBox="0 0 10 10"><path d="M0,0"/></svg>' );

		\ob_start();
		( new BookmarkPostType() )->inline_menu_icon();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( '<script id="linkstash-menu-icon">', $output );
		self::assertStringContainsString( '#menu-posts-' . BookmarkPostType::POST_TYPE . ' .wp-menu-image', $output );
		self::assertStringContainsString( 'd.innerHTML=', $output );
		self::assertStringContainsString( '<svg', $output );

		$cache_prop->setValue( null, null );
	}

	/**
	 * Verifies inline_menu_icon emits nothing when the SVG cannot be loaded.
	 *
	 * @return void
	 */
	public function test_inline_menu_icon_silent_when_svg_missing(): void {
		$reflection = new ReflectionClass( BookmarkPostType::class );
		$cache_prop = $reflection->getProperty( 'svg_cache' );
		$cache_prop->setValue( null, '' );

		\ob_start();
		( new BookmarkPostType() )->inline_menu_icon();
		$output = (string) \ob_get_clean();

		self::assertSame( '', $output );

		$cache_prop->setValue( null, null );
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
		self::assertSame( 'apermo_stash_bookmark', BookmarkPostType::POST_TYPE );
	}
}
