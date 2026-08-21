#!/usr/bin/env bash
# Disable WordPress production debugging after recovery validation while
# preserving a private, checksummed rollback copy of wp-config.php.
# Usage: bash disable-production-debug.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-disable-debug}"
BACKUP_ROOT="${HOME}/stthekla-backups"
CONFIG="$WP_PATH/wp-config.php"
HOME_URL="https://www.sttheklachurch.org/"
CONTACT_URL="https://www.sttheklachurch.org/contact-us/"
DONATION_URL="https://www.sttheklachurch.org/get-the-most-out-of-your-donations/"
DIRECTORY_LOGIN_URL="https://www.sttheklachurch.org/community/login/"
DIRECTORY_SESSION_URL="https://www.sttheklachurch.org/wp-json/community-directory/v1/auth/session-check"
SITE_CORE_API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/weekly-schedule"

if [[ ! -f "$CONFIG" ]]; then
  echo "ERROR: wp-config.php not found at $CONFIG" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi
for command_name in php python3 curl sha256sum; do
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

mkdir -p "$OUTPUT_DIR/private"
cp -p "$CONFIG" "$OUTPUT_DIR/private/wp-config.php.before"
sha256sum "$OUTPUT_DIR/private/wp-config.php.before" > "$OUTPUT_DIR/private/wp-config-before-SHA256SUMS.txt"

DEBUG_LOG="$WP_PATH/wp-content/debug.log"
if [[ -f "$DEBUG_LOG" ]]; then
  cp -p "$DEBUG_LOG" "$OUTPUT_DIR/private/debug.log.before"
  sha256sum "$OUTPUT_DIR/private/debug.log.before" > "$OUTPUT_DIR/private/debug-log-before-SHA256SUMS.txt"
  stat -c 'size_bytes=%s\npermissions=%a\nmodified_epoch=%Y' "$DEBUG_LOG" > "$OUTPUT_DIR/debug-log-before.txt"
else
  printf 'status=missing\n' > "$OUTPUT_DIR/debug-log-before.txt"
fi

capture_runtime() {
  local destination="$1"
  wp --path="$WP_PATH" eval '
    $names = array("WP_DEBUG", "WP_DEBUG_LOG", "WP_DEBUG_DISPLAY");
    $result = array();
    foreach ($names as $name) {
      $result[$name] = array(
        "defined" => defined($name),
        "value" => defined($name) ? constant($name) : null,
      );
    }
    echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  ' > "$destination"
}

capture_runtime "$OUTPUT_DIR/runtime-before.json"

rollback_needed="yes"
rollback() {
  if [[ "$rollback_needed" != "yes" ]]; then
    return
  fi
  set +e
  cp -p "$OUTPUT_DIR/private/wp-config.php.before" "$CONFIG"
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
  set -e
  echo "ERROR: Validation failed; the original wp-config.php was restored." >&2
}
trap rollback ERR

python3 - "$CONFIG" "$OUTPUT_DIR/config-changes.txt" <<'PY'
import re
import sys
from pathlib import Path

config_path = Path(sys.argv[1])
changes_path = Path(sys.argv[2])
text = config_path.read_text(encoding="utf-8")
lines = text.splitlines(keepends=True)

targets = {
    "WP_DEBUG": "false",
    "WP_DEBUG_LOG": "false",
    "WP_DEBUG_DISPLAY": "false",
}
pattern = re.compile(
    r"^(?P<indent>\s*)define\s*\(\s*(['\"])(?P<name>WP_DEBUG(?:_LOG|_DISPLAY)?)\2\s*,\s*(?P<value>true|false)\s*\)\s*;(?P<comment>\s*(?://.*|#.*)?)?(?P<newline>\r?\n)?$",
    re.IGNORECASE,
)

matches = {name: [] for name in targets}
for index, line in enumerate(lines):
    match = pattern.match(line)
    if match:
        name = match.group("name").upper()
        if name in targets:
            matches[name].append((index, match))

