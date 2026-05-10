<?php

declare(strict_types=1);

namespace Apermo\Stash\Url;

\defined( 'ABSPATH' ) || exit();

/**
 * Renders a URL as a short, human-readable string for display fallbacks.
 *
 * Used as the link title fallback when the user does not provide an
 * explicit label. Distinct from {@see Canonicalizer}: canonicalization
 * preserves enough of the URL to compare two links for dedupe identity
 * (sorted query, scheme, port); display drops everything that does not
 * help a human recognise the page.
 */
class DisplayUrl {

	/**
	 * Returns a short, human-readable form of the given URL.
	 *
	 * Strips scheme, leading `www.`, query string, and fragment. Lowercases
	 * the host and trims a single trailing slash from the path. Returns
	 * the original input unchanged when the URL cannot be parsed.
	 *
	 * @param string $url Input URL.
	 *
	 * @return string
	 */
	public static function simplify( string $url ): string {
		if ( $url === '' ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! \is_array( $parts ) || ! isset( $parts['host'] ) ) {
			return $url;
		}

		$host = \strtolower( $parts['host'] );
		if ( \str_starts_with( $host, 'www.' ) ) {
			$host = \substr( $host, 4 );
		}

		$path = $parts['path'] ?? '';
		if ( $path === '/' ) {
			$path = '';
		} elseif ( \str_ends_with( $path, '/' ) ) {
			$path = \rtrim( $path, '/' );
		}

		return $host . $path;
	}
}
