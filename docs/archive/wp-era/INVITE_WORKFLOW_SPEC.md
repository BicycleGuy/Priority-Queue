# Switchboard Invite Workflow -- Complete Specification

Status: Draft
Date: 2026-04-02

---

## 1. Use Cases

### Who invites whom

| Inviter | Invitee | Why |
|---------|---------|-----|
| Manager / Admin | Client contact | Onboard a new client user so they can view their queue, approve tasks, exchange messages |
| Manager / Admin | Team member (worker) | Add an internal team member who will work tasks |
| Client Admin | Their own people | A client admin invites a colleague to view or contribute to their client's queue |

### Key constraint: Client Admin invitations

Client Admins should only be able to invite users scoped to their own client, with roles at or below their own level (client_contributor, client_viewer -- never client_admin). This keeps the permission model tight without requiring manager involvement for every client-side addition.

Manager/Admin invitations are unrestricted across clients and roles.

---

## 2. The Invite Form

### Form fields (in order)

| # | Field | Type | Required | Conditional logic |
|---|-------|------|----------|-------------------|
| 1 | Email | email input | Yes | If email matches existing WP user, show inline note: "This person already has an account -- they'll be added to the selected client." |
| 2 | First Name | text | Yes | Auto-filled if email matches existing user |
| 3 | Last Name | text | Yes | Auto-filled if email matches existing user |
| 4 | Type | segmented toggle: "Client user" / "Team member" | Yes | Default: Client user. "Team member" hidden for Client Admin inviters. |
| 5 | Client | select dropdown | Yes (when Type = Client user) | Hidden when Type = Team member. For Client Admin inviters: locked to their own client. For managers: full client list + "+ New client" option. Placeholder: "Select a client..." (no auto-select). |
| 6 | Client Role | select: Viewer / Contributor / Admin | Yes (when Type = Client user) | Hidden when Type = Team member. Default: Contributor. Client Admin inviters cannot select Admin. |

### Design decisions

- **Email first.** This is the identity key. Leading with email lets the system immediately check for duplicates and existing accounts, which changes the rest of the form behavior. This matches Notion and Linear patterns.
- **No password field.** Invitee sets their password on acceptance (or logs in with existing credentials if account already exists). Matches every modern SaaS.
- **"+ New client" inline creation.** When a manager selects "+ New client," expand an inline field for client name. Client is created on invite submit, not in a separate flow. Keeps the manager in context.

---

## 3. Delivery

### Primary: Email via Gmail API / wp_mail fallback

This is the existing path. Keep it. But add two critical improvements:

**3a. Delivery status tracking**

Add a `delivery_status` column to `pq_invites`:

| Value | Meaning |
|-------|---------|
| `sent` | `send_gmail` returned true |
| `failed` | `send_gmail` returned false |
| `unknown` | Email was queued but delivery confirmation not available |

Update `create_invite` to capture the return value of `send_gmail_public` and write it to the invite record. This gives the admin visibility into whether the email actually went out.

**3b. Copy Link fallback**

After an invite is created, always show a "Copy Link" button next to the invite row. The link is `{site_url}/portal/invite/{token}`. This is the escape hatch for when email delivery is unreliable -- the manager can paste the link into Slack, a text message, or any other channel.

This is how Figma, Linear, and Slack all handle it: email is the primary channel, but the link is always copyable.

**3c. Resend**

Add a "Resend" action on pending invites. Behavior:
- Generate a new token (invalidating the old one).
- Reset `expires_at` to 7 days from now.
- Send a new email.
- Update `delivery_status`.
- Log the resend in a `resent_count` column (integer, default 0) and `last_resent_at` (datetime, nullable).

Rationale for new token on resend: prevents the old link from working if it was forwarded or intercepted. Clean security posture.

### What the email looks like

Subject: `{Inviter Name} invited you to Switchboard`

Body (plain text with link):
```
Hi {First Name},

{Inviter Name} has invited you to join Switchboard{client context}.

Accept your invitation:
{invite_url}

This link expires in 7 days. If you have questions, reply to this email.

-- Switchboard
```

Where `{client context}` is ` as a {role} on {Client Name}` for client users, or ` as a team member` for workers. Gives the recipient context about why they are being invited.

---

## 4. Acceptance Flow

### 4a. New user (no existing WP account for that email)

```
Click link
  -> Validate token (exists, status=pending, not expired)
  -> Show acceptance page with:
       - "Welcome to Switchboard" heading
       - Pre-filled: name, email (read-only)
       - Field: "Set your password" + confirmation
       - "Accept Invitation" button
  -> On submit:
       - Create WP user with provided password
       - Assign WordPress role (pq_client or pq_worker)
       - Bind to client + client role if applicable
       - Mark invite accepted
       - Log user in
       - Redirect to /portal (client users) or /wp-admin (team members)
```

