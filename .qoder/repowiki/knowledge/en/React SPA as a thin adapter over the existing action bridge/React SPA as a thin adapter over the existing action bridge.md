---
kind: design
name: React SPA as a thin adapter over the existing action bridge
source: session
category: adr
---

# React SPA as a thin adapter over the existing action bridge

_Source: coding plans from commit period 4c1f985 → 7c7fe68 — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
The legacy admin UI is built on Meta Box field arrays and `admin-ajax.php` dispatching through `do_instance_action()` / `tab_action`. Re-implementing all 51 tabs would be prohibitively large.

## Decision drivers
- zero business-logic rewrite
- single hard cutover release
- parity with existing tab behavior

## Considered options
- **Reimplement every tab in React against new PHP endpoints** _(rejected)_ — pros: Cleaner separation, no legacy coupling; cons: Massive effort across 51 tabs; high regression risk
- **Keep jQuery/Meta Box UI and gradually migrate tabs** _(rejected)_ — pros: Lower initial cost per tab; cons: Staged migration never finishes; two UIs to maintain
- **Expose GET `/tabs` + POST `/action` that call the existing filters/actions** — pros: No PHP logic changes; React renders controls generically from the same schema; cons: React must understand the Meta Box field-array shape

## Decision
Introduce a REST layer under `simple-stack/v1/` whose only job is to translate the existing `sstk_server_*_get_tabs` / `sstk_app_*_get_tabs` filter outputs into JSON and forward actions to `SSTK_WORDPRESS_APP()->do_instance_action()` and `apply_filters('sstk_app_wordpress-app_tab_action', ...)`. The React `TabRenderer` consumes this schema directly.

## Consequences
All 51 tabs ship without PHP rewrites; the React app is tightly coupled to the Meta Box field-array contract. Any future change to that contract must update both sides simultaneously.