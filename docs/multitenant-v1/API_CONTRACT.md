# API Contract (V1)

Base URL: `https://app.readspear.com/api/v1`

Headers:
- `Authorization: Bearer <jwt>`
- `X-Tenant-Id: <uuid>`

## Auth / tenancy
- `GET /me`
- `GET /tenants`
- `POST /tenants`
- `POST /tenants/{tenantId}/members`
- `PATCH /tenants/{tenantId}/members/{memberId}`

## Tasks
- `GET /tasks?status=&priority=&assignee=&page=`
- `POST /tasks`
- `GET /tasks/{taskId}`
- `PATCH /tasks/{taskId}`
- `POST /tasks/reorder`
- `POST /tasks/{taskId}/status`
- `POST /tasks/{taskId}/assignments`
- `DELETE /tasks/{taskId}/assignments/{userId}`

## Comments (replace chat clone)
- `GET /tasks/{taskId}/comments`
- `POST /tasks/{taskId}/comments`

## Files
- `POST /tasks/{taskId}/files/presign-upload`
- `POST /tasks/{taskId}/files/complete-upload`
- `GET /tasks/{taskId}/files`
- `POST /tasks/{taskId}/files/{fileId}/presign-download`

## Meetings / calendar
- `POST /tasks/{taskId}/meetings` (creates Google event + Meet if connected)
- `GET /tasks/{taskId}/meetings`
- `GET /calendar/events?from=&to=&view=`
- `POST /calendar/google/webhook`

## Integrations
- `GET /integrations/google/status`
- `POST /integrations/google/oauth/url`
- `GET /integrations/google/oauth/callback`
- `POST /integrations/google/disconnect`

## Notifications
- `GET /notification-prefs`
- `PUT /notification-prefs`

## Admin / audit
- `GET /audit-log?objectType=&objectId=&page=`

---

## Status transition policy
- `pending_review -> approved | not_approved`
- `not_approved -> pending_review`
- `approved -> in_progress | archived`
- `in_progress -> delivered | archived`
- `delivered -> revision_requested | completed`
- `revision_requested -> in_progress | archived`
- `completed -> archived`

## Event keys
- `task_created`
- `task_approved`
- `task_rejected`
- `task_revision_requested`
- `task_delivered`
- `task_completed`
- `retention_day_300`
