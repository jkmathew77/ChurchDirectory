# St. Thekla WordPress Production Recovery Checklist

This runbook is deliberately conservative. It does not delete users, content, tables, plugins, or themes until backups and dependency reports have been reviewed.

## Phase 1 — Preserve and inventory

- [ ] Run `backup-before-recovery.sh /home3/stthekla/public_html`.
- [ ] Confirm `database.sql`, `site-files.tar.gz`, and `SHA256SUMS` exist outside `public_html`.
- [ ] Run `site-audit.sh /home3/stthekla/public_html`.
- [ ] Review `users-directory-reconciliation.csv` before changing any user account.
- [ ] Review `shortcode-usage.csv` before deleting any inactive plugin.
- [ ] Preserve the current live `community-directory` plugin folder separately, even if it is version 0.4.4.
- [ ] Record whether `wp-content/plugins/community-directory` is a directory or symlink.

## Phase 2 — Reduce immediate exposure

- [ ] Disable **Settings → General → Anyone can register**.
- [ ] Close comments globally unless a guestbook or moderated discussion is intentionally used.
- [ ] Keep Bluehost must-use plugins and drop-ins unchanged.
- [ ] Do not activate all plugins at once.
- [ ] After dependency review, remove WP File Manager and the obsolete Tabs plugin first.

## Phase 3 — Restore transactional infrastructure

- [ ] Activate WP Mail SMTP only.
- [ ] Configure a church-owned sender and authenticated provider.
- [ ] Send and receive a test email.
- [ ] Activate WPForms Lite only.
- [ ] Create a new Contact Us form and verify delivery through WP Mail SMTP.
- [ ] Replace the broken Jetpack contact-form shortcode after the form test passes.

## Phase 4 — Restore church-owned public functionality

- [ ] Install St. Thekla Site Core on a staging copy or after backup verification.
- [ ] Enter the authoritative address, phone, email, map, donation, and livestream URLs.
- [ ] Add test services and verify the old `[ninja_tables id="142"]` position renders the new schedule.
- [ ] Replace the legacy shortcode with `[st_liturgy_schedule]` once validated.
- [ ] Add leadership and announcements only after the core schedule is stable.
- [ ] Confirm `/wp-json/st-thekla/v1/public` contains public information only.

## Phase 5 — Restore Community Directory

- [ ] Compare the live 0.4.4 folder with GitHub main and repair PR #1.
- [ ] Confirm the database backup contains all prefixed `cd_` tables plus `wp_users`, `wp_usermeta`, and `cd_%` options.
- [ ] Deploy to staging first when possible.
- [ ] Activate and verify migrations start from the installed `cd_db_version`.
- [ ] Resave permalinks and purge Bluehost caches.
- [ ] Test email/password login, Google OAuth, invitations, household profiles, photo upload, officer administration, REST routes, and PWA behavior.
- [ ] Do not merge or deploy the repair PR until these tests pass.

## Phase 6 — Consolidate and clean up

- [ ] Keep one plugin each for forms, SMTP, SEO, and any genuinely needed advanced calendar or PDF functionality.
- [ ] Migrate leadership, schedule, announcements, and contact information to Site Core.
- [ ] Replace all remaining Shortcodes Ultimate, iframe, Team Members, Tabs, and Jetpack dependencies before deleting their plugin folders.
- [ ] Keep the active Toujours theme temporarily and one updated default WordPress fallback theme.
- [ ] Delete obsolete inactive themes only after a verified file/database backup.
- [ ] Disable `WP_DEBUG` and `WP_DEBUG_LOG` after recovery is complete, then archive and remove the old debug log.
