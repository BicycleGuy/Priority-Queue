(function (m) {
  var state = m.state;
  var el = m.el;
  var esc = m.esc;
  var api = m.api;
  var submitJson = m.submitJson;
  var ensureClients = m.ensureClients;
  var sortedClients = m.sortedClients;
  var clientOptions = m.clientOptions;
  var memberOptions = m.memberOptions;
  var replaceSectionUrl = m.replaceSectionUrl;
  var renderManagerFrame = m.renderManagerFrame;

  var clientColors = ['red', 'green', 'blue', 'red', 'green', 'blue'];
  var avatarClasses = { client_admin: 'cl', client_contributor: 'wo', client_viewer: 'wo', manager: 'ma', worker: 'wo' };

  // Helper: build <option> elements from array
  function buildOptions(items, valueFn, labelFn) {
    return items.map(function (item) {
      return '<option value="' + valueFn(item) + '">' + esc(labelFn(item)) + '</option>';
    }).join('');
  }

  // Helper: build move-to-client panel HTML
  function buildMovePanel(bucketId, moveClientOptions) {
    return '<div class="wp-pq-job-move-panel" data-move-panel="' + bucketId + '" hidden>' +
      '<label>Move to client: <select data-move-target-client="' + bucketId + '">' +
        '<option value="0">Choose client\u2026</option>' + moveClientOptions +
      '</select></label>' +
      '<div class="wp-pq-modal-actions">' +
        '<button class="button button-primary" type="button" data-action="confirm-move-job" data-job-id="' + bucketId + '">Move Job</button>' +
        '<button class="button" type="button" data-action="cancel-job-panel" data-job-id="' + bucketId + '">Cancel</button>' +
      '</div>' +
    '</div>';
  }

  // Helper: build delete-job panel HTML
  function buildDeletePanel(bucketId, targetJobOptions, hasOtherJobs) {
    var reassignHtml = hasOtherJobs
      ? '<label>Reassign tasks to: <select data-delete-target-job="' + bucketId + '">' + targetJobOptions + '</select></label>'
      : '<p class="wp-pq-empty-state" style="margin:4px 0">This will delete the job and all associated tasks. It cannot be undone.</p>';

    return '<div class="wp-pq-job-delete-panel" data-delete-panel="' + bucketId + '" hidden>' +
      reassignHtml +
      '<label>Type <strong>DELETE</strong> to confirm: <input type="text" data-delete-confirm="' + bucketId + '" placeholder="DELETE" autocomplete="off"></label>' +
      '<div class="wp-pq-modal-actions">' +
        '<button class="button wp-pq-danger-action" type="button" data-action="confirm-delete-job" data-job-id="' + bucketId + '">Delete Job</button>' +
        '<button class="button" type="button" data-action="cancel-job-panel" data-job-id="' + bucketId + '">Cancel</button>' +
      '</div>' +
    '</div>';
  }

  function initials(name) {
    var parts = String(name || '?').trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return parts[0].substring(0, 2).toUpperCase();
  }

  function buildDrawerHtml(selectedClient, clientTab, candidates, allClients) {
    if (!selectedClient) return '';
    var members = selectedClient.members || [];
    var buckets = selectedClient.buckets || [];
    var clientMemberOptions = members.map(function (member) {
      return '<option value="' + (member.user_id || member.id) + '"' + (Number(member.user_id || member.id) === Number(selectedClient.primary_contact_user_id || 0) ? ' selected' : '') + '>' + esc(member.name || member.email || 'Member') + '</option>';
    }).join('');

    // Tab: Overview
    var overviewTab = '<div class="wp-pq-client-tab-body" data-tab="overview"' + (clientTab !== 'overview' ? ' hidden' : '') + '>' +
      '<form class="wp-pq-inline-action-form" data-action="update-client" data-client-id="' + selectedClient.id + '">' +
        '<label>Name <input type="text" name="name" value="' + esc(selectedClient.name || '') + '" required></label>' +
        '<label>Primary contact <select name="primary_contact_user_id"><option value="0">Not set</option>' + clientMemberOptions + '</select></label>' +
        '<button class="button" type="submit">Save</button>' +
      '</form>' +
      '<div class="wp-pq-client-stats">' +
        '<div class="wp-pq-stat"><span class="wp-pq-stat-value">' + Number(selectedClient.delivered_count || 0) + '</span><span class="wp-pq-stat-label">Completed</span></div>' +
        '<div class="wp-pq-stat"><span class="wp-pq-stat-value">' + Number(selectedClient.unbilled_count || 0) + '</span><span class="wp-pq-stat-label">Unbilled</span></div>' +
        '<div class="wp-pq-stat"><span class="wp-pq-stat-value">' + Number(selectedClient.work_log_count || 0) + '</span><span class="wp-pq-stat-label">Work Statements</span></div>' +
        '<div class="wp-pq-stat"><span class="wp-pq-stat-value">' + Number(selectedClient.statement_count || 0) + '</span><span class="wp-pq-stat-label">Invoice Drafts</span></div>' +
      '</div></div>';

    // Tab: Members
    var memberRows = members.map(function (member) {
      return '<tr><td>' + esc(member.name || '') + '</td><td>' + esc(member.email || '') + '</td><td>' + esc(member.role_label || member.role || '') + '</td></tr>';
    }).join('');
    var membersTab = '<div class="wp-pq-client-tab-body" data-tab="members"' + (clientTab !== 'members' ? ' hidden' : '') + '>' +
      '<table class="wp-pq-admin-table wp-pq-manager-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>' +
      '<tbody>' + (memberRows || '<tr><td colspan="3">No members yet.</td></tr>') + '</tbody></table>' +
      '<form class="wp-pq-inline-action-form" data-action="add-client-member" data-client-id="' + selectedClient.id + '">' +
        '<label><select name="user_id" required><option value="0">Choose user</option>' + memberOptions(candidates, 0) + '</select></label>' +
        '<label><select name="client_role"><option value="client_contributor">Client Contributor</option><option value="client_admin">Client Admin</option><option value="client_viewer">Client Viewer</option></select></label>' +
        '<button class="button" type="submit">Add Member</button>' +
      '</form></div>';

    // Tab: Jobs
    var otherClients = (allClients || []).filter(function (c) { return Number(c.id) !== Number(selectedClient.id); });
    var moveClientOptions = buildOptions(otherClients, function (c) { return c.id; }, function (c) { return c.name || 'Client'; });

    var jobRows = buckets.map(function (bucket) {
      var memberCount = members.filter(function (m) { return (m.bucket_ids || []).map(Number).includes(Number(bucket.id)); }).length;
      var otherBuckets = buckets.filter(function (b) { return Number(b.id) !== Number(bucket.id); });
      var canDelete = otherBuckets.length > 0;
      var targetJobOptions = buildOptions(
        otherBuckets,
        function (b) { return b.id; },
        function (b) { return (b.bucket_name || 'Job') + (b.is_default ? ' (default)' : ''); }
      );

      var defaultLabel = bucket.is_default ? 'Default' : '';
      var memberLabel = memberCount + ' member' + (memberCount !== 1 ? 's' : '');

      return '<div class="wp-pq-job-row wp-pq-manager-subcard">' +
        '<div class="wp-pq-job-summary">' +
          '<strong>' + esc(bucket.bucket_name || 'Job') + '</strong>' +
          '<small>' + defaultLabel + (defaultLabel && memberLabel ? ' \u00b7 ' : '') + memberLabel + '</small>' +
        '</div>' +
        '<div class="wp-pq-job-actions">' +
          '<button class="button wp-pq-secondary-action" type="button" data-action="show-move-job" data-job-id="' + bucket.id + '">Move</button>' +
          '<button class="button wp-pq-secondary-action wp-pq-danger-action" type="button" data-action="show-delete-job" data-job-id="' + bucket.id + '">Delete</button>' +
        '</div>' +
        buildMovePanel(bucket.id, moveClientOptions) +
        buildDeletePanel(bucket.id, targetJobOptions, canDelete) +
      '</div>';
    }).join('');
    var jobsTab = '<div class="wp-pq-client-tab-body" data-tab="jobs"' + (clientTab !== 'jobs' ? ' hidden' : '') + '>' +
      '<form class="wp-pq-inline-action-form" data-action="create-job" data-client-id="' + selectedClient.id + '">' +
        '<label><input type="text" name="bucket_name" placeholder="New job name" required></label><button class="button" type="submit">Add Job</button></form>' +
      '<div class="wp-pq-bucket-list">' + (jobRows || '<p class="wp-pq-empty-state">No jobs yet.</p>') + '</div></div>';

    // Tab: Access
    var accessHeaderCells = buckets.map(function (bucket) {
      var label = (bucket.bucket_name || '').length > 18 ? (bucket.bucket_name || '').slice(0, 16) + '\u2026' : (bucket.bucket_name || '');
      return '<th class="wp-pq-access-col-head" title="' + esc(bucket.bucket_name || '') + '">' + esc(label) + '</th>';
    }).join('');
    var accessRows = members.map(function (member) {
      var memberBucketIds = (member.bucket_ids || []).map(Number);
      var cells = buckets.map(function (bucket) {
        var assigned = memberBucketIds.includes(Number(bucket.id));
        return '<td class="wp-pq-access-cell"><label class="wp-pq-access-toggle"><input type="checkbox" data-action="toggle-job-access" data-bucket-id="' + bucket.id + '" data-user-id="' + (member.user_id || member.id) + '" data-client-id="' + selectedClient.id + '" ' + (assigned ? 'checked' : '') + '><span>' + (assigned ? '\u2713' : '') + '</span></label></td>';
      }).join('');
      return '<tr><td>' + esc(member.name || '') + '</td>' + cells + '</tr>';
    }).join('');
    var accessTab = '<div class="wp-pq-client-tab-body" data-tab="access"' + (clientTab !== 'access' ? ' hidden' : '') + '>' +
      (members.length > 0 && buckets.length > 0
        ? '<div class="wp-pq-access-matrix-wrap"><table class="wp-pq-admin-table wp-pq-manager-table wp-pq-access-matrix"><thead><tr><th>Member</th>' + accessHeaderCells + '</tr></thead><tbody>' + accessRows + '</tbody></table></div>'
        : '<p class="wp-pq-empty-state">Add members and jobs first to configure access.</p>') +
      '</div>';

    // Tab: Contact
    var clientContacts = selectedClient.contacts || [];
    var contactRows = clientContacts.map(function (c) {
      return '<tr><td>' + esc(c.contact_type || '') + ' <small>(' + esc(c.label || '') + ')</small></td>' +
        '<td>' + esc(c.value || '') + (Number(c.is_primary) ? ' \u2605' : '') + '</td><td></td>' +
        '<td><button type="button" class="button wp-pq-secondary-action" data-action="delete-client-contact" data-client-id="' + selectedClient.id + '" data-contact-id="' + c.id + '">Remove</button></td></tr>';
    }).join('');
    var memberContactSections = members.map(function (member) {
      var mc = member.contacts || [];
      var mcRows = mc.map(function (c) {
        return '<tr><td>' + esc(c.contact_type || '') + ' <small>(' + esc(c.label || '') + ')</small></td>' +
          '<td>' + esc(c.value || '') + (Number(c.is_primary) ? ' \u2605' : '') + '</td>' +
          '<td><button type="button" class="button wp-pq-secondary-action" data-action="delete-member-contact" data-member-id="' + member.membership_id + '" data-contact-id="' + c.id + '">Remove</button></td></tr>';
      }).join('');
      return '<div class="wp-pq-manager-subcard" style="margin-top:12px"><strong>' + esc(member.name || '') + '</strong> <small>' + esc(member.role || '') + '</small>' +
        (mc.length > 0 ? '<table class="wp-pq-admin-table wp-pq-manager-table" style="margin-top:6px"><tbody>' + mcRows + '</tbody></table>' : '') +
        '<form class="wp-pq-inline-action-form" data-action="save-member-contact" data-member-id="' + member.membership_id + '" style="margin-top:6px">' +
          '<select name="contact_type"><option value="email">Email</option><option value="phone">Phone</option></select>' +
          '<select name="label"><option value="work">Work</option><option value="personal">Personal</option><option value="mobile">Mobile</option><option value="home">Home</option></select>' +
          '<input type="text" name="value" placeholder="email@example.com" required>' +
          '<label><input type="checkbox" name="is_primary" value="1"> Primary</label>' +
          '<button class="button" type="submit">Add</button></form></div>';
    }).join('');
    var contactTab = '<div class="wp-pq-client-tab-body" data-tab="contact"' + (clientTab !== 'contact' ? ' hidden' : '') + '>' +
      '<form data-action="save-client-address" data-client-id="' + selectedClient.id + '"><div class="wp-pq-form-grid">' +
        '<label class="span-2">Address <input type="text" name="address_line1" value="' + esc(selectedClient.address_line1 || '') + '" placeholder="123 Main St"></label>' +
        '<label>Suite / Unit <input type="text" name="address_line2" value="' + esc(selectedClient.address_line2 || '') + '"></label>' +
        '<label>City <input type="text" name="city" value="' + esc(selectedClient.city || '') + '"></label>' +
        '<label>State <input type="text" name="state" value="' + esc(selectedClient.state || '') + '"></label>' +
        '<label>ZIP <input type="text" name="zip" value="' + esc(selectedClient.zip || '') + '"></label>' +
        '<label>Country <input type="text" name="country" value="' + esc(selectedClient.country || '') + '"></label>' +
        '<label>Tax ID / EIN <input type="text" name="tax_id" value="' + esc(selectedClient.tax_id || '') + '" placeholder="XX-XXXXXXX"></label>' +
      '</div><button class="button" type="submit">Save</button></form>' +
      '<h4 style="margin:20px 0 8px">Organization Contacts</h4>' +
      '<table class="wp-pq-admin-table wp-pq-manager-table"><thead><tr><th>Type</th><th>Label</th><th></th><th></th></tr></thead>' +
      '<tbody>' + (contactRows || '<tr><td colspan="4">No contacts yet.</td></tr>') + '</tbody></table>' +
      '<form class="wp-pq-inline-action-form" data-action="save-client-contact" data-client-id="' + selectedClient.id + '" style="margin-top:6px">' +
        '<select name="contact_type"><option value="email">Email</option><option value="phone">Phone</option></select>' +
        '<select name="label"><option value="work">Work</option><option value="personal">Personal</option><option value="mobile">Mobile</option><option value="home">Home</option></select>' +
        '<input type="text" name="value" placeholder="email@example.com" required>' +
        '<label><input type="checkbox" name="is_primary" value="1"> Primary</label>' +
        '<button class="button" type="submit">Add</button></form>' +
      (members.length > 0 ? '<h4 style="margin:20px 0 8px">Member Contacts</h4>' + memberContactSections : '') +
      '</div>';

    return '<div class="wp-pq-tree-drawer-backdrop is-open" id="wp-pq-tree-backdrop"></div>' +
      '<div class="wp-pq-tree-drawer is-open" id="wp-pq-tree-drawer" data-client-id="' + selectedClient.id + '">' +
        '<div class="wp-pq-tree-drawer-header">' +
          '<h3>' + esc(selectedClient.name || 'Client') + '</h3>' +
          '<p class="wp-pq-panel-note">' + esc(selectedClient.email || '') + '</p>' +
          '<button class="wp-pq-tree-drawer-close" type="button" data-action="close-tree-drawer">&times;</button>' +
        '</div>' +
        '<div class="wp-pq-client-tabs">' +
          '<button type="button" class="wp-pq-client-tab-btn' + (clientTab === 'overview' ? ' is-active' : '') + '" data-client-tab="overview">Overview</button>' +
          '<button type="button" class="wp-pq-client-tab-btn' + (clientTab === 'members' ? ' is-active' : '') + '" data-client-tab="members">Members</button>' +
          '<button type="button" class="wp-pq-client-tab-btn' + (clientTab === 'jobs' ? ' is-active' : '') + '" data-client-tab="jobs">Jobs</button>' +
          '<button type="button" class="wp-pq-client-tab-btn' + (clientTab === 'access' ? ' is-active' : '') + '" data-client-tab="access">Access</button>' +
          '<button type="button" class="wp-pq-client-tab-btn' + (clientTab === 'contact' ? ' is-active' : '') + '" data-client-tab="contact">Contact</button>' +
        '</div>' +
        overviewTab + membersTab + jobsTab + accessTab + contactTab +
      '</div>';
  }

  async function renderClients() {
    renderManagerFrame('clients');
    el.managerContent.innerHTML = '<div class="wp-pq-empty-state">Loading clients\u2026</div>';
    const data = await ensureClients();
    const linkable = data.linkable_users || [];
    const candidates = data.member_candidates || [];
    const params = new URLSearchParams(window.location.search);
    const queryClientId = Number(params.get('client_id') || 0);
    const search = String(state.clientsSearch || '').trim().toLowerCase();
    const allClients = sortedClients();
    const filteredClients = allClients.filter(function (client) {
      if (!search) return true;
      var haystack = [client.name || '', client.email || '', client.label || ''].join(' ').toLowerCase();
      return haystack.includes(search);
    });

    // Determine if drawer should be open
    const selectedFromState = Number(state.selectedClientId || queryClientId || 0);
    const drawerClient = state.drawerOpen
      ? (allClients.find(function (c) { return Number(c.id) === selectedFromState; }) || null)
      : null;
    const clientTab = state.clientTab || 'overview';

    // Toolbar: search + action buttons + dialogs
    el.managerToolbar.innerHTML =
      '<div class="wp-pq-manager-toolbar-actions">' +
        '<input type="search" id="wp-pq-client-browser-search" class="wp-pq-tree-search" value="' + esc(state.clientsSearch || '') + '" placeholder="Search clients\u2026">' +
        '<button class="button button-primary" type="button" data-action="open-create-client-modal">New Client</button>' +
        '<button class="button" type="button" data-action="open-link-client-modal">Link User to Client</button>' +
      '</div>' +
      '<dialog id="wp-pq-create-client-dialog" class="wp-pq-modal-dialog">' +
        '<form id="wp-pq-manager-create-client" class="wp-pq-modal-body" method="dialog">' +
          '<h3>New Client</h3>' +
          '<label>Name <input type="text" name="client_name" required></label>' +
          '<label>Email <input type="email" name="client_email" required></label>' +
          '<label>First job <input type="text" name="initial_bucket_name" placeholder="Main, Retainer, Launch"></label>' +
          '<details class="wp-pq-modal-details"><summary>Address &amp; billing (optional)</summary>' +
            '<label>Tax / VAT ID <input type="text" name="tax_id"></label>' +
            '<label>Address <input type="text" name="address_line1" placeholder="Street address"></label>' +
            '<label>Address 2 <input type="text" name="address_line2" placeholder="Suite, unit, etc."></label>' +
            '<div class="wp-pq-modal-row">' +
              '<label>City <input type="text" name="city"></label>' +
              '<label>State <input type="text" name="state"></label>' +
            '</div>' +
            '<div class="wp-pq-modal-row">' +
              '<label>Zip <input type="text" name="zip"></label>' +
              '<label>Country <input type="text" name="country"></label>' +
            '</div>' +
          '</details>' +
          '<div class="wp-pq-modal-actions"><button class="button button-primary" type="submit">Create Client</button><button class="button" type="button" data-action="close-dialog">Cancel</button></div>' +
        '</form></dialog>' +
      '<dialog id="wp-pq-link-client-dialog" class="wp-pq-modal-dialog">' +
        '<form id="wp-pq-manager-link-client" class="wp-pq-modal-body" method="dialog">' +
          '<h3>Link Existing User</h3>' +
          '<label>User <select name="user_id" required><option value="0">Choose user</option>' + memberOptions(linkable, 0) + '</select></label>' +
          '<label>First job <input type="text" name="initial_bucket_name" placeholder="Main, Retainer, Launch"></label>' +
          '<div class="wp-pq-modal-actions"><button class="button button-primary" type="submit">Link User</button><button class="button" type="button" data-action="close-dialog">Cancel</button></div>' +
        '</form></dialog>';

    // Build tree HTML
    var treeItems = filteredClients.map(function (client, idx) {
      var color = clientColors[idx % clientColors.length];
      var ini = initials(client.name);
      var unbilled = Number(client.unbilled_count || 0);
      var badge = unbilled > 0
        ? '<span class="wp-pq-tree-badge wp-pq-tree-badge-warn">' + unbilled + ' unbilled</span>'
        : '<span class="wp-pq-tree-badge wp-pq-tree-badge-ok">Up to date</span>';
      var members = client.members || [];
      var buckets = client.buckets || [];

      // People branch
      var memberNodes = members.map(function (member) {
        var role = member.role || member.client_role || '';
        var avClass = avatarClasses[role] || 'wo';
        var mIni = initials(member.name);
        return '<li><div class="wp-pq-tree-node wp-pq-tree-node-member">' +
          '<div class="wp-pq-tree-member-avatar ' + avClass + '">' + esc(mIni) + '</div>' +
          '<div><div class="wp-pq-tree-node-name">' + esc(member.name || '') + '</div>' +
          '<div class="wp-pq-tree-node-role">' + esc(member.role_label || role || '') + '</div></div></div></li>';
      }).join('');
      var peopleBranch = '<li>' +
        '<div class="wp-pq-tree-node wp-pq-tree-node-group" data-action="tree-toggle">' +
          '<span class="wp-pq-tree-collapse-indicator">&#9660;</span> People <span class="wp-pq-tree-count">' + members.length + '</span>' +
        '</div>' +
        '<ul>' + memberNodes +
          '<li><div class="wp-pq-tree-node wp-pq-tree-node-add" data-action="open-invite-for-client" data-client-id="' + client.id + '">+ Invite member</div></li>' +
        '</ul></li>';

      // Jobs branch
      var jobNodes = buckets.map(function (bucket) {
        var memberCount = members.filter(function (m) { return (m.bucket_ids || []).map(Number).includes(Number(bucket.id)); }).length;
        var jobMembers = members.filter(function (m) { return (m.bucket_ids || []).map(Number).includes(Number(bucket.id)); });
        var subMembers = jobMembers.map(function (jm) {
          var avClass = avatarClasses[jm.role || ''] || 'wo';
          return '<li><div class="wp-pq-tree-node wp-pq-tree-node-member"><div class="wp-pq-tree-member-avatar ' + avClass + '">' + esc(initials(jm.name)) + '</div><div><div class="wp-pq-tree-node-name">' + esc(jm.name || '') + '</div></div></div></li>';
        }).join('');
        return '<li>' +
          '<div class="wp-pq-tree-node wp-pq-tree-node-job">' +
            '<div><div class="wp-pq-tree-node-name">' + esc(bucket.bucket_name || 'Job') +
              (bucket.is_default ? ' <span class="wp-pq-tree-job-default">Default</span>' : '') + '</div>' +
            '<div class="wp-pq-tree-node-meta">' + memberCount + ' member' + (memberCount !== 1 ? 's' : '') + '</div></div>' +
          '</div>' +
          (subMembers ? '<ul>' + subMembers + '</ul>' : '') +
        '</li>';
      }).join('');
      var jobsBranch = '<li>' +
        '<div class="wp-pq-tree-node wp-pq-tree-node-group" data-action="tree-toggle">' +
          '<span class="wp-pq-tree-collapse-indicator">&#9660;</span> Jobs <span class="wp-pq-tree-count">' + buckets.length + '</span>' +
        '</div>' +
        '<ul>' + jobNodes +
          '<li><div class="wp-pq-tree-node wp-pq-tree-node-add" data-action="open-add-job-for-client" data-client-id="' + client.id + '">+ Add job</div></li>' +
        '</ul></li>';

      return '<li>' +
        '<div class="wp-pq-tree-node wp-pq-tree-node-client">' +
          '<span class="wp-pq-tree-collapse-indicator" data-action="tree-toggle">&#9660;</span>' +
          '<div class="wp-pq-tree-client-icon ' + color + '">' + esc(ini) + '</div>' +
          '<div><div class="wp-pq-tree-node-name">' + esc(client.name || 'Client') + ' ' + badge + '</div>' +
          '<div class="wp-pq-tree-node-meta">' + esc(client.email || '') + '</div></div>' +
          '<div class="wp-pq-tree-node-actions"><button class="wp-pq-tree-details-link" type="button" data-open-client="' + client.id + '">details</button></div>' +
        '</div>' +
        '<ul>' + peopleBranch + jobsBranch + '</ul>' +
      '</li>';
    }).join('');

    var drawerHtml = drawerClient ? buildDrawerHtml(drawerClient, clientTab, candidates, allClients) : '';

    el.managerContent.innerHTML =
      '<div class="wp-pq-tree-layout">' +
        '<ul class="wp-pq-tree">' +
          (treeItems || '<li><div class="wp-pq-empty-state">No clients yet.</div></li>') +
        '</ul>' +
      '</div>' +
      drawerHtml;
  }

  m.render.clients = renderClients;
})(window._pqMgr);
