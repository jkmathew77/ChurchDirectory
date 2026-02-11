<?php
/**
 * Admin page: WhatsApp Groups management.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap" id="cd-whatsapp-admin">
    <h1><?php esc_html_e( 'WhatsApp Groups', 'community-directory' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Manage WhatsApp groups visible to community members.', 'community-directory' ); ?></p>

    <div id="cd-whatsapp-messages"></div>

    <p>
        <button type="button" class="button button-primary" id="cd-wa-add-btn">
            <?php esc_html_e( 'Add New Group', 'community-directory' ); ?>
        </button>
    </p>

    <!-- Groups Table -->
    <table class="widefat striped" id="cd-wa-table" style="margin-top: 16px;">
        <thead>
            <tr>
                <th style="width:30px;"><?php esc_html_e( 'Order', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Name', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Description', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Invite URL', 'community-directory' ); ?></th>
                <th><?php esc_html_e( 'Visibility', 'community-directory' ); ?></th>
                <th style="width:60px;"><?php esc_html_e( 'Active', 'community-directory' ); ?></th>
                <th style="width:140px;"><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
            </tr>
        </thead>
        <tbody id="cd-wa-tbody">
            <tr id="cd-wa-loading"><td colspan="7" style="text-align:center;"><?php esc_html_e( 'Loading...', 'community-directory' ); ?></td></tr>
        </tbody>
    </table>

    <!-- Add/Edit Modal -->
    <div id="cd-wa-modal" style="display:none;">
        <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; max-width:500px; margin:20px auto; border-radius:4px;">
            <h2 id="cd-wa-modal-title"><?php esc_html_e( 'Add WhatsApp Group', 'community-directory' ); ?></h2>
            <input type="hidden" id="cd-wa-edit-id" value="">
            <table class="form-table">
                <tr>
                    <th><label for="cd-wa-name"><?php esc_html_e( 'Name', 'community-directory' ); ?> *</label></th>
                    <td><input type="text" id="cd-wa-name" class="regular-text" style="width:100%;"></td>
                </tr>
                <tr>
                    <th><label for="cd-wa-desc"><?php esc_html_e( 'Description', 'community-directory' ); ?></label></th>
                    <td><textarea id="cd-wa-desc" rows="2" style="width:100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="cd-wa-url"><?php esc_html_e( 'Invite URL', 'community-directory' ); ?> *</label></th>
                    <td><input type="url" id="cd-wa-url" class="regular-text" style="width:100%;" placeholder="https://chat.whatsapp.com/..."></td>
                </tr>
                <tr>
                    <th><label for="cd-wa-icon"><?php esc_html_e( 'Icon', 'community-directory' ); ?></label></th>
                    <td><input type="text" id="cd-wa-icon" style="width:80px;" placeholder="&#128172;"> <span class="description"><?php esc_html_e( 'Emoji or icon code', 'community-directory' ); ?></span></td>
                </tr>
                <tr>
                    <th><label for="cd-wa-order"><?php esc_html_e( 'Display Order', 'community-directory' ); ?></label></th>
                    <td><input type="number" id="cd-wa-order" min="0" value="0" style="width:80px;"></td>
                </tr>
                <tr>
                    <th><label for="cd-wa-visibility"><?php esc_html_e( 'Visibility', 'community-directory' ); ?></label></th>
                    <td>
                        <select id="cd-wa-visibility">
                            <option value="all"><?php esc_html_e( 'All Members', 'community-directory' ); ?></option>
                            <option value="tag"><?php esc_html_e( 'By Ministry Tag', 'community-directory' ); ?></option>
                        </select>
                        <input type="text" id="cd-wa-tag" placeholder="<?php esc_attr_e( 'Ministry tag...', 'community-directory' ); ?>" style="display:none; margin-top:4px; width:100%;">
                    </td>
                </tr>
                <tr>
                    <th><label for="cd-wa-active"><?php esc_html_e( 'Active', 'community-directory' ); ?></label></th>
                    <td><input type="checkbox" id="cd-wa-active" checked></td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary" id="cd-wa-save-btn"><?php esc_html_e( 'Save', 'community-directory' ); ?></button>
                <button type="button" class="button" id="cd-wa-cancel-btn"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
            </p>
        </div>
    </div>
</div>

<script>
(function($) {
    var apiBase = '<?php echo esc_js( rest_url( CD_API_NAMESPACE ) ); ?>';
    var nonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
    var groups = [];

    function apiRequest(endpoint, method, data) {
        var opts = {
            url: apiBase + endpoint,
            method: method || 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
            contentType: 'application/json',
        };
        if (data) opts.data = JSON.stringify(data);
        return $.ajax(opts);
    }

    function showMsg(text, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        $('#cd-whatsapp-messages').html('<div class="notice ' + cls + ' is-dismissible"><p>' + text + '</p></div>');
    }

    function renderTable() {
        var $tbody = $('#cd-wa-tbody');
        $tbody.empty();
        if (groups.length === 0) {
            $tbody.html('<tr><td colspan="7" style="text-align:center;"><?php echo esc_js( __( 'No groups found. Click "Add New Group" to create one.', 'community-directory' ) ); ?></td></tr>');
            return;
        }
        groups.forEach(function(g) {
            var row = '<tr data-id="' + g.id + '">' +
                '<td>' + g.display_order + '</td>' +
                '<td><strong>' + escHtml(g.name) + '</strong></td>' +
                '<td>' + escHtml(g.description).substring(0, 60) + '</td>' +
                '<td><a href="' + escHtml(g.invite_url) + '" target="_blank" rel="noopener">' + escHtml(g.invite_url).substring(0, 40) + '...</a></td>' +
                '<td>' + (g.visibility === 'tag' ? 'Tag: ' + escHtml(g.visibility_tag) : 'All') + '</td>' +
                '<td>' + (g.is_active ? '&#9989;' : '&#10060;') + '</td>' +
                '<td>' +
                    '<button class="button button-small cd-wa-edit" data-id="' + g.id + '"><?php echo esc_js( __( 'Edit', 'community-directory' ) ); ?></button> ' +
                    '<button class="button button-small button-link-delete cd-wa-delete" data-id="' + g.id + '"><?php echo esc_js( __( 'Delete', 'community-directory' ) ); ?></button>' +
                '</td></tr>';
            $tbody.append(row);
        });
    }

    function escHtml(s) { return $('<span>').text(s || '').html(); }

    function loadGroups() {
        apiRequest('/admin/whatsapp-groups').done(function(res) {
            groups = res.data.groups || [];
            renderTable();
        }).fail(function() { showMsg('Failed to load groups.', 'error'); });
    }

    function openModal(group) {
        var isEdit = !!group;
        $('#cd-wa-modal-title').text(isEdit ? '<?php echo esc_js( __( 'Edit WhatsApp Group', 'community-directory' ) ); ?>' : '<?php echo esc_js( __( 'Add WhatsApp Group', 'community-directory' ) ); ?>');
        $('#cd-wa-edit-id').val(isEdit ? group.id : '');
        $('#cd-wa-name').val(isEdit ? group.name : '');
        $('#cd-wa-desc').val(isEdit ? group.description : '');
        $('#cd-wa-url').val(isEdit ? group.invite_url : '');
        $('#cd-wa-icon').val(isEdit ? group.icon : '');
        $('#cd-wa-order').val(isEdit ? group.display_order : 0);
        $('#cd-wa-visibility').val(isEdit ? group.visibility : 'all').trigger('change');
        $('#cd-wa-tag').val(isEdit ? group.visibility_tag : '');
        $('#cd-wa-active').prop('checked', isEdit ? group.is_active : true);
        $('#cd-wa-modal').show();
    }

    // Toggle tag input based on visibility
    $('#cd-wa-visibility').on('change', function() {
        $('#cd-wa-tag').toggle($(this).val() === 'tag');
    });

    // Add button
    $('#cd-wa-add-btn').on('click', function() { openModal(null); });

    // Cancel
    $('#cd-wa-cancel-btn').on('click', function() { $('#cd-wa-modal').hide(); });

    // Save
    $('#cd-wa-save-btn').on('click', function() {
        var id = $('#cd-wa-edit-id').val();
        var payload = {
            name: $('#cd-wa-name').val().trim(),
            description: $('#cd-wa-desc').val().trim(),
            invite_url: $('#cd-wa-url').val().trim(),
            icon: $('#cd-wa-icon').val().trim(),
            display_order: parseInt($('#cd-wa-order').val()) || 0,
            visibility: $('#cd-wa-visibility').val(),
            visibility_tag: $('#cd-wa-tag').val().trim(),
            is_active: $('#cd-wa-active').is(':checked'),
        };
        if (!payload.name || !payload.invite_url) {
            showMsg('Name and Invite URL are required.', 'error');
            return;
        }
        var method = id ? 'PUT' : 'POST';
        var endpoint = id ? '/admin/whatsapp-groups/' + id : '/admin/whatsapp-groups';
        apiRequest(endpoint, method, payload).done(function(res) {
            showMsg(res.data.message || 'Saved.');
            $('#cd-wa-modal').hide();
            loadGroups();
        }).fail(function(xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save.';
            showMsg(msg, 'error');
        });
    });

    // Edit
    $(document).on('click', '.cd-wa-edit', function() {
        var id = parseInt($(this).data('id'));
        var group = groups.find(function(g) { return g.id === id; });
        if (group) openModal(group);
    });

    // Delete
    $(document).on('click', '.cd-wa-delete', function() {
        var id = $(this).data('id');
        if (!confirm('<?php echo esc_js( __( 'Delete this WhatsApp group?', 'community-directory' ) ); ?>')) return;
        apiRequest('/admin/whatsapp-groups/' + id, 'DELETE').done(function(res) {
            showMsg(res.data.message || 'Deleted.');
            loadGroups();
        }).fail(function() { showMsg('Failed to delete.', 'error'); });
    });

    // Init
    loadGroups();
})(jQuery);
</script>
