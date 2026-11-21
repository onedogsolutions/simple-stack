---
kind: external_dependency
name: Vultr cloud provider (API v2)
slug: vultr
category: external_dependency
category_hints:
    - vendor_identity
    - client_constraint
scope:
    - '**'
source_files:
    - includes/core/providers/class-vultr-v2.php
---

### Vultr
- **Role:** Primary cloud provider for provisioning servers and managing their lifecycle; default provider in the registry.
- **Integration point:** `CLOUD_PROVIDER_API_VultrV2` in `includes/core/providers/class-vultr-v2.php`, registered under slug `vultr-v2`; authenticated with an API key sent as `Authorization: Bearer {api_key}` against `https://api.vultr.com/v2/`.
- **Client constraints:** Base64-encoded `user_data` for cloud-init; plans are keyed by numeric id; snapshots, resize, SSH-key creation, test_connection, backups and backup-on-create are all advertised as supported features.
- Verify exact request/response shapes against the official Vultr API docs.