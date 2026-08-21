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

copy_if_present() {
  local source_dir="$1"
  shift
  local filename
  for filename in "$@"; do
    [[ -f "$source_dir/$filename" ]] && cp "$source_dir/$filename" "$SAFE_DIR/$filename"
  done
}

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

    safe_files=(core-version.txt core-checksums.txt plugins.csv themes.csv user-count.txt roles.csv cron-events.csv public-registration.txt default-comment-status.txt permalink-structure.txt database-table-sizes.csv shortcode-usage.csv community-directory-health.json community-directory-filesystem.txt plugin-directory-sizes.csv)
    copy_if_present "$audit_dir" "${safe_files[@]}"
    cp "$backup_dir/SHA256SUMS" "$SAFE_DIR/backup-SHA256SUMS.txt"
    cp "$backup_dir/wordpress-version.txt" "$SAFE_DIR/backup-wordpress-version.txt"
    cp "$backup_dir/plugins.csv" "$SAFE_DIR/backup-plugins.csv"
    cp "$backup_dir/themes.csv" "$SAFE_DIR/backup-themes.csv"
    du -h "$backup_dir/database.sql" "$backup_dir/site-files.tar.gz" > "$SAFE_DIR/backup-file-sizes.txt"
    {
      printf 'mode=backup-audit\nrun_token=%s\nbackup_directory=%s\naudit_directory=%s\nwordpress_path=%s\ncompleted_utc=%s\nuser_reconciliation_retained_privately=%s\n' \
        "$RUN_TOKEN" "$backup_dir" "$audit_dir" "$WP_PATH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$audit_dir/users-directory-reconciliation.csv"
    } > "$SAFE_DIR/execution-summary.txt"
    ;;

  deep-audit)
    audit_dir="${HOME}/stthekla-audits/deep-${RUN_TOKEN}"
    mkdir -p "$audit_dir"
    wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/deep-audit.php" > "$audit_dir/deep-audit.json"
    wp --path="$WP_PATH" eval-file "$SCRIPT_DIR/public-content-audit.php" > "$audit_dir/public-content-audit.json"
    cp "$audit_dir/deep-audit.json" "$SAFE_DIR/deep-audit.json"
    cp "$audit_dir/public-content-audit.json" "$SAFE_DIR/public-content-audit.json"
    printf 'mode=deep-audit\nrun_token=%s\naudit_directory=%s\nwordpress_path=%s\ncompleted_utc=%s\ncontains_personally_identifiable_information=no\n' \
      "$RUN_TOKEN" "$audit_dir" "$WP_PATH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$SAFE_DIR/execution-summary.txt"
    ;;

  config-audit)
    audit_dir="${HOME}/stthekla-audits/config-${RUN_TOKEN}"
    bash "$SCRIPT_DIR/config-log-audit.sh" "$WP_PATH" "$audit_dir"
    copy_if_present "$audit_dir" selected-constants.json selected-constant-occurrences.txt runtime-constants.json wp-config-php-lint.txt log-files.csv
    printf 'mode=config-audit\nrun_token=%s\naudit_directory=%s\nwordpress_path=%s\ncompleted_utc=%s\ncontains_secrets=no\n' \
      "$RUN_TOKEN" "$audit_dir" "$WP_PATH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$SAFE_DIR/execution-summary.txt"
    ;;

  inspect-wpforms)
    audit_dir="${HOME}/stthekla-audits/wpforms-source-${RUN_TOKEN}"
    bash "$SCRIPT_DIR/inspect-wpforms-source.sh" "$WP_PATH" "$audit_dir"
    copy_if_present "$audit_dir" wpforms-lite-source.tar.gz wpforms-lite-files.txt SHA256SUMS.txt wpforms-plugin-state.json
    printf 'mode=inspect-wpforms\nrun_token=%s\naudit_directory=%s\nwordpress_path=%s\ncompleted_utc=%s\ncontains_wordpress_content=no\n' \
      "$RUN_TOKEN" "$audit_dir" "$WP_PATH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$SAFE_DIR/execution-summary.txt"
    ;;

  repair-config-logs)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-config-logs"
    bash "$SCRIPT_DIR/repair-config-and-rotate-logs.sh" "$WP_PATH" "$change_dir"
    copy_if_present "$change_dir" runtime-before.json runtime-after.json removed-lines.txt wp-config-new-lint.txt log-archive-manifest.csv archive-SHA256SUMS.txt
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  restore-config)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-config-restore"
    bash "$SCRIPT_DIR/restore-config-after-safeguard.sh" "$WP_PATH" "$change_dir"
    copy_if_present "$change_dir" runtime-after-restore.json archived-config-lint.txt restored-config-lint.txt config-SHA256SUMS.txt
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  harden-settings)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-settings"
    bash "$SCRIPT_DIR/harden-settings.sh" "$WP_PATH" "$change_dir"
    copy_if_present "$change_dir" settings-before.json settings-after.json
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  quarantine-risky)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-plugin-quarantine"
    bash "$SCRIPT_DIR/quarantine-risky-plugins.sh" "$WP_PATH" "$change_dir"
    [[ -f "$change_dir/manifest.csv" ]] && cp "$change_dir/manifest.csv" "$SAFE_DIR/quarantine-manifest.csv"
    copy_if_present "$change_dir" quarantined-files-SHA256SUMS.txt plugins-after.csv
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  restore-mail)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-mail-service"
    bash "$SCRIPT_DIR/restore-mail-service.sh" "$WP_PATH" "$change_dir"
    copy_if_present "$change_dir" plugin-active-before.txt activation.txt mail-test.json mail-test-stderr.txt debug-delta.txt plugins-after.csv rollback.txt
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  restore-contact)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-contact-form"
    bash "$SCRIPT_DIR/restore-contact-and-address.sh" "$WP_PATH" "$change_dir"
    copy_if_present "$change_dir" plugin-active-before.txt activation.txt contact-form-result.json contact-form-stderr.txt cache-flush.txt curl-stderr.txt public-verification.json plugins-after.csv contact-page-state.json contact-address-result.json contact-address-stderr.txt address-cache-flush.txt address-curl-stderr.txt contact-address-public-verification.json
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  restore-ninja)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-ninja-table"
    bash "$SCRIPT_DIR/restore-ninja-table.sh" "$WP_PATH" "$change_dir"
    copy_if_present "$change_dir" table-before.json plugin-active-before.txt activation.txt internal-render.json cache-flush.txt curl-stderr.txt public-verification.json plugins-after.csv
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  restore-shortcodes)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-shortcodes-ultimate"
    bash "$SCRIPT_DIR/restore-shortcodes-ultimate.sh" "$WP_PATH" "$change_dir"
    copy_if_present "$change_dir" page-before.json plugin-active-before.txt activation.txt internal-render.json cache-flush.txt curl-stderr.txt public-verification.json plugins-after.csv
    cp "$change_dir/summary.txt" "$SAFE_DIR/execution-summary.txt"
    ;;

  deploy-directory)
    change_dir="${HOME}/stthekla-change-logs/${RUN_TOKEN}-community-directory"
    source_dir="$REMOTE_ROOT/toolkit/repair-source/plugin/community-directory"
    bash "$SCRIPT_DIR/deploy-community-directory.sh" "$WP_PATH" "$change_dir" "$source_dir"
    copy_if_present "$change_dir" php-lint.txt plugin-active-before.txt plugin-before.json table-counts-before.json deactivation.txt activation.txt rewrite-flush.txt cache-flush.txt rest-routes.json table-counts-after.json data-preservation.json login-curl-stderr.txt session-check.json session-curl-stderr.txt public-verification.json plugin-after.json db-version-after.txt
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
