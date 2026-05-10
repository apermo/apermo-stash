<?php

declare(strict_types=1);

namespace Apermo\Stash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\Stash\PostType\BookmarkPostType;
use WP_Screen;

/**
 * Adds contextual help tabs to LinkStash admin screens.
 *
 * Hooks `current_screen` and decides per-screen what tabs to register
 * via `WP_Screen::add_help_tab()` plus a sidebar via
 * `WP_Screen::set_help_sidebar()`. The "Help" pulldown that WordPress
 * renders at the top right of every admin screen surfaces them.
 */
class HelpTabs {

	private const SCREEN_ID           = 'edit-' . BookmarkPostType::POST_TYPE;
	private const PLUGIN_REPO_URL     = 'https://github.com/apermo/linkstash';
	private const EXTENSION_STORE_URL = 'https://chromewebstore.google.com/detail/linkstash/midebpgblmgkcgljcgojjbehnonljnmk';
	private const EXTENSION_REPO_URL  = 'https://github.com/apermo/linkstash-extension';

	/**
	 * Returns the Overview tab markup.
	 *
	 * @return string
	 */
	private static function overview_html(): string {
		return '<p>' . esc_html__(
			'LinkStash turns your WordPress site into a personal bookmark archive. Every URL you save is a real WordPress post (custom post type linkstash_bookmark), so it is searchable, taggable, and exportable like any other content on the site.',
			'linkstash',
		) . '</p>'
			. '<p>' . esc_html__( 'Use this screen to browse and edit your saved bookmarks. The columns show:', 'linkstash' ) . '</p>'
			. '<ul>'
			. '<li>' . wp_kses(
				__( '<strong>URL</strong> — the bookmarked page; click the link to open it in a new tab.', 'linkstash' ),
				[ 'strong' => [] ],
			) . '</li>'
			. '<li>' . wp_kses(
				__( '<strong>Tags</strong> — comma-separated tags. Click a tag to filter the list to only matching bookmarks.', 'linkstash' ),
				[ 'strong' => [] ],
			) . '</li>'
			. '<li>' . wp_kses(
				__( '<strong>Visibility</strong> — Public bookmarks are visible to anyone (including unauthenticated REST callers); Private are visible only to you and editors with the edit_others_posts capability.', 'linkstash' ),
				[ 'strong' => [] ],
			) . '</li>'
			. '<li>' . wp_kses(
				__( '<strong>Favorite</strong> — a star you set yourself. Filter the list to favorites with the URL parameter ?favorite=1, or use it as a manual quality marker.', 'linkstash' ),
				[ 'strong' => [] ],
			) . '</li>'
			. '</ul>';
	}

	/**
	 * Returns the Adding bookmarks tab markup.
	 *
	 * @return string
	 */
	private static function add_bookmarks_html(): string {
		return '<p>' . esc_html__( 'There are several ways to save a URL to LinkStash:', 'linkstash' ) . '</p>'
			. '<ul>'
			. '<li>' . wp_kses(
				__( '<strong>Add New Bookmark</strong> — the button at the top of this screen opens the bookmark editor. Paste a URL, optionally set a title, notes, tags, and visibility.', 'linkstash' ),
				[ 'strong' => [] ],
			) . '</li>'
			. '<li>' . wp_kses(
				__( '<strong>Dashboard widget</strong> — the "Add bookmark" tile on your WordPress dashboard offers one-step capture from anywhere in the admin. Tag autocomplete works there too.', 'linkstash' ),
				[ 'strong' => [] ],
			) . '</li>'
			. '<li>' . wp_kses(
				__( '<strong>Browser extension</strong> — see the next tab.', 'linkstash' ),
				[ 'strong' => [] ],
			) . '</li>'
			. '<li>' . wp_kses(
				\sprintf(
					/* translators: 1: REST endpoint, 2: Settings page label. */
					__( '<strong>REST API</strong> — POST to %1$s with an Authorization Bearer token generated under %2$s.', 'linkstash' ),
					'<code>/wp-json/linkstash/v1/bookmarks</code>',
					'<strong>' . esc_html__( 'Settings → LinkStash', 'linkstash' ) . '</strong>',
				),
				[
					'strong' => [],
					'code'   => [],
				],
			) . '</li>'
			. '</ul>'
			. '<p>' . esc_html__( 'LinkStash dedupes by canonical URL: saving the same page twice updates the existing record instead of creating a duplicate. The X-LinkStash-Existing response header on a re-save tells API clients which path was taken.', 'linkstash' ) . '</p>';
	}

