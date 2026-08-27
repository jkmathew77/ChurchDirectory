#!/usr/bin/env bash
# Deploy the exact merged Site Core 0.3.0 release and apply the approved Sparkill move.
# Usage: bash deploy-sparkill-move.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-sparkill-move}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN_SLUG="st-thekla-site-core"
PLUGIN_ROOT="$WP_PATH/wp-content/plugins"
TARGET="$PLUGIN_ROOT/$PLUGIN_SLUG"
SOURCE_COMMIT="a2ef358c9a2dca5eff4d0d7648c8197d7859f178"
EXPECTED_VERSION="0.3.0"
EXPECTED_DATA_VERSION="003"
REPOSITORY="https://github.com/jkmathew77/ChurchDirectory.git"
IMAGE_B64_LENGTH="168336"
IMAGE_ARCHIVE_SHA256="4e8c49e67bc6785701c26c6bfa6b9f2edc1aeb3a7a90579a4714ccf546b8443c"
HOME_URL="https://www.sttheklachurch.org/"
CONTACT_URL="https://www.sttheklachurch.org/contact-us/"
VISIT_URL="https://www.sttheklachurch.org/visit-us/"
DIRECTORY_URL="https://www.sttheklachurch.org/community/login/"
SESSION_URL="https://www.sttheklachurch.org/wp-json/community-directory/v1/auth/session-check"
PUBLIC_API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/public"
SCHEDULE_API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/weekly-schedule"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
for command_name in wp git php python3 curl base64 tar sha256sum; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "ERROR: Required command is unavailable: $command_name" >&2
    exit 1
  fi
done
for required_file in \
  "$SCRIPT_DIR/apply-sparkill-move.php" \
  "$SCRIPT_DIR/purge-bluehost-cache.php" \
  "$SCRIPT_DIR/move-images-deploy-small.part00" \
  "$SCRIPT_DIR/move-images-deploy-small.part01" \
  "$SCRIPT_DIR/move-images-deploy-small.part02" \
  "$SCRIPT_DIR/move-images-deploy-small.part03-04" \
  "$SCRIPT_DIR/move-images-deploy-small.part05-06" \
  "$SCRIPT_DIR/move-images-deploy-small.part07-08"; do
  if [[ ! -f "$required_file" ]]; then
    echo "ERROR: Required release file is missing: $required_file" >&2
    exit 1
  fi
done

LATEST_BACKUP="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_BACKUP" || ! -f "$LATEST_BACKUP/SHA256SUMS" ]]; then
  echo "ERROR: No verified St. Thekla backup was found." >&2
  exit 1
fi
backup_age=$(( $(date +%s) - $(stat -c %Y "$LATEST_BACKUP/SHA256SUMS") ))
if (( backup_age > 86400 )); then
  echo "ERROR: Latest backup is older than 24 hours: $LATEST_BACKUP" >&2
  exit 1
fi
mkdir -p "$(dirname "$OUTPUT_DIR")"
(
  cd "$LATEST_BACKUP"
  sha256sum -c SHA256SUMS
) > "$OUTPUT_DIR.backup-verification.tmp" 2>&1 || {
  cat "$OUTPUT_DIR.backup-verification.tmp" >&2
  rm -f "$OUTPUT_DIR.backup-verification.tmp"
  exit 1
}

mkdir -p "$OUTPUT_DIR/private"
chmod 700 "$OUTPUT_DIR" "$OUTPUT_DIR/private"
mv "$OUTPUT_DIR.backup-verification.tmp" "$OUTPUT_DIR/backup-verification.txt"
PRIVATE_DIR="$OUTPUT_DIR/private"
SNAPSHOT_FILE="$PRIVATE_DIR/wordpress-content-options.snapshot"
WORK_DIR="$PRIVATE_DIR/source"
ROLLBACK_DIR="$PRIVATE_DIR/plugin-before"
NEW_DIR="$PLUGIN_ROOT/.${PLUGIN_SLUG}.sparkill-new"
IMAGE_B64="$PRIVATE_DIR/move-images.b64"
IMAGE_ARCHIVE="$PRIVATE_DIR/move-images.tar.gz"
IMAGE_DIR="$PRIVATE_DIR/move-images"

