<?php

declare(strict_types=1);

namespace Apermo\Stash\Rest;

\defined( 'ABSPATH' ) || exit();

/**
 * Registers the apermo-stash/v1 REST namespace and routes.
 */
class RestController {

	public const NAMESPACE = 'apermo-stash/v1';

	/**
	 * Holds the bookmarks controller.
	 *
	 * @var BookmarksController
	 */
	private BookmarksController $bookmarks;

	/**
	 * Holds the tags controller.
	 *
	 * @var TagsController
	 */
	private TagsController $tags;

	/**
	 * Holds the URL check controller.
	 *
	 * @var CheckController
	 */
	private CheckController $check;

	/**
	 * Constructs the registrar with its child controllers.
	 *
	 * @param BookmarksController $bookmarks Bookmarks controller.
	 * @param TagsController      $tags      Tags controller.
	 * @param CheckController     $check     URL check controller.
	 */
	public function __construct(
		BookmarksController $bookmarks,
		TagsController $tags,
		CheckController $check
	) {
		$this->bookmarks = $bookmarks;
		$this->tags      = $tags;
		$this->check     = $check;
	}

	/**
	 * Hooks REST initialisation.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers all routes under the apermo-stash namespace.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$this->bookmarks->register_routes( self::NAMESPACE );
		$this->tags->register_routes( self::NAMESPACE );
		$this->check->register_routes( self::NAMESPACE );
	}
}
