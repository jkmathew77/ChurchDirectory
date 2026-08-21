# St. Thekla Church WordPress Platform

This repository is the source of truth for the custom WordPress code used by the St. Thekla Church website.

## Production-aligned custom plugins

| Plugin | Repository path | Production version |
| --- | --- | --- |
| Community Directory | `plugin/community-directory/` | `0.5.2` |
| St. Thekla Site Core | `plugin/st-thekla-site-core/` | `0.2.0` |

`main` was synchronized with both production builds on August 21, 2026.

### Community Directory

The private member application provides authentication, applications, member and household profiles, officer administration, invitations, WhatsApp links, Google integrations, and PWA functionality.

### St. Thekla Site Core

The public-site plugin owns stable church content and integrations that should remain isolated from the private directory application. It currently provides the church-managed Sunday schedule, contact settings, leadership and announcement content types, public APIs, and the compatibility renderer that replaced the live Ninja Tables dependency.

## Repository layout

- `plugin/community-directory/` — private member application
- `plugin/st-thekla-site-core/` — public church features
- `plugin/pack_plugin.py` — Community Directory ZIP builder
- `.github/workflows/php-lint.yml` — PHP 7.4, 8.2, and 8.4 linting plus release-package artifacts for both plugins
- `Docs/` — requirements, production baseline, and operational documentation
- `DEPLOY.md` — controlled production deployment and rollback procedure

## Enhancement workflow

1. Start a focused branch from the latest `main`.
2. Change only the plugin or documentation required for the enhancement.
3. Update the affected plugin version when production code changes.
4. Open a pull request and let the full PHP lint matrix and both packaging jobs complete.
5. Test the generated artifact against a staging copy or a verified production backup with a documented rollback path.
6. Merge only after validation.
7. Deploy the exact merged commit and record the deployed version in `Docs/PRODUCTION-BASELINE.md`.

Keep Community Directory and Site Core enhancements in separate pull requests whenever practical. Public website changes should not increase the failure surface of the private member application.

## Production safety rules

- Never commit SSH keys, OAuth secrets, SMTP credentials, WordPress salts, member exports, database backups, or `wp-config.php`.
- Never delete or rewrite member data as part of a code deployment.
- Take and verify a full database and site-files backup before changing production plugin files.
- Record Community Directory aggregate table counts before and after deployment.
- Preserve the prior plugin directory outside `public_html` until post-deployment verification is complete.
- Run public smoke tests and member-authenticated functional tests appropriate to the changed feature.
- Do not merge the temporary production-recovery execution branch into `main`.

See `Docs/PRODUCTION-BASELINE.md` for the dated production state and `DEPLOY.md` for the release procedure.
