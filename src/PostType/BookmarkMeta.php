<?php

declare(strict_types=1);

namespace Apermo\LinkStash\PostType;

\defined( 'ABSPATH' ) || exit();

/**
 * Registers post-meta keys for the bookmark CPT.
 */
class BookmarkMeta {

	public const META_URL           = '_linkstash_url';
	public const META_URL_CANONICAL = '_linkstash_url_canonical';
	public const META_UNREAD        = '_linkstash_unread';
	public const META_ARCHIVED      = '_linkstash_archived';

	/**
	 * Registers the WordPress hook that triggers meta registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_meta' ] );
	}

	/**
	 * Registers all bookmark meta keys with REST exposure.
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$auth_callback = static fn (): bool => current_user_can( 'edit_posts' );

		register_post_meta(
			BookmarkPostType::POST_TYPE,
			self::META_URL,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $auth_callback,
				'default'           => '',
			],
		);

		register_post_meta(
			BookmarkPostType::POST_TYPE,
			self::META_URL_CANONICAL,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $auth_callback,
				'default'           => '',
			],
		);

		register_post_meta(
			BookmarkPostType::POST_TYPE,
			self::META_UNREAD,
			[
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth_callback,
				'default'           => false,
			],
		);

		register_post_meta(
			BookmarkPostType::POST_TYPE,
			self::META_ARCHIVED,
			[
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth_callback,
				'default'           => false,
			],
		);
	}
}
