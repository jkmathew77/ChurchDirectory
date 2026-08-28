#!/usr/bin/env bash
# Deploy the exact merged St. Thekla Site Core 0.3.1 release, clear any
# punctuation-only telephone value, purge Bluehost caches, and verify the
# homepage compact image-centering rules and public output.
# Usage: bash deploy-site-core.sh /home3/stthekla/public_html /private/output/dir
set -euo pipefail
umask 077

WP_PATH="${1:-/home3/stthekla/public_html}"
OUTPUT_DIR="${2:-${HOME}/stthekla-change-logs/$(date +%Y%m%d-%H%M%S)-site-core-0.3.1}"
BACKUP_ROOT="${HOME}/stthekla-backups"
PLUGIN_SLUG="st-thekla-site-core"
PLUGIN_ROOT="$WP_PATH/wp-content/plugins"
TARGET="$PLUGIN_ROOT/$PLUGIN_SLUG"
REPOSITORY="https://github.com/jkmathew77/ChurchDirectory.git"
SOURCE_COMMIT="9e4e0df5ab16b21ec798fca39d2ea6f2e0b846c0"
EXPECTED_VERSION="0.3.1"
EXPECTED_DATA_VERSION="003"
HOME_URL="https://www.sttheklachurch.org/"
CONTACT_URL="https://www.sttheklachurch.org/contact-us/"
PUBLIC_API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/public"
SCHEDULE_API_URL="https://www.sttheklachurch.org/wp-json/st-thekla/v1/weekly-schedule"
DIRECTORY_LOGIN_URL="https://www.sttheklachurch.org/community/login/"

if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo "ERROR: WordPress was not found at $WP_PATH" >&2
  exit 1
fi
for command_name in wp git php curl python3 sha256sum; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "ERROR: Required command is unavailable: $command_name" >&2
    exit 1
  fi
done

LATEST_BACKUP="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
if [[ -z "$LATEST_BACKUP" || ! -f "$LATEST_BACKUP/SHA256SUMS" ]]; then
  echo "ERROR: No verified St. Thekla backup was found." >&2
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
WORK_DIR="$OUTPUT_DIR/private/source"
ROLLBACK_DIR="$OUTPUT_DIR/private/plugin-before"
SETTINGS_BACKUP="$OUTPUT_DIR/private/stc-settings-before.json"
NEW_DIR="$PLUGIN_ROOT/.${PLUGIN_SLUG}.release-new"
rm -rf "$WORK_DIR" "$NEW_DIR"

was_installed="no"
was_active="no"
if [[ -e "$TARGET" || -L "$TARGET" ]]; then
  was_installed="yes"
fi
if wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  was_active="yes"
fi
printf '%s\n' "$was_installed" > "$OUTPUT_DIR/plugin-installed-before.txt"
printf '%s\n' "$was_active" > "$OUTPUT_DIR/plugin-active-before.txt"
wp --path="$WP_PATH" option get stc_settings --format=json > "$SETTINGS_BACKUP"

wp --path="$WP_PATH" eval '
$settings = get_option("stc_settings", array());
$phone = trim((string) ($settings["phone"] ?? ""));
$digits = preg_replace("/\D/", "", $phone);
echo wp_json_encode(array(
    "phone_present" => "" !== $phone,
    "phone_digit_count" => strlen($digits),
    "phone_valid_or_empty" => "" === $phone || strlen($digits) >= 7,
), JSON_PRETTY_PRINT) . PHP_EOL;
' > "$OUTPUT_DIR/phone-before.json"

