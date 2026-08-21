#!/usr/bin/env bash
# Remove ineffective duplicate constant definitions from wp-config.php and
# rotate oversized/private PHP logs after a verified recovery backup.
# Usage: bash repair-config-and-rotate-logs.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-config-logs}"
BACKUP_ROOT="${HOME}/stthekla-backups"
CONFIG="$WP_PATH/wp-config.php"

if [[ ! -f "$CONFIG" ]]; then
  echo "ERROR: wp-config.php not found at $CONFIG" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
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

mkdir -p "$OUTPUT_DIR/archive"
cp -p "$CONFIG" "$OUTPUT_DIR/archive/wp-config.php.before"

runtime_constants() {
  local destination="$1"
  wp --path="$WP_PATH" eval '
    $names = array("WP_CRON_LOCK_TIMEOUT", "AUTOSAVE_INTERVAL", "WP_POST_REVISIONS", "EMPTY_TRASH_DAYS");
    $result = array();
    foreach ( $names as $name ) {
        $result[$name] = array("defined" => defined($name), "value" => defined($name) ? constant($name) : null);
    }
    echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  ' > "$destination"
}

runtime_constants "$OUTPUT_DIR/runtime-before.json"

python3 - "$CONFIG" "$OUTPUT_DIR/removed-lines.txt" <<'PY'
import re
import sys
from pathlib import Path

config_path = Path(sys.argv[1])
removed_path = Path(sys.argv[2])
text = config_path.read_text(encoding='utf-8')
lines = text.splitlines(keepends=True)
expected = {
    'WP_CRON_LOCK_TIMEOUT': '120',
    'AUTOSAVE_INTERVAL': '300',
    'WP_POST_REVISIONS': '5',
    'EMPTY_TRASH_DAYS': '7',
}
pattern = re.compile(r"^\s*define\s*\(\s*['\"](?P<name>[A-Z0-9_]+)['\"]\s*,\s*(?P<value>.*?)\s*\)\s*;\s*(?://.*|#.*)?(?:\r?\n)?$")
found = {name: [] for name in expected}
for index, line in enumerate(lines):
    match = pattern.match(line)
    if not match:
        continue
    name = match.group('name')
    if name in expected:
        value = re.sub(r'\s+', '', match.group('value'))
        found[name].append((index, value, line.rstrip('\r\n')))

errors = []
for name, expected_value in expected.items():
    entries = found[name]
    if len(entries) != 1:
        errors.append(f'{name}: expected one direct define, found {len(entries)}')
    elif entries[0][1] != expected_value:
        errors.append(f'{name}: expected value {expected_value}, found {entries[0][1]}')
if errors:
    raise SystemExit('; '.join(errors))

remove_indices = {entries[0][0] for entries in found.values()}
removed_lines = [f'{index + 1}: {lines[index].rstrip()}' for index in sorted(remove_indices)]
new_lines = [line for index, line in enumerate(lines) if index not in remove_indices]

replacement = config_path.with_suffix('.php.recovery-new')
replacement.write_text(''.join(new_lines), encoding='utf-8')
removed_path.write_text('\n'.join(removed_lines) + '\n', encoding='utf-8')
PY

php -l "$CONFIG.recovery-new" > "$OUTPUT_DIR/wp-config-new-lint.txt" 2>&1
chmod --reference="$CONFIG" "$CONFIG.recovery-new"
chown --reference="$CONFIG" "$CONFIG.recovery-new" 2>/dev/null || true
mv "$CONFIG.recovery-new" "$CONFIG"

if ! wp --path="$WP_PATH" core version > "$OUTPUT_DIR/wordpress-after-config.txt" 2>&1; then
  cp -p "$OUTPUT_DIR/archive/wp-config.php.before" "$CONFIG"
  echo "ERROR: WordPress failed after wp-config update; original restored." >&2
  exit 1
fi
runtime_constants "$OUTPUT_DIR/runtime-after.json"

python3 - "$OUTPUT_DIR/runtime-before.json" "$OUTPUT_DIR/runtime-after.json" <<'PY'
import json
import sys
from pathlib import Path
before = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
after = json.loads(Path(sys.argv[2]).read_text(encoding='utf-8'))
if before != after:
    raise SystemExit(f'Effective constant values changed: before={before!r} after={after!r}')
PY

# Archive logs privately, then start a clean debug log while recovery testing continues.
printf 'source,archive,size_bytes,sha256\n' > "$OUTPUT_DIR/log-archive-manifest.csv"
for log_path in \
  "$WP_PATH/wp-content/debug.log" \
  "$WP_PATH/wp-admin/error_log" \
  "$WP_PATH/wp-admin/network/error_log" \
  "$WP_PATH/wp-admin/user/error_log"; do
  if [[ ! -f "$log_path" ]]; then
    continue
  fi
  relative="${log_path#${WP_PATH}/}"
  archive_name="$(printf '%s' "$relative" | tr '/' '_')"
  archive_path="$OUTPUT_DIR/archive/$archive_name"
  size_bytes="$(stat -c %s "$log_path")"
  sha256="$(sha256sum "$log_path" | awk '{print $1}')"
  mv "$log_path" "$archive_path"
  printf '"%s","%s",%s,%s\n' "$log_path" "$archive_path" "$size_bytes" "$sha256" >> "$OUTPUT_DIR/log-archive-manifest.csv"
done

: > "$WP_PATH/wp-content/debug.log"
chmod 600 "$WP_PATH/wp-content/debug.log"

# Exercise WordPress repeatedly and ensure the duplicate-constant warning does not return.
for _ in 1 2 3; do
  wp --path="$WP_PATH" core version >/dev/null
  wp --path="$WP_PATH" option get home >/dev/null
  wp --path="$WP_PATH" plugin list --format=count >/dev/null
done

if grep -Eqi 'Constant (WP_CRON_LOCK_TIMEOUT|AUTOSAVE_INTERVAL|WP_POST_REVISIONS|EMPTY_TRASH_DAYS) already defined' "$WP_PATH/wp-content/debug.log"; then
  cp -p "$OUTPUT_DIR/archive/wp-config.php.before" "$CONFIG"
  echo "ERROR: Duplicate constant warnings persisted; original wp-config restored." >&2
  exit 1
fi

sha256sum "$OUTPUT_DIR/archive/wp-config.php.before" > "$OUTPUT_DIR/archive-SHA256SUMS.txt"
find "$OUTPUT_DIR/archive" -type f ! -name 'wp-config.php.before' -print0 | sort -z | xargs -0 -r sha256sum >> "$OUTPUT_DIR/archive-SHA256SUMS.txt"

{
  printf 'action=repair-config-and-rotate-logs\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'effective_constants_unchanged=yes\n'
  printf 'duplicate_constant_warnings_after_test=no\n'
  printf 'debug_log_reset=%s\n' "$WP_PATH/wp-content/debug.log"
  printf 'private_archive=%s\n' "$OUTPUT_DIR/archive"
} > "$OUTPUT_DIR/summary.txt"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Config repair and log rotation complete: %s\n' "$OUTPUT_DIR"
