#!/usr/bin/env bash
# Modified for Simple Stack by One Dog Solutions on 2026-11-23.
#
# Enforce the Simple Stack naming contract and the security invariants that are
# easy to regress during large refactors. Legacy identifiers are allowed only in
# attribution, the rename audit/tooling, and historical build-state documentation.

set -euo pipefail

cd "$(dirname "$0")/.."

status=0

fail() {
	printf 'FORBIDDEN: %s\n' "$1"
	status=1
}

missing() {
	printf 'MISSING: %s\n' "$1"
	status=1
}

require() {
	local needle="$1"
	local label="$2"
	if ! grep -rqF --include='*.php' -- "$needle" includes/core simple-stack.php uninstall.php; then
		missing "$needle ($label)"
	fi
}

require 'SSTK_PATH'                              'uppercase path constant'
require 'SSTK_URL'                               'uppercase URL constant'
require 'SSTK_VERSION'                           'uppercase version constant'
require 'SSTK_TEXTDOMAIN'                        'uppercase text-domain constant'
require 'SSTK_ENCRYPTION_KEY'                    'encryption-key configuration constant'
require 'sstk-encrypt-v2:'                       'authenticated encryption envelope'
require 'sstk_permission_assignments'           'permission table'
require 'sstk_every_minute'                     'one-minute cron schedule'
require 'sstk_every_two_minutes'                'two-minute cron schedule'
require 'sstk_every_fifteen_minutes'            'fifteen-minute cron schedule'
require 'sstk_app_server'                        'server CPT slug'
require 'sstk_app'                               'site CPT slug'
require 'sstk_app_group'                         'site taxonomy slug'
require 'sstk_app_server_group'                  'server taxonomy slug'

for class_name in SSTK_CORE SSTK_SERVER SSTK_WORDPRESS_APP SSTK_SETTINGS; do
	if ! grep -rqiE --include='*.php' "class[[:space:]]+${class_name}\b" includes/core; then
		missing "class $class_name"
	fi
done

for required_file in simple-stack.php includes/core/simple-stack.php; do
	[ -f "$required_file" ] || missing "$required_file"
done

if ! grep -qF 'Text Domain: simple-stack' simple-stack.php; then
	missing 'Text Domain: simple-stack'
fi

# First-party PHP symbols use the locked prefixes; product copy uses the spaced name.
if grep -rqnE --include='*.php' "(^|[[:space:]])(abstract[[:space:]]+|final[[:space:]]+)?class[[:space:]]+SimpleStack_|^function[[:space:]]+simplestack_" . \
	--exclude-dir=.git --exclude-dir=vendor --exclude-dir=required_plugins --exclude-dir=node_modules; then
	fail 'first-party PHP symbol outside the SSTK_/sstk_ contract'
fi
if grep -rqnF --include='*.php' --include='*.js' --include='*.jsx' 'SimpleStack' includes assets src simple-stack.php uninstall.php; then
	fail 'user-facing or source copy uses SimpleStack instead of Simple Stack'
fi

# Lowercase configuration constants were removed; option and meta keys remain lowercase.
if grep -rqnE --include='*.php' "defined[[:space:]]*\([[:space:]]*['\"]sstk_|define[[:space:]]*\([[:space:]]*['\"]sstk_" . \
	--exclude-dir=.git --exclude-dir=vendor --exclude-dir=required_plugins --exclude-dir=node_modules; then
	fail 'lowercase sstk_ configuration constant'
fi

# The complete upstream naming surface must be gone outside attribution and audit tooling.
legacy_matches="$({
	grep -rInEi --binary-files=without-match \
		--exclude-dir=.git --exclude-dir=vendor --exclude-dir=required_plugins \
		--exclude-dir=node_modules --exclude-dir=dist \
		--exclude=NOTICE.md --exclude=license.txt --exclude=README.md \
		--exclude=rename-inventory.md --exclude=rebrand.php --exclude=rebrand-map.json \
		--exclude=guard-sstk-prefix.sh --exclude=state.md --exclude=repowiki-metadata.json \
		'wpcd|wpclouddeploy|wp-cloud-deploy|wp_cloud_deploy' . || true
	grep -InEi 'wpcd|wpclouddeploy|wp-cloud-deploy|wp_cloud_deploy' required_plugins/sstk-required-plugins.php || true
} )"
if [ -n "$legacy_matches" ]; then
	fail 'legacy upstream identifier outside the attribution/tooling allowlist'
	printf '%s\n' "$legacy_matches"
