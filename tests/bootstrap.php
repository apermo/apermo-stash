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

// WP normally defines DAY_IN_SECONDS in wp-includes/default-constants.php.
// Provide it for unit-only runs so code that checks against it (e.g.
// SettingsPage::formatted_date) works without bringing in WP.
if ( ! $loading_wp && ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// WP defines these wpdb output-format constants in wp-includes/wp-db.php
// (loaded as part of WP boot). Mirror them here for unit-only runs so
// callers of `$wpdb->get_results( ..., ARRAY_A )` don't trip on a
// missing constant when WP isn't around.
if ( ! $loading_wp && ! defined( 'ARRAY_A' ) ) {
	define( 'OBJECT', 'OBJECT' );
	define( 'OBJECT_K', 'OBJECT_K' );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'ARRAY_N', 'ARRAY_N' );
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
