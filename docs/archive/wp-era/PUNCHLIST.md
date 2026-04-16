# Switchboard — Punch List

## Active UI/UX Issues

- [x] Status transition performance — fixed (v0.29.0): root cause was synchronous `send_gmail()` in `notify_mentions` (15s timeout per @mention email). Deferred all email sends to post-response via `fastcgi_finish_request()`. Also fixed `emit_assignment_event`. Added `PQ_PERF` instrumentation spans on `move_task` — measured p95 at ~33ms with status change.
- [x] File uploads — replaced with external link field per task (v0.27.0); removed Uppy, Drive integration, Documents panel, file dropzone
- [x] Messages/Notes UI — (v0.29.0) unified conversation view: single chronological stream via `GET /tasks/{id}/conversation` (UNION ALL, ASC). Compose toggle for Message (notifies) vs Note (silent). Notes render with pin badge + left border. "Notes" tab removed, "Messages" renamed to "Conversation".
- [x] Tooltip clipping — fixed: switched from CSS pseudo-element (absolute) to JS-positioned fixed tooltip that escapes overflow ancestors
- [x] Date validation bug — fixed: `sanitize_datetime()` now uses `DateTimeImmutable` + `wp_timezone()` to correctly interpret browser-local datetime-local inputs
- [x] Contextual date-swap prompt — (v0.29.0) move modal shows "Swap due dates" checkbox only on demotion (target status index < source) when either task has a deadline. Uses existing `swap_due_dates` API parameter. Hint text shows both task titles.
- [x] Create form — file upload removed; tasks use external link field instead

## Deferred Code Simplification (class-wp-pq-api.php)

These were identified by review agents but deferred as higher-risk refactors:

- [ ] **Decompose `move_task`** (235 lines, 6 concerns) — extract status transition, priority shift, date swap, reorder, history, and event emission into focused helpers
- [ ] **Batch position UPDATEs** — `reorder_tasks()` and `move_task()` issue individual UPDATE per task; replace with single SQL CASE statement
- [ ] **Unify access-scoping WHERE clauses** — duplicated in `get_visible_tasks_for_request`, `move_task`, and `build_client_status_update_body`
- [ ] **Add composite indexes** — `(status, client_id, queue_position)` on pq_tasks; `(user_id, is_read)` on pq_notifications
- [ ] **Move Google Calendar backfill to background** — `backfill_task_calendar_events` makes synchronous HTTP calls that block API responses
- [ ] **Add pagination to GET /tasks** — currently unbounded `SELECT *` with no LIMIT
- [ ] **Pass task row to `emit_event`** — every call re-fetches the task from DB despite callers already having it
- [ ] **Unify work-log filter builder** — `preview_work_log_tasks` and `create_work_log_snapshot` duplicate WHERE-clause construction
- [ ] **Extract client validation loop** — `create_invoice_draft` and `create_work_log_batch` share identical client-ID + date-range extraction logic
- [ ] **Billing status constants** — `'batched'`, `'paid'`, `'unbilled'` etc. used as raw strings in 10+ places; add to `WP_PQ_Workflow`
- [ ] **Priority value constants** — defined independently in `sanitize_priority`, `shift_priority`, `task_priority_rank`; consolidate to single source
- [ ] **History note format** — hand-rolled `key:value` string encoding with no parser; consider structured JSON

## Deferred Code Simplification (class-wp-pq-admin.php)

- [ ] **Move ~700 lines of DB queries to `WP_PQ_DB`** — `get_billing_clients`, `get_buckets_by_client`, `get_rollup_groups`, `get_work_log_summaries`, `get_work_log_detail`, `get_statement_summaries`, `get_statement_detail`, `get_unbilled_ledger_entries`, `get_client_directory_rows`, `get_client_directory_members_by_client`, `get_job_members_by_bucket_ids`
- [ ] **Merge two print templates** — `render_print_document` and `render_invoice_draft_print_document` share 60% of markup and identical CSS blocks (~40 lines duplicated)
- [ ] **Batch `get_bucket_dependency_counts`** — currently fires 3 COUNT queries per job in a loop; replace with single grouped query
- [ ] **Bound `get_directory_users`** — fetches ALL WordPress users with no role filter or LIMIT; add `role__in` for PQ roles
- [ ] **Pagination on client directory** — `render_clients_page` eagerly loads all clients, members, work logs, statements with correlated subqueries
- [ ] **Extract notice rendering helper** — same 6-line admin notice block repeated 5 times
- [ ] **Extract filter-bar form helper** — nearly identical date-range filter forms on 3 pages
- [ ] **Merge `get_statement_summaries` and `get_statement_summaries_for_range`** — identical SQL except WHERE clause
- [ ] **Move `resolve_import_user_id` to `WP_PQ_AI_Importer`** — user-matching logic for AI import, not admin rendering
- [ ] **Define client role constants on `WP_PQ_Roles`** — `'client_admin'`, `'client_contributor'`, `'client_viewer'` used as raw strings
- [ ] **Batch bucket name lookups in CSV export** — `statement_line_bucket_name` fires per-line query

## Program Backlog (phases, sequencing, dependencies)

Full implementation plan with exit criteria: `docs/release_tracking.md`

