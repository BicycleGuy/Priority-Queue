# Per-User Google OAuth — Implementation Spec

**Status:** Planned
**Date:** 2026-03-31
**Depends on:** Onboarding / magic-link invite system

## Problem

The current OAuth integration stores a single set of Google tokens site-wide in `wp_options`. All calendar events, Meet invitations, and (future) Drive/email operations run under one Google account — the manager who connected it. This creates privacy and ownership problems:

- Calendar events appear on the manager's calendar, not the acting user's
- Email notifications come from a system address, not the person who took the action
- If Drive were re-enabled, files would be owned by the manager's account
- No isolation between users' Google identities

## Target State

Every user connects their own Google account via OAuth during onboarding. Google operations (Calendar, Meet, Gmail, Drive) use the acting user's token.

## Scopes

```
https://www.googleapis.com/auth/calendar.events
https://www.googleapis.com/auth/userinfo.email
https://www.googleapis.com/auth/drive
https://www.googleapis.com/auth/gmail.send
```

## Token Storage

Per-user via `wp_usermeta` (key: `wp_pq_google_tokens`), not a custom table.

```php
[
    'access_token'            => string,
    'encrypted_refresh_token' => string,  // relay mode
    'refresh_token'           => string,  // direct mode
    'token_type'              => 'Bearer',
    'expires_at'              => int,     // Unix timestamp
    'connected_email'         => string,
    'granted_scope'           => string,
    'connected_at'            => string,  // ISO datetime
]
```

Rationale: plugin already uses `user_meta` elsewhere, automatic cleanup on user deletion, no query/join/index needs across users.

## Relay Changes

### `relay/initiate.php`

Accept `user_id` query parameter. Encrypt it into the AES-256-GCM state payload alongside `site_url`, `nonce`, `return_url`, and `ts`. The encrypted state prevents tampering during the Google redirect.

### `relay/callback.php`

Extract `user_id` from decrypted state. Include it in the HMAC-signed payload POSTed back to WordPress `relay-receive` endpoint. The HMAC signature prevents any tampering with `user_id` after it leaves the relay.

### `relay/config.php`

Add `gmail.send` scope to `GOOGLE_SCOPES`.

### `relay/refresh.php`

No changes — stateless. Receives encrypted refresh token, decrypts, returns fresh access token. WordPress side knows which user's token it's refreshing.

### Nonce Race Condition

Current: single `wp_pq_relay_oauth_nonce` option. Multiple users initiating OAuth simultaneously would overwrite each other's nonce.

Fix: per-user nonce via `update_user_meta($user_id, 'wp_pq_relay_oauth_nonce', $nonce)`.

## API Changes (`class-wp-pq-api.php`)

### Token Methods

```php
// Add $user_id parameter, default to current user
public static function get_google_access_token(int $user_id = 0): string
private static function store_google_tokens(array $payload, int $user_id = 0): void
private static function refresh_via_relay(array $tokens, string $relay_url, int $user_id): string
```

All read/write `get_user_meta` / `update_user_meta` instead of `get_option` / `update_option`.

### OAuth Endpoints

| Endpoint | Current Permission | New Permission |
|----------|-------------------|----------------|
| `google/oauth/status` | `CAP_APPROVE` | `is_user_logged_in` |
| `google/oauth/relay-initiate` | `CAP_APPROVE` | `is_user_logged_in` |
| `google/oauth/disconnect` | `CAP_APPROVE` | `is_user_logged_in` |

- `relay-initiate` passes `user_id` to relay
- `relay-receive` extracts `user_id` from HMAC-signed payload, stores tokens on that user
- `status` reads current user's meta
- `disconnect` deletes current user's meta

### Calendar Methods

All calendar methods accept `$user_id` for token retrieval:

- `create_google_calendar_event($task, $starts_at, $ends_at, $user_id)` — uses organizer's token
- `sync_task_calendar_event_inner($task)` — uses task's `action_owner_id` token
- `delete_google_calendar_event($event_id, $user_id)` — uses owner's token
- `fetch_google_calendar_events($user_id)` — uses requesting user's token

### Gmail Send

New helper method:

```php
private static function send_gmail(int $sender_user_id, string $to, string $subject, string $body): bool
```

