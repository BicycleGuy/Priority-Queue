# WP Priority Queue Portal — Architecture Flowchart

## System Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        WORDPRESS CORE                                   │
│                                                                         │
│  plugins_loaded ──► WP_PQ_Plugin::boot()                               │
│                      ├── DB migrations (12 migrate_* calls)             │
│                      ├── WP_PQ_Housekeeping::init()  ► cron hooks      │
│                      ├── WP_PQ_Admin::init()         ► wp-admin pages  │
│                      ├── WP_PQ_API::init()           ► REST routes     │
│                      ├── WP_PQ_Manager_API::init()   ► REST routes     │
│                      └── WP_PQ_Portal::init()        ► shortcode + JS  │
│                                                                         │
│  register_activation_hook  ──► WP_PQ_Installer::activate()             │
│                                 ├── register_roles_and_caps()           │
│                                 ├── create_tables() (19 tables)        │
│                                 ├── ensure_default_billing_buckets()    │
│                                 ├── set_default_options()               │
│                                 └── schedule cron                       │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Canonical Statuses (Source of Truth)

One status system across DB, PHP, API, JS, and UI. No duplicates. No aliases.

```
  pending_approval      Waiting for manager approval
  approved              Ready to begin work
  needs_clarification   Waiting on additional information
  in_progress           Active execution
  needs_review          Ready for internal review
  delivered             Delivered to client/requester
  done                  Completed — billing captured
  archived              Closed out — off the board permanently
```

**Board columns** show: `pending_approval` through `delivered` (6 columns).
`done` and `archived` do not appear on the board.

**Legacy aliases** (`draft`, `not_approved`, `pending_review`, `revision_requested`,
`completed`) exist only in `status_aliases()` for data migration safety. No runtime
code depends on them. All JS sends canonical values. All API responses return
canonical values.

---

## User Roles & Capabilities

```
┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│  WP Admin    │   │  PQ Manager  │   │  PQ Worker   │   │  PQ Client   │
│              │   │              │   │              │   │              │
│ All PQ caps  │   │ view_all     │   │ work_tasks   │   │ read         │
│              │   │ reorder_all  │   │ upload_files │   │ upload_files │
│              │   │ approve      │   │              │   │              │
│              │   │ assign       │   │              │   │              │
│              │   │ work_tasks   │   │              │   │              │
└──────┬───────┘   └──────┬───────┘   └──────┬───────┘   └──────┬───────┘
       │                  │                  │                   │
       │    Manager API   │                  │                   │
       ├──────────────────┤                  │                   │
       │    (clients,     │                  │                   │
       │     billing,     │   Task API       │   Task API        │
       │     statements,  ├──────────────────┤   (own tasks,     │
       │     AI import)   │   (all tasks)    │    messages)      │
       │                  │                  │                   │
       └──────────────────┴──────────────────┴───────────────────┘
```

---

