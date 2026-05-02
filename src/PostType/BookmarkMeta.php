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
	public const META_UNREACHABLE   = '_linkstash_unreachable';

	/**
	 * Registers a single-value string meta with REST exposure.
	 *
	 * @param string          $key      Meta key.
	 * @param callable():bool $auth_cb Auth callback.
	 *
	 * @return void
	 */
	private static function register_string_meta( string $key, callable $auth_cb ): void {
		register_post_meta(
			BookmarkPostType::POST_TYPE,
			$key,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $auth_cb,
				'default'           => '',
			],
		);
	}

	/**
	 * Registers a single-value boolean meta with REST exposure.
	 *
	 * @param string          $key      Meta key.
	 * @param callable():bool $auth_cb Auth callback.
	 *
	 * @return void
	 */
	private static function register_bool_meta( string $key, callable $auth_cb ): void {
		register_post_meta(
			BookmarkPostType::POST_TYPE,
			$key,
			[
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth_cb,
				'default'           => false,
			],
		);
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

		self::register_string_meta( self::META_URL, $auth_callback );
		self::register_string_meta( self::META_URL_CANONICAL, $auth_callback );
		self::register_bool_meta( self::META_UNREAD, $auth_callback );
		self::register_bool_meta( self::META_ARCHIVED, $auth_callback );
		self::register_bool_meta( self::META_UNREACHABLE, $auth_callback );
	}
}
