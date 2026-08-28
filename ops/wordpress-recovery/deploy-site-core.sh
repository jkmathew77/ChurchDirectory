#!/usr/bin/env bash
# Temporary operational launcher: run the checksum-verified, backup-gated
# Sparkill location release through the established Site Core SSH workflow.
# This file lives only on the non-mergeable production execution branch.
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-sparkill-release}"
REPOSITORY="https://github.com/jkmathew77/ChurchDirectory.git"
RELEASE_COMMIT="788c3c88284d5e73087611c1e5f09734661eed5a"
WORK_DIR="$OUTPUT_DIR/private/sparkill-release-source"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
for command_name in wp git php curl python3 base64 sha256sum tar; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "ERROR: Required command is unavailable: $command_name" >&2
    exit 1
  fi
done

mkdir -p "$OUTPUT_DIR/private"
rm -rf "$WORK_DIR"
git clone --quiet --no-checkout --filter=blob:none "$REPOSITORY" "$WORK_DIR"
git -C "$WORK_DIR" fetch --quiet --depth=1 origin "$RELEASE_COMMIT"
git -C "$WORK_DIR" checkout --quiet "$RELEASE_COMMIT" -- ops/wordpress-recovery
resolved_commit="$(git -C "$WORK_DIR" rev-parse FETCH_HEAD)"
if [[ "$resolved_commit" != "$RELEASE_COMMIT" ]]; then
  echo "ERROR: Operational release commit verification failed." >&2
  exit 1
fi

SCRIPT_DIR="$WORK_DIR/ops/wordpress-recovery"

# Site Core 0.3.0 exposes image data as nested attachment payloads. The original
# operational verifier looked for two superseded flat URL fields, causing a
# false-negative after every internal and public-page check had passed. Patch
# only that assertion in the private checkout and require one exact replacement.
python3 - "$SCRIPT_DIR/deploy-sparkill-move.sh" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text(encoding='utf-8')
old = "bool(visit_payload.get('exterior_image_url')) and bool(visit_payload.get('parking_map_image_url'))"
new = "bool((visit_payload.get('visit_image') or {}).get('url')) and bool((visit_payload.get('parking_map_image') or {}).get('url'))"
if text.count(old) != 1:
    raise SystemExit('Expected exactly one obsolete public API image assertion.')
path.write_text(text.replace(old, new), encoding='utf-8')
PY

grep -Fq "visit_payload.get('visit_image')" "$SCRIPT_DIR/deploy-sparkill-move.sh"
grep -Fq "visit_payload.get('parking_map_image')" "$SCRIPT_DIR/deploy-sparkill-move.sh"

chmod 700 \
  "$SCRIPT_DIR/deploy-sparkill-move-v4.sh" \
  "$SCRIPT_DIR/deploy-sparkill-move.sh"

exec bash "$SCRIPT_DIR/deploy-sparkill-move-v4.sh" "$WP_PATH" "$OUTPUT_DIR"
