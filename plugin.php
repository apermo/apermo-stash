<?php
/*
 * Plugin Name: LinkStash
 * Plugin URI:  https://github.com/apermo/linkstash
 * Description: A self-hosted bookmark collection with a token-protected REST API.
 * Version:     0.1.3
 * Author:      Christoph Daum
 * Author URI:  https://apermo.de
 * License:     GPL-2.0-or-later
 * Text Domain: linkstash
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

declare(strict_types=1);

namespace Apermo\LinkStash;

\defined( 'ABSPATH' ) || exit();

if ( ! \file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		// phpcs:ignore Universal.FunctionDeclarations.NoLongClosures.ExceedsMaximum
		static function (): void {
			wp_admin_notice(
				wp_kses(
					\sprintf(
						/* translators: %s: composer install command */
						__( 'Please run %s to install the required dependencies.', 'linkstash' ),
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

require_once __DIR__ . '/vendor/autoload.php';

Main::init( __FILE__ );