rollback() {
  set +e
  wp --path="$WP_PATH" plugin deactivate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
  rm -rf "$NEW_DIR"
  if [[ -e "$TARGET" || -L "$TARGET" ]]; then
    rm -rf "$TARGET"
  fi
  if [[ "$was_installed" == "yes" && -e "$ROLLBACK_DIR" ]]; then
    mv "$ROLLBACK_DIR" "$TARGET"
    if [[ "$was_active" == "yes" ]]; then
      wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" >/dev/null 2>&1 || true
    fi
  fi
  if [[ -s "$SETTINGS_BACKUP" ]]; then
    wp --path="$WP_PATH" option update stc_settings "$(cat "$SETTINGS_BACKUP")" --format=json >/dev/null 2>&1 || true
  fi
  wp --path="$WP_PATH" rewrite flush --hard >/dev/null 2>&1 || true
  wp --path="$WP_PATH" cache flush >/dev/null 2>&1 || true
  set -e
}
trap 'rollback' ERR

# Fetch only the exact merge commit that passed the complete PHP lint matrix and
# release-package checks.
git clone --quiet --no-checkout --filter=blob:none "$REPOSITORY" "$WORK_DIR"
git -C "$WORK_DIR" fetch --quiet --depth=1 origin "$SOURCE_COMMIT"
git -C "$WORK_DIR" checkout --quiet "$SOURCE_COMMIT" -- plugin/st-thekla-site-core
resolved_commit="$(git -C "$WORK_DIR" rev-parse FETCH_HEAD)"
if [[ "$resolved_commit" != "$SOURCE_COMMIT" ]]; then
  echo "ERROR: Source commit verification failed." >&2
  exit 1
fi

cp -a "$WORK_DIR/plugin/st-thekla-site-core" "$NEW_DIR"
find "$NEW_DIR" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l > "$OUTPUT_DIR/php-lint.txt"
version="$(grep -m1 -E '^ \* Version:' "$NEW_DIR/st-thekla-site-core.php" | sed -E 's/^ \* Version:[[:space:]]*//' | tr -d '[:space:]')"
if [[ "$version" != "$EXPECTED_VERSION" ]]; then
  echo "ERROR: Expected Site Core $EXPECTED_VERSION but found $version." >&2
  exit 1
fi
grep -Fq "define( 'STC_VERSION', '$EXPECTED_VERSION' )" "$NEW_DIR/st-thekla-site-core.php"
grep -Fq "define( 'STC_DATA_VERSION', '$EXPECTED_DATA_VERSION' )" "$NEW_DIR/st-thekla-site-core.php"
grep -Fq '.stc-visit-us-compact .stc-visit-primary' "$NEW_DIR/assets/css/public.css"
grep -Fq 'grid-template-columns: minmax(0, 1fr);' "$NEW_DIR/assets/css/public.css"
grep -Fq 'justify-self: center;' "$NEW_DIR/assets/css/public.css"
grep -Fq 'margin-inline: auto;' "$NEW_DIR/assets/css/public.css"
find "$NEW_DIR" -type f -print0 | sort -z | xargs -0 sha256sum > "$OUTPUT_DIR/site-core-files-SHA256SUMS.txt"

if [[ "$was_installed" == "yes" ]]; then
  rm -rf "$ROLLBACK_DIR"
  mv "$TARGET" "$ROLLBACK_DIR"
fi
mv "$NEW_DIR" "$TARGET"

wp --path="$WP_PATH" plugin activate "$PLUGIN_SLUG" > "$OUTPUT_DIR/activation.txt" 2>&1
if ! wp --path="$WP_PATH" plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  echo "ERROR: Site Core did not remain active." >&2
  exit 1
fi

# Clear the observed punctuation-only phone value while preserving any genuine
# telephone number. The CSS release also hides empty tel: links defensively.
wp --path="$WP_PATH" eval '
$settings = get_option("stc_settings", array());
$settings = is_array($settings) ? $settings : array();
$phone = trim((string) ($settings["phone"] ?? ""));
$normalized = preg_replace("/[^0-9+]/", "", $phone);
$digits = preg_replace("/\D/", "", $normalized);
$cleared = false;
if ("" !== $phone && strlen($digits) < 7) {
    $settings["phone"] = "";
    update_option("stc_settings", $settings, false);
    $cleared = true;
}
echo wp_json_encode(array(
    "invalid_phone_cleared" => $cleared,
    "phone_digit_count_after" => $cleared ? 0 : strlen($digits),
    "phone_valid_or_empty_after" => $cleared || "" === $phone || strlen($digits) >= 7,
), JSON_PRETTY_PRINT) . PHP_EOL;
' > "$OUTPUT_DIR/phone-cleanup.json"