## Frontend Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    [pq_client_portal] SHORTCODE                         │
│                    (WP_PQ_Portal::render_shortcode)                     │
│                                                                         │
│  Outputs full HTML app shell into any WordPress page                   │
│  ├── App shell (sidebar + workspace)                                   │
│  ├── Board columns / Calendar / List views                             │
│  ├── Task detail panel                                                 │
│  ├── Modals (completion, move, delete, create)                         │
│  └── Manager sections (clients, billing, statements, AI)               │
│                                                                         │
│  Enqueues:                                                             │
│  ├── admin-queue.css             (all styles)                          │
│  ├── admin-queue.js              (~2,500 lines — core controller)     │
│  ├── admin-queue-modals.js       (~620 lines — modal systems)        │
│  ├── admin-queue-alerts.js       (~310 lines — alerts & prefs)       │
│  ├── admin-portal-manager.js     (~1,700 lines — manager features)   │
│  ├── SortableJS                  (drag-and-drop reorder)              │
│  ├── FullCalendar                (calendar view)                      │
│  └── Uppy                        (file uploads)                       │
│                                                                         │
│  Injects wpPqConfig:                                                   │
│  { apiBase, nonce, userId, userRole, canApprove, canAssign, ... }      │
└─────────────────────────────────────────────────────────────────────────┘
```

### JavaScript Load Order & Bridge Pattern

```
  wp-pq-admin (admin-queue.js)
       │
       ├── Sets window.wpPqPortalUI (narrow bridge)
       │   { getActiveTask, getKnownTask, getSelectedTaskId,
       │     setSelectedTaskId, api, alert, upsertTask, loadTasks,
       │     selectTask, refreshFromCache, syncOrderFromBoardDom,
       │     activateWorkspaceTab, currentView, humanizeToken,
       │     escapeHtml, formatDateTime, removeTaskById,
       │     closeDrawer, openDrawer, focusMeetingStart }
       │
       ├──► wp-pq-modals (admin-queue-modals.js)
       │    Reads: window.wpPqPortalUI (18 methods)
       │    Sets:  window.wpPqModals
       │           { openMoveModal, openRevisionModal, openCompletionModal,
       │             openDeleteModal, close*Modal, shouldPromptForMoveDecision,
       │             setPendingMove, setPendingStatusAction, setPendingRevisionAction }
       │
       ├──► wp-pq-alerts (admin-queue-alerts.js)
       │    Reads: window.wpPqPortalUI (8 methods)
       │    Sets:  window.wpPqAlerts
       │           { loadPrefs, loadInbox, dismissNotifications,
       │             openTaskFromAlert, openPreferences, closePreferences,
       │             refreshPreferences }
       │
       └──► wp-pq-portal-manager (admin-portal-manager.js)
            Reads: window.wpPqAlerts.openPreferences
            Sets:  window.wpPqPortalManager { openSection }
```

### JavaScript App Controller (admin-queue.js)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       FRONTEND APP CONTROLLER                           │
│                                                                         │
│  INIT                                                                  │
│  ├── Parse wpPqConfig                                                  │
│  ├── Cache DOM references                                              │
│  ├── Wire event listeners (board, forms, sidebar, status actions)      │
│  ├── Set window.wpPqPortalUI bridge (before satellite scripts run)    │
│  ├── loadTasks() ──► GET /pq/v1/tasks ──► replaceTasks(cache)         │
│  └── Render initial view (board/calendar/list)                         │
│                                                                         │
│  VIEWS ─────────────────────────────────────────────────────────────── │
│  ├── Board View     renderBoard()     Kanban columns by status         │
│  ├── Calendar View  renderCalendar()  FullCalendar integration         │
│  └── List View      renderList()      Flat task list                   │
│                                                                         │
│  TASK SELECTION ────────────────────────────────────────────────────── │
│  selectTask(id)                                                        │
│  ├── updateTaskSummary(task)  ► sets activeTaskRecord                  │
│  ├── loadWorkers(task)        ► populates assignment panel             │
│  ├── syncAssignmentPanel()    ► action_owner + collaborators           │
│  ├── syncPriorityPanel()      ► priority dropdown                     │
│  └── loadActiveWorkspacePane()                                         │
│      ├── Messages tab    ► GET /tasks/{id}/messages                    │
│      ├── Notes tab       ► GET /tasks/{id}/notes                       │
│      ├── Files tab       ► GET /tasks/{id}/files                       │
│      ├── Meetings tab    ► GET /tasks/{id}/meetings                    │
│      ├── Activity tab    ► (status history from task data)             │
│      └── Billing tab     ► (from task enrichment)                      │
│                                                                         │
│  BOARD DRAG-AND-DROP ──────────────────────────────────────────────── │
│  initBoardSort() → SortableJS                                          │
│  ├── Same-column reorder → POST /tasks/reorder                         │
│  ├── Cross-column move   → calls wpPqModals.openMoveModal()           │
│  └── Simple transition   → POST /tasks/move (no modal needed)         │
│                                                                         │
│  STATUS BUTTONS ──────────────────────────────────────────────────── │
│  ├── in_progress shows: Needs Review, Delivered, Needs Clarification  │
│  ├── Delivered button (from in_progress): window.confirm() review-skip│
│  └── done button: opens completion modal (captures billing)           │
│                                                                         │
│  Does NOT contain: modals, alerts, notifications, or preferences.     │
│  Those live in satellite files that communicate via the bridge.        │
└─────────────────────────────────────────────────────────────────────────┘
```

