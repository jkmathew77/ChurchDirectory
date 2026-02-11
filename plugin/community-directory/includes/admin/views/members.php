<?php
/**
 * Admin view: Member Directory
 *
 * Lists all accepted members with search and filtering.
 * Uses REST API: GET /admin/members
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
/* Reusing styles from applications.php where possible */
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
.cd-pagination-buttons { display: flex; gap: 4px; align-items: center; }

/* Search Bar */
.cd-search-box {
    float: right;
    margin-bottom: 10px;
}
.cd-search-box input {
    margin: 0;
}

/* Badges */
.cd-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    line-height: 18px;
    white-space: nowrap;
}
.cd-badge-active { background: #e6f6e6; color: #1a7a1a; }
.cd-badge-inactive { background: #f0f0f1; color: #646970; }
.cd-badge-archived { background: #fce4e4; color: #cc1818; }

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
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Members', 'community-directory' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cd-import' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import Members', 'community-directory' ); ?></a>
    <hr class="wp-header-end">

    <div id="cd-notice" class="notice" style="display:none;" role="alert">
        <p id="cd-notice-text"></p>
    </div>

    <!-- Toolbar: Search + Export -->
    <div class="cd-search-box" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="search" id="cd-member-search" placeholder="<?php esc_attr_e( 'Search members...', 'community-directory' ); ?>">
        <button type="button" id="cd-btn-search" class="button"><?php esc_html_e( 'Search', 'community-directory' ); ?></button>
        <a href="<?php echo esc_url( rest_url( CD_API_NAMESPACE . '/admin/members/export?status=active&_wpnonce=' . wp_create_nonce( 'wp_rest' ) ) ); ?>"
           class="button" id="cd-btn-export" download="community-directory-export.csv"
           style="margin-left:auto;"><?php esc_html_e( 'Export CSV', 'community-directory' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cd-merge' ) ); ?>"
           class="button" id="cd-btn-merge"><?php esc_html_e( 'Find Duplicates', 'community-directory' ); ?></a>
    </div>

    <!-- Tabs -->
    <ul class="cd-status-tabs" id="cd-tabs">
        <li><a href="#" data-status="active" class="current"><?php esc_html_e( 'Active', 'community-directory' ); ?> <span class="count" id="cd-count-active">0</span></a></li>
        <li><a href="#" data-status="inactive"><?php esc_html_e( 'Inactive', 'community-directory' ); ?> <span class="count" id="cd-count-inactive">0</span></a></li>
        <li><a href="#" data-status="all"><?php esc_html_e( 'All', 'community-directory' ); ?> <span class="count" id="cd-count-all">0</span></a></li>
    </ul>

    <!-- Loading -->
    <div id="cd-loading" class="cd-loading">
        <span class="spinner"></span> <?php esc_html_e( 'Loading members...', 'community-directory' ); ?>
    </div>

    <!-- Table -->
    <table class="widefat striped" id="cd-table" style="display: none;">
        <thead>
            <tr>
                <th style="width: 50px;"><?php esc_html_e( 'Avatar', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Name', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Email', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Phone', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Status', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Member Since', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
            </tr>
        </thead>
        <tbody id="cd-tbody">
        </tbody>
    </table>

    <div id="cd-empty" style="display: none; padding: 20px; text-align: center; color: #646970;">
        <?php esc_html_e( 'No members found.', 'community-directory' ); ?>
    </div>

    <!-- Pagination -->
    <div id="cd-pagination" class="cd-pagination" style="display: none;">
        <span class="cd-pagination-info" id="cd-pagination-info"></span>
        <span class="cd-pagination-buttons">
            <button type="button" class="button" id="cd-prev" disabled>&laquo; <?php esc_html_e( 'Previous', 'community-directory' ); ?></button>
            <span id="cd-page-indicator" style="padding: 0 8px; font-size: 13px;"></span>
            <button type="button" class="button" id="cd-next" disabled><?php esc_html_e( 'Next', 'community-directory' ); ?> &raquo;</button>
        </span>
    </div>
</div>

<script>
(function() {
    'use strict';

    var API_BASE = <?php echo wp_json_encode( $api_base ); ?>;
    var NONCE    = <?php echo wp_json_encode( $nonce ); ?>;

    var state = { status: 'active', page: 1, perPage: 20, total: 0, pages: 0, search: '' };

    /* DOM Elements */
    var elLoading    = document.getElementById('cd-loading');
    var elTable      = document.getElementById('cd-table');
    var elTbody      = document.getElementById('cd-tbody');
    var elEmpty      = document.getElementById('cd-empty');
    var elPagination = document.getElementById('cd-pagination');
    var elPageInfo   = document.getElementById('cd-pagination-info');
    var elPageInd    = document.getElementById('cd-page-indicator');
    var elBtnPrev    = document.getElementById('cd-prev');
    var elBtnNext    = document.getElementById('cd-next');
    var elNotice     = document.getElementById('cd-notice');
    var elNoticeText = document.getElementById('cd-notice-text');
    var elSearch     = document.getElementById('cd-member-search');
    var elBtnSearch  = document.getElementById('cd-btn-search');

    /* Init */
    loadMembers();

    /* Event Listeners */
    /* Event Listeners */
    document.addEventListener('click', function(e) {
        // Tab clicks
        var tab = e.target.closest('a[data-status]');
        if (tab) {
            e.preventDefault();
            var links = document.getElementById('cd-tabs').querySelectorAll('a');
            for (var i = 0; i < links.length; i++) links[i].classList.remove('current');
            tab.classList.add('current');
            state.status = tab.getAttribute('data-status');
            state.page = 1;
            loadMembers();
            return;
        }

        // View/Toggle details
        var viewBtn = e.target.closest('.cd-view-member');
        if (viewBtn) {
            e.preventDefault();
            var id = viewBtn.getAttribute('data-id');
            var detailRow = document.getElementById('cd-detail-' + id);
            if (detailRow) {
                var isVisible = detailRow.style.display !== 'none';
                detailRow.style.display = isVisible ? 'none' : 'table-row';
                viewBtn.textContent = isVisible ? <?php echo wp_json_encode( __( 'View', 'community-directory' ) ); ?> : <?php echo wp_json_encode( __( 'Hide', 'community-directory' ) ); ?>;
            }
        }
    });

    elBtnPrev.addEventListener('click', function() {
        if (state.page > 1) { state.page--; loadMembers(); }
    });

    elBtnNext.addEventListener('click', function() {
        if (state.page < state.pages) { state.page++; loadMembers(); }
    });

    elBtnSearch.addEventListener('click', function() {
        state.search = elSearch.value.trim();
        state.page = 1;
        loadMembers();
    });

    elSearch.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            state.search = elSearch.value.trim();
            state.page = 1;
            loadMembers();
        }
    });

    /* Functions */
    function buildDetailPanel(row) {
        var html = '<div class="cd-detail-panel" id="cd-panel-' + row.id + '">';
        
        // Mode: view vs edit
        var isEdit = state.editingId == row.id;

        // Use either phone list or single phone
        var phones = row.phones || [];
        var phoneStr = row.primary_phone || '';
        if (!isEdit && phones.length > 1) {
             phoneStr = phones.map(function(p) { return esc(p.value) + ' (' + esc(p.type) + ')'; }).join(', ');
        }
        
        // Emails
        var emails = row.emails || [];
        var emailStr = row.primary_email || '';
        if (!isEdit && emails.length > 1) {
            emailStr = emails.map(function(e) { return esc(e.value) + ' (' + esc(e.type) + ')'; }).join(', ');
        }
        
        var actions = '';
        if (isEdit) {
            actions = '<div style="text-align:right; margin-bottom:10px;">' +
                      '<button type="button" class="button" onclick="cancelEdit(' + row.id + ')">' + esc(<?php echo wp_json_encode( __( 'Cancel', 'community-directory' ) ); ?>) + '</button> ' + 
                      '<button type="button" class="button button-primary" onclick="saveEdit(' + row.id + ')">' + esc(<?php echo wp_json_encode( __( 'Save Changes', 'community-directory' ) ); ?>) + '</button>' +
                      '</div>';
        } else {
            actions = '<div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:10px;">' +
                      '<button type="button" class="button button-link-delete" style="color:#a00;text-decoration:none;" onclick="deleteMember(' + row.id + ')">' + esc(<?php echo wp_json_encode( __( 'Delete Member', 'community-directory' ) ); ?>) + '</button>' +
                      '<button type="button" class="button" onclick="startEdit(' + row.id + ')">' + esc(<?php echo wp_json_encode( __( 'Edit Details', 'community-directory' ) ); ?>) + '</button>' +
                      '</div>';
        }

        html += actions;
        
        html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Contact Info', 'community-directory' ) ); ?>) + '</h4>';
        html += '<div class="cd-detail-grid">';
        
        if (isEdit) {
            html += fieldInput(row.id, 'first_name', 'First Name', row.first_name);
            html += fieldInput(row.id, 'last_name', 'Last Name', row.last_name);
            html += fieldInput(row.id, 'email', 'Email', emailStr);
            html += fieldInput(row.id, 'phone', 'Phone', phoneStr);
            
            // Address Block
            html += '<div style="grid-column: 1 / -1; margin-top:8px;"><strong>' + esc(<?php echo wp_json_encode( __( 'Home Address', 'community-directory' ) ); ?>) + '</strong></div>';
            html += fieldInput(row.id, 'address_line_1', 'Address 1', row.address_line_1);
            html += fieldInput(row.id, 'address_line_2', 'Address 2', row.address_line_2);
            html += fieldInput(row.id, 'city', 'City', row.city);
            html += fieldInput(row.id, 'state', 'State', row.state);
            html += fieldInput(row.id, 'zip_code', 'Zip', row.zip_code);
            
            // Mailing Address (Optional Toggle)
            var hasMailing = !!row.address_mailing;
            html += '<div style="grid-column: 1 / -1; margin-top:8px;">';
            html += '<label class="cd-label"><input type="checkbox" id="toggle-mailing-' + row.id + '" ' + (hasMailing ? 'checked' : '') + ' onchange="toggleMailing(' + row.id + ')"> ' + esc(<?php echo wp_json_encode( __( 'Has different mailing address?', 'community-directory' ) ); ?>) + '</label>';
            html += '<div id="container-mailing-' + row.id + '" style="display:' + (hasMailing ? 'block' : 'none') + '; margin-top:5px;">';
            html += fieldInput(row.id, 'address_mailing', 'Mailing Address (Full)', row.address_mailing);
            html += '</div></div>';
            
        } else {
             html += '<div class="cd-detail-field"><span class="cd-label">Email:</span> <span class="cd-value">' + (emailStr || '—') + '</span></div>';
             html += '<div class="cd-detail-field"><span class="cd-label">Phone:</span> <span class="cd-value">' + (phoneStr || '—') + '</span></div>';
             
             // Construct address string if fields exist, else fallback
             var addr = [];
             if (row.address_line_1) addr.push(row.address_line_1);
             if (row.address_line_2) addr.push(row.address_line_2);
             var cityState = [];
             if (row.city) cityState.push(row.city);
             if (row.state) cityState.push(row.state);
             if (row.zip_code) cityState.push(row.zip_code);
             if (cityState.length > 0) addr.push(cityState.join(', '));
             
             var displayAddr = addr.length > 0 ? addr.join('<br>') : (row.address_home || '—');
             
             html += '<div class="cd-detail-field"><span class="cd-label">Address:</span> <span class="cd-value">' + displayAddr + '</span></div>';
             if (row.address_mailing) {
                 html += '<div class="cd-detail-field"><span class="cd-label">Mailing:</span> <span class="cd-value">' + esc(row.address_mailing) + '</span></div>';
             }
        }
        html += '</div>';

        html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Membership', 'community-directory' ) ); ?>) + '</h4>';
        html += '<div class="cd-detail-grid">';
        
        if (isEdit) {
            html += '<div class="cd-detail-field"><label class="cd-label">Status:</label> <select id="edit-status-' + row.id + '">';
            var statuses = ['active', 'inactive', 'archived', 'deceased'];
            statuses.forEach(function(s) {
                html += '<option value="' + s + '" ' + (row.status === s ? 'selected' : '') + '>' + s + '</option>';
            });
            html += '</select></div>';
            
            html += fieldInput(row.id, 'member_since', 'Member Since (YYYY-MM-DD)', row.member_since);
            html += fieldInput(row.id, 'ministry_tags', 'Ministry Tags (comma sep)', Array.isArray(row.ministry_tags) ? row.ministry_tags.join(', ') : (row.ministry_tags || ''));
        } else {
            html += '<div class="cd-detail-field"><span class="cd-label">Status:</span> <span class="cd-value">' + esc(row.status) + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Member Since:</span> <span class="cd-value">' + formatDate(row.member_since) + '</span></div>';
            
            var tags = Array.isArray(row.ministry_tags) ? row.ministry_tags.join(', ') : '';
            html += '<div class="cd-detail-field"><span class="cd-label">Ministries:</span> <span class="cd-value">' + esc(tags || '—') + '</span></div>';
        }
        html += '</div>';

        // Household
        html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Household', 'community-directory' ) ); ?>) + '</h4>';
        html += '<div class="cd-detail-grid">';
        if (row.household_name) {
            html += '<div class="cd-detail-field"><span class="cd-label">Household:</span> <span class="cd-value">' + esc(row.household_name) + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Role:</span> <span class="cd-value">' + esc(row.household_role_label || row.household_role || '—') + '</span></div>';
        } else {
            html += '<div class="cd-detail-field"><span class="cd-value" style="color:#646970;">Not part of a household</span></div>';
        }
        html += '</div>';

        // Personal Details
        html += '<h4>' + esc(<?php echo wp_json_encode( __( 'Personal Details', 'community-directory' ) ); ?>) + '</h4>';
        html += '<div class="cd-detail-grid">';
         if (isEdit) {
            html += fieldInput(row.id, 'date_of_birth', 'Date of Birth (YYYY-MM-DD)', row.date_of_birth);
            // Removed Name Day
            html += fieldInput(row.id, 'baptism_date', 'Baptism Date (YYYY-MM-DD)', row.baptism_date);
            html += fieldInput(row.id, 'wedding_anniversary', 'Anniversary (YYYY-MM-DD)', row.wedding_anniversary);
            html += fieldInput(row.id, 'occupation', 'Occupation', row.occupation);
            html += fieldInput(row.id, 'employer', 'Employer', row.employer);
            
            html += '<div class="cd-detail-field"><label class="cd-label">Preferred Contact:</label> <select id="edit-preferred_contact_method-' + row.id + '">';
            ['email', 'phone', 'sms', 'whatsapp'].forEach(function(m) {
                 html += '<option value="' + m + '" ' + (row.preferred_contact_method === m ? 'selected' : '') + '>' + m + '</option>';
            });
            html += '</select></div>';
            
            html += fieldInput(row.id, 'preferred_language', 'Language', row.preferred_language);
            // Removed Avatar URL

            html += fieldInput(row.id, 'emergency_contact_name', 'Emergency Contact', row.emergency_contact_name);
            html += fieldInput(row.id, 'emergency_contact_phone', 'Emergency Phone', row.emergency_contact_phone);
            
            // Social Media Links Repeater
            html += '<div style="grid-column: 1 / -1; margin-top:10px;">';
            html += '<label class="cd-label"><strong>' + esc(<?php echo wp_json_encode( __( 'Social Media Profiles', 'community-directory' ) ); ?>) + '</strong></label>';
            html += '<div id="social-links-container-' + row.id + '">';
            // Existing links
            var social = row.social_links || [];
            // If empty, start with one empty? No, start empty allowed.
            social.forEach(function(link, index) {
                html += buildSocialRow(row.id, index, link.platform, link.url);
            });
            html += '</div>';
            html += '<button type="button" class="button button-small" onclick="addSocialRow(' + row.id + ')">+ Add Profile</button>';
            html += '</div>';

        } else {
            html += '<div class="cd-detail-field"><span class="cd-label">DOB:</span> <span class="cd-value">' + esc(row.date_of_birth || '—') + '</span></div>';
            // Removed Name Day view
            html += '<div class="cd-detail-field"><span class="cd-label">Baptism:</span> <span class="cd-value">' + esc(row.baptism_date || '—') + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Anniversary:</span> <span class="cd-value">' + esc(row.wedding_anniversary || '—') + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Occupation:</span> <span class="cd-value">' + esc(row.occupation || '—') + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Employer:</span> <span class="cd-value">' + esc(row.employer || '—') + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Pref. Contact:</span> <span class="cd-value">' + esc(row.preferred_contact_method || '—') + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Language:</span> <span class="cd-value">' + esc(row.preferred_language || '—') + '</span></div>';
            html += '<div class="cd-detail-field"><span class="cd-label">Emergency Contact:</span> <span class="cd-value">' + esc(row.emergency_contact_name || '—') + (row.emergency_contact_phone ? ' (' + esc(row.emergency_contact_phone) + ')' : '') + '</span></div>';
            
            // View Social Links
            if (row.social_links && row.social_links.length > 0) {
                 var linksHtml = row.social_links.map(function(l) {
                     return '<a href="' + esc(l.url) + '" target="_blank">' + esc(l.platform) + '</a>';
                 }).join(', ');
                 html += '<div class="cd-detail-field" style="grid-column: 1 / -1;"><span class="cd-label">Social:</span> <span class="cd-value">' + linksHtml + '</span></div>';
            }
        }
        html += '</div>';

        html += '</div>';
        return html;
    }

    function fieldInput(id, key, label, value) {
        return '<div class="cd-detail-field">' +
               '<label class="cd-label" for="edit-' + key + '-' + id + '">' + esc(label) + ':</label> ' +
               '<input type="text" id="edit-' + key + '-' + id + '" value="' + esc(value) + '" style="width:100%;">' +
               '</div>';
    }
    
    // Social Row Builder
    function buildSocialRow(id, index, platform, url) {
        var platforms = ['Facebook', 'Instagram', 'Twitter/X', 'LinkedIn', 'TikTok', 'YouTube', 'Other'];
        var options = platforms.map(function(p) { 
            return '<option value="' + p + '" ' + (p === platform ? 'selected' : '') + '>' + p + '</option>';
        }).join('');
        
        return '<div class="cd-social-row" id="social-row-' + id + '-' + index + '" style="display:flex; gap:5px; margin-bottom:5px;">' +
               '<select class="social-platform" style="width:120px;">' + options + '</select>' +
               '<input type="text" class="social-url" value="' + esc(url || '') + '" placeholder="URL" style="flex:1;">' +
               '<button type="button" class="button button-small" onclick="removeSocialRow(' + id + ',' + index + ')">×</button>' +
               '</div>';
    }
    
    // Global Actions
    window.toggleMailing = function(id) {
        var checked = document.getElementById('toggle-mailing-' + id).checked;
        var container = document.getElementById('container-mailing-' + id);
        container.style.display = checked ? 'block' : 'none';
        if (!checked) {
            // clear value? Or keep it hidden? User said "make optional", implies null if not checked.
            // But let's just hide it visually for now, saveEdit should probably handle it.
            // Actually, if unchecked, we should clear it on save.
        }
    };
    
    window.addSocialRow = function(id) {
        var container = document.getElementById('social-links-container-' + id);
        var index = container.children.length + 1; // simple index
        // Create a unique index based on timestamp to avoid collisions if we delete middle ones
        index = Date.now();
        var parser = new DOMParser();
        var el = parser.parseFromString(buildSocialRow(id, index, 'Facebook', ''), 'text/html').body.firstChild;
        container.appendChild(el);
    };
    
    window.removeSocialRow = function(id, index) {
        var row = document.getElementById('social-row-' + id + '-' + index);
        if (row) row.remove();
    };

    window.startEdit = function(id) {
        state.editingId = id;
        renderRowDetails(id);
    };
    
    window.cancelEdit = function(id) {
        state.editingId = null;
        renderRowDetails(id);
    };
    
    window.deleteMember = function(id) {
        if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to permanently DELETE this member? This action cannot be undone.', 'community-directory' ) ); ?>)) {
            return;
        }
        
        fetch(API_BASE + '/admin/members/' + id, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': NONCE }
        })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.success) {
                showNotice('Member deleted.', 'success');
                loadMembers();
            } else {
                showNotice(json.error ? json.error.message : 'Error deleting member.', 'error');
            }
        });
    };
    
    window.saveEdit = function(id) {
         // Gather all fields
         var getVal = function(k) { 
             var el = document.getElementById('edit-' + k + '-' + id);
             return el ? el.value.trim() : '';
         };
         
         // Mailing Address logic: if toggle unchecked, send empty?
         var mailingToggle = document.getElementById('toggle-mailing-' + id);
         var addressMailing = (mailingToggle && mailingToggle.checked) ? getVal('address_mailing') : '';
         
         // Gather social links
         var socialLinks = [];
         var container = document.getElementById('social-links-container-' + id);
         var rows = container.querySelectorAll('.cd-social-row');
         rows.forEach(function(row) {
             var p = row.querySelector('.social-platform').value;
             var u = row.querySelector('.social-url').value.trim();
             if (u) {
                 socialLinks.push({ platform: p, url: u });
             }
         });
         
         var data = {
             status: getVal('status'),
             first_name: getVal('first_name'),
             last_name: getVal('last_name'),
             email: getVal('email'),
             phone: getVal('phone'),
             // address_home: getVal('address_home'), // Deprecated in UI use
             address_line_1: getVal('address_line_1'),
             address_line_2: getVal('address_line_2'),
             city: getVal('city'),
             state: getVal('state'),
             zip_code: getVal('zip_code'),
             address_mailing: addressMailing,
             
             member_since: getVal('member_since'),
             ministry_tags: getVal('ministry_tags').split(',').map(function(s){return s.trim()}).filter(function(s){return s}),
             
             date_of_birth: getVal('date_of_birth'),
             // name_day: getVal('name_day'), // Removed
             baptism_date: getVal('baptism_date'),
             wedding_anniversary: getVal('wedding_anniversary'),
             occupation: getVal('occupation'),
             employer: getVal('employer'),
             emergency_contact_name: getVal('emergency_contact_name'),
             emergency_contact_phone: getVal('emergency_contact_phone'),
             preferred_contact_method: getVal('preferred_contact_method'),
             preferred_language: getVal('preferred_language'),
             // avatar_url: getVal('avatar_url'), // Removed
             
             social_links: socialLinks
         };
         
         // Call API
         fetch(API_BASE + '/admin/members/' + id, {
            method: 'PUT',
            headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
         })
         .then(function(r) { return r.json(); })
         .then(function(json) {
             if (json.success) {
                 showNotice('Member updated.', 'success');
                 state.editingId = null;
                 loadMembers(); // Reload to refresh data
             } else {
                 showNotice(json.error ? json.error.message : 'Error updating.', 'error');
             }
         });
    };
    
    function renderRowDetails(id) {
        var row = null;
        if (window.memberCache && window.memberCache[id]) {
            row = window.memberCache[id];
        } else {
            return; 
        }
        
        var td = document.querySelector('#cd-detail-' + id + ' td');
        if (td) {
            td.innerHTML = buildDetailPanel(row);
        }
    }

    function showNotice(message, type) {
        elNotice.className = 'notice notice-' + (type || 'success') + ' is-dismissible';
        elNoticeText.textContent = message;
        elNotice.style.display = '';
        setTimeout(function() { elNotice.style.display = 'none'; }, 5000);
    }
    
    // ... rest of existing functions ...

    function esc(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString();
    }

    function loadMembers() {
        elLoading.style.display = '';
        elTable.style.display = 'none';
        elEmpty.style.display = 'none';
        elPagination.style.display = 'none';

        var url = API_BASE + '/admin/members?page=' + state.page + '&per_page=' + state.perPage;
        if (state.status && state.status !== 'all') {
            url += '&status=' + encodeURIComponent(state.status);
        }
        if (state.search) {
             url += '&search=' + encodeURIComponent(state.search);
        }

        fetch(url, {
            headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            handleResponse(json);
        })
        .catch(function(err) {
            elLoading.style.display = 'none';
            showNotice('Network error: ' + err.message, 'error');
        });
    }

    function handleResponse(json) {
        elLoading.style.display = 'none';

        if (!json.success) {
            showNotice(json.error ? json.error.message : 'Error loading members.', 'error');
            return;
        }

        var rows = json.data.members || [];
        
        // Cache rows for editing
        window.memberCache = {};
        rows.forEach(function(r) { window.memberCache[r.id] = r; });

        var counts = json.data.counts || {};
        var meta = json.meta || {};

        state.total = meta.total || 0;
        state.pages = meta.pages || 0;

        // Update counts
        var countKeys = ['active', 'inactive', 'all'];
        for (var i = 0; i < countKeys.length; i++) {
             var el = document.getElementById('cd-count-' + countKeys[i]);
             if (el) el.textContent = counts[countKeys[i]] || 0;
        }

        if (rows.length === 0) {
            elEmpty.style.display = '';
            return;
        }

        renderRows(rows);
        updatePagination();
    }

    function buildAvatarHtml(name, avatarUrl) {
        var parts = name.split(/\s+/).filter(function(s){return s.length > 0;});
        var init = '';
        if (parts.length > 0) init += parts[0].charAt(0);
        if (parts.length > 1) init += parts[parts.length-1].charAt(0);
        init = init.toUpperCase();

        var bgColors = ['#d32f2f', '#c2185b', '#7b1fa2', '#512da8', '#303f9f', '#1976d2', '#0288d1', '#0097a7', '#00796b', '#388e3c', '#afb42b', '#fbc02d', '#ffa000', '#f57c00', '#e64a19', '#5d4037', '#616161'];
        var colorIndex = (name.length + (name.charCodeAt(0) || 0)) % bgColors.length;
        var bgColor = bgColors[colorIndex];

        var fallback = '<div class="cd-avatar-fallback" style="width:32px;height:32px;border-radius:50%;background:' + bgColor + ';display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:bold;color:#fff;">' + init + '</div>';

        if (avatarUrl) {
            return '<img src="' + esc(avatarUrl) + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">' +
                  '<div class="cd-avatar-fallback" style="display:none;width:32px;height:32px;border-radius:50%;background:' + bgColor + ';align-items:center;justify-content:center;font-size:13px;font-weight:bold;color:#fff;">' + init + '</div>';
        }
        return fallback;
    }

    function buildMemberRow(row, indent) {
        var name = ((row.first_name || '') + ' ' + (row.last_name || '')).trim() || '(No Name)';
        var avatar = buildAvatarHtml(name, row.avatar_url);
        var nameCol = indent
            ? '<span style="margin-left:24px;color:#646970;">└ </span>' + esc(name) + ' <span style="color:#888;font-size:12px;">(' + esc(row.household_role_label || row.household_role || '') + ')</span>'
            : '<strong>' + esc(name) + '</strong>' + (row.household_name ? ' <span style="color:#888;font-size:12px;">(' + esc(row.household_role_label || 'Primary') + ')</span>' : '');

        var html = '<tr' + (indent ? ' style="background:#f9f9f9;"' : '') + '>';
        html += '<td>' + avatar + '</td>';
        html += '<td>' + nameCol + '</td>';
        html += '<td>' + esc(row.primary_email || '—') + '</td>';
        html += '<td>' + esc(row.primary_phone || '—') + '</td>';
        html += '<td><span class="cd-badge cd-badge-' + esc(row.status) + '">' + esc(row.status) + '</span></td>';
        html += '<td>' + formatDate(row.member_since || row.created_at) + '</td>';
        html += '<td>';
        html += '<button type="button" class="button button-small cd-view-member" data-id="' + row.id + '">' + esc(<?php echo wp_json_encode( __( 'View', 'community-directory' ) ); ?>) + '</button> ';
        html += '</td>';
        html += '</tr>';

        /* Hidden detail row */
        html += '<tr class="cd-detail-row" id="cd-detail-' + row.id + '" style="display:none;">';
        html += '<td colspan="7">' + buildDetailPanel(row) + '</td>';
        html += '</tr>';
        return html;
    }

    function renderRows(rows) {
        // Group members by household: heads first, then their household members underneath
        // Members without a household just appear normally
        var rendered = {};  // track member IDs we've already rendered
        var html = '';

        // Build household groups: { household_id: [members] }
        var hhGroups = {};
        rows.forEach(function(row) {
            if (row.household_id) {
                if (!hhGroups[row.household_id]) hhGroups[row.household_id] = [];
                hhGroups[row.household_id].push(row);
            }
        });

        rows.forEach(function(row) {
            if (rendered[row.id]) return;
            rendered[row.id] = true;

            // Render this member
            html += buildMemberRow(row, false);

            // If this member is a household head, render other household members indented below
            if (row.household_id && row.household_role === 'head' && hhGroups[row.household_id]) {
                hhGroups[row.household_id].forEach(function(hm) {
                    if (hm.id === row.id || rendered[hm.id]) return;
                    rendered[hm.id] = true;
                    html += buildMemberRow(hm, true);
                });
            }
        });

        elTbody.innerHTML = html;
        elTable.style.display = '';
    }

    function updatePagination() {
        if (state.pages > 1) {
            var start = ((state.page - 1) * state.perPage) + 1;
            var end   = Math.min(state.page * state.perPage, state.total);
            elPageInfo.textContent = start + '–' + end + ' of ' + state.total;
            elPageInd.textContent  = state.page + ' / ' + state.pages;
            
            elBtnPrev.disabled = (state.page <= 1);
            elBtnNext.disabled = (state.page >= state.pages);
            elPagination.style.display = '';
        }
    }
})();
</script>
