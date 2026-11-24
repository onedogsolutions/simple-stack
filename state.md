# Build State — Simple Stack

Single source of truth for the hard-cutover fork and rebrand. Read this file at the start of
every task and update it at the end of every phase.

- **Branch:** `feat/simplestack-v1-init`
- **Plugin version target:** `1.0.0`
- **Last updated:** 2026-11-23
- **Overall status:** Hard-cutover rebrand, commercial cleanup, Vultr/UpCloud-only and
  OpenLiteSpeed-only cleanup, retired-code removal, documentation, translations and static
  verification are complete. The corrected 1.0.0 release candidate is active on One Dog Test and
  core live REST, admin UI, cron, public-page and provisioning-URL checks pass. Two additional live
  defects were fixed and redeployed. The release gate remains not green: PHPUnit needs MySQL,
  destructive cloud provisioning/uninstall and full OLS runtime certification remain open, and the
  TLS-validation policy is unresolved.

## Locked target

- Product: Simple Stack; class prefix `SSTK_`; function/data prefix `sstk_`; handles `sstk-`.
- Text domain and slug: `simple-stack`; REST namespace: `simple-stack/v1`.
- Providers: Vultr API v2 and UpCloud API 1.3 only.
- Stack: OpenLiteSpeed only on Ubuntu 24.04/26.04; PHP 8.3, 8.4 (default) and 8.5.
- No compatibility layer for upstream identifiers, providers, data keys, hooks or scripts.
- Port 7080 is never opened to `0.0.0.0/0`; secrets are never logged or returned.

## Current progress

| Workstream | Status |
|---|---|
| Rename audit, map and repeatable tooling | Complete |
| Mechanical identifier/path/asset rename | Complete |
| Manual rename follow-ups | Complete |
| Commercial WaaS/quota/subscription cleanup | Complete; PHP lint and copy-script checks passed |
| Vultr/UpCloud-only provider cleanup | Complete |
| Ubuntu 24.04/26.04 and PHP 8.3/8.4/8.5 alignment | Complete in active PHP/React code |
| Provisioning script version `v2` lock | Complete |
| OLS-only multisite, HTTPS, phpMyAdmin, backup/sync helpers and stale admin copy | Complete in code; PHP/shell/residual gates passed; live OLS runtime checks pending |
| Retired VPN/Basic Server/Stable Diffusion/Logtivity/stale-asset cleanup | Complete; lint, guard and residual scans passed |
| Branding, licensing, docs and generated metadata | Complete |
| Full lint/PHPCS/PHPUnit/build/guard/runtime verification | Static gates and core live checks complete; PHPUnit, destructive cloud provisioning/uninstall and full OLS runtime certification pending |

## Verified in the retired-code phase

- `bash bin/lint-php.sh`: 291 files OK.
- `bash bin/lint-sh.sh`: 354 scripts OK; `shellcheck` is not installed.
- `bash bin/guard-sstk-prefix.sh`: OK, including HTML-template coverage.
- `php bin/rebrand.php --dry-run`: zero text changes, replacements or path moves.
- Retired-token and retired-filename scans across `includes/`, `assets/` and `src/`: zero matches.
- No active editable/caller-controlled provisioning script-version paths remain; server creation
  uses the hard-locked `v2` scripts.
- The inherited whitespace backlog reported at this phase has since been cleared; the final
  follow-up `git diff --check` exits cleanly with no diagnostics.

## Verified in the OLS-only phase

- `bash bin/lint-php.sh`: 288 files OK, including the repaired redirect-rule description.
- `bash bin/lint-sh.sh`: 138 scripts OK; `shellcheck` is not installed.
- Active `scripts/v2` forbidden-stack scan: zero NGINX, PHP-FPM, FastCGI proxy, enterprise
  LiteSpeed, `/etc/wpcd` or stale webserver-restart matches.
- Retired CrowdSec/WPScan/tech-support/6G/`scripts/v1` scans across `includes/`: zero matches;
  `scripts/v1/` contains zero files.
- Wildcard multisite wrapper rendering with empty optional credentials: zero unresolved tokens and
  `bash -n` passed after rendering.
- Focused `git diff --check` across the OLS-phase files passed. The broader inherited whitespace
  backlog has since been cleared in the follow-up phase.

## Verified in the documentation and release-follow-up phase

- `bash bin/lint-php.sh`: 288 files OK.
- `bash bin/lint-sh.sh`: 139 scripts OK, now including the extensionless `wp-sync` and
  `wp-backup2-helper` scripts; `shellcheck` is not installed.
- `vendor/bin/phpcs`: clean, zero errors.
- `bash bin/guard-sstk-prefix.sh`: OK.
- `composer validate --no-check-publish`: valid and lock in sync.
- `php bin/rebrand.php --dry-run`: zero text changes, replacements or path moves.
- `npm run verify`: Vite build passed; CSS isolation passed with 274 rules and 263 scoped utility
  selectors.
- `php tests/uninstall-harness.php`: OK.
- `languages/simple-stack.pot`: `msgfmt --check --check-format` passed with 4,055 untranslated
  messages; all 5,363 source references resolve to existing files and valid line numbers.
- Retired-stack scan across all of `scripts/v2`, including extensionless helpers: zero matches.
- Upstream-token scans across active PHP/React/assets: zero matches; retired provider/module scans
  are clean apart from the write-only `sstk_server_scripts_version` audit meta, which is always set
  to the fixed `v2` value and is never read as an override.
- All 17 directly registered post-type keys are at most 20 characters; the longest are exactly 20.
- `git diff --check`: clean.
- `vendor/bin/phpunit`: blocked before test execution by `mysqli_real_connect(): (HY000/2002)`;
  no MySQL/MariaDB server is available locally.
- TLS-validation debt: 25 `--no-check-certificate` occurrences remain under `scripts/v2`.

## Verified in the One Dog Test live follow-up

- Corrected release-candidate packages were hash-verified locally and remotely before installation.
  The active package is `simple-stack-1.0.0-live-20261123-v5.zip` (2,426,583 bytes, SHA-256
  `3b72468c2f1a165395eb578f8f8f1a85d441031305966a3c1eca0b298bc16bdb`).
- Simple Stack 1.0.0 is active as `simple-stack/simple-stack.php`; `wpcd.php` is absent, all six
  bundled Meta Box autoloaders exist, OPcache was reset, and the homepage plus REST namespace return
  HTTP 200.
- Authenticated REST list/get, validation-boundary, unknown-ID, provider-credential-guard and public
  command-callback checks pass. Provider presence fields remain booleans; Vultr reports
  `uses_api_key=true`, UpCloud reports `uses_api_key=false`, and both report `credentials_available=false`.
- Headless Chrome rendered the React app on Servers, Sites, Providers, Activity and Provision server.
  The provider page shows two `needs credentials` badges and the UpCloud `User name / password`
  summary; provisioning correctly shows `No provider is configured`. No uncaught JavaScript errors,
  failed Simple Stack asset requests or failed Simple Stack REST requests were observed.
- The admin menu, React mount, hashed JS/CSS assets and inline `window.simplestackAdmin` bootstrap are
  present on all four React screens. All three public Simple Stack pages and the alert-setup page
  return HTTP 200.
- Six `sstk_*` cron events are scheduled with the expected minute, fifteen-minute and hourly
  schedules. Provisioning placeholders resolve to `scripts/v2/raw/`; both checked v2 assets return
  HTTP 200 and the corresponding v1 URL returns 404.
- Static v2 scripts deny public port 7080 and only allow an optional validated `SSTK_ADMIN_IP`; the
  external `https://test.onedog.solutions:7080/` probe timed out. Passwordless sudo was unavailable,
  so the host's UFW table could not be inspected directly.
- No cloud resources were created or deleted, no real provisioning or uninstall was run, and no
  other plugin was changed. The pre-live backup remains at
  `/tmp/simple-stack-pre-live-20261123.zip`.

## Next action

1. Decide and implement the TLS-validation policy for first-party provisioning downloads versus
   operator-supplied post-processing and Git-hook URLs.
2. Supply the frozen `ols1clk.sh` checksum and the post-install security-plugin slugs.
3. Provide a local MySQL/MariaDB service and run the PHPUnit suite; install ShellCheck if available.
4. With explicit approval and provider credentials, run Vultr/UpCloud create/details/power/delete,
   Ubuntu 24.04/26.04 provisioning, OLS hardening, backup/sync, Monit/Netdata and a real database
   uninstall. Core non-destructive live REST, admin UI, cron and provisioning-URL checks are complete.
5. Add a Simple Stack UI field for `sstk_admin_ip`; until then the documented programmatic setting is
   honoured and port 7080 remains loopback-only by default.
6. Commit, tag and release only on explicit operator instruction.

## Historical implementation record

The sections below describe earlier iterations and are retained as history. Where they conflict
with the current status above or the hard-cutover requirements, the current status and
hard-cutover requirements take precedence.

## Implementation steps

### Step 0 — Baseline & tooling ✅

Delivered:

- `.gitignore` — node build artifacts, local scratch; `assets/dist/` deliberately **not**
  ignored.
- `.gitattributes` — marks `assets/dist/**` as generated, tags provisioning `.txt` as Shell.
- `bin/lint-php.sh` — `php -l` over the tree, skipping `node_modules/`, `vendor/`,
  `assets/dist/`. Verified: 315 files OK.
- `bin/lint-sh.sh` — `bash -n` over `includes/**/scripts/*/raw/*.txt`. Three files are not
  shell and are excluded: `6G-Firewall-OLS.txt`, `7G-Firewall-OLS.txt` (Apache/OLS
  snippets) and `environment variables.txt` (documentation). Verified: 77 scripts OK.
  shellcheck runs in advisory mode only.
- `bin/guard-sstk-prefix.sh` — fails if `sstk_app_server`, `sstk_app`, `SSTK_PATH`,
  `SSTK_URL` or the `SSTK_SERVER` / `SSTK_WORDPRESS_APP` / `SSTK_SETTINGS` classes vanish
  (class match is case-insensitive: the inherited tree declares `SSTK_Server` and
  `SSTK_Settings` in title case), or if `SSTK_License` / `wisdom_` reappear under
  `includes/core`. The `__return_true` ban is scoped to `includes/core/api/` — two legacy
  endpoints (the remote command callback in `apps/class-sstk-app.php` and the GitHub webhook
  in `tabs/git-control-site.php`) are public by design and authenticate another way.
- `composer.json` — package renamed to `onedogsolutions/simple-stack`, PHP floor raised from
  `^7.4` to `>=8.1`, `lint` / `lint:sh` / `guard` scripts added.
- `phpcs.xml` — ruleset renamed, `includes/core/api` added to the scan set, excludes for
  `node_modules`, `vendor`, `assets/dist`, `src`.

### Step 1 — Rebrand, bloat removal, OLS lock ✅

`simple-stack.php` (1207 → 797 lines):

- Header rebranded to Simple Stack 1.0.0 / One Dog Solutions, `Requires at least: 6.5`,
  `Requires PHP: 8.1`. `Item Id`, `GitHub Plugin URI` and `Primary Branch` deleted.
  `Text Domain: simple-stack`; the translation catalog was regenerated under that domain.
- Removed `SSTK_ITEM_ID`, the EDD updater + license requires, the WooCommerce app module,
  the add-on compatibility checks (`are_all_addons_compatible()`,
  `check_all_addons_compatible()`, `sstk_updated_option()`, `sstk_plugin_force_deactivate()`,
  `sstk_plugin_deactivate_admin_notice()`), the Wisdom bootstrap (`admin_init()`,
  `sstk_start_plugin_tracking()`), `sstk_plugin_update_message()`, and the VPN /
  Basic Server / Stable Diffusion bootstrap + cron entries.
