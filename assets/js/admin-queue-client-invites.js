(function () {
  if (typeof window.wpPqConfig === 'undefined') return;
  if (!window.wpPqPortalUI) return;

  var api = window.wpPqPortalUI.api;
  var escapeHtml = window.wpPqPortalUI.escapeHtml;
  var alert = window.wpPqPortalUI.alert;
  var TOAST_DURATION_MS = window.wpPqPortalUI.TOAST_DURATION_MS;

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
          alert(result.message || 'Invite sent.', 'success', { duration: TOAST_DURATION_MS });
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
        alert('Invite revoked.', 'success', { duration: TOAST_DURATION_MS });
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
        alert(result.message || 'Invite resent.', 'success', { duration: TOAST_DURATION_MS });
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
        alert('Invite link copied to clipboard.', 'success', { duration: TOAST_DURATION_MS });
      } catch (copyErr) {
        window.prompt('Copy this invite link:', link);
      }
      return;
    }
  });
})();
