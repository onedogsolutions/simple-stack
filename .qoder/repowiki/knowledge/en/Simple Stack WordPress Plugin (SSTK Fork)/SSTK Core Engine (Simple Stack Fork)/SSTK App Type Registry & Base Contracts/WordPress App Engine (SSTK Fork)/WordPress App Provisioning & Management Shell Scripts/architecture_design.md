Simple Stack ships one fixed provisioning tree at `scripts/v2/`:
- Top-level `*.txt` action wrappers download assets, export `##PLACEHOLDER##` values and execute the matching raw script over SSH.
- `raw/*.txt` contains the numbered implementation fragments.
- `raw/9999-common-functions.txt` provides shared `gf_*` helpers, `g_*` module helpers and global runtime values such as `$GV_UBUNTU_VERSION`, `$WWW_PATH`, `$LSDIR`, `$WEBCF`, `$VHDIR` and `$PHPVER`.
- `raw/wp-backup2-helper` and `raw/wp-sync` are companion executables used by backup and site-sync actions.
- `multi-tenant-pre-post-samples/*.sh` contains optional operator hook examples.
- `raw/environment variables.txt` documents each action and its required variables.

Dependency direction is one-way: wrappers and raw action scripts source the common function library and call its helpers. There is no alternate script-version tree or per-server version selector.
