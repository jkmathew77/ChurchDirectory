<?php
/**
 * Community Directory — Edit Own Profile.
 * Stub for Phase 3.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug     = get_option( 'cd_base_slug', 'community' );
$directory_url = home_url( $base_slug . '/directory/' );

get_header();
?>

<div class="cd-wrap cd-edit-profile" x-data="cdEditProfile()">
    <div class="cd-container">
        <div class="cd-page-header">
            <a href="<?php echo esc_url( $directory_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Directory', 'community-directory' ); ?>
            </a>
            <h1 class="cd-title"><?php esc_html_e( 'Edit Profile', 'community-directory' ); ?></h1>
        </div>

        <div class="cd-main">
            <div class="cd-card">
                <p class="cd-text-muted cd-text-center">
                    <?php esc_html_e( 'Profile editing will be available in Phase 3.', 'community-directory' ); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
