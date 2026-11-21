---
kind: external_dependency
name: OpenLiteSpeed web server stack
slug: openlitespeed
category: external_dependency
category_hints:
    - framework_behavior
    - client_constraint
scope:
    - '**'
source_files:
    - includes/core/apps/wordpress-app/scripts/v2/raw/01-prepare_server.txt
    - includes/core/apps/wordpress-app/scripts/v2/raw/9999-common-functions.txt
    - includes/core/apps/wordpress-app/scripts/v2/raw/56-ols_manage_admin_console.txt
---

### OpenLiteSpeed
- **Role:** The only supported web server on Ubuntu 24.04 (`noble`) and 26.04 (`resolute`); nginx paths and branches were removed from the provisioning scripts.
- **Integration point:** Provisioned via `ols1clk.sh` pinned to tag `v3.1` downloaded from `https://repo.litespeed.sh`, then PHP versions `lsphp83` / `lsphp84` (default) / `lsphp85` installed explicitly from the vendor repo. The OLS main-config `extprocessor lsphp` path is rewritten to `${PHPVER}`.
- **Client constraint:** `ols1clk.sh --lsphp 84` is not implementable — the script validates against a fixed list and silently ignores out-of-list values, so the install runs the baseline with `--lsphp 81` and upgrades the default afterwards via `gf_set_default_php`.
- Verify exact OLS config directives against the current OLS documentation.