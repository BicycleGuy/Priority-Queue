<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_API
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('pq/v1', '/tasks', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_tasks'],
                'permission_callback' => [self::class, 'can_read_tasks'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_task'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);

        register_rest_route('pq/v1', '/tasks/reorder', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'reorder_tasks'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/move', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'move_task'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/approve-batch', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'approve_batch'],
            'permission_callback' => static fn() => current_user_can(WP_PQ_Roles::CAP_APPROVE),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'update_status'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/done', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'mark_task_done'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'delete_task'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/assignment', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'update_assignment'],
            'permission_callback' => static fn() => current_user_can(WP_PQ_Roles::CAP_ASSIGN),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/priority', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'update_priority'],
            'permission_callback' => static fn() => current_user_can(WP_PQ_Roles::CAP_APPROVE),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/schedule', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'update_schedule'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/messages', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_messages'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_message'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/notes', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_notes'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_note'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/participants', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_task_participants'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/files', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_files'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'attach_file'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/meetings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_meetings'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_meeting'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);

        register_rest_route('pq/v1', '/calendar/webhook', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'calendar_webhook'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('pq/v1', '/calendar/events', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_calendar_events'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/google/oauth/callback', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'google_oauth_callback'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'google_oauth_callback'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('pq/v1', '/google/oauth/url', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'google_oauth_url'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('pq/v1', '/google/oauth/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'google_oauth_status'],
            'permission_callback' => static fn() => current_user_can(WP_PQ_Roles::CAP_APPROVE),
        ]);

        register_rest_route('pq/v1', '/notification-prefs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_notification_prefs'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'update_notification_prefs'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);

        register_rest_route('pq/v1', '/notifications', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_notifications'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/notifications/mark-read', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'mark_notifications_read'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/workers', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_workers'],
            'permission_callback' => static fn() => current_user_can(WP_PQ_Roles::CAP_ASSIGN),
        ]);

        register_rest_route('pq/v1', '/statements/batch', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'batch_statement'],
            'permission_callback' => static fn() => current_user_can(WP_PQ_Roles::CAP_APPROVE),
        ]);
    }

    public static function can_read_tasks(): bool
    {
        return is_user_logged_in();
    }

    public static function get_tasks(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $rows = self::get_visible_tasks_for_request($request, $user_id);

        $rows = self::sort_task_rows($rows);
        $rows = self::enrich_task_rows($rows);

        return new WP_REST_Response([
            'tasks' => $rows,
            'filters' => self::build_task_filters_payload($user_id),
        ], 200);
    }

    public static function create_task(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_tasks';

        $user_id = get_current_user_id();
        $title = sanitize_text_field((string) $request->get_param('title'));

        if ($title === '') {
            return new WP_REST_Response(['message' => 'Task title is required.'], 422);
        }

        $allowed_assign = current_user_can(WP_PQ_Roles::CAP_ASSIGN);
        $requested_submitter_id = (int) $request->get_param('submitter_id');
        $submitter_id = $user_id;
        if (current_user_can(WP_PQ_Roles::CAP_APPROVE) && $requested_submitter_id > 0 && get_user_by('ID', $requested_submitter_id)) {
            $submitter_id = $requested_submitter_id;
        }
        $owner_ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get_param('owner_ids')))));
        $client_id = self::resolve_client_id($request, $submitter_id);
        $client_user_id = self::resolve_client_primary_contact_id($client_id, $user_id);
        $billing_bucket_id = self::resolve_billing_bucket_id($request, $client_id);
        $new_bucket_name = sanitize_text_field((string) $request->get_param('new_bucket_name'));
        if ($new_bucket_name !== '' && current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            $billing_bucket_id = self::create_bucket_for_client($client_id, $new_bucket_name);
        }
        $action_owner_id = self::resolve_action_owner_id($request, $allowed_assign ? $owner_ids : [], $client_id, $client_user_id);
        $billable_default = self::default_billable_for_assignment($client_id, $action_owner_id, true);
        $is_billable = self::request_truthy($request->get_param('is_billable'), $billable_default) ? 1 : 0;

        $max_position = (int) $wpdb->get_var("SELECT COALESCE(MAX(queue_position), 0) FROM {$table}");

        $inserted = $wpdb->insert($table, [
            'title' => $title,
            'description' => sanitize_textarea_field((string) $request->get_param('description')),
            'status' => 'pending_approval',
            'priority' => self::sanitize_priority((string) $request->get_param('priority')),
            'queue_position' => $max_position + 1,
            'due_at' => self::sanitize_datetime($request->get_param('due_at')),
            'requested_deadline' => self::sanitize_datetime($request->get_param('requested_deadline')),
            'submitter_id' => $submitter_id,
            'client_id' => $client_id > 0 ? $client_id : null,
            'client_user_id' => $client_user_id > 0 ? $client_user_id : $submitter_id,
            'action_owner_id' => $action_owner_id > 0 ? $action_owner_id : null,
            'is_billable' => $is_billable,
            'billing_bucket_id' => $billing_bucket_id > 0 ? $billing_bucket_id : null,
            'owner_ids' => wp_json_encode($allowed_assign ? $owner_ids : []),
            'needs_meeting' => $request->get_param('needs_meeting') ? 1 : 0,
            'billing_status' => $is_billable ? 'unbilled' : 'not_billable',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);

        if (! $inserted) {
            return new WP_REST_Response(['message' => 'Failed to create task.'], 500);
        }

        $task_id = (int) $wpdb->insert_id;
        if ($client_id > 0) {
            if ($submitter_id > 0 && self::user_has_role($submitter_id, 'pq_client')) {
                WP_PQ_DB::ensure_client_member($client_id, $submitter_id, 'client_admin');
            }
            if ($billing_bucket_id > 0 && $submitter_id > 0 && self::user_has_role($submitter_id, 'pq_client')) {
                WP_PQ_DB::ensure_job_member($billing_bucket_id, $submitter_id);
            }
        }
        self::sync_task_calendar_event((array) self::get_task_row($task_id));
        self::emit_event($task_id, 'task_created', 'Task created', 'A new task was submitted and is pending approval.');
        self::emit_assignment_event($task_id, 0, $action_owner_id);

        return new WP_REST_Response([
            'task_id' => $task_id,
            'task' => self::get_enriched_task($task_id),
        ], 201);
    }

    public static function reorder_tasks(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_tasks';
        $user_id = get_current_user_id();
        $items = (array) $request->get_param('items');
        $last_task_id = 0;

        foreach ($items as $index => $item) {
            $task_id = (int) ($item['id'] ?? 0);
            if ($task_id <= 0) {
                continue;
            }

            if (! current_user_can(WP_PQ_Roles::CAP_REORDER_ALL)) {
                $owner = (int) $wpdb->get_var($wpdb->prepare("SELECT submitter_id FROM {$table} WHERE id = %d", $task_id));
                if ($owner !== $user_id) {
                    continue;
                }
            }

            $wpdb->update($table, [
                'queue_position' => $index + 1,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $task_id]);
            $last_task_id = $task_id;
        }

        return new WP_REST_Response([
            'ok' => true,
            'task' => $last_task_id > 0 ? self::get_enriched_task($last_task_id) : null,
        ], 200);
    }

    public static function update_assignment(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);
        if (! $task) {
            return new WP_REST_Response(['message' => 'Task not found.'], 404);
        }

        $action_owner_id = max(0, (int) $request->get_param('action_owner_id'));
        if ($action_owner_id > 0 && ! get_user_by('ID', $action_owner_id)) {
            return new WP_REST_Response(['message' => 'Assigned user not found.'], 422);
        }
        $previous_action_owner_id = (int) ($task['action_owner_id'] ?? 0);

        $owner_ids = array_values(array_unique(array_filter(array_map('intval', (array) json_decode((string) ($task['owner_ids'] ?? ''), true)))));
        if ($action_owner_id > 0 && ! in_array($action_owner_id, $owner_ids, true)) {
            array_unshift($owner_ids, $action_owner_id);
        }

        $billing_sync = self::billing_sync_for_assignment($task, $action_owner_id);

        $wpdb->update($wpdb->prefix . 'pq_tasks', array_merge([
            'action_owner_id' => $action_owner_id > 0 ? $action_owner_id : null,
            'owner_ids' => wp_json_encode($owner_ids),
            'updated_at' => current_time('mysql', true),
        ], $billing_sync), ['id' => $task_id]);

        self::insert_history_note(
            $wpdb->prefix . 'pq_task_status_history',
            $task_id,
            (string) $task['status'],
            get_current_user_id(),
            'action_owner:' . ($action_owner_id > 0 ? $action_owner_id : 'none')
        );

        self::emit_assignment_event($task_id, $previous_action_owner_id, $action_owner_id);

        return new WP_REST_Response([
            'ok' => true,
            'task' => self::get_enriched_task($task_id),
        ], 200);
    }

    public static function update_priority(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);
        if (! $task) {
            return new WP_REST_Response(['message' => 'Task not found.'], 404);
        }

        $user_id = get_current_user_id();
        if (! self::can_access_task($task, $user_id)) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $current_priority = (string) ($task['priority'] ?? 'normal');
        $new_priority = self::sanitize_priority((string) $request->get_param('priority'));
        if ($new_priority === $current_priority) {
            return new WP_REST_Response([
                'ok' => true,
                'task' => self::get_enriched_task($task_id),
            ], 200);
        }

        $wpdb->update($wpdb->prefix . 'pq_tasks', [
            'priority' => $new_priority,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $task_id]);

        self::insert_history_note(
            $wpdb->prefix . 'pq_task_status_history',
            $task_id,
            (string) $task['status'],
            $user_id,
            'priority:' . $current_priority . '->' . $new_priority
        );

        self::emit_event($task_id, 'task_reprioritized', 'Task reprioritized', 'The task priority was updated.');

        return new WP_REST_Response([
            'ok' => true,
            'task' => self::get_enriched_task($task_id),
        ], 200);
    }

    public static function move_task(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $history_table = $wpdb->prefix . 'pq_task_status_history';

        $user_id = get_current_user_id();
        $task_id = (int) $request->get_param('task_id');
        $target_task_id = (int) $request->get_param('target_task_id');
        $position = sanitize_key((string) $request->get_param('position'));
        $target_status = sanitize_key((string) $request->get_param('target_status'));
        $action = sanitize_key((string) $request->get_param('action'));
        $priority_direction = self::sanitize_priority_direction((string) $request->get_param('priority_direction'));
        $raise_priority = rest_sanitize_boolean($request->get_param('raise_priority'));
        $swap_due_dates = rest_sanitize_boolean($request->get_param('swap_due_dates'));

        if ($action !== '') {
            $priority_direction = in_array($action, ['raise_priority', 'raise_and_swap'], true) ? 'up' : 'keep';
            $swap_due_dates = in_array($action, ['swap_due_dates', 'raise_and_swap'], true);
        } elseif ($priority_direction === 'keep' && $raise_priority) {
            $priority_direction = 'up';
        }

        if ($task_id <= 0 || ($target_task_id > 0 && $task_id === $target_task_id)) {
            return new WP_REST_Response(['message' => 'A valid task move target is required.'], 422);
        }

        if (! in_array($position, ['before', 'after'], true)) {
            return new WP_REST_Response(['message' => 'Invalid move position.'], 422);
        }

        $task = self::get_task_row($task_id);
        $target_task = $target_task_id > 0 ? self::get_task_row($target_task_id) : null;

        if (! $task) {
            return new WP_REST_Response(['message' => 'Task not found.'], 404);
        }

        if ($target_task_id > 0 && ! $target_task) {
            return new WP_REST_Response(['message' => 'Move target not found.'], 404);
        }

        if (! current_user_can(WP_PQ_Roles::CAP_REORDER_ALL)) {
            if ((int) $task['submitter_id'] !== $user_id || ($target_task && (int) $target_task['submitter_id'] !== $user_id)) {
                return new WP_REST_Response(['message' => 'Forbidden.'], 403);
            }
        }

        $current_status = WP_PQ_Workflow::normalize_status((string) $task['status']);
        $resolved_target_status = $target_status !== ''
            ? WP_PQ_Workflow::normalize_status($target_status)
            : ($target_task ? WP_PQ_Workflow::normalize_status((string) $target_task['status']) : $current_status);

        if (! in_array($resolved_target_status, WP_PQ_Workflow::allowed_statuses(), true)) {
            return new WP_REST_Response(['message' => 'Invalid move target status.'], 422);
        }

        if ($resolved_target_status === 'done') {
            return new WP_REST_Response(['message' => 'Use Mark Done so completion details can be captured before closing the task.'], 422);
        }

        if ($resolved_target_status === 'archived') {
            return new WP_REST_Response(['message' => 'Tasks can only be archived after being marked done.'], 422);
        }

        if (self::is_billing_locked_reopen($task, $current_status, $resolved_target_status)) {
            return new WP_REST_Response(['message' => 'This delivered task is still tied to billing. Remove it from the invoice draft first.'], 422);
        }

        if ($resolved_target_status !== $current_status && ! WP_PQ_Workflow::can_transition($current_status, $resolved_target_status, $user_id)) {
            return new WP_REST_Response(['message' => 'That status move is not allowed.'], 422);
        }

        $visible_rows = current_user_can(WP_PQ_Roles::CAP_VIEW_ALL)
            ? $wpdb->get_results("SELECT * FROM {$tasks_table} WHERE status NOT IN ('done','archived') ORDER BY queue_position ASC, id DESC", ARRAY_A)
            : $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$tasks_table} WHERE submitter_id = %d AND status NOT IN ('done','archived') ORDER BY queue_position ASC, id DESC", $user_id),
                ARRAY_A
            );
        $visible_rows = self::sort_task_rows($visible_rows);

        $ordered_ids = array_map(static fn($row) => (int) $row['id'], $visible_rows);
        if (! in_array($task_id, $ordered_ids, true) || ($target_task_id > 0 && ! in_array($target_task_id, $ordered_ids, true))) {
            return new WP_REST_Response(['message' => 'Task move is outside your visible queue.'], 403);
        }

        $ordered_ids = array_values(array_filter($ordered_ids, static fn($id) => $id !== $task_id));
        if ($target_task_id > 0) {
            $target_index = array_search($target_task_id, $ordered_ids, true);
            if ($target_index === false) {
                return new WP_REST_Response(['message' => 'Task move target could not be resolved.'], 422);
            }

            $insert_at = $position === 'before' ? $target_index : $target_index + 1;
        } else {
            $insert_at = count($ordered_ids);
        }
        array_splice($ordered_ids, $insert_at, 0, [$task_id]);

        foreach ($ordered_ids as $index => $ordered_id) {
            $wpdb->update($tasks_table, [
                'queue_position' => $index + 1,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $ordered_id]);
        }

        $priority_changed = false;
        $schedule_changed = false;
        $status_changed = false;
        $meeting_requested = (bool) $request->get_param('needs_meeting');
        $change_note = trim((string) $request->get_param('note'));
        $message_body = trim((string) $request->get_param('message_body'));
        $send_update_email = self::request_truthy($request->get_param('send_update_email'));
        $notes = ['board_move'];

        if ($resolved_target_status !== $current_status) {
            $reason_code = self::transition_reason_code($current_status, $resolved_target_status);
            $status_update = array_merge([
                'status' => $resolved_target_status,
                'updated_at' => current_time('mysql', true),
            ], self::status_timestamp_updates($resolved_target_status));
            if ($reason_code === 'revisions_requested') {
                $status_update['revision_count'] = max(0, (int) ($task['revision_count'] ?? 0)) + 1;
            }
            if ($meeting_requested) {
                $status_update['needs_meeting'] = 1;
            }
            $wpdb->update($tasks_table, $status_update, ['id' => $task_id]);
            $status_changed = true;
            $notes[] = 'status:' . $current_status . '->' . $resolved_target_status;
            $task['status'] = $resolved_target_status;
            if ($meeting_requested) {
                $task['needs_meeting'] = 1;
                $notes[] = 'meeting_requested';
            }
            foreach (self::status_timestamp_updates($resolved_target_status) as $field => $value) {
                $task[$field] = $value;
            }
        }

        if ($priority_direction !== 'keep') {
            $new_priority = self::shift_priority((string) $task['priority'], $priority_direction);
            if ($new_priority !== (string) $task['priority']) {
                $wpdb->update($tasks_table, [
                    'priority' => $new_priority,
                    'updated_at' => current_time('mysql', true),
                ], ['id' => $task_id]);
                $notes[] = 'priority:' . $task['priority'] . '->' . $new_priority;
                $priority_changed = true;
                $task['priority'] = $new_priority;
            }
        }

        if ($swap_due_dates && $target_task) {
            $task_dates = [
                'requested_deadline' => $task['requested_deadline'],
                'due_at' => $task['due_at'],
            ];
            $target_dates = [
                'requested_deadline' => $target_task['requested_deadline'],
                'due_at' => $target_task['due_at'],
            ];

            if ($task_dates !== $target_dates) {
                $wpdb->update($tasks_table, [
                    'requested_deadline' => $target_dates['requested_deadline'],
                    'due_at' => $target_dates['due_at'],
                    'updated_at' => current_time('mysql', true),
                ], ['id' => $task_id]);

                $wpdb->update($tasks_table, [
                    'requested_deadline' => $task_dates['requested_deadline'],
                    'due_at' => $task_dates['due_at'],
                    'updated_at' => current_time('mysql', true),
                ], ['id' => $target_task_id]);

                $notes[] = 'date_swap:' . $target_task_id;
                $schedule_changed = true;
            }
        }

        if ($status_changed) {
            if ($change_note !== '') {
                $notes[] = 'note:' . $change_note;
            }
            self::insert_status_history(
                $history_table,
                $task_id,
                $current_status,
                $resolved_target_status,
                $user_id,
                implode(';', $notes),
                $reason_code
            );
            self::emit_status_event($task_id, $current_status, $resolved_target_status);
        } else {
            if ($change_note !== '') {
                $notes[] = 'note:' . $change_note;
            }
            self::insert_history_note($history_table, $task_id, (string) $task['status'], $user_id, implode(';', $notes));
        }

        if ($message_body !== '') {
            self::store_task_message($task_id, $user_id, $message_body, $task);
        }

        if ($schedule_changed && $target_task_id > 0) {
            self::insert_history_note($history_table, $target_task_id, (string) $target_task['status'], $user_id, 'date_swap_with:' . $task_id);
        }

        if ($priority_changed) {
            self::emit_event($task_id, 'task_reprioritized', 'Task reprioritized', 'A task was moved and its priority changed.');
        }

        if ($schedule_changed) {
            self::emit_event($task_id, 'task_schedule_changed', 'Task schedule changed', 'A task move changed one or more due dates.');
            if ($target_task_id > 0) {
                self::emit_event($target_task_id, 'task_schedule_changed', 'Task schedule changed', 'A task move changed one or more due dates.');
            }
        }

        self::sync_task_calendar_event((array) self::get_task_row($task_id));
        if ($schedule_changed && $target_task_id > 0) {
            self::sync_task_calendar_event((array) self::get_task_row($target_task_id));
        }

        if ($status_changed && $send_update_email) {
            self::send_client_status_update($task_id, $resolved_target_status);
        }

        return new WP_REST_Response([
            'ok' => true,
            'task' => self::get_enriched_task($task_id),
            'target_task' => $target_task_id > 0 ? self::get_enriched_task($target_task_id) : null,
        ], 200);
    }

    public static function update_schedule(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $tasks = $wpdb->prefix . 'pq_tasks';
        $history = $wpdb->prefix . 'pq_task_status_history';

        $task_id = (int) $request->get_param('id');
        $when = self::sanitize_datetime($request->get_param('when'));
        $task = self::get_task_row($task_id);

        if (! $task) {
            return new WP_REST_Response(['message' => 'Task not found.'], 404);
        }

        if (! $when) {
            return new WP_REST_Response(['message' => 'A valid date is required.'], 422);
        }

        if (! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        [$requested_deadline, $due_at] = self::shift_task_schedule($task, $when);

        $wpdb->update($tasks, [
            'requested_deadline' => $requested_deadline,
            'due_at' => $due_at,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $task_id]);

        self::insert_history_note($history, $task_id, (string) $task['status'], get_current_user_id(), 'calendar_drag:' . $when);
        self::emit_event($task_id, 'task_schedule_changed', 'Task schedule changed', 'A task move changed one or more due dates.');
        self::sync_task_calendar_event((array) self::get_task_row($task_id));

        return new WP_REST_Response([
            'ok' => true,
            'task' => self::get_enriched_task($task_id),
        ], 200);
    }

    public static function update_status(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $tasks = $wpdb->prefix . 'pq_tasks';
        $history = $wpdb->prefix . 'pq_task_status_history';

        $task_id = (int) $request->get_param('id');
        $new_status = WP_PQ_Workflow::normalize_status((string) $request->get_param('status'));
        $task = self::get_task_row($task_id);

        if (! $task) {
            return new WP_REST_Response(['message' => 'Task not found.'], 404);
        }

        if (! in_array($new_status, WP_PQ_Workflow::allowed_statuses(), true)) {
            return new WP_REST_Response(['message' => 'Invalid status.'], 422);
        }

        if ($new_status === 'done') {
            return new WP_REST_Response(['message' => 'Use Mark Done so completion details can be captured before closing the task.'], 422);
        }

        $user_id = get_current_user_id();
        if (! self::can_access_task($task, $user_id)) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $current_status = WP_PQ_Workflow::normalize_status((string) $task['status']);

        if (self::is_billing_locked_reopen($task, $current_status, $new_status)) {
            return new WP_REST_Response(['message' => 'This delivered task is still tied to billing. Remove it from the invoice draft first.'], 422);
        }

        if (! WP_PQ_Workflow::can_transition($current_status, $new_status, $user_id)) {
            return new WP_REST_Response(['message' => 'Status transition not allowed.'], 422);
        }

        $reason_code = self::transition_reason_code($current_status, $new_status);
        $update_data = array_merge([
            'status' => $new_status,
            'updated_at' => current_time('mysql', true),
        ], self::status_timestamp_updates($new_status));
        if ($reason_code === 'revisions_requested') {
            $update_data['revision_count'] = max(0, (int) ($task['revision_count'] ?? 0)) + 1;
        }

        if ((bool) $request->get_param('needs_meeting')) {
            $update_data['needs_meeting'] = 1;
        }

        $wpdb->update($tasks, $update_data, ['id' => $task_id]);

        self::insert_status_history(
            $history,
            $task_id,
            $current_status,
            $new_status,
            $user_id,
            (string) $request->get_param('note'),
            $reason_code
        );

        $message_body = trim((string) $request->get_param('message_body'));
        $send_update_email = self::request_truthy($request->get_param('send_update_email'));
        if ($message_body !== '') {
            self::store_task_message($task_id, $user_id, $message_body, $task);
        }

        self::emit_status_event($task_id, $current_status, $new_status);
        self::sync_task_calendar_event((array) self::get_task_row($task_id));
        if ($send_update_email) {
            self::send_client_status_update($task_id, $new_status);
        }

        return new WP_REST_Response([
            'ok' => true,
            'task' => self::get_enriched_task($task_id),
        ], 200);
    }

    public static function mark_task_done(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $history_table = $wpdb->prefix . 'pq_task_status_history';
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task) {
            return new WP_REST_Response(['message' => 'Task not found.'], 404);
        }

        $user_id = get_current_user_id();
        if (! self::can_access_task($task, $user_id)) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $current_status = WP_PQ_Workflow::normalize_status((string) ($task['status'] ?? ''));
        if ($current_status === 'done') {
            return new WP_REST_Response(['message' => 'This task is already marked done.'], 409);
        }

        if (! WP_PQ_Workflow::can_transition($current_status, 'done', $user_id)) {
            return new WP_REST_Response(['message' => 'Only delivered tasks can be marked done.'], 422);
        }

        $payload = self::normalize_completion_payload((array) $request->get_json_params());
        $validated = self::validate_completion_payload($task, $payload);
        if (is_wp_error($validated)) {
            return new WP_REST_Response(['message' => $validated->get_error_message()], (int) ($validated->get_error_data()['status'] ?? 422));
        }

        $now = current_time('mysql', true);
        $delivered_at = (string) ($task['delivered_at'] ?? '');
        if ($delivered_at === '') {
            $delivered_at = $now;
        }

        $update_data = [
            'status' => 'done',
            'is_billable' => ! empty($validated['billable']) ? 1 : 0,
            'billing_status' => self::completion_billing_status($task, $validated),
            'billing_mode' => $validated['billing_mode'] !== '' ? $validated['billing_mode'] : null,
            'billing_category' => $validated['billing_category'] !== '' ? $validated['billing_category'] : null,
            'work_summary' => $validated['work_summary'] !== '' ? $validated['work_summary'] : null,
            'hours' => $validated['hours'],
            'rate' => $validated['rate'],
            'amount' => $validated['amount'],
            'non_billable_reason' => $validated['non_billable_reason'] !== '' ? $validated['non_billable_reason'] : null,
            'expense_reference' => $validated['expense_reference'] !== '' ? $validated['expense_reference'] : null,
            'delivered_at' => $delivered_at,
            'completed_at' => $now,
            'done_at' => $now,
            'updated_at' => $now,
        ];

        $started_transaction = false;
        if ($wpdb->query('START TRANSACTION') !== false) {
            $started_transaction = true;
        }

        $updated = $wpdb->update($tasks_table, $update_data, ['id' => $task_id]);
        if ($updated === false) {
            if ($started_transaction) {
                $wpdb->query('ROLLBACK');
            }
            return new WP_REST_Response(['message' => 'Could not mark this task done.'], 500);
        }

        $updated_task = self::get_task_row($task_id);
        if (! $updated_task) {
            if ($started_transaction) {
                $wpdb->query('ROLLBACK');
            }
            return new WP_REST_Response(['message' => 'Task could not be reloaded after completion.'], 500);
        }

        $ledger_entry = self::upsert_work_ledger_entry($updated_task);
        if (is_wp_error($ledger_entry)) {
            if ($started_transaction) {
                $wpdb->query('ROLLBACK');
            }
            return new WP_REST_Response(['message' => $ledger_entry->get_error_message()], (int) ($ledger_entry->get_error_data()['status'] ?? 422));
        }

        self::insert_status_history(
            $history_table,
            $task_id,
            $current_status,
            'done',
            $user_id,
            (string) $request->get_param('note'),
            'marked_done',
            [
                'billing_mode' => $validated['billing_mode'],
                'billing_category' => $validated['billing_category'],
                'invoice_status' => (string) ($ledger_entry['invoice_status'] ?? ''),
            ]
        );

        if ($started_transaction) {
            $wpdb->query('COMMIT');
        }

        self::sync_task_calendar_event((array) $updated_task);

        return new WP_REST_Response([
            'ok' => true,
            'task' => self::get_enriched_task($task_id),
            'ledger_entry' => $ledger_entry,
        ], 200);
    }

    public static function approve_batch(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $tasks = $wpdb->prefix . 'pq_tasks';
        $history = $wpdb->prefix . 'pq_task_status_history';

        $task_ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get_param('task_ids')))));
        if (empty($task_ids)) {
            return new WP_REST_Response(['message' => 'Select at least one task to approve.'], 422);
        }

        $user_id = get_current_user_id();
        $updated = [];

        foreach ($task_ids as $task_id) {
            $task = self::get_task_row($task_id);
            if (! $task) {
                return new WP_REST_Response(['message' => 'One or more selected tasks could not be loaded.'], 404);
            }

            if (! self::can_access_task($task, $user_id)) {
                return new WP_REST_Response(['message' => 'You cannot approve one or more selected tasks.'], 403);
            }

            $current_status = WP_PQ_Workflow::normalize_status((string) ($task['status'] ?? ''));
            if (! in_array($current_status, ['pending_approval', 'needs_clarification'], true)) {
                return new WP_REST_Response(['message' => 'Only pending or clarification tasks can be batch approved.'], 422);
            }

            if (! WP_PQ_Workflow::can_transition($current_status, 'approved', $user_id)) {
                return new WP_REST_Response(['message' => 'One or more selected tasks cannot be approved.'], 422);
            }

            $wpdb->update($tasks, [
                'status' => 'approved',
                'updated_at' => current_time('mysql', true),
            ], ['id' => $task_id]);

            self::insert_status_history($history, $task_id, $current_status, 'approved', $user_id, 'batch_approved', 'approved');
            self::emit_status_event($task_id, $current_status, 'approved');
            self::sync_task_calendar_event((array) self::get_task_row($task_id));
            $enriched = self::get_enriched_task($task_id);
            if ($enriched) {
                $updated[] = $enriched;
            }
        }

        return new WP_REST_Response([
            'ok' => true,
            'tasks' => $updated,
            'task_count' => count($updated),
        ], 200);
    }

    public static function delete_task(WP_REST_Request $request): WP_REST_Response
    {
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task) {
            return new WP_REST_Response(['message' => 'Task not found.'], 404);
        }

        $user_id = get_current_user_id();
        if (! self::can_delete_task($task, $user_id)) {
            return new WP_REST_Response(['message' => 'You cannot delete this task.'], 403);
        }

        if (
            (int) ($task['statement_id'] ?? 0) > 0
            || (int) ($task['work_log_id'] ?? 0) > 0
            || in_array((string) ($task['billing_status'] ?? ''), ['batched', 'statement_sent', 'paid'], true)
        ) {
            return new WP_REST_Response(['message' => 'This task is already tied to billing records. Remove it from billing first or archive it instead.'], 422);
        }

        self::purge_task($task);

        return new WP_REST_Response([
            'ok' => true,
            'deleted_task_id' => $task_id,
        ], 200);
    }

    public static function get_messages(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $table = $wpdb->prefix . 'pq_task_messages';
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY id ASC", $task_id),
            ARRAY_A
        );

        $rows = self::attach_author_names($rows);

        return new WP_REST_Response(['messages' => $rows], 200);
    }

    public static function create_message(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $body = trim((string) $request->get_param('body'));
        if ($body === '') {
            return new WP_REST_Response(['message' => 'Message body is required.'], 422);
        }

        self::store_task_message($task_id, get_current_user_id(), $body, $task);

        $table = $wpdb->prefix . 'pq_task_messages';
        $message = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY id DESC LIMIT 1", $task_id),
            ARRAY_A
        );

        return new WP_REST_Response([
            'ok' => true,
            'message' => $message ? self::attach_author_names([$message])[0] : null,
            'task' => self::get_enriched_task($task_id),
        ], 201);
    }

    public static function get_notes(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $table = $wpdb->prefix . 'pq_task_comments';
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY id DESC", $task_id),
            ARRAY_A
        );

        $rows = self::attach_author_names($rows);

        return new WP_REST_Response(['notes' => $rows], 200);
    }

    public static function create_note(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $body = trim((string) $request->get_param('body'));
        if ($body === '') {
            return new WP_REST_Response(['message' => 'Sticky note text is required.'], 422);
        }

        $table = $wpdb->prefix . 'pq_task_comments';
        $wpdb->insert($table, [
            'task_id' => $task_id,
            'author_id' => get_current_user_id(),
            'body' => sanitize_textarea_field($body),
            'created_at' => current_time('mysql', true),
        ]);
        $note = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY id DESC LIMIT 1", $task_id),
            ARRAY_A
        );

        return new WP_REST_Response([
            'ok' => true,
            'note' => $note ? self::attach_author_names([$note])[0] : null,
            'task' => self::get_enriched_task($task_id),
        ], 201);
    }

    public static function get_task_participants(WP_REST_Request $request): WP_REST_Response
    {
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        return new WP_REST_Response([
            'participants' => self::task_participants($task),
        ], 200);
    }

    public static function get_files(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
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
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
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
            'task' => self::get_enriched_task($task_id),
        ], 201);
    }

    public static function get_meetings(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $table = $wpdb->prefix . 'pq_task_meetings';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY id DESC", $task_id), ARRAY_A);

        return new WP_REST_Response(['meetings' => $rows], 200);
    }

    public static function create_meeting(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = self::get_task_row($task_id);

        if (! $task || ! self::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $starts_at = self::sanitize_datetime($request->get_param('starts_at'));
        $ends_at = self::sanitize_datetime($request->get_param('ends_at'));
        $event_id = sanitize_text_field((string) $request->get_param('event_id'));
        $meeting_url = esc_url_raw((string) $request->get_param('meeting_url'));

        if ($event_id === '' && $starts_at && $ends_at) {
            $google_event = self::create_google_calendar_event($task, $starts_at, $ends_at);
            if (is_wp_error($google_event)) {
                return new WP_REST_Response(['message' => $google_event->get_error_message()], 500);
            }
            $event_id = sanitize_text_field((string) ($google_event['id'] ?? ''));
            $meeting_url = esc_url_raw((string) ($google_event['hangoutLink'] ?? ''));
        }

        $table = $wpdb->prefix . 'pq_task_meetings';
        $wpdb->insert($table, [
            'task_id' => $task_id,
            'provider' => 'google',
            'event_id' => $event_id,
            'meeting_url' => $meeting_url,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'sync_direction' => 'two_way',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);

        $meeting = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY id DESC LIMIT 1", $task_id),
            ARRAY_A
        );

        return new WP_REST_Response([
            'ok' => true,
            'event_id' => $event_id,
            'meeting_url' => $meeting_url,
            'meeting' => $meeting,
            'task' => self::get_enriched_task($task_id),
        ], 201);
    }

    public static function calendar_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params();
        do_action('wp_pq_calendar_webhook_received', $payload);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function get_calendar_events(): WP_REST_Response
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $meetings_table = $wpdb->prefix . 'pq_task_meetings';
        $user_id = get_current_user_id();

        $tasks = self::get_visible_tasks_for_request($request ?? null, $user_id, true);

        self::backfill_task_calendar_events($tasks);

        $events = [];
        $visible_task_ids = [];

        foreach ($tasks as $task) {
            $visible_task_ids[] = (int) $task['id'];
            $dt = $task['requested_deadline'] ?: $task['due_at'];
            if (! $dt) {
                continue;
            }

            $events[] = [
                'id' => 'task_' . (int) $task['id'],
                'title' => '[Task] ' . (string) $task['title'],
                'start' => gmdate('c', strtotime((string) $dt . ' UTC')),
                'allDay' => false,
                'backgroundColor' => '#be123c',
                'borderColor' => '#be123c',
                'extendedProps' => [
                    'source' => 'task',
                    'taskId' => (int) $task['id'],
                    'status' => (string) $task['status'],
                    'priority' => (string) $task['priority'],
                    'description' => (string) $task['description'],
                    'needsMeeting' => ! empty($task['needs_meeting']),
                    'requestedDeadline' => (string) $task['requested_deadline'],
                    'dueAt' => (string) $task['due_at'],
                ],
            ];
        }

        if (! empty($visible_task_ids)) {
            $ids_in = implode(',', array_map('intval', $visible_task_ids));
            $meetings = $wpdb->get_results("SELECT * FROM {$meetings_table} WHERE task_id IN ({$ids_in}) ORDER BY id DESC", ARRAY_A);

            foreach ($meetings as $meeting) {
                if (! empty($meeting['starts_at'])) {
                    $events[] = [
                        'id' => 'meeting_' . (int) $meeting['id'],
                        'title' => '[Meet] Task #' . (int) $meeting['task_id'],
                        'start' => gmdate('c', strtotime((string) $meeting['starts_at'] . ' UTC')),
                        'end' => ! empty($meeting['ends_at']) ? gmdate('c', strtotime((string) $meeting['ends_at'] . ' UTC')) : null,
                        'allDay' => false,
                        'backgroundColor' => '#1d4ed8',
                        'borderColor' => '#1d4ed8',
                        'url' => ! empty($meeting['meeting_url']) ? $meeting['meeting_url'] : null,
                        'extendedProps' => [
                            'source' => 'meeting',
                            'taskId' => (int) $meeting['task_id'],
                            'eventId' => (string) $meeting['event_id'],
                            'meetingUrl' => (string) $meeting['meeting_url'],
                        ],
                    ];
                }
            }
        }

        $google_events = self::fetch_google_calendar_events();
        if (is_array($google_events)) {
            foreach ($google_events as $google_event) {
                $events[] = $google_event;
            }
        }

        return new WP_REST_Response(['events' => $events], 200);
    }

    private static function get_visible_tasks_for_request(?WP_REST_Request $request, int $user_id, bool $calendar_order = false): array
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $can_view_all = current_user_can(WP_PQ_Roles::CAP_VIEW_ALL);
        $selected_client_id = max(0, (int) ($request ? ($request->get_param('client_id') ?: $request->get_param('client_user_id')) : 0));
        $selected_bucket_id = max(0, (int) ($request ? $request->get_param('billing_bucket_id') : 0));

        if ($can_view_all) {
            $where = [];
            $params = [];
            $where[] = "status NOT IN ('done','archived')";
            if ($selected_client_id > 0) {
                $where[] = 'client_id = %d';
                $params[] = $selected_client_id;
            }

            if ($selected_bucket_id > 0) {
                $where[] = 'billing_bucket_id = %d';
                $params[] = $selected_bucket_id;
            }

            $sql = "SELECT * FROM {$tasks_table}";
            if (! empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= $calendar_order ? ' ORDER BY id DESC' : ' ORDER BY queue_position ASC, id DESC';

            return empty($params)
                ? $wpdb->get_results($sql, ARRAY_A)
                : $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        $rows = $wpdb->get_results("SELECT * FROM {$tasks_table} ORDER BY " . ($calendar_order ? 'id DESC' : 'queue_position ASC, id DESC'), ARRAY_A);

        return array_values(array_filter((array) $rows, static function (array $task) use ($user_id, $selected_client_id, $selected_bucket_id): bool {
            if (WP_PQ_Workflow::normalize_status((string) ($task['status'] ?? '')) === 'done') {
                return false;
            }
            if (! self::can_access_task($task, $user_id)) {
                return false;
            }
            if ($selected_client_id > 0 && (int) ($task['client_id'] ?? 0) !== $selected_client_id) {
                return false;
            }
            if ($selected_bucket_id > 0 && (int) ($task['billing_bucket_id'] ?? 0) !== $selected_bucket_id) {
                return false;
            }
            return true;
        }));
    }

    private static function build_task_filters_payload(int $user_id): array
    {
        $can_view_all = current_user_can(WP_PQ_Roles::CAP_VIEW_ALL);

        return [
            'can_view_all' => $can_view_all,
            'clients' => $can_view_all ? self::task_filter_clients() : [],
            'buckets' => self::task_filter_buckets($can_view_all ? 0 : self::current_client_scope_id($user_id), $user_id),
        ];
    }

    private static function task_filter_clients(): array
    {
        global $wpdb;

        $clients_table = $wpdb->prefix . 'pq_clients';
        $rows = $wpdb->get_results(
            "SELECT id, name, primary_contact_user_id FROM {$clients_table} ORDER BY name ASC, id ASC",
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            $primary_contact = (int) ($row['primary_contact_user_id'] ?? 0) > 0
                ? get_user_by('ID', (int) $row['primary_contact_user_id'])
                : null;
            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['name'] ?? '') . ($primary_contact && is_email($primary_contact->user_email) ? ' (' . $primary_contact->user_email . ')' : ''),
                'name' => (string) ($row['name'] ?? ''),
                'email' => $primary_contact && is_email($primary_contact->user_email) ? (string) $primary_contact->user_email : '',
                'primary_contact_user_id' => (int) ($row['primary_contact_user_id'] ?? 0),
            ];
        }, $rows);
    }

    private static function task_filter_buckets(int $client_id = 0, int $user_id = 0): array
    {
        global $wpdb;

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $clients_table = $wpdb->prefix . 'pq_clients';
        $where_clauses = [];
        $params = [];

        if ($client_id > 0) {
            $where_clauses[] = 'b.client_id = %d';
            $params[] = $client_id;
        }

        if ($user_id > 0 && ! current_user_can(WP_PQ_Roles::CAP_VIEW_ALL)) {
            $accessible_bucket_ids = self::accessible_job_ids($user_id);
            if (! empty($accessible_bucket_ids)) {
                $where_clauses[] = 'b.id IN (' . implode(',', array_map('intval', $accessible_bucket_ids)) . ')';
            } else {
                return [];
            }
        }

        $where = '';
        if (! empty($where_clauses)) {
            $where = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        $rows = $wpdb->get_results(
            empty($params)
            ? "SELECT b.id, b.client_id, b.client_user_id, b.bucket_name, b.is_default, c.name AS client_name
             FROM {$buckets_table} b
             LEFT JOIN {$clients_table} c ON c.id = b.client_id
             {$where}
             ORDER BY c.name ASC, b.is_default DESC, b.bucket_name ASC"
            : $wpdb->prepare(
                "SELECT b.id, b.client_id, b.client_user_id, b.bucket_name, b.is_default, c.name AS client_name
                 FROM {$buckets_table} b
                 LEFT JOIN {$clients_table} c ON c.id = b.client_id
                 {$where}
                 ORDER BY c.name ASC, b.is_default DESC, b.bucket_name ASC",
                ...$params
            ),
            ARRAY_A
        );

        return array_map(static function (array $row): array {
            $bucket_name = trim((string) ($row['bucket_name'] ?? ''));
            if ($bucket_name === '') {
                $bucket_name = ((int) ($row['is_default'] ?? 0) === 1) ? 'Main' : 'Job';
            }
            return [
                'id' => (int) ($row['id'] ?? 0),
                'client_id' => (int) ($row['client_id'] ?? 0),
                'client_user_id' => (int) ($row['client_user_id'] ?? 0),
                'label' => $bucket_name,
                'bucket_name' => $bucket_name,
                'is_default' => ((int) ($row['is_default'] ?? 0) === 1),
                'client_name' => (string) ($row['client_name'] ?? ''),
            ];
        }, $rows);
    }

    private static function resolve_client_id(WP_REST_Request $request, int $current_user_id): int
    {
        if (! current_user_can(WP_PQ_Roles::CAP_VIEW_ALL)) {
            return self::current_client_scope_id($current_user_id);
        }

        $client_id = max(0, (int) ($request->get_param('client_id') ?: 0));
        if ($client_id > 0) {
            return $client_id;
        }

        $client_user_id = max(0, (int) $request->get_param('client_user_id'));
        if ($client_user_id > 0) {
            return WP_PQ_DB::get_or_create_client_id_for_user($client_user_id);
        }

        return self::current_client_scope_id($current_user_id);
    }

    private static function resolve_client_primary_contact_id(int $client_id, int $fallback_user_id): int
    {
        $primary_contact_user_id = WP_PQ_DB::get_primary_contact_user_id($client_id);
        return $primary_contact_user_id > 0 ? $primary_contact_user_id : $fallback_user_id;
    }

    private static function resolve_billing_bucket_id(WP_REST_Request $request, int $client_id): int
    {
        global $wpdb;

        $bucket_id = max(0, (int) $request->get_param('billing_bucket_id'));
        if ($bucket_id > 0) {
            $owner_client_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT client_id FROM {$wpdb->prefix}pq_billing_buckets WHERE id = %d LIMIT 1",
                $bucket_id
            ));
            if ($owner_client_id === $client_id) {
                return $bucket_id;
            }
        }

        return WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);
    }

    private static function create_bucket_for_client(int $client_id, string $bucket_name): int
    {
        global $wpdb;

        $bucket_name = trim($bucket_name);
        if ($client_id <= 0 || $bucket_name === '') {
            return WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);
        }

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $slug = sanitize_title($bucket_name);
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_id = %d AND bucket_slug = %s LIMIT 1",
            $client_id,
            $slug
        ));

        if ($existing_id > 0) {
            return $existing_id;
        }

        $primary_contact_user_id = WP_PQ_DB::get_primary_contact_user_id($client_id);
        $wpdb->insert($buckets_table, [
            'client_id' => $client_id,
            'client_user_id' => $primary_contact_user_id,
            'bucket_name' => $bucket_name,
            'bucket_slug' => $slug,
            'description' => '',
            'is_default' => 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
        ]);

        $created_id = (int) $wpdb->insert_id;
        if ($created_id > 0) {
            foreach (WP_PQ_DB::get_client_admin_user_ids($client_id) as $admin_user_id) {
                WP_PQ_DB::ensure_job_member($created_id, $admin_user_id);
            }
            return $created_id;
        }

        return WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);
    }

    private static function resolve_action_owner_id(WP_REST_Request $request, array $owner_ids, int $client_id = 0, int $primary_contact_user_id = 0): int
    {
        $action_owner_id = max(0, (int) $request->get_param('action_owner_id'));
        if ($action_owner_id > 0 && get_user_by('ID', $action_owner_id)) {
            return $action_owner_id;
        }

        if (! empty($owner_ids)) {
            return (int) reset($owner_ids);
        }

        $default_owner_id = self::default_client_action_owner_id($client_id, $primary_contact_user_id);
        if ($default_owner_id > 0) {
            return $default_owner_id;
        }

        return 0;
    }

    private static function default_client_action_owner_id(int $client_id, int $primary_contact_user_id = 0): int
    {
        if ($client_id <= 0) {
            return 0;
        }

        $client_admin_ids = array_values(array_filter(array_map('intval', WP_PQ_DB::get_client_admin_user_ids($client_id))));
        if ($primary_contact_user_id > 0 && in_array($primary_contact_user_id, $client_admin_ids, true) && get_user_by('ID', $primary_contact_user_id)) {
            return $primary_contact_user_id;
        }

        foreach ($client_admin_ids as $admin_user_id) {
            if ($admin_user_id > 0 && get_user_by('ID', $admin_user_id)) {
                return $admin_user_id;
            }
        }

        if ($primary_contact_user_id > 0 && get_user_by('ID', $primary_contact_user_id)) {
            return $primary_contact_user_id;
        }

        return 0;
    }

    public static function google_oauth_callback(WP_REST_Request $request): WP_REST_Response
    {
        $code = sanitize_text_field((string) $request->get_param('code'));
        $state = sanitize_text_field((string) $request->get_param('state'));
        $error = sanitize_text_field((string) $request->get_param('error'));
        $as_json = sanitize_text_field((string) $request->get_param('format')) === 'json';

        if ($error !== '') {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'Google OAuth failed: ' . $error,
                ], 400);
            }

            self::render_oauth_result_page(false, 'Google authorization was not completed (' . $error . ').');
        }

        if ($code === '') {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'Missing OAuth code.',
                ], 400);
            }

            self::render_oauth_result_page(false, 'Google authorization code is missing.');
        }

        $client_id = (string) get_option('wp_pq_google_client_id', '');
        $client_secret = (string) get_option('wp_pq_google_client_secret', '');
        $redirect_uri = self::google_redirect_uri();
        if ($client_id === '' || $client_secret === '') {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'Google client credentials are not configured.',
                ], 500);
            }

            self::render_oauth_result_page(false, 'Google client credentials are not configured in WordPress.');
        }

        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($resp)) {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'OAuth token exchange failed: ' . $resp->get_error_message(),
                ], 500);
            }

            self::render_oauth_result_page(false, 'OAuth token exchange failed: ' . $resp->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($body) || empty($body['access_token'])) {
            if ($as_json) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'OAuth token exchange failed.',
                    'details' => $body,
                ], 500);
            }

            self::render_oauth_result_page(false, 'OAuth token exchange failed. Please retry connection.');
        }

        self::store_google_tokens($body);

        if (! $as_json) {
            self::render_oauth_result_page(true, 'Google Calendar connected successfully.');
        }

        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Google Calendar connected successfully.',
            'state' => $state,
        ], 200);
    }

    public static function google_oauth_url(): WP_REST_Response
    {
        $client_id = (string) get_option('wp_pq_google_client_id', '');
        if ($client_id === '') {
            return new WP_REST_Response(['message' => 'Google client ID is not configured.'], 422);
        }

        $state = wp_generate_password(20, false, false);
        update_option('wp_pq_google_oauth_state', $state, false);

        $params = [
            'client_id' => $client_id,
            'redirect_uri' => self::google_redirect_uri(),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => self::google_scopes(),
            'state' => $state,
            'include_granted_scopes' => 'true',
        ];

        return new WP_REST_Response([
            'url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
        ], 200);
    }

    public static function google_oauth_status(): WP_REST_Response
    {
        $tokens = (array) get_option('wp_pq_google_tokens', []);
        $connected = ! empty($tokens['access_token']) || ! empty($tokens['refresh_token']);

        return new WP_REST_Response([
            'connected' => $connected,
            'has_refresh_token' => ! empty($tokens['refresh_token']),
            'expires_at' => isset($tokens['expires_at']) ? (int) $tokens['expires_at'] : null,
            'redirect_uri' => self::google_redirect_uri(),
        ], 200);
    }

    public static function get_notification_prefs(): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_notification_prefs';
        $user_id = get_current_user_id();
        $events = WP_PQ_Workflow::notification_events();
        $extra_prefs = ['alert_auto_dismiss' => false];

        $rows = $wpdb->get_results($wpdb->prepare("SELECT event_key, is_enabled FROM {$table} WHERE user_id = %d", $user_id), ARRAY_A);
        $map = [];

        foreach ($events as $event) {
            $map[$event] = true;
        }
        foreach ($extra_prefs as $pref_key => $default_value) {
            $map[$pref_key] = $default_value;
        }

        foreach ($rows as $row) {
            $map[$row['event_key']] = ((int) $row['is_enabled'] === 1);
        }

        return new WP_REST_Response(['prefs' => $map], 200);
    }

    public static function update_notification_prefs(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_notification_prefs';
        $user_id = get_current_user_id();
        $prefs = (array) $request->get_param('prefs');
        $allowed = array_merge(WP_PQ_Workflow::notification_events(), ['alert_auto_dismiss']);

        foreach ($prefs as $event => $enabled) {
            $event = sanitize_key((string) $event);
            if (! in_array($event, $allowed, true)) {
                continue;
            }

            $wpdb->replace($table, [
                'user_id' => $user_id,
                'event_key' => $event,
                'is_enabled' => $enabled ? 1 : 0,
                'updated_at' => current_time('mysql', true),
            ]);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function get_notifications(): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_notifications';
        $user_id = get_current_user_id();

        $wpdb->delete($table, [
            'user_id' => $user_id,
            'is_read' => 1,
        ]);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ), ARRAY_A);

        $unread = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        foreach ($rows as &$row) {
            $row['payload'] = $row['payload'] ? json_decode((string) $row['payload'], true) : null;
            $row['is_read'] = false;
        }

        return new WP_REST_Response([
            'notifications' => $rows,
            'unread_count' => $unread,
        ], 200);
    }

    public static function mark_notifications_read(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_notifications';
        $user_id = get_current_user_id();
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get_param('ids')))));

        if (empty($ids)) {
            $wpdb->delete($table, ['user_id' => $user_id]);
        } else {
            $ids_in = implode(',', $ids);
            if ($ids_in !== '') {
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE user_id = %d AND id IN ({$ids_in})", $user_id));
            }
        }

        return self::get_notifications();
    }

    public static function get_workers(WP_REST_Request $request): WP_REST_Response
    {
        $task = null;
        $task_id = max(0, (int) $request->get_param('task_id'));
        $client_id = max(0, (int) ($request->get_param('client_id') ?: 0));
        $billing_bucket_id = max(0, (int) ($request->get_param('billing_bucket_id') ?: 0));

        if ($task_id > 0) {
            $task = self::get_task_row($task_id);
            if ($task) {
                $client_id = max($client_id, (int) ($task['client_id'] ?? 0));
                $billing_bucket_id = max($billing_bucket_id, (int) ($task['billing_bucket_id'] ?? 0));
            }
        }

        $users = get_users([
            'fields' => 'all',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $items = [];
        $job_member_ids = $billing_bucket_id > 0 ? WP_PQ_DB::get_job_member_ids($billing_bucket_id) : [];
        foreach ($users as $user) {
            $roles = (array) $user->roles;
            $is_internal = ! empty(array_intersect($roles, ['pq_worker', 'pq_manager', 'administrator']));
            $client_role = '';
            $job_member = false;

            if ($client_id > 0) {
                foreach (WP_PQ_DB::get_user_client_memberships((int) $user->ID) as $membership) {
                    if ((int) ($membership['client_id'] ?? 0) !== $client_id) {
                        continue;
                    }
                    $client_role = (string) ($membership['role'] ?? '');
                    break;
                }
            }

            if ($billing_bucket_id > 0) {
                $job_member = in_array((int) $user->ID, $job_member_ids, true);
            }

            $is_relevant_client_user = $client_role !== '' && (
                $client_role === 'client_admin'
                || $billing_bucket_id <= 0
                || $job_member
                || ((int) ($task['action_owner_id'] ?? 0) === (int) $user->ID)
            );

            if (! $is_internal && ! $is_relevant_client_user) {
                continue;
            }

            $scope_label = $is_internal
                ? 'Internal'
                : ucfirst(str_replace('_', ' ', $client_role)) . ($job_member ? ' · job member' : '');
            $items[] = [
                'id' => (int) $user->ID,
                'name' => (string) $user->display_name,
                'email' => (string) $user->user_email,
                'roles' => array_values($roles),
                'client_role' => $client_role,
                'job_member' => $job_member,
                'scope_label' => $scope_label,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $a_internal = ($a['scope_label'] ?? '') === 'Internal';
            $b_internal = ($b['scope_label'] ?? '') === 'Internal';
            if ($a_internal !== $b_internal) {
                return $a_internal ? -1 : 1;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return new WP_REST_Response(['workers' => $items], 200);
    }

    public static function batch_statement(WP_REST_Request $request): WP_REST_Response
    {
        $task_ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get_param('task_ids')))));
        $result = self::create_statement_batch(
            $task_ids,
            sanitize_textarea_field((string) $request->get_param('notes')),
            sanitize_text_field((string) $request->get_param('statement_month')),
            get_current_user_id()
        );

        if (is_wp_error($result)) {
            $status = (int) ($result->get_error_data('status') ?: 400);
            return new WP_REST_Response(['message' => $result->get_error_message()], $status);
        }

        return new WP_REST_Response([
            'ok' => true,
            'statement' => $result,
        ], 201);
    }

    public static function create_statement_batch(array $task_ids, string $notes = '', string $statement_month = '', int $user_id = 0)
    {
        return self::create_invoice_draft([
            'task_ids' => $task_ids,
            'notes' => $notes,
            'statement_month' => $statement_month,
        ], $user_id);
    }

    public static function create_invoice_draft(array $args, int $user_id = 0)
    {
        global $wpdb;

        $task_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($args['task_ids'] ?? [])))));
        $entry_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($args['entry_ids'] ?? [])))));
        $client_id = (int) ($args['client_id'] ?? 0);
        $statement_month = self::normalize_statement_month((string) ($args['statement_month'] ?? ''));
        $notes = sanitize_textarea_field((string) ($args['notes'] ?? ''));

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if (empty($entry_ids) && ! empty($task_ids)) {
            $entry_ids = self::resolve_invoice_draft_entry_ids_from_tasks($task_ids);
            if (is_wp_error($entry_ids)) {
                return $entry_ids;
            }
        }

        if (empty($entry_ids) && empty($task_ids)) {
            return new WP_Error('pq_missing_draft_entries', 'Choose at least one eligible completed work entry before creating an invoice draft.', ['status' => 422]);
        }

        $rows = self::load_invoice_draft_ledger_rows($entry_ids, $user_id);
        if (is_wp_error($rows)) {
            return $rows;
        }

        if (empty($rows)) {
            return new WP_Error('pq_missing_draft_entries', 'No eligible completed work could be added to this invoice draft.', ['status' => 422]);
        }

        $client_user_id = 0;
        $range_start = '';
        $range_end = '';

        foreach ($rows as $row) {
            $row_client_id = (int) ($row['client_id'] ?? 0);
            $row_client_user_id = (int) ($row['client_user_id'] ?? 0);
            if ($client_id === 0) {
                $client_id = $row_client_id;
                $client_user_id = $row_client_user_id;
            } elseif ($client_id !== $row_client_id) {
                return new WP_Error('pq_mixed_client_draft', 'Invoice drafts must belong to one client only.', ['status' => 422]);
            }

            if ($client_user_id <= 0 && $row_client_user_id > 0) {
                $client_user_id = $row_client_user_id;
            }

            $row_rollup_date = self::ledger_rollup_date($row);
            if ($row_rollup_date !== '') {
                $range_start = $range_start === '' || $row_rollup_date < $range_start ? $row_rollup_date : $range_start;
                $range_end = $range_end === '' || $row_rollup_date > $range_end ? $row_rollup_date : $range_end;
            }
        }

        if ($client_id <= 0) {
            return new WP_Error('pq_invalid_draft_client', 'Invoice drafts must belong to one client.', ['status' => 422]);
        }

        if ($client_user_id <= 0) {
            $client_user_id = WP_PQ_DB::get_primary_contact_user_id($client_id);
        }

        if ($range_start === '' || $range_end === '') {
            $month_ts = strtotime($statement_month . '-01');
            $range_start = $range_start !== '' ? $range_start : wp_date('Y-m-01', $month_ts);
            $range_end = $range_end !== '' ? $range_end : wp_date('Y-m-t', $month_ts);
        }

        $statements_table = $wpdb->prefix . 'pq_statements';
        $items_table = $wpdb->prefix . 'pq_statement_items';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $history_table = $wpdb->prefix . 'pq_task_status_history';
        $statement_code = self::generate_statement_code($statement_month);
        $billing_bucket_id = self::single_bucket_id_from_rows($rows, 'billing_bucket_id');
        $now = current_time('mysql', true);

        $wpdb->insert($statements_table, [
            'statement_code' => $statement_code,
            'statement_month' => $statement_month,
            'client_id' => $client_id,
            'client_user_id' => $client_user_id > 0 ? $client_user_id : null,
            'billing_bucket_id' => $billing_bucket_id > 0 ? $billing_bucket_id : null,
            'range_start' => $range_start ?: null,
            'range_end' => $range_end ?: null,
            'currency_code' => 'USD',
            'total_amount' => 0,
            'created_by' => $user_id,
            'notes' => $notes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $statement_id = (int) $wpdb->insert_id;
        if ($statement_id <= 0) {
            return new WP_Error('pq_statement_insert_failed', 'Failed to create the invoice draft.', ['status' => 500]);
        }

        $line_count = 0;
        foreach (self::build_invoice_draft_lines_from_ledger_entries($rows) as $line) {
            if (self::insert_statement_line($statement_id, $line, $now) > 0) {
                $line_count++;
            }
        }

        foreach ($rows as $row) {
            $ledger_entry_id = (int) ($row['ledger_entry_id'] ?? 0);
            $task_id = (int) ($row['task_id'] ?? 0);

            if ($ledger_entry_id > 0) {
                $wpdb->update($ledger_table, [
                    'invoice_status' => 'invoiced',
                    'invoice_draft_id' => $statement_id,
                    'updated_at' => $now,
                ], ['id' => $ledger_entry_id]);
            }

            if ($task_id > 0) {
                $wpdb->insert($items_table, [
                    'statement_id' => $statement_id,
                    'task_id' => $task_id,
                    'created_at' => $now,
                ]);

                $wpdb->update($tasks_table, [
                    'billing_status' => 'batched',
                    'statement_id' => $statement_id,
                    'statement_batched_at' => $now,
                    'updated_at' => $now,
                ], ['id' => $task_id]);

                self::insert_history_note($history_table, $task_id, (string) ($row['task_status'] ?? 'done'), $user_id, 'invoice_draft:' . $statement_code);
                self::emit_event($task_id, 'statement_batched', 'Invoice draft created', 'This completed work item was added to invoice draft ' . $statement_code . '.');
            }
        }

        self::recalculate_statement_total($statement_id);

        return [
            'id' => $statement_id,
            'code' => $statement_code,
            'month' => $statement_month,
            'client_id' => $client_id,
            'client_user_id' => $client_user_id,
            'billing_bucket_id' => $billing_bucket_id,
            'range_start' => $range_start,
            'range_end' => $range_end,
            'task_count' => count(array_filter($rows, static fn(array $row): bool => (int) ($row['task_id'] ?? 0) > 0)),
            'entry_count' => count($rows),
            'line_count' => $line_count,
        ];
    }

    public static function create_work_log_batch(array $task_ids, string $notes = '', string $range_start = '', string $range_end = '', int $user_id = 0, array $snapshot_filters = [])
    {
        global $wpdb;

        $task_ids = array_values(array_unique(array_filter(array_map('intval', $task_ids))));
        if (empty($task_ids)) {
            return new WP_Error('pq_no_work_log_tasks', 'Select at least one task to add to a work statement.', ['status' => 422]);
        }

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $logs_table = $wpdb->prefix . 'pq_work_logs';
        $items_table = $wpdb->prefix . 'pq_work_log_items';
        $ids_in = implode(',', $task_ids);
        $rows = $wpdb->get_results(
            "SELECT t.*, b.bucket_name, b.is_default
             FROM {$tasks_table} t
             LEFT JOIN {$wpdb->prefix}pq_billing_buckets b ON b.id = t.billing_bucket_id
             WHERE t.id IN ({$ids_in})",
            ARRAY_A
        );

        if (count($rows) !== count($task_ids)) {
            return new WP_Error('pq_missing_work_log_task', 'One or more tasks could not be loaded for the work log.', ['status' => 404]);
        }

        $client_id = 0;
        $client_user_id = 0;
        $billing_bucket_id = 0;
        $derived_start = '';
        $derived_end = '';

        foreach ($rows as $row) {
            if (! self::can_access_task($row, $user_id)) {
                return new WP_Error('pq_forbidden_work_log', 'You cannot add one or more selected tasks to a work log.', ['status' => 403]);
            }

            $row_client_id = (int) ($row['client_id'] ?? 0);
            $row_client_user_id = (int) ($row['client_user_id'] ?? 0);
            $row_bucket_id = (int) ($row['billing_bucket_id'] ?? 0);

            if ($client_id === 0) {
                $client_id = $row_client_id;
                $client_user_id = $row_client_user_id;
            } elseif ($client_id !== $row_client_id) {
                return new WP_Error('pq_mixed_work_log_group', 'Work statements must be created for one client at a time.', ['status' => 422]);
            }

            $row_rollup_date = self::task_rollup_date($row);
            if ($row_rollup_date !== '') {
                $derived_start = $derived_start === '' || $row_rollup_date < $derived_start ? $row_rollup_date : $derived_start;
                $derived_end = $derived_end === '' || $row_rollup_date > $derived_end ? $row_rollup_date : $derived_end;
            }
        }

        $billing_bucket_id = self::single_bucket_id_from_tasks($rows);
        $range_start = self::normalize_rollup_date($range_start ?: $derived_start);
        $range_end = self::normalize_rollup_date($range_end ?: $derived_end);
        $log_code = self::generate_work_log_code($range_end ?: $range_start);
        $now = current_time('mysql', true);

        $wpdb->insert($logs_table, [
            'work_log_code' => $log_code,
            'client_id' => $client_id,
            'client_user_id' => $client_user_id,
            'billing_bucket_id' => $billing_bucket_id,
            'range_start' => $range_start ?: null,
            'range_end' => $range_end ?: null,
            'created_by' => $user_id,
            'notes' => sanitize_textarea_field($notes),
            'snapshot_filters' => ! empty($snapshot_filters) ? wp_json_encode($snapshot_filters) : null,
            'created_at' => $now,
        ]);

        $work_log_id = (int) $wpdb->insert_id;
        if ($work_log_id <= 0) {
            return new WP_Error('pq_work_log_insert_failed', 'Failed to create the work log.', ['status' => 500]);
        }

        foreach ($rows as $row) {
            $task_id = (int) $row['id'];
            $wpdb->insert($items_table, [
                'work_log_id' => $work_log_id,
                'task_id' => $task_id,
                'task_title' => (string) ($row['title'] ?? ''),
                'task_description' => (string) ($row['description'] ?? ''),
                'task_status' => (string) ($row['status'] ?? ''),
                'task_billing_status' => (string) ($row['billing_status'] ?? ''),
                'task_bucket_name' => (string) ($row['bucket_name'] ?? ''),
                'task_bucket_is_default' => (int) ($row['is_default'] ?? 0) === 1 ? 1 : 0,
                'task_updated_at' => (string) ($row['updated_at'] ?? $row['created_at'] ?? $now),
                'created_at' => $now,
            ]);

            $wpdb->update($tasks_table, [
                'work_log_id' => $work_log_id,
                'work_logged_at' => $now,
                'updated_at' => $now,
            ], ['id' => $task_id]);
        }

        return [
            'id' => $work_log_id,
            'code' => $log_code,
            'client_id' => $client_id,
            'client_user_id' => $client_user_id,
            'billing_bucket_id' => $billing_bucket_id,
            'range_start' => $range_start,
            'range_end' => $range_end,
            'task_count' => count($rows),
        ];
    }

    public static function create_work_log_snapshot(array $args, int $user_id = 0)
    {
        global $wpdb;

        $client_id = (int) ($args['client_id'] ?? 0);
        $range_start = self::normalize_rollup_date((string) ($args['range_start'] ?? ''));
        $range_end = self::normalize_rollup_date((string) ($args['range_end'] ?? ''));
        $job_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($args['job_ids'] ?? [])))));
        $statuses = array_values(array_unique(array_filter(array_map(
            static fn($status): string => WP_PQ_Workflow::normalize_status((string) $status),
            (array) ($args['statuses'] ?? [])
        ))));
        $notes = sanitize_textarea_field((string) ($args['notes'] ?? ''));

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($client_id <= 0 || $range_start === '' || $range_end === '') {
            return new WP_Error('pq_invalid_work_statement_scope', 'Choose a client and date range before creating a work statement.', ['status' => 422]);
        }

        $allowed_statuses = WP_PQ_Workflow::allowed_statuses();
        $statuses = array_values(array_intersect($statuses, $allowed_statuses));
        if (empty($statuses)) {
            $statuses = $allowed_statuses;
        }

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $where = [
            $wpdb->prepare("t.client_id = %d", $client_id),
            $wpdb->prepare("DATE(COALESCE(t.updated_at, t.created_at)) BETWEEN %s AND %s", $range_start, $range_end),
        ];

        $status_sql = implode("','", array_map('esc_sql', $statuses));
        $where[] = "t.status IN ('{$status_sql}')";

        if (! empty($job_ids)) {
            $where[] = 't.billing_bucket_id IN (' . implode(',', array_map('intval', $job_ids)) . ')';
        }

        $rows = $wpdb->get_results(
            "SELECT t.id FROM {$tasks_table} t WHERE " . implode(' AND ', $where) . " ORDER BY t.status ASC, COALESCE(t.updated_at, t.created_at) DESC, t.id DESC",
            ARRAY_A
        );
        $task_ids = array_values(array_unique(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows)));
        if (empty($task_ids)) {
            return new WP_Error('pq_empty_work_statement', 'No tasks matched those work statement filters.', ['status' => 422]);
        }

        return self::create_work_log_batch($task_ids, $notes, $range_start, $range_end, $user_id, [
            'client_id' => $client_id,
            'job_ids' => $job_ids,
            'statuses' => $statuses,
            'date_basis' => 'updated_at',
        ]);
    }

    private static function emit_event(int $task_id, string $event_key, string $subject, string $body): void
    {
        if ($task_id <= 0) {
            return;
        }

        $task = self::get_task_row($task_id);
        if (! $task) {
            return;
        }

        $recipient_ids = [
            (int) ($task['submitter_id'] ?? 0),
            (int) ($task['client_user_id'] ?? 0),
            (int) ($task['action_owner_id'] ?? 0),
        ];
        $owners = json_decode((string) $task['owner_ids'], true);

        if (is_array($owners)) {
            foreach ($owners as $owner_id) {
                $recipient_ids[] = (int) $owner_id;
            }
        }

        $recipient_ids = array_merge($recipient_ids, self::client_notification_recipient_ids($task));
        $recipient_ids = array_values(array_unique(array_filter($recipient_ids)));

        foreach ($recipient_ids as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (! $user) {
                continue;
            }

            $message = self::build_event_message($task, $event_key, $user_id);
            self::create_notification($user_id, $task_id, $event_key, $message['title'], $message['body'], [
                'task_id' => $task_id,
                'event_key' => $event_key,
                'task_title' => (string) ($task['title'] ?? ''),
            ]);

            if (
                is_email($user->user_email)
                && WP_PQ_Housekeeping::is_event_enabled($user_id, $event_key)
                && ! self::should_suppress_generic_client_email($task, $user_id, $event_key)
            ) {
                wp_mail($user->user_email, $message['title'], $message['body'] . "\n\nTask ID: {$task_id}");
            }
        }
    }

    private static function emit_assignment_event(int $task_id, int $previous_action_owner_id, int $action_owner_id): void
    {
        if ($task_id <= 0 || $action_owner_id <= 0 || $action_owner_id === $previous_action_owner_id) {
            return;
        }

        $task = self::get_task_row($task_id);
        if (! $task) {
            return;
        }

        $recipient = get_user_by('ID', $action_owner_id);
        if (! $recipient) {
            return;
        }

        $message = self::build_event_message($task, 'task_assigned', $action_owner_id);
        self::create_notification($action_owner_id, $task_id, 'task_assigned', $message['title'], $message['body'], [
            'task_id' => $task_id,
            'event_key' => 'task_assigned',
            'task_title' => (string) ($task['title'] ?? ''),
            'action_owner_id' => $action_owner_id,
            'previous_action_owner_id' => $previous_action_owner_id,
        ]);

        if (is_email($recipient->user_email) && WP_PQ_Housekeeping::is_event_enabled($action_owner_id, 'task_assigned')) {
            wp_mail($recipient->user_email, $message['title'], $message['body'] . "\n\nTask ID: {$task_id}");
        }
    }

    private static function notify_mentions(int $task_id, array $task, string $body, int $author_id): void
    {
        $mentions = self::extract_mentions($body);
        if (empty($mentions)) {
            return;
        }

        $participants = self::task_participants($task);
        $by_handle = [];
        foreach ($participants as $participant) {
            $by_handle[strtolower((string) $participant['handle'])] = $participant;
        }

        $author = get_user_by('ID', $author_id);
        $author_name = $author ? $author->display_name : 'A collaborator';

        foreach ($mentions as $handle) {
            $participant = $by_handle[strtolower($handle)] ?? null;
            if (! $participant) {
                continue;
            }

            $mentioned_user_id = (int) $participant['id'];
            if ($mentioned_user_id === $author_id) {
                continue;
            }

            $user = get_user_by('ID', $mentioned_user_id);
            if (! $user || ! is_email($user->user_email)) {
                if (! $user) {
                    continue;
                }
            }

            $notification_title = $author_name . ' mentioned you';
            $notification_body = 'In "' . self::task_title($task) . '": ' . self::truncate_text(wp_strip_all_tags($body), 180);

            self::create_notification($mentioned_user_id, $task_id, 'task_mentioned', $notification_title, $notification_body, [
                'task_id' => $task_id,
                'mention_handle' => $participant['handle'],
                'task_title' => self::task_title($task),
            ]);

            if (is_email($user->user_email) && WP_PQ_Housekeeping::is_event_enabled($mentioned_user_id, 'task_mentioned')) {
                wp_mail(
                    $user->user_email,
                    'You were mentioned on a task',
                    $author_name . ' mentioned @' . $participant['handle'] . ' on "' . (string) ($task['title'] ?? 'Task') . "\".\n\n"
                    . 'Message: ' . wp_strip_all_tags($body) . "\n\nTask ID: {$task_id}"
                );
            }
        }
    }

    private static function extract_mentions(string $body): array
    {
        preg_match_all('/(^|\\s)@([A-Za-z0-9._-]{2,60})/', $body, $matches);
        if (empty($matches[2])) {
            return [];
        }

        return array_values(array_unique(array_map('sanitize_user', $matches[2])));
    }

    private static function task_participants(array $task): array
    {
        $ids = [
            (int) ($task['submitter_id'] ?? 0),
            (int) ($task['client_user_id'] ?? 0),
            (int) ($task['action_owner_id'] ?? 0),
        ];
        $owners = json_decode((string) $task['owner_ids'], true);

        if (is_array($owners)) {
            foreach ($owners as $owner_id) {
                $ids[] = (int) $owner_id;
            }
        }

        $ids = array_merge($ids, self::client_notification_recipient_ids($task));

        $staff = get_users([
            'role__in' => ['pq_worker', 'pq_manager', 'administrator'],
            'fields' => ['ID', 'display_name', 'user_login', 'user_nicename'],
        ]);

        foreach ($staff as $user) {
            $ids[] = (int) $user->ID;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        $people = [];

        foreach ($ids as $id) {
            $user = get_user_by('ID', $id);
            if (! $user) {
                continue;
            }

            $people[] = [
                'id' => (int) $user->ID,
                'name' => (string) $user->display_name,
                'handle' => (string) $user->user_login,
            ];
        }

        usort($people, static fn($a, $b) => strcmp((string) $a['name'], (string) $b['name']));
        return $people;
    }

    private static function create_notification(int $user_id, ?int $task_id, string $event_key, string $title, string $body, array $payload = []): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_notifications';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'task_id' => $task_id ?: null,
            'event_key' => $event_key,
            'title' => sanitize_text_field($title),
            'body' => sanitize_textarea_field($body),
            'payload' => wp_json_encode($payload),
            'is_read' => 0,
            'created_at' => current_time('mysql', true),
            'read_at' => null,
        ]);
    }

    private static function client_notification_recipient_ids(array $task): array
    {
        $client_id = (int) ($task['client_id'] ?? 0);
        if ($client_id <= 0) {
            return [];
        }

        $recipient_ids = [];
        foreach (WP_PQ_DB::get_client_memberships($client_id) as $membership) {
            $member_user_id = (int) ($membership['user_id'] ?? 0);
            $role = (string) ($membership['role'] ?? '');
            if ($member_user_id <= 0) {
                continue;
            }

            if ($role === 'client_admin') {
                $recipient_ids[] = $member_user_id;
                continue;
            }

            $bucket_id = (int) ($task['billing_bucket_id'] ?? 0);
            if ($bucket_id > 0 && in_array($member_user_id, WP_PQ_DB::get_job_member_ids($bucket_id), true)) {
                $recipient_ids[] = $member_user_id;
            }
        }

        return array_values(array_unique(array_filter($recipient_ids)));
    }

    private static function should_suppress_generic_client_email(array $task, int $user_id, string $event_key): bool
    {
        if (! in_array($event_key, ['task_created', 'task_approved', 'task_rejected', 'task_revision_requested', 'task_delivered', 'task_archived'], true)) {
            return false;
        }

        $client_id = (int) ($task['client_id'] ?? 0);
        if ($client_id <= 0) {
            return false;
        }

        foreach (WP_PQ_DB::get_client_memberships($client_id) as $membership) {
            if ((int) ($membership['user_id'] ?? 0) === $user_id) {
                return true;
            }
        }

        return false;
    }

    private static function send_client_status_update(int $task_id, string $new_status): void
    {
        $task = self::get_enriched_task($task_id);
        if (! $task) {
            return;
        }

        $recipient_ids = self::client_notification_recipient_ids($task);
        if (empty($recipient_ids)) {
            return;
        }

        foreach ($recipient_ids as $recipient_id) {
            if (! WP_PQ_Housekeeping::is_event_enabled($recipient_id, 'client_status_updates')) {
                continue;
            }

            $user = get_user_by('ID', $recipient_id);
            if (! $user || ! is_email($user->user_email)) {
                continue;
            }

            $body = self::build_client_status_update_body($task, $recipient_id, $new_status);
            if ($body === '') {
                continue;
            }

            wp_mail(
                $user->user_email,
                'Priority Portal update: ' . self::task_title($task),
                $body
            );
        }
    }

    private static function build_client_status_update_body(array $task, int $recipient_id, string $new_status): string
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $client_id = (int) ($task['client_id'] ?? 0);
        if ($client_id <= 0) {
            return '';
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tasks_table} WHERE client_id = %d ORDER BY queue_position ASC, id DESC",
            $client_id
        ), ARRAY_A);

        $visible_tasks = array_values(array_filter((array) $rows, static function (array $row) use ($recipient_id): bool {
            return self::can_access_task($row, $recipient_id);
        }));

        $groups = [
            'Awaiting you' => [],
            'Delivered' => [],
            'Needs clarification' => [],
            'Other changes' => [],
        ];

        foreach ($visible_tasks as $row) {
            $normalized = self::normalize_task_row($row);
            $status = (string) ($normalized['status'] ?? '');
            $is_action_owner = (int) ($normalized['action_owner_id'] ?? 0) === $recipient_id;

            if ($status === 'delivered') {
                $groups['Delivered'][] = $normalized;
            } elseif ($status === 'needs_clarification') {
                $groups['Needs clarification'][] = $normalized;
            } elseif ($is_action_owner && in_array($status, ['pending_approval', 'approved', 'in_progress', 'needs_review'], true)) {
                $groups['Awaiting you'][] = $normalized;
            } else {
                $groups['Other changes'][] = $normalized;
            }
        }

        $status_line = 'Task updated: "' . self::task_title($task) . '" is now ' . self::humanize_token($new_status) . '.';
        $summary = WP_PQ_Housekeeping::build_client_status_digest_body_for_api($recipient_id, $groups);

        return trim($status_line . "\n\n" . $summary);
    }

    private static function attach_author_names(array $rows): array
    {
        foreach ($rows as &$row) {
            $author_id = (int) ($row['author_id'] ?? 0);
            $user = $author_id > 0 ? get_user_by('ID', $author_id) : null;
            $row['author_name'] = $user ? (string) $user->display_name : 'Collaborator';
        }

        return $rows;
    }

    private static function build_event_message(array $task, string $event_key, int $recipient_id): array
    {
        $title = self::task_title($task);
        $priority = self::humanize_token((string) ($task['priority'] ?? 'normal'));
        $status = self::humanize_token((string) ($task['status'] ?? 'pending_approval'));
        $deadline = self::humanize_datetime((string) ($task['requested_deadline'] ?: $task['due_at'] ?: ''));
        $is_submitter = (int) ($task['submitter_id'] ?? 0) === $recipient_id;
        $is_action_owner = (int) ($task['action_owner_id'] ?? 0) === $recipient_id;
        $requester_name = (string) ($task['submitter_name'] ?? self::user_display_name((int) ($task['submitter_id'] ?? 0)));

        switch ($event_key) {
            case 'task_created':
                return [
                    'title' => $is_submitter ? 'Request received' : 'New request awaiting approval',
                    'body' => $is_submitter
                        ? '"' . $title . "\" is pending approval."
                        : '"' . $title . "\" was submitted and is awaiting approval.",
                ];
            case 'task_assigned':
                return [
                    'title' => $is_action_owner ? 'Task assigned to you' : 'Action owner updated',
                    'body' => $is_action_owner
                        ? '"' . $title . '" is now assigned to you' . ($requester_name ? ' by ' . $requester_name : '') . '.'
                        : 'The action owner for "' . $title . "\" was updated.",
                ];
            case 'task_approved':
                return [
                    'title' => 'Request approved',
                    'body' => '"' . $title . "\" is approved and ready to move forward.",
                ];
            case 'task_rejected':
                return [
                    'title' => 'Clarification requested',
                    'body' => '"' . $title . "\" was returned for clarification" . ($deadline ? ' before ' . $deadline : '') . '.',
                ];
            case 'task_revision_requested':
                return [
                    'title' => 'Revision requested',
                    'body' => 'Changes were requested on "' . $title . "\".",
                ];
            case 'task_delivered':
                return [
                    'title' => 'Work delivered',
                    'body' => 'A deliverable was posted to "' . $title . "\".",
                ];
            case 'task_archived':
                return [
                    'title' => 'Task archived',
                    'body' => '"' . $title . "\" has been archived.",
                ];
            case 'statement_batched':
                return [
                    'title' => 'Added to invoice draft',
                    'body' => '"' . $title . "\" was added to an invoice draft.",
                ];
            case 'task_reprioritized':
                return [
                    'title' => 'Priority changed to ' . $priority,
                    'body' => '"' . $title . "\" was reprioritized in the queue.",
                ];
            case 'task_schedule_changed':
                return [
                    'title' => 'Deadline updated',
                    'body' => '"' . $title . '"' . ($deadline ? ' is now targeting ' . $deadline : ' had its schedule adjusted') . '.',
                ];
            default:
                return [
                    'title' => self::humanize_token($event_key),
                    'body' => '"' . $title . '" is now ' . $status . '.',
                ];
        }
    }

    private static function emit_status_event(int $task_id, string $old_status, string $new_status): void
    {
        $old_status = WP_PQ_Workflow::normalize_status($old_status);
        $new_status = WP_PQ_Workflow::normalize_status($new_status);

        if ($new_status === 'approved') {
            self::emit_event($task_id, 'task_approved', 'Task approved', 'Your task was approved.');
        } elseif ($new_status === 'needs_clarification') {
            self::emit_event($task_id, 'task_rejected', 'Task needs clarification', 'Your task needs clarification and was returned.');
        } elseif ($new_status === 'in_progress' && in_array($old_status, ['needs_review', 'delivered'], true)) {
            self::emit_event($task_id, 'task_revision_requested', 'Revision requested', 'A revision was requested for this task.');
        } elseif ($new_status === 'delivered') {
            self::emit_event($task_id, 'task_delivered', 'Task delivered', 'Work product has been delivered.');
        } elseif ($new_status === 'archived') {
            self::emit_event($task_id, 'task_archived', 'Task archived', 'This task has been archived.');
        }
    }

    private static function enrich_task_rows(array $rows): array
    {
        global $wpdb;

        if (empty($rows)) {
            return [];
        }

        $task_ids = [];
        foreach ($rows as &$row) {
            $row = self::normalize_task_row($row);
            $task_ids[] = (int) $row['id'];
        }

        $task_ids = array_values(array_unique(array_filter($task_ids)));
        if (empty($task_ids)) {
            return $rows;
        }

        $ids_in = implode(',', $task_ids);
        $comments_table = $wpdb->prefix . 'pq_task_comments';
        $count_rows = $wpdb->get_results("SELECT task_id, COUNT(*) AS note_count FROM {$comments_table} WHERE task_id IN ({$ids_in}) GROUP BY task_id", ARRAY_A);
        $latest_rows = $wpdb->get_results("
            SELECT c.task_id, c.body
            FROM {$comments_table} c
            INNER JOIN (
                SELECT task_id, MAX(id) AS max_id
                FROM {$comments_table}
                WHERE task_id IN ({$ids_in})
                GROUP BY task_id
            ) latest ON latest.max_id = c.id
        ", ARRAY_A);

        $note_counts = [];
        foreach ($count_rows as $count_row) {
            $note_counts[(int) $count_row['task_id']] = (int) $count_row['note_count'];
        }

        $latest_notes = [];
        foreach ($latest_rows as $latest_row) {
            $latest_notes[(int) $latest_row['task_id']] = self::truncate_text(wp_strip_all_tags((string) $latest_row['body']), 120);
        }

        $statement_ids = array_values(array_unique(array_filter(array_map(static fn($row) => (int) ($row['statement_id'] ?? 0), $rows))));
        $statement_codes = [];
        if (! empty($statement_ids)) {
            $statement_ids_in = implode(',', $statement_ids);
            $statement_rows = $wpdb->get_results("SELECT id, statement_code FROM {$wpdb->prefix}pq_statements WHERE id IN ({$statement_ids_in})", ARRAY_A);
            foreach ($statement_rows as $statement_row) {
                $statement_codes[(int) $statement_row['id']] = (string) $statement_row['statement_code'];
            }
        }

        $bucket_ids = array_values(array_unique(array_filter(array_map(static fn($row) => (int) ($row['billing_bucket_id'] ?? 0), $rows))));
        $bucket_names = [];
        if (! empty($bucket_ids)) {
            $bucket_ids_in = implode(',', $bucket_ids);
            $bucket_rows = $wpdb->get_results("SELECT id, bucket_name, is_default FROM {$wpdb->prefix}pq_billing_buckets WHERE id IN ({$bucket_ids_in})", ARRAY_A);
            foreach ($bucket_rows as $bucket_row) {
                $bucket_name = trim((string) ($bucket_row['bucket_name'] ?? ''));
                $bucket_names[(int) $bucket_row['id']] = $bucket_name !== '' ? $bucket_name : (((int) ($bucket_row['is_default'] ?? 0) === 1) ? 'Main' : 'Job Bucket');
            }
        }

        foreach ($rows as &$row) {
            $task_id = (int) $row['id'];
            $row['note_count'] = $note_counts[$task_id] ?? 0;
            $row['latest_note_preview'] = $latest_notes[$task_id] ?? '';
            $row['statement_code'] = $statement_codes[(int) ($row['statement_id'] ?? 0)] ?? '';
            $row['bucket_name'] = $bucket_names[(int) ($row['billing_bucket_id'] ?? 0)] ?? 'Main';
        }

        return $rows;
    }

    private static function get_enriched_task(int $task_id): ?array
    {
        $task = self::get_task_row($task_id);
        if (! $task) {
            return null;
        }

        $rows = self::enrich_task_rows([$task]);
        return $rows[0] ?? null;
    }

    private static function task_title(array $task): string
    {
        $title = trim((string) ($task['title'] ?? ''));
        return $title !== '' ? $title : 'Untitled task';
    }

    private static function humanize_token(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (WP_PQ_Workflow::is_known_status($value)) {
            return WP_PQ_Workflow::label($value);
        }

        return ucwords(str_replace('_', ' ', $value));
    }

    private static function humanize_datetime(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if (! $timestamp) {
            return $value;
        }

        return wp_date('M j, Y g:i a', $timestamp);
    }

    private static function truncate_text(string $value, int $limit = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        if (strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, max(0, $limit - 1))) . '…';
    }

    private static function get_task_row(int $task_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_tasks';
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $task_id), ARRAY_A);

        return $task ?: null;
    }

    private static function purge_task(array $task): void
    {
        global $wpdb;

        $task_id = (int) ($task['id'] ?? 0);
        if ($task_id <= 0) {
            return;
        }

        $event_id = sanitize_text_field((string) ($task['google_event_id'] ?? ''));
        if ($event_id !== '') {
            self::delete_google_calendar_event($event_id);
        }

        $file_rows = $wpdb->get_results(
            $wpdb->prepare("SELECT media_id FROM {$wpdb->prefix}pq_task_files WHERE task_id = %d", $task_id),
            ARRAY_A
        );
        foreach ((array) $file_rows as $file_row) {
            $media_id = (int) ($file_row['media_id'] ?? 0);
            if ($media_id > 0) {
                wp_delete_attachment($media_id, true);
            }
        }

        $meeting_rows = $wpdb->get_results(
            $wpdb->prepare("SELECT event_id FROM {$wpdb->prefix}pq_task_meetings WHERE task_id = %d", $task_id),
            ARRAY_A
        );
        foreach ((array) $meeting_rows as $meeting_row) {
            $meeting_event_id = sanitize_text_field((string) ($meeting_row['event_id'] ?? ''));
            if ($meeting_event_id !== '') {
                self::delete_google_calendar_event($meeting_event_id);
            }
        }

        $wpdb->delete($wpdb->prefix . 'pq_statement_items', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_work_log_items', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_notifications', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_task_messages', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_task_comments', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_task_meetings', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_task_files', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_task_status_history', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_work_ledger_entries', ['task_id' => $task_id]);
        $wpdb->delete($wpdb->prefix . 'pq_tasks', ['id' => $task_id]);
    }

    private static function current_client_scope_id(int $user_id): int
    {
        $memberships = WP_PQ_DB::get_user_client_memberships($user_id);
        if (! empty($memberships)) {
            return (int) ($memberships[0]['client_id'] ?? 0);
        }

        if (self::user_has_role($user_id, 'pq_client')) {
            return WP_PQ_DB::get_or_create_client_id_for_user($user_id);
        }

        return 0;
    }

    private static function accessible_job_ids(int $user_id): array
    {
        global $wpdb;

        $memberships = WP_PQ_DB::get_user_client_memberships($user_id);
        if (empty($memberships)) {
            return [];
        }

        $admin_client_ids = [];
        foreach ($memberships as $membership) {
            if ((string) ($membership['role'] ?? '') === 'client_admin') {
                $admin_client_ids[] = (int) ($membership['client_id'] ?? 0);
            }
        }

        $bucket_ids = [];
        if (! empty($admin_client_ids)) {
            $bucket_ids = array_merge($bucket_ids, array_map('intval', (array) $wpdb->get_col(
                "SELECT id FROM {$wpdb->prefix}pq_billing_buckets WHERE client_id IN (" . implode(',', array_map('intval', $admin_client_ids)) . ')'
            )));
        }

        $bucket_ids = array_merge($bucket_ids, WP_PQ_DB::get_job_member_ids_for_user($user_id));

        return array_values(array_unique(array_filter(array_map('intval', $bucket_ids))));
    }

    private static function user_has_role(int $user_id, string $role): bool
    {
        $user = get_user_by('ID', $user_id);
        return $user ? in_array($role, (array) $user->roles, true) : false;
    }

    public static function can_access_task(array $task, int $user_id): bool
    {
        if (user_can($user_id, WP_PQ_Roles::CAP_VIEW_ALL) || user_can($user_id, WP_PQ_Roles::CAP_WORK)) {
            return true;
        }

        if ((int) $task['submitter_id'] === $user_id) {
            return true;
        }

        if ((int) ($task['client_user_id'] ?? 0) === $user_id) {
            return true;
        }

        if ((int) ($task['action_owner_id'] ?? 0) === $user_id) {
            return true;
        }

        $owners = json_decode((string) $task['owner_ids'], true);
        if (is_array($owners) && in_array($user_id, array_map('intval', $owners), true)) {
            return true;
        }

        $client_id = (int) ($task['client_id'] ?? 0);
        if ($client_id <= 0) {
            return false;
        }

        $memberships = WP_PQ_DB::get_user_client_memberships($user_id);
        foreach ($memberships as $membership) {
            if ((int) ($membership['client_id'] ?? 0) !== $client_id) {
                continue;
            }

            if ((string) ($membership['role'] ?? '') === 'client_admin') {
                return true;
            }

            $bucket_id = (int) ($task['billing_bucket_id'] ?? 0);
            if ($bucket_id > 0 && in_array($bucket_id, WP_PQ_DB::get_job_member_ids_for_user($user_id), true)) {
                return true;
            }
        }

        return false;
    }

    private static function can_delete_task(array $task, int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, WP_PQ_Roles::CAP_APPROVE)) {
            return true;
        }

        if ((int) ($task['submitter_id'] ?? 0) !== $user_id) {
            return false;
        }

        if (! in_array(WP_PQ_Workflow::normalize_status((string) ($task['status'] ?? '')), ['pending_approval', 'needs_clarification'], true)) {
            return false;
        }

        return (int) ($task['statement_id'] ?? 0) === 0 && (int) ($task['work_log_id'] ?? 0) === 0;
    }

    private static function default_billable_for_assignment(int $client_id, int $action_owner_id, bool $fallback = true): bool
    {
        if ($action_owner_id <= 0 || $client_id <= 0) {
            return $fallback;
        }

        return ! self::user_is_client_member($client_id, $action_owner_id);
    }

    private static function billing_sync_for_assignment(array $task, int $action_owner_id): array
    {
        $locked_statuses = ['batched', 'statement_sent', 'paid'];
        $current_status = (string) ($task['billing_status'] ?? '');
        if (in_array($current_status, $locked_statuses, true)) {
            return [];
        }

        $client_id = (int) ($task['client_id'] ?? 0);
        if ($action_owner_id <= 0 || $client_id <= 0) {
            return [];
        }

        $is_billable = self::default_billable_for_assignment($client_id, $action_owner_id, (int) ($task['is_billable'] ?? 1) === 1);
        return [
            'is_billable' => $is_billable ? 1 : 0,
            'billing_status' => $is_billable ? 'unbilled' : 'not_billable',
        ];
    }

    private static function user_is_client_member(int $client_id, int $user_id): bool
    {
        if ($client_id <= 0 || $user_id <= 0) {
            return false;
        }

        foreach (WP_PQ_DB::get_client_memberships($client_id) as $membership) {
            if ((int) ($membership['user_id'] ?? 0) === $user_id) {
                return true;
            }
        }

        return false;
    }

    private static function resolve_invoice_draft_entry_ids_from_tasks(array $task_ids)
    {
        global $wpdb;

        $task_ids = array_values(array_unique(array_filter(array_map('intval', $task_ids))));
        if (empty($task_ids)) {
            return [];
        }

        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $ids_in = implode(',', $task_ids);
        $rows = (array) $wpdb->get_results(
            "SELECT id, task_id FROM {$ledger_table} WHERE task_id IN ({$ids_in}) AND is_closed = 1",
            ARRAY_A
        );

        $entry_ids = [];
        $found_task_ids = [];
        foreach ($rows as $row) {
            $entry_id = (int) ($row['id'] ?? 0);
            $task_id = (int) ($row['task_id'] ?? 0);
            if ($entry_id > 0 && $task_id > 0) {
                $entry_ids[] = $entry_id;
                $found_task_ids[] = $task_id;
            }
        }

        $missing_task_ids = array_values(array_diff($task_ids, array_unique($found_task_ids)));
        if (! empty($missing_task_ids)) {
            return new WP_Error('pq_missing_ledger_entries', 'Only completed work that has been marked done can be added to an invoice draft.', ['status' => 422]);
        }

        return array_values(array_unique($entry_ids));
    }

    private static function load_invoice_draft_ledger_rows(array $entry_ids, int $user_id)
    {
        global $wpdb;

        $entry_ids = array_values(array_unique(array_filter(array_map('intval', $entry_ids))));
        if (empty($entry_ids)) {
            return [];
        }

        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $clients_table = $wpdb->prefix . 'pq_clients';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $ids_in = implode(',', $entry_ids);

        $rows = (array) $wpdb->get_results(
            "SELECT
                l.id AS ledger_entry_id,
                l.task_id,
                l.client_id,
                l.billing_bucket_id,
                l.title_snapshot,
                l.work_summary,
                l.owner_id,
                l.completion_date,
                l.billable,
                l.billing_mode,
                l.billing_category,
                l.is_closed,
                l.invoice_status,
                l.statement_month,
                l.invoice_draft_id,
                l.hours,
                l.rate,
                l.amount,
                COALESCE(t.client_user_id, c.primary_contact_user_id) AS client_user_id,
                t.status AS task_status,
                t.billing_status AS task_billing_status,
                t.statement_id AS task_statement_id,
                b.bucket_name,
                b.is_default
             FROM {$ledger_table} l
             LEFT JOIN {$tasks_table} t ON t.id = l.task_id
             LEFT JOIN {$clients_table} c ON c.id = l.client_id
             LEFT JOIN {$buckets_table} b ON b.id = l.billing_bucket_id
             WHERE l.id IN ({$ids_in})
               AND l.is_closed = 1",
            ARRAY_A
        );

        if (count($rows) !== count($entry_ids)) {
            return new WP_Error('pq_missing_ledger_entry', 'One or more completed work entries could not be loaded for invoice drafting.', ['status' => 404]);
        }

        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            foreach ($rows as $row) {
                $task_id = (int) ($row['task_id'] ?? 0);
                if ($task_id <= 0) {
                    continue;
                }
                $task = self::get_task_row($task_id);
                if (! $task || ! self::can_access_task($task, $user_id)) {
                    return new WP_Error('pq_forbidden_batch', 'You cannot add one or more selected work entries to an invoice draft.', ['status' => 403]);
                }
            }
        }

        foreach ($rows as $row) {
            if ((int) ($row['billable'] ?? 0) !== 1) {
                return new WP_Error('pq_non_billable_entry', 'Non-billable completed work cannot be added to an invoice draft.', ['status' => 422]);
            }

            if ((int) ($row['invoice_draft_id'] ?? 0) > 0) {
                return new WP_Error('pq_entry_already_drafted', 'A selected work entry is already linked to an active invoice draft.', ['status' => 422]);
            }

            $invoice_status = (string) ($row['invoice_status'] ?? 'unbilled');
            if ($invoice_status !== 'unbilled') {
                return new WP_Error('pq_invalid_invoice_status', 'Only unbilled completed work can be added to a new invoice draft.', ['status' => 422]);
            }
        }

        return $rows;
    }

    private static function build_invoice_draft_lines_from_ledger_entries(array $rows): array
    {
        global $wpdb;

        if (empty($rows)) {
            return [];
        }

        $bucket_ids = [];
        foreach ($rows as &$row) {
            $client_id = (int) ($row['client_id'] ?? 0);
            $bucket_id = (int) ($row['billing_bucket_id'] ?? 0);
            if ($bucket_id <= 0 && $client_id > 0) {
                $bucket_id = WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);
                $row['billing_bucket_id'] = $bucket_id;
            }
            if ($bucket_id > 0) {
                $bucket_ids[] = $bucket_id;
            }
        }
        unset($row);

        $bucket_names = [];
        $bucket_ids = array_values(array_unique(array_filter(array_map('intval', $bucket_ids))));
        if (! empty($bucket_ids)) {
            $bucket_rows = $wpdb->get_results(
                "SELECT id, bucket_name FROM {$wpdb->prefix}pq_billing_buckets WHERE id IN (" . implode(',', $bucket_ids) . ')',
                ARRAY_A
            );
            foreach ((array) $bucket_rows as $bucket_row) {
                $bucket_names[(int) ($bucket_row['id'] ?? 0)] = trim((string) ($bucket_row['bucket_name'] ?? ''));
            }
        }

        $groups = [];
        foreach ($rows as $row) {
            $bucket_id = (int) ($row['billing_bucket_id'] ?? 0);
            $bucket_name = $bucket_id > 0 ? ($bucket_names[$bucket_id] ?? 'Job') : 'General';
            $billing_mode = (string) ($row['billing_mode'] ?? 'fixed_fee');
            $task_id = (int) ($row['task_id'] ?? 0);
            $entry_id = (int) ($row['ledger_entry_id'] ?? 0);

            if ($billing_mode === 'pass_through_expense') {
                $description = trim((string) ($row['work_summary'] ?? ''));
                if ($description === '') {
                    $description = trim((string) ($row['title_snapshot'] ?? ''));
                }
                if ($description === '') {
                    $description = 'Pass-through expense';
                }

                $groups['entry:' . $entry_id] = [
                    'line_type' => 'pass_through_expense',
                    'source_kind' => 'task',
                    'description' => $description,
                    'quantity' => 1,
                    'unit' => 'expense',
                    'unit_rate' => null,
                    'line_amount' => self::sanitize_decimal_value($row['amount'] ?? null, 2),
                    'billing_bucket_id' => $bucket_id > 0 ? $bucket_id : null,
                    'linked_task_ids' => $task_id > 0 ? [$task_id] : [],
                    'source_snapshot' => [[
                        'ledger_entry_id' => $entry_id,
                        'task_id' => $task_id,
                        'title' => (string) ($row['title_snapshot'] ?? ''),
                        'work_summary' => (string) ($row['work_summary'] ?? ''),
                        'billing_mode' => $billing_mode,
                        'completion_date' => (string) ($row['completion_date'] ?? ''),
                        'amount' => self::sanitize_decimal_value($row['amount'] ?? null, 2),
                    ]],
                    'notes' => '',
                ];
                continue;
            }

            $group_key = $bucket_id . ':' . $billing_mode;
            if (! isset($groups[$group_key])) {
                $groups[$group_key] = [
                    'line_type' => 'task_rollup',
                    'source_kind' => 'task',
                    'description' => $billing_mode === 'hourly'
                        ? 'Completed hourly work for ' . $bucket_name
                        : 'Completed work for ' . $bucket_name,
                    'quantity' => 0.0,
                    'unit' => $billing_mode === 'hourly' ? 'hours' : 'tasks',
                    'unit_rate' => null,
                    'line_amount' => null,
                    'billing_bucket_id' => $bucket_id > 0 ? $bucket_id : null,
                    'linked_task_ids' => [],
                    'source_snapshot' => [],
                    'notes' => '',
                ];
            }

            $groups[$group_key]['quantity'] += $billing_mode === 'hourly'
                ? ((float) ($row['hours'] ?? 0) > 0 ? (float) $row['hours'] : 1.0)
                : 1.0;
            if ($task_id > 0) {
                $groups[$group_key]['linked_task_ids'][] = $task_id;
            }
            $groups[$group_key]['source_snapshot'][] = [
                'ledger_entry_id' => $entry_id,
                'task_id' => $task_id,
                'title' => (string) ($row['title_snapshot'] ?? ''),
                'work_summary' => (string) ($row['work_summary'] ?? ''),
                'billing_mode' => $billing_mode,
                'completion_date' => (string) ($row['completion_date'] ?? ''),
                'hours' => self::sanitize_decimal_value($row['hours'] ?? null, 2),
                'amount' => self::sanitize_decimal_value($row['amount'] ?? null, 2),
            ];
        }

        $lines = [];
        $sort_order = 1;
        foreach ($groups as $group) {
            $task_ids = array_values(array_unique(array_filter(array_map('intval', (array) $group['linked_task_ids']))));
            $source_snapshot = [
                'task_ids' => $task_ids,
                'suggested_description' => (string) $group['description'],
                'suggested_quantity' => (float) $group['quantity'],
                'suggested_unit' => (string) $group['unit'],
                'entries' => $group['source_snapshot'],
            ];

            $lines[] = [
                'line_type' => (string) $group['line_type'],
                'source_kind' => (string) $group['source_kind'],
                'description' => (string) $group['description'],
                'quantity' => (float) $group['quantity'],
                'unit' => (string) $group['unit'],
                'unit_rate' => null,
                'line_amount' => $group['line_amount'],
                'billing_bucket_id' => $group['billing_bucket_id'],
                'linked_task_ids' => $task_ids,
                'source_snapshot' => $source_snapshot,
                'notes' => '',
                'sort_order' => $sort_order++,
            ];
        }

        return $lines;
    }

    private static function insert_statement_line(int $statement_id, array $line, string $now): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pq_statement_lines';
        $quantity = isset($line['quantity']) && $line['quantity'] !== '' ? number_format((float) $line['quantity'], 2, '.', '') : null;
        $unit_rate = isset($line['unit_rate']) && $line['unit_rate'] !== '' ? number_format((float) $line['unit_rate'], 2, '.', '') : null;
        $line_amount = isset($line['line_amount']) && $line['line_amount'] !== '' ? number_format((float) $line['line_amount'], 2, '.', '') : null;
        $linked_task_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($line['linked_task_ids'] ?? [])))));

        $wpdb->insert($table, [
            'statement_id' => $statement_id,
            'line_type' => sanitize_key((string) ($line['line_type'] ?? 'manual_adjustment')),
            'source_kind' => sanitize_key((string) ($line['source_kind'] ?? 'manual')),
            'description' => sanitize_textarea_field((string) ($line['description'] ?? '')),
            'quantity' => $quantity,
            'unit' => sanitize_text_field((string) ($line['unit'] ?? '')),
            'unit_rate' => $unit_rate,
            'line_amount' => $line_amount,
            'billing_bucket_id' => (int) ($line['billing_bucket_id'] ?? 0) > 0 ? (int) $line['billing_bucket_id'] : null,
            'linked_task_ids' => ! empty($linked_task_ids) ? wp_json_encode($linked_task_ids) : null,
            'source_snapshot' => ! empty($line['source_snapshot']) ? wp_json_encode($line['source_snapshot']) : null,
            'notes' => sanitize_textarea_field((string) ($line['notes'] ?? '')),
            'sort_order' => max(0, (int) ($line['sort_order'] ?? 0)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function get_statement_line_rows(int $statement_id): array
    {
        global $wpdb;

        if ($statement_id <= 0) {
            return [];
        }

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pq_statement_lines WHERE statement_id = %d ORDER BY sort_order ASC, id ASC",
            $statement_id
        ), ARRAY_A);
    }

    public static function recalculate_statement_total(int $statement_id): float
    {
        global $wpdb;

        if ($statement_id <= 0) {
            return 0.0;
        }

        $total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(COALESCE(line_amount, 0)), 0) FROM {$wpdb->prefix}pq_statement_lines WHERE statement_id = %d",
            $statement_id
        ));

        $wpdb->update($wpdb->prefix . 'pq_statements', [
            'total_amount' => number_format($total, 2, '.', ''),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $statement_id]);

        return $total;
    }

    public static function delete_statement_line(int $statement_id, int $line_id, int $user_id = 0)
    {
        global $wpdb;

        if ($line_id <= 0 || $statement_id <= 0) {
            return new WP_Error('pq_missing_line', 'Invoice draft line not found.', ['status' => 404]);
        }

        $line = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pq_statement_lines WHERE id = %d AND statement_id = %d",
            $line_id,
            $statement_id
        ), ARRAY_A);
        if (! $line) {
            return new WP_Error('pq_missing_line', 'Invoice draft line not found.', ['status' => 404]);
        }

        foreach (self::statement_line_task_ids($line) as $task_id) {
            self::remove_task_from_statement_draft($statement_id, $task_id, $user_id);
        }

        $wpdb->delete($wpdb->prefix . 'pq_statement_lines', ['id' => $line_id, 'statement_id' => $statement_id]);
        self::recalculate_statement_total($statement_id);

        return true;
    }

    public static function remove_task_from_statement_draft(int $statement_id, int $task_id, int $user_id = 0)
    {
        global $wpdb;

        if ($statement_id <= 0 || $task_id <= 0) {
            return new WP_Error('pq_missing_draft_task', 'Invoice draft task linkage not found.', ['status' => 404]);
        }

        $items_table = $wpdb->prefix . 'pq_statement_items';
        $deleted = $wpdb->delete($items_table, [
            'statement_id' => $statement_id,
            'task_id' => $task_id,
        ]);
        if ($deleted === false) {
            return new WP_Error('pq_delete_draft_task_failed', 'Failed to remove the task from the invoice draft.', ['status' => 500]);
        }

        $lines = self::get_statement_line_rows($statement_id);
        foreach ($lines as $line) {
            $task_ids = self::statement_line_task_ids($line);
            if (! in_array($task_id, $task_ids, true)) {
                continue;
            }

            $task_ids = array_values(array_filter($task_ids, static fn(int $candidate): bool => $candidate !== $task_id));
            $wpdb->update($wpdb->prefix . 'pq_statement_lines', [
                'linked_task_ids' => ! empty($task_ids) ? wp_json_encode($task_ids) : null,
                'updated_at' => current_time('mysql', true),
            ], ['id' => (int) $line['id']]);
        }

        self::restore_task_invoice_eligibility($task_id, $statement_id);
        return true;
    }

    public static function delete_statement_draft(int $statement_id, int $user_id = 0)
    {
        global $wpdb;

        if ($statement_id <= 0) {
            return new WP_Error('pq_missing_statement', 'Invoice draft not found.', ['status' => 404]);
        }

        $items_table = $wpdb->prefix . 'pq_statement_items';
        $task_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT task_id FROM {$items_table} WHERE statement_id = %d",
            $statement_id
        )));

        foreach ($task_ids as $task_id) {
            self::remove_task_from_statement_draft($statement_id, $task_id, $user_id);
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}pq_work_ledger_entries
             SET invoice_status = CASE WHEN billable = 1 THEN 'unbilled' ELSE 'written_off' END,
                 invoice_draft_id = NULL,
                 updated_at = %s
             WHERE invoice_draft_id = %d",
            current_time('mysql', true),
            $statement_id
        ));

        $wpdb->delete($wpdb->prefix . 'pq_statement_lines', ['statement_id' => $statement_id]);
        $wpdb->delete($items_table, ['statement_id' => $statement_id]);
        $wpdb->delete($wpdb->prefix . 'pq_statements', ['id' => $statement_id]);

        return true;
    }

    private static function restore_task_invoice_eligibility(int $task_id, int $removed_statement_id = 0): void
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $items_table = $wpdb->prefix . 'pq_statement_items';
        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks_table} WHERE id = %d", $task_id), ARRAY_A);
        if (! $task) {
            return;
        }

        $ledger_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$ledger_table} WHERE task_id = %d",
            $task_id
        ), ARRAY_A);

        $active_statement_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT statement_id FROM {$items_table} WHERE task_id = %d ORDER BY id DESC LIMIT 1",
            $task_id
        ));

        if ($active_statement_id > 0) {
            if ($ledger_entry) {
                $wpdb->update($ledger_table, [
                    'invoice_status' => 'invoiced',
                    'invoice_draft_id' => $active_statement_id,
                    'updated_at' => current_time('mysql', true),
                ], ['id' => (int) $ledger_entry['id']]);
            }
            $wpdb->update($tasks_table, [
                'billing_status' => 'batched',
                'statement_id' => $active_statement_id,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $task_id]);
            return;
        }

        if ($ledger_entry && ($removed_statement_id <= 0 || (int) ($ledger_entry['invoice_draft_id'] ?? 0) === $removed_statement_id)) {
            $wpdb->update($ledger_table, [
                'invoice_status' => (int) ($ledger_entry['billable'] ?? 1) === 1 ? 'unbilled' : 'written_off',
                'invoice_draft_id' => null,
                'updated_at' => current_time('mysql', true),
            ], ['id' => (int) $ledger_entry['id']]);
        }

        $wpdb->update($tasks_table, [
            'billing_status' => (int) ($task['is_billable'] ?? 1) === 1 ? 'unbilled' : 'not_billable',
            'statement_id' => null,
            'statement_batched_at' => null,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $task_id]);
    }

    private static function allowed_completion_billing_modes(): array
    {
        return ['hourly', 'fixed_fee', 'pass_through_expense', 'non_billable'];
    }

    private static function normalize_completion_payload(array $payload): array
    {
        $billing_mode = sanitize_key((string) ($payload['billing_mode'] ?? ''));
        if (! in_array($billing_mode, self::allowed_completion_billing_modes(), true)) {
            $billing_mode = '';
        }

        return [
            'billing_mode' => $billing_mode,
            'billing_category' => sanitize_text_field((string) ($payload['billing_category'] ?? '')),
            'work_summary' => trim(sanitize_textarea_field((string) ($payload['work_summary'] ?? ''))),
            'hours' => self::sanitize_decimal_value($payload['hours'] ?? null, 2),
            'rate' => self::sanitize_decimal_value($payload['rate'] ?? null, 2),
            'amount' => self::sanitize_decimal_value($payload['amount'] ?? null, 2),
            'non_billable_reason' => trim(sanitize_textarea_field((string) ($payload['non_billable_reason'] ?? ''))),
            'expense_reference' => sanitize_text_field((string) ($payload['expense_reference'] ?? '')),
        ];
    }

    private static function validate_completion_payload(array $task, array $payload)
    {
        $billing_mode = $payload['billing_mode'] !== ''
            ? $payload['billing_mode']
            : ((string) ($task['billing_mode'] ?? '') !== '' ? sanitize_key((string) $task['billing_mode']) : ((int) ($task['is_billable'] ?? 1) === 1 ? 'fixed_fee' : 'non_billable'));
        if (! in_array($billing_mode, self::allowed_completion_billing_modes(), true)) {
            return new WP_Error('pq_invalid_billing_mode', 'Choose a valid billing mode before marking the task done.', ['status' => 422]);
        }

        $client_id = (int) ($task['client_id'] ?? 0);
        $billing_bucket_id = (int) ($task['billing_bucket_id'] ?? 0);
        if ($client_id <= 0 || $billing_bucket_id <= 0) {
            return new WP_Error('pq_missing_completion_context', 'Tasks must have a client and job before they can be marked done.', ['status' => 422]);
        }

        $work_summary = trim((string) $payload['work_summary']);
        if ($work_summary === '') {
            $work_summary = trim((string) ($task['work_summary'] ?? ''));
        }
        if ($work_summary === '') {
            $work_summary = trim((string) ($task['description'] ?? ''));
        }
        if ($work_summary === '') {
            $work_summary = trim((string) ($task['title'] ?? ''));
        }
        if ($work_summary === '') {
            return new WP_Error('pq_missing_work_summary', 'Add a work summary before marking this task done.', ['status' => 422]);
        }

        $billing_category = trim((string) $payload['billing_category']);
        if ($billing_category === '') {
            $billing_category = trim((string) ($task['billing_category'] ?? ''));
        }

        $is_billable = $billing_mode !== 'non_billable';
        $billing_locked = (int) ($task['statement_id'] ?? 0) > 0 || in_array((string) ($task['billing_status'] ?? ''), ['batched', 'statement_sent', 'paid'], true);
        if ($billing_locked && ! $is_billable) {
            return new WP_Error('pq_billing_mode_conflict', 'This task is already tied to invoicing and cannot be marked non-billable now.', ['status' => 422]);
        }

        if ($is_billable && $billing_category === '') {
            return new WP_Error('pq_missing_billing_category', 'Add a billing category before marking this task done.', ['status' => 422]);
        }

        if ($billing_mode === 'hourly' && (float) ($payload['hours'] ?? 0) <= 0) {
            return new WP_Error('pq_missing_hours', 'Hourly work requires hours before the task can be marked done.', ['status' => 422]);
        }

        if ($billing_mode === 'pass_through_expense' && (float) ($payload['amount'] ?? 0) <= 0 && trim((string) $payload['expense_reference']) === '') {
            return new WP_Error('pq_missing_expense_reference', 'Pass-through expenses need an amount or expense reference before the task can be marked done.', ['status' => 422]);
        }

        return [
            'billable' => $is_billable,
            'billing_mode' => $billing_mode,
            'billing_category' => $billing_category,
            'work_summary' => $work_summary,
            'hours' => $payload['hours'],
            'rate' => $payload['rate'],
            'amount' => $payload['amount'],
            'non_billable_reason' => trim((string) $payload['non_billable_reason']),
            'expense_reference' => trim((string) $payload['expense_reference']),
        ];
    }

    private static function sanitize_decimal_value($value, int $precision = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return number_format((float) $raw, $precision, '.', '');
    }

    private static function completion_billing_status(array $task, array $validated): string
    {
        $current_status = (string) ($task['billing_status'] ?? '');
        if (! empty($validated['billable'])) {
            if (in_array($current_status, ['batched', 'statement_sent', 'paid'], true)) {
                return $current_status;
            }
            return 'unbilled';
        }

        return in_array($current_status, ['batched', 'statement_sent', 'paid'], true)
            ? $current_status
            : 'not_billable';
    }

    private static function get_work_ledger_entry_by_task_id(int $task_id): ?array
    {
        global $wpdb;

        if ($task_id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'pq_work_ledger_entries';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d LIMIT 1", $task_id), ARRAY_A);

        return $row ?: null;
    }

    private static function ledger_payload_from_task(array $task): array
    {
        $completion_date = (string) ($task['done_at'] ?? '');
        if ($completion_date === '') {
            $completion_date = (string) ($task['completed_at'] ?? '');
        }
        if ($completion_date === '') {
            $completion_date = (string) ($task['delivered_at'] ?? '');
        }
        if ($completion_date === '') {
            $completion_date = (string) ($task['updated_at'] ?? current_time('mysql', true));
        }

        $work_summary = trim((string) ($task['work_summary'] ?? ''));
        if ($work_summary === '') {
            $work_summary = trim((string) ($task['description'] ?? ''));
        }
        if ($work_summary === '') {
            $work_summary = trim((string) ($task['title'] ?? ''));
        }

        return [
            'task_id' => (int) ($task['id'] ?? 0),
            'client_id' => (int) ($task['client_id'] ?? 0) > 0 ? (int) $task['client_id'] : null,
            'billing_bucket_id' => (int) ($task['billing_bucket_id'] ?? 0) > 0 ? (int) $task['billing_bucket_id'] : null,
            'title_snapshot' => (string) ($task['title'] ?? ''),
            'work_summary' => $work_summary,
            'owner_id' => (int) ($task['action_owner_id'] ?? 0) > 0 ? (int) $task['action_owner_id'] : ((int) ($task['submitter_id'] ?? 0) > 0 ? (int) $task['submitter_id'] : null),
            'completion_date' => $completion_date,
            'billable' => (int) ($task['is_billable'] ?? 1) === 1 ? 1 : 0,
            'billing_mode' => (string) ($task['billing_mode'] ?? '') !== '' ? (string) $task['billing_mode'] : (((int) ($task['is_billable'] ?? 1) === 1) ? 'fixed_fee' : 'non_billable'),
            'billing_category' => (string) ($task['billing_category'] ?? '') !== '' ? (string) $task['billing_category'] : 'general',
            'is_closed' => 1,
            'invoice_status' => self::ledger_invoice_status_from_task($task),
            'statement_month' => substr($completion_date, 0, 7),
            'invoice_draft_id' => (int) ($task['statement_id'] ?? 0) > 0 ? (int) $task['statement_id'] : null,
            'hours' => self::sanitize_decimal_value($task['hours'] ?? null, 2),
            'rate' => self::sanitize_decimal_value($task['rate'] ?? null, 2),
            'amount' => self::sanitize_decimal_value($task['amount'] ?? null, 2),
        ];
    }

    private static function ledger_invoice_status_from_task(array $task): string
    {
        if ((int) ($task['is_billable'] ?? 1) !== 1) {
            return 'written_off';
        }

        $billing_status = (string) ($task['billing_status'] ?? 'unbilled');
        if ($billing_status === 'paid') {
            return 'paid';
        }

        if ((int) ($task['statement_id'] ?? 0) > 0 || in_array($billing_status, ['batched', 'statement_sent'], true)) {
            return 'invoiced';
        }

        if ($billing_status === 'not_billable') {
            return 'written_off';
        }

        return 'unbilled';
    }

    private static function upsert_work_ledger_entry(array $task)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pq_work_ledger_entries';
        $payload = self::ledger_payload_from_task($task);
        $existing = self::get_work_ledger_entry_by_task_id((int) ($task['id'] ?? 0));
        $now = current_time('mysql', true);

        if ($existing) {
            $existing_status = (string) ($existing['invoice_status'] ?? '');
            if (in_array($existing_status, ['invoiced', 'paid'], true)) {
                return new WP_Error('pq_locked_ledger_entry', 'This task already has invoiced ledger data and needs manual reconciliation before it can be finalized again.', ['status' => 409]);
            }

            $updated = $wpdb->update($table, array_merge($payload, [
                'updated_at' => $now,
            ]), ['id' => (int) $existing['id']]);
            if ($updated === false) {
                return new WP_Error('pq_ledger_update_failed', 'The work ledger entry could not be updated.', ['status' => 500]);
            }

            return self::get_work_ledger_entry_by_task_id((int) ($task['id'] ?? 0));
        }

        $inserted = $wpdb->insert($table, array_merge($payload, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));
        if (! $inserted) {
            return new WP_Error('pq_ledger_insert_failed', 'The work ledger entry could not be created.', ['status' => 500]);
        }

        return self::get_work_ledger_entry_by_task_id((int) ($task['id'] ?? 0));
    }

    private static function statement_line_task_ids(array $line): array
    {
        $task_ids = json_decode((string) ($line['linked_task_ids'] ?? ''), true);
        if (! is_array($task_ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $task_ids))));
    }

    private static function single_bucket_id_from_tasks(array $rows): int
    {
        return self::single_bucket_id_from_rows($rows, 'billing_bucket_id');
    }

    private static function single_bucket_id_from_rows(array $rows, string $field): int
    {
        $bucket_ids = [];
        foreach ($rows as $row) {
            $bucket_id = (int) ($row[$field] ?? 0);
            if ($bucket_id > 0) {
                $bucket_ids[] = $bucket_id;
            }
        }

        $bucket_ids = array_values(array_unique($bucket_ids));
        return count($bucket_ids) === 1 ? (int) $bucket_ids[0] : 0;
    }

    public static function normalize_statement_month(string $statement_month = ''): string
    {
        $statement_month = trim($statement_month);
        if (! preg_match('/^\d{4}-\d{2}$/', $statement_month)) {
            return wp_date('Y-m');
        }

        return $statement_month;
    }

    public static function normalize_rollup_date(string $value = ''): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        return $value;
    }

    private static function generate_statement_code(string $statement_month = ''): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pq_statements';
        $statement_month = self::normalize_statement_month($statement_month);
        $month = str_replace('-', '', $statement_month);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE statement_month = %s",
            $statement_month
        ));

        return 'STM-' . $month . '-' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    private static function generate_work_log_code(string $seed_date = ''): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pq_work_logs';
        $month = preg_match('/^\d{4}-\d{2}-\d{2}$/', $seed_date) ? substr($seed_date, 0, 7) : wp_date('Y-m');
        $code_month = str_replace('-', '', $month);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s",
            $month
        ));

        return 'LOG-' . $code_month . '-' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    private static function task_rollup_date(array $task): string
    {
        $value = (string) ($task['delivered_at'] ?? '');
        if ($value === '') {
            $value = (string) ($task['updated_at'] ?? '');
        }
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 10);
    }

    private static function ledger_rollup_date(array $entry): string
    {
        $value = (string) ($entry['completion_date'] ?? '');
        if ($value === '') {
            $value = (string) ($entry['updated_at'] ?? '');
        }
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 10);
    }

    private static function insert_history_note(string $history_table, int $task_id, string $status, int $user_id, string $note): void
    {
        global $wpdb;

        $wpdb->insert($history_table, [
            'task_id' => $task_id,
            'old_status' => $status,
            'new_status' => $status,
            'changed_by' => $user_id,
            'reason_code' => null,
            'note' => $note,
            'metadata' => null,
            'created_at' => current_time('mysql', true),
        ]);
    }

    private static function insert_status_history(string $history_table, int $task_id, string $old_status, string $new_status, int $user_id, string $note = '', string $reason_code = '', ?array $metadata = null): void
    {
        global $wpdb;

        $clean_note = trim($note);
        $wpdb->insert($history_table, [
            'task_id' => $task_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'changed_by' => $user_id,
            'reason_code' => $reason_code !== '' ? $reason_code : null,
            'note' => $clean_note !== '' ? sanitize_textarea_field($clean_note) : null,
            'metadata' => ! empty($metadata) ? wp_json_encode($metadata) : null,
            'created_at' => current_time('mysql', true),
        ]);
    }

    private static function transition_reason_code(string $from_status, string $to_status): string
    {
        $from_status = WP_PQ_Workflow::normalize_status($from_status);
        $to_status = WP_PQ_Workflow::normalize_status($to_status);

        if ($to_status === 'approved') {
            return 'approved';
        }

        if ($to_status === 'done') {
            return 'marked_done';
        }

        if ($to_status === 'archived') {
            return 'archived';
        }

        if ($to_status === 'needs_clarification') {
            return $from_status === 'delivered' ? 'feedback_incomplete' : 'clarification_requested';
        }

        if ($to_status === 'needs_review' && $from_status === 'delivered') {
            return 'internal_correction';
        }

        if ($to_status === 'in_progress' && in_array($from_status, ['needs_review', 'delivered'], true)) {
            return 'revisions_requested';
        }

        return '';
    }

    private static function is_billing_locked_reopen(array $task, string $from_status, string $to_status): bool
    {
        $from_status = WP_PQ_Workflow::normalize_status($from_status);
        $to_status = WP_PQ_Workflow::normalize_status($to_status);

        if ($from_status !== 'delivered') {
            return false;
        }

        if (! in_array($to_status, ['in_progress', 'needs_clarification', 'needs_review'], true)) {
            return false;
        }

        return (int) ($task['statement_id'] ?? 0) > 0
            || in_array((string) ($task['billing_status'] ?? ''), ['batched', 'statement_sent', 'paid'], true);
    }

    private static function store_task_message(int $task_id, int $author_id, string $body, ?array $task = null): void
    {
        global $wpdb;

        $clean_body = trim($body);
        if ($clean_body === '') {
            return;
        }

        $task = $task ?: self::get_task_row($task_id);
        if (! $task) {
            return;
        }

        $table = $wpdb->prefix . 'pq_task_messages';
        $wpdb->insert($table, [
            'task_id' => $task_id,
            'author_id' => $author_id,
            'body' => sanitize_textarea_field($clean_body),
            'created_at' => current_time('mysql', true),
        ]);

        self::notify_mentions($task_id, $task, $clean_body, $author_id);
    }

    private static function shift_priority(string $priority, string $direction): string
    {
        $ladder = ['low', 'normal', 'high', 'urgent'];
        $index = array_search($priority, $ladder, true);

        if ($index === false) {
            return 'normal';
        }

        if ($direction === 'down') {
            return $ladder[max(0, $index - 1)];
        }

        if ($direction === 'up') {
            return $ladder[min(count($ladder) - 1, $index + 1)];
        }

        return $ladder[$index];
    }

    private static function sort_task_rows(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $bucket_cmp = self::task_bucket_rank($a) <=> self::task_bucket_rank($b);
            if ($bucket_cmp !== 0) {
                return $bucket_cmp;
            }

            $priority_cmp = self::task_priority_rank($a) <=> self::task_priority_rank($b);
            if ($priority_cmp !== 0) {
                return $priority_cmp;
            }

            $deadline_cmp = self::task_deadline_timestamp($a) <=> self::task_deadline_timestamp($b);
            if ($deadline_cmp !== 0) {
                return $deadline_cmp;
            }

            $queue_cmp = ((int) ($a['queue_position'] ?? 0)) <=> ((int) ($b['queue_position'] ?? 0));
            if ($queue_cmp !== 0) {
                return $queue_cmp;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        return $rows;
    }

    private static function task_priority_rank(array $task): int
    {
        $priority = (string) ($task['priority'] ?? 'normal');
        $ranks = [
            'urgent' => 0,
            'high' => 1,
            'normal' => 2,
            'low' => 3,
        ];

        return $ranks[$priority] ?? 2;
    }

    private static function task_bucket_rank(array $task): int
    {
        $deadline = self::task_primary_deadline($task);
        if (! $deadline) {
            return 3;
        }

        $now = new DateTimeImmutable('now', wp_timezone());
        $today_start = $now->setTime(0, 0, 0);
        $tomorrow_start = $today_start->modify('+1 day');
        $due_soon_end = $today_start->modify('+8 days');

        if ($deadline < $now) {
            return 0;
        }

        if ($deadline >= $today_start && $deadline < $tomorrow_start) {
            return 1;
        }

        if ($deadline < $due_soon_end) {
            return 2;
        }

        return 3;
    }

    private static function hydrate_file_row(array $row): array
    {
        $media_id = (int) ($row['media_id'] ?? 0);
        $row['media_url'] = $media_id > 0 ? wp_get_attachment_url($media_id) : '';
        $row['filename'] = $media_id > 0 ? wp_basename((string) get_attached_file($media_id)) : '';
        return $row;
    }

    private static function task_deadline_timestamp(array $task): int
    {
        $deadline = self::task_primary_deadline($task);
        return $deadline ? $deadline->getTimestamp() : PHP_INT_MAX;
    }

    private static function task_primary_deadline(array $task): ?DateTimeImmutable
    {
        $value = (string) ($task['requested_deadline'] ?: $task['due_at'] ?: '');
        if ($value === '') {
            return null;
        }

        try {
            $utc = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $utc->setTimezone(wp_timezone());
        } catch (Exception $e) {
            return null;
        }
    }

    private static function normalize_task_row(array $row): array
    {
        $row['status'] = WP_PQ_Workflow::normalize_status((string) ($row['status'] ?? 'pending_approval'));
        $row['owner_ids'] = $row['owner_ids'] ? json_decode($row['owner_ids'], true) : [];
        $row['needs_meeting'] = (bool) $row['needs_meeting'];
        $row['is_billable'] = ! isset($row['is_billable']) ? true : ((int) $row['is_billable'] === 1);
        $row['owner_names'] = [];
        $row['client_id'] = isset($row['client_id']) ? (int) $row['client_id'] : 0;
        $row['client_user_id'] = isset($row['client_user_id']) ? (int) $row['client_user_id'] : 0;
        $row['action_owner_id'] = isset($row['action_owner_id']) ? (int) $row['action_owner_id'] : 0;
        $row['revision_count'] = isset($row['revision_count']) ? (int) $row['revision_count'] : 0;
        $row['billing_status'] = (string) ($row['billing_status'] ?? ($row['is_billable'] ? 'unbilled' : 'not_billable'));
        $row['billing_mode'] = (string) ($row['billing_mode'] ?? '');
        $row['billing_category'] = (string) ($row['billing_category'] ?? '');
        $row['work_summary'] = (string) ($row['work_summary'] ?? '');
        $row['hours'] = isset($row['hours']) && $row['hours'] !== null ? (string) $row['hours'] : '';
        $row['rate'] = isset($row['rate']) && $row['rate'] !== null ? (string) $row['rate'] : '';
        $row['amount'] = isset($row['amount']) && $row['amount'] !== null ? (string) $row['amount'] : '';
        $row['non_billable_reason'] = (string) ($row['non_billable_reason'] ?? '');
        $row['expense_reference'] = (string) ($row['expense_reference'] ?? '');
        $row['done_at'] = (string) ($row['done_at'] ?? '');
        $row['archived_at'] = (string) ($row['archived_at'] ?? '');
        $row['statement_id'] = isset($row['statement_id']) ? (int) $row['statement_id'] : 0;
        $row['statement_code'] = (string) ($row['statement_code'] ?? '');
        $row['statement_batched_at'] = (string) ($row['statement_batched_at'] ?? '');

        foreach ((array) $row['owner_ids'] as $owner_id) {
            $user = get_user_by('ID', (int) $owner_id);
            if ($user) {
                $row['owner_names'][] = (string) $user->display_name;
            }
        }

        $submitter = isset($row['submitter_id']) ? get_user_by('ID', (int) $row['submitter_id']) : null;
        $row['submitter_name'] = $submitter ? (string) $submitter->display_name : '';
        $row['submitter_email'] = ($submitter && is_email($submitter->user_email)) ? (string) $submitter->user_email : '';
        $client = $row['client_user_id'] > 0 ? get_user_by('ID', $row['client_user_id']) : null;
        $row['client_name'] = $client ? (string) $client->display_name : '';
        $row['client_email'] = ($client && is_email($client->user_email)) ? (string) $client->user_email : '';
        $row['client_account_name'] = $row['client_id'] > 0 ? WP_PQ_DB::get_client_name($row['client_id']) : ($row['client_name'] ?: '');
        $action_owner = $row['action_owner_id'] > 0 ? get_user_by('ID', $row['action_owner_id']) : null;
        $row['action_owner_name'] = $action_owner ? (string) $action_owner->display_name : '';
        $row['action_owner_email'] = ($action_owner && is_email($action_owner->user_email)) ? (string) $action_owner->user_email : '';
        $row['action_owner_is_client'] = $row['action_owner_id'] > 0 && $row['client_id'] > 0
            ? ! empty(array_filter(WP_PQ_DB::get_client_memberships($row['client_id']), static function (array $membership) use ($row): bool {
                return (int) ($membership['user_id'] ?? 0) === (int) ($row['action_owner_id'] ?? 0);
            }))
            : false;
        $row['note_count'] = isset($row['note_count']) ? (int) $row['note_count'] : 0;
        $row['latest_note_preview'] = isset($row['latest_note_preview']) ? (string) $row['latest_note_preview'] : '';

        return $row;
    }

    private static function user_display_name(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        $user = get_user_by('ID', $user_id);
        return $user ? (string) $user->display_name : '';
    }

    private static function shift_task_schedule(array $task, string $new_primary_datetime): array
    {
        $requested_deadline = (string) ($task['requested_deadline'] ?? '');
        $due_at = (string) ($task['due_at'] ?? '');
        $current_primary = $requested_deadline !== '' ? $requested_deadline : $due_at;

        if ($current_primary === '') {
            return [$new_primary_datetime, null];
        }

        $old_ts = strtotime($current_primary . ' UTC');
        $new_ts = strtotime($new_primary_datetime . ' UTC');
        if (! $old_ts || ! $new_ts) {
            return [$new_primary_datetime, $due_at !== '' ? $new_primary_datetime : null];
        }

        $delta = $new_ts - $old_ts;
        $shift = static function (?string $value) use ($delta): ?string {
            if (! $value) {
                return null;
            }

            $ts = strtotime($value . ' UTC');
            if (! $ts) {
                return $value;
            }

            return gmdate('Y-m-d H:i:s', $ts + $delta);
        };

        return [
            $shift($requested_deadline) ?: ($requested_deadline !== '' ? $new_primary_datetime : null),
            $shift($due_at) ?: ($due_at !== '' ? $new_primary_datetime : null),
        ];
    }

    private static function status_timestamp_updates(string $status): array
    {
        $status = WP_PQ_Workflow::normalize_status($status);
        $now = current_time('mysql', true);

        if ($status === 'delivered') {
            return ['delivered_at' => $now];
        }

        if ($status === 'done') {
            return [
                'completed_at' => $now,
                'done_at' => $now,
            ];
        }

        if ($status === 'archived') {
            return ['archived_at' => $now];
        }

        return [];
    }

    private static function request_truthy($value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function google_redirect_uri(): string
    {
        return (string) get_option('wp_pq_google_redirect_uri', home_url('/wp-json/pq/v1/google/oauth/callback'));
    }

    private static function google_scopes(): string
    {
        $scopes = trim((string) get_option('wp_pq_google_scopes', ''));
        if ($scopes === '') {
            $scopes = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly';
        }

        return $scopes;
    }

    private static function store_google_tokens(array $token_payload): void
    {
        $existing = (array) get_option('wp_pq_google_tokens', []);
        $expires_in = isset($token_payload['expires_in']) ? (int) $token_payload['expires_in'] : 3600;
        $tokens = [
            'access_token' => (string) ($token_payload['access_token'] ?? ($existing['access_token'] ?? '')),
            'refresh_token' => (string) ($token_payload['refresh_token'] ?? ($existing['refresh_token'] ?? '')),
            'token_type' => (string) ($token_payload['token_type'] ?? ($existing['token_type'] ?? 'Bearer')),
            'expires_at' => time() + max(60, $expires_in - 30),
        ];

        update_option('wp_pq_google_tokens', $tokens, false);
    }

    private static function get_google_access_token(): string
    {
        $tokens = (array) get_option('wp_pq_google_tokens', []);
        $access_token = (string) ($tokens['access_token'] ?? '');
        $expires_at = (int) ($tokens['expires_at'] ?? 0);
        if ($access_token !== '' && $expires_at > (time() + 30)) {
            return $access_token;
        }

        $refresh_token = (string) ($tokens['refresh_token'] ?? '');
        if ($refresh_token === '') {
            return '';
        }

        $client_id = (string) get_option('wp_pq_google_client_id', '');
        $client_secret = (string) get_option('wp_pq_google_client_secret', '');
        if ($client_id === '' || $client_secret === '') {
            return '';
        }

        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);
        if (is_wp_error($resp)) {
            return '';
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($body) || empty($body['access_token'])) {
            return '';
        }

        self::store_google_tokens($body);
        $updated = (array) get_option('wp_pq_google_tokens', []);
        return (string) ($updated['access_token'] ?? '');
    }

    private static function create_google_calendar_event(array $task, string $starts_at, string $ends_at)
    {
        $token = self::get_google_access_token();
        if ($token === '') {
            return new WP_Error('google_not_connected', 'Google Calendar is not connected. Complete OAuth first.');
        }

        $description = (string) ($task['description'] ?? '');
        $summary = 'Priority Task #' . (int) $task['id'] . ': ' . (string) ($task['title'] ?? 'Meeting');
        $request_id = 'pq_' . wp_generate_uuid4();
        $attendees = self::meeting_attendees($task);
        $body = [
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => gmdate('c', strtotime($starts_at . ' UTC')),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => gmdate('c', strtotime($ends_at . ' UTC')),
                'timeZone' => 'UTC',
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => $request_id,
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ],
            'guestsCanModify' => false,
            'guestsCanInviteOthers' => true,
        ];
        if (! empty($attendees)) {
            $body['attendees'] = $attendees;
        }

        $resp = wp_remote_post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all', [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($resp)) {
            return new WP_Error('google_event_error', $resp->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($data) || empty($data['id'])) {
            return new WP_Error('google_event_error', 'Failed to create Google Calendar event.');
        }

        return $data;
    }

    private static function meeting_attendees(array $task): array
    {
        $emails = [];

        $submitter = isset($task['submitter_id']) ? get_user_by('ID', (int) $task['submitter_id']) : null;
        if ($submitter && is_email($submitter->user_email)) {
            $emails[] = (string) $submitter->user_email;
        }

        $emails = array_values(array_unique(array_filter($emails, 'is_email')));

        return array_map(static fn($email) => ['email' => $email], $emails);
    }

    private static function sync_task_calendar_event(array $task): void
    {
        global $wpdb;

        if (empty($task) || empty($task['id'])) {
            return;
        }

        $table = $wpdb->prefix . 'pq_tasks';
        $event_id = (string) ($task['google_event_id'] ?? '');
        $starts_at = (string) ($task['requested_deadline'] ?: $task['due_at'] ?: '');

        if ($starts_at === '') {
            if ($event_id !== '') {
                self::delete_google_calendar_event($event_id);
                $wpdb->update($table, [
                    'google_event_id' => null,
                    'google_event_url' => null,
                    'google_event_synced_at' => current_time('mysql', true),
                    'updated_at' => current_time('mysql', true),
                ], ['id' => (int) $task['id']]);
            }
            return;
        }

        $token = self::get_google_access_token();
        if ($token === '') {
            return;
        }

        $start_ts = strtotime($starts_at . ' UTC');
        if (! $start_ts) {
            return;
        }

        $ends_at = gmdate('Y-m-d H:i:s', $start_ts + HOUR_IN_SECONDS);
        $summary = 'Priority Task #' . (int) $task['id'] . ': ' . self::task_title($task);
        $description = trim((string) ($task['description'] ?? ''));
        $body = [
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => gmdate('c', $start_ts),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => gmdate('c', strtotime($ends_at . ' UTC')),
                'timeZone' => 'UTC',
            ],
            'extendedProperties' => [
                'private' => [
                    'wp_pq_task_id' => (string) (int) $task['id'],
                ],
            ],
        ];

        $url = $event_id !== ''
            ? 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($event_id) . '?sendUpdates=none'
            : 'https://www.googleapis.com/calendar/v3/calendars/primary/events?sendUpdates=none';
        $method = $event_id !== '' ? 'PUT' : 'POST';

        $resp = wp_remote_request($url, [
            'method' => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($resp)) {
            return;
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($data) || empty($data['id'])) {
            return;
        }

        $wpdb->update($table, [
            'google_event_id' => sanitize_text_field((string) $data['id']),
            'google_event_url' => esc_url_raw((string) ($data['htmlLink'] ?? '')),
            'google_event_synced_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int) $task['id']]);
    }

    private static function backfill_task_calendar_events(array $tasks): void
    {
        if (empty($tasks) || self::get_google_access_token() === '') {
            return;
        }

        foreach ($tasks as $task) {
            $starts_at = (string) ($task['requested_deadline'] ?: $task['due_at'] ?: '');
            if ($starts_at === '') {
                continue;
            }

            $event_id = (string) ($task['google_event_id'] ?? '');
            if ($event_id !== '') {
                continue;
            }

            self::sync_task_calendar_event((array) $task);
        }
    }

    private static function delete_google_calendar_event(string $event_id): void
    {
        $token = self::get_google_access_token();
        if ($token === '' || $event_id === '') {
            return;
        }

        wp_remote_request('https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($event_id) . '?sendUpdates=none', [
            'method' => 'DELETE',
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
    }

    private static function fetch_google_calendar_events(): array
    {
        $token = self::get_google_access_token();
        if ($token === '') {
            return [];
        }

        $time_min = rawurlencode(gmdate('c', strtotime('-30 days')));
        $time_max = rawurlencode(gmdate('c', strtotime('+90 days')));
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?singleEvents=true&orderBy=startTime&maxResults=100&timeMin=' . $time_min . '&timeMax=' . $time_max;

        $resp = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);
        if (is_wp_error($resp)) {
            return [];
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($data) || empty($data['items']) || ! is_array($data['items'])) {
            return [];
        }

        $events = [];
        foreach ($data['items'] as $item) {
            if (! empty($item['extendedProperties']['private']['wp_pq_task_id'])) {
                continue;
            }

            $start = '';
            $end = '';
            $all_day = false;

            if (! empty($item['start']['dateTime'])) {
                $start = (string) $item['start']['dateTime'];
                $end = ! empty($item['end']['dateTime']) ? (string) $item['end']['dateTime'] : '';
            } elseif (! empty($item['start']['date'])) {
                $start = (string) $item['start']['date'];
                $end = ! empty($item['end']['date']) ? (string) $item['end']['date'] : '';
                $all_day = true;
            }

            if ($start === '') {
                continue;
            }

            $events[] = [
                'id' => 'gcal_' . sanitize_text_field((string) ($item['id'] ?? wp_generate_uuid4())),
                'title' => '[GCal] ' . sanitize_text_field((string) ($item['summary'] ?? 'Google Event')),
                'start' => $start,
                'end' => $end ?: null,
                'allDay' => $all_day,
                'backgroundColor' => '#0f766e',
                'borderColor' => '#0f766e',
                'url' => ! empty($item['htmlLink']) ? esc_url_raw((string) $item['htmlLink']) : null,
                'extendedProps' => [
                    'source' => 'google',
                ],
            ];
        }

        return $events;
    }

    private static function sanitize_priority(string $priority): string
    {
        $allowed = ['low', 'normal', 'high', 'urgent'];
        $priority = sanitize_key($priority);

        return in_array($priority, $allowed, true) ? $priority : 'normal';
    }

    private static function sanitize_priority_direction(string $direction): string
    {
        $direction = sanitize_key($direction);
        return in_array($direction, ['keep', 'up', 'down'], true) ? $direction : 'keep';
    }

    private static function sanitize_datetime($value): ?string
    {
        if (! $value) {
            return null;
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function render_oauth_result_page(bool $ok, string $message): void
    {
        status_header($ok ? 200 : 400);
        nocache_headers();

        $title = $ok ? 'Google Connected' : 'Google Connection Failed';
        $color = $ok ? '#166534' : '#991b1b';
        $bg = $ok ? '#ecfdf5' : '#fef2f2';
        $portal_url = home_url('/priority-portal/');

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:32px;background:#f3f4f6}';
        echo '.card{max-width:640px;margin:40px auto;background:#fff;border:1px solid #d1d5db;border-radius:14px;padding:24px}';
        echo '.state{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:600;margin-bottom:12px}';
        echo '.btn{display:inline-block;margin-top:16px;background:#be123c;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600}';
        echo '</style></head><body><div class="card">';
        echo '<span class="state" style="background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';">' . esc_html($title) . '</span>';
        echo '<h1 style="margin:8px 0 10px">' . esc_html($title) . '</h1>';
        echo '<p style="font-size:16px;line-height:1.5;color:#374151">' . esc_html($message) . '</p>';
        echo '<a class="btn" href="' . esc_url($portal_url) . '">Return to Priority Portal</a>';
        echo '</div></body></html>';
        exit;
    }
}