### Modal Systems (admin-queue-modals.js)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       MODAL SYSTEMS                                     │
│                    Reads from window.wpPqPortalUI bridge               │
│                                                                         │
│  Each modal follows the same lifecycle:                                │
│    open*Modal()  → populate fields, show backdrop                     │
│    close*Modal() → hide, reset form, optionally refresh board         │
│    wire*Modal()  → attach submit/cancel/backdrop listeners             │
│                                                                         │
│  ├── Revision Modal     Request changes with note + optional message  │
│  │   ├── Sends canonical status: in_progress (not revision_requested) │
│  │   └── POST /tasks/{id}/status or /tasks/move                       │
│  │                                                                     │
│  ├── Move Modal         Cross-column move with options                │
│  │   ├── Meeting request checkbox, email notification option           │
│  │   ├── Context-aware: shows "Deliver Without Review?" when          │
│  │   │   dragging from in_progress directly to delivered               │
│  │   └── POST /tasks/move                                              │
│  │                                                                     │
│  ├── Completion Modal   Capture billing details when marking done     │
│  │   ├── Billing mode, hours, rate, amount, work summary              │
│  │   ├── Auto-selects non_billable when action_owner is client        │
│  │   └── POST /tasks/{id}/done → writes to work ledger               │
│  │                                                                     │
│  └── Delete Modal       Confirm task deletion                         │
│      └── DELETE /tasks/{id}                                            │
└─────────────────────────────────────────────────────────────────────────┘
```

### Alerts, Notifications & Preferences (admin-queue-alerts.js)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    NOTIFICATIONS & PREFERENCES                          │
│                    Reads from window.wpPqPortalUI bridge               │
│                                                                         │
│  INBOX ────────────────────────────────────────────────────────────── │
│  loadInbox()              GET /notifications → render alert cards      │
│  dismissNotifications()   POST /notifications/mark-read                │
│  openTaskFromAlert()      Navigate to task from alert card             │
│  30-second polling interval for new notifications                      │
│                                                                         │
│  ALERT STACK ──────────────────────────────────────────────────────── │
│  renderPersistentAlerts() Render notification cards into DOM           │
│  scheduleAlertDismiss()   Auto-dismiss after 2.6s (if pref enabled)   │
│                                                                         │
│  PREFERENCES ──────────────────────────────────────────────────────── │
│  loadPrefs()              GET /notification-prefs                      │
│  wirePrefs()              Save button → POST /notification-prefs       │
│  openPreferencesPanel()   Show preferences panel                      │
│  closePreferencesPanel()  Hide preferences panel                      │
└─────────────────────────────────────────────────────────────────────────┘
```

