<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Portal
{
    private static ?string $portal_url_cache = null;

    public static function init(): void
    {
        add_shortcode('pq_client_portal', [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('template_redirect', [self::class, 'redirect_old_portal_slug']);
    }

    /**
     * Redirect the retired /priority-portal/ slug to /switchboard/.
     */
    public static function redirect_old_portal_slug(): void
    {
        if (is_404()) {
            $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
            if ($path === 'priority-portal' || strpos($path, 'priority-portal') === 0) {
                $new_url = home_url('/switchboard/');
                $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
                wp_redirect($new_url . $qs, 301);
                exit;
            }
        }
    }

    public static function register_assets(): void
    {
        wp_register_style('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/css/admin-queue.css', [], WP_PQ_VERSION);
        wp_register_style('wp-pq-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.css', [], '6.1.19');
        wp_register_style('wp-pq-uppy', 'https://releases.transloadit.com/uppy/v3.27.1/uppy.min.css', [], '3.27.1');
        wp_register_script('wp-pq-uppy', 'https://releases.transloadit.com/uppy/v3.27.1/uppy.min.js', [], '3.27.1', true);
        wp_register_script('sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js', [], '1.15.6', true);
        wp_register_script('wp-pq-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js', [], '6.1.19', true);
        wp_register_script('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue.js', ['sortable-js', 'wp-pq-fullcalendar'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-modals', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue-modals.js', ['wp-pq-admin'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-alerts', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue-alerts.js', ['wp-pq-admin'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-portal-manager', WP_PQ_PLUGIN_URL . 'assets/js/admin-portal-manager.js', ['wp-pq-admin', 'wp-pq-modals', 'wp-pq-alerts', 'wp-pq-uppy'], WP_PQ_VERSION, true);
    }

    public static function portal_url(string $section = 'queue'): string
    {
        if (self::$portal_url_cache === null) {
            $pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                's' => '[pq_client_portal]',
            ]);

            $url = '';
            if (! empty($pages)) {
                $candidate = get_permalink((int) $pages[0]->ID);
                if (is_string($candidate) && $candidate !== '') {
                    $url = $candidate;
                }
            }

            if ($url === '') {
                $url = home_url('/switchboard/');
            }

            self::$portal_url_cache = $url;
        }

        $url = self::$portal_url_cache ?: home_url('/switchboard/');
        $section = sanitize_key($section);
        if ($section === '' || $section === 'queue') {
            return $url;
        }

        return add_query_arg('section', $section, $url);
    }

    public static function render_shortcode(): string
    {
        wp_enqueue_style('wp-pq-admin');
        wp_enqueue_style('wp-pq-fullcalendar');

        if (! is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());

            return '<div class="wp-pq-wrap wp-pq-guest">'
                . '<h2>Switchboard</h2>'
                . '<p>Requests, approvals, files, and scheduling in one place.</p>'
                . '<div class="wp-pq-guest-card">'
                . '<h3>Sign in required</h3>'
                . '<p>Please sign in to access your workspace.</p>'
                . '<a class="button button-primary" href="' . esc_url($login_url) . '">Log In</a>'
                . '</div>'
                . '</div>';
        }

        wp_enqueue_script('wp-pq-admin');
        wp_enqueue_script('wp-pq-modals');
        wp_enqueue_script('wp-pq-alerts');

        $is_manager = current_user_can(WP_PQ_Roles::CAP_APPROVE);
        if ($is_manager) {
            wp_enqueue_script('wp-pq-portal-manager');
            wp_enqueue_style('wp-pq-uppy');
            wp_enqueue_script('wp-pq-uppy');
        }

        $portal_config = [
            'root' => esc_url_raw(rest_url('pq/v1/')),
            'coreRoot' => esc_url_raw(rest_url('wp/v2/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'portalUrl' => esc_url_raw(self::portal_url()),
            'canApprove' => current_user_can(WP_PQ_Roles::CAP_APPROVE),
            'canWork' => current_user_can(WP_PQ_Roles::CAP_WORK),
            'canAssign' => current_user_can(WP_PQ_Roles::CAP_ASSIGN),
            'canBatch' => current_user_can(WP_PQ_Roles::CAP_APPROVE),
            'canViewAll' => current_user_can(WP_PQ_Roles::CAP_VIEW_ALL),
            'currentUserId' => get_current_user_id(),
            'isManager' => $is_manager,
        ];
        wp_localize_script('wp-pq-admin', 'wpPqConfig', $portal_config);
        if ($is_manager) {
            wp_localize_script('wp-pq-portal-manager', 'wpPqManagerConfig', $portal_config);
        }

        ob_start();
        echo '<div class="wp-pq-wrap wp-pq-portal">';
        echo '  <div class="wp-pq-app-shell">';
        echo '    <aside class="wp-pq-binder">';
        echo '      <div class="wp-pq-binder-head">';
        echo '        <p class="wp-pq-kicker">Readspear</p>';
        echo '        <h2>Switchboard</h2>';
        echo '        <p class="wp-pq-panel-note">Requests, approvals, files, and scheduling in one place.</p>';
        echo '      </div>';
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-action">';
        echo '        <button class="button button-primary wp-pq-primary-action" type="button" id="wp-pq-open-create">New Request</button>';
        echo '      </div>';
        echo '      <div id="wp-pq-queue-binder-sections">';
        echo '      <div class="wp-pq-binder-section">';
        echo '        <p class="wp-pq-binder-label">Mode</p>';
        echo '        <div class="wp-pq-view-toggle">';
        echo '          <button class="button button-primary is-active" id="wp-pq-view-board" type="button">Board</button>';
        echo '          <button class="button" id="wp-pq-view-calendar" type="button">Calendar</button>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-scope">';
        echo '        <p class="wp-pq-binder-label">Scope</p>';
        echo '        <div id="wp-pq-binder-client-context" class="wp-pq-binder-context">Loading client scope…</div>';
        echo '        <div id="wp-pq-binder-job-context" class="wp-pq-binder-context">Loading jobs…</div>';
        echo '        <div class="wp-pq-board-filters" id="wp-pq-board-filters" hidden>';
        echo '          <label class="wp-pq-filter-control" id="wp-pq-client-filter-wrap" hidden>Client';
        echo '            <select id="wp-pq-client-filter"></select>';
        echo '          </label>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="wp-pq-binder-section" id="wp-pq-job-nav-wrap" hidden>';
        echo '        <p class="wp-pq-binder-label">Jobs</p>';
        echo '        <div id="wp-pq-job-nav" class="wp-pq-job-nav wp-pq-filter-nav"></div>';
        echo '      </div>';
        echo '      </div>'; // close wp-pq-queue-binder-sections
        if ($is_manager) {
            echo '      <div class="wp-pq-binder-section">';
            echo '        <p class="wp-pq-binder-label">Workspace</p>';
            echo '        <div id="wp-pq-manager-nav" class="wp-pq-filter-nav wp-pq-manager-nav">';
            echo '          <button class="button is-active" type="button" data-pq-section="queue"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">☰</span><span>Queue</span></span></button>';
            echo '          <button class="button" type="button" data-pq-section="clients"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">◌</span><span>Clients</span></span></button>';
            echo '          <button class="button" type="button" data-pq-section="billing-rollup"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">◔</span><span>Billing Rollup</span></span></button>';
            echo '          <button class="button" type="button" data-pq-section="monthly-statements"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">◫</span><span>Monthly Statements</span></span></button>';
            echo '          <button class="button" type="button" data-pq-section="work-statements"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">✎</span><span>Work Statements</span></span></button>';
            echo '          <button class="button" type="button" data-pq-section="ai-import"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">✦</span><span>AI Import</span></span></button>';
            echo '          <button class="button" type="button" data-pq-section="preferences"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">○</span><span>Preferences</span></span></button>';
            echo '          <button class="button" type="button" data-pq-section="documents"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">▤</span><span>Documents</span></span></button>';
            echo '        </div>';
            echo '      </div>';
        }
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-bottom">';
        echo '        <button class="button wp-pq-dark-mode-btn" type="button" id="wp-pq-dark-toggle"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">◐</span><span>Dark Mode</span></span></button>';
        echo '        <button class="button" type="button" id="wp-pq-open-prefs"' . ($is_manager ? ' hidden' : '') . '><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">○</span><span>Preferences</span></span></button>';
        echo '      </div>';
        echo '    </aside>';
        echo '    <main class="wp-pq-workspace">';
        echo '  <section class="wp-pq-panel wp-pq-compose-panel" id="wp-pq-create-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>New Request</h3>';
        echo '        <p class="wp-pq-panel-note">Submitter uploads files, describes needs, assigns deadline, and can request a meeting when needed.</p>';
        echo '      </div>';
        echo '      <button class="button" type="button" id="wp-pq-close-create">Close</button>';
        echo '    </div>';
        echo '    <form id="wp-pq-create-form" class="wp-pq-create-grid">';
        echo '      <label>Title <input type="text" name="title" required></label>';
        echo '      <label class="wp-pq-manager-only" id="wp-pq-create-client-wrap" hidden>Client';
        echo '        <select name="client_id" id="wp-pq-create-client"></select>';
        echo '      </label>';
        echo '      <label>Job';
        echo '        <select name="billing_bucket_id" id="wp-pq-create-bucket"></select>';
        echo '      </label>';
        echo '      <label class="wp-pq-manager-only" id="wp-pq-create-new-bucket-wrap" hidden>Create new job';
        echo '        <input type="text" name="new_bucket_name" id="wp-pq-create-new-bucket" placeholder="Job name">';
        echo '      </label>';
        echo '      <label class="wp-pq-span-2">Description <textarea name="description" rows="3"></textarea></label>';
        echo '      <label>Priority';
        echo '        <select name="priority">';
        echo '          <option value="low">Low</option>';
        echo '          <option value="normal" selected>Normal</option>';
        echo '          <option value="high">High</option>';
        echo '          <option value="urgent">Urgent</option>';
        echo '        </select>';
        echo '      </label>';
        echo '      <label>Requested Deadline <input type="datetime-local" name="requested_deadline" step="60"></label>';
        echo '      <label class="inline wp-pq-span-2"><input type="checkbox" name="needs_meeting" id="wp-pq-create-needs-meeting"> Meeting Requested</label>';
        echo '      <div class="wp-pq-create-meeting-fields wp-pq-span-2" id="wp-pq-create-meeting-fields" hidden>';
        echo '        <label>Meeting Start <input type="datetime-local" name="meeting_starts_at" id="wp-pq-create-meeting-start" step="60"></label>';
        echo '        <label>Meeting End <input type="datetime-local" name="meeting_ends_at" id="wp-pq-create-meeting-end" step="60"></label>';
        echo '        <p class="wp-pq-panel-note wp-pq-span-2">Google Meet will invite the task requester.</p>';
        echo '      </div>';
        echo '      <label class="inline wp-pq-manager-only wp-pq-span-2"><input type="checkbox" name="is_billable" checked> Billable task</label>';
        echo '      <div class="wp-pq-create-actions wp-pq-span-2">';
        echo '        <button class="button button-primary" type="submit">Submit Request</button>';
        echo '      </div>';
        echo '    </form>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel wp-pq-pref-panel" id="wp-pq-pref-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>Preferences</h3>';
        echo '        <p class="wp-pq-panel-note">Notification controls live here now, and this panel is ready to expand later with appearance and layout options.</p>';
        echo '      </div>';
        echo '      <button class="button" type="button" id="wp-pq-close-prefs">Close</button>';
        echo '    </div>';
        echo '    <section class="wp-pq-pref-section">';
        echo '      <div class="wp-pq-pref-section-head">';
        echo '        <div>';
        echo '          <h4>Notifications</h4>';
        echo '          <p class="wp-pq-panel-note">Choose which emails you want, and whether alerts dismiss themselves or stay until you clear them.</p>';
        echo '        </div>';
      echo '      </div>';
        echo '      <div id="wp-pq-pref-list" class="wp-pq-pref-list"></div>';
        echo '      <button class="button button-primary" type="button" id="wp-pq-save-prefs">Save Preferences</button>';
        echo '    </section>';
        // Google Calendar connection — managers only.
        if (current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            echo '    <section class="wp-pq-pref-section" id="wp-pq-gcal-section">';
            echo '      <div class="wp-pq-pref-section-head">';
            echo '        <div>';
            echo '          <h4>Google Calendar</h4>';
            echo '          <p class="wp-pq-panel-note">Connect once so meetings auto-create Google Calendar events with Meet links.</p>';
            echo '        </div>';
            echo '      </div>';
            echo '      <div id="wp-pq-gcal-status" class="wp-pq-gcal-status">';
            echo '        <span class="wp-pq-gcal-indicator" id="wp-pq-gcal-indicator"></span>';
            echo '      </div>';
            echo '    </section>';
        }

        echo '    <section class="wp-pq-pref-section">';
        echo '      <div class="wp-pq-pref-section-head">';
        echo '        <div>';
        echo '          <h4>More Soon</h4>';
        echo '          <p class="wp-pq-panel-note">Appearance, type size, and layout controls will land here as the portal grows.</p>';
        echo '        </div>';
      echo '      </div>';
        echo '      <div class="wp-pq-empty-state">Preferences will keep growing here without turning into a separate admin screen.</div>';
        echo '    </section>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel wp-pq-docs-panel" id="wp-pq-docs-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>Documents</h3>';
        echo '        <p class="wp-pq-panel-note">Upload, browse, and manage files across all tasks.</p>';
        echo '      </div>';
        echo '      <button class="button" type="button" id="wp-pq-close-docs">Close</button>';
        echo '    </div>';
        echo '    <div id="wp-pq-docs-uppy"></div>';
        echo '    <div id="wp-pq-docs-list" class="wp-pq-docs-list"></div>';
        echo '  </section>';

        echo '  <div id="wp-pq-alert-stack" class="wp-pq-alert-stack" aria-live="polite" aria-label="Current alerts"></div>';

        echo '  <div class="wp-pq-workspace-body">';
        echo '    <div class="wp-pq-workspace-main">';
        echo '  <section class="wp-pq-panel wp-pq-manager-panel" id="wp-pq-manager-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3 id="wp-pq-manager-panel-title">Manager Workspace</h3>';
        echo '        <p class="wp-pq-panel-note" id="wp-pq-manager-panel-note">Portal-based admin tools for clients, billing, reporting, invoice drafts, and import.</p>';
        echo '      </div>';
        echo '    </div>';
        echo '    <div id="wp-pq-manager-toolbar" class="wp-pq-manager-toolbar"></div>';
        echo '    <div id="wp-pq-manager-content" class="wp-pq-manager-content"></div>';
        echo '  </section>';
        echo '  <div id="wp-pq-queue-main-sections">';
        echo '  <section class="wp-pq-board-shell">';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>Task Board</h3>';
        echo '        <p class="wp-pq-panel-note">Open any card to review details, files, approvals, and messages in the task workspace.</p>';
        echo '      </div>';
        echo '    </div>';
        echo '    <div id="wp-pq-board-filter-bar" class="wp-pq-board-filter-bar"></div>';
        echo '    <div id="wp-pq-board-panel">';
        echo '      <div id="wp-pq-board" class="wp-pq-board"></div>';
        echo '    </div>';
        echo '    <div id="wp-pq-calendar-panel" hidden>';
        echo '      <div id="wp-pq-calendar"></div>';
        echo '    </div>';
        echo '  </section>';

        echo '    </div>';
        echo '    </div>';
        echo '    <div class="wp-pq-drawer-backdrop" id="wp-pq-drawer-backdrop" hidden></div>';
        echo '    <aside class="wp-pq-drawer" id="wp-pq-task-drawer" aria-hidden="true">';
        echo '      <div class="wp-pq-drawer-header">';
        echo '        <div class="wp-pq-drawer-topline">';
        echo '          <p id="wp-pq-current-task-status" class="wp-pq-drawer-kicker">Select a task</p>';
        echo '          <button class="wp-pq-drawer-close" id="wp-pq-close-drawer" type="button" aria-label="Close task drawer">&times;</button>';
        echo '        </div>';
        echo '        <h3 id="wp-pq-current-task">Task Details</h3>';
        echo '        <div id="wp-pq-current-task-meta" class="wp-pq-drawer-context">Choose a board card or calendar item to open its workspace.</div>';
        echo '        <div id="wp-pq-current-task-guidance" class="wp-pq-guidance-callout" hidden></div>';
        echo '        <p id="wp-pq-current-task-description" class="wp-pq-drawer-description"></p>';
        echo '        <div id="wp-pq-current-task-actions" class="wp-pq-drawer-actions"></div>';
        echo '        <div id="wp-pq-assignment-panel" class="wp-pq-assignment-panel" hidden>';
        echo '          <div class="wp-pq-assignment-copy">';
        echo '            <strong>Responsibility</strong>';
        echo '            <div id="wp-pq-assignment-summary" class="wp-pq-assignment-facts">Choose who is responsible for the next step.</div>';
        echo '          </div>';
        echo '          <div class="wp-pq-assignment-controls">';
        echo '            <select id="wp-pq-assignment-select"></select>';
        echo '            <button class="button" type="button" id="wp-pq-save-assignment">Assign</button>';
        echo '          </div>';
        echo '        </div>';
        echo '        <div id="wp-pq-priority-panel" class="wp-pq-priority-panel" hidden>';
        echo '          <div class="wp-pq-priority-copy">';
        echo '            <strong>Priority</strong>';
        echo '            <div class="wp-pq-panel-note">Set urgency directly when it changes.</div>';
        echo '          </div>';
        echo '          <div class="wp-pq-priority-controls">';
        echo '            <select id="wp-pq-priority-select">';
        echo '              <option value="low">Low</option>';
        echo '              <option value="normal">Normal</option>';
        echo '              <option value="high">High</option>';
        echo '              <option value="urgent">Urgent</option>';
        echo '            </select>';
        echo '            <button class="button" type="button" id="wp-pq-save-priority">Update Priority</button>';
        echo '          </div>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div id="wp-pq-task-empty" class="wp-pq-task-empty">Select a task to open its workspace and review messages, notes, files, and approvals.</div>';
        echo '      <div id="wp-pq-task-workspace" class="wp-pq-task-workspace" hidden>';
        echo '        <div class="wp-pq-workspace-tabs">';
        echo '          <button class="button button-primary is-active" type="button" id="wp-pq-tab-messages">Messages</button>';
        echo '          <button class="button" type="button" id="wp-pq-tab-meetings">Meeting</button>';
        echo '          <button class="button" type="button" id="wp-pq-tab-notes">Sticky Notes</button>';
        echo '          <button class="button" type="button" id="wp-pq-tab-files">Files</button>';
        echo '        </div>';
        echo '        <div class="wp-pq-workspace-shell">';
        echo '          <div class="wp-pq-subpanel wp-pq-workspace-panel is-active" id="wp-pq-panel-messages">';
        echo '            <h4>Messages</h4>';
        echo '            <p class="wp-pq-panel-note">Use @handle to notify someone directly.</p>';
        echo '            <div id="wp-pq-mention-list" class="wp-pq-mention-list"></div>';
        echo '            <ul id="wp-pq-message-list" class="wp-pq-stream"></ul>';
        echo '            <form id="wp-pq-message-form">';
        echo '              <label>Message <textarea name="body" rows="2" required></textarea></label>';
        echo '              <button class="button" type="submit">Send Message</button>';
        echo '            </form>';
        echo '          </div>';
        echo '          <div class="wp-pq-subpanel wp-pq-workspace-panel" id="wp-pq-panel-meetings" hidden>';
        echo '            <h4>Meeting</h4>';
        echo '            <p class="wp-pq-panel-note" id="wp-pq-meeting-summary">Meeting details will appear here when requested.</p>';
        echo '            <ul id="wp-pq-meeting-list" class="wp-pq-stream"></ul>';
        echo '            <form id="wp-pq-meeting-form" class="wp-pq-meeting-form">';
        echo '              <label>Start <input type="datetime-local" name="starts_at" step="900"></label>';
        echo '              <label>End <input type="datetime-local" name="ends_at" step="900"></label>';
        echo '              <button class="button" type="submit">Schedule Google Meet</button>';
        echo '            </form>';
        echo '          </div>';
        echo '          <div class="wp-pq-subpanel wp-pq-workspace-panel" id="wp-pq-panel-notes" hidden>';
        echo '            <h4>Sticky Notes</h4>';
        echo '            <p class="wp-pq-panel-note">Pinned context for the task. Sticky notes do not send email.</p>';
        echo '            <ul id="wp-pq-note-list" class="wp-pq-stream"></ul>';
        echo '            <form id="wp-pq-note-form">';
        echo '              <label>Sticky note <textarea name="body" rows="2" required></textarea></label>';
        echo '              <button class="button" type="submit">Add Sticky Note</button>';
        echo '            </form>';
        echo '          </div>';
        echo '          <div class="wp-pq-subpanel wp-pq-workspace-panel" id="wp-pq-panel-files" hidden>';
        echo '            <h4>Files</h4>';
        echo '            <ul id="wp-pq-file-list" class="wp-pq-stream"></ul>';
        echo '            <div id="wp-pq-uppy" class="wp-pq-file-dropzone">';
        echo '              <span class="wp-pq-dropzone-label">Drop files here or <button type="button" class="wp-pq-dropzone-browse">browse</button></span>';
        echo '              <input type="file" id="wp-pq-file-input" multiple hidden>';
        echo '              <div id="wp-pq-upload-progress" class="wp-pq-upload-progress" hidden></div>';
        echo '            </div>';
        echo '          </div>';
        echo '        </div>';
        echo '      </div>';
        echo '    </aside>';
        echo '  </div>';
        echo '    </main>';
        echo '  </div>';

        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-revision-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-revision-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-revision-title">';
        echo '    <div class="wp-pq-modal-card wp-pq-modal-card-compact">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Revision request</p>';
        echo '          <h3 id="wp-pq-revision-title">What needs to change?</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-revision-summary">Add a short note so the requester knows what to revise.</p>';
        echo '        </div>';
        echo '        <button class="button" type="button" id="wp-pq-close-revision-modal">Cancel</button>';
        echo '      </div>';
        echo '      <form id="wp-pq-revision-form">';
        echo '        <label>Revision note <textarea name="revision_note" rows="3" required></textarea></label>';
        echo '        <label class="inline"><input type="checkbox" name="post_message" value="1" checked> Post this to task messages</label>';
        echo '        <div class="wp-pq-modal-actions">';
        echo '          <button class="button" type="button" id="wp-pq-cancel-revision">Cancel</button>';
        echo '          <button class="button button-primary" type="submit">Send to Revisions</button>';
        echo '        </div>';
        echo '      </form>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-move-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-move-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-move-title">';
        echo '    <div class="wp-pq-modal-card">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Queue decision</p>';
        echo '          <h3 id="wp-pq-move-title">How should this move apply?</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-move-summary">Choose whether this move should also change priority or dates.</p>';
        echo '        </div>';
        echo '      </div>';
        echo '      <form id="wp-pq-move-form">';
        echo '        <div class="wp-pq-choice wp-pq-choice-static">';
        echo '          <span><strong>Always included</strong><small>This move updates task order. If you moved the task into a new column, it also changes status.</small></span>';
        echo '        </div>';
        echo '        <fieldset class="wp-pq-choice-group">';
        echo '          <legend><strong>Priority change</strong><small>Only apply a priority change if this move should affect urgency.</small></legend>';
        echo '          <label class="wp-pq-choice">';
        echo '            <input type="radio" name="priority_direction" value="keep" checked>';
        echo '            <span><strong>Keep current priority</strong><small>Move the task without changing priority.</small></span>';
        echo '          </label>';
        echo '          <label class="wp-pq-choice">';
        echo '            <input type="radio" name="priority_direction" value="up">';
        echo '            <span><strong>Raise priority</strong><small>Promote the task one level.</small></span>';
        echo '          </label>';
        echo '          <label class="wp-pq-choice">';
        echo '            <input type="radio" name="priority_direction" value="down">';
        echo '            <span><strong>Lower priority</strong><small>Downgrade the task one level.</small></span>';
        echo '          </label>';
        echo '        </fieldset>';
        echo '        <label class="wp-pq-choice" id="wp-pq-move-meeting-option" hidden>';
          echo '          <input type="checkbox" name="request_meeting" value="1">';
          echo '          <span><strong>Open meeting scheduling next</strong><small>Mark the task as meeting requested and open the Google Meet scheduler after this move.</small></span>';
        echo '        </label>';
        echo '        <label class="wp-pq-choice" id="wp-pq-move-email-option" hidden>';
          echo '          <input type="checkbox" name="send_update_email" value="1">';
          echo '          <span><strong>Send update email now</strong><small>Email the client members who can see this job with an immediate status summary.</small></span>';
        echo '        </label>';
        echo '        <div class="wp-pq-modal-actions">';
        echo '          <button class="button" type="button" id="wp-pq-cancel-move">Cancel</button>';
        echo '          <button class="button button-primary" type="submit" id="wp-pq-apply-move">Apply Change</button>';
        echo '        </div>';
        echo '      </form>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-completion-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-completion-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-completion-title">';
        echo '    <div class="wp-pq-modal-card">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Completion</p>';
        echo '          <h3 id="wp-pq-completion-title">Mark this task done</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-completion-summary">Capture the billing details needed to close this task and write it to the work ledger.</p>';
        echo '        </div>';
        echo '        <button class="button" type="button" id="wp-pq-close-completion-modal">Cancel</button>';
        echo '      </div>';
        echo '      <form id="wp-pq-completion-form" class="wp-pq-create-grid">';
        echo '        <label>Billing mode';
        echo '          <select name="billing_mode" id="wp-pq-completion-billing-mode" required>';
        echo '            <option value="fixed_fee">Fixed fee</option>';
        echo '            <option value="hourly">Hourly</option>';
        echo '            <option value="pass_through_expense">Pass-through expense</option>';
        echo '            <option value="non_billable">Non-billable</option>';
        echo '          </select>';
        echo '        </label>';
        echo '        <label>Billing category <input type="text" name="billing_category" id="wp-pq-completion-billing-category" placeholder="Support, retainer, reimbursement, etc."></label>';
        echo '        <label class="wp-pq-span-2">Work summary <textarea name="work_summary" id="wp-pq-completion-work-summary" rows="4" required></textarea></label>';
        echo '        <p class="wp-pq-panel-note wp-pq-span-2" id="wp-pq-completion-mode-note">Fixed-fee work needs a short summary and billing category. Hours, rate, and amount stay optional.</p>';
        echo '        <label data-completion-field="hours">Hours <input type="number" name="hours" id="wp-pq-completion-hours" min="0" step="0.25" inputmode="decimal" placeholder="0.00"></label>';
        echo '        <label data-completion-field="rate">Rate <input type="number" name="rate" id="wp-pq-completion-rate" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></label>';
        echo '        <label data-completion-field="amount">Amount <input type="number" name="amount" id="wp-pq-completion-amount" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></label>';
        echo '        <label data-completion-field="expense_reference">Expense reference <input type="text" name="expense_reference" id="wp-pq-completion-expense-reference" placeholder="Vendor invoice, support pass-through, receipt ID"></label>';
        echo '        <label class="wp-pq-span-2" data-completion-field="non_billable_reason">Non-billable reason <textarea name="non_billable_reason" id="wp-pq-completion-non-billable-reason" rows="3" placeholder="Internal support, goodwill, admin cleanup, etc."></textarea></label>';
        echo '        <div class="wp-pq-modal-actions wp-pq-span-2">';
        echo '          <button class="button" type="button" id="wp-pq-cancel-completion">Cancel</button>';
        echo '          <button class="button button-primary" type="submit" id="wp-pq-apply-completion">Mark Done</button>';
        echo '        </div>';
        echo '      </form>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-delete-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-delete-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-delete-title">';
        echo '    <div class="wp-pq-modal-card wp-pq-modal-card-compact">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Delete task</p>';
        echo '          <h3 id="wp-pq-delete-title">Delete this task permanently?</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-delete-summary">This removes the task and its related messages, notes, files, meetings, and notifications.</p>';
        echo '        </div>';
        echo '        <button class="button" type="button" id="wp-pq-close-delete-modal">Cancel</button>';
        echo '      </div>';
        echo '      <div class="wp-pq-modal-actions">';
        echo '        <button class="button" type="button" id="wp-pq-cancel-delete">Cancel</button>';
        echo '        <button class="button button-primary wp-pq-button-danger" type="button" id="wp-pq-confirm-delete">Delete Task</button>';
        echo '      </div>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-floating-meeting" id="wp-pq-floating-meeting" hidden>';
        echo '    <div class="wp-pq-floating-meeting-header">';
        echo '      <h4 id="wp-pq-floating-meeting-title">Schedule Meeting</h4>';
        echo '      <button type="button" class="wp-pq-floating-meeting-close" id="wp-pq-floating-meeting-close" aria-label="Close">&times;</button>';
        echo '    </div>';
        echo '    <p class="wp-pq-panel-note" id="wp-pq-floating-meeting-summary"></p>';
        echo '    <form id="wp-pq-floating-meeting-form" class="wp-pq-floating-meeting-body">';
        echo '      <label>Start <input type="datetime-local" name="starts_at" step="900"></label>';
        echo '      <label>End <input type="datetime-local" name="ends_at" step="900"></label>';
        echo '      <div class="wp-pq-floating-meeting-actions">';
        echo '        <button class="button" type="button" id="wp-pq-floating-meeting-skip">Skip for now</button>';
        echo '        <button class="button button-primary" type="submit">Schedule Google Meet</button>';
        echo '      </div>';
        echo '    </form>';
        echo '  </div>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}
