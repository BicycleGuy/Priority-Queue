(function () {
  const bridge = window.wpPqPortalUI;
  if (!bridge || !bridge.api) return;

  // --- Modal DOM elements ---
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
  const moveEmailOption = document.getElementById('wp-pq-move-email-option');
  const completionModalBackdrop = document.getElementById('wp-pq-completion-modal-backdrop');
  const completionModal = document.getElementById('wp-pq-completion-modal');
  const completionForm = document.getElementById('wp-pq-completion-form');
  const completionSummaryEl = document.getElementById('wp-pq-completion-summary');
  const completionModeNoteEl = document.getElementById('wp-pq-completion-mode-note');
  const completionBillingModeEl = document.getElementById('wp-pq-completion-billing-mode');
  const completionBillingCategoryEl = document.getElementById('wp-pq-completion-billing-category');
  const completionWorkSummaryEl = document.getElementById('wp-pq-completion-work-summary');
  const completionHoursEl = document.getElementById('wp-pq-completion-hours');
  const completionRateEl = document.getElementById('wp-pq-completion-rate');
  const completionAmountEl = document.getElementById('wp-pq-completion-amount');
  const completionExpenseReferenceEl = document.getElementById('wp-pq-completion-expense-reference');
  const completionNonBillableReasonEl = document.getElementById('wp-pq-completion-non-billable-reason');
  const closeCompletionModalBtn = document.getElementById('wp-pq-close-completion-modal');
  const cancelCompletionBtn = document.getElementById('wp-pq-cancel-completion');
  const deleteModalBackdrop = document.getElementById('wp-pq-delete-modal-backdrop');
  const deleteModal = document.getElementById('wp-pq-delete-modal');
  const deleteSummaryEl = document.getElementById('wp-pq-delete-summary');
  const closeDeleteModalBtn = document.getElementById('wp-pq-close-delete-modal');
  const cancelDeleteBtn = document.getElementById('wp-pq-cancel-delete');
  const confirmDeleteBtn = document.getElementById('wp-pq-confirm-delete');

  // --- Modal state variables ---
  let pendingMove = null;
  let pendingStatusAction = null;
  let pendingRevisionAction = null;
  let pendingDeleteTaskId = 0;
  let pendingCompletionTaskId = 0;

  // --- Revision modal ---

  function openRevisionModal(action) {
    if (!revisionModal || !revisionModalBackdrop || !revisionForm || !action) return;
    pendingRevisionAction = action;
    const task = bridge.getTaskById(action.taskId);
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
      await bridge.loadTasks();
    }
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
      if (!note) return bridge.alert('Please describe what needs to change.');

      try {
        bridge.setSelectedTaskId(pendingRevisionAction.taskId);
        const preserveBoardOrder = pendingRevisionAction.type === 'move';
        let result;
        if (pendingRevisionAction.type === 'move') {
          result = await bridge.api('tasks/move', {
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
          result = await bridge.api('tasks/' + pendingRevisionAction.taskId + '/status', {
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
          bridge.upsertTask(result.task);
          if (result.target_task) {
            bridge.upsertTask(result.target_task);
          }
          if (preserveBoardOrder) {
            bridge.syncOrderFromBoardDom();
          }
          await bridge.refreshFromCache({ reloadActivePane: false, refreshCalendar: bridge.currentView() === 'calendar' });
        } else {
          await bridge.loadTasks();
        }
        await bridge.selectTask(bridge.getSelectedTaskId(), true, {
          preservePanelState: !postMessage,
          loadParticipants: false,
          loadWorkspace: false,
        });
        await bridge.activateWorkspaceTab(postMessage ? 'messages' : 'meetings');
      } catch (err) {
        bridge.alert(err.message);
        await closeRevisionModal(true);
      }
    });
  }

  // --- Move modal ---

  function shouldPromptForMoveDecision(sourceStatus, targetStatus) {
    const effectiveStatus = targetStatus || sourceStatus;
    return ['pending_approval', 'needs_clarification', 'approved', 'in_progress', 'needs_review', 'delivered'].includes(effectiveStatus);
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
      needs_review: {
        title: 'Send to Review?',
        body: 'This marks the task as ready for review. Responsibility may shift from execution to reviewer follow-up.',
        cta: 'Send to Review',
      },
      needs_clarification: {
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
        title: move.sourceStatus === 'in_progress' ? 'Deliver Without Review?' : 'Mark as Delivered?',
        body: move.sourceStatus === 'in_progress'
          ? 'You are about to mark this task delivered. Proceed without third-party review?'
          : 'This records the task as delivered to the requester or client. It stays reversible until you explicitly mark it done.',
        cta: 'Mark Delivered',
      },
    };

    return configs[move.targetStatus] || {
      title: 'Move task?',
      body: 'This changes the task status in the workflow.',
      cta: 'Move Task',
    };
  }

  function openMoveModal() {
    if (!moveModal || !moveModalBackdrop || (!pendingMove && !pendingStatusAction)) return;

    const action = pendingMove || pendingStatusAction;
    const movedTask = bridge.getTaskById(action.taskId);
    const targetTask = pendingMove ? bridge.getTaskById(pendingMove.targetTaskId) : null;
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
      moveSummaryEl.textContent = '"' + movedTask.title + '" moved into ' + bridge.humanizeToken(targetStatus) + '. ' + config.body;
    } else if (moveSummaryEl) {
      moveSummaryEl.textContent = config.body;
    }

    const keepPriority = moveForm ? moveForm.querySelector('input[name="priority_direction"][value="keep"]') : null;
    const swapDueDates = moveForm ? moveForm.querySelector('input[name="swap_due_dates"]') : null;
    const requestMeeting = moveForm ? moveForm.querySelector('input[name="request_meeting"]') : null;
    const sendUpdateEmail = moveForm ? moveForm.querySelector('input[name="send_update_email"]') : null;
    if (keepPriority) keepPriority.checked = true;
    if (swapDueDates) swapDueDates.checked = false;
    if (swapDueDates) swapDueDates.disabled = !pendingMove || !pendingMove.targetTaskId;
    if (requestMeeting) requestMeeting.checked = false;
    if (sendUpdateEmail) sendUpdateEmail.checked = !!window.wpPqConfig.canViewAll;
    if (moveMeetingOption) {
      moveMeetingOption.hidden = targetStatus !== 'needs_clarification';
    }
    if (moveEmailOption) {
      moveEmailOption.hidden = !window.wpPqConfig.canViewAll || !movedTask || !parseInt(movedTask.client_id || 0, 10);
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
      await bridge.loadTasks();
    }
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
      const sendUpdateEmail = formData.get('send_update_email') === '1';

      try {
        bridge.setSelectedTaskId(pendingMove ? pendingMove.taskId : pendingStatusAction.taskId);
        const preserveBoardOrder = !!pendingMove;
        let result;
        if (pendingMove) {
          result = await bridge.api('tasks/move', {
            method: 'POST',
            body: JSON.stringify({
              task_id: pendingMove.taskId,
              target_task_id: pendingMove.targetTaskId || 0,
              position: pendingMove.position,
              target_status: pendingMove.targetStatus,
              priority_direction: priorityDirection,
              swap_due_dates: swapDueDates,
              needs_meeting: requestMeeting,
              send_update_email: sendUpdateEmail,
            }),
          });
        } else {
          result = await bridge.api('tasks/' + pendingStatusAction.taskId + '/status', {
            method: 'POST',
            body: JSON.stringify({
              status: pendingStatusAction.status,
              needs_meeting: requestMeeting,
              send_update_email: sendUpdateEmail,
            }),
          });
        }
        await closeMoveModal(false);
        if (result && result.task) {
          bridge.upsertTask(result.task);
          if (result.target_task) {
            bridge.upsertTask(result.target_task);
          }
          if (preserveBoardOrder) {
            bridge.syncOrderFromBoardDom();
          }
          await bridge.refreshFromCache({ reloadActivePane: false, refreshCalendar: bridge.currentView() === 'calendar' });
        } else {
          await bridge.loadTasks();
        }
        if (requestMeeting && bridge.getSelectedTaskId()) {
          await bridge.selectTask(bridge.getSelectedTaskId(), true, { preservePanelState: true, loadParticipants: false, loadWorkspace: false });
          await bridge.activateWorkspaceTab('meetings');
          bridge.openDrawer();
          bridge.focusMeetingStart();
        }
      } catch (err) {
        bridge.alert(err.message);
        await closeMoveModal(true);
      }
    });
  }

  // --- Completion modal ---

  function completionModeConfig(mode) {
    const configs = {
      hourly: {
        note: 'Hourly work needs a work summary, billing category, and hours. Rate and amount stay optional.',
        show: ['hours', 'rate'],
        requireCategory: true,
        requireHours: true,
      },
      fixed_fee: {
        note: 'Fixed-fee work needs a short summary and billing category. Amount can stay optional until invoicing.',
        show: ['rate', 'amount'],
        requireCategory: true,
        requireHours: false,
      },
      pass_through_expense: {
        note: 'Pass-through expenses need a work summary, billing category, and either an amount or an expense reference.',
        show: ['amount', 'expense_reference'],
        requireCategory: true,
        requireHours: false,
      },
      non_billable: {
        note: 'Non-billable work still needs a summary for the ledger, but it will stay out of invoice prep.',
        show: ['non_billable_reason'],
        requireCategory: false,
        requireHours: false,
      },
    };

    return configs[mode] || configs.fixed_fee;
  }

  function syncCompletionForm(mode) {
    if (!completionForm) return;

    const normalizedMode = String(mode || (completionBillingModeEl ? completionBillingModeEl.value : '') || 'fixed_fee');
    const config = completionModeConfig(normalizedMode);
    const rows = {
      billing_category: completionBillingCategoryEl ? completionBillingCategoryEl.closest('label') : null,
      hours: completionHoursEl ? completionHoursEl.closest('label') : null,
      rate: completionRateEl ? completionRateEl.closest('label') : null,
      amount: completionAmountEl ? completionAmountEl.closest('label') : null,
      expense_reference: completionExpenseReferenceEl ? completionExpenseReferenceEl.closest('label') : null,
      non_billable_reason: completionNonBillableReasonEl ? completionNonBillableReasonEl.closest('label') : null,
    };

    Object.keys(rows).forEach((key) => {
      if (rows[key]) {
        rows[key].hidden = false;
      }
    });

    if (rows.billing_category) rows.billing_category.hidden = !config.requireCategory;
    if (rows.hours) rows.hours.hidden = !config.show.includes('hours');
    if (rows.rate) rows.rate.hidden = !config.show.includes('rate');
    if (rows.amount) rows.amount.hidden = !config.show.includes('amount');
    if (rows.expense_reference) rows.expense_reference.hidden = !config.show.includes('expense_reference');
    if (rows.non_billable_reason) rows.non_billable_reason.hidden = !config.show.includes('non_billable_reason');

    if (completionModeNoteEl) completionModeNoteEl.textContent = config.note;
    if (completionBillingCategoryEl) completionBillingCategoryEl.required = !!config.requireCategory;
    if (completionHoursEl) completionHoursEl.required = !!config.requireHours;
  }

  function openCompletionModal(taskId) {
    if (!completionModal || !completionModalBackdrop || !completionForm) return;

    const task = bridge.getKnownTask(taskId);
    if (!task) return;

    pendingCompletionTaskId = taskId;
    completionForm.reset();

    const defaultMode = String(task.billing_mode || '').trim()
      || ((Number(task.is_billable) === 0 || String(task.billing_status || '') === 'not_billable' || task.action_owner_is_client) ? 'non_billable' : 'fixed_fee');
    if (completionBillingModeEl) completionBillingModeEl.value = defaultMode;
    if (completionBillingCategoryEl) completionBillingCategoryEl.value = String(task.billing_category || task.bucket_name || '');
    if (completionWorkSummaryEl) completionWorkSummaryEl.value = String(task.work_summary || task.description || task.title || '');
    if (completionHoursEl) completionHoursEl.value = String(task.hours || '');
    if (completionRateEl) completionRateEl.value = String(task.rate || '');
    if (completionAmountEl) completionAmountEl.value = String(task.amount || '');
    if (completionExpenseReferenceEl) completionExpenseReferenceEl.value = String(task.expense_reference || '');
    if (completionNonBillableReasonEl) completionNonBillableReasonEl.value = String(task.non_billable_reason || '');
    if (completionSummaryEl) {
      completionSummaryEl.textContent = 'Capture the completion details for "' + task.title + '" before it moves out of the active workflow and into the work ledger.';
    }

    syncCompletionForm(defaultMode);
    completionModal.hidden = false;
    completionModal.setAttribute('aria-hidden', 'false');
    completionModalBackdrop.hidden = false;
    if (completionWorkSummaryEl) completionWorkSummaryEl.focus();
  }

  function closeCompletionModal() {
    pendingCompletionTaskId = 0;
    if (completionModal) {
      completionModal.hidden = true;
      completionModal.setAttribute('aria-hidden', 'true');
    }
    if (completionModalBackdrop) completionModalBackdrop.hidden = true;
  }

  function wireCompletionModal() {
    if (closeCompletionModalBtn) {
      closeCompletionModalBtn.addEventListener('click', closeCompletionModal);
    }

    if (cancelCompletionBtn) {
      cancelCompletionBtn.addEventListener('click', closeCompletionModal);
    }

    if (completionModalBackdrop) {
      completionModalBackdrop.addEventListener('click', closeCompletionModal);
    }

    if (completionBillingModeEl) {
      completionBillingModeEl.addEventListener('change', () => {
        syncCompletionForm(completionBillingModeEl.value);
      });
    }

    if (!completionForm) return;

    completionForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!pendingCompletionTaskId) return;

      const formData = new FormData(completionForm);
      const taskId = pendingCompletionTaskId;
      try {
        bridge.setSelectedTaskId(taskId);
        await bridge.api('tasks/' + taskId + '/done', {
          method: 'POST',
          body: JSON.stringify({
            billing_mode: formData.get('billing_mode') || '',
            billing_category: formData.get('billing_category') || '',
            work_summary: formData.get('work_summary') || '',
            hours: formData.get('hours') || '',
            rate: formData.get('rate') || '',
            amount: formData.get('amount') || '',
            expense_reference: formData.get('expense_reference') || '',
            non_billable_reason: formData.get('non_billable_reason') || '',
          }),
        });
        closeCompletionModal();
        bridge.removeTaskById(taskId);
        bridge.closeDrawer();
        await bridge.loadTasks();
        bridge.alert('Task marked done and added to the work ledger.', 'success');
      } catch (err) {
        bridge.alert(err.message);
      }
    });
  }

  // --- Delete modal ---

  function openDeleteModal(taskId) {
    if (!deleteModal || !deleteModalBackdrop || !taskId) return;
    pendingDeleteTaskId = taskId;
    const task = bridge.getTaskById(taskId);
    if (deleteSummaryEl) {
      deleteSummaryEl.textContent = task
        ? 'Delete "' + task.title + '" and remove its related messages, notes, files, meetings, and notifications.'
        : 'This removes the task and its related messages, notes, files, meetings, and notifications.';
    }
    deleteModal.hidden = false;
    deleteModal.setAttribute('aria-hidden', 'false');
    deleteModalBackdrop.hidden = false;
  }

  function closeDeleteModal() {
    pendingDeleteTaskId = 0;
    if (deleteModal) {
      deleteModal.hidden = true;
      deleteModal.setAttribute('aria-hidden', 'true');
    }
    if (deleteModalBackdrop) {
      deleteModalBackdrop.hidden = true;
    }
  }

  function wireDeleteModal() {
    if (closeDeleteModalBtn) {
      closeDeleteModalBtn.addEventListener('click', closeDeleteModal);
    }

    if (cancelDeleteBtn) {
      cancelDeleteBtn.addEventListener('click', closeDeleteModal);
    }

    if (deleteModalBackdrop) {
      deleteModalBackdrop.addEventListener('click', closeDeleteModal);
    }

    if (!confirmDeleteBtn) return;

    confirmDeleteBtn.addEventListener('click', async () => {
      if (!pendingDeleteTaskId) return;

      const taskId = pendingDeleteTaskId;
      confirmDeleteBtn.disabled = true;
      try {
        await bridge.api('tasks/' + taskId, { method: 'DELETE' });
        bridge.removeTaskById(taskId);
        closeDeleteModal();
        bridge.closeDrawer();
        await bridge.loadTasks();
        bridge.alert('Task deleted.', 'success');
      } catch (err) {
        bridge.alert(err.message);
      } finally {
        confirmDeleteBtn.disabled = false;
      }
    });
  }

  // --- Wire everything ---
  wireMoveModal();
  wireCompletionModal();
  wireDeleteModal();
  wireRevisionModal();

  // --- Public API for admin-queue.js to call back into ---
  window.wpPqModals = {
    openMoveModal: openMoveModal,
    openRevisionModal: openRevisionModal,
    openCompletionModal: openCompletionModal,
    openDeleteModal: openDeleteModal,
    closeDeleteModal: closeDeleteModal,
    closeCompletionModal: closeCompletionModal,
    closeRevisionModal: closeRevisionModal,
    closeMoveModal: closeMoveModal,
    shouldPromptForMoveDecision: shouldPromptForMoveDecision,
    completionModal: completionModal,
    getPendingMove: function () { return pendingMove; },
    setPendingMove: function (m) { pendingMove = m; },
    setPendingStatusAction: function (s) { pendingStatusAction = s; },
    setPendingRevisionAction: function (a) { pendingRevisionAction = a; },
  };
})();
