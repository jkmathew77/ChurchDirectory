<?php
/**
 * Admin view: Merge Members (duplicate detection + merge tool).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_base = esc_url( rest_url( CD_API_NAMESPACE ) );
$nonce    = wp_create_nonce( 'wp_rest' );
?>

<style>
.cd-merge-cluster { background: #fff; border: 1px solid #c3c4c7; padding: 16px; margin-bottom: 16px; border-radius: 4px; }
.cd-merge-cluster h3 { margin: 0 0 8px; font-size: 14px; color: #1d2327; }
.cd-merge-cluster .cd-confidence { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 3px; margin-left: 8px; font-weight: 600; }
.cd-confidence-high { background: #fecaca; color: #991b1b; }
.cd-confidence-medium { background: #fef3c7; color: #92400e; }
.cd-merge-members { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
.cd-merge-member { border: 2px solid #e5e7eb; padding: 12px; border-radius: 6px; cursor: pointer; position: relative; }
.cd-merge-member.selected { border-color: #2271b1; background: #f0f6fc; }
.cd-merge-member h4 { margin: 0 0 4px; font-size: 14px; }
.cd-merge-member p { margin: 2px 0; font-size: 12px; color: #50575e; }
.cd-merge-member .cd-radio-indicator { position: absolute; top: 8px; right: 8px; width: 18px; height: 18px; border: 2px solid #c3c4c7; border-radius: 50%; }
.cd-merge-member.selected .cd-radio-indicator { border-color: #2271b1; background: #2271b1; }
.cd-merge-member.selected .cd-radio-indicator::after { content: ''; position: absolute; top: 3px; left: 3px; width: 8px; height: 8px; background: #fff; border-radius: 50%; }
.cd-merge-actions { margin-top: 12px; display: flex; gap: 8px; }
.cd-merge-result { margin-top: 12px; padding: 10px; border-radius: 4px; display: none; }
.cd-merge-result.success { display: block; background: #d1fae5; color: #065f46; }
.cd-merge-result.error { display: block; background: #fee2e2; color: #991b1b; }
.cd-empty-state { text-align: center; padding: 40px 20px; color: #50575e; }
.cd-loading { text-align: center; padding: 20px; color: #50575e; }
</style>

<div class="wrap">
    <h1><?php esc_html_e( 'Merge Members — Duplicate Detection', 'community-directory' ); ?></h1>
    <p><?php esc_html_e( 'Members sharing the same name or email are grouped below. Select the primary record to keep, then merge.', 'community-directory' ); ?></p>
    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cd-members' ) ); ?>">&larr; <?php esc_html_e( 'Back to Members', 'community-directory' ); ?></a></p>

    <div id="cd-merge-loading" class="cd-loading"><?php esc_html_e( 'Scanning for duplicates...', 'community-directory' ); ?></div>
    <div id="cd-merge-empty" class="cd-empty-state" style="display:none;">
        <h2><?php esc_html_e( 'No duplicates found', 'community-directory' ); ?></h2>
        <p><?php esc_html_e( 'All member records appear to be unique.', 'community-directory' ); ?></p>
    </div>
    <div id="cd-merge-list"></div>
</div>

<script>
(function() {
    const API  = '<?php echo $api_base; ?>';
    const nonce = '<?php echo $nonce; ?>';

    function loadDuplicates() {
        fetch(API + '/admin/members/duplicates', {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(r => r.json())
        .then(res => {
            document.getElementById('cd-merge-loading').style.display = 'none';
            if (!res.success || !res.data.duplicates.length) {
                document.getElementById('cd-merge-empty').style.display = 'block';
                return;
            }
            renderClusters(res.data.duplicates);
        })
        .catch(() => {
            document.getElementById('cd-merge-loading').innerHTML = 'Error loading duplicates.';
        });
    }

    function renderClusters(clusters) {
        const list = document.getElementById('cd-merge-list');
        list.innerHTML = '';

        clusters.forEach((cluster, ci) => {
            const div = document.createElement('div');
            div.className = 'cd-merge-cluster';
            div.innerHTML =
                '<h3>' + escHtml(cluster.reason) +
                ' <span class="cd-confidence cd-confidence-' + cluster.confidence + '">' + cluster.confidence + '</span></h3>' +
                '<p style="font-size:12px;color:#6b7280;">Click a member to select as the <strong>primary</strong> record to keep:</p>' +
                '<div class="cd-merge-members" id="cd-cluster-' + ci + '">' +
                cluster.members.map((m, mi) =>
                    '<div class="cd-merge-member" data-id="' + m.id + '" onclick="selectPrimary(' + ci + ',' + m.id + ')">' +
                    '<div class="cd-radio-indicator"></div>' +
                    '<h4>' + escHtml(m.first_name + ' ' + m.last_name) + '</h4>' +
                    '<p>Email: ' + escHtml(m.email || '(none)') + '</p>' +
                    '<p>Status: ' + escHtml(m.status) + ' | Created: ' + escHtml(m.created_at || '') + '</p>' +
                    '<p>ID: ' + m.id + '</p>' +
                    '</div>'
                ).join('') +
                '</div>' +
                '<div class="cd-merge-actions">' +
                '<button class="button button-primary" id="cd-merge-btn-' + ci + '" disabled onclick="doMerge(' + ci + ')">' +
                '<?php esc_html_e( 'Merge Selected', 'community-directory' ); ?></button>' +
                '</div>' +
                '<div class="cd-merge-result" id="cd-merge-result-' + ci + '"></div>';
            list.appendChild(div);
        });

        window._clusters = clusters;
    }

    window.selectPrimary = function(ci, id) {
        const container = document.getElementById('cd-cluster-' + ci);
        container.querySelectorAll('.cd-merge-member').forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.id) === id);
        });
        window._clusters[ci]._selectedPrimary = id;
        document.getElementById('cd-merge-btn-' + ci).disabled = false;
    };

    window.doMerge = function(ci) {
        const cluster = window._clusters[ci];
        const primaryId = cluster._selectedPrimary;
        if (!primaryId) return;

        const secondaryIds = cluster.members.filter(m => m.id !== primaryId).map(m => m.id);
        if (!secondaryIds.length) return;

        if (!confirm('<?php echo esc_js( __( 'This will permanently merge the duplicate into the selected primary record. Continue?', 'community-directory' ) ); ?>')) return;

        const btn = document.getElementById('cd-merge-btn-' + ci);
        const result = document.getElementById('cd-merge-result-' + ci);
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js( __( 'Merging...', 'community-directory' ) ); ?>';

        // Merge one at a time (if >2 duplicates)
        let chain = Promise.resolve();
        secondaryIds.forEach(sid => {
            chain = chain.then(() =>
                fetch(API + '/admin/members/merge', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ primary_id: primaryId, secondary_id: sid })
                }).then(r => r.json())
            );
        });

        chain.then(res => {
            if (res && res.success) {
                result.className = 'cd-merge-result success';
                result.textContent = '<?php echo esc_js( __( 'Merge completed. Refresh to update the list.', 'community-directory' ) ); ?>';
            } else {
                result.className = 'cd-merge-result error';
                result.textContent = (res && res.message) || 'Merge failed.';
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js( __( 'Merge Selected', 'community-directory' ) ); ?>';
            }
        }).catch(() => {
            result.className = 'cd-merge-result error';
            result.textContent = 'Network error.';
            btn.disabled = false;
            btn.textContent = '<?php echo esc_js( __( 'Merge Selected', 'community-directory' ) ); ?>';
        });
    };

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    loadDuplicates();
})();
</script>
