#!/usr/bin/env bash
# Apply the first reversible WordPress hardening settings after a verified backup.
# Usage: bash harden-settings.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-settings}"
BACKUP_ROOT="${HOME}/stthekla-backups"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: wp-config.php not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi

LATEST_BACKUP="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_BACKUP" || ! -f "$LATEST_BACKUP/SHA256SUMS" ]]; then
  echo "ERROR: No verified St. Thekla recovery backup was found." >&2
  exit 1
fi

backup_age=$(( $(date +%s) - $(stat -c %Y "$LATEST_BACKUP/SHA256SUMS") ))
if (( backup_age > 86400 )); then
  echo "ERROR: Latest backup is older than 24 hours: $LATEST_BACKUP" >&2
  exit 1
fi

(
  cd "$LATEST_BACKUP"
  sha256sum -c SHA256SUMS
) > /dev/null

mkdir -p "$OUTPUT_DIR"

capture_settings() {
  local destination="$1"
  wp --path="$WP_PATH" eval '
    $data = array(
      "users_can_register"     => (string) get_option("users_can_register", ""),
      "default_comment_status" => (string) get_option("default_comment_status", ""),
      "default_ping_status"    => (string) get_option("default_ping_status", ""),
      "default_role"           => (string) get_option("default_role", ""),
      "recorded_at_utc"        => gmdate("c"),
    );
    echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  ' > "$destination"
}

capture_settings "$OUTPUT_DIR/settings-before.json"

wp --path="$WP_PATH" option update users_can_register 0
wp --path="$WP_PATH" option update default_comment_status closed
wp --path="$WP_PATH" option update default_ping_status closed

capture_settings "$OUTPUT_DIR/settings-after.json"

if [[ "$(wp --path="$WP_PATH" option get users_can_register)" != "0" ]]; then
  echo "ERROR: users_can_register verification failed." >&2
  exit 1
fi
if [[ "$(wp --path="$WP_PATH" option get default_comment_status)" != "closed" ]]; then
  echo "ERROR: default_comment_status verification failed." >&2
  exit 1
fi
if [[ "$(wp --path="$WP_PATH" option get default_ping_status)" != "closed" ]]; then
  echo "ERROR: default_ping_status verification failed." >&2
  exit 1
fi

{
  printf 'action=settings-hardening\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'users_can_register=0\n'
  printf 'default_comment_status=closed\n'
  printf 'default_ping_status=closed\n'
} > "$OUTPUT_DIR/summary.txt"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Settings hardening complete: %s\n' "$OUTPUT_DIR"
