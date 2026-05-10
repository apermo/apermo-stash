<?php

declare(strict_types=1);

namespace Apermo\Stash\Admin;

use Apermo\Stash\PostType\BookmarkPostType;

\defined( 'ABSPATH' ) || exit();

/**
 * Renders admin notices on the bookmark list screen in response to the
 * `apermo_stash_notice` query arg the QuickAdd handler appends after a save.
 *
 * Recognised notice slugs:
 *
 * - `saved` — bookmark created and metadata fetched.
 * - `saved-unreachable` — bookmark created, but the URL didn't respond (DNS
 *   failure, timeout, or non-200). The bookmark is intentionally still
 *   saved so private / VPN-only / OAuth-gated links work.
 * - `invalid` — input URL was empty or unparseable.
 * - `failed` — `wp_insert_post` failed.
 */
class Notices {

	/**
	 * Maps a notice slug to a (level, copy) tuple.
	 *
	 * @param string $slug Notice slug.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function message_for( string $slug ): array {
		switch ( $slug ) {
			case 'saved':
				return [ 'success', __( 'Bookmark saved.', 'apermo-stash' ) ];
			case 'saved-unreachable':
				return [
					'warning',
					__(
						'Bookmark saved, but the URL didn\'t respond — it may be private, behind a VPN or login wall, or temporarily down. Title and notes were left blank for you to fill in.',
						'apermo-stash',
					),
				];
			case 'invalid':
				return [ 'error', __( 'That URL was empty or unparseable.', 'apermo-stash' ) ];
			case 'failed':
				return [ 'error', __( 'Could not save the bookmark.', 'apermo-stash' ) ];
		}

		return [ '', '' ];
	}

	/**
	 * Hooks the notice renderer.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
	}

	/**
	 * Renders the notice when the current screen is the bookmark list and a
	 * recognised `apermo_stash_notice` slug is present.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		$screen = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen === null
			|| $screen->base !== 'edit'
			|| $screen->post_type !== BookmarkPostType::POST_TYPE
		) {
			return;
		}

		// $_GET['apermo_stash_notice'] is a flash flag set by our own
		// admin-post redirect; nothing security-relevant goes through it.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['apermo_stash_notice'] ) && \is_string( $_GET['apermo_stash_notice'] )
			? sanitize_key( wp_unslash( $_GET['apermo_stash_notice'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $notice === '' ) {
			return;
		}

		[ $type, $message ] = self::message_for( $notice );
		if ( $message === '' ) {
			return;
		}

		\printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message ),
		);
	}
}
