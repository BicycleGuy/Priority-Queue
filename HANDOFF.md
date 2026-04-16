# Handoff — Priority-Queue → Switchboard refactor

**Date:** 2026-04-16
**For:** the next conversation thread
**Last commit:** see `git log -1`
**Live plugin version:** `0.55.4`

---

## Read these first, in order

1. **[README.md](README.md)** — what the product is, current state, where files live.
2. **[REFACTOR_PLAN.md](REFACTOR_PLAN.md)** — the master plan: target stack, hosting, phases, risks.
3. `docs/multitenant-v1/` — earlier exploration (March 2026) of the same architectural direction. The DB schema sketch and API contract are useful starting material.
4. `docs/archive/wp-era/PUNCHLIST.md` — open issues from the WP version that should inform the new build.

## Decision summary (don't re-litigate without reason)

- **Going to Node + React + Postgres.** Decided 2026-04-16.
- **No paying clients yet** — migration risk is bounded.
- **New repo, not a sub-folder.** WP repo stays frozen as the reference implementation.
- **Hosting: Railway** for Phase 1. Fly.io as the fallback if we need multi-region.
- **This is a migration, not a rewrite.** Port logic verbatim where possible. The schema and business rules are the real product.

## What's still open before Phase 0 starts

These are the decisions that need to be locked in before we write any code:

| Decision               | Options                       | Notes                                                     |
| ---------------------- | ----------------------------- | --------------------------------------------------------- |
| Backend framework      | Hono vs Fastify               | Hono if we want edge portability; Fastify if plugin ecosystem matters more |
| ORM                    | Drizzle vs Prisma             | Leaning Drizzle — SQL-like, lighter                       |
| Auth                   | Clerk vs better-auth          | Clerk is fastest to ship; better-auth keeps us self-hosted |
| Repo name              | `switchboard-app` vs other    | The product is "Switchboard"; the repo can match           |
| Email provider         | Resend vs Postmark            | Resend has nicer DX; Postmark has stronger deliverability  |
| File storage           | R2 vs S3 vs Backblaze         | R2 has zero egress; preferred                             |
| Background jobs        | pg-boss vs BullMQ             | pg-boss avoids running Redis; BullMQ is the standard       |

## Current WP plugin state

### What works and ships

- Full task workflow (board, drawer, status transitions, swimlanes)
- Five billing modes: `hourly`, `fixed_fee`, `pass_through_expense`, `scope_of_work`, `non_billable`
- Billing Setup (per-job mode + rate/fee config)
- Billing Queue (adjust forms, bulk actions, CSV/print)
- Work Statements
- Invoice Drafts → Invoices
- Client portal with role-aware access
- AI Import (OpenAI + Anthropic) with editable preview
- Google Calendar / Meet integration
- File upload + retention
- Email notifications

### Recently changed (v0.54 → v0.55.4)

- Split monolithic `admin-queue.css` (5,672 lines) into `admin-base`, `admin-portal`, `admin-manager`, `admin-billing`
- Restructured manager nav with collapsible "Billing" group
- Added `scope_of_work` as fifth billing mode (DB column, API, UI)
- Added `default_fee` column on jobs
- Added Billing Setup section (inline-editable per-job billing defaults)
- Added editable AI Import preview (title, priority, owner dropdown, billing dropdown, remove row)
- Added "+ New Client" button on AI Import panel
- Fixed toast: was falling back to `window.alert()`; now uses portal's auto-dismissing toast stack

### Known carry-forward issues

From `docs/archive/wp-era/PUNCHLIST.md` (deferred but not yet built into the new app):

- `move_task` is 235 lines / 6 concerns — extract status transition, priority shift, date swap, reorder, history, event emission
- `reorder_tasks` issues individual UPDATEs — should be one CASE statement
- Duplicate access-scoping WHERE clauses in three methods
- Missing composite indexes: `(status, client_id, queue_position)` on tasks; `(user_id, is_read)` on notifications
- Google Calendar backfill is synchronous — should be background job
- `GET /tasks` is unbounded — needs pagination
- Billing status raw strings (`'batched'`, `'paid'`, `'unbilled'`) used in 10+ places — needs constants
- Priority value constants defined in three places — needs single source

These are WP-era issues. In the new build, address them by design rather than as cleanup.

### Open product threads (deferred features)

- **Color scheme system** with presets (calm, vibrant, stark, etc.) in Preferences → Appearance section. Audit hard-coded colors across CSS, replace with CSS custom properties, build preset themes with color picker. **Punt to new app.**
- **Billing mode selector on New Request form** (conditional on "Billable task" checkbox). **Punt to new app.**
- **Multi-client routing in AI Import** — current model requires one client per import pass. User decided this is fine for now; revisit only if it becomes a complaint.
- **Optional email on client creation** — currently required because every client gets a WP user. In the new app, decouple `clients` from `users` so clients can exist as billing entities without portal access.

## Deployment of the WP version (still needed for emergencies)

```bash
SSHPASS='kafkot-sohfuq-1meBki' sshpass -e rsync -avz --exclude='.git' --exclude='.claude' --exclude='.DS_Store' \
  /Users/readspear/Downloads/Priority-Queue/ \
  codex@104.236.224.6:/home/1353152.cloudwaysapps.com/qyrgzbqeju/public_html/wp-content/plugins/wp-priority-queue-plugin/ \
  -e 'ssh -o StrictHostKeyChecking=no'
```

Bump `WP_PQ_VERSION` in `wp-priority-queue-plugin.php` before deploying for cache busting.

## "Loyal opponent" pattern

The user wants a partner sub-agent during the refactor — someone to argue back, challenge decisions, point out what's being glossed over. Use the `Agent` tool with thoughtful prompts. Good moments to invoke:

- Before locking in a stack decision: have the opponent argue for the alternative.
- After designing a schema or API: have them try to break it.
- When porting a complex piece (workflow rules, billing cascade): have them audit the port for missed cases.

Frame prompts so the opponent has license to push back, not just rubber-stamp.

## What to do first in the new thread

1. Read this file, then `REFACTOR_PLAN.md`, then `docs/multitenant-v1/`.
2. Drive the open decisions above to a close (Hono/Fastify, Drizzle/Prisma, Clerk/better-auth, repo name).
3. Stand up the new repo and Railway project.
4. Drizzle schema first — schema is the contract, everything else flows from it.
5. Auth wired end-to-end before any feature code.
6. **Do not start porting features until Phase 0 exit criteria are met.**

## Git status at handoff

The repo should be on `main`, fully pushed, with the doc restructure committed. If `git status` shows anything dirty, that's a problem — investigate before continuing.

---

This document is the bridge. Keep it updated as Phase 0 decisions land.
