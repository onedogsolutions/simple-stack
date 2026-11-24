# Simple Stack

Internal hosting control plane for One Dog Solutions. A WordPress plugin that provisions
servers at Vultr and UpCloud and installs WordPress on them, entirely from inside `wp-admin`.

- Forked from the upstream 5.8.0 release. Not distributed; no license server, no telemetry,
  no commercial add-on layer. Attribution remains in `NOTICE.md` and `license.txt`.
- Web stack is **OpenLiteSpeed only** on Ubuntu 24.04 LTS (`noble`) / 26.04 LTS (`resolute`),
  with `lsphp83`, `lsphp84` (default) and `lsphp85`.
- Build state lives in [`state.md`](../state.md) at the repository root. Read it before
  changing anything; it is the single source of truth for what is done and what is next.

---

## 1. Architecture

```
simple-stack.php                       plugin header, constants, activation, required_files()
  └─ SSTK()                    includes/core/simple-stack.php
       ├─ add_sstk_menu_page()      one top-level menu, React container markup
       ├─ enqueue_sstk_app()        reads assets/dist/.vite/manifest.json
       ├─ get_provider_api()               class-{slug}.php → CLOUD_PROVIDER_API_{CamelCase}
       └─ apps[]
            └─ wordpress-app              the only app
                 ├─ include_tabs_server() loads tabs-server/*.php
                 ├─ include_tabs()        loads tabs/*.php
                 └─ do_instance_action()  the action engine

includes/core/api/             simple-stack/v1 REST — a thin adapter over the above
src/admin/                     React + Tailwind v4 client, built into assets/dist/
includes/core/apps/wordpress-app/scripts/v2/       active provisioning scripts
```

### Naming contract

The upstream naming surface has been replaced without a compatibility layer. Classes and
constants use `SSTK_`; functions, hooks, options, meta, post types, taxonomies, capabilities
and cron identifiers use the four-character `sstk_` prefix; asset handles and CSS/JS names
use `sstk-`; the text domain, plugin folder, main file and REST namespace use `simple-stack`.
User-facing copy says **Simple Stack**. `bin/guard-sstk-prefix.sh` rejects legacy identifiers
outside the attribution and rename-audit allowlist.

### The action bridge

Tab controllers contribute their names and field arrays through filters:

```php
apply_filters( "sstk_server_{$app}_get_tabnames", array(), $id );
apply_filters( "sstk_server_{$app}_get_tabs", array(), $id );
apply_filters( "sstk_app_{$app}_get_tabnames", array(), $id );
apply_filters( "sstk_app_{$app}_get_tabs", array(), $id );
```

The server and site screens no longer render those arrays through Meta Box or dispatch through
`admin-ajax.php`. Instead the REST layer:

1. reads the same arrays and serialises them as a control schema
   (`GET /servers/{id}/tabs`, `GET /sites/{id}/tabs`);
2. maps a submitted form onto the shared `sstk_app_wordpress-app_tab_action` dispatch used by
   server and site tab handlers, with `do_instance_action()` as the server lifecycle fallback.

Some legacy server tab constructors also register `sstk_server_{$app}_tab_action`, but the active
REST bridge does not dispatch that filter. `TabRenderer` renders the returned schema generically;
only the log console, security probe and activity feed have dedicated components. A new PHP tab
appears without a client change when its schema uses supported field types.

---

## 2. REST reference

Namespace `simple-stack/v1`. `SSTK_REST_API` registers the six admin SPA controllers on
`rest_api_init`. The WordPress App controller family and the command/webhook callbacks register
their routes separately under the same namespace.

| Method | Route | Purpose |
|---|---|---|
| GET | `/servers` | List servers; filters `provider`, `region`, `status`, `search` |
| POST | `/servers` | Create a server and queue the provisioning task |
| GET | `/servers/{id}` | Server summary plus redacted `sstk_server_*` meta |
| DELETE | `/servers/{id}` | Destroy the server |
| POST | `/servers/{id}/action` | Tab-action filter first; `do_instance_action()` fallback |
| GET | `/servers/{id}/logs` | Command / SSH / error log, `type` + `since` cursor + `limit` |
| GET | `/servers/{id}/tabs` | Tab schema from `sstk_server_wordpress-app_get_tabs` |
| GET | `/sites` | List sites where `app_type = wordpress-app` |
| POST | `/sites` | Deploy a site via `install_wp_validate()` |
| GET | `/sites/{id}` | Site summary plus redacted `wpapp_*` meta |
| DELETE | `/sites/{id}` | Trash the site |
| POST | `/sites/{id}/action` | `sstk_app_wordpress-app_tab_action` after mapping the body onto `$_POST` |
| GET | `/sites/{id}/tabs` | Tab schema from `sstk_app_wordpress-app_get_tabs` |
| POST | `/sites/{id}/security-status` | Read-only hardening probe (plugins, `DISALLOW_FILE_EDIT`, PHP version) |
| GET | `/settings/providers` | Provider state as booleans, feature flags and cached counts |
| POST | `/settings/providers` | Save credentials (encrypted before storage) |
| POST | `/settings/providers/{slug}/test` | `call( 'test_connection' )` against the provider API |
| GET | `/activity` | Merged command / SSH / error feed |