### 4b. Existing user (WP account already exists for that email)

```
Click link
  -> Validate token
  -> If user is already logged in as the matching email:
       - Add role, bind to client, mark accepted
       - Redirect with success toast
  -> If user is NOT logged in:
       - Show acceptance page with:
           - "You already have an account" message
           - "Log in to accept" with email pre-filled, password field
           - "Accept & Log In" button
       - On submit:
           - Authenticate credentials
           - Add role, bind to client, mark accepted
           - Redirect with success toast
  -> If user is logged in as a DIFFERENT email:
       - Show message: "This invitation was sent to {email}. Please log out and log in as {email}, or click below to log out now."
       - "Log out & continue" button
```

### 4c. Invalid / expired / revoked token

Redirect to `/portal/login?invite=expired` with a clear message: "This invitation has expired or been revoked. Contact your administrator for a new one."

No ambiguity, no dead ends.

---

## 5. Follow-on Actions

### 5a. Notification to inviter

When an invite is accepted, notify the person who sent it:
- In-app notification (via existing `pq_notifications` system): "{Name} accepted your invitation and joined {Client Name / the team}."
- Email notification (optional, via Gmail): same message. Only send if the inviter has Google connected. Low priority -- the in-app notification is sufficient for v1.

### 5b. Invite record update

- `status` -> `accepted`
- `accepted_at` -> current timestamp
- `accepted_user_id` -> the WP user ID that was created or matched (new column)

### 5c. No auto-assignment of tasks

Accepting an invite does not auto-assign any tasks. The manager decides what work to assign. Anything automatic here would be surprising and unwelcome.

### 5d. Google OAuth prompt (team members only)

After a team member accepts and lands on `/wp-admin`, the existing Google OAuth interstitial handles prompting them to connect their account. No changes needed.

---

## 6. Edge Cases

| Scenario | System behavior |
|----------|----------------|
| **Duplicate email, pending invite exists** | Block creation. Show: "An active invite already exists for this email." Offer to resend or revoke the existing one. (Current behavior blocks; enhance message to include action links.) |
| **Email belongs to existing user, same client** | Allow invite. On acceptance, `ensure_client_member` is idempotent -- it updates the role if the membership already exists. Show note in form: "This user already has an account." |
| **Email belongs to existing user, different client** | Normal flow. User gets added to the new client. Multiple client memberships are expected. |
| **Expired invite, user clicks link** | Redirect to login with "expired" message. Admin sees "expired" status in invite list. |
| **Revoked invite, user clicks link** | Same as expired. Token lookup query already filters by `status = 'pending'`. |
| **User clicks link twice (already accepted)** | Token status is `accepted`, so the `WHERE status = 'pending'` query returns null. Redirect to login. Optionally: detect `status = 'accepted'` and show a friendlier message: "You've already accepted this invitation. Log in below." |
| **Manager revokes after user has already clicked but before form submit** | Token re-validated on form submit (not just on page load). If revoked between page load and submit, show error. |
| **Client Admin invites someone, then gets demoted** | The invite remains valid -- it was authorized at creation time. Revoking requires explicit action. |
| **Multiple invites to same email for different clients** | Allowed. Each invite is independent. The duplicate check is scoped to `email + status = pending`. Adding a client_id to the uniqueness check would be the enhancement: allow one pending invite per email per client. |
| **Invitee's email has uppercase characters** | Normalize to lowercase before storage and comparison. Add `strtolower()` to `create_invite`. |

---

## 7. Admin Visibility

### Invite list (existing, enhanced)

Current table columns: Name, Email, Role, Client, Status, Sent, Actions.

**Add/change:**

| Column | Current | Proposed |
|--------|---------|----------|
| Delivery | -- | New. Shows icon: green check (sent), red x (failed), gray dash (unknown) |
| Status | Text only | Pill badge with color: green=accepted, yellow=pending, gray=expired, red=revoked |
| Actions (pending) | Revoke | Revoke, Resend, Copy Link |
| Actions (expired) | -- | Resend (creates new invite), Copy Link (disabled) |
| Actions (accepted) | -- | None (or "View user" link to their profile) |
| Actions (revoked) | -- | Re-invite (shortcut to pre-fill the form with same details) |

### Filtering and counts

Above the table, show summary counts: `3 pending, 12 accepted, 1 expired`. Clickable to filter.

For v1, this is sufficient. Pagination can wait until the invite count warrants it.

---

## 8. Database Changes

### Alter `pq_invites`

