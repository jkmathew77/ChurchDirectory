#!/usr/bin/env bash
# Exercise authenticated, read-only Community Directory REST paths using existing
# linked member and administrator accounts. No credentials or PII are exported.
# Usage: bash authenticated-directory-smoke.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-audits/$(date +%Y%m%d-%H%M%S)-directory-authenticated-smoke}"
BACKUP_ROOT="${HOME}/stthekla-backups"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
MANIFEST_URL="https://www.sttheklachurch.org/community/manifest.json"
SERVICE_WORKER_URL="https://www.sttheklachurch.org/community/cd-sw.js"
LOGIN_URL="https://www.sttheklachurch.org/community/login/"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi
for command_name in curl python3 sha256sum; do
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
) >/dev/null

mkdir -p "$OUTPUT_DIR"
wp --path="$WP_PATH" plugin get community-directory --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-state.json"
wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/authenticated-directory-smoke.php" > "$OUTPUT_DIR/authenticated-rest-smoke.json"

pwa_enabled="$(python3 - "$OUTPUT_DIR/authenticated-rest-smoke.json" <<'PY'
import json
import sys
from pathlib import Path
report = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
print(report.get('pwa', {}).get('enabled_option', '0'))
PY
)"

login_status="$(curl -sS -L --max-time 60 \
  -o "$OUTPUT_DIR/login-page.html" \
  -w '%{http_code}' \
  -H 'Cache-Control: no-cache, no-store, must-revalidate' \
  -H 'Pragma: no-cache' \
  "${LOGIN_URL}?authenticated_smoke=1&ts=$(date +%s%N)" \
  2> "$OUTPUT_DIR/login-curl-stderr.txt")"
if [[ "$login_status" != "200" ]] \
  || ! grep -q 'cd-wrap cd-login' "$OUTPUT_DIR/login-page.html" \
  || ! grep -q 'Member Login' "$OUTPUT_DIR/login-page.html"; then
  echo "ERROR: Community Directory public login page verification failed." >&2
  exit 1
fi

if [[ "$pwa_enabled" == "1" ]]; then
  manifest_status="$(curl -sS -L --max-time 60 \
    -o "$OUTPUT_DIR/manifest.json" \
    -w '%{http_code}' \
    -H 'Cache-Control: no-cache, no-store, must-revalidate' \
    "${MANIFEST_URL}?authenticated_smoke=1&ts=$(date +%s%N)" \
    2> "$OUTPUT_DIR/manifest-curl-stderr.txt")"
  sw_status="$(curl -sS -L --max-time 60 \
    -o "$OUTPUT_DIR/service-worker.js" \
    -w '%{http_code}' \
    -H 'Cache-Control: no-cache, no-store, must-revalidate' \
    "${SERVICE_WORKER_URL}?authenticated_smoke=1&ts=$(date +%s%N)" \
    2> "$OUTPUT_DIR/service-worker-curl-stderr.txt")"

  python3 - "$OUTPUT_DIR/manifest.json" "$OUTPUT_DIR/service-worker.js" "$manifest_status" "$sw_status" > "$OUTPUT_DIR/pwa-public-verification.json" <<'PY'
import json
import sys
from pathlib import Path

manifest = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
service_worker = Path(sys.argv[2]).read_text(encoding='utf-8', errors='replace')
manifest_status = int(sys.argv[3])
sw_status = int(sys.argv[4])
checks = {
    'manifest_http_200': manifest_status == 200,
    'service_worker_http_200': sw_status == 200,
    'manifest_name_present': bool(manifest.get('name')),
    'manifest_start_url_correct': manifest.get('start_url') == '/community/',
    'manifest_scope_correct': manifest.get('scope') == '/community/',
    'service_worker_install_handler_present': "addEventListener('install'" in service_worker or 'addEventListener("install"' in service_worker,
    'service_worker_fetch_handler_present': "addEventListener('fetch'" in service_worker or 'addEventListener("fetch"' in service_worker,
}
failed = [name for name, ok in checks.items() if not ok]
print(json.dumps({'enabled': True, 'checks': checks, 'failed': failed}, indent=2, sort_keys=True))
if failed:
    raise SystemExit('PWA public verification failed: ' + ', '.join(failed))
PY
else
  printf '{\n  "enabled": false,\n  "note": "cd_pwa_enabled is currently disabled; manifest and service worker were not expected to be public."\n}\n' > "$OUTPUT_DIR/pwa-public-verification.json"
fi

python3 - "$OUTPUT_DIR/authenticated-rest-smoke.json" "$OUTPUT_DIR/pwa-public-verification.json" > "$OUTPUT_DIR/combined-verification.json" <<'PY'
import json
import sys
from pathlib import Path
rest = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
pwa = json.loads(Path(sys.argv[2]).read_text(encoding='utf-8'))
combined = {
    'authenticated_rest_success': rest.get('success') is True,
    'member_candidate_found': rest.get('member_context', {}).get('candidate_found') is True,
    'admin_candidate_found': rest.get('admin_context', {}).get('candidate_found') is True,
    'google_oauth_auth_url_valid': rest.get('google_oauth', {}).get('auth_url_valid') is True,
    'google_oauth_secret_decrypts': rest.get('google_oauth', {}).get('secret_decrypts') is True,
    'custom_table_counts_unchanged': rest.get('data_preservation', {}).get('custom_table_counts_unchanged') is True,
    'pwa_enabled': pwa.get('enabled') is True,
    'pwa_public_checks_passed': not pwa.get('failed', []),
}
print(json.dumps(combined, indent=2, sort_keys=True))
required = [
    'authenticated_rest_success',
    'member_candidate_found',
    'admin_candidate_found',
    'google_oauth_auth_url_valid',
    'google_oauth_secret_decrypts',
    'custom_table_counts_unchanged',
    'pwa_public_checks_passed',
]
failed = [name for name in required if not combined.get(name)]
if failed:
    raise SystemExit('Authenticated directory smoke failed: ' + ', '.join(failed))
PY

rm -f "$OUTPUT_DIR/login-page.html" "$OUTPUT_DIR/manifest.json" "$OUTPUT_DIR/service-worker.js"

{
  printf 'action=authenticated-directory-smoke\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'authenticated_member_rest=passed\n'
  printf 'authenticated_admin_rest=passed\n'
  printf 'google_oauth_configuration=passed\n'
  printf 'custom_directory_table_counts_unchanged=yes\n'
  printf 'public_login_page=passed\n'
  printf 'pwa_enabled=%s\n' "$pwa_enabled"
} > "$OUTPUT_DIR/summary.txt"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Authenticated Community Directory smoke complete: %s\n' "$OUTPUT_DIR"
