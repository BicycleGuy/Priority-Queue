<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Files
{
    public static function get_files(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = WP_PQ_API::get_task_row($task_id);

        if (! $task || ! WP_PQ_API::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $table = $wpdb->prefix . 'pq_task_files';
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY file_role ASC, version_num DESC", $task_id),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row = self::hydrate_file_row($row);
        }

        return new WP_REST_Response(['files' => $rows], 200);
    }

    public static function attach_file(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = WP_PQ_API::get_task_row($task_id);

        if (! $task || ! WP_PQ_API::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $media_id = (int) $request->get_param('media_id');
        $file_role = sanitize_key((string) $request->get_param('file_role'));
        if (! in_array($file_role, ['input', 'deliverable'], true)) {
            $file_role = 'input';
        }

        if ($media_id <= 0 || get_post_type($media_id) !== 'attachment') {
            return new WP_REST_Response(['message' => 'Valid media_id is required.'], 422);
        }

        $table = $wpdb->prefix . 'pq_task_files';
        $retention_days = (int) get_option('wp_pq_retention_days', 365);
        $version_limit = (int) get_option('wp_pq_file_version_limit', 3);

        $max_version = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(version_num), 0) FROM {$table} WHERE task_id = %d AND file_role = %s",
            $task_id,
            $file_role
        ));

        $wpdb->insert($table, [
            'task_id' => $task_id,
            'uploader_id' => get_current_user_id(),
            'media_id' => $media_id,
            'file_role' => $file_role,
            'version_num' => $max_version + 1,
            'storage_expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . $retention_days . ' days')),
            'created_at' => current_time('mysql', true),
        ]);

        $overflow = $wpdb->get_results($wpdb->prepare(
            "SELECT id, media_id
             FROM {$table}
             WHERE task_id = %d AND file_role = %s
             ORDER BY version_num DESC
             LIMIT 100 OFFSET %d",
            $task_id,
            $file_role,
            $version_limit
        ), ARRAY_A);

        foreach ($overflow as $row) {
            wp_delete_attachment((int) $row['media_id'], true);
            $wpdb->delete($table, ['id' => (int) $row['id']]);
        }

        $file = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d AND media_id = %d ORDER BY id DESC LIMIT 1", $task_id, $media_id),
            ARRAY_A
        );

        return new WP_REST_Response([
            'ok' => true,
            'file' => $file ? self::hydrate_file_row($file) : null,
            'task' => WP_PQ_API::get_enriched_task($task_id),
        ], 201);
    }

    /**
     * Update the files_link URL on a task.
     */
    public static function update_files_link(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = WP_PQ_API::get_task_row($task_id);

        if (! $task || ! WP_PQ_API::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Task not found or access denied.'], 404);
        }

        $files_link = esc_url_raw(trim((string) $request->get_param('files_link')));

        $wpdb->update(
            $wpdb->prefix . 'pq_tasks',
            ['files_link' => $files_link ?: null],
            ['id' => $task_id]
        );

        return new WP_REST_Response([
            'ok' => true,
            'files_link' => $files_link,
            'task' => WP_PQ_API::get_enriched_task($task_id),
        ], 200);
    }

    public static function get_all_documents(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $files_table = $wpdb->prefix . 'pq_task_files';
        $tasks_table = $wpdb->prefix . 'pq_tasks';

        $rows = $wpdb->get_results(
            "SELECT f.*, t.title AS task_title
             FROM {$files_table} f
             LEFT JOIN {$tasks_table} t ON t.id = f.task_id
             ORDER BY f.created_at DESC
             LIMIT 500",
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row = self::hydrate_file_row($row);
            $uploader = get_userdata((int) ($row['uploader_id'] ?? 0));
            $row['uploader_name'] = $uploader ? $uploader->display_name : 'Unknown';

            // hydrate_file_row already sets filesize for Drive files.
            // Only compute from disk for media-library files.
            $storage_type = $row['storage_type'] ?? 'media';
            if ($storage_type !== 'drive') {
                $row['filesize'] = 0;
                $media_id = (int) ($row['media_id'] ?? 0);
                if ($media_id > 0) {
                    $path = get_attached_file($media_id);
                    if ($path && file_exists($path)) {
                        $row['filesize'] = filesize($path);
                    }
                }
            }
        }

        return new WP_REST_Response(['documents' => $rows], 200);
    }

    public static function register_document(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $media_id = (int) $request->get_param('media_id');

        if ($media_id <= 0 || get_post_type($media_id) !== 'attachment') {
            return new WP_REST_Response(['message' => 'Valid media_id is required.'], 422);
        }

        $table = $wpdb->prefix . 'pq_task_files';
        $retention_days = (int) get_option('wp_pq_retention_days', 365);

        $wpdb->insert($table, [
            'task_id' => 0,
            'uploader_id' => get_current_user_id(),
            'media_id' => $media_id,
            'file_role' => 'input',
            'version_num' => 1,
            'storage_expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . $retention_days . ' days')),
            'created_at' => current_time('mysql', true),
        ]);

        return new WP_REST_Response(['ok' => true, 'id' => $wpdb->insert_id], 201);
    }

    public static function delete_document(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $file_id = (int) $request->get_param('id');
        $table = $wpdb->prefix . 'pq_task_files';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $file_id),
            ARRAY_A
        );

        if (! $row) {
            return new WP_REST_Response(['message' => 'File not found.'], 404);
        }

        $storage_type = $row['storage_type'] ?? 'media';
        if ($storage_type === 'drive') {
            $drive_file_id = $row['drive_file_id'] ?? '';
            if ($drive_file_id !== '') {
                WP_PQ_Drive::delete_file($drive_file_id);
            }
        } else {
            $media_id = (int) ($row['media_id'] ?? 0);
            if ($media_id > 0) {
                wp_delete_attachment($media_id, true);
            }
        }

        $wpdb->delete($table, ['id' => $file_id]);

        return new WP_REST_Response(['ok' => true, 'deleted_id' => $file_id], 200);
    }

    // ── Google Drive file endpoints ─────────────────────────────────

    public static function drive_status(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'enabled' => WP_PQ_Drive::is_enabled(),
            'has_tokens' => ! empty(get_user_meta(get_current_user_id(), 'wp_pq_google_tokens', true)),
        ], 200);
    }

    public static function upload_file_to_drive(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $task_id = (int) $request->get_param('id');
        $task = WP_PQ_API::get_task_row($task_id);
        $user_id = get_current_user_id();

        if (! $task || ! WP_PQ_API::can_access_task($task, $user_id)) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $files = $request->get_file_params();
        $file = $files['file'] ?? null;

        if (! $file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_REST_Response(['message' => 'No file provided.'], 422);
        }

        $file_role = sanitize_key((string) $request->get_param('file_role'));
        if (! in_array($file_role, ['input', 'deliverable'], true)) {
            $file_role = 'input';
        }

        $client_id = (int) ($task['client_id'] ?? 0);
        if ($client_id <= 0) {
            return new WP_REST_Response(['message' => 'Task has no client — cannot determine Drive.'], 422);
        }

        try {
            $task_folder_id = WP_PQ_Drive::ensure_task_folder($task_id, $client_id, $task['title'] ?? 'Untitled');

            // Find the role subfolder (input/ or deliverables/).
            $drive_id = WP_PQ_Drive::ensure_client_drive($client_id);
            $subfolder_name = $file_role === 'deliverable' ? 'deliverables' : 'input';
            $subfolders = WP_PQ_Drive::list_files($task_folder_id, $drive_id);
            $target_folder = $task_folder_id;
            foreach ($subfolders as $sf) {
                if (($sf['name'] ?? '') === $subfolder_name && str_contains($sf['mimeType'] ?? '', 'folder')) {
                    $target_folder = $sf['id'];
                    break;
                }
            }

            $contents = file_get_contents($file['tmp_name']);
            $mime = $file['type'] ?: 'application/octet-stream';
            $filename = sanitize_file_name($file['name'] ?: 'upload');

            $result = WP_PQ_Drive::upload_file($target_folder, $drive_id, $filename, $mime, $contents);

            // Record in pq_task_files.
            $table = $wpdb->prefix . 'pq_task_files';
            $retention_days = (int) get_option('wp_pq_retention_days', 365);

            $max_version = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(version_num), 0) FROM {$table} WHERE task_id = %d AND file_role = %s",
                $task_id,
                $file_role
            ));

            $wpdb->insert($table, [
                'task_id' => $task_id,
                'uploader_id' => $user_id,
                'media_id' => null,
                'storage_type' => 'drive',
                'drive_file_id' => $result['id'],
                'drive_file_name' => $result['name'] ?? $filename,
                'drive_file_url' => $result['webViewLink'] ?? '',
                'drive_mime_type' => $result['mimeType'] ?? $mime,
                'drive_file_size' => (int) ($result['size'] ?? 0),
                'file_role' => $file_role,
                'version_num' => $max_version + 1,
                'storage_expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . $retention_days . ' days')),
                'created_at' => current_time('mysql', true),
            ]);

            $file_row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id),
                ARRAY_A
            );

            return new WP_REST_Response([
                'ok' => true,
                'file' => $file_row ? self::hydrate_file_row($file_row) : null,
                'task' => WP_PQ_API::get_enriched_task($task_id),
            ], 201);
        } catch (RuntimeException $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public static function upload_document_to_drive(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $files = $request->get_file_params();
        $file = $files['file'] ?? null;

        if (! $file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_REST_Response(['message' => 'No file provided.'], 422);
        }

        $client_id = (int) $request->get_param('client_id');
        if ($client_id <= 0) {
            return new WP_REST_Response(['message' => 'client_id is required for Drive uploads.'], 422);
        }

        try {
            $folder_id = WP_PQ_Drive::ensure_unattached_folder($client_id);
            $drive_id = WP_PQ_Drive::ensure_client_drive($client_id);

            $contents = file_get_contents($file['tmp_name']);
            $mime = $file['type'] ?: 'application/octet-stream';
            $filename = sanitize_file_name($file['name'] ?: 'upload');

            $result = WP_PQ_Drive::upload_file($folder_id, $drive_id, $filename, $mime, $contents);

            $table = $wpdb->prefix . 'pq_task_files';
            $retention_days = (int) get_option('wp_pq_retention_days', 365);

            $wpdb->insert($table, [
                'task_id' => 0,
                'uploader_id' => get_current_user_id(),
                'media_id' => null,
                'storage_type' => 'drive',
                'drive_file_id' => $result['id'],
                'drive_file_name' => $result['name'] ?? $filename,
                'drive_file_url' => $result['webViewLink'] ?? '',
                'drive_mime_type' => $result['mimeType'] ?? $mime,
                'drive_file_size' => (int) ($result['size'] ?? 0),
                'file_role' => 'input',
                'version_num' => 1,
                'storage_expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . $retention_days . ' days')),
                'created_at' => current_time('mysql', true),
            ]);

            return new WP_REST_Response(['ok' => true, 'id' => $wpdb->insert_id], 201);
        } catch (RuntimeException $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public static function drive_download_proxy(WP_REST_Request $request): WP_REST_Response
    {
        $drive_file_id = sanitize_text_field($request->get_param('file_id'));

        // Verify the user has access to a task that owns this file.
        global $wpdb;
        $table = $wpdb->prefix . 'pq_task_files';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE drive_file_id = %s LIMIT 1", $drive_file_id),
            ARRAY_A
        );

        if (! $row) {
            return new WP_REST_Response(['message' => 'File not found.'], 404);
        }

        $task_id = (int) ($row['task_id'] ?? 0);
        if ($task_id > 0) {
            $task = WP_PQ_API::get_task_row($task_id);
            if (! $task || ! WP_PQ_API::can_access_task($task, get_current_user_id())) {
                return new WP_REST_Response(['message' => 'Forbidden.'], 403);
            }
        } elseif (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        try {
            $download = WP_PQ_Drive::download_file($drive_file_id);

            header('Content-Type: ' . $download['mime_type']);
            header('Content-Disposition: attachment; filename="' . sanitize_file_name($download['filename']) . '"');
            header('Content-Length: ' . strlen($download['content']));
            echo $download['content'];
            exit;
        } catch (RuntimeException $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public static function hydrate_file_row(array $row): array
    {
        $storage_type = $row['storage_type'] ?? 'media';

        if ($storage_type === 'drive') {
            $row['media_url'] = $row['drive_file_url'] ?? '';
            $row['filename'] = $row['drive_file_name'] ?? '';
            $row['download_url'] = rest_url('pq/v1/drive/files/' . ($row['drive_file_id'] ?? '') . '/download');
            $row['filesize'] = (int) ($row['drive_file_size'] ?? 0);
        } else {
            $media_id = (int) ($row['media_id'] ?? 0);
            $row['media_url'] = $media_id > 0 ? wp_get_attachment_url($media_id) : '';
            $row['filename'] = $media_id > 0 ? wp_basename((string) get_attached_file($media_id)) : '';
        }

        return $row;
    }
}
