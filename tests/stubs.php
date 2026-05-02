<?php
/**
 * Minimal WordPress class stubs for unit tests that exercise code paths
 * branching on WordPress error types without loading the full WP suite.
 *
 * Loaded from tests/bootstrap.php before unit tests run; skipped when the
 * full WordPress test environment is loaded for integration tests (real
 * WP_Error from core takes precedence in that case).
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Minimal WP_Query stand-in for unit tests.
	 *
	 * Each `new WP_Query(...)` consumes the next entry from the static
	 * `$results` queue, so a test can pre-load the responses it expects.
	 * Construction args are recorded in the public `$args` property so
	 * tests can assert what the SUT requested.
	 */
	class WP_Query {

		/**
		 * Holds the args the SUT passed to the constructor.
		 *
		 * @var array<string, mixed>
		 */
		public array $args = [];

		/**
		 * Holds the IDs (or post objects) returned for this query.
		 *
		 * @var array<int, mixed>
		 */
		public array $posts = [];

		/**
		 * Holds the total found posts (independent of pagination).
		 *
		 * @var int
		 */
		public int $found_posts = 0;

		/**
		 * Holds the max page count.
		 *
		 * @var int
		 */
		public int $max_num_pages = 1;

		/**
		 * Holds queued query results.
		 *
		 * @var list<array{posts?: array<int, mixed>, found_posts?: int, max_num_pages?: int}>
		 */
		public static array $results = [];

		/**
		 * Constructs the stub.
		 *
		 * @param array<string, mixed> $args Query args.
		 */
		public function __construct( array $args = [] ) {
			$this->args = $args;
			if ( self::$results !== [] ) {
				$next                = \array_shift( self::$results );
				$this->posts         = $next['posts'] ?? [];
				$this->found_posts   = $next['found_posts'] ?? \count( $this->posts );
				$this->max_num_pages = $next['max_num_pages'] ?? 1;
			}
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post stand-in for unit tests.
	 */
	class WP_Post {

		/**
		 * Stores the post ID.
		 *
		 * @var int
		 */
		public int $ID = 0; // phpcs:ignore WordPress.NamingConventions.ValidVariableName

		/**
		 * Stores the post status.
		 *
		 * @var string
		 */
		public string $post_status = 'publish';

		/**
		 * Stores the post type.
		 *
		 * @var string
		 */
		public string $post_type = '';

		/**
		 * Stores the post title.
		 *
		 * @var string
		 */
		public string $post_title = '';

		/**
		 * Stores the post content.
		 *
		 * @var string
		 */
		public string $post_content = '';

		/**
		 * Stores the post author.
		 *
		 * @var int
		 */
		public int $post_author = 0;

		/**
		 * Stores the GMT created date.
		 *
		 * @var string
		 */
		public string $post_date_gmt = '2026-05-01 00:00:00';

		/**
		 * Stores the GMT modified date.
		 *
		 * @var string
		 */
		public string $post_modified_gmt = '2026-05-01 00:00:00';
	}
}

if ( ! class_exists( 'WP_Screen' ) ) {
	/**
	 * Minimal WP_Screen stand-in for unit tests.
	 */
	class WP_Screen {

		/**
		 * Holds the screen base (e.g. "edit", "post").
		 *
		 * @var string
		 */
		public string $base = '';

		/**
		 * Holds the screen post type, when applicable.
		 *
		 * @var string
		 */
		public string $post_type = '';
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	/**
	 * Minimal WP_Term stand-in for unit tests.
	 */
	class WP_Term {

		/**
		 * Stores the term ID.
		 *
		 * @var int
		 */
		public int $term_id = 0;

		/**
		 * Stores the term slug.
		 *
		 * @var string
		 */
		public string $slug = '';

		/**
		 * Stores the term name.
		 *
		 * @var string
		 */
		public string $name = '';

		/**
		 * Stores the count of attached posts.
		 *
		 * @var int
		 */
		public int $count = 0;
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Minimal WP_REST_Server constants for unit tests.
	 */
	class WP_REST_Server {

		public const READABLE  = 'GET';
		public const CREATABLE = 'POST';
		public const EDITABLE  = 'POST, PUT, PATCH';
		public const DELETABLE = 'DELETE';
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal WP_REST_Response stand-in for unit tests.
	 */
	class WP_REST_Response {

		/**
		 * Stores the response payload.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Stores response headers.
		 *
		 * @var array<string, string>
		 */
		public array $headers = [];

		/**
		 * Stores the HTTP status.
		 *
		 * @var int
		 */
		public int $status = 200;

		/**
		 * Constructs the stub.
		 *
		 * @param mixed $data    Payload.
		 * @param int   $status  HTTP status.
		 * @param array<string, string> $headers Headers.
		 */
		public function __construct( $data = null, int $status = 200, array $headers = [] ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}

		/**
		 * Sets a header.
		 *
		 * @param string $key   Header name.
		 * @param string $value Header value.
		 *
		 * @return void
		 */
		public function header( string $key, string $value ): void {
			$this->headers[ $key ] = $value;
		}

		/**
		 * Sets the HTTP status.
		 *
		 * @param int $status Status.
		 *
		 * @return void
		 */
		public function set_status( int $status ): void {
			$this->status = $status;
		}

		/**
		 * Returns the response data.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal WP_REST_Request stand-in for unit tests.
	 *
	 * Mirrors only the surface that LinkStash code touches: `get_param`,
	 * `has_param`, and `ArrayAccess`. Mockery extends this class to mock
	 * specific methods per test.
	 *
	 * @implements \ArrayAccess<string, mixed>
	 */
	class WP_REST_Request implements \ArrayAccess {

		/**
		 * Holds request parameters.
		 *
		 * @var array<string, mixed>
		 */
		public array $params = [];

		/**
		 * Returns the value of a parameter, or null when absent.
		 *
		 * @param string $key Parameter name.
		 *
		 * @return mixed
		 */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * Returns whether a parameter is set.
		 *
		 * @param string $key Parameter name.
		 *
		 * @return bool
		 */
		public function has_param( string $key ): bool {
			return \array_key_exists( $key, $this->params );
		}

		/**
		 * @param mixed $offset Offset.
		 */
		public function offsetExists( $offset ): bool {
			return $this->has_param( (string) $offset );
		}

		/**
		 * @param mixed $offset Offset.
		 *
		 * @return mixed
		 */
		#[\ReturnTypeWillChange]
		public function offsetGet( $offset ) {
			return $this->get_param( (string) $offset );
		}

		/**
		 * @param mixed $offset Offset.
		 * @param mixed $value  Value.
		 */
		public function offsetSet( $offset, $value ): void {
			$this->params[ (string) $offset ] = $value;
		}

		/**
		 * @param mixed $offset Offset.
		 */
		public function offsetUnset( $offset ): void {
			unset( $this->params[ (string) $offset ] );
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stand-in for unit tests.
	 *
	 * Mirrors the constructor signature of the real class enough that test
	 * code can construct it; the tests rely on `is_wp_error()` (stubbed by
	 * Brain Monkey) rather than calling methods on the instance.
	 */
	class WP_Error {

		/**
		 * Stores the error code.
		 *
		 * @var string
		 */
		public string $code;

		/**
		 * Stores the human-readable error message.
		 *
		 * @var string
		 */
		public string $message;

		/**
		 * Constructs the stub.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
	}
}
