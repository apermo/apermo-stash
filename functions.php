<?php

declare(strict_types=1);

namespace Plugin_Name;

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
						__( 'Please run %s to install the required dependencies.', 'plugin-name' ),
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

\define( 'PLUGIN_NAME_VERSION', '0.1.0' );
\define( 'PLUGIN_NAME_FILE', __FILE__ );
\define( 'PLUGIN_NAME_DIR', get_template_directory() . '/' );

require_once __DIR__ . '/vendor/autoload.php';

Theme::init();
