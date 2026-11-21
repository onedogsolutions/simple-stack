# SimpleStack

Internal hosting control plane for One Dog Solutions. A WordPress plugin that provisions
servers at Vultr and UpCloud and installs WordPress on them, entirely from inside `wp-admin`.

- Forked from WP Cloud Deploy 5.8.0. Not distributed; no licence server, no telemetry, no
  commercial add-on layer.
- Web stack is **OpenLiteSpeed only** on Ubuntu 24.04 LTS (`noble`) / 26.04 LTS (`resolute`),
  with `lsphp83`, `lsphp84` (default) and `lsphp85`.
- Build state lives in [`state.md`](../state.md) at the repository root. Read it before
  changing anything; it is the single source of truth for what is done and what is next.

---

## 1. Architecture

```
wpcd.php                       plugin header, constants, activation, required_files()
  └─ WPCD()                    includes/core/wp-cloud-deploy.php
       ├─ add_simplestack_menu_page()      one top-level menu, React container markup
       ├─ enqueue_simplestack_app()        reads assets/dist/.vite/manifest.json
       ├─ get_provider_api()               class-{slug}.php → CLOUD_PROVIDER_API_{CamelCase}
       └─ apps[]
            └─ wordpress-app              the only app
                 ├─ include_tabs_server() 22 requires in tabs-server/*.php
                 ├─ include_tabs()        29 requires in tabs/*.php
                 └─ do_instance_action()  the action engine

includes/core/api/             simple-stack/v1 REST — a thin adapter over the above
src/admin/                     React + Tailwind v4 client, built into assets/dist/
includes/core/apps/wordpress-app/scripts/v{1,2}/   provisioning scripts
```

### Two names, one plugin

`wpcd_` is deliberately **not** renamed. PHP class names, the `wpcd_app_server` / `wpcd_app`
custom post types, `wpcd_*` post meta keys, `wpcd_*` option keys and the Bash environment
variables all keep their upstream names, because add-on code and the provisioning scripts
address them by name. Only user-facing strings, the plugin header, and the new REST namespace
say "SimpleStack". `bin/guard-wpcd-prefix.sh` fails the build if any of them disappear.

### The action bridge

Every one of the 51 server and site tabs already registers three filters:

```php
add_filter( "wpcd_server_{$app}_get_tabnames", [ $this, 'get_tab' ], 10, 2 );
add_filter( "wpcd_server_{$app}_get_tabs",     [ $this, 'get_tab_fields' ], 10, 2 );
add_filter( "wpcd_server_{$app}_tab_action",   [ $this, 'tab_action' ], 10, 3 );
```

The plugin used to render those arrays through Meta Box and dispatch through `admin-ajax.php`.
Both are gone. Instead the REST layer:

1. reads the same `get_tabs` arrays and serialises them as a control schema
   (`GET /servers/{id}/tabs`, `GET /sites/{id}/tabs`);
2. maps a submitted form back onto the same dispatch points —
   `do_instance_action()` for servers, the `wpcd_app_wordpress-app_tab_action` filter for sites.

So the React rewrite did not reimplement 51 tabs. `TabRenderer` renders the schema generically
for every tab; only a handful of panels (log console, security probe, activity feed) have
dedicated components. Adding a tab on the PHP side makes it appear in the React UI with no
client change.

---

## 2. REST reference

Namespace `simple-stack/v1`. Every route is registered by
`includes/core/api/class-simplestack-rest-api.php` on `rest_api_init`.

