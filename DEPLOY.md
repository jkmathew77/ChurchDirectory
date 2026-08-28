# Controlled Production Deployment

The St. Thekla production WordPress installation currently runs at:

```text
/home3/stthekla/public_html
```

Production uses normal WordPress plugin directories. Do **not** replace them with development symlinks, and do not run an unreviewed `git pull` inside `public_html`.

## Supported custom plugins

| Plugin slug | Current production version |
| --- | --- |
| `community-directory` | `0.5.2` |
| `st-thekla-site-core` | `0.3.0` |

GitHub Actions lints the complete repository on PHP 7.4, 8.2, and 8.4 and creates a ZIP artifact for each plugin.

## Release process

### 1. Prepare the change

- Create a focused branch from the latest `main`.
- Update the affected plugin version.
- Open a pull request.
- Require the full PHP lint matrix and both packaging jobs to pass.
- Review the exact changed files and generated artifact.

### 2. Establish the production safety gate

Before writing production plugin files:

1. Verify SSH host identity and WordPress/WP-CLI access.
2. Create a complete database export and `wp-content` archive outside `public_html`.
3. Generate and verify SHA-256 checksums.
4. Restrict backup permissions to the hosting account.
5. Record the active plugin version and activation state.
6. For Community Directory, record aggregate row counts for all `cd_*` tables.
7. Preserve the existing plugin directory privately for immediate rollback.
8. When page content or options will change, capture a private serialized snapshot before applying the migration.

No deployment should continue if the backup is missing, older than the approved window, or fails checksum verification.

### 3. Stage and validate the exact merged source

- Deploy from the exact merged commit SHA, not an unpinned branch tip.
- Extract or copy the plugin into a private staging directory first.
- Run `php -l` against every PHP file under the staged plugin.
- Confirm the plugin header version matches the intended release.
- Apply normal WordPress permissions before the atomic directory swap.
- Verify any media or binary payload by exact SHA-256 before it reaches production.

### 4. Deploy one plugin at a time

Replace only the targeted plugin directory. Do not deploy Community Directory and Site Core together unless the release explicitly requires both.

After the swap:

```bash
wp --path=/home3/stthekla/public_html plugin activate <plugin-slug>
wp --path=/home3/stthekla/public_html rewrite flush
wp --path=/home3/stthekla/public_html cache flush
```

Purge Bluehost/Newfold/Endurance page caches when public output changed.

### 5. Verify

Every release must include tests appropriate to the changed plugin.

#### Community Directory minimum checks

- Plugin reports the intended version and remains active.
- Database schema version is unchanged unless the release contains a reviewed migration.
- Aggregate table counts are unchanged unless the release intentionally changes data.
- Login page returns HTTP 200 and renders the member-login component.
- Session-check endpoint returns valid JSON.
- Required REST routes are registered.
- Test email/password login and Google OAuth with an authorized test member when authentication code changes.
- Test directory search, profiles, households, photos, officer administration, and PWA behavior when those areas change.

#### Site Core minimum checks

- Plugin reports the intended version and remains active.
- Site Core data version is the expected value.
- Homepage renders the intended church-owned content and schedule.
- Raw legacy shortcodes are absent from the public response.
- Weekly schedule API returns the exact expected rows.
- Public API returns the intended contact, visit, image and announcement values.
- Contact, location, leadership, announcement, donation or livestream output affected by the release renders correctly.
- When the release changes pages or navigation, verify those exact pages, menus and old-content removals.

#### External public smoke checks

Run cache-busted checks from outside Bluehost for, at minimum:

- homepage;
- Contact Us;
- Visit Us when location content is involved;
- donation page;
- Community Directory login;
- directory session endpoint;
- Site Core public API; and
- Site Core schedule API.

### 6. Roll back on any failure

- Deactivate the failed plugin build.
- Restore the preserved prior plugin directory.
- Restore its prior activation state.
- Restore the private page-content and option snapshot when the release changed WordPress content.
- Remove only media or posts created by the failed release.
- Flush rewrite rules and caches.
- Re-run the same verification suite.
- Restore the full database only when a reviewed migration changed data and targeted rollback is insufficient.

Keep the private rollback directory and full backup until the release has completed its observation period.

## Production-state documentation

After a successful deployment, update `Docs/PRODUCTION-BASELINE.md` with:

- deployment date;
- plugin and data versions;
- exact merged commit SHA;
- production and external validation completed;
- content, navigation or media changes;
- data-preservation result; and
- temporary dependencies or known follow-up work.

Do not record passwords, email addresses, tokens, private member data, private server paths to backup contents, or backup files in the repository.
