#!/usr/bin/env bash
# Collect the newest non-PII Community Directory smoke report after a failed
# validation attempt. This script does not change WordPress or directory data.
# Usage: bash collect-directory-smoke-diagnostic.sh <output_dir>
set -euo pipefail
umask 077

OUTPUT_DIR="${1:-${HOME}/stthekla-audits/$(date +%Y%m%d-%H%M%S)-directory-smoke-diagnostic}"
AUDIT_ROOT="${HOME}/stthekla-audits"

LATEST_DIR="$(find "$AUDIT_ROOT" -mindepth 1 -maxdepth 1 -type d -name '*-directory-authenticated-smoke' -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_DIR" || ! -f "$LATEST_DIR/authenticated-rest-smoke.json" ]]; then
  echo "ERROR: No failed authenticated directory smoke report was found." >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"
cp "$LATEST_DIR/authenticated-rest-smoke.json" "$OUTPUT_DIR/authenticated-rest-smoke.json"
[[ -f "$LATEST_DIR/plugin-state.json" ]] && cp "$LATEST_DIR/plugin-state.json" "$OUTPUT_DIR/plugin-state.json"
if [[ -f "$LATEST_DIR/authenticated-rest-smoke-stderr.txt" ]]; then
  cp "$LATEST_DIR/authenticated-rest-smoke-stderr.txt" "$OUTPUT_DIR/authenticated-rest-smoke-stderr.txt"
else
  : > "$OUTPUT_DIR/authenticated-rest-smoke-stderr.txt"
fi

python3 - "$OUTPUT_DIR/authenticated-rest-smoke.json" > "$OUTPUT_DIR/diagnostic-summary.json" <<'PY'
import json
import sys
from pathlib import Path

report = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
summary = {
    'success': report.get('success'),
    'plugin': report.get('plugin'),
    'member_candidate_found': report.get('member_context', {}).get('candidate_found'),
    'member_route_tests': report.get('member_context', {}).get('tests', []),
    'admin_candidate_found': report.get('admin_context', {}).get('candidate_found'),
    'admin_route_tests': report.get('admin_context', {}).get('tests', []),
    'google_oauth': report.get('google_oauth'),
    'pwa': report.get('pwa'),
    'data_preservation': {
        'custom_table_counts_unchanged': report.get('data_preservation', {}).get('custom_table_counts_unchanged'),
    },
    'failed_route_tests': report.get('failed_route_tests', []),
}
print(json.dumps(summary, indent=2, sort_keys=True))
PY

{
  printf 'action=collect-directory-smoke-diagnostic\n'
  printf 'source_directory=%s\n' "$LATEST_DIR"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'wordpress_changes=none\n'
  printf 'contains_member_pii=no\n'
} > "$OUTPUT_DIR/summary.txt"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Directory smoke diagnostic collected: %s\n' "$OUTPUT_DIR"
