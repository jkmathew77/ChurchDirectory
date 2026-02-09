<?php
/**
 * Admin view: Officers Group
 *
 * Manages church officers: list, add, remove, and annual rotation.
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
.cd-officers-section {
    margin-top: 24px;
}
.cd-officers-section h2 {
    font-size: 16px;
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid #c3c4c7;
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
#cd-officers-notice { display: none; }

/* --- Add Officer form --- */
.cd-add-officer-form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
    padding: 16px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}
.cd-add-officer-form .cd-form-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.cd-add-officer-form label {
    font-weight: 600;
    font-size: 13px;
}
.cd-add-officer-form input[type="text"] {
    min-width: 220px;
}

/* --- Autocomplete --- */
.cd-autocomplete-wrap {
    position: relative;
}
.cd-autocomplete-list {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-top: none;
    border-radius: 0 0 4px 4px;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.cd-autocomplete-list.active { display: block; }
.cd-autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f1;
}
.cd-autocomplete-item:last-child { border-bottom: none; }
.cd-autocomplete-item:hover,
.cd-autocomplete-item.selected {
    background: #2271b1;
    color: #fff;
}
.cd-autocomplete-empty {
    padding: 8px 12px;
    color: #646970;
    font-size: 13px;
    font-style: italic;
}

/* --- Rotation section --- */
.cd-rotation-section {
    margin-top: 24px;
    padding: 20px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}
.cd-rotation-section h2 {
    margin-top: 0;
    border-bottom: none;
    padding-bottom: 0;
}
.cd-rotation-warning {
    background: #fcf0e3;
    border: 1px solid #dba617;
    border-radius: 4px;
    padding: 12px 16px;
    margin: 12px 0;
    font-size: 13px;
    color: #664d03;
    line-height: 1.5;
}
.cd-rotation-warning strong {
    display: block;
    margin-bottom: 4px;
}
.cd-btn-danger {
    background: #d63638 !important;
    border-color: #d63638 !important;
    color: #fff !important;
}
.cd-btn-danger:hover {
    background: #b32d2e !important;
    border-color: #b32d2e !important;
}

