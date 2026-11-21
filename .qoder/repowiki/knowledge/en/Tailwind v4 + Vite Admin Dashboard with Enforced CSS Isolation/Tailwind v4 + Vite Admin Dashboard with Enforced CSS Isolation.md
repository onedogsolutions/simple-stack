---
kind: frontend_style
name: Tailwind v4 + Vite Admin Dashboard with Enforced CSS Isolation
category: frontend_style
scope:
    - '**'
source_files:
    - tailwind.config.js
    - src/admin/styles/index.css
    - vite.config.js
    - package.json
    - tools/verify-css-isolation.mjs
    - src/admin/components/AppShell.jsx
    - src/admin/components/Button.jsx
---

## System Overview

The SimpleStack admin dashboard is a React application styled exclusively with **Tailwind CSS v4** and built by **Vite**. It lives under `src/admin/` and is compiled into `assets/dist/`. The plugin also ships legacy jQuery-based assets under `assets/css/` and `assets/js/` for the older WPCD screens; those are separate from the new Tailwind-powered dashboard.

## Build Pipeline

- `vite.config.js` uses `@tailwindcss/vite` as a Vite plugin, bundles React (not WordPress's bundled copy) so the Tailwind pipeline stays reproducible, and outputs one combined stylesheet (`cssCodeSplit: false`) plus hashed JS chunks to `assets/dist/`.
- `package.json` scripts expose `npm run dev`, `npm run build`, and `npm run verify:css` (which runs the isolation guard).
- The entry point is `src/admin/index.jsx`; the CSS entry is `src/admin/styles/index.css`.

## Styling Architecture

### Tailwind Configuration — `tailwind.config.js`

Two settings are the architectural core:

1. `important: '#simplestack-admin'` — every generated utility selector is prefixed with `#simplestack-admin`, so utilities cannot leak into wp-admin chrome.
2. `corePlugins.preflight: false` — Tailwind's global reset (which targets `html`, `body`, `*`) is disabled because it would restyle the entire WordPress admin area.

Design tokens are extended via `theme.extend`:

- Color palette: `brand` (pink), `ink` (dark neutrals), `surface` (base/muted/border), `positive`, `warning`, `negative`.
- Fonts: `sans` = system stack (`-apple-system`, `BlinkMacSystemFont`, `Segoe UI`, `Roboto`); `mono` = `ui-monospace` / Menlo / Consolas.
- Radius token: `card: '0.5rem'` (used as `rounded-card`).

### CSS Entry — `src/admin/styles/index.css`

Tailwind v4 is imported explicitly in two layers rather than via the shorthand `@import "tailwindcss"`:

```css
@import "tailwindcss/theme.css" layer(theme);
@import "tailwindcss/utilities.css" layer(utilities);
```

A hand-written reset scoped to `#simplestack-admin` sets `box-sizing`, resets headings/lists/images/links/buttons/tables, and defines `.ss-visually-hidden` — placed outside `@layer` so it beats wp-admin's unlayered author rules regardless of specificity.

### Component Conventions

Components under `src/admin/components/` use Tailwind utility classes directly (no custom CSS per component):

- `AppShell.jsx` composes layout using `bg-surface-muted`, `text-ink-900`, `font-sans`, `rounded-card`, `text-brand-600`, etc.
- `Button.jsx` centralizes variants (`primary`, `secondary`, `ghost`, `danger`) and sizes (`sm`, `md`) as class-name maps, with explicit focus-ring (`focus-visible:ring-brand-500`) and disabled states.
- Components are intentionally thin; wp-admin owns page title, menu, and admin bar — the shell only organises content inside `#simplestack-admin`.

## Enforced Constraints

`tools/verify-css-isolation.mjs` is a Node script that fails the build on any violation. It is invoked by `npm run verify:css` and asserts five invariants against the emitted CSS:

1. No top-level selectors `html`, `body`, `*`, `:root`, or `::selection` that contain real declarations (token-only custom-property declarations are allowed).
2. Every rule inside `@layer utilities` has a selector containing `#simplestack-admin`.
3. At least one scoped utility selector exists (prevents a silent empty Tailwind run).
4. No unprocessed directive residue (`@tailwind`, `@config`, `@apply`, `@import "tailwindcss"`) remains in the bundle.
5. The Vite manifest at `assets/dist/.vite/manifest.json` exists and lists a stylesheet chunk.

On failure the script prints every offending selector and exits non-zero.

## Legacy Assets

Older WPCD screens still ship raw CSS under `assets/css/` (e.g. `wpcd-admin-settings.css`, `wpcd-app.css`, `wpcd-global.css`, `wpcd-setup-wizard.css`) and jQuery-based JS under `assets/js/`. These are independent of the Tailwind dashboard and are not processed by Vite/Tailwind.

## Key Files

- `tailwind.config.js` — design tokens, scoping, preflight policy.
- `src/admin/styles/index.css` — Tailwind v4 entry, layered imports, scoped reset.
- `vite.config.js` — Vite + Tailwind plugin, output manifest, single CSS bundle.
- `package.json` — dependencies (`tailwindcss ^4`, `@tailwindcss/vite ^4`, `react ^19`, `vite ^7`) and `verify:css` script.
- `tools/verify-css-isolation.mjs` — build-time CSS isolation enforcement.
- `src/admin/components/AppShell.jsx` — app chrome using design tokens.
- `src/admin/components/Button.jsx` — variant/size convention example.