- `plugin_row_meta` now uses `plugin_basename( __FILE__ ) === $plugin_file_name` instead of
  the truthiness bug on `strpos()` (risk register #2).
- PHP floor notice raised to 8.1; all notice copy rebranded and pointed at the new repo.

`includes/core/simple-stack.php`:

- Wisdom hooks, license notices, `set_wisdom_custom_options()`, `set_wisdom_schedule()`,
  `license_notices_server_limit()`, `license_notices_wpsite_limit()` and
  `sstk_wisdom_custom_options_events()` deleted.
- `$providers` → `vultr-v2`, `upcloud`, `digital-ocean`, `custom-server`.
- `get_webserver_list()` → `array( 'ols' => 'OpenLiteSpeed' )`.
- `add_backup_digital_ocean_provider()` / `remove_digital_ocean_provider()` replaced by
  `disable_digital_ocean_provider_by_default()` keyed on `enable-do-provider`.
- Front-end half of `sstk_inject_brand_color_styles()` removed.

OLS lock: `get_default_webserver()`, `get_web_server_type()` and both `create_server()`
fallbacks default to `'ols'`; the settings `std` for `wordpress_app_default_webserver` is
`'ols'` and its tooltip says the value is fixed. The REST server-create endpoint already
validates against `SSTK()->get_webserver_list()`, so it became OLS-only for free.

Labels and assets: CPT `menu_name` → Simple Stack; the inline base64 SVG menu icon replaced
with `dashicons-cloud`; `enqueue_server_post_common_scripts()` gutted (select2, magnific,
`sstk-wpapp-admin-common`, `sstk-wpapp-server-admin`, server/cards CSS).

`includes/core/class-sstk-settings.php`:
- License settings tab, meta box, `check_license()`, `get_license_fields_for_add_ons()`,
  `check_for_updates()`, `sstk_check_for_updates()`, `sstk_validate_licenses()`,
  `handle_wisdom_opt_out()` and the `sstk-admin-settings-data-sync.js` enqueue deleted.
- `hide-do-provider` field replaced with `enable-do-provider` (inverted default).

Deleted outright (~78 files): `apps/vpn/`, `apps/basic-server/`, `apps/stable-diffusion/`,
`apps/class-sstk-woocommerce.php`, the `woocommerce_support` trait, `class-sstk-license.php`,
`class-sstk-data-sync-rest.php`, both Logtivity integrations,
`includes/vendor/SSTK_EDD_SL_Plugin_Updater.php`, `vendor/edd_sl/` (unused duplicate),
`vendor/wisdom_plugin/`, `assets/css/sstk-vpn.css`, `assets/js/sstk-vpn{,-wc}.js`,
`assets/js/sstk-admin-settings-data-sync.js`, `assets/fonts/spinupvpnwebsite.*`.

Verification: `bash bin/lint-php.sh` → 293 files OK; `bash bin/lint-sh.sh` → 75 scripts OK;
`bash bin/guard-sstk-prefix.sh` → OK; `grep` sweep for `SSTK_License`,
`Plugin_Usage_Tracker`, `basic-server`, `stable-diffusion`, `SSTK_HIDE_LICENSE_TAB`,
`sstk_license_check` is empty.

### Step 2 — `/simple-stack/v1/` REST layer ✅

New directory `includes/core/api/` (9 files, ~2 230 lines):

| File | Role |
|---|---|
| `class-simplestack-secret-redactor.php` | recursive masking, PEM scrub |
| `class-simplestack-rest-controller-base.php` | namespace, permission callback, envelope, arg helpers |
| `class-simplestack-rest-servers.php` | server list/detail/create/delete + server action bridge |
| `class-simplestack-rest-sites.php` | site list/detail/deploy/delete + site action bridge |
| `class-simplestack-rest-logs.php` | per-server log stream + activity feed |
| `class-simplestack-rest-tabs.php` | tab schema for servers and sites |
| `class-simplestack-rest-providers.php` | provider credential state, save, test |
| `class-simplestack-rest-security.php` | read-only site security probe |
| `class-simplestack-rest-api.php` | registrar + `sstk_rest_api()` |

**Route table (18 routes, verified by a stub harness that enumerates the registered table):**

| Method | Route | Handler |
|---|---|---|
| GET | `/servers` | `list_servers` — `WP_Query` on `sstk_app_server`, filters `provider`/`region`/`status`/`search` |
| POST | `/servers` | `create_server` — `SSTK_SERVER()->create_server()` + `add_pending_task_log_entry()` |
| GET | `/servers/{id}` | `get_server` — summary + all `sstk_server_*` meta, redacted |
| DELETE | `/servers/{id}` | `delete_server` — `do_action( 'sstk_server_wordpress-app_action', $id, 'delete' )` |
| POST | `/servers/{id}/action` | `run_action` — `do_instance_action( $id, $action, $additional )` |
| GET | `/servers/{id}/logs` | `server_logs` — `type` + `since` cursor + `limit` |
| GET | `/servers/{id}/tabs` | `server_tabs` — `sstk_server_wordpress-app_get_tabnames` / `_get_tabs` |
| GET | `/sites` | `list_sites` — `sstk_app` where `app_type = wordpress-app` |
| POST | `/sites` | `deploy_site` — `install_wp_validate( $args )` |
| GET | `/sites/{id}` | `get_site` — summary + `wpapp_*` meta, redacted |
| DELETE | `/sites/{id}` | `delete_site` — CPT permission gate, then `wp_trash_post()` |
| POST | `/sites/{id}/action` | `run_action` — `$_POST` mapped from body, then `sstk_app_wordpress-app_tab_action` |
| GET | `/sites/{id}/tabs` | `site_tabs` — `sstk_app_wordpress-app_get_tabnames` / `_get_tabs` |
| GET | `/settings/providers` | `list_providers` — booleans, feature flags, cached counts |
| POST | `/settings/providers` | `save_provider` — encrypts before `sstk_set_option()` |
| POST | `/settings/providers/{slug}/test` | `test_provider` — `call( 'test_connection' )` |
| POST | `/sites/{id}/security-status` | `security_status` — read-only `wp plugin list` / `wp config get` / `php -v` over SSH |
| GET | `/activity` | `activity` — merged command / SSH / error feed |

**Security posture:**

- `SSTK_REST_Controller_Base::permissions_check()` is the only permission callback in
the namespace; it returns `current_user_can( 'manage_options' )`. No `__return_true` anywhere.
- Every registered argument carries both `sanitize_callback` and `validate_callback`; the
helpers `arg_int` / `arg_string` / `arg_text` / `arg_object` / `arg_id` make that structural
rather than a convention.
- Every response is wrapped by `respond()`, which pipes the payload through
`SSTK_Secret_Redactor::redact()`. Keys matching
`/pass|secret|token|private|authorization|_key$|api_?key/i` become `********`, PEM blocks and
`token=`/`password=` fragments inside free text are scrubbed, and password-typed tab fields
ship with `std`/`value`/`default` blanked.
- `respond()` masks the credential-shaped keys before serialisation, and provider routes report
`has_api_key` / `has_ssh_key` booleans instead of values.

Wiring: `simple-stack.php` requires `class-simplestack-rest-api.php` after `functions-classes.php`,
calls `SSTK_REST_API::load_files()` and then `sstk_rest_api()`. The registrar hooks
`rest_api_init`, so the controllers themselves are constructed on that hook.

Verification: `php -l` → 302 files OK (293 + 9 new); `bash bin/guard-sstk-prefix.sh` → OK;
`grep -rn "__return_true" includes/core/api/` → empty; the stub harness enumerating
`register_rest_route()` calls reports **18 routes, 0 failures**, all under `simple-stack/v1`
with a non-public permission callback and fully validated args.

### Step 3 — Vite + React + Tailwind v4 pipeline, CSS isolation ✅

**Build toolchain (new):**

| File | Role |
|---|---|
| `package.json` | Vite 7, React 19, `@tanstack/react-query` 5, Tailwind 4, `@tailwindcss/vite` |
| `vite.config.js` | `outDir: assets/dist`, `manifest: true`, `cssCodeSplit: false`, single `admin` entry |
| `tailwind.config.js` | CJS; `important: '#simplestack-admin'`, `corePlugins.preflight: false`, One Dog brand tokens |
| `src/admin/styles/index.css` | `@config` + theme/utilities layers only; hand-written reset scoped to the mount |
| `src/admin/index.jsx` | guarded mount into `#simplestack-admin` |
| `src/admin/App.jsx` | Phase 3 placeholder shell; Phase 4 replaces it with the routed app |
| `tools/verify-css-isolation.mjs` | build-time isolation gate |

`package.json` deliberately has **no** `"type": "module"` so `tailwind.config.js` can stay
CommonJS (Tailwind v4's `@config` directive loads it as CJS). `vite.config.js` still uses ESM
syntax because Vite transpiles its own config, and the verifier is `.mjs`.

**PHP layer — `includes/core/simple-stack.php`:**

- `add_sstk_menu_page()` on `admin_menu` (priority 100) registers the `simplestack`
  page, capability `sstk_manage_servers`, icon `dashicons-cloud`, position 3.
- `render_sstk_app()` prints `<div id="simplestack-admin" class="simplestack-root">`, and
  emits an operator-facing notice instead of a blank page when the build output is missing.
- `is_sstk_screen( $hook )` matches `toplevel_page_simplestack` and `sstk_page_*`.
- `get_sstk_manifest()` reads `assets/dist/.vite/manifest.json` once per request and
  caches the result; a missing or unparseable manifest yields `false` and never fatals.
- `get_sstk_entry_assets()` resolves the entry chunk and every stylesheet, handling both
  manifest shapes (entry `css` array, or standalone `.css` chunk).
- `get_sstk_asset_version()` extracts the Vite hash from the filename so the enqueue is
  cache-safe without a manual version bump.
- `enqueue_sstk_app( $hook )` on `admin_enqueue_scripts` (priority 20) enqueues
  `simplestack-admin` with `wp-i18n` in the footer, plus
  `wp_add_inline_script( ..., 'before' )` defining
  `window.simplestackAdmin = { root, restUrl, nonce, namespace, version, userId }`.

**CSS isolation — the mechanism, not a promise:**

wp-admin's stylesheets are *unlayered* author styles. Tailwind v4 emits real cascade layers, and
an unlayered rule beats a layered rule regardless of specificity — so an unscoped `.flex` would
silently lose to wp-admin, and any stray `body { }` would clobber the admin chrome. Two measures:

1. `important: '#simplestack-admin'` in `tailwind.config.js`. Verified in the build output:
   every utility is emitted as `@layer utilities { #simplestack-admin .flex { ... } }`.
2. `tools/verify-css-isolation.mjs` fails the build if that ever regresses. It walks the emitted
   stylesheet, tracks the at-rule stack per rule, and asserts: no top-level `html`/`body`/`*`/
   `:root` selector carrying real declarations; every `@layer utilities` selector anchored on the
   mount; at least one scoped utility present; no `@tailwind`/`@config`/`@apply` residue; the
   manifest exists and lists a stylesheet.

The only document-level selectors in the bundle are Tailwind's own token layers —
`@layer theme { :root, :host { --spacing: ... } }` and
`@layer properties { @supports (...) { *, ::before, ::after, ::backdrop { --tw-*: initial } } }`.
Both declare custom properties only, which cannot change layout, and both sit in a layer that
wp-admin's unlayered rules outrank. The verifier special-cases exactly this shape via
`isTokenOnlyDeclarations()`.

**Verification:**

| Command | Result |
|---|---|
| `npm ci` / `npm install` | 80 packages |
| `npm run build` | 27 modules; `assets/dist/assets/admin-*.js` 223.94 kB (69.61 kB gzip); `assets/dist/assets/style-*.css` 9.70 kB (2.25 kB gzip) |
| `npm run verify:css` | OK — 95 rules, **85 scoped utility selectors**, 0 unscoped |
| Negative test of the verifier | an injected unscoped `.flex` and `body { margin: 0 }` both fail with exit 1 |
| `php -l` (whole tree) | 302 files OK |
| `bash -n` | 75 scripts OK |
| `bin/guard-sstk-prefix.sh` | OK |
| Enqueue harness | **46/46 checks** — screen detection, off-screen bail-out, real manifest resolution, asset existence, footer + `wp-i18n` deps, hash-based version, bootstrap JSON shape, no credential keys, and safe degradation when the manifest is absent |

### Step 4 — Hard-cutover React admin UI ✅

**Client (`src/admin/`, 25 files):**

```
index.jsx                 mounts at #simplestack-admin, seeds the hash from data-route
App.jsx                   hash router (#/servers, #/servers/:id, #/sites, #/sites/:id,
                          #/servers/new, #/sites/new, #/settings/providers,
                          #/security, #/activity)
lib/api.js                fetch wrapper, X-WP-Nonce, envelope unwrap, FormData uploads
lib/router.js             useRoute / navigate / seedRoute primitives
lib/schema.js             Meta Box field array → control descriptor
lib/format.js             bytes, dates, relative times, state tones
components/AppShell.jsx   header, nav, breadcrumbs, action slot
components/DataTable.jsx  Button.jsx  Card.jsx  Modal.jsx  Toast.jsx  SecretField.jsx
components/LogDrawer.jsx  cursor-polls GET /servers/{id}/logs
components/TabRenderer.jsx  renders the get_tabs schema and posts .../action
routes/ServersList.jsx  ServerDetail.jsx  ProvisionServer.jsx
routes/SitesList.jsx    SiteDetail.jsx    DeploySite.jsx
routes/ProviderSettings.jsx  SecurityStatus.jsx  ActivityLog.jsx
```

**Parity table — 51 tabs, all reached through `TabRenderer`:**

| Surface | Count | Tab slugs |
|---|---|---|
| Server | 22 | `server_backup` `callbacks` `fail2ban` `git-server-control` `goaccess` `server-logs` `monit-healing` `monitorix` `ols_console` `svr_power` `resize` `services` `sites_on_server` `ssh_console` `server-ssh-keys` `svr_statistics` `svr_tools` `svr_tweaks` `firewall` `server_upgrade` `server-users` + `general` |
| Site | 29 | `6g_waf` `7g_waf` `backup` `cache` `change-domain` `clone-site` `copy-to-existing-site` `crons` `file-manager` `general` `git-site-control` `site-logs` `misc` `multitenant-site` `php-options` `database` `redirect-rules` `site-security` `sftp` `site-sync` `site-system-users` `ssl` `staging` `statistics` `theme-and-plugin-updates` `tools` `tweaks` `wp-site-users` `wpconfig` |

Field types emitted by the 51 tabs and covered by `describeField()`: `button`, `custom-html`,
`custom_html`, `date`, `datetime`, `divider`, `email`, `file`, `heading`, `number`, `password`,
`post`, `select`, `switch`, `taxonomy`, `taxonomy_advanced`, `text`, `textarea`, `time`, `url`,
plus the `sstk_card_container` grouping pseudo-type. `data-show-log-console` survives the
`get_tabs` → REST → `schema.js` → `TabRenderer`/`LogDrawer` chain untouched.

**The action bridge fix (non-obvious, worth recording):** server tabs do **not** bind a
server-specific filter. `tabs-server/*.php` register `sstk_server_{app}_get_tabnames` /
`_get_tabs` / `_tab_action`, but their own header comment says the third "has not been defined
and called yet … and might never be", and they dispatch through
`sstk_app_{app}_tab_action` instead.
`do_instance_action()` (`commands-and-logs.php:614-794`) owns lifecycle verbs only and contains
no `tab_action` dispatch. Routing `/servers/{id}/action` straight at `do_instance_action()` — as
Phase 2 shipped — meant **every server tab action would have failed silently**. Both resource
controllers now call `SSTK_REST_Controller_Base::dispatch_tab_action()` first and fall
back to `do_instance_action()` only when the filter chain returns `null`.

`dispatch_tab_action()` also reproduces two legacy quirks deliberately: it populates
`$_POST['params']` (the tabs read it via `wp_parse_args`) and promotes
`ssh-private-key` / `ssh-public-key` / `ssh-private-key-password` to top-level `$_POST` keys,
because `wp_parse_args()` mangles the multi-line PEM values. Uploads arrive as `$_FILES['file']`
for `sftp-set-key` and `site-user-set-key`, matching the deleted `sstk-wpapp-admin-common.js`.
The client sends `FormData` (params as a JSON string) whenever an `<input type="file">` is part
of the action, and plain JSON otherwise.

**Menu replacement — `includes/core/simple-stack.php`:**

- `add_main_menu_page()` deleted. `add_sstk_menu_page()` (on `admin_menu` 100) registers
  `simplestack` plus `simplestack-sites`, `simplestack-providers`, `simplestack-activity`, then
  the two surviving native screens (`App Groups`, `Notifications`) and `Help`.
- `sstk_screen_routes()` + `current_sstk_route()` + `data-route` + the bootstrap
  object's `route` field + `seedRoute()` in `index.jsx`: a top-level click opens the right hash
  route without a second PHP routing layer. An existing hash is never overwritten.
- `ensure_submenu_items_order()` re-hooked from `admin_init` (where it was **dead code** —
  `admin_init` fires before `admin_menu`) to `admin_menu` priority 999.
- `sstk_get_faq_and_help_text_page_callback()` item remains, but the body is rewritten.
- `redirect_legacy_cpt_screens()` on `load-edit.php` / `load-post.php` sends old CPT bookmarks to
  the React route.

**Retired:** `metaboxes-server.php`, `metaboxes-app.php` (the only consumers of the
`get_tabnames`/`get_tabs` filters for rendering; the filters themselves stay, because the REST
bridge serialises them), `install-app-popup.php`, `show-log-console.php`,
`show-log-console-bare.php`. The dead `ajax_server()` cases `install-app-popup` / `create-popup` /
`log-console` and the `ajax_app()` case `show-log-console` are removed from
`class-wordpress-app.php`. `functions-metabox.php`'s docblock is repointed at the REST consumer.

**Kept, with reasons (deviation from the plan's delete list):**

| File | Surviving consumer |
|---|---|
| `templates/create-popup.php`, `header-popup.php`, `log-console.php` | `public/class-wordpress-app-public.php:430` → `ajax_server_handle_create_popup( 'public' )`, i.e. the **unauthenticated public deploy form**. Not renamed, not deleted. |
| `assets/js/select2.min.js`, `assets/css/select2.min.css` | `class-sstk-posts-notify-user.php:483-484`, `class-sstk-posts-app.php:204-206`; `sstk-user-notifications-admin.js` calls `.select2()`. |
| `assets/js/jquery.magnific-popup.min.js`, `assets/css/magnific-popup.css` | `class-sstk-settings.php:1836-1841` registers `sstk-magnific` unconditionally and makes `sstk-custom_table` / `sstk-admin-settings` depend on it; `custom_table.js` calls `.magnificPopup()`. |

Deleting those four really would have broken the settings screen and the notification editor.

**Verification:**

| Command | Result |
|---|---|
| `npm run build` | 49 modules; `assets/dist/assets/admin-*.js` **287.11 kB** (86.61 kB gzip); `assets/dist/assets/style-*.css` **23.88 kB** (4.40 kB gzip) |
| `npm run verify:css` | OK — 259 rules, **247 scoped utility selectors**, 0 unscoped |
| `grep -rn "admin-ajax" assets/dist/` | **empty** — no legacy ajax call survives in the browser bundle |
| `grep -rn "__return_true" includes/core/api/` | **empty** |
| `php -l` (whole tree) | 297 files OK |
| `bash -n` | 75 scripts OK |
| `bin/guard-sstk-prefix.sh` | OK |

**Deviation — no bespoke tab components.** The plan named `ssh_console`, `ols_console`,
`file-manager`, `logs`, `statistics`, `monitorix`, `goaccess` as needing dedicated components.
Each of them is a plain Meta Box field array (`button` / `heading` / `text` / `textarea` /
`switch` / `password` / `custom_html`), so `TabRenderer` renders them generically. Streaming
output goes to the shared `LogDrawer` (`data-show-log-console`) instead of the deleted popup
console, which is the same destination the legacy screen used.

**Deviation — the server `general` pseudo-tab.** `tabs-server/general.php::get_nontab_fields()`
is dead code upstream: its `add_filter` lines are commented out and its class is never
instantiated. The server summary fields therefore only ever came from the legacy top-of-page
seeding. `ServerDetail`'s Overview card reproduces them field-for-field from `GET /servers/{id}`
(name, IPv4, IPv6, provider, region, size, OS, web server, PHP, site count, created, reachability),
so nothing was lost.

**Deviation — Meta Box settings page re-parented.** The bundled `sstk_settings` screen registers
itself at `admin_menu` priority 11 as a child of the server CPT menu. With that menu hidden it
would have been unreachable, so `register_options_page()` now returns
`'parent' => 'simplestack'`, `'position' => 80`, `'capability' => 'sstk_manage_settings'`, and
`ensure_submenu_items_order()` places it seventh. `is_sstk_screen()` deliberately
excludes `sstk_page_sstk_settings`: that screen renders Meta Box markup, not the React
container, so the bundle is not enqueued there.

**Deviation — two extra submenus.** `sstk_notify_user` (alert recipients) and the
`sstk_app_group` taxonomy screen were only reachable through `add_main_menu_page()`. Losing
alert-recipient configuration is a real functional regression, so both are re-registered as
`simplestack` submenus using verbatim `.php` slugs — the same trick core uses to link taxonomy
screens. The log CPTs (`sstk_command_log`, `sstk_ssh_log`) are deliberately **not** re-registered:
they stay attached to now-unreachable parents, superseded by `/activity` and the log drawer.

### Step 5 — `scripts/v2/` for OpenLiteSpeed on Ubuntu 24.04 / 26.04 ✅

**Created.** `includes/core/apps/wordpress-app/scripts/v2/` — 188 files, of which 80 are
`raw/*.txt`. Copied wholesale from `v1/` and then edited in place, so every script that did
not need a change is still byte-identical to the version that has been running in production.
`v1/` is untouched: existing servers keep whatever `sstk_server_scripts_version` their meta
records, and the settings dropdown still lists both folders.

| File | v1 lines | v2 lines | What changed |
|---|---|---|---|
| `raw/01-prepare_server.txt` | 1095 | 704 | The structural rewrite (see below) |
| `raw/9999-common-functions.txt` | 2049 | 2175 | OS constants, `PHPVER=lsphp84`, 2 new helpers |
| `raw/38-server_php_version.txt` | 91 | 76 | Delegates to `gf_set_default_php` |
| `raw/56-ols_manage_admin_console.txt` | 270 | 330 | Bind address + fail-closed firewall |
| `raw/04-manage_https.txt` | 206 | 129 | nginx paths and functions deleted |
| `raw/37-backup-configuration.txt` | 125 | 129 | php.ini backup loops over installed versions |
| `raw/12-redis.txt`, `raw/16-memcached.txt` | 213 / 220 | 222 / 229 | Extensions installed for 83/84/85 |

**`01-prepare_server.txt`.** Reduced by 391 lines. Removed: the Ubuntu 24.04 hard refusal, the
entire nginx/LEMP install branch, the nginx `default` vhost and `sites-enabled` handling, the
`ondrej/nginx` PPA, and the lEMP-only `php-fpm` service block. Added or replaced:

- `export DEBIAN_FRONTEND=noninteractive` at the top of the script, before any `apt` call, so
  package maintainer prompts cannot block an unattended run.
- A webserver guard that defaults to `ols`, lowercases the value, and exits non-zero for
  anything outside `ols` / `ols-enterprise`.
- A `case` on `GV_UBUNTU_VERSION` that maps `24.04 → noble` and `26.04 → resolute` and fails
  fast, with an explicit message, on any other release.
- `curl --fail` (not `-k`) for `https://repo.litespeed.sh`, run before `ols1clk.sh`, so
  `lsphp83/84/85` are available from the vendor repository.
- `ols1clk.sh` pinned to tag `v3.1`, downloaded with `--fail --location`, `bash -n`-checked,
  and compared against `$SSTK_OLS1CLK_SHA256` when that variable is set.
- An explicit `for v in "${g_php_versions[@]}"` loop for `lsphp${v}`, `-common`, `-dev`,
  `-pear`, `-mysql`, then a second loop for `imap curl gd imagick intl opcache redis`.
  Version-specific extensions warn and continue rather than aborting provisioning.
- `gf_set_default_php "$g_default_php_short"`, plus a `sed` that repoints the `lsphp` handler
  in `$WEBCF` at `lsphp${g_default_php_short}`.
- Port 7080: unconditional `ufw deny 7080`, then a scoped allow **only** when
  `SSTK_ADMIN_IP` is non-empty; otherwise the console stays on loopback.

The Vultr cloud-init workaround `apt-get install -y mariadb-server -o Dpkg::Options::="--force-confnew"`
is preserved verbatim.

**`9999-common-functions.txt`.** `GV_UBUNTU_VERSION=$(lsb_release -rs)` replaces
`lsb_release -r | cut -f2`, which depended on the output separator staying a tab and would
break the new release guard on a stray space. Added `GV_UBUNTU_CODENAME` and
`GV_SUPPORTED_UBUNTU_VERSION_1/_2` + `_CODENAME_1/_2`. `PHPVER=lsphp84`,
`g_php_versions=(83 84 85)`, `g_default_php_short=84`. Three new helpers:
`gf_set_default_php()` (one call site for the `php`/`phar`/`pecl`/`pear` alternatives, replacing
five hand-copied `update-alternatives --install` blocks),
`gf_list_installed_php()` (replaces `ls | grep lsphp` pipelines), and
`gf_normalise_admin_ip()` (validates the admin-IP placeholder on arrival, fails closed).
The OLS main-config template's `extprocessor lsphp` `path` directive was hard-coded to
`lsphp81`; it is now `${PHPVER}`.

**`56-ols_manage_admin_console.txt`.** `ufw allow 7080` (open to the world) is gone. New
`set_webconsole_bind_address()` rewrites only the `:7080` listener in
`admin_config.conf` — `*:7080` when an admin IP is configured, `127.0.0.1:7080` otherwise —
and `enable_webconsole_port()` deletes any inherited blanket rule first, then adds
`ufw allow from $SSTK_ADMIN_IP to any port 7080 proto tcp`. With no admin IP it adds
nothing and prints the SSH-tunnel command. `disable_webconsole_port()` removes all three rule
shapes so a stale rule from `v1` cannot survive. `enable_ols_console()` now calls
`set_webconsole_bind_address` then `enable_webconsole_port` then `gf_restart_webserver`.

**`04-manage_https.txt`.** The nginx `http2` regex block, four nginx-only functions and
`domain_conf="/etc/nginx/sites-enabled/${domain}"` are deleted; all four
`if nginx / elif ols` branches collapse to the OLS path. The certbot calls were already
`--non-interactive --agree-tos` upstream (`gf_enable_certbot_ols_site()`), so they were kept
as-is; the `snap install --classic certbot` failure path in `01-prepare_server.txt` now names
certbot instead of only printing "Failed!  Quitting process".

**`SSTK_ADMIN_IP` end to end.** Setting
`wordpress_app_settings → sstk_admin_ip` → `get_sstk_admin_ip()` → the
`SSTK_ADMIN_IP` key in `script_placeholders()`'s `$common_array` →
`##SSTK_ADMIN_IP##` → exported by `after-server-create-run-commands.txt` and
`ols_manage_admin_console.txt` → `gf_normalise_admin_ip()` in the receiving script. Validation
happens twice on purpose: `get_sstk_admin_ip()` rejects anything that is not a clean
IPv4/IPv6 address or CIDR block *before* the value can reach a shell command line (it is
interpolated into one, so this is the injection boundary), and the Bash helper re-checks after
the placeholder substitution so an unreplaced `##TOKEN##` becomes `""` rather than a literal.
Prefix lengths start at 1, so `0.0.0.0/0` and `::/0` are rejected outright — the "never open
7080 to the world" rule is enforced in code, not just in prose. A 23-case table
(accepts `203.0.113.5`, `203.0.113.0/24`, `::1`, `2001:db8::/32`; rejects `0.0.0.0/0`, `::/0`,
`$(id)`, `1.2.3.4; rm -rf /`, `1.2.3.4/24/extra`, `999.1.1.1`, a trailing space, a trailing
newline) was executed against the compiled pattern and all 23 behaved as intended.

**Script-version wiring.** See the `wordpress_app_script_version` decision in the log below;
the shipped default is now `v2` and the hard-coded `scripts/v1` path in
`wpapp_admin_init_is_readable()` is gone.

**Verification**

| Check | Result |
|---|---|
| `php -l` on every touched PHP file | clean |
| `bash -n` over `v2/raw/*.txt` | 77 OK; only `6G-Firewall-OLS.txt`, `7G-Firewall-OLS.txt`, `environment variables.txt` fail, exactly the three `bin/lint-sh.sh` already excludes |
| `bash bin/lint-php.sh` | 297 files OK |
| `bash bin/lint-sh.sh` | 150 scripts OK (v1 + v2) |
| `bash bin/guard-sstk-prefix.sh` | OK |
| `grep -rn "grep -[a-zA-Z]*P" v2/raw/` | empty (97 hits in `v1`) |
| `grep -rn "ufw allow 7080" v2/raw/` | one hit, and it is the comment explaining why not to |
| `grep -rn "lsphp81" v2/raw/` | comments only; every code path uses `$PHPVER` / `${g_php_versions[@]}` |

**Deviation — `ols1clk.sh --lsphp 84` is not implementable.** The plan specified it, but `v3.1`
validates `--lsphp` against `LSPHPVERLIST=(71 72 73 74 80 81)` and **silently ignores** an
out-of-list value, so `--lsphp 84` would have quietly installed lsphp81 and left OLS pointing at
the wrong binary. The install therefore runs `ols1clk.sh --quiet --lsphp 81` as the baseline,
installs 83/84/85 explicitly from `repo.litespeed.sh`, and then makes 84 the default via
`gf_set_default_php` plus a rewrite of the `extprocessor lsphp` path. The reasoning is in a
comment at the call site.

**Deviation — `grep -qP` became `grep -qE`, not `grep -q`.** The plan said "replace `grep -qP`
with POSIX `grep -q`", but the inherited patterns use `(...)` and `|`, which are *literal* in
BRE — the conversion would have silently broken 97 match conditions. Every hit was first
checked for PCRE-only constructs (`\d`, `\s`, `\w`, `\b`, `(?:`, lookarounds) and none existed,
so ERE is semantically identical.

**Deviation — nginx branches left in `08-backup.txt` / `10-misc.txt`.** Both files carry ~140
`if [ "$g_webserver_type" = "nginx" ]` blocks. They are unreachable (`gf_check_webserver()`
reports `ols` on any Simple Stack box, and the provisioning guard rejects a non-OLS
`webserver_type`), and every helper they call (`gf_remove_nginx_site`, `gf_restore_nginx_site`,
`gf_add_nginx_conf`) is still defined, so they cannot fatal. Deleting them means rewriting
interactive backup/restore and header-injection logic that has no test coverage; the risk
outweighs the tidiness. The plan's intent — "the executed path is OLS-only" — holds. The same
applies to the historic `10xx-upgrade_*` migration scripts, which only ever run against a
legacy server with an old recorded version.

**Deviation — `sstk_post_site_install` call deferred to Phase 7.** The plan lists the
`02-install_wordpress_site.txt` change under Phase 5 *and* Phase 7, annotated "(Phase 7)".
Adding the call before `66-sstk_post_install.txt` exists would make every site
install invoke an undefined function, so both land together in Phase 7.

### Step 6 — Vultr v2 + UpCloud providers ✅

Delivered:

- `includes/core/providers/class-vultr-v2.php` — `CLOUD_PROVIDER_API_VultrV2` (1308 lines),
  `https://api.vultr.com/v2/`, `Authorization: Bearer {api_key}`. `call()` dispatches to one
  private method per operation; `fetch_all()` walks Vultr's cursor pagination (bounded at 20
  pages) so `sizes`/`keys`/`snapshots` are complete rather than silently truncated at
  `per_page`; `execute()` retries once on 429 honouring `retry-after`.
- `includes/core/providers/class-upcloud.php` — `CLOUD_PROVIDER_API_Upcloud` (1275 lines),
  `https://api.upcloud.com/1.3/`, HTTP Basic `{user_name}:{user_password}`.
- `class-cloud-provider-api.php` — added `uses_api_key()` / `has_credentials()` (credential
  *shape*, not credential *values*) and `get_credential_fingerprint()`.
- `class-sstk-settings.php` — `is_api_key_empty()` is now provider-aware; `get_size_options()`
  replaces the deleted WooCommerce size source (see the Phase 1 leftover note below).
- `class-sstk-setup-wizard.php` — `sstk_select_provider()` reads the registry instead of a
  hardcoded Digital-Ocean-first list, and the "premium purchase" copy is gone.
- `SSTK_REST_Providers` — `uses_api_key` / `has_credentials` / `has_user_password` in the
  provider payload; `POST /settings/providers` accepts `user_name` / `user_password` and writes
  only the option keys the provider's credential shape actually uses.
- `src/admin/routes/ProviderSettings.jsx` — the credential form renders user name + password
  instead of an api key when `uses_api_key` is false.
- `functions-classes.php` — deleted the `init` hooks and accessors for the five classes Phase 1
  removed (see the fatal-error note below).

**Method mapping**

| `call()` | Vultr v2 | UpCloud 1.3 |
|---|---|---|
| `regions` | `GET /regions` → `id` => `city, country` | `GET /zone` → `id` => `description` |
| `sizes` | `GET /plans` → plan id => `vcpu/ram GB/disk GB` | `GET /plan` → **plan `name`** => `cpu/ram/storage` |
| `keys` | `GET /ssh-keys` → id => name | `GET /ssh_key` → name => name |
| `create` | `POST /instances` | `POST /server` (template clone) |
| `details` | `GET /instances/{id}` | `GET /server/{uuid}` |
| `status` | `GET /instances/{id}` + pending-action meta | `GET /server/{uuid}` + pending-action meta |
| `reboot`/`on`/`off` | `POST /instances/{id}/reboot\|start\|halt` | `POST /server/{uuid}/restart\|start\|stop` |
| `delete` | `DELETE /instances/{id}` | `DELETE /server/{uuid}` |
| `resize` | `PATCH /instances/{id}` `{plan}` | `POST /server/{uuid}/modify` `{server:{plan}}` |
| `snapshot` | `POST /instances/{id}/snapshot` | **not supported** (see below) |
| `delete_snapshot` | `DELETE /snapshots/{id}` | **not supported** |
| `list_snapshots` | `GET /snapshots?instance_id=` | **not supported** |
| `list_all_snapshots` | `GET /snapshots` | **not supported** |
| `pending_snapshot_status` | `GET /snapshots/{id}` | **not supported** |
| `ssh_create` | `POST /ssh-keys` (returns numeric id) | `POST /ssh_key` (best effort) |
| `test_connection` | `GET /account` | `GET /account` |

Feature flags: Vultr sets `snapshots`, `snapshot-delete`, `snapshot-list`, `resize`,
`ssh_create`, `test_connection`, `backups` and `enable_backups_on_server_create` true.
UpCloud sets `resize`, `ssh_create`, `test_connection` true and **every snapshot/backup flag
false**.

**Decision — `status` is synthesised, because neither API returns a pollable operation id.**
`handle_async_action()` calls `call( $action )` and, on `in-progress`, stores the action name and
then later calls `call( 'status', $attributes )` with the *original* attributes. Vultr answers a
power change with `204 No Content` and UpCloud with `202` and no body, so there is nothing to
poll. Both providers therefore:

1. return `in-progress` with the action name as `action_id`, and stamp
   `sstk_{provider}_power_started_{md5(id)}`;
2. on `status`, read `get_post_meta( $attributes['post_id'], 'sstk_server_action' )` to learn
   which action is pending and infer completion from `power_status` (Vultr) or `state`
   (UpCloud) — `off` completes on `stopped`, `on` on `running`, `reboot` on `running` and not
   `locked`;
3. fall back to a 1800 s elapsed-time backstop (`_POWER_ACTION_TIMEOUT`) so a lost signal
   cannot wedge a server in "performing ..." forever, and clear the stamp once complete.

`call( 'status' )` has exactly one caller in the tree (`handle_async_action`), which is why
designing it around the pending-action meta was safe rather than speculative.

**Decision — UpCloud snapshots are declared unsupported rather than half-mapped.** UpCloud
identifies a backup by the storage device it belongs to plus the date it was taken, which
cannot be expressed as a `snapshot_id` to delete, list or poll. Setting the feature flags false
hides the snapshot buttons (the backup tab gates them on `get_feature_flag( 'snapshots' )`) and
`call()` returns a `WP_Error`, so an unmapped API is never exposed.

**Decision — UpCloud plans are keyed by `name`.** UpCloud plans have no id; `name` (e.g.
`2xCPU-4GB`) is the stable identifier and the array key. The create call also has to **clone** a
storage template (`action: "clone"`, `storage: <uuid>`) resolved from `GET /storage/template`,
so a 24.04 Ubuntu template is looked up first with an exact version match and then by family.

**Decision — `user_data` encoding differs.** Vultr expects base64 and does not encode it for
you; UpCloud takes a plain script. Both are encoded at the call site with a comment saying that
base64 is an encoding, not a protection, and that no credential may be placed in the string.

**Decision — no resize auto-start cron for either provider.** DigitalOcean's parent provider
schedules a cron to power a resized server back on. Neither new provider does, because
`tabs-server/resize.php` **ignores** the return value of `call( 'resize' )` and sets
`sstk_server_current_state = 'in-progress'` with a message telling the operator to restart the
server manually. Adding a second, competing mechanism would only create a race.

**Decision — credential shape is a first-class concept.** UpCloud authenticates with a user
name and password, so:

- `uses_api_key()` / `has_credentials()` were added to `class-cloud-provider-api.php` and
  `is_api_key_empty()` delegates to them. Without this, `sstk_upcloud_apikey` is permanently
  empty and the SSH-key, size and cache sections of the settings screen stay hidden no matter
  what the operator enters.
- `settings()` on the UpCloud provider prepends the two credential fields and **removes**
  `sstk_upcloud_apikey` / `sstk_upcloud_keynotes`, because a field nothing reads is an invitation
  to paste the account password into it.
- `get_secret_key()` returns the password, so the inherited `get_cache_key()`, `store_cache()`,
  `get_cached_data()`, `clear_cache()` and `get_cached_transient_list()` stay consistent with no
  base-class change.
- `SSH key` material is installed from the create payload's inline
  `login_user.ssh_keys.ssh_key[]` (sourced from `sstk_upcloud_public_sshkey`), so
  `sstk_upcloud_sshkey_id` is a local bookkeeping marker only and is deliberately **not** part of
  `credentials_available()`.

**Decision — `get_credential_fingerprint()` on the base class.** `sstk_can_connect_to_provider()`
and its two siblings keyed the "connection succeeded" transient off `get_api_key()`. For
UpCloud that is always `''`, so every account on the install shared one transient and account
A's successful test would vouch for account B. The base class now resolves
`get_secret_key()` first and falls back to `get_api_key()`, and all three call sites use it.

**Fixed — two Phase 1 leftovers that were live fatals.**

1. `functions-classes.php` still ran `add_action( 'init', 'sstk_init_sstk_woocommerce' )` →
   `new SSTK_WOOCOMMERCE()`, and still ran `sstk_init_sstk_data_sync_rest()` →
   `new SSTK_DATA_SYNC_REST()`. Both classes were deleted in Phase 1 and neither hook was
   guarded by `class_exists()`, so both fatal on every request. The `init` hooks and their
   accessors are removed (the VPN, Basic Server and Stable Diffusion accessors went with them;
   their hooks were guarded, so they were dead rather than fatal).
2. `class-sstk-settings.php::get_generic_size_fields()` called
   `SSTK_WOOCOMMERCE()->get_wc_size_options()`, which would fatal on the provider settings
   screen — the exact screen Phase 6 depends on. Replaced with `get_size_options()`, a fixed
   four-slot list (`small`/`medium`/`large`/`xlarge`) exposed through the
   `sstk_provider_size_options` filter. Nothing else in the tree references a slot name, and
   the React provision form sends a raw plan id down the `size_raw` path, so the slots only
   drive the classic settings mapping.

`bin/guard-sstk-prefix.sh` now forbids `SSTK_WOOCOMMERCE` and `SSTK_DATA_SYNC_REST` so neither
can reappear.

**Registry and requires were already correct.** `simple-stack.php` lists
`vultr-v2`, `upcloud`, `digital-ocean` and `custom-server` (Phase 1), `simple-stack.php` requires both
new provider files behind `file_exists` guards, `get_provider_api()` needs no change
(`vultr-v2` → `CLOUD_PROVIDER_API_VultrV2`, `upcloud` → `CLOUD_PROVIDER_API_Upcloud`), and
`class-sstk-setup-wizard.php` already handled both slugs in all three of
`sstk_api_token_instructions()`, `sstk_get_other_provider_fields()` and the save handler. Digital
Ocean stays hidden by default through the Phase 1 filter.

**Verification**

| Check | Result |
|---|---|
| `php -l` on both new providers and every touched file | clean |
| `php node_modules/.cache/t_providers.php` | **110 passed, 0 failed** |
| `php node_modules/.cache/t_symbols.php` | 0 undefined `SSTK_*` functions or classes |
| `bash bin/lint-php.sh` | 299 files OK |
| `bash bin/guard-sstk-prefix.sh` | OK |
| `grep -rn "__return_true" includes/core/api includes/core/providers` | empty |
| `npm run build && npm run verify:css` | 259 rules, 247 scoped; OK |
| `grep -rn "admin-ajax" assets/dist/` | empty |

The 110-assertion harness (gitignored, `node_modules/.cache/t_providers.php`) stubs WordPress
and drives a canned HTTP FIFO through both classes: slug→class resolution, feature flags,
credential gating with and without secrets, cache-key stability and secret-freedom, region
parsing, two-page cursor pagination, a 401 surfacing as a `WP_Error` with the API's error code, a
429 retried once then succeeding, the `active`/`off`/`locked`/`0.0.0.0` state mappings, the full
power-action lifecycle including the timeout backstop, create-body construction (plan mapping,
`os_id` resolution, base64 vs plain `user_data`, `sshkey_id` array, hostname normalisation),
argument-validation rejections, UpCloud's Basic-auth header, UpCloud plans keyed by `name`,
UpCloud's single-object `ip_address` quirk, snapshot refusal (asserting **no** HTTP call is made),
power/resize URLs, credential fingerprints, and a scan asserting neither secret ever reaches the
log.

**Deviation — `test_connection` was not run against a live key.** The plan's acceptance criterion
is a live key in a staging install; no staging install or provider account was available here.
Both classes are exercised only against canned fixtures. See the open item below.

### Step 7 — OLS vhost hardening + `sstk_post_site_install` ✅

Delivered:

- `scripts/v2/raw/9999-common-functions.txt` — `gf_add_ols_conf()` hardened; new
  `gf_harden_ols_conf()`; no-op `sstk_post_site_install()` plus a guarded `source` of the
  hook file.
- `scripts/v2/raw/66-sstk_post_install.txt` — new post-install hook.
- `scripts/v2/raw/02-install_wordpress_site.txt` — calls the hook after the post-processing
  script.
- `scripts/v2/install_wordpress_site.txt` — downloads the hook and exports the plugin list.
- `script-handlers.php` — `SCRIPT_POST_INSTALL_URL`, `SCRIPT_POST_INSTALL_NAME`,
  `SSTK_SECURITY_PLUGINS`.
- `class-sstk-app.php` — `get_post_site_install_plugins()`.
- `class-simplestack-rest-security.php` — probe path fixed, vhost handler read,
  `parse_probe_output()` split out.
- `src/admin/routes/SecurityStatus.jsx` — docblock only; the bundle hash did not change.

`scripts/v1/` is untouched, so servers pinned to `v1` keep the old behaviour.

#### Deny and rewrite rules (`gf_add_ols_conf`)

| Rule | Before (v1) | After (v2) |
|---|---|---|
| Deny context | `error_log`, `wp-config-sample.php`, `\.pl`, `\.cgi`, `\.lua`, `\.perl`, `\.sql`, `\.sh`, `wp-config.php`, `php.ini`, `\.log` | adds `\.env`, `\.git`, `\.htaccess`, `\.htpasswd`, `composer\.(json\|lock)`, `\.user\.ini` and escapes the previously bare `.` in the four names that had them |
| Upload execution | `^/wp-content/uploads/.*\.php$` | `^/wp-content/(uploads\|files\|updraft\|cache)/.*\.(php\|phtml\|phar\|php[0-9])$` |
| Backup directory | — | `^/wp-content/updraft/.*$` denied entirely |
| Dotfiles | — | `"(^\|/)\.(?!well-known/)"` denied |
| ACME | implied | explicit `context /.well-known/acme-challenge/` with `allowBrowse 1` |

The ACME context is not decorative: the dotfile rewrite would otherwise make the
exemption for `/.well-known/` unreachable, and a certbot renewal that silently starts failing
six weeks after go-live is the hardest kind of regression to attribute.

#### Why a second applier instead of "replicate into `httpd_config.conf`"

The plan asked for the same rules in `gf_add_domain_ols_httpd_conf()` "for the
`httpd_config.conf` block". That block is a `virtualhost` stanza — it has no `rewrite` or
`context` scope, so the rules cannot live there. The requirement behind it is "a site must never
be registered without the protections", so `gf_harden_ols_conf()` applies them to the per-vhost
conf and is called from both places that can produce a served vhost:

| Caller | Why |
|---|---|
| `gf_add_ols_conf()` | new site; the template already carries the rules |
| `gf_add_domain_ols_httpd_conf()` | domain registration — also the path a restored vhost takes |
| `gf_restore_ols_site()` | `rsync` from a backup; the restored conf predates the template |

`gf_harden_ols_conf()` is idempotent (it returns early when both the hardened context and the
hardened rules are already present), rewrites only the context line and the `rules` region,
inserts the ACME context when absent, and warns instead of failing when a conf has no rewrite
block at all. It passes the replacement text through `ENVIRON[]` rather than `awk -v`, because
`-v` processes escape sequences and would strip the `\.` that OLS needs.

#### `sstk_post_site_install()` contract

```bash
sstk_post_site_install <domain> <site-linux-user> <webserver-type>
```

| Step | Detail |
|---|---|
| Guards | Empty domain/user, or a missing `wp-config.php`, logs and returns 0 |
| Hardening | `wp config set DISALLOW_FILE_EDIT true --raw`, run as the site user |
| Plugins | Space separated slugs from `SSTK_SECURITY_PLUGINS`, installed then activated |
| Source of the list | `sstk_post_site_install_plugins` filter, **empty by default** |
| Slug validation | PHP side reduces to `[A-Za-z0-9._-]` with an optional `author/plugin`, and the Bash side re-checks the same set plus `..` |

The hook is downloaded outside the `&&` chain that guards the files an install cannot proceed
without, and `9999-common-functions.txt` defines a no-op fallback before sourcing it. A failed
`wget`, a stale deploy or a typo in the URL therefore cannot break a site install — the hook
degrades to doing nothing.

#### Two defects found while validating Phase 7

1. **The security probe was pointing at the wrong directory.** Phase 2 ran
   `cd /var/www/<domain>` before `wp plugin list`, but WordPress lives in
   `/var/www/<domain>/html` (the site user's home *is* the `html` directory, which is why the
   `su - <user> -c "wp ..."` calls elsewhere work without `--path`). Every probe would have
   returned zero plugins and `file_edit_disallowed: false` on sites that were fully hardened.
2. **`php -v` does not answer for the site.** On an OpenLiteSpeed host the CLI `php` is not
   necessarily the handler serving the vhost, and is absent entirely when no CLI PHP is
   installed. The probe now also greps the vhost for `lsapi:lsphpXX` and prefers that, falling
   back to `php -v` only when the grep is empty — so the old three-packet parse still works.

#### Verification

| Check | Result |
|---|---|
| `bash bin/lint-php.sh` | 299 files OK |
| `bash bin/lint-sh.sh` | 355 scripts OK (151 `raw/` + 204 command wrappers) |
| `bash bin/guard-sstk-prefix.sh` | OK |
| `php node_modules/.cache/t_symbols.php` | undefined sstk symbols: 0 |
| `php node_modules/.cache/t_providers.php` | 110 / 110 |
| `php node_modules/.cache/t_security.php` | 28 / 28 (new) |
| `bash node_modules/.cache/t_harden.sh` | 25 / 25 (new) |
| `bash node_modules/.cache/t_postinstall.sh` | 18 / 18 (new) |
| `npm run build && npm run verify:css` | OK; both bundle hashes unchanged |
| `grep -rn "admin-ajax" assets/dist/` | empty |
| `grep -rn "ufw allow 7080" includes/` | v1 only, plus the explanatory comment in v2 |
| `git status includes/.../scripts/v1/` | clean — v1 untouched |

The three new harnesses are gitignored scratch under `node_modules/.cache/`. `t_harden.sh`
drives the real `gf_harden_ols_conf()` against a v1-era vhost conf and asserts the hardened
rules land, a second run is byte-identical, a conf that already has everything is untouched, a
conf with no rewrite block warns instead of injecting rules, a missing conf is a clean skip, and
the optional vhost-name argument is honoured. `t_postinstall.sh` stubs `su` and asserts the
fallback no-op, the argument guards, the missing-`wp-config` skip, install-then-activate ordering,
the empty-list no-op, and that a hostile slug (`../escape`, `;rm`) never reaches the command line.
`t_security.php` drives the real `build_command()` and `parse_probe_output()`.

**Deviation — the live 403 assertions were not run.** The plan's acceptance step is
`curl -sI https://<site>/wp-content/uploads/test.php` → 403 and `/.env` → 403 on a provisioned
site. No server was available here, so the rules are verified by template assertion and
fixture test only. Recorded as an open item.

**Deviation — the hook is called next to the post-processing script, not at the end of the
file.** The plan says "next to the existing `$post_processing_script_site` call". It is called
immediately after that block and before the blueprint section. The blueprint block only
installs and activates plugins, so it cannot undo `DISALLOW_FILE_EDIT`, and the post-processing
script is the last operator-supplied step — running the hook after it means the operator's
own plugin changes are already in place.

### Step 8 — QA, cleanup, release ✅

**Documentation.** `docs/SSTK.md` is new (314 lines, 8 sections): the architecture diagram,
the "two names, one plugin" rule, the action bridge, the REST route reference with its three
security guarantees, the admin UI and its four Tailwind containment mechanisms, the
raw-vs-wrapper script distinction with v1/v2 coexistence, the OLS hardening rule block verbatim,
the `sstk_post_site_install` contract, and the two new providers. It deliberately does not
restate `AGENTS.md` — it points at it. `README.md` was replaced outright: 1245 lines of upstream
Simple Stack marketing (a DigitalOcean marketplace droplet, a MailPoet signup form,
`onedogsolutions.com` pricing links) became 126 lines describing what this fork actually is,
including the Admin IP / port 7080 step that an operator will otherwise miss on install.

**`uninstall.php` — a credentials defect, found and fixed.** The file still deleted
`sstk_license_key`, `sstk_addons_compatible_last_version_checked`, three `wisdom_*` options and the
`sstk_wisdom_custom_options_first_run_done` transient — all dead since Phase 1. Removing those
exposed the real bug underneath: the settings purge was

```php
foreach ( $sstk_setup->settings_defaults as $k => $v ) { … }
```

but `SSTK_Setup::$settings_defaults` is declared and **never assigned anywhere in the tree**, so
the loop iterated an empty array and `sstk_settings` — the option holding the provider API keys
and the encrypted SSH keys — **was never deleted on uninstall**. A stale credential in the
database is still a credential. The file now deletes `sstk_options` and `sstk_settings` whole, and
the comment says why: filtering key by key silently leaves behind every key the file does not
happen to know about. The standalone list grew to cover `sstk_plugin_first_time_activated`,
`sstk_crons_not_running_email_next_send_date` and the three `sstk_public_*_page_id` options, each
confirmed to be written by live code before being added, and `SSTK_Setup`'s require is gone.
Server, site and log posts are still deliberately retained, and the header now says so: they
describe machines that keep running after the plugin is gone.

**The test toolchain had never worked.** `vendor/bin` was empty, and `composer install` had been
broken since Phase 0 raised the PHP floor: the committed lock still said `platform.php: ^7.4`
against `composer.json: >=8.1`, and the locked `phpunit 7.5.20` (`php: ^7.1`) cannot install on
PHP 8.5 at all. The scaffold also targeted WP 5.9.5 while the plugin requires 6.5, and all four
`tests/phpunit/rest-api/*.php` classes extended `WPTest\Test\TestCase` from
`cyruscollier/wp-test` — a package pinned to phpunit `^7.5.18`, i.e. unusable.

Rewritten: `composer.json` (phpunit `^9.6`, wp-phpunit `^6.7`, `yoast/phpunit-polyfills`,
`johnpbloch/wordpress ^6.7`, a `test` script, `allow-plugins`), the lock, `phpunit.xml` for the
PHPUnit 9 schema, and `tests/phpunit.php` as a plain wp-phpunit bootstrap. The four test classes
now `extends WP_UnitTestCase`. `WP_TESTS_CONFIG_FILE_PATH` is resolved with `dirname( __DIR__ )`
rather than through a `<const>` in `phpunit.xml`, because a relative path there resolves against
whatever directory PHPUnit happened to be started from.

**Composer resolution surfaced two real security findings**, neither of them scope creep:

- `wp-coding-standards/wpcs 2.3.0` is blocked by advisory `PKSA-mh9b-91zm-m1gy`; bumped to `^3.0`
  with `dealerdirect/phpcodesniffer-composer-installer ^1.0`.
- `phpseclib 3.0.19`, the version the tracked `vendor/` shipped, is affected by **12** advisories.
  It is now 3.0.59 (with `paragonie/constant_time_encoding` 2.6.3 → 3.1.5), installed into the
  tracked tree with `composer install --no-dev`. Composer also removed the now-unreferenced
  `vendor/edd_sl/SSTK_EDD_SL_Plugin_Updater.php`; the empty `vendor/edd_sl/` and
  `vendor/wisdom_plugin/` directories were pruned. `phpseclib3\Net\SSH2`, `SFTP`, `Crypt\RSA` and
  `PublicKeyLoader` were each confirmed to autoload afterwards.

**PHPUnit was proved runnable, not assumed runnable.** Dev dependencies were installed to a scratch
vendor dir: `phpunit --version` reports **9.6.39** on PHP 8.5.2, `wp-phpunit` 6.9.10 ships both
`includes/functions.php` and `includes/bootstrap.php`, and running the suite gets all the way to
`mysqli_real_connect(): (HY000/2002)` — bootstrap, config path, `ABSPATH` and WP core 6.9.12 all
resolve. The only blocker is that this environment has no MySQL server (no `mysql`/`mysqld`/
`mariadb` binary exists, nothing listening on 3306). Recorded as an open item rather than papered
over.

**`phpcs.xml`.** A ruleset that references a sniff WPCS 3 deleted is a **hard failure**, not a
warning: `WordPress.PHP.DisallowShortTernary` had to go. Added
`Universal.Files.SeparateFunctionsFromOO.Mixed` to the exclusions — WordPress plugins
conventionally pair a class with a function-style accessor (`SSTK()`, `SSTK_SERVER()`,
`sstk_rest_api()`), and one of those pairings is ours. `minimum_supported_wp_version`
4.9 → 6.5. An inline `phpcs:ignore` was tried first for that one; it detached the function's
docblock and produced `Missing doc comment`, so the exemption belongs in the ruleset instead.
`includes/core/api/` is now phpcs-clean; 38 advisory errors remain across the six inherited
`rest-api/` files.

**The `__return_true` sweep found one real hole and one false alarm.**

- `/simple-stack/v1/command/{id}/{name}/{status}/{cmdid}/` **had to stay public** — the provisioning
  scripts `wget` it with no WordPress credentials — but `perform_command()` fired
  `do_action( "sstk_…_command_{$name}_{$status}" )` *before* `update_command_status()`'s
  unknown-command bail, so an anonymous caller could trigger completion hooks for any post id and
  command name it guessed. It now returns `sstk_unknown_command` unless
  `get_command_attributes( $id, $name )` finds the pending-command transient — the same condition
  the status update always enforced — so no legitimate caller changes behaviour.
- `/simple-stack/v1/handle-git-webhook/{randomid}/{id}/` was **already correct**: it verifies HMAC-SHA1
  with `hash_equals()` against the per-site `sstk_app_git_push_to_deploy_secret_key`. Only the
  unprotected `$_SERVER['HTTP_X_HUB_SIGNATURE']` access needed hardening — `empty()` for a missing
  header, a `str_contains( '=' )` guard for a malformed one, and `wp_unslash()`.

Both registration sites now carry a comment stating why the route is public, so the next sweep
does not "fix" them into breaking provisioning.

#### Verification

| Check | Result |
|---|---|
| `bash bin/guard-sstk-prefix.sh` | OK |
| `bash bin/lint-php.sh` | 299 files OK |
| `bash bin/lint-sh.sh` | 358 scripts OK (151 `raw/` + 204 wrappers + 3 gate tools) |
| `composer validate` | `./composer.json is valid`, lock in sync |
| `composer lint` | 299 files OK |
| `npm ci && npm run build` | 81 packages; both bundle hashes unchanged (`style-CYOBWG1A.css`, `admin-xl7RrI3C.js`) |
| `npm run verify:css` | OK — 259 rules, 247 scoped utility selectors |
| `t_providers.php` | 110 / 110 |
| `t_security.php` | 28 / 28 |
| `t_symbols.php` | undefined sstk symbols: 0 |
| `t_adminip.php` | 23 / 23 |
| `t_harden.sh` | 25 / 25 |
| `t_postinstall.sh` | 18 / 18 |
| `grep -rn "__return_true" includes/` | 2 — both required-public, both audited above |
| `grep -rn "ufw allow 7080" includes/` | 3 — one live in frozen `v1/`, one commented in `v1/`, one explanatory comment in `v2/` |
| `grep -rn "SSTK_License\|wisdom_\|woocommerce" includes/ --include='*.php'` | 21 — prose, example strings, two dormancy probes |

The three "must be empty" greps in the plan are not empty, and none of the three should be made
empty by editing code. Each hit is accounted for below.

**Deviation — the two public routes are exempt from the `__return_true` sweep.** They cannot be
gated on `manage_options` without breaking server provisioning and GitHub push-to-deploy.
`bin/guard-sstk-prefix.sh` now asserts that the set is exactly these two files, which is stronger
than an empty grep: it fails if a *third* one appears, and it also fails if either of these two is
deleted or gated. Four deliberate violations were run through it before it was accepted.

**Deviation — `ufw allow 7080` survives in `scripts/v1/`.** Phase 5 froze `v1` deliberately so
servers provisioned before the modernization keep the scripts they were built with; patching a
frozen script changes behaviour on machines that cannot be tested from here. The one live
occurrence is `v1/raw/56-ols_manage_admin_console.txt:45`. Every server that matters runs `v2`,
where the line is gone and replaced by deny-by-default plus the `SSTK_ADMIN_IP` allow.

**Deviation — the `woocommerce` matches are kept.** They are prose, example strings in help text,
and two probes: `sstk_is_woocommerce_activated()` asks whether the *third-party* store plugin is
active (a legitimate question regardless of the deleted module), and `sstk_is_wc_module_enabled()`
returns false because `SSTK_WooCommerce_Init` no longer exists. That false is the dormancy
mechanism behind 13 `class_exists` door checks, so removing the helper would resurrect WaaS post
types that Phase 1 decided to keep dormant rather than delete.

### Step 9 — Live verification on One Dog Test ✅ (3 defects found, 2 fixed)

Installed the built artifact on `https://test.onedog.solutions` (WordPress 7.1.5, PHP 8.3.37,
OpenLiteSpeed) through the Novamira MCP surface and exercised it for real. Deployment path:
`create-upload-link` PUT → `wp plugin install <zip>` → `wp plugin activate`. The zip is 3.1 MB /
1365 entries and excludes `node_modules`, `.git`, `vendor/bin`, `tests`, `wordpress` and the
PHPUnit config, so the 81 MB dev-tree WordPress core is not shipped.

Activation was sequenced to be non-destructive: the full `required_files()` bootstrap was proved
in a throwaway request first (78 classes, 0 warnings, 86.8 ms on PHP 8.3), a temporary mu-plugin
fatal guard was installed so a bad admin-context load could not lock the operator out, and only
then was the plugin activated. Activation created 3 pages (#234 Cloud Servers, #235 Cloud Apps,
#236 Deploy Server), 4 roles, 8 capabilities, 17 CPTs and 10 options, and left the 33 already
active plugins untouched. The mu-plugin guard and the `.ss-upload/` staging directory were
deleted after the run.

| Area | Result |
|---|---|
| REST route table | 13 functional paths + namespace index, exactly the documented set |
| Anonymous access | 401 `rest_forbidden` on every route |
| Authenticated list/get | 200; unknown ids 404 `sstk_{server,site}_not_found` |
| Secret redactor | `api_key`, `root_password`, `ssh_private_key` (PEM), `Authorization`, inline `password=`/`token=` → `********`; non-secrets preserved |
| Admin menu | 1 top-level `Simple Stack` (`sstk_manage_servers`) + 7 submenus |
| Mount point | `render_sstk_app()` emits `#simplestack-admin` with `data-route="/servers"`, no "bundle missing" notice |
| Screen scoping | `is_sstk_screen()` true for the 4 app screens, false for `edit.php`/`index.php`/settings |
| Bundle enqueue | registered with content-hash version `xl7RrI3C`, dep `wp-i18n`, **footer** placement, inline `window.simplestackAdmin` bootstrap; no-op on other screens |
| Assets | `admin-xl7RrI3C.js` 200 (288 kB), `style-CYOBWG1A.css` 200 (23.8 kB), missing asset 404 |
| Provisioning URLs | every placeholder (`01-prepare_server`, `9999-common-functions`, `6G`/`7G`, `66-sstk_post_install`, `02-install_wordpress_site`) → 200 from `scripts/v2/` |
| Provider guards | credentials absent → 400 `sstk_provider_credentials_missing` without a network call; unknown slug → 400 |
| Public pages | #234–#236 all 200 |

**Defect 1 — optional integer parameters could never be omitted (FIXED).** `GET /sites` (no filter)
and `GET /activity` returned `400 rest_invalid_param: server_id`. `arg_int()` always emitted
`'default' => 0`, so `get_param()` handed the validator `0` instead of `null`, and `0 < min 1`
failed even though the parameter was declared optional. Unfiltered `/sites` and `/activity` were
therefore unusable. `arg_int()` now types the default `?int $default = null`, emits `'default'`
only when non-null, and returns `! $required` for `null`/`''`; `arg_string()` gained the same
null tolerance. Verified live: `/sites` and `/activity` → 200, while `server_id=abc`,
`server_id=0`, `per_page=0`, `per_page=500`, `page=abc` and `limit=0` still 400 and the boundary
`per_page=200` still 200. Side effect worth keeping: an anonymous `GET /sites` now returns 401
rather than 400, because validation no longer fails before the permission check.

**Defect 2 — app settings were unreadable outside admin (FIXED).** `sstk_get_option()` delegates to
`rwmb_meta()`, which only resolves Meta Box fields registered *for the current request*; the
WordPress App settings metabox registers in admin, while commands are built during REST requests
and cron. Consequence: `get_sstk_admin_ip()` returned `''` even when a valid address was
stored, so the port-7080 allow-list was **silently ignored** (fail-closed, but inert), and
`get_configured_scripts_version()` always fell through to `v2`. Both now read
`sstk_get_early_option()` first and fall back to `sstk_get_option()`. The script-version read also
rejects a folder that does not exist, so a typo cannot publish 404 script URLs. Verified live
through the real methods: 15/15 address cases correct (valid IPv4/CIDR/IPv6 accepted and trimmed;
`0.0.0.0/0`, `::/0`, `; id`, `$(whoami)`, `` `id` ``, `999.1.1.1`, `/33` rejected), the placeholder
carries the configured address, and `v1`/`v2` are honoured while `v9` and `../v2` fall back to
`v2`.

**Defect 3 — the admin-IP setting has no UI (RECORDED, NOT FIXED).** `scripts_fields()` holds the
`sstk_admin_ip` field, but `all_fields()` has `$script_fields = $this->scripts_fields();`
commented out ("Removing script fields for now") and the `wordpress-app-scripts` tab is commented
out too. Confirmed on the live site: 690 registered settings fields, neither
`sstk_admin_ip` nor `wordpress_app_script_version` among them. Re-enabling that section
would also resurrect SSTK's legacy script-version picker, which can select the frozen non-OLS `v1`
set — a regression risk for an OLS-only build — so it was left dormant and the setting is
currently settable only programmatically (`wp option patch update sstk_settings
sstk_admin_ip <addr>`), which the fix above now honours end to end.

**Environment finding — Meta Box is a hard prerequisite.** `meta-box` is declared required
(`'required' => true`) by the bundled TGM list and was *not* installed on the test site, so
`rwmb_meta` did not exist and `sstk_get_option()` returned `false` for every setting. Meta Box
5.15.5 was installed and activated to complete the test and is still active; without it no
Simple Stack setting can be read at all.

**Operational note — OPcache.** `wp plugin install --force` replaces files but `opcache.
revalidate_freq` is 60 on this host, so the old bytecode kept serving after the first redeploy and
masked the fix. A redeploy of plugin files must be followed by `opcache_invalidate()` or an
FPM reload; this is why the first post-fix probe still showed the pre-fix `default: 0` argument.

### Step 9 follow-up — Corrected release candidate on One Dog Test 🟡

The first follow-up package exposed a packaging-only fatal before activation: an unanchored
`rsync --exclude='vendor/'` removed every nested Meta Box `vendor/` directory, including
`mb-settings-page/vendor/autoload.php`, so `MBSP\Loader` could not load. The site was recovered via
SSH/WP-CLI with plugins skipped, the package was rebuilt with `--exclude='/vendor/'`, and all seven
production autoloaders were verified before installation. The corrected v2 package activated
successfully and the original plugin backup was retained.

Live REST testing then found two further defects:

1. **Provider presence booleans were redacted.** `sstk_redact_secrets()` masked every value under a
   credential-shaped key, including `credentials_available`, `uses_api_key` and `has_*`. Unconfigured
   providers therefore appeared ready and UpCloud rendered API-key controls. Boolean and null
   presence signals now remain unchanged while credential strings and numbers remain masked. A
   regression test covers both behaviours.
2. **React assets were not enqueued on submenu screens.** `is_sstk_screen()` assumed a
   `sstk_page_` prefix. WordPress derives these hooks from the sanitized top-level menu title, so the
   real hooks are `simple-stack_page_simplestack-{sites,providers,activity}`. The mount existed but the
   bundle and inline bootstrap were absent on all three submenu pages. The hook list now uses the
   real prefix, with regression assertions.

After PHP lint, PHPCS and `git diff --check` passed, v5 was packaged with the root-anchored vendor
exclusion, uploaded, hash-verified, installed with `--force --no-activate`, and followed by an
OPcache reset. Live provider output now preserves the expected booleans, and headless Chrome
rendered every React route without uncaught errors. The v5 archive still contains empty `scripts/v1/`
directories inherited from the source tree, but no v1 files; the v1 provisioning URL returns 404 and
runtime selection remains hard-locked to v2.

This follow-up is 🟡 rather than ✅ because destructive provider operations, real Ubuntu/OLS
provisioning, backup/sync, Monit/Netdata and database uninstall still require separate approval and
credentials.

## Decisions & deviations log

- 2026-11-23 — **Production packaging must anchor the root vendor exclusion.** `vendor/` excludes
  every nested dependency tree; `/vendor/` excludes only the plugin's root Composer directory. The
  unanchored form caused a site-wide `MBSP\Loader` fatal. Every subsequent package verified all
  seven autoloaders before upload and matched local/remote SHA-256 hashes before installation.
- 2026-11-23 — **Presence metadata is not credential material.** Secret redaction now preserves
  booleans and null under credential-shaped keys. This keeps the REST contract useful without
  weakening masking of actual strings, numbers, PEM blocks or inline assignments.
- 2026-11-23 — **WordPress submenu hooks come from the sanitized menu title, not the menu slug.** The
  `Simple Stack` title yields `simple-stack_page_*`; assuming `sstk_page_*` or `simplestack_page_*`
  leaves the React container blank on submenu screens. The regression test now asserts the real hook
  names emitted by the live admin page.


- 2026-11-23 — **The OLS-only pass is code-complete but not runtime-certified.** Multisite now uses
  OpenLiteSpeed vhosts, shared document roots and per-vhost HTTPS state; phpMyAdmin restrictions
  and vhost removal are exact/idempotent edits; wildcard SSL forwards credentials for every
  supported DNS provider; and site removal validates database identifiers before destructive
  SQL. Local syntax/residual gates pass, but these paths still require a live OLS server before
  release. `shellcheck` is unavailable on this workstation, so shell validation is `bash -n` only.
- 2026-11-23 — **Retired modules were removed rather than left dormant.** VPN, Basic Server,
  Stable Diffusion and Logtivity no longer have active code, assets, templates, cron branches or
  permission registrations. The unused Basic Server help-popup template was deleted and the guard
  now scans HTML templates so this residue cannot return unnoticed.
- 2026-11-23 — **Provisioning is hard-locked to script version `v2`.** The settings field, popup
  selector, directory scanning and caller-supplied version paths were removed; both REST server
  creation paths and the legacy server form now use `get_configured_scripts_version()`.
- 2026-11-23 — **The naming guard is excluded from mechanical rebranding.** Its legacy-token grep
  patterns are intentional detection logic, not product identifiers. Excluding
  `bin/guard-sstk-prefix.sh` restores a zero-change rebrand dry run without weakening the guard.
- 2026-11-20 — **Live testing changed two helpers; both changes are hardening, not redesign.**
  `arg_int()` no longer stamps `0` onto every optional integer, because WordPress validates the
  *default* it is given and `0` failed a `min 1` bound — which silently made unfiltered `/sites`
  and `/activity` unusable. Keeping the parameter unbounded when it is omitted is the correct
  REST contract, and the 400 gone from anonymous calls is a small information-disclosure win too.
- 2026-11-20 — **`sstk_get_early_option()` is now the primary read for settings consumed outside
  admin.** `sstk_get_option()` is fine on a settings screen but returns `false` in REST and cron,
  because Meta Box only resolves fields registered for the current request. That is not something
  a caller can see: the value simply looks empty. Any future setting that ends up on a shell
  command line should be read through the early accessor and validated on the way out, exactly as
  `get_sstk_admin_ip()` does.
- 2026-11-20 — **The dormant `wordpress-app-scripts` settings tab was deliberately left dormant.**
  Re-enabling it would restore the admin-IP field, but it also restores SSTK's script-version text
  box, which accepts the frozen non-OLS `v1` script set. Making one security field reachable is
  not worth handing an operator a one-keystroke way to provision the wrong stack; the setting is
  documented as programmatic until a Simple Stack-owned field is added to the React settings
  screen.

- 2026-11-20 — `bin/guard-sstk-prefix.sh` now enforces three things Phase 8 had previously only
  reasoned about: `__return_true` stays out of `includes/core/api/`; the set of public permission
  callbacks outside that directory is exactly the two required ones, checked in **both**
  directions, so a third file fails *and* deleting either of the two also fails because it breaks
  provisioning; and no *active* `ufw allow 7080` exists outside the frozen `scripts/v1/`. All four
  failure modes were exercised deliberately before the guard was accepted.
- 2026-11-20 — The two required-public endpoints stay `__return_true`, documented at their
  registration sites. Neither can be gated on `manage_options`: one is called by a bare `wget`
  from a provisioning script, the other by GitHub. Each authenticates by other means — a
  pending-command transient and an HMAC-SHA1 signature — so the honest fix was to make both
  verifications run *before* their side effects, not to add a permission check no caller can
  satisfy.
- 2026-11-20 — `uninstall.php` deletes `sstk_options` and `sstk_settings` whole rather than
  filtering them key by key. The filtered version had been silently deleting nothing: it iterated
  `SSTK_Setup::$settings_defaults`, which is declared and never assigned anywhere in the tree, so
  the provider API keys and the encrypted SSH keys survived plugin deletion.
- 2026-11-20 — `phpseclib` moved from 3.0.19 to 3.0.59 and `wpcs` from 2.3 to 3.x during the
  toolchain repair. Neither is scope creep: 3.0.19 carries twelve published advisories and the
  2.3 line is blocked outright by advisory `PKSA-mh9b-91zm-m1gy`. The tracked `vendor/` was
  re-resolved with `composer install --no-dev` rather than hand-edited.
- 2026-11-20 — The PHPUnit scaffold is rebuilt on wp-phpunit 6.9 + PHPUnit 9.6 + WP core 6.9. It
  had been dead since Phase 0: the lock pinned `phpunit 7.5.20` (`php: ^7.1`, uninstallable on
  8.5), targeted WP 5.9.5 while the plugin requires 6.5, and every test class extended
  `WPTest\Test\TestCase` from a package itself pinned to phpunit 7.
- 2026-11-20 — `WP_TESTS_CONFIG_FILE_PATH` is defined in `tests/phpunit.php` with an absolute
  path instead of as a `<const>` in `phpunit.xml`. A relative value in the XML resolves against
  the directory PHPUnit was started from, so the suite would have behaved differently depending
  on where it was invoked.
- 2026-11-20 — A ruleset that references a sniff the current WPCS no longer ships is a hard
  failure, not a warning: `WordPress.PHP.DisallowShortTernary` had to be deleted from
  `phpcs.xml` before phpcs would run at all. `Universal.Files.SeparateFunctionsFromOO.Mixed` is
  excluded in the ruleset rather than suppressed inline, because an inline `phpcs:ignore` above a
  function detaches its docblock and trades one error for another.
- 2026-11-20 — `scripts/v1/` is left untouched even though it still opens port 7080
  (`v1/raw/56-ols_manage_admin_console.txt:45`). Phase 5 froze v1 so servers provisioned before
  the modernization keep the scripts they were built with, and patching a frozen script changes
  behaviour on machines that cannot be tested from here. The guard now fails on *new* occurrences
  outside v1, so the regression cannot come back through v2.
- 2026-11-20 — The remaining `woocommerce` references are kept. They are prose and help strings
  plus two probes: `sstk_is_woocommerce_activated()` asks about the third-party store plugin, and
  `sstk_is_wc_module_enabled()` returns false because `SSTK_WooCommerce_Init` no longer exists —
  that false is what keeps 13 `class_exists` door checks closed, including for the WaaS post types
  Phase 1 chose to keep dormant.
- 2026-11-20 — Logtivity stays as a settings/script feature. Recorded as a decision rather than
  carried as an open question: it is inert unless an operator enables it, `52-logtivity.txt` is a
  normal v2 script, and removing the surface would change a shipped script for no security gain.
- 2026-11-20 — `bin/lint-sh.sh` now also syntax-checks the `bin/*.sh` gate tools themselves.
  Until Phase 8 they were the only shell in the tree that nothing verified, which is backwards: a
  broken guard is discovered at the moment it is supposed to be protecting something. A deliberate
  syntax error and a deliberate guard violation were both run through the gate to confirm they
  fail (exit 1) before being reverted.
- 2026-11-20 — `docs/SSTK.md` and `README.md` are deliberately different documents. README
  is for an operator installing the plugin, the doc is for someone changing it, and neither
  restates `AGENTS.md` — both point at it.

- 2026-11-20 — OLS hardening is applied by `gf_harden_ols_conf()`, not by writing rules into
  `gf_add_domain_ols_httpd_conf()`. That function emits a `virtualhost` stanza, which has no
  `rewrite` or `context` scope; the requirement "a registered site is never unprotected" is met
  by calling the applier from `gf_add_domain_ols_httpd_conf()` and `gf_restore_ols_site()`.
- 2026-11-20 — The applier passes replacement text through awk `ENVIRON[]`, not `awk -v`. `-v`
  processes escape sequences, so `\.env` would arrive as `.env` and silently stop matching.
- 2026-11-20 — The post-install hook is downloaded outside the `&&` chain and has a no-op
  fallback in `9999-common-functions.txt`. A missing hook file must degrade to doing nothing, not
  abort a site install that is otherwise complete.
- 2026-11-20 — Plugin slugs are validated on both sides (PHP filter reduces to the slug character
  set; Bash re-checks it plus `..`) rather than escaped. The list is empty by default and is
  supplied later by One Dog, so the default path must be a provable no-op.
- 2026-11-20 — `POST /sites/{id}/security-status` starts wp-cli in `/var/www/<domain>/html`, not
  `/var/www/<domain>`. The Phase 2 version pointed one directory too high and would have reported
  every hardened site as unhardened.
- 2026-11-20 — The security probe prefers the vhost's `lsapi:lsphpXX` handler for `php_version`
  and treats `php -v` as a fallback, because the CLI PHP is not necessarily the one serving the
  site. The packet layout stays backward compatible: a three-packet reply still parses.
- 2026-11-20 — `bin/lint-sh.sh` now also checks the command wrappers that sit next to `raw/`.
  They are PHP templates whose `##PLACEHOLDER##` tokens are not shell, so each wrapper is
  substituted to a scratch file before `bash -n`. Without this pass a broken `&&` chain in a
  wrapper — which silently skips the rest of a provisioning command — is invisible to every gate;
  the pass was added here after a wrapper edit had to be verified by hand. A deliberate break was
  run through the gate to confirm it fails (exit 1, one `FAIL` line) before being reverted.
- 2026-11-20 — `get_secret_key()` is the credential fingerprint used for cache keys and for the
  "connection succeeded" transient. The base template returns `''`, so `get_api_key()` is
  consulted as a fallback; UpCloud overrides it with the account password. Two accounts on one
  install must never share a cache entry or a connection-test result.
- 2026-11-20 — UpCloud authenticates with a user name and password (`sstk_upcloud_user_name` /
  `sstk_upcloud_user_password`, both encrypted) rather than an api key. `uses_api_key()` and
  `has_credentials()` exist on the base class so the settings screen can ask "are credentials
  configured?" without knowing the credential shape; `is_api_key_empty()` delegates to them.
- 2026-11-20 — UpCloud plans are keyed by `name`, not id, and creating a server requires cloning
  a storage template. Both are UpCloud API constraints, not choices.
- 2026-11-20 — UpCloud snapshot/backup support is deliberately absent: a backup is identified by
  (storage uuid, date) and cannot be expressed as a snapshot id. The feature flags are false so
  the UI hides the buttons instead of offering something that cannot work.
- 2026-11-20 — Both new providers synthesise `status` from `power_status` / `state` plus the
  pending-action post meta, with a 1800 s backstop, because neither API returns a pollable
  operation id for a power change.
- 2026-11-20 — No DigitalOcean-style resize auto-start cron for Vultr or UpCloud: the resize tab
  ignores the provider return value and instructs the operator to restart the server manually.
- 2026-11-20 — `get_generic_size_fields()` no longer reads WooCommerce product size options. The
  four slots live in `get_size_options()` behind the `sstk_provider_size_options` filter; the
  React provision form bypasses slots entirely by sending a raw plan id as `size_raw`.
- 2026-11-20 — `bin/guard-sstk-prefix.sh` forbids `SSTK_WOOCOMMERCE` and `SSTK_DATA_SYNC_REST`
  in addition to `SSTK_License` and `wisdom_`, because a stray `init` hook that instantiates one
  of them is a fatal error on every request.
- 2026-11-20 — Providers implemented in-repo rather than as separate add-ons (Vultr, UpCloud),
  because the upstream add-ons are not present in this tree.
- 2026-11-20 — UI cutover is a single hard phase rather than staged; the action bridge makes
  this feasible without rewriting the 51 tab behaviours.
- 2026-11-20 — The `AGENTS.md` line describing an "external Astro control panel" is removed;
  the native-`wp-admin` requirement supersedes it.
- 2026-11-20 — `assets/dist/` is committed, and therefore `assets/dist/.vite/` is **not**
  git-ignored: the PHP enqueue layer reads `manifest.json` at runtime.
- 2026-11-20 — `bin/lint-sh.sh` excludes three `.txt` files that are not shell
  (`6G-Firewall-OLS.txt`, `7G-Firewall-OLS.txt`, `environment variables.txt`).
- 2026-11-20 — Script version defaults to `v2`; `v1` is retained on disk and still selectable,
  so a server provisioned before this phase keeps the scripts it was built with.
- 2026-11-20 — `wordpress_app_script_version` (underscores) is the canonical option key.
  Upstream read `{app_name}_script_version` — `wordpress-app_script_version` (hyphens) — while
  the settings screen wrote the underscored name, so nothing ever populated the key the readers
  asked for and **every server silently fell back to `v1`**. `get_configured_scripts_version()`
  now reads the underscored key first, keeps the hyphenated one as a legacy fallback, and
  defaults to `v2`. Both readers plus the readability probe go through it.
- 2026-11-20 — `ols1clk.sh` is pinned to tag `v3.1`. Its `--lsphp` validator only knows
  71–81, so `lsphp84` cannot be requested through it; the LiteSpeed repo is added first and
  83/84/85 are installed as explicit packages. `$SSTK_OLS1CLK_SHA256` gates the
  checksum when supplied, and `curl -k` is gone.
- 2026-11-20 — `https://repo.litespeed.sh` advertises Ubuntu 20.04, 22.04, 24.04 **and 26.04**
  in its header, which materially de-risks `resolute`. This is vendor documentation, not a
  tested image — the 26.04 dry run is still the open item below.
- 2026-11-20 — `grep -qP` was replaced with `grep -qE` rather than `grep -q`, because the
  inherited patterns depend on ERE `(...)` and `|` and would have changed meaning under BRE.
- 2026-11-20 — The admin IP is validated in PHP *and* in Bash. The PHP check is the security
  boundary (the value is string-interpolated into a shell command line); the Bash check is
  defence in depth for unreplaced placeholders. Both treat "cannot validate" as "not
  configured", which only ever narrows access.
- 2026-11-20 — shellcheck is advisory, not gating: the inherited v1 script backlog would
  otherwise block every phase.
- 2026-11-20 — **Deviation from the plan text:** the WaaS post types
  (`class-wordpress-posts-site-package.php`, `-update-plan.php`, `-update-plan-log.php`,
  `-quota-profile.php`, `-quota-limits.php`) are **kept on disk and loaded unconditionally**.
  The plan listed them for deletion, but `SSTK_SITE_PACKAGE()` is called unguarded in
  `class-wordpress-app.php` (`can_user_execute_bash_scripts()`) and `SSTK_APP_UPDATE_PLAN()`
  unguarded in `tabs/copy-to-existing-site.php`; deleting the classes would fatal. They stay
  dormant because their `init` registrations remain gated on
  `class_exists( 'SSTK_WooCommerce_Init' )`, which is now never true.
- 2026-11-20 — Logtivity integration files are deleted, but
  `SSTK_WORDPRESS_APP_LOGTIVITY()` keeps working: `functions-classes-wordpress-app.php` now
  returns an inert `SSTK_WORDPRESS_APP_LOGTIVITY_STUB` (a `__call()` sink) because
  `push-commands.php` and `tabs/logs.php` call it unguarded. The stub and those call sites are
  revisited in Phase 4 when the tab layer is retired.
- 2026-11-20 — Digital Ocean is present but **disabled by default**: the registry still lists
  it so legacy servers resolve their provider class, and `enable-do-provider` re-enables it.
- 2026-11-20 — `SSTK_ITEM_ID` deleted outright; nothing in the tree referenced it after the
  license layer was removed.
- 2026-11-20 — **Deviation from the plan text:** the REST registrar is instantiated at the end
  of `required_files()` rather than on `plugins_loaded`. `required_files()` itself runs on
  `init` at priority −20, so a `plugins_loaded` callback registered from inside it would never
  fire. The registrar's constructor only adds the `rest_api_init` hook, which still runs after
  every controller class is loaded.
- 2026-11-20 — The server action route calls `do_instance_action()` directly and passes the
  request object through untouched, instead of reproducing the legacy
  `array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['additional'] ) ) )`
  adapter. That adapter is risk register #4 (nested arrays become "Array to string" warnings
  and lose data); bypassing it is the mitigation the plan asked for.
  **Superseded in Phase 4:** `do_instance_action()` is now only the *fallback*. The route
  dispatches through `sstk_app_{app}_tab_action` first (see Step 4), because that — not
  `do_instance_action()` — is what the server tabs bind to.
- 2026-11-20 — The tabs routes expose only what the `get_tabnames` / `get_tabs` filters return.
  The "top of page" summary fields come from `get_general_fields_server()` (private, in
  `class-wordpress-app.php`) and `get_general_fields()` (in `metaboxes-app.php`, deleted in
  Phase 4), so the React detail screens render their overview panel from `GET /servers/{id}`
  and `GET /sites/{id}` instead of resurrecting those private methods.
- 2026-11-20 — The legacy `show-log-console` and `fetch-logs-from-db` ajax actions were handled
  by explicit `switch` cases in `ajax_app()` *before* the `tab_action` filter, so they were never
  reachable through the site action bridge. `show-log-console` was deleted in Phase 4 as dead
  code; the React `LogDrawer` consumes `GET /servers/{id}/logs` instead. `fetch-logs-from-db`
  keeps its `switch` case because the surviving `log-console` templates still route through it.
- 2026-11-20 — The redactor key pattern is the plan's `/pass|secret|token|private|_key$|api_?key/i`
  plus `authorization`, because a bare `Authorization: Bearer …` header value would otherwise
  survive redaction.
- 2026-11-20 — `GET /settings/providers` reports region and size **counts** only, cached in a
  5 minute transient (`sstk_provider_counts_{slug}`) with `?refresh=1` to bypass. Calling
  the provider APIs on every settings page load would be too slow, and a provider outage must
  never break the screen.
- 2026-11-20 — React is **bundled** (`react@19` from npm) rather than borrowed from
  `@wordpress/element`. This diverges from the sister `google-security-for-wordpress` plugin,
  which uses `@wordpress/scripts` + `@tailwindcss/postcss`. `@tailwindcss/vite` is required for
  the Tailwind v4 pipeline, and a self-contained bundle keeps that build reproducible.
- 2026-11-20 — `package.json` has no `"type": "module"` on purpose: `tailwind.config.js` stays
  CommonJS so Tailwind v4's `@config` directive can load it.
- 2026-11-20 — `cssCodeSplit: false` is kept as the plan specifies (one stylesheet). Under that
  flag Vite publishes the CSS as a **standalone manifest chunk** rather than the entry's `css`
  array, so both the verifier and `get_sstk_entry_assets()` fall back to scanning every
  chunk for a `.css` file. Handling both shapes keeps the build free to flip the flag later.
- 2026-11-20 — **Deviation from the plan's phase split:** the `simplestack` menu page is
  registered in Phase 3, not Phase 4. Without a screen there is no way to satisfy Phase 3's own
  acceptance criterion ("visual check that the wp-admin sidebar/toolbar are unchanged on a
  Simple Stack screen"). Only the page + container land here; the submenu replacement,
  `show_in_menu => false` on both CPTs, and removal of the CPT menus stay in Phase 4.
- 2026-11-20 — `render_sstk_app()` prints an admin notice when the manifest is missing
  instead of leaving a blank page. The plan only asked for a logged early return; a silently
  empty screen in production is indistinguishable from a broken plugin.
- 2026-11-20 — The CSS isolation gate has **five** assertions, not the plan's three. Two were
  added after the first build: (a) rules that only declare custom properties are exempt from the
  document-selector ban, because Tailwind's `@layer theme` / `@layer properties` token layers
  legitimately touch `:root` and `*`; (b) at least one scoped utility must exist, so a Tailwind
  run that emits nothing cannot pass by emitting no violations.
- 2026-11-20 — **Correction to Phase 0's `.gitignore`:** the plan's literal `.vite/` entry also
  matched `assets/dist/.vite/manifest.json`, which the PHP enqueue layer reads at runtime. A bare
  `.vite/` therefore silently excluded the one build artefact that has to ship. Narrowed to
  `/.vite/` (root-level dev cache only); the manifest now commits with the rest of `assets/dist/`.
- 2026-11-20 — The bundle is 223.94 kB (69.61 kB gzip) for a placeholder shell. React 19 +
  `react-dom` dominate it and do not shrink with app code; Phase 4 adds routes on top. Accepted:
  the bundle is only loaded on Simple Stack screens.

- 2026-11-23 — **The hard cutover supersedes every historical preservation note below.**
  There is no `scripts/v1`, script-version selector, per-server override, DigitalOcean provider,
  custom-server provider or upstream-identifier compatibility layer. `sstk_server_scripts_version`
  remains only as write-only audit metadata and is always populated with `v2`; no code reads it to
  choose a provisioning tree.
- 2026-11-23 — **The two extensionless provisioning helpers are now first-class gate inputs.**
  `wp-sync` and `wp-backup2-helper` reject non-OpenLiteSpeed local hosts, and `wp-sync` also rejects
  a non-OpenLiteSpeed destination before transferring data. The backup helper no longer contains
  Nginx/Enterprise branches and now writes its OLS archive under `.wp-backup2`, matching the upload
  and pruning paths. `bin/lint-sh.sh` checks every file under `raw/`, not only `*.txt`.
- 2026-11-23 — **The translation catalog is source-generated and reference-validated.** WP-CLI is
  unavailable locally, so GNU `xgettext` was used with the WordPress translation keywords. The
  placeholder header and empty-msgid references were normalized, `msgfmt` passes, and all 5,363
  references resolve. Only intentional `SSTK_ENCRYPTION_KEY` and `DISABLE_SSTK_CRON` constants remain
  in translated copy.
- 2026-11-23 — **PHPCS is clean after substantive fixes, not suppressions.** REST filenames now
  match their `SSTK_*` classes, modification notices live in file docblocks, runtime dates use
  `gmdate()`, and the site-user creation path now reads the `WP_Error` from `$user` rather than the
  unrelated `$user_data` array. JSON exception messages carry narrow, documented escaping ignores.
- 2026-11-23 — **Static release gates are green; runtime gates remain open.** PHPUnit cannot start
  without MySQL, ShellCheck is unavailable, and no current OpenLiteSpeed/Monit/cron runtime exists
  locally. The 25 TLS-validation bypasses are recorded as a release blocker because some downloads
  are operator-supplied and cannot be changed safely without an explicit trust policy.

## Open questions / blockers

- **PHPUnit has not executed a test.** The toolchain boots correctly, but
  `mysqli_real_connect(): (HY000/2002)` stops the suite before assertions because no MySQL/MariaDB
  server is available. Provide a test database and rerun the four REST test classes.
- **ShellCheck is unavailable.** `bash -n` covers all 139 shell files, including wrappers and the
  extensionless helpers, but the advisory ShellCheck pass cannot run on this workstation.
- **Destructive live runtime certification remains open.** Core non-destructive activation, REST,
  admin UI, cron, public-page, provisioning-URL and redaction checks pass on the current v5 candidate.
  Still verify Vultr and UpCloud create/details/power/delete, Ubuntu 24.04 and 26.04 provisioning, OLS
  vhost hardening, multisite/HTTPS/phpMyAdmin, backup and sync helpers, Monit/Netdata and a real
  database uninstall. These require explicit approval and provider credentials.
- **Host firewall inspection was limited.** Passwordless sudo was unavailable on One Dog Test. The
  external port-7080 probe timed out and the shipped scripts deny public access by default, but the
  live UFW ruleset itself was not captured.
- **TLS validation is disabled in 25 `scripts/v2` commands.** Separate first-party provisioning
  downloads from operator-supplied post-processing and Git-hook URLs, define the trust policy, and
  remove `--no-check-certificate` wherever certificate validation can be enforced safely.
- **`SSTK_OLS1CLK_SHA256` is unset.** Supply the checksum once the shipping `ols1clk.sh` tag is
  frozen; until then the download is protected by the pinned tag, `curl --fail` and `bash -n` only.
- **The post-install security-plugin list is empty.** One Dog Solutions slugs must be supplied for
  `sstk_post_site_install_plugins`; the default path intentionally installs no plugins.
- **The OLS WebAdmin allow-list has no UI field.** The documented programmatic `sstk_admin_ip`
  setting is honoured, and blank keeps port 7080 on loopback. Add a Simple Stack settings field
  before release if operator self-service is required.
- **Plugin replacement requires an OPcache flush on hosts with delayed revalidation.** Call
  `opcache_invalidate()` or reload the active PHP handler after replacing plugin files; otherwise
  stale bytecode can mask deployment results.
- **`v1.0.0` is not tagged and the release gate is not green.** Live deployment to One Dog Test was
  performed under explicit approval; no commit, tag, push or public release has been performed.

## Update protocol (every build session, read this)

1. Flip that phase's **Status** in the Progress table (🟡 while working, ✅ when its
   acceptance criteria pass) and fill in the short commit hash.
2. Update **Overall status**, **Last updated** (today's date), and **Next action**.
3. Append surprises to **Decisions & deviations log**; add unresolved items to
   **Open questions / blockers**.
4. Commit `state.md` **in the same commit** as the phase's code so state never drifts
   from the tree.
