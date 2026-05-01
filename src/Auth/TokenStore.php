<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Auth;

/**
 * Persists plugin-issued API tokens as WordPress user meta.
 *
 * Tokens are stored as an array of entries on the `_linkstash_tokens` user
 * meta key. Each entry holds the token id, name, hash (sha256 of the plain
 * token), creation time, and last-used time. The plain token value is shown
 * once at creation time and never re-derivable from storage.
 */
class TokenStore {

	public const META_KEY = '_linkstash_tokens';

	private const TOKEN_LENGTH = 40;

	/**
	 * Clock used for created/last_used timestamps.
	 *
	 * @var callable():int
	 */
	private $clock;

	/**
	 * Constructs the store with an injectable clock for testability.
	 *
	 * @param (callable():int)|null $clock Returns the current Unix timestamp.
	 */
	public function __construct( ?callable $clock = null ) {
		$this->clock = $clock ?? static fn (): int => \time();
	}

	/**
	 * Hashes a plain token.
	 *
	 * Token values are server-generated, high-entropy random strings, so a
	 * fast cryptographic hash (SHA-256) is sufficient — there is no need for
	 * the slow password-strength hashing that would apply to user-chosen
	 * passwords.
	 *
	 * @param string $plain Plain token.
	 *
	 * @return string
	 */
	private static function hash( string $plain ): string {
		return \hash( 'sha256', $plain );
	}

	/**
	 * Returns a public view of an entry with the hash field removed.
	 *
	 * @param array{id: string, name: string, hash: string, created: int, last_used: ?int} $entry Raw entry.
	 *
	 * @return array{id: string, name: string, created: int, last_used: ?int}
	 */
	private static function public_view( array $entry ): array {
		return [
			'id'        => $entry['id'],
			'name'      => $entry['name'],
			'created'   => $entry['created'],
			'last_used' => $entry['last_used'],
		];
	}

	/**
	 * Creates and persists a new token, returning the plain value.
	 *
	 * @param int    $user_id User the token is bound to.
	 * @param string $name    Human-readable label.
	 *
	 * @return string Plain token value (only returned at creation time).
	 */
	public function create( int $user_id, string $name ): string {
		$plain = wp_generate_password( self::TOKEN_LENGTH, false );

		$entry = [
			'id'        => wp_generate_uuid4(),
			'name'      => $name,
			'hash'      => self::hash( $plain ),
			'created'   => $this->now(),
			'last_used' => null,
		];

		$entries   = $this->raw_entries( $user_id );
		$entries[] = $entry;
		update_user_meta( $user_id, self::META_KEY, $entries );

		return $plain;
	}

	/**
	 * Returns the user's tokens with the hash field stripped.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return list<array{id: string, name: string, created: int, last_used: ?int}>
	 */
	public function list( int $user_id ): array {
		return \array_map( [ self::class, 'public_view' ], $this->raw_entries( $user_id ) );
	}

	/**
	 * Removes the matching token entry.
	 *
	 * @param int    $user_id User ID.
	 * @param string $id      Token id.
	 *
	 * @return bool True if an entry was removed.
	 */
	public function revoke( int $user_id, string $id ): bool {
		$entries  = $this->raw_entries( $user_id );
		$filtered = \array_values(
			\array_filter(
				$entries,
				static fn ( array $entry ): bool => $entry['id'] !== $id,
			),
		);

		if ( \count( $filtered ) === \count( $entries ) ) {
			return false;
		}

		if ( \count( $filtered ) === 0 ) {
			delete_user_meta( $user_id, self::META_KEY );
		} else {
			update_user_meta( $user_id, self::META_KEY, $filtered );
		}

		return true;
	}

	/**
	 * Locates the owner of a plain token, scanning across all users.
	 *
	 * Returns null when no entry matches.
	 *
	 * @param string $plain Plain token value.
	 *
	 * @return array{user_id: int, id: string}|null
	 */
	public function find_by_plain( string $plain ): ?array {
		if ( $plain === '' ) {
			return null;
		}

		$hash = self::hash( $plain );

		$user_ids = get_users( [ 'fields' => 'ID' ] );
		foreach ( $user_ids as $raw_id ) {
			$user_id = (int) $raw_id;
			foreach ( $this->raw_entries( $user_id ) as $entry ) {
				if ( \hash_equals( $entry['hash'], $hash ) ) {
					return [
						'user_id' => $user_id,
						'id'      => $entry['id'],
					];
				}
			}
		}

		return null;
	}

	/**
	 * Updates the last-used timestamp of a token entry.
	 *
	 * @param int    $user_id User ID.
	 * @param string $id      Token id.
	 *
	 * @return void
	 */
	public function touch_last_used( int $user_id, string $id ): void {
		$entries = $this->raw_entries( $user_id );
		$dirty   = false;
		foreach ( $entries as $key => $entry ) {
			if ( $entry['id'] === $id ) {
				$entries[ $key ]['last_used'] = $this->now();
				$dirty                        = true;
				break;
			}
		}

		if ( $dirty ) {
			update_user_meta( $user_id, self::META_KEY, $entries );
		}
	}

	/**
	 * Returns the current Unix timestamp via the injected clock.
	 *
	 * @return int
	 */
	private function now(): int {
		return ( $this->clock )();
	}

	/**
	 * Reads the raw entries for a user, normalizing missing meta to an empty list.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return list<array{id: string, name: string, hash: string, created: int, last_used: ?int}>
	 */
	private function raw_entries( int $user_id ): array {
		$value = get_user_meta( $user_id, self::META_KEY, true );

		return \is_array( $value ) ? \array_values( $value ) : [];
	}
}
