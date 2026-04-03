<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Google OAuth and token management.
 *
 * Extracted from WP_PQ_API during Phase 7b code cleanup.
 */
class WP_PQ_Google_Auth
{
    public static function google_oauth_callback(WP_REST_Request $request): WP_REST_Response
    {
        $code = sanitize_text_field((string) $request->get_param('code'));
        $state = sanitize_text_field((string) $request->get_param('state'));
        $error = sanitize_text_field((string) $request->get_param('error'));
        $as_json = sanitize_text_field((string) $request->get_param('format')) === 'json';

        if ($error !== '') {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'Google OAuth failed: ' . $error,
                ], 400);
            }

            self::render_oauth_result_page(false, 'Google authorization was not completed (' . $error . ').');
        }

        if ($code === '') {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'Missing OAuth code.',
                ], 400);
            }

            self::render_oauth_result_page(false, 'Google authorization code is missing.');
        }

        $client_id = (string) get_option('wp_pq_google_client_id', '');
        $client_secret = (string) get_option('wp_pq_google_client_secret', '');
        $redirect_uri = self::google_redirect_uri();
        if ($client_id === '' || $client_secret === '') {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'Google client credentials are not configured.',
                ], 500);
            }

            self::render_oauth_result_page(false, 'Google client credentials are not configured in WordPress.');
        }

        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($resp)) {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'OAuth token exchange failed: ' . $resp->get_error_message(),
                ], 500);
            }

            self::render_oauth_result_page(false, 'OAuth token exchange failed: ' . $resp->get_error_message());
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($body) || empty($body['access_token'])) {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'OAuth token exchange failed.',
                    'details' => $body,
                ], 500);
            }

            self::render_oauth_result_page(false, 'OAuth token exchange failed. Please retry connection.');
            return null;
        }

        // Debug: log whether Google returned a refresh_token.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('PQ OAuth token exchange keys: ' . implode(', ', array_keys($body)));
            error_log('PQ OAuth has refresh_token: ' . (isset($body['refresh_token']) ? 'YES' : 'NO'));
        }

        self::store_google_tokens($body);

        if (! $as_json) {
            self::render_oauth_result_page(true, 'Google Calendar connected successfully.');
        }

        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Google Calendar connected successfully.',
            'state' => $state,
        ], 200);
    }

    public static function google_oauth_url(): WP_REST_Response
    {
        $client_id = (string) get_option('wp_pq_google_client_id', '');
        if ($client_id === '') {
            return new WP_REST_Response(['message' => 'Google client ID is not configured.'], 422);
        }

        $state = wp_generate_password(20, false, false);
        update_option('wp_pq_google_oauth_state', $state, false);

        $params = [
            'client_id' => $client_id,
            'redirect_uri' => self::google_redirect_uri(),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => self::google_scopes(),
            'state' => $state,
            'include_granted_scopes' => 'true',
        ];

        return new WP_REST_Response([
            'url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
        ], 200);
    }

    public static function google_oauth_status(): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $tokens = self::get_user_google_tokens($user_id);
        $relay_url = trim((string) get_option('wp_pq_relay_url', ''));
        $using_relay = $relay_url !== '';

        // Either token format indicates a connection.
        $connected = ! empty($tokens['encrypted_refresh_token']) || ! empty($tokens['refresh_token']);

        return new WP_REST_Response([
            'connected'         => $connected,
            'has_refresh_token'  => $connected,
            'expires_at'        => isset($tokens['expires_at']) ? (int) $tokens['expires_at'] : null,
            'redirect_uri'      => self::google_redirect_uri(),
            'using_relay'       => $using_relay,
            'connected_email'   => (string) ($tokens['connected_email'] ?? ''),
        ], 200);
    }

    /**
     * Relay-receive -- called by the OAuth relay after Google consent.
     *
     * POST /wp-json/pq/v1/google/oauth/relay-receive
     * Body: { nonce, access_token, expires_in, encrypted_refresh_token, connected_email }
     * Header: X-Relay-Signature (HMAC-SHA256 of body)
     */
    public static function google_oauth_relay_receive(WP_REST_Request $request): WP_REST_Response
    {
        $debug = defined('WP_DEBUG') && WP_DEBUG;

        if ($debug) {
            error_log('PQ Relay-Receive: endpoint hit');
        }

        // -- Verify HMAC signature --
        $relay_key = trim((string) get_option('wp_pq_relay_encryption_key', ''));
        if ($relay_key === '') {
            if ($debug) { error_log('PQ Relay-Receive: FAILED — relay encryption key is empty'); }
            return new WP_REST_Response(['error' => 'Relay encryption key is not configured.'], 500);
        }

        $raw_body  = (string) $request->get_body();
        $signature = (string) $request->get_header('X-Relay-Signature');

        if ($debug) {
            error_log('PQ Relay-Receive: signature present=' . ($signature !== '' ? 'yes' : 'no') . ' body_length=' . strlen($raw_body));
        }

        if ($signature === '' || ! hash_equals(hash_hmac('sha256', $raw_body, hex2bin($relay_key)), $signature)) {
            if ($debug) { error_log('PQ Relay-Receive: FAILED — signature mismatch'); }
            return new WP_REST_Response(['error' => 'Invalid signature.'], 403);
        }

        // -- Parse body --
        $body = json_decode($raw_body, true);
        if (! is_array($body)) {
            if ($debug) { error_log('PQ Relay-Receive: FAILED — invalid JSON body'); }
            return new WP_REST_Response(['error' => 'Invalid JSON body.'], 400);
        }

        $nonce                   = sanitize_text_field((string) ($body['nonce'] ?? ''));
        $user_id                 = (int) ($body['user_id'] ?? 0);
        $access_token            = sanitize_text_field((string) ($body['access_token'] ?? ''));
        $expires_in              = (int) ($body['expires_in'] ?? 3600);
        $encrypted_refresh_token = (string) ($body['encrypted_refresh_token'] ?? '');
        $connected_email         = sanitize_email((string) ($body['connected_email'] ?? ''));
        $granted_scope           = sanitize_text_field((string) ($body['granted_scope'] ?? ''));

        // -- Recover user_id from nonce if relay didn't forward it --
        // Nonce format: "user_id:random_string"
        if ($user_id <= 0 && $nonce !== '' && str_contains($nonce, ':')) {
            $nonce_user_id = (int) explode(':', $nonce, 2)[0];
            if ($nonce_user_id > 0) {
                $user_id = $nonce_user_id;
                if ($debug) { error_log('PQ Relay-Receive: recovered user_id=' . $user_id . ' from nonce'); }
            }
        }

        if ($debug) {
            error_log('PQ Relay-Receive: user_id=' . $user_id . ' email=' . $connected_email . ' has_nonce=' . ($nonce !== '' ? 'yes' : 'no') . ' has_access=' . ($access_token !== '' ? 'yes' : 'no') . ' has_refresh=' . ($encrypted_refresh_token !== '' ? 'yes' : 'no'));
        }

        if ($nonce === '' || $access_token === '' || $encrypted_refresh_token === '') {
            if ($debug) { error_log('PQ Relay-Receive: FAILED — missing required fields (nonce=' . ($nonce !== '' ? 'yes' : 'no') . ' access=' . ($access_token !== '' ? 'yes' : 'no') . ' refresh=' . ($encrypted_refresh_token !== '' ? 'yes' : 'no') . ')'); }
            return new WP_REST_Response(['error' => 'Missing required fields.'], 400);
        }

        if ($user_id <= 0 || ! get_userdata($user_id)) {
            if ($debug) { error_log('PQ Relay-Receive: FAILED — invalid user_id ' . $user_id); }
            return new WP_REST_Response(['error' => 'Invalid user_id.'], 400);
        }

        // -- Verify nonce (per-user) --
        $stored_nonce = (string) get_user_meta($user_id, 'wp_pq_relay_oauth_nonce', true);
        if ($debug) {
            error_log('PQ Relay-Receive: stored_nonce=' . ($stored_nonce !== '' ? 'present(' . strlen($stored_nonce) . 'chars)' : 'EMPTY') . ' received_nonce_length=' . strlen($nonce));
        }
        if ($stored_nonce === '' || ! hash_equals($stored_nonce, $nonce)) {
            if ($debug) { error_log('PQ Relay-Receive: FAILED — nonce mismatch (stored_empty=' . ($stored_nonce === '' ? 'yes' : 'no') . ')'); }
            return new WP_REST_Response(['error' => 'Nonce mismatch.'], 403);
        }

        // Clear the nonce (single-use).
        delete_user_meta($user_id, 'wp_pq_relay_oauth_nonce');

        // -- Store tokens on the user --
        $tokens = [
            'access_token'            => $access_token,
            'encrypted_refresh_token' => $encrypted_refresh_token,
            'token_type'              => 'Bearer',
            'expires_at'              => time() + max(60, $expires_in - 30),
            'connected_email'         => $connected_email,
            'granted_scope'           => $granted_scope,
            'connected_at'            => gmdate('Y-m-d H:i:s'),
        ];

        update_user_meta($user_id, 'wp_pq_google_tokens', $tokens);

        if ($debug) {
            error_log('PQ Relay-Receive: SUCCESS — tokens stored for user ' . $user_id . ' (' . $connected_email . ')');
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Relay-initiate -- redirects the user to the relay to start OAuth.
     *
     * GET /wp-json/pq/v1/google/oauth/relay-initiate
     * Returns { url: "..." } that the portal JS navigates to.
     */
    public static function google_oauth_relay_initiate(): WP_REST_Response
    {
        $relay_url = trim((string) get_option('wp_pq_relay_url', ''));
        if ($relay_url === '') {
            return new WP_REST_Response(['error' => 'OAuth relay URL is not configured.'], 422);
        }

        $user_id = get_current_user_id();

        // Generate a per-user one-time nonce so relay-receive can verify.
        // Prefix with user_id so relay-receive can recover the user even if
        // the relay service doesn't forward user_id in its POST body.
        $random = wp_generate_password(32, false, false);
        $nonce  = $user_id . ':' . $random;
        update_user_meta($user_id, 'wp_pq_relay_oauth_nonce', $nonce);

        $scopes = trim((string) get_option('wp_pq_google_scopes', ''));
        if ($scopes === '') {
            $scopes = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/drive';
        }

        $params = http_build_query([
            'site_url'   => home_url(),
            'nonce'      => $nonce,
            'return_url' => home_url('/portal?section=preferences'),
            'user_id'    => $user_id,
            'scopes'     => $scopes,
        ], '', '&', PHP_QUERY_RFC3986);

        $url = rtrim($relay_url, '/') . '/initiate.php?' . $params;

        return new WP_REST_Response(['url' => $url], 200);
    }

    /**
     * Disconnect Google -- wipe the current user's stored tokens.
     */
    public static function google_oauth_disconnect(): WP_REST_Response
    {
        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'wp_pq_google_tokens');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('PQ: Google disconnected by user ' . $user_id);
        }

        return new WP_REST_Response(['ok' => true, 'message' => 'Google disconnected.'], 200);
    }

    public static function google_redirect_uri(): string
    {
        $uri = trim((string) get_option('wp_pq_google_redirect_uri', ''));
        if ($uri === '') {
            $uri = home_url('/wp-json/pq/v1/google/oauth/callback');
        }

        return $uri;
    }

    public static function google_scopes(): string
    {
        $scopes = trim((string) get_option('wp_pq_google_scopes', ''));
        if ($scopes === '') {
            $scopes = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/drive';
        }

        return $scopes;
    }

    /**
     * Get a user's stored Google tokens from user_meta.
     * Falls back to the legacy site-wide wp_options token during migration.
     */
    public static function get_user_google_tokens(int $user_id): array
    {
        $tokens = (array) get_user_meta($user_id, 'wp_pq_google_tokens', true);
        if (! empty($tokens['access_token']) || ! empty($tokens['encrypted_refresh_token']) || ! empty($tokens['refresh_token'])) {
            return $tokens;
        }
        return [];
    }

    /**
     * Store Google tokens. Used only for direct-mode (non-relay) token refresh.
     */
    public static function store_google_tokens(array $token_payload, int $user_id = 0): void
    {
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        $existing = self::get_user_google_tokens($user_id);
        $expires_in = isset($token_payload['expires_in']) ? (int) $token_payload['expires_in'] : 3600;

        $tokens = [
            'access_token' => (string) ($token_payload['access_token'] ?? ($existing['access_token'] ?? '')),
            'refresh_token' => (string) ($token_payload['refresh_token'] ?? ($existing['refresh_token'] ?? '')),
            'token_type' => (string) ($token_payload['token_type'] ?? ($existing['token_type'] ?? 'Bearer')),
            'expires_at' => time() + max(60, $expires_in - 30),
            'connected_email' => (string) ($existing['connected_email'] ?? ''),
            'granted_scope' => (string) ($existing['granted_scope'] ?? ''),
        ];

        update_user_meta($user_id, 'wp_pq_google_tokens', $tokens);
    }

    /**
     * Get a valid Google access token for a user.
     *
     * @param int $user_id WordPress user ID. 0 = current user.
     */
    public static function get_google_access_token(int $user_id = 0): string
    {
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }
        if ($user_id <= 0) {
            return '';
        }

        $tokens = self::get_user_google_tokens($user_id);
        $access_token = (string) ($tokens['access_token'] ?? '');
        $expires_at = (int) ($tokens['expires_at'] ?? 0);
        if ($access_token !== '' && $expires_at > (time() + 30)) {
            return $access_token;
        }

        // -- Relay mode: call the relay's /refresh.php endpoint --
        $relay_url = trim((string) get_option('wp_pq_relay_url', ''));
        if ($relay_url !== '') {
            return self::refresh_via_relay($tokens, $relay_url, $user_id);
        }

        // -- Direct mode: refresh directly with Google --
        $refresh_token = (string) ($tokens['refresh_token'] ?? '');
        if ($refresh_token === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Google: no refresh_token for user ' . $user_id);
            }
            return '';
        }

        $client_id = (string) get_option('wp_pq_google_client_id', '');
        $client_secret = (string) get_option('wp_pq_google_client_secret', '');

        if ($client_id === '' || $client_secret === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Google: client_id or client_secret missing');
            }
            return '';
        }

        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);
        if (is_wp_error($resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Google refresh WP_Error: ' . $resp->get_error_message());
            }
            return '';
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($body) || empty($body['access_token'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Google refresh failed: HTTP ' . $status . ' — ' . wp_remote_retrieve_body($resp));
            }
            return '';
        }

        self::store_google_tokens($body, $user_id);
        return (string) ($body['access_token'] ?? '');
    }

    /**
     * Refresh access token via the OAuth relay server.
     * Sends the encrypted_refresh_token to the relay, which decrypts it,
     * calls Google, and returns a fresh access_token.
     */
    private static function refresh_via_relay(array $tokens, string $relay_url, int $user_id): string
    {
        $encrypted_refresh = (string) ($tokens['encrypted_refresh_token'] ?? '');
        if ($encrypted_refresh === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Relay: no encrypted_refresh_token for user ' . $user_id);
            }
            return '';
        }

        $payload = wp_json_encode(['encrypted_refresh_token' => $encrypted_refresh]);
        $url = rtrim($relay_url, '/') . '/refresh.php';

        $resp = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $payload,
        ]);

        if (is_wp_error($resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Relay refresh WP_Error: ' . $resp->get_error_message());
            }
            return '';
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);

        if ($status >= 300 || ! is_array($body) || empty($body['access_token'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Relay refresh failed: HTTP ' . $status . ' — ' . wp_remote_retrieve_body($resp));
            }
            return '';
        }

        // Update stored access token and expiry on the user, keep encrypted_refresh_token intact.
        $tokens['access_token'] = (string) $body['access_token'];
        $tokens['expires_at']   = time() + max(60, ((int) ($body['expires_in'] ?? 3600)) - 30);

        update_user_meta($user_id, 'wp_pq_google_tokens', $tokens);

        return (string) $body['access_token'];
    }

    private static function render_oauth_result_page(bool $ok, string $message): void
    {
        status_header($ok ? 200 : 400);
        nocache_headers();

        $title = $ok ? 'Google Connected' : 'Google Connection Failed';
        $color = $ok ? '#166534' : '#991b1b';
        $bg = $ok ? '#ecfdf5' : '#fef2f2';
        $portal_url = home_url('/portal');

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:32px;background:#f3f4f6}';
        echo '.card{max-width:640px;margin:40px auto;background:#fff;border:1px solid #d1d5db;border-radius:14px;padding:24px}';
        echo '.state{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:600;margin-bottom:12px}';
        echo '.btn{display:inline-block;margin-top:16px;background:#be123c;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600}';
        echo '</style></head><body><div class="card">';
        echo '<span class="state" style="background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';">' . esc_html($title) . '</span>';
        echo '<h1 style="margin:8px 0 10px">' . esc_html($title) . '</h1>';
        echo '<p style="font-size:16px;line-height:1.5;color:#374151">' . esc_html($message) . '</p>';
        echo '<a class="btn" href="' . esc_url($portal_url) . '">Return to Switchboard</a>';
        echo '</div></body></html>';
        exit;
    }
}
