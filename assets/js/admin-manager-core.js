(function () {
  const managerConfig = window.wpPqManagerConfig || window.wpPqConfig;
  if (typeof managerConfig === 'undefined' || !managerConfig.isManager) {
    return;
  }

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
  const closeDocsBtn = document.getElementById('wp-pq-close-docs');

  const state = {
    section: 'queue',
    lastNonPreferenceSection: 'queue',
    clients: null,
    selectedClientId: 0,
    clientsSearch: '',
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
      title: 'Clients and Jobs',
      note: 'Create client accounts, manage members, and control job access without leaving the portal.',
    },
    'billing-setup': {
      title: 'Billing Setup',
      note: 'Configure billing mode and rate for each client and job.',
    },
    'billing-queue': {
      title: 'Billing Queue',
      note: 'Review delivered work, make billing decisions, and track payment status.',
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
    documents: {
      title: 'Documents',
      note: 'Upload, browse, and manage files across all tasks.',
    },
    files: {
      title: 'Files & Links',
      note: 'Browse external file links attached to tasks, filterable by client and job.',
    },
    invites: {
      title: 'Invites',
      note: 'Send magic-link invitations and track who has joined.',
    },
  };

  const esc = window.wpPqPortalUI.escapeHtml;
  const api = window.wpPqPortalUI.api;

  function rowsToCsv(rows) {
    return rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
  }

  function invoiceStatusLabel(status) {
    switch (String(status || '')) {
      case 'pending_review': return 'Pending Review';
      case 'billable': return 'Billable';
      case 'no_charge': return 'No Charge';
      case 'invoiced': return 'Invoiced';
      case 'paid': return 'Paid';
      default: return String(status || 'Pending Review').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }
  }

  function toast(message, isError) {
    // Use the portal's auto-dismissing toast stack
    if (window.wpPqPortalUI?.alert) {
      window.wpPqPortalUI.alert(message, isError ? 'error' : 'success');
      return;
    }
    // Fallback: create a simple self-dismissing toast
    var stack = document.getElementById('wp-pq-toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'wp-pq-toast-stack';
      stack.className = 'wp-pq-toast-stack';
      document.body.appendChild(stack);
    }
    var el = document.createElement('div');
    el.className = 'wp-pq-toast ' + (isError ? 'error' : 'success');
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(function () { el.remove(); }, 3200);
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

  function formatDate(value) {
    if (!value) return '';
    const d = new Date(value.replace(' ', 'T') + (value.includes('T') || value.includes('+') ? '' : 'Z'));
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
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

  function setActiveNav(section) {
    // Update admin nav buttons (inside <details>)
    if (managerNav) {
      managerNav.querySelectorAll('[data-pq-section]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.pqSection === section);
      });
    }
    // Update standalone Queue button (outside admin nav)
    var queueBtn = document.getElementById('wp-pq-nav-queue');
    if (queueBtn) {
      queueBtn.classList.toggle('is-active', section === 'queue');
    }
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

  function renderManagerFrame(section) {
    const meta = sectionMeta[section] || sectionMeta.queue;
    managerTitle.textContent = meta.title;
    managerNote.textContent = meta.note;
  }

  function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
  }

  // Documents panel + Uppy removed — file exchange replaced by link field.

  async function submitJson(path, method, body) {
    return api(path, {
      method: method || 'POST',
      body: JSON.stringify(body || {}),
    });
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

  // ── Expose shared namespace ──────────────────────────────────────────
  window._pqMgr = {
    state,
    el: { managerToolbar, managerContent, managerNav, managerPanel, managerTitle, managerNote },
    esc,
    rowsToCsv,
    api,
    submitJson,
    toast,
    ensureClients,
    clientOptions,
    clientJobOptions,
    memberOptions,
    currentClients,
    sortedClients,
    replaceSectionUrl,
    invoiceStatusLabel,
    workflowStatusLabel,
    formatDate,
    endOfMonth,
    formatBytes,
    downloadCsv,
    printHtml,
    renderManagerFrame,
    render: {},
  };

  // ── Router ───────────────────────────────────────────────────────────
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
      if (state.section === 'work-statements') {
        const params = new URLSearchParams(window.location.search);
        const renderer = _pqMgr.render['work-statements'];
        if (renderer) await renderer(Number(params.get('work_log_id') || 0));
      } else if (state.section === 'invoice-drafts') {
        const params = new URLSearchParams(window.location.search);
        const renderer = _pqMgr.render['invoice-drafts'];
        if (renderer) await renderer(Number(params.get('statement_id') || 0));
      } else {
        const renderer = _pqMgr.render[state.section];
        if (renderer) await renderer();
      }
    } catch (error) {
      managerToolbar.innerHTML = '';
      managerContent.innerHTML = `<div class="wp-pq-empty-state">${esc(error.message || 'Section failed to load.')}</div>`;
    }
  }

  // ── Event listeners ──────────────────────────────────────────────────

  managerNav?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-pq-section]');
    if (!button) return;
    event.preventDefault();
    if (button.dataset.pqSection === 'invoice-drafts') {
      state.invoiceDraftMode = 'review';
    }
    openSection(button.dataset.pqSection);
  });

  // Standalone Queue button (outside admin nav <details>).
  var queueBtn = document.getElementById('wp-pq-nav-queue');
  if (queueBtn) {
    queueBtn.addEventListener('click', function (event) {
      event.preventDefault();
      openSection('queue');
    });
  }

  closePrefsBtn?.addEventListener('click', (event) => {
    if (state.section !== 'preferences') return;
    event.preventDefault();
    openSection(state.lastNonPreferenceSection || 'queue').catch((error) => {
      toast(error.message || 'Preferences failed to close cleanly.', true);
    });
  });

  closeDocsBtn?.addEventListener('click', (event) => {
    if (state.section !== 'documents') return;
    event.preventDefault();
    openSection(state.lastNonPreferenceSection || 'queue').catch((error) => {
      toast(error.message || 'Documents failed to close cleanly.', true);
    });
  });

  managerToolbar?.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('button');
    if (!button) return;
    const action = button.dataset.action;

    try {
      if (action === 'open-create-client-modal') {
        const dialog = document.getElementById('wp-pq-create-client-dialog');
        if (dialog) dialog.showModal();
        return;
      }
      // invite form is now inline toggle, no dialog needed
      if (action === 'open-link-client-modal') {
        const dialog = document.getElementById('wp-pq-link-client-dialog');
        if (dialog) dialog.showModal();
        return;
      }
      if (action === 'close-dialog') {
        const dialog = button.closest('dialog');
        if (dialog) dialog.close();
        return;
      }
      if (action === 'start-create-statement') {
        state.invoiceDraftMode = 'create';
        await _pqMgr.render['invoice-drafts']();
        return;
      }
      if (action === 'cancel-create-statement') {
        state.invoiceDraftMode = 'review';
        state.invoiceDraftClientId = 0;
        replaceSectionUrl('invoice-drafts', { period: new URLSearchParams(window.location.search).get('period') || new Date().toISOString().slice(0, 7) });
        await _pqMgr.render['invoice-drafts']();
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  managerToolbar?.addEventListener('input', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.id !== 'wp-pq-client-browser-search') return;

    state.clientsSearch = target.value || '';
    await _pqMgr.render.clients();
    const searchInput = document.getElementById('wp-pq-client-browser-search');
    if (searchInput) {
      searchInput.focus();
      const length = String(state.clientsSearch || '').length;
      searchInput.setSelectionRange(length, length);
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
        var renderer = _pqMgr.render[state.section] || _pqMgr.render.clients;
        await renderer();
        toast('Client created.', false);
        return;
      }
      if (form.id === 'wp-pq-manager-link-client') {
        await submitJson('manager/clients', 'POST', Object.fromEntries(new FormData(form).entries()));
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Existing user linked as client.', false);
        return;
      }
      if (form.id === 'wp-pq-invite-form') {
        const formData = Object.fromEntries(new FormData(form).entries());
        if (formData.client_id === 'new') formData.client_id = 0;
        await api('manager/invites', { method: 'POST', body: JSON.stringify(formData) });
        state.clients = null; // refresh client list if a new one was created
        toast('Invite sent.', false);
        form.reset();
        var formWrap = document.getElementById('wp-pq-invite-form-wrap');
        if (formWrap) formWrap.hidden = true;
        await _pqMgr.render.invites();
        return;
      }
      if (form.id === 'wp-pq-rollup-filter-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('billing-queue', data);
        await _pqMgr.render['billing-queue']();
        return;
      }
      if (form.id === 'wp-pq-files-filter-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('files', data);
        await _pqMgr.render.files();
        return;
      }
      // wp-pq-work-log-filter-form handled inline in renderWorkStatements
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
        await _pqMgr.render['invoice-drafts'](statementId || 0);
        toast(response.message || 'Invoice Draft created.', false);
        return;
      }
      if (form.id === 'wp-pq-ai-import-form') {
        const formData = new FormData(form);
        const response = await api('manager/ai-import/parse', {
          method: 'POST',
          body: formData,
        });
        state.aiPreview = response.preview || null;
        if (response.team_members) state.aiTeamMembers = response.team_members;
        await _pqMgr.render['ai-import']();
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
        replaceSectionUrl('billing-queue', data);
        await _pqMgr.render['billing-queue']();
        return;
      }
      if (form.id === 'wp-pq-files-filter-form') {
        if (target.name === 'client_id') {
          const jobFilter = form.querySelector('select[name="bucket_id"]');
          if (jobFilter) {
            jobFilter.innerHTML = Number(target.value || 0) > 0
              ? clientJobOptions(target.value, 0, true)
              : '<option value="0">All jobs</option>';
          }
        }
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('files', data);
        await _pqMgr.render.files();
        return;
      }
      // wp-pq-work-log-filter-form handled inline in renderWorkStatements
      if (form.id === 'wp-pq-statement-period-form') {
        const data = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('invoice-drafts', data);
        await _pqMgr.render['invoice-drafts']();
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  // Tree collapse/expand, backdrop click, and tree-action links (non-button elements)
  managerContent?.addEventListener('click', async (event) => {
    // Tree toggle: collapse indicator or group header
    const toggle = event.target.closest('[data-action="tree-toggle"]');
    if (toggle) {
      const li = toggle.closest('li');
      if (li) li.classList.toggle('is-collapsed');
      return;
    }
    // Drawer backdrop click
    if (event.target.id === 'wp-pq-tree-backdrop') {
      state.drawerOpen = false;
      const drawer = document.getElementById('wp-pq-tree-drawer');
      const backdrop = document.getElementById('wp-pq-tree-backdrop');
      if (drawer) drawer.classList.remove('is-open');
      if (backdrop) backdrop.classList.remove('is-open');
      return;
    }
    // "+ Add job" / "+ Invite member" tree links (non-button data-action elements)
    const actionEl = event.target.closest('[data-action]');
    if (actionEl && actionEl.dataset.clientId) {
      const action = actionEl.dataset.action;
      if (action === 'open-add-job-for-client' || action === 'open-invite-for-client') {
        state.selectedClientId = Number(actionEl.dataset.clientId);
        state.clientTab = 'overview';
        state.drawerOpen = true;
        await _pqMgr.render.clients();
        return;
      }
    }
  });

  managerContent?.addEventListener('click', async (event) => {
    const button = event.target.closest('button');
    if (!button) return;
    const action = button.dataset.action;
    try {
      if (button.dataset.openClient) {
        state.selectedClientId = Number(button.dataset.openClient || 0);
        state.clientTab = 'overview';
        state.drawerOpen = true;
        replaceSectionUrl('clients', state.selectedClientId > 0 ? { client_id: state.selectedClientId } : {});
        await _pqMgr.render.clients();
        return;
      }
      if (action === 'close-tree-drawer') {
        state.drawerOpen = false;
        const drawer = document.getElementById('wp-pq-tree-drawer');
        const backdrop = document.getElementById('wp-pq-tree-backdrop');
        if (drawer) drawer.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-open');
        return;
      }
      if (button.dataset.clientTab) {
        state.clientTab = button.dataset.clientTab;
        document.querySelectorAll('.wp-pq-client-tab-btn').forEach((btn) => btn.classList.toggle('is-active', btn.dataset.clientTab === state.clientTab));
        document.querySelectorAll('.wp-pq-client-tab-body').forEach((body) => { body.hidden = body.dataset.tab !== state.clientTab; });
        return;
      }
      if (button.dataset.openStatement) {
        state.invoiceDraftMode = 'review';
        await openSection('invoice-drafts', { query: { statement_id: Number(button.dataset.openStatement || 0) } });
        return;
      }
      if (action === 'delete-client-contact') {
        await api(`manager/clients/${button.dataset.clientId}/contacts/${button.dataset.contactId}`, { method: 'DELETE' });
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Contact removed.', false);
        return;
      }
      if (action === 'delete-member-contact') {
        await api(`manager/members/${button.dataset.memberId}/contacts/${button.dataset.contactId}`, { method: 'DELETE' });
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Member contact removed.', false);
        return;
      }
      if (action === 'show-move-job') {
        const jobId = button.dataset.jobId;
        const panel = managerContent.querySelector(`[data-move-panel="${jobId}"]`);
        if (panel) panel.hidden = false;
        const delPanel = managerContent.querySelector(`[data-delete-panel="${jobId}"]`);
        if (delPanel) delPanel.hidden = true;
        return;
      }
      if (action === 'show-delete-job') {
        const jobId = button.dataset.jobId;
        const panel = managerContent.querySelector(`[data-delete-panel="${jobId}"]`);
        if (panel) panel.hidden = false;
        const movePanel = managerContent.querySelector(`[data-move-panel="${jobId}"]`);
        if (movePanel) movePanel.hidden = true;
        return;
      }
      if (action === 'cancel-job-panel') {
        const jobId = button.dataset.jobId;
        const movePanel = managerContent.querySelector(`[data-move-panel="${jobId}"]`);
        const delPanel = managerContent.querySelector(`[data-delete-panel="${jobId}"]`);
        if (movePanel) movePanel.hidden = true;
        if (delPanel) delPanel.hidden = true;
        return;
      }
      if (action === 'confirm-move-job') {
        const jobId = button.dataset.jobId;
        const select = managerContent.querySelector(`[data-move-target-client="${jobId}"]`);
        const targetClientId = select ? Number(select.value) : 0;
        if (targetClientId <= 0) { toast('Choose a target client.', true); return; }
        await submitJson(`manager/jobs/${jobId}/move`, 'POST', { target_client_id: targetClientId });
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Job moved to new client.', false);
        return;
      }
      if (action === 'confirm-delete-job') {
        const jobId = button.dataset.jobId;
        const confirmInput = managerContent.querySelector(`[data-delete-confirm="${jobId}"]`);
        const confirmVal = confirmInput ? confirmInput.value.trim() : '';
        if (confirmVal !== 'DELETE') { toast('Type DELETE to confirm.', true); return; }
        const targetSelect = managerContent.querySelector(`[data-delete-target-job="${jobId}"]`);
        const targetBucketId = targetSelect ? Number(targetSelect.value) : 0;
        await submitJson(`manager/jobs/${jobId}/force-delete`, 'POST', {
          confirm: 'DELETE',
          target_bucket_id: targetBucketId,
        });
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Job deleted.', false);
        return;
      }
      if (action === 'revoke-invite') {
        const inviteId = button.dataset.inviteId;
        if (!inviteId || !confirm('Revoke this invite?')) return;
        await api('manager/invites/' + inviteId, { method: 'DELETE' });
        toast('Invite revoked.', false);
        await _pqMgr.render.invites();
        return;
      }
      if (action === 'resend-invite') {
        const inviteId = button.dataset.inviteId;
        if (!inviteId) return;
        button.disabled = true;
        button.textContent = 'Sending…';
        var result = await api('manager/invites/' + inviteId + '/resend', { method: 'POST' });
        toast(result.message || 'Invite resent.', false);
        await _pqMgr.render.invites();
        return;
      }
      if (action === 'copy-invite-link') {
        var token = button.dataset.inviteToken;
        if (!token) return;
        var link = window.location.origin + '/portal/invite/' + token;
        try {
          await navigator.clipboard.writeText(link);
          toast('Invite link copied to clipboard.', false);
        } catch (e) {
          window.prompt('Copy this invite link:', link);
        }
        return;
      }
      if (action === 'assign-rollup-job') {
        return;
      }
      if (action === 'toggle-payment' && state.statementDetail) {
        const response = await submitJson(`manager/statements/${state.statementDetail.id}/payment`, 'POST', { payment_status: button.dataset.paymentStatus });
        await _pqMgr.render['invoice-drafts'](state.statementDetail.id);
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
        await api(`manager/statements/${state.statementDetail.id}`, { method: 'DELETE' });
        state.invoiceDraftMode = 'review';
        replaceSectionUrl('invoice-drafts', {});
        await _pqMgr.render['invoice-drafts']();
        toast('Invoice Draft deleted.', false);
        return;
      }
      if (action === 'delete-line' && state.statementDetail) {
        await api(`manager/statements/${state.statementDetail.id}/lines/${button.dataset.lineId}`, { method: 'DELETE' });
        await _pqMgr.render['invoice-drafts'](state.statementDetail.id);
        toast('Invoice draft line removed.', false);
        return;
      }
      if (action === 'remove-task' && state.statementDetail) {
        await api(`manager/statements/${state.statementDetail.id}/tasks/${button.dataset.taskId}`, { method: 'DELETE' });
        await _pqMgr.render['invoice-drafts'](state.statementDetail.id);
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
        await _pqMgr.render['invoice-drafts']();
        return;
      }
      if (target.dataset.action === 'toggle-job-access') {
        const bucketId = Number(target.dataset.bucketId || 0);
        const userId = Number(target.dataset.userId || 0);
        const clientId = Number(target.dataset.clientId || 0);
        if (bucketId <= 0 || userId <= 0) return;
        if (target.checked) {
          await submitJson(`manager/jobs/${bucketId}/members`, 'POST', { user_id: userId });
        } else {
          await api(`manager/jobs/${bucketId}/members/${userId}`, { method: 'DELETE' });
        }
        state.clients = null;
        toast(target.checked ? 'Access granted.' : 'Access removed.', false);
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
    await _pqMgr.render.clients();
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
        await _pqMgr.render.clients();
        toast('Client member saved.', false);
        return;
      }
      if (form.dataset.action === 'update-client') {
        await submitJson(`manager/clients/${form.dataset.clientId}`, 'POST', Object.fromEntries(new FormData(form).entries()));
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Client details updated.', false);
        return;
      }
      if (form.dataset.action === 'create-job') {
        const fd = new FormData(form);
        const payload = { client_id: Number(form.dataset.clientId || 0), bucket_name: fd.get('bucket_name') };
        const mode = fd.get('default_billing_mode');
        const rate = fd.get('default_rate');
        if (mode) payload.default_billing_mode = mode;
        if (rate) payload.default_rate = rate;
        await submitJson('manager/jobs', 'POST', payload);
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Job saved.', false);
        return;
      }
      if (form.dataset.action === 'save-client-address') {
        await submitJson(`manager/clients/${form.dataset.clientId}`, 'POST', Object.fromEntries(new FormData(form).entries()));
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Address saved.', false);
        return;
      }
      if (form.dataset.action === 'save-client-contact') {
        const fd = Object.fromEntries(new FormData(form).entries());
        fd.is_primary = fd.is_primary ? 1 : 0;
        await submitJson(`manager/clients/${form.dataset.clientId}/contacts`, 'POST', fd);
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Contact saved.', false);
        return;
      }
      if (form.dataset.action === 'save-member-contact') {
        const fd = Object.fromEntries(new FormData(form).entries());
        fd.is_primary = fd.is_primary ? 1 : 0;
        await submitJson(`manager/members/${form.dataset.memberId}/contacts`, 'POST', fd);
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Member contact saved.', false);
        return;
      }
      if (form.dataset.action === 'assign-job-member') {
        await submitJson(`manager/jobs/${form.dataset.bucketId}/members`, 'POST', { user_id: Number(new FormData(form).get('user_id') || 0) });
        state.clients = null;
        await _pqMgr.render.clients();
        toast('Job access saved.', false);
        return;
      }
      if (form.dataset.action === 'assign-rollup-job') {
        await submitJson('manager/rollups/assign-job', 'POST', Object.fromEntries(new FormData(form).entries()));
        await _pqMgr.render['billing-queue']();
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
          if (state.section === 'billing-queue') {
            await _pqMgr.render['billing-queue']();
          } else if (state.section === 'invoice-drafts' && state.statementDetail) {
            await _pqMgr.render['invoice-drafts'](state.statementDetail.id);
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
          if (state.section === 'billing-queue') {
            await _pqMgr.render['billing-queue']();
          } else if (state.section === 'invoice-drafts' && state.statementDetail) {
            await _pqMgr.render['invoice-drafts'](state.statementDetail.id);
          }
          return;
        }
      }
      if (form.id === 'wp-pq-statement-update-form' && state.statementDetail) {
        await submitJson(`manager/statements/${state.statementDetail.id}`, 'POST', Object.fromEntries(new FormData(form).entries()));
        await _pqMgr.render['invoice-drafts'](state.statementDetail.id);
        toast('Invoice draft updated.', false);
        return;
      }
      if (form.id === 'wp-pq-statement-line-form' && state.statementDetail) {
        await submitJson(`manager/statements/${state.statementDetail.id}/lines`, 'POST', Object.fromEntries(new FormData(form).entries()));
        await _pqMgr.render['invoice-drafts'](state.statementDetail.id);
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
        if (response.team_members) state.aiTeamMembers = response.team_members;
        await _pqMgr.render['ai-import']();
        toast(response.message || 'Preview context updated.', false);
        return;
      }
      if (target.id === 'wp-pq-ai-import-confirm') {
        const clientId = Number(document.getElementById('wp-pq-ai-client')?.value || state.aiPreview?.client_id || 0);
        const jobId = Number(document.getElementById('wp-pq-ai-job')?.value || state.aiPreview?.billing_bucket_id || 0);
        const confirmNewJobs = !!document.getElementById('wp-pq-ai-confirm-jobs')?.checked;
        state.aiContext = { client_id: clientId, billing_bucket_id: jobId };
        const taskOverrides = (state.aiPreview?.tasks || []).map(function (t) { return t._edited ? t : null; });
        const payload = { client_id: clientId, billing_bucket_id: jobId, confirm_new_jobs: confirmNewJobs };
        if (taskOverrides.some(Boolean)) payload.task_overrides = taskOverrides;
        const response = await submitJson('manager/ai-import/import', 'POST', payload);
        state.aiPreview = null;
        await _pqMgr.render['ai-import']();
        toast(response.message || 'Tasks imported.', false);
        return;
      }
      if (target.id === 'wp-pq-ai-discard') {
        const response = await submitJson('manager/ai-import/discard', 'POST', {});
        state.aiPreview = null;
        await _pqMgr.render['ai-import']();
        toast(response.message || 'Preview discarded.', false);
        return;
      }
    } catch (error) {
      toast(error.message || 'Action failed.', true);
    }
  });

  // ── Public API ───────────────────────────────────────────────────────
  window.wpPqPortalManager = {
    openSection,
  };

  // Boot is deferred to the last-loading section file (admin-manager-tools.js)
  // so all renderers are registered before the initial openSection call.
})();
