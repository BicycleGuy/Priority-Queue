# Multi-Tenant V1 Architecture (Read Spear)

> **Plugin version:** 0.23.4 | **Last updated:** 2026-03-27

## Goal
Turn the current single-site workflow into a client-facing product where each client runs in an isolated tenant.

## Product split
- `readspear.com` (WordPress): marketing + intake + branded login handoff.
- `app.readspear.com` (new app): multi-tenant workspace for queue, approvals, files, comments, and calendar.

## Recommended stack
- API: Node.js (`NestJS` preferred) + Postgres.
- Work management UI: Focalboard-compatible domain model (board/list/calendar views).
- Storage: S3-compatible object store.
- Auth: external IdP (Auth0/Clerk) or Keycloak.
- Jobs: queue worker (BullMQ or equivalent).

## Tenant model
- Every business object carries `tenant_id`.
- Isolation boundary: tenant.
- Users can belong to multiple tenants through membership records.
- RBAC enforced by membership role + policy checks.

## Roles
- `tenant_owner`: billing, integrations, members.
- `manager`: approve/reject, assign owners, see all tenant tasks.
- `worker`: execute work, upload deliverables, comment.
- `client_member`: create and track own requests, comment/upload.
- `viewer`: read-only.

## Workflow (preserve existing)
- `pending_review`
- `approved`
- `not_approved`
- `in_progress`
- `delivered`
- `revision_requested`
- `completed`
- `archived`

## Data retention policy
- Keep last 3 versions per file role (`input`, `deliverable`).
- File retention: 365 days.
- Reminder: day 300.

## Integration boundaries
- Google OAuth tokens stored per tenant.
- Meeting creation uses tenant token and creates Google Meet links.
- Calendar aggregation endpoint merges:
  - task deadlines
  - meeting events
  - linked Google events

## Deployment model
- Separate app runtime from WordPress.
- WordPress posts intake to API with signed service token.
- API syncs task cards to board model.

## Non-negotiable controls before external rollout
- Row-level tenant isolation tests.
- Immutable audit log.
- Encrypted secrets/tokens.
- Backup + restore drills.
- API rate limiting and abuse controls.
