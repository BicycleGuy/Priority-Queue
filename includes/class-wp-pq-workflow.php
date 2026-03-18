<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Workflow
{
    public static function allowed_statuses(): array
    {
        return [
            'pending_approval',
            'pending_review',
            'not_approved',
            'approved',
            'in_progress',
            'delivered',
            'revision_requested',
            'archived',
        ];
    }

    public static function can_transition(string $from, string $to, int $user_id): bool
    {
        $is_manager = current_user_can(WP_PQ_Roles::CAP_APPROVE);
        $is_worker = current_user_can(WP_PQ_Roles::CAP_WORK);

        $matrix = [
            'pending_approval' => ['approved', 'not_approved'],
            'not_approved' => ['approved'],
            'approved' => ['not_approved', 'in_progress', 'delivered', 'revision_requested', 'archived'],
            'in_progress' => ['pending_review', 'delivered', 'revision_requested', 'archived'],
            'pending_review' => ['delivered', 'revision_requested', 'archived'],
            'delivered' => ['revision_requested', 'archived'],
            'revision_requested' => ['in_progress', 'archived'],
            'archived' => [],
        ];

        if (! isset($matrix[$from]) || ! in_array($to, $matrix[$from], true)) {
            return false;
        }

        if ($from === 'pending_approval' || $from === 'not_approved' || $to === 'approved' || $to === 'not_approved') {
            return $is_manager;
        }

        if ($to === 'in_progress' || $to === 'pending_review' || $to === 'delivered') {
            return $is_manager || $is_worker;
        }

        return $is_manager || $is_worker || $user_id > 0;
    }

    public static function notification_events(): array
    {
        return [
            'task_created',
            'task_assigned',
            'task_approved',
            'task_rejected',
            'task_mentioned',
            'task_reprioritized',
            'task_schedule_changed',
            'task_revision_requested',
            'task_delivered',
            'statement_batched',
            'client_status_updates',
            'client_daily_digest',
            'retention_day_300',
        ];
    }
}
