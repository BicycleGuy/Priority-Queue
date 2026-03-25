<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Housekeeping
{
    public static function init(): void
    {
        add_action('wp_pq_daily_housekeeping', [self::class, 'run_daily']);
        self::schedule();
    }

    public static function schedule(): void
    {
        if (! wp_next_scheduled('wp_pq_daily_housekeeping')) {
            $next_run = strtotime('tomorrow 8:00', current_time('timestamp'));
            if ($next_run <= current_time('timestamp')) {
                $next_run = current_time('timestamp') + HOUR_IN_SECONDS;
            }
            wp_schedule_event($next_run, 'daily', 'wp_pq_daily_housekeeping');
        }
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled('wp_pq_daily_housekeeping');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_pq_daily_housekeeping');
        }
    }

    public static function run_daily(): void
    {
        global $wpdb;

        $files_table = $wpdb->prefix . 'pq_task_files';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $history_table = $wpdb->prefix . 'pq_task_status_history';

        $day_300 = gmdate('Y-m-d H:i:s', strtotime('-300 days'));
        $day_301 = gmdate('Y-m-d H:i:s', strtotime('-301 days'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT f.task_id, t.submitter_id
                 FROM {$files_table} f
                 INNER JOIN {$tasks_table} t ON t.id = f.task_id
                 WHERE f.created_at <= %s AND f.created_at > %s",
                $day_300,
                $day_301
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $task_id = (int) $row['task_id'];
            $user_id = (int) $row['submitter_id'];
            $note = 'retention_reminder_300';

            $already = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM {$history_table} WHERE task_id = %d AND note = %s",
                $task_id,
                $note
            ));

            if ($already > 0) {
                continue;
            }

            $user = get_user_by('ID', $user_id);
            if ($user && self::is_event_enabled($user_id, 'retention_day_300')) {
                wp_mail(
                    $user->user_email,
                    'File retention reminder (300 days)',
                    'Task #' . $task_id . ' files are 300 days old. Move long-term copies to alternate storage before day 365.'
                );
            }

            $current_status = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$tasks_table} WHERE id = %d",
                $task_id
            ));
            $wpdb->insert($history_table, [
                'task_id' => $task_id,
                'old_status' => null,
                'new_status' => WP_PQ_Workflow::normalize_status($current_status ?: 'pending_approval'),
                'changed_by' => 0,
                'reason_code' => null,
                'note' => $note,
                'metadata' => null,
                'created_at' => current_time('mysql', true),
            ]);
        }

        $expired = $wpdb->get_results(
            "SELECT id, media_id FROM {$files_table} WHERE storage_expires_at <= UTC_TIMESTAMP()",
            ARRAY_A
        );

        foreach ($expired as $file) {
            $media_id = (int) $file['media_id'];
            if ($media_id > 0) {
                wp_delete_attachment($media_id, true);
            }
            $wpdb->delete($files_table, ['id' => (int) $file['id']]);
        }

        self::send_client_daily_digests();
    }

    public static function is_event_enabled(int $user_id, string $event_key): bool
    {
        global $wpdb;
        $prefs = $wpdb->prefix . 'pq_notification_prefs';

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT is_enabled FROM {$prefs} WHERE user_id = %d AND event_key = %s",
            $user_id,
            $event_key
        ));

        if ($value === null) {
            return true;
        }

        return (int) $value === 1;
    }

    public static function build_client_status_digest_body_for_api(int $user_id, array $groups): string
    {
        $user = get_user_by('ID', $user_id);
        if (! $user) {
            return '';
        }

        return self::build_digest_body($user, $groups);
    }

    private static function send_client_daily_digests(): void
    {
        global $wpdb;

        $members_table = $wpdb->prefix . 'pq_client_members';
        $task_table = $wpdb->prefix . 'pq_tasks';
        $history_table = $wpdb->prefix . 'pq_task_status_history';
        $prefs_table = $wpdb->prefix . 'pq_notification_prefs';

        $members = $wpdb->get_results(
            "SELECT DISTINCT user_id FROM {$members_table} ORDER BY user_id ASC",
            ARRAY_A
        );

        // Batch-load digest prefs for all client members in one query.
        $member_user_ids = array_map(static fn(array $r): int => (int) ($r['user_id'] ?? 0), (array) $members);
        $member_user_ids = array_values(array_filter($member_user_ids));
        $digest_disabled_ids = [];
        if (! empty($member_user_ids)) {
            $ids_in = implode(',', array_map('intval', $member_user_ids));
            $disabled_rows = $wpdb->get_col(
                "SELECT user_id FROM {$prefs_table} WHERE event_key = 'client_daily_digest' AND is_enabled = 0 AND user_id IN ({$ids_in})"
            );
            $digest_disabled_ids = array_flip(array_map('intval', (array) $disabled_rows));
        }

        $now = current_time('mysql', true);

        foreach ((array) $members as $member_row) {
            $user_id = (int) ($member_row['user_id'] ?? 0);
            if ($user_id <= 0 || isset($digest_disabled_ids[$user_id])) {
                continue;
            }

            $user = get_user_by('ID', $user_id);
            if (! $user || ! is_email($user->user_email)) {
                continue;
            }

            $last_sent_at = (string) get_user_meta($user_id, 'wp_pq_client_digest_last_sent_at', true);
            if ($last_sent_at === '') {
                $last_sent_at = gmdate('Y-m-d H:i:s', strtotime('-1 day'));
            }

            // Scope to tasks belonging to this user's client accounts.
            $client_ids = array_map(
                static fn(array $m): int => (int) ($m['client_id'] ?? 0),
                WP_PQ_DB::get_user_client_memberships($user_id)
            );
            $client_ids = array_values(array_filter($client_ids));
            if (empty($client_ids)) {
                update_user_meta($user_id, 'wp_pq_client_digest_last_sent_at', $now);
                continue;
            }

            $client_ids_in = implode(',', array_map('intval', $client_ids));
            $task_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT h.task_id
                 FROM {$history_table} h
                 INNER JOIN {$task_table} t ON t.id = h.task_id
                 WHERE h.created_at > %s
                   AND h.created_at <= %s
                   AND t.client_id IN ({$client_ids_in})",
                $last_sent_at,
                $now
            )));

            if (empty($task_ids)) {
                update_user_meta($user_id, 'wp_pq_client_digest_last_sent_at', $now);
                continue;
            }

            $ids_in = implode(',', array_map('intval', $task_ids));
            $tasks = $wpdb->get_results("SELECT * FROM {$task_table} WHERE id IN ({$ids_in})", ARRAY_A);
            $visible_tasks = array_values(array_filter((array) $tasks, static function (array $task) use ($user_id): bool {
                return WP_PQ_API::can_access_task($task, $user_id);
            }));

            if (empty($visible_tasks)) {
                update_user_meta($user_id, 'wp_pq_client_digest_last_sent_at', $now);
                continue;
            }

            // Batch-preload submitter users for this digest.
            $submitter_ids = array_values(array_unique(array_filter(array_map(
                static fn(array $t): int => (int) ($t['submitter_id'] ?? 0),
                $visible_tasks
            ))));
            WP_PQ_API::preload_users($submitter_ids);

            $groups = [
                'Awaiting you' => [],
                'Delivered' => [],
                'Needs clarification' => [],
                'Other changes' => [],
            ];

            foreach ($visible_tasks as $task) {
                $task = self::normalize_task_for_digest($task);
                $status = (string) ($task['status'] ?? '');
                $is_action_owner = (int) ($task['action_owner_id'] ?? 0) === $user_id;

                if ($status === 'delivered') {
                    $groups['Delivered'][] = $task;
                } elseif ($status === 'needs_clarification') {
                    $groups['Needs clarification'][] = $task;
                } elseif ($is_action_owner && in_array($status, ['pending_approval', 'approved', 'in_progress', 'needs_review'], true)) {
                    $groups['Awaiting you'][] = $task;
                } else {
                    $groups['Other changes'][] = $task;
                }
            }

            $body = self::build_digest_body($user, $groups);
            if ($body === '') {
                update_user_meta($user_id, 'wp_pq_client_digest_last_sent_at', $now);
                continue;
            }

            wp_mail(
                $user->user_email,
                'Priority Portal daily digest',
                $body
            );

            update_user_meta($user_id, 'wp_pq_client_digest_last_sent_at', $now);
        }
    }

    private static function normalize_task_for_digest(array $task): array
    {
        $submitter = isset($task['submitter_id']) ? WP_PQ_API::get_cached_user((int) $task['submitter_id']) : null;
        $task['submitter_name'] = $submitter ? (string) $submitter->display_name : '';
        return $task;
    }

    private static function build_digest_body(WP_User $user, array $groups): string
    {
        $lines = [];
        $lines[] = 'Here is your Priority Portal daily digest.';
        $lines[] = '';

        foreach ($groups as $label => $tasks) {
            if (empty($tasks)) {
                continue;
            }

            $lines[] = $label;
            $lines[] = str_repeat('-', strlen($label));
            foreach ($tasks as $task) {
                $task_title = (string) ($task['title'] ?? 'Task');
                $job = (string) ($task['bucket_name'] ?? '');
                $status = (string) ($task['status'] ?? '');
                $deadline = (string) ($task['requested_deadline'] ?: $task['due_at'] ?: '');
                $line = '* ' . $task_title;
                if ($job !== '') {
                    $line .= ' [' . $job . ']';
                }
                if ($status !== '') {
                    $line .= ' — ' . ucwords(str_replace('_', ' ', $status));
                }
                if ($deadline !== '') {
                    $line .= ' — deadline ' . wp_date('M j, Y g:i a', strtotime($deadline . ' UTC'));
                }
                $lines[] = $line;
            }
            $lines[] = '';
        }

        return count($lines) > 2 ? implode("\n", $lines) : '';
    }
}
