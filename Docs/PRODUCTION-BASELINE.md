# Production Baseline

**Baseline date:** August 28, 2026  
**WordPress path:** `/home3/stthekla/public_html`  
**Verified environment:** WordPress 7.1, PHP 8.3.33

This document records the non-sensitive production state from which future enhancements should begin. It is not a substitute for a fresh pre-deployment audit.

## Repository and production alignment

| Component | Production version | Exact deployed source |
| --- | --- | --- |
| Community Directory | `0.5.2` | `65f5da89a9128c6797d96bb5b6ce39b32f6bf115` |
| St. Thekla Site Core | `0.3.0` | `a2ef358c9a2dca5eff4d0d7648c8197d7859f178` |

Future product branches should start from the latest `main`, not from historical repair or production-execution branches.

## Sacred Heart Chapel release

The location release completed at `2026-08-28T02:22:15Z` after a fresh full database and site-files backup passed SHA-256 verification.

- Production deployment workflow run: `33135656681`
- Independent external smoke-test run: `33135788238`
- Site Core data version: `003`
- Move effective date: August 23, 2026
- Exterior image attachment: `4452`
- Parking-map attachment: `4453`

The deployment preserved a private copy of the prior Site Core plugin and a private snapshot of the changed WordPress options and page content for rollback. Private backup contents and paths are not committed to the repository.

## Approved public location

St. Thekla Malankara Orthodox Church now worships at:

- Sacred Heart Chapel
- 175 Route 340
- Sparkill, NY 10976

Map destination:

`https://www.google.com/maps/search/?api=1&query=175+Route+340+Sparkill+NY+10976`

## Recurring Sunday schedule

| Time | Service or activity |
| --- | --- |
| 8:00 AM | Lilyo |
| 8:30 AM | Morning Prayer |
| 9:00 AM | Holy Qurbana |
| 10:10 AM | Dismissal |
| 10:30 AM | Refreshments / Fellowship |
| 10:45 AM | Tree of Life |
| 11:30 AM | End of Tree of Life |

## Current public-site state

- The homepage displays the Sacred Heart Chapel move announcement, exterior image, new address, directions links and the exact seven-row schedule.
- `/visit-us/` is published with the exterior image, full parking map, parking restrictions, chapel entrance guidance, and St. Martin Hall/restroom information.
- The Contact Us page displays the centralized Sparkill location and weekly schedule while preserving WPForms form `4424`.
- The primary navigation contains a Visit Us item.
- The important announcement **St. Thekla Has a New Home** is published with the exterior image.
- The weekly schedule endpoint is `/wp-json/st-thekla/v1/weekly-schedule`.
- Public contact, visit, image and schedule data are available from `/wp-json/st-thekla/v1/public`.
- Current published pages contain none of the known West Nyack or Nyack location values.
- The homepage does not expose a raw Ninja Tables shortcode.
- The donation page continues to render its QuickPay and PayPal information.
- The Community Directory login page and unauthenticated session endpoint remain available.

Bluehost-side cache-busted validation and an independent GitHub-hosted external test passed all of these checks.

## Active services observed during the release preflight

- Community Directory `0.5.2`
- St. Thekla Site Core `0.3.0` after deployment
- WPForms Lite `2.0.1.1`
- WP Mail SMTP `4.9.0`
- Jetpack `16.1.2`

Bluehost/Newfold cache and SSO must-use plugins remain hosting-managed. Jetpack remains an item for a separate dependency review; it was not modified as part of the location release.

## Community Directory data preservation

The Sparkill release compared every Community Directory custom-table aggregate count immediately before and after the public-site changes. All counts were unchanged. The Community Directory plugin version and member-application source were not modified.

The previously recorded named baseline remains:

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

The directory database schema remains at version `007`.

## WordPress-user audit baseline

The latest completed user-classification baseline remains:

- Total WordPress users: 676
- Directory members linked to WordPress users: 33
- Directory member records without a WordPress user ID: 36, consistent with managed household-member records
- Subscriber accounts not linked to Community Directory: 636

No user was deleted, disabled or modified during the location release. The unlinked subscribers require classification before any cleanup action.

## Remaining manual and follow-up work

1. Submit one Contact Us message and confirm actual receipt in the church inbox.
2. Continue real-member testing of email/password login, Google OAuth, directory search, profiles, households, photo uploads, officer administration and PWA behavior.
3. Review whether Jetpack is still required after confirming every remaining dependency.
4. Classify the unlinked subscriber accounts before any deletion decision.
5. Review obsolete inactive themes and plugins as a separate, backup-gated cleanup.
6. Update off-site profiles such as Google Business, Facebook and Instagram if they still list a prior location.

## Operational branches

The production-recovery execution branch and its draft pull request are operational records only. They contain backup-gated diagnostic and recovery controls and must **not** be merged into `main`.

Private SSH credentials, WordPress configuration, backups, member reconciliation exports, email addresses and detailed production logs are intentionally excluded from this baseline.
