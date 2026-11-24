# Deployment and Operations

<cite>
**Referenced Files in This Document**
- [Plugin entry point](file://simple-stack.php)
- [Core bootstrap](file://includes/core/simple-stack.php)
- [Required plugins](file://required_plugins/sstk-required-plugins.php)
- [Provider settings controller](file://includes/core/api/class-sstk-rest-providers.php)
- [Provisioning scripts](file://includes/core/apps/wordpress-app/scripts/v2/readme.txt)
- [Uninstaller](file://uninstall.php)
- [Build state](file://state.md)
</cite>

## Supported deployment

| Layer | Supported choice |
|---|---|
| WordPress | 6.5 or later |
| PHP | 8.1 or later for the control-plane plugin |
| Cloud providers | Vultr API v2 and UpCloud API 1.3 |
| Provisioned OS | Ubuntu 24.04 LTS and 26.04 LTS |
| Web server | OpenLiteSpeed only |
| Provisioned PHP | `lsphp83`, `lsphp84` (default), `lsphp85` |
| Admin UI | React 19 + Tailwind CSS v4 inside `wp-admin` |
| REST namespace | `simple-stack/v1` |

Meta Box is a hard deployment prerequisite. The bundled required-plugin flow marks it required, and
settings reads return false when `rwmb_meta()` is unavailable.

## Installation

1. Place the plugin in `wp-content/plugins/simple-stack/`.
2. Define a strong `SSTK_ENCRYPTION_KEY` in `wp-config.php` before activation.
3. Activate **Simple Stack** and complete the setup wizard.
4. Install and activate Meta Box if the required-plugin flow has not already done so.
5. Save Vultr and/or UpCloud credentials under **Simple Stack → Providers**.
6. Use **Test connection**, then provision a server from **Simple Stack → Servers**.

`assets/dist/` is committed. Production installs do not need Node; development builds require the
npm toolchain.

## Secret operations

Stored secrets use authenticated `sstk-encrypt-v2:` envelopes. The preferred key is
`SSTK_ENCRYPTION_KEY`; runtime fallback is the `sstk_encryption_key_v2` option. Legacy v1 envelopes
are not accepted. Back up the key and re-enter credentials after any key change.

Do not log root passwords, private SSH keys, encryption keys or provider tokens.

## OpenLiteSpeed WebAdmin

Port 7080 is denied by default. A validated `sstk_admin_ip` value in `sstk_settings` permits access
only from that address or CIDR. With no value, WebAdmin remains on loopback and provisioning prints
an SSH-tunnel command.

The admin-IP field currently has no rendered settings UI; set it programmatically until a dedicated
Simple Stack field is added. World-wide CIDRs such as `0.0.0.0/0` and `::/0` are rejected.

## Provisioning runtime

All server creation and management operations resolve scripts under
`includes/core/apps/wordpress-app/scripts/v2/`. There is no version selector, per-server override or
v1 fallback. Only the v2 tree is present in the working tree.

Raw scripts are downloaded verbatim; command wrappers are PHP templates whose `##TOKEN##`
placeholders are substituted before execution. Checkpoints under
`.sstk-server-provision-checkpoints` make long provisioning phases resumable.

## Updates and OPcache

Replacing plugin files does not necessarily invalidate compiled PHP bytecode. After a file-based
update, run `opcache_invalidate()` for the changed files or reload the PHP/OLS service according to
the host's operational procedure. Otherwise old bytecode can mask the deployed version.

## Data portability

Cross-site data sync is retired. The service class, settings tab, AJAX handlers, cron wiring and
remote receiver are absent. There is no `/simple-stack/v1/receivedata` route, import workflow or
compatibility flag. Configure each control-plane site explicitly.

## Uninstall

The uninstaller removes `sstk_` options and transients, prefixed post records (including
servers, sites and logs), attached comments and metadata, prefixed user/term metadata, taxonomies,
every scheduled `sstk_` cron hook, custom role definitions, role assignments, direct `sstk_` user
capabilities and custom tables across a multisite network. `composer test:uninstall` exercises these
paths with a recording database double. It does not destroy cloud servers or remote WordPress sites;
those continue running and must be removed separately at the provider.

## Release verification still required

- Fresh activation and uninstall on WordPress with Meta Box installed.
- Live Vultr and UpCloud create/details/power/delete cycles.
- Live Ubuntu 24.04 and 26.04 OpenLiteSpeed provisioning.
- WebAdmin, HTTPS renewal and vhost hardening checks on a real OLS server.
- PHPUnit with a running MySQL test database.

See [`state.md`](../state.md) for the authoritative current gate results and blockers.
