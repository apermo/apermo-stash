# Apermo Stash

[![PHP CI](https://github.com/apermo/apermo-stash/actions/workflows/ci.yml/badge.svg)](https://github.com/apermo/apermo-stash/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](LICENSE)

A self-hosted WordPress plugin for collecting links. Inspired by
[linkding](https://linkding.link/). Stores URL + title + notes + tags as a
custom post type and exposes a token-protected REST API so a browser extension
can save links from anywhere.

Per-link public/private visibility, idempotent save (safe to re-submit),
and CORS configured for `chrome-extension://*` origins out of the box.

## Requirements

- PHP 8.1+
- WordPress 6.4+
- Composer (development only — runtime has no Composer dependencies)
- Node.js 20+ and npm (activates husky pre-commit hook, runs Playwright)
- [DDEV](https://ddev.readthedocs.io/) (for local development)

## Installation

1. Clone or download this repository into `wp-content/plugins/apermo-stash/`.
2. Run `composer install --no-dev` to generate the autoloader.
3. Activate the plugin through the WordPress "Plugins" screen.
4. Visit **Settings → Apermo Stash** to generate an API token (see Authentication
   below).

## Authentication

Apermo Stash accepts two equivalent authentication schemes; pick whichever fits
your client.

### WordPress Application Passwords (Basic Auth)

Available in WordPress core. Generate one under **Users → Profile → Application
Passwords** and pass it as Basic Auth:

```bash
curl -u "your-username:xxxx xxxx xxxx xxxx xxxx xxxx" \
     https://example.tld/wp-json/apermo-stash/v1/links
```

### Apermo Stash Bearer Tokens

Better suited for browser extensions: generate at **Settings → Apermo Stash → API
Tokens**. The plain token is shown **once** at creation time — copy it
immediately. Send it as:

```bash
curl -H "Authorization: Bearer <token>" \
     https://example.tld/wp-json/apermo-stash/v1/links
```

Each token is bound to a WordPress user; permission checks run against that
user's capabilities (`edit_posts` for write endpoints).

## REST API

Base path: `/wp-json/apermo-stash/v1`.

| Method | Path | Description |
|---|---|---|
| `GET` | `/links` | List links (filters: `tag`, `q`, `unread`, `archived`, `public`/`private`, `page`, `per_page`) |
| `POST` | `/links` | Create a link (idempotent — same URL returns existing record with `X-Apermo-Stash-Existing: 1`) |
| `GET` | `/links/{id}` | Fetch a single link |
| `PATCH` | `/links/{id}` | Update fields |
| `DELETE` | `/links/{id}` | Delete a link |
| `GET` | `/tags` | List tags with link counts |
| `GET` | `/check?url=...` | Returns `{exists: bool, id?: int}` for a given URL |

### Examples

Save a link; let the server fetch the title and description:

```bash
curl -X POST https://example.tld/wp-json/apermo-stash/v1/links \
     -H "Authorization: Bearer <token>" \
     -H "Content-Type: application/json" \
     -d '{"url":"https://example.tld/article","tags":["reading"],"public":true}'
```

Check whether a URL is already saved (browser-extension "already saved" badge):

```bash
curl -H "Authorization: Bearer <token>" \
     "https://example.tld/wp-json/apermo-stash/v1/check?url=https://example.tld/article"
```

Search and filter:

```bash
curl -H "Authorization: Bearer <token>" \
     "https://example.tld/wp-json/apermo-stash/v1/links?tag=reading&unread=1"
```

### Public versus private links

Links use WordPress's native `post_status`:

- `publish` (public) — readable without authentication via the REST API.
- `private` — only the owner (and users with `edit_others_posts`) can read.

Anonymous `GET /links` returns only public links. Authenticated users
see their own links plus any public links owned by other users. POST,
PATCH, DELETE always require authentication.

### CORS

By default Apermo Stash sends CORS headers permitting `chrome-extension://*`
origins. Add additional origins via the `apermo_stash_allowed_origins` filter:

```php
add_filter( 'apermo_stash_allowed_origins', static function ( array $origins ): array {
    $origins[] = 'https://my-frontend.example.tld';
    return $origins;
} );
```

To **narrow** the default allow-list once you know your extension's
specific ID — defense-in-depth on top of the Bearer requirement —
return only that origin:

```php
add_filter( 'apermo_stash_allowed_origins', static function (): array {
    return [ 'chrome-extension://abcdefghijklmnopqrstuvwxyzabcdef' ];
} );
```

### Outbound HTTP

Apermo Stash makes one outbound HTTP request per saved link — to
the saved URL itself, via `wp_safe_remote_get` (5 s timeout, up
to three redirects, all re-validated). The fetched body is parsed
for `<title>` and `<meta name="description" / og:description>`; on
failure the link still saves and an "unreachable" warning is
shown on next edit. `wp_safe_remote_get` blocks loopback and private
IP ranges, so a hostile URL can't be used to probe internal services.

No third-party services are contacted. No analytics, no telemetry.
The companion Chrome extension talks only to the host you configure
on its options page.

## Development

```bash
composer install
npm install               # activates husky pre-commit hook
composer cs               # PHPCS
composer cs:fix           # PHPCBF
composer analyse          # PHPStan
composer test:unit        # unit tests (Brain Monkey)
composer test:integration # integration tests (wp-phpunit)
npm run test:e2e          # Playwright E2E
```

### Local WordPress environment

```bash
ddev start && ddev orchestrate
```

## License

[GPL-2.0-or-later](LICENSE)
