---
kind: dependency_management
name: 'Multi-layer Dependency Management: Composer + npm/Vite + Bundled WordPress Plugins'
category: dependency_management
scope:
    - '**'
source_files:
    - composer.json
    - composer.lock
    - package.json
    - package-lock.json
    - vite.config.js
    - tools/verify-css-isolation.mjs
    - required_plugins/wpcd-required-plugins.php
    - required_plugins/class-tgm-plugin-activation.php
    - .gitignore
---

## Overview

SimpleStack uses three distinct dependency-management layers for its WordPress plugin:

1. **PHP runtime & dev tooling** — managed via **Composer** (`composer.json`, `composer.lock`).
2. **Frontend build (React admin dashboard)** — managed via **npm** (`package.json`, `package-lock.json`) and built with **Vite** (`vite.config.js`), producing committed artifacts under `assets/dist/`.
3. **WordPress plugin dependencies** — delivered as **vendored source** under `required_plugins/` plus a TGMPA registration layer that can auto-install the core Meta Box plugin from the WordPress.org repository.

There is no PHP autoloader configuration beyond Composer defaults; the root `vendor/` directory is intentionally empty on this branch (test scaffolding installs into `vendor/wordpress/` per `.gitignore`).

## Layer 1 — PHP Dependencies (Composer)

- Manifest: `composer.json`
- Lockfile: `composer.lock`
- Runtime requirement: only `php >=8.1` and `phpseclib/phpseclib ^3.0`.
- Dev requirements include PHPUnit, WP-CLI test scaffolding (`johnpbloch/wordpress`), coding standards (`wp-coding-standards/wpcs`), and the PHPCS installer.
- The `extra.wordpress-install-dir` points to `vendor/wordpress/wordpress`, and `.gitignore` excludes `vendor/wordpress/` and `vendor/bin/` so the test WordPress install is not committed.
- Plugin execution is restricted via `config.allow-plugins`: only `dealerdirect/phpcodesniffer-composer-installer` and `johnpbloch/wordpress-core-installer` are whitelisted.
- Scripts expose `lint`, `lint:sh`, `guard`, and `test` commands that wrap shell scripts under `bin/`.

No private registry or `GOPRIVATE`-style configuration exists; all packages resolve from Packagist.

## Layer 2 — Frontend Dependencies (npm + Vite)

- Manifest: `package.json`
- Lockfile: `package-lock.json`
- Production dependencies: `react ^19.0.0`, `react-dom ^19.0.0`, `@tanstack/react-query ^5.62.7`.
- Dev dependencies: `vite ^7.0.0`, `@vitejs/plugin-react ^4.3.4`, `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`.
- Build output goes to `assets/dist/` (see `vite.config.js` `build.outDir`), with hashed filenames (`assets/[name]-[hash].js`, `assets/[name]-[hash][extname]`) and a manifest file (`manifest: true`).
- CSS is bundled as a single stylesheet (`cssCodeSplit: false`) because Tailwind v4 publishes it as a standalone chunk.
- `node_modules/` is gitignored; the built artifacts under `assets/dist/` are committed instead, so production installations need no Node toolchain.
- A custom script `tools/verify-css-isolation.mjs` runs after `npm run build` to assert CSS isolation across chunks.

The Vite config explicitly bundles React rather than relying on `@wordpress/element` so the Tailwind v4 pipeline stays reproducible (documented in the comment at the top of `vite.config.js`).

## Layer 3 — WordPress Plugin Dependencies (Bundling + TGMPA)

### Vendored add-ons

Under `required_plugins/` the repo ships full source trees for multiple Meta Box ecosystem add-ons:

- `mb-admin-columns/`
- `mb-custom-table/`
- `mb-settings-page/`
- `mb-term-meta/`
- `mb-user-meta/`
- `meta-box-columns/`
- `meta-box-conditional-logic/`
- `meta-box-group/`
- `meta-box-include-exclude/`
- `meta-box-show-hide/`
- `meta-box-tabs/`
- `meta-box-tooltip/`

Each subdirectory contains its own `vendor/` folder (likely from the original package) and a main `.php` entry file. These are vendored copies, not resolved by Composer or npm.

### TGMPA bootstrap

`required_plugins/class-tgm-plugin-activation.php` is the TGMPA library itself (a third-party dependency). `required_plugins/wpcd-required-plugins.php` registers the required plugins via the `tgmpa_register` hook. In the current state only `meta-box` (slug `meta-box`) is listed as required from the WordPress.org repository; the rest are expected to be loaded from the vendored directories above.

Configuration sets `is_automatic => true` and `dismissable => false`, meaning required plugins are auto-activated and the notice cannot be dismissed until they are installed.

## Conventions and Constraints

- **Runtime PHP version**: `php >=8.1` is enforced by Composer's `require` constraint in `composer.json`.
- **Allowed Composer plugins**: only `dealerdirect/phpcodesniffer-composer-installer` and `johnpbloch/wordpress-core-installer` are permitted via `config.allow-plugins`; any other Composer plugin will be blocked unless explicitly whitelisted.
- **Build artifacts are committed**: `assets/dist/` is intentionally tracked in Git (`.gitignore` comments explain this); `node_modules/` is ignored. This means production deployments ship prebuilt JS/CSS and do not require Node.
- **CSS must remain isolated**: the `verify:css` script (`tools/verify-css-isolation.mjs`) is part of the `npm run verify` pipeline and checks that every Vite chunk has an associated `.css` file, enforcing the single-stylesheet build strategy declared in `vite.config.js`.
- **WordPress test scaffolding is excluded**: `vendor/wordpress/` and `vendor/bin/` are gitignored, keeping the test-only WordPress installation out of the distribution.
- **No private registries**: there is no `repositories` block in `composer.json`, no `.npmrc`, and no private registry configuration observed — all packages resolve from public sources (Packagist, npm registry).
- **Vendor directories are not autoloaded**: the root `includes/vendor/` directory exists but is empty; dependencies are either Composer-managed (installed to root `vendor/`) or manually included via `require_once` (e.g., TGMPA class).

## Key Files

- `composer.json` — PHP runtime/dev dependencies, scripts, allowed plugins.
- `composer.lock` — pinned PHP dependency tree.
- `package.json` — frontend dependencies and build scripts.
- `package-lock.json` — pinned npm dependency tree.
- `vite.config.js` — build target, output path, committed artifact strategy, CSS bundling behavior.
- `tools/verify-css-isolation.mjs` — post-build invariant check for CSS chunks.
- `required_plugins/class-tgm-plugin-activation.php` — vendored TGMPA library.
- `required_plugins/wpcd-required-plugins.php` — TGMPA registration declaring `meta-box` as required.
- `required_plugins/<add-on>/` — vendored Meta Box ecosystem add-on sources.
- `.gitignore` — declares that `assets/dist/` is committed and `node_modules/` is ignored.