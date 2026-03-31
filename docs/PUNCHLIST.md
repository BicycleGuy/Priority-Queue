# Priority Queue — Punch List

## Active UI/UX Issues

- [ ] Status transition performance — 10-15 second delay between click and toast on some moves (profile server-side `sync_task_calendar_event` and `emit_event`)
- [x] File uploads — replaced Uppy with lightweight custom dropzone (drag-and-drop + browse)
- [ ] Messages/Notes UI — user wants unified conversation view instead of separate cards
- [ ] Tooltip clipping — sticky note and priority marker tooltips can get clipped by card `overflow: hidden` on cards near container edges
- [ ] Date validation bug — `datetime-local` input sends browser-local format; `strtotime()` handles it but display may show wrong timezone
- [ ] Contextual date-swap prompt — when a drag demotes a task, prompt "Would you like to swap due dates with [displaced task]?"
- [ ] Create form — no file upload capability in the New Request modal (files only via task drawer after creation)

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

## Integration / Infrastructure

- [ ] **Google OAuth** — Client ID and secret need to be saved to WordPress options (`wp_pq_google_client_id`, `wp_pq_google_client_secret`, `wp_pq_google_redirect_uri`). OAuth client exists in Google Cloud Console but credentials aren't in WP. "Client missing a project id" error persists.
- [ ] **SMTP / Email** — depends on Google OAuth or alternative SMTP plugin. `wp_mail()` calls exist but no mail delivery confirmed.
- [ ] **Google Meet scheduling** — depends on OAuth fix. Floating scheduler UI is wired but API calls will fail without valid tokens.
- [ ] **Front-end portal route** — user wants `/portal` route with custom login (not wp-admin). Discussed but not started. Will become the basis for multi-tenant migration.

## Swimlanes (Future Feature)

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

## Multi-Tenant Migration (Future)

Blitzy tech spec saved at `docs/NEXT_JS_ROADMAP.md` (10,800 lines). Target stack:

- React + Next.js (App Router), TypeScript, Tailwind CSS
- PostgreSQL (Supabase or self-hosted), NextAuth.js or Supabase Auth
- Google Calendar/Meet API, Resend/SendGrid, OpenAI, S3-compatible storage
- Vercel or similar Node hosting

Key migration concerns:
- Auth system replacement (WordPress roles → custom RBAC)
- Database migration (18 MySQL tables → PostgreSQL)
- File storage (WordPress uploads → S3)
- Email (wp_mail → Resend/SendGrid)
- Cron jobs (WP-Cron → platform cron or Vercel Cron)

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
