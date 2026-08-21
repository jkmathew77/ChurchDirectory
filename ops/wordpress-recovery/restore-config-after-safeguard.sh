#!/usr/bin/env bash
# Restore wp-config.php from the most recent guarded config-repair archive.
# Usage: bash restore-config-after-safeguard.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-config-restore}"
CONFIG="$WP_PATH/wp-config.php"
CHANGE_ROOT="${HOME}/stthekla-change-logs"

if [[ ! -f "$CONFIG" ]]; then
  echo "ERROR: wp-config.php not found at $CONFIG" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi

ARCHIVED_CONFIG="$(find "$CHANGE_ROOT" -path '*/archive/wp-config.php.before' -type f -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {$1=""; sub(/^ /, ""); print}')"
if [[ -z "$ARCHIVED_CONFIG" || ! -f "$ARCHIVED_CONFIG" ]]; then
  echo "ERROR: No guarded wp-config archive was found." >&2
  exit 1
fi

archive_age=$(( $(date +%s) - $(stat -c %Y "$ARCHIVED_CONFIG") ))
if (( archive_age > 7200 )); then
  echo "ERROR: Latest guarded wp-config archive is older than two hours: $ARCHIVED_CONFIG" >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR/archive"
php -l "$ARCHIVED_CONFIG" > "$OUTPUT_DIR/archived-config-lint.txt" 2>&1
cp -p "$CONFIG" "$OUTPUT_DIR/archive/wp-config.php.failed-attempt"
cp -p "$ARCHIVED_CONFIG" "$CONFIG.recovery-restore"
chmod --reference="$CONFIG" "$CONFIG.recovery-restore"
chown --reference="$CONFIG" "$CONFIG.recovery-restore" 2>/dev/null || true
php -l "$CONFIG.recovery-restore" > "$OUTPUT_DIR/restored-config-lint.txt" 2>&1
mv "$CONFIG.recovery-restore" "$CONFIG"

wp --path="$WP_PATH" core version > "$OUTPUT_DIR/wordpress-after-restore.txt"
wp --path="$WP_PATH" eval '
$expected = array(
    "WP_CRON_LOCK_TIMEOUT" => 120,
    "AUTOSAVE_INTERVAL" => 300,
    "WP_POST_REVISIONS" => 5,
    "EMPTY_TRASH_DAYS" => 7,
);
$result = array();
foreach ( $expected as $name => $expected_value ) {
    $actual = defined($name) ? constant($name) : null;
    $result[$name] = array("expected" => $expected_value, "actual" => $actual, "matches" => ($actual === $expected_value));
    if ( $actual !== $expected_value ) {
        fwrite(STDERR, $name . " was not restored to the expected value.\n");
        exit(1);
    }
}
echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
' > "$OUTPUT_DIR/runtime-after-restore.json"

sha256sum "$ARCHIVED_CONFIG" "$OUTPUT_DIR/archive/wp-config.php.failed-attempt" > "$OUTPUT_DIR/config-SHA256SUMS.txt"

{
  printf 'action=restore-config-after-safeguard\n'
  printf 'source_archive=%s\n' "$ARCHIVED_CONFIG"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'custom_constant_values_restored=yes\n'
} > "$OUTPUT_DIR/summary.txt"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'wp-config restore complete: %s\n' "$OUTPUT_DIR"
