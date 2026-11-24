---
kind: build_system
name: 'Build System: Vite + Composer + PHPUnit for a WordPress Plugin'
category: build_system
scope:
    - '**'
source_files:
    - package.json
    - vite.config.js
    - tailwind.config.js
    - tools/verify-css-isolation.mjs
    - composer.json
    - phpunit.xml
    - phpunit-watcher.yml
    - bin/lint-php.sh
    - bin/lint-sh.sh
    - bin/guard-sstk-prefix.sh
---

## Overview

The Simple Stack plugin is built with three independent toolchains that are wired together through `package.json` and `composer.json` scripts:

- **Vite** (React + Tailwind v4) builds the Simple Stack admin dashboard under `src/admin/` into `assets/dist/`.
- **Composer** manages PHP runtime dependencies (`phpseclib`) and dev/test tooling (PHPUnit, WP-CLI test suite, PHPCS).
- **Bash helpers** in `bin/` provide linting, syntax checks, and policy guards; `tools/verify-css-isolation.mjs` enforces CSS scoping at build time.

There is no CI pipeline file in `.github/` (only issue templates and funding config), so the build is intended to be run locally or by an external orchestrator. The repository ships pre-built assets in `assets/dist/` (see `vite.config.js` comment: "output lands in assets/dist/ and is committed, so a production install needs no Node toolchain").

## Key Files

| File | Role |
|---|---|
| `package.json` | Node entry point — defines `dev`, `build`, `verify`, `verify:css` scripts and React/Tailwind/Vite deps |
| `vite.config.js` | Single-entry Vite build targeting `es2020`, outputting hashed files to `assets/dist/` with a manifest |
| `tailwind.config.js` | Tailwind v4 config scoped via `important: '#simplestack-admin'` and `corePlugins.preflight: false` |
| `tools/verify-css-isolation.mjs` | Build-time assertion that every generated utility selector is anchored on `#simplestack-admin` and no document-root selectors leak |
| `composer.json` | Declares PHP >= 8.1, `phpseclib/phpseclib`, and dev deps including `johnpbloch/wordpress`, `wp-phpunit/wp-phpunit`, `spatie/phpunit-watcher`, WPCS |
| `phpunit.xml` | PHPUnit 9 config bootstrapping from `tests/phpunit.php`, excludes `integration` group, covers only `includes/core/apps/wordpress-app/rest-api` |
| `phpunit-watcher.yml` | Watches `includes/core` and `tests/phpunit` for live test runs |
| `bin/lint-php.sh` | Runs `php -l` over all `.php` files, pruning `node_modules`, `vendor`, `assets/dist` |
| `bin/lint-sh.sh` | Syntax-checks provisioning scripts (`bash -n`) plus wrapper templates after `##PLACEHOLDER##` substitution; shellcheck runs advisory-only |
| `bin/guard-sstk-prefix.sh` | Policy guard enforcing legacy `sstk_` identifiers, forbidding removed layers, and constraining public endpoints |

## Architecture & Conventions

### Frontend build (Vite)

- Single entry point: `src/admin/index.jsx`.
- Output layout mirrors the existing asset tree: `assets/[name]-[hash].js`, `assets/[name]-[hash][extname]`.
- `cssCodeSplit: false` forces one stylesheet; `manifest: true` emits `.vite/manifest.json` which the PHP enqueue layer reads.
- Source maps disabled in production builds.
- Tailwind v4 is used via `@tailwindcss/vite`; React is bundled rather than borrowed from `@wordpress/element` so the Tailwind pipeline stays reproducible (documented in `vite.config.js`).
- The `verify` npm script runs both `npm run build` and `npm run verify:css`, making CSS isolation part of the standard build.

### CSS isolation enforcement

`tools/verify-css-isolation.mjs` performs five assertions against the compiled CSS:

1. No top-level `html`, `body`, `*`, `:root`, `::selection` selectors with real declarations.
2. Every rule inside `@layer utilities` starts with `#simplestack-admin`.
3. At least one scoped utility exists (prevents a silent empty Tailwind run).
4. No unprocessed `@tailwind` / `@config` / `@import "tailwindcss"` residue.
5. The Vite manifest exists and lists a stylesheet.

This is the primary mechanism preventing the Simple Stack dashboard from leaking styles into wp-admin's chrome.

### PHP dependency management

- Runtime dependency: `phpseclib/phpseclib ^3.0`.
- Dev dependencies include the full WordPress test bootstrap (`johnpbloch/wordpress ^6.7`, `wp-phpunit/wp-phpunit ^6.7`) installed under `vendor/wordpress/wordpress` via `extra.wordpress-install-dir`.
- Coding standards enforced via `wp-coding-standards/wpcs ^3.0` with `dealerdirect/phpcodesniffer-composer-installer`.
- Composer scripts expose `lint`, `lint:sh`, `guard`, and `test`.

### Test setup

- PHPUnit 9 configuration lives in `phpunit.xml` with `bootstrap=./tests/phpunit.php`.
- Tests live under `tests/phpunit/` (REST API controllers) plus data fixtures under `tests/data/`.
- `phpunit-watcher.yml` watches `includes/core` and `tests/phpunit` for live reruns via `spatie/phpunit-watcher`.
- Coverage is limited to `includes/core/apps/wordpress-app/rest-api`.
- The `integration` group is excluded by default.

### Shell-script provisioning pipeline

Provisioning commands ship as `.txt` files under `includes/core/apps/wordpress-app/scripts/v2/`. Raw scripts are Bash; wrapper templates carry `##PLACEHOLDER##` tokens that are substituted at runtime. `bin/lint-sh.sh` syntax-checks raw scripts, rendered wrappers and gate tools, excluding known non-shell snippets. Provisioning is fixed to `v2`; no `v1` tree or fallback exists. `composer test:uninstall` runs the standalone multisite cleanup harness without MySQL.

## Conventions & Constraints

- **Prebuilt artifacts**: The Vite output in `assets/dist/` is committed; production installs do not require Node. This is stated explicitly in `vite.config.js`.
- **CSS must stay scoped**: All Tailwind utilities are emitted under `#simplestack-admin` (`tailwind.config.js` `important`); `corePlugins.preflight` is disabled. Violations fail the build via `tools/verify-css-isolation.mjs`.
- **PHP minimum version**: `>=8.1` is declared in `composer.json`.
- **Legacy identifier guard**: `bin/guard-sstk-prefix.sh` asserts that CPT slugs `sstk_app_server` and `sstk_app`, constants `SSTK_PATH`/`SSTK_URL`, and classes `SSTK_SERVER`, `SSTK_WORDPRESS_APP`, `SSTK_SETTINGS` still exist in `includes/core`; it also forbids `SSTK_License`, `wisdom_`, `SSTK_WOOCOMMERCE`, and `SSTK_DATA_SYNC_REST`.
- **Public endpoint whitelist**: Only `includes/core/apps/class-sstk-app.php` and `includes/core/apps/wordpress-app/tabs/git-control-site.php` may use `__return_true` as a permission callback outside the new REST layer; any other occurrence fails the guard.
- **Port 7080 policy**: public `ufw allow 7080` rules are forbidden; only validated trusted-IP rules are allowed. This is enforced by the same guard script.
- **Shell lint is advisory for style, hard for syntax**: `shellcheck` runs in advisory mode (`-S warning`) and does not fail the gate; `bash -n` syntax checks do fail.
- **Test exclusion**: The `integration` group is excluded from PHPUnit by default (`phpunit.xml` `<groups>`).