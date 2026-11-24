# Security API

<cite>
**Referenced Files in This Document**
- [Security controller](file://includes/core/api/class-sstk-rest-security.php)
- [SPA REST base](file://includes/core/api/class-sstk-rest-controller-base.php)
- [React security view](file://src/admin/routes/SecurityStatus.jsx)
- [React API client](file://src/admin/lib/api.js)
</cite>

## Endpoint

```text
POST /simple-stack/v1/sites/{id}/security-status
```

- `id` is an integer `sstk_app` post ID.
- The SPA permission callback requires `manage_options`.
- The React client sends `X-WP-Nonce` with a same-origin credentialed request.
- The operation is read-only: it does not install plugins, change configuration or modify files.

## Response

```json
{
  "ok": true,
  "data": {
    "app_id": 42,
    "server_id": 7,
    "domain": "example.com",
    "connected": true,
    "plugins": [
      { "name": "Example", "status": "active", "version": "1.0", "update": "none" }
    ],
    "file_edit_disallowed": true,
    "php_version": "8.4",
    "message": ""
  },
  "meta": {
    "request_date": "2026-11-23T12:00:00+00:00",
    "namespace": "simple-stack/v1"
  }
}
```

The response data is redacted by the SPA base controller. The probe never includes SSH keys,
passwords or provider credentials.

## Probe implementation

`resolve_ssh()` and `parse_probe_output()` are methods of `SSTK_REST_Security`, not methods of the
base controller. The controller resolves the parent server and provider, obtains SSH connection
attributes and executes one merged command from `/var/www/<domain>/html`.

Four output packets are separated by `#SSTK#`:

1. `wp plugin list --format=json --allow-root`
2. `wp config get DISALLOW_FILE_EDIT --allow-root`
3. `php -v`
4. the vhost's `lsapi:lsphpXX` handler

The vhost handler takes precedence over CLI PHP because it identifies the PHP process serving the
site. A three-packet response remains parseable; packet four is optional.

## Failure semantics

| Condition | Result |
|---|---|
| Missing/invalid site ID | WordPress argument validation error |
| ID is not an `sstk_app` post | HTTP 404, `sstk_site_not_found` |
| Missing domain or parent server | HTTP 200 envelope, `connected: false`, diagnostic `message` |
| Provider/SSH details unavailable | HTTP 200 envelope, `connected: false`, diagnostic `message` |
| SSH command fails | HTTP 200 envelope, `connected: false`, diagnostic `message` |
| Command succeeds | HTTP 200 envelope, `connected: true`, parsed posture fields |

Operational connectivity failures are deliberately data in a successful envelope so the React
view can render a useful diagnostic. Clients must not treat HTTP 200 alone as proof that
`connected` is true.

## Verification boundary

The parser can be exercised without a live SSH server, but a real provisioned OpenLiteSpeed site is
required to verify the complete probe. PHPUnit integration tests remain blocked in the local
environment until a MySQL test database is available; documentation must not claim those tests
have already executed.