errors = []
for name, entries in matches.items():
    if len(entries) != 1:
        errors.append(f"{name}: expected exactly one direct boolean define, found {len(entries)}")
if errors:
    raise SystemExit("; ".join(errors))

changes = []
for name, entries in matches.items():
    index, match = entries[0]
    old_value = match.group("value").lower()
    newline = match.group("newline") or "\n"
    indent = match.group("indent")
    comment = match.group("comment") or ""
    lines[index] = f"{indent}define( '{name}', {targets[name]} );{comment}{newline}"
    changes.append(f"line={index + 1} constant={name} before={old_value} after={targets[name]}")

new_path = config_path.with_name(config_path.name + ".disable-debug-new")
new_path.write_text("".join(lines), encoding="utf-8")
changes_path.write_text("\n".join(changes) + "\n", encoding="utf-8")
PY

NEW_CONFIG="$CONFIG.disable-debug-new"
php -l "$NEW_CONFIG" > "$OUTPUT_DIR/wp-config-new-lint.txt" 2>&1
chmod --reference="$CONFIG" "$NEW_CONFIG"
chown --reference="$CONFIG" "$NEW_CONFIG" 2>/dev/null || true
mv "$NEW_CONFIG" "$CONFIG"

wp --path="$WP_PATH" core version > "$OUTPUT_DIR/wordpress-after.txt"
capture_runtime "$OUTPUT_DIR/runtime-after.json"

python3 - "$OUTPUT_DIR/runtime-after.json" <<'PY'
import json
import sys
from pathlib import Path

values = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
for name in ("WP_DEBUG", "WP_DEBUG_LOG", "WP_DEBUG_DISPLAY"):
    entry = values.get(name, {})
    if not entry.get("defined"):
        raise SystemExit(f"{name} is not defined after update")
    if entry.get("value") is not False:
        raise SystemExit(f"{name} is not false after update: {entry!r}")
PY

wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true

