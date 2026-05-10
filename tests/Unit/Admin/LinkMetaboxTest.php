<?php

declare(strict_types=1);

namespace Apermo\Stash\Tests\Unit\Admin;

use Apermo\Stash\Admin\LinkMetabox;
use Apermo\Stash\Main;
use Apermo\Stash\PostType\LinkMeta;
use Apermo\Stash\PostType\LinkPostType;
use Apermo\Stash\Url\MetadataFetcher;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Post;
use WP_Screen;

/**
 * Tests the bookmark edit-screen metabox class.
 */
class LinkMetaboxTest extends TestCase {

	/**
	 * Holds the recorded update_post_meta calls.
	 *
	 * @var array<int, array{0: int, 1: string, 2: mixed}>
	 */
	private array $meta_writes = [];

	/**
	 * Holds the recorded wp_update_post calls.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $post_writes = [];

	/**
	 * Sets up Brain Monkey and a permissive set of WP stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$_POST = [];

		$this->meta_writes = [];
		$this->post_writes = [];

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_attr_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub.
			},
		);
		Functions\when( 'esc_html_e' )->alias(
			static function ( $value ): void {
				echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub.
			},
		);
		Functions\when( 'esc_textarea' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url ) => \parse_url( $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'checked' )->alias(
			static function ( $value, $compare = true ): string {
				return $value === $compare ? "checked='checked'" : '';
			},
		);
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );

		Functions\when( 'get_post_meta' )->justReturn( '' );

		$writes = &$this->meta_writes;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$writes ): bool {
				$writes[] = [ $post_id, $key, $value ];
				return true;
			},
		);

		$post_writes = &$this->post_writes;
		Functions\when( 'wp_update_post' )->alias(
			static function ( array $args ) use ( &$post_writes ): int {
				$post_writes[] = $args;
				return (int) ( $args['ID'] ?? 0 );
			},
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
		$_POST = [];
	}

	/**
	 * Verifies register hooks the meta-box registration, save handler, and editor filter.
	 *
	 * @return void
	 */
	public function test_register_hooks_admin_actions(): void {
		$metabox = $this->metabox();
		$metabox->register();

		$post_type = LinkPostType::POST_TYPE;
		self::assertNotFalse( has_action( "add_meta_boxes_{$post_type}", [ $metabox, 'register_meta_boxes' ] ) );
		self::assertNotFalse( has_action( "save_post_{$post_type}", [ $metabox, 'save_post' ] ) );
		self::assertNotFalse( has_filter( 'use_block_editor_for_post_type', [ $metabox, 'disable_block_editor' ] ) );
		self::assertNotFalse( has_action( 'admin_enqueue_scripts', [ $metabox, 'enqueue_unsaved_changes_script' ] ) );
	}

	/**
	 * Verifies the editor filter returns false for the bookmark CPT only.
	 *
	 * @return void
	 */
	public function test_disable_block_editor_only_for_bookmark_cpt(): void {
		$metabox = $this->metabox();

		self::assertFalse( $metabox->disable_block_editor( true, LinkPostType::POST_TYPE ) );
		self::assertTrue( $metabox->disable_block_editor( true, 'post' ) );
		self::assertFalse( $metabox->disable_block_editor( false, 'post' ) );
	}

	/**
	 * Verifies register_meta_boxes calls add_meta_box for both panels.
	 *
	 * @return void
	 */
	public function test_register_meta_boxes(): void {
		Functions\expect( 'add_meta_box' )->twice();

		$this->metabox()->register_meta_boxes();
	}

	/**
	 * Verifies enqueue_unsaved_changes_script enqueues on the bookmark add/edit screen.
	 *
	 * @return void
	 */
	public function test_enqueue_unsaved_changes_script_on_bookmark_post_screen(): void {
		$screen            = Mockery::mock( WP_Screen::class );
		$screen->base      = 'post';
		$screen->post_type = LinkPostType::POST_TYPE;
		Functions\when( 'get_current_screen' )->justReturn( $screen );
		Functions\when( 'plugins_url' )->alias(
			static fn ( string $path ): string => '/wp-content/plugins/apermo-stash/' . $path,
		);

		$enqueued = null;
		Functions\when( 'wp_enqueue_script' )->alias(
			static function ( string $handle ) use ( &$enqueued ): void {
				$enqueued = $handle;
			},
		);
		Functions\stubs( [ 'register_activation_hook', 'register_deactivation_hook', 'add_action' ] );
		( new ReflectionClass( Main::class ) )->getProperty( 'file' )->setValue( null, '/tmp/plugin.php' );

		$this->metabox()->enqueue_unsaved_changes_script();

		self::assertSame( 'apermo-stash-unsaved-changes', $enqueued );
	}

	/**
	 * Verifies enqueue_unsaved_changes_script bails on unrelated screens.
	 *
	 * @return void
	 */
	public function test_enqueue_unsaved_changes_script_skips_other_screens(): void {
		$screen            = Mockery::mock( WP_Screen::class );
		$screen->base      = 'post';
		$screen->post_type = 'post';
		Functions\when( 'get_current_screen' )->justReturn( $screen );

		Functions\expect( 'wp_enqueue_script' )->never();

		$this->metabox()->enqueue_unsaved_changes_script();
	}

	/**
	 * Verifies enqueue_unsaved_changes_script bails when get_current_screen returns null.
	 *
	 * @return void
	 */
	public function test_enqueue_unsaved_changes_script_skips_when_no_screen(): void {
		Functions\when( 'get_current_screen' )->justReturn( null );

		Functions\expect( 'wp_enqueue_script' )->never();

		$this->metabox()->enqueue_unsaved_changes_script();
	}

