(function () {
  const managerConfig = window.wpPqManagerConfig || window.wpPqConfig;
  if (typeof managerConfig === 'undefined' || !managerConfig.isManager) {
    return;
  }

  const apiRoot = managerConfig.root;
  const headers = { 'X-WP-Nonce': managerConfig.nonce };
  const managerNav = document.getElementById('wp-pq-manager-nav');
  const managerPanel = document.getElementById('wp-pq-manager-panel');
  const managerTitle = document.getElementById('wp-pq-manager-panel-title');
  const managerNote = document.getElementById('wp-pq-manager-panel-note');
  const managerToolbar = document.getElementById('wp-pq-manager-toolbar');
  const managerContent = document.getElementById('wp-pq-manager-content');
  const queueBinderSections = document.getElementById('wp-pq-queue-binder-sections');
  const queueMainSections = document.getElementById('wp-pq-queue-main-sections');
  const prefPanel = document.getElementById('wp-pq-pref-panel');
  const taskDrawer = document.getElementById('wp-pq-task-drawer');
  const drawerBackdrop = document.getElementById('wp-pq-drawer-backdrop');
  const appShell = document.querySelector('.wp-pq-app-shell');
  const closePrefsBtn = document.getElementById('wp-pq-close-prefs');

  const state = {
    section: 'queue',
    lastNonPreferenceSection: 'queue',
    clients: null,
    selectedClientId: 0,
    clientsSearch: '',
    workLogMode: 'review',
    workLogDetail: null,
    statementDetail: null,
    invoiceDraftMode: 'review',
    invoiceDraftClientId: 0,
    aiPreview: null,
    aiContext: { client_id: 0, billing_bucket_id: 0 },
  };

  const sectionMeta = {
    queue: {
      title: 'Task Board',
      note: 'Active workflow stays here. Everything else now hangs off the same portal shell.',
    },
    clients: {
      title: 'Clients',
      note: 'Create client accounts, manage members, and control job access without leaving the portal.',
    },
    'billing-rollup': {
      title: 'Billing Rollup',
      note: 'Overview only. Completed work is grouped from ledger entries, and job corrections save automatically.',
    },
    'monthly-statements': {
      title: 'Monthly Statements',
      note: 'Read-only ledger reporting grouped by client, job, and month.',
    },
    'work-statements': {
      title: 'Work Statements',
      note: 'Optional frozen task snapshots for client-facing reporting.',
    },
    'invoice-drafts': {
      title: 'Invoice Drafts',
      note: 'Create, review, and send ledger-backed invoice drafts without the old admin clutter.',
    },
    'ai-import': {
      title: 'AI Import',
      note: 'Parse task lists, revalidate against client/job context, and import them into the queue.',
    },
    preferences: {
      title: 'Preferences',
      note: 'Preferences stays lightweight for now, with notifications first and more layout controls later.',
    },
  };

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function invoiceStatusLabel(status) {
    switch (String(status || '')) {
      case 'written_off': return 'No Charge';
      case 'unbilled': return 'Unbilled';
      case 'invoiced': return 'Invoiced';
      case 'paid': return 'Paid';
      default: return String(status || 'Unbilled').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }
  }

  function toast(message, isError) {
    if (window.wp?.data?.dispatch) {
      window.wp.data.dispatch('core/notices').createNotice(isError ? 'error' : 'success', message, { isDismissible: true });
      return;
    }
    window.alert(message);
  }

  function friendlyApiError(response, payload) {
    const code = String(payload && payload.code ? payload.code : '');
    const message = String(payload && payload.message ? payload.message : '');
    if (
      code === 'rest_cookie_invalid_nonce'
      || /cookie check failed/i.test(message)
      || (/nonce/i.test(message) && /invalid|expired|failed/i.test(message))
      || ((response.status === 401 || response.status === 403) && /cookie|nonce|rest/i.test(message))
    ) {
      return 'Your session expired. Refresh the page and try again.';
    }

    return message || 'Request failed.';
  }

  async function api(path, options) {
    const requestOptions = Object.assign({
      credentials: 'same-origin',
    }, options || {});
    requestOptions.headers = Object.assign({}, headers, (options && options.headers) || {});
    if (requestOptions.body instanceof FormData) {
      delete requestOptions.headers['Content-Type'];
    } else if (!requestOptions.headers['Content-Type']) {
      requestOptions.headers['Content-Type'] = 'application/json';
    }
    const response = await fetch(apiRoot + path.replace(/^\//, ''), requestOptions);

    let payload = null;
    const text = await response.text();
    try {
      payload = text ? JSON.parse(text) : {};
    } catch (error) {
      payload = { message: text || 'Request failed.' };
    }

    if (!response.ok) {
      throw new Error(friendlyApiError(response, payload));
    }

    return payload;
  }

  function sectionUrl(section) {
    const base = managerConfig.portalUrl || window.location.pathname;
    if (!section || section === 'queue') {
      return base;
    }
    const url = new URL(base, window.location.origin);
    url.searchParams.set('section', section);
    return url.toString();
  }

  function buildSectionUrl(section, params) {
    const url = new URL(sectionUrl(section), window.location.origin);
    Object.entries(params || {}).forEach(([key, value]) => {
      const normalized = value == null ? '' : String(value);
      if (normalized === '' || normalized === '0') {
        url.searchParams.delete(key);
        return;
      }
      url.searchParams.set(key, normalized);
    });
    return url;
  }

  function replaceSectionUrl(section, params) {
    const url = buildSectionUrl(section, params);
    window.history.replaceState({}, '', url.toString());
    return url;
  }

  function endOfMonth(monthValue) {
    if (!monthValue) return '';
    const base = new Date(`${monthValue}-01T00:00:00`);
    if (Number.isNaN(base.getTime())) return '';
    const end = new Date(base.getFullYear(), base.getMonth() + 1, 0);
    return [
      end.getFullYear(),
      String(end.getMonth() + 1).padStart(2, '0'),
      String(end.getDate()).padStart(2, '0'),
    ].join('-');
  }

  function workflowStatusLabel(status) {
    const labels = {
      pending_approval: 'Pending Approval',
      needs_clarification: 'Needs Clarification',
      approved: 'Approved',
      in_progress: 'In Progress',
      needs_review: 'Needs Review',
      delivered: 'Delivered',
      done: 'Done',
    };
    return labels[status] || String(status || '').replace(/_/g, ' ');
  }

  function reopenTargetOptions(selected) {
    return ['in_progress', 'needs_review', 'needs_clarification']
      .map((status) => `<option value="${status}"${selected === status ? ' selected' : ''}>${esc(workflowStatusLabel(status))}</option>`)
      .join('');
  }

  function setActiveNav(section) {
    if (!managerNav) return;
    managerNav.querySelectorAll('[data-pq-section]').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.pqSection === section);
    });
  }

  function closeDrawer() {
    if (taskDrawer) {
      taskDrawer.classList.remove('is-open');
      taskDrawer.setAttribute('aria-hidden', 'true');
    }
    if (drawerBackdrop) {
      drawerBackdrop.hidden = true;
    }
    if (appShell) {
      appShell.classList.remove('is-detail-focus');
    }
    document.body.classList.remove('wp-pq-drawer-open');
  }

  function showQueue(show) {
    if (queueBinderSections) {
      queueBinderSections.hidden = !show;
    }
    if (queueMainSections) {
      queueMainSections.hidden = !show;
    }
    if (!show) {
      closeDrawer();
    }
  }

  function showPreferences(show) {
    if (!prefPanel) return;
    prefPanel.hidden = !show;
  }

  function showManagerPanel(show) {
    if (managerPanel) {
      managerPanel.hidden = !show;
    }
  }

  function currentClients() {
    return state.clients && Array.isArray(state.clients.clients) ? state.clients.clients : [];
  }

  function clientOptions(selectedId, includeBlank) {
    const blank = includeBlank ? '<option value="0">All clients</option>' : '';
    return blank + currentClients().map((client) => `<option value="${client.id}"${Number(selectedId) === Number(client.id) ? ' selected' : ''}>${esc(client.name)}</option>`).join('');
  }

  function sortedClients() {
    return currentClients().slice().sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')));
  }

  function clientJobOptions(clientId, selectedId, includeBlank) {
    const client = currentClients().find((item) => Number(item.id) === Number(clientId));
    const jobs = client && Array.isArray(client.buckets) ? client.buckets : [];
    const blank = includeBlank ? '<option value="0">All jobs</option>' : '';
    return blank + jobs.map((job) => `<option value="${job.id}"${Number(selectedId) === Number(job.id) ? ' selected' : ''}>${esc(job.bucket_name || job.bucket_label || 'Job')}</option>`).join('');
  }

  function memberOptions(candidates, selectedId) {
    return (Array.isArray(candidates) ? candidates : []).map((candidate) => `<option value="${candidate.id}"${Number(selectedId) === Number(candidate.id) ? ' selected' : ''}>${esc(candidate.label)}</option>`).join('');
  }

  async function ensureClients() {
    if (!state.clients) {
      state.clients = await api('manager/clients');
    }
    return state.clients;
  }

  function renderEmpty(message) {
    managerToolbar.innerHTML = '';
    managerContent.innerHTML = `<div class="wp-pq-empty-state">${esc(message)}</div>`;
  }

  function renderManagerFrame(section) {
    const meta = sectionMeta[section] || sectionMeta.queue;
    managerTitle.textContent = meta.title;
    managerNote.textContent = meta.note;
  }

  async function renderClients() {
    renderManagerFrame('clients');
    managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading clients…</div>';
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

    managerToolbar.innerHTML = `
      <div class="wp-pq-manager-toolbar-grid">
        <form id="wp-pq-manager-create-client" class="wp-pq-panel wp-pq-manager-form-card">
          <h4>Create client</h4>
          <label>Name <input type="text" name="client_name" required></label>
          <label>Email <input type="text" name="client_email" required></label>
          <label>Initial job <input type="text" name="initial_bucket_name" placeholder="Main, Retainer, Launch"></label>
          <button class="button button-primary" type="submit">Create Client</button>
        </form>
        <form id="wp-pq-manager-link-client" class="wp-pq-panel wp-pq-manager-form-card">
          <h4>Link existing user</h4>
          <label>User
            <select name="user_id" required>
              <option value="0">Choose user</option>
              ${memberOptions(linkable, 0)}
            </select>
          </label>
          <label>Initial job <input type="text" name="initial_bucket_name" placeholder="Main, Retainer, Launch"></label>
          <button class="button" type="submit">Link Existing User</button>
        </form>
      </div>
    `;

    const browserList = filteredClients.map((client) => {
      const letter = String(client.name || '?').trim().charAt(0).toUpperCase() || '#';
      return `
        <button class="wp-pq-manager-list-item wp-pq-client-browser-item${Number(client.id) === Number(selectedClient?.id || 0) ? ' is-active' : ''}" type="button" data-open-client="${client.id}" data-client-letter="${esc(letter)}">
          <strong>${esc(client.name || 'Client')}</strong>
          <span>${esc(client.email || 'No primary contact email')}</span>
          <small>${letter} · ${Number(client.delivered_count || 0)} completed · ${Number(client.unbilled_count || 0)} unbilled</small>
        </button>
      `;
    }).join('');

    const activeLetters = new Set(filteredClients.map((client) => (String(client.name || '?').trim().charAt(0).toUpperCase() || '#')));
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ#'.split('');

    let detailHtml = '<div class="wp-pq-empty-state">No client matches that search.</div>';
    if (selectedClient) {
      const clientMemberOptions = (selectedClient.members || []).map((member) => `<option value="${member.user_id}"${Number(member.user_id) === Number(selectedClient.primary_contact_user_id || 0) ? ' selected' : ''}>${esc(member.name || member.email || 'Member')}</option>`).join('');
      const memberRows = (selectedClient.members || []).map((member) => `
        <tr>
          <td>${esc(member.name || '')}</td>
          <td>${esc(member.email || '')}</td>
          <td>${esc(member.role_label || member.role || '')}</td>
          <td>${esc((member.job_names || []).join(', ') || 'All default access')}</td>
        </tr>
      `).join('');
      const jobs = (selectedClient.buckets || []).map((bucket) => {
        const memberChoices = (selectedClient.members || []).map((member) => `<option value="${member.user_id}">${esc(member.name || member.email || 'Member')}</option>`).join('');
        return `
          <div class="wp-pq-job-row wp-pq-manager-subcard">
            <div class="wp-pq-job-summary">
              <strong>${esc(bucket.bucket_name || 'Job')}</strong>
              <p class="wp-pq-job-row-note">${bucket.is_default ? 'Default job' : 'Secondary job'}${bucket.description ? ' · ' + esc(bucket.description) : ''}</p>
            </div>
            <form class="wp-pq-inline-action-form wp-pq-job-assign-form" data-action="assign-job-member" data-bucket-id="${bucket.id}" data-client-id="${selectedClient.id}">
              <label>
                <select name="user_id" required>
                  <option value="0">Choose member</option>
                  ${memberChoices}
                </select>
              </label>
              <button class="button" type="submit">Assign to Job</button>
              <button class="button wp-pq-secondary-action" type="button" data-action="delete-job" data-job-id="${bucket.id}">Delete Empty Job</button>
            </form>
          </div>
        `;
      }).join('');

      detailHtml = `
        <section class="wp-pq-panel wp-pq-manager-card" data-client-id="${selectedClient.id}">
          <div class="wp-pq-section-heading">
            <div>
              <h3>${esc(selectedClient.name || 'Client')}</h3>
              <p class="wp-pq-panel-note">${esc(selectedClient.email || 'No primary contact email')} · Completed work ${Number(selectedClient.delivered_count || 0)} · Unbilled ${Number(selectedClient.unbilled_count || 0)} · Work Statements ${Number(selectedClient.work_log_count || 0)} · Invoice Drafts ${Number(selectedClient.statement_count || 0)}</p>
            </div>
          </div>
          <div class="wp-pq-manager-card-grid">
            <div>
              <h4>Client Details</h4>
              <form class="wp-pq-inline-action-form" data-action="update-client" data-client-id="${selectedClient.id}">
                <label><input type="text" name="name" value="${esc(selectedClient.name || '')}" required></label>
                <label>
                  <select name="primary_contact_user_id">
                    <option value="0">Primary contact</option>
                    ${clientMemberOptions}
                  </select>
                </label>
                <button class="button" type="submit">Save Client</button>
              </form>
              <h4>Members</h4>
              <table class="wp-pq-admin-table wp-pq-manager-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Jobs</th></tr></thead>
                <tbody>${memberRows || '<tr><td colspan="4">No members yet.</td></tr>'}</tbody>
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
                    <option value="client_contributor">Client contributor</option>
                    <option value="client_admin">Client admin</option>
                    <option value="client_viewer">Client viewer</option>
                  </select>
                </label>
                <button class="button" type="submit">Add Member</button>
              </form>
            </div>
            <div>
              <h4>Jobs</h4>
              <form class="wp-pq-inline-action-form" data-action="create-job" data-client-id="${selectedClient.id}">
                <label><input type="text" name="bucket_name" placeholder="Add a new job" required></label>
                <button class="button" type="submit">Add Job</button>
              </form>
              <div class="wp-pq-bucket-list">${jobs || '<p class="wp-pq-empty-state">No jobs yet.</p>'}</div>
            </div>
          </div>
        </section>
      `;
    }

    managerContent.innerHTML = `
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

  async function renderBillingRollup() {
    renderManagerFrame('billing-rollup');
    managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading billing rollup…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const month = params.get('month') || new Date().toISOString().slice(0, 7);
    const clientId = params.get('client_id') || '0';
    const data = await api(`manager/rollups?month=${encodeURIComponent(month)}&client_id=${encodeURIComponent(clientId)}`);

    managerToolbar.innerHTML = `
      <form id="wp-pq-rollup-filter-form" class="wp-pq-period-form">
        <label>Month <input type="month" name="month" value="${esc(data.range.month)}"></label>
        <label>Client
          <select name="client_id">${clientOptions(clientId, true)}</select>
        </label>
      </form>
    `;

    managerContent.innerHTML = (data.groups || []).map((group) => `
      <section class="wp-pq-panel wp-pq-manager-card">
        <div class="wp-pq-section-heading">
          <div>
            <h3>${esc(group.client_name || 'Client')} · ${esc(group.bucket_name || 'Job')}</h3>
            <p class="wp-pq-panel-note">${Number((group.entries || []).length)} completed work entries · ${Number(group.invoice_ready_count || 0)} invoice-ready</p>
          </div>
        </div>
        <table class="wp-pq-admin-table wp-pq-manager-table">
          <thead><tr><th>Date</th><th>Title</th><th>Owner</th><th>Status</th><th>Job</th></tr></thead>
          <tbody>
            ${(group.entries || []).map((entry) => `
              <tr>
                <td>${esc(String(entry.completion_date || '').slice(0, 10))}</td>
                <td>
                  <strong>${esc(entry.title_snapshot || '')}</strong>
                  <div class="wp-pq-panel-note">${esc(entry.work_summary || '')}</div>
                </td>
                <td>${esc(entry.owner_name || 'Unassigned')}</td>
                <td>${esc(entry.invoice_status || 'unbilled')}</td>
                <td>
                  <select
                    class="wp-pq-rollup-job-select"
                    data-action="assign-rollup-job"
                    data-ledger-entry-id="${entry.ledger_entry_id}"
                    data-task-id="${entry.task_id || 0}"
                    data-current-bucket-id="${entry.billing_bucket_id || 0}"
                  >${(group.bucket_options || []).map((bucket) => `<option value="${bucket.id}"${Number(bucket.id) === Number(entry.billing_bucket_id) ? ' selected' : ''}>${esc(bucket.bucket_name || 'Job')}</option>`).join('')}</select>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </section>
    `).join('') || '<div class="wp-pq-empty-state">No completed work in this period.</div>';
  }

  function monthlyStatementCsv(groups) {
    const rows = [['Client', 'Job', 'Month', 'Completion Date', 'Title', 'Work Summary', 'Owner', 'Billing Mode', 'Billing Category', 'Hours', 'Rate', 'Amount', 'Invoice Status']];
    groups.forEach((group) => {
      (group.entries || []).forEach((entry) => {
        rows.push([
          group.client_name || '',
          group.job_name || '',
          group.month || '',
          String(entry.completion_date || '').slice(0, 10),
          entry.title_snapshot || '',
          entry.work_summary || '',
          entry.owner_name || '',
          entry.billing_mode || '',
          entry.billing_category || '',
          entry.hours || '',
          entry.rate || '',
          entry.amount || '',
          invoiceStatusLabel(entry.invoice_status),
        ]);
      });
    });
    return rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
  }

  function downloadCsv(filename, contents) {
    const blob = new Blob([contents], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  function printHtml(title, html) {
    const win = window.open('', '_blank', 'width=1100,height=900');
    if (!win) return;
    win.document.write(`<!doctype html><html><head><title>${esc(title)}</title><style>body{font-family:ui-sans-serif,system-ui,sans-serif;padding:24px;color:#172033}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d7deea;padding:8px;text-align:left;vertical-align:top}h1,h2,h3{margin:0 0 12px}section{margin-bottom:22px}.note{color:#5d6880;font-size:13px}</style></head><body>${html}</body></html>`);
    win.document.close();
    win.focus();
    win.print();
  }

  async function renderMonthlyStatements() {
    renderManagerFrame('monthly-statements');
    managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading monthly statements…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const month = params.get('month') || new Date().toISOString().slice(0, 7);
    const clientId = params.get('client_id') || '0';
    const jobId = params.get('billing_bucket_id') || '0';
    const status = params.get('invoice_status') || '';
    const data = await api(`manager/monthly-statements?month=${encodeURIComponent(month)}&client_id=${encodeURIComponent(clientId)}&billing_bucket_id=${encodeURIComponent(jobId)}&invoice_status=${encodeURIComponent(status)}`);

    managerToolbar.innerHTML = `
      <form id="wp-pq-monthly-filter-form" class="wp-pq-period-form">
        <label>Month <input type="month" name="month" value="${esc(data.month)}"></label>
        <label>Client
          <select name="client_id">${clientOptions(clientId, true)}</select>
        </label>
        <label>Job
          <select name="billing_bucket_id" id="wp-pq-monthly-job-filter">${Number(clientId || 0) > 0 ? clientJobOptions(clientId, jobId, true) : '<option value="0">All jobs</option>'}</select>
        </label>
        <label>Invoice status
          <select name="invoice_status">
            <option value="">All statuses</option>
            <option value="unbilled"${status === 'unbilled' ? ' selected' : ''}>Unbilled</option>
            <option value="invoiced"${status === 'invoiced' ? ' selected' : ''}>Invoiced</option>
            <option value="paid"${status === 'paid' ? ' selected' : ''}>Paid</option>
            <option value="written_off"${status === 'written_off' ? ' selected' : ''}>No Charge</option>
          </select>
        </label>
        <button class="button wp-pq-secondary-action" type="button" id="wp-pq-monthly-export-csv">Export CSV</button>
        <button class="button wp-pq-secondary-action" type="button" id="wp-pq-monthly-print">Print PDF</button>
      </form>
    `;

    managerContent.innerHTML = (data.groups || []).map((group) => `
      <section class="wp-pq-panel wp-pq-manager-card">
        <div class="wp-pq-section-heading">
          <div>
            <h3>${esc(group.client_name || 'Client')} · ${esc(group.job_name || 'Job')}</h3>
            <p class="wp-pq-panel-note">${esc(group.month || data.month)} · Unbilled ${Number(group.counts?.unbilled || 0)} · Invoiced ${Number(group.counts?.invoiced || 0)} · Paid ${Number(group.counts?.paid || 0)} · No Charge ${Number(group.counts?.written_off || 0)}</p>
          </div>
        </div>
        <table class="wp-pq-admin-table wp-pq-manager-table">
          <thead><tr><th>Date</th><th>Work</th><th>Owner</th><th>Billing</th><th>Hours / Rate / Amount</th><th>Invoice Status</th></tr></thead>
          <tbody>
            ${(group.entries || []).map((entry) => `
              <tr>
                <td>${esc(String(entry.completion_date || '').slice(0, 10))}</td>
                <td><strong>${esc(entry.title_snapshot || '')}</strong><div class="wp-pq-panel-note">${esc(entry.work_summary || '')}</div></td>
                <td>${esc(entry.owner_name || 'Unassigned')}</td>
                <td>${esc(entry.billing_mode || '')} · ${esc(entry.billing_category || '')}</td>
                <td>${esc(entry.hours || '')} ${entry.hours ? 'hrs · ' : ''}${esc(entry.rate || '')}${entry.rate ? ' rate · ' : ''}${esc(entry.amount || '')}</td>
                <td>${esc(invoiceStatusLabel(entry.invoice_status))}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </section>
    `).join('') || '<div class="wp-pq-empty-state">No ledger entries matched those filters.</div>';

    const clientFilter = managerToolbar.querySelector('select[name="client_id"]');
    const jobFilter = document.getElementById('wp-pq-monthly-job-filter');
    if (clientFilter && jobFilter) {
      const syncJobFilter = () => {
        jobFilter.innerHTML = Number(clientFilter.value || 0) > 0
          ? clientJobOptions(clientFilter.value, jobFilter.value || jobId, true)
          : '<option value="0">All jobs</option>';
      };
      clientFilter.addEventListener('change', syncJobFilter);
      syncJobFilter();
    }

    const csvBtn = document.getElementById('wp-pq-monthly-export-csv');
    if (csvBtn) {
      csvBtn.onclick = () => downloadCsv(`monthly-statements-${data.month}.csv`, monthlyStatementCsv(data.groups || []));
    }
    const printBtn = document.getElementById('wp-pq-monthly-print');
    if (printBtn) {
      printBtn.onclick = () => {
        const html = (data.groups || []).map((group) => `
          <section>
            <h2>${esc(group.client_name || 'Client')} · ${esc(group.job_name || 'Job')}</h2>
            <p class="note">${esc(group.month || data.month)}</p>
            <table>
              <thead><tr><th>Date</th><th>Work</th><th>Owner</th><th>Billing</th><th>Hours / Rate / Amount</th><th>Status</th></tr></thead>
              <tbody>${(group.entries || []).map((entry) => `
                <tr>
                  <td>${esc(String(entry.completion_date || '').slice(0, 10))}</td>
                  <td><strong>${esc(entry.title_snapshot || '')}</strong><div>${esc(entry.work_summary || '')}</div></td>
                  <td>${esc(entry.owner_name || 'Unassigned')}</td>
                  <td>${esc(entry.billing_mode || '')} · ${esc(entry.billing_category || '')}</td>
                  <td>${esc(entry.hours || '')} ${entry.hours ? 'hrs ' : ''}${esc(entry.rate || '')} ${entry.rate ? 'rate ' : ''}${esc(entry.amount || '')}</td>
                  <td>${esc(invoiceStatusLabel(entry.invoice_status))}</td>
                </tr>
              `).join('')}</tbody>
            </table>
          </section>
        `).join('');
        printHtml(`Monthly Statements ${data.month}`, `<h1>Monthly Statements ${esc(data.month)}</h1>${html}`);
      };
    }
  }

  function workStatementCsv(workLog) {
    const rows = [['Work Statement Code', 'Client', 'Jobs', 'Range Start', 'Range End', 'Task ID', 'Task Title', 'Job', 'Status', 'Updated At', 'Billing Status']];
    (workLog.tasks || []).forEach((task) => {
      rows.push([
        workLog.work_log_code || '',
        workLog.client_name || '',
        workLog.job_summary || '',
        workLog.range_start || '',
        workLog.range_end || '',
        task.id || '',
        task.title || '',
        task.bucket_name || '',
        task.status || '',
        task.updated_at || '',
        task.billing_status || '',
      ]);
    });
    return rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
  }

  function statementCsv(statement) {
    const rows = [[
      'Draft Code',
      'Client',
      'Period',
      'Line Type',
      'Description',
      'Quantity',
      'Unit',
      'Unit Rate',
      'Amount',
      'Job',
      'Linked Task IDs',
      'Notes',
    ]];
    (statement.lines || []).forEach((line) => {
      rows.push([
        statement.statement_code || '',
        statement.client_name || '',
        statement.statement_month || '',
        line.line_type || '',
        line.description || '',
        line.quantity || '',
        line.unit || '',
        line.unit_rate || '',
        line.line_amount || '',
        line.bucket_name || statement.job_summary || '',
        Array.isArray(line.linked_task_ids) ? line.linked_task_ids.join('|') : (line.linked_task_ids || ''),
        line.notes || '',
      ]);
    });
    return rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
  }

  async function renderWorkStatements(selectedId) {
    renderManagerFrame('work-statements');
    managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading work statements…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const month = params.get('month') || new Date().toISOString().slice(0, 7);
    const rangeStart = params.get('start_date') || `${month}-01`;
    const rangeEnd = params.get('end_date') || endOfMonth(month);
    const data = await api(`manager/work-logs?start_date=${encodeURIComponent(rangeStart)}&end_date=${encodeURIComponent(rangeEnd)}`);
    const workLogs = Array.isArray(data.work_logs) ? data.work_logs : [];
    const mode = state.workLogMode === 'create' ? 'create' : 'review';
    const selectedWorkLogId = mode === 'create'
      ? 0
      : Number(selectedId || params.get('work_log_id') || workLogs[0]?.id || 0);
    const defaultClientId = Number(params.get('client_id') || currentClients()[0]?.id || 0);

    managerToolbar.innerHTML = `
      <form id="wp-pq-work-log-filter-form" class="wp-pq-period-form">
        <label>Start <input type="date" name="start_date" value="${esc(data.range.start)}" required></label>
        <label>End <input type="date" name="end_date" value="${esc(data.range.end)}" required></label>
        <div class="wp-pq-manager-inline-actions">
          ${mode === 'create'
            ? '<button class="button wp-pq-secondary-action" type="button" data-action="cancel-create-work-log">Cancel</button>'
            : '<button class="button button-primary" type="button" data-action="start-create-work-log">New Work Statement</button>'}
        </div>
      </form>
    `;

    const logsHtml = `
      <div class="wp-pq-manager-list-head">
        <h3>Saved Work Statements</h3>
        <p class="wp-pq-panel-note">Frozen client-facing snapshots in this range.</p>
      </div>
      ${workLogs.map((log) => `
        <button class="wp-pq-manager-list-item${Number(selectedWorkLogId) === Number(log.id) ? ' is-active' : ''}" type="button" data-open-work-log="${log.id}">
          <strong>${esc(log.work_log_code || 'Work Statement')}</strong>
          <span>${esc(log.client_name || '')}${log.job_summary ? ` · ${esc(log.job_summary)}` : ''}</span>
          <small>${esc(log.range_start || '')} to ${esc(log.range_end || '')} · ${Number(log.task_count || 0)} tasks</small>
        </button>
      `).join('') || '<div class="wp-pq-empty-state">No work statements in this range yet.</div>'}
    `;

    let detailHtml = `
      <section class="wp-pq-panel wp-pq-manager-card wp-pq-manager-subcard">
        <div class="wp-pq-empty-state">Select a work statement to inspect it.</div>
      </section>
    `;

    if (mode === 'create') {
      detailHtml = `
        <section class="wp-pq-panel wp-pq-manager-card wp-pq-manager-form-card wp-pq-work-log-composer">
          <div class="wp-pq-section-heading">
            <div>
              <h3>Create Work Statement</h3>
              <p class="wp-pq-panel-note">Capture one frozen client snapshot across a date range, chosen jobs, and selected workflow statuses.</p>
            </div>
          </div>
          <form id="wp-pq-work-log-create-form" class="wp-pq-manager-card">
            <div class="wp-pq-manager-card-grid">
              <label>Client
                <select name="client_id" id="wp-pq-work-log-client" required>${clientOptions(defaultClientId, false)}</select>
              </label>
              <label>Start <input type="date" name="range_start" value="${esc(data.range.start)}" required></label>
              <label>End <input type="date" name="range_end" value="${esc(data.range.end)}" required></label>
              <label class="wp-pq-span-2">Jobs
                <select name="job_ids" id="wp-pq-work-log-jobs" class="wp-pq-manager-multiselect" multiple size="5"></select>
              </label>
              <fieldset class="wp-pq-manager-statuses wp-pq-span-2">
                <legend>Status filters</legend>
                <div class="wp-pq-manager-status-grid">
                  ${['pending_approval', 'needs_clarification', 'approved', 'in_progress', 'needs_review', 'delivered', 'done'].map((statusKey) => `
                    <label class="wp-pq-manager-status-option">
                      <input type="checkbox" name="statuses" value="${statusKey}"${['delivered', 'done'].includes(statusKey) ? ' checked' : ''}>
                      <span>${esc(workflowStatusLabel(statusKey))}</span>
                    </label>
                  `).join('')}
                </div>
              </fieldset>
              <label class="wp-pq-span-2">Notes
                <textarea name="notes" rows="4" placeholder="Optional context for the client-facing snapshot."></textarea>
              </label>
            </div>
            <div class="wp-pq-manager-inline-actions">
              <button class="button button-primary" type="submit">Create Work Statement</button>
            </div>
          </form>
        </section>
      `;
      state.workLogDetail = null;
    } else if (selectedWorkLogId) {
      const detailResponse = await api(`manager/work-logs/${selectedWorkLogId}`);
      state.workLogDetail = detailResponse.work_log;
      const workLog = state.workLogDetail;
      detailHtml = `
        <section class="wp-pq-panel wp-pq-manager-card">
          <div class="wp-pq-section-heading">
            <div>
              <h3>${esc(workLog.work_log_code || 'Work Statement')}</h3>
              <p class="wp-pq-panel-note">${esc(workLog.client_name || '')}${workLog.job_summary ? ` · ${esc(workLog.job_summary)}` : ''}</p>
            </div>
            <div class="wp-pq-manager-inline-actions">
              <button class="button wp-pq-secondary-action" type="button" id="wp-pq-work-log-export">Export CSV</button>
              <button class="button wp-pq-secondary-action" type="button" id="wp-pq-work-log-print">Print PDF</button>
            </div>
          </div>
          <div class="wp-pq-chip-row">
            <span class="wp-pq-chip">${esc(workLog.range_start || '')} to ${esc(workLog.range_end || '')}</span>
            <span class="wp-pq-chip">${Number((workLog.tasks || []).length)} tasks</span>
          </div>
          <form id="wp-pq-work-log-update-form" class="wp-pq-manager-card">
            <label>Notes <textarea name="notes" rows="3">${esc(workLog.notes || '')}</textarea></label>
            <div class="wp-pq-manager-inline-actions">
              <button class="button" type="submit">Save Notes</button>
            </div>
          </form>
          <table class="wp-pq-admin-table wp-pq-manager-table">
            <thead><tr><th>Task</th><th>Status</th><th>Job</th><th>Updated</th><th>Billing</th></tr></thead>
            <tbody>
              ${(workLog.tasks || []).map((task) => `
                <tr>
                  <td><strong>${esc(task.title || '')}</strong><div class="wp-pq-panel-note">${esc(task.description || '')}</div></td>
                  <td>${esc(workflowStatusLabel(task.status || ''))}</td>
                  <td>${esc(task.bucket_name || '')}</td>
                  <td>${esc(task.updated_at || task.created_at || '')}</td>
                  <td>${esc(task.billing_status || '')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </section>
      `;
    } else {
      state.workLogDetail = null;
    }

    managerContent.innerHTML = `
      <div class="wp-pq-manager-split">
        <div class="wp-pq-panel wp-pq-manager-list-panel">${logsHtml}</div>
        <div>${detailHtml}</div>
      </div>
    `;

    const clientSelect = document.getElementById('wp-pq-work-log-client');
    const jobSelect = document.getElementById('wp-pq-work-log-jobs');
    function fillJobs() {
      if (!clientSelect || !jobSelect) return;
      jobSelect.innerHTML = clientJobOptions(clientSelect.value, 0, false);
    }
    if (clientSelect) {
      clientSelect.addEventListener('change', fillJobs);
      fillJobs();
    }

    if (state.workLogDetail) {
      const exportBtn = document.getElementById('wp-pq-work-log-export');
      const printBtn = document.getElementById('wp-pq-work-log-print');
      if (exportBtn) exportBtn.onclick = () => downloadCsv(`${state.workLogDetail.work_log_code || 'work-statement'}.csv`, workStatementCsv(state.workLogDetail));
      if (printBtn) printBtn.onclick = () => printHtml(state.workLogDetail.work_log_code || 'Work Statement', managerContent.querySelector('.wp-pq-manager-card').outerHTML);
    }
  }

  async function renderInvoiceDrafts(selectedId) {
    renderManagerFrame('invoice-drafts');
    managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading invoice drafts…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const period = params.get('period') || new Date().toISOString().slice(0, 7);
    const queryStatementId = Number(params.get('statement_id') || 0);
    const queryClientId = Number(params.get('client_id') || 0);
    const mode = state.invoiceDraftMode === 'create' ? 'create' : 'review';
    const data = await api(`manager/statements?period=${encodeURIComponent(period)}`);
    const statements = Array.isArray(data.statements) ? data.statements : [];
    const selectedStatementId = mode === 'create'
      ? 0
      : Number(selectedId || queryStatementId || statements[0]?.id || 0);
    const composerClientId = Number(queryClientId || state.invoiceDraftClientId || 0);

    managerToolbar.innerHTML = `
      <form id="wp-pq-statement-period-form" class="wp-pq-period-form">
        <label>Period <input type="month" name="period" value="${esc(data.period)}"></label>
        <div class="wp-pq-manager-inline-actions">
          ${mode === 'create'
            ? '<button class="button wp-pq-secondary-action" type="button" data-action="cancel-create-statement">Cancel</button>'
            : '<button class="button button-primary" type="button" data-action="start-create-statement">New Invoice Draft</button>'}
        </div>
      </form>
    `;

    const draftsHtml = statements.map((statement) => `
      <button class="wp-pq-manager-list-item${Number(selectedStatementId) === Number(statement.id) ? ' is-active' : ''}" type="button" data-open-statement="${statement.id}">
        <strong>${esc(statement.statement_code || 'Invoice Draft')}</strong>
        <span>${esc(statement.client_name || '')} · ${esc(statement.job_summary || '')}</span>
        <small>${esc(statement.created_at || '')} · ${Number(statement.entry_count || 0)} entries · ${statement.payment_status || 'unpaid'}${Number(statement.entry_count || 0) === 0 ? ' · Empty draft' : ''}</small>
      </button>
    `).join('') || '<div class="wp-pq-empty-state">No invoice drafts for this period yet.</div>';

    let detailHtml = '<div class="wp-pq-empty-state">Select an invoice draft to review it, or start a new one.</div>';
    state.statementDetail = null;
    if (mode === 'create') {
      state.invoiceDraftClientId = composerClientId;
      const eligibleEntries = (data.unbilled_entries || []).filter((entry) => Number(entry.client_id || 0) === composerClientId);
      detailHtml = `
        <section class="wp-pq-panel wp-pq-manager-card">
          <div class="wp-pq-section-heading">
            <div>
              <h3>Create Invoice Draft</h3>
              <p class="wp-pq-panel-note">Pick a client, choose billable completed work, and create a draft only when there is something real to send.</p>
            </div>
          </div>
          <form id="wp-pq-statement-create-form" class="wp-pq-manager-toolbar-grid">
            <input type="hidden" name="statement_month" value="${esc(data.period)}">
            <label>Client
              <select name="client_id" id="wp-pq-statement-client" required>
                <option value="0">Choose client</option>
                ${clientOptions(composerClientId, false)}
              </select>
            </label>
            <label class="wp-pq-span-2">Notes <textarea name="notes" rows="3"></textarea></label>
            <div class="wp-pq-panel wp-pq-manager-subcard wp-pq-span-2">
              <strong>Eligible completed work</strong>
              <div class="wp-pq-manager-entry-list">
                ${composerClientId <= 0
                  ? '<div class="wp-pq-empty-state">Choose a client to see billable completed work for this period.</div>'
                  : eligibleEntries.length
                    ? eligibleEntries.map((entry) => `
                        <label class="wp-pq-manager-checkbox-row" data-client-id="${entry.client_id}">
                          <input type="checkbox" name="entry_ids" value="${entry.id}">
                          <span><strong>${esc(entry.title || '')}</strong><small>${esc(entry.bucket_name || '')} · ${esc(String(entry.completion_date || '').slice(0, 10))}</small></span>
                        </label>
                      `).join('')
                    : '<div class="wp-pq-empty-state">No billable completed work is ready for this client in this period.</div>'}
              </div>
            </div>
            <button class="button button-primary" type="submit"${composerClientId <= 0 || eligibleEntries.length === 0 ? ' disabled' : ''}>Create Invoice Draft</button>
          </form>
        </section>
      `;
    } else if (selectedStatementId > 0) {
      const detailResponse = await api(`manager/statements/${selectedStatementId}`);
      state.statementDetail = detailResponse.statement;
      const statement = state.statementDetail;
      detailHtml = `
        <section class="wp-pq-panel wp-pq-manager-card">
          <div class="wp-pq-section-heading">
            <div>
              <h3>${esc(statement.statement_code || 'Invoice Draft')}</h3>
              <p class="wp-pq-panel-note">${esc(statement.client_name || '')} · ${esc(statement.job_summary || '')} · ${esc(statement.statement_month || '')} · ${esc(statement.payment_status || 'unpaid')} · Total ${esc(statement.total_amount || '0.00')}</p>
            </div>
            <div class="wp-pq-manager-inline-actions">
              <button class="button wp-pq-secondary-action" type="button" id="wp-pq-statement-export">Export CSV</button>
              <button class="button wp-pq-secondary-action" type="button" id="wp-pq-statement-print">Print PDF</button>
              <button class="button" type="button" data-action="email-statement"${statement.client_email ? '' : ' disabled'}>${statement.client_email ? 'Email Client' : 'No Client Email'}</button>
              <button class="button" type="button" data-action="toggle-payment" data-payment-status="${statement.payment_status === 'paid' ? 'unpaid' : 'paid'}">${statement.payment_status === 'paid' ? 'Mark Unpaid' : 'Mark Paid'}</button>
              <button class="button wp-pq-secondary-action" type="button" data-action="delete-statement">Delete Draft</button>
            </div>
          </div>
          <form id="wp-pq-statement-update-form" class="wp-pq-manager-detail-copy">
            <p><strong>Client email:</strong> ${esc(statement.client_email || 'No client email on file')}</p>
            <label><strong>Notes</strong> <textarea name="notes" rows="2">${esc(statement.notes || '')}</textarea></label>
            <div class="wp-pq-manager-inline-actions">
              <button class="button" type="submit">Save Notes</button>
            </div>
          </form>
          <div class="wp-pq-manager-split wp-pq-manager-split-tight">
            <section class="wp-pq-panel wp-pq-manager-subcard">
              <h4>Line items</h4>
              <table class="wp-pq-admin-table wp-pq-manager-table">
                <thead><tr><th>Description</th><th>Type</th><th>Qty</th><th>Rate</th><th>Amount</th><th></th></tr></thead>
                <tbody>
                  ${(statement.lines || []).length ? (statement.lines || []).map((line) => `
                    <tr>
                      <td>${esc(line.description || '')}<div class="wp-pq-panel-note">${esc(line.notes || '')}</div></td>
                      <td>${esc(line.line_type || '')}</td>
                      <td>${esc(line.quantity || '')} ${esc(line.unit || '')}</td>
                      <td>${esc(line.unit_rate || '')}</td>
                      <td>${esc(line.line_amount || '')}</td>
                      <td><button class="button wp-pq-secondary-action" type="button" data-action="delete-line" data-line-id="${line.id}">Remove</button></td>
                    </tr>
                  `).join('') : '<tr><td colspan="6">This draft has no line items yet.</td></tr>'}
                </tbody>
              </table>
              <form id="wp-pq-statement-line-form" class="wp-pq-inline-action-form">
                <label>Description <input type="text" name="description" required></label>
                <label>Type
                  <select name="line_type">
                    <option value="hours">Hours</option>
                    <option value="fixed">Fixed</option>
                    <option value="expense">Expense</option>
                  </select>
                </label>
                <label>Qty <input type="number" name="quantity" step="any" value="1"></label>
                <label>Rate <input type="number" name="unit_rate" step="any" value="0"></label>
                <label>Amount <input type="number" name="line_amount" step="any" value="0"></label>
                <button class="button" type="submit">Add Line</button>
              </form>
            </section>
            <section class="wp-pq-panel wp-pq-manager-subcard">
              <h4>Linked completed work</h4>
              <table class="wp-pq-admin-table wp-pq-manager-table">
                <thead><tr><th>Task</th><th>Status</th><th>Job</th><th></th></tr></thead>
                <tbody>
                  ${(statement.tasks || []).length ? (statement.tasks || []).map((task) => `
                    <tr>
                      <td><strong>${esc(task.title || '')}</strong></td>
                      <td>${esc(task.status || '')}</td>
                      <td>${esc(task.bucket_name || '')}</td>
                      <td><button class="button wp-pq-secondary-action" type="button" data-action="remove-task" data-task-id="${task.id}">Remove</button></td>
                    </tr>
                  `).join('') : '<tr><td colspan="4">No completed work entries are linked to this draft.</td></tr>'}
                </tbody>
              </table>
            </section>
          </div>
        </section>
      `;
    }

    managerContent.innerHTML = `
      <div class="wp-pq-manager-split">
        <div class="wp-pq-panel wp-pq-manager-list-panel">${draftsHtml}</div>
        <div>${detailHtml}</div>
      </div>
    `;

    if (state.statementDetail) {
      const exportBtn = document.getElementById('wp-pq-statement-export');
      const printBtn = document.getElementById('wp-pq-statement-print');
      if (exportBtn) {
        exportBtn.onclick = () => downloadCsv(`${state.statementDetail.statement_code || 'invoice-draft'}.csv`, statementCsv(state.statementDetail));
      }
      if (printBtn) {
        printBtn.onclick = () => {
          const lineRows = (state.statementDetail.lines || []).map((line) => `
            <tr>
              <td>${esc(line.description || '')}</td>
              <td>${esc(line.line_type || '')}</td>
              <td>${esc(line.quantity || '')} ${esc(line.unit || '')}</td>
              <td>${esc(line.unit_rate || '')}</td>
              <td>${esc(line.line_amount || '')}</td>
            </tr>
          `).join('');
          printHtml(
            state.statementDetail.statement_code || 'Invoice Draft',
            `
              <h1>${esc(state.statementDetail.statement_code || 'Invoice Draft')}</h1>
              <p class="note">${esc(state.statementDetail.client_name || '')} · ${esc(state.statementDetail.job_summary || '')} · ${esc(state.statementDetail.statement_month || '')} · ${esc(state.statementDetail.payment_status || 'unpaid')}</p>
              <p class="note">${esc(state.statementDetail.notes || '')}</p>
              <table>
                <thead><tr><th>Description</th><th>Type</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                <tbody>${lineRows || '<tr><td colspan="5">No line items.</td></tr>'}</tbody>
              </table>
            `
          );
        };
      }
    }
  }

  async function renderAiImport() {
    renderManagerFrame('ai-import');
    managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading AI import…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    state.aiContext.client_id = Number(params.get('client_id') || state.aiContext.client_id || 0);
    state.aiContext.billing_bucket_id = Number(params.get('billing_bucket_id') || state.aiContext.billing_bucket_id || 0);
    const data = await api('manager/ai-import');
    state.aiPreview = data.preview || null;

    managerToolbar.innerHTML = `
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
        managerContent.innerHTML = '<div class="wp-pq-empty-state">Parse a task list or upload a source document to start.</div>';
        return;
      }
      const preview = state.aiPreview;
      managerContent.innerHTML = `
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

  async function openSection(section, options) {
    const config = options && typeof options === 'object' && !Array.isArray(options)
      ? options
      : { pushHistory: options !== false };
    state.section = section || 'queue';
    if (state.section !== 'preferences') {
      state.lastNonPreferenceSection = state.section;
    }
    setActiveNav(state.section);
    if (state.section === 'ai-import') {
      if (config.clientId !== undefined) {
        state.aiContext.client_id = Number(config.clientId || 0);
      }
      if (config.billingBucketId !== undefined) {
        state.aiContext.billing_bucket_id = Number(config.billingBucketId || 0);
      }
    }

    if (config.pushHistory !== false) {
      const query = Object.assign({}, config.query || {});
      if (state.section === 'ai-import') {
        query.client_id = state.aiContext.client_id || 0;
        query.billing_bucket_id = state.aiContext.billing_bucket_id || 0;
      }
      replaceSectionUrl(state.section, query);
    }

    if (state.section === 'queue') {
      showQueue(true);
      showManagerPanel(false);
      showPreferences(false);
      return;
    }

    if (state.section === 'preferences') {
      showQueue(false);
      showManagerPanel(false);
      showPreferences(true);
      if (window.wpPqAlerts && typeof window.wpPqAlerts.openPreferences === 'function') {
        await window.wpPqAlerts.openPreferences();
      }
      return;
    }

    showQueue(false);
    showPreferences(false);
    showManagerPanel(true);

    try {
      if (state.section === 'clients') {
        await renderClients();
      } else if (state.section === 'billing-rollup') {
        await renderBillingRollup();
      } else if (state.section === 'monthly-statements') {
        await renderMonthlyStatements();
      } else if (state.section === 'work-statements') {
        const params = new URLSearchParams(window.location.search);
        await renderWorkStatements(Number(params.get('work_log_id') || 0));
      } else if (state.section === 'invoice-drafts') {
        const params = new URLSearchParams(window.location.search);
        await renderInvoiceDrafts(Number(params.get('statement_id') || 0));
      } else if (state.section === 'ai-import') {
        await renderAiImport();
      }
    } catch (error) {
      managerToolbar.innerHTML = '';
      managerContent.innerHTML = `<div class="wp-pq-empty-state">${esc(error.message || 'Section failed to load.')}</div>`;
    }
  }

  async function submitJson(path, method, body) {
    return api(path, {
      method: method || 'POST',
      headers: Object.assign({}, headers, { 'Content-Type': 'application/json' }),
      body: JSON.stringify(body || {}),
    });
  }

  managerNav?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-pq-section]');
    if (!button) return;
    event.preventDefault();
    if (button.dataset.pqSection === 'work-statements') {
      state.workLogMode = 'review';
    }
    if (button.dataset.pqSection === 'invoice-drafts') {
      state.invoiceDraftMode = 'review';
    }
    openSection(button.dataset.pqSection);
  });

  closePrefsBtn?.addEventListener('click', (event) => {
    if (state.section !== 'preferences') return;
    event.preventDefault();
    openSection(state.lastNonPreferenceSection || 'queue').catch((error) => {
      toast(error.message || 'Preferences failed to close cleanly.', true);
    });
  });

  managerToolbar?.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('button');
    if (!button) return;
    const action = button.dataset.action;

    try {
      if (action === 'start-create-work-log') {
        state.workLogMode = 'create';
        await renderWorkStatements();
        return;
      }
      if (action === 'cancel-create-work-log') {
        state.workLogMode = 'review';
        const params = new URLSearchParams(window.location.search);
        replaceSectionUrl('work-statements', {
          work_log_id: params.get('work_log_id') || '',
          start_date: params.get('start_date') || '',
          end_date: params.get('end_date') || '',
        });
        await renderWorkStatements();
        return;
      }
      if (action === 'start-create-statement') {
        state.invoiceDraftMode = 'create';
        await renderInvoiceDrafts();
        return;
      }
      if (action === 'cancel-create-statement') {
        state.invoiceDraftMode = 'review';
        state.invoiceDraftClientId = 0;
        replaceSectionUrl('invoice-drafts', { period: new URLSearchParams(window.location.search).get('period') || new Date().toISOString().slice(0, 7) });
        await renderInvoiceDrafts();
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  managerToolbar?.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    event.preventDefault();
    try {
      if (form.id === 'wp-pq-manager-create-client') {
        await submitJson('manager/clients', 'POST', Object.fromEntries(new FormData(form).entries()));
        state.clients = null;
        await renderClients();
        toast('Client created.', false);
        return;
      }
      if (form.id === 'wp-pq-manager-link-client') {
        await submitJson('manager/clients', 'POST', Object.fromEntries(new FormData(form).entries()));
        state.clients = null;
        await renderClients();
        toast('Existing user linked as client.', false);
        return;
      }
      if (form.id === 'wp-pq-rollup-filter-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('billing-rollup', data);
        await renderBillingRollup();
        return;
      }
      if (form.id === 'wp-pq-monthly-filter-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('monthly-statements', data);
        await renderMonthlyStatements();
        return;
      }
      if (form.id === 'wp-pq-work-log-filter-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('work-statements', data);
        await renderWorkStatements();
        return;
      }
      if (form.id === 'wp-pq-statement-create-form') {
        const formData = new FormData(form);
        const payload = {
          client_id: Number(formData.get('client_id') || 0),
          statement_month: formData.get('statement_month'),
          notes: formData.get('notes'),
          entry_ids: Array.from(form.querySelectorAll('input[name="entry_ids"]:checked')).map((input) => Number(input.value || 0)).filter(Boolean),
        };
        if (payload.client_id <= 0 || !payload.entry_ids.length) {
          throw new Error('Choose a client and at least one eligible completed work entry.');
        }
        const response = await submitJson('manager/statements', 'POST', payload);
        const statementId = response?.statement?.id || response?.statement?.statement_id || response?.statement?.id;
        state.invoiceDraftMode = 'review';
        state.invoiceDraftClientId = 0;
        replaceSectionUrl('invoice-drafts', statementId ? { statement_id: statementId } : {});
        await renderInvoiceDrafts(statementId || 0);
        toast(response.message || 'Invoice Draft created.', false);
        return;
      }
      if (form.id === 'wp-pq-ai-import-form') {
        const formData = new FormData(form);
        const response = await api('manager/ai-import/parse', {
          method: 'POST',
          headers,
          body: formData,
        });
        state.aiPreview = response.preview || null;
        await renderAiImport();
        const taskCount = (response.preview?.tasks || []).length;
        toast((response.message || 'Parsed.') + (taskCount > 0 ? ' Review below, then click Import Tasks.' : ''), false);
        return;
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  managerToolbar?.addEventListener('change', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement)) return;
    const form = target.form;
    if (!(form instanceof HTMLFormElement)) return;

    try {
      if (form.id === 'wp-pq-rollup-filter-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('billing-rollup', data);
        await renderBillingRollup();
        return;
      }
      if (form.id === 'wp-pq-monthly-filter-form') {
        if (target.name === 'client_id') {
          const jobFilter = form.querySelector('select[name="billing_bucket_id"]');
          if (jobFilter) {
            jobFilter.innerHTML = Number(target.value || 0) > 0
              ? clientJobOptions(target.value, 0, true)
              : '<option value="0">All jobs</option>';
          }
        }
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('monthly-statements', data);
        await renderMonthlyStatements();
        return;
      }
      if (form.id === 'wp-pq-work-log-filter-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('work-statements', data);
        await renderWorkStatements();
        return;
      }
      if (form.id === 'wp-pq-statement-period-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('invoice-drafts', data);
        await renderInvoiceDrafts();
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  managerContent?.addEventListener('click', async (event) => {
    const button = event.target.closest('button');
    if (!button) return;
    const action = button.dataset.action;
    try {
      if (button.dataset.openClient) {
        state.selectedClientId = Number(button.dataset.openClient || 0);
        replaceSectionUrl('clients', state.selectedClientId > 0 ? { client_id: state.selectedClientId } : {});
        await renderClients();
        return;
      }
      if (button.dataset.clientAlpha) {
        const alpha = String(button.dataset.clientAlpha || '').toUpperCase();
        const listEl = document.querySelector('.wp-pq-client-browser-list');
        if (listEl) {
          const target = listEl.querySelector(`[data-client-letter="${alpha}"]`);
          if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
        }
        // highlight active letter
        document.querySelectorAll('.wp-pq-client-alpha-btn').forEach((btn) => btn.classList.toggle('is-active', String(btn.dataset.clientAlpha || '').toUpperCase() === alpha));
        return;
      }
      if (button.dataset.openWorkLog) {
        state.workLogMode = 'review';
        await openSection('work-statements', { query: { work_log_id: Number(button.dataset.openWorkLog || 0) } });
        return;
      }
      if (button.dataset.openStatement) {
        state.invoiceDraftMode = 'review';
        await openSection('invoice-drafts', { query: { statement_id: Number(button.dataset.openStatement || 0) } });
        return;
      }
      if (action === 'delete-job') {
        if (!window.confirm('Delete this job if it is empty and has no dependencies?')) return;
        await api(`manager/jobs/${button.dataset.jobId}`, { method: 'DELETE', headers });
        state.clients = null;
        await renderClients();
        toast('Job deleted.', false);
        return;
      }
      if (action === 'assign-rollup-job') {
        return;
      }
      if (action === 'toggle-payment' && state.statementDetail) {
        const response = await submitJson(`manager/statements/${state.statementDetail.id}/payment`, 'POST', { payment_status: button.dataset.paymentStatus });
        await renderInvoiceDrafts(state.statementDetail.id);
        toast(response.message || 'Invoice draft payment state updated.', false);
        return;
      }
      if (action === 'email-statement' && state.statementDetail) {
        const response = await submitJson(`manager/statements/${state.statementDetail.id}/email-client`, 'POST', {});
        toast(response.message || 'Invoice draft email sent.', false);
        return;
      }
      if (action === 'delete-statement' && state.statementDetail) {
        if (!window.confirm('Delete this invoice draft and restore entry eligibility?')) return;
        await api(`manager/statements/${state.statementDetail.id}`, { method: 'DELETE', headers });
        state.invoiceDraftMode = 'review';
        replaceSectionUrl('invoice-drafts', {});
        await renderInvoiceDrafts();
        toast('Invoice Draft deleted.', false);
        return;
      }
      if (action === 'delete-line' && state.statementDetail) {
        await api(`manager/statements/${state.statementDetail.id}/lines/${button.dataset.lineId}`, { method: 'DELETE', headers });
        await renderInvoiceDrafts(state.statementDetail.id);
        toast('Invoice draft line removed.', false);
        return;
      }
      if (action === 'remove-task' && state.statementDetail) {
        await api(`manager/statements/${state.statementDetail.id}/tasks/${button.dataset.taskId}`, { method: 'DELETE', headers });
        await renderInvoiceDrafts(state.statementDetail.id);
        toast('Task removed from invoice draft.', false);
        return;
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  managerContent?.addEventListener('change', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement || target instanceof HTMLInputElement)) return;

    try {
      if (target.id === 'wp-pq-statement-client') {
        state.invoiceDraftClientId = Number(target.value || 0);
        replaceSectionUrl('invoice-drafts', state.invoiceDraftClientId > 0 ? { client_id: state.invoiceDraftClientId, period: new URLSearchParams(window.location.search).get('period') || new Date().toISOString().slice(0, 7) } : { period: new URLSearchParams(window.location.search).get('period') || new Date().toISOString().slice(0, 7) });
        await renderInvoiceDrafts();
        return;
      }
      if (target.matches('.wp-pq-rollup-job-select')) {
        const ledgerEntryId = Number(target.dataset.ledgerEntryId || 0);
        const taskId = Number(target.dataset.taskId || 0);
        const billingBucketId = Number(target.value || 0);
        const currentBucketId = Number(target.dataset.currentBucketId || 0);
        if (billingBucketId <= 0 || billingBucketId === currentBucketId) return;
        await submitJson('manager/rollups/assign-job', 'POST', {
          ledger_entry_id: ledgerEntryId,
          task_id: taskId,
          billing_bucket_id: billingBucketId,
        });
        target.dataset.currentBucketId = String(billingBucketId);
        toast('Completed work job updated.', false);
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  managerContent?.addEventListener('input', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.id !== 'wp-pq-client-browser-search') return;

    state.clientsSearch = target.value || '';
    await renderClients();
    const searchInput = document.getElementById('wp-pq-client-browser-search');
    if (searchInput) {
      searchInput.focus();
      const length = String(state.clientsSearch || '').length;
      searchInput.setSelectionRange(length, length);
    }
  });

  managerContent?.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    event.preventDefault();
    try {
      if (form.dataset.action === 'add-client-member') {
        await submitJson(`manager/clients/${form.dataset.clientId}/members`, 'POST', Object.fromEntries(new FormData(form).entries()));
        state.clients = null;
        await renderClients();
        toast('Client member saved.', false);
        return;
      }
      if (form.dataset.action === 'update-client') {
        await submitJson(`manager/clients/${form.dataset.clientId}`, 'POST', Object.fromEntries(new FormData(form).entries()));
        state.clients = null;
        await renderClients();
        toast('Client details updated.', false);
        return;
      }
      if (form.dataset.action === 'create-job') {
        await submitJson('manager/jobs', 'POST', { client_id: Number(form.dataset.clientId || 0), bucket_name: new FormData(form).get('bucket_name') });
        state.clients = null;
        await renderClients();
        toast('Job saved.', false);
        return;
      }
      if (form.dataset.action === 'assign-job-member') {
        await submitJson(`manager/jobs/${form.dataset.bucketId}/members`, 'POST', { user_id: Number(new FormData(form).get('user_id') || 0) });
        state.clients = null;
        await renderClients();
        toast('Job access saved.', false);
        return;
      }
      if (form.dataset.action === 'assign-rollup-job') {
        await submitJson('manager/rollups/assign-job', 'POST', Object.fromEntries(new FormData(form).entries()));
        await renderBillingRollup();
        toast('Completed work job updated.', false);
        return;
      }
      if (form.dataset.action === 'reopen-completed-task') {
        const taskId = Number(form.dataset.taskId || 0);
        const formData = new FormData(form);
        const targetStatus = String(formData.get('target_status') || 'in_progress');
        try {
          const response = await submitJson(`tasks/${taskId}/reopen-completed`, 'POST', { target_status: targetStatus });
          toast(response.message || 'Completed task reopened.', false);
          if (state.section === 'monthly-statements') {
            await renderMonthlyStatements();
          } else if (state.section === 'invoice-drafts' && state.statementDetail) {
            await renderInvoiceDrafts(state.statementDetail.id);
          }
          return;
        } catch (error) {
          if (!/follow-up/i.test(error.message || '')) {
            throw error;
          }
          if (!window.confirm(`${error.message}\n\nCreate a follow-up task instead?`)) {
            return;
          }
          const followup = await submitJson(`tasks/${taskId}/followup`, 'POST', { target_status: targetStatus });
          toast(followup.message || 'Follow-up task created.', false);
          if (state.section === 'monthly-statements') {
            await renderMonthlyStatements();
          } else if (state.section === 'invoice-drafts' && state.statementDetail) {
            await renderInvoiceDrafts(state.statementDetail.id);
          }
          return;
        }
      }
      if (form.id === 'wp-pq-work-log-create-form') {
        const formData = new FormData(form);
        const payload = {
          client_id: Number(formData.get('client_id') || 0),
          range_start: formData.get('range_start'),
          range_end: formData.get('range_end'),
          notes: formData.get('notes'),
          job_ids: Array.from(form.querySelector('#wp-pq-work-log-jobs')?.selectedOptions || []).map((option) => Number(option.value || 0)).filter(Boolean),
          statuses: Array.from(form.querySelectorAll('input[name="statuses"]:checked')).map((input) => input.value),
        };
        const response = await submitJson('manager/work-logs', 'POST', payload);
        const workLogId = Number(response?.work_log?.id || 0);
        state.workLogMode = 'review';
        replaceSectionUrl('work-statements', workLogId > 0 ? { work_log_id: workLogId } : {});
        await renderWorkStatements(workLogId);
        toast(response.message || 'Work statement created.', false);
        return;
      }
      if (form.id === 'wp-pq-work-log-update-form' && state.workLogDetail) {
        await submitJson(`manager/work-logs/${state.workLogDetail.id}`, 'POST', Object.fromEntries(new FormData(form).entries()));
        await renderWorkStatements(state.workLogDetail.id);
        toast('Work statement updated.', false);
        return;
      }
      if (form.id === 'wp-pq-statement-update-form' && state.statementDetail) {
        await submitJson(`manager/statements/${state.statementDetail.id}`, 'POST', Object.fromEntries(new FormData(form).entries()));
        await renderInvoiceDrafts(state.statementDetail.id);
        toast('Invoice draft updated.', false);
        return;
      }
      if (form.id === 'wp-pq-statement-line-form' && state.statementDetail) {
        await submitJson(`manager/statements/${state.statementDetail.id}/lines`, 'POST', Object.fromEntries(new FormData(form).entries()));
        await renderInvoiceDrafts(state.statementDetail.id);
        toast('Invoice draft line added.', false);
        return;
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  managerContent?.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    try {
      if (target.id === 'wp-pq-ai-revalidate') {
        const clientId = Number(document.getElementById('wp-pq-ai-client')?.value || state.aiPreview?.client_id || 0);
        const jobId = Number(document.getElementById('wp-pq-ai-job')?.value || state.aiPreview?.billing_bucket_id || 0);
        state.aiContext = { client_id: clientId, billing_bucket_id: jobId };
        replaceSectionUrl('ai-import', { client_id: clientId, billing_bucket_id: jobId });
        const response = await submitJson('manager/ai-import/revalidate', 'POST', { client_id: clientId, billing_bucket_id: jobId });
        state.aiPreview = response.preview || null;
        await renderAiImport();
        toast(response.message || 'Preview context updated.', false);
        return;
      }
      if (target.id === 'wp-pq-ai-import-confirm') {
        const clientId = Number(document.getElementById('wp-pq-ai-client')?.value || state.aiPreview?.client_id || 0);
        const jobId = Number(document.getElementById('wp-pq-ai-job')?.value || state.aiPreview?.billing_bucket_id || 0);
        const confirmNewJobs = !!document.getElementById('wp-pq-ai-confirm-jobs')?.checked;
        state.aiContext = { client_id: clientId, billing_bucket_id: jobId };
        const response = await submitJson('manager/ai-import/import', 'POST', { client_id: clientId, billing_bucket_id: jobId, confirm_new_jobs: confirmNewJobs });
        state.aiPreview = null;
        await renderAiImport();
        toast(response.message || 'Tasks imported.', false);
        return;
      }
      if (target.id === 'wp-pq-ai-discard') {
        const response = await submitJson('manager/ai-import/discard', 'POST', {});
        state.aiPreview = null;
        await renderAiImport();
        toast(response.message || 'Preview discarded.', false);
        return;
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  window.wpPqPortalManager = {
    openSection,
  };

  const params = new URLSearchParams(window.location.search);
  const initialSection = params.get('section') || 'queue';
  openSection(initialSection, { pushHistory: false });
})();