plugin_installed_before="no"
plugin_active_before="no"
plugin_swapped="no"
snapshot_created="no"
content_applied="no"
exterior_attachment_id="0"
parking_attachment_id="0"
exterior_created="no"
parking_created="no"

if [[ -e "$TARGET" || -L "$TARGET" ]]; then
  plugin_installed_before="yes"
fi
if wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  plugin_active_before="yes"
fi
printf '%s\n' "$plugin_installed_before" > "$OUTPUT_DIR/plugin-installed-before.txt"
printf '%s\n' "$plugin_active_before" > "$OUTPUT_DIR/plugin-active-before.txt"
wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-before.json" 2>/dev/null || printf '{}\n' > "$OUTPUT_DIR/plugin-before.json"

count_directory_tables() {
  wp --path="$WP_PATH" eval '
    global $wpdb;
    $like = $wpdb->esc_like($wpdb->prefix . "cd_") . "%";
    $tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $like));
    $counts = array();
    foreach ($tables as $table) {
      $suffix = substr($table, strlen($wpdb->prefix . "cd_"));
      $counts[$suffix] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }
    ksort($counts);
    echo wp_json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  '
}
count_directory_tables > "$OUTPUT_DIR/directory-counts-before.json"

rollback() {
  set +e
  echo "Rolling back the Sparkill move release..." >&2

  if [[ "$snapshot_created" == "yes" ]]; then
    ST_MOVE_MODE=rollback \
    ST_MOVE_SNAPSHOT_FILE="$SNAPSHOT_FILE" \
      wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/apply-sparkill-move.php" \
      > "$PRIVATE_DIR/content-rollback.json" 2> "$PRIVATE_DIR/content-rollback-stderr.txt" || true
  fi

  if [[ "$exterior_created" == "yes" && "$exterior_attachment_id" =~ ^[0-9]+$ && "$exterior_attachment_id" -gt 0 ]]; then
    wp --path="$WP_PATH" post delete "$exterior_attachment_id" --force >/dev/null 2>&1 || true
  fi
  if [[ "$parking_created" == "yes" && "$parking_attachment_id" =~ ^[0-9]+$ && "$parking_attachment_id" -gt 0 ]]; then
    wp --path="$WP_PATH" post delete "$parking_attachment_id" --force >/dev/null 2>&1 || true
  fi

  if [[ "$plugin_swapped" == "yes" ]]; then
    wp --path="$WP_PATH" plugin deactivate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
    rm -rf "$TARGET" "$NEW_DIR"
    if [[ "$plugin_installed_before" == "yes" && -e "$ROLLBACK_DIR" ]]; then
      mv "$ROLLBACK_DIR" "$TARGET"
      if [[ "$plugin_active_before" == "yes" ]]; then
        wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
      fi
    fi
  fi

  wp --path="$WP_PATH" rewrite flush --hard >/dev/null 2>&1 || true
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
  wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/purge-bluehost-cache.php" >/dev/null 2>&1 || true
  set -e
}
trap rollback ERR

ST_MOVE_MODE=snapshot \
ST_MOVE_SNAPSHOT_FILE="$SNAPSHOT_FILE" \
  wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/apply-sparkill-move.php" \
  > "$OUTPUT_DIR/content-snapshot-summary.json" 2> "$OUTPUT_DIR/content-snapshot-stderr.txt"
snapshot_created="yes"