wp --path="$WP_PATH" eval '
$css_path = STC_PLUGIN_DIR . "assets/css/public.css";
$css = is_file($css_path) ? file_get_contents($css_path) : "";
$settings = get_option("stc_settings", array());
$settings = is_array($settings) ? $settings : array();
$phone = trim((string) ($settings["phone"] ?? ""));
$digits = preg_replace("/\D/", "", $phone);
$rows = get_option("stc_weekly_schedule", array());
$html = do_shortcode("[st_visit_us layout=\"compact\"]");
$active = (array) get_option("active_plugins", array());
$checks = array(
    "plugin_active" => in_array("st-thekla-site-core/st-thekla-site-core.php", $active, true),
    "plugin_version" => defined("STC_VERSION") && STC_VERSION === "0.3.1",
    "data_version" => defined("STC_DATA_VERSION") && STC_DATA_VERSION === "003",
    "compact_single_column_css" => false !== strpos($css, ".stc-visit-us-compact .stc-visit-primary") && false !== strpos($css, "grid-template-columns: minmax(0, 1fr);"),
    "compact_center_css" => false !== strpos($css, "justify-self: center;") && false !== strpos($css, "margin-inline: auto;"),
    "compact_component_renders" => false !== strpos($html, "stc-visit-us-compact") && false !== strpos($html, "stc-visit-image"),
    "empty_tel_link_absent" => false === strpos($html, "href=\"tel:\""),
    "phone_valid_or_empty" => "" === $phone || strlen($digits) >= 7,
    "sparkill_address_preserved" => ($settings["address_line_1"] ?? "") === "Sacred Heart Chapel" && ($settings["address_line_2"] ?? "") === "175 Route 340" && ($settings["city"] ?? "") === "Sparkill",
    "seven_row_schedule_preserved" => is_array($rows) && count($rows) === 7,
    "community_directory_active" => in_array("community-directory/community-directory.php", $active, true),
);
$failed = array_keys(array_filter($checks, static function($value) { return !$value; }));
$report = array("checks" => $checks, "failed" => $failed);
echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if ($failed) { exit(2); }
' > "$OUTPUT_DIR/internal-verification.json"

wp --path="$WP_PATH" rewrite flush --hard > "$OUTPUT_DIR/rewrite-flush.txt" 2>&1 || true

cat > "$OUTPUT_DIR/private/purge-bluehost-cache.php" <<'PHP'
<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$results = array(
    'wordpress_object_cache' => (bool) wp_cache_flush(),
    'newfold_cache'          => 'unavailable',
    'endurance_page_cache'   => 'unavailable',
);
try {
    if ( class_exists( 'NewfoldLabs\\WP\\ModuleLoader' ) ) {
        $container = NewfoldLabs\WP\ModuleLoader\container();
        if ( $container && method_exists( $container, 'get' ) ) {
            $purger = $container->get( 'cachePurger' );
            if ( is_object( $purger ) && method_exists( $purger, 'purge_all' ) ) {
                $purger->purge_all();
                $results['newfold_cache'] = 'purged';
            }
        }
    }
} catch ( Throwable $error ) {
    $results['newfold_cache'] = 'error:' . sanitize_key( get_class( $error ) );
}
try {
    if ( class_exists( 'Endurance_Page_Cache' ) ) {
        $endurance = Endurance_Page_Cache::get_instance();
        if ( $endurance && method_exists( $endurance, 'purge_all' ) ) {
            if ( property_exists( $endurance, 'force_purge' ) ) {
                $endurance->force_purge = true;
            }
            $endurance->purge_all();
            $results['endurance_page_cache'] = 'purged';
        }
    }
} catch ( Throwable $error ) {
    $results['endurance_page_cache'] = 'error:' . sanitize_key( get_class( $error ) );
}
$results['completed_at_utc'] = gmdate( 'c' );
echo wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
PHP
wp --path="$WP_PATH" eval-file "$OUTPUT_DIR/private/purge-bluehost-cache.php" > "$OUTPUT_DIR/cache-flush.txt" 2>&1 || true
sleep 2

