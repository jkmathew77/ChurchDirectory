#!/usr/bin/env bash
# Operational launcher for the reviewed Site Core 0.3.3 deployment. It reuses
# the backup-gated 0.3.1 deployment harness, pins the exact merged 0.3.3 source,
# verifies the cache-independent inline centering safeguard, and removes only
# Bluehost's bot-blocked direct CSS curl probe.
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-site-core-0.3.3}"
REPOSITORY="https://github.com/jkmathew77/ChurchDirectory.git"
BASE_COMMIT="d8a2abf275e95232add6649b11717e9bf3588457"
SOURCE_COMMIT="3e2692c9df6744a3f176483e9fe87c83659d71be"
PATCH_ONLY="${STC_PATCH_ONLY:-0}"
WORK_DIR="$OUTPUT_DIR/private/site-core-deployment-runner"
BASE_SCRIPT="$WORK_DIR/ops/wordpress-recovery/deploy-site-core.sh"
PATCHED_SCRIPT="$OUTPUT_DIR/private/deploy-site-core-patched.sh"

if [[ "$PATCH_ONLY" != "1" && ! -f "$WP_PATH/wp-config.php" ]]; then
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
source = source.replace('0.3.1', '0.3.3')

old_margin_check = "grep -Fq 'margin-inline: auto;' \"$NEW_DIR/assets/css/public.css\"\n"
new_margin_check = (
    old_margin_check
    + "test -f \"$NEW_DIR/includes/class-stc-critical-styles.php\"\n"
    + "grep -Fq \"require_once STC_PLUGIN_DIR . 'includes/class-stc-critical-styles.php'\" \"$NEW_DIR/st-thekla-site-core.php\"\n"
    + "grep -Fq 'STC_Critical_Styles::init();' \"$NEW_DIR/st-thekla-site-core.php\"\n"
    + "grep -Fq 'stc-critical-inline-css' \"$NEW_DIR/includes/class-stc-critical-styles.php\"\n"
    + "grep -Fq '.stc-visit-us-compact .stc-visit-image' \"$NEW_DIR/includes/class-stc-critical-styles.php\"\n"
    + "grep -Fq 'margin-left: auto !important;' \"$NEW_DIR/includes/class-stc-critical-styles.php\"\n"
    + "grep -Fq 'margin-right: auto !important;' \"$NEW_DIR/includes/class-stc-critical-styles.php\"\n"
)
if source.count(old_margin_check) != 1:
    raise SystemExit('Expected one existing centering margin assertion.')
source = source.replace(old_margin_check, new_margin_check)

homepage_curl = (
    'curl "${curl_common[@]}" "${HOME_URL}?site_core_patch=${stamp}" '
    '> "$OUTPUT_DIR/homepage.html" 2> "$OUTPUT_DIR/homepage-curl-stderr.txt"\n'
)
homepage_inline_checks = (
    homepage_curl
    + "grep -Fq 'id=\"stc-critical-inline-css\"' \"$OUTPUT_DIR/homepage.html\"\n"
    + "grep -Fq '.stc-visit-us-compact .stc-visit-image' \"$OUTPUT_DIR/homepage.html\"\n"
    + "grep -Fq 'margin-left: auto !important;' \"$OUTPUT_DIR/homepage.html\"\n"
    + "grep -Fq 'margin-right: auto !important;' \"$OUTPUT_DIR/homepage.html\"\n"
)
if source.count(homepage_curl) != 1:
    raise SystemExit('Expected one public homepage curl verification step.')
source = source.replace(homepage_curl, homepage_inline_checks)

# The 0.3.3 inline safeguard intentionally contains a CSS selector with
# href="tel:". Verify actual empty anchor elements instead of scanning all raw
# HTML, which would incorrectly treat that selector as a broken telephone link.
old_empty_tel_check = (
    "    'empty_tel_link_absent': 'href=\"tel:\"' not in home and "
    "'href=\"tel:\"' not in contact,\n"
)
new_empty_tel_check = (
    "    'empty_tel_link_absent': "
    "re.search(r'<a\\b[^>]*\\bhref=\"tel:\\+?\"', home, flags=re.I) is None "
    "and re.search(r'<a\\b[^>]*\\bhref=\"tel:\\+?\"', contact, flags=re.I) is None,\n"
)
if source.count(old_empty_tel_check) != 1:
    raise SystemExit('Expected one raw empty telephone-link public check.')
source = source.replace(old_empty_tel_check, new_empty_tel_check)

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
    "'href=\"tel:\"' not in home",
):
    if forbidden in source:
        raise SystemExit(f'Obsolete deployment verifier reference remained after patching: {forbidden}')

required = (
    release_commit,
    "EXPECTED_VERSION=\"0.3.3\"",
    "STC_VERSION === \"0.3.3\"",
    'class-stc-critical-styles.php',
    'STC_Critical_Styles::init();',
    'id=\"stc-critical-inline-css\"',
    "grep -Fq 'margin-left: auto !important;'",
    "grep -Fq 'margin-right: auto !important;'",
    "re.search(r'<a\\b",
    "'versioned_css_requested'",
    'compact_single_column_css',
    'compact_center_css',
)
for marker in required:
    if marker not in source:
        raise SystemExit(f'Required 0.3.3 deployment safeguard missing: {marker}')

Path(sys.argv[2]).write_text(source, encoding='utf-8')
PY

chmod 700 "$PATCHED_SCRIPT"
bash -n "$PATCHED_SCRIPT"

if [[ "$PATCH_ONLY" == "1" ]]; then
  printf 'Site Core 0.3.3 deployment patch validation complete: %s\n' "$PATCHED_SCRIPT"
  exit 0
fi

exec bash "$PATCHED_SCRIPT" "$WP_PATH" "$OUTPUT_DIR"
