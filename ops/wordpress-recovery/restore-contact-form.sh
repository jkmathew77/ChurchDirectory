#!/usr/bin/env bash
# Activate WPForms Lite, create a controlled contact form, replace the inactive
# Jetpack contact shortcode, and verify the public page. All changes are backed
# up and rolled back if verification fails.
# Usage: bash restore-contact-form.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-contact-form}"
BACKUP_ROOT="${HOME}/stthekla-backups"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN="wpforms-lite"
PAGE_ID=7
PUBLIC_URL="https://www.sttheklachurch.org/contact-us/"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi
if ! wp --path="$WP_PATH" plugin is-installed "$PLUGIN" >/dev/null 2>&1; then
  echo "ERROR: WPForms Lite is not installed." >&2
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

mkdir -p "$OUTPUT_DIR/private"

ADMIN_ID="$(wp --path="$WP_PATH" user list --role=administrator --field=ID --format=ids | awk '{print $1}')"
if [[ -z "$ADMIN_ID" ]]; then
  echo "ERROR: No WordPress administrator was found." >&2
  exit 1
fi

was_active="no"
if wp --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null 2>&1; then
  was_active="yes"
fi
printf '%s\n' "$was_active" > "$OUTPUT_DIR/plugin-active-before.txt"

rollback() {
  local form_id="${1:-}"
  local form_created="${2:-false}"

  if [[ -f "$OUTPUT_DIR/private/contact-page-before.html" ]]; then
    ST_RESTORE_PAGE_ID="$PAGE_ID" \
    ST_RESTORE_PAGE_FILE="$OUTPUT_DIR/private/contact-page-before.html" \
      wp --path="$WP_PATH" --user="$ADMIN_ID" eval '
        $page_id = (int) getenv("ST_RESTORE_PAGE_ID");
        $file = (string) getenv("ST_RESTORE_PAGE_FILE");
        $content = is_file($file) ? file_get_contents($file) : false;
        if ( false !== $content ) {
            wp_update_post(array("ID" => $page_id, "post_content" => $content));
            clean_post_cache($page_id);
        }
      ' >/dev/null 2>&1 || true
  fi

  if [[ "$form_created" == "true" && "$form_id" =~ ^[0-9]+$ ]]; then
    wp --path="$WP_PATH" --user="$ADMIN_ID" post delete "$form_id" --force >/dev/null 2>&1 || true
  fi

  if [[ "$was_active" != "yes" ]]; then
    wp --path="$WP_PATH" plugin deactivate "$PLUGIN" >/dev/null 2>&1 || true
  fi

  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
}

activation_result="success"
if [[ "$was_active" != "yes" ]]; then
  if ! wp --path="$WP_PATH" plugin activate "$PLUGIN" > "$OUTPUT_DIR/activation.txt" 2>&1; then
    activation_result="failed"
  fi
else
  printf 'WPForms Lite was already active.\n' > "$OUTPUT_DIR/activation.txt"
fi

if [[ "$activation_result" != "success" ]] || ! wp --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null 2>&1; then
  rollback "" "false"
  echo "ERROR: WPForms Lite could not be activated." >&2
  exit 1
fi

set +e
ST_CONTACT_PAGE_ID="$PAGE_ID" \
ST_CONTACT_BACKUP_FILE="$OUTPUT_DIR/private/contact-page-before.html" \
  wp --path="$WP_PATH" --user="$ADMIN_ID" eval-file "$SCRIPT_DIR/restore-contact-form.php" \
  > "$OUTPUT_DIR/contact-form-result.json" \
  2> "$OUTPUT_DIR/contact-form-stderr.txt"
php_exit=$?
set -e

form_id=""
form_created="false"
if [[ -s "$OUTPUT_DIR/contact-form-result.json" ]]; then
  form_id="$(python3 - "$OUTPUT_DIR/contact-form-result.json" <<'PY'
import json, sys
try:
    data = json.load(open(sys.argv[1], encoding='utf-8'))
    print(data.get('form_id', ''))
except Exception:
    print('')
PY
)"
  form_created="$(python3 - "$OUTPUT_DIR/contact-form-result.json" <<'PY'
import json, sys
try:
    data = json.load(open(sys.argv[1], encoding='utf-8'))
    print('true' if data.get('form_created') else 'false')
except Exception:
    print('false')
PY
)"
fi

if [[ $php_exit -ne 0 || ! "$form_id" =~ ^[0-9]+$ ]]; then
  rollback "$form_id" "$form_created"
  echo "ERROR: Contact form creation or page migration failed." >&2
  exit 1
fi

wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true

public_verified="no"
for attempt in 1 2 3; do
  test_url="${PUBLIC_URL}?recovery_form=${form_id}&attempt=${attempt}&ts=$(date +%s)"
  if curl -fsSL --max-time 45 \
      -H 'Cache-Control: no-cache, no-store, must-revalidate' \
      -H 'Pragma: no-cache' \
      "$test_url" > "$OUTPUT_DIR/public-contact-page.html" 2> "$OUTPUT_DIR/curl-stderr.txt"; then
    if grep -q "wpforms-form-${form_id}" "$OUTPUT_DIR/public-contact-page.html" \
      && ! grep -q '\[contact-form' "$OUTPUT_DIR/public-contact-page.html"; then
      public_verified="yes"
      break
    fi
  fi
  sleep 3
done

if [[ "$public_verified" != "yes" ]]; then
  rollback "$form_id" "$form_created"
  echo "ERROR: The public Contact Us page did not show the new form; changes were rolled back." >&2
  exit 1
fi

# Store only a short structural verification, not the full public response.
python3 - "$OUTPUT_DIR/public-contact-page.html" "$form_id" > "$OUTPUT_DIR/public-verification.json" <<'PY'
import json, re, sys
path, form_id = sys.argv[1], sys.argv[2]
html = open(path, encoding='utf-8', errors='replace').read()
report = {
    'form_id': int(form_id),
    'wpforms_markup_present': f'wpforms-form-{form_id}' in html,
    'legacy_contact_shortcode_absent': '[contact-form' not in html,
    'name_field_present': bool(re.search(r'Name', html, re.I)),
    'email_field_present': bool(re.search(r'Email', html, re.I)),
    'message_field_present': bool(re.search(r'Comment or Message', html, re.I)),
    'response_bytes': len(html.encode('utf-8')),
}
print(json.dumps(report, indent=2, sort_keys=True))
PY
rm -f "$OUTPUT_DIR/public-contact-page.html"

wp --path="$WP_PATH" plugin list --fields=name,status,version --format=csv > "$OUTPUT_DIR/plugins-after.csv"
wp --path="$WP_PATH" post get "$PAGE_ID" --fields=ID,post_title,post_status,post_modified_gmt --format=json > "$OUTPUT_DIR/contact-page-state.json"

{
  printf 'action=restore-contact-form\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'wpforms_activation=%s\n' "$activation_result"
  printf 'wpforms_kept_active=yes\n'
  printf 'contact_form_id=%s\n' "$form_id"
  printf 'public_page_verified=yes\n'
  printf 'legacy_jetpack_form_replaced=yes\n'
} > "$OUTPUT_DIR/summary.txt"

sha256sum "$OUTPUT_DIR/private/contact-page-before.html" > "$OUTPUT_DIR/private/SHA256SUMS.txt"
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Contact form restoration complete: %s\n' "$OUTPUT_DIR"
