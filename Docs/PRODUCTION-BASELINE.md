# Production Baseline

**Baseline date:** August 21, 2026  
**WordPress path:** `/home3/stthekla/public_html`  
**Verified environment:** WordPress 7.1, PHP 8.3.33

This document records the non-sensitive production state from which future enhancements should begin. It is not a substitute for a fresh pre-deployment audit.

## Repository alignment

| Component | Production version | Deployed source | Synchronized `main` commit |
| --- | --- | --- | --- |
| Community Directory | `0.5.2` | `65f5da89a9128c6797d96bb5b6ce39b32f6bf115` | `d41f59b487b034dc874a4a0293d2cbad68c7a53b` |
| St. Thekla Site Core | `0.2.0` | `1166ec7e9be7b5f141d4a4242297f83fa129d0ff` | `b52f5166275145b777974c0dbe8cb6edaff01660` |

The plugin source now present on `main` matches the production builds. Future product branches should start from `main`, not from the historical repair or recovery branches.

## Current ordinary active plugins

- Community Directory `0.5.2`
- St. Thekla Site Core `0.2.0`
- WPForms Lite `2.0.0.5`
- WP Mail SMTP `4.9.0`
- Shortcodes Ultimate `7.8.4` — temporary dependency for the existing donation-page lightbox markup

Bluehost/Newfold cache and SSO must-use plugins remain hosting-managed.

## Retired or inactive dependencies

- Ninja Tables is inactive. Its historical table and six schedule rows remain preserved, while Site Core renders the live homepage schedule.
- The legacy Jetpack contact-form shortcode was replaced by WPForms.
- Risky or duplicate inactive plugin directories identified during recovery were moved outside the public web root rather than deleted.

## Public-site state

- The homepage uses the Site Core Sunday schedule renderer.
- The recurring schedule contains Morning Prayers, Holy Liturgy, Dismissal, Refreshments, Tree of Life, and End of Tree of Life.
- The weekly schedule endpoint is `/wp-json/st-thekla/v1/weekly-schedule`.
- The Community Directory login page is `/community/login/`.
- The unauthenticated session endpoint is `/wp-json/community-directory/v1/auth/session-check`.
- The Contact Us page renders WPForms and displays `2 Old Ox Road, Nyack, NY 10960`.
- The former West Nyack address and raw Jetpack contact shortcode are absent.
- The donation page renders QuickPay and PayPal instructions without exposing raw Shortcodes Ultimate tags.

A Bluehost-side cache-busted verification and a separate external GitHub-hosted smoke test passed all of these public checks.

## Community Directory data-preservation baseline

Aggregate counts recorded before and after the production 0.5.2 deployment were identical:

| Table area | Rows |
| --- | ---: |
| Applications | 3 |
| Members | 69 |
| Directory profiles | 69 |
| Households | 11 |
| Household members | 44 |
| Invites | 91 |
| Audit log | 261 |
| Officers | 9 |
| WhatsApp groups | 2 |

The database schema remained at version `007`.

These counts are a dated reference only. A future deployment must collect fresh counts and explain any intentional difference.

## WordPress-user audit baseline

- Total WordPress users: 676
- Directory members linked to WordPress users: 33
- Directory member records without a WordPress user ID: 36, consistent with managed household-member records
- Subscriber accounts not linked to Community Directory: 636

No user was deleted, disabled, or modified during the recovery. The unlinked subscribers require classification before any cleanup action.

## Validation still required for relevant enhancements

Public health checks do not replace authenticated functional testing. Changes touching these areas must be tested with authorized accounts:

- Email/password login
- Google OAuth
- Directory search and profile viewing
- Member and household editing
- Member, avatar, and household-photo uploads
- Officer administration and application workflows
- PWA installation, service-worker updates, and offline behavior
- Email notifications and Google Contacts synchronization

## Known follow-up work

1. Replace the remaining Shortcodes Ultimate donation markup with Site Core functionality, then deactivate the plugin.
2. Replace the legacy PDF Embedder shortcode on the historical Palm Sunday event.
3. Classify the 636 unlinked subscriber accounts before any deletion decision.
4. Review obsolete inactive themes separately.
5. Continue authenticated Community Directory regression testing.

## Operational branches

The production-recovery execution branch and its draft pull request are operational records only. They contain backup-gated diagnostic and recovery controls and must **not** be merged into `main`.

Private SSH credentials, WordPress configuration, backups, member reconciliation exports, email addresses, and detailed production logs are intentionally excluded from this baseline.
