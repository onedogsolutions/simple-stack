---
kind: design
name: Vite + Tailwind v4 SPA scoped to `#simplestack-admin`
source: session
category: adr
---

# Vite + Tailwind v4 SPA scoped to `#simplestack-admin`

_Source: coding plans from commit period 4c1f985 → 7c7fe68 — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
The UI must live inside `wp-admin` as a native page, not an external control panel. Tailwind's global resets (`preflight`) and unscoped utilities would bleed into wp-admin.

## Decision drivers
- native wp-admin integration
- Tailwind v4 toolchain (`@tailwindcss/vite`)
- no style leakage into wp-admin

## Considered options
- **@wordpress/scripts + @tailwindcss/postcss (like sister plugin)** _(rejected)_ — pros: Consistent build tooling across plugins; cons: Does not support Tailwind v4; requires PostCSS instead of Vite
- **Vite + Tailwind v4 with `important: '#simplestack-admin'` and preflight disabled** — pros: Self-contained bundle, reproducible Tailwind build, CSS isolated to the mount point; cons: Bundles React instead of sharing `@wordpress/element`

## Decision
Build output goes to `assets/dist/` via Vite with `@tailwindcss/vite`; Tailwind config sets `important: '#simplestack-admin'` and disables Preflight. A `verify-css-isolation.mjs` script asserts at build time that no top-level `html`/`body`/`*` selectors leak and that every utility selector contains `#simplestack-admin`. The app mounts into `document.getElementById('simplestack-admin')` and receives `restUrl`, nonce, version, userId via inline script.

## Consequences
The build artifact is committed (`assets/dist/** linguist-generated=true`). React is bundled rather than shared with WordPress core, increasing payload size but keeping the Tailwind pipeline self-contained.