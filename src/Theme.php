<?php

declare(strict_types=1);

namespace Plugin_Name;

/**
 * Bootstraps the theme.
 */
class Theme {

	/**
	 * Initializes the theme.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'after_setup_theme', [ self::class, 'setup' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Sets up theme support.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
	}

	/**
	 * Enqueues front-end assets.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'plugin-name-style',
			get_stylesheet_uri(),
			[],
			\PLUGIN_NAME_VERSION,
		);
	}
}
