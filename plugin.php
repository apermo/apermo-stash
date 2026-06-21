<?php
/*
 * Plugin Name: Apermo Stash
 * Plugin URI:  https://github.com/apermo/apermo-stash
 * Description: A self-hosted link collection with a token-protected REST API.
 * Version:     0.2.1
 * Author:      Christoph Daum
 * Author URI:  https://christoph-daum.com
 * License:     GPL-2.0-or-later
 * Text Domain: apermo-stash
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

declare(strict_types=1);

namespace Apermo\Stash;

\defined( 'ABSPATH' ) || exit();

if ( \file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Reached when neither a local vendor/autoload.php nor a parent project's
// autoloader (Bedrock and similar) has registered the plugin's PSR-4 namespace.
if ( ! \class_exists( Main::class ) ) {
	add_action(
		'admin_notices',
		// phpcs:ignore Universal.FunctionDeclarations.NoLongClosures.ExceedsMaximum
		static function (): void {
			wp_admin_notice(
				wp_kses(
					\sprintf(
						/* translators: %s: composer install command */
						__( 'Please run %s to install the required dependencies.', 'apermo-stash' ),
						'<code>composer install</code>',
					),
					[ 'code' => [] ],
				),
				[ 'type' => 'error' ],
			);
		},
	);
	return;
}

Main::init( __FILE__ );
