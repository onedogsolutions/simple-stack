# Simple Stack — Project Instructions & Guardrails

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
Simple Stack is an internal hosting control-plane plugin for One Dog Solutions
(`https://github.com/onedogsolutions/simple-stack`), forked from the upstream 5.8.0 release.
The fork has no production installs, data or servers to migrate. GPL attribution remains in
`NOTICE.md`, `license.txt` and the README provenance section.

- **Product name:** Simple Stack.
- **Code prefixes:** `SSTK_` for classes/constants and `sstk_` for functions, hooks,
  options, meta, post types, taxonomies, capabilities and cron identifiers.
- **Asset/text prefixes:** `sstk-` for CSS/JS handles, classes and IDs; `simple-stack`
  for the text domain, plugin folder, main file and REST namespace.
- **Target Cloud Providers:** Vultr (API v2) and UpCloud (API 1.3).
- **Target Web Server Stack:** OpenLiteSpeed (`ols`) on Ubuntu 24.04 LTS / 26.04 LTS with
  `lsphp83`, `lsphp84`, and `lsphp85`.
- **Frontend Architecture:** Native `wp-admin`. A React + Tailwind CSS v4 single-page app
  mounts at `#simplestack-admin` inside the WordPress admin and talks to the plugin through
  the `simple-stack/v1/` REST namespace. There is no external or headless frontend.

## Critical Refactoring Constraints
1. **The hard rename is complete:** do not reintroduce any upstream vendor or product
   identifier captured in `bin/rebrand-map.json`. There is no compatibility layer. The only
   exceptions are GPL attribution in `NOTICE.md`, `license.txt` and README credits, plus the
   rename inventory/tooling and historical `state.md` notes.
   `bin/guard-sstk-prefix.sh` enforces this boundary.
2. **Keep the four-character `sstk_` prefix:** post-type keys must remain at most 20
   characters. Do not lengthen it to `simplestack_`.
3. **Preserve Vultr Cloud-Init Fixes:** do not remove existing `/etc/cloud/cloud.cfg`
   reset workarounds in the OpenLiteSpeed Ubuntu provisioning scripts.
4. **Security First:**
   - Every custom REST endpoint under `simple-stack/v1` MUST verify authentication and
     enforce `current_user_can( 'manage_options' )` (or a dedicated capability).
   - Never log plaintext root passwords, private SSH keys, or cloud API tokens to debug
     logs or REST responses.
   - Port `7080` (OpenLiteSpeed WebAdmin) must be blocked from public `0.0.0.0/0` access
     in UFW rules by default.
5. **Retired services stay retired:** no EDD licensing/updater, Wisdom telemetry,
   Logtivity integration, VPN/Basic Server/Stable Diffusion modules or WooCommerce
   compatibility layer.

## Validation Standards
- Run `php -l` on all modified or newly created PHP files.
- Run `shellcheck` (if available) and `bash -n` on all modified provisioning scripts under
  `includes/core/apps/wordpress-app/scripts/`.
- Run `php bin/rebrand.php --dry-run` after rename-related changes; it must report zero
  text changes, replacements and path moves.
- Run `npm run build` (and `npm run verify:css`) after any change under `src/admin/`, then
  include the regenerated `assets/dist/` output.
- Convenience wrappers: `bash bin/lint-php.sh`, `bash bin/lint-sh.sh`,
  `bash bin/guard-sstk-prefix.sh`.

## UI Constraints
- Tailwind CSS v4 must never leak outside `#simplestack-admin`. Do not enable Preflight;
  do not add unscoped `html`/`body`/`*` rules. `npm run verify:css` enforces this.
- The admin bundle is built with Vite (`@tailwindcss/vite`) and React is bundled rather than
  borrowed from `@wordpress/element`. Keep it that way unless `state.md` records a change.
- User-facing product copy uses **Simple Stack**. Internal identifiers use `SSTK_`, `sstk_`
  and `sstk-`; the slug and text domain use `simple-stack`.
