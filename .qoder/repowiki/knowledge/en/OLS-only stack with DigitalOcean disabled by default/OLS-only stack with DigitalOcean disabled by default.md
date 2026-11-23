---
kind: design
name: OLS-only stack with DigitalOcean disabled by default
source: session
category: adr
---

# OLS-only stack with DigitalOcean disabled by default

_Source: coding plans from commit period 4c1f985 → 7c7fe68 — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
The fork targets an internal SimpleStack product that ships OpenLiteSpeed exclusively; the upstream WP Cloud Deploy supported nginx+ols and DigitalOcean only.

## Decision drivers
- product scope (OpenLiteSpeed only)
- simplify provider surface
- keep DO available for internal testing via opt-in

## Considered options
- **Remove nginx and DigitalOcean entirely** _(rejected)_ — pros: Smallest codebase; cons: Breaks any existing DO deployments or add-ons expecting the slug
- **Register Vultr/UpCloud/Custom Server alongside DO, but disable DO by default** — pros: New providers ship in-repo; DO remains addressable via `enable-do-provider` option; cons: Slightly larger provider registry

## Decision
`get_webserver_list()` returns only `'ols'`; `$providers` registers `vultr-v2`, `upcloud`, `custom-server`, and `digital-ocean` with a single filter that unsets DO unless `wpcd_get_early_option('enable-do-provider')` is set. Hardcoded `'nginx'` fallbacks in `class-wordpress-app.php` are replaced with `'ols'`.

## Consequences
Existing DO users must explicitly enable the provider; nginx-based installs will fall back to OLS. Provider classes live in-repo rather than as add-ons.