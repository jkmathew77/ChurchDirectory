#!/usr/bin/env bash
# Activate WP Mail SMTP and send one controlled test message to the configured
# WordPress administrator email. If activation or sending fails, restore the
# prior inactive state.
# Usage: bash restore-mail-service.sh /home3/stthekla/public_html /private/output/directory
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-mail-service}"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN="wp-mail-smtp"
DEBUG_LOG="$WP_PATH/wp-content/debug.log"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi
if ! wp --path="$WP_PATH" plugin is-installed "$PLUGIN" >/dev/null 2>&1; then
  echo "ERROR: WP Mail SMTP is not installed." >&2
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

mkdir -p "$OUTPUT_DIR"
was_active="no"
if wp --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null 2>&1; then
  was_active="yes"
fi
printf '%s\n' "$was_active" > "$OUTPUT_DIR/plugin-active-before.txt"

debug_before=0
[[ -f "$DEBUG_LOG" ]] && debug_before="$(stat -c %s "$DEBUG_LOG")"

activation_result="success"
if [[ "$was_active" != "yes" ]]; then
  if ! wp --path="$WP_PATH" plugin activate "$PLUGIN" > "$OUTPUT_DIR/activation.txt" 2>&1; then
    activation_result="failed"
  fi
else
  printf 'WP Mail SMTP was already active.\n' > "$OUTPUT_DIR/activation.txt"
fi

mail_result="not-attempted"
kept_active="no"
if [[ "$activation_result" == "success" ]] && wp --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null 2>&1; then
  set +e
  wp --path="$WP_PATH" eval '
    $settings = get_option("wp_mail_smtp", array());
    $mailer = is_array($settings) && isset($settings["mail"]["mailer"]) ? (string) $settings["mail"]["mailer"] : "";
    $from = is_array($settings) && isset($settings["mail"]["from_email"]) ? (string) $settings["mail"]["from_email"] : "";
    $to = (string) get_option("admin_email", "");
    $subject = "St. Thekla website email test — " . gmdate("Y-m-d H:i") . " UTC";
    $message = "This is a controlled delivery test sent while restoring the St. Thekla Church website. No response is required.";
    $sent = false;
    if ( is_email($to) ) {
        $sent = wp_mail($to, $subject, $message, array("Content-Type: text/plain; charset=UTF-8"));
    }
    $report = array(
        "mailer" => $mailer,
        "from_email_configured" => is_email($from),
        "recipient_configured" => is_email($to),
        "sent" => (bool) $sent,
        "tested_at_utc" => gmdate("c"),
    );
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($sent ? 0 : 2);
  ' > "$OUTPUT_DIR/mail-test.json" 2> "$OUTPUT_DIR/mail-test-stderr.txt"
  send_exit=$?
  set -e

  if [[ $send_exit -eq 0 ]]; then
    mail_result="sent"
    kept_active="yes"
  else
    mail_result="failed"
  fi
else
  printf '{"sent":false,"reason":"activation_failed"}\n' > "$OUTPUT_DIR/mail-test.json"
fi

# Keep only non-sensitive diagnostics generated after this test.
if [[ -f "$DEBUG_LOG" ]]; then
  debug_after="$(stat -c %s "$DEBUG_LOG")"
  if (( debug_after > debug_before )); then
    tail -c +$((debug_before + 1)) "$DEBUG_LOG" \
      | sed -E 's/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/[email]/Ig; s#https?://[^[:space:]]+#\[url\]#g' \
      | tail -n 200 > "$OUTPUT_DIR/debug-delta.txt"
  else
    : > "$OUTPUT_DIR/debug-delta.txt"
  fi
else
  : > "$OUTPUT_DIR/debug-delta.txt"
fi

if [[ "$kept_active" != "yes" && "$was_active" != "yes" ]]; then
  wp --path="$WP_PATH" plugin deactivate "$PLUGIN" > "$OUTPUT_DIR/rollback.txt" 2>&1 || true
fi

wp --path="$WP_PATH" plugin list --fields=name,status,version --format=csv > "$OUTPUT_DIR/plugins-after.csv"

{
  printf 'action=restore-mail-service\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'activation_result=%s\n' "$activation_result"
  printf 'mail_result=%s\n' "$mail_result"
  printf 'kept_active=%s\n' "$kept_active"
  printf 'prior_active_state=%s\n' "$was_active"
} > "$OUTPUT_DIR/summary.txt"

chmod -R go-rwx "$OUTPUT_DIR"
printf 'Mail-service restoration check complete: %s\n' "$OUTPUT_DIR"
