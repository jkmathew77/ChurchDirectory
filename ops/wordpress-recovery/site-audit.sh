#!/usr/bin/env bash
# Capture a read-only WordPress recovery inventory.
# Usage: bash site-audit.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-audits/$(date +%Y%m%d-%H%M%S)}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: wp-config.php not found at $WP_PATH" >&2
  exit 1
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required but was not found in PATH." >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"

wp --path="$WP_PATH" core version > "$OUTPUT_DIR/core-version.txt"
wp --path="$WP_PATH" core verify-checksums > "$OUTPUT_DIR/core-checksums.txt" 2>&1 || true
wp --path="$WP_PATH" plugin list --fields=name,status,update,version,update_version,auto_update --format=csv > "$OUTPUT_DIR/plugins.csv"
wp --path="$WP_PATH" theme list --fields=name,status,update,version,update_version,auto_update --format=csv > "$OUTPUT_DIR/themes.csv"
wp --path="$WP_PATH" user list --format=count > "$OUTPUT_DIR/user-count.txt"
wp --path="$WP_PATH" role list --format=csv > "$OUTPUT_DIR/roles.csv"
wp --path="$WP_PATH" cron event list --fields=hook,next_run_gmt,next_run_relative,recurrence --format=csv > "$OUTPUT_DIR/cron-events.csv"
wp --path="$WP_PATH" option get users_can_register > "$OUTPUT_DIR/public-registration.txt"
wp --path="$WP_PATH" option get default_comment_status > "$OUTPUT_DIR/default-comment-status.txt"
wp --path="$WP_PATH" option get permalink_structure > "$OUTPUT_DIR/permalink-structure.txt"
wp --path="$WP_PATH" db size --tables --format=csv > "$OUTPUT_DIR/database-table-sizes.csv"

wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/user-audit.php" > "$OUTPUT_DIR/users-directory-reconciliation.csv"
wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/shortcode-audit.php" > "$OUTPUT_DIR/shortcode-usage.csv"
wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/directory-health.php" > "$OUTPUT_DIR/community-directory-health.json"

{
  plugin_dir="$WP_PATH/wp-content/plugins/community-directory"
  printf 'configured_path=%s\n' "$plugin_dir"
  if [[ -e "$plugin_dir" || -L "$plugin_dir" ]]; then
    ls -ld "$plugin_dir"
    printf 'resolved_path=%s\n' "$(readlink -f "$plugin_dir" || true)"
  else
    printf 'status=missing\n'
  fi
} > "$OUTPUT_DIR/community-directory-filesystem.txt"

{
  printf 'bytes,plugin_directory\n'
  find "$WP_PATH/wp-content/plugins" -mindepth 1 -maxdepth 1 -type d -print0 \
    | sort -z \
    | while IFS= read -r -d '' plugin_dir; do
        bytes="$(du -sb "$plugin_dir" | awk '{print $1}')"
        printf '%s,"%s"\n' "$bytes" "$(basename "$plugin_dir" | sed 's/"/""/g')"
      done
} > "$OUTPUT_DIR/plugin-directory-sizes.csv"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Audit complete: %s\n' "$OUTPUT_DIR"