### JavaScript Manager (admin-portal-manager.js)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       MANAGER FEATURES                                  │
│                    (only loaded for CAP_APPROVE users)                  │
│                                                                         │
│  SIDEBAR SECTIONS                                                      │
│  ├── Clients          renderClients()                                  │
│  │   ├── List view    GET /manager/clients                             │
│  │   ├── Detail view  GET /manager/clients/{id}                        │
│  │   ├── Create       POST /manager/clients                            │
│  │   ├── Update       POST /manager/clients/{id}                       │
│  │   ├── Add member   POST /manager/clients/{id}/members               │
│  │   ├── Create job   POST /manager/jobs                               │
│  │   ├── Delete job   DELETE /manager/jobs/{id}                        │
│  │   └── Assign job member  POST /manager/jobs/{id}/members            │
│  │                                                                     │
│  ├── Billing Rollup   renderBillingRollup()                            │
│  │   ├── List view    GET /manager/rollups                             │
│  │   └── Assign job   POST /manager/rollups/assign-job                 │
│  │                                                                     │
│  ├── Monthly Stmts    renderMonthlyStatements()                        │
│  │   └── List view    GET /manager/monthly-statements                  │
│  │                                                                     │
│  ├── Work Statements  renderWorkStatements()                           │
│  │   ├── List view    GET /manager/work-logs                           │
│  │   ├── Detail view  GET /manager/work-logs/{id}                      │
│  │   └── Create       POST /manager/work-logs                          │
│  │                                                                     │
│  ├── Invoice Drafts   renderInvoiceDrafts()                            │
│  │   ├── List view    GET /manager/statements                          │
│  │   ├── Detail view  GET /manager/statements/{id}                     │
│  │   ├── Create       POST /manager/statements                         │
│  │   ├── Update       POST /manager/statements/{id}                    │
│  │   ├── Delete       DELETE /manager/statements/{id}                  │
│  │   ├── Add line     POST /manager/statements/{id}/lines              │
│  │   ├── Update line  POST /manager/statements/{id}/lines/{line_id}    │
│  │   ├── Delete line  DELETE /manager/statements/{id}/lines/{line_id}  │
│  │   ├── Remove task  DELETE /manager/statements/{id}/tasks/{task_id}  │
│  │   ├── Record pay   POST /manager/statements/{id}/payment            │
│  │   └── Email client POST /manager/statements/{id}/email-client       │
│  │                                                                     │
│  └── AI Import        renderAiImport()                                 │
│      ├── Parse text   POST /manager/ai-import/parse  ──► OpenAI       │
│      ├── Revalidate   POST /manager/ai-import/revalidate               │
│      ├── Import tasks POST /manager/ai-import/import                   │
│      └── Discard      POST /manager/ai-import/discard                  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Task Lifecycle (State Machine)

```
                          ┌─────────────────┐
                          │ PENDING_APPROVAL │ ◄── task created
                          └────────┬────────┘
                                   │
                    ┌──────────────┼──────────────┐
                    ▼              │               ▼
          ┌─────────────────┐     │    ┌──────────────────────┐
          │    APPROVED      │ ◄──┼────│ NEEDS_CLARIFICATION  │
          └────────┬────────┘     │    └──────┬───┬───────────┘
                   │              │           │   │
                   ▼              │           │   │ (mid-work: resume)
          ┌─────────────────┐     │           │   │
          │  IN_PROGRESS     │ ◄──┘───────────┘───┘
          └────────┬────────┘
                   │
          ┌────────┼──────────────────────┐
          │        │                      │
          │        ▼                      ▼  (skip review — confirm required)
          │  ┌─────────────────┐   ┌───────────┐
          │  │  NEEDS_REVIEW    │   │ DELIVERED  │
          │  └────────┬────────┘   └─────┬─────┘
          │           │                  │
          │  ┌────────┼───────┐          │
          │  │        │       │          │
          │  │        ▼       ▼          │
          │  │  ┌──────────┐ ┌────────┐  │
          │  │  │DELIVERED │ │IN_PROG.│  │
          │  │  └────┬─────┘ │(revsn) │  │
          │  │       │       └────────┘  │
          │  │       │                   │
          │  └───────┼───────────────────┘
          │          │
          │          ▼
          │    ┌───────────┐
          └───►│   DONE     │ ◄── completion modal captures billing
               └─────┬─────┘
                     │
                     ▼  (manager only)
               ┌───────────┐
               │  ARCHIVED  │  No transitions out. Reopen creates
               └───────────┘  follow-up or moves back to active status.
```

### Explicit Transition Matrix

```
  FROM                  → TO (allowed)
  ─────────────────────────────────────────────────────────────────
  pending_approval      → approved, needs_clarification
  needs_clarification   → approved, in_progress
  approved              → in_progress, needs_clarification
  in_progress           → needs_clarification, needs_review, delivered
  needs_review          → in_progress, delivered
  delivered             → in_progress, needs_clarification, needs_review, done
  done                  → archived, in_progress, needs_clarification, needs_review
  archived              → (none — use reopen or follow-up)
```

