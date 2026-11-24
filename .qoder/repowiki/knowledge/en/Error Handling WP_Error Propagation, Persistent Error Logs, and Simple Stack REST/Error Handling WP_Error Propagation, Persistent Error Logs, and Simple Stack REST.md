---
kind: error_handling
name: 'Error Handling: WP_Error Propagation, Persistent Error Logs, and Simple Stack REST Envelope'
category: error_handling
scope:
    - '**'
source_files:
    - includes/core/class-sstk-posts-error-log.php
    - includes/core/class-sstk-posts-command-log.php
    - includes/core/api/class-sstk-rest-controller-base.php
    - includes/core/apps/class-sstk-ssh.php
    - includes/core/apps/wordpress-app/class-wordpress-ssh.php
    - includes/core/apps/wordpress-app/class-wordpress-app.php
    - includes/core/simple-stack.php
---

## Overview

The Simple Stack plugin uses two complementary error-handling strategies:

1. **Runtime errors** (validation failures, invalid IDs, SSH/connectivity problems) are propagated as `\WP_Error` objects returned from functions — the standard WordPress error-return convention.
2. **Operational / diagnostic errors** (exceptions during provisioning, command execution, DNS operations, etc.) are persisted to a dedicated custom post type `sstk_error_log`, which is then surfaced in the admin UI under *Simple Stack → Error Log*.
3. The new **Simple Stack REST layer** (`simple-stack/v1`) wraps every response through a shared envelope that redacts secrets, and returns structured `WP_Error` responses for failures.

There is no custom exception hierarchy, no global panic/recover, and no middleware framework beyond the base controller's `permissions_check` callback.

## Key Files and Packages

- `includes/core/class-sstk-posts-error-log.php` — defines the `SSTK_ERROR_LOG` singleton, registers the `sstk_error_log` CPT, and exposes `add_error_log_entry($msg, $type, $file, $line, $data)`.
- `includes/core/class-sstk-posts-command-log.php` — sibling log model; also returns `\WP_Error` when an invariant is violated (e.g. "Command X for Y has N instances").
- `includes/core/api/class-sstk-rest-controller-base.php` — base class for all Simple Stack REST controllers; provides `respond()`, `error()`, argument helpers, and the single `try/finally` block around tab-action dispatch.
- `includes/core/simple-stack.php` — top-level bootstrap; contains `error_log()` calls for missing build artifacts and a generic PHP error handler that writes to the server's PHP error log.
- `includes/core/apps/class-sstk-ssh.php` and `includes/core/apps/wordpress-app/class-wordpress-ssh.php` — SSH-layer methods return `\WP_Error` with human-readable messages for connectivity and file-not-found conditions.
- `includes/core/apps/wordpress-app/class-wordpress-app.php` — app-layer validation returns `\WP_Error` for invalid IDs, duplicate domains, bad passwords/emails/usernames, and malformed create-site payloads.

## Architecture and Conventions

### 1. `WP_Error` as the primary propagation mechanism

Business logic throughout the codebase returns `\WP_Error` on failure rather than throwing exceptions or returning sentinel values. Examples observed:

- `class-sstk-ssh.php`: connection failures, empty result sets, missing files → `new \WP_Error( $this->construct_error( __( ... ) ) )`.
- `class-wordpress-app.php`: `Invalid server ID`, `Invalid site ID`, `This domain already exists in our system.`, `The password for the site contains invalid characters.`.
- `class-sstk-command-log.php`: returns `new \WP_Error( "Command $name for $id has " . count( $posts ) . ' instances' )` when a uniqueness invariant is broken.

Callers are expected to check `is_wp_error()` before proceeding. There is no central dispatcher that converts `WP_Error` into a unified response shape outside the Simple Stack REST layer.

### 2. Persistent error logging via `SSTK_ERROR_LOG`

`SSTK_ERROR_LOG::instance()->add_error_log_entry($msg, $type, $file, $line, $data)` persists an error as a private `sstk_error_log` post with meta fields `error_type`, `error_msg`, `error_file`, `error_line`, and optionally `error_data`. Before insertion it:

- Applies exclude/include filters configured via `exclude_msg_txt` and `include_msg_txt` options.
- Applies include-file filtering via `include_file_txt`.
- Strips known password/key-value pairs using `sstk_replace_key_value_paired_strings`.
- Truncates the post title to 100 chars.

The CPT is registered with `show_in_menu => 'edit.php?post_type=sstk_app_server'`, `publicly_queryable => false`, and capability `sstk_manage_logs`, so only authorized admins can view logs.

### 3. Simple Stack REST error envelope

Every Simple Stack route goes through `SSTK_REST_Controller_Base`:

- `register_route()` attaches a shared `permission_callback` (`current_user_can('manage_options')`).
- `respond($data, $status)` wraps success payloads in `{ ok: true, data: <redacted>, meta: { request_date, namespace } }`.
- `error($code, $message, $status = 400)` returns `new WP_Error($code, $message, ['status' => $status])`, which WordPress REST translates to a JSON `{ code, message, data: { status } }` response.
- All response data passes through `SSTK_Secret_Redactor::redact()` to scrub credentials.

The base controller also normalizes input via typed helpers (`arg_int`, `arg_string`, `arg_text`, `arg_object`, `arg_params`) each pairing a `sanitize_callback` with a `validate_callback`, so malformed requests never reach handlers.

### 4. Exception handling is localized

Only a handful of locations use `try/catch/finally`:

- `class-sstk-rest-controller-base.php::dispatch_tab_action()`: wraps the legacy `apply_filters(..._tab_action...)` call in `try/finally` to guarantee `$_POST`, `$_REQUEST`, and `$_FILES` are restored even if a tab handler throws.
- `class-sstk-dns.php`, `class-sstk-posts-app-server.php`, `class-sstk-rest-api-controller-base.php` (WordPress App REST layer): local try blocks around external calls (DNS, provider APIs).

There is no global exception handler converting uncaught exceptions into user-facing messages or persistent logs in this branch.

### 5. Admin notices vs. programmatic errors

User-facing admin notices are handled separately by `includes/core/functions-handle-admin-notices.php`; they are not part of the error-propagation path but serve as the presentation layer for non-fatal issues in the classic Simple Stack admin screens.

## Conventions and Constraints

- **Return `\WP_Error` for recoverable business-rule violations** (invalid IDs, duplicates, auth failures). This is the dominant pattern across `apps/`, `providers/`, and core classes.
- **Persist operational failures via `SSTK_ERROR_LOG::instance()->add_error_log_entry()`** rather than relying solely on `error_log()` or PHP's default error handler. The helper supports configurable inclusion/exclusion filters and credential scrubbing.
- **Simple Stack REST endpoints must not expose secrets**: success responses go through `SSTK_Secret_Redactor::redact()`, and error responses use the shared `error()` helper so clients receive a uniform `{ code, message, data.status }` shape.
- **All Simple Stack routes are admin-only**: the base controller enforces `manage_options` via `permissions_check()`; there is no per-route public permission callback.
- **Input validation is declarative**: route parameters are defined with `arg_*` helpers that pair sanitization and validation callbacks, reducing ad-hoc `isset()`/`ctype_digit()` checks inside handlers.
- **Legacy tab-action dispatch preserves superglobals safely**: `dispatch_tab_action()` snapshots and restores `$_POST`/`$_REQUEST`/`$_FILES` in a `finally` block, ensuring partial failures do not leak state into subsequent handlers.
- **No custom exception types or global error middleware** were found in this branch; error handling is distributed across function return values, the persistent log model, and the REST base controller.