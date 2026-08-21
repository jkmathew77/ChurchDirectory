#!/usr/bin/env bash
# Deploy the validated St. Thekla Site Core plugin from an exact Git commit,
# activate it, and verify the homepage schedule and public API. The prior plugin
# directory and activation state are restored automatically on any failure.
# Usage: bash deploy-site-core.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-site-core}"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN_SLUG="st-thekla-site-core"
PLUGIN_ROOT="$WP_PATH/wp-content/plugins"
TARGET="$PLUGIN_ROOT/$PLUGIN_SLUG"
REPOSITORY="https://github.com/jkmathew77/ChurchDirectory.git"
SOURCE_COMMIT="1166ec7e9be7b5f141d4a4242297f83fa129d0ff"
EXPECTED_VERSION="0.2.0"
HOME_URL="https://www.sttheklachurch.org/"
API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/weekly-schedule"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
for command_name in wp git php curl; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "ERROR: Required command is unavailable: $command_name" >&2
    exit 1
  fi
done

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

mkdir -p "$OUTPUT_DIR/private"
WORK_DIR="$OUTPUT_DIR/private/source"
ROLLBACK_DIR="$OUTPUT_DIR/private/plugin-before"
NEW_DIR="$PLUGIN_ROOT/.${PLUGIN_SLUG}.recovery-new"
rm -rf "$WORK_DIR" "$NEW_DIR"

was_installed="no"
was_active="no"
if [[ -e "$TARGET" || -L "$TARGET" ]]; then
  was_installed="yes"
fi
if wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  was_active="yes"
fi
printf '%s\n' "$was_installed" > "$OUTPUT_DIR/plugin-installed-before.txt"
printf '%s\n' "$was_active" > "$OUTPUT_DIR/plugin-active-before.txt"

rollback() {
  set +e
  wp --path="$WP_PATH" plugin deactivate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
  rm -rf "$NEW_DIR"
  if [[ -e "$TARGET" || -L "$TARGET" ]]; then
    rm -rf "$TARGET"
  fi
  if [[ "$was_installed" == "yes" && -e "$ROLLBACK_DIR" ]]; then
    mv "$ROLLBACK_DIR" "$TARGET"
    if [[ "$was_active" == "yes" ]]; then
      wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
    fi
  fi
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
  set -e
}
trap 'rollback' ERR

# Fetch exactly the commit that passed PHP 7.4, 8.2, and 8.4 lint checks.
git clone --quiet --no-checkout --filter=blob:none "$REPOSITORY" "$WORK_DIR"
git -C "$WORK_DIR" fetch --quiet --depth=1 origin "$SOURCE_COMMIT"
git -C "$WORK_DIR" checkout --quiet "$SOURCE_COMMIT" -- plugin/st-thekla-site-core
resolved_commit="$(git -C "$WORK_DIR" rev-parse FETCH_HEAD)"
if [[ "$resolved_commit" != "$SOURCE_COMMIT" ]]; then
  echo "ERROR: Source commit verification failed." >&2
  exit 1
fi

cp -a "$WORK_DIR/plugin/st-thekla-site-core" "$NEW_DIR"

find "$NEW_DIR" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l > "$OUTPUT_DIR/php-lint.txt"
version="$(grep -m1 -E '^ \* Version:' "$NEW_DIR/st-thekla-site-core.php" | sed -E 's/^ \* Version:[[:space:]]*//' | tr -d '[:space:]')"
if [[ "$version" != "$EXPECTED_VERSION" ]]; then
  echo "ERROR: Expected Site Core $EXPECTED_VERSION but found $version." >&2
  exit 1
fi

find "$NEW_DIR" -type f -print0 | sort -z | xargs -0 sha256sum > "$OUTPUT_DIR/site-core-files-SHA256SUMS.txt"

if [[ "$was_installed" == "yes" ]]; then
  mv "$TARGET" "$ROLLBACK_DIR"
fi
mv "$NEW_DIR" "$TARGET"

wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" > "$OUTPUT_DIR/activation.txt" 2>&1
if ! wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  echo "ERROR: Site Core did not remain active." >&2
  exit 1
fi

