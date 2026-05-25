=== Apermo Stash ===
Contributors: apermo
Tags: links, bookmarks, rest-api, self-hosted, archive
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted link archive with a token-protected REST API and a
companion Chrome extension.

== Description ==

Apermo Stash turns your WordPress site into a personal link archive,
inspired by linkding and Delicious. Save URLs with a title,
notes, and tags from the WordPress admin or from your browser via a
[Chrome extension](https://chromewebstore.google.com/detail/linkstash/midebpgblmgkcgljcgojjbehnonljnmk); read
them back through the same admin UI or over a REST API designed for
extensions and your own scripts.

= Highlights =

* **Links as a custom post type.** Every URL is a `apermo_stash_link`
  post — searchable, filterable, taggable, and reachable through
  WordPress's existing tooling.
* **Public or private per-link.** Visibility uses native
  `post_status`: `publish` for shareable links, `private` for the
  ones only you should see. Anonymous REST clients see only public;
  authenticated users see public + their own private; admins see
  everything.
* **Dashboard widget for quick capture.** A QuickDraft-style "Add
  link" tile lives on the WordPress dashboard. Paste a URL,
  optionally type tags (with autocomplete) and pick public/private,
  hit save. The plugin fetches the page title and meta description
  automatically and records whether the URL responded so you know
  later when a link rots.
* **Classic editor for link detail.** No Gutenberg overhead — the
  Add/Edit screen is a small classic-editor form with URL, title,
  optional notes, and tags. Title falls back to a simplified URL when
  you leave it empty.
* **REST API, Bearer-authenticated.** Every endpoint under
  `apermo-stash/v1` accepts WordPress Application Passwords and
  plugin-issued Bearer tokens. CORS is preconfigured for
  `chrome-extension://*` so the companion extension works without
  additional setup.
* **Idempotent save.** `POST /links` dedupes by canonical URL —
  re-saving the same page from the extension merges into the existing
  record (and updates fields you change) rather than creating a
  duplicate.
* **Tag autocomplete.** Both the admin forms and the REST API return
  tag suggestions scoped to the caller's visibility.

= REST API =

`apermo-stash/v1` exposes:

* `GET /links` (list, paged, filterable by tag / favorite / public /
  private)
* `POST /links` (create — idempotent on canonical URL)
* `GET /links/{id}` / `PATCH /links/{id}` / `DELETE /links/{id}`
* `GET /tags?q=` (tag listing with counts; respects visibility)
* `GET /check?url=` (browser-extension "is this saved?" check)

Token CRUD lives under **Settings → Apermo Stash**. New tokens are shown
once at creation time; their hash is stored in user meta and never
recoverable.

= Companion Chrome extension =

A Chrome MV3 extension is published on the Chrome Web Store:
https://chromewebstore.google.com/detail/linkstash/midebpgblmgkcgljcgojjbehnonljnmk

Source: [apermo/linkstash-extension](https://github.com/apermo/linkstash-extension).

It surfaces the saved/unsaved state on the action badge, lets you save
or edit the current tab from the popup, and offers a right-click
"Save link" context menu.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/apermo-stash/`, or
   install via the Plugins screen in WordPress.
2. Activate the plugin.
3. Visit **Settings → Permalinks** and pick anything other than
   "Plain" — the REST API needs rewrite rules. Most installs default
   to a sensible setting already.
4. Visit **Settings → Apermo Stash** to generate an API token for your
   browser extension or scripting.

== Frequently Asked Questions ==

= Where are my links stored? =

In your WordPress database, as posts of type `apermo_stash_link`.
The URL, canonical URL, favorite flag, and unreachable flag live in
post meta. Tags use a custom non-hierarchical taxonomy
(`apermo_stash_tag`), separate from your standard post tags.

= Can multiple users on the same site have separate libraries? =

Yes. Each link has an author and a public/private visibility
flag. Anonymous visitors see only public links; logged-in users
see public + their own private. Editors / admins (anyone with
`edit_others_posts`) see every link across the site.

= Will uninstalling the plugin delete my links? =

No — links are deliberately preserved on uninstall, so you can
deactivate, switch to another link plugin, or come back later
without losing the archive. Only API tokens and transient state are
removed.

= Does it work with WordPress Application Passwords? =

Yes, in addition to plugin-issued Bearer tokens. Both flows hit the
same REST endpoints; the plugin's Bearer auth runs through
`determine_current_user`, which composes cleanly with WordPress's
built-in authentication.

= Does it modify the front end? =

Not currently. Links live in the admin and the REST API. A public
sharing page is on the roadmap.

= What data does the plugin send anywhere? =

Outbound HTTP from your server to one place only: the URL you save.
On every save (admin form, dashboard widget, REST POST), Apermo Stash
issues a single `wp_safe_remote_get` against the saved URL with
a 5-second timeout to fetch its title and meta description. If the
URL cannot be reached the link is still saved and a "URL didn't
respond" warning is shown next time you edit it. WordPress's
`wp_safe_remote_get` blocks loopback and private IP ranges, so a
malicious URL cannot be used to probe internal services.

The plugin does not call any third-party services, does not send
analytics or telemetry, and does not load resources from third-party
CDNs. The companion Chrome extension talks only to the Apermo Stash
host you configure on its options page.

= Can I lock down which browser extensions can talk to the API? =

Yes. The plugin defaults to allowing CORS preflight from any
`chrome-extension://...` origin, which is convenient for installing
the companion extension before you know its ID, but means any
installed Chrome extension on your browser could call the API if it
also has a valid Bearer token.

To restrict the allow-list to a specific extension after install,
add a snippet to your `mu-plugins/` folder or theme's functions.php:

    add_filter( 'apermo_stash_allowed_origins', static function () {
        return [ 'chrome-extension://abcdefghijklmnopqrstuvwxyzabcdef' ];
    } );

Replace the example ID with the actual ID shown on your `chrome://extensions`
page. The Bearer token is still required regardless; this is a
defense-in-depth narrowing of the CORS surface.

== Screenshots ==

1. Link list screen with the URL / tags / visibility / favorite columns.
2. Link edit screen with URL meta box and unreachable-URL warning.
3. Dashboard widget for one-click capture from anywhere in the admin.
4. Settings → Apermo Stash token settings page.
5. Companion Chrome extension popup saving the current tab.

== Changelog ==

= 0.2.1 =
* Added: local `.husky/commit-msg` hook mirroring the
  conventional-commit rules already enforced by CI's
  `pr-validation` workflow.
* Fixed: "Please run composer install" admin notice no longer
  false-positives when the plugin runs inside a Composer-managed
  parent project (Bedrock and similar). `plugin.php` now loads
  the local autoloader if present and only shows the notice when
  `Main` is still unreachable.
* Changed: "Tested up to" bumped from 6.9 to 7.0.

= 0.2.0 =
* Renamed plugin: LinkStash → Apermo Stash. Slug, namespace,
  text domain, REST namespace and Composer package all updated.
* Renamed content type: bookmark → link. Post type slug
  `apermo_stash_link`, REST endpoint `/links`, admin labels
  "Link"/"Links". The 21-character `apermo_stash_bookmark` would
  have exceeded WordPress's 20-char `register_post_type` limit.
* Security: `GET /links` and `GET /tags` now require `edit_posts`
  — both were previously reachable by unauthenticated callers.
* Author URI changed from apermo.de to christoph-daum.com.

= 0.1.3 =
* Fixed: the Chrome extension's save flow now actually reaches the
  REST API on production hosts. WordPress core's CORS handler strips
  `chrome-extension://` origins (the scheme is not in
  `wp_allowed_protocols()`) and emits an empty
  `Access-Control-Allow-Origin` header, which browsers reject. The
  plugin now removes the core hook for Apermo Stash routes and emits a
  complete CORS header set itself.

= 0.1.2 =
* Hardening: tighter output escaping in the link list table and
  contextual help tabs. No functional changes.

= 0.1.1 =
* New: single Favorite flag (replaces Unread / Archived), with a
  list-table column, an admin URL filter (?favorite=1), and a REST
  query/body field.
* New: starter tags created on first activation (read-later /
  reference / inspiration / archive); seeded once and never recreated
  if you delete them.
* New: unsaved-changes guard on the link add/edit screen.
* New: contextual help tabs on the link list screen.
* Changed: settings moved from Tools to Settings → Apermo Stash.
* Security: /check now requires authentication (was anonymous);
  unauthorized reads of private links return 404 instead of 403
  to prevent ID enumeration.
* Fixed: the Tags column on the link list screen showed nothing
  because the column key collided with WordPress core's reserved
  `tags` slot for the `post_tag` taxonomy.
* Listing: WordPress.org assets (banner, icon, screenshots) and an
  expanded readme.

= 0.1.0 =
* Initial release. Custom post type, custom tag taxonomy, REST API
  (CRUD + tags + check), Bearer-token auth, public/private per
  link, CORS for `chrome-extension://*`, admin list columns,
  quick-add form, classic-editor metaboxes, dashboard widget, tag
  autocomplete, https:// auto-prepend, URL-reachability check,
  Settings → Apermo Stash settings page, uninstall cleanup.
