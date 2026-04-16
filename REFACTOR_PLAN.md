# Switchboard — Refactor Plan

**Decision date:** 2026-04-16
**Decision:** Move off WordPress before any client lands on the product. Migrate the codebase to Node + React + Postgres as a standalone, multi-tenant application.

---

## Why now

- App has outgrown WordPress. We use auth, the PHP runtime, `wp_mail`, and file uploads — that's it. Every table is custom, every endpoint is custom, the frontend is hand-rolled.
- No clients on the product yet → migration risk is bounded by our own time, not by uptime obligations.
- Continuing to build features in vanilla JS with string-concatenated HTML and no build tools compounds the cost of every future change.
- Multi-tenancy is the natural target architecture (already explored in `docs/multitenant-v1/`). Adding it to a WP plugin would be painful; building it into a fresh stack is straightforward.

## Why NOT a full rewrite from scratch

- Rewrites famously fail because they discard accumulated bug fixes and edge-case handling.
- The current codebase has months of debugged behavior — billing mode cascades, status transition rules, AI import enrichment, owner resolution — that we want to preserve.
- This is a **migration**, not a rewrite. Schema and business logic port; ceremony does not.

---

## Target stack

| Layer            | Choice                            | Why                                                                                  |
| ---------------- | --------------------------------- | ------------------------------------------------------------------------------------ |
| Backend runtime  | Node.js (TypeScript)              | Single-language stack with the frontend; mature ecosystem; easy serverless or VPS    |
| Backend framework| **Hono** or **Fastify**           | Light, typed, fast. Hono if we want edge-portable. Fastify if we want plugin ecosystem |
| ORM              | **Drizzle** or **Prisma**         | Drizzle: closer to SQL, type-safe, tiny. Prisma: better DX but heavier. Drizzle preferred |
| Database         | Postgres 16+                      | Real types, transactional DDL, JSON, full-text search                                |
| Frontend         | React + Vite + TypeScript         | Component model we need; Vite for fast dev                                           |
| State / data     | TanStack Query + Zustand          | Server cache + small global state                                                    |
| UI components    | shadcn/ui + Tailwind              | Copy-in components; full control; no lock-in                                         |
| Routing          | TanStack Router                   | Type-safe routing, search params as state                                            |
| Auth             | **Clerk** or **better-auth**      | Clerk is fastest path to live; better-auth keeps us self-hosted                      |
| Email            | Resend or Postmark                | Replaces `wp_mail`; transactional + templates                                        |
| File storage     | S3-compatible (Cloudflare R2)     | Replaces WP uploads; cheap egress                                                    |
| Background jobs  | BullMQ (Redis) or pg-boss (Postgres) | pg-boss avoids Redis; BullMQ is the standard                                       |
| AI               | OpenAI SDK + Anthropic SDK        | Same providers, native TypeScript SDKs                                               |
| Validation       | Zod                               | One schema for API + DB + forms                                                      |
| Testing          | Vitest + Playwright               | Unit + E2E                                                                            |

**Final picks pending validation:** Hono+Drizzle vs Fastify+Drizzle, Clerk vs better-auth. Will lock in during Phase 1.

## Hosting (post-Cloudways)

Ranked by fit for this project:

1. **Railway** *(recommended)* — Postgres + Node + Redis from one dashboard. Predictable pricing (~$5–25/mo to start). Branch deploys, secrets, logs, CI built in. Lowest friction migration target.
2. **Fly.io** — More control, global edge, Postgres add-on. Slightly more setup. Better if we want regional deployment later.
3. **Render** — Similar to Railway. Good DX. Slightly more expensive at scale.
4. **Vercel + Neon + Upstash** — Vercel for the React app, Neon for Postgres (serverless), Upstash for Redis. Best for "static frontend + API routes" pattern. Splits the stack across vendors.
5. **DigitalOcean App Platform** — Solid, predictable, less magic than Railway.

**Recommendation: Railway** for Phase 1. Move to Fly.io later if we need multi-region. Avoid AWS/GCP — too much undifferentiated heavy lifting at our size.

**Domain:** `app.switchboard.com` (or whatever; the WP marketing site can stay at the root domain).

---

## Repository strategy

Two paths considered:

| Option                         | Pros                                                  | Cons                                                       |
| ------------------------------ | ----------------------------------------------------- | ---------------------------------------------------------- |
| New repo `switchboard-app`     | Clean slate, no WP cruft, separate CI                 | Lose git history continuity with `Priority-Queue`          |
| Same repo, new top-level `app/`| Single source of truth, easy cross-reference          | Mixes two stacks during the transition                     |

**Recommendation: new repo** `switchboard-app` (or similar name TBD). Keep `Priority-Queue` frozen as the reference implementation. Cross-reference via README links. The WP plugin keeps shipping bug fixes if needed, but no new features.

Repo structure for the new app (monorepo with pnpm workspaces):

```
switchboard-app/
  apps/
    api/          Backend (Hono/Fastify + Drizzle)
    web/          Frontend (React + Vite)
  packages/
    schema/       Shared Zod schemas + types
    db/           Drizzle schema + migrations
    config/       Shared eslint, tsconfig, tailwind
  docs/
    DECISIONS.md  ADR-style architecture decision log
    PORTING_LOG.md What was ported when, edge cases preserved
  .github/
    workflows/    CI: lint, typecheck, test, deploy
```

---

## Domain model — what we're carrying forward

The current schema is the most valuable artifact. It's been through real billing scenarios. It maps cleanly to Postgres.

### Core entities (preserved)

