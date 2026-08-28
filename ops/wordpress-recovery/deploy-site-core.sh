#!/usr/bin/env bash
# Operational launcher for the reviewed Site Core 0.3.2 deployment. It reuses
# the backup-gated 0.3.1 deployment harness, pins the exact merged 0.3.2 source,
# adds the direct-image centering assertion, and removes only Bluehost's
# bot-blocked direct CSS curl probe. Browser verification runs after deployment.
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-site-core-0.3.2}"
REPOSITORY="https://github.com/jkmathew77/ChurchDirectory.git"
BASE_COMMIT="d8a2abf275e95232add6649b11717e9bf3588457"
SOURCE_COMMIT="5bb59c041e24bd0b28a47afa18bf576ff3734f36"
WORK_DIR="$OUTPUT_DIR/private/site-core-deployment-runner"
BASE_SCRIPT="$WORK_DIR/ops/wordpress-recovery/deploy-site-core.sh"
PATCHED_SCRIPT="$OUTPUT_DIR/private/deploy-site-core-patched.sh"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
for command_name in git python3 bash; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "ERROR: Required command is unavailable: $command_name" >&2
    exit 1
  fi
done

mkdir -p "$OUTPUT_DIR/private"
rm -rf "$WORK_DIR"
git clone --quiet --no-checkout --filter=blob:none "$REPOSITORY" "$WORK_DIR"
git -C "$WORK_DIR" fetch --quiet --depth=1 origin "$BASE_COMMIT"
git -C "$WORK_DIR" checkout --quiet "$BASE_COMMIT" -- ops/wordpress-recovery/deploy-site-core.sh
resolved_commit="$(git -C "$WORK_DIR" rev-parse FETCH_HEAD)"
if [[ "$resolved_commit" != "$BASE_COMMIT" ]]; then
  echo "ERROR: Base deployment commit verification failed." >&2
  exit 1
fi

python3 - "$BASE_SCRIPT" "$PATCHED_SCRIPT" "$SOURCE_COMMIT" <<'PY'
from pathlib import Path
import sys

source = Path(sys.argv[1]).read_text(encoding='utf-8')
release_commit = sys.argv[3]

old_source_commit = '9e4e0df5ab16b21ec798fca39d2ea6f2e0b846c0'
if source.count(old_source_commit) != 1:
    raise SystemExit('Expected one Site Core 0.3.1 source commit reference.')
source = source.replace(old_source_commit, release_commit)

if '0.3.1' not in source:
    raise SystemExit('Expected Site Core 0.3.1 release markers in base deployment script.')
source = source.replace('0.3.1', '0.3.2')

old_margin_check = "grep -Fq 'margin-inline: auto;' \"$NEW_DIR/assets/css/public.css\"\n"
new_margin_check = (
    old_margin_check
    + "grep -Fq '.stc-visit-us-compact .stc-visit-image' \"$NEW_DIR/assets/css/public.css\"\n"
    + "grep -Fq 'width: auto;' \"$NEW_DIR/assets/css/public.css\"\n"
)
if source.count(old_margin_check) != 1:
    raise SystemExit('Expected one existing centering margin assertion.')
source = source.replace(old_margin_check, new_margin_check)

replacements = [
    (
        'curl "${curl_common[@]}" "https://www.sttheklachurch.org/wp-content/plugins/st-thekla-site-core/assets/css/public.css?ver=${EXPECTED_VERSION}&site_core_patch=${stamp}" > "$OUTPUT_DIR/public.css" 2> "$OUTPUT_DIR/public-css-curl-stderr.txt"\n',
        '',
    ),
    ("css = read('public.css')\n", ''),
    (
        "css_checks = {\n"
        "    'compact_single_column_rule': '.stc-visit-us-compact .stc-visit-primary' in css and 'grid-template-columns: minmax(0, 1fr);' in css,\n"
        "    'compact_center_rule': '.stc-visit-us-compact .stc-visit-image-wrap' in css and 'justify-self: center;' in css and 'margin-inline: auto;' in css,\n"
        "    'empty_tel_defense': '.stc-location a[href=\"tel:\"]' in css,\n"
        "}\n",
        '',
    ),
    (
        "css_report = {'checks': css_checks, 'failed': [k for k, v in css_checks.items() if not v]}\n",
        '',
    ),
    (
        "(root / 'public-css-verification.json').write_text(json.dumps(css_report, indent=2, sort_keys=True) + '\\n', encoding='utf-8')\n",
        '',
    ),
    (
        "failed = homepage_report['failed'] + css_report['failed'] + api_report['failed']\n",
        "failed = homepage_report['failed'] + api_report['failed']\n",
    ),
    ('  "$OUTPUT_DIR/public.css"\n', ''),
]

for old, new in replacements:
    count = source.count(old)
    if count != 1:
        raise SystemExit(f'Expected one deployment verifier fragment, found {count}: {old[:90]!r}')
    source = source.replace(old, new)

for forbidden in (
    '$OUTPUT_DIR/public.css',
    'public-css-curl-stderr',
    "css = read('public.css')",
    'css_report',
    'css_checks',
):
    if forbidden in source:
        raise SystemExit(f'Direct CSS probe reference remained after patching: {forbidden}')

required = (
    release_commit,
    "EXPECTED_VERSION=\"0.3.2\"",
    "STC_VERSION === \"0.3.2\"",
    ".stc-visit-us-compact .stc-visit-image",
    "grep -Fq 'width: auto;'",
    "'versioned_css_requested'",
    'compact_single_column_css',
    'compact_center_css',
)
for marker in required:
    if marker not in source:
        raise SystemExit(f'Required 0.3.2 deployment safeguard missing: {marker}')

Path(sys.argv[2]).write_text(source, encoding='utf-8')
PY

chmod 700 "$PATCHED_SCRIPT"
exec bash "$PATCHED_SCRIPT" "$WP_PATH" "$OUTPUT_DIR"
