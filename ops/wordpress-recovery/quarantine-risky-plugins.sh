#!/usr/bin/env bash
# Move known risky or duplicate inactive plugins outside public_html.
# Nothing is deleted; every moved directory can be restored.
# Usage: bash quarantine-risky-plugins.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-plugin-quarantine}"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN_ROOT="$WP_PATH/wp-content/plugins"
STAMP="$(date +%Y%m%d-%H%M%S)"
QUARANTINE_ROOT="${HOME}/stthekla-quarantine/${STAMP}/plugins"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: wp-config.php not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi

LATEST_BACKUP="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_BACKUP" || ! -f "$LATEST_BACKUP/SHA256SUMS" ]]; then
  echo "ERROR: No verified St. Thekla recovery backup was found." >&2
  exit 1
fi
backup_age=$(( $(date +%s) - $(stat -c %Y "$LATEST_BACKUP/SHA256SUMS") ))
if (( backup_age > 86400 )); then
  echo "ERROR: Latest backup is older than 24 hours: $LATEST_BACKUP" >&2
  exit 1
fi
(
  cd "$LATEST_BACKUP"
  sha256sum -c SHA256SUMS
) > /dev/null

mkdir -p "$OUTPUT_DIR" "$QUARANTINE_ROOT"

# directory|WordPress plugin name
TARGETS=(
  "wp-file-manager|wp-file-manager"
  "vc-tabs|vc-tabs"
  "community-directorydd|community-directorydd"
  "community-directory-0.4.4|community-directory-0.4.4/community-directory"
  "community-directory-v0.4.4|community-directory-v0.4.4/community-directory"
)

printf 'directory,plugin_name,status,source,destination,bytes\n' > "$OUTPUT_DIR/manifest.csv"

for entry in "${TARGETS[@]}"; do
  directory="${entry%%|*}"
  plugin_name="${entry#*|}"
  source="$PLUGIN_ROOT/$directory"
  destination="$QUARANTINE_ROOT/$directory"

  if [[ ! -e "$source" && ! -L "$source" ]]; then
    printf '"%s","%s",missing,"%s","",0\n' "$directory" "$plugin_name" "$source" >> "$OUTPUT_DIR/manifest.csv"
    continue
  fi

  if wp --path="$WP_PATH" plugin is-active "$plugin_name" >/dev/null 2>&1; then
    echo "ERROR: Refusing to quarantine active plugin: $plugin_name" >&2
    exit 1
  fi

  if [[ -e "$destination" || -L "$destination" ]]; then
    echo "ERROR: Quarantine destination already exists: $destination" >&2
    exit 1
  fi

  bytes="$(du -sb "$source" | awk '{print $1}')"
  mv "$source" "$destination"
  printf '"%s","%s",moved,"%s","%s",%s\n' \
    "$directory" "$plugin_name" "$source" "$destination" "$bytes" \
    >> "$OUTPUT_DIR/manifest.csv"
done

if [[ ! -f "$PLUGIN_ROOT/community-directory/community-directory.php" ]]; then
  echo "ERROR: Canonical Community Directory plugin is missing after quarantine." >&2
  exit 1
fi

find "$QUARANTINE_ROOT" -type f -print0 | sort -z | xargs -0 sha256sum > "$OUTPUT_DIR/quarantined-files-SHA256SUMS.txt"
wp --path="$WP_PATH" plugin list --fields=name,status,version --format=csv > "$OUTPUT_DIR/plugins-after.csv"

{
  printf 'action=plugin-quarantine\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'quarantine_root=%s\n' "$QUARANTINE_ROOT"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'canonical_community_directory=%s\n' "$PLUGIN_ROOT/community-directory"
} > "$OUTPUT_DIR/summary.txt"

chmod -R go-rwx "$OUTPUT_DIR" "${HOME}/stthekla-quarantine/${STAMP}"
printf 'Plugin quarantine complete: %s\n' "$OUTPUT_DIR"
