#!/usr/bin/env bash
# Deploy the validated Community Directory repair build, preserve the current
# plugin files, activate it, and verify data counts and public routes.
# Usage: bash deploy-community-directory.sh <wp_path> <output_dir> <source_dir>
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:?output directory is required}"
SOURCE_DIR="${3:?validated Community Directory source directory is required}"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN_SLUG="community-directory"
EXPECTED_VERSION="0.5.2"
LIVE_DIR="$WP_PATH/wp-content/plugins/$PLUGIN_SLUG"
MAIN_FILE="$LIVE_DIR/community-directory.php"
SOURCE_MAIN="$SOURCE_DIR/community-directory.php"
LOGIN_URL="https://www.sttheklachurch.org/community/login/"
SESSION_URL="https://www.sttheklachurch.org/wp-json/community-directory/v1/auth/session-check"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: WP-CLI is required." >&2
  exit 1
fi
if [[ ! -f "$SOURCE_MAIN" ]]; then
  echo "ERROR: Validated plugin source was not found at $SOURCE_DIR" >&2
  exit 1
fi
if [[ ! -d "$LIVE_DIR" || ! -f "$MAIN_FILE" ]]; then
  echo "ERROR: Existing Community Directory installation was not found at $LIVE_DIR" >&2
  exit 1
fi

LATEST_BACKUP="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_BACKUP" || ! -f "$LATEST_BACKUP/SHA256SUMS" ]]; then
  echo "ERROR: No verified recovery backup was found." >&2
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
) >/dev/null

mkdir -p "$OUTPUT_DIR/private"

source_version="$(sed -n 's/^[[:space:]]*\* Version:[[:space:]]*//p' "$SOURCE_MAIN" | head -n 1 | tr -d '[:space:]')"
if [[ "$source_version" != "$EXPECTED_VERSION" ]]; then
  echo "ERROR: Expected source version $EXPECTED_VERSION but found ${source_version:-unknown}." >&2
  exit 1
fi

# Validate every PHP file under the exact source that will be deployed.
find "$SOURCE_DIR" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l > "$OUTPUT_DIR/php-lint.txt"

was_active="no"
if wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  was_active="yes"
fi
printf '%s\n' "$was_active" > "$OUTPUT_DIR/plugin-active-before.txt"
wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-before.json"

# Record aggregate row counts only. These are the production data-preservation gate.
wp --path="$WP_PATH" eval '
  global $wpdb;
  $suffixes = array("applications", "members", "directory_profiles", "households", "household_members", "invites", "audit_log", "officers", "whatsapp_groups");
  $out = array();
  foreach ($suffixes as $suffix) {
    $table = $wpdb->prefix . "cd_" . $suffix;
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
    $out[$suffix] = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`") : null;
  }
  echo wp_json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
' > "$OUTPUT_DIR/table-counts-before.json"

# Preserve the exact current plugin files and checksums outside public_html.
tar -czf "$OUTPUT_DIR/private/community-directory-before.tar.gz" -C "$(dirname "$LIVE_DIR")" "$PLUGIN_SLUG"
find "$LIVE_DIR" -type f -print0 | sort -z | xargs -0 sha256sum > "$OUTPUT_DIR/private/files-before-SHA256SUMS.txt"

STAGING_DIR="$WP_PATH/wp-content/plugins/.community-directory-staging-$$"
OLD_DIR="$OUTPUT_DIR/private/community-directory-live-before"
rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"
cp -a "$SOURCE_DIR"/. "$STAGING_DIR"/
find "$STAGING_DIR" -type d -exec chmod 755 {} +
find "$STAGING_DIR" -type f -exec chmod 644 {} +

rollback() {
  set +e
  wp --path="$WP_PATH" plugin deactivate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
  if [[ -d "$LIVE_DIR" ]]; then
    rm -rf "$LIVE_DIR"
  fi
  if [[ -d "$OLD_DIR" ]]; then
    mv "$OLD_DIR" "$LIVE_DIR"
  fi
  rm -rf "$STAGING_DIR"
  if [[ "$was_active" == "yes" ]]; then
    wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
  fi
  wp --path="$WP_PATH" rewrite flush >/dev/null 2>&1 || true
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
  set -e
}
trap 'rollback' ERR

if [[ "$was_active" == "yes" ]]; then
  wp --path="$WP_PATH" plugin deactivate "$PLUGIN_SLUG" > "$OUTPUT_DIR/deactivation.txt" 2>&1
else
  printf 'Community Directory was already inactive.\n' > "$OUTPUT_DIR/deactivation.txt"
fi

mv "$LIVE_DIR" "$OLD_DIR"
mv "$STAGING_DIR" "$LIVE_DIR"

wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" > "$OUTPUT_DIR/activation.txt" 2>&1
wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null

live_version="$(wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --field=version)"
if [[ "$live_version" != "$EXPECTED_VERSION" ]]; then
  echo "ERROR: WordPress reports Community Directory version $live_version after deployment." >&2
  exit 1
fi

wp --path="$WP_PATH" rewrite flush > "$OUTPUT_DIR/rewrite-flush.txt" 2>&1
wp --path="$WP_PATH" cache flush > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true

# Verify the plugin registered its REST API routes.
wp --path="$WP_PATH" eval '
  $server = rest_get_server();
  $routes = $server->get_routes();
  $required = array(
    "/community-directory/v1/auth/login",
    "/community-directory/v1/auth/session-check",
    "/community-directory/v1/members",
  );
  $missing = array();
  foreach ($required as $route) {
    if (!isset($routes[$route])) { $missing[] = $route; }
  }
  echo wp_json_encode(array("required" => $required, "missing" => $missing), JSON_PRETTY_PRINT) . PHP_EOL;
  if ($missing) { exit(4); }
' > "$OUTPUT_DIR/rest-routes.json"

# Verify aggregate row counts did not change during file deployment/activation.
wp --path="$WP_PATH" eval '
  global $wpdb;
  $suffixes = array("applications", "members", "directory_profiles", "households", "household_members", "invites", "audit_log", "officers", "whatsapp_groups");
  $out = array();
  foreach ($suffixes as $suffix) {
    $table = $wpdb->prefix . "cd_" . $suffix;
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
    $out[$suffix] = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`") : null;
  }
  echo wp_json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
