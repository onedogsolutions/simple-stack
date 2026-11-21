#!/usr/bin/env bash
#
# Syntax-check every PHP file in the plugin.
# Skips dependencies and generated build output.
#
set -euo pipefail

cd "$(dirname "$0")/.."

status=0
count=0

while IFS= read -r file; do
	[ -z "$file" ] && continue
	count=$(( count + 1 ))
	if ! output=$(php -l "$file" 2>&1); then
		printf '%s\n' "$output"
		status=1
	fi
done < <(
	find . \
		-path ./node_modules -prune -o \
		-path ./vendor -prune -o \
		-path ./assets/dist -prune -o \
		-name '*.php' -type f -print
)

if [ "$status" -eq 0 ]; then
	printf 'php -l: %s files OK\n' "$count"
fi

exit "$status"
