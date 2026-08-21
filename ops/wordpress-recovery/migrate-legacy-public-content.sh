#!/usr/bin/env bash
# Replace the final public Shortcodes Ultimate and PDF Embedder dependencies with
# native WordPress content, then privately quarantine the inactive plugin code.
# Usage: bash migrate-legacy-public-content.sh <wp_path> <output_dir>
set -Eeuo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-legacy-public-content}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKUP_ROOT="${HOME}/stthekla-backups"
QUARANTINE_ROOT="${HOME}/stthekla-plugin-quarantine"
PLUGIN_ROOT="$WP_PATH/wp-content/plugins"
SU_SLUG="shortcodes-ultimate"
PDF_SLUG="pdf-embedder"
DONATION_URL="https://www.sttheklachurch.org/get-the-most-out-of-your-donations/"
PDF_URL="https://www.sttheklachurch.org/wp-content/uploads/2024/03/Palm-Sunday-2024-DRAFT-1.pdf"
HOME_URL="https://www.sttheklachurch.org/"
CONTACT_URL="https://www.sttheklachurch.org/contact-us/"
DIRECTORY_LOGIN_URL="https://www.sttheklachurch.org/community/login/"
SCHEDULE_API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/weekly-schedule"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
for command_name in wp php python3 curl sha256sum; do
  command -v "$command_name" >/dev/null 2>&1 || {
    echo "ERROR: Required command is unavailable: $command_name" >&2
    exit 1
  }
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
) >/dev/null

mkdir -p "$OUTPUT_DIR/private"
PRIVATE_DIR="$OUTPUT_DIR/private"
STAMP="$(date +%Y%m%d-%H%M%S)"
QUARANTINE_DIR="$QUARANTINE_ROOT/${STAMP}-retired-content-plugins"
mkdir -p "$QUARANTINE_DIR"

plugin_exists() {
  wp --path="$WP_PATH" plugin get "$1" --field=name >/dev/null 2>&1
}
plugin_active() {
  wp --path="$WP_PATH" plugin is-active "$1" >/dev/null 2>&1
}

su_installed="no"
su_active="no"
pdf_installed="no"
pdf_active="no"
plugin_exists "$SU_SLUG" && su_installed="yes"
plugin_active "$SU_SLUG" && su_active="yes"
plugin_exists "$PDF_SLUG" && pdf_installed="yes"
plugin_active "$PDF_SLUG" && pdf_active="yes"

{
  printf 'plugin,installed,active,directory\n'
  printf '%s,%s,%s,%s\n' "$SU_SLUG" "$su_installed" "$su_active" "$PLUGIN_ROOT/$SU_SLUG"
  printf '%s,%s,%s,%s\n' "$PDF_SLUG" "$pdf_installed" "$pdf_active" "$PLUGIN_ROOT/$PDF_SLUG"
} > "$OUTPUT_DIR/plugin-state-before.csv"

