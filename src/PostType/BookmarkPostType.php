<?php

declare(strict_types=1);

namespace Apermo\LinkStash\PostType;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\Main;

/**
 * Registers the linkstash_bookmark custom post type.
 */
class BookmarkPostType {

	public const POST_TYPE = 'linkstash_bookmark';

	/**
	 * Caches the data-URI form of the menu icon so the file is read at
	 * most once per request.
	 *
	 * @var string|null
	 */
	private static ?string $menu_icon_cache = null;

	/**
	 * Returns the menu icon as a base64 data URI.
	 *
	 * Falls back to the empty string if the SVG file cannot be read,
	 * which makes WordPress render an empty icon container — preferable
	 * to an exception that breaks the admin.
	 *
	 * @return string
	 */
	private static function menu_icon_data_uri(): string {
		if ( self::$menu_icon_cache !== null ) {
			return self::$menu_icon_cache;
		}

		$path = \dirname( Main::file() ) . '/assets/menu-icon.svg';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$markup = \is_readable( $path ) ? (string) \file_get_contents( $path ) : '';

		if ( $markup === '' ) {
			self::$menu_icon_cache = '';
			return self::$menu_icon_cache;
		}

		// $markup is the contents of an SVG file shipped with the plugin;
		// base64_encode is the standard data-URI encoding, not obfuscation.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		self::$menu_icon_cache = 'data:image/svg+xml;base64,' . \base64_encode( $markup );

		return self::$menu_icon_cache;
	}

	/**
	 * Registers the WordPress hooks that drive CPT registration plus
	 * the admin-menu icon styling.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_print_styles', [ $this, 'print_menu_icon_styles' ] );
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
				'menu_icon'          => self::menu_icon_data_uri(),
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
	 * Prints admin styles that apply the menu icon as a CSS mask.
	 *
	 * Rendering the SVG via `background: currentColor` plus `mask-image`
	 * makes the icon adopt the menu item's text color — which the WP
	 * admin color scheme already paints grey-ish in the idle state and
	 * the scheme's highlight on hover/active. The default `<img>` from
	 * `menu_icon` is hidden so we don't have a coloured logo and a
	 * masked icon stacked on top of each other.
	 *
	 * @return void
	 */
	public function print_menu_icon_styles(): void {
		$icon = self::menu_icon_data_uri();
		if ( $icon === '' ) {
			return;
		}

		$menu_id = '#menu-posts-' . self::POST_TYPE;
		$rules   = $menu_id . ' div.wp-menu-image{background-color:currentColor;'
			. '-webkit-mask-image:url("' . $icon . '");mask-image:url("' . $icon . '");'
			. '-webkit-mask-position:center 7px;mask-position:center 7px;'
			. '-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;'
			. '-webkit-mask-size:20px;mask-size:20px;}'
			. $menu_id . ' div.wp-menu-image::before,'
			. $menu_id . ' div.wp-menu-image img{display:none;}';

		// $icon is base64 from a known-safe SVG file shipped with the
		// plugin; $menu_id is composed from a class constant. No user
		// data flows into the CSS string.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "<style id=\"linkstash-menu-icon\">{$rules}</style>\n";
	}
}
