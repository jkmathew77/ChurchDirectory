#!/usr/bin/env bash
# Entry point for the backup-gated Community Directory production deployment.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
exec bash "$SCRIPT_DIR/deploy-community-directory-v2.sh" "$@"
