<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_DB
{
    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $tasks = $wpdb->prefix . 'pq_tasks';
        $history = $wpdb->prefix . 'pq_task_status_history';
        $files = $wpdb->prefix . 'pq_task_files';
        $messages = $wpdb->prefix . 'pq_task_messages';
        $comments = $wpdb->prefix . 'pq_task_comments';
        $meetings = $wpdb->prefix . 'pq_task_meetings';
        $prefs = $wpdb->prefix . 'pq_notification_prefs';
        $notifications = $wpdb->prefix . 'pq_notifications';
        $billing_buckets = $wpdb->prefix . 'pq_billing_buckets';
        $statements = $wpdb->prefix . 'pq_statements';
        $statement_items = $wpdb->prefix . 'pq_statement_items';
        $work_logs = $wpdb->prefix . 'pq_work_logs';
        $work_log_items = $wpdb->prefix . 'pq_work_log_items';

        dbDelta("CREATE TABLE {$tasks} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            queue_position INT UNSIGNED NOT NULL DEFAULT 0,
            due_at DATETIME NULL,
            requested_deadline DATETIME NULL,
            submitter_id BIGINT UNSIGNED NOT NULL,
            client_user_id BIGINT UNSIGNED NULL,
            action_owner_id BIGINT UNSIGNED NULL,
            owner_ids LONGTEXT NULL,
            needs_meeting TINYINT(1) NOT NULL DEFAULT 0,
            billing_bucket_id BIGINT UNSIGNED NULL,
            delivered_at DATETIME NULL,
            completed_at DATETIME NULL,
            billing_status VARCHAR(30) NOT NULL DEFAULT 'unbilled',
            work_log_id BIGINT UNSIGNED NULL,
            work_logged_at DATETIME NULL,
            statement_id BIGINT UNSIGNED NULL,
            statement_batched_at DATETIME NULL,
            google_event_id VARCHAR(191) NULL,
            google_event_url VARCHAR(500) NULL,
            google_event_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY submitter_id (submitter_id),
            KEY client_user_id (client_user_id),
            KEY action_owner_id (action_owner_id),
            KEY status (status),
            KEY billing_bucket_id (billing_bucket_id),
            KEY billing_status (billing_status),
            KEY work_log_id (work_log_id),
            KEY statement_id (statement_id),
            KEY queue_position (queue_position),
            KEY google_event_id (google_event_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$history} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NOT NULL,
            changed_by BIGINT UNSIGNED NOT NULL,
            note LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY new_status (new_status)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$files} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            uploader_id BIGINT UNSIGNED NOT NULL,
            media_id BIGINT UNSIGNED NOT NULL,
            file_role VARCHAR(30) NOT NULL DEFAULT 'input',
            version_num INT UNSIGNED NOT NULL DEFAULT 1,
            storage_expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY file_role (file_role),
            KEY storage_expires_at (storage_expires_at)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$messages} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            author_id BIGINT UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$comments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            author_id BIGINT UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$meetings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(30) NOT NULL DEFAULT 'google',
            event_id VARCHAR(191) NULL,
            meeting_url VARCHAR(500) NULL,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            sync_direction VARCHAR(20) NOT NULL DEFAULT 'two_way',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY event_id (event_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$prefs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            event_key VARCHAR(50) NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_event (user_id, event_key),
            KEY user_id (user_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$notifications} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            task_id BIGINT UNSIGNED NULL,
            event_key VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body LONGTEXT NULL,
            payload LONGTEXT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            read_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY task_id (task_id),
            KEY event_key (event_key),
            KEY is_read (is_read)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$billing_buckets} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_user_id BIGINT UNSIGNED NOT NULL,
            bucket_name VARCHAR(190) NOT NULL,
            bucket_slug VARCHAR(190) NOT NULL,
            description LONGTEXT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY client_slug (client_user_id, bucket_slug),
            KEY client_user_id (client_user_id),
            KEY is_default (is_default)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$statements} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            statement_code VARCHAR(50) NOT NULL,
            statement_month VARCHAR(7) NULL,
            client_user_id BIGINT UNSIGNED NULL,
            billing_bucket_id BIGINT UNSIGNED NULL,
            range_start DATE NULL,
            range_end DATE NULL,
            currency_code VARCHAR(10) NULL,
            total_amount DECIMAL(12,2) NULL,
            due_date DATE NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY statement_code (statement_code),
            KEY statement_month (statement_month),
            KEY client_user_id (client_user_id),
            KEY billing_bucket_id (billing_bucket_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$statement_items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            statement_id BIGINT UNSIGNED NOT NULL,
            task_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY statement_task (statement_id, task_id),
            KEY task_id (task_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$work_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            work_log_code VARCHAR(50) NOT NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            billing_bucket_id BIGINT UNSIGNED NOT NULL,
            range_start DATE NULL,
            range_end DATE NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY work_log_code (work_log_code),
            KEY client_user_id (client_user_id),
            KEY billing_bucket_id (billing_bucket_id),
            KEY range_start (range_start),
            KEY range_end (range_end)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$work_log_items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            work_log_id BIGINT UNSIGNED NOT NULL,
            task_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY work_log_task (work_log_id, task_id),
            KEY task_id (task_id)
        ) {$charset_collate};");
    }

    public static function migrate_legacy_statuses(): void
    {
        global $wpdb;

        if (get_option('wp_pq_status_migration_066_applied')) {
            return;
        }

        $tasks = $wpdb->prefix . 'pq_tasks';
        $history = $wpdb->prefix . 'pq_task_status_history';

        $wpdb->update($tasks, ['status' => 'pending_approval'], ['status' => 'pending_review']);
        $wpdb->update($history, ['old_status' => 'pending_approval'], ['old_status' => 'pending_review']);
        $wpdb->update($history, ['new_status' => 'pending_approval'], ['new_status' => 'pending_review']);

        $wpdb->update($tasks, [
            'status' => 'delivered',
            'billing_status' => 'batched',
            'statement_batched_at' => current_time('mysql', true),
        ], ['status' => 'completed']);
        $wpdb->update($history, ['old_status' => 'delivered'], ['old_status' => 'completed']);
        $wpdb->update($history, ['new_status' => 'delivered'], ['new_status' => 'completed']);

        update_option('wp_pq_status_migration_066_applied', 1, false);
    }

    public static function migrate_task_context_fields(): void
    {
        global $wpdb;

        if (get_option('wp_pq_task_context_migration_087_applied')) {
            return;
        }

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $tasks = $wpdb->get_results("SELECT id, submitter_id, client_user_id, action_owner_id, owner_ids, billing_bucket_id FROM {$tasks_table}", ARRAY_A);
        if (! is_array($tasks)) {
            update_option('wp_pq_task_context_migration_087_applied', 1, false);
            return;
        }

        foreach ($tasks as $task) {
            $task_id = (int) ($task['id'] ?? 0);
            if ($task_id <= 0) {
                continue;
            }

            $client_user_id = (int) ($task['client_user_id'] ?? 0);
            if ($client_user_id <= 0) {
                $bucket_id = (int) ($task['billing_bucket_id'] ?? 0);
                if ($bucket_id > 0) {
                    $client_user_id = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT client_user_id FROM {$buckets_table} WHERE id = %d LIMIT 1",
                        $bucket_id
                    ));
                }
                if ($client_user_id <= 0) {
                    $client_user_id = (int) ($task['submitter_id'] ?? 0);
                }
            }

            $action_owner_id = (int) ($task['action_owner_id'] ?? 0);
            if ($action_owner_id <= 0) {
                $owner_ids = json_decode((string) ($task['owner_ids'] ?? ''), true);
                if (is_array($owner_ids) && ! empty($owner_ids)) {
                    $action_owner_id = (int) reset($owner_ids);
                }
            }

            $update = [];
            if ($client_user_id > 0 && (int) ($task['client_user_id'] ?? 0) !== $client_user_id) {
                $update['client_user_id'] = $client_user_id;
            }
            if ($action_owner_id > 0 && (int) ($task['action_owner_id'] ?? 0) !== $action_owner_id) {
                $update['action_owner_id'] = $action_owner_id;
            }

            if (! empty($update)) {
                $wpdb->update($tasks_table, $update, ['id' => $task_id]);
            }
        }

        update_option('wp_pq_task_context_migration_087_applied', 1, false);
    }

    public static function ensure_default_billing_buckets(): void
    {
        global $wpdb;

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $tasks_table = $wpdb->prefix . 'pq_tasks';

        $client_ids = $wpdb->get_col("SELECT DISTINCT submitter_id FROM {$tasks_table} WHERE submitter_id > 0");
        if (empty($client_ids)) {
            return;
        }

        foreach (array_map('intval', $client_ids) as $client_id) {
            $default_bucket_id = self::get_or_create_default_billing_bucket_id($client_id);

            if ($default_bucket_id > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$tasks_table} SET billing_bucket_id = %d WHERE submitter_id = %d AND (billing_bucket_id IS NULL OR billing_bucket_id = 0)",
                    $default_bucket_id,
                    $client_id
                ));
            }
        }
    }

    public static function migrate_named_default_buckets(): void
    {
        global $wpdb;

        if (get_option('wp_pq_named_bucket_migration_090_applied')) {
            return;
        }

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $work_logs_table = $wpdb->prefix . 'pq_work_logs';
        $statements_table = $wpdb->prefix . 'pq_statements';

        $rows = $wpdb->get_results("SELECT id, client_user_id, bucket_name, is_default FROM {$buckets_table} ORDER BY client_user_id ASC, is_default DESC, id ASC", ARRAY_A);
        $by_client = [];
        foreach ((array) $rows as $row) {
            $by_client[(int) $row['client_user_id']][] = $row;
        }

        foreach ($by_client as $client_user_id => $client_buckets) {
            if ($client_user_id <= 0) {
                continue;
            }

            $default_bucket = null;
            $other_buckets = [];
            foreach ($client_buckets as $bucket) {
                if ((int) ($bucket['is_default'] ?? 0) === 1 && ! $default_bucket) {
                    $default_bucket = $bucket;
                    continue;
                }
                $other_buckets[] = $bucket;
            }

            if ($default_bucket) {
                $name = strtolower(trim((string) ($default_bucket['bucket_name'] ?? '')));
                $is_generic = in_array($name, ['general', 'default', 'default bucket'], true);
                if ($is_generic && ! empty($other_buckets)) {
                    $default_bucket_id = (int) $default_bucket['id'];
                    $task_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tasks_table} WHERE billing_bucket_id = %d", $default_bucket_id));
                    $work_log_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$work_logs_table} WHERE billing_bucket_id = %d", $default_bucket_id));
                    $statement_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$statements_table} WHERE billing_bucket_id = %d", $default_bucket_id));

                    if ($task_count === 0 && $work_log_count === 0 && $statement_count === 0) {
                        $replacement_id = (int) ($other_buckets[0]['id'] ?? 0);
                        if ($replacement_id > 0) {
                            $wpdb->update($buckets_table, ['is_default' => 1], ['id' => $replacement_id]);
                            $wpdb->delete($buckets_table, ['id' => $default_bucket_id]);
                            $default_bucket = null;
                        }
                    }
                }
            }

            if (! $default_bucket) {
                $default_bucket_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$buckets_table} WHERE client_user_id = %d ORDER BY id ASC LIMIT 1",
                    $client_user_id
                ));
                if ($default_bucket_id > 0) {
                    $wpdb->update($buckets_table, ['is_default' => 1], ['id' => $default_bucket_id]);
                }
            }

            $default_bucket_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$buckets_table} WHERE client_user_id = %d AND is_default = 1 ORDER BY id ASC LIMIT 1",
                $client_user_id
            ));
            if ($default_bucket_id > 0) {
                $suggested = self::suggest_default_bucket_name($client_user_id);
                $current_name = (string) $wpdb->get_var($wpdb->prepare(
                    "SELECT bucket_name FROM {$buckets_table} WHERE id = %d",
                    $default_bucket_id
                ));
                if (in_array(strtolower(trim($current_name)), ['general', 'default', 'default bucket'], true)) {
                    $wpdb->update($buckets_table, [
                        'bucket_name' => $suggested,
                        'bucket_slug' => sanitize_title($suggested),
                    ], ['id' => $default_bucket_id]);
                }
            }
        }

        update_option('wp_pq_named_bucket_migration_090_applied', 1, false);
    }

    public static function get_or_create_default_billing_bucket_id(int $client_user_id): int
    {
        global $wpdb;

        if ($client_user_id <= 0) {
            return 0;
        }

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $default_bucket_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_user_id = %d AND is_default = 1 LIMIT 1",
            $client_user_id
        ));

        if ($default_bucket_id > 0) {
            return $default_bucket_id;
        }

        $first_bucket_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_user_id = %d ORDER BY id ASC LIMIT 1",
            $client_user_id
        ));
        if ($first_bucket_id > 0) {
            $wpdb->update($buckets_table, ['is_default' => 1], ['id' => $first_bucket_id]);
            return $first_bucket_id;
        }

        $created_by = get_current_user_id();
        if ($created_by <= 0) {
            $created_by = $client_user_id;
        }
        if ($created_by <= 0) {
            $created_by = 1;
        }

        $wpdb->insert($buckets_table, [
            'client_user_id' => $client_user_id,
            'bucket_name' => self::suggest_default_bucket_name($client_user_id),
            'bucket_slug' => sanitize_title(self::suggest_default_bucket_name($client_user_id)),
            'description' => '',
            'is_default' => 1,
            'created_by' => $created_by,
            'created_at' => current_time('mysql', true),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function suggest_default_bucket_name(int $client_user_id): string
    {
        $user = $client_user_id > 0 ? get_user_by('ID', $client_user_id) : null;
        $base = $user ? trim((string) $user->display_name) : '';
        if ($base === '') {
            return 'Main';
        }

        return $base . ' - Main';
    }
}
