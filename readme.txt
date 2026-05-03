=== LinkStash ===
Contributors: apermo
Tags: bookmarks, links, rest-api, self-hosted, archive
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted bookmark archive with a token-protected REST API and a
companion Chrome extension.

== Description ==

LinkStash turns your WordPress site into a personal bookmark archive,
inspired by [linkding](https://linkding.link/). Save URLs with a title,
notes, and tags from the WordPress admin or from your browser via a
[Chrome extension](https://github.com/apermo/linkstash-extension); read
them back through the same admin UI or over a REST API designed for
extensions and your own scripts.

= Highlights =

* **Bookmarks as a custom post type.** Every URL is a `linkstash_bookmark`
  post — searchable, filterable, taggable, and reachable through
  WordPress's existing tooling.
* **Public or private per-bookmark.** Visibility uses native
  `post_status`: `publish` for shareable bookmarks, `private` for the
  ones only you should see. Anonymous REST clients see only public;
  authenticated users see public + their own private; admins see
  everything.
* **Dashboard widget for quick capture.** A QuickDraft-style "Add
  bookmark" tile lives on the WordPress dashboard. Paste a URL,
  optionally type tags (with autocomplete) and pick public/private,
  hit save. The plugin fetches the page title and meta description
  automatically and records whether the URL responded so you know
  later when a link rots.
* **Classic editor for bookmark detail.** No Gutenberg overhead — the
  Add/Edit screen is a small classic-editor form with URL, title,
  optional notes, and tags. Title falls back to a simplified URL when
  you leave it empty.
* **REST API, Bearer-authenticated.** Every endpoint under
  `linkstash/v1` accepts WordPress Application Passwords and
  plugin-issued Bearer tokens. CORS is preconfigured for
  `chrome-extension://*` so the companion extension works without
  additional setup.
* **Idempotent save.** `POST /bookmarks` dedupes by canonical URL —
  re-saving the same page from the extension merges into the existing
  record (and updates fields you change) rather than creating a
  duplicate.
* **Tag autocomplete.** Both the admin forms and the REST API return
  tag suggestions scoped to the caller's visibility.

= REST API =

`linkstash/v1` exposes:

* `GET /bookmarks` (list, paged, filterable by tag / favorite / public /
  private)
* `POST /bookmarks` (create — idempotent on canonical URL)
* `GET /bookmarks/{id}` / `PATCH /bookmarks/{id}` / `DELETE /bookmarks/{id}`
* `GET /tags?q=` (tag listing with counts; respects visibility)
* `GET /check?url=` (browser-extension "is this saved?" check)

Token CRUD lives under **Settings → LinkStash**. New tokens are shown
once at creation time; their hash is stored in user meta and never
recoverable.

= Companion Chrome extension =

A Chrome MV3 extension is in development at
[apermo/linkstash-extension](https://github.com/apermo/linkstash-extension).
It surfaces the saved/unsaved state on the action badge, lets you save
or edit the current tab from the popup, and offers a right-click
"Save link" context menu.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/linkstash/`, or
   install via the Plugins screen in WordPress.
2. Activate the plugin.
3. Visit **Settings → Permalinks** and pick anything other than
   "Plain" — the REST API needs rewrite rules. Most installs default
   to a sensible setting already.
4. Visit **Settings → LinkStash** to generate an API token for your
   browser extension or scripting.

== Frequently Asked Questions ==

= Where are my bookmarks stored? =

In your WordPress database, as posts of type `linkstash_bookmark`.
The URL, canonical URL, favorite flag, and unreachable flag live in
post meta. Tags use a custom non-hierarchical taxonomy
(`linkstash_tag`), separate from your standard post tags.

= Can multiple users on the same site have separate libraries? =

Yes. Each bookmark has an author and a public/private visibility
flag. Anonymous visitors see only public bookmarks; logged-in users
see public + their own private. Editors / admins (anyone with
`edit_others_posts`) see every bookmark across the site.

= Will uninstalling the plugin delete my bookmarks? =

No — bookmarks are deliberately preserved on uninstall, so you can
deactivate, switch to another bookmark plugin, or come back later
without losing the archive. Only API tokens and transient state are
removed.

= Does it work with WordPress Application Passwords? =

Yes, in addition to plugin-issued Bearer tokens. Both flows hit the
same REST endpoints; the plugin's Bearer auth runs through
`determine_current_user`, which composes cleanly with WordPress's
built-in authentication.

= Does it modify the front end? =

Not currently. Bookmarks live in the admin and the REST API. A public
sharing page is on the roadmap.

= What data does the plugin send anywhere? =

Outbound HTTP from your server to one place only: the URL you save.
On every save (admin form, dashboard widget, REST POST), LinkStash
issues a single `wp_safe_remote_get` against the bookmarked URL with
a 5-second timeout to fetch its title and meta description. If the
URL cannot be reached the bookmark is still saved and a "URL didn't
respond" warning is shown next time you edit it. WordPress's
`wp_safe_remote_get` blocks loopback and private IP ranges, so a
malicious URL cannot be used to probe internal services.

The plugin does not call any third-party services, does not send
analytics or telemetry, and does not load resources from third-party
CDNs. The companion Chrome extension talks only to the LinkStash
host you configure on its options page.

= Can I lock down which browser extensions can talk to the API? =

Yes. The plugin defaults to allowing CORS preflight from any
`chrome-extension://...` origin, which is convenient for installing
the companion extension before you know its ID, but means any
installed Chrome extension on your browser could call the API if it
also has a valid Bearer token.

To restrict the allow-list to a specific extension after install,
add a snippet to your `mu-plugins/` folder or theme's functions.php:

    add_filter( 'linkstash_allowed_origins', static function () {
        return [ 'chrome-extension://abcdefghijklmnopqrstuvwxyzabcdef' ];
    } );

Replace the example ID with the actual ID shown on your `chrome://extensions`
page. The Bearer token is still required regardless; this is a
defense-in-depth narrowing of the CORS surface.

== Screenshots ==

1. Bookmark list screen with the URL / tags / visibility / favorite columns.
2. Bookmark edit screen with URL meta box and unreachable-URL warning.
3. Dashboard widget for one-click capture from anywhere in the admin.
4. Settings → LinkStash token settings page.
5. Companion Chrome extension popup saving the current tab.

== Changelog ==

= 0.1.1 =
* WordPress.org listing assets (banner, icon, screenshots) and an
  expanded readme. No functional changes.

= 0.1.0 =
* Initial release. Custom post type, custom tag taxonomy, REST API
  (CRUD + tags + check), Bearer-token auth, public/private per
  bookmark, CORS for `chrome-extension://*`, admin list columns,
  quick-add form, classic-editor metaboxes, dashboard widget, tag
  autocomplete, https:// auto-prepend, URL-reachability check,
  Settings → LinkStash settings page, uninstall cleanup.
