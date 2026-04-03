<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One-time data migrations extracted from WP_PQ_DB.
 *
 * Each method checks an option flag and returns early if already applied.
 * They are called from WP_PQ_Plugin::boot() on every page load but only
 * execute real work once per site.
 */
class WP_PQ_Migrations
{
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

        update_option('wp_pq_status_migration_066_applied', 1, true);
    }

    public static function migrate_workflow_status_model(): void
    {
        global $wpdb;

        if (get_option('wp_pq_workflow_status_migration_130_applied')) {
            return;
        }

        $tasks = $wpdb->prefix . 'pq_tasks';
        $history = $wpdb->prefix . 'pq_task_status_history';
        $status_map = WP_PQ_Workflow::status_aliases();

        foreach ($status_map as $legacy_status => $canonical_status) {
            $wpdb->update($tasks, ['status' => $canonical_status], ['status' => $legacy_status]);
            $wpdb->update($history, ['old_status' => $canonical_status], ['old_status' => $legacy_status]);
            $wpdb->update($history, ['new_status' => $canonical_status], ['new_status' => $legacy_status]);
        }

        $wpdb->query(
            "UPDATE {$tasks}
             SET done_at = COALESCE(done_at, completed_at, updated_at),
                 archived_at = COALESCE(archived_at, completed_at, updated_at)
             WHERE status = 'done'"
        );

        $wpdb->query(
            "UPDATE {$history}
             SET reason_code = 'revisions_requested'
             WHERE reason_code IS NULL
               AND old_status IN ('delivered', 'needs_review')
               AND new_status = 'in_progress'"
        );

        update_option('wp_pq_workflow_status_migration_130_applied', 1, true);
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
            update_option('wp_pq_task_context_migration_087_applied', 1, true);
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

        update_option('wp_pq_task_context_migration_087_applied', 1, true);
    }

    public static function migrate_workflow_ledger_model(): void
    {
        global $wpdb;

        if (get_option('wp_pq_workflow_ledger_migration_131_applied')) {
            return;
        }

        $tasks = $wpdb->prefix . 'pq_tasks';
        $history = $wpdb->prefix . 'pq_task_status_history';
        $ledger = $wpdb->prefix . 'pq_work_ledger_entries';

        $wpdb->query(
            "UPDATE {$tasks} t
             SET revision_count = (
                 SELECT COUNT(*)
                 FROM {$history} h
                 WHERE h.task_id = t.id
                   AND h.reason_code = 'revisions_requested'
             )"
        );

        $wpdb->query(
            "INSERT INTO {$ledger} (
                task_id,
                client_id,
                billing_bucket_id,
                title_snapshot,
                work_summary,
                owner_id,
                completion_date,
                billable,
                billing_mode,
                billing_category,
                invoice_status,
                statement_month,
                invoice_draft_id,
                hours,
                rate,
                amount,
                created_at,
                updated_at
            )
            SELECT
                t.id,
                NULLIF(t.client_id, 0),
                NULLIF(t.billing_bucket_id, 0),
                t.title,
                COALESCE(NULLIF(t.work_summary, ''), NULLIF(t.description, ''), t.title),
                NULLIF(COALESCE(t.action_owner_id, t.submitter_id), 0),
                COALESCE(t.done_at, t.completed_at, t.updated_at, t.created_at),
                CASE WHEN t.is_billable = 1 THEN 1 ELSE 0 END,
                CASE
                    WHEN t.billing_mode IS NOT NULL AND t.billing_mode <> '' THEN t.billing_mode
                    WHEN t.is_billable = 1 THEN 'fixed_fee'
                    ELSE 'non_billable'
                END,
                COALESCE(NULLIF(t.billing_category, ''), 'general'),
                CASE
                    WHEN t.is_billable <> 1 THEN 'written_off'
                    WHEN t.billing_status = 'paid' THEN 'paid'
                    WHEN t.statement_id IS NOT NULL AND t.statement_id > 0 THEN 'invoiced'
                    WHEN t.billing_status IN ('batched', 'statement_sent') THEN 'invoiced'
                    ELSE 'unbilled'
                END,
                DATE_FORMAT(COALESCE(t.done_at, t.completed_at, t.updated_at, t.created_at), '%Y-%m'),
                NULLIF(t.statement_id, 0),
                t.hours,
                t.rate,
                t.amount,
                COALESCE(t.done_at, t.completed_at, t.updated_at, t.created_at),
                COALESCE(t.updated_at, t.done_at, t.completed_at, t.created_at)
            FROM {$tasks} t
            LEFT JOIN {$ledger} l ON l.task_id = t.id
            WHERE l.id IS NULL
              AND t.status = 'done'"
        );

        update_option('wp_pq_workflow_ledger_migration_131_applied', 1, true);
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
            $client_id = WP_PQ_DB::get_or_create_client_id_for_user($user_id);
            if ($client_id <= 0) {
                continue;
            }

            WP_PQ_DB::ensure_client_member($client_id, $user_id, 'client_admin');

            $bucket_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$buckets_table} WHERE client_user_id = %d",
                $user_id
            ));

            foreach (array_map('intval', (array) $bucket_ids) as $bucket_id) {
                $wpdb->update($buckets_table, ['client_id' => $client_id], ['id' => $bucket_id]);
                WP_PQ_DB::ensure_job_member($bucket_id, $user_id);
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
                $client_id = WP_PQ_DB::get_or_create_client_id_for_user($primary_user_id);
                if ($client_id > 0) {
                    $wpdb->update($buckets_table, ['client_id' => $client_id], ['id' => $bucket_id]);
                }
            }
            if ($bucket_id > 0 && $primary_user_id > 0) {
                WP_PQ_DB::ensure_job_member($bucket_id, $primary_user_id);
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
                WP_PQ_DB::ensure_job_member($bucket_id, $user_id);
            }
        }

        update_option('wp_pq_client_account_migration_098_applied', 1, true);
    }

    public static function ensure_default_billing_buckets(): void
    {
        if (get_option('wp_pq_default_billing_buckets_applied')) {
            return;
        }

        global $wpdb;

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $tasks_table = $wpdb->prefix . 'pq_tasks';

        $client_user_ids = $wpdb->get_col("SELECT DISTINCT submitter_id FROM {$tasks_table} WHERE submitter_id > 0");
        if (empty($client_user_ids)) {
            update_option('wp_pq_default_billing_buckets_applied', 1, true);
            return;
        }

        foreach (array_map('intval', $client_user_ids) as $client_user_id) {
            $client_id = WP_PQ_DB::get_or_create_client_id_for_user($client_user_id);
            if ($client_id <= 0) {
                continue;
            }

            $default_bucket_id = WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);

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

        update_option('wp_pq_default_billing_buckets_applied', 1, true);
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
            $client_id = WP_PQ_DB::get_or_create_client_id_for_user((int) $client_user_id);
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
                $suggested = WP_PQ_DB::suggest_default_bucket_name($client_id);
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

        update_option('wp_pq_named_bucket_migration_090_applied', 1, true);
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

        update_option('wp_pq_invoice_draft_migration_120_applied', 1, true);
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

        update_option('wp_pq_work_statement_snapshot_migration_120_applied', 1, true);
    }

    public static function migrate_portal_manager_model(): void
    {
        global $wpdb;

        if (get_option('wp_pq_portal_manager_migration_160_applied')) {
            return;
        }

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $statements_table = $wpdb->prefix . 'pq_statements';

        $wpdb->query(
            "UPDATE {$statements_table}
             SET payment_status = COALESCE(NULLIF(payment_status, ''), 'unpaid')"
        );

        $wpdb->query(
            "UPDATE {$statements_table} s
             LEFT JOIN {$wpdb->prefix}pq_work_ledger_entries l ON l.invoice_draft_id = s.id
             SET s.payment_status = 'paid',
                 s.paid_at = COALESCE(s.paid_at, NOW())
             WHERE l.invoice_status = 'paid'
               AND COALESCE(s.payment_status, 'unpaid') <> 'paid'"
        );

        $wpdb->query(
            "UPDATE {$tasks_table}
             SET source_task_id = NULL
             WHERE source_task_id IS NOT NULL
               AND source_task_id <= 0"
        );

        update_option('wp_pq_portal_manager_migration_160_applied', 1, true);
    }

    public static function migrate_ledger_closure_model(): void
    {
        global $wpdb;

        if (get_option('wp_pq_ledger_closure_migration_161_applied')) {
            return;
        }

        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $tasks_table = $wpdb->prefix . 'pq_tasks';

        $wpdb->query(
            "UPDATE {$ledger_table}
             SET is_closed = 1
             WHERE is_closed IS NULL"
        );

        $wpdb->query(
            "UPDATE {$ledger_table} l
             LEFT JOIN {$tasks_table} t ON t.id = l.task_id
             SET l.is_closed = CASE
                    WHEN t.id IS NULL THEN 1
                    WHEN t.status = 'done' THEN 1
                    ELSE 0
                 END,
                 l.updated_at = COALESCE(l.updated_at, NOW())"
        );

        update_option('wp_pq_ledger_closure_migration_161_applied', 1, true);
    }

    public static function migrate_notification_event_keys(): void
    {
        global $wpdb;

        if (get_option('wp_pq_notification_event_rename_180_applied')) {
            return;
        }

        $prefs = $wpdb->prefix . 'pq_notification_prefs';
        $notifications = $wpdb->prefix . 'pq_notifications';

        $wpdb->update($prefs, ['event_key' => 'task_returned_to_work'], ['event_key' => 'task_revision_requested']);
        $wpdb->update($notifications, ['event_key' => 'task_returned_to_work'], ['event_key' => 'task_revision_requested']);

        update_option('wp_pq_notification_event_rename_180_applied', 1, true);
    }

    public static function migrate_rejected_event_key(): void
    {
        global $wpdb;

        if (get_option('wp_pq_notification_event_rename_rejected_applied')) {
            return;
        }

        $prefs = $wpdb->prefix . 'pq_notification_prefs';
        $notifications = $wpdb->prefix . 'pq_notifications';

        $wpdb->update($prefs, ['event_key' => 'task_clarification_requested'], ['event_key' => 'task_rejected']);
        $wpdb->update($notifications, ['event_key' => 'task_clarification_requested'], ['event_key' => 'task_rejected']);

        update_option('wp_pq_notification_event_rename_rejected_applied', 1, true);
    }

    public static function migrate_clear_false_archived_at(): void
    {
        global $wpdb;

        if (get_option('wp_pq_clear_false_archived_at_applied')) {
            return;
        }

        $wpdb->query(
            "UPDATE {$wpdb->prefix}pq_tasks
             SET archived_at = NULL
             WHERE status = 'done'
               AND archived_at IS NOT NULL"
        );

        update_option('wp_pq_clear_false_archived_at_applied', 1, true);
    }

    public static function migrate_drive_storage_model(): void
    {
        global $wpdb;

        if (get_option('wp_pq_drive_storage_migration_applied')) {
            return;
        }

        $clients_table = $wpdb->prefix . 'pq_clients';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $files_table = $wpdb->prefix . 'pq_task_files';

        // Add google_drive_id to clients.
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$clients_table} LIKE 'google_drive_id'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$clients_table} ADD COLUMN google_drive_id VARCHAR(191) NULL AFTER primary_contact_user_id");
        }

        // Add google_folder_id to tasks.
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$tasks_table} LIKE 'google_folder_id'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$tasks_table} ADD COLUMN google_folder_id VARCHAR(191) NULL AFTER google_event_id");
        }

        // Add Drive columns to task_files.
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$files_table} LIKE 'storage_type'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$files_table}
                ADD COLUMN storage_type VARCHAR(20) NOT NULL DEFAULT 'media' AFTER media_id,
                ADD COLUMN drive_file_id VARCHAR(191) NULL AFTER storage_type,
                ADD COLUMN drive_file_name VARCHAR(500) NULL AFTER drive_file_id,
                ADD COLUMN drive_file_url VARCHAR(1000) NULL AFTER drive_file_name,
                ADD COLUMN drive_mime_type VARCHAR(191) NULL AFTER drive_file_url,
                ADD COLUMN drive_file_size BIGINT UNSIGNED NULL AFTER drive_mime_type,
                MODIFY COLUMN media_id BIGINT UNSIGNED NULL");
        }

        update_option('wp_pq_drive_storage_migration_applied', 1, true);
    }

    public static function migrate_files_link(): void
    {
        global $wpdb;
        if (get_option('wp_pq_files_link_migration_applied')) {
            return;
        }

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$tasks_table} LIKE 'files_link'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$tasks_table} ADD COLUMN files_link VARCHAR(2000) NULL AFTER google_folder_id");
        }

        update_option('wp_pq_files_link_migration_applied', 1, true);
    }

    /**
     * Migrate the legacy site-wide Google tokens (wp_options) to user meta
     * for every admin user. Runs once.
     */
    public static function migrate_per_user_google_tokens(): void
    {
        if (get_option('wp_pq_per_user_tokens_migration_applied')) {
            return;
        }

        $legacy = get_option('wp_pq_google_tokens', []);
        if (! empty($legacy) && (! empty($legacy['access_token']) || ! empty($legacy['encrypted_refresh_token']))) {
            // Copy legacy tokens to all administrator users who don't already have tokens.
            $admins = get_users(['role' => 'administrator', 'fields' => ['ID']]);
            foreach ($admins as $admin) {
                $existing = get_user_meta((int) $admin->ID, 'wp_pq_google_tokens', true);
                if (empty($existing) || (! is_array($existing)) || empty($existing['access_token'])) {
                    update_user_meta((int) $admin->ID, 'wp_pq_google_tokens', $legacy);
                }
            }
        }

        update_option('wp_pq_per_user_tokens_migration_applied', 1, true);
    }

    /**
     * Migrate legacy site-wide Google tokens to per-user storage.
     * Finds the WordPress user matching the connected_email and moves
     * the tokens to their wp_usermeta. Idempotent — skips if already run.
     */
    public static function migrate_google_tokens_to_user(): void
    {
        if (get_option('wp_pq_google_tokens_migrated')) {
            return;
        }

        $site_tokens = get_option('wp_pq_google_tokens', []);
        if (! is_array($site_tokens) || empty($site_tokens['connected_email'])) {
            // Nothing to migrate.
            update_option('wp_pq_google_tokens_migrated', 1, true);
            return;
        }

        $user = get_user_by('email', $site_tokens['connected_email']);
        if ($user) {
            // Only migrate if user doesn't already have per-user tokens.
            $existing = get_user_meta($user->ID, 'wp_pq_google_tokens', true);
            if (empty($existing) || (! is_array($existing)) || empty($existing['access_token'])) {
                update_user_meta($user->ID, 'wp_pq_google_tokens', $site_tokens);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('PQ: Migrated site-wide Google tokens to user ' . $user->ID . ' (' . $site_tokens['connected_email'] . ')');
                }
            }
        }

        update_option('wp_pq_google_tokens_migrated', 1, true);
    }

    public static function migrate_invite_tracking_columns(): void
    {
        global $wpdb;
        if (get_option('wp_pq_invite_tracking_migration_applied')) {
            return;
        }

        $table = $wpdb->prefix . 'pq_invites';
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        if (! in_array('delivery_status', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN delivery_status VARCHAR(20) NOT NULL DEFAULT 'unknown' AFTER status");
        }
        if (! in_array('accepted_user_id', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN accepted_user_id BIGINT UNSIGNED NULL AFTER accepted_at");
        }
        if (! in_array('resent_count', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN resent_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER accepted_user_id");
        }
        if (! in_array('last_resent_at', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN last_resent_at DATETIME NULL AFTER resent_count");
        }

        update_option('wp_pq_invite_tracking_migration_applied', 1, true);
    }
}
