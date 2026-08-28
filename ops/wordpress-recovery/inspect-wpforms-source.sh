#!/usr/bin/env bash
# Package the installed WPForms Lite source for offline compatibility review.
# The plugin is GPL-distributed code; no WordPress content or options are copied.
# Usage: bash inspect-wpforms-source.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-audits/$(date +%Y%m%d-%H%M%S)-wpforms-source}"
PLUGIN_ROOT="$WP_PATH/wp-content/plugins"
PLUGIN_DIR="$PLUGIN_ROOT/wpforms-lite"

if [[ ! -d "$PLUGIN_DIR" ]]; then
  echo "ERROR: WPForms Lite directory was not found at $PLUGIN_DIR" >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"

tar -czf "$OUTPUT_DIR/wpforms-lite-source.tar.gz" -C "$PLUGIN_ROOT" wpforms-lite
find "$PLUGIN_DIR" -type f -printf '%P\n' | sort > "$OUTPUT_DIR/wpforms-lite-files.txt"
sha256sum "$OUTPUT_DIR/wpforms-lite-source.tar.gz" > "$OUTPUT_DIR/SHA256SUMS.txt"

if command -v wp >/dev/null 2>&1; then
  wp --path="$WP_PATH" plugin get wpforms-lite --fields=name,status,version --format=json \
    > "$OUTPUT_DIR/wpforms-plugin-state.json"
fi

chmod -R go-rwx "$OUTPUT_DIR"
printf 'WPForms source audit complete: %s\n' "$OUTPUT_DIR"
