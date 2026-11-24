# System API

<cite>
**Referenced Files in This Document**
- [SPA REST registrar](file://includes/core/api/class-sstk-rest-api.php)
- [SPA REST base](file://includes/core/api/class-sstk-rest-controller-base.php)
- [Servers controller](file://includes/core/api/class-sstk-rest-servers.php)
- [Sites controller](file://includes/core/api/class-sstk-rest-sites.php)
- [Logs controller](file://includes/core/api/class-sstk-rest-logs.php)
- [Tabs controller](file://includes/core/api/class-sstk-rest-tabs.php)
- [Providers controller](file://includes/core/api/class-sstk-rest-providers.php)
- [Security controller](file://includes/core/api/class-sstk-rest-security.php)
</cite>

## Scope

This reference covers the React admin SPA routes registered by `SSTK_REST_API` under
`simple-stack/v1`. Every route in this table uses `SSTK_REST_Controller_Base::permissions_check()`
and therefore requires `manage_options`.

The WordPress App controller family shares the namespace but uses `sstk_is_admin()` and different
response handling. Two public callbacks also share the namespace and authenticate with a pending
command transient or GitHub HMAC. Those are documented in
[Authentication Mechanisms](../Security%20%26%20Authentication/Authentication%20Mechanisms.md).

## Shared contract

Successful SPA responses use:

```json
{
  "ok": true,
  "data": {},
  "meta": {
    "request_date": "2026-11-23T12:00:00+00:00",
    "namespace": "simple-stack/v1"
  }
}
```

`data` is passed through `SSTK_Secret_Redactor`. Errors are WordPress `WP_Error` responses with a
code, message and status. List routes accept `page` and `per_page`; the default page size is 25 and
the maximum is 200.

The React client sends the REST nonce as `X-WP-Nonce` and uses same-origin credentials.

## Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/servers` | Paginated server list with provider, region, status and search filters |
| POST | `/servers` | Create a cloud server and queue provisioning |
| GET | `/servers/{id}` | Server summary, metadata and availability |
| DELETE | `/servers/{id}` | Run the server deletion chain |
| POST | `/servers/{id}/action` | Dispatch a server tab action, with lifecycle-action fallback |
| GET | `/servers/{id}/logs` | Server command, SSH and error logs |
| GET | `/servers/{id}/tabs` | Server tab schema for `TabRenderer` |
| GET | `/sites` | Paginated WordPress site list with optional server filter |
| POST | `/sites` | Validate and deploy a WordPress site |
| GET | `/sites/{id}` | Site summary, metadata and action status |
| DELETE | `/sites/{id}` | Run site permission checks and trash the site record |
| POST | `/sites/{id}/action` | Map the request onto the site tab-action pipeline |
| GET | `/sites/{id}/tabs` | Site tab schema for `TabRenderer` |
| GET | `/settings/providers` | Provider credential presence, features and cached counts |
| POST | `/settings/providers` | Save encrypted provider credentials and SSH keys |
| POST | `/settings/providers/{slug}/test` | Test the configured provider connection |
| POST | `/sites/{id}/security-status` | Read-only site hardening probe |
| GET | `/activity` | Merged recent command, SSH and error activity |

## Server operations

`POST /servers` validates the provider and request attributes, calls the server creation service and
records a pending task. The active providers are Vultr v2 and UpCloud 1.3; OpenLiteSpeed is the only
supported web server.

Server actions first dispatch through the existing tab filter chain. If no tab claims the action,
the controller falls back to `do_instance_action()` for lifecycle verbs. This preserves the tab
engine rather than reimplementing it in React.

## Site operations

`POST /sites` delegates deployment validation to the existing WordPress App installer. Site actions
use the same superglobal adapter as server tab actions, including multipart upload support for file
and key fields.

`DELETE /sites/{id}` respects the app post type's permission gate before trashing the local record.
It does not claim to erase remote files or databases unless the underlying site deletion service
explicitly performs that work.

## Provider settings

The save route is `POST /settings/providers`, not `/settings/providers/save_provider`.

- Vultr accepts an API key.
- UpCloud accepts a user name and password.
- Both can accept an SSH private key.
- Empty credential inputs preserve stored values.
- Responses expose presence booleans and feature flags, never raw credentials.

Region and size counts are cached for 300 seconds. A failed provider connection test is returned as
a successful SPA envelope containing `connected: false` and a diagnostic message.

## Logs and tabs

Log routes support `all`, `command`, `ssh` and `error`, an ISO-8601 `since` cursor and a bounded
`limit`. Tab routes serialize the existing Meta Box field arrays into the schema consumed by
`TabRenderer`; password defaults are blanked before serialization.

## Security probe

`POST /sites/{id}/security-status` reads plugin inventory, `DISALLOW_FILE_EDIT`, CLI PHP and the
OpenLiteSpeed vhost handler over one SSH command. See
[Security API](Security%20API.md) for response and failure semantics.
