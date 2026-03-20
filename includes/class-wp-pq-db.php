<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_DB
{
    private static array $client_memberships_cache = [];
    private static array $job_member_ids_cache = [];
    private static array $job_member_ids_for_user_cache = [];
    private static array $user_client_memberships_cache = [];

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
        $clients = $wpdb->prefix . 'pq_clients';
        $client_members = $wpdb->prefix . 'pq_client_members';
        $job_members = $wpdb->prefix . 'pq_job_members';
        $billing_buckets = $wpdb->prefix . 'pq_billing_buckets';
        $statements = $wpdb->prefix . 'pq_statements';
        $statement_items = $wpdb->prefix . 'pq_statement_items';
        $statement_lines = $wpdb->prefix . 'pq_statement_lines';
        $work_logs = $wpdb->prefix . 'pq_work_logs';
        $work_log_items = $wpdb->prefix . 'pq_work_log_items';

        dbDelta("CREATE TABLE {$clients} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            primary_contact_user_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY primary_contact_user_id (primary_contact_user_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$client_members} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(40) NOT NULL DEFAULT 'client_contributor',
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY client_user (client_id, user_id),
            KEY user_id (user_id),
            KEY role (role)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$job_members} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            billing_bucket_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_user (billing_bucket_id, user_id),
            KEY user_id (user_id)
        ) {$charset_collate};");

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
            client_id BIGINT UNSIGNED NULL,
            client_user_id BIGINT UNSIGNED NULL,
            action_owner_id BIGINT UNSIGNED NULL,
            owner_ids LONGTEXT NULL,
            needs_meeting TINYINT(1) NOT NULL DEFAULT 0,
            is_billable TINYINT(1) NOT NULL DEFAULT 1,
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
            KEY client_id (client_id),
            KEY client_user_id (client_user_id),
            KEY action_owner_id (action_owner_id),
            KEY status (status),
            KEY is_billable (is_billable),
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
            client_id BIGINT UNSIGNED NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            bucket_name VARCHAR(190) NOT NULL,
            bucket_slug VARCHAR(190) NOT NULL,
            description LONGTEXT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY client_slug (client_user_id, bucket_slug),
            KEY client_id (client_id),
            KEY client_user_id (client_user_id),
            KEY is_default (is_default)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$statements} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            statement_code VARCHAR(50) NOT NULL,
            statement_month VARCHAR(7) NULL,
            client_id BIGINT UNSIGNED NULL,
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
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY statement_code (statement_code),
            KEY statement_month (statement_month),
            KEY client_id (client_id),
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

        dbDelta("CREATE TABLE {$statement_lines} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            statement_id BIGINT UNSIGNED NOT NULL,
            line_type VARCHAR(40) NOT NULL DEFAULT 'manual_adjustment',
            source_kind VARCHAR(20) NOT NULL DEFAULT 'manual',
            description LONGTEXT NULL,
            quantity DECIMAL(12,2) NULL,
            unit VARCHAR(40) NULL,
            unit_rate DECIMAL(12,2) NULL,
            line_amount DECIMAL(12,2) NULL,
            billing_bucket_id BIGINT UNSIGNED NULL,
            linked_task_ids LONGTEXT NULL,
            source_snapshot LONGTEXT NULL,
            notes LONGTEXT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY statement_id (statement_id),
            KEY line_type (line_type),
            KEY billing_bucket_id (billing_bucket_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$work_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            work_log_code VARCHAR(50) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            billing_bucket_id BIGINT UNSIGNED NOT NULL,
            range_start DATE NULL,
            range_end DATE NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            notes LONGTEXT NULL,
            snapshot_filters LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY work_log_code (work_log_code),
            KEY client_id (client_id),
            KEY client_user_id (client_user_id),
            KEY billing_bucket_id (billing_bucket_id),
            KEY range_start (range_start),
            KEY range_end (range_end)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$work_log_items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            work_log_id BIGINT UNSIGNED NOT NULL,
            task_id BIGINT UNSIGNED NOT NULL,
            task_title VARCHAR(255) NULL,
            task_description LONGTEXT NULL,
            task_status VARCHAR(50) NULL,
            task_billing_status VARCHAR(30) NULL,
            task_bucket_name VARCHAR(190) NULL,
            task_bucket_is_default TINYINT(1) NOT NULL DEFAULT 0,
            task_updated_at DATETIME NULL,
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

    public static function migrate_client_accounts(): void
    {
        global $wpdb;

        if (get_option('wp_pq_client_account_migration_098_applied')) {
            return;
        }

        $clients_table = $wpdb->prefix . 'pq_clients';
        $client_members_table = $wpdb->prefix . 'pq_client_members';
        $job_members_table = $wpdb->prefix . 'pq_job_members';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $statements_table = $wpdb->prefix . 'pq_statements';
        $work_logs_table = $wpdb->prefix . 'pq_work_logs';

        $user_ids = array_map('intval', array_unique(array_filter(array_merge(
            $wpdb->get_col("SELECT DISTINCT client_user_id FROM {$tasks_table} WHERE client_user_id > 0"),
            $wpdb->get_col("SELECT DISTINCT client_user_id FROM {$buckets_table} WHERE client_user_id > 0"),
            $wpdb->get_col("SELECT DISTINCT client_user_id FROM {$statements_table} WHERE client_user_id > 0"),
            $wpdb->get_col("SELECT DISTINCT client_user_id FROM {$work_logs_table} WHERE client_user_id > 0"),
            get_users([
                'role' => 'pq_client',
                'fields' => 'ID',
            ])
        ))));

        foreach ($user_ids as $user_id) {
            $client_id = self::get_or_create_client_id_for_user($user_id);
            if ($client_id <= 0) {
                continue;
            }

            self::ensure_client_member($client_id, $user_id, 'client_admin');

            $bucket_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$buckets_table} WHERE client_user_id = %d",
                $user_id
            ));

            foreach (array_map('intval', (array) $bucket_ids) as $bucket_id) {
                $wpdb->update($buckets_table, ['client_id' => $client_id], ['id' => $bucket_id]);
                self::ensure_job_member($bucket_id, $user_id);
            }

            $wpdb->query($wpdb->prepare(
                "UPDATE {$tasks_table} SET client_id = %d WHERE client_user_id = %d AND (client_id IS NULL OR client_id = 0)",
                $client_id,
                $user_id
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$statements_table} SET client_id = %d WHERE client_user_id = %d AND (client_id IS NULL OR client_id = 0)",
                $client_id,
                $user_id
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$work_logs_table} SET client_id = %d WHERE client_user_id = %d AND (client_id IS NULL OR client_id = 0)",
                $client_id,
                $user_id
            ));
        }

        $bucket_rows = $wpdb->get_results("SELECT id, client_id, client_user_id FROM {$buckets_table}", ARRAY_A);
        foreach ((array) $bucket_rows as $bucket_row) {
            $bucket_id = (int) ($bucket_row['id'] ?? 0);
            $client_id = (int) ($bucket_row['client_id'] ?? 0);
            $primary_user_id = (int) ($bucket_row['client_user_id'] ?? 0);
            if ($bucket_id <= 0) {
                continue;
            }
            if ($client_id <= 0 && $primary_user_id > 0) {
                $client_id = self::get_or_create_client_id_for_user($primary_user_id);
                if ($client_id > 0) {
                    $wpdb->update($buckets_table, ['client_id' => $client_id], ['id' => $bucket_id]);
                }
            }
            if ($bucket_id > 0 && $primary_user_id > 0) {
                self::ensure_job_member($bucket_id, $primary_user_id);
            }
        }

        $client_members = $wpdb->get_results("SELECT client_id, user_id FROM {$client_members_table}", ARRAY_A);
        foreach ((array) $client_members as $membership) {
            $client_id = (int) ($membership['client_id'] ?? 0);
            $user_id = (int) ($membership['user_id'] ?? 0);
            if ($client_id <= 0 || $user_id <= 0) {
                continue;
            }

            $bucket_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$buckets_table} WHERE client_id = %d AND is_default = 1",
                $client_id
            ));
            foreach (array_map('intval', (array) $bucket_ids) as $bucket_id) {
                self::ensure_job_member($bucket_id, $user_id);
            }
        }

        update_option('wp_pq_client_account_migration_098_applied', 1, false);
    }

    public static function ensure_default_billing_buckets(): void
    {
        global $wpdb;

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $tasks_table = $wpdb->prefix . 'pq_tasks';

        $client_user_ids = $wpdb->get_col("SELECT DISTINCT submitter_id FROM {$tasks_table} WHERE submitter_id > 0");
        if (empty($client_user_ids)) {
            return;
        }

        foreach (array_map('intval', $client_user_ids) as $client_user_id) {
            $client_id = self::get_or_create_client_id_for_user($client_user_id);
            if ($client_id <= 0) {
                continue;
            }

            $default_bucket_id = self::get_or_create_default_billing_bucket_id($client_id);

            if ($default_bucket_id > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$tasks_table} SET billing_bucket_id = %d WHERE submitter_id = %d AND (billing_bucket_id IS NULL OR billing_bucket_id = 0)",
                    $default_bucket_id,
                    $client_user_id
                ));
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$tasks_table} SET client_id = %d WHERE submitter_id = %d AND (client_id IS NULL OR client_id = 0)",
                    $client_id,
                    $client_user_id
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
            $client_id = self::get_or_create_client_id_for_user((int) $client_user_id);
            if ($client_id <= 0) {
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
                    "SELECT id FROM {$buckets_table} WHERE client_id = %d ORDER BY id ASC LIMIT 1",
                    $client_id
                ));
                if ($default_bucket_id > 0) {
                    $wpdb->update($buckets_table, ['is_default' => 1], ['id' => $default_bucket_id]);
                }
            }

            $default_bucket_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$buckets_table} WHERE client_id = %d AND is_default = 1 ORDER BY id ASC LIMIT 1",
                $client_id
            ));
            if ($default_bucket_id > 0) {
                $suggested = self::suggest_default_bucket_name($client_id);
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

    public static function migrate_invoice_draft_models(): void
    {
        global $wpdb;

        if (get_option('wp_pq_invoice_draft_migration_120_applied')) {
            return;
        }

        $statements_table = $wpdb->prefix . 'pq_statements';
        $statement_items_table = $wpdb->prefix . 'pq_statement_items';
        $statement_lines_table = $wpdb->prefix . 'pq_statement_lines';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $statement_ids = array_map('intval', (array) $wpdb->get_col("SELECT id FROM {$statements_table}"));
        foreach ($statement_ids as $statement_id) {
            if ($statement_id <= 0) {
                continue;
            }

            $line_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$statement_lines_table} WHERE statement_id = %d",
                $statement_id
            ));
            if ($line_count > 0) {
                continue;
            }

            $statement = $wpdb->get_row($wpdb->prepare(
                "SELECT statement_code, billing_bucket_id, total_amount FROM {$statements_table} WHERE id = %d",
                $statement_id
            ), ARRAY_A);
            if (! $statement) {
                continue;
            }

            $task_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
                "SELECT task_id FROM {$statement_items_table} WHERE statement_id = %d ORDER BY id ASC",
                $statement_id
            )));
            $bucket_id = (int) ($statement['billing_bucket_id'] ?? 0);
            $bucket_name = $bucket_id > 0
                ? (string) $wpdb->get_var($wpdb->prepare("SELECT bucket_name FROM {$buckets_table} WHERE id = %d", $bucket_id))
                : '';
            $description = $bucket_name !== '' ? 'Imported task rollup for ' . $bucket_name : 'Imported task rollup';
            $now = current_time('mysql', true);

            $wpdb->insert($statement_lines_table, [
                'statement_id' => $statement_id,
                'line_type' => 'task_rollup',
                'source_kind' => 'task',
                'description' => $description,
                'quantity' => count($task_ids),
                'unit' => 'tasks',
                'unit_rate' => null,
                'line_amount' => $statement['total_amount'] !== null ? number_format((float) $statement['total_amount'], 2, '.', '') : null,
                'billing_bucket_id' => $bucket_id > 0 ? $bucket_id : null,
                'linked_task_ids' => ! empty($task_ids) ? wp_json_encode($task_ids) : null,
                'source_snapshot' => wp_json_encode([
                    'task_ids' => $task_ids,
                    'suggested_description' => $description,
                    'suggested_quantity' => count($task_ids),
                    'suggested_unit' => 'tasks',
                    'billing_bucket_id' => $bucket_id,
                ]),
                'notes' => '',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        update_option('wp_pq_invoice_draft_migration_120_applied', 1, false);
    }

    public static function migrate_work_statement_snapshots(): void
    {
        global $wpdb;

        if (get_option('wp_pq_work_statement_snapshot_migration_120_applied')) {
            return;
        }

        $items_table = $wpdb->prefix . 'pq_work_log_items';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $rows = $wpdb->get_results(
            "SELECT wi.id, wi.task_id
             FROM {$items_table} wi
             WHERE wi.task_title IS NULL OR wi.task_title = ''",
            ARRAY_A
        );

        foreach ((array) $rows as $row) {
            $item_id = (int) ($row['id'] ?? 0);
            $task_id = (int) ($row['task_id'] ?? 0);
            if ($item_id <= 0 || $task_id <= 0) {
                continue;
            }

            $task = $wpdb->get_row($wpdb->prepare(
                "SELECT t.*, b.bucket_name, b.is_default
                 FROM {$tasks_table} t
                 LEFT JOIN {$buckets_table} b ON b.id = t.billing_bucket_id
                 WHERE t.id = %d",
                $task_id
            ), ARRAY_A);
            if (! $task) {
                continue;
            }

            $wpdb->update($items_table, [
                'task_title' => (string) ($task['title'] ?? ''),
                'task_description' => (string) ($task['description'] ?? ''),
                'task_status' => (string) ($task['status'] ?? ''),
                'task_billing_status' => (string) ($task['billing_status'] ?? ''),
                'task_bucket_name' => (string) ($task['bucket_name'] ?? ''),
                'task_bucket_is_default' => (int) ($task['is_default'] ?? 0) === 1 ? 1 : 0,
                'task_updated_at' => (string) ($task['updated_at'] ?? $task['created_at'] ?? current_time('mysql', true)),
            ], ['id' => $item_id]);
        }

        update_option('wp_pq_work_statement_snapshot_migration_120_applied', 1, false);
    }

    public static function get_or_create_default_billing_bucket_id(int $client_id): int
    {
        global $wpdb;

        if ($client_id <= 0) {
            return 0;
        }

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $default_bucket_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_id = %d AND is_default = 1 LIMIT 1",
            $client_id
        ));

        if ($default_bucket_id > 0) {
            return $default_bucket_id;
        }

        $first_bucket_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_id = %d ORDER BY id ASC LIMIT 1",
            $client_id
        ));
        if ($first_bucket_id > 0) {
            $wpdb->update($buckets_table, ['is_default' => 1], ['id' => $first_bucket_id]);
            return $first_bucket_id;
        }

        $created_by = get_current_user_id();
        if ($created_by <= 0) {
            $created_by = self::get_primary_contact_user_id($client_id);
        }
        if ($created_by <= 0) {
            $created_by = 1;
        }

        $wpdb->insert($buckets_table, [
            'client_id' => $client_id,
            'client_user_id' => self::get_primary_contact_user_id($client_id),
            'bucket_name' => self::suggest_default_bucket_name($client_id),
            'bucket_slug' => sanitize_title(self::suggest_default_bucket_name($client_id)),
            'description' => '',
            'is_default' => 1,
            'created_by' => $created_by,
            'created_at' => current_time('mysql', true),
        ]);

        $bucket_id = (int) $wpdb->insert_id;
        if ($bucket_id > 0) {
            foreach (self::get_client_admin_user_ids($client_id) as $admin_user_id) {
                self::ensure_job_member($bucket_id, $admin_user_id);
            }
        }

        return $bucket_id;
    }

    public static function suggest_default_bucket_name(int $client_id): string
    {
        $base = trim(self::get_client_name($client_id));
        if ($base === '') {
            return 'Main';
        }

        return $base . ' - Main';
    }

    public static function get_or_create_client_id_for_user(int $primary_contact_user_id, string $fallback_name = ''): int
    {
        global $wpdb;

        if ($primary_contact_user_id <= 0) {
            return 0;
        }

        $clients_table = $wpdb->prefix . 'pq_clients';
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$clients_table} WHERE primary_contact_user_id = %d LIMIT 1",
            $primary_contact_user_id
        ));
        if ($existing_id > 0) {
            return $existing_id;
        }

        $user = get_user_by('ID', $primary_contact_user_id);
        $name = trim($fallback_name);
        if ($name === '' && $user) {
            $name = trim((string) $user->display_name);
        }
        if ($name === '' && $user) {
            $name = trim((string) $user->user_email);
        }
        if ($name === '') {
            $name = 'Client ' . $primary_contact_user_id;
        }

        $base_slug = sanitize_title($name);
        if ($base_slug === '') {
            $base_slug = 'client-' . $primary_contact_user_id;
        }
        $slug = $base_slug;
        $suffix = 2;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$clients_table} WHERE slug = %s", $slug)) > 0) {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }

        $now = current_time('mysql', true);
        $created_by = get_current_user_id();
        if ($created_by <= 0) {
            $created_by = $primary_contact_user_id;
        }

        $wpdb->insert($clients_table, [
            'name' => $name,
            'slug' => $slug,
            'primary_contact_user_id' => $primary_contact_user_id,
            'created_by' => $created_by,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function ensure_client_member(int $client_id, int $user_id, string $role = 'client_contributor'): void
    {
        global $wpdb;

        if ($client_id <= 0 || $user_id <= 0) {
            return;
        }

        $table = $wpdb->prefix . 'pq_client_members';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, role FROM {$table} WHERE client_id = %d AND user_id = %d LIMIT 1",
            $client_id,
            $user_id
        ), ARRAY_A);

        $now = current_time('mysql', true);
        $created_by = get_current_user_id();
        if ($created_by <= 0) {
            $created_by = $user_id;
        }

        if ($existing) {
            if ((string) ($existing['role'] ?? '') !== $role) {
                $wpdb->update($table, [
                    'role' => $role,
                    'updated_at' => $now,
                ], ['id' => (int) $existing['id']]);
            }
            self::clear_client_membership_cache($client_id, $user_id);
            if ($role === 'client_admin') {
                self::ensure_client_admin_job_access($client_id, $user_id);
            }
            return;
        }

        $wpdb->insert($table, [
            'client_id' => $client_id,
            'user_id' => $user_id,
            'role' => $role,
            'created_by' => $created_by,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        self::clear_client_membership_cache($client_id, $user_id);

        if ($role === 'client_admin') {
            self::ensure_client_admin_job_access($client_id, $user_id);
        }
    }

    public static function ensure_job_member(int $billing_bucket_id, int $user_id): void
    {
        global $wpdb;

        if ($billing_bucket_id <= 0 || $user_id <= 0) {
            return;
        }

        $table = $wpdb->prefix . 'pq_job_members';
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE billing_bucket_id = %d AND user_id = %d LIMIT 1",
            $billing_bucket_id,
            $user_id
        ));
        if ($existing_id > 0) {
            return;
        }

        $now = current_time('mysql', true);
        $created_by = get_current_user_id();
        if ($created_by <= 0) {
            $created_by = $user_id;
        }

        $wpdb->insert($table, [
            'billing_bucket_id' => $billing_bucket_id,
            'user_id' => $user_id,
            'created_by' => $created_by,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        self::clear_job_membership_cache($billing_bucket_id, $user_id);
    }

    private static function ensure_client_admin_job_access(int $client_id, int $user_id): void
    {
        global $wpdb;

        if ($client_id <= 0 || $user_id <= 0) {
            return;
        }

        $bucket_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}pq_billing_buckets WHERE client_id = %d",
            $client_id
        ));
        if (empty($bucket_ids)) {
            return;
        }

        foreach ($bucket_ids as $bucket_id) {
            self::ensure_job_member((int) $bucket_id, $user_id);
        }
    }

    public static function get_client_name(int $client_id): string
    {
        global $wpdb;

        if ($client_id <= 0) {
            return '';
        }

        $clients_table = $wpdb->prefix . 'pq_clients';
        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$clients_table} WHERE id = %d LIMIT 1",
            $client_id
        ));
    }

    public static function get_primary_contact_user_id(int $client_id): int
    {
        global $wpdb;

        if ($client_id <= 0) {
            return 0;
        }

        $clients_table = $wpdb->prefix . 'pq_clients';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT primary_contact_user_id FROM {$clients_table} WHERE id = %d LIMIT 1",
            $client_id
        ));
    }

    public static function get_client_memberships(int $client_id): array
    {
        global $wpdb;

        if ($client_id <= 0) {
            return [];
        }

        if (array_key_exists($client_id, self::$client_memberships_cache)) {
            return self::$client_memberships_cache[$client_id];
        }

        $table = $wpdb->prefix . 'pq_client_members';
        self::$client_memberships_cache[$client_id] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE client_id = %d ORDER BY id ASC",
            $client_id
        ), ARRAY_A) ?: [];

        return self::$client_memberships_cache[$client_id];
    }

    public static function get_client_admin_user_ids(int $client_id): array
    {
        return array_values(array_map(
            static fn(array $membership): int => (int) ($membership['user_id'] ?? 0),
            array_filter(self::get_client_memberships($client_id), static function (array $membership): bool {
                return (string) ($membership['role'] ?? '') === 'client_admin' && (int) ($membership['user_id'] ?? 0) > 0;
            })
        ));
    }

    public static function get_job_member_ids(int $billing_bucket_id): array
    {
        global $wpdb;

        if ($billing_bucket_id <= 0) {
            return [];
        }

        if (array_key_exists($billing_bucket_id, self::$job_member_ids_cache)) {
            return self::$job_member_ids_cache[$billing_bucket_id];
        }

        $table = $wpdb->prefix . 'pq_job_members';
        self::$job_member_ids_cache[$billing_bucket_id] = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE billing_bucket_id = %d",
            $billing_bucket_id
        )));

        return self::$job_member_ids_cache[$billing_bucket_id];
    }

    public static function get_job_member_ids_for_user(int $user_id): array
    {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        if (array_key_exists($user_id, self::$job_member_ids_for_user_cache)) {
            return self::$job_member_ids_for_user_cache[$user_id];
        }

        $table = $wpdb->prefix . 'pq_job_members';
        self::$job_member_ids_for_user_cache[$user_id] = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT billing_bucket_id FROM {$table} WHERE user_id = %d",
            $user_id
        )));

        return self::$job_member_ids_for_user_cache[$user_id];
    }

    public static function get_user_client_memberships(int $user_id): array
    {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        if (array_key_exists($user_id, self::$user_client_memberships_cache)) {
            return self::$user_client_memberships_cache[$user_id];
        }

        $table = $wpdb->prefix . 'pq_client_members';
        self::$user_client_memberships_cache[$user_id] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC",
            $user_id
        ), ARRAY_A) ?: [];

        return self::$user_client_memberships_cache[$user_id];
    }

    private static function clear_client_membership_cache(int $client_id, int $user_id): void
    {
        unset(self::$client_memberships_cache[$client_id], self::$user_client_memberships_cache[$user_id]);
    }

    private static function clear_job_membership_cache(int $billing_bucket_id, int $user_id): void
    {
        unset(self::$job_member_ids_cache[$billing_bucket_id], self::$job_member_ids_for_user_cache[$user_id]);
    }
}
