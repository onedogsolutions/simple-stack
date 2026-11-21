# SimpleStack — Project Instructions & Guardrails

## Session Start — Mandatory
Read `state.md` at the repository root **before** doing anything else. It records the
current phase, completed milestones, architectural decisions, modified files, open
blockers and the next action. `state.md` is the single source of truth for build state.

## Session End — Mandatory
Update `state.md` at the end of **every** phase: flip the phase status in the Progress
table, refresh Overall status / Last updated / Next action, append anything surprising to
the Decisions & deviations log, and record unresolved items under Open questions /
blockers. Follow the Update protocol section inside `state.md`.

## Overview
SimpleStack is an internal hosting control-plane plugin for One Dog Solutions (`https://github.com/onedogsolutions/simple-stack`), forked from WP Cloud Deploy (v5.8.0) to replace xCloud.
- **Target Cloud Providers:** Vultr (API v2) and UpCloud (API v1.3) only.
- **Target Web Server Stack:** OpenLiteSpeed (`ols`) on Ubuntu 24.04 LTS / 26.04 LTS with `lsphp83`, `lsphp84`, and `lsphp85`.
- **Frontend Architecture:** Native `wp-admin`. A React + Tailwind CSS v4 single-page app mounts at `#simplestack-admin` inside the WordPress admin and talks to the plugin through the `simple-stack/v1/` REST namespace. There is no external or headless frontend.

## Critical Refactoring Constraints
1. **DO NOT global-rename `wpcd_` prefixes:** Keep existing `wpcd_` PHP class names, Custom Post Type slugs (`wpcd_app_server`, `wpcd_app`), post meta keys, and Bash environment variables intact to preserve internal hook compatibility. Only rebrand user-facing strings, plugin headers, log prefixes, and new REST endpoints to **SimpleStack**.
2. **Preserve Vultr Cloud-Init Fixes:** Do not remove existing `/etc/cloud/cloud.cfg` reset workarounds in the OpenLiteSpeed Ubuntu provisioning scripts.
3. **Security First:**
   - Every custom REST endpoint under `simple-stack/v1` MUST verify authentication and enforce `current_user_can( 'manage_options' )` (or a dedicated capability).
   - Never log plaintext root passwords, private SSH keys, or cloud API tokens to debug logs or REST responses.
   - Port `7080` (OpenLiteSpeed WebAdmin) must be blocked from public `0.0.0.0/0` access in UFW rules by default.

## Validation Standards
- Run `php -l` on all modified or newly created PHP files.
- Run `shellcheck` (if available) and `bash -n` on all modified provisioning scripts under `includes/core/apps/wordpress-app/scripts/`.
- Run `npm run build` (and `npm run verify:css`) after any change under `src/admin/`, then commit the regenerated `assets/dist/`.
- Convenience wrappers: `bash bin/lint-php.sh`, `bash bin/lint-sh.sh`, `bash bin/guard-wpcd-prefix.sh`.

## UI Constraints
- Tailwind CSS v4 must never leak outside `#simplestack-admin`. Do not enable Preflight;
  do not add unscoped `html`/`body`/`*` rules. `npm run verify:css` enforces this.
- The admin bundle is built with Vite (`@tailwindcss/vite`) and React is bundled rather than
  borrowed from `@wordpress/element`. Keep it that way unless `state.md` records a change.
