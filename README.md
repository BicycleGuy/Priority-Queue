# Priority Queue

WordPress plugin for a client-facing task portal with approvals, board workflow, messaging, files, meeting scheduling, and billing rollups.

## What It Does

- Board and calendar views for task management
- Role-aware access for:
  - `pq_client`
  - `pq_worker`
  - `pq_manager`
- Approval workflow with explicit status transitions
- Separate requester, client, and action owner task context
- Task messaging with `@mentions`
- Sticky notes for pinned task context
- File attachments with retention/version rules
- Google Calendar / Google Meet integration
- In-app alerts and email notifications
- Client/job bucket model for billing organization
- Billing rollups, work logs, and statements

## Current Workflow

- `Pending Approval`
- `Needs Clarification`
- `Approved`
- `In Progress`
- `Revisions Needed`
- `Needs Review`
- `Delivered`

Billing is intentionally separate from workflow status.

## Shortcode

Use this shortcode on the portal page:

```text
[pq_client_portal]
```

## Main Plugin Areas

- Plugin bootstrap:
  - `wp-priority-queue-plugin.php`
- Runtime boot:
  - `includes/class-wp-pq-plugin.php`
- REST API and business logic:
  - `includes/class-wp-pq-api.php`
- Admin screens:
  - `includes/class-wp-pq-admin.php`
- Portal markup:
  - `includes/class-wp-pq-portal.php`
- DB schema and migrations:
  - `includes/class-wp-pq-db.php`
- Workflow rules:
  - `includes/class-wp-pq-workflow.php`
- Frontend controller:
  - `assets/js/admin-queue.js`
- Frontend styling:
  - `assets/css/admin-queue.css`

## Key Features

### Tasks and Workflow

- Drag-and-drop board
- Status transitions with modal guidance where needed
- Priority and date adjustments during meaningful moves
- Action-owner assignment and reassignment notifications
- Client and job filtering for manager view

### Collaboration

- Task-scoped messages
- `@mentions` for direct notification
- Sticky notes for non-conversational reference material
- Meeting-request overlay with separate meeting tab

### Meetings and Calendar

- Google OAuth connection flow
- Google Calendar sync for task scheduling
- Google Meet scheduling from task context

### Files

- Task file uploads
- Version retention:
  - keep last 3 versions
  - retain for 365 days
  - reminder around day 300

### Billing

- Client directory
- Client-specific job buckets
- Billing rollup by date range
- Work logs
- Statements
- Print/PDF-friendly statement output

## Plugin Options

- `wp_pq_max_upload_mb`
- `wp_pq_retention_days`
- `wp_pq_retention_reminder_day`
- `wp_pq_file_version_limit`
- `wp_pq_google_client_id`
- `wp_pq_google_client_secret`
- `wp_pq_google_redirect_uri`

## Notes

- The live product is the WordPress plugin in this repository.
- The surrounding archive/workspace may contain unrelated legacy projects; this repo is intended to track the plugin only.
- SMTP and mail delivery are environment-dependent.