	/**
	 * Verifies the URL meta box renders the URL, unread, and archived inputs.
	 *
	 * @return void
	 */
	public function test_render_url_meta_box_outputs_inputs(): void {
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $post_id, string $key ) => match ( $key ) {
				LinkMeta::META_URL      => 'https://example.tld',
				LinkMeta::META_FAVORITE => true,
				default                     => '',
			},
		);

		$post     = new WP_Post();
		$post->ID = 7;

		\ob_start();
		$this->metabox()->render_url_meta_box( $post );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'name="apermo_stash_url"', $output );
		self::assertStringContainsString( 'value="https://example.tld"', $output );
		self::assertStringContainsString( 'name="apermo_stash_favorite"', $output );
	}

	/**
	 * Verifies the URL meta box renders the unreachable-warning notice when set.
	 *
	 * @return void
	 */
	public function test_render_url_meta_box_unreachable_notice(): void {
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $post_id, string $key ) => match ( $key ) {
				LinkMeta::META_URL         => 'https://example.tld/down',
				LinkMeta::META_UNREACHABLE => true,
				default                        => '',
			},
		);

		$post     = new WP_Post();
		$post->ID = 7;

		\ob_start();
		$this->metabox()->render_url_meta_box( $post );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'notice-warning', $output );
		self::assertStringContainsString( 'URL didn', $output );
	}

	/**
	 * Verifies the Notes meta box renders the post content in a textarea.
	 *
	 * @return void
	 */
	public function test_render_note_meta_box(): void {
		$post               = new WP_Post();
		$post->ID           = 7;
		$post->post_content = 'These are my notes.';

		\ob_start();
		$this->metabox()->render_note_meta_box( $post );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( '<textarea', $output );
		self::assertStringContainsString( 'These are my notes.', $output );
	}

	/**
	 * Verifies save_post bails when no nonce is present.
	 *
	 * @return void
	 */
	public function test_save_post_bails_without_nonce(): void {
		$post     = new WP_Post();
		$post->ID = 7;

		$this->metabox()->save_post( 7, $post );

		self::assertSame( [], $this->meta_writes );
		self::assertSame( [], $this->post_writes );
	}

	/**
	 * Verifies save_post persists the URL, canonical URL, and flag meta when the form posts a URL.
	 *
	 * @return void
	 */
	public function test_save_post_persists_url_and_flags(): void {
		$_POST = [
			'apermo_stash_metabox_nonce' => 'nonce',
			'apermo_stash_url'           => 'https://www.example.tld/article',
			'apermo_stash_favorite'      => '1',
		];

		$post               = new WP_Post();
		$post->ID           = 7;
		$post->post_title   = 'My title';
		$post->post_content = '';

		$this->metabox()->save_post( 7, $post );

		$keys = \array_column( $this->meta_writes, 1 );
		self::assertContains( LinkMeta::META_URL, $keys );
		self::assertContains( LinkMeta::META_URL_CANONICAL, $keys );
		self::assertContains( LinkMeta::META_FAVORITE, $keys );

		// Title was non-empty, so we should NOT have rewritten it.
		$post_title_writes = \array_filter(
			$this->post_writes,
			static fn ( array $write ): bool => isset( $write['post_title'] ),
		);
		self::assertSame( [], $post_title_writes );
	}

	/**
	 * Verifies save_post derives a fallback title from the URL when the title is empty.
	 *
	 * @return void
	 */
	public function test_save_post_derives_fallback_title(): void {
		$_POST = [
			'apermo_stash_metabox_nonce' => 'nonce',
			'apermo_stash_url'           => 'https://www.example.tld/blog/article',
		];

		$post               = new WP_Post();
		$post->ID           = 7;
		$post->post_title   = '';
		$post->post_content = '';

		$this->metabox()->save_post( 7, $post );

		$title_write = null;
		foreach ( $this->post_writes as $write ) {
			if ( isset( $write['post_title'] ) ) {
				$title_write = $write['post_title'];
				break;
			}
		}

		self::assertSame( 'example.tld/blog/article', $title_write );
	}

	/**
	 * Verifies save_post persists notes only when they differ from the existing content.
	 *
	 * @return void
	 */
	public function test_save_post_persists_notes(): void {
		$_POST = [
			'apermo_stash_metabox_nonce' => 'nonce',
			'apermo_stash_url'           => 'https://example.tld',
			'apermo_stash_note'          => 'New notes',
		];

		$post               = new WP_Post();
		$post->ID           = 7;
		$post->post_title   = 'T';
		$post->post_content = 'Old notes';

		$this->metabox()->save_post( 7, $post );

		$content_write = null;
		foreach ( $this->post_writes as $write ) {
			if ( isset( $write['post_content'] ) ) {
				$content_write = $write['post_content'];
				break;
			}
		}

		self::assertSame( 'New notes', $content_write );
	}

	/**
	 * Builds a metabox wired to a Mockery'd metadata fetcher that reports
	 * unreachable. The fetcher only runs when the URL changes during save,
	 * so most tests don't actually invoke it.
	 *
	 * @return LinkMetabox
	 */
	private function metabox(): LinkMetabox {
		$fetcher = Mockery::mock( MetadataFetcher::class );
		$fetcher->shouldReceive( 'fetch' )->andReturn(
			[
				'title'       => null,
				'description' => null,
				'reachable'   => false,
			],
		);

		return new LinkMetabox( $fetcher );
	}
}
