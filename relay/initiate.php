<?php
/**
 * Switchboard OAuth Relay — Initiate
 *
 * GET /initiate.php?site_url=...&nonce=...&return_url=...
 *
 * Builds the Google OAuth authorization URL using the relay's client_id
 * and redirects the user to Google's consent screen.
 */

require __DIR__ . '/config.php';

// ── Validate inputs ────────────────────────────────────────────────────

$site_url   = filter_input(INPUT_GET, 'site_url',   FILTER_SANITIZE_URL)    ?: '';
$nonce      = filter_input(INPUT_GET, 'nonce',       FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$return_url = filter_input(INPUT_GET, 'return_url',  FILTER_SANITIZE_URL)    ?: '';
$user_id    = (int) (filter_input(INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT) ?: 0);

if ($site_url === '' || $nonce === '') {
    relay_json(['error' => 'Missing site_url or nonce.'], 400);
}

if ($user_id <= 0) {
    relay_json(['error' => 'Missing user_id.'], 400);
}

if (RELAY_GOOGLE_CLIENT_ID === '' || RELAY_BASE_URL === '') {
    relay_json(['error' => 'Relay is not configured.'], 500);
}

// ── Build state payload ────────────────────────────────────────────────
// State is signed so the callback can trust it.

$state_data = json_encode([
    'site_url'   => $site_url,
    'nonce'      => $nonce,
    'return_url' => $return_url,
    'user_id'    => $user_id,
    'ts'         => time(),
], JSON_UNESCAPED_SLASHES);

$state = relay_encrypt($state_data);

// ── Redirect to Google ─────────────────────────────────────────────────

$params = http_build_query([
    'client_id'     => RELAY_GOOGLE_CLIENT_ID,
    'redirect_uri'  => rtrim(RELAY_BASE_URL, '/') . '/callback.php',
    'response_type' => 'code',
    'scope'         => GOOGLE_SCOPES,
    'access_type'   => 'offline',
    'prompt'        => 'consent',
    'state'         => $state,
]);

header('Location: ' . GOOGLE_AUTH_URL . '?' . $params, true, 302);
exit;
