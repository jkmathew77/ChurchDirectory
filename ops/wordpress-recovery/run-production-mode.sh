#!/usr/bin/env bash
# Execute one explicitly approved St. Thekla recovery mode on Bluehost.
# Usage: bash run-production-mode.sh <mode> <wp_path> <run_token> <remote_root>
set -euo pipefail
umask 077

MODE="${1:?mode is required}"
WP_PATH="${2:?WordPress path is required}"
RUN_TOKEN="${3:?run token is required}"
REMOTE_ROOT="${4:?remote root is required}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SAFE_DIR="$REMOTE_ROOT/safe-results"
PRIVATE_ROOT="${HOME}/stthekla-recovery-private/${RUN_TOKEN}"

mkdir -p "$SAFE_DIR" "$PRIVATE_ROOT"

case "$MODE" in
  backup-audit)
    backup_log="$PRIVATE_ROOT/backup.log"
    audit_log="$PRIVATE_ROOT/audit.log"
    audit_dir="${HOME}/stthekla-audits/github-${RUN_TOKEN}"

    bash "$SCRIPT_DIR/backup-before-recovery.sh" "$WP_PATH" | tee "$backup_log"
    backup_dir="$(sed -n 's/^Location: //p' "$backup_log" | tail -n 1)"
    if [[ -z "$backup_dir" || ! -d "$backup_dir" ]]; then
      echo "Unable to determine completed backup directory." >&2
      exit 1
    fi

    bash "$SCRIPT_DIR/site-audit.sh" "$WP_PATH" "$audit_dir" | tee "$audit_log"

    safe_files=(
      core-version.txt
      core-checksums.txt
      plugins.csv
      themes.csv
      user-count.txt
      roles.csv
      cron-events.csv
      public-registration.txt
      default-comment-status.txt
      permalink-structure.txt
      database-table-sizes.csv
      shortcode-usage.csv
      community-directory-health.json
      community-directory-filesystem.txt
      plugin-directory-sizes.csv
    )
    for filename in "${safe_files[@]}"; do
      [[ -f "$audit_dir/$filename" ]] && cp "$audit_dir/$filename" "$SAFE_DIR/$filename"
    done

    cp "$backup_dir/SHA256SUMS" "$SAFE_DIR/backup-SHA256SUMS.txt"
    cp "$backup_dir/wordpress-version.txt" "$SAFE_DIR/backup-wordpress-version.txt"
    cp "$backup_dir/plugins.csv" "$SAFE_DIR/backup-plugins.csv"
    cp "$backup_dir/themes.csv" "$SAFE_DIR/backup-themes.csv"
    du -h "$backup_dir/database.sql" "$backup_dir/site-files.tar.gz" > "$SAFE_DIR/backup-file-sizes.txt"

    {
      printf 'mode=backup-audit\n'
      printf 'run_token=%s\n' "$RUN_TOKEN"
      printf 'backup_directory=%s\n' "$backup_dir"
      printf 'audit_directory=%s\n' "$audit_dir"
      printf 'wordpress_path=%s\n' "$WP_PATH"
      printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
      printf 'user_reconciliation_retained_privately=%s\n' "$audit_dir/users-directory-reconciliation.csv"
    } > "$SAFE_DIR/execution-summary.txt"
    ;;

  deep-audit)
    audit_dir="${HOME}/stthekla-audits/deep-${RUN_TOKEN}"
    mkdir -p "$audit_dir"

    wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/deep-audit.php" > "$audit_dir/deep-audit.json"
    wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/public-content-audit.php" > "$audit_dir/public-content-audit.json"

    cp "$audit_dir/deep-audit.json" "$SAFE_DIR/deep-audit.json"
    cp "$audit_dir/public-content-audit.json" "$SAFE_DIR/public-content-audit.json"

    {
      printf 'mode=deep-audit\n'
      printf 'run_token=%s\n' "$RUN_TOKEN"
      printf 'audit_directory=%s\n' "$audit_dir"
      printf 'wordpress_path=%s\n' "$WP_PATH"
      printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
      printf 'contains_personally_identifiable_information=no\n'
    } > "$SAFE_DIR/execution-summary.txt"
    ;;

  config-audit)
    audit_dir="${HOME}/stthekla-audits/config-${RUN_TOKEN}"
    bash "$SCRIPT_DIR/config-log-audit.sh" "$WP_PATH" "$audit_dir"
    cp "$audit_dir/selected-constants.json" "$SAFE_DIR/selected-constants.json"
    cp "$audit_dir/wp-config-php-lint.txt" "$SAFE_DIR/wp-config-php-lint.txt"
    cp "$audit_dir/log-files.csv" "$SAFE_DIR/log-files.csv"
    {
      printf 'mode=config-audit\n'
      printf 'run_token=%s\n' "$RUN_TOKEN"
      printf 'audit_directory=%s\n' "$audit_dir"
      printf 'wordpress_path=%s\n' "$WP_PATH"
      printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
      printf 'contains_secrets=no\n'
    } > "$SAFE_DIR/execution-summary.txt"
    ;;

  harden-settings)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-settings"
    bash "$SCRIPT_DIR/harden-settings.sh" "$WP_PATH" "$change_dir"
    cp "$change_dir/settings-before.json" "$SAFE_DIR/settings-before.json"
    cp "$change_dir/settings-after.json" "$SAFE_DIR/settings-after.json"
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  quarantine-risky)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-plugin-quarantine"
    bash "$SCRIPT_DIR/quarantine-risky-plugins.sh" "$WP_PATH" "$change_dir"
    cp "$change_dir/manifest.csv" "$SAFE_DIR/quarantine-manifest.csv"
    cp "$change_dir/quarantined-files-SHA256SUMS.txt" "$SAFE_DIR/quarantined-files-SHA256SUMS.txt"
    cp "$change_dir/plugins-after.csv" "$SAFE_DIR/plugins-after.csv"
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  *)
    echo "Unsupported production mode: $MODE" >&2
    exit 1
    ;;
esac

chmod -R go-rwx "$SAFE_DIR" "$PRIVATE_ROOT"
tar -czf "$REMOTE_ROOT/safe-results.tar.gz" -C "$SAFE_DIR" .
printf 'Production mode complete: %s\n' "$MODE"
