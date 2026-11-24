# Simple Stack

Internal hosting control plane for One Dog Solutions. Simple Stack provisions servers at
[Vultr](https://www.vultr.com) and [UpCloud](https://upcloud.com), installs WordPress on them
over SSH, and manages every server and site from inside the WordPress admin dashboard.

Simple Stack is a fork of **WPCloudDeploy 5.8.0**. The upstream product was never used in
production, so this fork deliberately replaces its identifiers and data model without a
migration or compatibility layer. It has no license server, telemetry or commercial add-on
layer. See [`NOTICE.md`](NOTICE.md) for attribution and licensing.

## What it does

- **Provision servers** at Vultr (API v2) or UpCloud (API 1.3) from a form in `wp-admin`.
- **Install WordPress** on them: OpenLiteSpeed, PHP, MariaDB, Redis, certbot, firewall and an
  OLS vhost, driven by Bash scripts executed over SSH.
- **Manage servers and sites** through dedicated tabs — services, firewall, backups, Monit,
  logs, Fail2ban, SSH keys, resize, power, and on the site side SSL, cache, PHP options,
  `wp-config`, staging, clone, domain change, redirects, cron, SFTP, phpMyAdmin, Git and
  per-site users.
- **Probe hardening** on demand: installed plugins, `DISALLOW_FILE_EDIT`, and the PHP version
  the vhost actually serves.

Everything happens inside `wp-admin`. There is no external or headless frontend, and no
third-party SaaS in the control path — only the cloud provider's own API.

## Stack

| Layer | Choice |
|---|---|
| Cloud providers | Vultr API v2, UpCloud API 1.3 |
| Operating system | Ubuntu 24.04 LTS (`noble`) and 26.04 LTS (`resolute`) |
| Web server | OpenLiteSpeed only |
| PHP | `lsphp83`, `lsphp84` (default), `lsphp85` |
| Admin client | React 19 + Tailwind CSS v4, built with Vite, mounted at `#simplestack-admin` |
| REST API | `simple-stack/v1`; admin SPA routes require `manage_options` |
| Provisioning | Bash scripts under `includes/core/apps/wordpress-app/scripts/v2/` |

## Requirements

- WordPress 6.5 or later
- PHP 8.1 or later
- Meta Box, installed and activated through the bundled required-plugin flow; Simple Stack
  cannot read its settings without it
- A Vultr API key and/or UpCloud API credentials, plus an SSH key pair for the provider
- Node 20 or later for development builds only; `assets/dist/` is committed, so a production
  install needs no Node toolchain

## Installation

1. Copy this directory to `wp-content/plugins/simple-stack/`.
2. Add a strong encryption key to `wp-config.php` before activation (recommended):

   ```php
   define( 'SSTK_ENCRYPTION_KEY', 'replace-with-a-long-random-value' );
   ```

3. Activate **Simple Stack** in *Plugins*.
4. Open **Simple Stack → Providers** and save your Vultr and/or UpCloud credentials, then use
   *Test connection* to confirm they work.
5. Open **Simple Stack → Servers → Add Server** to provision.

Port `7080` is denied by default. The trusted admin IP is read from `sstk_admin_ip` in the
`sstk_settings` option. Until an admin UI field is available, set it programmatically; without
it, the console remains on loopback and the provisioning script prints the SSH tunnel command.

## Secret storage and uninstall

`SSTK_ENCRYPTION_KEY` is the preferred encryption key. If it is absent, Simple Stack generates
and persistently stores a fallback key in the `sstk_encryption_key_v2` option. Stored secrets use
an authenticated `sstk-encrypt-v2:` envelope with a random IV and HMAC-SHA256; legacy envelopes are
intentionally unsupported. Back up the configured key because changing or removing it makes the
stored credentials unreadable.

Cross-site data sync is not included. Each control-plane site is configured explicitly; there is
no remote import endpoint or compatibility path.

Uninstalling Simple Stack removes its `sstk_` options, metadata, post types, taxonomies, every
scheduled `sstk_` cron hook, custom role definitions, user role assignments, direct `sstk_`
capabilities and custom tables from every site in a multisite network. It does not destroy cloud
servers or remote WordPress sites; those continue running and must be removed separately at the
provider.

## Development

```bash
composer install
npm ci
```

### Front-end build

```bash
npm run build                    # → assets/dist/
npm run verify:css               # fails if Tailwind escapes #simplestack-admin
npm run dev                      # Vite dev server, for work on src/admin/ only
```

The build output is committed on purpose. `assets/dist/.vite/manifest.json` is read at runtime
by `enqueue_sstk_app()`; if the manifest is missing the admin page renders an
operator-facing notice rather than a blank screen.

### Verification

Run these before considering any change done:

```bash
bash bin/lint-php.sh             # php -l over the first-party PHP tree
bash bin/lint-sh.sh              # bash -n over raw scripts, wrappers and gate tools
bash bin/guard-sstk-prefix.sh    # naming contract, retired services, REST and port 7080
composer test:uninstall          # standalone multisite cleanup lifecycle harness
composer validate --no-check-publish
php bin/rebrand.php --dry-run    # must report zero changes, replacements and moves
npm run build && npm run verify:css
```

`composer lint`, `composer lint:sh` and `composer guard` wrap the first three. The uninstall
harness stubs WordPress and the database, so it runs without MySQL; it does not replace a real
WordPress activation/uninstall test.

### PHPUnit

The suite needs a local MySQL database matching `wp-tests-config.php`:

```bash
composer install
./vendor/bin/phpunit
```

The current local environment has no MySQL socket, so PHPUnit cannot complete until that
service is available. PHPCS and PHP syntax results are recorded in [`state.md`](state.md).

### Translation template

Regenerate the catalog after source-string changes with WP-CLI:

```bash
wp i18n make-pot . languages/simple-stack.pot --domain=simple-stack \
  --exclude=vendor,required_plugins,node_modules,assets/dist
```

WP-CLI is required for a canonical regeneration, including current source references. The local
environment does not provide it, so the release gate remains open until that command succeeds.

## Documentation

- [`docs/SIMPLESTACK.md`](docs/SIMPLESTACK.md) — architecture, REST route reference,
  fixed `v2` provisioning scripts and OLS hardening rules.
- [`docs/rename-inventory.md`](docs/rename-inventory.md) — pre-rename inventory retained as
  an audit artifact; its legacy tokens are intentional.
- [`state.md`](state.md) — build state, decisions, deviations and open blockers.
- [`AGENTS.md`](AGENTS.md) — project constraints that must not be broken.

## Naming contract

- `SSTK_` — PHP classes and constants.
- `sstk_` — functions, hooks, options, meta, post types, taxonomies, capabilities and cron
  identifiers. This prefix is exactly four characters so post-type keys stay within the
  WordPress 20-character limit.
- `sstk-` — CSS/JS handles, classes and IDs.
- `simple-stack` — text domain, plugin folder, main file and REST namespace.
- `Simple Stack` — user-facing product name.

`bin/rebrand-map.json` is the single source for the historical rename rules.
`bin/rebrand.php` is idempotent and uses `git mv` for tracked path changes. The optional
`wpapp`/`WPAPP` sweep and PHP namespaces/PSR-4 migration are deferred to separate passes.

## License and provenance

Simple Stack is a fork of WPCloudDeploy 5.8.0 by WPCloudDeploy and other contributors,
distributed under the GNU General Public License v2 or later — see [`license.txt`](license.txt)
and [`NOTICE.md`](NOTICE.md). Modifications are copyright One Dog Solutions.

Bundled third-party code retains its original names and licenses, including Meta Box,
phpseclib, ParagonIE and Chart.js.
