# Switchboard (Priority-Queue) -- Consolidated Code Review Report

**Generated:** 2026-04-05
**Plugin version:** 0.48.0 (`wp-priority-queue-plugin.php:15`)

---

## 1. Executive Summary

Switchboard is a WordPress plugin (formerly "Priority Queue") providing client work-request workflow management: queue boards, approvals, billing, file exchange, scheduling, notifications, and a client-facing portal. The codebase comprises 16 PHP classes (~17,000 LOC), 9 JavaScript files (~9,350 LOC), 1 CSS file (5,502 LOC), a 4-file OAuth relay, 21 markdown docs, and 3 HTML mockups. Active development from v0.28 to v0.47.2 over approximately 6 weeks.

Security fundamentals are solid: all SQL uses `wpdb->prepare` or `intval` mapping, nonce checks and capability gates are consistent throughout. However, two PHP classes exceed 3,000 lines each (god-class pattern), one critical JavaScript bug will crash CSV export at runtime, and there is no test suite, no CI/CD pipeline, and no linting configuration. The `.env` writer in the installer is vulnerable to newline injection.

The highest-leverage improvements are: (1) fix the infinite-recursion bug in `admin-portal-manager.js:89`, (2) sanitize the `.env` writer in the installer, (3) break up `WP_PQ_API` and `WP_PQ_Admin` using the refactor targets already catalogued in `docs/PUNCHLIST.md`, and (4) add a minimal test harness and ESLint before the codebase grows further.

---

## 2. Repository Structure

```
Priority-Queue/
  wp-priority-queue-plugin.php       (entry point, v0.47.2)
  includes/                          (16 PHP classes)
  assets/js/                         (9 JS files)
  assets/css/                        (1 CSS file -- admin-queue.css)
  relay/                             (4 PHP files -- OAuth relay)
  docs/                              (11 markdown specs + multitenant-v1/)
  mockups/                           (2 HTML mockups)
  mockup-org-chart.html              (1 HTML mockup at root)
  bin/                               (install.sh, build-zip.sh)
  dist/                              (2 versioned ZIPs)
  .claude/                           (11 agents, 15 commands, settings)
  FLOW.md, README.md, LICENSE
```

| Category | Files | Total LOC |
|----------|-------|-----------|
| PHP classes (includes/) | 16 | ~16,966 |
| JavaScript (assets/js/) | 9 | ~9,345 |
| CSS (assets/css/) | 1 | 5,502 |
| OAuth relay (relay/) | 4 | ~200 |
| Markdown docs | 21+ | ~13,335 |
| HTML mockups | 3 | ~530 |
| **Total** | **54+** | **~45,878** |

---

## 3. Findings by Domain

### 3.1 PHP Architecture

#### God classes

| File | Class | LOC | Methods | Concern |
|------|-------|-----|---------|---------|
| `includes/class-wp-pq-api.php` | WP_PQ_API | 4,656 | 44 static | REST routing + task ops + notifications + billing + events |
| `includes/class-wp-pq-admin.php` | WP_PQ_Admin | 3,630 | 30+ | Settings rendering + 700 lines of DB queries + form handlers |
| `includes/class-wp-pq-manager-api.php` | WP_PQ_Manager_API | 2,318 | ~25 | Manager REST endpoints + billing logic |
| `includes/class-wp-pq-portal.php` | WP_PQ_Portal | 1,294 | 10 | Portal routing + auth + rendering |
| `includes/class-wp-pq-db.php` | WP_PQ_DB | 1,163 | 28 static | Schema + query helpers + caching |

#### Oversized methods (50+ lines)

| Method | File | Lines | Issue |
|--------|------|-------|-------|
| `handle_portal_route()` | class-wp-pq-portal.php | ~316 | 3 routes (invite, login, portal) handled in one method |
| `move_task()` | class-wp-pq-api.php | ~280 | 6 concerns: status transition, priority shift, date swap, reorder, history, event emission |
| `register_routes()` | class-wp-pq-api.php | ~246 | Monolithic route registration (somewhat unavoidable) |
| `create_invoice_draft()` | class-wp-pq-api.php | ~158 | Billing logic mixed with API handling |
| `build_invoice_draft_lines_from_ledger()` | class-wp-pq-api.php | ~143 | Complex calculation in API class |
| `mark_task_done()` | class-wp-pq-api.php | ~121 | Task completion + notifications + ledger write |
| `create_work_log_batch()` | class-wp-pq-api.php | ~116 | Work log generation |

#### Caching

Static class-level caches (`$client_memberships_cache`, `$job_member_ids_cache`, etc.) in `WP_PQ_DB` have no TTL or invalidation strategy -- stale data risk in long-running requests or WP-CLI contexts.

