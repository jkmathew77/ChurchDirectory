<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_enabled = ( '1' === get_option( 'cd_debug_logging', '1' ) );
$log_size   = CD_Logger::get_file_size();
$log_path   = CD_Logger::get_log_file();
$nonce      = wp_create_nonce( 'cd_debug_log_actions' );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Debug Log', 'community-directory' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Plugin-specific debug log. Use this to diagnose OAuth, routing, and database issues without sifting through WordPress core logs.', 'community-directory' ); ?>
    </p>

    <!-- Controls Bar -->
    <div style="display:flex; align-items:center; gap:16px; margin:16px 0; flex-wrap:wrap;">
        <!-- Toggle Switch -->
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-weight:600;"><?php esc_html_e( 'Logging:', 'community-directory' ); ?></span>
            <button type="button" id="cd-log-toggle" class="button <?php echo $is_enabled ? 'button-primary' : ''; ?>"
                style="min-width:70px;">
                <?php echo $is_enabled ? esc_html__( 'ON', 'community-directory' ) : esc_html__( 'OFF', 'community-directory' ); ?>
            </button>
            <span id="cd-log-status" style="color:<?php echo $is_enabled ? '#16a34a' : '#94a3b8'; ?>; font-weight:600;">
                &#9679; <?php echo $is_enabled
                    ? esc_html__( 'Active', 'community-directory' )
                    : esc_html__( 'Disabled', 'community-directory' ); ?>
            </span>
        </div>

        <span style="color:#ccc;">|</span>

        <!-- Action Buttons -->
        <button type="button" id="cd-log-refresh" class="button">
            <?php esc_html_e( 'Refresh', 'community-directory' ); ?>
        </button>
        <button type="button" id="cd-log-clear" class="button" style="color:#dc2626;">
            <?php esc_html_e( 'Clear Log', 'community-directory' ); ?>
        </button>

        <span style="color:#ccc;">|</span>

        <!-- Lines selector -->
        <label>
            <?php esc_html_e( 'Show last', 'community-directory' ); ?>
            <select id="cd-log-lines">
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200" selected>200</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
            </select>
            <?php esc_html_e( 'lines', 'community-directory' ); ?>
        </label>

        <!-- File Info -->
        <span style="color:#64748b; font-size:0.85em; margin-left:auto;">
            <?php esc_html_e( 'File:', 'community-directory' ); ?>
            <code style="font-size:0.9em;"><?php echo esc_html( $log_path ); ?></code>
            &mdash;
            <span id="cd-log-size"><?php echo esc_html( size_format( $log_size ) ); ?></span>
        </span>
    </div>

    <!-- Status Message -->
    <div id="cd-log-message" style="display:none; padding:8px 12px; margin-bottom:12px; border-left:4px solid #2271b1; background:#f0f6fc;"></div>

    <!-- Log Output -->
    <div style="position:relative;">
        <pre id="cd-log-content" style="
            background: #1e1e2e;
            color: #cdd6f4;
            padding: 16px;
            border-radius: 6px;
            font-family: 'Fira Code', 'Consolas', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            max-height: 600px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
        "><?php echo esc_html( CD_Logger::tail( 200 ) ); ?></pre>
    </div>
</div>

<style>
#cd-log-content .log-error { color: #f38ba8; font-weight: 600; }
#cd-log-content .log-warn  { color: #fab387; }
#cd-log-content .log-info  { color: #a6e3a1; }
#cd-log-content .log-debug { color: #94a3b8; }
</style>

<script>
jQuery(function($) {
    var nonce = <?php echo wp_json_encode( $nonce ); ?>;
    var $content  = $('#cd-log-content');
    var $toggle   = $('#cd-log-toggle');
    var $status   = $('#cd-log-status');
    var $message  = $('#cd-log-message');
    var $size     = $('#cd-log-size');

    function showMsg(text, isError) {
        $message.text(text)
            .css('border-color', isError ? '#dc2626' : '#2271b1')
            .css('background', isError ? '#fef2f2' : '#f0f6fc')
            .fadeIn(200);
        setTimeout(function() { $message.fadeOut(400); }, 3000);
    }

    function colorize(text) {
        return text.replace(/^(\[.*?\]) \[(ERROR)\](.*)/gm, '$1 [<span class="log-error">$2</span>]<span class="log-error">$3</span>')
                   .replace(/^(\[.*?\]) \[(WARN)\](.*)/gm,  '$1 [<span class="log-warn">$2</span>]<span class="log-warn">$3</span>')
                   .replace(/^(\[.*?\]) \[(INFO)\](.*)/gm,  '$1 [<span class="log-info">$2</span>]$3')
                   .replace(/^(\[.*?\]) \[(DEBUG)\](.*)/gm, '$1 [<span class="log-debug">$2</span>]<span class="log-debug">$3</span>');
    }

    function loadLog() {
        var lines = parseInt($('#cd-log-lines').val(), 10);
        $.post(ajaxurl, { action: 'cd_refresh_debug_log', nonce: nonce, lines: lines }, function(r) {
            if (r.success) {
                var raw = r.data.content || '(empty log)';
                $content.html(colorize($('<div>').text(raw).html()));
                $content.scrollTop($content[0].scrollHeight);
                $size.text(formatSize(r.data.size));
                updateToggleUI(r.data.enabled);
            }
        });
    }

    function updateToggleUI(enabled) {
        if (enabled) {
            $toggle.addClass('button-primary').text('ON');
            $status.html('&#9679; Active').css('color', '#16a34a');
        } else {
            $toggle.removeClass('button-primary').text('OFF');
            $status.html('&#9679; Disabled').css('color', '#94a3b8');
        }
    }

    function formatSize(bytes) {
        if (bytes === 0) return '0 B';
        var units = ['B', 'KB', 'MB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0) + ' ' + units[i];
    }

    // Toggle logging
    $toggle.on('click', function() {
        $.post(ajaxurl, { action: 'cd_toggle_debug_logging', nonce: nonce }, function(r) {
            if (r.success) {
                updateToggleUI(r.data.enabled);
                showMsg(r.data.message);
            }
        });
    });

    // Refresh
    $('#cd-log-refresh').on('click', function() { loadLog(); });
    $('#cd-log-lines').on('change', function() { loadLog(); });

    // Clear
    $('#cd-log-clear').on('click', function() {
        if (!confirm(<?php echo wp_json_encode( __( 'Clear the entire debug log?', 'community-directory' ) ); ?>)) return;
        $.post(ajaxurl, { action: 'cd_clear_debug_log', nonce: nonce }, function(r) {
            if (r.success) {
                showMsg(r.data.message);
                loadLog();
            }
        });
    });

    // Colorize initial content
    var initial = $content.text();
    $content.html(colorize($('<div>').text(initial).html()));
    $content.scrollTop($content[0].scrollHeight);
});
</script>
