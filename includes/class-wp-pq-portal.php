<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Portal
{
    public static function init(): void
    {
        add_shortcode('pq_client_portal', [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
    }

    public static function register_assets(): void
    {
        wp_register_style('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/css/admin-queue.css', [], WP_PQ_VERSION);
        wp_register_style('wp-pq-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.css', [], '6.1.19');
        wp_register_style('wp-pq-uppy', 'https://releases.transloadit.com/uppy/v3.27.1/uppy.min.css', [], '3.27.1');
        wp_register_script('sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js', [], '1.15.6', true);
        wp_register_script('wp-pq-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js', [], '6.1.19', true);
        wp_register_script('wp-pq-uppy', 'https://releases.transloadit.com/uppy/v3.27.1/uppy.min.js', [], '3.27.1', true);
        wp_register_script('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue.js', ['sortable-js', 'wp-pq-fullcalendar', 'wp-pq-uppy'], WP_PQ_VERSION, true);
    }

    public static function render_shortcode(): string
    {
        wp_enqueue_style('wp-pq-admin');
        wp_enqueue_style('wp-pq-fullcalendar');
        wp_enqueue_style('wp-pq-uppy');

        if (! is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());

            return '<div class="wp-pq-wrap wp-pq-guest">'
                . '<h2>Priority Portal</h2>'
                . '<p>Manage requests, files, approvals, and revisions.</p>'
                . '<div class="wp-pq-guest-card">'
                . '<h3>Sign in required</h3>'
                . '<p>Please sign in to access your request workspace.</p>'
                . '<a class="button button-primary" href="' . esc_url($login_url) . '">Log In</a>'
                . '</div>'
                . '</div>';
        }

        wp_enqueue_script('wp-pq-admin');

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

        ob_start();
        echo '<div class="wp-pq-wrap wp-pq-portal">';
        echo '  <div class="wp-pq-app-shell">';
        echo '    <aside class="wp-pq-binder">';
        echo '      <div class="wp-pq-binder-head">';
        echo '        <p class="wp-pq-kicker">Readspear Workflow</p>';
        echo '        <h2>Priority Portal</h2>';
        echo '        <p class="wp-pq-panel-note">Client accounts, jobs, approvals, and delivery all live here.</p>';
        echo '      </div>';
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-action">';
        echo '        <button class="button button-primary wp-pq-primary-action" type="button" id="wp-pq-open-create">New Request</button>';
        echo '      </div>';
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-status">';
        echo '        <p class="wp-pq-binder-label">Status</p>';
        echo '        <button class="button wp-pq-alerts-link" type="button" id="wp-pq-open-inbox"><span>Alerts</span><span id="wp-pq-inbox-count" class="wp-pq-inline-count">0</span></button>';
        echo '      </div>';
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
        echo '      <div class="wp-pq-binder-section">';
        echo '        <p class="wp-pq-binder-label">Filter</p>';
        echo '        <div id="wp-pq-filter-list" class="wp-pq-filter-nav wp-pq-filter-list"></div>';
        echo '        <div class="wp-pq-binder-secondary-actions">';
        echo '          <button class="button" type="button" id="wp-pq-batch-approve" hidden>Approve Selected</button>';
        echo '          <button class="button" type="button" id="wp-pq-batch-statement" hidden>Create Statement from Selected</button>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-bottom">';
        echo '        <button class="button" type="button" id="wp-pq-open-prefs">Preferences</button>';
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
        echo '        <input type="text" name="new_bucket_name" id="wp-pq-create-new-bucket" placeholder="Only if this client needs a new job">';
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
        echo '      <label>Requested Deadline <input type="datetime-local" name="requested_deadline" step="900"></label>';
        echo '      <label class="inline wp-pq-span-2"><input type="checkbox" name="needs_meeting"> Meeting Requested</label>';
        echo '      <label class="inline wp-pq-manager-only wp-pq-span-2"><input type="checkbox" name="is_billable" checked> Billable task</label>';
        echo '      <div class="wp-pq-create-actions wp-pq-span-2">';
        echo '        <button class="button wp-pq-secondary-action wp-pq-manager-only" type="button" id="wp-pq-open-ai-import" hidden>Import with AI</button>';
        echo '        <button class="button button-primary" type="submit">Submit Request</button>';
        echo '      </div>';
        echo '    </form>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel wp-pq-pref-panel" id="wp-pq-pref-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>Notification Preferences</h3>';
        echo '        <p class="wp-pq-panel-note">Choose whether you want immediate client updates and the daily digest.</p>';
        echo '      </div>';
        echo '      <button class="button" type="button" id="wp-pq-close-prefs">Close</button>';
        echo '    </div>';
        echo '    <div id="wp-pq-pref-list" class="wp-pq-pref-list"></div>';
        echo '    <button class="button button-primary" type="button" id="wp-pq-save-prefs">Save Preferences</button>';
        echo '  </section>';

        echo '  <div class="wp-pq-workspace-body">';
        echo '    <div class="wp-pq-workspace-main">';
        echo '  <section class="wp-pq-board-shell">';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>Task Board</h3>';
        echo '        <p class="wp-pq-panel-note">Open any card to review details, files, approvals, and messages in the task workspace.</p>';
        echo '      </div>';
        echo '    </div>';
        echo '    <div id="wp-pq-board-panel">';
        echo '      <div id="wp-pq-board" class="wp-pq-board"></div>';
        echo '    </div>';
        echo '    <div id="wp-pq-calendar-panel" hidden>';
        echo '      <div id="wp-pq-calendar"></div>';
        echo '    </div>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel wp-pq-inbox-panel" id="wp-pq-inbox-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>Alerts</h3>';
        echo '        <p class="wp-pq-panel-note">Workflow changes, mentions, and approvals that need your attention.</p>';
        echo '      </div>';
      echo '      <button class="button" type="button" id="wp-pq-close-inbox">Close</button>';
        echo '    </div>';
        echo '    <div class="wp-pq-inbox-actions">';
        echo '      <button class="button" type="button" id="wp-pq-mark-all-read">Mark all read</button>';
        echo '    </div>';
        echo '    <ul id="wp-pq-inbox-list" class="wp-pq-stream"></ul>';
        echo '  </section>';
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
        echo '            <div id="wp-pq-uppy" class="wp-pq-uppy"></div>';
        echo '            <form id="wp-pq-file-form">';
        echo '              <label>Type';
        echo '                <select name="file_role">';
        echo '                  <option value="input">Input</option>';
        echo '                  <option value="deliverable">Deliverable</option>';
        echo '                </select>';
        echo '              </label>';
        echo '              <label>Upload file <input type="file" name="file" required></label>';
        echo '              <button class="button" type="submit">Upload File</button>';
        echo '            </form>';
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
        echo '        <button class="button" type="button" id="wp-pq-close-move-modal">Cancel</button>';
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
        echo '        <label class="wp-pq-choice">';
          echo '          <input type="checkbox" name="swap_due_dates" value="1">';
          echo '          <span><strong>Swap dates too</strong><small>Exchange the two tasks&#039; requested and due dates.</small></span>';
        echo '        </label>';
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
        echo '</div>';

        return (string) ob_get_clean();
    }
}
