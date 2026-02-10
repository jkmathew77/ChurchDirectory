<?php
/**
 * Admin view: All Registrations
 *
 * Lists all applications including unverified ones.
 * Uses fetch() to call the REST API with WP nonce authentication.
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
.cd-status-tabs {
    display: flex;
    gap: 0;
    margin: 16px 0 0 0;
    border-bottom: 1px solid #c3c4c7;
    padding: 0;
    list-style: none;
}
.cd-status-tabs li {
    margin: 0;
}
.cd-status-tabs a {
    display: inline-block;
    padding: 8px 14px;
    text-decoration: none;
    color: #50575e;
    border: 1px solid transparent;
    border-bottom: none;
    margin-bottom: -1px;
    font-size: 13px;
    line-height: 1.4;
}
.cd-status-tabs a:hover {
    color: #135e96;
}
.cd-status-tabs a.current {
    background: #fff;
    border-color: #c3c4c7;
    border-bottom-color: #fff;
    color: #1d2327;
    font-weight: 600;
}
.cd-status-tabs .count {
    display: inline-block;
    background: #dcdcde;
    border-radius: 10px;
    padding: 0 7px;
    font-size: 11px;
    line-height: 18px;
    color: #50575e;
    margin-left: 4px;
    vertical-align: baseline;
}
.cd-status-tabs a.current .count {
    background: #2271b1;
    color: #fff;
}
.cd-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    line-height: 18px;
    white-space: nowrap;
}
.cd-badge-pending_verification {
    background: #fcf0e3;
    color: #9a6700;
}
.cd-badge-new {
    background: #e7f1fd;
    color: #1d4ed8;
}
.cd-badge-approved {
    background: #e6f6e6;
    color: #1a7a1a;
}
.cd-badge-not_approved {
    background: #fce4e4;
    color: #cc1818;
}
.cd-badge-archived {
    background: #f0f0f1;
    color: #646970;
}
.cd-badge-under_review {
    background: #fef3cd;
    color: #856404;
}
.cd-badge-on_hold {
    background: #fff3cd;
    color: #664d03;
}
.cd-loading {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}
.cd-loading .spinner {
    visibility: visible;
    float: none;
    margin: 0 8px 0 0;
    vertical-align: middle;
}
.cd-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
}
.cd-pagination-buttons {
    display: flex;
    gap: 4px;
    align-items: center;
}
.cd-pagination-buttons button {
    min-width: 30px;
}
.cd-pagination-info {
    color: #646970;
    font-size: 13px;
}
#cd-registrations-notice {
    display: none;
}
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'All Registrations', 'community-directory' ); ?></h1>
    <hr class="wp-header-end">

    <div id="cd-registrations-notice" class="notice" role="alert">
        <p id="cd-registrations-notice-text"></p>
    </div>

    <ul class="cd-status-tabs" id="cd-reg-tabs">
        <li><a href="#" data-status="all" class="current"><?php esc_html_e( 'All', 'community-directory' ); ?> <span class="count" id="cd-count-all">0</span></a></li>
        <li><a href="#" data-status="pending_verification"><?php esc_html_e( 'Pending Verification', 'community-directory' ); ?> <span class="count" id="cd-count-pending_verification">0</span></a></li>
        <li><a href="#" data-status="new"><?php esc_html_e( 'New (verified)', 'community-directory' ); ?> <span class="count" id="cd-count-new">0</span></a></li>
        <li><a href="#" data-status="approved"><?php esc_html_e( 'Approved', 'community-directory' ); ?> <span class="count" id="cd-count-approved">0</span></a></li>
        <li><a href="#" data-status="not_approved"><?php esc_html_e( 'Not Approved', 'community-directory' ); ?> <span class="count" id="cd-count-not_approved">0</span></a></li>
        <li><a href="#" data-status="archived"><?php esc_html_e( 'Archived', 'community-directory' ); ?> <span class="count" id="cd-count-archived">0</span></a></li>
    </ul>

    <div id="cd-reg-loading" class="cd-loading">
        <span class="spinner"></span> <?php esc_html_e( 'Loading registrations...', 'community-directory' ); ?>
    </div>

    <table class="widefat striped" id="cd-reg-table" style="display: none;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Email', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Status', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Submitted', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Verified', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
            </tr>
        </thead>
        <tbody id="cd-reg-tbody">
        </tbody>
    </table>

    <div id="cd-reg-empty" style="display: none; padding: 20px; text-align: center; color: #646970;">
        <?php esc_html_e( 'No registrations found.', 'community-directory' ); ?>
    </div>

    <div id="cd-reg-pagination" class="cd-pagination" style="display: none;">
        <span class="cd-pagination-info" id="cd-reg-pagination-info"></span>
        <span class="cd-pagination-buttons">
            <button type="button" class="button" id="cd-reg-prev" disabled>&laquo; <?php esc_html_e( 'Previous', 'community-directory' ); ?></button>
            <span id="cd-reg-page-indicator" style="padding: 0 8px; font-size: 13px;"></span>
            <button type="button" class="button" id="cd-reg-next" disabled><?php esc_html_e( 'Next', 'community-directory' ); ?> &raquo;</button>
        </span>
    </div>
</div>

<script>
(function() {
    'use strict';

    var API_BASE = <?php echo wp_json_encode( $api_base ); ?>;
    var NONCE    = <?php echo wp_json_encode( $nonce ); ?>;

    var state = {
        status: 'all',
        page: 1,
        perPage: 20,
        total: 0,
        pages: 0
    };

    var statusLabels = {
        pending_verification: <?php echo wp_json_encode( __( 'Pending Verification', 'community-directory' ) ); ?>,
        'new':               <?php echo wp_json_encode( __( 'New', 'community-directory' ) ); ?>,
        approved:            <?php echo wp_json_encode( __( 'Approved', 'community-directory' ) ); ?>,
        not_approved:        <?php echo wp_json_encode( __( 'Not Approved', 'community-directory' ) ); ?>,
        archived:            <?php echo wp_json_encode( __( 'Archived', 'community-directory' ) ); ?>,
        under_review:        <?php echo wp_json_encode( __( 'Under Review', 'community-directory' ) ); ?>,
        on_hold:             <?php echo wp_json_encode( __( 'On Hold', 'community-directory' ) ); ?>
    };

    /* ---- DOM refs ---- */
    var elLoading    = document.getElementById('cd-reg-loading');
    var elTable      = document.getElementById('cd-reg-table');
    var elTbody      = document.getElementById('cd-reg-tbody');
    var elEmpty      = document.getElementById('cd-reg-empty');
    var elPagination = document.getElementById('cd-reg-pagination');
    var elPageInfo   = document.getElementById('cd-reg-pagination-info');
    var elPageInd    = document.getElementById('cd-reg-page-indicator');
    var elBtnPrev    = document.getElementById('cd-reg-prev');
    var elBtnNext    = document.getElementById('cd-reg-next');
    var elNotice     = document.getElementById('cd-registrations-notice');
    var elNoticeText = document.getElementById('cd-registrations-notice-text');

    /* ---- Helpers ---- */
    function showNotice(message, type) {
        elNotice.className = 'notice notice-' + (type || 'success') + ' is-dismissible';
        elNoticeText.textContent = message;
        elNotice.style.display = '';
        setTimeout(function() { elNotice.style.display = 'none'; }, 6000);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    /* ---- Tab clicks ---- */
    document.getElementById('cd-reg-tabs').addEventListener('click', function(e) {
        var a = e.target.closest('a[data-status]');
        if (!a) return;
        e.preventDefault();
        var links = this.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) links[i].classList.remove('current');
        a.classList.add('current');
        state.status = a.getAttribute('data-status');
        state.page = 1;
        loadRegistrations();
    });

    /* ---- Pagination ---- */
    elBtnPrev.addEventListener('click', function() {
        if (state.page > 1) { state.page--; loadRegistrations(); }
    });
    elBtnNext.addEventListener('click', function() {
        if (state.page < state.pages) { state.page++; loadRegistrations(); }
    });

    /* ---- Load data ---- */
    function loadRegistrations() {
        elLoading.style.display = '';
        elTable.style.display = 'none';
        elEmpty.style.display = 'none';
        elPagination.style.display = 'none';

        var url = API_BASE + '/admin/registrations?page=' + state.page + '&per_page=' + state.perPage;
        if (state.status && state.status !== 'all') {
            url += '&status=' + encodeURIComponent(state.status);
        }

        fetch(url, {
            method: 'GET',
            headers: { 'X-WP-Nonce': NONCE },
            credentials: 'same-origin'
        })
        .then(function(resp) { return resp.json(); })
        .then(function(json) {
            elLoading.style.display = 'none';

            if (!json.success) {
                showNotice(json.error ? json.error.message : 'Failed to load registrations.', 'error');
                return;
            }

            var rows   = json.data.registrations || [];
            var counts = json.data.counts || {};
            var meta   = json.meta || {};

            state.total = meta.total || 0;
            state.pages = meta.pages || 0;

            /* Update tab counts */
            var countKeys = ['all', 'pending_verification', 'new', 'approved', 'not_approved', 'archived'];
            for (var i = 0; i < countKeys.length; i++) {
                var el = document.getElementById('cd-count-' + countKeys[i]);
                if (el) el.textContent = counts[countKeys[i]] || 0;
            }

            /* Render rows */
            if (rows.length === 0) {
                elEmpty.style.display = '';
                return;
            }

            var html = '';
            for (var r = 0; r < rows.length; r++) {
                var row = rows[r];
                var name = escapeHtml((row.first_name || '') + ' ' + (row.last_name || '')).trim() || '—';
                var badge = '<span class="cd-badge cd-badge-' + escapeHtml(row.status) + '">' + escapeHtml(statusLabels[row.status] || row.status) + '</span>';
                var verified = row.verified_at ? formatDate(row.verified_at) : '—';
                
                // Distinguish App vs Member Invite
                var isMemberInvite = (row.type === 'member_invite');
                if (isMemberInvite) {
                    badge += ' <small style="display:block;margin-top:2px;font-size:10px;opacity:0.8;color:inherit;">Invited Member</small>';
                }

                var actions = '';
                if (row.status === 'pending_verification') {
                    if (isMemberInvite) {
                         actions = '<button type="button" class="button button-small cd-resend-btn" data-id="' + row.id + '" data-type="member_invite">' +
                                  escapeHtml(<?php echo wp_json_encode( __( 'Resend Invite', 'community-directory' ) ); ?>) + '</button>';
                    } else {
                         actions = '<button type="button" class="button button-small cd-resend-btn" data-id="' + row.id + '" data-type="application">' +
                                  escapeHtml(<?php echo wp_json_encode( __( 'Resend Verification', 'community-directory' ) ); ?>) + '</button>';
                    }
                }

                html += '<tr>' +
                    '<td>' + name + '</td>' +
                    '<td>' + escapeHtml(row.email) + '</td>' +
                    '<td>' + badge + '</td>' +
                    '<td>' + formatDate(row.submitted_at) + '</td>' +
                    '<td>' + verified + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
            }
            elTbody.innerHTML = html;
            elTable.style.display = '';

            /* Pagination */
            if (state.pages > 1) {
                var start = ((state.page - 1) * state.perPage) + 1;
                var end   = Math.min(state.page * state.perPage, state.total);
                elPageInfo.textContent = start + '–' + end + ' of ' + state.total;
                elPageInd.textContent  = state.page + ' / ' + state.pages;
                elBtnPrev.disabled = (state.page <= 1);
                elBtnNext.disabled = (state.page >= state.pages);
                elPagination.style.display = '';
            }
        })
        .catch(function(err) {
            elLoading.style.display = 'none';
            showNotice('Network error: ' + err.message, 'error');
        });
    }

    /* ---- Resend verification ---- */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.cd-resend-btn');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var type = btn.getAttribute('data-type') || 'application'; // Default to app
        
        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Sending...', 'community-directory' ) ); ?>;

        // Route based on type
        var endpoint = (type === 'member_invite') 
            ? '/admin/members/' + id + '/resend-invite'
            : '/admin/registrations/' + id + '/resend-verification';

        fetch(API_BASE + endpoint, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': NONCE,
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: '{}'
        })
        .then(function(resp) { return resp.json(); })
        .then(function(json) {
            if (json.success) {
                showNotice(json.data.message || 'Email resent.', 'success');
            } else {
                showNotice(json.error ? json.error.message : 'Failed to resend.', 'error');
            }
            btn.disabled = false;
            // Restore original text logic? Simplified:
            btn.textContent = (type === 'member_invite') 
                ? <?php echo wp_json_encode( __( 'Resend Invite', 'community-directory' ) ); ?>
                : <?php echo wp_json_encode( __( 'Resend Verification', 'community-directory' ) ); ?>;
        })
        .catch(function(err) {
            showNotice('Network error: ' + err.message, 'error');
            btn.disabled = false;
            btn.textContent = (type === 'member_invite') 
                ? <?php echo wp_json_encode( __( 'Resend Invite', 'community-directory' ) ); ?>
                : <?php echo wp_json_encode( __( 'Resend Verification', 'community-directory' ) ); ?>;
        });
    });

    /* ---- Init ---- */
    loadRegistrations();
})();
</script>
