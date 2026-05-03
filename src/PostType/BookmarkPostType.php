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
	 * Returns the public URL of the SVG used as the admin-menu icon.
	 *
	 * @return string
	 */
	private static function menu_icon_url(): string {
		return plugins_url( 'assets/menu-icon.svg', Main::file() );
	}

	/**
	 * Registers the WordPress hooks that drive CPT registration plus
	 * the admin-menu icon styling.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_icon_styles' ] );
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
				'menu_icon'          => self::menu_icon_url(),
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
	 * Attaches the menu-icon mask CSS to wp-admin's stylesheet.
	 *
	 * Renders the SVG via `background: currentColor` plus `mask-image`
	 * so the icon adopts the menu item's text colour — which the WP
	 * admin colour scheme already paints grey-ish in the idle state and
	 * the scheme's highlight on hover/active. The default `<img>` from
	 * `menu_icon` is hidden so we don't have a coloured logo and a
	 * masked icon stacked on top of each other.
	 *
	 * @return void
	 */
	public function enqueue_menu_icon_styles(): void {
		$icon = self::menu_icon_url();

		$menu_id = '#menu-posts-' . self::POST_TYPE;
		$rules   = $menu_id . ' div.wp-menu-image{background-color:currentColor;'
			. '-webkit-mask-image:url("' . esc_url( $icon ) . '");mask-image:url("' . esc_url( $icon ) . '");'
			. '-webkit-mask-position:center 7px;mask-position:center 7px;'
			. '-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;'
			. '-webkit-mask-size:20px;mask-size:20px;}'
			. $menu_id . ' div.wp-menu-image::before,'
			. $menu_id . ' div.wp-menu-image img{display:none;}';

		wp_add_inline_style( 'wp-admin', $rules );
	}
}
