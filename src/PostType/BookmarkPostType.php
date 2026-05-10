<?php

declare(strict_types=1);

namespace Apermo\Stash\PostType;

\defined( 'ABSPATH' ) || exit();

use Apermo\Stash\Main;

/**
 * Registers the linkstash_bookmark custom post type.
 */
class BookmarkPostType {

	public const POST_TYPE = 'linkstash_bookmark';

	/**
	 * Caches the SVG markup so the file is read at most once per request.
	 *
	 * @var string|null
	 */
	private static ?string $svg_cache = null;

	/**
	 * Returns the inline SVG markup for the admin-menu icon.
	 *
	 * Reads the plugin-shipped `assets/menu-icon.svg` once per request.
	 * Returns the empty string if the file cannot be read, in which case
	 * the menu item simply renders without an icon.
	 *
	 * @return string
	 */
	private static function svg_markup(): string {
		if ( self::$svg_cache !== null ) {
			return self::$svg_cache;
		}

		$path = \dirname( Main::file() ) . '/assets/menu-icon.svg';
		// SVG file is a static plugin-shipped asset; WP_Filesystem is the
		// recommended-by-sniff alternative but brings request-time setup
		// overhead for what is a single static read.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		self::$svg_cache = \is_readable( $path ) ? (string) \file_get_contents( $path ) : '';

		return self::$svg_cache;
	}

	/**
	 * Registers the WordPress hooks that drive CPT registration plus
	 * the admin-menu icon styling and SVG inlining.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_icon_styles' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'inline_menu_icon' ] );
	}

	/**
	 * Registers the CPT with WordPress.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			// register_post_type accepts a flat options array; using a typed
			// object would be busywork.
			// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys
			[
				'labels'             => [
					'name'               => __( 'Bookmarks', 'linkstash' ),
					'singular_name'      => __( 'Bookmark', 'linkstash' ),
					'menu_name'          => __( 'LinkStash', 'linkstash' ),
					'add_new'            => __( 'Add New', 'linkstash' ),
					'add_new_item'       => __( 'Add New Bookmark', 'linkstash' ),
					'edit_item'          => __( 'Edit Bookmark', 'linkstash' ),
					'new_item'           => __( 'New Bookmark', 'linkstash' ),
					'view_item'          => __( 'View Bookmark', 'linkstash' ),
					'search_items'       => __( 'Search Bookmarks', 'linkstash' ),
					'not_found'          => __( 'No bookmarks found.', 'linkstash' ),
					'not_found_in_trash' => __( 'No bookmarks found in Trash.', 'linkstash' ),
					'all_items'          => __( 'All Bookmarks', 'linkstash' ),
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'bookmarks',
				'menu_icon'          => 'none',
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'supports'           => [ 'title' ],
				'has_archive'        => false,
				'hierarchical'       => false,
				'rewrite'            => false,
			],
		);
	}

	/**
	 * Attaches the menu-icon sizing + colour CSS to wp-admin's stylesheet.
	 *
	 * The SVG ships with `<g fill="currentColor">`, so the icon adopts the
	 * `color` property of its parent. Idle state explicitly sets the same
	 * grey WP uses for native dashicons (`#a7aaad`); hover and current
	 * states swap to the WP admin colour scheme's accent (the
	 * `--wp-admin-theme-color` custom property defined per scheme since
	 * WP 5.7) so the icon picks up the user's chosen scheme highlight.
	 *
	 * @return void
	 */
	public function enqueue_menu_icon_styles(): void {
		$menu_id = '#menu-posts-' . self::POST_TYPE;
		$rules   = $menu_id . ' div.wp-menu-image{color:#a7aaad;}'
			. $menu_id . ' div.wp-menu-image svg{display:block;width:20px;height:20px;margin:7px auto 0;}'
			. $menu_id . ':hover div.wp-menu-image,'
			. $menu_id . '.wp-has-current-submenu div.wp-menu-image,'
			. $menu_id . '.current div.wp-menu-image{color:var(--wp-admin-theme-color,#2271b1);}';

		wp_add_inline_style( 'wp-admin', $rules );
	}

	/**
	 * Injects the SVG markup directly into the admin menu icon container.
	 *
	 * Setting `menu_icon` to `none` makes WP render an empty
	 * `.wp-menu-image` div (no `<img>` tag); this hook fills that div
	 * with the actual SVG markup so `currentColor` references inside the
	 * SVG cascade from the parent CSS, which `<img src=...svg>` would
	 * not allow.
	 *
	 * @return void
	 */
	public function inline_menu_icon(): void {
		$markup = self::svg_markup();
		if ( $markup === '' ) {
			return;
		}

		// $markup is read from a plugin-shipped static SVG file and embedded
		// inside a JS string literal via wp_json_encode (which produces
		// JS-safe escaping); the surrounding script tag and selector are
		// constants composed from a class constant. No user data flows in.
		$selector = '#menu-posts-' . self::POST_TYPE . ' .wp-menu-image';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script id="linkstash-menu-icon">'
			. '(function(){'
			. 'var d=document.querySelector(' . wp_json_encode( $selector ) . ');'
			. 'if(d){d.innerHTML=' . wp_json_encode( $markup ) . ';}'
			. '})();'
			. "</script>\n";
	}
}
