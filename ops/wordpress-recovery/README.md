# St. Thekla WordPress Recovery Toolkit

Read-only audit scripts and a private backup script for the production recovery project.

## Requirements

- SSH access to the Bluehost account
- WP-CLI available as `wp`
- Permission to read the WordPress installation and write to the account home directory

## Run

```bash
bash backup-before-recovery.sh /home3/stthekla/public_html
bash site-audit.sh /home3/stthekla/public_html
```

Backups and reports are written under the account home directory, never under `public_html`. The audit includes user email addresses and Community Directory linkage, so its output must remain private.

No script in this toolkit deletes or updates WordPress data.
