# GitHub Actions execution gate

This branch provides a temporary GitHub-hosted SSH runner for the St. Thekla production recovery because the interactive ChatGPT execution sandbox does not permit outbound raw TCP/SSH connections.

## Required repository secret

Create one repository Actions secret:

- Name: `STTHEKLA_SSH_PRIVATE_KEY`
- Value: the complete OpenSSH private key corresponding to the public key authorized for `stthekla@162.241.24.113`

The workflow writes the key only to the ephemeral runner, validates it with `ssh-keygen`, and removes the runner at the end of the job.

## Trigger

The workflow runs only when this exact file is created or updated on branch `ops/stthekla-production-execution`:

```text
ops/wordpress-recovery/.production-recovery-trigger
```

Supported file contents:

- `connectivity` — verify SSH, WP-CLI, WordPress path, PHP version, and host-key fingerprints only
- `backup-audit` — repeat connectivity checks, then create the private production backup and read-only audit

## Data handling

- Full backups remain under `~/stthekla-backups/` on Bluehost.
- Full audits remain under `~/stthekla-audits/` on Bluehost.
- The PII-containing user reconciliation CSV is never copied into GitHub Actions.
- Only the approved non-PII diagnostics, backup checksums, and backup sizes are uploaded as a short-lived Actions artifact.
- No plugin is activated, deleted, upgraded, or deployed by this workflow.
- No WordPress setting, page, user, or database record is changed.

## Revocation

After recovery work is complete:

1. Delete the `STTHEKLA_SSH_PRIVATE_KEY` repository secret.
2. Remove the matching public key from Bluehost SSH Management.
3. Delete or close this temporary execution branch.
