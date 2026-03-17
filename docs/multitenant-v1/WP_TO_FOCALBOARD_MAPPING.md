# WP -> Focalboard Sync Mapping

## Direction
- Primary direction: WordPress intake/approval events -> app API -> Focalboard board/card updates.
- Secondary direction: Focalboard card changes -> app API webhook -> task status update.

## Board model
Recommended boards per tenant:
- `Intake`
- `Active Work`
- `Delivered`
- `Archive`

## Field mapping
WordPress task -> Focalboard card
- `tasks.id` -> card `properties.externalTaskId`
- `title` -> card title
- `description` -> card description
- `status` -> select property `status`
- `priority` -> select property `priority`
- `requested_deadline` -> date property `requested_deadline`
- `due_at` -> date property `due_date`
- `submitter_user_id` -> people property `submitter`
- `owner assignments` -> people property `owners`
- `needs_meeting` -> checkbox property `needs_meeting`

## Status -> board/list mapping
- `pending_review` -> Intake / Pending Review
- `not_approved` -> Intake / Needs Clarification
- `approved` -> Active Work / Approved
- `in_progress` -> Active Work / In Progress
- `delivered` -> Delivered / Awaiting Review
- `revision_requested` -> Active Work / Revisions
- `completed` -> Delivered / Completed
- `archived` -> Archive / Archived

## File handling
Option A (recommended for fast rollout):
- Keep file storage in app object storage.
- Push signed download URL metadata into card comments.

Option B:
- Store files in Focalboard-backed storage and keep mirrored file refs in app DB.

## Sync events
Outbound (app -> Focalboard):
- `task_created`
- `task_updated`
- `task_status_changed`
- `task_assignment_changed`
- `task_comment_added`
- `task_file_uploaded`
- `meeting_created`

Inbound (Focalboard -> app):
- `card_moved`
- `card_property_updated`
- `card_comment_added`

## Conflict rule
- API/app DB is source of truth for lifecycle rules.
- If Focalboard update violates transition policy, reject and write system comment.

## Idempotency
- Send `Idempotency-Key` on every sync mutation.
- Store `sync_events` table with `(tenant_id, external_id, hash, processed_at)`.
