<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Housekeeping
{
    public static function init(): void
    {
        add_action('wp_pq_daily_housekeeping', [self::class, 'run_daily']);
    }

    public static function schedule(): void
    {
        if (! wp_next_scheduled('wp_pq_daily_housekeeping')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wp_pq_daily_housekeeping');
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

            $wpdb->insert($history_table, [
                'task_id' => $task_id,
                'old_status' => null,
                'new_status' => 'archived',
                'changed_by' => 0,
                'note' => $note,
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
}
