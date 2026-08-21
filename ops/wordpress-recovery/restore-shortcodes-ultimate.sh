#!/usr/bin/env bash
# Restore Shortcodes Ultimate only for the published donation instructions page
# and verify that raw [su_*] tags are no longer exposed publicly.
# Usage: bash restore-shortcodes-ultimate.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-shortcodes-ultimate}"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN="shortcodes-ultimate"
PAGE_ID="196"
PUBLIC_URL="https://www.sttheklachurch.org/get-the-most-out-of-your-donations/"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi
if ! wp --path="$WP_PATH" plugin is-installed "$PLUGIN" >/dev/null 2>&1; then
  echo "ERROR: Shortcodes Ultimate is not installed." >&2
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
wp --path="$WP_PATH" post get "$PAGE_ID" --fields=ID,post_type,post_status,post_title,post_modified_gmt --format=json > "$OUTPUT_DIR/page-before.json"

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
  printf 'Shortcodes Ultimate was already active.\n' > "$OUTPUT_DIR/activation.txt"
fi
wp --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null

ST_PAGE_ID="$PAGE_ID" wp --path="$WP_PATH" eval '
  $page_id = (int) getenv("ST_PAGE_ID");
  $content = (string) get_post_field("post_content", $page_id);
  $rendered = do_shortcode($content);
  $raw_absent = false === strpos($rendered, "[su_lightbox") && false === strpos($rendered, "[su_lightbox_content");
  $expected = false !== stripos($rendered, "QuickPay") && false !== stripos($rendered, "PayPal");
  echo wp_json_encode(array(
    "shortcode_lightbox_registered" => shortcode_exists("su_lightbox"),
    "shortcode_content_registered" => shortcode_exists("su_lightbox_content"),
    "rendered_bytes" => strlen($rendered),
    "raw_shortcode_absent" => $raw_absent,
    "expected_donation_text_present" => $expected,
  ), JSON_PRETTY_PRINT) . PHP_EOL;
  if (!$raw_absent || !$expected) { exit(3); }
' > "$OUTPUT_DIR/internal-render.json"

wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true

public_verified="no"
for attempt in 1 2 3; do
  test_url="${PUBLIC_URL}?restore_donation=1&attempt=${attempt}&ts=$(date +%s)"
  if curl -fsSL --max-time 45 \
      -H 'Cache-Control: no-cache, no-store, must-revalidate' \
      -H 'Pragma: no-cache' \
      "$test_url" > "$OUTPUT_DIR/donation-page.html" 2> "$OUTPUT_DIR/curl-stderr.txt"; then
    if ! grep -Fq '[su_lightbox' "$OUTPUT_DIR/donation-page.html" \
      && grep -qi 'QuickPay' "$OUTPUT_DIR/donation-page.html" \
      && grep -qi 'PayPal' "$OUTPUT_DIR/donation-page.html"; then
      public_verified="yes"
      break
    fi
  fi
  sleep 3
done

if [[ "$public_verified" != "yes" ]]; then
  echo "ERROR: Donation page still exposes raw Shortcodes Ultimate tags." >&2
  exit 1
fi

python3 - "$OUTPUT_DIR/donation-page.html" > "$OUTPUT_DIR/public-verification.json" <<'PY'
import json, sys
html = open(sys.argv[1], encoding='utf-8', errors='replace').read()
print(json.dumps({
    'raw_shortcode_absent': '[su_lightbox' not in html,
    'quickpay_present': 'quickpay' in html.lower(),
    'paypal_present': 'paypal' in html.lower(),
    'response_bytes': len(html.encode('utf-8')),
}, indent=2, sort_keys=True))
PY
rm -f "$OUTPUT_DIR/donation-page.html"

wp --path="$WP_PATH" plugin list --fields=name,status,version --format=csv > "$OUTPUT_DIR/plugins-after.csv"
{
  printf 'action=restore-shortcodes-ultimate\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'plugin=shortcodes-ultimate\n'
  printf 'page_id=%s\n' "$PAGE_ID"
  printf 'plugin_kept_active=yes\n'
  printf 'public_page_verified=yes\n'
  printf 'raw_shortcodes_absent=yes\n'
} > "$OUTPUT_DIR/summary.txt"

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Shortcodes Ultimate restoration complete: %s\n' "$OUTPUT_DIR"
