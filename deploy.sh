#!/usr/bin/env bash
#
# The former development-symlink deployment path has been retired.
# Production uses versioned plugin directories, verified backups, staged
# releases, atomic swaps, and documented rollback checks.
#
set -euo pipefail

cat >&2 <<'EOF'
Direct deployment through deploy.sh is disabled.

The previous script could remove an installed WordPress plugin directory and
replace it with a development symlink. That workflow is no longer compatible
with the production safety model for St. Thekla Church.

Use the release packages produced by GitHub Actions and follow DEPLOY.md:

  1. Merge a reviewed pull request into main.
  2. Use the exact versioned CI artifact for the targeted plugin.
  3. Verify a full database/files backup outside public_html.
  4. Stage and lint the release.
  5. Preserve the prior plugin directory for rollback.
  6. Deploy one plugin at a time and run the documented verification suite.

No files were changed by this command.
EOF

exit 2
