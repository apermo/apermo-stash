<?php

declare(strict_types=1);

namespace Apermo\Stash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\Stash\PostType\LinkMeta;
use Apermo\Stash\PostType\LinkPostType;
use Apermo\Stash\PostType\TagTaxonomy;
use Apermo\Stash\Url\Canonicalizer;
use Apermo\Stash\Url\MetadataFetcher;

/**
 * Renders the paste-a-URL quick-add form on the link list screen
 * and handles its submission.
 */
class QuickAdd {

	private const ACTION = 'apermo_stash_quick_add';

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
	 * Returns the URL of the link list screen, optionally with a notice param.
	 *
	 * @param string $notice Notice slug.
	 *
	 * @return string
	 */
	private static function list_url( string $notice ): string {
		return add_query_arg(
			[
				'post_type'           => LinkPostType::POST_TYPE,
				'apermo_stash_notice' => $notice,
			],
			admin_url( 'edit.php' ),
		);
	}

	/**
	 * Renders the standalone quick-add form HTML.
	 *
	 * Shared by the link list screen and the dashboard widget so the
	 * markup, nonce, and submit target stay in lockstep.
	 *
	 * @param string $css_class Extra CSS class to apply to the form element.
	 *
	 * @return void
	 */
	public static function render_form_html( string $css_class = 'apermo-stash-quick-add' ): void {
		$nonce = wp_create_nonce( self::ACTION );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( $css_class ); ?>" style="margin: 0.5rem 0;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
			<p>
				<input type="url" name="url" placeholder="<?php esc_attr_e( 'https://…', 'apermo-stash' ); ?>" required class="widefat" data-apermo-stash-url-input />
			</p>
			<p>
				<input type="text" name="tags" placeholder="<?php esc_attr_e( 'tags, comma, separated', 'apermo-stash' ); ?>" class="widefat" data-apermo-stash-tag-autocomplete="<?php echo esc_attr( TagTaxonomy::TAXONOMY ); ?>" autocomplete="off" />
			</p>
			<p>
				<label>
					<input type="checkbox" name="public" value="1" />
					<?php esc_html_e( 'Public', 'apermo-stash' ); ?>
				</label>
				<button type="submit" class="button button-primary alignright"><?php esc_html_e( 'Save link', 'apermo-stash' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Hooks the submission handler.
	 *
	 * The list-screen quick-add form is no longer rendered — capture
	 * happens through the dashboard widget (which calls
	 * `render_form_html()` directly). This class still handles the
	 * `admin-post.php` POST that the dashboard form (and any future
	 * caller of `render_form_html`) submits.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_submission' ] );
	}

	/**
	 * Handles the quick-add form submission.
	 *
	 * @return void
	 */
	public function handle_submission(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to add links.', 'apermo-stash' ), '', [ 'response' => 403 ] );
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
				'post_type'    => LinkPostType::POST_TYPE,
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

		update_post_meta( $post_id, LinkMeta::META_URL, $url );
		update_post_meta( $post_id, LinkMeta::META_URL_CANONICAL, $canonical );
		update_post_meta( $post_id, LinkMeta::META_FAVORITE, LinkMeta::bool_to_meta( false ) );
		update_post_meta( $post_id, LinkMeta::META_UNREACHABLE, LinkMeta::bool_to_meta( ! $meta['reachable'] ) );

		if ( $tags !== [] ) {
			wp_set_object_terms( $post_id, $tags, TagTaxonomy::TAXONOMY, false );
		}

		wp_safe_redirect( self::list_url( $meta['reachable'] ? 'saved' : 'saved-unreachable' ) );
		exit();
	}
}
