<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\PostType\TagTaxonomy;
use Apermo\LinkStash\Url\Canonicalizer;
use Apermo\LinkStash\Url\MetadataFetcher;

/**
 * Renders the paste-a-URL quick-add form on the bookmark list screen
 * and handles its submission.
 */
class QuickAdd {

	private const ACTION = 'linkstash_quick_add';

	/**
	 * Holds the metadata fetcher.
	 *
	 * @var MetadataFetcher
	 */
	private MetadataFetcher $fetcher;

	/**
	 * Constructs the screen.
	 *
	 * @param MetadataFetcher $fetcher Metadata fetcher.
	 */
	public function __construct( MetadataFetcher $fetcher ) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Splits the tags input into a list of trimmed values.
	 *
	 * @param string $raw Raw comma-separated input.
	 *
	 * @return list<string>
	 */
	private static function parse_tags( string $raw ): array {
		if ( $raw === '' ) {
			return [];
		}

		$parts  = \explode( ',', $raw );
		$result = [];
		foreach ( $parts as $part ) {
			$part = \trim( $part );
			if ( $part !== '' ) {
				$result[] = $part;
			}
		}

		return \array_values( \array_unique( $result ) );
	}

	/**
	 * Returns the URL of the bookmark list screen, optionally with a notice param.
	 *
	 * @param string $notice Notice slug.
	 *
	 * @return string
	 */
	private static function list_url( string $notice ): string {
		return add_query_arg(
			[
				'post_type'        => BookmarkPostType::POST_TYPE,
				'linkstash_notice' => $notice,
			],
			admin_url( 'edit.php' ),
		);
	}

	/**
	 * Renders the standalone quick-add form HTML.
	 *
	 * Shared by the bookmark list screen and the dashboard widget so the
	 * markup, nonce, and submit target stay in lockstep.
	 *
	 * @param string $css_class Extra CSS class to apply to the form element.
	 *
	 * @return void
	 */
	public static function render_form_html( string $css_class = 'linkstash-quick-add' ): void {
		$nonce = wp_create_nonce( self::ACTION );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( $css_class ); ?>" style="margin: 0.5rem 0;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
			<p>
				<input type="url" name="url" placeholder="<?php esc_attr_e( 'https://…', 'linkstash' ); ?>" required class="widefat" data-linkstash-url-input />
			</p>
			<p>
				<input type="text" name="tags" placeholder="<?php esc_attr_e( 'tags, comma, separated', 'linkstash' ); ?>" class="widefat" data-linkstash-tag-autocomplete="<?php echo esc_attr( TagTaxonomy::TAXONOMY ); ?>" autocomplete="off" />
			</p>
			<p>
				<label>
					<input type="checkbox" name="public" value="1" />
					<?php esc_html_e( 'Public', 'linkstash' ); ?>
				</label>
				<button type="submit" class="button button-primary alignright"><?php esc_html_e( 'Save bookmark', 'linkstash' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Hooks the rendering and submission handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'all_admin_notices', [ $this, 'maybe_render_form' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_submission' ] );
	}

	/**
	 * Renders the quick-add form on the bookmark list screen.
	 *
	 * Hooked to `all_admin_notices` rather than `restrict_manage_posts` so
	 * that the standalone `<form>` does not nest inside the list table's
	 * own `#posts-filter` form (which `restrict_manage_posts` fires inside
	 * of). The notices area sits above the list table in `<div class="wrap">`
	 * but outside any other form.
	 *
	 * @return void
	 */
	public function maybe_render_form(): void {
		$screen = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen === null
			|| $screen->base !== 'edit'
			|| $screen->post_type !== BookmarkPostType::POST_TYPE
		) {
			return;
		}

		self::render_form_html( 'linkstash-quick-add' );
	}

	/**
	 * Handles the quick-add form submission.
	 *
	 * @return void
	 */
	public function handle_submission(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to add bookmarks.', 'linkstash' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::ACTION );

		$url       = isset( $_POST['url'] ) && \is_string( $_POST['url'] )
			? esc_url_raw( wp_unslash( $_POST['url'] ) )
			: '';
		$canonical = Canonicalizer::canonicalize( $url );

		if ( $url === '' || $canonical === '' ) {
			wp_safe_redirect( self::list_url( 'invalid' ) );
			exit();
		}

		$tags_raw = isset( $_POST['tags'] ) && \is_string( $_POST['tags'] )
			? sanitize_text_field( wp_unslash( $_POST['tags'] ) )
			: '';
		$tags     = self::parse_tags( $tags_raw );

		$is_public = isset( $_POST['public'] );

		$meta = $this->fetcher->fetch( $url );

		$post_id = wp_insert_post(
			[
				'post_type'    => BookmarkPostType::POST_TYPE,
				'post_status'  => $is_public ? 'publish' : 'private',
				'post_title'   => $meta['title'] ?? $url,
				'post_content' => $meta['description'] ?? '',
				'post_author'  => get_current_user_id(),
			],
			true,
		);

		if ( is_wp_error( $post_id ) ) {
			wp_safe_redirect( self::list_url( 'failed' ) );
			exit();
		}

		update_post_meta( $post_id, BookmarkMeta::META_URL, $url );
		update_post_meta( $post_id, BookmarkMeta::META_URL_CANONICAL, $canonical );
		update_post_meta( $post_id, BookmarkMeta::META_UNREAD, false );
		update_post_meta( $post_id, BookmarkMeta::META_ARCHIVED, false );
		update_post_meta( $post_id, BookmarkMeta::META_UNREACHABLE, ! $meta['reachable'] );

		if ( $tags !== [] ) {
			wp_set_object_terms( $post_id, $tags, TagTaxonomy::TAXONOMY, false );
		}

		wp_safe_redirect( self::list_url( $meta['reachable'] ? 'saved' : 'saved-unreachable' ) );
		exit();
	}
}
