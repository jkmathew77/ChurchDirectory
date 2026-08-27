# St. Thekla Site Core

A focused WordPress plugin for stable, church-owned public-site features. It is intentionally separate from Community Directory so public content changes cannot destabilize the private membership application.

## Features

- Service Schedule custom post type
- Recurring Sunday schedule and read-only schedule API
- Leadership custom post type
- Announcement custom post type with dates, priority and featured images
- Centralized public church contact settings
- Visitor, move-announcement and parking components with WordPress Media Library images
- Public, read-only app endpoint at `/wp-json/st-thekla/v1/public`
- Shortcodes:
  - `[st_liturgy_schedule]`
  - `[st_weekly_schedule]`
  - `[st_visit_us]`
  - `[st_visit_us layout="compact"]`
  - `[st_leadership]`
  - `[st_church_location]`
  - `[st_announcements]`
- Compatibility bridge for the former `[ninja_tables id="142"]` schedule shortcode when Ninja Tables is inactive
- Conservative, versioned migrations that stop rather than overwrite an unexpected saved location or schedule
- Admin warnings for public user registration, migration problems and production debugging

## Version 0.3.0

Version 0.3.0 establishes Sacred Heart Chapel, 175 Route 340, Sparkill, NY 10976 as the current public worship location and updates the recurring Sunday schedule to:

- 8:00 AM — Lilyo
- 8:30 AM — Morning Prayer
- 9:00 AM — Holy Qurbana
- 10:10 AM — Dismissal
- 10:30 AM — Refreshments / Fellowship
- 10:45 AM — Tree of Life
- 11:30 AM — End of Tree of Life

The data migration runs only when the saved values match the approved prior Nyack baseline, are empty, or are already the approved Sparkill values.

## Deployment safety

Activation creates no custom database tables. Content is stored using WordPress core posts, post meta and options. Deactivation and plugin deletion do not automatically erase church content.

A production release must use a fresh verified database/files backup, preserve the prior plugin directory, import visitor images into the WordPress Media Library, apply the page/menu migration, purge host caches and complete external public verification.
