# Workflow / Ledger Refactor Spec

## Governing Model
- Workflow board = active operational work.
- Ledger = completed business record for billing and reporting.
- Board state must stop being the billing source of truth once a task is business-complete.

## Canonical Workflow Statuses
- `pending_approval`
- `needs_clarification`
- `approved`
- `in_progress`
- `needs_review`
- `delivered`
- `done`

## Board Rules
- Visible columns:
  - `pending_approval`
  - `needs_clarification`
  - `approved`
  - `in_progress`
  - `needs_review`
  - `delivered`
- Hidden terminal status:
  - `done`
- Default active-board queries exclude `done`.
- `needs_clarification` should preferably remain visible even when empty.

## Revisions Model
- `revision_requested` is deprecated as a standalone queue state.
- Revision requests reopen work into an actionable state:
  - `needs_review -> in_progress`
  - `delivered -> in_progress`
  - `delivered -> needs_clarification`
  - `delivered -> needs_review`
- Revision history belongs in audit metadata, not as a permanent column.

## Transition Map
Primary path:
- `pending_approval -> approved`
- `needs_clarification -> approved`
- `approved -> in_progress`
- `in_progress -> needs_review`
- `needs_review -> delivered`
- `delivered -> done`

Allowed correction and reopen transitions:
- `approved -> needs_clarification`
- `in_progress -> needs_clarification`
- `needs_review -> in_progress`
- `delivered -> in_progress`
- `delivered -> needs_clarification`
- `delivered -> needs_review`

## Delivered Semantics
- `delivered` is soft and reversible.
- `delivered` does not imply accepted, invoiced, or paid.
- Exits from `delivered` should be audited.

## Done Semantics
- `done` is a terminal workflow state.
- It is entered through explicit action, not a board column.
- On success:
  - `status = done`
  - set completion timestamps
  - remove task from active board queries
  - later phases create or update ledger records

## Billing Bucket Naming
- Backend: `billing_bucket_id`
- UI: `Job`
- Avoid introducing `project_id` as separate product vocabulary in v1.

## Billing Modes
Validation by billing mode is a later phase, but the planned modes are:
- `hourly`
- `fixed_fee`
- `pass_through_expense`
- `non_billable`

## Phase Plan
### Phase 1
- hidden `done`
- remove `revision_requested` as board queue
- explicit transition map
- reversible `delivered`
- explicit `Mark Done`
- board limited to active columns

### Phase 2
- completion metadata on tasks
- billing mode field
- completion validation by billing mode

### Phase 3
- `work_ledger_entries`
- done-triggered ledger upsert
- board and ledger separated cleanly

### Phase 4
- invoice draft generation from ledger
- monthly statement generation from ledger
- billing views driven by ledger only

### Phase 5
- controlled reopen after `done`
- reconciliation rules when ledger entries are already invoiced or paid

## Minimal Acceptance Criteria
- `done` exists as a real backend status.
- `done` is excluded from normal board columns.
- `revision_requested` is not a primary queue state.
- `delivered` is reversible.
- `delivered -> done` is explicit.
- Billing/reporting eventually pivots away from board state and onto ledger data.
