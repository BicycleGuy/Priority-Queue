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

  // ── Billing Queue ────────────────────────────────────────────────────

  function rollupDatePresets() {
    var now = new Date();
    var y = now.getFullYear();
    var m = now.getMonth();
    var thisStart = new Date(y, m, 1);
    var thisEnd = new Date(y, m + 1, 0);
    var lastStart = new Date(y, m - 1, 1);
    var lastEnd = new Date(y, m, 0);
    var qStart = new Date(y, Math.floor(m / 3) * 3, 1);
    var qEnd = new Date(y, Math.floor(m / 3) * 3 + 3, 0);
    function fmt(d) { return d.toISOString().slice(0, 10); }
    return [
      { label: 'This Month', start: fmt(thisStart), end: fmt(thisEnd) },
      { label: 'Last Month', start: fmt(lastStart), end: fmt(lastEnd) },
      { label: 'This Quarter', start: fmt(qStart), end: fmt(qEnd) },
    ];
  }

  function rollupStatusSelect(entry) {
    var status = String(entry.invoice_status || 'pending_review');
    var locked = status === 'invoiced' || status === 'paid';
    if (locked) return esc(invoiceStatusLabel(status));
    return '<select class="wp-pq-rollup-status-select" data-ledger-entry-id="' + entry.ledger_entry_id + '">' +
      '<option value="pending_review"' + (status === 'pending_review' ? ' selected' : '') + '>Pending Review</option>' +
      '<option value="billable"' + (status === 'billable' ? ' selected' : '') + '>Billable</option>' +
      '<option value="no_charge"' + (status === 'no_charge' ? ' selected' : '') + '>No Charge</option>' +
      '</select>';
  }

  function billingRollupCsv(groups) {
    var rows = [['Date', 'Customer/Client', 'Description', 'Work Summary', 'Owner', 'Job', 'Hours', 'Rate', 'Amount', 'Category', 'Billing Mode', 'Invoice Status']];
    (groups || []).forEach(function (group) {
      (group.entries || []).forEach(function (entry) {
        rows.push([
          String(entry.completion_date || '').slice(0, 10),
          group.client_name || '',
          entry.title_snapshot || '',
          entry.work_summary || '',
          entry.owner_name || 'Unassigned',
          group.bucket_name || '',
          entry.hours || '',
          entry.rate || '',
          entry.amount || '',
          entry.billing_category || '',
          entry.billing_mode || '',
          invoiceStatusLabel(entry.invoice_status),
        ]);
      });
    });
    return rowsToCsv(rows);
  }

  async function renderBillingQueue() {
    renderManagerFrame('billing-queue');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading billing queue…</div>';
    await ensureClients();
    // On re-render, preserve current form values; fall back to URL params on initial load
    var existingForm = document.getElementById('wp-pq-rollup-filter-form');
    var params = new URLSearchParams(window.location.search);
    var startDate = existingForm
      ? (existingForm.querySelector('[name="start_date"]') || {}).value || ''
      : params.get('start_date') || '';
    var endDate = existingForm
      ? (existingForm.querySelector('[name="end_date"]') || {}).value || ''
      : params.get('end_date') || '';
    var month = existingForm
      ? (existingForm.querySelector('[name="month"]') || {}).value || new Date().toISOString().slice(0, 7)
      : params.get('month') || new Date().toISOString().slice(0, 7);
    var clientId = existingForm
      ? (existingForm.querySelector('[name="client_id"]') || {}).value || '0'
      : params.get('client_id') || '0';

    var invoiceStatusParam = existingForm
      ? (existingForm.querySelector('[name="invoice_status"]') || {}).value || ''
      : params.get('invoice_status') || 'pending_review';

    // Build API query — prefer custom dates, fall back to month
    var apiQuery = 'manager/rollups?client_id=' + encodeURIComponent(clientId);
    if (startDate && endDate) {
      apiQuery += '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
    } else {
      apiQuery += '&month=' + encodeURIComponent(month);
    }
    if (invoiceStatusParam) {
      apiQuery += '&invoice_status=' + encodeURIComponent(invoiceStatusParam);
    }
    var data = await api(apiQuery);

    // Resolve display dates from the API response
    var rangeStart = data.range.custom_start || data.range.start || '';
    var rangeEnd = data.range.custom_end || data.range.end || '';

    var presets = rollupDatePresets();
    var presetsHtml = presets.map(function (p) {
      return '<button type="button" class="button wp-pq-rollup-preset" data-start="' + p.start + '" data-end="' + p.end + '">' + esc(p.label) + '</button>';
    }).join('');

    var invoiceStatus = invoiceStatusParam;

    el.managerToolbar.innerHTML =
      '<form id="wp-pq-rollup-filter-form" class="wp-pq-period-form">' +
      '  <label>Start <input type="date" name="start_date" value="' + esc(rangeStart) + '"></label>' +
      '  <label>End <input type="date" name="end_date" value="' + esc(rangeEnd) + '"></label>' +
      '  <label>Client <select name="client_id">' + clientOptions(clientId, true) + '</select></label>' +
      '  <label>Status <select name="invoice_status">' +
      '    <option value=""' + (invoiceStatus === '' ? ' selected' : '') + '>All statuses</option>' +
      '    <option value="pending_review"' + (invoiceStatus === 'pending_review' ? ' selected' : '') + '>Pending Review</option>' +
      '    <option value="billable"' + (invoiceStatus === 'billable' ? ' selected' : '') + '>Billable</option>' +
      '    <option value="no_charge"' + (invoiceStatus === 'no_charge' ? ' selected' : '') + '>No Charge</option>' +
      '    <option value="invoiced"' + (invoiceStatus === 'invoiced' ? ' selected' : '') + '>Invoiced</option>' +
      '    <option value="paid"' + (invoiceStatus === 'paid' ? ' selected' : '') + '>Paid</option>' +
      '  </select></label>' +
      '  <div class="wp-pq-rollup-presets">' + presetsHtml + '</div>' +
      '  <button class="button wp-pq-secondary-action" type="button" id="wp-pq-rollup-export-csv">Export CSV</button>' +
      '  <button class="button wp-pq-secondary-action" type="button" id="wp-pq-rollup-print">Print</button>' +
      '</form>';

    // Bulk action bar
    var bulkHtml =
      '<div class="wp-pq-rollup-bulk-bar" id="wp-pq-rollup-bulk-bar" hidden>' +
      '  <span id="wp-pq-rollup-bulk-count">0 selected</span>' +
      '  <button type="button" class="button" data-action="bulk-billable">Mark Billable</button>' +
      '  <button type="button" class="button" data-action="bulk-no-charge">Mark No Charge</button>' +
      '</div>';

    var groupsHtml = (data.groups || []).map(function (group) {
      var summaryParts = [];
      summaryParts.push(Number(group.entry_count || (group.entries || []).length) + ' entries');
      if (group.billable_count > 0) summaryParts.push(group.billable_count + ' billable');
      if (group.no_charge_count > 0) summaryParts.push(group.no_charge_count + ' no-charge');
      if (Number(group.total_amount || 0) > 0) summaryParts.push('$' + Number(group.total_amount).toFixed(2));

      var rowsHtml = (group.entries || []).map(function (entry) {
        var status = String(entry.invoice_status || 'pending_review');
        var isLocked = status === 'invoiced' || status === 'paid';
        var mode = String(entry.billing_mode || 'fixed_fee');
        var modeLabels = { hourly: 'Hourly', fixed_fee: 'Fixed fee', pass_through_expense: 'Pass-through', scope_of_work: 'Scope of work', non_billable: 'Non-billable' };
        var amountStr = Number(entry.amount || 0) > 0 ? '$' + Number(entry.amount).toFixed(2) : '';
        var hoursStr = Number(entry.hours || 0) > 0 ? entry.hours + 'h' : '';
        var billingMeta = [modeLabels[mode] || mode, hoursStr, amountStr].filter(Boolean).join(' · ');

        var adjustHasHours = mode === 'fixed_fee' && Number(entry.hours || 0) > 0;
        var dn = ' style="display:none"';

        var adjustRow = '<tr class="wp-pq-adjust-row" data-adjust-for="' + entry.ledger_entry_id + '" style="display:none">' +
          '<td colspan="7"><form class="wp-pq-adjust-form" data-action="adjust-ledger" data-ledger-id="' + entry.ledger_entry_id + '">' +
          '<div class="wp-pq-adjust-grid">' +
          '<label>Mode <select name="billing_mode">' +
          '<option value="fixed_fee"' + (mode === 'fixed_fee' ? ' selected' : '') + '>Fixed fee</option>' +
          '<option value="hourly"' + (mode === 'hourly' ? ' selected' : '') + '>Hourly</option>' +
          '<option value="pass_through_expense"' + (mode === 'pass_through_expense' ? ' selected' : '') + '>Pass-through</option>' +
          '<option value="scope_of_work"' + (mode === 'scope_of_work' ? ' selected' : '') + '>Scope of work</option>' +
          '<option value="non_billable"' + (mode === 'non_billable' ? ' selected' : '') + '>Non-billable</option>' +
          '</select></label>' +
          '<label class="wp-pq-af-hours"' + (mode !== 'hourly' && !adjustHasHours ? dn : '') + '>Hours <input type="number" name="hours" step="0.25" min="0" value="' + (entry.hours || '') + '"></label>' +
          '<label class="wp-pq-af-rate"' + (mode !== 'hourly' ? dn : '') + '>Rate <input type="number" name="rate" step="0.01" min="0" value="' + (entry.rate || '') + '"></label>' +
          '<label class="wp-pq-af-amount"' + (mode === 'non_billable' ? dn : '') + '>Amount <input type="number" name="amount" step="0.01" value="' + (entry.amount || '') + '"></label>' +
          '<label class="wp-pq-af-record-hours"' + (mode !== 'fixed_fee' ? dn : '') + '><input type="checkbox" class="wp-pq-adjust-record-hours-cb"' + (adjustHasHours ? ' checked' : '') + '> Also record hours</label>' +
          '</div>' +
          '<div class="wp-pq-adjust-actions">' +
          '<button class="button button-primary" type="submit">Save</button>' +
          '<button class="button" type="button" data-action="cancel-adjust" data-ledger-id="' + entry.ledger_entry_id + '">Cancel</button>' +
          '</div></form></td></tr>';

        return '<tr' + (status === 'no_charge' ? ' class="wp-pq-rollup-no-charge"' : '') + '>' +
          '<td><input type="checkbox" class="wp-pq-rollup-check" data-ledger-entry-id="' + entry.ledger_entry_id + '"' +
          (isLocked ? ' disabled' : '') + '></td>' +
          '<td>' + esc(String(entry.completion_date || '').slice(0, 10)) + '</td>' +
          '<td><strong>' + esc(entry.title_snapshot || '') + '</strong>' +
          '<div class="wp-pq-panel-note">' + esc(entry.work_summary || '') + '</div></td>' +
          '<td>' + esc(entry.owner_name || 'Unassigned') + '</td>' +
          '<td>' + rollupStatusSelect(entry) +
          '<div class="wp-pq-panel-note">' + billingMeta + '</div></td>' +
          '<td><select class="wp-pq-rollup-job-select" data-action="assign-rollup-job"' +
          ' data-ledger-entry-id="' + entry.ledger_entry_id + '"' +
          ' data-task-id="' + (entry.task_id || 0) + '"' +
          ' data-current-bucket-id="' + (entry.billing_bucket_id || 0) + '">' +
          (group.bucket_options || []).map(function (bucket) {
            return '<option value="' + bucket.id + '"' +
              (Number(bucket.id) === Number(entry.billing_bucket_id) ? ' selected' : '') + '>' +
              esc(bucket.bucket_name || 'Job') + '</option>';
          }).join('') +
          '</select></td>' +
          '<td><button class="button wp-pq-secondary-action" type="button" data-action="toggle-adjust" data-ledger-id="' + entry.ledger_entry_id + '">Adjust</button></td>' +
          '</tr>' + adjustRow;
      }).join('');

      return '<section class="wp-pq-panel wp-pq-manager-card">' +
        '<div class="wp-pq-section-heading"><div>' +
        '<h3>' + esc(group.client_name || 'Client') + ' · ' + esc(group.bucket_name || 'Job') + '</h3>' +
        '<p class="wp-pq-panel-note">' + esc(summaryParts.join(' · ')) + '</p>' +
        '</div></div>' +
        '<table class="wp-pq-admin-table wp-pq-manager-table">' +
        '<thead><tr>' +
        '<th><input type="checkbox" class="wp-pq-rollup-check-all"></th>' +
        '<th>Date</th><th>Title</th><th>Owner</th><th>Billing</th><th>Job</th><th></th>' +
        '</tr></thead>' +
        '<tbody>' + rowsHtml + '</tbody>' +
        '</table></section>';
    }).join('');

    el.managerContent.innerHTML = bulkHtml + (groupsHtml || '<div class="wp-pq-empty-state">No entries match these filters.</div>');

    // ── Wire events ──

    // Preset buttons
    el.managerToolbar.querySelectorAll('.wp-pq-rollup-preset').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = document.getElementById('wp-pq-rollup-filter-form');
        if (!form) return;
        form.querySelector('[name="start_date"]').value = btn.dataset.start;
        form.querySelector('[name="end_date"]').value = btn.dataset.end;
        var formData = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('billing-queue', formData);
        renderBillingQueue();
      });
    });

    // CSV export
    var csvBtn = document.getElementById('wp-pq-rollup-export-csv');
    if (csvBtn) {
      csvBtn.addEventListener('click', function () {
        downloadCsv('billing-queue-' + rangeStart + '-to-' + rangeEnd + '.csv', billingRollupCsv(data.groups || []));
      });
    }

    // Print
    var printBtn = document.getElementById('wp-pq-rollup-print');
    if (printBtn) {
      printBtn.addEventListener('click', function () {
        var html = (data.groups || []).map(function (group) {
          var rows = (group.entries || []).map(function (entry) {
            return '<tr>' +
              '<td>' + esc(String(entry.completion_date || '').slice(0, 10)) + '</td>' +
              '<td>' + esc(entry.title_snapshot || '') + '</td>' +
              '<td>' + esc(entry.owner_name || '') + '</td>' +
              '<td>' + esc(entry.hours || '') + '</td>' +
              '<td>' + (entry.amount ? '$' + Number(entry.amount).toFixed(2) : '') + '</td>' +
              '<td>' + esc(invoiceStatusLabel(entry.invoice_status)) + '</td></tr>';
          }).join('');
          return '<h2>' + esc(group.client_name || 'Client') + ' · ' + esc(group.bucket_name || 'Job') + '</h2>' +
            '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px;">' +
            '<thead><tr style="background:#f5f5f4;"><th>Date</th><th>Title</th><th>Owner</th><th>Hours</th><th>Amount</th><th>Status</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table>';
        }).join('');
        printHtml('Billing Queue ' + rangeStart + ' to ' + rangeEnd, '<h1>Billing Queue</h1><p>' + rangeStart + ' to ' + rangeEnd + '</p>' + html);
      });
    }

    // Invoice status filter change → re-render
    var statusFilter = el.managerToolbar.querySelector('[name="invoice_status"]');
    if (statusFilter) {
      statusFilter.addEventListener('change', function () {
        var form = document.getElementById('wp-pq-rollup-filter-form');
        if (!form) return;
        var formData = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('billing-queue', formData);
        renderBillingQueue();
      });
    }

    // Client filter change → re-render
    var clientFilter = el.managerToolbar.querySelector('[name="client_id"]');
    if (clientFilter) {
      clientFilter.addEventListener('change', function () {
        var form = document.getElementById('wp-pq-rollup-filter-form');
        if (!form) return;
        var formData = Object.fromEntries(new FormData(form).entries());
        replaceSectionUrl('billing-queue', formData);
        renderBillingQueue();
      });
    }

    // Select-all checkboxes
    el.managerContent.querySelectorAll('.wp-pq-rollup-check-all').forEach(function (allCb) {
      allCb.addEventListener('change', function () {
        var table = allCb.closest('table');
        if (!table) return;
        table.querySelectorAll('.wp-pq-rollup-check:not(:disabled)').forEach(function (cb) {
          cb.checked = allCb.checked;
        });
        updateBulkBar();
      });
    });

    // Individual checkboxes → update bulk bar
    el.managerContent.addEventListener('change', function (e) {
      if (e.target.classList.contains('wp-pq-rollup-check')) {
        updateBulkBar();
      }
    });

    // Inline status change
    el.managerContent.addEventListener('change', async function (e) {
      if (!e.target.classList.contains('wp-pq-rollup-status-select')) return;
      var entryId = Number(e.target.dataset.ledgerEntryId || 0);
      var newStatus = e.target.value;
      if (!entryId) return;
      try {
        await submitJson('manager/rollups/update-status', 'POST', {
          entry_ids: [entryId],
          invoice_status: newStatus,
        });
        await renderBillingQueue();
      } catch (err) {
        alert('Failed: ' + err.message);
      }
    });

    // Bulk action buttons
    var bulkBar = document.getElementById('wp-pq-rollup-bulk-bar');
    if (bulkBar) {
      bulkBar.addEventListener('click', async function (e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;
        var action = btn.dataset.action;
        var newStatus = action === 'bulk-no-charge' ? 'no_charge' : 'billable';
        var ids = getCheckedEntryIds();
        if (!ids.length) return;
        try {
          await submitJson('manager/rollups/update-status', 'POST', {
            entry_ids: ids,
            invoice_status: newStatus,
          });
          await renderBillingQueue();
        } catch (err) {
          alert('Failed: ' + err.message);
        }
      });
    }

    // ── Adjust-row wiring (direct on each form, fresh every render) ──
    wireAdjustForms();

    function getCheckedEntryIds() {
      var ids = [];
      el.managerContent.querySelectorAll('.wp-pq-rollup-check:checked').forEach(function (cb) {
        var id = Number(cb.dataset.ledgerEntryId || 0);
        if (id > 0) ids.push(id);
      });
      return ids;
    }

    function updateBulkBar() {
      var ids = getCheckedEntryIds();
      var bar = document.getElementById('wp-pq-rollup-bulk-bar');
      var count = document.getElementById('wp-pq-rollup-bulk-count');
      if (bar) bar.hidden = ids.length === 0;
      if (count) count.textContent = ids.length + ' selected';
    }

    function show(el) { if (el) el.style.display = ''; }
    function hide(el) { if (el) el.style.display = 'none'; }

    function updateAdjustFields(form) {
      if (!form) return;
      var mode = form.querySelector('[name="billing_mode"]').value;
      var hoursLabel = form.querySelector('.wp-pq-af-hours');
      var rateLabel = form.querySelector('.wp-pq-af-rate');
      var amountLabel = form.querySelector('.wp-pq-af-amount');
      var recordHoursLabel = form.querySelector('.wp-pq-af-record-hours');

      if (mode === 'hourly') {
        show(hoursLabel); show(rateLabel); show(amountLabel); hide(recordHoursLabel);
      } else if (mode === 'fixed_fee') {
        hide(rateLabel); show(amountLabel); show(recordHoursLabel);
        var cb = recordHoursLabel ? recordHoursLabel.querySelector('.wp-pq-adjust-record-hours-cb') : null;
        if (cb && cb.checked) { show(hoursLabel); } else { hide(hoursLabel); }
      } else if (mode === 'pass_through_expense' || mode === 'scope_of_work') {
        hide(hoursLabel); hide(rateLabel); show(amountLabel); hide(recordHoursLabel);
      } else {
        hide(hoursLabel); hide(rateLabel); hide(amountLabel); hide(recordHoursLabel);
      }
    }

    function wireAdjustForms() {
      el.managerContent.querySelectorAll('.wp-pq-adjust-form').forEach(function (form) {
        var modeSelect = form.querySelector('[name="billing_mode"]');
        var recordCb = form.querySelector('.wp-pq-adjust-record-hours-cb');
        var hoursInput = form.querySelector('[name="hours"]');
        var rateInput = form.querySelector('[name="rate"]');

        // Mode change → show/hide fields
        modeSelect.addEventListener('change', function () { updateAdjustFields(form); });

        // "Also record hours" checkbox
        if (recordCb) {
          recordCb.addEventListener('change', function () {
            var hoursLabel = form.querySelector('.wp-pq-af-hours');
            if (recordCb.checked) { show(hoursLabel); } else { hide(hoursLabel); }
          });
        }

        // Hourly auto-calc
        function autoCalc() {
          if (modeSelect.value !== 'hourly') return;
          var h = Number(hoursInput.value || 0);
          var r = Number(rateInput.value || 0);
          if (h > 0 && r > 0) {
            form.querySelector('[name="amount"]').value = (h * r).toFixed(2);
          }
        }
        if (hoursInput) hoursInput.addEventListener('input', autoCalc);
        if (rateInput) rateInput.addEventListener('input', autoCalc);

        // Submit
        form.addEventListener('submit', async function (e) {
          e.preventDefault();
          var entryId = form.dataset.ledgerId;
          if (!entryId) return;
          var mode = modeSelect.value;
          var payload = {};
          if (mode) payload.billing_mode = mode;

          if (mode === 'hourly') {
            if (hoursInput.value !== '') payload.hours = Number(hoursInput.value);
            if (rateInput.value !== '') payload.rate = Number(rateInput.value);
            var a = form.querySelector('[name="amount"]').value;
            if (a !== '') payload.amount = Number(a);
          } else if (mode === 'fixed_fee') {
            var a = form.querySelector('[name="amount"]').value;
            if (a !== '') payload.amount = Number(a);
            if (recordCb && recordCb.checked && hoursInput.value !== '') {
              payload.hours = Number(hoursInput.value);
            } else {
              payload.hours = 0;
            }
          } else if (mode === 'pass_through_expense' || mode === 'scope_of_work') {
            var a = form.querySelector('[name="amount"]').value;
            if (a !== '') payload.amount = Number(a);
          }

          try {
            await submitJson('manager/ledger/' + entryId, 'POST', payload);
            await renderBillingQueue();
          } catch (err) {
            alert('Adjust failed: ' + err.message);
          }
        });
      });

      // Toggle / Cancel — delegated on container (click targets are fresh each render)
      el.managerContent.querySelectorAll('[data-action="toggle-adjust"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var row = el.managerContent.querySelector('.wp-pq-adjust-row[data-adjust-for="' + btn.dataset.ledgerId + '"]');
          if (!row) return;
          var visible = row.style.display !== 'none';
          row.style.display = visible ? 'none' : '';
          if (!visible) updateAdjustFields(row.querySelector('.wp-pq-adjust-form'));
        });
      });
      el.managerContent.querySelectorAll('[data-action="cancel-adjust"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var row = el.managerContent.querySelector('.wp-pq-adjust-row[data-adjust-for="' + btn.dataset.ledgerId + '"]');
          if (row) row.style.display = 'none';
        });
      });
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
                  <td>${esc(task.billing_status || '')}</td>
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

  // ── Billing Setup ─────────────────────────────────────────────────

  var modeLabels = { hourly: 'Hourly', fixed_fee: 'Fixed fee', pass_through_expense: 'Pass-through', non_billable: 'Non-billable' };
  var modeOptions = '<option value="">Not set</option>' +
    '<option value="fixed_fee">Fixed fee</option>' +
    '<option value="hourly">Hourly</option>' +
    '<option value="pass_through_expense">Pass-through</option>' +
    '<option value="scope_of_work">Scope of work</option>' +
    '<option value="non_billable">Non-billable</option>';

  async function renderBillingSetup() {
    renderManagerFrame('billing-setup');
    el.managerToolbar.innerHTML = '';
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading billing setup\u2026</div>';
    await ensureClients();
    var clients = currentClients();
    if (!clients.length) {
      el.managerContent.innerHTML = '<div class="wp-pq-empty-state">No clients found.</div>';
      return;
    }

    var treeItems = clients.map(function (client) {
      var buckets = client.buckets || [];
      if (!buckets.length) return '';

      var jobNodes = buckets.map(function (bucket) {
        var mode = bucket.default_billing_mode || '';
        var rate = bucket.default_rate && Number(bucket.default_rate) > 0 ? Number(bucket.default_rate).toFixed(2) : '';
        var fee = bucket.default_fee && Number(bucket.default_fee) > 0 ? Number(bucket.default_fee).toFixed(2) : '';
        var modeOpts = modeOptions.replace('value="' + mode + '"', 'value="' + mode + '" selected');
        var dn = ' style="display:none"';

        return '<li>' +
          '<div class="wp-pq-tree-node wp-pq-tree-node-job wp-pq-billing-setup-job" data-bucket-id="' + bucket.id + '">' +
          '<div class="wp-pq-billing-setup-name">' + esc(bucket.bucket_name || 'Job') +
          (Number(bucket.is_default) ? ' <span class="wp-pq-tree-badge">default</span>' : '') +
          '</div>' +
          '<div class="wp-pq-billing-setup-fields">' +
          '<label>Mode <select name="billing_mode" class="wp-pq-billing-setup-mode">' + modeOpts + '</select></label>' +
          '<label class="wp-pq-billing-setup-rate"' + (mode !== 'hourly' ? dn : '') + '>Rate <input type="number" name="rate" step="0.01" min="0" value="' + rate + '" placeholder="0.00"></label>' +
          '<label class="wp-pq-billing-setup-fee"' + (mode !== 'fixed_fee' ? dn : '') + '>Fee <input type="number" name="default_fee" step="0.01" min="0" value="' + fee + '" placeholder="0.00"></label>' +
          '<span class="wp-pq-billing-setup-saved" style="display:none">Saved</span>' +
          '</div>' +
          '</div></li>';
      }).join('');

      var initials = (client.company_name || client.display_name || '?').charAt(0).toUpperCase();

      return '<li>' +
        '<div class="wp-pq-tree-node wp-pq-tree-node-client">' +
        '<span class="wp-pq-tree-collapse-indicator">&#9660;</span>' +
        '<div class="wp-pq-tree-client-icon" style="background:#64748b">' + esc(initials) + '</div>' +
        '<div><div class="wp-pq-tree-node-name">' + esc(client.company_name || client.display_name || 'Client') + '</div>' +
        '<div class="wp-pq-tree-node-meta">' + buckets.length + ' job' + (buckets.length !== 1 ? 's' : '') + '</div></div>' +
        '</div>' +
        '<ul>' + jobNodes + '</ul>' +
        '</li>';
    }).filter(Boolean).join('');

    el.managerContent.innerHTML =
      '<section class="wp-pq-panel wp-pq-manager-card">' +
      '<div class="wp-pq-section-heading"><div>' +
      '<h3>Billing Setup</h3>' +
      '<p class="wp-pq-panel-note">Set the default billing mode and rate for each job. These defaults pre-fill the completion modal when tasks are delivered.</p>' +
      '</div></div>' +
      '<div class="wp-pq-tree-layout">' +
      '<ul class="wp-pq-tree">' + treeItems + '</ul>' +
      '</div></section>';

    // Wire mode selects → show/hide contextual fields, auto-save
    el.managerContent.querySelectorAll('.wp-pq-billing-setup-job').forEach(function (node) {
      var bucketId = node.dataset.bucketId;
      var modeSelect = node.querySelector('[name="billing_mode"]');
      var rateInput = node.querySelector('[name="rate"]');
      var feeInput = node.querySelector('[name="default_fee"]');
      var rateLabel = node.querySelector('.wp-pq-billing-setup-rate');
      var feeLabel = node.querySelector('.wp-pq-billing-setup-fee');
      var savedEl = node.querySelector('.wp-pq-billing-setup-saved');

      function syncFields() {
        var m = modeSelect.value;
        if (rateLabel) rateLabel.style.display = m === 'hourly' ? '' : 'none';
        if (feeLabel) feeLabel.style.display = m === 'fixed_fee' ? '' : 'none';
      }

      async function save() {
        var payload = {
          default_billing_mode: modeSelect.value || '',
          default_rate: rateInput && rateInput.value !== '' ? Number(rateInput.value) : '',
          default_fee: feeInput && feeInput.value !== '' ? Number(feeInput.value) : '',
        };
        try {
          await submitJson('manager/jobs/' + bucketId, 'POST', payload);
          if (savedEl) {
            savedEl.style.display = '';
            setTimeout(function () { savedEl.style.display = 'none'; }, 1500);
          }
        } catch (err) {
          alert('Save failed: ' + err.message);
        }
      }

      modeSelect.addEventListener('change', function () { syncFields(); save(); });
      if (rateInput) rateInput.addEventListener('change', save);
      if (feeInput) feeInput.addEventListener('change', save);
    });

    // Collapse/expand client nodes
    el.managerContent.querySelectorAll('.wp-pq-tree-node-client').forEach(function (node) {
      node.addEventListener('click', function (e) {
        if (e.target.closest('select') || e.target.closest('input')) return;
        var li = node.closest('li');
        if (li) li.classList.toggle('is-collapsed');
      });
    });
  }

  m.render['billing-setup'] = renderBillingSetup;
  m.render['billing-queue'] = renderBillingQueue;
  m.render['work-statements'] = renderWorkStatements;
  m.render['invoice-drafts'] = renderInvoiceDrafts;
})(window._pqMgr);
