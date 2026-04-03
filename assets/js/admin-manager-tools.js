(function (m) {
  var state = m.state;
  var el = m.el;
  var esc = m.esc;
  var api = m.api;
  var submitJson = m.submitJson;
  var ensureClients = m.ensureClients;
  var clientOptions = m.clientOptions;
  var clientJobOptions = m.clientJobOptions;
  var workflowStatusLabel = m.workflowStatusLabel;
  var formatDate = m.formatDate;
  var replaceSectionUrl = m.replaceSectionUrl;
  var renderManagerFrame = m.renderManagerFrame;
  var memberOptions = m.memberOptions;
  var printHtml = m.printHtml;
  var toast = m.toast;

  // ── Files & Links ────────────────────────────────────────────────────

  async function renderFiles() {
    renderManagerFrame('files');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading files…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const clientId = params.get('client_id') || '0';
    const bucketId = params.get('bucket_id') || '0';
    const queryParts = [];
    if (Number(clientId)) queryParts.push(`client_id=${encodeURIComponent(clientId)}`);
    if (Number(bucketId)) queryParts.push(`bucket_id=${encodeURIComponent(bucketId)}`);
    const data = await api('files' + (queryParts.length ? '?' + queryParts.join('&') : ''));
    const items = data.items || data.files || [];

    el.managerToolbar.innerHTML = `
      <form id="wp-pq-files-filter-form" class="wp-pq-period-form">
        <label>Client
          <select name="client_id">${clientOptions(clientId, true)}</select>
        </label>
        <label>Job
          <select name="bucket_id" id="wp-pq-files-job-filter">${Number(clientId || 0) > 0 ? clientJobOptions(clientId, bucketId, true) : '<option value="0">All jobs</option>'}</select>
        </label>
      </form>
    `;

    if (!items.length) {
      el.managerContent.innerHTML = '<div class="wp-pq-empty-state">No tasks with linked files match those filters.</div>';
      return;
    }

    el.managerContent.innerHTML = `
      <section class="wp-pq-panel wp-pq-manager-card">
        <table class="wp-pq-admin-table wp-pq-manager-table">
          <thead><tr><th>Task</th><th>Client</th><th>Job</th><th>Status</th><th>Link</th><th>Updated</th></tr></thead>
          <tbody>
            ${items.map((item) => {
              const linkUrl = esc(item.files_link || '');
              const linkLabel = linkUrl.length > 60 ? linkUrl.slice(0, 57) + '…' : linkUrl;
              return `
              <tr>
                <td><strong>${esc(item.title || 'Untitled')}</strong></td>
                <td>${esc(item.client_name || '—')}</td>
                <td>${esc(item.bucket_name || '—')}</td>
                <td>${esc(item.status || '')}</td>
                <td><a href="${linkUrl}" target="_blank" rel="noopener noreferrer">${linkLabel}</a></td>
                <td>${esc(String(item.updated_at || '').slice(0, 10))}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </section>
    `;
  }

  // ── Invites ──────────────────────────────────────────────────────────

  async function renderInvites() {
    renderManagerFrame('invites');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading invites…</div>';
    await ensureClients();
    const data = await api('manager/invites');
    const invites = data.invites || [];

    el.managerToolbar.innerHTML = `
      <div class="wp-pq-manager-toolbar-actions">
        <button class="button button-primary" type="button" id="wp-pq-toggle-invite-form">Send Invite</button>
      </div>
      <div id="wp-pq-invite-form-wrap" class="wp-pq-panel wp-pq-manager-card" hidden>
        <form id="wp-pq-invite-form">
          <h3 style="margin:0 0 12px">New Invite</h3>
          <div class="wp-pq-manager-form-grid">
            <label>First Name <input type="text" name="first_name" required></label>
            <label>Last Name <input type="text" name="last_name" required></label>
            <label>Email <input type="email" name="email" required></label>
            <label>Role
              <select name="role" id="wp-pq-invite-role">
                <option value="pq_client">Client user</option>
                <option value="pq_worker">Team member</option>
              </select>
            </label>
            <label id="wp-pq-invite-client-wrap">Client
              <select name="client_id" id="wp-pq-invite-client" required>
                <option value="">Choose client</option>
                ${clientOptions(0, false)}
                <option value="new">+ New client</option>
              </select>
            </label>
            <label id="wp-pq-invite-new-client-wrap" hidden>Client name
              <input type="text" name="new_client_name" id="wp-pq-invite-new-client" placeholder="e.g. Acme Corp">
            </label>
            <label id="wp-pq-invite-client-role-wrap">Client role
              <select name="client_role">
                <option value="client_contributor">Client Contributor</option>
                <option value="client_admin">Client Admin</option>
                <option value="client_viewer">Client Viewer</option>
              </select>
            </label>
          </div>
          <div style="margin-top:12px;display:flex;gap:8px">
            <button class="button button-primary" type="submit">Send Invite</button>
            <button class="button" type="button" id="wp-pq-cancel-invite">Cancel</button>
          </div>
        </form>
      </div>
    `;
    var toggleBtn = document.getElementById('wp-pq-toggle-invite-form');
    var formWrap = document.getElementById('wp-pq-invite-form-wrap');
    var cancelBtn = document.getElementById('wp-pq-cancel-invite');
    var roleSelect = document.getElementById('wp-pq-invite-role');
    var clientWrap = document.getElementById('wp-pq-invite-client-wrap');
    var clientSelect = document.getElementById('wp-pq-invite-client');
    var clientRoleWrap = document.getElementById('wp-pq-invite-client-role-wrap');
    var newClientWrap = document.getElementById('wp-pq-invite-new-client-wrap');
    var newClientInput = document.getElementById('wp-pq-invite-new-client');

    function syncInviteRole() {
      var isClient = roleSelect.value === 'pq_client';
      clientWrap.hidden = !isClient;
      clientRoleWrap.hidden = !isClient;
      newClientWrap.hidden = !isClient || clientSelect.value !== 'new';
      if (clientSelect) clientSelect.required = isClient;
      if (newClientInput) newClientInput.required = isClient && clientSelect.value === 'new';
    }
    if (clientSelect) {
      clientSelect.addEventListener('change', syncInviteRole);
    }
    if (roleSelect) {
      roleSelect.addEventListener('change', syncInviteRole);
      syncInviteRole();
    }
    if (toggleBtn && formWrap) {
      toggleBtn.addEventListener('click', function () { formWrap.hidden = !formWrap.hidden; });
    }
    if (cancelBtn && formWrap) {
      cancelBtn.addEventListener('click', function () { formWrap.hidden = true; });
    }

    if (!invites.length) {
      el.managerContent.innerHTML = '<div class="wp-pq-empty-state">No invites sent yet.</div>';
      return;
    }

    el.managerContent.innerHTML = `
      <section class="wp-pq-panel wp-pq-manager-card">
        <table class="wp-pq-admin-table wp-pq-manager-table">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Client</th><th>Status</th><th>Delivery</th><th>Sent</th><th></th></tr></thead>
          <tbody>
            ${invites.map((inv) => {
              const statusClass = inv.status === 'accepted' ? 'wp-pq-status-done'
                : inv.status === 'pending' ? 'wp-pq-status-pending'
                : 'wp-pq-status-muted';
              const deliveryIcon = inv.delivery_status === 'sent' ? '<span title="Email sent" style="color:#16a34a">&#10003;</span>'
                : inv.delivery_status === 'failed' ? '<span title="Email failed" style="color:#dc2626">&#10007;</span>'
                : '<span title="Unknown" style="color:#9ca3af">&mdash;</span>';
              const roleLabel = inv.role === 'pq_worker' ? 'Team member' : 'Client ' + (inv.client_role || 'contributor').replace('client_', '').replace(/^\w/, c => c.toUpperCase());
              const fullName = [inv.first_name || '', inv.last_name || ''].join(' ').trim();
              var actions = '';
              if (inv.status === 'pending' || inv.status === 'expired') {
                actions += '<button class="button wp-pq-small-action" type="button" data-action="resend-invite" data-invite-id="' + inv.id + '">Resend</button> ';
              }
              if (inv.status === 'pending') {
                actions += '<button class="button wp-pq-small-action" type="button" data-action="copy-invite-link" data-invite-token="' + esc(inv.token || '') + '">Copy Link</button> ';
                actions += '<button class="button wp-pq-small-action" type="button" data-action="revoke-invite" data-invite-id="' + inv.id + '">Revoke</button>';
              }
              return '<tr>' +
                '<td>' + esc(fullName || '—') + '</td>' +
                '<td>' + esc(inv.email) + '</td>' +
                '<td>' + esc(roleLabel) + '</td>' +
                '<td>' + esc(inv.client_name || '—') + '</td>' +
                '<td><span class="' + statusClass + '">' + esc(inv.status) + '</span></td>' +
                '<td style="text-align:center">' + deliveryIcon + '</td>' +
                '<td>' + esc(String(inv.created_at || '').slice(0, 10)) + '</td>' +
                '<td class="wp-pq-invite-actions">' + actions + '</td>' +
              '</tr>';
            }).join('')}
          </tbody>
        </table>
      </section>
    `;
  }

  // ── AI Import ────────────────────────────────────────────────────────

  async function renderAiImport() {
    renderManagerFrame('ai-import');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading AI import…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    state.aiContext.client_id = Number(params.get('client_id') || state.aiContext.client_id || 0);
    state.aiContext.billing_bucket_id = Number(params.get('billing_bucket_id') || state.aiContext.billing_bucket_id || 0);
    const data = await api('manager/ai-import');
    state.aiPreview = data.preview || null;

    el.managerToolbar.innerHTML = `
      <form id="wp-pq-ai-import-form" class="wp-pq-manager-toolbar-grid" enctype="multipart/form-data">
        <label>Client
          <select name="client_id" id="wp-pq-ai-client" required>
            <option value="0">Choose client</option>
            ${clientOptions(state.aiPreview?.client_id || state.aiContext.client_id || 0, false)}
          </select>
        </label>
        <label>Job
          <select name="billing_bucket_id" id="wp-pq-ai-job">
            <option value="0">Auto-match jobs</option>
          </select>
        </label>
        <label class="wp-pq-span-2">Paste source text <textarea name="source_text" rows="5"></textarea></label>
        <label class="wp-pq-span-2">Or upload a source file <input type="file" name="source_file"></label>
        <button class="button button-primary wp-pq-span-2" type="submit">Parse with AI</button>
      </form>
    `;

    function renderPreview() {
      if (!state.aiPreview) {
        el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Parse a task list or upload a source document to start.</div>';
        return;
      }
      const preview = state.aiPreview;
      el.managerContent.innerHTML = `
        <section class="wp-pq-panel wp-pq-manager-card">
          <div class="wp-pq-section-heading">
            <div>
              <h3>${esc(preview.source_name || 'Parsed source')}</h3>
              <p class="wp-pq-panel-note">${esc(preview.summary || '')}</p>
            </div>
            <div class="wp-pq-manager-inline-actions">
              <button class="button" type="button" id="wp-pq-ai-revalidate">Revalidate Context</button>
              <button class="button button-primary" type="button" id="wp-pq-ai-import-confirm">Import Tasks</button>
              <button class="button wp-pq-secondary-action" type="button" id="wp-pq-ai-discard">Discard Preview</button>
            </div>
          </div>
          ${(preview.blocking_errors || []).length ? `<div class="wp-pq-manager-warning">${(preview.blocking_errors || []).map((message) => `<p>${esc(message)}</p>`).join('')}</div>` : ''}
          ${(preview.warning_messages || []).length ? `<div class="wp-pq-manager-notice">${(preview.warning_messages || []).map((message) => `<p>${esc(message)}</p>`).join('')}</div>` : ''}
          <label class="inline"><input type="checkbox" id="wp-pq-ai-confirm-jobs"${preview.requires_job_confirmation ? '' : ' hidden'}> Confirm new job creation for this import</label>
          <table class="wp-pq-admin-table wp-pq-manager-table">
            <thead><tr><th>Title</th><th>Job</th><th>Priority</th><th>Owner</th><th>Deadline</th><th>Billing</th><th>Status Hint</th></tr></thead>
            <tbody>
              ${(preview.tasks || []).map((task) => `
                <tr>
                  <td><strong>${esc(task.title || '')}</strong><div class="wp-pq-panel-note">${esc(task.description || '')}</div></td>
                  <td>${esc(task.job_label || '')}</td>
                  <td>${esc(task.priority || '')}</td>
                  <td>${esc(task.owner_label || '')}</td>
                  <td>${esc(task.deadline_label || '')}</td>
                  <td>${esc(task.is_billable === null ? 'auto' : task.is_billable ? 'billable' : 'not billable')}</td>
                  <td>${esc(task.status_hint || '')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </section>
      `;
    }

    renderPreview();
    if (state.aiPreview) {
      const importBtn = document.getElementById('wp-pq-ai-import-confirm');
      if (importBtn) importBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    const clientSelect = document.getElementById('wp-pq-ai-client');
    const jobSelect = document.getElementById('wp-pq-ai-job');
    function fillJobs() {
      if (!clientSelect || !jobSelect) return;
      jobSelect.innerHTML = '<option value="0">Auto-match jobs</option>' + clientJobOptions(clientSelect.value, state.aiPreview?.billing_bucket_id || state.aiContext.billing_bucket_id || 0, false);
    }
    if (clientSelect) {
      clientSelect.addEventListener('change', fillJobs);
      fillJobs();
    }
  }

  // ── Swimlanes Management ──────────────────────────────────────────────

  var lanesTasksCache = []; // cached task list for assignment modal

  async function renderLanes() {
    renderManagerFrame('lanes');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading lanes…</div>';

    // Fetch lanes and tasks in parallel.
    var results = await Promise.all([
      api('manager/lanes'),
      api('tasks'),
    ]);
    var lanes = results[0].lanes || [];
    var allTasks = results[1].tasks || [];
    lanesTasksCache = allTasks;

    // Count tasks per lane.
    var laneCounts = {};
    lanes.forEach(function (l) { laneCounts[l.id] = 0; });
    var uncatCount = 0;
    allTasks.forEach(function (t) {
      var lid = parseInt(t.lane_id || 0, 10);
      if (lid > 0 && laneCounts[lid] !== undefined) {
        laneCounts[lid]++;
      } else {
        uncatCount++;
      }
    });

    var html = '<div class="wp-pq-section-heading"><div><h3>Swimlanes</h3>' +
      '<p class="wp-pq-panel-note">Swimlanes add horizontal rows across the board to group tasks by category. ' +
      'Click a task count to assign or remove tasks from that lane.</p></div></div>';

    html += '<div class="wp-pq-lanes-manager">';

    // Add lane form.
    html += '<form class="wp-pq-lane-add-form" id="wp-pq-lane-add-form">' +
      '<input type="text" name="label" placeholder="New lane name…" class="regular-text" required autocomplete="off" />' +
      ' <label class="wp-pq-lane-visible-label"><input type="checkbox" name="client_visible" checked /> Visible to clients</label>' +
      ' <button type="submit" class="button button-primary">Add Lane</button>' +
      '</form>';

    // Lane list.
    if (!lanes.length) {
      html += '<p class="wp-pq-empty-state">No lanes yet. Create one above to start grouping tasks on the board.</p>';
    } else {
      html += '<table class="wp-pq-lanes-table widefat striped"><thead><tr>' +
        '<th>Lane</th><th>Client Visible</th><th>Tasks</th><th></th>' +
        '</tr></thead><tbody>';
      lanes.forEach(function (lane) {
        var count = laneCounts[lane.id] || 0;
        html += '<tr data-lane-id="' + lane.id + '">' +
          '<td><input type="text" class="wp-pq-lane-label-input" value="' + esc(lane.label) + '" data-lane-id="' + lane.id + '" /></td>' +
          '<td><input type="checkbox" class="wp-pq-lane-visible-toggle" data-lane-id="' + lane.id + '"' + (lane.client_visible ? ' checked' : '') + ' /></td>' +
          '<td><button type="button" class="button button-small wp-pq-lane-assign-btn" data-lane-id="' + lane.id + '" data-lane-label="' + esc(lane.label) + '">' + count + ' task' + (count !== 1 ? 's' : '') + '</button></td>' +
          '<td><button type="button" class="button button-small wp-pq-lane-delete" data-lane-id="' + lane.id + '">Delete</button></td>' +
          '</tr>';
      });
      html += '</tbody></table>';
      html += '<p class="wp-pq-lane-uncat-note">' + uncatCount + ' task' + (uncatCount !== 1 ? 's' : '') + ' not assigned to any lane.</p>';
    }

    // Assignment modal (hidden, reused).
    html += '<dialog id="wp-pq-lane-assign-dialog" class="wp-pq-lane-assign-dialog">' +
      '<div class="wp-pq-lane-assign-inner">' +
      '<h4 id="wp-pq-lane-assign-title">Assign tasks</h4>' +
      '<div class="wp-pq-lane-assign-search"><input type="text" id="wp-pq-lane-assign-search" placeholder="Filter tasks…" autocomplete="off" /></div>' +
      '<div class="wp-pq-lane-assign-list" id="wp-pq-lane-assign-list"></div>' +
      '<div class="wp-pq-lane-assign-actions">' +
      '<button type="button" class="button button-primary" id="wp-pq-lane-assign-save">Save</button>' +
      ' <button type="button" class="button" id="wp-pq-lane-assign-cancel">Cancel</button>' +
      '</div>' +
      '</div></dialog>';

    html += '</div>';
    el.managerContent.innerHTML = html;

    // Wire add form.
    var addForm = document.getElementById('wp-pq-lane-add-form');
    if (addForm) {
      addForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        var label = addForm.elements.label.value.trim();
        if (!label) return;
        var clientVisible = addForm.elements.client_visible.checked;
        await submitJson('manager/lanes', 'POST', { label: label, client_visible: clientVisible });
        toast('Lane created.');
        await renderLanes();
      });
    }

    // Wire inline rename (on blur).
    el.managerContent.querySelectorAll('.wp-pq-lane-label-input').forEach(function (input) {
      input.addEventListener('blur', async function () {
        var laneId = input.dataset.laneId;
        var newLabel = input.value.trim();
        if (!newLabel) return;
        await submitJson('manager/lanes/' + laneId, 'POST', { label: newLabel });
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
      });
    });

    // Wire client visible toggle.
    el.managerContent.querySelectorAll('.wp-pq-lane-visible-toggle').forEach(function (cb) {
      cb.addEventListener('change', async function () {
        var laneId = cb.dataset.laneId;
        await submitJson('manager/lanes/' + laneId, 'POST', { client_visible: cb.checked });
      });
    });

    // Wire delete buttons.
    el.managerContent.querySelectorAll('.wp-pq-lane-delete').forEach(function (btn) {
      btn.addEventListener('click', async function () {
        var laneId = btn.dataset.laneId;
        if (!confirm('Delete this lane? Tasks in it will move to Uncategorized.')) return;
        await api('manager/lanes/' + laneId, { method: 'DELETE' });
        toast('Lane deleted.');
        await renderLanes();
      });
    });

    // Wire assignment buttons.
    el.managerContent.querySelectorAll('.wp-pq-lane-assign-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openAssignDialog(parseInt(btn.dataset.laneId, 10), btn.dataset.laneLabel);
      });
    });
  }

  function openAssignDialog(laneId, laneLabel) {
    var dialog = document.getElementById('wp-pq-lane-assign-dialog');
    var titleEl = document.getElementById('wp-pq-lane-assign-title');
    var listEl = document.getElementById('wp-pq-lane-assign-list');
    var searchEl = document.getElementById('wp-pq-lane-assign-search');
    var saveBtn = document.getElementById('wp-pq-lane-assign-save');
    var cancelBtn = document.getElementById('wp-pq-lane-assign-cancel');
    if (!dialog || !listEl) return;

    titleEl.textContent = 'Assign tasks to "' + laneLabel + '"';
    searchEl.value = '';

    // Build task checklist sorted by title.
    var sorted = lanesTasksCache.slice().sort(function (a, b) {
      return (a.title || '').localeCompare(b.title || '');
    });

    function renderList(filter) {
      var query = (filter || '').toLowerCase();
      var html = '';
      sorted.forEach(function (task) {
        var title = task.title || 'Task #' + task.id;
        var subtitle = task.bucket_name || '';
        if (query && title.toLowerCase().indexOf(query) === -1 && subtitle.toLowerCase().indexOf(query) === -1) return;
        var checked = parseInt(task.lane_id || 0, 10) === laneId;
        html += '<label class="wp-pq-lane-assign-item">' +
          '<input type="checkbox" value="' + task.id + '"' + (checked ? ' checked' : '') + ' /> ' +
          '<span class="wp-pq-lane-assign-task-title">#' + esc(task.id) + ' ' + esc(title) + '</span>' +
          (subtitle ? '<span class="wp-pq-lane-assign-task-sub">' + esc(subtitle) + '</span>' : '') +
          '</label>';
      });
      if (!html) html = '<p class="wp-pq-empty-state">No tasks match.</p>';
      listEl.innerHTML = html;
    }

    renderList('');
    searchEl.addEventListener('input', function () { renderList(searchEl.value); });

    // Save handler.
    var onSave = async function () {
      var checked = listEl.querySelectorAll('input[type="checkbox"]:checked');
      var taskIds = [];
      checked.forEach(function (cb) { taskIds.push(parseInt(cb.value, 10)); });
      saveBtn.disabled = true;
      try {
        await submitJson('manager/lanes/' + laneId + '/assign', 'POST', { task_ids: taskIds });
        dialog.close();
        toast(taskIds.length + ' task(s) assigned to "' + laneLabel + '".');
        await renderLanes();
      } catch (err) {
        toast(err.message || 'Assignment failed.', true);
      } finally {
        saveBtn.disabled = false;
      }
    };

    // Clean up old listeners by cloning buttons.
    var newSave = saveBtn.cloneNode(true);
    saveBtn.parentNode.replaceChild(newSave, saveBtn);
    newSave.addEventListener('click', onSave);

    var newCancel = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
    newCancel.addEventListener('click', function () { dialog.close(); });

    dialog.showModal();
  }

  m.render.files = renderFiles;
  m.render.invites = renderInvites;
  m.render['ai-import'] = renderAiImport;
  m.render.lanes = renderLanes;

  // ── Boot ─────────────────────────────────────────────────────────────
  // This file loads last (all renderers are now registered).
  var params = new URLSearchParams(window.location.search);
  var initialSection = params.get('section') || 'queue';
  window.wpPqPortalManager.openSection(initialSection, { pushHistory: false });
})(window._pqMgr);