```sql
ALTER TABLE {prefix}pq_invites
  ADD COLUMN delivery_status VARCHAR(20) NOT NULL DEFAULT 'unknown' AFTER status,
  ADD COLUMN accepted_user_id BIGINT UNSIGNED NULL AFTER accepted_at,
  ADD COLUMN resent_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER accepted_user_id,
  ADD COLUMN last_resent_at DATETIME NULL AFTER resent_count;
```

### New index

```sql
CREATE INDEX email_client ON {prefix}pq_invites (email, client_id, status);
```

This supports the enhanced duplicate check (one pending invite per email per client).

---

## 9. API Changes

### Existing endpoints (modify)

**POST /pq/v1/manager/invites**
- Add `delivery_status` to response.
- Return the `token` in the response (so the UI can build the copy-link URL without a separate query).
- Duplicate check: change from email-only to email + client_id for client-user invites. Team member invites remain email-only.

**GET /pq/v1/manager/invites**
- Add `delivery_status`, `resent_count`, `last_resent_at`, `accepted_user_id` to response rows.

**DELETE /pq/v1/manager/invites/{id}**
- No changes needed.

### New endpoints

**POST /pq/v1/manager/invites/{id}/resend**
- Permission: `can_manage`
- Generates new token, resets expiry, sends email, increments `resent_count`, updates `last_resent_at` and `delivery_status`.
- Returns updated invite record.

### Client Admin endpoints (new)

**POST /pq/v1/client/invites**
- Permission: current user must have `client_admin` role on the client.
- Fields: email, first_name, last_name, client_role (contributor or viewer only).
- Client is auto-set to the inviter's client. Role is auto-set to `pq_client`.
- Same invite creation logic, scoped.

**GET /pq/v1/client/invites**
- Returns invites for the current user's client only.

These are lower priority. Ship manager invites first, add client admin invites in a follow-up.

---

## 10. Acceptance Page (Portal)

### Current behavior

The portal acceptance at `/portal/invite/{token}` currently auto-creates the user with a random password and immediately logs them in. There is no password-setting step.

### Proposed change

Replace the auto-create-and-redirect with a two-step acceptance page:

**Step 1: Token validation**
- Server-side: validate token, load invite record.
- If invalid/expired: redirect to `/portal/login?invite=expired`.

**Step 2: Render acceptance form**
- For new users: show name (read-only), email (read-only), password field, confirm password, "Accept Invitation" button.
- For existing users who are logged in: show "Accept & Continue" button (no password needed).
- For existing users who are not logged in: show email (read-only), password field, "Log In & Accept" button.

This is a meaningful upgrade from the current flow. Users get to set their own password, which matters for security and reduces the "I don't know my password" support burden.

---

## 11. Implementation Priority

### Phase 1 -- Reliability (ship this week)

1. Capture `delivery_status` from `send_gmail_public` return value.
2. Add "Copy Link" button to invite list rows.
3. Add "Resend" action for pending and expired invites.
4. Normalize email to lowercase on invite creation.
5. Handle the "already accepted" case with a friendlier redirect message.

These are small changes to existing code. They fix the biggest pain points: unreliable email with no fallback, and no resend capability.

### Phase 2 -- Acceptance UX (next sprint)

6. Build the acceptance page with password-setting form.
7. Add existing-user detection and login-to-accept flow.
8. Add in-app notification to inviter on acceptance.
9. Add `accepted_user_id` tracking.

### Phase 3 -- Client Admin self-service (later)

10. Client Admin invite endpoints.
11. Client Admin invite UI in portal.
12. Scoped permission enforcement.

---

## 12. What This Does NOT Cover

- **Bulk invites / CSV import.** Not needed yet. When the user count warrants it, add a CSV upload that creates multiple invites in one action.
- **SSO / SAML.** Out of scope for the WordPress plugin. Relevant for the Next.js migration.
- **Invite approval workflow.** No need for a manager to approve client admin invites at this stage. The permission model (client admins can only invite contributors/viewers) is sufficient guardrail.
- **Custom invite messages.** The manager cannot customize the email body. Keep it simple. If needed later, add an optional "personal note" textarea that gets appended to the email.

---

## 13. Reference: How Others Do It

| Product | Key pattern borrowed |
|---------|---------------------|
| **Slack** | Email + copy-link dual delivery. Pending invite list with resend/revoke. |
| **Notion** | Email-first form with existing-user detection. Role selection in invite form. |
| **Linear** | Minimal form (email + role). Invite list as admin panel section. |
| **Figma** | Copy-link as first-class action, not just fallback. Team-scoped invites. |
| **Google Workspace** | Acceptance page with account setup. Admin notification on acceptance. |

The Switchboard workflow draws most heavily from the Slack/Linear pattern: email-first, copy-link always available, simple admin list with status tracking, and role assignment at invite time.
