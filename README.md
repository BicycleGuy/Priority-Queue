# Switchboard

Client work management: intake, approvals, kanban execution, billing, and a client portal.

Currently shipping as a WordPress plugin (Priority-Queue). **In transition** to a standalone Node + React + Postgres application — see [REFACTOR_PLAN.md](REFACTOR_PLAN.md).

---

## Status

- **Live plugin version:** `0.55.4` (`wp-priority-queue-plugin.php`)
- **Live URL:** https://readspear.com/priority-portal/
- **Stack today:** WordPress 6.0+ / PHP 8.1+ / MySQL / vanilla JS portal
- **Next stack:** Node + React + Postgres (multi-tenant, standalone)
- **Status:** No paying clients yet. Refactor begins now to avoid carrying WP debt forward.

## What it does

- Board and calendar views for task management
- Approval workflow with explicit status transitions: `Pending Approval` → `Needs Clarification` → `Approved` → `In Progress` → `Needs Review` → `Delivered`
- Role-based access: client / worker / manager / administrator
- Task messaging with `@mentions`, sticky notes, file attachments
- Google Calendar / Google Meet integration
- Email notifications and in-app alerts
- Client → job → task hierarchy
- Billing modes per task or job: `hourly`, `fixed_fee`, `pass_through_expense`, `scope_of_work`, `non_billable`
- Billing queue, work statements, invoice drafts
- AI Import: paste plain-language task lists, parse via OpenAI/Anthropic, edit preview, import to queue

## Repository layout (current WP plugin)

```
wp-priority-queue-plugin.php       Plugin bootstrap, WP_PQ_VERSION constant
includes/
  class-wp-pq-plugin.php           Runtime boot
  class-wp-pq-installer.php        Activation, table creation
  class-wp-pq-db.php               Schema (dbDelta), helpers
  class-wp-pq-migrations.php       One-time data migrations
  class-wp-pq-roles.php            Capabilities, roles
  class-wp-pq-workflow.php         Status transitions, allowed moves
  class-wp-pq-sanitizer.php        Input sanitization helpers
  class-wp-pq-api.php              Portal REST API + task business logic
  class-wp-pq-manager-api.php      Manager-only REST API
  class-wp-pq-portal.php           Shortcode markup, asset registration
  class-wp-pq-admin.php            Admin pages, client/billing helpers
  class-wp-pq-mail.php             Email sending
  class-wp-pq-files.php            Upload handling
  class-wp-pq-calendar.php         Google Calendar sync
  class-wp-pq-google-auth.php      OAuth relay
  class-wp-pq-drive.php            Google Drive (mostly removed)
  class-wp-pq-housekeeping.php     Cron jobs
  class-wp-pq-ai-importer.php      OpenAI/Anthropic parse calls
assets/
  js/                              Vanilla JS modules (admin-queue, admin-manager-*, modals)
  css/                             admin-base, admin-portal, admin-manager, admin-billing
docs/
  multitenant-v1/                  Earlier exploration of Node + Postgres target — basis for refactor
  archive/wp-era/                  Historical specs and punch lists from the WP plugin era
```

## Documentation

- **[REFACTOR_PLAN.md](REFACTOR_PLAN.md)** — the master plan for moving off WordPress
- **[HANDOFF.md](HANDOFF.md)** — current state and next steps for thread continuity
- `docs/multitenant-v1/` — schema sketch, API contract, architecture notes that seed the new app
- `docs/archive/wp-era/` — historical specs (board redesign, ledger refactor, OAuth, conversation, invites, release tracking, punchlist)

## Deployment (current WP version)

```bash
SSHPASS='...' sshpass -e rsync -avz --exclude='.git' --exclude='.claude' --exclude='.DS_Store' \
  /Users/readspear/Downloads/Priority-Queue/ \
  codex@104.236.224.6:/home/1353152.cloudwaysapps.com/qyrgzbqeju/public_html/wp-content/plugins/wp-priority-queue-plugin/ \
  -e 'ssh -o StrictHostKeyChecking=no'
```

Bump `WP_PQ_VERSION` in `wp-priority-queue-plugin.php` before deploy for CSS/JS cache busting. Pushing to GitHub does NOT deploy.

## Plugin options

- `wp_pq_max_upload_mb`, `wp_pq_retention_days`, `wp_pq_retention_reminder_day`, `wp_pq_file_version_limit`
- `wp_pq_google_client_id`, `wp_pq_google_client_secret`, `wp_pq_google_redirect_uri`
- `wp_pq_openai_api_key`, `wp_pq_anthropic_api_key`, `wp_pq_openai_model`

## License

MIT. Copyright 2026 BicycleGuy.