#### Well-designed classes

| File | Class | LOC | Assessment |
|------|-------|-----|------------|
| `class-wp-pq-roles.php` | WP_PQ_Roles | 45 | Clean, single responsibility |
| `class-wp-pq-mail.php` | WP_PQ_Mail | 79 | Focused, proper fallback |
| `class-wp-pq-workflow.php` | WP_PQ_Workflow | 153 | Clean state machine |
| `class-wp-pq-migrations.php` | WP_PQ_Migrations | 858 | Idempotent, well-guarded |

---

### 3.2 Security

| ID | Severity | Finding | Location | Detail |
|----|----------|---------|----------|--------|
| SEC-1 | Medium | .env newline injection | `class-wp-pq-installer.php:94-97, 136-139` | Credentials interpolated into `.env` without stripping `\n`. A value containing a newline can inject arbitrary env vars into the relay config. |
| SEC-2 | Medium | Global OAuth state race condition | `class-wp-pq-google-auth.php:122` | `update_option('wp_pq_google_oauth_state', ...)` is global. Two concurrent OAuth flows overwrite each other's state. |
| SEC-3 | Low | No rate limiting | REST API + portal login | No throttling on login attempts or API endpoints. |
| SEC-4 | Info | Credentials in `.claude/settings.local.json` | `.claude/settings.local.json` | SSH password and API key in plaintext. Local-only, not committed to shared repos, but flagged for awareness. |

**Positive findings:** All SQL parameterized via `wpdb->prepare` or `intval` mapping. All REST endpoints have nonce verification. All admin handlers use `check_admin_referer()`. All operations check `current_user_can()`. HTML output properly escaped.

---

### 3.3 JavaScript

#### Critical bug

```
File: assets/js/admin-portal-manager.js, line 89
function rowsToCsv(rows) {
    return rowsToCsv(rows);  // infinite recursion -- stack overflow on any CSV export
}
```

The working implementation exists in `assets/js/admin-manager-core.js:89`:
```
function rowsToCsv(rows) {
    return rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
}
```

#### Duplicate code inventory

The root cause: `admin-manager-core.js` + split modules are consumed by wp-admin pages; `admin-portal-manager.js` is a consolidated IIFE that duplicates shared utilities for the standalone `/portal` route.

| Duplicated Function/Block | Locations | LOC Wasted |
|--------------------------|-----------|------------|
| HTML escape (`esc()`) | admin-manager-core.js:80, admin-portal-manager.js:80, admin-queue.js:301, admin-queue-alerts.js (bridge) | ~28 |
| `invoiceStatusLabel()` | admin-manager-core.js:93, admin-portal-manager.js:93 | ~20 |
| `friendlyApiError()` | admin-manager-core.js:112, admin-portal-manager.js:112 | ~20 |
| `rowsToCsv()` | admin-manager-core.js:89, admin-portal-manager.js:89 | ~6 |
| Invite form HTML | admin-manager-tools.js:156-197, admin-queue-client-invites.js:96-157 | ~85 |
| `buildDrawerHtml()` | admin-manager-clients.js, admin-portal-manager.js | ~90 |

#### Oversized functions

| Function | File | Lines | Issue |
|----------|------|-------|-------|
| `updateBinderUi()` | admin-queue.js:698 | ~140 | Filter UI + state management + event binding |
| `renderBoard()` | admin-queue.js:1465 | ~150 | Swimlanes + columns + cards + phone layout |

#### File sizes

| File | Lines | Responsibilities |
|------|-------|-----------------|
| admin-queue.js | 3,503 | Board rendering, drag-and-drop, filters, binder UI, task drawer |
| admin-portal-manager.js | 1,933 | Consolidated portal build (mirrors core + clients + reports + tools) |
| admin-manager-core.js | 990 | Module factory, shared state, event delegation, form handlers |
| admin-manager-reports.js | 737 | Billing, work statements, invoice drafts |
| admin-queue-modals.js | 660 | Compose panel, done/revision/delete modals |
| admin-manager-tools.js | 501 | Invites, AI import, files, swimlane config |
| admin-queue-alerts.js | 493 | Notification stack, alert rendering |
| admin-manager-clients.js | 277 | Client org tree + drawer rendering |
| admin-queue-client-invites.js | 251 | Client-side invite management |

#### Error handling

~12% of async `fetch()` chains lack `.catch()` handlers -- silent failures in edge cases.

---

### 3.4 CSS

Single file: `assets/css/admin-queue.css` at 5,502 lines, 925+ selectors.

