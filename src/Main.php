<?php

declare(strict_types=1);

namespace Apermo\LinkStash;

use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;

/**
 * Bootstraps the plugin.
 */
class Main {

	public const VERSION = '0.1.0';

	/**
	 * Holds the main plugin file path.
	 *
	 * @var string
	 */
	private static string $file = '';

	/**
	 * Initializes the plugin.
	 *
	 * @param string $file Main plugin file path.
	 *
	 * @return void
	 */
	public static function init( string $file ): void {
		self::$file = $file;

		register_activation_hook( $file, [ self::class, 'activate' ] );
		register_deactivation_hook( $file, [ self::class, 'deactivate' ] );
		add_action( 'plugins_loaded', [ self::class, 'boot' ] );
	}

	/**
	 * Returns the main plugin file path.
	 *
	 * @return string
	 */
	public static function file(): string {
		return self::$file;
	}

	/**
	 * Activates the plugin.
	 *
	 * Registers the CPT once so that subsequent rewrite-rule flushes know the
	 * post type, then flushes rewrites.
	 *
	 * @return void
	 */
	public static function activate(): void {
		( new BookmarkPostType() )->register_post_type();
		( new TagTaxonomy() )->register_taxonomy();
		flush_rewrite_rules();
	}

	/**
	 * Deactivates the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Boots the plugin after all plugins are loaded.
	 *
	 * @return void
	 */
	public static function boot(): void {
		( new BookmarkPostType() )->register();
		( new TagTaxonomy() )->register();
		( new BookmarkMeta() )->register();
	}
}
