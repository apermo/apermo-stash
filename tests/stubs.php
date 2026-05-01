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
