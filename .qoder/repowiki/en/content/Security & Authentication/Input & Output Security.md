# Input and Output Security

<cite>
**Referenced Files in This Document**
- [SPA REST base](file://includes/core/api/class-sstk-rest-controller-base.php)
- [WordPress App REST base](file://includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-base.php)
- [SPA secret redactor](file://includes/core/api/class-sstk-secret-redactor.php)
- [Security probe](file://includes/core/api/class-sstk-rest-security.php)
- [Server preparation](file://includes/core/apps/wordpress-app/scripts/v2/raw/01-prepare_server.txt)
- [OLS WebAdmin management](file://includes/core/apps/wordpress-app/scripts/v2/raw/56-ols_manage_admin_console.txt)
- [OLS vhost helpers](file://includes/core/apps/wordpress-app/scripts/v2/raw/9999-common-functions.txt)
</cite>

## Request validation

SPA routes declare argument schemas with sanitization and validation callbacks. The base helpers
cover IDs, bounded integers, enum strings, free-form text, objects and tab parameter payloads.
Optional integer and string arguments remain `null` when omitted instead of receiving a default
that can fail minimum-bound validation.

WordPress App controllers use `validate_required_parameters()` and controller-specific validation.
They do not inherit the SPA argument helpers, so each new controller must define its own input
checks.

## Legacy tab-action adapter

Tab handlers expect values in `$_POST`, `$_REQUEST` and `$_FILES`. The SPA action bridge maps REST
parameters into those structures only for the duration of the dispatch and restores the previous
values in `finally`. Multiline SSH key fields are promoted to the top-level keys expected by the
legacy parser.

Extensions should use the adapter rather than reading unsanitized request bodies directly.

## Output redaction

`SSTK_Secret_Redactor` is applied by `SSTK_REST_Controller_Base::respond()`. It masks credential-like
keys, PEM blocks and embedded token/password assignments, and blanks password-field defaults.

The redactor is not global middleware. WordPress App controller responses, public callback
responses, admin-ajax responses and persisted operational logs do not automatically pass through
it. Each of those boundaries must avoid secret exposure explicitly.

## SSH and shell boundaries

The security probe uses `escapeshellarg()` for the site path and vhost path and runs read-only
commands only. Provisioning values that reach shell command lines are validated before
interpolation and revalidated in Bash where appropriate.

The OLS admin IP is validated in PHP and by `gf_normalise_admin_ip()`. Empty, malformed, placeholder
or world-wide CIDR values fail closed. Port 7080 is denied by default and can be allowed only from a
validated admin IP/CIDR.

## Active provisioning stack

Runtime provisioning is fixed to `includes/core/apps/wordpress-app/scripts/v2/`. The supported stack
is OpenLiteSpeed on Ubuntu 24.04/26.04 with `lsphp83`, `lsphp84` and `lsphp85`. Nginx, PHP-FPM and
LiteSpeed Enterprise are not supported execution paths.

The active scripts:

- reject unsupported web server types before installation;
- resolve only the supported Ubuntu releases;
- install explicit LSPHP versions and make 8.4 the default;
- pin `ols1clk.sh` to tag `v3.1`, syntax-check it and optionally verify
  `SSTK_OLS1CLK_SHA256`;
- keep WebAdmin on loopback unless a trusted admin IP is configured; and
- apply OLS vhost deny/rewrite rules through `gf_harden_ols_conf()`.

## Logging

Never log plaintext root passwords, private SSH keys, encryption keys or cloud API tokens. The SPA
redactor protects serialized SPA responses; it does not retroactively sanitize data written to
command logs, error logs or remote files. Callers must scrub or omit secrets before logging.
