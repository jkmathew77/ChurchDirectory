# St. Thekla Production Recovery Status — August 21, 2026

This report records the non-sensitive production results of the controlled Bluehost recovery. Private backups, user reconciliation data, SSH credentials, email addresses, and detailed logs are intentionally excluded.

## Recovery state

- SSH access to the Bluehost account was verified against the recorded server host keys.
- WordPress 7.1 and WP-CLI were verified at `/home3/stthekla/public_html`.
- Multiple complete database and site-file backups were created outside `public_html`, with SHA-256 checksums and private permissions.
- The production execution trigger is set to `connectivity`, and execution PR #5 is closed. No write operation is currently scheduled.

## Security and configuration changes

- Public WordPress registration was disabled.
- Default comments and pings were changed from open to closed.
- WP File Manager, the obsolete Tabs plugin, and duplicate Community Directory folders were moved to a private quarantine outside the web root. Nothing was deleted.
- Repeated custom-constant warnings in `wp-config.php` were eliminated by guarding the existing definitions while preserving their effective values.
- The prior oversized PHP logs were archived privately and reset. The final recovery audit reported an empty `wp-content/debug.log` and no current WordPress administrative error logs.

## Restored services

- WP Mail SMTP 4.9.0 is active. A controlled WordPress email-delivery test returned success.
- WPForms Lite 2.0.0.5 is active.
- A published Contact Us form exists and renders publicly. The raw Jetpack contact shortcode was removed.
- The Contact Us page now uses the current Nyack location, and the former West Nyack address is absent from the verified public response.
- Shortcodes Ultimate 7.8.4 is temporarily active so the existing donation instructions render without raw shortcode text.

## St. Thekla Site Core

- St. Thekla Site Core 0.2.0 is active.
- The homepage Sunday schedule renders through church-owned code.
- The recurring schedule contains six rows: Morning Prayers, Holy Liturgy, Dismissal, Refreshments, Tree of Life, and End of Tree of Life.
- The public weekly schedule API is available at `/wp-json/st-thekla/v1/weekly-schedule`.
- The current church location is centrally configured as St. Thomas Lutheran Church, 2 Old Ox Road, Nyack, NY 10960.
- Ninja Tables is inactive. Its six historical schedule rows and table record remain preserved, while Site Core owns the live homepage schedule.

## Community Directory

- Community Directory was upgraded from version 0.5.0 inactive to version 0.5.2 active.
- All PHP files passed syntax validation before deployment.
- WordPress rewrite rules and caches were refreshed.
- Required REST routes were verified, including authentication, session check, and directory search.
- The public member login page returned HTTP 200 and contained the expected Community Directory login component.
- The public session-check endpoint returned HTTP 200.
- The directory database schema remains at version `007`.
- Aggregate row counts were identical before and after deployment:
  - applications: 3
  - members: 69
  - directory profiles: 69
  - households: 11
  - household members: 44
  - invites: 91
  - audit log: 261
  - officers: 9
  - WhatsApp groups: 2

## Final public verification

A cache-busted production verification passed every tested public check:

- Homepage Site Core schedule present
- Homepage raw Ninja Tables shortcode absent
- Contact form present
- Current Nyack address present
- Former West Nyack address absent
- Raw Jetpack contact shortcode absent
- Community Directory member-login component present
- Community Directory session endpoint returned JSON
- Donation-page QuickPay and PayPal instructions present
- Donation-page raw Shortcodes Ultimate markup absent
- Site Core schedule API returned all six expected rows

## Current ordinary active plugins

- Community Directory 0.5.2
- St. Thekla Site Core 0.2.0
- WPForms Lite 2.0.0.5
- WP Mail SMTP 4.9.0
- Shortcodes Ultimate 7.8.4 — temporary legacy dependency

Bluehost’s Endurance cache and SSO must-use plugins remain in place.

## User audit — no accounts changed

- WordPress contains 676 users.
- 69 Community Directory member records exist.
- 33 directory members are linked to WordPress users.
- 36 directory member records do not have a WordPress user ID, consistent with managed household-member records.
- 636 subscriber accounts are not linked to Community Directory.
- No user account was deleted, disabled, or modified during this recovery.

## Remaining work

1. Perform member-authenticated testing of email/password login, Google OAuth, directory search, profiles, households, photos, officer administration, and PWA behavior.
2. Review the 636 unlinked subscriber accounts before any deletion decision.
3. Replace the remaining Shortcodes Ultimate donation markup with church-owned Site Core content, then deactivate that plugin.
4. Replace the legacy PDF Embedder shortcode on the historical Palm Sunday event.
5. Disable production debugging after the observation period.
6. Review obsolete inactive themes as a separate cleanup task.

## Repository controls

- Production execution PR #5 is closed and was not merged into `main`.
- Community Directory repair work remains in draft PR #1.
- Site Core development remains in draft PR #2.
