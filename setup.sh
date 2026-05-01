#!/usr/bin/env bash

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Portable sed in-place (macOS BSD sed vs GNU sed)
sedi() {
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "$@"
    else
        sed -i "$@"
    fi
}

# --- Prompts ---

echo ""
echo "=== WordPress Template Setup ==="
echo ""

# Slug (kebab-case)
read -rp "Project slug (kebab-case, e.g. my-plugin): " SLUG
[ -z "$SLUG" ] && error "Slug is required."
[[ "$SLUG" =~ ^[a-z0-9]+(-[a-z0-9]+)*$ ]] || error "Slug must be kebab-case (e.g. my-plugin)."

# Namespace
read -rp "PHP namespace (e.g. Apermo\\MyPlugin): " NAMESPACE
[ -z "$NAMESPACE" ] && error "Namespace is required."

# Composer package name
DEFAULT_COMPOSER="apermo/${SLUG}"
read -rp "Composer package name [${DEFAULT_COMPOSER}]: " COMPOSER_NAME
COMPOSER_NAME="${COMPOSER_NAME:-$DEFAULT_COMPOSER}"

# Mode
PS3="Select project mode: "
select PROJECT_MODE in "plugin" "theme"; do
    [ -n "$PROJECT_MODE" ] && break
done

# WordPress.org publishing
read -rp "Publish to WordPress.org? (y/N): " WPORG_PUBLISH
echo ""

# --- Derive placeholder values ---

# PascalCase with underscores: my-plugin → My_Plugin
PASCAL_UNDER=$(echo "$SLUG" | awk -F'-' '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) substr($i,2)} 1' OFS='_')

# UPPER_SNAKE_CASE: My_Plugin → MY_PLUGIN
UPPER_SNAKE=$(echo "$PASCAL_UNDER" | tr '[:lower:]' '[:upper:]')

# snake_case: My_Plugin → my_plugin
SNAKE_CASE=$(echo "$PASCAL_UNDER" | tr '[:upper:]' '[:lower:]')

# Namespace escaped for sed
NAMESPACE_SED=$(echo "$NAMESPACE" | sed 's/\\/\\\\/g')

# Namespace for JSON (\\)
NAMESPACE_JSON=$(echo "$NAMESPACE" | sed 's/\\/\\\\\\\\/g')

info "Configuration:"
echo "  Slug:        ${SLUG}"
echo "  PascalCase:  ${PASCAL_UNDER}"
echo "  UPPER_SNAKE: ${UPPER_SNAKE}"
echo "  snake_case:  ${SNAKE_CASE}"
echo "  Namespace:   ${NAMESPACE}"
echo "  Composer:    ${COMPOSER_NAME}"
echo "  Mode:        ${PROJECT_MODE}"
echo ""

# --- Replace placeholders ---

info "Replacing placeholders..."

FIND_COMMON=(-type f -not -path './.git/*' -not -path './setup.sh' -not -path './vendor/*' -not -path './node_modules/*')

replace_in_files() {
    local pattern="$1"
    local value="$2"
    shift 2

    find . "${FIND_COMMON[@]}" "$@" -print0 | while IFS= read -r -d '' file; do
        sedi "s|${pattern}|${value}|g" "$file"
    done
}

# 1. Namespace in PHP files (before general Plugin_Name replacement)
replace_in_files "namespace Plugin_Name" "namespace ${NAMESPACE_SED}" -name '*.php'
replace_in_files "use Plugin_Name\\\\" "use ${NAMESPACE_SED}\\\\" -name '*.php'

# 2. Namespace in composer.json
sedi "s|Plugin_Name\\\\\\\\|${NAMESPACE_JSON}\\\\\\\\|g" composer.json

# 3. Namespace prefix in phpcs.xml.dist (must use full namespace, not PascalCase)
sedi "s|<element value=\"Plugin_Name\"/>|<element value=\"${NAMESPACE_SED}\"/>|" phpcs.xml.dist

# 4. Composer package name
sedi "s|apermo/plugin-name|${COMPOSER_NAME}|g" composer.json

# 5. Bulk placeholder replacements (all files)
replace_in_files "PLUGIN_NAME" "$UPPER_SNAKE"
replace_in_files "Plugin_Name" "$PASCAL_UNDER"
replace_in_files "plugin_name" "$SNAKE_CASE"
replace_in_files "plugin-name" "$SLUG"

# --- Mode cleanup ---

info "Configuring ${PROJECT_MODE} mode..."

if [ "$PROJECT_MODE" = "plugin" ]; then
    # Remove theme files
    rm -f style.css functions.php theme.json
    rm -f src/Theme.php
    rm -rf templates/ parts/ assets/

    # Remove theme-only Lighthouse CI scaffolding
    rm -f .github/workflows/lhci.yml .lighthouserc.js .wp-env.json

    # Clean phpstan.neon.dist
    sedi '/- functions.php/d' phpstan.neon.dist
else
    # Remove plugin files
    rm -f plugin.php uninstall.php
    rm -f src/Main.php
    rm -f tests/Unit/MainTest.php

    # Remove plugin-only Plugin Check workflow
    rm -f .github/workflows/plugin-check.yml

    # Update composer.json type
    sedi 's|"wordpress-plugin"|"wordpress-theme"|' composer.json

    # Update DDEV .env
    sedi 's|PROJECT_MODE=plugin|PROJECT_MODE=theme|' .ddev/.env

    # Clean phpstan.neon.dist
    sedi '/- plugin.php/d' phpstan.neon.dist
    sedi '/- uninstall.php/d' phpstan.neon.dist

    # Update readme.txt for theme mode
    sedi 's|A WordPress plugin|A WordPress block theme|g' readme.txt
    sedi "s|/wp-content/plugins/${SLUG}/|/wp-content/themes/${SLUG}/|" readme.txt
