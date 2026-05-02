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
	 * Normalises a boolean flag for `update_post_meta()` storage.
	 *
	 * Plain booleans round-trip through update_post_meta as `'1'` and
	 * empty string, which breaks `meta_query` comparisons against `'0'`
	 * for the false case. Storing the explicit string here keeps reads,
	 * writes, and queries on the same shape.
	 *
	 * @param bool $flag Boolean flag.
	 *
	 * @return string `'1'` for true, `'0'` for false.
	 */
	public static function bool_to_meta( bool $flag ): string {
		return $flag ? '1' : '0';
	}

	/**
	 * Sanitises a flag value coming from the REST API into the storage shape.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string `'1'` or `'0'`.
	 */
	public static function sanitize_bool_meta( mixed $value ): string {
		// @phpstan-ignore argument.templateType
		return self::bool_to_meta( rest_sanitize_boolean( \is_scalar( $value ) ? $value : false ) );
	}

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
				'sanitize_callback' => [ self::class, 'sanitize_bool_meta' ],
				'auth_callback'     => $auth_callback,
				'default'           => '0',
			],
		);

		register_post_meta(
			BookmarkPostType::POST_TYPE,
			self::META_ARCHIVED,
			[
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_bool_meta' ],
				'auth_callback'     => $auth_callback,
				'default'           => '0',
			],
		);
	}
}
