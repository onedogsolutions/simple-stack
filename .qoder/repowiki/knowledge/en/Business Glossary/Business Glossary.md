---
kind: business_term
name: Business Glossary
category: business_term
scope:
    - '**'
---

### Simple Stack
- Definition：Internal hosting control-plane WordPress plugin for One Dog Solutions. It is a hard-cutover fork with no upstream compatibility layer; GPL attribution is recorded in `NOTICE.md` and `license.txt`. It provisions servers at Vultr and UpCloud, installs WordPress over SSH, and manages every server and site from inside wp-admin. No license server, telemetry, or upsell.

### sstk_
- Definition：The four-character Simple Stack prefix used for functions, hooks, options, meta keys, Custom Post Type slugs (`sstk_app_server`, `sstk_app`), capabilities, cron hooks and Bash environment variables. It replaces the upstream prefix in place; there is no deprecated-name bridge. PHP classes and constants use the corresponding uppercase `SSTK_` prefix.
- Aliases：SSTK_

### SSTK_ADMIN_IP
- Definition：Configuration-driven host/IP/CIDR that determines whether the OpenLiteSpeed WebAdmin console (port 7080) is exposed publicly. Originates at Settings → `sstk_admin_ip`, validated twice (PHP before shell interpolation, Bash after placeholder substitution), rejects `0.0.0.0/0` and `::/0`, and requires an SSH tunnel when empty.
- Aliases：admin IP、Admin IP

### sstk_post_site_install
- Definition：Bash hook invoked after a WordPress site finishes installing. Sets `DISALLOW_FILE_EDIT true` for the site and installs/activates each plugin slug listed in `SSTK_SECURITY_PLUGINS`, which comes from the `sstk_post_site_install_plugins` filter (empty by default). The hook file is downloaded optionally; if missing, a no-op definition ensures the install still completes.
- Aliases：post-install hook

### raw scripts
- Definition：Provisioning scripts under `scripts/v2/raw/*.txt` that are downloaded verbatim from `SCRIPT_URL` and never undergo PHP placeholder substitution. They cannot carry a `##TOKEN##` placeholder and must fetch every sibling file explicitly through the wrapper.
- Aliases：raw/*

### wrapper templates
- Definition：Provisioning scripts under `scripts/v2/*.txt` (no `raw/` subdirectory) whose `##TOKEN##` placeholders are substituted by PHP's `script_placeholders()` before being sent as a command to the remote server.
- Aliases：command wrappers

### TabRenderer
- Definition：The single React component that renders all 51 server/site tabs generically from the schema returned by `GET /servers/{id}/tabs` or `GET /sites/{id}/tabs`. Adding a tab on the PHP side makes it appear in the React UI without a client change; only streaming output goes to the shared `LogDrawer`.
- Aliases：tab renderer

### Action bridge
- Definition：The mechanism that routes React actions back into the legacy tab engine: the REST layer serialises the same `get_tabs` arrays used by Meta Box and posts submitted forms to `do_instance_action()` for servers and `sstk_app_wordpress-app_tab_action` for sites, preserving legacy quirks like `$_POST['params']` and multi-line PEM promotion.
- Aliases：tab action bridge

### v2 scripts
- Definition：The only supported provisioning-script tree under `includes/core/apps/wordpress-app/scripts/v2/`. Server creation and management use it unconditionally for Ubuntu 24.04/26.04 and OpenLiteSpeed. There is no `v1/` tree, per-server selector or fallback path.
- Aliases：fixed v2、scripts/v2

### CSS isolation
- Definition：Build-time guarantee that Tailwind CSS v4 rules emitted into the admin bundle never leak outside the React mount root `#simplestack-admin`. Enforced by `important: '#simplestack-admin'`, disabling Preflight, a scoped hand-written reset, and `npm run verify:css` which asserts no top-level `html`/`body`/`*`/`:root` selectors survive.
- Aliases：Tailwind isolation、verify:css