| Method | Route | Purpose |
|---|---|---|
| GET | `/servers` | List servers; filters `provider`, `region`, `status`, `search` |
| POST | `/servers` | Create a server and queue the provisioning task |
| GET | `/servers/{id}` | Server summary plus redacted `wpcd_server_*` meta |
| DELETE | `/servers/{id}` | Destroy the server |
| POST | `/servers/{id}/action` | `do_instance_action( $id, $action, $additional )` |
| GET | `/servers/{id}/logs` | Command / SSH / error log, `type` + `since` cursor + `limit` |
| GET | `/servers/{id}/tabs` | Tab schema from `wpcd_server_wordpress-app_get_tabs` |
| GET | `/sites` | List sites where `app_type = wordpress-app` |
| POST | `/sites` | Deploy a site via `install_wp_validate()` |
| GET | `/sites/{id}` | Site summary plus redacted `wpapp_*` meta |
| DELETE | `/sites/{id}` | Trash the site |
| POST | `/sites/{id}/action` | `wpcd_app_wordpress-app_tab_action` after mapping the body onto `$_POST` |
| GET | `/sites/{id}/tabs` | Tab schema from `wpcd_app_wordpress-app_get_tabs` |
| POST | `/sites/{id}/security-status` | Read-only hardening probe (plugins, `DISALLOW_FILE_EDIT`, PHP version) |
| GET | `/settings/providers` | Provider state as booleans, feature flags and cached counts |
| POST | `/settings/providers` | Save credentials (encrypted before storage) |
| POST | `/settings/providers/{slug}/test` | `call( 'test_connection' )` against the provider API |
| GET | `/activity` | Merged command / SSH / error feed |

### Guarantees

- **One permission callback.** `SimpleStack_REST_Controller_Base::permissions_check()` returns
  `current_user_can( 'manage_options' )` and is the only one in the namespace. `__return_true` does
  not appear anywhere under `includes/core/api/`, and the build gate fails if it does.
  Two endpoints elsewhere in the tree are public by necessity, not by oversight: the provisioning
  command callback (`includes/core/apps/class-wpcd-app.php`), which a `wget` in the shell scripts
  calls with no credentials, and the GitHub webhook
  (`includes/core/apps/wordpress-app/tabs/git-control-site.php`), which GitHub calls. The first only
  reports on a command the site is already waiting for, checked against a transient; the second
  verifies an HMAC-SHA1 signature with `hash_equals()`. `bin/guard-wpcd-prefix.sh` pins that set to
  exactly these two files, so a third one is a build failure.
- **Every argument is validated.** Each `register_rest_arg()` call carries a
  `sanitize_callback` and a `validate_callback`; the `arg_int` / `arg_string` / `arg_text` /
  `arg_object` / `arg_id` helpers make that structural rather than a habit.
- **Nothing secret is serialisable.** Every response passes through
  `SimpleStack_Secret_Redactor::redact()`. Keys matching
  `/pass|secret|token|private|authorization|_key$|api_?key/i` become `********`, PEM blocks and
  `token=` / `password=` fragments inside free text are scrubbed, and password-typed tab fields
  ship with `std` / `value` / `default` blanked. Provider routes report `has_api_key` /
  `has_ssh_key` booleans instead of values.

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
  components/TabRenderer.jsx   renders all 51 tabs from the schema
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
| `scripts/vN/raw/*.txt` | plain Bash | downloaded verbatim over HTTP from `SCRIPT_URL` |
| `scripts/vN/*.txt` (no `raw/`) | PHP template | `##TOKEN##` placeholders substituted from `script_placeholders()`, then sent as a command |

A raw script therefore cannot carry a placeholder, and **no raw script sources a sibling** —
every file a script needs is fetched explicitly by the wrapper. That is why the post-install
hook needs a `SCRIPT_POST_INSTALL_URL` / `_NAME` pair and its own `wget`, and why the fetch
sits outside the `&&` chain (a failed optional download must not abort a finished install).

Because `##TOKEN##` at the start of a line is a Bash comment when unsubstituted, `bash -n` on a
wrapper template always fails. `bin/lint-sh.sh` therefore checks both populations: raw scripts
as they ship, and wrappers after replacing every `##PLACEHOLDER##` with a plain word.

### v1 and v2 coexist

`v1/` is the upstream script set and is untouched. `v2/` is the SimpleStack set. The settings
default is `v2`; the per-server meta `wpcd_server_scripts_version` still wins, so servers
provisioned before the fork keep using `v1` and nothing needs migrating. The version dropdown
lists both folders.

What changed in `v2`, in one line each:

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
- `66-simplestack_post_install.txt` — new post-install hook, see §6.

### Port 7080

