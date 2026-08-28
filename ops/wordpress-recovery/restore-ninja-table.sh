#!/usr/bin/env bash
# Retire the third-party Ninja Tables runtime after St. Thekla Site Core has
# taken ownership of the legacy table-142 shortcode, purge Bluehost caches, and
# run end-to-end public verification of the repaired site.
# Usage: bash restore-ninja-table.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-site-finalization}"
BACKUP_ROOT="${HOME}/stthekla-backups"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
NINJA_PLUGIN="ninja-tables"
SITE_CORE_PLUGIN="st-thekla-site-core"
TABLE_ID="142"
HOME_URL="https://www.sttheklachurch.org/"
CONTACT_URL="https://www.sttheklachurch.org/contact-us/"
DONATION_URL="https://www.sttheklachurch.org/get-the-most-out-of-your-donations/"
DIRECTORY_URL="https://www.sttheklachurch.org/community/login/"
SESSION_URL="https://www.sttheklachurch.org/wp-json/community-directory/v1/auth/session-check"
SCHEDULE_API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/weekly-schedule"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
for command_name in wp curl python3; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "ERROR: Required command is unavailable: $command_name" >&2
    exit 1
  fi
done
if ! wp --path="$WP_PATH" plugin is-installed "$NINJA_PLUGIN" >/dev/null 2>&1; then
  echo "ERROR: Ninja Tables is not installed, so its prior state cannot be audited." >&2
  exit 1
fi
if ! wp --path="$WP_PATH" plugin is-active "$SITE_CORE_PLUGIN" >/dev/null 2>&1; then
  echo "ERROR: St. Thekla Site Core must be active before Ninja Tables is retired." >&2
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

# Preserve an aggregate record proving that the legacy schedule data still
# exists even though its rendering runtime is being retired.
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
    "legacy_row_count" => $rows,
  ), JSON_PRETTY_PRINT) . PHP_EOL;
' > "$OUTPUT_DIR/table-before.json"

was_active="no"
if wp --path="$WP_PATH" plugin is-active "$NINJA_PLUGIN" >/dev/null 2>&1; then
  was_active="yes"
fi
printf '%s\n' "$was_active" > "$OUTPUT_DIR/plugin-active-before.txt"

rollback() {
  set +e
  if [[ "$was_active" == "yes" ]]; then
    wp --path="$WP_PATH" plugin activate "$NINJA_PLUGIN" >/dev/null 2>&1 || true
  else
    wp --path="$WP_PATH" plugin deactivate "$NINJA_PLUGIN" >/dev/null 2>&1 || true
  fi
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
  if [[ -f "$SCRIPT_DIR/purge-bluehost-cache.php" ]]; then
    wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/purge-bluehost-cache.php" >/dev/null 2>&1 || true
  fi
  set -e
}
trap 'rollback' ERR

if [[ "$was_active" == "yes" ]]; then
  wp --path="$WP_PATH" plugin deactivate "$NINJA_PLUGIN" > "$OUTPUT_DIR/activation.txt" 2>&1
else
  printf 'Ninja Tables was already inactive.\n' > "$OUTPUT_DIR/activation.txt"
fi
if wp --path="$WP_PATH" plugin is-active "$NINJA_PLUGIN" >/dev/null 2>&1; then
  echo "ERROR: Ninja Tables remained active after deactivation." >&2
  exit 1
fi

# Confirm that the church-owned Site Core plugin now owns the legacy shortcode
# and renders all six established schedule rows without Ninja Tables.
wp --path="$WP_PATH" eval '
  $shortcode = "[ninja_tables id=\"142\"]";
  $html = do_shortcode($shortcode);
  $labels = array("Morning Prayers", "Holy Liturgy", "Dismissal", "Refreshments", "Tree of Life", "End of Tree of Life");
  $missing = array();
  foreach ($labels as $label) {
    if (false === strpos($html, $label)) { $missing[] = $label; }
  }
  $report = array(
    "shortcode_registered" => shortcode_exists("ninja_tables"),
    "site_core_markup_present" => false !== strpos($html, "stc-weekly-schedule-table"),
    "raw_shortcode_absent" => false === strpos($html, "[ninja_tables"),
    "rendered_bytes" => strlen($html),
    "missing_labels" => $missing,
  );
  echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  if (!$report["shortcode_registered"] || !$report["site_core_markup_present"] || !$report["raw_shortcode_absent"] || $missing) { exit(3); }
' > "$OUTPUT_DIR/internal-render.json"

# Verify the required repaired plugin stack before public testing.
required_active=(community-directory st-thekla-site-core wpforms-lite wp-mail-smtp shortcodes-ultimate)
for plugin in "${required_active[@]}"; do
  if ! wp --path="$WP_PATH" plugin is-active "$plugin" >/dev/null 2>&1; then
    echo "ERROR: Required plugin is not active: $plugin" >&2
    exit 1
  fi
done

wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/purge-bluehost-cache.php" > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true

curl_common=(
  -fsSL
  --max-time 45
  -H 'Cache-Control: no-cache, no-store, must-revalidate'
  -H 'Pragma: no-cache'
  -H 'User-Agent: StThekla-Recovery-Verification/1.0'
)
: > "$OUTPUT_DIR/curl-stderr.txt"

