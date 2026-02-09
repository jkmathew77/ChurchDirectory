<?php
/**
 * Community Directory Landing Page.
 * Shown to unauthenticated visitors.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug = get_option( 'cd_base_slug', 'community' );
$login_url = home_url( $base_slug . '/login/' );
$apply_url = home_url( $base_slug . '/apply/' );

get_header();
?>

<div class="cd-wrap cd-landing" x-data="{ ready: true }">
    <div class="cd-container">
        <div class="cd-page-header">
            <h1 class="cd-title"><?php esc_html_e( 'Community Directory', 'community-directory' ); ?></h1>
        </div>

        <div class="cd-main">
            <div class="cd-card cd-landing-card">
                <h2><?php esc_html_e( 'Welcome', 'community-directory' ); ?></h2>
                <p><?php esc_html_e( 'Connect with fellow parishioners through our members-only community directory.', 'community-directory' ); ?></p>

                <div class="cd-actions">
                    <a href="<?php echo esc_url( $login_url ); ?>" class="cd-btn cd-btn-primary">
                        <?php esc_html_e( 'Member Login', 'community-directory' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $apply_url ); ?>" class="cd-btn cd-btn-secondary">
                        <?php esc_html_e( 'Become a Member', 'community-directory' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
