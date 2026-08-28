#!/usr/bin/env bash
# One-time operational bridge: the registered production-recovery workflow
# invokes this path for the already-approved `migrate-legacy-content` mode.
# Route that invocation to the reviewed, backup-gated Sparkill move deployment.
# The original legacy-content migration is already complete and this bridge is
# restored after the location release finishes.
set -euo pipefail
umask 077
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
exec bash "$SCRIPT_DIR/deploy-sparkill-move-v4.sh" "$@"
