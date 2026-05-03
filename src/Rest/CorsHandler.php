<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Rest;

\defined( 'ABSPATH' ) || exit();

/**
 * Sends CORS headers for the LinkStash REST namespace and short-circuits
 * `OPTIONS` preflight requests.
 *
 * Browser extensions (Chrome MV3) send `Origin: chrome-extension://<id>` and
 * issue a preflight `OPTIONS` request before any non-simple call. WordPress
 * core only echoes `Access-Control-Allow-Origin: *` for cookie-authenticated
 * requests by default, which doesn't help an extension that wants to send
 * `Authorization: Bearer ...`. This handler responds for the LinkStash
 * routes only.
 */
class CorsHandler {

	private const ALLOWED_HEADERS = 'Authorization, Content-Type, X-WP-Nonce, X-Requested-With';
	private const ALLOWED_METHODS = 'GET, POST, PATCH, PUT, DELETE, OPTIONS';
	private const EXPOSED_HEADERS = 'X-LinkStash-Existing, X-LinkStash-Meta-Fetched, X-WP-Total, X-WP-TotalPages, Link';

	/**
	 * Returns the origin from the current request, or an empty string.
	 *
	 * @return string
	 */
	private static function request_origin(): string {
		if ( ! isset( $_SERVER['HTTP_ORIGIN'] ) || ! \is_string( $_SERVER['HTTP_ORIGIN'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
	}

	/**
	 * Returns true when the origin matches the allow-list.
	 *
	 * Extensions can extend the list via the `linkstash_allowed_origins` filter.
	 * The default list contains the literal `chrome-extension://*` wildcard.
	 *
	 * @param string $origin Origin header value.
	 *
	 * @return bool
	 */
	private static function is_allowed_origin( string $origin ): bool {
		/**
		 * Filters the list of allowed CORS origins for the LinkStash REST namespace.
		 *
		 * @param list<string> $origins Origins; entries may end in `*` to match any suffix.
		 *
		 * @return mixed Filter consumers may return anything; non-array values fall back to deny.
		 */
		$allowed = apply_filters( 'linkstash_allowed_origins', [ 'chrome-extension://*' ] );
		// @phpstan-ignore function.alreadyNarrowedType
		if ( ! \is_array( $allowed ) ) {
			return false;
		}

		foreach ( $allowed as $candidate ) {
			// @phpstan-ignore function.alreadyNarrowedType
			if ( ! \is_string( $candidate ) ) {
				continue;
			}
			if ( self::origin_matches( $origin, $candidate ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns true when the origin matches a single allow-list entry.
	 *
	 * A trailing `*` matches any suffix, but only at a scheme/path/port
	 * boundary so that `https://example.tld*` does not match
	 * `https://example.tld.attacker.tld`. Boundary means the prefix already
	 * ends in `/` or `:`, or the next character of the origin is `/`, `:`,
	 * `?`, or `#`, or the origin equals the prefix exactly.
	 *
	 * @param string $origin    Origin header value.
	 * @param string $candidate Allow-list entry.
	 *
	 * @return bool
	 */
	private static function origin_matches( string $origin, string $candidate ): bool {
		if ( $candidate === $origin ) {
			return true;
		}

		if ( ! \str_ends_with( $candidate, '*' ) ) {
			return false;
		}

		$prefix = \substr( $candidate, 0, -1 );
		if ( ! \str_starts_with( $origin, $prefix ) ) {
			return false;
		}

		if ( \str_ends_with( $prefix, '/' ) || \str_ends_with( $prefix, ':' ) ) {
			return true;
		}

		$rest = \substr( $origin, \strlen( $prefix ) );
		if ( $rest === '' ) {
			return true;
		}

		return \in_array( $rest[0], [ '/', ':', '?', '#' ], true );
	}

	/**
	 * Returns true for OPTIONS requests.
	 *
	 * @return bool
	 */
	private static function is_options_request(): bool {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || ! \is_string( $_SERVER['REQUEST_METHOD'] ) ) {
			return false;
		}

		return \strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) === 'OPTIONS';
	}

	/**
	 * Returns true when the current REST route is under the LinkStash namespace.
	 *
	 * @return bool
	 */
	private static function is_linkstash_route(): bool {
		$route = self::current_route();

		return $route !== '' && \str_starts_with( $route, '/' . RestController::NAMESPACE );
	}

	/**
	 * Reads the current REST route from the request URI.
	 *
	 * Supports both pretty permalinks (`/wp-json/<namespace>/...`) and the
	 * `?rest_route=/<namespace>/...` fallback used when permalinks are
	 * disabled.
	 *
	 * @return string
	 */
	private static function current_route(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only inspection of the incoming request shape.
		if ( isset( $_GET['rest_route'] ) && \is_string( $_GET['rest_route'] ) ) {
			return sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! \is_string( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$uri = \strtok( $uri, '?' );
		if ( $uri === false ) {
			return '';
		}

		$rest_prefix = '/' . rest_get_url_prefix() . '/';
		$position    = \strpos( $uri, $rest_prefix );
		if ( $position === false ) {
			return '';
		}

		return \substr( $uri, $position + \strlen( $rest_prefix ) - 1 );
	}

	/**
	 * Hooks the REST init events.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priority 100 puts us after WP core's rest_send_cors_headers
		// (priority 10) AND after WP_REST_Server::send_header() emissions
		// for Access-Control-Allow-Headers / -Expose-Headers (which fire
		// inside serve_request before this filter runs). We need to be
		// the last writer or core's empty Allow-Origin (sanitize_url
		// strips chrome-extension://, which is not in wp_allowed_protocols)
		// is what the browser sees.
		add_filter( 'rest_pre_serve_request', [ $this, 'send_cors_response' ], 100, 4 );
	}

	/**
	 * Emits the LinkStash CORS response headers and, for OPTIONS preflight,
	 * short-circuits the request with a 204.
	 *
	 * Runs late on `rest_pre_serve_request` (priority 100) to be the last
	 * writer for the CORS header set. This means our values overwrite WP
	 * core's `rest_send_cors_headers` (which would otherwise emit an empty
	 * `Access-Control-Allow-Origin` for `chrome-extension://...` because
	 * `sanitize_url()` strips schemes outside `wp_allowed_protocols()`)
	 * and `WP_REST_Server::send_header()` defaults for Allow-Headers and
	 * Expose-Headers.
	 *
	 * @param bool  $served  Whether the request has already been served.
	 * @param mixed $result  The REST response (unused).
	 * @param mixed $request The REST request (unused).
	 * @param mixed $server  The REST server (unused).
	 *
	 * @return bool
	 */
	public function send_cors_response( bool $served, mixed $result, mixed $request, mixed $server ): bool {
		unset( $result, $request, $server );

		if ( ! self::is_linkstash_route() ) {
			return $served;
		}

		$origin = self::request_origin();
		if ( $origin === '' || ! self::is_allowed_origin( $origin ) ) {
			return $served;
		}

		\header( 'Access-Control-Allow-Origin: ' . $origin );
		\header( 'Access-Control-Allow-Methods: ' . self::ALLOWED_METHODS );
		\header( 'Access-Control-Allow-Headers: ' . self::ALLOWED_HEADERS );
		\header( 'Access-Control-Allow-Credentials: true' );
		\header( 'Access-Control-Expose-Headers: ' . self::EXPOSED_HEADERS );
		\header( 'Vary: Origin' );

		if ( self::is_options_request() && ! $served ) {
			\header( 'Access-Control-Max-Age: 86400' );
			status_header( 204 );
			return true;
		}

		return $served;
	}
}
