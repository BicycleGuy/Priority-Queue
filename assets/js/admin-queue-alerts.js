(function () {
  var bridge = window.wpPqPortalUI;
  if (!bridge || !bridge.api) return;

  var alertDismissPrefKey = 'alert_auto_dismiss';

  var prefGroups = [
    {
      key: 'review',
      label: 'Reviews and approvals',
      description: 'New requests, approvals, and clarification requests',
      events: ['task_created', 'task_assigned', 'task_approved', 'task_clarification_requested'],
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
      description: 'Deliveries, revisions, and invoice draft creation',
      events: ['task_returned_to_work', 'task_delivered', 'statement_batched'],
    },
    {
      key: 'retention',
      label: 'Retention reminders',
      description: 'Day-300 storage reminder',
      events: ['retention_day_300'],
    },
    {
      key: 'client_updates',
      label: 'Client updates',
      description: 'Immediate status updates and the daily digest',
      events: ['client_status_updates', 'client_daily_digest'],
    },
  ];

  // DOM elements — own lookups
  var alertStackEl = document.getElementById('wp-pq-alert-stack');
  var openPrefsBtn = document.getElementById('wp-pq-open-prefs');
  var closePrefsBtn = document.getElementById('wp-pq-close-prefs');
  var prefPanel = document.getElementById('wp-pq-pref-panel');
  var prefList = document.getElementById('wp-pq-pref-list');
  var prefSaveBtn = document.getElementById('wp-pq-save-prefs');

  // State
  var prefsLoaded = false;
  var prefState = {};
  var notificationsCache = [];
  var notificationDismissTimers = new Map();

  function activePrefGroups() {
    if (!window.wpPqConfig.canViewAll && prefPanel) {
      return prefGroups.filter(function (group) { return group.key === 'client_updates'; });
    }
    return prefGroups;
  }

  async function loadPrefs() {
    if (!prefList) return;
    var data = await bridge.api('notification-prefs', { method: 'GET' });
    var prefs = data.prefs || {};
    prefState = prefs;
    prefsLoaded = true;
    prefList.innerHTML = '';

    activePrefGroups().forEach(function (group) {
      var enabled = group.events.some(function (eventKey) { return !!prefs[eventKey]; });
      var row = document.createElement('label');
      row.className = 'wp-pq-pref-card';
      row.innerHTML =
        '<input type="checkbox" data-pref-group="' + group.key + '" ' + (enabled ? 'checked' : '') + '>' +
        '<span><strong>' + bridge.escapeHtml(group.label) + '</strong><small>' + bridge.escapeHtml(group.description) + '</small></span>';
      prefList.appendChild(row);
    });

    var alertRow = document.createElement('label');
    alertRow.className = 'wp-pq-pref-card';
    alertRow.innerHTML =
      '<input type="checkbox" data-pref-key="' + alertDismissPrefKey + '" ' + (prefs[alertDismissPrefKey] ? 'checked' : '') + '>' +
      '<span><strong>Auto-dismiss alerts</strong><small>When enabled, on-screen alerts fade away after a few seconds. Turn it off if you want to dismiss them manually.</small></span>';
    prefList.appendChild(alertRow);
  }

  function shouldAutoDismissAlerts() {
    return !!prefState[alertDismissPrefKey];
  }

  function portalAlertStack() {
    if (alertStackEl) return alertStackEl;
    var stack = document.getElementById('wp-pq-alert-stack');
    if (stack) return stack;
    stack = document.createElement('div');
    stack.id = 'wp-pq-alert-stack';
    stack.className = 'wp-pq-alert-stack';
    stack.setAttribute('aria-live', 'polite');
    document.body.appendChild(stack);
    return stack;
  }

  function clearDismissTimer(notificationId) {
    var timer = notificationDismissTimers.get(notificationId);
    if (timer) {
      window.clearTimeout(timer);
      notificationDismissTimers.delete(notificationId);
    }
  }

  async function dismissNotifications(ids) {
    await bridge.api('notifications/mark-read', { method: 'POST', body: JSON.stringify({ ids: ids || [] }) });
    (ids || []).forEach(function (id) { clearDismissTimer(Number(id || 0)); });
    await loadInbox();
  }

  function scheduleAlertDismiss(item) {
    var notificationId = parseInt(item && item.id, 10);
    if (!notificationId || !shouldAutoDismissAlerts()) return;
    if (notificationDismissTimers.has(notificationId)) return;
    var timer = window.setTimeout(async function () {
      try {
        await dismissNotifications([notificationId]);
      } catch (err) {
        console.error(err);
      }
    }, 2600);
    notificationDismissTimers.set(notificationId, timer);
  }

  function renderPersistentAlerts(notifications) {
    var stack = portalAlertStack();
    var activeIds = new Set((notifications || []).map(function (item) { return parseInt(item.id, 10); }).filter(Boolean));
    Array.from(notificationDismissTimers.keys()).forEach(function (notificationId) {
      if (!activeIds.has(notificationId)) {
        clearDismissTimer(notificationId);
      }
    });

    stack.innerHTML = '';
    if (!notifications.length) {
      stack.hidden = true;
      return;
    }

    stack.hidden = false;
    notifications.forEach(function (item) {
      var card = document.createElement('article');
      card.className = 'wp-pq-alert-card';
      card.dataset.notificationId = String(item.id || '');
      card.innerHTML =
        '<div class="wp-pq-alert-copy">'
          + '<strong>' + bridge.escapeHtml(item.title || bridge.humanizeToken(item.event_key)) + '</strong>'
          + '<small>' + bridge.escapeHtml(bridge.formatDateTime(item.created_at)) + '</small>'
          + '<p>' + bridge.escapeHtml(item.body || '') + '</p>'
        + '</div>'
        + '<div class="wp-pq-alert-actions">'
          + (item.task_id ? '<button type="button" class="button wp-pq-secondary-action" data-open-alert-task="' + bridge.escapeHtml(item.task_id) + '" data-notification-id="' + bridge.escapeHtml(item.id) + '">Open Task</button>' : '')
          + '<button type="button" class="button" data-dismiss-alert="' + bridge.escapeHtml(item.id) + '">Dismiss</button>'
        + '</div>';
      stack.appendChild(card);
      scheduleAlertDismiss(item);
    });
  }

  async function refreshPreferencesPanel() {
    await loadPrefs();
  }

  async function openPreferencesPanel() {
    if (prefPanel) {
      prefPanel.hidden = false;
    }
    await refreshPreferencesPanel();
  }

  function closePreferencesPanel() {
    if (prefPanel) {
      prefPanel.hidden = true;
    }
  }

  var inboxLoading = false;
  async function loadInbox() {
    if (inboxLoading) return;
    inboxLoading = true;
    try {
      var data = await bridge.api('notifications', { method: 'GET' });
      var notifications = data.notifications || [];
      notificationsCache = notifications;
      renderPersistentAlerts(notifications);
      return data;
    } finally {
      inboxLoading = false;
    }
  }

  async function openTaskFromAlert(notification) {
    var taskId = parseInt(notification && notification.task_id, 10);
    var notificationId = parseInt(notification && notification.id, 10);
    if (!taskId) return;

    if (window.wpPqPortalManager && typeof window.wpPqPortalManager.openSection === 'function') {
      await window.wpPqPortalManager.openSection('queue', { pushHistory: true });
    }

    bridge.setSelectedTaskId(taskId);
    if (!bridge.getTaskById(taskId)) {
      await bridge.loadTasks();
    }
    var drawerEl = document.getElementById('wp-pq-task-drawer');
    await bridge.selectTask(taskId, !!drawerEl);

    if (notificationId > 0) {
      try {
        await dismissNotifications([notificationId]);
      } catch (err) {
        bridge.alert(err.message);
      }
    }
  }

  // Wire preferences save
  function wirePrefs() {
    if (!prefSaveBtn || !prefList) return;
    prefSaveBtn.addEventListener('click', async function () {
      var prefs = {};
      activePrefGroups().forEach(function (group) {
        var checkbox = prefList ? prefList.querySelector('[data-pref-group="' + group.key + '"]') : null;
        group.events.forEach(function (eventKey) {
          prefs[eventKey] = !!(checkbox && checkbox.checked);
        });
      });
      var alertAutoDismiss = prefList ? prefList.querySelector('[data-pref-key="' + alertDismissPrefKey + '"]') : null;
      prefs[alertDismissPrefKey] = !!(alertAutoDismiss && alertAutoDismiss.checked);

      try {
        await bridge.api('notification-prefs', { method: 'POST', body: JSON.stringify({ prefs: prefs }) });
        prefState = prefs;
        renderPersistentAlerts(notificationsCache);
        bridge.alert('Preferences saved.', 'success');
      } catch (err) {
        bridge.alert(err.message);
      }
    });
  }

  // Wire inbox click handlers
  function wireInbox() {
    var stack = portalAlertStack();
    stack.addEventListener('click', async function (e) {
      var dismissBtn = e.target.closest('[data-dismiss-alert]');
      if (dismissBtn) {
        e.preventDefault();
        var alertCard = dismissBtn.closest('.wp-pq-alert-card');
        if (alertCard) alertCard.remove();
        var remainingCards = stack.querySelectorAll('.wp-pq-alert-card');
        if (!remainingCards.length) stack.hidden = true;
        try {
          await dismissNotifications([parseInt(dismissBtn.dataset.dismissAlert || '0', 10)]);
        } catch (err) {
          // silently fail — card already removed from DOM
        }
        return;
      }

      var openBtn = e.target.closest('[data-open-alert-task]');
      if (openBtn) {
        e.preventDefault();
        var alertCard = openBtn.closest('.wp-pq-alert-card');
        if (alertCard) alertCard.remove();
        try {
          await openTaskFromAlert({
            id: parseInt(openBtn.dataset.notificationId || '0', 10),
            task_id: parseInt(openBtn.dataset.openAlertTask || '0', 10),
          });
        } catch (err) {
          bridge.alert(err.message);
        }
      }
    });
  }

  // Wire and initialize
  wirePrefs();
  wireInbox();
  if (openPrefsBtn && prefPanel) {
    openPrefsBtn.addEventListener('click', function (event) {
      event.preventDefault();
      openPreferencesPanel().catch(function (err) { bridge.alert(err.message); });
    });
  }
  if (closePrefsBtn && prefPanel) {
    closePrefsBtn.addEventListener('click', function () {
      closePreferencesPanel();
    });
  }
  if (prefList && prefSaveBtn && !openPrefsBtn) {
    refreshPreferencesPanel().catch(console.error);
  }

  // Boot: load prefs then inbox, with polling
  loadPrefs()
    .catch(console.error)
    .finally(function () { loadInbox().catch(console.error); });
  window.setInterval(function () {
    loadInbox().catch(console.error);
  }, 30000);

  // Public API
  window.wpPqAlerts = {
    loadPrefs: loadPrefs,
    loadInbox: loadInbox,
    dismissNotifications: dismissNotifications,
    openTaskFromAlert: openTaskFromAlert,
    openPreferences: openPreferencesPanel,
    closePreferences: closePreferencesPanel,
    refreshPreferences: refreshPreferencesPanel,
  };
})();
