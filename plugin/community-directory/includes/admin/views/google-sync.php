<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$google_status = class_exists( 'CD_Google_Contacts' ) ? CD_Google_Contacts::get_status() : array( 'status' => 'not_configured' );
$is_connected  = 'connected' === ( $google_status['status'] ?? '' );
$nonce         = wp_create_nonce( 'wp_rest' );
$api_base      = esc_url_raw( rest_url( 'community-directory/v1/admin' ) );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Import & Google Contacts Sync', 'community-directory' ); ?></h1>

    <?php if ( isset( $_GET['sync_success'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Sync completed successfully.', 'community-directory' ); ?></p></div>
    <?php endif; ?>

    <!-- Connection Status Card -->
    <div class="cd-admin-card" style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo $is_connected ? '#16a34a' : '#d97706'; ?>;padding:16px 20px;margin:20px 0;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Google Contacts Connection', 'community-directory' ); ?></h2>
        <?php
        $status_text = array(
            'connected'      => '<span style="color:#16a34a;font-weight:600;">&#9679; ' . esc_html__( 'Connected', 'community-directory' ) . '</span>',
            'needs_auth'     => '<span style="color:#d97706;font-weight:600;">&#9679; ' . esc_html__( 'Needs Authorization', 'community-directory' ) . '</span>',
            'error'          => '<span style="color:#dc2626;font-weight:600;">&#9679; ' . esc_html__( 'Error', 'community-directory' ) . '</span>',
            'not_configured' => '<span style="color:#64748b;">&#9679; ' . esc_html__( 'Not Configured', 'community-directory' ) . '</span>',
        );
        echo '<p>' . esc_html__( 'Status:', 'community-directory' ) . ' ' . ( $status_text[ $google_status['status'] ] ?? $status_text['not_configured'] ) . '</p>';
        ?>

        <?php if ( ! $is_connected ) : ?>
            <p>
                <?php
                printf(
                    esc_html__( 'Configure Google API credentials in %sSettings%s, then authorize the connection.', 'community-directory' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=cd-settings' ) ) . '">',
                    '</a>'
                );
                ?>
            </p>
        <?php endif; ?>

        <?php if ( $google_status['pending_retries'] > 0 ) : ?>
            <p style="color:#d97706;">
                <?php
                printf(
                    esc_html__( '%d contact(s) pending retry. The next retry will run automatically via cron.', 'community-directory' ),
                    $google_status['pending_retries']
                );
                ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ( $is_connected ) : ?>

    <!-- Import from Google Section -->
    <div class="cd-admin-card" style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Import Contacts from Google', 'community-directory' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Fetch contacts from the connected Google account and preview matches against existing directory members before importing.', 'community-directory' ); ?></p>

        <div id="cd-sync-controls" style="margin:16px 0;">
            <button type="button" class="button button-primary" id="cd-btn-preview-import">
                <?php esc_html_e( 'Preview Import', 'community-directory' ); ?>
            </button>
            <span id="cd-sync-spinner" class="spinner" style="float:none;"></span>
            <span id="cd-sync-status" style="margin-left:8px;"></span>
        </div>

        <!-- Preview Table -->
        <div id="cd-import-preview" style="display:none;margin-top:20px;">
            <h3><?php esc_html_e( 'Import Preview', 'community-directory' ); ?></h3>

            <div id="cd-import-summary" style="margin-bottom:12px;"></div>

            <table class="widefat striped" id="cd-import-table">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="cd-import-select-all"></th>
                        <th><?php esc_html_e( 'Name', 'community-directory' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'community-directory' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'community-directory' ); ?></th>
                        <th><?php esc_html_e( 'Match', 'community-directory' ); ?></th>
                    </tr>
                </thead>
                <tbody id="cd-import-tbody">
                </tbody>
            </table>

            <div style="margin-top:12px;">
                <button type="button" class="button button-primary" id="cd-btn-confirm-import" disabled>
                    <?php esc_html_e( 'Import Selected', 'community-directory' ); ?>
                </button>
                <button type="button" class="button" id="cd-btn-cancel-import">
                    <?php esc_html_e( 'Cancel', 'community-directory' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Export Status Section -->
    <div class="cd-admin-card" style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Export to Google Contacts', 'community-directory' ); ?></h2>
        <p class="description">
            <?php
            $export_enabled = '1' === get_option( 'cd_google_export_on_approval', '1' );
            if ( $export_enabled ) {
                esc_html_e( 'Auto-export is enabled. New members are automatically added to Google Contacts when approved.', 'community-directory' );
            } else {
                esc_html_e( 'Auto-export is disabled. Enable it in Settings to automatically add approved members to Google Contacts.', 'community-directory' );
            }
            ?>
        </p>
    </div>

    <?php else : ?>

    <div class="cd-admin-card" style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Getting Started', 'community-directory' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Go to the Google Cloud Console and create OAuth 2.0 credentials (Web application type).', 'community-directory' ); ?></li>
            <li><?php
                $callback_url = admin_url( 'admin-ajax.php?action=cd_google_callback' );
                printf(
                    esc_html__( 'Set the authorized redirect URI to: %s', 'community-directory' ),
                    '<code>' . esc_html( $callback_url ) . '</code>'
                );
            ?></li>
            <li><?php
                printf(
                    esc_html__( 'Enter the Client ID and Client Secret in %sSettings%s.', 'community-directory' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=cd-settings' ) ) . '">',
                    '</a>'
                );
            ?></li>
            <li><?php esc_html_e( 'Click "Connect to Google" and authorize with a shared workspace account (e.g., sainttheklachurch@gmail.com).', 'community-directory' ); ?></li>
            <li><?php esc_html_e( 'Contacts created through this account will be visible to all workspace users.', 'community-directory' ); ?></li>
        </ol>
    </div>

    <?php endif; ?>
</div>

<?php if ( $is_connected ) : ?>
<script>
(function() {
    var apiBase = <?php echo wp_json_encode( $api_base ); ?>;
    var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
    var previewData = [];

    var btnPreview = document.getElementById('cd-btn-preview-import');
    var btnConfirm = document.getElementById('cd-btn-confirm-import');
    var btnCancel  = document.getElementById('cd-btn-cancel-import');
    var spinner    = document.getElementById('cd-sync-spinner');
    var statusEl   = document.getElementById('cd-sync-status');
    var previewDiv = document.getElementById('cd-import-preview');
    var tbody      = document.getElementById('cd-import-tbody');
    var summary    = document.getElementById('cd-import-summary');
    var selectAll  = document.getElementById('cd-import-select-all');

    function showSpinner(show) {
        spinner.style.visibility = show ? 'visible' : 'hidden';
        spinner.classList.toggle('is-active', show);
    }

    btnPreview.addEventListener('click', function() {
        showSpinner(true);
        statusEl.textContent = '<?php echo esc_js( __( 'Fetching contacts from Google...', 'community-directory' ) ); ?>';
        btnPreview.disabled = true;

        fetch(apiBase + '/google-sync/preview', {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            showSpinner(false);
            btnPreview.disabled = false;

            if (!res.success) {
                statusEl.textContent = res.data || 'Error fetching contacts.';
                return;
            }

            previewData = res.data.contacts || [];
            statusEl.textContent = '';

            var exact = 0, strong = 0, weak = 0, none = 0;
            tbody.innerHTML = '';

            previewData.forEach(function(c, i) {
                if (c.match_type === 'exact') exact++;
                else if (c.match_type === 'strong') strong++;
                else if (c.match_type === 'weak') weak++;
                else none++;

                var matchBadge = '';
                switch(c.match_type) {
                    case 'exact':  matchBadge = '<span style="color:#16a34a;">&#10003; Existing</span>'; break;
                    case 'strong': matchBadge = '<span style="color:#2563eb;">~ Likely match</span>'; break;
                    case 'weak':   matchBadge = '<span style="color:#d97706;">? Name match</span>'; break;
                    default:       matchBadge = '<span style="color:#64748b;">New</span>'; break;
                }

                var checked = c.match_type === 'none' ? ' checked' : '';
                var row = '<tr>' +
                    '<td><input type="checkbox" class="cd-import-check" data-index="' + i + '"' + checked + '></td>' +
                    '<td>' + escHtml(c.name) + '</td>' +
                    '<td>' + escHtml(c.email) + '</td>' +
                    '<td>' + escHtml(c.phone) + '</td>' +
                    '<td>' + matchBadge + '</td>' +
                    '</tr>';
                tbody.insertAdjacentHTML('beforeend', row);
            });

            summary.innerHTML = '<strong>' + previewData.length + '</strong> contacts found: ' +
                '<span style="color:#16a34a;">' + exact + ' existing</span>, ' +
                '<span style="color:#2563eb;">' + strong + ' likely matches</span>, ' +
                '<span style="color:#d97706;">' + weak + ' name matches</span>, ' +
                '<span style="color:#64748b;">' + none + ' new</span>';

            previewDiv.style.display = '';
            updateConfirmBtn();
        })
        .catch(function(err) {
            showSpinner(false);
            btnPreview.disabled = false;
            statusEl.textContent = 'Error: ' + err.message;
        });
    });

    btnCancel.addEventListener('click', function() {
        previewDiv.style.display = 'none';
        previewData = [];
    });

    selectAll.addEventListener('change', function() {
        var checks = tbody.querySelectorAll('.cd-import-check');
        for (var i = 0; i < checks.length; i++) {
            checks[i].checked = selectAll.checked;
        }
        updateConfirmBtn();
    });

    tbody.addEventListener('change', function() {
        updateConfirmBtn();
    });

    function updateConfirmBtn() {
        var checks = tbody.querySelectorAll('.cd-import-check:checked');
        btnConfirm.disabled = checks.length === 0;
        btnConfirm.textContent = checks.length > 0
            ? '<?php echo esc_js( __( 'Import Selected', 'community-directory' ) ); ?> (' + checks.length + ')'
            : '<?php echo esc_js( __( 'Import Selected', 'community-directory' ) ); ?>';
    }

    btnConfirm.addEventListener('click', function() {
        var checks = tbody.querySelectorAll('.cd-import-check:checked');
        var selected = [];
        for (var i = 0; i < checks.length; i++) {
            selected.push(previewData[parseInt(checks[i].dataset.index)]);
        }

        if (selected.length === 0) return;

        if (!confirm('<?php echo esc_js( __( 'Import', 'community-directory' ) ); ?> ' + selected.length + ' <?php echo esc_js( __( 'contact(s) as new members?', 'community-directory' ) ); ?>')) {
            return;
        }

        showSpinner(true);
        statusEl.textContent = '<?php echo esc_js( __( 'Importing...', 'community-directory' ) ); ?>';
        btnConfirm.disabled = true;

        fetch(apiBase + '/google-sync/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ contacts: selected })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            showSpinner(false);
            if (res.success) {
                statusEl.innerHTML = '<span style="color:#16a34a;">' +
                    (res.data.imported || 0) + ' <?php echo esc_js( __( 'contact(s) imported successfully.', 'community-directory' ) ); ?></span>';
                previewDiv.style.display = 'none';
                previewData = [];
            } else {
                statusEl.textContent = res.data || 'Import failed.';
                btnConfirm.disabled = false;
            }
        })
        .catch(function(err) {
            showSpinner(false);
            statusEl.textContent = 'Error: ' + err.message;
            btnConfirm.disabled = false;
        });
    });

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>
<?php endif; ?>
