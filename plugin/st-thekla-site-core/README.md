# St. Thekla Site Core

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
- Compatibility bridge for the former `[ninja_tables id="142"]` schedule shortcode when Ninja Tables is inactive
- Admin warnings for public user registration and production debugging

## Deployment safety

Activation creates no custom database tables and does not modify existing pages. Content is stored using WordPress core posts and post meta. Deactivation and plugin deletion do not automatically erase church content.

## Initial production setup

1. Back up WordPress files and database.
2. Install and activate the plugin.
3. Open **Settings → St. Thekla Site Core** and enter the current public contact details.
4. Add upcoming services under **Service Schedule**.
5. Add leaders and announcements if those modules will be used.
6. Confirm the homepage's legacy Ninja Tables shortcode now renders the new schedule.
7. Replace the legacy shortcode with `[st_liturgy_schedule]` after validation.
