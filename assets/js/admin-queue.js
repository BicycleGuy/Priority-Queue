(function () {
  if (typeof window.wpPqConfig === 'undefined') return;

  const apiRoot = window.wpPqConfig.root;
  const coreRoot = window.wpPqConfig.coreRoot || '/wp-json/wp/v2/';
  const headers = { 'X-WP-Nonce': window.wpPqConfig.nonce };

  const statusColumns = [
    { key: 'pending_approval', label: 'Pending Approval' },
    { key: 'not_approved', label: 'Needs Clarification' },
    { key: 'approved', label: 'Approved' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'revision_requested', label: 'Revisions Needed' },
    { key: 'pending_review', label: 'Needs Review' },
    { key: 'delivered', label: 'Delivered' },
  ];

  const tokenLabels = {
    pending_approval: 'Pending Approval',
    pending_review: 'Needs Review',
    not_approved: 'Needs Clarification',
    revision_requested: 'Revisions Needed',
    task_rejected: 'Clarification Requested',
    task_assigned: 'Task Assigned',
    task_reprioritized: 'Priority Changed',
    task_schedule_changed: 'Schedule Changed',
    statement_batched: 'Statement Batched',
  };

  const prefGroups = [
    {
      key: 'review',
      label: 'Reviews and approvals',
      description: 'New requests, approvals, and clarification requests',
      events: ['task_created', 'task_assigned', 'task_approved', 'task_rejected'],
    },
    {
      key: 'mentions',
      label: 'Mentions',
      description: 'Direct @mentions on a task',
      events: ['task_mentioned'],
    },
    {
      key: 'schedule',
      label: 'Schedule changes',
      description: 'Priority changes and date updates',
      events: ['task_reprioritized', 'task_schedule_changed'],
    },
    {
      key: 'delivery',
      label: 'Delivery and revisions',
      description: 'Deliveries, revisions, and statement batching',
      events: ['task_revision_requested', 'task_delivered', 'statement_batched'],
    },
    {
      key: 'retention',
      label: 'Retention reminders',
      description: 'Day-300 storage reminder',
      events: ['retention_day_300'],
    },
  ];

  const taskList = document.getElementById('wp-pq-task-list');
  const boardEl = document.getElementById('wp-pq-board');
  const boardSummaryEl = document.getElementById('wp-pq-board-summary');
  const boardFiltersEl = document.getElementById('wp-pq-board-filters');
  const clientFilterWrap = document.getElementById('wp-pq-client-filter-wrap');
  const clientFilterEl = document.getElementById('wp-pq-client-filter');
  const bucketFilterWrap = document.getElementById('wp-pq-bucket-filter-wrap');
  const bucketFilterEl = document.getElementById('wp-pq-bucket-filter');
  const clearFiltersBtn = document.getElementById('wp-pq-clear-filters');
  const batchStatementBtn = document.getElementById('wp-pq-batch-statement');
  const createForm = document.getElementById('wp-pq-create-form');
  const createPanel = document.getElementById('wp-pq-create-panel');
  const openCreateBtn = document.getElementById('wp-pq-open-create');
  const closeCreateBtn = document.getElementById('wp-pq-close-create');
  const createClientWrap = document.getElementById('wp-pq-create-client-wrap');
  const createClientEl = document.getElementById('wp-pq-create-client');
  const createBucketEl = document.getElementById('wp-pq-create-bucket');
  const createNewBucketWrap = document.getElementById('wp-pq-create-new-bucket-wrap');
  const createNewBucketEl = document.getElementById('wp-pq-create-new-bucket');
  const openInboxBtn = document.getElementById('wp-pq-open-inbox');
  const closeInboxBtn = document.getElementById('wp-pq-close-inbox');
  const inboxPanel = document.getElementById('wp-pq-inbox-panel');
  const inboxList = document.getElementById('wp-pq-inbox-list');
  const inboxCount = document.getElementById('wp-pq-inbox-count');
  const markAllReadBtn = document.getElementById('wp-pq-mark-all-read');
  const openPrefsBtn = document.getElementById('wp-pq-open-prefs');
  const closePrefsBtn = document.getElementById('wp-pq-close-prefs');
  const prefPanel = document.getElementById('wp-pq-pref-panel');
  const prefList = document.getElementById('wp-pq-pref-list');
  const prefSaveBtn = document.getElementById('wp-pq-save-prefs');
  const currentTaskEl = document.getElementById('wp-pq-current-task');
  const currentTaskStatusEl = document.getElementById('wp-pq-current-task-status');
  const currentTaskMetaEl = document.getElementById('wp-pq-current-task-meta');
  const currentTaskGuidanceEl = document.getElementById('wp-pq-current-task-guidance');
  const currentTaskDescriptionEl = document.getElementById('wp-pq-current-task-description');
  const currentTaskActionsEl = document.getElementById('wp-pq-current-task-actions');
  const assignmentPanelEl = document.getElementById('wp-pq-assignment-panel');
  const assignmentSummaryEl = document.getElementById('wp-pq-assignment-summary');
  const assignmentSelectEl = document.getElementById('wp-pq-assignment-select');
  const assignmentSaveBtn = document.getElementById('wp-pq-save-assignment');
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
  const fileForm = document.getElementById('wp-pq-file-form');
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
  const drawerEl = document.getElementById('wp-pq-task-drawer');
  const drawerBackdrop = document.getElementById('wp-pq-drawer-backdrop');
  const drawerCloseBtn = document.getElementById('wp-pq-close-drawer');
  const revisionModalBackdrop = document.getElementById('wp-pq-revision-modal-backdrop');
  const revisionModal = document.getElementById('wp-pq-revision-modal');
  const revisionForm = document.getElementById('wp-pq-revision-form');
  const revisionSummaryEl = document.getElementById('wp-pq-revision-summary');
  const closeRevisionModalBtn = document.getElementById('wp-pq-close-revision-modal');
  const cancelRevisionBtn = document.getElementById('wp-pq-cancel-revision');
  const moveModalBackdrop = document.getElementById('wp-pq-move-modal-backdrop');
  const moveModal = document.getElementById('wp-pq-move-modal');
  const moveForm = document.getElementById('wp-pq-move-form');
  const closeMoveModalBtn = document.getElementById('wp-pq-close-move-modal');
  const cancelMoveBtn = document.getElementById('wp-pq-cancel-move');
  const moveTitleEl = document.getElementById('wp-pq-move-title');
  const moveSummaryEl = document.getElementById('wp-pq-move-summary');
  const applyMoveBtn = document.getElementById('wp-pq-apply-move');
  const moveMeetingOption = document.getElementById('wp-pq-move-meeting-option');

  let selectedTaskId = null;
  let tasksCache = [];
  let calendar = null;
  let pendingMove = null;
  let pendingStatusAction = null;
  let pendingRevisionAction = null;
  let participantCache = [];
  let currentView = 'board';
  let prefsLoaded = false;
  let prefState = {};
  let taskPanelState = { taskId: null, messages: false, meetings: false, notes: false, files: false, participants: false };
  let selectedBatchTaskIds = new Set();
  let filterState = { clientUserId: 0, billingBucketId: 0 };
  let filterOptions = { canViewAll: !!window.wpPqConfig.canViewAll, clients: [], buckets: [] };
  let createFormState = { clientUserId: 0, billingBucketId: 0 };
  let workersCache = [];
  let boardSortInstances = [];

  async function api(path, options) {
    let resp;
    try {
      resp = await fetch(apiRoot + path, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          ...headers,
        },
        credentials: 'same-origin',
      });
    } catch (err) {
      throw new Error('Connection failed. Please try again.');
    }

    if (!resp.ok) {
      const body = await resp.json().catch(() => ({}));
      throw new Error(body.message || 'Request failed');
    }

    return resp.json();
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

  function formatDateTime(value) {
    if (!value) return '';
    const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T') + 'Z';
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleString();
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

  function fileItemHtml(file) {
    const fileName = file.filename || ('media #' + file.media_id);
    const fileUrl = file.media_url || '';
    const createdAt = formatDateTime(file.created_at);
    return '<div><strong>' + escapeHtml(file.file_role) + '</strong> v' + escapeHtml(file.version_num) + '</div>' +
      (fileUrl
        ? '<div><a href="' + encodeURI(fileUrl) + '" target="_blank" rel="noopener">' + escapeHtml(fileName) + '</a></div>'
        : '<div>' + escapeHtml(fileName) + '</div>') +
      (createdAt ? '<div class="msg-author">Uploaded: ' + escapeHtml(createdAt) + '</div>' : '');
  }

  function currentTaskQuery() {
    const params = new URLSearchParams();
    if (filterState.clientUserId > 0) {
      params.set('client_user_id', String(filterState.clientUserId));
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
    return filterOptions.buckets.filter((bucket) => parseInt(bucket.client_user_id, 10) === filterState.clientUserId);
  }

  function syncFilterControls() {
    if (!boardFiltersEl) return;

    const canViewAll = !!filterOptions.canViewAll;
    const clientOptions = Array.isArray(filterOptions.clients) ? filterOptions.clients : [];
    const bucketOptions = visibleBuckets();

    boardFiltersEl.hidden = !canViewAll && bucketOptions.length <= 1;

    if (clientFilterWrap && clientFilterEl) {
      clientFilterWrap.hidden = !canViewAll;
      if (canViewAll) {
        clientFilterEl.innerHTML = '<option value="0">All clients</option>' + clientOptions.map((client) => (
          '<option value="' + escapeHtml(client.id) + '">' + escapeHtml(client.label || client.name || ('Client #' + client.id)) + '</option>'
        )).join('');
        clientFilterEl.value = String(filterState.clientUserId || 0);
      }
    }

    if (bucketFilterWrap && bucketFilterEl) {
      const shouldShowBuckets = bucketOptions.length > 1 || filterState.billingBucketId > 0 || (!canViewAll && bucketOptions.length > 0);
      bucketFilterWrap.hidden = !shouldShowBuckets;
      bucketFilterEl.innerHTML = '<option value="0">' + (canViewAll && !filterState.clientUserId ? 'All jobs' : 'All jobs') + '</option>' + bucketOptions.map((bucket) => (
        '<option value="' + escapeHtml(bucket.id) + '">' + escapeHtml(bucket.label || bucket.bucket_name || 'Job Bucket') + '</option>'
      )).join('');

      const bucketIsValid = bucketOptions.some((bucket) => parseInt(bucket.id, 10) === filterState.billingBucketId);
      if (!bucketIsValid) {
        filterState.billingBucketId = 0;
      }
      bucketFilterEl.value = String(filterState.billingBucketId || 0);
    }

    if (clearFiltersBtn) {
      clearFiltersBtn.hidden = !(filterState.clientUserId || filterState.billingBucketId);
    }

    syncCreateFormContext();
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
      createClientId > 0 ? parseInt(bucket.client_user_id, 10) === createClientId : !canViewAll
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
        '<option value="' + escapeHtml(bucket.id) + '">' + escapeHtml(bucket.label || bucket.bucket_name || 'Job Bucket') + '</option>'
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
    const index = tasksCache.findIndex((item) => item.id === task.id);
    if (index >= 0) {
      tasksCache[index] = task;
      return;
    }
    tasksCache.push(task);
  }

  function replaceTasks(tasks) {
    tasksCache = Array.isArray(tasks) ? tasks.slice() : [];
  }

  function pruneBatchSelection() {
    selectedBatchTaskIds = new Set(
      Array.from(selectedBatchTaskIds).filter((taskId) => {
        const task = tasksCache.find((item) => item.id === taskId);
        return !!task && task.status === 'delivered' && task.billing_status === 'unbilled';
      })
    );
  }

  function renderTaskCollections() {
    pruneBatchSelection();

    if (boardEl) {
      renderBoard(tasksCache);
      updateBoardSummary(tasksCache);
      updateBatchButton();
      initBoardSort();
    } else {
      renderTaskList(tasksCache);
      if (selectedTaskId === null && tasksCache[0]) {
        selectedTaskId = tasksCache[0].id;
      }
    }

    highlightSelected();
  }

  async function syncTaskWorkspace(options) {
    const reloadActivePane = !!(options && options.reloadActivePane);
    const refreshCalendar = !!(options && options.refreshCalendar);
    const forceSelect = !!(options && options.forceSelect);

    const current = selectedTaskId ? getTaskById(selectedTaskId) : null;
    if (current) {
      await updateTaskSummary(current);
      if ((!boardEl || drawerIsOpen() || forceSelect) && reloadActivePane) {
        ensureTaskPanelState(current.id);
        await Promise.all([loadParticipants(), loadActiveWorkspacePane()]);
      }
    } else {
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
      renderTaskCollections();
    }
    await syncTaskWorkspace(options || {});
  }

  function nextStepLabel(task) {
    const status = String(task.status || '');
    if (status === 'pending_approval') return 'Waiting for approval';
    if (status === 'not_approved') return 'Clarification needed from client';
    if (status === 'approved') return 'Ready to begin work';
    if (status === 'in_progress') return 'Work is in progress';
    if (status === 'pending_review') return 'Ready for internal review';
    if (status === 'delivered') return task.billing_status === 'batched' ? 'Added to statement batch' : 'Waiting for batching or client response';
    if (status === 'revision_requested') return 'Revisions needed';
    return humanizeToken(status);
  }

  function taskMetaChips(task) {
    const chips = [
      '<span class="wp-pq-detail-pill">Task #' + escapeHtml(task.id) + '</span>',
      '<span class="wp-pq-detail-pill">Priority: ' + escapeHtml(humanizeToken(task.priority)) + '</span>',
      '<span class="wp-pq-detail-pill">Status: ' + escapeHtml(humanizeToken(task.status)) + '</span>',
    ];

    if (task.requested_deadline) {
      chips.push('<span class="wp-pq-detail-pill">Requested: ' + escapeHtml(formatDateTime(task.requested_deadline)) + '</span>');
    }
    if (task.due_at) {
      chips.push('<span class="wp-pq-detail-pill">Due: ' + escapeHtml(formatDateTime(task.due_at)) + '</span>');
    }
    if (task.delivered_at) {
      chips.push('<span class="wp-pq-detail-pill">Delivered: ' + escapeHtml(formatDateTime(task.delivered_at)) + '</span>');
    }
    if (task.billing_status) {
      chips.push('<span class="wp-pq-detail-pill">Billing: ' + escapeHtml(humanizeToken(task.billing_status)) + '</span>');
    }
    if (task.statement_code) {
      chips.push('<span class="wp-pq-detail-pill">Statement: ' + escapeHtml(task.statement_code) + '</span>');
    }
    if (task.statement_batched_at) {
      chips.push('<span class="wp-pq-detail-pill">Batched: ' + escapeHtml(formatDateTime(task.statement_batched_at)) + '</span>');
    }
    if (task.bucket_name) {
      chips.push('<span class="wp-pq-detail-pill">Job: ' + escapeHtml(task.bucket_name) + '</span>');
    }
    if (task.client_name) {
      chips.push('<span class="wp-pq-detail-pill">Client: ' + escapeHtml(task.client_name) + '</span>');
    }
    if (task.submitter_name) {
      chips.push('<span class="wp-pq-detail-pill">Requester: ' + escapeHtml(task.submitter_name) + '</span>');
    }
    if (task.action_owner_name) {
      chips.push('<span class="wp-pq-detail-pill">Action owner: ' + escapeHtml(task.action_owner_name) + '</span>');
    } else if (Array.isArray(task.owner_names) && task.owner_names.length) {
      chips.push('<span class="wp-pq-detail-pill">Owners: ' + escapeHtml(task.owner_names.join(', ')) + '</span>');
    }
    if (task.needs_meeting) {
      chips.push('<span class="wp-pq-detail-pill meeting">Meeting requested</span>');
    }

    return chips.join('');
  }

  function taskGuidance(task) {
    const status = String(task.status || '');
    if (status === 'pending_approval') {
      return window.wpPqConfig.canApprove
        ? 'This request is waiting on your approval. Approve it or send it back for clarification.'
        : 'Your request is pending approval. We will either approve it or ask for clarification.';
    }
    if (status === 'not_approved') return 'This request needs clarification before work can continue.';
    if (status === 'approved') return 'The request is approved. The next step is to start work or assign an owner.';
    if (status === 'in_progress') return 'Work is underway. When execution is complete, send the task to review.';
    if (status === 'pending_review') return 'Execution is finished. Review the work here before delivery or sending it back for revisions.';
    if (status === 'delivered') return task.billing_status === 'batched'
      ? 'This delivered task has already been added to a statement batch.'
      : 'A deliverable is ready. Keep it here until you batch it into a statement or request revisions.';
    if (status === 'revision_requested') return 'Revisions were requested. Update the files and return the task to active work.';
    return '';
  }

  function taskActorLabel(task) {
    if (task.action_owner_name) return 'Awaiting ' + task.action_owner_name;
    if (task.client_name && task.status === 'not_approved') return 'Awaiting ' + task.client_name;
    return 'Awaiting assignment';
  }

  function assignmentSummary(task) {
    const requester = task.submitter_name || 'Requester';
    const client = task.client_name || requester;
    if (task.action_owner_name) {
      return 'Requester: ' + requester + ' · Client: ' + client + ' · Current owner: ' + task.action_owner_name;
    }
    return 'Requester: ' + requester + ' · Client: ' + client + ' · No action owner assigned yet.';
  }

  async function loadWorkers() {
    if (!window.wpPqConfig.canAssign) return [];
    if (workersCache.length) return workersCache;
    const data = await api('workers', { method: 'GET' });
    workersCache = Array.isArray(data.workers) ? data.workers : [];
    return workersCache;
  }

  function syncAssignmentPanel(task) {
    if (!assignmentPanelEl || !assignmentSummaryEl || !assignmentSelectEl || !assignmentSaveBtn) return;
    if (!window.wpPqConfig.canAssign || !task) {
      assignmentPanelEl.hidden = true;
      return;
    }

    assignmentPanelEl.hidden = false;
    assignmentSummaryEl.textContent = assignmentSummary(task);

    const options = ['<option value="0">Unassigned</option>'].concat(
      workersCache.map((worker) => '<option value="' + escapeHtml(worker.id) + '">' + escapeHtml(worker.name + ' (' + worker.email + ')') + '</option>')
    );
    assignmentSelectEl.innerHTML = options.join('');
    assignmentSelectEl.value = String(parseInt(task.action_owner_id || 0, 10) || 0);
    assignmentSaveBtn.disabled = false;
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
    card.className = 'wp-pq-task-card';
    card.dataset.id = task.id;

    const brief = truncateText(task.description || 'No request brief yet.', 160);
    const deadline = formatDateTime(task.requested_deadline || task.due_at);
    const actionOwner = task.action_owner_name || '';
    const badges = [
      '<span class="wp-pq-badge priority-' + escapeHtml(task.priority) + '">' + escapeHtml(humanizeToken(task.priority)) + '</span>',
    ];

    if (deadline) {
      badges.push('<span class="wp-pq-badge muted">Due ' + escapeHtml(deadline) + '</span>');
    }
    if (task.needs_meeting) {
      badges.push('<span class="wp-pq-badge meeting">Meeting requested</span>');
    }
    if (task.billing_status === 'batched' && task.statement_code) {
      badges.push('<span class="wp-pq-badge muted">Statement ' + escapeHtml(task.statement_code) + '</span>');
    }
    if (task.bucket_name) {
      badges.push('<span class="wp-pq-badge muted">' + escapeHtml(task.bucket_name) + '</span>');
    }

    card.innerHTML =
      '<div class="wp-pq-task-card-top">' +
      '<span class="wp-pq-card-grip" title="Drag to reprioritize">::</span>' +
      '<span class="wp-pq-task-id">Task #' + escapeHtml(task.id) + '</span>' +
      (window.wpPqConfig.canBatch && task.status === 'delivered' && task.billing_status === 'unbilled'
        ? '<label class="wp-pq-batch-pick"><input type="checkbox" data-batch-task-id="' + escapeHtml(task.id) + '"' + (selectedBatchTaskIds.has(task.id) ? ' checked' : '') + '><span>Batch</span></label>'
        : '') +
      (task.note_count > 0
        ? '<span class="wp-pq-note-flag" title="' + escapeHtml((task.latest_note_preview || (task.note_count + ' sticky notes on this task'))) + '"></span>'
        : '') +
      '</div>' +
      '<h4>' + escapeHtml(task.title) + '</h4>' +
      '<p class="wp-pq-task-brief">' + escapeHtml(brief || 'No request brief yet.') + '</p>' +
      '<div class="wp-pq-task-badges">' + badges.join('') + '</div>' +
      '<div class="wp-pq-task-next-step">' + escapeHtml(nextStepLabel(task)) + '</div>' +
      '<div class="wp-pq-task-footer">' +
      '<span>' + escapeHtml(taskActorLabel(task)) + '</span>' +
      (window.wpPqConfig.canViewAll && (task.client_name || task.submitter_name)
        ? '<span>' + escapeHtml(task.client_name || task.submitter_name) + '</span>'
        : (actionOwner ? '<span>' + escapeHtml(actionOwner) + '</span>' : '')) +
      '</div>';

    card.addEventListener('click', () => selectTask(task.id, true));
    const grip = card.querySelector('.wp-pq-card-grip');
    if (grip) {
      grip.addEventListener('click', (e) => e.stopPropagation());
    }
    const batchPick = card.querySelector('[data-batch-task-id]');
    if (batchPick) {
      batchPick.addEventListener('click', (e) => e.stopPropagation());
      batchPick.addEventListener('change', (e) => {
        const taskId = parseInt(e.target.getAttribute('data-batch-task-id'), 10);
        if (!taskId) return;
        if (e.target.checked) {
          selectedBatchTaskIds.add(taskId);
        } else {
          selectedBatchTaskIds.delete(taskId);
        }
        updateBatchButton();
      });
    }
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
    if (window.wpPqConfig.canApprove && task.status === 'pending_approval') {
      buttons.push(buttonHtml(task.id, 'approved', 'Approve'));
      buttons.push(buttonHtml(task.id, 'not_approved', 'Needs Clarification'));
    }
    if (window.wpPqConfig.canApprove && task.status === 'not_approved') {
      buttons.push(buttonHtml(task.id, 'approved', 'Approve'));
    }
    if (window.wpPqConfig.canApprove && task.status === 'approved') {
      buttons.push(buttonHtml(task.id, 'not_approved', 'Needs Clarification'));
    }
    if (task.status === 'approved') {
      buttons.push(buttonHtml(task.id, 'in_progress', 'Start Work'));
      buttons.push(buttonHtml(task.id, 'revision_requested', 'Request Revisions'));
    }
    if (task.status === 'in_progress') {
      buttons.push(buttonHtml(task.id, 'pending_review', 'Send to Review'));
      buttons.push(buttonHtml(task.id, 'revision_requested', 'Request Revisions'));
    }
    if (task.status === 'pending_review') {
      buttons.push(buttonHtml(task.id, 'delivered', 'Mark Delivered'));
      buttons.push(buttonHtml(task.id, 'revision_requested', 'Request Revisions'));
    }
    if (task.status === 'delivered') {
      buttons.push(buttonHtml(task.id, 'revision_requested', 'Request Revisions'));
    }
    if (task.status === 'revision_requested') buttons.push(buttonHtml(task.id, 'in_progress', 'Resume Work'));
    return buttons.join(' ');
  }

  function buttonHtml(taskId, status, label) {
    return '<button type="button" class="button wp-pq-status-btn" data-task-id="' + taskId + '" data-status="' + status + '">' + label + '</button>';
  }

  function openRevisionModal(action) {
    if (!revisionModal || !revisionModalBackdrop || !revisionForm || !action) return;
    pendingRevisionAction = action;
    const task = getTaskById(action.taskId);
    const textarea = revisionForm.querySelector('textarea[name="revision_note"]');
    const checkbox = revisionForm.querySelector('input[name="post_message"]');
    if (revisionSummaryEl) {
      revisionSummaryEl.textContent = task
        ? 'Explain what needs to change on "' + task.title + '". This will guide the next revision cycle.'
        : 'Add a short note so the requester knows what to revise.';
    }
    if (textarea) textarea.value = '';
    if (checkbox) checkbox.checked = true;
    revisionModal.hidden = false;
    revisionModal.setAttribute('aria-hidden', 'false');
    revisionModalBackdrop.hidden = false;
    if (textarea) textarea.focus();
  }

  async function closeRevisionModal(shouldResetBoard) {
    if (revisionModal) {
      revisionModal.hidden = true;
      revisionModal.setAttribute('aria-hidden', 'true');
    }
    if (revisionModalBackdrop) revisionModalBackdrop.hidden = true;
    const resetBoard = (!!pendingRevisionAction || !!pendingMove || !!pendingStatusAction) && shouldResetBoard;
    pendingRevisionAction = null;
    pendingMove = null;
    pendingStatusAction = null;
    if (resetBoard) {
      await loadTasks();
    }
  }

  function renderBoard(tasks) {
    if (!boardEl) return;
    boardEl.innerHTML = '';

    statusColumns.forEach((column) => {
      const tasksInColumn = tasks.filter((task) => task.status === column.key);
      const columnEl = document.createElement('section');
      columnEl.className = 'wp-pq-board-column';
      columnEl.innerHTML =
        '<header class="wp-pq-board-column-head">' +
        '<h4>' + escapeHtml(column.label) + '</h4>' +
        '<span>' + tasksInColumn.length + '</span>' +
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

  function shouldPromptForMoveDecision(sourceStatus, targetStatus) {
    const effectiveStatus = targetStatus || sourceStatus;
    return ['pending_approval', 'not_approved', 'approved', 'in_progress', 'pending_review', 'revision_requested'].includes(effectiveStatus);
  }

  function moveDecisionConfig(move) {
    if (!move || move.sourceStatus === move.targetStatus) {
      return {
        title: 'Apply queue change?',
        body: 'This changes where the task sits in the active queue. If the move reflects a change in urgency, you can raise or lower priority here as well.',
        cta: 'Apply Queue Change',
      };
    }

    const configs = {
      pending_approval: {
        title: 'Send for Approval?',
        body: 'This places the task in the approval queue so you can review scope, urgency, and timing before work begins.',
        cta: 'Send for Approval',
      },
      pending_review: {
        title: 'Send to Review?',
        body: 'This marks the task as ready for review. Responsibility may shift from execution to reviewer follow-up.',
        cta: 'Send to Review',
      },
      not_approved: {
        title: 'Move to Needs Clarification?',
        body: 'This marks the task as waiting on additional information or direction. You can also open the meeting scheduler next if a call would unblock the task.',
        cta: 'Request Clarification',
      },
      approved: {
        title: 'Mark as Approved?',
        body: 'This confirms the task has been reviewed and accepted at this stage. Downstream work may now proceed.',
        cta: 'Mark Approved',
      },
      in_progress: {
        title: 'Start Work?',
        body: 'This moves the task into active execution. It will appear in current work queues and progress tracking.',
        cta: 'Start Work',
      },
      delivered: {
        title: 'Mark as Delivered?',
        body: 'This records the task as delivered to the requester or client. Further changes may require a revision cycle.',
        cta: 'Mark Delivered',
      },
      revision_requested: {
        title: 'Send to Revisions?',
        body: 'This returns the task for additional changes before completion. It will re-enter active work queues.',
        cta: 'Request Revisions',
      },
    };

    return configs[move.targetStatus] || {
      title: 'Move task?',
      body: 'This changes the task status in the workflow.',
      cta: 'Move Task',
    };
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
        animation: 160,
        handle: '.wp-pq-card-grip',
        onEnd: async (evt) => {
          const sourceStatus = evt.from && evt.from.dataset ? evt.from.dataset.status : '';
          const targetStatus = evt.to && evt.to.dataset ? evt.to.dataset.status : sourceStatus;
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

          pendingMove = {
            taskId: movedTaskId,
            targetTaskId: targetTaskId,
            position: position,
            sourceStatus: sourceStatus,
            targetStatus: targetStatus,
          };

          if (targetStatus === 'revision_requested') {
            openRevisionModal({
              type: 'move',
              taskId: movedTaskId,
              targetTaskId: targetTaskId,
              position: position,
              sourceStatus: sourceStatus,
              targetStatus: targetStatus,
            });
            return;
          }

          if (!shouldPromptForMoveDecision(sourceStatus, targetStatus)) {
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
                syncOrderFromBoardDom();
                await refreshFromCache({ reloadActivePane: false, refreshCalendar: currentView === 'calendar' });
              } else {
                await loadTasks();
              }
            } catch (err) {
              alert(err.message);
              await loadTasks();
            }
            return;
          }

          openMoveModal();
        },
      });
      boardSortInstances.push(sortable);
    });
  }

  function openMoveModal() {
    if (!moveModal || !moveModalBackdrop || (!pendingMove && !pendingStatusAction)) return;

    const action = pendingMove || pendingStatusAction;
    const movedTask = getTaskById(action.taskId);
    const targetTask = pendingMove ? getTaskById(pendingMove.targetTaskId) : null;
    const targetStatus = pendingMove ? pendingMove.targetStatus : pendingStatusAction.status;
    const config = moveDecisionConfig({
      sourceStatus: pendingMove ? pendingMove.sourceStatus : (movedTask ? movedTask.status : ''),
      targetStatus: targetStatus,
    });
    if (moveTitleEl) moveTitleEl.textContent = config.title;
    if (applyMoveBtn) applyMoveBtn.textContent = config.cta;

    const staticCopy = moveForm ? moveForm.querySelector('.wp-pq-choice-static small') : null;
    if (staticCopy) {
      staticCopy.textContent = pendingMove
        ? 'This move updates task order. If you moved the task into a new column, it also changes status.'
        : 'This move changes task status. Priority and meeting follow-up are optional.';
    }

    if (moveSummaryEl && movedTask && targetTask) {
      const moveText = '"' + movedTask.title + '" moved ' + pendingMove.position + ' "' + targetTask.title + '". ';
      moveSummaryEl.textContent = moveText + config.body;
    } else if (moveSummaryEl && movedTask) {
      moveSummaryEl.textContent = '"' + movedTask.title + '" moved into ' + humanizeToken(targetStatus) + '. ' + config.body;
    } else if (moveSummaryEl) {
      moveSummaryEl.textContent = config.body;
    }

    const keepPriority = moveForm ? moveForm.querySelector('input[name="priority_direction"][value="keep"]') : null;
    const swapDueDates = moveForm ? moveForm.querySelector('input[name="swap_due_dates"]') : null;
    const requestMeeting = moveForm ? moveForm.querySelector('input[name="request_meeting"]') : null;
    if (keepPriority) keepPriority.checked = true;
    if (swapDueDates) swapDueDates.checked = false;
    if (swapDueDates) swapDueDates.disabled = !pendingMove || !pendingMove.targetTaskId;
    if (requestMeeting) requestMeeting.checked = false;
    if (moveMeetingOption) {
      moveMeetingOption.hidden = targetStatus !== 'not_approved';
    }

    moveModal.hidden = false;
    moveModal.setAttribute('aria-hidden', 'false');
    moveModalBackdrop.hidden = false;
  }

  async function closeMoveModal(shouldResetBoard) {
    if (moveModal) {
      moveModal.hidden = true;
      moveModal.setAttribute('aria-hidden', 'true');
    }
    if (moveModalBackdrop) moveModalBackdrop.hidden = true;

    const resetBoard = (!!pendingMove || !!pendingStatusAction) && shouldResetBoard;
    pendingMove = null;
    pendingStatusAction = null;

    if (resetBoard) {
      await loadTasks();
    }
  }

  function updateBoardSummary(tasks) {
    if (!boardSummaryEl) return;

    const urgentCount = tasks.filter((task) => task.priority === 'urgent').length;
    const approvalCount = tasks.filter((task) => task.status === 'pending_approval').length;
    const reviewCount = tasks.filter((task) => task.status === 'pending_review').length;
    const blockedCount = tasks.filter((task) => task.status === 'not_approved' || task.status === 'revision_requested').length;

    boardSummaryEl.innerHTML =
      '<span class="wp-pq-summary-pill">' + tasks.length + ' active</span>' +
      '<span class="wp-pq-summary-pill">' + urgentCount + ' urgent</span>' +
      '<span class="wp-pq-summary-pill">' + approvalCount + ' awaiting approval</span>' +
      '<span class="wp-pq-summary-pill">' + reviewCount + ' awaiting review</span>' +
      '<span class="wp-pq-summary-pill">' + blockedCount + ' waiting on changes</span>';
  }

  function updateBatchButton() {
    if (!batchStatementBtn || !window.wpPqConfig.canBatch) return;
    const count = selectedBatchTaskIds.size;
    batchStatementBtn.hidden = count === 0;
    batchStatementBtn.textContent = count > 0
      ? 'Create Statement from Selected (' + count + ')'
      : 'Create Statement from Selected';
  }

  function getTaskById(taskId) {
    return tasksCache.find((task) => task.id === taskId) || null;
  }

  function resetTaskSummary() {
    if (currentTaskStatusEl) currentTaskStatusEl.textContent = 'Select a task';
    if (currentTaskEl) currentTaskEl.textContent = currentTaskStatusEl ? 'Task Details' : 'Select a task from the queue.';
    if (currentTaskMetaEl) currentTaskMetaEl.innerHTML = 'Choose a board card or calendar item to open its workspace.';
    if (currentTaskGuidanceEl) {
      currentTaskGuidanceEl.hidden = true;
      currentTaskGuidanceEl.textContent = '';
    }
    if (currentTaskDescriptionEl) currentTaskDescriptionEl.textContent = '';
    if (currentTaskActionsEl) currentTaskActionsEl.innerHTML = '';
    if (assignmentPanelEl) assignmentPanelEl.hidden = true;
  }

  async function updateTaskSummary(task) {
    if (currentTaskStatusEl) currentTaskStatusEl.textContent = humanizeToken(task.status);
    if (currentTaskEl) currentTaskEl.textContent = currentTaskStatusEl ? task.title : 'Selected task: #' + task.id + ' - ' + task.title;
    if (currentTaskMetaEl) currentTaskMetaEl.innerHTML = taskMetaChips(task);
    if (currentTaskGuidanceEl) {
      const guidance = taskGuidance(task);
      currentTaskGuidanceEl.textContent = guidance;
      currentTaskGuidanceEl.hidden = !guidance;
    }
    if (currentTaskDescriptionEl) currentTaskDescriptionEl.textContent = task.description || 'No request brief provided yet.';
    if (currentTaskActionsEl) currentTaskActionsEl.innerHTML = renderStatusButtons(task);
    if (meetingPanel) {
      meetingPanel.hidden = false;
    }
    if (window.wpPqConfig.canAssign) {
      await loadWorkers();
      ensureAssigneePresent(task);
      syncAssignmentPanel(task);
    }
  }

  function drawerIsOpen() {
    return !!drawerEl && drawerEl.classList.contains('is-open');
  }

  function openDrawer() {
    if (!drawerEl) return;
    drawerEl.classList.add('is-open');
    drawerEl.setAttribute('aria-hidden', 'false');
    if (drawerBackdrop) drawerBackdrop.hidden = false;
    document.body.classList.add('wp-pq-drawer-open');
  }

  function closeDrawer() {
    if (!drawerEl) return;
    drawerEl.classList.remove('is-open');
    drawerEl.setAttribute('aria-hidden', 'true');
    if (drawerBackdrop) drawerBackdrop.hidden = true;
    document.body.classList.remove('wp-pq-drawer-open');
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
    filterOptions = data.filters || filterOptions;
    syncFilterControls();
    await refreshFromCache({ reloadActivePane: !boardEl || drawerIsOpen(), refreshCalendar: currentView === 'calendar' });
  }

  async function selectTask(taskId, shouldOpenDrawer, options) {
    const config = options || {};
    const sameTask = selectedTaskId === taskId;
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
        setFilterState({
          clientUserId: parseInt(clientFilterEl.value || '0', 10) || 0,
          billingBucketId: 0,
        });
        syncFilterControls();
        selectedTaskId = null;
        await loadTasks();
      });
    }

    if (bucketFilterEl) {
      bucketFilterEl.addEventListener('change', async () => {
        setFilterState({
          clientUserId: filterState.clientUserId,
          billingBucketId: parseInt(bucketFilterEl.value || '0', 10) || 0,
        });
        syncFilterControls();
        selectedTaskId = null;
        await loadTasks();
      });
    }

    if (clearFiltersBtn) {
      clearFiltersBtn.addEventListener('click', async () => {
        setFilterState({ clientUserId: 0, billingBucketId: 0 });
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
        createClientId > 0 ? parseInt(bucket.client_user_id, 10) === createClientId : true
      ));
      if (window.wpPqConfig.canApprove && createClientId > 0 && (selectedBucketId === -1 || visibleCreateBuckets.length === 0) && !newBucketName) {
        alert('Name the first job bucket for this client before submitting the task.');
        return;
      }
      const body = {
        title: formData.get('title') || '',
        description: formData.get('description') || '',
        priority: formData.get('priority') || 'normal',
        due_at: formData.get('due_at') || null,
        requested_deadline: formData.get('requested_deadline') || null,
        needs_meeting: formData.get('needs_meeting') === 'on',
        owner_ids: parseOwnerIds(formData.get('owner_ids')),
        client_user_id: createClientId || 0,
        billing_bucket_id: selectedBucketId > 0 ? selectedBucketId : 0,
        new_bucket_name: newBucketName,
      };

      try {
        const result = await api('tasks', { method: 'POST', body: JSON.stringify(body) });
        createForm.reset();
        createFormState.clientUserId = 0;
        createFormState.billingBucketId = 0;
        syncCreateFormContext();
        if (createPanel) createPanel.hidden = true;
        if (result.task) {
          upsertTask(result.task);
          selectedTaskId = result.task.id;
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: currentView === 'calendar' });
        } else {
          selectedTaskId = result.task_id || selectedTaskId;
          await loadTasks();
        }
        if (boardEl && selectedTaskId) {
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

  async function loadInbox() {
    if (!inboxList) return;

    const data = await api('notifications', { method: 'GET' });
    const notifications = data.notifications || [];
    inboxList.innerHTML = '';

    if (inboxCount) inboxCount.textContent = String(data.unread_count || 0);

    if (!notifications.length) {
      renderEmptyStream(inboxList, 'No alerts yet.');
      return;
    }

    notifications.forEach((item) => {
      const payload = item.payload || {};
      const taskLabel = payload.task_title ? '"' + payload.task_title + '"' : (item.task_id ? 'task #' + item.task_id : 'this item');
      const li = document.createElement('li');
      li.className = (item.is_read ? 'theirs' : 'mine') + (item.is_read ? '' : ' is-unread');
      li.innerHTML =
        '<div class="msg-author">' + escapeHtml(humanizeToken(item.event_key)) + ' · ' + escapeHtml(formatDateTime(item.created_at)) + '</div>' +
        '<div><strong>' + escapeHtml(item.title) + '</strong></div>' +
        '<div>' + escapeHtml(item.body || '') + '</div>' +
        (item.task_id ? '<div class="wp-pq-inbox-task"><button type="button" class="wp-pq-linkish" data-open-task="' + item.task_id + '">Open ' + escapeHtml(taskLabel) + '</button></div>' : '');
      inboxList.appendChild(li);
    });
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

  async function uploadToMedia(file) {
    const resp = await fetch(coreRoot + 'media', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': window.wpPqConfig.nonce,
        'Content-Disposition': 'attachment; filename="' + file.name.replaceAll('"', '') + '"',
        'Content-Type': file.type || 'application/octet-stream',
      },
      body: file,
    });

    if (!resp.ok) {
      const body = await resp.json().catch(() => ({}));
      throw new Error(body.message || 'Media upload failed');
    }

    return resp.json();
  }

  async function loadFiles(options) {
    if (!fileList) return;
    if (!selectedTaskId) {
      renderEmptyStream(fileList, 'Open a task to see its files.');
      return;
    }

    ensureTaskPanelState(selectedTaskId);
    if (taskPanelState.files && !(options && options.force)) {
      return;
    }
    const data = await api('tasks/' + selectedTaskId + '/files', { method: 'GET' });
    fileList.innerHTML = '';
    taskPanelState.files = true;

    if (!(data.files || []).length) {
      renderEmptyStream(fileList, 'No files uploaded yet for this task.');
      return;
    }

    (data.files || []).forEach((file) => {
      const li = document.createElement('li');
      li.innerHTML = fileItemHtml(file);
      fileList.appendChild(li);
    });
  }

  function wireFiles() {
    if (!fileForm) return;

    fileForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectedTaskId) return alert('Select a task first.');

      const formData = new FormData(fileForm);
      const file = formData.get('file');
      const fileRole = (formData.get('file_role') || 'input').toString();

      if (!file || !(file instanceof File)) return alert('Select a file first.');

      try {
        const media = await uploadToMedia(file);
        const result = await api('tasks/' + selectedTaskId + '/files', {
          method: 'POST',
          body: JSON.stringify({ media_id: media.id, file_role: fileRole }),
        });
        fileForm.reset();
        if (result.task) {
          upsertTask(result.task);
        }
        if (result.file && fileList) {
          const emptyState = fileList.querySelector('.wp-pq-stream-empty');
          if (emptyState) {
            fileList.innerHTML = '';
          }
          const li = document.createElement('li');
          li.innerHTML = fileItemHtml(result.file);
          fileList.prepend(li);
          taskPanelState.files = true;
        } else {
          await loadFiles({ force: true });
        }
      } catch (err) {
        alert(err.message);
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

  function wireBatching() {
    if (!batchStatementBtn) return;

    batchStatementBtn.addEventListener('click', async () => {
      const taskIds = Array.from(selectedBatchTaskIds);
      if (!taskIds.length) return;

      try {
        const data = await api('statements/batch', {
          method: 'POST',
          body: JSON.stringify({ task_ids: taskIds }),
        });
        selectedBatchTaskIds.clear();
        updateBatchButton();
        alert('Statement ' + data.statement.code + ' created with ' + data.statement.task_count + ' delivered task' + (data.statement.task_count === 1 ? '' : 's') + '.');
        await loadTasks();
      } catch (err) {
        alert(err.message);
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

  function initUppy() {
    if (!uppyTarget || typeof window.Uppy === 'undefined') return;

    const UppyCtor = window.Uppy.Uppy || window.Uppy.Core;
    if (!UppyCtor) return;

    if (fileForm) {
      fileForm.style.display = 'none';
    }

    const uppy = new UppyCtor({
      autoProceed: true,
      restrictions: {
        maxNumberOfFiles: 5,
      },
    });

    const Dashboard = window.Uppy.Dashboard;
    if (Dashboard) {
      uppy.use(Dashboard, {
        inline: true,
        target: '#wp-pq-uppy',
        height: 280,
        proudlyDisplayPoweredByUppy: false,
        note: 'Upload files to selected task',
      });
    }

    uppy.addUploader(async (fileIDs) => {
      if (!selectedTaskId) {
        alert('Select a task first.');
        return;
      }

      for (const fileID of fileIDs) {
        const file = uppy.getFile(fileID);
        if (!file || !file.data) continue;

        const media = await uploadToMedia(file.data);
        await api('tasks/' + selectedTaskId + '/files', {
          method: 'POST',
          body: JSON.stringify({ media_id: media.id, file_role: 'input' }),
        });
      }

      await loadFiles();
      uppy.cancelAll();
    });
  }

  async function loadPrefs() {
    if (!prefList) return;
    const data = await api('notification-prefs', { method: 'GET' });
    const prefs = data.prefs || {};
    prefState = prefs;
    prefsLoaded = true;
    prefList.innerHTML = '';

    prefGroups.forEach((group) => {
      const enabled = group.events.some((eventKey) => !!prefs[eventKey]);
      const row = document.createElement('label');
      row.className = 'wp-pq-pref-card';
      row.innerHTML =
        '<input type="checkbox" data-pref-group="' + group.key + '" ' + (enabled ? 'checked' : '') + '>' +
        '<span><strong>' + escapeHtml(group.label) + '</strong><small>' + escapeHtml(group.description) + '</small></span>';
      prefList.appendChild(row);
    });
  }

  function wirePrefs() {
    if (!prefSaveBtn || !prefList) return;
    prefSaveBtn.addEventListener('click', async () => {
      const prefs = {};
      prefGroups.forEach((group) => {
        const checkbox = prefList ? prefList.querySelector('[data-pref-group="' + group.key + '"]') : null;
        group.events.forEach((eventKey) => {
          prefs[eventKey] = !!(checkbox && checkbox.checked);
        });
      });

      try {
        await api('notification-prefs', { method: 'POST', body: JSON.stringify({ prefs: prefs }) });
        prefState = prefs;
        alert('Preferences saved.');
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function wireInbox() {
    if (markAllReadBtn) {
      markAllReadBtn.addEventListener('click', async () => {
        try {
          await api('notifications/mark-read', { method: 'POST', body: JSON.stringify({ ids: [] }) });
          await loadInbox();
        } catch (err) {
          alert(err.message);
        }
      });
    }

    if (inboxList) {
      inboxList.addEventListener('click', async (e) => {
        const button = e.target.closest('[data-open-task]');
        if (!button) return;

        const taskId = parseInt(button.dataset.openTask, 10);
        if (!taskId) return;

        if (inboxPanel) inboxPanel.hidden = true;
        selectedTaskId = taskId;
        await loadTasks();
        await selectTask(taskId, !!drawerEl);
      });
    }
  }

  function wireStatusActions() {
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.wp-pq-status-btn');
      if (!btn) return;

      e.preventDefault();
      e.stopPropagation();

      const id = parseInt(btn.dataset.taskId, 10);
      const status = btn.dataset.status;
      if (!id || !status) return;

      const task = getTaskById(id);
      if (status === 'not_approved' && task) {
        selectedTaskId = id;
        pendingMove = null;
        pendingStatusAction = {
          taskId: id,
          status: status,
        };
        openMoveModal();
        return;
      }
      if (status === 'revision_requested' && task) {
        selectedTaskId = id;
        openRevisionModal({ type: 'status', taskId: id, status: status });
        return;
      }

      try {
        selectedTaskId = id;
        const result = await api('tasks/' + id + '/status', { method: 'POST', body: JSON.stringify({ status: status }) });
        if (result.task) {
          upsertTask(result.task);
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: currentView === 'calendar' });
        } else {
          await loadTasks();
        }
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function wireDrawerControls() {
    if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeDrawer);
    if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && revisionModal && !revisionModal.hidden) {
        closeRevisionModal(true).catch(console.error);
        return;
      }
      if (e.key === 'Escape' && moveModal && !moveModal.hidden) {
        closeMoveModal(true).catch(console.error);
        return;
      }
      if (e.key === 'Escape' && drawerIsOpen()) {
        closeDrawer();
      }
    });
  }

  function wireMoveModal() {
    if (closeMoveModalBtn) {
      closeMoveModalBtn.addEventListener('click', () => {
        closeMoveModal(true).catch(console.error);
      });
    }

    if (cancelMoveBtn) {
      cancelMoveBtn.addEventListener('click', () => {
        closeMoveModal(true).catch(console.error);
      });
    }

    if (moveModalBackdrop) {
      moveModalBackdrop.addEventListener('click', () => {
        closeMoveModal(true).catch(console.error);
      });
    }

    if (!moveForm) return;

    moveForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!pendingMove && !pendingStatusAction) return;

      const formData = new FormData(moveForm);
      const priorityDirection = (formData.get('priority_direction') || 'keep').toString();
      const swapDueDates = formData.get('swap_due_dates') === '1';
      const requestMeeting = formData.get('request_meeting') === '1';

      try {
        selectedTaskId = pendingMove ? pendingMove.taskId : pendingStatusAction.taskId;
        const preserveBoardOrder = !!pendingMove;
        let result;
        if (pendingMove) {
          result = await api('tasks/move', {
            method: 'POST',
            body: JSON.stringify({
              task_id: pendingMove.taskId,
              target_task_id: pendingMove.targetTaskId || 0,
              position: pendingMove.position,
              target_status: pendingMove.targetStatus,
              priority_direction: priorityDirection,
              swap_due_dates: swapDueDates,
              needs_meeting: requestMeeting,
            }),
          });
        } else {
          result = await api('tasks/' + pendingStatusAction.taskId + '/status', {
            method: 'POST',
            body: JSON.stringify({
              status: pendingStatusAction.status,
              needs_meeting: requestMeeting,
            }),
          });
        }
        await closeMoveModal(false);
        if (result && result.task) {
          upsertTask(result.task);
          if (result.target_task) {
            upsertTask(result.target_task);
          }
          if (preserveBoardOrder) {
            syncOrderFromBoardDom();
          }
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: currentView === 'calendar' });
        } else {
          await loadTasks();
        }
        if (requestMeeting && selectedTaskId) {
          await selectTask(selectedTaskId, true, { preservePanelState: true, loadParticipants: false, loadWorkspace: false });
          await activateWorkspaceTab('meetings');
          openDrawer();
          if (meetingStartInput) {
            meetingStartInput.focus();
          }
        }
      } catch (err) {
        alert(err.message);
        await closeMoveModal(true);
      }
    });
  }

  function wireRevisionModal() {
    if (closeRevisionModalBtn) {
      closeRevisionModalBtn.addEventListener('click', () => {
        closeRevisionModal(true).catch(console.error);
      });
    }

    if (cancelRevisionBtn) {
      cancelRevisionBtn.addEventListener('click', () => {
        closeRevisionModal(true).catch(console.error);
      });
    }

    if (revisionModalBackdrop) {
      revisionModalBackdrop.addEventListener('click', () => {
        closeRevisionModal(true).catch(console.error);
      });
    }

    if (!revisionForm) return;

    revisionForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!pendingRevisionAction) return;

      const formData = new FormData(revisionForm);
      const note = (formData.get('revision_note') || '').toString().trim();
      const postMessage = formData.get('post_message') === '1';
      if (!note) return alert('Please describe what needs to change.');

      try {
        selectedTaskId = pendingRevisionAction.taskId;
        const preserveBoardOrder = pendingRevisionAction.type === 'move';
        let result;
        if (pendingRevisionAction.type === 'move') {
          result = await api('tasks/move', {
            method: 'POST',
            body: JSON.stringify({
              task_id: pendingRevisionAction.taskId,
              target_task_id: pendingRevisionAction.targetTaskId || 0,
              position: pendingRevisionAction.position || 'after',
              target_status: 'revision_requested',
              priority_direction: 'keep',
              swap_due_dates: false,
              note: note,
              message_body: postMessage ? note : '',
            }),
          });
        } else {
          result = await api('tasks/' + pendingRevisionAction.taskId + '/status', {
            method: 'POST',
            body: JSON.stringify({
              status: 'revision_requested',
              note: note,
              message_body: postMessage ? note : '',
            }),
          });
        }
        await closeRevisionModal(false);
        if (result && result.task) {
          upsertTask(result.task);
          if (result.target_task) {
            upsertTask(result.target_task);
          }
          if (preserveBoardOrder) {
            syncOrderFromBoardDom();
          }
          await refreshFromCache({ reloadActivePane: false, refreshCalendar: currentView === 'calendar' });
        } else {
          await loadTasks();
        }
        await selectTask(selectedTaskId, true, {
          preservePanelState: !postMessage,
          loadParticipants: false,
          loadWorkspace: false,
        });
        await activateWorkspaceTab(postMessage ? 'messages' : 'meetings');
      } catch (err) {
        alert(err.message);
        await closeRevisionModal(true);
      }
    });
  }

  wireCreateForm();
  wireBoardFilters();
  wireSort();
  wireMessages();
  wireNotes();
  wireFiles();
  wireMeetings();
  wireAssignment();
  wireBatching();
  wirePrefs();
  wireInbox();
  wireStatusActions();
  wireDrawerControls();
  wireMoveModal();
  wireRevisionModal();
  wireTogglePanel(openCreateBtn, createPanel, closeCreateBtn);
  wireTogglePanel(openInboxBtn, inboxPanel, closeInboxBtn);
  if (openInboxBtn) {
    openInboxBtn.addEventListener('click', () => {
      loadInbox().catch(console.error);
    });
  }
  if (openPrefsBtn && prefPanel) {
    openPrefsBtn.addEventListener('click', () => {
      prefPanel.hidden = !prefPanel.hidden;
      if (!prefPanel.hidden && !prefsLoaded) {
        loadPrefs().catch(console.error);
      }
    });
  }
  if (closePrefsBtn && prefPanel) {
    closePrefsBtn.addEventListener('click', () => {
      prefPanel.hidden = true;
    });
  }
  if (prefList && prefSaveBtn && !openPrefsBtn) {
    loadPrefs().catch(console.error);
  }
  initViewToggle();
  initWorkspaceTabs();
  initCalendar();
  initUppy();
  setActiveView(true);
  resetTaskSummary();

  loadTasks().catch(console.error);
  loadInbox().catch(console.error);
})();
