#!/usr/bin/env bash
# Run the production mode dispatcher, substituting the corrected Community
# Directory deployment wrapper only for deploy-directory mode.
set -euo pipefail
umask 077

MODE="${1:?mode is required}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [[ "$MODE" != "deploy-directory" ]]; then
  exec bash "$SCRIPT_DIR/run-production-mode.sh" "$@"
fi

PATCHED_DISPATCH="$(mktemp "${TMPDIR:-/tmp}/run-production-mode.XXXXXX.sh")"
cleanup() {
  rm -f "$PATCHED_DISPATCH"
}
trap cleanup EXIT

sed 's#bash "$SCRIPT_DIR/deploy-community-directory.sh"#bash "$SCRIPT_DIR/deploy-community-directory-v2.sh"#' \
  "$SCRIPT_DIR/run-production-mode.sh" > "$PATCHED_DISPATCH"
chmod 700 "$PATCHED_DISPATCH"
bash "$PATCHED_DISPATCH" "$@"