Never open to the world. `01-prepare_server.txt` applies an unconditional `ufw deny 7080`.
`56-ols_manage_admin_console.txt` then rewrites only the `:7080` listener in
`admin_config.conf` — `*:7080` when `SIMPLE_STACK_ADMIN_IP` is set, `127.0.0.1:7080` otherwise
— and deletes any inherited blanket rule before adding
`ufw allow from $SIMPLE_STACK_ADMIN_IP to any port 7080 proto tcp`. With no admin IP it adds
nothing and prints the SSH-tunnel command instead.

`SIMPLE_STACK_ADMIN_IP` originates at Settings → `simplestack_admin_ip`, is validated twice
(PHP before it reaches a command line, Bash after placeholder substitution), rejects
`0.0.0.0/0` and `::/0` outright, and requires an SSH tunnel when empty.

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

## 6. `simplestack_post_site_install`

Called by `02-install_wordpress_site.txt` once WordPress and any post-processing script have
finished:

```bash
simplestack_post_site_install "$domain" "$user_name" "$g_webserver_type"
```

Defined in `scripts/v2/raw/66-simplestack_post_install.txt`, sourced from
`9999-common-functions.txt`. `9999` defines a no-op before sourcing, so if the optional
download did not land the call still resolves and the install completes.

What it does:

1. `wp config set DISALLOW_FILE_EDIT true --raw` for the site;
2. installs and activates each plugin in `SIMPLE_STACK_SECURITY_PLUGINS`.

The plugin list arrives through `get_post_site_install_plugins()` (in
`includes/core/apps/class-wpcd-app.php`), which reads the
`simplestack_post_site_install_plugins` filter and reduces every entry to `[A-Za-z0-9._-]`, an
optional `author/plugin` slash, and nothing else. The Bash side re-checks the same set and
rejects `..`. Validation is by omission rather than escaping, because the value is interpolated
into a `su -c` command line. The filter returns an empty list by default until the One Dog
plugin slugs are supplied.

Note that the site user's home directory **is** `/var/www/<domain>/html`
(`useradd -d "$wphome"`), which is why `su - "$user_name" -c "wp ..."` needs no `--path`.

---

## 7. Providers

Registry in `includes/core/wp-cloud-deploy.php`:

| Slug | Class | Status |
|---|---|---|
| `vultr-v2` | `CLOUD_PROVIDER_API_VultrV2` | default |
| `upcloud` | `CLOUD_PROVIDER_API_Upcloud` | supported |
| `custom-server` | `CLOUD_PROVIDER_API_Custom_Server` | supported |
| `digital-ocean` | `CLOUD_PROVIDER_API_Digital_Ocean` | present, disabled by default |

Both new classes implement the full `CLOUD_PROVIDER_API` contract — `regions`, `sizes`, `keys`,
`create`, `details`, `status`, `reboot` / `on` / `off`, `delete`, snapshot create / delete /
list, `resize`, `ssh_create`, `test_connection` — declare their feature flags, cache
`regions` / `sizes`, and return `credentials_available()` booleans. Neither ever places an API
key or root password in an exception message or a log line.

DigitalOcean stays in the tree but is unregistered unless the `enable-do-provider` early option
is set.

---

## 8. Working on the plugin

```bash
# PHP and shell gates
bash bin/lint-php.sh            # php -l over every PHP file except node_modules/ and vendor/
bash bin/lint-sh.sh             # bash -n over raw scripts, substituted wrappers and bin/*.sh
bash bin/guard-wpcd-prefix.sh   # wpcd_ names, public REST callbacks, port 7080
composer lint                   # same as lint-php.sh

# Front end (only needed if you touch src/admin/)
npm ci
npm run build                   # → assets/dist/
npm run verify:css              # fails if Tailwind escapes #simplestack-admin
```

Requirements: WordPress 6.5+, PHP 8.1+, Node 20+ for builds only. `assets/dist/` is committed,
so a production install needs no Node.

After any phase of work, update `state.md` — flip the phase status in the Progress table,
refresh Overall status / Last updated / Next action, append surprises to Decisions & deviations,
and record unresolved items under Open questions / blockers. `AGENTS.md` states the same rule
and adds the project constraints that must not be broken.
