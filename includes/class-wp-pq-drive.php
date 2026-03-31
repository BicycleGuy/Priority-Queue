<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Google Drive Shared Drive integration.
 *
 * All API calls use wp_remote_* (no PHP SDK).
 * Requires a valid access token from WP_PQ_API::get_google_access_token().
 */
class WP_PQ_Drive
{
    private const API_BASE = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD_BASE = 'https://www.googleapis.com/upload/drive/v3';

    /** @var string|null Cached access token for the current request. */
    private static ?string $cached_token = null;

    /**
     * Get a valid access token, cached for the lifetime of this request.
     */
    private static function token(): string
    {
        if (self::$cached_token !== null) {
            return self::$cached_token;
        }

        $token = WP_PQ_API::get_google_access_token();
        if (! $token) {
            throw new RuntimeException('No Google access token available.');
        }

        self::$cached_token = $token;
        return $token;
    }

    /**
     * Get or create a Shared Drive for a client.
     */
    public static function ensure_client_drive(int $client_id): string
    {
        global $wpdb;
        $clients_table = $wpdb->prefix . 'pq_clients';

        $client = $wpdb->get_row(
            $wpdb->prepare("SELECT id, name, google_drive_id FROM {$clients_table} WHERE id = %d", $client_id),
            ARRAY_A
        );

        if (! $client) {
            throw new RuntimeException('Client not found.');
        }

        $drive_id = $client['google_drive_id'] ?? '';
        if ($drive_id !== '') {
            return $drive_id;
        }

        $token = self::token();
        $drive_name = trim($client['name']) . ' — Switchboard';

        // Shared Drive creation requires a requestId query param.
        $request_id = wp_generate_uuid4();
        $response = wp_remote_post(self::API_BASE . '/drives?requestId=' . $request_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['name' => $drive_name]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Drive API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300 || empty($body['id'])) {
            $error = $body['error']['message'] ?? 'Unknown error';
            throw new RuntimeException('Failed to create Shared Drive: ' . $error);
        }

        $drive_id = $body['id'];

        $wpdb->update($clients_table, ['google_drive_id' => $drive_id], ['id' => $client_id]);

        return $drive_id;
    }

    /**
     * Get or create a task folder inside the client's Shared Drive.
     */
    public static function ensure_task_folder(int $task_id, int $client_id, string $task_title): string
    {
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'pq_tasks';

        $folder_id = $wpdb->get_var(
            $wpdb->prepare("SELECT google_folder_id FROM {$tasks_table} WHERE id = %d", $task_id)
        );

        if ($folder_id) {
            return $folder_id;
        }

        $drive_id = self::ensure_client_drive($client_id);
        $token = self::token();

        $folder_name = $task_id . ' — ' . sanitize_file_name($task_title);

        $folder_id = self::create_folder($token, $folder_name, $drive_id, $drive_id);

        $wpdb->update($tasks_table, ['google_folder_id' => $folder_id], ['id' => $task_id]);

        // Create input/ and deliverables/ subfolders.
        self::create_folder($token, 'input', $folder_id, $drive_id);
        self::create_folder($token, 'deliverables', $folder_id, $drive_id);

        return $folder_id;
    }

