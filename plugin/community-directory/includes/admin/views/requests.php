<?php
/**
 * Admin view: Requests
 *
 * Combines Household Merge Requests and Account Deletion Requests
 * into a single tabbed interface.
 *
 * @package CommunityDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_base = esc_url( rest_url( CD_API_NAMESPACE ) );
$nonce    = wp_create_nonce( 'wp_rest' );
?>

<style>
.cd-requests-notice { display: none; }
.cd-main-tabs {
    display: flex; gap: 0; margin: 10px 0 0 0; padding: 0; list-style: none;
}
.cd-main-tabs li { margin: 0; }
.cd-main-tabs a {
    display: inline-block; padding: 10px 18px; text-decoration: none;
    color: #50575e; font-size: 14px; font-weight: 500;
    border: 1px solid transparent; border-bottom: none; margin-bottom: -1px;
    background: transparent; border-radius: 4px 4px 0 0;
}
.cd-main-tabs a:hover { color: #135e96; background: #f6f7f7; }
.cd-main-tabs a.current {
    background: #fff; border-color: #c3c4c7; border-bottom-color: #fff;
    color: #1d2327; font-weight: 600;
}
.cd-main-tabs .count {
    display: inline-block; background: #dcdcde; border-radius: 10px;
    padding: 0 7px; font-size: 11px; line-height: 18px; color: #50575e;
    margin-left: 4px; vertical-align: baseline;
}
.cd-main-tabs a.current .count { background: #2271b1; color: #fff; }
.cd-section { border-top: 1px solid #c3c4c7; padding-top: 16px; display: none; }
.cd-section.active { display: block; }
.cd-status-tabs {
    display: flex; gap: 0; margin: 0 0 12px 0; padding: 0; list-style: none;
    border-bottom: 1px solid #dcdcde;
}
.cd-status-tabs li { margin: 0; }
.cd-status-tabs a {
    display: inline-block; padding: 6px 12px; text-decoration: none;
    color: #50575e; font-size: 12px; border: 1px solid transparent;
    border-bottom: none; margin-bottom: -1px;
}
.cd-status-tabs a:hover { color: #135e96; }
.cd-status-tabs a.current {
    background: #fff; border-color: #dcdcde; border-bottom-color: #fff;
    color: #1d2327; font-weight: 600;
}
.cd-status-tabs .count {
    display: inline-block; background: #f0f0f1; border-radius: 8px;
    padding: 0 6px; font-size: 10px; line-height: 16px; color: #646970;
    margin-left: 3px;
}
.cd-status-tabs a.current .count { background: #2271b1; color: #fff; }
.cd-badge {
    display: inline-block; padding: 2px 10px; border-radius: 12px;
    font-size: 12px; font-weight: 500; line-height: 18px; white-space: nowrap;
}
.cd-badge-pending { background: #fcf0e3; color: #9a6700; }
.cd-badge-approved { background: #e6f6e6; color: #1a7a1a; }
.cd-badge-rejected, .cd-badge-denied { background: #fce4e4; color: #cc1818; }
.cd-loading {
    text-align: center; padding: 30px 20px; color: #646970;
}
.cd-loading .spinner { visibility: visible; float: none; margin: 0 8px 0 0; vertical-align: middle; }
.cd-actions-cell { white-space: nowrap; }
.cd-actions-cell .button { margin-right: 4px; }
.cd-notes-input {
    width: 100%; max-width: 300px; margin-top: 4px; font-size: 12px;
    padding: 4px 8px; display: none;
}
.cd-household-info { font-size: 12px; color: #646970; }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Requests', 'community-directory' ); ?></h1>
    <hr class="wp-header-end">

    <div id="cd-requests-notice" class="cd-requests-notice notice" role="alert">
        <p id="cd-requests-notice-text"></p>
    </div>

    <!-- Main Tabs: Merges vs Deletions -->
    <ul class="cd-main-tabs" id="cd-main-tabs">
        <li><a href="#" data-section="merges" class="current">
            <?php esc_html_e( 'Household Merges', 'community-directory' ); ?>
            <span class="count" id="cd-merge-total-count">0</span>
        </a></li>
        <li><a href="#" data-section="deletions">
            <?php esc_html_e( 'Account Deletions', 'community-directory' ); ?>
            <span class="count" id="cd-deletion-total-count">0</span>
        </a></li>
    </ul>

    <!-- ============ MERGE REQUESTS SECTION ============ -->
    <div id="cd-section-merges" class="cd-section active">
        <ul class="cd-status-tabs" id="cd-merge-tabs">
            <li><a href="#" data-status="pending" class="current"><?php esc_html_e( 'Pending', 'community-directory' ); ?> <span class="count" id="cd-merge-count-pending">0</span></a></li>
            <li><a href="#" data-status="approved"><?php esc_html_e( 'Approved', 'community-directory' ); ?> <span class="count" id="cd-merge-count-approved">0</span></a></li>
            <li><a href="#" data-status="rejected"><?php esc_html_e( 'Denied', 'community-directory' ); ?> <span class="count" id="cd-merge-count-rejected">0</span></a></li>
            <li><a href="#" data-status="all"><?php esc_html_e( 'All', 'community-directory' ); ?> <span class="count" id="cd-merge-count-all">0</span></a></li>
        </ul>

        <div id="cd-merge-loading" class="cd-loading">
            <span class="spinner"></span> <?php esc_html_e( 'Loading merge requests...', 'community-directory' ); ?>
        </div>

        <table class="widefat striped" id="cd-merge-table" style="display: none;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Requested By', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Source Household', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Target Household', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
                </tr>
            </thead>
            <tbody id="cd-merge-tbody"></tbody>
        </table>

        <div id="cd-merge-empty" style="display: none; padding: 20px; text-align: center; color: #646970;">
            <?php esc_html_e( 'No merge requests found.', 'community-directory' ); ?>
        </div>
    </div>

    <!-- ============ DELETION REQUESTS SECTION ============ -->
    <div id="cd-section-deletions" class="cd-section">
        <ul class="cd-status-tabs" id="cd-deletion-tabs">
            <li><a href="#" data-status="pending" class="current"><?php esc_html_e( 'Pending', 'community-directory' ); ?> <span class="count" id="cd-del-count-pending">0</span></a></li>
            <li><a href="#" data-status="approved"><?php esc_html_e( 'Processed', 'community-directory' ); ?> <span class="count" id="cd-del-count-approved">0</span></a></li>
            <li><a href="#" data-status="denied"><?php esc_html_e( 'Denied', 'community-directory' ); ?> <span class="count" id="cd-del-count-denied">0</span></a></li>
            <li><a href="#" data-status="all"><?php esc_html_e( 'All', 'community-directory' ); ?> <span class="count" id="cd-del-count-all">0</span></a></li>
        </ul>

        <div id="cd-del-loading" class="cd-loading">
            <span class="spinner"></span> <?php esc_html_e( 'Loading deletion requests...', 'community-directory' ); ?>
        </div>

        <table class="widefat striped" id="cd-del-table" style="display: none;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Member', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Reason', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Household Impact', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
                </tr>
            </thead>
            <tbody id="cd-del-tbody"></tbody>
        </table>

        <div id="cd-del-empty" style="display: none; padding: 20px; text-align: center; color: #646970;">
            <?php esc_html_e( 'No deletion requests found.', 'community-directory' ); ?>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var API  = <?php echo wp_json_encode( $api_base ); ?>;
    var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

    /* ---- Helpers ---- */
    function escHtml(t) {
        if (!t) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(t));
        return d.innerHTML;
    }

    function fmtDate(s) {
        if (!s) return '—';
        var d = new Date(s.replace(' ', 'T'));
        return isNaN(d.getTime()) ? s : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function apiFetch(method, path, body) {
        var opts = {
            method: method,
            headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(API + path, opts).then(function(r) { return r.json(); });
    }

    function showNotice(msg, type) {
        var el  = document.getElementById('cd-requests-notice');
        var txt = document.getElementById('cd-requests-notice-text');
        el.className = 'notice notice-' + (type || 'success') + ' is-dismissible';
        txt.textContent = msg;
        el.style.display = '';
        setTimeout(function() { el.style.display = 'none'; }, 6000);
    }

    function badge(status) {
        var labels = { pending: 'Pending', approved: 'Approved', rejected: 'Denied', denied: 'Denied' };
        return '<span class="cd-badge cd-badge-' + escHtml(status) + '">' + escHtml(labels[status] || status) + '</span>';
    }

    /* ---- Main tab switching ---- */
    var currentSection = 'merges';
    document.getElementById('cd-main-tabs').addEventListener('click', function(e) {
        var a = e.target.closest('a[data-section]');
        if (!a) return;
        e.preventDefault();
        var links = this.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) links[i].classList.remove('current');
        a.classList.add('current');

        currentSection = a.getAttribute('data-section');
        document.getElementById('cd-section-merges').className = 'cd-section' + (currentSection === 'merges' ? ' active' : '');
        document.getElementById('cd-section-deletions').className = 'cd-section' + (currentSection === 'deletions' ? ' active' : '');
    });

    /* ============================================================
       MERGE REQUESTS
       ============================================================ */
    var mergeStatus = 'pending';

    document.getElementById('cd-merge-tabs').addEventListener('click', function(e) {
        var a = e.target.closest('a[data-status]');
        if (!a) return;
        e.preventDefault();
        var links = this.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) links[i].classList.remove('current');
        a.classList.add('current');
        mergeStatus = a.getAttribute('data-status');
        loadMerges();
    });

    function loadMerges() {
        var elLoad  = document.getElementById('cd-merge-loading');
        var elTable = document.getElementById('cd-merge-table');
        var elTbody = document.getElementById('cd-merge-tbody');
        var elEmpty = document.getElementById('cd-merge-empty');

        elLoad.style.display = '';
        elTable.style.display = 'none';
        elEmpty.style.display = 'none';

        var path = '/admin/household-requests?status=' + encodeURIComponent(mergeStatus);
        apiFetch('GET', path).then(function(json) {
            elLoad.style.display = 'none';

            if (!json.success) {
                showNotice(json.error ? json.error.message : 'Failed to load merge requests.', 'error');
                return;
            }

            var rows   = json.data.requests || [];
            var counts = json.data.counts || {};

            // Update counts
            var total = (counts.pending || 0) + (counts.approved || 0) + (counts.rejected || 0);
            setText('cd-merge-count-pending', counts.pending || 0);
            setText('cd-merge-count-approved', counts.approved || 0);
            setText('cd-merge-count-rejected', counts.rejected || 0);
            setText('cd-merge-count-all', total);
            setText('cd-merge-total-count', counts.pending || 0);

            if (rows.length === 0) {
                elEmpty.style.display = '';
                return;
            }

            var html = '';
            for (var i = 0; i < rows.length; i++) {
                var r = rows[i];
                var requester = escHtml(r.requester_name || '—');
                var source = escHtml(r.source_household_name || '—');
                var target = escHtml(r.target_household_name || '—');
                var sourceCt = r.source_member_count ? ' <span class="cd-household-info">(' + r.source_member_count + ' members)</span>' : '';
                var targetCt = r.target_member_count ? ' <span class="cd-household-info">(' + r.target_member_count + ' members)</span>' : '';

                var actions = '';
                if (r.status === 'pending') {
                    actions =
                        '<div class="cd-actions-cell">' +
                            '<button type="button" class="button button-primary button-small cd-merge-action" data-id="' + r.id + '" data-action="approve">' +
                                escHtml(<?php echo wp_json_encode( __( 'Approve', 'community-directory' ) ); ?>) +
                            '</button>' +
                            '<button type="button" class="button button-small cd-merge-action" data-id="' + r.id + '" data-action="deny">' +
                                escHtml(<?php echo wp_json_encode( __( 'Deny', 'community-directory' ) ); ?>) +
                            '</button>' +
                            '<input type="text" class="cd-notes-input" id="cd-merge-notes-' + r.id + '" placeholder="' +
                                escHtml(<?php echo wp_json_encode( __( 'Optional notes...', 'community-directory' ) ); ?>) + '">' +
                        '</div>';
                } else {
                    var notes = r.notes ? '<br><small>' + escHtml(r.notes) + '</small>' : '';
                    actions = '<span class="cd-household-info">' +
                        (r.reviewed_by_name ? escHtml(<?php echo wp_json_encode( __( 'By ', 'community-directory' ) ); ?>) + escHtml(r.reviewed_by_name) : '') +
                        notes + '</span>';
                }

                html += '<tr>' +
                    '<td>' + requester + '</td>' +
                    '<td>' + source + sourceCt + '</td>' +
                    '<td>' + target + targetCt + '</td>' +
                    '<td>' + badge(r.status) + '</td>' +
                    '<td>' + fmtDate(r.created_at) + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
            }

            elTbody.innerHTML = html;
            elTable.style.display = '';
        }).catch(function(err) {
            elLoad.style.display = 'none';
            showNotice('Network error: ' + err.message, 'error');
        });
    }

    /* Merge action buttons (approve/deny) */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.cd-merge-action');
        if (!btn) return;

        var id     = btn.getAttribute('data-id');
        var action = btn.getAttribute('data-action');

        // Show notes input on first click of deny
        var notesEl = document.getElementById('cd-merge-notes-' + id);
        if (notesEl && notesEl.style.display === 'none') {
            notesEl.style.display = '';
            if (action === 'deny') { notesEl.focus(); return; }
        }

        var notes = notesEl ? notesEl.value : '';
        var confirmMsg = action === 'approve'
            ? <?php echo wp_json_encode( __( 'Approve this merge? All members will be moved from source to target household.', 'community-directory' ) ); ?>
            : <?php echo wp_json_encode( __( 'Deny this merge request?', 'community-directory' ) ); ?>;

        if (!confirm(confirmMsg)) return;

        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Processing...', 'community-directory' ) ); ?>;

        apiFetch('PUT', '/admin/household-requests/' + id, {
            action: action,
            notes: notes
        }).then(function(json) {
            if (json.success) {
                showNotice(json.data.message || 'Request updated.', 'success');
                loadMerges();
            } else {
                showNotice(json.error ? json.error.message : 'Failed.', 'error');
                btn.disabled = false;
                btn.textContent = action === 'approve' ? 'Approve' : 'Deny';
            }
        }).catch(function(err) {
            showNotice('Network error: ' + err.message, 'error');
            btn.disabled = false;
            btn.textContent = action === 'approve' ? 'Approve' : 'Deny';
        });
    });

    /* ============================================================
       DELETION REQUESTS
       ============================================================ */
    var delStatus = 'pending';

    document.getElementById('cd-deletion-tabs').addEventListener('click', function(e) {
        var a = e.target.closest('a[data-status]');
        if (!a) return;
        e.preventDefault();
        var links = this.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) links[i].classList.remove('current');
        a.classList.add('current');
        delStatus = a.getAttribute('data-status');
        loadDeletions();
    });

    function loadDeletions() {
        var elLoad  = document.getElementById('cd-del-loading');
        var elTable = document.getElementById('cd-del-table');
        var elTbody = document.getElementById('cd-del-tbody');
        var elEmpty = document.getElementById('cd-del-empty');

        elLoad.style.display = '';
        elTable.style.display = 'none';
        elEmpty.style.display = 'none';

        var path = '/admin/deletion-requests?status=' + encodeURIComponent(delStatus);
        apiFetch('GET', path).then(function(json) {
            elLoad.style.display = 'none';

            if (!json.success) {
                showNotice(json.error ? json.error.message : 'Failed to load deletion requests.', 'error');
                return;
            }

            var rows   = json.data.requests || [];
            var counts = json.data.counts || {};

            // Update counts
            var total = (counts.pending || 0) + (counts.approved || 0) + (counts.denied || 0);
            setText('cd-del-count-pending', counts.pending || 0);
            setText('cd-del-count-approved', counts.approved || 0);
            setText('cd-del-count-denied', counts.denied || 0);
            setText('cd-del-count-all', total);
            setText('cd-deletion-total-count', counts.pending || 0);

            if (rows.length === 0) {
                elEmpty.style.display = '';
                return;
            }

            var html = '';
            for (var i = 0; i < rows.length; i++) {
                var r = rows[i];
                var name = escHtml((r.first_name || '') + ' ' + (r.last_name || '')).trim() || '—';
                var email = escHtml(r.email || '—');
                var reason = escHtml(r.reason || '—');

                // Household impact
                var impact = '—';
                if (r.household_name) {
                    impact = escHtml(r.household_name);
                    if (r.household_role === 'head') {
                        impact += ' <span class="cd-badge cd-badge-pending" style="font-size:10px;">' +
                            escHtml(<?php echo wp_json_encode( __( 'Head — will auto-promote spouse', 'community-directory' ) ); ?>) +
                            '</span>';
                    } else if (r.household_role) {
                        impact += ' <span class="cd-household-info">(' + escHtml(r.household_role) + ')</span>';
                    }
                }

                var actions = '';
                if (r.status === 'pending') {
                    actions =
                        '<div class="cd-actions-cell">' +
                            '<button type="button" class="button button-primary button-small cd-del-action" data-id="' + r.id + '" data-action="approve">' +
                                escHtml(<?php echo wp_json_encode( __( 'Process', 'community-directory' ) ); ?>) +
                            '</button>' +
                            '<button type="button" class="button button-small cd-del-action" data-id="' + r.id + '" data-action="deny">' +
                                escHtml(<?php echo wp_json_encode( __( 'Deny', 'community-directory' ) ); ?>) +
                            '</button>' +
                        '</div>';
                } else {
                    var statusLabel = r.status === 'approved' ? <?php echo wp_json_encode( __( 'Processed', 'community-directory' ) ); ?> : <?php echo wp_json_encode( __( 'Denied', 'community-directory' ) ); ?>;
                    actions = '<span class="cd-household-info">' + escHtml(statusLabel) +
                        (r.acknowledged_by_name ? ' ' + escHtml(<?php echo wp_json_encode( __( 'by ', 'community-directory' ) ); ?>) + escHtml(r.acknowledged_by_name) : '') +
                        '</span>';
                }

                html += '<tr>' +
                    '<td>' + name + '</td>' +
                    '<td>' + email + '</td>' +
                    '<td>' + reason + '</td>' +
                    '<td>' + impact + '</td>' +
                    '<td>' + badge(r.status) + '</td>' +
                    '<td>' + fmtDate(r.requested_at) + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
            }

            elTbody.innerHTML = html;
            elTable.style.display = '';
        }).catch(function(err) {
            elLoad.style.display = 'none';
            showNotice('Network error: ' + err.message, 'error');
        });
    }

    /* Deletion action buttons (process/deny) */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.cd-del-action');
        if (!btn) return;

        var id     = btn.getAttribute('data-id');
        var action = btn.getAttribute('data-action');

        var confirmMsg = action === 'approve'
            ? <?php echo wp_json_encode( __( 'Process this deletion? The member will be deactivated and removed from any household.', 'community-directory' ) ); ?>
            : <?php echo wp_json_encode( __( 'Deny this deletion request?', 'community-directory' ) ); ?>;

        if (!confirm(confirmMsg)) return;

        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Processing...', 'community-directory' ) ); ?>;

        apiFetch('PUT', '/admin/deletion-requests/' + id, {
            action: action
        }).then(function(json) {
            if (json.success) {
                showNotice(json.data.message || 'Request updated.', 'success');
                loadDeletions();
            } else {
                showNotice(json.error ? json.error.message : 'Failed.', 'error');
                btn.disabled = false;
                btn.textContent = action === 'approve' ? 'Process' : 'Deny';
            }
        }).catch(function(err) {
            showNotice('Network error: ' + err.message, 'error');
            btn.disabled = false;
            btn.textContent = action === 'approve' ? 'Process' : 'Deny';
        });
    });

    /* ---- Utility ---- */
    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    /* ---- Init ---- */
    loadMerges();
    loadDeletions();
})();
</script>
