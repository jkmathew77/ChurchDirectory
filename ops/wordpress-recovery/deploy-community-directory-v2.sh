#!/usr/bin/env bash
# Run the reviewed Community Directory deployment script from its exact commit,
# correcting the REST smoke-test route from /members to /directory.
set -euo pipefail
umask 077

SOURCE_URL="https://raw.githubusercontent.com/jkmathew77/ChurchDirectory/81da0bf07f947b7c52bcbd042f67c80350562b3c/ops/wordpress-recovery/deploy-community-directory.sh"
EXPECTED_GIT_BLOB="71525f2532eed43c88d9fd7c032948c66f94df11"
ORIGINAL="$(mktemp "${TMPDIR:-/tmp}/deploy-community-directory.original.XXXXXX.sh")"
PATCHED="$(mktemp "${TMPDIR:-/tmp}/deploy-community-directory.patched.XXXXXX.sh")"
cleanup() {
  rm -f "$ORIGINAL" "$PATCHED"
}
trap cleanup EXIT

curl -fsSL --max-time 45 "$SOURCE_URL" -o "$ORIGINAL"
actual_blob="$(git hash-object "$ORIGINAL")"
if [[ "$actual_blob" != "$EXPECTED_GIT_BLOB" ]]; then
  echo "ERROR: Pinned Community Directory deployment script failed integrity verification." >&2
  exit 1
fi

sed 's#"/community-directory/v1/members",#"/community-directory/v1/directory",#' \
  "$ORIGINAL" > "$PATCHED"
chmod 700 "$PATCHED"

bash "$PATCHED" "$@"
