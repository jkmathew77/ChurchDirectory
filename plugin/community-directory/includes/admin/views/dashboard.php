<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$members_table      = CD_Database::table( 'members' );
$applications_table = CD_Database::table( 'applications' );
$households_table   = CD_Database::table( 'households' );
$profiles_table     = CD_Database::table( 'directory_profiles' );
$deletion_table     = CD_Database::table( 'deletion_requests' );

// Counts
$total_members          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$members_table} WHERE status = 'active'" );
$pending_applications   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$applications_table} WHERE status = 'new'" );
$pending_verifications  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$applications_table} WHERE status = 'pending_verification'" );
$pending_deletions      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$deletion_table} WHERE status = 'pending'" );
$total_households       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$households_table} WHERE status = 'active'" );
$incomplete_profiles    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$profiles_table} WHERE profile_completion < 80" );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Community Directory', 'community-directory' ); ?></h1>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-top: 20px;">

        <div class="card" style="padding: 16px;">
            <h2 style="margin-top:0; font-size: 28px;"><?php echo esc_html( $total_members ); ?></h2>
            <p style="color: #666; margin-bottom: 0;"><?php esc_html_e( 'Active Members', 'community-directory' ); ?></p>
        </div>

        <div class="card" style="padding: 16px;">
            <h2 style="margin-top:0; font-size: 28px;"><?php echo esc_html( $pending_applications ); ?></h2>
            <p style="color: #666; margin-bottom: 0;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cd-applications' ) ); ?>">
                    <?php esc_html_e( 'Pending Applications', 'community-directory' ); ?>
                </a>
            </p>
        </div>

        <div class="card" style="padding: 16px;">
            <h2 style="margin-top:0; font-size: 28px;"><?php echo esc_html( $pending_verifications ); ?></h2>
            <p style="color: #666; margin-bottom: 0;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cd-registrations' ) ); ?>">
                    <?php esc_html_e( 'Awaiting Verification', 'community-directory' ); ?>
                </a>
            </p>
        </div>

        <div class="card" style="padding: 16px;">
            <h2 style="margin-top:0; font-size: 28px;"><?php echo esc_html( $pending_deletions ); ?></h2>
            <p style="color: #666; margin-bottom: 0;">
                <?php esc_html_e( 'Deletion Requests', 'community-directory' ); ?>
            </p>
        </div>

        <div class="card" style="padding: 16px;">
            <h2 style="margin-top:0; font-size: 28px;"><?php echo esc_html( $total_households ); ?></h2>
            <p style="color: #666; margin-bottom: 0;"><?php esc_html_e( 'Households', 'community-directory' ); ?></p>
        </div>

        <div class="card" style="padding: 16px;">
            <h2 style="margin-top:0; font-size: 28px;"><?php echo esc_html( $incomplete_profiles ); ?></h2>
            <p style="color: #666; margin-bottom: 0;"><?php esc_html_e( 'Incomplete Profiles', 'community-directory' ); ?></p>
        </div>

    </div>

    <div class="card" style="padding: 16px; margin-top: 20px; max-width: 600px;">
        <h3 style="margin-top: 0;"><?php esc_html_e( 'System Health', 'community-directory' ); ?></h3>
        <table class="widefat" style="border: none;">
            <tbody>
                <tr>
                    <td><?php esc_html_e( 'Database', 'community-directory' ); ?></td>
                    <td><span style="color: green;">&#9679;</span> <?php esc_html_e( 'Connected', 'community-directory' ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'DB Version', 'community-directory' ); ?></td>
                    <td><?php echo esc_html( get_option( 'cd_db_version', 'N/A' ) ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Plugin Version', 'community-directory' ); ?></td>
                    <td><?php echo esc_html( CD_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'MySQL Version', 'community-directory' ); ?></td>
                    <td><?php global $wpdb; echo esc_html( $wpdb->db_version() ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'JSON Support', 'community-directory' ); ?></td>
                    <td>
                        <?php if ( CD_Database::supports_json() ) : ?>
                            <span style="color: green;">&#9679;</span> <?php esc_html_e( 'Native JSON', 'community-directory' ); ?>
                        <?php else : ?>
                            <span style="color: orange;">&#9679;</span> <?php esc_html_e( 'LONGTEXT fallback', 'community-directory' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Google OAuth', 'community-directory' ); ?></td>
                    <td>
                        <?php if ( get_option( 'cd_google_oauth_enabled' ) === '1' ) : ?>
                            <span style="color: green;">&#9679;</span> <?php esc_html_e( 'Configured', 'community-directory' ); ?>
                        <?php else : ?>
                            <span style="color: #999;">&#9679;</span> <?php esc_html_e( 'Not configured', 'community-directory' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php
    // ── Security Checklist (PRD Section 10.6) ──
    $checks = array();

    // 1. SSL/HTTPS active
    $checks[] = array(
        'label'    => __( 'SSL/HTTPS Active', 'community-directory' ),
        'pass'     => is_ssl(),
        'fail_msg' => __( 'Your site is not using HTTPS. Member data is transmitted unencrypted. Enable SSL immediately.', 'community-directory' ),
    );

    // 2. WP_DEBUG disabled
    $checks[] = array(
        'label'    => __( 'WP_DEBUG Disabled', 'community-directory' ),
        'pass'     => ! defined( 'WP_DEBUG' ) || ! WP_DEBUG,
        'fail_msg' => __( 'Debug mode is on. Error messages may expose file paths and database details. Set WP_DEBUG to false in wp-config.php.', 'community-directory' ),
    );

    // 3. WP_DEBUG_DISPLAY disabled
    $checks[] = array(
        'label'    => __( 'WP_DEBUG_DISPLAY Disabled', 'community-directory' ),
        'pass'     => ! defined( 'WP_DEBUG_DISPLAY' ) || ! WP_DEBUG_DISPLAY,
        'fail_msg' => __( 'Debug display is on. PHP errors may be shown to visitors.', 'community-directory' ),
    );

    // 4. DISALLOW_FILE_EDIT
    $checks[] = array(
        'label'    => __( 'File Editor Disabled', 'community-directory' ),
        'pass'     => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
        'fail_msg' => __( 'File editing is enabled in WordPress admin. Add define(\'DISALLOW_FILE_EDIT\', true) to wp-config.php.', 'community-directory' ),
    );

    // 5. Table prefix not default
    $checks[] = array(
        'label'    => __( 'Custom Table Prefix', 'community-directory' ),
        'pass'     => $wpdb->prefix !== 'wp_',
        'fail_msg' => __( 'Your database uses the default table prefix. Consider changing it to reduce automated attack surface.', 'community-directory' ),
        'warn'     => true,
    );

    // 6. WordPress version up to date
    $wp_is_latest = true;
    $wp_version_check = get_site_transient( 'update_core' );
    if ( $wp_version_check && ! empty( $wp_version_check->updates ) ) {
        foreach ( $wp_version_check->updates as $update ) {
            if ( 'upgrade' === $update->response ) {
                $wp_is_latest = false;
                break;
            }
        }
    }
    $checks[] = array(
        'label'    => __( 'WordPress Up to Date', 'community-directory' ),
        'pass'     => $wp_is_latest,
        'fail_msg' => __( 'Your WordPress version is outdated. Update to receive security patches.', 'community-directory' ),
    );

    // 7. PHP version
    $checks[] = array(
        'label'    => sprintf( __( 'PHP Version (%s)', 'community-directory' ), PHP_VERSION ),
        'pass'     => version_compare( PHP_VERSION, '7.4', '>=' ),
        'fail_msg' => __( 'Your PHP version is outdated and may have known vulnerabilities. Upgrade to PHP 7.4 or later.', 'community-directory' ),
    );

    // 8. AUTH_KEY and salts configured
    $auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
    $salt_ok  = ! empty( $auth_key ) && $auth_key !== 'put your unique phrase here' && strlen( $auth_key ) >= 32;
    $checks[] = array(
        'label'    => __( 'Security Keys Configured', 'community-directory' ),
        'pass'     => $salt_ok,
        'fail_msg' => __( 'Your WordPress security keys are set to default values. Generate new keys at api.wordpress.org/secret-key.', 'community-directory' ),
    );

    // 9. XML-RPC disabled
    $xmlrpc_disabled = has_filter( 'xmlrpc_enabled', '__return_false' ) || has_filter( 'xmlrpc_methods', '__return_empty_array' );
    $checks[] = array(
        'label'    => __( 'XML-RPC Disabled', 'community-directory' ),
        'pass'     => $xmlrpc_disabled,
        'fail_msg' => __( 'XML-RPC is enabled. If not needed, disable it to reduce attack surface. The Community Directory does not require XML-RPC.', 'community-directory' ),
        'warn'     => true,
    );

    $pass_count  = count( array_filter( $checks, function( $c ) { return $c['pass']; } ) );
    $total_count = count( $checks );
    ?>
    <div class="card" style="padding: 16px; margin-top: 20px; max-width: 700px;">
        <h3 style="margin-top: 0;">
            <?php esc_html_e( 'Security Checklist', 'community-directory' ); ?>
            <span style="font-weight: normal; font-size: 0.85em; color: <?php echo $pass_count === $total_count ? 'green' : '#d97706'; ?>;">
                (<?php echo esc_html( $pass_count . '/' . $total_count ); ?>)
            </span>
        </h3>
        <table class="widefat" style="border: none;">
            <tbody>
                <?php foreach ( $checks as $check ) : ?>
                    <tr>
                        <td style="width: 30px; text-align: center;">
                            <?php if ( $check['pass'] ) : ?>
                                <span style="color: green; font-size: 1.2em;">&#10003;</span>
                            <?php elseif ( ! empty( $check['warn'] ) ) : ?>
                                <span style="color: #d97706; font-size: 1.2em;">&#9888;</span>
                            <?php else : ?>
                                <span style="color: #dc2626; font-size: 1.2em;">&#10007;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html( $check['label'] ); ?></strong>
                            <?php if ( ! $check['pass'] ) : ?>
                                <br><span style="color: #666; font-size: 0.9em;"><?php echo esc_html( $check['fail_msg'] ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