rollback_needed="yes"
content_migrated_in_this_run="no"
su_moved="no"
pdf_moved="no"
rollback() {
  local exit_code=$?
  if [[ "$rollback_needed" != "yes" ]]; then
    exit "$exit_code"
  fi
  set +e

  if [[ "$su_moved" == "yes" && -e "$QUARANTINE_DIR/$SU_SLUG" && ! -e "$PLUGIN_ROOT/$SU_SLUG" ]]; then
    mv "$QUARANTINE_DIR/$SU_SLUG" "$PLUGIN_ROOT/$SU_SLUG"
  fi
  if [[ "$pdf_moved" == "yes" && -e "$QUARANTINE_DIR/$PDF_SLUG" && ! -e "$PLUGIN_ROOT/$PDF_SLUG" ]]; then
    mv "$QUARANTINE_DIR/$PDF_SLUG" "$PLUGIN_ROOT/$PDF_SLUG"
  fi

  if [[ "$content_migrated_in_this_run" == "yes" ]]; then
    ST_MIGRATION_MODE=rollback \
    ST_MIGRATION_BACKUP_DIR="$PRIVATE_DIR" \
      wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/migrate-legacy-public-content.php" \
      > "$PRIVATE_DIR/rollback-content.json" 2> "$PRIVATE_DIR/rollback-content-stderr.txt" || true
  fi

  if [[ "$su_active" == "yes" && -e "$PLUGIN_ROOT/$SU_SLUG" ]]; then
    wp --path="$WP_PATH" plugin activate "$SU_SLUG" >/dev/null 2>&1 || true
  fi
  if [[ "$pdf_active" == "yes" && -e "$PLUGIN_ROOT/$PDF_SLUG" ]]; then
    wp --path="$WP_PATH" plugin activate "$PDF_SLUG" >/dev/null 2>&1 || true
  fi

  wp --path="$WP_PATH" rewrite flush --hard >/dev/null 2>&1 || true
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
  echo "ERROR: Migration validation failed; reversible plugin changes were restored." >&2
  exit "$exit_code"
}
trap rollback ERR

ST_MIGRATION_MODE=apply \
ST_MIGRATION_BACKUP_DIR="$PRIVATE_DIR" \
  wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/migrate-legacy-public-content.php" \
  > "$OUTPUT_DIR/content-migration.json" 2> "$OUTPUT_DIR/content-migration-stderr.txt"

already_migrated="$(python3 - "$OUTPUT_DIR/content-migration.json" <<'PY'
import json
import sys
from pathlib import Path
report = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
print('yes' if report.get('already_migrated') else 'no')
PY
)"
[[ "$already_migrated" == "no" ]] && content_migrated_in_this_run="yes"

if plugin_active "$SU_SLUG"; then
  wp --path="$WP_PATH" plugin deactivate "$SU_SLUG" > "$OUTPUT_DIR/shortcodes-ultimate-deactivation.txt" 2>&1
else
  printf 'already_inactive=yes\n' > "$OUTPUT_DIR/shortcodes-ultimate-deactivation.txt"
fi
if plugin_active "$PDF_SLUG"; then
  wp --path="$WP_PATH" plugin deactivate "$PDF_SLUG" > "$OUTPUT_DIR/pdf-embedder-deactivation.txt" 2>&1
else
  printf 'already_inactive=yes\n' > "$OUTPUT_DIR/pdf-embedder-deactivation.txt"
fi

wp --path="$WP_PATH" post get 196 --field=post_content > "$OUTPUT_DIR/donation-content-after.txt"
wp --path="$WP_PATH" post get 2932 --field=post_content > "$OUTPUT_DIR/palm-sunday-content-after.txt"
wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/shortcode-audit.php" > "$OUTPUT_DIR/shortcode-usage-after-content.csv"
if awk -F, 'NR > 1 && ($5 == "shortcodes_ultimate" || $5 == "pdf_embedder") { found=1 } END { exit found ? 0 : 1 }' "$OUTPUT_DIR/shortcode-usage-after-content.csv"; then
  echo "ERROR: Legacy shortcode dependencies remain after migration." >&2
  exit 1
fi

wp --path="$WP_PATH" rewrite flush --hard > "$OUTPUT_DIR/rewrite-flush-before-quarantine.txt" 2>&1 || true
wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush-before-quarantine.txt" 2>&1 || true

fetch_public() {
  local name="$1"
  local url="$2"
  local output="$OUTPUT_DIR/${name}.html"
  local separator='?'
  local status
  [[ "$url" == *\?* ]] && separator='&'
  status="$(curl -sS -L --max-time 60 \
    -o "$output" \
    -w '%{http_code}' \
    -H 'Cache-Control: no-cache, no-store, must-revalidate' \
    -H 'Pragma: no-cache' \
    "${url}${separator}legacy_content_migration=1&ts=$(date +%s%N)" \
    2> "$OUTPUT_DIR/${name}-curl-stderr.txt")"
  printf '%s\n' "$status" > "$OUTPUT_DIR/${name}-http-status.txt"
  [[ "$status" == "200" ]]
}