' > "$OUTPUT_DIR/table-counts-after.json"

python3 - "$OUTPUT_DIR/table-counts-before.json" "$OUTPUT_DIR/table-counts-after.json" > "$OUTPUT_DIR/data-preservation.json" <<'PY'
import json, sys
before = json.load(open(sys.argv[1], encoding='utf-8'))
after = json.load(open(sys.argv[2], encoding='utf-8'))
report = {'unchanged': before == after, 'before': before, 'after': after}
print(json.dumps(report, indent=2, sort_keys=True))
if before != after:
    raise SystemExit(5)
PY

# Public smoke tests. Cache-busting avoids stale Bluehost page-cache responses.
login_status="$(curl -sS -L --max-time 45 -o "$OUTPUT_DIR/login-page.html" -w '%{http_code}' \
  -H 'Cache-Control: no-cache, no-store, must-revalidate' \
  "${LOGIN_URL}?directory_recovery=1&ts=$(date +%s)" 2> "$OUTPUT_DIR/login-curl-stderr.txt")"
if [[ "$login_status" != "200" ]] \
  || ! grep -q 'cd-wrap cd-login' "$OUTPUT_DIR/login-page.html" \
  || ! grep -q 'Member Login' "$OUTPUT_DIR/login-page.html"; then
  echo "ERROR: Community Directory login page smoke test failed with HTTP $login_status." >&2
  exit 1
fi

session_status="$(curl -sS -L --max-time 45 -o "$OUTPUT_DIR/session-check.json" -w '%{http_code}' \
  -H 'Cache-Control: no-cache, no-store, must-revalidate' \
  "${SESSION_URL}?directory_recovery=1&ts=$(date +%s)" 2> "$OUTPUT_DIR/session-curl-stderr.txt")"
if [[ "$session_status" != "200" ]] || ! grep -Eq 'logged_in|success|data' "$OUTPUT_DIR/session-check.json"; then
  echo "ERROR: Community Directory session-check endpoint failed with HTTP $session_status." >&2
  exit 1
fi

python3 - "$OUTPUT_DIR/login-page.html" "$login_status" "$session_status" > "$OUTPUT_DIR/public-verification.json" <<'PY'
import json, sys
html = open(sys.argv[1], encoding='utf-8', errors='replace').read()
print(json.dumps({
    'login_http_status': int(sys.argv[2]),
    'session_check_http_status': int(sys.argv[3]),
    'login_component_present': 'cd-wrap cd-login' in html,
    'member_login_heading_present': 'Member Login' in html,
    'login_response_bytes': len(html.encode('utf-8')),
}, indent=2, sort_keys=True))
PY
rm -f "$OUTPUT_DIR/login-page.html"

wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-after.json"
wp --path="$WP_PATH" option get cd_db_version > "$OUTPUT_DIR/db-version-after.txt"

{
  printf 'action=deploy-community-directory\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'previous_plugin_files=%s\n' "$OLD_DIR"
  printf 'deployed_version=%s\n' "$live_version"
  printf 'plugin_active=yes\n'
  printf 'database_row_counts_unchanged=yes\n'
  printf 'rest_routes_verified=yes\n'
  printf 'login_page_verified=yes\n'
  printf 'session_endpoint_verified=yes\n'
} > "$OUTPUT_DIR/summary.txt"

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Community Directory deployment complete: %s\n' "$OUTPUT_DIR"