rm -rf "$WORK_DIR" "$NEW_DIR"
git clone --quiet --no-checkout --filter=blob:none "$REPOSITORY" "$WORK_DIR"
git -C "$WORK_DIR" fetch --quiet --depth=1 origin "$SOURCE_COMMIT"
git -C "$WORK_DIR" checkout --quiet "$SOURCE_COMMIT" -- plugin/st-thekla-site-core
resolved_commit="$(git -C "$WORK_DIR" rev-parse FETCH_HEAD)"
if [[ "$resolved_commit" != "$SOURCE_COMMIT" ]]; then
  echo "ERROR: Exact source commit verification failed." >&2
  exit 1
fi
cp -a "$WORK_DIR/plugin/st-thekla-site-core" "$NEW_DIR"
find "$NEW_DIR" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l > "$OUTPUT_DIR/php-lint.txt"
version="$(grep -m1 -E '^ \* Version:' "$NEW_DIR/st-thekla-site-core.php" | sed -E 's/^ \* Version:[[:space:]]*//' | tr -d '[:space:]')"
if [[ "$version" != "$EXPECTED_VERSION" ]]; then
  echo "ERROR: Expected Site Core $EXPECTED_VERSION but found $version." >&2
  exit 1
fi
grep -q "define( 'STC_DATA_VERSION', '$EXPECTED_DATA_VERSION' )" "$NEW_DIR/st-thekla-site-core.php"
find "$NEW_DIR" -type f -print0 | sort -z | xargs -0 sha256sum > "$OUTPUT_DIR/site-core-files-SHA256SUMS.txt"

if [[ "$plugin_active_before" == "yes" ]]; then
  wp --path="$WP_PATH" plugin deactivate "$PLUGIN_SLUG" > "$OUTPUT_DIR/deactivation.txt" 2>&1
fi
if [[ "$plugin_installed_before" == "yes" ]]; then
  mv "$TARGET" "$ROLLBACK_DIR"
fi
mv "$NEW_DIR" "$TARGET"
plugin_swapped="yes"
wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" > "$OUTPUT_DIR/activation.txt" 2>&1
if ! wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  echo "ERROR: Site Core did not remain active." >&2
  exit 1
fi
wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-after-swap.json"
if [[ "$(wp --path="$WP_PATH" option get stc_data_version 2>/dev/null || true)" != "$EXPECTED_DATA_VERSION" ]]; then
  echo "ERROR: Site Core migration $EXPECTED_DATA_VERSION did not complete." >&2
  exit 1
fi

cat \
  "$SCRIPT_DIR/move-images-deploy-small.part00" \
  "$SCRIPT_DIR/move-images-deploy-small.part01" \
  "$SCRIPT_DIR/move-images-deploy-small.part02" \
  "$SCRIPT_DIR/move-images-deploy-small.part03-04" \
  "$SCRIPT_DIR/move-images-deploy-small.part05-06" \
  "$SCRIPT_DIR/move-images-deploy-small.part07-08" \
  > "$IMAGE_B64"
if [[ "$(wc -c < "$IMAGE_B64" | tr -d '[:space:]')" != "$IMAGE_B64_LENGTH" ]]; then
  echo "ERROR: Approved image payload length verification failed." >&2
  exit 1
fi
base64 --decode "$IMAGE_B64" > "$IMAGE_ARCHIVE"
echo "$IMAGE_ARCHIVE_SHA256  $IMAGE_ARCHIVE" | sha256sum -c - > "$OUTPUT_DIR/image-archive-verification.txt"
mkdir -p "$IMAGE_DIR"
tar -xzf "$IMAGE_ARCHIVE" -C "$IMAGE_DIR"
EXTERIOR_FILE="$IMAGE_DIR/st-thekla-sacred-heart-chapel-new-home.jpg"
PARKING_FILE="$IMAGE_DIR/st-thekla-sacred-heart-chapel-parking-map.jpg"
test -s "$EXTERIOR_FILE"
test -s "$PARKING_FILE"

find_attachment_by_title() {
  local title="$1"
  ST_FIND_TITLE="$title" wp --path="$WP_PATH" eval '
    global $wpdb;
    $title = getenv("ST_FIND_TITLE");
    $id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s ORDER BY ID DESC LIMIT 1", "attachment", $title));
    echo $id ? (int) $id : 0;
  '
}