stamp="${SOURCE_COMMIT:0:10}-$(date +%s%N)"
curl_common=(
  -fsSL
  --max-time 60
  -H 'Cache-Control: no-cache, no-store, must-revalidate'
  -H 'Pragma: no-cache'
  -H 'User-Agent: StThekla-Site-Core-0.3.1-Verification/1.0'
)

curl "${curl_common[@]}" "${HOME_URL}?site_core_patch=${stamp}" > "$OUTPUT_DIR/homepage.html" 2> "$OUTPUT_DIR/homepage-curl-stderr.txt"
curl "${curl_common[@]}" "${CONTACT_URL}?site_core_patch=${stamp}" > "$OUTPUT_DIR/contact.html" 2> "$OUTPUT_DIR/contact-curl-stderr.txt"
curl "${curl_common[@]}" "${PUBLIC_API_URL}?site_core_patch=${stamp}" > "$OUTPUT_DIR/public-api.json" 2> "$OUTPUT_DIR/public-api-curl-stderr.txt"
curl "${curl_common[@]}" "${SCHEDULE_API_URL}?site_core_patch=${stamp}" > "$OUTPUT_DIR/schedule-api.json" 2> "$OUTPUT_DIR/schedule-api-curl-stderr.txt"
curl "${curl_common[@]}" "${DIRECTORY_LOGIN_URL}?site_core_patch=${stamp}" > "$OUTPUT_DIR/directory-login.html" 2> "$OUTPUT_DIR/directory-login-curl-stderr.txt"
curl "${curl_common[@]}" "https://www.sttheklachurch.org/wp-content/plugins/st-thekla-site-core/assets/css/public.css?ver=${EXPECTED_VERSION}&site_core_patch=${stamp}" > "$OUTPUT_DIR/public.css" 2> "$OUTPUT_DIR/public-css-curl-stderr.txt"

python3 - "$OUTPUT_DIR" <<'PY'
import json
import re
import sys
from pathlib import Path

root = Path(sys.argv[1])
def read(name):
    return (root / name).read_text(encoding='utf-8', errors='replace')

home = read('homepage.html')
contact = read('contact.html')
directory = read('directory-login.html')
css = read('public.css')
public = json.loads(read('public-api.json'))
schedule = json.loads(read('schedule-api.json'))

contact_payload = public.get('contact', {}) if isinstance(public, dict) else {}
phone = str(contact_payload.get('phone', '') or '').strip()
phone_digits = re.sub(r'\D', '', phone)
expected_schedule = [
    ('8:00 AM', 'Lilyo'),
    ('8:30 AM', 'Morning Prayer'),
    ('9:00 AM', 'Holy Qurbana'),
    ('10:10 AM', 'Dismissal'),
    ('10:30 AM', 'Refreshments / Fellowship'),
    ('10:45 AM', 'Tree of Life'),
    ('11:30 AM', 'End of Tree of Life'),
]
actual_schedule = [
    (str(item.get('time', '')), str(item.get('description', '')))
    for item in schedule.get('items', []) if isinstance(item, dict)
]