fetch_public donation "$DONATION_URL"
fetch_public palm-sunday-pdf "$PDF_URL"
fetch_public homepage "$HOME_URL"
fetch_public contact "$CONTACT_URL"
fetch_public directory-login "$DIRECTORY_LOGIN_URL"
fetch_public schedule-api "$SCHEDULE_API_URL"

python3 - "$OUTPUT_DIR" > "$OUTPUT_DIR/public-verification-before-quarantine.json" <<'PY'
import json
import sys
from pathlib import Path

root = Path(sys.argv[1])
def read(name):
    return (root / name).read_text(encoding='utf-8', errors='replace')

def schedule_rows(payload):
    rows = payload.get('data', payload) if isinstance(payload, dict) else payload
    if isinstance(rows, dict):
        rows = rows.get('schedule', rows.get('items', []))
    return rows if isinstance(rows, list) else []

donation = read('donation.html')
event_content = read('palm-sunday-content-after.txt')
home = read('homepage.html')
contact = read('contact.html')
login = read('directory-login.html')
schedule = json.loads(read('schedule-api.html'))
checks = {
    'donation_native_marker_present': 'st-thekla-native-donation-options' in donation,
    'donation_su_shortcode_absent': '[su_' not in donation,
    'donation_email_present': 'sainttheklachurch@gmail.com' in donation,
    'donation_paypal_button_present': 'KQXSC8WTH7TXJ' in donation and 'Donate with PayPal' in donation,
    'event_pdf_shortcode_absent': '[pdf-embedder' not in event_content,
    'event_pdf_link_present': 'Palm-Sunday-2024-DRAFT-1.pdf' in event_content,
    'pdf_asset_nonempty': (root / 'palm-sunday-pdf.html').stat().st_size > 1000,
    'homepage_schedule_present': 'stc-weekly-schedule-table' in home and 'Holy Liturgy' in home,
    'homepage_raw_ninja_absent': '[ninja_tables' not in home,
    'contact_wpforms_present': 'wpforms' in contact.lower(),
    'contact_raw_jetpack_absent': '[contact-form' not in contact and '[contact-field' not in contact,
    'directory_login_present': 'cd-wrap cd-login' in login and 'Member Login' in login,
    'schedule_api_has_six_rows': len(schedule_rows(schedule)) == 6,
}
failed = [name for name, passed in checks.items() if not passed]
print(json.dumps({'checks': checks, 'failed': failed}, indent=2, sort_keys=True))
if failed:
    raise SystemExit('Public verification failed: ' + ', '.join(failed))
PY

if [[ -e "$PLUGIN_ROOT/$SU_SLUG" || -L "$PLUGIN_ROOT/$SU_SLUG" ]]; then
  mv "$PLUGIN_ROOT/$SU_SLUG" "$QUARANTINE_DIR/$SU_SLUG"
  su_moved="yes"
fi
if [[ -e "$PLUGIN_ROOT/$PDF_SLUG" || -L "$PLUGIN_ROOT/$PDF_SLUG" ]]; then
  mv "$PLUGIN_ROOT/$PDF_SLUG" "$QUARANTINE_DIR/$PDF_SLUG"
  pdf_moved="yes"
fi

{
  printf 'plugin,source,destination,sha256_manifest\n'
  for slug in "$SU_SLUG" "$PDF_SLUG"; do
    if [[ -d "$QUARANTINE_DIR/$slug" ]]; then
      manifest="$QUARANTINE_DIR/$slug-SHA256SUMS.txt"
      find "$QUARANTINE_DIR/$slug" -type f -print0 | sort -z | xargs -0 sha256sum > "$manifest"
      printf '%s,%s,%s,%s\n' "$slug" "$PLUGIN_ROOT/$slug" "$QUARANTINE_DIR/$slug" "$manifest"
    fi
  done
} > "$OUTPUT_DIR/quarantine-manifest.csv"