- **tenants** — new top-level isolation boundary
- **users** — auth identity
- **memberships** — `tenant_id × user_id × role`
- **clients** — billing entity (was `pq_clients`)
- **jobs** — was `pq_billing_buckets`. Carries `default_billing_mode`, `default_rate`, `default_fee`
- **tasks** — was `pq_tasks`. Status, priority, queue_position, billing_mode, owners, deadlines
- **task_assignments** — was `pq_task_owners`
- **task_events** — was `pq_status_history`. Audit trail
- **notes** — task notes (sticky pinned + inline)
- **messages** — task conversation
- **ledger_entries** — was `pq_work_ledger_entries`. The billing source of truth — created when a task is delivered
- **invoices** — was `pq_statements`
- **invoice_lines** — was `pq_statement_items`
- **work_statements** — separate from invoices; deliverable summary
- **files** — task attachments
- **calendar_events** — Google Calendar sync
- **notifications** — in-app alerts
- **lanes** — swimlane groupings

### Key invariants to preserve

- **Status transitions are constrained** — see `class-wp-pq-workflow.php` for the allowed transition matrix. Port verbatim.
- **Billing mode cascade** — `task.billing_mode` → `task.job_default_billing_mode` → fallback. Determines how the ledger entry is built at completion time.
- **Ledger entry is created on `delivered`** — not at invoice time. Adjustments happen in the billing queue.
- **Owner resolution** — a task can have multiple owners; one is the "action owner" (the WP role-aware logic must be preserved as `role` enum on assignments).
- **Workflow vs billing are separate** — a task can be `delivered` (workflow) but `pending_review` (billing).

---

## Migration phases

### Phase 0 — Decisions and skeleton (this week)

- [ ] Lock in Hono vs Fastify, Drizzle vs Prisma, Clerk vs better-auth
- [ ] Spin up `switchboard-app` repo on GitHub
- [ ] Provision Railway project: Node + Postgres + Redis (if needed)
- [ ] Bootstrap monorepo with pnpm + Turborepo or just workspaces
- [ ] Drizzle schema seeded from `docs/multitenant-v1/DB_SCHEMA.sql` and the live WP schema audit
- [ ] CI: lint + typecheck + test on every PR
- [ ] Auth wired end-to-end (login → session → protected route)
- [ ] Tenant context middleware: `X-Tenant-Id` header → `tenant_id` in every query

**Exit criteria:** A logged-in user can hit `GET /api/v1/me` and see their memberships.

### Phase 1 — Tasks read-only

- [ ] Tasks table + assignments + events
- [ ] `GET /tasks` with filters (client, job, status, owner)
- [ ] `GET /tasks/:id` with assignments and events
- [ ] React board view (read-only): swimlanes, columns, cards
- [ ] Card drawer: read-only details
- [ ] Search params drive board state

**Exit criteria:** Can browse tasks ported from a snapshot of the WP DB.

### Phase 2 — Tasks write

- [ ] Create task, edit task, drag-to-reorder, status transitions
- [ ] Assignment add/remove
- [ ] Workflow rules enforced server-side (port `class-wp-pq-workflow.php`)
- [ ] Owner notifications
- [ ] Activity feed (task_events)

**Exit criteria:** Full task CRUD, workflow correctness verified against ported test cases.

### Phase 3 — Clients, jobs, billing setup

- [ ] Clients CRUD
- [ ] Jobs CRUD with billing defaults (mode, rate, fee)
- [ ] Billing Setup screen
- [ ] Client portal access (separate role)

**Exit criteria:** Manager can configure a client and a job from scratch.

### Phase 4 — Billing pipeline

- [ ] Ledger entry created on task delivery
- [ ] Billing Queue with adjust-form and bulk actions
- [ ] Invoice drafts → invoices
- [ ] Work Statements
- [ ] CSV / PDF export

**Exit criteria:** Can run a full month-end billing cycle end-to-end.

### Phase 5 — AI Import

- [ ] Port `class-wp-pq-ai-importer.php` to TypeScript
- [ ] Editable preview UI (already designed in WP version)
- [ ] OpenAI + Anthropic adapters
- [ ] Job auto-matching, owner resolution, deadline normalization

**Exit criteria:** Can paste a task list and get the same preview structure as the WP version.

### Phase 6 — Files, calendar, email

- [ ] S3/R2 file uploads with retention rules
- [ ] Google Calendar OAuth + sync
- [ ] Resend/Postmark for transactional email

### Phase 7 — Data migration

- [ ] Migration script: WP MySQL → Postgres
- [ ] Map `wp_users` → `users` + `memberships`
- [ ] Map `pq_*` tables → new schema
- [ ] Preserve IDs where possible (or maintain mapping table)

### Phase 8 — Cutover

- [ ] DNS swap
- [ ] Freeze WP version (no new writes)
- [ ] Final data sync
- [ ] WP version becomes read-only archive

---

## Risk register

| Risk                                            | Mitigation                                                                                |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Phase drift — phases stretch and never finish   | Strict exit criteria per phase. No starting Phase N+1 until N is verifiably done.         |
| Rebuilding bugs that took months to find        | Port logic verbatim where possible. Use the WP version as a reference oracle.             |
| Forgetting an invariant (e.g., billing cascade) | This document + `PORTING_LOG.md` in the new repo. Every edge case documented as it ports. |
| Auth complexity (multi-tenant + roles)          | Clerk does this out of the box. If we self-host, dedicate Phase 0 to getting it right.    |
| AI import flakiness                             | Already flaky in WP version. Snapshot test the prompt + response structure.               |
| Email deliverability                            | Use Resend/Postmark from day 1, not raw SMTP.                                             |

---

## What stays in this repo

- The WP plugin (frozen unless a critical bug emerges)
- This plan
- The handoff doc
- `docs/multitenant-v1/` as starting material
- `docs/archive/wp-era/` as historical reference

## What goes in the new repo

- All new code
- Architecture decisions (ADRs)
- Porting log (what came from where, what changed, why)
