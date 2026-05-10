<?php

declare(strict_types=1);

namespace Apermo\Stash\Admin;

\defined( 'ABSPATH' ) || exit();

/**
 * Adds a "Quick Link" widget to the WordPress admin dashboard.
 *
 * Mirrors core's Quick Draft widget: a small paste-a-URL form that posts
 * to the same `apermo_stash_quick_add` admin-post handler used by the link
 * list-screen quick-add. The form HTML is reused via
 * {@see QuickAdd::render_form_html} so behaviour stays in lockstep.
 */
class DashboardWidget {

	private const WIDGET_ID = 'apermo_stash_quick_link';

	/**
	 * Hooks the dashboard setup.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ] );
	}

	/**
	 * Registers the widget with the dashboard, gated on `edit_posts`.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'Quick Link', 'apermo-stash' ),
			[ $this, 'render' ],
		);
	}

	/**
	 * Renders the dashboard widget body.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<p>' . esc_html__( 'Save a URL to your link library.', 'apermo-stash' ) . '</p>';
		QuickAdd::render_form_html( 'apermo-stash-dashboard-widget' );
	}
}
