# Priority Portal Board Redesign Spec

This document converts the current product discussion into build rules for the next UI iteration.

## Goal

Replace the current queue list with a more intuitive board-first experience without losing:

- approval workflow
- queue order
- due date management
- audit history
- email notifications
- task-level messaging and file exchange

## Product Principles

1. The board is the primary workspace.
2. The task detail lives in a focused drawer or slide-over, not a third full column.
3. Drag-and-drop should never make hidden business decisions.
4. Email should be reserved for meaningful changes, not simple board activity.
5. Messages and sticky notes are different interaction types and should be modeled separately.

## Proposed Main Screens

### Board View

Primary default view.

Contains:

- tasks grouped by workflow stage
- visible task title
- visible short description or brief
- priority badge
- due date badge
- owner avatars or initials
- meeting indicator

### Calendar View

Alternate view for deadline and meeting visibility.

Contains:

- task deadlines
- meeting blocks
- hover tooltip with task summary

### Task Detail Drawer

Opens from a board card or calendar item.

Contains:

- full description
- approvals and status controls
- files
- messages
- sticky notes
- timeline / audit history

## Workflow Model

Current statuses remain:

- `pending_review`
- `not_approved`
- `approved`
- `in_progress`
- `delivered`
- `revision_requested`
- `completed`
- `archived`

These should become board columns or grouped views, with `archived` hidden by default.

## Drag-and-Drop Rules

Dragging a card should not always mean the same thing.

### Safe drag

If a task is moved within the same priority band and no dated task is displaced:

- update queue order only
- do not send email
- log audit entry

### Meaningful drag

If a drag crosses another dated task or implies reprioritization:

- show a confirmation modal
- do not apply permanent changes until the user confirms

## Reprioritization Modal

When a meaningful drag occurs, the modal should ask:

1. Reorder only
2. Reorder and raise priority
3. Reorder and swap due dates
4. Reorder, raise priority, and swap due dates

If the user cancels:

- revert the drag visually
- make no data changes

## Notification Rules

Default recommendation:

- no email for simple reorder
- email when priority changes
- email when due date changes
- email when owner changes
- email when approval state changes
- email when revision is requested
- email when work is delivered
- email when task is completed

Recipients should eventually become event-specific instead of always broadcasting to submitter plus owners.

## Messaging Model

The current single message box should split into two content types.

### Directed Message

Used for person-to-person task communication.

Rules:

- supports `@mentions`
- belongs in task activity thread
- notifies mentioned users based on preferences
- can optionally notify watchers if enabled later

### Sticky Note

Used for board or task context, not direct messaging.

Rules:

- no recipient required
- no email by default
- visually pinned in task detail
- can be created from the same composer via a toggle such as `Make this a sticky note`

## Recommended UI Direction

The current three-column layout feels crowded because every function is trying to stay visible at once.

Recommended next layout:

1. Board or calendar as the main canvas
2. Global create button for new request
3. Task detail slide-over for work context
4. Messages, files, and notes as tabs inside the drawer

This should replace the always-open left request form and right workspace panel.

## Build Sequence

### Phase 1

- redesign layout shell
- replace queue list with board view
- move task detail into slide-over drawer

### Phase 2

- add drag confirmation modal
- implement queue-only vs priority/date-changing drag outcomes
- add audit entries for drag decisions

### Phase 3

- split message composer into directed messages and sticky notes
- add `@mentions`
- add mention-triggered notifications

### Phase 4

- make email routing event-specific
- align calendar sync with due date updates

## Immediate Build Recommendation

Before we build messaging changes, start with:

1. board layout shell
2. task drawer
3. drag modal scaffold

That gives the portal a cleaner mental model before we add richer communication features.
