<?php

declare(strict_types=1);

namespace Apermo\LinkStash;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\Admin\BookmarkMetabox;
use Apermo\LinkStash\Admin\DashboardWidget;
use Apermo\LinkStash\Admin\HelpTabs;
use Apermo\LinkStash\Admin\ListColumns;
use Apermo\LinkStash\Admin\ListFilter;
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

	public const VERSION = '0.1.3';

	private const STARTER_TAGS_SEEDED_OPTION = 'linkstash_starter_tags_seeded';

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
		self::seed_starter_tags();
		flush_rewrite_rules();
	}

	/**
	 * Seeds a small set of starter tags exactly once.
	 *
	 * Sets `linkstash_starter_tags_seeded` after the first fully-successful
	 * run; subsequent (re-)activations short-circuit on that option, so a
	 * user who deletes a starter tag and later reactivates the plugin
	 * will not see it resurrected. Existing tags with the same slug are
	 * still skipped on the very first run so a pre-existing site is
	 * never disturbed.
	 *
	 * If any `wp_insert_term` call returns a `WP_Error` (e.g. a transient
	 * DB failure during activation) the marker is **not** written, so the
	 * next activation retries the missing tags.
	 *
	 * @return void
	 */
	private static function seed_starter_tags(): void {
		if ( (bool) get_option( self::STARTER_TAGS_SEEDED_OPTION, false ) ) {
			return;
		}

		$tags = [
			'read-later'  => __( 'Read later', 'linkstash' ),
			'reference'   => __( 'Reference', 'linkstash' ),
			'inspiration' => __( 'Inspiration', 'linkstash' ),
			'archive'     => __( 'Archive', 'linkstash' ),
		];

		$all_ok = true;
		foreach ( $tags as $slug => $name ) {
			if ( term_exists( $slug, TagTaxonomy::TAXONOMY ) !== null ) {
				continue;
			}
			$result = wp_insert_term( $name, TagTaxonomy::TAXONOMY, [ 'slug' => $slug ] );
			if ( is_wp_error( $result ) ) {
				$all_ok = false;
			}
		}

		if ( $all_ok ) {
			update_option( self::STARTER_TAGS_SEEDED_OPTION, true, false );
		}
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
			( new ListFilter() )->register();
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