homepage_checks = {
    'compact_component_present': 'stc-visit-us-compact' in home and 'stc-visit-image' in home,
    'empty_tel_link_absent': 'href="tel:"' not in home and 'href="tel:"' not in contact,
    'sparkill_address_present': all(value in home for value in ('Sacred Heart Chapel', '175 Route 340', 'Sparkill')),
    'schedule_preserved': actual_schedule == expected_schedule,
    'directory_login_preserved': 'cd-wrap cd-login' in directory and 'Member Login' in directory,
    'versioned_css_requested': 'st-thekla-site-core' in home and ('ver=0.3.1' in home or 'ver=0.3.1' in home.replace('&#038;', '&')),
}
css_checks = {
    'compact_single_column_rule': '.stc-visit-us-compact .stc-visit-primary' in css and 'grid-template-columns: minmax(0, 1fr);' in css,
    'compact_center_rule': '.stc-visit-us-compact .stc-visit-image-wrap' in css and 'justify-self: center;' in css and 'margin-inline: auto;' in css,
    'empty_tel_defense': '.stc-location a[href="tel:"]' in css,
}
api_checks = {
    'phone_valid_or_empty': phone == '' or len(phone_digits) >= 7,
    'sparkill_address_preserved': all(value in str(contact_payload.get('address', '')) for value in ('Sacred Heart Chapel', '175 Route 340', 'Sparkill')),
    'seven_row_schedule_preserved': actual_schedule == expected_schedule,
}

homepage_report = {'checks': homepage_checks, 'failed': [k for k, v in homepage_checks.items() if not v]}
css_report = {'checks': css_checks, 'failed': [k for k, v in css_checks.items() if not v]}
api_report = {'checks': api_checks, 'failed': [k for k, v in api_checks.items() if not v], 'schedule': actual_schedule}
(root / 'public-homepage-verification.json').write_text(json.dumps(homepage_report, indent=2, sort_keys=True) + '\n', encoding='utf-8')
(root / 'public-css-verification.json').write_text(json.dumps(css_report, indent=2, sort_keys=True) + '\n', encoding='utf-8')
(root / 'public-api-verification.json').write_text(json.dumps(api_report, indent=2, sort_keys=True) + '\n', encoding='utf-8')

failed = homepage_report['failed'] + css_report['failed'] + api_report['failed']
if failed:
    raise SystemExit('Public verification failed: ' + ', '.join(failed))
PY

rm -f \
  "$OUTPUT_DIR/homepage.html" \
  "$OUTPUT_DIR/contact.html" \
  "$OUTPUT_DIR/directory-login.html" \
  "$OUTPUT_DIR/public-api.json" \
  "$OUTPUT_DIR/schedule-api.json" \
  "$OUTPUT_DIR/public.css"

wp --path="$WP_PATH" plugin get "$PLUGIN_SLUG" --fields=name,status,version --format=json > "$OUTPUT_DIR/plugin-state-after.json"

{
  printf 'action=deploy-site-core-0.3.1\n'
  printf 'backup_verified=%s\n' "$LATEST_BACKUP"
  printf 'wordpress_path=%s\n' "$WP_PATH"
  printf 'source_commit=%s\n' "$SOURCE_COMMIT"
  printf 'version=%s\n' "$EXPECTED_VERSION"
  printf 'data_version=%s\n' "$EXPECTED_DATA_VERSION"
  printf 'completed_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'plugin_active=yes\n'
  printf 'homepage_image_centering_css_verified=yes\n'
  printf 'invalid_phone_value_cleared_or_absent=yes\n'
  printf 'public_homepage_verified=yes\n'
  printf 'public_api_verified=yes\n'
  printf 'community_directory_preserved=yes\n'
  printf 'rollback_copy=%s\n' "$ROLLBACK_DIR"
} > "$OUTPUT_DIR/summary.txt"

trap - ERR
chmod -R go-rwx "$OUTPUT_DIR"
printf 'Site Core 0.3.1 deployment complete: %s\n' "$OUTPUT_DIR"
