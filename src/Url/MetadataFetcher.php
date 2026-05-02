<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Url;

\defined( 'ABSPATH' ) || exit();

/**
 * Fetches the title and description metadata for a remote URL.
 */
class MetadataFetcher {

	private const TIMEOUT_SECONDS = 5;

	/**
	 * Returns the trimmed contents of the first <title> tag, or null.
	 *
	 * @param string $html HTML body.
	 *
	 * @return string|null
	 */
	private static function extract_title( string $html ): ?string {
		if ( \preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $match ) !== 1 ) {
			return null;
		}
		$title = \trim( wp_strip_all_tags( \html_entity_decode( $match[1], \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ) ) );

		return $title === '' ? null : $title;
	}

	/**
	 * Returns the page description from common meta tags, or null.
	 *
	 * Tries `<meta name="description">` first, then `<meta property="og:description">`.
	 *
	 * @param string $html HTML body.
	 *
	 * @return string|null
	 */
	private static function extract_description( string $html ): ?string {
		$value = self::extract_meta_content( $html, 'name', 'description' );
		if ( $value === null ) {
			$value = self::extract_meta_content( $html, 'property', 'og:description' );
		}

		return $value;
	}

	/**
	 * Extracts the `content` attribute of a `<meta>` tag matched by attribute name and value.
	 *
	 * @param string $html      HTML body.
	 * @param string $attribute Attribute name to match (e.g. "name" or "property").
	 * @param string $value     Attribute value to match (e.g. "description").
	 *
	 * @return string|null
	 */
	private static function extract_meta_content( string $html, string $attribute, string $value ): ?string {
		$pattern = \sprintf(
			'#<meta\b[^>]*\b%s\s*=\s*["\']%s["\'][^>]*\bcontent\s*=\s*["\']([^"\']*)["\'][^>]*/?>#i',
			\preg_quote( $attribute, '#' ),
			\preg_quote( $value, '#' ),
		);

		if ( \preg_match( $pattern, $html, $match ) !== 1 ) {
			$pattern_reversed = \sprintf(
				'#<meta\b[^>]*\bcontent\s*=\s*["\']([^"\']*)["\'][^>]*\b%s\s*=\s*["\']%s["\'][^>]*/?>#i',
				\preg_quote( $attribute, '#' ),
				\preg_quote( $value, '#' ),
			);
			if ( \preg_match( $pattern_reversed, $html, $match ) !== 1 ) {
				return null;
			}
		}

		$content = \trim( wp_strip_all_tags( \html_entity_decode( $match[1], \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ) ) );

		return $content === '' ? null : $content;
	}

	/**
	 * Returns the empty result shape.
	 *
	 * @return array{title: null, description: null}
	 */
	private static function empty_result(): array {
		return [
			'title'       => null,
			'description' => null,
		];
	}

	/**
	 * Returns the title and description for the given URL, or nulls on failure.
	 *
	 * @param string $url Remote URL to scrape.
	 *
	 * @return array{title: ?string, description: ?string}
	 */
	public function fetch( string $url ): array {
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'     => self::TIMEOUT_SECONDS,
				'redirection' => 3,
				'user-agent'  => 'LinkStash/0.1 (+https://github.com/apermo/linkstash)',
			],
		);

		if ( is_wp_error( $response ) ) {
			return self::empty_result();
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return self::empty_result();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( $body === '' ) {
			return self::empty_result();
		}

		return [
			'title'       => self::extract_title( $body ),
			'description' => self::extract_description( $body ),
		];
	}
}
