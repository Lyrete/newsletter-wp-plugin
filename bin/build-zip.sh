#!/usr/bin/env bash
#
# Build an installable WordPress plugin zip.
#
# Usage:
#   bin/build-zip.sh [output.zip]
#
# The archive contains a top-level newsletter-wp-plugin/ directory.

set -euo pipefail

SLUG="newsletter-wp-plugin"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/dist/$SLUG.zip}"

case "$OUT" in
	/*) ;;
	*) OUT="$PWD/$OUT" ;;
esac

for command in rsync zip; do
	if ! command -v "$command" >/dev/null 2>&1; then
		echo "Error: $command is required to build the plugin zip." >&2
		exit 1
	fi
done

BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

mkdir -p "$BUILD/$SLUG" "$(dirname "$OUT")"

rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$BUILD/$SLUG/"

rm -f "$OUT"
(
	cd "$BUILD"
	zip -rq "$OUT" "$SLUG"
)

echo "Built $OUT"
