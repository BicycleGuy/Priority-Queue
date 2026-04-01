<?php
/**
 * Switchboard OAuth Relay — Configuration
 *
 * Deploy this relay on any PHP host (separate from WordPress).
 * Set environment variables or edit the constants below.
 *
 * Required env vars:
 *   RELAY_GOOGLE_CLIENT_ID     — from Google Cloud Console
 *   RELAY_GOOGLE_CLIENT_SECRET — from Google Cloud Console
 *   RELAY_ENCRYPTION_KEY       — 32-byte hex key for token encryption
 *   RELAY_BASE_URL             — public URL of this relay (e.g., https://relay.example.com)
 */

// Load .env file if present (simple dotenv loader).
// Uses $_ENV only — putenv() is disabled on some hosts.
$_relay_env = [];
$_relay_env_file = __DIR__ . '/.env';
if (is_readable($_relay_env_file)) {
    foreach (file($_relay_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (str_contains($_line, '=')) {
            [$_key, $_val] = explode('=', $_line, 2);
            $_relay_env[trim($_key)] = trim($_val);
        }
    }
    unset($_line, $_key, $_val);
}
unset($_relay_env_file);

// Helper: read from parsed .env, then $_ENV, then getenv().
function _relay_env(string $key): string {
    global $_relay_env;
    return $_relay_env[$key] ?? ($_ENV[$key] ?? (getenv($key) ?: ''));
}

define('RELAY_GOOGLE_CLIENT_ID',     _relay_env('RELAY_GOOGLE_CLIENT_ID'));
define('RELAY_GOOGLE_CLIENT_SECRET', _relay_env('RELAY_GOOGLE_CLIENT_SECRET'));
define('RELAY_ENCRYPTION_KEY',       _relay_env('RELAY_ENCRYPTION_KEY'));
define('RELAY_BASE_URL',             _relay_env('RELAY_BASE_URL'));

// Google OAuth endpoints
define('GOOGLE_AUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');

// Scopes — calendar.events is narrower than full calendar access
define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/gmail.send');

/**
 * Encrypt a string with AES-256-GCM.
 * Returns base64-encoded: iv(12) + tag(16) + ciphertext.
 */
function relay_encrypt(string $plaintext): string
{
    $key = hex2bin(RELAY_ENCRYPTION_KEY);
    $iv  = random_bytes(12);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt a base64-encoded AES-256-GCM payload.
 */
function relay_decrypt(string $payload): string
{
    $key  = hex2bin(RELAY_ENCRYPTION_KEY);
    $raw  = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 29) {
        throw new RuntimeException('Invalid encrypted payload.');
    }
    $iv         = substr($raw, 0, 12);
    $tag        = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plaintext  = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false) {
        throw new RuntimeException('Decryption failed.');
    }
    return $plaintext;
}

/**
 * Send a JSON response and exit.
 */
function relay_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Make an HTTP POST request (cURL).
 */
function relay_post(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('HTTP request failed: ' . $err);
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON response (HTTP ' . $code . '): ' . substr($body, 0, 200));
    }

    return $decoded;
}

/**
 * Fetch JSON via GET (cURL).
 */
function relay_get(string $url, string $bearer_token): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $bearer_token,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($body ?: '', true);
    return is_array($decoded) ? $decoded : [];
}
