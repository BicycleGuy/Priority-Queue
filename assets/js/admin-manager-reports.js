(function (m) {
  var state = m.state;
  var el = m.el;
  var esc = m.esc;
  var api = m.api;
  var submitJson = m.submitJson;
  var ensureClients = m.ensureClients;
  var clientOptions = m.clientOptions;
  var clientJobOptions = m.clientJobOptions;
  var currentClients = m.currentClients;
  var replaceSectionUrl = m.replaceSectionUrl;
  var invoiceStatusLabel = m.invoiceStatusLabel;
  var workflowStatusLabel = m.workflowStatusLabel;
  var formatDate = m.formatDate;
  var endOfMonth = m.endOfMonth;
  var rowsToCsv = m.rowsToCsv;
  var downloadCsv = m.downloadCsv;
  var printHtml = m.printHtml;
  var renderManagerFrame = m.renderManagerFrame;

  // ── Billing Rollup ───────────────────────────────────────────────────

  async function renderBillingRollup() {
    renderManagerFrame('billing-rollup');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading billing rollup…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const month = params.get('month') || new Date().toISOString().slice(0, 7);
    const clientId = params.get('client_id') || '0';
    const data = await api(`manager/rollups?month=${encodeURIComponent(month)}&client_id=${encodeURIComponent(clientId)}`);

    el.managerToolbar.innerHTML = `
      <form id="wp-pq-rollup-filter-form" class="wp-pq-period-form">
        <label>Month <input type="month" name="month" value="${esc(data.range.month)}"></label>
        <label>Client
          <select name="client_id">${clientOptions(clientId, true)}</select>
        </label>
      </form>
    `;

    el.managerContent.innerHTML = (data.groups || []).map((group) => `
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

  // ── Monthly Statements ───────────────────────────────────────────────

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
    return rowsToCsv(rows);
  }

  async function renderMonthlyStatements() {
    renderManagerFrame('monthly-statements');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading monthly statements…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const month = params.get('month') || new Date().toISOString().slice(0, 7);
    const clientId = params.get('client_id') || '0';
    const jobId = params.get('billing_bucket_id') || '0';
    const status = params.get('invoice_status') || '';
    const data = await api(`manager/monthly-statements?month=${encodeURIComponent(month)}&client_id=${encodeURIComponent(clientId)}&billing_bucket_id=${encodeURIComponent(jobId)}&invoice_status=${encodeURIComponent(status)}`);

    el.managerToolbar.innerHTML = `
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

    el.managerContent.innerHTML = (data.groups || []).map((group) => `
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

    const clientFilter = el.managerToolbar.querySelector('select[name="client_id"]');
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

  // ── Work Statements ──────────────────────────────────────────────────

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
    return rowsToCsv(rows);
  }

  async function renderWorkStatements() {
    renderManagerFrame('work-statements');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading…</div>';
    await ensureClients();
    const params = new URLSearchParams(window.location.search);
    const month = params.get('month') || new Date().toISOString().slice(0, 7);
    const defaultClientId = Number(params.get('client_id') || currentClients()[0]?.id || 0);
    const rangeStart = params.get('start_date') || `${month}-01`;
    const rangeEnd = params.get('end_date') || endOfMonth(month);

    el.managerToolbar.innerHTML = '';

    el.managerContent.innerHTML = `
      <section class="wp-pq-panel wp-pq-manager-card wp-pq-work-log-composer">
        <div class="wp-pq-section-heading">
          <div>
            <h3>Work Statement</h3>
            <p class="wp-pq-panel-note">Filter by client, date range, jobs, and statuses. The preview updates live. Download as PDF when ready.</p>
          </div>
        </div>
        <form id="wp-pq-work-log-filter-form" class="wp-pq-work-log-filters">
          <div class="wp-pq-wl-filter-row">
            <label>Client
              <select name="client_id" id="wp-pq-work-log-client" required>${clientOptions(defaultClientId, false)}</select>
            </label>
            <label>Start <input type="date" name="range_start" value="${esc(rangeStart)}" required></label>
            <label>End <input type="date" name="range_end" value="${esc(rangeEnd)}" required></label>
            <label>Jobs
              <select name="job_ids" id="wp-pq-work-log-jobs" class="wp-pq-manager-multiselect" multiple size="3"></select>
            </label>
          </div>
          <div class="wp-pq-wl-filter-row">
            <fieldset class="wp-pq-manager-statuses">
              <legend>Status</legend>
              <div class="wp-pq-manager-status-grid">
                ${['pending_approval', 'needs_clarification', 'approved', 'in_progress', 'needs_review', 'delivered', 'done'].map((statusKey) => `
                  <label class="wp-pq-manager-status-option">
                    <input type="checkbox" name="statuses" value="${statusKey}"${['delivered', 'done'].includes(statusKey) ? ' checked' : ''}>
                    <span>${esc(workflowStatusLabel(statusKey))}</span>
                  </label>
                `).join('')}
              </div>
            </fieldset>
            <fieldset class="wp-pq-manager-statuses">
              <legend>Billable</legend>
              <div class="wp-pq-manager-status-grid">
                <label class="wp-pq-manager-status-option"><input type="checkbox" name="billable" value="billable" checked><span>Billable</span></label>
                <label class="wp-pq-manager-status-option"><input type="checkbox" name="billable" value="non_billable"><span>Non-billable</span></label>
              </div>
            </fieldset>
          </div>
        </form>
        <div class="wp-pq-manager-inline-actions" style="padding: 12px 0 4px;">
          <button class="button button-primary" type="button" id="wp-pq-work-log-download-pdf" disabled>Download Work Statement</button>
          <span id="wp-pq-work-log-preview-count" class="wp-pq-panel-note"></span>
        </div>
        <div id="wp-pq-work-log-preview"></div>
      </section>
    `;

    const filterForm = document.getElementById('wp-pq-work-log-filter-form');
    const clientSelect = document.getElementById('wp-pq-work-log-client');
    const jobSelect = document.getElementById('wp-pq-work-log-jobs');
    const previewEl = document.getElementById('wp-pq-work-log-preview');
    const countEl = document.getElementById('wp-pq-work-log-preview-count');
    const downloadBtn = document.getElementById('wp-pq-work-log-download-pdf');
    let previewTasks = [];
    let previewDebounce = null;

    function fillJobs() {
      if (!clientSelect || !jobSelect) return;
      jobSelect.innerHTML = clientJobOptions(clientSelect.value, 0, false);
    }
    if (clientSelect) fillJobs();

    function getFilters() {
      const fd = new FormData(filterForm);
      return {
        client_id: Number(fd.get('client_id') || 0),
        range_start: fd.get('range_start') || '',
        range_end: fd.get('range_end') || '',
        job_ids: fd.getAll('job_ids').map(Number).filter(Boolean),
        statuses: fd.getAll('statuses').filter(Boolean),
        billable: fd.getAll('billable').filter(Boolean),
      };
    }

    async function refreshPreview() {
      const filters = getFilters();
      if (!filters.client_id || !filters.range_start || !filters.range_end) {
        previewEl.innerHTML = '<div class="wp-pq-empty-state">Choose a client and date range.</div>';
        countEl.textContent = '';
        downloadBtn.disabled = true;
        previewTasks = [];
        return;
      }
      previewEl.innerHTML = '<div class="wp-pq-empty-state">Loading preview…</div>';
      try {
        const result = await submitJson('manager/work-logs/preview', 'POST', filters);
        previewTasks = result.tasks || [];
        countEl.textContent = previewTasks.length + ' task' + (previewTasks.length !== 1 ? 's' : '');
        downloadBtn.disabled = previewTasks.length === 0;
        if (!previewTasks.length) {
          previewEl.innerHTML = '<div class="wp-pq-empty-state">No tasks match these filters.</div>';
          return;
        }
        previewEl.innerHTML = `
          <table class="wp-pq-admin-table wp-pq-manager-table">
            <thead><tr><th>Task</th><th>Status</th><th>Job</th><th>Updated</th><th>Billing</th></tr></thead>
            <tbody>
              ${previewTasks.map((task) => `
                <tr>
                  <td><strong>${esc(task.title || '')}</strong></td>
                  <td>${esc(workflowStatusLabel(task.status || ''))}</td>
                  <td>${esc(task.bucket_name || '')}</td>
                  <td>${esc(formatDate(task.updated_at || ''))}</td>
                  <td>${esc(task.invoice_status || task.billing_status || '')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
      } catch (err) {
        previewEl.innerHTML = '<div class="wp-pq-empty-state">Preview failed: ' + esc(err.message) + '</div>';
        previewTasks = [];
        downloadBtn.disabled = true;
      }
    }

    function schedulePreview() {
      if (previewDebounce) clearTimeout(previewDebounce);
      previewDebounce = setTimeout(refreshPreview, 300);
    }

    filterForm.addEventListener('change', (e) => {
      if (e.target.name === 'client_id') fillJobs();
      schedulePreview();
    });

    if (downloadBtn) {
      downloadBtn.addEventListener('click', () => {
        if (!previewTasks.length) return;
        const filters = getFilters();
        const clientName = clientSelect ? clientSelect.options[clientSelect.selectedIndex]?.textContent?.trim() || 'Client' : 'Client';
        const title = `Work Statement — ${clientName} — ${filters.range_start} to ${filters.range_end}`;
        const html = `
          <h2>${esc(title)}</h2>
          <p>${previewTasks.length} task${previewTasks.length !== 1 ? 's' : ''}</p>
          <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px;">
            <thead><tr style="background:#f5f5f4;"><th>Task</th><th>Status</th><th>Job</th><th>Updated</th><th>Hours</th><th>Amount</th></tr></thead>
            <tbody>
              ${previewTasks.map((task) => `
                <tr>
                  <td>${esc(task.title || '')}</td>
                  <td>${esc(workflowStatusLabel(task.status || ''))}</td>
                  <td>${esc(task.bucket_name || '')}</td>
                  <td>${esc(formatDate(task.updated_at || ''))}</td>
                  <td>${task.hours ? Number(task.hours).toFixed(1) : ''}</td>
                  <td>${task.amount ? '$' + Number(task.amount).toFixed(2) : ''}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
        printHtml(title, html);
      });
    }

    // Initial preview load
    await refreshPreview();
  }

  // ── Invoice Drafts ───────────────────────────────────────────────────

  async function renderInvoiceDrafts(selectedId) {
    renderManagerFrame('invoice-drafts');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading invoice drafts…</div>';
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

    el.managerToolbar.innerHTML = `
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

    el.managerContent.innerHTML = `
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

  m.render['billing-rollup'] = renderBillingRollup;
  m.render['monthly-statements'] = renderMonthlyStatements;
  m.render['work-statements'] = renderWorkStatements;
  m.render['invoice-drafts'] = renderInvoiceDrafts;
})(window._pqMgr);
