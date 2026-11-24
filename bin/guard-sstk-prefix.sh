#!/usr/bin/env bash
#
# Guard against accidental global renames of the inherited wpcd_ identifiers.
# These must survive the SimpleStack rebrand: class names, custom post type
# slugs, post meta keys and Bash environment variables all depend on them.
#
set -euo pipefail

cd "$(dirname "$0")/.."

status=0

require() {
	local needle="$1"
	local label="$2"
	if ! grep -rqF --include='*.php' -- "$needle" includes/core; then
		printf 'MISSING: %s (%s)\n' "$needle" "$label"
		status=1
	fi
}

require 'wpcd_app_server' 'server CPT slug'
require 'wpcd_app'        'app CPT slug'
require 'wpcd_path'       'path constant'
require 'wpcd_url'        'url constant'

# At least a few of the core classes must remain. PHP class names are case-insensitive,
# and the inherited tree declares some of them (WPCD_Server, WPCD_Settings) in title case.
for class_name in WPCD_SERVER WPCD_WORDPRESS_APP WPCD_SETTINGS; do
	if ! grep -rqiE --include='*.php' "class[[:space:]]+${class_name}\b" includes/core; then
		printf 'MISSING: class %s\n' "$class_name"
		status=1
	fi
done

# Things that must NOT be reintroduced.
forbidden() {
	local needle="$1"
	local label="$2"
	if grep -rqF --include='*.php' -- "$needle" includes/core; then
		printf 'FORBIDDEN: %s (%s)\n' "$needle" "$label"
		status=1
	fi
}

forbidden 'WPCD_License'  'removed licensing layer'
forbidden 'wisdom_'       'removed telemetry layer'
# Deleted in Phase 1. Instantiating any of these from an `init` hook is a fatal error,
# and nothing in SimpleStack supplies them.
forbidden 'WPCD_WOOCOMMERCE'    'removed WooCommerce layer'
forbidden 'WPCD_DATA_SYNC_REST' 'removed data-sync layer'

# The new SimpleStack REST layer (`includes/core/api/`) must be admin-only.
if [ -d includes/core/api ]; then
	if grep -rqF --include='*.php' -- '__return_true' includes/core/api; then
		printf 'FORBIDDEN: %s\n' '__return_true in includes/core/api (unauthenticated permission callback)'
		status=1
	fi
fi

# Exactly two public endpoints exist outside that layer and they must stay. The
# provisioning command callback is called by a bare `wget` from a shell script and the
# GitHub webhook by GitHub itself; neither caller has WordPress credentials, so neither
# can be gated on manage_options. They authenticate by other means - a pending-command
# transient and an HMAC signature. Assert the set is exactly these two files: a third
# one anywhere in the tree is a gate failure, not something to catch in review.
allowed_public_routes='includes/core/apps/class-wpcd-app.php
includes/core/apps/wordpress-app/tabs/git-control-site.php'

while IFS= read -r public_file; do
	[ -n "$public_file" ] || continue
	if ! printf '%s\n' "$allowed_public_routes" | grep -qxF -- "$public_file"; then
		printf 'FORBIDDEN: %s (%s)\n' "__return_true in $public_file" 'unexpected unauthenticated permission callback'
		status=1
	fi
done < <(grep -rlF --include='*.php' -- '__return_true' includes 2>/dev/null || true)

while IFS= read -r public_file; do
	[ -n "$public_file" ] || continue
	if ! grep -qF --include='*.php' -- '__return_true' "$public_file"; then
		printf 'MISSING: %s (%s)\n' "__return_true in $public_file" 'required public endpoint was removed or gated, which breaks provisioning'
		status=1
	fi
done <<< "$allowed_public_routes"

# Port 7080 must never be opened to the world. `scripts/v1/` is frozen on purpose so
# servers provisioned before the v2 modernization keep the scripts they were built with,
# so it is exempt; an active `ufw allow 7080` anywhere else - a v2 regression - fails here.
# Commented-out lines are inert and therefore not flagged.
while IFS= read -r open_7080; do
	[ -n "$open_7080" ] || continue
	printf 'FORBIDDEN: %s (%s)\n' "$open_7080" 'port 7080 opened outside the frozen v1 scripts'
	status=1
done < <(grep -rnF --include='*.txt' -- 'ufw allow 7080' includes 2>/dev/null \
	| grep -v '/scripts/v1/' \
	| grep -vE ':[0-9]+:[[:space:]]*#' || true)

if [ "$status" -eq 0 ]; then
	echo 'wpcd_ prefix guard: OK'
fi

exit "$status"