**Good:** Consistent `wp-pq-` prefix throughout. Uses BEM-adjacent naming (`wp-pq-board-column-list`). State modifiers via `.is-active`, `.is-collapsed`, `.is-selected`.

**Issues:** No minification pipeline. No CSS preprocessor. No media query organization strategy. Candidates for extraction: portal-specific styles, modal styles, print styles, tree styles.

---

### 3.5 Documentation

All 21+ markdown files are classified as **active/current**. No orphaned or stale docs found.

| Doc | Lines | Status | Notes |
|-----|-------|--------|-------|
| `README.md` | ~100 | Active | Plugin overview |
| `FLOW.md` | ~796 | Active | Architecture + workflow states |
| `docs/DEVELOPER_HANDOFF_2026-03-21.md` | 297 | Active | Onboarding, deployment, smoke tests |
| `docs/PUNCHLIST.md` | 127 | Active | 12 deferred refactor targets |
| `docs/RELEASE_TRACKING.md` | 125 | Active | Version shipping checklist |
| `docs/BOARD_REDESIGN_SPEC.md` | 198 | Active | UI redesign rules |
| `docs/INVITE_WORKFLOW_SPEC.md` | 349 | Active | Magic-link invite spec |
| `docs/PER_USER_OAUTH_SPEC.md` | 208 | Active | Google OAuth implementation plan |
| `docs/UNIFIED_CONVERSATION_SPEC.md` | 134 | Active | Messages/Notes API design |
| `docs/WORKFLOW_LEDGER_REFACTOR_SPEC.md` | 116 | Active | Ledger refactoring plan |
| `docs/READABILITY_HYGIENE.md` | 32 | Active | Code standards for billing/AI paths |
| `docs/NEXT_JS_ROADMAP.md` | 10,837 | Active (future) | Next.js migration plan -- very large, consider splitting |
| `docs/multitenant-v1/` (4 files) | ~270 | Active (future) | Multi-tenant architecture planning |

**Recommendations:**
- `docs/NEXT_JS_ROADMAP.md` at 10,837 lines is unwieldy. Consider splitting by bounded context (auth, billing, task management) with a top-level index.
- `docs/PUNCHLIST.md` title still says "Priority Queue". Align with "Switchboard" branding.
- Consider adding a `CONTRIBUTING.md` covering local setup, coding standards, and commit conventions.

---

### 3.6 Tooling and Infrastructure

| Tool | Present? | Notes |
|------|----------|-------|
| Test suite (PHP) | No | No PHPUnit |
| Test suite (JS) | No | No Jest/Mocha |
| ESLint | No | No config file |
| Prettier | No | No config file |
| Stylelint | No | No config file |
| PHPCS / PHPStan | No | No config file |
| CI/CD | No | No GitHub Actions |
| Build pipeline | Minimal | `bin/build-zip.sh` produces versioned ZIPs |
| Deploy automation | Manual | SSH/rsync via Claude settings whitelist |

---

## 4. Ranked Issues

### Critical (fix immediately)

| # | Issue | File(s) | Impact |
|---|-------|---------|--------|
| 1 | `rowsToCsv()` infinite recursion | `assets/js/admin-portal-manager.js:89` | Stack overflow on any CSV export from portal. Runtime crash. |
| 2 | `.env` newline injection | `includes/class-wp-pq-installer.php:94-97, 136-139` | Admin-supplied credentials can inject arbitrary env vars into relay config. |

### Important (address in next sprint)

| # | Issue | File(s) | Impact |
|---|-------|---------|--------|
| 3 | Global OAuth state race condition | `includes/class-wp-pq-google-auth.php:122` | Concurrent OAuth flows can CSRF each other. |
| 4 | God class: WP_PQ_API (4,656 LOC) | `includes/class-wp-pq-api.php` | Unmaintainable; every change risks regression across unrelated features. |
| 5 | God class: WP_PQ_Admin (3,630 LOC) | `includes/class-wp-pq-admin.php` | 700 lines of DB queries belong in WP_PQ_DB. |
| 6 | No test suite | repo-wide | No regression safety net despite rapid release cadence (19+ versions in 6 weeks). |
| 7 | No linting/formatting config | repo-wide | Inconsistencies accumulate; no automated quality gate. |
| 8 | Static caches with no invalidation | `includes/class-wp-pq-db.php` | Stale data in long-running processes or WP-CLI. |
| 9 | No rate limiting on login/API | REST API, portal | Brute-force and abuse vector. |

### Important (UX improvements -- Clients view) -- ALL DONE (v0.48.0)