### Guarantees

- **Administrative routes are gated.** The six SPA controllers use
  `SSTK_REST_Controller_Base::permissions_check()`, which requires `current_user_can( 'manage_options' )`.
  WordPress App controllers use `sstk_is_admin()`. Two callbacks in the same namespace are public
  by necessity: the provisioning command callback
  (`includes/core/apps/class-sstk-app.php`) validates a pending-command transient, and the GitHub
  webhook (`includes/core/apps/wordpress-app/tabs/git-control-site.php`) requires a non-empty
  per-site secret and verifies HMAC-SHA1 with `hash_equals()`. `bin/guard-sstk-prefix.sh` limits
  `__return_true` to those two callback files.
- **SPA arguments are validated.** Arguments registered through
  `SSTK_REST_Controller_Base` carry `sanitize_callback` and `validate_callback`; the `arg_int` /
  `arg_string` / `arg_text` / `arg_object` / `arg_id` helpers make that structural. The WordPress
  App and public-callback routes use their own validation paths.
- **SPA response payloads are redacted.** Payloads returned through the SPA base controller's
  `respond()` method pass through `SSTK_Secret_Redactor::redact()`. Keys matching
  `/pass|secret|token|private|authorization|_key$|api_?key/i` become `********`, PEM blocks and
  `token=` / `password=` fragments inside free text are scrubbed, and password-typed tab fields
  ship with `std` / `value` / `default` blanked. Provider routes report `has_api_key` /
  `has_ssh_key` booleans instead of values. Other REST response paths do not inherit this automatic
  redaction boundary.

---

## 3. Admin UI

React 19 + Tailwind CSS v4, built with Vite 7 into `assets/dist/`.

```
src/admin/
  index.jsx              mounts into #simplestack-admin
  App.jsx                hash route table
  lib/api.js             fetch wrapper: X-WP-Nonce, envelope unwrap, error toast
  lib/schema.js          tab field array → control descriptor
  lib/router.js          hash router (wp-admin owns the URL bar, pushState would fight it)
  components/TabRenderer.jsx   renders supported tab schemas generically
  routes/                ServersList, ServerDetail, ProvisionServer,
                         SitesList, SiteDetail, DeploySite,
                         ProviderSettings, SecurityStatus, ActivityLog
```

Hash routes: `/servers`, `/servers/new`, `/servers/:id`, `/sites`, `/sites/new`,
`/sites/:id`, `/sites/:id/security`, `/settings/providers`, `/activity`. Deep links look like
`admin.php?page=simplestack#/servers/42`.

**Tailwind is not allowed to leak.** Build settings that enforce it:

- `important: '#simplestack-admin'` in `tailwind.config.js`, so utilities outrank wp-admin's
  unlayered author styles even inside cascade layers;
- Preflight is **not** imported — only `tailwindcss/theme.css` and `tailwindcss/utilities.css`;
- a small hand-written reset is scoped to `#simplestack-admin`;
- `npm run verify:css` asserts the built CSS has no top-level `html` / `body` / `*` / `:root`
  selector, that every utility selector is scoped to `#simplestack-admin`, and that no
  `@import "tailwindcss"` residue survives.

The bundle is committed. `assets/dist/` is part of the repository so the plugin runs on a
server with no Node toolchain; `assets/dist/.vite/manifest.json` is read at runtime and a
missing manifest renders an operator-facing notice instead of a blank screen.

---

## 4. Provisioning scripts

Two directory shapes, and the difference matters:

| Location | Form | How it reaches the server |
|---|---|---|
| `scripts/v2/raw/*.txt` | plain Bash | downloaded verbatim over HTTP from `SCRIPT_URL` |
| `scripts/v2/*.txt` (no `raw/`) | PHP template | `##TOKEN##` placeholders substituted from `script_placeholders()`, then sent as a command |

A raw script therefore cannot carry a placeholder, and **no raw script sources a sibling** —
every file a script needs is fetched explicitly by the wrapper. That is why the post-install
hook needs a `SCRIPT_POST_INSTALL_URL` / `_NAME` pair and its own `wget`, and why the fetch
sits outside the `&&` chain (a failed optional download must not abort a finished install).

