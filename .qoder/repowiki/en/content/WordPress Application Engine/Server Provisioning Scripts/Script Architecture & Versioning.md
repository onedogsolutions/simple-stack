# Script Architecture and Versioning

<cite>
**Referenced Files in This Document**
- [Active script readme](file://includes/core/apps/wordpress-app/scripts/v2/readme.txt)
- [Server preparation](file://includes/core/apps/wordpress-app/scripts/v2/raw/01-prepare_server.txt)
- [Common functions](file://includes/core/apps/wordpress-app/scripts/v2/raw/9999-common-functions.txt)
- [Site installation](file://includes/core/apps/wordpress-app/scripts/v2/raw/02-install_wordpress_site.txt)
- [Post-install hook](file://includes/core/apps/wordpress-app/scripts/v2/raw/66-sstk_post_install.txt)
- [Script handlers](file://includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/script-handlers.php)
</cite>

## Fixed runtime version

Simple Stack provisions and manages servers only through
`includes/core/apps/wordpress-app/scripts/v2/`. `get_configured_scripts_version()` returns `v2`.
There is no settings dropdown, per-server version metadata, directory selector or v1 fallback, and
the working tree contains no v1 script files.

The supported stack is OpenLiteSpeed on Ubuntu 24.04/26.04 with `lsphp83`, `lsphp84` (default) and
`lsphp85`. Nginx, PHP-FPM and LiteSpeed Enterprise are not supported.

## Directory contract

| Location | Form | Execution |
|---|---|---|
| `scripts/v2/raw/*.txt` | plain Bash | downloaded verbatim and run on the server |
| `scripts/v2/*.txt` outside `raw/` | PHP command template | `##TOKEN##` placeholders substituted, then sent over SSH |
| `scripts/v2/readme.txt` | operator/developer notes | not executed |

Raw scripts do not source sibling files automatically. Wrappers explicitly download every raw
script they need. The common library arrives as `9999-common-functions.sh` even though its source
file is `raw/9999-common-functions.txt`.

## Server preparation pipeline

`01-prepare_server.txt` performs the following contract:

1. Require root and export `DEBIAN_FRONTEND=noninteractive`.
2. Source `9999-common-functions.sh`.
3. Normalize `SSTK_ADMIN_IP`; invalid values become empty and fail closed.
4. Accept only `webserver_type=ols`.
5. Resolve Ubuntu 24.04 to `noble` and 26.04 to `resolute`; reject other releases.
6. Create `.sstk-server-provision-checkpoints`.
7. Add required repositories and download `ols1clk.sh` tag `v3.1` over verified TLS.
8. Syntax-check the installer and compare `SSTK_OLS1CLK_SHA256` when configured.
9. Install explicit `lsphp83/84/85` packages and make 8.4 the default.
10. Configure MariaDB, OLS, firewall and services.
11. Deny port 7080 before applying any validated admin-IP allow rule.

The Vultr cloud-init workaround using `Dpkg::Options::=--force-confnew` is intentionally retained.

## Common functions

`9999-common-functions.txt` centralizes:

- Ubuntu and PHP matrix constants;
- `gf_set_default_php()` and `gf_list_installed_php()`;
- `gf_normalise_admin_ip()`;
- OLS restart, vhost and configuration helpers;
- database creation and site helpers;
- HTTPS/certbot integration; and
- hardened vhost deny/rewrite rules through `gf_harden_ols_conf()`.

Scripts check that the library exists and source it before using shared helpers.

## Checkpoints

Long phases write marker files such as `checkpoint05-end.txt`, `checkpoint10-end.txt`,
`checkpoint20-end.txt`, `checkpoint100-end.txt` and `checkpoint150-end.txt`. A rerun skips completed
blocks and resumes at the first incomplete phase. Checkpoints are per server and must be removed
only when an operator intentionally wants a full reprovision.

## Environment and placeholders

Wrappers receive values through `script_placeholders()` and exported environment variables.
Security-sensitive values are validated before shell interpolation. A raw script cannot safely
contain `##TOKEN##` placeholders because an unsubstituted token at the start of a line is a Bash
comment.

`bin/lint-sh.sh` syntax-checks raw scripts directly and wrapper templates after placeholder
substitution.

## Site installation and post-install hook

`02-install_wordpress_site.txt` creates the site, installs WordPress and configures the OLS vhost.
It invokes `66-sstk_post_install.txt` after operator post-processing. The hook can set
`DISALLOW_FILE_EDIT` and install a filtered list of security plugins; the default plugin list is
empty.

The optional hook download sits outside the required-file `&&` chain, and a no-op fallback exists
in the common library. A missing optional hook must not turn a completed site install into a
failure.

## Adding a provisioning phase

1. Add a raw Bash script under `scripts/v2/raw/` with a deterministic numeric prefix.
2. Add or update the wrapper that downloads and invokes it.
3. Put reusable logic in `9999-common-functions.txt`.
4. Add checkpoint guards for expensive or non-idempotent phases.
5. Validate every placeholder that reaches a shell command.
6. Run `bash bin/lint-sh.sh` and the relevant focused harnesses.
7. Keep the implementation OLS-only and within the supported Ubuntu/PHP matrix.
