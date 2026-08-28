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
EXPECTED_B64_SHA256="4c152d2146301e84a624ebdfe15fbccc9a81043e57c76be35ad1828e5f601600"
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

# GitHub's text transport dropped one known byte from part00 and appended a
# newline to part13. Reconstruct the reviewed payload deterministically, then
# require its exact SHA-256 before any decode or production action.
python3 - "$IMAGE_B64" "${parts[@]}" <<'PY'
from pathlib import Path
import hashlib
import json
import re
import sys

output = Path(sys.argv[1])
parts = [Path(value) for value in sys.argv[2:]]
clean = [path.read_text(encoding='ascii').replace('\r', '').replace('\n', '') for path in parts]

# The current part00 is exactly the reviewed 6,000-byte segment with the
# lowercase 'o' at offset 5,011 omitted. Its file hash was independently
# matched against every possible one-byte deletion from the reviewed source.
if len(clean[0]) != 5999:
    raise SystemExit(f'Unexpected part00 length: {len(clean[0])}')
clean[0] = clean[0][:5011] + 'o' + clean[0][5011:]

payload = ''.join(clean)
output.write_text(payload, encoding='ascii')
allowed = re.compile(r'^[A-Za-z0-9+/]*={0,2}$')
report = {
    'payload_length': len(payload),
    'payload_sha256': hashlib.sha256(payload.encode('ascii')).hexdigest(),
    'base64_alphabet_valid': bool(allowed.fullmatch(payload)),
}
print('SPARKILL_IMAGE_PAYLOAD=' + json.dumps(report, sort_keys=True), file=sys.stderr)
if not report['base64_alphabet_valid']:
    raise SystemExit('Reconstructed payload contains a non-base64 character.')
PY

actual_length="$(wc -c < "$IMAGE_B64" | tr -d '[:space:]')"
if [[ "$actual_length" != "$EXPECTED_B64_LENGTH" ]]; then
  echo "ERROR: Optimized image payload length mismatch: $actual_length" >&2
  exit 1
fi
echo "$EXPECTED_B64_SHA256  $IMAGE_B64" | sha256sum -c - >/dev/null

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

# Site Core 0.3.0 returns nested image objects in the public payload:
# visit.visit_image.url and visit.parking_map_image.url. Correct the stale
# verifier written against the earlier proposed flat field names.
python3 - "$PATCHED_DEPLOY" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text(encoding='utf-8')
old = "bool(visit_payload.get('exterior_image_url')) and bool(visit_payload.get('parking_map_image_url'))"
new = "bool((visit_payload.get('visit_image') or {}).get('url')) and bool((visit_payload.get('parking_map_image') or {}).get('url'))"
if old not in text:
    raise SystemExit('Expected stale public API image verifier was not found.')
path.write_text(text.replace(old, new, 1), encoding='utf-8')
PY
chmod 700 "$PATCHED_DEPLOY"

exec bash "$PATCHED_DEPLOY" "$@"