	/**
	 * Returns the Browser extension tab markup.
	 *
	 * @return string
	 */
	private static function extension_html(): string {
		$store_link = '<a href="' . esc_url( self::EXTENSION_STORE_URL ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Chrome Web Store', 'linkstash' ) . '</a>';
		$repo_link  = '<a href="' . esc_url( self::EXTENSION_REPO_URL ) . '" target="_blank" rel="noopener">'
			. esc_html( self::EXTENSION_REPO_URL ) . '</a>';

		$kses_a = [
			'a' => [
				'href'   => [],
				'target' => [],
				'rel'    => [],
			],
		];

		return '<p>' . wp_kses(
			\sprintf(
				/* translators: 1: Chrome Web Store link, 2: GitHub source link. */
				esc_html__( 'A companion Chrome extension is available on the %1$s. The source is at %2$s if you prefer to review it or install from source.', 'linkstash' ),
				$store_link,
				$repo_link,
			),
			$kses_a,
		) . '</p>'
			. '<p>' . esc_html__( 'Once installed, the extension adds:', 'linkstash' ) . '</p>'
			. '<ul>'
			. '<li>' . esc_html__( 'A toolbar action that saves the current tab in one click.', 'linkstash' ) . '</li>'
			. '<li>' . esc_html__( 'A green checkmark on the toolbar icon when the open page is already saved.', 'linkstash' ) . '</li>'
			. '<li>' . esc_html__( 'An edit-from-popup flow with title, description, tags, and a public/private toggle.', 'linkstash' ) . '</li>'
			. '<li>' . esc_html__( 'A right-click "Save link to LinkStash" context-menu entry, so you can save a link without visiting it.', 'linkstash' ) . '</li>'
			. '</ul>'
			. '<p>' . wp_kses(
				\sprintf(
					/* translators: %s: Settings page label. */
					esc_html__( 'After installing, open the extension\'s options page, enter your site URL and a Bearer token generated under %s, and pick your default visibility.', 'linkstash' ),
					'<strong>' . esc_html__( 'Settings → LinkStash', 'linkstash' ) . '</strong>',
				),
				[ 'strong' => [] ],
			) . '</p>';
	}

	/**
	 * Returns the help-sidebar markup with quick links.
	 *
	 * @return string
	 */
	private static function sidebar_html(): string {
		$settings_url = admin_url( 'options-general.php?page=linkstash' );

		return '<p><strong>' . esc_html__( 'For more information:', 'linkstash' ) . '</strong></p>'
			. '<p><a href="' . esc_url( self::PLUGIN_REPO_URL ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Plugin source &amp; README', 'linkstash' )
			. '</a></p>'
			. '<p><a href="' . esc_url( self::EXTENSION_STORE_URL ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Chrome extension', 'linkstash' )
			. '</a></p>'
			. '<p><a href="' . esc_url( $settings_url ) . '">'
			. esc_html__( 'Settings → LinkStash', 'linkstash' )
			. '</a></p>';
	}

	/**
	 * Hooks `current_screen` so we can attach tabs once WP has resolved
	 * which admin screen is being rendered.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'current_screen', [ $this, 'maybe_add_help_tabs' ] );
	}

	/**
	 * Attaches the LinkStash help tabs when the current screen is the
	 * bookmark list table.
	 *
	 * @param WP_Screen $screen Current admin screen.
	 *
	 * @return void
	 */
	public function maybe_add_help_tabs( WP_Screen $screen ): void {
		if ( $screen->id !== self::SCREEN_ID ) {
			return;
		}

		$screen->add_help_tab(
			[
				'id'      => 'linkstash-overview',
				'title'   => esc_html__( 'Overview', 'linkstash' ),
				'content' => self::overview_html(),
			],
		);

		$screen->add_help_tab(
			[
				'id'      => 'linkstash-add-bookmarks',
				'title'   => esc_html__( 'Adding bookmarks', 'linkstash' ),
				'content' => self::add_bookmarks_html(),
			],
		);

		$screen->add_help_tab(
			[
				'id'      => 'linkstash-extension',
				'title'   => esc_html__( 'Browser extension', 'linkstash' ),
				'content' => self::extension_html(),
			],
		);

		$screen->set_help_sidebar( self::sidebar_html() );
	}
}
