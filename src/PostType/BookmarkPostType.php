<?php

declare(strict_types=1);

namespace Apermo\LinkStash\PostType;

/**
 * Registers the linkstash_bookmark custom post type.
 */
class BookmarkPostType {

	public const POST_TYPE = 'linkstash_bookmark';

	/**
	 * Registers the WordPress hook that triggers CPT registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Registers the CPT with WordPress.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
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
				'menu_icon'          => 'dashicons-admin-links',
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'supports'           => [ 'title', 'editor', 'custom-fields' ],
				'has_archive'        => false,
				'hierarchical'       => false,
				'rewrite'            => false,
			],
		);
	}
}