### Permission Rules

```
  approve / request clarification (→ approved, →needs_clarification): CAP_APPROVE only
  archive (→ archived):                                       CAP_APPROVE only
  work transitions (→ in_progress, needs_review, delivered,
                      needs_clarification, done):             CAP_APPROVE or CAP_WORK
```

### Confirmation Dialogs

```
  in_progress → delivered (button click):  window.confirm("Proceed without third-party review?")
  in_progress → delivered (drag-and-drop): move modal title "Deliver Without Review?"
  delivered   → done:                      completion modal (captures billing)
  done        → archived:                  status button (manager only)
```

### Timestamp Behavior

```
  → delivered:   sets delivered_at
  → done:        sets done_at
  → archived:    sets archived_at
  reopen:        clears done_at, archived_at
```

---

## Billing & Invoicing Pipeline

```
  Task completed (status → done)
       │
       ▼
  ┌──────────────────┐
  │ Completion Modal  │  User picks: billing_mode, hours, rate, amount
  │ billing details   │  work_summary, billing_category
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ Work Ledger Entry │  pq_work_ledger_entries table
  │ invoice_status:   │  ├── unbilled (billable work)
  │   unbilled |      │  └── written_off / "No Charge" (non-billable)
  │   written_off     │
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐     ┌──────────────────┐
  │ Billing Rollup   │────►│ Monthly Statement │  Aggregated view
  │ (unbatched items)│     │ (by client/month) │  counts by status
  └────────┬─────────┘     └──────────────────┘
           │
           ▼  Manager batches items
  ┌──────────────────┐
  │ Work Statement   │  pq_work_logs + pq_work_log_items
  │ (work log)       │  Groups ledger entries for a period
  └────────┬─────────┘
           │
           ▼  Manager creates invoice draft
  ┌──────────────────┐
  │ Invoice Draft    │  pq_statements + pq_statement_items
  │ (statement)      │  ├── Add custom line items
  │                  │  ├── Set amounts, discounts
  │                  │  └── Attach work statement
  └────────┬─────────┘
           │
           ├──► Email to client  (POST .../email-client → wp_mail)
           │
           ▼  Manager records payment
  ┌──────────────────┐
  │ Payment Recorded │  statement status → paid
  │                  │  All linked ledger entries → paid
  └──────────────────┘

  Reopen behavior:
  ├── done → active status: clears timestamps, reopens ledger entry
  ├── blocked if ledger entry is invoiced or paid
  └── follow-up task created instead when blocked
```

---

## Notification System

```
  ┌─────────────────────────────────────────────────────────────────┐
  │                    EVENT TRIGGERS                                │
  │                                                                 │
  │  Status change ──► emit_status_events()                        │
  │  Assignment    ──► emit_assignment_event()                     │
  │  @mention      ──► notify_mentions()                           │
  │  Daily cron    ──► send_client_daily_digests()                 │
  │  File aging    ──► retention reminders (day 300)               │
  └──────────────┬──────────────────────────────────────────────────┘
                 │
                 ▼
  ┌──────────────────────────┐
  │  For each recipient:     │
  │  1. create_notification()│ ──► pq_notifications table (in-app)
  │  2. is_event_enabled()?  │ ──► pq_notification_prefs table
  │     └── yes ──► wp_mail()│ ──► WordPress mail system
  └──────────────────────────┘         │
                                       ▼
                               ┌───────────────────┐
                               │ SMTP plugin needed │
                               │ (not built-in)     │
                               └───────────────────┘

  Configurable events per user (Preferences panel):
  ├── task_created              ├── task_returned_to_work
  ├── task_assigned             ├── task_delivered
  ├── task_approved             ├── task_archived
  ├── task_rejected             ├── statement_batched
  ├── task_mentioned            ├── client_status_updates
  ├── task_reprioritized        ├── client_daily_digest
  └── task_schedule_changed     └── retention_day_300
```

