# Release tracking

Local program tracker (replacing GitHub milestones for planning). Update checklist lines as you ship.

---

## Recently shipped

### v0.28.3

- [x] SVG icons on sidebar section headers (Scope, Jobs, Administration)
- [x] @handle mention chips restyled to pill shape
- [x] Drawer buttons → outline/ghost style; active tabs → accent-soft tint
- [x] Tooltip clipping fix (JS fixed-position)
- [x] Date timezone bug fix (`DateTimeImmutable` + `wp_timezone()`)

### v0.29.0

- [x] Deferred `send_gmail()` in `notify_mentions` + `emit_assignment_event` (perf fix)
- [x] `PQ_PERF` instrumentation spans on `move_task`
- [x] Mention chip CSS hardened (`!important` overrides to defeat wp-admin button styles)
- [x] Contextual date-swap prompt on demotion (Phase C)

---

## Active punch list

### 1. Status transition performance

**Problem:** 10–15s delay on some moves.  
**Next:** Server-side profiling of `sync_task_calendar_event` and `emit_event`; add timed spans; target p95 after fix.

- [ ] Baseline logs / profiler notes: ____________________
- [ ] Fix implemented: ____________________
- [ ] Verified p95 / release: ____________________

### 2. Messages / Notes unified conversation view

**Goal:** Single threaded view instead of separate cards.

- [ ] Product rules (ordering, types, permissions)
- [ ] API response shape (single sorted stream)
- [ ] Portal UI + a11y
- [ ] Shipped in version: ____________________

### 3. Contextual date-swap prompt

**Goal:** When drag demotes a task, offer to swap due dates (or keep / edit).

- [ ] Demotion rules aligned JS + PHP
- [ ] Modal + API atomic with status change
- [ ] Shipped in version: ____________________

---

## Bigger work (queued)

| #   | Item                              | Notes                                                                 |
| --- | --------------------------------- | --------------------------------------------------------------------- |
| 4   | Per-user Google OAuth             | Locked 7-step spec: relay → token storage → API → calendar → Gmail → portal UI → migration |
| 5   | Onboarding / magic-link invites   |                                                                       |
| 6   | Front-end `/portal` route         | Custom login, not wp-admin                                            |
| 7   | Swimlanes                         | Future board layout                                                   |
| 8   | Deferred API + Admin simplifications | Time-box to refactors when touching those areas                    |

---

## Milestone plan (single train)

Order epics inside one milestone before starting the next. Optional: point releases (e.g. v0.29.1) for small follow-ups.

### v0.29 — Fast, trustworthy board

**Theme:** Instrumentation + performance on status transitions; optional date-swap if it fits.

- [x] Epic: Status transition observability — `PQ_PERF` timed spans on `move_task` (visible_query, sort, reorder_updates, status_change, store_message, emit_events, enrich_response)
- [x] Epic: Status transition performance — root cause: synchronous `send_gmail()` in `notify_mentions` + `emit_assignment_event`. All email sends now deferred. p95 measured at ~33ms.
- [x] Epic: Contextual date-swap on demotion — checkbox in move modal on demotion drags, wired to existing `swap_due_dates` API

### v0.30 — One conversation (shipped early in v0.29.0)

- [x] Epic: Unified conversation API — `GET /tasks/{id}/conversation` via UNION ALL
- [x] Epic: Unified conversation UI — single stream, compose toggle, note badges

### v0.31 — Portal shell

- [ ] Epic: `/portal` route + custom login + redirects + cookie/REST alignment

### v0.32 — Per-user Google

- [ ] Epic: OAuth (relay → storage → endpoints → calendar → Gmail → UI → migration)

### v0.33 — Growth

- [ ] Epic: Magic-link invites + onboarding flows

### Later

- [ ] Swimlanes (after board perf is stable)
- [ ] Ongoing: deferred refactors (tie to touches on API/Admin)

---

## Major future (separate programs)

- **Multi-tenant / Next.js / PostgreSQL** — see `docs/NEXT_JS_ROADMAP.md`
- **iOS / Android** — duplicate repo; RN vs native TBD; consume stable API

---

## Release notes scratchpad

_Use for copy-paste into tags or changelogs._

**v0.29.0**

- Fix: synchronous `send_gmail()` in `notify_mentions` and `emit_assignment_event` — deferred to post-response (was blocking 10-15s per @mention/assignment email)
- Add: `PQ_PERF` instrumentation spans on `move_task` handler (7 named spans + total)
- Fix: mention chip buttons hardened with `!important` overrides to defeat wp-admin button CSS
- Add: contextual date-swap prompt — "Swap due dates" checkbox appears in move modal only on demotion drags when tasks have deadlines
- Add: unified conversation view — messages + notes merged into single chronological stream. Compose mode toggle (Message/Note). Notes tab removed. New `GET /tasks/{id}/conversation` endpoint.

**v0.30**

-
