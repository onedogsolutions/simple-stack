# API Layer Design

<cite>
**Referenced Files in This Document**
- [SPA REST registrar](file://includes/core/api/class-sstk-rest-api.php)
- [SPA REST base](file://includes/core/api/class-sstk-rest-controller-base.php)
- [WordPress App REST base](file://includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-base.php)
- [WordPress App controller loading](file://includes/core/apps/wordpress-app/class-wordpress-app.php)
- [Provisioning callback](file://includes/core/apps/class-sstk-app.php)
- [GitHub webhook](file://includes/core/apps/wordpress-app/tabs/git-control-site.php)
- [React API client](file://src/admin/lib/api.js)
</cite>

## Two controller families

Simple Stack uses the `simple-stack/v1` namespace for two related but distinct REST controller
families. They must not be documented as one uniform security or response layer.

| Family | Registrar | Base class | Authorization | Response behavior |
|---|---|---|---|---|
| React admin SPA | `SSTK_REST_API` | `SSTK_REST_Controller_Base` | `current_user_can( 'manage_options' )` | `{ ok, data, meta }`; data redacted |
| WordPress App | `SSTK_WORDPRESS_APP::include_rest_api()` | `SSTK_REST_API_Controller_Base` | `sstk_is_admin()` | controller result passed through `rest_ensure_response()`; exceptions become `WP_Error` |

The SPA registrar loads six resource controllers: servers, sites, logs, tabs, providers and
security. The WordPress App family loads servers, sites, change-domain, clone-site and tasks
controllers when `wordpress_app_rest_api_enable` is enabled. Its controller list is filterable
through `sstk_app_{app}_rest_api_controller_list`.

## SPA request lifecycle

1. WordPress routes a request under `simple-stack/v1`.
2. `SSTK_REST_API` instantiates each controller extending `SSTK_REST_Controller_Base`.
3. The shared permission callback requires `manage_options`.
4. WordPress validates route arguments using the controllers' sanitize and validate callbacks.
5. The handler delegates to existing server, site, provider, log or WordPress App services.
6. `respond()` redacts the data and adds request metadata.
7. Failures return `WP_Error` with a machine-readable code and HTTP status.

The SPA base also owns paging, typed argument builders and the tab-action bridge.
`dispatch_tab_action()` temporarily maps REST parameters into the legacy `$_POST`, `$_REQUEST` and
`$_FILES` shapes expected by tab handlers, then restores those superglobals in `finally`.

## WordPress App request lifecycle

`SSTK_REST_API_Controller_Base` registers controller routes on `rest_api_init`. Its permission
callback calls `sstk_is_admin()`, which accepts a super administrator or a user with
`manage_options`. Handler callbacks catch `Throwable`, convert it to a `WP_Error` and return a REST
response.

This family does not use the SPA response envelope or automatic secret redactor. The
`disable_status_code` request parameter can intentionally convert an exception status to HTTP 200,
so clients must inspect the error payload as well as the HTTP status.

## Authentication and nonces

The React client uses same-origin credentialed requests and sends the WordPress REST nonce in the
`X-WP-Nonce` header. Cookie authentication identifies the user; the nonce protects the request
against cross-site misuse; the permission callback enforces authorization. A nonce is not a
replacement for the capability check.

The two public callbacks use independent authentication and are not SPA controllers:

| Route | Caller | Authentication before side effects |
|---|---|---|
| `GET|POST /simple-stack/v1/command/{id}/{name}/{status}/{cmdid}/` | provisioning scripts over SSH | pending-command transient for the server/app and command name |
| `GET|POST /simple-stack/v1/handle-git-webhook/{randomid}/{id}/` | GitHub | non-empty per-site secret and HMAC-SHA1 verified with `hash_equals()` |

`bin/guard-sstk-prefix.sh` pins the public permission callbacks to these two implementation files.

## Extension boundaries

- Add SPA behavior by extending `SSTK_REST_Controller_Base` and registering the controller with
  `SSTK_REST_API`.
- Add WordPress App behavior through `sstk_wpapp_include_rest_api` and the controller-list filter.
- Reuse existing domain services instead of duplicating provider, SSH or provisioning logic.
- New SPA responses must go through `respond()`; new WordPress App responses must explicitly
  avoid secret exposure because they have no automatic redactor.
