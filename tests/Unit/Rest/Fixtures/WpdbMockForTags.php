<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Tests\Unit\Rest\Fixtures;

/**
 * Stands in for the WordPress $wpdb global in unit tests.
 *
 * Records the prepared SQL/args on each call and returns whichever
 * rows the test pre-loaded via the by-reference handle.
 */
class WpdbMockForTags {

	/**
	 * Holds the canned rows the next get_results call will return.
	 *
	 * @var list<array<string, mixed>>
	 */
	public array $rows_ref;

	/**
	 * Mirrors $wpdb->terms.
	 *
	 * @var string
	 */
	public string $terms = 'wp_terms';

	/**
	 * Mirrors $wpdb->term_taxonomy.
	 *
	 * @var string
	 */
	public string $term_taxonomy = 'wp_term_taxonomy';

	/**
	 * Mirrors $wpdb->term_relationships.
	 *
	 * @var string
	 */
	public string $term_relationships = 'wp_term_relationships';

	/**
	 * Mirrors $wpdb->posts.
	 *
	 * @var string
	 */
	public string $posts = 'wp_posts';

	/**
	 * Captures the most recent SQL handed to prepare().
	 *
	 * @var string|null
	 */
	public ?string $last_sql = null;

	/**
	 * Captures the most recent args handed to prepare().
	 *
	 * @var list<int|string>
	 */
	public array $last_args = [];

	/**
	 * Constructs the mock with a by-reference handle to the test's row queue.
	 *
	 * @param list<array<string, mixed>> $rows_ref By-ref handle.
	 */
	public function __construct( array &$rows_ref ) {
		$this->rows_ref = &$rows_ref;
	}

	/**
	 * Records the prepare() call and returns the SQL unchanged.
	 *
	 * @param string            $sql  SQL template.
	 * @param array<int, mixed> $args Placeholder values.
	 *
	 * @return string
	 */
	public function prepare( string $sql, array $args ): string {
		$this->last_sql  = $sql;
		$this->last_args = \array_values( $args );
		return $sql;
	}

	/**
	 * Returns the queued rows verbatim (output format and SQL are ignored).
	 *
	 * Signature mirrors `wpdb::get_results()` so the SUT can call it
	 * without knowing it's hitting a stub; the parameters are
	 * intentionally unused on the fixture side.
	 *
	 * @param string $sql    Prepared SQL (unused — fixture returns canned rows).
	 * @param mixed  $output Output format (unused — fixture returns ARRAY_A shape).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function get_results( string $sql, mixed $output = null ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter
		return $this->rows_ref;
	}
}
