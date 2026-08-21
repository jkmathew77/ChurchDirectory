#!/usr/bin/env bash
# Restore the WPForms contact form, normalize the current church address, and
# verify both on the public Contact Us page.
# Usage: bash restore-contact-and-address.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-contact-form}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PAGE_ID=7
PUBLIC_URL="https://www.sttheklachurch.org/contact-us/"

bash "$SCRIPT_DIR/restore-contact-form.sh" "$WP_PATH" "$OUTPUT_DIR"

ADMIN_ID="$(wp --path="$WP_PATH" user list --role=administrator --field=ID --format=ids | awk '{print $1}')"
if [[ -z "$ADMIN_ID" ]]; then
  echo "ERROR: No WordPress administrator was found for address normalization." >&2
  exit 1
fi

rollback_page() {
  if [[ -f "$OUTPUT_DIR/private/contact-page-before.html" ]]; then
    ST_RESTORE_PAGE_ID="$PAGE_ID" \
    ST_RESTORE_PAGE_FILE="$OUTPUT_DIR/private/contact-page-before.html" \
      wp --path="$WP_PATH" --user="$ADMIN_ID" eval '
        $page_id = (int) getenv("ST_RESTORE_PAGE_ID");
        $file = (string) getenv("ST_RESTORE_PAGE_FILE");
        $content = is_file($file) ? file_get_contents($file) : false;
        if (false !== $content) {
          wp_update_post(array("ID" => $page_id, "post_content" => $content));
          clean_post_cache($page_id);
        }
      ' >/dev/null 2>&1 || true
  fi
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
}
trap 'rollback_page' ERR

ST_CONTACT_PAGE_ID="$PAGE_ID" \
  wp --path="$WP_PATH" --user="$ADMIN_ID" eval-file "$SCRIPT_DIR/normalize-contact-page.php" \
  > "$OUTPUT_DIR/contact-address-result.json" \
  2> "$OUTPUT_DIR/contact-address-stderr.txt"

wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/address-cache-flush.txt" 2>&1 || true

public_verified="no"
for attempt in 1 2 3; do
  test_url="${PUBLIC_URL}?contact_address_recovery=1&attempt=${attempt}&ts=$(date +%s)"
  if curl -fsSL --max-time 45 \
      -H 'Cache-Control: no-cache, no-store, must-revalidate' \
      -H 'Pragma: no-cache' \
      "$test_url" > "$OUTPUT_DIR/public-contact-address.html" 2> "$OUTPUT_DIR/address-curl-stderr.txt"; then
    if grep -q 'wpforms-form-' "$OUTPUT_DIR/public-contact-address.html" \
      && grep -q '2 Old Ox Road' "$OUTPUT_DIR/public-contact-address.html" \
      && grep -q 'Nyack, NY 10960' "$OUTPUT_DIR/public-contact-address.html" \
      && ! grep -qi '107 Strawtown Road' "$OUTPUT_DIR/public-contact-address.html" \
      && ! grep -qi 'West Nyack' "$OUTPUT_DIR/public-contact-address.html"; then
      public_verified="yes"
      break
    fi
  fi
  sleep 3
done

if [[ "$public_verified" != "yes" ]]; then
  echo "ERROR: Contact form/address public verification failed." >&2
  exit 1
fi

python3 - "$OUTPUT_DIR/public-contact-address.html" > "$OUTPUT_DIR/contact-address-public-verification.json" <<'PY'
import json, sys
html = open(sys.argv[1], encoding='utf-8', errors='replace').read()
print(json.dumps({
    'wpforms_markup_present': 'wpforms-form-' in html,
    'current_address_present': '2 Old Ox Road' in html,
    'current_city_present': 'Nyack, NY 10960' in html,
    'legacy_address_absent': '107 strawtown road' not in html.lower(),
    'legacy_city_absent': 'west nyack' not in html.lower(),
    'response_bytes': len(html.encode('utf-8')),
}, indent=2, sort_keys=True))
PY
rm -f "$OUTPUT_DIR/public-contact-address.html"

cat >> "$OUTPUT_DIR/summary.txt" <<EOF
contact_address_updated=yes
current_address=2 Old Ox Road, Nyack, NY 10960
legacy_address_removed=yes
EOF

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Contact form and address restoration complete: %s\n' "$OUTPUT_DIR"
