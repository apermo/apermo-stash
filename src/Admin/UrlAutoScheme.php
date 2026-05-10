<?php

declare(strict_types=1);

namespace Apermo\Stash\Admin;

use Apermo\Stash\PostType\BookmarkPostType;

\defined( 'ABSPATH' ) || exit();

/**
 * Prepends `https://` to bare URL inputs when the user tabs away.
 *
 * Saves the user from typing the scheme on every quick-add. Targets any
 * `<input type="url">` that opts in via `data-apermo-stash-url-input`. The
 * blur handler is conservative: it leaves anything that already looks
 * scheme-prefixed alone (`http://`, `https://`, `mailto:`, `//host/...`,
 * etc.).
 */
class UrlAutoScheme {

	/**
	 * Returns true on screens that render a Apermo Stash URL input.
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

		// Bookmark add/edit screens carry the URL meta box.
		if ( ! \in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
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
		return "( function () {\n"
			. "\tfunction attach( input ) {\n"
			. "\t\tinput.addEventListener( 'blur', function () {\n"
			. "\t\t\tvar value = input.value.trim();\n"
			. "\t\t\tif ( value === '' ) { return; }\n"
			. "\t\t\t// Already scheme-prefixed or protocol-relative — leave alone.\n"
			. "\t\t\tif ( /^[a-z][a-z0-9+.\\-]*:|^\\/\\//i.test( value ) ) { return; }\n"
			. "\t\t\tinput.value = 'https://' + value;\n"
			. "\t\t} );\n"
			. "\t}\n"
			. "\tfunction init() {\n"
			. "\t\tdocument.querySelectorAll( 'input[data-apermo-stash-url-input]' ).forEach( attach );\n"
			. "\t}\n"
			. "\tif ( document.readyState === 'loading' ) {\n"
			. "\t\tdocument.addEventListener( 'DOMContentLoaded', init );\n"
			. "\t} else {\n"
			. "\t\tinit();\n"
			. "\t}\n"
			. "} )();\n";
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
	 * Apermo Stash URL input.
	 *
	 * @param string $hook Current admin screen hook suffix.
	 *
	 * @return void
	 */
	public function maybe_enqueue( string $hook ): void {
		if ( ! self::is_target_screen( $hook ) ) {
			return;
		}

		wp_register_script( 'apermo-stash-url-auto-scheme', false, [], '0.1.0', true );
		wp_enqueue_script( 'apermo-stash-url-auto-scheme' );
		wp_add_inline_script( 'apermo-stash-url-auto-scheme', self::handler_js() );
	}
}
