#!/usr/bin/env bash
# Produce a non-secret audit of selected wp-config constants and log files.
# Usage: bash config-log-audit.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-audits/$(date +%Y%m%d-%H%M%S)-config-log}"
CONFIG="$WP_PATH/wp-config.php"

if [[ ! -f "$CONFIG" ]]; then
  echo "ERROR: wp-config.php not found at $CONFIG" >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"

php -l "$CONFIG" > "$OUTPUT_DIR/wp-config-php-lint.txt" 2>&1

python3 - "$CONFIG" "$OUTPUT_DIR/selected-constant-occurrences.txt" > "$OUTPUT_DIR/selected-constants.json" <<'PY'
import json
import re
import sys
from pathlib import Path

path = Path(sys.argv[1])
occurrences_path = Path(sys.argv[2])
lines = path.read_text(encoding='utf-8', errors='replace').splitlines()
selected = {
    'WP_CRON_LOCK_TIMEOUT',
    'AUTOSAVE_INTERVAL',
    'WP_POST_REVISIONS',
    'EMPTY_TRASH_DAYS',
    'WP_DEBUG',
    'WP_DEBUG_LOG',
    'WP_DEBUG_DISPLAY',
    'WP_CACHE',
    'WP_MEMORY_LIMIT',
    'WP_MAX_MEMORY_LIMIT',
    'WP_ENVIRONMENT_TYPE',
}
pattern = re.compile(r"^\s*@?define\s*\(\s*['\"]([A-Z0-9_]+)['\"]\s*,\s*(.*?)\s*\)\s*;\s*(?://.*|#.*)?$")
report = {name: [] for name in sorted(selected)}
occurrences = []
for line_number, line in enumerate(lines, start=1):
    match = pattern.match(line)
    if match:
        name, value = match.groups()
        if name in selected:
            value = value.strip()
            if len(value) > 120:
                value = value[:120] + '…'
            report[name].append({'line': line_number, 'value': value})
    for name in sorted(selected):
        if name in line:
            safe_line = line.strip()
            if len(safe_line) > 240:
                safe_line = safe_line[:240] + '…'
            occurrences.append(f'{line_number}: {safe_line}')
            break
occurrences_path.write_text('\n'.join(occurrences) + ('\n' if occurrences else ''), encoding='utf-8')
print(json.dumps(report, indent=2, sort_keys=True))
PY

wp --path="$WP_PATH" eval '
$names = array(
    "WP_CRON_LOCK_TIMEOUT",
    "AUTOSAVE_INTERVAL",
    "WP_POST_REVISIONS",
    "EMPTY_TRASH_DAYS",
    "WP_DEBUG",
    "WP_DEBUG_LOG",
    "WP_DEBUG_DISPLAY",
    "WP_CACHE",
    "WP_MEMORY_LIMIT",
    "WP_MAX_MEMORY_LIMIT",
    "WP_ENVIRONMENT_TYPE",
);
$result = array();
foreach ( $names as $name ) {
    $result[$name] = array(
        "defined" => defined($name),
        "value" => defined($name) ? constant($name) : null,
    );
}
echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
' > "$OUTPUT_DIR/runtime-constants.json"

{
  printf 'path,size_bytes,modified_utc,permissions\n'
  for log_path in \
    "$WP_PATH/wp-content/debug.log" \
    "$WP_PATH/wp-admin/error_log" \
    "$WP_PATH/wp-admin/network/error_log" \
    "$WP_PATH/wp-admin/user/error_log"; do
    if [[ -e "$log_path" ]]; then
      printf '"%s",%s,"%s","%s"\n' \
        "$log_path" \
        "$(stat -c %s "$log_path")" \
        "$(date -u -d "@$(stat -c %Y "$log_path")" +%Y-%m-%dT%H:%M:%SZ)" \
        "$(stat -c %a "$log_path")"
    else
      printf '"%s",0,"","missing"\n' "$log_path"
    fi
  done
} > "$OUTPUT_DIR/log-files.csv"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Config/log audit complete: %s\n' "$OUTPUT_DIR"
