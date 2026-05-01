# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.0] - 2026-04-20

### Changed

- **BREAKING:** Plugin mode main class renamed from `Plugin` to `Main`
  (`src/Plugin.php` → `src/Main.php`). Resolves the confusing collision
  with the root `plugin.php` bootstrap file. Derived projects extending
  or referencing the `Plugin` class must rename usages to `Main` (or
  keep their own class name and update the `plugin.php` bootstrap call
  accordingly). Template files updated: `plugin.php`, `setup.sh`,
  `tests/Unit/MainTest.php`, `tests/Integration/ExampleIntegrationTest.php`,
  `CLAUDE.md`. See [#39](https://github.com/apermo/template-wordpress/issues/39).

## [0.7.0] - 2026-04-20

### Added

- Accessibility testing scaffolding: `@axe-core/playwright` dependency, shared
  `e2e/helpers/a11y.js` helper with WCAG 2.1 AA defaults, and `e2e/a11y.spec.js`
  sample spec. E2E workflow now passes `a11y: true` to the reusable workflow.
- Theme mode: Lighthouse CI workflow (`.github/workflows/lhci.yml`), starter
  `.lighthouserc.js` (a11y ≥ 90, performance ≥ 80), and minimal `.wp-env.json`
- Plugin mode: Plugin Check workflow calling
  [`apermo/reusable-workflows/reusable-plugin-check.yml`](https://github.com/apermo/reusable-workflows/releases/tag/v0.5.0)
  (wraps [`wordpress/plugin-check-action`](https://github.com/WordPress/plugin-check-action))
  to enforce WP.org directory policy. Kept only when WP.org publishing is
  enabled during `setup.sh`; removed in theme mode or when opting out of WP.org.

### Changed

- Pre-commit hook now managed by [husky](https://typicode.github.io/husky/) and
  [lint-staged](https://github.com/lint-staged/lint-staged). Activates automatically
  on `npm install` — no manual `git config core.hooksPath` step required.
- Upgrade `apermo/apermo-coding-standards` to `^2.9` (2.8.0 added a docblock
  summary sniff enforcing third-person singular per WordPress style). All
  template docblock summaries rewritten to conform.
- Minimum WordPress version bumped from 6.2 to 6.4 (required by `wp_admin_notice()`)
- Composer install notice now uses the native `wp_admin_notice()` function
  (WP 6.4+) instead of hand-rolled markup

### Removed

- `.githooks/` directory (replaced by `.husky/`)

### Fixed

- `setup.sh` branch ruleset used outdated `Check CHANGELOG Entry` /
  `Check Commit Message Format` check names — updated to `pr-validation / validate`
  and `conventional-commits / validate` to match the renamed jobs in
  `apermo/reusable-workflows` v0.4.0+
- `ddev orchestrate` failing with "unknown command" on fresh clone: pre-start hook
  now auto-installs the `apermo/ddev-orchestrate` addon
- Playwright E2E tests failing with `ERR_CERT_AUTHORITY_INVALID` against DDEV's
  self-signed HTTPS certificate (`ignoreHTTPSErrors: true`)

## [0.6.1] - 2026-04-05

### Fixed

- `composer.lock` no longer gitignored — WordPress plugins are distributed as-is
- `setup.sh` branch ruleset using bare job names instead of workflow-prefixed check names

## [0.6.0] - 2026-04-05

### Changed

- DDEV docroot moved to `.ddev/wordpress/` subdirectory to keep project root clean
- Removed `docker-compose.plugin.yaml.dist` and `docker-compose.theme.yaml.dist` mount files

### Fixed

- WordPress core files polluting project root when using `ddev-orchestrate`

## [0.5.0] - 2026-04-05

### Added

- Codecov configuration (`codecov.yml`) for derived projects
- Graceful admin notice when `vendor/autoload.php` is missing
- Post-setup reminder for `CODECOV_TOKEN` in `setup.sh` and `CLAUDE.md`
- `package-lock.json` committed for reliable E2E CI runs

### Fixed

- PHPCS scanning `.ddev/` directory causing hangs
- Pre-commit hook passing staged file paths to PHPStan
- `CHANGELOG.md` template entries persisting into derived projects

## [0.4.0] - 2026-03-15

### Added

- `Requires at least` header in `plugin.php` and `style.css`
- Integration test matrix auto-detects minimum WP version from plugin/theme header

### Changed

- Upgrade `apermo/apermo-coding-standards` to 2.6.1
- Configure `text_domain`, `prefixes`, and `minimum_wp_version` in `phpcs.xml.dist`

## [0.3.0] - 2026-03-15

### Changed

- Upgrade `apermo/apermo-coding-standards` to 2.6.0
- Fully qualify global functions and constants in namespaced code

## [0.2.0] - 2026-03-15

### Added

- Plugin lifecycle methods: `activate()`, `deactivate()`, `boot()`
- GitHub issue templates (bug report, feature request)
- GitHub pull request template
- Repository marked as GitHub template

### Changed

- Standardize plugin entry file to `plugin.php`
- Replace global constants with class members in Plugin class

## [0.1.0] - 2026-03-15

### Added

- Initial project setup
- Optional WordPress.org SVN deploy workflow
- WordPress integration test infrastructure with multisite matrix
- `wp-tests-config.php.dist` for CI test suite configuration
- WP beta/RC nightly compatibility workflow
- Playwright E2E test infrastructure with auth setup and example spec
- E2E caller workflow (`e2e.yml`)
- `WP_DB_IMPORT` support in `.ddev/.env` for database dump import

### Changed

- Integration test bootstrap auto-detects `vendor/wp-phpunit/wp-phpunit`

### Fixed

- Workflow callers missing permissions (caused startup_failure)

[0.8.0]: https://github.com/apermo/template-wordpress/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/apermo/template-wordpress/compare/v0.6.1...v0.7.0
[0.6.1]: https://github.com/apermo/template-wordpress/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/apermo/template-wordpress/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/apermo/template-wordpress/compare/v0.4.1...v0.5.0
[0.4.0]: https://github.com/apermo/template-wordpress/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/apermo/template-wordpress/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/apermo/template-wordpress/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/apermo/template-wordpress/releases/tag/v0.1.0
