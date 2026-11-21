#!/usr/bin/env bash
#
# Syntax-check the provisioning scripts. They ship as .txt but are Bash.
# shellcheck runs in advisory mode: its findings are printed but do not fail
# the gate, because the inherited v1 scripts carry a large warning backlog.
#
set -euo pipefail

cd "$(dirname "$0")/.."

# These ship alongside the scripts but are not shell: they are .htaccess / OLS
# snippets, plain documentation and sample readmes, so `bash -n` rejects them.
NON_SHELL_PATTERN='6G-Firewall-OLS\.txt|7G-Firewall-OLS\.txt|environment variables\.txt|readme\.txt|_description\.txt'

list_scripts() {
	find includes -path '*/scripts/*/raw/*.txt' -type f -print \
		| grep -Ev "$NON_SHELL_PATTERN"
}

# The command wrappers that sit next to raw/ are PHP templates: they carry
# ##PLACEHOLDER## tokens that only become shell after substitution. `bash -n` on the
# template fails on the tokens and on nothing else, so the tokens are replaced with a
# plain word and the result is checked instead. Without this pass a broken && chain in
# a wrapper - which silently skips the rest of a provisioning command - goes unnoticed.
list_wrappers() {
	find includes -path '*/scripts/*/*.txt' -type f -not -path '*/raw/*' -print \
		| grep -Ev "$NON_SHELL_PATTERN"
}

status=0
count=0

scratch="$(mktemp)"
trap 'rm -f "$scratch"' EXIT

# The gate's own tools are shell too. A syntax error in one of them is not caught by
# anything else - it surfaces as the tool failing at the moment it is needed, which is
# exactly when a red result is hardest to interpret.
while IFS= read -r file; do
	[ -z "$file" ] && continue
	count=$(( count + 1 ))
	if ! output=$(bash -n "$file" 2>&1); then
		printf 'FAIL %s (gate tool)\n%s\n' "$file" "$output"
		status=1
	fi
done < <(find bin -maxdepth 1 -name '*.sh' -type f -print)

while IFS= read -r file; do
	[ -z "$file" ] && continue
	count=$(( count + 1 ))
	if ! output=$(bash -n "$file" 2>&1); then
		printf 'FAIL %s\n%s\n' "$file" "$output"
		status=1
	fi
done < <(list_scripts)

while IFS= read -r file; do
	[ -z "$file" ] && continue
	count=$(( count + 1 ))
	sed -E 's/##[A-Za-z_0-9]+##/PLACEHOLDER/g' "$file" > "$scratch"
	if ! output=$(bash -n "$scratch" 2>&1); then
		printf 'FAIL %s (after ##PLACEHOLDER## substitution)\n%s\n' "$file" "$output"
		status=1
	fi
done < <(list_wrappers)


if [ "$status" -eq 0 ]; then
	printf 'bash -n: %s scripts OK\n' "$count"
fi

if command -v shellcheck >/dev/null 2>&1; then
	echo "shellcheck (advisory):"
	while IFS= read -r file; do
		[ -z "$file" ] && continue
		shellcheck -s bash -e SC1090,SC1091 -S warning "$file" || true
	done < <(list_scripts)
else
	echo "shellcheck: not installed, skipping advisory pass"
fi

exit "$status"
