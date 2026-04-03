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
        $installed = get_option('wp_pq_db_version', '');
        if ($installed === WP_PQ_VERSION) {
            return;
        }

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
        $ledger_entries = $wpdb->prefix . 'pq_work_ledger_entries';
        $invites = $wpdb->prefix . 'pq_invites';

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
            status VARCHAR(50) NOT NULL DEFAULT 'pending_approval',
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
            billing_mode VARCHAR(40) NULL,
            billing_category VARCHAR(80) NULL,
            work_summary LONGTEXT NULL,
            hours DECIMAL(10,2) NULL,
            rate DECIMAL(12,2) NULL,
            amount DECIMAL(12,2) NULL,
            revision_count INT UNSIGNED NOT NULL DEFAULT 0,
            non_billable_reason LONGTEXT NULL,
            expense_reference VARCHAR(191) NULL,
            delivered_at DATETIME NULL,
            completed_at DATETIME NULL,
            done_at DATETIME NULL,
            archived_at DATETIME NULL,
            billing_status VARCHAR(30) NOT NULL DEFAULT 'unbilled',
            work_log_id BIGINT UNSIGNED NULL,
            work_logged_at DATETIME NULL,
            statement_id BIGINT UNSIGNED NULL,
            statement_batched_at DATETIME NULL,
            source_task_id BIGINT UNSIGNED NULL,
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
            KEY source_task_id (source_task_id),
            KEY queue_position (queue_position),
            KEY google_event_id (google_event_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$history} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NOT NULL,
            changed_by BIGINT UNSIGNED NOT NULL,
            reason_code VARCHAR(50) NULL,
            note LONGTEXT NULL,
            metadata LONGTEXT NULL,
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
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            paid_at DATETIME NULL,
            paid_by BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY statement_code (statement_code),
            KEY statement_month (statement_month),
            KEY client_id (client_id),
            KEY client_user_id (client_user_id),
            KEY billing_bucket_id (billing_bucket_id),
            KEY payment_status (payment_status)
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

        dbDelta("CREATE TABLE {$ledger_entries} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            billing_bucket_id BIGINT UNSIGNED NULL,
            title_snapshot VARCHAR(255) NOT NULL,
            work_summary LONGTEXT NULL,
            owner_id BIGINT UNSIGNED NULL,
            completion_date DATETIME NOT NULL,
            billable TINYINT(1) NOT NULL DEFAULT 1,
            billing_mode VARCHAR(40) NULL,
            billing_category VARCHAR(80) NULL,
            is_closed TINYINT(1) NOT NULL DEFAULT 1,
            invoice_status VARCHAR(30) NOT NULL DEFAULT 'unbilled',
            statement_month VARCHAR(7) NULL,
            invoice_draft_id BIGINT UNSIGNED NULL,
            hours DECIMAL(10,2) NULL,
            rate DECIMAL(12,2) NULL,
            amount DECIMAL(12,2) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY task_id (task_id),
            KEY client_id (client_id),
            KEY billing_bucket_id (billing_bucket_id),
            KEY is_closed (is_closed),
            KEY invoice_status (invoice_status),
            KEY statement_month (statement_month),
            KEY invoice_draft_id (invoice_draft_id)
        ) {$charset_collate};");

        $lanes = $wpdb->prefix . 'pq_lanes';

        dbDelta("CREATE TABLE {$lanes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            manager_user_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            label VARCHAR(100) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            client_visible TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY manager_user_id (manager_user_id),
            KEY client_id (client_id),
            KEY sort_order (sort_order)
        ) {$charset_collate};");

        // Add lane_id column to tasks table if it doesn't exist.
        $lane_col = $wpdb->get_results("SHOW COLUMNS FROM {$tasks} LIKE 'lane_id'");
        if (empty($lane_col)) {
            $wpdb->query("ALTER TABLE {$tasks} ADD COLUMN lane_id BIGINT UNSIGNED NULL AFTER billing_bucket_id");
            $wpdb->query("ALTER TABLE {$tasks} ADD KEY lane_id (lane_id)");
        }

        dbDelta("CREATE TABLE {$invites} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            first_name VARCHAR(100) NOT NULL DEFAULT '',
            last_name VARCHAR(100) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL,
            role VARCHAR(40) NOT NULL DEFAULT 'pq_client',
            client_id BIGINT UNSIGNED NULL,
            client_role VARCHAR(40) NULL DEFAULT 'client_contributor',
            invited_by BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            delivery_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
            expires_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            accepted_user_id BIGINT UNSIGNED NULL,
            resent_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_resent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY email (email),
            KEY status_expires (status, expires_at),
            KEY email_client_status (email, client_id, status)
        ) {$charset_collate};");

        update_option('wp_pq_db_version', WP_PQ_VERSION, true);
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

        $primary_user_id = self::get_primary_contact_user_id($client_id);
        $bucket_name = self::suggest_default_bucket_name($client_id);
        $created_by = get_current_user_id();
        if ($created_by <= 0) {
            $created_by = $primary_user_id;
        }
        if ($created_by <= 0) {
            $created_by = 1;
        }

        $wpdb->insert($buckets_table, [
            'client_id' => $client_id,
            'client_user_id' => $primary_user_id,
            'bucket_name' => $bucket_name,
            'bucket_slug' => sanitize_title($bucket_name),
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

    public static function create_client(string $name, string $email = ''): int
    {
        global $wpdb;
        $clients_table = $wpdb->prefix . 'pq_clients';
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $base_slug = sanitize_title($name);
        if ($base_slug === '') {
            $base_slug = 'client';
        }
        $slug = $base_slug;
        $suffix = 2;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$clients_table} WHERE slug = %s", $slug)) > 0) {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }
        $now = current_time('mysql', true);
        $wpdb->insert($clients_table, [
            'name' => $name,
            'slug' => $slug,
            'primary_contact_user_id' => 0,
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Create a new WordPress user with a unique username derived from the email.
     *
     * Generates a login from the email local-part, appending a numeric suffix
     * if needed to guarantee uniqueness.  Returns the new user ID or WP_Error.
     *
     * Accepted $args keys:
     *   display_name      (string)  Defaults to the email address.
     *   nickname          (string)  Defaults to display_name.
     *   first_name        (string)  Optional.
     *   last_name         (string)  Optional.
     *   role              (string)  Defaults to 'pq_client'.
     *   password          (string)  Defaults to a random 24-char password.
     *   username_fallback (string)  Fallback base login when the email local-part
     *                               sanitises to an empty string. Defaults to 'client'.
     *
     * @param  string        $email  Valid email address for the new user.
     * @param  array<string,string> $args   Optional overrides (see above).
     * @return int|\WP_Error  New user ID on success, WP_Error on failure.
     */
    public static function create_wp_user(string $email, array $args = []) {
        $fallback   = (string) ($args['username_fallback'] ?? 'client');
        $base_login = sanitize_user((string) current(explode('@', $email)), true);
        if ($base_login === '') {
            $base_login = $fallback !== '' ? $fallback : 'client';
        }

        $login  = $base_login;
        $suffix = 1;
        while (username_exists($login)) {
            $suffix++;
            $login = $base_login . $suffix;
        }

        $display_name = (string) ($args['display_name'] ?? $email);
        $password     = (string) ($args['password']     ?? wp_generate_password(24, true, true));
        $role         = (string) ($args['role']         ?? 'pq_client');
        $nickname     = (string) ($args['nickname']     ?? $display_name);

        $user_data = [
            'user_login'   => $login,
            'user_pass'    => $password,
            'user_email'   => $email,
            'display_name' => $display_name,
            'nickname'     => $nickname,
            'role'         => $role,
        ];

        if (isset($args['first_name']) && $args['first_name'] !== '') {
            $user_data['first_name'] = (string) $args['first_name'];
        }
        if (isset($args['last_name']) && $args['last_name'] !== '') {
            $user_data['last_name'] = (string) $args['last_name'];
        }

        return wp_insert_user($user_data);
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

        $user = WP_PQ_API::get_cached_user($primary_contact_user_id);
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

        if ((int) $wpdb->insert_id > 0) {
            return (int) $wpdb->insert_id;
        }

        // Slug collision from concurrent request — re-check for existing client.
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$clients_table} WHERE primary_contact_user_id = %d LIMIT 1",
            $primary_contact_user_id
        ));
        return $existing_id;
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

    public static function remove_job_member(int $billing_bucket_id, int $user_id): bool
    {
        global $wpdb;

        if ($billing_bucket_id <= 0 || $user_id <= 0) {
            return false;
        }

        $table = $wpdb->prefix . 'pq_job_members';
        $deleted = $wpdb->delete($table, [
            'billing_bucket_id' => $billing_bucket_id,
            'user_id' => $user_id,
        ]);
        self::clear_job_membership_cache($billing_bucket_id, $user_id);
        return $deleted > 0;
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

    // ── Swimlane helpers ─────────────────────────────────────────────

    /**
     * Get all lanes, optionally filtered by manager.
     *
     * @return array<int, array{id: int, manager_user_id: int, client_id: int|null, label: string, sort_order: int, client_visible: bool}>
     */
    public static function get_lanes(int $manager_user_id = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_lanes';

        if ($manager_user_id > 0) {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE manager_user_id = %d ORDER BY sort_order ASC, id ASC", $manager_user_id),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A);
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'manager_user_id' => (int) $row['manager_user_id'],
                'client_id' => $row['client_id'] !== null ? (int) $row['client_id'] : null,
                'label' => (string) $row['label'],
                'sort_order' => (int) $row['sort_order'],
                'client_visible' => (bool) $row['client_visible'],
                'created_at' => (string) $row['created_at'],
            ];
        }, $rows ?: []);
    }

    /**
     * Get a single lane by ID.
     */
    public static function get_lane(int $lane_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_lanes';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $lane_id), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Create a new lane and return its ID.
     */
    public static function create_lane(int $manager_user_id, string $label, ?int $client_id = null, bool $client_visible = true): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_lanes';

        $max_sort = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM {$table} WHERE manager_user_id = %d", $manager_user_id)
        );

        $wpdb->insert($table, [
            'manager_user_id' => $manager_user_id,
            'client_id' => $client_id,
            'label' => $label,
            'sort_order' => $max_sort + 1,
            'client_visible' => $client_visible ? 1 : 0,
            'created_at' => current_time('mysql', true),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a lane's properties.
     */
    public static function update_lane(int $lane_id, array $data): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_lanes';

        $update = [];
        if (isset($data['label'])) {
            $update['label'] = sanitize_text_field($data['label']);
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['client_visible'])) {
            $update['client_visible'] = $data['client_visible'] ? 1 : 0;
        }

        if (empty($update)) {
            return false;
        }

        return $wpdb->update($table, $update, ['id' => $lane_id]) !== false;
    }

    /**
     * Delete a lane and reassign its tasks to Uncategorized (lane_id = NULL).
     */
    public static function delete_lane(int $lane_id): bool
    {
        global $wpdb;
        $lanes_table = $wpdb->prefix . 'pq_lanes';
        $tasks_table = $wpdb->prefix . 'pq_tasks';

        // Move tasks out of this lane.
        $wpdb->update($tasks_table, ['lane_id' => null], ['lane_id' => $lane_id]);

        return $wpdb->delete($lanes_table, ['id' => $lane_id]) !== false;
    }

    /**
     * Bulk-update lane sort orders.
     *
     * @param array<int, int> $order Map of lane_id => sort_order
     */
    public static function reorder_lanes(array $order): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_lanes';

        foreach ($order as $lane_id => $sort_order) {
            $wpdb->update($table, ['sort_order' => (int) $sort_order], ['id' => (int) $lane_id]);
        }
    }

    /**
     * Build a lane label lookup map for enriching task rows.
     *
     * @return array<int, string> lane_id => label
     */
    public static function get_lane_labels(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pq_lanes';
        $rows = $wpdb->get_results("SELECT id, label FROM {$table}", ARRAY_A);
        $map = [];
        foreach ($rows ?: [] as $row) {
            $map[(int) $row['id']] = (string) $row['label'];
        }
        return $map;
    }
}
