---
kind: business_term
name: Business Glossary
category: business_term
scope:
    - '**'
---

### SimpleStack
- Definition：Internal hosting control-plane WordPress plugin for One Dog Solutions, forked from WP Cloud Deploy 5.8.0. It provisions servers at Vultr and UpCloud, installs WordPress over SSH, and manages every server and site from inside wp-admin. Not distributed; no license server, telemetry, or upsell.

### wpcd_
- Definition：The upstream naming prefix deliberately preserved across SimpleStack — class names, Custom Post Type slugs (`wpcd_app_server`, `wpcd_app`), post meta keys, option keys, and Bash environment variables keep their WPCloudDeploy names because provisioning scripts and add-on code address them by name. Only user-facing strings, the plugin header, and the new REST namespace are branded SimpleStack.
- Aliases：WPCD_

### SIMPLE_STACK_ADMIN_IP
- Definition：Configuration-driven host/IP/CIDR that determines whether the OpenLiteSpeed WebAdmin console (port 7080) is exposed publicly. Originates at Settings → `simplestack_admin_ip`, validated twice (PHP before shell interpolation, Bash after placeholder substitution), rejects `0.0.0.0/0` and `::/0`, and requires an SSH tunnel when empty.
- Aliases：admin IP、Admin IP

### simplestack_post_site_install
- Definition：Bash hook invoked after a WordPress site finishes installing. Sets `DISALLOW_FILE_EDIT true` for the site and installs/activates each plugin slug listed in `SIMPLE_STACK_SECURITY_PLUGINS`, which comes from the `simplestack_post_site_install_plugins` filter (empty by default). The hook file is downloaded optionally; if missing, a no-op definition ensures the install still completes.
- Aliases：post-install hook

### raw scripts
- Definition：Provisioning scripts under `scripts/vN/raw/*.txt` that are downloaded verbatim over HTTP from `SCRIPT_URL` and never undergo PHP placeholder substitution. They cannot carry a `##TOKEN##` placeholder and must fetch every sibling file explicitly through the wrapper.
- Aliases：raw/*

### wrapper templates
- Definition：Provisioning scripts under `scripts/vN/*.txt` (no `raw/` subdirectory) whose `##TOKEN##` placeholders are substituted by PHP's `script_placeholders()` before being sent as a command to the remote server.
- Aliases：command wrappers

### TabRenderer
- Definition：The single React component that renders all 51 server/site tabs generically from the schema returned by `GET /servers/{id}/tabs` or `GET /sites/{id}/tabs`. Adding a tab on the PHP side makes it appear in the React UI without a client change; only streaming output goes to the shared `LogDrawer`.
- Aliases：tab renderer

### Action bridge
- Definition：The mechanism that routes React actions back into the legacy tab engine: the REST layer serialises the same `get_tabs` arrays used by Meta Box and posts submitted forms to `do_instance_action()` for servers and `wpcd_app_wordpress-app_tab_action` for sites, preserving legacy quirks like `$_POST['params']` and multi-line PEM promotion.
- Aliases：tab action bridge

### v1 / v2 scripts
- Definition：Two coexisting provisioning-script trees under `includes/core/apps/wordpress-app/scripts/`. `v1/` is the frozen upstream set; `v2/` is the SimpleStack set. Per-server meta `wpcd_server_scripts_version` selects which tree to use, so servers provisioned before the fork keep `v1` while the settings default is `v2`.
- Aliases：script version、scripts/v1、scripts/v2

### CSS isolation
- Definition：Build-time guarantee that Tailwind CSS v4 rules emitted into the admin bundle never leak outside the React mount root `#simplestack-admin`. Enforced by `important: '#simplestack-admin'`, disabling Preflight, a scoped hand-written reset, and `npm run verify:css` which asserts no top-level `html`/`body`/`*`/`:root` selectors survive.
- Aliases：Tailwind isolation、verify:css
