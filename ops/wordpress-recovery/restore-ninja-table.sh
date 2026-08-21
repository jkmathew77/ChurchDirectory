#!/usr/bin/env bash
# Restore the existing Holy Liturgy schedule by activating Ninja Tables and
# verifying that table 142 replaces the raw shortcode on the public homepage.
# Usage: bash restore-ninja-table.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-ninja-table}"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN="ninja-tables"
TABLE_ID="142"
PUBLIC_URL="https://www.sttheklachurch.org/"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi
if ! wp --path="$WP_PATH" plugin is-installed "$PLUGIN" >/dev/null 2>&1; then
  echo "ERROR: Ninja Tables is not installed." >&2
  exit 1
fi

LATEST_BACKUP="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_BACKUP" || ! -f "$LATEST_BACKUP/SHA256SUMS" ]]; then
  echo "ERROR: No verified recovery backup was found." >&2
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
) >/dev/null

mkdir -p "$OUTPUT_DIR"

# Confirm the table and its six saved rows still exist before activation.
ST_NINJA_TABLE_ID="$TABLE_ID" wp --path="$WP_PATH" eval '
  global $wpdb;
  $table_id = (int) getenv("ST_NINJA_TABLE_ID");
  $post = get_post($table_id);
  $storage = $wpdb->prefix . "ninja_table_items";
  $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $storage)) === $storage;
  $rows = $exists ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$storage}` WHERE table_id = %d", $table_id)) : 0;
  echo wp_json_encode(array(
    "post_exists" => $post instanceof WP_Post,
    "post_type" => $post instanceof WP_Post ? $post->post_type : null,
    "post_status" => $post instanceof WP_Post ? $post->post_status : null,
    "row_count" => $rows,
  ), JSON_PRETTY_PRINT) . PHP_EOL;
  if (!$post instanceof WP_Post || "ninja-table" !== $post->post_type || $rows < 1) { exit(2); }
' > "$OUTPUT_DIR/table-before.json"

was_active="no"
if wp --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null 2>&1; then
  was_active="yes"
fi
printf '%s\n' "$was_active" > "$OUTPUT_DIR/plugin-active-before.txt"

rollback() {
  if [[ "$was_active" != "yes" ]]; then
    wp --path="$WP_PATH" plugin deactivate "$PLUGIN" >/dev/null 2>&1 || true
  fi
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
}
trap 'rollback' ERR

if [[ "$was_active" != "yes" ]]; then
  wp --path="$WP_PATH" plugin activate "$PLUGIN" > "$OUTPUT_DIR/activation.txt" 2>&1
else
  printf 'Ninja Tables was already active.\n' > "$OUTPUT_DIR/activation.txt"
fi
wp --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null

# Verify WordPress now processes the shortcode internally.
ST_NINJA_TABLE_ID="$TABLE_ID" wp --path="$WP_PATH" eval '
  $id = (int) getenv("ST_NINJA_TABLE_ID");
  $shortcode = sprintf("[ninja_tables id=\"%d\"]", $id);
  $rendered = do_shortcode($shortcode);
  $ok = false !== stripos($rendered, "ninja") && false === strpos($rendered, "[ninja_tables");
  echo wp_json_encode(array(
    "shortcode_registered" => shortcode_exists("ninja_tables"),
    "rendered_bytes" => strlen($rendered),
    "raw_shortcode_absent" => false === strpos($rendered, "[ninja_tables"),
    "ninja_markup_present" => false !== stripos($rendered, "ninja"),
  ), JSON_PRETTY_PRINT) . PHP_EOL;
  if (!$ok) { exit(3); }
' > "$OUTPUT_DIR/internal-render.json"

wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true

public_verified="no"
for attempt in 1 2 3; do
  test_url="${PUBLIC_URL}?restore_schedule=1&attempt=${attempt}&ts=$(date +%s)"
  if curl -fsSL --max-time 45 \
      -H 'Cache-Control: no-cache, no-store, must-revalidate' \
      -H 'Pragma: no-cache' \
      "$test_url" > "$OUTPUT_DIR/homepage.html" 2> "$OUTPUT_DIR/curl-stderr.txt"; then
    if ! grep -Fq '[ninja_tables' "$OUTPUT_DIR/homepage.html" \
      && grep -Eqi 'ninja[-_ ]?table|footable|ninja_table' "$OUTPUT_DIR/homepage.html"; then
      public_verified="yes"
      break
    fi
  fi
  sleep 3
done

if [[ "$public_verified" != "yes" ]]; then
  echo "ERROR: Homepage did not render the Ninja Tables component." >&2
  exit 1
fi

python3 - "$OUTPUT_DIR/homepage.html" > "$OUTPUT_DIR/public-verification.json" <<'PY'
import json, re, sys
html = open(sys.argv[1], encoding='utf-8', errors='replace').read()
print(json.dumps({
    'raw_shortcode_absent': '[ninja_tables' not in html,
    'ninja_markup_present': bool(re.search(r'ninja[-_ ]?table|footable|ninja_table', html, re.I)),
    'response_bytes': len(html.encode('utf-8')),
}, indent=2, sort_keys=True))
PY
rm -f "$OUTPUT_DIR/homepage.html"

wp --path="$WP_PATH" plugin list --fields=name,status,version --format=csv > "$OUTPUT_DIR/plugins-after.csv"
{
  printf 'action=restore-ninja-table\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'plugin=ninja-tables\n'
  printf 'table_id=%s\n' "$TABLE_ID"
  printf 'plugin_kept_active=yes\n'
  printf 'public_homepage_verified=yes\n'
  printf 'raw_shortcode_absent=yes\n'
} > "$OUTPUT_DIR/summary.txt"

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Ninja Tables restoration complete: %s\n' "$OUTPUT_DIR"
