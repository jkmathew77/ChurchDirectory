<?php
/**
 * Admin view: Reports & Analytics.
 * Displays member growth chart, status breakdown, household stats, and recent activity.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_base = esc_url( rest_url( CD_API_NAMESPACE ) );
$nonce    = wp_create_nonce( 'wp_rest' );
?>

<style>
.cd-reports-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
.cd-report-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; }
.cd-report-card h2 { font-size: 15px; margin: 0 0 16px; color: #1d2327; border-bottom: 1px solid #f0f0f1; padding-bottom: 8px; }
.cd-report-card canvas { max-height: 260px; }
.cd-stat-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
.cd-stat-row:last-child { border-bottom: none; }
.cd-stat-label { color: #50575e; }
.cd-stat-value { font-weight: 600; color: #1d2327; }
.cd-activity-list { list-style: none; margin: 0; padding: 0; max-height: 320px; overflow-y: auto; }
.cd-activity-list li { padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-size: 12px; }
.cd-activity-list li:last-child { border-bottom: none; }
.cd-activity-type { font-weight: 600; color: #2271b1; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
.cd-activity-time { color: #9ca3af; float: right; }
@media (max-width: 960px) { .cd-reports-grid { grid-template-columns: 1fr; } }
</style>

<div class="wrap">
    <h1><?php esc_html_e( 'Reports & Analytics', 'community-directory' ); ?></h1>

    <div class="cd-reports-grid">
        <!-- Member Growth Chart -->
        <div class="cd-report-card">
            <h2><?php esc_html_e( 'Member Growth (Last 12 Months)', 'community-directory' ); ?></h2>
            <canvas id="cd-chart-growth"></canvas>
        </div>

        <!-- Status Breakdown -->
        <div class="cd-report-card">
            <h2><?php esc_html_e( 'Member Status Breakdown', 'community-directory' ); ?></h2>
            <canvas id="cd-chart-status"></canvas>
        </div>

        <!-- Key Metrics -->
        <div class="cd-report-card">
            <h2><?php esc_html_e( 'Key Metrics', 'community-directory' ); ?></h2>
            <div id="cd-metrics">
                <div class="cd-stat-row"><span class="cd-stat-label"><?php esc_html_e( 'Loading...', 'community-directory' ); ?></span></div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="cd-report-card">
            <h2><?php esc_html_e( 'Recent Activity', 'community-directory' ); ?></h2>
            <ul class="cd-activity-list" id="cd-activity">
                <li><?php esc_html_e( 'Loading...', 'community-directory' ); ?></li>
            </ul>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
    const API   = '<?php echo $api_base; ?>';
    const nonce = '<?php echo $nonce; ?>';

    fetch(API + '/admin/stats', { headers: { 'X-WP-Nonce': nonce } })
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;
        const d = res.data;

        // Growth chart
        const months = (d.members_by_month || []).map(m => m.month);
        const counts = (d.members_by_month || []).map(m => parseInt(m.count));

        new Chart(document.getElementById('cd-chart-growth'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: '<?php echo esc_js( __( 'New Members', 'community-directory' ) ); ?>',
                    data: counts,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34,113,177,0.1)',
                    fill: true,
                    tension: 0.3,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Status chart
        const sc = d.status_counts || {};
        const statLabels = Object.keys(sc);
        const statValues = Object.values(sc);
        const colors = { active: '#16a34a', inactive: '#9ca3af', archived: '#d97706', deceased: '#64748b' };
        const bgColors = statLabels.map(s => colors[s] || '#2271b1');

        new Chart(document.getElementById('cd-chart-status'), {
            type: 'doughnut',
            data: {
                labels: statLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{ data: statValues, backgroundColor: bgColors }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        // Key Metrics
        const totalMembers = Object.values(sc).reduce((a, b) => a + b, 0);
        const metrics = document.getElementById('cd-metrics');
        metrics.innerHTML =
            metricRow('<?php echo esc_js( __( 'Total Members', 'community-directory' ) ); ?>', totalMembers) +
            metricRow('<?php echo esc_js( __( 'Active Members', 'community-directory' ) ); ?>', sc.active || 0) +
            metricRow('<?php echo esc_js( __( 'Active Households', 'community-directory' ) ); ?>', d.household_stats ? d.household_stats.total : 0) +
            metricRow('<?php echo esc_js( __( 'Avg Household Size', 'community-directory' ) ); ?>', d.household_stats ? d.household_stats.avg_size : '-') +
            metricRow('<?php echo esc_js( __( 'Google Contacts Synced', 'community-directory' ) ); ?>', d.google_sync ? d.google_sync.synced : 0) +
            metricRow('<?php echo esc_js( __( 'Google Sync Pending', 'community-directory' ) ); ?>', d.google_sync ? d.google_sync.pending_retries : 0) +
            metricRow('<?php echo esc_js( __( 'Applications This Month', 'community-directory' ) ); ?>', d.applications_month || 0);

        // Activity
        const activity = document.getElementById('cd-activity');
        if (d.recent_activity && d.recent_activity.length) {
            activity.innerHTML = d.recent_activity.map(a => {
                const dt = a.created_at ? new Date(a.created_at).toLocaleString() : '';
                const details = a.details ? (a.details.action || a.details.member_name || '') : '';
                return '<li><span class="cd-activity-type">' + escHtml(a.event_type) + '</span> ' +
                       escHtml(details) +
                       '<span class="cd-activity-time">' + escHtml(dt) + '</span></li>';
            }).join('');
        } else {
            activity.innerHTML = '<li><?php echo esc_js( __( 'No recent activity.', 'community-directory' ) ); ?></li>';
        }
    });

    function metricRow(label, value) {
        return '<div class="cd-stat-row"><span class="cd-stat-label">' + label + '</span><span class="cd-stat-value">' + value + '</span></div>';
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }
})();
</script>
