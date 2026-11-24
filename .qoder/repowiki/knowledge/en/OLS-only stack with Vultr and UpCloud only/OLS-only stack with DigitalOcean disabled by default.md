---
kind: design
name: OLS-only stack with Vultr and UpCloud only
source: session
category: adr
---

# OLS-only stack with Vultr and UpCloud only

_Source: coding plans from commit period 4c1f985 → 7c7fe68 — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
The fork targets an internal Simple Stack product that ships OpenLiteSpeed exclusively. The upstream product supported multiple web stacks and providers, but this fork has no production installs or legacy data to preserve.

## Decision drivers
- product scope (OpenLiteSpeed only)
- simplify provider surface
- remove retired providers completely rather than retain dormant compatibility code

## Considered options
- **Remove nginx, DigitalOcean and custom-server providers entirely** _(accepted)_ — pros: smallest supported surface and no compatibility layer; cons: none for this fork because upstream was never used in production.
- **Retain DigitalOcean or custom servers behind opt-in flags** _(rejected)_ — leaves retired code, identifiers and untested execution paths in the release.

## Decision
`get_webserver_list()` returns only `'ols'`, and `$providers` registers only `vultr-v2` and `upcloud`. The DigitalOcean, custom-server, nginx and LiteSpeed Enterprise paths are removed. Provisioning is fixed to `scripts/v2/` on Ubuntu 24.04/26.04 with `lsphp83`, `lsphp84` and `lsphp85`.

## Consequences
There is no migration or fallback for DigitalOcean, custom servers or nginx installs. The smaller provider and stack surface is intentional, and all first-party identifiers use the Simple Stack naming contract.