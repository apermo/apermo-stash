<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Auth;

\defined( 'ABSPATH' ) || exit();

/**
 * Persists plugin-issued API tokens as WordPress user meta.
 *
 * Tokens are stored as an array of entries on the `_linkstash_tokens` user
 * meta key. Each entry holds the token id, name, hash (sha256 of the plain
 * token), creation time, and last-used time. The plain token value is shown
 * once at creation time and never re-derivable from storage.
 */
class TokenStore {

	public const META_KEY     = '_linkstash_tokens';
	public const INDEX_OPTION = 'linkstash_token_index';

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

		$this->index_set( $entry['hash'], $user_id, $entry['id'] );

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
		$entries        = $this->raw_entries( $user_id );
		$removed_hashes = [];
		$filtered       = [];
		foreach ( $entries as $entry ) {
			if ( $entry['id'] === $id ) {
				$removed_hashes[] = $entry['hash'];
				continue;
			}
			$filtered[] = $entry;
		}

		if ( $removed_hashes === [] ) {
			return false;
		}

		if ( $filtered === [] ) {
			delete_user_meta( $user_id, self::META_KEY );
		} else {
			update_user_meta( $user_id, self::META_KEY, $filtered );
		}

		foreach ( $removed_hashes as $hash ) {
			$this->index_remove( $hash );
		}

		return true;
	}

	/**
	 * Locates the owner of a plain token via the hash → user index.
	 *
	 * Returns null when the hash is not in the index, even if a matching
	 * token entry exists in user meta. The index is the only source of
	 * truth — `create()` writes both sides atomically — and an unindexed
	 * lookup would otherwise let an unauthenticated caller force a full
	 * `get_users()` scan via repeated invalid Bearer tokens (DoS).
	 *
	 * @param string $plain Plain token value.
	 *
	 * @return array{user_id: int, id: string}|null
	 */
	public function find_by_plain( string $plain ): ?array {
		if ( $plain === '' ) {
			return null;
		}

		$hash  = self::hash( $plain );
		$index = $this->load_index();

		if ( ! isset( $index[ $hash ] ) ) {
			return null;
		}

		$user_id = $index[ $hash ]['user_id'];

		foreach ( $this->raw_entries( $user_id ) as $entry ) {
			if ( \hash_equals( $entry['hash'], $hash ) ) {
				return [
					'user_id' => $user_id,
					'id'      => $entry['id'],
				];
			}
		}

		// Entry vanished from user meta; drop the stale index row.
		$this->index_remove( $hash );

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

	/**
	 * Loads the global hash → user index, normalizing missing data to an empty array.
	 *
	 * @return array<string, array{user_id: int, id: string}>
	 */
	private function load_index(): array {
		$value = get_option( self::INDEX_OPTION, [] );

		return \is_array( $value ) ? $value : [];
	}

	/**
	 * Adds (or replaces) an index entry.
	 *
	 * @param string $hash    Hashed token value.
	 * @param int    $user_id Owner user ID.
	 * @param string $id      Token id.
	 *
	 * @return void
	 */
	private function index_set( string $hash, int $user_id, string $id ): void {
		$index          = $this->load_index();
		$index[ $hash ] = [
			'user_id' => $user_id,
			'id'      => $id,
		];
		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Removes an index entry, or no-ops when the hash is unknown.
	 *
	 * @param string $hash Hashed token value.
	 *
	 * @return void
	 */
	private function index_remove( string $hash ): void {
		$index = $this->load_index();
		if ( ! isset( $index[ $hash ] ) ) {
			return;
		}
		unset( $index[ $hash ] );
		if ( $index === [] ) {
			delete_option( self::INDEX_OPTION );
			return;
		}
		update_option( self::INDEX_OPTION, $index, false );
	}
}
