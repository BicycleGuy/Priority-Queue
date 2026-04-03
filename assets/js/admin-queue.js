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
  const lanePanelEl = document.getElementById('wp-pq-lane-panel');
  const laneSelectEl = document.getElementById('wp-pq-lane-select');
  const laneSaveBtn = document.getElementById('wp-pq-save-lane');
  const meetingPanel = document.getElementById('wp-pq-meeting-panel');
  const meetingSummaryEl = document.getElementById('wp-pq-meeting-summary');
  const meetingList = document.getElementById('wp-pq-meeting-list');
  const meetingForm = document.getElementById('wp-pq-meeting-form');
  const meetingStartInput = meetingForm ? meetingForm.querySelector('input[name="starts_at"]') : null;
  const meetingEndInput = meetingForm ? meetingForm.querySelector('input[name="ends_at"]') : null;
  const messageList = document.getElementById('wp-pq-message-list');
  const messageForm = document.getElementById('wp-pq-message-form');
  const composeMode = document.getElementById('wp-pq-compose-mode');
  const composeSubmit = document.getElementById('wp-pq-compose-submit');
  const noteList = null; // unified into messageList
  const noteForm = null; // unified into messageForm
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
  const tabNotesBtn = null; // unified into conversation tab
  const tabFilesBtn = document.getElementById('wp-pq-tab-files');
  const panelMessages = document.getElementById('wp-pq-panel-messages');
  const panelMeetings = document.getElementById('wp-pq-panel-meetings');
  const panelNotes = null; // unified into panelMessages
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
  let lanesCache = [];
  let laneMode = 'off'; // 'off' | 'manual' | 'auto_job'
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

  function conversationItemHtml(item) {
    var badge = item.type === 'note' ? '<span class="wp-pq-note-badge">\uD83D\uDCCC Note</span> ' : '';
    return '<div class="msg-author">' + badge + escapeHtml(item.author_name || 'Collaborator') + ' \u00B7 ' + escapeHtml(formatDateTime(item.created_at)) + '</div>' +
      '<div>' + escapeHtml(item.body || '') + '</div>';
  }

  // Legacy aliases for backward compatibility.
  function messageItemHtml(msg) { return conversationItemHtml(Object.assign({ type: 'message' }, msg)); }
  function noteItemHtml(note) { return conversationItemHtml(Object.assign({ type: 'note' }, note)); }

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

  // Deterministic color palette for client avatars — offset from job hues.
  function clientAvatarColor(clientId) {
    var hues = [10, 160, 270, 50, 210, 340, 130, 300, 30, 190, 80, 230];
    var idx = (parseInt(clientId, 10) || 0) % hues.length;
    return 'hsl(' + hues[idx] + ', 55%, 45%)';
  }
  function clientAvatarHtml(name, clientId) {
    var letter = (name || 'C').charAt(0).toUpperCase();
    var bg = clientAvatarColor(clientId);
    return '<span class="wp-pq-job-avatar" style="background:' + bg + '">' + escapeHtml(letter) + '</span>';
  }

  function updateBinderUi(scopedTasks, visibleTasks) {
    var canViewAll = !!window.wpPqConfig.canViewAll;
    var allClients = Array.isArray(filterOptions.clients) ? filterOptions.clients : [];
    var allBuckets = visibleBuckets();

    /* ── Client axis ─────────────────────────────────── */
    if (binderClientContext && canViewAll) {
      var clientBtns = [
        '<button class="button' + (filterState.clientUserId === 0 ? ' is-active' : '') + '" type="button" data-client-id="0">' +
          '<span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><span class="wp-pq-job-avatar wp-pq-job-avatar--all">✱</span></span><span>All clients</span></span>' +
        '</button>'
      ];
      allClients.forEach(function (c) {
        var cid = parseInt(c.id, 10) || 0;
        var name = c.name || c.label || 'Client';
        clientBtns.push(
          '<button class="button' + (cid === filterState.clientUserId ? ' is-active' : '') + '" type="button" data-client-id="' + escapeHtml(cid) + '">' +
            '<span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">' + clientAvatarHtml(name, cid) + '</span><span>' + escapeHtml(name) + '</span></span>' +
          '</button>'
        );
      });
      binderClientContext.innerHTML = '<div class="wp-pq-filter-nav">' + clientBtns.join('') + '</div>';

      if (!binderClientContext._pqBound) {
        binderClientContext._pqBound = true;
        binderClientContext.addEventListener('click', async function (e) {
          var btn = e.target.closest('[data-client-id]');
          if (!btn) return;
          e.preventDefault();
          taskFilter = { mode: 'all', value: 'all' };
          setFilterState({ clientUserId: parseInt(btn.dataset.clientId || '0', 10) || 0, billingBucketId: 0 });
          syncFilterControls();
          selectedTaskId = null;
          await loadTasks();
        });
      }
    } else if (binderClientContext) {
      var selectedClient = (filterOptions.clients || []).find(function (c) { return parseInt(c.id, 10) === filterState.clientUserId; });
      var clientLabel = selectedClient
        ? (selectedClient.name || selectedClient.label || 'Selected client')
        : 'Your workspace';
      binderClientContext.innerHTML = '<div class="wp-pq-filter-nav"><button class="button is-active" type="button" disabled><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">' + clientAvatarHtml(clientLabel, filterState.clientUserId) + '</span><span>' + escapeHtml(clientLabel) + '</span></span></button></div>';
    }

    /* ── Job axis ────────────────────────────────────── */
    if (binderJobContext) {
      var jobBtns = [
        '<button class="button' + (filterState.billingBucketId === 0 ? ' is-active' : '') + '" type="button" data-job-id="0">' +
          '<span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><span class="wp-pq-job-avatar wp-pq-job-avatar--all">✱</span></span><span>All jobs</span></span>' +
        '</button>'
      ];
      allBuckets.forEach(function (b) {
        var bid = parseInt(b.id, 10) || 0;
        var name = b.label || b.bucket_name || 'Job';
        jobBtns.push(
          '<button class="button' + (bid === filterState.billingBucketId ? ' is-active' : '') + '" type="button" data-job-id="' + escapeHtml(bid) + '">' +
            '<span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true">' + jobAvatarHtml(name, b.client_id) + '</span><span>' + escapeHtml(name) + '</span></span>' +
          '</button>'
        );
      });
      binderJobContext.innerHTML = '<div class="wp-pq-filter-nav">' + jobBtns.join('') + '</div>';

      if (!binderJobContext._pqBound) {
        binderJobContext._pqBound = true;
        binderJobContext.addEventListener('click', async function (e) {
          var btn = e.target.closest('[data-job-id]');
          if (!btn) return;
          e.preventDefault();
          taskFilter = { mode: 'all', value: 'all' };
          setFilterState({
            clientUserId: filterState.clientUserId,
            billingBucketId: parseInt(btn.dataset.jobId || '0', 10) || 0,
          });
          syncFilterControls();
          await loadTasks();
        });
      }
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

  // Deterministic color palette for job avatars — seeded by client_id.
  var jobAvatarHues = [210, 340, 160, 30, 270, 190, 10, 130, 50, 300, 80, 230];
  function jobAvatarColor(clientId) {
    var idx = (parseInt(clientId, 10) || 0) % jobAvatarHues.length;
    return 'hsl(' + jobAvatarHues[idx] + ', 55%, 45%)';
  }
  function jobAvatarHtml(name, clientId) {
    var letter = (name || 'J').charAt(0).toUpperCase();
    var bg = jobAvatarColor(clientId);
    return '<span class="wp-pq-job-avatar" style="background:' + bg + '">' + escapeHtml(letter) + '</span>';
  }

  function renderJobNav() {
    // Job nav is now rendered inline inside the binder Scope section.
    // Keep the old wrapper hidden.
    if (jobNavWrap) jobNavWrap.hidden = true;
    if (jobNavEl) jobNavEl.innerHTML = '';
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

  function syncLanePanel(task) {
    if (!lanePanelEl || !laneSelectEl || !laneSaveBtn) return;
    if (!window.wpPqConfig.canApprove || !task) {
      lanePanelEl.hidden = true;
      return;
    }

    // Hide in auto_job mode (lanes are derived from job assignment).
    if (laneMode === 'auto_job' || laneMode === 'off') {
      lanePanelEl.hidden = true;
      return;
    }

    // Populate lane options from cache.
    var options = '<option value="0">Uncategorized</option>';
    lanesCache.forEach(function (lane) {
      options += '<option value="' + lane.id + '">' + escapeHtml(lane.label) + '</option>';
    });
    laneSelectEl.innerHTML = options;
    laneSelectEl.value = String(parseInt(task.lane_id || 0, 10) || 0);
    lanePanelEl.hidden = !lanesCache.length;
    laneSaveBtn.disabled = false;
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
    card.className = 'wp-pq-task-card is-priority-' + (task.priority || 'normal');
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

    if (task.files_link) {
      cardActions.push('<span class="wp-pq-link-flag" data-tooltip="Has linked files" aria-label="Has linked files">&#128279;</span>');
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

    // "Allowed next" workflow hint
    var flowHint = '';
    var allowedMoves = dragTransitions[normalizeStatus(task.status)] || [];
    if (allowedMoves.length) {
      flowHint = '<p class="wp-pq-card-flow"><strong>Allowed next:</strong> ' + allowedMoves.map(humanizeToken).join(', ') + '</p>';
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
      '</div>' +
      flowHint;

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

  function getLaneCollapseState() {
    try {
      return JSON.parse(localStorage.getItem('wp_pq_lane_collapse') || '{}');
    } catch (e) {
      return {};
    }
  }

  function setLaneCollapsed(laneKey, collapsed) {
    var state = getLaneCollapseState();
    state[laneKey] = collapsed;
    try {
      localStorage.setItem('wp_pq_lane_collapse', JSON.stringify(state));
    } catch (e) { /* ignore */ }
  }

  function renderBoard(tasks) {
    if (!boardEl) return;
    boardEl.innerHTML = '';

    // Determine effective lanes based on mode.
    var effectiveLanes = [];
    if (laneMode === 'manual') {
      effectiveLanes = lanesCache.slice();
    } else if (laneMode === 'auto_job') {
      effectiveLanes = buildAutoJobLanes(tasks);
    }

    // If no lanes to show, render the classic flat board.
    if (!effectiveLanes.length) {
      renderBoardFlat(tasks);
      return;
    }

    // Swimlane mode: group tasks into lanes.
    var laneOrder = effectiveLanes.slice().sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); });
    // Add virtual "Uncategorized" lane at the end.
    laneOrder.push({ id: 0, label: 'Uncategorized', sort_order: 99999, _virtual: true });

    var collapseState = getLaneCollapseState();

    // Sticky column header row.
    var headerRow = document.createElement('div');
    headerRow.className = 'wp-pq-board-lane-header-row';
    // Empty cell for the lane label column.
    var cornerCell = document.createElement('div');
    cornerCell.className = 'wp-pq-board-lane-corner';
    headerRow.appendChild(cornerCell);
    statusColumns.forEach(function (column) {
      var colHead = document.createElement('div');
      colHead.className = 'wp-pq-board-lane-col-head';
      colHead.innerHTML = '<h4>' + escapeHtml(column.label) + '</h4>';
      headerRow.appendChild(colHead);
    });
    boardEl.appendChild(headerRow);

    laneOrder.forEach(function (lane) {
      var laneKey = 'lane_' + lane.id;
      var isCollapsed = !!collapseState[laneKey];
      var laneTasks;
      if (laneMode === 'auto_job') {
        laneTasks = tasks.filter(function (task) {
          var bucketId = parseInt(task.billing_bucket_id || 0, 10);
          return lane.id === 0 ? (bucketId === 0) : (bucketId === lane.id);
        });
      } else {
        laneTasks = tasks.filter(function (task) {
          return lane.id === 0 ? (!task.lane_id || task.lane_id === 0) : (parseInt(task.lane_id, 10) === lane.id);
        });
      }

      // Lane header (full-width, collapsible).
      var laneHeaderEl = document.createElement('div');
      laneHeaderEl.className = 'wp-pq-board-lane-head' + (isCollapsed ? ' is-collapsed' : '');
      laneHeaderEl.dataset.laneId = lane.id;
      laneHeaderEl.innerHTML =
        '<button type="button" class="wp-pq-lane-toggle" aria-expanded="' + (!isCollapsed) + '">' +
        '<span class="wp-pq-lane-arrow">' + (isCollapsed ? '&#9654;' : '&#9660;') + '</span>' +
        ' <strong>' + escapeHtml(lane.label) + '</strong>' +
        ' <span class="wp-pq-lane-count">' + laneTasks.length + '</span>' +
        '</button>';

      laneHeaderEl.querySelector('.wp-pq-lane-toggle').addEventListener('click', function () {
        var nowCollapsed = !isCollapsed;
        setLaneCollapsed(laneKey, nowCollapsed);
        // Re-render board to reflect new state.
        renderBoard(tasks);
        initBoardSort();
      });
      boardEl.appendChild(laneHeaderEl);

      // Lane body (the grid row of columns) — hidden when collapsed.
      if (!isCollapsed) {
        var laneRow = document.createElement('div');
        laneRow.className = 'wp-pq-board-lane-row';
        laneRow.dataset.laneId = lane.id;

        statusColumns.forEach(function (column) {
          var cellTasks = laneTasks.filter(function (task) { return normalizeStatus(task.status) === column.key; });
          var cellEl = document.createElement('div');
          cellEl.className = 'wp-pq-board-lane-cell';
          cellEl.dataset.status = column.key;
          cellEl.dataset.laneId = lane.id;

          var listEl = document.createElement('div');
          listEl.className = 'wp-pq-board-column-list';
          listEl.dataset.status = column.key;
          listEl.dataset.laneId = lane.id;

          if (!cellTasks.length) {
            var emptyEl = document.createElement('p');
            emptyEl.className = 'wp-pq-empty-state';
            emptyEl.textContent = '\u00A0';
            listEl.appendChild(emptyEl);
          } else {
            cellTasks.forEach(function (task) { listEl.appendChild(boardCard(task)); });
          }

          cellEl.appendChild(listEl);
          laneRow.appendChild(cellEl);
        });

        boardEl.appendChild(laneRow);
      }
    });
  }

  function renderBoardFlat(tasks) {
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
      const isNarrow = window.innerWidth <= 640;
      const sortable = Sortable.create(columnEl, {
        group: 'wp-pq-board',
        animation: 80,
        draggable: '.wp-pq-task-card',
        forceFallback: true,
        fallbackOnBody: true,
        fallbackTolerance: 3,
        fallbackClass: 'wp-pq-sortable-fallback',
        disabled: isNarrow,
        delay: isNarrow ? 999 : 0,
        delayOnTouchOnly: true,
        touchStartThreshold: isNarrow ? 50 : 3,
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
          const sourceLaneId = parseInt((evt.from && evt.from.dataset ? evt.from.dataset.laneId : '') || '0', 10);
          const targetLaneId = parseInt((evt.to && evt.to.dataset ? evt.to.dataset.laneId : '') || '0', 10);
          const laneChanged = sourceLaneId !== targetLaneId && lanesCache.length > 0;
          if (evt.oldIndex === evt.newIndex && sourceStatus === targetStatus && !laneChanged) return;

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
            laneId: laneChanged ? targetLaneId : undefined,
          });

          // Cross-lane drag confirmation.
          if (laneChanged && sourceStatus === targetStatus) {
            var targetLane = lanesCache.find(function (l) { return l.id === targetLaneId; });
            var targetLaneLabel = targetLaneId === 0 ? 'Uncategorized' : (targetLane ? targetLane.label : 'Lane #' + targetLaneId);
            if (!confirm('Move task #' + movedTaskId + ' to lane "' + targetLaneLabel + '"?')) {
              await loadTasks();
              return;
            }
          }

          if (!window.wpPqModals.shouldPromptForMoveDecision(sourceStatus, targetStatus)) {
            try {
              selectedTaskId = movedTaskId;
              var moveBody = {
                task_id: movedTaskId,
                target_task_id: targetTaskId || 0,
                position: position,
                target_status: targetStatus,
                priority_direction: 'keep',
                swap_due_dates: false,
              };
              if (laneChanged) {
                moveBody.lane_id = targetLaneId;
              }
              const result = await api('tasks/move', {
                method: 'POST',
                body: JSON.stringify(moveBody),
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
    syncLanePanel(task);
  }

  function isDesktopWorkspace() {
    return window.matchMedia('(min-width: 901px)').matches;
  }

  function drawerIsOpen() {
    return !!drawerEl && drawerEl.classList.contains('is-open');
  }

  var focalHintEl = document.getElementById('wp-pq-focal-hint');

  function openDrawer() {
    if (!drawerEl) return;
    drawerEl.classList.add('is-open');
    drawerEl.setAttribute('aria-hidden', 'false');
    if (appShellEl) appShellEl.classList.add('is-detail-focus');
    if (drawerBackdrop) drawerBackdrop.hidden = isDesktopWorkspace();
    document.body.classList.toggle('wp-pq-drawer-open', !isDesktopWorkspace());
    if (focalHintEl) focalHintEl.hidden = true;
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
    if (focalHintEl) focalHintEl.hidden = false;
  }

  function highlightSelected() {
    document.querySelectorAll('.wp-pq-task, .wp-pq-task-card').forEach((el) => {
      el.classList.toggle('active', parseInt(el.dataset.id, 10) === selectedTaskId);
    });
  }

  async function loadTasks() {
    const data = await api(apiPathWithFilters('tasks'), { method: 'GET' });
    replaceTasks(data.tasks || []);
    lanesCache = Array.isArray(data.lanes) ? data.lanes : [];
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
      const requestedDeadlineVal = (formData.get('requested_deadline') || '').toString().trim();
      if (!requestedDeadlineVal) {
        alert('Please set a requested deadline before submitting.');
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
        requested_deadline: requestedDeadlineVal,
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
      renderEmptyStream(messageList, 'Open a task to see its conversation.');
      return;
    }

    ensureTaskPanelState(selectedTaskId);
    if (taskPanelState.messages && !(options && options.force)) {
      return;
    }
    const data = await api('tasks/' + selectedTaskId + '/conversation', { method: 'GET' });
    messageList.innerHTML = '';
    taskPanelState.messages = true;
    taskPanelState.notes = true;

    var items = data.items || [];
    if (!items.length) {
      renderEmptyStream(messageList, 'No messages yet for this task.');
      return;
    }

    items.forEach(function (item) {
      const li = document.createElement('li');
      li.className = (item.author_id === window.wpPqConfig.currentUserId ? 'mine' : 'theirs') +
        (item.type === 'note' ? ' is-note' : '');
      li.innerHTML = conversationItemHtml(item);
      messageList.appendChild(li);
    });

    var badge = document.getElementById('wp-pq-badge-messages');
    if (badge) badge.textContent = items.length > 0 ? String(items.length) : '';
  }

  async function loadNotes(options) {
    // Notes are now loaded as part of the unified conversation stream.
    return loadMessages(options);
  }

  function wireMessages() {
    if (!messageForm) return;

    // Compose mode toggle: update button label and placeholder.
    if (composeMode) {
      composeMode.addEventListener('change', function () {
        var isNote = composeMode.value === 'note';
        if (composeSubmit) composeSubmit.textContent = isNote ? 'Add Note' : 'Send Message';
        var textarea = messageForm.querySelector('textarea[name="body"]');
        if (textarea) textarea.placeholder = isNote ? 'Write a note (no notifications)\u2026' : 'Write a message\u2026';
      });
    }

    messageForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectedTaskId) return alert('Select a task first.');

      const formData = new FormData(messageForm);
      const body = (formData.get('body') || '').toString().trim();
      if (!body) return;

      var mode = composeMode ? composeMode.value : 'message';
      var endpoint = mode === 'note'
        ? 'tasks/' + selectedTaskId + '/notes'
        : 'tasks/' + selectedTaskId + '/messages';

      try {
        const result = await api(endpoint, { method: 'POST', body: JSON.stringify({ body: body }) });
        messageForm.reset();
        // Reset compose mode to message after sending.
        if (composeMode) {
          composeMode.value = 'message';
          if (composeSubmit) composeSubmit.textContent = 'Send Message';
          var textarea = messageForm.querySelector('textarea[name="body"]');
          if (textarea) textarea.placeholder = 'Write a message\u2026';
        }
        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: false, renderCollections: false });
        }

        // Append item to unified stream.
        var newItem = result.message || result.note;
        var itemType = result.message ? 'message' : 'note';
        if (newItem && messageList) {
          const emptyState = messageList.querySelector('.wp-pq-stream-empty');
          if (emptyState) {
            messageList.innerHTML = '';
          }
          const li = document.createElement('li');
          li.className = (newItem.author_id === window.wpPqConfig.currentUserId ? 'mine' : 'theirs') +
            (itemType === 'note' ? ' is-note' : '');
          li.innerHTML = conversationItemHtml(Object.assign({ type: itemType }, newItem));
          messageList.appendChild(li);
          taskPanelState.messages = true;
          taskPanelState.notes = true;
        } else {
          await loadMessages({ force: true });
        }
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function wireNotes() {
    // Notes are now part of the unified conversation compose form.
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
        alert('Files link saved.', 'success');
        await loadFiles({ force: true });
      } catch (err) {
        alert(err.message || 'Failed to save link.', 'error');
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

  // ── Lane save button ──────────────────────────────────────────────
  if (laneSaveBtn && laneSelectEl) {
    laneSaveBtn.addEventListener('click', async () => {
      if (!selectedTaskId) return;

      const laneId = parseInt(laneSelectEl.value || '0', 10);
      laneSaveBtn.disabled = true;

      try {
        const result = await api('tasks/' + selectedTaskId + '/lane', {
          method: 'POST',
          body: JSON.stringify({ lane_id: laneId }),
        });

        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: false, forceSelect: true });
          await selectTask(selectedTaskId, drawerIsOpen(), {
            preservePanelState: true,
            loadParticipants: false,
            loadWorkspace: false,
          });
          alert('Lane updated.', 'success');
        } else {
          await loadTasks();
        }
      } catch (err) {
        alert(err.message);
      } finally {
        laneSaveBtn.disabled = false;
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

  // ── Lane mode toggle ──────────────────────────────────────────────
  var laneModeSelect = document.getElementById('wp-pq-lane-mode-select');
  var laneModeBar = document.getElementById('wp-pq-lane-mode-bar');

  function initLaneMode() {
    try {
      laneMode = localStorage.getItem('wp_pq_lane_mode') || 'off';
    } catch (e) {
      laneMode = 'off';
    }
    if (laneModeSelect) {
      laneModeSelect.value = laneMode;
      laneModeSelect.addEventListener('change', function () {
        laneMode = laneModeSelect.value;
        try { localStorage.setItem('wp_pq_lane_mode', laneMode); } catch (e) { /* */ }
        renderTaskCollections();
      });
    }
  }

  /**
   * Build virtual lanes from tasks' bucket assignments.
   * Returns an array shaped like lanesCache entries.
   */
  function buildAutoJobLanes(tasks) {
    var bucketMap = {};
    tasks.forEach(function (task) {
      var bucketId = parseInt(task.billing_bucket_id || 0, 10);
      var bucketName = task.bucket_name || 'Main';
      if (bucketId > 0 && !bucketMap[bucketId]) {
        bucketMap[bucketId] = { id: bucketId, label: bucketName, sort_order: Object.keys(bucketMap).length, client_visible: true, _auto: true };
      }
    });
    var lanes = Object.values(bucketMap);
    lanes.sort(function (a, b) { return a.label.localeCompare(b.label); });
    return lanes;
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
    if (!panelMessages || !panelFiles) return;

    // Map legacy 'notes' tab to 'messages' (unified conversation).
    if (tab === 'notes') tab = 'messages';

    const showMessages = tab === 'messages';
    const showMeetings = tab === 'meetings';
    const showFiles = tab === 'files';

    panelMessages.hidden = !showMessages;
    if (panelMeetings) panelMeetings.hidden = !showMeetings;
    panelFiles.hidden = !showFiles;

    if (tabMessagesBtn) tabMessagesBtn.classList.toggle('button-primary', showMessages);
    if (tabMessagesBtn) tabMessagesBtn.classList.toggle('is-active', showMessages);
    if (tabMeetingsBtn) tabMeetingsBtn.classList.toggle('button-primary', showMeetings);
    if (tabMeetingsBtn) tabMeetingsBtn.classList.toggle('is-active', showMeetings);
    if (tabFilesBtn) tabFilesBtn.classList.toggle('button-primary', showFiles);
    if (tabFilesBtn) tabFilesBtn.classList.toggle('is-active', showFiles);

    if (!selectedTaskId) return;
    if (showMessages && !taskPanelState.messages) await loadMessages();
    if (showMeetings && !taskPanelState.meetings) await loadMeetings();
    if (showFiles && !taskPanelState.files) await loadFiles();
  }

  function initWorkspaceTabs() {
    if (!tabMessagesBtn || !tabFilesBtn || !panelMessages || !panelFiles) return;

    tabMessagesBtn.addEventListener('click', () => activateWorkspaceTab('messages').catch(console.error));
    if (tabMeetingsBtn && panelMeetings) {
      tabMeetingsBtn.addEventListener('click', () => activateWorkspaceTab('meetings').catch(console.error));
    }
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
  initLaneMode();
  initWorkspaceTabs();
  initCalendar();
  setActiveView(true);
  resetTaskSummary();

  /* ── Deep-link: ?task=ID opens the task drawer ──── */
  (function initDeepLink() {
    var params = new URLSearchParams(window.location.search);
    var deepTaskId = parseInt(params.get('task') || params.get('task_id') || '0', 10);
    if (!deepTaskId) return;

    // Tasks load async — poll briefly until the task appears in cache.
    var attempts = 0;
    var interval = setInterval(function () {
      attempts++;
      var task = getTaskById(deepTaskId);
      if (task) {
        clearInterval(interval);
        selectTask(deepTaskId, true);
      } else if (attempts > 20) {
        clearInterval(interval);
        // Task might be in a different filter scope — try loading it directly.
        api('tasks/' + deepTaskId, { method: 'GET' }).then(function (data) {
          if (data && data.task) {
            upsertTask(data.task);
            selectTask(deepTaskId, true);
          }
        }).catch(function () {});
      }
    }, 250);
  })();

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
        if (toggleLabel) toggleLabel.textContent = 'Light mode';
      } else {
        wrap.removeAttribute('data-theme');
        toggle.classList.remove('is-active');
        if (toggleLabel) toggleLabel.textContent = 'Dark mode';
      }
    }
    applyTheme(saved === 'dark');
    toggle.addEventListener('click', function () {
      const isDark = wrap.getAttribute('data-theme') !== 'dark';
      applyTheme(isDark);
      localStorage.setItem(key, isDark ? 'dark' : 'light');
    });
  })();

  /* ── Mobile bar toggle ─────────────────────────────── */
  (function initMobileBar() {
    var menuBtn = document.getElementById('wp-pq-mobile-menu-btn');
    var binder = document.getElementById('wp-pq-binder');
    var mobileNewBtn = document.getElementById('wp-pq-mobile-new-btn');
    var createBtn = document.getElementById('wp-pq-open-create');

    if (menuBtn && binder) {
      menuBtn.addEventListener('click', function () {
        binder.classList.toggle('is-open');
      });
    }
    if (mobileNewBtn && createBtn) {
      mobileNewBtn.addEventListener('click', function () {
        createBtn.click();
        if (binder) binder.classList.remove('is-open');
      });
    }
  })();

  // ── Fixed-position tooltip (escapes overflow:hidden ancestors) ──
  (function () {
    var tip = document.getElementById('wp-pq-tooltip');
    if (!tip) return;
    var wrap = document.querySelector('.wp-pq-wrap');
    if (!wrap) return;

    function show(e) {
      var el = e.target.closest('[data-tooltip]');
      if (!el) return;
      var text = el.getAttribute('data-tooltip');
      if (!text) return;
      tip.textContent = text;
      tip.style.display = 'block';
      var rect = el.getBoundingClientRect();
      var tipRect = tip.getBoundingClientRect();
      var top = rect.top - tipRect.height - 6;
      var left = rect.right - tipRect.width;
      if (top < 4) { top = rect.bottom + 6; }
      if (left < 4) { left = rect.left; }
      tip.style.top = top + 'px';
      tip.style.left = left + 'px';
      requestAnimationFrame(function () { tip.classList.add('is-visible'); });
    }

    function hide() {
      tip.classList.remove('is-visible');
      tip.addEventListener('transitionend', function once() {
        tip.removeEventListener('transitionend', once);
        if (!tip.classList.contains('is-visible')) { tip.style.display = 'none'; }
      });
    }

    wrap.addEventListener('mouseover', show);
    wrap.addEventListener('mouseout', function (e) {
      if (e.target.closest('[data-tooltip]')) hide();
    });
  })();

  // ── Client Admin Invites ──────────────────────────────────────────
  (function initClientAdminInvites() {
    var cfg = window.wpPqConfig;
    if (!cfg || !cfg.isClientAdmin || cfg.isManager) return;

    var clientAdminNav = document.getElementById('wp-pq-client-admin-nav');
    if (!clientAdminNav) return;

    var workspace = document.querySelector('.wp-pq-workspace');
    if (!workspace) return;

    // Create the panel element once
    var panel = document.createElement('section');
    panel.className = 'wp-pq-panel';
    panel.id = 'wp-pq-client-invites-panel';
    panel.hidden = true;
    workspace.appendChild(panel);

    // Elements we may want to hide/show
    var boardPanel = document.getElementById('wp-pq-board-panel') || document.getElementById('wp-pq-queue-panel');
    var createPanel = document.getElementById('wp-pq-create-panel');
    var queueBinderSections = document.getElementById('wp-pq-queue-binder-sections');
    var queueNavBtn = document.getElementById('wp-pq-nav-queue');

    function showClientInvites() {
      // Hide queue and compose panels
      if (boardPanel) boardPanel.hidden = true;
      if (createPanel) createPanel.hidden = true;
      if (queueBinderSections) queueBinderSections.hidden = true;
      // Hide any existing manager sections
      document.querySelectorAll('.wp-pq-manager-section').forEach(function (s) { s.hidden = true; });
      // Deactivate queue nav
      if (queueNavBtn) queueNavBtn.classList.remove('is-active');
      // Activate client admin nav
      clientAdminNav.querySelectorAll('.button').forEach(function (b) { b.classList.remove('is-active'); });
      var activeBtn = clientAdminNav.querySelector('[data-pq-section="client-invites"]');
      if (activeBtn) activeBtn.classList.add('is-active');
      // Show panel
      panel.hidden = false;
      loadClientInvites();
    }

    function returnToQueue() {
      panel.hidden = true;
      if (boardPanel) boardPanel.hidden = false;
      if (queueBinderSections) queueBinderSections.hidden = false;
      if (queueNavBtn) queueNavBtn.classList.add('is-active');
      clientAdminNav.querySelectorAll('.button').forEach(function (b) { b.classList.remove('is-active'); });
    }

    // Nav click
    clientAdminNav.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-pq-section]');
      if (!btn) return;
      if (btn.dataset.pqSection === 'client-invites') {
        showClientInvites();
      }
    });

    // Queue nav click should return from client invites
    if (queueNavBtn) {
      queueNavBtn.addEventListener('click', function () {
        returnToQueue();
      });
    }

    var adminClients = cfg.clientAdminClients || [];

    function clientSelectHtml() {
      if (adminClients.length <= 1) return '';
      return '<label>Client <select name="client_id" id="wp-pq-ca-invite-client" required>' +
        adminClients.map(function (c) {
          return '<option value="' + c.id + '">' + escapeHtml(c.name || 'Client #' + c.id) + '</option>';
        }).join('') +
        '</select></label>';
    }

    async function loadClientInvites() {
      panel.innerHTML = '<div class="wp-pq-empty-state">Loading invites...</div>';
      try {
        var data = await api('client/invites');
        var invites = data.invites || [];
        renderClientInvitePanel(invites);
      } catch (err) {
        panel.innerHTML = '<div class="wp-pq-empty-state">Failed to load invites: ' + escapeHtml(err.message) + '</div>';
      }
    }

    function renderClientInvitePanel(invites) {
      var formHtml =
        '<div class="wp-pq-section-heading"><div><h3>Team Invites</h3>' +
        '<p class="wp-pq-panel-note">Invite new team members to your client account.</p></div>' +
        '<div><button class="button button-primary" type="button" id="wp-pq-ca-toggle-invite-form">Send Invite</button></div></div>' +
        '<div id="wp-pq-ca-invite-form-wrap" class="wp-pq-panel wp-pq-manager-card" hidden>' +
        '<form id="wp-pq-ca-invite-form">' +
        '<h3 style="margin:0 0 12px">New Invite</h3>' +
        '<div class="wp-pq-manager-form-grid">' +
        '<label>First Name <input type="text" name="first_name" required></label>' +
        '<label>Last Name <input type="text" name="last_name" required></label>' +
        '<label>Email <input type="email" name="email" required></label>' +
        '<label>Role <select name="client_role">' +
        '<option value="client_contributor">Contributor</option>' +
        '<option value="client_viewer">Viewer</option>' +
        '</select></label>' +
        clientSelectHtml() +
        '</div>' +
        '<div style="margin-top:12px;display:flex;gap:8px">' +
        '<button class="button button-primary" type="submit">Send Invite</button>' +
        '<button class="button" type="button" id="wp-pq-ca-cancel-invite">Cancel</button>' +
        '</div>' +
        '</form></div>';

      var tableHtml = '';
      if (invites.length) {
        tableHtml = '<section class="wp-pq-panel wp-pq-manager-card">' +
          '<table class="wp-pq-admin-table wp-pq-manager-table">' +
          '<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Delivery</th><th>Sent</th><th></th></tr></thead>' +
          '<tbody>' +
          invites.map(function (inv) {
            var statusClass = inv.status === 'accepted' ? 'wp-pq-status-done'
              : inv.status === 'pending' ? 'wp-pq-status-pending'
              : 'wp-pq-status-muted';
            var deliveryIcon = inv.delivery_status === 'sent' ? '<span title="Email sent" style="color:#16a34a">&#10003;</span>'
              : inv.delivery_status === 'failed' ? '<span title="Email failed" style="color:#dc2626">&#10007;</span>'
              : '<span title="Unknown" style="color:#9ca3af">&mdash;</span>';
            var roleLabel = (inv.client_role || 'contributor').replace('client_', '');
            roleLabel = roleLabel.charAt(0).toUpperCase() + roleLabel.slice(1);
            var fullName = [inv.first_name || '', inv.last_name || ''].join(' ').trim();
            var actions = '';
            if (inv.status === 'pending' || inv.status === 'expired') {
              actions += '<button class="button wp-pq-small-action" type="button" data-ca-action="resend" data-invite-id="' + inv.id + '">Resend</button> ';
            }
            if (inv.status === 'pending') {
              actions += '<button class="button wp-pq-small-action" type="button" data-ca-action="copy-link" data-invite-token="' + escapeHtml(inv.token || '') + '">Copy Link</button> ';
              actions += '<button class="button wp-pq-small-action" type="button" data-ca-action="revoke" data-invite-id="' + inv.id + '">Revoke</button>';
            }
            return '<tr>' +
              '<td>' + escapeHtml(fullName || '\u2014') + '</td>' +
              '<td>' + escapeHtml(inv.email) + '</td>' +
              '<td>' + escapeHtml(roleLabel) + '</td>' +
              '<td><span class="' + statusClass + '">' + escapeHtml(inv.status) + '</span></td>' +
              '<td style="text-align:center">' + deliveryIcon + '</td>' +
              '<td>' + escapeHtml(String(inv.created_at || '').slice(0, 10)) + '</td>' +
              '<td class="wp-pq-invite-actions">' + actions + '</td>' +
            '</tr>';
          }).join('') +
          '</tbody></table></section>';
      } else {
        tableHtml = '<div class="wp-pq-empty-state">No invites sent yet.</div>';
      }

      panel.innerHTML = formHtml + tableHtml;

      // Wire up toggle / cancel
      var toggleBtn = document.getElementById('wp-pq-ca-toggle-invite-form');
      var formWrap = document.getElementById('wp-pq-ca-invite-form-wrap');
      var cancelBtn = document.getElementById('wp-pq-ca-cancel-invite');
      if (toggleBtn && formWrap) {
        toggleBtn.addEventListener('click', function () { formWrap.hidden = !formWrap.hidden; });
      }
      if (cancelBtn && formWrap) {
        cancelBtn.addEventListener('click', function () { formWrap.hidden = true; });
      }

      // Form submit
      var form = document.getElementById('wp-pq-ca-invite-form');
      if (form) {
        form.addEventListener('submit', async function (e) {
          e.preventDefault();
          var fd = Object.fromEntries(new FormData(form).entries());
          // Auto-set client_id if only one client
          if (!fd.client_id && adminClients.length === 1) {
            fd.client_id = adminClients[0].id;
          }
          var submitBtn = form.querySelector('[type="submit"]');
          if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending...'; }
          try {
            var result = await api('client/invites', {
              method: 'POST',
              body: JSON.stringify(fd),
            });
            alert(result.message || 'Invite sent.', 'success', { duration: 3200 });
            form.reset();
            if (formWrap) formWrap.hidden = true;
            await loadClientInvites();
          } catch (err) {
            alert(err.message || 'Failed to send invite.', 'error', { duration: 4000 });
          } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Invite'; }
          }
        });
      }
    }

    // Delegated action handler for table actions
    panel.addEventListener('click', async function (e) {
      var btn = e.target.closest('[data-ca-action]');
      if (!btn) return;
      var action = btn.dataset.caAction;

      if (action === 'revoke') {
        var inviteId = btn.dataset.inviteId;
        if (!inviteId || !confirm('Revoke this invite?')) return;
        try {
          await api('client/invites/' + inviteId, { method: 'DELETE' });
          alert('Invite revoked.', 'success', { duration: 3200 });
          await loadClientInvites();
        } catch (err) {
          alert(err.message || 'Failed to revoke invite.', 'error', { duration: 4000 });
        }
        return;
      }

      if (action === 'resend') {
        var inviteId2 = btn.dataset.inviteId;
        if (!inviteId2) return;
        btn.disabled = true;
        btn.textContent = 'Sending...';
        try {
          var result = await api('client/invites/' + inviteId2 + '/resend', { method: 'POST' });
          alert(result.message || 'Invite resent.', 'success', { duration: 3200 });
          await loadClientInvites();
        } catch (err) {
          alert(err.message || 'Failed to resend invite.', 'error', { duration: 4000 });
          btn.disabled = false;
          btn.textContent = 'Resend';
        }
        return;
      }

      if (action === 'copy-link') {
        var token = btn.dataset.inviteToken;
        if (!token) return;
        var link = window.location.origin + '/portal/invite/' + token;
        try {
          await navigator.clipboard.writeText(link);
          alert('Invite link copied to clipboard.', 'success', { duration: 3200 });
        } catch (copyErr) {
          window.prompt('Copy this invite link:', link);
        }
        return;
      }
    });
  })();

  loadTasks().catch(console.error);
})();
