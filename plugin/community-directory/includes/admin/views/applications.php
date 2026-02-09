<?php
/**
 * Admin view: Application Review
 *
 * Lists verified applications for officer review with approve/reject/hold workflow.
 * Uses fetch() to call the REST API with WP nonce authentication.
 *
 * @package CommunityDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_base     = esc_url( rest_url( CD_API_NAMESPACE ) );
$nonce        = wp_create_nonce( 'wp_rest' );
$current_user = wp_get_current_user();
?>

<style>
/* --- Tab styles --- */
.cd-status-tabs {
    display: flex;
    gap: 0;
    margin: 16px 0 0 0;
    border-bottom: 1px solid #c3c4c7;
    padding: 0;
    list-style: none;
}
.cd-status-tabs li { margin: 0; }
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
.cd-status-tabs a:hover { color: #135e96; }
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
}
.cd-status-tabs a.current .count {
    background: #2271b1;
    color: #fff;
}

/* --- Badge styles --- */
.cd-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    line-height: 18px;
    white-space: nowrap;
}
.cd-badge-new { background: #e7f1fd; color: #1d4ed8; }
.cd-badge-under_review { background: #fef3cd; color: #856404; }
.cd-badge-on_hold { background: #fff3cd; color: #664d03; }
.cd-badge-approved { background: #e6f6e6; color: #1a7a1a; }
.cd-badge-not_approved { background: #fce4e4; color: #cc1818; }

/* --- Loading --- */
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

/* --- Pagination --- */
.cd-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
}
.cd-pagination-buttons { display: flex; gap: 4px; align-items: center; }
.cd-pagination-buttons button { min-width: 30px; }
.cd-pagination-info { color: #646970; font-size: 13px; }

/* --- Detail panel --- */
.cd-detail-row td {
    padding: 0 !important;
    background: #f9f9f9;
}
.cd-detail-panel {
    padding: 16px 24px;
    border-top: 1px solid #e0e0e0;
}
.cd-detail-panel h4 {
    margin: 16px 0 8px 0;
    font-size: 13px;
    color: #1d2327;
}
.cd-detail-panel h4:first-child { margin-top: 0; }
.cd-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 8px 24px;
}
.cd-detail-field { font-size: 13px; }
.cd-detail-field .cd-label {
    font-weight: 600;
    color: #50575e;
    display: inline;
}
.cd-detail-field .cd-value { color: #1d2327; display: inline; }
.cd-detail-notes {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    padding: 10px 12px;
    font-size: 13px;
    margin-top: 4px;
    white-space: pre-wrap;
}
.cd-church-use {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    padding: 12px 16px;
    margin-top: 12px;
}
.cd-church-use h4 {
    margin-top: 0;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 8px;
}
.cd-toggle-details {
    cursor: pointer;
    color: #2271b1;
    text-decoration: none;
    font-size: 13px;
}
.cd-toggle-details:hover { text-decoration: underline; }

/* --- Action buttons --- */
.cd-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.cd-actions .button-approve { background: #00a32a; border-color: #00a32a; color: #fff; }
.cd-actions .button-approve:hover { background: #008a20; border-color: #008a20; color: #fff; }
.cd-actions .button-reject { background: #d63638; border-color: #d63638; color: #fff; }
.cd-actions .button-reject:hover { background: #b32d2e; border-color: #b32d2e; color: #fff; }
.cd-actions .button-hold { background: #dba617; border-color: #dba617; color: #fff; }
.cd-actions .button-hold:hover { background: #c09315; border-color: #c09315; color: #fff; }

/* --- Modal overlay --- */
.cd-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100100;
    justify-content: center;
    align-items: center;
}
.cd-modal-overlay.active { display: flex; }
.cd-modal {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.2);
    max-width: 520px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}
.cd-modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #dcdcde;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cd-modal-header h2 { margin: 0; font-size: 16px; }
.cd-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #646970;
    padding: 0 4px;
    line-height: 1;
}
.cd-modal-close:hover { color: #1d2327; }
.cd-modal-body { padding: 20px; }
.cd-modal-body label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; }
.cd-modal-body textarea,
.cd-modal-body select,
.cd-modal-body input[type="text"] { width: 100%; margin-bottom: 12px; }
.cd-modal-body textarea { min-height: 100px; }
.cd-modal-footer {
    padding: 12px 20px;
    border-top: 1px solid #dcdcde;
    text-align: right;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

#cd-app-notice { display: none; }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Application Review', 'community-directory' ); ?></h1>
    <hr class="wp-header-end">

    <div id="cd-app-notice" class="notice" role="alert">
        <p id="cd-app-notice-text"></p>
    </div>

    <ul class="cd-status-tabs" id="cd-app-tabs">
        <li><a href="#" data-status="all" class="current"><?php esc_html_e( 'All', 'community-directory' ); ?> <span class="count" id="cd-acount-all">0</span></a></li>
        <li><a href="#" data-status="new"><?php esc_html_e( 'New', 'community-directory' ); ?> <span class="count" id="cd-acount-new">0</span></a></li>
        <li><a href="#" data-status="under_review"><?php esc_html_e( 'Under Review', 'community-directory' ); ?> <span class="count" id="cd-acount-under_review">0</span></a></li>
        <li><a href="#" data-status="on_hold"><?php esc_html_e( 'On Hold', 'community-directory' ); ?> <span class="count" id="cd-acount-on_hold">0</span></a></li>
        <li><a href="#" data-status="approved"><?php esc_html_e( 'Approved', 'community-directory' ); ?> <span class="count" id="cd-acount-approved">0</span></a></li>
        <li><a href="#" data-status="not_approved"><?php esc_html_e( 'Not Approved', 'community-directory' ); ?> <span class="count" id="cd-acount-not_approved">0</span></a></li>
    </ul>

    <div id="cd-app-loading" class="cd-loading">
        <span class="spinner"></span> <?php esc_html_e( 'Loading applications...', 'community-directory' ); ?>
    </div>

    <table class="widefat striped" id="cd-app-table" style="display: none;">
        <thead>
            <tr>
                <th style="width: 30px;"></th>
                <th><?php esc_html_e( 'Name', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Email', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Phone', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Submitted', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Status', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
            </tr>
        </thead>
        <tbody id="cd-app-tbody">
        </tbody>
    </table>

    <div id="cd-app-empty" style="display: none; padding: 20px; text-align: center; color: #646970;">
        <?php esc_html_e( 'No applications found.', 'community-directory' ); ?>
    </div>

    <div id="cd-app-pagination" class="cd-pagination" style="display: none;">
        <span class="cd-pagination-info" id="cd-app-pagination-info"></span>
        <span class="cd-pagination-buttons">
            <button type="button" class="button" id="cd-app-prev" disabled>&laquo; <?php esc_html_e( 'Previous', 'community-directory' ); ?></button>
            <span id="cd-app-page-indicator" style="padding: 0 8px; font-size: 13px;"></span>
            <button type="button" class="button" id="cd-app-next" disabled><?php esc_html_e( 'Next', 'community-directory' ); ?> &raquo;</button>
        </span>
    </div>
</div>

<!-- Rejection Modal -->
<div class="cd-modal-overlay" id="cd-reject-modal">
    <div class="cd-modal">
        <div class="cd-modal-header">
            <h2><?php esc_html_e( 'Not Approved', 'community-directory' ); ?></h2>
            <button type="button" class="cd-modal-close" id="cd-reject-close">&times;</button>
        </div>
        <div class="cd-modal-body">
            <input type="hidden" id="cd-reject-app-id" value="">
            <label for="cd-reject-reason"><?php esc_html_e( 'Reason', 'community-directory' ); ?></label>
            <select id="cd-reject-reason">
                <option value="incomplete"><?php esc_html_e( 'Incomplete application', 'community-directory' ); ?></option>
                <option value="not_eligible"><?php esc_html_e( 'Not eligible', 'community-directory' ); ?></option>
                <option value="duplicate"><?php esc_html_e( 'Duplicate application', 'community-directory' ); ?></option>
                <option value="other"><?php esc_html_e( 'Other', 'community-directory' ); ?></option>
            </select>
            <label for="cd-reject-notes"><?php esc_html_e( 'Internal Notes', 'community-directory' ); ?></label>
            <textarea id="cd-reject-notes" rows="3" placeholder="<?php esc_attr_e( 'Optional internal notes...', 'community-directory' ); ?>"></textarea>
            <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; margin-top: 4px;">
                <input type="checkbox" id="cd-reject-send-email" checked>
                <?php esc_html_e( 'Send notification email to applicant', 'community-directory' ); ?>
            </label>
        </div>
        <div class="cd-modal-footer">
            <button type="button" class="button" id="cd-reject-cancel"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
            <button type="button" class="button button-reject" id="cd-reject-confirm" style="background:#d63638;border-color:#d63638;color:#fff;"><?php esc_html_e( 'Mark Not Approved', 'community-directory' ); ?></button>
        </div>
    </div>
</div>

<!-- Request Info Modal -->
<div class="cd-modal-overlay" id="cd-info-modal">
    <div class="cd-modal">
        <div class="cd-modal-header">
            <h2><?php esc_html_e( 'Request Information', 'community-directory' ); ?></h2>
            <button type="button" class="cd-modal-close" id="cd-info-close">&times;</button>
        </div>
        <div class="cd-modal-body">
            <input type="hidden" id="cd-info-app-id" value="">
            <label for="cd-info-message"><?php esc_html_e( 'Message to Applicant', 'community-directory' ); ?></label>
            <textarea id="cd-info-message" rows="5" placeholder="<?php esc_attr_e( 'Please provide the following information...', 'community-directory' ); ?>"></textarea>
        </div>
        <div class="cd-modal-footer">
            <button type="button" class="button" id="cd-info-cancel"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
            <button type="button" class="button button-primary" id="cd-info-confirm"><?php esc_html_e( 'Send Request', 'community-directory' ); ?></button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var API_BASE     = <?php echo wp_json_encode( $api_base ); ?>;
    var NONCE        = <?php echo wp_json_encode( $nonce ); ?>;
    var CURRENT_USER = <?php echo wp_json_encode( $current_user->display_name ); ?>;

    var state = { status: 'all', page: 1, perPage: 20, total: 0, pages: 0 };
    var appCache = {}; // id -> row data for detail panels

    var statusLabels = {
        'new':           <?php echo wp_json_encode( __( 'New', 'community-directory' ) ); ?>,
        under_review:    <?php echo wp_json_encode( __( 'Under Review', 'community-directory' ) ); ?>,
        on_hold:         <?php echo wp_json_encode( __( 'On Hold', 'community-directory' ) ); ?>,
        approved:        <?php echo wp_json_encode( __( 'Approved', 'community-directory' ) ); ?>,
        not_approved:    <?php echo wp_json_encode( __( 'Not Approved', 'community-directory' ) ); ?>
    };

    /* ---- DOM refs ---- */
    var elLoading    = document.getElementById('cd-app-loading');
    var elTable      = document.getElementById('cd-app-table');
    var elTbody      = document.getElementById('cd-app-tbody');
    var elEmpty      = document.getElementById('cd-app-empty');
    var elPagination = document.getElementById('cd-app-pagination');
    var elPageInfo   = document.getElementById('cd-app-pagination-info');
    var elPageInd    = document.getElementById('cd-app-page-indicator');
    var elBtnPrev    = document.getElementById('cd-app-prev');
    var elBtnNext    = document.getElementById('cd-app-next');
    var elNotice     = document.getElementById('cd-app-notice');
    var elNoticeText = document.getElementById('cd-app-notice-text');

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

    function esc(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }

    function apiCall(method, endpoint, body) {
        var opts = {
            method: method,
            headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(API_BASE + endpoint, opts).then(function(r) { return r.json(); });
    }

    /* ---- Tab clicks ---- */
    document.getElementById('cd-app-tabs').addEventListener('click', function(e) {
        var a = e.target.closest('a[data-status]');
        if (!a) return;
        e.preventDefault();
        var links = this.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) links[i].classList.remove('current');
        a.classList.add('current');
        state.status = a.getAttribute('data-status');
        state.page = 1;
        loadApplications();
    });

    /* ---- Pagination ---- */
    elBtnPrev.addEventListener('click', function() {
        if (state.page > 1) { state.page--; loadApplications(); }
    });
    elBtnNext.addEventListener('click', function() {
        if (state.page < state.pages) { state.page++; loadApplications(); }
    });

    /* ---- Build detail panel HTML ---- */
    function buildDetailPanel(row) {
        var fd = row.form_data || {};
        var html = '<div class="cd-detail-panel">';

        /* Application form data */
        html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Application Details', 'community-directory' ) ); ?>) + '</h4>';
        html += '<div class="cd-detail-grid">';

        var fields = [
            [<?php echo wp_json_encode( __( 'Full Name', 'community-directory' ) ); ?>, (esc(row.first_name) + ' ' + esc(row.last_name)).trim()],
            [<?php echo wp_json_encode( __( 'Email', 'community-directory' ) ); ?>, esc(row.email)],
            [<?php echo wp_json_encode( __( 'Phone', 'community-directory' ) ); ?>, esc(fd.phone || fd.cell_phone || '')],
            [<?php echo wp_json_encode( __( 'Address', 'community-directory' ) ); ?>, esc([fd.address_line_1, fd.address_line_2, fd.city, fd.state, fd.zip].filter(Boolean).join(', '))],
            [<?php echo wp_json_encode( __( 'Date of Birth', 'community-directory' ) ); ?>, esc(fd.date_of_birth || '')],
            [<?php echo wp_json_encode( __( 'Date of Baptism', 'community-directory' ) ); ?>, esc(fd.date_of_baptism || '')],
            [<?php echo wp_json_encode( __( 'Profession', 'community-directory' ) ); ?>, esc(fd.profession || '')],
            [<?php echo wp_json_encode( __( 'Prior Parishes', 'community-directory' ) ); ?>, esc(fd.prior_parishes || '')],
            [<?php echo wp_json_encode( __( 'Marital Status', 'community-directory' ) ); ?>, esc(fd.marital_status || '')]
        ];

        if (fd.marriage_date || fd.spouse_name) {
            fields.push([<?php echo wp_json_encode( __( 'Marriage Date', 'community-directory' ) ); ?>, esc(fd.marriage_date || '')]);
            fields.push([<?php echo wp_json_encode( __( 'Spouse Name', 'community-directory' ) ); ?>, esc(fd.spouse_name || '')]);
            if (fd.marriage_officiant) {
                fields.push([<?php echo wp_json_encode( __( 'Marriage Officiant', 'community-directory' ) ); ?>, esc(fd.marriage_officiant || '')]);
            }
        }

        for (var i = 0; i < fields.length; i++) {
            if (fields[i][1]) {
                html += '<div class="cd-detail-field"><span class="cd-label">' + fields[i][0] + ':</span> <span class="cd-value">' + fields[i][1] + '</span></div>';
            }
        }
        html += '</div>';

        /* Family members */
        var family = fd.family_members || fd.dependents || [];
        if (family.length) {
            html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Family Members', 'community-directory' ) ); ?>) + '</h4>';
            html += '<table class="widefat" style="max-width:600px;"><thead><tr><th>Name</th><th>Relationship</th><th>DOB</th></tr></thead><tbody>';
            for (var f = 0; f < family.length; f++) {
                var fm = family[f];
                html += '<tr><td>' + esc(fm.name || fm.first_name || '') + '</td><td>' + esc(fm.relationship || fm.relation || '') + '</td><td>' + esc(fm.date_of_birth || fm.dob || '') + '</td></tr>';
            }
            html += '</tbody></table>';
        }

        /* Ministry interests */
        var ministries = fd.ministry_interests || [];
        if (ministries.length) {
            html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Ministry Interests', 'community-directory' ) ); ?>) + '</h4>';
            html += '<p>';
            for (var m = 0; m < ministries.length; m++) {
                html += '<span style="display:inline-block;background:#e7f1fd;border-radius:4px;padding:2px 8px;margin:2px 4px 2px 0;font-size:12px;">' + esc(ministries[m]) + '</span>';
            }
            html += '</p>';
        }

        /* Internal notes */
        if (row.notes) {
            html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Internal Notes', 'community-directory' ) ); ?>) + '</h4>';
            html += '<div class="cd-detail-notes">' + esc(row.notes) + '</div>';
        }

        /* Review metadata */
        if (row.reviewed_by || row.reviewed_at) {
            html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Review History', 'community-directory' ) ); ?>) + '</h4>';
            html += '<div class="cd-detail-grid">';
            if (row.reviewer_name) {
                html += '<div class="cd-detail-field"><span class="cd-label">' + esc(<?php echo wp_json_encode( __( 'Reviewed by', 'community-directory' ) ); ?>) + ':</span> <span class="cd-value">' + esc(row.reviewer_name) + '</span></div>';
            }
            if (row.reviewed_at) {
                html += '<div class="cd-detail-field"><span class="cd-label">' + esc(<?php echo wp_json_encode( __( 'Reviewed at', 'community-directory' ) ); ?>) + ':</span> <span class="cd-value">' + formatDate(row.reviewed_at) + '</span></div>';
            }
            if (row.rejection_reason) {
                html += '<div class="cd-detail-field"><span class="cd-label">' + esc(<?php echo wp_json_encode( __( 'Rejection reason', 'community-directory' ) ); ?>) + ':</span> <span class="cd-value">' + esc(row.rejection_reason) + '</span></div>';
            }
            html += '</div>';
        }

        /* Church Use Only section (shown for approved or ready-to-approve) */
        if (row.status === 'approved') {
            html += '<div class="cd-church-use">';
            html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Church Use Only', 'community-directory' ) ); ?>) + '</h4>';
            html += '<div class="cd-detail-grid">';
            html += '<div class="cd-detail-field"><span class="cd-label">' + esc(<?php echo wp_json_encode( __( 'Membership ID', 'community-directory' ) ); ?>) + ':</span> <span class="cd-value">' + esc(row.id) + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">' + esc(<?php echo wp_json_encode( __( 'Approved Date', 'community-directory' ) ); ?>) + ':</span> <span class="cd-value">' + formatDate(row.reviewed_at) + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">' + esc(<?php echo wp_json_encode( __( 'Secretary', 'community-directory' ) ); ?>) + ':</span> <span class="cd-value">' + esc(row.reviewer_name || '') + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">' + esc(<?php echo wp_json_encode( __( 'Approved by', 'community-directory' ) ); ?>) + ':</span> <span class="cd-value">' + esc(row.reviewer_name || '') + '</span></div>';
            html += '</div>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    /* ---- Build action buttons ---- */
    function buildActions(row) {
        var html = '<div class="cd-actions">';
        var actionableStatuses = ['new', 'under_review', 'on_hold'];

        if (actionableStatuses.indexOf(row.status) !== -1) {
            html += '<button type="button" class="button button-small button-approve cd-action-btn" data-id="' + row.id + '" data-action="approve">' + esc(<?php echo wp_json_encode( __( 'Approve', 'community-directory' ) ); ?>) + '</button>';
            html += '<button type="button" class="button button-small button-reject cd-action-btn" data-id="' + row.id + '" data-action="reject">' + esc(<?php echo wp_json_encode( __( 'Not Approved', 'community-directory' ) ); ?>) + '</button>';
            html += '<button type="button" class="button button-small cd-action-btn" data-id="' + row.id + '" data-action="request_info">' + esc(<?php echo wp_json_encode( __( 'Request Info', 'community-directory' ) ); ?>) + '</button>';
            html += '<button type="button" class="button button-small button-hold cd-action-btn" data-id="' + row.id + '" data-action="hold">' + esc(<?php echo wp_json_encode( __( 'Hold', 'community-directory' ) ); ?>) + '</button>';
        }

        if (row.status === 'approved') {
            html += '<button type="button" class="button button-small cd-resend-invite-btn" data-id="' + row.id + '">' + esc(<?php echo wp_json_encode( __( 'Resend Invite', 'community-directory' ) ); ?>) + '</button>';
        }

        html += '</div>';
        return html;
    }

    /* ---- Load data ---- */
    function loadApplications() {
        elLoading.style.display = '';
        elTable.style.display = 'none';
        elEmpty.style.display = 'none';
        elPagination.style.display = 'none';
        appCache = {};

        var url = '/admin/applications?page=' + state.page + '&per_page=' + state.perPage;
        if (state.status && state.status !== 'all') {
            url += '&status=' + encodeURIComponent(state.status);
        }

        apiCall('GET', url)
        .then(function(json) {
            elLoading.style.display = 'none';

            if (!json.success) {
                showNotice(json.error ? json.error.message : 'Failed to load applications.', 'error');
                return;
            }

            var rows   = json.data.applications || [];
            var counts = json.data.counts || {};
            var meta   = json.meta || {};

            state.total = meta.total || 0;
            state.pages = meta.pages || 0;

            /* Update tab counts */
            var countKeys = ['all', 'new', 'under_review', 'on_hold', 'approved', 'not_approved'];
            for (var i = 0; i < countKeys.length; i++) {
                var el = document.getElementById('cd-acount-' + countKeys[i]);
                if (el) el.textContent = counts[countKeys[i]] || 0;
            }

            if (rows.length === 0) {
                elEmpty.style.display = '';
                return;
            }

            var html = '';
            for (var r = 0; r < rows.length; r++) {
                var row = rows[r];
                appCache[row.id] = row;
                var name = (esc(row.first_name || '') + ' ' + esc(row.last_name || '')).trim() || '—';
                var fd   = row.form_data || {};
                var phone = esc(fd.phone || fd.cell_phone || '') || '—';
                var badge = '<span class="cd-badge cd-badge-' + esc(row.status) + '">' + esc(statusLabels[row.status] || row.status) + '</span>';

                html += '<tr class="cd-app-row" data-id="' + row.id + '">';
                html += '<td><a href="#" class="cd-toggle-details" data-id="' + row.id + '" title="Toggle details">&#9660;</a></td>';
                html += '<td>' + name + '</td>';
                html += '<td>' + esc(row.email) + '</td>';
                html += '<td>' + phone + '</td>';
                html += '<td>' + formatDate(row.submitted_at) + '</td>';
                html += '<td>' + badge + '</td>';
                html += '<td>' + buildActions(row) + '</td>';
                html += '</tr>';

                /* Hidden detail row */
                html += '<tr class="cd-detail-row" id="cd-detail-' + row.id + '" style="display:none;">';
                html += '<td colspan="7">' + buildDetailPanel(row) + '</td>';
                html += '</tr>';
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

    /* ---- Toggle detail panel ---- */
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.cd-toggle-details');
        if (!toggle) return;
        e.preventDefault();
        var id = toggle.getAttribute('data-id');
        var detailRow = document.getElementById('cd-detail-' + id);
        if (!detailRow) return;
        var isVisible = detailRow.style.display !== 'none';
        detailRow.style.display = isVisible ? 'none' : '';
        toggle.innerHTML = isVisible ? '&#9660;' : '&#9650;';
    });

    /* ---- Action buttons ---- */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.cd-action-btn');
        if (!btn) return;

        var id     = btn.getAttribute('data-id');
        var action = btn.getAttribute('data-action');
        var row    = appCache[id];

        if (action === 'approve') {
            var name = row ? (row.first_name + ' ' + row.last_name).trim() : '#' + id;
            if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to approve this application?', 'community-directory' ) ); ?> + '\n\n' + name)) return;
            btn.disabled = true;
            btn.textContent = <?php echo wp_json_encode( __( 'Approving...', 'community-directory' ) ); ?>;
            apiCall('PUT', '/admin/applications/' + id, { action: 'approve' })
            .then(function(json) {
                if (json.success) {
                    showNotice(json.data.message || 'Application approved.', 'success');
                    loadApplications();
                } else {
                    showNotice(json.error ? json.error.message : 'Failed to approve.', 'error');
                    btn.disabled = false;
                    btn.textContent = <?php echo wp_json_encode( __( 'Approve', 'community-directory' ) ); ?>;
                }
            })
            .catch(function(err) {
                showNotice('Network error: ' + err.message, 'error');
                btn.disabled = false;
                btn.textContent = <?php echo wp_json_encode( __( 'Approve', 'community-directory' ) ); ?>;
            });
        }

        else if (action === 'reject') {
            document.getElementById('cd-reject-app-id').value = id;
            document.getElementById('cd-reject-reason').value = 'other';
            document.getElementById('cd-reject-notes').value = '';
            document.getElementById('cd-reject-send-email').checked = true;
            document.getElementById('cd-reject-modal').classList.add('active');
        }

        else if (action === 'request_info') {
            document.getElementById('cd-info-app-id').value = id;
            document.getElementById('cd-info-message').value = '';
            document.getElementById('cd-info-modal').classList.add('active');
        }

        else if (action === 'hold') {
            btn.disabled = true;
            btn.textContent = <?php echo wp_json_encode( __( 'Holding...', 'community-directory' ) ); ?>;
            apiCall('PUT', '/admin/applications/' + id, { action: 'hold' })
            .then(function(json) {
                if (json.success) {
                    showNotice(json.data.message || 'Application placed on hold.', 'success');
                    loadApplications();
                } else {
                    showNotice(json.error ? json.error.message : 'Failed to hold.', 'error');
                    btn.disabled = false;
                    btn.textContent = <?php echo wp_json_encode( __( 'Hold', 'community-directory' ) ); ?>;
                }
            })
            .catch(function(err) {
                showNotice('Network error: ' + err.message, 'error');
                btn.disabled = false;
                btn.textContent = <?php echo wp_json_encode( __( 'Hold', 'community-directory' ) ); ?>;
            });
        }
    });

    /* ---- Resend Invite ---- */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.cd-resend-invite-btn');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Sending...', 'community-directory' ) ); ?>;

        apiCall('POST', '/admin/applications/' + id + '/resend-invite', {})
        .then(function(json) {
            if (json.success) {
                showNotice(json.data.message || 'Invite resent.', 'success');
            } else {
                showNotice(json.error ? json.error.message : 'Failed to resend invite.', 'error');
            }
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Resend Invite', 'community-directory' ) ); ?>;
        })
        .catch(function(err) {
            showNotice('Network error: ' + err.message, 'error');
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Resend Invite', 'community-directory' ) ); ?>;
        });
    });

    /* ---- Rejection modal ---- */
    function closeRejectModal() {
        document.getElementById('cd-reject-modal').classList.remove('active');
    }
    document.getElementById('cd-reject-close').addEventListener('click', closeRejectModal);
    document.getElementById('cd-reject-cancel').addEventListener('click', closeRejectModal);

    document.getElementById('cd-reject-confirm').addEventListener('click', function() {
        var id    = document.getElementById('cd-reject-app-id').value;
        var btn   = this;
        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Submitting...', 'community-directory' ) ); ?>;

        apiCall('PUT', '/admin/applications/' + id, {
            action:           'reject',
            rejection_reason: document.getElementById('cd-reject-reason').value,
            notes:            document.getElementById('cd-reject-notes').value,
            send_email:       document.getElementById('cd-reject-send-email').checked
        })
        .then(function(json) {
            closeRejectModal();
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Mark Not Approved', 'community-directory' ) ); ?>;
            if (json.success) {
                showNotice(json.data.message || 'Application marked as not approved.', 'success');
                loadApplications();
            } else {
                showNotice(json.error ? json.error.message : 'Failed.', 'error');
            }
        })
        .catch(function(err) {
            closeRejectModal();
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Mark Not Approved', 'community-directory' ) ); ?>;
            showNotice('Network error: ' + err.message, 'error');
        });
    });

    /* ---- Request Info modal ---- */
    function closeInfoModal() {
        document.getElementById('cd-info-modal').classList.remove('active');
    }
    document.getElementById('cd-info-close').addEventListener('click', closeInfoModal);
    document.getElementById('cd-info-cancel').addEventListener('click', closeInfoModal);

    document.getElementById('cd-info-confirm').addEventListener('click', function() {
        var id  = document.getElementById('cd-info-app-id').value;
        var msg = document.getElementById('cd-info-message').value.trim();
        if (!msg) {
            alert(<?php echo wp_json_encode( __( 'Please enter a message for the applicant.', 'community-directory' ) ); ?>);
            return;
        }
        var btn = this;
        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Sending...', 'community-directory' ) ); ?>;

        apiCall('PUT', '/admin/applications/' + id, {
            action:  'request_info',
            message: msg
        })
        .then(function(json) {
            closeInfoModal();
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Send Request', 'community-directory' ) ); ?>;
            if (json.success) {
                showNotice(json.data.message || 'Information request sent.', 'success');
                loadApplications();
            } else {
                showNotice(json.error ? json.error.message : 'Failed.', 'error');
            }
        })
        .catch(function(err) {
            closeInfoModal();
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Send Request', 'community-directory' ) ); ?>;
            showNotice('Network error: ' + err.message, 'error');
        });
    });

    /* ---- Close modals on overlay click ---- */
    document.querySelectorAll('.cd-modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });

    /* ---- Init ---- */
    loadApplications();
})();
</script>
