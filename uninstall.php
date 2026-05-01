<?php
/**
 * Uninstall handler for LinkStash.
 *
 * Removes plugin-owned data (API tokens, transients) but intentionally
 * preserves user-created bookmarks so an accidental delete + reinstall
 * does not destroy the archive.
 *
 * Re-runs after the plugin is deleted from the WordPress UI.
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit();

global $wpdb;

// Delete every user's stored API tokens.
delete_metadata( 'user', 0, '_linkstash_tokens', '', true );

// Drop one-shot transients used to surface freshly-issued tokens. A direct
// query is used here because the user IDs are not enumerated and per-user
// delete_transient() calls would require iterating every user. Caching is
// not relevant during uninstall.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_linkstash_new_token_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_linkstash_new_token_' ) . '%',
	),
);

// Bookmarks are deliberately retained — see README.
