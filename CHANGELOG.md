# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-05-02

### Added

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
- Custom admin-menu icon: a monochromatic LinkStash logo
  (`assets/menu-icon.svg`) used as a CSS `mask-image` so the icon
  adopts the WordPress admin color scheme — grey-ish in idle state,
  the scheme's highlight on hover/active — instead of the brand's
  blue/orange. Replaces the previous `dashicons-admin-links` icon.
- Contextual help tabs on the bookmark list screen
  (`edit.php?post_type=linkstash_bookmark`): Overview (what each
  list column means), Adding bookmarks (Add New / dashboard widget /
  browser extension / REST API), and Browser extension (Chrome Web
  Store review status + install-from-source pointer + configuration
  walk-through). Plus a sidebar with quick links to the GitHub
  repos and the Settings page.

### Changed

- Settings page moved from **Tools → LinkStash** to **Settings →
  LinkStash** (`tools.php?page=linkstash` → `options-general.php?page=linkstash`).
  Existing tokens are unaffected; only the menu location and URL move.
- Bumped the `Version` plugin header, `Main::VERSION`, and `readme.txt`
  Stable tag to 0.1.1.

### Fixed

- Tags column on the bookmark list screen was empty for every row.
  The previous column key `tags` is reserved by core for the
  `post_tag` taxonomy; core's built-in handler claimed the cell and
  found nothing because the bookmark CPT doesn't have `post_tag`
  attached. Switched to a `linkstash_tag` column key with our own
  renderer that emits one anchor per tag pointing at the
  filter-by-tag URL — clicking a tag now narrows the list to that
  tag.

### Removed

- The redundant quick-add form on the bookmark list screen. The
  dashboard widget covers the same flow and is the single quick-add
  surface going forward. `TagAutocomplete` and `UrlAutoScheme` no
  longer enqueue on `edit.php` since their target inputs are gone
  there.

## [0.1.0] - 2026-05-01

### Added

- Custom post type `linkstash_bookmark` with REST exposure, custom non-hierarchical
  taxonomy `linkstash_tag`, and post meta for URL, canonical URL, unread, and
  archived flags.
- URL canonicalization helper (strips `utm_*`, `fbclid`, `gclid`, lowercases
  scheme and host, drops fragment, sorts remaining query parameters).
- URL metadata fetcher (`wp_safe_remote_get`, parses `<title>` and
  `<meta name="description">` / `og:description`, 5 s timeout, fail-soft).
- Bearer-token store backed by user meta (SHA-256 hashed) and a
  `determine_current_user` filter that authenticates `Authorization: Bearer`
  requests.
- REST namespace `linkstash/v1` with bookmark CRUD, idempotent create
  (returns existing record with `X-LinkStash-Existing: 1` on duplicate URL),
  tag listing with counts, and `GET /check?url=` for browser-extension
  "already saved" badges.
- Public/private visibility enforcement on REST reads via WordPress's
  native `post_status` (`publish` versus `private`).
- CORS allow-list (default `chrome-extension://*`, extensible via the
  `linkstash_allowed_origins` filter) and `OPTIONS` preflight handling.
- Admin: bookmark list columns (URL, Tags, Visibility, Flags), quick-add
  form, and Tools → LinkStash settings page for token CRUD.
- `uninstall.php` clears plugin-owned data while preserving bookmarks.
