<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Workflow
{
    /**
     * Legacy aliases — used ONLY by data migrations.
     * No runtime code should depend on these.
     */
    public static function status_aliases(): array
    {
        return [
            'draft' => 'pending_approval',
            'not_approved' => 'needs_clarification',
            'pending_review' => 'needs_review',
            'revision_requested' => 'in_progress',
            'completed' => 'done',
        ];
    }

    public static function allowed_statuses(): array
    {
        return [
            'pending_approval',
            'needs_clarification',
            'approved',
            'in_progress',
            'needs_review',
            'delivered',
            'done',
            'archived',
        ];
    }

    public static function board_statuses(): array
    {
        return [
            'pending_approval',
            'needs_clarification',
            'approved',
            'in_progress',
            'needs_review',
            'delivered',
        ];
    }

    public static function billing_source_statuses(): array
    {
        return ['delivered', 'done', 'archived'];
    }

    public static function is_known_status(string $status): bool
    {
        $normalized = self::normalize_status($status);
        return in_array($normalized, self::allowed_statuses(), true);
    }

    public static function normalize_status(string $status): string
    {
        $status = sanitize_key($status);
        if ($status === '') {
            return '';
        }

        return self::status_aliases()[$status] ?? $status;
    }

    public static function label(string $status): string
    {
        return match (self::normalize_status($status)) {
            'pending_approval' => 'Pending Approval',
            'needs_clarification' => 'Needs Clarification',
            'approved' => 'Approved',
            'in_progress' => 'In Progress',
            'needs_review' => 'Needs Review',
            'delivered' => 'Delivered',
            'done' => 'Done',
            'archived' => 'Archived',
            default => ucwords(str_replace('_', ' ', trim($status))),
        };
    }

    public static function can_transition(string $from, string $to, int $user_id): bool
    {
        $from = self::normalize_status($from);
        $to = self::normalize_status($to);
        $is_manager = current_user_can(WP_PQ_Roles::CAP_APPROVE);
        $is_worker = current_user_can(WP_PQ_Roles::CAP_WORK);

        $matrix = [
            'pending_approval' => ['approved', 'needs_clarification'],
            'needs_clarification' => ['approved', 'in_progress'],
            'approved' => ['in_progress', 'needs_clarification'],
            'in_progress' => ['needs_clarification', 'needs_review', 'delivered'],
            'needs_review' => ['in_progress', 'delivered'],
            'delivered' => ['in_progress', 'needs_clarification', 'needs_review', 'done', 'archived'],
            'done' => ['archived', 'in_progress', 'needs_clarification', 'needs_review'],
            'archived' => [],
        ];

        if (! isset($matrix[$from]) || ! in_array($to, $matrix[$from], true)) {
            return false;
        }

        if ($to === 'approved' || ($from === 'pending_approval' && $to === 'needs_clarification')) {
            return $is_manager;
        }

        if ($to === 'archived') {
            return $is_manager;
        }

        if (in_array($to, ['in_progress', 'needs_clarification', 'needs_review', 'delivered', 'done'], true)) {
            return $is_manager || $is_worker;
        }

        return false;
    }

    public static function notification_events(): array
    {
        return array_keys(self::notification_event_defaults());
    }

    /**
     * Default on/off for each notification event.
     *
     * Only events marked TRUE here will send emails when a user
     * has never touched their notification preferences.
     */
    public static function notification_event_defaults(): array
    {
        return [
            'task_created'                 => false,
            'task_assigned'                => true,
            'task_approved'                => false,
            'task_clarification_requested' => false,
            'task_mentioned'               => true,
            'task_reprioritized'           => false,
            'task_schedule_changed'        => false,
            'task_returned_to_work'        => false,
            'task_delivered'               => false,
            'task_archived'                => false,
            'statement_batched'            => false,
            'client_status_updates'        => false,
            'client_daily_digest'          => false,
            'retention_day_300'            => true,
        ];
    }
}