Because `##TOKEN##` at the start of a line is a Bash comment when unsubstituted, `bash -n` on a
wrapper template always fails. `bin/lint-sh.sh` therefore checks both populations: raw scripts
as they ship, and wrappers after replacing every `##PLACEHOLDER##` with a plain word.

### Provisioning is fixed to `v2`

`get_configured_scripts_version()` always returns `v2`. Server creation and REST requests use that
value; there is no version dropdown, per-server override or editable script-version path. The
working tree contains no `scripts/v1/` directory and has no v1 fallback.

What defines the `v2` stack, in one line each:

- `01-prepare_server.txt` — Ubuntu 24.04 refusal removed, nginx/LEMP branch removed,
  `24.04 → noble` / `26.04 → resolute` guard that fails fast, explicit `lsphp83/84/85` install
  loop from `repo.litespeed.sh`, `ols1clk.sh` pinned to tag `v3.1` with `curl --fail`.
  The Vultr cloud-init workaround
  `apt-get install -y mariadb-server -o Dpkg::Options::="--force-confnew"` is preserved
  verbatim.
- `9999-common-functions.txt` — `PHPVER=lsphp84`, `g_php_versions=(83 84 85)`,
  `gf_set_default_php()` / `gf_list_installed_php()` / `gf_normalise_admin_ip()`.
- `56-ols_manage_admin_console.txt` — see §5.
- `04-manage_https.txt` — nginx branches deleted; certbot stays `--non-interactive --agree-tos`.
- `66-sstk_post_install.txt` — new post-install hook, see §6.

### Port 7080

Never open to the world. `01-prepare_server.txt` applies an unconditional `ufw deny 7080`.
`56-ols_manage_admin_console.txt` then rewrites only the `:7080` listener in
`admin_config.conf` — `*:7080` when `SSTK_ADMIN_IP` is set, `127.0.0.1:7080` otherwise
— and deletes any inherited blanket rule before adding
`ufw allow from $SSTK_ADMIN_IP to any port 7080 proto tcp`. With no admin IP it adds
nothing and prints the SSH-tunnel command instead.

`SSTK_ADMIN_IP` originates at the `sstk_admin_ip` key in the `sstk_settings` option. The
setting is currently programmatic because its legacy settings tab remains disabled; an admin UI
field is still an open item. The value is validated twice (PHP before it reaches a command line,
Bash after placeholder substitution), rejects `0.0.0.0/0` and `::/0` outright, and requires an SSH
tunnel when empty.

---

## 5. OLS vhost hardening

`gf_add_ols_conf()` emits the vhost from a template, and `gf_harden_ols_conf()` rewrites an
existing vhost in place. Both are needed: the template covers new sites, and the applier covers
sites created before this rule set and sites restored from a pre-rule-set backup.

Deny context (dotfiles and interpreter sources that must never be served):

```
context exp:error_log|wp-config-sample\.php|\.pl|\.cgi|\.lua|\.perl|\.sql|\.sh|wp-config\.php|php\.ini|\.log|\.env|\.git|\.htaccess|\.htpasswd|composer\.(json|lock)|\.user\.ini {
  location                 $DOC_ROOT/$0
  allowBrowse              0
}
```

Rewrite block, replacing the old `^/wp-content/uploads/.*\.php$` single rule:

```
rewrite {
  rules                   <<<END_rules
  RewriteRule ^/wp-content/(uploads|files|updraft|cache)/.*\.(php|phtml|phar|php[0-9])$ - [F,L]
  RewriteRule ^/wp-content/updraft/.*$ - [F,L]
  RewriteRule "(^|/)\.(?!well-known/)" - [F,L]
  END_rules
}
```

The `.well-known` exemption is not cosmetic: the dotfile rule would otherwise block ACME
challenges and every certbot renewal would silently fail. A
`context /.well-known/acme-challenge/ { allowBrowse 1 }` is kept in place, and the applier
asserts it survives.

`gf_harden_ols_conf()` is idempotent — a second run changes nothing — and prints a warning and
skips when the target conf has no `rewrite` block at all, since such a file cannot be hardened
in place. If a site predates this rule set and is never re-provisioned or restored, its vhost
stays on the old rule set.

---

## 6. `sstk_post_site_install`

Called by `02-install_wordpress_site.txt` once WordPress and any post-processing script have
finished:

```bash
sstk_post_site_install "$domain" "$user_name" "$g_webserver_type"
```

Defined in `scripts/v2/raw/66-sstk_post_install.txt`, sourced from
`9999-common-functions.txt`. `9999` defines a no-op before sourcing, so if the optional
download did not land the call still resolves and the install completes.

