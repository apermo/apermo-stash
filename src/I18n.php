<?php

declare(strict_types=1);

namespace Apermo\Stash;

\defined( 'ABSPATH' ) || exit();

use function Required\Traduttore_Registry\add_project;

/**
 * Registers the plugin with the self-hosted Traduttore Registry so installs
 * receive translations from the GlotPress server at translate.chrdm.de.
 *
 * PHP translations are loaded just-in-time by WordPress 7.0+, so no manual
 * `load_plugin_textdomain()` call is needed; this class only points WordPress
 * at the translation source.
 */
final class I18n {

	/**
	 * Project type as understood by Traduttore Registry.
	 */
	private const PROJECT_TYPE = 'plugin';

	/**
	 * GlotPress translations API endpoint for this project. The trailing slash
	 * is significant; the bare `/api/translations/` path returns a 404.
	 */
	private const API_URL = 'https://translate.chrdm.de/glotpress/api/translations/apermo-stash/';

	/**
	 * Wires the registration on init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'add_project' ] );
	}

	/**
	 * Registers the project with Traduttore Registry when the library is
	 * present. Degrades to a no-op when the dependency is missing.
	 *
	 * @return void
	 */
	public function add_project(): void {
		if ( \function_exists( 'Required\Traduttore_Registry\add_project' ) ) {
			add_project(
				self::PROJECT_TYPE,
				'apermo-stash',
				self::API_URL,
			);
		}
	}
}
