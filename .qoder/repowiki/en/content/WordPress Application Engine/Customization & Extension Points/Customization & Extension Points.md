# Customization and Extension Points

<cite>
**Referenced Files in This Document**
- [Base app](file://includes/core/apps/class-sstk-app.php)
- [WordPress App engine](file://includes/core/apps/wordpress-app/class-wordpress-app.php)
- [Site tabs](file://includes/core/apps/wordpress-app/tabs/tabs.php)
- [SPA REST registrar](file://includes/core/api/class-sstk-rest-api.php)
- [WordPress App REST base](file://includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-base.php)
- [Script handlers](file://includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/script-handlers.php)
- [Provisioning scripts](file://includes/core/apps/wordpress-app/scripts/v2/readme.txt)
</cite>

## Extension model

Simple Stack extends WordPress behavior through actions, filters, service registration and the
existing app interfaces. There is no compatibility layer for retired upstream identifiers or
providers. Extensions must use `SSTK_` for classes/constants, `sstk_` for functions/data/hooks,
`sstk-` for assets and `simple-stack` for the text domain and REST namespace.

## App and tab extensions

`SSTK_APP` provides the base application abstraction. `SSTK_WORDPRESS_APP` loads site tabs, server
tabs, script handlers and REST controllers.

Use these hooks to add tabs:

- `sstk_wpapp_include_app_tabs`
- `sstk_wpapp_include_server_tabs`

A tab contributes its field schema through the existing `get_tabnames` / `get_tabs` filters and
handles submissions through the tab-action pipeline. The React `TabRenderer` renders supported
Meta Box field types generically, so a new PHP tab does not require a bespoke React component.

Server tab actions dispatch through the app tab-action chain; lifecycle verbs can fall back to
`do_instance_action()`.

## Provisioning extensions

All runtime scripts live under `scripts/v2`. Add raw Bash phases under `raw/`, update the wrapper
that downloads and invokes them, and place reusable logic in `9999-common-functions.txt`.

Use checkpoints for long or non-idempotent phases. Validate every placeholder before it reaches a
shell command, and keep new work within the OpenLiteSpeed-only Ubuntu/PHP matrix. There is no v1
fallback to extend.

The post-install security plugin list is empty by default and filterable through
`sstk_post_site_install_plugins`. Plugin slugs are validated in PHP and Bash.

## REST extensions

Choose the correct family:

### SPA controllers

Extend `SSTK_REST_Controller_Base`, register routes with its helpers and return data through
`respond()`. This provides `manage_options`, argument helpers, the standard envelope and secret
redaction. Register the controller with `SSTK_REST_API`.

### WordPress App controllers

Use `sstk_wpapp_include_rest_api` to load files and
`sstk_app_{app}_rest_api_controller_list` to register controller classes. Extend
`SSTK_REST_API_Controller_Base` to preserve `sstk_is_admin()` and exception handling. This family
has no automatic SPA envelope or redactor, so handlers must avoid returning secrets.

### Public callbacks

The provisioning callback and GitHub webhook are the only approved public permission callbacks.
They authenticate independently. Do not add another `__return_true` route without a reviewed
credential mechanism and a guard update.

## Providers

The supported product set is Vultr v2 and UpCloud 1.3. Provider classes implement the common API
contract for regions, sizes, keys, lifecycle operations and feature flags. Adding another provider
would be a product-scope change, not a routine compatibility extension.

Credentials must be encrypted before storage and omitted from responses. UpCloud uses user
name/password; Vultr uses an API key.

## Data and permissions

Custom post types, meta, options, capabilities, cron hooks and tables use the four-character
`sstk_` prefix. Post-type keys must remain within WordPress's 20-character limit.

Administrators and super administrators pass `sstk_is_admin()`. Team and per-resource permissions
continue to use the permission-type post type and `sstk_permission_assignments` table for domain
operations that support delegated access.

## Security requirements for extensions

- Never log or return plaintext root passwords, private SSH keys, provider tokens or encryption
  keys.
- Do not weaken the port 7080 fail-closed rules.
- Do not introduce Nginx, PHP-FPM or LiteSpeed Enterprise provisioning paths.
- Do not restore retired commercial licensing, telemetry or upstream service dependencies.
- Run PHP lint, shell syntax, prefix guard, build and CSS isolation checks for the relevant files.
