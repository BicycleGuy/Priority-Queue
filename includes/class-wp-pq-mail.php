<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * WP_PQ_Mail — Gmail sending extracted from WP_PQ_API.
 */
class WP_PQ_Mail
{
    /**
     * Send an email via the Gmail API using the sender's OAuth token.
     *
     * Falls back to wp_mail() when the user has no connected Google account
     * or when the Gmail API request fails.
     *
     * @param int    $sender_user_id  WordPress user whose Gmail token to use.
     * @param string $to              Recipient email address.
     * @param string $subject         Email subject.
     * @param string $body            Email body (plain text or HTML).
     * @param bool   $is_html         Whether $body is HTML. Default false.
     * @return bool  True on success, false on failure.
     */
    public static function send_gmail(int $sender_user_id, string $to, string $subject, string $body, bool $is_html = false): bool
    {
        $access_token = WP_PQ_Google_Auth::get_google_access_token($sender_user_id);
        if ($access_token === '') {
            // Fallback to wp_mail() if user hasn't connected Google.
            $headers = $is_html ? ['Content-Type: text/html; charset=UTF-8'] : [];
            return wp_mail($to, $subject, $body, $headers);
        }

        $tokens = WP_PQ_Google_Auth::get_user_google_tokens($sender_user_id);
        $from_email = (string) ($tokens['connected_email'] ?? '');
        if ($from_email === '') {
            return wp_mail($to, $subject, $body, $is_html ? ['Content-Type: text/html; charset=UTF-8'] : []);
        }

        // Build RFC 2822 message.
        $content_type = $is_html ? 'text/html' : 'text/plain';
        $mime = "From: {$from_email}\r\n"
              . "To: {$to}\r\n"
              . "Subject: {$subject}\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: {$content_type}; charset=UTF-8\r\n"
              . "\r\n"
              . $body;

        $encoded = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');

        $resp = wp_remote_post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['raw' => $encoded]),
        ]);

        if (is_wp_error($resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Gmail send failed: ' . $resp->get_error_message());
            }
            // Fallback to wp_mail on network failure.
            return wp_mail($to, $subject, $body, $is_html ? ['Content-Type: text/html; charset=UTF-8'] : []);
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PQ Gmail send HTTP ' . $code . ': ' . wp_remote_retrieve_body($resp));
            }
            // Fallback to wp_mail on API error.
            return wp_mail($to, $subject, $body, $is_html ? ['Content-Type: text/html; charset=UTF-8'] : []);
        }

        return true;
    }
}
