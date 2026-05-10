<?php

declare(strict_types=1);

namespace Apermo\Stash\PostType;

\defined( 'ABSPATH' ) || exit();

/**
 * Registers the apermo_stash_tag taxonomy attached to the link CPT.
 */
class TagTaxonomy {

	public const TAXONOMY = 'apermo_stash_tag';

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
			LinkPostType::POST_TYPE,
			[
				'labels'            => [
					'name'          => __( 'Tags', 'apermo-stash' ),
					'singular_name' => __( 'Tag', 'apermo-stash' ),
					'search_items'  => __( 'Search Tags', 'apermo-stash' ),
					'all_items'     => __( 'All Tags', 'apermo-stash' ),
					'edit_item'     => __( 'Edit Tag', 'apermo-stash' ),
					'update_item'   => __( 'Update Tag', 'apermo-stash' ),
					'add_new_item'  => __( 'Add New Tag', 'apermo-stash' ),
					'new_item_name' => __( 'New Tag Name', 'apermo-stash' ),
					'menu_name'     => __( 'Tags', 'apermo-stash' ),
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