exterior_title="St. Thekla at Sacred Heart Chapel"
parking_title="Sacred Heart Chapel Parking and Arrival Map"
exterior_attachment_id="$(find_attachment_by_title "$exterior_title")"
if [[ "$exterior_attachment_id" == "0" ]]; then
  exterior_attachment_id="$(wp --path="$WP_PATH" media import "$EXTERIOR_FILE" --porcelain --title="$exterior_title")"
  exterior_created="yes"
fi
parking_attachment_id="$(find_attachment_by_title "$parking_title")"
if [[ "$parking_attachment_id" == "0" ]]; then
  parking_attachment_id="$(wp --path="$WP_PATH" media import "$PARKING_FILE" --porcelain --title="$parking_title")"
  parking_created="yes"
fi
if [[ ! "$exterior_attachment_id" =~ ^[0-9]+$ || "$exterior_attachment_id" -le 0 || ! "$parking_attachment_id" =~ ^[0-9]+$ || "$parking_attachment_id" -le 0 ]]; then
  echo "ERROR: WordPress media import did not return valid attachment IDs." >&2
  exit 1
fi
wp --path="$WP_PATH" post meta update "$exterior_attachment_id" _wp_attachment_image_alt 'Exterior of Sacred Heart Chapel, the new worship location of St. Thekla Malankara Orthodox Church in Sparkill, New York.' >/dev/null
wp --path="$WP_PATH" post update "$exterior_attachment_id" --post_excerpt='Sacred Heart Chapel, the new worship location of St. Thekla.' >/dev/null
wp --path="$WP_PATH" post meta update "$parking_attachment_id" _wp_attachment_image_alt 'Aerial parking map for Sacred Heart Chapel showing entrances, designated parking, no-parking areas, chapel entrance and exit, and St. Martin Hall restrooms.' >/dev/null
wp --path="$WP_PATH" post update "$parking_attachment_id" --post_excerpt='Parking, entrance and arrival map for Sacred Heart Chapel.' >/dev/null
printf '{"exterior_attachment_id":%s,"parking_attachment_id":%s,"exterior_created":"%s","parking_created":"%s"}\n' \
  "$exterior_attachment_id" "$parking_attachment_id" "$exterior_created" "$parking_created" > "$OUTPUT_DIR/media-import-summary.json"

ST_MOVE_MODE=apply \
ST_MOVE_SNAPSHOT_FILE="$SNAPSHOT_FILE" \
ST_MOVE_EXTERIOR_ID="$exterior_attachment_id" \
ST_MOVE_PARKING_ID="$parking_attachment_id" \
  wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/apply-sparkill-move.php" \
  > "$OUTPUT_DIR/content-apply-summary.json" 2> "$OUTPUT_DIR/content-apply-stderr.txt"
content_applied="yes"

ST_MOVE_MODE=verify \
ST_MOVE_SNAPSHOT_FILE="$SNAPSHOT_FILE" \
ST_MOVE_EXTERIOR_ID="$exterior_attachment_id" \
ST_MOVE_PARKING_ID="$parking_attachment_id" \
  wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/apply-sparkill-move.php" \
  > "$OUTPUT_DIR/internal-verification.json" 2> "$OUTPUT_DIR/internal-verification-stderr.txt"

wp --path="$WP_PATH" rewrite flush --hard > "$OUTPUT_DIR/rewrite-flush.txt" 2>&1 || true
wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/object-cache-flush.txt" 2>&1 || true
wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/purge-bluehost-cache.php" > "$OUTPUT_DIR/bluehost-cache-purge.txt" 2>&1 || true
sleep 2

