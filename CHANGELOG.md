# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Rename: LinkStash → Apermo Stash.** Slug `linkstash` →
  `apermo-stash`, namespace `Apermo\LinkStash` → `Apermo\Stash`,
  text domain `linkstash` → `apermo-stash`, REST namespace
  `linkstash/v1` → `apermo-stash/v1`, Composer package
  `apermo/linkstash` → `apermo/apermo-stash`. The post type
  (`apermo_stash_bookmark`), taxonomy (`apermo_stash_tag`), options,
  user/post meta keys, transient keys, query parameters, filter hooks,
  HTTP headers (`X-Apermo-Stash-Existing`,
  `X-Apermo-Stash-Meta-Fetched`), HTML data attributes, and CSS class
  names were all updated to match. No migration shim is provided —
  the plugin had no published WP.org installs at the time of the
  rename.
- Author URI on the plugin header is now
  `https://christoph-daum.com` (was `https://apermo.de`); the author
  is the person Christoph Daum, not the Apermo brand.

## [0.1.3] - 2026-05-03

### Fixed

- Chrome-extension save flow on production hosts. WordPress core's
  `rest_send_cors_headers` runs `sanitize_url()` on the incoming
  `Origin` header, and `chrome-extension://...` is not in
  `wp_allowed_protocols()` — so core writes an empty
  `Access-Control-Allow-Origin:` value. Browsers reject the empty
  string, the extension's preflight fails, and the POST silently
  never runs. (Local dev with DDEV typically didn't reproduce because
  the LiteSpeed/Apache plugin order on production let core's hook
  fire after ours and overwrite the value; on a barebones WP it was
  still a latent bug because core would always overwrite the origin
  on direct POSTs.) `CorsHandler::send_cors_headers` now removes the
  core hook for Apermo Stash routes when the origin matches the
  allow-list, and emits the complete CORS header set itself
  (`Access-Control-Allow-Origin`, `-Allow-Methods`, `-Allow-Headers`,
  `-Allow-Credentials`, `-Expose-Headers`, `Vary`). Other namespaces
  and disallowed origins still flow through core.
- Plugin Check `PluginCheck.Security.DirectDB.UnescapedDBParameter`
  warning on the `/tags` aggregate query in `TagsController`. The
  query was already correctly prepared (every interpolated value is
  a `$wpdb->`-prefixed table name or a constant `%s`/`%d` placeholder
  built in `build_where_clause`) and the WordPress.DB sniff variants
  were already suppressed; Plugin Check ships its own scanner under
  the `PluginCheck.*` namespace that doesn't inherit the existing
  ignore list, so the same false positive is now suppressed
  alongside the WordPress.DB ones.

## [0.1.2] - 2026-05-03

### Security

- Tighter output escaping in the bookmark list-table column headers
  (`src/Admin/ListColumns.php`) and the contextual help tabs
  (`src/Admin/HelpTabs.php`). Every translatable string that lands in
  HTML now goes through `esc_html__()` at the call site, and the two
  `sprintf`-builds-help-text spots that previously ran `wp_kses` on
  the translation template *before* interpolation now wrap the
  interpolated result, so the embedded `<a>` / `<code>` / `<strong>`
  fragments pass through the same allow-list. None of the values
  involved are user-supplied today, so no exploitable flaw existed in
  0.1.1, but the patterns were fragile and `WP_List_Table` writes
  column headers to the page raw — flagged in code review and fixed
  here as defense in depth.

## [0.1.1] - 2026-05-02

### Added

- **Favorite** flag on bookmarks (replaces Unread / Archived).
  Single boolean meta `_linkstash_favorite`; rendered as a star
  badge in the new "Favorite" list-table column; filterable via
  `GET /apermo-stash/v1/bookmarks?favorite=1`. The Add/Edit screen
  shows a single "Favorite" checkbox in the URL meta box.
- Starter tags created on first activation: `read-later`,
  `reference`, `inspiration`, `archive`. A one-shot
  `apermo_stash_starter_tags_seeded` option records that the seed has
  run, so subsequent (re-)activations are no-ops — tags the user
  deletes are never resurrected. The marker is cleared on uninstall
  so a fresh reinstall reseeds. Tags cover the categorisation use
  cases the dropped flags were trying to.
- "Are you sure you want to leave?" guard on the bookmark add/edit
  screen. Once any field changes, navigating away (closing the tab,
  hitting back, clicking a link) prompts the browser's native
  unsaved-changes dialog. Submitting the form clears the flag, so
  legitimate saves don't prompt.
- WordPress.org listing assets directory (`.wordpress-org/`) with
  documentation of the required image dimensions (icon-128 / icon-256,
  banner-772 / banner-1544, screenshot-N).
