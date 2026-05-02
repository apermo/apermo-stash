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
