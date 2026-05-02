<?php

declare(strict_types=1);

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( $wp_tests_dir === false ) {
	$vendor_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
	if ( is_dir( $vendor_dir ) ) {
		$wp_tests_dir = $vendor_dir;
	}
}

$loading_wp = $wp_tests_dir !== false && is_dir( $wp_tests_dir );

// Source files include `defined( 'ABSPATH' ) || exit();` guards. In unit-only
// runs nothing else defines ABSPATH, so the autoloader would exit() the
// moment it loaded a class. Pre-define it here for that case. When the WP
// test suite is loading we leave it alone — WP defines its own ABSPATH that
// points at the WordPress install, and overriding that breaks core lookups
// (e.g. wp-phpunit's mock-mailer reaching ABSPATH . wp-includes/PHPMailer/...).
if ( ! $loading_wp && ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../vendor/autoload.php';

// Load the WordPress class stubs only when the real WP suite is not available.
// In integration runs WP core declares its own WP_Error and friends; double-
// declaring them here would cause a fatal.
if ( ! $loading_wp ) {
	require_once __DIR__ . '/stubs.php';
}

if ( $loading_wp ) {
	if ( getenv( 'WP_MULTISITE' ) ) {
		define( 'WP_TESTS_MULTISITE', true );
	}

	require_once $wp_tests_dir . '/includes/functions.php';

	tests_add_filter( 'muplugins_loaded', 'linkstash_tests_load_project' );

	require_once $wp_tests_dir . '/includes/bootstrap.php';
}

/**
 * Loads the plugin or theme under test.
 *
 * @return void
 */
function linkstash_tests_load_project(): void {
	$plugin_file = dirname( __DIR__ ) . '/plugin.php';
	if ( file_exists( $plugin_file ) ) {
		require $plugin_file;
	} else {
		register_theme_directory( dirname( __DIR__, 2 ) );
		switch_theme( basename( dirname( __DIR__ ) ) );
	}
}
