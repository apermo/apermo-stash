<?php

declare(strict_types=1);

namespace Plugin_Name;

/**
 * Bootstraps the plugin.
 */
class Main {

	public const VERSION = '0.1.0';

	/**
	 * Holds the main plugin file path.
	 *
	 * @var string
	 */
	private static string $file = '';

	/**
	 * Initializes the plugin.
	 *
	 * @param string $file Main plugin file path.
	 *
	 * @return void
	 */
	public static function init( string $file ): void {
		self::$file = $file;

		register_activation_hook( $file, [ self::class, 'activate' ] );
		register_deactivation_hook( $file, [ self::class, 'deactivate' ] );
		add_action( 'plugins_loaded', [ self::class, 'boot' ] );
	}

	/**
	 * Returns the main plugin file path.
	 *
	 * @return string
	 */
	public static function file(): string {
		return self::$file;
	}

	/**
	 * Activates the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Activation logic.
	}

	/**
	 * Deactivates the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Deactivation logic.
	}

	/**
	 * Boots the plugin after all plugins are loaded.
	 *
	 * @return void
	 */
	public static function boot(): void {
		// Initialize plugin functionality.
	}
}
