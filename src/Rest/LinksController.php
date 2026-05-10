<?php

declare(strict_types=1);

namespace Apermo\Stash\Rest;

\defined( 'ABSPATH' ) || exit();

use Apermo\Stash\PostType\LinkMeta;
use Apermo\Stash\PostType\LinkPostType;
use Apermo\Stash\PostType\TagTaxonomy;
use Apermo\Stash\Url\Canonicalizer;
use Apermo\Stash\Url\MetadataFetcher;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles bookmark CRUD over REST.
 */
class LinksController {

	private const MAX_PER_PAGE = 100;

	/**
	 * Holds the metadata fetcher used when title/description are missing.
	 *
	 * @var MetadataFetcher
	 */
	private MetadataFetcher $fetcher;

	/**
	 * Constructs the controller.
	 *
	 * @param MetadataFetcher $fetcher URL metadata fetcher.
	 */
	public function __construct( MetadataFetcher $fetcher ) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Returns the visibility query fragments for the current request.
	 *
	 * Anonymous callers see only published bookmarks. Authenticated callers
	 * see public bookmarks from anyone plus their own private bookmarks
	 * (via WP_Query's `perm => 'readable'`). Callers with
	 * `edit_others_posts` see everything.
	 *
	 * The optional `public` / `private` query params narrow the result:
	 * `public=1` returns only public bookmarks (everyone's); `private=1`
	 * returns only the caller's own private bookmarks (since others'
	 * private bookmarks are never readable). When both or neither flag is
	 * set the default "own + public" behaviour applies.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array{post_status: list<string>, author: list<int>|null, perm: ?string}
	 */
	public static function visibility_filter( WP_REST_Request $request ): array {
		$current_user = get_current_user_id();
		$want_public  = (bool) $request->get_param( 'public' );
		$want_private = (bool) $request->get_param( 'private' );

		if ( $current_user === 0 ) {
			return [
				'post_status' => [ 'publish' ],
				'author'      => null,
				'perm'        => null,
			];
		}

		if ( $want_public !== $want_private ) {
			if ( $want_public ) {
				return [
					'post_status' => [ 'publish' ],
					'author'      => null,
					'perm'        => null,
				];
			}

			return [
				'post_status' => [ 'private' ],
				'author'      => [ $current_user ],
				'perm'        => null,
			];
		}

		if ( current_user_can( 'edit_others_posts' ) ) {
			return [
				'post_status' => [ 'publish', 'private' ],
				'author'      => null,
				'perm'        => null,
			];
		}

		return [
			'post_status' => [ 'publish', 'private' ],
			'author'      => null,
			'perm'        => 'readable',
		];
	}

	/**
	 * Reads an optional bool param, returning null when absent.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $key     Param key.
	 *
	 * @return bool|null
	 */
	private static function optional_bool( WP_REST_Request $request, string $key ): ?bool {
		if ( ! $request->has_param( $key ) ) {
			return null;
		}

		$value = $request->get_param( $key );

		// @phpstan-ignore argument.templateType
		return rest_sanitize_boolean( \is_scalar( $value ) ? $value : false );
	}

	/**
	 * Returns the args schema for list_items.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function list_args(): array {
		return [
			'page'     => [
				'type' => 'integer',
				'default' => 1,
				'minimum' => 1,
			],
			'per_page' => [
				'type' => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => self::MAX_PER_PAGE,
			],
			'tag'      => [ 'type' => 'string' ],
			'q'        => [ 'type' => 'string' ],
			'favorite' => [ 'type' => 'boolean' ],
			'public'   => [ 'type' => 'boolean' ],
			'private'  => [ 'type' => 'boolean' ],
		];
	}

	/**
	 * Returns the args schema for create_item.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function create_args(): array {
		return [
			'url'         => [
				'type' => 'string',
				'required' => true,
				'format' => 'uri',
			],
			'title'       => [ 'type' => 'string' ],
			'description' => [ 'type' => 'string' ],
			'tags'        => [
				'type' => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'favorite'    => [ 'type' => 'boolean' ],
			'public'      => [ 'type' => 'boolean' ],
		];
	}

	/**
	 * Returns the args schema for update_item.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function update_args(): array {
		return [
			'url'         => [
				'type' => 'string',
				'format' => 'uri',
			],
			'title'       => [ 'type' => 'string' ],
			'description' => [ 'type' => 'string' ],
			'tags'        => [
				'type' => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'favorite'    => [ 'type' => 'boolean' ],
			'public'      => [ 'type' => 'boolean' ],
		];
	}

	/**
	 * Builds the meta_query fragment from the favorite param.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return list<array<string, string>>
	 */
	private static function build_meta_query( WP_REST_Request $request ): array {
		$meta_query = [];

		$favorite = $request->get_param( 'favorite' );
		if ( $favorite !== null ) {
			$meta_query[] = [
				'key'   => LinkMeta::META_FAVORITE,
				'value' => LinkMeta::sanitize_bool_meta( $favorite ),
			];
		}

		return $meta_query;
	}

	/**
	 * Registers list/create and per-ID routes.
	 *
	 * @param string $rest_namespace REST namespace.
	 *
	 * @return void
	 */
	public function register_routes( string $rest_namespace ): void {
		register_rest_route(
			$rest_namespace,
			'/bookmarks',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_items' ],
					'permission_callback' => [ Permissions::class, 'allow_anyone' ],
					'args'                => self::list_args(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ Permissions::class, 'require_edit_posts' ],
					'args'                => self::create_args(),
				],
			],
		);

