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
chmod 700 \
  "$SCRIPT_DIR/deploy-sparkill-move-v4.sh" \
  "$SCRIPT_DIR/deploy-sparkill-move.sh"

exec bash "$SCRIPT_DIR/deploy-sparkill-move-v4.sh" "$WP_PATH" "$OUTPUT_DIR"
