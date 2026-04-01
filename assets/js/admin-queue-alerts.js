(function () {
  const bridge = window.wpPqPortalUI;
  if (!bridge || !bridge.api) return;

  const alertDismissPrefKey = 'alert_auto_dismiss';

  const prefGroups = [
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
  const alertStackEl = document.getElementById('wp-pq-alert-stack');
  const openPrefsBtn = document.getElementById('wp-pq-open-prefs');
  const closePrefsBtn = document.getElementById('wp-pq-close-prefs');
  const prefPanel = document.getElementById('wp-pq-pref-panel');
  const prefList = document.getElementById('wp-pq-pref-list');
  const prefSaveBtn = document.getElementById('wp-pq-save-prefs');

  // State
  let prefState = {};
  let notificationsCache = [];
  const notificationDismissTimers = new Map();

  function activePrefGroups() {
    if (!window.wpPqConfig.canViewAll && prefPanel) {
      return prefGroups.filter(function (group) { return group.key === 'client_updates'; });
    }
    return prefGroups;
  }

  async function loadPrefs() {
    if (!prefList) return;
    const data = await bridge.api('notification-prefs', { method: 'GET' });
    const prefs = data.prefs || {};
    prefState = prefs;
    prefList.innerHTML = '';

    activePrefGroups().forEach(function (group) {
      const enabled = group.events.some(function (eventKey) { return !!prefs[eventKey]; });
      const row = document.createElement('label');
      row.className = 'wp-pq-pref-card';
      row.innerHTML =
        '<input type="checkbox" data-pref-group="' + group.key + '" ' + (enabled ? 'checked' : '') + '>' +
        '<span><strong>' + bridge.escapeHtml(group.label) + '</strong><small>' + bridge.escapeHtml(group.description) + '</small></span>';
      prefList.appendChild(row);
    });

    const alertRow = document.createElement('label');
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
    const stack = document.getElementById('wp-pq-alert-stack');
    if (stack) return stack;
    stack = document.createElement('div');
    stack.id = 'wp-pq-alert-stack';
    stack.className = 'wp-pq-alert-stack';
    stack.setAttribute('aria-live', 'polite');
    document.body.appendChild(stack);
    return stack;
  }

  function clearDismissTimer(notificationId) {
    const timer = notificationDismissTimers.get(notificationId);
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
    if (!shouldAutoDismissAlerts()) return;
    const notificationId = parseInt(item && item.id, 10);
    if (!notificationId) return;
    if (notificationDismissTimers.has(notificationId)) return;
    const timer = window.setTimeout(function () {
      // Remove the card from the DOM immediately so the user sees it vanish.
      const card = document.querySelector('.wp-pq-alert-card[data-notification-id="' + notificationId + '"]');
      if (card) card.remove();
      const stack = portalAlertStack();
      const remaining = stack.querySelectorAll('.wp-pq-alert-card');
      if (!remaining.length) stack.hidden = true;
      notificationDismissTimers.delete(notificationId);
      // Fire the API dismiss in the background — no need to await.
      dismissNotifications([notificationId]).catch(function (err) { console.error(err); });
    }, 4000);
    notificationDismissTimers.set(notificationId, timer);
  }

  function renderPersistentAlerts(notifications) {
    const stack = portalAlertStack();
    const activeIds = new Set((notifications || []).map(function (item) { return parseInt(item.id, 10); }).filter(Boolean));
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
      const card = document.createElement('article');
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
    await refreshGcalStatus();
  }

  async function refreshGcalStatus() {
    var container = document.getElementById('wp-pq-gcal-status');
    if (!container) return;

    try {
      var data = await bridge.api('google/oauth/status', { method: 'GET' });
      var connected = !!(data && data.connected);
      var email = (data && data.connected_email) || '';
      var usingRelay = !!(data && data.using_relay);

      if (connected) {
        container.innerHTML =
          '<span class="wp-pq-gcal-badge wp-pq-gcal-connected">&#x2713; Connected' +
          (email ? ' &mdash; ' + bridge.escapeHtml(email) : '') + '</span>' +
          '<button class="button wp-pq-gcal-disconnect" type="button" id="wp-pq-gcal-disconnect">Disconnect</button>';
        var disconnectBtn = document.getElementById('wp-pq-gcal-disconnect');
        if (disconnectBtn) {
          disconnectBtn.addEventListener('click', async function () {
            if (!confirm('Disconnect Google? Calendar events, Meet links, and email notifications will stop working.')) return;
            try {
              await bridge.api('google/oauth/disconnect', { method: 'POST' });
              bridge.alert('Google disconnected.');
              await refreshGcalStatus();
            } catch (err) {
              bridge.alert(err.message || 'Failed to disconnect.', true);
            }
          });
        }
      } else {
        var connectLabel = usingRelay ? 'Connect Google' : 'Connect Google (requires setup)';
        var endpoint = usingRelay ? 'google/oauth/relay-initiate' : 'google/oauth/url';
        container.innerHTML =
          '<span class="wp-pq-gcal-badge wp-pq-gcal-disconnected">Not connected</span>' +
          '<button class="button button-primary wp-pq-gcal-connect" type="button" id="wp-pq-gcal-connect">' + connectLabel + '</button>';
        var connectBtn = document.getElementById('wp-pq-gcal-connect');
        if (connectBtn) {
          connectBtn.addEventListener('click', async function () {
            try {
              var urlData = await bridge.api(endpoint, { method: 'GET' });
              if (urlData && urlData.url) {
                window.location.href = urlData.url;
              } else {
                bridge.alert('Could not generate OAuth URL.', true);
              }
            } catch (err) {
              bridge.alert(err.message || 'Failed to start Google connection.', true);
            }
          });
        }
      }
    } catch (err) {
      container.innerHTML = '<span class="wp-pq-gcal-badge wp-pq-gcal-error">Unable to check status</span>';
    }
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

  let inboxLoading = false;
  async function loadInbox() {
    if (inboxLoading) return;
    inboxLoading = true;
    try {
      const data = await bridge.api('notifications', { method: 'GET' });
      const notifications = data.notifications || [];
      notificationsCache = notifications;
      renderPersistentAlerts(notifications);
      return data;
    } finally {
      inboxLoading = false;
    }
  }

  async function openTaskFromAlert(notification) {
    const taskId = parseInt(notification && notification.task_id, 10);
    const notificationId = parseInt(notification && notification.id, 10);
    if (!taskId) return;

    // 1. Switch to the queue section so the board & drawer are visible.
    if (window.wpPqPortalManager && typeof window.wpPqPortalManager.openSection === 'function') {
      await window.wpPqPortalManager.openSection('queue', { pushHistory: true });
    }

    // 2. If the task isn't already in the local cache (e.g. current filters
    //    exclude it), reload tasks. We set selectedTaskId *after* loadTasks
    //    returns so that loadTasks' "close drawer if selected task missing"
    //    guard doesn't wipe it out.
    if (!bridge.getTaskById(taskId)) {
      await bridge.loadTasks();
    }

    // 3. If still missing after the filtered load, fetch the full unfiltered
    //    task list and upsert so selectTask can find it.
    if (!bridge.getTaskById(taskId)) {
      try {
        const data = await bridge.api('tasks', { method: 'GET' });
        const tasks = data && data.tasks ? data.tasks : [];
        const match = tasks.find(function (t) { return t.id === taskId; });
        if (match) bridge.upsertTask(match);
      } catch (ignore) { /* best-effort */ }
    }

    // 4. Select the task and always open the drawer.
    bridge.setSelectedTaskId(taskId);
    await bridge.selectTask(taskId, true, { forceWorkspace: true, forceParticipants: true });

    // 5. If selectTask bailed (task filtered out / missing), force-fetch
    //    the full unfiltered list, upsert the match, and retry so the
    //    drawer doesn't show the empty placeholder.
    if (!bridge.getTaskById(taskId)) {
      try {
        var data = await bridge.api('tasks', { method: 'GET' });
        var tasks = data && data.tasks ? data.tasks : [];
        var match = tasks.find(function (t) { return t.id === taskId; });
        if (match) {
          bridge.upsertTask(match);
          bridge.setSelectedTaskId(taskId);
          await bridge.selectTask(taskId, true, { forceWorkspace: true, forceParticipants: true });
        }
      } catch (ignore) { /* best-effort */ }
    }

    // 6. Ensure drawer is visually open even if selectTask didn't open it.
    if (typeof bridge.openDrawer === 'function') bridge.openDrawer();

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
      const prefs = {};
      activePrefGroups().forEach(function (group) {
        const checkbox = prefList ? prefList.querySelector('[data-pref-group="' + group.key + '"]') : null;
        group.events.forEach(function (eventKey) {
          prefs[eventKey] = !!(checkbox && checkbox.checked);
        });
      });
      const alertAutoDismiss = prefList ? prefList.querySelector('[data-pref-key="' + alertDismissPrefKey + '"]') : null;
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
    const stack = portalAlertStack();
    stack.addEventListener('click', async function (e) {
      const dismissBtn = e.target.closest('[data-dismiss-alert]');
      if (dismissBtn) {
        e.preventDefault();
        const alertCard = dismissBtn.closest('.wp-pq-alert-card');
        if (alertCard) alertCard.remove();
        const remainingCards = stack.querySelectorAll('.wp-pq-alert-card');
        if (!remainingCards.length) stack.hidden = true;
        try {
          await dismissNotifications([parseInt(dismissBtn.dataset.dismissAlert || '0', 10)]);
        } catch (err) {
          // silently fail — card already removed from DOM
        }
        return;
      }

      const openBtn = e.target.closest('[data-open-alert-task]');
      if (openBtn) {
        e.preventDefault();
        const alertCard = openBtn.closest('.wp-pq-alert-card');
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

  // Handle ?gcal_connected=1 redirect from OAuth relay
  (function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('gcal_connected') === '1') {
      // Clean the URL
      params.delete('gcal_connected');
      var clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
      window.history.replaceState(null, '', clean);
      // Show success and open preferences
      setTimeout(function () {
        bridge.alert('Google connected — Calendar, Meet, and Gmail are active.', 'success');
        // Hide onboarding overlay if visible.
        var overlay = document.getElementById('wp-pq-onboarding-overlay');
        if (overlay) overlay.hidden = true;
        if (typeof openPreferencesPanel === 'function') openPreferencesPanel().catch(console.error);
      }, 300);
    }
  })();

  // Onboarding interstitial — show if Google not connected.
  (function () {
    var overlay = document.getElementById('wp-pq-onboarding-overlay');
    if (!overlay) return;
    if (window.wpPqConfig && window.wpPqConfig.googleConnected) return;
    overlay.hidden = false;
    var connectBtn = document.getElementById('wp-pq-onboarding-connect');
    if (connectBtn) {
      connectBtn.addEventListener('click', async function () {
        try {
          var data = await bridge.api('google/oauth/relay-initiate', { method: 'GET' });
          if (data && data.url) {
            window.location.href = data.url;
          } else {
            bridge.alert('Could not start Google connection.', true);
          }
        } catch (err) {
          bridge.alert(err.message || 'Failed to start Google connection.', true);
        }
      });
    }
  })();

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
