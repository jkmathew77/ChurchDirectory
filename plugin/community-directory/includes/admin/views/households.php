<?php
/**
 * Admin view: Households
 *
 * Lists all households with CRUD operations.
 * Uses REST API: GET/POST /households, GET/PUT /households/{id}, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_base = esc_url( rest_url( CD_API_NAMESPACE ) );
$nonce    = wp_create_nonce( 'wp_rest' );
?>

<style>
.cd-status-tabs {
    display: flex; gap: 0; margin: 16px 0 0 0;
    border-bottom: 1px solid #c3c4c7; padding: 0; list-style: none;
}
.cd-status-tabs li { margin: 0; }
.cd-status-tabs a {
    display: inline-block; padding: 8px 14px; text-decoration: none; color: #50575e;
    border: 1px solid transparent; border-bottom: none; margin-bottom: -1px; font-size: 13px;
}
.cd-status-tabs a:hover { color: #135e96; }
.cd-status-tabs a.current {
    background: #fff; border-color: #c3c4c7; border-bottom-color: #fff; color: #1d2327; font-weight: 600;
}
.cd-status-tabs .count {
    display: inline-block; background: #dcdcde; border-radius: 10px; padding: 0 7px;
    font-size: 11px; line-height: 18px; color: #50575e; margin-left: 4px;
}
.cd-status-tabs a.current .count { background: #2271b1; color: #fff; }
.cd-loading { text-align: center; padding: 40px 20px; color: #646970; }
.cd-loading .spinner { visibility: visible; float: none; margin: 0 8px 0 0; vertical-align: middle; }
.cd-search-box { float: right; margin-bottom: 10px; }
.cd-search-box input { margin: 0; }
.cd-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; white-space: nowrap; }
.cd-badge-active { background: #e6f6e6; color: #1a7a1a; }
.cd-badge-inactive { background: #f0f0f1; color: #646970; }
.cd-badge-head { background: #e8f0fe; color: #1a56db; }
.cd-badge-spouse { background: #fef3e2; color: #b45309; }
.cd-badge-adult_child { background: #f0e6ff; color: #6b21a8; }
.cd-badge-child { background: #fce4e4; color: #991b1b; }
.cd-badge-other { background: #f0f0f1; color: #646970; }

/* Detail panel */
.cd-detail-row td { padding: 0 !important; background: #f9f9f9; }
.cd-detail-panel { padding: 16px 24px; border-top: 1px solid #e0e0e0; }
.cd-detail-panel h4 { margin: 16px 0 8px 0; font-size: 13px; color: #1d2327; }
.cd-detail-panel h4:first-child { margin-top: 0; }

/* Member list inside detail */
.cd-hh-members { margin-top: 8px; }
.cd-hh-member-row {
    display: flex; align-items: center; gap: 12px; padding: 6px 0;
    border-bottom: 1px solid #eee; font-size: 13px;
}
.cd-hh-member-row:last-child { border-bottom: none; }
.cd-hh-member-name { flex: 1; font-weight: 500; }
.cd-hh-member-actions { display: flex; gap: 4px; }

/* Modal */
.cd-modal-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center;
}
.cd-modal-overlay.active { display: flex; }
.cd-modal {
    background: #fff; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%;
    max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
.cd-modal h2 { margin: 0 0 16px 0; font-size: 18px; }
.cd-modal label { display: block; margin-bottom: 12px; font-size: 13px; font-weight: 600; }
.cd-modal input, .cd-modal textarea, .cd-modal select {
    width: 100%; padding: 6px 8px; margin-top: 4px; box-sizing: border-box;
}
.cd-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }

/* Pagination */
.cd-pagination { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; }
.cd-pagination-buttons { display: flex; gap: 4px; align-items: center; }

/* Member search autocomplete */
.cd-autocomplete { position: relative; }
.cd-autocomplete-results {
    position: absolute; top: 100%; left: 0; right: 0; background: #fff;
    border: 1px solid #c3c4c7; max-height: 200px; overflow-y: auto; z-index: 10;
    display: none;
}
.cd-autocomplete-results.active { display: block; }
.cd-autocomplete-item {
    padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f0f0f1;
}
.cd-autocomplete-item:hover { background: #f0f6fc; }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Households', 'community-directory' ); ?></h1>
    <button type="button" class="page-title-action" id="cd-btn-create"><?php esc_html_e( 'Create Household', 'community-directory' ); ?></button>
    <hr class="wp-header-end">

    <div id="cd-notice" class="notice" style="display:none;" role="alert"><p id="cd-notice-text"></p></div>

    <div class="cd-search-box">
        <input type="search" id="cd-search" placeholder="<?php esc_attr_e( 'Search households...', 'community-directory' ); ?>">
        <button type="button" id="cd-btn-search" class="button"><?php esc_html_e( 'Search', 'community-directory' ); ?></button>
    </div>

    <ul class="cd-status-tabs" id="cd-tabs">
        <li><a href="#" data-status="active" class="current"><?php esc_html_e( 'Active', 'community-directory' ); ?> <span class="count" id="cd-count-active">0</span></a></li>
        <li><a href="#" data-status="inactive"><?php esc_html_e( 'Inactive', 'community-directory' ); ?> <span class="count" id="cd-count-inactive">0</span></a></li>
        <li><a href="#" data-status="all"><?php esc_html_e( 'All', 'community-directory' ); ?> <span class="count" id="cd-count-all">0</span></a></li>
    </ul>

    <div id="cd-loading" class="cd-loading">
        <span class="spinner"></span> <?php esc_html_e( 'Loading households...', 'community-directory' ); ?>
    </div>

    <table class="widefat striped" id="cd-table" style="display:none;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Household Name', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Head', 'community-directory' ); ?></th>
                <th style="width:80px;"><?php esc_html_e( 'Members', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Status', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Created', 'community-directory' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
            </tr>
        </thead>
        <tbody id="cd-tbody"></tbody>
    </table>

    <div id="cd-empty" style="display:none;padding:20px;text-align:center;color:#646970;">
        <?php esc_html_e( 'No households found.', 'community-directory' ); ?>
    </div>

    <div id="cd-pagination" class="cd-pagination" style="display:none;">
        <span id="cd-pagination-info"></span>
        <span class="cd-pagination-buttons">
            <button type="button" class="button" id="cd-prev" disabled>&laquo; <?php esc_html_e( 'Previous', 'community-directory' ); ?></button>
            <span id="cd-page-indicator" style="padding:0 8px;font-size:13px;"></span>
            <button type="button" class="button" id="cd-next" disabled><?php esc_html_e( 'Next', 'community-directory' ); ?> &raquo;</button>
        </span>
    </div>
</div>

<!-- Create Household Modal -->
<div class="cd-modal-overlay" id="cd-modal-create">
    <div class="cd-modal">
        <h2><?php esc_html_e( 'Create Household', 'community-directory' ); ?></h2>
        <label><?php esc_html_e( 'Household Name', 'community-directory' ); ?>
            <input type="text" id="cd-create-name" placeholder="<?php esc_attr_e( 'e.g. The Smith Family', 'community-directory' ); ?>">
        </label>
        <label><?php esc_html_e( 'Primary Address (optional)', 'community-directory' ); ?>
            <textarea id="cd-create-address" rows="2" placeholder="<?php esc_attr_e( '123 Main St, City, State ZIP', 'community-directory' ); ?>"></textarea>
        </label>
        <div class="cd-modal-actions">
            <button type="button" class="button" id="cd-create-cancel"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
            <button type="button" class="button button-primary" id="cd-create-submit"><?php esc_html_e( 'Create', 'community-directory' ); ?></button>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="cd-modal-overlay" id="cd-modal-add-member">
    <div class="cd-modal">
        <h2><?php esc_html_e( 'Add Member to Household', 'community-directory' ); ?></h2>
        <label><?php esc_html_e( 'Search Member', 'community-directory' ); ?>
            <div class="cd-autocomplete">
                <input type="text" id="cd-add-member-search" autocomplete="off" placeholder="<?php esc_attr_e( 'Type a name...', 'community-directory' ); ?>">
                <div class="cd-autocomplete-results" id="cd-member-results"></div>
            </div>
        </label>
        <input type="hidden" id="cd-add-member-id" value="">
        <div id="cd-add-member-selected" style="margin-bottom:12px;font-weight:600;display:none;"></div>
        <label><?php esc_html_e( 'Role', 'community-directory' ); ?>
            <select id="cd-add-member-role">
                <option value="head"><?php esc_html_e( 'Head of Household', 'community-directory' ); ?></option>
                <option value="spouse"><?php esc_html_e( 'Spouse', 'community-directory' ); ?></option>
                <option value="adult_child"><?php esc_html_e( 'Adult Child (18+)', 'community-directory' ); ?></option>
                <option value="child"><?php esc_html_e( 'Child (under 18)', 'community-directory' ); ?></option>
                <option value="other" selected><?php esc_html_e( 'Other', 'community-directory' ); ?></option>
            </select>
        </label>
        <div class="cd-modal-actions">
            <button type="button" class="button" id="cd-add-member-cancel"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
            <button type="button" class="button button-primary" id="cd-add-member-submit"><?php esc_html_e( 'Add Member', 'community-directory' ); ?></button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var API = <?php echo wp_json_encode( $api_base ); ?>;
    var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

    var state = { status: 'active', page: 1, perPage: 20, total: 0, pages: 0, search: '', expandedId: null, addMemberHouseholdId: null };
    var cache = {};

    /* DOM */
    var $ = function(id) { return document.getElementById(id); };
    var elLoading = $('cd-loading'), elTable = $('cd-table'), elTbody = $('cd-tbody');
    var elEmpty = $('cd-empty'), elPagination = $('cd-pagination');
    var elNotice = $('cd-notice'), elNoticeText = $('cd-notice-text');

    /* Init */
    loadList();

    /* Tab clicks */
    document.addEventListener('click', function(e) {
        var tab = e.target.closest('#cd-tabs a[data-status]');
        if (tab) {
            e.preventDefault();
            var links = $('cd-tabs').querySelectorAll('a');
            for (var i = 0; i < links.length; i++) links[i].classList.remove('current');
            tab.classList.add('current');
            state.status = tab.getAttribute('data-status');
            state.page = 1;
            loadList();
        }
    });

    /* Search */
    $('cd-btn-search').addEventListener('click', function() { state.search = $('cd-search').value.trim(); state.page = 1; loadList(); });
    $('cd-search').addEventListener('keypress', function(e) { if (e.key === 'Enter') { state.search = this.value.trim(); state.page = 1; loadList(); } });

    /* Pagination */
    $('cd-prev').addEventListener('click', function() { if (state.page > 1) { state.page--; loadList(); } });
    $('cd-next').addEventListener('click', function() { if (state.page < state.pages) { state.page++; loadList(); } });

    /* Create Household Modal */
    $('cd-btn-create').addEventListener('click', function() { $('cd-modal-create').classList.add('active'); $('cd-create-name').focus(); });
    $('cd-create-cancel').addEventListener('click', function() { $('cd-modal-create').classList.remove('active'); });
    $('cd-create-submit').addEventListener('click', createHousehold);

    /* Add Member Modal */
    $('cd-add-member-cancel').addEventListener('click', closeAddMemberModal);
    $('cd-add-member-submit').addEventListener('click', addMemberSubmit);

    /* Member search autocomplete */
    var searchTimer = null;
    $('cd-add-member-search').addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        if (q.length < 2) { $('cd-member-results').classList.remove('active'); return; }
        searchTimer = setTimeout(function() { searchMembers(q); }, 300);
    });

    /* Close modals on overlay click */
    document.querySelectorAll('.cd-modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) el.classList.remove('active');
        });
    });

    /* ── API Functions ── */

    function loadList() {
        elLoading.style.display = '';
        elTable.style.display = 'none';
        elEmpty.style.display = 'none';
        elPagination.style.display = 'none';

        var url = API + '/households?page=' + state.page + '&per_page=' + state.perPage;
        if (state.status && state.status !== 'all') url += '&status=' + encodeURIComponent(state.status);
        if (state.search) url += '&search=' + encodeURIComponent(state.search);

        apiFetch(url).then(function(json) {
            elLoading.style.display = 'none';
            if (!json.success) { showNotice(json.error ? json.error.message : 'Error', 'error'); return; }

            var rows = json.data.households || [];
            var counts = json.data.counts || {};
            var meta = json.meta || {};
            state.total = meta.total || 0;
            state.pages = meta.pages || 0;

            ['active', 'inactive', 'all'].forEach(function(k) {
                var el = $('cd-count-' + k);
                if (el) el.textContent = counts[k] || 0;
            });

            if (rows.length === 0) { elEmpty.style.display = ''; return; }
            renderRows(rows);
            updatePagination();
        });
    }

    function renderRows(rows) {
        var html = '';
        rows.forEach(function(row) {
            html += '<tr>';
            html += '<td><strong>' + esc(row.name) + '</strong></td>';
            html += '<td>' + esc(row.head_name || '—') + '</td>';
            html += '<td style="text-align:center;">' + (row.member_count || 0) + '</td>';
            html += '<td><span class="cd-badge cd-badge-' + esc(row.status) + '">' + esc(row.status) + '</span></td>';
            html += '<td>' + formatDate(row.created_at) + '</td>';
            html += '<td>';
            html += '<button type="button" class="button button-small" onclick="cdHH.toggle(' + row.id + ')">' + (state.expandedId == row.id ? 'Hide' : 'Manage') + '</button>';
            html += '</td>';
            html += '</tr>';

            /* Detail row (hidden by default) */
            html += '<tr class="cd-detail-row" id="cd-detail-' + row.id + '" style="display:' + (state.expandedId == row.id ? 'table-row' : 'none') + ';">';
            html += '<td colspan="6"><div class="cd-detail-panel" id="cd-panel-' + row.id + '"><div class="cd-loading"><span class="spinner"></span> Loading...</div></div></td>';
            html += '</tr>';
        });
        elTbody.innerHTML = html;
        elTable.style.display = '';

        // If we had an expanded row, re-load its details
        if (state.expandedId) {
            loadHouseholdDetail(state.expandedId);
        }
    }

    function loadHouseholdDetail(id) {
        apiFetch(API + '/households/' + id).then(function(json) {
            if (!json.success) return;
            var h = json.data.household;
            cache[id] = h;
            renderDetail(id, h);
        });
    }

    function renderDetail(id, h) {
        var panel = $('cd-panel-' + id);
        if (!panel) return;

        var html = '';

        /* Actions bar */
        html += '<div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:12px;">';
        html += '<button type="button" class="button button-small" onclick="cdHH.edit(' + id + ')">Edit Household</button>';
        html += '<button type="button" class="button button-small button-link-delete" style="color:#a00;" onclick="cdHH.deactivate(' + id + ')">Deactivate</button>';
        html += '</div>';

        /* Household info */
        html += '<h4>Household Info</h4>';
        html += '<div style="font-size:13px;margin-bottom:4px;"><strong>Name:</strong> ' + esc(h.name) + '</div>';
        html += '<div style="font-size:13px;margin-bottom:12px;"><strong>Address:</strong> ' + esc(h.primary_address || '—') + '</div>';

        /* Members list */
        html += '<h4>Members <button type="button" class="button button-small" style="margin-left:8px;" onclick="cdHH.openAddMember(' + id + ')">+ Add Member</button></h4>';
        html += '<div class="cd-hh-members">';

        if (h.members && h.members.length > 0) {
            h.members.forEach(function(m) {
                html += '<div class="cd-hh-member-row">';
                html += '<span class="cd-hh-member-name">' + esc(m.first_name) + ' ' + esc(m.last_name) + '</span>';
                html += '<span class="cd-badge cd-badge-' + esc(m.role) + '">' + esc(formatRole(m.role)) + '</span>';
                if (m.primary_email) html += '<span style="font-size:12px;color:#646970;margin-left:8px;">' + esc(m.primary_email) + '</span>';
                html += '<span class="cd-hh-member-actions">';
                html += '<select onchange="cdHH.changeRole(' + id + ',' + m.member_id + ',this.value)" style="font-size:12px;padding:2px;">';
                ['head','spouse','adult_child','child','other'].forEach(function(r) {
                    html += '<option value="' + r + '"' + (r === m.role ? ' selected' : '') + '>' + formatRole(r) + '</option>';
                });
                html += '</select>';
                html += '<button type="button" class="button button-small button-link-delete" style="color:#a00;font-size:11px;" onclick="cdHH.removeMember(' + id + ',' + m.member_id + ')">Remove</button>';
                html += '</span>';
                html += '</div>';
            });
        } else {
            html += '<div style="font-size:13px;color:#646970;padding:8px 0;">No members in this household.</div>';
        }
        html += '</div>';

        panel.innerHTML = html;
    }

    function createHousehold() {
        var name = $('cd-create-name').value.trim();
        var address = $('cd-create-address').value.trim();
        if (!name) { showNotice('Household name is required.', 'error'); return; }

        apiFetch(API + '/households', 'POST', { name: name, primary_address: address }).then(function(json) {
            $('cd-modal-create').classList.remove('active');
            $('cd-create-name').value = '';
            $('cd-create-address').value = '';
            if (json.success) {
                showNotice('Household created.', 'success');
                loadList();
            } else {
                showNotice(json.error ? json.error.message : 'Error creating household.', 'error');
            }
        });
    }

    function searchMembers(q) {
        apiFetch(API + '/admin/members/search?q=' + encodeURIComponent(q)).then(function(json) {
            var results = $('cd-member-results');
            if (!json.success || !json.data.members.length) {
                results.classList.remove('active');
                return;
            }
            var html = '';
            json.data.members.forEach(function(m) {
                html += '<div class="cd-autocomplete-item" data-id="' + m.id + '" data-name="' + esc(m.first_name + ' ' + m.last_name) + '">';
                html += esc(m.first_name + ' ' + m.last_name);
                html += '</div>';
            });
            results.innerHTML = html;
            results.classList.add('active');

            // Click handler for results
            results.querySelectorAll('.cd-autocomplete-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    $('cd-add-member-id').value = this.getAttribute('data-id');
                    $('cd-add-member-search').value = this.getAttribute('data-name');
                    $('cd-add-member-selected').textContent = 'Selected: ' + this.getAttribute('data-name');
                    $('cd-add-member-selected').style.display = '';
                    results.classList.remove('active');
                });
            });
        });
    }

    function addMemberSubmit() {
        var memberId = $('cd-add-member-id').value;
        var role = $('cd-add-member-role').value;
        var householdId = state.addMemberHouseholdId;

        if (!memberId) { showNotice('Please search and select a member.', 'error'); return; }

        apiFetch(API + '/households/' + householdId + '/members', 'POST', { member_id: parseInt(memberId), role: role }).then(function(json) {
            closeAddMemberModal();
            if (json.success) {
                showNotice(json.data.message || 'Member added.', 'success');
                loadHouseholdDetail(householdId);
                loadList(); // refresh counts
            } else {
                showNotice(json.error ? json.error.message : 'Error adding member.', 'error');
            }
        });
    }

    function closeAddMemberModal() {
        $('cd-modal-add-member').classList.remove('active');
        $('cd-add-member-id').value = '';
        $('cd-add-member-search').value = '';
        $('cd-add-member-selected').style.display = 'none';
        $('cd-member-results').classList.remove('active');
    }

    /* ── Global action object ── */
    window.cdHH = {
        toggle: function(id) {
            var row = $('cd-detail-' + id);
            if (!row) return;
            if (state.expandedId == id) {
                row.style.display = 'none';
                state.expandedId = null;
            } else {
                // Collapse any other open row
                if (state.expandedId) {
                    var prev = $('cd-detail-' + state.expandedId);
                    if (prev) prev.style.display = 'none';
                }
                row.style.display = 'table-row';
                state.expandedId = id;
                loadHouseholdDetail(id);
            }
        },

        edit: function(id) {
            var h = cache[id];
            if (!h) return;
            var newName = prompt('Household Name:', h.name);
            if (newName === null) return;
            var newAddr = prompt('Primary Address:', h.primary_address || '');
            if (newAddr === null) return;

            apiFetch(API + '/households/' + id, 'PUT', { name: newName, primary_address: newAddr }).then(function(json) {
                if (json.success) {
                    showNotice('Household updated.', 'success');
                    loadList();
                    loadHouseholdDetail(id);
                } else {
                    showNotice(json.error ? json.error.message : 'Error', 'error');
                }
            });
        },

        deactivate: function(id) {
            if (!confirm('Are you sure you want to deactivate this household?')) return;
            apiFetch(API + '/households/' + id, 'PUT', { status: 'inactive' }).then(function(json) {
                if (json.success) { showNotice('Household deactivated.', 'success'); state.expandedId = null; loadList(); }
                else showNotice(json.error ? json.error.message : 'Error', 'error');
            });
        },

        openAddMember: function(id) {
            state.addMemberHouseholdId = id;
            $('cd-modal-add-member').classList.add('active');
            $('cd-add-member-search').focus();
        },

        changeRole: function(householdId, memberId, newRole) {
            apiFetch(API + '/households/' + householdId + '/members/' + memberId, 'PUT', { role: newRole }).then(function(json) {
                if (json.success) {
                    showNotice('Role updated.', 'success');
                    loadHouseholdDetail(householdId);
                } else {
                    showNotice(json.error ? json.error.message : 'Error', 'error');
                    loadHouseholdDetail(householdId); // revert UI
                }
            });
        },

        removeMember: function(householdId, memberId) {
            if (!confirm('Remove this member from the household?')) return;
            apiFetch(API + '/households/' + householdId + '/members/' + memberId, 'DELETE').then(function(json) {
                if (json.success) {
                    showNotice(json.data.message || 'Member removed.', 'success');
                    loadHouseholdDetail(householdId);
                    loadList();
                } else {
                    showNotice(json.error ? json.error.message : 'Error', 'error');
                }
            });
        }
    };

    /* ── Helpers ── */

    function apiFetch(url, method, body) {
        var opts = { headers: { 'X-WP-Nonce': NONCE } };
        if (method && method !== 'GET') {
            opts.method = method;
            opts.headers['Content-Type'] = 'application/json';
            if (body) opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function(r) { return r.json(); }).catch(function(err) {
            showNotice('Network error: ' + err.message, 'error');
            return { success: false };
        });
    }

    function showNotice(msg, type) {
        elNotice.className = 'notice notice-' + (type || 'success') + ' is-dismissible';
        elNoticeText.textContent = msg;
        elNotice.style.display = '';
        setTimeout(function() { elNotice.style.display = 'none'; }, 5000);
    }

    function esc(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }

    function formatDate(d) {
        if (!d) return '—';
        var dt = new Date(d);
        return isNaN(dt.getTime()) ? d : dt.toLocaleDateString();
    }

    function formatRole(role) {
        var labels = { head: 'Head', spouse: 'Spouse', adult_child: 'Adult Child', child: 'Child', other: 'Other' };
        return labels[role] || role;
    }

    function updatePagination() {
        if (state.pages > 1) {
            var start = ((state.page - 1) * state.perPage) + 1;
            var end = Math.min(state.page * state.perPage, state.total);
            $('cd-pagination-info').textContent = start + '–' + end + ' of ' + state.total;
            $('cd-page-indicator').textContent = state.page + ' / ' + state.pages;
            $('cd-prev').disabled = (state.page <= 1);
            $('cd-next').disabled = (state.page >= state.pages);
            elPagination.style.display = '';
        }
    }
})();
</script>