1. Get sender's access token via `get_google_access_token($sender_user_id)`
2. Build RFC 2822 message with `From:` set to user's `connected_email`
3. Base64url-encode and POST to `https://gmail.googleapis.com/gmail/v1/users/me/messages/send`
4. Fall back to `wp_mail()` if user has no Google connection

Replace `wp_mail()` calls in `emit_event` and `send_client_status_update` with `send_gmail()` using the acting user's token.

## Portal UI Changes (`class-wp-pq-portal.php`)

### Google Connection Section

Remove manager-only gate. All logged-in users see "Connect Google" in their preferences.

### Onboarding Interstitial

Server-side rendered (no FOUC). When `get_user_meta($user_id, 'wp_pq_google_tokens', true)` is empty:

1. Render full-screen panel: "Connect your Google account to get started"
2. Single "Connect Google" button triggers `relay-initiate`
3. After OAuth completes and redirect returns with `?gcal_connected=1`, portal renders normally
4. Workspace is blocked until Google is connected

## Migration Path

### Phase 1: Backward-compatible per-user storage

- Per-user code active, site-wide `wp_options` token kept as fallback
- `get_google_access_token(0)` checks user meta first, then falls back to `wp_options`
- Existing manager token continues working for all operations

### Phase 2: Migrate existing token

```php
$site_tokens = get_option('wp_pq_google_tokens', []);
if (!empty($site_tokens['connected_email'])) {
    $user = get_user_by('email', $site_tokens['connected_email']);
    if ($user) {
        update_user_meta($user->ID, 'wp_pq_google_tokens', $site_tokens);
    }
}
```

### Phase 3: Remove fallback

After all active users have connected:
- Remove `wp_options` fallback from `get_google_access_token()`
- `delete_option('wp_pq_google_tokens')`
- Remove legacy code paths

## Onboarding Flow

1. Manager invites user (enters email, role, client assignment)
2. Magic link sent via system email
3. User clicks link — WordPress account created/logged in
4. Portal loads — detects no Google tokens in user meta
5. Onboarding interstitial — "Connect your Google account to get started"
6. OAuth flow — `relay-initiate` (with `user_id`) -> Google consent -> `callback.php` -> `relay-receive` (with `user_id`) -> tokens stored in user meta
7. Redirect back — portal renders workspace

## Security

- **User ID in relay state:** Encrypted in AES-256-GCM state parameter and HMAC-signed in callback payload. No user can hijack another user's OAuth flow.
- **Per-user nonce:** Prevents race conditions when multiple users initiate OAuth simultaneously.
- **Permission escalation:** `relay-receive` verifies HMAC, then checks user exists and is active before storing tokens.
- **Token isolation:** Each user can only read/modify their own tokens. `get_google_access_token($user_id)` should verify the caller has legitimate reason to access another user's token.

## Implementation Order

1. Relay files (`initiate.php`, `callback.php`, `config.php`) — carry `user_id`, add Gmail scope
2. Per-user token storage methods in `class-wp-pq-api.php`
3. `relay-receive` extracts `user_id`, stores in user meta, per-user nonce
4. `relay-initiate` passes `user_id` to relay
5. `oauth/status` and `disconnect` become per-user, open to all users
6. Calendar methods accept `$user_id` for token retrieval
7. `class-wp-pq-drive.php` accepts `$user_id` (for future use)
8. Portal UI — Google connection for all users, onboarding interstitial
9. JS — remove manager-only gate, add onboarding check
10. Migration logic in `class-wp-pq-db.php`
11. `send_gmail()` helper, conditionally replace `wp_mail()` calls
12. Remove legacy `wp_options` fallback

## Files Affected

| File | Changes |
|------|---------|
| `relay/initiate.php` | Accept `user_id`, encrypt into state |
| `relay/callback.php` | Extract `user_id`, include in HMAC payload |
| `relay/config.php` | Add `gmail.send` scope |
| `includes/class-wp-pq-api.php` | Per-user token storage, calendar methods, Gmail send, endpoint permissions |
| `includes/class-wp-pq-portal.php` | Remove manager gate, add onboarding interstitial |
| `includes/class-wp-pq-drive.php` | Accept `$user_id` parameter |
| `includes/class-wp-pq-db.php` | Token migration logic |
| `includes/class-wp-pq-admin.php` | Per-user token checks |
| `assets/js/admin-queue-alerts.js` | Remove manager-only gate, onboarding flow |
