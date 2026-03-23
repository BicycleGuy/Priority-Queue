# Priority Queue Developer Handoff

Date: 2026-03-21  
Prepared for: next developer pickup  
Project path: `/Users/readspear/Downloads/cuentacuentos-backups/backup-20260207-165223/wp-priority-queue-plugin`

## Current Source Of Truth

- The deployed server copy is authoritative.
- Local currently matches the deployed plugin files.
- GitHub `origin/main` has been pushed to the current local state.

Current commit:

- `d9705f0`

Current live plugin version:

- `0.12.7`

## Repo / Backup Locations

Repo:

- [Priority-Queue](https://github.com/BicycleGuy/Priority-Queue)

Local backup archive:

- [`/Users/readspear/Downloads/Priority-Queue-Backups/wp-priority-queue-plugin-backup-20260321-095453.tgz`](/Users/readspear/Downloads/Priority-Queue-Backups/wp-priority-queue-plugin-backup-20260321-095453.tgz)

Notes:

- The backup was moved out of the `cuentacuentos` workspace on purpose to avoid confusion.
- The local repo working tree is clean.

## Deployment Notes

- The site is a WordPress plugin deployment.
- Public portal URL:
  - [https://readspear.com/priority-portal/](https://readspear.com/priority-portal/)
- Recent deploy verification relied on:
  - remote PHP lint
  - public asset version checks
  - normalized local vs server file-manifest comparison

Important caveat:

- The server plugin directory contains deploy debris from past packaging:
  - `._*` Apple sidecar files
  - `.git` directory
- These are not part of the functional plugin code, but they should eventually be cleaned.

## Product Boundary Decisions

These decisions are already made and should be treated as intentional unless the product owner changes them.

### What Priority Portal Is

- Work management
- Client/job workflow management
- Billing preparation
- Work statement generation
- Invoice draft generation
- Handoff/export to accounting systems

### What Priority Portal Is Not

- Not a full accounting system
- Not the system of record for issued invoices
- Not native time tracking in this phase

### Accounting Boundary

- Priority Portal creates **Invoice Drafts**
- Wave / QuickBooks are downstream accounting systems of record
- Final invoices are expected to be issued there, not here

## Billing Model Decisions

### Billing Rollup

- Overview-only surface
- Cross-client period overview is allowed for PM/admin visibility
- It is not the place where invoice drafts are composed
- It should answer:
  - what was delivered
  - what is billable
  - what is unbilled
  - what already has a Work Statement
  - what already belongs to an Invoice Draft

### Work Statements

- Optional
- Client-facing report artifact
- PDF-first
- Frozen snapshot at creation
- May span multiple jobs
- May include multiple statuses based on chosen filters
- Must never auto-update after creation

### Statements -> Invoice Drafts

- User-facing `Statement` language has been replaced with `Invoice Draft`
- Single client only
- May span one or many jobs for that client
- Cross-client selection should be impossible in UI and rejected in backend
- Tasks linked to an active draft are not eligible for another draft

### Invoice Draft Line Items

- Line items are first-class
- Totals derive from line items only
- Tasks are traceability/supporting records, not financial truth

Supported v1 line types:

- `task_rollup`
- `fixed_fee`
- `retainer`
- `hourly_overage`
- `pass_through_expense`
- `subscription_service`
- `manual_adjustment`

Rule that matters:

- Line items may be initialized from tasks, but are never derived from them again after creation

## UI / UX Decisions Already Made

### Portal Shell

- Desktop uses a three-pane workspace:
  - left binder
  - center kanban board
  - right task workspace
- Task details should feel like a workspace pane, not a modal

### Binder

- Binder is row-based navigation, not pill-button UI
- Jobs are a separate navigation axis
- Filters are unified under one system:
  - `All tasks`
  - `By responsibility`
  - `By status`

### Board

- Kanban stays
- Empty lanes partially collapse
- Collapsed lanes expand as drag targets
- This replaced the idea of rotating the board or switching to a vertical status stack

### Task Cards

- Left status edge
- Avatar-based ownership
- Priority marker instead of a priority pill
- Denser card spacing

## Recent Fixes And Their Intent

### `0.12.2`

- Clarified job access vs task ownership
- Client admins default into all jobs
- Defaulted new client tasks to client admin / primary contact when no owner is specified

### `0.12.3`

- Performance pass on Clients/admin page
- Removed repeated client loads and reduced N+1 lookups

### `0.12.4` to `0.12.7`

Board drag reliability pass:

- only task cards are draggable
- hover transforms are disabled during drag
- click-vs-drag conflicts were reduced
- desktop drag behavior was stabilized
- collapsed lanes became valid drop targets
- `In Progress -> Needs review` drag path was specifically fixed

Current user report:

- Dragging is now working again

## Debug Workflow Structure

The app now contains internal debug tasks under:

- Client: `Readspear Internal`
- Job: `Debug`

These were created as internal non-billable tasks to track test passes/failures.

Task IDs:

- `#32` Debug: Billing Rollup
- `#33` Debug: Work Statements
- `#34` Debug: Invoice Drafts
- `#35` Debug: Queue Core
- `#36` Debug: Clients & Jobs
- `#37` Debug: Permissions
- `#38` Debug: AI Import
- `#39` Debug: Notifications

Recommended test logging format in comments:

- `BR-01 PASS: ...`
- `WS-08 FAIL: ...`
- `ID-10 FAIL: ...`

## Recommended Next Debug Order

Workflows first, then code behind them.

### Highest Priority

1. Billing Rollup
2. Work Statements
3. Invoice Drafts
4. Queue Core regressions

### Suggested Opening Checks

- `BR-01` Open Billing Rollup for current month
- `BR-02` Filter by one client
- `WS-01` Create Work Statement with default filters
- `WS-08` Freeze test
- `ID-01` Open Invoice Draft workspace
- `ID-02` Create draft from selected tasks
- `ID-10` Confirm totals derive from line items only
- `ID-11` Export canonical CSV

## Codebase Observations

There is real performance and structure debt, but broad refactoring should not happen before workflow debugging is grounded.

Recommended rule:

- Debug workflow failures first
- Fix them
- Add tests around fixed workflows
- Refactor only the touched areas

### Known Hotspots

- [`/Users/readspear/Downloads/cuentacuentos-backups/backup-20260207-165223/wp-priority-queue-plugin/includes/class-wp-pq-api.php`](/Users/readspear/Downloads/cuentacuentos-backups/backup-20260207-165223/wp-priority-queue-plugin/includes/class-wp-pq-api.php)
- [`/Users/readspear/Downloads/cuentacuentos-backups/backup-20260207-165223/wp-priority-queue-plugin/includes/class-wp-pq-admin.php`](/Users/readspear/Downloads/cuentacuentos-backups/backup-20260207-165223/wp-priority-queue-plugin/includes/class-wp-pq-admin.php)
- [`/Users/readspear/Downloads/cuentacuentos-backups/backup-20260207-165223/wp-priority-queue-plugin/assets/js/admin-queue.js`](/Users/readspear/Downloads/cuentacuentos-backups/backup-20260207-165223/wp-priority-queue-plugin/assets/js/admin-queue.js)

### Code Hygiene Direction

When touching code, prefer:

- meaningful names
- smaller focused functions
- explicit error handling
- comments for why, not what
- refactoring only where behavior is already understood
- leaving touched code cleaner than found

## Current Technical State Summary

At handoff time:

- Local repo is clean
- Local matches deployed server files
- GitHub is pushed
- Backup archive exists locally in its own folder
- Drag/drop is working again after the collapsed-lane fixes
- Billing/work statement/invoice-draft flows still need systematic validation

## Immediate Practical Advice For The Next Developer

1. Do not start with architecture refactors.
2. Use the in-app Debug job tasks as the active QA ledger.
3. Treat server behavior as authoritative if any discrepancy appears.
4. Validate billing workflows end-to-end before making more product changes.
5. If deploying from macOS, avoid shipping `._*` sidecar files in future deploy artifacts.

## If You Need To Re-Establish Confidence Quickly

Use this sequence:

1. Confirm local repo is clean
2. Confirm live asset version in portal HTML
3. Confirm local/server normalized file manifests still match
4. Run:
   - Billing Rollup smoke test
   - Work Statement creation + freeze test
   - Invoice Draft creation + CSV export test

