<?php
/**
 * Community Directory — View Member Profile.
 * Stub for Phase 3.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 * Member UUID is passed to JS via wp_add_inline_script in class-plugin.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug     = get_option( 'cd_base_slug', 'community' );
$directory_url = home_url( $base_slug . '/directory/' );
$login_url     = home_url( $base_slug . '/login/' );

if ( ! is_user_logged_in() ) {
    wp_redirect( $login_url );
    exit;
}

get_header();
?>

<div class="cd-wrap cd-member-profile" x-data="cdMemberProfile">
    <div class="cd-container">
        <div class="cd-page-header">
            <a href="<?php echo esc_url( $directory_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Directory', 'community-directory' ); ?>
            </a>
            <h1 class="cd-title"><?php esc_html_e( 'Member Profile', 'community-directory' ); ?></h1>
        </div>

        <div class="cd-main">
            <div class="cd-card">
                <p class="cd-text-muted cd-text-center">
                    <?php esc_html_e( 'Member profiles will be available in Phase 3.', 'community-directory' ); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
