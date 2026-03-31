(function () {
  if (typeof window.wpPqConfig === 'undefined') return;

  const apiRoot = window.wpPqConfig.root;
  const coreRoot = window.wpPqConfig.coreRoot || '/wp-json/wp/v2/';
  const headers = { 'X-WP-Nonce': window.wpPqConfig.nonce };

  const statusColumns = [
    { key: 'pending_approval', label: 'Pending Approval' },
    { key: 'needs_clarification', label: 'Needs Clarification' },
    { key: 'approved', label: 'Approved' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'needs_review', label: 'Needs Review' },
    { key: 'delivered', label: 'Delivered' },
  ];

  const tokenLabels = {
    pending_approval: 'Pending Approval',
    needs_review: 'Needs Review',
    needs_clarification: 'Needs Clarification',
    done: 'Done',
    task_clarification_requested: 'Clarification Requested',
    task_assigned: 'Task Assigned',
    task_reprioritized: 'Priority Changed',
    task_schedule_changed: 'Schedule Changed',
    statement_batched: 'In Invoice Draft',
  };

  function normalizeStatus(s) {
    return String(s || '').toLowerCase();
  }

  const binderIcons = {
    jobs: '▣',
    all_tasks: '≡',
    awaiting_me: '@',
    awaiting_client: '◌',
    pending_approval: '!',
    needs_review: '✓',
    needs_clarification: '?',
    delivered: '↓',
    unbilled: '$',
    urgent: '▲',
    alerts: '•',
    preferences: '○',
  };

  const taskList = document.getElementById('wp-pq-task-list');
  const boardEl = document.getElementById('wp-pq-board');
  const filterListEl = document.getElementById('wp-pq-board-filter-bar') || document.getElementById('wp-pq-filter-list');
  const boardFiltersEl = document.getElementById('wp-pq-board-filters');
  const clientFilterWrap = document.getElementById('wp-pq-client-filter-wrap');
  const clientFilterEl = document.getElementById('wp-pq-client-filter');
  const jobNavWrap = document.getElementById('wp-pq-job-nav-wrap');
  const jobNavEl = document.getElementById('wp-pq-job-nav');
  const createForm = document.getElementById('wp-pq-create-form');
  const createPanel = document.getElementById('wp-pq-create-panel');
  const openCreateBtn = document.getElementById('wp-pq-open-create');
  const closeCreateBtn = document.getElementById('wp-pq-close-create');
  const createClientWrap = document.getElementById('wp-pq-create-client-wrap');
  const createClientEl = document.getElementById('wp-pq-create-client');
  const createBucketEl = document.getElementById('wp-pq-create-bucket');
  const createNewBucketWrap = document.getElementById('wp-pq-create-new-bucket-wrap');
  const createNewBucketEl = document.getElementById('wp-pq-create-new-bucket');
  const createNeedsMeetingEl = document.getElementById('wp-pq-create-needs-meeting');
  const createMeetingFieldsEl = document.getElementById('wp-pq-create-meeting-fields');
  const createMeetingStartEl = document.getElementById('wp-pq-create-meeting-start');
  const createMeetingEndEl = document.getElementById('wp-pq-create-meeting-end');
  const currentTaskEl = document.getElementById('wp-pq-current-task');
  const currentTaskStatusEl = document.getElementById('wp-pq-current-task-status');
  const currentTaskMetaEl = document.getElementById('wp-pq-current-task-meta');
  const currentTaskGuidanceEl = document.getElementById('wp-pq-current-task-guidance');
  const currentTaskDescriptionEl = document.getElementById('wp-pq-current-task-description');
  const currentTaskActionsEl = document.getElementById('wp-pq-current-task-actions');
  const assignmentPanelEl = document.getElementById('wp-pq-assignment-panel');
  const assignmentFactsEl = document.getElementById('wp-pq-assignment-summary');
  const assignmentSelectEl = document.getElementById('wp-pq-assignment-select');
  const assignmentSaveBtn = document.getElementById('wp-pq-save-assignment');
  const priorityPanelEl = document.getElementById('wp-pq-priority-panel');
  const prioritySelectEl = document.getElementById('wp-pq-priority-select');
  const prioritySaveBtn = document.getElementById('wp-pq-save-priority');
  const meetingPanel = document.getElementById('wp-pq-meeting-panel');
  const meetingSummaryEl = document.getElementById('wp-pq-meeting-summary');
  const meetingList = document.getElementById('wp-pq-meeting-list');
  const meetingForm = document.getElementById('wp-pq-meeting-form');
  const meetingStartInput = meetingForm ? meetingForm.querySelector('input[name="starts_at"]') : null;
  const meetingEndInput = meetingForm ? meetingForm.querySelector('input[name="ends_at"]') : null;
  const messageList = document.getElementById('wp-pq-message-list');
  const messageForm = document.getElementById('wp-pq-message-form');
  const noteList = document.getElementById('wp-pq-note-list');
  const noteForm = document.getElementById('wp-pq-note-form');
  const mentionList = document.getElementById('wp-pq-mention-list');
  const fileList = document.getElementById('wp-pq-file-list');
  const boardViewBtn = document.getElementById('wp-pq-view-board');
  const calendarViewBtn = document.getElementById('wp-pq-view-calendar');
  const boardPanel = document.getElementById('wp-pq-board-panel') || document.getElementById('wp-pq-queue-panel');
  const calendarPanel = document.getElementById('wp-pq-calendar-panel');
  const calendarEl = document.getElementById('wp-pq-calendar');
  const uppyTarget = document.getElementById('wp-pq-uppy');
  const tabMessagesBtn = document.getElementById('wp-pq-tab-messages');
  const tabMeetingsBtn = document.getElementById('wp-pq-tab-meetings');
  const tabNotesBtn = document.getElementById('wp-pq-tab-notes');
  const tabFilesBtn = document.getElementById('wp-pq-tab-files');
  const panelMessages = document.getElementById('wp-pq-panel-messages');
  const panelMeetings = document.getElementById('wp-pq-panel-meetings');
  const panelNotes = document.getElementById('wp-pq-panel-notes');
  const panelFiles = document.getElementById('wp-pq-panel-files');
  const appShellEl = document.querySelector('.wp-pq-app-shell');
  const drawerEl = document.getElementById('wp-pq-task-drawer');
  const drawerBackdrop = document.getElementById('wp-pq-drawer-backdrop');
  const drawerCloseBtn = document.getElementById('wp-pq-close-drawer');
  const taskWorkspaceEl = document.getElementById('wp-pq-task-workspace');
  const taskEmptyEl = document.getElementById('wp-pq-task-empty');
  const binderClientContext = document.getElementById('wp-pq-binder-client-context');
  const binderJobContext = document.getElementById('wp-pq-binder-job-context');
  const floatingMeetingEl = document.getElementById('wp-pq-floating-meeting');
  const floatingMeetingForm = document.getElementById('wp-pq-floating-meeting-form');
  const floatingMeetingSummary = document.getElementById('wp-pq-floating-meeting-summary');
  const floatingMeetingStartInput = floatingMeetingForm ? floatingMeetingForm.querySelector('input[name="starts_at"]') : null;
  const floatingMeetingEndInput = floatingMeetingForm ? floatingMeetingForm.querySelector('input[name="ends_at"]') : null;
  const floatingMeetingCloseBtn = document.getElementById('wp-pq-floating-meeting-close');
  const floatingMeetingSkipBtn = document.getElementById('wp-pq-floating-meeting-skip');

  let selectedTaskId = null;
  let floatingMeetingTaskId = null;
  let tasksCache = [];
  let calendar = null;
  let participantCache = [];
  let currentView = 'board';
  let taskPanelState = { taskId: null, messages: false, meetings: false, notes: false, files: false, participants: false };
  let selectedApprovalTaskIds = new Set();
  let selectedBatchTaskIds = new Set();
  let filterState = { clientUserId: 0, billingBucketId: 0 };
  let filterOptions = { canViewAll: !!window.wpPqConfig.canViewAll, clients: [], buckets: [] };
  let createFormState = { clientUserId: 0, billingBucketId: 0 };
  let taskFilter = { mode: 'all', value: 'all' };
  let workersCache = [];
  let workersCacheKey = '';
  let boardSortInstances = [];
  let boardDragActive = false;
  let boardDragLockUntil = 0;
  let activeTaskRecord = null;

  function apiErrorMessage(resp, body) {
    const code = String(body && body.code ? body.code : '');
    const message = String(body && body.message ? body.message : '');

    if (
      code === 'rest_cookie_invalid_nonce'
      || /cookie check failed/i.test(message)
      || (/nonce/i.test(message) && /invalid|expired|failed/i.test(message))
      || ((resp.status === 401 || resp.status === 403) && /cookie|nonce|rest/i.test(message))
    ) {
      return 'Your session expired. Refresh the page and try again.';
    }

    return message || 'Request failed';
  }

  async function api(path, options) {
    const requestOptions = {
      ...(options || {}),
      credentials: 'same-origin',
    };
    requestOptions.headers = {
      ...headers,
      ...((options && options.headers) || {}),
    };
    if (requestOptions.body instanceof FormData) {
      delete requestOptions.headers['Content-Type'];
    } else if (!requestOptions.headers['Content-Type']) {
      requestOptions.headers['Content-Type'] = 'application/json';
    }

    let resp;
    try {
      resp = await fetch(apiRoot + path, requestOptions);
    } catch (err) {
      throw new Error('Connection failed. Please try again.');
    }

    const text = await resp.text();
    let payload = {};
    if (text) {
      try {
        payload = JSON.parse(text);
      } catch (err) {
        payload = { message: text };
      }
    }

    if (!resp.ok) {
      throw new Error(apiErrorMessage(resp, payload));
    }

    return payload;
  }

  function toastStack() {
    let stack = document.getElementById('wp-pq-toast-stack');
    if (stack) return stack;
    stack = document.createElement('div');
    stack.id = 'wp-pq-toast-stack';
    stack.className = 'wp-pq-toast-stack';
    document.body.appendChild(stack);
    return stack;
  }

  const dragTransitions = {
    pending_approval: ['approved', 'needs_clarification'],
    needs_clarification: ['approved', 'in_progress'],
    approved: ['in_progress', 'needs_clarification'],
    in_progress: ['needs_clarification', 'needs_review', 'delivered'],
    needs_review: ['in_progress', 'delivered'],
    delivered: ['in_progress', 'needs_clarification', 'needs_review'],
  };

  let suppressedDragAlerts;
  try { suppressedDragAlerts = JSON.parse(localStorage.getItem('wp_pq_suppress_drag_alerts') || '{}'); }
  catch (_) { suppressedDragAlerts = {}; }

  function alert(message, type, options) {
    const opts = options || {};
    const suppressKey = opts.suppressKey || null;
    const duration = opts.duration || 3200;
    if (suppressKey && suppressedDragAlerts[suppressKey]) return;
    const stack = toastStack();
    const toast = document.createElement('div');
    toast.className = 'wp-pq-toast ' + (type || 'error');
    const textEl = document.createElement('div');
    textEl.textContent = String(message || 'Something went wrong.');
    toast.appendChild(textEl);
    if (suppressKey) {
      const dismissLabel = document.createElement('label');
      dismissLabel.className = 'wp-pq-toast-dismiss';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.addEventListener('change', () => {
        if (cb.checked) {
          suppressedDragAlerts[suppressKey] = true;
          localStorage.setItem('wp_pq_suppress_drag_alerts', JSON.stringify(suppressedDragAlerts));
        }
      });
      dismissLabel.appendChild(cb);
      dismissLabel.appendChild(document.createTextNode("\u2019t show this again"));
      toast.appendChild(dismissLabel);
    }
    stack.appendChild(toast);
    window.setTimeout(() => {
      toast.classList.add('is-leaving');
      window.setTimeout(() => toast.remove(), 220);
    }, duration);
  }

  function isDragAllowed(from, to) {
    if (from === to) return true;
    const allowed = dragTransitions[from];
    return allowed ? allowed.indexOf(to) !== -1 : false;
  }

  function dragBlockedMessage(from, to) {
    const fromLabel = humanizeToken(from);
    const toLabel = humanizeToken(to);
    const allowed = (dragTransitions[from] || []).map(humanizeToken);
    return fromLabel + ' cannot move directly to ' + toLabel + '. Allowed: ' + allowed.join(', ') + '.';
  }

  function parseOwnerIds(raw) {
    if (!raw) return [];
    return String(raw).split(',').map((v) => parseInt(v.trim(), 10)).filter((v) => Number.isInteger(v) && v > 0);
  }

  function escapeHtml(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function humanizeToken(value) {
    const key = String(value || '');
    if (tokenLabels[key]) return tokenLabels[key];
    return key
      .split('_')
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
  }

  function truncateText(value, maxLength) {
    const text = String(value || '').trim();
    if (!text || text.length <= maxLength) return text;
    return text.slice(0, maxLength - 1).trimEnd() + '...';
  }

  function canDeleteTask(task) {
    if (!task) return false;
    if (!!window.wpPqConfig.canApprove) return true;
    const currentUserId = parseInt(window.wpPqConfig.currentUserId || 0, 10) || 0;
    return currentUserId > 0
      && parseInt(task.submitter_id || 0, 10) === currentUserId
      && ['pending_approval', 'needs_clarification'].includes(normalizeStatus(task.status))
      && !(parseInt(task.statement_id || 0, 10) || 0)
      && !(parseInt(task.work_log_id || 0, 10) || 0)
      && !['batched', 'statement_sent', 'paid'].includes(String(task.billing_status || ''));
  }

  function removeTaskById(taskId) {
    tasksCache = tasksCache.filter((task) => !idEq(task.id, taskId));
    selectedApprovalTaskIds.delete(taskId);
    selectedBatchTaskIds.delete(taskId);
    if (activeTaskRecord && idEq(activeTaskRecord.id, taskId)) {
      activeTaskRecord = null;
    }
    if (idEq(selectedTaskId, taskId)) {
      selectedTaskId = null;
    }
  }

  function formatDateTime(value) {
    if (!value) return '';
    const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T') + 'Z';
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleString();
  }

  function formatCardDateTime(value) {
    if (!value) return '';
    const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T') + 'Z';
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  }

  function toLocalDatetimeValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
  }

  function roundUpToQuarterHour(date) {
    const rounded = new Date(date.getTime());
    rounded.setSeconds(0, 0);
    const minutes = rounded.getMinutes();
    const nextQuarter = Math.ceil(minutes / 15) * 15;
    if (nextQuarter === 60) {
      rounded.setHours(rounded.getHours() + 1, 0, 0, 0);
    } else {
      rounded.setMinutes(nextQuarter, 0, 0);
    }
    return rounded;
  }

  function autoFillMeetingEnd() {
    if (!meetingStartInput || !meetingEndInput || !meetingStartInput.value) return;
    const start = new Date(meetingStartInput.value);
    if (Number.isNaN(start.getTime())) return;
    const end = new Date(start.getTime() + 30 * 60 * 1000);
    meetingEndInput.value = toLocalDatetimeValue(end);
    meetingEndInput.dataset.autoManaged = '1';
  }

  function seedMeetingForm(force) {
    if (!meetingStartInput || !meetingEndInput) return;
    if (!force && (meetingStartInput.value || meetingEndInput.value)) return;
    const start = roundUpToQuarterHour(new Date());
    meetingStartInput.value = toLocalDatetimeValue(start);
    autoFillMeetingEnd();
  }

  function resetTaskPanelState(taskId) {
    taskPanelState = {
      taskId: taskId || null,
      messages: false,
      meetings: false,
      notes: false,
      files: false,
      participants: false,
    };
  }

  function ensureTaskPanelState(taskId) {
    if (taskPanelState.taskId !== taskId) {
      resetTaskPanelState(taskId);
    }
  }

  function reorderTasksCache(taskIds) {
    if (!Array.isArray(taskIds) || !taskIds.length) return;
    const order = new Map(taskIds.map((taskId, index) => [parseInt(taskId, 10), index]));
    tasksCache.sort((left, right) => {
      const leftIndex = order.has(left.id) ? order.get(left.id) : Number.MAX_SAFE_INTEGER;
      const rightIndex = order.has(right.id) ? order.get(right.id) : Number.MAX_SAFE_INTEGER;
      if (leftIndex !== rightIndex) return leftIndex - rightIndex;
      return (left.queue_position || 0) - (right.queue_position || 0);
    });
    tasksCache = tasksCache.map((task, index) => ({
      ...task,
      queue_position: index + 1,
    }));
  }

  function syncOrderFromBoardDom() {
    if (!boardEl) return;
    const orderedIds = Array.from(boardEl.querySelectorAll('.wp-pq-task-card'))
      .map((card) => parseInt(card.dataset.id || '0', 10))
      .filter((taskId) => taskId > 0);
    reorderTasksCache(orderedIds);
  }

  function messageItemHtml(msg) {
    return '<div class="msg-author">' + escapeHtml(msg.author_name || 'Collaborator') + ' · ' + escapeHtml(formatDateTime(msg.created_at)) + '</div>' +
      '<div>' + escapeHtml(msg.body || '') + '</div>';
  }

  function noteItemHtml(note) {
    return '<div class="msg-author">' + escapeHtml(note.author_name || 'Collaborator') + ' · ' + escapeHtml(formatDateTime(note.created_at)) + '</div>' +
      '<div>' + escapeHtml(note.body || '') + '</div>';
  }

  function meetingItemHtml(meeting, invitee) {
    const start = formatDateTime(meeting.starts_at);
    const end = formatDateTime(meeting.ends_at);
    return '<div><strong>Google Meet</strong></div>' +
      (start ? '<div>Starts: ' + escapeHtml(start) + '</div>' : '') +
      (end ? '<div>Ends: ' + escapeHtml(end) + '</div>' : '') +
      (meeting.meeting_url ? '<div><a href="' + encodeURI(meeting.meeting_url) + '" target="_blank" rel="noopener">Open meeting link</a></div>' : '') +
      '<div class="msg-author">Invitee: ' + escapeHtml(invitee) + '</div>';
  }

  // fileItemHtml removed — file exchange replaced by link field.

  function currentTaskQuery() {
    const params = new URLSearchParams();
    if (filterState.clientUserId > 0) {
      params.set('client_id', String(filterState.clientUserId));
    }
    if (filterState.billingBucketId > 0) {
      params.set('billing_bucket_id', String(filterState.billingBucketId));
    }
    return params.toString();
  }

  function apiPathWithFilters(path) {
    const query = currentTaskQuery();
    if (!query) return path;
    return path + (path.includes('?') ? '&' : '?') + query;
  }

  function setFilterState(nextState) {
    filterState = {
      clientUserId: Math.max(0, parseInt(nextState.clientUserId || 0, 10) || 0),
      billingBucketId: Math.max(0, parseInt(nextState.billingBucketId || 0, 10) || 0),
    };
  }

  function visibleBuckets() {
    if (!Array.isArray(filterOptions.buckets)) return [];
    if (!filterState.clientUserId) return filterOptions.buckets;
    return filterOptions.buckets.filter((bucket) => parseInt(bucket.client_id, 10) === filterState.clientUserId);
  }

  function syncFilterControls() {
    if (!boardFiltersEl) return;

    const canViewAll = !!filterOptions.canViewAll;
    const clientOptions = Array.isArray(filterOptions.clients) ? filterOptions.clients : [];
    const bucketOptions = visibleBuckets();

    boardFiltersEl.hidden = !canViewAll;

    if (clientFilterWrap && clientFilterEl) {
      clientFilterWrap.hidden = !canViewAll;
      if (canViewAll) {
        clientFilterEl.innerHTML = '<option value="0">All clients</option>' + clientOptions.map((client) => (
          '<option value="' + escapeHtml(client.id) + '">' + escapeHtml(client.label || client.name || ('Client #' + client.id)) + '</option>'
        )).join('');
        clientFilterEl.value = String(filterState.clientUserId || 0);
      }
    }

    const bucketIsValid = bucketOptions.some((bucket) => parseInt(bucket.id, 10) === filterState.billingBucketId);
    if (!bucketIsValid) {
      filterState.billingBucketId = 0;
    }

    syncCreateFormContext();
    renderJobNav();
  }

  function currentCreateClientId() {
    if (window.wpPqConfig.canViewAll && createClientEl) {
      return createFormState.clientUserId || (parseInt(createClientEl.value || '0', 10) || 0);
    }
    return filterState.clientUserId || 0;
  }

  function syncCreateFormContext() {
    if (!createForm || !createBucketEl) return;

    const canViewAll = !!window.wpPqConfig.canViewAll;
    const clientOptions = Array.isArray(filterOptions.clients) ? filterOptions.clients : [];

    if (createClientWrap && createClientEl) {
      createClientWrap.hidden = !canViewAll;
      if (canViewAll) {
        createClientEl.innerHTML = '<option value="0">Select client</option>' + clientOptions.map((client) => (
          '<option value="' + escapeHtml(client.id) + '">' + escapeHtml(client.label || client.name || ('Client #' + client.id)) + '</option>'
        )).join('');
        createClientEl.value = String(createFormState.clientUserId || 0);
      }
    }

    const createClientId = currentCreateClientId();
    const selectedClient = clientOptions.find((client) => parseInt(client.id, 10) === createClientId) || null;
    const bucketOptions = (Array.isArray(filterOptions.buckets) ? filterOptions.buckets : []).filter((bucket) => (
      createClientId > 0 ? parseInt(bucket.client_id, 10) === createClientId : !canViewAll
    ));

    if (window.wpPqConfig.canViewAll && createClientId <= 0) {
      createBucketEl.disabled = true;
      createBucketEl.innerHTML = '<option value="0">Choose a client first</option>';
      createFormState.billingBucketId = 0;
      if (createNewBucketWrap) createNewBucketWrap.hidden = true;
      if (createNewBucketEl) createNewBucketEl.value = '';
      return;
    }

    createBucketEl.disabled = false;
    const allowInlineCreate = !!window.wpPqConfig.canApprove && createClientId > 0;
    const createOptionValue = -1;
    createBucketEl.innerHTML = '<option value="0">' + (bucketOptions.length ? 'Select job' : 'Choose a job') + '</option>' +
      bucketOptions.map((bucket) => (
        '<option value="' + escapeHtml(bucket.id) + '">' + escapeHtml(bucket.label || bucket.bucket_name || 'Job') + '</option>'
      )).join('') +
      (allowInlineCreate ? '<option value="' + createOptionValue + '">' + (bucketOptions.length ? '+ Create new job' : 'Create the first job') + '</option>' : '');

    const bucketIsValid = bucketOptions.some((bucket) => parseInt(bucket.id, 10) === createFormState.billingBucketId);
    const wantsNewBucket = createFormState.billingBucketId === createOptionValue;
    if (!bucketIsValid && !wantsNewBucket) {
      createFormState.billingBucketId = bucketOptions.length === 1 ? parseInt(bucketOptions[0].id, 10) : 0;
    }
    createBucketEl.value = String(createFormState.billingBucketId || 0);

    if (createNewBucketWrap) {
      createNewBucketWrap.hidden = !allowInlineCreate || !(createFormState.billingBucketId === createOptionValue || bucketOptions.length === 0);
    }
    if (createNewBucketEl) {
      const clientLabel = selectedClient ? String(selectedClient.label || selectedClient.name || '').trim() : '';
      const suggestedName = clientLabel ? (clientLabel + ' - Main') : 'Client Name - Main';
      createNewBucketEl.placeholder = suggestedName;
      if (allowInlineCreate && (createFormState.billingBucketId === createOptionValue || bucketOptions.length === 0) && !String(createNewBucketEl.value || '').trim()) {
        createNewBucketEl.value = suggestedName;
      }
    }
  }

  function upsertTask(task) {
    if (!task || !task.id) return;
    const index = tasksCache.findIndex((item) => idEq(item.id, task.id));
    if (index >= 0) {
      tasksCache[index] = task;
      if (activeTaskRecord && idEq(activeTaskRecord.id, task.id)) {
        activeTaskRecord = task;
      }
      return;
    }
    tasksCache.push(task);
    if (activeTaskRecord && idEq(activeTaskRecord.id, task.id)) {
      activeTaskRecord = task;
    }
  }

  function replaceTasks(tasks) {
    tasksCache = Array.isArray(tasks) ? tasks.slice() : [];
    if (activeTaskRecord && activeTaskRecord.id) {
      activeTaskRecord = getTaskById(activeTaskRecord.id) || activeTaskRecord;
    }
  }

  function pruneBatchSelection() {
    selectedApprovalTaskIds = new Set(
      Array.from(selectedApprovalTaskIds).filter((taskId) => {
        const task = tasksCache.find((item) => idEq(item.id, taskId));
        return !!task && normalizeStatus(task.status) === 'pending_approval';
      })
    );
    selectedBatchTaskIds = new Set(
      Array.from(selectedBatchTaskIds).filter((taskId) => {
        const task = tasksCache.find((item) => idEq(item.id, taskId));
        return !!task && normalizeStatus(task.status) === 'delivered' && task.is_billable && task.billing_status === 'unbilled';
      })
    );
  }

  function renderTaskCollections() {
    pruneBatchSelection();
    const scopedTasks = tasksCache.slice();
    const visibleTasks = applyTaskFilter(scopedTasks);

    if (boardEl) {
      renderBoard(visibleTasks);
      initBoardSort();
    } else {
      renderTaskList(visibleTasks);
      if (selectedTaskId === null && visibleTasks[0]) {
        selectedTaskId = visibleTasks[0].id;
      }
    }

    updateBinderUi(scopedTasks, visibleTasks);
    highlightSelected();
  }

  function applyTaskFilter(tasks) {
    if (taskFilter.mode === 'responsibility' && taskFilter.value === 'awaiting_me') {
      return tasks.filter((task) => parseInt(task.action_owner_id || 0, 10) === parseInt(window.wpPqConfig.currentUserId || 0, 10));
    }
    if (taskFilter.mode === 'responsibility' && taskFilter.value === 'awaiting_client') {
      const myId = parseInt(window.wpPqConfig.currentUserId || 0, 10);
      return tasks.filter((task) => !!task.action_owner_is_client && parseInt(task.action_owner_id || 0, 10) !== myId && !['delivered', 'done', 'archived'].includes(normalizeStatus(task.status)));
    }
    if (taskFilter.mode === 'status' && taskFilter.value === 'pending_approval') {
      return tasks.filter((task) => normalizeStatus(task.status) === 'pending_approval');
    }
    if (taskFilter.mode === 'status' && taskFilter.value === 'needs_review') {
      return tasks.filter((task) => normalizeStatus(task.status) === 'needs_review');
    }
    if (taskFilter.mode === 'status' && taskFilter.value === 'delivered') {
      return tasks.filter((task) => normalizeStatus(task.status) === 'delivered');
    }
    if (taskFilter.mode === 'status' && taskFilter.value === 'unbilled') {
      return tasks.filter((task) => normalizeStatus(task.status) === 'delivered' && !!task.is_billable && String(task.billing_status || '') === 'unbilled');
    }
    if (taskFilter.mode === 'status' && taskFilter.value === 'urgent') {
      return tasks.filter((task) => String(task.priority || '') === 'urgent');
    }
    return tasks;
  }

  function updateBinderUi(scopedTasks, visibleTasks) {
    const selectedClient = (filterOptions.clients || []).find((client) => parseInt(client.id, 10) === filterState.clientUserId);
    const selectedBucket = (filterOptions.buckets || []).find((bucket) => parseInt(bucket.id, 10) === filterState.billingBucketId);
    if (binderClientContext) {
      binderClientContext.textContent = selectedClient
        ? (selectedClient.name || selectedClient.label || 'Selected client')
        : (window.wpPqConfig.canViewAll ? 'All clients' : 'Your client workspace');
    }
    if (binderJobContext) {
      const countLabel = (visibleTasks || []).length + ' visible tasks';
      binderJobContext.textContent = selectedBucket
        ? ((selectedBucket.label || selectedBucket.bucket_name || 'Selected job') + ' · ' + countLabel)
        : ('All jobs · ' + countLabel);
    }
    renderUnifiedFilters(scopedTasks || []);
  }

  function renderUnifiedFilters(tasks) {
    if (!filterListEl) return;

    const currentId = parseInt(window.wpPqConfig.currentUserId || 0, 10);
    let urgentCount = 0, approvalCount = 0, reviewCount = 0, deliveredCount = 0, unbilledCount = 0, awaitingMeCount = 0, awaitingClientCount = 0;
    for (let i = 0; i < tasks.length; i++) {
      const task = tasks[i];
      const status = normalizeStatus(task.status);
      const ownerId = parseInt(task.action_owner_id || 0, 10);
      if (task.priority === 'urgent') urgentCount++;
      if (status === 'pending_approval') approvalCount++;
      if (status === 'needs_review') reviewCount++;
      if (status === 'delivered') {
        deliveredCount++;
        if (task.is_billable && task.billing_status === 'unbilled') unbilledCount++;
      }
      if (ownerId === currentId) awaitingMeCount++;
      if (task.action_owner_is_client && ownerId !== currentId && status !== 'delivered' && status !== 'done' && status !== 'archived') awaitingClientCount++;
    }

    const groups = [
      {
        label: '',
        items: [
          { mode: 'all', value: 'all', label: 'All tasks', count: tasks.length },
        ],
      },
      {
        label: 'By responsibility',
        items: [
          { mode: 'responsibility', value: 'awaiting_me', label: 'Awaiting me', count: awaitingMeCount },
          { mode: 'responsibility', value: 'awaiting_client', label: 'Awaiting client', count: awaitingClientCount },
        ],
      },
      {
        label: 'By status',
        items: [
          { mode: 'status', value: 'pending_approval', label: 'Awaiting approval', count: approvalCount },
          { mode: 'status', value: 'needs_review', label: 'Awaiting review', count: reviewCount },
          { mode: 'status', value: 'delivered', label: 'Delivered', count: deliveredCount },
          { mode: 'status', value: 'unbilled', label: 'Unbilled', count: unbilledCount },
          { mode: 'status', value: 'urgent', label: 'Urgent', count: urgentCount, tone: urgentCount > 0 ? 'warning' : 'default' },
        ],
      },
    ];

    let html = '';
    let prevGroupLabel = '';
    for (let g = 0; g < groups.length; g++) {
      const group = groups[g];
      if (group.label && prevGroupLabel) {
        html += '<span class="wp-pq-filter-sep">|</span>';
      }
      for (let i = 0; i < group.items.length; i++) {
        const item = group.items[i];
        const isActive = taskFilter.mode === item.mode && taskFilter.value === item.value;
        html += '<label class="wp-pq-filter-check' +
          (isActive ? ' is-active' : '') +
          '" data-filter-mode="' + escapeHtml(item.mode) + '" data-filter-value="' + escapeHtml(item.value) + '">' +
          '<input type="checkbox"' + (isActive ? ' checked' : '') + '> ' +
          escapeHtml(item.label) +
          ' <span class="wp-pq-filter-check-count">' + escapeHtml(item.count) + '</span>' +
          '</label>';
      }
      prevGroupLabel = group.label;
    }
    filterListEl.innerHTML = html;
  }

  function renderJobNav() {
    if (!jobNavWrap || !jobNavEl) return;

    const bucketOptions = visibleBuckets();
    const canViewAll = !!filterOptions.canViewAll;
    const shouldShow = bucketOptions.length > 0 && (!canViewAll || filterState.clientUserId > 0 || bucketOptions.length > 1);
    jobNavWrap.hidden = !shouldShow;

    if (!shouldShow) {
      jobNavEl.innerHTML = '';
      return;
    }

    const buttons = [
      '<button class="button ' + (filterState.billingBucketId === 0 ? 'is-active' : '') + '" type="button" data-job-id="0">' +
        '<span class="wp-pq-job-row-main"><span class="wp-pq-row-icon" aria-hidden="true">' + binderIcons.jobs + '</span><span>All jobs</span></span>' +
      '</button>',
    ];

    bucketOptions.forEach((bucket) => {
      const bucketId = parseInt(bucket.id, 10) || 0;
      buttons.push(
        '<button class="button ' + (bucketId === filterState.billingBucketId ? 'is-active' : '') + '" type="button" data-job-id="' + escapeHtml(bucketId) + '">' +
          '<span class="wp-pq-job-row-main"><span class="wp-pq-row-icon" aria-hidden="true">' + binderIcons.jobs + '</span><span>' + escapeHtml(bucket.label || bucket.bucket_name || 'Job') + '</span></span>' +
        '</button>'
      );
    });

    jobNavEl.innerHTML = buttons.join('');
  }

  async function syncTaskWorkspace(options) {
    const reloadActivePane = !!(options && options.reloadActivePane);
    const refreshCalendar = !!(options && options.refreshCalendar);
    const forceSelect = !!(options && options.forceSelect);

    // Look up the selected task in cache first; fall back to the active
    // record so the drawer survives when the task is filtered out of the
    // current board view (e.g. after a status change).
    const current = selectedTaskId
      ? (getTaskById(selectedTaskId) || (activeTaskRecord && idEq(activeTaskRecord.id, selectedTaskId) ? activeTaskRecord : null))
      : null;
    if (current) {
      await updateTaskSummary(current);
      if ((!boardEl || drawerIsOpen() || forceSelect) && reloadActivePane) {
        ensureTaskPanelState(current.id);
        await Promise.all([loadParticipants(), loadActiveWorkspacePane()]);
      }
    } else if (!selectedTaskId) {
      // Only reset the drawer when there is genuinely no selected task.
      // If selectedTaskId is set but the task is missing from both cache
      // and activeTaskRecord, leave the drawer alone — a concurrent load
      // will repopulate it.
      resetTaskSummary();
      resetTaskPanelState(null);
      renderEmptyStream(messageList, 'Open a task to see its messages.');
      renderEmptyStream(meetingList, 'Open a task to see meeting details.');
      renderEmptyStream(noteList, 'Open a task to see its sticky notes.');
      renderEmptyStream(fileList, 'Open a task to see its files.');
      participantCache = [];
      renderMentionChips();
      if (boardEl) {
        closeDrawer();
      }
    }

    if (refreshCalendar && currentView === 'calendar') {
      await loadCalendarEvents();
    }
  }

  async function refreshFromCache(options) {
    if (!options || options.renderCollections !== false) {
      if (options && options.skipBoardRender && boardEl) {
        pruneBatchSelection();
        syncOrderFromBoardDom();
        const scopedTasks = tasksCache.slice();
        const visibleTasks = applyTaskFilter(scopedTasks);
        updateBinderUi(scopedTasks, visibleTasks);
        highlightSelected();
      } else {
        renderTaskCollections();
      }
    }
    await syncTaskWorkspace(options || {});
  }

  function renderFactList(facts, className) {
    if (!Array.isArray(facts) || !facts.length) return '';
    return '<dl class="' + escapeHtml(className) + '">' + facts.map((fact) => (
      '<div class="wp-pq-fact-row">' +
        '<dt>' + escapeHtml(fact.label) + '</dt>' +
        '<dd>' + (fact.html ? fact.value : escapeHtml(fact.value)) + '</dd>' +
      '</div>'
    )).join('') + '</dl>';
  }

  function taskContextMarkup(task) {
    const facts = [
      { label: 'Task', value: '#' + task.id },
      { label: 'Job', value: task.bucket_name || 'No job set' },
      { label: 'Priority', value: humanizeToken(task.priority || 'normal') },
    ];

    if (task.requested_deadline) {
      facts.push({ label: 'Requested', value: formatDateTime(task.requested_deadline) });
    }
    if (task.due_at) {
      facts.push({ label: 'Due', value: formatDateTime(task.due_at) });
    }
    if (task.delivered_at) {
      facts.push({ label: 'Delivered', value: formatDateTime(task.delivered_at) });
    }
    if (!task.is_billable) {
      facts.push({ label: 'Billing', value: 'Not billable' });
    } else if (task.billing_status) {
      facts.push({ label: 'Billing', value: humanizeToken(task.billing_status) });
    }
    if (task.statement_code) {
      facts.push({ label: 'Invoice Draft', value: task.statement_code });
    }
    if (task.needs_meeting) {
      facts.push({ label: 'Meeting', value: 'Requested' });
    }
    if (task.files_link) {
      facts.push({ label: 'Files', value: '<a href="' + encodeURI(task.files_link) + '" target="_blank" rel="noopener">Open folder</a>', html: true });
    }

    return renderFactList(facts, 'wp-pq-drawer-facts');
  }

  function taskGuidance(task) {
    const status = normalizeStatus(task.status);
    if (status === 'pending_approval') {
      return window.wpPqConfig.canApprove
        ? 'Awaiting your approval. Approve this request to move it into active workflow, or send it back for clarification.'
        : 'Awaiting approval. We will either approve this request or send it back for clarification.';
    }
    if (status === 'needs_clarification') return 'Awaiting clarification from the requester before this can move forward.';
    if (status === 'approved') return task.action_owner_name
      ? 'Approved and ready to start. Confirm the action owner, then move it into active work.'
      : 'Approved, but no action owner is set yet. Assign responsibility before starting work.';
    if (status === 'in_progress') return 'Work is underway. When execution is complete, send it to review or return it for clarification.';
    if (status === 'needs_review') return 'Review the work here, then deliver it or reopen it for more work.';
    if (status === 'delivered') return task.billing_status === 'batched'
      ? 'This delivered task has already been added to an invoice draft.'
      : (!task.is_billable
        ? 'Delivered and ready to close. This task is marked non-billable, so it will stay out of billing rollups.'
        : 'Delivered work stays here until you mark it done, add it to an invoice draft, or reopen it.');
    if (status === 'done') return 'This task is complete. A manager can archive it or reopen it.';
    if (status === 'archived') return 'This task is archived and no longer appears in active workflow views.';
    return '';
  }

  function taskActorLabel(task) {
    if (task.action_owner_name) return 'Awaiting ' + task.action_owner_name;
    if (task.client_account_name && normalizeStatus(task.status) === 'needs_clarification') return 'Awaiting ' + task.client_account_name;
    return 'Awaiting assignment';
  }

  function personInitials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function personAvatarHtml(name, role, tone) {
    const safeName = String(name || '').trim();
    const label = role + ': ' + (safeName || 'Unassigned');
    return '<span class="wp-pq-task-avatar wp-pq-task-avatar-' + escapeHtml(tone) + '" title="' + escapeHtml(label) + '">' +
      escapeHtml(personInitials(safeName)) +
      '</span>';
  }

  function priorityMarkerHtml(priority) {
    const normalized = String(priority || 'normal');
    return '<span class="wp-pq-priority-marker priority-' + escapeHtml(normalized) + '" data-tooltip="' + escapeHtml(humanizeToken(normalized) + ' priority') + '">▲</span>';
  }


  function assignmentFacts(task) {
    const requester = task.submitter_name || 'Unspecified';
    const client = task.client_account_name || task.client_name || requester;
    const owner = task.action_owner_name || 'Unassigned';
    return renderFactList([
      { label: 'Requester', value: requester },
      { label: 'Client', value: client },
      { label: 'Action owner', value: owner },
    ], 'wp-pq-responsibility-facts');
  }

  async function loadWorkers(task) {
    if (!window.wpPqConfig.canAssign) return [];

    const taskId = task && task.id ? parseInt(task.id, 10) || 0 : 0;
    const clientId = task && task.client_id ? parseInt(task.client_id, 10) || 0 : (filterState.clientUserId || currentCreateClientId() || 0);
    const billingBucketId = task && task.billing_bucket_id ? parseInt(task.billing_bucket_id, 10) || 0 : (filterState.billingBucketId || 0);
    const params = new URLSearchParams();
    if (taskId > 0) {
      params.set('task_id', String(taskId));
    } else {
      if (clientId > 0) params.set('client_id', String(clientId));
      if (billingBucketId > 0) params.set('billing_bucket_id', String(billingBucketId));
    }

    const cacheKey = params.toString() || 'global';
    if (workersCache.length && workersCacheKey === cacheKey) return workersCache;

    const data = await api('workers' + (cacheKey ? ('?' + cacheKey) : ''), { method: 'GET' });
    workersCache = Array.isArray(data.workers) ? data.workers : [];
    workersCacheKey = cacheKey;
    return workersCache;
  }

  function syncAssignmentPanel(task) {
    if (!assignmentPanelEl || !assignmentFactsEl || !assignmentSelectEl || !assignmentSaveBtn) return;
    if (!window.wpPqConfig.canAssign || !task) {
      assignmentPanelEl.hidden = true;
      return;
    }

    assignmentPanelEl.hidden = false;
    assignmentFactsEl.innerHTML = assignmentFacts(task);

    const options = ['<option value="0">Unassigned</option>'].concat(
      workersCache.map((worker) => {
        const detail = worker.scope_label || worker.email || '';
        return '<option value="' + escapeHtml(worker.id) + '">' + escapeHtml(worker.name + (detail ? ' · ' + detail : '')) + '</option>';
      })
    );
    assignmentSelectEl.innerHTML = options.join('');
    assignmentSelectEl.value = String(parseInt(task.action_owner_id || 0, 10) || 0);
    assignmentSaveBtn.disabled = false;
  }

  function syncPriorityPanel(task) {
    if (!priorityPanelEl || !prioritySelectEl || !prioritySaveBtn) return;
    if (!window.wpPqConfig.canApprove || !task) {
      priorityPanelEl.hidden = true;
      return;
    }

    priorityPanelEl.hidden = false;
    prioritySelectEl.value = String(task.priority || 'normal');
    prioritySaveBtn.disabled = false;
  }

  function ensureAssigneePresent(task) {
    if (!task || !task.action_owner_id || !task.action_owner_name) return;
    const exists = workersCache.some((worker) => parseInt(worker.id, 10) === parseInt(task.action_owner_id, 10));
    if (!exists) {
      workersCache = workersCache.concat([{
        id: task.action_owner_id,
        name: task.action_owner_name,
        email: task.action_owner_email || '',
        roles: [],
      }]);
    }
  }

  async function loadActiveWorkspacePane(options) {
    if (!selectedTaskId) return;
    const config = options || {};

    if (panelMeetings && !panelMeetings.hidden) {
      await loadMeetings(config);
      return;
    }

    if (panelNotes && !panelNotes.hidden) {
      await loadNotes(config);
      return;
    }

    if (panelFiles && !panelFiles.hidden) {
      await loadFiles(config);
      return;
    }

    await loadMessages(config);
  }

  async function loadMeetings(options) {
    if (!meetingList || !meetingSummaryEl) return;
    if (!selectedTaskId) {
      renderEmptyStream(meetingList, 'Open a task to see meeting details.');
      meetingSummaryEl.textContent = 'Meeting details will appear here when requested.';
      return;
    }

    const task = getTaskById(selectedTaskId);
    if (taskPanelState.meetings && !(options && options.force)) {
      const invitee = task && task.submitter_email ? task.submitter_email : 'the task requester';
      meetingSummaryEl.textContent = 'Google Meet will invite ' + invitee + '.';
      return;
    }
    const data = await api('tasks/' + selectedTaskId + '/meetings', { method: 'GET' });
    const meetings = data.meetings || [];
    meetingList.innerHTML = '';
    taskPanelState.meetings = true;

    const invitee = task && task.submitter_email ? task.submitter_email : 'the task requester';
    if (meetings.length) {
      meetingSummaryEl.textContent = 'Google Meet will invite ' + invitee + '.';
      meetings.forEach((meeting) => {
        const li = document.createElement('li');
        li.innerHTML = meetingItemHtml(meeting, invitee);
        meetingList.appendChild(li);
      });
      return;
    }

    meetingSummaryEl.textContent = task && task.needs_meeting
      ? 'Meeting requested. Schedule a Google Meet and invite ' + invitee + '.'
      : 'No meeting scheduled for this task.';
    renderEmptyStream(meetingList, task && task.needs_meeting ? 'No meeting is scheduled yet.' : 'Meeting is not requested for this task.');
  }

  function boardCard(task) {
    const card = document.createElement('article');
    card.className = 'wp-pq-task-card is-status-' + normalizeStatus(task.status || 'pending_approval').replaceAll('_', '-');
    card.dataset.id = task.id;

    const brief = truncateText(task.description || 'No request brief yet.', 160);
    const deadline = formatCardDateTime(task.requested_deadline || task.due_at);
    const clientName = task.client_account_name || task.client_name || task.submitter_name || 'Client';
    const actionOwnerName = task.action_owner_name || '';
    const metaBits = [];
    const cardActions = [];

    if (deadline) metaBits.push('<span>Due ' + escapeHtml(deadline) + '</span>');
    if (task.needs_meeting) metaBits.push('<span>Meeting requested</span>');
    if (!task.is_billable) {
      metaBits.push('<span>Non-billable</span>');
    } else if (task.billing_status === 'batched' && task.statement_code) {
      metaBits.push('<span>Invoice Draft ' + escapeHtml(task.statement_code) + '</span>');
    }

    if (task.note_count > 0) {
      cardActions.push('<span class="wp-pq-note-flag" data-tooltip="' + escapeHtml((task.latest_note_preview || (task.note_count + ' sticky notes'))) + '"></span>');
    }
    cardActions.push(priorityMarkerHtml(task.priority));

    const avatars = [];
    avatars.push(personAvatarHtml(actionOwnerName || 'Unassigned', 'Owner', 'owner'));
    if (!actionOwnerName || actionOwnerName !== clientName) {
      avatars.push(personAvatarHtml(clientName, 'Client', 'client'));
    }

    card.innerHTML =
      '<div class="wp-pq-task-card-top">' +
      '<div class="wp-pq-task-card-identity"><span class="wp-pq-task-id">#' + escapeHtml(task.id) + '</span></div>' +
      '<div class="wp-pq-task-card-actions">' + cardActions.join('') + '</div>' +
      '</div>' +
      '<h4>' + escapeHtml(task.title) + '</h4>' +
      '<p class="wp-pq-task-brief">' + escapeHtml(brief || 'No request brief yet.') + '</p>' +
      '<div class="wp-pq-task-meta">' +
      (task.bucket_name ? '<span class="wp-pq-task-tag">' + escapeHtml(task.bucket_name) + '</span>' : '') +
      (metaBits.length ? '<div class="wp-pq-task-meta-line">' + metaBits.join('<span class="wp-pq-task-meta-sep">·</span>') + '</div>' : '') +
      '</div>' +
      '<div class="wp-pq-task-footer">' +
      '<span class="wp-pq-task-awaiting">' + escapeHtml(taskActorLabel(task)) + '</span>' +
      '<div class="wp-pq-task-avatars">' + avatars.join('') + '</div>' +
      '</div>';

    card.addEventListener('click', () => {
      if (boardDragActive || Date.now() < boardDragLockUntil) return;
      selectTask(task.id, true);
    });
    return card;
  }

  function taskItem(task) {
    const li = document.createElement('li');
    li.className = 'wp-pq-task';
    li.dataset.id = task.id;

    const actionOwner = task.action_owner_name || '';
    const requestDetails = (task.description || '').trim();
    const requestedDeadline = formatDateTime(task.requested_deadline);
    const dueAt = formatDateTime(task.due_at);

    li.innerHTML =
      '<div class="title">' + escapeHtml(task.title) + '</div>' +
      '<div class="meta">Status: ' + escapeHtml(task.status) +
      ' | Priority: ' + escapeHtml(task.priority) +
      (actionOwner ? ' | Action owner: ' + escapeHtml(actionOwner) : '') +
      (task.needs_meeting ? ' | Meeting requested' : '') +
      '</div>' +
      '<div class="request">' +
      (requestDetails
        ? '<div><strong>Description:</strong> ' + escapeHtml(requestDetails) + '</div>'
        : '<div><strong>Description:</strong> (no details provided)</div>') +
      (requestedDeadline ? '<div><strong>Requested deadline:</strong> ' + escapeHtml(requestedDeadline) + '</div>' : '') +
      (dueAt ? '<div><strong>Due date:</strong> ' + escapeHtml(dueAt) + '</div>' : '') +
      '<div><strong>Task ID:</strong> ' + escapeHtml(task.id) + '</div>' +
      '</div>' +
      '<div class="actions">' + renderStatusButtons(task) + '</div>';

    li.addEventListener('click', () => selectTask(task.id, false));
    return li;
  }

  function renderStatusButtons(task) {
    const buttons = [];
    const billingLocked = parseInt(task.statement_id || 0, 10) > 0 || ['batched', 'statement_sent', 'paid'].includes(String(task.billing_status || ''));
    const canOperate = !!window.wpPqConfig.canApprove || !!window.wpPqConfig.canWork;
    const status = normalizeStatus(task.status);
    if (window.wpPqConfig.canApprove && status === 'pending_approval') {
      buttons.push(buttonHtml(task.id, 'approved', 'Approve'));
      buttons.push(buttonHtml(task.id, 'needs_clarification', 'Needs Clarification'));
    }
    if (window.wpPqConfig.canApprove && status === 'needs_clarification') {
      buttons.push(buttonHtml(task.id, 'approved', 'Approve'));
    }
    if (canOperate && status === 'needs_clarification') {
      buttons.push(buttonHtml(task.id, 'in_progress', 'In Progress'));
    }
    if (canOperate && status === 'approved') {
      buttons.push(buttonHtml(task.id, 'in_progress', 'In Progress'));
      if (window.wpPqConfig.canApprove) {
        buttons.push(buttonHtml(task.id, 'needs_clarification', 'Needs Clarification'));
      }
    }
    if (canOperate && status === 'in_progress') {
      buttons.push(buttonHtml(task.id, 'needs_review', 'Needs Review'));
      buttons.push(buttonHtml(task.id, 'delivered', 'Delivered'));
      if (window.wpPqConfig.canApprove) {
        buttons.push(buttonHtml(task.id, 'needs_clarification', 'Needs Clarification'));
      }
    }
    if (canOperate && status === 'needs_review') {
      buttons.push(buttonHtml(task.id, 'in_progress', 'In Progress'));
      buttons.push(buttonHtml(task.id, 'delivered', 'Delivered'));
    }
    if (canOperate && status === 'delivered') {
      if (window.wpPqModals && window.wpPqModals.completionModal) {
        buttons.push(buttonHtml(task.id, 'done', 'Mark Done'));
      }
      if (window.wpPqConfig.canApprove) {
        buttons.push(buttonHtml(task.id, 'archived', 'Archive'));
      }
      if (!billingLocked) {
        buttons.push(buttonHtml(task.id, 'in_progress', 'In Progress'));
        if (window.wpPqConfig.canApprove) {
          buttons.push(buttonHtml(task.id, 'needs_review', 'Needs Review'));
          buttons.push(buttonHtml(task.id, 'needs_clarification', 'Needs Clarification'));
        }
      }
    }
    if (canDeleteTask(task)) buttons.push(deleteButtonHtml(task.id));
    return buttons.join(' ');
  }

  function buttonHtml(taskId, status, label) {
    return '<button type="button" class="button wp-pq-status-btn" data-task-id="' + taskId + '" data-status="' + status + '">' + label + '</button>';
  }

  function deleteButtonHtml(taskId) {
    return '<button type="button" class="button wp-pq-delete-btn wp-pq-button-danger" data-task-id="' + taskId + '">Delete</button>';
  }

  function renderBoard(tasks) {
    if (!boardEl) return;
    boardEl.innerHTML = '';

    statusColumns.forEach((column) => {
      const tasksInColumn = tasks.filter((task) => normalizeStatus(task.status) === column.key);
      const columnEl = document.createElement('section');
      const shouldCollapse = tasksInColumn.length === 0;
      columnEl.className = 'wp-pq-board-column' + (shouldCollapse ? ' is-collapsed' : '');
      columnEl.dataset.status = column.key;
      let archiveAllBtn = '';
      if (column.key === 'delivered' && tasksInColumn.length > 0 && window.wpPqConfig.canApprove) {
        archiveAllBtn = ' <button type="button" class="button button-small wp-pq-archive-all-btn" title="Archive all delivered tasks">Archive All</button>';
      }
      columnEl.innerHTML =
        '<header class="wp-pq-board-column-head">' +
        '<h4>' + escapeHtml(column.label) + '</h4>' +
        '<span>' + tasksInColumn.length + '</span>' +
        archiveAllBtn +
        '</header>';

      const listEl = document.createElement('div');
      listEl.className = 'wp-pq-board-column-list';
      listEl.dataset.status = column.key;

      if (!tasksInColumn.length) {
        const emptyEl = document.createElement('p');
        emptyEl.className = 'wp-pq-empty-state';
        emptyEl.textContent = 'No tasks here yet.';
        listEl.appendChild(emptyEl);
      } else {
        tasksInColumn.forEach((task) => listEl.appendChild(boardCard(task)));
      }

      columnEl.appendChild(listEl);
      boardEl.appendChild(columnEl);
    });
  }

  function renderTaskList(tasks) {
    if (!taskList) return;
    taskList.innerHTML = '';
    tasks.forEach((task) => taskList.appendChild(taskItem(task)));
  }

  function initBoardSort() {
    if (!boardEl || typeof Sortable === 'undefined') return;

    boardSortInstances.forEach((instance) => {
      if (instance && typeof instance.destroy === 'function') {
        instance.destroy();
      }
    });
    boardSortInstances = [];

    boardEl.querySelectorAll('.wp-pq-board-column-list').forEach((columnEl) => {
      const sortable = Sortable.create(columnEl, {
        group: 'wp-pq-board',
        animation: 80,
        draggable: '.wp-pq-task-card',
        forceFallback: true,
        fallbackOnBody: true,
        fallbackTolerance: 3,
        fallbackClass: 'wp-pq-sortable-fallback',
        delayOnTouchOnly: true,
        touchStartThreshold: 3,
        emptyInsertThreshold: 60,
        scroll: true,
        scrollSensitivity: 120,
        scrollSpeed: 18,
        ghostClass: 'wp-pq-sortable-ghost',
        chosenClass: 'wp-pq-sortable-chosen',
        dragClass: 'wp-pq-sortable-drag',
        filter: 'button, input, label, a, select, textarea',
        preventOnFilter: false,
        onStart: () => {
          boardDragActive = true;
          boardEl.classList.add('is-dragging');
          if (appShellEl) appShellEl.classList.add('is-board-dragging');
          setBoardDragTarget(null);
        },
        onMove: (evt) => {
          setBoardDragTarget(evt && evt.to ? evt.to.closest('.wp-pq-board-column') : null);
        },
        onEnd: async (evt) => {
          boardDragActive = false;
          boardDragLockUntil = Date.now() + 100;
          boardEl.classList.remove('is-dragging');
          if (appShellEl) appShellEl.classList.remove('is-board-dragging');
          setBoardDragTarget(null);
          const sourceStatus = normalizeStatus(evt.from && evt.from.dataset ? evt.from.dataset.status : '');
          const targetStatus = normalizeStatus(evt.to && evt.to.dataset ? evt.to.dataset.status : sourceStatus);
          if (evt.oldIndex === evt.newIndex && sourceStatus === targetStatus) return;

          const movedTaskId = parseInt(evt.item.dataset.id, 10);
          const nextCard = evt.item.nextElementSibling;
          const prevCard = evt.item.previousElementSibling;
          let targetTaskId = 0;
          let position = 'before';

          if (nextCard && nextCard.dataset && nextCard.dataset.id) {
            targetTaskId = parseInt(nextCard.dataset.id, 10);
            position = 'before';
          } else if (prevCard && prevCard.dataset && prevCard.dataset.id) {
            targetTaskId = parseInt(prevCard.dataset.id, 10);
            position = 'after';
          }

          if (!movedTaskId) {
            await loadTasks();
            return;
          }

          if (sourceStatus !== targetStatus && !isDragAllowed(sourceStatus, targetStatus)) {
            alert(dragBlockedMessage(sourceStatus, targetStatus), 'warning', {
              suppressKey: sourceStatus + '_to_' + targetStatus,
              duration: 6000,
            });
            await loadTasks();
            return;
          }

          window.wpPqModals.setPendingMove({
            taskId: movedTaskId,
            targetTaskId: targetTaskId,
            position: position,
            sourceStatus: sourceStatus,
            targetStatus: targetStatus,
          });
          if (!window.wpPqModals.shouldPromptForMoveDecision(sourceStatus, targetStatus)) {
            try {
              selectedTaskId = movedTaskId;
              const result = await api('tasks/move', {
                method: 'POST',
                body: JSON.stringify({
                  task_id: movedTaskId,
                  target_task_id: targetTaskId || 0,
                  position: position,
                  target_status: targetStatus,
                  priority_direction: 'keep',
                  swap_due_dates: false,
                }),
              });
              if (result.task) {
                upsertTask(result.task);
                if (result.target_task) {
                  upsertTask(result.target_task);
                }
                await refreshFromCache({ reloadActivePane: false, refreshCalendar: currentView === 'calendar', skipBoardRender: true });
              } else {
                await loadTasks();
              }
            } catch (err) {
              alert(err.message);
              await loadTasks();
            }
            return;
          }

          window.wpPqModals.openMoveModal();
        },
      });
      boardSortInstances.push(sortable);
    });
  }

  function setBoardDragTarget(columnEl) {
    if (!boardEl) return;
    boardEl.querySelectorAll('.wp-pq-board-column.is-drag-target').forEach((el) => {
      if (el !== columnEl) el.classList.remove('is-drag-target');
    });
    if (columnEl) columnEl.classList.add('is-drag-target');
  }

  function idEq(a, b) {
    return a != null && b != null && parseInt(a, 10) === parseInt(b, 10);
  }

  function getTaskById(taskId) {
    return tasksCache.find((task) => idEq(task.id, taskId)) || null;
  }

  function getKnownTask(taskId) {
    const cacheTask = getTaskById(taskId);
    if (cacheTask) return cacheTask;
    if (activeTaskRecord && idEq(activeTaskRecord.id, taskId)) return activeTaskRecord;
    if (idEq(selectedTaskId, taskId)) {
      return {
        id: taskId,
        title: currentTaskEl ? String(currentTaskEl.textContent || '').trim() || ('Task #' + taskId) : ('Task #' + taskId),
        description: currentTaskDescriptionEl ? String(currentTaskDescriptionEl.textContent || '').trim() : '',
        bucket_name: '',
        is_billable: true,
        billing_status: 'unbilled',
        billing_mode: 'fixed_fee',
      };
    }
    return null;
  }

  function resetTaskSummary() {
    activeTaskRecord = null;
    if (currentTaskStatusEl) currentTaskStatusEl.textContent = 'Select a task';
    if (currentTaskEl) currentTaskEl.textContent = currentTaskStatusEl ? 'Task Details' : 'Select a task from the queue.';
    if (currentTaskMetaEl) currentTaskMetaEl.textContent = 'Choose a board card or calendar item to open its workspace.';
    if (currentTaskGuidanceEl) {
      currentTaskGuidanceEl.hidden = true;
      currentTaskGuidanceEl.textContent = '';
    }
    if (currentTaskDescriptionEl) currentTaskDescriptionEl.textContent = '';
    if (currentTaskActionsEl) currentTaskActionsEl.innerHTML = '';
    if (assignmentPanelEl) assignmentPanelEl.hidden = true;
    if (priorityPanelEl) priorityPanelEl.hidden = true;
    if (taskWorkspaceEl) taskWorkspaceEl.hidden = true;
    if (taskEmptyEl) taskEmptyEl.hidden = false;
  }

  async function updateTaskSummary(task) {
    activeTaskRecord = task ? { ...task } : null;
    if (currentTaskStatusEl) currentTaskStatusEl.textContent = humanizeToken(task.status);
    if (currentTaskEl) currentTaskEl.textContent = currentTaskStatusEl ? task.title : 'Selected task: #' + task.id + ' - ' + task.title;
    if (currentTaskMetaEl) currentTaskMetaEl.innerHTML = taskContextMarkup(task);
    if (currentTaskGuidanceEl) {
      const guidance = taskGuidance(task);
      currentTaskGuidanceEl.textContent = guidance;
      currentTaskGuidanceEl.hidden = !guidance;
    }
    if (currentTaskDescriptionEl) currentTaskDescriptionEl.textContent = task.description || 'No request brief provided yet.';
    if (currentTaskActionsEl) currentTaskActionsEl.innerHTML = renderStatusButtons(task);
    if (taskWorkspaceEl) taskWorkspaceEl.hidden = false;
    if (taskEmptyEl) taskEmptyEl.hidden = true;
    if (meetingPanel) {
      meetingPanel.hidden = false;
    }
    if (window.wpPqConfig.canAssign) {
      await loadWorkers(task);
      ensureAssigneePresent(task);
      syncAssignmentPanel(task);
    }
    syncPriorityPanel(task);
  }

  function isDesktopWorkspace() {
    return window.matchMedia('(min-width: 901px)').matches;
  }

  function drawerIsOpen() {
    return !!drawerEl && drawerEl.classList.contains('is-open');
  }

  function openDrawer() {
    if (!drawerEl) return;
    drawerEl.classList.add('is-open');
    drawerEl.setAttribute('aria-hidden', 'false');
    if (appShellEl) appShellEl.classList.add('is-detail-focus');
    if (drawerBackdrop) drawerBackdrop.hidden = isDesktopWorkspace();
    document.body.classList.toggle('wp-pq-drawer-open', !isDesktopWorkspace());
  }

  function closeDrawer() {
    if (!drawerEl) return;
    selectedTaskId = null;
    drawerEl.classList.remove('is-open');
    drawerEl.setAttribute('aria-hidden', 'true');
    if (appShellEl) appShellEl.classList.remove('is-detail-focus');
    if (drawerBackdrop) drawerBackdrop.hidden = true;
    document.body.classList.remove('wp-pq-drawer-open');
    resetTaskSummary();
    highlightSelected();
  }

  function highlightSelected() {
    document.querySelectorAll('.wp-pq-task, .wp-pq-task-card').forEach((el) => {
      el.classList.toggle('active', parseInt(el.dataset.id, 10) === selectedTaskId);
    });
  }

  async function loadTasks() {
    const data = await api(apiPathWithFilters('tasks'), { method: 'GET' });
    replaceTasks(data.tasks || []);
    if (selectedTaskId && !getTaskById(selectedTaskId) && !(activeTaskRecord && idEq(activeTaskRecord.id, selectedTaskId))) {
      closeDrawer();
    }
    filterOptions = data.filters || filterOptions;
    syncFilterControls();
    await refreshFromCache({ reloadActivePane: !boardEl || drawerIsOpen(), refreshCalendar: currentView === 'calendar' });
  }

  async function selectTask(taskId, shouldOpenDrawer, options) {
    const config = options || {};
    const sameTask = idEq(selectedTaskId, taskId);
    selectedTaskId = taskId;
    const task = getTaskById(taskId);
    if (!task) return;

    await updateTaskSummary(task);
    if (!(config.preservePanelState && sameTask)) {
      resetTaskPanelState(task.id);
    }
    highlightSelected();
    if (shouldOpenDrawer) openDrawer();
    seedMeetingForm(config.forceMeetingSeed === true);
    const jobs = [];
    if (config.loadParticipants !== false) jobs.push(loadParticipants({ force: !!config.forceParticipants }));
    if (config.loadWorkspace !== false) jobs.push(loadActiveWorkspacePane({ force: !!config.forceWorkspace }));
    if (jobs.length) {
      await Promise.all(jobs);
    }
  }

  function wireBoardFilters() {
    if (clientFilterEl) {
      clientFilterEl.addEventListener('change', async () => {
        taskFilter = { mode: 'all', value: 'all' };
        setFilterState({
          clientUserId: parseInt(clientFilterEl.value || '0', 10) || 0,
          billingBucketId: 0,
        });
        syncFilterControls();
        selectedTaskId = null;
        await loadTasks();
      });
    }

  }

  function wireCreateForm() {
    if (!createForm) return;

    if (createClientEl) {
      createClientEl.addEventListener('change', () => {
        createFormState.clientUserId = parseInt(createClientEl.value || '0', 10) || 0;
        createFormState.billingBucketId = 0;
        if (createNewBucketEl) {
          createNewBucketEl.value = '';
        }
        syncCreateFormContext();
      });
    }

    if (createBucketEl) {
      createBucketEl.addEventListener('change', () => {
        createFormState.billingBucketId = parseInt(createBucketEl.value || '0', 10) || 0;
        syncCreateFormContext();
      });
    }

    if (createNeedsMeetingEl && createMeetingFieldsEl) {
      createNeedsMeetingEl.addEventListener('change', () => {
        const show = createNeedsMeetingEl.checked;
        createMeetingFieldsEl.hidden = !show;
        if (show && createMeetingStartEl && !createMeetingStartEl.value) {
          const start = roundUpToQuarterHour(new Date());
          createMeetingStartEl.value = toLocalDatetimeValue(start);
          if (createMeetingEndEl) {
            createMeetingEndEl.value = toLocalDatetimeValue(new Date(start.getTime() + 60 * 60 * 1000));
          }
        }
      });
      if (createMeetingStartEl) {
        createMeetingStartEl.addEventListener('change', () => {
          if (!createMeetingEndEl) return;
          const start = new Date(createMeetingStartEl.value);
          if (!Number.isNaN(start.getTime())) {
            createMeetingEndEl.value = toLocalDatetimeValue(new Date(start.getTime() + 60 * 60 * 1000));
          }
        });
      }
    }

    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(createForm);
      const createClientId = currentCreateClientId();
      if (window.wpPqConfig.canViewAll && createClientId <= 0) {
        alert('Choose a client before creating a task.');
        return;
      }
      const selectedBucketId = parseInt(formData.get('billing_bucket_id') || '0', 10) || 0;
      const newBucketName = (formData.get('new_bucket_name') || '').toString().trim();
      const visibleCreateBuckets = (Array.isArray(filterOptions.buckets) ? filterOptions.buckets : []).filter((bucket) => (
        createClientId > 0 ? parseInt(bucket.client_id, 10) === createClientId : true
      ));
      if (window.wpPqConfig.canApprove && createClientId > 0 && (selectedBucketId === -1 || visibleCreateBuckets.length === 0) && !newBucketName) {
        alert('Name the first job for this client before submitting the task.');
        return;
      }
      const needsMeeting = formData.get('needs_meeting') === 'on';
      const meetingStart = (formData.get('meeting_starts_at') || '').toString();
      const meetingEnd = (formData.get('meeting_ends_at') || '').toString();

      const body = {
        title: formData.get('title') || '',
        description: formData.get('description') || '',
        priority: formData.get('priority') || 'normal',
        due_at: formData.get('due_at') || null,
        requested_deadline: formData.get('requested_deadline') || null,
        needs_meeting: needsMeeting,
        is_billable: !window.wpPqConfig.canApprove || formData.get('is_billable') === 'on',
        owner_ids: parseOwnerIds(formData.get('owner_ids')),
        client_id: createClientId || 0,
        billing_bucket_id: selectedBucketId > 0 ? selectedBucketId : 0,
        new_bucket_name: newBucketName,
      };

      try {
        const result = await api('tasks', { method: 'POST', body: JSON.stringify(body) });
        const newTaskId = result.task ? result.task.id : (result.task_id || null);

        // Schedule the meeting inline — no separate modal.
        if (needsMeeting && meetingStart && newTaskId) {
          let endsAt = meetingEnd;
          if (!endsAt) {
            const s = new Date(meetingStart);
            if (!Number.isNaN(s.getTime())) {
              endsAt = toLocalDatetimeValue(new Date(s.getTime() + 60 * 60 * 1000));
            }
          }
          try {
            await api('tasks/' + newTaskId + '/meetings', {
              method: 'POST',
              body: JSON.stringify({ starts_at: meetingStart, ends_at: endsAt || meetingStart }),
            });
          } catch (meetErr) {
            alert('Task created but meeting scheduling failed: ' + meetErr.message, 'warning');
          }
        }

        createForm.reset();
        if (createMeetingFieldsEl) createMeetingFieldsEl.hidden = true;
        createFormState.clientUserId = 0;
        createFormState.billingBucketId = 0;
        syncCreateFormContext();
        if (createPanel) createPanel.hidden = true;

        if (result.task) {
          upsertTask(result.task);
          selectedTaskId = result.task.id;
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: true });
        } else {
          selectedTaskId = result.task_id || selectedTaskId;
          await loadTasks();
        }
        if (selectedTaskId && boardEl) {
          await selectTask(selectedTaskId, true);
        }
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function wireSort() {
    if (!taskList || boardEl || typeof Sortable === 'undefined') return;
    Sortable.create(taskList, {
      animation: 150,
      draggable: '.wp-pq-task',
      onEnd: async () => {
        const orderedIds = Array.from(taskList.querySelectorAll('.wp-pq-task')).map((el) => parseInt(el.dataset.id, 10)).filter((id) => id > 0);
        const items = orderedIds.map((id) => ({ id: id }));
        try {
          await api('tasks/reorder', { method: 'POST', body: JSON.stringify({ items: items }) });
          reorderTasksCache(orderedIds);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: false });
        } catch (err) {
          alert(err.message);
        }
      },
    });
  }

  function renderEmptyStream(target, message) {
    if (!target) return;
    target.innerHTML = '';
    const li = document.createElement('li');
    li.className = 'wp-pq-stream-empty';
    li.textContent = message;
    target.appendChild(li);
  }

  async function loadParticipants(options) {
    if (!selectedTaskId) {
      participantCache = [];
      renderMentionChips();
      return;
    }

    ensureTaskPanelState(selectedTaskId);
    if (taskPanelState.participants && !(options && options.force)) {
      renderMentionChips();
      return;
    }

    const data = await api('tasks/' + selectedTaskId + '/participants', { method: 'GET' });
    participantCache = data.participants || [];
    taskPanelState.participants = true;
    renderMentionChips();
  }

  function renderMentionChips() {
    if (!mentionList) return;
    mentionList.innerHTML = '';

    if (!participantCache.length) {
      const empty = document.createElement('span');
      empty.className = 'wp-pq-mention-hint';
      empty.textContent = 'No mention shortcuts yet.';
      mentionList.appendChild(empty);
      return;
    }

    participantCache.forEach((person) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'wp-pq-mention-chip';
      button.textContent = '@' + person.handle;
      button.title = person.name;
      button.addEventListener('click', () => insertMention(person.handle));
      mentionList.appendChild(button);
    });
  }

  function insertMention(handle) {
    if (!messageForm) return;
    const textarea = messageForm.querySelector('textarea[name="body"]');
    if (!textarea) return;

    const value = textarea.value.trimEnd();
    textarea.value = (value ? value + ' ' : '') + '@' + handle + ' ';
    textarea.focus();
  }

  async function loadMessages(options) {
    if (!messageList) return;
    if (!selectedTaskId) {
      renderEmptyStream(messageList, 'Open a task to see its messages.');
      return;
    }

    ensureTaskPanelState(selectedTaskId);
    if (taskPanelState.messages && !(options && options.force)) {
      return;
    }
    const data = await api('tasks/' + selectedTaskId + '/messages', { method: 'GET' });
    messageList.innerHTML = '';
    taskPanelState.messages = true;

    if (!(data.messages || []).length) {
      renderEmptyStream(messageList, 'No messages yet for this task.');
      return;
    }

    (data.messages || []).forEach((msg) => {
      const li = document.createElement('li');
      li.className = msg.author_id === window.wpPqConfig.currentUserId ? 'mine' : 'theirs';
      li.innerHTML = messageItemHtml(msg);
      messageList.appendChild(li);
    });
  }

  async function loadNotes(options) {
    if (!noteList) return;
    if (!selectedTaskId) {
      renderEmptyStream(noteList, 'Open a task to see its sticky notes.');
      return;
    }

    ensureTaskPanelState(selectedTaskId);
    if (taskPanelState.notes && !(options && options.force)) {
      return;
    }
    const data = await api('tasks/' + selectedTaskId + '/notes', { method: 'GET' });
    noteList.innerHTML = '';
    taskPanelState.notes = true;

    if (!(data.notes || []).length) {
      renderEmptyStream(noteList, 'No sticky notes yet for this task.');
      return;
    }

    (data.notes || []).forEach((note) => {
      const li = document.createElement('li');
      li.className = note.author_id === window.wpPqConfig.currentUserId ? 'mine' : 'theirs';
      li.innerHTML = noteItemHtml(note);
      noteList.appendChild(li);
    });
  }

  function wireMessages() {
    if (!messageForm) return;
    messageForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectedTaskId) return alert('Select a task first.');

      const formData = new FormData(messageForm);
      const body = (formData.get('body') || '').toString().trim();
      if (!body) return;

      try {
        const result = await api('tasks/' + selectedTaskId + '/messages', { method: 'POST', body: JSON.stringify({ body: body }) });
        messageForm.reset();
        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: false, renderCollections: false });
        }
        if (result.message && messageList) {
          const emptyState = messageList.querySelector('.wp-pq-stream-empty');
          if (emptyState) {
            messageList.innerHTML = '';
          }
          const li = document.createElement('li');
          li.className = result.message.author_id === window.wpPqConfig.currentUserId ? 'mine' : 'theirs';
          li.innerHTML = messageItemHtml(result.message);
          messageList.appendChild(li);
          taskPanelState.messages = true;
        } else {
          await loadMessages({ force: true });
        }
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function wireNotes() {
    if (!noteForm) return;
    noteForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectedTaskId) return alert('Select a task first.');

      const formData = new FormData(noteForm);
      const body = (formData.get('body') || '').toString().trim();
      if (!body) return;

      try {
        const result = await api('tasks/' + selectedTaskId + '/notes', { method: 'POST', body: JSON.stringify({ body: body }) });
        noteForm.reset();
        if (result.task) {
          upsertTask(result.task);
        }
        await refreshFromCache({ reloadActivePane: false, refreshCalendar: false });
        if (result.note && noteList) {
          const emptyState = noteList.querySelector('.wp-pq-stream-empty');
          if (emptyState) {
            noteList.innerHTML = '';
          }
          const li = document.createElement('li');
          li.className = result.note.author_id === window.wpPqConfig.currentUserId ? 'mine' : 'theirs';
          li.innerHTML = noteItemHtml(result.note);
          noteList.prepend(li);
          taskPanelState.notes = true;
        } else {
          await loadNotes({ force: true });
        }
      } catch (err) {
        alert(err.message);
      }
    });
  }

  // uploadToMedia removed — file exchange replaced by link field.

  async function loadFiles(options) {
    const linkDisplay = document.getElementById('wp-pq-files-link-display');
    const linkForm = document.getElementById('wp-pq-files-link-form');
    if (!linkDisplay || !linkForm) return;
    if (!selectedTaskId) return;

    ensureTaskPanelState(selectedTaskId);
    if (taskPanelState.files && !(options && options.force)) return;
    taskPanelState.files = true;

    const task = getTaskById(selectedTaskId);
    const link = (task && task.files_link) || '';
    const linkInput = linkForm.querySelector('input[name="files_link"]');

    if (link) {
      linkDisplay.innerHTML = '<a href="' + encodeURI(link) + '" target="_blank" rel="noopener">' + escapeHtml(link) + '</a>';
    } else {
      linkDisplay.innerHTML = '<span class="wp-pq-muted">No files link set.</span>';
    }

    if (linkInput) linkInput.value = link;
  }

  function wireFiles() {
    const linkForm = document.getElementById('wp-pq-files-link-form');
    if (!linkForm) return;

    linkForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectedTaskId) return;
      const linkInput = linkForm.querySelector('input[name="files_link"]');
      const filesLink = (linkInput ? linkInput.value : '').trim();

      try {
        const result = await api('tasks/' + selectedTaskId + '/files-link', {
          method: 'PUT',
          body: JSON.stringify({ files_link: filesLink }),
        });
        if (result.task) upsertTask(result.task);
        toast('Files link saved.');
        await loadFiles({ force: true });
      } catch (err) {
        toast(err.message || 'Failed to save link.', true);
      }
    });
  }

  function wireMeetings() {
    if (!meetingForm) return;

    seedMeetingForm(true);

    if (meetingStartInput) {
      meetingStartInput.addEventListener('change', () => autoFillMeetingEnd());
    }
    if (meetingEndInput) {
      meetingEndInput.addEventListener('input', () => {
        meetingEndInput.dataset.autoManaged = '0';
      });
    }

    meetingForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectedTaskId) return alert('Select a task first.');

      const formData = new FormData(meetingForm);
      const startsAt = (formData.get('starts_at') || '').toString();
      let endsAt = (formData.get('ends_at') || '').toString();
      if (!startsAt) return alert('Choose a meeting start time.');

      if (!endsAt) {
        const start = new Date(startsAt);
        if (!Number.isNaN(start.getTime())) {
          const end = new Date(start.getTime() + 30 * 60 * 1000);
          endsAt = end.toISOString().slice(0, 16);
        }
      }

      if (!endsAt) return alert('Choose a meeting end time.');

      try {
        const result = await api('tasks/' + selectedTaskId + '/meetings', {
          method: 'POST',
          body: JSON.stringify({
            starts_at: startsAt,
            ends_at: endsAt,
          }),
        });
        meetingForm.reset();
        seedMeetingForm(true);
        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: false, renderCollections: false });
        }
        if (result.meeting && meetingList) {
          const task = getTaskById(selectedTaskId);
          const invitee = task && task.submitter_email ? task.submitter_email : 'the task requester';
          const emptyState = meetingList.querySelector('.wp-pq-stream-empty');
          if (emptyState) {
            meetingList.innerHTML = '';
          }
          const li = document.createElement('li');
          li.innerHTML = meetingItemHtml(result.meeting, invitee);
          meetingList.prepend(li);
          meetingSummaryEl.textContent = 'Google Meet will invite ' + invitee + '.';
          taskPanelState.meetings = true;
        } else {
          await loadMeetings({ force: true });
        }
        await loadCalendarEvents();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function openMeetingScheduler(taskId) {
    if (!floatingMeetingEl || !floatingMeetingForm) return;
    floatingMeetingTaskId = taskId;
    const task = getTaskById(taskId);
    const invitee = task && task.submitter_email ? task.submitter_email : 'the task requester';
    if (floatingMeetingSummary) {
      floatingMeetingSummary.textContent = 'Google Meet will invite ' + invitee + '.';
    }
    if (floatingMeetingStartInput && floatingMeetingEndInput) {
      const start = roundUpToQuarterHour(new Date());
      floatingMeetingStartInput.value = toLocalDatetimeValue(start);
      const end = new Date(start.getTime() + 60 * 60 * 1000);
      floatingMeetingEndInput.value = toLocalDatetimeValue(end);
    }
    floatingMeetingEl.hidden = false;
  }

  function closeMeetingScheduler() {
    if (!floatingMeetingEl) return;
    floatingMeetingEl.hidden = true;
    floatingMeetingTaskId = null;
    if (floatingMeetingForm) floatingMeetingForm.reset();
  }

  function wireFloatingMeeting() {
    if (!floatingMeetingEl || !floatingMeetingForm) return;

    if (floatingMeetingCloseBtn) floatingMeetingCloseBtn.addEventListener('click', closeMeetingScheduler);
    if (floatingMeetingSkipBtn) floatingMeetingSkipBtn.addEventListener('click', closeMeetingScheduler);

    if (floatingMeetingStartInput) {
      floatingMeetingStartInput.addEventListener('change', () => {
        if (!floatingMeetingEndInput) return;
        const start = new Date(floatingMeetingStartInput.value);
        if (Number.isNaN(start.getTime())) return;
        const end = new Date(start.getTime() + 60 * 60 * 1000);
        floatingMeetingEndInput.value = toLocalDatetimeValue(end);
      });
    }

    floatingMeetingForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!floatingMeetingTaskId) return alert('No task selected.');

      const formData = new FormData(floatingMeetingForm);
      const startsAt = (formData.get('starts_at') || '').toString();
      let endsAt = (formData.get('ends_at') || '').toString();
      if (!startsAt) return alert('Choose a meeting start time.');

      if (!endsAt) {
        const start = new Date(startsAt);
        if (!Number.isNaN(start.getTime())) {
          const end = new Date(start.getTime() + 60 * 60 * 1000);
          endsAt = end.toISOString().slice(0, 16);
        }
      }
      if (!endsAt) return alert('Choose a meeting end time.');

      const submitBtn = floatingMeetingForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const result = await api('tasks/' + floatingMeetingTaskId + '/meetings', {
          method: 'POST',
          body: JSON.stringify({ starts_at: startsAt, ends_at: endsAt }),
        });
        closeMeetingScheduler();
        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: true, refreshCalendar: true });
        }
        await loadCalendarEvents();
      } catch (err) {
        alert(err.message);
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  function wireAssignment() {
    if (!assignmentSaveBtn || !assignmentSelectEl) return;

    assignmentSaveBtn.addEventListener('click', async () => {
      if (!selectedTaskId) return;

      const actionOwnerId = parseInt(assignmentSelectEl.value || '0', 10) || 0;
      assignmentSaveBtn.disabled = true;

      try {
        const result = await api('tasks/' + selectedTaskId + '/assignment', {
          method: 'POST',
          body: JSON.stringify({ action_owner_id: actionOwnerId }),
        });

        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: false, forceSelect: true });
          await selectTask(selectedTaskId, drawerIsOpen(), {
            preservePanelState: true,
            loadParticipants: false,
            loadWorkspace: false,
          });
        } else {
          await loadTasks();
        }
      } catch (err) {
        alert(err.message);
      } finally {
        assignmentSaveBtn.disabled = false;
      }
    });
  }

  function wirePriority() {
    if (!prioritySaveBtn || !prioritySelectEl) return;

    prioritySaveBtn.addEventListener('click', async () => {
      if (!selectedTaskId) return;

      const priority = String(prioritySelectEl.value || 'normal');
      prioritySaveBtn.disabled = true;

      try {
        const result = await api('tasks/' + selectedTaskId + '/priority', {
          method: 'POST',
          body: JSON.stringify({ priority: priority }),
        });

        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: false, forceSelect: true });
          await selectTask(selectedTaskId, drawerIsOpen(), {
            preservePanelState: true,
            loadParticipants: false,
            loadWorkspace: false,
          });
          alert('Priority updated.', 'success');
        } else {
          await loadTasks();
        }
      } catch (err) {
        alert(err.message);
      } finally {
        prioritySaveBtn.disabled = false;
      }
    });
  }

  function setActiveView(showBoard) {
    currentView = showBoard ? 'board' : 'calendar';
    if (boardPanel) boardPanel.hidden = !showBoard;
    if (calendarPanel) calendarPanel.hidden = showBoard;

    if (boardViewBtn) boardViewBtn.classList.toggle('button-primary', showBoard);
    if (boardViewBtn) boardViewBtn.classList.toggle('is-active', showBoard);
    if (calendarViewBtn) calendarViewBtn.classList.toggle('button-primary', !showBoard);
    if (calendarViewBtn) calendarViewBtn.classList.toggle('is-active', !showBoard);
  }

  function initViewToggle() {
    if (!boardViewBtn || !calendarViewBtn || !boardPanel || !calendarPanel) return;

    boardViewBtn.addEventListener('click', () => setActiveView(true));

    calendarViewBtn.addEventListener('click', async () => {
      setActiveView(false);
      if (calendar) calendar.render();
      await loadCalendarEvents();
    });
  }

  function wireTogglePanel(button, panel, closeButton) {
    if (!button || !panel) return;

    button.addEventListener('click', () => {
      panel.hidden = !panel.hidden;
    });

    if (closeButton) {
      closeButton.addEventListener('click', () => {
        panel.hidden = true;
      });
    }
  }

  async function activateWorkspaceTab(tab) {
    if (!panelMessages || !panelNotes || !panelFiles) return;

    const showMessages = tab === 'messages';
    const showMeetings = tab === 'meetings';
    const showNotes = tab === 'notes';
    const showFiles = tab === 'files';

    panelMessages.hidden = !showMessages;
    if (panelMeetings) panelMeetings.hidden = !showMeetings;
    panelNotes.hidden = !showNotes;
    panelFiles.hidden = !showFiles;

    if (tabMessagesBtn) tabMessagesBtn.classList.toggle('button-primary', showMessages);
    if (tabMessagesBtn) tabMessagesBtn.classList.toggle('is-active', showMessages);
    if (tabMeetingsBtn) tabMeetingsBtn.classList.toggle('button-primary', showMeetings);
    if (tabMeetingsBtn) tabMeetingsBtn.classList.toggle('is-active', showMeetings);
    if (tabNotesBtn) tabNotesBtn.classList.toggle('button-primary', showNotes);
    if (tabNotesBtn) tabNotesBtn.classList.toggle('is-active', showNotes);
    if (tabFilesBtn) tabFilesBtn.classList.toggle('button-primary', showFiles);
    if (tabFilesBtn) tabFilesBtn.classList.toggle('is-active', showFiles);

    if (!selectedTaskId) return;
    if (showMessages && !taskPanelState.messages) await loadMessages();
    if (showMeetings && !taskPanelState.meetings) await loadMeetings();
    if (showNotes && !taskPanelState.notes) await loadNotes();
    if (showFiles && !taskPanelState.files) await loadFiles();
  }

  function initWorkspaceTabs() {
    if (!tabMessagesBtn || !tabNotesBtn || !tabFilesBtn || !panelMessages || !panelNotes || !panelFiles) return;

    tabMessagesBtn.addEventListener('click', () => activateWorkspaceTab('messages').catch(console.error));
    if (tabMeetingsBtn && panelMeetings) {
      tabMeetingsBtn.addEventListener('click', () => activateWorkspaceTab('meetings').catch(console.error));
    }
    tabNotesBtn.addEventListener('click', () => activateWorkspaceTab('notes').catch(console.error));
    tabFilesBtn.addEventListener('click', () => activateWorkspaceTab('files').catch(console.error));
    activateWorkspaceTab('messages').catch(console.error);
  }

  function initCalendar() {
    if (!calendarEl || typeof window.FullCalendar === 'undefined') return;

    calendar = new window.FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 'auto',
      contentHeight: 540,
      dayMaxEventRows: 2,
      editable: true,
      eventDurationEditable: false,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
      },
      eventDidMount: (info) => {
        info.el.title = buildCalendarTooltip(info.event);
      },
      eventClick: async (info) => {
        const taskId = info.event.extendedProps && info.event.extendedProps.taskId;
        if (taskId) {
          info.jsEvent.preventDefault();
          await selectTask(taskId, !!drawerEl);
        }

        const link = info.event.url;
        if (link) {
          info.jsEvent.preventDefault();
          window.open(link, '_blank', 'noopener');
        }
      },
      eventDrop: async (info) => {
        const props = info.event.extendedProps || {};
        if (props.source !== 'task' || !props.taskId) {
          info.revert();
          return;
        }

        try {
          const result = await api('tasks/' + props.taskId + '/schedule', {
            method: 'POST',
            body: JSON.stringify({ when: info.event.startStr }),
          });
          if (result.task) {
            upsertTask(result.task);
            await refreshFromCache({ reloadActivePane: false, refreshCalendar: false });
          } else {
            await loadTasks();
          }
          if (selectedTaskId === props.taskId) {
            await selectTask(props.taskId, !!drawerEl && drawerIsOpen(), {
              preservePanelState: true,
              loadParticipants: false,
              loadWorkspace: false,
            });
          }
        } catch (err) {
          info.revert();
          alert(err.message);
        }
      },
    });
  }

  function buildCalendarTooltip(event) {
    const props = event.extendedProps || {};
    const lines = [event.title];

    if (props.source === 'task') {
      if (props.priority) lines.push('Priority: ' + humanizeToken(props.priority));
      if (props.status) lines.push('Status: ' + humanizeToken(props.status));

      const deadline = props.requestedDeadline || props.dueAt || event.startStr;
      if (deadline) lines.push('When: ' + formatDateTime(deadline));
      if (props.needsMeeting) lines.push('Needs meeting: Yes');
      if (props.description) lines.push('Brief: ' + truncateText(props.description, 160));
    }

    if (props.source === 'meeting') {
      lines.push('Meeting for task #' + (props.taskId || ''));
      if (event.startStr) lines.push('Starts: ' + formatDateTime(event.startStr));
      if (event.endStr) lines.push('Ends: ' + formatDateTime(event.endStr));
      if (props.meetingUrl) lines.push('Open meeting link');
    }

    if (!props.source && event.startStr) {
      lines.push('Starts: ' + formatDateTime(event.startStr));
    }

    return lines.filter(Boolean).join('\n');
  }

  async function loadCalendarEvents() {
    if (!calendar) return;
    try {
      const data = await api(apiPathWithFilters('calendar/events'), { method: 'GET' });
      calendar.removeAllEvents();
      (data.events || []).forEach((event) => calendar.addEvent(event));
    } catch (err) {
      console.error(err);
    }
  }

  // initUppy removed — file exchange replaced by link field.

  function wireUnifiedFilters() {
    if (!filterListEl) return;
    filterListEl.addEventListener('change', async (e) => {
      const label = e.target.closest('[data-filter-mode][data-filter-value]');
      if (!label) return;
      const clickedMode = label.dataset.filterMode || 'all';
      const clickedValue = label.dataset.filterValue || 'all';
      if (taskFilter.mode === clickedMode && taskFilter.value === clickedValue) {
        taskFilter = { mode: 'all', value: 'all' };
      } else {
        taskFilter = { mode: clickedMode, value: clickedValue };
      }
      await refreshFromCache({ reloadActivePane: false, refreshCalendar: currentView === 'calendar' });
    });
  }

  function wireJobNav() {
    if (!jobNavEl) return;
    jobNavEl.addEventListener('click', async (e) => {
      const button = e.target.closest('[data-job-id]');
      if (!button) return;
      e.preventDefault();
      taskFilter = { mode: 'all', value: 'all' };
      setFilterState({
        clientUserId: filterState.clientUserId,
        billingBucketId: parseInt(button.dataset.jobId || '0', 10) || 0,
      });
      syncFilterControls();
      await loadTasks();
    });
  }

  function wireStatusActions() {
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.wp-pq-status-btn');
      const deleteBtn = e.target.closest('.wp-pq-delete-btn');
      if (!btn && !deleteBtn) return;

      e.preventDefault();
      e.stopPropagation();

      if (deleteBtn) {
        const taskId = parseInt(deleteBtn.dataset.taskId, 10);
        if (!taskId) return;
        selectedTaskId = taskId;
        window.wpPqModals.openDeleteModal(taskId);
        return;
      }

      const id = parseInt(btn.dataset.taskId, 10);
      const status = btn.dataset.status;
      if (!id || !status) return;

      if (normalizeStatus(status) === 'done') {
        selectedTaskId = id;
        window.wpPqModals.setPendingMove(null);
        window.wpPqModals.setPendingStatusAction(null);
        window.wpPqModals.openCompletionModal(id);
        return;
      }

      const task = getKnownTask(id);
      if (normalizeStatus(status) === 'needs_clarification' && task) {
        selectedTaskId = id;
        window.wpPqModals.setPendingMove(null);
        window.wpPqModals.setPendingStatusAction({
          taskId: id,
          status: status,
        });
        window.wpPqModals.openMoveModal();
        return;
      }

      if (normalizeStatus(status) === 'delivered' && task && normalizeStatus(task.status) === 'in_progress') {
        if (!window.confirm('You are about to mark this task delivered. Proceed without third-party review?')) return;
      }

      try {
        selectedTaskId = id;
        const result = await api('tasks/' + id + '/status', { method: 'POST', body: JSON.stringify({ status: status }) });
        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: true, refreshCalendar: currentView === 'calendar' });
        } else {
          await loadTasks();
        }
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function wireArchiveAll() {
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.wp-pq-archive-all-btn');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      const deliveredCount = tasksCache.filter(function (t) { return normalizeStatus(t.status) === 'delivered'; }).length;
      if (!deliveredCount) return;
      if (!window.confirm('Archive all ' + deliveredCount + ' delivered task' + (deliveredCount === 1 ? '' : 's') + '? This cannot be undone.')) return;
      try {
        const result = await api('tasks/archive-delivered', { method: 'POST', body: JSON.stringify({}) });
        alert(result.archived_count + ' task' + (result.archived_count === 1 ? '' : 's') + ' archived.', 'success');
        await loadTasks();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function wireDrawerControls() {
    if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeDrawer);
    if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && window.wpPqModals) {
        const deleteModalEl = document.getElementById('wp-pq-delete-modal');
        const completionModalEl = document.getElementById('wp-pq-completion-modal');
        const revisionModalEl = document.getElementById('wp-pq-revision-modal');
        const moveModalEl = document.getElementById('wp-pq-move-modal');
        if (deleteModalEl && !deleteModalEl.hidden) {
          window.wpPqModals.closeDeleteModal();
          return;
        }
        if (completionModalEl && !completionModalEl.hidden) {
          window.wpPqModals.closeCompletionModal();
          return;
        }
        if (revisionModalEl && !revisionModalEl.hidden) {
          window.wpPqModals.closeRevisionModal(true).catch(console.error);
          return;
        }
        if (moveModalEl && !moveModalEl.hidden) {
          window.wpPqModals.closeMoveModal(true).catch(console.error);
          return;
        }
      }
      if (e.key === 'Escape' && drawerIsOpen()) {
        closeDrawer();
      }
    });
  }

  // Bridge for modals — must be set before wire calls so admin-queue-modals.js can read it
  window.wpPqPortalUI = Object.assign({}, window.wpPqPortalUI || {}, {
    // Bridge for modals
    getActiveTask: () => activeTaskRecord ? { ...activeTaskRecord } : null,
    getTaskById: (id) => getTaskById(id),
    getKnownTask: (id) => getKnownTask(id),
    getSelectedTaskId: () => selectedTaskId,
    setSelectedTaskId: (id) => { selectedTaskId = id; },
    api: api,
    alert: alert,
    upsertTask: upsertTask,
    loadTasks: loadTasks,
    selectTask: selectTask,
    refreshFromCache: refreshFromCache,
    syncOrderFromBoardDom: syncOrderFromBoardDom,
    activateWorkspaceTab: activateWorkspaceTab,
    currentView: () => currentView,
    normalizeStatus: normalizeStatus,
    humanizeToken: humanizeToken,
    escapeHtml: escapeHtml,
    formatDateTime: formatDateTime,
    removeTaskById: removeTaskById,
    closeDrawer: closeDrawer,
    openDrawer: openDrawer,
    focusMeetingStart: () => { if (meetingStartInput) meetingStartInput.focus(); },
    openMeetingScheduler: openMeetingScheduler,
  });

  wireCreateForm();
  wireBoardFilters();
  wireSort();
  wireMessages();
  wireNotes();
  wireFiles();
  wireMeetings();
  wireFloatingMeeting();
  wireAssignment();
  wirePriority();
  wireUnifiedFilters();
  wireJobNav();
  wireStatusActions();
  wireArchiveAll();
  wireDrawerControls();
  wireTogglePanel(openCreateBtn, createPanel, closeCreateBtn);
  initViewToggle();
  initWorkspaceTabs();
  initCalendar();
  setActiveView(true);
  resetTaskSummary();

  /* ── Dark-mode toggle ──────────────────────────────── */
  (function initDarkMode() {
    const wrap = document.querySelector('.wp-pq-wrap');
    const toggle = document.getElementById('wp-pq-dark-toggle');
    if (!wrap || !toggle) return;
    const key = 'wp_pq_theme';
    const saved = localStorage.getItem(key);
    const toggleLabel = toggle.querySelector('span > span:last-child');
    function applyTheme(isDark) {
      if (isDark) {
        wrap.setAttribute('data-theme', 'dark');
        toggle.classList.add('is-active');
        if (toggleLabel) toggleLabel.textContent = 'Light Mode';
      } else {
        wrap.removeAttribute('data-theme');
        toggle.classList.remove('is-active');
        if (toggleLabel) toggleLabel.textContent = 'Dark Mode';
      }
    }
    applyTheme(saved === 'dark');
    toggle.addEventListener('click', function () {
      const isDark = wrap.getAttribute('data-theme') !== 'dark';
      applyTheme(isDark);
      localStorage.setItem(key, isDark ? 'dark' : 'light');
    });
  })();

  loadTasks().catch(console.error);
})();