    /**
     * Get or create the _unattached folder in a client's Shared Drive.
     */
    public static function ensure_unattached_folder(int $client_id): string
    {
        $drive_id = self::ensure_client_drive($client_id);
        $token = self::token();

        // Search for existing _unattached folder.
        $query = "name = '_unattached' and mimeType = 'application/vnd.google-apps.folder' and '" . $drive_id . "' in parents and trashed = false";
        $search_url = self::API_BASE . '/files?' . http_build_query([
            'q' => $query,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
            'corpora' => 'drive',
            'driveId' => $drive_id,
            'fields' => 'files(id)',
        ]);

        $response = wp_remote_get($search_url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Drive folder search failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $files = $body['files'] ?? [];

        if (! empty($files[0]['id'])) {
            return $files[0]['id'];
        }

        return self::create_folder($token, '_unattached', $drive_id, $drive_id);
    }

    /**
     * Upload a file to Google Drive.
     *
     * @return array{id: string, name: string, webViewLink: string, mimeType: string, size: string}
     */
    public static function upload_file(string $folder_id, string $drive_id, string $filename, string $mime_type, string $file_contents): array
    {
        $token = self::token();

        $metadata = wp_json_encode([
            'name' => $filename,
            'parents' => [$folder_id],
        ]);

        $boundary = 'switchboard_' . wp_generate_uuid4();
        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mime_type}\r\n\r\n"
            . $file_contents . "\r\n"
            . "--{$boundary}--";

        $url = self::UPLOAD_BASE . '/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink,mimeType,size';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
            ],
            'body' => $body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Drive upload request failed: ' . $response->get_error_message());
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300 || empty($result['id'])) {
            $error = $result['error']['message'] ?? 'Unknown error';
            throw new RuntimeException('Drive upload failed: ' . $error);
        }

        return $result;
    }

    /**
     * Delete a file from Google Drive.
     */
    public static function delete_file(string $drive_file_id): bool
    {
        try {
            $token = self::token();
        } catch (RuntimeException $e) {
            return false;
        }

        $response = wp_remote_request(self::API_BASE . '/files/' . $drive_file_id . '?supportsAllDrives=true', [
            'method' => 'DELETE',
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 300;
    }

    /**
     * List files in a Drive folder.
     *
     * @return array[] List of file metadata arrays.
     */
    public static function list_files(string $folder_id, string $drive_id): array
    {
        try {
            $token = self::token();
        } catch (RuntimeException $e) {
            return [];
        }

        $query = "'" . $folder_id . "' in parents and trashed = false";
        $url = self::API_BASE . '/files?' . http_build_query([
            'q' => $query,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
            'corpora' => 'drive',
            'driveId' => $drive_id,
            'fields' => 'files(id,name,mimeType,size,webViewLink,createdTime,modifiedTime)',
            'orderBy' => 'createdTime desc',
            'pageSize' => 100,
        ]);

        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['files'] ?? [];
    }

    /**
     * Download file content from Drive (for proxying to the browser).
     *
     * @return array{content: string, mime_type: string, filename: string}
     */
    public static function download_file(string $drive_file_id): array
    {
        $token = self::token();

        // Get file metadata first.
        $meta_response = wp_remote_get(
            self::API_BASE . '/files/' . $drive_file_id . '?supportsAllDrives=true&fields=name,mimeType',
            ['headers' => ['Authorization' => 'Bearer ' . $token], 'timeout' => 10]
        );

        if (is_wp_error($meta_response)) {
            throw new RuntimeException('Drive metadata request failed: ' . $meta_response->get_error_message());
        }

        $meta = json_decode(wp_remote_retrieve_body($meta_response), true);

        // Download content.
        $dl_response = wp_remote_get(
            self::API_BASE . '/files/' . $drive_file_id . '?alt=media&supportsAllDrives=true',
            ['headers' => ['Authorization' => 'Bearer ' . $token], 'timeout' => 60]
        );

        if (is_wp_error($dl_response)) {
            throw new RuntimeException('Drive download failed: ' . $dl_response->get_error_message());
        }

        return [
            'content' => wp_remote_retrieve_body($dl_response),
            'mime_type' => $meta['mimeType'] ?? 'application/octet-stream',
            'filename' => $meta['name'] ?? 'download',
        ];
    }

    /**
     * Add a member to a Shared Drive (for project handoff).
     */
    public static function share_drive(string $drive_id, string $email, string $role = 'writer'): bool
    {
        try {
            $token = self::token();
        } catch (RuntimeException $e) {
            return false;
        }

        $url = self::API_BASE . '/files/' . $drive_id . '/permissions?supportsAllDrives=true';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'type' => 'user',
                'role' => $role,
                'emailAddress' => $email,
            ]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 300;
    }

    /**
     * Check if the current access token has Drive scope.
     */
    public static function is_enabled(): bool
    {
        $tokens = get_option('wp_pq_google_tokens', []);
        if (empty($tokens)) {
            return false;
        }

        // Check the actual scope Google granted, not just what we requested.
        $granted = $tokens['granted_scope'] ?? '';
        if ($granted !== '') {
            return str_contains($granted, 'drive');
        }

        // Fallback for tokens stored before we tracked granted_scope.
        return false;
    }

    // ── Private helpers ───────────────────────────────────────────

    private static function create_folder(string $token, string $name, string $parent_id, string $drive_id): string
    {
        $response = wp_remote_post(self::API_BASE . '/files?supportsAllDrives=true', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parent_id],
            ]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Failed to create folder: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300 || empty($body['id'])) {
            $error = $body['error']['message'] ?? 'Unknown error';
            throw new RuntimeException('Failed to create folder: ' . $error);
        }

        return $body['id'];
    }
}
