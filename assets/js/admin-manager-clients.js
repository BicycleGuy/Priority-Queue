(function (m) {
  var state = m.state;
  var el = m.el;
  var esc = m.esc;
  var api = m.api;
  var submitJson = m.submitJson;
  var ensureClients = m.ensureClients;
  var sortedClients = m.sortedClients;
  var clientOptions = m.clientOptions;
  var memberOptions = m.memberOptions;
  var replaceSectionUrl = m.replaceSectionUrl;
  var renderManagerFrame = m.renderManagerFrame;

  async function renderClients() {
    renderManagerFrame('clients');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading clients…</div>';
    const data = await ensureClients();
    const linkable = data.linkable_users || [];
    const candidates = data.member_candidates || [];
    const params = new URLSearchParams(window.location.search);
    const queryClientId = Number(params.get('client_id') || 0);
    const search = String(state.clientsSearch || '').trim().toLowerCase();
    const allClients = sortedClients();
    const filteredClients = allClients.filter((client) => {
      if (!search) return true;
      const haystack = [
        client.name || '',
        client.email || '',
        client.label || '',
      ].join(' ').toLowerCase();
      return haystack.includes(search);
    });
    const selectedFromState = Number(state.selectedClientId || queryClientId || 0);
    const selectedClient = filteredClients.find((client) => Number(client.id) === selectedFromState)
      || allClients.find((client) => Number(client.id) === selectedFromState)
      || filteredClients[0]
      || allClients[0]
      || null;
    state.selectedClientId = Number(selectedClient?.id || 0);

    const clientTab = state.clientTab || 'overview';

    el.managerToolbar.innerHTML = `
      <div class="wp-pq-manager-toolbar-actions">
        <button class="button button-primary" type="button" data-action="open-create-client-modal">New Client</button>
        <button class="button" type="button" data-action="open-link-client-modal">Link User to Client</button>
      </div>
      <dialog id="wp-pq-create-client-dialog" class="wp-pq-modal-dialog">
        <form id="wp-pq-manager-create-client" class="wp-pq-modal-body" method="dialog">
          <h3>New Client</h3>
          <label>Name <input type="text" name="client_name" required></label>
          <label>Email <input type="email" name="client_email" required></label>
          <label>First job <input type="text" name="initial_bucket_name" placeholder="Main, Retainer, Launch"></label>
          <div class="wp-pq-modal-actions">
            <button class="button button-primary" type="submit">Create Client</button>
            <button class="button" type="button" data-action="close-dialog">Cancel</button>
          </div>
        </form>
      </dialog>
      <dialog id="wp-pq-link-client-dialog" class="wp-pq-modal-dialog">
        <form id="wp-pq-manager-link-client" class="wp-pq-modal-body" method="dialog">
          <h3>Link Existing User</h3>
          <label>User
            <select name="user_id" required>
              <option value="0">Choose user</option>
              ${memberOptions(linkable, 0)}
            </select>
          </label>
          <label>First job <input type="text" name="initial_bucket_name" placeholder="Main, Retainer, Launch"></label>
          <div class="wp-pq-modal-actions">
            <button class="button button-primary" type="submit">Link User</button>
            <button class="button" type="button" data-action="close-dialog">Cancel</button>
          </div>
        </form>
      </dialog>
    `;

    const browserList = filteredClients.map((client) => {
      const letter = String(client.name || '?').trim().charAt(0).toUpperCase() || '#';
      const unbilled = Number(client.unbilled_count || 0);
      return `
        <button class="wp-pq-manager-list-item wp-pq-client-browser-item${Number(client.id) === Number(selectedClient?.id || 0) ? ' is-active' : ''}" type="button" data-open-client="${client.id}" data-client-letter="${esc(letter)}">
          <strong>${esc(client.name || 'Client')}</strong>
          <span>${esc(client.email || 'No primary contact email')}</span>
          <small>${unbilled > 0 ? unbilled + ' unbilled' : 'Up to date'}</small>
        </button>
      `;
    }).join('');

    const activeLetters = new Set(filteredClients.map((client) => (String(client.name || '?').trim().charAt(0).toUpperCase() || '#')));
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ#'.split('');

    let detailHtml = '<div class="wp-pq-empty-state">Select a client to view details.</div>';
    if (selectedClient) {
      const members = selectedClient.members || [];
      const buckets = selectedClient.buckets || [];
      const clientMemberOptions = members.map((member) => `<option value="${member.user_id || member.id}"${Number(member.user_id || member.id) === Number(selectedClient.primary_contact_user_id || 0) ? ' selected' : ''}>${esc(member.name || member.email || 'Member')}</option>`).join('');

      // Tab: Overview
      const overviewTab = `
        <div class="wp-pq-client-tab-body" data-tab="overview"${clientTab !== 'overview' ? ' hidden' : ''}>
          <form class="wp-pq-inline-action-form" data-action="update-client" data-client-id="${selectedClient.id}">
            <label>Name <input type="text" name="name" value="${esc(selectedClient.name || '')}" required></label>
            <label>Primary contact
              <select name="primary_contact_user_id">
                <option value="0">Not set</option>
                ${clientMemberOptions}
              </select>
            </label>
            <button class="button" type="submit">Save</button>
          </form>
          <div class="wp-pq-client-stats">
            <div class="wp-pq-stat"><span class="wp-pq-stat-value">${Number(selectedClient.delivered_count || 0)}</span><span class="wp-pq-stat-label">Completed</span></div>
            <div class="wp-pq-stat"><span class="wp-pq-stat-value">${Number(selectedClient.unbilled_count || 0)}</span><span class="wp-pq-stat-label">Unbilled</span></div>
            <div class="wp-pq-stat"><span class="wp-pq-stat-value">${Number(selectedClient.work_log_count || 0)}</span><span class="wp-pq-stat-label">Work Statements</span></div>
            <div class="wp-pq-stat"><span class="wp-pq-stat-value">${Number(selectedClient.statement_count || 0)}</span><span class="wp-pq-stat-label">Invoice Drafts</span></div>
          </div>
        </div>
      `;

      // Tab: Members
      const memberRows = members.map((member) => `
        <tr>
          <td>${esc(member.name || '')}</td>
          <td>${esc(member.email || '')}</td>
          <td>${esc(member.role_label || member.role || '')}</td>
        </tr>
      `).join('');
      const membersTab = `
        <div class="wp-pq-client-tab-body" data-tab="members"${clientTab !== 'members' ? ' hidden' : ''}>
          <table class="wp-pq-admin-table wp-pq-manager-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
            <tbody>${memberRows || '<tr><td colspan="3">No members yet.</td></tr>'}</tbody>
          </table>
          <form class="wp-pq-inline-action-form" data-action="add-client-member" data-client-id="${selectedClient.id}">
            <label>
              <select name="user_id" required>
                <option value="0">Choose user</option>
                ${memberOptions(candidates, 0)}
              </select>
            </label>
            <label>
              <select name="client_role">
                <option value="client_contributor">Client Contributor</option>
                <option value="client_admin">Client Admin</option>
                <option value="client_viewer">Client Viewer</option>
              </select>
            </label>
            <button class="button" type="submit">Add Member</button>
          </form>
        </div>
      `;

      // Tab: Jobs
      const jobRows = buckets.map((bucket) => {
        const memberCount = members.filter((m) => (m.bucket_ids || []).map(Number).includes(Number(bucket.id))).length;
        const isEmpty = memberCount === 0;
        return `
          <div class="wp-pq-job-row wp-pq-manager-subcard">
            <div class="wp-pq-job-summary">
              <strong>${esc(bucket.bucket_name || 'Job')}</strong>
              <small>${bucket.is_default ? 'Default' : ''} · ${memberCount} member${memberCount !== 1 ? 's' : ''}</small>
            </div>
            <div class="wp-pq-job-actions">
              <button class="button wp-pq-secondary-action" type="button" data-action="delete-job" data-job-id="${bucket.id}"${!isEmpty ? ' disabled title="Remove all members first"' : ''}>Delete</button>
            </div>
          </div>
        `;
      }).join('');
      const jobsTab = `
        <div class="wp-pq-client-tab-body" data-tab="jobs"${clientTab !== 'jobs' ? ' hidden' : ''}>
          <form class="wp-pq-inline-action-form" data-action="create-job" data-client-id="${selectedClient.id}">
            <label><input type="text" name="bucket_name" placeholder="New job name" required></label>
            <button class="button" type="submit">Add Job</button>
          </form>
          <div class="wp-pq-bucket-list">${jobRows || '<p class="wp-pq-empty-state">No jobs yet.</p>'}</div>
        </div>
      `;

      // Tab: Access (matrix)
      const accessHeaderCells = buckets.map((bucket) => `<th class="wp-pq-access-col-head" title="${esc(bucket.bucket_name || '')}">${esc((bucket.bucket_name || '').length > 18 ? (bucket.bucket_name || '').slice(0, 16) + '…' : (bucket.bucket_name || ''))}</th>`).join('');
      const accessRows = members.map((member) => {
        const memberBucketIds = (member.bucket_ids || []).map(Number);
        const cells = buckets.map((bucket) => {
          const assigned = memberBucketIds.includes(Number(bucket.id));
          return `<td class="wp-pq-access-cell"><label class="wp-pq-access-toggle"><input type="checkbox" data-action="toggle-job-access" data-bucket-id="${bucket.id}" data-user-id="${member.user_id || member.id}" data-client-id="${selectedClient.id}" ${assigned ? 'checked' : ''}><span>${assigned ? '✓' : ''}</span></label></td>`;
        }).join('');
        return `<tr><td>${esc(member.name || '')}</td>${cells}</tr>`;
      }).join('');
      const accessTab = `
        <div class="wp-pq-client-tab-body" data-tab="access"${clientTab !== 'access' ? ' hidden' : ''}>
          ${members.length > 0 && buckets.length > 0 ? `
            <div class="wp-pq-access-matrix-wrap">
              <table class="wp-pq-admin-table wp-pq-manager-table wp-pq-access-matrix">
                <thead><tr><th>Member</th>${accessHeaderCells}</tr></thead>
                <tbody>${accessRows}</tbody>
              </table>
            </div>
          ` : '<p class="wp-pq-empty-state">Add members and jobs first to configure access.</p>'}
        </div>
      `;

      detailHtml = `
        <section class="wp-pq-panel wp-pq-manager-card" data-client-id="${selectedClient.id}">
          <div class="wp-pq-section-heading">
            <h3>${esc(selectedClient.name || 'Client')}</h3>
            <p class="wp-pq-panel-note">${esc(selectedClient.email || '')}</p>
          </div>
          <div class="wp-pq-client-tabs">
            <button type="button" class="wp-pq-client-tab-btn${clientTab === 'overview' ? ' is-active' : ''}" data-client-tab="overview">Overview</button>
            <button type="button" class="wp-pq-client-tab-btn${clientTab === 'members' ? ' is-active' : ''}" data-client-tab="members">Members</button>
            <button type="button" class="wp-pq-client-tab-btn${clientTab === 'jobs' ? ' is-active' : ''}" data-client-tab="jobs">Jobs</button>
            <button type="button" class="wp-pq-client-tab-btn${clientTab === 'access' ? ' is-active' : ''}" data-client-tab="access">Access</button>
          </div>
          ${overviewTab}
          ${membersTab}
          ${jobsTab}
          ${accessTab}
        </section>
      `;
    }

    el.managerContent.innerHTML = `
      <div class="wp-pq-manager-browser-layout">
        <section class="wp-pq-panel wp-pq-manager-browser-panel">
          <div class="wp-pq-manager-browser-head">
            <label>Find client
              <input type="search" id="wp-pq-client-browser-search" value="${esc(state.clientsSearch || '')}" placeholder="Search by client name">
            </label>
          </div>
          <div class="wp-pq-manager-browser-body">
            <div class="wp-pq-manager-list-panel wp-pq-client-browser-list">
              ${browserList || '<div class="wp-pq-empty-state">No clients yet.</div>'}
            </div>
            <div class="wp-pq-client-alpha-rail">
              ${letters.map((letter) => `<button type="button" class="button wp-pq-client-alpha-btn${activeLetters.has(letter) ? '' : ' is-dim'}" data-client-alpha="${esc(letter)}"${activeLetters.has(letter) ? '' : ' disabled'}>${esc(letter)}</button>`).join('')}
            </div>
          </div>
        </section>
        <div>${detailHtml}</div>
      </div>
    `;
  }

  m.render.clients = renderClients;
})(window._pqMgr);
