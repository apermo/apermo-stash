# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-05-02

### Added

- WordPress.org listing assets directory (`.wordpress-org/`) with
  documentation of the required image dimensions (icon-128 / icon-256,
  banner-772 / banner-1544, screenshot-N).
- Expanded `readme.txt` for the WordPress.org plugin directory —
  highlights, REST API summary, FAQ, screenshot captions, link to the
  companion Chrome extension.
- "Settings" link in the plugin row actions on the Plugins listing
  screen.

### Changed

- Settings page moved from **Tools → LinkStash** to **Settings →
  LinkStash** (`tools.php?page=linkstash` → `options-general.php?page=linkstash`).
  Existing tokens are unaffected; only the menu location and URL move.
- Bumped the `Version` plugin header, `Main::VERSION`, and `readme.txt`
  Stable tag to 0.1.1.

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
