<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Url;

/**
 * Canonicalizes URLs for deduplication and lookup.
 */
class Canonicalizer {

	private const TRACKING_PARAM_PREFIXES = [ 'utm_' ];
	private const TRACKING_PARAM_EXACT    = [ 'fbclid', 'gclid', 'mc_cid', 'mc_eid' ];

	/**
	 * Returns a canonical form of the given URL.
	 *
	 * Lowercases scheme and host, strips default ports, drops the URL
	 * fragment and known tracking query parameters, sorts the remaining
	 * query parameters by key, and trims a trailing slash from the root path.
	 *
	 * Returns an empty string when the URL cannot be parsed.
	 *
	 * @param string $url Input URL.
	 *
	 * @return string
	 */
	public static function canonicalize( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! \is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return '';
		}

		$scheme = \strtolower( $parts['scheme'] );
		$host   = \strtolower( $parts['host'] );
		$path   = $parts['path'] ?? '';
		$port   = $parts['port'] ?? 0;

		if ( $path === '/' ) {
			$path = '';
		}

		$query = '';
		if ( isset( $parts['query'] ) && $parts['query'] !== '' ) {
			$query = self::filter_query( $parts['query'] );
		}

		$canonical = $scheme . '://' . $host;
		if ( $port !== 0 && ! self::is_default_port( $scheme, $port ) ) {
			$canonical .= ':' . $port;
		}
		$canonical .= $path;
		if ( $query !== '' ) {
			$canonical .= '?' . $query;
		}

		return $canonical;
	}

	/**
	 * Filters and sorts query string parameters.
	 *
	 * @param string $query Raw query string.
	 *
	 * @return string
	 */
	private static function filter_query( string $query ): string {
		$pairs = [];
		\parse_str( $query, $pairs );

		$kept = [];
		foreach ( $pairs as $key => $value ) {
			$key_str = (string) $key;
			if ( self::is_tracking_param( $key_str ) ) {
				continue;
			}
			$kept[ $key_str ] = $value;
		}

		if ( \count( $kept ) === 0 ) {
			return '';
		}

		\ksort( $kept );

		return \http_build_query( $kept );
	}

	/**
	 * Returns true when the given key is a known tracking parameter.
	 *
	 * @param string $key Query parameter name.
	 *
	 * @return bool
	 */
	private static function is_tracking_param( string $key ): bool {
		if ( \in_array( $key, self::TRACKING_PARAM_EXACT, true ) ) {
			return true;
		}
		foreach ( self::TRACKING_PARAM_PREFIXES as $prefix ) {
			if ( \str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns true when the port is the default for the scheme.
	 *
	 * @param string $scheme URL scheme.
	 * @param int    $port   Port number.
	 *
	 * @return bool
	 */
	private static function is_default_port( string $scheme, int $port ): bool {
		return ( $scheme === 'http' && $port === 80 )
			|| ( $scheme === 'https' && $port === 443 );
	}
}
