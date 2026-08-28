#!/usr/bin/env bash
# Create a private, timestamped WordPress backup before recovery work.
# Usage: bash backup-before-recovery.sh /home3/stthekla/public_html
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: wp-config.php not found at $WP_PATH" >&2
  exit 1
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required but was not found in PATH." >&2
  exit 1
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_ROOT="${HOME}/stthekla-backups"
BACKUP_DIR="${BACKUP_ROOT}/${STAMP}"
mkdir -p "$BACKUP_DIR"

printf 'Creating backup in %s\n' "$BACKUP_DIR"

wp --path="$WP_PATH" core version > "$BACKUP_DIR/wordpress-version.txt"
wp --path="$WP_PATH" plugin list --format=csv > "$BACKUP_DIR/plugins.csv"
wp --path="$WP_PATH" theme list --format=csv > "$BACKUP_DIR/themes.csv"
wp --path="$WP_PATH" option get siteurl > "$BACKUP_DIR/siteurl.txt"
wp --path="$WP_PATH" option get home > "$BACKUP_DIR/home-url.txt"
wp --path="$WP_PATH" db export "$BACKUP_DIR/database.sql" --add-drop-table

tar \
  --exclude='wp-content/cache' \
  --exclude='wp-content/upgrade' \
  --exclude='wp-content/debug.log' \
  -czf "$BACKUP_DIR/site-files.tar.gz" \
  -C "$WP_PATH" \
  wp-content wp-config.php .htaccess 2>/dev/null || {
    echo "WARNING: .htaccess may not exist; retrying without it." >&2
    tar \
      --exclude='wp-content/cache' \
      --exclude='wp-content/upgrade' \
      --exclude='wp-content/debug.log' \
      -czf "$BACKUP_DIR/site-files.tar.gz" \
      -C "$WP_PATH" \
      wp-content wp-config.php
  }

if [[ -f "$WP_PATH/wp-content/debug.log" ]]; then
  cp "$WP_PATH/wp-content/debug.log" "$BACKUP_DIR/debug.log"
fi

(
  cd "$BACKUP_DIR"
  sha256sum database.sql site-files.tar.gz > SHA256SUMS
)

chmod -R go-rwx "$BACKUP_DIR"

printf '\nBackup complete.\n'
printf 'Location: %s\n' "$BACKUP_DIR"
printf 'Database: %s\n' "$BACKUP_DIR/database.sql"
printf 'Files: %s\n' "$BACKUP_DIR/site-files.tar.gz"
printf 'Checksums: %s\n' "$BACKUP_DIR/SHA256SUMS"
printf '\nDo not move this backup into public_html.\n'