fi

# --- WordPress.org publishing ---

if [[ ! "$WPORG_PUBLISH" =~ ^[Yy]$ ]]; then
    info "Removing WordPress.org deploy files..."
    rm -f .github/workflows/wporg-deploy.yml
    rm -f .github/workflows/plugin-check.yml
    rm -f readme.txt
    rm -rf .wordpress-org/
else
    info "WordPress.org deploy workflow retained."
    info "Add SVN credentials as repository secrets:"
    echo "  WPORG_SVN_USERNAME"
    echo "  WPORG_SVN_PASSWORD"
    echo ""
fi

# --- Configure repository via gh CLI ---

if command -v gh &>/dev/null; then
    read -rp "Configure GitHub repository? (y/N): " CONFIGURE_GH
    if [[ "$CONFIGURE_GH" =~ ^[Yy]$ ]]; then
        OWNER_REPO=$(gh repo view --json nameWithOwner -q '.nameWithOwner' 2>/dev/null || echo "")

        if [ -z "$OWNER_REPO" ]; then
            warn "Could not detect repository. Skipping GitHub configuration."
        else
            info "Configuring ${OWNER_REPO}..."

            gh repo edit "$OWNER_REPO" \
                --delete-branch-on-merge \
                --enable-wiki=false \
                --enable-projects=false 2>/dev/null || warn "Could not update repo settings."

            info "Removing default labels..."
            for label in $(gh label list --repo "$OWNER_REPO" --json name -q '.[].name' 2>/dev/null); do
                gh label delete "$label" --repo "$OWNER_REPO" --yes 2>/dev/null || true
            done

            info "Creating standard labels..."
            gh label create "type: bug"        --color "D73A4A" --description "Something isn't working" --repo "$OWNER_REPO" 2>/dev/null || true
            gh label create "type: feature"    --color "0E8A16" --description "New functionality" --repo "$OWNER_REPO" 2>/dev/null || true
            gh label create "type: docs"       --color "0075CA" --description "Documentation" --repo "$OWNER_REPO" 2>/dev/null || true
            gh label create "type: chore"      --color "BFD4F2" --description "Maintenance and cleanup" --repo "$OWNER_REPO" 2>/dev/null || true
            gh label create "priority: high"   --color "B60205" --description "Must have" --repo "$OWNER_REPO" 2>/dev/null || true
            gh label create "priority: medium" --color "FBCA04" --description "Should have" --repo "$OWNER_REPO" 2>/dev/null || true
            gh label create "priority: low"    --color "C5DEF5" --description "Nice to have" --repo "$OWNER_REPO" 2>/dev/null || true
            gh label create "dependencies"     --color "0366D6" --description "Dependency updates" --repo "$OWNER_REPO" 2>/dev/null || true

            info "Creating branch ruleset..."
            REQUIRED_CHECKS='[{"context":"pr-validation / validate"},{"context":"conventional-commits / validate"},{"context":"ci / PHPStan"},{"context":"ci / Coding Standards"}]'

            gh api "repos/${OWNER_REPO}/rulesets" --method POST --input - <<RULESET_EOF || warn "Could not create ruleset."
{
    "name": "Protect main",
    "target": "branch",
    "enforcement": "active",
    "bypass_actors": [
        {
            "actor_id": 5,
            "actor_type": "RepositoryRole",
            "bypass_mode": "always"
        }
    ],
    "conditions": {
        "ref_name": {
            "include": ["refs/heads/main"],
            "exclude": []
        }
    },
    "rules": [
        {"type": "deletion"},
        {"type": "non_fast_forward"},
        {
            "type": "pull_request",
            "parameters": {
                "required_approving_review_count": 0,
                "dismiss_stale_reviews_on_push": false,
                "require_code_owner_review": false,
                "require_last_push_approval": false,
                "required_review_thread_resolution": false
            }
        },
        {
            "type": "required_status_checks",
            "parameters": {
                "strict_required_status_checks_policy": false,
                "required_status_checks": ${REQUIRED_CHECKS}
            }
        }
    ]
}
RULESET_EOF
        fi
    fi
fi

# --- Git hooks ---

info "Git hooks will activate automatically after 'npm install' (via husky)."

# --- Verify no placeholders remain ---

info "Verifying no placeholders remain..."
REMAINING=$(grep -r 'plugin-name\|Plugin_Name\|PLUGIN_NAME\|plugin_name' . \
    --include='*.php' --include='*.json' --include='*.xml' --include='*.neon' \
    --include='*.css' --include='*.yaml' --include='*.yml' --include='*.html' --include='*.txt' \
    -l 2>/dev/null | grep -v '.git/' | grep -v 'setup.sh' || true)

if [ -n "$REMAINING" ]; then
    warn "Placeholders may remain in: $REMAINING"
else
    info "No remaining placeholders found."
fi

# --- Reset CHANGELOG ---

info "Resetting CHANGELOG.md..."
cat > CHANGELOG.md <<'CHANGELOG_EOF'
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial project setup
CHANGELOG_EOF

# --- Clean up ---

info "Removing setup script..."
rm -- "$0"

echo ""
info "Setup complete! Next steps:"
echo ""
echo "  1. Review the changes"
echo "  2. Run: composer install"
echo "  3. Run: npm install"
echo "  4. Run: git add -A && git commit -m 'feat: initial project setup'"
echo "  5. Run: ddev start && ddev orchestrate"
echo "  6. Add CODECOV_TOKEN secret (Settings > Secrets > Actions)"
echo ""
