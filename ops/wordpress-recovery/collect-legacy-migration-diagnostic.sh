#!/usr/bin/env bash
# Collect non-private diagnostics from the newest failed legacy public content
# migration attempt. This script does not change WordPress or plugin state.
# Usage: bash collect-legacy-migration-diagnostic.sh <output_dir>
set -euo pipefail
umask 077

OUTPUT_DIR="${1:-${HOME}/stthekla-audits/$(date +%Y%m%d-%H%M%S)-legacy-content-diagnostic}"
CHANGE_ROOT="${HOME}/stthekla-change-logs"

LATEST_DIR="$(find "$CHANGE_ROOT" -mindepth 1 -maxdepth 1 -type d -name '*-legacy-public-content' -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_DIR" || ! -d "$LATEST_DIR" ]]; then
  echo "ERROR: No legacy public content migration directory was found." >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"
safe_files=(
  plugin-state-before.csv
  content-migration.json
  content-migration-stderr.txt
  shortcodes-ultimate-deactivation.txt
  pdf-embedder-deactivation.txt
  rewrite-flush-before-quarantine.txt
  cache-flush-before-quarantine.txt
  shortcode-usage-after-content.csv
  public-verification-before-quarantine.json
  quarantine-manifest.csv
  rewrite-flush-after-quarantine.txt
  cache-flush-after-quarantine.txt
  public-verification-final.json
  plugins-after.csv
  donation-http-status.txt
  palm-sunday-http-status.txt
  homepage-http-status.txt
  contact-http-status.txt
  directory-login-http-status.txt
  schedule-api-http-status.txt
  donation-final-http-status.txt
  palm-sunday-final-http-status.txt
  homepage-final-http-status.txt
  contact-final-http-status.txt
  directory-login-final-http-status.txt
  schedule-api-final-http-status.txt
  summary.txt
)

for filename in "${safe_files[@]}"; do
  if [[ -f "$LATEST_DIR/$filename" ]]; then
    cp "$LATEST_DIR/$filename" "$OUTPUT_DIR/$filename"
  fi
done

{
  printf 'source_directory=%s\n' "$LATEST_DIR"
  printf 'source_files:\n'
  find "$LATEST_DIR" -mindepth 1 -maxdepth 1 -type f -printf '%f\n' | sort
  printf 'private_rollback_directory_present=%s\n' "$([[ -d "$LATEST_DIR/private" ]] && printf yes || printf no)"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'wordpress_changes=none\n'
} > "$OUTPUT_DIR/diagnostic-inventory.txt"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Legacy migration diagnostic collected: %s\n' "$OUTPUT_DIR"
