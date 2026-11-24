---
kind: design
name: REST responses redacted through `SSTK_Secret_Redactor`
source: session
category: adr
---

# REST responses redacted through `SSTK_Secret_Redactor`

_Source: coding plans from commit period 4c1f985 → 7c7fe68 — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
Provider credentials, SSH keys, and private tokens flow through the same data paths exposed by the new REST API. The old `class-sstk-data-sync-rest.php` endpoint used `__return_true` and was unsafe.

## Decision drivers
- zero secret leakage
- defense-in-depth beyond provider-side `credentials_available()` booleans

## Considered options
- **Rely on providers returning booleans only** _(rejected)_ — pros: Minimal PHP change; cons: Any future response path could still leak secrets
- **Recursive redactor keyed on patterns like `pass|secret|token|private|_key$|api_key` plus PEM headers** — pros: Catches accidental leaks regardless of which controller produces the response; cons: May redact legitimate-looking values if naming conventions drift

## Decision
Every REST response passes through `SSTK_Secret_Redactor`, which recursively replaces values whose keys match `/pass|secret|token|private|_key$|api_?key/i` with `'••••••••'` and scrubs `-----BEGIN [A-Z ]*PRIVATE KEY-----` blocks.

## Consequences
Debugging credential issues requires enabling verbose logging server-side; the redactor is a safety net, not a substitute for providers returning booleans.