What it does:

1. `wp config set DISALLOW_FILE_EDIT true --raw` for the site;
2. installs and activates each plugin in `SSTK_SECURITY_PLUGINS`.

The plugin list arrives through `get_post_site_install_plugins()` (in
`includes/core/apps/class-sstk-app.php`), which reads the
`sstk_post_site_install_plugins` filter and reduces every entry to `[A-Za-z0-9._-]`, an
optional `author/plugin` slash, and nothing else. The Bash side re-checks the same set and
rejects `..`. Validation is by omission rather than escaping, because the value is interpolated
into a `su -c` command line. The filter returns an empty list by default until the One Dog
plugin slugs are supplied.

Note that the site user's home directory **is** `/var/www/<domain>/html`
(`useradd -d "$wphome"`), which is why `su - "$user_name" -c "wp ..."` needs no `--path`.

---

## 7. Providers

Registry in `includes/core/simple-stack.php`:

| Slug | Class | Authentication |
|---|---|---|
| `vultr-v2` | `CLOUD_PROVIDER_API_VultrV2` | Bearer token |
| `upcloud` | `CLOUD_PROVIDER_API_Upcloud` | Account user name and password |

Both classes implement the `CLOUD_PROVIDER_API` contract — `regions`, `sizes`, `keys`,
`create`, `details`, `status`, `reboot` / `on` / `off`, `delete`, `resize`, `ssh_create` and
`test_connection` — with feature flags controlling provider-specific operations such as
snapshots. They cache region and size results and never place credentials in exception
messages or log lines. Provider options use the `sstk_` prefix.

---

## 8. Secret storage and uninstall

`SSTK_ENCRYPTION_KEY` in `wp-config.php` is the preferred key for stored provider credentials,
passwords and private SSH keys. When it is absent, `SSTK::encrypt()` generates a key once and
persists it in the `sstk_encryption_key_v2` option. This is a durable database fallback, not a
temporary per-request key. Operators should still configure the constant explicitly and back it
up: changing or deleting the key makes existing ciphertext unreadable.

Secrets are stored as `sstk-encrypt-v2:` envelopes containing a random AES-CBC IV, an HMAC-SHA256
authentication tag and the ciphertext. `decrypt()` strictly decodes the envelope, validates its
lengths and verifies the tag before decrypting. Malformed, truncated, tampered, wrong-key and
legacy-format values return an empty string; there is no legacy decryption path.

Cross-site data sync is retired. The former service class, settings tab, AJAX handlers, cron
wiring and remote receiver are absent; there is no import endpoint or compatibility layer. Each
control-plane site is configured explicitly.

Uninstall is a hard-cutover cleanup. `uninstall.php` removes `sstk_` options and transients,
prefixed post/user/term metadata, posts whose type starts with `sstk_`, prefixed taxonomies and
terms, every scheduled cron hook beginning with `sstk_`, custom role definitions, role assignments
and direct `sstk_` capabilities on users, and tables whose name starts with the site's WordPress
table prefix plus `sstk_`. On multisite it repeats the blog cleanup for every site and removes
network options separately. Cloud servers and remote WordPress sites are not destroyed. The
standalone `composer test:uninstall` harness exercises these cleanup paths without MySQL; a real
WordPress activation/uninstall test is still required before release.

---

## 9. Working on the plugin

```bash
# PHP and shell gates
bash bin/lint-php.sh            # php -l over every PHP file except node_modules/ and vendor/
bash bin/lint-sh.sh             # bash -n over raw scripts, substituted wrappers and bin/*.sh
bash bin/guard-sstk-prefix.sh   # sstk_ names, public REST callbacks, port 7080
composer lint                   # same as lint-php.sh
composer test:uninstall          # standalone multisite cleanup lifecycle harness
composer validate --no-check-publish
php bin/rebrand.php --dry-run    # zero changes, replacements and path moves

# Front end (only needed if you touch src/admin/)
npm ci
npm run build                   # → assets/dist/
npm run verify:css              # fails if Tailwind escapes #simplestack-admin
```

Requirements: WordPress 6.5+, PHP 8.1+, Node 20+ for builds only. `assets/dist/` is committed,
so a production install needs no Node. Regenerate `languages/simple-stack.pot` after source-string
changes with `wp i18n make-pot`; WP-CLI is required for canonical source references.

After any phase of work, update `state.md` — flip the phase status in the Progress table,
refresh Overall status / Last updated / Next action, append surprises to Decisions & deviations,
and record unresolved items under Open questions / blockers. `AGENTS.md` states the same rule
and adds the project constraints that must not be broken.
