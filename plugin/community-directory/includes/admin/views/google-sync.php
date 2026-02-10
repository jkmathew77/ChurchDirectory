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

    <!-- CSV/Excel Import Section -->
    <div class="cd-admin-card" style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Import Members via CSV', 'community-directory' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Upload a CSV file to bulk import members.', 'community-directory' ); ?></p>
        
        <div style="margin:16px 0;">
            <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=cd_download_csv_template' ) ); ?>" class="button">
                <span class="dashicons dashicons-download" style="line-height:28px;"></span> <?php esc_html_e( 'Download Template', 'community-directory' ); ?>
            </a>
        </div>

        <div style="margin:16px 0; border: 2px dashed #c3c4c7; padding: 20px; text-align: center; background: #f9f9f9;" id="cd-dropzone">
            <p><?php esc_html_e( 'Drag and drop your CSV file here, or click to select.', 'community-directory' ); ?></p>
            <input type="file" id="cd-csv-upload" accept=".csv,application/vnd.ms-excel,text/csv" style="display:none;">
            <button type="button" class="button" id="cd-btn-select-file"><?php esc_html_e( 'Select File', 'community-directory' ); ?></button>
            <span id="cd-file-name" style="margin-left: 10px; font-weight: bold;"></span>
        </div>
        
        <div id="cd-csv-actions" style="display:none; margin-top:10px;">
            <button type="button" class="button button-primary" id="cd-btn-preview-csv">
                <?php esc_html_e( 'Preview Import', 'community-directory' ); ?>
            </button>
            <span id="cd-csv-spinner" class="spinner" style="float:none;"></span>
            <span id="cd-csv-status" style="margin-left:8px;"></span>
        </div>
    </div>
    
    <!-- Shared Preview Table (Used by both Google and CSV) -->
    <div class="cd-admin-card" id="cd-import-preview-container" style="display:none; background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;">
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

    <?php if ( $is_connected ) : ?>

    <!-- Import from Google Section -->
    <div class="cd-admin-card" style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Import Contacts from Google', 'community-directory' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Fetch contacts from the connected Google account.', 'community-directory' ); ?></p>

        <div id="cd-sync-controls" style="margin:16px 0;">
            <button type="button" class="button button-primary" id="cd-btn-preview-import">
                <?php esc_html_e( 'Fetch from Google', 'community-directory' ); ?>
            </button>
            <span id="cd-sync-spinner" class="spinner" style="float:none;"></span>
            <span id="cd-sync-status" style="margin-left:8px;"></span>
        </div>
    </div>
    
    <?php endif; ?>

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

    <?php if ( ! $is_connected ) : ?>
    <div class="cd-admin-card" style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Google Sync Setup', 'community-directory' ); ?></h2>
            <ol>
            <li><?php esc_html_e( 'Google Sync is optional. You can still use CSV import without it.', 'community-directory' ); ?></li>
            <li><?php esc_html_e( 'Go to the Google Cloud Console and create OAuth 2.0 credentials (Web application type).', 'community-directory' ); ?></li>
            <li><?php
                $callback_url = rest_url( 'community-directory/v1/admin/google/callback' );
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

    <!-- Modal for Confirmation -->
    <div id="cd-confirm-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; border-radius:4px; max-width:400px; width:100%; box-shadow:0 10px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;"><?php esc_html_e( 'Confirm Import', 'community-directory' ); ?></h3>
            <p id="cd-confirm-message"></p>
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="button" id="cd-modal-cancel"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="button button-primary" id="cd-modal-confirm"><?php esc_html_e( 'Import Members', 'community-directory' ); ?></button>
            </div>
        </div>
    </div>

</div>

<!-- CSS to hide modal by default but allow flex when active -->
<style>
#cd-confirm-modal[style*="display:none"] { display: none !important; }
</style>

