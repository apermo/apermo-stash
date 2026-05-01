# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