fetch_public() {
  local name="$1"
  local url="$2"
  local output="$OUTPUT_DIR/${name}.body"
  local separator='?'
  local status
  [[ "$url" == *\?* ]] && separator='&'
  status="$(curl -sS -L --max-time 60 \
    -o "$output" \
    -w '%{http_code}' \
    -H 'Cache-Control: no-cache, no-store, must-revalidate' \
    -H 'Pragma: no-cache' \
    -H 'User-Agent: StThekla-Sparkill-Move-Verification/1.0' \
    "${url}${separator}sparkill_move=${SOURCE_COMMIT:0:8}&ts=$(date +%s%N)" \
    2> "$OUTPUT_DIR/${name}-curl-stderr.txt")"
  printf '%s\n' "$status" > "$OUTPUT_DIR/${name}-http-status.txt"
  [[ "$status" == "200" ]]
}

fetch_public homepage "$HOME_URL"
fetch_public contact "$CONTACT_URL"
fetch_public visit "$VISIT_URL"
fetch_public directory-login "$DIRECTORY_URL"
fetch_public directory-session "$SESSION_URL"
fetch_public public-api "$PUBLIC_API_URL"
fetch_public schedule-api "$SCHEDULE_API_URL"

python3 - "$OUTPUT_DIR" > "$OUTPUT_DIR/public-verification.json" <<'PY'
import json
import sys
from pathlib import Path

root = Path(sys.argv[1])
def read(name):
    return (root / name).read_text(encoding='utf-8', errors='replace')

home = read('homepage.body')
contact = read('contact.body')
visit = read('visit.body')
login = read('directory-login.body')
session = json.loads(read('directory-session.body'))
public_api = json.loads(read('public-api.body'))
schedule_api = json.loads(read('schedule-api.body'))

old_values = [
    '107 Strawtown Road', 'West Nyack', '2 Old Ox Road',
    'St. Thomas Lutheran Church', 'St Thomas Lutheran Church',
    'Nyack, NY 10960', 'Nyack, New York 10960'
]
expected_schedule = [
    ('8:00 AM', 'Lilyo'),
    ('8:30 AM', 'Morning Prayer'),
    ('9:00 AM', 'Holy Qurbana'),
    ('10:10 AM', 'Dismissal'),
    ('10:30 AM', 'Refreshments / Fellowship'),
    ('10:45 AM', 'Tree of Life'),
    ('11:30 AM', 'End of Tree of Life'),
]
items = schedule_api.get('items', []) if isinstance(schedule_api, dict) else []
actual_schedule = [(str(x.get('time', '')), str(x.get('description', ''))) for x in items if isinstance(x, dict)]
contact_payload = public_api.get('contact', {}) if isinstance(public_api, dict) else {}
visit_payload = public_api.get('visit', {}) if isinstance(public_api, dict) else {}

checks = {
    'homepage_compact_visit_component': 'stc-visit-us-compact' in home,
    'homepage_exterior_image': 'stc-visit-image' in home,
    'homepage_new_address': 'Sacred Heart Chapel' in home and '175 Route 340' in home and 'Sparkill' in home and '10976' in home,
    'homepage_schedule': all(time in home and label in home for time, label in expected_schedule),
    'homepage_no_old_location': not any(value.lower() in home.lower() for value in old_values),
    'homepage_no_raw_ninja': '[ninja_tables' not in home,
    'contact_wpforms': 'wpforms-form-' in contact,
    'contact_new_address': 'Sacred Heart Chapel' in contact and '175 Route 340' in contact and 'Sparkill' in contact,
    'contact_schedule': 'Lilyo' in contact and 'Holy Qurbana' in contact and 'Tree of Life' in contact,
    'contact_no_old_location': not any(value.lower() in contact.lower() for value in old_values),
    'visit_full_component': 'stc-visit-us-full' in visit,
    'visit_parking_map': 'stc-parking-map' in visit,
    'visit_parking_notes': 'designated parking areas' in visit and 'marked in red' in visit,
    'visit_no_old_location': not any(value.lower() in visit.lower() for value in old_values),
    'directory_login': 'cd-wrap cd-login' in login and 'Member Login' in login,
    'directory_session_json': isinstance(session, dict),
    'public_api_new_address': 'Sacred Heart Chapel' in str(contact_payload.get('address', '')) and '175 Route 340' in str(contact_payload.get('address', '')) and 'Sparkill' in str(contact_payload.get('address', '')),
    'public_api_visit_images': bool(visit_payload.get('exterior_image_url')) and bool(visit_payload.get('parking_map_image_url')),
    'public_api_move_date': str(visit_payload.get('move_effective_date', '')) == '2026-08-23',
    'schedule_api_exact': actual_schedule == expected_schedule,
}
failed = [name for name, passed in checks.items() if not passed]
report = {'checks': checks, 'failed': failed, 'schedule': actual_schedule}
print(json.dumps(report, indent=2, sort_keys=True))
if failed:
    raise SystemExit('Public verification failed: ' + ', '.join(failed))
