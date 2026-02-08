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
</div>
