<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Manager_API
{
    // ── Table name constants ──────────────────────────────────────────
    private const TABLE_CLIENTS = 'pq_clients';
    private const TABLE_BUCKETS = 'pq_billing_buckets';
    private const TABLE_TASKS = 'pq_tasks';
    private const TABLE_HISTORY = 'pq_task_status_history';
    private const TABLE_MEMBERS = 'pq_job_members';
    private const TABLE_STATEMENTS = 'pq_statements';
    private const TABLE_STATEMENT_LINES = 'pq_statement_lines';
    private const TABLE_LEDGER = 'pq_work_ledger';
    private const TABLE_LEDGER_ENTRIES = 'pq_work_ledger_entries';
    private const TABLE_INVITES = 'pq_invites';
    private const TABLE_WORK_LOGS = 'pq_work_logs';
    private const TABLE_WORK_LOG_ITEMS = 'pq_work_log_items';

    private static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    private static function error_response(string $message, int $status = 422): WP_REST_Response
    {
        return new WP_REST_Response(['message' => $message], $status);
    }

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('pq/v1', '/manager/clients', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_clients'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_client'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/clients/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_client'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'update_client'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/clients/(?P<id>\d+)/members', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'add_client_member'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        // ── Contact info ─────────────────────────────────────────────
        register_rest_route('pq/v1', '/manager/clients/(?P<id>\d+)/contacts', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_contacts'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'save_contact'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/clients/(?P<id>\d+)/contacts/(?P<contact_id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'delete_contact'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/members/(?P<member_id>\d+)/contacts', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_member_contacts'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'save_member_contact'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/members/(?P<member_id>\d+)/contacts/(?P<contact_id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'delete_member_contact'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/jobs', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_job'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/jobs/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'delete_job'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/jobs/(?P<id>\d+)/force-delete', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'force_delete_job'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/jobs/(?P<id>\d+)/move', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'move_job'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/jobs/(?P<id>\d+)/members', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'assign_job_member'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/jobs/(?P<id>\d+)/members/(?P<user_id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'unassign_job_member'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/rollups', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_rollups'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/rollups/assign-job', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'assign_rollup_job'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/rollups/update-status', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'update_rollup_status'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/monthly-statements', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_monthly_statements'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/work-logs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_work_logs'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_work_log'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/work-logs/preview', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'preview_work_log'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/work-logs/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_work_log'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'update_work_log'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/statements', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_statements'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_statement'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/statements/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_statement'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'update_statement'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_statement'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/statements/(?P<id>\d+)/tasks/(?P<task_id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'remove_statement_task'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/statements/(?P<id>\d+)/lines', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_statement_line'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/statements/(?P<id>\d+)/lines/(?P<line_id>\d+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'update_statement_line'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_statement_line'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/statements/(?P<id>\d+)/payment', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'update_statement_payment'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/statements/(?P<id>\d+)/email-client', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'email_statement_to_client'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/ai-import', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_ai_import_state'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/ai-import/parse', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'parse_ai_import'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/ai-import/revalidate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'revalidate_ai_import'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/ai-import/import', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'import_ai_preview'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/ai-import/discard', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'discard_ai_preview'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        // Files library — all logged-in users (permission-segregated in handler).
        register_rest_route('pq/v1', '/files', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_files_library'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/reopen-completed', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'reopen_completed_task'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/tasks/(?P<id>\d+)/followup', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_followup_task'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/invites', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'list_invites'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_invite'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/invites/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'revoke_invite'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/invites/(?P<id>\d+)/resend', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'resend_invite'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        // ── Swimlanes ────────────────────────────────────────────────
        register_rest_route('pq/v1', '/manager/lanes', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_lanes'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_lane'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/lanes/reorder', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'reorder_lanes'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route('pq/v1', '/manager/lanes/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'update_lane'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_lane'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('pq/v1', '/manager/lanes/(?P<id>\d+)/assign', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'assign_lane_tasks'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        // ── Client Admin self-service invites ─────────────────────────
        register_rest_route('pq/v1', '/client/invites', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'client_list_invites'],
                'permission_callback' => [self::class, 'can_client_admin'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'client_create_invite'],
                'permission_callback' => [self::class, 'can_client_admin'],
            ],
        ]);

        register_rest_route('pq/v1', '/client/invites/(?P<id>\d+)/resend', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'client_resend_invite'],
            'permission_callback' => [self::class, 'can_client_admin'],
        ]);

        register_rest_route('pq/v1', '/client/invites/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'client_revoke_invite'],
            'permission_callback' => [self::class, 'can_client_admin'],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can(WP_PQ_Roles::CAP_APPROVE);
    }

    /**
     * Permission callback: current user must be client_admin on at least one client.
     */
    public static function can_client_admin(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }
        $memberships = WP_PQ_DB::get_user_client_memberships(get_current_user_id());
        foreach ($memberships as $m) {
            if ($m['role'] === 'client_admin') {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the client IDs where the current user is client_admin.
     */
    private static function get_current_user_admin_client_ids(): array
    {
        $memberships = WP_PQ_DB::get_user_client_memberships(get_current_user_id());
        $ids = [];
        foreach ($memberships as $m) {
            if ($m['role'] === 'client_admin') {
                $ids[] = (int) $m['client_id'];
            }
        }
        return $ids;
    }

    public static function get_clients(WP_REST_Request $request): WP_REST_Response
    {
        $directory_users = WP_PQ_Admin::get_directory_users();
        $clients = WP_PQ_Admin::get_billing_clients($directory_users);
        $rows = WP_PQ_Admin::get_client_directory_rows($clients, $directory_users);

        return new WP_REST_Response([
            'clients' => $rows,
            'linkable_users' => WP_PQ_Admin::get_linkable_users($directory_users),
            'member_candidates' => WP_PQ_Admin::get_member_candidate_users($directory_users),
        ], 200);
    }

    public static function get_client(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = (int) $request['id'];
        $directory_users = WP_PQ_Admin::get_directory_users();
        $clients = WP_PQ_Admin::get_billing_clients($directory_users);
        $rows = WP_PQ_Admin::get_client_directory_rows($clients, $directory_users);
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $client_id) {
                return new WP_REST_Response(['client' => $row], 200);
            }
        }

        return self::error_response('Client not found.', 404);
    }

    public static function create_client(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = WP_PQ_Sanitizer::int($request, 'user_id');
        $client_name = WP_PQ_Sanitizer::text($request, 'client_name');
        $client_email = WP_PQ_Sanitizer::email($request, 'client_email');
        $initial_bucket_name = WP_PQ_Sanitizer::text($request, 'initial_bucket_name');

        if ($user_id > 0) {
            $user = WP_PQ_API::get_cached_user($user_id);
            if (! $user) {
                return self::error_response('Choose an existing WordPress user to link as a client.');
            }
            $user->add_role('pq_client');
            $client_id = WP_PQ_DB::get_or_create_client_id_for_user((int) $user->ID, (string) $user->display_name);
            WP_PQ_DB::ensure_client_member($client_id, (int) $user->ID, 'client_admin');
            if ($initial_bucket_name === '') {
                $initial_bucket_name = WP_PQ_DB::suggest_default_bucket_name($client_id);
            }
            WP_PQ_Admin::create_bucket_for_client($client_id, $initial_bucket_name);
            return self::client_response($client_id);
        }

        if ($client_name === '' || ! is_email($client_email)) {
            return self::error_response('Enter a valid client name and email.');
        }

        $user = get_user_by('email', $client_email);
        $created = false;
        if (! $user) {
            $user_id = WP_PQ_DB::create_wp_user($client_email, [
                'display_name' => $client_name,
            ]);
            if (is_wp_error($user_id)) {
                return self::error_response($user_id->get_error_message());
            }
            $user = WP_PQ_API::get_cached_user((int) $user_id);
            WP_PQ_Admin::send_welcome_email((int) $user_id, $client_name);
            $created = true;
        } else {
            $user->add_role('pq_client');
        }

        $client_user_id = $user ? (int) $user->ID : 0;
        if ($client_user_id <= 0) {
            return self::error_response('Unable to create or link that client.', 500);
        }

        $client_id = WP_PQ_DB::get_or_create_client_id_for_user($client_user_id, $client_name !== '' ? $client_name : (string) $user->display_name);
        WP_PQ_DB::ensure_client_member($client_id, $client_user_id, 'client_admin');
        if ($initial_bucket_name === '') {
            $initial_bucket_name = $created ? trim($client_name) . ' - Main' : WP_PQ_DB::suggest_default_bucket_name($client_id);
        }
        WP_PQ_Admin::create_bucket_for_client($client_id, $initial_bucket_name);

        // Save optional address / tax fields if provided.
        $extra = [];
        foreach (['tax_id', 'address_line1', 'address_line2', 'city', 'state', 'zip', 'country'] as $field) {
            $val = $request->get_param($field);
            if ($val !== null && trim((string) $val) !== '') {
                $extra[$field] = sanitize_text_field((string) $val);
            }
        }
        if ($extra) {
            $extra['updated_at'] = current_time('mysql', true);
            $wpdb->update(self::table(self::TABLE_CLIENTS), $extra, ['id' => $client_id]);
        }

        $response = self::client_response($client_id);
        $data = (array) $response->get_data();
        $data['message'] = $created ? 'Client created and ready for billing.' : 'Existing user linked as a client.';
        return new WP_REST_Response($data, 201);
    }

    public static function update_client(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $client_id = (int) $request['id'];
        if ($client_id <= 0 || ! WP_PQ_Admin::can_manage_client($client_id)) {
            return self::error_response('Client not found.', 404);
        }

        $updates = [];
        $name = WP_PQ_Sanitizer::text($request, 'name');
        if ($name !== '') {
            $updates['name'] = $name;
            $updates['slug'] = sanitize_title($name);
        }

        $primary_contact_user_id = WP_PQ_Sanitizer::int($request, 'primary_contact_user_id');
        if ($primary_contact_user_id > 0) {
            $updates['primary_contact_user_id'] = $primary_contact_user_id;
            WP_PQ_DB::ensure_client_member($client_id, $primary_contact_user_id, 'client_admin');
        }

        foreach (['tax_id', 'address_line1', 'address_line2', 'city', 'state', 'zip', 'country'] as $field) {
            $val = $request->get_param($field);
            if ($val !== null) {
                $updates[$field] = sanitize_text_field((string) $val);
            }
        }

        if (! empty($updates)) {
            $updates['updated_at'] = current_time('mysql', true);
            $wpdb->update(self::table(self::TABLE_CLIENTS), $updates, ['id' => $client_id]);
        }

        return self::client_response($client_id);
    }

    public static function add_client_member(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = (int) $request['id'];
        $user_id = WP_PQ_Sanitizer::int($request, 'user_id');
        $role = WP_PQ_Sanitizer::key($request, 'client_role');
        if (! in_array($role, ['client_admin', 'client_contributor', 'client_viewer'], true)) {
            $role = 'client_contributor';
        }
        $user = $user_id > 0 ? WP_PQ_API::get_cached_user($user_id) : null;
        if (! WP_PQ_Admin::can_manage_client($client_id) || ! $user) {
            return self::error_response('Choose a valid user to add to this client.');
        }

        $user->add_role('pq_client');
        WP_PQ_DB::ensure_client_member($client_id, $user_id, $role);

        return new WP_REST_Response(['ok' => true, 'message' => 'Client member saved.'], 200);
    }

    public static function create_job(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = WP_PQ_Sanitizer::int($request, 'client_id');
        $bucket_name = WP_PQ_Sanitizer::text($request, 'bucket_name');
        if ($client_id <= 0 || $bucket_name === '') {
            return self::error_response('Choose a client and job name.');
        }
        $bucket_id = WP_PQ_Admin::create_bucket_for_client($client_id, $bucket_name);
        if ($bucket_id <= 0) {
            return self::error_response('Job could not be saved.', 500);
        }

        return new WP_REST_Response([
            'ok' => true,
            'bucket_id' => $bucket_id,
            'message' => 'Job saved.',
        ], 201);
    }

    public static function delete_job(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $bucket_id = (int) $request['id'];
        if ($bucket_id <= 0) {
            return self::error_response('Choose a job to delete.');
        }

        $counts = WP_PQ_Admin::get_bucket_dependency_counts($bucket_id);
        $has_deps = ! WP_PQ_Admin::bucket_can_be_deleted($counts);

        if ($has_deps) {
            $confirm = WP_PQ_Sanitizer::text($request, 'confirm');
            if ($confirm !== 'DELETE') {
                return new WP_REST_Response([
                    'message' => 'Type DELETE to confirm.',
                    'counts' => $counts,
                ], 422);
            }

            $target_id = WP_PQ_Sanitizer::int($request, 'target_bucket_id');
            if ($target_id <= 0 || $target_id === $bucket_id) {
                return self::error_response('Choose a valid target job to reassign tasks.');
            }

            // Verify target bucket exists.
            $target = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM " . self::table(self::TABLE_BUCKETS) . " WHERE id = %d",
                $target_id
            ));
            if (! $target) {
                return self::error_response('Target job not found.');
            }

            // Reassign tasks.
            $wpdb->update(
                self::table(self::TABLE_TASKS),
                ['billing_bucket_id' => $target_id],
                ['billing_bucket_id' => $bucket_id]
            );

            // Reassign statement lines.
            $wpdb->update(
                self::table(self::TABLE_STATEMENT_LINES),
                ['billing_bucket_id' => $target_id],
                ['billing_bucket_id' => $bucket_id]
            );
        }

        // Remove job member assignments and then the bucket itself.
        $wpdb->delete(self::table(self::TABLE_MEMBERS), ['billing_bucket_id' => $bucket_id]);
        $wpdb->delete(self::table(self::TABLE_BUCKETS), ['id' => $bucket_id]);

        return new WP_REST_Response(['ok' => true, 'message' => $has_deps ? 'Job deleted and tasks reassigned.' : 'Empty job deleted.'], 200);
    }

    /**
     * Force-delete a job via POST with JSON body (reliable param delivery).
     * If target_bucket_id is provided, tasks are reassigned; otherwise tasks are deleted.
     */
    public static function force_delete_job(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $bucket_id = (int) $request['id'];
        if ($bucket_id <= 0) {
            return self::error_response('Choose a job to delete.');
        }

        $confirm = WP_PQ_Sanitizer::text($request, 'confirm');
        if ($confirm !== 'DELETE') {
            return self::error_response('Type DELETE to confirm.');
        }

        $target_id = WP_PQ_Sanitizer::int($request, 'target_bucket_id');

        if ($target_id > 0 && $target_id !== $bucket_id) {
            // Reassign tasks and statement lines to target job.
            $wpdb->update(
                self::table(self::TABLE_TASKS),
                ['billing_bucket_id' => $target_id],
                ['billing_bucket_id' => $bucket_id]
            );
            $wpdb->update(
                self::table(self::TABLE_STATEMENT_LINES),
                ['billing_bucket_id' => $target_id],
                ['billing_bucket_id' => $bucket_id]
            );
        } else {
            // No target — cascading delete: remove tasks, work log items, statement lines.
            $task_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM " . self::table(self::TABLE_TASKS) . " WHERE billing_bucket_id = %d",
                $bucket_id
            ));
            if ($task_ids) {
                $id_list = implode(',', array_map('intval', $task_ids));
                $work_log_items_table = self::table(self::TABLE_WORK_LOG_ITEMS);
                $tasks_table = self::table(self::TABLE_TASKS);
                $wpdb->query("DELETE FROM {$work_log_items_table} WHERE task_id IN ({$id_list})");
                $wpdb->query("DELETE FROM {$tasks_table} WHERE id IN ({$id_list})");
            }
            $wpdb->delete(self::table(self::TABLE_STATEMENT_LINES), ['billing_bucket_id' => $bucket_id]);
        }

        // Remove job member assignments and bucket.
        $wpdb->delete(self::table(self::TABLE_MEMBERS), ['billing_bucket_id' => $bucket_id]);
        $wpdb->delete(self::table(self::TABLE_BUCKETS), ['id' => $bucket_id]);

        return new WP_REST_Response(['ok' => true, 'message' => 'Job deleted.'], 200);
    }

    public static function move_job(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $bucket_id = (int) $request['id'];
        $target_client_id = WP_PQ_Sanitizer::int($request, 'target_client_id');

        if ($bucket_id <= 0 || $target_client_id <= 0) {
            return self::error_response('Choose a job and target client.');
        }

        $bucket = $wpdb->get_row($wpdb->prepare(
            "SELECT id, client_id, client_user_id FROM " . self::table(self::TABLE_BUCKETS) . " WHERE id = %d",
            $bucket_id
        ), ARRAY_A);

        if (! $bucket) {
            return self::error_response('Job not found.', 404);
        }

        if ((int) $bucket['client_id'] === $target_client_id) {
            return self::error_response('Job already belongs to that client.');
        }

        if (! WP_PQ_Admin::can_manage_client((int) $bucket['client_id']) || ! WP_PQ_Admin::can_manage_client($target_client_id)) {
            return self::error_response('Permission denied.', 403);
        }

        // Get the target client's primary user ID for the client_user_id column.
        $target_user_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT primary_contact_user_id FROM " . self::table(self::TABLE_CLIENTS) . " WHERE id = %d",
            $target_client_id
        ));

        $wpdb->update(self::table(self::TABLE_BUCKETS), [
            'client_id' => $target_client_id,
            'client_user_id' => $target_user_id > 0 ? $target_user_id : (int) $bucket['client_user_id'],
            'is_default' => 0,
        ], ['id' => $bucket_id]);

        return new WP_REST_Response(['ok' => true, 'message' => 'Job moved to new client.'], 200);
    }

    public static function assign_job_member(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $bucket_id = (int) $request['id'];
        $user_id = WP_PQ_Sanitizer::int($request, 'user_id');
        $bucket = $bucket_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM " . self::table(self::TABLE_BUCKETS) . " WHERE id = %d", $bucket_id), ARRAY_A) : null;
        $client_id = (int) ($bucket['client_id'] ?? 0);
        if (! $bucket || ! WP_PQ_Admin::can_manage_client($client_id) || $user_id <= 0) {
            return self::error_response('Choose a valid client member and job.');
        }

        $member_ids = array_map(static fn(array $membership): int => (int) ($membership['user_id'] ?? 0), WP_PQ_DB::get_client_memberships($client_id));
        if (! in_array($user_id, $member_ids, true)) {
            return self::error_response('Add the user to the client account before assigning them to a job.');
        }

        if (in_array($user_id, WP_PQ_DB::get_job_member_ids($bucket_id), true)) {
            return new WP_REST_Response(['ok' => true, 'message' => 'That member already has access to this job.', 'noop' => true], 200);
        }

        WP_PQ_DB::ensure_job_member($bucket_id, $user_id);
        return new WP_REST_Response(['ok' => true, 'message' => 'Job access saved.'], 200);
    }

    public static function unassign_job_member(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $bucket_id = (int) $request['id'];
        $user_id = (int) $request['user_id'];
        $bucket = $bucket_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM " . self::table(self::TABLE_BUCKETS) . " WHERE id = %d", $bucket_id), ARRAY_A) : null;
        $client_id = (int) ($bucket['client_id'] ?? 0);
        if (! $bucket || ! WP_PQ_Admin::can_manage_client($client_id) || $user_id <= 0) {
            return self::error_response('Invalid job or user.');
        }

        WP_PQ_DB::remove_job_member($bucket_id, $user_id);
        return new WP_REST_Response(['ok' => true, 'message' => 'Job access removed.'], 200);
    }

    public static function get_rollups(WP_REST_Request $request): WP_REST_Response
    {
        $range = self::range_from_request($request);
        $client_id = WP_PQ_Sanitizer::int($request, 'client_id');
        $invoice_status = WP_PQ_Sanitizer::key($request, 'invoice_status');
        $groups = WP_PQ_Admin::get_rollup_groups($range['start'], $range['end'], self::buckets_by_client(), $invoice_status);
        if ($client_id > 0) {
            $groups = array_values(array_filter($groups, static fn(array $group): bool => (int) ($group['client_id'] ?? 0) === $client_id));
        }

        return new WP_REST_Response([
            'range' => $range,
            'groups' => $groups,
            'clients' => WP_PQ_Admin::get_billing_clients(WP_PQ_Admin::get_directory_users()),
        ], 200);
    }

    public static function assign_rollup_job(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $task_id = WP_PQ_Sanitizer::int($request, 'task_id');
        $ledger_entry_id = WP_PQ_Sanitizer::int($request, 'ledger_entry_id');
        $bucket_id = WP_PQ_Sanitizer::int($request, 'billing_bucket_id');
        if ($bucket_id <= 0 || ($task_id <= 0 && $ledger_entry_id <= 0)) {
            return self::error_response('Choose completed work and a job.');
        }

        $bucket = $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM " . self::table(self::TABLE_BUCKETS) . " WHERE id = %d", $bucket_id), ARRAY_A);
        $ledger_entry = $ledger_entry_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, task_id, client_id FROM " . self::table(self::TABLE_LEDGER_ENTRIES) . " WHERE id = %d", $ledger_entry_id), ARRAY_A) : null;
        $task = $task_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM " . self::table(self::TABLE_TASKS) . " WHERE id = %d", $task_id), ARRAY_A) : null;
        if (! $bucket) {
            return self::error_response('Choose a valid job.');
        }

        $now = current_time('mysql', true);
        if ($ledger_entry && (int) ($ledger_entry['client_id'] ?? 0) === (int) ($bucket['client_id'] ?? 0)) {
            $wpdb->update(self::table(self::TABLE_LEDGER_ENTRIES), [
                'billing_bucket_id' => $bucket_id,
                'updated_at' => $now,
            ], ['id' => $ledger_entry_id]);
            if ($task_id <= 0) {
                $task_id = (int) ($ledger_entry['task_id'] ?? 0);
                if ($task_id > 0) {
                    $task = $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM " . self::table(self::TABLE_TASKS) . " WHERE id = %d", $task_id), ARRAY_A);
                }
            }
        }

        if ($task && (int) ($task['client_id'] ?? 0) === (int) ($bucket['client_id'] ?? 0)) {
            $wpdb->update(self::table(self::TABLE_TASKS), [
                'billing_bucket_id' => $bucket_id,
                'updated_at' => $now,
            ], ['id' => $task_id]);
        }

        return new WP_REST_Response(['ok' => true, 'message' => 'Completed work job updated.'], 200);
    }

    public static function update_rollup_status(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $entry_ids = $request->get_param('entry_ids');
        $new_status = WP_PQ_Sanitizer::key($request, 'invoice_status');

        if (! is_array($entry_ids) || empty($entry_ids)) {
            return self::error_response('Select at least one entry.');
        }
        if (! in_array($new_status, ['pending_review', 'billable', 'no_charge'], true)) {
            return self::error_response('Invalid status.');
        }

        $safe_ids = array_map('intval', $entry_ids);
        $safe_ids = array_filter($safe_ids, static fn(int $id): bool => $id > 0);
        if (empty($safe_ids)) {
            return self::error_response('No valid entry IDs.');
        }

        $placeholders = implode(',', array_fill(0, count($safe_ids), '%d'));
        $now = current_time('mysql', true);

        // Only allow changes on entries that haven't been invoiced or paid.
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table(self::TABLE_LEDGER_ENTRIES) . "
             SET invoice_status = %s, updated_at = %s
             WHERE id IN ($placeholders)
               AND invoice_status IN ('pending_review', 'billable', 'no_charge')",
            array_merge([$new_status, $now], $safe_ids)
        ));

        return new WP_REST_Response([
            'ok' => true,
            'updated' => (int) $updated,
            'message' => $updated . ' ' . ($updated === 1 ? 'entry' : 'entries') . ' updated.',
        ], 200);
    }

    public static function get_monthly_statements(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $period = WP_PQ_API::normalize_statement_month((string) $request->get_param('month'));
        $client_id = WP_PQ_Sanitizer::int($request, 'client_id');
        $bucket_id = WP_PQ_Sanitizer::int($request, 'billing_bucket_id');
        $invoice_status = WP_PQ_Sanitizer::key($request, 'invoice_status');

        $where = [$wpdb->prepare('l.statement_month = %s', $period)];
        if ($client_id > 0) {
            $where[] = $wpdb->prepare('l.client_id = %d', $client_id);
        }
        if ($bucket_id > 0) {
            $where[] = $wpdb->prepare('l.billing_bucket_id = %d', $bucket_id);
        }
        if ($invoice_status !== '' && in_array($invoice_status, ['pending_review', 'billable', 'no_charge', 'invoiced', 'paid'], true)) {
            $where[] = $wpdb->prepare('l.invoice_status = %s', $invoice_status);
        }

        $ledger_entries_table = self::table(self::TABLE_LEDGER_ENTRIES);
        $clients_table = self::table(self::TABLE_CLIENTS);
        $buckets_table = self::table(self::TABLE_BUCKETS);
        $rows = (array) $wpdb->get_results(
            "SELECT l.*, owner.display_name AS owner_name, client.name AS client_name, bucket.bucket_name
             FROM {$ledger_entries_table} l
             LEFT JOIN {$wpdb->users} owner ON owner.ID = l.owner_id
             LEFT JOIN {$clients_table} client ON client.id = l.client_id
             LEFT JOIN {$buckets_table} bucket ON bucket.id = l.billing_bucket_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY client.name ASC, bucket.bucket_name ASC, l.completion_date DESC, l.id DESC",
            ARRAY_A
        );

        $groups = [];
        foreach ($rows as $row) {
            $group_key = (int) ($row['client_id'] ?? 0) . ':' . (int) ($row['billing_bucket_id'] ?? 0) . ':' . (string) ($row['statement_month'] ?? $period);
            if (! isset($groups[$group_key])) {
                $groups[$group_key] = [
                    'client_id' => (int) ($row['client_id'] ?? 0),
                    'client_name' => (string) ($row['client_name'] ?? ''),
                    'billing_bucket_id' => (int) ($row['billing_bucket_id'] ?? 0),
                    'job_name' => (string) ($row['bucket_name'] ?? ''),
                    'month' => (string) ($row['statement_month'] ?? $period),
                    'entries' => [],
                    'total_amount' => 0.0,
                    'counts' => [
                        'pending_review' => 0,
                        'billable' => 0,
                        'no_charge' => 0,
                        'invoiced' => 0,
                        'paid' => 0,
                    ],
                ];
            }
            $groups[$group_key]['entries'][] = $row;
            $groups[$group_key]['total_amount'] += (float) ($row['amount'] ?? 0);
            $status_key = (string) ($row['invoice_status'] ?? 'pending_review');
            if (! isset($groups[$group_key]['counts'][$status_key])) {
                $groups[$group_key]['counts'][$status_key] = 0;
            }
            $groups[$group_key]['counts'][$status_key]++;
        }

        return new WP_REST_Response([
            'month' => $period,
            'groups' => array_values($groups),
        ], 200);
    }

    public static function get_work_logs(WP_REST_Request $request): WP_REST_Response
    {
        $range = self::range_from_request($request);
        return new WP_REST_Response([
            'range' => $range,
            'work_logs' => WP_PQ_Admin::get_work_log_summaries($range['start'], $range['end']),
        ], 200);
    }

    public static function get_work_log(WP_REST_Request $request): WP_REST_Response
    {
        $work_log_id = (int) $request['id'];
        $detail = WP_PQ_Admin::get_work_log_detail($work_log_id);
        if (! $detail) {
            return self::error_response('Work statement not found.', 404);
        }
        return new WP_REST_Response(['work_log' => $detail], 200);
    }

    public static function preview_work_log(WP_REST_Request $request): WP_REST_Response
    {
        $tasks = WP_PQ_API::preview_work_log_tasks([
            'client_id' => WP_PQ_Sanitizer::int($request, 'client_id'),
            'range_start' => WP_PQ_API::normalize_rollup_date((string) $request->get_param('range_start')),
            'range_end' => WP_PQ_API::normalize_rollup_date((string) $request->get_param('range_end')),
            'job_ids' => WP_PQ_API::sanitize_int_array($request->get_param('job_ids')),
            'statuses' => (array) $request->get_param('statuses'),
            'billable' => (array) $request->get_param('billable'),
        ]);

        return new WP_REST_Response([
            'tasks' => $tasks,
            'count' => count($tasks),
        ], 200);
    }

    public static function create_work_log(WP_REST_Request $request): WP_REST_Response
    {
        $result = WP_PQ_API::create_work_log_snapshot([
            'client_id' => WP_PQ_Sanitizer::int($request, 'client_id'),
            'range_start' => WP_PQ_API::normalize_rollup_date((string) $request->get_param('range_start')),
            'range_end' => WP_PQ_API::normalize_rollup_date((string) $request->get_param('range_end')),
            'job_ids' => WP_PQ_API::sanitize_int_array($request->get_param('job_ids')),
            'statuses' => (array) $request->get_param('statuses'),
            'notes' => WP_PQ_Sanitizer::textarea($request, 'notes'),
        ], get_current_user_id());

        if (is_wp_error($result)) {
            return self::error_response($result->get_error_message(), (int) ($result->get_error_data()['status'] ?? 422));
        }

        return new WP_REST_Response([
            'ok' => true,
            'message' => sprintf('Work statement %s created.', (string) ($result['code'] ?? '')),
            'work_log' => WP_PQ_Admin::get_work_log_detail((int) ($result['id'] ?? 0)),
        ], 201);
    }

    public static function update_work_log(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $work_log_id = (int) $request['id'];
        $exists = $work_log_id > 0 && $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table(self::TABLE_WORK_LOGS) . " WHERE id = %d", $work_log_id));
        if (! $exists) {
            return self::error_response('Work statement not found.', 404);
        }
        $wpdb->update(self::table(self::TABLE_WORK_LOGS), [
            'notes' => WP_PQ_Sanitizer::textarea($request, 'notes'),
        ], ['id' => $work_log_id]);

        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Work statement updated.',
            'work_log' => WP_PQ_Admin::get_work_log_detail($work_log_id),
        ], 200);
    }

    public static function get_statements(WP_REST_Request $request): WP_REST_Response
    {
        $period = WP_PQ_API::normalize_statement_month((string) $request->get_param('period'));
        return new WP_REST_Response([
            'period' => $period,
            'unbilled_entries' => WP_PQ_Admin::get_unbilled_ledger_entries($period),
            'statements' => WP_PQ_Admin::get_statement_summaries($period),
        ], 200);
    }

    public static function get_statement(WP_REST_Request $request): WP_REST_Response
    {
        $statement_id = (int) $request['id'];
        $detail = WP_PQ_Admin::get_statement_detail($statement_id);
        if (! $detail) {
            return self::error_response('Invoice draft not found.', 404);
        }
        return new WP_REST_Response(['statement' => $detail], 200);
    }

    public static function create_statement(WP_REST_Request $request): WP_REST_Response
    {
        $entry_ids = WP_PQ_API::sanitize_int_array($request->get_param('entry_ids'));
        $task_ids = WP_PQ_API::sanitize_int_array($request->get_param('task_ids'));
        if (empty($entry_ids) && empty($task_ids)) {
            return self::error_response('Choose at least one eligible completed work entry before creating an invoice draft.');
        }

        $result = WP_PQ_API::create_invoice_draft([
            'task_ids' => $task_ids,
            'entry_ids' => $entry_ids,
            'client_id' => WP_PQ_Sanitizer::int($request, 'client_id'),
            'notes' => WP_PQ_Sanitizer::textarea($request, 'notes'),
            'statement_month' => WP_PQ_API::normalize_statement_month((string) $request->get_param('statement_month')),
        ], get_current_user_id());

        if (is_wp_error($result)) {
            return self::error_response($result->get_error_message(), (int) ($result->get_error_data()['status'] ?? 422));
        }

        return new WP_REST_Response([
            'ok' => true,
            'message' => sprintf('Invoice Draft %s created.', (string) ($result['code'] ?? '')),
            'statement' => WP_PQ_Admin::get_statement_detail((int) ($result['id'] ?? 0)),
        ], 201);
    }

    public static function update_statement(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $statement_id = (int) $request['id'];
        $exists = $statement_id > 0 && $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table(self::TABLE_STATEMENTS) . " WHERE id = %d", $statement_id));
        if (! $exists) {
            return self::error_response('Invoice draft not found.', 404);
        }

        $update = [
            'currency_code' => strtoupper(substr(sanitize_text_field((string) $request->get_param('currency_code') ?: 'USD'), 0, 10)),
            'due_date' => null,
            'notes' => WP_PQ_Sanitizer::textarea($request, 'notes'),
            'updated_at' => current_time('mysql', true),
        ];
        $due_date = WP_PQ_API::normalize_rollup_date((string) $request->get_param('due_date'));
        if ($due_date !== '') {
            $update['due_date'] = $due_date;
        }
        $wpdb->update(self::table(self::TABLE_STATEMENTS), $update, ['id' => $statement_id]);
        WP_PQ_API::recalculate_statement_total($statement_id);

        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Invoice draft updated.',
            'statement' => WP_PQ_Admin::get_statement_detail($statement_id),
        ], 200);
    }

    public static function delete_statement(WP_REST_Request $request): WP_REST_Response
    {
        $statement_id = (int) $request['id'];
        $result = WP_PQ_API::delete_statement_draft($statement_id, get_current_user_id());
        if (is_wp_error($result)) {
            return self::error_response($result->get_error_message(), (int) ($result->get_error_data()['status'] ?? 422));
        }
        return new WP_REST_Response(['ok' => true, 'message' => 'Invoice Draft deleted.'], 200);
    }

    public static function remove_statement_task(WP_REST_Request $request): WP_REST_Response
    {
        $statement_id = (int) $request['id'];
        $task_id = (int) $request['task_id'];
        $result = WP_PQ_API::remove_task_from_statement_draft($statement_id, $task_id, get_current_user_id());
        if (is_wp_error($result)) {
            return self::error_response($result->get_error_message(), (int) ($result->get_error_data()['status'] ?? 422));
        }
        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Task removed from invoice draft.',
            'statement' => WP_PQ_Admin::get_statement_detail($statement_id),
        ], 200);
    }

    public static function create_statement_line(WP_REST_Request $request): WP_REST_Response
    {
        $statement_id = (int) $request['id'];
        $payload = self::statement_line_payload($request, false);
        if (is_wp_error($payload)) {
            return self::error_response($payload->get_error_message(), (int) ($payload->get_error_data()['status'] ?? 422));
        }

        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert(self::table(self::TABLE_STATEMENT_LINES), array_merge($payload, [
            'statement_id' => $statement_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
        WP_PQ_API::recalculate_statement_total($statement_id);

        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Invoice draft line added.',
            'statement' => WP_PQ_Admin::get_statement_detail($statement_id),
        ], 201);
    }

    public static function update_statement_line(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $statement_id = (int) $request['id'];
        $line_id = (int) $request['line_id'];
        $payload = self::statement_line_payload($request, true);
        if (is_wp_error($payload)) {
            return self::error_response($payload->get_error_message(), (int) ($payload->get_error_data()['status'] ?? 422));
        }

        $updated = $wpdb->update(self::table(self::TABLE_STATEMENT_LINES), array_merge($payload, [
            'updated_at' => current_time('mysql', true),
        ]), [
            'id' => $line_id,
            'statement_id' => $statement_id,
        ]);
        if ($updated === false) {
            return self::error_response('Invoice draft line could not be updated.', 500);
        }
        WP_PQ_API::recalculate_statement_total($statement_id);

        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Invoice draft line updated.',
            'statement' => WP_PQ_Admin::get_statement_detail($statement_id),
        ], 200);
    }

    public static function delete_statement_line(WP_REST_Request $request): WP_REST_Response
    {
        $statement_id = (int) $request['id'];
        $line_id = (int) $request['line_id'];
        $result = WP_PQ_API::delete_statement_line($statement_id, $line_id, get_current_user_id());
        if (is_wp_error($result)) {
            return self::error_response($result->get_error_message(), (int) ($result->get_error_data()['status'] ?? 422));
        }
        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Invoice draft line removed.',
            'statement' => WP_PQ_Admin::get_statement_detail($statement_id),
        ], 200);
    }

    public static function update_statement_payment(WP_REST_Request $request): WP_REST_Response
    {
        $statement_id = (int) $request['id'];
        $payment_state = WP_PQ_Sanitizer::key($request, 'payment_status');
        if (! in_array($payment_state, ['paid', 'unpaid'], true)) {
            return self::error_response('Choose a valid payment status.');
        }

        $result = self::set_statement_payment_state($statement_id, $payment_state === 'paid', get_current_user_id());
        if (is_wp_error($result)) {
            return self::error_response($result->get_error_message(), (int) ($result->get_error_data()['status'] ?? 422));
        }

        return new WP_REST_Response([
            'ok' => true,
            'message' => $payment_state === 'paid' ? 'Invoice draft marked paid.' : 'Invoice draft marked unpaid.',
            'statement' => WP_PQ_Admin::get_statement_detail($statement_id),
        ], 200);
    }

    public static function email_statement_to_client(WP_REST_Request $request): WP_REST_Response
    {
        $statement_id = (int) $request['id'];
        $statement = WP_PQ_Admin::get_statement_detail($statement_id);
        if (! $statement) {
            return self::error_response('Invoice draft not found.', 404);
        }

        $client_email = sanitize_email((string) ($statement['client_email'] ?? ''));
        if (! is_email($client_email)) {
            return self::error_response('This client does not have a valid email address on file.');
        }

        $subject = sprintf('Invoice Draft %s', (string) ($statement['statement_code'] ?? $statement_id));
        $message = self::statement_email_html($statement);
        $sent = WP_PQ_API::send_gmail(get_current_user_id(), $client_email, $subject, $message, true);
        if (! $sent) {
            return self::error_response('Invoice draft email could not be sent.', 500);
        }

        return new WP_REST_Response([
            'ok' => true,
            'message' => sprintf('Invoice draft emailed to %s.', $client_email),
        ], 200);
    }

    public static function get_ai_import_state(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'preview' => WP_PQ_Admin::get_ai_import_preview(),
        ], 200);
    }

    public static function parse_ai_import(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = WP_PQ_Sanitizer::int($request, 'client_id');
        $bucket_id = WP_PQ_Sanitizer::int($request, 'billing_bucket_id');
        if ($client_id <= 0) {
            return self::error_response('Choose a client before parsing.');
        }

        $source_text = trim((string) $request->get_param('source_text'));
        $file_params = $request->get_file_params();
        $upload = $file_params['source_file'] ?? null;
        $file_path = '';
        $file_name = '';
        $mime_type = '';
        if (is_array($upload) && ! empty($upload['tmp_name']) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $file_path = (string) $upload['tmp_name'];
            $file_name = sanitize_file_name((string) ($upload['name'] ?? 'document'));
            $mime_type = sanitize_mime_type((string) ($upload['type'] ?? ''));
        }

        $client_name = WP_PQ_DB::get_client_name($client_id);
        $jobs = array_map(static fn(array $job): string => (string) ($job['bucket_name'] ?? ''), WP_PQ_Admin::get_client_bucket_rows($client_id));
        $parsed = WP_PQ_AI_Importer::parse_document([
            'api_key' => (string) get_option('wp_pq_openai_api_key', ''),
            'anthropic_key' => (string) get_option('wp_pq_anthropic_api_key', ''),
            'model' => (string) get_option('wp_pq_openai_model', 'gpt-4o-mini'),
            'client_name' => $client_name,
            'known_jobs' => $jobs,
            'source_text' => $source_text,
            'file_path' => $file_path,
            'file_name' => $file_name,
            'mime_type' => $mime_type,
        ]);

        if (is_wp_error($parsed)) {
            return self::error_response($parsed->get_error_message());
        }
        if (empty($parsed['tasks'])) {
            return self::error_response('OpenAI did not find any importable tasks in that source.');
        }

        $preview = WP_PQ_Admin::build_ai_import_preview(
            (array) ($parsed['tasks'] ?? []),
            $client_id,
            $bucket_id,
            $file_name !== '' ? $file_name : 'Pasted text',
            (string) ($parsed['summary'] ?? 'Parsed task list')
        );
        WP_PQ_Admin::store_ai_import_preview($preview);

        return new WP_REST_Response([
            'ok' => true,
            'message' => sprintf('Parsed %d task%s.', count((array) ($preview['tasks'] ?? [])), count((array) ($preview['tasks'] ?? [])) === 1 ? '' : 's'),
            'preview' => $preview,
        ], 200);
    }

    public static function revalidate_ai_import(WP_REST_Request $request): WP_REST_Response
    {
        $preview = WP_PQ_Admin::get_ai_import_preview();
        if (! $preview || empty($preview['raw_tasks'])) {
            return self::error_response('Parse a task list before trying to revalidate it.');
        }

        $client_id = WP_PQ_Sanitizer::int($request, 'client_id');
        $bucket_id = WP_PQ_Sanitizer::int($request, 'billing_bucket_id');
        if ($client_id <= 0) {
            return self::error_response('Choose a client before revalidating the import preview.');
        }

        $rebuilt = WP_PQ_Admin::build_ai_import_preview(
            (array) $preview['raw_tasks'],
            $client_id,
            $bucket_id,
            (string) ($preview['source_name'] ?? 'Pasted text'),
            (string) ($preview['summary'] ?? 'Parsed task list')
        );
        WP_PQ_Admin::store_ai_import_preview($rebuilt);

        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Preview context updated.',
            'preview' => $rebuilt,
        ], 200);
    }

    public static function import_ai_preview(WP_REST_Request $request): WP_REST_Response
    {
        $preview = WP_PQ_Admin::get_ai_import_preview();
        if (! $preview || empty($preview['tasks'])) {
            return self::error_response('There is no parsed preview ready to import.');
        }

        $posted_client_id = WP_PQ_Sanitizer::int($request, 'client_id');
        $posted_bucket_id = WP_PQ_Sanitizer::int($request, 'billing_bucket_id');
        $client_id = (int) ($preview['client_id'] ?? 0);
        $bucket_id = (int) ($preview['billing_bucket_id'] ?? 0);
        if ($posted_client_id !== $client_id || $posted_bucket_id !== $bucket_id) {
            return self::error_response('The selected client/job context changed. Revalidate the preview before importing again.');
        }
        if (! empty($preview['blocking_errors'])) {
            return self::error_response('Fix the blocking import issues before importing.');
        }
        if (! empty($preview['requires_job_confirmation']) && ! $request->get_param('confirm_new_jobs')) {
            return self::error_response('Confirm the new job creation before importing.');
        }

        $submitter_id = WP_PQ_DB::get_primary_contact_user_id($client_id);
        $imported = 0;
        $errors = [];
        global $wpdb;

        foreach ((array) $preview['tasks'] as $task) {
            $task_bucket_id = self::resolve_preview_bucket_id($task, $client_id, $bucket_id);
            $action_owner_id = (int) ($task['resolved_owner_id'] ?? 0);
            $task_request = new WP_REST_Request('POST', '/pq/v1/tasks');
            $task_request->set_param('title', (string) ($task['title'] ?? ''));
            $task_request->set_param('description', (string) ($task['description'] ?? ''));
            $task_request->set_param('priority', (string) ($task['priority'] ?? 'normal'));
            $task_request->set_param('requested_deadline', (string) ($task['normalized_deadline'] ?? ''));
            $task_request->set_param('needs_meeting', ! empty($task['needs_meeting']));
            $task_request->set_param('client_id', $client_id);
            $task_request->set_param('submitter_id', $submitter_id);
            $task_request->set_param('billing_bucket_id', $task_bucket_id);
            if ($action_owner_id > 0) {
                $task_request->set_param('owner_ids', [$action_owner_id]);
            }
            if (($task['is_billable'] ?? null) !== null) {
                $task_request->set_param('is_billable', ! empty($task['is_billable']));
            }

            $response = WP_PQ_API::create_task($task_request);
            if ($response->get_status() >= 400) {
                $data = (array) $response->get_data();
                $errors[] = (string) ($data['message'] ?? 'Task import failed.');
                continue;
            }

            $task_id = (int) (($response->get_data()['task_id'] ?? 0));
            $status_hint = (string) ($task['status_hint'] ?? 'pending_approval');
            WP_PQ_API::import_set_initial_status($task_id, $status_hint);
            $imported++;
        }

        WP_PQ_Admin::clear_ai_import_preview();
        return new WP_REST_Response([
            'ok' => true,
            'message' => sprintf('Imported %d task%s.', $imported, $imported === 1 ? '' : 's') . (! empty($errors) ? ' ' . count($errors) . ' item(s) could not be imported.' : ''),
            'imported' => $imported,
            'errors' => $errors,
        ], 200);
    }

    public static function discard_ai_preview(WP_REST_Request $request): WP_REST_Response
    {
        WP_PQ_Admin::clear_ai_import_preview();
        return new WP_REST_Response(['ok' => true, 'message' => 'Preview discarded.'], 200);
    }

    public static function reopen_completed_task(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $task_id = (int) $request['id'];
        $target_status = WP_PQ_Workflow::normalize_status((string) $request->get_param('target_status'));
        $note = WP_PQ_Sanitizer::textarea($request, 'note');
        if (! in_array($target_status, ['in_progress', 'needs_clarification', 'needs_review'], true)) {
            return self::error_response('Choose a valid active workflow state.');
        }

        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table(self::TABLE_TASKS) . " WHERE id = %d", $task_id), ARRAY_A);
        if (! $task) {
            return self::error_response('Task not found.', 404);
        }
        $current = WP_PQ_Workflow::normalize_status((string) ($task['status'] ?? ''));
        if (! in_array($current, ['done', 'archived'], true)) {
            return self::error_response('Only completed or archived tasks can be reopened from this flow.');
        }

        $ledger_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table(self::TABLE_LEDGER_ENTRIES) . " WHERE task_id = %d", $task_id), ARRAY_A);
        $invoice_status = (string) ($ledger_entry['invoice_status'] ?? 'pending_review');
        if ($ledger_entry && in_array($invoice_status, ['invoiced', 'paid'], true)) {
            $reason_code = $invoice_status === 'paid' ? 'blocked_reopen_paid' : 'blocked_reopen_invoiced';
            $wpdb->insert(self::table(self::TABLE_HISTORY), [
                'task_id' => $task_id,
                'old_status' => $current,
                'new_status' => $current,
                'changed_by' => get_current_user_id(),
                'reason_code' => $reason_code,
                'note' => $note !== '' ? $note : 'Blocked reopen of completed work.',
                'metadata' => wp_json_encode(['requested_target_status' => $target_status]),
                'created_at' => current_time('mysql', true),
            ]);
            return new WP_REST_Response([
                'message' => $invoice_status === 'paid'
                    ? 'This completed work has already been paid. Reopen is blocked; create a follow-up task instead.'
                    : 'This completed work is already invoiced. Reopen is blocked; create a follow-up task instead.',
                'blocked' => true,
                'invoice_status' => $invoice_status,
                'suggest_followup' => true,
            ], 409);
        }

        if ($ledger_entry) {
            $wpdb->update(self::table(self::TABLE_LEDGER_ENTRIES), [
                'is_closed' => 0,
                'updated_at' => current_time('mysql', true),
            ], ['id' => (int) $ledger_entry['id']]);
        }

        $wpdb->update(self::table(self::TABLE_TASKS), [
            'status' => $target_status,
            'done_at' => null,
            'archived_at' => null,
            'completed_at' => null,
            'revision_count' => ((int) ($task['revision_count'] ?? 0)) + 1,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $task_id]);

        $wpdb->insert(self::table(self::TABLE_HISTORY), [
            'task_id' => $task_id,
            'old_status' => $current,
            'new_status' => $target_status,
            'changed_by' => get_current_user_id(),
            'reason_code' => 'reopened_after_done',
            'note' => $note !== '' ? $note : 'Completed work reopened.',
            'metadata' => wp_json_encode(['ledger_entry_id' => (int) ($ledger_entry['id'] ?? 0)]),
            'created_at' => current_time('mysql', true),
        ]);

        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table(self::TABLE_TASKS) . " WHERE id = %d", $task_id), ARRAY_A);
        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Completed task reopened.',
            'task' => $task,
        ], 200);
    }

    public static function create_followup_task(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $task_id = (int) $request['id'];
        $target_status = WP_PQ_Workflow::normalize_status((string) $request->get_param('target_status'));
        $note = WP_PQ_Sanitizer::textarea($request, 'note');
        if (! in_array($target_status, ['in_progress', 'needs_clarification', 'needs_review'], true)) {
            return self::error_response('Choose a valid active workflow state.');
        }

        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table(self::TABLE_TASKS) . " WHERE id = %d", $task_id), ARRAY_A);
        if (! $task) {
            return self::error_response('Task not found.', 404);
        }
        $followup_source_status = WP_PQ_Workflow::normalize_status((string) ($task['status'] ?? ''));
        if (! in_array($followup_source_status, ['done', 'archived'], true)) {
            return self::error_response('Only completed or archived tasks can spawn a follow-up from this flow.');
        }

        $now = current_time('mysql', true);
        $insert = [
            'title' => 'Follow-up: ' . trim((string) ($task['title'] ?? 'Untitled task')),
            'description' => (string) ($task['description'] ?? ''),
            'status' => $target_status,
            'priority' => (string) ($task['priority'] ?? 'normal'),
            'queue_position' => 0,
            'due_at' => $task['due_at'] ?: null,
            'requested_deadline' => $task['requested_deadline'] ?: null,
            'submitter_id' => (int) ($task['submitter_id'] ?? get_current_user_id()),
            'client_id' => (int) ($task['client_id'] ?? 0) ?: null,
            'client_user_id' => (int) ($task['client_user_id'] ?? 0) ?: null,
            'action_owner_id' => (int) ($task['action_owner_id'] ?? 0) ?: null,
            'owner_ids' => (string) ($task['owner_ids'] ?? ''),
            'needs_meeting' => (int) ($task['needs_meeting'] ?? 0) === 1 ? 1 : 0,
            'is_billable' => (int) ($task['is_billable'] ?? 1) === 1 ? 1 : 0,
            'billing_bucket_id' => (int) ($task['billing_bucket_id'] ?? 0) ?: null,
            'billing_mode' => (string) ($task['billing_mode'] ?? ''),
            'billing_category' => (string) ($task['billing_category'] ?? ''),
            'work_summary' => '',
            'hours' => null,
            'rate' => $task['rate'] !== null ? $task['rate'] : null,
            'amount' => null,
            'revision_count' => 0,
            'non_billable_reason' => (string) ($task['non_billable_reason'] ?? ''),
            'expense_reference' => (string) ($task['expense_reference'] ?? ''),
            'delivered_at' => null,
            'completed_at' => null,
            'done_at' => null,
            'archived_at' => null,
            'billing_status' => (int) ($task['is_billable'] ?? 1) === 1 ? 'unbilled' : 'not_billable',
            'work_log_id' => null,
            'work_logged_at' => null,
            'statement_id' => null,
            'statement_batched_at' => null,
            'source_task_id' => $task_id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $wpdb->insert(self::table(self::TABLE_TASKS), $insert);
        $new_task_id = (int) $wpdb->insert_id;
        if ($new_task_id <= 0) {
            return self::error_response('Follow-up task could not be created.', 500);
        }

        $wpdb->insert(self::table(self::TABLE_HISTORY), [
            'task_id' => $task_id,
            'old_status' => $followup_source_status,
            'new_status' => $followup_source_status,
            'changed_by' => get_current_user_id(),
            'reason_code' => 'followup_created_from_done',
            'note' => $note !== '' ? $note : 'Follow-up task created from completed work.',
            'metadata' => wp_json_encode(['followup_task_id' => $new_task_id, 'target_status' => $target_status]),
            'created_at' => $now,
        ]);

        $wpdb->insert(self::table(self::TABLE_HISTORY), [
            'task_id' => $new_task_id,
            'old_status' => null,
            'new_status' => $target_status,
            'changed_by' => get_current_user_id(),
            'reason_code' => 'followup_created_from_done',
            'note' => $note !== '' ? $note : 'Created from completed work follow-up.',
            'metadata' => wp_json_encode(['source_task_id' => $task_id]),
            'created_at' => $now,
        ]);

        $new_task = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table(self::TABLE_TASKS) . " WHERE id = %d", $new_task_id), ARRAY_A);
        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Follow-up task created.',
            'task' => $new_task,
        ], 201);
    }

    private static function range_from_request(WP_REST_Request $request): array
    {
        $month = WP_PQ_API::normalize_statement_month((string) $request->get_param('month'));
        $custom_start = WP_PQ_API::normalize_rollup_date((string) $request->get_param('start_date'));
        $custom_end = WP_PQ_API::normalize_rollup_date((string) $request->get_param('end_date'));
        if ($custom_start !== '' && $custom_end !== '' && $custom_start <= $custom_end) {
            return [
                'month' => substr($custom_end, 0, 7),
                'start' => $custom_start,
                'end' => $custom_end,
                'custom_start' => $custom_start,
                'custom_end' => $custom_end,
            ];
        }

        $month_ts = strtotime($month . '-01');
        return [
            'month' => $month,
            'start' => wp_date('Y-m-01', $month_ts),
            'end' => wp_date('Y-m-t', $month_ts),
            'custom_start' => '',
            'custom_end' => '',
        ];
    }

    private static function client_response(int $client_id): WP_REST_Response
    {
        $request = new WP_REST_Request('GET', '/pq/v1/manager/clients/' . $client_id);
        $request->set_param('id', $client_id);
        return self::get_client($request);
    }

    private static function buckets_by_client(): array
    {
        global $wpdb;

        $buckets_table = self::table(self::TABLE_BUCKETS);
        $clients_table = self::table(self::TABLE_CLIENTS);
        $rows = (array) $wpdb->get_results(
            "SELECT b.*, c.name AS client_name
             FROM {$buckets_table} b
             LEFT JOIN {$clients_table} c ON c.id = b.client_id
             ORDER BY c.name ASC, b.is_default DESC, b.bucket_name ASC",
            ARRAY_A
        );

        $by_client = [];
        foreach ($rows as $row) {
            $by_client[(int) ($row['client_id'] ?? 0)][] = $row;
        }
        return $by_client;
    }

    private static function resolve_preview_bucket_id(array $task, int $client_id, int $selected_bucket_id): int
    {
        if ($selected_bucket_id > 0) {
            return $selected_bucket_id;
        }

        $matched_bucket_id = (int) ($task['matched_bucket_id'] ?? 0);
        if ($matched_bucket_id > 0) {
            return $matched_bucket_id;
        }

        // Only create a new bucket when the preview explicitly flagged it
        // as a new job AND the user confirmed new-job creation.
        $job_name = trim((string) ($task['job_name'] ?? ''));
        if ($job_name !== '' && ! empty($task['requires_new_job'])) {
            return WP_PQ_Admin::create_bucket_for_client($client_id, $job_name);
        }

        return WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);
    }

    private static function statement_line_payload(WP_REST_Request $request, bool $allow_partial)
    {
        $line_type = WP_PQ_Sanitizer::key($request, 'line_type');
        if ($line_type === '') {
            $line_type = 'manual_adjustment';
        }

        $description = WP_PQ_Sanitizer::textarea($request, 'description');
        if (! $allow_partial && $description === '') {
            return new WP_Error('pq_missing_line_description', 'Add a line description.', ['status' => 422]);
        }

        $payload = [
            'line_type' => $line_type,
            'source_kind' => sanitize_key((string) $request->get_param('source_kind') ?: 'manual'),
            'description' => $description,
            'quantity' => self::format_decimal($request->get_param('quantity')),
            'unit' => WP_PQ_Sanitizer::text($request, 'unit'),
            'unit_rate' => self::format_decimal($request->get_param('unit_rate')),
            'line_amount' => self::format_decimal($request->get_param('line_amount')),
            'billing_bucket_id' => (int) $request->get_param('billing_bucket_id') > 0 ? (int) $request->get_param('billing_bucket_id') : null,
            'notes' => WP_PQ_Sanitizer::textarea($request, 'notes'),
            'sort_order' => max(0, (int) $request->get_param('sort_order')),
        ];

        if ($request->get_param('linked_task_ids') !== null) {
            $linked_task_ids = WP_PQ_API::sanitize_int_array($request->get_param('linked_task_ids'));
            $payload['linked_task_ids'] = ! empty($linked_task_ids) ? wp_json_encode($linked_task_ids) : null;
        }

        if ($request->get_param('source_snapshot') !== null) {
            $payload['source_snapshot'] = ! empty($request->get_param('source_snapshot')) ? wp_json_encode($request->get_param('source_snapshot')) : null;
        }

        return $payload;
    }

    private static function format_decimal($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return number_format((float) $value, 2, '.', '');
    }

    private static function statement_email_html(array $statement): string
    {
        $line_rows = '';
        foreach ((array) ($statement['lines'] ?? []) as $line) {
            $line_rows .= '<tr>'
                . '<td style="padding:8px;border:1px solid #d7deea;">' . esc_html((string) ($line['description'] ?? '')) . '</td>'
                . '<td style="padding:8px;border:1px solid #d7deea;">' . esc_html((string) ($line['line_type'] ?? '')) . '</td>'
                . '<td style="padding:8px;border:1px solid #d7deea;">' . esc_html(trim((string) ($line['quantity'] ?? '') . ' ' . (string) ($line['unit'] ?? ''))) . '</td>'
                . '<td style="padding:8px;border:1px solid #d7deea;">' . esc_html((string) ($line['unit_rate'] ?? '')) . '</td>'
                . '<td style="padding:8px;border:1px solid #d7deea;">' . esc_html((string) ($line['line_amount'] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($line_rows === '') {
            $line_rows = '<tr><td colspan="5" style="padding:8px;border:1px solid #d7deea;">No line items are attached to this draft yet.</td></tr>';
        }

        $notes = trim((string) ($statement['notes'] ?? ''));

        return '<div style="font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#172033;line-height:1.5;">'
            . '<h1 style="margin:0 0 12px;">' . esc_html((string) ($statement['statement_code'] ?? 'Invoice Draft')) . '</h1>'
            . '<p style="margin:0 0 16px;color:#52607a;">'
            . esc_html((string) ($statement['client_name'] ?? ''))
            . ' · '
            . esc_html((string) ($statement['job_summary'] ?? ''))
            . ' · '
            . esc_html((string) ($statement['statement_month'] ?? ''))
            . '</p>'
            . ($notes !== '' ? '<p style="margin:0 0 16px;">' . nl2br(esc_html($notes)) . '</p>' : '')
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="padding:8px;border:1px solid #d7deea;text-align:left;">Description</th>'
            . '<th style="padding:8px;border:1px solid #d7deea;text-align:left;">Type</th>'
            . '<th style="padding:8px;border:1px solid #d7deea;text-align:left;">Qty</th>'
            . '<th style="padding:8px;border:1px solid #d7deea;text-align:left;">Rate</th>'
            . '<th style="padding:8px;border:1px solid #d7deea;text-align:left;">Amount</th>'
            . '</tr></thead>'
            . '<tbody>' . $line_rows . '</tbody>'
            . '</table>'
            . '<p style="margin:16px 0 0;"><strong>Total:</strong> ' . esc_html((string) ($statement['total_amount'] ?? '0.00')) . ' ' . esc_html((string) ($statement['currency_code'] ?? 'USD')) . '</p>'
            . '</div>';
    }

    private static function set_statement_payment_state(int $statement_id, bool $is_paid, int $user_id)
    {
        global $wpdb;

        if ($statement_id <= 0) {
            return new WP_Error('pq_missing_statement', 'Invoice draft not found.', ['status' => 404]);
        }

        $statement = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table(self::TABLE_STATEMENTS) . " WHERE id = %d", $statement_id), ARRAY_A);
        if (! $statement) {
            return new WP_Error('pq_missing_statement', 'Invoice draft not found.', ['status' => 404]);
        }

        $now = current_time('mysql', true);
        $wpdb->update(self::table(self::TABLE_STATEMENTS), [
            'payment_status' => $is_paid ? 'paid' : 'unpaid',
            'paid_at' => $is_paid ? $now : null,
            'paid_by' => $is_paid ? $user_id : null,
            'updated_at' => $now,
        ], ['id' => $statement_id]);

        $ledger_entries_table = self::table(self::TABLE_LEDGER_ENTRIES);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$ledger_entries_table}
             SET invoice_status = %s,
                 updated_at = %s
             WHERE invoice_draft_id = %d",
            $is_paid ? 'paid' : 'invoiced',
            $now,
            $statement_id
        ));

        $tasks_table = self::table(self::TABLE_TASKS);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tasks_table}
             SET billing_status = %s,
                 updated_at = %s
             WHERE statement_id = %d",
            $is_paid ? 'paid' : 'invoiced',
            $now,
            $statement_id
        ));

        return true;
    }

    // ── Files Library ───────────────────────────────────────────

    /**
     * Return all tasks that have a files_link, permission-segregated.
     * Managers see everything; clients see only tasks they can access.
     * Supports ?client_id and ?bucket_id query filters.
     */
    public static function get_files_library(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $tasks_table  = self::table(self::TABLE_TASKS);
        $bucket_table = self::table(self::TABLE_BUCKETS);
        $user_id      = get_current_user_id();
        $is_manager   = current_user_can(WP_PQ_Roles::CAP_APPROVE);

        $where = ["t.files_link IS NOT NULL", "t.files_link != ''"];
        $params = [];

        // Permission scoping: non-managers only see tasks they can access.
        if (! $is_manager) {
            $memberships = WP_PQ_DB::get_user_client_memberships($user_id);
            $client_ids = array_map(fn($m) => (int) $m['client_id'], $memberships);
            if (empty($client_ids)) {
                return new WP_REST_Response(['items' => []], 200);
            }
            $placeholders = implode(',', array_fill(0, count($client_ids), '%d'));
            $where[] = "t.client_id IN ($placeholders)";
            $params = array_merge($params, $client_ids);
        }

        // Optional filters.
        $client_filter = WP_PQ_Sanitizer::int($request, 'client_id');
        if ($client_filter > 0) {
            $where[] = 't.client_id = %d';
            $params[] = $client_filter;
        }
        $bucket_filter = WP_PQ_Sanitizer::int($request, 'bucket_id');
        if ($bucket_filter > 0) {
            $where[] = 't.billing_bucket_id = %d';
            $params[] = $bucket_filter;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT t.id, t.title, t.files_link, t.status, t.client_id,
                       t.billing_bucket_id, t.updated_at,
                       b.bucket_name
                FROM {$tasks_table} t
                LEFT JOIN {$bucket_table} b ON b.id = t.billing_bucket_id
                WHERE {$where_sql}
                ORDER BY t.updated_at DESC
                LIMIT 500";

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        // Attach client account names.
        $client_ids = array_unique(array_filter(array_column($rows, 'client_id')));
        $client_names = [];
        if (! empty($client_ids)) {
            $accounts_table = self::table(self::TABLE_CLIENTS);
            $ph = implode(',', array_fill(0, count($client_ids), '%d'));
            $accounts = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM {$accounts_table} WHERE id IN ($ph)",
                ...$client_ids
            ), ARRAY_A) ?: [];
            foreach ($accounts as $a) {
                $client_names[(int) $a['id']] = $a['name'];
            }
        }
        foreach ($rows as &$row) {
            $row['client_name'] = $client_names[(int) ($row['client_id'] ?? 0)] ?? '';
        }
        unset($row);

        return new WP_REST_Response(['items' => $rows], 200);
    }

    // ── Invites ───────────────────────────────────────────────────

    public static function create_invite(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $first_name = sanitize_text_field(trim((string) $request->get_param('first_name')));
        $last_name = sanitize_text_field(trim((string) $request->get_param('last_name')));
        $email = strtolower(sanitize_email((string) $request->get_param('email')));
        $role = WP_PQ_Sanitizer::key($request, 'role');
        $client_id = WP_PQ_Sanitizer::int($request, 'client_id');
        $client_role = WP_PQ_Sanitizer::key($request, 'client_role');

        if ($first_name === '' || $last_name === '') {
            return self::error_response('First and last name are required.');
        }
        if (! is_email($email)) {
            return self::error_response('Valid email required.');
        }
        if (! in_array($role, ['pq_client', 'pq_worker'], true)) {
            $role = 'pq_client';
        }
        // Create new client on the fly if requested
        $new_client_name = sanitize_text_field(trim((string) $request->get_param('new_client_name')));
        if ($role === 'pq_client' && $client_id <= 0 && $new_client_name !== '') {
            $new_client_id = WP_PQ_DB::create_client($new_client_name, $email);
            if ($new_client_id <= 0) {
                return self::error_response('Failed to create client.', 500);
            }
            WP_PQ_Admin::create_bucket_for_client($new_client_id, WP_PQ_DB::suggest_default_bucket_name($new_client_id));
            $client_id = $new_client_id;
        }
        if ($role === 'pq_client' && $client_id <= 0) {
            return self::error_response('Select or create a client when inviting a client user.');
        }
        if (! in_array($client_role, ['client_admin', 'client_contributor', 'client_viewer'], true)) {
            $client_role = 'client_contributor';
        }

        // Check for existing pending invite to same email
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM " . self::table(self::TABLE_INVITES) . " WHERE email = %s AND status = 'pending' AND expires_at > NOW()",
            $email
        ));
        if ($existing) {
            return self::error_response('An active invite already exists for this email.', 409);
        }

        $token = bin2hex(random_bytes(32));
        $now = current_time('mysql', true);
        $expires = gmdate('Y-m-d H:i:s', time() + (7 * DAY_IN_SECONDS));

        $wpdb->insert(self::table(self::TABLE_INVITES), [
            'token' => $token,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'role' => $role,
            'client_id' => $client_id ?: null,
            'client_role' => $role === 'pq_client' ? $client_role : null,
            'invited_by' => get_current_user_id(),
            'status' => 'pending',
            'expires_at' => $expires,
            'created_at' => $now,
        ]);

        // Send the invite email
        $invite_url = home_url('/portal/invite/' . $token);
        $inviter = wp_get_current_user();
        $inviter_name = $inviter->display_name ?: 'Your team';
        $subject = $inviter_name . ' invited you to Switchboard';
        $body = "Hi {$first_name},\n\n"
              . "{$inviter_name} has invited you to collaborate on Switchboard.\n\n"
              . "Click below to accept and set up your account:\n\n"
              . $invite_url . "\n\n"
              . "This link expires in 7 days.";

        $invite_id = $wpdb->insert_id;
        $sent = WP_PQ_API::send_gmail(get_current_user_id(), $email, $subject, $body);
        $delivery_status = $sent ? 'sent' : 'failed';
        $wpdb->update(self::table(self::TABLE_INVITES), ['delivery_status' => $delivery_status], ['id' => $invite_id]);

        return new WP_REST_Response([
            'ok' => true,
            'message' => $sent ? 'Invite sent to ' . $email . '.' : 'Invite created but email delivery failed. Use Copy Link to share manually.',
            'invite' => [
                'id' => $invite_id,
                'email' => $email,
                'role' => $role,
                'status' => 'pending',
                'delivery_status' => $delivery_status,
                'token' => $token,
                'expires_at' => $expires,
            ],
        ], 201);
    }

    public static function list_invites(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = self::table(self::TABLE_INVITES);
        $clients_table = self::table(self::TABLE_CLIENTS);

        $rows = $wpdb->get_results(
            "SELECT i.*, c.name AS client_name
             FROM {$table} i
             LEFT JOIN {$clients_table} c ON c.id = i.client_id
             ORDER BY i.created_at DESC
             LIMIT 200",
            ARRAY_A
        ) ?: [];

        // Mark expired invites
        foreach ($rows as &$row) {
            if ($row['status'] === 'pending' && strtotime($row['expires_at']) < time()) {
                $row['status'] = 'expired';
            }
        }
        unset($row);

        return new WP_REST_Response(['invites' => $rows], 200);
    }

    public static function revoke_invite(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request['id'];
        $wpdb->update(
            self::table(self::TABLE_INVITES),
            ['status' => 'revoked'],
            ['id' => $id, 'status' => 'pending']
        );
        return new WP_REST_Response(['ok' => true, 'message' => 'Invite revoked.'], 200);
    }

    public static function resend_invite(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request['id'];
        $table = self::table(self::TABLE_INVITES);

        $invite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (! $invite) {
            return self::error_response('Invite not found.', 404);
        }
        if ($invite['status'] === 'accepted') {
            return self::error_response('This invite has already been accepted.', 409);
        }
        if ($invite['status'] === 'revoked') {
            return self::error_response('This invite has been revoked.', 409);
        }

        $new_token = bin2hex(random_bytes(32));
        $new_expires = gmdate('Y-m-d H:i:s', time() + (7 * DAY_IN_SECONDS));
        $now = current_time('mysql', true);

        $wpdb->update($table, [
            'token' => $new_token,
            'status' => 'pending',
            'expires_at' => $new_expires,
            'resent_count' => (int) ($invite['resent_count'] ?? 0) + 1,
            'last_resent_at' => $now,
        ], ['id' => $id]);

        $invite_url = home_url('/portal/invite/' . $new_token);
        $inviter = wp_get_current_user();
        $inviter_name = $inviter->display_name ?: 'Your team';
        $subject = $inviter_name . ' invited you to Switchboard';
        $body = "Hi {$invite['first_name']},\n\n"
              . "{$inviter_name} has invited you to collaborate on Switchboard.\n\n"
              . "Click below to accept and set up your account:\n\n"
              . $invite_url . "\n\n"
              . "This link expires in 7 days.";

        $sent = WP_PQ_API::send_gmail(get_current_user_id(), $invite['email'], $subject, $body);
        $delivery_status = $sent ? 'sent' : 'failed';
        $wpdb->update($table, ['delivery_status' => $delivery_status], ['id' => $id]);

        return new WP_REST_Response([
            'ok' => true,
            'message' => $sent ? 'Invite resent to ' . $invite['email'] . '.' : 'Invite updated but email delivery failed. Use Copy Link to share manually.',
            'token' => $new_token,
            'delivery_status' => $delivery_status,
        ], 200);
    }

    // ── Client Admin self-service invite handlers ─────────────────

    public static function client_list_invites(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $admin_client_ids = self::get_current_user_admin_client_ids();
        if (empty($admin_client_ids)) {
            return new WP_REST_Response(['invites' => []], 200);
        }

        $table = self::table(self::TABLE_INVITES);
        $clients_table = self::table(self::TABLE_CLIENTS);
        $placeholders = implode(',', array_fill(0, count($admin_client_ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, c.name AS client_name
             FROM {$table} i
             LEFT JOIN {$clients_table} c ON c.id = i.client_id
             WHERE i.client_id IN ({$placeholders})
             ORDER BY i.created_at DESC
             LIMIT 200",
            ...$admin_client_ids
        ), ARRAY_A) ?: [];

        foreach ($rows as &$row) {
            if ($row['status'] === 'pending' && strtotime($row['expires_at']) < time()) {
                $row['status'] = 'expired';
            }
        }
        unset($row);

        return new WP_REST_Response(['invites' => $rows], 200);
    }

    public static function client_create_invite(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $admin_client_ids = self::get_current_user_admin_client_ids();
        if (empty($admin_client_ids)) {
            return self::error_response('No client admin access.', 403);
        }

        $first_name = sanitize_text_field(trim((string) $request->get_param('first_name')));
        $last_name = sanitize_text_field(trim((string) $request->get_param('last_name')));
        $email = strtolower(sanitize_email((string) $request->get_param('email')));
        $client_role = WP_PQ_Sanitizer::key($request, 'client_role');
        $client_id = WP_PQ_Sanitizer::int($request, 'client_id');

        if ($first_name === '' || $last_name === '') {
            return self::error_response('First and last name are required.');
        }
        if (! is_email($email)) {
            return self::error_response('Valid email required.');
        }

        // Auto-set client_id if user is admin on exactly one client
        if ($client_id <= 0 && count($admin_client_ids) === 1) {
            $client_id = $admin_client_ids[0];
        }
        if ($client_id <= 0 || ! in_array($client_id, $admin_client_ids, true)) {
            return self::error_response('Invalid client selection.');
        }

        // Client admins can only invite contributors or viewers, not admins
        if (! in_array($client_role, ['client_contributor', 'client_viewer'], true)) {
            $client_role = 'client_contributor';
        }

        // Check for existing pending invite
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM " . self::table(self::TABLE_INVITES) . " WHERE email = %s AND status = 'pending' AND expires_at > NOW()",
            $email
        ));
        if ($existing) {
            return self::error_response('An active invite already exists for this email.', 409);
        }

        $token = bin2hex(random_bytes(32));
        $now = current_time('mysql', true);
        $expires = gmdate('Y-m-d H:i:s', time() + (7 * DAY_IN_SECONDS));

        $wpdb->insert(self::table(self::TABLE_INVITES), [
            'token' => $token,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'role' => 'pq_client',
            'client_id' => $client_id,
            'client_role' => $client_role,
            'invited_by' => get_current_user_id(),
            'status' => 'pending',
            'expires_at' => $expires,
            'created_at' => $now,
        ]);

        $invite_url = home_url('/portal/invite/' . $token);
        $inviter = wp_get_current_user();
        $inviter_name = $inviter->display_name ?: 'Your team';
        $subject = $inviter_name . ' invited you to Switchboard';
        $body = "Hi {$first_name},\n\n"
              . "{$inviter_name} has invited you to collaborate on Switchboard.\n\n"
              . "Click below to accept and set up your account:\n\n"
              . $invite_url . "\n\n"
              . "This link expires in 7 days.";

        $invite_id = $wpdb->insert_id;
        $sent = WP_PQ_API::send_gmail(get_current_user_id(), $email, $subject, $body);
        $delivery_status = $sent ? 'sent' : 'failed';
        $wpdb->update(self::table(self::TABLE_INVITES), ['delivery_status' => $delivery_status], ['id' => $invite_id]);

        return new WP_REST_Response([
            'ok' => true,
            'message' => $sent ? 'Invite sent to ' . $email . '.' : 'Invite created but email delivery failed. Use Copy Link to share manually.',
            'invite' => [
                'id' => $invite_id,
                'email' => $email,
                'role' => 'pq_client',
                'status' => 'pending',
                'delivery_status' => $delivery_status,
                'token' => $token,
                'expires_at' => $expires,
            ],
        ], 201);
    }

    public static function client_resend_invite(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request['id'];
        $table = self::table(self::TABLE_INVITES);
        $admin_client_ids = self::get_current_user_admin_client_ids();

        $invite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (! $invite || ! in_array((int) $invite['client_id'], $admin_client_ids, true)) {
            return self::error_response('Invite not found.', 404);
        }
        if ($invite['status'] === 'accepted') {
            return self::error_response('This invite has already been accepted.', 409);
        }
        if ($invite['status'] === 'revoked') {
            return self::error_response('This invite has been revoked.', 409);
        }

        $new_token = bin2hex(random_bytes(32));
        $new_expires = gmdate('Y-m-d H:i:s', time() + (7 * DAY_IN_SECONDS));
        $now = current_time('mysql', true);

        $wpdb->update($table, [
            'token' => $new_token,
            'status' => 'pending',
            'expires_at' => $new_expires,
            'resent_count' => (int) ($invite['resent_count'] ?? 0) + 1,
            'last_resent_at' => $now,
        ], ['id' => $id]);

        $invite_url = home_url('/portal/invite/' . $new_token);
        $inviter = wp_get_current_user();
        $inviter_name = $inviter->display_name ?: 'Your team';
        $subject = $inviter_name . ' invited you to Switchboard';
        $body = "Hi {$invite['first_name']},\n\n"
              . "{$inviter_name} has invited you to collaborate on Switchboard.\n\n"
              . "Click below to accept and set up your account:\n\n"
              . $invite_url . "\n\n"
              . "This link expires in 7 days.";

        $sent = WP_PQ_API::send_gmail(get_current_user_id(), $invite['email'], $subject, $body);
        $delivery_status = $sent ? 'sent' : 'failed';
        $wpdb->update($table, ['delivery_status' => $delivery_status], ['id' => $id]);

        return new WP_REST_Response([
            'ok' => true,
            'message' => $sent ? 'Invite resent to ' . $invite['email'] . '.' : 'Invite updated but email delivery failed. Use Copy Link to share manually.',
            'token' => $new_token,
            'delivery_status' => $delivery_status,
        ], 200);
    }

    public static function client_revoke_invite(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request['id'];
        $table = self::table(self::TABLE_INVITES);
        $admin_client_ids = self::get_current_user_admin_client_ids();

        $invite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (! $invite || ! in_array((int) $invite['client_id'], $admin_client_ids, true)) {
            return self::error_response('Invite not found.', 404);
        }

        $wpdb->update($table, ['status' => 'revoked'], ['id' => $id, 'status' => 'pending']);
        return new WP_REST_Response(['ok' => true, 'message' => 'Invite revoked.'], 200);
    }

    // ── Swimlane handlers ────────────────────────────────────────────

    public static function get_lanes(WP_REST_Request $request): WP_REST_Response
    {
        $lanes = WP_PQ_DB::get_lanes();
        return new WP_REST_Response(['lanes' => $lanes], 200);
    }

    public static function create_lane(WP_REST_Request $request): WP_REST_Response
    {
        $label = WP_PQ_Sanitizer::text($request, 'label');
        if ($label === '') {
            return self::error_response('Lane label is required.');
        }

        $client_id = $request->get_param('client_id') !== null ? (int) $request->get_param('client_id') : null;
        $client_visible = $request->get_param('client_visible') !== null
            ? rest_sanitize_boolean($request->get_param('client_visible'))
            : true;

        $lane_id = WP_PQ_DB::create_lane(get_current_user_id(), $label, $client_id, $client_visible);

        if ($lane_id <= 0) {
            return self::error_response('Failed to create lane.', 500);
        }

        $lane = WP_PQ_DB::get_lane($lane_id);
        return new WP_REST_Response(['lane' => $lane], 201);
    }

    public static function update_lane(WP_REST_Request $request): WP_REST_Response
    {
        $lane_id = (int) $request['id'];
        $lane = WP_PQ_DB::get_lane($lane_id);

        if (! $lane) {
            return self::error_response('Lane not found.', 404);
        }

        $data = [];
        if ($request->get_param('label') !== null) {
            $label = WP_PQ_Sanitizer::text($request, 'label');
            if ($label === '') {
                return self::error_response('Lane label cannot be empty.');
            }
            $data['label'] = $label;
        }
        if ($request->get_param('sort_order') !== null) {
            $data['sort_order'] = WP_PQ_Sanitizer::int($request, 'sort_order');
        }
        if ($request->get_param('client_visible') !== null) {
            $data['client_visible'] = rest_sanitize_boolean($request->get_param('client_visible'));
        }

        WP_PQ_DB::update_lane($lane_id, $data);

        return new WP_REST_Response(['lane' => WP_PQ_DB::get_lane($lane_id)], 200);
    }

    public static function delete_lane(WP_REST_Request $request): WP_REST_Response
    {
        $lane_id = (int) $request['id'];
        $lane = WP_PQ_DB::get_lane($lane_id);

        if (! $lane) {
            return self::error_response('Lane not found.', 404);
        }

        WP_PQ_DB::delete_lane($lane_id);

        return new WP_REST_Response(['ok' => true, 'message' => 'Lane deleted. Tasks moved to Uncategorized.'], 200);
    }

    /**
     * Bulk-assign tasks to a lane. Accepts { task_ids: [1,2,3] }.
     * Any tasks currently in this lane but NOT in the list get moved to Uncategorized.
     */
    public static function assign_lane_tasks(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $lane_id = (int) $request['id'];
        $lane = WP_PQ_DB::get_lane($lane_id);

        if (! $lane) {
            return self::error_response('Lane not found.', 404);
        }

        $task_ids = array_map('intval', (array) $request->get_param('task_ids'));
        $task_ids = array_values(array_filter($task_ids, static fn($id) => $id > 0));
        $tasks_table = self::table(self::TABLE_TASKS);
        $now = current_time('mysql', true);

        // Remove tasks no longer in this lane.
        if (! empty($task_ids)) {
            $ids_in = implode(',', $task_ids);
            $wpdb->query("UPDATE {$tasks_table} SET lane_id = NULL, updated_at = '{$now}' WHERE lane_id = {$lane_id} AND id NOT IN ({$ids_in})");
        } else {
            // All tasks removed from lane.
            $wpdb->update($tasks_table, ['lane_id' => null, 'updated_at' => $now], ['lane_id' => $lane_id]);
        }

        // Assign selected tasks to this lane.
        foreach ($task_ids as $tid) {
            $wpdb->update($tasks_table, ['lane_id' => $lane_id, 'updated_at' => $now], ['id' => $tid]);
        }

        $count = count($task_ids);
        return new WP_REST_Response(['ok' => true, 'count' => $count, 'message' => $count . ' task(s) assigned.'], 200);
    }

    public static function reorder_lanes(WP_REST_Request $request): WP_REST_Response
    {
        $items = (array) $request->get_param('items');
        $order = [];

        foreach ($items as $index => $item) {
            $lane_id = (int) ($item['id'] ?? 0);
            if ($lane_id > 0) {
                $order[$lane_id] = (int) $index;
            }
        }

        if (! empty($order)) {
            WP_PQ_DB::reorder_lanes($order);
        }

        return new WP_REST_Response(['ok' => true, 'lanes' => WP_PQ_DB::get_lanes()], 200);
    }

    // ── Contact info endpoints ────────────────────────

    public static function get_contacts(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = (int) $request['id'];
        if ($client_id <= 0) {
            return self::error_response('Invalid client.', 404);
        }
        return new WP_REST_Response([
            'contacts' => WP_PQ_DB::get_contact_info('client', $client_id),
        ], 200);
    }

    public static function save_contact(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = (int) $request['id'];
        if ($client_id <= 0) {
            return self::error_response('Invalid client.', 404);
        }
        $contact_id = WP_PQ_Sanitizer::int($request, 'contact_id');
        $saved_id = WP_PQ_DB::save_contact_info($contact_id, [
            'entity_type'  => 'client',
            'entity_id'    => $client_id,
            'contact_type' => (string) $request->get_param('contact_type'),
            'label'        => (string) $request->get_param('label'),
            'value'        => (string) $request->get_param('value'),
            'is_primary'   => WP_PQ_Sanitizer::bool($request, 'is_primary'),
        ]);
        if ($saved_id <= 0) {
            return self::error_response('Contact type, value required.');
        }
        return new WP_REST_Response([
            'ok' => true,
            'contact_id' => $saved_id,
            'contacts' => WP_PQ_DB::get_contact_info('client', $client_id),
        ], 200);
    }

    public static function delete_contact(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = (int) $request['id'];
        $contact_id = (int) $request['contact_id'];
        WP_PQ_DB::delete_contact_info($contact_id);
        return new WP_REST_Response([
            'ok' => true,
            'contacts' => WP_PQ_DB::get_contact_info('client', $client_id),
        ], 200);
    }

    public static function get_member_contacts(WP_REST_Request $request): WP_REST_Response
    {
        $member_id = (int) $request['member_id'];
        if ($member_id <= 0) {
            return self::error_response('Invalid member.', 404);
        }
        return new WP_REST_Response([
            'contacts' => WP_PQ_DB::get_contact_info('member', $member_id),
        ], 200);
    }

    public static function save_member_contact(WP_REST_Request $request): WP_REST_Response
    {
        $member_id = (int) $request['member_id'];
        if ($member_id <= 0) {
            return self::error_response('Invalid member.', 404);
        }
        $contact_id = WP_PQ_Sanitizer::int($request, 'contact_id');
        $saved_id = WP_PQ_DB::save_contact_info($contact_id, [
            'entity_type'  => 'member',
            'entity_id'    => $member_id,
            'contact_type' => (string) $request->get_param('contact_type'),
            'label'        => (string) $request->get_param('label'),
            'value'        => (string) $request->get_param('value'),
            'is_primary'   => WP_PQ_Sanitizer::bool($request, 'is_primary'),
        ]);
        if ($saved_id <= 0) {
            return self::error_response('Contact type, value required.');
        }
        return new WP_REST_Response([
            'ok' => true,
            'contact_id' => $saved_id,
            'contacts' => WP_PQ_DB::get_contact_info('member', $member_id),
        ], 200);
    }

    public static function delete_member_contact(WP_REST_Request $request): WP_REST_Response
    {
        $member_id = (int) $request['member_id'];
        $contact_id = (int) $request['contact_id'];
        WP_PQ_DB::delete_contact_info($contact_id);
        return new WP_REST_Response([
            'ok' => true,
            'contacts' => WP_PQ_DB::get_contact_info('member', $member_id),
        ], 200);
    }
}
