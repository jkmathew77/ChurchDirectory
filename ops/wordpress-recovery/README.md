# St. Thekla WordPress Recovery Toolkit

Read-only audit scripts and a private backup script for the production recovery project.

## Requirements

- SSH or Bluehost Terminal access
- WP-CLI available as `wp`
- Permission to read the WordPress installation and write to the account home directory

## Obtain the toolkit without merging

From the Bluehost account home directory:

```bash
cd ~
git clone --branch ops/wordpress-recovery-runbook --single-branch \
  https://github.com/jkmathew77/ChurchDirectory.git \
  ChurchDirectory-recovery
cd ~/ChurchDirectory-recovery/ops/wordpress-recovery
```

If that folder already exists, do not overwrite it blindly. Rename the old recovery checkout or inspect it first.

## Run the private backup first

```bash
bash backup-before-recovery.sh /home3/stthekla/public_html
```

Confirm the command reports a new directory under `~/stthekla-backups/` containing:

- `database.sql`
- `site-files.tar.gz`
- `SHA256SUMS`
- plugin/theme inventories

## Run the read-only audit

```bash
bash site-audit.sh /home3/stthekla/public_html
```

The audit produces reports under `~/stthekla-audits/`, including:

- WordPress user to Community Directory reconciliation
- Inactive-plugin shortcode dependencies
- Community Directory version, symlink, schema, table, and critical-column health
- Plugin sizes, cron events, roles, themes, and database table sizes

Backups and reports are written under the account home directory, never under `public_html`. The audit includes user email addresses and Community Directory linkage, so its output must remain private.

No script in this toolkit deletes or updates WordPress data.