| Phase | Target | Items | Depends on |
|-------|--------|-------|------------|
| **A** v0.29 | Hot path performance | Timed spans, profile emit_event + calendar sync, defer/async fix, regression guard | — |
| **B** v0.30 | Unified conversation UI | Product rules, API contract (single sorted stream), portal JS, a11y | A (stable transitions) |
| **C** v0.29 | Date-swap on demotion | Define demotion pairs, modal/prompt, atomic API, telemetry | A (same handler path) |
| **D** v0.32 | Per-user Google OAuth | Relay, token storage, API endpoints, calendar, Gmail send, portal UI, migration | F (cookie/redirect alignment) |
| **E** v0.33 | Onboarding / magic-link | Token model, email templates, redeem flow, manager invite UI | F (URL shape), D (Gmail option) |
| **F** v0.31 | `/portal` route | Routing, custom login, session cookies, redirect rules | — |
| **G** Later | Swimlanes | Lane key, board layout, Sortable constraints, persistence | A (board perf stable) |
| **H** Ongoing | Code simplifications | Tied to touch points in A/B/D; time-boxed refactors | — |

## Integration / Infrastructure

- [x] **Google OAuth** — OAuth relay deployed, consent screen working, Calendar + Drive scopes granted, token refresh working via relay
- [x] **SMTP / Email** — configured and delivering
- [x] **Google Meet scheduling** — OAuth connected, Calendar API functional
- [x] **Per-user Google OAuth** *(Phase D, v0.32.0)* — per-user token storage (wp_usermeta), relay carries user_id, per-user nonce, onboarding interstitial, migration from wp_options, legacy fallback removed
- [x] **Onboarding / magic-link invite system** *(Phase E, v0.33.0)* — 64-char hex token, pq_invites table, invite email via send_gmail, /portal/invite/{token} auto-creates user + assigns role + binds to client + logs in, manager Invites section with send/revoke UI
- [x] **Gmail send** *(Phase D, v0.32.0)* — `send_gmail()` via Gmail API using sender's token; falls back to `wp_mail()` if user hasn't connected Google. All 4 wp_mail call sites replaced.
- [x] **Front-end portal route** *(Phase F, v0.30.0)* — `/portal` with custom login, session cookies, redirect rules, REST nonce alignment. Basis for multi-tenant migration.

## Swimlanes *(Phase G — Future Feature)*

Horizontal row groupings that cut across status columns. Design decisions locked in:

- **Lanes are per-manager**, optionally shared with their client via `client_visible` flag
- **Each task gets a lane assignment** — defaults to "Uncategorized" (bottom-most row)
- **Within each lane**, cards sort by priority (high → low), then queue position
- **Cross-lane drag** triggers a confirmation prompt before re-categorizing
- **Lanes are collapsible** so managers can focus on one category at a time
- **Example lanes:** Priority, Grow, Sell, Discuss (user-defined, not hardcoded)

Data model: new `pq_lanes` table (`id`, `manager_user_id`, `client_id` nullable, `label`, `sort_order`, `client_visible`) + `lane_id` column on `pq_tasks`.

## Rebrand

App name: **Switchboard**. All user-facing references (login page, welcome emails, portal header, browser title) should use "Switchboard" instead of "Priority Queue" or "Priority Portal".

## Multi-Tenant Migration *(Separate Program)*

Blitzy tech spec saved at `docs/NEXT_JS_ROADMAP.md` (10,800 lines). Strategy: strangler fig — define read models and events, dual-write one bounded context first, tenant isolation + audit log from day one.

Target stack: React + Next.js (App Router), TypeScript, Tailwind, PostgreSQL (Supabase or self-hosted), NextAuth/Supabase Auth, Google APIs, Resend/SendGrid, S3, Vercel.

## iOS / Android *(Separate Program)*

After Phase F and stable public API surface. Mobile is a **consumer**, not a fork of business rules. RN vs native TBD based on calendar/Gmail depth and offline needs; share OpenAPI from PHP or Next backend.

## Completed (This Session)

- [x] Remove inline "Approve" button from task cards (approval via drawer only)
- [x] Auto-activate meeting scheduler after task creation with `needs_meeting`
- [x] Add archive pathway: delivered → archived (single + bulk "Archive All")
- [x] Drag transition validation with dismissable warning toasts
- [x] Move board filters from sidebar to horizontal checkbox bar
- [x] Status filters are toggles (click to activate, click again to deactivate)
- [x] Remove duplicate Cancel button from queue decision modal
- [x] Remove "Swap dates" checkbox from queue decision modal
- [x] Narrow modal to 420px, bump body text to 17px, small text to 14px
- [x] Remove "Import with AI" button from New Request form
- [x] Reorder sidebar: Mode → Scope → Jobs → Workspace
- [x] Workspace nav stays visible when navigating to non-queue sections
- [x] Dark mode toggle text changes to "Light Mode" when active
- [x] New job placeholder changed to "Job name"
- [x] Speed up Sortable: animation 80ms, emptyInsertThreshold 60, lock 100ms
- [x] Hide empty-state placeholders during drag for reliable empty-column drops
- [x] Add bottom padding to columns for below-last-card drops
- [x] Compact create form and section heading spacing
- [x] Remove board heading divider line
- [x] Warning toast styling: dark bg with amber left border
- [x] Merge alert/alertWithDismiss into single parameterized function
- [x] Fix renderUnifiedFilters: clean for loops, no side effects
- [x] Guard JSON.parse of localStorage, var→const/let cleanup
- [x] Delete dead filterIcon() function
- [x] API: 13 get_user_by → get_cached_user (fewer DB queries)
- [x] API: request-level client membership cache (4 call sites)
- [x] API: sanitize_int_array helper (17 inline chains replaced)
- [x] API: cache status_timestamp_updates in move_task
- [x] Update ARCHITECTURE.md