wp --path="$WP_PATH" rewrite flush --hard > "$OUTPUT_DIR/rewrite-flush-after-quarantine.txt" 2>&1 || true
wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush-after-quarantine.txt" 2>&1 || true
sleep 2

fetch_public donation-final "$DONATION_URL"
fetch_public palm-sunday-pdf-final "$PDF_URL"
fetch_public homepage-final "$HOME_URL"
fetch_public contact-final "$CONTACT_URL"
fetch_public directory-login-final "$DIRECTORY_LOGIN_URL"
fetch_public schedule-api-final "$SCHEDULE_API_URL"

python3 - "$OUTPUT_DIR" > "$OUTPUT_DIR/public-verification-final.json" <<'PY'
import json
import sys
from pathlib import Path

root = Path(sys.argv[1])
def read(name):
    return (root / name).read_text(encoding='utf-8', errors='replace')
def schedule_rows(payload):
    rows = payload.get('data', payload) if isinstance(payload, dict) else payload
    if isinstance(rows, dict):
        rows = rows.get('schedule', rows.get('items', []))
    return rows if isinstance(rows, list) else []

donation = read('donation-final.html')
event_content = read('palm-sunday-content-after.txt')
home = read('homepage-final.html')
contact = read('contact-final.html')
login = read('directory-login-final.html')
schedule = json.loads(read('schedule-api-final.html'))
checks = {
    'donation_native_marker_present': 'st-thekla-native-donation-options' in donation,
    'donation_su_shortcode_absent': '[su_' not in donation,
    'donation_paypal_button_present': 'KQXSC8WTH7TXJ' in donation,
    'event_pdf_shortcode_absent': '[pdf-embedder' not in event_content,
    'event_pdf_link_present': 'Palm-Sunday-2024-DRAFT-1.pdf' in event_content,
    'pdf_asset_nonempty': (root / 'palm-sunday-pdf-final.html').stat().st_size > 1000,
    'homepage_schedule_present': 'stc-weekly-schedule-table' in home and 'Holy Liturgy' in home,
    'contact_wpforms_present': 'wpforms' in contact.lower(),
    'directory_login_present': 'cd-wrap cd-login' in login and 'Member Login' in login,
    'schedule_api_has_six_rows': len(schedule_rows(schedule)) == 6,
}
failed = [name for name, passed in checks.items() if not passed]
print(json.dumps({'checks': checks, 'failed': failed}, indent=2, sort_keys=True))
if failed:
    raise SystemExit('Final public verification failed: ' + ', '.join(failed))
PY

wp --path="$WP_PATH" plugin list --fields=name,status,version,update,auto_update --format=csv > "$OUTPUT_DIR/plugins-after.csv"
rm -f "$OUTPUT_DIR/"*.html

{
  printf 'action=migrate-legacy-public-content\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'content_was_already_migrated=%s\n' "$already_migrated"
  printf 'donation_page_native=yes\n'
  printf 'palm_sunday_pdf_native_link=yes\n'
  printf 'shortcodes_ultimate_active=no\n'
  printf 'pdf_embedder_active=no\n'
  printf 'shortcodes_ultimate_quarantined=%s\n' "$su_moved"
  printf 'pdf_embedder_quarantined=%s\n' "$pdf_moved"
  printf 'quarantine_directory=%s\n' "$QUARANTINE_DIR"
  printf 'private_content_rollback_directory=%s\n' "$PRIVATE_DIR"
  printf 'public_verification=passed\n'
} > "$OUTPUT_DIR/summary.txt"

rollback_needed="no"
trap - ERR
chmod -R go-rwx "$OUTPUT_DIR" "$QUARANTINE_DIR"
printf 'Legacy public content migration complete: %s\n' "$OUTPUT_DIR"