curl "${curl_common[@]}" "$HOME_URL" > "$OUTPUT_DIR/homepage.html" 2>> "$OUTPUT_DIR/curl-stderr.txt"
curl "${curl_common[@]}" "$CONTACT_URL" > "$OUTPUT_DIR/contact.html" 2>> "$OUTPUT_DIR/curl-stderr.txt"
curl "${curl_common[@]}" "$DONATION_URL" > "$OUTPUT_DIR/donation.html" 2>> "$OUTPUT_DIR/curl-stderr.txt"
curl "${curl_common[@]}" "$DIRECTORY_URL" > "$OUTPUT_DIR/directory-login.html" 2>> "$OUTPUT_DIR/curl-stderr.txt"
curl "${curl_common[@]}" "$SESSION_URL" > "$OUTPUT_DIR/session.json" 2>> "$OUTPUT_DIR/curl-stderr.txt"
curl "${curl_common[@]}" "$SCHEDULE_API_URL" > "$OUTPUT_DIR/schedule-api.json" 2>> "$OUTPUT_DIR/curl-stderr.txt"

python3 - \
  "$OUTPUT_DIR/homepage.html" \
  "$OUTPUT_DIR/contact.html" \
  "$OUTPUT_DIR/donation.html" \
  "$OUTPUT_DIR/directory-login.html" \
  "$OUTPUT_DIR/session.json" \
  "$OUTPUT_DIR/schedule-api.json" \
  > "$OUTPUT_DIR/public-verification.json" <<'PY'
import json
import sys
from pathlib import Path

home = Path(sys.argv[1]).read_text(encoding='utf-8', errors='replace')
contact = Path(sys.argv[2]).read_text(encoding='utf-8', errors='replace')
donation = Path(sys.argv[3]).read_text(encoding='utf-8', errors='replace')
directory = Path(sys.argv[4]).read_text(encoding='utf-8', errors='replace')
session_raw = Path(sys.argv[5]).read_text(encoding='utf-8', errors='replace')
schedule_raw = Path(sys.argv[6]).read_text(encoding='utf-8', errors='replace')

try:
    session = json.loads(session_raw)
except Exception:
    session = None
try:
    schedule = json.loads(schedule_raw)
except Exception:
    schedule = None

schedule_items = schedule.get('items', []) if isinstance(schedule, dict) else []
expected_labels = {
    'Morning Prayers', 'Holy Liturgy', 'Dismissal', 'Refreshments',
    'Tree of Life', 'End of Tree of Life'
}
actual_labels = {
    str(item.get('description', '')) for item in schedule_items
    if isinstance(item, dict)
}

checks = {
    'homepage_site_core_schedule_present': 'stc-weekly-schedule-table' in home,
    'homepage_holy_liturgy_present': 'Holy Liturgy' in home,
    'homepage_raw_ninja_shortcode_absent': '[ninja_tables' not in home,
    'contact_form_present': 'wpforms-form-' in contact,
    'contact_current_address_present': '2 Old Ox Road' in contact and 'Nyack, NY 10960' in contact,
    'contact_legacy_address_absent': '107 strawtown road' not in contact.lower() and 'west nyack' not in contact.lower(),
    'contact_raw_jetpack_shortcode_absent': '[contact-form' not in contact,
    'donation_quickpay_present': 'quickpay' in donation.lower(),
    'donation_paypal_present': 'paypal' in donation.lower(),
    'donation_raw_shortcodes_absent': '[su_lightbox' not in donation,
    'directory_login_component_present': 'cd-wrap cd-login' in directory,
    'directory_member_login_present': 'Member Login' in directory,
    'directory_session_endpoint_json': isinstance(session, dict),
    'site_core_schedule_api_six_rows': len(schedule_items) == 6,
    'site_core_schedule_api_labels_present': expected_labels.issubset(actual_labels),
}

report = {
    'checks': checks,
    'all_checks_passed': all(checks.values()),
    'response_bytes': {
        'homepage': len(home.encode('utf-8')),
        'contact': len(contact.encode('utf-8')),
        'donation': len(donation.encode('utf-8')),
        'directory_login': len(directory.encode('utf-8')),
    },
}
print(json.dumps(report, indent=2, sort_keys=True))
if not report['all_checks_passed']:
    failed = [name for name, passed in checks.items() if not passed]
    raise SystemExit('Public verification failed: ' + ', '.join(failed))
PY

rm -f \
  "$OUTPUT_DIR/homepage.html" \
  "$OUTPUT_DIR/contact.html" \
  "$OUTPUT_DIR/donation.html" \
  "$OUTPUT_DIR/directory-login.html" \
  "$OUTPUT_DIR/session.json" \
  "$OUTPUT_DIR/schedule-api.json"

wp --path="$WP_PATH" plugin list --fields=name,status,version --format=csv > "$OUTPUT_DIR/plugins-after.csv"
{
  printf 'action=finalize-public-site\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'ninja_tables_active=no\n'
  printf 'schedule_owner=st-thekla-site-core\n'
  printf 'community_directory_active=yes\n'
  printf 'contact_form_verified=yes\n'
  printf 'current_address_verified=yes\n'
  printf 'donation_page_verified=yes\n'
  printf 'bluehost_cache_purge_attempted=yes\n'
  printf 'all_public_checks_passed=yes\n'
} > "$OUTPUT_DIR/summary.txt"

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'St. Thekla public-site finalization complete: %s\n' "$OUTPUT_DIR"
