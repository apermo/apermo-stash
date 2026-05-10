<?php
/**
 * Uninstall handler for Apermo Stash.
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
delete_metadata( 'user', 0, '_apermo_stash_tokens', '', true );

// Drop the global hash → user index that backs O(1) token lookups.
delete_option( 'apermo_stash_token_index' );

// Drop the one-shot starter-tags marker so a fresh reinstall reseeds.
delete_option( 'apermo_stash_starter_tags_seeded' );

// Drop the one-shot transients used to surface freshly-issued tokens. A
// direct query is used here because user IDs are not enumerated and
// per-user delete_transient() calls would require iterating every user.
// Caching is irrelevant during uninstall.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_apermo_stash_new_token_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_apermo_stash_new_token_' ) . '%',
	),
);

// Bookmarks are deliberately retained — see README.