---

## Data Model (19 Tables)

```
  ┌─────────────┐       ┌──────────────────┐      ┌────────────────┐
  │ pq_clients  │──┐    │ pq_billing_      │      │ pq_tasks       │
  │             │  │    │ buckets (jobs)   │      │                │
  │ name        │  │    │                  │      │ title          │
  │ slug        │  │    │ name             │      │ description    │
  │ primary_    │  │    │ client_id ───────┤      │ status         │
  │ contact_    │  │    │ hourly_rate      │      │ priority       │
  │ user_id     │  │    │                  │      │ client_id ─────┤──►
  └──────┬──────┘  │    └────────┬─────────┘      │ billing_       │
         │         │             │                 │ bucket_id ─────┤──►
         ▼         │             ▼                 │ submitter_id   │
  ┌──────────────┐ │    ┌──────────────────┐      │ action_owner_id│
  │ pq_client_   │ │    │ pq_job_members   │      │ owner_ids []   │
  │ members      │ │    │                  │      │ billing_mode   │
  │              │ │    │ billing_bucket_id │      │ billing_status │
  │ client_id ───┤ │    │ user_id          │      │ ...            │
  │ user_id      │ │    └──────────────────┘      └───────┬────────┘
  │ role         │ │                                      │
  └──────────────┘ │                        ┌─────────────┼──────────────┐
                   │                        │             │              │
                   │                        ▼             ▼              ▼
                   │             ┌─────────────┐  ┌────────────┐  ┌──────────┐
                   │             │ pq_task_     │  │ pq_task_   │  │ pq_task_ │
                   │             │ messages     │  │ comments   │  │ files    │
                   │             │ (client-     │  │ (internal  │  │          │
                   │             │  visible)    │  │  notes)    │  │ media_id │
                   │             └─────────────┘  └────────────┘  │ file_role│
                   │                                              └──────────┘
                   │
                   │  ┌────────────────────────┐    ┌──────────────────────┐
                   │  │ pq_task_status_history │    │ pq_task_meetings     │
                   │  │                        │    │                      │
                   │  │ task_id                │    │ task_id              │
                   │  │ old_status → new_status│    │ google event_id      │
                   │  │ changed_by             │    │ meeting_url          │
                   │  │ reason_code            │    │ starts_at / ends_at  │
                   │  └────────────────────────┘    └──────────────────────┘
                   │
                   │  BILLING TABLES
                   │  ┌──────────────────────┐     ┌────────────────────┐
                   │  │ pq_work_ledger_      │     │ pq_work_logs       │
                   │  │ entries              │     │ (work statements)  │
                   │  │                      │     │                    │
                   │  │ task_id              │     │ client_id          │
                   │  │ billable (0/1)       │────►│ period, total_hrs  │
                   │  │ invoice_status       │     │                    │
                   │  │ hours, rate, amount  │     └────────┬───────────┘
                   │  └──────────────────────┘              │
                   │                                        ▼
                   │  ┌──────────────────────┐     ┌────────────────────┐
                   │  │ pq_work_log_items    │     │ pq_statements      │
                   │  │                      │     │ (invoice drafts)   │
                   │  │ work_log_id          │     │                    │
                   │  │ ledger_entry_id      │     │ client_id          │
                   │  └──────────────────────┘     │ status (draft/     │
                   │                               │   sent/paid)       │
                   │  ┌──────────────────────┐     │ total_amount       │
                   │  │ pq_statement_items   │     └────────────────────┘
                   │  │                      │     ┌────────────────────┐
                   │  │ statement_id         │     │ pq_statement_lines │
                   │  │ task_id              │     │ (custom charges)   │
                   │  └──────────────────────┘     │ statement_id       │
                   │                               │ description        │
                   │  NOTIFICATION TABLES           │ amount             │
                   │  ┌──────────────────────┐     └────────────────────┘
                   │  │ pq_notifications     │
                   │  │                      │
                   │  │ user_id, event_key   │
                   │  │ title, body          │
                   │  │ is_read              │
                   │  └──────────────────────┘
                   │  ┌──────────────────────┐
                   │  │ pq_notification_prefs│
                   │  │                      │
                   │  │ user_id, event_key   │
                   │  │ is_enabled (0/1)     │
                   │  └──────────────────────┘
```

