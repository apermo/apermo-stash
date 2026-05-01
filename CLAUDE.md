# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

GitHub template repository for bootstrapping WordPress plugins and themes. Ships both plugin and theme scaffolding; a `setup.sh` script lets developers choose their mode and configures the project accordingly.

**PHP 8.1+, WordPress 6.4+.** Strict types everywhere (`declare(strict_types=1)`).

## Architecture

### Dual-mode template (plugin + theme)

Both modes coexist in the repo. The `setup.sh` script (see #10) removes the irrelevant set after the developer picks a mode.

**Plugin mode files:** `plugin.php` (main file), `src/Main.php`, `uninstall.php`, `.github/workflows/plugin-check.yml` (dropped if WP.org publishing is declined)
**Theme mode files:** `style.css`, `functions.php`, `src/Theme.php`, `templates/`, `parts/`, `assets/`, `.github/workflows/lhci.yml`, `.lighthouserc.js`, `.wp-env.json`
**Shared:** `src/` (PSR-4 root), `tests/`, `e2e/` (incl. `helpers/a11y.js` + `a11y.spec.js`), `composer.json`, CI config, DDEV config

### Key conventions

- PSR-4 autoloading under `src/`
- Coding standards: `apermo/apermo-coding-standards` ^2.9 (PHPCS). Docblock
  summaries must be third-person singular (`Initializes the plugin`, not
  `Initialize the plugin`) — enforced by the 2.8+ summary sniff.
- Static analysis: `apermo/phpstan-wordpress-rules` + `szepeviktor/phpstan-wordpress`
- Testing: PHPUnit + Brain Monkey + Yoast PHPUnit Polyfills
- Test suites: `tests/Unit/` and `tests/Integration/`
- E2E + a11y: Playwright + `@axe-core/playwright` via `e2e/helpers/a11y.js`
  (WCAG 2.1 AA defaults). Sample `e2e/a11y.spec.js` ships as `test.skip` —
  unskip once the project has a surface worth auditing.

## Commands

```bash
composer cs              # Run PHPCS
composer cs:fix          # Fix PHPCS violations
composer analyse         # Run PHPStan
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:integration # Run integration tests only
npm run test:e2e         # Run Playwright E2E tests
npm run test:e2e:ui      # Run E2E tests with UI
```

## Local Development (DDEV)

```bash
ddev start && ddev orchestrate   # Full WordPress environment
```

- Uses `apermo/ddev-orchestrate` addon
- Project type is `php` (not `wordpress`), so WP-CLI uses a custom `ddev wp` command wrapper
- WordPress installs into `.ddev/wordpress/` subdirectory (keeps project root clean)
- `ddev-orchestrate` symlinks the project into the WP plugins/themes directory automatically

## Git Hooks

Pre-commit hook runs PHPCS (on staged files) and PHPStan (whole project) via
[husky](https://typicode.github.io/husky/) and [lint-staged](https://github.com/lint-staged/lint-staged).
It activates automatically on `npm install` via the `prepare` script.

## CI (GitHub Actions)

All workflows call reusables from `apermo/reusable-workflows`.

- `ci.yml` — PHPCS + PHPStan + PHPUnit across PHP 8.1, 8.2, 8.3, 8.4
- `integration.yml` — WP integration tests (real WP + MySQL, multisite matrix)
- `e2e.yml` — Playwright E2E tests (passes `a11y: true` for axe-core checks)
- `lhci.yml` — Lighthouse CI (theme mode only; a11y ≥ 90, performance ≥ 80)
- `plugin-check.yml` — WordPress Plugin Check via `wordpress/plugin-check-action`
  (plugin mode + WP.org publishing only; guarded with
  `if: github.repository != 'apermo/template-wordpress'` so the template repo itself doesn't fail)
- `wp-beta.yml` — Nightly WP beta/RC compatibility check
- `release.yml` — CHANGELOG-driven releases
- `pr-validation.yml` — conventional commit and changelog checks

### Integration test environment

Integration tests run against a real WordPress instance. The bootstrap auto-detects
`vendor/wp-phpunit/wp-phpunit` when `WP_TESTS_DIR` is unset. For local development:

```bash
composer require --dev wp-phpunit/wp-phpunit
cp wp-tests-config.php.dist wp-tests-config.php  # edit DB credentials
composer test:integration
```

You can also set `WP_TESTS_DIR` explicitly:

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib WP_MULTISITE=1 composer test:integration
```

When neither `WP_TESTS_DIR` nor `vendor/wp-phpunit/wp-phpunit` exist, the bootstrap
skips WP loading — unit tests work unchanged.

### E2E test environment

E2E tests use Playwright against a running WordPress instance (DDEV locally, wp-env in CI):

```bash
npm ci
npx playwright install --with-deps chromium
npm run test:e2e
```

The `WP_BASE_URL` env var overrides the default DDEV site URL. Authentication
is handled by `e2e/auth.setup.js` which stores state in `.auth/admin.json`.

Accessibility assertions: use `expectNoA11yViolations(page)` from
`e2e/helpers/a11y.js`. It runs axe-core against WCAG 2.1 A/AA tags and
formats violations for readable failure output. `ignoreHTTPSErrors: true`
is set in `playwright.config.js` to tolerate DDEV's self-signed cert.

## Template Sync (for derived projects)

```bash
git remote add template https://github.com/apermo/template-wordpress.git
git fetch template
git checkout -b chore/sync-template
git merge template/main --allow-unrelated-histories
```

## Post-setup checklist (for derived projects)

After running `setup.sh` on a new project derived from this template, remind the user to:

- Add the `CODECOV_TOKEN` repository secret (Settings > Secrets > Actions) for code coverage reporting

## Placeholder conventions

The setup script replaces these across all files:
- `plugin-name` → slug (kebab-case)
- `Plugin_Name` → PascalCase
- `PLUGIN_NAME` → UPPER_SNAKE_CASE
- `plugin_name` → snake_case
- Placeholder namespace → chosen namespace
