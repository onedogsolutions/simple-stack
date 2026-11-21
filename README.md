# SimpleStack

Internal hosting control plane for One Dog Solutions. SimpleStack deploys servers at
[Vultr](https://www.vultr.com) and [UpCloud](https://upcloud.com), installs WordPress on them
over SSH, and then manages every server and site from inside the WordPress admin dashboard.

It is a private fork of [WP Cloud Deploy](https://wpclouddeploy.com) 5.8.0, rebuilt around a
single web stack and a single admin UI. It is not distributed and there is no licence server,
no telemetry and no upsell.

## What it does

- **Provision servers** at Vultr (API v2), UpCloud (API 1.3) or an already-running custom
  server, from a form in `wp-admin`.
- **Install WordPress** on them: OpenLiteSpeed, PHP, MariaDB, Redis, certbot, firewall and an
  OLS vhost, driven by shell scripts executed over SSH.
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
| Cloud providers | Vultr API v2, UpCloud API 1.3, Custom Server. DigitalOcean is present but disabled by default. |
| Operating system | Ubuntu 24.04 LTS (`noble`) and 26.04 LTS (`resolute`) |
| Web server | OpenLiteSpeed only. nginx is not supported. |
| PHP | `lsphp83`, `lsphp84` (default), `lsphp85` |
| Admin client | React 19 + Tailwind CSS v4, built with Vite, mounted at `#simplestack-admin` |
| REST API | `simple-stack/v1`, `manage_options` gated |
| Provisioning | Bash scripts under `includes/core/apps/wordpress-app/scripts/v2/` |

## Requirements

- WordPress 6.5 or later
- PHP 8.1 or later
- Node 20 or later — **builds only**; `assets/dist/` is committed, so a production install
  needs no Node toolchain
- A Vultr API key and/or UpCloud API credentials, plus an SSH key pair for the provider

## Installation

1. Copy this directory to `wp-content/plugins/simple-stack/`.
2. Activate **SimpleStack** in *Plugins*.
3. Open **SimpleStack → Providers** and save your Vultr and/or UpCloud credentials, then use
   *Test connection* to confirm they work.
4. Open **SimpleStack → Servers → Add Server** to provision.

Also set **Settings → SimpleStack → Admin IP** to the address you will reach the
OpenLiteSpeed WebAdmin console from. Port `7080` is denied by default and only opened to that
address; with no admin IP configured the console stays on loopback and the provisioning script
prints the SSH tunnel command instead.

## Development

```bash
composer install                 # PHP dependencies
npm ci                           # front-end dependencies
```

### Front-end build

```bash
npm run build                    # → assets/dist/
npm run verify:css               # fails if Tailwind escapes #simplestack-admin
npm run dev                      # Vite dev server, for work on src/admin/ only
```

The build output is committed on purpose. `assets/dist/.vite/manifest.json` is read at runtime
by `enqueue_simplestack_app()`; if the manifest is missing the admin page renders an
operator-facing notice rather than a blank screen.

### Verification

Run these before considering any change done:

```bash
bash bin/lint-php.sh             # php -l over every PHP file
bash bin/lint-sh.sh              # bash -n over raw scripts, wrappers and gate tools
bash bin/guard-wpcd-prefix.sh    # wpcd_ names, public REST callbacks, port 7080
npm run build && npm run verify:css
```

`composer lint`, `composer lint:sh` and `composer guard` wrap the first three.

### PHPUnit

The suite needs a local MySQL database matching `wp-tests-config.php`:

```bash
composer install
./vendor/bin/phpunit
```

Existing servers keep whatever script version their `wpcd_server_scripts_version` meta records,
so `scripts/v1/` remains in the tree for anything provisioned before the fork.

## Documentation

- [`docs/SIMPLESTACK.md`](docs/SIMPLESTACK.md) — architecture, REST route reference, script
  versioning, OLS hardening rules.
- [`state.md`](state.md) — build state: what is complete, what is next, every deviation and
  open blocker. Read this first; it is the single source of truth.
- [`AGENTS.md`](AGENTS.md) — the project constraints that must not be broken.

## A note on naming

`wpcd_` prefixes are deliberately **not** renamed. PHP class names, the `wpcd_app_server` and
`wpcd_app` custom post types, `wpcd_*` post meta and option keys, and the Bash environment
variables keep their upstream names because the provisioning scripts and any surviving add-on
code address them by name. Only user-facing strings, the plugin header and the new REST
namespace are branded SimpleStack. `bin/guard-wpcd-prefix.sh` enforces this.

## Licence and provenance

SimpleStack is a fork of WP Cloud Deploy 5.8.0 by WPCloudDeploy, distributed under the GNU
General Public License v2 or later — see [`license.txt`](license.txt). Modifications are
copyright One Dog Solutions.

Bundled third-party code: Meta Box, phpseclib, Chart.js.