| # | Issue | File(s) | Status |
|---|-------|---------|--------|
| 10 | Search field not filtering | `admin-manager-core.js`, `admin-portal-manager.js` | **Fixed** -- added `input` listener on `managerToolbar` |
| 11 | New Client modal too lean | `admin-manager-clients.js`, `admin-portal-manager.js` | **Fixed** -- added collapsible address/billing fields; API extended |
| 12 | Details button unstyled | `assets/css/admin-queue.css` | **Fixed** -- restyled as compact outlined button |
| 13 | Detail drawer clipped by WP admin bar | `assets/css/admin-queue.css` | **Fixed** -- `top: 32px`, `height: calc(100vh - 32px)` |
| 14 | Job deletion UX incomplete | `admin-manager-core.js`, `class-wp-pq-manager-api.php` | **Fixed** -- Move + Delete with task reassignment and TYPE DELETE confirmation |

#### Job Management UX Design (approved)

Current behavior: jobs with tasks/work logs/invoices cannot be deleted at all. The delete button fails silently with a toast. This should be replaced with two distinct operations:

**Operation 1: "Move to another client"** -- Reassigns the job (and all its tasks, work logs, invoice lines) to a different existing client, or to a newly created client. Safe, non-destructive. UI: dropdown of existing clients + "Create new" option.

**Operation 2: "Delete job"** -- For jobs with tasks, show a confirmation dialog:
- Display: "This job has N tasks. They will be moved to [default job dropdown]. Type DELETE to confirm."
- Tasks are reassigned to the selected target job before the source job is deleted.
- Optional checkbox: "Also delete all N tasks permanently" (cascading delete).
- Requires typing "DELETE" in a text field to enable the confirm button.
- For empty jobs (no tasks, no work logs, no invoices): simple confirmation is sufficient.

Implementation touches: `class-wp-pq-manager-api.php` (new endpoints for job transfer and cascading delete), `class-wp-pq-db.php` (task reassignment helper), JS drawer HTML (new UI), CSS (confirmation dialog styles).

### Nice-to-have (backlog)

| # | Issue | File(s) | Impact |
|---|-------|---------|--------|
| 15 | 4 duplicate HTML escape implementations | JS files (see 3.3) | Maintenance burden, divergence risk. |
| 16 | Duplicate invite form HTML (~85 lines) | admin-manager-tools.js, admin-queue-client-invites.js | Same markup in two places. |
| 17 | Duplicate `buildDrawerHtml()` (~90 lines) | admin-manager-clients.js, admin-portal-manager.js | Will diverge over time. |
| 18 | 12% of async calls missing `.catch()` | across 8 JS files | Silent failures in edge cases. |
| 19 | CSS single-file monolith (5,502 lines) | `assets/css/admin-queue.css` | Hard to navigate; no separation of concerns. |
| 20 | Oversized JS functions | `assets/js/admin-queue.js` | `updateBinderUi()` ~140 lines, `renderBoard()` ~150 lines. |
| 21 | `NEXT_JS_ROADMAP.md` at 10,837 lines | `docs/NEXT_JS_ROADMAP.md` | Unwieldy single document. |

---

## 5. Recommended Refactor Targets

### 5.1 PHP Refactors (align with docs/PUNCHLIST.md)

1. **Decompose `move_task()`** in `class-wp-pq-api.php` (~280 lines, 6 concerns). Extract: `apply_status_transition()`, `shift_priorities()`, `swap_due_dates()`, `reorder_queue()`, `record_history()`, `emit_move_event()`.

2. **Move ~700 lines of DB queries from WP_PQ_Admin to WP_PQ_DB** -- 11 specific methods listed in `PUNCHLIST.md`.

3. **Fix `.env` writer** in `class-wp-pq-installer.php:94-97, 136-139` -- strip `\n`, `\r`, `=` from credential values before interpolation.

4. **Per-user OAuth state** in `class-wp-pq-google-auth.php:122` -- replace `update_option('wp_pq_google_oauth_state', $state)` with `set_transient('wp_pq_oauth_state_' . get_current_user_id(), $state, 600)`.

5. **Extract service classes from WP_PQ_API**: `WP_PQ_Task_Service`, `WP_PQ_Billing_Service`, `WP_PQ_Notification_Service`, `WP_PQ_Event_Service`.

### 5.2 JavaScript Refactors

1. **Fix `rowsToCsv()`** in `admin-portal-manager.js:89` -- replace the self-call with the actual implementation from `admin-manager-core.js:89`.

2. **Extract shared utilities module** -- create `assets/js/shared-utils.js` containing `esc()`, `invoiceStatusLabel()`, `friendlyApiError()`, `rowsToCsv()`. Both the module-based admin pages and the portal IIFE can consume it.

