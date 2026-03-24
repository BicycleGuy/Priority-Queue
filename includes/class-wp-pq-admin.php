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
        add_action('admin_init', [self::class, 'redirect_retired_admin_pages']);
        add_action('admin_post_wp_pq_google_oauth_start', [self::class, 'handle_google_oauth_start']);
        add_action('admin_post_wp_pq_create_client', [self::class, 'handle_create_client']);
        add_action('admin_post_wp_pq_link_client', [self::class, 'handle_link_client']);
        add_action('admin_post_wp_pq_create_bucket', [self::class, 'handle_create_bucket']);
        add_action('admin_post_wp_pq_delete_bucket', [self::class, 'handle_delete_bucket']);
        add_action('admin_post_wp_pq_add_client_member', [self::class, 'handle_add_client_member']);
        add_action('admin_post_wp_pq_assign_job_member', [self::class, 'handle_assign_job_member']);
        add_action('admin_post_wp_pq_assign_bucket', [self::class, 'handle_assign_bucket']);
        add_action('admin_post_wp_pq_create_work_log', [self::class, 'handle_create_work_log']);
        add_action('admin_post_wp_pq_export_work_log', [self::class, 'handle_export_work_log']);
        add_action('admin_post_wp_pq_print_work_log', [self::class, 'handle_print_work_log']);
        add_action('admin_post_wp_pq_update_work_log', [self::class, 'handle_update_work_log']);
        add_action('admin_post_wp_pq_create_statement', [self::class, 'handle_create_statement']);
        add_action('admin_post_wp_pq_export_statement', [self::class, 'handle_export_statement']);
        add_action('admin_post_wp_pq_print_statement', [self::class, 'handle_print_statement']);
        add_action('admin_post_wp_pq_update_statement', [self::class, 'handle_update_statement']);
        add_action('admin_post_wp_pq_delete_statement', [self::class, 'handle_delete_statement']);
        add_action('admin_post_wp_pq_remove_statement_task', [self::class, 'handle_remove_statement_task']);
        add_action('admin_post_wp_pq_ai_parse', [self::class, 'handle_ai_parse']);
        add_action('admin_post_wp_pq_ai_revalidate', [self::class, 'handle_ai_revalidate']);
        add_action('admin_post_wp_pq_ai_import', [self::class, 'handle_ai_import']);
        add_action('admin_post_wp_pq_ai_clear_preview', [self::class, 'handle_ai_clear_preview']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('plugin_action_links_' . plugin_basename(WP_PQ_PLUGIN_FILE), [self::class, 'plugin_action_links']);
    }

    public static function register_menu(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            return;
        }

        add_menu_page(
            'Priority Queue Settings',
            'Priority Queue',
            WP_PQ_Roles::CAP_APPROVE,
            'wp-pq-settings',
            [self::class, 'render_settings_page'],
            'dashicons-list-view',
            26
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        if (strpos($hook, 'wp-pq-settings') === false) {
            return;
        }

        wp_enqueue_style('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/css/admin-queue.css', [], WP_PQ_VERSION);
    }

    public static function register_settings(): void
    {
        register_setting('wp_pq_settings_group', 'wp_pq_google_client_id', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('wp_pq_settings_group', 'wp_pq_google_client_secret', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('wp_pq_settings_group', 'wp_pq_google_redirect_uri', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('wp_pq_settings_group', 'wp_pq_google_scopes', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('wp_pq_settings_group', 'wp_pq_openai_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('wp_pq_settings_group', 'wp_pq_openai_model', ['sanitize_callback' => 'sanitize_text_field']);
    }

    public static function plugin_action_links(array $links): array
    {
        array_unshift(
            $links,
            '<a href="' . esc_url(WP_PQ_Portal::portal_url()) . '">Open Portal</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=wp-pq-settings')) . '">Settings</a>'
        );
        return $links;
    }

    public static function redirect_retired_admin_pages(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page === '' || $page === 'wp-pq-settings') {
            return;
        }

        $section_map = self::retired_admin_section_map();
        if (! isset($section_map[$page])) {
            return;
        }

        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        wp_safe_redirect(WP_PQ_Portal::portal_url((string) $section_map[$page]));
        exit;
    }

    public static function render_page(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        wp_safe_redirect(WP_PQ_Portal::portal_url('queue'));
        exit;
    }

    public static function render_clients_page(): void
    {
        if (! self::can_manage_any_clients()) {
            wp_die('Forbidden');
        }

        $directory_users = self::get_directory_users();
        $clients = self::get_billing_clients($directory_users);
        $client_details = self::get_client_directory_rows($clients, $directory_users);
        $linkable_users = self::get_linkable_users($directory_users);
        $member_users = self::get_member_candidate_users($directory_users);
        $can_manage_all = current_user_can(WP_PQ_Roles::CAP_APPROVE);

        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Clients</h1>';
        echo '<p>Create client accounts, link existing WordPress users as client members, manage jobs, and jump straight into that client\'s work statements and invoice drafts.</p>';
        echo self::admin_section_nav('clients');

        if (isset($_GET['wp_pq_notice'])) {
            $notice = sanitize_key((string) $_GET['wp_pq_notice']);
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';
            $class = in_array($notice, ['client_saved', 'bucket_saved'], true) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
        }

        echo self::render_client_datalist($clients, 'wp-pq-client-options');
        echo self::render_user_datalist($linkable_users, 'wp-pq-user-options');
        echo self::render_user_datalist($member_users, 'wp-pq-member-user-options');

        if ($can_manage_all) {
            echo '<div class="wp-pq-billing-grid">';
            echo '  <section class="wp-pq-panel">';
            echo '    <h2>Create Client</h2>';
            echo '    <p class="wp-pq-panel-note">Create a new WordPress user with the client role and set the first job right away.</p>';
            echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
            wp_nonce_field('wp_pq_create_client');
            echo '      <input type="hidden" name="action" value="wp_pq_create_client">';
            echo '      <input type="hidden" name="redirect_page" value="wp-pq-client-directory">';
            echo '      <label>Client name <input type="text" name="client_name" placeholder="Read Spear" required></label>';
            echo '      <label>Client email <input type="email" name="client_email" placeholder="client@example.com" required></label>';
            echo '      <label>First job <input type="text" name="initial_bucket_name" placeholder="Client Name - Main" required></label>';
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
            echo '      <label>First job <input type="text" name="initial_bucket_name" placeholder="Client Name - Main" required></label>';
            echo '      <button class="button" type="submit">Link User as Client</button>';
            echo '    </form>';
            echo '  </section>';
            echo '</div>';
        }

        echo '<div class="wp-pq-rollup-groups">';
        if (empty($client_details)) {
            echo '<section class="wp-pq-panel"><p class="wp-pq-empty-state">No clients have been created yet.</p></section>';
        } else {
            foreach ($client_details as $client) {
                $rollup_url = add_query_arg([
                    'page' => 'wp-pq-rollups',
                    'client_id' => (int) $client['id'],
                ], admin_url('admin.php'));
                $statement_url = add_query_arg([
                    'page' => 'wp-pq-statements',
                    'client_id' => (int) $client['id'],
                ], admin_url('admin.php'));
                $members = (array) ($client['members'] ?? []);
                echo '<section class="wp-pq-panel wp-pq-rollup-group">';
                echo '<h2>' . esc_html((string) $client['name']) . '</h2>';
                echo '<p class="wp-pq-panel-note">Completed work: ' . (int) $client['delivered_count'] . ' · Unbilled: ' . (int) $client['unbilled_count'] . ' · Work statements: ' . (int) $client['work_log_count'] . ' · Invoice Drafts: ' . (int) $client['statement_count'] . '.</p>';
                echo '<p class="wp-pq-panel-note">Primary contact: ' . esc_html((string) ($client['email'] ?: 'Not set')) . '</p>';
                if (! empty($members)) {
                    echo '<h3>Members</h3>';
                    echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Jobs</th></tr></thead><tbody>';
                    foreach ($members as $member) {
                        $member_job_names = ! empty($member['job_names']) ? implode(', ', (array) ($member['job_names'] ?? [])) : 'No jobs yet';
                        echo '<tr>';
                        echo '<td>' . esc_html((string) $member['name']) . '</td>';
                        echo '<td>' . esc_html((string) $member['email']) . '</td>';
                        echo '<td>' . esc_html(self::humanize_label((string) $member['role'])) . '</td>';
                        echo '<td>' . esc_html($member_job_names) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form">';
                wp_nonce_field('wp_pq_add_client_member_' . (int) $client['id']);
                echo '  <input type="hidden" name="action" value="wp_pq_add_client_member">';
                echo '  <input type="hidden" name="client_id" value="' . (int) $client['id'] . '">';
                echo '  <input type="hidden" name="redirect_page" value="wp-pq-client-directory">';
                echo self::render_user_picker('client-member-' . (int) $client['id'], 'user_id', $member_users, 0, 'Add member', 'Type a user name or email', true, 'wp-pq-member-user-options');
                echo '  <label>Role<select name="client_role"><option value="client_admin">Client admin</option><option value="client_contributor" selected>Client contributor</option><option value="client_viewer">Client viewer</option></select></label>';
                echo '  <button class="button" type="submit">Add Member</button>';
                echo '</form>';
                if ($can_manage_all) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form">';
                    wp_nonce_field('wp_pq_create_bucket');
                    echo '  <input type="hidden" name="action" value="wp_pq_create_bucket">';
                    echo '  <input type="hidden" name="redirect_page" value="wp-pq-client-directory">';
                    echo '  <input type="hidden" name="client_id" value="' . (int) $client['id'] . '">';
                    echo '  <label>Add job <input type="text" name="bucket_name" placeholder="Retainer, Launch, etc." required></label>';
                    echo '  <button class="button" type="submit">Add Job</button>';
                    echo '</form>';
                }
                foreach ($client['buckets'] as $bucket) {
                    $bucket_id = (int) ($bucket['id'] ?? 0);
                    $job_members = self::get_job_member_rows($bucket_id, $members);
                    $assignable_members = self::get_assignable_job_member_rows($bucket_id, $members);
                    echo '<div class="wp-pq-bucket-group" id="wp-pq-job-' . $bucket_id . '">';
                    echo '<div class="wp-pq-job-row">';
                    echo '<div class="wp-pq-job-summary">';
                    echo '<strong>' . esc_html(self::bucket_label_from_row($bucket)) . '</strong>';
                    if (! empty($job_members)) {
                        echo '<p class="wp-pq-panel-note wp-pq-job-members">Access: ' . esc_html(implode(', ', array_map(static fn(array $job_member): string => (string) $job_member['name'], $job_members))) . '</p>';
                    } else {
                        echo '<p class="wp-pq-panel-note wp-pq-job-members">No one currently has access to this job.</p>';
                    }
                    echo '</div>';
                    if (! empty($assignable_members)) {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form wp-pq-job-assign-form">';
                        wp_nonce_field('wp_pq_assign_job_member_' . $bucket_id);
                        echo '  <input type="hidden" name="action" value="wp_pq_assign_job_member">';
                        echo '  <input type="hidden" name="client_id" value="' . (int) $client['id'] . '">';
                        echo '  <input type="hidden" name="billing_bucket_id" value="' . $bucket_id . '">';
                        echo '  <input type="hidden" name="redirect_page" value="wp-pq-client-directory">';
                        echo '  <label>Assign member<select name="user_id" required><option value="">Choose member</option>';
                        foreach ($assignable_members as $member) {
                            echo '<option value="' . (int) $member['id'] . '">' . esc_html((string) $member['name'] . ' (' . self::humanize_label((string) $member['role']) . ')') . '</option>';
                        }
                        echo '  </select></label>';
                        echo '  <button class="button" type="submit">Assign to Job</button>';
                        echo '</form>';
                    } else {
                        echo '<p class="wp-pq-panel-note wp-pq-job-row-note">All current client members already have access.</p>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
                echo '<div class="wp-pq-inline-action-form">';
                echo '  <a class="button" href="' . esc_url($rollup_url) . '">View Billing Rollup</a>';
                echo '  <a class="button" href="' . esc_url(add_query_arg(['page' => 'wp-pq-work-logs', 'client_id' => (int) $client['id']], admin_url('admin.php'))) . '">View Work Statements</a>';
                echo '  <a class="button" href="' . esc_url($statement_url) . '">View Invoice Drafts</a>';
                echo '</div>';
                if (! empty($client['recent_work_logs'])) {
                    echo '<h3>Recent Work Statements</h3>';
                    echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Bucket</th><th>Range</th><th>Tasks</th></tr></thead><tbody>';
                    foreach ($client['recent_work_logs'] as $work_log) {
                        echo '<tr><td>' . esc_html((string) $work_log['work_log_code']) . '</td><td>' . esc_html(self::bucket_label_from_row($work_log)) . '</td><td>' . esc_html(self::format_date_range((string) $work_log['range_start'], (string) $work_log['range_end'])) . '</td><td>' . (int) $work_log['task_count'] . '</td></tr>';
                    }
                    echo '</tbody></table>';
                }
                if (! empty($client['recent_statements'])) {
                    echo '<h3>Recent Invoice Drafts</h3>';
                    echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Bucket</th><th>Range</th><th>Work</th></tr></thead><tbody>';
                    foreach ($client['recent_statements'] as $statement) {
                        echo '<tr><td>' . esc_html((string) $statement['statement_code']) . '</td><td>' . esc_html(self::bucket_label_from_row($statement)) . '</td><td>' . esc_html(self::format_date_range((string) $statement['range_start'], (string) $statement['range_end'])) . '</td><td>' . (int) ($statement['entry_count'] ?? $statement['task_count'] ?? 0) . '</td></tr>';
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
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $redirect_uri = (string) get_option('wp_pq_google_redirect_uri', home_url('/wp-json/pq/v1/google/oauth/callback'));
        $tokens = (array) get_option('wp_pq_google_tokens', []);
        $is_connected = ! empty($tokens['refresh_token']);
        $oauth_url = wp_nonce_url(admin_url('admin-post.php?action=wp_pq_google_oauth_start'), 'wp_pq_google_oauth_start');

        echo '<div class="wrap wp-pq-wrap wp-pq-settings-page">';
        echo '<h1>Priority Queue Settings</h1>';
        echo '<p>Manage workflow email preferences, Google Calendar / Meet integration, and AI document import settings here.</p>';
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

        echo '  <section class="wp-pq-panel wp-pq-settings-panel">';
        echo '    <h2>OpenAI Document Ingester</h2>';
        echo '    <p class="wp-pq-panel-note">Add your OpenAI API key and model so Priority Queue can turn pasted lists, CSVs, and PDFs into draft tasks for review before import.</p>';
        echo '    <form method="post" action="options.php">';
        settings_fields('wp_pq_settings_group');
        echo '      <label>OpenAI API key <input type="password" name="wp_pq_openai_api_key" value="' . esc_attr((string) get_option('wp_pq_openai_api_key', '')) . '" autocomplete="off"></label>';
        echo '      <label>Model <input type="text" name="wp_pq_openai_model" value="' . esc_attr((string) get_option('wp_pq_openai_model', 'gpt-4o-mini')) . '" placeholder="gpt-4o-mini"></label>';
        echo '      <div class="wp-pq-create-actions">';
        echo '        <button class="button button-primary" type="submit">Save OpenAI Settings</button>';
        echo '        <a class="button" href="' . esc_url(admin_url('admin.php?page=wp-pq-ai-import')) . '">Open AI Import</a>';
        echo '      </div>';
        echo '    </form>';
        echo '    <div class="wp-pq-admin-callout">';
        echo '      <p><strong>Importer flow:</strong> Upload or paste a list, review the parsed tasks, then import them into the live queue.</p>';
        echo '      <p><strong>Recommended default:</strong> <code>gpt-4o-mini</code> for file-friendly parsing. You can override it if you prefer another supported OpenAI model.</p>';
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
        $selected_client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : (isset($_GET['client_user_id']) ? (int) $_GET['client_user_id'] : 0);
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
                return (int) ($row['client_id'] ?? 0) === $selected_client_id;
            }));
            $statements = array_values(array_filter($statements, static function (array $row) use ($selected_client_id): bool {
                return (int) ($row['client_id'] ?? 0) === $selected_client_id;
            }));
        }
        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Billing Rollup</h1>';
        echo '<p>Review completed work by date range, sort it into client jobs, and see what is still unbilled before you move into Work Statements or Invoice Drafts.</p>';
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
        echo '    <input type="hidden" name="client_id" value="' . (int) $selected_client_id . '">';
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
        echo self::render_rollup_client_jobs_panel($clients, $buckets_by_client, $selected_client_id, $range);

        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Existing Output</h2>';
        echo '    <p class="wp-pq-panel-note">Documents created from this date range.</p>';
        echo '    <h3>Work Statements</h3>';
        if (empty($work_logs)) {
            echo '<p class="wp-pq-empty-state">No work statements in this range yet.</p>';
        } else {
            echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Client</th><th>Jobs</th><th>Tasks</th><th>Actions</th></tr></thead><tbody>';
            foreach ($work_logs as $work_log) {
                $view_url = add_query_arg([
                    'page' => 'wp-pq-work-logs',
                    'month' => $range['month'],
                    'start_date' => $range['custom_start'],
                    'end_date' => $range['custom_end'],
                    'client_id' => (int) ($work_log['client_id'] ?? 0),
                    'work_log_id' => (int) $work_log['id'],
                ], admin_url('admin.php'));
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
                echo '<td><a class="button" href="' . esc_url($view_url) . '">View</a> <a class="button" target="_blank" href="' . esc_url($print_url) . '">Print / PDF</a> <a class="button" href="' . esc_url($export_url) . '">CSV</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '    <h3>Invoice Drafts</h3>';
        if (empty($statements)) {
            echo '<p class="wp-pq-empty-state">No invoice drafts in this range yet.</p>';
        } else {
            echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Client</th><th>Jobs</th><th>Work</th><th>Lines</th><th>Actions</th></tr></thead><tbody>';
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
                echo '<td>' . (int) ($statement['entry_count'] ?? $statement['task_count'] ?? 0) . '</td>';
                echo '<td>' . (int) ($statement['line_count'] ?? 0) . '</td>';
                echo '<td><a class="button" href="' . esc_url($view_url) . '">Open Draft</a> <a class="button" target="_blank" href="' . esc_url($print_url) . '">Print / PDF</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '  </section>';
        echo '</div>';

        echo '<div class="wp-pq-rollup-groups">';
        if (empty($groups)) {
            echo '<section class="wp-pq-panel"><p class="wp-pq-empty-state">No completed work fell inside this range.</p></section>';
        } else {
            foreach ($groups as $group) {
            echo '<section class="wp-pq-panel wp-pq-rollup-group">';
                echo '<h2>' . esc_html((string) $group['client_name']) . '</h2>';
                echo '<p class="wp-pq-panel-note"><strong>Job:</strong> ' . esc_html((string) $group['bucket_name']) . '</p>';
                echo '<p class="wp-pq-panel-note">' . count($group['entries']) . ' completed work item(s) in range. Invoice Draft eligibility: ' . (int) $group['invoice_ready_count'] . ' still eligible.</p>';
                echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Completed Work</th><th>Completed</th><th>Billing</th><th>Work Statement</th><th>Invoice Draft</th><th>Job</th></tr></thead><tbody>';
                foreach ($group['entries'] as $entry) {
                    $nonce_id = (int) ($entry['ledger_entry_id'] ?? 0) > 0 ? (int) $entry['ledger_entry_id'] : (int) ($entry['task_id'] ?? 0);
                    echo '<tr>';
                    echo '<td><strong>' . esc_html((string) ($entry['title_snapshot'] ?? 'Untitled work item')) . '</strong><br><span class="description">Ledger #' . (int) ($entry['ledger_entry_id'] ?? 0) . ((int) ($entry['task_id'] ?? 0) > 0 ? ' · Task #' . (int) $entry['task_id'] : '') . ' · ' . esc_html(wp_trim_words((string) ($entry['work_summary'] ?? ''), 18)) . '</span></td>';
                    echo '<td>' . esc_html(self::format_admin_datetime((string) ($entry['completion_date'] ?? ''))) . '</td>';
                    echo '<td>' . esc_html(self::rollup_billing_label($entry)) . '</td>';
                    echo '<td>' . esc_html((int) ($entry['task_id'] ?? 0) <= 0 ? 'No task snapshot' : ((int) ($entry['work_log_id'] ?? 0) > 0 ? 'Included before' : 'Available from task')) . '</td>';
                    echo '<td>' . esc_html(self::ledger_invoice_status_label((string) ($entry['invoice_status'] ?? 'unbilled'))) . '</td>';
                    echo '<td>';
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-form">';
                    wp_nonce_field('wp_pq_assign_bucket_' . $nonce_id);
                    echo '<input type="hidden" name="action" value="wp_pq_assign_bucket">';
                    echo '<input type="hidden" name="task_id" value="' . (int) ($entry['task_id'] ?? 0) . '">';
                    echo '<input type="hidden" name="ledger_entry_id" value="' . (int) ($entry['ledger_entry_id'] ?? 0) . '">';
                    echo '<input type="hidden" name="redirect_page" value="wp-pq-rollups">';
                    echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
                    echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
                    echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
                    echo '<input type="hidden" name="client_id" value="' . (int) ($group['client_id'] ?? 0) . '">';
                    echo '<select name="billing_bucket_id">';
                    foreach ($group['bucket_options'] as $bucket_option) {
                        echo '<option value="' . (int) $bucket_option['id'] . '"' . selected((int) $bucket_option['id'], (int) ($entry['billing_bucket_id'] ?? 0), false) . '>' . esc_html(self::bucket_label_from_row($bucket_option)) . '</option>';
                    }
                    echo '</select> <button class="button" type="submit">Save</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                $work_statement_url = add_query_arg([
                    'page' => 'wp-pq-work-logs',
                    'month' => $range['month'],
                    'start_date' => $range['custom_start'],
                    'end_date' => $range['custom_end'],
                    'client_id' => (int) ($group['client_id'] ?? 0),
                ], admin_url('admin.php'));
                $draft_url = add_query_arg([
                    'page' => 'wp-pq-statements',
                    'period' => $range['month'],
                    'client_id' => (int) ($group['client_id'] ?? 0),
                ], admin_url('admin.php'));
                echo '<div class="wp-pq-inline-action-form">';
                echo '<a class="button" href="' . esc_url($work_statement_url) . '">Open Work Statements</a>';
                echo '<a class="button button-primary" href="' . esc_url($draft_url) . '">Open Invoice Drafts</a>';
                echo '</div>';
                echo '</section>';
            }
        }
        echo '</div>';
        echo self::client_picker_script();
    }

    public static function render_work_logs_page(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $range = self::get_rollup_range();
        $selected_client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : (isset($_GET['client_user_id']) ? (int) $_GET['client_user_id'] : 0);
        $selected_work_log_id = isset($_GET['work_log_id']) ? (int) $_GET['work_log_id'] : 0;
        $work_log_summaries = self::get_work_log_summaries($range['start'], $range['end']);
        $work_log_detail = $selected_work_log_id > 0 ? self::get_work_log_detail($selected_work_log_id) : null;
        $clients = self::get_billing_clients();
        $jobs = $selected_client_id > 0 ? self::get_client_bucket_rows($selected_client_id) : [];
        $status_labels = self::work_statement_status_labels();
        $selected_statuses = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($_GET['statuses'] ?? [])))));
        if (empty($selected_statuses)) {
            $selected_statuses = array_keys($status_labels);
        }

        if ($selected_client_id > 0) {
            $work_log_summaries = array_values(array_filter($work_log_summaries, static function (array $log) use ($selected_client_id): bool {
                return (int) ($log['client_id'] ?? 0) === $selected_client_id;
            }));
            if ($work_log_detail && (int) ($work_log_detail['client_id'] ?? 0) !== $selected_client_id) {
                $work_log_detail = null;
            }
        }

        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Work Statements</h1>';
        echo '<p>Create optional, frozen client work reports from one client, one date range, and the jobs or statuses you want represented in the snapshot.</p>';
        echo self::admin_section_nav('work_logs');

        if (isset($_GET['wp_pq_notice'])) {
            $notice = sanitize_key((string) $_GET['wp_pq_notice']);
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';
            $class = in_array($notice, ['work_log_created', 'work_log_updated'], true) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message ?: 'Work statement updated.') . '</p></div>';
        }

        echo self::render_client_datalist($clients, 'wp-pq-client-options');
        echo '<div class="wp-pq-panel wp-pq-filter-bar">';
        echo '  <form method="get" class="wp-pq-period-form">';
        echo '    <input type="hidden" name="page" value="wp-pq-work-logs">';
        echo '    <label>Month';
        echo '      <input type="month" name="month" value="' . esc_attr($range['month']) . '">';
        echo '    </label>';
        echo '    <label>Custom Start';
        echo '      <input type="date" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '    </label>';
        echo '    <label>Custom End';
        echo '      <input type="date" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        echo '    </label>';
        echo self::render_client_picker('work-log-client-filter', 'client_id', $clients, $selected_client_id, 'Client', 'Type a client name or email');
        echo '    <button class="button" type="submit">Filter History</button>';
        echo '  </form>';
        echo '  <p class="wp-pq-panel-note">Current range: ' . esc_html($range['label']) . '</p>';
        echo '</div>';

        echo '<div class="wp-pq-billing-grid">';
        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Create Work Statement Snapshot</h2>';
        if ($selected_client_id <= 0) {
            echo '<p class="wp-pq-empty-state">Pick a client above to build a frozen work statement snapshot.</p>';
        } else {
            echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-ai-form">';
            wp_nonce_field('wp_pq_create_work_log');
            echo '      <input type="hidden" name="action" value="wp_pq_create_work_log">';
            echo '      <input type="hidden" name="redirect_page" value="wp-pq-work-logs">';
            echo '      <input type="hidden" name="client_id" value="' . (int) $selected_client_id . '">';
            echo '      <input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
            echo '      <input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
            echo '      <input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
            echo '      <p class="wp-pq-panel-note">This snapshot freezes on creation and never auto-updates. It can span multiple jobs and statuses for the selected client.</p>';
            if (! empty($jobs)) {
                echo '      <label>Jobs';
                echo '        <select name="job_ids[]" multiple size="' . esc_attr((string) min(6, max(3, count($jobs)))) . '">';
                foreach ($jobs as $job) {
                    $job_id = (int) ($job['id'] ?? 0);
                    echo '<option value="' . $job_id . '">' . esc_html(self::bucket_label_from_row($job)) . '</option>';
                }
                echo '        </select>';
                echo '      </label>';
            }
            echo '      <fieldset class="wp-pq-status-filter-fieldset"><legend>Status filters</legend>';
            foreach ($status_labels as $status_key => $status_label) {
                echo '<label class="inline"><input type="checkbox" name="statuses[]" value="' . esc_attr($status_key) . '"' . checked(in_array($status_key, $selected_statuses, true), true, false) . '> ' . esc_html($status_label) . '</label>';
            }
            echo '      </fieldset>';
            echo '      <label>Notes <textarea name="notes" rows="4" placeholder="Optional summary or client-facing context for this frozen report."></textarea></label>';
            echo '      <div class="wp-pq-create-actions">';
            echo '        <button class="button button-primary" type="submit">Create Work Statement</button>';
            echo '      </div>';
            echo '    </form>';
        }
        echo '  </section>';

        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Work Statement History</h2>';
        echo '    <p class="wp-pq-panel-note">These are frozen client reports. Open one to review the exact job/status snapshot that was captured.</p>';

        if (empty($work_log_summaries)) {
            echo '<p class="wp-pq-empty-state">No work statements have been created in this range yet.</p>';
        } else {
            echo '<table class="widefat striped wp-pq-admin-table"><thead><tr><th>Code</th><th>Client</th><th>Jobs</th><th>Range</th><th>Tasks</th><th>Actions</th></tr></thead><tbody>';
            foreach ($work_log_summaries as $work_log) {
                $view_url = add_query_arg([
                    'page' => 'wp-pq-work-logs',
                    'month' => $range['month'],
                    'start_date' => $range['custom_start'],
                    'end_date' => $range['custom_end'],
                    'client_id' => $selected_client_id,
                    'work_log_id' => (int) $work_log['id'],
                ], admin_url('admin.php'));
                $print_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'wp_pq_print_work_log',
                        'work_log_id' => (int) $work_log['id'],
                    ], admin_url('admin-post.php')),
                    'wp_pq_print_work_log_' . (int) $work_log['id']
                );
                $export_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'wp_pq_export_work_log',
                        'work_log_id' => (int) $work_log['id'],
                    ], admin_url('admin-post.php')),
                    'wp_pq_export_work_log_' . (int) $work_log['id']
                );
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $work_log['work_log_code']) . '</strong></td>';
                echo '<td>' . esc_html(self::client_label_from_row($work_log)) . '</td>';
                echo '<td>' . esc_html(self::bucket_label_from_row($work_log)) . '</td>';
                echo '<td>' . esc_html(self::format_date_range((string) $work_log['range_start'], (string) $work_log['range_end'])) . '</td>';
                echo '<td>' . (int) $work_log['task_count'] . '</td>';
                echo '<td><a class="button" href="' . esc_url($view_url) . '">View</a> <a class="button" target="_blank" href="' . esc_url($print_url) . '">Print / PDF</a> <a class="button" href="' . esc_url($export_url) . '">CSV</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '  </section>';
        echo '</div>';

        if ($work_log_detail) {
            echo '<section class="wp-pq-panel wp-pq-statement-detail">';
            echo '  <h2>Work Statement ' . esc_html((string) $work_log_detail['work_log_code']) . '</h2>';
            echo '  <p class="wp-pq-panel-note">Created ' . esc_html(self::format_admin_datetime((string) $work_log_detail['created_at'])) . ' for ' . esc_html(self::client_label_from_row($work_log_detail)) . ' across ' . esc_html(self::bucket_label_from_row($work_log_detail)) . '.</p>';
            echo '  <div class="wp-pq-admin-callout"><p><strong>Freeze rule:</strong> this report is a creation-time snapshot. Later task changes do not change this document.</p></div>';
            echo '  <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
            wp_nonce_field('wp_pq_update_work_log_' . (int) $work_log_detail['id']);
            echo '    <input type="hidden" name="action" value="wp_pq_update_work_log">';
            echo '    <input type="hidden" name="work_log_id" value="' . (int) $work_log_detail['id'] . '">';
            echo '    <input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
            echo '    <input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
            echo '    <input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
            echo '    <input type="hidden" name="client_id" value="' . (int) $selected_client_id . '">';
            echo '    <label>Work statement notes <textarea name="notes" rows="4" placeholder="Optional client-facing notes for this work statement.">' . esc_textarea((string) ($work_log_detail['notes'] ?? '')) . '</textarea></label>';
            echo '    <div class="wp-pq-inline-action-form">';
            echo '      <button class="button button-primary" type="submit">Save Work Statement</button>';
            echo '      <a class="button" target="_blank" href="' . esc_url(wp_nonce_url(add_query_arg([
                'action' => 'wp_pq_print_work_log',
                'work_log_id' => (int) $work_log_detail['id'],
            ], admin_url('admin-post.php')), 'wp_pq_print_work_log_' . (int) $work_log_detail['id'])) . '">Print / PDF</a>';
            echo '      <a class="button" href="' . esc_url(wp_nonce_url(add_query_arg([
                'action' => 'wp_pq_export_work_log',
                'work_log_id' => (int) $work_log_detail['id'],
            ], admin_url('admin-post.php')), 'wp_pq_export_work_log_' . (int) $work_log_detail['id'])) . '">CSV</a>';
            echo '    </div>';
            echo '  </form>';
            echo '  <table class="widefat striped wp-pq-admin-table">';
            echo '    <thead><tr><th>Task</th><th>Job</th><th>Status</th><th>Updated</th><th>Billing status</th></tr></thead>';
            echo '    <tbody>';
            foreach ($work_log_detail['tasks'] as $task) {
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $task['title']) . '</strong><br><span class="description">#' . (int) $task['id'] . '</span></td>';
                echo '<td>' . esc_html(self::bucket_label_from_row($task)) . '</td>';
                echo '<td>' . esc_html(self::humanize_label((string) ($task['status'] ?? 'pending_approval'))) . '</td>';
                echo '<td>' . esc_html(self::format_admin_datetime((string) ($task['updated_at'] ?? $task['created_at'] ?? ''))) . '</td>';
                echo '<td>' . esc_html(self::billing_status_label((string) ($task['billing_status'] ?? 'unbilled'))) . '</td>';
                echo '</tr>';
            }
            echo '    </tbody>';
            echo '  </table>';
            echo '</section>';
        }

        echo '</div>';
        echo self::client_picker_script();
    }

    public static function render_ai_import_page(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $clients = self::get_billing_clients();
        $selected_client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        $selected_bucket_id = isset($_GET['bucket_id']) ? (int) $_GET['bucket_id'] : 0;
        $preview = self::get_ai_import_preview();
        if ($preview) {
            if ($selected_client_id <= 0) {
                $selected_client_id = (int) ($preview['client_id'] ?? 0);
            }
            if ($selected_bucket_id <= 0) {
                $selected_bucket_id = (int) ($preview['billing_bucket_id'] ?? 0);
            }
        }
        $client = $selected_client_id > 0 ? self::find_client_by_id($clients, $selected_client_id) : null;
        $jobs = $selected_client_id > 0 ? self::get_client_bucket_rows($selected_client_id) : [];
        $openai_key = trim((string) get_option('wp_pq_openai_api_key', ''));

        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>AI Import</h1>';
        echo '<p>Paste a messy task list or upload a document, review the parsed draft, then import it into the live queue with the right client and job context.</p>';
        echo self::admin_section_nav('ai_import');

        if (isset($_GET['wp_pq_notice'])) {
            $notice = sanitize_key((string) $_GET['wp_pq_notice']);
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';
            $class = in_array($notice, ['ai_import_parsed', 'ai_import_done'], true) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
        }

        echo self::render_client_datalist($clients, 'wp-pq-client-options');
        echo '<div class="wp-pq-billing-grid">';
        echo self::render_ai_parse_panel($clients, $jobs, $selected_client_id, $selected_bucket_id, $openai_key);
        echo self::render_ai_rules_panel($client, $jobs, $selected_bucket_id);
        echo '</div>';

        if ($preview) {
            echo self::render_ai_preview_panel($preview, $clients);
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
        $selected_client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : (isset($_GET['client_user_id']) ? (int) $_GET['client_user_id'] : 0);
        $selected_statement_id = isset($_GET['statement_id']) ? (int) $_GET['statement_id'] : 0;
        $unbilled_entries = self::get_unbilled_ledger_entries($selected_month);
        $statement_summaries = self::get_statement_summaries($selected_month);
        $statement_detail = $selected_statement_id > 0 ? self::get_statement_detail($selected_statement_id) : null;
        $clients = self::get_billing_clients();
        if ($selected_client_id > 0) {
            $unbilled_entries = array_values(array_filter($unbilled_entries, static function (array $entry) use ($selected_client_id): bool {
                return (int) ($entry['client_id'] ?? 0) === $selected_client_id;
            }));
            $statement_summaries = array_values(array_filter($statement_summaries, static function (array $statement) use ($selected_client_id): bool {
                return (int) ($statement['client_id'] ?? 0) === $selected_client_id;
            }));
            if ($statement_detail && (int) ($statement_detail['client_id'] ?? 0) !== $selected_client_id) {
                $statement_detail = null;
            }
        }

        $client_jobs = $selected_client_id > 0 ? self::get_client_bucket_rows($selected_client_id) : [];
        $detail_jobs = $statement_detail ? self::get_client_bucket_rows((int) ($statement_detail['client_id'] ?? 0)) : $client_jobs;
        $eligible_by_job = [];
        foreach ($unbilled_entries as $entry) {
            $job_key = (int) ($entry['billing_bucket_id'] ?? 0);
            if (! isset($eligible_by_job[$job_key])) {
                $eligible_by_job[$job_key] = [
                    'job_label' => self::bucket_label_from_row($entry),
                    'entries' => [],
                ];
            }
            $eligible_by_job[$job_key]['entries'][] = $entry;
        }

        $line_type_labels = self::invoice_line_type_labels();

        echo '<div class="wrap wp-pq-wrap">';
        echo '<h1>Invoice Drafts</h1>';
        echo '<p>Build single-client invoice drafts that can span one or many jobs, then export a canonical CSV for accounting handoff.</p>';
        echo self::admin_section_nav('statements');

        if (isset($_GET['wp_pq_notice'])) {
            $notice = sanitize_key((string) $_GET['wp_pq_notice']);
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';
            if ($notice === 'statement_created') {
                echo '<div class="notice notice-success"><p>' . esc_html($message ?: 'Invoice Draft created.') . '</p></div>';
            } elseif ($notice === 'statement_error') {
                echo '<div class="notice notice-error"><p>' . esc_html($message ?: 'There was a problem updating the Invoice Draft.') . '</p></div>';
            }
        }

        echo self::render_client_datalist($clients, 'wp-pq-client-options');
        echo '<div class="wp-pq-panel wp-pq-filter-bar">';
        echo '  <form method="get" class="wp-pq-period-form">';
        echo '    <input type="hidden" name="page" value="wp-pq-statements">';
        echo '    <label>Period';
        echo '      <input type="month" name="period" value="' . esc_attr($selected_month) . '">';
        echo '    </label>';
        echo self::render_client_picker('statement-client-filter', 'client_id', $clients, $selected_client_id, 'Client', 'Type a client name or email');
        echo '    <button class="button" type="submit">Open Workspace</button>';
        echo '  </form>';
        echo '</div>';

        echo '<div class="wp-pq-billing-grid">';
        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Start Invoice Draft</h2>';
        if ($selected_client_id <= 0) {
            echo '<p class="wp-pq-empty-state">Choose a client above to start an Invoice Draft workspace.</p>';
        } else {
            echo '    <div class="wp-pq-admin-callout">';
            echo '      <p><strong>Rule:</strong> line items may be initialized from completed work, but they stop deriving from that source as soon as the draft is created.</p>';
            echo '      <p><strong>Client guardrail:</strong> this workspace never mixes clients. One draft belongs to one client only.</p>';
            echo '    </div>';

            echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-ai-form">';
            wp_nonce_field('wp_pq_create_statement');
            echo '      <input type="hidden" name="action" value="wp_pq_create_statement">';
            echo '      <input type="hidden" name="redirect_page" value="wp-pq-statements">';
            echo '      <input type="hidden" name="client_id" value="' . (int) $selected_client_id . '">';
            echo '      <input type="hidden" name="period" value="' . esc_attr($selected_month) . '">';
            if (empty($eligible_by_job)) {
                echo '<p class="wp-pq-empty-state">No unbilled completed work was found for this client in ' . esc_html($selected_month) . '.</p>';
            } else {
                echo '<table class="widefat striped wp-pq-admin-table">';
                echo '<thead><tr><th class="check-column"><input type="checkbox" data-pq-check-all></th><th>Completed Work</th><th>Job</th><th>Completed</th><th>Owner</th></tr></thead><tbody>';
                foreach ($eligible_by_job as $group) {
                    echo '<tr class="wp-pq-admin-group-row"><td colspan="5"><strong>' . esc_html((string) $group['job_label']) . '</strong></td></tr>';
                    foreach ($group['entries'] as $entry) {
                        echo '<tr>';
                        echo '<td class="check-column"><input type="checkbox" name="entry_ids[]" value="' . (int) $entry['id'] . '"></td>';
                        echo '<td><strong>' . esc_html((string) $entry['title']) . '</strong><br><span class="description">Ledger #' . (int) $entry['id'] . ((int) ($entry['task_id'] ?? 0) > 0 ? ' · Task #' . (int) $entry['task_id'] : '') . ' · ' . esc_html(wp_trim_words((string) $entry['description'], 18)) . '</span></td>';
                        echo '<td>' . esc_html(self::bucket_label_from_row($entry)) . '</td>';
                        echo '<td>' . esc_html(self::format_admin_datetime((string) $entry['completion_date'])) . '</td>';
                        echo '<td>' . esc_html((string) ($entry['owner_name'] ?? 'Unassigned')) . '</td>';
                        echo '</tr>';
                    }
                }
                echo '</tbody></table>';
            }
            echo '      <label>Draft Notes';
            echo '        <textarea name="notes" rows="4" placeholder="Optional internal context for this Invoice Draft."></textarea>';
            echo '      </label>';
            echo '      <div class="wp-pq-create-actions">';
            echo '        <button class="button button-primary" type="submit">Create Invoice Draft from Selected Work</button>';
            echo '      </div>';
            echo '    </form>';

            echo '    <hr>';
            echo '    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-ai-form">';
            wp_nonce_field('wp_pq_create_statement');
            echo '      <input type="hidden" name="action" value="wp_pq_create_statement">';
            echo '      <input type="hidden" name="redirect_page" value="wp-pq-statements">';
            echo '      <input type="hidden" name="client_id" value="' . (int) $selected_client_id . '">';
            echo '      <input type="hidden" name="period" value="' . esc_attr($selected_month) . '">';
            echo '      <p class="wp-pq-panel-note">Need a retainer, fixed fee, subscription, pass-through, or manual adjustment first? Start an empty draft and add lines directly.</p>';
            echo '      <label>Draft Notes';
            echo '        <textarea name="notes" rows="3" placeholder="Optional starting note for an empty draft."></textarea>';
            echo '      </label>';
            echo '      <div class="wp-pq-create-actions">';
            echo '        <button class="button" type="submit">Create Empty Invoice Draft</button>';
            echo '      </div>';
            echo '    </form>';
        }
        echo '  </section>';

        echo '  <section class="wp-pq-panel">';
        echo '    <h2>Invoice Draft History</h2>';
        echo '    <p class="wp-pq-panel-note">These drafts are accounting handoff records. Totals come from line items only.</p>';
        if (empty($statement_summaries)) {
            echo '<p class="wp-pq-empty-state">No invoice drafts have been created for this period yet.</p>';
        } else {
            echo '      <table class="widefat striped wp-pq-admin-table">';
            echo '        <thead><tr><th>Code</th><th>Jobs</th><th>Work</th><th>Lines</th><th>Total</th><th>Actions</th></tr></thead>';
            echo '        <tbody>';
            foreach ($statement_summaries as $statement) {
                $view_url = add_query_arg([
                    'page' => 'wp-pq-statements',
                    'period' => $selected_month,
                    'client_id' => (int) ($statement['client_id'] ?? 0),
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
                echo '<td>' . esc_html(self::bucket_label_from_row($statement)) . '</td>';
                echo '<td>' . (int) ($statement['entry_count'] ?? $statement['task_count'] ?? 0) . '</td>';
                echo '<td>' . (int) ($statement['line_count'] ?? 0) . '</td>';
                echo '<td>' . esc_html((string) ($statement['currency_code'] ?: 'USD')) . ' ' . esc_html(number_format((float) ($statement['total_amount'] ?? 0), 2)) . '</td>';
                echo '<td><a class="button" href="' . esc_url($view_url) . '">Open</a> <a class="button" href="' . esc_url($export_url) . '">CSV</a> <a class="button" target="_blank" href="' . esc_url($print_url) . '">Print / PDF</a></td>';
                echo '</tr>';
            }
            echo '        </tbody>';
            echo '      </table>';
        }
        echo '  </section>';
        echo '</div>';

        if ($statement_detail) {
            $statement_total = number_format((float) ($statement_detail['total_amount'] ?? 0), 2);
            echo '<section class="wp-pq-panel wp-pq-statement-detail">';
            echo '  <h2>Invoice Draft ' . esc_html((string) $statement_detail['statement_code']) . '</h2>';
            echo '  <p class="wp-pq-panel-note">Created ' . esc_html(self::format_admin_datetime((string) $statement_detail['created_at'])) . ' by ' . esc_html((string) $statement_detail['creator_name']) . ' for ' . esc_html(self::client_label_from_row($statement_detail)) . ' across ' . esc_html(self::bucket_label_from_row($statement_detail)) . '.</p>';
            echo '  <div class="wp-pq-admin-callout">';
            echo '    <p><strong>Financial truth:</strong> totals come from line items only. Linked tasks are there for traceability and to prevent double-billing.</p>';
            echo '    <p><strong>One-way rule:</strong> work-derived lines can be edited, but they do not re-derive from completed work after the draft is created.</p>';
            echo '  </div>';
            if (! empty($statement_detail['notes'])) {
                echo '  <div class="wp-pq-admin-callout"><p>' . esc_html((string) $statement_detail['notes']) . '</p></div>';
            }
            echo '  <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
            wp_nonce_field('wp_pq_update_statement_' . (int) $statement_detail['id']);
            echo '    <input type="hidden" name="action" value="wp_pq_update_statement">';
            echo '    <input type="hidden" name="statement_id" value="' . (int) $statement_detail['id'] . '">';
            echo '    <div class="wp-pq-admin-callout"><p><strong>Computed total:</strong> ' . esc_html((string) ($statement_detail['currency_code'] ?: 'USD')) . ' ' . esc_html($statement_total) . '</p></div>';
            echo '    <label>Currency <input type="text" name="currency_code" value="' . esc_attr((string) ($statement_detail['currency_code'] ?: 'USD')) . '" maxlength="10"></label>';
            echo '    <label>Due date <input type="date" name="due_date" value="' . esc_attr((string) $statement_detail['due_date']) . '"></label>';
            echo '    <label>Invoice Draft notes <textarea name="notes" rows="4">' . esc_textarea((string) $statement_detail['notes']) . '</textarea></label>';
            echo '    <h3>Invoice Lines</h3>';
            echo '    <table class="widefat striped wp-pq-admin-table">';
            echo '      <thead><tr><th>Type</th><th>Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Amount</th><th>Job</th><th>Notes</th><th>Remove</th></tr></thead><tbody>';
            foreach ((array) ($statement_detail['lines'] ?? []) as $line) {
                $line_id = (int) ($line['id'] ?? 0);
                $mismatch = self::line_source_mismatch_message($line);
                echo '<tr>';
                echo '<td><input type="hidden" name="line_id[]" value="' . $line_id . '"><select name="line_type[]">';
                foreach ($line_type_labels as $line_key => $line_label) {
                    echo '<option value="' . esc_attr($line_key) . '"' . selected((string) ($line['line_type'] ?? 'manual_adjustment'), $line_key, false) . '>' . esc_html($line_label) . '</option>';
                }
                echo '</select></td>';
                echo '<td><textarea name="line_description[]" rows="3" placeholder="Line description">' . esc_textarea((string) ($line['description'] ?? '')) . '</textarea>';
                echo '<div class="description">' . esc_html((string) ($line['source_kind'] ?? 'manual') === 'task' ? 'Work-derived' : 'Manual line') . ' · ' . count(self::decode_line_task_ids($line)) . ' linked task(s)</div>';
                if ($mismatch !== '') {
                    echo '<div class="description" style="color:#b45309;">' . esc_html($mismatch) . '</div>';
                }
                echo '</td>';
                echo '<td><input type="number" name="line_quantity[]" min="0" step="0.01" value="' . esc_attr((string) ($line['quantity'] ?? '')) . '"></td>';
                echo '<td><input type="text" name="line_unit[]" value="' . esc_attr((string) ($line['unit'] ?? '')) . '"></td>';
                echo '<td><input type="number" name="line_unit_rate[]" min="0" step="0.01" value="' . esc_attr((string) ($line['unit_rate'] ?? '')) . '"></td>';
                echo '<td><input type="number" name="line_amount[]" min="0" step="0.01" value="' . esc_attr((string) ($line['line_amount'] ?? '')) . '"></td>';
                echo '<td>' . self::render_bucket_select('line_bucket_id[]', $detail_jobs, (int) ($line['billing_bucket_id'] ?? 0), '', 'No job') . '</td>';
                echo '<td><textarea name="line_notes[]" rows="3" placeholder="Optional note">' . esc_textarea((string) ($line['notes'] ?? '')) . '</textarea></td>';
                echo '<td><label class="inline"><input type="checkbox" name="remove_line_ids[]" value="' . $line_id . '"> Remove</label></td>';
                echo '</tr>';
            }
            for ($line_index = 0; $line_index < 3; $line_index++) {
                echo '<tr>';
                echo '<td><select name="new_line_type[]">';
                foreach ($line_type_labels as $line_key => $line_label) {
                    echo '<option value="' . esc_attr($line_key) . '"' . selected($line_key, 'manual_adjustment', false) . '>' . esc_html($line_label) . '</option>';
                }
                echo '</select></td>';
                echo '<td><textarea name="new_line_description[]" rows="3" placeholder="New line description"></textarea></td>';
                echo '<td><input type="number" name="new_line_quantity[]" min="0" step="0.01"></td>';
                echo '<td><input type="text" name="new_line_unit[]" placeholder="hours, mo, etc."></td>';
                echo '<td><input type="number" name="new_line_unit_rate[]" min="0" step="0.01"></td>';
                echo '<td><input type="number" name="new_line_amount[]" min="0" step="0.01"></td>';
                echo '<td>' . self::render_bucket_select('new_line_bucket_id[]', $detail_jobs, 0, '', 'No job') . '</td>';
                echo '<td><textarea name="new_line_notes[]" rows="3" placeholder="Optional note"></textarea></td>';
                echo '<td></td>';
                echo '</tr>';
            }
            echo '      </tbody></table>';
            echo '    <div class="wp-pq-inline-action-form">';
            echo '      <button class="button button-primary" type="submit">Save Invoice Draft</button>';
            echo '      <a class="button" href="' . esc_url(wp_nonce_url(add_query_arg([
                'action' => 'wp_pq_export_statement',
                'statement_id' => (int) $statement_detail['id'],
            ], admin_url('admin-post.php')), 'wp_pq_export_statement_' . (int) $statement_detail['id'])) . '">Export Canonical CSV</a>';
            echo '      <a class="button" target="_blank" href="' . esc_url(wp_nonce_url(add_query_arg([
                'action' => 'wp_pq_print_statement',
                'statement_id' => (int) $statement_detail['id'],
            ], admin_url('admin-post.php')), 'wp_pq_print_statement_' . (int) $statement_detail['id'])) . '">Print / PDF</a>';
            echo '    </div>';
            echo '  </form>';
            echo '  <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form">';
            wp_nonce_field('wp_pq_delete_statement_' . (int) $statement_detail['id']);
            echo '    <input type="hidden" name="action" value="wp_pq_delete_statement">';
            echo '    <input type="hidden" name="statement_id" value="' . (int) $statement_detail['id'] . '">';
            echo '    <button class="button" type="submit">Delete Invoice Draft</button>';
            echo '  </form>';
            echo '  <h3>Linked Tasks</h3>';
            echo '  <table class="widefat striped wp-pq-admin-table">';
            echo '    <thead><tr><th>Task</th><th>Job</th><th>Delivered</th><th>Billing Status</th><th>Actions</th></tr></thead>';
            echo '    <tbody>';
            foreach ($statement_detail['tasks'] as $task) {
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $task['title']) . '</strong><br><span class="description">#' . (int) $task['id'] . '</span></td>';
                echo '<td>' . esc_html(self::bucket_label_from_row($task)) . '</td>';
                echo '<td>' . esc_html(self::format_admin_datetime((string) $task['delivered_at'])) . '</td>';
                echo '<td>' . esc_html(self::billing_status_label((string) ($task['billing_status'] ?? 'unbilled'))) . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-form">';
                wp_nonce_field('wp_pq_remove_statement_task_' . (int) $statement_detail['id'] . '_' . (int) $task['id']);
                echo '<input type="hidden" name="action" value="wp_pq_remove_statement_task">';
                echo '<input type="hidden" name="statement_id" value="' . (int) $statement_detail['id'] . '">';
                echo '<input type="hidden" name="task_id" value="' . (int) $task['id'] . '">';
                echo '<button class="button" type="submit">Remove Task</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '    </tbody>';
            echo '  </table>';
            echo '</section>';
        }

        echo '</div>';
        echo "<script>document.addEventListener('DOMContentLoaded',function(){var all=document.querySelector('[data-pq-check-all]');if(!all)return;all.addEventListener('change',function(){document.querySelectorAll('input[name=\"entry_ids[]\"], input[name=\"task_ids[]\"]').forEach(function(box){box.checked=all.checked;});});});</script>";
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

        $client_id = WP_PQ_DB::get_or_create_client_id_for_user($client_user_id, $client_name);
        WP_PQ_DB::ensure_client_member($client_id, $client_user_id, 'client_admin');

        if ($initial_bucket_name === '') {
            $initial_bucket_name = $created
                ? trim($client_name) . ' - Main'
                : WP_PQ_DB::suggest_default_bucket_name($client_id);
        }
        self::create_bucket_for_client($client_id, $initial_bucket_name);

        $message = $created ? 'Client created and ready for billing.' : 'Existing user linked as a client.';
        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_saved', $message, [], ['client_user_id' => $client_user_id, 'client_id' => $client_id]));
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
        $client_id = WP_PQ_DB::get_or_create_client_id_for_user((int) $user->ID, (string) $user->display_name);
        WP_PQ_DB::ensure_client_member($client_id, (int) $user->ID, 'client_admin');
        if ($initial_bucket_name === '') {
            $initial_bucket_name = WP_PQ_DB::suggest_default_bucket_name($client_id);
        }
        self::create_bucket_for_client($client_id, $initial_bucket_name);

        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_saved', 'Existing user linked as a client.', [], ['client_user_id' => (int) $user->ID, 'client_id' => $client_id]));
        exit;
    }

    public static function handle_create_bucket(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_create_bucket');
        global $wpdb;

        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $redirect_page = sanitize_key((string) ($_POST['redirect_page'] ?? 'wp-pq-rollups'));
        if ($client_id <= 0 && isset($_POST['client_user_id'])) {
            $client_id = WP_PQ_DB::get_or_create_client_id_for_user((int) $_POST['client_user_id']);
        }
        $bucket_name = sanitize_text_field(wp_unslash((string) ($_POST['bucket_name'] ?? '')));
        if ($client_id > 0 && $bucket_name !== '') {
            self::create_bucket_for_client($client_id, $bucket_name);
        }

        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'bucket_saved', 'Job saved.', [], ['client_id' => $client_id]));
        exit;
    }

    public static function handle_delete_bucket(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $bucket_id = isset($_POST['bucket_id']) ? (int) $_POST['bucket_id'] : 0;
        check_admin_referer('wp_pq_delete_bucket_' . $bucket_id);

        $redirect_page = sanitize_key((string) ($_POST['redirect_page'] ?? 'wp-pq-rollups'));
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        if ($bucket_id <= 0) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'rollup_error', 'Choose a job to delete.', [], ['client_id' => $client_id]));
            exit;
        }

        $counts = self::get_bucket_dependency_counts($bucket_id);
        if (! self::bucket_can_be_deleted($counts)) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'rollup_error', 'That job still has tasks, work statements, or statements attached to it.', [], ['client_id' => $client_id]));
            exit;
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'pq_billing_buckets', ['id' => $bucket_id]);
        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'bucket_saved', 'Empty job deleted.', [], ['client_id' => $client_id]));
        exit;
    }

    public static function handle_add_client_member(): void
    {
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        if (! self::can_manage_client($client_id)) {
            wp_die('Forbidden');
        }
        check_admin_referer('wp_pq_add_client_member_' . $client_id);

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $role = sanitize_key((string) ($_POST['client_role'] ?? 'client_contributor'));
        $redirect_page = sanitize_key((string) ($_POST['redirect_page'] ?? 'wp-pq-client-directory'));

        if (! in_array($role, ['client_admin', 'client_contributor', 'client_viewer'], true)) {
            $role = 'client_contributor';
        }

        $user = $user_id > 0 ? get_user_by('ID', $user_id) : false;
        if ($client_id <= 0 || ! $user) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_error', 'Choose a valid user to add to this client.', [], ['client_id' => $client_id]));
            exit;
        }

        $user->add_role('pq_client');
        WP_PQ_DB::ensure_client_member($client_id, $user_id, $role);

        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_saved', 'Client member saved.', [], ['client_id' => $client_id]));
        exit;
    }

    public static function handle_assign_job_member(): void
    {
        $bucket_id = isset($_POST['billing_bucket_id']) ? (int) $_POST['billing_bucket_id'] : 0;
        check_admin_referer('wp_pq_assign_job_member_' . $bucket_id);
        global $wpdb;

        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        if (! self::can_manage_client($client_id)) {
            wp_die('Forbidden');
        }
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $redirect_page = sanitize_key((string) ($_POST['redirect_page'] ?? 'wp-pq-client-directory'));
        $bucket = $bucket_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM {$wpdb->prefix}pq_billing_buckets WHERE id = %d", $bucket_id), ARRAY_A) : null;

        if (! $bucket || (int) ($bucket['client_id'] ?? 0) !== $client_id || $user_id <= 0) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_error', 'Choose a valid client member and job.', [], ['client_id' => $client_id]));
            exit;
        }

        $member_ids = array_map(static fn(array $membership): int => (int) ($membership['user_id'] ?? 0), WP_PQ_DB::get_client_memberships($client_id));
        if (! in_array($user_id, $member_ids, true)) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_error', 'Add the user to the client account before assigning them to a job.', [], ['client_id' => $client_id]));
            exit;
        }

        if (in_array($user_id, WP_PQ_DB::get_job_member_ids($bucket_id), true)) {
            wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_saved', 'That member already has access to this job.', [], ['client_id' => $client_id]));
            exit;
        }

        WP_PQ_DB::ensure_job_member($bucket_id, $user_id);
        wp_safe_redirect(self::admin_redirect_url($redirect_page, 'client_saved', 'Job access saved.', [], ['client_id' => $client_id]));
        exit;
    }

    public static function handle_assign_bucket(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        $ledger_entry_id = isset($_POST['ledger_entry_id']) ? (int) $_POST['ledger_entry_id'] : 0;
        $nonce_id = $ledger_entry_id > 0 ? $ledger_entry_id : $task_id;
        check_admin_referer('wp_pq_assign_bucket_' . $nonce_id);
        global $wpdb;

        $bucket_id = isset($_POST['billing_bucket_id']) ? (int) $_POST['billing_bucket_id'] : 0;
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $task = $task_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM {$tasks_table} WHERE id = %d", $task_id), ARRAY_A) : null;
        $ledger_entry = $ledger_entry_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, task_id, client_id FROM {$ledger_table} WHERE id = %d", $ledger_entry_id), ARRAY_A) : null;
        $bucket = $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM {$buckets_table} WHERE id = %d", $bucket_id), ARRAY_A);
        $range = self::get_rollup_range_from_request($_POST);
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;

        if ($bucket && $ledger_entry && (int) ($ledger_entry['client_id'] ?? 0) === (int) ($bucket['client_id'] ?? 0)) {
            $wpdb->update($ledger_table, [
                'billing_bucket_id' => $bucket_id,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $ledger_entry_id]);
            if ($task_id <= 0) {
                $task_id = (int) ($ledger_entry['task_id'] ?? 0);
                if ($task_id > 0) {
                    $task = $wpdb->get_row($wpdb->prepare("SELECT id, client_id FROM {$tasks_table} WHERE id = %d", $task_id), ARRAY_A);
                }
            }
        }

        if ($task && $bucket && (int) ($task['client_id'] ?? 0) === (int) ($bucket['client_id'] ?? 0)) {
            $wpdb->update($tasks_table, [
                'billing_bucket_id' => $bucket_id,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $task_id]);
        }

        wp_safe_redirect(self::admin_redirect_url('wp-pq-rollups', 'bucket_saved', 'Completed work job updated.', $range, ['client_id' => $client_id]));
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
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['notes'])) : '';

        if (! empty($task_ids)) {
            $result = WP_PQ_API::create_work_log_batch($task_ids, $notes, $range['start'], $range['end'], get_current_user_id());
        } else {
            $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
            $job_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['job_ids'] ?? [])))));
            $statuses = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($_POST['statuses'] ?? [])))));
            $result = WP_PQ_API::create_work_log_snapshot([
                'client_id' => $client_id,
                'range_start' => $range['start'],
                'range_end' => $range['end'],
                'job_ids' => $job_ids,
                'statuses' => $statuses,
                'notes' => $notes,
            ], get_current_user_id());
        }

        if (is_wp_error($result)) {
            wp_safe_redirect(self::admin_redirect_url((string) ($_POST['redirect_page'] ?? 'wp-pq-work-logs'), 'work_log_error', $result->get_error_message(), $range, [
                'client_id' => isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0,
            ]));
            exit;
        }

        wp_safe_redirect(self::admin_redirect_url((string) ($_POST['redirect_page'] ?? 'wp-pq-work-logs'), 'work_log_created', sprintf('Work statement %s created with %d task%s.', $result['code'], (int) $result['task_count'], ((int) $result['task_count'] === 1 ? '' : 's')), $range, [
            'client_id' => isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0,
            'work_log_id' => (int) $result['id'],
        ]));
        exit;
    }

    public static function handle_update_work_log(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $work_log_id = isset($_POST['work_log_id']) ? (int) $_POST['work_log_id'] : 0;
        check_admin_referer('wp_pq_update_work_log_' . $work_log_id);

        if ($work_log_id <= 0) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-work-logs', 'work_log_error', 'Work statement not found.'));
            exit;
        }

        global $wpdb;
        $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? '')));
        $wpdb->update($wpdb->prefix . 'pq_work_logs', [
            'notes' => $notes,
        ], ['id' => $work_log_id]);

        wp_safe_redirect(self::admin_redirect_url('wp-pq-work-logs', 'work_log_updated', 'Work statement details updated.', self::get_rollup_range_from_request($_POST), [
            'client_id' => isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0,
            'work_log_id' => $work_log_id,
        ]));
        exit;
    }

    public static function handle_create_statement(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_create_statement');
        $task_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['task_ids'] ?? [])))));
        $entry_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['entry_ids'] ?? [])))));
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['notes'])) : '';
        $range = self::get_rollup_range_from_request($_POST);
        $period = $range['month'];
        $result = WP_PQ_API::create_invoice_draft([
            'task_ids' => $task_ids,
            'entry_ids' => $entry_ids,
            'client_id' => isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0,
            'notes' => $notes,
            'statement_month' => $period,
        ], get_current_user_id());

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
            sprintf(
                'Invoice Draft %s created with %d completed work item%s and %d line%s.',
                $result['code'],
                (int) ($result['entry_count'] ?? $result['task_count'] ?? 0),
                ((int) ($result['entry_count'] ?? $result['task_count'] ?? 0) === 1 ? '' : 's'),
                (int) ($result['line_count'] ?? 0),
                ((int) ($result['line_count'] ?? 0) === 1 ? '' : 's')
            ),
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
            wp_safe_redirect(self::admin_redirect_url('wp-pq-statements', 'statement_error', 'Invoice Draft not found.'));
            exit;
        }

        global $wpdb;
        $statements_table = $wpdb->prefix . 'pq_statements';

        $currency_code = strtoupper(sanitize_text_field(wp_unslash((string) ($_POST['currency_code'] ?? 'USD'))));
        $due_date = WP_PQ_API::normalize_rollup_date(sanitize_text_field(wp_unslash((string) ($_POST['due_date'] ?? ''))));
        $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? '')));

        $update = [
            'currency_code' => $currency_code !== '' ? substr($currency_code, 0, 10) : 'USD',
            'due_date' => $due_date !== '' ? $due_date : null,
            'notes' => $notes,
            'updated_at' => current_time('mysql', true),
        ];

        $wpdb->update($statements_table, $update, ['id' => $statement_id]);
        self::sync_invoice_draft_lines_from_request($statement_id, $_POST);
        WP_PQ_API::recalculate_statement_total($statement_id);

        wp_safe_redirect(add_query_arg([
            'page' => 'wp-pq-statements',
            'statement_id' => $statement_id,
            'wp_pq_notice' => 'statement_created',
            'message' => 'Invoice Draft details updated.',
        ], admin_url('admin.php')));
        exit;
    }

    public static function handle_delete_statement(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $statement_id = isset($_POST['statement_id']) ? (int) $_POST['statement_id'] : 0;
        check_admin_referer('wp_pq_delete_statement_' . $statement_id);
        $result = WP_PQ_API::delete_statement_draft($statement_id, get_current_user_id());

        if (is_wp_error($result)) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-statements', 'statement_error', $result->get_error_message()));
            exit;
        }

        wp_safe_redirect(self::admin_redirect_url('wp-pq-statements', 'statement_created', 'Invoice Draft deleted.'));
        exit;
    }

    public static function handle_remove_statement_task(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        $statement_id = isset($_POST['statement_id']) ? (int) $_POST['statement_id'] : 0;
        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        check_admin_referer('wp_pq_remove_statement_task_' . $statement_id . '_' . $task_id);
        $result = WP_PQ_API::remove_task_from_statement_draft($statement_id, $task_id, get_current_user_id());

        if (is_wp_error($result)) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-statements', 'statement_error', $result->get_error_message(), [], [
                'statement_id' => $statement_id,
            ]));
            exit;
        }

        wp_safe_redirect(self::admin_redirect_url('wp-pq-statements', 'statement_created', 'Task removed from invoice draft.', [], [
            'statement_id' => $statement_id,
        ]));
        exit;
    }

    public static function handle_ai_parse(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_ai_parse');
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $bucket_id = isset($_POST['billing_bucket_id']) ? (int) $_POST['billing_bucket_id'] : 0;
        if ($client_id <= 0) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'Choose a client before parsing.'));
            exit;
        }

        $source_text = trim((string) wp_unslash($_POST['source_text'] ?? ''));
        $upload = $_FILES['source_file'] ?? null;
        $file_path = '';
        $file_name = '';
        $mime_type = '';
        if (is_array($upload) && ! empty($upload['tmp_name']) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $file_path = (string) $upload['tmp_name'];
            $file_name = sanitize_file_name((string) ($upload['name'] ?? 'document'));
            $mime_type = sanitize_mime_type((string) ($upload['type'] ?? ''));
        }

        $client_name = WP_PQ_DB::get_client_name($client_id);
        $jobs = array_map(static fn(array $job): string => (string) ($job['bucket_name'] ?? ''), self::get_client_bucket_rows($client_id));
        $parsed = WP_PQ_AI_Importer::parse_document([
            'api_key' => (string) get_option('wp_pq_openai_api_key', ''),
            'model' => (string) get_option('wp_pq_openai_model', 'gpt-4o-mini'),
            'client_name' => $client_name,
            'known_jobs' => $jobs,
            'source_text' => $source_text,
            'file_path' => $file_path,
            'file_name' => $file_name,
            'mime_type' => $mime_type,
        ]);

        if (is_wp_error($parsed)) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', $parsed->get_error_message(), [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
            exit;
        }
        if (empty($parsed['tasks'])) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'OpenAI did not find any importable tasks in that source.', [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
            exit;
        }

        $preview = self::build_ai_import_preview(
            (array) ($parsed['tasks'] ?? []),
            $client_id,
            $bucket_id,
            $file_name !== '' ? $file_name : 'Pasted text',
            (string) ($parsed['summary'] ?? 'Parsed task list')
        );
        self::store_ai_import_preview($preview);

        wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_parsed', sprintf('Parsed %d task%s. Review them before import.', count($preview['tasks']), count($preview['tasks']) === 1 ? '' : 's'), [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
        exit;
    }

    public static function handle_ai_revalidate(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_ai_revalidate');
        $preview = self::get_ai_import_preview();
        if (! $preview || empty($preview['raw_tasks'])) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'Parse a task list before trying to revalidate it.'));
            exit;
        }

        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $bucket_id = isset($_POST['billing_bucket_id']) ? (int) $_POST['billing_bucket_id'] : 0;
        if ($client_id <= 0) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'Choose a client before revalidating the import preview.'));
            exit;
        }

        $rebuilt = self::build_ai_import_preview(
            (array) $preview['raw_tasks'],
            $client_id,
            $bucket_id,
            (string) ($preview['source_name'] ?? 'Pasted text'),
            (string) ($preview['summary'] ?? 'Parsed task list')
        );
        self::store_ai_import_preview($rebuilt);

        wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_parsed', 'Preview context updated.', [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
        exit;
    }

    public static function handle_ai_import(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_ai_import');
        $preview = self::get_ai_import_preview();
        if (! $preview || empty($preview['tasks'])) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'There is no parsed preview ready to import.'));
            exit;
        }

        $posted_client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $posted_bucket_id = isset($_POST['billing_bucket_id']) ? (int) $_POST['billing_bucket_id'] : 0;
        $client_id = (int) ($preview['client_id'] ?? 0);
        $bucket_id = (int) ($preview['billing_bucket_id'] ?? 0);
        if ($posted_client_id !== $client_id || $posted_bucket_id !== $bucket_id) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'The selected client/job context changed. Revalidate the preview before importing again.', [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
            exit;
        }
        if (! empty($preview['blocking_errors'])) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'Fix the blocking import issues before importing.', [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
            exit;
        }
        if (! empty($preview['requires_job_confirmation']) && empty($_POST['confirm_new_jobs'])) {
            wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_error', 'Confirm the new job creation before importing.', [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
            exit;
        }

        $submitter_id = WP_PQ_DB::get_primary_contact_user_id($client_id);
        $imported = 0;
        $errors = [];

        foreach ((array) $preview['tasks'] as $task) {
            $task_bucket_id = self::resolve_preview_bucket_id($task, $client_id, $bucket_id);
            $action_owner_id = (int) ($task['resolved_owner_id'] ?? 0);
            $request = new WP_REST_Request('POST', '/pq/v1/tasks');
            $request->set_param('title', (string) ($task['title'] ?? ''));
            $request->set_param('description', (string) ($task['description'] ?? ''));
            $request->set_param('priority', (string) ($task['priority'] ?? 'normal'));
            $request->set_param('requested_deadline', (string) ($task['normalized_deadline'] ?? ''));
            $request->set_param('needs_meeting', ! empty($task['needs_meeting']));
            $request->set_param('client_id', $client_id);
            $request->set_param('submitter_id', $submitter_id);
            $request->set_param('billing_bucket_id', $task_bucket_id);
            if ($action_owner_id > 0) {
                $request->set_param('owner_ids', [$action_owner_id]);
            }
            if (($task['is_billable'] ?? null) !== null) {
                $request->set_param('is_billable', ! empty($task['is_billable']));
            }

            $response = WP_PQ_API::create_task($request);
            if ($response->get_status() >= 400) {
                $data = (array) $response->get_data();
                $errors[] = (string) ($data['message'] ?? 'Task import failed.');
                continue;
            }

            $task_id = (int) (($response->get_data()['task_id'] ?? 0));
            $status_hint = WP_PQ_Workflow::normalize_status((string) ($task['status_hint'] ?? 'pending_approval'));
            if ($task_id > 0 && in_array($status_hint, ['needs_review', 'delivered', 'done'], true)) {
                global $wpdb;
                $wpdb->update($wpdb->prefix . 'pq_tasks', [
                    'status' => $status_hint,
                    'delivered_at' => $status_hint === 'delivered' ? current_time('mysql', true) : null,
                    'completed_at' => $status_hint === 'done' ? current_time('mysql', true) : null,
                    'done_at' => $status_hint === 'done' ? current_time('mysql', true) : null,
                    'archived_at' => $status_hint === 'done' ? current_time('mysql', true) : null,
                    'updated_at' => current_time('mysql', true),
                ], ['id' => $task_id]);
            }
            $imported++;
        }

        self::clear_ai_import_preview();
        $message = sprintf('Imported %d task%s.', $imported, $imported === 1 ? '' : 's');
        if (! empty($errors)) {
            $message .= ' ' . count($errors) . ' item(s) could not be imported.';
        }
        wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_done', $message, [], ['client_id' => $client_id, 'bucket_id' => $bucket_id]));
        exit;
    }

    public static function handle_ai_clear_preview(): void
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            wp_die('Forbidden');
        }

        check_admin_referer('wp_pq_ai_clear_preview');
        self::clear_ai_import_preview();
        wp_safe_redirect(self::admin_redirect_url('wp-pq-ai-import', 'ai_import_parsed', 'Preview discarded.'));
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
        fputcsv($out, ['Work Statement Code', 'Client', 'Jobs', 'Range Start', 'Range End', 'Task ID', 'Task Title', 'Job', 'Status', 'Updated At', 'Billing Status']);
        foreach ($work_log['tasks'] as $task) {
            fputcsv($out, [
                $work_log['work_log_code'],
                $work_log['client_name'],
                self::bucket_label_from_row($work_log),
                $work_log['range_start'],
                $work_log['range_end'],
                $task['id'],
                $task['title'],
                self::bucket_label_from_row($task),
                self::humanize_label((string) ($task['status'] ?? 'pending_approval')),
                self::format_admin_datetime((string) ($task['updated_at'] ?? $task['created_at'] ?? '')),
                self::billing_status_label((string) ($task['billing_status'] ?? 'unbilled')),
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

        self::render_print_document('Work Statement', [
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
            wp_die('Invoice Draft not found.');
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($statement['statement_code'] . '.csv'));

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Draft Code', 'Client', 'Period', 'Line Type', 'Description', 'Quantity', 'Unit', 'Unit Rate', 'Amount', 'Jobs', 'Linked Task IDs', 'Notes']);
        foreach ((array) ($statement['lines'] ?? []) as $line) {
            $linked_task_ids = self::decode_line_task_ids($line);
            $job_label = (int) ($line['billing_bucket_id'] ?? 0) > 0
                ? self::bucket_label_from_row(['billing_bucket_id' => (int) $line['billing_bucket_id'], 'bucket_name' => self::statement_line_bucket_name((int) ($line['billing_bucket_id'] ?? 0))])
                : self::bucket_label_from_row($statement);
            fputcsv($out, [
                $statement['statement_code'],
                self::client_label_from_row($statement),
                $statement['statement_month'],
                self::humanize_label((string) ($line['line_type'] ?? 'manual_adjustment')),
                (string) ($line['description'] ?? ''),
                (string) ($line['quantity'] ?? ''),
                (string) ($line['unit'] ?? ''),
                (string) ($line['unit_rate'] ?? ''),
                (string) ($line['line_amount'] ?? ''),
                $job_label,
                implode('|', $linked_task_ids),
                (string) ($line['notes'] ?? ''),
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
            wp_die('Invoice Draft not found.');
        }

        self::render_invoice_draft_print_document($statement);
    }

    private static function get_rollup_range(): array
    {
        return self::get_rollup_range_from_request($_GET);
    }

    private static function admin_section_nav(string $current): string
    {
        $items = [
            'portal' => ['label' => 'Open Portal', 'url' => WP_PQ_Portal::portal_url('queue')],
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

    private static function retired_admin_section_map(): array
    {
        return [
            'wp-pq-queue' => 'queue',
            'wp-pq-client-directory' => 'clients',
            'wp-pq-rollups' => 'billing-rollup',
            'wp-pq-work-logs' => 'work-statements',
            'wp-pq-statements' => 'invoice-drafts',
            'wp-pq-ai-import' => 'ai-import',
        ];
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
        if (! empty($extra['client_id'])) {
            $args['client_id'] = (int) $extra['client_id'];
        } elseif (isset($_REQUEST['client_id'])) {
            $args['client_id'] = (int) $_REQUEST['client_id'];
        } elseif (! empty($extra['client_user_id'])) {
            $args['client_id'] = (int) $extra['client_user_id'];
        } elseif (isset($_REQUEST['client_user_id'])) {
            $args['client_id'] = (int) $_REQUEST['client_user_id'];
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    public static function get_billing_clients(array $directory_users = []): array
    {
        global $wpdb;

        static $cache = [];
        $cache_key = get_current_user_id() . ':' . (current_user_can(WP_PQ_Roles::CAP_APPROVE) ? 'all' : implode(',', self::managed_client_ids()));
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $clients_table = $wpdb->prefix . 'pq_clients';
        $managed_client_ids = self::managed_client_ids();
        $sql = "SELECT * FROM {$clients_table}";
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            if (empty($managed_client_ids)) {
                return [];
            }
            $sql .= ' WHERE id IN (' . implode(',', array_map('intval', $managed_client_ids)) . ')';
        }
        $sql .= ' ORDER BY name ASC, id ASC';
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $users_by_id = self::get_directory_user_index($directory_users);
        $clients = [];
        foreach ((array) $rows as $row) {
            $primary_contact = $users_by_id[(int) ($row['primary_contact_user_id'] ?? 0)] ?? null;
            $clients[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'email' => ($primary_contact && is_email($primary_contact->user_email)) ? (string) $primary_contact->user_email : '',
                'label' => (string) ($row['name'] ?? '') . ($primary_contact ? ' <' . $primary_contact->user_email . '>' : ''),
                'primary_contact_user_id' => (int) ($row['primary_contact_user_id'] ?? 0),
            ];
        }

        $cache[$cache_key] = $clients;
        return $cache[$cache_key];
    }

    public static function get_linkable_users(array $directory_users = []): array
    {
        $users = ! empty($directory_users) ? $directory_users : self::get_directory_users();

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

    public static function get_client_bucket_rows(int $client_id): array
    {
        global $wpdb;

        if ($client_id <= 0) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pq_billing_buckets WHERE client_id = %d ORDER BY is_default DESC, bucket_name ASC, id ASC",
            $client_id
        ), ARRAY_A);
    }

    private static function render_rollup_client_jobs_panel(array $clients, array $buckets_by_client, int $selected_client_id, array $range): string
    {
        $selected_jobs = $selected_client_id > 0 ? ($buckets_by_client[$selected_client_id] ?? []) : [];
        $html = '<section class="wp-pq-panel">';
        $html .= '<h2>Clients &amp; Jobs</h2>';
        $html .= '<p class="wp-pq-panel-note">Jobs are always selected or explicitly created. They are never inferred from free text on this screen.</p>';
        $html .= self::render_client_datalist($clients, 'wp-pq-client-options');
        $html .= '<div class="wp-pq-admin-stack">';
        $html .= self::render_rollup_create_client_form($range);
        $html .= self::render_rollup_client_filter_form($clients, $selected_client_id, $range);
        $html .= self::render_rollup_job_controls($clients, $selected_client_id, $selected_jobs, $range);
        $html .= '</div>';
        $html .= self::render_rollup_job_list($selected_client_id, $selected_jobs, $range);
        $html .= '</section>';
        return $html;
    }

    private static function render_rollup_create_client_form(array $range): string
    {
        ob_start();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-bucket-form">';
        wp_nonce_field('wp_pq_create_client');
        echo '<input type="hidden" name="action" value="wp_pq_create_client">';
        echo '<input type="hidden" name="redirect_page" value="wp-pq-rollups">';
        echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
        echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        echo '<h3>Create Client</h3>';
        echo '<label>Client name <input type="text" name="client_name" placeholder="Read Spear" required></label>';
        echo '<label>Client email <input type="email" name="client_email" placeholder="client@example.com" required></label>';
        echo '<label>First job <input type="text" name="initial_bucket_name" placeholder="Client Name - Main" required></label>';
        echo '<button class="button button-primary" type="submit">Create Client</button>';
        echo '</form>';
        return (string) ob_get_clean();
    }

    private static function render_rollup_client_filter_form(array $clients, int $selected_client_id, array $range): string
    {
        $clear_url = add_query_arg([
            'page' => 'wp-pq-rollups',
            'month' => $range['month'],
            'start_date' => $range['custom_start'],
            'end_date' => $range['custom_end'],
        ], admin_url('admin.php'));

        ob_start();
        echo '<form method="get" class="wp-pq-bucket-form wp-pq-client-filter-form">';
        echo '<input type="hidden" name="page" value="wp-pq-rollups">';
        echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
        echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        echo '<h3>Find Client</h3>';
        echo self::render_client_picker('rollup-client-filter', 'client_id', $clients, $selected_client_id, 'Search client', 'Type a client name or email');
        echo '<div class="wp-pq-inline-action-form">';
        echo '<button class="button" type="submit">Show Client</button>';
        if ($selected_client_id > 0) {
            echo '<a class="button" href="' . esc_url($clear_url) . '">Clear</a>';
        }
        echo '</div>';
        echo '</form>';
        return (string) ob_get_clean();
    }

    private static function render_rollup_job_controls(array $clients, int $selected_client_id, array $jobs, array $range): string
    {
        $selected_client = $selected_client_id > 0 ? self::find_client_by_id($clients, $selected_client_id) : null;
        $job_options = self::render_bucket_select('existing_bucket_id', $jobs, 0, 'Existing job', 'Select a job');
        $modal_id = 'wp-pq-create-job-modal';

        ob_start();
        echo '<section class="wp-pq-admin-subpanel">';
        echo '<h3>Manage Jobs</h3>';
        if (! $selected_client) {
            echo '<p class="wp-pq-panel-note">Pick a client first, then select an existing job or create a new one explicitly.</p>';
        } else {
            echo '<p class="wp-pq-panel-note"><strong>Client:</strong> ' . esc_html((string) $selected_client['label']) . '</p>';
            echo '<p class="wp-pq-panel-note">Existing jobs are listed here for selection and confirmation. Delivered-task reassignment stays lower on the page.</p>';
            echo $job_options;
            echo '<div class="wp-pq-inline-action-form">';
            echo '<button class="button button-primary" type="button" data-pq-open-modal="' . esc_attr($modal_id) . '">Create Job</button>';
            echo '</div>';
            echo self::render_create_job_modal($modal_id, $clients, $selected_client_id, $range);
        }
        echo '</section>';
        return (string) ob_get_clean();
    }

    private static function render_create_job_modal(string $modal_id, array $clients, int $selected_client_id, array $range): string
    {
        ob_start();
        echo '<div class="wp-pq-admin-modal-backdrop" id="' . esc_attr($modal_id) . '-backdrop" hidden></div>';
        echo '<section class="wp-pq-admin-modal" id="' . esc_attr($modal_id) . '" hidden>';
        echo '<div class="wp-pq-modal-card wp-pq-modal-card-compact">';
        echo '<div class="wp-pq-section-heading"><div><h3>Create Job</h3><p class="wp-pq-panel-note">Create a new job explicitly for the selected client.</p></div>';
        echo '<button class="button" type="button" data-pq-close-modal="' . esc_attr($modal_id) . '">Close</button></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-ai-form">';
        wp_nonce_field('wp_pq_create_bucket');
        echo '<input type="hidden" name="action" value="wp_pq_create_bucket">';
        echo '<input type="hidden" name="redirect_page" value="wp-pq-rollups">';
        echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
        echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
        echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
        if ($selected_client_id > 0) {
            echo '<input type="hidden" name="client_id" value="' . (int) $selected_client_id . '">';
        } else {
            echo self::render_client_picker('rollup-create-job-client', 'client_id', $clients, 0, 'Client', 'Type a client name or email', true);
        }
        echo '<label>Job name <input type="text" name="bucket_name" placeholder="Website retainer" required></label>';
        echo '<div class="wp-pq-modal-actions">';
        echo '<button class="button" type="button" data-pq-close-modal="' . esc_attr($modal_id) . '">Cancel</button>';
        echo '<button class="button button-primary" type="submit">Create Job</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</section>';
        return (string) ob_get_clean();
    }

    private static function render_rollup_job_list(int $selected_client_id, array $jobs, array $range): string
    {
        if ($selected_client_id <= 0) {
            return '';
        }

        $html = '<div class="wp-pq-bucket-list">';
        if (empty($jobs)) {
            $html .= '<p class="wp-pq-empty-state">No jobs exist for this client yet.</p></div>';
            return $html;
        }

        foreach ($jobs as $job) {
            $bucket_id = (int) ($job['id'] ?? 0);
            $counts = self::get_bucket_dependency_counts($bucket_id);
            $can_delete = self::bucket_can_be_deleted($counts);
            $html .= '<div class="wp-pq-bucket-group">';
            $html .= '<div class="wp-pq-job-row">';
            $html .= '<div><strong>' . esc_html(self::bucket_label_from_row($job)) . '</strong>';
            $html .= '<p class="wp-pq-panel-note">Tasks: ' . (int) $counts['task_count'] . ' · Work Statements: ' . (int) $counts['work_log_count'] . ' · Invoice Drafts: ' . (int) $counts['statement_count'] . '</p></div>';
            if ($can_delete) {
                ob_start();
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-inline-action-form">';
                wp_nonce_field('wp_pq_delete_bucket_' . $bucket_id);
                echo '<input type="hidden" name="action" value="wp_pq_delete_bucket">';
                echo '<input type="hidden" name="redirect_page" value="wp-pq-rollups">';
                echo '<input type="hidden" name="client_id" value="' . (int) $selected_client_id . '">';
                echo '<input type="hidden" name="bucket_id" value="' . $bucket_id . '">';
                echo '<input type="hidden" name="month" value="' . esc_attr($range['month']) . '">';
                echo '<input type="hidden" name="start_date" value="' . esc_attr($range['custom_start']) . '">';
                echo '<input type="hidden" name="end_date" value="' . esc_attr($range['custom_end']) . '">';
                echo '<button class="button" type="submit">Delete Empty Job</button>';
                echo '</form>';
                $html .= (string) ob_get_clean();
            } else {
                $html .= '<span class="wp-pq-detail-pill">In use</span>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function render_ai_parse_panel(array $clients, array $jobs, int $selected_client_id, int $selected_bucket_id, string $openai_key): string
    {
        ob_start();
        echo '<section class="wp-pq-panel">';
        echo '<h2>Parse Source Document</h2>';
        if ($openai_key === '') {
            echo '<div class="wp-pq-admin-callout"><p>Add your OpenAI API key in <a href="' . esc_url(admin_url('admin.php?page=wp-pq-settings')) . '">Settings</a> before using the ingester.</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="wp-pq-ai-form">';
        wp_nonce_field('wp_pq_ai_parse');
        echo '<input type="hidden" name="action" value="wp_pq_ai_parse">';
        echo self::render_client_picker('ai-import-client', 'client_id', $clients, $selected_client_id, 'Client', 'Type a client name or email', true);
        echo self::render_bucket_select('billing_bucket_id', $jobs, $selected_bucket_id, 'Job context', 'Use parsed jobs', true);
        echo '<label>Paste task list text';
        echo '<textarea name="source_text" rows="12" placeholder="Paste notes, bullets, email copy, or a rough master list here."></textarea>';
        echo '</label>';
        echo '<label>Or upload a document';
        echo '<input type="file" name="source_file" accept=".txt,.md,.csv,.json,.pdf">';
        echo '</label>';
        echo '<div class="wp-pq-create-actions">';
        echo '<button class="button button-primary" type="submit"' . ($openai_key === '' ? ' disabled' : '') . '>Parse With OpenAI</button>';
        echo '</div>';
        echo '</form>';
        echo '</section>';
        return (string) ob_get_clean();
    }

    private static function render_ai_rules_panel(?array $client, array $jobs, int $selected_bucket_id): string
    {
        $selected_bucket = $selected_bucket_id > 0 ? self::find_bucket_by_id($jobs, $selected_bucket_id) : null;

        ob_start();
        echo '<section class="wp-pq-panel">';
        echo '<h2>Importer Rules</h2>';
        echo '<div class="wp-pq-admin-callout">';
        echo '<p><strong>Flow:</strong> parse → preview → import.</p>';
        echo '<p><strong>Jobs:</strong> existing jobs are reused by normalized name. New jobs require explicit confirmation before import.</p>';
        echo '<p><strong>Warnings:</strong> unresolved assignees and bad deadlines warn only. Client/context mismatches block import.</p>';
        echo '<p><strong>Requester:</strong> imported tasks are attributed to the client primary contact, not to you as PM.</p>';
        echo '</div>';
        if ($client) {
            echo '<p class="wp-pq-panel-note"><strong>Selected client:</strong> ' . esc_html((string) $client['label']) . '</p>';
        }
        if ($selected_bucket) {
            echo '<p class="wp-pq-panel-note"><strong>Locked job context:</strong> ' . esc_html(self::bucket_label_from_row($selected_bucket)) . '</p>';
        } elseif (! empty($jobs)) {
            echo '<div class="wp-pq-chip-row">';
            foreach ($jobs as $job) {
                echo '<span class="wp-pq-detail-pill">' . esc_html(self::bucket_label_from_row($job)) . '</span>';
            }
            echo '</div>';
        }
        echo '</section>';
        return (string) ob_get_clean();
    }

    private static function render_ai_preview_panel(array $preview, array $clients): string
    {
        $client_id = (int) ($preview['client_id'] ?? 0);
        $jobs = $client_id > 0 ? self::get_client_bucket_rows($client_id) : [];
        $warning_list = (array) ($preview['warning_messages'] ?? []);
        $blocking_list = (array) ($preview['blocking_errors'] ?? []);

        ob_start();
        echo '<section class="wp-pq-panel wp-pq-ai-preview">';
        echo '<h2>Preview Import</h2>';
        echo '<p class="wp-pq-panel-note">' . esc_html((string) ($preview['summary'] ?? 'Parsed task list.')) . '</p>';
        echo '<p class="wp-pq-panel-note">Client: ' . esc_html((string) ($preview['client_label'] ?? 'Client')) . ' · Source: ' . esc_html((string) ($preview['source_name'] ?? 'Pasted text')) . ' · Tasks: ' . (int) count((array) ($preview['tasks'] ?? [])) . '</p>';
        if (! empty($blocking_list)) {
            echo '<div class="notice notice-error inline"><p><strong>Blocking issues:</strong> ' . esc_html(implode(' ', $blocking_list)) . '</p></div>';
        }
        if (! empty($warning_list)) {
            echo '<div class="notice notice-warning inline"><p><strong>Warnings:</strong> ' . esc_html(implode(' ', $warning_list)) . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-pq-ai-context-form" data-pq-auto-submit="1">';
        wp_nonce_field('wp_pq_ai_revalidate');
        echo '<input type="hidden" name="action" value="wp_pq_ai_revalidate">';
        echo self::render_client_picker('ai-preview-client', 'client_id', $clients, $client_id, 'Client context', 'Type a client name or email', true);
        echo self::render_bucket_select('billing_bucket_id', $jobs, (int) ($preview['billing_bucket_id'] ?? 0), 'Job context', 'Use parsed jobs', true);
        echo '<div class="wp-pq-inline-action-form"><button class="button" type="submit">Revalidate Preview</button></div>';
        echo '</form>';

        echo '<table class="widefat striped wp-pq-admin-table">';
        echo '<thead><tr><th>Task</th><th>Job</th><th>Priority</th><th>Owner</th><th>Deadline</th><th>Billable</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        foreach ((array) ($preview['tasks'] ?? []) as $task) {
            $warnings = (array) ($task['warnings'] ?? []);
            echo '<tr>';
            echo '<td><strong>' . esc_html((string) ($task['title'] ?? 'Task')) . '</strong><br><span class="description">' . esc_html(wp_trim_words((string) ($task['description'] ?? ''), 18)) . '</span>';
            if (! empty($warnings)) {
                echo '<br><span class="description">' . esc_html(implode(' · ', $warnings)) . '</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html((string) ($task['job_label'] ?? 'Default job')) . '</td>';
            echo '<td>' . esc_html(ucfirst((string) ($task['priority'] ?? 'normal'))) . '</td>';
            echo '<td>' . esc_html((string) ($task['owner_label'] ?? 'Unassigned')) . '</td>';
            echo '<td>' . esc_html((string) ($task['deadline_label'] ?? 'No deadline')) . '</td>';
            echo '<td>' . esc_html(self::humanize_label((string) self::render_ai_billable_label($task['is_billable'] ?? null))) . '</td>';
            echo '<td>' . esc_html(self::humanize_label((string) ($task['status_hint'] ?? 'pending_approval'))) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        echo '<div class="wp-pq-inline-action-form">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wp_pq_ai_import');
        echo '<input type="hidden" name="action" value="wp_pq_ai_import">';
        echo '<input type="hidden" name="client_id" value="' . $client_id . '">';
        echo '<input type="hidden" name="billing_bucket_id" value="' . (int) ($preview['billing_bucket_id'] ?? 0) . '">';
        if (! empty($preview['requires_job_confirmation'])) {
            echo '<label class="inline"><input type="checkbox" name="confirm_new_jobs" value="1" required> Confirm new job creation for this import</label>';
        }
        echo '<button class="button button-primary" type="submit"' . (! empty($blocking_list) ? ' disabled' : '') . '>Import Parsed Tasks</button>';
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wp_pq_ai_clear_preview');
        echo '<input type="hidden" name="action" value="wp_pq_ai_clear_preview">';
        echo '<button class="button" type="submit">Discard Preview</button>';
        echo '</form>';
        echo '</div>';
        echo '</section>';
        return (string) ob_get_clean();
    }

    private static function render_bucket_select(string $name, array $jobs, int $selected_id, string $label, string $empty_label, bool $allowParsedOption = false): string
    {
        $wrap_label = $label !== '';
        $html = $wrap_label ? '<label>' . esc_html($label) : '';
        $html .= '<select name="' . esc_attr($name) . '">';
        $html .= '<option value="0">' . esc_html($empty_label) . '</option>';
        foreach ($jobs as $job) {
            $job_id = (int) ($job['id'] ?? 0);
            $html .= '<option value="' . $job_id . '"' . selected($job_id, $selected_id, false) . '>' . esc_html(self::bucket_label_from_row($job)) . '</option>';
        }
        if ($allowParsedOption && empty($jobs)) {
            $html .= '<option value="0" selected>' . esc_html($empty_label) . '</option>';
        }
        $html .= '</select>';
        if ($wrap_label) {
            $html .= '</label>';
        }
        return $html;
    }

    public static function get_bucket_dependency_counts(int $bucket_id): array
    {
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $work_log_items_table = $wpdb->prefix . 'pq_work_log_items';
        $statement_lines_table = $wpdb->prefix . 'pq_statement_lines';

        return [
            'task_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tasks_table} WHERE billing_bucket_id = %d", $bucket_id)),
            'work_log_count' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT wi.work_log_id)
                 FROM {$work_log_items_table} wi
                 INNER JOIN {$tasks_table} t ON t.id = wi.task_id
                 WHERE t.billing_bucket_id = %d",
                $bucket_id
            )),
            'statement_count' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT statement_id) FROM {$statement_lines_table} WHERE billing_bucket_id = %d",
                $bucket_id
            )),
        ];
    }

    public static function bucket_can_be_deleted(array $counts): bool
    {
        return (int) ($counts['task_count'] ?? 0) === 0
            && (int) ($counts['work_log_count'] ?? 0) === 0
            && (int) ($counts['statement_count'] ?? 0) === 0;
    }

    public static function build_ai_import_preview(array $raw_tasks, int $client_id, int $bucket_id, string $source_name, string $summary): array
    {
        $client_name = WP_PQ_DB::get_client_name($client_id);
        $jobs = self::get_client_bucket_rows($client_id);
        $selected_bucket = $bucket_id > 0 ? self::find_bucket_by_id($jobs, $bucket_id) : null;
        $blocking_errors = [];
        $warning_messages = [];
        if ($client_id <= 0) {
            $blocking_errors[] = 'Choose a client before importing.';
        }
        if ($bucket_id > 0 && ! $selected_bucket) {
            $blocking_errors[] = 'The chosen job does not belong to the selected client.';
        }

        $tasks = [];
        $new_job_names = [];
        foreach ($raw_tasks as $task) {
            $enriched = self::enrich_ai_preview_task((array) $task, $client_id, $jobs, $selected_bucket);
            $tasks[] = $enriched;
            foreach ((array) ($enriched['warnings'] ?? []) as $warning) {
                $warning_messages[$warning] = $warning;
            }
            if (! empty($enriched['requires_new_job']) && ! empty($enriched['job_name'])) {
                $new_job_names[sanitize_title((string) $enriched['job_name'])] = (string) $enriched['job_name'];
            }
        }

        return [
            'client_id' => $client_id,
            'billing_bucket_id' => $selected_bucket ? (int) ($selected_bucket['id'] ?? 0) : 0,
            'client_label' => $client_name,
            'source_name' => $source_name,
            'summary' => sanitize_text_field($summary),
            'raw_tasks' => array_values($raw_tasks),
            'tasks' => $tasks,
            'blocking_errors' => array_values($blocking_errors),
            'warning_messages' => array_values($warning_messages),
            'new_job_names' => array_values($new_job_names),
            'requires_job_confirmation' => ! empty($new_job_names),
            'created_at' => current_time('mysql', true),
        ];
    }

    private static function enrich_ai_preview_task(array $task, int $client_id, array $jobs, ?array $selected_bucket): array
    {
        $job_name = trim((string) ($task['job_name'] ?? ''));
        $matched_bucket = $selected_bucket ?: self::find_bucket_by_name($jobs, $job_name);
        $resolved_owner_id = self::resolve_import_user_id((string) ($task['action_owner_hint'] ?? ''), $client_id);
        $normalized_deadline = self::normalize_import_deadline((string) ($task['requested_deadline'] ?? ''));
        $warnings = [];

        if ((string) ($task['action_owner_hint'] ?? '') !== '' && $resolved_owner_id <= 0) {
            $warnings[] = 'Unresolved assignee';
        }
        if ((string) ($task['requested_deadline'] ?? '') !== '' && $normalized_deadline === '') {
            $warnings[] = 'Deadline could not be normalized';
        }
        if (! $selected_bucket && $job_name !== '' && ! $matched_bucket) {
            $warnings[] = 'New job will be created';
        }

        $job_label = 'Default job';
        if ($selected_bucket) {
            $job_label = self::bucket_label_from_row($selected_bucket) . ' (selected context)';
        } elseif ($matched_bucket) {
            $job_label = self::bucket_label_from_row($matched_bucket) . ' (existing)';
        } elseif ($job_name !== '') {
            $job_label = $job_name . ' (new)';
        }

        return [
            'title' => (string) ($task['title'] ?? ''),
            'description' => (string) ($task['description'] ?? ''),
            'job_name' => $job_name,
            'job_label' => $job_label,
            'matched_bucket_id' => (int) ($matched_bucket['id'] ?? 0),
            'requires_new_job' => ! $selected_bucket && $job_name !== '' && ! $matched_bucket,
            'priority' => (string) ($task['priority'] ?? 'normal'),
            'requested_deadline' => (string) ($task['requested_deadline'] ?? ''),
            'normalized_deadline' => $normalized_deadline,
            'deadline_label' => $normalized_deadline !== '' ? self::format_admin_datetime($normalized_deadline) : (((string) ($task['requested_deadline'] ?? '')) !== '' ? 'Unparsed deadline' : 'No deadline'),
            'needs_meeting' => ! empty($task['needs_meeting']),
            'action_owner_hint' => (string) ($task['action_owner_hint'] ?? ''),
            'resolved_owner_id' => $resolved_owner_id,
            'owner_label' => $resolved_owner_id > 0 ? self::user_display_name($resolved_owner_id) : (((string) ($task['action_owner_hint'] ?? '')) !== '' ? (string) $task['action_owner_hint'] : 'Unassigned'),
            'is_billable' => array_key_exists('is_billable', $task) ? $task['is_billable'] : null,
            'status_hint' => (string) ($task['status_hint'] ?? 'pending_approval'),
            'warnings' => $warnings,
        ];
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

        $job_name = trim((string) ($task['job_name'] ?? ''));
        if ($job_name !== '') {
            return self::create_bucket_for_client($client_id, $job_name);
        }

        return WP_PQ_DB::get_or_create_default_billing_bucket_id($client_id);
    }

    private static function find_bucket_by_id(array $jobs, int $bucket_id): ?array
    {
        foreach ($jobs as $job) {
            if ((int) ($job['id'] ?? 0) === $bucket_id) {
                return $job;
            }
        }
        return null;
    }

    private static function find_bucket_by_name(array $jobs, string $job_name): ?array
    {
        $slug = sanitize_title($job_name);
        if ($slug === '') {
            return null;
        }

        foreach ($jobs as $job) {
            if (sanitize_title((string) ($job['bucket_name'] ?? '')) === $slug || sanitize_title((string) ($job['bucket_slug'] ?? '')) === $slug) {
                return $job;
            }
        }
        return null;
    }

    private static function user_display_name(int $user_id): string
    {
        $user = get_user_by('ID', $user_id);
        if (! $user) {
            return 'User #' . $user_id;
        }
        return (string) ($user->display_name ?: $user->user_login);
    }

    public static function get_client_directory_rows(array $clients = [], array $directory_users = []): array
    {
        global $wpdb;

        if (empty($clients)) {
            $clients = self::get_billing_clients($directory_users);
        }
        if (empty($clients)) {
            return [];
        }

        $users_by_id = self::get_directory_user_index($directory_users);
        $client_ids = array_map(static fn(array $client): int => (int) $client['id'], $clients);
        $ids_in = implode(',', array_map('intval', $client_ids));
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $logs_table = $wpdb->prefix . 'pq_work_logs';
        $statements_table = $wpdb->prefix . 'pq_statements';
        $buckets_by_client = self::get_buckets_by_client();
        $members_by_client = self::get_client_directory_members_by_client($client_ids, $buckets_by_client, $users_by_id);

        $task_counts = [];
        if ($ids_in !== '') {
            $delivered_rows = $wpdb->get_results(
                "SELECT client_id, COUNT(*) AS delivered_count
                 FROM {$ledger_table}
                 WHERE client_id IN ({$ids_in})
                   AND is_closed = 1
                 GROUP BY client_id",
                ARRAY_A
            );
            foreach ($delivered_rows as $row) {
                $task_counts[(int) $row['client_id']] = [
                    'delivered_count' => (int) $row['delivered_count'],
                    'unbilled_count' => 0,
                ];
            }

            $unbilled_rows = $wpdb->get_results(
                "SELECT client_id, COUNT(*) AS unbilled_count
                 FROM {$ledger_table}
                 WHERE client_id IN ({$ids_in})
                   AND is_closed = 1
                   AND billable = 1
                   AND invoice_status = 'unbilled'
                 GROUP BY client_id",
                ARRAY_A
            );
            foreach ($unbilled_rows as $row) {
                $client_id = (int) ($row['client_id'] ?? 0);
                if (! isset($task_counts[$client_id])) {
                    $task_counts[$client_id] = [
                        'delivered_count' => 0,
                        'unbilled_count' => 0,
                    ];
                }
                $task_counts[$client_id]['unbilled_count'] = (int) ($row['unbilled_count'] ?? 0);
            }
        }

        if ($ids_in !== '') {
            foreach ($client_ids as $client_id) {
                if (! isset($task_counts[$client_id])) {
                    $task_counts[$client_id] = [
                        'delivered_count' => 0,
                        'unbilled_count' => 0,
                    ];
                }
            }
        }

        $work_log_rows = [];
        if ($ids_in !== '') {
            $work_log_rows = $wpdb->get_results(
                "SELECT client_id, client_user_id, work_log_code, billing_bucket_id, range_start, range_end, created_at,
                        (SELECT COUNT(*) FROM {$wpdb->prefix}pq_work_log_items wli WHERE wli.work_log_id = l.id) AS task_count
                 FROM {$logs_table} l
                 WHERE client_id IN ({$ids_in})
                 ORDER BY created_at DESC, id DESC",
                ARRAY_A
            );
        }
        $work_logs_by_client = [];
        foreach ($work_log_rows as $row) {
            $work_logs_by_client[(int) ($row['client_id'] ?? 0)][] = $row;
        }

        $statement_rows = [];
        if ($ids_in !== '') {
            $statement_rows = $wpdb->get_results(
                "SELECT client_id, client_user_id, statement_code, billing_bucket_id, range_start, range_end, created_at,
                        (SELECT COUNT(*) FROM {$wpdb->prefix}pq_statement_items psi WHERE psi.statement_id = s.id) AS task_count,
                        (SELECT COUNT(*) FROM {$ledger_table} l WHERE l.invoice_draft_id = s.id) AS entry_count
                 FROM {$statements_table} s
                 WHERE client_id IN ({$ids_in})
                 ORDER BY created_at DESC, id DESC",
                ARRAY_A
            );
        }
        $statements_by_client = [];
        foreach ($statement_rows as $row) {
            $statements_by_client[(int) ($row['client_id'] ?? 0)][] = $row;
        }

        $rows = [];
        foreach ($clients as $client) {
            $client_id = (int) $client['id'];
            $client_work_logs = $work_logs_by_client[$client_id] ?? [];
            $client_statements = $statements_by_client[$client_id] ?? [];
            $rows[] = [
                'id' => $client_id,
                'name' => (string) ($client['name'] ?? ''),
                'email' => (string) ($client['email'] ?? ''),
                'label' => (string) $client['label'],
                'delivered_count' => (int) ($task_counts[$client_id]['delivered_count'] ?? 0),
                'unbilled_count' => (int) ($task_counts[$client_id]['unbilled_count'] ?? 0),
                'work_log_count' => count($client_work_logs),
                'statement_count' => count($client_statements),
                'buckets' => $buckets_by_client[$client_id] ?? [],
                'members' => $members_by_client[$client_id] ?? [],
                'recent_work_logs' => array_slice($client_work_logs, 0, 3),
                'recent_statements' => array_slice($client_statements, 0, 3),
            ];
        }

        return $rows;
    }

    private static function get_buckets_by_client(): array
    {
        global $wpdb;

        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $users_table = $wpdb->users;
        $rows = $wpdb->get_results(
            "SELECT b.*, u.display_name AS client_name, u.user_email AS client_email
             FROM {$buckets_table} b
             LEFT JOIN {$wpdb->prefix}pq_clients c ON c.id = b.client_id
             LEFT JOIN {$users_table} u ON u.ID = b.client_user_id
             ORDER BY c.name ASC, b.is_default DESC, b.bucket_name ASC",
            ARRAY_A
        );

        $by_client = [];
        foreach ($rows as $row) {
            $by_client[(int) ($row['client_id'] ?? 0)][] = $row;
        }

        $cache = $by_client;
        return $cache;
    }

    public static function get_rollup_groups(string $start_date, string $end_date, array $buckets_by_client): array
    {
        global $wpdb;

        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT
                l.id AS ledger_entry_id,
                l.task_id,
                l.client_id,
                l.billing_bucket_id,
                l.title_snapshot,
                l.work_summary,
                l.billable,
                l.billing_mode,
                l.billing_category,
                l.invoice_status,
                l.invoice_draft_id,
                l.completion_date,
                l.hours,
                l.rate,
                l.amount,
                t.work_log_id,
                t.billing_status,
                b.bucket_name,
                b.is_default,
                owner.display_name AS owner_name,
                client_user.display_name AS client_name,
                client_user.user_email AS client_email
             FROM {$ledger_table} l
             LEFT JOIN {$tasks_table} t ON t.id = l.task_id
             LEFT JOIN {$wpdb->prefix}pq_clients c ON c.id = l.client_id
             LEFT JOIN {$users_table} owner ON owner.ID = l.owner_id
             LEFT JOIN {$users_table} client_user ON client_user.ID = COALESCE(t.client_user_id, c.primary_contact_user_id)
             LEFT JOIN {$buckets_table} b ON b.id = l.billing_bucket_id
             WHERE DATE(l.completion_date) BETWEEN %s AND %s
               AND l.is_closed = 1
             ORDER BY client_user.display_name ASC, b.bucket_name ASC, l.completion_date DESC, l.id DESC",
            $start_date,
            $end_date
        ), ARRAY_A);

        $groups = [];
        foreach ($rows as $row) {
            $client_id = (int) ($row['client_id'] ?? 0);
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
                    'entries' => [],
                    'invoice_ready_count' => 0,
                ];
            }

            $groups[$group_key]['entries'][] = $row;
            if ((string) ($row['invoice_status'] ?? 'unbilled') === 'unbilled' && (int) ($row['billable'] ?? 1) === 1) {
                $groups[$group_key]['invoice_ready_count']++;
            }
        }

        return array_values($groups);
    }

    public static function get_work_log_summaries(string $start_date, string $end_date): array
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'pq_work_logs';
        $items_table = $wpdb->prefix . 'pq_work_log_items';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $rows = $wpdb->get_results($wpdb->prepare(
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

        foreach ($rows as &$row) {
            $row['job_summary'] = self::work_log_job_summary($row);
        }

        return $rows;
    }

    public static function get_work_log_detail(int $work_log_id): ?array
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

        $work_log['job_summary'] = self::work_log_job_summary($work_log);
        $work_log['tasks'] = $wpdb->get_results($wpdb->prepare(
            "SELECT
                wi.task_id AS id,
                wi.task_title AS title,
                wi.task_description AS description,
                wi.task_status AS status,
                wi.task_billing_status AS billing_status,
                wi.task_bucket_name AS bucket_name,
                wi.task_bucket_is_default AS is_default,
                wi.task_updated_at AS updated_at,
                t.created_at,
                u.display_name AS submitter_name
             FROM {$items_table} wi
             LEFT JOIN {$tasks_table} t ON t.id = wi.task_id
             LEFT JOIN {$users_table} u ON u.ID = t.submitter_id
             WHERE wi.work_log_id = %d
             ORDER BY wi.task_status ASC, COALESCE(wi.task_updated_at, t.created_at) DESC, wi.id DESC",
            $work_log_id
        ), ARRAY_A);

        return $work_log;
    }

    public static function get_statement_summaries_for_range(string $start_date, string $end_date): array
    {
        global $wpdb;

        $statements_table = $wpdb->prefix . 'pq_statements';
        $items_table = $wpdb->prefix . 'pq_statement_items';
        $lines_table = $wpdb->prefix . 'pq_statement_lines';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, COUNT(DISTINCT si.id) AS task_count, COUNT(DISTINCT sl.id) AS line_count,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}pq_work_ledger_entries le WHERE le.invoice_draft_id = s.id) AS entry_count,
                    u.display_name AS client_name, u.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$statements_table} s
             LEFT JOIN {$items_table} si ON si.statement_id = s.id
             LEFT JOIN {$lines_table} sl ON sl.statement_id = s.id
             LEFT JOIN {$users_table} u ON u.ID = s.client_user_id
             LEFT JOIN {$buckets_table} b ON b.id = s.billing_bucket_id
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY s.id
             ORDER BY s.created_at DESC, s.id DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['job_summary'] = self::statement_job_summary((int) ($row['id'] ?? 0), $row);
        }

        return $rows;
    }

    public static function get_unbilled_ledger_entries(string $period): array
    {
        global $wpdb;

        $ledger_table = $wpdb->prefix . 'pq_work_ledger_entries';
        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $clients_table = $wpdb->prefix . 'pq_clients';
        $users_table = $wpdb->users;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT
                l.id,
                l.task_id,
                l.client_id,
                COALESCE(t.client_user_id, c.primary_contact_user_id) AS client_user_id,
                l.billing_bucket_id,
                l.title_snapshot AS title,
                l.work_summary AS description,
                l.completion_date,
                l.billing_mode,
                l.billing_category,
                l.hours,
                l.rate,
                l.amount,
                owner.display_name AS owner_name,
                b.bucket_name,
                b.is_default
             FROM {$ledger_table} l
             LEFT JOIN {$tasks_table} t ON t.id = l.task_id
             LEFT JOIN {$clients_table} c ON c.id = l.client_id
             LEFT JOIN {$users_table} owner ON owner.ID = l.owner_id
             LEFT JOIN {$wpdb->prefix}pq_billing_buckets b ON b.id = l.billing_bucket_id
             WHERE l.billable = 1
               AND l.is_closed = 1
               AND l.invoice_status = 'unbilled'
               AND l.statement_month = %s
             ORDER BY l.completion_date DESC, l.id DESC",
            $period
        ), ARRAY_A);

        return $rows;
    }

    public static function get_statement_summaries(string $period): array
    {
        global $wpdb;

        $statements_table = $wpdb->prefix . 'pq_statements';
        $items_table = $wpdb->prefix . 'pq_statement_items';
        $lines_table = $wpdb->prefix . 'pq_statement_lines';
        $users_table = $wpdb->users;
        $buckets_table = $wpdb->prefix . 'pq_billing_buckets';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, COUNT(DISTINCT si.id) AS task_count, COUNT(DISTINCT sl.id) AS line_count,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}pq_work_ledger_entries le WHERE le.invoice_draft_id = s.id) AS entry_count,
                    u.display_name AS creator_name, client.display_name AS client_name, client.user_email AS client_email, b.bucket_name, b.is_default
             FROM {$statements_table} s
             LEFT JOIN {$items_table} si ON si.statement_id = s.id
             LEFT JOIN {$lines_table} sl ON sl.statement_id = s.id
             LEFT JOIN {$users_table} u ON u.ID = s.created_by
             LEFT JOIN {$users_table} client ON client.ID = s.client_user_id
             LEFT JOIN {$buckets_table} b ON b.id = s.billing_bucket_id
             WHERE s.statement_month = %s
             GROUP BY s.id
             ORDER BY s.created_at DESC, s.id DESC",
            $period
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['job_summary'] = self::statement_job_summary((int) ($row['id'] ?? 0), $row);
        }

        return $rows;
    }

    public static function get_statement_detail(int $statement_id): ?array
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

        $statement['job_summary'] = self::statement_job_summary($statement_id, $statement);
        $statement['lines'] = WP_PQ_API::get_statement_line_rows($statement_id);
        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.display_name AS submitter_name, b.bucket_name, b.is_default
             FROM {$items_table} si
             INNER JOIN {$tasks_table} t ON t.id = si.task_id
             LEFT JOIN {$users_table} u ON u.ID = t.submitter_id
             LEFT JOIN {$buckets_table} b ON b.id = t.billing_bucket_id
             WHERE si.statement_id = %d
             ORDER BY COALESCE(t.delivered_at, t.updated_at) DESC, t.id DESC",
            $statement_id
        ), ARRAY_A);

        $statement['tasks'] = $tasks;
        $statement['line_count'] = count((array) $statement['lines']);
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

    private static function render_ai_billable_label($value): string
    {
        if ($value === null || $value === '') {
            return 'auto';
        }

        return ! empty($value) ? 'billable' : 'not billable';
    }

    private static function ai_import_preview_key(): string
    {
        return 'wp_pq_ai_import_preview_' . get_current_user_id();
    }

    public static function store_ai_import_preview(array $preview): void
    {
        set_transient(self::ai_import_preview_key(), $preview, HOUR_IN_SECONDS);
    }

    public static function get_ai_import_preview(): ?array
    {
        $preview = get_transient(self::ai_import_preview_key());
        return is_array($preview) ? $preview : null;
    }

    public static function clear_ai_import_preview(): void
    {
        delete_transient(self::ai_import_preview_key());
    }

    private static function resolve_import_user_id(string $hint, int $client_id): int
    {
        $hint = trim(strtolower($hint));
        if ($hint === '') {
            return 0;
        }

        $client_user_ids = [];
        foreach (WP_PQ_DB::get_client_memberships($client_id) as $membership) {
            $client_user_ids[] = (int) ($membership['user_id'] ?? 0);
        }
        $internal_users = get_users([
            'role__in' => ['administrator', 'pq_manager', 'pq_worker'],
            'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
        ]);

        $candidates = [];
        foreach ($internal_users as $user) {
            $candidates[(int) $user->ID] = $user;
        }
        foreach ($client_user_ids as $user_id) {
            $user = get_user_by('ID', $user_id);
            if ($user) {
                $candidates[(int) $user->ID] = $user;
            }
        }

        foreach ($candidates as $candidate) {
            $haystacks = [
                strtolower((string) $candidate->display_name),
                strtolower((string) $candidate->user_login),
                strtolower((string) $candidate->user_email),
            ];
            foreach ($haystacks as $value) {
                if ($value !== '' && (str_contains($value, $hint) || str_contains($hint, $value))) {
                    return (int) $candidate->ID;
                }
            }
        }

        return 0;
    }

    private static function normalize_import_deadline(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if (! $timestamp) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function render_user_picker(string $base_id, string $hidden_name, array $users, int $selected_id, string $label, string $placeholder, bool $required = false, string $list_id = 'wp-pq-user-options'): string
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
            . '<input type="text" class="wp-pq-client-picker" id="' . esc_attr($base_id) . '-search" data-hidden-target="' . esc_attr($base_id) . '-value" list="' . esc_attr($list_id) . '" value="' . esc_attr((string) ($selected['label'] ?? '')) . '" placeholder="' . esc_attr($placeholder) . '"' . $required_attr . '>'
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
    input.addEventListener('change', function () {
      var form = input.closest('form');
      if (form && form.dataset.pqAutoSubmit === '1' && hidden.value) {
        form.submit();
      }
    });
  });
  document.querySelectorAll('select').forEach(function (select) {
    select.addEventListener('change', function () {
      var form = select.closest('form');
      if (form && form.dataset.pqAutoSubmit === '1') {
        form.submit();
      }
    });
  });
  document.querySelectorAll('[data-pq-open-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      var modalId = button.dataset.pqOpenModal;
      var modal = modalId ? document.getElementById(modalId) : null;
      var backdrop = modalId ? document.getElementById(modalId + '-backdrop') : null;
      if (modal) modal.hidden = false;
      if (backdrop) backdrop.hidden = false;
    });
  });
  document.querySelectorAll('[data-pq-close-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      var modalId = button.dataset.pqCloseModal;
      var modal = modalId ? document.getElementById(modalId) : null;
      var backdrop = modalId ? document.getElementById(modalId + '-backdrop') : null;
      if (modal) modal.hidden = true;
      if (backdrop) backdrop.hidden = true;
    });
  });
});
</script>";
    }

    public static function create_bucket_for_client(int $client_id, string $bucket_name): int
    {
        global $wpdb;

        $bucket_name = trim($bucket_name);
        if ($client_id <= 0 || $bucket_name === '') {
            return 0;
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

        $has_default = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$buckets_table} WHERE client_id = %d AND is_default = 1 LIMIT 1",
            $client_id
        ));
        $client_user_id = WP_PQ_DB::get_primary_contact_user_id($client_id);

        $wpdb->insert($buckets_table, [
            'client_id' => $client_id,
            'client_user_id' => $client_user_id,
            'bucket_name' => $bucket_name,
            'bucket_slug' => $slug,
            'description' => '',
            'is_default' => $has_default > 0 ? 0 : 1,
            'created_by' => get_current_user_id() ?: 1,
            'created_at' => current_time('mysql', true),
        ]);

        $bucket_id = (int) $wpdb->insert_id;
        if ($bucket_id > 0) {
            foreach (WP_PQ_DB::get_client_admin_user_ids($client_id) as $admin_user_id) {
                WP_PQ_DB::ensure_job_member($bucket_id, $admin_user_id);
            }
        }

        return $bucket_id;
    }

    public static function can_manage_any_clients(): bool
    {
        return current_user_can(WP_PQ_Roles::CAP_APPROVE) || ! empty(self::managed_client_ids());
    }

    private static function managed_client_ids(int $user_id = 0): array
    {
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if (user_can($user_id, WP_PQ_Roles::CAP_APPROVE)) {
            return [];
        }

        $client_ids = [];
        foreach (WP_PQ_DB::get_user_client_memberships($user_id) as $membership) {
            if ((string) ($membership['role'] ?? '') !== 'client_admin') {
                continue;
            }
            $client_ids[] = (int) ($membership['client_id'] ?? 0);
        }

        return array_values(array_unique(array_filter($client_ids)));
    }

    public static function can_manage_client(int $client_id, int $user_id = 0): bool
    {
        if ($client_id <= 0) {
            return false;
        }

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if (user_can($user_id, WP_PQ_Roles::CAP_APPROVE)) {
            return true;
        }

        return in_array($client_id, self::managed_client_ids($user_id), true);
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

    public static function get_member_candidate_users(array $directory_users = []): array
    {
        $users = ! empty($directory_users) ? $directory_users : self::get_directory_users();

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'id' => (int) $user->ID,
                'label' => self::user_label($user),
            ];
        }

        return $rows;
    }

    public static function get_directory_users(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        return $cache;
    }

    private static function get_directory_user_index(array $directory_users = []): array
    {
        $users = ! empty($directory_users) ? $directory_users : self::get_directory_users();
        $index = [];
        foreach ($users as $user) {
            $index[(int) $user->ID] = $user;
        }

        return $index;
    }

    private static function get_client_directory_members_by_client(array $client_ids, array $buckets_by_client, array $users_by_id): array
    {
        global $wpdb;

        if (empty($client_ids)) {
            return [];
        }

        $client_id_list = implode(',', array_map('intval', array_values(array_unique(array_filter($client_ids)))));
        if ($client_id_list === '') {
            return [];
        }

        $memberships = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}pq_client_members WHERE client_id IN ({$client_id_list}) ORDER BY client_id ASC, id ASC",
            ARRAY_A
        );

        $memberships_by_client = [];
        foreach ((array) $memberships as $membership) {
            $memberships_by_client[(int) ($membership['client_id'] ?? 0)][] = $membership;
        }

        $bucket_ids = [];
        $bucket_labels_by_client = [];
        foreach ($buckets_by_client as $client_id => $buckets) {
            foreach ((array) $buckets as $bucket) {
                $bucket_id = (int) ($bucket['id'] ?? 0);
                if ($bucket_id <= 0) {
                    continue;
                }
                $bucket_ids[] = $bucket_id;
                $bucket_labels_by_client[(int) $client_id][$bucket_id] = self::bucket_label_from_row($bucket);
            }
        }

        $job_members_by_bucket = self::get_job_members_by_bucket_ids($bucket_ids);
        $job_names_by_client_user = [];
        foreach ($bucket_labels_by_client as $client_id => $labels) {
            foreach ($labels as $bucket_id => $label) {
                foreach ($job_members_by_bucket[$bucket_id] ?? [] as $user_id) {
                    $job_names_by_client_user[(int) $client_id][(int) $user_id][] = $label;
                }
            }
        }

        $rows = [];
        foreach ($memberships_by_client as $client_id => $client_memberships) {
            foreach ($client_memberships as $membership) {
                $user_id = (int) ($membership['user_id'] ?? 0);
                $user = $users_by_id[$user_id] ?? null;
                if (! $user) {
                    continue;
                }

                $job_names = array_values(array_unique($job_names_by_client_user[(int) $client_id][$user_id] ?? []));
                $rows[(int) $client_id][] = [
                    'id' => $user_id,
                    'name' => (string) $user->display_name,
                    'email' => (string) $user->user_email,
                    'role' => (string) ($membership['role'] ?? 'client_contributor'),
                    'job_names' => $job_names,
                ];
            }

            usort($rows[(int) $client_id], static fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
        }

        return $rows;
    }

    private static function get_job_members_by_bucket_ids(array $bucket_ids): array
    {
        global $wpdb;

        $bucket_ids = array_values(array_unique(array_filter(array_map('intval', $bucket_ids))));
        if (empty($bucket_ids)) {
            return [];
        }

        $rows = $wpdb->get_results(
            "SELECT billing_bucket_id, user_id FROM {$wpdb->prefix}pq_job_members WHERE billing_bucket_id IN (" . implode(',', $bucket_ids) . ')',
            ARRAY_A
        );

        $members_by_bucket = [];
        foreach ((array) $rows as $row) {
            $bucket_id = (int) ($row['billing_bucket_id'] ?? 0);
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($bucket_id <= 0 || $user_id <= 0) {
                continue;
            }
            $members_by_bucket[$bucket_id][] = $user_id;
        }

        return $members_by_bucket;
    }

    private static function get_client_member_rows(int $client_id): array
    {
        $rows = [];
        foreach (WP_PQ_DB::get_client_memberships($client_id) as $membership) {
            $user_id = (int) ($membership['user_id'] ?? 0);
            $user = $user_id > 0 ? get_user_by('ID', $user_id) : null;
            if (! $user) {
                continue;
            }

            $rows[] = [
                'id' => $user_id,
                'name' => (string) $user->display_name,
                'email' => (string) $user->user_email,
                'role' => (string) ($membership['role'] ?? 'client_contributor'),
                'job_names' => self::job_names_for_member($client_id, $user_id),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
        return $rows;
    }

    private static function get_job_member_rows(int $bucket_id, array $members): array
    {
        $member_ids = WP_PQ_DB::get_job_member_ids($bucket_id);
        return array_values(array_filter($members, static function (array $member) use ($member_ids): bool {
            return in_array((int) ($member['id'] ?? 0), $member_ids, true);
        }));
    }

    private static function get_assignable_job_member_rows(int $bucket_id, array $members): array
    {
        $member_ids = WP_PQ_DB::get_job_member_ids($bucket_id);
        return array_values(array_filter($members, static function (array $member) use ($member_ids): bool {
            return ! in_array((int) ($member['id'] ?? 0), $member_ids, true);
        }));
    }

    private static function job_names_for_member(int $client_id, int $user_id): array
    {
        global $wpdb;

        $job_names = [];
        foreach (WP_PQ_DB::get_job_member_ids_for_user($user_id) as $bucket_id) {
            $bucket = $wpdb->get_row($wpdb->prepare(
                "SELECT client_id, bucket_name, is_default FROM {$wpdb->prefix}pq_billing_buckets WHERE id = %d",
                $bucket_id
            ), ARRAY_A);
            if (! $bucket || (int) ($bucket['client_id'] ?? 0) !== $client_id) {
                continue;
            }
            $job_names[] = self::bucket_label_from_row($bucket);
        }

        return array_values(array_unique($job_names));
    }

    private static function invoice_line_type_labels(): array
    {
        return [
            'task_rollup' => 'Work Rollup',
            'fixed_fee' => 'Fixed Fee',
            'retainer' => 'Retainer',
            'hourly_overage' => 'Hourly Overage',
            'pass_through_expense' => 'Pass-through Expense',
            'subscription_service' => 'Subscription / Service',
            'manual_adjustment' => 'Manual Adjustment',
        ];
    }

    private static function work_statement_status_labels(): array
    {
        return [
            'pending_approval' => 'Pending Approval',
            'needs_clarification' => 'Needs Clarification',
            'approved' => 'Approved',
            'in_progress' => 'In Progress',
            'needs_review' => 'Needs Review',
            'delivered' => 'Delivered',
            'done' => 'Done',
        ];
    }

    private static function statement_job_summary(int $statement_id, array $row = []): string
    {
        global $wpdb;

        if (! empty($row['job_summary'])) {
            return (string) $row['job_summary'];
        }

        if ($statement_id <= 0) {
            return 'All Jobs';
        }

        $bucket_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT billing_bucket_id FROM {$wpdb->prefix}pq_statement_lines WHERE statement_id = %d AND billing_bucket_id IS NOT NULL AND billing_bucket_id > 0 ORDER BY billing_bucket_id ASC",
            $statement_id
        )));

        if (count($bucket_ids) > 1) {
            return 'Multiple Jobs';
        }

        if (count($bucket_ids) === 1) {
            $bucket = $wpdb->get_row($wpdb->prepare(
                "SELECT bucket_name, is_default FROM {$wpdb->prefix}pq_billing_buckets WHERE id = %d",
                $bucket_ids[0]
            ), ARRAY_A);
            if ($bucket) {
                return self::bucket_label_from_row($bucket);
            }
        }

        if ((int) ($row['billing_bucket_id'] ?? 0) > 0) {
            return self::bucket_label_from_row($row);
        }

        return 'All Jobs';
    }

    private static function work_log_job_summary(array $row): string
    {
        $filters = json_decode((string) ($row['snapshot_filters'] ?? ''), true);
        $job_ids = is_array($filters) ? array_values(array_unique(array_filter(array_map('intval', (array) ($filters['job_ids'] ?? []))))) : [];

        if (count($job_ids) > 1) {
            return 'Multiple Jobs';
        }

        if ((int) ($row['billing_bucket_id'] ?? 0) > 0) {
            return self::bucket_label_from_row($row);
        }

        if (count($job_ids) === 1) {
            return self::bucket_label_from_row($row);
        }

        return 'All Jobs';
    }

    private static function decode_line_task_ids(array $line): array
    {
        $task_ids = json_decode((string) ($line['linked_task_ids'] ?? ''), true);
        if (! is_array($task_ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $task_ids))));
    }

    private static function line_source_mismatch_message(array $line): string
    {
        if ((string) ($line['source_kind'] ?? '') !== 'task') {
            return '';
        }

        $snapshot = json_decode((string) ($line['source_snapshot'] ?? ''), true);
        if (! is_array($snapshot)) {
            return '';
        }

        $suggested_description = trim((string) ($snapshot['suggested_description'] ?? ''));
        $suggested_quantity = isset($snapshot['suggested_quantity']) ? (float) $snapshot['suggested_quantity'] : null;
        $suggested_unit = trim((string) ($snapshot['suggested_unit'] ?? ''));
        $snapshot_task_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($snapshot['task_ids'] ?? [])))));
        $current_task_ids = self::decode_line_task_ids($line);
        $current_description = trim((string) ($line['description'] ?? ''));
        $current_quantity = isset($line['quantity']) && $line['quantity'] !== null ? (float) $line['quantity'] : null;
        $current_unit = trim((string) ($line['unit'] ?? ''));
        $has_pricing_override = trim((string) ($line['unit_rate'] ?? '')) !== '' || trim((string) ($line['line_amount'] ?? '')) !== '';

        $description_changed = $suggested_description !== '' && $current_description !== $suggested_description;
        $quantity_changed = $suggested_quantity !== null && $current_quantity !== null && abs($current_quantity - $suggested_quantity) > 0.009;
        $unit_changed = $suggested_unit !== '' && $current_unit !== $suggested_unit;
        $linked_tasks_changed = ! empty($snapshot_task_ids) && $snapshot_task_ids !== $current_task_ids;

        if (! $description_changed && ! $quantity_changed && ! $unit_changed && ! $has_pricing_override && ! $linked_tasks_changed) {
            return '';
        }

        return 'This line no longer matches the original work-derived suggestion.';
    }

    private static function ledger_invoice_status_label(string $status): string
    {
        switch (sanitize_key($status)) {
            case 'invoiced':
                return 'In invoice draft';
            case 'paid':
                return 'Paid';
            case 'written_off':
                return 'No Charge';
            case 'unbilled':
                return 'Eligible';
            default:
                return self::humanize_label($status !== '' ? $status : 'unbilled');
        }
    }

    private static function rollup_billing_label(array $entry): string
    {
        if ((int) ($entry['billable'] ?? 1) !== 1) {
            return 'Non-billable';
        }

        $mode = sanitize_key((string) ($entry['billing_mode'] ?? 'fixed_fee'));
        return 'Billable · ' . self::humanize_label($mode !== '' ? $mode : 'fixed_fee');
    }

    private static function statement_line_bucket_name(int $bucket_id): string
    {
        global $wpdb;

        if ($bucket_id <= 0) {
            return '';
        }

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT bucket_name FROM {$wpdb->prefix}pq_billing_buckets WHERE id = %d",
            $bucket_id
        ));
    }

    private static function billing_status_label(string $status): string
    {
        $status = trim($status);
        if ($status === 'batched') {
            return 'In invoice draft';
        }

        if ($status === 'statement_sent') {
            return 'Sent to accounting';
        }

        return self::humanize_label($status !== '' ? $status : 'unbilled');
    }

    private static function sync_invoice_draft_lines_from_request(int $statement_id, array $source): void
    {
        global $wpdb;

        $lines_table = $wpdb->prefix . 'pq_statement_lines';
        $existing_lines = [];
        foreach (WP_PQ_API::get_statement_line_rows($statement_id) as $line) {
            $existing_lines[(int) ($line['id'] ?? 0)] = $line;
        }

        $remove_ids = array_fill_keys(array_map('intval', (array) ($source['remove_line_ids'] ?? [])), true);
        $line_ids = array_values(array_map('intval', (array) ($source['line_id'] ?? [])));
        $count = count($line_ids);

        for ($index = 0; $index < $count; $index++) {
            $line_id = (int) ($line_ids[$index] ?? 0);
            if ($line_id <= 0 || ! isset($existing_lines[$line_id])) {
                continue;
            }

            if (isset($remove_ids[$line_id])) {
                WP_PQ_API::delete_statement_line($statement_id, $line_id, get_current_user_id());
                continue;
            }

            $payload = self::invoice_line_payload_from_request($source, $index, 'line_');
            if (! self::invoice_line_payload_has_content($payload)) {
                continue;
            }

            $wpdb->update($lines_table, [
                'line_type' => $payload['line_type'],
                'description' => $payload['description'],
                'quantity' => $payload['quantity'],
                'unit' => $payload['unit'],
                'unit_rate' => $payload['unit_rate'],
                'line_amount' => $payload['line_amount'],
                'billing_bucket_id' => $payload['billing_bucket_id'],
                'notes' => $payload['notes'],
                'sort_order' => $index + 1,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $line_id, 'statement_id' => $statement_id]);
        }

        $new_count = max(
            count((array) ($source['new_line_type'] ?? [])),
            count((array) ($source['new_line_description'] ?? [])),
            count((array) ($source['new_line_amount'] ?? []))
        );

        for ($index = 0; $index < $new_count; $index++) {
            $payload = self::invoice_line_payload_from_request($source, $index, 'new_line_');
            if (! self::invoice_line_payload_has_content($payload)) {
                continue;
            }

            $wpdb->insert($lines_table, [
                'statement_id' => $statement_id,
                'line_type' => $payload['line_type'],
                'source_kind' => 'manual',
                'description' => $payload['description'],
                'quantity' => $payload['quantity'],
                'unit' => $payload['unit'],
                'unit_rate' => $payload['unit_rate'],
                'line_amount' => $payload['line_amount'],
                'billing_bucket_id' => $payload['billing_bucket_id'],
                'linked_task_ids' => null,
                'source_snapshot' => null,
                'notes' => $payload['notes'],
                'sort_order' => count($existing_lines) + $index + 1,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ]);
        }
    }

    private static function invoice_line_payload_from_request(array $source, int $index, string $prefix): array
    {
        $type_values = (array) ($source[$prefix . 'type'] ?? []);
        $description_values = (array) ($source[$prefix . 'description'] ?? []);
        $quantity_values = (array) ($source[$prefix . 'quantity'] ?? []);
        $unit_values = (array) ($source[$prefix . 'unit'] ?? []);
        $unit_rate_values = (array) ($source[$prefix . 'unit_rate'] ?? []);
        $amount_values = (array) ($source[$prefix . 'amount'] ?? []);
        $note_values = (array) ($source[$prefix . 'notes'] ?? []);
        $bucket_values = (array) ($source[$prefix . 'bucket_id'] ?? []);

        $line_type = sanitize_key((string) ($type_values[$index] ?? 'manual_adjustment'));
        $allowed_line_types = array_keys(self::invoice_line_type_labels());
        if (! in_array($line_type, $allowed_line_types, true)) {
            $line_type = 'manual_adjustment';
        }

        $description = sanitize_textarea_field((string) ($description_values[$index] ?? ''));
        $quantity_raw = sanitize_text_field((string) ($quantity_values[$index] ?? ''));
        $unit = sanitize_text_field((string) ($unit_values[$index] ?? ''));
        $unit_rate_raw = sanitize_text_field((string) ($unit_rate_values[$index] ?? ''));
        $line_amount_raw = sanitize_text_field((string) ($amount_values[$index] ?? ''));
        $notes = sanitize_textarea_field((string) ($note_values[$index] ?? ''));
        $billing_bucket_id = (int) ($bucket_values[$index] ?? 0);

        $quantity = $quantity_raw !== '' ? number_format((float) $quantity_raw, 2, '.', '') : null;
        $unit_rate = $unit_rate_raw !== '' ? number_format((float) $unit_rate_raw, 2, '.', '') : null;
        if ($line_amount_raw !== '') {
            $line_amount = number_format((float) $line_amount_raw, 2, '.', '');
        } elseif ($quantity !== null && $unit_rate !== null) {
            $line_amount = number_format(((float) $quantity) * ((float) $unit_rate), 2, '.', '');
        } else {
            $line_amount = null;
        }

        return [
            'line_type' => $line_type,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_rate' => $unit_rate,
            'line_amount' => $line_amount,
            'billing_bucket_id' => $billing_bucket_id > 0 ? $billing_bucket_id : null,
            'notes' => $notes,
        ];
    }

    private static function invoice_line_payload_has_content(array $payload): bool
    {
        return trim((string) ($payload['description'] ?? '')) !== ''
            || trim((string) ($payload['quantity'] ?? '')) !== ''
            || trim((string) ($payload['unit_rate'] ?? '')) !== ''
            || trim((string) ($payload['line_amount'] ?? '')) !== ''
            || trim((string) ($payload['notes'] ?? '')) !== '';
    }

    private static function humanize_label(string $value): string
    {
        return ucwords(str_replace('_', ' ', trim($value)));
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
        if (! empty($row['job_summary'])) {
            return (string) $row['job_summary'];
        }

        $bucket_name = trim((string) ($row['bucket_name'] ?? ''));
        $is_default = (int) ($row['is_default'] ?? 0) === 1;
        if ($bucket_name === '') {
            return $is_default ? 'Default Job' : 'Job';
        }
        if ($is_default && in_array(strtolower($bucket_name), ['general', 'default', 'default bucket'], true)) {
            return 'Default Job';
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
        $is_work_statement = $totals === null;
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
        <div class="summary-copy"><?php echo esc_html($is_work_statement ? 'Frozen client work snapshot for the selected jobs and statuses in this reporting range.' : 'Invoice draft prepared for accounting handoff from the selected work in this billing period.'); ?></div>
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
          <th style="width:46%;"><?php echo esc_html($is_work_statement ? 'Task' : 'Task'); ?></th>
          <th style="width:20%;"><?php echo esc_html($is_work_statement ? 'Job' : 'Delivered'); ?></th>
          <th style="width:14%;"><?php echo esc_html($is_work_statement ? 'Status' : 'Priority'); ?></th>
          <th style="width:20%;"><?php echo esc_html($is_work_statement ? 'Billing Status' : 'Reference'); ?></th>
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
            <?php if ($is_work_statement) : ?>
              <td><?php echo esc_html(self::bucket_label_from_row($task)); ?></td>
              <td><span class="pill"><?php echo esc_html(self::humanize_label((string) ($task['status'] ?? 'pending_approval'))); ?></span></td>
              <td><?php echo esc_html(self::billing_status_label((string) ($task['billing_status'] ?? 'unbilled'))); ?></td>
            <?php else : ?>
              <td><?php echo esc_html(self::format_admin_datetime((string) ($task['delivered_at'] ?? $task['updated_at'] ?? ''))); ?></td>
              <td><span class="pill"><?php echo esc_html(ucfirst((string) ($task['priority'] ?? 'normal'))); ?></span></td>
              <td><?php echo esc_html('Task #' . (int) ($task['id'] ?? 0)); ?></td>
            <?php endif; ?>
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
      Generated from the Readspear Priority Portal. Keep this document with the related task record, work statement, and invoice draft for audit continuity.
    </p>
  </main>
</body>
</html>
        <?php
        exit;
    }

    private static function render_invoice_draft_print_document(array $statement): void
    {
        nocache_headers();
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $site_url = home_url('/');
        $admin_email = (string) get_option('admin_email');
        $document_code = (string) ($statement['statement_code'] ?? '-');
        $client_label = self::client_label_from_row($statement);
        $job_label = self::bucket_label_from_row($statement);
        $period_label = self::format_date_range((string) ($statement['range_start'] ?? ''), (string) ($statement['range_end'] ?? ''));
        $created_label = self::format_admin_datetime((string) ($statement['created_at'] ?? ''));
        $due_label = self::format_date_only((string) ($statement['due_date'] ?? ''));
        $currency = trim((string) (($statement['currency_code'] ?? '') ?: 'USD'));
        $total = number_format((float) ($statement['total_amount'] ?? 0), 2);
        $line_labels = self::invoice_line_type_labels();
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo esc_html('Invoice Draft ' . $document_code); ?></title>
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
    thead th { font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: #6b768b; padding: 12px 12px; background: #f7f9fc; border-bottom: 1px solid #dfe6ef; text-align: left; }
    tbody td { padding: 14px 12px; border-bottom: 1px solid #e6ebf3; vertical-align: top; text-align: left; }
    .line-title { font-weight: 800; margin-bottom: 6px; }
    .line-brief { color: #5d6880; font-size: 13px; line-height: 1.5; }
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
        <h1>Invoice Draft</h1>
        <p class="sub"><?php echo esc_html($site_url); ?><br><?php echo esc_html($admin_email); ?></p>
      </div>
      <div class="doc-card amount-card">
        <strong>Invoice Draft #</strong>
        <div class="doc-code"><?php echo esc_html($document_code); ?></div>
        <div style="margin-top:18px;">
          <strong>Total</strong>
          <div class="amount-value"><?php echo esc_html($currency . ' ' . $total); ?></div>
        </div>
      </div>
    </div>

    <div class="summary-grid">
      <section class="doc-card">
        <span class="label">Client</span>
        <div class="summary-value"><?php echo esc_html($client_label); ?></div>
        <div class="summary-copy">Accounting handoff draft. Financial totals derive from line items only; linked tasks are supporting traceability.</div>
      </section>
      <section class="doc-card">
        <div class="meta-stack">
          <div><span class="label">Jobs</span><div class="summary-value"><?php echo esc_html($job_label); ?></div></div>
          <div><span class="label">Billing period</span><div class="summary-value"><?php echo esc_html($period_label); ?></div></div>
        </div>
      </section>
      <section class="doc-card">
        <div class="meta-stack">
          <div><span class="label">Created</span><div class="summary-value"><?php echo esc_html($created_label); ?></div></div>
          <?php if ($due_label !== '' && $due_label !== '-') : ?>
            <div><span class="label">Due</span><div class="summary-value"><?php echo esc_html($due_label); ?></div></div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <div class="section-title">Invoice Lines</div>
    <table>
      <thead>
        <tr>
          <th style="width:40%;">Description</th>
          <th style="width:14%;">Type</th>
          <th style="width:12%;">Qty / Unit</th>
          <th style="width:12%;">Rate</th>
          <th style="width:12%;">Amount</th>
          <th style="width:10%;">Job</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ((array) ($statement['lines'] ?? []) as $line) : ?>
          <?php
          $linked_task_ids = self::decode_line_task_ids($line);
          $line_bucket_name = self::statement_line_bucket_name((int) ($line['billing_bucket_id'] ?? 0));
          $line_job_label = $line_bucket_name !== ''
              ? self::bucket_label_from_row([
                  'bucket_name' => $line_bucket_name,
                  'is_default' => 0,
              ])
              : $job_label;
          $line_type = (string) ($line['line_type'] ?? 'manual_adjustment');
          $quantity = (string) ($line['quantity'] ?? '');
          $unit = trim((string) ($line['unit'] ?? ''));
          $qty_label = trim($quantity . ($unit !== '' ? ' ' . $unit : ''));
          ?>
          <tr>
            <td>
              <div class="line-title"><?php echo esc_html((string) ($line['description'] ?? 'Untitled line')); ?></div>
              <?php if (! empty($line['notes'])) : ?>
                <div class="line-brief"><?php echo esc_html((string) $line['notes']); ?></div>
              <?php endif; ?>
              <?php if (! empty($linked_task_ids)) : ?>
                <div class="line-brief">Linked tasks: <?php echo esc_html(implode(', ', array_map(static fn(int $task_id): string => '#' . $task_id, $linked_task_ids))); ?></div>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html((string) ($line_labels[$line_type] ?? self::humanize_label($line_type))); ?></td>
            <td><?php echo esc_html($qty_label !== '' ? $qty_label : '—'); ?></td>
            <td><?php echo esc_html((string) ($line['unit_rate'] !== null && $line['unit_rate'] !== '' ? $currency . ' ' . number_format((float) $line['unit_rate'], 2) : '—')); ?></td>
            <td><?php echo esc_html((string) ($line['line_amount'] !== null && $line['line_amount'] !== '' ? $currency . ' ' . number_format((float) $line['line_amount'], 2) : '—')); ?></td>
            <td><?php echo esc_html($line_job_label); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="footer-grid">
      <?php if (! empty($statement['notes'])) : ?>
        <section class="doc-card notes">
          <strong>Notes</strong>
          <p><?php echo esc_html((string) $statement['notes']); ?></p>
        </section>
      <?php else : ?>
        <section class="doc-card notes">
          <strong>Summary</strong>
          <p>Invoice draft prepared by <?php echo esc_html($site_name); ?> for <?php echo esc_html($client_label); ?> covering <?php echo esc_html($period_label); ?>.</p>
        </section>
      <?php endif; ?>

      <section class="doc-card totals">
        <div class="totals-row"><strong>Currency</strong><span><?php echo esc_html($currency); ?></span></div>
        <div class="totals-row"><strong>Total</strong><span><?php echo esc_html($total); ?></span></div>
        <div class="totals-row"><strong>Lines</strong><span><?php echo esc_html((string) count((array) ($statement['lines'] ?? []))); ?></span></div>
        <?php if ($due_label !== '' && $due_label !== '-') : ?>
          <div class="totals-row"><strong>Due</strong><span><?php echo esc_html($due_label); ?></span></div>
        <?php endif; ?>
      </section>
    </div>

    <p class="fine-print">
      Generated from the Readspear Priority Portal. This document is an invoice draft for accounting handoff, not the issued invoice of record.
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