# Validate bootstrapped schedule, address, shortcodes, and REST response internally.
wp --path="$WP_PATH" eval '
$rows = get_option("stc_weekly_schedule", array());
$settings = get_option("stc_settings", array());
$html = do_shortcode("[ninja_tables id=\"142\"]");
$request = new WP_REST_Request("GET", "/st-thekla/v1/weekly-schedule");
$response = rest_do_request($request);
$data = $response instanceof WP_REST_Response ? $response->get_data() : array();
$expected = array("Morning Prayers", "Holy Liturgy", "Dismissal", "Refreshments", "Tree of Life", "End of Tree of Life");
$errors = array();
if ( ! is_array($rows) || count($rows) !== 6 ) { $errors[] = "weekly_schedule_count"; }
foreach ( $expected as $label ) { if ( false === strpos($html, $label) ) { $errors[] = "missing_" . sanitize_key($label); } }
if ( false === strpos($html, "stc-weekly-schedule-table") ) { $errors[] = "weekly_markup"; }
if ( ($settings["address_line_2"] ?? "") !== "2 Old Ox Road" ) { $errors[] = "address"; }
if ( ! is_array($data) || empty($data["items"]) || count($data["items"]) !== 6 ) { $errors[] = "rest_items"; }
$report = array(
  "schedule_rows" => is_array($rows) ? count($rows) : 0,
  "address_configured" => (($settings["address_line_2"] ?? "") === "2 Old Ox Road"),
  "shortcode_verified" => empty($errors),
  "rest_status" => $response instanceof WP_REST_Response ? $response->get_status() : 0,
  "errors" => $errors,
);
echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if ( ! empty($errors) ) { exit(2); }
' > "$OUTPUT_DIR/internal-verification.json"

wp --path="$WP_PATH" rewrite flush --hard > "$OUTPUT_DIR/rewrite-flush.txt" 2>&1 || true
wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true

public_home_verified="no"
for attempt in 1 2 3; do
  test_url="${HOME_URL}?site_core_recovery=${SOURCE_COMMIT:0:8}&attempt=${attempt}&ts=$(date +%s)"
  if curl -fsSL --max-time 45 \
      -H 'Cache-Control: no-cache, no-store, must-revalidate' \
      -H 'Pragma: no-cache' \
      "$test_url" > "$OUTPUT_DIR/public-homepage.html" 2> "$OUTPUT_DIR/homepage-curl-stderr.txt"; then
    if grep -q 'stc-weekly-schedule-table' "$OUTPUT_DIR/public-homepage.html" \
      && grep -q 'Holy Liturgy' "$OUTPUT_DIR/public-homepage.html" \
      && ! grep -q '\[ninja_tables' "$OUTPUT_DIR/public-homepage.html"; then
      public_home_verified="yes"
      break
    fi
  fi
  sleep 3
done
if [[ "$public_home_verified" != "yes" ]]; then
  echo "ERROR: Public homepage verification failed." >&2
  exit 1
fi

curl -fsSL --max-time 45 \
  -H 'Cache-Control: no-cache, no-store, must-revalidate' \
  "${API_URL}?ts=$(date +%s)" > "$OUTPUT_DIR/public-weekly-api.json"
python3 - "$OUTPUT_DIR/public-weekly-api.json" > "$OUTPUT_DIR/public-api-verification.json" <<'PY'
import json
import sys
from pathlib import Path

data = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
items = data.get('items', [])
expected = [
    'Morning Prayers',
    'Holy Liturgy',
    'Dismissal',
    'Refreshments',
    'Tree of Life',
    'End of Tree of Life',
]
labels = [item.get('description') for item in items]
report = {
    'item_count': len(items),
    'expected_labels_present': all(label in labels for label in expected),
    'day': data.get('day'),
}
print(json.dumps(report, indent=2, sort_keys=True))
if not report['expected_labels_present'] or len(items) != 6:
    raise SystemExit('Public weekly schedule API verification failed.')
PY

python3 - "$OUTPUT_DIR/public-homepage.html" > "$OUTPUT_DIR/public-homepage-verification.json" <<'PY'
import json
import sys
from pathlib import Path

html = Path(sys.argv[1]).read_text(encoding='utf-8', errors='replace')
report = {
    'weekly_schedule_markup_present': 'stc-weekly-schedule-table' in html,
    'legacy_ninja_shortcode_absent': '[ninja_tables' not in html,
    'holy_liturgy_present': 'Holy Liturgy' in html,
    'response_bytes': len(html.encode('utf-8')),
}
print(json.dumps(report, indent=2, sort_keys=True))
PY
rm -f "$OUTPUT_DIR/public-homepage.html" "$OUTPUT_DIR/public-weekly-api.json"

wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-state-after.json"

{
  printf 'action=deploy-site-core\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'source_commit=%s\n' "$SOURCE_COMMIT"
  printf 'version=%s\n' "$EXPECTED_VERSION"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'plugin_active=yes\n'
  printf 'homepage_schedule_verified=yes\n'
  printf 'public_api_verified=yes\n'
  printf 'rollback_copy=%s\n' "$ROLLBACK_DIR"
} > "$OUTPUT_DIR/summary.txt"

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Site Core deployment complete: %s\n' "$OUTPUT_DIR"
