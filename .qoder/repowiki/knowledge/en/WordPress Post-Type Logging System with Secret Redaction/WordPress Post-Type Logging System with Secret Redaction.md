---
kind: logging_system
name: WordPress Post-Type Logging System with Secret Redaction
category: logging_system
scope:
    - '**'
source_files:
    - includes/core/class-wpcd-posts-log.php
    - includes/core/class-wpcd-posts-error-log.php
    - includes/core/class-wpcd-posts-command-log.php
    - includes/core/class-wpcd-posts-ssh-log.php
    - includes/core/api/class-simplestack-rest-logs.php
    - src/admin/components/LogDrawer.jsx
    - assets/js/wpcd-logs-common.js
---

## Approach

WPCD does not use an external logging framework (no Monolog, no `error_log`-based file sink). All application logs are persisted as **private WordPress posts** in dedicated custom post types, then surfaced through the admin UI and a SimpleStack React dashboard via the `simple-stack/v1` REST API.

There is no runtime log-level system; instead, filtering happens at write time through configurable include/exclude rules on message text and source file paths.

## Key Files

- `includes/core/class-wpcd-posts-log.php` — Base class `WPCD_POSTS_LOG`; registers shared hooks (purge AJAX, search extension, capability checks, auto-trim, row-action stripping).
- `includes/core/class-wpcd-posts-error-log.php` — `WPCD_ERROR_LOG`, post type `wpcd_error_log`, fields `error_type`, `error_msg`, `error_file`, `error_line`, `error_data`.
- `includes/core/class-wpcd-posts-command-log.php` — `WPCD_COMMAND_LOG`, post type `wpcd_command_log`, fields `parent_post_id`, `command_type`, `command_result`, `command_reference`.
- `includes/core/class-wpcd-posts-ssh-log.php` — `WPCD_SSH_LOG`, post type `wpcd_ssh_log`, fields `parent_post_id`, `ssh_cmd`, `ssh_cmd_result`.
- `includes/core/api/class-simplestack-rest-logs.php` — `SimpleStack_REST_Logs`; maps public log names (`command`, `ssh`, `error`) to post types and exposes `GET /servers/{id}/logs` + `GET /activity`.
- `src/admin/components/LogDrawer.jsx` — React component polling `/servers/{id}/logs` every 15 s with a `since` cursor, rendering color-coded entries by type.
- `assets/js/wpcd-logs-common.js` — Client-side purge/clean-up AJAX helpers for the legacy log list screens.
- `includes/templates/error_log.php`, `command_log.php`, `ssh_log.php` — Admin meta-box templates for individual log entries.

## Architecture

### Post-type model
Each log category is a singleton class extending `WPCD_POSTS_LOG`:

```
WPCD_Posts_Base
 └── WPCD_POSTS_LOG
      ├── WPCD_ERROR_LOG   → wpcd_error_log
      ├── WPCD_COMMAND_LOG → wpcd_command_log
      └── WPCD_SSH_LOG     → wpcd_ssh_log
```

The base class owns:
- Common admin hooks: remove Edit/Quick Edit rows, strip the Publish metabox, add "Back To List" button, enqueue `wpcd-logs-common.js`.
- Bulk action removal (`bulk_actions-edit-{cpt}`) so logs cannot be edited from the list.
- Capability gate: access to any log listing requires `wpcd_manage_logs` (`wpcd_posts_log_load`).
- Search extension: `pre_get_posts` adds `meta_query` over the descendant's declared `post_search_fields` plus title matching.
- Auto-trim: `clean_up_old_log_entries($post_type)` deletes oldest private posts when count exceeds a per-post-type setting (`auto_trim_*_log_limit`), capped by `most_items_to_delete`. The constant `WPCD_AUTO_TRIM_LOG_LIMIT` overrides settings.
- Sensitive-value scrubbing via `wpcd_get_pw_terms_to_clean()` delegating to `WPCD()->wpcd_get_pw_terms_to_clean()`.

### Write path
- Error logs: `WPCD_ERROR_LOG::add_error_log_entry($msg, $type, $file, $line, $data)` applies exclude/include filters on message and file before inserting a `wpcd_error_log` post with status `private` and author set to `get_current_user()`.
- Command/SSH logs: `add_command_log_entry` / `add_ssh_log_entry` insert a `wpcd_command_log` or `wpcd_ssh_log` post keyed by `parent_post_id` (the server/app being acted on), convert `WP_Error` results to strings, run `remove_common_strings()`, then scrub passwords via `wpcd_replace_key_value_paired_strings`.

### Read path
- Legacy admin: each log CPT registers its own columns, sortable headers, and meta box template under `includes/templates/*_log.php`.
- SimpleStack dashboard: `SimpleStack_REST_Logs` normalizes a `WP_Query` result into `{ id, type, parent, title, date_gmt, reference?, body, file?, line? }` and returns a `cursor` derived from the newest entry's `date_gmt`. The React `LogDrawer` polls `GET /servers/{id}/logs?type=all&since=<cursor>&limit=50` every 15 seconds and renders entries with tone classes `command` / `ssh` / `error`.

### Security
- Every REST payload goes through the secret redactor because command output may contain passwords. The controller comment states this explicitly.
- Password/key pairs are stripped from both stored and returned data using `wpcd_replace_key_value_paired_strings`.
- Log posts are always created with `post_status => 'private'` and `publicly_queryable => false`.

## Conventions and Constraints

- **One log category = one child of `WPCD_POSTS_LOG`**, registering a single custom post type whose slug follows the `wpcd_<category>_log` pattern (`wpcd_error_log`, `wpcd_command_log`, `wpcd_ssh_log`).
- **Log entries are WordPress posts**, not files or syslog lines. The post title is truncated to 100 characters; structured fields live in post meta.
- **All log posts have status `private`**; auto-trim only considers `private` posts when pruning.
- **Access control**: viewing/editing any log listing requires the `wpcd_manage_logs` capability; bulk actions disable edit on all log lists.
- **Sensitive data redaction**: every write path calls `wpcd_replace_key_value_paired_strings` with terms from `WPCD()->wpcd_get_pw_terms_to_clean()` before persisting; the REST layer re-redacts payloads before returning them.
- **Include/exclude filtering**: error log writes honor `exclude_msg_txt`, `include_msg_txt`, and `include_file_txt` early options; if include criteria are configured, a message must match at least one rule to be written.
- **Auto-trimming**: each log type has a configurable limit (`auto_trim_*_log_limit`); the constant `WPCD_AUTO_TRIM_LOG_LIMIT` takes precedence; default fallback is 999 entries.
- **Parent association**: command and SSH logs link back to their owner via the `parent_post_id` meta field; the REST query supports filtering by that field.
- **REST contract**: log types exposed publicly are limited to `command`, `ssh`, `error` (mapped in `$log_types`); `all` is accepted but expands to the three mapped types. The `since` parameter accepts ISO8601 timestamps and is translated to a `post_date_gmt` `after` date query.
- **No log levels**: there is no severity enum or level-based routing; filtering is purely textual (message/file inclusion) and post-status based.