- Expanded `readme.txt` for the WordPress.org plugin directory —
  highlights, REST API summary, FAQ, screenshot captions, link to the
  companion Chrome extension.
- "Settings" link in the plugin row actions on the Plugins listing
  screen.
- Custom admin-menu icon: a monochromatic Apermo Stash logo
  (`assets/menu-icon.svg`) used as a CSS `mask-image` so the icon
  adopts the WordPress admin color scheme — grey-ish in idle state,
  the scheme's highlight on hover/active — instead of the brand's
  blue/orange. Replaces the previous `dashicons-admin-links` icon.
- Contextual help tabs on the bookmark list screen
  (`edit.php?post_type=apermo_stash_bookmark`): Overview (what each
  list column means), Adding bookmarks (Add New / dashboard widget /
  browser extension / REST API), and Browser extension (Chrome Web
  Store review status + install-from-source pointer + configuration
  walk-through). Plus a sidebar with quick links to the GitHub
  repos and the Settings page.

### Changed

- Settings page moved from **Tools → Apermo Stash** to **Settings →
  Apermo Stash** (`tools.php?page=linkstash` → `options-general.php?page=linkstash`).
  Existing tokens are unaffected; only the menu location and URL move.
- Bumped the `Version` plugin header, `Main::VERSION`, and `readme.txt`
  Stable tag to 0.1.1.

### Fixed

- Tags column on the bookmark list screen was empty for every row.
  The previous column key `tags` is reserved by core for the
  `post_tag` taxonomy; core's built-in handler claimed the cell and
  found nothing because the bookmark CPT doesn't have `post_tag`
  attached. Switched to a `apermo_stash_tag` column key with our own
  renderer that emits one anchor per tag pointing at the
  filter-by-tag URL — clicking a tag now narrows the list to that
  tag.

### Security

- `GET /apermo-stash/v1/check?url=` now requires the same `edit_posts`
  capability as the rest of the Apermo Stash REST surface, instead of
  allowing anonymous callers to probe whether a public bookmark
  exists. The companion Chrome extension already sends a Bearer
  token on every call, so this is transparent for it; ad-hoc
  unauthenticated callers will get a 403.
- `Permissions::can_read_bookmark` returns 404 (instead of 403)
  when the caller is not authorised to read a private bookmark, so
  the response is indistinguishable from "post does not exist" —
  preventing ID-enumeration of private bookmarks via the
  `GET /bookmarks/{id}` endpoint.
- README + readme.txt now document the single outbound HTTP request
  the plugin makes (the metadata fetch on save, via
  `wp_safe_remote_get`, which blocks loopback and private IP
  ranges) and how to narrow the CORS allow-list to a specific
  extension ID via the `apermo_stash_allowed_origins` filter.

### Removed

- **Unread** and **Archived** flags. `_linkstash_unread` and
  `_linkstash_archived` meta keys are gone, along with the REST
  `unread` / `archived` query/body fields and the multi-badge
  Flags column. Tags + the new starter set cover those use cases.
  v0.1.0 was never shipped to wp.org so there is no migration path
  — the previous keys are simply dropped.
- The redundant quick-add form on the bookmark list screen. The
  dashboard widget covers the same flow and is the single quick-add
  surface going forward. `TagAutocomplete` and `UrlAutoScheme` no
  longer enqueue on `edit.php` since their target inputs are gone
  there.

## [0.1.0] - 2026-05-01

### Added

- Custom post type `apermo_stash_bookmark` with REST exposure, custom non-hierarchical
  taxonomy `apermo_stash_tag`, and post meta for URL, canonical URL, unread, and
  archived flags.
- URL canonicalization helper (strips `utm_*`, `fbclid`, `gclid`, lowercases
  scheme and host, drops fragment, sorts remaining query parameters).
- URL metadata fetcher (`wp_safe_remote_get`, parses `<title>` and
  `<meta name="description">` / `og:description`, 5 s timeout, fail-soft).
- Bearer-token store backed by user meta (SHA-256 hashed) and a
  `determine_current_user` filter that authenticates `Authorization: Bearer`
  requests.
- REST namespace `apermo-stash/v1` with bookmark CRUD, idempotent create
  (returns existing record with `X-Apermo-Stash-Existing: 1` on duplicate URL),
  tag listing with counts, and `GET /check?url=` for browser-extension
  "already saved" badges.
- Public/private visibility enforcement on REST reads via WordPress's
  native `post_status` (`publish` versus `private`).
- CORS allow-list (default `chrome-extension://*`, extensible via the
  `apermo_stash_allowed_origins` filter) and `OPTIONS` preflight handling.
- Admin: bookmark list columns (URL, Tags, Visibility, Flags), quick-add
  form, and Tools → Apermo Stash settings page for token CRUD.
- `uninstall.php` clears plugin-owned data while preserving bookmarks.