		register_rest_route(
			$rest_namespace,
			'/bookmarks/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ Permissions::class, 'can_read_bookmark' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ Permissions::class, 'can_edit_bookmark' ],
					'args'                => self::update_args(),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ Permissions::class, 'can_delete_bookmark' ],
				],
			],
		);
	}

	/**
	 * Lists bookmarks.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$query = new WP_Query( $this->build_list_args( $request ) );
		$items = \array_map( [ $this, 'prepare_response' ], $query->posts );

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) \max( 1, $query->max_num_pages ) );

		return $response;
	}

	/**
	 * Builds the WP_Query args for list_items.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array<string, mixed>
	 */
	private function build_list_args( WP_REST_Request $request ): array {
		$page       = \max( 1, (int) $request->get_param( 'page' ) );
		$per_page   = \min( self::MAX_PER_PAGE, \max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$visibility = self::visibility_filter( $request );

		$args = [
			'post_type'      => LinkPostType::POST_TYPE,
			'paged'          => $page,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => $visibility['post_status'],
		];

		if ( $visibility['author'] !== null ) {
			$args['author__in'] = $visibility['author'];
		}
		if ( $visibility['perm'] !== null ) {
			$args['perm'] = $visibility['perm'];
		}

		$tag = sanitize_text_field( (string) ( $request->get_param( 'tag' ) ?? '' ) );
		if ( $tag !== '' ) {
			// Tag filter is the documented way to scope the listing; the
			// taxonomy is small in practice (one slug per saved bookmark tag).
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'] = [
				[
					'taxonomy' => TagTaxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $tag,
				],
			];
		}

		$search = sanitize_text_field( (string) ( $request->get_param( 'q' ) ?? '' ) );
		if ( $search !== '' ) {
			$args['s'] = $search;
		}

		$meta_query = self::build_meta_query( $request );
		if ( $meta_query !== [] ) {
			// Filtering by unread/archived booleans needs meta_query; the
			// alternative would be loading every post and filtering in PHP.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = $meta_query;
		}

		return $args;
	}

	/**
	 * Creates a bookmark with idempotent dedupe by canonical URL.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 *
	 * Linear shape (validate, fetch metadata, dedupe, persist) reads more
	 * naturally as a single function than as a chain of micro-helpers.
	 *
	 * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
	 */
	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$url = esc_url_raw( (string) $request->get_param( 'url' ) );
		if ( $url === '' ) {
			return new WP_Error( 'apermo_stash_missing_url', __( 'A url is required.', 'apermo-stash' ), [ 'status' => 400 ] );
		}

		$canonical = Canonicalizer::canonicalize( $url );
		if ( $canonical === '' ) {
			return new WP_Error( 'apermo_stash_invalid_url', __( 'The url is not valid.', 'apermo-stash' ), [ 'status' => 400 ] );
		}

		$user_id  = get_current_user_id();
		$existing = $this->find_by_canonical( $user_id, $canonical );

		$title        = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) );
		$description  = sanitize_textarea_field( (string) ( $request->get_param( 'description' ) ?? '' ) );
		$meta_fetched = null;

		[ $title, $description, $meta_fetched ] = $this->enrich_metadata( $url, $title, $description );

		$tags      = $this->normalize_tags( $request->get_param( 'tags' ) );
		$is_public = self::optional_bool( $request, 'public' );

		if ( $existing !== null ) {
			return $this->update_existing( $existing, $request, $tags, $title, $description );
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => LinkPostType::POST_TYPE,
				'post_status'  => $is_public === true ? 'publish' : 'private',
				'post_title'   => $title !== '' ? $title : $url,
				'post_content' => $description,
				'post_author'  => $user_id,
			],
			true,
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, LinkMeta::META_URL, $url );
		update_post_meta( $post_id, LinkMeta::META_URL_CANONICAL, $canonical );
		update_post_meta( $post_id, LinkMeta::META_FAVORITE, LinkMeta::bool_to_meta( self::optional_bool( $request, 'favorite' ) ?? false ) );

		if ( $tags !== [] ) {
			wp_set_object_terms( $post_id, $tags, TagTaxonomy::TAXONOMY, false );
		}

		$response = rest_ensure_response( $this->prepare_response( get_post( $post_id ) ) );
		$response->set_status( 201 );
		if ( $meta_fetched !== null ) {
			$response->header( 'X-Apermo-Stash-Meta-Fetched', $meta_fetched ? '1' : '0' );
		}

		return $response;
	}

	/**
	 * Returns a single bookmark.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( $post === null || $post->post_type !== LinkPostType::POST_TYPE ) {
			return new WP_Error( 'apermo_stash_not_found', __( 'Bookmark not found.', 'apermo-stash' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->prepare_response( $post ) );
	}

	/**
	 * Updates an existing bookmark.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 *
	 * The branching mirrors the request shape — each optional field is its
	 * own conditional. Splitting these out into helpers would mean
	 * threading the post id through several short methods for no clarity
	 * gain.
	 *
	 * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
	 */
	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( $post === null || $post->post_type !== LinkPostType::POST_TYPE ) {
			return new WP_Error( 'apermo_stash_not_found', __( 'Bookmark not found.', 'apermo-stash' ), [ 'status' => 404 ] );
		}

		$update = [ 'ID' => $post_id ];

		if ( $request->has_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( $request->has_param( 'description' ) ) {
			$update['post_content'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
		}
		$is_public = self::optional_bool( $request, 'public' );
		if ( $is_public !== null ) {
			$update['post_status'] = $is_public ? 'publish' : 'private';
		}

		if ( \count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( $request->has_param( 'url' ) ) {
			$url       = esc_url_raw( (string) $request->get_param( 'url' ) );
			$canonical = Canonicalizer::canonicalize( $url );
			if ( $canonical === '' ) {
				return new WP_Error(
					'apermo_stash_invalid_url',
					__( 'The url is not valid.', 'apermo-stash' ),
					[ 'status' => 400 ],
				);
			}
			update_post_meta( $post_id, LinkMeta::META_URL, $url );
			update_post_meta( $post_id, LinkMeta::META_URL_CANONICAL, $canonical );
		}

		$favorite = self::optional_bool( $request, 'favorite' );
		if ( $favorite !== null ) {
			update_post_meta( $post_id, LinkMeta::META_FAVORITE, LinkMeta::bool_to_meta( $favorite ) );
		}

		if ( $request->has_param( 'tags' ) ) {
			$tags = $this->normalize_tags( $request->get_param( 'tags' ) );
			wp_set_object_terms( $post_id, $tags, TagTaxonomy::TAXONOMY, false );
		}

		return rest_ensure_response( $this->prepare_response( get_post( $post_id ) ) );
	}

	/**
	 * Deletes a bookmark.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( $post === null || $post->post_type !== LinkPostType::POST_TYPE ) {
			return new WP_Error( 'apermo_stash_not_found', __( 'Bookmark not found.', 'apermo-stash' ), [ 'status' => 404 ] );
		}

		$deleted = wp_delete_post( $post_id, true );
		if ( $deleted === false || $deleted === null ) {
			return new WP_Error( 'apermo_stash_delete_failed', __( 'Could not delete bookmark.', 'apermo-stash' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'deleted' => true,
				'id' => $post_id,
			],
		);
	}

	/**
	 * Updates an existing bookmark to match the create-item request body.
	 *
	 * Treats POST as idempotent: tags replace the existing set (rather than
	 * append), and any field present in the request — title, description,
	 * unread, archived, public — overwrites what is currently stored. Fields
	 * the caller did not send are left alone.
	 *
	 * @param WP_Post           $existing    Existing bookmark.
	 * @param WP_REST_Request   $request     REST request.
	 * @param array<int,string> $tags        Tags from the request (may be empty).
	 * @param string            $title       Resolved title (post-enrichment).
	 * @param string            $description Resolved description (post-enrichment).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function update_existing(
		WP_Post $existing,
		WP_REST_Request $request,
		array $tags,
		string $title,
		string $description
	): WP_REST_Response|WP_Error {
		if ( $request->has_param( 'tags' ) ) {
			wp_set_object_terms( $existing->ID, $tags, TagTaxonomy::TAXONOMY, false );
		}

		$update = [ 'ID' => $existing->ID ];

		if ( $request->has_param( 'title' ) && $title !== '' ) {
			$update['post_title'] = $title;
		}
		if ( $request->has_param( 'description' ) ) {
			$update['post_content'] = $description;
		}

		$is_public = self::optional_bool( $request, 'public' );
		if ( $is_public !== null ) {
			$update['post_status'] = $is_public ? 'publish' : 'private';
		}

		if ( \count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$favorite = self::optional_bool( $request, 'favorite' );
		if ( $favorite !== null ) {
			update_post_meta( $existing->ID, LinkMeta::META_FAVORITE, LinkMeta::bool_to_meta( $favorite ) );
		}

		// Re-fetch by ID so prepare_response sees the post_status that
		// wp_update_post just persisted, not the stale $existing snapshot.
		$fresh = get_post( $existing->ID );
		$response = rest_ensure_response( $this->prepare_response( $fresh ) );
		$response->set_status( 200 );
		$response->header( 'X-Apermo-Stash-Existing', '1' );

		return $response;
	}

	/**
	 * Locates a bookmark for the user by canonical URL.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $canonical Canonical URL.
	 *
	 * @return WP_Post|null
	 */
	/**
	 * Fills missing title/description from the metadata fetcher.
	 *
	 * Skips the network round-trip when the caller supplied both fields.
	 * Returns the resolved title, description, and a `reachable` flag that
	 * is null when the fetcher wasn't called and bool when it was.
	 *
	 * @param string $url         Bookmark URL.
	 * @param string $title       Caller-supplied title.
	 * @param string $description Caller-supplied description.
	 *
	 * @return array{0: string, 1: string, 2: ?bool}
	 */
	private function enrich_metadata( string $url, string $title, string $description ): array {
		if ( $title !== '' && $description !== '' ) {
			return [ $title, $description, null ];
		}

		$meta = $this->fetcher->fetch( $url );

		if ( $title === '' && $meta['title'] !== null ) {
			$title = $meta['title'];
		}
		if ( $description === '' && $meta['description'] !== null ) {
			$description = $meta['description'];
		}

		return [ $title, $description, $meta['reachable'] ];
	}

	/**
	 * Locates a bookmark for the user by canonical URL.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $canonical Canonical URL.
	 *
	 * @return WP_Post|null
	 */
	private function find_by_canonical( int $user_id, string $canonical ): ?WP_Post {
		$query = new WP_Query(
			[
				'post_type'      => LinkPostType::POST_TYPE,
				'author'         => $user_id,
				'posts_per_page' => 1,
				'post_status'    => [ 'publish', 'private' ],
				// Dedupe by canonical URL is the whole point of this query;
				// the meta key is short and a meta_query lookup is the
				// idiomatic way to do it.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => [
					[
						'key'   => LinkMeta::META_URL_CANONICAL,
						'value' => $canonical,
					],
				],
				'no_found_rows'  => true,
				'fields'         => 'all',
			],
		);

		return $query->posts[0] ?? null;
	}

	/**
	 * Normalises the incoming tags param to a list of strings.
	 *
	 * @param mixed $tags Raw tags value.
	 *
	 * @return list<string>
	 */
	private function normalize_tags( mixed $tags ): array {
		if ( ! \is_array( $tags ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $tags as $tag ) {
			if ( ! \is_string( $tag ) ) {
				continue;
			}
			$tag = \trim( $tag );
			if ( $tag !== '' ) {
				$normalized[] = $tag;
			}
		}

		return \array_values( \array_unique( $normalized ) );
	}

	/**
	 * Prepares a bookmark for the REST response shape.
	 *
	 * @param WP_Post|null $post WP post.
	 *
	 * @return array<string, mixed>
	 */
	private function prepare_response( ?WP_Post $post ): array {
		if ( $post === null ) {
			return [];
		}

		$tag_objects = wp_get_object_terms( $post->ID, TagTaxonomy::TAXONOMY );
		$tags        = [];
		if ( \is_array( $tag_objects ) ) {
			foreach ( $tag_objects as $term ) {
				$tags[] = $term->slug;
			}
		}

		return [
			'id'          => $post->ID,
			'url'         => (string) get_post_meta( $post->ID, LinkMeta::META_URL, true ),
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'tags'        => $tags,
			'favorite'    => (bool) get_post_meta( $post->ID, LinkMeta::META_FAVORITE, true ),
			'public'      => $post->post_status === 'publish',
			'created'     => mysql2date( 'c', $post->post_date_gmt, false ),
			'modified'    => mysql2date( 'c', $post->post_modified_gmt, false ),
		];
	}
}
