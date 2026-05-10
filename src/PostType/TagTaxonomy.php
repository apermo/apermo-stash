<?php

declare(strict_types=1);

namespace Apermo\Stash\PostType;

\defined( 'ABSPATH' ) || exit();

/**
 * Registers the linkstash_tag taxonomy attached to the bookmark CPT.
 */
class TagTaxonomy {

	public const TAXONOMY = 'linkstash_tag';

	/**
	 * Registers the WordPress hook that triggers taxonomy registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_taxonomy' ] );
	}

	/**
	 * Registers the taxonomy with WordPress.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			BookmarkPostType::POST_TYPE,
			[
				'labels'            => [
					'name'          => __( 'Tags', 'linkstash' ),
					'singular_name' => __( 'Tag', 'linkstash' ),
					'search_items'  => __( 'Search Tags', 'linkstash' ),
					'all_items'     => __( 'All Tags', 'linkstash' ),
					'edit_item'     => __( 'Edit Tag', 'linkstash' ),
					'update_item'   => __( 'Update Tag', 'linkstash' ),
					'add_new_item'  => __( 'Add New Tag', 'linkstash' ),
					'new_item_name' => __( 'New Tag Name', 'linkstash' ),
					'menu_name'     => __( 'Tags', 'linkstash' ),
				],
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'tags',
				'rewrite'           => false,
			],
		);
	}
}
