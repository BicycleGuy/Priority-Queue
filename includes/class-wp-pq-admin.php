<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_post_wp_pq_google_oauth_start', [self::class, 'handle_google_oauth_start']);
        add_action('admin_post_wp_pq_create_client', [self::class, 'handle_create_client']);
        add_action('admin_post_wp_pq_link_client', [self::class, 'handle_link_client']);
        add_action('admin_post_wp_pq_create_bucket', [self::class, 'handle_create_bucket']);
        add_action('admin_post_wp_pq_assign_bucket', [self::class, 'handle_assign_bucket']);
        add_action('admin_post_wp_pq_create_work_log', [self::class, 'handle_create_work_log']);
        add_action('admin_post_wp_pq_export_work_log', [self::class, 'handle_export_work_log']);
        add_action('admin_post_wp_pq_print_work_log', [self::class, 'handle_print_work_log']);
        add_action('admin_post_wp_pq_create_statement', [self::class, 'handle_create_statement']);
        add_action('admin_post_wp_pq_export_statement', [self::class, 'handle_export_statement']);
        add_action('admin_post_wp_pq_print_statement', [self::class, 'handle_print_statement']);
        add_action('admin_post_wp_pq_update_statement', [self::class, 'handle_update_statement']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('plugin_action_links_' . plugin_basename(WP_PQ_PLUGIN_FILE), [self::class, 'plugin_action_links']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            'Priority Queue',
            'Priority Queue',
            'read',
            'wp-pq-queue',
            [self::class, 'render_page'],
            'dashicons-list-view',
            26
        );

        add_submenu_page(
            'wp-pq-queue',
            'Priority Queue Clients',
            'Clients',
            'read',
            'wp-pq-client-directory',
            [self::class, 'render_clients_page']
        );

        add_users_page(
            'Priority Queue Clients',
            'PQ Clients',
            'list_users',
            'wp-pq-client-directory',
            [self::class, 'render_clients_page']
        );

        add_submenu_page(
            'wp-pq-queue',
            'Priority Queue Billing Rollup',
            'Billing Rollup',
            'read',
            'wp-pq-rollups',
            [self::class, 'render_rollups_page']
        );

        add_submenu_page(
            'wp-pq-queue',
            'Priority Queue Statements',
            'Statements',
            'read',
            'wp-pq-statements',
            [self::class, 'render_statements_page']
        );

        add_submenu_page(
            'wp-pq-queue',
            'Priority Queue Settings',
            'Settings',
            'read',
            'wp-pq-settings',
            [self::class, 'render_settings_page']
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_wp-pq-queue' && strpos($hook, 'wp-pq-settings') === false && strpos($hook, 'wp-pq-statements') === false && strpos($hook, 'wp-pq-rollups') === false && strpos($hook, 'wp-pq-client-directory') === false) {
            return;
        }

        wp_enqueue_style('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/css/admin-queue.css', [], WP_PQ_VERSION);
        if (strpos($hook, 'wp-pq-statements') !== false || strpos($hook, 'wp-pq-rollups') !== false || strpos($hook, 'wp-pq-client-directory') !== false) {
            return;
        }

        wp_enqueue_script('sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js', [], '1.15.6', true);
        wp_enqueue_script('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue.js', ['wp-api-fetch', 'sortable-js'], WP_PQ_VERSION, true);

        wp_localize_script('wp-pq-admin', 'wpPqConfig', [
            'root' => esc_url_raw(rest_url('pq/v1/')),
            'coreRoot' => esc_url_raw(rest_url('wp/v2/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'canApprove' => current_user_can(WP_PQ_Roles::CAP_APPROVE),
            'canAssign' => current_user_can(WP_PQ_Roles::CAP_ASSIGN),
            'canBatch' => current_user_can(WP_PQ_Roles::CAP_APPROVE),
            'canViewAll' => current_user_can(WP_PQ_Roles::CAP_VIEW_ALL),
            'currentUserId' => get_current_user_id(),
        ]);
    }

    public static function register_settings(): void
    {
        register_setting('wp_pq_settings_group', 'wp_pq_google_client_id', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('wp_pq_settings_group', 'wp_pq_google_client_secret', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('wp_pq_settings_group', 'wp_pq_google_redirect_uri', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('wp_pq_settings_group', 'wp_pq_google_scopes', ['sanitize_callback' => 'sanitize_text_field']);
    }

    public static function plugin_action_links(array $links): array
    {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('admin.php?page=wp-pq-client-directory')) . '">Clients</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=wp-pq-rollups')) . '">Billing Rollup</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=wp-pq-statements')) . '">Statements</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=wp-pq-settings')) . '">Settings</a>'
        );
        return $links;
    }

    public static function render_page(): void
    {
        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Priority Queue</h1>';
        echo '<p>Workflow scaffold: queue, approvals, owners, status transitions, files, and notifications.</p>';
        echo self::admin_section_nav('queue');

        echo '<div class="wp-pq-grid">';
        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Create Request</h2>';
        echo '    <form id="wp-pq-create-form">';
        echo '      <label>Title <input type="text" name="title" required></label>';
        echo '      <label>Description <textarea name="description" rows="3"></textarea></label>';
        echo '      <label>Priority';
        echo '        <select name="priority">';
        echo '          <option value="low">Low</option>';
        echo '          <option value="normal" selected>Normal</option>';
        echo '          <option value="high">High</option>';
        echo '          <option value="urgent">Urgent</option>';
        echo '        </select>';
        echo '      </label>';
        echo '      <label>Due Date <input type="datetime-local" name="due_at" step="900"></label>';
        echo '      <label>Requested Deadline <input type="datetime-local" name="requested_deadline" step="900"></label>';
        echo '      <label class="inline"><input type="checkbox" name="needs_meeting"> Meeting Requested</label>';
        echo '      <label>Owner IDs (comma-separated) <input type="text" name="owner_ids" placeholder="12,34"></label>';
        echo '      <button class="button button-primary" type="submit">Create Request</button>';
        echo '    </form>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Queue</h2>';
        echo '    <p class="desc">Drag to reorder. Clients only move their own tasks; managers can move all tasks.</p>';
        echo '    <ul id="wp-pq-task-list" class="wp-pq-task-list"></ul>';
        echo '  </section>';

        echo '</div>';
        echo '</div>';
    }

    public static function render_clients_page(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $clients = self::get_billing_clients();
        $client_details = self::get_client_directory_rows();
        $linkable_users = self::get_linkable_users();

        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Clients</h1>';
        echo '<p>Create clients, link existing WordPress users as clients, manage billing buckets, and jump straight into that client\'s work logs and statements.</p>';
        echo self::admin_section_nav('clients');

        if (isset($_GET['wp_pq_notice'])) {
            $notice = sanitize_key((string) $_GET['wp_pq_notice']);
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';
            $class = in_array($notice, ['client_saved', 'bucket_saved'], true) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
        }

        echo self::render_client_datalist($clients, 'wp-pq-client-options');
        echo self::render_user_datalist($linkable_users, 'wp-pq-user-options');

        echo '<div class="wp-pq-billing-grid">';
        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Create Client</h2>';
        echo '    <p class="wp-pq-panel-note">Create a new WordPress user with the client role and set the first job bucket right away.</p>';
        echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
        wp_nonce_field('wp_pq_create_client');
        echo '      <input type="hidden" name="action" value="wp_pq_create_client">';
        echo '      <input type="hidden" name="redirect_page" value="wp-pq-client-directory">';
        echo '      <label>Client name <input type="text" name="client_name" placeholder="Read Spear" required></label>';
        echo '      <label>Client email <input type="email" name="client_email" placeholder="client@example.com" required></label>';
        echo '      <label>First job bucket <input type="text" name="initial_bucket_name" placeholder="Client Name - Main" required></label>';
        echo '      <button class="button button-primary" type="submit">Create Client</button>';
        echo '    </form>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Link Existing User</h2>';
        echo '    <p class="wp-pq-panel-note">Promote any existing WordPress user into the client directory without changing their other roles.</p>';
        echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
        wp_nonce_field('wp_pq_link_client');
        echo '      <input type="hidden" name="action" value="wp_pq_link_client">';
        echo '      <input type="hidden" name="redirect_page" value="wp-pq-client-directory">';
        echo self::render_user_picker('existing-user-link', 'user_id', $linkable_users, 0, 'Existing user', 'Type a WordPress username or email', true);
        echo '      <label>First job bucket <input type="text" name="initial_bucket_name" placeholder="Client Name - Main" required></label>';
        echo '      <button class="button" type="submit">Link User as Client</button>';
        echo '    </form>';
        echo '  </section>';
        echo '</div>';

        echo '<div class="wp-pq-rollup-groups">';
        if (empty($client_details)) {
            echo '<section class="wp-pq-panel"><p class="wp-pq-empty-state">No clients have been created yet.</p></section>';
        } else {
            foreach ($client_details as $client) {
                $rollup_url = add_query_arg([
                    'page' => 'wp-pq-rollups',
                    'client_user_id' => (int) $client['id'],
                ], admin_url('admin.php'));
                $statement_url = add_query_arg([
                    'page' => 'wp-pq-statements',
                    'client_user_id' => (int) $client['id'],
                ], admin_url('admin.php'));
                echo '<section class="wp-pq-panel wp-pq-rollup-group">';
                echo '<h2>' . esc_html((string) $client['label']) . '</h2>';
                echo '<p class="wp-pq-panel-note">Delivered tasks: ' . (int) $client['delivered_count'] . ' · Unbilled: ' . (int) $client['unbilled_count'] . ' · Work logs: ' . (int) $client['work_log_count'] . ' · Statements: ' . (int) $client['statement_count'] . '.</p>';
                echo '<div class="wp-pq-chip-row">';
                foreach ($client['buckets'] as $bucket) {
                    echo '<span class="wp-pq-detail-pill">' . esc_html(self::bucket_label_from_row($bucket)) . '</span>';
                }
                echo '</div>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form">';
                wp_nonce_field('wp_pq_create_bucket');
                echo '  <input type="hidden" name="action" value="wp_pq_create_bucket">';
                echo '  <input type="hidden" name="redirect_page" value="wp-pq-client-directory">';
                echo '  <input type="hidden" name="client_user_id" value="' . (int) $client['id'] . '">';
                echo '  <label>Add bucket <input type="text" name="bucket_name" placeholder="Retainer, Launch, etc." required></label>';
                echo '  <button class="button" type="submit">Add Bucket</button>';
                echo '</form>';
                echo '<div class="wp-pq-inline-action-form">';
                echo '  <a class="button" href="' . esc_url($rollup_url) . '">View Billing Rollup</a>';
                echo '  <a class="button" href="' . esc_url($statement_url) . '">View Statements</a>';
                echo '</div>';
                if (! empty($client['recent_work_logs'])) {
                    echo '<h3>Recent Work Logs</h3>';
                    echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Bucket</th><th>Range</th><th>Tasks</th></tr></thead><tbody>';
                    foreach ($client['recent_work_logs'] as $work_log) {
                        echo '<tr><td>' . esc_html((string) $work_log['work_log_code']) . '</td><td>' . esc_html(self::bucket_label_from_row($work_log)) . '</td><td>' . esc_html(self::format_date_range((string) $work_log['range_start'], (string) $work_log['range_end'])) . '</td><td>' . (int) $work_log['task_count'] . '</td></tr>';
                    }
                    echo '</tbody></table>';
                }
                if (! empty($client['recent_statements'])) {
                    echo '<h3>Recent Statements</h3>';
                    echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Bucket</th><th>Range</th><th>Tasks</th></tr></thead><tbody>';
                    foreach ($client['recent_statements'] as $statement) {
                        echo '<tr><td>' . esc_html((string) $statement['statement_code']) . '</td><td>' . esc_html(self::bucket_label_from_row($statement)) . '</td><td>' . esc_html(self::format_date_range((string) $statement['range_start'], (string) $statement['range_end'])) . '</td><td>' . (int) $statement['task_count'] . '</td></tr>';
                    }
                    echo '</tbody></table>';
                }
                echo '</section>';
            }
        }
        echo '</div>';
        echo self::client_picker_script();
    }

    public static function render_settings_page(): void
    {
        $redirect_uri = (string) get_option('wp_pq_google_redirect_uri', home_url('/wp-json/pq/v1/google/oauth/callback'));
        $tokens = (array) get_option('wp_pq_google_tokens', []);
        $is_connected = ! empty($tokens['refresh_token']);
        $oauth_url = wp_nonce_url(admin_url('admin-post.php?action=wp_pq_google_oauth_start'), 'wp_pq_google_oauth_start');

        echo '<div class="wrap wp-pq-wrap wp-pq-settings-page">';
        echo '<h1>Priority Queue Settings</h1>';
        echo '<p>Manage workflow email preferences and Google Calendar / Meet integration here.</p>';
        echo self::admin_section_nav('settings');

        echo '<div class="wp-pq-grid wp-pq-settings-grid">';
        echo '  <section class="wp-pq-panel wp-pq-settings-panel">';
        echo '    <h2>Notifications</h2>';
        echo '    <p class="wp-pq-panel-note">Choose which workflow emails you want. In-app alerts stay on.</p>';
        echo '    <div id="wp-pq-pref-list" class="wp-pq-pref-list"></div>';
        echo '    <button class="button button-primary" id="wp-pq-save-prefs" type="button">Save Preferences</button>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel wp-pq-settings-panel">';
        echo '    <h2>Google Calendar &amp; Meet</h2>';
        echo '    <p class="wp-pq-panel-note">Enter your Google OAuth app details, then connect Google Calendar to enable meeting scheduling and calendar sync.</p>';
        echo '    <form method="post" action="options.php">';
        settings_fields('wp_pq_settings_group');
        echo '      <label>Google Client ID <input type="text" name="wp_pq_google_client_id" value="' . esc_attr((string) get_option('wp_pq_google_client_id', '')) . '"></label>';
        echo '      <label>Google Client Secret <input type="text" name="wp_pq_google_client_secret" value="' . esc_attr((string) get_option('wp_pq_google_client_secret', '')) . '"></label>';
        echo '      <label>Authorized Redirect URI <input type="text" name="wp_pq_google_redirect_uri" value="' . esc_attr($redirect_uri) . '"></label>';
        echo '      <label>Scopes <input type="text" name="wp_pq_google_scopes" value="' . esc_attr((string) get_option('wp_pq_google_scopes', 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly')) . '"></label>';
        echo '      <div class="wp-pq-create-actions">';
        echo '        <button class="button button-primary" type="submit">Save Google Settings</button>';
        echo '      </div>';
        echo '    </form>';
        echo '    <div class="wp-pq-admin-callout">';
        echo '      <p><strong>Status:</strong> ' . ($is_connected ? 'Connected' : 'Not connected') . '</p>';
        echo '      <p><strong>Set this redirect URI in Google Cloud:</strong> ' . esc_html($redirect_uri) . '</p>';
        echo '      <p><a href="' . esc_url($oauth_url) . '" class="button">Connect Google Calendar</a></p>';
        echo '      <p><a href="https://console.cloud.google.com/apis/library/calendar-json.googleapis.com" target="_blank" rel="noopener">Open Google Cloud Console</a></p>';
        echo '    </div>';
        echo '  </section>';
        echo '</div>';
        echo '</div>';
    }

    public static function render_rollups_page(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $range = self::get_rollup_range();
        $selected_client_id = isset($_GET['client_user_id']) ? (int) $_GET['client_user_id'] : 0;
        $clients = self::get_billing_clients();
        $buckets_by_client = self::get_buckets_by_client();
        $groups = self::get_rollup_groups($range['start'], $range['end'], $buckets_by_client);
        if ($selected_client_id > 0) {
            $groups = array_values(array_filter($groups, static function (array $group) use ($selected_client_id): bool {
                return (int) ($group['client_id'] ?? 0) === $selected_client_id;
            }));
        }
        $work_logs = self::get_work_log_summaries($range['start'], $range['end']);
        $statements = self::get_statement_summaries_for_range($range['start'], $range['end']);
        if ($selected_client_id > 0) {
            $work_logs = array_values(array_filter($work_logs, static function (array $row) use ($selected_client_id): bool {
                return (int) ($row['client_user_id'] ?? 0) === $selected_client_id;
            }));
            $statements = array_values(array_filter($statements, static function (array $row) use ($selected_client_id): bool {
                return (int) ($row['client_user_id'] ?? 0) === $selected_client_id;
            }));
        }
        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Billing Rollup</h1>';
        echo '<p>Review delivered work by date range, sort it into client billing buckets, then create either a work log or a billing statement.</p>';
        echo self::admin_section_nav('rollups');

        if (isset($_GET['wp_pq_notice'])) {
            $notice = sanitize_key((string) $_GET['wp_pq_notice']);
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';
            $class = in_array($notice, ['bucket_saved', 'work_log_created', 'statement_created'], true) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<div class="wp-pq-panel wp-pq-filter-bar">';
        echo '  <form method="get" class="wp-pq-period-form">';
        echo '    <input type="hidden" name="page" value="wp-pq-rollups">';
        echo '    <input type="hidden" name="client_user_id" value="' . (int) $selected_client_id . '">';
        echo '    <label>Month';
        echo '      <input type="month" name="month" value="' . esc_attr($range['month']) . '">';
        echo '    </label>';
        echo '    <label>Custom Start';
        echo '      <input type="date" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '    </label>';
        echo '    <label>Custom End';
        echo '      <input type="date" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        echo '    </label>';
        echo '    <button class="button" type="submit">Apply Range</button>';
        echo '  </form>';
        echo '  <p class="wp-pq-panel-note">Current range: ' . esc_html($range['label']) . '</p>';
        echo '</div>';

        echo '<div class="wp-pq-billing-grid">';
        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Clients &amp; Buckets</h2>';
        echo '    <p class="wp-pq-panel-note">Create a client, give them a first job bucket, then sort delivered work into those client buckets.</p>';
        echo self::render_client_datalist($clients, 'wp-pq-client-options');
        echo '    <div class="wp-pq-admin-stack">';
        echo '      <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
        wp_nonce_field('wp_pq_create_client');
        echo '        <input type="hidden" name="action" value="wp_pq_create_client">';
        echo '        <input type="hidden" name="redirect_page" value="wp-pq-rollups">';
        echo '        <input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
        echo '        <input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '        <input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        echo '        <h3>Create Client</h3>';
        echo '        <label>Client name <input type="text" name="client_name" placeholder="Read Spear" required></label>';
        echo '        <label>Client email <input type="email" name="client_email" placeholder="client@example.com" required></label>';
        echo '        <label>First job bucket <input type="text" name="initial_bucket_name" placeholder="Client Name - Main" required></label>';
        echo '        <button class="button button-primary" type="submit">Create Client</button>';
        echo '      </form>';
        echo '      <form method="get" class="wp-pq-bucket-form wp-pq-client-filter-form">';
        echo '        <input type="hidden" name="page" value="wp-pq-rollups">';
        echo '        <input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
        echo '        <input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '        <input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        echo '        <h3>Find Client</h3>';
        echo self::render_client_picker('rollup-client-filter', 'client_user_id', $clients, $selected_client_id, 'Search client', 'Type a client name or email');
        echo '        <div class="wp-pq-inline-action-form">';
        echo '          <button class="button" type="submit">Show Client</button>';
        if ($selected_client_id > 0) {
            echo '      <a class="button" href="' . esc_url(add_query_arg([
                'page' => 'wp-pq-rollups',
                'month' => $range['month'],
                'start_date' => $range['custom_start'],
                'end_date' => $range['custom_end'],
            ], admin_url('admin.php'))) . '">Clear</a>';
        }
        echo '        </div>';
        echo '      </form>';
        echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
        wp_nonce_field('wp_pq_create_bucket');
        echo '      <input type="hidden" name="action" value="wp_pq_create_bucket">';
        echo '      <input type="hidden" name="redirect_page" value="wp-pq-rollups">';
        echo '      <input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
        echo '      <input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '      <input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        echo '      <h3>Define Bucket</h3>';
        echo self::render_client_picker('bucket-client-picker', 'client_user_id', $clients, $selected_client_id, 'Client', 'Type a client name or email', true);
        echo '      <label>Bucket name <input type="text" name="bucket_name" placeholder="Website retainer" required></label>';
        echo '      <button class="button button-primary" type="submit">Add Bucket</button>';
        echo '    </form>';
        echo '    </div>';

        if (! empty($buckets_by_client)) {
            echo '<div class="wp-pq-bucket-list">';
            foreach ($buckets_by_client as $client_id => $client_buckets) {
                if ($selected_client_id > 0 && $client_id !== $selected_client_id) {
                    continue;
                }
                $client_name = self::client_label_from_row($client_buckets[0]);
                echo '<div class="wp-pq-bucket-group">';
                echo '<strong>' . esc_html((string) $client_name) . '</strong>';
                echo '<div class="wp-pq-chip-row">';
                foreach ($client_buckets as $bucket) {
                    echo '<span class="wp-pq-detail-pill">' . esc_html(self::bucket_label_from_row($bucket)) . '</span>';
                }
                echo '</div></div>';
            }
            echo '</div>';
        }
        echo '  </section>';

        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Existing Output</h2>';
        echo '    <p class="wp-pq-panel-note">Documents created from this date range.</p>';
        echo '    <h3>Work Logs</h3>';
        if (empty($work_logs)) {
            echo '<p class="wp-pq-empty-state">No work logs in this range yet.</p>';
        } else {
            echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Client</th><th>Bucket</th><th>Tasks</th><th>Actions</th></tr></thead><tbody>';
            foreach ($work_logs as $work_log) {
                $export_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'wp_pq_export_work_log',
                        'work_log_id' => (int) $work_log['id'],
                    ], admin_url('admin-post.php')),
                    'wp_pq_export_work_log_' . (int) $work_log['id']
                );
                $print_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'wp_pq_print_work_log',
                        'work_log_id' => (int) $work_log['id'],
                    ], admin_url('admin-post.php')),
                    'wp_pq_print_work_log_' . (int) $work_log['id']
                );
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $work_log['work_log_code']) . '</strong></td>';
                echo '<td>' . esc_html(self::client_label_from_row($work_log)) . '</td>';
                echo '<td>' . esc_html(self::bucket_label_from_row($work_log)) . '</td>';
                echo '<td>' . (int) $work_log['task_count'] . '</td>';
                echo '<td><a class="button" target="_blank" href="' . esc_url($print_url) . '">Print / PDF</a> <a class="button" href="' . esc_url($export_url) . '">CSV</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '    <h3>Statements</h3>';
        if (empty($statements)) {
            echo '<p class="wp-pq-empty-state">No statements in this range yet.</p>';
        } else {
            echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Client</th><th>Bucket</th><th>Tasks</th><th>Actions</th></tr></thead><tbody>';
            foreach ($statements as $statement) {
                $view_url = add_query_arg([
                    'page' => 'wp-pq-statements',
                    'period' => (string) ($statement['statement_month'] ?: $range['month']),
                    'statement_id' => (int) $statement['id'],
                ], admin_url('admin.php'));
                $print_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'wp_pq_print_statement',
                        'statement_id' => (int) $statement['id'],
                    ], admin_url('admin-post.php')),
                    'wp_pq_print_statement_' . (int) $statement['id']
                );
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $statement['statement_code']) . '</strong></td>';
                echo '<td>' . esc_html(self::client_label_from_row($statement)) . '</td>';
                echo '<td>' . esc_html(self::bucket_label_from_row($statement)) . '</td>';
                echo '<td>' . (int) $statement['task_count'] . '</td>';
                echo '<td><a class="button" href="' . esc_url($view_url) . '">View</a> <a class="button" target="_blank" href="' . esc_url($print_url) . '">Print / PDF</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '  </section>';
        echo '</div>';

        echo '<div class="wp-pq-rollup-groups">';
        if (empty($groups)) {
            echo '<section class="wp-pq-panel"><p class="wp-pq-empty-state">No delivered tasks fell inside this range.</p></section>';
        } else {
            foreach ($groups as $group) {
            echo '<section class="wp-pq-panel wp-pq-rollup-group">';
                echo '<h2>' . esc_html((string) $group['client_name']) . '</h2>';
                echo '<p class="wp-pq-panel-note"><strong>Bucket:</strong> ' . esc_html((string) $group['bucket_name']) . '</p>';
                echo '<p class="wp-pq-panel-note">' . count($group['tasks']) . ' delivered task(s) in range. Work-log eligible: ' . (int) $group['work_log_ready_count'] . '. Statement eligible: ' . (int) $group['statement_ready_count'] . '.</p>';
                echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Task</th><th>Delivered</th><th>Work Log</th><th>Statement</th><th>Bucket</th></tr></thead><tbody>';
                foreach ($group['tasks'] as $task) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html((string) $task['title']) . '</strong><br><span class="description">#' . (int) $task['id'] . ' · ' . esc_html((string) $task['priority']) . '</span></td>';
                    echo '<td>' . esc_html(self::format_admin_datetime((string) $task['delivered_at'])) . '</td>';
                    echo '<td>' . esc_html((int) ($task['work_log_id'] ?? 0) > 0 ? 'Logged' : 'Ready') . '</td>';
                    echo '<td>' . esc_html((string) ($task['billing_status'] ?? '') === 'batched' ? 'Batched' : 'Ready') . '</td>';
                    echo '<td>';
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-form">';
                    wp_nonce_field('wp_pq_assign_bucket_' . (int) $task['id']);
                    echo '<input type="hidden" name="action" value="wp_pq_assign_bucket">';
                    echo '<input type="hidden" name="task_id" value="' . (int) $task['id'] . '">';
                    echo '<input type="hidden" name="redirect_page" value="wp-pq-rollups">';
                    echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
                    echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
                    echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
                    echo '<select name="billing_bucket_id">';
                    foreach ($group['bucket_options'] as $bucket_option) {
                        echo '<option value="' . (int) $bucket_option['id'] . '"' . selected((int) $bucket_option['id'], (int) $task['billing_bucket_id'], false) . '>' . esc_html(self::bucket_label_from_row($bucket_option)) . '</option>';
                    }
                    echo '</select> <button class="button" type="submit">Save</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                if ($group['work_log_ready_count'] > 0) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form">';
                    wp_nonce_field('wp_pq_create_work_log');
                    echo '<input type="hidden" name="action" value="wp_pq_create_work_log">';
                    echo '<input type="hidden" name="redirect_page" value="wp-pq-rollups">';
                    echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
                    echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
                    echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
                    foreach ($group['work_log_task_ids'] as $task_id) {
                        echo '<input type="hidden" name="task_ids[]" value="' . (int) $task_id . '">';
                    }
                    echo '<button class="button" type="submit">Create Work Log (' . (int) $group['work_log_ready_count'] . ')</button>';
                    echo '</form>';
                }

                if ($group['statement_ready_count'] > 0) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form">';
                    wp_nonce_field('wp_pq_create_statement');
                    echo '<input type="hidden" name="action" value="wp_pq_create_statement">';
                    echo '<input type="hidden" name="redirect_page" value="wp-pq-rollups">';
                    echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
                    echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
                    echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
                    foreach ($group['statement_task_ids'] as $task_id) {
                        echo '<input type="hidden" name="task_ids[]" value="' . (int) $task_id . '">';
                    }
                    echo '<button class="button button-primary" type="submit">Create Statement (' . (int) $group['statement_ready_count'] . ')</button>';
                    echo '</form>';
                }
                echo '</section>';
            }
        }
        echo '</div>';
        echo self::client_picker_script();
    }

    public static function render_statements_page(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $selected_month = WP_PQ_API::normalize_statement_month((string) ($_GET['period'] ?? ''));
        $selected_client_id = isset($_GET['client_user_id']) ? (int) $_GET['client_user_id'] : 0;
        $selected_statement_id = isset($_GET['statement_id']) ? (int) $_GET['statement_id'] : 0;
        $unbilled_tasks = self::get_delivered_unbilled_tasks($selected_month);
        $statement_summaries = self::get_statement_summaries($selected_month);
        $statement_detail = $selected_statement_id > 0 ? self::get_statement_detail($selected_statement_id) : null;
        if ($selected_client_id > 0) {
            $unbilled_tasks = array_values(array_filter($unbilled_tasks, static function (array $task) use ($selected_client_id): bool {
                return (int) ($task['submitter_id'] ?? 0) === $selected_client_id;
            }));
            $statement_summaries = array_values(array_filter($statement_summaries, static function (array $statement) use ($selected_client_id): bool {
                return (int) ($statement['client_user_id'] ?? 0) === $selected_client_id;
            }));
            if ($statement_detail && (int) ($statement_detail['client_user_id'] ?? 0) !== $selected_client_id) {
                $statement_detail = null;
            }
        }

        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Statements</h1>';
        echo '<p>Batch delivered work into statements, review by month, and export a clean task list for billing.</p>';
        echo self::admin_section_nav('statements');

        if (isset($_GET['wp_pq_notice'])) {
            $notice = sanitize_key((string) $_GET['wp_pq_notice']);
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';
            if ($notice === 'statement_created') {
                echo '<div class="notice notice-success"><p>' . esc_html($message ?: 'Statement created.') . '</p></div>';
            } elseif ($notice === 'statement_error') {
                echo '<div class="notice notice-error"><p>' . esc_html($message ?: 'There was a problem creating the statement.') . '</p></div>';
            }
        }

        echo '<div class="wp-pq-panel wp-pq-filter-bar">';
        echo '  <form method="get" class="wp-pq-period-form">';
        echo '    <input type="hidden" name="page" value="wp-pq-statements">';
        echo '    <input type="hidden" name="client_user_id" value="' . (int) $selected_client_id . '">';
        echo '    <label>Period';
        echo '      <input type="month" name="period" value="' . esc_attr($selected_month) . '">';
        echo '    </label>';
        echo '    <button class="button" type="submit">Filter Period</button>';
        echo '  </form>';
        echo '</div>';

        echo '<div class="wp-pq-billing-grid">';
        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Unbilled Delivered Tasks</h2>';
        echo '    <p class="wp-pq-panel-note">These delivered tasks are ready to be grouped into a statement for ' . esc_html($selected_month) . '.</p>';

        if (empty($unbilled_tasks)) {
            echo '<p class="wp-pq-empty-state">No unbilled delivered tasks were found for this period.</p>';
        } else {
            echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_pq_create_statement');
            echo '      <input type="hidden" name="action" value="wp_pq_create_statement">';
            echo '      <input type="hidden" name="period" value="' . esc_attr($selected_month) . '">';
            echo '      <table class="widefat striped wp-pq-admin-table">';
            echo '        <thead><tr><th class="check-column"><input type="checkbox" data-pq-check-all></th><th>Task</th><th>Requested By</th><th>Delivered</th><th>Priority</th><th>Owners</th></tr></thead>';
            echo '        <tbody>';
            foreach ($unbilled_tasks as $task) {
                echo '<tr>';
                echo '<td class="check-column"><input type="checkbox" name="task_ids[]" value="' . (int) $task['id'] . '"></td>';
                echo '<td><strong>' . esc_html((string) $task['title']) . '</strong><br><span class="description">#' . (int) $task['id'] . ' · ' . esc_html(wp_trim_words((string) $task['description'], 18)) . '</span></td>';
                echo '<td>' . esc_html((string) $task['submitter_name']) . '</td>';
                echo '<td>' . esc_html(self::format_admin_datetime((string) $task['delivered_at'])) . '</td>';
                echo '<td>' . esc_html(ucfirst((string) $task['priority'])) . '</td>';
                echo '<td>' . esc_html((string) $task['owner_names']) . '</td>';
                echo '</tr>';
            }
            echo '        </tbody>';
            echo '      </table>';
            echo '      <label>Statement Notes';
            echo '        <textarea name="notes" rows="4" placeholder="Optional client-facing or internal note for this statement period."></textarea>';
            echo '      </label>';
            echo '      <div class="wp-pq-create-actions">';
            echo '        <button class="button button-primary" type="submit">Create Statement from Selected</button>';
            echo '      </div>';
            echo '    </form>';
        }
        echo '  </section>';

        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Statement Batches</h2>';
        echo '    <p class="wp-pq-panel-note">Review existing statements for this period and export a task list for billing.</p>';
        if (empty($statement_summaries)) {
            echo '<p class="wp-pq-empty-state">No statements have been created for this period yet.</p>';
        } else {
            echo '      <table class="widefat striped wp-pq-admin-table">';
            echo '        <thead><tr><th>Code</th><th>Created</th><th>Tasks</th><th>Created By</th><th>Notes</th><th>Actions</th></tr></thead>';
            echo '        <tbody>';
            foreach ($statement_summaries as $statement) {
                $view_url = add_query_arg([
                    'page' => 'wp-pq-statements',
                    'period' => $selected_month,
                    'statement_id' => (int) $statement['id'],
                ], admin_url('admin.php'));
                $export_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'wp_pq_export_statement',
                        'statement_id' => (int) $statement['id'],
                    ], admin_url('admin-post.php')),
                    'wp_pq_export_statement_' . (int) $statement['id']
                );
                $print_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'wp_pq_print_statement',
                        'statement_id' => (int) $statement['id'],
                    ], admin_url('admin-post.php')),
                    'wp_pq_print_statement_' . (int) $statement['id']
                );
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $statement['statement_code']) . '</strong></td>';
                echo '<td>' . esc_html(self::format_admin_datetime((string) $statement['created_at'])) . '</td>';
                echo '<td>' . (int) $statement['task_count'] . '</td>';
                echo '<td>' . esc_html((string) $statement['creator_name']) . '</td>';
                echo '<td>' . esc_html(wp_trim_words((string) $statement['notes'], 16)) . '</td>';
                echo '<td><a class="button" href="' . esc_url($view_url) . '">View</a> <a class="button" target="_blank" href="' . esc_url($print_url) . '">Print / PDF</a> <a class="button" href="' . esc_url($export_url) . '">CSV</a></td>';
                echo '</tr>';
            }
            echo '        </tbody>';
            echo '      </table>';
        }
        echo '  </section>';
        echo '</div>';

        if ($statement_detail) {
            echo '<section class="wp-pq-panel wp-pq-statement-detail">';
            echo '  <h2>Statement ' . esc_html((string) $statement_detail['statement_code']) . '</h2>';
            echo '  <p class="wp-pq-panel-note">Created ' . esc_html(self::format_admin_datetime((string) $statement_detail['created_at'])) . ' by ' . esc_html((string) $statement_detail['creator_name']) . ' for ' . esc_html(self::client_label_from_row($statement_detail)) . ' in ' . esc_html(self::bucket_label_from_row($statement_detail)) . '.</p>';
            if (! empty($statement_detail['notes'])) {
                echo '  <div class="wp-pq-admin-callout"><p>' . esc_html((string) $statement_detail['notes']) . '</p></div>';
            }
            echo '  <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
            wp_nonce_field('wp_pq_update_statement_' . (int) $statement_detail['id']);
            echo '    <input type="hidden" name="action" value="wp_pq_update_statement">';
            echo '    <input type="hidden" name="statement_id" value="' . (int) $statement_detail['id'] . '">';
            echo '    <label>Currency <input type="text" name="currency_code" value="' . esc_attr((string) ($statement_detail['currency_code'] ?: 'USD')) . '" maxlength="10"></label>';
            echo '    <label>Total amount <input type="number" name="total_amount" min="0" step="0.01" value="' . esc_attr((string) $statement_detail['total_amount']) . '"></label>';
            echo '    <label>Due date <input type="date" name="due_date" value="' . esc_attr((string) $statement_detail['due_date']) . '"></label>';
            echo '    <label>Statement notes <textarea name="notes" rows="4">' . esc_textarea((string) $statement_detail['notes']) . '</textarea></label>';
            echo '    <div class="wp-pq-inline-action-form">';
            echo '      <button class="button button-primary" type="submit">Save Billing Details</button>';
            echo '      <a class="button" target="_blank" href="' . esc_url(wp_nonce_url(add_query_arg([
                'action' => 'wp_pq_print_statement',
                'statement_id' => (int) $statement_detail['id'],
            ], admin_url('admin-post.php')), 'wp_pq_print_statement_' . (int) $statement_detail['id'])) . '">Print / PDF</a>';
            echo '    </div>';
            echo '  </form>';
            echo '  <table class="widefat striped wp-pq-admin-table">';
            echo '    <thead><tr><th>Task</th><th>Requested By</th><th>Delivered</th><th>Batched</th><th>Priority</th></tr></thead>';
            echo '    <tbody>';
            foreach ($statement_detail['tasks'] as $task) {
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $task['title']) . '</strong><br><span class="description">#' . (int) $task['id'] . '</span></td>';
                echo '<td>' . esc_html((string) $task['submitter_name']) . '</td>';
                echo '<td>' . esc_html(self::format_admin_datetime((string) $task['delivered_at'])) . '</td>';
                echo '<td>' . esc_html(self::format_admin_datetime((string) $task['statement_batched_at'])) . '</td>';
                echo '<td>' . esc_html(ucfirst((string) $task['priority'])) . '</td>';
                echo '</tr>';
            }
            echo '    </tbody>';
            echo '  </table>';
            echo '</section>';
        }

        echo '</div>';
        echo "<script>document.addEventListener('DOMContentLoaded',function(){var all=document.querySelector('[data-pq-check-all]');if(!all)return;all.addEventListener('change',function(){document.querySelectorAll('input[name=\"task_ids[]\"]').forEach(function(box){box.checked=all.checked;});});});</script>";
    }

    public static function handle_google_oauth_start(): void
    {
        if (! current_user_can('read')) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_google_oauth_start');
        $response = WP_PQ_API::google_oauth_url();
        $data = $response->get_data();
        $url = is_array($data) ? (string) ($data['url'] ?? '') : '';

        if ($url === '') {
            wp_safe_redirect(admin_url('admin.php?page=wp-pq-settings'));
            exit;
        }

        wp_safe_redirect($url);
        exit;
    }

    public static function handle_create_client(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_create_client');

        $client_name = sanitize_text_field(wp_unslash((string) ($_POST['client_name'] ?? '')));
        $client_email = sanitize_email(wp_unslash((string) ($_POST['client_email'] ?? '')));
        $initial_bucket_name = sanitize_text_field(wp_unslash((string) ($_POST['initial_bucket_name'] ?? '')));
        $redirect_page = sanitize_key((string) ($_POST['redirect_page'] ?? 'wp-pq-rollups'));

        if ($client_name === '' || ! is_email($client_email)) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_error', 'Enter a valid client name and email.'));
            exit;
        }

        $user = get_user_by('email', $client_email);
        $created = false;

        if (! $user) {
            $base_login = sanitize_user(current(explode('@', $client_email)), true);
            if ($base_login === '') {
                $base_login = 'client';
            }

            $login = $base_login;
            $suffix = 1;
            while (username_exists($login)) {
                $suffix++;
                $login = $base_login . $suffix;
            }

            $user_id = wp_insert_user([
                'user_login' => $login,
                'user_pass' => wp_generate_password(24, true, true),
                'user_email' => $client_email,
                'display_name' => $client_name,
                'nickname' => $client_name,
                'role' => 'pq_client',
            ]);

            if (is_wp_error($user_id)) {
                wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_error', $user_id->get_error_message()));
                exit;
            }

            $user = get_user_by('ID', (int) $user_id);
            $created = true;
        } else {
            $user->add_role('pq_client');
            if ((string) $user->display_name === '') {
                wp_update_user([
                    'ID' => (int) $user->ID,
                    'display_name' => $client_name,
                    'nickname' => $client_name,
                ]);
                $user = get_user_by('ID', (int) $user->ID);
            }
        }

        $client_user_id = $user ? (int) $user->ID : 0;
        if ($client_user_id <= 0) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_error', 'Unable to create or link that client.'));
            exit;
        }

        if ($initial_bucket_name === '') {
            $initial_bucket_name = $created
                ? trim($client_name) . ' - Main'
                : WP_PQ_DB::suggest_default_bucket_name($client_user_id);
        }
        self::create_bucket_for_client($client_user_id, $initial_bucket_name);

        $message = $created ? 'Client created and ready for billing.' : 'Existing user linked as a client.';
        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_saved', $message, [], ['client_user_id' => $client_user_id]));
        exit;
    }

    public static function handle_link_client(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_link_client');

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $initial_bucket_name = sanitize_text_field(wp_unslash((string) ($_POST['initial_bucket_name'] ?? '')));
        $redirect_page = sanitize_key((string) ($_POST['redirect_page'] ?? 'wp-pq-client-directory'));

        $user = $user_id > 0 ? get_user_by('ID', $user_id) : false;
        if (! $user) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_error', 'Choose an existing WordPress user to link as a client.'));
            exit;
        }

        $user->add_role('pq_client');
        if ($initial_bucket_name === '') {
            $initial_bucket_name = WP_PQ_DB::suggest_default_bucket_name((int) $user->ID);
        }
        self::create_bucket_for_client((int) $user->ID, $initial_bucket_name);

        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_saved', 'Existing user linked as a client.', [], ['client_user_id' => (int) $user->ID]));
        exit;
    }

    public static function handle_create_bucket(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_create_bucket');
        global $wpdb;

        $client_user_id = isset($_POST['client_user_id']) ? (int) $_POST['client_user_id'] : 0;
        $bucket_name = sanitize_text_field(wp_unslash((string) ($_POST['bucket_name'] ?? '')));
        if ($client_user_id > 0 && $bucket_name !== '') {
            self::create_bucket_for_client($client_user_id, $bucket_name);
        }

        wp_safe_redirect(self::admin_redirect_url('wp-pq-rollups', 'bucket_saved', 'Billing bucket saved.', [], ['client_user_id' => $client_user_id]));
        exit;
    }

    public static function handle_assign_bucket(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        check_admin_referer('wp_pq_assign_bucket_' . $task_id);
        global $wpdb;

        $bucket_id = isset($_POST['billing_bucket_id']) ? (int) $_POST['billing_bucket_id'] : 0;
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $task = $wpdb->get_row($wpdb->prepare("SELECT id, submitter_id FROM {$tasks_table} WHERE id = %d", $task_id), ARRAY_A);
        $bucket = $wpdb->get_row($wpdb->prepare("SELECT id, client_user_id FROM {$buckets_table} WHERE id = %d", $bucket_id), ARRAY_A);

        if ($task && $bucket && (int) $task['submitter_id'] === (int) $bucket['client_user_id']) {
            $wpdb->update($tasks_table, [
                'billing_bucket_id' => $bucket_id,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $task_id]);
        }

        wp_safe_redirect(self::admin_redirect_url('wp-pq-rollups', 'bucket_saved', 'Task billing bucket updated.'));
        exit;
    }

    public static function handle_create_work_log(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_create_work_log');
        $task_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['task_ids'] ?? [])))));
        $range = self::get_rollup_range_from_request($_POST);
        $result = WP_PQ_API::create_work_log_batch($task_ids, '', $range['start'], $range['end'], get_current_user_id());

        if (is_wp_error($result)) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-rollups', 'rollup_error', $result->get_error_message()));
            exit;
        }

        wp_safe_redirect(self::admin_redirect_url('wp-pq-rollups', 'work_log_created', sprintf('Work log %s created with %d task%s.', $result['code'], (int) $result['task_count'], ((int) $result['task_count'] === 1 ? '' : 's'))));
        exit;
    }

    public static function handle_create_statement(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_create_statement');
        $task_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['task_ids'] ?? [])))));
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['notes'])) : '';
        $range = self::get_rollup_range_from_request($_POST);
        $period = $range['month'];
        $result = WP_PQ_API::create_statement_batch($task_ids, $notes, $period, get_current_user_id());

        if (is_wp_error($result)) {
            wp_safe_redirect(self::admin_redirect_url((string) ($_POST['redirect_page'] ?? 'wp-pq-statements'), 'statement_error', $result->get_error_message(), $range));
            exit;
        }

        $redirect_args = [
            'statement_id' => (int) $result['id'],
        ];
        $redirect_url = self::admin_redirect_url(
            (string) ($_POST['redirect_page'] ?? 'wp-pq-statements'),
            'statement_created',
            sprintf('Statement %s created with %d task%s.', $result['code'], (int) $result['task_count'], ((int) $result['task_count'] === 1 ? '' : 's')),
            $range,
            $redirect_args
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function handle_update_statement(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $statement_id = isset($_POST['statement_id']) ? (int) $_POST['statement_id'] : 0;
        check_admin_referer('wp_pq_update_statement_' . $statement_id);

        if ($statement_id <= 0) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-statements', 'statement_error', 'Statement not found.'));
            exit;
        }

        global $wpdb;
        $statements_table = $wpdb->prefix . 'pq_statements';

        $currency_code = strtoupper(sanitize_text_field(wp_unslash((string) ($_POST['currency_code'] ?? 'USD'))));
        $total_amount_raw = sanitize_text_field(wp_unslash((string) ($_POST['total_amount'] ?? '')));
        $due_date = WP_PQ_API::normalize_rollup_date(sanitize_text_field(wp_unslash((string) ($_POST['due_date'] ?? ''))));
        $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? '')));

        $update = [
            'currency_code' => $currency_code !== '' ? substr($currency_code, 0, 10) : 'USD',
            'total_amount' => $total_amount_raw === '' ? null : number_format((float) $total_amount_raw, 2, '.', ''),
            'due_date' => $due_date !== '' ? $due_date : null,
            'notes' => $notes,
        ];

        $wpdb->update($statements_table, $update, ['id' => $statement_id]);

        wp_safe_redirect(add_query_arg([
            'page' => 'wp-pq-statements',
            'statement_id' => $statement_id,
            'wp_pq_notice' => 'statement_created',
            'message' => 'Statement details updated.',
        ], admin_url('admin.php')));
        exit;
    }

    public static function handle_export_work_log(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $work_log_id = isset($_GET['work_log_id']) ? (int) $_GET['work_log_id'] : 0;
        check_admin_referer('wp_pq_export_work_log_' . $work_log_id);
        $work_log = self::get_work_log_detail($work_log_id);
        if (! $work_log) {
            wp_die('Work log not found.');
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($work_log['work_log_code'] . '.csv'));

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Work Log Code', 'Client', 'Bucket', 'Range Start', 'Range End', 'Task ID', 'Task Title', 'Delivered At', 'Priority']);
        foreach ($work_log['tasks'] as $task) {
            fputcsv($out, [
                $work_log['work_log_code'],
                $work_log['client_name'],
                $work_log['bucket_name'],
                $work_log['range_start'],
                $work_log['range_end'],
                $task['id'],
                $task['title'],
                self::format_admin_datetime((string) $task['delivered_at']),
                ucfirst((string) $task['priority']),
            ]);
        }
        fclose($out);
        exit;
    }

    public static function handle_print_work_log(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $work_log_id = isset($_GET['work_log_id']) ? (int) $_GET['work_log_id'] : 0;
        check_admin_referer('wp_pq_print_work_log_' . $work_log_id);
        $work_log = self::get_work_log_detail($work_log_id);
        if (! $work_log) {
            wp_die('Work log not found.');
        }

        self::render_print_document('Work Log', [
            'code' => (string) $work_log['work_log_code'],
            'client' => self::client_label_from_row($work_log),
            'bucket' => self::bucket_label_from_row($work_log),
            'period' => self::format_date_range((string) $work_log['range_start'], (string) $work_log['range_end']),
            'created' => self::format_admin_datetime((string) $work_log['created_at']),
        ], $work_log['tasks'], (string) ($work_log['notes'] ?? ''), null);
    }

    public static function handle_export_statement(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $statement_id = isset($_GET['statement_id']) ? (int) $_GET['statement_id'] : 0;
        check_admin_referer('wp_pq_export_statement_' . $statement_id);
        $statement = self::get_statement_detail($statement_id);
        if (! $statement) {
            wp_die('Statement not found.');
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($statement['statement_code'] . '.csv'));

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Statement Code', 'Statement Month', 'Task ID', 'Task Title', 'Requested By', 'Delivered At', 'Batched At', 'Priority']);
        foreach ($statement['tasks'] as $task) {
            fputcsv($out, [
                $statement['statement_code'],
                $statement['statement_month'],
                $task['id'],
                $task['title'],
                $task['submitter_name'],
                self::format_admin_datetime((string) $task['delivered_at']),
                self::format_admin_datetime((string) $task['statement_batched_at']),
                ucfirst((string) $task['priority']),
            ]);
        }
        fclose($out);
        exit;
    }

    public static function handle_print_statement(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $statement_id = isset($_GET['statement_id']) ? (int) $_GET['statement_id'] : 0;
        check_admin_referer('wp_pq_print_statement_' . $statement_id);
        $statement = self::get_statement_detail($statement_id);
        if (! $statement) {
            wp_die('Statement not found.');
        }

        self::render_print_document('Billing Statement', [
            'code' => (string) $statement['statement_code'],
            'client' => self::client_label_from_row($statement),
            'bucket' => self::bucket_label_from_row($statement),
            'period' => self::format_date_range((string) $statement['range_start'], (string) $statement['range_end']),
            'created' => self::format_admin_datetime((string) $statement['created_at']),
            'due' => self::format_date_only((string) $statement['due_date']),
        ], $statement['tasks'], (string) ($statement['notes'] ?? ''), [
            'currency' => (string) ($statement['currency_code'] ?: 'USD'),
            'total' => isset($statement['total_amount']) ? (string) $statement['total_amount'] : '',
        ]);
    }

    private static function get_rollup_range(): array
    {
        return self::get_rollup_range_from_request($_GET);
    }

    private static function admin_section_nav(string $current): string
    {
        $items = [
            'queue' => ['label' => 'Queue', 'url' => admin_url('admin.php?page=wp-pq-queue')],
            'clients' => ['label' => 'Clients', 'url' => admin_url('admin.php?page=wp-pq-client-directory')],
            'rollups' => ['label' => 'Billing Rollup', 'url' => admin_url('admin.php?page=wp-pq-rollups')],
            'statements' => ['label' => 'Statements', 'url' => admin_url('admin.php?page=wp-pq-statements')],
            'settings' => ['label' => 'Settings', 'url' => admin_url('admin.php?page=wp-pq-settings')],
        ];

        $html = '<nav class="wp-pq-admin-nav">';
        foreach ($items as $key => $item) {
            $class = 'button' . ($key === $current ? ' button-primary' : '');
            $html .= '<a class="' . esc_attr($class) . '" href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }

    private static function get_rollup_range_from_request(array $source): array
    {
        $month_input = isset($source['month']) ? $source['month'] : ($source['period'] ?? '');
        $month = WP_PQ_API::normalize_statement_month(sanitize_text_field(wp_unslash((string) $month_input)));
        $custom_start = WP_PQ_API::normalize_rollup_date(isset($source['start_date']) ? sanitize_text_field(wp_unslash((string) $source['start_date'])) : '');
        $custom_end = WP_PQ_API::normalize_rollup_date(isset($source['end_date']) ? sanitize_text_field(wp_unslash((string) $source['end_date'])) : '');

        if ($custom_start !== '' && $custom_end !== '' && $custom_start <= $custom_end) {
            return [
                'month' => substr($custom_end, 0, 7),
                'start' => $custom_start,
                'end' => $custom_end,
                'custom_start' => $custom_start,
                'custom_end' => $custom_end,
                'label' => wp_date('M j, Y', strtotime($custom_start)) . ' to ' . wp_date('M j, Y', strtotime($custom_end)),
            ];
        }

        $month_ts = strtotime($month . '-01');
        return [
            'month' => $month,
            'start' => wp_date('Y-m-01', $month_ts),
            'end' => wp_date('Y-m-t', $month_ts),
            'custom_start' => '',
            'custom_end' => '',
            'label' => wp_date('F Y', $month_ts),
        ];
    }

    private static function admin_redirect_url(string $page, string $notice, string $message, array $range = [], array $extra = []): string
    {
        $args = array_merge([
            'page' => $page,
            'wp_pq_notice' => $notice,
            'message' => $message,
        ], $extra);

        if (! empty($range['month'])) {
            $args['month'] = $range['month'];
        } elseif (isset($_REQUEST['month'])) {
            $args['month'] = sanitize_text_field(wp_unslash((string) $_REQUEST['month']));
        }
        if (! empty($range['custom_start'])) {
            $args['start_date'] = $range['custom_start'];
        } elseif (isset($_REQUEST['start_date'])) {
            $args['start_date'] = sanitize_text_field(wp_unslash((string) $_REQUEST['start_date']));
        }
        if (! empty($range['custom_end'])) {
            $args['end_date'] = $range['custom_end'];
        } elseif (isset($_REQUEST['end_date'])) {
            $args['end_date'] = sanitize_text_field(wp_unslash((string) $_REQUEST['end_date']));
        }
        if (! empty($extra['client_user_id'])) {
            $args['client_user_id'] = (int) $extra['client_user_id'];
        } elseif (isset($_REQUEST['client_user_id'])) {
            $args['client_user_id'] = (int) $_REQUEST['client_user_id'];
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function get_billing_clients(): array
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $client_ids = array_map('intval', array_unique(array_filter(array_merge(
            get_users([
                'role' => 'pq_client',
                'fields' => 'ID',
            ]),
            $wpdb->get_col("SELECT DISTINCT submitter_id FROM {$tasks_table} WHERE submitter_id > 0"),
            $wpdb->get_col("SELECT DISTINCT client_user_id FROM {$buckets_table} WHERE client_user_id > 0")
        ))));

        if (empty($client_ids)) {
            return [];
        }

        $users = get_users([
            'include' => $client_ids,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $clients = [];
        foreach ($users as $user) {
            $clients[] = [
                'id' => (int) $user->ID,
                'name' => (string) $user->display_name,
                'email' => (string) $user->user_email,
                'label' => self::user_label($user),
            ];
        }

        return $clients;
    }

    private static function get_linkable_users(): array
    {
        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $rows = [];
        foreach ($users as $user) {
            if (in_array('pq_client', (array) $user->roles, true)) {
                continue;
            }
            $rows[] = [
                'id' => (int) $user->ID,
                'label' => self::user_label($user),
            ];
        }

        return $rows;
    }

    private static function get_client_directory_rows(): array
    {
        global $wpdb;

        $clients = self::get_billing_clients();
        if (empty($clients)) {
            return [];
        }

        $client_ids = array_map(static fn(array $client): int => (int) $client['id'], $clients);
        $ids_in = implode(',', array_map('intval', $client_ids));
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $logs_table = $wpdb->prefix . 'pq_work_logs';
        $statements_table = $wpdb->prefix . 'pq_statements';
        $buckets_by_client = self::get_buckets_by_client();

        $task_counts = [];
        if ($ids_in !== '') {
            $task_rows = $wpdb->get_results(
                "SELECT submitter_id AS client_user_id,
                        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
                        SUM(CASE WHEN status = 'delivered' AND billing_status = 'unbilled' THEN 1 ELSE 0 END) AS unbilled_count
                 FROM {$tasks_table}
                 WHERE submitter_id IN ({$ids_in})
                 GROUP BY submitter_id",
                ARRAY_A
            );
            foreach ($task_rows as $row) {
                $task_counts[(int) $row['client_user_id']] = [
                    'delivered_count' => (int) $row['delivered_count'],
                    'unbilled_count' => (int) $row['unbilled_count'],
                ];
            }
        }

        $work_log_rows = [];
        if ($ids_in !== '') {
            $work_log_rows = $wpdb->get_results(
                "SELECT client_user_id, work_log_code, billing_bucket_id, range_start, range_end, created_at,
                        (SELECT COUNT(*) FROM {$wpdb->prefix}pq_work_log_items wli WHERE wli.work_log_id = l.id) AS task_count
                 FROM {$logs_table} l
                 WHERE client_user_id IN ({$ids_in})
                 ORDER BY created_at DESC, id DESC",
                ARRAY_A
            );
        }

        $statement_rows = [];
        if ($ids_in !== '') {
            $statement_rows = $wpdb->get_results(
                "SELECT client_user_id, statement_code, billing_bucket_id, range_start, range_end, created_at,
                        (SELECT COUNT(*) FROM {$wpdb->prefix}pq_statement_items psi WHERE psi.statement_id = s.id) AS task_count
                 FROM {$statements_table} s
                 WHERE client_user_id IN ({$ids_in})
                 ORDER BY created_at DESC, id DESC",
                ARRAY_A
            );
        }

        $rows = [];
        foreach ($clients as $client) {
            $client_id = (int) $client['id'];
            $client_work_logs = array_values(array_filter($work_log_rows, static function (array $row) use ($client_id): bool {
                return (int) ($row['client_user_id'] ?? 0) === $client_id;
            }));
            $client_statements = array_values(array_filter($statement_rows, static function (array $row) use ($client_id): bool {
                return (int) ($row['client_user_id'] ?? 0) === $client_id;
            }));
            $rows[] = [
                'id' => $client_id,
                'label' => (string) $client['label'],
                'delivered_count' => (int) ($task_counts[$client_id]['delivered_count'] ?? 0),
                'unbilled_count' => (int) ($task_counts[$client_id]['unbilled_count'] ?? 0),
                'work_log_count' => count($client_work_logs),
                'statement_count' => count($client_statements),
                'buckets' => $buckets_by_client[$client_id] ?? [],
                'recent_work_logs' => array_slice($client_work_logs, 0, 3),
                'recent_statements' => array_slice($client_statements, 0, 3),
            ];
        }

        return $rows;
    }

    private static function get_buckets_by_client(): array
    {
        global $wpdb;

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $users_table = $wpdb->users;
        $rows = $wpdb->get_results(
            "SELECT b.*, u.display_name AS client_name, u.user_email AS client_email
             FROM {$buckets_table} b
             LEFT JOIN {$users_table} u ON u.ID = b.client_user_id
             ORDER BY u.display_name ASC, b.is_default DESC, b.bucket_name ASC",
            ARRAY_A
        );

        $by_client = [];
        foreach ($rows as $row) {
            $by_client[(int) $row['client_user_id']][] = $row;
        }

        return $by_client;
    }

    private static function get_rollup_groups(string $start_date, string $end_date, array $buckets_by_client): array
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.display_name AS submitter_name, u.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$tasks_table} t
             LEFT JOIN {$users_table} u ON u.ID = t.submitter_id
             LEFT JOIN {$buckets_table} b ON b.id = t.billing_bucket_id
             WHERE t.status = 'delivered'
               AND DATE(COALESCE(t.delivered_at, t.updated_at)) BETWEEN %s AND %s
             ORDER BY u.display_name ASC, b.bucket_name ASC, COALESCE(t.delivered_at, t.updated_at) DESC, t.id DESC",
            $start_date,
            $end_date
        ), ARRAY_A);

        $groups = [];
        foreach ($rows as $row) {
            $client_id = (int) ($row['submitter_id'] ?? 0);
            $bucket_id = (int) ($row['billing_bucket_id'] ?? 0);
            if ($bucket_id <= 0) {
                $bucket_id = WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);
                $row['billing_bucket_id'] = $bucket_id;
            }
            $group_key = $client_id . ':' . $bucket_id;
            if (! isset($groups[$group_key])) {
                $groups[$group_key] = [
                    'client_id' => $client_id,
                    'client_name' => self::client_label_from_row($row),
                    'bucket_id' => $bucket_id,
                    'bucket_name' => self::bucket_label_from_row($row),
                    'bucket_options' => $buckets_by_client[$client_id] ?? [],
                    'tasks' => [],
                    'work_log_task_ids' => [],
                    'statement_task_ids' => [],
                    'work_log_ready_count' => 0,
                    'statement_ready_count' => 0,
                ];
            }

            $groups[$group_key]['tasks'][] = $row;
            if ((int) ($row['work_log_id'] ?? 0) <= 0) {
                $groups[$group_key]['work_log_task_ids'][] = (int) $row['id'];
                $groups[$group_key]['work_log_ready_count']++;
            }
            if ((string) ($row['billing_status'] ?? 'unbilled') === 'unbilled') {
                $groups[$group_key]['statement_task_ids'][] = (int) $row['id'];
                $groups[$group_key]['statement_ready_count']++;
            }
        }

        return array_values($groups);
    }

    private static function get_work_log_summaries(string $start_date, string $end_date): array
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'pq_work_logs';
        $items_table = $wpdb->prefix . 'pq_work_log_items';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, COUNT(li.id) AS task_count, u.display_name AS client_name, u.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$logs_table} l
             LEFT JOIN {$items_table} li ON li.work_log_id = l.id
             LEFT JOIN {$users_table} u ON u.ID = l.client_user_id
             LEFT JOIN {$buckets_table} b ON b.id = l.billing_bucket_id
             WHERE l.created_at BETWEEN %s AND %s
             GROUP BY l.id
             ORDER BY l.created_at DESC, l.id DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ), ARRAY_A);
    }

    private static function get_work_log_detail(int $work_log_id): ?array
    {
        global $wpdb;

        if ($work_log_id <= 0) {
            return null;
        }

        $logs_table = $wpdb->prefix . 'pq_work_logs';
        $items_table = $wpdb->prefix . 'pq_work_log_items';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $work_log = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, u.display_name AS client_name, u.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$logs_table} l
             LEFT JOIN {$users_table} u ON u.ID = l.client_user_id
             LEFT JOIN {$buckets_table} b ON b.id = l.billing_bucket_id
             WHERE l.id = %d",
            $work_log_id
        ), ARRAY_A);

        if (! $work_log) {
            return null;
        }

        $work_log['tasks'] = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*
             FROM {$items_table} wi
             INNER JOIN {$tasks_table} t ON t.id = wi.task_id
             WHERE wi.work_log_id = %d
             ORDER BY COALESCE(t.delivered_at, t.updated_at) DESC, t.id DESC",
            $work_log_id
        ), ARRAY_A);

        return $work_log;
    }

    private static function get_statement_summaries_for_range(string $start_date, string $end_date): array
    {
        global $wpdb;

        $statements_table = $wpdb->prefix . 'pq_statements';
        $items_table = $wpdb->prefix . 'pq_statement_items';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, COUNT(si.id) AS task_count, u.display_name AS client_name, u.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$statements_table} s
             LEFT JOIN {$items_table} si ON si.statement_id = s.id
             LEFT JOIN {$users_table} u ON u.ID = s.client_user_id
             LEFT JOIN {$buckets_table} b ON b.id = s.billing_bucket_id
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY s.id
             ORDER BY s.created_at DESC, s.id DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ), ARRAY_A);
    }

    private static function get_delivered_unbilled_tasks(string $period): array
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $users_table = $wpdb->users;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.display_name AS submitter_name
             FROM {$tasks_table} t
             LEFT JOIN {$users_table} u ON u.ID = t.submitter_id
             WHERE t.status = 'delivered'
               AND t.billing_status = 'unbilled'
               AND DATE_FORMAT(COALESCE(t.delivered_at, t.updated_at), '%%Y-%%m') = %s
             ORDER BY COALESCE(t.delivered_at, t.updated_at) DESC, t.priority DESC, t.id DESC",
            $period
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['owner_names'] = self::owner_names_from_json((string) ($row['owner_ids'] ?? ''));
        }

        return $rows;
    }

    private static function get_statement_summaries(string $period): array
    {
        global $wpdb;

        $statements_table = $wpdb->prefix . 'pq_statements';
        $items_table = $wpdb->prefix . 'pq_statement_items';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, COUNT(si.id) AS task_count, u.display_name AS creator_name, client.display_name AS client_name, client.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$statements_table} s
             LEFT JOIN {$items_table} si ON si.statement_id = s.id
             LEFT JOIN {$users_table} u ON u.ID = s.created_by
             LEFT JOIN {$users_table} client ON client.ID = s.client_user_id
             LEFT JOIN {$buckets_table} b ON b.id = s.billing_bucket_id
             WHERE s.statement_month = %s
             GROUP BY s.id
             ORDER BY s.created_at DESC, s.id DESC",
            $period
        ), ARRAY_A);
    }

    private static function get_statement_detail(int $statement_id): ?array
    {
        global $wpdb;

        if ($statement_id <= 0) {
            return null;
        }

        $statements_table = $wpdb->prefix . 'pq_statements';
        $items_table = $wpdb->prefix . 'pq_statement_items';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $statement = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, u.display_name AS creator_name, client.display_name AS client_name, client.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$statements_table} s
             LEFT JOIN {$users_table} u ON u.ID = s.created_by
             LEFT JOIN {$users_table} client ON client.ID = s.client_user_id
             LEFT JOIN {$buckets_table} b ON b.id = s.billing_bucket_id
             WHERE s.id = %d",
            $statement_id
        ), ARRAY_A);

        if (! $statement) {
            return null;
        }

        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.display_name AS submitter_name
             FROM {$items_table} si
             INNER JOIN {$tasks_table} t ON t.id = si.task_id
             LEFT JOIN {$users_table} u ON u.ID = t.submitter_id
             WHERE si.statement_id = %d
             ORDER BY COALESCE(t.delivered_at, t.updated_at) DESC, t.id DESC",
            $statement_id
        ), ARRAY_A);

        $statement['tasks'] = $tasks;
        return $statement;
    }

    private static function find_client_by_id(array $clients, int $client_id): ?array
    {
        foreach ($clients as $client) {
            if ((int) ($client['id'] ?? 0) === $client_id) {
                return $client;
            }
        }

        return null;
    }

    private static function render_client_datalist(array $clients, string $list_id): string
    {
        $html = '<datalist id="' . esc_attr($list_id) . '">';
        foreach ($clients as $client) {
            $html .= '<option value="' . esc_attr((string) $client['label']) . '" data-id="' . (int) $client['id'] . '"></option>';
        }
        $html .= '</datalist>';

        return $html;
    }

    private static function render_user_datalist(array $users, string $list_id): string
    {
        $html = '<datalist id="' . esc_attr($list_id) . '">';
        foreach ($users as $user) {
            $html .= '<option value="' . esc_attr((string) $user['label']) . '" data-id="' . (int) $user['id'] . '"></option>';
        }
        $html .= '</datalist>';

        return $html;
    }

    private static function render_client_picker(string $base_id, string $hidden_name, array $clients, int $selected_id, string $label, string $placeholder, bool $required = false): string
    {
        $selected = self::find_client_by_id($clients, $selected_id);
        $required_attr = $required ? ' required' : '';

        return '<label>' . esc_html($label)
            . '<input type="text" class="wp-pq-client-picker" id="' . esc_attr($base_id) . '-search" data-hidden-target="' . esc_attr($base_id) . '-value" list="wp-pq-client-options" value="' . esc_attr((string) ($selected['label'] ?? '')) . '" placeholder="' . esc_attr($placeholder) . '"' . $required_attr . '>'
            . '<input type="hidden" id="' . esc_attr($base_id) . '-value" name="' . esc_attr($hidden_name) . '" value="' . (int) $selected_id . '"></label>';
    }

    private static function render_user_picker(string $base_id, string $hidden_name, array $users, int $selected_id, string $label, string $placeholder, bool $required = false): string
    {
        $selected = null;
        foreach ($users as $user) {
            if ((int) ($user['id'] ?? 0) === $selected_id) {
                $selected = $user;
                break;
            }
        }
        $required_attr = $required ? ' required' : '';

        return '<label>' . esc_html($label)
            . '<input type="text" class="wp-pq-client-picker" id="' . esc_attr($base_id) . '-search" data-hidden-target="' . esc_attr($base_id) . '-value" list="wp-pq-user-options" value="' . esc_attr((string) ($selected['label'] ?? '')) . '" placeholder="' . esc_attr($placeholder) . '"' . $required_attr . '>'
            . '<input type="hidden" id="' . esc_attr($base_id) . '-value" name="' . esc_attr($hidden_name) . '" value="' . (int) $selected_id . '"></label>';
    }

    private static function client_picker_script(): string
    {
        return "<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.wp-pq-client-picker').forEach(function (input) {
    var hidden = document.getElementById(input.dataset.hiddenTarget);
    var listId = input.getAttribute('list');
    var list = listId ? document.getElementById(listId) : null;
    if (!hidden || !list) return;
    var syncValue = function () {
      var match = Array.prototype.find.call(list.options, function (option) {
        return option.value === input.value;
      });
      hidden.value = match ? (match.dataset.id || '') : '';
    };
    input.addEventListener('change', syncValue);
    input.addEventListener('input', syncValue);
  });
});
</script>";
    }

    private static function create_bucket_for_client(int $client_user_id, string $bucket_name): int
    {
        global $wpdb;

        $bucket_name = trim($bucket_name);
        if ($client_user_id <= 0 || $bucket_name === '') {
            return 0;
        }

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $slug = sanitize_title($bucket_name);
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_user_id = %d AND bucket_slug = %s LIMIT 1",
            $client_user_id,
            $slug
        ));

        if ($existing_id > 0) {
            return $existing_id;
        }

        $has_default = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_user_id = %d AND is_default = 1 LIMIT 1",
            $client_user_id
        ));

        $wpdb->insert($buckets_table, [
            'client_user_id' => $client_user_id,
            'bucket_name' => $bucket_name,
            'bucket_slug' => $slug,
            'description' => '',
            'is_default' => $has_default > 0 ? 0 : 1,
            'created_by' => get_current_user_id() ?: 1,
            'created_at' => current_time('mysql', true),
        ]);

        return (int) $wpdb->insert_id;
    }

    private static function user_label(WP_User $user): string
    {
        $display = trim((string) $user->display_name);
        $email = trim((string) $user->user_email);
        if ($display === '') {
            return $email;
        }

        return $email !== '' && strcasecmp($display, $email) !== 0 ? $display . ' <' . $email . '>' : $display;
    }

    private static function client_label_from_row(array $row): string
    {
        $name = trim((string) ($row['client_name'] ?? $row['submitter_name'] ?? ''));
        $email = trim((string) ($row['client_email'] ?? ''));
        if ($name === '') {
            return $email !== '' ? $email : 'Client';
        }

        return $email !== '' && strcasecmp($name, $email) !== 0 ? $name . ' <' . $email . '>' : $name;
    }

    private static function bucket_label_from_row(array $row): string
    {
        $bucket_name = trim((string) ($row['bucket_name'] ?? ''));
        $is_default = (int) ($row['is_default'] ?? 0) === 1;
        if ($bucket_name === '') {
            return $is_default ? 'Default Bucket' : 'Billing Bucket';
        }
        if ($is_default && in_array(strtolower($bucket_name), ['general', 'default', 'default bucket'], true)) {
            return 'Default Bucket';
        }

        return $is_default ? $bucket_name . ' (Default)' : $bucket_name;
    }

    private static function format_date_only(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if (! $timestamp) {
            return $value;
        }

        return wp_date('M j, Y', $timestamp);
    }

    private static function format_date_range(string $start, string $end): string
    {
        if ($start === '' && $end === '') {
            return '-';
        }

        if ($start !== '' && $end !== '' && $start !== $end) {
            return self::format_date_only($start) . ' to ' . self::format_date_only($end);
        }

        return self::format_date_only($end !== '' ? $end : $start);
    }

    private static function render_print_document(string $title, array $meta, array $tasks, string $notes = '', ?array $totals = null): void
    {
        nocache_headers();
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $site_url = home_url('/');
        $admin_email = (string) get_option('admin_email');
        $document_code = (string) ($meta['code'] ?? '-');
        $client_label = (string) ($meta['client'] ?? '-');
        $bucket_label = (string) ($meta['bucket'] ?? '-');
        $period_label = (string) ($meta['period'] ?? '-');
        $created_label = (string) ($meta['created'] ?? '-');
        $due_label = (string) ($meta['due'] ?? '-');
        $currency = trim((string) ($totals['currency'] ?? 'USD'));
        $total = (string) ($totals['total'] ?? '');
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo esc_html($title . ' ' . $document_code); ?></title>
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #eef2f7; color: #172033; }
    .toolbar { max-width: 1040px; margin: 18px auto 0; display: flex; gap: 10px; justify-content: flex-end; }
    .toolbar button { border: 0; border-radius: 999px; background: #b91c1c; color: #fff; padding: 10px 16px; font-weight: 700; cursor: pointer; }
    .doc { max-width: 1040px; margin: 18px auto 32px; background: #fff; padding: 44px 48px 48px; box-shadow: 0 24px 60px rgba(15,23,42,.10); border-radius: 24px; }
    .header { display: flex; justify-content: space-between; gap: 28px; align-items: flex-start; margin-bottom: 28px; }
    .brand-kicker { font-size: 12px; letter-spacing: .18em; text-transform: uppercase; color: #7b879c; margin: 0 0 12px; }
    .brand h1 { margin: 0; font-size: 2.6rem; line-height: .98; letter-spacing: -.04em; }
    .brand .sub { margin: 10px 0 0; color: #5d6880; line-height: 1.6; }
    .doc-card { border: 1px solid #d9e0ea; border-radius: 20px; padding: 18px 20px; background: #fbfcff; }
    .doc-card strong, .label { display: block; font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: #6b768b; margin-bottom: 6px; }
    .doc-code { font-size: 1.15rem; font-weight: 800; }
    .summary-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 16px; margin-bottom: 26px; }
    .summary-value { font-size: 1rem; font-weight: 700; }
    .summary-copy { color: #5d6880; line-height: 1.55; }
    .amount-card { background: #172033; color: #fff; }
    .amount-card strong { color: rgba(255,255,255,.72); }
    .amount-value { font-size: 2rem; font-weight: 800; letter-spacing: -.03em; }
    .section-title { margin: 30px 0 12px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: #6b768b; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    thead th { font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: #6b768b; padding: 12px 12px; background: #f7f9fc; border-bottom: 1px solid #dfe6ef; }
    tbody td { padding: 14px 12px; border-bottom: 1px solid #e6ebf3; vertical-align: top; text-align: left; }
    .task-title { font-weight: 800; margin-bottom: 6px; }
    .task-brief { color: #5d6880; font-size: 13px; line-height: 1.5; }
    .pill { display: inline-flex; align-items: center; border-radius: 999px; background: #f2f5fa; color: #44506a; padding: 5px 9px; font-size: 11px; font-weight: 700; }
    .meta-stack { display: grid; gap: 4px; }
    .notes, .footer-grid { margin-top: 28px; }
    .footer-grid { display: grid; gap: 16px; grid-template-columns: 1.35fr .75fr; align-items: start; }
    .notes p { margin: 0; color: #415067; line-height: 1.65; white-space: pre-wrap; }
    .totals .totals-row { display: flex; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid #e6ebf3; }
    .totals .totals-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .totals .totals-row strong { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #6b768b; }
    .fine-print { margin-top: 28px; color: #7b879c; font-size: 12px; line-height: 1.6; }
    @media print {
      body { background: #fff; }
      .toolbar { display: none; }
      .doc { margin: 0; box-shadow: none; max-width: none; border-radius: 0; padding: 0; }
    }
  </style>
</head>
<body>
  <div class="toolbar"><button onclick="window.print()">Print / Save PDF</button></div>
  <main class="doc">
    <div class="header">
      <div class="brand">
        <p class="brand-kicker"><?php echo esc_html($site_name); ?></p>
        <h1><?php echo esc_html($title); ?></h1>
        <p class="sub"><?php echo esc_html($site_url); ?><br><?php echo esc_html($admin_email); ?></p>
      </div>
      <div class="doc-card amount-card">
        <strong><?php echo esc_html($title); ?> #</strong>
        <div class="doc-code"><?php echo esc_html($document_code); ?></div>
        <?php if ($total !== '') : ?>
          <div style="margin-top:18px;">
            <strong>Amount due</strong>
            <div class="amount-value"><?php echo esc_html($currency . ' ' . number_format((float) $total, 2)); ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="summary-grid">
      <section class="doc-card">
        <span class="label">Bill to</span>
        <div class="summary-value"><?php echo esc_html($client_label); ?></div>
        <div class="summary-copy">Task roll-up for client work delivered in this billing period.</div>
      </section>
      <section class="doc-card">
        <div class="meta-stack">
          <div><span class="label">Job bucket</span><div class="summary-value"><?php echo esc_html($bucket_label); ?></div></div>
          <div><span class="label">Billing period</span><div class="summary-value"><?php echo esc_html($period_label); ?></div></div>
        </div>
      </section>
      <section class="doc-card">
        <div class="meta-stack">
          <div><span class="label">Issued</span><div class="summary-value"><?php echo esc_html($created_label); ?></div></div>
          <?php if ($due_label !== '' && $due_label !== '-') : ?>
            <div><span class="label">Due</span><div class="summary-value"><?php echo esc_html($due_label); ?></div></div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <div class="section-title">Included Work</div>
    <table>
      <thead>
        <tr>
          <th style="width:46%;">Task</th>
          <th style="width:20%;">Delivered</th>
          <th style="width:14%;">Priority</th>
          <th style="width:20%;">Reference</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $task) : ?>
          <tr>
            <td>
              <div class="task-title"><?php echo esc_html((string) $task['title']); ?></div>
              <?php if (! empty($task['description'])) : ?>
                <div class="task-brief"><?php echo esc_html(wp_trim_words((string) $task['description'], 28)); ?></div>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html(self::format_admin_datetime((string) ($task['delivered_at'] ?? $task['updated_at'] ?? ''))); ?></td>
            <td><span class="pill"><?php echo esc_html(ucfirst((string) ($task['priority'] ?? 'normal'))); ?></span></td>
            <td><?php echo esc_html('Task #' . (int) ($task['id'] ?? 0)); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="footer-grid">
      <?php if ($notes !== '') : ?>
        <section class="doc-card notes">
          <strong>Notes</strong>
          <p><?php echo esc_html($notes); ?></p>
        </section>
      <?php else : ?>
        <section class="doc-card notes">
          <strong>Summary</strong>
          <p><?php echo esc_html($title); ?> prepared by <?php echo esc_html($site_name); ?> for <?php echo esc_html($client_label); ?> covering work in <?php echo esc_html($period_label); ?>.</p>
        </section>
      <?php endif; ?>

      <?php if ($total !== '') : ?>
        <section class="doc-card totals">
          <div class="totals-row"><strong>Currency</strong><span><?php echo esc_html($currency); ?></span></div>
          <div class="totals-row"><strong>Total</strong><span><?php echo esc_html(number_format((float) $total, 2)); ?></span></div>
          <?php if ($due_label !== '' && $due_label !== '-') : ?>
            <div class="totals-row"><strong>Due</strong><span><?php echo esc_html($due_label); ?></span></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>

    <p class="fine-print">
      Generated from the Readspear Priority Portal. Keep this document with the related task record, work log, and statement batch for audit continuity.
    </p>
  </main>
</body>
</html>
        <?php
        exit;
    }

    private static function owner_names_from_json(string $owner_ids_json): string
    {
        $owner_ids = json_decode($owner_ids_json, true);
        if (! is_array($owner_ids) || empty($owner_ids)) {
            return 'Unassigned';
        }

        $owner_ids = array_values(array_unique(array_filter(array_map('intval', $owner_ids))));
        if (empty($owner_ids)) {
            return 'Unassigned';
        }

        $names = [];
        foreach ($owner_ids as $owner_id) {
            $user = get_user_by('ID', $owner_id);
            if ($user) {
                $names[] = $user->display_name;
            }
        }

        return empty($names) ? 'Unassigned' : implode(', ', $names);
    }

    private static function format_admin_datetime(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if (! $timestamp) {
            return $value;
        }

        return wp_date('M j, Y g:i A', $timestamp);
    }
}