/* --- Remove button --- */
.cd-remove-officer {
    color: #b32d2e;
    cursor: pointer;
    text-decoration: none;
}
.cd-remove-officer:hover {
    color: #d63638;
    text-decoration: underline;
}
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Officers Group', 'community-directory' ); ?></h1>
    <hr class="wp-header-end">

    <div id="cd-officers-notice" class="notice" role="alert">
        <p id="cd-officers-notice-text"></p>
    </div>

    <!-- Current Officers -->
    <div class="cd-officers-section">
        <h2><?php esc_html_e( 'Current Officers', 'community-directory' ); ?></h2>

        <div id="cd-officers-loading" class="cd-loading">
            <span class="spinner"></span> <?php esc_html_e( 'Loading officers...', 'community-directory' ); ?>
        </div>

        <table class="widefat striped" id="cd-officers-table" style="display: none;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Title', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Added Date', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
                </tr>
            </thead>
            <tbody id="cd-officers-tbody">
            </tbody>
        </table>

        <div id="cd-officers-empty" style="display: none; padding: 20px; text-align: center; color: #646970;">
            <?php esc_html_e( 'No active officers.', 'community-directory' ); ?>
        </div>
    </div>

    <!-- Add Officer -->
    <div class="cd-officers-section">
        <h2><?php esc_html_e( 'Add Officer', 'community-directory' ); ?></h2>

        <div class="cd-add-officer-form">
            <div class="cd-form-field">
                <label for="cd-officer-search"><?php esc_html_e( 'Member', 'community-directory' ); ?></label>
                <div class="cd-autocomplete-wrap">
                    <input type="text" id="cd-officer-search" autocomplete="off"
                           placeholder="<?php esc_attr_e( 'Search by name...', 'community-directory' ); ?>">
                    <input type="hidden" id="cd-officer-member-id" value="">
                    <div class="cd-autocomplete-list" id="cd-officer-autocomplete"></div>
                </div>
            </div>

            <div class="cd-form-field">
                <label for="cd-officer-title"><?php esc_html_e( 'Title', 'community-directory' ); ?></label>
                <input type="text" id="cd-officer-title"
                       placeholder="<?php esc_attr_e( 'e.g. President, Secretary...', 'community-directory' ); ?>">
            </div>

            <div class="cd-form-field">
                <button type="button" class="button button-primary" id="cd-officer-add-btn">
                    <?php esc_html_e( 'Add Officer', 'community-directory' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Annual Rotation -->
    <div class="cd-rotation-section">
        <h2><?php esc_html_e( 'Annual Rotation', 'community-directory' ); ?></h2>

        <div class="cd-rotation-warning">
            <strong><?php esc_html_e( 'Warning: This action cannot be undone.', 'community-directory' ); ?></strong>
            <?php esc_html_e( 'Rotating officers will deactivate ALL current officers from the officers group. Their officer privileges will be revoked and they will be notified by email. After rotation, you will need to add the new officers manually.', 'community-directory' ); ?>
        </div>

        <button type="button" class="button cd-btn-danger" id="cd-rotate-btn">
            <?php esc_html_e( 'Rotate Officers', 'community-directory' ); ?>
        </button>
    </div>
</div>

<script>
(function() {
    'use strict';

    var API_BASE = <?php echo wp_json_encode( $api_base ); ?>;
    var NONCE    = <?php echo wp_json_encode( $nonce ); ?>;

    /* ---- DOM refs ---- */
    var elLoading   = document.getElementById('cd-officers-loading');
    var elTable     = document.getElementById('cd-officers-table');
    var elTbody     = document.getElementById('cd-officers-tbody');
    var elEmpty     = document.getElementById('cd-officers-empty');
    var elNotice    = document.getElementById('cd-officers-notice');
    var elNoticeTxt = document.getElementById('cd-officers-notice-text');

    var elSearch      = document.getElementById('cd-officer-search');
    var elMemberId    = document.getElementById('cd-officer-member-id');
    var elTitle       = document.getElementById('cd-officer-title');
    var elAddBtn      = document.getElementById('cd-officer-add-btn');
    var elAutocomplete = document.getElementById('cd-officer-autocomplete');
    var elRotateBtn   = document.getElementById('cd-rotate-btn');

    /* ---- Helpers ---- */
    function showNotice(message, type) {
        elNotice.className = 'notice notice-' + (type || 'success') + ' is-dismissible';
        elNoticeTxt.textContent = message;
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

    /* ---- Load officers ---- */
    function loadOfficers() {
        elLoading.style.display = '';
        elTable.style.display = 'none';
        elEmpty.style.display = 'none';

        apiCall('GET', '/admin/officers')
        .then(function(json) {
            elLoading.style.display = 'none';

            if (!json.success) {
                showNotice(json.error ? json.error.message : 'Failed to load officers.', 'error');
                return;
            }

            var officers = json.data.officers || [];

            if (officers.length === 0) {
                elEmpty.style.display = '';
                return;
            }

            var html = '';
            for (var i = 0; i < officers.length; i++) {
                var o = officers[i];
                var name = (esc(o.first_name || '') + ' ' + esc(o.last_name || '')).trim() || '—';

                html += '<tr>';
                html += '<td>' + name + '</td>';
                html += '<td>' + esc(o.email || '—') + '</td>';
                html += '<td>' + esc(o.title || '—') + '</td>';
                html += '<td>' + formatDate(o.added_at) + '</td>';
                html += '<td><a href="#" class="cd-remove-officer" data-id="' + o.id + '" data-name="' + esc(name) + '">' + esc(<?php echo wp_json_encode( __( 'Remove', 'community-directory' ) ); ?>) + '</a></td>';
                html += '</tr>';
            }
            elTbody.innerHTML = html;
            elTable.style.display = '';
        })
        .catch(function(err) {
            elLoading.style.display = 'none';
            showNotice('Network error: ' + err.message, 'error');
        });
    }

    /* ---- Remove officer ---- */
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.cd-remove-officer');
        if (!link) return;
        e.preventDefault();

        var id   = link.getAttribute('data-id');
        var name = link.getAttribute('data-name');

        if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to remove this officer?', 'community-directory' ) ); ?> + '\n\n' + name)) {
            return;
        }

        link.textContent = <?php echo wp_json_encode( __( 'Removing...', 'community-directory' ) ); ?>;
        link.style.pointerEvents = 'none';

        apiCall('DELETE', '/admin/officers/' + id)
        .then(function(json) {
            if (json.success) {
                showNotice(json.data.message || 'Officer removed.', 'success');
                loadOfficers();
            } else {
                showNotice(json.error ? json.error.message : 'Failed to remove officer.', 'error');
                link.textContent = <?php echo wp_json_encode( __( 'Remove', 'community-directory' ) ); ?>;
                link.style.pointerEvents = '';
            }
        })
        .catch(function(err) {
            showNotice('Network error: ' + err.message, 'error');
            link.textContent = <?php echo wp_json_encode( __( 'Remove', 'community-directory' ) ); ?>;
            link.style.pointerEvents = '';
        });
    });

    /* ---- Member search autocomplete ---- */
    var searchTimer   = null;
    var selectedIndex = -1;

    elSearch.addEventListener('input', function() {
        var q = this.value.trim();
        elMemberId.value = '';

        if (searchTimer) clearTimeout(searchTimer);

        if (q.length < 2) {
            elAutocomplete.classList.remove('active');
            elAutocomplete.innerHTML = '';
            return;
        }

        searchTimer = setTimeout(function() {
            apiCall('GET', '/admin/members/search?q=' + encodeURIComponent(q))
            .then(function(json) {
                if (!json.success) {
                    elAutocomplete.classList.remove('active');
                    return;
                }

                var members = json.data.members || [];
                selectedIndex = -1;

                if (members.length === 0) {
                    elAutocomplete.innerHTML = '<div class="cd-autocomplete-empty">' + esc(<?php echo wp_json_encode( __( 'No members found.', 'community-directory' ) ); ?>) + '</div>';
                    elAutocomplete.classList.add('active');
                    return;
                }

                var html = '';
                for (var i = 0; i < members.length; i++) {
                    var m = members[i];
                    var mName = (esc(m.first_name || '') + ' ' + esc(m.last_name || '')).trim();
                    html += '<div class="cd-autocomplete-item" data-id="' + m.id + '" data-name="' + esc(mName) + '">' + mName + '</div>';
                }
                elAutocomplete.innerHTML = html;
                elAutocomplete.classList.add('active');
            })
            .catch(function() {
                elAutocomplete.classList.remove('active');
            });
        }, 300);
    });

    /* Keyboard navigation in autocomplete */
    elSearch.addEventListener('keydown', function(e) {
        var items = elAutocomplete.querySelectorAll('.cd-autocomplete-item');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateAutocompleteSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            updateAutocompleteSelection(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                selectMember(items[selectedIndex]);
            }
        } else if (e.key === 'Escape') {
            elAutocomplete.classList.remove('active');
        }
    });

    function updateAutocompleteSelection(items) {
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('selected', i === selectedIndex);
        }
        if (selectedIndex >= 0 && items[selectedIndex]) {
            items[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    /* Click on autocomplete item */
    elAutocomplete.addEventListener('click', function(e) {
        var item = e.target.closest('.cd-autocomplete-item');
        if (item) selectMember(item);
    });

    function selectMember(item) {
        elSearch.value    = item.getAttribute('data-name');
        elMemberId.value  = item.getAttribute('data-id');
        elAutocomplete.classList.remove('active');
        elAutocomplete.innerHTML = '';
    }

    /* Close autocomplete on outside click */
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.cd-autocomplete-wrap')) {
            elAutocomplete.classList.remove('active');
        }
    });

    /* ---- Add officer ---- */
    elAddBtn.addEventListener('click', function() {
        var memberId = elMemberId.value;
        var title    = elTitle.value.trim();

        if (!memberId) {
            showNotice(<?php echo wp_json_encode( __( 'Please search and select a member first.', 'community-directory' ) ); ?>, 'error');
            elSearch.focus();
            return;
        }

        elAddBtn.disabled = true;
        elAddBtn.textContent = <?php echo wp_json_encode( __( 'Adding...', 'community-directory' ) ); ?>;

        apiCall('POST', '/admin/officers', {
            member_id: parseInt(memberId, 10),
            title: title
        })
        .then(function(json) {
            elAddBtn.disabled = false;
            elAddBtn.textContent = <?php echo wp_json_encode( __( 'Add Officer', 'community-directory' ) ); ?>;

            if (json.success) {
                showNotice(json.data.message || 'Officer added.', 'success');
                elSearch.value = '';
                elMemberId.value = '';
                elTitle.value = '';
                loadOfficers();
            } else {
                showNotice(json.error ? json.error.message : 'Failed to add officer.', 'error');
            }
        })
        .catch(function(err) {
            elAddBtn.disabled = false;
            elAddBtn.textContent = <?php echo wp_json_encode( __( 'Add Officer', 'community-directory' ) ); ?>;
            showNotice('Network error: ' + err.message, 'error');
        });
    });

    /* ---- Rotate officers ---- */
    elRotateBtn.addEventListener('click', function() {
        var firstConfirm = confirm(
            <?php echo wp_json_encode( __( 'WARNING: This will deactivate ALL current officers and revoke their privileges. This action cannot be undone.', 'community-directory' ) ); ?> +
            '\n\n' +
            <?php echo wp_json_encode( __( 'Are you sure you want to proceed with the annual rotation?', 'community-directory' ) ); ?>
        );
        if (!firstConfirm) return;

        var secondConfirm = confirm(
            <?php echo wp_json_encode( __( 'Please confirm once more: Remove ALL officers and start fresh?', 'community-directory' ) ); ?>
        );
        if (!secondConfirm) return;

        elRotateBtn.disabled = true;
        elRotateBtn.textContent = <?php echo wp_json_encode( __( 'Rotating...', 'community-directory' ) ); ?>;

        apiCall('POST', '/admin/officers/rotate', {})
        .then(function(json) {
            elRotateBtn.disabled = false;
            elRotateBtn.textContent = <?php echo wp_json_encode( __( 'Rotate Officers', 'community-directory' ) ); ?>;

            if (json.success) {
                showNotice(json.data.message || 'Officer rotation complete.', 'success');
                loadOfficers();
            } else {
                showNotice(json.error ? json.error.message : 'Failed to rotate officers.', 'error');
            }
        })
        .catch(function(err) {
            elRotateBtn.disabled = false;
            elRotateBtn.textContent = <?php echo wp_json_encode( __( 'Rotate Officers', 'community-directory' ) ); ?>;
            showNotice('Network error: ' + err.message, 'error');
        });
    });

    /* ---- Init ---- */
    loadOfficers();
})();
</script>
