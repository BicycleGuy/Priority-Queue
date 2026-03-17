# WP Priority Queue Portal

WordPress plugin scaffold for your workflow on Cloudways.

## Included in this implementation

- Drag-and-drop queue with role-based visibility
- Roles: `pq_client`, `pq_worker`, `pq_manager`
- Owners are plural (`owner_ids`)
- Approval workflow with transition policy matrix
- Task messaging endpoint (all authenticated task participants)
- File attachment endpoint with retention logic
  - Keep last 3 versions per file role
  - Auto-delete older versions
  - Auto-expire at 365 days
  - Day-300 email reminder
- Notification preferences endpoint (per-user checkbox model)
- Meeting records endpoint (Google two-way sync scaffold)
- Calendar webhook endpoint scaffold for inbound sync updates

## Shortcode

- Use `[pq_client_portal]` on a new page.

## REST endpoints

- `GET/POST /wp-json/pq/v1/tasks`
- `POST /wp-json/pq/v1/tasks/reorder`
- `POST /wp-json/pq/v1/tasks/{id}/status`
- `GET/POST /wp-json/pq/v1/tasks/{id}/messages`
- `GET/POST /wp-json/pq/v1/tasks/{id}/files`
- `GET/POST /wp-json/pq/v1/tasks/{id}/meetings`
- `POST /wp-json/pq/v1/calendar/webhook`
- `GET/POST /wp-json/pq/v1/notification-prefs`
- `GET /wp-json/pq/v1/workers`

## Plugin options

- `wp_pq_max_upload_mb` (default `1024`)
- `wp_pq_retention_days` (default `365`)
- `wp_pq_retention_reminder_day` (default `300`)
- `wp_pq_file_version_limit` (default `3`)

## Notes

- Google OAuth/token storage and Calendar API calls are scaffold-ready but not fully wired in this pass.
- SMTP setup controls actual email deliverability.
