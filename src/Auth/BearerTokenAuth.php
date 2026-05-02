<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Auth;

/**
 * Authenticates REST API requests carrying a `Authorization: Bearer <token>` header.
 */
class BearerTokenAuth {

	/**
	 * Holds the token store used for lookups.
	 *
	 * @var TokenStore
	 */
	private TokenStore $store;

	/**
	 * Constructs the filter handler.
	 *
	 * @param TokenStore $store Token store.
	 */
	public function __construct( TokenStore $store ) {
		$this->store = $store;
	}

	/**
	 * Extracts the bearer token from the Authorization header, or returns an empty string.
	 *
	 * @return string
	 */
	private static function extract_token(): string {
		$header = self::authorization_header();
		if ( $header === '' ) {
			return '';
		}

		if ( \stripos( $header, 'Bearer ' ) !== 0 ) {
			return '';
		}

		return \trim( \substr( $header, 7 ) );
	}

	/**
	 * Returns the Authorization header value, normalizing common server quirks.
	 *
	 * @return string
	 */
	private static function authorization_header(): string {
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) && \is_string( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) && \is_string( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( \function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			foreach ( $headers as $name => $value ) {
				if ( \strcasecmp( (string) $name, 'Authorization' ) === 0 ) {
					return sanitize_text_field( wp_unslash( (string) $value ) );
				}
			}
		}

		return '';
	}

	/**
	 * Registers the determine_current_user filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'determine_current_user', [ $this, 'authenticate' ] );
	}

	/**
	 * Resolves the request user from a bearer token, falling through to the input
	 * user id when no token is present or no token matches.
	 *
	 * @param int|false|null $user_id Current user ID candidate from earlier filters.
	 *
	 * @return int|false|null
	 */
	public function authenticate( int|false|null $user_id ): int|false|null {
		$token = self::extract_token();
		if ( $token === '' ) {
			return $user_id;
		}

		$match = $this->store->find_by_plain( $token );
		if ( $match === null ) {
			return $user_id;
		}

		$this->store->touch_last_used( $match['user_id'], $match['id'] );

		return $match['user_id'];
	}
}
