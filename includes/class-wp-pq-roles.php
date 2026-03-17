<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Roles
{
    public const CAP_VIEW_ALL = 'pq_view_all_tasks';
    public const CAP_REORDER_ALL = 'pq_reorder_all_tasks';
    public const CAP_APPROVE = 'pq_approve_requests';
    public const CAP_ASSIGN = 'pq_assign_owners';
    public const CAP_WORK = 'pq_work_tasks';

    public static function register_roles_and_caps(): void
    {
        add_role('pq_client', 'PQ Client', [
            'read' => true,
            'upload_files' => true,
        ]);

        add_role('pq_worker', 'PQ Worker', [
            'read' => true,
            'upload_files' => true,
            self::CAP_WORK => true,
        ]);

        add_role('pq_manager', 'PQ Manager', [
            'read' => true,
            'upload_files' => true,
            self::CAP_WORK => true,
            self::CAP_VIEW_ALL => true,
            self::CAP_REORDER_ALL => true,
            self::CAP_APPROVE => true,
            self::CAP_ASSIGN => true,
        ]);

        $admin = get_role('administrator');
        if ($admin) {
            foreach ([self::CAP_VIEW_ALL, self::CAP_REORDER_ALL, self::CAP_APPROVE, self::CAP_ASSIGN, self::CAP_WORK] as $cap) {
                $admin->add_cap($cap);
            }
        }
    }
}
