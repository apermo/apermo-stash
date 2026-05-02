<?php

declare(strict_types=1);

namespace Apermo\LinkStash;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\Admin\BookmarkMetabox;
use Apermo\LinkStash\Admin\DashboardWidget;
use Apermo\LinkStash\Admin\HelpTabs;
use Apermo\LinkStash\Admin\ListColumns;
use Apermo\LinkStash\Admin\Notices;
use Apermo\LinkStash\Admin\QuickAdd;
use Apermo\LinkStash\Admin\SettingsPage;
use Apermo\LinkStash\Admin\TagAutocomplete;
use Apermo\LinkStash\Admin\UrlAutoScheme;
use Apermo\LinkStash\Auth\BearerTokenAuth;
use Apermo\LinkStash\Auth\TokenStore;
use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;
use Apermo\LinkStash\Rest\BookmarksController;
use Apermo\LinkStash\Rest\CheckController;
use Apermo\LinkStash\Rest\CorsHandler;
use Apermo\LinkStash\Rest\RestController;
use Apermo\LinkStash\Rest\TagsController;
use Apermo\LinkStash\Url\MetadataFetcher;

/**
 * Bootstraps the plugin.
 */
class Main {

	public const VERSION = '0.1.1';

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
		( new BearerTokenAuth( new TokenStore() ) )->register();
		( new RestController(
			new BookmarksController( new MetadataFetcher() ),
			new TagsController(),
			new CheckController(),
		) )->register();
		( new CorsHandler() )->register();

		if ( is_admin() ) {
			$store = new TokenStore();
			( new ListColumns() )->register();
			( new Notices() )->register();
			( new QuickAdd( new MetadataFetcher() ) )->register();
			( new SettingsPage( $store ) )->register();
			( new BookmarkMetabox( new MetadataFetcher() ) )->register();
			( new DashboardWidget() )->register();
			( new TagAutocomplete() )->register();
			( new UrlAutoScheme() )->register();
			( new HelpTabs() )->register();
		}
	}
}
