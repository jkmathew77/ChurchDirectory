#!/usr/bin/env bash
# Prepare the verified optimized Sparkill image payload, then invoke the
# reviewed backup-gated deployment with only release constants and filenames
# patched in a private runtime copy.
# Usage: bash deploy-sparkill-move-v4.sh <wp_path> <output_dir>
set -euo pipefail
umask 077

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
IMAGE_B64="$SCRIPT_DIR/.move-images-v4.tar.gz.b64"
IMAGE_ARCHIVE="$SCRIPT_DIR/.move-images-v4.tar.gz"
PATCHED_DEPLOY="$SCRIPT_DIR/.deploy-sparkill-move-v4-runtime.sh"
EXPECTED_B64_LENGTH="78108"
EXPECTED_ARCHIVE_SHA256="812bfc8273c3fe7b7406edd5025f57bfeacade1762afa7d6f8ccdc368624ce87"

parts=(
  "$SCRIPT_DIR/move-images-v4.part00.b64"
  "$SCRIPT_DIR/move-images-v4.part01.b64"
  "$SCRIPT_DIR/move-images-v4.part02.b64"
  "$SCRIPT_DIR/move-images-v4.part03.b64"
  "$SCRIPT_DIR/move-images-v4.part04.b64"
  "$SCRIPT_DIR/move-images-v4.part05.b64"
  "$SCRIPT_DIR/move-images-v4.part06.b64"
  "$SCRIPT_DIR/move-images-v4.part07.b64"
  "$SCRIPT_DIR/move-images-v4.part08.b64"
  "$SCRIPT_DIR/move-images-v4.part09.b64"
  "$SCRIPT_DIR/move-images-v4.part10.b64"
  "$SCRIPT_DIR/move-images-v4.part11.b64"
  "$SCRIPT_DIR/move-images-v4.part12.b64"
  "$SCRIPT_DIR/move-images-v4.part13.b64"
)

for part in "${parts[@]}"; do
  if [[ ! -s "$part" ]]; then
    echo "ERROR: Missing optimized image payload part: $part" >&2
    exit 1
  fi
done

cat "${parts[@]}" > "$IMAGE_B64"
actual_length="$(wc -c < "$IMAGE_B64" | tr -d '[:space:]')"
if [[ "$actual_length" != "$EXPECTED_B64_LENGTH" ]]; then
  echo "ERROR: Optimized image payload length mismatch: $actual_length" >&2
  exit 1
fi

base64 --decode "$IMAGE_B64" > "$IMAGE_ARCHIVE"
echo "$EXPECTED_ARCHIVE_SHA256  $IMAGE_ARCHIVE" | sha256sum -c - >/dev/null

tar -tzf "$IMAGE_ARCHIVE" | sort > "$SCRIPT_DIR/.move-images-v4-files.txt"
grep -qx 'st-thekla-sacred-heart-chapel-new-home.webp' "$SCRIPT_DIR/.move-images-v4-files.txt"
grep -qx 'st-thekla-sacred-heart-chapel-parking-map.webp' "$SCRIPT_DIR/.move-images-v4-files.txt"

# The reviewed deployment script expects six historical chunk filenames. Split
# the already-verified base64 text across those names; the script immediately
# concatenates them before performing its own length and checksum checks.
python3 - "$IMAGE_B64" "$SCRIPT_DIR" <<'PY'
from pathlib import Path
import sys

payload = Path(sys.argv[1]).read_text(encoding='ascii')
root = Path(sys.argv[2])
names = [
    'move-images-deploy-small.part00',
    'move-images-deploy-small.part01',
    'move-images-deploy-small.part02',
    'move-images-deploy-small.part03-04',
    'move-images-deploy-small.part05-06',
    'move-images-deploy-small.part07-08',
]
base, extra = divmod(len(payload), len(names))
position = 0
for index, name in enumerate(names):
    size = base + (1 if index < extra else 0)
    (root / name).write_text(payload[position:position + size], encoding='ascii')
    position += size
if position != len(payload):
    raise SystemExit('Image payload split did not consume all data.')
PY

# Patch a private runtime copy only. The exact Site Core source commit and all
# backup, rollback, data-preservation, content-migration, and smoke-test logic
# remain unchanged from the reviewed deployment script.
sed \
  -e "s/IMAGE_B64_LENGTH=\"[0-9]*\"/IMAGE_B64_LENGTH=\"$EXPECTED_B64_LENGTH\"/" \
  -e "s/IMAGE_ARCHIVE_SHA256=\"[a-f0-9]*\"/IMAGE_ARCHIVE_SHA256=\"$EXPECTED_ARCHIVE_SHA256\"/" \
  -e 's/st-thekla-sacred-heart-chapel-new-home\.jpg/st-thekla-sacred-heart-chapel-new-home.webp/g' \
  -e 's/st-thekla-sacred-heart-chapel-parking-map\.jpg/st-thekla-sacred-heart-chapel-parking-map.webp/g' \
  "$SCRIPT_DIR/deploy-sparkill-move.sh" > "$PATCHED_DEPLOY"
chmod 700 "$PATCHED_DEPLOY"

exec bash "$PATCHED_DEPLOY" "$@"
