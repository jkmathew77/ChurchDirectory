#!/usr/bin/env bash
# Compatibility wrapper for the production deployment script. The member list
# endpoint is /directory; there is no bare /members route.
set -euo pipefail
umask 077

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PATCHED_SCRIPT="$(mktemp "${TMPDIR:-/tmp}/deploy-community-directory.XXXXXX.sh")"
cleanup() {
  rm -f "$PATCHED_SCRIPT"
}
trap cleanup EXIT

sed 's#"/community-directory/v1/members",#"/community-directory/v1/directory",#' \
  "$SCRIPT_DIR/deploy-community-directory.sh" > "$PATCHED_SCRIPT"
chmod 700 "$PATCHED_SCRIPT"

bash "$PATCHED_SCRIPT" "$@"
