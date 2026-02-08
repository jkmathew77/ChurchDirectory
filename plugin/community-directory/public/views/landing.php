<?php
/**
 * Community Directory Landing Page.
 * Shown to unauthenticated visitors.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug = get_option( 'cd_base_slug', 'community' );
$login_url = home_url( $base_slug . '/login/' );
$apply_url = home_url( $base_slug . '/apply/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — <?php esc_html_e( 'Community Directory', 'community-directory' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cd-page cd-landing" x-data="{ ready: true }">

    <div class="cd-container">
        <header class="cd-header">
            <?php
            $logo_url = '';
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            if ( $custom_logo_id ) {
                $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
            }
            ?>
            <?php if ( $logo_url ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="cd-logo">
            <?php endif; ?>
            <h1 class="cd-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
            <p class="cd-subtitle"><?php esc_html_e( 'Community Directory', 'community-directory' ); ?></p>
        </header>

        <main class="cd-main">
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
        </main>

        <footer class="cd-footer">
            <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
        </footer>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
