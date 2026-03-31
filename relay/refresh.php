<?php
/**
 * Switchboard OAuth Relay — Refresh
 *
 * POST /refresh.php
 * Body: { "encrypted_refresh_token": "..." }
 *
 * WordPress calls this when the access token expires.
 * Decrypts the refresh token, exchanges it for a new access token,
 * and returns the new access token to WordPress.
 */

require __DIR__ . '/config.php';

// ── Only accept POST ───────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    relay_json(['error' => 'Method not allowed.'], 405);
}

// ── Parse request body ─────────────────────────────────────────────────

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body) || empty($body['encrypted_refresh_token'])) {
    relay_json(['error' => 'Missing encrypted_refresh_token.'], 400);
}

// ── Rate limiting (basic, per-IP) ──────────────────────────────────────

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_file = sys_get_temp_dir() . '/switchboard_relay_rate_' . md5($ip);
$now = time();

if (file_exists($rate_file)) {
    $last = (int) file_get_contents($rate_file);
    if ($now - $last < 5) {
        relay_json(['error' => 'Rate limited. Try again in a few seconds.'], 429);
    }
}
file_put_contents($rate_file, (string) $now);

// ── Decrypt refresh token ──────────────────────────────────────────────

try {
    $refresh_token = relay_decrypt($body['encrypted_refresh_token']);
} catch (Throwable $e) {
    relay_json(['error' => 'Invalid refresh token payload.'], 400);
}

// ── Exchange for new access token ──────────────────────────────────────

try {
    $tokens = relay_post(GOOGLE_TOKEN_URL, [
        'refresh_token' => $refresh_token,
        'client_id'     => RELAY_GOOGLE_CLIENT_ID,
        'client_secret' => RELAY_GOOGLE_CLIENT_SECRET,
        'grant_type'    => 'refresh_token',
    ]);
} catch (Throwable $e) {
    relay_json(['error' => 'Token refresh failed: ' . $e->getMessage()], 502);
}

if (!empty($tokens['error'])) {
    relay_json(['error' => 'Google error: ' . ($tokens['error_description'] ?? $tokens['error'])], 502);
}

$access_token = $tokens['access_token'] ?? '';
$expires_in   = (int) ($tokens['expires_in'] ?? 3600);

if ($access_token === '') {
    relay_json(['error' => 'Google did not return an access token.'], 502);
}

// ── Return new access token ────────────────────────────────────────────

relay_json([
    'access_token' => $access_token,
    'expires_in'   => $expires_in,
]);