fetch_page() {
  local name="$1"
  local url="$2"
  local output="$OUTPUT_DIR/${name}.html"
  local status
  status="$(curl -sS -L --max-time 60 \
    -o "$output" \
    -w '%{http_code}' \
    -H 'Cache-Control: no-cache, no-store, must-revalidate' \
    -H 'Pragma: no-cache' \
    "${url}${url#*\?}" >/dev/null 2>&1 || true)"
  # The odd-looking URL construction above is intentionally replaced below;
  # retain a single canonical cache-busted request for deterministic output.
  status="$(curl -sS -L --max-time 60 \
    -o "$output" \
    -w '%{http_code}' \
    -H 'Cache-Control: no-cache, no-store, must-revalidate' \
    -H 'Pragma: no-cache' \
    "${url}$([[ "$url" == *\?* ]] && printf '&' || printf '?')disable_debug_check=1&ts=$(date +%s%N)" \
    2> "$OUTPUT_DIR/${name}-curl-stderr.txt")"
  printf '%s\n' "$status" > "$OUTPUT_DIR/${name}-http-status.txt"
  if [[ "$status" != "200" ]]; then
    echo "ERROR: $name returned HTTP $status." >&2
    return 1
  fi
}

fetch_page homepage "$HOME_URL"
fetch_page contact "$CONTACT_URL"
fetch_page donation "$DONATION_URL"
fetch_page directory-login "$DIRECTORY_LOGIN_URL"
fetch_page directory-session "$DIRECTORY_SESSION_URL"
fetch_page site-core-api "$SITE_CORE_API_URL"

python3 - "$OUTPUT_DIR" > "$OUTPUT_DIR/public-verification.json" <<'PY'
import json
import re
import sys
from pathlib import Path

root = Path(sys.argv[1])
def read(name):
    return (root / name).read_text(encoding="utf-8", errors="replace")

home = read("homepage.html")
contact = read("contact.html")
donation = read("donation.html")
login = read("directory-login.html")
session_text = read("directory-session.html")
schedule_text = read("site-core-api.html")

try:
    session = json.loads(session_text)
except json.JSONDecodeError as exc:
    raise SystemExit(f"Directory session endpoint did not return JSON: {exc}")
try:
    schedule = json.loads(schedule_text)
except json.JSONDecodeError as exc:
    raise SystemExit(f"Site Core schedule endpoint did not return JSON: {exc}")

schedule_rows = schedule.get("data", schedule)
if isinstance(schedule_rows, dict):
    schedule_rows = schedule_rows.get("schedule", schedule_rows.get("items", []))
if not isinstance(schedule_rows, list):
    raise SystemExit("Site Core schedule endpoint did not contain a list")

checks = {
    "homepage_raw_ninja_shortcode_absent": "[ninja_tables" not in home,
    "homepage_holy_liturgy_present": "Holy Liturgy" in home,
    "homepage_site_core_markup_present": bool(re.search(r"stc[-_]|st-thekla", home, re.I)),
    "contact_raw_jetpack_shortcode_absent": "[contact-form" not in contact and "[contact-field" not in contact,
    "contact_wpforms_present": "wpforms" in contact.lower(),
    "contact_current_address_present": "2 Old Ox Road" in contact and "Nyack" in contact,
    "contact_old_address_absent": "107 Strawtown Road" not in contact,
    "donation_raw_shortcode_absent": "[su_" not in donation,
    "donation_payment_content_present": "PayPal" in donation and "QuickPay" in donation,
    "directory_login_component_present": "cd-wrap cd-login" in login and "Member Login" in login,
    "directory_session_json_present": isinstance(session, dict),
    "site_core_schedule_has_six_or_more_rows": len(schedule_rows) >= 6,
}
failed = [name for name, ok in checks.items() if not ok]
result = {
    "checks": checks,
    "failed": failed,
    "schedule_rows": len(schedule_rows),
    "response_bytes": {
        "homepage": len(home.encode("utf-8")),
        "contact": len(contact.encode("utf-8")),
        "donation": len(donation.encode("utf-8")),
        "directory_login": len(login.encode("utf-8")),
    },
}
print(json.dumps(result, indent=2, sort_keys=True))
if failed:
    raise SystemExit("Public validation failed: " + ", ".join(failed))
PY

rm -f "$OUTPUT_DIR/"*.html

# Confirm that normal WordPress execution did not recreate a debug log after
# WP_DEBUG and WP_DEBUG_LOG were disabled.
for _ in 1 2 3; do
  wp --path="$WP_PATH" core version >/dev/null
  wp --path="$WP_PATH" plugin list --format=count >/dev/null
  wp --path="$WP_PATH" option get home >/dev/null
done

if [[ -f "$DEBUG_LOG" ]]; then
  stat -c 'status=present\nsize_bytes=%s\npermissions=%a\nmodified_epoch=%Y' "$DEBUG_LOG" > "$OUTPUT_DIR/debug-log-after.txt"
else
  printf 'status=absent\n' > "$OUTPUT_DIR/debug-log-after.txt"
fi

sha256sum "$CONFIG" > "$OUTPUT_DIR/wp-config-after-SHA256SUMS.txt"
{
  printf 'action=disable-production-debug\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'wp_debug=false\n'
  printf 'wp_debug_log=false\n'
  printf 'wp_debug_display=false\n'
  printf 'wp_config_php_lint=passed\n'
  printf 'wordpress_bootstrap=passed\n'
  printf 'public_site_verification=passed\n'
  printf 'community_directory_public_endpoints=passed\n'
  printf 'site_core_schedule_api=passed\n'
  printf 'private_rollback_config=%s\n' "$OUTPUT_DIR/private/wp-config.php.before"
} > "$OUTPUT_DIR/summary.txt"

rollback_needed="no"
trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Production debugging disabled and verified: %s\n' "$OUTPUT_DIR"
