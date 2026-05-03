<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\PostType\BookmarkMeta;
use Apermo\LinkStash\PostType\BookmarkPostType;
use Apermo\LinkStash\Url\Canonicalizer;
use Apermo\LinkStash\Url\DisplayUrl;
use Apermo\LinkStash\Url\MetadataFetcher;
use WP_Post;

/**
 * Replaces the default bookmark edit screen with a small classic-editor form.
 *
 * Registers two meta boxes — a URL panel (URL + Favorite flag)
 * and a Notes panel (plain textarea bound to post_content) — and disables
 * the block editor for the bookmark CPT so the classic edit screen is
 * used instead. Saving falls back to a simplified URL as the post_title
 * when the user does not supply an explicit label.
 */
class BookmarkMetabox {

	private const NONCE_FIELD  = 'linkstash_metabox_nonce';
	private const NONCE_ACTION = 'linkstash_save_metabox';

	/**
	 * URL metadata fetcher (used on save to mark unreachable URLs).
	 *
	 * @var MetadataFetcher
	 */
	private MetadataFetcher $fetcher;

	/**
	 * Constructs the metabox.
	 *
	 * @param MetadataFetcher $fetcher URL metadata fetcher.
	 */
	public function __construct( MetadataFetcher $fetcher ) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Returns true when this save_post invocation should persist meta-box state.
	 *
	 * Bails on autosaves, revisions, missing nonces, and capability misses.
	 *
	 * @param int $post_id Post ID being saved.
	 *
	 * @return bool
	 */
	private static function should_persist( int $post_id ): bool {
		if ( \defined( 'DOING_AUTOSAVE' ) && \DOING_AUTOSAVE ) {
			return false;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! \is_string( $_POST[ self::NONCE_FIELD ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Reads a text field from the POST request, sanitised and unslashed.
	 *
	 * @param string $key Field name.
	 *
	 * @return string
	 */
	private static function read_text( string $key ): string {
		// Nonce verification happens in self::should_persist before save_post
		// reaches this helper.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) || ! \is_string( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Returns the dirty-tracking + beforeunload script body.
	 *
	 * @return string
	 */
	private static function unsaved_changes_script(): string {
		return "( function () {\n"
			. "\tfunction init() {\n"
			. "\t\tvar form = document.getElementById( 'post' );\n"
			. "\t\tif ( ! form ) { return; }\n"
			. "\t\tvar dirty = false;\n"
			. "\t\tform.addEventListener( 'input', function () { dirty = true; } );\n"
			. "\t\tform.addEventListener( 'change', function () { dirty = true; } );\n"
			. "\t\tform.addEventListener( 'submit', function () { dirty = false; } );\n"
			. "\t\twindow.addEventListener( 'beforeunload', function ( event ) {\n"
			. "\t\t\tif ( ! dirty ) { return; }\n"
			. "\t\t\tevent.preventDefault();\n"
			. "\t\t\tevent.returnValue = '';\n"
			. "\t\t} );\n"
			. "\t}\n"
			. "\tif ( document.readyState === 'loading' ) {\n"
			. "\t\tdocument.addEventListener( 'DOMContentLoaded', init );\n"
			. "\t} else {\n"
			. "\t\tinit();\n"
			. "\t}\n"
			. "} )();\n";
	}

	/**
	 * Updates the given post fields without re-triggering save_post handlers.
	 *
	 * Detaches our own save_post hook for the duration of the update so the
	 * fallback-title write does not recurse into save_post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields  Fields to update.
	 *
	 * @return void
	 */
	private function update_post_fields( int $post_id, array $fields ): void {
		$hook_name = 'save_post_' . BookmarkPostType::POST_TYPE;

		remove_action( $hook_name, [ $this, 'save_post' ], 10 );
		wp_update_post( \array_merge( [ 'ID' => $post_id ], $fields ) );
		add_action( $hook_name, [ $this, 'save_post' ], 10, 2 );
	}

	/**
	 * Hooks the meta-box registration, save handler, and editor filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . BookmarkPostType::POST_TYPE, [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post_' . BookmarkPostType::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
		add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_block_editor' ], 10, 2 );
		add_action( 'admin_print_footer_scripts', [ $this, 'maybe_print_unsaved_changes_script' ] );
	}

	/**
	 * Prints the inline beforeunload guard on the bookmark add/edit screen.
	 *
	 * Wires a small DOM-level dirty-tracking script to `#post` (the
	 * standard classic-editor `<form>` id WordPress emits on
	 * post.php / post-new.php). Any `input` or `change` event inside
	 * the form flips the dirty flag; the form's own `submit` clears
	 * it so the legitimate save round-trip doesn't prompt. When dirty,
	 * `beforeunload` returns a non-empty value so the browser renders
	 * its native "Leave site?" dialog.
	 *
	 * @return void
	 */
	public function maybe_print_unsaved_changes_script(): void {
		$screen = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen === null
			|| $screen->base !== 'post'
			|| $screen->post_type !== BookmarkPostType::POST_TYPE
		) {
			return;
		}

		// Constant string, no user data.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "<script>\n" . self::unsaved_changes_script() . "</script>\n";
	}

	/**
	 * Returns false for the bookmark CPT so the classic editor is used.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        Post-type slug.
	 *
	 * @return bool
	 */
	public function disable_block_editor( bool $use_block_editor, string $post_type ): bool {
		if ( $post_type === BookmarkPostType::POST_TYPE ) {
			return false;
		}

		return $use_block_editor;
	}

	/**
	 * Registers the URL and Notes meta boxes for the bookmark CPT.
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		// Both meta boxes live in the main (normal) column. The URL panel
		// uses 'high' priority so it renders directly under the title and
		// above the Notes panel.
		add_meta_box(
			'linkstash_bookmark_url',
			__( 'Bookmark URL', 'linkstash' ),
			[ $this, 'render_url_meta_box' ],
			BookmarkPostType::POST_TYPE,
			'normal',
			'high',
		);

		add_meta_box(
			'linkstash_bookmark_note',
			__( 'Notes', 'linkstash' ),
			[ $this, 'render_note_meta_box' ],
			BookmarkPostType::POST_TYPE,
			'normal',
			'default',
		);
	}

	/**
	 * Renders the URL meta box with URL and the Favorite flag input.
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return void
	 */
	public function render_url_meta_box( WP_Post $post ): void {
		$url         = (string) get_post_meta( $post->ID, BookmarkMeta::META_URL, true );
		$favorite    = (bool) get_post_meta( $post->ID, BookmarkMeta::META_FAVORITE, true );
		$unreachable = (bool) get_post_meta( $post->ID, BookmarkMeta::META_UNREACHABLE, true );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p>
			<label for="linkstash-url"><strong><?php esc_html_e( 'URL', 'linkstash' ); ?></strong></label><br />
			<input type="url"
					id="linkstash-url"
					name="linkstash_url"
					value="<?php echo esc_attr( $url ); ?>"
					required
					class="widefat"
					placeholder="https://&hellip;"
					data-linkstash-url-input />
		</p>
		<?php if ( $unreachable && $url !== '' ) { ?>
			<div class="notice notice-warning inline" style="margin: 0.5rem 0; padding: 0.5rem 0.75rem;">
				<p style="margin: 0;">
					<strong><?php esc_html_e( 'URL didn\'t respond on last save.', 'linkstash' ); ?></strong>
					<br />
					<?php esc_html_e( 'It may be private, behind a VPN or login wall, or temporarily down. The bookmark is saved either way; re-saving will re-check.', 'linkstash' ); ?>
				</p>
			</div>
		<?php } ?>
		<p>
			<label>
				<input type="checkbox" name="linkstash_favorite" value="1" <?php checked( $favorite ); ?> />
				<?php esc_html_e( 'Favorite', 'linkstash' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Leave the title field empty to use the URL as the title.', 'linkstash' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the Notes meta box (plain textarea bound to post_content).
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return void
	 */
	public function render_note_meta_box( WP_Post $post ): void {
		?>
		<textarea name="linkstash_note"
					id="linkstash-note"
					class="widefat"
					rows="8"
					placeholder="<?php esc_attr_e( 'Optional note&hellip;', 'linkstash' ); ?>"><?php echo esc_textarea( $post->post_content ); ?></textarea>
		<?php
	}

	/**
	 * Persists the meta-box fields when a bookmark is saved.
	 *
	 * @param int     $post_id Bookmark post ID.
	 * @param WP_Post $post    The saved post.
	 *
	 * @return void
	 */
	public function save_post( int $post_id, WP_Post $post ): void {
		// Nonce verification happens in self::should_persist below; suppress
		// PHPCS's per-line warnings about $_POST access since the gate at
		// the top of this method already covers them.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! self::should_persist( $post_id ) ) {
			return;
		}

		$url       = self::read_text( 'linkstash_url' );
		$canonical = Canonicalizer::canonicalize( $url );
		if ( $url !== '' && $canonical !== '' ) {
			update_post_meta( $post_id, BookmarkMeta::META_URL, esc_url_raw( $url ) );
			update_post_meta( $post_id, BookmarkMeta::META_URL_CANONICAL, $canonical );

			// Re-check reachability on every save, even when the URL
			// itself didn't change — a previously-down host coming back
			// up should clear the warning automatically the next time
			// the user touches the bookmark.
			$result = $this->fetcher->fetch( $url );
			update_post_meta( $post_id, BookmarkMeta::META_UNREACHABLE, BookmarkMeta::bool_to_meta( ! $result['reachable'] ) );
		}

		update_post_meta(
			$post_id,
			BookmarkMeta::META_FAVORITE,
			BookmarkMeta::bool_to_meta( isset( $_POST['linkstash_favorite'] ) ),
		);

		$note_raw = isset( $_POST['linkstash_note'] ) && \is_string( $_POST['linkstash_note'] )
			? wp_kses_post( wp_unslash( $_POST['linkstash_note'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$update = [];
		if ( $note_raw !== $post->post_content ) {
			$update['post_content'] = $note_raw;
		}

		if ( \trim( $post->post_title ) === '' ) {
			$resolved_url = $url !== '' ? $url : (string) get_post_meta( $post_id, BookmarkMeta::META_URL, true );
			$display      = DisplayUrl::simplify( $resolved_url );
			if ( $display !== '' ) {
				$update['post_title'] = $display;
			}
		}

		if ( $update !== [] ) {
			$this->update_post_fields( $post_id, $update );
		}
	}
}