3. **Add `.catch()` to all fetch chains** -- audit all 9 JS files for uncaught promise rejections.

4. **Break up `admin-queue.js`** (3,503 lines) -- extract `renderBoard()`, `updateBinderUi()`, and drag-and-drop handling into separate modules.

5. **Add ESLint + Prettier** -- create `.eslintrc.json` and `.prettierrc` matching existing code style.

### 5.3 CSS Refactors

1. Split `admin-queue.css` into logical partials: `board.css`, `drawer.css`, `modals.css`, `portal.css`, `tree.css`.
2. Add Stylelint config.
3. Add minification to the build pipeline.

---

## 6. Documentation Cleanup Recommendations

| Doc | Action | Reason |
|-----|--------|--------|
| `docs/NEXT_JS_ROADMAP.md` | Split into per-domain docs | 10,837 lines is unwieldy; split by auth, billing, tasks, etc. with index |
| `docs/PUNCHLIST.md` | ~~Update title to "Switchboard"~~ | **Done** |
| `docs/PUNCHLIST.md` | Add priority/effort estimates | 12 items lack sizing |
| (new) `CONTRIBUTING.md` | Create | Local setup, coding standards, commit conventions |
| `docs/DEVELOPER_HANDOFF_2026-03-21.md` | ~~Update version numbers~~ | **Done** -- annotated with current v0.48.0 |
| All mockups | Keep | All 3 are recent (v0.40+), actively referenced |
| `dist/*.zip` | Keep | Versioned release artifacts |

---

## 7. Phased Action Plan

### Phase 0: Immediate Fixes (1 day) -- DONE

- [x] Fix `rowsToCsv()` infinite recursion in `assets/js/admin-portal-manager.js:89`
- [x] Sanitize `.env` writer inputs in `includes/class-wp-pq-installer.php` -- added `sanitize_env_value()` helper
- [x] Switch OAuth state to per-user transient in `includes/class-wp-pq-google-auth.php:122` -- also added state validation in callback

### Phase 1: Tooling Foundation (1 week) -- PARTIALLY DONE

- [x] Add ESLint + Prettier config (`eslint.config.js`, `.prettierrc`)
- [x] Add Stylelint config (`.stylelintrc.json`)
- [x] Add PHPStan level 5 config (`phpstan.neon`, `composer.json`)
- [ ] Create first smoke tests for `WP_PQ_DB::create_tables()` and `WP_PQ_Installer::needs_setup()`
- [ ] Add basic CI pipeline (GitHub Actions) -- lint + tests on push

### Phase 2: God Class Decomposition (2-3 weeks)

- [ ] Extract service classes from `WP_PQ_API`: Task, Billing, Notification, Event services
- [ ] Move 700 lines of DB queries from `WP_PQ_Admin` to `WP_PQ_DB`
- [ ] Decompose `move_task()` into 6 focused helpers
- [ ] Write integration tests for each extracted service

### Phase 3: JS Consolidation (1-2 weeks)

- [ ] Create `assets/js/shared-utils.js` and eliminate all duplicate utility implementations
- [ ] Extract invite form template to shared partial
- [x] Add `.catch()` to all fetch chains (only 1 found: `admin-queue-alerts.js:426`)
- [ ] Break `admin-queue.js` into logical modules

### Phase 4: Hardening (ongoing)

- [ ] Add rate limiting (WordPress application passwords or lightweight middleware)
- [ ] Add cache TTL/invalidation strategy to static caches
- [ ] Add pagination to unbounded queries
- [ ] Add composite DB indexes per PUNCHLIST
- [ ] Split CSS monolith into logical partials

---

## Appendix: Key Files Reference

| File | Role | LOC | Priority |
|------|------|-----|----------|
| `assets/js/admin-portal-manager.js` | Portal consolidated build (contains critical bug) | 1,933 | Phase 0 |
| `includes/class-wp-pq-installer.php` | Plugin activation (contains .env injection) | 171 | Phase 0 |
| `includes/class-wp-pq-google-auth.php` | OAuth (contains race condition) | 527 | Phase 0 |
| `includes/class-wp-pq-api.php` | Main REST API (god class) | 4,656 | Phase 2 |
| `includes/class-wp-pq-admin.php` | Admin interface (god class) | 3,630 | Phase 2 |
| `assets/js/admin-queue.js` | Board rendering (oversized) | 3,503 | Phase 3 |
| `assets/css/admin-queue.css` | All styles (monolith) | 5,502 | Phase 4 |
| `docs/PUNCHLIST.md` | Existing refactor backlog (21 items) | 127 | Reference |
