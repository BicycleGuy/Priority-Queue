<?php
/**
 * Switchboard OAuth Relay — Callback
 *
 * GET /callback.php?code=...&state=...
 *
 * Google redirects here after the user consents.
 * Exchanges the auth code for tokens, encrypts the refresh token,
 * POSTs the tokens back to the originating WordPress site,
 * then redirects the user to their return URL.
 */

require __DIR__ . '/config.php';

// ── Validate inputs ────────────────────────────────────────────────────

$code  = filter_input(INPUT_GET, 'code',  FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$state = filter_input(INPUT_GET, 'state', FILTER_DEFAULT)                ?: '';
$error = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

if ($error !== '') {
    // User denied consent or Google returned an error.
    http_response_code(400);
    echo '<!doctype html><html><body><h2>Google authorization was declined.</h2>';
    echo '<p>' . htmlspecialchars($error) . '</p>';
    echo '<p><a href="javascript:history.back()">Go back</a></p></body></html>';
    exit;
}

if ($code === '' || $state === '') {
    relay_json(['error' => 'Missing code or state.'], 400);
}

// ── Decrypt & validate state ───────────────────────────────────────────

try {
    $state_json = relay_decrypt($state);
    $state_data = json_decode($state_json, true);
} catch (Throwable $e) {
    relay_json(['error' => 'Invalid state: ' . $e->getMessage()], 400);
}

if (!is_array($state_data) || empty($state_data['site_url']) || empty($state_data['nonce'])) {
    relay_json(['error' => 'Malformed state payload.'], 400);
}

// Reject state older than 1 hour.
if (isset($state_data['ts']) && (time() - (int) $state_data['ts']) > 3600) {
    relay_json(['error' => 'Authorization request expired. Please try again.'], 400);
}

$site_url   = $state_data['site_url'];
$nonce      = $state_data['nonce'];
$return_url = $state_data['return_url'] ?? '';

// ── Exchange code for tokens ───────────────────────────────────────────

try {
    $tokens = relay_post(GOOGLE_TOKEN_URL, [
        'code'          => $code,
        'client_id'     => RELAY_GOOGLE_CLIENT_ID,
        'client_secret' => RELAY_GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => rtrim(RELAY_BASE_URL, '/') . '/callback.php',
        'grant_type'    => 'authorization_code',
    ]);
} catch (Throwable $e) {
    relay_json(['error' => 'Token exchange failed: ' . $e->getMessage()], 502);
}

if (!empty($tokens['error'])) {
    relay_json(['error' => 'Google token error: ' . ($tokens['error_description'] ?? $tokens['error'])], 502);
}

$access_token  = $tokens['access_token']  ?? '';
$refresh_token = $tokens['refresh_token'] ?? '';
$expires_in    = (int) ($tokens['expires_in'] ?? 3600);
$granted_scope = $tokens['scope'] ?? '';

if ($access_token === '' || $refresh_token === '') {
    relay_json(['error' => 'Google did not return required tokens.'], 502);
}

// ── Fetch connected email ──────────────────────────────────────────────

$userinfo = relay_get('https://www.googleapis.com/oauth2/v2/userinfo', $access_token);
$connected_email = $userinfo['email'] ?? '';

// ── Encrypt refresh token ──────────────────────────────────────────────

$encrypted_refresh = relay_encrypt($refresh_token);

// ── POST tokens back to WordPress ──────────────────────────────────────

$receive_url = rtrim($site_url, '/') . '/wp-json/pq/v1/google/oauth/relay-receive';

$payload = json_encode([
    'nonce'                    => $nonce,
    'access_token'             => $access_token,
    'expires_in'               => $expires_in,
    'encrypted_refresh_token'  => $encrypted_refresh,
    'connected_email'          => $connected_email,
    'granted_scope'            => $granted_scope,
], JSON_UNESCAPED_SLASHES);

$ch = curl_init($receive_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Relay-Signature: ' . hash_hmac('sha256', $payload, hex2bin(RELAY_ENCRYPTION_KEY)),
    ],
]);
$wp_response = curl_exec($ch);
$wp_code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($wp_code < 200 || $wp_code >= 300) {
    // Log but don't expose details to user.
    error_log('Switchboard relay: WordPress receive failed (HTTP ' . $wp_code . '): ' . substr($wp_response ?: '', 0, 500));
}

// ── Redirect user back to their site ───────────────────────────────────

$redirect = $return_url ?: ($site_url . '/switchboard/?section=preferences');
$redirect = $redirect . (str_contains($redirect, '?') ? '&' : '?') . 'gcal_connected=1';

header('Location: ' . $redirect, true, 302);
exit;