---

## REST API Route Map (50+ endpoints)

```
  PUBLIC (no auth)
  ├── GET/POST  /pq/v1/google/oauth/callback
  ├── GET       /pq/v1/google/oauth/url
  └── POST      /pq/v1/calendar/webhook

  LOGGED-IN USER (is_user_logged_in + can_access_task)
  ├── GET       /pq/v1/tasks                    ► filtered task list
  ├── POST      /pq/v1/tasks                    ► create task
  ├── POST      /pq/v1/tasks/reorder            ► drag-drop reorder
  ├── POST      /pq/v1/tasks/move               ► move between columns
  ├── POST      /pq/v1/tasks/{id}/status        ► transition status
  ├── POST      /pq/v1/tasks/{id}/done          ► complete + ledger
  ├── DELETE    /pq/v1/tasks/{id}               ► delete task
  ├── POST      /pq/v1/tasks/{id}/schedule      ► set due date
  ├── GET/POST  /pq/v1/tasks/{id}/messages      ► client-visible msgs
  ├── GET/POST  /pq/v1/tasks/{id}/notes         ► internal notes
  ├── GET       /pq/v1/tasks/{id}/participants  ► user list
  ├── GET/POST  /pq/v1/tasks/{id}/files         ► file attachments
  ├── GET/POST  /pq/v1/tasks/{id}/meetings      ► calendar events
  ├── GET       /pq/v1/calendar/events          ► calendar feed
  ├── GET/POST  /pq/v1/notification-prefs       ► user preferences
  ├── GET       /pq/v1/notifications            ► in-app notifications
  └── POST      /pq/v1/notifications/mark-read  ► mark read

  CAP_APPROVE (manager / admin)
  ├── POST      /pq/v1/tasks/approve-batch      ► bulk approve
  ├── GET       /pq/v1/google/oauth/status      ► OAuth config
  ├── POST      /pq/v1/statements/batch         ► batch to statement
  │
  │  MANAGER API (/manager/*)
  ├── GET/POST  /manager/clients                ► list / create
  ├── GET/POST  /manager/clients/{id}           ► detail / update
  ├── POST      /manager/clients/{id}/members   ► add member
  ├── POST      /manager/jobs                   ► create job
  ├── DELETE    /manager/jobs/{id}              ► delete job
  ├── POST      /manager/jobs/{id}/members      ► assign job member
  ├── GET       /manager/rollups                ► billing rollup
  ├── POST      /manager/rollups/assign-job     ► assign job to entry
  ├── GET       /manager/monthly-statements     ► monthly aggregates
  ├── GET/POST  /manager/work-logs              ► work statements
  ├── GET/POST  /manager/work-logs/{id}         ► detail / update
  ├── GET/POST  /manager/statements             ► invoice drafts
  ├── GET/POST/DEL /manager/statements/{id}     ► detail/update/delete
  ├── DELETE    /manager/statements/{id}/tasks/{tid}  ► remove task
  ├── POST      /manager/statements/{id}/lines        ► add line
  ├── POST/DEL  /manager/statements/{id}/lines/{lid}  ► update/delete
  ├── POST      /manager/statements/{id}/payment      ► record payment
  ├── POST      /manager/statements/{id}/email-client  ► send invoice
  ├── GET       /manager/ai-import              ► preview state
  ├── POST      /manager/ai-import/parse        ► OpenAI parse
  ├── POST      /manager/ai-import/revalidate   ► update context
  ├── POST      /manager/ai-import/import       ► create tasks
  ├── POST      /manager/ai-import/discard      ► clear preview
  ├── POST      /tasks/{id}/reopen-completed    ► reopen done/archived
  └── POST      /tasks/{id}/followup            ► create followup

  CAP_ASSIGN (manager / admin)
  ├── POST      /pq/v1/tasks/{id}/assignment    ► assign owner
  └── GET       /pq/v1/workers                  ► worker list

  CAP_APPROVE (manager / admin)
  └── POST      /pq/v1/tasks/{id}/priority      ► change priority
```