PY

count_directory_tables > "$OUTPUT_DIR/directory-counts-after.json"
if ! cmp -s "$OUTPUT_DIR/directory-counts-before.json" "$OUTPUT_DIR/directory-counts-after.json"; then
  echo "ERROR: Community Directory aggregate table counts changed during the public-site release." >&2
  diff -u "$OUTPUT_DIR/directory-counts-before.json" "$OUTPUT_DIR/directory-counts-after.json" >&2 || true
  exit 1
fi
printf '{"custom_table_counts_unchanged":true}\n' > "$OUTPUT_DIR/directory-data-preservation.json"

wp --path="$WP_PATH" plugin list --fields=name,status,version,update,auto_update --format=csv > "$OUTPUT_DIR/plugins-after.csv"
wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-final.json"
wp --path="$WP_PATH" eval '
  global $wpdb;
  $needles = array("107 Strawtown Road", "West Nyack", "2 Old Ox Road", "St. Thomas Lutheran Church", "St Thomas Lutheran Church", "Nyack, NY 10960", "Nyack, New York 10960");
  $matches = array();
  $rows = $wpdb->get_results("SELECT ID, post_type, post_status, post_title, post_content FROM {$wpdb->posts} WHERE post_status NOT IN (\"trash\", \"auto-draft\", \"inherit\") ORDER BY ID ASC");
  foreach ($rows as $row) {
    foreach ($needles as $needle) {
      if (false !== stripos($row->post_content, $needle)) {
        $matches[] = array("id" => (int) $row->ID, "type" => $row->post_type, "status" => $row->post_status, "title" => $row->post_title, "matched" => $needle);
        break;
      }
    }
  }
  echo wp_json_encode(array("remaining_content_references" => $matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
' > "$OUTPUT_DIR/remaining-old-location-references.json"

rm -f "$OUTPUT_DIR"/*.body

{
  printf 'action=deploy-sparkill-move\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'source_commit=%s\n' "$SOURCE_COMMIT"
  printf 'site_core_version=%s\n' "$EXPECTED_VERSION"
  printf 'site_core_data_version=%s\n' "$EXPECTED_DATA_VERSION"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'location=Sacred Heart Chapel, 175 Route 340, Sparkill, NY 10976\n'
  printf 'move_effective_date=2026-08-23\n'
  printf 'homepage_updated=yes\n'
  printf 'contact_updated=yes\n'
  printf 'visit_page_published=yes\n'
  printf 'navigation_updated=yes\n'
  printf 'move_announcement_published=yes\n'
  printf 'exterior_attachment_id=%s\n' "$exterior_attachment_id"
  printf 'parking_attachment_id=%s\n' "$parking_attachment_id"
  printf 'directory_counts_unchanged=yes\n'
  printf 'public_verification=passed\n'
  printf 'rollback_plugin_copy=%s\n' "$ROLLBACK_DIR"
  printf 'private_content_snapshot=%s\n' "$SNAPSHOT_FILE"
} > "$OUTPUT_DIR/summary.txt"

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Sparkill move release complete: %s\n' "$OUTPUT_DIR"
