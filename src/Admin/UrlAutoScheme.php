<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Admin;

use Apermo\LinkStash\PostType\BookmarkPostType;

\defined( 'ABSPATH' ) || exit();

/**
 * Prepends `https://` to bare URL inputs when the user tabs away.
 *
 * Saves the user from typing the scheme on every quick-add. Targets any
 * `<input type="url">` that opts in via `data-linkstash-url-input`. The
 * blur handler is conservative: it leaves anything that already looks
 * scheme-prefixed alone (`http://`, `https://`, `mailto:`, `//host/...`,
 * etc.).
 */
class UrlAutoScheme {

	/**
	 * Returns true on screens that render a LinkStash URL input.
	 *
	 * @param string $hook Hook suffix passed to admin_enqueue_scripts.
	 *
	 * @return bool
	 */
	private static function is_target_screen( string $hook ): bool {
		// Dashboard always runs the quick-add widget.
		if ( $hook === 'index.php' ) {
			return true;
		}

		if ( ! \in_array( $hook, [ 'edit.php', 'post.php', 'post-new.php' ], true ) ) {
			return false;
		}

		$screen = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen !== null && $screen->post_type === BookmarkPostType::POST_TYPE;
	}

	/**
	 * Returns the inline blur handler.
	 *
	 * @return string
	 */
	private static function handler_js(): string {
		return <<<'JS'
( function () {
	function attach( input ) {
		input.addEventListener( 'blur', function () {
			var value = input.value.trim();
			if ( value === '' ) { return; }
			// Already scheme-prefixed (http://, mailto:, chrome-extension://, …) or protocol-relative — leave alone.
			if ( /^[a-z][a-z0-9+.\-]*:|^\/\//i.test( value ) ) { return; }
			input.value = 'https://' + value;
		} );
	}
	function init() {
		document.querySelectorAll( 'input[data-linkstash-url-input]' ).forEach( attach );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
JS;
	}

	/**
	 * Hooks the script enqueue.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
	}

	/**
	 * Enqueues the blur-handler script on every screen that renders a
	 * LinkStash URL input.
	 *
	 * @param string $hook Current admin screen hook suffix.
	 *
	 * @return void
	 */
	public function maybe_enqueue( string $hook ): void {
		if ( ! self::is_target_screen( $hook ) ) {
			return;
		}

		wp_register_script( 'linkstash-url-auto-scheme', false, [], '0.1.0', true );
		wp_enqueue_script( 'linkstash-url-auto-scheme' );
		wp_add_inline_script( 'linkstash-url-auto-scheme', self::handler_js() );
	}
}