fi

# Retired licensing, telemetry and commercial modules must not be reintroduced.
retired_matches="$({
	grep -rInEi --binary-files=without-match \
		--include='*.php' --include='*.js' --include='*.jsx' --include='*.css' --include='*.html' --include='*.txt' \
		--exclude-dir=.git --exclude-dir=vendor --exclude-dir=required_plugins \
		--exclude-dir=node_modules --exclude-dir=dist \
		'SSTK_License|wisdom_|SSTK_WOOCOMMERCE|SSTK_DATA_SYNC_REST|LOGTIVITY|sstk-vpn|spinupvpn|basic-server|stable-diffusion' \
		includes assets src simple-stack.php uninstall.php || true
} )"
if [ -n "$retired_matches" ]; then
	fail 'retired licensing, telemetry or commercial-module reference'
	printf '%s\n' "$retired_matches"
fi

# Retired crypto and unprefixed data identifiers must not be reintroduced.
if grep -rqF --include='*.php' -- 'aaaabbbbcccccddddeweee' includes simple-stack.php uninstall.php; then
	fail 'fixed-IV encryption path'
fi
if grep -rqnE --include='*.php' 'function[[:space:]]+(encrypt_v1|decrypt_v1|decrypt_v2)|const[[:space:]]+ENCRYPTION_KEY' includes simple-stack.php uninstall.php; then
	fail 'retired encryption implementation or hardcoded key'
fi
if grep -rqnE --include='*.php' "\$wpdb->prefix[[:space:]]*\.[[:space:]]*['\"]permission_assignments['\"]" includes; then
	fail 'permission table outside the sstk_ namespace'
fi
if grep -rqnE --include='*.php' "['\"](every_minute|every_two_minute|every_fifteen_minute)['\"]" includes simple-stack.php; then
	fail 'custom cron schedule outside the sstk_ namespace'
fi

# The Simple Stack REST layer must be admin-only.
if [ -d includes/core/api ]; then
	if grep -rqF --include='*.php' -- '__return_true' includes/core/api; then
		fail '__return_true in includes/core/api (unauthenticated permission callback)'
	fi
fi

# Exactly two public endpoints exist outside that layer. The provisioning callback
# authenticates with a pending-command transient; the GitHub webhook uses an HMAC.
allowed_public_routes='includes/core/apps/class-sstk-app.php
includes/core/apps/wordpress-app/tabs/git-control-site.php'

while IFS= read -r public_file; do
	[ -n "$public_file" ] || continue
	if ! printf '%s\n' "$allowed_public_routes" | grep -qxF -- "$public_file"; then
		fail "__return_true in $public_file (unexpected unauthenticated permission callback)"
	fi
done < <(grep -rlF --include='*.php' -- '__return_true' includes 2>/dev/null || true)

while IFS= read -r public_file; do
	[ -n "$public_file" ] || continue
	if ! grep -qF --include='*.php' -- '__return_true' "$public_file"; then
		missing "__return_true in $public_file (required public endpoint was removed or gated)"
	fi
done <<< "$allowed_public_routes"

# Port 7080 must never be opened to the world.
while IFS= read -r open_7080; do
	[ -n "$open_7080" ] || continue
	fail "$open_7080 (port 7080 opened to the public)"
done < <(grep -rnF --include='*.txt' -- 'ufw allow 7080' includes 2>/dev/null \
	| grep -vE ':[0-9]+:[[:space:]]*#' || true)

if [ "$status" -eq 0 ]; then
	echo 'Simple Stack naming and security guard: OK'
fi

exit "$status"
