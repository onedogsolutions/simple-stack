---
kind: external_dependency
name: UpCloud cloud provider (API 1.3)
slug: upcloud
category: external_dependency
category_hints:
    - vendor_identity
    - auth_protocol
    - client_constraint
scope:
    - '**'
source_files:
    - includes/core/providers/class-upcloud.php
---

### UpCloud
- **Role:** Secondary cloud provider for provisioning servers and lifecycle management; registered under slug `upcloud`.
- **Integration point:** `CLOUD_PROVIDER_API_Upcloud` in `includes/core/providers/class-upcloud.php`; authenticates via HTTP Basic using `user_name` / `user_password` against `https://api.upcloud.com/1.3/`.
- **Durable usage model:** Plans have no numeric id — they are keyed by stable `name` (e.g. `2xCPU-4GB`). Create uses a template clone (`action: "clone"`, `storage: <uuid>`) resolved from `GET /storage/template` with exact-version then family fallback. SSH keys are installed inline from `login_user.ssh_keys.ssh_key[]` rather than via a separate create call.
- **Client constraint:** Snapshots/backups are declared unsupported — no snapshot endpoints exist in this implementation, and feature flags hide those UI controls.
- Verify exact endpoint payloads against the official UpCloud 1.3 API docs.