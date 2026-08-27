# St. Thekla Site Core 0.3.0

A focused WordPress plugin for stable, church-owned public-site features. It is intentionally separate from Community Directory so public content changes cannot destabilize the private membership application.

## Features

- Service Schedule custom post type
- Leadership custom post type
- Announcement custom post type with start/end dates
- Centralized public church contact settings
- Public, read-only app endpoint at `/wp-json/st-thekla/v1/public`
- Shortcodes:
  - `[st_liturgy_schedule]`
  - `[st_leadership]`
  - `[st_church_location]`
  - `[st_announcements]`
- Native recurring Sunday worship schedule with a compatibility bridge for the former `[ninja_tables id="142"]` shortcode
- Admin warnings for public user registration and production debugging

## Deployment safety

Activation creates no custom database tables and does not modify existing pages. Content is stored using WordPress core posts and post meta. Deactivation and plugin deletion do not automatically erase church content.

## Initial production setup

1. Back up WordPress files and database.
2. Install and activate the plugin.
3. Open **Settings → St. Thekla Site Core** and enter the current public contact details.
4. Add upcoming services under **Service Schedule**.
5. Add leaders and announcements if those modules will be used.
6. Add `[st_weekly_schedule]` to the homepage.
7. Verify the public API and church location settings after deployment.

## 0.3.0 location baseline

- Sacred Heart Chapel, 175 Route 340, Sparkill, NY 10976
- Sunday schedule: Lilyo at 8:00 AM, Morning Prayer at 8:30 AM, and Holy Qurbana at 9:00 AM