---

## AI Import Flow

```
  User enters text or pastes document content
       │
       ▼
  POST /manager/ai-import/parse
       │
       ▼
  ┌──────────────────────────────────────────────────┐
  │ WP_PQ_AI_Importer::parse()                       │
  │                                                    │
  │  1. Read OpenAI API key from wp_options            │
  │  2. Build prompt with source_text                  │
  │  3. POST to OpenAI responses API                   │
  │  4. Parse JSON response → array of tasks           │
  │     { title, description, priority, client,        │
  │       billing_bucket, action_owner, due_at }       │
  └──────────────┬───────────────────────────────────┘
                 │
                 ▼
  Preview returned to JS → state.aiPreview
  Toast: "Parsed N tasks. Review below, then click Import Tasks."
       │
       ▼ (optional)
  POST /manager/ai-import/revalidate
  └── Re-match client/job context if user changes dropdowns
       │
       ▼
  User clicks "Import Tasks"
  POST /manager/ai-import/import
       │
       ▼
  For each preview task:
  └── POST /pq/v1/tasks (internal) → creates real task
       │
       ▼
  Toast: "Imported N tasks."
  Board refreshes with new tasks
```

---

## Cron / Housekeeping

```
  wp_pq_daily_housekeeping (runs daily at 8am local)
  │
  ├── File retention reminders
  │   └── Files at 300 days → email submitter
  │
  ├── Expired file cleanup
  │   └── Files past 365 days → wp_delete_attachment + DB delete
  │
  └── Client daily digests
      └── For each client member:
          ├── Gather tasks with status changes since last digest
          ├── Group: Awaiting you / Delivered / Needs clarification / Other
          └── wp_mail() digest email
```

---

## File Structure

```
wp-priority-queue-plugin/
├── wp-priority-queue-plugin.php    Bootstrap, defines, hooks
├── includes/
│   ├── class-wp-pq-plugin.php      Singleton boot, migrations
│   ├── class-wp-pq-installer.php   Activation / deactivation
│   ├── class-wp-pq-roles.php       Role & capability registration
│   ├── class-wp-pq-db.php          Schema, migrations, queries
│   ├── class-wp-pq-workflow.php     Status machine, transitions
│   ├── class-wp-pq-api.php         Core REST API (tasks, messages, files)
│   ├── class-wp-pq-manager-api.php Manager REST API (clients, billing)
│   ├── class-wp-pq-ai-importer.php OpenAI integration
│   ├── class-wp-pq-admin.php       WP Admin pages, settings, rendering
│   ├── class-wp-pq-portal.php      Shortcode, asset registration
│   └── class-wp-pq-housekeeping.php Cron jobs, digests, cleanup
├── assets/
│   ├── css/admin-queue.css          All plugin styles (~2600 lines)
│   └── js/
│       ├── admin-queue.js           Core app controller (~2500 lines)
│       ├── admin-queue-modals.js    Modal systems (~620 lines)
│       ├── admin-queue-alerts.js    Alerts & preferences (~310 lines)
│       └── admin-portal-manager.js  Manager features (~1700 lines)
├── ARCHITECTURE.md                  This file
└── .claude/                         Claude Code commands & agents

Script load order (WordPress wp_enqueue_script dependencies):
  sortable-js, fullcalendar, uppy
    └── wp-pq-admin (admin-queue.js)        ► sets window.wpPqPortalUI
          ├── wp-pq-modals (modals.js)      ► sets window.wpPqModals
          ├── wp-pq-alerts (alerts.js)      ► sets window.wpPqAlerts
          └── wp-pq-portal-manager          ► sets window.wpPqPortalManager
              (depends on admin + modals + alerts)
```