<script>
(function() {
    var apiBase = <?php echo wp_json_encode( $api_base ); ?>;
    var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
    var previewData = [];
    var currentImportType = ''; // 'google' or 'csv'
    var selectedContacts = []; // Store selected contacts for modal

    // Google Buttons
    var btnPreviewGoogle = document.getElementById('cd-btn-preview-import'); // exists only if connected
    
    // CSV Elements
    var dropzone     = document.getElementById('cd-dropzone');
    var fileInput    = document.getElementById('cd-csv-upload');
    var btnSelectFile= document.getElementById('cd-btn-select-file');
    var fileNameSpan = document.getElementById('cd-file-name');
    var csvActions   = document.getElementById('cd-csv-actions');
    var btnPreviewCsv= document.getElementById('cd-btn-preview-csv');
    var spinnerCsv   = document.getElementById('cd-csv-spinner');
    var statusCsv    = document.getElementById('cd-csv-status');

    // Shared Elements
    var btnConfirm = document.getElementById('cd-btn-confirm-import');
    var btnCancel  = document.getElementById('cd-btn-cancel-import');
    var spinnerSync= document.getElementById('cd-sync-spinner'); // Google spinner
    var statusSync = document.getElementById('cd-sync-status'); // Google status
    var previewDiv = document.getElementById('cd-import-preview-container');
    var tbody      = document.getElementById('cd-import-tbody');
    var summary    = document.getElementById('cd-import-summary');
    var selectAll  = document.getElementById('cd-import-select-all');

    // Modal Elements
    var modal       = document.getElementById('cd-confirm-modal');
    var modalMsg    = document.getElementById('cd-confirm-message');
    var modalCancel = document.getElementById('cd-modal-cancel');
    var modalConfirm= document.getElementById('cd-modal-confirm');

    // Hide modal initially (JS enforcement)
    if (modal) modal.style.display = 'none';

    // --- CSV Dropzone Logic ---
    if (dropzone) {
        btnSelectFile.addEventListener('click', function() { fileInput.click(); });
        
        fileInput.addEventListener('change', handleFileSelect);

        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropzone.style.background = '#e5e7eb';
        });
        
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dropzone.style.background = '#f9f9f9';
        });
        
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropzone.style.background = '#f9f9f9';
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect();
            }
        });
    }

    function handleFileSelect() {
        if (fileInput.files.length > 0) {
             fileNameSpan.textContent = fileInput.files[0].name;
             csvActions.style.display = 'block';
             // Hide other preview if open
             previewDiv.style.display = 'none';
        }
    }

    // --- CSV Preview Click ---
    if (btnPreviewCsv) {
        btnPreviewCsv.addEventListener('click', function() {
            var file = fileInput.files[0];
            if (!file) return;

            currentImportType = 'csv';
            // Reset Google UI if active
            if (statusSync) statusSync.textContent = '';
            
            spinnerCsv.style.visibility = 'visible';
            spinnerCsv.classList.add('is-active');
            statusCsv.textContent = '<?php echo esc_js( __( 'Parsing CSV...', 'community-directory' ) ); ?>';
            statusCsv.className = ''; // Reset error classes
            btnPreviewCsv.disabled = true;

            var formData = new FormData();
            formData.append('file', file);

            fetch(apiBase + '/import/preview', {
                method: 'POST',
                headers: { 'X-WP-Nonce': nonce },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                 handlePreviewResponse(res, spinnerCsv, statusCsv, btnPreviewCsv);
            })
            .catch(function(err) {
                 handlePreviewError(err, spinnerCsv, statusCsv, btnPreviewCsv);
            });
        });
    }

    // --- Google Preview Click ---
    if (btnPreviewGoogle) {
        btnPreviewGoogle.addEventListener('click', function() {
            currentImportType = 'google';
            // Reset CSV UI if active
            if (statusCsv) statusCsv.textContent = '';

            spinnerSync.style.visibility = 'visible';
            spinnerSync.classList.add('is-active');
            statusSync.textContent = '<?php echo esc_js( __( 'Fetching contacts from Google...', 'community-directory' ) ); ?>';
            statusSync.className = ''; // Reset error classes
            btnPreviewGoogle.disabled = true;

            fetch(apiBase + '/google-sync/preview', {
                headers: { 'X-WP-Nonce': nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                handlePreviewResponse(res, spinnerSync, statusSync, btnPreviewGoogle);
            })
            .catch(function(err) {
                handlePreviewError(err, spinnerSync, statusSync, btnPreviewGoogle);
            });
        });
    }

    function handlePreviewResponse(res, spinner, statusEl, btn) {
        spinner.style.visibility = 'hidden';
        spinner.classList.remove('is-active');
        btn.disabled = false;

        if (!res.success) {
            statusEl.textContent = res.data.message || res.data || 'Error fetching data.';
            statusEl.className = 'error'; // Add WP error styling class if desired
            statusEl.style.color = '#dc2626';
            return;
        }

        previewData = res.data.contacts || [];
        statusEl.textContent = '';
        renderTable();
    }

    function handlePreviewError(err, spinner, statusEl, btn) {
        spinner.style.visibility = 'hidden';
        spinner.classList.remove('is-active');
        btn.disabled = false;
        statusEl.textContent = 'Error: ' + err.message;
        statusEl.style.color = '#dc2626';
    }

    function renderTable() {
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
                '<td>' + escHtml(c.name || (c.first_name + ' ' + c.last_name)) + '</td>' +
                '<td>' + escHtml(c.email) + '</td>' +
                '<td>' + escHtml(c.phone) + '</td>' +
                '<td>' + matchBadge + '</td>' +
                '</tr>';
            tbody.insertAdjacentHTML('beforeend', row);
        });

        summary.innerHTML = '<strong>' + previewData.length + '</strong> records: ' +
            '<span style="color:#16a34a;">' + exact + ' existing</span>, ' +
            '<span style="color:#2563eb;">' + strong + ' likely matches</span>, ' +
            '<span style="color:#64748b;">' + none + ' new</span>';

        previewDiv.style.display = 'block';
        updateConfirmBtn();
    }

    function updateConfirmBtn() {
        var checks = tbody.querySelectorAll('.cd-import-check:checked');
        btnConfirm.disabled = checks.length === 0;
        btnConfirm.textContent = checks.length > 0
            ? '<?php echo esc_js( __( 'Import Selected', 'community-directory' ) ); ?> (' + checks.length + ')'
            : '<?php echo esc_js( __( 'Import Selected', 'community-directory' ) ); ?>';
    }

    // Shared Event Listeners
    btnCancel.addEventListener('click', function() {
        previewDiv.style.display = 'none';
        previewData = [];
    });

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checks = tbody.querySelectorAll('.cd-import-check');
            for (var i = 0; i < checks.length; i++) {
                checks[i].checked = selectAll.checked;
            }
            updateConfirmBtn();
        });
    }

    tbody.addEventListener('change', function() {
        updateConfirmBtn();
    });

    // --- Modal Logic ---
    btnConfirm.addEventListener('click', function() {
        var checks = tbody.querySelectorAll('.cd-import-check:checked');
        selectedContacts = []; // Reset global
        for (var i = 0; i < checks.length; i++) {
            selectedContacts.push(previewData[parseInt(checks[i].dataset.index)]);
        }

        if (selectedContacts.length === 0) return;

        // Show Modal
        modalMsg.textContent = '<?php echo esc_js( __( 'Are you sure you want to import', 'community-directory' ) ); ?> ' + selectedContacts.length + ' <?php echo esc_js( __( 'member(s)?', 'community-directory' ) ); ?>';
        modal.style.display = 'flex';
    });

    modalCancel.addEventListener('click', function() {
        modal.style.display = 'none';
        selectedContacts = [];
    });

    modalConfirm.addEventListener('click', function() {
        modal.style.display = 'none'; // Hide modal
        performImport(selectedContacts); // Proceed with import
    });

    function performImport(contactsToImport) {
        btnConfirm.disabled = true;
        btnConfirm.textContent = '<?php echo esc_js( __( 'Importing...', 'community-directory' ) ); ?>';
        
        // Show a processing message in summary area
        var originalSummary = summary.innerHTML;
        summary.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> <?php echo esc_js( __( 'Processing import...', 'community-directory' ) ); ?>';

        var endpoint = currentImportType === 'google' ? '/google-sync/confirm' : '/import/confirm';

        fetch(apiBase + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ contacts: contactsToImport })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                // Success - reload page or show success
                summary.innerHTML = '<div class="notice notice-success inline"><p>' + (res.data.imported || 0) + ' <?php echo esc_js( __( 'imported successfully. Reloading...', 'community-directory' ) ); ?></p></div>';
                setTimeout(function() {
                    location.href = location.pathname + location.search + '&sync_success=1';
                }, 1500);
            } else {
                // Error handled nicely
                summary.innerHTML = originalSummary;
                alert(res.data || 'Import failed.'); // Fallback alert for critical failure info, or use a specific error div
                summary.insertAdjacentHTML('beforeend', '<div class="notice notice-error inline" style="margin-top:10px;"><p>' + (res.data || 'Import failed') + '</p></div>');
                
                btnConfirm.disabled = false;
                updateConfirmBtn();
            }
        })
        .catch(function(err) {
            summary.innerHTML = originalSummary;
            summary.insertAdjacentHTML('beforeend', '<div class="notice notice-error inline" style="margin-top:10px;"><p>Error: ' + err.message + '</p></div>');
            btnConfirm.disabled = false;
            updateConfirmBtn();
        });
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>

