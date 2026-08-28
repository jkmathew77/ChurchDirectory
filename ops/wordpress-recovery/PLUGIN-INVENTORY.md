# St. Thekla WordPress Plugin and Theme Inventory

Source: WordPress Site Health captured August 21, 2026. All ordinary plugins were inactive at capture time. Bluehost must-use plugins remained active.

## Target architecture

### Church-owned plugins

1. **Community Directory** — private membership application, authentication, households, officer administration, PWA, and app APIs.
2. **St. Thekla Site Core** — public service schedule, leadership, announcements, authoritative church contact information, and public app API.

### Focused third-party plugins

Keep no more than one maintained plugin for each genuinely required function: forms, SMTP/email delivery, SEO, advanced calendar, PDF rendering, spam control, analytics, or tournament brackets.

## Ordinary plugin decisions

| Plugin | Installed version | Decision | Prerequisite before removal or activation |
|---|---:|---|---|
| Community Directory | 0.4.4 live | **Repair and keep** | Full database/files backup; schema health report; compare live folder to GitHub; deploy and test repair PR #1 on staging or a controlled production window. |
| WP Mail SMTP | 4.9.0 | **Keep** | Configure a church-owned authenticated sender; verify test email before forms or directory emails are relied upon. |
| WPForms Lite | 2.0.0.5 | **Keep** | Activate after SMTP; rebuild Contact Us form; verify delivery and spam controls. |
| Yoast SEO | 28.3 | **Keep** | Activate only after core pages are stable; confirm one SEO plugin only. |
| Ninja Tables | 5.2.13 | **Migrate, then remove** | Preserve/export table 142 data; recreate services in Site Core; replace legacy shortcode after validation. |
| Jetpack | 16.1.2 | **Audit, replace, then remove** | Replace Contact Us form; audit galleries, subscriptions, statistics, social menu, CDN, and any remaining Jetpack shortcodes/features. |
| Team Members | 5.4.1 | **Migrate, then remove** | Recreate leadership entries in Site Core; replace all Team Members shortcodes or blocks. |
| iframe | 6.0 | **Replace, then remove** | Find all `[iframe]` shortcodes; convert approved embeds to native Embed or Custom HTML blocks. |
| Shortcodes Ultimate | 7.8.4 | **Replace, then remove** | Use shortcode audit to locate every `[su_*]` dependency; replace with core blocks or narrow Site Core components. |
| PublishPress Capabilities | 2.50.0 | **Audit, likely remove** | Export role/capability report; verify no non-directory role depends on changes made through this plugin. Community Directory owns its `cd_*` capabilities. |
| The Events Calendar | 6.17.3 | **Conditional keep** | Keep only if recurring events, venues, organizers, calendar feeds, or other advanced calendar functions are actively used. Otherwise migrate public events to Site Core. |
| Simple Tournament Brackets | 1.3.1 | **Temporary keep** | Confirm basketball tournament pages use it and that the version works under PHP 8.2/WordPress 7.1. Replace only when a custom tournament module is scoped. |
| PDF Embedder | 5.0.2 | **Conditional keep** | Keep only where inline PDF reading is required; otherwise replace with WordPress File blocks and direct links. |
| Akismet Anti-spam | 5.7.2 | **Conditional remove** | Close comments unless intentionally used; configure WPForms spam protection; then remove if no public comments/guestbook remain. |
| MonsterInsights | 11.1.3 | **Likely remove** | Confirm whether church leadership actively uses its dashboard. Preserve the Google Analytics property/measurement ID before deletion. |
| OptinMonster | 2.16.22 | **Remove** | Capture any active campaign or mailing-list integration first; otherwise no replacement is required. |
| Tabs – Responsive Tabs… | 4.0.6 | **Do not reactivate; replace and delete** | Locate every tabs shortcode and convert content before deleting the plugin directory. |
| WP File Manager | 8.0.4 | **Delete after backup** | Bluehost File Manager, SSH, and Git replace this capability. No page-content dependency is expected, but preserve the plugin inventory first. |

## Must-use plugins and drop-ins

Keep unchanged during recovery unless Bluehost confirms otherwise:

- Endurance Browser Cache 0.4
- Endurance Page Cache 2.2.2
- SSO 0.5
- `db-error.php`
- `maintenance.php`

These components are host-managed and are not part of the ordinary plugin consolidation.

## Theme decisions

- Keep **Toujours 1.1.2** active during plugin and directory recovery. Do not combine a theme migration with this incident.
- Update and retain one current default WordPress theme as an emergency fallback.
- After backup verification, remove the remaining obsolete inactive themes.
- Plan a separate modern church-theme migration after public content and Community Directory are stable.

## Safe activation order

1. WP Mail SMTP
2. WPForms Lite
3. St. Thekla Site Core
4. Community Directory after schema/file diagnostics
5. Yoast SEO
6. Conditional plugins one at a time, only where the dependency report proves they are needed

Purge Bluehost caches and test the affected pages after each activation. Never activate the entire inactive list in one operation.

## Safe removal order

After backups and shortcode reports are reviewed:

1. WP File Manager
2. Obsolete Tabs plugin, after content conversion
3. OptinMonster, if no active campaign exists
4. MonsterInsights, if unused
5. Akismet, if comments are closed and form spam protection is configured
6. iframe, Team Members, Shortcodes Ultimate, Ninja Tables, and Jetpack only after all embedded dependencies are migrated
7. Conditional calendar/PDF/tournament plugins only after their pages are confirmed independent
