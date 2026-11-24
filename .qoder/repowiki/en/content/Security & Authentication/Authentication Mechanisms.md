# Authentication Mechanisms

<cite>
**Referenced Files in This Document**
- [SPA REST base](file://includes/core/api/class-sstk-rest-controller-base.php)
- [WordPress App REST base](file://includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-base.php)
- [Admin helper](file://includes/core/functions.php)
- [React API client](file://src/admin/lib/api.js)
- [Provisioning callback](file://includes/core/apps/class-sstk-app.php)
- [GitHub webhook](file://includes/core/apps/wordpress-app/tabs/git-control-site.php)
</cite>

## Overview

Simple Stack has four distinct REST authorization paths. WordPress cookie authentication and REST
nonces apply to browser requests, while the provisioning callback and GitHub webhook authenticate
with their own credentials because their callers cannot log in to WordPress.

## React admin SPA

All routes registered by `SSTK_REST_API` use:

```php
current_user_can( 'manage_options' )
```

The React client sends credentials with `credentials: 'same-origin'` and includes the localized
WordPress REST nonce in `X-WP-Nonce`. Cookie authentication identifies the user, the nonce binds the
request to the WordPress session, and the permission callback enforces authorization.

Anonymous or underprivileged requests are rejected by WordPress before the controller handler
runs. The SPA base does not use `__return_true`.

## WordPress App controllers

Controllers extending `SSTK_REST_API_Controller_Base` use `sstk_is_admin()`. That helper returns
true for a super administrator or a user with `manage_options`.

These controllers do not use the SPA response envelope or automatic secret redactor. Their base
class catches exceptions and converts them to `WP_Error`; clients must inspect both HTTP status and
response data.

## Provisioning command callback

```text
GET|POST /simple-stack/v1/command/{id}/{name}/{status}/{cmdid}/
```

Provisioning scripts call this route with a bare `wget`, so it cannot require a WordPress login.
`perform_command()` accepts the report only when a pending-command transient exists for the
server/app ID and command name. Unknown or expired commands are rejected before completion hooks
or status updates run.

The numeric command timestamp in the URL is carried through the callback contract; the expiring
transient is the authoritative proof that the command is pending.

## GitHub webhook

```text
GET|POST /simple-stack/v1/handle-git-webhook/{randomid}/{id}/
```

GitHub has no WordPress account. The handler:

1. requires an `X-Hub-Signature` header;
2. accepts only the `sha1` algorithm;
3. loads and decrypts the per-site webhook secret;
4. rejects a missing site or empty secret;
5. computes HMAC-SHA1 over the raw request body; and
6. compares the supplied and computed signatures with `hash_equals()`.

The random URL component is not a substitute for signature verification. The non-empty secret and
constant-time comparison are the security boundary.

## AJAX and other hooks

Legacy admin-ajax actions are separate from REST. Each retained handler must keep its own nonce
and permission checks, such as `check_ajax_referer()` plus `sstk_is_admin()`. They must not be
described as protected by the SPA REST permission callback.

## Extension rules

- New SPA controllers must extend `SSTK_REST_Controller_Base` and use `respond()`.
- New WordPress App controllers must preserve the `sstk_is_admin()` permission callback and handle
  secrets explicitly.
- A new public callback requires an independent, reviewed authentication mechanism and an update
  to the guard that pins the current set of two public files.
- Never replace a permission check with nonce verification alone; they solve different problems.

## Test status

Static source and harness checks cover the permission model, but the PHPUnit authentication suite
cannot run locally until a MySQL test database is available. Live HTTP 401/403 behavior must be
verified on a WordPress test site before release.
